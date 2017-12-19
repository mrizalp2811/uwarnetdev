<?php

namespace App\Controller\Api;

use App\SlimC;
use App\Model\IndiHome;
use App\Model\Item;
use App\Model\Operator;
use App\Model\OperatorSession;
use App\Model\Supplier;
use App\Model\Transaction;
use App\Model\WarnetWallet;
use App\Model\Wallet;
use App\Model\Sms;
use App\Model\MailQueue;
use Slim\Http\Request;
use Slim\Http\Response;

class DesktopController extends BaseApiController
{
    private $_balance_limit = 100000;

    function getRoutes() {
        return array(
            'post' => array(
                "/confirm" => array('confirm')
            )
        );
    }

    public function confirm(SlimC $app)
    {
        $request = $app->request;
        $response = $app->response;
        $username = $request->post('username');
        $unique_id = $request->post('unique_id');
        $trx_id = $request->post('trx_id');
        $phone = $request->post('phone');
        $signature = $request->post('signature');

        if ($phone) {
            $check_required_query_params = $this->check_required_query_params(array($username, $unique_id, $trx_id, $phone, $signature));
            $check_signature_payload = array($username, $unique_id, $trx_id, $phone);
        } else {
            $check_required_query_params = $this->check_required_query_params(array($username, $unique_id, $trx_id, $signature));
            $check_signature_payload = array($username, $unique_id, $trx_id);
        }

        if ($check_required_query_params) {
            $operator = Operator::whereUsername($username)->first();
            if ($operator) {
                if ($operator->active && $operator->warnet->active && $operator->warnet->owner->active) {
                    $operator_session = OperatorSession::where('operator_id', '=', $operator->id)
                        ->where('unique_id', '=', $unique_id)
                        ->first();
                    if ($operator_session) {
                        if ($this->check_signature($check_signature_payload, $operator_session->token, $this->getApp()->config('app_key'), $signature)) {
                            if ($operator_session->approved) {
                                $transaction = Transaction::where('trx_id', '=', $trx_id)->first();
                                if ($transaction && $transaction->status == 1 && $transaction->operator_id == $operator->id && $transaction->device_id == $unique_id) {
                                    $item = Item::find($transaction->item_id);

                                    $warnet_wallet = WarnetWallet::where('warnet_id', '=', $operator->warnet->id)->first();
                                    if ($warnet_wallet) {
                                        $wallet = Wallet::find($warnet_wallet->wallet_id);
                                        if ($wallet && $wallet->owner_id == $operator->warnet->owner->id) {
                                            if ($wallet->active) {
                                                $payment_ref_no = $this->generate_payment_ref_no();

                                                $payment_result = $wallet->charge($item->price, $payment_ref_no);
                                                if ($payment_result->status === true) {
                                                    $limited = $wallet->balance() < $this->_balance_limit ? true : false;

                                                    $transaction->status = 2;
                                                    $transaction->confirm_time = date('Y-m-d H:i:s');
                                                    $transaction->phone = $phone;
                                                    $transaction->wallet_id = $wallet->id;
                                                    $transaction->payment_trx_id = $payment_result->payload->trx_id;
                                                    $transaction->payment_ref_no = $payment_ref_no;

                                                    $this->queue_mail_balance_limited($operator->warnet->owner, $payment_result->payload->balance);

                                                    if ($transaction->save()) {
                                                        $supplier = Supplier::whereActive(1)->find($item->supplier_id);
                                                        $postfields = array(
                                                            'partner_id' => $supplier->identity,
                                                            'trx_id' => $transaction->supplier_trx_id,
                                                            'ref_no' => $transaction->supplier_ref_no,
                                                            'status' => 1
                                                        );

                                                        $supplier_item = $supplier->retrieve($postfields, $this->getApp());
                                                        $transaction->supplier_item = json_encode($supplier_item);
                                                        $transaction->save();
                                                        
                                                        if ($phone) {
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
                                                                $transaction->sms_message_id = $sms->payload->{'message-id'};
                                                                $transaction->save();
                                                            }
                                                        }

                                                        $this->send_response(true, array(
                                                            'item' => $supplier_item,
                                                            'limited' => $limited
                                                        ));
                                                    } else {
                                                        $this->send_response(false, array(
                                                            'code' => "E504",
                                                            'message' => "Failed to write transaction"
                                                        ));
                                                    }
                                                } else {
                                                    $this->send_response(false, array(
                                                        'code' => "E112",
                                                        'message' => $payment_result->payload->error_message
                                                    ));
                                                }   
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
                                        'code' => "E110",
                                        'message' => "Transaction not found"
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
                        'code' => "E113",
                        'message' => "Account not active"
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

    function generate_payment_ref_no() {
        return md5(uniqid(rand(), TRUE));
    }

    private function queue_mail_balance_limited($owner, $balance)
    {
        if ($balance < $this->_balance_limit) {
            if ($owner->warning == 0) {
                $mail_queue = new MailQueue;
                $mail_queue->email = $owner->email;
                $mail_queue->name = $owner->name;
                $mail_queue->type = $mail_queue->types['USER_BALANCE_LIMITED'];
                $mail_queue->params = json_encode(array(
                    'link' => 'https://uwarnet.id/wallet'
                ));
                $mail_queue->save();

                $owner->warning = 1;
                $owner->save();
            }
        }
    }
}