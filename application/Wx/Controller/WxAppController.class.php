<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-09-22
 * Time: 10:21
 */

namespace Wx\Controller;

use Think\Controller;

class WxAppController extends Controller
{
    function __construct() {
        parent::__construct();

        $this->appid = 'wx4aa42346f45a7436';
        $this->appsecret = 'b3fa79043f3f6d8178c376a095bd630b';
    }

    public function getOpenid() {

        $code = I('code');
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$this->appid}&secret={$this->appsecret}&js_code={$code}&grant_type=authorization_code";  

        $data = $this->http_request_curl($url);      
        echo $data;exit;
    }

    public function http_request_curl($url, $rawData = array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawData);

        $data = curl_exec($ch);
        curl_close($ch);
        return ($data);
    }

}