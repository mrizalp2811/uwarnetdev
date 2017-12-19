<?php

namespace App\Controller\Portal;

use App\Controller\Portal\BasePortalController;
use App\Model\Owner;
use App\Model\Wallet;

class UserController extends BasePortalController
{
    function getRoutes() {
        $routes = array(
            'get' => array(
                '' => array('view', $this->auth($this->getApp()))
            ),
            'put' => array(
                '' => array('update', $this->auth($this->getApp())),
                '/warnet' => array('update_warnet', $this->auth($this->getApp()))
            )
        );
        return $routes;
    }

    public function view($app) {
        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();
        $wallets = Wallet::where('owner_id', '=', $owner->id)
            ->where('is_deleted', '=', 0)
            ->orderBy('created_at', 'desc')
            ->get();

        $app->render('portal/header.php', array(
            'name' => $_SESSION['name']
        ));
        $app->render('portal/profile.php', array(
            'owner' => $owner,
            'wallets' => $wallets
        ));
        $app->render('portal/footer.php');
    }

    public function update($app) {
        $old_password = $app->request->post('old_password');
        $password = $app->request->post('password');
        $confirm_password = $app->request->post('confirm_password');

        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();

        $bcrypt = new \Bcrypt\Bcrypt();

        if ($bcrypt->verify($old_password, $owner->password)) {
            if ($password && $confirm_password && $password == $confirm_password) {
                $owner->password = $bcrypt->hash($password);
                if ($owner->save()) {
                    $app->flash('success', 'Password diperbarui');
                } else {
                    $app->flash('error', 'Gagal memperbarui password');
                }
            } else {
                $app->flash('error', 'Password baru & konfirmasinya tidak sama');
            }
        } else {
            $app->flash('error', 'Kesalahan pada password lama');
        }
        $app->redirect($app->baseUrl().'/profile');
    }

    public function update_warnet($app) {
        $warnet_name = $app->request->post('warnet_name');
        $warnet_count = $app->request->post('warnet_count');
        $warnet_address = $app->request->post('warnet_address');
        $warnet_city = $app->request->post('warnet_city');

        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();

        $owner->warnet_name = $warnet_name;
        $owner->warnet_count = $warnet_count;
        $owner->warnet_address = $warnet_address;
        $owner->warnet_city = $warnet_city;
        if ($owner->save()) {
            $app->flash('success', 'Info warnet diperbarui');
        } else {
            $app->flash('error', 'Gagal memperbarui info warnet');
        }
        $app->redirect($app->baseUrl().'/profile');
    }
}