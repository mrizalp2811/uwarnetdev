<?php

namespace App\Controller\Api;

use App\SlimC;
use App\Model\Operator;
use App\Model\OperatorSession;
use Slim\Http\Request;
use Slim\Http\Response;

class SignController extends BaseApiController
{
    function getRoutes() {
        return array(
            "post"=>array(
                "/device_info" => "device_info",
                "/voucher/groups" => "voucherGroups",
                "/voucher/items" => "voucherItems",
                "/check_token" => "checkToken",
                "/check_device" => "checkDevice",
                "/transaction/init" => "transactionInit",
                "/transaction/confirm" => "transactionConfirm",
                "/report" => "report"
            )
        );
    }

    function device_info(SlimC $app) {
        $request = $app->request;
        $response = $app->response;
        $username = $request->post('username');

        $unique_id = $request->post('unique_id');
        $brand = $request->post('brand');
        $device = $request->post('device');
        $model = $request->post('model');
        $version_release = $request->post('version_release');

        if ($this->check_required_query_params(array($username, $unique_id, $brand, $device, $model, $version_release))){
            $operator = Operator::whereUsername($username)->first();
            if ($operator) {
                $operator_session = OperatorSession::where('operator_id', '=', $operator->id)
                    ->where('unique_id', '=', $unique_id)
                    ->first();
                if ($operator_session) {
                    $payload = array($username, $unique_id, $brand, $device, $model, $version_release);

                    $signature = hash('sha256', implode("", $payload) . $operator_session->token . $this->getApp()->config('app_key'));
                    $this->send_response(true, array(
                        'signature' => $signature
                    ));
                }else{
                    $this->send_response(false, array(
                        'code' => "E105",
                        'message' => "Device not found"
                    ));
                }
            }else{
                $this->send_response(false, array(
                    'code' => "E102",
                    'message' => "User not found"
                ));
            }
        }else{
            $this->send_response(false, array(
                'code' => "E101",
                'message' => "Missing required params"
            ));
        }

    }

    function voucherGroups(SlimC $app) {
        $request = $app->request;
        $response = $app->response;
        $username = $request->post('username');
        $unique_id = $request->post('unique_id');

        $operator = Operator::whereUsername($username)->first();
        $operator_session = OperatorSession::where('operator_id', '=', $operator->id)
            ->where('unique_id', '=', $unique_id)
            ->first();
        $payload = array($username, $unique_id);

        $signature = hash('sha256', implode("", $payload) . $operator_session->token . $this->getApp()->config('app_key'));
        $this->send_response(true, array(
            'signature' => $signature
        ));
    }

    function voucherItems(SlimC $app) {
        $request = $app->request;
        $response = $app->response;
        $username = $request->post('username');
        $unique_id = $request->post('unique_id');
        $group_id = $request->post('group_id');

        $operator = Operator::whereUsername($username)->first();
        $operator_session = OperatorSession::where('operator_id', '=', $operator->id)
            ->where('unique_id', '=', $unique_id)
            ->first();
        $payload = array($username, $unique_id, $group_id);

        $signature = hash('sha256', implode("", $payload) . $operator_session->token . $this->getApp()->config('app_key'));
        $this->send_response(true, array(
            'signature' => $signature
        ));
    }
    
    function checkToken(SlimC $app) {
        $request = $app->request;
        $response = $app->response;
        $username = $request->post('username');
        $unique_id = $request->post('unique_id');

        $operator = Operator::whereUsername($username)->first();
        $operator_session = OperatorSession::where('operator_id', '=', $operator->id)
            ->where('unique_id', '=', $unique_id)
            ->first();
        $payload = array($username, $unique_id);

        $signature = hash('sha256', implode("", $payload) . $operator_session->token . $this->getApp()->config('app_key'));
        $this->send_response(true, array(
            'signature' => $signature
        ));
    }

    function checkDevice(SlimC $app) {
        $request = $app->request;
        $response = $app->response;
        $username = $request->post('username');
        $unique_id = $request->post('unique_id');

        $operator = Operator::whereUsername($username)->first();
        $operator_session = OperatorSession::where('operator_id', '=', $operator->id)
            ->where('unique_id', '=', $unique_id)
            ->first();
        $payload = array($username, $unique_id);

        $signature = hash('sha256', implode("", $payload) . $operator_session->token . $this->getApp()->config('app_key'));
        $this->send_response(true, array(
            'signature' => $signature
        ));
    }
    
    function transactionInit(SlimC $app) {
        $request = $app->request;
        $response = $app->response;
        $username = $request->post('username');
        $unique_id = $request->post('unique_id');
        $item_id = $request->post('item_id');
        $ref_no = $request->post('ref_no');

        $operator = Operator::whereUsername($username)->first();
        $operator_session = OperatorSession::where('operator_id', '=', $operator->id)
            ->where('unique_id', '=', $unique_id)
            ->first();
        $payload = array($username, $unique_id, $item_id, $ref_no);

        $signature = hash('sha256', implode("", $payload) . $operator_session->token . $this->getApp()->config('app_key'));
        $this->send_response(true, array(
            'signature' => $signature
        ));
    }
    
    function transactionConfirm(SlimC $app) {
        $request = $app->request;
        $response = $app->response;
        $username = $request->post('username');
        $unique_id = $request->post('unique_id');
        $trx_id = $request->post('trx_id');
        $phone = $request->post('phone');

        $operator = Operator::whereUsername($username)->first();
        $operator_session = OperatorSession::where('operator_id', '=', $operator->id)
            ->where('unique_id', '=', $unique_id)
            ->first();
        $payload = array($username, $unique_id, $trx_id, $phone);

        $signature = hash('sha256', implode("", $payload) . $operator_session->token . $this->getApp()->config('app_key'));
        $this->send_response(true, array(
            'signature' => $signature
        ));
    }
    
    function report(SlimC $app) {
        $request = $app->request;
        $response = $app->response;
        $username = $request->post('username');
        $unique_id = $request->post('unique_id');
        $date = $request->post('date');

        $operator = Operator::whereUsername($username)->first();
        $operator_session = OperatorSession::where('operator_id', '=', $operator->id)
            ->where('unique_id', '=', $unique_id)
            ->first();
        $payload = array($username, $unique_id, $date);

        $signature = hash('sha256', implode("", $payload) . $operator_session->token . $this->getApp()->config('app_key'));
        $this->send_response(true, array(
            'signature' => $signature
        ));
    }

}