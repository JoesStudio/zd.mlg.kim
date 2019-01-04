<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-01-17
 * Time: 12:26
 */

namespace Sms\Common;

include SITE_PATH.'/simplewind/Core/Library/Alidayu/TopSdk.php';
use \TopClient;
use \AlibabaAliqinFcSmsNumSendRequest as SendRequest;
use \AlibabaAliqinFcSmsNumQueryRequest as QueryRequest;

class AlidayuSms
{
    public $client;
    public $sender;

    public function __construct($config=array()){
        if(empty($config)){
            $config = array(
                'appkey'    => '23600529',
                'secretKey' => 'a5adb9f3dcd3450db9348ba5f4322e38',
                'format'    => 'json',
            );
        }
        $this->client = new TopClient($config['appkey'],$config['secretKey']);
        $this->client->format = $config['format'];
    }

    public function send($to, $param, $tpl_code, $sign_name, $extend='', $type='normal'){
        $this->sender = new SendRequest;
        $this->sender->setExtend($extend);
        $this->sender->setSmsType($type);
        $this->sender->setSmsFreeSignName($sign_name);
        $this->sender->setSmsParam(json_encode($param));
        $this->sender->setRecNum($to);
        $this->sender->setSmsTemplateCode($tpl_code);
        $resp = $this->client->execute($this->sender);
        if(isset($resp->result->success)){
            return array(null, (array) $resp->result);
        }else{
            return array((array) $resp, null);
        }
    }


}