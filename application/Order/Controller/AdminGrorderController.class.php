<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-16
 * Time: 10:24
 */

namespace Order\Controller;


use Common\Controller\AdminbaseController;

class AdminGrorderController extends AdminbaseController
{
    protected $_model;
    function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->_model = D('Order', 'Logic');
        $this->assign('order_statuses', $this->_model->orderStatuses);
        $this->assign('pay_statuses', $this->_model->payStatuses);
        $this->assign('shipping_statuses', $this->_model->shippingStatuses);
    }

    function view(){
        $id = I('get.id');
        $where['order_id'] = $id;
        $order = $this->_model->getOrder($where);
        $order_id = $order['order_id'];
        $tf_ids = array_keys($order['goods']);
        $rs = D('Tf/Tf')->getTfNoPaged(array('id'=>array('IN',$tf_ids)));
        foreach($rs as $tf){
            $order['goods'][$tf['id']]['tf'] = $tf;
        }
        $this->assign($order);
        $logs = D('Log')->getLogsPaged("order_id:$id");
        $this->assign('logs', $logs);

        $allow_act = $this->_model->allow_act;
        $acts_tmp = array_intersect($allow_act['order'][$order['order_status']],
            $allow_act['pay'][$order['pay_status']],
            $allow_act['ship'][$order['shipping_status']]);
        $actions = array();
        foreach($acts_tmp as $v){
            if(isset($this->_model->operation[$v])){
                $actions[$v] = $this->_model->operation[$v];
            }
        }
        $this->assign('actions',$actions);

        $group_id = M('OrderGroupRls')->where("order_id=$order_id")->getField('group_id');
        $this->assign('group_id', $group_id);

        $this->display();
    }

    function operate(){
        if(IS_POST){
            $post = I('post.');
            if(empty($post['order_id'])){
                $this->error('非法操作！');
            }

            $order_id = $post['order_id'];

            if(isset($post['confirm']) && $post['confirm'] == '确认'){
                $_POST['act'] = 'operate_post';
                $_POST['operation'] = 'confirm';
                $this->operate_post();
            }
            if(isset($post['pay']) && $post['pay'] == '付款'){
                if(empty($post['action_note'])){
                    $where['order_id'] = $order_id;
                    $where['pay_status'] = array('lt',2);
                    $count = $this->_model->where($where)->count();
                    if($count > 0){
                        $this->success('',U('operate',array('act'=>'pay','order_id'=>$order_id,
                            'action_note'=>$post['action_note'])));
                    }else{
                        $this->success('',U('view',array('id'=>$order_id)));
                    }
                }else{
                    $_POST['act'] = 'operate_post';
                    $_POST['operation'] = 'pay';
                    $this->operate_post();
                }
            }
            if(isset($post['unpay']) && $post['unpay'] == '设为未付款'){
                $where['order_id'] = $order_id;
                $where['pay_status'] = 2;
                $count = $this->_model->where($where)->count();
                if($count > 0){
                    $this->success('',leuu('operate',array('act'=>'unpay','order_id'=>$order_id,
                        'action_note'=>$post['action_note'])));
                }else{
                    $this->success('',leuu('view',array('id'=>$order_id)));
                }
            }
            if(isset($post['prepare']) && $post['prepare'] == '配货'){
                $_POST['act'] = 'operate_post';
                $_POST['operation'] = 'prepare';
                $this->operate_post();
            }
            if(isset($post['ship']) && $post['ship'] == '生成发货单'){
                $this->success('',leuu('AdminDelivery/add',array('order_id'=>$order_id,'action_note'=>$post['action_note'])));
            }
            if(isset($post['unship']) && $post['unship'] == '未发货'){
                if(empty($post['action_note'])){
                    $this->error('请填写取消发货原因！');
                }else{
                    $_POST['act'] = 'operate_post';
                    $_POST['operation'] = 'unship';
                    $this->operate_post();
                }
            }
            if(isset($post['to_delivery']) && $post['to_delivery'] == '去发货'){
                $this->success('',leuu('AdminDelivery/view',array('order_id'=>$order_id,
                    'action_note'=>$post['action_note'])));
            }
            if(isset($post['receive']) && $post['receive'] == '已收货'){
                if(empty($post['action_note'])){
                    $this->error('请填写手动收货的原因！');
                }else{
                    $_POST['act'] = 'operate_post';
                    $_POST['operation'] = 'receive';
                    $this->operate_post();
                }
            }
            if(isset($post['cancel']) && $post['cancel'] == '取消'){
                if(empty($post['action_note'])){
                    $this->error('请填写取消订单原因！');
                }else{
                    $_POST['act'] = 'operate_post';
                    $_POST['operation'] = 'cancel';
                    $this->operate_post();
                }
            }
            if(isset($post['invalid']) && $post['invalid'] == '无效'){
                $_POST['operation'] = 'invalid';
                $this->operate_post();
            }
        }else{
            $this->assign('order_id',I('get.order_id'));
            $this->assign('action_note',I('get.action_note'));
            $this->display(I('get.act'));
        }
    }

    function operate_post(){
        if(IS_POST){
            $post = I('post.');

            $order_id = $post['order_id'];

            $count = $this->_model->where(array('order_id'=>$order_id))->count();
            if(!$count){
                $this->error('非法操作！');
            }

            $data['order_id'] = $post['order_id'];

            switch($post['operation']){
                case 'confirm':
                    $result = $this->_model->setConfirm($order_id);
                    break;
                case 'pay':
                    $result = $this->_model->setPaid($order_id);
                    break;
                case 'unpay':
                    $result = $this->_model->setUnpaid($order_id);
                    break;
                case 'prepare':
                    $result = $this->_model->setPrepare($order_id);
                    break;
                case 'unship':
                    $result = $this->_model->unship($order_id);
                    if($result !== false){
                        $delivery_model = D('Delivery');
                        $delivery_id = $delivery_model->where(array('order_id'=>$order_id))->getField('delivery_id');
                        $result = $delivery_model->unship($delivery_id);
                        if($result === false){
                            $this->_model->setError($delivery_model->getError());
                        }
                    }
                    break;
                case 'receive':
                    $result = $this->_model->setReceived($order_id);
                    break;
                case 'cancel':
                    $result = $this->_model->cancel($order_id);
                    break;
                case 'invalid':
                    $result = false;
                    break;
                default:
                    $result = false;
            }

            if($result !== false){
                $this->logAction($order_id, $post['action_note']);
                $this->success('操作成功！', leuu('view',array('id'=>$order_id)));
            }else{
                $this->error('操作失败！'.$this->_model->getError());
            }
        }
    }

    function logAction($order_id, $action_note){
        D('Log')->logAction($order_id, sp_get_current_userid(), $action_note, 0);
    }

}