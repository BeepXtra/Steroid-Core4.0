<?php
/*
The MIT License (MIT)
Copyright (c) 2018 AroDev

www.arionum.com

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE
OR OTHER DEALINGS IN THE SOFTWARE.
*/

define('_SECURED', 1);


/**
 * Load the Core Library
 */
include(__DIR__.'/library/classes/SCore.php');
$platform = new SCore();
//parse configuration
$_config = $platform->config;
//initialize database library
$db = $platform->db;

require_once __DIR__.'/library/includes/init.inc.php';
header('Content-Type: application/json');

$trx = new STx();
$block = new SBlock();
$q = $_GET['q'];

// the data is sent as json, in $_POST['data']

if (!empty(file_get_contents('php://input'))) {
    $json = file_get_contents('php://input');
       _log("Peer get_contents: {$json}") ;
    $json = trim(urldecode($json),'data=');
    _log("Peer url: {$q}") ;
    _log("Peer file: {$json}") ;
   
    $data = json_decode($json,true);
}

// make sure it's the same coin and not testnet
if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])){
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else {
    $ip = $_SERVER['REMOTE_ADDR'];
}


$ip = san_ip($ip);
$ip = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
$ip = md5($ip);
_log('REMOTE IP:'. $ip);
// peer with the current node
if ($q == "peer") {
    _log('INCOMING DATA '.$data['hostname']);
    if ($data['coin'] != $_config->coin) {
    print_r(json_encode(api_err("Invalid coin")));die;
}
    // sanitize the hostname
    $hostname = filter_var($data['hostname'], FILTER_SANITIZE_URL);

    $bad_peers = ["127.", "localhost", "10.", "192.168.","172.16.","172.17.","172.18.","172.19.","172.20.","172.21.","172.22.","172.23.","172.24.","172.25.","172.26.","172.27.","172.28.","172.29.","172.30.","172.31."];
    $tpeer=str_replace(["https://","http://","//"], "", $hostname);
    foreach ($bad_peers as $bp) {
        if (strpos($tpeer, $bp)===0) {
            print_r(json_encode(api_err("invalid-hostname")));die;
        }
    }

    if (!filter_var($hostname, FILTER_VALIDATE_URL)) {
        print_r(json_encode(api_err("invalid-hostname")));die;
    }
    $hostname = san_host($hostname);
    // if it's already peered, only repeer on request
    
    $res = $db->single(
        "SELECT COUNT(1) FROM peers WHERE hostname='".$hostname."' AND ip='".$ip."'",
        [":hostname" => $hostname, ":ip" => $ip]
    );
    $stat = ($res)?'success':'fail';
    _log('RES RESULT '.$stat.' HOSTNAME'.$hostname.' IP'.$ip);
    if ($res == 1) {
        if ($data['repeer'] == 1) {
            $res = peer_post($hostname."/peer.php?q=peer", ["hostname" => $_config->hostname]);
            if ($res !== false) {
                print_r(json_encode(api_echo("re-peer-ok")));die;
            } else {
                print_r(json_encode(api_err("re-peer failed 1 - $result")));die;
            }
        }
        print_r(json_encode(api_echo("peer-ok-already")));die;
    }
    // if we have enough peers, add it to DB as reserve
    $res = $db->single("SELECT COUNT(1) FROM peers WHERE blacklisted<UNIX_TIMESTAMP() AND ping >UNIX_TIMESTAMP()-86400 AND reserve=0");
    $reserve = 1;
    if ($res < $_config->max_peers) {
        $reserve = 0;
    }
    _log($db->run(
        "INSERT ignore INTO peers SET hostname=:hostname, reserve=:reserve, ping=UNIX_TIMESTAMP(), ip=:ip ON DUPLICATE KEY UPDATE hostname=:hostname2",
        [":ip" => $ip, ":hostname2" => $hostname, ":hostname" => $hostname, ":reserve" => $reserve]
    ));
    _log('Peering hostname '.$hostname . $data['hostname']);
    // re-peer to make sure the peer is valid
    $res = peer_post($hostname."/peer.php?q=peer", ["hostname" => $_config->hostname],5);
    _log('CHECK PEER '.$res);
    if ($res !== false) {
        print_r(json_encode(api_echo("re-peer-ok")));die;
    } else {
        $db->run("DELETE FROM peers WHERE ip=:ip", [":ip" => $ip]);
        print_r(json_encode(api_err("re-peer failed 2 - $result")));die;
    }
} elseif ($q == "ping") {
    // confirm peer is active
    print_r(json_encode(api_echo("pong")));die;
} elseif ($q == "submitTransaction") {
    if ($data['coin'] != $_config->coin) {
    print_r(json_encode(api_err("Invalid coin")));die;
}
    // receive a new transaction from a peer
    $current = $block->current();


    // no transactions accepted if the sanity is syncing
    if ($_config->sanity_sync == 1) {
        print_r(json_encode(api_err("sanity-sync")));die;
    }

    $data['id'] = san($data['id']);
    // validate transaction data
    if (!$trx->check($data)) {
        print_r(json_encode(api_err("Invalid transaction")));die;
    }
    $hash = $data['id'];
    // make sure it's not already in mempool
    $res = $db->single("SELECT COUNT(1) FROM mempool WHERE id=:id", [":id" => $hash]);
    if ($res != 0) {
        print_r(json_encode(api_err("The transaction is already in mempool")));die;
    }
    // make sure the peer is not flooding us with transactions
    $res = $db->single("SELECT COUNT(1) FROM mempool WHERE src=:src", [":src" => $data['src']]);
    if ($res > 25) {
        print_r(json_encode(api_err("Too many transactions from this address in mempool. Please rebroadcast later.")));die;
    }
    $res = $db->single("SELECT COUNT(1) FROM mempool WHERE peer=:peer", [":peer" => $ip]);
    if ($res > $_config->peer_max_mempool) {
        print_r(json_encode(api_error("Too many transactions broadcasted from this peer")));die;
    }


    // make sure the transaction is not already on the blockchain
    $res = $db->single("SELECT COUNT(1) FROM transactions WHERE id=:id", [":id" => $hash]);
    if ($res != 0) {
        print_r(json_encode(api_err("The transaction is already in a block")));die;
    }
    $acc = new SWallet();
    $src = $acc->get_address($data['public_key']);
    // make sure the sender has enough balance
    $balance = $db->single("SELECT balance FROM accounts WHERE id=:id", [":id" => $src]);
    if ($balance < $val + $fee) {
        print_r(json_encode(api_err("Not enough funds")));die;
    }

    // make sure the sender has enough pending balance
    $memspent = $db->single("SELECT SUM(val+fee) FROM mempool WHERE src=:src", [":src" => $src]);
    if ($balance - $memspent < $val + $fee) {
        print_r(json_encode(api_err("Not enough funds (mempool)")));die;
    }
  
    // add to mempool
    $trx->add_mempool($data, $ip);

    // rebroadcast the transaction to some peers unless the transaction is smaller than the average size of transactions in mempool - protect against garbage data flooding
    $res = $db->row("SELECT COUNT(1) as c, sum(val) as v FROM  mempool ", [":src" => $data['src']]);
    if ($res['c'] < $_config->max_mempool_rebroadcast && $res['v'] / $res['c'] < $data['val']) {
        $data['id']=escapeshellarg(san($data['id']));
        system("php propagate.php transaction '$data[id]'  > /dev/null 2>&1  &");
    }
    print_r(json_encode(api_echo("transaction-ok")));die;
} elseif ($q == "submitBlock") {
    if ($data['coin'] != $_config->coin) {
    print_r(json_encode(api_err("Invalid coin")));die;
}
    // receive a  new block from a peer

    // if sanity sync, refuse all
    if ($_config->sanity_sync == 1) {
        _log('['.$ip."] Block rejected due to sanity sync");
        print_r(json_encode(api_err("sanity-sync")));die;
    }
    $data['id'] = san($data['id']);
    $current = $block->current();
    // block already in the blockchain
    if ($current['id'] == $data['id']) {
        print_r(json_encode(api_echo("block-ok")));die;
    }
    if ($data['date'] > time() + 30) {
        print_r(json_encode(api_err("block in the future")));die;
    }

    if ($current['height'] == $data['height'] && $current['id'] != $data['id']) {
        // different forks, same height
        $accept_new = false;

            // convert the first 12 characters from hex to decimal and the block with the largest number wins
            $no1 = hexdec(substr(coin2hex($current['id']), 0, 12));
            $no2 = hexdec(substr(coin2hex($data['id']), 0, 12));
            if (gmp_cmp($no1, $no2) == 1) {
                $accept_new = true;
            }
        
        if ($accept_new) {
            // if the new block is accepted, run a microsanity to sync it
            _log('['.$ip."] Starting microsanity - $data[height]");
            $ip=escapeshellarg($ip);
            system("php sanity.php microsanity '$ip'  > /dev/null 2>&1  &");
            print_r(json_encode(api_echo("microsanity")));die;
        } else {
            _log('['.$ip."] suggesting reverse-microsanity - $data[height]");
            print_r(json_encode(api_echo("reverse-microsanity")));die; // if it's not, suggest to the peer to get the block from us
        }
    }
    // if it's not the next block
    if ($current['height'] != $data['height'] - 1) {
        // if the height of the block submitted is lower than our current height, send them our current block
        if ($data['height'] < $current['height']) {
            $pr = $db->row("SELECT * FROM peers WHERE ip=:ip", [":ip" => $ip]);
            if (!$pr) {
                print_r(json_encode(api_err("block-too-old")));die;
            }
            $peer_host = escapeshellcmd(base58_encode($pr['hostname']));
            $pr['ip'] = escapeshellcmd(san_ip($pr['ip']));
            system("php propagate.php block current '$peer_host' '$pr[ip]'   > /dev/null 2>&1  &");
            _log('['.$ip."] block too old, sending our current block - $data[height]");

            print_r(json_encode(api_err("block-too-old")));die;
        }
        // if the block difference is bigger than 150, nothing should be done. They should sync via sanity
        if ($data['height'] - $current['height'] > 150) {
            _log('['.$ip."] block-out-of-sync - $data[height]");
            print_r(json_encode(api_err("block-out-of-sync")));die;
        }
        // request them to send us a microsync with the latest blocks
        _log('['.$ip."] requesting microsync - $current[height] - $data[height]");
        print_r(json_encode(api_echo(["request" => "microsync", "height" => $current['height'], "block" => $current['id']])));die;
    }
    // check block data
    if (!$block->check($data)) {
        _log('['.$ip."] invalid block - $data[height]");
        print_r(json_encode(api_err("invalid-block")));die;
    }
    $b = $data;
    // add the block to the blockchain
    $res = $block->add(
        $b['height'],
        $b['public_key'],
        $b['nonce'],
        $b['data'],
        $b['date'],
        $b['signature'],
        $b['difficulty'],
        $b['reward_signature'],
        $b['argon']
    );

    if (!$res) {
        _log('['.$ip."] invalid block data - $data[height]");
        print_r(json_encode(api_err("invalid-block-data")));die;
    }

    _log('['.$ip."] block ok, repropagating - $data[height]");

    // send it to all our peers
    $data['id']=escapeshellcmd(san($data['id']));
    system("php propagate.php block '$data[id]' all all linear > /dev/null 2>&1  &");
    print_r(json_encode(api_echo("block-ok")));die;
} // return the current block, used in syncing
elseif ($q == "currentBlock") {
    $current = $block->current();
    print_r(json_encode(api_echo($current)));die;
} // return a specific block, used in syncing
elseif ($q == "getBlock") {
    $height = intval($data['height']);
    $export = $block->export("", $height);
    if (!$export) {
        print_r(json_encode(api_err("invalid-block")));die;
    }
    print_r(json_encode(api_echo($export)));die;
} elseif ($q == "getBlocks") {
// returns X block starting at height,  used in syncing

    $height = intval($data['height']);

    $r = $db->run(
        "SELECT id,height FROM blocks WHERE height>=:height ORDER by height ASC LIMIT 100",
        [":height" => $height]
    );
    foreach ($r as $x) {
        $blocks[$x['height']] = $block->export($x['id']);
    }
    print_r(json_encode(api_echo($blocks)));die;
} // returns a full list of unblacklisted peers in a random order
elseif ($q == "getPeers") {
    $peers = $db->run("SELECT ip,hostname FROM peers WHERE blacklisted<UNIX_TIMESTAMP() ORDER by RAND()");
    print_r(json_encode(api_echo($peers)));die;
} else {
    print_r(json_encode(api_err("Invalid request")));die;
}
