<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-02-28
 * Time: 9:56
 */

namespace Order\Controller;


use Common\Controller\AdminbaseController;

class AdminGroupController extends AdminbaseController 
{
    protected $_model;
    protected $order_model;
    public function _initialize()
    {
        parent::_initialize();
        $this->_model = D('Order/Group');
        $this->order_model = D('Order/Order', 'Logic');
        $this->assign('statuses', $this->_model->statuses);
        $this->assign('model_actions', $this->_model->actions);
    }

    public function index()
    {
        $groups = $this->_model->getGroupsPaged();
        $this->assign('list', $groups);
        $this->display();
    }

    public function group($id)
    {
        $where['id'] = $id;
        $group = $this->_model->getGroup($where);
        if (empty($group)) {
            $this->error(L('ERROR_REQUEST_DATA'));
        }

        foreach ($group['orders'] as $key => $order) {
            $order['delivery'] = D('Order/Delivery')->getDeliveryByOrder($order['order_id']);
        }

        $this->assign($group);

        $logs = D('Order/GroupLog')->getLogsPaged("order_id:$id");
        $this->assign('logs', $logs);

        $this->display();
    }

    public function refresh_status($id)
    {
        $this->_model->check_prepare($id);
    }

    function operate_post(){
        if(IS_POST){
            $post = I('post.');
            if(empty($post['group_id'])){
                $this->error('非法操作！');
            }

            $group_id = $post['group_id'];
            switch ($post['act']) {
                case 'grouped':
                    $this->_grouped($group_id);
                    break;
                case 'prepare':
                    $this->_prepare($group_id);
                    break;
                case 'ship':
                    $this->success('',leuu('AdminDelivery/groupadd',array('group_id'=>$group_id,'action_note'=>$post['action_note'])));
                    break;
                case 'unship':
                    if(empty($post['action_note'])){
                        $this->error('请填写取消发货原因！');
                    }
                    $this->_unship($group_id);
                    break;
                case 'to_delivery':
                    $this->success('',U('AdminDelivery/group',array('group_id'=>$group_id,
                        'action_note'=>$post['action_note'])));
                    break;
                case 'cancel':
                    if(empty($post['action_note'])){
                        $this->error('请填写取消拼单原因！');
                    }
                    $this->_cancel($group_id);
                    break;
                default:
            }
        }else{
            $this->assign('group_id',I('get.group_id'));
            $this->assign('action_note',I('get.action_note'));
            $this->display(I('get.act'));
        }
    }

    private function _grouped($group_id)
    {
        $group = $this->_model->getGroup(array('id'=>$group_id));
        if (empty($group)) {
            $this->error(L('ERROR_REQUEST_DATA'));
        }
        $note = I('post.action_note');
        $result = $this->_model->where("id=$group_id")->setField('group_status', 1);
        if ($result !== false) {
            $this->_logAction($group_id, 'GROUPED', $note);
            $this->success('操作成功！', U('group',array('id'=>$group_id)));
        } else {
            $this->error('操作失败！');
        }
    }

    private function _prepare($group_id)
    {
        $group = $this->_model->getGroup(array('id'=>$group_id));
        if (empty($group)) {
            $this->error(L('ERROR_REQUEST_DATA'));
        }
        $note = I('post.action_note');

        foreach ($group['orders'] as $order) {
            $order_id = $order['order_id'];
            if ($order['shipping_status'] == 0) {
                $result = $this->order_model->setPrepare($order_id);
                if($result !== false){
                    $this->_logOrderAction($order_id, $note);
                }
            }
        }
        $this->_logAction($group_id, 'PREPARE', $note);
        $this->success('操作成功！', leuu('group',array('id'=>$group_id)));
    }

    private function _logOrderAction($order_id, $note)
    {
        D('Order/Log')->logAction($order_id, sp_get_current_admin_id(), $note, 0);
    }

    private function _logAction($group_id, $action, $note)
    {
        D('Order/GroupLog')->logAction($group_id, sp_get_current_admin_id(), $action, $note);
    }
}