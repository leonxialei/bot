<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Count extends Model
{
    protected $table = 'order_count';
    public $timestamps = false;

    public static function getCount() {
        $mod = new Count();
        $count = $mod->where('id', 1)->first();
        return $count->count;
    }

    public static function plus() {
        $mod = new Count();
        $count = $mod->where('id', 1)->first();
        $mod->where('id', 1)->update([
            'count' => $count->count + 1
        ]);
    }
}
