<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-01-17
 * Time: 11:15
 */


function build_verify_no()
{
    /* 选择一个随机的方案 */
    mt_srand((double) microtime() * 1000000);
    return str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
}

function send_sms($data, $tpl_code, $sign_name = '面料馆', $extend = '')
{
    $to = $data['to'];
    $param = $data['param'];
    $usage = $data['usage'];

    $api = new \Sms\Common\AlidayuSms($config = array(
        'appkey'    => '23600529',
        'secretKey' => 'a5adb9f3dcd3450db9348ba5f4322e38',
        'format'    => 'json',
    ));

    list($exception, $resp) = $api->send($to, $param, $tpl_code, $sign_name, $extend);

    $log = array(
        'to'    => $to,
        'usage' => $usage,
        'code'  => isset($param['code']) ? $param['code']:'',
        'content'   => '',
        'status'    => is_null($exception) && $resp['success'] == true ? 1:0,
        'error_code'=> is_null($exception) ? '':$exception['sub_code'],
        'error_msg' => is_null($exception) ? '':$exception['sub_msg'],
        'used'      => 0,
        'smeta'     => json_encode(array(
            'param' => $param,
            'tpl'   => $tpl_code,
            'sign'  => $sign_name,
        ), 256),
        'expire_time'   => date('Y-m-d H:i:s', time() + 60 * 15),
        'result'    => is_null($exception) ? $resp:$exception,
    );
    $result = D('Sms/Sms')->logSms($log);

    return $resp['success'];

    /*if ($result !== false) {
        if ($exception) {
            $this->error($exception['sub_msg']);
        } elseif ($resp['success'] !== true) {
            $this->error('验证码发送失败');
        } else {
            $result = array(
                'status'    => 1,
                'data'      => $resp,
            );
            $this->ajaxReturn($result);
        }
    } else {
        $this->error('验证码发送失败');
    }*/
}
