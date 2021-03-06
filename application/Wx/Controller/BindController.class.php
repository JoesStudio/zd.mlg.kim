<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-12-29
 * Time: 15:20
 */

namespace Wx\Controller;


use Common\Controller\HomebaseController;
use Common\Controller\MemberbaseController;
use Wx\Common\Wechat;

class BindController extends MemberbaseController
{
    protected $_model;

    function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->_model = D('Users');
    }

    function unbind(){
        if(IS_POST){
            $result = $this->_model->where(array('id'=>$this->userid))->setField('openid','');
            if($result !== false){
                $user = $this->_model->getUser($this->userid);
                session('user', $user);
                $this->success('解绑成功！',leuu('user/center/profile'));
            }else{
                $this->error('解绑失败！');
            }
        }
    }

    function qr(){
        if(IS_POST){
            if(empty($this->user['openid'])){
                $wx_model = new Wechat();
                list($err, $data) = $wx_model->createQr(60, 2, $this->userid);
                if($data){
                    $this->success($data);
                }else{
                    $this->error($err);
                }
            }
        }
    }

    function check_bind(){
        $qr_model = M('WxQrcode');
        $where = array(
            'sceneid'   => I('get.sceneid'),
            'ticket'    => I('get.ticket'),
            'status'    => 0,
            'type'      => 2,
            'user_id'   => $this->userid,
        );
        $qr = $qr_model->where($where)->find();
        $exist_id = $this->_model->where(array('openid'=>$qr['openid']))->getField('id');
        if($exist_id > 0 && $exist_id == $this->userid){
            $this->success('该微信号已经绑定这个账号了！');
        }elseif($exist_id > 0){
            $this->error('该微信号已经绑定其他账号了！');
        }else{
            $this->_model->where(array('id'=>$this->userid))->setField('openid',$qr['openid']);
        }

        $user = $this->_model->find($this->userid);
        if(!empty($user['openid'])){
            session('user', $user);

            $wx_model = new Wechat();
            $info = $wx_model->api->get_user_info($user['openid']);

            $data = array(
                'src'       => (string)$info[1]->headimgurl,
                'nickname'  => (string)$info[1]->nickname,
            );

            $this->success($data);
        }else{
            $this->error('');
        }
    }

}