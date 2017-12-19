<?php

namespace App\Controller\Admin;

use App\Controller\Admin\BaseAdminController;
use App\Model\Owner;
use App\Model\Promo;
use App\Model\SmsPromo;
use App\Model\Push;
use App\Model\FcmPush;
use App\Model\DesktopPush;
use App\Model\OperatorSession;
use Hashids\Hashids;

class PromoController extends BaseAdminController
{
    function getRoutes() {
        return array(
            'get' => array(
                '' => array('view', $this->auth($this->getApp())),
                '/add' => array('add', $this->auth($this->getApp())),
                '/edit/:hashid' => array('edit', $this->auth($this->getApp())),
                '/sms' => array('sms', $this->auth($this->getApp()))
            ),
            'post' => array(
                '' => array('create', $this->auth($this->getApp())),
                '/push' => array('push', $this->auth($this->getApp())),
                '/activate' => array('activate', $this->auth($this->getApp())),
                '/deactivate' => array('deactivate', $this->auth($this->getApp())),
                '/upload' => array('upload', $this->auth($this->getApp())),
                '/sms' => array('add_sms', $this->auth($this->getApp())),
                '/sms/push' => array('push_sms', $this->auth($this->getApp()))
            ),
            'put' => array(
                '' => array('update', $this->auth($this->getApp())),
                '/sms' => array('update_sms', $this->auth($this->getApp())),
            ),
        );
    }

    public function view($app) {
        $promos = array();

        $promos = Promo::orderBy('created_at', 'asc')
            ->get();
        
        $hashids = new Hashids("promo-salt", 8, 'abcdefghijklmnopqrstuvwxyz1234567890');

        $app->render('admin/header.php', array(
            'name' => $_SESSION['admin_name']
        ));
        $app->render('admin/promo.php', array(
            'hashids' => $hashids,
            'promos' => $promos,
            'assets' => array(
                'js' => array(
                    'admin-promo.js?v=2'
                )
            )
        ));
        $app->render('admin/footer.php');
    }

    public function add($app) {
        $app->render('admin/header.php', array(
            'name' => $_SESSION['admin_name']
        ));
        $app->render('admin/promo-add.php', array(
            'assets' => array(
                'js' => array(
                    'admin-promo-add.js?v=6'
                )
            )
        ));
        $app->render('admin/footer.php');
    }

    public function edit($app, $hashid) {
        $hashids = new Hashids("promo-salt", 8, 'abcdefghijklmnopqrstuvwxyz1234567890');

        $array = $hashids->decode($hashid);
        if ($array) {
            $id = $array[0];
            $promo = Promo::find($id);

            $app->render('admin/header.php', array(
                'name' => $_SESSION['admin_name']
            ));
            $app->render('admin/promo-edit.php', array(
                'promo' => $promo,
                'assets' => array(
                    'js' => array(
                        'admin-promo-edit.js?v=6'
                    )
                )
            ));
            $app->render('admin/footer.php');
        } else {
            $app->redirect($app->baseUrl().'/auth/login');
        }
    }

    public function sms($app) {
        $sms_promos = array();

        $sms_promos = SmsPromo::orderBy('created_at', 'asc')
            ->get();

        $hashids = new Hashids("sms-promo-salt", 8, 'abcdefghijklmnopqrstuvwxyz1234567890');

        $app->render('admin/header.php', array(
            'name' => $_SESSION['admin_name']
        ));
        $app->render('admin/sms_promo.php', array(
            'hashids' => $hashids,
            'sms_promos' => $sms_promos,
            'assets' => array(
                'js' => array(
                    'admin-sms-promo.js?v=2'
                )
            )
        ));
        $app->render('admin/footer.php');
    }

    public function add_sms($app) {
        $name = $app->request->post('name');
        $message = $app->request->post('message');
        $phones = $app->request->post('phones');

        if ($name && $message && $phones) {
            if (strlen($message) <= 160) {
                $phones = preg_replace( '/\s+/', '', $phones);
                $sms_promo = new SmsPromo;
                $sms_promo->name = $name;
                $sms_promo->message = $message;
                $sms_promo->phones = $phones;
                
                if ($sms_promo->save()) {
                    $app->flash('success', 'SMS Promo added');
                } else {
                    $app->flash('error', 'Failed to save SMS promo');
                }
            } else {
                $app->flash('error', 'Message length max 160 characters');
            }
        } else {
            $app->flash('error', 'Missing some information');
        }
        $app->redirect($app->baseUrl().'/promo/sms');
    }

    public function update_sms($app) {
        $id = $app->request->post('id');
        $name = $app->request->post('name');
        $message = $app->request->post('message');
        $phones = $app->request->post('phones');

        if ($name && $message && $phones) {
            if (strlen($message) <= 160) {
                $phones = preg_replace( '/\s+/', '', $phones);
                $sms_promo = SmsPromo::find($id);
                $sms_promo->name = $name;
                $sms_promo->message = $message;
                $sms_promo->phones = $phones;
                
                if ($sms_promo->save()) {
                    $app->flash('success', 'Promo saved');
                } else {
                    $app->flash('error', 'Failed to save promo');
                }
            } else {
                $app->flash('error', 'Message length max 160 characters');
            }
        } else {
            $app->flash('error', 'Missing some information');
        }
        $app->redirect($app->baseUrl().'/promo/sms');
    }

    public function push_sms($app)
    {
        $id = $app->request->post('id');

        $sms_promo = SmsPromo::find($id);
        if ($sms_promo) {
            $sms_promo->sent = 0;
            $sms_promo->push_count += 1;
            $sms_promo->last_push = date('Y-m-d H:i:s');
            if ($sms_promo->save()) {
                $app->flash('success', 'Push success');
            } else {
                $app->flash('error', 'Failed to push SMS promo');
            }
        } else {
            $app->flash('error', 'SMS promo not found');
        }
        $app->redirect($app->baseUrl().'/promo/sms');
    }

    public function create($app) {
        $name = $app->request->post('name');
        $type = $app->request->post('type');
        $content = $app->request->post('content');

        if ($name && $type) {
            $promo = new Promo;
            $promo->name = $name;
            $promo->type = $type;
            $promo->content = $content;
            
            if ($promo->save()) {
                $app->flash('success', 'Promo added');
            } else {
                $app->flash('error', 'Failed to save promo');
            }
        } else {
            $app->flash('error', 'Missing some information');
        }
        $app->redirect($app->baseUrl().'/promo');
    }

    public function update($app) {
        $id = $app->request->post('id');
        $name = $app->request->post('name');
        $type = $app->request->post('type');
        $content = $app->request->post('content');

        if ($name && $type) {
            $promo = Promo::find($id);
            $promo->name = $name;
            $promo->type = $type;
            $promo->content = $content;
            
            if ($promo->save()) {
                $app->flash('success', 'Promo saved');
            } else {
                $app->flash('error', 'Failed to save promo');
            }
        } else {
            $app->flash('error', 'Missing some information');
        }
        $app->redirect($app->baseUrl().'/promo');
    }

    public function push($app)
    {
        $id = $app->request->post('id');

        $promo = Promo::find($id);
        if ($promo) {
            $hashids = new Hashids("promo-salt", 8, 'abcdefghijklmnopqrstuvwxyz1234567890');
            if ($promo->type == 'inner') {
                $link = "https://uwarnet.id/promo/".$hashids->encode($promo->id);
                // $link = "http://uwarnet.trusty:22280/promo/".$hashids->encode($promo->id);
            } else if ($promo->type == 'link') {
                $link = $promo->content;
            }

            $tokens = OperatorSession::whereNotNull('fcm_token')->lists('fcm_token');

            $data = array(
                "link" => $link,
                "group_type" => "promo",
                "status" => true
            );

            $notification = array(
                "title" => $promo->name
            );

            $push_result = Push::send($app, $promo->name, $link);
            $fcm_push_result = FcmPush::sendMultiple($app, $tokens, $data, $notification);
            $desktop_push_result = DesktopPush::sendMultiple($app, array(
                "notification" => array(
                    "title" => $promo->name,
                    "message" => $promo->name,
                    "link" => $link
                ),
                "data" => $data
            ));
            if ($push_result->status && $fcm_push_result->status) {
                $promo->sent = 1;
                $promo->push_count += 1;
                $promo->last_push = date('Y-m-d H:i:s');
                $promo->save();

                $app->flash('success', 'Push success.');
            } else {
                $app->flash('error', 'Failed to push promo');
            }
        } else {
            $app->flash('error', 'Promo not found');
        }
        $app->redirect($app->baseUrl().'/promo');
    }

    public function activate($app) {
        $id = $app->request->post('id');
        $promo = Promo::find($id);

        $promo->active = 1;
        $promo->save();

        $app->redirect($app->baseUrl().'/promo');
    }

    public function deactivate($app) {
        $id = $app->request->post('id');
        $promo = Promo::find($id);

        $promo->active = 0;
        $promo->save();
        
        $app->redirect($app->baseUrl().'/promo');
    }

    public function upload($app) {
        if (isset($_FILES['file'])) {
            if ($_FILES['file']['name']) {
                if (!$_FILES['file']['error']) {
                    $upload_filename = $_FILES['file']['tmp_name'];
                    $path_parts = pathinfo($_FILES['file']['name']);
                    $item_img_ext = ".".$path_parts['extension'];
                    $item_img_dir = '../../public/assets/img/promos/';
                    $item_img_name = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $path_parts['filename']));
                    $temp_name = $item_img_name;
                    while (file_exists($item_img_dir.$temp_name.$item_img_ext)) {
                        $temp_name = $item_img_name.uniqid();
                    }
                    $item_img_filename = $temp_name.$item_img_ext;
                    $item_img_filepath = $item_img_dir.$temp_name.$item_img_ext;

                    if (move_uploaded_file($upload_filename, $item_img_filepath)) {
                        echo $app->baseUrl().'/assets/img/promos/'.$item_img_filename;
                    } else {
                        echo $message = 'Ooops! Failed to process file.';
                    }
                } else {
                    echo $message = 'Ooops! Your upload triggered the following error: '.$_FILES['file']['error'];
                }
            } else {
                echo $message = 'Ooops! File not uploaded.';
            }
        } else {
            echo $message = 'Ooops! No file uploaded.';
        }
    }
}