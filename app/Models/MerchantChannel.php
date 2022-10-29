<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class MerchantChannel extends Model
{
    protected $table = 'merchant_channel';
    public $timestamps = false;

    public function merchant() {
        return $this->hasOne('App\Models\Merchant', 'id', 'merchant_id');
    }

    public function channel() {
        return $this->hasOne('App\Models\UpstreamChannel', 'id', 'channel_id');
    }

    public function merchantAdvance() {
        return $this->hasMany('App\Models\MerchantAdvance', 'merchant_id', 'id');
    }
}
