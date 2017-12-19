<?php

namespace App\Controller\Api;

use App\SlimC;
use App\Model\Operator;
use App\Model\OperatorSession;
use Slim\Http\Request;
use Slim\Http\Response;

class LoginController extends BaseApiController
{
    function getRoutes() {
        return array(
            'post' => array(
                '' => 'index'
            )
        );
    }

    public function index(SlimC $app) {
        $request = $app->request;
        $response = $app->response;
        $username = $request->post('username');
        $password = $request->post('password');
        $unique_id = $request->post('unique_id');

        if ($this->check_required_query_params(array($username, $password, $unique_id))) {
            $operator = Operator::whereUsername($username)->whereActive(1)->first();
            if ($operator) {
                $bcrypt = new \Bcrypt\Bcrypt();

                if ($bcrypt->verify($password, $operator->password)) {
                    $operator_session = OperatorSession::where('operator_id', '=', $operator->id)
                        ->where('unique_id', '=', $unique_id)
                        ->first();

                    if (! $operator_session) {
                        $operator_session = new OperatorSession;
                        $operator_session->operator_id = $operator->id;
                        $operator_session->warnet_id = $operator->warnet->id;
                        $operator_session->owner_id = $operator->warnet->owner->id;
                        $operator_session->unique_id = $unique_id;
                        if (! $operator_session->save()) {
                            $this->send_response(false, array(
                                'code' => "E502",
                                'message' => "Failed to write unique_id"
                            ));
                        }
                    }

                    $token = $this->generate_token(time());
                    $operator_session->token = $token;
                    if ($operator_session->save()) {
                        $this->send_response(true, array(
                            'token' => $token,
                            'pin' => $operator_session->pin
                        ));
                    } else {
                        $this->send_response(false, array(
                            'code' => "E501",
                            'message' => "Failed to write token"
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

    function generate_token($seed) {
        return hash('sha256', $seed);
    }

}