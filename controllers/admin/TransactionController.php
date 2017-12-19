<?php

namespace App\Controller\Admin;

use App\Controller\Admin\BaseAdminController;
use App\Model\Owner;
use App\Model\Warnet;
use App\Model\Operator;
use App\Model\Transaction;
use App\Model\Group;
use App\Model\Item;
use App\Model\Admin;
use App\Model\Sms;
use App\Library\Pagination;

class TransactionController extends BaseAdminController
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
                '/viewitem/:id' => array('view_item', $this->auth($this->getApp())),
                '/resendsms/:id' => array('resend_sms', $this->auth($this->getApp()))
            )
        );
        return $routes;
    }

    public function view($app) {
        $search = $app->request->get('search');
        $from = $app->request->get('from'); 
        $to = $app->request->get('to'); 
        $owner_id = $app->request->get('owner_id'); 
        $warnet_id = $app->request->get('warnet_id'); 
        $operator_id = $app->request->get('operator_id'); 
        $group_id = $app->request->get('group_id'); 
        $item_id = $app->request->get('item_id'); 
        $status = $app->request->get('status'); 

        $owners = array();
        $warnets = array();
        $operators = array();
        $transactions = array();
        $pagination = null;

        $owners = Owner::where('active', 1)->orderBy('name', 'asc')
            ->get();

        if ($owner_id) {
            $warnets = Warnet::where('owner_id', '=', $owner_id)
                // ->where('is_deleted', '=', 0)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($warnet_id) {
                $operators = Operator::where('warnet_id', '=', $warnet_id)
                    // ->where('is_deleted', '=', 0)
                    ->orderBy('created_at', 'desc')
                    ->get();
            }
        }

        $transaction_builder = Transaction::query();

        $url = '/transaction';
        $query_params = array();

        if ($search) {
            $transaction_builder->where(function($query) use($search) {
                $query->orWhere('ref_no', 'LIKE', "%$search%");
                $query->orWhere('phone', 'LIKE', "%$search%");
                $query->orWhere('pln_id', 'LIKE', "%$search%");
                $query->orWhere('supplier_item', 'LIKE', "%$search%");
            });
            $query_params['search'] = $search;
        }
        if ($from) {
            $transaction_builder->where('init_time', '>=', $from." 00:00:00");
            $query_params['from'] = $from;
        }
        if ($to) {
            $transaction_builder->where('init_time', '<=', $to." 23:59:59");
            $query_params['to'] = $to;
        }
        if ($owner_id) {
            $transaction_builder->where('owner_id', '=', $owner_id);
            $query_params['owner_id'] = $owner_id;
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
        
        $groups = Group::orderBy('name', 'asc')
                ->get();
        $items = array();
        if ($group_id) {
            $items = Item::where('group_id', '=', $group_id)
                    ->orderBy('name', 'asc')
                    ->get();
        }
        $queries = $app->request->get();
        $is_query_exist = $queries && (!(count($queries == 1) && array_key_exists('page', $queries))) ? true : false;

        $app->render('admin/header.php', array(
            'name' => $_SESSION['admin_name']
        ));
        $app->render('admin/transaction.php', array(
            'owners' => $owners,
            'warnets' => $warnets,
            'operators' => $operators,
            'groups' => $groups,
            'items' => $items,
            'transactions' => $transactions,
            'query' => array(
                'search' => $search,
                'from' => $from ? $from : null,
                'to' => $to ? $to : null,
                'owner_id' => $owner_id,
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
                    'admin-transaction.js?v=4'
                )
            )
        ));
        $app->render('admin/footer.php');
    }

    public function download($app)
    {
        $search = $app->request->get('search');
        $from = $app->request->get('from'); 
        $to = $app->request->get('to'); 
        $owner_id = $app->request->get('owner_id'); 
        $warnet_id = $app->request->get('warnet_id'); 
        $operator_id = $app->request->get('operator_id'); 
        $group_id = $app->request->get('group_id'); 
        $item_id = $app->request->get('item_id'); 
        $status = $app->request->get('status'); 
        $page = $app->request->get('page') ? $app->request->get('page') : 1;

        $owners = array();
        $warnets = array();
        $operators = array();
        $transactions = array();
        $pagination = array();

        $owners = Owner::orderBy('created_at', 'desc')
            ->get();

        if ($owner_id) {
            $warnets = Warnet::where('owner_id', '=', $owner_id)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($warnet_id) {
                $operators = Operator::where('warnet_id', '=', $warnet_id)
                    ->orderBy('created_at', 'desc')
                    ->get();
            }
        }

        $transaction_builder = Transaction::query();

        if ($search) {
            $transaction_builder->where(function($query) use($search) {
                $query->orWhere('phone', 'LIKE', "%$search%");
                $query->orWhere('pln_id', 'LIKE', "%$search%");
                $query->orWhere('supplier_item', 'LIKE', "%$search%");
            });
            $query_params['search'] = $search;
        }
        if ($from) {
            $transaction_builder->where('init_time', '>=', $from." 00:00:00");
        }
        if ($to) {
            $transaction_builder->where('init_time', '<=', $to." 23:59:59");
        }
        if ($owner_id) {
            $transaction_builder->where('owner_id', '=', $owner_id);
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
            'Init Time',
            'Confirm Time',
            'Price',
            'Sale Price',
            'Group',
            'Item',
            'Owner',
            'Warnet',
            'Operator',
            'Customer Phone',
            'PLN ID',
            'Item Content',
            'Status',
            'Supplier Trx ID'
        );

        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename='uwarnet-transaction-" . date("Y-m-d_H-i-s") . ".csv");
        header("Pragma: no-cache");
        header("Expires: 0");
            
        $handle = fopen("php://output", "w");
        fputcsv($handle, $headers);

        $transaction_builder->chunk(200, function($rows) use(&$handle) {
            foreach ($rows as &$transaction) {
                switch ($transaction->status) {
                    case 1:
                        $status = 'Init';
                        break;
                    case 2:
                        $status = 'Success';
                        break;
                    case 3:
                        $status = 'Failed';
                        break;
                    case 4:
                        $status = 'Expired';
                        break;
                }
                $item = '';
                if ($transaction->group_id == 19 && $transaction->supplier_item) {
                    $supplier_item_keys = array('token');
                    $supplier_item = json_decode($transaction->supplier_item);
                    if (count($supplier_item)) foreach ($supplier_item as $property) {
                        if (in_array($property->name, $supplier_item_keys)) {
                            $item = $property->value;
                        }
                    }
                }
                // $supplier_trx_id = '';
                // if ($transaction->status == 2 && !$transaction->supplier_item) {
                //     $supplier_trx_id = $transaction->supplier_trx_id;
                // }
                fputcsv($handle, array(
                    $transaction->init_time,
                    $transaction->confirm_time,
                    $transaction->price,
                    $transaction->sale_price,
                    $transaction->group->name,
                    $transaction->item->name,
                    $transaction->owner->name,
                    $transaction->warnet->name,
                    $transaction->operator->username,
                    $transaction->phone,
                    $transaction->pln_id,
                    $item,
                    $status,
                    $transaction->supplier_trx_id
                ));
            }
        });
        
        fclose($handle);
    }

    public function view_item($app, $id)
    {
        $id = $app->request->post('id');
        $password = $app->request->post('password');

        $email = $_SESSION['admin_email'];
        $admin = Admin::whereEmail($email)->whereActive(1)->first();

        $bcrypt = new \Bcrypt\Bcrypt();

        if ($bcrypt->verify($password, $admin->password)) {
            $transaction = Transaction::find($id);
            $result = new \stdClass();
            $result->status = true;
            $itemString = '';
            $itemElements = json_decode($transaction->supplier_item);
            if ($itemElements) foreach ($itemElements as $element) {
                $itemString .= $element->name." = ".$element->value."\n\r";
            }
            $result->item = $itemString;
        } else {
            $result = new \stdClass();
            $result->status = false;
            $result->code = 'password';
        }

        $app->response->headers->set('Content-type', 'application/json');
        echo json_encode($result);
    }

    public function resend_sms($app, $id)
    {
        $id = $app->request->post('id');
        $password = $app->request->post('password');
        $phone = $app->request->post('phone');

        $email = $_SESSION['admin_email'];
        $admin = Admin::whereEmail($email)->whereActive(1)->first();

        $bcrypt = new \Bcrypt\Bcrypt();

        if ($bcrypt->verify($password, $admin->password)) {
            if ($phone) {
                $transaction = Transaction::find($id);
                $item = Item::find($transaction->item_id);
                $supplier_item = json_decode($transaction->supplier_item);

                $sms_message = "[UWARNET] Pembelian Anda: ".$item->name.". ";
                if ($supplier_item) foreach ($supplier_item as $property) {
                    $sms_message .= $property->name.":".$property->value;
                }
                
                $i = 1;
                $sms_api_status = false;
                $sms = null;
                while ($i <= 5 && $sms_api_status === false) {
                    $sms = Sms::send($app, $phone, $sms_message);
                    if ($sms->status) {
                        $sms_api_status = true;
                    }
                    $i++;
                }

                if ($sms && $sms->status) {
                    $transaction->sms_delivery_status = 1;
                    $transaction->phone = $phone;
                    $transaction->sms_message_id = $sms->payload->{'message-id'};
                    $transaction->save();

                    $result = new \stdClass();
                    $result->status = true;
                } else {
                    $result = new \stdClass();
                    $result->status = false;
                    $result->code = 'failed';
                }
            } else {
                $result = new \stdClass();
                $result->status = false;
                $result->code = 'phone';
            }
        } else {
            $result = new \stdClass();
            $result->status = false;
            $result->code = 'password';
        }

        $app->response->headers->set('Content-type', 'application/json');
        echo json_encode($result);
    }
}