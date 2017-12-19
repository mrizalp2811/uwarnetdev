<?php

namespace App\Controller\Api;

use App\SlimC;
use App\Controller\BaseController;
use App\Model\Operator;
use App\Model\OperatorSession;
use Slim\Http\Request;
use Slim\Http\Response;

class DeviceInfoController extends BaseApiController
{
    function getRoutes() {
        return array(
            'post' => array(
                '/' => 'index',
                '/desktop' => 'desktop'
            )
        );
    }

    function index(SlimC $app) {
        $request = $app->request;
        $response = $app->response;
        $username = $request->post('username');
        $unique_id = $request->post('unique_id');
        $brand = $request->post('brand');
        $device = $request->post('device');
        $model = $request->post('model');
        $version_release = $request->post('version_release');
        $fcm_token = $request->post('fcm_token');
        $signature = $request->post('signature');

        if ($this->check_required_query_params(array($username, $unique_id, $signature))) {
            $operator = Operator::whereUsername($username)->whereActive(1)->first();
            if ($operator) {
                $operator_session = OperatorSession::where('operator_id', '=', $operator->id)
                    ->where('unique_id', '=', $unique_id)
                    ->first();
                if ($operator_session) {
                    $payload = array($username, $unique_id, $brand, $device, $model, $version_release);
                    if ($this->check_signature($payload, $operator_session->token, $this->getApp()->config('app_key'), $signature)) {
                        $operator_session->brand = $brand;
                        $operator_session->device = $device;
                        $operator_session->model = $model;
                        $operator_session->version_release = $version_release;
                        if ($fcm_token) {
                            $operator_session->fcm_token = $fcm_token;
                        }

                        if ($operator_session->save()) {
                            $this->send_response(true);
                        } else {
                            $this->send_response(false, array(
                                'code' => "E503",
                                'message' => "Failed to write device info"
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

    function desktop(SlimC $app) {
        $request = $app->request;
        $response = $app->response;
        $username = $request->post('username');
        $unique_id = $request->post('unique_id');
        $mac_address = $request->post('mac_address');
        $computer_name = $request->post('computer_name');
        $os_name = $request->post('os_name');
        $version_release = $request->post('version_release');
        $signature = $request->post('signature');

        if ($this->check_required_query_params(array($username, $unique_id, $signature))) {
            $operator = Operator::whereUsername($username)->whereActive(1)->first();
            if ($operator) {
                $operator_session = OperatorSession::where('operator_id', '=', $operator->id)
                    ->where('unique_id', '=', $unique_id)
                    ->first();
                if ($operator_session) {
                    $payload = array($username, $unique_id, $mac_address, $computer_name, $os_name);
                    if ($this->check_signature($payload, $operator_session->token, $this->getApp()->config('app_key'), $signature)) {
                        $operator_session->device_type = 'desktop';
                        $operator_session->mac_address = $mac_address;
                        $operator_session->computer_name = $computer_name;
                        $operator_session->os_name = $os_name;
                        $operator_session->version_release = $version_release;

                        if ($operator_session->save()) {
                            $this->send_response(true);
                        } else {
                            $this->send_response(false, array(
                                'code' => "E503",
                                'message' => "Failed to write device info"
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