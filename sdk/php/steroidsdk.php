<?php

//DO NOT EDIT BELOW

class SApiRequest
{
    private $ch;
    private $appid;
    private $appkey;
    public $format; // xml | json
    private $login;
    private $password;
    public $host;
    public $header;
    public $referer;
    public $cookie;
    private $apiurl;
    /**
     * Init curl session
     * 
     * $params = array('url' => '',
     *                    'host' => '',
     *                   'header' => '',
     *                   'method' => '',
     *                   'referer' => '',
     *                   'cookie' => '',
     *                   'post_fields' => '',
     *                    ['login' => '',]
     *                    ['password' => '',]      
     *                   'timeout' => 0
     *                   );
     */  
    
    function __construct()
	{
		global $appid, $appkey, $format, $login, $password, $host, $header, $referer, $cookie;
                $this->appid = $appid;
                $this->appkey = $appkey;
                $this->format = $format;
                $this->login = $login;
                $this->password = $password;
                $this->host = $host;
                $this->header = $header;
                $this->referer = $referer;
                $this->cookie = $cookie;
                $this->apiurl = 'https://galileo.steroid.io';
                
	}
    public function init($params)
    {
        $this->ch = curl_init();
        $user_agent = 'SAPI|1.0|'.$params['config']['appid'].'|'.$params['config']['key'].'|'.$params['config']['module'].'';
        if($params['header'] == 'application/json'){
            $header = array("Accept: application/json;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5",
            "Accept-Language: ru-ru,ru;q=0.7,en-us;q=0.5,en;q=0.3", "Accept-Charset: windows-1251,utf-8;q=0.7,*;q=0.7",
            "Keep-Alive: 300");
        } else {
            $header = array("Accept: text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5",
            "Accept-Language: ru-ru,ru;q=0.7,en-us;q=0.5,en;q=0.3", "Accept-Charset: windows-1251,utf-8;q=0.7,*;q=0.7",
            "Keep-Alive: 300");
        }
        if (isset($params['host']) && $params['host']){  
            $header[]="Host: ".$host; 
        }
        if (isset($params['header']) && $params['header']) {
            $header[]=$params['header'];
        }        
        @curl_setopt ( $this->ch , CURLOPT_RETURNTRANSFER , 1 );
        @curl_setopt ( $this->ch , CURLOPT_VERBOSE , 1 );
        @curl_setopt ( $this->ch , CURLOPT_HEADER , 1 );
        if ($params['method'] == "HEAD") {
            @curl_setopt($this -> ch,CURLOPT_NOBODY,1);
        }
        @curl_setopt ( $this->ch, CURLOPT_FOLLOWLOCATION, 1);
        @curl_setopt ( $this->ch , CURLOPT_HTTPHEADER, $header );
        if ($params['referer']) {
            @curl_setopt ($this -> ch , CURLOPT_REFERER, $params['referer'] );
        }
        @curl_setopt ( $this->ch , CURLOPT_USERAGENT, $user_agent);
        if ($params['cookie']) {
            @curl_setopt ($this -> ch , CURLOPT_COOKIE, $params['cookie']);
        }
        
        if ( $params['method'] == "post" )
        {
            curl_setopt( $this->ch, CURLOPT_POST, true );
            curl_setopt( $this->ch, CURLOPT_POSTFIELDS, $params['post_fields'] );
        }
        if ( $params['method'] == "get" ){
            //curl_setopt( $this->ch, CURLOPT_POST, true );
            curl_setopt( $this->ch, CURLOPT_POSTFIELDS, http_build_query($params['config']) );
            curl_setopt($this->ch, CURLOPT_POST, 0);
            curl_setopt($this->ch, CURLOPT_HTTPGET, 1);
        }
        if ( $params['method'] == 'put'){
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt( $this->ch, CURLOPT_POSTFIELDS, http_build_query($params['config']) );
        }
        if ( $params['method'] == 'delete'){
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt( $this->ch, CURLOPT_POSTFIELDS, http_build_query($params['config']) );
        }
        
        @curl_setopt( $this->ch, CURLOPT_URL, $params['url']);
        @curl_setopt ( $this->ch , CURLOPT_SSL_VERIFYPEER, 0 );
        @curl_setopt ( $this->ch , CURLOPT_SSL_VERIFYHOST, 0 );
        if (isset($params['login']) & isset($params['password']))
            @curl_setopt($this->ch , CURLOPT_USERPWD,$params['login'].':'.$params['password']);
        @curl_setopt ( $this->ch , CURLOPT_TIMEOUT, $params['timeout']);
    }
    
    /**
     * Make curl request
     *
     * @return array  'header','body','curl_error','http_code','last_url'
     */
    public function exec()
    {
        $response = curl_exec($this->ch);
        $error = curl_error($this->ch);
        $result = array( 'header' => '', 
                         'body' => '', 
                         'curl_error' => '', 
                         'http_code' => '',
                         'last_url' => '');
        if ( $error != "" )
        {
            $result['curl_error'] = $error;
            return $result;
        }
        
        $header_size = curl_getinfo($this->ch,CURLINFO_HEADER_SIZE);
        $result['header'] = substr($response, 0, $header_size);
        $result['body'] = substr( $response, $header_size );
        $result['http_code'] = curl_getinfo($this -> ch,CURLINFO_HTTP_CODE);
        $result['last_url'] = curl_getinfo($this -> ch,CURLINFO_EFFECTIVE_URL);
        return $result;
    }
    
    function request($method,$module,$resource = NULL){
        $apiurl = $this->apiurl;
        $request = $module.'/'.$resource;
        $request = urlencode($request);
                $config = array(
                    'appid' => urlencode($this->appid),
                    'key' => urlencode($this->appkey),
                    'method' => urlencode($method),
                    'request' => urlencode($request),
                    'module' => urlencode($module)
                );

                //url-ify the data for the POST
                $fields_string = NULL;
                foreach($config as $key=>$value) 
                    { $fields_string .= $key.'='.$value.'&'; }
                    rtrim($fields_string, '&');
        
        $params = array('url' => $apiurl.'/'.$request,
            'host' => $this->host,
            'header' => $this->header,
            'method' => $method,
            'referer' => $this->referer,
            'cookie' => $this->cookie,
            'post_fields' => $fields_string, // 'var1=value&var2=value
            'login' => $this->login,
            'password' => $this->password,
            'config' => $config,
            'timeout' => 5
            );
            $this->init($params);
            $result = $this->exec();
            if ($result['curl_error'])    $result['body'] = $result['curl_error'];
            if ($result['http_code']!='200')    $result['body'] = "HTTP Code = ".$result['http_code'];
            if (!$result['body'])        $result['body'] = "Your request is invalid or no results found";
            
            return $result['body'];
            //echo $e->getMessage();
    }
}
?>
