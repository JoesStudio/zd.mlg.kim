<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-01-21
 * Time: 17:43
 */

namespace Portal\Controller;


use Common\Controller\HomebaseController;

class InviteController extends HomebaseController
{
    function invitefans($code){
        $invite_member = D('BizMember')->where("biz_code='$code'")->find();
        if(!empty($invite_member) && !empty($invite_member['biz_code'])){
            session('invite_code',$code);
            $result = be_fans_by_code();
            if ($result !== false) {
                $this->success('您已经成为'.$result['biz_name'].'的好友了！', leuu('User/Center/index'));
            }
        }
        $this->redirect('Portal/Index/index');
    }



}