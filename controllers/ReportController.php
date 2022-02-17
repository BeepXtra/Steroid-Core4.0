<?php

class ReportController extends AbstractController {

    private $BeepUsers = null;

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

    protected function beginRequest($request) {
        switch (count($request->url_elements)) {
            case 1:
                $data = $this->error('Please specify a resource to retrieve', 1);
                break;
            case 2:
                $model = $this->getModel($request);
                if ($model == 'users') {
                    $data = $this->getUsersReport($request);
                } else {
                    $data = $this->error('The specified resource does not exist in this module', 7);
                }
                break;
            case 3:
                //TO DEVELOP
                $model = $this->getModel($request);
                switch ($model) {
                    case 'users':
                        $data = $this->error('The specified resource does not exist in this module', 7);
                        break;
                    default:
                        //error
                        $data = $this->error('no model found for this request', 6);
                        break;
                }
                break;
            default :
                $data = NULL;
                break;
        }
        return $data;
    }

    protected function getModel($request) {
        $model = $request->url_elements[1];
        return $model;
    }

    /**
     * POST action.
     *
     * @param  $request
     * @return null
     */
    public function post($request) {
        //to develop
        return $request;
    }

    public function put($request) {

        return $request;
    }

    public function delete($request) {

        return 'Delete method not allowed';
    }

}
