<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
class InitController extends Controller
{
    public function index(Request $request)
    {

        $data = [
            "clearInfo" => [
                "clearUrl" => "api/clear.json"
            ],
            "homeInfo" => [
                "title" => "首页",
                "icon" => "fa fa-home",
                "href" => "upstream/advance"
            ],
            "logoInfo" => [
                "title" => "后台管理",
                "image" => "images/logo.png",
                "href" => ""
            ],
            "menuInfo" => [
                "currency" => [
                    "title" => "后台管理",
                    "icon" => "fa fa-address-book",
                    "child" => []
                ]
            ]
        ];

        $menuModel = new Menu();
        $menus = $menuModel->where('status', 1)
            ->OrderBy('weight', 'DESC')->get()->toArray();
        foreach($menus as $key => $menu) {
            unset($menus[$key]['id']);
            unset($menus[$key]['status']);
            unset($menus[$key]['weight']);
        }
        $data['menuInfo']['currency']['child'] = $menus;
        return $data;
    }
}
