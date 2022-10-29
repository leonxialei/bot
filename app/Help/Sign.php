<?php

namespace App\Help;
class Sign {
    public function encode($params, $key) {
        $sourceStr = $this->ASCII($params).'&key='.$key;
        return strtoupper(md5($sourceStr));
    }

    public function zhongyou($params, $key) {
        $sourceStr = $this->ASCII($params).$key;
        return md5($sourceStr);
    }

    public function brencode($params, $key) {
        $sourceStr = $this->ASCII($params).$key;
        return strtoupper(md5($sourceStr));
    }

    public function brcbencode($params, $key) {
        if(!empty($params)){
            $p =  ksort($params);
            if($p){
                $str = '';
                foreach ($params as $k=>$val){
                    $str .= $k .'=' . $val . '&';
                }
                $strs = rtrim($str, '&');

            }
        }
        dd($strs.$key);
        return strtoupper(md5($strs.$key));
    }

    public function pxencode($params, $key) {
        if(!empty($params)){
            $p =  ksort($params);
            if($p){
                $str = '';
                foreach ($params as $k=>$val){
                    if($val != ''){
                        $str .= $k .'=' . $val . '&';
                    }
                }
                $sourceStr = rtrim($str, '&');
            }
        }



        $sourceStr = $sourceStr.$key;
        return strtoupper(md5($sourceStr));
    }

    private function ASCII($params = []) {
        if(!empty($params)){
            $p =  ksort($params);
            if($p){
                $str = '';
                foreach ($params as $k=>$val){
                    if($val != ''){
                        $str .= $k .'=' . $val . '&';
                    }
                }
                $strs = rtrim($str, '&');
                return $strs;
            }
        }
        return false;
    }

    public function wuyouEncode($params, $key, $token) {
        $sourceStr = $this->wuyouASCII($params).$key.$token;
        return strtoupper(md5($sourceStr));
    }

    private function wuyouASCII($params = []) {
        if(!empty($params)){
            $p =  ksort($params);
            if($p){
                $str = '';
                foreach ($params as $k=>$val){
                    if(!empty($val)){
                        $str .= $k. $val;
                    }
                }
                $strs = rtrim($str, '&');
                return $strs;
            }
        }
        return false;
    }

    public function testEncode($params, $key) {
        $sourceStr = $this->ASCII($params).$key;
        error_log(print_r($sourceStr,1)."\n" ,3, 'facaisign.txt');
        error_log(print_r(strtoupper(md5($sourceStr)),1)."\n" ,3, 'facaisign.txt');

        return strtoupper(md5($sourceStr));
    }
}
