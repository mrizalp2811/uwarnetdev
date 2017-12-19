<?php

namespace App\Controller\Api;

use App\SlimC;
use App\Model\Operator;
use App\Model\OperatorSession;
use Slim\Http\Request;
use Slim\Http\Response;

class PinController extends BaseApiController
{
    function getRoutes() {
        return array(
            "post" => array(
                "" => "index"
            )
        );
    }

    function index(SlimC $app) {
        $request = $app->request;
        $response = $app->response;
        $username = $request->post('username');
        $password = $request->post('password');
        $unique_id = $request->post('unique_id');
        $pin = $request->post('pin');
        $signature = $request->post('signature');

        if ($this->check_required_query_params(array($username, $password, $unique_id, $pin, $signature))) {
            $operator = Operator::whereUsername($username)->whereActive(1)->first();
            if ($operator) {
                $operator_session = OperatorSession::where('operator_id', '=', $operator->id)
                    ->where('unique_id', '=', $unique_id)
                    ->first();
                if ($operator_session) {
                    $payload = array($username, $password, $unique_id, $pin);
                    if ($this->check_signature($payload, $operator_session->token, $this->getApp()->config('app_key'), $signature)) {
                        $bcrypt = new \Bcrypt\Bcrypt();

                        if ($bcrypt->verify($password, $operator->password)) {
                            if (ctype_digit($pin) && strlen($pin) == 4) {
                                $operator_session->pin = $pin;
                                if ($operator_session->save()) {
                                    $this->send_response(true);
                                } else {
                                    $this->send_response(false, array(
                                        'code' => "E507",
                                        'message' => "Failed to write pin"
                                    ));
                                }
                            } else {
                                $this->send_response(false, array(
                                    'code' => "E116",
                                    'message' => "Wrong pin format"
                                ));
                            }
                        } else {
                            $this->send_response(false, array(
                                'code' => "E103",
                                'message' => "Password not match"
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