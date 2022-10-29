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
class NotifyController extends Controller
{

    public function dx(Request $request)
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
//        error_log(print_r($request->all(),1),3,'daxing.txt');
        $modelName = 'App\Upstream' . '\\' . 'Daxing';
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


    public function br(Request $request)
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
        $modelName = 'App\Upstream' . '\\' . 'Bangrui';
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



    public function facai(Request $request)
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
        $modelName = 'App\Upstream' . '\\' . 'Facai';
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



    public function hudie(Request $request)
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
//        error_log(print_r($request->all(),1),3,'hudie.txt');
        $modelName = 'App\Upstream' . '\\' . 'Hudie';
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

}
