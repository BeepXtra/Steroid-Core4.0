<?php

class BeepController extends AbstractController {

    /**
     * GET method.
     * 
     * @param  Request $request
     * @return string
     */
    public function get($request) {
        $data = $this->beginRequest($request);
        return $data;
    }

    public function post($request) {
        $data = $this->beginRequest($request, 'post');
        return $data;
    }

    public function put($request) {
        $data = $this->beginRequest($request, 'put');
        return $data;
    }

    public function delete($request) {
        $data = $this->beginRequest($request, 'delete');
        return $data;
    }

    protected function beginRequest($request, $method = null) {
        if (!$method || $method == 'get') {
            $data = $this->getRequest($request);
        } elseif ($method == 'post') {
            $data = $this->postRequest($request);
        } elseif ($method == 'put') {
            $data = $this->putRequest($request);
        } elseif ($method == 'delete') {
            $data = $this->deleteRequest($request);
        }
        return $data;
    }
    
    protected function getRequest($request) {
        switch (count($request->url_elements)) {
            case 1:
                $data = $this->error('Incomplete request. Please check documentation',2);
                break;
            case 2:
                $data = $this->error('Incomplete request. Please check documentation',2);
                break;
            case 3:
                $data = $this->error('Incomplete request. Please check documentation',2);
                break;
            case 4:
                $data = $this->error('Incomplete request. Please check documentation',2);
                break;
            default :
                $data = $this->error('Invalid request. Please check documentation',3);
                break;
        }
        if ($data) {
            return $data;
        } else {
            return $this->error('Something went wrong with your request',5);
        }
    }
    
    protected function postRequest($request) {
        //print_r($request->url_elements);
        //echo urldecode($request->url_elements[4]);
                switch (count($request->url_elements)) {
            case 1:
                $data = $this->error('Please specify a resource to post',1);
                break;
            case 2:
                $data = $this->error('Incomplete request. Please check documentation',2);
                break;
            case 3:
                $data = $this->error('Incomplete request. Please check documentation',2);
                break;
            case 4:
                $data = $this->error('Incomplete request. Please check documentation',2);
                break;
            case 5:
                $data = $this->error('Incomplete request. Please check documentation',2);
                break;
            default :
                $data = $this->error('Invalid request. Please check documentation',3);
                break;
        }
        if ($data) {
            return $data;
        } else {
            return $this->error('Something went wrong with your request',5);
        }
    }
    
    protected function putRequest($request) {
                switch (count($request->url_elements)) {
            case 1:
                $data = $this->error('Please specify a resource to post',1);
                break;
            case 2:
                $data = $this->error('Incomplete request. Please check documentation',2);
                break;
            case 3:
                $data = $this->error('Incomplete request. Please check documentation',2);
                break;
            case 4:
                $data = $this->error('Incomplete request. Please check documentation',2);
                break;
            default :
                $data = $this->error('Invalid request. Please check documentation',3);
                break;
        }
        if ($data) {
            return $data;
        } else {
            return $this->error('Something went wrong with your request',5);
        }
    }
    
    protected function deleteRequest($request) {
                switch (count($request->url_elements)) {
            case 1:
                $data = $this->error('Please specify a resource to post',1);
                break;
            case 2:
                $data = $this->error('Incomplete request. Please check documentation',2);
                break;
            case 3:
                $data = $this->error('Incomplete request. Please check documentation',2);
                break;
            case 4:
                $data = $this->error('Incomplete request. Please check documentation',2);
                break;
            default :
                $data = $this->error('Invalid request. Please check documentation',3);
                break;
        }
        if ($data) {
            return $data;
        } else {
            return $this->error('Something went wrong with your request',5);
        }
    }

}