<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


class Order extends Model
{
    protected $table = 'square_order';
    public $timestamps = false;

    public function upstream() {
        return $this->hasOne('App\Models\Upstream', 'id', 'upstream_id');
    }

    public function merchantChannel() {
        return $this->hasOne('App\Models\MerchantChannel', 'id', 'merchant_channel_id');
    }

    public static function detail($upstream_id, $start_date, $end_date) {
        $model = new Order();
        $start_time = strtotime($start_date.' 00:00:00');
        $end_time = strtotime($end_date.' 23:59:59');
        $orders = $model
            ->join('merchant_channel', 'square_order.merchant_channel_id', '=', 'merchant_channel.id')
            ->select(DB::raw('SUM(`square_order`.`pay_amount`) as `pay_amount`,SUM(`square_order`.`original_amount`) as `original_amount`, SUM(`square_order`.`amount`) as `amount`, `merchant_channel`.`channel_id`'))
            ->where('square_order.upstream_id', $upstream_id)
            ->where('square_order.created', '>=', $start_time)
            ->where('square_order.created', '<=', $end_time)
            ->where('square_order.status', 2)
            ->groupBy('merchant_channel.channel_id')
            ->get();
        return $orders;
    }

    public static function upstreamDetail($upstream_id, $start_date, $end_date) {
        $model = new Order();
        $start_time = strtotime($start_date.' 00:00:00');
        $end_time = strtotime($end_date.' 23:59:59');
        $orders = $model
            ->select(DB::raw('SUM(`original_amount`) as `original_amount`, SUM(`amount`) as `amount`'))
            ->where('upstream_id', $upstream_id)
            ->where('created', '>=', $start_time)
            ->where('created', '<=', $end_time)
            ->where('status', 2)
            ->first();
        return $orders;
    }

    public static function orderDetail($merchant_id, $start_date, $end_date) {
        $model = new Order();
        $start_time = strtotime($start_date.' 00:00:00');
        $end_time = strtotime($end_date.' 23:59:59');
        $orders = $model
            ->select(DB::raw('SUM(`merchant_amount`) as `merchant_amount`, SUM(`amount`) as `amount`'))
            ->where('customer_id', $merchant_id)
            ->where('created', '>=', $start_time)
            ->where('created', '<=', $end_time)
            ->where('status', 2)
            ->groupBy('customer_id')
            ->first();
        return $orders;
    }
    public static function merchantAmount($merchant_id, $start_date, $end_date) {
        $model = new Order();
        $start_time = strtotime($start_date.' 00:00:00');
        $end_time = strtotime($end_date.' 23:59:59');
        $orders = $model
            ->select(DB::raw('SUM(`merchant_amount`) as `merchant_amount`, SUM(`amount`) as `amount`'))
            ->where('customer_id', $merchant_id)
            ->where('created', '>=', $start_time)
            ->where('created', '<=', $end_time)
            ->where('status', 2)
            ->groupBy('customer_id')
            ->first();

        return $orders;
    }

    public static function merchantOriginalAmount($merchant_id, $start_date, $end_date) {
        $model = new Order();
        $start_time = strtotime($start_date.' 00:00:00');
        $end_time = strtotime($end_date.' 23:59:59');
        $orders = $model
            ->select(DB::raw('SUM(`merchant_amount`) as `merchant_amount`, SUM(`original_amount`) as `amount`'))
            ->where('customer_id', $merchant_id)
            ->where('created', '>=', $start_time)
            ->where('created', '<=', $end_time)
            ->where('status', 2)
            ->groupBy('customer_id')
            ->first();

        return $orders;
    }

    public static function merchantOrder($merchant_id, $start_date, $end_date, $status) {
        $model = new Order();
        $start_time = strtotime($start_date.' 00:00:00');
        $end_time = strtotime($end_date.' 23:59:59');
        $model = $model
            ->where('customer_id', $merchant_id)
            ->where('created', '>=', $start_time)
            ->where('created', '<=', $end_time);
        if(is_array($status)) {
            $model = $model->whereIn('status', $status);
        } else {
            $model = $model->where('status', $status);
        }
        $orders = $model->get();
        return $orders;
    }

    public static function merchantDetail($merchant_id, $start_date, $end_date) {
        $model = new Order();
        $start_time = strtotime($start_date.' 00:00:00');
        $end_time = strtotime($end_date.' 23:59:59');
        $orders = $model
            ->select(DB::raw('SUM(`original_amount`) as `original_amount`, SUM(`merchant_amount`) as `merchant_amount`, SUM(`amount`) as `amount`, `merchant_channel_id`'))
            ->where('customer_id', $merchant_id)
            ->where('created', '>=', $start_time)
            ->where('created', '<=', $end_time)
            ->where('status', 2)
            ->groupBy('merchant_channel_id')
            ->get();
        return $orders;
    }



    public static function log_change($upstream_id, $start_date, $end_date) {
        $logModel = new AdvanceLog();
        $start_time = strtotime($start_date.' 00:00:00');
        $end_time = strtotime($end_date.' 23:59:59');
        $logs = $logModel
            ->where('upstream_id', $upstream_id)
            ->where('created', '>=', $start_time)
            ->where('created', '<=', $end_time)
            ->get();
        $amount = 0;
        foreach($logs as $log) {
            if($log->type == 1) {
                $amount = $amount + $log->amount;
            } else if($log->type == 2) {
                $amount = $amount - $log->amount;
            }
        }

        return $amount;
    }

    public function customer() {
        return $this->hasOne('App\Models\Merchant', 'account', 'customer_id');
    }

    public static function succeed($merchant_channel_id, $start_time, $end_time) {
        $model = new Order();
//        $start_time = strtotime($start_date.' 00:00:00');
//        $end_time = strtotime($end_date.' 23:59:59');
        $order = $model
            ->select(DB::raw('SUM(`original_amount`) as `original_amount`, SUM(`merchant_amount`) as `merchant_amount`, SUM(`amount`) as `amount`, COUNT(`id`) as count'))
            ->where('merchant_channel_id', $merchant_channel_id)
            ->where('pay_time', '>=', $start_time)
            ->where('pay_time', '<=', $end_time)
            ->whereIn('status', [1,2])
            ->groupBy('merchant_channel_id')
            ->first();
        return $order;
    }

    public static function getMerchantDetail($merchant_id, $start_date, $end_date) {
        $model = new Order();
        $start_time = strtotime($start_date.' 00:00:00');
        $end_time = strtotime($end_date.' 23:59:59');

        $orders = $model
            ->select(DB::raw('SUM(`original_amount`) as `original_amount`, SUM(`merchant_amount`) as `merchant_amount`, SUM(`amount`) as `amount`, `code`'))
            ->where('customer_id', $merchant_id)
            ->where('pay_time', '>=', $start_time)
            ->where('pay_time', '<=', $end_time)
            ->where('status', 2)
            ->groupBy('code')
            ->get();
        return $orders;
    }

}
