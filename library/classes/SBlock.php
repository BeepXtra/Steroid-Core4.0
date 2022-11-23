<?php

defined('_SECURED') or die('Restricted access');

/**
 * Class for steroid platform
 *
 * @author exevior
 */
class SBlock {

    public function add_log($hash, $log) {
        global $db;
        $hash = san($hash);
        //$json=["table"=>"masternode", "key"=>"public_key","id"=>$x['public_key'], "vals"=>['ip'=>$current_ip] ];
        $db->run("INSERT into logs SET block=:id, json=:json", [':id' => $hash, ":json" => json_encode($log)]);
    }

    public function reverse_log($hash) {
        global $db;
        $r = $db->run("SELECT json, id FROM logs WHERE block=:id ORDER by id DESC", [":id" => $hash]);
        foreach ($r as $json) {
            $old = json_decode($json['json'], true);
            if ($old !== false && is_array($old)) {
                //making sure there's no sql injection here, as the table name and keys are sanitized to A-Za-z0-9_
                $table = san($old['table']);
                $key = san($old['key'], '_');
                $id = san($old['id'], '_');
                foreach ($old['vals'] as $v => $l) {
                    $v = san($v, '_');
                    $db->run("UPDATE `$table` SET `$v`=:val WHERE `$key`=:keyid", [":keyid" => $id, ":val" => $l]);
                }
            }
            $db->run("DELETE FROM logs WHERE id=:id", [":id" => $json['id']]);
        }
    }

    public function add($height, $public_key, $nonce, $data, $date, $signature, $difficulty, $reward_signature, $argon, $bootstrapping = false) {
        global $db;
        $acc = new SWallet();
        $trx = new STx();

        $generator = $acc->get_address($public_key);

        // the transactions are always sorted in the same way, on all nodes, as they are hashed as json
        ksort($data);

        // create the hash / block id
        $hash = $this->hash($generator, $height, $date, $nonce, $data, $signature, $difficulty, $argon);

        $json = json_encode($data);

        // create the block data and check it against the signature
        $info = "{$generator}-{$height}-{$date}-{$nonce}-{$json}-{$difficulty}-{$argon}";
        // _log($info,3);
        if (!$bootstrapping) {
            if (!$acc->check_signature($info, $signature, $public_key)) {
                _log("Block signature check failed");
                return false;
            }

            if (!$this->parse_block($hash, $height, $data, true)) {
                _log("Parse block failed");
                return false;
            }
        }
        // lock table to avoid race conditions on blocks
        $db->exec("LOCK TABLES blocks WRITE, accounts WRITE, transactions WRITE, mempool WRITE, masternode WRITE, peers write, config WRITE, assets WRITE, assets_balance WRITE, assets_market WRITE, votes WRITE, logs WRITE");

        $reward = $this->reward($height, $data);

        $msg = '';

        $mn_reward_rate = 0.33;

        // hf
        if ($height > 216000) {
            $votes = [];
            $r = $db->run("SELECT id,val FROM votes");
            foreach ($r as $vote) {
                $votes[$vote['id']] = $vote['val'];
            }
            // emission cut by 30%
            if ($votes['emission30'] == 1) {
                $reward = round($reward * 0.7);
            }
            // 50% to masternodes
            if ($votes['masternodereward50'] == 1) {
                $mn_reward_rate = 0.5;
            }
            // minimum reward to always be 10 bpc
            if ($votes['endless10reward'] == 1 && $reward < 10) {
                $reward = 10;
            }
        }


        if ($height >= 320000) {
            //reward the masternode
            // do not reward blacklisted mns after 320000
            $check_mn_votes = "";
            if ($height > 320000) {
                $check_mn_votes = "and voted=0";
            }
            $mn_winner = $db->single(
                    "SELECT public_key FROM masternode WHERE status=1 AND blacklist<:current AND height<:start $check_mn_votes ORDER by last_won ASC, public_key ASC LIMIT 1",
                    [":current" => $height, ":start" => $height - 360]
            );
            _log("MN Winner: $mn_winner", 2);
            if ($mn_winner !== false) {
                $mn_reward = round($mn_reward_rate * $reward, 8);
                $reward = round($reward - $mn_reward, 8);
                $reward = number_format($reward, 8, ".", "");
                if ($mn_reward < 1) {
                    $mn_reward = 1;
                }
                $mn_reward = number_format($mn_reward, 8, ".", "");
                _log("MN Reward: $mn_reward", 2);
            }
        }
        $cold_winner = false;
        $cold_reward = 0;
        $cold_last_won = 0;
        if ($height > 216000) {
            if ($votes['coldstacking'] == 1) {
                $cold_reward = round($mn_reward * 0.2, 8);
                $mn_reward = $mn_reward - $cold_reward;
                $mn_reward = number_format($mn_reward, 8, ".", "");
                $cold_reward = number_format($cold_reward, 8, ".", "");
                $cw = $db->row(
                        "SELECT public_key, cold_last_won  FROM masternode WHERE height<:start ORDER by cold_last_won ASC, public_key ASC LIMIT 1",
                        [":start" => $height - 360]
                );
                $cold_winner = $cw['public_key'];
                $cold_last_won = $cw['cold_last_won'];
                _log("Cold MN Winner: $cold_winner [$cold_last_won]", 2);
            }
        }



        // the reward transaction
        $transaction = [
            "src" => $generator,
            "dst" => $generator,
            "val" => $reward,
            "version" => 0,
            "date" => $date,
            "message" => $msg,
            "fee" => "0.00000000",
            "public_key" => $public_key,
        ];
        $transaction['signature'] = $reward_signature;
        // hash the transaction
        $transaction['id'] = $trx->hash($transaction);
        if (!$bootstrapping) {
            // check the signature
            $info = $transaction['val'] . "-" . $transaction['fee'] . "-" . $transaction['dst'] . "-" . $transaction['message'] . "-" . $transaction['version'] . "-" . $transaction['public_key'] . "-" . $transaction['date'];
            if (!$acc->check_signature($info, $reward_signature, $public_key)) {
                _log("Reward signature failed");
                $db->exec("UNLOCK TABLES");
                return false;
            }
        }
        // insert the block into the db
        $db->beginTransaction();
        $total = count($data);

        $bind = [
            ":id" => $hash,
            ":generator" => $generator,
            ":signature" => $signature,
            ":height" => $height,
            ":date" => $date,
            ":nonce" => $nonce,
            ":difficulty" => $difficulty,
            ":argon" => $argon,
            ":transactions" => $total,
        ];
        $res = $db->run(
                "INSERT into blocks SET id=:id, generator=:generator, height=:height,`date`=:date,nonce=:nonce, signature=:signature, difficulty=:difficulty, argon=:argon, transactions=:transactions",
                $bind
        );
        if ($res != 1) {
            // rollback and exit if it fails
            _log("Block DB insert failed");
            $db->rollback();
            $db->exec("UNLOCK TABLES");
            return false;
        }

        // insert the reward transaction in the db
        $res = $trx->add($hash, $height, $transaction);
        if ($res == false) {
            // rollback and exit if it fails
            _log("Reward DB insert failed");
            $db->rollback();
            $db->exec("UNLOCK TABLES");
            return false;
        }
        //masternode rewards
        if (isset($mn_winner) && $mn_winner !== false && $height >= 20 && $mn_reward > 0) {
            //cold stacking rewards

            if ($cold_winner !== false && $height > 20 && $cold_reward > 0) {
                $db->run("UPDATE accounts SET balance=balance+:bal WHERE public_key=:pub", [":pub" => $cold_winner, ":bal" => $cold_reward]);

                $bind = [
                    ":id" => hex2coin(hash("sha512", "cold" . $hash . $height . $cold_winner)),
                    ":public_key" => $public_key,
                    ":height" => $height,
                    ":block" => $hash,
                    ":dst" => $acc->get_address($cold_winner),
                    ":val" => $cold_reward,
                    ":fee" => 0,
                    ":signature" => $reward_signature,
                    ":version" => 0,
                    ":date" => $date,
                    ":message" => 'masternode-cold',
                ];
                $res = $db->run(
                        "INSERT into transactions SET id=:id, public_key=:public_key, block=:block,  height=:height, dst=:dst, val=:val, fee=:fee, signature=:signature, version=:version, message=:message, `date`=:date",
                        $bind
                );

                if ($res != 1) {
                    // rollback and exit if it fails
                    _log("Masternode Cold reward DB insert failed");
                    $db->rollback();
                    $db->exec("UNLOCK TABLES");
                    return false;
                }

                if ($height > 216070) {
                    $db->run("UPDATE masternode SET cold_last_won=:height WHERE public_key=:pub", [':pub' => $cold_winner, ":height" => $height]);

                    $this->add_log($hash, ["table" => "masternode", "key" => "public_key", "id" => $cold_winner, "vals" => ['cold_last_won' => $cold_last_won]]);
                }
            }


            $db->run("UPDATE accounts SET balance=balance+:bal WHERE public_key=:pub", [":pub" => $mn_winner, ":bal" => $mn_reward]);
            $bind = [
                ":id" => hex2coin(hash("sha512", "mn" . $hash . $height . $mn_winner)),
                ":public_key" => $public_key,
                ":height" => $height,
                ":block" => $hash,
                ":dst" => $acc->get_address($mn_winner),
                ":val" => $mn_reward,
                ":fee" => 0,
                ":signature" => $reward_signature,
                ":version" => 0,
                ":date" => $date,
                ":message" => 'masternode',
            ];
            $res = $db->run(
                    "INSERT into transactions SET id=:id, public_key=:public_key, block=:block,  height=:height, dst=:dst, val=:val, fee=:fee, signature=:signature, version=:version, message=:message, `date`=:date",
                    $bind
            );
            if ($res != 1) {
                // rollback and exit if it fails
                _log("Masternode reward DB insert failed");
                $db->rollback();
                $db->exec("UNLOCK TABLES");
                return false;
            }
            $res = $this->reset_fails_masternodes($mn_winner, $height, $hash);
            if (!$res) {

                // rollback and exit if it fails
                _log("Masternode log DB insert failed");
                $db->rollback();
                $db->exec("UNLOCK TABLES");
                return false;
            }
        }

        // parse the block's transactions and insert them to db
        $res = $this->parse_block($hash, $height, $data, false, $bootstrapping);

        if (($height - 1) % 3 == 2) {
            $this->blacklist_masternodes();
            $this->reset_fails_masternodes($public_key, $height, $hash);
        }

        // automated asset distribution, checked only every 1000 blocks to reduce load. Payouts every 10000 blocks.

        if ($height > 20 && $height % 50 == 1 && $res == true) { //  every 50 for testing. No initial height set yet.
            $res = $this->asset_distribute_dividends($height, $hash, $public_key, $date, $signature);
        }

        if ($height > 20 && $res == true) {
            $res = $this->asset_market_orders($height, $hash, $public_key, $date, $signature);
        }

        if ($height > 20 && $height % 43200 == 0) {
            $res = $this->masternode_votes($public_key, $height, $hash);
        }

        // if any fails, rollback
        if ($res == false) {
            _log("Rollback block", 3);
            $db->rollback();
        } else {
            _log("Commiting block", 3);
            $db->commit();
        }
        // relese the locking as everything is finished
        $db->exec("UNLOCK TABLES");
        return true;
    }

    public function masternode_votes($public_key, $height, $hash) {
        global $db;

        $bpcdev = 'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCxSfuVGzyQJhvgizJayys9rmiS3pu85PaYy3bGyHKSAvo75SmZJ78bgwY3gajeMXUfNbrM2Gv5WnTfrgFu9UPVckX';

        // masternode votes
        if ($height % 43200 == 0) {
            _log("Checking masternode votes", 3);
            $blacklist = [];
            $total_mns = $db->single("SELECT COUNT(1) FROM masternode");
            $total_mns_with_key = $db->single("SELECT COUNT(1) FROM masternode WHERE vote_key IS NOT NULL");

            // only if at least 50% of the masternodes have voting keys
            if ($total_mns_with_key / $total_mns > 0.50) {
                _log("Counting the votes from other masternodes", 3);
                $r = $db->run("SELECT message, count(message) as c FROM transactions WHERE version=106 AND height>:height group by message", [':height' => $height - 43200]);
                foreach ($r as $x) {
                    if ($x['c'] > $total_mns_with_key / 1.5) {
                        $blacklist[] = san($x['message']);
                    }
                }
            } else {
                // If less than 50% of the mns have voting key, BpcDev's votes are used
                _log("Counting BpcDev votes", 3);
                $r = $db->run("SELECT message FROM transactions WHERE version=106 AND height>:height AND public_key=:pub", [':height' => $height - 43200, ":pub" => $bpcdev]);
                foreach ($r as $x) {
                    $blacklist[] = san($x['message']);
                }
            }
            $r = $db->run("SELECT public_key FROM masternode WHERE voted=1");
            foreach ($r as $masternode) {
                if (!in_array($masternode, $blacklist)) {
                    _log("Masternode removed from voting blacklist - $masternode", 3);
                    $this->add_log($hash, ["table" => "masternode", "key" => "public_key", "id" => $masternode, "vals" => ['voted' => 1]]);
                    $db->run("UPDATE masternode SET voted=0 WHERE public_key=:pub", [":pub" => $masternode]);
                }
            }

            foreach ($blacklist as $masternode) {
                $res = $db->single("SELECT voted FROM masternode WHERE public_key=:pub", [":pub" => $masternode]);
                if ($res == 0) {
                    _log("Masternode blacklist voted - $masternode", 3);
                    $db->run("UPDATE masternode SET voted=1 WHERE public_key=:pub", [":pub" => $masternode]);
                    $this->add_log($hash, ["table" => "masternode", "key" => "public_key", "id" => $masternode, "vals" => ['voted' => 0]]);
                }
            }
        }

        // blockchain votes
        $voted = [];
        if ($height % 129600 == 0) {

            // only if at least 50% of the masternodes have voting keys
            if ($total_mns_with_key / $total_mns > 0.50) {
                _log("Counting masternode blockchain votes", 3);
                $r = $db->run("SELECT message, count(message) as c FROM transactions WHERE version=107 AND height>:height group by message", [':height' => $height - 129600]);
                foreach ($r as $x) {
                    if ($x['c'] > $total_mns_with_key / 1.5) {
                        $voted[] = san($x['message']);
                    }
                }
            } else {
                _log("Counting BpcDev blockchain votes", 3);
                // If less than 50% of the mns have voting key, BpcDev's votes are used
                $r = $db->run("SELECT message FROM transactions WHERE version=107 AND height>:height AND public_key=:pub", [':height' => $height - 129600, ":pub" => $bpcdev]);
                foreach ($r as $x) {
                    $voted[] = san($x['message']);
                }
            }


            foreach ($voted as $vote) {
                $v = $db->row("SELECT id, val FROM votes WHERE id=:id", [":id" => $vote]);
                if ($v) {
                    if ($v['val'] == 0) {
                        _log("Blockchain vote - $v[id] = 1", 3);
                        $db->run("UPDATE votes SET val=1 WHERE id=:id", [":id" => $v['id']]);
                        $this->add_log($hash, ["table" => "votes", "key" => "id", "id" => $v['id'], "vals" => ['val' => 0]]);
                    } else {
                        _log("Blockchain vote - $v[id] = 0", 3);
                        $db->run("UPDATE votes SET val=0 WHERE id=:id", [":id" => $v['id']]);
                        $this->add_log($hash, ["table" => "votes", "key" => "id", "id" => $v['id'], "vals" => ['val' => 1]]);
                    }
                }
            }
        }

        return true;
    }

    public function asset_market_orders($height, $hash, $public_key, $date, $signature) {
        global $db;
        require_once 'STx.php';
        $trx = new STx();
        // checks all bid market orders ordered in the same way on all nodes
        $r = $db->run("SELECT * FROM assets_market WHERE status=0 and val_done<val AND type='bid' ORDER by asset ASC, id ASC");
        foreach ($r as $x) {
            $finished = 0;
            //remaining part of the order
            $val = $x['val'] - $x['val_done'];
            // starts checking all ask orders that are still valid and are on the same price. should probably adapt this to allow lower price as well in the future.
            $asks = $db->run("SELECT * FROM assets_market WHERE status=0 and val_done<val AND asset=:asset AND price=:price AND type='ask' ORDER by price ASC, id ASC", [":asset" => $x['asset'], ":price" => $x['price']]);
            foreach ($asks as $ask) {
                //remaining part of the order
                $remaining = $ask['val'] - $ask['val_done'];
                // how much of the ask should we use to fill the bid order
                $use = 0;
                if ($remaining > $val) {
                    $use = $val;
                } else {
                    $use = $remaining;
                }
                $val -= $use;
                $db->run("UPDATE assets_market SET val_done=val_done+:done WHERE id=:id", [":id" => $ask['id'], ":done" => $use]);
                $db->run("UPDATE assets_market SET val_done=val_done+:done WHERE id=:id", [":id" => $x['id'], ":done" => $use]);
                // if we filled the order, we should exit the loop
                $db->run("INSERT into assets_balance SET account=:account, asset=:asset, balance=:balance ON DUPLICATE KEY UPDATE balance=balance+:balance2", [":account" => $x['account'], ":asset" => $x['asset'], ":balance" => $use, ":balance2" => $use]);
                $bpc = $use * $x['price'];
                $db->run("UPDATE accounts SET balance=balance+:balance WHERE id=:id", [":balance" => $bpc, ":id" => $ask['account']]);

                $random = hex2coin(hash("sha512", $x['id'] . $ask['id'] . $val . $hash));
                $new = [
                    "id" => $random,
                    "public_key" => $x['id'],
                    "dst" => $ask['id'],
                    "val" => $bpc,
                    "fee" => 0,
                    "signature" => $signature,
                    "version" => 58,
                    "date" => $date,
                    "message" => $use
                ];

                $res = $trx->add($hash, $height, $new);
                if (!$res) {
                    return false;
                }
                if ($val <= 0) {
                    break;
                }
            }
        }



        return true;
    }

    public function asset_distribute_dividends($height, $hash, $public_key, $date, $signature) {
        global $db;
        require_once 'STx.php';
        $trx = new STx();
        _log("Starting automated dividend distribution", 3);
        // just the assets with autodividend
        $r = $db->run("SELECT * FROM assets WHERE auto_dividend=1");

        if ($r === false) {
            return true;
        }
        foreach ($r as $x) {
            $asset = $db->row("SELECT id, public_key, balance FROM accounts WHERE id=:id", [":id" => $x['id']]);
            // minimum balance 1 bpc
            if ($asset['balance'] < 1) {
                _log("Asset $asset[id] not enough balance", 3);
                continue;
            }
            _log("Autodividend $asset[id] - $asset[balance] BPC", 3);
            // every 10000 blocks and at minimum 10000 of asset creation or last distribution, manual or automated
            $last = $db->single("SELECT height FROM transactions WHERE (version=54 OR version=50 or version=57) AND public_key=:pub ORDER by height DESC LIMIT 1", [":pub" => $asset['public_key']]);
            if ($height < $last + 100) { // 100 for testnet
                continue;
            }
            _log("Autodividend continue", 3);
            // generate a pseudorandom id  and version 54 transaction for automated dividend distribution. No fees for such automated distributions to encourage the system
            $random = hex2coin(hash("sha512", $x['id'] . $hash . $height));
            $new = [
                "id" => $random,
                "public_key" => $asset['public_key'],
                "dst" => $asset['id'],
                "val" => $asset['balance'],
                "fee" => 0,
                "signature" => $signature,
                "version" => 57,
                "date" => $date,
                "src" => $asset['id'],
                "message" => '',
            ];
            $res = $trx->add($hash, $height, $new);
            if (!$res) {
                return false;
            }
        }
        return true;
    }

    // resets the number of fails when winning a block and marks it with a transaction

    public function reset_fails_masternodes($public_key, $height, $hash) {
        global $db;
        $res = $this->masternode_log($public_key, $height, $hash);
        if ($res === 5) {
            return false;
        }

        if ($res) {
            $rez = $db->run("UPDATE masternode SET last_won=:last_won,fails=0 WHERE public_key=:public_key", [":public_key" => $public_key, ":last_won" => $height]);
            if ($rez != 1) {
                return false;
            }
        }
        return true;
    }

    //logs the current masternode status
    public function masternode_log($public_key, $height, $hash) {
        global $db;

        $mn = $db->row("SELECT blacklist,last_won,fails FROM masternode WHERE public_key=:public_key", [":public_key" => $public_key]);

        if (!$mn) {
            return false;
        }

        $id = hex2coin(hash("sha512", "resetfails-$hash-$height-$public_key"));
        $msg = "$mn[blacklist],$mn[last_won],$mn[fails]";

        $res = $db->run(
                "INSERT into transactions SET id=:id, block=:block, height=:height, dst=:dst, val=0, fee=0, signature=:sig, version=111, message=:msg, date=:date, public_key=:public_key",
                [":id" => $id, ":block" => $hash, ":height" => $height, ":dst" => $hash, ":sig" => $hash, ":msg" => $msg, ":date" => time(), ":public_key" => $public_key]
        );
        if ($res != 1) {
            return 5;
        }
        return true;
    }

    // returns the current block, without the transactions
    public function current() {
        global $db;
        $current = $db->row("SELECT * FROM blocks ORDER by height DESC LIMIT 1");
        if (!$current) {
            $this->genesis();
            return $this->current(true);
        }
        return $current;
    }

    // returns the previous block
    public function prev() {
        global $db;
        $current = $db->row("SELECT * FROM blocks ORDER by height DESC LIMIT 1,1");

        return $current;
    }

    // calculates the difficulty / base target for a specific block. The higher the difficulty number, the easier it is to win a block.
    public function difficulty($height = 0) {
        global $db;

        // if no block height is specified, use the current block.
        if ($height == 0) {
            $current = $this->current();
        } else {
            $current = $this->get($height);
        }


        $height = $current['height'];

        /* if ($height == 10801||($height>=80456&&$height<80460)) {
          return "5555555555"; //hard fork 10900 resistance, force new difficulty
          } */

        // last 20 blocks used to check the block times
        $limit = 20;
        if ($height < 20) {
            $limit = $height - 1;
        }

        // for the first 10 blocks, use the genesis difficulty
        if ($height < 10) {
            return $current['difficulty'];
        }

        // before first 20 blocks
        if ($height < 20) {
            // elapsed time between the last 20 blocks
            $first = $db->row("SELECT `date` FROM blocks  ORDER by height DESC LIMIT :limit,1", [":limit" => $limit]);
            $time = $current['date'] - $first['date'];

            // avg block time
            $result = ceil($time / $limit);
            _log("Block time: $result", 3);

            // if larger than 200 sec, increase by 5%
            if ($result > 220) {
                $dif = bcmul($current['difficulty'], 1.05);
            } elseif ($result < 240) {
                // if lower, decrease by 5%
                $dif = bcmul($current['difficulty'], 0.99995);
            } else {
                // keep current difficulty
                $dif = $current['difficulty'];
            }
        } elseif ($height >= 20) {
            $type = $height % 2;
            $current = $db->row("SELECT difficulty from blocks WHERE height<=:h ORDER by height DESC LIMIT 1,1", [":h" => $height]);
            $blks = 0;
            $total_time = 0;
            $blk = $db->run("SELECT `date`, height FROM blocks WHERE height<=:h  ORDER by height DESC LIMIT 20", [":h" => $height]);
            for ($i = 0; $i < 19; $i++) {
                $ctype = $blk[$i + 1]['height'] % 2;
                $time = $blk[$i]['date'] - $blk[$i + 1]['date'];
                if ($type != $ctype) {
                    continue;
                }
                $blks++;
                $total_time += $time;
            }
            $result = ceil($total_time / $blks);
            _log("Block time: $result", 3);
            // 1 minute blocktime
            if ($type /* && disable for now  $result == false */) {
                // miner block
                // 1 minute blocktime
                if ($result > 70) {
                    $dif = bcmul($current['difficulty'], 1.05);
                } elseif ($result < 50) {
                    // if lower, decrease by 5%
                    $dif = bcmul($current['difficulty'], 0.95);
                } else {
                    // keep current difficulty
                    $dif = $current['difficulty'];
                }
            } else {
                // masternode blocks
                // 2 minutes blocktime
                if ($result > 70) {
                    $dif = bcmul($current['difficulty'], 1.05);
                } elseif ($result < 50) {
                    // if lower, decrease by 5%
                    $dif = bcmul($current['difficulty'], 0.95);
                } else {
                    // keep current difficulty
                    $dif = $current['difficulty'];
                }
            }
        } else {
            // hardfork 80000, fix difficulty targetting



            $type = $height % 2;
            // for mn, we use gpu diff
            if (!$type) {
                return $current['difficulty'];
            }

            $blks = 0;
            $total_time = 0;
            $blk = $db->run("SELECT `date`, height FROM blocks  ORDER by height DESC LIMIT 60");
            for ($i = 0; $i < 59; $i++) {
                $ctype = $blk[$i + 1]['height'] % 2;
                $time = $blk[$i]['date'] - $blk[$i + 1]['date'];
                if ($type == $ctype) {
                    continue;
                }
                $blks++;
                $total_time += $time;
            }
            $result = ceil($total_time / $blks);
            _log("Block time: $result", 3);

            // if larger than 260 sec, increase by 5%
            if ($result > 60) {
                $dif = bcmul($current['difficulty'], 1.05);
            } elseif ($result < 20) {
                // if lower, decrease by 5%
                $dif = bcmul($current['difficulty'], 0.95);
            } else {
                // keep current difficulty
                $dif = $current['difficulty'];
            }
        }






        if (strpos($dif, '.') !== false) {
            $dif = substr($dif, 0, strpos($dif, '.'));
        }

        //minimum and maximum diff
        if ($dif < 1000) {
            $dif = 1000;
        }
        if ($dif > 9223372036854775800) {
            $dif = 9223372036854775800;
        }
        _log("Difficulty: $dif", 3);
        return $dif;
    }

    // calculates the maximum block size and increase by 10% the number of transactions if > 100 on the last 100 blocks
    public function max_transactions() {
        global $db;
        $current = $this->current();
        $limit = $current['height'] - 100;
        $avg = $db->single("SELECT AVG(transactions) FROM blocks WHERE height>:limit", [":limit" => $limit]);
        if ($avg < 100) {
            return 100;
        }
        return ceil($avg * 1.1);
    }

    // calculate the reward for each block
    public function reward($id, $data = []) {
        global $platform;
        if($id ==1 && $platform->config->premine){
            return number_format($platform->config->premine, 8, '.', '');
        }
        // starting reward
        $reward = 1;
        // decrease by 1% each 10800 blocks (approx 1 month)
        $factor = floor($id / 10800) / 100;
        $reward -= $reward * $factor;

        if ($reward < 0) {
            $reward = 0;
        }
        // calculate the transaction fees
        $fees = 0;
        if (count($data) > 0) {
            foreach ($data as $x) {
                $fees += $x['fee'];
            }
        }
        if($id === 1){
            //pre-mine configuration
            
        }
        return number_format($reward + $fees, 8, '.', '');
    }

    // checks the validity of a block
    public function check($data) {
        // argon must have at least 20 chars
        if (strlen($data['argon']) < 20) {
            _log("Invalid block argon - $data[argon]");
            return false;
        }
        require_once 'SWallet.php';
        $acc = new SWallet();

        if ($data['date'] > time() + 30) {
            _log("Future block - $data[date] $data[public_key]", 2);
            return false;
        }

        // generator's public key must be valid
        if (!$acc->valid_key($data['public_key'])) {
            _log("Invalid public key - $data[public_key]");
            return false;
        }

        //difficulty should be the same as our calculation
        if ($data['difficulty'] != $this->difficulty()) {
            _log("Invalid difficulty - $data[difficulty] - " . $this->difficulty());
            return false;
        }

        //check the argon hash and the nonce to produce a valid block
        if (!$this->mine($data['public_key'], $data['nonce'], $data['argon'], $data['difficulty'], 0, 0, $data['date'])) {
            _log("Mine check failed");
            return false;
        }

        return true;
    }

    // creates a new block on this node
    public function forge($nonce, $argon, $public_key, $private_key) {
        _log("Forge attempt - nonce:" . $nonce . " argon:" . $argon . " miner:" . $public_key);
        //check the argon hash and the nonce to produce a valid block
        if (!$this->mine($public_key, $nonce, $argon)) {
            _log("Forge failed - Invalid argon");
            return false;
        }

        // the block's date timestamp must be bigger than the last block
        $current = $this->current();
        $height = $current['height'] += 1;
        $date = time();
        if ($date <= $current['date']) {
            _log("Forge failed - Date older than last block");
            return false;
        }

        // get the mempool transactions
        require_once 'STx.php';
        $txn = new STx();
        $data = $txn->mempool($this->max_transactions());

        $difficulty = $this->difficulty();
        require_once 'SWallet.php';
        $acc = new SWallet();
        $generator = $acc->get_address($public_key);

        // always sort  the transactions in the same way
        ksort($data);

        // sign the block
        $signature = $this->sign($generator, $height, $date, $nonce, $data, $private_key, $difficulty, $argon);

        // reward transaction and signature
        $reward = $this->reward($height, $data);
        $mn_reward_rate = 0.33;
        global $db;
        // hf
        if ($height > 216000) {
            $votes = [];
            $r = $db->run("SELECT id,val FROM votes");
            foreach ($r as $vote) {
                $votes[$vote['id']] = $vote['val'];
            }
            // emission cut by 30%
            if ($votes['emission30'] == 1) {
                $reward = round($reward * 0.7);
            }
            // 50% to masternodes
            if ($votes['masternodereward50'] == 1) {
                $mn_reward_rate = 0.5;
            }

            // minimum reward to always be 10 bpc
            if ($votes['endless10reward'] == 1 && $reward < 10) {
                $reward = 10;
            }
        }

        if ($height >= 80458) {
            //reward the masternode
            // do not reward blacklisted mns after 320000
            $check_mn_votes = "";
            if ($height > 320000) {
                $check_mn_votes = "and voted=0";
            }

            $mn_winner = $db->single(
                    "SELECT public_key FROM masternode WHERE status=1 AND blacklist<:current AND height<:start $check_mn_votes ORDER by last_won ASC, public_key ASC LIMIT 1",
                    [":current" => $height, ":start" => $height - 360]
            );
            _log("MN Winner: $mn_winner", 2);
            if ($mn_winner !== false) {
                $mn_reward = round($mn_reward_rate * $reward, 8);
                $reward = round($reward - $mn_reward, 8);
                $reward = number_format($reward, 8, ".", "");
                $mn_reward = number_format($mn_reward, 8, ".", "");
                _log("MN Reward: $mn_reward", 2);
            }
        }

        $msg = '';
        $transaction = [
            "src" => $generator,
            "dst" => $generator,
            "val" => $reward,
            "version" => 0,
            "date" => $date,
            "message" => $msg,
            "fee" => "0.00000000",
            "public_key" => $public_key,
        ];
        ksort($transaction);
        $reward_signature = $txn->sign($transaction, $private_key);

        // add the block to the blockchain
        $res = $this->add(
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
        if (!$res) {
            _log("Forge failed - Block->Add() failed");
            return false;
        }
        return true;
    }

    public function blacklist_masternodes() {
        global $db;
        _log("Checking if there are masternodes to be blacklisted", 2);
        $current = $this->current();
        if (($current['height'] - 1) % 3 != 2) {
            _log("bad height");
            return;
        }
        $last = $this->get($current['height'] - 1);
        $total_time = $current['date'] - $last['date'];
        _log("blacklist total time $total_time");
        if ($total_time <= 600 && $current['height'] < 80500) {
            return;
        }
        if ($current['height'] >= 80500 && $total_time < 360) {
            return false;
        }
        if ($current['height'] >= 80500) {
            $total_time -= 360;
            $tem = floor($total_time / 120) + 1;
            if ($tem > 5) {
                $tem = 5;
            }
        } else {
            $tem = floor($total_time / 600);
        }
        _log("We have masternodes to blacklist - $tem", 2);
        $ban = $db->run(
                "SELECT public_key, blacklist, fails, last_won FROM masternode WHERE status=1 AND blacklist<:current AND height<:start ORDER by last_won ASC, public_key ASC LIMIT 0,:limit",
                [":current" => $last['height'], ":start" => $last['height'] - 360, ":limit" => $tem]
        );
        _log(json_encode($ban));
        $i = 0;
        foreach ($ban as $b) {
            $this->masternode_log($b['public_key'], $current['height'], $current['id']);
            _log("Blacklisting masternode - $i $b[public_key]", 2);
            $btime = 10;
            if ($current['height'] > 83000) {
                $btime = 360;
            }
            $db->run("UPDATE masternode SET fails=fails+1, blacklist=:blacklist WHERE public_key=:public_key", [":public_key" => $b['public_key'], ":blacklist" => $current['height'] + (($b['fails'] + 1) * $btime)]);
            $i++;
        }
    }

    // check if the arguments are good for mining a specific block
    public function mine($public_key, $nonce, $argon, $difficulty = 0, $current_id = 0, $current_height = 0, $time = 0) {
        global $_config;
        //_log("-->STARTING MINE BLOCK ", 3);
        // invalid future blocks and exploit attempts
        if ($time > time() + 30) {
            //_log("-->time in the future ", 3);
            return false;
        }


        // if no id is specified, we use the current
        if ($current_id === 0 || $current_height === 0) {
            $current = $this->current();
            $current_id = $current['id'];
            $current_height = $current['height'];
        }

        if ($time == 0) {
            $time = time();
        }
        //_log("Block Timestamp $time", 3);
        // get the current difficulty if empty
        if ($difficulty === 0) {
            $difficulty = $this->difficulty();
        }
        //Adjust for first blocks to kickstart after genesis
        if ($current_height < 20) {
            $difficulty = $difficulty;
        }

        if (empty($public_key)) {
            _log("Empty public key", 1);
            return false;
        }

        if ($current_height < 20) {
            if ($current_height % 2 == 0) {
                // cpu mining
                _log("CPU Mining - $current_height", 2);
                $argon = '$argon2i$v=19$m=524288,t=1,p=1' . $argon;
                //_log("argon submitted " . $argon, 3);
            } else {
                // gpu mining
                _log("GPU Mining - $current_height", 2);
                $argon = '$argon2i$v=19$m=16384,t=4,p=4' . $argon;
                //_log("argon submitted " . $argon, 3);
            }
        } else {
            _log("Block - $current_height", 2);

            if ($current_height % 2 == 0) {
                // cpu mining
                _log("CPU Mining - $current_height", 2);
                $argon = '$argon2i$v=19$m=524288,t=1,p=1' . $argon;
                //_log("argon submitted " . $argon, 3);
            } elseif ($current_height % 2 == 1) {
                // gpu mining
                _log("GPU Mining - $current_height", 2);
                $argon = '$argon2i$v=19$m=16384,t=4,p=4' . $argon;
                //_log("argon submitted " . $argon, 3);
            } else {
                _log("Masternode Mining - $current_height", 2);
                // masternode
                global $db;

                // fake time
                if ($time > time()) {
                    _log("Masternode block in the future - $time", 1);
                    return false;
                }

                // selecting the masternode winner in order
                $winner = $db->single(
                        "SELECT public_key FROM masternode WHERE status=1 AND blacklist<:current AND height<:start ORDER by last_won ASC, public_key ASC LIMIT 1",
                        [":current" => $current_height, ":start" => $current_height - 360]
                );

                // if there are no active masternodes, give the block to gpu
                if ($winner === false) {
                    _log("No active masternodes, reverting to gpu", 1);
                    $argon = '$argon2i$v=19$m=16384,t=4,p=4' . $argon;
                } else {
                    _log("The first masternode winner should be $winner", 1);
                    // 4 mins need to pass since last block
                    $last_time = $db->single("SELECT `date` FROM blocks WHERE height=:height", [":height" => $current_height]);
                    if ($time - $last_time < 240 && $_config->testnet == false) {
                        $mempool = $db->single("SELECT count(*) as counttx FROM mempool", []);
                        if ($mempool && $mempool['counttx']) {
                            //we have work to do... curry on
                            return true;
                        } else {
                            _log("4 minutes have not passed since the last block - $time", 1);
                            return false;
                        }
                    }

                    if ($public_key == $winner) {
                        return true;
                    }
                    // if 10 mins have passed, try to give the block to the next masternode and do this every 10mins
                    _log("Last block time: $last_time, difference: " . ($time - $last_time), 3);
                    if (($time - $last_time > 600 && $current_height > 20)) {
                        _log("Current public_key $public_key", 3);
                        if ($current_height >= 20) {
                            $total_time = $time - $last_time;
                            $total_time -= 360;
                            $tem = floor($total_time / 120) + 1;
                        } else {
                            $tem = floor(($time - $last_time) / 600);
                        }
                        $winner = $db->single(
                                "SELECT public_key FROM masternode WHERE status=1 AND blacklist<:current AND height<:start ORDER by last_won ASC, public_key ASC LIMIT :tem,1",
                                [":current" => $current_height, ":start" => $current_height - 360, ":tem" => $tem]
                        );
                        _log("Moving to the next masternode - $tem - $winner", 1);
                        // if all masternodes are dead, give the block to gpu
                        if ($winner === false || ($tem >= 5 && $current_height >= 20)) {
                            _log("All masternodes failed, giving the block to gpu", 1);
                            $argon = '$argon2i$v=19$m=16384,t=4,p=4' . $argon;
                        } elseif ($winner == $public_key) {
                            return true;
                        } else {
                            return false;
                        }
                    } else {
                        _log("A different masternode should win this block $public_key - $winner", 2);
                        return false;
                    }
                }
            }
        }

        // the hash base for argon
        $base = $public_key . '-' . $nonce . '-' . $current_id . '-' . $difficulty;
        _log("Base " . $base." Argon:".$argon, 3);
        // check argon's hash validity
        if (!password_verify($base, $argon)) {
            _log("--> ARGON VERIFY FAILED - $base - $argon", 3);
            return false;
        }
        //_log("--> ARGON VERIFY PASSED", 3);
        // all nonces are valid in testnet
        if ($_config->testnet == true) {
            return true;
        }

        // prepare the base for the hashing
        $hash = $base . $argon;

        // hash the base 6 times
        for ($i = 0; $i < 5; $i++) {
            $hash = hash("sha512", $hash, true);
        }
        $hash = hash("sha512", $hash);

        // split it in 2 char substrings, to be used as hex
        $m = str_split($hash, 2);

        // calculate a number based on 8 hex numbers - no specific reason, we just needed an algoritm to generate the number from the hash
        $duration = hexdec($m[10]) . hexdec($m[15]) . hexdec($m[20]) . hexdec($m[23]) . hexdec($m[31]) . hexdec($m[40]) . hexdec($m[45]) . hexdec($m[55]);

        // the number must not start with 0
        $duration = ltrim($duration, '0');

        // divide the number by the difficulty and create the deadline
        $result = gmp_div($duration, $difficulty);
        $limit = $difficulty * 10000;
        _log("result ".$result." limit ".$limit, 3);
        // if the deadline >0 and <=240, the arguments are valid fora  block win
        if ($result > 0 && $result <= $limit) {
            _log("YYYYYYYYYYYYYYYYYYYYYYY", 3);
            return true;
        }
        return false;
    }

    // parse the block transactions
    public function parse_block($block, $height, $data, $test = true, $bootstrapping = false) {
        global $db;
        // data must be array
        if ($data === false) {
            _log("Block data is false", 3);
            return false;
        }
        $acc = new SWallet();
        $trx = new STx();
        // no transactions means all are valid
        if (count($data) == 0) {
            return true;
        }

        // check if the number of transactions is not bigger than current block size
        $max = $this->max_transactions();
        if (count($data) > $max) {
            _log("Too many transactions in block", 3);
            return false;
        }

        $balance = [];
        $mns = [];

        foreach ($data as &$x) {
            // get the sender's account if empty
            if (empty($x['src'])) {
                $x['src'] = $acc->get_address($x['public_key']);
            }
            if (!$bootstrapping) {
                //validate the transaction
                if (!$trx->check($x, $height)) {
                    _log("Transaction check failed - $x[id]", 3);
                    return false;
                }
                if ($x['version'] >= 100 && $x['version'] < 110 && $x['version'] != 106 && $x['version'] != 107) {
                    $mns[] = $x['public_key'];
                }
                if ($x['version'] == 106 || $x['version'] == 107) {
                    $mns[] = $x['public_key'] . $x['message'];
                }

                // prepare total balance
                $balance[$x['src']] += $x['val'] + $x['fee'];

                // check if the transaction is already on the blockchain
                if ($db->single("SELECT COUNT(1) FROM transactions WHERE id=:id", [":id" => $x['id']]) > 0) {
                    _log("Transaction already on the blockchain - $x[id]", 3);
                    return false;
                }
            }
        }
        //only a single masternode transaction per block for any masternode
        if (count($mns) != count(array_unique($mns))) {
            _log("Too many masternode transactions", 3);
            return false;
        }

        if (!$bootstrapping) {
            // check if the account has enough balance to perform the transaction
            foreach ($balance as $id => $bal) {
                $res = $db->single(
                        "SELECT COUNT(1) FROM accounts WHERE id=:id AND balance>=:balance",
                        [":id" => $id, ":balance" => $bal]
                );
                if ($res == 0) {
                    _log("Not enough balance for transaction - $id", 3);
                    return false; // not enough balance for the transactions
                }
            }
        }
        // if the test argument is false, add the transactions to the blockchain
        if ($test == false) {
            foreach ($data as $d) {
                $res = $trx->add($block, $height, $d);
                if ($res == false) {
                    return false;
                }
            }
        }

        return true;
    }

    // initialize the blockchain, add the genesis block
    private function genesis() {
        global $db;
        
        
        
        $nonce = base64_encode(openssl_random_pseudo_bytes(32));
            //$nonce = preg_replace("/[^a-zA-Z0-9]/", "", $nonce);
            $base = 'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCxSfuVGzyQJhvgizJayys9rmiS3pu85PaYy3bGyHKSAvo75SmZJ78bgwY3gajeMXUfNbrM2Gv5WnTfrgFu9UPVckX-IT83QNECZQbJ2kNA6EXQUrswoaKgvvSco7DsBNjseI-1-5555555555';
            //$base = $this->publicKey."-".$nonce."-".$this->height."-".$this->difficulty;
            

            //echo "/n $base/n";
            //PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCx8yGj2PNN4MTehQGt5t3TXuLUsVBi52qQuoXChcMpsUBHu9khFJjTWLPXM3L6KSjm16kfwjQmvDUQ3URv5qiL9Hy-ptCvZFwwejjSIqN7JNxgjUZxBRxqeruG7FYo6pU8-L6oyJzUD7FkbyLYMeps6qAh7iTzrHvPHMqq8x9dyiUAbGDHAFqWrbbTK1rPaJ9mh8UReDhQvMRwCwPTpU6Z4Zgv-5555555555000000
            //$argon = $this->genArgon($base);
            
        $signature = 'iKx1CJPRFSgRshYYfd4nHkTKfam7bhGWcfXa6xmNFDkX3TUG4gm85gWCaXYJP3aGxSmVuvZ8ukaAdCbZETYPH3355d4dv3UDrn';
        $generator = '3G6XrZGoBwbBRG2WpXpvxGWcPiAovAiYyFAAVusSGXmeDpwe4o7iHmabyDRX1QzWL7rsGQcBfqAM2d13erw1Sthv';
        $public_key = 'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCxSfuVGzyQJhvgizJayys9rmiS3pu85PaYy3bGyHKSAvo75SmZJ78bgwY3gajeMXUfNbrM2Gv5WnTfrgFu9UPVckX';
        $reward_signature = 'iKx1CJPRFSgRshYYfd4nHkTKfam7bhGWcfXa6xmNFDkX3TUG4gm85gWCaXYJP3aGxSmVuvZ8ukaAdCbZETYPH3355d4dv3UDrn';
        $argon = '$c3JqODRMU3U5VXo4dnA1Mw$VNlhX+SSyQBLEr7VWw98DtsG4Z1S9mB7G2Z8Jun/B0E';

        $difficulty = "5555555555";
        $height = 1;
        $data = [];
        $date = '1646823187';
        $nonce = 'IT83QNECZQbJ2kNA6EXQUrswoaKgvvSco7DsBNjseI';

        $res = $this->add(
                $height,
                $public_key,
                $nonce,
                $data,
                $date,
                $signature,
                $difficulty,
                $reward_signature,
                $argon,
                1
        );
        if (!$res) {
            print_r(json_encode(api_err("Could not add the genesis block.")));
            exit;
        }
    }

    // delete last X blocks
    public function pop($no = 1) {
        $current = $this->current();
        return $this->delete($current['height'] - $no + 1);
    }

    // delete all blocks >= height
    public function delete($height) {
        global $_config;
        if ($height < 2) {
            $height = 2;
        }
        global $db;
        $trx = new STx();

        $r = $db->run("SELECT * FROM blocks WHERE height>=:height ORDER by height DESC", [":height" => $height]);

        if (count($r) == 0) {
            return true;
        }
        $db->beginTransaction();
        $db->exec("LOCK TABLES blocks WRITE, accounts WRITE, transactions WRITE, mempool WRITE, masternode WRITE, peers write, config WRITE, assets WRITE, assets_balance WRITE, assets_market WRITE, votes WRITE,logs WRITE");

        foreach ($r as $x) {
            $res = $trx->reverse($x['id']);
            if ($res === false) {
                _log("A transaction could not be reversed. Delete block failed.");
                $db->rollback();
                // the blockchain has some flaw, we should resync from scratch

                $current = $this->current();
                if (($current['date'] < time() - (3600 * 48)) && $_config->auto_resync !== false) {
                    _log("Blockchain corrupted. Resyncing from scratch.");
                    $db->run("SET foreign_key_checks=0;");
                    $tables = ["accounts", "transactions", "mempool", "masternode", "blocks"];
                    foreach ($tables as $table) {
                        $db->run("TRUNCATE TABLE {$table}");
                    }
                    $db->run("SET foreign_key_checks=1;");
                    $db->exec("UNLOCK TABLES");

                    $db->run("UPDATE config SET val=0 WHERE cfg='sanity_sync'");
                    @unlink(SANITY_LOCK_PATH);
                    system("php sanity.php  > /dev/null 2>&1  &");
                    exit;
                }
                $db->exec("UNLOCK TABLES");
                return false;
            }
            $res = $db->run("DELETE FROM blocks WHERE id=:id", [":id" => $x['id']]);
            if ($res != 1) {
                _log("Delete block failed.");
                $db->rollback();
                $db->exec("UNLOCK TABLES");
                return false;
            }
            $this->reverse_log($x['id']);
        }



        $db->commit();
        $db->exec("UNLOCK TABLES");
        return true;
    }

    // delete specific block
    public function delete_id($id) {
        global $db;
        $trx = new STx();

        $x = $db->row("SELECT * FROM blocks WHERE id=:id", [":id" => $id]);

        if ($x === false) {
            return false;
        }
        // avoid race conditions on blockchain manipulations
        $db->beginTransaction();
        $db->exec("LOCK TABLES blocks WRITE, accounts WRITE, transactions WRITE, mempool WRITE, masternode WRITE, peers write, config WRITE, assets WRITE, assets_balance WRITE, assets_market WRITE, votes WRITE, logs WRITE");

        // reverse all transactions of the block
        $res = $trx->reverse($x['id']);
        if ($res === false) {
            // rollback if you can't reverse the transactions
            $db->rollback();
            $db->exec("UNLOCK TABLES");
            return false;
        }
        // remove the actual block
        $res = $db->run("DELETE FROM blocks WHERE id=:id", [":id" => $x['id']]);
        if ($res != 1) {
            //rollback if you can't delete the block
            $db->rollback();
            $db->exec("UNLOCK TABLES");
            return false;
        }
        // commit and release if all good
        $db->commit();
        $db->exec("UNLOCK TABLES");
        return true;
    }

    // sign a new block, used when mining
    public function sign($generator, $height, $date, $nonce, $data, $key, $difficulty, $argon) {
        $json = json_encode($data);
        $info = "{$generator}-{$height}-{$date}-{$nonce}-{$json}-{$difficulty}-{$argon}";

        $signature = ec_sign($info, $key);
        return $signature;
    }

    // generate the sha512 hash of the block data and converts it to base58
    public function hash($public_key, $height, $date, $nonce, $data, $signature, $difficulty, $argon) {
        $json = json_encode($data);
        $hash = hash("sha512", "{$public_key}-{$height}-{$date}-{$nonce}-{$json}-{$signature}-{$difficulty}-{$argon}");
        return hex2coin($hash);
    }

    // exports the block data, to be used when submitting to other peers
    public function export($id = "", $height = "") {
        if (empty($id) && empty($height)) {
            return false;
        }

        global $db;
        $trx = new STx();
        if (!empty($height)) {
            $block = $db->row("SELECT * FROM blocks WHERE height=:height", [":height" => $height]);
        } else {
            $block = $db->row("SELECT * FROM blocks WHERE id=:id", [":id" => $id]);
        }

        if (!$block) {
            return false;
        }
        $r = $db->run("SELECT * FROM transactions WHERE version>0 AND block=:block", [":block" => $block['id']]);
        $transactions = [];
        foreach ($r as $x) {
            if ($x['version'] > 110 || $x['version'] == 57 || $x['version'] == 58 || $x['version'] == 59) {
                //internal transactions
                continue;
            }
            $trans = [
                "id" => $x['id'],
                "dst" => $x['dst'],
                "val" => $x['val'],
                "fee" => $x['fee'],
                "signature" => $x['signature'],
                "message" => $x['message'],
                "version" => $x['version'],
                "date" => $x['date'],
                "public_key" => $x['public_key'],
            ];
            ksort($trans);
            $transactions[$x['id']] = $trans;
        }
        ksort($transactions);
        $block['data'] = $transactions;

        // the reward transaction always has version 0
        $gen = $db->row(
                "SELECT public_key, signature FROM transactions WHERE  version=0 AND block=:block AND message=''",
                [":block" => $block['id']]
        );
        $block['public_key'] = $gen['public_key'];
        $block['reward_signature'] = $gen['signature'];
        return $block;
    }

    //return a specific block as array
    public function get($height) {
        global $db;
        if (empty($height)) {
            return false;
        }
        $block = $db->row("SELECT * FROM blocks WHERE height=:height", [":height" => $height]);
        return $block;
    }

}
