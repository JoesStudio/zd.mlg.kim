<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2014 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
namespace Portal\Controller;
use Common\Controller\HomebaseController;
use Common\Controller\MemberbaseController;

/**
 * 商家列表
*/
class NeedController extends MemberbaseController  {

	protected $biz_member_model;
    protected $demand_model;

    function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->biz_member_model = D("BizMember");
        $this->demand_model = D("Demand/Demand");

        if(sp_is_mobile()){
            $wx = new \Wx\Common\Wechat();
            $this->assign('jsApiParams',json_encode($wx->getSignPackage(),256));
        }
        
    }

	public function index() {
	    if(!sp_is_mobile_bind()){
	        $this->error('请先绑定手机',leuu('User/Center/profile'));
        }
    	$this->display(":needForm");
	}

    public function post(){
        $uid = sp_get_current_userid();
        if(!sp_is_user_login()){
            $this->error("你还没登陆！");exit();
        }

        if(IS_POST){
            if($_FILES){
                $config = array(
                    'maxSize'    =>    3145728, 
                    'rootPath'   =>    'data/', //文件上传跟目录
                    'savePath'   =>    'upload/need-img/', //设置附件上传目录 
                    'saveName'   =>    array('uniqid',''),
                    'exts'       =>    array('jpg', 'gif', 'png', 'jpeg'), 
                    'autoSub'    =>    true,
                    'subName'    =>    array('date','Y-m-d'),
                    );

                $upload = new \Think\Upload($config);// 实例化上传类
                $info   =   $upload->upload();

                if(!$info){
                    $this->error("图片上传失败，请从新上传！");die;//输出错误提示
                }else{
                    $need_img = array();
                    foreach($info as $key => $value){
                        $need_img[] = 'need-img/'.date('Y-m-d',time()).'/'.$value['savename']; 
                    }
                    $need_img = json_encode($need_img,true);
                }
            
            }
            if(!sp_is_mobile()){
                $_POST['demand_img'] = $need_img;
            }else{
                $need_img = array();
                foreach($_POST['demand_img'] as $key => $value){
                        $need_img[] = $value; 
                    }
                $_POST['demand_img'] = json_encode($need_img,true);
            }
            $_POST['user_id'] = $uid;
            $_POST['demand_created'] = time();
            $_POST['demand_end_date'] = strtotime($_POST['demand_end_date']);
            $_POST['demand_expect_date'] = strtotime($_POST['demand_expect_date']);

            $_POST['demand_contact'] = json_encode($_POST['demand_contact'],true);

            $result = $this->demand_model->saveDemand($_POST);
            if($result !== false){
                D('Demand/Log')->logAction($result,sp_get_current_userid());
                $this->success("发布成功!",leuu('Demand/Index/view',array('id'=>$result)));
            }else{
                $this->error($this->demand_model->getError());
            }
        }
    }
	
    function wx_post(){
        if (isset($_GET['api'])) {
            $api = $_GET['api'];
            //上传
            if ($api == 'upload') {
                $mediaId = $_POST['media_id'];
                $file = upload($mediaId);
                if ($file) {
                exit (toJson(200, array('url' => $file)));
                } else {
                exit (toJson(400, null, 'error'));
                }
            }
        }
    }

    /**
    * 上传图片
    * @param media_id
    */
    function upload($media_id) {
        $access_token = getAccessToken();
        if (!$access_token) return false;
        $url = "http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=".$access_token."&media_id=".$media_id;
        if (!file_exists(__ROOT__.'/data/upload/need-img/')) {
            mkdir(__ROOT__.'/data/upload/need-img/', 0775, true); //将图片保存到upload目录
        }
        $fileName = date('YmdHis').rand(1000,9999).'.jpg';
        $targetName = __ROOT__.'/data/upload/need-img/'. $fileName;

        $ch = curl_init($url); 
        $fp = fopen($targetName, 'wb'); 
        curl_setopt($ch, CURLOPT_FILE, $fp); // 设置输出文件的位置，值是一个资源类型
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
        return __ROOT__.'/data/upload/need-img/'.$fileName; //输出文件名
    }

    /**
    * 输出json
    */
    function toJson ($code = 200, $data = array(), $message = 'success') {
    return json_encode(array('code' => $code, 'data' => $data, 'message' => $message));
    }
    
}
