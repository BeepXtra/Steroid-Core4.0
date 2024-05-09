<?php

class LibController extends AbstractController {

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

                $data = $this->error('Please specify a resource to retrieve',1);
                break;
            case 2:
                $model = $this->getModel($request);
                $data = $this->error('The specified resource does not exist in this module',7);
                break;
            case 3:
                //TO DEVELOP
                $model = $this->getModel($request);
                $data = $this->error('The specified resource does not exist in this module',7);
                break;
            default :
                $data = NULL;
                break;
        }
        return $data;
    }

    /**
     * POST action.
     *
     * @param  $request
     * @return null
     */
    public function post($request) {
        $request = $this->error("This is a post request",1001);
        return $request;
    }

    public function put($request) {
        $request = $this->error("This is a put request",1002);
        return $request;
    }

    public function delete($request) {
        $request = $this->error("This is a delete request",1003);
        return $request;
    }

}