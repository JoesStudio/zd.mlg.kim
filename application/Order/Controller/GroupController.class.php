<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-02-28
 * Time: 9:56
 */

namespace Order\Controller;


use Common\Controller\MemberbaseController;
use Tf\Service\TfUnionService;
use Order\Service\TfGroupService;

class GroupController extends MemberbaseController
{
    protected $_model;
    protected $order_model;
    protected $tfUnionService;
    protected $groupService;

    public function __construct()
    {
        parent::__construct();
        $this->_model = D('Order/Group');
        $this->order_model = D('Order/Order', 'Logic');
        $this->assign('statuses', $this->_model->statuses);
        $this->assign('pay_statuses', $this->order_model->payStatuses);
        $this->assign('shipping_statuses', $this->order_model->shippingStatuses);
        $this->assign('order_statuses', $this->order_model->orderStatuses);
        $this->tfUnionService = new TfUnionService();
        $this->groupService = new TfGroupService();
    }

    public function index()
    {
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
            if(!empty($filter['datestart']) && !empty($filter['datefinish'])){
                $where['add_time'] = array('between', strtotime($filter['datestart']) . ',' . strtotime($filter['datefinish']));
            }elseif(!empty($filter['datestart']) && empty($filter['datefinish'])){
                $where['add_time'] = array('egt', strtotime($filter['datestart']));
            }elseif(empty($filter['datestart']) && !empty($filter['datefinish'])){
                $where['add_time'] = array('elt', strtotime($filter['datefinish']));
            }
            $this->assign('filter',$filter);
        }

        $where['user_id'] = $this->userid;
        $orders = $this->groupService->getOrdersPaged($where);
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

    function view(){
        $where['order_id'] = I('get.oid');
        $where['user_id'] = $this->userid;
        $order = $this->groupService->getOrder($where);
        if (empty($order)) {
            $this->error('找不到订单！');
        }
        $this->assign($order);
        $this->display();
    }

    public function glist()
    {
        $tfCode = I('request.tfsn');
        if(empty($tfCode) || $tfCode == ''){
            $this->error('传入数据错误！');
        }
        $tf = $this->tfUnionService->getTf($tfCode);
        if(empty($tfCode)){
            $this->error('该面料无效！');
        }
        if($tf['on_sale'] == 0){
            $this->error('该面料已下架！');
        }
        $sku = $this->tfUnionService->unionSkuModel
            ->where(array(
                'original'=>$tf['source'],
                'id'=>I('request.sku_id/d', 0),
                'tf_id'=>$tf['id'],
                'group_enabled'=>1,
            ))
            ->find();
        if(empty($sku)){
            $this->error('当前选择的颜色是无效的！');
        }

        $skuId = $sku['id'];

        $where = array();
        $where['goods_sku_id'] = $skuId;
        $where['group_status'] = 0;
        $where['group_trash'] = 0;
        $where['issource'] = $tf['source'];

        $groups = $this->_model->getGroupsNoPaged($where);
        if (empty($groups)) {
            $this->redirect('group_order', array('tfsn'=>$tf['tf_code'],'sku_id'=>$skuId));
        }
        $this->assign('groups', $groups);
        $this->assign('tf_code', $tfCode);
        $this->display();
    }

    public function group_list()
    {
        $goods_ids = $this->_model->distinct(true)->where(array('group_trash'=>array('neq',1)))->field('goods_id')->select();
        $tf_ids = array();

        $search = I('request.search/s','');
        if(!empty($search)){
            $tag['name|code|spec|material|component|function|purpose'] = array('LIKE','%'.$search.'%');
        }
        foreach($goods_ids as $key =>$value){
            array_push($tf_ids,$value['goods_id']);
        }
        $tag['id'] = array('in',$tf_ids);
        $tf_list = D("Tf/Tf")->getTfNoPaged($tag);

        
        
        $this->assign('tf_list',$tf_list);

        $this->display();
    }

    public function group_order()
    {
        if(isset($_REQUEST['group_id'])){
            $group = M('OrderGroup')
                ->where(array(
                    'group_status'=>0,
                    'group_trash'=>0,
                    'id'=>I('request.group_id/d',0),
                ))
                ->find();
            if(empty($group)){
                $this->error('该拼单无效！');
            }
            $tfCode = $group['goods_sn'];
            $skuId = $group['goods_sku_id'];
            $this->assign('group', $group);
        }else{
            $tfCode = I('request.tfsn');
            $skuId = I('request.sku_id/d', 0);
        }
        if(empty($tfCode) || $tfCode == ''){
            $this->error('传入数据错误！');
        }
        $tf = $this->tfUnionService->getTf($tfCode);
        if(empty($tfCode)){
            $this->error('该面料无效！');
        }
        if($tf['on_sale'] == 0){
            $this->error('该面料已下架！');
        }
        $sku = $this->tfUnionService->unionSkuModel
            ->where(array(
                'original'=>$tf['source'],
                'id'=>$skuId,
                'tf_id'=>$tf['id'],
                'group_enabled'=>1,
            ))
            ->find();
        if(empty($sku)){
            $this->error('当前选择的颜色是无效的！');
        }

        $this->assign('tf', $tf);
        $this->assign('sku', $sku);

        $addresses = D('Address/Address')->getAddressNoPaged(array('user_id'=>$this->userid));
        $this->assign('addresses',$addresses);

        $shippings = D('Shipping')->getShippingNoPaged();
        $this->assign('shippings',$shippings);
        $this->assign('shipping',reset($shippings));

        $this->assign('goods_number', 1);
        $this->display();
    }

    public function group_order_post()
    {
        if (IS_POST) {
            $post = I('post.');
            $tf_code = $post['tf_code'];
            $sku_id = $post['sku_id'];
            $aid = $post['address_id'];
            $sid = $post['shipping_id'];
            $number = $post['goods_number'];
            $ps = $post['postscript'];

            if(empty($sku_id) || $sku_id == 0){
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

            $tf = $this->tfUnionService->getTf($tf_code);
            if (empty($tf)) {
                $this->error('该面料无效！');
            }
            $sku = $this->tfUnionService->unionSkuModel
                ->where(array(
                    'original'=>$tf['source'],
                    'id'=>$sku_id,
                    'sku_type'=>0,
                    'tf_id'=>$tf['id'],
                    'group_enabled'=>1,
                ))
                ->find();
            if(empty($sku)){
                $this->error('当前面料不存在这个颜色或不支持拼单！');
            }

            $this->groupService->groupModel->startTrans();
            if (isset($post['group_id'])) {
                $result = $this->groupService->createGroupOrder($post['group_id'], $number, $this->userid, $aid, $sid, $ps, false);
            } else {
                $result = $this->groupService->createGroup($tf['tf_code'], $sku_id, $post['expire_date'], $number, $this->userid, $aid, $sid, $ps, false);
            }

            if($result !== false){
                $this->groupService->groupModel->commit();
                //$this->ajaxReturn($result);
                $this->success('已创建拼单！',leuu('view',array('oid'=>$result)));
            }else{
                $this->groupService->groupModel->rollback();
                $this->error('操作失败！'.$this->groupService->getError());
            }
        }
    }

    public function refresh_status($id)
    {
        $this->_model->check_prepare($id);
    }


    function receive(){
        if(IS_POST){
            $id = I('post.id');
            $where['order_id'] = $id;
            $where['user_id'] = $this->userid;
            $where['shipping_status'] = 1;
            $order = $this->order_model->getOrder($where);
            if($order){
                $result = $this->order_model->setReceived($id);
                if($result !== false){
                    $this->success('操作成功！',leuu('view',array('oid'=>$id)));
                }else{
                    $this->error($this->order_model->getError());
                }
            }else{
                $this->error('错误的操作！');
            }
        }
    }

    function cancel(){
        if(IS_POST){
            $id = I('post.id');
            $order = $this->order_model->where(array(
                'order_id'=>$id,
                'user_id'=>$this->userid,
            ))->find();
            if(empty($order)){
                $this->error('该订单不存在！');
            }
            if($order['pay_status'] > 0){
                $this->error('该订单已付款或正在付款，不能取消！');
            }
            $this->groupService->orderModel->startTrans();
            $result = $this->groupService->cancel($id);
            if($result !== false){
                $this->groupService->orderModel->commit();
                $this->success('操作成功！',leuu('view',array('oid'=>$id)));
            }else{
                $this->groupService->orderModel->rollback();
                $this->error($this->groupService->orderModel->getError());
            }
        }
    }

    function delete(){
        if(IS_POST){
            $id = I('post.id');
            $where['user_id'] = $this->userid;
            $where['order_status'] = array('IN','2,3');
            $order = $this->order_model->getOrder($where);
            if($order){
                $result = $this->order_model->trash($id);
                if($result !== false){
                    $this->success('操作成功！',leuu('index'));
                }else{
                    $this->error($this->order_model->getError());
                }
            }else{
                $this->error('错误的操作！');
            }
        }
    }
}