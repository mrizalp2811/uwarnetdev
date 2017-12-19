<?php

namespace App\Controller\Promoter;

use Gregwar\Captcha\CaptchaBuilder;
use App\Controller\Promoter\BasePromoterController;
use App\Model\Promoter;
use App\Model\Redeem;
use App\Model\MailQueue;

class AuthController extends BasePromoterController
{
    function getRoutes() {
        $routes = array(
            'get' => array(
                '/register' => array('register'),
                '/after_register' => array('after_register'),
                '/login' => array('login'),
                '/logout' => array('logout'),
                '/verification/:token' => array('verification'),
                '/bank_account_verification/:token' => array('bank_account_verification'),
                '/redeem_request_verification/:token' => array('redeem_request_verification'),
                '/verified' => array('verified'),
                '/bank_account_verified' => array('bank_account_verified'),
                '/redeem_request_verified' => array('redeem_request_verified'),
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
        return $routes;
    }

    public function register($app) {
        $app->render('promoter/register.php');
    }

    public function after_register($app) {
        $app->render('promoter/after_register.php');
    }

    public function do_register($app) {
        $name = $app->request->post('name');
        $phone = $app->request->post('phone');
        $city = $app->request->post('city');
        $email = $app->request->post('email');
        $password = $app->request->post('password');
        $confirm_password = $app->request->post('confirm_password');

        $app->flash('register', array(
            'name' => $name,
            'phone' => $phone,
            'city' => $city,
            'email' => $email,
        ));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $promoter = Promoter::whereEmail($email)->first();
            if (!$promoter) {
                if ($name && $phone && $city && $password && $confirm_password) {
                    if ($password == $confirm_password) {
                        $bcrypt = new \Bcrypt\Bcrypt();
                        $token = sha1($name.$phone.$city.$email.time());

                        $new_promoter = new Promoter;
                        $new_promoter->name = $name;
                        $new_promoter->phone = $phone;
                        $new_promoter->city = $city;
                        $new_promoter->email = $email;
                        $new_promoter->password = $bcrypt->hash($password);
                        $new_promoter->token = $token;
                        $new_promoter->expired = date('Y-m-d H:i:s', strtotime('+3 days'));

                        if ($new_promoter->save()) {
                            $mail_queue = new MailQueue;
                            $mail_queue->email = $email;
                            $mail_queue->name = $name;
                            $mail_queue->type = $mail_queue->types['PROMOTER_VERIFICATION'];
                            $mail_queue->params = json_encode(array(
                                'link' => 'https://promoter.uwarnet.id/auth/verification/'.$token
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
        $app->redirect($app->baseUrl().'/auth/register');
    }

    public function verification($app, $token)
    {
        $promoter = Promoter::whereToken($token)->first();

        if ($promoter) {
            if (strtotime($promoter->expired) > time()) {
                $promoter->verified = 1;
                if ($promoter->save()) {
                    $mail_queue = new MailQueue;
                    $mail_queue->email = 'uwarnet.id@gmail.com';
                    $mail_queue->name = 'Admin';
                    $mail_queue->type = $mail_queue->types['PROMOTER_VERIFICATION_NOTIF'];
                    $mail_queue->params = json_encode(array(
                        'name' => $promoter->name,
                        'phone' => $promoter->phone,
                        'city' => $promoter->city,
                        'email' => $promoter->email,
                        'register_time' => strftime("%B %e, %Y @%H:%M", strtotime($promoter->created_at)),
                        'verification_time' => strftime("%B %e, %Y @%H:%M", strtotime($promoter->updated_at)),
                        'link' => 'https://admin.uwarnet.id/promoter'
                    ));
                    $mail_queue->save();
                }
                $app->redirect($app->baseUrl().'/auth/verified');
            }
            $app->flash('error', 'Tautan verifikasi Anda expired');
        }
        $app->redirect($app->baseUrl().'/auth/login');
    }

    public function bank_account_verification($app, $token)
    {
        $promoter = Promoter::where('bank_account_verification_token', $token)->first();

        if ($promoter) {
            if (strtotime($promoter->bank_account_verification_expired) > time()) {
                $promoter->bank_account_verification_status = 1;
                $temp_bank_account = json_decode($promoter->temp_bank_account);
                $promoter->bank_name = $temp_bank_account->bank_name;
                $promoter->bank_account = $temp_bank_account->bank_account;
                $promoter->bank_account_holder_name = $temp_bank_account->bank_account_holder_name;

                $promoter->save();
                $app->redirect($app->baseUrl().'/auth/bank_account_verified');
            }
            $app->flash('error', 'Tautan verifikasi Anda expired');
        }
        $app->redirect($app->baseUrl().'/auth/login');
    }

    public function redeem_request_verification($app, $token)
    {
        $redeem = Redeem::whereToken($token)->first();

        if ($redeem) {
            if (strtotime($redeem->expired) > time()) {
                if ($redeem->status == 0) {
                    $redeem->status = 1;
                    if ($redeem->save()) {
                        $mail_queue = new MailQueue;
                        $mail_queue->email = 'uwarnet.id@gmail.com';
                        $mail_queue->name = 'Admin';
                        $mail_queue->type = $mail_queue->types['PROMOTER_REDEEM_VERIFICATION_NOTIF'];
                        $mail_queue->params = json_encode(array(
                            'name' => $redeem->promoter->name,
                            'amount' => "Rp.".number_format($redeem->amount * 1000, 0, ',', '.'),
                            'bank_name' => $redeem->bank_name,
                            'bank_account' => $redeem->bank_account,
                            'bank_account_holder_name' => $redeem->bank_account_holder_name,
                            'confirmation_time' => strftime("%B %e, %Y @%H:%M", time()),
                            'link' => 'https://admin.uwarnet.id/redeem'
                        ));
                        $mail_queue->save();
                    }
                    $app->redirect($app->baseUrl().'/auth/redeem_request_verified');
                } else {
                    $app->redirect($app->baseUrl().'/auth/login');
                }
            }
            $app->flash('error', 'Tautan verifikasi Anda expired');
        }
        $app->redirect($app->baseUrl().'/auth/login');
    }

    public function verified($app)
    {
        $app->render('promoter/verified.php');
    }

    public function bank_account_verified($app)
    {
        $app->render('promoter/bank_account_verified.php');
    }

    public function redeem_request_verified($app)
    {
        $app->render('promoter/redeem_request_verified.php');
    }

    public function resend_verification($app)
    {
        $app->render('promoter/resend_verification.php');
    }

    public function do_resend_verification($app)
    {
        $email = $app->request->post('email');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $promoter = Promoter::whereEmail($email)->first();
            if ($promoter) {
                if (!$promoter->verified) {
                    $promoter->expired = date('Y-m-d H:i:s', strtotime('+3 days'));
                    $promoter->save();
                    
                    $queue = MailQueue::whereEmail($email)
                        ->whereType(7)
                        ->whereStatus(0)
                        ->first();
                    if ($queue) {
                        
                    } else {
                        $mail_queue = new MailQueue;
                        $mail_queue->email = $promoter->email;
                        $mail_queue->name = $promoter->name;
                        $mail_queue->type = $mail_queue->types['PROMOTER_VERIFICATION'];
                        $mail_queue->params = json_encode(array(
                            'link' => 'https://promoter.uwarnet.id/auth/verification/'.$promoter->token
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
        $app->render('promoter/after_resend_verification.php');
    }

    public function forgot_password($app)
    {
        $app->render('promoter/forgot_password.php');
    }

    public function do_forgot_password($app)
    {
        $email = $app->request->post('email');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $promoter = Promoter::whereEmail($email)->first();
            if ($promoter) {
                if ($promoter->verified) {
                    if ($promoter->active) {
                        $token = sha1($promoter->name.$promoter->email.$promoter->phone.time());
                        $promoter->token = $token;
                        $promoter->expired = date('Y-m-d H:i:s', strtotime('+3 days'));

                        if ($promoter->save()) {
                            $mail_queue = new MailQueue;
                            $mail_queue->email = $promoter->email;
                            $mail_queue->name = $promoter->name;
                            $mail_queue->type = $mail_queue->types['PROMOTER_RESET_PASSWORD'];
                            $mail_queue->params = json_encode(array(
                                'link' => 'https://promoter.uwarnet.id/auth/reset_password/'.$token
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
        $app->render('promoter/after_forgot_password.php');
    }

    public function reset_password($app, $token)
    {
        $promoter = Promoter::whereToken($token)->first();

        if ($promoter && $promoter->verified && $promoter->active) {
            if (strtotime($promoter->expired) > time()) {
                $app->render('promoter/reset_password.php', array(
                    'token' => $promoter->token,
                    'email' => $promoter->email
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

        $promoter = Promoter::whereEmail($email)
            ->whereToken($token)
            ->first();

        if ($promoter) {
            if ($promoter->verified) {
                if ($promoter->active) {
                    if ($password && $confirm_password) {
                        if ($password == $confirm_password) {
                            $bcrypt = new \Bcrypt\Bcrypt();
                            $promoter->password = $bcrypt->hash($password);

                            if ($promoter->save()) {
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
        $app->render('promoter/after_reset_password.php');
    }

    public function login($app) {
        $params = array();

        if (isset($_SESSION['slim.flash']['captcha']) && $_SESSION['slim.flash']['captcha'] == 'ask') {
            $builder = new CaptchaBuilder;
            $builder->build();

            $app->flash('captcha_code', $builder->getPhrase());

            $params['builder'] = $builder;
        }

        $app->render('promoter/login.php', $params);
    }

    public function do_login($app) {
        $email = $app->request->post('email');
        $password = $app->request->post('password');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $promoter = Promoter::whereEmail($email)->first();
            if ($promoter) {
                if ($promoter->verified) {
                    if ($promoter->active) {
                        $bcrypt = new \Bcrypt\Bcrypt();

                        if ($bcrypt->verify($password, $promoter->password)) {
                            // $ip_address = $app->request->getIp();
                            // $user_agent = $app->request->getUserAgent();

                            // $hash = md5($promoter->email.$user_agent);
                            // $promoter_device = OwnerDevice::where('hash', $hash)->first();
                            // if ($promoter_device) {
                                $_SESSION['promoter_id'] = $promoter->id;
                                $_SESSION['promoter_email'] = $email;
                                $_SESSION['promoter_name'] = $promoter->name;
                                $_SESSION['promoter_login'] = true;
                                $app->redirect($app->baseUrl().'/profile');
                            // } else {
                            //     $code = $app->request->post('code');
                            //     if ($code) {
                            //         if ($code == $promoter->code) {
                            //             $promoter_device = new OwnerDevice;
                            //             $promoter_device->owner_id = $promoter->id;
                            //             $promoter_device->hash = md5($promoter->email.$user_agent);

                            //             $_SESSION['id'] = $promoter->id;
                            //             $_SESSION['email'] = $email;
                            //             $_SESSION['name'] = $promoter->name;
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