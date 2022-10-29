<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class ChannelCode extends Model
{
    protected $table = 'channel_code';
    public $timestamps = false;

    public function upstreamChannel() {
        return $this->hasMany('App\Models\UpstreamChannel', 'code', 'code');
    }
}
