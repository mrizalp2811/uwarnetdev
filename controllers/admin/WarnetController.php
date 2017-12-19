<?php

namespace App\Controller\Admin;

use App\Controller\Admin\BaseAdminController;
use App\Model\Operator;
use App\Model\Warnet;
use App\Model\Admin;
use App\Library\Pagination;

class WarnetController extends BaseAdminController
{
    function getRoutes() {
        return array(
            'get' => array(
                '' => array('view', $this->auth($this->getApp())),
                '/:id/operators' => array('operators', $this->auth($this->getApp())),
            ),
            'delete' => array(
                '' => array('destroy', $this->auth($this->getApp()))
            )
        );
    }

    public function view($app) {
        $search = htmlspecialchars($app->request->get('search'));

        $warnets = array();
        $pagination = null;

        $email = $_SESSION['admin_email'];
        $admin = Admin::whereEmail($email)->whereActive(1)->first();
        if ($admin) {
            $warnet_builder = Warnet::query();

            $url = '/warnet';
            $query_params = array();

            if ($search) {
                $warnet_builder->where(function($query) use($search) {
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
                $query_params['search'] = $search;
            }

            $warnet_builder->where('is_deleted', '=', 0);
            $warnet_builder->orderBy('created_at', 'asc');

            $number_of_items = $warnet_builder->count();
            $limit = 100;
            $number_of_page = ceil($number_of_items / $limit);
            $page = $app->request->get('page') ? $app->request->get('page') : $number_of_page;
            $offset = ($page - 1) * $limit;
            $from_count = $offset + 1;
            $to_count = $offset + $limit;
            if ($number_of_items < $limit || $to_count > $number_of_items) {
                $to_count = $number_of_items;
            }
            $warnets = $warnet_builder->forPage($page, $limit)->get();
            
            if (count($warnets)) {
                $pagination = Pagination::createHTML($url, $query_params, $number_of_page, $page, null, $from_count, $to_count, $number_of_items);
            }
        }

        $app->render('admin/header.php', array(
            'name' => $_SESSION['admin_name']
        ));
        $app->render('admin/warnet.php', array(
            'warnets' => $warnets,
            'query' => array(
                'search' => $search
            ),
            // 'filter' => $is_query_exist,
            'pagination' => $pagination,
            'offset' => $offset,
            'assets' => array(
                'js' => array(
                    'admin-warnet.js?v=1'
                )
            )
        ));
        $app->render('admin/footer.php');
    }

    public function destroy($app) {
        $id = $app->request->post('id');
        $warnet = Warnet::find($id);

        $warnet->active = 0;
        $warnet->is_deleted = 1;
        $warnet->save();
        $app->redirect($app->baseUrl().'/warnet');
    }

    public function operators($app, $id) {
        $operators = Operator::where('warnet_id', '=', $id)
            ->where('is_deleted', '=', 0)
            ->orderBy('username', 'asc')
            ->get()
            ->toArray();

        $app->response->headers->set('Content-type', 'application/json');
        echo json_encode($operators);
    }
}