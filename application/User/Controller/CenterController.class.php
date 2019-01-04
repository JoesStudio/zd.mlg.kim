<?php

/**
 * 会员中心
 */
namespace User\Controller;
use Common\Controller\MemberbaseController;
use Wx\Common\Wechat;
class CenterController extends MemberbaseController {

    protected $info_model;
	
	function _initialize(){
		parent::_initialize();
        $this->info_model = D('UserInfo');
	}
    //会员中心
	public function index() {
		$this->assign($this->user);

        $uid = $this->userid;
        $tf_ids = D('History')->getGoodsIds("user_id:$uid;type:1;limit:30;group:goods_id;");
        $supplier_ids = D('History')->getGoodsIds("user_id:$uid;type:3;limit:20;group:goods_id;");
        $card_ids = D('History')->getGoodsIds("user_id:$uid;type:2;limit:20;group:goods_id;");
        if(!empty($tf_ids)){
            $tf_ids = implode(',',$tf_ids);
            $data['tf'] = D('Tf/Tf')->getTfNoPaged("_string:tf.id IN($tf_ids);order:FIELD(tf.id,$tf_ids) ASC;");
        }

        if(!empty($supplier_ids)){

            $supplier_ids = implode(',',$supplier_ids);
            $data['supplier'] = D('Common/BizMember')->getMembersNoPaged("_string:id IN($supplier_ids);order:FIELD(id,$supplier_ids);");
        }

        if(!empty($card_ids)){
            $card_ids = implode(',',$card_ids);
            $where = array();
            $where['card_id'] = array('in',$card_ids);
            $where['card_type'] = 1;
            $where['card_status'] = 20;
            $where['card_trash'] = 0;
            $data['card'] = D('Colorcard/Colorcard')->getCardsNoPaged($where);
        }

        $this->assign('history',$data);
    	$this->display();
    }

    //手机版的浏览过的面料记录
    function history(){
        if(sp_is_mobile() && !IS_AJAX){
            $this->display();
            exit();
        }
        
        $this->assign($this->user);
        $uid = $this->userid;
        $tf_ids = D('History')->getGoodsIds("user_id:$uid;type:1;limit:20;group:goods_id;");
         
        if(!empty($tf_ids)){
            $tf_ids = implode(',',$tf_ids);
            $data['tf'] = D('Tf/Tf')->getTfNoPaged("_string:tf.id IN($tf_ids);limit:30;order:FIELD(tf.id,$tf_ids);");
        }
        $this->assign('history',$data);
        
        if(sp_is_mobile() && IS_AJAX){
            $html = $this->fetch(":Center/history-more");
            $data['status'] = 1;
            $data['html'] = $html;
            $this->ajaxReturn($data);
        }
        
    	$this->display();
    }

    function delete_history(){
        $type = I('get.type');
        $id = I('get.id');
        $model = D('History');
        $result = $model->deleteRecByGoods($id,$type,$this->userid);
        if($result){
            $this->success(array('type'=>$type,'id'=>$id));
        }else{
            $this->error('删除失败！'.$model->getError());
        }
    }

    function delete_history2(){
        $type = I('get.type');
        $id = I('get.id');
        $history = cookie('history');
        $history[$type] = array_diff(explode(',', $history[$type]),array($id));
        cookie("history[$type]", implode(',', $history[$type]), 3600 * 24 * 30);
        $result = !in_array($id,$history[$type]);
        if($result){
            $this->success(array('type'=>$type,'id'=>$id));
        }else{
            $this->error('删除失败！');
        }
    }

    

    function profile(){
        if(!empty($this->user['openid']) && !sp_is_mobile()){
            $wechat = new Wechat(get_wx_configs());
            $info = $wechat->api->get_user_info($this->user['openid']);
            $this->assign('wx_info', (array)$info[1]);
        }
        $this->assign('user', $this->user);
        $this->assign('areas',D('Areas')->getAreasByDistrict($this->user['userinfo']['district']));
        $this->display();
    }

    function info_post(){
        if(IS_POST){
            $data = I('post.userinfo');
            $data['id'] = $this->user['userinfo']['id'];
            if(empty($data['id'])){
                $this->error('保存失败，请重新登录后再试！');
            }
            $result = $this->info_model->saveInfo($data);
            if($result !== false){
                $this->refresh_session();
                $this->success('资料已更新！');
            }else{
                $this->error($this->info_model->getError());
            }
        }
    }

    function wx_unbind(){
        if(IS_POST){
            $result = $this->users_model->where(array('id'=>$this->userid))->setField('openid','');
            if($result !== false){
                $user = $this->users_model->getUser($this->userid);
                session('user', $user);
                $this->success('解绑成功！',leuu('profile'));
            }else{
                $this->error('解绑失败！');
            }
        }
    }

    function wx_bind_qr(){
        if(IS_POST){
            $count = $this->users_model->where(array('id'=>$this->userid,'openid'=>''))->count();
            if($count > 0){
                $wx_model = new Wechat(get_wx_configs());
                list($err, $data) = $wx_model->createQr(60, 2, $this->userid);
                if($data){
                    $this->success($data);
                }else{
                    $this->error($err);
                }
            }
        }
    }

    function bind_mobile(){
        if(IS_POST){
            $post = I('post.');

            $verify = $post['verify'];
            if(empty($verify)){
                $this->error('请输入验证码');
            }else{
                list($result, $error_code, $sms_id) = D('Sms/Sms')->checkCode($post['mobile'], $verify);
                if($result !== true){
                    $this->error('验证码错误');
                }
            }

            $data['id'] = $this->user['userinfo']['id'];
            $data['mobile'] = $post['mobile'];
            $result = $this->info_model->saveInfo($data);
            if($result !== false){
                D('Sms/Sms')->setUsed($sms_id);
                $this->refresh_session();
                $this->success('已绑定手机！');
            }else{
                $this->error($this->info_model->getError());
            }
        }else{
            $this->assign('user',$this->user);
            $this->display();
        }
    }

    function verify_mobile(){
        if(IS_POST){
            $verify = I('post.verify');
            $act = I('post.act');
            $mobile = $this->user['userinfo']['mobile'];
            if(empty($verify)){
                $this->error('请输入验证码');
            }else{
                list($result, $error_code, $sms_id) = D('Sms/Sms')->checkCode($mobile, $verify);
                if($result !== true){
                    $this->error('验证码错误');
                }else{
                    switch($act){
                        case 'change_mobile':
                            $key = md5(uniqid());
                            session('change_mobile_token',$key);
                            D('Sms/Sms')->setUsed($sms_id);
                            $this->ajaxReturn(array('status'=>1,'url'=>leuu('change_mobile',array('key'=>$key))));
                            break;
                        default:
                    }
                }
            }
        }else{
            $this->assign('user',$this->user);
            $this->display();
        }
    }

    function change_mobile(){
        $key = I('get.key');
        if($key == session('change_mobile_token')){
            session('change_mobile_token',null);
            $this->assign('user',$this->user);
            $this->display();
        }else{
            $this->redirect('profile');
        }
    }

    function change_mobile_post(){
        if(IS_POST){
            $verify = I('post.verify');
            $mobile = I('post.mobile');
            if(empty($mobile)){
                $this->error('请输入新手机号码');
            }
            if(empty($verify)){
                $this->error('请输入验证码');
            }


            list($result, $error_code, $sms_id) = D('Sms/Sms')->checkCode($mobile, $verify);
            if($result !== true){
                $this->error('验证码错误');
            }else{
                $data = array(
                    'id'    => $this->user['userinfo']['id'],
                    'mobile'=> $mobile,
                );
                $result = $this->info_model->saveInfo($data);
                if($result !== false){
                    $this->refresh_session();
                    D('Sms/Sms')->setUsed($sms_id);
                    $this->success('手机已更改',leuu('change_mobile_success'));
                }else{
                    $this->error('手机更改失败！'.$this->info_model->getError());
                }
            }
        }
    }

    function change_mobile_success(){
        
        $this->display();
    }
}
