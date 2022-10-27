<?php

class PlatformController extends AbstractController {

    private $BeepCountry = null;
    private $BeepProduct = null;

    public function __construct() {
        parent::__construct();

        global $beep;
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

    /**
     * POST action.
     *
     * @param  $request
     * @return null
     */
    public function post($request) {
        //to develop
        return 'Post method not allowed';
    }

    public function put($request) {

        return 'Put method not allowed';
    }

    public function delete($request) {

        return 'Delete method not allowed';
    }

    public function head($request) {
        return (array) $request;
    }

    /**
     * Get User details.
     *
     * @return array
     */
    protected function beginRequest($request) {
        switch (count($request->url_elements)) {
            case 1:
                $data = $this->error('Please specify the model you wish to retrieve data from', 1);
                break;
            case 2:
                $model = $this->getModel($request);
                switch ($model) {
                    case 'test':
                        $data = $this->sapi->test($request->url_elements[2]);
                        break;
                    default:
                        //error
                        $data = $this->error('no model found for this request', 6);
                        break;
                }
                break;
            case 3:
                $model = $this->getModel($request);
                switch ($model) {
                    case 'test':
                        $data = $this->sapi->test($request->url_elements[2]);
                        break;
                    default:
                        //error
                        $data = $this->error('no model found for this request', 6);
                        break;
                }

                break;
            case 4:
                $model = $this->getModel($request);

                switch ($model) {

                    default:
                        $data = $this->error('no model found for this request', 6);
                        break;
                }
                break;
            default :
                $data_id = $request->url_elements[2];
                $data = NULL;
                break;
        }
        return $data;
    }

    protected function getModel($request) {
        $model = $request->url_elements[1];
        return $model;
    }

}
