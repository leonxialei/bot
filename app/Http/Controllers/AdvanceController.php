<?php

namespace App\Http\Controllers;

use App\Models\AdvanceLog;
use App\Models\Merchant;
use App\Models\MerchantAdvance;
use App\Models\MerchantChannel;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\TelegramMerchant;
use App\Models\Upstream;
use Illuminate\Http\Request;
use App\Models\UpstreamChannel;
use Illuminate\Support\Facades\DB;
use  Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use App\Models\TelegramBookkeeping;
use Illuminate\Support\Facades\Crypt;

class AdvanceController extends Controller
{
    public $auth_ids = [
        '5526661955',
        '1673223753',
        '5275008453',
        '5177670065'
    ];


    public function bot(Request $request) {
        error_log(print_r($request->all(),1),3,'cfiwehfwehfoiwefoiweoiw');
        $input = file_get_contents('php://input');
        $input = json_decode($input, true);
        $token = config('services.telegram-bot-api.newtoken');
        $chat_id = $input['message']['chat']['id'];
        $user_id = $input['message']['from']['id'];




//        if(!in_array($user_id, $this->auth_ids)) {
//            $parameters = [
//                'chat_id' => $chat_id,
//                'text' =>  '您无此权限'
//            ];
//            $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
//            Http::post($url, $parameters);
//            die;
//        }

        if($chat_id == '-742212393') {
            $url = 'https://api.telegram.org/bot'.$token.'/forwardMessage';
            $merchantModel = new TelegramMerchant();
            $merchants = $merchantModel->where('type',2)->get();
            $message_id = $input['message']['message_id'];
            foreach ($merchants as $merchant) {
                $parameters = [
                    'chat_id' => $merchant->chat_id,
                    'from_chat_id' => $chat_id,
                    'message_id' => $message_id
                ];
                Http::post($url, $parameters);
            }
            die;

        }


        if($chat_id == '-722867709') {
//            $parameters = [
//                'chat_id' => $chat_id,
//                'text' =>  $input
//            ];
//            $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
//            Http::post($url, $parameters);
//            die;

//            $parameters = [
//                'chat_id' => $chat_id,
//                'voice' =>  'AwACAgUAAxkBAAEBUu9jV9cT2DynUUTQfE86xW7Kqrx3MQAC4gYAAo7OuFZwYoq0OpSg-yoE',
//                'caption' => '【發財--通道通知】'."\n".'通道已打开，请加大并发，'."\n".'请加大并发，请加大并发，请加大并发！！！！',
//                'protect_content' => true
//            ];
//
//            $url = 'https://api.telegram.org/bot'.$token.'/sendVoice';
//            Http::post($url, $parameters);
//            die;

        }
//        if($chat_id == '-1001615268552') {
//            error_log(print_r($input,1),3,'xiyoujixiyouji');
//        }


        if($chat_id == '-1001615268552') {
            if(isset($input['message']['caption'])) {
                $headArr = explode("\n", $input['message']['caption']);
                $text = '';
                $old_chat_id = $input['message']['chat']['id'];

                $message_id = $input['message']['message_id'];
                foreach($headArr as $row) {
                    $orderModel = new Order();
                    $row = trim($row);
                    $rowAry = explode('系统单号:', $row);
//                    $text = print_r($rowAry,1);
                    $order = $orderModel->where('mchOrderNo', trim($rowAry[1]))->first();
                    if (empty($order)) {
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
                    $tMerchantModel = new TelegramMerchant();
                    $tMerchant = $tMerchantModel->where('customer_id', $order->upstream_id)
                        ->where('type', 1)
                        ->first();
                    $text .= '[商户单号]:'.$order->mchOrderNo."\n".
                        '系统单号:YZ'.$order->OrderNo."\n".
                        '订单金额:'.sprintf("%.2f",$order->original_amount/100).'元'."\n".
                        '订单状态:'.$status."\n".
//                        '上游编号:'.$order->upstream_id."\n".



                        $chat_id = $tMerchant->chat_id;
                    $url = 'https://api.telegram.org/bot'.$token.'/copyMessage';
                    $parameters = [
                        'chat_id' => $chat_id,
//                'chat_id' => '-727649980',
                        'from_chat_id' => $old_chat_id,
                        'message_id' => $input['message']['message_id'],
                        'caption' => $text
                    ];
                    Http::post($url, $parameters);
                    Redis::set('query_order_'.$order->OrderNo, $message_id);
                    $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                    $parameters = [
                        'chat_id' => $old_chat_id,
                        'reply_to_message_id' => $message_id,
                        'text' => '转发了'
                    ];
                    Http::post($url, $parameters);
                    die;
//
//                    $parameters = [
//                        'chat_id' => $chat_id,
//                        'text' =>  $text
//                    ];
//                    $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
//                    Http::post($url, $parameters);
//                    die;
                }


            }
        }

        if(isset($input['message']['reply_to_message']['caption'])) {

            $headArr = explode("\n", $input['message']['reply_to_message']['caption']);
            if(strpos($headArr[0],'[商户单号]:') !== false) {
                $morder_id = trim(explode('[商户单号]:', $headArr[0])[1]);
                $orderModel = new Order();
                $order = $orderModel->where('mchOrderNo', $morder_id)->first();

//                    error_log(print_r($order->toArray(),1),3,'555555');
                if(!empty($order)) {
                    $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                    $parameters = [
                        'chat_id' => $chat_id,
                        'text' => '已传达谢谢！'
                    ];
                    Http::post($url, $parameters);


                    $tMerchantModel = new TelegramMerchant();
                    $tMerchant = $tMerchantModel->where('customer_id', $order->customer->id)
                        ->where('type', 2)
                        ->first();

                    $message_id = Redis::get('query_order_'.$order->OrderNo);
                    $text = '订单号：'.$morder_id."\n".$input['message']['text'];


                    $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                    $parameters = [
                        'chat_id' => $tMerchant->chat_id,
                        'reply_to_message_id' => $message_id,
                        'text' => $text
                    ];
                    Http::post($url, $parameters);
                    Redis::del('query_order_'.$order->OrderNo);
//                    $url = 'https://api.telegram.org/bot'.$token.'/sendPhoto';
//                    $parameters = [
////                        'chat_id' => $chat_id,
//                            'chat_id' => $tMerchant->chat_id,
//
//                        'photo'=> $input['message']['reply_to_message']['photo'][0]['file_id'],
//                        'caption' =>
//                    ];
//                    Http::post($url, $parameters);
                    die;

                }




            }
        }
        if(isset($input['message']['caption'])) {
            $headArr = explode("\n", $input['message']['caption']);
            $text = '';
            $old_chat_id = $input['message']['chat']['id'];

            $message_id = $input['message']['message_id'];
            foreach($headArr as $row) {
                $orderModel = new Order();
                $row = trim($row);
                $rowAry = explode(' ', $row);
                $order = $orderModel->where('mchOrderNo', $rowAry[0])->first();
                if (empty($order)) {
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
                $tMerchantModel = new TelegramMerchant();
                $tMerchant = $tMerchantModel->where('customer_id', $order->upstream_id)
                    ->where('type', 1)
                    ->first();
                $text .= '[商户单号]:'.$order->mchOrderNo."\n".
                    '系统单号:YZ'.$order->OrderNo."\n".
                    '订单金额:'.sprintf("%.2f",$order->original_amount/100).'元'."\n".
                    '订单状态:'.$status."\n".
//                        '上游编号:'.$order->upstream_id."\n".



                $chat_id = $tMerchant->chat_id;
                $url = 'https://api.telegram.org/bot'.$token.'/copyMessage';
                $parameters = [
                    'chat_id' => $chat_id,
//                'chat_id' => '-727649980',
                    'from_chat_id' => $old_chat_id,
                    'message_id' => $input['message']['message_id'],
                    'caption' => $text
                ];
                Http::post($url, $parameters);
                Redis::set('query_order_'.$order->OrderNo, $message_id);
                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                $parameters = [
                    'chat_id' => $old_chat_id,
                    'reply_to_message_id' => $message_id,
                    'text' => '查询中，稍后'
                ];
                Http::post($url, $parameters);
                die;
            }


        }
        if(isset($input['message']['text'])) {
            if(strpos($input['message']['text'],'#下发') !== false) {
                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                if($user_id != '5526661955' && $user_id != '5212721521' && $user_id != '1673223753'
                    && $user_id != '5275008453' && $user_id != '5083689282' && $user_id != '5177670065'
                ) {
                    $parameters = [
                        'chat_id' => $chat_id,
                        'text' => '您没有此权限！'
                    ];
                    Http::post($url, $parameters);
                    die;
                }
                $row = explode('#下发', $input['message']['text']);
                $row = trim($row[1]);
                $row = explode(' ', $row);
                $chat_id = $input['message']['chat']['id'];
                $name = $input['message']['from']['first_name'];
                $tMerchantModel = new TelegramMerchant();
                $tMerchant = $tMerchantModel->where('chat_id', $chat_id)
                    ->first();
                if(empty($tMerchant)){
                    return ;
                }
                $bookkeepingModel = new TelegramBookkeeping();

                $bookkeepingModel->chat_id = $tMerchant->chat_id;
                $bookkeepingModel->customer_id = $tMerchant->customer_id;
                $bookkeepingModel->type = $tMerchant->type;
                $bookkeepingModel->genre = 1;
                $bookkeepingModel->amount = $row[0] * 100;
                $bookkeepingModel->name = $name;
                if(!empty($row[1])) {
                    $bookkeepingModel->note = $row[1];
                } else if(isset($row[2])) {
                    $bookkeepingModel->note = $row[2];
                }
                $bookkeepingModel->created = time();
                $bookkeepingModel->save();

                $text_c = '';
                if($tMerchant->type == 1) {
                    $text_c = $this->upUpdate($row[0], $tMerchant->customer_id);
                } elseif($tMerchant->type == 2) {
                    $text_c = $this->dowmUpdate($row[0], $tMerchant->customer_id);
                }

                $text = $this->bookkeeping($tMerchant->chat_id);
                $text = $text."\n"."\n".$text_c;

                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                $parameters = [
                    'chat_id' => $chat_id,
                    'text' => $text
                ];
                $res = Http::post($url, $parameters);


                $res = $res->json();

                $parameters = [
                    'chat_id' => $chat_id,
                    'message_id' =>  $res['result']['message_id']
                ];
                $url = 'https://api.telegram.org/bot'.$token.'/pinChatMessage';
                Http::post($url, $parameters);
                die;
            }
            else if(strpos($input['message']['text'],'#查询') !== false) {
                $start_date = date('Y-m-d');

                $end_date = $start_date;

                $tMerchantModel = new TelegramMerchant();
                $tMerchant = $tMerchantModel->where('chat_id', $chat_id)
                    ->first();
                if(empty($tMerchant)){
                    return ;
                }

                $text_c = '';
                if($tMerchant->type == 1) {

                    $upstreamModel = new Upstream();
                    $upstream = $upstreamModel->where('id', $tMerchant->customer_id)->first();
                    $amount = Order::log_change($upstream->id, $start_date, $end_date)/100;
                    $amount = empty($amount)?'0.00':$amount;
                    $balance = sprintf("%.2f",$amount - (Order::upstreamDetail($upstream->id, $start_date, $end_date)->amount/100));
                    $text_c = '['.$upstream->name.']预付剩余:'.$balance;


                } elseif($tMerchant->type == 2) {
                    $merchantModel = new Merchant();
                    $merchant = $merchantModel->where('id', $tMerchant->customer_id)->first();
                    if(!empty(Order::merchantAmount($merchant->account, $start_date, $end_date))){
                        $merchantAmount = Order::merchantAmount($merchant->account, $start_date, $end_date)->merchant_amount/100;
                    }else {
                        $merchantAmount = 0;
                    }

                    $balance = ($merchant->advance($merchant->id, $start_date, $end_date)/100) - $merchantAmount;

                    $balance = sprintf("%.2f", $balance);
                    $text_c = '['.$merchant->name.']预付剩余:'.$balance;



                }

                $text = $this->bookkeeping($tMerchant->chat_id);
                $text = $text."\n"."\n".$text_c;

                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                $parameters = [
                    'chat_id' => $chat_id,
                    'text' => $text
                ];
                Http::post($url, $parameters);

                die;
            }
            else if(strpos($input['message']['text'],'#记账') !== false) {
                $row = explode('#记账', $input['message']['text']);
                $row = trim($row[1]);
                $row = explode(' ', $row);
                $chat_id = $input['message']['chat']['id'];
                $name = $input['message']['from']['first_name'];
                $tMerchantModel = new TelegramMerchant();
                $tMerchant = $tMerchantModel->where('chat_id', $chat_id)
                    ->first();
                if(empty($tMerchant)){
                    return ;
                }
                $bookkeepingModel = new TelegramBookkeeping();

                $bookkeepingModel->chat_id = $tMerchant->chat_id;
                $bookkeepingModel->customer_id = $tMerchant->customer_id;
                $bookkeepingModel->type = $tMerchant->type;
                $bookkeepingModel->genre = 2;
                $bookkeepingModel->amount = $row[0] * 100;
                $bookkeepingModel->name = $name;
                if(isset($row[1])) {
                    $bookkeepingModel->note = $row[1];
                }
                $bookkeepingModel->created = time();
                $bookkeepingModel->save();


                $text = $this->bookkeeping($tMerchant->chat_id);


                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                $parameters = [
                    'chat_id' => $chat_id,
                    'text' => $text
                ];
                Http::post($url, $parameters);
                die;
            }
            else if(strpos($input['message']['text'],'#入账') !== false) {
                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                $row = explode('#入账', $input['message']['text']);
                $row = trim($row[1]);
                $chat_id = $input['message']['chat']['id'];
                $user_id = $input['message']['from']['id'];
//                if(!in_array($user_id, $this->auth_ids)) {
//                    $parameters = [
//                        'chat_id' => $chat_id,
//                        'text' => '您没有此权限！'
//                    ];
//                    Http::post($url, $parameters);
//                    die;
//                }
                $tMerchantModel = new TelegramMerchant();
                $tMerchant = $tMerchantModel->where('chat_id', $chat_id)
                    ->first();
                $text = '';
                if($tMerchant->type == 1) {
                    $text = $this->upUpdate($row, $tMerchant->customer_id);
                } elseif($tMerchant->type == 2) {
                    $text = $this->dowmUpdate($row, $tMerchant->customer_id);
                }


                if(empty($text)){
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
            else if(strpos($input['message']['text'],'#结算') !== false) {
                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                $chat_id = $input['message']['chat']['id'];
                $tMerchantModel = new TelegramMerchant();
                $tMerchant = $tMerchantModel->where('chat_id', $chat_id)
                    ->first();
                if($tMerchant->type == 1) {
                    $text = $this->_up($tMerchant->customer_id);
                } elseif($tMerchant->type == 2) {
                    $text = $this->_down($tMerchant->customer_id);
                }


                if(empty($text)){
                    $parameters = [
                        'chat_id' => $chat_id,
                        'text' => '没有结算信息'
                    ];
                    Http::post($url, $parameters);


                    die;

                } else {
                    $parameters = [
                        'chat_id' => $chat_id,
                        'text' => $text
                    ];
                    $res = Http::post($url, $parameters);
                    $res = $res->json();

                    $parameters = [
                        'chat_id' => $chat_id,
                        'message_id' =>  $res['result']['message_id']
                    ];
                    $url = 'https://api.telegram.org/bot'.$token.'/pinChatMessage';
                    Http::post($url, $parameters);
                    die;
                }
            }
            else if(strpos($input['message']['text'],'#帮助') !== false) {
                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                $chat_id = $input['message']['chat']['id'];
                $text = '#结算 （金额）---获取当天结算信息'."\n";
                $text .= '#下发 （金额）---添加下发金额可以负数金额前必须空格'."\n";
                $text .= '#查询 ---查询上下游下发情况'."\n";
                $text .= '#计算器 （数学公式）---可以进行简单加减乘除'."\n";
                $text .= '#支付测试 （宇宙的编码）---支付测试地址（宇宙的支付编码）'."\n";
                $parameters = [
                    'chat_id' => $chat_id,
                    'text' => $text
                ];
                Http::post($url, $parameters);
                die;
            }
            else if(strpos($input['message']['text'],'#群信息') !== false) {
                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                $chat_id = $input['message']['chat']['id'];

                $parameters = [
                    'chat_id' => $chat_id,
                    'text' => '群ID：'.$chat_id
                ];
                Http::post($url, $parameters);
                die;
            }
            else if(strpos($input['message']['text'],'#初始化') !== false) {
                $chat_id = $input['message']['chat']['id'];
//                $user_id = $input['message']['from']['id'];
                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                if($user_id != '5526661955' && $user_id != '5212721521' && $user_id != '1673223753'
                    && $user_id != '5275008453' && $user_id != '5083689282' && $user_id != '5177670065'
                ) {
                    $parameters = [
                        'chat_id' => $chat_id,
                        'text' => '您没有此权限！'
                    ];
                    Http::post($url, $parameters);
                    die;
                }

                $row = explode('#初始化', $input['message']['text']);
                $row = explode(' ', trim($row[1]));

                $tMerchantModel = new TelegramMerchant();
                $tMerchant = $tMerchantModel->where('chat_id', $chat_id)
                    ->first();

                if(empty($tMerchant)) {
                    $tMerchantModel->chat_id = $chat_id;
                    $tMerchantModel->customer_id = trim($row[0]);
                    $tMerchantModel->type = trim($row[1]);
                    $tMerchantModel->created = time();
                    $tMerchantModel->save();
                    $parameters = [
                        'chat_id' => $chat_id,
                        'text' => '初始化成功！'
                    ];
                } else {
                    $tMerchantModel->where('chat_id', $chat_id)
                        ->update([
                            'customer_id' => trim($row[0]),
                            'type' => trim($row[1])
                    ]);
                    $parameters = [
                        'chat_id' => $chat_id,
                        'text' => '初始化更改成功！'
                    ];
                }


                Http::post($url, $parameters);
                die;
            }
            else if(strpos($input['message']['text'],'#计算器') !== false) {
                $chat_id = $input['message']['chat']['id'];
                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
                $row = explode('#计算器', $input['message']['text']);
                $row = trim($row[1]);
                $number = 0;
                if(strpos($row,'+') !== false) {
                    $row = explode('+', $row);
                    $number = trim($row[0]) + trim($row[1]);
                } elseif(strpos($row,'-') !== false) {
                    $row = explode('-', $row);
                    $number = trim($row[0]) - trim($row[1]);
                } elseif(strpos($row,'*') !== false) {
                    $row = explode('*', $row);
                    $number = trim($row[0]) * trim($row[1]);
                } elseif(strpos($row,'/') !== false) {
                    $row = explode('/', $row);
                    $number = trim($row[0]) / trim($row[1]);
                    $number = sprintf("%.2f", $number);
                }
                $parameters = [
                    'chat_id' => $chat_id,
                    'text' => $number
                ];
                Http::post($url, $parameters);
                die;
            }
            else if(strpos($input['message']['text'],'#支付测试') !== false) {
                $chat_id = $input['message']['chat']['id'];
                $row = explode('#支付测试', $input['message']['text']);
                $row = trim($row[1]);

                $tMerchantModel = new TelegramMerchant();
                $tMerchant = $tMerchantModel->where('chat_id', $chat_id)->first();
                if(empty($tMerchant) || $tMerchant->type ==2) {
                    return false;
                }
                $json = json_encode([
                    $row,
                    $tMerchant->customer_id
                ]);
                $base = Crypt::encryptString($json);
                $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';

                $parameters = [
                    'chat_id' => $chat_id,
                    'text' => '请用系统浏览器打开：http://78.142.245.26:2088/pay/test/create/'.$base
                ];
                Http::post($url, $parameters);
                die;
            }
        }





    }


    private function upUpdate($row, $id) {
        $start_date = date('Y-m-d');

        $end_date = $start_date;

        $upstreamModel = new Upstream();
        $upstream = $upstreamModel->where('id', $id)->first();
        if(empty($upstream)) {
            return false;
        }
        $balance = ($upstream->balance/100 + (int)$row)*100;
        $upstreamModel->where('id', $id)->update([
            'balance' => $balance
        ]);

        $logModel = new AdvanceLog();
        $logModel->upstream_id = $id;
        $logModel->user_id = 1;
        $logModel->amount = abs($row) * 100;
        $type = 1;
        if(strpos($row,'+') !== false) {
            $type = 1;
        } elseif(strpos($row,'-') !== false) {
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

    private function dowmUpdate($row, $id) {
        $start_date = date('Y-m-d');

        $end_date = $start_date;



        $merchantModel = new Merchant();
        $merchant = $merchantModel->where('id', $id)->first();
        if(empty($merchant)) {
            return false;
        }
        $advanceModel = new MerchantAdvance();
        $advanceModel->merchant_id = $merchant->id;
        $advanceModel->amount = abs($row)*100;
        $advanceModel->user_id = 1;
        $type = 1;
        if(strpos($row,'+') !== false) {
            $type = 1;
        } elseif(strpos($row,'-') !== false) {
            $type = 2;
        }
        $advanceModel->type = $type;
        $advanceModel->recharge_time = time();
        $advanceModel->balance = ($merchant->balance/100 + $row)*100;

        $advanceModel->created = time();
        $advanceModel->save();



        if(!empty(Order::merchantAmount($merchant->account, $start_date, $end_date))){
            $merchantAmount = Order::merchantAmount($merchant->account, $start_date, $end_date)->merchant_amount/100;
        }else {
            $merchantAmount = 0;
        }

        $balance = ($merchant->advance($merchant->id, $start_date, $end_date)/100) - $merchantAmount;




        $orderLogModel = new OrderLog();
        $orderLogModel->merchant_id = $merchant->id;
        $orderLogModel->attribute = 2;
        $orderLogModel->type = $type;
        $orderLogModel->amount = abs($row)*100;
        $orderLogModel->before_balance = ($balance - $row) * 100;
        $orderLogModel->balance = $balance * 100;
        $orderLogModel->note = '宇宙机器人入账';
        $orderLogModel->created = time();
        $orderLogModel->save();
        $merchantModel->where('id', $merchant->id)->update([
            'balance' => $balance
        ]);


        $balance = sprintf("%.2f", $balance);
        $text = '['.$merchant->name.']预付剩余:'.$balance;

        return $text;
    }

    private function bookkeeping($chat_id) {
        $start_date = date('Y-m-d');
        $end_date = $start_date;
        $start_time = strtotime($start_date.' 00:00:00');
        $end_time = strtotime($end_date.' 23:59:59');
        $bookkeepingModel = new TelegramBookkeeping();
        $bookkeepings = $bookkeepingModel->where('chat_id', $chat_id)
            ->where('genre', 1)
            ->where('created', '>=', $start_time)
            ->where('created', '<=', $end_time)
            ->get();
        $total = 0;
        $tmp = '';
        foreach ($bookkeepings as $bookkeeping) {
            $tmp .= '['.date('H:i',$bookkeeping->created ).'] '.sprintf("%.2f", $bookkeeping->amount/100)
                .'  '.$bookkeeping->name;
            if(!empty($bookkeeping->note)) {
                $tmp .= '    '.$bookkeeping->note."\n";
            } else {
                $tmp .= "\n";
            }
            $total = $total + sprintf("%.2f", $bookkeeping->amount/100);
        }
        $text = '下发共计：'.sprintf("%.2f", $total).'        '."\n";
        $text .= $tmp."\n"."\n";



        $bookkeepings = $bookkeepingModel->where('chat_id', $chat_id)
            ->where('genre', 2)
            ->where('created', '>=', $start_time)
            ->where('created', '<=', $end_time)
            ->get();
        $tmp = '';
        $total = 0;
        foreach ($bookkeepings as $bookkeeping) {
            $tmp .= '['.date('H:i',$bookkeeping->created ).'] '.sprintf("%.2f", $bookkeeping->amount/100)
                .'  '.$bookkeeping->name;
            if(!empty($bookkeeping->note)) {
                $tmp .= '    '.$bookkeeping->note."\n";
            } else {
                $tmp .= "\n";
            }
            $total = $total + sprintf("%.2f", $bookkeeping->amount/100);
        }
        $text .= '记账共计：'.sprintf("%.2f", $total).'        '."\n";
        $text .= $tmp."\n";
        return $text;
    }

    private function _up($id) {
        $start_date = date('Y-m-d');
        $end_date = $start_date;
        $upstreamModel = new Upstream();
        $upstream = $upstreamModel->where('id', $id)->first();

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

        return $text;

    }

    private function _down($id) {
        $start_date = date('Y-m-d');
        $end_date = $start_date;
        $merchantChannelModel = new MerchantChannel();
        $merchantChannel =  $merchantChannelModel->where('merchant_id', $id)
            ->first();

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
        return $text;

    }

}
