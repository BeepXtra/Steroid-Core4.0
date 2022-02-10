<?php

/**
 * PHP Blockchain Platform
 *
 * @author exevior
 */
defined('_SECURED') or die('Restricted access');
include("library/classes/Block.php");

class Schain {

    public $db;
    public $config;
    public $platform;

    //Our constructor 
    //Initializes the chain stdclass (object) with the genesis block
    public function __construct($platform) {
        $this->chain = [$this->createGenesisBlock()];
        $this->difficulty = 2;
        $this->platform = $platform;
    }

    //Generate genesis block
    private function createGenesisBlock() {
        //check if we have a genesis block from db and return
        //else, initialize chain
        return new Block(0, strtotime(date("Y-m-d h:m:s")), "Genesis Block");
    }

    //Get the last block from the chain
    public function getLastBlock() {
        //get last from db or caching mechanism (sphinx???)
        //for now
        return $this->chain[count($this->chain) - 1];
    }

    //Get entire chain
    public function getChain($height = null, $blocks = null) {
        //get chain from db or caching mechanism (sphinx???)
        //There should be limits to protect server load!!!
        if (!$blocks) {
            $blocks = 100;
        }
        if ($height) {
            $limit = $height.','.$blocks;
        } else {
            $limit = $blocks;
        }
        $sql = 'SELECT * FROM blocks LIMIT '.$limit;
        $chain = $this->platform->getdata($sql);
        $i = 0;
        foreach($chain as $block){
            $id = unpack('H*hex',$chain[0]['id']);
            $chain[$i]['id'] = $id['hex'];
            $gen =  unpack('H*hex',$chain[0]['generator']);
            $chain[$i]['generator'] = $gen['hex'];
            $sign = unpack('H*hex',$chain[0]['signature']);
            $chain[$i]['signature'] = $sign['hex'];
            $blockhash = unpack('H*hex',$chain[0]['blockhash']);
            $chain[$i]['blockhash'] = $blockhash['hex'];
            $i++;
        }
        
        //$sql = "SELECT * FROM blocks WHERE CAST(`blocks`.`id` AS BINARY) = X'".$varid['hex']."';";
        ///print_r($this->strd->getdata($sql));die;
        //print_r(unpack('H*hex',$chain[0]['varid']));
        
        
        return $chain;
    }

    //Push new block
    //Usage push(new Block(index,timestamp,data));
    public function push($block) {

        $block->previousHash = $this->getLastBlock()->hash;
        $this->mine($block);
        array_push($this->chain, $block);



        //mining should be segmentated into an indipendent process...
        /*
         * push function should only be executed when a block is found by mining process.
         * There should be a validator that checks the integrity of the push and score it 
         * for addition to db as final next block.
         * Rewards could run here as a chain reaction... block added, generate transaction 
         * in next block and issue rewards to associated miners.
         */
    }

    //Used above by push()
    public function mine($block) {
        //Sample difficulty algorithm
        //Keep generating new hash until has begins with 0000 
        //(How many zeroes depends on difficulty specified in constructor)
        while (substr($block->hash, 0, $this->difficulty) !== str_repeat("0", $this->difficulty)) {
            $block->nonce++;
            $block->hash = $block->calculateHash();
        }

        echo "Block mined: " . $block->hash . "\n";

        //mining should move to a separate process
        /*
         * mining process should get available tasks from chain and process accordingly
         * available tasks:
         * - calculate hash for new blocks
         * - record pending transactions in temporary block
         * - validate hashes submitted from other miners if found correct
         * - ???
         */
    }

    //Validate the chain hasn't been altered or malformed
    public function isValid() {
        //TO REVISE
        for ($i = 1; $i < count($this->chain); $i++) {
            $currentBlock = $this->chain[$i];
            $previousBlock = $this->chain[$i - 1];

            if ($currentBlock->hash != $currentBlock->calculateHash()) {
                return false;
            }

            if ($currentBlock->previousHash != $previousBlock->hash) {
                return false;
            }
        }





        return true;
    }

}

?>