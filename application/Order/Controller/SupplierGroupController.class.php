<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-02-28
 * Time: 9:56
 */

namespace Order\Controller;


use Common\Controller\SupplierbaseController;
use Order\Service\DealGroupService;

class SupplierGroupController extends SupplierbaseController
{
    protected $_model;
    protected $order_model;
    protected $dealGroupService;
    public function __construct()
    {
        parent::__construct();
        $this->_model = D('Order/Group');
        $this->order_model = D('Order/Order', 'Logic');
        $this->assign('statuses', $this->_model->statuses);
        $this->assign('model_actions', $this->_model->actions);
        $this->dealGroupService = new DealGroupService();
    }

    public function index()
    {
        if(IS_AJAX && !sp_is_mobile()){
            $where = array();

            if(isset($_REQUEST['filter'])){
                $filter = I('request.filter');

                if(isset($filter['status'])){
                    if($filter['status']==-1){
                        $shere['group_status'] = array('in',array_keys($this->_model->statuses));
                    }else{
                        $where['group_status'] = $filter['status'];
                    }
                }
                if(!empty($filter['keywords'])){
                    $where['group_sn'] = array('like','%'.$filter['keywords'].'%');
                }
                if(!empty($filter['datestart']) && !empty($filter['datefinish'])){
                    $where['create_date'] = array('between',$filter['datestart'].','.$filter['datefinsh']);
                }elseif(!empty($filter['datestart']) && empty($filter['datefinish'])){
                    $where['create_date'] = array('egt',$filter['datestart']);
                }elseif(empty($filter['datestart']) && !empty($filter['datefinish'])){
                    $where['create_date'] = array('elt',$filter['datefinish']);
                }


                $this->assign('filter',$filter);
            }



            $where['supplier_id|source_supplier_id'] = $this->memberid;
            $data['data'] = $this->dealGroupService->getRowsNoPaged($where);
            $data['status'] = 1;
            $this->ajaxReturn($data);
        }elseif(IS_AJAX && sp_is_mobile()){
            $where['supplier_id|source_supplier_id'] = $this->memberid;
            $data = $this->dealGroupService->getRowsPaged($where);
            $this->assign('data', $data);
            $html = $this->fetch('more');
            $this->ajaxReturn(array(
                'data'=>$data,
                'html'=>$html,
                'totalPages'=>$data['totalPages'],
            ));
        }else{
            $this->display();
        }
    }

    public function group()
    {
        $id = I('get.id/d',0);
        $group = $this->dealGroupService->getGroup($id);
        if(empty($group) || $group['group_trash']){
            $this->error('找不到拼单！');
        }

        $supplierDealSource = $group['issource'] == 1 && $group['supplier_id'] == $this->memberid;
        $distributorDealDist = $group['issource'] == 0 && $group['handover'] == 0
            && $group['supplier_id'] == $this->memberid;
        $supplierDealDist = $group['issource'] == 0 && $group['handover'] == 1
            && $group['source_supplier_id'] == $this->memberid;
        $distributorViewDist = $group['issource'] == 0 && $group['handover'] == 1
            && $group['supplier_id'] = $this->memberid;
        if(!($supplierDealDist || $supplierDealSource || $distributorDealDist || $distributorViewDist)){
            $this->error('您没有权限操作该拼单！');
        }
        $this->assign('supplierDealSource', $supplierDealSource);
        $this->assign('distributorDealDist', $distributorDealDist);
        $this->assign('supplierDealDist', $supplierDealDist);
        $this->assign('distributorViewDist', $distributorViewDist);

        foreach ($group['orders'] as $key => $order) {
            $order['delivery'] = D('Order/Delivery')->getDeliveryByOrder($order['order_id']);
        }

        $this->assign($group);

        $logs = D('Order/GroupLog')->getLogsPaged("group_id:$id");
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
                case 'handover':
                    $this->_handover($group_id);
                    break;
                case 'grouped':
                    $this->_grouped($group_id);
                    break;
                case 'prepare':
                    $this->_prepare($group_id);
                    break;
                case 'ship':
                    $this->success('',leuu('SupplierDelivery/groupadd',array('group_id'=>$group_id,'action_note'=>$post['action_note'])));
                    break;
                case 'unship':
                    if(empty($post['action_note'])){
                        $this->error('请填写取消发货原因！');
                    }
                    $this->_unship($group_id);
                    break;
                case 'to_delivery':
                    $this->success('',U('SupplierDelivery/group',array('group_id'=>$group_id,
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

    private function _handover($group_id){
        $group = $this->dealGroupService->getGroup($group_id);
        if(empty($group) || $group['group_trash']){
            $this->error('找不到拼单！');
        }
        $access = $group['issource'] == 0 && $group['handover'] == 0 && $group['group_status'] == 1
            && $group['supplier_id'] == $this->memberid;
        if(!$access){
            $this->error('您没有权限执行该操作！');
        }
        $result = $this->dealGroupService->saveGroup(array(
            'id'=>$group_id,
            'handover'=>1,
            'handover_time'=>date('Y-m-d H:i:s'),
        ));
        if ($result !== false) {
            $note = I('post.action_note');
            $this->_logAction($group_id, 'HANDOVER', $note);
            $this->success('操作成功！', leuu('group',array('id'=>$group_id)));
        } else {
            $this->error('操作失败！');
        }
    }

    private function _grouped($group_id)
    {
        $group = $this->_model->getGroup(array('id'=>$group_id,'supplier_id'=>$this->memberid));
        if (empty($group)) {
            $this->error(L('ERROR_REQUEST_DATA'));
        }
        foreach ($group['orders'] as $order) {
            if ($order['pay_status'] != 2) {
                $this->error('操作失败，还有订单未完成付款，请等待客户完成付款或取消订单！');
            }
        }
        $note = I('post.action_note');
        $result = $this->_model->where("id=$group_id")->setField('group_status', 1);
        if ($result !== false) {
            $this->_logAction($group_id, 'GROUPED', $note);
            $this->success('操作成功！', leuu('group',array('id'=>$group_id)));
        } else {
            $this->error('操作失败！');
        }
    }

    private function _prepare($group_id)
    {
        $group = $this->_model->getGroup(array('id'=>$group_id,'supplier_id'=>$this->memberid));
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
        D('Order/Log')->logAction($order_id, sp_get_current_userid(), $note, 0);
    }

    private function _logAction($group_id, $action, $note)
    {
        D('Order/GroupLog')->logAction($group_id, sp_get_current_userid(), $action, $note);
    }
}