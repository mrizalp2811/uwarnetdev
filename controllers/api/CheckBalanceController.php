<?php

namespace App\Controller\Api;

use App\Model\Operator;
use App\Model\OperatorSession;
use App\Model\WarnetWallet;
use App\Model\Wallet;
use App\SlimC;
use Slim\Http\Request;
use Slim\Http\Response;

class CheckBalanceController extends BaseApiController
{
    function getRoutes() {
        return array(
            "post" => array("" => "index")
        );
    }

    function index(SlimC $app) {
        $request = $app->request;
        $response = $app->response;
        $username = $request->post('username');
        $unique_id = $request->post('unique_id');
        $signature = $request->post('signature');

        if ($this->check_required_query_params(array($username, $unique_id, $signature))) {
            $operator = Operator::whereUsername($username)->whereActive(1)->first();
            if ($operator) {
                $operator_session = OperatorSession::where('operator_id', '=', $operator->id)
                    ->where('unique_id', '=', $unique_id)
                    ->first();
                if ($operator_session) {
                    $payload = array($username, $unique_id);
                    if ($this->check_signature($payload, $operator_session->token, $this->getApp()->config('app_key'), $signature)) {
                        if ($operator_session->approved) {
                            $warnet_wallet = WarnetWallet::where('warnet_id', '=', $operator->warnet->id)->first();
                            if ($warnet_wallet) {
                                $wallet = Wallet::find($warnet_wallet->wallet_id);
                                if ($wallet && $wallet->owner_id == $operator->warnet->owner->id) {
                                    if ($wallet->active) {
                                        $balance = $wallet->balance();

                                        $this->send_response(true, array(
                                            'balance' => $balance
                                        ));
                                    } else {
                                        $this->send_response(false, array(
                                            'code' => "E115",
                                            'message' => "Payment not active"
                                        ));
                                    }
                                } else {
                                    $this->send_response(false, array(
                                        'code' => "E114",
                                        'message' => "Payment not found"
                                    ));
                                }
                            } else {
                                $this->send_response(false, array(
                                    'code' => "E114",
                                    'message' => "Payment not found"
                                ));
                            }
                        } else {
                            $this->send_response(false, array(
                                'code' => "E109",
                                'message' => "Device not approved"
                            ));
                        }
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