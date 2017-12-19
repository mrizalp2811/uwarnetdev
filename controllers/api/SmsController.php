<?php

namespace App\Controller\Api;

use App\SlimC;
use App\Model\Transaction;
use Slim\Http\Request;
use Slim\Http\Response;

class SmsController extends BaseApiController
{
    function getRoutes() {
        return array(
            'post' => array(
                '/nexmo/callback' => 'nexmo_callback'
            )
        );
    }

    public function nexmo_callback(SlimC $app) {
        $request = $app->request;
        $response = $app->response;

        parse_str($request->getBody(), $info);
        $info = (object) $info;
        if ($info) {
            if (isset($info->status) && $info->status == 'delivered') {
                $sms_delivery_status = 2;
            } else if (isset($info->status) && $info->status == 'expired') {
                $sms_delivery_status = 3;
            } else if (isset($info->status) && $info->status == 'failed') {
                $sms_delivery_status = 4;
            } else if (isset($info->status) && $info->status == 'rejected') {
                $sms_delivery_status = 5;
            } else if (isset($info->status) && $info->status == 'accepted') {
                $sms_delivery_status = 6;
            } else if (isset($info->status) && $info->status == 'buffered') {
                $sms_delivery_status = 7;
            } else if (isset($info->status) && $info->status == 'unknown') {
                $sms_delivery_status = 8;
            }

            $transaction = Transaction::where('sms_message_id', $info->messageId)->first();
            if ($transaction) {
                $transaction->sms_delivery_status = $sms_delivery_status;
                $transaction->save();
            }
        }
        header("HTTP/1.1 200 OK");
    }
}