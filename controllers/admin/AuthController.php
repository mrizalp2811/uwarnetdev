<?php

namespace App\Controller\Admin;

use Gregwar\Captcha\CaptchaBuilder;
use App\Controller\Admin\BaseAdminController;
use App\Model\Admin;

class AuthController extends BaseAdminController
{
    function getRoutes() {
        $routes = array(
            'get' => array(
                '/login' => array('login'),
                '/logout' => array('logout')
            ),
            'post' => array(
                '/login' => array('do_login')
            )
        );
        return $routes;
    }

    public function login($app) {
        $params = array();

        if (isset($_SESSION['slim.flash']['captcha']) && $_SESSION['slim.flash']['captcha'] == 'ask') {
            $builder = new CaptchaBuilder;
            $builder->build();

            $app->flash('captcha_code', $builder->getPhrase());

            $params['builder'] = $builder;
        }

        $app->render('admin/login.php', $params);
    }

    public function do_login($app) {
        $ip_address = $app->request->getIp();
        $user_agent = $app->request->getUserAgent();
        $bfb_status = \App\BruteForceBlock::getLoginStatus('admin', $ip_address);

        if ($bfb_status['status'] == 'safe') {
            $email = $app->request->post('email');
            $password = $app->request->post('password');

            $admin = Admin::whereEmail($email)->whereActive(1)->first();
            if ($admin) {
                $bcrypt = new \Bcrypt\Bcrypt();

                if ($bcrypt->verify($password, $admin->password)) {
                    $_SESSION['admin_id'] = $admin->id;
                    $_SESSION['admin_email'] = $email;
                    $_SESSION['admin_name'] = $admin->name;
                    $_SESSION['admin_login'] = true;

                    $app->redirect($app->baseUrl().'/owner');
                } else {
                    \App\BruteForceBlock::addFailedAttempt('admin', $email, $ip_address, $user_agent);
                }
            } else {
                \App\BruteForceBlock::addFailedAttempt('admin', $email, $ip_address, $user_agent);
            }
            $app->flash('error', 'Wrong email or password');
            $app->redirect($app->baseUrl().'/auth/login');
        } else if ($bfb_status['status'] == 'error') {
            $app->flash('error', 'Unknown error');
            $app->redirect($app->baseUrl().'/auth/login');
        } else if ($bfb_status['status'] == 'delay') {
            $app->flash('delay', $bfb_status['message']);
            $app->redirect($app->baseUrl().'/auth/login');
        } else if ($bfb_status['status'] == 'captcha') {
            if (isset($_SESSION['slim.flash']['captcha_code'])) {
                if ($_SESSION['slim.flash']['captcha_code'] != $app->request->post('captcha')) {
                    $app->flash('error', 'Wrong code');
                    $app->flash('captcha', 'ask');
                    $app->redirect($app->baseUrl().'/auth/login');
                }
            }

            $email = $app->request->post('email');
            $password = $app->request->post('password');

            $admin = Admin::whereEmail($email)->whereActive(1)->first();
            if ($admin) {
                $bcrypt = new \Bcrypt\Bcrypt();

                if ($bcrypt->verify($password, $admin->password)) {
                    $_SESSION['admin_id'] = $admin->id;
                    $_SESSION['admin_email'] = $email;
                    $_SESSION['admin_name'] = $admin->name;
                    $_SESSION['admin_login'] = true;

                    $app->redirect($app->baseUrl().'/owner');
                } else {
                    \App\BruteForceBlock::addFailedAttempt('admin', $email, $ip_address, $user_agent);
                }
            } else {
                \App\BruteForceBlock::addFailedAttempt('admin', $email, $ip_address, $user_agent);
            }

            $app->flash('captcha', 'ask');
            $app->redirect($app->baseUrl().'/auth/login');
        }
    }

    public function logout($app) {
        session_destroy();
        $app->redirect($app->baseUrl().'/auth/login');
    }
}