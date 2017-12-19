<?php

namespace App\Controller\Admin;

use App\Controller\Admin\BaseAdminController;
use App\Model\Admin;
use App\Model\Promoter;
use App\Model\Topup;
use App\Model\Warnet;
use App\Model\MailQueue;

class PromoterController extends BaseAdminController
{
    function getRoutes() {
        $routes = array(
            'get' => array(
                '' => array('view', $this->auth($this->getApp()))
            ),
            'post' => array(
                '/verify' => array('verify', $this->auth($this->getApp())),
                '/activate' => array('activate', $this->auth($this->getApp())),
                '/deactivate' => array('deactivate', $this->auth($this->getApp()))
            ),
            'delete' => array(
                '' => array('destroy', $this->auth($this->getApp()))
            )
        );
        return $routes;
    }

    public function view($app) {
        $promoters = array();

        $email = $_SESSION['admin_email'];
        $admin = Admin::whereEmail($email)->whereActive(1)->first();
        if ($admin) {
            $promoters = Promoter::where('is_deleted', '=', 0)
                // ->where('verified', 1)
                ->orderBy('created_at', 'desc')
                ->get();
        }
        $app->render('admin/header.php', array(
            'name' => $_SESSION['admin_name']
        ));
        $app->render('admin/promoter.php', array(
            'promoters' => $promoters,
            'assets' => array(
                'js' => array(
                    'admin-promoter.js?v=2'
                )
            )
        ));
        $app->render('admin/footer.php');
    }

    public function verify($app) {
        $id = $app->request->post('id');
        $promoter = Promoter::find($id);

        $promoter->active = 1;

        if ($promoter->save()) {
            $mail_queue = new MailQueue;
            $mail_queue->email = $promoter->email;
            $mail_queue->name = $promoter->name;
            $mail_queue->type = $mail_queue->types['ADMIN_ACTIVATION_NOTIF'];
            $mail_queue->params = json_encode(array(
                'link' => 'https://uwarnet.id'
            ));
            $mail_queue->save();
        }
        $app->redirect($app->baseUrl().'/promoter');
    }

    public function activate($app) {
        $id = $app->request->post('id');
        $promoter = Promoter::find($id);

        $promoter->active = 1;
        $promoter->save();
        
        $app->redirect($app->baseUrl().'/promoter');
    }

    public function deactivate($app) {
        $id = $app->request->post('id');
        $promoter = Promoter::find($id);

        $promoter->active = 0;
        $promoter->save();
        
        $app->redirect($app->baseUrl().'/promoter');
    }

    public function destroy($app) {
        $id = $app->request->post('id');
        $promoter = Promoter::find($id);

        $promoter->active = 0;
        $promoter->is_deleted = 1;
        $promoter->save();
        $app->redirect($app->baseUrl().'/promoter');
    }
}