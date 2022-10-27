<?php
class Response
{
    /**
     * Constructor.
     *
     * @param string $data
     * @param string $format
     */
    public static function create($data, $format)
    {
        //print_r($data);die;
        switch ($format) {
            case 'application/json':
                $obj = new ResponseJson($data);
                break;
            default:
                $obj = new ResponseJson($data);
            break;
        }
        return $obj;
    }
}