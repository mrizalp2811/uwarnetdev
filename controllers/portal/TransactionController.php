<?php

namespace App\Controller\Portal;

use App\Controller\Portal\BasePortalController;
use App\Model\Owner;
use App\Model\Warnet;
use App\Model\Operator;
use App\Model\Transaction;
use App\Model\Group;
use App\Model\Item;
use App\Library\Pagination;

class TransactionController extends BasePortalController
{
    function getRoutes() {
        $routes = array(
            'get' => array(
                '' => array('view', $this->auth($this->getApp())),
                '/download' => array('download', $this->auth($this->getApp()))
            ),
            'post' => array(
                '/approve' => array('approve', $this->auth($this->getApp())),
                '/reject' => array('reject', $this->auth($this->getApp())),
                // '/viewitem/:id' => array('view_item', $this->auth($this->getApp()))
            )
        );
        return $routes;
    }

    public function view($app) {
        $from = $app->request->get('from'); 
        $to = $app->request->get('to'); 
        $warnet_id = $app->request->get('warnet_id'); 
        $operator_id = $app->request->get('operator_id'); 
        $group_id = $app->request->get('group_id'); 
        $item_id = $app->request->get('item_id');
        $status = $app->request->get('status');

        $warnets = array();
        $operators = array();
        $transactions = array();
        $pagination = array();

        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();
        if ($owner) {
            $warnets = Warnet::where('owner_id', '=', $owner->id)
                // ->where('is_deleted', '=', 0)
                ->orderBy('name', 'asc')
                ->get();

            if ($warnet_id) {
                $operators = Operator::where('warnet_id', '=', $warnet_id)
                    // ->where('is_deleted', '=', 0)
                    ->orderBy('username', 'asc')
                    ->get();
            }

            $transaction_builder = Transaction::query();
            $transaction_builder->where('owner_id', '=', $owner->id);

            $url = '/transaction';
            $query_params = array();

            if ($from) {
                $transaction_builder->where('init_time', '>=', $from." 00:00:00");
                $query_params['from'] = $from;
            }
            if ($to) {
                $transaction_builder->where('init_time', '<=', $to." 23:59:59");
                $query_params['to'] = $to;
            }
            if ($warnet_id) {
                $transaction_builder->where('warnet_id', '=', $warnet_id);
                $query_params['warnet_id'] = $warnet_id;
            }
            if ($operator_id) {
                $transaction_builder->where('operator_id', '=', $operator_id);
                $query_params['operator_id'] = $operator_id;
            }
            if ($group_id) {
                $transaction_builder->where('group_id', '=', $group_id);
                $query_params['group_id'] = $group_id;
            }
            if ($item_id) {
                $transaction_builder->where('item_id', '=', $item_id);
                $query_params['item_id'] = $item_id;
            }
            if ($status) {
                $transaction_builder->where('status', '=', $status);
                $query_params['status'] = $status;
            }

            $number_of_items = $transaction_builder->count();
            $limit = 100;
            $number_of_page = ceil($number_of_items / $limit);
            $page = $app->request->get('page') ? $app->request->get('page') : $number_of_page;
            $offset = ($page - 1) * $limit;
            $from_count = $offset + 1;
            $to_count = $offset + $limit;
            if ($number_of_items < $limit || $to_count > $number_of_items) {
                $to_count = $number_of_items;
            }
            $total_price = $transaction_builder->sum('price');
            $total_sale_price = $transaction_builder->sum('sale_price');
            $transactions = $transaction_builder->forPage($page, $limit)->get();

            if (count($transactions)) {
                $pagination = Pagination::createHTML($url, $query_params, $number_of_page, $page, null, $from_count, $to_count, $number_of_items);
            }
        }

        $groups = Group::orderBy('name', 'asc')->get();

        $items = array();
        if ($group_id) {
            $items = Item::where('group_id', '=', $group_id)
                ->orderBy('name', 'asc')
                ->get();
        }
        $queries = $app->request->get();
        $is_query_exist = $queries && (!(count($queries == 1) && array_key_exists('page', $queries))) ? true : false;

        $app->render('portal/header.php', array(
            'name' => $_SESSION['name']
        ));
        $app->render('portal/transaction.php', array(
            'warnets' => $warnets,
            'operators' => $operators,
            'groups' => $groups,
            'items' => $items,
            'transactions' => $transactions,
            'query' => array(
                'from' => $from ? $from : null,
                'to' => $to ? $to : null,
                'warnet_id' => $warnet_id,
                'operator_id' => $operator_id,
                'group_id' => $group_id,
                'item_id' => $item_id,
                'status' => $status
            ),
            'filter' => $is_query_exist,
            'pagination' => $pagination,
            'offset' => $offset,
            'total_price' => $total_price,
            'total_sale_price' => $total_sale_price,
            'assets' => array(
                'js' => array(
                    'portal-transaction.js?v=2'
                )
            )
        ));
        $app->render('portal/footer.php');
    }

    public function download($app)
    {
        $from = $app->request->get('from'); 
        $to = $app->request->get('to'); 
        $warnet_id = $app->request->get('warnet_id'); 
        $operator_id = $app->request->get('operator_id'); 
        $group_id = $app->request->get('group_id'); 
        $item_id = $app->request->get('item_id'); 
        $status = $app->request->get('status'); 
        $page = $app->request->get('page') ? $app->request->get('page') : 1;

        $warnets = array();
        $operators = array();
        $transactions = array();
        $pagination = array();

        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();
        if ($owner) {
            $warnets = Warnet::where('owner_id', '=', $owner->id)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($warnet_id) {
                $operators = Operator::where('warnet_id', '=', $warnet_id)
                    ->orderBy('created_at', 'desc')
                    ->get();
            }
        }

        $transaction_builder = Transaction::query();

        $transaction_builder->where('owner_id', '=', $owner->id);

        if ($from) {
            $transaction_builder->where('init_time', '>=', $from." 00:00:00");
        }
        if ($to) {
            $transaction_builder->where('init_time', '<=', $to." 23:59:59");
        }
        if ($warnet_id) {
            $transaction_builder->where('warnet_id', '=', $warnet_id);
        }
        if ($operator_id) {
            $transaction_builder->where('operator_id', '=', $operator_id);
        }
        if ($group_id) {
            $transaction_builder->where('group_id', '=', $group_id);
        }
        if ($item_id) {
            $transaction_builder->where('item_id', '=', $item_id);
        }
        if ($status) {
            $transaction_builder->where('status', '=', $status);
        }

        $headers = array(
            'Waktu Awal',
            'Waktu Akhir',
            'Harga Beli (Rp)',
            'Harga Jual (Rp)',
            'Group',
            'Item',
            'Warnet',
            'Operator',
            'Status'
        );

        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename='uwarnet-export-" . date("Y-m-d_H-i-s") . ".csv");
        header("Pragma: no-cache");
        header("Expires: 0");
            
        $handle = fopen("php://output", "w");
        fputcsv($handle, $headers);

        $transaction_builder->chunk(200, function($rows) use(&$handle) {
            foreach ($rows as &$transaction) {
                switch ($transaction->status) {
                    case 1:
                        $status = 'Dimulai';
                        break;
                    case 2:
                        $status = 'Sukses';
                        break;
                    case 3:
                        $status = 'Gagal';
                        break;
                    case 4:
                        $status = 'Expired';
                        break;
                }
                fputcsv($handle, array(
                    $transaction->init_time,
                    $transaction->confirm_time,
                    $transaction->price,
                    $transaction->sale_price,
                    $transaction->group->name,
                    $transaction->item->name,
                    $transaction->warnet->name,
                    $transaction->operator->username,
                    $status
                ));
            }
        });
        
        fclose($handle);
    }

    // public function view_item($app, $id)
    // {
    //     $id = $app->request->post('id');
    //     $password = $app->request->post('password');

    //     $email = $_SESSION['email'];
    //     $owner = Owner::whereEmail($email)->whereActive(1)->first();

    //     $bcrypt = new \Bcrypt\Bcrypt();

    //     if ($bcrypt->verify($password, $owner->password)) {
    //         $transaction = Transaction::find($id);
    //         if ($transaction->owner_id == $owner->id) {
    //             $result = new \stdClass();
    //             $result->status = true;
    //             $itemString = '';
    //             $itemElements = json_decode($transaction->supplier_item);
    //             if ($itemElements) foreach ($itemElements as $element) {
    //                 $itemString .= $element->name." = ".$element->value."\n\r";
    //             }
    //             $result->item = $itemString;
    //         } else {
    //             $result = new \stdClass();
    //             $result->status = false;
    //             $result->code = 'denied';
    //         }
    //     } else {
    //         $result = new \stdClass();
    //         $result->status = false;
    //         $result->code = 'password';
    //     }

    //     $app->response->headers->set('Content-type', 'application/json');
    //     echo json_encode($result);
    // }

}