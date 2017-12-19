<?php

namespace App\Controller\Portal;

use App\Controller\Portal\BasePortalController;
use App\Model\Promo;
use Hashids\Hashids;

class PromotionController extends BasePortalController
{
    function getRoutes() {
        return array(
            'get' => array(
                '' => array('index')
            ),
        );
    }

    public function index($app) {
        $app->render('portal/promotion.php');
    }
}