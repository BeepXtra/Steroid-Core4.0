<?php
/*
The MIT License (MIT)
Copyright (c) 2018 BpcDev

www.steroid.io

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


//Development tool
$debug = 1;
if($debug){
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
}


/**
 * Load the Core Library
 */
include('../library/classes/SCore.php');
$platform = new SCore();
//parse configuration
$_config = $platform->config;
//initialize database library
$db = $platform->db;
/*
 * Load generic functions
 */
require_once '../library/includes/init.inc.php';
$block = new SBlock();
$acc = new SWallet();
set_time_limit(360);
$q = $_GET['q'];

$ip = san_ip($_SERVER['REMOTE_ADDR']);
$ip = filter_var($ip, FILTER_VALIDATE_IP);

// in case of testnet, all IPs are accepted for mining
if ($_config->testnet == false && !in_array($ip, $_config->allowed_hosts) && !empty($ip) && !in_array(
    '*',
    $_config->allowed_hosts
)) {
    api_err("unauthorized");
}

if ($q == "info") {
    // provides the mining info to the miner
    $diff = $block->difficulty();
    $current = $block->current();

    $current_height=$current['height'];
    //if($current_height < 1000000){
            //$diff = $diff*1000000;
    //    }

    $recommendation="mine";
    $argon_mem=524288;
    $argon_threads=1;
    $argon_time=1;
    if($current_height){
	if($current_height%2==0){
	    $argon_mem=524288;
            $argon_threads=1;
            $argon_time=1;
	} else {
	    $argon_mem=16384;
            $argon_threads=4;
            $argon_time=4;

	}

	
    } else {
        if ($current_height%3==0) {
            $argon_mem=524288;
            $argon_threads=1;
            $argon_time=1;
        } elseif ($current_height%3==2) {
            global $db;
            $winner=$db->single(
                "SELECT public_key FROM masternode WHERE status=1 AND blacklist<:current AND height<:start ORDER by last_won ASC, public_key ASC LIMIT 1",
                [":current"=>$current_height, ":start"=>$current_height-360]
            );
            //$recommendation="pause";
            if ($winner===false) {
                $recommendation="mine";
            }
        }
    }
    
    
    
    $res = [
        "difficulty" => $diff,
        "block"      => $current['id'],
        "height"     => $current['height'],
        "testnet"    => $_config->testnet,
        "recommendation"=> $recommendation,
        "argon_mem"  => $argon_mem,
        "argon_threads"  => $argon_threads,
        "argon_time"  => $argon_time,
    ];
    print_r(json_encode(api_echo([$res])));
    exit;
} elseif ($q == "submitNonce") {
    // in case the blocks are syncing, reject all
    if ($_config->sanity_sync == 1) {
        print_r(json_encode(api_err("sanity-sync")));
    }
    $nonce = san($_POST['nonce']);
    $argon = $_POST['argon'];
    $public_key = san($_POST['public_key']);
    $private_key = san($_POST['private_key']);
     //TESTING
    if(isset($_GET['minertest'])){
        $nonce = 'HSwvfDHzkUlgGhqSG70PO6IuGvuDaZOtopgmOyi30';
        $argon = '$d01UQXVGMS5LYmtBSDhLNQ$VOJejcdCvub3DZn+Cwf9rnnvZ7AvMdT9u9Qdfqy8HNM';
        $public_key = 'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCx8yGj2PNN4MTehQGt5t3TXuLUsVBi52qQuoXChcMpsUBHu9khFJjTWLPXM3L6KSjm16kfwjQmvDUQ3URv5qiL9Hy';
        $private_key = 'Lzhp9LopCEmWahwR82MMwt8BfPjANmof31Pxm8gwHnnKsNxfm6bHrpjWv5mrJ8u35Tfy7657ZmQSDHWVvYjwV5ycJKzZM7kkuAcn1H8o1Rk7Z3JzNWVBqMcFhLDyGs1VPBVEf94wRvJEVnPxqo57p4rPaF5qByuQT';
    }
    /*
     * BEGIN MINER STAKE LOGIC 10000 BPC
     */
    $pk = san($public_key);
    $bl = $db->single(
                "SELECT balance FROM accounts WHERE public_key=:pk",
                [":pk"=>$pk]
            );
    if($bl < 10000){
        _log("Not enough stake in ".$public_key." : ".$bl,3);
        print_r(json_encode(api_err("rejected - ensure wallet has at least 10000bpc ")));die;
    } else {
        _log("miner has balance ".$bl,3);
    }
    // check if the miner won the block
    $result = $block->mine($public_key, $nonce, $argon);
    
    if ($result) {
        _log("Miner won", 3);
        // generate the new block
        $res = $block->forge($nonce, $argon, $public_key, $private_key);


        if ($res) {
            _log("Miner generated block", 3);
            //if the new block is generated, propagate it to all peers in background
            $current = $block->current();
            $current['id']=escapeshellarg(san($current['id']));
            system("php propagate.php block $current[id]  > /dev/null 2>&1  &");
            print_r(json_encode(api_echo("accepted")));
        } else {
            _log("Miner nonce failed to forge block", 3);
            print_r(json_encode(api_err("rejected")));
        }
    } else {
        _log("Miner failed to verify argon ".$argon, 3);
        print_r(json_encode(api_err("rejected")));
    }
    
} elseif ($q == "submitBlock") {
    // in case the blocks are syncing, reject all
    if ($_config->sanity_sync == 1) {
        print_r(json_encode(api_err("sanity-sync")));
    }
    $nonce = san($_POST['nonce']);
    $argon = $_POST['argon'];
    $public_key = san($_POST['public_key']);
    // check if the miner won the block
    
    $result = $block->mine($public_key, $nonce, $argon);
    
    if ($result) {
        // generate the new block
        $date = intval($_POST['date']);
        if ($date <= $current['date']) {
            print_r(json_encode(api_err("rejected - date")));
        }

        // get the mempool transactions
        $txn = new STx();
        $current = $block->current();
        $height = $current['height'] += 1;

        $difficulty = $block->difficulty();
        $acc = new SWallet();
        $generator = $acc->get_address($public_key);

        $data=json_decode($_POST['data'], true);
           
        // sign the block
        $signature = san($_POST['signature']);

        // reward transaction and signature
        $reward = $block->reward($height, $data);
        $msg = '';
        $transaction = [
            "src"        => $generator,
            "dst"        => $generator,
            "val"        => $reward,
            "version"    => 0,
            "date"       => $date,
            "message"    => $msg,
            "fee"        => "0.00000000",
            "public_key" => $public_key,
        ];
        ksort($transaction);
        $reward_signature = san($_POST['reward_signature']);

        // add the block to the blockchain
        $res = $block->add(
            $height,
            $public_key,
            $nonce,
            $data,
            $date,
            $signature,
            $difficulty,
            $reward_signature,
            $argon
        );


        if ($res) {
            //if the new block is generated, propagate it to all peers in background
            $current = $block->current();
            $current['id']=escapeshellarg(san($current['id']));
            system("php propagate.php block $current[id]  > /dev/null 2>&1  &");
            print_r(json_encode(api_echo("accepted")));
        } else {
            print_r(json_encode(api_err("rejected - add")));
        }
    }
    print_r(json_encode(api_err("rejected")));
} elseif ($q == "getWork") {
    if ($_config->sanity_sync == 1) {
        print_r(json_encode(api_err("sanity-sync")));
    }
    $block = new SBlock();
    $current = $block->current();
    $height = $current['height'] += 1;
    $date = time();
    // get the mempool transactions
    $txn = new STx();
    $data = $txn->mempool($block->max_transactions());


    $difficulty = $block->difficulty();
    // always sort  the transactions in the same way
    ksort($data);


    // reward transaction and signature
    $reward = $block->reward($height, $data);
    print_r(json_encode(api_echo(["height"=>$height, "data"=>$data, "reward"=>$reward, "block"=>$current['id'], "difficulty"=>$difficulty])));
} else {
    print_r(json_encode(api_err("invalid command")));
}
