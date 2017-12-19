<?php

namespace App\Controller\Admin;

use App\Controller\Admin\BaseAdminController;
use App\Model\Admin;
use App\Model\Promoter;
use App\Model\Redeem;
use App\Model\RedeemSetup;
use App\Model\Warnet;
use App\Model\MailQueue;

class RedeemController extends BaseAdminController
{
    function getRoutes() {
        return array(
            'get' => array(
                '' => array('view', $this->auth($this->getApp())),
                '/setup' => array('setup', $this->auth($this->getApp()))
            ),
            'post' => array(
                '/change_status' => array('change_status', $this->auth($this->getApp())),
                '/setup' => array('create_setup', $this->auth($this->getApp())),
                '/verify' => array('verify', $this->auth($this->getApp())),
                '/setup/activate' => array('activate', $this->auth($this->getApp())),
                '/setup/deactivate' => array('deactivate', $this->auth($this->getApp()))
            ),
            'put' => array(
                '/setup' => array('update_setup', $this->auth($this->getApp()))
            ),
            'delete' => array(
                '' => array('destroy', $this->auth($this->getApp()))
            )
        );
    }

    public function view($app) {
        $promoters = array();

        $email = $_SESSION['admin_email'];
        $admin = Admin::whereEmail($email)->whereActive(1)->first();
        if ($admin) {
            $redeems = Redeem::where('status', '>', 0)
                ->orderBy('created_at', 'desc')
                ->get();
        }
        $app->render('admin/header.php', array(
            'name' => $_SESSION['admin_name']
        ));
        $app->render('admin/redeem.php', array(
            'redeems' => $redeems,
            'assets' => array(
                'js' => array(
                    'admin-redeem.js?v=2'
                )
            )
        ));
        $app->render('admin/footer.php');
    }

    public function setup($app) {
        $promoters = array();

        $email = $_SESSION['admin_email'];
        $admin = Admin::whereEmail($email)->whereActive(1)->first();
        if ($admin) {
            $redeem_setups = RedeemSetup::orderBy('created_at', 'asc')->get();
        }
        $app->render('admin/header.php', array(
            'name' => $_SESSION['admin_name']
        ));
        $app->render('admin/redeem_setup.php', array(
            'redeem_setups' => $redeem_setups,
            'assets' => array(
                'js' => array(
                    'admin-redeem-setup.js?v=2'
                )
            )
        ));
        $app->render('admin/footer.php');
    }

    public function change_status($app)
    {
        $id = $app->request->post('id');
        $submit = $app->request->post('submit');
        $redeem = Redeem::find($id);

        if ($submit == 'reject') {
            $redeem->status = 3;
            $redeem->message = $app->request->post('message');
        } else if ($submit == 'transferred') {
            $redeem->status = 2;
        }

        if ($redeem->save()) {
            $app->flash('success', 'Redeem status saved');
        } else {
            $app->flash('error', 'Failed to save redeem status');
        }
        $app->redirect($app->baseUrl().'/redeem');
    }

    public function create_setup($app) {
        $start_time = $app->request->post('start_time');
        $end_time = $app->request->post('end_time');

        if ($start_time && $end_time) {
            $redeem_setup = new RedeemSetup;
            $redeem_setup->start_time = $start_time." 00:00:00";
            $redeem_setup->end_time = $end_time." 23:59:59";
            
            if ($redeem_setup->save()) {
                $app->flash('success', 'Redeem date range added');
            } else {
                $app->flash('error', 'Failed to save redeem date range');
            }
        } else {
            $app->flash('error', 'Missing some information');
        }
        $app->redirect($app->baseUrl().'/redeem/setup');
    }

    public function update_setup($app) {
        $id = $app->request->post('id');
        $start_time = $app->request->post('start_time');
        $end_time = $app->request->post('end_time');

        $redeem_setup = RedeemSetup::find($id);
        if ($redeem_setup) {
            if ($start_time && $end_time) {
                $redeem_setup->start_time = $start_time." 00:00:00";
                $redeem_setup->end_time = $end_time." 23:59:59";
                
                if ($redeem_setup->save()) {
                    $app->flash('success', 'Redeem date range updated');
                } else {
                    $app->flash('error', 'Failed to save redeem date range');
                }
            } else {
                $app->flash('error', 'Missing some information');
            }
        } else {
            $app->flash('error', 'Resource not found');
        }
        $app->redirect($app->baseUrl().'/redeem/setup');
    }

    public function activate($app) {
        $id = $app->request->post('id');
        $redeem_setup = RedeemSetup::find($id);

        $redeem_setup->active = 1;
        $redeem_setup->save();
        
        $app->redirect($app->baseUrl().'/redeem/setup');
    }

    public function deactivate($app) {
        $id = $app->request->post('id');
        $redeem_setup = RedeemSetup::find($id);

        $redeem_setup->active = 0;
        $redeem_setup->save();
        
        $app->redirect($app->baseUrl().'/redeem/setup');
    }

    public function destroy($app) {
        $id = $app->request->post('id');
        $redeem_setup = RedeemSetup::find($id);

        $redeem_setup->active = 0;
        $redeem_setup->is_deleted = 1;
        $redeem_setup->save();
        $app->redirect($app->baseUrl().'/redeem/setup');
    }
}