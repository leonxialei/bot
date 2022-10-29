<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
class UserController extends Controller
{
    public function store(Request $request){
        if(empty($request->input('user'))) {
            return [
                'code' => 50001,
                'result' => false,
                'msg' => '账号不能为空！'
            ];
        }
        if(empty($request->input('password'))) {
            return [
                'code' => 50002,
                'result' => false,
                'msg' => '账号不能为空！'
            ];
        }

        $userModel = new User();
        $user = $userModel->where('username', $request->input('user'))->first();
        if(empty($user)) {
            return [
                'code' => 50003,
                'result' => false,
                'msg' => '无此账号！'
            ];
        }
        if($user->status != 1) {
            return [
                'code' => 50004,
                'result' => false,
                'msg' => '此账号被停用！'
            ];
        }
        if($user->password != md5($request->input('password'))) {
            return [
                'code' => 50004,
                'result' => false,
                'msg' => '密码错误！'
            ];
        }

        $ip = $_SERVER['REMOTE_ADDR'];
        $logModel = new LoginLog();
        $logModel->user_id = $user->id;
        $logModel->ip = $ip;
        $logModel->created = time();
        $logModel->save();
        session(['account' => [
            'user_id' => $user->id,
            'username' => $user->username,
            'type' => $user->type,
            'status' => $user->status
        ]]);
        return [
            'code' => 200,
            'result' => true,
            'msg' => '登录成功！正在安全验证...'
        ];
    }
}
