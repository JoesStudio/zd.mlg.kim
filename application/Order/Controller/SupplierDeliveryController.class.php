<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-12-10
 * Time: 17:31
 */

namespace Order\Controller;


use Common\Controller\SupplierbaseController;
use Order\Service\DealTfOrderService;

class SupplierDeliveryController extends SupplierbaseController
{
    protected $_model;
    protected $orders_model;
    protected $orderService;

    function __construct()
    {
        parent::__construct(); // TODO: Change the autogenerated stub
        $this->_model = D('Order/Delivery');
        $this->orders_model = D('Order/Order', 'Logic');
        $this->orderService = new DealTfOrderService();
        $this->assign('statuses', $this->_model->statuses);
        $this->assign('order_statuses', $this->orders_model->orderStatuses);
        $this->assign('pay_statuses', $this->orders_model->payStatuses);
        $this->assign('shipping_statuses', $this->orders_model->shippingStatuses);
    }

    function index(){
        $where = '';
        if (isset($_REQUEST['filter'])) {

            $filter = I('request.filter');

            if (isset($filter['type'])){
                if($filter['type']==-1){
                    $where['status'] = array('IN','0,1');
                }else{
                $where['status'] = $filter['type'];
                }
            }

            if (isset($filter['order_sn']) && !empty($filter['order_sn'])) {
                $where['delivery.order_sn'] = $filter['order_sn'];
            }

            $this->assign('filter', $filter);
            
        }

        $where['delivery.supplier_id'] = $this->memberid;
        $deliveries = $this->_model->getDeliveriesPaged($where);
        $this->assign('deliveries', $deliveries);
        $this->display();
    }

    function add(){
        $order_id = I('get.order_id/d',0);
        $action_note = I('get.action_note');
        $order = $this->orders_model->getOrder($order_id);
        if(empty($order)){
            $this->redirect('index');
        }
        if(!in_array($order['shipping_status'], array(0,3))){
            $this->error('当前订单状态不允许此操作！');
        }

        if($order['order_type'] == 1){
            $orderTf = M('OrderTf')->where("order_id='{$order_id}'")->find();
            $supplier_deal_source = $orderTf['issource'] == 1 && $order['supplier_id'] == $this->memberid;
            $supplier_deal_dist = $orderTf['issource'] == 0 && $order['source_supplier_id'] == $this->memberid && $orderTf['handover'] == 1;
            if(!($supplier_deal_source || $supplier_deal_dist)){
                $this->error('您没有权限操作该订单！');
            }
        }else{
            if($order['supplier_id'] != $this->memberid){
                $this->error('您没有权限操作该发货单！');
            }
        }
        if($this->_model->where("order_id='{$order_id}'")->count() > 0){
            $this->redirect('view',array('order_id'=>$order_id));
        }

        $this->assign($order);
        //$this->assign('member', $member);
        $this->assign('order_id',$order_id);
        $this->assign('action_note',$action_note);
        $this->display('add');
    }

    function add_post(){
        if(IS_POST){
            $post = I('post.');
            $order_id = $post['order_id'];
            $action_note = $post['action_note'];
            $data = $this->_model->build_delivery_data($order_id,'',sp_get_current_userid());
            if(!empty($data)){
                $result = $this->_model->buildDelivery($data);
                if($result !== false){
                    $this->orders_model->readyForShipment($order_id);
                    $this->logAction($order_id, $action_note);
                    $this->success('已生成发货单！',leuu('Supplier/view',array('id'=>$order_id)));
                }else{
                    $this->error($this->_model->getError());
                }
            }else{
                $this->error('订单数据错误！');
            }
        }
    }

    function groupadd(){
        $group_id = I('get.group_id');
        $action_note = I('get.action_note');
        $group = D('Order/Group')->getGroup($group_id);
        if (empty($group)) {
            $this->error(L('ERROR_REQUEST_DATA'));
        }

        if ($group['actions']['ship'] == 0) {
            $this->error('当前操作不被允许！');
        }

        foreach($group['orders'] as $key => $order) {
            $group['orders'][$key]['delivery'] = $this->_model->where("order_id=".$order['order_id'])->find();
        }
        $this->assign($group);
        $this->assign('member', $this->member);
        $this->assign('group_id',$group_id);
        $this->assign('action_note',$action_note);
        $this->display();
    }

    public function groupadd_post()
    {
        if (IS_POST) {
            $post = I('post.');
            $group_id = $post['group_id'];
            $order_ids = $post['order_id'];
            $action_note = $post['action_note'];
            $error = array();
            foreach ($order_ids as $order_id) {
                $data = $this->_model->build_delivery_data($order_id,'',sp_get_current_userid());
                if(!empty($data)){
                    $result = $this->_model->buildDelivery($data);
                    if($result !== false){
                        $this->orders_model->readyForShipment($order_id);
                        $this->logAction($order_id, $action_note);
                    }else{
                        $error[$order_id] = $this->_model->getError();
                    }
                }
            }
            D('Order/GroupLog')->logAction($group_id, sp_get_current_userid(), 'SHIP', $action_note);
            if (empty($error)) {
                $this->success('', leuu('Order/SupplierGroup/group', array('id'=>$group_id)));
            } else {
                $this->error(reset($error));
            }
        }
    }

    function group($group_id){
        $group = D('Order/Group')->getGroup($group_id);
        if (empty($group)) {
            $this->error(L('NO_DATA_ERROR'));
        }
        $this->assign('group_id', $group_id);
        $order_ids = array();
        foreach ($group['orders'] as $order) {
            $order_ids[] = $order['order_id'];
        }
        $where['delivery.supplier_id'] = $this->memberid;
        $where['delivery.order_id'] = array('IN', $order_ids);
        $deliveries = $this->_model->getDeliveriesNoPaged($where);
        $this->assign('deliveries', $deliveries);
        $this->display();
    }



    function view(){
        if(isset($_GET['id'])){
            $data = $this->_model->getDelivery(I('get.id'));
        }else if(isset($_GET['order_id'])){
            $data = $this->_model->getDeliveryByOrder(I('get.order_id'));
        }else{
            $this->redirect('index');
        }
        $order_id = $data['order_id'];

        $order_type = D('Order/Order')
            ->where("order_id=$order_id")
            ->getField('order_type');
        $this->assign('order_type', $order_type);
        if ($order_type == 2) {
            $group_id = M('OrderGroupRls')
                ->where("order_id=$order_id")
                ->getField('group_id');
            $this->assign('group_id', $group_id);
        }
        
        $data['user']=$this->users_model->find($this->userid);
        
        $this->assign($data);
        $this->assign('action_note',I('get.action_note'));

        $order_id = $data['order_id'];
        $logs = D('Log')->getLogsPaged("order_id:$order_id;action_place:1;");
        $this->assign('logs', $logs);

        $this->display();
    }

    function delete(){
        if(IS_POST){
            $id = I('post.id');
            $delivery = $this->_model->getDelivery($id);
            if($delivery){
                $order_id = $delivery['order_id'];

                $result = $this->_model->deleteDelivery($id);
                if($result !== false){
                    $order_model = D('Order');
                    $result = $order_model->unship($order_id);
                    if($result === false){
                        $this->_model->setError($order_model->getError());
                    }
                }

                if($result !== false){
                    $this->logAction($order_id, L('LOG_DELETE',array('sn'=>$delivery['delivery_sn'])));


                    $order_type = D('Order/Order')
                        ->where("order_id=$order_id")
                        ->getField('order_type');
                    if ($order_type == 2) {
                        $group_id = M('OrderGroupRls')
                            ->where("order_id=$order_id")
                            ->getField('group_id');
                        $href = leuu('group', array('group_id'=>$group_id));
                    } else {
                        $href = leuu('index');
                    }

                    $this->success('删除成功！', $href);
                }else{
                    $this->error('删除失败！'.$this->_model);
                }
            }else{
                $this->error('非法操作！');
            }
        }
    }

    function operate(){
        if(IS_POST && $_POST['act'] == 'delivery_ship'){
            $post = I('post.');
            if(empty($post['delivery_id'])){
                $this->error('非法操作！');
            }

            $order_id = $post['order_id'];
            $delivery_id = $post['delivery_id'];

            if(isset($post['delivery_confirmed']) && $post['delivery_confirmed'] == '发货'){
                $_POST['operation'] = 'delivery_confirmed';
                $this->operate_post();
            }
            if(isset($post['delivery_cancel_ship']) && $post['delivery_cancel_ship'] == '取消发货'){
                if(empty($post['action_note'])){
                    $this->error('请填写取消发货原因！');
                }
                $_POST['operation'] = 'delivery_cancel_ship';
                $this->operate_post();
            }
        }
    }

    function operate_post(){
        if(IS_POST){
            $post = I('post.');

            $order_id = $post['order_id'];
            $delivery_id = $post['delivery_id'];

            switch($post['operation']){
                case 'delivery_confirmed':
                    $invoice_no = $post['invoice_no'];
                    if (empty($invoice_no)) {
                        $this->error('请填写物流单号！');
                    }
                    $result = $this->_model->toDelivery($delivery_id,$invoice_no);
                    $this->send_delivery_msg($order_id);
                    break;
                case 'delivery_cancel_ship':
                    $result = $this->_model->unship($delivery_id);
                    if($result !== false){
                        $order_model = D('Order');
                        $result = $order_model->unship($order_id);
                        if($result === false){
                            $this->_model->setError($order_model->getError());
                        }
                    }
                    break;
                default:
                    $result = false;
            }

            if($result !== false){
                $order_id = $post['order_id'];
                $action_note = $post['action_note'];
                $this->logAction($order_id, $action_note);
                $order_type = D('Order/Order')
                    ->where("order_id=$order_id")
                    ->getField('order_type');
                if ($order_type == 2) {
                    $group_id = M('OrderGroupRls')
                        ->where("order_id=$order_id")
                        ->getField('group_id');
                    $href = leuu('group', array('group_id'=>$group_id));
                } else {
                    $href = leuu('Supplier/view', array('id'=>$order_id));
                }
                $this->success('操作成功！', $href);
            }else{
                $this->error('操作失败'.$this->_model->getError());
            }
        }
    }

    function logAction($order_id, $action_note){
        D('Log')->logAction($order_id, sp_get_current_userid(), $action_note, 1);
    }

    function send_delivery_msg($order_id){
        $order = D('Order')->getOrder($order_id);
        if($order){
            $title = L('MSG_TITLE_DELIVERY',array('order_sn'=>$order['order_sn']));
            $content = L('MSG_CONTENT_DELIVERY',array('order_sn'=>$order['order_sn'],'invoice_no'=>$order['invoice_no']));
            D('Notify/Msg')->sendMsg($order['user_id'],$title,$content);
        }
    }
}