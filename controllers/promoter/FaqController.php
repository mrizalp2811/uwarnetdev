<?php

namespace App\Controller\Promoter;

use App\Controller\Promoter\BasePromoterController;

class FaqController extends BasePromoterController
{
    function getRoutes() {
        return array(
            'get' => array(
                '' => array('view'),
            ),
        );
    }

    public function view($app) {
        $app->render('promoter/faq.php');
    }
}