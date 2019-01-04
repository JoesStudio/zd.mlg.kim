<?php
/**
 */

namespace Wx\Common;

class Jssdk
{

    protected $config;

    // 开发者中心-配置项-AppID(应用ID)
    public $appId;
    // 开发者中心-配置项-AppSecret(应用密钥)
    public $appSecret;
    // 开发者中心-配置项-服务器配置-Token(令牌)
    public $token;
    //商户号
    protected $mchId;
    //商户号API密钥
    protected $key;
    // 开发者中心-配置项-服务器配置-EncodingAESKey(消息加解密密钥)
    public $encodingAESKey;
    // wechat模块
    public $wechat;
    // api模块
    public $api;

    function __construct($config=array()){
        if(empty($config)){
            if(function_exists("get_wx_configs")){
                $config = get_wx_configs();
            }
        }
        $this->config = $config;
        $this->appId = $config['appId'];
        $this->appSecret = $config['appSecret'];
        $this->token = $config['token'];
        $this->mchId = $config['mchId'];
        $this->key = $config['key'];
        $this->encodingAESKey = $config['encodingAESKey'];

    }


    //获取accessToekn
    public function getAccessToken()
    {

        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $this->appId . "&secret=" . $this->appSecret;
        // 微信返回的信息
        $returnData = json_decode($this->curlHttp($url));
        $resData['accessToken'] = $returnData->access_token;
        $resData['expiresIn'] = $returnData->expires_in;
        $resData['time'] = date("Y-m-d H:i",time());

        $res = $resData;
        return $res;
    }

    //curlHttp
    public function curlHttp($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($curl);
        curl_close($curl);
        return $res;
    }

    //获取api_ticket
    public function getJsApiTicket($accessToken)
    {

        $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=$accessToken&&type=jsapi";
        // 微信返回的信息
        $returnData = json_decode($this->curlHttp($url));

        $resData['ticket'] = $returnData->ticket;
        $resData['expiresIn'] = $returnData->expires_in;
        $resData['time'] = date("Y-m-d H:i", time());
        $resData['errcode'] = $returnData->errcode;

        return $resData;
    }

    // 获取签名
    public function getSignPackage() {
        // 获取token
        $token = $this->getAccessToken();
        // 获取ticket
        $ticketList = $this->getJsApiTicket($token['accessToken']);
        $ticket = $ticketList['ticket'];

        // 该url为调用jssdk接口的url
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        // 生成时间戳
        $timestamp = time();
        // 生成随机字符串
        $nonceStr = $this->createNoncestr();
        // 这里参数的顺序要按照 key 值 ASCII 码升序排序 j -> n -> t -> u
        $string = "jsapi_ticket=$ticket&noncestr=$nonceStr×tamp=$timestamp&url=$url";
        $signature = sha1($string);
        $signPackage = array (
            "appId" => 'wxda450acec7d47d60',
            "nonceStr" => $nonceStr,
            "timestamp" => $timestamp,
            "url" => $url,
            "signature" => $signature,
            "rawString" => $string,
            "ticket" => $ticket,
            "token" => $token['accessToken']
        );

        // 返回数据给前端
        return json_encode($signPackage);
    }

    // 创建随机字符串
    public function createNoncestr($length = 16)
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }



}
