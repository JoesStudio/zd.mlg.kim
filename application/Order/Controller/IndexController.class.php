<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-16
 * Time: 10:24
 */

namespace Order\Controller;


use Common\Controller\MemberbaseController;
use Tf\Service\TfUnionService;
use Order\Service\TfOrderService;

class IndexController extends MemberbaseController
{
    protected $_model;
    protected $tfUnionService;
    protected $orderService;
    protected $tfOrderService;
    protected $tf_model;

    function __construct()
    {
        parent::__construct(); // TODO: Change the autogenerated stub
        $this->_model = D('Order', 'Logic');
        $this->assign('order_statuses', $this->_model->orderStatuses);
        $this->assign('pay_statuses', $this->_model->payStatuses);
        $this->assign('shipping_statuses', $this->_model->shippingStatuses);
        $this->tfUnionService = new TfUnionService();
        $this->tfOrderService = new TfOrderService();
        $this->tf_model = D('TextileFabric');
    }

    function index($is_colorcard=0){
        if(sp_is_mobile() && !IS_AJAX){
            $this->display();
            exit();
        }

        if(isset($_REQUEST['status'])){
            $_REQUEST['filter']['status'] = $_REQUEST['status'];
        }

        if(isset($_REQUEST['filter'])){
            $filter = I('request.filter');
            if(isset($filter['status'])){
                $where['order_status'] = array('IN','0,1');
                switch($filter['status']){
                    case 'unpaid':
                        $where['pay_status'] = array('IN','0,1');
                        $where['shipping_status'] = 0;
                        if(isset($_REQUEST['status'])) $this->assign('unpaid_active', 'active');
                        break;
                    case 'unshipped':
                        $where['pay_status'] = 2;
                        $where['shipping_status'] = array('IN', '0,3,4');
                        if(isset($_REQUEST['status'])) $this->assign('unshipped_active', 'active');
                        break;
                    case 'unreceived':
                        $where['pay_status'] = 2;
                        $where['shipping_status'] = 1;
                        if(isset($_REQUEST['status'])) $this->assign('unreceived_active', 'active');
                        break;
                    case 'received':
                        $where['pay_status'] = 2;
                        $where['shipping_status'] = 2;
                        if(isset($_REQUEST['status'])) $this->assign('received_active', 'active');
                        break;
                    case 'closed':
                        $where['order_status'] = array('IN','2,3');
                        $where['pay_status'] = 0;
                        $where['shipping_status'] = 0;
                        if(isset($_REQUEST['status'])) $this->assign('received_active', 'active');
                        break;
                    case 'refunded':
                        $where['order_status'] = 4;
                        $where['pay_status'] = 0;
                        $where['shipping_status'] = 0;
                        if(isset($_REQUEST['status'])) $this->assign('received_active', 'active');
                        break;
                    default:
                        unset($where['order_status']);
                        if(isset($_REQUEST['status'])) $this->assign('default_active', 'active');

                }
            }
            if(!empty($filter['keywords'])){
                $where['order_sn'] = array('LIKE','%'.$filter['keywords'].'%');
            }
            if(!empty($filter['datestart']) && !empty($filter['datestart'])){
                $where['add_time'] = array('between', strtotime($filter['datestart']) . ',' . strtotime($filter['datefinish']));
            }elseif(!empty($filter['datestart']) && empty($filter['datestart'])){
                $where['add_time'] = array('egt', strtotime($filter['datestart']));
            }elseif(empty($filter['datestart']) && !empty($filter['datestart'])){
                $where['add_time'] = array('elt', strtotime($filter['datefinish']));
            }
            $this->assign('filter',$filter);
        }

        $where['user_id'] = $this->userid;
        $orders = $this->tfOrderService->getRowsPaged($where, 10);
        $this->assign('orders', $orders);


//        var_dump($orders);

        if(IS_AJAX){
            $html = $this->fetch('more');
            $data['html'] = $html;
            $data['totalPages'] = $orders['totalPages'];
            $this->ajaxReturn($data);
        }else{
            $this->display();
        }
    }

    function my_orders(){
        $this->display();
    }

    function view(){
        $where['id'] = I('get.id/d',0);
        $where['user_id'] = $this->userid;
        $order = $this->tfOrderService->getOrder($where);

        if(empty($order)){
            $this->redirect('index');
        }

        $this->assign($order);
        $this->display();
    }

    function colorboard_order() {
        $qty = I('request.number/d',1);

        $tf = $this->tf_model->getTf(I('get.tfsn'));

        $this->assign('tf', $tf);
        $this->assign('goods_number',$qty);
        if(isset($tf['selected_sku'])){
            $this->assign('sku', $tf['selected_sku']);
        }

        $addresses = D('Address/Address')->getAddressNoPaged(array('user_id'=>$this->userid));
        $this->assign('addresses',$addresses);

        $serviceFee = 5;
        $this->assign("serviceFee", $serviceFee);

        $tsfmType = intval(I("request.colorboard", 1));

        if ($tsfmType == 1) {
            $transportFee = 0;
        } else {
            //判断地址是否是广东省
            if ($addresses[array_keys($addresses)[0]]['province_name'] == "广东省") {
                $transportFee = 7;
            } else {
                $transportFee = 15;
            }

        }

        $this->assign("transportFee", $transportFee);
        $totalFee = $serviceFee + $transportFee;
        $this->assign("totalFee", $totalFee);

        $shippings = D('Shipping')->getShippingNoPaged();
        $this->assign('shippings',$shippings);
        $this->assign('shipping',reset($shippings));

        $this->display();
    }

    function easy_order(){
        if(IS_POST){
            $sku_id = I('request.sku_id/d', 0);
            if(empty($sku_id) || !is_numeric($sku_id)){
                $this->error('请选择面料规格！');
            }
            if(isset($_REQUEST['tfsn'])){
                $queryparams['tfsn'] = I('request.tfsn');
            }
            $queryparams['sku_id'] = $sku_id;
            if(isset($_REQUEST['number'])){
                $queryparams['number'] = I('request.number/d', 1);
            }
            $this->success('',leuu('easy_order',$queryparams));
        }else{
            $qty = I('request.number/d',1);
            if(isset($_REQUEST['tfsn'])){
                $tf_code = I('request.tfsn');
                $sku_id = I('request.sku_id/d', 0);
                $tf = $this->tfUnionService->getTf($tf_code);
                if(!empty($tf)){
                    $tf['sku'] = $this->tfUnionService->getSkuList(array(
                        'original'=>$tf['source'],
                        'tf_id'=>$tf['id'],
                    ));
                    foreach($tf['sku'] as $v){
                        if($v['id'] == $sku_id){
                            $tf['selected_sku'] = $v;
                        }
                    }
                }
                if(empty($tf['sku'])){
                    $this->error('目前该面料无货，请联系面料商！');
                }
            }else{
                $this->redirect('Portal/Index/index');
            }

            $this->assign('tf', $tf);
            $this->assign('goods_number',$qty);
            if(isset($tf['selected_sku'])){
                $this->assign('sku', $tf['selected_sku']);
            }

            $addresses = D('Address/Address')->getAddressNoPaged(array('user_id'=>$this->userid));
            $this->assign('addresses',$addresses);

            $shippings = D('Shipping')->getShippingNoPaged();
            $this->assign('shippings',$shippings);
            $this->assign('shipping',reset($shippings));

            $this->display();
        }
    }

    function easy_order_post(){
        if(IS_POST){
            $post = I('post.');
            $tfCode = I('post.tf_code');
            $aid = $post['address_id'];
            $sid = $post['shipping_id'];
            $sku_id = $post['sku_id'];
            $number = $post['goods_number'];
            $ps = $post['postscript'];

            if(empty($tfCode)){
                $this->error('操作失败，该面料无效！');
            }
            if(empty($sku_id) || !is_numeric($sku_id)){
                $this->error('请选择面料规格！');
            }
            if(empty($number) || !is_numeric($number)){
                $this->error('请选择购买的数量！');
            }
            if(empty($aid) || !is_numeric($aid)){
                $this->error('请选择收货地址！');
            }
            if(empty($sid) || !is_numeric($sid)){
                $this->error('请选择配送方式！');
            }

            $result = $this->tfOrderService->buildEasyOrder($tfCode, $sku_id, $number, $this->userid, $aid, $sid, $ps, false);
            if($result){
                //$this->ajaxReturn($result);
                $this->success('已下单！',leuu('view',array('id'=>$result)));
            }else{
                $this->error('操作失败！'.$this->_model->getError());
            }
        }
    }

    function colorboard_order_post(){
        if(IS_POST){
            $post = I('post.');
            $tfCode = I('post.tf_code');

            $aid = $post['address_id'];
            $sid = $post['shipping_id'];
            $sku_id = 1;
            $number = $post['goods_number'];
            $ps = $post['postscript'];

            $tf = $this->tf_model->getTf(I('post.tf_id'));


            if(empty($aid) || !is_numeric($aid)){
                $this->error('请选择收货地址！');
            }
            if(empty($sid) || !is_numeric($sid)){
                $this->error('请选择配送方式！');
            }

            $result = $this->tfOrderService->buildColorboardEasyOrder(array(
                "id" => $tf['id']
            ), "", 1, $this->userid, $aid, $sid, $ps, $getdata = false);
//            $this->ajaxReturn($result);
            if($result){
                $this->success('已下单！',leuu('view',array('id'=>$result)));
            }else{
                $this->error('操作失败！'.$this->tf_model->getError());
            }
        }
    }

    function build_order(){
        if(IS_POST){
            $cart = D('Cart/Cart')->getCart($this->userid);
            $post = I('post.');
            $aid = $post['address_id'];
            $sid = $post['shipping_id'];
            $ps = $post['postscript'];
            $order_ids = array();
            foreach($cart as $supplier_id=>$supplier){
                if(empty($supplier['goods'])) continue;
                $result = $this->_model->buildOrder($this->userid,$supplier_id,$aid,$sid,$ps);
                if($result !== false){
                    array_push($order_ids, $result);
                }
            }
            if($order_ids){
                D('Cart/Cart')->cleanAllItems($this->userid);
                $this->success('已添加订单！',leuu('index',array('filter[pay_status]'=>0)));
            }else{
                $this->error($this->_model->getError());
            }
        }
    }

    function receive(){
        if(IS_POST){
            $id = I('post.id');
            $where['order_id'] = $id;
            $where['user_id'] = $this->userid;
            $where['shipping_status'] = 1;
            $order = $this->_model->getOrder($where);
            if($order){
                $this->_model->startTrans();
                $result = $this->_model->setReceived($id);
                if($result !== false){
                    $this->_model->commit();
                    $this->success('操作成功！',leuu('view',array('id'=>$id)));
                }else{
                    $this->_model->rollback();
                    $this->error($this->_model->getError());
                }
            }else{
                $this->error('错误的操作！');
            }
        }
    }

    function cancel(){
        if(IS_POST){
            $id = I('post.id');
            $where['order_id'] = $id;
            $where['user_id'] = $this->userid;
            $where['shipping_status'] = 1;
            $order = $this->_model->getOrder($where);
            if($order){
                $result = $this->_model->cancel($id);
                if($result !== false){
                    $this->success('操作成功！',leuu('view',array('id'=>$id)));
                }else{
                    $this->error($this->_model->getError());
                }
            }else{
                $this->error('错误的操作！');
            }
        }
    }

    function delete(){
        if(IS_POST){
            $id = I('post.id');
            $where['user_id'] = $this->userid;
            $where['order_status'] = array('IN','2,3');
            $order = $this->_model->getOrder($where);
            if($order){
                $result = $this->_model->trash($id);
                if($result !== false){
                    $this->success('操作成功！',leuu('index'));
                }else{
                    $this->error($this->_model->getError());
                }
            }else{
                $this->error('错误的操作！');
            }
        }
    }

}