<?php

namespace App\Controller\Api;


use App\SlimC;
use App\Controller\BaseController;
use Slim\Http\Request;
use Slim\Http\Response;

class TestController extends BaseController
{
    function getRoutes()
    {
        $routes = array(
            'get'=> array(
                '/testing',
                '/auth'
            )
        );
        return $routes;
    }

    public function testing (SlimC $app){
        $request = $app->request;
        $response = $app->response;
        echo "testing oke";
    }
}