<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-01-17
 * Time: 10:39
 */

namespace Aliyun\Controller;


include SITE_PATH.'/simplewind/Core/Library/Alidayu/TopSdk.php';
use Think\Controller;

class AlidayuController extends Controller
{
    function index(){
        $c = new \TopClient;
        $c->appkey = '23600529';
        $c->secretKey = 'a5adb9f3dcd3450db9348ba5f4322e38';
        $req = new \AlibabaAliqinFcSmsNumSendRequest;
        $req->setExtend("");
        $req->setSmsType("normal");
        $req->setSmsFreeSignName("身份验证");
        $param = array(
            'code'      => build_verify_no(),
            'product'   => '面料馆',
        );
        $req->setSmsParam(json_encode($param));
        $req->setRecNum("18520121334");
        $req->setSmsTemplateCode("SMS_41410009");
        $resp = $c->execute($req);

        print_r($resp);
    }

}