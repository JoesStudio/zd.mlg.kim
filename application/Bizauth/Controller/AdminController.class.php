<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-01-11
 * Time: 15:54
 */

namespace Bizauth\Controller;

use Common\Controller\AdminbaseController;

class AdminController extends AdminbaseController
{
    protected $_model;
    protected $bm_model;

    public function _initialize()
    {
        parent::_initialize();
        $this->_model = D("BizAuth");
        $this->bm_model = D("BizMember");
    }

    public function index()
    {
        $tag = '';
        if (isset($_GET['status'])) {
            $tag['auth_status'] = I('get.status/d');
        }

        if (isset($_REQUEST['status'])) {
            $_REQUEST['filter']['status'] = $_REQUEST['status'];
        }
        
        if (isset($_REQUEST['filter'])) {

            $filter = I('request.filter');

            if (isset($filter['type']) && !empty($filter['type'])) {
                $tag['auth_type'] = $filter['type'];
            }

            if (!empty($filter['datestart']) && !empty($filter['datefinish'])) {
                $tag['auth_apply_time'] = array('between', $filter['datestart'].','.$filter['datefinish']);
            } elseif (!empty($filter['datestart']) && empty($filter['datefinish'])) {
                $tag['auth_apply_time'] = array('egt', $filter['datestart']);
            } elseif (empty($filter['datestart']) && !empty($filter['datefinish'])) {
                $tag['auth_apply_time'] = array('elt', $filter['datefinish']);
            }

            $this->assign('filter', $filter);
        }

        $list = $this->_model->getAuthsPaged($tag);
        $this->assign('list', $list);
        $this->display();
    }

    public function view()
    {
        $id = I('get.id/d');
        $auth = $this->_model->getAuth($id);
        $this->assign('auth', $auth);


        $logs = D('Log')->getLogsNoPaged("auth_id:$id;");
        $this->assign('logs', $logs);
        $this->display();
    }

    public function operate_post()
    {
        if(IS_POST){
            $post = I('post.');
            $id = $post['id'];
            $note = $post['action_note'];
            $act = $post['act'];

            if(empty($id)){
                $this->error('非法操作！');
            }

            switch($act){
                case 'pass':
                    $this->pass($id, $note);
                    break;
                case 'nopass':
                    $this->nopass($id, $note);
                    break;
                case 'revoke':
                    $this->revoke($id, $note);
                    break;
                default:
                    $this->error('非法操作！');
            }
        }
    }

    private function revoke($id, $note)
    {
        $auth = $this->_model->getAuth($id);
        if (empty($note)) {
            $this->error('请注明撤销认证原因！');
        }

        $auth = $this->_model->getAuth($id);
        $status = 0;
        $type = 0;

        $member['id'] = $auth['member_id'];
        $member['authenticated'] = $status;
        $member['biz_type'] = $type;
        $member['biz_status'] = 0;
        $result = $this->bm_model->saveMember($member);

        //找到创始人的user_id
        $ops = D('BizOperator')
        ->field('op.*')
        ->alias('op')
        ->join('__BIZ_ROLE_OP__ rop ON rop.op_id=op.id')
        ->join('__BIZ_ROLE__ role ON role.id=rop.role_id')
        ->where(array('op.member_id'=>$auth['member_id'],'rop.role_id'=>1))
        ->select();
        $op = reset($ops);

        $data['id'] = $id;
        $data['auth_status'] = $status;
        $data['auth_type'] = $type;
        $result2 = $this->_model->saveAuth($data);

        if ($result !== false) {
            D('Log')->logAction($id, sp_get_current_admin_id(), $note);

            $title = '您的认证信息审核不通过！';
            $content = '撤销原因：'.$note;
            D('Notify/Msg')->sendMsg($op['user_id'],$title,$content,0,1);
            $this->success('操作成功！', U('view', array('id' => $id)));
        } else {
            $this->error($this->_model->getError());
        }
    }

    private function nopass($id, $note)
    {
        $auth = $this->_model->getAuth($id);
        if(empty($note)){
            $this->error('请注明驳回原因！');
        }

        //找到创始人的user_id
        $ops = D('BizOperator')
        ->field('op.*')
        ->alias('op')
        ->join('__BIZ_ROLE_OP__ rop ON rop.op_id=op.id')
        ->join('__BIZ_ROLE__ role ON role.id=rop.role_id')
        ->where(array('op.member_id'=>$auth['member_id'],'rop.role_id'=>1))
        ->select();
        $op = reset($ops);

        $data['id'] = $id;
        $data['auth_status'] = 3;
        $result = $this->_model->saveAuth($data);

        

        if($result !== false){
            D('Log')->logAction($id,sp_get_current_admin_id(),$note);

            $title = '您的认证信息审核不通过！';
            $content = '驳回原因：'.$note;
            D('Notify/Msg')->sendMsg($op['user_id'],$title,$content,0,1);
            $this->success('操作成功！',U('view',array('id'=>$id)));
        }else{
            $this->error($this->_model->getError());
        }
    }

    private function pass($id, $note)
    {
        $auth = $this->_model->getAuth($id);
        $status = 1;

        $data['id'] = $auth['member_id'];
        $data['authenticated'] = $status;
        $data['biz_type'] = $auth['auth_type'];
        $data['biz_status'] = 1;
        $data['biz_name'] = $auth['auth_shop_name'];
        $data['auth']['id'] = $id;
        $data['auth']['auth_status'] = $status;

        //找到创始人的user_id,目的为了给创始人按绑定的手机好创建账号和密码
        $ops = D('BizOperator')
        ->field('op.*')
        ->alias('op')
        ->join('__BIZ_ROLE_OP__ rop ON rop.op_id=op.id')
        ->join('__BIZ_ROLE__ role ON role.id=rop.role_id')
        ->where(array('op.member_id'=>$auth['member_id'],'rop.role_id'=>1))
        ->select();
        $op = reset($ops);
        $user_info = D('UserInfo')->getInfo($op['user_id']);
        $fater_six = substr($user_info['mobile'],-6);//手机后六位数字
        $info = array(
                'id'=>$user_info['user_id'],
                'user_mobile'=>$user_info['mobile'],
                'user_pass'=>sp_password($fater_six),
                );
        $result3 = D('Users')->saveUser($info);

        if($result3 === false){
            $this->error('创建会员账号和密码失败！',D('Users')->getError());
        }

        $result = $this->bm_model->saveMember($data);
        $result2 = $this->_model->saveAuth($data['auth']);
        if($result !== false){
            D('Log')->logAction($id,sp_get_current_admin_id(),$note);

            $title = '您的认证信息已经审核通过！';
            $content = '您的认证信息已经通过，您现在可以使用商家功能了。会员登陆账号：'.$user_info['mobile'].'密码：'.$fater_six;
            
            D('Notify/Msg')->sendMsg($user_info['user_id'],$title,$content,0,1);
            $this->success('操作成功！',U('view',array('id'=>$id)));
        }else{
            $this->error($this->bm_model->getError());
        }
    }

}