<?php

namespace App\Controller\Portal;

use App\Controller\Portal\BasePortalController;
use App\Model\Owner;
use App\Model\OperatorSession;

class DeviceController extends BasePortalController
{
    function getRoutes() {
        $routes = array(
            'get' => array(
                '' => array('view', $this->auth($this->getApp()))
            ),
            'post' => array(
                '/approve' => array('approve', $this->auth($this->getApp())),
                '/reject' => array('reject', $this->auth($this->getApp()))
            )
        );
        return $routes;
    }

    public function view($app) {
        $devices = array();

        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();
        if ($owner) {
            $devices = OperatorSession::where('owner_id', '=', $owner->id)
                ->orderBy('created_at', 'desc')
                ->get();
        }
        $app->render('portal/header.php', array(
            'name' => $_SESSION['name']
        ));
        $app->render('portal/device.php', array(
            'devices' => $devices,
            'assets' => array(
                'js' => array(
                    'portal-device.js'
                )
            )
        ));
        $app->render('portal/footer.php');
    }

    public function approve($app) {
        $id = $app->request->post('id');
        $device = OperatorSession::find($id);

        if ($_SESSION['id'] == $device->owner_id) {
            $device->approved = 1;

            $device->save();
            $app->redirect($app->baseUrl().'/device');
        }
        $app->redirect($app->baseUrl().'/auth/login');
    }

    public function reject($app) {
        $id = $app->request->post('id');
        $device = OperatorSession::find($id);

        if ($_SESSION['id'] == $device->owner_id) {
            $device->approved = 0;

            $device->save();
            $app->redirect($app->baseUrl().'/device');
        }
        $app->redirect($app->baseUrl().'/auth/login');
    }
}