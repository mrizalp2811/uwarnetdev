<?php

namespace App\Controller\Api;

use App\Model\Operator;
use App\SlimC;
use Slim\Http\Request;
use Slim\Http\Response;

class BcryptController extends BaseApiController
{
    function getRoutes()
    {
        return array("get"=>array(
            "/:pwd"=>array(
                "index",
                function(){

                },

        )));
    }

    function index (SlimC $app, $pwd){
        $request = $app->request;
        $response = $app->response;
        $response->headers->set('Content-type', 'application/json');
        $result = new \stdClass();
        $result->status = true;
        $result->pwd = $pwd;

        $bcrypt = new \Bcrypt\Bcrypt();
        $operator = Operator::where('username', '=', 'kanaqsasak')->first();
        if ($operator) {
            $result->operator = $operator->toArray();
        }

        $result->crypt = $bcrypt->hash($pwd);

        $valid = $bcrypt->verify($pwd, $result->crypt);
        $result->valid = $valid;
        echo json_encode($result);
    }


}