<?php

namespace App\Controller\Portal;

use App\Controller\Portal\BasePortalController;
use App\Model\Owner;
use App\Model\Operator;
use App\Model\Warnet;
use App\Model\Wallet;

class WarnetController extends BasePortalController
{
    function getRoutes() {
        $routes = array(
            'get' => array(
                '' => array('view', $this->auth($this->getApp())),
                '/:id/operators' => array('operators', $this->auth($this->getApp())),
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

        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();
        if ($owner) {
            $warnets = Warnet::where('owner_id', '=', $owner->id)
                ->where('is_deleted', '=', 0)
                ->orderBy('created_at', 'desc')
                ->get();
        }
        $app->render('portal/header.php', array(
            'name' => $_SESSION['name']
        ));
        $app->render('portal/warnet.php', array(
            'warnets' => $warnets,
            'wallets' => $owner->wallets,
            'assets' => array(
                'js' => array(
                    'portal-warnet.js?v=2'
                )
            )
        ));
        $app->render('portal/footer.php');
    }

    public function create($app) {
        $warnet = new Warnet;
        $warnet->owner_id = $_SESSION['id'];
        $warnet->name = $app->request->post('name');
        $warnet->address = $app->request->post('address');
        $warnet->phone = $app->request->post('phone');
        $warnet->save();

        $wallet_id = $app->request->post('wallet_id');
        if ($wallet_id) {
            $wallet = Wallet::find($wallet_id);

            if ($wallet->owner_id == $warnet->owner_id) {
                $warnet->wallets()->attach($wallet_id);
            }
        }

        $app->redirect($app->baseUrl().'/warnet');
    }

    public function update($app) {
        $id = $app->request->post('id');
        $warnet = Warnet::find($id);
        
        if ($_SESSION['id'] == $warnet->owner_id) {
            if ($warnet) {
                $name = $app->request->post('name');
                $address = $app->request->post('address');
                $phone = $app->request->post('phone');
                // $wallet_id = $app->request->post('wallet_id');

                if ($name && $address && $phone) {
                    $warnet->name = $name;
                    $warnet->address = $address;
                    $warnet->phone = $phone;

                    // if ($wallet_id) {
                    //     $wallet = Wallet::find($wallet_id);

                    //     if ($wallet->owner_id == $warnet->owner_id) {
                    //         $warnet->wallets()->detach();
                    //         $warnet->wallets()->attach($wallet_id);
                    //     }
                    // } else {
                    //     $warnet->wallets()->detach();
                    // }

                    if ($warnet->save()) {
                        $app->flash('success', 'Warnet diperbarui');
                    } else {
                        $app->flash('error', 'Gagal memperbarui warnet');
                    }
                } else {
                    $app->flash('error', 'Info yang Anda masukkan tidak lengkap');
                }
            } else {
                $app->flash('error', 'Warnet tidak ditemukan');
            }
            $app->redirect($app->baseUrl().'/warnet');
        }
        $app->redirect($app->baseUrl().'/auth/login');
    }

    public function destroy($app) {
        $id = $app->request->post('id');
        $warnet = Warnet::find($id);

        if ($_SESSION['id'] == $warnet->owner_id) {

            $warnet->active = 0;
            $warnet->is_deleted = 1;
            $warnet->wallets()->detach();
            $warnet->save();
            $app->redirect($app->baseUrl().'/warnet');
        }
        $app->redirect($app->baseUrl().'/auth/login');
    }

    public function activate($app) {
        $id = $app->request->post('id');
        $warnet = Warnet::find($id);

        if ($_SESSION['id'] == $warnet->owner_id) {
            $warnet->active = 1;

            $warnet->save();
        }
        $app->redirect($app->baseUrl().'/warnet');
    }

    public function deactivate($app) {
        $id = $app->request->post('id');
        $warnet = Warnet::find($id);

        if ($_SESSION['id'] == $warnet->owner_id) {
            $warnet->active = 0;

            $warnet->save();
        }
        $app->redirect($app->baseUrl().'/warnet');
    }

    public function operators($app, $id) {
        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();

        $operators = array();

        if ($owner) {
            $operators = Operator::where('owner_id', '=', $owner->id)
                ->where('warnet_id', '=', $id)
                // ->where('is_deleted', '=', 0)
                ->orderBy('username', 'asc')
                ->get()
                ->toArray();
        }

        if ($app->request->isAjax()) {
            $app->response->headers->set('Content-type', 'application/json');
            echo json_encode($operators);
        } else {
            
        }
    }
}