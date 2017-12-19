<?php

namespace App\Controller\Portal;

use App\Controller\Portal\BasePortalController;
use App\Model\Item;

class GroupController extends BasePortalController
{
    function getRoutes() {
        $routes = array(
            'get' => array(
                '/:id/items' => array('items', $this->auth($this->getApp())),
            )
        );
        return $routes;
    }

    public function items($app, $id) {
        $items = Item::where('group_id', '=', $id)
            ->orderBy('name', 'asc')
            ->get()
            ->toArray();

        if ($app->request->isAjax()) {
            $app->response->headers->set('Content-type', 'application/json');
            echo json_encode($items);
        } else {
            
        }
    }
}