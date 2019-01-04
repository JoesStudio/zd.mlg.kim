<?php

/**
 * 商家入驻
 */
namespace User\Controller;
use Common\Controller\HomebaseController; 

class CertificateController extends HomebaseController {
    protected $contact_model;
    protected $member_model;
    protected $auth_model;

    function _initialize(){
        parent::_initialize();
        $this->contact_model =M("Biz_contact");
        $this->member_model =M("Biz_member");
        $this->auth_model =M("Biz_auth");
    }
    
    public function index() {
        
        $this->display(":certificate");
    }

    /*个人认证*/
    public function personal() {
        $uid = sp_get_current_userid();
        $count = $this->member_model->where("id=$uid")->count();

        if($count){
            $join1 = "LEFT JOIN __BIZ_CONTACT__ contact ON contact.id = member.id";
            $join2 = "LEFT JOIN __BIZ_AUTH__ auth ON auth.id = member.id";

            $join3 = 'LEFT JOIN __AREAS__ a ON a.id = contact.contact_province';
            $join4 = 'LEFT JOIN __AREAS__ b ON b.id = contact.contact_city';
            $join5 = 'LEFT JOIN __AREAS__ c ON c.id = contact.contact_district';

            $member_info = $this->member_model->alias("member")->field('member.*,contact.*,auth.*,a.name as contact_province,b.name as contact_city,c.name as contact_district')->join($join1)->join($join2)->join($join3)->join($join4)->join($join5)->where("member.id=$uid")->find();

            $this->assign('member_info',$member_info);
        }

       
        $this->display(":certificate_personal");
    }

    public function personal_post() {
        $uid = sp_get_current_userid();
        // $uid = 2333;
        if(IS_POST){
            if($_FILES){
                $upload = new \Think\Upload();// 实例化上传类   
                $upload->maxSize   =  3145728 ;//设置附件上传大小    
                $upload->exts      =  array('jpg', 'gif', 'png', 'jpeg');//设置附件上传类型 

                $rootpath = $upload->rootPath = 'data/';   //文件上传更目录   
                $upload->savePath  =  'upload/personal/';// 设置附件上传目录    // 上传文件

                $info   =   $upload->upload();

                $_POST['auth_photo_inhand']= 'personal/'.date('Y-m-d',time()).'/'.$info['auth_photo_inhand']['savename'];
                $_POST['auth_photo_idcard']= 'personal/'.date('Y-m-d',time()).'/'.$info['auth_photo_idcard']['savename'];
                $_POST['authenticated']=2; 
                $_POST['type']=2; 
                
                if(!$info) {// 上传错误提示错误信息       
                    $this->error($upload->getError());

                }else{// 上传成功

                    $_POST['id']=$uid;
                    $_POST['created_at'] = date("Y-m-d h-m-s",time());
                    if(($this->member_model->create($post))){
                        $check_member_id = $this->member_model->where("id=$uid")->find();
                        $check_auth_id = $this->member_model->where("id=$uid")->find();

                        if($check_member_id){
                            $this->member_model->save($_POST);
                        }else{
                            $this->member_model->add($_POST);  
                        }

                        if($this->contact_model->create($post)){
                            $check_contact_id = $this->auth_model->where("id=$uid")->find();
                            if($check_contact_id){
                            $this->contact_model->save($_POST);
                            }else{
                                $this->contact_model->add($_POST);  
                            } 
                        }
                        if($this->auth_model->create($post)){
                            $check_auth_id = $this->auth_model->where("id=$uid")->find();
                            if($check_auth_id){
                            $this->auth_model->save($_POST);
                            }else{
                                $this->auth_model->add($_POST);  
                            } 
                        }

                        $this->redirect("Certificate/submit");  
                    }else{
                        $this->error('提交失败!');
                    }       
                }

            }


        }
       

    }


    /*企业认证*/
    public function company() {
        $uid = sp_get_current_userid();
        $count = $this->member_model->where("id=$uid")->count();

        if($count){
            $join1 = "LEFT JOIN __BIZ_CONTACT__ contact ON contact.id = member.id";
            $join2 = "LEFT JOIN __BIZ_AUTH__ auth ON auth.id = member.id";

            $join3 = 'LEFT JOIN __AREAS__ a ON a.id = contact.contact_province';
            $join4 = 'LEFT JOIN __AREAS__ b ON b.id = contact.contact_city';
            $join5 = 'LEFT JOIN __AREAS__ c ON c.id = contact.contact_district';

            $member_info = $this->member_model->alias("member")->field('member.*,contact.*,auth.*,a.name as contact_province,b.name as contact_city,c.name as contact_district')->join($join1)->join($join2)->join($join3)->join($join4)->join($join5)->where("member.id=$uid")->find();

            $this->assign('member_info',$member_info);
        }
        
        $this->display(":certificate_company");
    }

    public function company_post() {
        $uid = sp_get_current_userid();
        // $uid = 5548;
        if(IS_POST){
            if($_FILES){
                $upload = new \Think\Upload();// 实例化上传类   
                $upload->maxSize   =  3145728 ;//设置附件上传大小    
                $upload->exts      =  array('jpg', 'gif', 'png', 'jpeg');//设置附件上传类型 

                $rootpath = $upload->rootPath = 'data/';   //文件上传更目录   
                $upload->savePath  =  'upload/company/';// 设置附件上传目录    // 上传文件

                $info   =   $upload->upload();

                $_POST['auth_photo_licence']= 'company/'.date('Y-m-d',time()).'/'.$info['auth_photo_licence']['savename'];
                $_POST['authenticated']=2;
                $_POST['type']=1;  
                
                if(!$info) {// 上传错误提示错误信息       
                    $this->error($upload->getError());
                }else{// 上传成功
                    $_POST['id']=$uid;
                    $_POST['created_at'] = date("Y-m-d h-m-s",time());
                    if(($this->member_model->create($post))){
                        $check_member_id = $this->member_model->where("id=$uid")->find();
                        $check_auth_id = $this->member_model->where("id=$uid")->find();

                        if($check_member_id){
                            $this->member_model->save($_POST);
                        }else{
                            $this->member_model->add($_POST);  
                        }

                        if($this->contact_model->create($post)){
                            $check_contact_id = $this->auth_model->where("id=$uid")->find();
                            if($check_contact_id){
                            $this->contact_model->save($_POST);
                            }else{
                                $this->contact_model->add($_POST);  
                            } 
                        }
                        if($this->auth_model->create($post)){
                            $check_auth_id = $this->auth_model->where("id=$uid")->find();
                            if($check_auth_id){
                            $this->auth_model->save($_POST);
                            }else{
                                $this->auth_model->add($_POST);  
                            } 
                        }

                        $this->redirect("Certificate/submit");  
                    }else{
                        $this->error('提交失败!');
                    }         
                }

            }


        }
       



    }

     public function submit() {
        
        
        $this->display(":certificate_submit");
    }

    /*企业认证*/
    public function result() {
        $uid = sp_get_current_userid();
        $member_info = $this->member_model->where("id=$uid")->find();
        $auth_status = $member_info['authenticated'];
        $member_type = $member_info['type'];
        
        $this->assign('auth_status',$auth_status);
        $this->assign('member_type',$member_type);
        
        $this->display(":certificate_result");
    }

    

}


