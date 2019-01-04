<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-06-07
 * Time: 16:54
 */

namespace Order\Controller;


use Common\Controller\SupplierbaseController;
use Order\Service\DealTfDeliveryService;
use Order\Service\DealTfOrderService;
use Order\Model\DeliveryModel;
use Order\Model\OrderModel;
use Order\Service\DealGroupService;

class SupplierTfDeliveryController extends SupplierbaseController
{
    protected $deliveryService;
    protected $orderService;
    protected $_model;
    protected $orders_model;
    public function __construct()
    {
        parent::__construct();
        $this->deliveryService = new DealTfDeliveryService();
        $this->orderService = new DealTfOrderService();
        $this->_model = new DeliveryModel();
        $this->orders_model = new OrderModel();
    }

    function index(){
        if(IS_AJAX && !sp_is_mobile()){
            $where['_string'] = "supplier_id='{$this->memberid}' OR 
            (source_supplier_id='{$this->memberid}' AND handover=1)";
            $data['data'] = $this->deliveryService->getRowsNoPaged($where);
            $data['status'] = 1;
            $this->ajaxReturn($data);
        }elseif(IS_AJAX && sp_is_mobile()){
            $where['_string'] = "supplier_id='{$this->memberid}' OR 
            (source_supplier_id='{$this->memberid}' AND handover=1)";
            $data = $this->deliveryService->getRowsPaged($where);
            $this->assign('data', $data);
            $html = $this->fetch('more');
            $this->ajaxReturn(array(
                'html'=>$html,
                'totalPages'=>$data['totalPages'],
            ));
        }else{
            $this->display();
        }
    }

    function add(){
        $order_id = I('get.order_id/d',0);
        $orderType = I('get.type/d',1);
        $action_note = I('get.action_note');
        if(M('DeliveryOrder')->where("order_id='{$order_id}'")->count() > 0){
            $this->redirect('view',array('order_id'=>$order_id));
        }
        if($orderType == 1){
            $order = $this->orderService->getOrder($order_id);
            if(empty($order)){
                $this->redirect('Supplier/view',array('id'=>$order_id));
            }
        }elseif($orderType == 2){
            $dealGroupService = new DealGroupService();
            $order = $dealGroupService->getOrder(array('order_id'=>$order_id));
            if(empty($order)){
                $this->redirect('SupplierGrorder/view',array('id'=>$order_id));
            }
        }else{
            $this->error('该订单类型无效！');
        }

        //供应商处理非代理订单
        $supplierDealSource = $order['issource'] == 1 && $order['supplier_id'] == $this->memberid;
        //供应商处理代理订单
        $supplierDealDist = $order['issource'] == 0 && $order['source_supplier_id'] == $this->memberid
            && $order['handover'] == 1;
        $this->assign('supplierDealSource', $supplierDealSource);
        $this->assign('supplierDealDist', $supplierDealDist);
        if(!($supplierDealSource || $supplierDealDist)){
            $this->error('您没有权限操作该订单！');
        }

        if(!in_array($order['shipping_status'], array(0,3))){
            $this->error('当前订单状态不允许此操作！');
        }
        $member = D('BizMember')->getMember($supplierDealSource ? $order['supplier_id']:$order['source_supplier_id']);

        $this->assign($order);
        $this->assign('member', $member);
        $this->assign('order_id',$order_id);
        $this->assign('action_note',$action_note);
        $this->assign('order_type', $orderType);
        $this->display('add');
    }

    function add_post(){
        if(IS_POST){
            $post = I('post.');
            $order_id = $post['order_id'];
            $orderType = $post['order_type'];
            $action_note = $post['action_note'];

            if($orderType == 1){
                $order = $this->orderService->getOrder($order_id);
                $href = leuu('Supplier/view',array('id'=>$order_id));
            }elseif($orderType == 2){
                $dealGroupService = new DealGroupService();
                $order = $dealGroupService->getOrder(array('order_id'=>$order_id));
                $href = leuu('SupplierGrorder/view',array('id'=>$order_id));
            }else{
                $this->error('该订单类型无效！');
            }
            if(empty($order)){
                $this->error('当前订单无效！');
            }
            //供应商处理非代理订单
            $supplierDealSource = $order['issource'] == 1 && $order['supplier_id'] == $this->memberid;
            //供应商处理代理订单
            $supplierDealDist = $order['issource'] == 0 && $order['source_supplier_id'] == $this->memberid
                && $order['handover'] == 1;
            $this->assign('supplierDealSource', $supplierDealSource);
            $this->assign('supplierDealDist', $supplierDealDist);
            if(!($supplierDealSource || $supplierDealDist)){
                $this->error('您没有权限操作该订单！');
            }

            $data = $this->_model->build_delivery_data($order_id,'',sp_get_current_userid());
            if(!empty($data)){
                $result = $this->_model->buildDelivery($data);
                if($result !== false){
                    $this->orders_model->readyForShipment($order_id);
                    $this->logAction($order_id, $action_note);
                    $this->success('已生成发货单！',$href);
                }else{
                    $this->error($this->_model->getError());
                }
            }else{
                $this->error('订单数据错误！');
            }
        }
    }

    function view(){
        if(isset($_GET['order_id'])){
            $data = $this->deliveryService->getDeliveryByOrder(I('get.order_id/d',0));
        }else{
            $data = $this->deliveryService->getDelivery(I('get.id/d',0));
        }

        if(empty($data)){
            $this->redirect('index');
        }
        $supplierDealSource = $data['issource'] == 1 && $data['supplier_id'] == $this->memberid;
        $supplierDealDist = $data['issource'] == 0 && $data['source_supplier_id'] == $this->memberid;
        $distributorDealDist = $data['issource'] == 0 && $data['supplier_id'] == $this->memberid;
        $this->assign('supplierDealSource', $supplierDealSource);
        $this->assign('supplierDealDist', $supplierDealDist);
        $this->assign('distributorDealDist', $distributorDealDist);
        if(!($distributorDealDist || $supplierDealDist || $supplierDealSource)){
            $this->error('您没有权限操作该发货单！');
        }

        $order_id = $data['order_id'];

        $data['user']=$this->users_model->find($this->userid);

        $this->assign($data);
        $this->assign('action_note',I('get.action_note'));
        $this->assign('order_type', $data['order_type']);

        $logs = D('Log')->getLogsPaged("order_id:$order_id;action_place:1;");
        $this->assign('logs', $logs);

        $this->display();
    }

    function delete(){
        if(IS_POST){
            $id = I('post.id');

            $delivery = $this->deliveryService->getDelivery($id);
            if(empty($delivery)){
                $this->error('当前发货单无效！');
            }
            //供应商处理非代理订单
            $supplierDealSource = $delivery['issource'] == 1 && $delivery['supplier_id'] == $this->memberid;
            //供应商处理代理订单
            $supplierDealDist = $delivery['issource'] == 0 && $delivery['source_supplier_id'] == $this->memberid
                && $delivery['handover'] == 1;
            $this->assign('supplierDealSource', $supplierDealSource);
            $this->assign('supplierDealDist', $supplierDealDist);
            if(!($supplierDealSource || $supplierDealDist)){
                $this->error('您没有权限操作该发货单！');
            }

            $order_id = $delivery['order_id'];

            $result = $this->_model->deleteDelivery($id);
            if($result !== false){
                $result = $this->order_model->unship($order_id);
                if($result === false){
                    $this->_model->setError($this->order_model->getError());
                }
            }

            if($result !== false){
                $this->logAction($order_id, L('LOG_DELETE',array('sn'=>$delivery['delivery_sn'])));
                $this->success('删除成功！', leuu('index'));
            }else{
                $this->error('删除失败！');
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
            $orderType = $post['order_type'];
            if($orderType == 1){
                $order = $this->orderService->getOrder($order_id);
            }elseif($orderType == 2){
                $dealGroupService = new DealGroupService();
                $order = $dealGroupService->getOrder(array('order_id'=>$order_id));
            }else{
                $this->error('该订单类型无效！');
            }
            if(empty($order)){
                $this->error('当前订单无效！');
            }
            $order['order_type'] = $orderType;
            //供应商处理非代理订单
            $supplierDealSource = $order['issource'] == 1 && $order['supplier_id'] == $this->memberid;
            //供应商处理代理订单
            $supplierDealDist = $order['issource'] == 0 && $order['source_supplier_id'] == $this->memberid
                && $order['handover'] == 1;
            $this->assign('supplierDealSource', $supplierDealSource);
            $this->assign('supplierDealDist', $supplierDealDist);
            if(!($supplierDealSource || $supplierDealDist)){
                $this->error('您没有权限操作该订单！');
            }

            if(isset($post['delivery_confirmed']) && $post['delivery_confirmed'] == '发货'){
                $_POST['operation'] = 'delivery_confirmed';
                $this->operate_post($order);
            }
            if(isset($post['delivery_cancel_ship']) && $post['delivery_cancel_ship'] == '取消发货'){
                if(empty($post['action_note'])){
                    $this->error('请填写取消发货原因！');
                }
                $_POST['operation'] = 'delivery_cancel_ship';
                $this->operate_post($order);
            }
        }
    }

    private function operate_post($order){
        if(IS_POST){
            $post = I('post.');

            $order_id = $post['order_id'];
            $delivery_id = $post['delivery_id'];

            switch($post['operation']){
                case 'delivery_confirmed':
                    $invoice_no = $post['invoice_no'];
                    if (empty($invoice_no) || $invoice_no == '') {
                        $this->error('请填写物流单号！');
                    }
                    $result = $this->_model->toDelivery($delivery_id,$invoice_no);
                    $this->send_delivery_msg($order_id);
                    break;
                case 'delivery_cancel_ship':
                    $result = $this->_model->unship($delivery_id);
                    if($result !== false){
                        $result = $this->order_model->unship($order_id);
                        if($result === false){
                            $this->_model->setError($this->order_model->getError());
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
                if($order['order_type'] == 2){
                    $href = leuu('SupplierGrorder/view',array('id'=>$order_id));
                }else{
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
        $order = $this->orders_model->find($order_id);
        if($order){
            $title = L('MSG_TITLE_DELIVERY',array('order_sn'=>$order['order_sn']));
            $content = L('MSG_CONTENT_DELIVERY',array('order_sn'=>$order['order_sn'],'invoice_no'=>$order['invoice_no']));
            D('Notify/Msg')->sendMsg($order['user_id'],$title,$content);
        }
    }

}