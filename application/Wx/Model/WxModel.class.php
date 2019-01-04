<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-09-23
 * Time: 9:33
 */

namespace Wx\Model;


use Common\Model\CommonModel;

class WxModel extends CommonModel
{
    protected $autoCheckFields = false;

    protected $config;

    // 开发者中心-配置项-AppID(应用ID)
    public $appId;
    // 开发者中心-配置项-AppSecret(应用密钥)
    public $appSecret;
    // 开发者中心-配置项-服务器配置-Token(令牌)
    public $token;
    // 开发者中心-配置项-服务器配置-EncodingAESKey(消息加解密密钥)
    public $encodingAESKey;
    // wechat模块
    public $wechat;
    // api模块
    public $api;

    function __construct(){
        parent::__construct();
        $this->config = get_wx_configs();
        $this->appId = $this->config['appId'];
        $this->appSecret = $this->config['appSecret'];
        $this->token = $this->config['token'];
        $this->encodingAESKey = $this->config['encodingAESKey'];

        // wechat模块 - 处理用户发送的消息和回复消息
        $this->wechat = new \Gaoming13\WechatPhpSdk\Wechat(array(
            'appId' => $this->appId,
            'token' =>     $this->token,
            //'encodingAESKey' =>    $this->encodingAESKey //可选
        ));

        // api模块 - 包含各种系统主动发起的功能
        $this->api = new \Gaoming13\WechatPhpSdk\Api(array(
            'appId' => $this->appId,
            'appSecret'    => $this->appSecret,
            'get_access_token' => function(){
                return S('wechat_token');
            },
            'save_access_token' => function($token) {
                S('wechat_token', $token);
            }
        ));
    }

    function update_menu($data){
        if(is_object($data) || is_array($data)){
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        $this->api->create_menu($data);
    }

    function test(){
        print_r($this->api->get_menu());
    }

}