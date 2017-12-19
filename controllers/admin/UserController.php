<?php

namespace App\Controller\Admin;

use App\Controller\Admin\BaseAdminController;
use App\Model\Admin;

class UserController extends BaseAdminController
{
    function getRoutes() {
        $routes = array(
            'get' => array(
                '' => array('view', $this->auth($this->getApp()))
            ),
            'put' => array(
                '' => array('update', $this->auth($this->getApp()))
            )
        );
        return $routes;
    }

    public function view($app) {
        $email = $_SESSION['admin_email'];
        $admin = Admin::whereEmail($email)->whereActive(1)->first();

        $app->render('admin/header.php', array(
            'name' => $_SESSION['admin_name']
        ));
        $app->render('admin/profile.php', array(
            'admin' => $admin
        ));
        $app->render('admin/footer.php');
    }

    public function update($app) {
        $old_password = $app->request->post('old_password');
        $password = $app->request->post('password');
        $confirm_password = $app->request->post('confirm_password');

        $email = $_SESSION['admin_email'];
        $admin = Admin::whereEmail($email)->whereActive(1)->first();

        $bcrypt = new \Bcrypt\Bcrypt();

        if ($bcrypt->verify($old_password, $admin->password)) {
            if ($password && $confirm_password && $password == $confirm_password) {
                $admin->password = $bcrypt->hash($password);
                if ($admin->save()) {
                    $app->flash('success', 'Password updated');
                } else {
                    $app->flash('error', 'Failed to save password');
                }
            } else {
                $app->flash('error', 'Password & confirm not match');
            }
        } else {
            $app->flash('error', 'Wrong password');
        }
        $app->redirect($app->baseUrl().'/profile');
    }
}