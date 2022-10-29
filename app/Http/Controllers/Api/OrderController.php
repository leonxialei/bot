<?php

namespace App\Http\Controllers\Api;

use App\Help\Sign;
use App\Http\Controllers\Controller;
use App\Models\AdvanceLog;
use App\Models\Count;
use App\Models\Merchant;
use App\Models\MerchantAdvance;
use App\Models\MerchantChannel;
use App\Models\Order;
use App\Models\UpstreamChannel;
use App\Models\Upstream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
class OrderController extends Controller
{
    public function createOrder(Request $request) {
        $merchantChannelModel = new MerchantChannel();




        $request['mchId'] = 1100030;
        $request['mchOrderNo'] = 'TTT'.time();
        $request['amount'] = $request->get('amount')*100;
        $request['notifyUrl'] = 'http://103.13.229.1/api/pay/notify';
        $request['redirectUrl'] = 'http://103.13.229.1/api/pay/notify';
        $request['subject'] = 'testtest';
        $request['body'] = 'testtestbody';
        $request['clientIp'] = '223.204.246.162';


//        $para['channelId'] = $request->get('amount');







        $orderModel = new Order();
        $merchantChannel = $merchantChannelModel->where('channel_id', $request->get('channel_id'))
            ->where('merchant_id', 30)->where('status', 1)->first();
        if(empty($merchantChannel)) {
            return [
                'status' => '30008',
                'msg' => 'Without this channel'
            ];
        }
        if($merchantChannel->is_amount) {
            $amountType = explode(',', $merchantChannel->amount);
            if(!in_array($request['amount'], $amountType)) {
                return [
                    'status' => '30005',
                    'msg' => 'Recharge amount is incorrect'
                ];
            }
        }
        $count = Count::getCount();
        $orderNo = rand(1, 99).date('dsi').$count. rand(10, 99);
        Count::plus();

        $modelName = 'App\Upstream'.'\\'.ucfirst($merchantChannel->channel->upstream->en_name);
        $model = new $modelName;
        $request['channelId'] = $merchantChannel->channel->upstream_code;
        $request['orderNo'] = $orderNo;
        $result = $model->order($request);
        if($result['code'] == 1) {
            $orderModel = new Order();

            $orderModel->OrderNo = $orderNo;
            $orderModel->mchOrderNo = $request['mchOrderNo'];
            $orderModel->upOrderNo = $result['data']['payOrderId'];
            $orderModel->merchant_channel_id = $merchantChannel->id;
            $orderModel->upstream_id = $merchantChannel->channel->upstream->id;
            $orderModel->notifyUrl = $request['notifyUrl'];
            $orderModel->original_amount = $request['amount'];
            $orderModel->subject = $request['subject'];
            $orderModel->body = $request['body'];

            $orderModel->amount = $request['amount'] - ($request['amount'] * ($merchantChannel->channel->rate / 1000));
            $orderModel->merchant_amount = $request['amount'] - ($request['amount'] * ($merchantChannel->rate / 1000));
            $orderModel->customer_id = $request['mchId'];
            $orderModel->clientIp = $request['clientIp'];
            if(!empty($request['uid'])) {
                $orderModel->uid = $request['uid'];
            }
            if(!empty($request['bankCode'])) {
                $orderModel->bankCode = $request['bankCode'];
            }
            if(!empty($request['cardId'])) {
                $orderModel->cardId = $request['cardId'];
            }
            if(!empty($request['bankCardNo'])) {
                $orderModel->bankCardNo = $request['bankCardNo'];
            }
            if(!empty($request['phone'])) {
                $orderModel->phone = $request['phone'];
            }
            if(!empty($request['bankCardName'])) {
                $orderModel->bankCardName = $request['bankCardName'];
            }
            $orderModel->status = 0;
            $orderModel->created = time();
            $orderModel->pay_time = 0;
            $orderModel->save();
            if(!empty($result['data']['payUrl']) || $result['data']['qrImgUrl'] || !empty($result['data']['qrUrl'])) {
                return [
                    'status' => '200',
                    'msg' => 'SUCCESS',
                    "payOrderId" => 'T'.$orderNo,
                    "out_trade_no" => $request['mchOrderNo'],
                    "channelId" => $result['data']['channelId'],
                    "payUrl" => $result['data']['payUrl'],
                    "qrUrl" => $result['data']['qrUrl'],
                    "qrImgUrl" => $result['data']['qrImgUrl'],
                ];
            } else {
                return $result['data']['body'];
            }

        } else if($result['code'] == 0) {
            $error_data = [
                'data' => $result['data'],

                'channel_id' => $merchantChannel->channel->id
            ];
            if(isset( $result['para'])) {
                $error_data['para'] = $result['para'];
            }
            $error_data = json_encode($error_data);
            Redis::rpush('error_log', $error_data);
            return [
                'status' => '30006',
                'msg' => $error_data
            ];
        }


    }

    public function query(Request $request) {
        set_time_limit(120);
        $request = $request->all();
        if(empty($request['mchId']) || empty($request['mchOrderNo']) || empty($request['sign']) ) {
            return [
                'status' => '30001',
                'msg' => 'Parameter error'
            ];
        }
        $cliSign = $request['sign'];
        unset($request['sign']);
        $merchantModel = new Merchant();
        $merchant = $merchantModel->where('account', $request['mchId'])->first();
        if(empty($merchant)) {
            return [
                'status' => '30002',
                'msg' => 'No have this merchant!'
            ];
        }

        $signObj = new Sign();
        $sign = $signObj->encode($request, $merchant->token);
        if($sign != $cliSign) {
            return [
                'status' => '30003',
                'msg' => 'Signature error'
            ];
        }

        $orderModel = new Order();
        $orderModel = $orderModel->where('mchOrderNo', $request['mchOrderNo'])
            ->where('customer_id', $request['mchId']);

        if(isset($request['payOrderId'])) {
            $order_id = substr($request['payOrderId'], 1);
            $orderModel = $orderModel->where('OrderNo', $order_id);
        }
        $order = $orderModel->first();
        if(empty($order)){
            return [
                'status' => '30004',
                'msg' => 'No have this order!'
            ];
        }

        if(isset($request['executeNotify']) && $request['executeNotify'] == true) {
            $data = json_encode([
                'number' => 5,
                'order_id' => $order->id
            ]);
            Redis::rpush('feedback_pool', $data);
        }


        return [
            "msg" => "SUCCESS",
            "payOrderId" => 'T'.$order->OrderNo,
            "mchOrderNo" => $order->mchOrderNo,
            "amount" => $order->original_amount,
            "createTime" => $order->created,
            "payStatus" => $order->status,
            "status" => "200",
            "sign" => $sign
        ];
    }

}
