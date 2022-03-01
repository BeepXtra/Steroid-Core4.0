<?php

class strdconfig {

    //Database configuration
    public $db_hostname = 'localhost';
    public $db_username = 'testnet';
    public $db_password = '1QHUGrl7mCQ33uSg';
    public $strd_database = 'testnet';
    public $debug = 1;
    //The time a session should be left alive (In seconds)
    //This is for security reasons. Users will be automatically logged out after the specified seconds of inactivity
    public $session_timeout = '10800';
    //Time settings
    public $timezone = 'UTC';

    function __construct() {
        $servername = explode('.', $_SERVER['HTTP_HOST']);
        $this->debug_queries = $this->debug;
    }

    function getTld() {
        //print_r($_SERVER);
        $tld = strrchr($_SERVER['HTTP_HOST'], ".");
        $tld = substr($tld, 1);
        return $tld;
    }

}

?>
