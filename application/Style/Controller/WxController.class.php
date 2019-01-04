<?php
namespace User\Controller;
use Common\Controller\HomebaseController;
use Common\Model\UsersModel;
use Common\Model\UserInfoModel;
use Wx\Common\Wechat;

class WxController extends HomebaseController {

    public function _initialize() {
        parent::_initialize();
        $this->_model = new UsersModel();
    }

    public function getOpenid(){

        $wechat = new Wechat();

        echo json_encode($wechat);exit;
    }

    public function do_wechat_register(){

        if(!isset($_POST['wx_style_register']) && empty($_POST['openid'])){
            return array('非法操作！',null);
        }

        $openid = $_POST['openid'];
        $password = isset($_POST['password']) ? $_POST['password']:$openid;
        $username = isset($_POST['username']) ? $_POST['username']:'wxuser_'.time();
        $nicename = $_POST['nickname'];
        $sex = $_POST['sex'];
        $avatar = $_POST['avatar'];

        $data = array(
            'user_login' => $username,
            'avatar' => $avatar,
            'sex' => $sex,
            'nickname' => $nicename,
            'user_pass' => $password,
            'last_login_ip' => get_client_ip(0,true),
            'create_time' => date("Y-m-d H:i:s"),
            'last_login_time' => date("Y-m-d H:i:s"),
            'user_status' => 1,
            "user_type"=>$this->default_user_type,//会员
            "openid"=>$openid,
        );
        $this->_model->startTrans();
        $result = $this->_model->saveUser($data);

        if($result !== false){
            $info = array(
                'user_id'   => $result,
                'nickname'  => $nicename,
                'sex'       => $sex,
                'avatar'    => $avatar,
            );
            $infoModel = new UserInfoModel();
            $result = $infoModel->saveInfo($info);
        }

        if($result !== false){
            $this->_model->commit();
            return array(null, $result);
        }else{
            $this->_model->rollback();
            return array($this->_model->getError(), null);
        }
    }
}
