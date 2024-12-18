<?php

// BPC version
define("VERSION", "1.3.0-beta");
// UTC timezone by default
date_default_timezone_set("UTC");

require_once __DIR__ . '/Exception.php';
require_once __DIR__ . '/functions.inc.php';
require_once __DIR__ . '/Blacklist.php';
require_once __DIR__ . '/InitialPeers.php';
require_once $platform->config->root_folder . '/library/classes/SBlock.php';
require_once $platform->config->root_folder . '/library/classes/SWallet.php';
require_once $platform->config->root_folder . '/library/classes/STx.php';

if ($platform->config->db_password == "ENTER-DB-PASS") {
    die("Please update your config file and set your db password");
}

// checks for php version and extensions
if (!extension_loaded("openssl") && !defined("OPENSSL_KEYTYPE_EC")) {
    api_err("Openssl php extension missing");
}
if (!extension_loaded("gmp")) {
    api_err("gmp php extension missing");
}
if (!extension_loaded('PDO')) {
    api_err("pdo php extension missing");
}
if (!extension_loaded("bcmath")) {
    api_err("bcmath php extension missing");
}
if (!defined("PASSWORD_ARGON2I")) {
    api_err("The php version is not compiled with argon2i support");
}

if (floatval(phpversion()) < 7.2) {
    api_err("The minimum php version required is 7.2");
}



// update the db schema, on every git pull or initial install
if (file_exists($platform->config->root_folder . "/tmp/dbupdate")) {

    //checking if the server has at least 2GB of ram
    $ram = file_get_contents("/proc/meminfo");
    $ramz = explode("MemTotal:", $ram);
    $ramb = explode("kB", $ramz[1]);
    $ram = intval(trim($ramb[0]));
    if ($ram < 1700000) {
        die("The node requires at least 2 GB of RAM");
    }
    $res = unlink("tmp/dbupdate");
    if ($res) {
        echo "Updating db schema! Please refresh!\n";
        require_once __DIR__ . '/schema.inc.php';
        exit;
    }
    echo "Could not access the tmp/dbupdate file. Please give full permissions to this file\n";
}
// Getting extra configs from the database
$query = $db->run("SELECT cfg, val FROM config");
if ($query) {
    foreach ($query as $res) {
        $platform->config->{$res['cfg']} = trim($res['val']);
    }
}


// nothing is allowed while in maintenance
if ($platform->config->maintenance == 1) {
    api_err("under-maintenance");
}



// something went wront with the db schema
if ($platform->config->dbversion < 2) {
    exit;
}

// separate blockchain for testnet
if ($platform->config->testnet == true) {
    $platform->config->coin .= "-testnet";
}

// current hostname
if (!isset($_SERVER['HTTP_HOST'])) {
    $hostname = 'localhost';
} else {
    $hostname = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . "://" . san_host($_SERVER['HTTP_HOST']);
}

// set the hostname to the current one
//print_r($_SERVER);
if (isset($_SERVER['HTTP_HOST']) && $hostname != $platform->config->hostname && $_SERVER['HTTP_HOST'] != "localhost" && $_SERVER['HTTP_HOST'] != "127.0.0.1" && $_SERVER['HTTP_HOST'] != '::1' && php_sapi_name() !== 'cli' && ($platform->config->allow_hostname_change != false || empty($platform->config->hostname))) {
    $db->run("UPDATE config SET val=:hostname WHERE cfg='hostname' LIMIT 1", [":hostname" => $hostname]);
    $platform->config->hostname = $hostname;
}
if (empty($platform->config->hostname) || $platform->config->hostname == "http://" || $platform->config->hostname == "https://") {
    api_err("Invalid hostname");
}

// run sanity
$t = time();
if ($t - $platform->config->sanity_last > $platform->config->sanity_interval && php_sapi_name() !== 'cli') {
    system("php sanity.php  > /dev/null 2>&1  &");
}
