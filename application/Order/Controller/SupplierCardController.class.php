<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-04-20
 * Time: 17:09
 */

namespace Order\Controller;


use Common\Controller\SupplierbaseController;
use Colorcard\Model\OfferModel;
use Order\Service\CardService;
use Order\Model\OrderModel;

class SupplierCardController extends SupplierbaseController
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
    }

    function index(){
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

        $where['user_id'] = $this->memberid;
        $orders = $this->cardServcie->getOrdersPaged($where);
        $this->assign('orders', $orders);
        if(IS_AJAX){
            $html = $this->fetch('more');
            $data['html'] = $html;
            $data['totalPages'] = $orders['totalPages'];
            $this->ajaxReturn($data);
        }else{
            $this->display();
        }
    }

    function new_card(){
        $tmpOffers = $this->offerModel->getRowsNoPaged();
        $offers = array();
        foreach($tmpOffers as $row){
            $offers[$row['tpl_id']][$row['tf_qty']][$row['print_qty']] = $row;
        }
        $this->assign('offers', $offers);

        $addresses = D('Address/Address')->getAddressNoPaged(array('user_id'=>$this->userid));
        $this->assign('addresses',$addresses);

        $shippings = D('Shipping')->getShippingNoPaged();
        $this->assign('shippings',$shippings);
        $this->assign('shipping',reset($shippings));
        $this->display();
    }

    function new_card2($ofid=0){
        $offer = $this->offerModel->getRow($ofid);
        $this->assign('offer', $offer);
        /*$tpl = M('ColorcardTpl')->find($offer['tpl_id']);
        $this->assign('tpl', $tpl);*/

        $tmpOffers = $this->offerModel->getRowsNoPaged("tpl_id:".$offer['tpl_id']);
        $offers = array();
        foreach($tmpOffers as $row){
            $offers[$row['tf_qty']][$row['print_qty']] = $row;
        }
        $this->assign('offers', $offers);

        $addresses = D('Address/Address')->getAddressNoPaged(array('user_id'=>$this->userid));
        $this->assign('addresses',$addresses);

        $shippings = D('Shipping')->getShippingNoPaged();
        $this->assign('shippings',$shippings);
        $this->assign('shipping',reset($shippings));
        $this->display();
    }

    function easy_order($id){
        if(!$this->cardServcie->isEntity($id)){
            $this->error('该色卡不是实体色卡！');
        }
        $isSecondary = $this->cardServcie->isSecondaryOrder($id);
        $this->assign('isSecondary', $isSecondary);

        $offers = $this->cardServcie->getOffersByCard($id);
        $this->assign('offers',$offers);

        $addresses = D('Address/Address')->getAddressNoPaged(array('user_id'=>$this->userid));
        $this->assign('addresses',$addresses);
        $shippings = D('Shipping')->getShippingNoPaged();
        $this->assign('shippings',$shippings);
        $this->assign('shipping',reset($shippings));

        $this->assign('card_id', $id);
        $this->display();
    }

    function new_card_post(){
        if(IS_POST){
            $post = I('post.');
            $oId = $post['offer_id'];
            $aId = $post['address_id'];
            if(empty($aId)){
                $this->error('请选择收货地址');
            }
            $sId = $post['shipping_id'];
            if(empty($sId)){
                $this->error('请选择配送方式');
            }
            $ps = $post['postscript'];
            $preData = $this->cardServcie->buildOrderData($this->memberid,0,$oId,$aId,$sId,$ps,$this->userid);
            /*$this->ajaxReturn(array(
                'status'    => 1,
                'data'  => $preData,
            ));
            return true;*/
            $result = $this->cardServcie->createOrder($preData);
            if($result !== false){
                $this->success('已添加订单！', leuu('view', array('id'=>$result)));
            }else{
                $this->error($this->cardServcie->getError());
            }
        }
    }

    function easy_order_post(){
        if(IS_POST){
            $post = I('post.');
            $cardId = $post['card_id'];
            if($cardId == 0){
                $this->error('传入数据错误');
            }
            $oId = $post['offer_id'];
            $aId = $post['address_id'];
            if(empty($aId)){
                $this->error('请选择收货地址');
            }
            $sId = $post['shipping_id'];
            if(empty($sId)){
                $this->error('请选择配送方式');
            }
            $ps = $post['postscript'];
            $preData = $this->cardServcie->buildOrderData($this->memberid,$cardId,$oId,$aId,$sId,$ps,$this->userid);
            /*$this->ajaxReturn(array(
                'status'    => 1,
                'data'  => $preData,
            ));
            return true;*/
            $result = $this->cardServcie->createOrder($preData);
            if($result !== false){
                $this->success('已添加订单！', leuu('view', array('id'=>$result)));
            }else{
                $this->error($this->cardServcie->getError());
            }
        }
    }

    function view($id){
        $order = $this->cardServcie->getOrder($id);
        $this->assign($order);
        $this->display();
    }

    function receive(){
        if(IS_POST){
            $id = I('post.id');
            $where['order_id'] = $id;
            $where['user_id'] = $this->memberid;
            $where['shipping_status'] = 1;
            $order = $this->orderModel->where($where)->find();
            if($order){
                $result = $this->orderModel->setReceived($id);
                if($result !== false){
                    $this->success('操作成功！',leuu('view',array('id'=>$id)));
                }else{
                    $this->error($this->orderModel->getError());
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
            $where['user_id'] = $this->memberid;
            $where['shipping_status'] = 1;
            $order = $this->orderModel->where($where)->find();
            if($order){
                $result = $this->orderModel->cancel($id);
                if($result !== false){
                    $this->success('操作成功！',leuu('view',array('id'=>$id)));
                }else{
                    $this->error($this->orderModel->getError());
                }
            }else{
                $this->error('错误的操作！');
            }
        }
    }

    function delete(){
        if(IS_POST){
            $id = I('post.id');
            $where['user_id'] = $this->memberid;
            $where['order_status'] = array('IN','2,3');
            $order = $this->orderModel->where($where)->find();
            if($order){
                $result = $this->orderModel->trash($id);
                if($result !== false){
                    $this->success('操作成功！',leuu('index'));
                }else{
                    $this->error($this->orderModel->getError());
                }
            }else{
                $this->error('错误的操作！');
            }
        }
    }

}