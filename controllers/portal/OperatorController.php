<?php

namespace App\Controller\Portal;

use App\Controller\Portal\BasePortalController;
use App\Model\Owner;
use App\Model\Warnet;
use App\Model\Operator;

class OperatorController extends BasePortalController
{
    function getRoutes() {
        $routes = array(
            'get' => array(
                '' => array('view', $this->auth($this->getApp()))
            ),
            'post' => array(
                '' => array('create', $this->auth($this->getApp())),
                '/activate' => array('activate', $this->auth($this->getApp())),
                '/deactivate' => array('deactivate', $this->auth($this->getApp()))
            ),
            'put' => array(
                '' => array('update', $this->auth($this->getApp()))
            ),
            'delete' => array(
                '' => array('destroy', $this->auth($this->getApp()))
            )
        );
        return $routes;
    }

    public function view($app) {
        $warnets = array();
        $operators = array();

        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();
        if ($owner) {
            $warnets = Warnet::where('owner_id', '=', $owner->id)
                ->where('is_deleted', '=', 0)
                ->orderBy('created_at', 'desc')
                ->get();

            $operators = Operator::where('owner_id', '=', $owner->id)
                ->where('is_deleted', '=', 0)
                ->orderBy('created_at', 'desc')
                ->get();
        }
        $app->render('portal/header.php', array(
            'name' => $_SESSION['name']
        ));
        $app->render('portal/operator.php', array(
            'warnets' => $warnets,
            'operators' => $operators,
            'assets' => array(
                'js' => array(
                    'portal-operator.js?v=2'
                )
            )
        ));
        $app->render('portal/footer.php');
    }

    public function create($app) {
        $warnet_id = $app->request->post('warnet_id');
        $username = $app->request->post('username');

        $operator = Operator::whereUsername($username)->first();
        if (!$operator) {
            $operator = new Operator;
            $operator->warnet_id = $warnet_id;
            $operator->owner_id = $_SESSION['id'];
            $operator->username = $username;

            $bcrypt = new \Bcrypt\Bcrypt();
            $operator->password = $bcrypt->hash($app->request->post('password'));

            $operator->save();
        } else {
            $app->flash('error', 'Username telah terdaftar');
        }
        $app->redirect($app->baseUrl().'/operator');
    }

    public function update($app) {
        $id = $app->request->post('id');
        $operator = Operator::find($id);
        
        if ($_SESSION['id'] == $operator->warnet->owner_id) {
            if ($operator) {
                $password = $app->request->post('password');

                if ($password) {
                    $bcrypt = new \Bcrypt\Bcrypt();
                    $operator->password = $bcrypt->hash($password);
                    if ($operator->save()) {
                        $app->flash('success', 'Operator diperbarui');
                    } else {
                        $app->flash('error', 'Gagal memperbarui operator');
                    }
                } else {
                    $app->flash('error', 'Info yang Anda masukkan tidak lengkap');
                }
            } else {
                $app->flash('error', 'Operator tidak ditemukan');
            }
            $app->redirect($app->baseUrl().'/operator');
        }
        $app->redirect($app->baseUrl().'/auth/login');
    }

    public function destroy($app) {
        $id = $app->request->post('id');
        $operator = Operator::find($id);

        if ($_SESSION['id'] == $operator->warnet->owner_id) {
            $operator->active = 0;
            $operator->is_deleted = 1;
            $operator->save();
            $app->redirect($app->baseUrl().'/operator');
        }
        $app->redirect($app->baseUrl().'/auth/login');
    }

    public function activate($app) {
        $id = $app->request->post('id');
        $operator = Operator::find($id);

        if ($_SESSION['id'] == $operator->warnet->owner_id) {
            $operator->active = 1;

            $operator->save();
        }
        $app->redirect($app->baseUrl().'/operator');
    }

    public function deactivate($app) {
        $id = $app->request->post('id');
        $operator = Operator::find($id);

        if ($_SESSION['id'] == $operator->warnet->owner_id) {
            $operator->active = 0;

            $operator->save();
        }
        $app->redirect($app->baseUrl().'/operator');
    }
}