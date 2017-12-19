<?php

namespace App\Controller\Admin;

use App\Controller\Admin\BaseAdminController;
use App\Model\Admin;
use App\Model\Owner;
use App\Model\Topup;
use App\Model\Warnet;
use App\Model\MailQueue;
use App\Library\Pagination;

class OwnerController extends BaseAdminController
{
    function getRoutes() {
        $routes = array(
            'get' => array(
                '' => array('view', $this->auth($this->getApp())),
                '/download' => array('download', $this->auth($this->getApp())),
                '/:id/warnets' => array('warnets', $this->auth($this->getApp())),
                '/:id/balance' => array('balance', $this->auth($this->getApp()))
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
        $search = htmlspecialchars($app->request->get('search'));

        $owners = array();
        $pagination = null;
        $total_topup = 'N\A';

        $email = $_SESSION['admin_email'];
        $admin = Admin::whereEmail($email)->whereActive(1)->first();
        if ($admin) {
            $topup_number = Topup::where('status', 2)->sum('amount');
            $total_topup = $topup_number !== false ? "Rp.".number_format($topup_number, 0, ',', '.') : 'N/A';
            // $owners = Owner::where('is_deleted', '=', 0)
            //     ->orderBy('created_at', 'desc')
            //     ->get();

            $owners = array();

            $owner_builder = Owner::query();

            $url = '/owner';
            $query_params = array();

            if ($search) {
                $owner_builder->where(function($query) use($search) {
                    $query->orWhere('name', 'LIKE', "%$search%");
                    $query->orWhere('email', 'LIKE', "%$search%");
                    $query->orWhere('phone', 'LIKE', "%$search%");
                    $query->orWhere('warnet_name', 'LIKE', "%$search%");
                    $query->orWhere('warnet_address', 'LIKE', "%$search%");
                    $query->orWhere('warnet_city', 'LIKE', "%$search%");
                });
                $query_params['search'] = $search;
            }

            $owner_builder->where('is_deleted', '=', 0);
            $owner_builder->orderBy('created_at', 'asc');

            $number_of_items = $owner_builder->count();
            $limit = 100;
            $number_of_page = ceil($number_of_items / $limit);
            $page = $app->request->get('page') ? $app->request->get('page') : $number_of_page;
            $offset = ($page - 1) * $limit;
            $from_count = $offset + 1;
            $to_count = $offset + $limit;
            if ($number_of_items < $limit || $to_count > $number_of_items) {
                $to_count = $number_of_items;
            }
            $owners = $owner_builder->forPage($page, $limit)->get();
            
            if (count($owners)) {
                $pagination = Pagination::createHTML($url, $query_params, $number_of_page, $page, null, $from_count, $to_count, $number_of_items);
            }
        }

        // $queries = $app->request->get();
        // $is_query_exist = $queries && (!(count($queries == 1) && array_key_exists('page', $queries))) ? true : false;

        $app->render('admin/header.php', array(
            'name' => $_SESSION['admin_name']
        ));
        $app->render('admin/owner.php', array(
            'total_topup' => $total_topup,
            'owners' => $owners,
            'query' => array(
                'search' => $search
            ),
            // 'filter' => $is_query_exist,
            'pagination' => $pagination,
            'offset' => $offset,
            'assets' => array(
                'js' => array(
                    'admin-owner.js?v=3'
                )
            )
        ));
        $app->render('admin/footer.php');
    }

    public function download($app) {
        $owners = array();

        $owner_builder = Owner::query();

        $url = '/owner';

        $owner_builder->where('is_deleted', '=', 0);
        $owner_builder->orderBy('created_at', 'asc');

        $headers = array(
            'Name',
            'Email',
            'Phone',
            'Warnet Name',
            'Warnet Number',
            'Warnet Address',
            'Warnet City',
            'Total Topup (Rp)',
            'Verify Email',
            'Register At',
            'Status'
        );

        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename='uwarnet-owner-" . date("Y-m-d_H-i-s") . ".csv");
        header("Pragma: no-cache");
        header("Expires: 0");
            
        $handle = fopen("php://output", "w");
        fputcsv($handle, $headers);

        $owner_builder->chunk(200, function($rows) use(&$handle) {
            foreach ($rows as &$owner) {
                switch ($owner->active) {
                    case 0:
                        $status = 'Inactive';
                        break;
                    case 1:
                        $status = 'Active';
                        break;
                }
                switch ($owner->verified) {
                    case 0:
                        $verified = 'Not Verified';
                        break;
                    case 1:
                        $verified = 'Verified';
                        break;
                }
                fputcsv($handle, array(
                    $owner->name,
                    $owner->email,
                    $owner->phone,
                    $owner->warnet_name,
                    $owner->warnet_count,
                    $owner->warnet_address,
                    $owner->warnet_city,
                    $owner->total_topup(true),
                    $verified,
                    $owner->created_at,
                    $status
                ));
            }
        });
        
        fclose($handle);
    }

    public function verify($app) {
        $id = $app->request->post('id');
        $owner = Owner::find($id);

        $owner->active = 1;

        if ($owner->save()) {
            $mail_queue = new MailQueue;
            $mail_queue->email = $owner->email;
            $mail_queue->name = $owner->name;
            $mail_queue->type = $mail_queue->types['ADMIN_ACTIVATION_NOTIF'];
            $mail_queue->params = json_encode(array(
                'link' => 'https://uwarnet.id'
            ));
            $mail_queue->save();
        }
        $app->redirect($app->baseUrl().'/owner');
    }

    public function activate($app) {
        $id = $app->request->post('id');
        $owner = Owner::find($id);

        $owner->active = 1;
        if ($owner->save()) {
            $mail_queue = new MailQueue;
            $mail_queue->email = $owner->email;
            $mail_queue->name = $owner->name;
            $mail_queue->type = $mail_queue->types['ADMIN_ACTIVATION_NOTIF'];
            $mail_queue->params = json_encode(array(
                'link' => 'https://uwarnet.id'
            ));
            $mail_queue->save();
        }
        
        $app->redirect($app->baseUrl().'/owner');
    }

    public function deactivate($app) {
        $id = $app->request->post('id');
        $owner = Owner::find($id);

        $owner->active = 0;
        $owner->save();
        
        $app->redirect($app->baseUrl().'/owner');
    }

    public function destroy($app) {
        $id = $app->request->post('id');
        $owner = Owner::find($id);

        $owner->active = 0;
        $owner->is_deleted = 1;
        $owner->save();
        $app->redirect($app->baseUrl().'/owner');
    }

    public function warnets($app, $id) {
        $warnets = Warnet::where('owner_id', '=', $id)
            ->where('is_deleted', '=', 0)
            ->orderBy('name', 'asc')
            ->get()
            ->toArray();

        $app->response->headers->set('Content-type', 'application/json');
        echo json_encode($warnets);
    }

    public function balance($app, $id) {
        $owner = Owner::find($id);

        $app->response->headers->set('Content-type', 'application/json');
        if ($owner->wallets()->first() && $owner->wallets()->first()->balance()) {
            $result = number_format($owner->wallets()->first()->balance(), 0, ',', '.');
        } else {
            $result = 'N/A';
        }
        echo json_encode($result);
    }
}