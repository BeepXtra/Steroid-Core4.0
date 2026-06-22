<?php
defined('_SECURED') or die('Restricted access');

class STx {
    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    // ── Build Transaction ────────────────────────────────────────────────────

    public function build(array $params): array {
        $required = ['type', 'src', 'dst', 'val', 'public_key', 'signature'];
        foreach ($required as $field) {
            if (!isset($params[$field])) throw new InvalidArgumentException("Missing field: $field");
        }
        $fee = $this->calcFee((float)$params['val'], (int)$params['type']);
        return [
            'type'       => (int) $params['type'],
            'src'        => $params['src'],
            'dst'        => $params['dst'],
            'val'        => number_format((float)$params['val'], 8, '.', ''),
            'fee'        => number_format($fee, 8, '.', ''),
            'signature'  => $params['signature'],
            'message'    => $params['message'] ?? '',
            'version'    => (int)($params['version'] ?? 1),
            'date'       => $params['date'] ?? time(),
            'public_key' => $params['public_key'],
        ];
    }

    public function calcFee(float $amount, int $type): float {
        // Reward transactions (type 0) and governance (105-107) carry no fee
        if (in_array($type, [TX_REWARD, TX_GOV_RESULT])) return 0.0;
        $fee = $amount * FEE_PCT;
        return max($fee, (float)MIN_FEE);
    }

    // ── Validate ─────────────────────────────────────────────────────────────

    public function validate(array $tx, bool $mempool = true): array {
        $errors = [];

        // Signature check
        $wallet  = new SWallet($this->db);
        $sigData = $this->signingData($tx);
        if (!$wallet->verify($sigData, $tx['signature'], $tx['public_key'])) {
            $errors[] = 'Invalid signature';
        }

        // Balance check (skip for reward tx)
        if ($tx['type'] !== TX_REWARD) {
            $balance = (float) $wallet->getBalance($tx['src']);
            $pending = $mempool ? (float) $this->pendingOut($tx['src']) : 0.0;
            $needed  = (float) $tx['val'] + (float) $tx['fee'];
            if (($balance - $pending) < $needed) {
                $errors[] = 'Insufficient balance';
            }
        }

        // Duplicate in mempool
        if ($mempool && $this->db->exists('mempool', 'signature=?', [$tx['signature']])) {
            $errors[] = 'Transaction already in mempool';
        }

        // Type-specific validation
        $typeErrors = $this->validateType($tx);
        $errors = array_merge($errors, $typeErrors);

        return $errors;
    }

    private function validateType(array $tx): array {
        $errors = [];
        switch ((int)$tx['type']) {
            case TX_ALIAS_SET:
                if (empty($tx['message'])) $errors[] = 'Alias cannot be empty';
                break;
            case TX_MN_REGISTER:
                $collateral = (float) MASTERNODE_COLLATERAL;
                if ((float)$tx['val'] < $collateral) {
                    $errors[] = "Masternode collateral must be >= $collateral";
                }
                break;
            case TX_ASSET_CREATE:
                $msg = json_decode($tx['message'], true);
                if (!$msg || empty($msg['name'])) $errors[] = 'Asset name required';
                break;
        }
        return $errors;
    }

    // ── Mempool ──────────────────────────────────────────────────────────────

    public function addToMempool(array $tx, string $peer = ''): bool {
        $errors = $this->validate($tx);
        if (!empty($errors)) return false;

        $this->db->insert('mempool', [
            'type'       => $tx['type'],
            'src'        => $tx['src'],
            'dst'        => $tx['dst'],
            'val'        => $tx['val'],
            'fee'        => $tx['fee'],
            'signature'  => $tx['signature'],
            'message'    => $tx['message'] ?? '',
            'version'    => $tx['version'] ?? 1,
            'date'       => $tx['date'] ?? time(),
            'public_key' => $tx['public_key'],
            'peer'       => $peer,
        ]);
        return true;
    }

    public function getMempool(int $limit = 500): array {
        return $this->db->rows(
            "SELECT * FROM mempool ORDER BY fee DESC, date ASC LIMIT ?", [$limit]
        );
    }

    public function cleanMempool(): int {
        $cutoff = time() - MAX_MEMPOOL_AGE;
        return $this->db->delete('mempool', 'date<?', [$cutoff]);
    }

    public function removeFromMempool(array $signatures): void {
        if (empty($signatures)) return;
        $placeholders = implode(',', array_fill(0, count($signatures), '?'));
        $this->db->query("DELETE FROM mempool WHERE signature IN ($placeholders)", $signatures);
    }

    // ── Confirm into block ───────────────────────────────────────────────────

    public function confirm(array $tx, int $blockId, int $height): void {
        $this->db->transaction(function(Database $db) use ($tx, $blockId, $height) {
            $db->insert('transactions', [
                'block'      => $blockId,
                'height'     => $height,
                'type'       => $tx['type'],
                'src'        => $tx['src'],
                'dst'        => $tx['dst'],
                'val'        => $tx['val'],
                'fee'        => $tx['fee'],
                'signature'  => $tx['signature'],
                'message'    => $tx['message'] ?? '',
                'version'    => $tx['version'] ?? 1,
                'date'       => $tx['date'] ?? time(),
                'public_key' => $tx['public_key'],
            ]);

            $wallet = new SWallet($db);
            $this->applyEffect($tx, $wallet, $height);
        });
    }

    // ── Apply on-chain effects ────────────────────────────────────────────────

    private function applyEffect(array $tx, SWallet $wallet, int $height): void {
        $type = (int)$tx['type'];

        // Credit/debit for monetary transfers
        if (in_array($type, [TX_REWARD, TX_TRANSFER, TX_ALIAS_PAY])) {
            if ($tx['src'] !== '0') {
                $wallet->debitBalance($tx['src'], bcadd($tx['val'], $tx['fee'], 8));
            }
            $wallet->getOrCreateAccount($tx['dst'], '');
            $wallet->creditBalance($tx['dst'], $tx['val']);
        }

        // Alias assignment
        if ($type === TX_ALIAS_SET) {
            $wallet->setAlias($tx['src'], $tx['message']);
        }

        // Masternode register
        if ($type === TX_MN_REGISTER) {
            $mn = new SMasternode($this->db);
            $mn->register($tx, $height);
        }

        // Masternode pause/resume/blacklist
        if ($type === TX_MN_PAUSE)       (new SMasternode($this->db))->pause($tx['src']);
        if ($type === TX_MN_RESUME)      (new SMasternode($this->db))->resume($tx['src']);
        if ($type === TX_MN_BLACKLIST)   (new SMasternode($this->db))->blacklist($tx['src']);
        if ($type === TX_MN_UNBLACKLIST)(new SMasternode($this->db))->unblacklist($tx['src']);

        // Assets
        if (in_array($type, [TX_ASSET_CREATE,TX_ASSET_TRANSFER,TX_ASSET_BID,TX_ASSET_ASK,
                              TX_ASSET_CANCEL,TX_ASSET_INFLATE,TX_ASSET_DIVIDEND,TX_ASSET_FILL])) {
            (new SAssets($this->db))->applyTx($tx, $height);
        }

        // Governance
        if (in_array($type, [TX_GOV_PROPOSAL, TX_GOV_VOTE, TX_GOV_RESULT])) {
            (new SGovernance($this->db))->applyTx($tx, $height);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function signingData(array $tx): string {
        return implode(':', [
            $tx['type'],
            $tx['src'],
            $tx['dst'],
            $tx['val'],
            $tx['fee'],
            $tx['message'] ?? '',
            $tx['date'],
        ]);
    }

    private function pendingOut(string $address): float {
        $row = $this->db->row(
            "SELECT COALESCE(SUM(val+fee),0) AS t FROM mempool WHERE src=?", [$address]
        );
        return (float)($row['t'] ?? 0);
    }

    public function getTx(string $signature): ?array {
        return $this->db->row("SELECT * FROM transactions WHERE signature=?", [$signature]);
    }

    public function getByAddress(string $address, int $limit = 50): array {
        return $this->db->rows(
            "SELECT * FROM transactions WHERE src=? OR dst=? ORDER BY id DESC LIMIT ?",
            [$address, $address, $limit]
        );
    }
}
