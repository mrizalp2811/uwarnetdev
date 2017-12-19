<?php

namespace App\Controller\Admin;

use App\Controller\Admin\BaseAdminController;
use App\Model\Group;
use App\Model\Item;

class GroupController extends BaseAdminController
{
    function getRoutes() {
        $routes = array(
            'get' => array(
                '' => array('view', $this->auth($this->getApp())),
                '/:id/items' => array('items', $this->auth($this->getApp()))
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
        $groups = Group::orderBy('order', 'desc')
            ->get();

        // $orders = array();
        // if (!$groups->isEmpty()) foreach ($groups as $group) {
        //     $orders[] = $group->order;
        // }
        // asort($orders);
        
        $app->render('admin/header.php', array(
            'name' => $_SESSION['admin_name']
        ));
        $app->render('admin/group.php', array(
            'groups' => $groups,
            // 'orders' => $orders,
            'assets' => array(
                'js' => array(
                    'admin-group.js'
                )
            )
        ));
        $app->render('admin/footer.php');
    }

    public function create($app) {
        $name = $app->request->post('name');
        $description = $app->request->post('description');

        if ($name && $description) {
            if (isset($_FILES['icon'])) {
                $upload_filename = $_FILES['icon']['tmp_name'];
                // $group_img_ext = pathinfo($upload_filename, PATHINFO_EXTENSION);
                $path_parts = pathinfo($_FILES['icon']['name']);
                $group_img_ext = ".".(isset($path_parts['extension']) ? $path_parts['extension'] : '.png');
                $group_img_dir = '../../public/assets/img/groups/';
                $group_img_name = strtolower(preg_replace('/[^a-z0-9]/', '', $name));
                $temp_name = $group_img_name;
                while (file_exists($group_img_dir.$temp_name.$group_img_ext)) {
                    $temp_name = $group_img_name.uniqid();
                }
                $group_img_filename = $temp_name.$group_img_ext;
                $group_img_filepath = $group_img_dir.$temp_name.$group_img_ext;
                list($width, $height, $type, $attr) = getimagesize($upload_filename);
                if ($width == 600 && $height == 300) {
                    if (move_uploaded_file($upload_filename, $group_img_filepath)) {
                        $group = new Group;
                        $group->name = $name;
                        $group->description = $description;
                        $group->icon = $group_img_filename;
                        
                        if ($group->save()) {
                            $app->flash('success', 'Group added');
                        } else {
                            $app->flash('error', 'Failed to save group');
                        }
                    } else {
                        $app->flash('error', 'Failed to upload image');
                    }
                } else {
                    $app->flash('error', 'Image must be 600x300 in pixels');
                }
            } else {
                $app->flash('error', 'No image uploaded');
            }
        } else {
            $app->flash('error', 'Missing some information');
        }
        $app->redirect($app->baseUrl().'/group');
    }

    public function update($app) {
        $id = $app->request->post('id');
        $name = $app->request->post('name');
        $description = $app->request->post('description');

        $group = Group::find($id);
        if ($group) {
            if ($name && $description) {
                if ($_FILES['icon']['name']) {
                    $upload_filename = $_FILES['icon']['tmp_name'];
                    // $group_img_ext = pathinfo($upload_filename, PATHINFO_EXTENSION);
                    $path_parts = pathinfo($_FILES['icon']['name']);
                    $group_img_ext = ".".$path_parts['extension'];
                    $group_img_dir = '../../public/assets/img/groups/';
                    $group_img_name = strtolower(preg_replace('/[^a-z0-9]/', '', $name));
                    $temp_name = $group_img_name;
                    while (file_exists($group_img_dir.$temp_name.$group_img_ext)) {
                        $temp_name = $group_img_name.uniqid();
                    }
                    $group_img_filename = $temp_name.$group_img_ext;
                    $group_img_filepath = $group_img_dir.$temp_name.$group_img_ext;
                    list($width, $height, $type, $attr) = getimagesize($upload_filename);
                    if ($width == 600 && $height == 300) {
                        if (move_uploaded_file($upload_filename, $group_img_filepath)) {
                            $group->name = $name;
                            $group->description = $description;
                            $group->icon = $group_img_filename;
                            if ($group->save()) {
                                $app->flash('success', 'Group updated');
                            } else {
                                $app->flash('error', 'Failed to save group');
                            }
                        } else {
                            $app->flash('error', 'Failed to upload image');
                        }
                    } else {
                        $app->flash('error', 'Image must be 600x300 in pixels');
                    }
                } else {
                    $group->name = $name;
                    $group->description = $description;
                    if ($group->save()) {
                        $app->flash('success', 'Group updated');
                    } else {
                        $app->flash('error', 'Failed to save group');
                    }
                }
            } else {
                $app->flash('error', 'Missing some information');
            }
        } else {
            $app->flash('error', 'Resource not found');
        }
        $app->redirect($app->baseUrl().'/group');
    }

    public function activate($app) {
        $id = $app->request->post('id');
        $group = Group::find($id);
            
        $group->active = 1;
        $group->save();

        $app->redirect($app->baseUrl().'/group');
    }

    public function deactivate($app) {
        $id = $app->request->post('id');
        $group = Group::find($id);

        $group->active = 0;
        $group->save();
        
        $app->redirect($app->baseUrl().'/group');
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