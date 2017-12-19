<?php

namespace App\Controller\Api;

use App\SlimC;
use App\Model\Operator;
use App\Model\OperatorSession;
use App\Model\Transaction;
use Slim\Http\Request;
use Slim\Http\Response;

class ReportController extends BaseApiController
{
    function getRoutes()
    {
        return array("post"=>array(""=>"index"));
    }

    function index (SlimC $app){
        $request = $app->request;
        $response = $app->response;
        $username = $request->post('username');
        $unique_id = $request->post('unique_id');
        $date = $request->post('date');
        $signature = $request->post('signature');

        if ($this->check_required_query_params(array($username, $unique_id, $date, $signature))) {
            $operator = Operator::whereUsername($username)->whereActive(1)->first();
            if ($operator) {
                $operator_session = OperatorSession::where('operator_id', '=', $operator->id)
                    ->where('unique_id', '=', $unique_id)
                    ->first();
                if ($operator_session) {
                    $payload = array($username, $unique_id, $date);
                    if ($this->check_signature($payload, $operator_session->token, $this->getApp()->config('app_key'), $signature)) {
                        $date = date('Y-m-d', strtotime($date));
                        $transactions = Transaction::whereStatus(2)->where('operator_id', '=', $operator->id)
                            ->where('confirm_time', '>=', $date." 00:00:00")
                            ->where('confirm_time', '<=', $date." 23:59:59")
                            ->orderBy('confirm_time', 'asc')
                            ->get();

                        $output = array();
                        if ($transactions) {
                            foreach ($transactions as $transaction) {
                                $output[] = (object) array(
                                    'item_name' => $transaction->item->name,
                                    'group_name' => $transaction->item->group->name,
                                    'price' => $transaction->sale_price,
                                    'sms_delivery_status' => $transaction->sms_delivery_status,
                                    'time' => date('H:i', strtotime($transaction->confirm_time)),
                                    'ref_no' => substr($transaction->ref_no, 0, 6)
                                );
                            }
                        } else {
                            $output = array();
                        }
                        $this->send_response(true, array(
                            'transactions' => $output
                        ));
                    } else {
                        $this->send_response(false, array(
                            'code' => "E104",
                            'message' => "Signature failed"
                        ));
                    }
                } else {
                    $this->send_response(false, array(
                        'code' => "E105",
                        'message' => "Device not found"
                    ));
                }
            } else {
                $this->send_response(false, array(
                    'code' => "E102",
                    'message' => "User not found"
                ));
            }
        } else {
            $this->send_response(false, array(
                'code' => "E101",
                'message' => "Missing required params"
            ));
        }
    }
}