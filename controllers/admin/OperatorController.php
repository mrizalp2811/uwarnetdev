<?php

namespace App\Controller\Admin;

use App\Controller\Admin\BaseAdminController;
use App\Model\Owner;
use App\Model\Warnet;
use App\Model\Operator;
use App\Model\Admin;
use App\Library\Pagination;

class OperatorController extends BaseAdminController
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
            $operator_builder = Operator::query();

            $url = '/operator';
            $query_params = array();

            if ($search) {
                $operator_builder->where(function($query) use($search) {
                    $query->where('username', 'LIKE', "%$search%")
                        ->orWhereHas('warnet', function($query) use($search) {
                            $query->where(function($query) use($search) {
                                $query->where('name', 'LIKE', "%$search%")
                                    ->orWhere('address', 'LIKE', "%$search%")
                                    ->orWhere('phone', 'LIKE', "%$search%")
                                    ->orWhereHas('owner', function($query) use($search) {
                                        $query->where(function($query) use($search) {
                                            $query->where('name', 'LIKE', "%$search%")
                                                // ->orWhere('email', 'LIKE', "%$search%")
                                                ->orWhere('phone', 'LIKE', "%$search%")
                                                // ->orWhere('warnet_name', 'LIKE', "%$search%")
                                                // ->orWhere('warnet_address', 'LIKE', "%$search%")
                                                ->orWhere('warnet_city', 'LIKE', "%$search%");
                                        });
                                    });
                            });
                        });
                });
                $query_params['search'] = $search;
            }

            $operator_builder->where('is_deleted', '=', 0);
            $operator_builder->orderBy('created_at', 'asc');

            $number_of_items = $operator_builder->count();
            $limit = 100;
            $number_of_page = ceil($number_of_items / $limit);
            $page = $app->request->get('page') ? $app->request->get('page') : $number_of_page;
            $offset = ($page - 1) * $limit;
            $from_count = $offset + 1;
            $to_count = $offset + $limit;
            if ($number_of_items < $limit || $to_count > $number_of_items) {
                $to_count = $number_of_items;
            }
            $operators = $operator_builder->forPage($page, $limit)->get();
            
            if (count($operators)) {
                $pagination = Pagination::createHTML($url, $query_params, $number_of_page, $page, null, $from_count, $to_count, $number_of_items);
            }
        }
        
        $app->render('admin/header.php', array(
            'name' => $_SESSION['admin_name']
        ));
        $app->render('admin/operator.php', array(
            'operators' => $operators,
            'query' => array(
                'search' => $search
            ),
            // 'filter' => $is_query_exist,
            'pagination' => $pagination,
            'offset' => $offset,
            'assets' => array(
                'js' => array(
                    'admin-operator.js?v=1'
                )
            )
        ));
        $app->render('admin/footer.php');
    }
}