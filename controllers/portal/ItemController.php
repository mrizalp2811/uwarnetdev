<?php

namespace App\Controller\Portal;

use App\Controller\Portal\BaseAdminController;
use App\Model\Owner;
use App\Model\Group;
use App\Model\Item;
use App\Model\OwnerItem;

class ItemController extends BasePortalController
{
    function getRoutes() {
        $routes = array(
            'get' => array(
                '' => array('view', $this->auth($this->getApp()))
            ),
            'put' => array(
                '' => array('update', $this->auth($this->getApp()))
            )
        );
        return $routes;
    }

    public function view($app) {
        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();

        $groups = Group::orderBy('name', 'asc')
            ->whereActive(1)
            ->get();

        $items = Item::select('item.*')
            ->join('group', 'item.group_id', '=', 'group.id')
            ->orderBy('group.order', 'desc')
            ->orderBy('group.name', 'asc')
            ->orderBy('item.price', 'asc')
            ->where('group.active', 1)
            ->where('item.active', 1)
            ->get();

        $app->render('portal/header.php', array(
            'name' => $_SESSION['name']
        ));
        $app->render('portal/item.php', array(
            'owner' => $owner,
            'groups' => $groups,
            'items' => $items,
            'assets' => array(
                'js' => array(
                    'portal-item.js?v=2'
                )
            )
        ));
        $app->render('portal/footer.php');
    }

    public function update($app) {
        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();

        $item_id = $app->request->post('id');
        $item = Item::find($item_id);

        $sale_price = $app->request->post('sale_price');
        if ($sale_price >= $item->price) {
            $owner->items()->sync(array($item->id => array('sale_price' => $sale_price)), false);
            $app->flash('success', 'Harga jual item berhasil diperbarui');
        } else {
            $app->flash('error', 'Harga jual minimal harus sama dengan harga beli');
        }
        $app->redirect($app->baseUrl().'/item');
    }
}