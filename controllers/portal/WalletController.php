<?php

namespace App\Controller\Portal;

use App\Controller\Portal\BasePortalController;
use App\Model\Owner;
use App\Model\Topup;
use App\Model\Wallet;
use App\Model\MailQueue;
use Hashids\Hashids;

class WalletController extends BasePortalController
{
    function getRoutes() {
        $routes = array(
            'get' => array(
                '' => array('view', $this->auth($this->getApp())),
                '/history' => array('history', $this->auth($this->getApp())),
                // '/topup/indihome/init/:hashid' => array('topup_indihome_init', $this->auth($this->getApp())),
                // '/topup/indihome/confirm/:hashid/:topup_hashid' => array('topup_indihome_confirm', $this->auth($this->getApp())),
                // '/topup/tcash/init/:hashid' => array('topup_tcash_init', $this->auth($this->getApp())),
                // '/topup/tcash/confirm/:hashid/:topup_hashid' => array('topup_tcash_confirm', $this->auth($this->getApp())),
                '/topup/banktransfer/init/:hashid' => array('topup_banktransfer_init', $this->auth($this->getApp())),
                '/topup/banktransfer/confirm/:hashid/:topup_hashid' => array('topup_banktransfer_confirm', $this->auth($this->getApp())),
            ),
            'post' => array(
                '' => array('create', $this->auth($this->getApp())),
                '/register' => array('register', $this->auth($this->getApp())),
                '/activate' => array('activate', $this->auth($this->getApp())),
                '/deactivate' => array('deactivate', $this->auth($this->getApp())),
                // '/topup/indihome/init/:hashid' => array('do_topup_indihome_init', $this->auth($this->getApp())),
                // '/topup/indihome/confirm/:hashid/:topup_hashid' => array('do_topup_indihome_confirm', $this->auth($this->getApp())),
                // '/topup/tcash/init/:hashid' => array('do_topup_tcash_init', $this->auth($this->getApp())),
                // '/topup/tcash/confirm/:hashid/:topup_hashid' => array('do_topup_tcash_confirm', $this->auth($this->getApp())),
                '/topup/banktransfer/init/:hashid' => array('do_topup_banktransfer_init', $this->auth($this->getApp())),
            ),            
            'delete' => array(
                '' => array('destroy', $this->auth($this->getApp()))
            )
        );
        return $routes;
    }

    public function view($app) {
        $wallets = array();

        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();
        if ($owner) {
            $wallets = Wallet::where('owner_id', '=', $owner->id)
                ->where('is_deleted', '=', 0)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        $hashids = new Hashids($app->container->settings['app_key'].$owner->id, 8, 'abcdefghijklmnopqrstuvwxyz1234567890');
        
        $saldo = 'N\A';
        $topups = array();
        if ($wallets && !$wallets->isEmpty()) {
            $topups = Topup::where('wallet_id', $wallets->first()->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            $saldo = $wallets->first()->balance() !== false ? "Rp.".number_format($wallets->first()->balance(), 0, ',', '.') : 'N/A';
        }

        $app->render('portal/header.php', array(
            'name' => $_SESSION['name']
        ));
        $app->render('portal/wallet.php', array(
            'hashids' => $hashids,
            'wallets' => $wallets,
            'saldo' => $saldo,
            'topups' => $topups,
            'assets' => array(
                'js' => array(
                    'portal-wallet.js?v=2'
                )
            )
        ));
        $app->render('portal/footer.php');
    }

    public function history($app)
    {
        $wallets = array();

        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();
        if ($owner) {
            $wallets = Wallet::where('owner_id', '=', $owner->id)
                ->where('is_deleted', '=', 0)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        $hashids = new Hashids($app->container->settings['app_key'].$owner->id, 8, 'abcdefghijklmnopqrstuvwxyz1234567890');
        
        $saldo = 'N\A';
        $topups = array();
        if ($wallets && !$wallets->isEmpty()) {
            $saldo = $wallets->first()->balance() !== false ? "Rp.".number_format($wallets->first()->balance(), 0, ',', '.') : 'N/A';
            $total_topup = Topup::where('wallet_id', $wallets->first()->id)
                ->where('status', 2)
                ->sum('amount');
            // $first_topup = Topup::where('wallet_id', $wallets->first()->id)
            //     ->where('status', 2)
            //     ->orderBy('created_at', 'asc')
            //     ->first()->created_at;
            // $last_topup = Topup::where('wallet_id', $wallets->first()->id)
            //     ->where('status', 2)
            //     ->orderBy('created_at', 'desc')
            //     ->first()->created_at;
            // $biggest_topup = Topup::where('wallet_id', $wallets->first()->id)
            //     ->where('status', 2)
            //     ->where('amount', '>', 0)
            //     ->orderBy('amount', 'desc')
            //     ->first()->amount;
            // $smallest_topup = Topup::where('wallet_id', $wallets->first()->id)
            //     ->where('status', 2)
            //     ->where('amount', '>', 0)
            //     ->orderBy('amount', 'asc')
            //     ->first()->amount;
            // $average_topup = Topup::where('wallet_id', $wallets->first()->id)
            //     ->where('status', 2)
            //     ->where('amount', '>', 0)
            //     ->avg('amount');
            // $success_topup = Topup::where('wallet_id', $wallets->first()->id)
            //     ->where('status', 2)
            //     ->count();
            // $failed_topup = Topup::where('wallet_id', $wallets->first()->id)
            //     ->where('status', '>', 2)
            //     ->count();

            // $topups = Topup::where('wallet_id', $wallets->first()->id)
            //     ->orderBy('created_at', 'desc')
            //     ->limit(10)
            //     ->get();

            $topup_builder = Topup::query();
            $topup_builder->where('wallet_id', $wallets->first()->id);

            $url = '/wallet/history';
            $query_params = array();

            $topup_collection = $topup_builder->get();

            $limit = null;
            $offset = null;
            $from_count = null;
            $to_count = null;
            $number_of_items = 0;
            $total_price = 0;
            $total_sale_price = 0;
            if (!$topup_collection->isEmpty()) {
                $number_of_items = $topup_collection->count();

                $limit = 10;
                $number_of_page = ceil($number_of_items / $limit);

                $page = $app->request->get('page') ? $app->request->get('page') : $number_of_page;

                $offset = ($page - 1) * $limit;
                $from_count = $offset + 1;
                $to_count = $offset + $limit;

                $total_price = $topup_collection->sum('price');
                $total_sale_price = $topup_collection->sum('sale_price');
                $topups = $topup_collection->slice($offset, $limit)->all();

                if ($number_of_items < $limit || $to_count > $number_of_items) {
                    $to_count = $number_of_items;
                }

                $pagination = new \stdClass();
                $query_params['page'] = 1;
                $pagination->first = (object) array(
                    'url' => ($page - 1 != 0) ? $url."?".http_build_query($query_params) : false
                );
                $query_params['page'] = $page - 1;
                $pagination->prev = (object) array(
                    'url' => ($page - 1 != 0) ? $url."?".http_build_query($query_params) : false
                );
                $pagination->pages = array();
                $max_links = 5;
                if ($number_of_page <= 2 * $max_links) {
                    $min_pagination = 1;
                    $max_pagination = $number_of_page;
                } else {
                    if ($page - $max_links < 1) {
                        $min_pagination = 1;
                        $max_pagination = 2 * $max_links + 1;
                    } else if ($page + $max_links > $number_of_page) {
                        $min_pagination = $number_of_page - (2 * $max_links) - 1;
                        $max_pagination = $number_of_page;
                    } else {
                        $min_pagination = $page - $max_links;
                        $max_pagination = $page + $max_links;
                    }
                }
                for ($i = $min_pagination; $i <= $max_pagination; $i++) { 
                    if ($i == $min_pagination && $min_pagination !== 1) {
                        $link = new \stdClass();
                        $link->caption = 1;
                        if ($i == $page) {
                            $link->url = false;
                            $link->current_page = true;
                        } else {
                            $query_params['page'] = 1;
                            $link->url = $url."?".http_build_query($query_params);
                            $link->current_page = false;
                        }
                        $pagination->pages[] = $link;

                        $link = new \stdClass();
                        $link->caption = '..';
                        $link->url = false;
                        $link->current_page = false;
                        $pagination->pages[] = $link;
                    }
                    $link = new \stdClass();
                    $link->caption = $i;
                    if ($i == $page) {
                        $link->url = false;
                        $link->current_page = true;
                    } else {
                        $query_params['page'] = $i;
                        $link->url = $url."?".http_build_query($query_params);
                        $link->current_page = false;
                    }
                    $pagination->pages[] = $link;
                    if ($i == $max_pagination && $max_pagination !== $number_of_page) {
                        $link = new \stdClass();
                        $link->caption = '..';
                        $link->url = false;
                        $link->current_page = false;
                        $pagination->pages[] = $link;

                        $link = new \stdClass();
                        $link->caption = $number_of_page;
                        if ($i == $page) {
                            $link->url = false;
                            $link->current_page = true;
                        } else {
                            $query_params['page'] = $number_of_page;
                            $link->url = $url."?".http_build_query($query_params);
                            $link->current_page = false;
                        }
                        $pagination->pages[] = $link;
                    }
                }
                $query_params['page'] = $page + 1;
                $pagination->next = (object) array(
                    'url' => ($page != $number_of_page) ? $url."?".http_build_query($query_params) : false
                );
                $query_params['page'] = $number_of_page;
                $pagination->last = (object) array(
                    'url' => ($page != $number_of_page) ? $url."?".http_build_query($query_params) : false
                );
            }
        }

        $queries = $app->request->get();
        $is_query_exist = $queries && (!(count($queries == 1) && array_key_exists('page', $queries))) ? true : false;

        $app->render('portal/header.php', array(
            'name' => $_SESSION['name']
        ));
        $app->render('portal/wallet_history.php', array(
            'hashids' => $hashids,
            'wallets' => $wallets,
            'saldo' => $saldo,
            'topups' => $topups,
            'filter' => $is_query_exist,
            'pagination' => $pagination,
            'offset' => $offset,
            'from_count' => $from_count,
            'to_count' => $to_count,
            'number_of_items' => $number_of_items,
            'total_topup' => $total_topup,
            // 'first_topup' => $first_topup,
            // 'last_topup' => $last_topup,
            // 'biggest_topup' => $biggest_topup,
            // 'smallest_topup' => $smallest_topup,
            // 'average_topup' => $average_topup,
            // 'success_topup' => $success_topup,
            // 'failed_topup' => $failed_topup,
            'assets' => array(
                'js' => array(
                    // 'portal-wallet.js?v=2'
                )
            )
        ));
        $app->render('portal/footer.php');
    }

    public function register($app) {
        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();

        if ($owner) {
            $wallet = new Wallet;
            $wallet->owner_id = $_SESSION['id'];
            $wallet->email = $email;

            $register_result = $wallet->register($email);
            if ($register_result->status === true) {
                $wallet->token = $register_result->payload->token;
                $wallet->active = 1;
                if ($wallet->save()) {

                } else {
                    $app->flash('error', 'Gagal menyimpan deposit');
                }
            } else {
                $app->flash('error', 'Gagal mengaktifkan deposit');
            }   
            $app->redirect($app->baseUrl().'/wallet');
        } else {
            $app->redirect($app->baseUrl().'/auth/login');
        }
    }

    public function create($app) {
        $email = $app->request->post('email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $wallet = new Wallet;
            $wallet->owner_id = $_SESSION['id'];
            $wallet->email = $email;

            $register_result = Wallet::register($email);
            if ($register_result->status === true) {
                $wallet->token = $register_result->payload->token;
                $wallet->active = 1;
                if ($wallet->save()) {

                } else {
                    $app->flash('error', 'Gagal menyimpan deposit');
                }
            } else {
                $app->flash('error', 'Gagal mengaktifkan deposit');
            }   
        } else {
            $app->flash('error', 'Email tidak valid');
        }
        $app->redirect($app->baseUrl().'/wallet');
    }

    public function destroy($app) {
        $id = $app->request->post('id');
        $wallet = Wallet::find($id);

        if ($_SESSION['id'] == $wallet->owner_id) {

            $wallet->is_deleted = 1;
            $wallet->save();
            $app->redirect($app->baseUrl().'/wallet');
        }
        $app->redirect($app->baseUrl().'/auth/login');
    }

    public function activate($app) {
        $id = $app->request->post('id');
        $wallet = Wallet::find($id);

        if ($_SESSION['id'] == $wallet->owner_id) {
            $wallet->active = 1;

            $wallet->save();
        }
        $app->redirect($app->baseUrl().'/wallet');
    }

    public function deactivate($app) {
        $id = $app->request->post('id');
        $wallet = Wallet::find($id);

        if ($_SESSION['id'] == $wallet->owner_id) {
            $wallet->active = 0;

            $wallet->save();
        }
        $app->redirect($app->baseUrl().'/wallet');
    }

    public function topup_indihome_init($app, $hashid) {
        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();

        if ($owner) {
            $hashids = new Hashids($app->container->settings['app_key'].$owner->id, 8, 'abcdefghijklmnopqrstuvwxyz1234567890');
            $array = $hashids->decode($hashid);

            if ($array) {
                $wallet_id = $array[0];
            
                $wallet = Wallet::find($wallet_id);
                if ($wallet && $wallet->owner_id == $owner->id) {
                    $app->render('portal/header.php', array(
                        'name' => $_SESSION['name']
                    ));
                    $app->render('portal/wallet_topup_indihome_init.php', array(
                        'hashid' => $hashid,
                        'wallet' => $wallet,
                        'assets' => array(
                            'js' => array(
                                'portal-wallet-topup-indihome-init.js?v=2'
                            )
                        )
                    ));
                    $app->render('portal/footer.php');
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

    public function do_topup_indihome_init($app, $hashid) {
        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();

        if ($owner) {
            $hashids = new Hashids($app->container->settings['app_key'].$owner->id, 8, 'abcdefghijklmnopqrstuvwxyz1234567890');
            $array = $hashids->decode($hashid);

            if ($array) {
                $wallet_id = $array[0];
                $id = $app->request->post('id');
                if ($wallet_id == $id) {
                    $wallet = Wallet::find($wallet_id);

                    if ($wallet && $wallet->owner_id == $owner->id) {
                        $amount = $app->request->post('amount');
                        $indihome_number = $app->request->post('indihome_number');

                        $topup_result = $wallet->topup_indihome_init($amount, $indihome_number);
                        if ($topup_result->status === true) {
                            $topup = new Topup;
                            $topup->wallet_id = $wallet->id;
                            $topup->type = 'indihome';
                            if (isset($topup_result->payload)) {
                                $topup_result->payload->indihome_number = $indihome_number;
                            }
                            $topup->info = json_encode($topup_result->payload);
                            $topup->amount = $amount;
                            $topup->trx_id = $topup_result->payload->trx_id;
                            $topup->status = 1;

                            if ($topup->save()) {
                                $topup_hashid = $hashids->encode($topup->id);
                                $app->redirect($app->baseUrl().'/wallet/topup/indihome/confirm/'.$hashid.'/'.$topup_hashid);
                            } else {
                                $app->flash('error', 'Gagal menyimpan informasi top up');
                            }
                        } else {
                            $app->flash('error', 'Gagal menginisiasi top up');
                        }
                        $app->redirect($app->baseUrl().'/wallet');
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

    public function topup_indihome_confirm($app, $hashid, $topup_hashid) {
        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();

        if ($owner) {
            $hashids = new Hashids($app->container->settings['app_key'].$owner->id, 8, 'abcdefghijklmnopqrstuvwxyz1234567890');
            $array = $hashids->decode($hashid);

            if ($array) {
                $wallet_id = $array[0];
            
                $wallet = Wallet::find($wallet_id);
                if ($wallet && $wallet->owner_id == $owner->id) {
                    $array_topup = $hashids->decode($topup_hashid);
                    if ($array_topup) {
                        $topup_id = $array_topup[0];

                        $topup = Topup::find($topup_id);
                        // if ($topup && $topup->wallet_id == $wallet->id && $topup->type == 'indihome') {
                        if ($topup && $topup->wallet_id == $wallet->id) {
                            if ($topup->status == 1) {
                                $info = json_decode($topup->info);
                                $phone = $info->phone;
                                $indihome_number = $info->indihome_number;
                                $amount = $topup->amount;

                                $app->render('portal/header.php', array(
                                    'name' => $_SESSION['name']
                                ));
                                $app->render('portal/wallet_topup_indihome_confirm.php', array(
                                    'hashid' => $hashid,
                                    'topup_hashid' => $topup_hashid,
                                    'indihome_number' => $indihome_number,
                                    'amount' => $amount,
                                    'phone' => $phone,
                                    'wallet' => $wallet
                                ));
                                $app->render('portal/footer.php');
                            } else {
                                if ($topup->status == 2) {
                                    $app->flash('error', 'Top up sudah berhasil sebelumnya');
                                } else if ($topup->status == 3) {
                                    $app->flash('error', 'Top up sudah gagal sebelumnya');
                                } else if ($topup->status == 4) {
                                    $app->flash('error', 'Top up sudah expired');
                                }
                                $app->redirect($app->baseUrl().'/wallet');
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
        } else {
            $app->redirect($app->baseUrl().'/auth/login');
        }
    }

    public function do_topup_indihome_confirm($app, $hashid, $topup_hashid) {
        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();

        if ($owner) {
            $hashids = new Hashids($app->container->settings['app_key'].$owner->id, 8, 'abcdefghijklmnopqrstuvwxyz1234567890');
            $array = $hashids->decode($hashid);

            if ($array) {
                $wallet_id = $array[0];
                $id = $app->request->post('id');
                if ($wallet_id == $id) {
                    $wallet = Wallet::find($wallet_id);

                    if ($wallet && $wallet->owner_id == $owner->id) {
                        $array_topup = $hashids->decode($topup_hashid);
                        if ($array_topup) {
                            $topup_id = $array_topup[0];

                            $topup = Topup::find($topup_id);
                            if ($topup && $topup->wallet_id == $wallet->id && $topup->type == 'indihome') {
                            // if ($topup && $topup->wallet_id == $wallet->id) {
                                if ($topup->status == 1) {
                                    $otp_token = $app->request->post('otp_token');
                                    $info = json_decode($topup->info);
                                    $trx_id = $info->trx_id;

                                    $topup_result = $wallet->topup_indihome_confirm($otp_token, $trx_id);
                                    if ($topup_result->status === true) {
                                        $topup->status = 2;
                                        $topup->balance_after = $topup_result->balance;

                                        if ($topup->save()) {
                                            $this->queue_mail_topup_success($owner, $topup);

                                            $app->flash('success', 'Top up berhasil');
                                        } else {
                                            $app->flash('error', 'Gagal menyimpan status top up');
                                        }
                                    } else {
                                        $app->flash('error', 'Gagal konfirmasi top up');
                                    }
                                } else if ($topup->status == 2) {
                                    $app->flash('error', 'Top up sudah berhasil sebelumnya');
                                } else if ($topup->status == 3) {
                                    $app->flash('error', 'Top up sudah gagal sebelumnya');
                                } else if ($topup->status == 4) {
                                    $app->flash('error', 'Top up sudah expired');
                                }
                                $app->redirect($app->baseUrl().'/wallet');
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
        } else {
            $app->redirect($app->baseUrl().'/auth/login');
        }
    }    

    public function topup_tcash_init($app, $hashid) {
        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();

        if ($owner) {
            $hashids = new Hashids($app->container->settings['app_key'].$owner->id, 8, 'abcdefghijklmnopqrstuvwxyz1234567890');
            $array = $hashids->decode($hashid);

            if ($array) {
                $wallet_id = $array[0];
            
                $wallet = Wallet::find($wallet_id);
                if ($wallet && $wallet->owner_id == $owner->id) {
                    $app->render('portal/header.php', array(
                        'name' => $_SESSION['name']
                    ));
                    $app->render('portal/wallet_topup_tcash_init.php', array(
                        'hashid' => $hashid,
                        'wallet' => $wallet,
                        'assets' => array(
                            'js' => array(
                                'portal-wallet-topup-tcash-init.js?v=2'
                            )
                        )
                    ));
                    $app->render('portal/footer.php');
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

    public function do_topup_tcash_init($app, $hashid) {
        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();

        if ($owner) {
            $hashids = new Hashids($app->container->settings['app_key'].$owner->id, 8, 'abcdefghijklmnopqrstuvwxyz1234567890');
            $array = $hashids->decode($hashid);

            if ($array) {
                $wallet_id = $array[0];
                $id = $app->request->post('id');
                if ($wallet_id == $id) {
                    $wallet = Wallet::find($wallet_id);

                    if ($wallet && $wallet->owner_id == $owner->id) {
                        $amount = $app->request->post('amount');
                        $tcash_number = $app->request->post('tcash_number');

                        $topup_result = $wallet->topup_tcash_init($amount, $tcash_number);
                        if ($topup_result->status === true) {
                            $topup = new Topup;
                            $topup->wallet_id = $wallet->id;
                            $topup->type = 'tcash';
                            $topup->info = json_encode($topup_result->payload);
                            $topup->amount = $amount;
                            $topup->trx_id = $topup_result->payload->trx_id;
                            $topup->status = 1;

                            if ($topup->save()) {
                                $topup_hashid = $hashids->encode($topup->id);
                                $app->redirect($app->baseUrl().'/wallet/topup/tcash/confirm/'.$hashid.'/'.$topup_hashid);
                            } else {
                                $app->flash('error', 'Gagal menyimpan informasi top up');
                            }
                        } else {
                            $app->flash('error', 'Gagal menginisiasi top up');
                        }
                        $app->redirect($app->baseUrl().'/wallet');
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

    public function topup_tcash_confirm($app, $hashid, $topup_hashid) {
        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();

        if ($owner) {
            $hashids = new Hashids($app->container->settings['app_key'].$owner->id, 8, 'abcdefghijklmnopqrstuvwxyz1234567890');
            $array = $hashids->decode($hashid);

            if ($array) {
                $wallet_id = $array[0];
            
                $wallet = Wallet::find($wallet_id);
                if ($wallet && $wallet->owner_id == $owner->id) {
                    $array_topup = $hashids->decode($topup_hashid);
                    if ($array_topup) {
                        $topup_id = $array_topup[0];

                        $topup = Topup::find($topup_id);
                        // if ($topup && $topup->wallet_id == $wallet->id && $topup->type == 'tcash') {
                        if ($topup && $topup->wallet_id == $wallet->id) {
                            if ($topup->status == 1) {
                                $info = json_decode($topup->info);
                                $confirmation_id = $info->data->conf_id;
                                $reference_number = $info->data->tx_id;

                                $app->render('portal/header.php', array(
                                    'name' => $_SESSION['name']
                                ));
                                $app->render('portal/wallet_topup_tcash_confirm.php', array(
                                    'hashid' => $hashid,
                                    'topup_hashid' => $topup_hashid,
                                    'confirmation_id' => $confirmation_id,
                                    'reference_number' => $reference_number,
                                    'wallet' => $wallet
                                ));
                                $app->render('portal/footer.php');
                            } else {
                                if ($topup->status == 2) {
                                    $app->flash('error', 'Top up sudah berhasil sebelumnya');
                                } else if ($topup->status == 3) {
                                    $app->flash('error', 'Top up sudah gagal sebelumnya');
                                } else if ($topup->status == 4) {
                                    $app->flash('error', 'Top up sudah expired');
                                }
                                $app->redirect($app->baseUrl().'/wallet');
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
        } else {
            $app->redirect($app->baseUrl().'/auth/login');
        }
    }

    public function do_topup_tcash_confirm($app, $hashid, $topup_hashid) {
        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();

        if ($owner) {
            $hashids = new Hashids($app->container->settings['app_key'].$owner->id, 8, 'abcdefghijklmnopqrstuvwxyz1234567890');
            $array = $hashids->decode($hashid);

            if ($array) {
                $wallet_id = $array[0];
                $id = $app->request->post('id');
                if ($wallet_id == $id) {
                    $wallet = Wallet::find($wallet_id);

                    if ($wallet && $wallet->owner_id == $owner->id) {
                        $array_topup = $hashids->decode($topup_hashid);
                        if ($array_topup) {
                            $topup_id = $array_topup[0];

                            $topup = Topup::find($topup_id);
                            if ($topup && $topup->wallet_id == $wallet->id && $topup->type == 'tcash') {
                            // if ($topup && $topup->wallet_id == $wallet->id) {
                                // $otp_token = $app->request->post('otp_token');
                                $info = json_decode($topup->info);
                                $trx_id = $info->trx_id;

                                $topup_result = $wallet->topup_tcash_confirm($trx_id);
                                if ($topup_result->status === true) {
                                    $topup->status = 2;
                                    $topup->balance_after = $topup_result->balance;

                                    if ($topup->save()) {
                                        $this->queue_mail_topup_success($owner, $topup);

                                        $app->flash('success', 'Topup berhasil');
                                    } else {
                                        $app->flash('error', 'Gagal menyimpan status top up');
                                    }
                                } else {
                                    $app->flash('error', 'Gagal konfirmasi top up');
                                }
                                $app->redirect($app->baseUrl().'/wallet');
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
        } else {
            $app->redirect($app->baseUrl().'/auth/login');
        }
    }   

    public function topup_banktransfer_init($app, $hashid) {
        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();

        if ($owner) {
            $hashids = new Hashids($app->container->settings['app_key'].$owner->id, 8, 'abcdefghijklmnopqrstuvwxyz1234567890');
            $array = $hashids->decode($hashid);

            if ($array) {
                $wallet_id = $array[0];
            
                $wallet = Wallet::find($wallet_id);
                if ($wallet && $wallet->owner_id == $owner->id) {
                    $app->render('portal/header.php', array(
                        'name' => $_SESSION['name']
                    ));
                    $app->render('portal/wallet_topup_banktransfer_init.php', array(
                        'hashid' => $hashid,
                        'wallet' => $wallet,
                        'assets' => array(
                            'js' => array(
                                'portal-wallet-topup-banktransfer-init.js?v=4'
                            )
                        )
                    ));
                    $app->render('portal/footer.php');
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

    public function do_topup_banktransfer_init($app, $hashid) {
        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();

        if ($owner) {
            $hashids = new Hashids($app->container->settings['app_key'].$owner->id, 8, 'abcdefghijklmnopqrstuvwxyz1234567890');
            $array = $hashids->decode($hashid);

            if ($array) {
                $wallet_id = $array[0];
                $id = $app->request->post('id');
                if ($wallet_id == $id) {
                    $wallet = Wallet::find($wallet_id);

                    if ($wallet && $wallet->owner_id == $owner->id) {
                        $amount = $app->request->post('amount');

                        $topup_result = $wallet->topup_banktransfer_init($amount);
                        if ($topup_result->status === true) {
                            $topup = new Topup;
                            $topup->wallet_id = $wallet->id;
                            $topup->type = 'transfer';
                            $topup->info = json_encode($topup_result->payload);
                            $topup->amount = $amount;
                            $topup->trx_id = $topup_result->payload->trx_id;
                            $topup->status = 1;

                            if ($topup->save()) {
                                $topup_hashid = $hashids->encode($topup->id);
                                $app->redirect($app->baseUrl().'/wallet/topup/banktransfer/confirm/'.$hashid.'/'.$topup_hashid);
                            } else {
                                $app->flash('error', 'Gagal menyimpan informasi top up');
                            }
                        } else {
                            $app->flash('error', 'Gagal menginisiasi top up');
                        }
                        $app->redirect($app->baseUrl().'/wallet');
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

    public function topup_banktransfer_confirm($app, $hashid, $topup_hashid) {
        $email = $_SESSION['email'];
        $owner = Owner::whereEmail($email)->whereActive(1)->first();

        if ($owner) {
            $hashids = new Hashids($app->container->settings['app_key'].$owner->id, 8, 'abcdefghijklmnopqrstuvwxyz1234567890');
            $array = $hashids->decode($hashid);

            if ($array) {
                $wallet_id = $array[0];
            
                $wallet = Wallet::find($wallet_id);
                if ($wallet && $wallet->owner_id == $owner->id) {
                    $array_topup = $hashids->decode($topup_hashid);
                    if ($array_topup) {
                        $topup_id = $array_topup[0];

                        $topup = Topup::find($topup_id);
                        // if ($topup && $topup->wallet_id == $wallet->id && $topup->type == 'tcash') {
                        if ($topup && $topup->wallet_id == $wallet->id) {
                            if ($topup->status == 1) {
                                $info = json_decode($topup->info);
                                $payment_code = $info->data->payment_code;
                                $amount = $info->data->amount;

                                $app->render('portal/header.php', array(
                                    'name' => $_SESSION['name']
                                ));
                                $app->render('portal/wallet_topup_banktransfer_confirm.php', array(
                                    'hashid' => $hashid,
                                    'topup_hashid' => $topup_hashid,
                                    'payment_code' => $payment_code,
                                    'amount' => $amount,
                                    'wallet' => $wallet
                                ));
                                $app->render('portal/footer.php');
                            } else {
                                if ($topup->status == 2) {
                                    $app->flash('error', 'Top up sudah berhasil sebelumnya');
                                } else if ($topup->status == 3) {
                                    $app->flash('error', 'Top up sudah gagal sebelumnya');
                                } else if ($topup->status == 4) {
                                    $app->flash('error', 'Top up sudah expired');
                                }
                                $app->redirect($app->baseUrl().'/wallet');
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
        } else {
            $app->redirect($app->baseUrl().'/auth/login');
        }
    }

    private function queue_mail_topup_success($owner, $topup)
    {
        switch ($topup->type) {
            case 'indihome':
                $topup_type = 'Tagihan IndiHome';
                break;
            case 'tcash':
                $topup_type = 'tcash';
                break;
            case 'transfer':
                $topup_type = 'Bank Transfer';
                break;
        }

        $mail_queue = new MailQueue;
        $mail_queue->email = $owner->email;
        $mail_queue->name = $owner->name;
        $mail_queue->type = $mail_queue->types['USER_TOPUP_SUCCESS'];
        $mail_queue->params = json_encode(array(
            'type' => $topup_type,
            'amount' => "Rp.".number_format($topup->amount, 0, ',', '.'),
            'topup_time' => strftime("%B %e, %Y @%H:%M", strtotime($topup->updated_at)),
            'link' => 'https://uwarnet.id/wallet'
        ));
        $mail_queue->save();

        $owner->warning = 0;
        $owner->save();
    }
}