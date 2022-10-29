<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Merchant extends Model
{
    protected $table = 'merchant';
    public $timestamps = false;

    public function advance($merchant_id, $start, $end) {
        $model = new MerchantAdvance();
        $start_time = strtotime($start.' 00:00:00');
        $end_time = strtotime($end.' 23:59:59');
        $advances = $model->where('merchant_id', $merchant_id)
            ->where('created', '>=', $start_time)
            ->where('created', '<=', $end_time)
            ->get();
        $amount = 0;
        foreach ($advances as $adv) {
            if($adv->type == 1) {
                $amount = $amount + $adv->amount;
            } elseif($adv->type == 2) {
                $amount = $amount - $adv->amount;
            }
        }
        return $amount;
    }

    public function agency() {
        return $this->hasOne('App\Models\Merchant', 'id', 'agency_id');
    }
}
