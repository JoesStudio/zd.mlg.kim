<?php
namespace Asset\Controller;

use Common\Controller\MemberbaseController;
use Wx\Common\Wechat;

class WxAssetApiController extends MemberbaseController
{

    protected $_model;
    protected $uid;
    protected $utype;
    protected $wx;

    function _initialize()
    {
        $userid = sp_get_current_userid();
        $this->uid = $userid;
        $this->utype = 3;
        if($this->user['member']){
            $this->uid = $this->user['member']['id'];
            $this->utype = 2;
        }

        if (empty($userid)) {
            $this->ajaxReturn(array(
                'code' => 400,
                'error' => '非法上传!'
            ));
        }

        $config = get_wx_configs();
        $this->wx = new Wechat($config);
    }

    function upload_post(){
        $mediaId = $_POST['media_id'];
        $filepath = $this->upload($mediaId);
        if ($filepath) {
            $this->save_asset($filepath);
            $this->toJson(200, array('url' => get_thumb_url($filepath), 'path' => $filepath));
        } else {
            $this->toJson(400, null, 'error');
        }
    }

    function save_asset($filepath){
        $data = array(
            'uid'   => $this->uid,
            'utype' => $this->utype,
            'key'   => 'file',
            //'filename'  => $oriName,
            //'filesize'  => $first['size'],
            'filepath'  => $filepath,
            'uploadtime'=> date('Y-m-d H:i:s'),
            'status'=> 1,
            //'meta'  => json_encode($meta, 256),
            'suffix'=> 'jpg',
        );
        return D('Asset')->saveAsset($data);
    }

    /**
     * 上传图片
     * @param media_id
     */
    function upload($media_id) {
        $access_token = $this->wx->api->get_access_token();
        if (!$access_token) return false;
        $url = "http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=".$access_token."&media_id=".$media_id;

        $rootpath = './'.C("UPLOADPATH");
        $savepath = strtolower(MODULE_NAME).'/'.date('Ymd').'/';

        if (!file_exists($rootpath.$savepath)) {
            mkdir($rootpath.$savepath, 0775, true); //将图片保存到upload目录
        }

        $fileName = date('YmdHis').rand(1000,9999).'.jpg';
        $targetName = $rootpath.$savepath.$fileName;

        $ch = curl_init($url);
        $fp = fopen($targetName, 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fp); // 设置输出文件的位置，值是一个资源类型
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
        /*$this->ajaxReturn(array(
            'code' => 400,
            'error' => get_thumb_url($savepath.$fileName)
        ));*/
        return $savepath.$fileName; //输出文件名
    }

    //curl
    function getcurl($url, $data=array()){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch) ;
        return $response;
    }

    /**
     * 输出json
     */
    function toJson ($code = 200, $data = array(), $message = 'success') {
        return $this->ajaxReturn(array('code' => $code, 'data' => $data, 'message' => $message));
    }
}