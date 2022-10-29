<?php

namespace App\Http\Controllers\Api;

use App\Help\Sign;
use App\Http\Controllers\Controller;
use App\Models\AdvanceLog;
use App\Models\Merchant;
use App\Models\MerchantAdvance;
use App\Models\MerchantChannel;
use App\Models\Order;
use App\Models\UpstreamChannel;
use App\Models\Upstream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use App\Models\Count;
class PayController extends Controller
{
    public function createOrder(Request $request) {
        set_time_limit(120);
        $request = $request->all();
        if(empty($request['mchId']) || empty($request['mchOrderNo']) || empty($request['channelId']) ||
            empty($request['amount']) || empty($request['notifyUrl']) || empty($request['subject']) ||
            empty($request['body']) || empty($request['clientIp']) || empty($request['sign'] )) {
            return [
                'status' => '30001',
                'msg' => 'Parameter error'
            ];
        }
        $orderModel = new Order();
        $order = $orderModel->where('mchOrderNo', $request['mchOrderNo'])
            ->where('customer_id', $request['mchId'])->first();
        if(!empty($order)) {
            return [
                'status' => '30007',
                'msg' => 'This order is available'
            ];
        }

        $cliSign = $request['sign'];
        foreach($request as $key => $value) {
            if(empty($value)) {
                unset($request[$key]);
            }
        }
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
        $sign = $signObj->encode($request, $merchant->token);

        if($sign != $cliSign) {
            return [
                'status' => '30003',
                'msg' => 'Signature error'
            ];
        }









        $upstreamChannelModel = new UpstreamChannel();
        $upstreamChannels = $upstreamChannelModel->where('code', $request['channelId'])
            ->where('status', 1)->get();
        if($upstreamChannels->isEmpty()) {
            return [
                'status' => '30004',
                'msg' => 'Channel stopped'
            ];
        }
        $ids = [];
        foreach($upstreamChannels as $upstreamChannel) {
            $ids[] = $upstreamChannel->id;
        }








        $merchantChannelModel = new MerchantChannel();
        $merchantChannels = $merchantChannelModel
            ->select('id','weight')
            ->whereIn('channel_id', $ids)
            ->where('merchant_id', $merchant->id)
            ->where('status', 1)->orderBy('weight', 'DESC')->get();
        if($merchantChannels->isEmpty()) {
            return [
                'status' => '30004',
                'msg' => 'Channel stopped'
            ];
        }
        $totalWeight = 0;

        foreach($merchantChannels as $mc) {
            $mc->start = $totalWeight;
            $totalWeight = $mc->weight + $totalWeight;
            $mc->end = $totalWeight;
        }
        $rant = rand(0, $totalWeight);
//        $cids = [];
//        foreach($merchantChannels as $mc) {
//            if($mc->start <= $rant && $mc->end >= $rant) {
//                $cids[] = $mc->toArray();
//            }
//        }
//        foreach ($merchantChannels as $mc) {
//            if($cids[0]['id'] != $mc->id) {
//                $cids[] = $mc->toArray();
//            }
//        }



        $cid = 0;
        foreach($merchantChannels as $mc) {
            if($mc->start <= $rant && $mc->end >= $rant) {
                $cid = $mc->id;
            }
        }





        $merchantChannel = $merchantChannelModel->where('id',$cid)
            ->where('status', 1)->first();

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
            $orderModel->code = $request['channelId'];
            $orderModel->upstream_id = $merchantChannel->channel->upstream->id;
            $orderModel->notifyUrl = $request['notifyUrl'];
            $orderModel->original_amount = $request['amount'];
            $orderModel->subject = $request['subject'];
            $orderModel->body = $request['body'];
//                $orderModel->amount = $request['amount'] - ($request['amount']/1000*$merchantChannel->rate);
            $log = [
                'xiajia_rate' => $merchantChannel->rate,
                'shangjia_rate' => $merchantChannel->channel->rate,
                'shangjia_id' =>$merchantChannel->id,
                'xiajia_id' => $merchantChannel->channel->id
            ];
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
                'msg' => 'Abnormal channel'
            ];
        }


    }

    public function notify(Request $request) {
        $upstreamModel = new Upstream();
        $upstream = $upstreamModel->where('ip', $_SERVER['REMOTE_ADDR'])->first();
        if(empty($upstream)) {
            return [
                'status' => '30009',
                'msg' => 'IP restrictions'
            ];
        }
        $modelName = 'App\Upstream'.'\\'.ucfirst($upstream->en_name);
//        $modelName = 'App\Upstream'.'\\'.'Xiyouji';
        $model = new $modelName;
        $res = $model->notify($request);
        if(isset($res['status']) && $res['status'] == 30003) {
            return [
                'status' => '30003',
                'msg' => 'Signature error'
            ];
        }
        if($res && $res->status != 2) {
            $balance = $res->upstream->balance - $res->amount;
            $upstreamModel = new Upstream();
            $upstreamModel->where('id', $res->upstream_id)->update([
                'balance' => $balance
            ]);
            $advanceModel = new AdvanceLog();
            if($balance < 0) {
                $beginToday=mktime(0,0,0,date('m'),date('d'),date('Y'));
                $endToday=mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;

                $advance = $advanceModel->where('upstream_id', $res->upstream_id)
                    ->where('type', 1)
                    ->where('created','>=', $beginToday)
                    ->where('created','<=', $endToday)
                    ->first();
                if(!empty($advance)){
                    $upstreamChannelModel = new UpstreamChannel();
                    $upstreamChannelModel->where('upstream_id', $res->upstream_id)
                        ->update('status', 0);
                }

            }


            $mbalance = $res->customer->balance - $res->merchant_amount;
            $merchantModel = new Merchant();
            $merchantModel->where('id', $res->customer->id)->update([
                'balance' => $mbalance
            ]);

            $signObj = new sign();
            $key = $res->customer->token;
            $p = [
                'payOrderId' => 'T'.$res->OrderNo,
                'amount' => $res->original_amount,
                'mchOrderNo' => $res->mchOrderNo,
                'status' => 2,
            ];
            $data = json_encode([
                'number' => 5,
                'order_id' => $res->id
            ]);
            Redis::rpush('feedback_pool', $data);
            $sign = $signObj->encode($p, $key);
            $p['sign'] = $sign;
            @$response = Http::get($res->notifyUrl, $p);
            if(strtoupper($response->body()) != 'SUCCESS') {
                $data = json_encode([
                    'number' => 5,
                    'order_id' => $res->id
                ]);
                Redis::rpush('feedback_pool', $data);
            } else {
                $orderModel = new Order();
                $orderModel->where('id', $res->id)->update([
                    'status' => 2,
                    'is_notify' => 1
                ]);
            }

            return 'SUCCESS';
        }







    }

    public function notifyPost(Request $request)
    {
        error_log(print_r($request->all(), 1), 3, '333.txt');
        error_log(print_r($_SERVER, 1), 3, '444.txt');
        $upstreamModel = new Upstream();
        $upstream = $upstreamModel->where('ip', $_SERVER['REMOTE_ADDR'])->first();
        if (empty($upstream)) {
            return [
                'status' => '30009',
                'msg' => 'IP restrictions'
            ];
        }
        $modelName = 'App\Upstream' . '\\' . ucfirst($upstream->en_name);
//        $modelName = 'App\Upstream'.'\\'.'Kuohai';
        $model = new $modelName;
        $res = $model->notify($request);
        if (isset($res['status']) && $res['status'] == 30003) {
            return [
                'status' => '30003',
                'msg' => 'Signature error'
            ];
        }

        if ($res && $res->status != 2) {
            $balance = $res->upstream->balance - $res->amount;
            $upstreamModel = new Upstream();
            $upstreamModel->where('id', $res->upstream_id)->update([
                'balance' => $balance
            ]);
            $advanceModel = new AdvanceLog();
            if ($balance < 0) {
                $beginToday = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
                $endToday = mktime(0, 0, 0, date('m'), date('d') + 1, date('Y')) - 1;

                $advance = $advanceModel->where('upstream_id', $res->upstream_id)
                    ->where('type', 1)
                    ->where('created','>=', $beginToday)
                    ->where('created','<=', $endToday)
                    ->first();
                if (!empty($advance)) {
                    $upstreamChannelModel = new UpstreamChannel();
                    $upstreamChannelModel->where('upstream_id', $res->upstream_id)
                        ->update('status', 0);
                }

            }

            $mbalance = $res->customer->balance - $res->merchant_amount;
            $merchantModel = new Merchant();
            $merchantModel->where('id', $res->customer->id)->update([
                'balance' => $mbalance
            ]);

            $signObj = new sign();
            $key = $res->customer->token;
            $p = [
                'payOrderId' => 'T' . $res->OrderNo,
                'amount' => $res->original_amount,
                'mchOrderNo' => $res->mchOrderNo,
                'status' => 2,
            ];
            $data = json_encode([
                'number' => 5,
                'order_id' => $res->id
            ]);
            Redis::rpush('feedback_pool', $data);
            $sign = $signObj->encode($p, $key);
            $p['sign'] = $sign;
            @$response = Http::get($res->notifyUrl, $p);
            if (strtoupper($response->body()) != 'SUCCESS') {
                $data = json_encode([
                    'number' => 5,
                    'order_id' => $res->id
                ]);
                Redis::rpush('feedback_pool', $data);
            } else {
                $orderModel = new Order();
                $orderModel->where('id', $res->id)->update([
                    'status' => 2,
                    'is_notify' => 1
                ]);
            }

            return 'SUCCESS';
        }

    }


    public function wuyouNotify(Request $request) {
        $modelName = 'App\Upstream'.'\\'.'Wuyou';
        $model = new $modelName;
        $res = $model->notify($request);
        if(isset($res['status']) && $res['status'] == 30003) {
            return [
                'status' => '30003',
                'msg' => 'Signature error'
            ];
        }

        if($res && $res->status != 2) {
            $balance = $res->upstream->balance - $res->amount;
            $upstreamModel = new Upstream();
            $upstreamModel->where('id', $res->upstream_id)->update([
                'balance' => $balance
            ]);


            $mbalance = $res->customer->balance - $res->merchant_amount;
            $merchantModel = new Merchant();
            $merchantModel->where('id', $res->customer->id)->update([
                'balance' => $mbalance
            ]);

            $signObj = new sign();
            $key = $res->customer->token;
            $p = [
                'payOrderId' => 'T'.$res->OrderNo,
                'amount' => $res->original_amount,
                'mchOrderNo' => $res->mchOrderNo,
                'status' => 2,
            ];
            $data = json_encode([
                'number' => 5,
                'order_id' => $res->id
            ]);
            Redis::rpush('feedback_pool', $data);
            $sign = $signObj->encode($p, $key);
            $p['sign'] = $sign;
            @$response = Http::get($res->notifyUrl, $p);
            if(strtoupper($response->body()) != 'SUCCESS') {
                $data = json_encode([
                    'number' => 5,
                    'order_id' => $res->id
                ]);
                Redis::rpush('feedback_pool', $data);
            } else {
                $orderModel = new Order();
                $orderModel->where('id', $res->id)->update([
                    'status' => 2,
                    'is_notify' => 1
                ]);
            }

            return 'success';
        }




}


    public function apprise($id) {
        $orderModel = new Order();
        $order = $orderModel->where('id', $id)->first();
        if($order->status == 2) {
            return 2;
        }
        if($order->status == 0) {
            return 3;
        }
        $status = $order->status;
        if($order->status == 1) {
            $status = 2;
        }
        $key = $order->customer->token;
        $p = [
            'payOrderId' => 'T'.$order->OrderNo,
            'amount' => $order->original_amount,
            'mchOrderNo' => $order->mchOrderNo,
            'status' => $status,
        ];
        $signObj = new Sign();
        $sign = $signObj->encode($p, $key);
        $p['sign'] = $sign;
        $response = Http::get($order->notifyUrl, $p);

        if(strtoupper($response->body()) == 'SUCCESS') {
            $orderModel->where('id', $id)->update([
                'status' => $status,
                'is_notify' => 1
            ]);
            return true;
        } else {
            return false;
        }

    }




    public function ytNotify(Request $request)
    {
//        $upstreamModel = new Upstream();
//        $upstream = $upstreamModel->where('ip', $_SERVER['REMOTE_ADDR'])->first();
//        if (empty($upstream)) {
//            return [
//                'status' => '30009',
//                'msg' => 'IP restrictions'
//            ];
//        }
//        $modelName = 'App\Upstream' . '\\' . ucfirst($upstream->en_name);
        $modelName = 'App\Upstream' . '\\' . 'Yt';
        $model = new $modelName;
        $res = $model->notify($request);
        if (isset($res['status']) && $res['status'] == 30003) {
            return [
                'status' => '30003',
                'msg' => 'Signature error'
            ];
        }
        if ($res && $res->status != 2) {
            $balance = $res->upstream->balance - $res->amount;
            $upstreamModel = new Upstream();
            $upstreamModel->where('id', $res->upstream_id)->update([
                'balance' => $balance
            ]);
            $advanceModel = new AdvanceLog();
            if($balance < 0) {
                $beginToday=mktime(0,0,0,date('m'),date('d'),date('Y'));
                $endToday=mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;

                $advance = $advanceModel->where('upstream_id', $res->upstream_id)
                    ->where('type', 1)
                    ->where('created','>=', $beginToday)
                    ->where('created','<=', $endToday)
                    ->first();
                if(!empty($advance)){
                    $upstreamChannelModel = new UpstreamChannel();
                    $upstreamChannelModel->where('upstream_id', $res->upstream_id)
                        ->update('status', 0);
                }

            }

            $mbalance = $res->customer->balance - $res->merchant_amount;
            $merchantModel = new Merchant();
            $merchantModel->where('id', $res->customer->id)->update([
                'balance' => $mbalance
            ]);

            $signObj = new sign();
            $key = $res->customer->token;
            $p = [
                'payOrderId' => 'T' . $res->OrderNo,
                'amount' => $res->original_amount,
                'mchOrderNo' => $res->mchOrderNo,
                'status' => 2,
            ];
            $data = json_encode([
                'number' => 5,
                'order_id' => $res->id
            ]);
            Redis::rpush('feedback_pool', $data);
            $sign = $signObj->encode($p, $key);
            $p['sign'] = $sign;
            @$response = Http::get($res->notifyUrl, $p);
            if (strtoupper($response->body()) != 'SUCCESS') {
                $data = json_encode([
                    'number' => 5,
                    'order_id' => $res->id
                ]);
                Redis::rpush('feedback_pool', $data);
            } else {
                $orderModel = new Order();
                $orderModel->where('id', $res->id)->update([
                    'status' => 2,
                    'is_notify' => 1
                ]);
            }

            return 'SUCCESS';
        }


    }


    public function notifyTest(Request $request) {
//        $upstreamModel = new Upstream();
//        $upstream = $upstreamModel->where('ip', $_SERVER['REMOTE_ADDR'])->first();
//        if(empty($upstream)) {
//            return [
//                'status' => '30009',
//                'msg' => 'IP restrictions'
//            ];
//        }
//        $modelName = 'App\Upstream'.'\\'.ucfirst($upstream->en_name);
//        $model = new $modelName;
//        $res = $model->notify($request);
//        if(isset($res['status']) && $res['status'] == 30003) {
//            return [
//                'status' => '30003',
//                'msg' => 'Signature error'
//            ];
//        }
//        if($res && $res->status != 2) {
//            $balance = $res->upstream->balance - $res->amount;
//            $upstreamModel = new Upstream();
//            $upstreamModel->where('id', $res->upstream_id)->update([
//                'balance' => $balance
//            ]);
//            $advanceModel = new AdvanceLog();
//            $advanceModel->upstream_id = $res->upstream_id;
//            $advanceModel->user_id = 0;
//            $advanceModel->amount = $res->amount;
//            $advanceModel->type = 2;
//            $advanceModel->balance = $balance;
//            $advanceModel->created = time();
//            $advanceModel->save();
//            $mbalance = $res->customer->balance - $res->merchant_amount;
//            $merchantModel = new Merchant();
//            $merchantModel->where('id', $res->customer->id)->update([
//                'balance' => $mbalance
//            ]);
//            $madvanceModel = new MerchantAdvance();
//            $madvanceModel->merchant_id = $res->customer->id;
//            $madvanceModel->user_id = 0;
//            $madvanceModel->amount = $res->merchant_amount;
//            $madvanceModel->type = 2;
//            $madvanceModel->balance = $mbalance;
//            $madvanceModel->recharge_time = time();
//            $madvanceModel->created = time();
//            $madvanceModel->save();
//            $signObj = new sign();
//            $key = $res->customer->token;
//            $p = [
//                'payOrderId' => 'T'.$res->OrderNo,
//                'amount' => $res->original_amount,
//                'mchOrderNo' => $res->mchOrderNo,
//                'status' => 2,
//            ];
//            $sign = $signObj->encode($p, $key);
//            $p['sign'] = $sign;
//            @$response = Http::get($res->notifyUrl, $p);
//            if(strtoupper($response->body()) != 'SUCCESS') {
//                $data = json_encode([
//                    'number' => 5,
//                    'order_id' => $res->id
//                ]);
//                Redis::rpush('feedback_pool', $data);
//            } else {
//                $orderModel = new Order();
//                $orderModel->where('id', $res->id)->update([
//                    'status' => 2,
//                    'is_notify' => 1
//                ]);
//            }
//
            return 'SUCCESS';
//        }







    }




    public function PxNotify(Request $request)
    {
//        $upstreamModel = new Upstream();
//        $upstream = $upstreamModel->where('ip', $_SERVER['REMOTE_ADDR'])->first();
//        if(empty($upstream)) {
//            return [
//                'status' => '30009',
//                'msg' => 'IP restrictions'
//            ];
//        }
//        $modelName = 'App\Upstream'.'\\'.ucfirst($upstream->en_name);
        $modelName = 'App\Upstream' . '\\' . 'Pangxie';
        $model = new $modelName;
        $res = $model->notify($request);
        if (isset($res['status']) && $res['status'] == 30003) {
            return [
                'status' => '30003',
                'msg' => 'Signature error'
            ];
        }
        if ($res && $res->status != 2) {
            $balance = $res->upstream->balance - $res->amount;
            $upstreamModel = new Upstream();
            $upstreamModel->where('id', $res->upstream_id)->update([
                'balance' => $balance
            ]);
            $advanceModel = new AdvanceLog();
            if ($balance < 0) {
                $beginToday = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
                $endToday = mktime(0, 0, 0, date('m'), date('d') + 1, date('Y')) - 1;

                $advance = $advanceModel->where('upstream_id', $res->upstream_id)
                    ->where('type', 1)
                    ->where('created', '>=', $beginToday)
                    ->where('created', '<=', $endToday)
                    ->first();
                if (!empty($advance)) {
                    $upstreamChannelModel = new UpstreamChannel();
                    $upstreamChannelModel->where('upstream_id', $res->upstream_id)
                        ->update('status', 0);
                }

            }


            $mbalance = $res->customer->balance - $res->merchant_amount;
            $merchantModel = new Merchant();
            $merchantModel->where('id', $res->customer->id)->update([
                'balance' => $mbalance
            ]);

            $signObj = new sign();
            $key = $res->customer->token;
            $p = [
                'payOrderId' => 'T' . $res->OrderNo,
                'amount' => $res->original_amount,
                'mchOrderNo' => $res->mchOrderNo,
                'status' => 2,
            ];
            $data = json_encode([
                'number' => 5,
                'order_id' => $res->id
            ]);
            Redis::rpush('feedback_pool', $data);
            $sign = $signObj->encode($p, $key);
            $p['sign'] = $sign;
            @$response = Http::get($res->notifyUrl, $p);
            if (strtoupper($response->body()) != 'SUCCESS') {
                $data = json_encode([
                    'number' => 5,
                    'order_id' => $res->id
                ]);
                Redis::rpush('feedback_pool', $data);
            } else {
                $orderModel = new Order();
                $orderModel->where('id', $res->id)->update([
                    'status' => 2,
                    'is_notify' => 1
                ]);
            }

            return 'SUCCESS';
        }
    }

    public function WgNotify(Request $request)
    {
//        $upstreamModel = new Upstream();
//        $upstream = $upstreamModel->where('ip', $_SERVER['REMOTE_ADDR'])->first();
//        if(empty($upstream)) {
//            return [
//                'status' => '30009',
//                'msg' => 'IP restrictions'
//            ];
//        }
//        $modelName = 'App\Upstream'.'\\'.ucfirst($upstream->en_name);
        $modelName = 'App\Upstream' . '\\' . 'Wanguan';
        $model = new $modelName;
        $res = $model->notify($request);
        if (isset($res['status']) && $res['status'] == 30003) {
            return [
                'status' => '30003',
                'msg' => 'Signature error'
            ];
        }
        if ($res && $res->status != 2) {
            $balance = $res->upstream->balance - $res->amount;
            $upstreamModel = new Upstream();
            $upstreamModel->where('id', $res->upstream_id)->update([
                'balance' => $balance
            ]);
            $advanceModel = new AdvanceLog();
            if ($balance < 0) {
                $beginToday = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
                $endToday = mktime(0, 0, 0, date('m'), date('d') + 1, date('Y')) - 1;

                $advance = $advanceModel->where('upstream_id', $res->upstream_id)
                    ->where('type', 1)
                    ->where('created', '>=', $beginToday)
                    ->where('created', '<=', $endToday)
                    ->first();
                if (!empty($advance)) {
                    $upstreamChannelModel = new UpstreamChannel();
                    $upstreamChannelModel->where('upstream_id', $res->upstream_id)
                        ->update('status', 0);
                }

            }


            $mbalance = $res->customer->balance - $res->merchant_amount;
            $merchantModel = new Merchant();
            $merchantModel->where('id', $res->customer->id)->update([
                'balance' => $mbalance
            ]);

            $signObj = new sign();
            $key = $res->customer->token;
            $p = [
                'payOrderId' => 'T' . $res->OrderNo,
                'amount' => $res->original_amount,
                'mchOrderNo' => $res->mchOrderNo,
                'status' => 2,
            ];
            $data = json_encode([
                'number' => 5,
                'order_id' => $res->id
            ]);
            Redis::rpush('feedback_pool', $data);
            $sign = $signObj->encode($p, $key);
            $p['sign'] = $sign;
            @$response = Http::get($res->notifyUrl, $p);
            if (strtoupper($response->body()) != 'SUCCESS') {
                $data = json_encode([
                    'number' => 5,
                    'order_id' => $res->id
                ]);
                Redis::rpush('feedback_pool', $data);
            } else {
                $orderModel = new Order();
                $orderModel->where('id', $res->id)->update([
                    'status' => 2,
                    'is_notify' => 1
                ]);
            }

            return 'SUCCESS';
        }
    }


    public function jdNotify(Request $request)
    {
//        $upstreamModel = new Upstream();
//        $upstream = $upstreamModel->where('ip', $_SERVER['REMOTE_ADDR'])->first();
//        if(empty($upstream)) {
//            return [
//                'status' => '30009',
//                'msg' => 'IP restrictions'
//            ];
//        }
//        $modelName = 'App\Upstream'.'\\'.ucfirst($upstream->en_name);
        error_log(print_r($request->all(),1),3,'jindun.txt');
        $modelName = 'App\Upstream' . '\\' . 'Jindun';
        $model = new $modelName;
        $res = $model->notify($request);
        if (isset($res['status']) && $res['status'] == 30003) {
            return [
                'status' => '30003',
                'msg' => 'Signature error'
            ];
        }
        if ($res && $res->status != 2) {
            $balance = $res->upstream->balance - $res->amount;
            $upstreamModel = new Upstream();
            $upstreamModel->where('id', $res->upstream_id)->update([
                'balance' => $balance
            ]);
            $advanceModel = new AdvanceLog();
            if ($balance < 0) {
                $beginToday = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
                $endToday = mktime(0, 0, 0, date('m'), date('d') + 1, date('Y')) - 1;

                $advance = $advanceModel->where('upstream_id', $res->upstream_id)
                    ->where('type', 1)
                    ->where('created', '>=', $beginToday)
                    ->where('created', '<=', $endToday)
                    ->first();
                if (!empty($advance)) {
                    $upstreamChannelModel = new UpstreamChannel();
                    $upstreamChannelModel->where('upstream_id', $res->upstream_id)
                        ->update('status', 0);
                }

            }


            $mbalance = $res->customer->balance - $res->merchant_amount;
            $merchantModel = new Merchant();
            $merchantModel->where('id', $res->customer->id)->update([
                'balance' => $mbalance
            ]);

            $signObj = new sign();
            $key = $res->customer->token;
            $p = [
                'payOrderId' => 'T' . $res->OrderNo,
                'amount' => $res->original_amount,
                'mchOrderNo' => $res->mchOrderNo,
                'status' => 2,
            ];
            $data = json_encode([
                'number' => 5,
                'order_id' => $res->id
            ]);
            Redis::rpush('feedback_pool', $data);
            $sign = $signObj->encode($p, $key);
            $p['sign'] = $sign;
            @$response = Http::get($res->notifyUrl, $p);
            if (strtoupper($response->body()) != 'SUCCESS') {
                $data = json_encode([
                    'number' => 5,
                    'order_id' => $res->id
                ]);
                Redis::rpush('feedback_pool', $data);
            } else {
                $orderModel = new Order();
                $orderModel->where('id', $res->id)->update([
                    'status' => 2,
                    'is_notify' => 1
                ]);
            }

            return 'OK';
        }
    }

}
