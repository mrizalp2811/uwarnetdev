<?php

namespace App\Controller\Portal;

use App\Controller\BaseController;
use App\Model\Operator;
use App\Model\Sms;
use App\Model\MailQueue;
use Slim\Http\Request;
use Slim\Http\Response;

class TestController extends BaseController
{
    function getRoutes()
    {
        $routes = array(
            'get' => array(
                '/test' => array('test'),
                // '/operator' => array('operator'),
                // '/sms' => array('sms')
            ),
        );
        return $routes;
    }

    public function test($app)
    {
        // $msisdn = '082120082065';
        // $msisdn = '08172303407';
        // $msisdn = '081233888440';
        // $msisdn = '08562029958';
        // $msisdn = '089613094872';
        $msisdn = '083822850041';
        $message = 'UWarnet';

        Sms::send($app, $msisdn, $message);
    }

    public function sms($app)
    {
        // $msisdn = '082120082065';
        // $msisdn = '08172303407';
        // $message = 'UWarnet';

        // Sms::send($app, $msisdn, $message);

        // $client = new \Nexmo\Client(new \Nexmo\Client\Credentials\Basic('91c69cae', '06bc43be41580f69'));
        // // $client = new \Nexmo\Client(new \Nexmo\Client\Credentials\Basic('bb475606', 'c5672c55a6321be8'));

        // try {
        //     // $message = $client->message()->send([
        //     //     'to' => $msisdn,
        //     //     'from' => 'UWARNET',
        //     //     'text' => $message
        //     // ]);

        //     // if ($message['status'] == 0) {
        //     //     echo 'sukses';
        //     // } else {
        //     //     echo $message['error-text'];
        //     // }

        //     $text = new \Nexmo\Message\Text($msisdn, 'UWARNET', $message);
        //     $client->message()->send($text);

        //     echo 'Status: '.$text->getStatus(); exit();
        // } catch (\Nexmo\Client\Exception\Request $e) {
        //     // echo $message['error-text'];
        //     $text     = $e->getEntity();
        //     $request  = $text->getRequest(); //PSR-7 Request Object
        //     $response = $text->getResponse(); //PSR-7 Response Object
        //     $data     = $text->getResponseData(); //parsed response object
        //     $code     = $e->getCode(); //nexmo error code

        //     echo json_encode($data['messages'][0]); exit();
        // }

        // // if ($message['status'] == 0) {
        // //     echo 'sukses';
        // // } else {
        // //     echo $message['error-text'];
        // // }

        // echo ' '.$message['remaining-balance']; exit();

        // // try {
        // //     $text = new \Nexmo\Message\Text('not valid', NEXMO_FROM, $longwinded);
        // //     $client->message()->send($text);
        // // } catch (\Nexmo\Client\Exception\Request $e) {
        // //     $text     = $e->getEntity();
        // //     $request  = $text->getRequest(); //PSR-7 Request Object
        // //     $response = $text->getResponse(); //PSR-7 Response Object
        // //     $data     = $text->getResponseData(); //parsed response object
        // //     $code     = $e->getCode(); //nexmo error code
        // //     error_log($e->getMessage()); //nexmo error message
        // // }
    }

    public function testing($app) {
        $request = $app->request;
        $response = $app->response;
        echo "testing oke";
    }

    public function operator($app)
    {
        $operator = Operator::find(1);
        $this->queue_mail_balance_limited($operator->warnet->owner, 50000); exit();
    }

    private function queue_mail_balance_limited($owner, $balance)
    {
        if ($balance < 100000) {
            if ($owner->warning == 0) {
                $mail_queue = new MailQueue;
                $mail_queue->email = $owner->email;
                $mail_queue->name = $owner->name;
                $mail_queue->type = $mail_queue->types['USER_BALANCE_LIMITED'];
                $mail_queue->params = json_encode(array(
                    'link' => 'https://uwarnet.id/wallet'
                ));
                $mail_queue->save();

                $owner->warning = 1;
                $owner->save();
            }
        }
    }
}