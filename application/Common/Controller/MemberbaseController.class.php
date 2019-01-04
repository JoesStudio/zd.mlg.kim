<?php
namespace Common\Controller;
use Common\Controller\HomebaseController;
class MemberbaseController extends HomebaseController{
    protected $users_model;
    protected $user;
    protected $userid;
    function _initialize() {
        parent::_initialize();
        
        $this->check_login();
        $this->check_user();

        if(sp_is_user_login()){
            $this->userid=sp_get_current_userid();
            $this->users_model=D("Common/Users");
                $this->refresh_session();
            /*if(empty($_SESSION['user'])){
                $this->refresh_session();
            }else{
                $this->user = session('user');
            }*/
        }
    }

    function refresh_session(){
        $this->user = $this->users_model->getUser($this->userid);
        session('user',$this->user);
    }

    function is_mobile_bind(){
        return !empty($this->user['userinfo']['mobile']);
    }
    
}
