<?php

namespace App\Http\Controllers;

use App\Models\AdvanceLog;
use App\Models\Merchant;
use App\Models\MerchantAdvance;
use App\Models\MerchantChannel;
use App\Models\Order;
use App\Models\Upstream;
use Illuminate\Http\Request;
use App\Models\UpstreamChannel;
use  Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class ServiceController extends Controller
{
    public function bot(Request $request) {
        $input = file_get_contents('php://input');
        $input = json_decode($input, true);
        $token = config('services.telegram-bot-api.token');
        $chat_id = $input['message']['chat']['id'];
        if(isset($input['message']['caption'])) {
            $headArr = explode("\n", $input['message']['caption']);
            $text = '';
            foreach($headArr as $row) {
                $orderModel = new Order();
                $row = trim($row);
                $rowAry = explode(' ', $row);
                $order = $orderModel->where('mchOrderNo', $rowAry[0])->first();
                if(empty($order)) {
                    break;
                }
                switch($order->status) {
                    case 0:
                        $status = '创建成功';
                        break;
                    case 1:
                        $status = '支付成功';
                        break;
                    case 2:
                        $status = '订单完成';
                        break;
                    case 3:
                        $status = '创建失败';
                        break;
                    default:
                        $status = '';
                        break;
                }


                $text .= '商户单号:'.$order->mchOrderNo."\n".
                    '系统单号:T'.$order->OrderNo."\n".
                    '订单金额:'.sprintf("%.2f",$order->original_amount/100).'元'."\n".
                    '订单状态:'.$status."\n".
                    '所属上游:'.$order->upstream->name."\n";


            }

            if(!empty($text)) {
                $url = 'https://api.telegram.org/bot'.$token.'/copyMessage';
                $parameters = [
                    'chat_id' => $chat_id,
                    'from_chat_id' => $chat_id,
                    'message_id' => $input['message']['message_id'],
                    'caption' => $text
                ];
                Http::post($url, $parameters);
            } else {
                $url = 'https://api.telegram.org/bot'.$token.'/copyMessage';
                $parameters = [
                    'chat_id' => $chat_id,
                    'from_chat_id' => $chat_id,
                    'message_id' => $input['message']['message_id'],
                    'caption' => '查无此单！'
                ];
                Http::post($url, $parameters);
            }



        }

        if(isset($input['message']['text'])) {
            if(strpos($input['message']['text'],'#1') !== false) {
                $row = explode('#1', $input['message']['text']);
                if($row > 1) {
                    $orderModel = new Order();
                    $row = trim($row[1]);
                    $rowAry = explode(' ', $row);
                    $order = $orderModel->where('mchOrderNo', $rowAry[0])->first();
                    if(empty($order)) {
                        $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                        $parameters = [
                            'chat_id' => $chat_id,
                            'text' => '查无此单！'
                        ];
                        Http::post($url, $parameters);
                        die;
                    }
                    switch($order->status) {
                        case 0:
                            $status = '创建成功';
                            break;
                        case 1:
                            $status = '支付成功';
                            break;
                        case 2:
                            $status = '订单完成';
                            break;
                        case 3:
                            $status = '创建失败';
                            break;
                        default:
                            $status = '';
                            break;
                    }


                    $text = '商户单号:'.$order->mchOrderNo."\n".
                        '系统单号:T'.$order->OrderNo."\n".
                        '订单金额:'.sprintf("%.2f",$order->original_amount/100).'元'."\n".
                        '订单状态:'.$status."\n".
                        '所属上游:'.$order->upstream->name."\n";



                    if(!empty($text)) {
                        $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                        $parameters = [
                            'chat_id' => $chat_id,
                            'text' => $text
                        ];
                        Http::post($url, $parameters);
                        die;
                    } else {
                        $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                        $parameters = [
                            'chat_id' => $chat_id,
                            'text' => '查无此单！'
                        ];
                        Http::post($url, $parameters);
                        die;
                    }

                }
            }
            else if(strpos($input['message']['text'],'#上游结算') !== false) {
                return $this->_up();
            }
            else if(strpos($input['message']['text'],'#下游结算') !== false) {
                return $this->_down();
            }
            else if(strpos($input['message']['text'],'#通道列表') !== false) {
                $row = explode('#通道列表', $input['message']['text']);

                $channelModel = new UpstreamChannel();
                if(isset($row[1])) {
                    $code = trim($row[1]);
                    $channelModel = $channelModel->where('code', $code);
                }
                $channels = $channelModel->get();
                $text = '';
                foreach ($channels as $channel) {
                    $text .= $channel->id.'---'.$channel->name.'---'.$channel->code.'---';
                    $text .= $channel->status == 1?'开启':'关闭';
                    $text .= "\n"."\n";
                }
                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                $parameters = [
                    'chat_id' => $chat_id,
                    'text' => $text
                ];
                Http::post($url, $parameters);
                die;
            }
            else if(strpos($input['message']['text'],'#通道关闭') !== false) {
                $row = explode('#通道关闭', $input['message']['text']);
                $row = trim($row[1]);
                $channelModel = new UpstreamChannel();
                $channel = $channelModel->where('id', $row)->first();
                if(empty($channel)){
                    $parameters = [
                        'chat_id' => $chat_id,
                        'text' => '找不到此通道'
                    ];
                    Http::post($url, $parameters);
                    die;
                }
                $channelModel->where('id', $row)->update([
                    'status' => 0
                ]);
                $merchantChannelModel = new MerchantChannel();
                $merchantChannelModel->where('channel_id', $row)->update([
                    'status' => 0
                ]);
                $channel = $channelModel->where('id', $row)->first();
                $text = '';
                $text .= $channel->id.'---'.$channel->name.'---'.$channel->code.'---';
                $text .= $channel->status == 1?'开启':'关闭';
                $text .= "\n"."\n";
                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                $parameters = [
                    'chat_id' => $chat_id,
                    'text' => $text
                ];
                Http::post($url, $parameters);
                die;
            }
            else if(strpos($input['message']['text'],'#通道开启') !== false) {
                $row = explode('#通道开启', $input['message']['text']);
                $row = trim($row[1]);
                $channelModel = new UpstreamChannel();
                $channel = $channelModel->where('id', $row)->first();
                if(empty($channel)){
                    $parameters = [
                        'chat_id' => $chat_id,
                        'text' => '找不到此通道'
                    ];
                    Http::post($url, $parameters);
                    die;
                }
                $channelModel->where('id', $row)->update([
                    'status' => 1
                ]);
                $merchantChannelModel = new MerchantChannel();
                $merchantChannelModel->where('channel_id', $row)->update([
                    'status' => 1
                ]);
                $channel = $channelModel->where('id', $row)->first();
                $text = '';
                $text .= $channel->id.'---'.$channel->name.'---'.$channel->code.'---';
                $text .= $channel->status == 1?'开启':'关闭';
                $text .= "\n"."\n";
                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                $parameters = [
                    'chat_id' => $chat_id,
                    'text' => $text
                ];
                Http::post($url, $parameters);
                die;
            }
            else if(strpos($input['message']['text'],'#关闭所有通道') !== false) {
                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                $this->_pushChannelData();
                $channelModel = new UpstreamChannel();
                $channelModel->where('id', '!=', 0)->update([
                    'status' => 0
                ]);
                $merchantChannelModel = new MerchantChannel();
                $merchantChannelModel->where('id', '!=', 0)->update([
                    'status' => 0
                ]);
                $parameters = [
                    'chat_id' => $chat_id,
                    'text' => '通道已经全部关闭'
                ];
                Http::post($url, $parameters);
                die;
            }
            else if(strpos($input['message']['text'],'#通道恢复') !== false) {
                $upData = Redis::llen('upData');
                $merchantData = Redis::llen('merchantData');
                if(empty($upData) || empty($merchantData)) {
                    $parameters = [
                        'chat_id' => $chat_id,
                        'text' => '没有需要恢复的通道'
                    ];
                    $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                    Http::post($url, $parameters);
                    die;
                }
                $upData = json_decode($upData, true);
                foreach ($upData as $row) {
                    $upstreamChannelModel = new UpstreamChannel();
                    $upstreamChannelModel->where('id', $row['id'])->update([
                        'status' => $row['status']
                    ]);
                }

                $merchantData = json_decode($merchantData, true);
                foreach ($merchantData as $row) {
                    $merchantChannelModel = new MerchantChannel();
                    $merchantChannelModel->where('id', $row['id'])->update([
                        'status' => $row['status']
                    ]);
                }
                $parameters = [
                    'chat_id' => $chat_id,
                    'text' => '通道已经全部恢复'
                ];
                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                Http::post($url, $parameters);
                die;
            }
            else if(strpos($input['message']['text'],'#帮助') !== false) {
                $text = '#1 （单号）---查询订单状态'."\n";
                $text .= '#上游结算---获取上游结算信息'."\n";
                $text .= '#下游结算---获取下游结算信息'."\n";
                $text .= '#上游预付查询---获取上游今日预付信息'."\n";
                $text .= '#下游预付查询---获取下游今日预付信息'."\n";
                $text .= '#上游充值 （上游编号） （充值金额）---上游编号通过预付查询，每个参数前保持一个空格金额可以是负数'."\n";
                $text .= '#下游充值 （下游编号） （充值金额）---下游编号通过预付查询，每个参数前保持一个空格金额可以是负数'."\n";
                $text .= '#通道列表---获取当下所有通道信息'."\n";
                $text .= '#通道关闭 （通道编号）---关闭指定通道，通道号请查询#通道列表'."\n";
                $text .= '#通道开启 （通道编号）---开启指定通道，通道号请查询#通道列表'."\n";
                $text .= '#关闭所有通道---关闭所有通道'."\n";
                $text .= '#通道恢复---恢复上次所有通道关闭之前的状态'."\n";
                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                $parameters = [
                    'chat_id' => $chat_id,
                    'text' => $text
                ];
                Http::post($url, $parameters);
                die;
            }
            else if(strpos($input['message']['text'],'#下游预付查询') !== false) {
                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                $text = $this->merchantAdvance();
                if(empty($text)) {
                    $parameters = [
                        'chat_id' => $chat_id,
                        'text' => '下游暂无跑量或充值记录'
                    ];
                    Http::post($url, $parameters);
                    die;
                }
                $parameters = [
                    'chat_id' => $chat_id,
                    'text' => $text
                ];
                Http::post($url, $parameters);
                die;
            }
            else if(strpos($input['message']['text'],'#上游预付查询') !== false) {
                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                $text = $this->advance();
                if(empty($text)) {
                    $parameters = [
                        'chat_id' => $chat_id,
                        'text' => '上游暂无跑量或充值记录'
                    ];
                    Http::post($url, $parameters);
                    die;
                }
                $parameters = [
                    'chat_id' => $chat_id,
                    'text' => $text
                ];
                Http::post($url, $parameters);
                die;
            }
            else if(strpos($input['message']['text'],'#上游充值') !== false) {
                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                $row = explode('#上游充值', $input['message']['text']);
                $row = explode(' ', $row[1]);
                $text = $this->upUpdate($row);
                if($text == false){
                    $parameters = [
                        'chat_id' => $chat_id,
                        'text' => '充值失败'
                    ];
                    Http::post($url, $parameters);
                    die;
                } else {
                    $parameters = [
                        'chat_id' => $chat_id,
                        'text' => $text
                    ];
                    Http::post($url, $parameters);
                    die;
                }
            }
            else if(strpos($input['message']['text'],'#下游充值') !== false) {
                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                $row = explode('#下游充值', $input['message']['text']);
                $row = explode(' ', $row[1]);
                $text = $this->dowmUpdate($row);
                if($text == false){
                    $parameters = [
                        'chat_id' => $chat_id,
                        'text' => '充值失败'
                    ];
                    Http::post($url, $parameters);
                    die;
                } else {
                    $parameters = [
                        'chat_id' => $chat_id,
                        'text' => $text
                    ];
                    Http::post($url, $parameters);
                    die;
                }
            }
        }





    }

    private function _up() {
        $token = config('services.telegram-bot-api.token');
        $start_date = date('Y-m-d');
        $end_date = $start_date;
        $upstreamModel = new Upstream();
        $upstreams = $upstreamModel->where('status', 1)->get();
        foreach ($upstreams as $upstream) {
            if(Order::upstreamDetail($upstream->id, $start_date, $end_date)->original_amount == 0 &&
                Order::upstreamDetail($upstream->id, $start_date, $end_date)->amount == 0 &&
                empty(Order::log_change($upstream->id, $start_date, $end_date))
            ) {
                continue;
            }
            $text = '';
            $text .= $end_date.'账单明细:'."\n".
                '上游:'.$upstream->name."\n";
            $total_pay_amount = 0;
            foreach(Order::detail($upstream->id, $start_date, $end_date) as $item) {
                $text .= UpstreamChannel::obj($item->channel_id)->name."\n".
                    '跑量:'.sprintf("%.2f",$item->original_amount/100)."\n";
                $rate = UpstreamChannel::obj($item->channel_id)->rate;
                $text .= '费率:'.($rate/10).'%'."\n";
                $pay_amount = ($item->original_amount - $item->original_amount*($rate/1000))/100;
                $total_pay_amount = $total_pay_amount + $pay_amount;
                $text .= '应结算:'.sprintf("%.2f",$pay_amount)."\n";
            }
            $amount = Order::log_change($upstream->id, $start_date, $end_date)/100;
            $amount = empty($amount)?'0.00':$amount;
            $text .= '跑量合计:'.sprintf("%.2f",Order::upstreamDetail($upstream->id, $start_date, $end_date)->original_amount/100)."\n".
            '应结算合计:'.sprintf("%.2f",$total_pay_amount)."\n".
            '已预付:'.sprintf("%.2f",$amount)."\n".
            '剩余应结算:'.sprintf("%.2f",$amount).' - '.
            sprintf("%.2f",$total_pay_amount).' = '.sprintf("%.2f",$amount - $total_pay_amount)."\n"."\n";
            $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
            $parameters = [
                'chat_id' => $chat_id,
                'text' => $text
            ];
            Http::post($url, $parameters);
            die;
        }



    }

    private function _down() {
        $token = config('services.telegram-bot-api.token');
        $start_date = date('Y-m-d');
        $end_date = $start_date;
        $start_time = strtotime($start_date.' 00:00:00');
        $end_time = strtotime($end_date.' 23:59:59');

        $orderModel = new Order();
        $orders = $orderModel->select('customer_id')->where('created', '>=', $start_time)
            ->where('created', '<=', $end_time)
            ->groupBy('customer_id')
            ->get();
        $ids = [];
        foreach($orders as $order) {
            $ids[] = $order->customer->id;
        }

        $merchantChannelModel = new MerchantChannel();
        $merchantChannels =  $merchantChannelModel->select('merchant_id')->whereIn('merchant_id', $ids)
            ->groupBy('merchant_id')->get();


        foreach($merchantChannels as $merchantChannel) {
            if(!empty(Order::merchantOriginalAmount($merchantChannel->merchant->account, $start_date, $end_date))) {
                $originalAmount = sprintf("%.2f", Order::merchantOriginalAmount($merchantChannel->merchant->account, $start_date, $end_date)->amount/100);
            } else {
                $originalAmount = '0.00';
            }
            $pay_amount_total = 0;
            $text = '商户:'.$merchantChannel->merchant->name."\n"."\n";
            foreach(Order::merchantDetail($merchantChannel->merchant->account, $start_date, $end_date) as $item) {
                $text .= $item->merchantChannel->channel->name."\n";
                $text .= '跑量:'.sprintf("%.2f", $item->original_amount/100)."\n";
                $text .= '费率:'.($item->merchantChannel->rate/10).'%'."\n";
                $pay_amount = ($item->original_amount - ($item->original_amount*($item->merchantChannel->rate/1000)))/100;
                $pay_amount_total = $pay_amount_total + $pay_amount;
                $text .= '应结算:'.sprintf("%.2f", $pay_amount)."\n";
            }

            $text .= '跑量合计:'.$originalAmount."\n";
            $text .= '应结算合计:'.$pay_amount_total."\n";
            $text .= '已预付:'.sprintf("%.2f", $merchantChannel->merchant->advance($merchantChannel->merchant->id, $start_date, $end_date)/100)."\n";
            $text .= '剩余应结算:'.sprintf("%.2f", $merchantChannel->merchant->advance($merchantChannel->merchant->id, $start_date, $end_date)/100).
                ' - '. $pay_amount_total. ' = '.
                sprintf("%.2f", $merchantChannel->merchant->advance($merchantChannel->merchant->id, $start_date, $end_date)/100 - $pay_amount_total);
            $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
            $parameters = [
                'chat_id' => $chat_id,
                'text' => $text
            ];
            Http::post($url, $parameters);
            die;
        }














    }

    private function _pushChannelData() {
        $upstreamChannelModel = new UpstreamChannel();
        $upstreamChannels = $upstreamChannelModel->get();
        $upData = [];
        foreach ($upstreamChannels as $key => $upstreamChannel) {
            $upData[$key]['id'] = $upstreamChannel->id;
            $upData[$key]['status'] = $upstreamChannel->status;
        }
        $upData = json_encode($upData);
        Redis::del('upData');
        Redis::rpush('upData', $upData);
        $merchantChannelModel = new MerchantChannel();
        $merchantChannels = $merchantChannelModel->get();
        $merchantData = [];
        foreach ($merchantChannels as $key => $channel) {
            $merchantData[$key]['id'] = $channel->id;
            $merchantData[$key]['status'] = $channel->status;
        }
        $merchantData = json_encode($merchantData);
        Redis::del('merchantData');
        Redis::rpush('merchantData', $merchantData);
    }

    private function merchantAdvance() {
        $start_date = date('Y-m-d');
        $end_date = $start_date;
        $start_time = strtotime($start_date.' 00:00:00');
        $end_time = strtotime($end_date.' 23:59:59');
        $orderModel = new Order();
        $orders = $orderModel->select('customer_id')->where('created', '>=', $start_time)
            ->where('created', '<=', $end_time)
            ->groupBy('customer_id')
            ->get();
        $ids = [];
        foreach($orders as $order) {
            $ids[] = $order->customer->id;
        }

        $merchantChannelModel = new MerchantChannel();
        $merchantChannels =  $merchantChannelModel->select('merchant_id')->whereIn('merchant_id', $ids)
            ->groupBy('merchant_id')->get();
        $text = '';
        foreach ($merchantChannels as $merchantChannel) {

            $text .= '商户：'.$merchantChannel->merchant->name."\n";
            $text .= '商户编号：'.$merchantChannel->merchant->account."\n";
            if(!empty(Order::merchantAmount($merchantChannel->merchant->account, $start_date, $end_date))){
                $merchantAmount = sprintf("%.2f", Order::merchantAmount($merchantChannel->merchant->account, $start_date, $end_date)->merchant_amount/100);
            }else {
                $merchantAmount = 0;
            }

            $balance = sprintf("%.2f", ($merchantChannel->merchant->advance($merchantChannel->merchant->id, $start_date, $end_date)/100) - $merchantAmount);
            $text .= '预付金额:'.sprintf("%.2f", $merchantChannel->merchant->advance($merchantChannel->merchant->id, $start_date, $end_date)/100)."\n";
            $text .= '预付剩余:'.$balance."\n";
            $merchantAdvanceModel = new MerchantAdvance();
            $merchantAdvances = $merchantAdvanceModel->where('merchant_id', $merchantChannel->merchant->id)
                ->where('created','>=', $start_time)
                ->where('created','<=', $end_time)->get();
            foreach($merchantAdvances as $k => $merchantAdvance) {
                $symbol = '+';
                if($merchantAdvance->type == 1) {
                    $symbol = '+';
                } elseif($merchantAdvance->type == 2) {
                    $symbol = '-';
                }
                $amount = sprintf("%.2f", $merchantAdvance->amount/100);
                    $text .= ($k+1).'.'.$symbol.$amount."\n";
            }
            $text .= "\n";


        }

        return $text;
    }

    private function advance() {
        $start_date = date('Y-m-d');

        $end_date = $start_date;
        $start_time = strtotime($start_date.' 00:00:00');
        $end_time = strtotime($end_date.' 23:59:59');
        $upstreamModel = new Upstream();
        $upstreams = $upstreamModel->where('status', 1)->get();
        $text = '';
        foreach($upstreams as $upstream) {
            if(Order::upstreamDetail($upstream->id, $start_date, $end_date)->original_amount == 0 &&
                Order::upstreamDetail($upstream->id, $start_date, $end_date)->amount == 0 &&
                empty(Order::log_change($upstream->id, $start_date, $end_date))
            ) {
                continue;
            }
            $text .= '上游：'.$upstream->name."\n";
            $text .= '上游：'.$upstream->id."\n";
            $amount = Order::log_change($upstream->id, $start_date, $end_date)/100;
            $amount = empty($amount)?'0.00':$amount;
            $text .= '预付金额:'.sprintf("%.2f", $amount)."\n";

            $balance = sprintf("%.2f",$amount - (Order::upstreamDetail($upstream->id, $start_date, $end_date)->amount/100));
            $text .= '预付剩余:'.$balance."\n";
            $advanceMode = new AdvanceLog();
            $advances = $advanceMode->where('upstream_id', $upstream->id)
                ->where('created','>=', $start_time)
                ->where('created','<=', $end_time)->get();
            foreach($advances as $k => $advance) {
                $symbol = '+';
                if($advance->type == 1) {
                    $symbol = '+';
                } elseif($advance->type == 2) {
                    $symbol = '-';
                }
                $amount = sprintf("%.2f", $advance->amount/100);
                $text .= ($k+1).'.'.$symbol.$amount."\n";
            }
            $text .= "\n";

        }

        return $text;




    }

    private function upUpdate($row) {
        $start_date = date('Y-m-d');

        $end_date = $start_date;
        $id = trim($row[1]);

        $upstreamModel = new Upstream();
        $upstream = $upstreamModel->where('id', $id)->first();
        if(empty($upstream)) {
            return false;
        }
        $balance = ($upstream->balance/100 + (int)$row[2])*100;
        $upstreamModel->where('id', $id)->update([
            'balance' => $balance
        ]);

        $logModel = new AdvanceLog();
        $logModel->upstream_id = $id;
        $logModel->user_id = 1;
        $logModel->amount = abs($row[2]) * 100;
        $type = 1;
        if(strpos($row[2],'+') !== false) {
            $type = 1;
        } elseif(strpos($row[2],'-') !== false) {
            $type = 2;
        }
        $logModel->type = $type;
        $logModel->balance = 0;
        $logModel->created = time();
        $logModel->save();
        $amount = Order::log_change($upstream->id, $start_date, $end_date)/100;
        $amount = empty($amount)?'0.00':$amount;
        $balance = sprintf("%.2f",$amount - (Order::upstreamDetail($upstream->id, $start_date, $end_date)->amount/100));
        $text = '['.$upstream->name.']预付剩余:'.$balance;

        return $text;
    }

    private function dowmUpdate($row) {
        $start_date = date('Y-m-d');

        $end_date = $start_date;
        $id = trim($row[1]);



        $merchantModel = new Merchant();
        $merchant = $merchantModel->where('account', $id)->first();
        if(empty($merchant)) {
            return false;
        }
        $advanceModel = new MerchantAdvance();
        $advanceModel->merchant_id = $merchant->id;
        $advanceModel->amount = abs($row[2])*100;
        $advanceModel->user_id = 1;
        $type = 1;
        if(strpos($row[2],'+') !== false) {
            $type = 1;
        } elseif(strpos($row[2],'-') !== false) {
            $type = 2;
        }
        $advanceModel->type = $type;
        $advanceModel->recharge_time = time();
        $balance = $advanceModel->balance = ($merchant->balance/100 + $row[2])*100;

        $advanceModel->created = time();
        $advanceModel->save();
        $merchantModel->where('id', $merchant->id)->update([
            'balance' => $balance
        ]);

        if(!empty(Order::merchantAmount($merchant->account, $start_date, $end_date))){
            $merchantAmount = sprintf("%.2f", Order::merchantAmount($merchant->account, $start_date, $end_date)->merchant_amount/100);
        }else {
            $merchantAmount = 0;
        }

        $balance = sprintf("%.2f", ($merchant->advance($merchant->id, $start_date, $end_date)/100) - $merchantAmount);
        $text = '['.$merchant->name.']预付剩余:'.$balance;

        return $text;
    }
}
