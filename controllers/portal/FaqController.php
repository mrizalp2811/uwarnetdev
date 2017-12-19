<?php

namespace App\Controller\Portal;

use App\Controller\Portal\BasePortalController;

class FaqController extends BasePortalController
{
    function getRoutes() {
        $routes = array(
            'get' => array(
                '' => array('view'),
            ),
        );
        return $routes;
    }

    public function view($app) {
        $app->render('portal/faq.php');
    }
}