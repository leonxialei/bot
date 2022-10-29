<?php

namespace App\Mission;
use App\Http\Controllers\Api\PayController;
use App\Models\AdvanceLog;
use App\Models\Order;
use App\Models\Upstream;
use App\Models\UpstreamChannel;
use  Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class Broadcast {
    public function apprise() {
        $total = Redis::llen('feedback_pool');
        if($total > 50) {
            $total = 50;
        }
        for($i = 1; $i <= $total; $i++) {
            $data = Redis::blpop('feedback_pool', 1);
            $data = json_decode($data[1]);
            $number = $data->number - 1;
            $PayController = new PayController();
            if($PayController->apprise($data->order_id) != true) {
                if($number > 0) {
                    Redis::rpush('feedback_pool',json_encode([
                        'number' => $number,
                        'order_id' => $data->order_id
                    ]));
                }

            }
        }

    }

    public function bot() {
        for($i = 0; $i <= 30; $i++) {
            $data = Redis::blpop('error_log', 1);
            if(isset($data)) {
                $data = json_decode($data[1], true);
                if(isset($data['para'])) {
                    $para = json_decode($data['para'],1);
                } else {
                    $para = '';
                }

                $error = json_decode($data['data'],1);
                $channel_id = $data['channel_id'];
                $channelModel = new UpstreamChannel();
                $channel = $channelModel->find($channel_id);
                $word = '########「'.$channel->name.'」########'."\n";
                if(!empty($para)){
                    $word = $word.'请求参数:'.json_encode($para,JSON_UNESCAPED_UNICODE)."\n";
                }
                $word = $word.'响应结果:'.json_encode($error,JSON_UNESCAPED_UNICODE)."\n";
                $token = config('services.telegram-bot-api.token');
                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                $parameters = [
                    'chat_id' => '-777614738',
                    'text' => $word
                ];
                Http::post($url, $parameters);
            }
            sleep(2);
        }


    }

    public function debt()
    {
        $start_date = date('Y-m-d');
        $end_date = $start_date;
        $upstreamModel = new Upstream();
        $upstreams = $upstreamModel->where('status', 1)->get();
        $start_time = strtotime($start_date.' 00:00:00');
        $end_time = strtotime($end_date.' 23:59:59');
        foreach($upstreams as $upstream) {
            $order = Order::log_change($upstream->id, $start_date, $end_date);
            $quantity = Order::upstreamDetail($upstream->id, $start_date, $end_date);
            $amount = $order - $quantity->amount;
            if(!empty($quantity->amount)) {
                if($amount <= 100000) {
                    $logModel = new AdvanceLog();
                    $log = $logModel->where('upstream_id', $upstream->id)
                        ->where('type', 1)
                        ->where('created', '>=', $start_time)
                        ->where('created', '<=', $end_time)
                        ->first();
                    if(!empty($log)) {
                        $word = '====各位注意啦「'.$upstream->name.'」也许太穷了，现在预付金低于1000';
                        $word .= '现有预付金'.($amount/100).'元，注意追债！！！';
                        $token = config('services.telegram-bot-api.token');
                        $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                        $parameters = [
                            'chat_id' => '-601212317',
                            'text' => $word
                        ];
                        Http::post($url, $parameters);
                    }
                }
            }
        }
    }



}
