<?php

namespace App\Controller\Promoter;

use App\Controller\Promoter\BasePromoterController;
use App\Model\Operator;
use App\Model\Transaction;
use App\Model\Promoter;
use App\Model\Redeem;
use App\Model\RedeemSetup;
use App\Model\Owner;
use App\Model\MailQueue;
use Hashids\Hashids;

class RedeemController extends BasePromoterController
{
    private $point_to_money_rate = 1000;

    function getRoutes() {
        return array(
            'get' => array(
                '' => array('view', $this->auth($this->getApp())),
                '/request/:hashid' => array('redeem_request', $this->auth($this->getApp())),
                '/confirm/:hashid/:amount_hashid' => array('redeem_confirm', $this->auth($this->getApp()))
            ),
            'post' => array(
                '/request/:hashid' => array('do_redeem_request', $this->auth($this->getApp())),
                '/confirm/:hashid/:amount_hashid' => array('do_redeem_confirm', $this->auth($this->getApp())),
            )
        );
    }

    public function view($app) {
        $email = $_SESSION['promoter_email'];
        $promoter = Promoter::whereEmail($email)->whereActive(1)->first();

        $hashids = new Hashids($app->container->settings['app_key'].$promoter->id, 8, 'abcdefghijklmnopqrstuvwxyz1234567890');

        $promoter_redeem_info = $this->_promoter_redeem_info($promoter);
        $total_base_reward = $promoter_redeem_info->total_base_reward;
        $total_reward = $promoter_redeem_info->total_reward;
        $total_redeem = $promoter_redeem_info->total_redeem;
        $total_reward_redeemable = $promoter_redeem_info->total_reward_redeemable;
        $total_point = $promoter_redeem_info->total_point;

        $app->render('promoter/header.php', array(
            'name' => $_SESSION['promoter_name']
        ));
        $app->render('promoter/redeem.php', array(
            'hashids' => $hashids,
            'promoter' => $promoter,
            'total_base_reward' => $total_base_reward,
            'total_reward' => $total_reward,
            'total_redeem' => $total_redeem,
            'total_reward_redeemable' => $total_reward_redeemable,
            'total_point' => $total_point,
            'redeems' => $promoter->redeems()->orderBy('updated_at', 'desc')->get()
        ));
        $app->render('promoter/footer.php');
    }

    public function redeem_request($app, $hashid)
    {
        $email = $_SESSION['promoter_email'];
        $promoter = Promoter::whereEmail($email)->whereActive(1)->first();

        if ($promoter) {
            $hashids = new Hashids($app->container->settings['app_key'].$promoter->id, 8, 'abcdefghijklmnopqrstuvwxyz1234567890');
            $array = $hashids->decode($hashid);

            if ($array) {
                $promoter_id = $array[0];

                if ($promoter->id == $promoter_id) {
                    $promoter_redeem_info = $this->_promoter_redeem_info($promoter);
                    $total_reward_redeemable = $promoter_redeem_info->total_reward_redeemable;
                    $redeem_amounts = array(200, 400, 600, 800, 1000, 1600, 2000, 3000, 4000);

                    $app->render('promoter/header.php', array(
                        'name' => $_SESSION['promoter_name']
                    ));
                    $app->render('promoter/redeem_request.php', array(
                        'hashid' => $hashid,
                        'redeem_amounts' => $redeem_amounts,
                        'total_reward_redeemable' => $total_reward_redeemable,
                        'promoter' => $promoter
                    ));
                    $app->render('promoter/footer.php');
                } else {
                    $app->redirect($app->baseUrl().'/auth/login');
                }
            } else {
                $app->redirect($app->baseUrl().'/auth/login');
            }
        } else {
            $app->redirect($app->baseUrl().'/auth/login');
        }
    }

    public function do_redeem_request($app, $hashid)
    {
        $email = $_SESSION['promoter_email'];
        $promoter = Promoter::whereEmail($email)->whereActive(1)->first();

        if ($promoter) {
            $hashids = new Hashids($app->container->settings['app_key'].$promoter->id, 8, 'abcdefghijklmnopqrstuvwxyz1234567890');
            $array = $hashids->decode($hashid);

            if ($array) {
                $promoter_id = $array[0];
                
                if ($promoter->id == $promoter_id) {
                    $amount = $app->request->post('amount');
                    $amount_hashid = $hashids->encode($amount);
                    $app->redirect($app->baseUrl().'/redeem/confirm/'.$hashid.'/'.$amount_hashid);
                } else {
                    $app->redirect($app->baseUrl().'/auth/login');
                }
            } else {
                $app->redirect($app->baseUrl().'/auth/login');
            }
        } else {
            $app->redirect($app->baseUrl().'/auth/login');
        }
    }

    public function redeem_confirm($app, $hashid, $amount_hashid)
    {
        $email = $_SESSION['promoter_email'];
        $promoter = Promoter::whereEmail($email)->whereActive(1)->first();

        if ($promoter) {
            $hashids = new Hashids($app->container->settings['app_key'].$promoter->id, 8, 'abcdefghijklmnopqrstuvwxyz1234567890');
            $array = $hashids->decode($hashid);

            if ($array) {
                $promoter_id = $array[0];

                if ($promoter->id == $promoter_id) {
                    $array_redeem = $hashids->decode($amount_hashid);
                    if ($array_redeem) {
                        $amount = $array_redeem[0];

                        if ($amount) {
                            $app->render('promoter/header.php', array(
                                'name' => $_SESSION['promoter_name']
                            ));
                            $app->render('promoter/redeem_confirm.php', array(
                                'hashid' => $hashid,
                                'amount' => $amount,
                                'amount_money' => $amount * $this->point_to_money_rate,
                                'amount_hashid' => $amount_hashid,
                                'promoter' => $promoter
                            ));
                            $app->render('promoter/footer.php');
                        } else {
                            $app->redirect($app->baseUrl().'/auth/login');
                        }
                    } else {
                        $app->redirect($app->baseUrl().'/auth/login');
                    }
                } else {
                    $app->redirect($app->baseUrl().'/auth/login');
                }
            } else {
                $app->redirect($app->baseUrl().'/auth/login');
            }
        } else {
            $app->redirect($app->baseUrl().'/auth/login');
        }
    }

    public function do_redeem_confirm($app, $hashid, $amount_hashid)
    {
        $email = $_SESSION['promoter_email'];
        $promoter = Promoter::whereEmail($email)->whereActive(1)->first();

        if ($promoter) {
            $hashids = new Hashids($app->container->settings['app_key'].$promoter->id, 8, 'abcdefghijklmnopqrstuvwxyz1234567890');
            $array = $hashids->decode($hashid);

            if ($array) {
                $promoter_id = $array[0];
                
                if ($promoter->id == $promoter_id) {
                    $array_redeem = $hashids->decode($amount_hashid);
                    if ($array_redeem) {
                        $amount = $array_redeem[0];

                        $redeem = new Redeem;
                        $redeem->promoter_id = $promoter->id;
                        $redeem->bank_name = $promoter->bank_name;
                        $redeem->bank_account = $promoter->bank_account;
                        $redeem->bank_account_holder_name = $promoter->bank_account_holder_name;
                        $redeem->amount = $amount;
                        $redeem->status = 0;
                        $token = sha1($redeem->promoter_id.$redeem->bank_name.$redeem->bank_account.$redeem->bank_account_holder_name.$redeem->amount.time());
                        $redeem->token = $token;
                        $redeem->expired = date('Y-m-d H:i:s', strtotime('+3 days'));
                        if ($redeem->save()) {
                            $mail_queue = new MailQueue;
                            $mail_queue->email = $email;
                            $mail_queue->name = $promoter->name;
                            $mail_queue->type = $mail_queue->types['PROMOTER_REDEEM_VERIFICATION'];
                            $mail_queue->params = json_encode(array(
                                'amount' => "Rp.".number_format($redeem->amount * $this->point_to_money_rate, 0, ',', '.'),
                                'bank_name' => $redeem->bank_name,
                                'bank_account' => $redeem->bank_account,
                                'bank_account_holder_name' => $redeem->bank_account_holder_name,
                                'request_time' => strftime("%B %e, %Y @%H:%M", strtotime($redeem->created_at)),
                                'link' => 'https://promoter.uwarnet.id/auth/redeem_request_verification/'.$token
                            ));
                            $mail_queue->save();

                            $app->flash('success', 'Permintaan berhasil dikirim');
                        } else {
                            $app->flash('error', 'Gagal menyimpan informasi redeem');
                        }
                        $app->redirect($app->baseUrl().'/redeem');
                    } else {
                        $app->redirect($app->baseUrl().'/auth/login');
                    }
                } else {
                    $app->redirect($app->baseUrl().'/auth/login');
                }
            } else {
                $app->redirect($app->baseUrl().'/auth/login');
            }
        } else {
            $app->redirect($app->baseUrl().'/auth/login');
        }
    }

    private function _promoter_redeem_info($promoter)
    {
        $poin_rate = 0.001;
        $base_reward = 50;

        $owner_ids = $promoter->owners()->whereActive(1)->lists('id');
        if ($owner_ids) {
            $transaction_builder = Transaction::query();
            $transaction_builder->whereIn('owner_id', $owner_ids);
            $transaction_builder->where('status', 2);

            $transaction_builder->where(function ($query) {
                $redeem_setups = RedeemSetup::whereActive(1)->get();
                if (count($redeem_setups)) foreach ($redeem_setups as $redeem_setup) {
                    $query->orWhereBetween('confirm_time', array($redeem_setup->start_time, $redeem_setup->end_time));
                }
            });
            $total_transaction_amount = $transaction_builder->get()->sum('price');
        } else {
            $total_transaction_amount = 0;
        }
        $total_base_reward = $base_reward * count($owner_ids);
        $total_reward = floor(floor(0.1 / 100 * $total_transaction_amount) * $poin_rate);
        $total_redeem = $promoter->redeems()->where('status', '<', 3)->where('status', '>', 0)->sum('amount');
        $total_reward_redeemable = $total_base_reward + $total_reward - $total_redeem;
        $total_point = floor($total_reward / $this->point_to_money_rate);

        return (object) array(
            'total_base_reward' => $total_base_reward,
            'total_reward' => $total_reward,
            'total_redeem' => $total_redeem,
            'total_reward_redeemable' => $total_reward_redeemable,
            'total_point' => $total_point
        );
    }
}