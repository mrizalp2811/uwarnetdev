<?php

namespace App\Controller\Api;

use App\Model\Operator;
use App\Model\OperatorSession;
use App\SlimC;
use Slim\Http\Request;
use Slim\Http\Response;

class CheckDeviceController extends BaseApiController
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
                            $this->send_response(true);
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