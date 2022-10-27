<?php     
// Copyright (c) 2018-2021 BeepXtra
// Copyright (c) 2023+ The Bitcoin Core developers
// Distributed under the MIT software license, see the accompanying
// file COPYING or http://www.opensource.org/licenses/mit-license.php.

// Dev performance testing
$start = microtime(true);
/**
 * As always
 * Security first 
 */
define('_SECURED', 1);
/*
 * ---------------------------------------------------------------
 * APPLICATION ENVIRONMENT
 * ---------------------------------------------------------------
 *
 * You can load different configurations depending on your
 * current environment. Setting the environment also influences
 * things like logging and error reporting.
 *
 * This can be set to anything, but default usage is:
 *
 *     development
 *     testing
 *     production
 *
 * NOTE: If you change these, also change the error_reporting() code below
 *
 */
define('ENVIRONMENT', 'development');
/*
 * ---------------------------------------------------------------
 * ERROR REPORTING
 * ---------------------------------------------------------------
 *
 * Different environments will require different levels of error reporting.
 * By default development will show errors but testing and live will hide them.
 */
if (defined('ENVIRONMENT')) {
    switch (ENVIRONMENT) {
        case 'development':
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);
            
            break;

        case 'testing':
            break;
        case 'production':
            error_reporting(0);
            break;

        default:
            exit('The application environment is not set correctly.');
    }
}

/**
 * Load the Core Library
 */
include('library/classes/SCore.php');
$platform = new SCore();
//parse configuration
$_config = $platform->config;
//initialize database library
$db = $platform->db;

/*
 * Load generic functions
 */
require_once __DIR__.'/library/includes/init.inc.php';

/*
 * Initiate Block session
 */
if (!class_exists("SBlock")) {
            include_once("library/classes/SBlock.php");
        }
$block = new SBlock();
//$block = new Block();
$current = $block->current();

//To develop further for private/controlled api access
$currentapp = explode('|',$_SERVER['HTTP_USER_AGENT']); // 0=>BeepAPI, 1=>Version, 2=>appid, 3=>appkey, 4=>module

/**
 * Common class autoloader.
 * 
 * @param string $class_name <- to automatically load the controller specified
 * Developed by Exevior for using MVC in SApi and other node controllers
 */
function autoload_class($class_name) {
    $directories = array(
        'controllers/',
        'models/'
    );
    foreach ($directories as $directory) {
        $filename = $directory . $class_name . '.php';
        if (is_file($filename)) {
            require($filename);
            break;
        }
    }
}

/**
 * Register autoloader functions.
 */
spl_autoload_register('autoload_class');

/**
 * Parse the incoming request.
 */
$request = new Request();

$flag = false;

$request->method = strtoupper($_SERVER['REQUEST_METHOD']);

//To Do
/*
 * Develop each request method for further security, utilities & functionality
 * GET POST PUT DELETE HEAD
 */
switch ($request->method) {
    case 'POST2':
        $request->parameters = $_GET;//POST;
        $data=$_POST;
        if(!empty($request->parameters)){
            $request->url_elements = explode('/', trim(urldecode($_SERVER['REQUEST_URI']), '/'));
            $flag = true; 
        }
    break;
    case 'PUT2':
        //$request->parameters = $_POST;
        parse_str(file_get_contents('php://input'), $request->parameters);
        if(!empty($request->parameters)){
            $request->url_elements = explode('/', trim(urldecode($_SERVER['REQUEST_URI']), '/'));
            $flag = true; 
        }
    break;
    case 'DELETE2':
        parse_str(file_get_contents('php://input'), $request->parameters);
        if(!empty($request->parameters)){
            $request->url_elements = explode('/', trim(urldecode($_SERVER['REQUEST_URI']), '/'));
            $flag = true; 
        }
    break;
    default:
        parse_str(file_get_contents('php://input'), $request->parameters);
        $request->parameters = $_GET;
        if(!empty($request->parameters)){
            $request->url_elements = explode('/', trim(urldecode($_SERVER['REQUEST_URI']), '/'));
            $flag = true; 
        }
    break;
}

/**
 * Route the request.
 */
//Load the controller) {
if ($flag) {
    $controller_name = ucfirst($request->url_elements[0]) . 'Controller';
    if (class_exists($controller_name)) {
        $controller = new $controller_name;
        $action_name = strtolower($request->method);
        //echo $action_name; exit(1);
        $response_str = call_user_func_array(array($controller, $action_name), array($request));
        $controller->authorize($currentapp,$platform);
    }
    else {
        header('HTTP/1.1 404 Not Found');
       // $response_str = 'Unknown request: ' . $request->url_elements[0];
        $response_str = array('error' => 'Unknown request');
    } 
}
else {
    $response_str = array('error' => 'Unknown request');
}

/**
 * Send the response to the client.
 */

if(!isset($_SERVER['HTTP_ACCEPT'])){
    $accept = "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8";
} else {
    $accept = $_SERVER['HTTP_ACCEPT'];
}
$response_obj = Response::create($response_str, $accept);
$response = $response_obj->render();
echo $response;

/*
 * Performance testing end of run
 */
$finish = microtime(true);
if (!isset($_GET['request'])) {
    //echo '<!--<div>' . sprintf($finish - $start) . '</div>-->';
}