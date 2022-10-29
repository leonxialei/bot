<?php

namespace App\Http\Controllers;

use App\Models\AdvanceLog;
use App\Models\Merchant;
use App\Models\MerchantAdvance;
use App\Models\MerchantChannel;
use App\Models\Order;
use App\Models\TelegramMerchant;
use App\Models\Upstream;
use Illuminate\Http\Request;
use App\Models\UpstreamChannel;
use  Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class AutoController extends Controller
{
    public function index() {

        $input = file_get_contents('php://input');
        $input = json_decode($input, true);
        $token = config('services.telegram-bot-api.token');
        $chat_id = $input['message']['chat']['id'];
        $user_id = $input['message']['from']['id'];
        $message_id = $input['message']['message_id'];
        if($chat_id == '-742212393') {
            $url = 'https://api.telegram.org/bot'.$token.'/forwardMessage';
            $merchantModel = new TelegramMerchant();
            $merchants = $merchantModel->where('type',2)->get();
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




    }
}
