<?php

class ResponseJson {

    /**
     * Response data.
     *
     * @var string
     */
    protected $data;
    protected $start;

    /**
     * Constructor.
     *
     * @param string $data
     */
    public function __construct($data) {
        global $request;
        $this->data = $data;
        if (isset($request->url_elements[0]) && $request->url_elements[0] != "platform") {
            if (isset($this->data[0])) {
                if (count($this->data) == 1) {
                    $this->data = $this->data[0];
                } else {
                    $this->data = $this->data;
                }
            }
        }

        return $this;
    }

    /**
     * Render the response as JSON.
     * 
     * @return string
     */
    public function render() {

        header('Content-Type: application/json');
        //Last check to remove single array being child to [0]
        if (isset($this->data[0])) {
            if (count($this->data) == 1) {
                $data = $this->data[0];
            } else {
                $data = $this->data;
            }
        } else {
            $data = $this->data;
        }
        //print_r($data);
        $finaldata = $this->buildFinalData($data);

        //print_r($finaldata);
        $response = json_encode($finaldata);

        return $response;
    }

    /*
     * @return array
     */
    public function buildFinalData($data) {
        global $request;

        $controller = $this->buildControllerData($request);
        $error = $this->errorHandler($this->data);
        $success = $this->successHandler($error);

        $finaldata = array();
        //Kept for backwards compatibility
        //$finaldata = $data;
        //Added for generic responses V2.0
        $finaldata['success'] = $success;
        $finaldata['request'] = $controller;
        $finaldata['error'] = $error;
        $finaldata['data'] = $this->data;
        //print_r($finaldata);
        return $finaldata;
    }

    /*
     * @return array
     */
    public function buildControllerData($request) {
        global $start;
        $controller = array();
        $controller['method'] = $request->method;
        if (isset($request->url_elements[0])) {
            $controller['controller'] = $request->url_elements[0];
        } else {
            $controller['controller'] = null;
        }

        if (isset($request->url_elements[1])) {
            $controller['resource'] = $request->url_elements[1];
        } else {
            $controller['resource'] = null;
        }
        if (key($request->parameters)) {
            $controller['parameters'] = key($request->parameters);
        } else {
            $controller['parameters'] = null;
        }

        $controller['url_elements'] = $request->url_elements;
        
        //Time taken to process in microseconds
        $controller['mileage'] = sprintf(microtime(true) - $start);
        return $controller;
    }

    /*
     * @return array
     */
    public function errorHandler($data) {
        //print_r($data);die;
        $error = array();
        if (isset($data['error'])) {
            //Backwards compatible error reporting
            if (isset($data['errorid'])) {
                $error['message'] = $data['errormsg'];
                $error['errorid'] = $data['errorid'];
            } else {
                $error['errorid'] = 1000;
                $error['message'] = $data['error'];
            }
        } else {
            $error['errorid'] = 0;
            $error['message'] = 0;
        }
        //print_r($error);

        return $error;
    }

    /*
     * @return array
     */
    public function successHandler($error) {
        //print_r($error);die;
        if ($error['errorid']) {
            $success = false;
        } else {
            $success = true;
        }
        return $success;
    }

}
