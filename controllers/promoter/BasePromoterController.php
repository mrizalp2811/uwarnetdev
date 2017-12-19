<?php

namespace App\Controller\Promoter;

use App\Controller\BaseController;
use App\Model\Promoter;

abstract class BasePromoterController extends BaseController
{
    protected function auth($app) {
        return function () use ($app) {
            if (!isset($_SESSION['promoter_login']) || !$_SESSION['promoter_login']) {
                $app->redirect($app->baseUrl().'/auth/login');
            }
        };
    }
}