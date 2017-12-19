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
use App\Model\Pulsa;
use App\Model\MailQueue;
use App\Model\FcmPush;
use App\Model\DesktopPush;
use Slim\Http\Request;
use Slim\Http\Response;

class PulsaController extends BaseApiController
{
    private $_balance_limit = 100000;

    function getRoutes() {
        return array(
            'post' => array(
                "/init" => array('init'), 
                "/confirm" => array('confirm'),
                "/callback" => array('callback')
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
                                    $transaction = Transaction::where('ref_no', '=', $ref_no)->first();
                                    if (! $transaction) {
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

                                                    $transaction->phone = $phone;
                                                    $transaction->wallet_id = $wallet->id;
                                                    $transaction->payment_trx_id = $payment_result->payload->trx_id;
                                                    $transaction->payment_ref_no = $payment_ref_no;

                                                    $this->queue_mail_balance_limited($operator->warnet->owner, $payment_result->payload->balance);

                                                    if ($transaction->save()) {
                                                        $supplier_ref_no = $this->generate_supplier_ref_no();
                                                        $transaction->supplier_ref_no = $supplier_ref_no;
                                                        $transaction->save();

                                                        $postfields = array(
                                                            'receiver_number' => $phone,
                                                            'trx_id' => $transaction->supplier_ref_no,
                                                            'itemcode' => $item->code
                                                        );

                                                        $pulsa = new Pulsa;
                                                        $topup_result = $pulsa->inquiry($postfields, $this->getApp());
                                                        if ($topup_result->status) {
                                                            $transaction->supplier_trx_id = $topup_result->pulsa_trx_id;
                                                            $transaction->save();

                                                            $confirmfields = array('trx_id' => $transaction->supplier_ref_no);
                                                            $confirm_result = $pulsa->confirm($confirmfields, $this->getApp());

                                                            if ($confirm_result->status) {
                                                                $transaction->status = 2;
                                                                $transaction->confirm_time = date('Y-m-d H:i:s');
                                                                $transaction->save();

                                                                $this->send_response(true, array(
                                                                    'limited' => $limited
                                                                ));
                                                            } else {
                                                                if ($confirm_result->error == 'pending') {
                                                                    $this->send_response(false, array(
                                                                        'code' => "E303",
                                                                        'message' => "Pulsa result pending"
                                                                    ));
                                                                } else {
                                                                    $wallet->refund($transaction->payment_trx_id);
                                                                    $transaction->status = 3;
                                                                    $transaction->confirm_time = date('Y-m-d H:i:s');
                                                                    $transaction->save();

                                                                    $this->send_response(false, array(
                                                                        'code' => "E508",
                                                                        'message' => "Failed to top up pulsa"
                                                                    ));
                                                                }
                                                            }
                                                        } else {
                                                            $wallet->refund($transaction->payment_trx_id);
                                                            $transaction->status = 3;
                                                            $transaction->confirm_time = date('Y-m-d H:i:s');
                                                            $transaction->save();

                                                            $this->send_response(false, array(
                                                                'code' => "E508",
                                                                'message' => "Failed to topup pulsa"
                                                            ));
                                                        }
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

    public function callback(SlimC $app)
    {
        $request = $app->request;
        $response = $app->response;

        $body = $request->getBody();
        $body = json_decode($body);

        if (isset($body->result) && isset($body->t_id)) {
            $result = $body->result;
            $supplier_trx_id = $body->t_id;
            $transaction = Transaction::where('supplier_trx_id', $supplier_trx_id)->first();
            
            // if ($transaction && $transaction->status == 1) {
            if ($transaction) {
                $app->log->setEnabled(true);
                $app->log->info(json_encode(array(
                    'time' => date('Y-m-d H:i:s'),
                    'type' => 'pulsa_callback',
                    'payload' => $body
                )));
                $app->log->setEnabled(false);

                $item = Item::find($transaction->item_id);
                if ($result == true) {
                    $transaction->status = 2;
                    $transaction->confirm_time = date('Y-m-d H:i:s');
                    $transaction->save();
                } else {
                    $transaction->status = 3;
                    $transaction->confirm_time = date('Y-m-d H:i:s');
                    $wallet = Wallet::find($transaction->wallet_id);
                    $wallet->refund($transaction->payment_trx_id);
                    $transaction->save();
                }

                // push to app
                $operator_session = OperatorSession::where('operator_id', '=', $transaction->operator_id)
                    ->where('unique_id', $transaction->device_id)
                    ->first();
                if ($operator_session) {
                    $owner_id = $transaction->owner_id;
                    $item->price = $item->owners()->where('owner_id', $owner_id)->first() ? $item->owners()->where('owner_id', $owner_id)->first()->pivot->sale_price : $item->price;

                    $data = array(
                        "item_id" => $item->id,
                        "item_name" => $item->name,
                        "item_price" => $item->price,
                        "item_icon" => $item->icon,
                        "customer_phone" => $transaction->phone,
                        "group_id" => $item->group_id,
                        "group_type" => "pulsa"
                    );

                    if ($result == true) {
                        $data['status'] = true;
                        $data['customer_phone'] = $transaction->phone;
                    } else {
                        $data['status'] = false;
                        $data['error_code'] = 'E508';
                        $data['error_message'] = 'Failed to topup pulsa';
                    }

                    if ($operator_session->fcm_token) {
                        FcmPush::send($app, $operator_session->fcm_token, $data);
                    }

                    if ($operator_session->device_type == 'desktop') {
                        DesktopPush::send($app, $operator_session->unique_id, array(
                            "data" => $data
                        ));
                    }
                }

                echo json_encode((object) array(
                    'result' => true
                ));
                // $this->send_response(true);
            } else {
                $this->send_response(false, array(
                    'code' => "E404",
                    'message' => "Transaction not found"
                ));
            }
        } else {
            $this->send_response(false, array(
                'code' => "E401",
                'message' => "Missing params"
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