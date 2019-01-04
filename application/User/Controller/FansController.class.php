<?php
namespace User\Controller;

use Common\Controller\MemberbaseController;

class FansController extends MemberbaseController
{
    protected $_model;

    public function _initialize()
    {
        parent::_initialize();
        $this->_model = D("FansApply");
    }

    public function index()
    {
        $this->display();
    }

    public function accept($code)
    {
        $data = $this->_model->where(array('code' => $code, 'to' => $this->userid))->find();
        if (!empty($data)) {
            $fans_model = D('Supplier/Fans');
            if ($data['to_type'] == 1 && $data['from_type'] == 2) {
                $uid = $data['to'];
                $member_id = $data['from'];

                $fans = $fans_model->where("`user_id`=$uid AND `member_id`=$member_id")->find();
                if (!empty($fans)) {
                    $this->success('你们已经是好友了！');
                } else {
                    $result = $fans_model->saveFans(array(
                        'nickname'  => $this->user['userinfo']['nickname'],
                        'user_id'   => $uid,
                        'member_id' => $member_id,
                        ));
                    if ($result !== false) {
                        $this->_model->delete($data['id']);
                        $title = '您的好友申请已经通过！';
                        $content = '您跟'.$this->user['userinfo']['nickname'].'已经是好友了，您现在可以给TA发送产品消息了。';
                        D('Notify/Msg')->sendMsg($member_id, $title, $content, $uid, 1, 2);
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

    public function apply_supplier_fans()
    {
        if (IS_POST) {
            $member_id = I('post.id');
            if (empty($member_id)) {
                $this->error('操作失败！');
            }
            $uid = $this->userid;
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
                $data = array(
                    'member_id'     => $member_id,
                    'user_id'       => $this->userid,
                    'nickname'      => $this->user['nickname'],
                    'order_num'     => $fansorder['order_num'],
                    'order_date'    => date('Y-m-d H:i:s', $fansorder['order_date'])
                );
                $result = D('Supplier/Fans')->saveFans($data);
                if ($result) {
                    $this->ajaxReturn(array(
                        'status'    => 1,
                        'info'      => '已添加好友！',
                        'data'      => array(
                            'id'    => $data['user_id'],
                            'type'  => '1',
                        ),
                        'is_fans'   => 1,
                    ));
                } else {
                    $this->error('添加好友失败！');
                }
            } else {
                $apply_model = D('FansApply');

                $apply = $apply_model->where("`from`=$uid AND `to`=$member_id AND `from_type`=1 AND `to_type`=2")->find();
                if (empty($apply)) {
                    $created_date = date('Y-m-d H:i:s', time());
                    $apply_code = md5($this->user['code']."[$member_id]".$created_date);
                    $data = array(
                        'from'      => $uid,
                        'to'        => $member_id,
                        'from_type' => 1,
                        'to_type'   => 2,
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
                    $title = $this->user['userinfo']['nickname'].'希望加你为好友！';
                    $href = leuu('supplier/fans/accept', array('code' => $apply_code));
                    $content = '是否要接受'.$this->user['userinfo']['nickname'].'的好友请求？'
                        .'<a href="'.$href.'" class="btn-u btn-u-xs">接受</a>';
                    D('Notify/Msg')->sendMsg($member_id, $title, $content, $uid, 1, 2);
                    $this->ajaxReturn(array(
                        'status'    => 1,
                        'info'      => '已发送好友申请！',
                        'data'      => array(
                            'id'    => $uid,
                            'type'  => '2',
                        ),
                        'is_fans'   => 0,
                    ));
                } else {
                    $this->error('申请失败！');
                }
            }
        }
    }
}
