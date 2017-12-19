<?php

namespace App\Controller\Promoter;

use App\Controller\Promoter\BasePromoterController;
use App\Model\Promoter;
use App\Model\MailQueue;
use Hashids\Hashids;

class UserController extends BasePromoterController
{
    function getRoutes() {
        return array(
            'get' => array(
                '' => array('view', $this->auth($this->getApp()))
            ),
            'put' => array(
                '' => array('update', $this->auth($this->getApp())),
                '/info' => array('update_info', $this->auth($this->getApp())),
                '/bank' => array('update_bank', $this->auth($this->getApp()))
            )
        );
    }

    public function view($app) {
        $email = $_SESSION['promoter_email'];
        $promoter = Promoter::whereEmail($email)->whereActive(1)->first();

        $hashids = new Hashids('uwarnet-promoter', 8, 'abcdefghijklmnopqrstuvwxyz1234567890');

        $app->render('promoter/header.php', array(
            'name' => $_SESSION['promoter_name']
        ));
        $app->render('promoter/profile.php', array(
            'hashids' => $hashids,
            'promoter' => $promoter,
            'assets' => array(
                'js' => array(
                    'promoter-profile.js?v=2'
                )
            )
        ));
        $app->render('promoter/footer.php');
    }

    public function update($app) {
        $old_password = $app->request->post('old_password');
        $password = $app->request->post('password');
        $confirm_password = $app->request->post('confirm_password');

        $email = $_SESSION['promoter_email'];
        $promoter = Promoter::whereEmail($email)->whereActive(1)->first();

        $bcrypt = new \Bcrypt\Bcrypt();

        if ($bcrypt->verify($old_password, $promoter->password)) {
            if ($password && $confirm_password && $password == $confirm_password) {
                $promoter->password = $bcrypt->hash($password);
                if ($promoter->save()) {
                    $app->flash('success', 'Password diperbarui');
                } else {
                    $app->flash('error', 'Gagal memperbarui password');
                }
            } else {
                $app->flash('error', 'Password baru & konfirmasinya tidak sama');
            }
        } else {
            $app->flash('error', 'Kesalahan pada password lama');
        }
        $app->redirect($app->baseUrl().'/profile');
    }

    public function update_info($app) {
        $identity_card_number = $app->request->post('identity_card_number');
        $place_of_birth = $app->request->post('place_of_birth');
        $birthday = $app->request->post('birthday');
        $address = $app->request->post('address');

        $email = $_SESSION['promoter_email'];
        $promoter = Promoter::whereEmail($email)->whereActive(1)->first();

        if ($identity_card_number && $place_of_birth && $birthday && $address) {
            $promoter->identity_card_number = $identity_card_number;
            $promoter->place_of_birth = $place_of_birth;
            $promoter->date_of_birth = sprintf("%02d", $birthday['year'])."-".sprintf("%02d", $birthday['month'])."-".sprintf("%02d", $birthday['day'])." 00:00:00";
            $promoter->address = $address;
            if ($promoter->save()) {
                $app->flash('success', 'Info Anda berhasil diperbarui');
            } else {
                $app->flash('error', 'Gagal memperbarui info Anda');
            }
        } else {
            $app->flash('error', 'Info yang Anda masukkan tidak lengkap');
        }
        $app->redirect($app->baseUrl().'/profile');
    }

    public function update_bank($app) {
        $bank_name = $app->request->post('bank_name');
        $bank_account = $app->request->post('bank_account');
        $bank_account_holder_name = $app->request->post('bank_account_holder_name');

        if ($bank_name && $bank_account && $bank_account_holder_name) {
            $email = $_SESSION['promoter_email'];
            $promoter = Promoter::whereEmail($email)->whereActive(1)->first();

            if ($promoter->bank_name != $bank_name || 
                $promoter->bank_account != $bank_account || 
                $promoter->bank_account_holder_name != $bank_account_holder_name) {
                $promoter->bank_account_verification_status = 0;
                $token = sha1($promoter->name.$promoter->phone.$promoter->city.$promoter->email.time());
                $temp_bank_account = (object) array(
                    'bank_name' => $bank_name,
                    'bank_account' => $bank_account,
                    'bank_account_holder_name' => $bank_account_holder_name
                );
                $promoter->bank_account_verification_token = $token;
                $promoter->bank_account_verification_expired = date('Y-m-d H:i:s', strtotime('+3 days'));
                $promoter->temp_bank_account = json_encode($temp_bank_account);

                if ($promoter->save()) {
                    $mail_queue = new MailQueue;
                    $mail_queue->email = $promoter->email;
                    $mail_queue->name = $promoter->name;
                    $mail_queue->type = $mail_queue->types['PROMOTER_BANK_ACCOUNT_VERIFICATION'];
                    $mail_queue->params = json_encode(array(
                        'bank_name' => $bank_name,
                        'bank_account' => $bank_account,
                        'bank_account_holder_name' => $bank_account_holder_name,
                        'change_time' => strftime("%B %e, %Y @%H:%M", time()),
                        'link' => 'https://promoter.uwarnet.id/auth/bank_account_verification/'.$token
                    ));
                    $mail_queue->save();

                    $app->flash('success', 'Email konfirmasi berhasil dikirim');
                } else {
                    $app->flash('error', 'Gagal memperbarui rekening Anda');
                }
            } else {
                $app->flash('error', 'Anda tidak melakukan perubahan');
            }
        } else {
            $app->flash('error', 'Info yang Anda masukkan tidak lengkap');
        }
        $app->redirect($app->baseUrl().'/profile');
    }
}