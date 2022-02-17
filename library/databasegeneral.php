<?php

defined('_SECURED') or die('Restricted access');

//include_once "strdconfig().php";
class mysqlDatabase {

    public $connection;
    private $magic_quotes_active;
    private $real_escape_string_exists;
    public $last_query;

    function __construct() {
        global $strd;
        // $this->open_connection_ego();
        // $this->open_connection_directory();
        $this->magic_quotes_active = get_magic_quotes_gpc();
        $this->real_escape_string_exists = function_exists("mysqli_real_escape_string");
    }

    public function open_connection() {
        $config = new strdconfig();
        $this->connection = mysqli_connect($config->db_hostname, $config->db_username, $config->db_password, $config->strd_database);
        @mysqli_query($this->connection, 'set names "utf-8"');
        @mysqli_query($this->connection, 'set character set "utf8"');
        @mysqli_query($this->connection, 'set character_set_server="utf8"');
        @mysqli_query($this->connection, 'set collation_connection="utf8_general_ci"');
        if (!$this->connection) {
            die("Database connection failed: " . mysqli_error());
        }
    }

    public function close_connection() {
        if (isset($this->connection)) {
            mysqli_close($this->connection);
            unset($this->connection);
        }
    }

    public function query($sql) {
        $config = new strdconfig();
        $debug_queries = $config->debug_queries;
        $this->last_query = $sql;
        $result = mysqli_query($this->connection, $sql);
        if (!$debug_queries) {
            $this->confirm_query($result);
        } else {
            if (!$result) {
                //global $platform;
                //$lib = new Platform();

                $body = "Database query failed: " . mysqli_error($this->connection) . "<br/><br/>";
                $body .= "Last SQL query: " . $this->last_query;
                print_r($body);
                echo '<br/><br/>';
            }
        }
        return $result;
    }

    public function escape_value($value) {
        if ($this->real_escape_string_exists) { // PHP v4.3.0 or higher
            // undo any magic quote effects so mysql_real_escape_string can do the work
            if ($this->magic_quotes_active) {
                $value = stripslashes($value);
            }
            $value = mysqli_real_escape_string($value);
        } else { // before PHP v4.3.0
            // if magic quotes aren't already on then add slashes manually
            if (!$this->magic_quotes_active) {
                $value = addslashes($value);
            }
            // if magic quotes are active, then the slashes already exist
        }
        return $value;
    }

    function replaceValue($str) {
        $specialCharacters = array('#', '<', '$', '%', '&', '@', '.', '�', '+', '=', '�', '\\', '/', ">");
        $countArr = count($specialCharacters);
        for ($i = 0; $i < $countArr; $i++) {
            if (strchr("$str", $specialCharacters[$i])) {
                $str = str_replace('<', '*', $str);
            }
        }
        return $str;
    }

    function evalInput($str) {
        $specialCharacters = array('#', '<', '$', '%', '&', '@', '.', '�', '+', '=', '�', '\\', '/', ">");
        $countArr = count($specialCharacters);
        $t = false;
        for ($i = 0; $i < $countArr; $i++) {
            if (strchr("$str", $specialCharacters[$i])) {
                $t = true;
                break;
            }
        }
        return $t;
    }

    public function fetch_array($result_set) {
        $array_arr = Array();
        while ($row = mysqli_fetch_array($result_set, MYSQLI_NUM)) {
            $array_arr[] = $row;
        }
        return $array_arr;
    }

    public function fetch_row($result_set) {
        return $row = mysqli_fetch_array($result_set);
    }

    public function fetch_assoc($result_set) {
        //return 
        $assoc_arr = Array();
        while ($row = mysqli_fetch_assoc($result_set)) {
            $assoc_arr[] = $row;
        }
        return $assoc_arr;
    }

    public function fetch_assoc_row($result_set) {
        return $row = mysqli_fetch_assoc($result_set);
    }

    public function fetch_object($result_set) {
        $object_arr = Array();
        while ($row = mysqli_fetch_object($result_set)) {
            $object_arr[] = $row;
        }
        return $object_arr;
    }

    // return the numbers of rows affected by last  SELECT statement
    public function num_rows($result_set) {
        return mysqli_num_rows($result_set);
    }

    public function insert_id() {
        // get the last id inerted over the current db connection
        return mysqli_insert_id($this->connection);
    }

    // return the numbers of rows affected by last  INSERT, UPDATE or DELETE statement
    public function affected_rows() {
        return mysqli_affected_rows($this->connection);
    }

    private function confirm_query($result) {
        if (!$result) {
            //global $strd;
            //$lib = new strd;

            $body = "Database query failed: " . mysqli_error($this->connection) . "<br/><br/>";
            $body .= "Last SQL query: " . $this->last_query;
            $body .= "<pre>";
            ob_start();
            print_r($_SERVER);
            $body .= ob_get_contents();
            ob_end_clean();
            $body .= "</pre>";


            $from = 'error_log@strdxtra.com';
            $to = 'noc@exevior.com';
            $subject = 'error in query';

            $output = '<html><head>
                <title>- ooops! You found a Bug! -</title>
                <style>body {
                    background:#0000aa;
                    color:#ffffff;
                    font-family:courier;
                    font-size:12pt;
                    text-align:center;
                    margin:100px;
                }
                .neg {
                    background:#fff;
                    color:#0000aa;
                    padding:2px 8px;
                    font-weight:bold;
                }
                p {
                    margin:30px 100px;
                    text-align:left;
                }
                a,a:hover {
                    color:inherit;
                    font:inherit;
                }
                .menu {
                    text-align:center;
                    margin-top:50px;
                }</style></head><body>
                <span class="neg">Congratulations!!!</span>
                <p>You are one of the very few who have found a problem with our site<br/><br/>
                An alert has already been send to us <br/>
                and a highly trained team of rescue cats has been dispatched<br/>
                to resolve the issue.</p>
                <p>We apologise for the inconvenience<br/>
                As we said above, we have already been notified.<br/>
                If you wish to be notified when this is solved,<br/>
                or wish to provide further details to help us fix this faster<br/>
                you can click on the link at the bottom of this page and contact support.</p>
                <p>Please include the following in your request:<br/><br/>
                "<i>Hi<br/>
                I was just trying to...<br/>
                And then it said...<br/>
                and i am not really sure why but  am pretty sure that...<br/><br/>
                If this happens again i will... your...<br/><br/>
                Cheers</i>"<p><br/><br/><br/>
                <div class="menu"><a href="/en/contact-us">Contact Support</a> OR <a href="/">Go back to homepage</a></div>
                <br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/></body></html>';
            echo $output;
            echo '<hr/>';
            echo $body;
            //$lib->sendmail($from, $to, $subject, $body);
            die('Exiting');
        }
    }

    public function free_result($result) {
        mysqli_free_result($result);
    }

    public function next() {
        mysqli_next_result($this->connection);
    }

}

?>
