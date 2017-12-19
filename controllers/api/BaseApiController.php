<?php

namespace App\Controller\Api;

use App\Controller\BaseController;

abstract class BaseApiController extends BaseController
{
    function check_required_query_params($params) {
        if (!is_array($params)){
            $params = func_get_args();
        }
        if ($params) {
            $is_complete = true;
            foreach ($params as $param) {
                if (! $param) {
                    $is_complete = false;
                    break;
                }
            }
            if ($is_complete) {
                return true;
            }
            return false;
        }
        return false;
    }

    function check_signature($payload, $token, $app_key, $signature) {
        $generated_signature = hash('sha256', implode("", $payload) . $token . $app_key);
        if ($generated_signature === $signature) return true;
        return false;
    }
    
    function send_response($success, $params = null) {
        if ($success) {
            $result = new \stdClass();
            $result->status = true;
            if ($params) foreach ($params as $key => $value) {
                $result->{$key} = $value;
            }
        } else {
            $result = new \stdClass();
            $result->status = false;
            $result->error_code = $params['code'];
            $result->error_message = $params['message'];
        }
        echo json_encode($result);
    }
}