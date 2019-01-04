<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-12-29
 * Time: 14:58
 */

namespace Wx\Controller;


use Common\Controller\HomebaseController;

class RegisterController extends HomebaseController
{
    protected $_model;

    protected $default_user_type = 20;

    function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->_model = D('Users');
    }

    public function doregister(){

        if(empty($_POST['openid'])){
            $this->error('非法操作！');
        }

        $openid = $_POST['openid'];
        $password = isset($_POST['password']) ? $_POST['password']:$openid;
        $username = isset($_POST['username']) ? $_POST['username']:'wxuser_'.time();
        $nickname = $_POST['nickname'];
        $sex = $_POST['sex'];
        $avatar = $_POST['avatar'];

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
            "user_type"=>$this->default_user_type,//会员
            "openid"=>$openid,
        );
        if(!empty($_SESSION['invite_code'])){
            $data['invite_code'] = $_SESSION['invite_code'];
        }
        $result = $this->_model->addUser($data);
        if($result){
            $userinfo = array(
                'user_id'   => $result,
                'nickname'  => $nickname,
                'avatar'    => $avatar,
                'sex'       => $sex,
            );
            D('UserInfo')->saveInfo($userinfo);
        }


        if($result > 0){
            $user_id = $result;
            $user = $this->_model->getUser($user_id);
            if($user){
                $redirect = login_session($user);
                $this->success('账号已创建！',$redirect);
            }else{
                $this->error('账号已创建，但加载账号失败，请重新登录！');
            }
        }else{
            $this->error('创建账号失败！');
        }
    }

}