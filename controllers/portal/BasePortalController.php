<?php

namespace App\Controller\Portal;

use App\Controller\BaseController;
use App\Model\Owner;

abstract class BasePortalController extends BaseController
{
    protected function auth($app) {
        return function () use ($app) {
            if (!isset($_SESSION['login']) || !$_SESSION['login']) {
                // $app->redirect($app->baseUrl().'/auth/login');
                $app->redirect($app->baseUrl().'/landing');
            } else {
                $email = $_SESSION['email'];
                $owner = Owner::whereEmail($email)->whereActive(1)->first();
                if (!$owner) {
                    // $app->redirect($app->baseUrl().'/auth/login');
                    $app->redirect($app->baseUrl().'/landing');
                }
            }
        };
    }
}