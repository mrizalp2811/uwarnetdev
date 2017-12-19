<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Model\Admin;

abstract class BaseAdminController extends BaseController
{
    protected function auth($app) {
        return function () use ($app) {
            if (!isset($_SESSION['admin_login']) || !$_SESSION['admin_login']) {
                $app->redirect($app->baseUrl().'/auth/login');
            }
        };
    }
}