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
use  Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use App\Models\TelegramBookkeeping;

class HomeController extends Controller
{
    public function index() {
//        phpinfo();
        echo '你TMD管我显示什么';
        die;
    }

    public function test() {

    }
}
