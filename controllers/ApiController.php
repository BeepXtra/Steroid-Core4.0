<?php

class ApiController extends AbstractController {

    protected function getRequest($request) {
        switch (count($request->url_elements)) {
            /*
             * case counts url elements after controller. e.g. /{api} case 1/case 2/case 3/case 4/case 5...
             */
            case 0:
                /*
                 * @url /api
                 */
                $data = $this->success('Basic API Information');
                break;
            case 1:
                $data = $this->getInfo();
                break;
            case 2:
                $req = $request->url_elements[1];
                switch ($req) {
                    case 'generate_wallet':
                        $data = $this->sapi->generate_account();
                        break;
                    case 'currentblock':
                        $data = $this->sapi->currentblock();
                        break;
                    case 'version':
                        $data = $this->sapi->version();
                        break;
                    case 'mempoolsize':
                        $data = $this->sapi->mempoolsize();
                        break;
                    case 'sanity':
                        $data = $this->sapi->sanity();
                        break;
                    case 'node-info':
                        $data = $this->sapi->nodeinfo();
                        break;
                    case 'masternodes':
                        $data = $this->sapi->masternodes();
                        break;
                    default:
                        $data = $this->error('Incomplete request. Please check documentation', 2);
                        break;
                }
                break;
            case 3:
                $req = $request->url_elements[1];
                switch ($req) {
                    case 'getbalance':
                        $data = $this->sapi->getbalance($request->url_elements[2]);
                        break;
                    case 'getpendingbalance':
                        $data = $this->sapi->getpendingbalance($request->url_elements[2]);
                        break;
                    case 'gettransactions':
                        $data = $this->sapi->gettransactions($request->url_elements[2]);
                        break;
                    case 'gettransaction':
                        $data = $this->sapi->gettransaction($request->url_elements[2]);
                        break;
                    case 'getpublickey':
                        $data = $this->sapi->getpublickey($request->url_elements[2]);
                        break;
                    case 'getalias':
                        $data = $this->sapi->getalias($request->url_elements[2]);
                        break;
                    case 'getaddress':
                        (strlen($request->url_elements[2]) < 32) ? $data = $this->error("Invalid public key") : $data = $this->sapi->getaddress($request->url_elements[2]);
                        break;
                    case 'base58':
                        $data = $this->sapi->base58($request->url_elements[2]);
                        break;
                    case 'getblock':
                        $data = $this->sapi->getblock($request->url_elements[2]);
                        break;
                    case 'getblocktransactions':
                        $data = $this->sapi->getblocktransactions($request->url_elements[2]);
                        break;
                    case 'send':
                        $data = $this->sapi->send($request->url_elements[2]);
                        break;
                    case 'propwallet':
                        $currentblock = $this->sapi->sblock->current();
                        $this->sapi->swallet->add($request->url_elements[2], $currentblock['id']);
                        $data = $this->success('Storing wallet attempted - check to verify');
                        break;
                    case 'checkaddress':
                        $data = $this->sapi->checkaddress($request->url_elements[2]);
                        break;
                    case 'assetbalance':
                        $data = $this->sapi->assetbalance($request->url_elements[2]);
                        break;
                    case 'getblocktransactions':
                        $data = $this->sapi->getblocktransactions($request->url_elements[2]);
                        break;
                    default:
                        $data = $this->error('Incomplete request. Please check documentation', 2);
                        break;
                }
                break;
            case 4:
                if ($request->url_elements[1] == 'checkaddress') {
                    $data = $this->sapi->checkaddress($request->url_elements[2], $request->url_elements[3]);
                } else {
                    $data = $this->error('Incomplete request. Please check documentation', 2);
                }
                break;
            case 5:
                if ($request->url_elements[1] == 'checksignature') {
                    /*
                     * @url /api/checksignature/$public_key/$signature/$data
                     */
                    $data = $this->sapi->checksignature($request);
                } else {
                    $data = $this->error('Incomplete request. Please check documentation', 2);
                }
                break;
            default :
                $data = $this->error('Invalid request. Please check documentation', 3);
                break;
        }
        if ($data) {
            return $data;
        } else {
            return $this->error('Something went wrong with your request', 5);
        }
    }

    protected function postRequest($request) {
        //print_r($request->url_elements);
        //echo urldecode($request->url_elements[4]);
        switch (count($request->url_elements)) {
            case 1:
                $data = $this->error('Please specify a resource to post', 1);
                break;
            case 2:
                if ($request->url_elements[1] == 'test') {
                    $data = $this->sapi->test();
                } else {
                    $data = $this->error('Incomplete request. Please check documentation', 2);
                }
                break;
            case 3:
                if ($request->url_elements[1] == 'send') {
                    $data = $this->sapi->sendtx($request->url_elements[2]);
                }
                break;
            default :
                $data = $this->error('Invalid request. Please check documentation', 3);
                break;
        }
        if ($data) {
            return $data;
        } else {
            return $this->error('Something went wrong with your request', 5);
        }
    }

    protected function putRequest($request) {
        switch (count($request->url_elements)) {
            case 1:
                $data = $this->error('Please specify a resource to post', 1);
                break;
            case 2:
                $data = $this->error('Incomplete request. Please check documentation', 2);
                break;
            default :
                $data = $this->error('Invalid request. Please check documentation', 3);
                break;
        }
        if ($data) {
            return $data;
        } else {
            return $this->error('Something went wrong with your request', 5);
        }
    }

    protected function deleteRequest($request) {
        switch (count($request->url_elements)) {
            case 1:
                $data = $this->error('Please specify a resource to post', 1);
                break;
            case 2:
                $data = $this->error('Incomplete request. Please check documentation', 2);
                break;
            default :
                $data = $this->error('Invalid request. Please check documentation', 3);
                break;
        }
        if ($data) {
            return $data;
        } else {
            return $this->error('Something went wrong with your request', 5);
        }
    }

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

    protected function getInfo() {
        $data = array();
        $data['info'] = 'Basic API Information';
        $data['version'] = '1.0.1b';
        return $data;
    }

}
