<?php
/**
 * Created by PhpStorm.
 * User: Jason
 * Date: 2016-09-07
 * Time: 16:10
 */

namespace Common\Controller;

use Wx\Common\Wechat;


class WxbaseController extends HomebaseController
{

    // wechat模块
    protected $wechat;
    // api模块
    protected $api;

    protected $wx;

    function __construct(){
        parent::__construct();
        $this->wx = new Wechat(get_wx_configs());

        // wechat模块 - 处理用户发送的消息和回复消息
        $this->wechat = $this->wx->wechat;

        // api模块 - 包含各种系统主动发起的功能
        $this->api = $this->wx->api;
    }

    function _initialize()
    {
        parent::_initialize();
    }

    function test(){
        //print_r(json_decode(S('wechat_token')));
        print_r($_SESSION['user']);
        $this->api->send($_SESSION['user']['openid'], array(
            'type' => 'news',
            'articles' => array(
                array(
                    'title' => '图文消息标题1',                               //可选
                    'description' => '图文消息描述1',                     //可选
                    'picurl' => 'http://me.diary8.com/data/img/demo1.jpg',  //可选
                    'url' => 'http://www.example.com/'                      //可选
                ),
                array(
                    'title' => '图文消息标题2',
                    'description' => '图文消息描述2',
                    'picurl' => 'http://me.diary8.com/data/img/demo2.jpg',
                    'url' => 'http://www.example.com/'
                ),
                array(
                    'title' => '图文消息标题3',
                    'description' => '图文消息描述3',
                    'picurl' => 'http://me.diary8.com/data/img/demo3.jpg',
                    'url' => 'http://www.example.com/'
                )
            ),
        ));
        /*echo $this->appId;
        echo $this->appSecret;
        echo $this->token;*/
    }

}