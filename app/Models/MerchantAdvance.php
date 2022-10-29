<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


class MerchantAdvance extends Model
{
    protected $table = 'merchant_advance';
    public $timestamps = false;


    public static function plus($merchant_id, $start_time, $end_time) {
        $model = new MerchantAdvance();
        $data = $model
            ->select(DB::raw('SUM(amount) as amount'))
            ->where('merchant_id', $merchant_id)->where('recharge_time', '>=', $start_time)
            ->where('recharge_time', '<=', $end_time)->groupBy('merchant_id')->where('type', 1)
            ->groupBy('merchant_id')->first();
        return empty($data) ? 0 : $data->amount;
    }

    public static function minus($merchant_id, $start_time, $end_time) {
        $model = new MerchantAdvance();
        $data = $model
            ->select(DB::raw('SUM(amount) as amount'))
            ->where('merchant_id', $merchant_id)->where('recharge_time', '>=', $start_time)
            ->where('recharge_time', '<=', $end_time)->groupBy('merchant_id')->where('type', 2)
            ->groupBy('merchant_id')->first();
        return empty($data) ? 0 : $data->amount;
    }

    public function merchant() {
        return $this->hasOne('App\Models\Merchant', 'id', 'merchant_id');
    }
}
