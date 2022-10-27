<?php
class Translator{
    private $language = 'en';
    private $lang = array();
    private $path="languages/";
    private $tabletpath="../languages/";

    public function __construct($language){
       $this->language = $language;
       
    }
    private function findString($str) {
        if (array_key_exists($str, $this->lang[$this->language])) {
            return $this->lang[$this->language][$str];
            //return;
        }
        return $str;
    }    
    private function splitStrings($str) {
        return explode('=',trim($str));
    }
    public function __($str) {
            $file=$this->path.$this->language.'.txt';
            $filetablet=$this->tabletpath.$this->language.'.txt';
        if (!array_key_exists($this->language, $this->lang)) {
            if (file_exists($file)) {
                $strings = array_map(array($this,'splitStrings'),file($file));
                foreach ($strings as $k => $v) {
                    $this->lang[$this->language][$v[0]] = $v[1];
                }
                return $this->findString($str);
            }
            else if (file_exists($filetablet)) {
                $strings = array_map(array($this,'splitStrings'),file($filetablet));
                foreach ($strings as $k => $v) {
                    $this->lang[$this->language][$v[0]] = $v[1];
                }
                return $this->findString($str);
            }
            else {
                return $str;
            }
        }
        else {
            return $this->findString($str);
        }
    }
    
    public function changelang($lang){
       $this->language = $lang; 
    }
}
?>