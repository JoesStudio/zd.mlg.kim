<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-09-23
 * Time: 9:33
 */

namespace Wx\Common;

use Wx\Common\Api;


class Wechat
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

        // wechat模块 - 处理用户发送的消息和回复消息
        $this->wechat = new \Gaoming13\WechatPhpSdk\Wechat(array(
            'appId' => $this->appId,
            'token' =>     $this->token,
            'encodingAESKey' =>    $this->encodingAESKey //可选
        ));

        // api模块 - 包含各种系统主动发起的功能
        $this->api = new Api(array(
            'appId' => $this->appId,
            'appSecret'    => $this->appSecret,
            'mchId' => $this->mchId, //微信支付商户号
            'key' => $this->key, //微信商户API密钥
            'get_access_token' => function(){
                return S('wechat_token');
            },
            'save_access_token' => function($token) {
                S('wechat_token', $token);
            }
        ));
    }

    function getConfig($key=null){
        return is_null($key) ? $this->config:$this->config[$key];
    }

    function update_menu($data){
        if(is_object($data) || is_array($data)){
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        $this->api->create_menu($data);
    }

    public function createLoginQrcode($timeout = 60)
    {
        $_model = D('Wx/Qr');
        $scene_str = (int)sprintf('%u', crc32(uniqid()));
        $where = array(
            'type'      => 1,
            'scene_str' => $scene_str,
            'scan_count'=> 0,
        );
        $_model->where($where)->delete();

        return $this->createQr($scene_str, 1, $timeout, 0);
    }

    public function createTfQr($id)
    {
        if (D('Tf/Tf')->where(array('id' => $id))->count() > 0) {
            return $this->createQr('TF_'.$id, 3, 0, $id);
        } else {
            return array(null, null);
        }
    }

    public function createSupplierQr($id)
    {
        if (D('BizMember')->where(array('id' => $id))->count() > 0) {
            return $this->createQr('BIZ_'.$id, 5, 0, $id);
        } else {
            return array(null, null);
        }
    }

    public function createCardQr($id)
    {
        if (D('Colorcard/Colorcard')->where(array('id' => $id))->count() > 0) {
            return $this->createQr('CARD_'.$id, 4, 0, $id);
        } else {
            return array(null, null);
        }
    }

    /*
     * 生成临时二维码
     * @param string $scene_str 参数
     * @param int $type 类型 1登陆 2绑定
     * @param int $target_id 目标id
     * @param int $timeout 过期时间
     */
    public function createQr($scene_str = null, $type = 1, $timeout = 60, $target_id = 0)
    {
        $_model = D('Wx/Qr');

        list($err, $data) = $this->api->create_qrcode($scene_str, $timeout);

        if(isset($err)){
            return array($err, null);
        }else{
            $ticket = (string) $data->ticket;
            $url = $this->api->get_qrcode_url($ticket);
            $date = date('Y-m-d H:i:s');
            $data = array(
                'scene_str' => $scene_str,
                'type'      => $type,
                'create_date'=> $date,
                'status'    => 1,
                'ticket'    => $ticket,
                'url'       => $url,
                'target_id' => $target_id,
            );
            if ($timeout > 0) {
                $data['expire_date'] = date('Y-m-d H:i:s', strtotime($date) + $timeout);
            }

            //将新的二维码信息插入到数据库
            $result = $_model->add($data);
            if ($result === false) {
                return array($_model->getDbError(), null);
            } else {
                $data['id'] = $result;
                return array(null, $data);
            }
        }
    }

    function regByOpenId($openid){
        $info = $this->api->get_user_info($openid);

        $password = $openid;
        $username = 'wxuser_'.time();
        $nickname = (string)$info[1]->nickname;
        $sex = (int)$info[1]->sex;
        $avatar = (string)$info[1]->headimgurl;

        $data = array(
            'user_login' => $username,
            'avatar' => $avatar,
            'sex' => $sex,
            'nickname' => $nickname,
            'user_pass' => sp_password($password),
            'last_login_ip' => get_client_ip(0,true),
            'create_time' => date("Y-m-d H:i:s"),
            'last_login_time' => date("Y-m-d H:i:s"),
            'user_status' => 1,
            "user_type"=>20,//会员
            "openid"=>$openid,
        );
        if(!empty($_SESSION['invite_code'])){
            $data['invite_code'] = $_SESSION['invite_code'];
        }
        $result = D('Users')->addUser($data);
        if($result){
            $user_id = $result;
            $userinfo = array(
                'user_id'   => $user_id,
                'nickname'  => $nickname,
                'avatar'    => $avatar,
                'sex'       => $sex,
            );
            D('UserInfo')->saveInfo($userinfo);
            return $result;
        }else{
            return false;
        }
    }



    public function getSignPackage() {
        $jsapiTicket = $this->getJsApiTicket();

        // 注意 URL 一定要动态获取，不能 hardcode.
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

        $timestamp = time();
        $nonceStr = $this->createNonceStr();

        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

        $signature = sha1($string);

        $signPackage = array(
            "appId"     => $this->appId,
            "nonceStr"  => $nonceStr,
            "timestamp" => $timestamp,
            "url"       => $url,
            "signature" => $signature,
            "rawString" => $string
        );
        return $signPackage;
    }

    private function createNonceStr($length = 16) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    private function getJsApiTicket() {
        // jsapi_ticket 应该全局存储与更新，以下代码以写入到文件中做示例
        $data = json_decode(file_get_contents("jsapi_ticket.json"));
        if ($data->expire_time < time()) {
            $accessToken = $this->getAccessToken();
            // 如果是企业号用以下 URL 获取 ticket
            // $url = "https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket?access_token=$accessToken";
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
            $res = json_decode($this->httpGet($url));
            $ticket = $res->ticket;
            if ($ticket) {
                $data->expire_time = time() + 7000;
                $data->jsapi_ticket = $ticket;
                $fp = fopen("jsapi_ticket.json", "w");
                fwrite($fp, json_encode($data));
                fclose($fp);
            }
        } else {
            $ticket = $data->jsapi_ticket;
        }

        return $ticket;
    }

    public function getAccessToken() {
        // access_token 应该全局存储与更新，以下代码以写入到文件中做示例
        $data = json_decode(file_get_contents("access_token.json"));
        if ($data->expire_time < time()) {
            // 如果是企业号用以下URL获取access_token
            // $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=$this->appId&corpsecret=$this->appSecret";
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->appId&secret=$this->appSecret";
            $res = json_decode($this->httpGet($url));
            $access_token = $res->access_token;
            if ($access_token) {
                $data->expire_time = time() + 7000;
                $data->access_token = $access_token;
                $fp = fopen("access_token.json", "w");
                fwrite($fp, json_encode($data));
                fclose($fp);
            }
        } else {
            $access_token = $data->access_token;
        }
        return $access_token;
    }

    private function httpGet($url) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        // 为保证第三方服务器与微信服务器之间数据传输的安全性，所有微信接口采用https方式调用，必须使用下面2行代码打开ssl安全校验。
        // 如果在部署过程中代码在此处验证失败，请到 http://curl.haxx.se/ca/cacert.pem 下载新的证书判别文件。
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_URL, $url);

        $res = curl_exec($curl);
        curl_close($curl);

        return $res;
    }
}