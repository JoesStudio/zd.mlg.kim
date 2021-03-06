<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-16
 * Time: 10:24
 */

namespace Order\Controller;


use Common\Controller\SupplierbaseController;
use Order\Service\DealGroupService;

class SupplierGrorderController extends SupplierbaseController
{
    protected $_model;
    protected $dealGroupService;

    function __construct()
    {
        parent::__construct(); // TODO: Change the autogenerated stub
        $this->_model = D('Order', 'Logic');
        $this->assign('order_statuses', $this->_model->orderStatuses);
        $this->assign('pay_statuses', $this->_model->payStatuses);
        $this->assign('shipping_statuses', $this->_model->shippingStatuses);
        $this->dealGroupService = new DealGroupService();
    }

    function view()
    {
        $id = I('get.id/d', 0);

        $order = $this->dealGroupService->getOrder(array('order_id' => $id));
        if (empty($order)) {
            $this->error('订单不存在！');
        }

        $group = $order['group'];

        $supplierDealSource = $group['issource'] == 1 && $group['supplier_id'] == $this->memberid;
        $distributorDealDist = $group['issource'] == 0 && $group['handover'] == 0
            && $group['supplier_id'] == $this->memberid;
        $supplierDealDist = $group['issource'] == 0 && $group['handover'] == 1
            && $group['source_supplier_id'] == $this->memberid;
        $distributorViewDist = $group['issource'] == 0 && $group['handover'] == 1
            && $group['supplier_id'] = $this->memberid;
        if (!($supplierDealDist || $supplierDealSource || $distributorDealDist || $distributorViewDist)) {
            $this->error('您没有权限操作该拼单！');
        }
        $this->assign('supplierDealSource', $supplierDealSource);
        $this->assign('distributorDealDist', $distributorDealDist);
        $this->assign('supplierDealDist', $supplierDealDist);
        $this->assign('distributorViewDist', $distributorViewDist);

        $order_id = $order['id'];
        $this->assign($order);
        $logs = D('Log')->getLogsPaged("order_id:$id");
        $this->assign('logs', $logs);

        $allow_act = $this->_model->allow_act;
        $acts_tmp = array_intersect($allow_act['order'][$order['order_status']],
            $allow_act['pay'][$order['pay_status']],
            $allow_act['ship'][$order['shipping_status']]);
        $actions = array();
        foreach ($acts_tmp as $v) {
            if (in_array($v, array('unpay', 'pay', 'modify_price'))) {
                continue;
            }
            if (isset($this->_model->operation[$v])) {
                $actions[$v] = $this->_model->operation[$v];
            }
        }
        $this->assign('actions', $actions);

        $group_id = M('OrderGroupRls')->where("order_id=$order_id")->getField('group_id');
        $this->assign('group_id', $group_id);

        $this->display();
    }

    function operate()
    {
        if (IS_POST) {
            $post = I('post.');
            if (empty($post['order_id'])) {
                $this->error('非法操作！');
            }

            $order_id = $post['order_id'];
            $order = $this->dealGroupService->getOrder(array('order_id' => $order_id));
            if (empty($order)) {
                $this->error('操作失败！');
            }
            $group = $order['group'];
            $supplierDealSource = $group['issource'] == 1 && $group['supplier_id'] == $this->memberid;
            $distributorDealDist = $group['issource'] == 0 && $group['handover'] == 0
                && $group['supplier_id'] == $this->memberid;
            $supplierDealDist = $group['issource'] == 0 && $group['handover'] == 1
                && $group['source_supplier_id'] == $this->memberid;
            if (!($supplierDealDist || $supplierDealSource || $distributorDealDist)) {
                $this->error('您没有权限操作该拼单！');
            }

            if (($supplierDealSource || $distributorDealDist)
                && isset($post['confirm']) && $post['confirm'] == '确认'
            ) {
                $_POST['act'] = 'operate_post';
                $_POST['operation'] = 'confirm';
                $this->operate_post($order);
            }
            if (($supplierDealSource || $supplierDealDist)
                && isset($post['prepare']) && $post['prepare'] == '配货'
            ) {
                $_POST['act'] = 'operate_post';
                $_POST['operation'] = 'prepare';
                $this->operate_post($order);
            }
            if (($supplierDealSource || $supplierDealDist)
                && isset($post['ship']) && $post['ship'] == '生成发货单') {
                $this->success('', leuu('SupplierTfDelivery/add', array('type'=>2,'order_id' => $order_id,
                    'action_note' => $post['action_note'])));
            }
            if (($supplierDealSource || $supplierDealDist)
                && isset($post['unship']) && $post['unship'] == '未发货') {
                if (empty($post['action_note'])) {
                    $this->error('请填写取消发货原因！');
                } else {
                    $_POST['act'] = 'operate_post';
                    $_POST['operation'] = 'unship';
                    $this->operate_post($order);
                }
            }
            if (($supplierDealSource || $supplierDealDist)
                && isset($post['to_delivery']) && $post['to_delivery'] == '去发货') {
                $this->success('', leuu('SupplierTfDelivery/view', array('order_id' => $order_id,
                    'action_note' => $post['action_note'])));
            }
            if (($supplierDealSource || $supplierDealDist)
                && isset($post['receive']) && $post['receive'] == '已收货') {
                if (empty($post['action_note'])) {
                    $this->error('请填写手动收货的原因！');
                } else {
                    $_POST['act'] = 'operate_post';
                    $_POST['operation'] = 'receive';
                    $this->operate_post($order);
                }
            }
            if (($supplierDealSource || $distributorDealDist)
                && isset($post['cancel']) && $post['cancel'] == '取消'
            ) {
                if (empty($post['action_note'])) {
                    $this->error('请填写取消订单原因！');
                } else {
                    $_POST['act'] = 'operate_post';
                    $_POST['operation'] = 'cancel';
                    $this->operate_post($order);
                }
            }
            if (($supplierDealSource || $distributorDealDist)
                && isset($post['invalid']) && $post['invalid'] == '无效'
            ) {
                $_POST['operation'] = 'invalid';
                $this->operate_post($order);
            }
            $this->error('操作失败！');
        } else {
            $this->assign('order_id', I('get.order_id'));
            $this->assign('action_note', I('get.action_note'));
            $this->display(I('get.act'));
        }
    }

    private function operate_post($order)
    {
        if (IS_POST) {
            $post = I('post.');

            $order_id = $order['id'];
            $group = $order['group'];

            $data['order_id'] = $post['order_id'];

            switch ($post['operation']) {
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
                    if ($result !== false) {
                        $delivery_model = D('Delivery');
                        $delivery_id = $delivery_model->where(array('order_id' => $order_id))->getField('delivery_id');
                        $result = $delivery_model->unship($delivery_id);
                        if ($result === false) {
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

            if ($result !== false) {
                $this->logAction($order_id, $post['action_note']);
                $this->success('操作成功！', leuu('view', array('id' => $order_id)));
            } else {
                $this->error('操作失败！' . $this->_model->getError());
            }
        }
    }

    function logAction($order_id, $action_note)
    {
        D('Log')->logAction($order_id, sp_get_current_userid(), $action_note, 0);
    }

}