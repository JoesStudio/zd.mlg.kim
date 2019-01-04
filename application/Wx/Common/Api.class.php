<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-02-17
 * Time: 11:54
 */

namespace Wx\Common;

use Gaoming13\WechatPhpSdk\Api as MainApi;
use Gaoming13\WechatPhpSdk\Utils\HttpCurl;
use Gaoming13\WechatPhpSdk\Utils\Error;
use Gaoming13\WechatPhpSdk\Utils\SHA1;
use Gaoming13\WechatPhpSdk\Utils\Xml;


class Api extends MainApi
{
    /**
     * 设定配置项
     *
     * @param array $config
     */
    public function __construct($config)
    {
        parent::__construct($config);
    }

    /**
     * 生成带参数的二维码
     *
     * @int $scene_id 场景值ID，临时二维码时为32位非0整型，永久二维码时最大值为100000（目前参数只支持1--100000）
     * @int $expire_seconds 可选：该二维码有效时间，以秒为单位。 最大不超过604800（即7天），默认为永久二维码，填写该项为临时二维码
     *
     * @return array(err, data)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     *
     * Examples:
     * ```
     * list($err, $data) = $api->create_qrcode(1234); // 创建一个永久二维码
     * list($err, $data) = $api->create_qrcode(1234, 100); //创建一个临时二维码，有效期100秒
     * ```
     * Result:
     * ```
     * [
     *  null,
     *  {
     *      ticket: "gQFM8DoAAAAAAAAAASxodHRwOi8vd2VpeGluLnFxLmNvbS9xLzlVeU83dGZsMXNldlAtQ0hmbUswAAIEQcrVVQMEZAAAAA==",
     *      expire_seconds: 100,
     *      url: "http://weixin.qq.com/q/9UyO7tfl1sevP-CHfmK0"
     *  }
     * ]
     * ```
     */

    public function create_qrcode($scene_id, $expire_seconds = 0)
    {
        $url = self::API_DOMAIN . 'cgi-bin/qrcode/create?access_token=' . $this->get_access_token();
        if (is_string($scene_id)) {
            $action_name = 'QR_LIMIT_STR_SCENE';
            $expire_seconds = 0;
            $scene = '{"scene_str":"'.$scene_id.'"}';
        } elseif ($expire_seconds == 0) {
            $action_name = 'QR_LIMIT_SCENE';
            $scene = '{"scene_id":"'.$scene_id.'"}';
        } else {
            $action_name = 'QR_SCENE';
            $scene = '{"scene_id":"'.$scene_id.'"}';
        }
        $expire = $expire_seconds == 0 ? '' : '"expire_seconds": ' . $expire_seconds . ',';
        $xml = sprintf('{%s"action_name": "%s", "action_info": {"scene": %s}}',
            $expire,
            $action_name,
            $scene);
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if (isset($res->ticket)) {
            return array(null, $res);
        } else {
            return array($res, null);
        }
    }
}