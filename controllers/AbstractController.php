<?php

/**
 * @package  api-framework REST on NginX by Angel Exevior
 */
abstract class AbstractController {

    public $platform;
    public $request;

    function __construct() {
        //Nothing here
        global $platform, $request;
        
        //$this->$platform = $platform;
        $this->request = $request;
        
        if (!class_exists("SApi"))
            include_once("library/classes/SApi.php");
        $this->sapi = new SApi($platform);

        
    }

    public function error($error,$id = null) {
        if($id){
            $array = array('0' => array('error' => $error, 'errorid' => $id, 'errormsg' => $error ));
        } else {
            $array = array('0' => array('error' => $error));
        }
        
        return $array;
    }

    public function response($data) {
        $array = array('0' => array('response' => $data));
        return $array;
    }

    public function success($data) {
        $array = array('0' => array('success' => $data));
        //print_r($array);die;
        return $array;
    }

    public function authorize($app, $platform) {
        /*
         * Get the application and it's permissions for authentication
         */
        //print_r($this->request);
        
        if (count($app) > 1 && $app == 'disabled') {
            $query = "SELECT a.* ,b.id as module_id, b.module, c.get,c.post,c.put,c.delete,c.other
                    FROM api_apps as a
                    LEFT JOIN api_modules as b
                    ON b.module = '" . $app[4] . "'
                    LEFT JOIN api_module_permissions as c
                    ON a.id = c.app_id
                    AND c.module_id = b.id
                    WHERE a.app_id = '" . $app[2] . "'";
            //echo $query;
            $application = $platform->getdata($query);
            //print_r($application);
            /**
             * Validate app_key with app_id 
             */
            if ($application[0]['key'] !== $app[3]) {
                echo 'Application key does not match';
                die;
            }

            /**
             * Validate app has permissions to access data 
             */
            if ($application[0][strtolower($_SERVER['REQUEST_METHOD'])] !== '1') {
                echo 'Application is not allowed to perform this request using method "' . $_SERVER['REQUEST_METHOD'] . '"';
                die;
            }

            return true;
        } else {
            //Rule to exclude open api calls
        if($this->request->url_elements[0] == 'platform' || $this->request->url_elements[0] == 'api'){
                return true;
            } else {
            // return $this->error('This request cannot be performed<br/>Please check your App Credentials and try again', 1);
                echo 'This request cannot be performed<br/>Please check your App Credentials and try again';
            die;
            }
        }
        /**
         * If all validation above pass the test
         * continue processing 
         */
    }

    /*
     * Common Funtions
     */

    protected function buildOrderQuery($order) {
        switch ($order[1]) {
            case 'default':
                $ordering = "ORDER BY id {$order[2]}";
                break;
            default:
                $ordering = "ORDER BY {$order[1]} {$order[2]}";
                break;
        }
        return $ordering;
    }

    protected function buildLimitQuery($limit) {
        if (is_numeric($limit[1]) && is_numeric($limit[2])) {
            $pagination = "LIMIT {$limit[1]},{$limit[2]}";
        } else {
            $pagination = '';
        }
        return $pagination;
    }

    protected function getFunction($request, $i) {
        $split = explode('-', $request->url_elements[$i]);
        $function = $split[0];
        return $function;
    }

    protected function getModel($request) {
        $model = $request->url_elements[1];
        return $model;
    }

    protected function getFunctionParams($request, $i) {
        $split = explode('-', $request->url_elements[$i]);
        return $split;
    }

    protected function generate_seo_link($input, $replace = '-', $remove_words = true) {
        $words_array = array('a', 'and', 'the', 'an', 'it', 'is', 'with', 'can', 'of', 'why', 'not');
        //make it lowercase, remove punctuation, remove multiple/leading/ending spaces
        $return = trim(preg_replace('/[^a-zA-Z0-9\s]/', '', strtolower($input)));

        //remove words, if not helpful to seo
        //i like my defaults list in remove_words(), so I wont pass that array
        if ($remove_words) {
            $return = $this->remove_words($return, $replace, $words_array);
        }

        //convert the spaces to whatever the user wants
        //usually a dash or underscore..
        //...then return the value.
        return str_replace(' ', $replace, $return);
    }

    protected function remove_words($input, $replace, $words_array = array(), $unique_words = true) {
        //separate all words based on spaces
        $input_array = explode(' ', $input);

        //create the return array
        $return = array();

        //loops through words, remove bad words, keep good ones
        foreach ($input_array as $word) {
            //if it's a word we should add...
            if (!in_array($word, $words_array) && ($unique_words ? !in_array($word, $return) : true)) {
                $return[] = $word;
            }
        }

        //return good words separated by dashes
        return implode($replace, $return);
    }

}
