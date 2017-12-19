<?php

namespace App\Controller\Promoter;

use App\Controller\Promoter\BasePromoterController;
use App\Model\Operator;
use App\Model\Owner;
use App\Model\Promoter;

class PromoteeController extends BasePromoterController
{
    function getRoutes() {
        return array(
            'get' => array(
                '' => array('view', $this->auth($this->getApp()))
            )
        );
    }

    public function view($app) {
        $email = $_SESSION['promoter_email'];
        $promoter = Promoter::whereEmail($email)->whereActive(1)->first();

        $promotees = $promoter->owners()->get();
        $total_transaction_amount = 0;
        if (count($promotees)) foreach ($promotees as $promotee) {
            if ($promotee->active) {
                $total_transaction_amount += $promotee->total_transaction_amount();
            }
        }

        $app->render('promoter/header.php', array(
            'name' => $_SESSION['promoter_name']
        ));
        $app->render('promoter/promotee.php', array(
            'total_transaction_amount' => $total_transaction_amount,
            'promotees' => $promotees
        ));
        $app->render('promoter/footer.php');
    }
}