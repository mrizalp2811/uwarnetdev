<?php

namespace App\Controller\Admin;

use App\Controller\Admin\BaseAdminController;
use App\Model\Owner;
use App\Model\Topup;
use App\Model\Wallet;
use App\Library\Pagination;

class TopupController extends BaseAdminController
{
    function getRoutes() {
        $routes = array(
            'get' => array(
                '/' => array('view', $this->auth($this->getApp())),
                '/download' => array('download', $this->auth($this->getApp()))
            )
        );
        return $routes;
    }

    public function view($app)
    {
        $search = $app->request->get('search');
        $from = $app->request->get('from'); 
        $to = $app->request->get('to'); 
        $owner_id = $app->request->get('owner_id'); 
        $type = $app->request->get('type'); 
        $status = $app->request->get('status');

        $owners = array();
        $topups = array();
        $pagination = null;

        $owners = Owner::select('owner.*')
            ->where('owner.active', 1)
            ->orderBy('owner.name', 'asc')
            ->join('wallet', 'wallet.owner_id', '=', 'owner.id')
            ->get();

        $topup_builder = Topup::query();

        $url = '/topup';
        $query_params = array();

        if ($search) {
            $topup_builder->where(function($query) use($search) {
                $query->orWhere('info', 'LIKE', "%$search%");
            });
            $query_params['search'] = $search;
        }
        if ($from) {
            $topup_builder->where('created_at', '>=', $from." 00:00:00");
            $query_params['from'] = $from;
        }
        if ($to) {
            $topup_builder->where('created_at', '<=', $to." 23:59:59");
            $query_params['to'] = $to;
        }
        if ($owner_id) {
            $owner = Owner::find($owner_id);
            $wallet_ids = $owner->wallets()->lists('id');

            $topup_builder->whereIn('wallet_id', $wallet_ids);
            $query_params['owner_id'] = $owner_id;
        }
        if ($type) {
            $topup_builder->where('type', '=', $type);
            $query_params['type'] = $type;
        }
        if ($status) {
            $topup_builder->where('status', '=', $status);
            $query_params['status'] = $status;
        }

        $number_of_items = $topup_builder->count();
        $limit = 100;
        $number_of_page = ceil($number_of_items / $limit);
        $page = $app->request->get('page') ? $app->request->get('page') : $number_of_page;
        $offset = ($page - 1) * $limit;
        $from_count = $offset + 1;
        $to_count = $offset + $limit;
        if ($number_of_items < $limit || $to_count > $number_of_items) {
            $to_count = $number_of_items;
        }
        $total_topup = $topup_builder->sum('amount');
        $topups = $topup_builder->forPage($page, $limit)->get();

        if (count($topups)) {
            $pagination = Pagination::createHTML($url, $query_params, $number_of_page, $page, null, $from_count, $to_count, $number_of_items);
        }

        $queries = $app->request->get();
        $is_query_exist = $queries && (!(count($queries == 1) && array_key_exists('page', $queries))) ? true : false;

        $app->render('admin/header.php', array(
            'name' => $_SESSION['admin_name']
        ));
        $app->render('admin/topup.php', array(
            'owners' => $owners,
            'topups' => $topups,
            'query' => array(
                'search' => $search,
                'from' => $from ? $from : null,
                'to' => $to ? $to : null,
                'owner_id' => $owner_id,
                'type' => $type,
                'status' => $status
            ),
            'filter' => $is_query_exist,
            'pagination' => $pagination,
            'offset' => $offset,
            'from_count' => $from_count,
            'to_count' => $to_count,
            'number_of_items' => $number_of_items,
            'total_topup' => $total_topup,
            'assets' => array(
                'js' => array(
                    'admin-topup.js?v=1'
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
        $type = $app->request->get('type'); 
        $status = $app->request->get('status'); 
        $page = $app->request->get('page') ? $app->request->get('page') : 1;

        $owners = array();
        $topups = array();
        $pagination = array();

        $owners = Owner::select('owner.*')
            ->where('owner.active', 1)
            ->orderBy('owner.name', 'asc')
            ->join('wallet', 'wallet.owner_id', '=', 'owner.id')
            ->get();

        $topup_builder = Topup::query();

        if ($search) {
            $topup_builder->where(function($query) use($search) {
                $query->orWhere('info', 'LIKE', "%$search%");
            });
            $query_params['search'] = $search;
        }
        if ($from) {
            $topup_builder->where('created_at', '>=', $from." 00:00:00");
        }
        if ($to) {
            $topup_builder->where('created_at', '<=', $to." 23:59:59");
        }
        if ($owner_id) {
            $owner = Owner::find($owner_id);
            $wallet_ids = $owner->wallets()->lists('id');

            $topup_builder->whereIn('wallet_id', $wallet_ids);
        }
        if ($type) {
            $topup_builder->where('type', '=', $type);
        }
        if ($status) {
            $topup_builder->where('status', '=', $status);
        }

        $headers = array(
            'Init Time',
            'Confirm Time',
            'Owner',
            'Payment Type',
            'Payment Info',
            'Amount (Rp)',
            'Balance After (Rp)',
            'Status'
        );

        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename='uwarnet-topup-" . date("Y-m-d_H-i-s") . ".csv");
        header("Pragma: no-cache");
        header("Expires: 0");
            
        $handle = fopen("php://output", "w");
        fputcsv($handle, $headers);

        $topup_builder->chunk(200, function($rows) use(&$handle) {
            foreach ($rows as &$topup) {
                switch ($topup->type) {
                    case 'indihome':
                        $type = 'Tagihan Indihome';
                        break;
                    case 'transfer':
                        $type = 'Bank Transfer';
                        break;
                    case 'tcash':
                        $type = 'tcash';
                        break;
                }
                switch ($topup->status) {
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
                $info = json_decode($topup->info);
                if ($info) {
                    switch ($topup->type) {
                        case 'indihome':
                            $payment_info = $info->phone;
                            break;
                        case 'transfer':
                            $payment_info = $info->data->payment_code;
                            break;
                        case 'tcash':
                            $payment_info = '';
                            break;
                        default:

                    }
                }
                fputcsv($handle, array(
                    $topup->created_at,
                    $topup->updated_at,
                    $topup->wallet->owner->name,
                    $type,
                    $payment_info,
                    $topup->amount,
                    $topup->balance_after,
                    $status
                ));
            }
        });
        
        fclose($handle);
    }
}