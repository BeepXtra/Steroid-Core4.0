<?php
defined('_SECURED') or die('Restricted access');

/**
 * STx — Transaction Engine
 * Handles creation, validation, and execution of all tx versions (v0–v111).
 *
 * Version map:
 *  v0   — standard transfer
 *  v1   — transfer with message
 *  v2   — alias registration
 *  v3   — alias transfer
 *  v100 — masternode registration
 *  v101 — masternode deregister
 *  v102 — masternode pause
 *  v103 — masternode resume
 *  v104 — cold staking lock
 *  v105 — governance proposal
 *  v106 — governance vote
 *  v107 — governance close
 *  v110 — asset creation
 *  v111 — asset transfer
 */
class STx {
    private Database $db;
    private Config   $cfg;
    private SWallet  $wallet;

    const VERSION_TRANSFER         = 0;
    const VERSION_TRANSFER_MSG     = 1;
    const VERSION_ALIAS_REG        = 2;
    const VERSION_ALIAS_TRANSFER   = 3;
    const VERSION_MN_REG           = 100;
    const VERSION_MN_DEREG         = 101;
    const VERSION_MN_PAUSE         = 102;
    const VERSION_MN_RESUME        = 103;
    const VERSION_COLD_STAKE       = 104;
    const VERSION_GOV_PROPOSAL     = 105;
    const VERSION_GOV_VOTE         = 106;
    const VERSION_GOV_CLOSE        = 107;
    const VERSION_ASSET_CREATE     = 110;
    const VERSION_ASSET_TRANSFER   = 111;

    public function __construct(Database $db, Config $cfg, SWallet $wallet) {
        $this->db     = $db;
        $this->cfg    = $cfg;
        $this->wallet = $wallet;
    }

    // ─── Create Transaction ──────────────────────────────────

    public function create(array $data): array {
        $required = ['src','dst','val','fee','version','public_key','signature'];
        foreach ($required as $f) {
            if (!isset($data[$f])) throw new InvalidArgumentException("STx: missing field $f");
        }

        $tx = [
            'id'         => $this->txId($data),
            'src'        => $data['src'],
            'dst'        => $data['dst'],
            'val'        => number_format((float)$data['val'], 8, '.', ''),
            'fee'        => number_format((float)$data['fee'], 8, '.', ''),
            'signature'  => $data['signature'],
            'version'    => (int)$data['version'],
            'message'    => substr($data['message'] ?? '', 0, 256),
            'date'       => (int)($data['date'] ?? time()),
            'public_key' => $data['public_key'],
        ];

        return $tx;
    }

    // ─── Validate ────────────────────────────────────────────

    public function validate(array $tx): array {
        $errors = [];

        // Signature
        $sigStr = $this->wallet->txSignatureString($tx);
        if (!$this->wallet->verify($sigStr, $tx['signature'], $tx['public_key'])) {
            $errors[] = 'Invalid signature';
        }

        // Address derived from public key must match src
        $derivedAddr = $this->wallet->publicKeyToAddress($tx['public_key']);
        if ($derivedAddr !== $tx['src']) {
            $errors[] = 'Public key does not match src address';
        }

        // Fee check
        $minFee = $this->calcFee((float)$tx['val']);
        if ((float)$tx['fee'] < $minFee) {
            $errors[] = "Fee too low: minimum {$minFee}";
        }

        // Balance check
        $balance = (float)$this->wallet->getBalance($tx['src']);
        $total   = (float)$tx['val'] + (float)$tx['fee'];
        if ($balance < $total) {
            $errors[] = "Insufficient balance: have {$balance}, need {$total}";
        }

        // Duplicate
        if ($this->exists($tx['id'])) {
            $errors[] = 'Duplicate transaction';
        }

        // Version-specific
        $versionErrors = $this->validateVersion($tx);
        $errors        = array_merge($errors, $versionErrors);

        return $errors;
    }

    // ─── Execute (apply to DB, called inside block commit) ───

    public function execute(array $tx, string $blockId, int $height): void {
        $this->db->insert('transactions', array_merge($tx, [
            'block'  => $blockId,
            'height' => $height,
        ]));

        // Debit sender
        $this->wallet->updateBalance($tx['src'], '-' . ((float)$tx['val'] + (float)$tx['fee']));

        // Credit receiver (standard transfer)
        if (in_array($tx['version'], [self::VERSION_TRANSFER, self::VERSION_TRANSFER_MSG])) {
            if (!$this->wallet->accountExists($tx['dst'])) {
                $this->wallet->createAccount($tx['dst'], '', $blockId);
            }
            $this->wallet->updateBalance($tx['dst'], $tx['val']);
        }

        // Version-specific execution
        $this->executeVersion($tx, $blockId, $height);
    }

    // ─── Mempool ─────────────────────────────────────────────

    public function addToMempool(array $tx, string $peer = null): bool {
        $errors = $this->validate($tx);
        if (!empty($errors)) return false;

        $this->db->upsert('mempool', [
            'id'         => $tx['id'],
            'height'     => 0,
            'src'        => $tx['src'],
            'dst'        => $tx['dst'],
            'val'        => $tx['val'],
            'fee'        => $tx['fee'],
            'signature'  => $tx['signature'],
            'version'    => $tx['version'],
            'message'    => $tx['message'] ?? '',
            'public_key' => $tx['public_key'],
            'date'       => $tx['date'],
            'peer'       => $peer,
        ]);
        return true;
    }

    public function getMempoolTxs(int $limit = 500): array {
        return $this->db->fetchAll(
            'SELECT * FROM mempool ORDER BY fee DESC, date ASC LIMIT ?',
            [$limit]
        );
    }

    public function clearMempool(array $txIds): void {
        if (empty($txIds)) return;
        $places = implode(',', array_fill(0, count($txIds), '?'));
        $this->db->execute("DELETE FROM mempool WHERE id IN ($places)", $txIds);
    }

    public function purgeExpiredMempool(): int {
        $cutoff = time() - $this->cfg->mempool_max_age;
        return $this->db->execute('DELETE FROM mempool WHERE date < ?', [$cutoff]);
    }

    // ─── Lookups ─────────────────────────────────────────────

    public function get(string $id): ?array {
        return $this->db->fetchOne('SELECT * FROM transactions WHERE id = ?', [$id]);
    }

    public function exists(string $id): bool {
        return (bool)$this->db->fetchColumn('SELECT COUNT(*) FROM transactions WHERE id = ?', [$id]);
    }

    public function getByAddress(string $address, int $limit = 50, int $offset = 0): array {
        return $this->db->fetchAll(
            'SELECT * FROM transactions WHERE src = ? OR dst = ? ORDER BY height DESC LIMIT ? OFFSET ?',
            [$address, $address, $limit, $offset]
        );
    }

    public function getByBlock(string $blockId): array {
        return $this->db->fetchAll(
            'SELECT * FROM transactions WHERE block = ? ORDER BY date ASC',
            [$blockId]
        );
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function txId(array $tx): string {
        $str = implode('-', [
            $tx['src'] ?? '', $tx['dst'] ?? '', $tx['val'] ?? '',
            $tx['fee'] ?? '', $tx['version'] ?? '', $tx['date'] ?? '', $tx['public_key'] ?? '',
        ]);
        return hash('sha256', $str);
    }

    public function calcFee(float $val): float {
        $fee = $val * ($this->cfg->fee_percent / 100);
        return max($fee, $this->cfg->min_fee);
    }

    // ─── Version-specific Validation ─────────────────────────

    private function validateVersion(array $tx): array {
        $errors = [];
        switch ((int)$tx['version']) {
            case self::VERSION_ALIAS_REG:
                if (empty($tx['message'])) $errors[] = 'Alias required in message field';
                elseif (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $tx['message']))
                    $errors[] = 'Invalid alias format';
                elseif ($this->aliasExists($tx['message']))
                    $errors[] = 'Alias already taken';
                break;
            case self::VERSION_MN_REG:
                if ((float)$tx['val'] < $this->cfg->mn_collateral)
                    $errors[] = "MN collateral must be >= {$this->cfg->mn_collateral} STR";
                break;
        }
        return $errors;
    }

    // ─── Version-specific Execution ──────────────────────────

    private function executeVersion(array $tx, string $blockId, int $height): void {
        switch ((int)$tx['version']) {
            case self::VERSION_ALIAS_REG:
                $this->db->execute(
                    'UPDATE accounts SET alias = ? WHERE id = ?',
                    [$tx['message'], $tx['src']]
                );
                break;
            case self::VERSION_MN_REG:
                $this->db->upsert('masternode', [
                    'public_key' => $tx['public_key'],
                    'height'     => $height,
                    'ip'         => '',
                    'status'     => 1,
                ], ['height','status']);
                break;
            case self::VERSION_MN_DEREG:
                $this->db->execute(
                    'DELETE FROM masternode WHERE public_key = ?',
                    [$tx['public_key']]
                );
                break;
            case self::VERSION_MN_PAUSE:
                $this->db->execute(
                    'UPDATE masternode SET status = 0 WHERE public_key = ?',
                    [$tx['public_key']]
                );
                break;
            case self::VERSION_MN_RESUME:
                $this->db->execute(
                    'UPDATE masternode SET status = 1 WHERE public_key = ?',
                    [$tx['public_key']]
                );
                break;
            // Assets, governance, cold staking handled by SAssets/SGovernance/SMasternode
        }
    }

    private function aliasExists(string $alias): bool {
        return (bool)$this->db->fetchColumn(
            'SELECT COUNT(*) FROM accounts WHERE alias = ?',
            [$alias]
        );
    }
}
