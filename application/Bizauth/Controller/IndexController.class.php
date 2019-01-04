<?php

/**
 * 商家入驻
 */
namespace Bizauth\Controller;
use Common\Controller\MemberbaseController; 

class IndexController extends MemberbaseController {
    protected $member_model;
    protected $_model;
    protected $member;
    protected $role_id = 1;

    function _initialize(){
        parent::_initialize();
        $this->member_model = D("BizMember");
        $this->_model = D('BizAuth');
        $this->member = $this->member_model->getMemberByOpUser($this->userid);
        $this->assign('member',$this->member);
        $protected_actions = array('personal', 'personal_post', 'company', 'company_post');
        if (isset($this->user['operator'])) {
            if (in_array(ACTION_NAME, $protected_actions)) {
                if ($this->user['operator']['role_id'] != $this->role_id) {
                    $this->error('只有该面料商的运营管理人员才能访问这个页面！');
                }
            }
        }
    }
    
    public function index() {
        if($this->member['auth']['auth_status'] == 1 && sp_is_mobile()){
            $this->redirect('info');
        }
        $this->display();
    }

    /*个人认证*/
    public function personal() {
        $member = $this->member_model->getMemberByOpAdmin($this->userid);

        if(in_array($member['auth']['auth_status'], array(1,2))){
            $this->redirect('index');
        }

        if(!empty($member['auth']['auth_district'])){
            $areas = D('Areas')->getAreasByDistrict($member['auth']['auth_district']);
            $this->assign('areas', $areas['areas']);
        }

        $this->assign('member',$member);
        $this->display();
    }

    public function personal_post() {
        if(IS_POST && $_POST['act'] == 'personal'){

            $post = I('post.');
            $data = $post['auth'];

            if($post['agreement'] != 1){
                $this->error('请同意条款！');
            }

            $member = $this->member_model->getMemberByOpAdmin($this->userid);

            if(!empty($member)){
                if($member['authenticated'])
                if(in_array($member['authenticated'], array(1,2))){
                    $this->error('非法操作，当前认证状态不允许这次操作！');
                }
                if(in_array($member['auth']['auth_status'], array(1,2))){
                    $this->error('非法操作，当前认证状态不允许这次操作！');
                }
            }


            $verify = $post['mobile_verify'];
            if(empty($verify)){
                $this->error('请输入验证码');
            }else{
                list($result, $error_code, $sms_id) = D('Sms/Sms')->checkCode($this->user['userinfo']['mobile'], $verify);
                if($result !== true){
                    $this->error('验证码错误');
                }
            }

            if(!empty($member)){
                $data['id'] = $member['auth']['id'];
            }else{
                //创建Member和Operator的数据
                $memberData = array();
                $result = $this->member_model->saveMember($memberData);
                if($result !== false){
                    $data['member_id'] = $result;
                    $operatorData = array(
                        'member_id' => $result,
                        'user_id'   => $this->userid,
                        'role_id'   => $this->role_id,
                    );
                    $op_model = D('BizOperator');
                    $result = $op_model->saveOperator($operatorData);
                    if($result === false){
                        $this->error($op_model->getError());
                    }
                }else{
                    $this->error($this->member_model->getError());
                }
            }

            $data['auth_status'] = 2;
            $data['auth_type'] = 2;
            $data['auth_apply_time'] = date("Y-m-d H:i:s",time());
            $result = $this->_model->saveAuth($data);

            if($result !== false){
                $auth_id = isset($data['id']) ? $data['id']:$result;
                D('Sms/Sms')->setUsed($sms_id);
                $this->refresh_session();
                D('Log')->logAction($auth_id, $this->userid);
                $this->success('认证申请已提交！',leuu('index'));
            }else{
                $this->error($this->member_model->getError());
            }
        }
    }


    /*企业认证*/
    public function company() {

        $member = $this->member_model->getMemberByOpAdmin($this->userid);
        if($member['auth']['auth_status'] == 2){
            $this->redirect('index');
        }

        if($member['auth']['auth_type'] == 1
            && $member['auth']['auth_status'] == 1){
            $this->redirect('index');
        }

        if(!empty($member['auth']['auth_district'])){
            $areas = D('Areas')->getAreasByDistrict($member['auth']['auth_district']);
            $this->assign('areas', $areas['areas']);
        }

        $this->assign('member',$member);
        $this->display();
    }

    public function company_post() {
        if(IS_POST && $_POST['act'] == 'company'){

            $post = I('post.');
            $data = $post['auth'];

            if($post['agreement'] != 1){
                $this->error('请同意条款！');
            }

            $member = $this->member_model->getMemberByOpAdmin($this->userid);

            if(!empty($member)){
                if($member['authenticated'])
                    if(in_array($member['authenticated'], array(1,2))
                    && $member['biz_type'] == 1){
                        $this->error('非法操作，当前认证状态不允许这次操作！');
                    }
                if(in_array($member['auth']['auth_status'], array(1,2))
                && $member['auth']['auth_type'] == 1){
                    $this->error('非法操作，当前认证状态不允许这次操作！');
                }
            }


            $verify = $post['mobile_verify'];
            if(empty($verify)){
                $this->error('请输入验证码');
            }else{
                list($result, $error_code, $sms_id) = D('Sms/Sms')->checkCode($this->user['userinfo']['mobile'], $verify);
                if($result !== true){
                    $this->error('验证码错误'.$error_code);
                }
            }

            if(!empty($member)){
                $data['id'] = $member['auth']['id'];
            }else{
                $memberData = array();
                $result = $this->member_model->saveMember($memberData);
                if($result !== false){
                    $data['member_id'] = $result;
                    $operatorData = array(
                        'member_id' => $result,
                        'user_id'   => $this->userid,
                        'role_id'   => $this->role_id,
                    );
                    $op_model = D('BizOperator');
                    $result = $op_model->saveOperator($operatorData);
                    if($result === false){
                        $this->error($op_model->getError());
                    }
                }else{
                    $this->error($this->member_model->getError());
                }
            }

            $data['auth_status'] = 2;
            $data['auth_type'] = 1;
            $data['auth_apply_time'] = date("Y-m-d H:i:s",time());
            $result = $this->_model->saveAuth($data);

            if($result !== false){
                $auth_id = isset($data['id']) ? $data['id']:$result;
                D('Sms/Sms')->setUsed($sms_id);
                $this->refresh_session();
                D('Log')->logAction($auth_id, $this->userid);
                $this->success('认证申请已提交！',leuu('index'));
            }else{
                $this->error($this->member_model->getError());
            }
        }
    }

    /*认证的信息*/
    public function info(){
        $this->assign($this->member);
        $this->display();
    }
}


