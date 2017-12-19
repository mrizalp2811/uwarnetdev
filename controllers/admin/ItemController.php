<?php

namespace App\Controller\Admin;

use App\Controller\Admin\BaseAdminController;
use App\Model\Group;
use App\Model\Item;

class ItemController extends BaseAdminController
{
    function getRoutes() {
        $routes = array(
            'get' => array(
                '' => array('view', $this->auth($this->getApp()))
            ),
            'post' => array(
                '' => array('create', $this->auth($this->getApp())),
                '/activate' => array('activate', $this->auth($this->getApp())),
                '/deactivate' => array('deactivate', $this->auth($this->getApp()))
            ),
            'put' => array(
                '' => array('update', $this->auth($this->getApp()))
            )
        );
        return $routes;
    }

    public function view($app) {
        $groups = Group::orderBy('name', 'asc')
            ->get();

        $items = Item::select('item.*')
            ->join('group', 'item.group_id', '=', 'group.id')
            ->orderBy('group.order', 'desc')
            ->orderBy('group.name', 'asc')
            ->orderBy('item.price', 'asc')
            ->get();

        $app->render('admin/header.php', array(
            'name' => $_SESSION['admin_name']
        ));
        $app->render('admin/item.php', array(
            'groups' => $groups,
            'items' => $items,
            'assets' => array(
                'js' => array(
                    'admin-item.js'
                )
            )
        ));
        $app->render('admin/footer.php');
    }

    public function create($app) {
        $code = $app->request->post('code');
        $name = $app->request->post('name');
        $price = $app->request->post('price');
        $group_id = $app->request->post('group_id');

        if ($code && $name && $price) {
            if ($group_id) {
                if (isset($_FILES['icon'])) {
                    $upload_filename = $_FILES['icon']['tmp_name'];
                    // echo $item_img_ext = pathinfo($upload_filename, PATHINFO_EXTENSION); exit();
                    $path_parts = pathinfo($_FILES['icon']['name']);
                    $item_img_ext = ".".$path_parts['extension'];
                    $item_img_dir = '../../public/assets/img/items/';
                    $item_img_name = strtolower(preg_replace('/[^a-z0-9]/', '', $name));
                    $temp_name = $item_img_name;
                    while (file_exists($item_img_dir.$temp_name.$item_img_ext)) {
                        $temp_name = $item_img_name.uniqid();
                    }
                    $item_img_filename = $temp_name.$item_img_ext;
                    $item_img_filepath = $item_img_dir.$temp_name.$item_img_ext;
                    list($width, $height, $type, $attr) = getimagesize($upload_filename);
                    if ($width == 150 && $height == 200) {
                        if (move_uploaded_file($upload_filename, $item_img_filepath)) {
                            $item = new Item;
                            $item->code = $code;
                            $item->name = $name;
                            $item->price = $price;
                            $item->icon = $item_img_filename;
                            $item->group_id = $group_id;
                            $item->supplier_id = 1;
                            
                            if ($item->save()) {
                                $app->flash('success', 'Item added');
                            } else {
                                $app->flash('error', 'Failed to save item');
                            }
                        } else {
                            $app->flash('error', 'Failed to upload image');
                        }
                    } else {
                        $app->flash('error', 'Image must be 150x200 in pixels');
                    }
                } else {
                    $app->flash('error', 'No image uploaded');
                }
            } else {
                $app->flash('error', 'Please select item group');
            }
        } else {
            $app->flash('error', 'Missing some information');
        }
        $app->redirect($app->baseUrl().'/item');
    }

    public function update($app) {
        $id = $app->request->post('id');
        $code = $app->request->post('code');
        $name = $app->request->post('name');
        $price = $app->request->post('price');
        $group_id = $app->request->post('group_id');

        $item = Item::find($id);
        if ($item) {
            if ($code && $name && $price) {
                if ($group_id) {
                    if ($_FILES['icon']['name']) {
                        $upload_filename = $_FILES['icon']['tmp_name'];
                        // $item_img_ext = pathinfo($upload_filename, PATHINFO_EXTENSION);
                        $path_parts = pathinfo($_FILES['icon']['name']);
                        $item_img_ext = ".".$path_parts['extension'];
                        $item_img_dir = '../../public/assets/img/items/';
                        $item_img_name = strtolower(preg_replace('/[^a-z0-9]/', '', $name));
                        $temp_name = $item_img_name;
                        while (file_exists($item_img_dir.$temp_name.$item_img_ext)) {
                            $temp_name = $item_img_name.uniqid();
                        }
                        $item_img_filename = $temp_name.$item_img_ext;
                        $item_img_filepath = $item_img_dir.$temp_name.$item_img_ext;
                        list($width, $height, $type, $attr) = getimagesize($upload_filename);
                        if ($width == 150 && $height == 200) {
                            if (move_uploaded_file($upload_filename, $item_img_filepath)) {
                                $item->code = $code;
                                $item->name = $name;
                                $item->price = $price;
                                $item->group_id = $group_id;
                                $item->icon = $item_img_filename;
                                if ($item->save()) {
                                    $app->flash('success', 'Item updated');
                                } else {
                                    $app->flash('error', 'Failed to save item');
                                }
                            } else {
                                $app->flash('error', 'Failed to upload image');
                            }
                        } else {
                            $app->flash('error', 'Image must be 150x200 in pixels');
                        }
                    } else {
                        $item->code = $code;
                        $item->name = $name;
                        $item->price = $price;
                        $item->group_id = $group_id;
                        if ($item->save()) {
                            $app->flash('success', 'Item updated');
                        } else {
                            $app->flash('error', 'Failed to save item');
                        }
                    }
                } else {
                    $app->flash('error', 'Please select item group');
                }
            } else {
                $app->flash('error', 'Missing some information');
            }
        } else {
            $app->flash('error', 'Resource not found');
        }
        $app->redirect($app->baseUrl().'/item');
    }

    public function activate($app) {
        $id = $app->request->post('id');
        $item = Item::find($id);

        $item->active = 1;
        $item->save();

        $app->redirect($app->baseUrl().'/item');
    }

    public function deactivate($app) {
        $id = $app->request->post('id');
        $item = Item::find($id);

        $item->active = 0;
        $item->save();
        
        $app->redirect($app->baseUrl().'/item');
    }
}