<?php

/**
 * 商家入驻
 */
namespace Portal\Controller;
use Common\Controller\HomebaseController; 

class ApplyCertificateController extends HomebaseController {
    protected $biz_contact_model;
    protected $biz_member_model;
    protected $biz_auth_model;
    protected $areas_model;
    function _initialize(){
        parent::_initialize();
        $this->biz_contact_model =D("Common/BizContact");
        $this->biz_member_model =M("Biz_member");
        $this->biz_auth_model =M("BizAuth");
        $this->areas_model =M("Areas");
    }


    
    public function free_shop() {
        if(IS_POST){

        }
        
        $this->display(":free-shop");
    }

    /*商家认证第一步*/
    public function type1() {
        $uid = sp_get_current_userid();
        
        $this->display(":apply-certificate-type1");
    }

    /* 上传凭证*/
    public function type1_post(){
        if($_FILES){

            $upload = new \Think\Upload();// 实例化上传类   
            $upload->maxSize   =  3145728 ;//设置附件上传大小    
            $upload->exts      =  array('jpg', 'gif', 'png', 'jpeg');//设置附件上传类型 

            $rootpath = $upload->rootPath = 'data/';   //文件上传更目录   
            $upload->savePath  =  'upload/certificate/';// 设置附件上传目录    // 上传文件

            $info   =   $upload->upload(); 
            $certificate_url= 'certificate/'.date('Y-m-d',time()).'/'.$info['certificate']['savename'];
            session('type1',$certificate_url);
            if(!$info) {// 上传错误提示错误信息       
                $this->error($upload->getError());  
            }else{// 上传成功
                $this->redirect("ApplyCertificate/type2");      
            }
        }
    }

    /*商家认证第二步*/
    public function type2() {
        
        $provinces = $this->areas_model->where('parentId=0')->select();
        $this->assign('provinces',$provinces);
        
        $this->display(":apply-certificate-type2");
    }

    public function type2_post(){
        if(IS_POST){
            if ($this->biz_contact_model->create($post)){
                $contact_info = $this->biz_contact_model->create($post);
                session('type2',$contact_info);

            if(session('type2')){
                    $this->redirect("ApplyCertificate/type3");
                }else{
                    $this->error($this->biz_contact_model->getError());
                }
            }else{
                $this->error($this->biz_contact_model->getError());
            }
        }
    }

    /*商家认证第三步*/
    public function type3() {
        $provinces = $this->areas_model->where('parentId=0')->select();
        $this->assign('provinces',$provinces);
        
        $this->display(":apply-certificate-type3");
    }

    /*上传店铺logo*/
    public function type3_post(){
        if(IS_POST){
            if($_FILES){
                $upload = new \Think\Upload();// 实例化上传类   
                $upload->maxSize   =  3145728 ;//设置附件上传大小    
                $upload->exts      =  array('jpg', 'gif', 'png', 'jpeg');//设置附件上传类型 

                $rootpath = $upload->rootPath = 'data/';   //文件上传更目录   
                $upload->savePath  =  'upload/shop_logo/';// 设置附件上传目录    // 上传文件
                
                $info   =   $upload->upload(); 

                $logo_url= 'logo/'.date('Y-m-d',time()).'/'.$info['logo']['savename'];
                session('logo_url',$logo_url);
            }else{
                $this->error('图片上传失败');
            }

            if ($this->biz_member_model->create($post)){
                $company_info = $this->biz_member_model->create($post);
                session('company_info',$company_info);
                if(session('?company_info')){
                    $this->redirect('ApplyCertificate/type4');
    
                    }else{
                        $this->error($this->biz_member_model->getError());
                    }
            }else{
                $this->error($this->biz_member_model->getError());
            } 
            
        }
        
    }

    public function type4() {
        $provinces = $this->areas_model->where('parentId=0')->select();
        $this->assign('provinces',$provinces);

        $this->display(":apply-certificate-type4");
    }

    public function type4_post(){

        if(IS_POST){

             $id=session("user.id");
             if(session('?company_info') && session('?logo_url')){
                $arr = array('id'=>$id);
                $logo=array('logo'=>session('logo_url'));
                $type3=array_merge($logo,$arr,session('company_info'));
                $check_member_id = $this->biz_member_model->where("id=$id")->find();
                if($check_member_id){
                    $this->biz_member_model->where("id=$id")->data($type3)->save();
                }else{
                    $this->biz_member_model->data($type3)->add();
                }
             }

             if(session('?type2')){

                $arr = array('id'=>$id);
                $type2 = array_merge($arr,session('type2'));
                $check_contact_id = $this->biz_contact_model->where("id=$id")->find();
                if($check_contact_id){
                    $this->biz_contact_model->where("id=$id")->data($type2)->save();
                }else{
                    $this->biz_contact_model->data($type2)->add();
                }
                
             }

             if(session('?type1') && session('?company_info') && session('?logo_url') && session('?type2')){

                if($this->biz_auth_model->create($post)){
                    $account_info=$this->biz_auth_model->create($post);
                    $arr2 = array('id'=>$id);
                    $certificate_url = session('type1');
                    $account_info['auth_photo_licence']= $certificate_url; 
                    $type4 = array_merge($arr2,$account_info);
                    $check_auth_id = $this->biz_auth_model->where("id=$id")->find();

                    if($check_auth_id){
                        $auth_save=$this->biz_auth_model->where("id=$id")->data($type4)->save();
                        if($auth_save){
                            $this->success('商家入驻信息已提交，待审核！',U('Index/index'));
                            session('company_info',null);
                            session('?logo_url',null);
                            session('type2',null);
                            session('type1',null);
                        }else{
                            $this->error('提交失败',U('ApplyCertificate/type4'));
                            $this->biz_auth_model->where("id=$id")->delete();
                            $this->biz_contact_model->where("id=$id")->delete();
                            $this->biz_member_model->where("id=$id")->delete();
                        }
                       
                    }else{
                        $auth_add=$this->biz_auth_model->data($type4)->add();
                        if($auth_add){
                            $this->success('商家入驻信息已提交，待审核！',U('Index/index'));
                            session('company_info',null);
                            session('?logo_url',null);
                            session('type2',null);
                            session('type1',null);
                        }else{
                            $this->error('提交失败',U('ApplyCertificate/type4'));
                            $this->biz_auth_model->where("id=$id")->delete();
                            $this->biz_contact_model->where("id=$id")->delete();
                            $this->biz_member_model->where("id=$id")->delete();
                        }
                    }
                }
             }else{
                $this->error('提交失败',U('ApplyCertificate/type4'));
             }
        }
    }

}


