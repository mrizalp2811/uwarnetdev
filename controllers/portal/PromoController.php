<?php

namespace App\Controller\Portal;

use App\Controller\Portal\BasePortalController;
use App\Model\Promo;
use Hashids\Hashids;

class PromoController extends BasePortalController
{
    function getRoutes() {
        return array(
            'get' => array(
                '' => array('index', $this->auth($this->getApp())),
                '/:id' => array('view'),
            ),
        );
    }

    public function index($app) {
        $promos = array();

        $promos = Promo::where('sent', 1)
            ->where('active', 1)
            ->orderBy('created_at', 'asc')
            ->get();
        
        $hashids = new Hashids("promo-salt", 8, 'abcdefghijklmnopqrstuvwxyz1234567890');

        $app->render('portal/header.php', array(
            'name' => $_SESSION['name']
        ));
        $app->render('portal/promo_index.php', array(
            'hashids' => $hashids,
            'promos' => $promos,
            'assets' => array(
                'js' => array(
                    // 'portal-promo-dev.js?v=1'
                )
            )
        ));
        $app->render('portal/footer.php');
    }

    public function view($app, $hashid) {
        $hashids = new Hashids("promo-salt", 8, 'abcdefghijklmnopqrstuvwxyz1234567890');

        $array = $hashids->decode($hashid);
        if ($array) {
            $id = $array[0];
            $promo = Promo::find($id);

            if ($promo->active) {
                $app->render('portal/promo.php', array(
                    'promo' => $promo
                ));   
            } else {
                $app->redirect($app->baseUrl().'/promo');
            }
        } else {
            $app->render('errors/404.php');
        }
    }
}