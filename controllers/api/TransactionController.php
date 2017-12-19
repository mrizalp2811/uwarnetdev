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

class TransactionController extends BaseApiController
{
    private $_balance_limit = 100000;

    function getRoutes() {
        return array(
            'post' => array(
                "/init" => array('init'), 
                "/confirm" => array('confirm')
            )
        );
    }

    function init(SlimC $app) {
        $request = $app->request;
        $response = $app->response;
        $username = $request->post('username');
        $unique_id = $request->post('unique_id');
        $item_id = $request->post('item_id');
        $ref_no = $request->post('ref_no');
        $signature = $request->post('signature');

        if ($this->check_required_query_params(array($username, $unique_id, $item_id, $ref_no, $signature))) {
            $operator = Operator::whereUsername($username)->first();
            if ($operator) {
                if ($operator->active && $operator->warnet->active && $operator->warnet->owner->active) {
                    $operator_session = OperatorSession::where('operator_id', '=', $operator->id)
                        ->where('unique_id', '=', $unique_id)
                        ->first();
                    if ($operator_session) {
                        $payload = array($username, $unique_id, $item_id, $ref_no);
                        if ($this->check_signature($payload, $operator_session->token, $this->getApp()->config('app_key'), $signature)) {
                            if ($operator_session->approved) {
                                if ($item = Item::whereId($item_id)->whereActive(1)->first()) {
                                    $ref_no = substr($ref_no, 0, 50);
                                    $transaction = Transaction::where('ref_no', '=', $ref_no)->first();
                                    if (! $transaction) {
                                        if ($supplier = Supplier::whereActive(1)->find($item->supplier_id)) {
                                            $supplier_ref_no = $this->generate_supplier_ref_no();
                                            $postfields = array(
                                                'partner_id' => $supplier->identity,
                                                'ref_no' => $supplier_ref_no,
                                                'item_code' => $item->code,
                                                'timeout' => 600
                                            );

                                            $content = implode("", array_values($postfields));
                                            $content .= $supplier->token;

                                            $signature = md5($content);
                                            $postfields['signature'] = $signature;

                                            $result = $supplier->inquiry($supplier->inquiry_url, $postfields, $this->getApp());
                                            if ($result->status === true) {
                                                $trx_id = $this->generate_trx_id(uniqid());
                                                $owner_id = $operator->warnet->owner->id;
                                                $sale_price = $item->owners()->where('owner_id', $owner_id)->first() ? $item->owners()->where('owner_id', $owner_id)->first()->pivot->sale_price : $item->price;

                                                $transaction = new Transaction;
                                                $transaction->device_id = $unique_id;
                                                $transaction->operator_id = $operator->id;
                                                $transaction->warnet_id = $operator->warnet->id;
                                                $transaction->owner_id = $owner_id;
                                                $transaction->trx_id = $trx_id;
                                                $transaction->ref_no = $ref_no;
                                                $transaction->item_id = $item->id;
                                                $transaction->group_id = $item->group->id;
                                                $transaction->price = $item->price;
                                                $transaction->sale_price = $sale_price;
                                                $transaction->init_time = date('Y-m-d H:i:s');
                                                $transaction->status = 1;
                                                $transaction->supplier_trx_id = $result->payload->trx_id;
                                                $transaction->supplier_ref_no = $supplier_ref_no;

                                                if ($transaction->save()) {
                                                    $this->send_response(true, array(
                                                        'trx_id' => $trx_id
                                                    ));
                                                } else {
                                                    $this->send_response(false, array(
                                                        'code' => "E504",
                                                        'message' => "Failed to write transaction"
                                                    ));
                                                }
                                            } else {
                                                $this->send_response(false, array(
                                                    'code' => "E301",
                                                    'message' => "Item not available"
                                                ));
                                            }
                                        } else {
                                            $this->send_response(false, array(
                                                'code' => "E301",
                                                'message' => "Item not available"
                                            ));
                                        }
                                    } else {
                                        $this->send_response(false, array(
                                            'code' => "E106",
                                            'message' => "Transaction exist"
                                        ));
                                    }
                                } else {
                                    $this->send_response(false, array(
                                        'code' => "E108",
                                        'message' => "Item not found"
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

    function confirm(SlimC $app) {
        $request = $app->request;
        $response = $app->response;
        $username = $request->post('username');
        $unique_id = $request->post('unique_id');
        $trx_id = $request->post('trx_id');
        $phone = $request->post('phone');
        $signature = $request->post('signature');

        if ($this->check_required_query_params(array($username, $unique_id, $trx_id, $phone, $signature))) {
            $operator = Operator::whereUsername($username)->first();
            if ($operator) {
                if ($operator->active && $operator->warnet->active && $operator->warnet->owner->active) {
                    $operator_session = OperatorSession::where('operator_id', '=', $operator->id)
                        ->where('unique_id', '=', $unique_id)
                        ->first();
                    if ($operator_session) {
                        $payload = array($username, $unique_id, $trx_id, $phone);
                        if ($this->check_signature($payload, $operator_session->token, $this->getApp()->config('app_key'), $signature)) {
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

    function generate_trx_id($seed) {
        return time().hash('sha1', $seed);
    }

    function generate_supplier_ref_no() {
        return md5(uniqid(rand(), TRUE));
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