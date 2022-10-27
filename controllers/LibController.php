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
                if ($model == 'api') {
                    $data = $this->getApiModules();
                } elseif($model == 'apicalls'){
                    $data = $this->getApiCalls();
                } else {
                    $data = $this->error('The specified resource does not exist in this module',7);
                }
                break;
            case 3:
                //TO DEVELOP
                $model = $this->getModel($request);
                print_r($model);
                switch ($model) {
                    case 'comemodel':
                        $data= null;
                        break;
                    default:
                        //error
                        $data = $this->error('no model found for this request',6);
                        break;
                }
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

    protected function getApiModules() {
        global $currentapp;
        $query = "SELECT c. * , a.module, a.params, a.description
            FROM api_module_permissions AS c
            LEFT JOIN api_apps AS b ON b.id = c.app_id
            LEFT JOIN api_modules AS a ON a.id = c.module_id
            WHERE b.app_id =  '{$currentapp[2]}'";
        $result = $this->beep->db->query($query);
        $result = $this->beep->db->fetch_assoc($result);
        return $result;
    }
    
    protected function getApiCalls() {
        global $currentapp;
        $query = "SELECT b.module, a.method, a.params, a.name, a.description
FROM api_module_calls AS a
LEFT JOIN api_modules AS b ON a.module_id = b.id
ORDER BY b.module ASC,a.id ASC";
        
        $result = $this->beep->db->query($query);
        $result = $this->beep->db->fetch_assoc($result);
        return $result;
    }
}