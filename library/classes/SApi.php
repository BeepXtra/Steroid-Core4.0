<?php

defined('_SECURED') or die('Restricted access');

/**
 * Class for steroid platform
 *
 * @author exevior
 */
class SApi {

    public $platform;
    public $db;

    function __construct() {
        if (!class_exists("SWallet")) {
            include_once("library/classes/SWallet.php");
        }
        $this->swallet = new SWallet();

        if (!class_exists("SAssets")) {
            include_once("library/classes/SAssets.php");
        }
        $this->sassets = new SAssets();

        if (!class_exists("SBlock")) {
            include_once("library/classes/SBlock.php");
        }
        $this->sblock = new SBlock();

        if (!class_exists("SMine")) {
            include_once("library/classes/SMine.php");
        }
        $this->smine = new SMine();

        if (!class_exists("SPool")) {
            include_once("library/classes/SPool.php");
        }
        $this->spool = new SPool();

        if (!class_exists("STx")) {
            include_once("library/classes/STx.php");
        }
        $this->stx = new STx();

        if (!class_exists("SXplore")) {
            include_once("library/classes/SXplore.php");
        }
        $this->sxplore = new SXplore();

        //if (!class_exists("SChain")) {
        //    include_once("library/classes/SChain.php");
        //}
        //$this->schain = new SChain();
    }

    public function generate_account() {
        return $this->swallet->generate_account();
    }

    public function getbalance($public_key) {
        return array('balance' => $this->swallet->balance($public_key));
    }

    public function getpendingbalance($public_key) {
        return array('pending_balance' => $this->swallet->pending_balance($public_key));
    }

    public function gettransactions($address) {
        return array('transactions' => $this->swallet->get_transactions($address));
    }

    public function gettransaction($id) {
        return array('transaction' => $this->stx->get_transaction($id));
    }

    public function getpublickey($id) {

        return array('public_key' => $this->swallet->public_key($id));
    }

    public function getaddress($public_key) {
        //echo $this->swallet->get_address($public_key);
        return array('address' => $this->swallet->get_address($public_key));
    }

    public function base58($string) {
        return array('base58' => base58_encode($string));
    }

    public function currentblock() {
        return array('block' => $this->sblock->current());
    }

    public function getblock($height) {
        return array('block' => $this->sblock->get($height));
    }

    public function getblocktransactions($params) {
        $params = explode(':', $params);
        if (isset($params[1])) {
            $includeMiningRewards = $params[1];
        } else {
            $includeMiningRewards = false;
        }
        if (is_numeric($params[0])) {
            $height = $params[0];
            return array('transactions' => $this->stx->get_transactions($height, null, $includeMiningRewards));
        } else {
            $blockid = $params[0];
            return array('transactions' => $this->stx->get_transactions(null, $blockid, $includeMiningRewards));
        }
    }

    public function version() {
        return array('php_wrapper_version' => VERSION);
    }

    public function sendtx($data) {
        $x = explode(':', $data);
        //' . $val0 . ':' . $fee1 . ':' . $dst2 . ':' . $public_key3 . ':' . $sign4 . ':' . $pkey5 . ':' . $date6 . ':' . $msg7 . ':18'
        $info = $x['0'] . "-" . $x['1'] . "-" . $x['2'] . "-" . $x['7'] . "-" . $x['1'] . "-" . $x['3'] . "-" . $x['6'];
        if (ec_verify($info, $x[4], $x[3])) {
            return array('data' => true);
        } else {
            return array('result' => 'transaction could not be verified');
        }
    }

    private function prep_tx_data($data) {
        $x = explode(':', $data);
        $prepped = array();
        $prepped['val'] = $x[0];
        $prepped['fee'] = $x[1];
        $prepped['dst'] = $x[2];
        $prepped['public_key'] = $x[3];
        $prepped['signature'] = $x[4];
        $prepped['private_key'] = $x[5];
        $prepped['date'] = $x[6];
        $prepped['message'] = $x[7];
        $prepped['version'] = $x[8];
        return $prepped;
    }

    public function send($data) {
        global $_config, $db;
        $data = $this->prep_tx_data($data);

        /**
         * @api {post} /api/send/$data  14. send
         * @apiName send
         * @apiGroup API
         * @apiDescription Sends a transaction.
         *
         * @apiParam {numeric} val Transaction value (without fees)
         * @apiParam {string} dst Destination address
         * @apiParam {string} public_key Sender's public key
         * @apiParam {string} [signature] Transaction signature. It's recommended that the transaction is signed before being sent to the node to avoid sending your private key to the node.
         * @apiParam {string} [private_key] Sender's private key. Only to be used when the transaction is not signed locally.
         * @apiParam {numeric} [date] Transaction's date in UNIX TIMESTAMP format. Required when the transaction is pre-signed.
         * @apiParam {string} [message] A message to be included with the transaction. Maximum 128 chars.
         * @apiParam {numeric} [version] The version of the transaction. 1 to send coins.
         *
         * @apiSuccess {string} data  Transaction id
         */
        $current = $this->sblock->current();

        if ($current['height'] > 10790 && $current['height'] < 10810) {
            return api_err("Hard fork in progress. Please retry the transaction later!"); //10800
        }

        $acc = $this->swallet;
        $block = $this->sblock;

        $trx = $this->stx;
        $version = intval($data['version']);
        $dst = san($data['dst']);
        if ($version < 1) {
            $version = 1;
        }




        if ($version == 1) {
            if (!$acc->valid($dst)) {
                return api_err("Invalid destination address");
            }
            $dst_b = base58_decode($dst);
            if (strlen($dst_b) != 64) {
                return api_err("Invalid destination address");
            }
        } elseif ($version == 2) {
            $dst = strtoupper($dst);
            $dst = san($dst);
            if (!$acc->valid_alias($dst)) {
                return api_err("Invalid destination alias");
            }
        }




        $public_key = san($data['public_key']);

        if (!$acc->valid_key($public_key)) {
            return api_err("Invalid public key");
        }
        if ($_config->use_official_blacklist !== false) {
            //Throws error when calling namespace
            //TODO Convert to class and load in abstract controller
            if (Blacklist::checkPublicKey($public_key)) {
                return api_err("Blacklisted account");
            }
        }



        $private_key = san($data['private_key']);
        if (!$acc->valid_key($private_key)) {
            return api_err("Invalid private key");
        }
        $signature = san($data['signature']);
        if (!$acc->valid_key($signature)) {
            return api_err("Invalid signature");
        }
        $date = $data['date'] + 0;

        if ($date == 0) {
            $date = time();
        }
        if ($date < time() - (3600 * 24 * 48)) {
            return api_err("The date is too old");
        }
        if ($date > time() + 86400) {
            return api_err("Invalid Date");
        }


        $message = $data['message'];
        if (strlen($message) > 128) {
            return api_err("The message must be less than 128 chars");
        }
        $val = $data['val'] + 0;
        $fee = $val * 0.003;
        if ($fee < 0.00000001) {
            $fee = 0.00000001;
        }


        //if ($fee > 10 && $current['height'] > 10800) {
        //    $fee = 10; //10800
        //}
        if ($val < 0) {
            return api_err("Invalid value");
        }


        // set alias
        if ($version == 3) {
            $fee = 10;
            $message = san($message);
            $message = strtoupper($message);
            if (!$acc->free_alias($message)) {
                return api_err("Invalid alias");
            }
            if ($acc->has_alias($public_key)) {
                return api_err("This account already has an alias");
            }
        }

        if ($version >= 100 && $version < 110) {
            if ($version == 100) {
                $message = preg_replace("/[^0-9\.]/", "", $message);
                if (!filter_var($message, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return api_err("Invalid Node IP - $message !");
                }
                $val = 250000;
            }
        }

        $val = number_format($val, 8, '.', '');
        $fee = number_format($fee, 8, '.', '');

        if (empty($public_key) && empty($private_key)) {
            return api_err("Either the private key or the public key must be sent");
        }


        if (empty($private_key) && empty($signature)) {
            return api_err("Either the private_key or the signature must be sent");
        }



        if (empty($public_key)) {
            $pk = coin2pem($private_key, true);
            $pkey = openssl_pkey_get_private($pk);
            $pub = openssl_pkey_get_details($pkey);
            $public_key = pem2coin($pub['key']);
        }
        $transaction = [
            "val" => $val,
            "fee" => $fee,
            "dst" => $dst,
            "public_key" => $public_key,
            "date" => $date,
            "version" => $version,
            "message" => $message,
            "signature" => $signature,
        ];

        if (!empty($private_key)) {
            $signature = $trx->sign($transaction, $private_key);
            $transaction['signature'] = $signature;
        }


        $hash = $trx->hash($transaction);
        $transaction['id'] = $hash;
        //return array('info' => $hash, 'sign' => $data['signature']);



        if (!$trx->check($transaction)) {
            return api_err("Transaction signature failed");
        }

        $res = $db->single("SELECT COUNT(1) FROM mempool WHERE id=:id", [":id" => $hash]);
        if ($res != 0) {
            return api_err("The transaction is already in mempool");
        }

        $res = $db->single("SELECT COUNT(1) FROM transactions WHERE id=:id", [":id" => $hash]);
        if ($res != 0) {
            return api_err("The transaction is already in a block");
        }


        $src = $acc->get_address($public_key);
        $transaction['src'] = $src;
        $balance = $db->single("SELECT balance FROM accounts WHERE id=:id", [":id" => $src]);
        if ($balance < $val + $fee) {
            return api_err("Not enough funds");
        }


        $memspent = $db->single("SELECT SUM(val+fee) FROM mempool WHERE src=:src", [":src" => $src]);
        if ($balance - $memspent < $val + $fee) {
            return api_err("Not enough funds (mempool)");
        }


        $trx->add_mempool($transaction, "local");
        $hash = escapeshellarg(san($hash));
        system("php propagate.php transaction $hash > /dev/null 2>&1  &");
        //api_echo($hash);
        return array('txid' => $hash);
    }

    public function mempoolsize() {
        global $db;
        $res = $db->single("SELECT COUNT(1) as mempoolsize FROM mempool");
        if (!$res) {
            return array('mempoolsize' => 0);
        }
        return json_encode(api_echo($res));
    }

    public function randomnumber($height, $max, $min = 1, $seed = '') {
        $height = san($height);
        $max = intval($max);
        $min = intval($min);

        $blk = $db->single("SELECT id FROM blocks WHERE height=:h", [":h" => $height]);
        if ($blk === false) {
            return api_err("Unknown block");
        }
        $base = hash("sha256", $blk . $seed);

        $seed1 = hexdec(substr($base, 0, 12));
        // generate random numbers based on the seed
        mt_srand($seed1, MT_RAND_MT19937);
        $res = mt_rand($min, $max);
        return json_encode(api_echo($res));
    }

    public function checksignature($request) {
        $pubkey = $request->url_elements[2];
        $sign = $request->url_elements[3];
        $data = $request->url_elements[4];
        if (ec_verify($data, $sign, $pubkey)) {
            return api_echo('true');
        } else {
            return api_err('false');
        }
    }

    public function checkaddress($address, $pubkey = null) {



        if (!$this->swallet->valid($address)) {
            api_err(false);
        }

        $dst_b = base58_decode($address);
        if (strlen($dst_b) != 64) {
            return api_err(false);
        }
        if (!empty($pubkey)) {
            if ($this->swallet->get_address($pubkey) != $address) {
                return api_err(false);
            }
        }
        return api_echo(true);
    }

    public function sanity() {
        global $db;
        $sanity = file_exists(__DIR__ . '/tmp/sanity-lock');
        $lastSanity = (int) $db->single("SELECT val FROM config WHERE cfg='sanity_last'");
        $sanitySync = (bool) $db->single("SELECT val FROM config WHERE cfg='sanity_sync'");
        return api_echo(['sanity_running' => $sanity, 'last_sanity' => $lastSanity, 'sanity_sync' => $sanitySync]);
    }

    public function nodeinfo() {
        global $db, $_config;
        //strd db version
        $dbVersion = $db->single("SELECT val FROM config WHERE cfg='dbversion'");
        //peer hostname
        $hostname = $db->single("SELECT val FROM config WHERE cfg='hostname'");
        //number of active wallets
        $acc = $db->single("SELECT COUNT(1) FROM accounts");
        //number of transactions recorded
        $tr = $db->single("SELECT COUNT(1) FROM transactions");
        //count masternodes
        $masternodes = $db->single("SELECT COUNT(1) FROM masternode");
        //txs in mempool
        $mempool = $db->single("SELECT COUNT(1) FROM mempool");
        //count peers
        $peers = $db->single("SELECT COUNT(1) FROM peers WHERE blacklisted<UNIX_TIMESTAMP()");
        //current block height
        $blockheight = $db->single("SELECT height FROM blocks ORDER BY height DESC limit 1");
        //Passive peering active
        $passive_peer = $_config->passive_peering;
        //Masternode Public Key
        $public_key = $_config->masternode_public_key;
        //PHP Version
        $phpversion = phpversion();
        //Node OS Distribution
        $system = parse_ini_string(shell_exec('cat /etc/lsb-release'))['DISTRIB_DESCRIPTION'];
        //Free disk space
        $disk = $this->format_bytes(disk_free_space("."));
        $disktot = $this->format_bytes(disk_total_space("."));
        //Ram usage
        $memory = $this->getSystemMemInfo();
        //Web-server type and version i.e. Nginx 1.14.2
        $nginxVersion = $_SERVER['SERVER_SOFTWARE'];
        //database engine version
        $mysqlVersion = $db->single("SELECT VERSION()");
        //current load avg
        $load = sys_getloadavg();
        //echo '<pre>';print_r($GLOBALS);
        return api_echo([
            'hostname' => $hostname,
            'version' => VERSION,
            'dbversion' => $dbVersion,
            'accounts' => $acc,
            'transactions' => $tr,
            'mempool' => $mempool,
            'masternodes' => $masternodes,
            'peers' => $peers,
            'height' => $blockheight,
            'passive_peering' => $passive_peer,
            'public_key' => $public_key,
            'loadavg' => $load[0],
            'disk' => array(
                'available' => $disk,
                'total' => $disktot
            ),
            'memory' => $memory,
            'php' => $phpversion,
            'system' => $system,
            'webserver' => $nginxVersion,
            'dbengine' => $mysqlVersion
        ]);
    }

    public function masternodes($data = null) {
        global $db;
        $bind = [];
        $whr = '';
        ($data) ? $public_key = san($data['public_key']) : $public_key = null;

        if (!empty($public_key)) {
            $whr = "WHERE public_key=:public_key";
            $bind[':public_key'] = $public_key;
        }
        $res = $db->run("SELECT * FROM masternode $whr ORDER by public_key ASC", $bind);

        return array(["masternodes" => $res, "hash" => md5(json_encode($res))]);
    }

    private function format_bytes($bytes) {
        $si_prefix = array('B', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB');
        $base = 1024;
        $class = min((int) log($bytes, $base), count($si_prefix) - 1);
        $formatted = sprintf('%1.2f', $bytes / pow($base, $class)) . ' ' . $si_prefix[$class];
        return $formatted;
    }

    private function getSystemMemInfo() {
        $data = explode("\n", file_get_contents("/proc/meminfo"));
        $meminfo = array();

      
        $memdata = explode(':', $data[2]);
        $meminfo['available'] = trim($memdata[1]);
  $memdata = explode(':', $data[0]);
        $meminfo['total'] = trim($memdata[1]);
        return $meminfo;
    }

    public function test($public_key) {
        //$add = '2P67zUANj7NRKTruQ8nJRHNdKMroY6gLw4NjptTVmYk6Hh1QPYzzfEa9z4gv8qJhuhCNM8p9GDAEDqGUU1awaLW62iuDtB7dUkaezC137o2a1D7trrR2JMk7EaRbckHu9KvLNHyb7wcp6MVuYj32X79tZumtZohfKQWwvoyPqCZuVmfJ';
//$pub = 'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCyjGMdVDanywM3CbqvswVqysqU8XS87FcjpqNijtpRSSQ36WexRDv3rJL5X8qpGvzvznuErSRMfb2G6aNoiaT3aEJ';
//$addlen2 = strlen($add);
//$addlen = $addlen2 / 2;
//$publen = strlen($pub);
        $diff = $this->sblock->difficulty(7300);

        return array('difficulty' => $diff);
    }

}

?>
