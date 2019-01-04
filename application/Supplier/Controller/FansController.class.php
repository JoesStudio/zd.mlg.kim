<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-01-09
 * Time: 15:51
 */

namespace Supplier\Controller;


use Common\Controller\SupplierbaseController;

class FansController extends SupplierbaseController
{
    protected $fans_model;
    protected $is_delete = 0;

    function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->fans_model = D('Supplier/Fans');
    }

    public function index(){
        $where = array();
        $where['member_id'] = $this->memberid;

        $fans = $this->fans_model->getFansPaged($where);

        $this->assign('fans', $fans);
        $this->display();
    }

    public function view($id)
    {
        $where = array(
            'id'    => $id,
            'member_id' => $this->memberid,
        );
        $fans = $this->fans_model->getFans($where);
        $this->assign($fans);
        $this->display();
    }

    public function info_post()
    {
        if (IS_POST) {
            $post = I('post.');
            $id = $post['id'];
            $where = array(
                'id'    => $id,
                'member_id' => $this->memberid,
            );
            if ($this->fans_model->where($where)->count() == 0) {
                $this->error('非法操作：好友资料数据错误，请刷新！');
            }
            $allow_fields = array('id', 'level', 'comment');
            foreach ($post as $key => $value) {
                if (!in_array($key, $allow_fields)) {
                    unset($post[$key]);
                }
            }
            $result = $this->fans_model->saveFans($post);
            if ($result !== false) {
                $this->success('资料已更新！');
            } else {
                $this->error('操作失败！'.$this->fans_model->getError());
            }
        }
    }

    function invite(){
        $this->display();
    }

    public function send_sms($id)
    {
        $fanlist = D('Supplier/Fans')->getFansNoPaged("member_id:".$this->memberid.";");
        $this->assign('fans_list', $fanlist);
        $fans = D('Supplier/Fans')->getFans($id);
        $this->assign($fans);
        $this->display();
    }

    public function orderlist(){
        $where = array();

        $where['supplier_id'] = $this->memberid;
        $where['_string'] = "ISNULL(fans.id)";
        $fans = $this->fans_model->getOrderFans($where);

        $this->assign('fans', $fans);
        $this->display();
    }

    function add_order_fan(){
        if(IS_POST){
            $user_id = I('post.id');
            $count = $this->fans_model
                ->where(array('member_id'=>$this->memberid,'user_id'=>$user_id))
                ->count();
            if($count == 0){
                //查找是否有下单记录
                $data = array(
                    'member_id'     => $this->memberid,
                    'user_id'       => $user_id,
                );
                $fansorder = D('Order/Order')
                    ->field('add_time as order_date,COUNT(order_id) as order_num')
                    ->where(array('user_id'=>$user_id,'supplier_id'=>$this->memberid))
                    ->order('add_time DESC')
                    ->group('user_id')
                    ->find();
                if(!empty($fansorder)){
                    $data['order_num'] = $fansorder['order_num'];
                    $data['order_date'] = date('Y-m-d H:i:s',$fansorder['order_date']);
                }
                $result = $this->fans_model->saveFans($data);
                if($result){
                    $this->ajaxReturn(array(
                        'status'    => 1,
                        'info'      => '已添加好友！',
                        'data'      => array('id'=>$data['user_id']),
                    ));
                }else{
                    $this->error('添加好友失败！');
                }
            }
        }
    }

    public function add(){
        $supplier_id = $this->memberid;

        $fans=$this->fans_model->getOrderFans($supplier_id);
        

        $this->display();
    }

    public function add_post(){
        if(IS_POST){
            $data = I('post.');
            $data['add_time'] = time();
            $data['member_id'] = sp_get_current_userid();
            $result = $this->fans_model->saveFan($data);
            if($result){
                $this->redirect('index');
            }else{
                $this->error($this->fans_model->getError());
            }
        }
        $this->display();
    }

    public function edit(){

        $fans = $this->fans_model->getFan($_GET['id']); 
      
        $this->assign("fans",$fans);

        $this->display();
    }

    public function edit_post(){
        if(IS_POST){
            $data = I('post.');
            $data['add_time'] = time();
            $data['supplier_id'] = sp_get_current_userid();
            $result = $this->fans_model->saveFan($data);
            if($result){
                $this->redirect('index');
            }else{
                $this->error($this->fans_model->getError());
            }
        }
        $this->display();
    }

    function delete(){
        if(IS_POST){
            $id = I('post.id');
            $fan = $this->fans_model->where(array('id'=>$id))->find();
            if($fan){
                $result = $this->fans_model->where(array('id'=>$id))->delete();
                if($result !== false){
                    $this->ajaxReturn(array(
                        'status'    => 1,
                        'info'      => '好友已删除！',
                        'data'      => array('id'=>$id),
                    ));
                }else{
                    $this->error($this->fans_model->getError());
                }
            }else{
                $this->error('错误的操作！');
            }
        }
    }

    public function level_post()
    {
        if (IS_POST) {
            $post = I('post.');
            $fans = $this->fans_model
            ->where(array('id' => $post['id'], 'member_id' => $this->memberid))
            ->find();
            if (empty($fans)) {
                $this->error('非法操作！');
            }
            $result = $this->fans_model->setLevel($post['id'], $post['level']);
            if ($result !== false) {
                $this->success('好友<b>'.$fans['nickname'].'</b>已设为<b>'.$post['level'].'级</b>！');
            } else {
                $this->error($this->fans_model->getError());
            }
        }
    }

    //接受好友链接
    public function accept($code)
    {
        $member_id = $this->memberid;
        $apply_model = D('FansApply');
        $data = $apply_model->where(array('code' => $code, 'to' => $member_id))->find();
        if (!empty($data)) {
            $fans_model = D('Supplier/Fans');
            if ($data['to_type'] == 2 && $data['from_type'] == 1) {
                $uid = $data['from'];

                $fans = $fans_model->where("`user_id`=$uid AND `member_id`=$member_id")->find();
                if (!empty($fans)) {
                    $this->success('你们已经是好友了！');
                } else {
                    $user = D('UserInfo')->where(array('user_id' => $uid))->find();
                    $result = $fans_model->saveFans(array(
                        'nickname'  => $user['nickname'],
                        'user_id'   => $uid,
                        'member_id' => $member_id,
                        ));
                    if ($result !== false) {
                        $apply_model->delete($data['id']);
                        $title = '您的好友申请已经通过！';
                        $content = '您跟'.$this->member['biz_name'].'已经是好友了，现在您可以浏览该面料商更多的面料了。';
                        D('Notify/Msg')->sendMsg($uid, $title, $content, $this->memberid, 2, 1);
                        $this->success('你们已经是好友了！');
                    } else {
                        $this->error('操作失败！');
                    }
                }
            } else {
                $this->error('操作已经失效了！');
            }
        } else {
            $this->error('操作已经失效了！');
        }
    }

    public function apply_user_fans()
    {
        if (IS_POST) {
            $uid = I('post.id');
            if (empty($uid)) {
                $this->error('操作失败！');
            }
            $member_id = $this->memberid;
            $count = D('Supplier/Fans')->where("user_id=$uid AND member_id=$member_id")->count();
            if ($count > 0) {
                $this->success('你们已经是好友了！');
            }

            //查找是否有下单记录
            $fansorder = D('Order/Order')
                ->field('add_time as order_date,COUNT(order_id) as order_num')
                ->where("user_id=$uid AND supplier_id=$member_id")
                ->order('add_time DESC')
                ->group('user_id')
                ->find();
            if (!empty($fansorder)) {
                $data['order_num'] = $fansorder['order_num'];
                $data['order_date'] = date('Y-m-d H:i:s', $fansorder['order_date']);
                $result = $this->fans_model->saveFan($data);
                if ($result) {
                    $this->ajaxReturn(array(
                        'status'    => 1,
                        'info'      => '已添加好友！',
                        'data'      => array(
                            'id'    => $data['user_id'],
                            'type'  => '1',
                        ),
                    ));
                } else {
                    $this->error('添加好友失败！');
                }
            } else {
                $apply_model = D('FansApply');

                $apply = $apply_model->where("`from`=$member_id AND `to`=$uid AND `from_type`=2 AND `to_type`=1")->find();
                if (empty($apply)) {
                    $created_date = date('Y-m-d H:i:s', time());
                    $apply_code = md5($this->member['biz_code']."[$uid]".$created_date);
                    $data = array(
                        'from'      => $member_id,
                        'to'        => $uid,
                        'from_type' => 2,
                        'to_type'   => 1,
                        'created_date'=> $created_date,
                        'code'      => $apply_code,
                    );
                } else {
                    $apply_code = $apply['code'];
                    $data = array(
                        'id'        => $apply['id'],
                        'isread'    => 0,
                        'modified_date'=> date('Y-m-d H:i:s', time()),
                    );
                }
                $result = $apply_model->saveApply($data);
                if ($result !== false) {
                    //发送好友申请
                    //send_invite_code();
                    $title = $this->member['biz_name'].'希望加你为好友！';
                    $href = leuu('user/fans/accept', array('code' => $apply_code));
                    $content = '是否要接受'.$this->member['biz_name'].'的好友请求？'
                        .'<a href="'.$href.'" class="btn-u btn-u-xs">接受</a>';
                    D('Notify/Msg')->sendMsg($uid, $title, $content, $this->memberid, 2, 1);
                    $this->ajaxReturn(array(
                        'status'    => 1,
                        'info'      => '已发送好友申请！',
                        'data'      => array(
                            'id'    => $uid,
                            'type'  => '2',
                        ),
                    ));
                } else {
                    $this->error('申请失败！');
                }
            }
        }
    }
}