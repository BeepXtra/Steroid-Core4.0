<?php
defined('_SECURED') or die('Restricted access');

/**
 * SBlock — Block creation, validation, argon2id PoW, reward distribution.
 */
class SBlock {
    private Database $db;
    private Config   $cfg;
    private STx      $tx;

    public function __construct(Database $db, Config $cfg, STx $tx) {
        $this->db  = $db;
        $this->cfg = $cfg;
        $this->tx  = $tx;
    }

    // ─── Get ─────────────────────────────────────────────────

    public function getByHeight(int $height): ?array {
        return $this->db->fetchOne('SELECT * FROM blocks WHERE height = ?', [$height]);
    }

    public function getById(string $id): ?array {
        return $this->db->fetchOne('SELECT * FROM blocks WHERE id = ?', [$id]);
    }

    public function getLatest(): ?array {
        return $this->db->fetchOne('SELECT * FROM blocks ORDER BY height DESC LIMIT 1');
    }

    public function getHeight(): int {
        return (int)($this->db->fetchColumn('SELECT MAX(height) FROM blocks') ?? 0);
    }

    public function getLast(int $n): array {
        return $this->db->fetchAll(
            'SELECT * FROM blocks ORDER BY height DESC LIMIT ?', [$n]
        );
    }

    // ─── Block Creation ──────────────────────────────────────

    public function create(string $generator, string $nonce, string $argon, array $txs = []): array {
        $prev       = $this->getLatest();
        $height     = ($prev ? (int)$prev['height'] : 0) + 1;
        $difficulty = $this->getDifficulty();
        $date       = time();

        $block = [
            'generator'    => $generator,
            'height'       => $height,
            'date'         => $date,
            'nonce'        => $nonce,
            'difficulty'   => $difficulty,
            'argon'        => $argon,
            'transactions' => count($txs),
        ];
        $block['id']        = $this->blockId($block, $prev ? $prev['id'] : '0');
        $block['signature'] = $this->signBlock($block);

        return $block;
    }

    // ─── Commit Block ────────────────────────────────────────

    public function commit(array $block, array $txs, string $privateKeyPem): void {
        $this->db->transaction(function (Database $db) use ($block, $txs, $privateKeyPem) {
            // Insert block
            $db->insert('blocks', [
                'id'           => $block['id'],
                'generator'    => $block['generator'],
                'height'       => $block['height'],
                'date'         => $block['date'],
                'nonce'        => $block['nonce'],
                'signature'    => $block['signature'],
                'difficulty'   => $block['difficulty'],
                'argon'        => $block['argon'],
                'transactions' => count($txs),
            ]);

            // Execute transactions
            foreach ($txs as $tx) {
                $this->tx->execute($tx, $block['id'], $block['height']);
            }

            // Remove confirmed txs from mempool
            $this->tx->clearMempool(array_column($txs, 'id'));

            // Mining reward
            $this->distributeRewards($block);
        });
    }

    // ─── Reward Distribution ─────────────────────────────────

    private function distributeRewards(array $block): void {
        $totalReward = $this->calcBlockReward($block['height']);
        $mnShare     = round($totalReward * ($this->cfg->masternode_reward / 100), 8);
        $minerShare  = round($totalReward - $mnShare, 8);

        // Credit miner
        $this->db->execute(
            'UPDATE accounts SET balance = balance + ? WHERE id = ?',
            [$minerShare, $block['generator']]
        );

        // Masternode reward: pick winner (least recently won, active, not blacklisted)
        $mn = $this->db->fetchOne(
            'SELECT public_key FROM masternode
             WHERE status = 1 AND blacklist = 0
             ORDER BY last_won ASC LIMIT 1'
        );
        if ($mn) {
            // Derive address from MN public key
            $wallet = new SWallet($this->db);
            $mnAddr = $wallet->publicKeyToAddress($mn['public_key']);

            $this->db->execute(
                'UPDATE accounts SET balance = balance + ? WHERE id = ?',
                [$mnShare, $mnAddr]
            );
            $this->db->execute(
                'UPDATE masternode SET last_won = ? WHERE public_key = ?',
                [time(), $mn['public_key']]
            );
            $this->db->insert('masternode_rewards', [
                'public_key' => $mn['public_key'],
                'height'     => $block['height'],
                'reward'     => $mnShare,
                'date'       => $block['date'],
            ]);
        }
    }

    public function calcBlockReward(int $height): float {
        // Halving every 525600 blocks (~1 year at 60s blocks)
        $halvings = floor($height / 525600);
        $reward   = $this->cfg->mining_reward / pow(2, $halvings);
        return max(round($reward, 8), 0.00000001);
    }

    // ─── Difficulty ──────────────────────────────────────────

    public function getDifficulty(): string {
        $current = $this->db->fetchColumn(
            'SELECT difficulty FROM blocks ORDER BY height DESC LIMIT 1'
        );
        if (!$current) return str_pad('', 64, '0') . '00000ffff';

        $height = $this->getHeight();
        if ($height % $this->cfg->difficulty_retarget !== 0) return $current;

        return $this->retargetDifficulty($height, $current);
    }

    private function retargetDifficulty(int $height, string $current): string {
        $window = $this->cfg->difficulty_retarget;
        $blocks = $this->db->fetchAll(
            'SELECT date FROM blocks WHERE height > ? ORDER BY height ASC LIMIT ?',
            [$height - $window, $window]
        );
        if (count($blocks) < 2) return $current;

        $actualTime = end($blocks)['date'] - reset($blocks)['date'];
        $targetTime = $this->cfg->block_time_target * $window;
        $ratio      = $targetTime / max($actualTime, 1);
        $ratio      = min(max($ratio, 0.25), 4.0); // clamp 4x

        // Adjust as hex target
        $target  = gmp_init($current, 16);
        $newTarget = gmp_mul($target, gmp_init((int)($ratio * 1000)));
        $newTarget = gmp_div($newTarget, gmp_init(1000));
        return str_pad(gmp_strval($newTarget, 16), strlen($current), '0', STR_PAD_LEFT);
    }

    // ─── PoW Validation ──────────────────────────────────────

    public function validatePoW(array $block): bool {
        $hash = $this->argonHash($block);
        return $this->meetsTarget($hash, $block['difficulty']);
    }

    public function argonHash(array $block): string {
        $data = implode('-', [
            $block['generator'], $block['height'],
            $block['date'], $block['nonce'], $block['difficulty'],
        ]);
        return sodium_crypto_pwhash_str(
            $data,
            $this->cfg->argon2_time,
            $this->cfg->argon2_memory
        );
    }

    private function meetsTarget(string $hash, string $difficulty): bool {
        $hashHex   = bin2hex(hash('sha256', $hash, true));
        $hashInt   = gmp_init($hashHex, 16);
        $targetInt = gmp_init($difficulty, 16);
        return gmp_cmp($hashInt, $targetInt) <= 0;
    }

    // ─── Signature ───────────────────────────────────────────

    private function signBlock(array $block): string {
        $data = implode('-', [
            $block['id'], $block['generator'],
            $block['height'], $block['date'],
        ]);
        return hash('sha256', $data);
    }

    // ─── Block ID ────────────────────────────────────────────

    public function blockId(array $block, string $prevId): string {
        $data = implode('-', [
            $prevId, $block['generator'], $block['height'],
            $block['date'], $block['nonce'], $block['difficulty'],
        ]);
        return hash('sha256', $data);
    }

    // ─── Validate Block ──────────────────────────────────────

    public function validate(array $block): array {
        $errors = [];
        $prev   = $this->getLatest();
        $expectedHeight = ($prev ? (int)$prev['height'] : 0) + 1;

        if ((int)$block['height'] !== $expectedHeight)
            $errors[] = "Height mismatch: expected {$expectedHeight}";

        if (empty($block['generator']))
            $errors[] = 'Missing generator';

        if (!$this->validatePoW($block))
            $errors[] = 'PoW failed';

        if ($this->getById($block['id']))
            $errors[] = 'Duplicate block';

        return $errors;
    }
}
