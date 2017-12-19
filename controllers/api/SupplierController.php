<?php 

namespace App\Controller\Api;

use App\SlimC;

class SupplierController extends BaseApiController
{
	function getRoutes() {
        return array(
            "post" => array(
                "/request" => "inquiry",
                "/confirm" => "confirm"
            )
        );
    }

    function inquiry(SlimC $app) {
    	$this->send_response(true, array(
            'trx_id' => md5(uniqid(rand(), TRUE))
        ));
    }

    function confirm(SlimC $app) {
    	$item = new \stdClass();
        $item->name = "Voucher Code";
        $item->value = rand(100000, 999999);

    	$this->send_response(true, array(
            'trx_id' => $app->request->post('trx_id'),
            'item' => array($item)
        ));
    }
}