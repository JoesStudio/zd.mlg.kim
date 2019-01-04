<?php

/**
 * 商家入驻
 */
namespace User\Controller;
use Common\Controller\MemberbaseController; 

class CertificateController extends MemberbaseController {
    protected $_model;

    function _initialize(){
        parent::_initialize();
        $this->_model = D("BizMember");
    }
    
    public function index() {
        $member = $this->_model->getMember($this->userid);
        $this->assign('member', $member);
        $this->display();
    }

    /*个人认证*/
    public function personal() {
        $member = $this->_model->getMember($this->userid);
        if(in_array($member['authenticated'], array(1,2))){
            $this->redirect('index');
        }

        $this->assign('member', $member);
        $this->display();
    }

    public function personal_post() {
        if(IS_POST && $_POST['act'] == 'personal'){
            if($_POST['agreement'] != 1){
                $this->error('请同意条款！');
            }

            $member = $this->_model->getMember($this->userid);
            if(in_array($member['authenticated'], array(1,2))){
                $this->error('非法操作，当前认证状态不允许这次操作！');
            }

            $_POST['id'] = $this->userid;
            $_POST['authenticated'] = 2;    //未审核状态
            $_POST['type'] = 2;   //个人认证类型
            $result = $this->_model->saveMember();
            if($result){
                $this->success('认证申请已提交！',leuu('index'));
            }else{
                $this->error($this->_model->getError());
            }
        }
    }


    /*企业认证*/
    public function company() {
        $member = $this->_model->getMember($this->userid);
        if($member['authenticated'] == 2){
            $this->redirect('index');
        }

        if($member['type'] == 1 && $member['authenticated'] == 1){
            $this->redirect('index');
        }

        $this->assign('member', $member);
        $this->display();
    }

    public function company_post() {
        if(IS_POST && $_POST['act'] == 'company'){
            if($_POST['agreement'] != 1){
                $this->error('请同意条款！');
            }

            $member = $this->_model->getMember($this->userid);
            if(in_array($member['authenticated'], array(1,2))){
                $this->error('非法操作，当前认证状态不允许这次操作！');
            }

            $_POST['id'] = $this->userid;
            $_POST['authenticated'] = 2;    //未审核状态
            $_POST['type'] = 1;   //企业认证类型
            $result = $this->_model->saveMember();
            if($result){
                $this->success('认证申请已提交！',leuu('index'));
            }else{
                $this->error($this->_model->getError());
            }
        }
    }

    /*认证的信息*/
    public function info(){
        $member = $this->_model->getMember($this->userid);
        $this->assign($member);
        $this->display();
    }
}


