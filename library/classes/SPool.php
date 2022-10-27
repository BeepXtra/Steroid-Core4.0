<?php
defined('_SECURED') or die('Restricted access');
/**
 * Class for steroid platform
 *
 * @author exevior
 */
class SPool {
    
    public $nonce;
    public $timestamp;
    public $data;
    public $index;
    public $hash;
    public $previoushash;
    
    //Used when creating a new block
    function block($index, $timestamp, $data, $previoushash = null){
        $this->index = $index;
        $this->timestamp = $timestamp;
        $this->data = $data;
        $this->previousHash = $previoushash;
        $this->hash = $this->calculateHash();
        $this->nonce = 0;

    }
    
    /**
     * Hash encryption algorithm
     */
    public function calculateHash()
    {
        //Using sha256. Could be altered with any encryption algorithm
        //*tip For php the argon2 library is fast and efficient
        return hash("sha512", $this->index.$this->previousHash.$this->timestamp.((string)$this->data).$this->nonce);
    }
    
   
}
?>