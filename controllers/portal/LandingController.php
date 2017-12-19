<?php

namespace App\Controller\Portal;

use App\Controller\BaseController;
use Slim\Http\Request;
use Slim\Http\Response;

class LandingController extends BaseController
{
    function getRoutes()
    {
        return array(
            'get' => array(
                '/' => array('landing')
            )
        );
    }

    public function landing($app) {
        $app->render('portal/landing.php');
    }
}