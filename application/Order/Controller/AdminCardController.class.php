<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-04-21
 * Time: 15:11
 */

namespace Order\Controller;


use Common\Controller\AdminbaseController;
use Colorcard\Model\OfferModel;
use Order\Service\CardService;
use Order\Model\OrderModel;

class AdminCardController extends AdminbaseController
{
    protected $offerModel;
    protected $cardServcie;
    protected $orderModel;
    public function __construct()
    {
        parent::__construct();
        $this->offerModel = new OfferModel();
        $this->cardServcie = new CardService();
        $this->orderModel = new OrderModel();

        $this->assign('statuses', $this->orderModel->statuses);
        $this->assign('orderStatuses', $this->orderModel->orderStatuses);
        $this->assign('payStatuses', $this->orderModel->payStatuses);
        $this->assign('shippingStatuses', $this->orderModel->shippingStatuses);
    }

    function index(){
        if(IS_AJAX){
            $data['data'] = $this->cardServcie->getOrdersNoPaged();
            $data['status'] = 1;
            $this->ajaxReturn($data);
        }
        $this->display();
    }


    function view(){
        $id = I('get.id');
        $order = $this->cardServcie->getOrder($id);
        $member = D('BizMember')->getMember($order['user_id']);
        $this->assign($order);
        $this->assign('member',$member);

        $logs = D('Log')->getLogsPaged("order_id:$id");
        $this->assign('logs', $logs);

        $allow_act = $this->orderModel->allow_act;

        $acts_tmp = array_intersect($allow_act['order'][$order['order_status']],
            $allow_act['pay'][$order['pay_status']],
            $allow_act['ship'][$order['shipping_status']]);

        $actions = array();
        foreach($acts_tmp as $v){
            if(isset($this->orderModel->operation[$v])){
                $actions[$v] = $this->orderModel->operation[$v];
            }
        }

        $this->assign('actions',$actions);

        $this->display();
    }

    function create_new_card(){
        if(IS_POST){
            $orderId = I('post.id/d','0');
            if(!$orderId){
                $this->error('传入数据错误');
            }
            $result = $this->cardServcie->buildEntityCard($orderId);
            if($result !== false){
                $this->success('已生成色卡',U('Colorcard/Admin/edit',array('id'=>$result)));
            }else{
                $this->error($this->cardServcie->getError());
            }
        }
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

            if(isset($post['modify_price']) && !empty($post['modify_price'])){
                if(empty($post['action_note'])){
                     $this->error('请填写修改价格原因！');
                }else{
                    $_POST['act'] = 'operate_post';
                    $_POST['operation'] = 'modify_price';
                    $this->operate_post();
                }
            }

            if(isset($post['pay']) && $post['pay'] == '付款'){
                if(empty($post['action_note'])){
                    $where['order_id'] = $order_id;
                    $where['pay_status'] = array('lt',2);
                    $count = $this->orderModel->where($where)->count();
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
                $count = $this->orderModel->where($where)->count();
                if($count > 0){
                    $this->success('',U('operate',array('act'=>'unpay','order_id'=>$order_id,
                        'action_note'=>$post['action_note'])));
                }else{
                    $this->success('',U('view',array('id'=>$order_id)));
                }
            }
            if(isset($post['prepare']) && $post['prepare'] == '配货'){
                $_POST['act'] = 'operate_post';
                $_POST['operation'] = 'prepare';
                $this->operate_post();
            }
            if(isset($post['ship']) && $post['ship'] == '生成发货单'){
                $this->success('',U('AdminDelivery/add',array('order_id'=>$order_id,'action_note'=>$post['action_note'])));
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
                $this->success('',U('AdminDelivery/view',array('order_id'=>$order_id,
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

            $data['order_id'] = $post['order_id'];

            switch($post['operation']){
                case 'confirm':
                    $result = $this->orderModel->setConfirm($order_id);
                    break;
                case 'pay':
                    $result = $this->orderModel->setPaid($order_id);
                    break;
                case 'unpay':
                    $result = $this->orderModel->setUnpaid($order_id);
                    break;
                case 'modify_price':
                    $data['order_id'] = $order_id;
                    $data['order_amount'] = $post['modify_price'];
                    $result = $this->orderModel->updateOrder($data);
                    break;
                case 'prepare':
                    $result = $this->orderModel->setPrepare($order_id);
                    break;
                case 'unship':
                    $result = $this->orderModel->unship($order_id);
                    if($result !== false){
                        $delivery_model = D('Delivery');
                        $delivery_id = $delivery_model->where(array('order_id'=>$order_id))->getField('delivery_id');
                        $result = $delivery_model->unship($delivery_id);
                        if($result === false){
                            $this->orderModel->setError($delivery_model->getError());
                        }
                    }
                    break;
                case 'receive':
                    $result = $this->orderModel->setReceived($order_id);
                    break;
                case 'cancel':
                    $result = $this->orderModel->cancel($order_id);
                    break;
                case 'invalid':
                    $result = false;
                    break;
                default:
                    $result = false;
            }

            if($result !== false){
                $this->logAction($order_id, $post['action_note']);
                $this->success('操作成功！', U('view',array('id'=>$order_id)));
            }else{
                $this->error('操作失败！'.$this->orderModel->getError());
            }
        }
    }

    function logAction($order_id, $action_note){
        D('Log')->logAction($order_id, sp_get_current_admin_id(), $action_note, 0);
    }

}