<?php

namespace App\Controller\Api;

use App\SlimC;
use App\Model\Topup;
use App\Model\Owner;
use App\Model\MailQueue;
use Slim\Http\Request;
use Slim\Http\Response;

class TopupController extends BaseApiController
{
    private $app_token = 'welehweleh';

    function getRoutes()
    {
        return array(
            'post' => array(
                '/banktransfer/callback' => 'banktransfer_callback'
            )
        );
    }

    public function banktransfer_callback(SlimC $app) {
        $request = $app->request;
        $response = $app->response;
        $status = $request->post('status');
        $trx_id = $request->post('trx_id');
        $amount = $request->post('amount');
        $balance = $request->post('balance');
        $signature = $request->post('signature');

        if ($this->check_required_query_params(array($status, $trx_id, $amount, $balance))) {
            $payload = array($status, $trx_id, $amount, $balance);
            if ($this->check_signature($payload, $this->app_token, $this->getApp()->config('app_key'), $signature)) {
                $topup = Topup::where('trx_id', $trx_id)->first();
                if ($topup->status == 1) {
                    if ($status == 'success') {
                        $topup->status = 2;
                        $topup->balance_after = $balance;
                    } else if ($status == 'failed') {
                        $topup->status = 3;
                    }
                    if ($topup->save()) {
                        if ($topup->status == 2) {
                            $owner = Owner::find($topup->wallet->owner_id);
                            if ($owner) {
                                $this->queue_mail_topup_success($owner, $topup);
                            }
                        }

                        $this->send_response(true);
                    } else {
                        $this->send_response(false, array(
                            'code' => "E505",
                            'message' => "Failed to update topup"
                        ));
                    }
                } else {
                    $this->send_response(false, array(
                        'code' => "E506",
                        'message' => "Topup already confirmed"
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
                'code' => "E101",
                'message' => "Missing required params"
            ));
        }
    }

    function check_signature($payload, $token, $app_key, $signature) {
        $generated_signature = md5(implode("", $payload) . $token);
        if ($generated_signature === $signature) return true;
        return false;
    }

    private function queue_mail_topup_success($owner, $topup)
    {
        switch ($topup->type) {
            case 'indihome':
                $topup_type = 'Tagihan IndiHome';
                break;
            case 'tcash':
                $topup_type = 'tcash';
                break;
            case 'transfer':
                $topup_type = 'Bank Transfer';
                break;
        }

        $mail_queue = new MailQueue;
        $mail_queue->email = $owner->email;
        $mail_queue->name = $owner->name;
        $mail_queue->type = $mail_queue->types['USER_TOPUP_SUCCESS'];
        $mail_queue->params = json_encode(array(
            'type' => $topup_type,
            'amount' => "Rp.".number_format($topup->amount, 0, ',', '.'),
            'topup_time' => strftime("%B %e, %Y @%H:%M", strtotime($topup->updated_at)),
            'link' => 'https://uwarnet.id/wallet'
        ));
        $mail_queue->save();

        $owner->warning = 0;
        $owner->save();
    }
}