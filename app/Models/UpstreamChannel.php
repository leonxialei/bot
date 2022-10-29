<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class UpstreamChannel extends Model
{
    protected $table = 'upstream_channel';
    public $timestamps = false;

    public function upstream() {
        return $this->hasOne('App\Models\Upstream', 'id', 'upstream_id');
    }

    public function channelCode() {
        return $this->hasOne('App\Models\ChannelCode', 'code', 'code');
    }

    public function merchantChannel() {
        return $this->hasMany('App\Models\MerchantChannel', 'channel_id', 'id');
    }

    public static function obj($id) {
        $model = new UpstreamChannel();
        return $model->where('id', $id)->first();
    }
}
