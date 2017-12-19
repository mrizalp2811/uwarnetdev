<?php

namespace App\Controller\Portal;

use App\Controller\Portal\BasePortalController;
use App\Model\Owner;
use App\Model\OwnerDevice;
use App\Model\MailQueue;
use Hashids\Hashids;

class AuthController extends BasePortalController
{
    function getRoutes() {
        return array(
            'get' => array(
                '/register' => array('register'),
                '/after_register' => array('after_register'),
                '/login' => array('login'),
                '/logout' => array('logout'),
                '/verification/:token' => array('verification'),
                '/verified' => array('verified'),
                '/resend_verification' => array('resend_verification'),
                '/after_resend_verification' => array('after_resend_verification'),
                '/forgot_password' => array('forgot_password'),
                '/after_forgot_password' => array('after_forgot_password'),
                '/reset_password/:token' => array('reset_password'),
                '/after_reset_password' => array('after_reset_password'),
            ),
            'post' => array(
                '/register' => array('do_register'),
                '/login' => array('do_login'),
                '/do_resend_verification' => array('do_resend_verification'),
                '/do_forgot_password' => array('do_forgot_password'),
                '/do_reset_password' => array('do_reset_password'),
            )
        );
    }    

    public function register($app) {
        $pid = $app->request->get('pid');
        $app->render('portal/register.php', array(
            'pid' => htmlspecialchars($pid)
        ));
    }

    public function after_register($app) {
        $app->render('portal/after_register.php');
    }

    public function do_register($app) {
        $pid = $app->request->post('pid');
        $name = $app->request->post('name');
        $email = $app->request->post('email');
        $phone = $app->request->post('phone');
        $password = $app->request->post('password');
        $confirm_password = $app->request->post('confirm_password');

        $warnet_name = $app->request->post('warnet_name');
        $warnet_count = $app->request->post('warnet_count');
        $warnet_address = $app->request->post('warnet_address');
        $warnet_city = $app->request->post('warnet_city');

        $app->flash('register', array(
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'warnet_name' => $warnet_name,
            'warnet_count' => $warnet_count,
            'warnet_address' => $warnet_address,
            'warnet_city' => $warnet_city
        ));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $owner = Owner::whereEmail($email)->first();
            if (!$owner) {
                if ($name && $phone && $password && $confirm_password && $warnet_name && $warnet_count && $warnet_address && $warnet_city) {
                    if ($password == $confirm_password) {
                        $bcrypt = new \Bcrypt\Bcrypt();
                        $token = sha1($name.$email.$phone.time());

                        $new_owner = new Owner;
                        $new_owner->name = $name;
                        $new_owner->email = $email;
                        $new_owner->phone = $phone;
                        $new_owner->password = $bcrypt->hash($password);
                        $new_owner->warnet_name = $warnet_name;
                        $new_owner->warnet_count = $warnet_count;
                        $new_owner->warnet_address = $warnet_address;
                        $new_owner->warnet_city = $warnet_city;
                        $new_owner->token = $token;
                        $new_owner->expired = date('Y-m-d H:i:s', strtotime('+3 days'));

                        if ($pid) {
                            $hashids = new Hashids('uwarnet-promoter', 8, 'abcdefghijklmnopqrstuvwxyz1234567890');
                            $array = $hashids->decode($pid);
                            if ($array) {
                                $promoter_id = $array[0];
                                $new_owner->promoter_id = $promoter_id;
                            }                            
                        }

                        if ($new_owner->save()) {
                            $mail_queue = new MailQueue;
                            $mail_queue->email = $email;
                            $mail_queue->name = $name;
                            $mail_queue->type = $mail_queue->types['USER_VERIFICATION'];
                            $mail_queue->params = json_encode(array(
                                'link' => 'https://uwarnet.id/auth/verification/'.$token
                            ));
                            $mail_queue->save();

                            $app->redirect($app->baseUrl().'/auth/after_register');
                        } else {
                            $app->flash('error', 'Gagal menambahkan akun Anda');
                        }
                    } else {
                        $app->flash('error', 'Password & konfirmasinya tidak sama');
                    }
                } else {
                    $app->flash('error', 'Info yang Anda masukkan tidak lengkap');
                }
            } else {
                $app->flash('error', 'Email sudah terdaftar');
            }
        } else {
            $app->flash('error', 'Email tidak valid');
        }
        $app->redirect($app->baseUrl().'/auth/register'.($pid ? "?pid=".$pid : ""));
    }

    public function verification($app, $token)
    {
        $owner = Owner::whereToken($token)->first();

        if ($owner) {
            if (strtotime($owner->expired) > time()) {
                $owner->verified = 1;
                if ($owner->save()) {
                    $mail_queue = new MailQueue;
                    $mail_queue->email = 'uwarnet.id@gmail.com';
                    $mail_queue->name = 'Admin';
                    $mail_queue->type = $mail_queue->types['USER_VERIFICATION_NOTIF'];
                    $mail_queue->params = json_encode(array(
                        'name' => $owner->name,
                        'email' => $owner->email,
                        'phone' => $owner->phone,
                        'warnet_name' => $owner->warnet_name,
                        'warnet_count' => $owner->warnet_count,
                        'warnet_address' => $owner->warnet_address,
                        'warnet_city' => $owner->warnet_city,
                        'register_time' => strftime("%B %e, %Y @%H:%M", strtotime($owner->created_at)),
                        'verification_time' => strftime("%B %e, %Y @%H:%M", strtotime($owner->updated_at)),
                        'link' => 'https://admin.uwarnet.id/owner'
                    ));
                    $mail_queue->save();
                }
                $app->redirect($app->baseUrl().'/auth/verified');
            }
            $app->flash('error', 'Tautan verifikasi Anda expired');
        }
        $app->redirect($app->baseUrl().'/auth/login');
    }

    public function verified($app)
    {
        $app->render('portal/verified.php');
    }

    public function resend_verification($app)
    {
        $app->render('portal/resend_verification.php');
    }

    public function do_resend_verification($app)
    {
        $email = $app->request->post('email');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $owner = Owner::whereEmail($email)->first();
            if ($owner) {
                if (!$owner->verified) {
                    $owner->expired = date('Y-m-d H:i:s', strtotime('+3 days'));
                    $owner->save();
                    
                    $queue = MailQueue::whereEmail($email)
                        ->whereType(1)
                        ->whereStatus(0)
                        ->first();
                    if ($queue) {
                        
                    } else {
                        $mail_queue = new MailQueue;
                        $mail_queue->email = $owner->email;
                        $mail_queue->name = $owner->name;
                        $mail_queue->type = $mail_queue->types['USER_VERIFICATION'];
                        $mail_queue->params = json_encode(array(
                            'link' => 'https://uwarnet.id/auth/verification/'.$owner->token
                        ));
                        $mail_queue->save();
                    }
                    $app->redirect($app->baseUrl().'/auth/after_resend_verification');
                } else {
                    $app->flash('error', 'Akun Anda sudah diverifikasi');
                }
            } else {
                $app->flash('error', 'Akun Anda tidak ditemukan');
            }
        } else {
            $app->flash('error', 'Email tidak valid');
        }
        $app->redirect($app->baseUrl().'/auth/resend_verification');
    }

    public function after_resend_verification($app)
    {
        $app->render('portal/after_resend_verification.php');
    }

    public function forgot_password($app)
    {
        $app->render('portal/forgot_password.php');
    }

    public function do_forgot_password($app)
    {
        $email = $app->request->post('email');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $owner = Owner::whereEmail($email)->first();
            if ($owner) {
                if ($owner->verified) {
                    if ($owner->active) {
                        $token = sha1($owner->name.$owner->email.$owner->phone.time());
                        $owner->token = $token;
                        $owner->expired = date('Y-m-d H:i:s', strtotime('+3 days'));

                        if ($owner->save()) {
                            $mail_queue = new MailQueue;
                            $mail_queue->email = $owner->email;
                            $mail_queue->name = $owner->name;
                            $mail_queue->type = $mail_queue->types['USER_RESET_PASSWORD'];
                            $mail_queue->params = json_encode(array(
                                'link' => 'https://uwarnet.id/auth/reset_password/'.$token
                            ));
                            $mail_queue->save();
                            $app->redirect($app->baseUrl().'/auth/after_forgot_password');
                        } else {
                            $app->flash('error', 'Gagal memperbarui password Anda');
                        }
                    } else {
                        $app->flash('error', 'Akun Anda belum diaktivasi');
                    }
                } else {
                    $app->flash('error', 'Akun Anda belum diverifikasi');
                }
            } else {
                $app->flash('error', 'Akun Anda tidak ditemukan');
            }
        } else {
            $app->flash('error', 'Email tidak valid');
        }
        $app->redirect($app->baseUrl().'/auth/forgot_password');
    }

    public function after_forgot_password($app)
    {
        $app->render('portal/after_forgot_password.php');
    }

    public function reset_password($app, $token)
    {
        $owner = Owner::whereToken($token)->first();

        if ($owner && $owner->verified && $owner->active) {
            if (strtotime($owner->expired) > time()) {
                $app->render('portal/reset_password.php', array(
                    'token' => $owner->token,
                    'email' => $owner->email
                ));
            } else {
                $app->flash('error', 'Tautan reset password Anda expired');
                $app->redirect($app->baseUrl().'/auth/login');
            }
        } else {
            $app->redirect($app->baseUrl().'/auth/login');
        }
    }

    public function do_reset_password($app)
    {
        $email = $app->request->post('email');
        $token = $app->request->post('token');
        $password = $app->request->post('password');
        $confirm_password = $app->request->post('confirm_password');

        $owner = Owner::whereEmail($email)
            ->whereToken($token)
            ->first();

        if ($owner) {
            if ($owner->verified) {
                if ($owner->active) {
                    if ($password && $confirm_password) {
                        if ($password == $confirm_password) {
                            $bcrypt = new \Bcrypt\Bcrypt();
                            $owner->password = $bcrypt->hash($password);

                            if ($owner->save()) {
                                $app->redirect($app->baseUrl().'/auth/after_reset_password');
                            } else {
                                $app->flash('error', 'Gagal mengubah password');
                            }
                        } else {
                            $app->flash('error', 'Password & konfirmasinya tidak sama');
                        }
                    } else {
                        $app->flash('error', 'Info yang Anda masukkan tidak lengkap');
                    }
                } else {
                    $app->flash('error', 'Akun Anda belum diaktivasi');
                }
            } else {
                $app->flash('error', 'Akun Anda belum diverifikasi');
            }
        } else {
            $app->flash('error', 'Akun Anda tidak ditemukan');
        }
        $app->redirect($app->baseUrl().'/auth/reset_password/'.$token);
    }

    public function after_reset_password($app)
    {
        $app->render('portal/after_reset_password.php');
    }

    public function login($app) {
        $app->render('portal/login.php');
    }

    public function do_login($app) {
        $email = $app->request->post('email');
        $password = $app->request->post('password');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $owner = Owner::whereEmail($email)->first();
            if ($owner) {
                if ($owner->verified) {
                    if ($owner->active) {
                        $bcrypt = new \Bcrypt\Bcrypt();

                        if ($bcrypt->verify($password, $owner->password)) {
                            // $ip_address = $app->request->getIp();
                            // $user_agent = $app->request->getUserAgent();

                            // $hash = md5($owner->email.$user_agent);
                            // $owner_device = OwnerDevice::where('hash', $hash)->first();
                            // if ($owner_device) {
                                $_SESSION['id'] = $owner->id;
                                $_SESSION['email'] = $email;
                                $_SESSION['name'] = $owner->name;
                                $_SESSION['login'] = true;
                                $app->redirect($app->baseUrl().'/wallet');
                            // } else {
                            //     $code = $app->request->post('code');
                            //     if ($code) {
                            //         if ($code == $owner->code) {
                            //             $owner_device = new OwnerDevice;
                            //             $owner_device->owner_id = $owner->id;
                            //             $owner_device->hash = md5($owner->email.$user_agent);

                            //             $_SESSION['id'] = $owner->id;
                            //             $_SESSION['email'] = $email;
                            //             $_SESSION['name'] = $owner->name;
                            //             $_SESSION['login'] = true;
                            //             $app->redirect($app->baseUrl().'/wallet');
                            //         } else {
                            //             $app->flash('error', 'Kesalahan pada kode. Masukkan kode yang dikirimkan ke email Anda.');
                            //             $app->flash('secure_email', $email);
                            //             $app->flash('secure_password', $password);
                            //         }
                            //     } else {
                            //         $app->flash('error', 'Login dari perangkat baru. Masukkan kode yang dikirimkan ke email Anda.');
                            //         $app->flash('secure_email', $email);
                            //         $app->flash('secure_password', $password);
                            //     }
                            // }
                        } else {
                            $app->flash('error', 'Kesalahan pada email atau password');
                        }
                    } else {
                        $app->flash('error', 'Akun Anda belum diaktivasi');
                    }
                } else {
                    $app->flash('error', 'Akun Anda belum diverifikasi');
                }
            } else {
                $app->flash('error', 'Kesalahan pada email atau password');
            }
        } else {
            $app->flash('error', 'Email tidak valid');
        }
        $app->redirect($app->baseUrl().'/auth/login');
    }

    public function logout($app) {
        session_destroy();
        $app->redirect($app->baseUrl().'/auth/login');
    }
}