<?php

namespace App\Controller\Admin;

use App\Controller\Admin\BaseAdminController;
use App\Model\Owner;
use App\Model\OperatorSession;
use App\Model\Admin;
use App\Library\Pagination;

class DeviceController extends BaseAdminController
{
    function getRoutes() {
        return array(
            'get' => array(
                '' => array('view', $this->auth($this->getApp()))
            )
        );
    }

    public function view($app) {
        $search = htmlspecialchars($app->request->get('search'));

        $operators = array();
        $pagination = null;

        $email = $_SESSION['admin_email'];
        $admin = Admin::whereEmail($email)->whereActive(1)->first();
        if ($admin) {
            $device_builder = OperatorSession::query();

            $url = '/device';
            $query_params = array();

            if ($search) {
                $device_builder->where(function($query) use($search) {
                    $query->whereHas('operator', function($query) use($search) {
                        $query->where('username', 'LIKE', "%$search%");
                    })
                    ->orWhereHas('warnet', function($query) use($search) {
                        $query->where('name', 'LIKE', "%$search%");
                    })
                    ->orWhereHas('owner', function($query) use($search) {
                        $query->where('name', 'LIKE', "%$search%");
                    });
                });
                $query_params['search'] = $search;
            }

            $number_of_items = $device_builder->count();
            $limit = 100;
            $number_of_page = ceil($number_of_items / $limit);
            $page = $app->request->get('page') ? $app->request->get('page') : $number_of_page;
            $offset = ($page - 1) * $limit;
            $from_count = $offset + 1;
            $to_count = $offset + $limit;
            if ($number_of_items < $limit || $to_count > $number_of_items) {
                $to_count = $number_of_items;
            }
            $devices = $device_builder->forPage($page, $limit)->get();
            
            if (count($devices)) {
                $pagination = Pagination::createHTML($url, $query_params, $number_of_page, $page, null, $from_count, $to_count, $number_of_items);
            }
        }
        
        $app->render('admin/header.php', array(
            'name' => $_SESSION['admin_name']
        ));
        $app->render('admin/device.php', array(
            'devices' => $devices,
            'query' => array(
                'search' => $search
            ),
            // 'filter' => $is_query_exist,
            'pagination' => $pagination,
            'offset' => $offset,
            'assets' => array(
                'js' => array(
                    'admin-device.js?v=1'
                )
            )
        ));
        $app->render('admin/footer.php');
    }
}