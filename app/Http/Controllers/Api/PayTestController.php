<?php

namespace App\Http\Controllers\Api;

use App\Help\Broadcast;
use App\Help\Sign;
use App\Http\Controllers\Controller;
use App\Models\AdvanceLog;
use App\Models\Count;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Upstream;
use App\Models\UpstreamChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use  Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use NotificationChannels\Telegram\TelegramUpdates;
use NotificationChannels\Telegram\TelegramMessage;
use NotificationChannels\Telegram\TelegramPoll;
use Illuminate\Support\Str;
class PayTestController extends Controller
{
    public function createOrder(Request $request) {
        $request = $request->all();
        if(empty($request['mchId']) || empty($request['mchOrderNo']) || empty($request['channelId']) ||
            empty($request['amount']) || empty($request['notifyUrl']) || empty($request['subject']) ||
            empty($request['body']) || empty($request['clientIp']) ) {
//            || empty($request['sign'])
//            return [
//                'status' => '30001',
//                'msg' => 'Parameter error'
//            ];
        }
//        $cliSign = $request['sign'];
        unset($request['sign']);
        $merchantModel = new Merchant();
        $merchant = $merchantModel->where('account', $request['mchId'])->first();
        if(empty($merchant)) {
            return [
                'status' => '30002',
                'msg' => 'Illegal mchId'
            ];
        }
        $signObj = new Sign();
        $sign = $signObj->encode($request,$merchant->token);
        echo $sign;
//        if($sign != $cliSign) {
//            return [
//                'status' => '30003',
//                'msg' => 'Broadcast error'
//            ];
//        }





    }

    public function test() {
        $response = Http::get('http://103.13.229.1:8081/api/pay/notify', [
            'aa' => 1232,
            'bb' => 123555,
        ])->body();
        echo $response;
        die;
        $response = Http::post('http://103.13.229.1:8081/api/pay/create_order', [
            'mchId' => 1100030,
            'mchOrderNo' => 202137424342,
            'amount' => 3000,
            'channelId' => 1218,
            'notifyUrl' => 'http://square.test/api/pay/notify',
            'subject' => 'weixinios',
            'body' => 'weixinios',
            'clientIp' => '223.204.246.162',
            'sign' => 'A10DC901C98F9C6CE352C5528166B958',
        ]);
        $aa = $response->json();
        dd($aa);
    }


    public function redis() {
        return 'SUCCESS';
//        $a = Redis::rpush('feedback_pool',json_encode([
//            'bb' => 1234
//        ]));
//        $a = Redis::blpop('feedback_pool', 1);
//        dd(json_decode($a[1])->bb);
        dd(Redis::llen('feedback_pool'));
    }

    public function notify(Request $request) {
        $beginToday=mktime(0,0,0,date('m'),date('d'),date('Y'));
        $endToday=mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;
        echo $beginToday.'@'.$endToday;
        die;
        $mchOrderNo = $request->get('mchOrderNo');
        $orderModel = new Order();
        $order = $orderModel->where('mchOrderNo', $mchOrderNo)->first();
        if(empty($order)) {
            return false;
        }
        if($request->get('status') == 2 && $order->status == 0) {
            $orderModel->where('mchOrderNo', $mchOrderNo)->update([
                'status' => 1,
                'pay_amount' => $request->get('total_fee'),
                'pay_time' => time()
            ]);
        }
        return 'SUCCESS';
    }

    public function bot()
    {
        echo Str::random(20);
    }

}
