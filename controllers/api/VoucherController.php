<?php

namespace App\Controller\Api;

use App\SlimC;
use App\Model\Group;
use App\Model\Item;
use App\Model\Operator;
use App\Model\OperatorSession;
use Slim\Http\Request;
use Slim\Http\Response;

class VoucherController extends BaseApiController
{
    function getRoutes() {
        return array(
            'post' => array(
                '/groups' => array('groups'), 
                '/items' => array('items')
            )
        );
    }

    function groups(SlimC $app) {
        $request = $app->request;
        $response = $app->response;
        $username = $request->post('username');
        $unique_id = $request->post('unique_id');
        $signature = $request->post('signature');
        $api_version = $app->request->headers->get('API-Version');

        if ($this->check_required_query_params(array($username, $unique_id, $signature))) {
            $operator = Operator::whereUsername($username)->whereActive(1)->first();
            if ($operator) {
                $operator_session = OperatorSession::where('operator_id', '=', $operator->id)
                    ->where('unique_id', '=', $unique_id)
                    ->first();
                if ($operator_session) {
                    $payload = array($username, $unique_id);
                    if ($this->check_signature($payload, $operator_session->token, $this->getApp()->config('app_key'), $signature)) {
                        $groups_builder = Group::whereActive(1)->orderBy('order', 'desc');

                        if ($api_version < 2) {
                            $groups_builder->select(array('id', 'name', 'icon', 'description', 'created_at', 'updated_at'));
                            $groups_builder->where('type', null);
                        }

                        if ($api_version < 3 || ($operator_session->device_type == 'mobile' && !$operator_session->fcm_token)) {
                            // $groups_builder->where('id', '<>', 19);
                            $groups_builder->whereNotIn('id', array(18,19,20,21,22,23,24));
                        }

                        // if ($operator->id != 2 && $operator->id != 1) {
                        //     $groups_builder->where('id', '<>', 19);
                        // }
                        $groups = $groups_builder->get()->toArray();

                        $this->send_response(true, array(
                            'groups' => $groups
                        ));
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

    function items(SlimC $app){
        $request = $app->request;
        $response = $app->response;
        $username = $request->post('username');
        $unique_id = $request->post('unique_id');
        $group_id = $request->post('group_id');
        $signature = $request->post('signature');

        if ($this->check_required_query_params(array($username, $unique_id, $group_id, $signature))) {
            $operator = Operator::whereUsername($username)->whereActive(1)->first();
            if ($operator) {
                $operator_session = OperatorSession::where('operator_id', '=', $operator->id)
                    ->where('unique_id', '=', $unique_id)
                    ->first();
                if ($operator_session) {
                    $payload = array($username, $unique_id, $group_id);
                    if ($this->check_signature($payload, $operator_session->token, $this->getApp()->config('app_key'), $signature)) {
                        $items = Item::whereActive(1)
                            ->where('group_id', '=', $group_id)
                            ->orderBy('price', 'asc')
                            ->get();

                        $owner_id = $operator->warnet->owner->id;

                        if ($items) foreach ($items as $item) {
                            $item->price = $item->owners()->where('owner_id', $owner_id)->first() ? $item->owners()->where('owner_id', $owner_id)->first()->pivot->sale_price : $item->price;
                            $group = Group::find($item->group_id);
                            if ($group->type == null) {
                                $type = null;
                            } else if ($group->type == 'pulsa') {
                                $type = 'pulsa';
                            } else if ($group->type == 'pln-prepaid') {
                                $type = 'pln-prepaid';
                            } else if ($group->type == 'pln-postpaid') {
                                $type = 'pln-postpaid';
                            }
                            $item->type = $type;
                        }

                        $items = $items->toArray();

                        if ($items) {
                            send_response(true, array(
                                'items' => $items
                            ));
                        } else {
                            $this->send_response(false, array(
                                'code' => "E107",
                                'message' => "Group not found"
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