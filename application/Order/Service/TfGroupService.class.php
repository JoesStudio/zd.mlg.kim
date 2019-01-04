<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-06-09
 * Time: 11:39
 */

namespace Order\Service;

use Tf\Service\TfUnionService;
use Order\Model\OrderModel;
use Address\Model\AddressModel;
use Common\Model\ShippingModel;
use Order\Model\GroupModel;

class TfGroupService
{
    protected $tfUnionService;
    public $orderModel;
    protected $addressModel;
    protected $shippingModel;
    public $groupModel;
    protected $groupView;
    protected $error;
    public function __construct()
    {
        $this->tfUnionService = new TfUnionService();
        $this->orderModel = new OrderModel();
        $this->addressModel = new AddressModel();
        $this->shippingModel = new ShippingModel();
        $this->groupModel = new GroupModel();
        $this->groupView = M('TfGrouporderView');
    }

    function rows($tag = '', $pageSize = 0, $pagetpl = '', $tplName = 'default')
    {
        $where = array();
        if (is_array($tag)) {
            $where = array_merge($where, $tag);
        } else {
            $tag = sp_param_lable($tag);
            if (isset($tag['user_id'])) {
                $where['user_id'] = $tag['user_id'];
            }
            if (isset($tag['supplier_id'])) {
                $where['supplier_id'] = $tag['supplier_id'];
            }
            if (isset($tag['where'])) {
                $where['_string'] = $tag['where'];
            }
        }

        $where["order_trash"] = isset($where["order_trash"]) ? $where["order_trash"] : 0;

        $field = !empty($tag['field']) ? $tag['field']:'*';
        $order = !empty($tag['order']) ? $tag['order'] : "add_time DESC";

        $data['total'] = $this->groupView->where($where)->count();

        $this->groupView->field($field)->where($where)->order($order);
        if (empty($pageSize)) {
            if (!empty($tag['limit'])) {
                $this->groupView->limit($tag['limit']);
            }
        } else {
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->groupView->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }

        $rs = $this->groupView->select();

        $data['data'] = array();
        if (!empty($rs)) {
            $groups = array();
            foreach ($rs as $k=>$v) {
                $group_id = $v['group_id'];
                if(!isset($groups[$group_id])){
                    $groups[$group_id] = array(
                        'id'=>$v['group_id'],
                        'supplier_id'=>$v['supplier_id'],
                        'supplier_name'=>$v['supplier_name'],
                        'supplier_logo'=>$v['supplier_logo'],
                        'group_sn'=>$v['group_sn'],
                        'group_status'=>$v['group_status'],
                        'goods_number'=>$v['group_goods_number'],
                        'goods_amount'=>$v['group_goods_amount'],
                        'group_amount'=>$v['group_amount'],
                        'min_charge'=>$v['group_min_charge'],
                        'group_trash'=>$v['group_trash'],
                        'create_date'=>$v['group_create_date'],
                        'modify_date'=>$v['group_modify_date'],
                        'expire_date'=>$v['group_expire_date'],
                        'issource'=>$v['issource'],
                        'source_id'=>$v['source_id'],
                        'source_supplier_id'=>$v['source_supplier_id'],
                        'source_supplier_name'=>$v['source_supplier_name'],
                        'source_supplier_logo'=>$v['source_supplier_logo'],
                        'handover'=>$v['handover'],
                        'handover_time'=>$v['handover_time'],
                        'paid_user_number'=>$v['paid_user_number'],
                        'paid_goods_number'=>$v['paid_goods_number'],
                        'ordered_user_number'=>$v['ordered_user_number'],
                        'goods'=>array(
                            'tf_id'=>$v['goods_id'],
                            'tf_code'=>$v['goods_sn'],
                            'tf_name'=>$v['goods_name'],
                            'price'=>$v['goods_price'],
                            'number'=>$v['order_goods_number'],
                            'sku'=>json_decode($v['goods_sku'],true),
                            'sku_id'=>$v['goods_sku_id'],
                            'unit'=>$v['goods_unit'],
                            'thumb'=>$v['goods_thumb'],
                        ),
                        'orders'=>array(),
                    );
                }
                $groups[$group_id]['orders'][] = array(
                    'id'=>$v['order_id'],
                    'supplier_id'=>$v['supplier_id'],
                    'supplier_name'=>$v['supplier_name'],
                    'supplier_logo'=>$v['supplier_logo'],
                    'order_sn'=>$v['order_sn'],
                    'user_id'=>$v['user_id'],
                    'user_nickname'=>$v['user_nickname'],
                    'user_avatar'=>$v['user_avatar'],
                    'order_status'=>$v['order_status'],
                    'pay_status'=>$v['pay_status'],
                    'shipping_status'=>$v['shipping_status'],
                    'consignee'=>$v['consignee'],
                    'country'=>$v['country'],
                    'privince'=>$v['privince'],
                    'province_name'=>$v['province_name'],
                    'city'=>$v['city'],
                    'city_name'=>$v['city_name'],
                    'district'=>$v['district'],
                    'district_name'=>$v['district_name'],
                    'address'=>$v['address'],
                    'zipcode'=>$v['zipcode'],
                    'tel'=>$v['tel'],
                    'mobile'=>$v['mobile'],
                    'email'=>$v['email'],
                    'postscript'=>$v['postscript'],
                    'shipping_id'=>$v['shipping_id'],
                    'shipping_name'=>$v['shipping_name'],
                    'pay_id'=>$v['pay_id'],
                    'pay_name'=>$v['pay_name'],
                    'goods_amount'=>$v['order_goods_amount'],
                    'shipping_fee'=>$v['shipping_fee'],
                    'insure_fee'=>$v['insure_fee'],
                    'pay_fee'=>$v['pay_fee'],
                    'order_amount'=>$v['order_amount'],
                    'add_time'=>$v['add_time'],
                    'confirm_time'=>$v['confirm_time'],
                    'pay_time'=>$v['pay_time'],
                    'shipping_time'=>$v['shipping_time'],
                    'invoice_no'=>$v['invoice_no'],
                    'to_buyer'=>$v['to_buyer'],
                    'pay_note'=>$v['pay_note'],
                    'inv_type'=>$v['inv_type'],
                    'inv_content'=>$v['inv_content'],
                    'tax'=>$v['tax'],
                    'discount'=>$v['discount'],
                    'order_trash'=>$v['order_trash'],
                );
            }
            foreach($groups as $v){
                $data['data'][] = $v;
            }
            unset($groups);
            unset($rs);
        }

        return $data;
    }

    function getRowsPaged($tag = '', $pageSize = 20, $pagetpl = '', $tplName = 'default')
    {
        return $this->rows($tag, $pageSize, $pagetpl, $tplName);
    }

    function getRowsNoPaged($tag = '')
    {
        $data = $this->rows($tag);
        return $data['data'];
    }

    function orders($tag = '', $pageSize = 0, $pagetpl = '', $tplName = 'default')
    {
        $where = array();
        if (is_array($tag)) {
            $where = array_merge($where, $tag);
        } else {
            $tag = sp_param_lable($tag);
            if (isset($tag['user_id'])) {
                $where['user_id'] = $tag['user_id'];
            }
            if (isset($tag['supplier_id'])) {
                $where['supplier_id'] = $tag['supplier_id'];
            }
            if (isset($tag['where'])) {
                $where['_string'] = $tag['where'];
            }
        }

        $where["order_trash"] = isset($where["order_trash"]) ? $where["order_trash"] : 0;

        $field = !empty($tag['field']) ? $tag['field']:'*';
        $order = !empty($tag['order']) ? $tag['order'] : "add_time DESC";

        $data['total'] = $this->groupView->where($where)->count();

        $this->groupView->field($field)->where($where)->order($order);
        if (empty($pageSize)) {
            if (!empty($tag['limit'])) {
                $this->groupView->limit($tag['limit']);
            }
        } else {
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->groupView->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }

        $rs = $this->groupView->select();

        $data['data'] = array();
        if (!empty($rs)) {
            foreach ($rs as $k=>$v) {
                $goods = array(
                    'tf_id'=>$v['goods_id'],
                    'tf_code'=>$v['goods_sn'],
                    'tf_name'=>$v['goods_name'],
                    'price'=>$v['goods_price'],
                    'number'=>$v['order_goods_number'],
                    'sku'=>json_decode($v['goods_sku'],true),
                    'sku_id'=>$v['goods_sku_id'],
                    'unit'=>$v['goods_unit'],
                    'thumb'=>$v['goods_thumb'],
                );
                $order = array(
                    'id'=>$v['order_id'],
                    'supplier_id'=>$v['supplier_id'],
                    'supplier_name'=>$v['supplier_name'],
                    'supplier_logo'=>$v['supplier_logo'],
                    'order_sn'=>$v['order_sn'],
                    'user_id'=>$v['user_id'],
                    'user_nickname'=>$v['user_nickname'],
                    'user_avatar'=>$v['user_avatar'],
                    'order_status'=>$v['order_status'],
                    'pay_status'=>$v['pay_status'],
                    'shipping_status'=>$v['shipping_status'],
                    'consignee'=>$v['consignee'],
                    'country'=>$v['country'],
                    'privince'=>$v['privince'],
                    'province_name'=>$v['province_name'],
                    'city'=>$v['city'],
                    'city_name'=>$v['city_name'],
                    'district'=>$v['district'],
                    'district_name'=>$v['district_name'],
                    'address'=>$v['address'],
                    'zipcode'=>$v['zipcode'],
                    'tel'=>$v['tel'],
                    'mobile'=>$v['mobile'],
                    'email'=>$v['email'],
                    'postscript'=>$v['postscript'],
                    'shipping_id'=>$v['shipping_id'],
                    'shipping_name'=>$v['shipping_name'],
                    'pay_id'=>$v['pay_id'],
                    'pay_name'=>$v['pay_name'],
                    'goods_amount'=>$v['order_goods_amount'],
                    'shipping_fee'=>$v['shipping_fee'],
                    'insure_fee'=>$v['insure_fee'],
                    'pay_fee'=>$v['pay_fee'],
                    'order_amount'=>$v['order_amount'],
                    'add_time'=>$v['add_time'],
                    'confirm_time'=>$v['confirm_time'],
                    'pay_time'=>$v['pay_time'],
                    'shipping_time'=>$v['shipping_time'],
                    'invoice_no'=>$v['invoice_no'],
                    'to_buyer'=>$v['to_buyer'],
                    'pay_note'=>$v['pay_note'],
                    'inv_type'=>$v['inv_type'],
                    'inv_content'=>$v['inv_content'],
                    'tax'=>$v['tax'],
                    'discount'=>$v['discount'],
                    'order_trash'=>$v['order_trash'],
                );
                $group = array(
                    'id'=>$v['group_id'],
                    'supplier_id'=>$v['supplier_id'],
                    'supplier_name'=>$v['supplier_name'],
                    'supplier_logo'=>$v['supplier_logo'],
                    'group_sn'=>$v['group_sn'],
                    'group_status'=>$v['group_status'],
                    'goods_number'=>$v['group_goods_number'],
                    'goods_amount'=>$v['group_goods_amount'],
                    'group_amount'=>$v['group_amount'],
                    'min_charge'=>$v['group_min_charge'],
                    'group_trash'=>$v['group_trash'],
                    'create_date'=>$v['group_create_date'],
                    'modify_date'=>$v['group_modify_date'],
                    'expire_date'=>$v['group_expire_date'],
                    'issource'=>$v['issource'],
                    'source_id'=>$v['source_id'],
                    'source_supplier_id'=>$v['source_supplier_id'],
                    'source_supplier_name'=>$v['source_supplier_name'],
                    'source_supplier_logo'=>$v['source_supplier_logo'],
                    'handover'=>$v['handover'],
                    'handover_time'=>$v['handover_time'],
                    'paid_user_number'=>$v['paid_user_number'],
                    'paid_goods_number'=>$v['paid_goods_number'],
                    'ordered_user_number'=>$v['ordered_user_number'],
                );
                $order['goods'] = $goods;
                $group['goods'] = $goods;
                $order['group'] = $group;
                $data['data'][] = $order;
                unset($goods);
                unset($group);
                unset($order);
            }
            unset($rs);
        }

        return $data;
    }

    function getOrdersPaged($tag = '', $pageSize = 20, $pagetpl = '', $tplName = 'default')
    {
        return $this->orders($tag, $pageSize, $pagetpl, $tplName);
    }

    function getOrdersNoPaged($tag = '')
    {
        $data = $this->orders($tag);
        return $data['data'];
    }


    function getOrder($where){
        $v = $this->groupView->where($where)->find();
        if (!empty($v)) {
            $group = array(
                'id'=>$v['group_id'],
                'supplier_id'=>$v['supplier_id'],
                'supplier_name'=>$v['supplier_name'],
                'supplier_logo'=>$v['supplier_logo'],
                'group_sn'=>$v['group_sn'],
                'group_status'=>$v['group_status'],
                'goods_number'=>$v['group_goods_number'],
                'goods_amount'=>$v['group_goods_amount'],
                'group_amount'=>$v['group_amount'],
                'min_charge'=>$v['group_min_charge'],
                'group_trash'=>$v['group_trash'],
                'create_date'=>$v['group_create_date'],
                'modify_date'=>$v['group_modify_date'],
                'expire_date'=>$v['group_expire_date'],
                'issource'=>$v['issource'],
                'source_id'=>$v['source_id'],
                'source_supplier_id'=>$v['source_supplier_id'],
                'source_supplier_name'=>$v['source_supplier_name'],
                'source_supplier_logo'=>$v['source_supplier_logo'],
                'handover'=>$v['handover'],
                'handover_time'=>$v['handover_time'],
                'paid_user_number'=>$v['paid_user_number'],
                'paid_goods_number'=>$v['paid_goods_number'],
                'ordered_user_number'=>$v['ordered_user_number'],
            );
            $goods = array(
                'tf_id'=>$v['goods_id'],
                'tf_code'=>$v['goods_sn'],
                'tf_name'=>$v['goods_name'],
                'price'=>$v['goods_price'],
                'number'=>$v['order_goods_number'],
                'sku'=>json_decode($v['goods_sku'],true),
                'sku_id'=>$v['goods_sku_id'],
                'unit'=>$v['goods_unit'],
                'thumb'=>$v['goods_thumb'],
            );
            $order = array(
                'id'=>$v['order_id'],
                'supplier_id'=>$v['supplier_id'],
                'supplier_name'=>$v['supplier_name'],
                'supplier_logo'=>$v['supplier_logo'],
                'order_sn'=>$v['order_sn'],
                'user_id'=>$v['user_id'],
                'user_nickname'=>$v['user_nickname'],
                'user_avatar'=>$v['user_avatar'],
                'order_status'=>$v['order_status'],
                'pay_status'=>$v['pay_status'],
                'shipping_status'=>$v['shipping_status'],
                'consignee'=>$v['consignee'],
                'country'=>$v['country'],
                'privince'=>$v['privince'],
                'province_name'=>$v['province_name'],
                'city'=>$v['city'],
                'city_name'=>$v['city_name'],
                'district'=>$v['district'],
                'district_name'=>$v['district_name'],
                'address'=>$v['address'],
                'zipcode'=>$v['zipcode'],
                'tel'=>$v['tel'],
                'mobile'=>$v['mobile'],
                'email'=>$v['email'],
                'postscript'=>$v['postscript'],
                'shipping_id'=>$v['shipping_id'],
                'shipping_name'=>$v['shipping_name'],
                'pay_id'=>$v['pay_id'],
                'pay_name'=>$v['pay_name'],
                'goods_amount'=>$v['order_goods_amount'],
                'shipping_fee'=>$v['shipping_fee'],
                'insure_fee'=>$v['insure_fee'],
                'pay_fee'=>$v['pay_fee'],
                'order_amount'=>$v['order_amount'],
                'add_time'=>$v['add_time'],
                'confirm_time'=>$v['confirm_time'],
                'pay_time'=>$v['pay_time'],
                'shipping_time'=>$v['shipping_time'],
                'invoice_no'=>$v['invoice_no'],
                'to_buyer'=>$v['to_buyer'],
                'pay_note'=>$v['pay_note'],
                'inv_type'=>$v['inv_type'],
                'inv_content'=>$v['inv_content'],
                'tax'=>$v['tax'],
                'discount'=>$v['discount'],
                'order_trash'=>$v['order_trash'],
            );
            $group['goods'] = $goods;
            $order['goods'] = $goods;
            $order['group'] = $group;
            return $order;
        }else{
            return null;
        }
    }

    public function createGroup($tf_code, $sku_id, $expire_date, $goods_number, $user_id, $address_id, $shipping_id, $postscript = '', $getdata = false)
    {
        $fields = array_diff($this->tfUnionService->unionModel->getDbFields(),array('describe',));
        $tf = $this->tfUnionService->getTf($tf_code, $fields);
        if(empty($tf)){
            $this->error = '当前面料无效！';
            return false;
        }
        if($tf['on_sale'] == 0){
            $this->error = '当前面料已下架！';
            return false;
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

        $data = array(
            'supplier_id'   => $tf['supplier_id'],
            'group_sn'      => $this->build_group_sn(),
            'group_status'  => 0,
            'goods_id'      => $tf['id'],
            'goods_sku_id'  => $sku['id'],
            'goods_sn'      => $tf['tf_code'],
            'goods_name'    => $tf['name'],
            'goods_thumb'   => $tf['thumb'],
            'goods_content' => json_encode($tf, 256),
            'goods_unit'    => $sku['group_unit'],
            'goods_price'   => $sku['group_price'],
            'goods_number'  => 0,   //已下单商品数量
            'goods_amount'  => 0.00,   //已下单商品总价
            'group_amount'  => 0.00,   //
            'min_charge'    => $sku['min_charge'],  //拼单需求最小值
            'expire_date'   => date('Y-m-d H:i:s', strtotime($expire_date)),
            'issource'      => $tf['source'],
            'source_id'     => $tf['source_id'],
            'source_supplier_id'=> $tf['source_supplier_id'],
            'handover'      => 0,
        );

        if ($getdata) {
            return $data;
        } else {
            $result = $this->groupModel->saveGroup($data);
            if ($result !== false) {
                $result = $this->createGroupOrder($result, $goods_number, $user_id, $address_id, $shipping_id, $postscript);
            }
            return $result;
        }
    }

    function createGroupOrder($group_id, $number, $user_id, $address_id, $shipping_id, $postscript = '', $getdata=false){
        $group = $this->groupModel
            ->where(array(
                'id'=>$group_id,
                'group_trash'=>0,
            ))
            ->find();
        if(empty($group)){
            $this->error = '当前拼单不存在！';
            return false;
        }
        if($group['group_status'] > 0){
            $this->error = '当前拼单已成团或无效！';
        }
        if(strtotime($group['expire_date']) <= time()){
            $this->error = '当前拼单已过期！';
        }

        //获取收货地址
        if (is_array($address_id)) {
            $address = $address_id;
        } else {
            $address = $this->addressModel->getAddress(array('user_id' => $user_id, 'address_id' => $address_id));
        }
        if (empty($address)) {
            $this->error = '未选择收货信息！';
        }

        //获取物流信息
        if (is_array($shipping_id)) {
            $shipping = $shipping_id;
        } else {
            $shipping = $this->shippingModel->getShipping(array('user_id' => $user_id, 'shipping_id' => $shipping_id));
        }
        if (empty($shipping)) {
            $this->error = '未选择配送方式！';
        }

        $sku = $this->tfUnionService->unionSkuModel->find($group['goods_sku_id']);

        $goods = array(
            'goods_id'      => $group['goods_id'],
            'goods_name'    => $group['goods_name'],
            'goods_sn'      => $group['goods_sn'],
            'goods_number'  => $number,
            'goods_price'   => $group['goods_price'],
            'goods_sku_id'  => $group['goods_sku_id'],
            'goods_color_code' => $sku['color_code'],
            'goods_sku'     => json_encode($sku, 256),
            'parent_id'     => 0,
            'goods_thumb'   => $group['goods_thumb'],
        );

        //商品总额
        $goods_amount = $goods['goods_price'] * $number;
        $order = array(
            'order_sn' => $this->build_order_no(), //订单号
            'user_id' => $user_id,
            'supplier_id' => $group['supplier_id'],
            'postscript' => $postscript, //订单附言，由客户提交
            'shipping_id' => $shipping['shipping_id'],    //配送方式
            'shipping_name' => $shipping['shipping_name'],  //配送方式代号
            'pay_id' => 0,       //支付方式，在选择支付方式的时候再修改
            'pay_name' => '',      //支付方式代号
            'goods_amount' => $goods_amount,   //商品总额
            'shipping_fee' => 0.00,    //运费，根据shipping_area的运费信息来计算，现阶段暂定为0
            'insure_fee' => 0.00,    //保价费，根据shipping表的insure字段来计算，现阶段暂定为0
            'pay_fee' => 0.00,    //支付方式费，根据payment的pay_fee来计算，现阶段暂定为0
            'order_amount' => $goods_amount,   //应付款
            'invoice_no' => '',  //发货单号
            'to_buyer' => '',  //给客户的留言
            'pay_note' => '',  //付款备注
            'inv_type' => '',  //发票类型
            'inv_payee' => '',  //发票抬头
            'inv_content' => '',  //发票内容
            'tax' => 0.00,    //税费
            'discount' => 0.00,    //折扣
            'order_status' => 0,   //订单状态；0，未确认；1，已确认；2，已取消；3，无效；4，退货；
            'shipping_status' => 0,   //配送状态；0，未发货；1，已发货；2，已收货；3，备货中
            'pay_status' => 0,   //支付状态；0，未付款；1，付款中；2，已付款
            'add_time' => time(),  //订单生成时间
            'confirm_time' => 0,       //订单确认时间
            'pay_time' => 0,       //支付时间
            'shipping_time' => 0,       //配送时间
            'order_type' => 2, //1米样 2拼单 3色卡
        );

        $order['order_amount'] = $this->order_amount($order);
        $order = array_merge($order, $address);   //添加收货信息
        $order['goods'][0] = $goods;
        $result = $this->orderModel->createOrder($order);
        if($result === false){
            $this->error = $this->orderModel->getError();
            return false;
        }
        $order_id = $result;
        $rlsModel = M('OrderGroupRls');
        $result = $rlsModel->add(array(
            'group_id'  => $group_id,
            'order_id'  => $order_id,
        ));
        if($result === false){
            $this->error = $rlsModel->getDbError();
            return false;
        }

        $result = $this->groupModel->saveGroup(array(
            'id'=>$group['id'],
            'goods_number'  => $group['goods_number'] + $goods['goods_number'],
            'goods_amount'  => $group['goods_amount'] + $group['goods_price'] * $goods['goods_number'],
            'group_amount'  => $group['group_amount'] + $group['goods_price'] * $goods['goods_number'],
        ));

        if($result === false){
            $this->error = $this->groupModel->getError();
        }else{
            $result = $order_id;
        }

        return $result;
    }

    public function saveGroup($data)
    {
        $result = $this->groupModel->create($data);
        if ($result !== false) {
            $result = isset($data[$this->groupModel->getPk()]) ?
                $this->groupModel->save():$this->groupModel->add();
            if ($result === false) {
                $this->error = $this->groupModel->getDbError();
            }
        }
        return $result;
    }

    public function saveOrder($data)
    {
        $result = $this->orderModel->create($data);
        if ($result !== false) {
            $result = isset($this->orderModel->data[$this->orderModel->getPk()]) ?
                $this->orderModel->save():$this->orderModel->add();
            if ($result === false) {
                $this->error = $this->orderModel->getDbError();
            }
        }
        return $result;
    }

    public function cancel($id){
        $order = $this->getOrder(array('order_id'=>$id));
        $result = $this->groupModel->saveGroup(array(
            'id'=>$order['group']['id'],
            'goods_number'=>$order['group']['goods_number'] - $order['goods']['number'],
            'goods_amount'=>$order['group']['goods_amount'] - $order['goods_amount'],
            'group_amount'=>$order['group']['group_amount'] - $order['goods_amount'],
        ));
        if($result === false){
            $this->error = '操作失败！';
            return false;
        }
        return $this->orderModel->cancel($id);
    }

    /**
     * 得到新订单号
     * @return  string
     */
    private function build_group_sn(){
        /* 选择一个随机的方案 */
        mt_srand((double) microtime() * 1000000);
        return date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    /**
     * 得到新订单号
     * @return  string
     */
    private function build_order_no()
    {
        /* 选择一个随机的方案 */
        mt_srand((double)microtime() * 1000000);
        return date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    /*
     * 获得商品总额
     */
    private function goods_amount($data)
    {
        $amount = 0.00;
        foreach ($data as $goods) {
            $amount += $goods['goods_price'] * $goods['goods_number'];
        }
        return $amount;
    }

    /*
     * 获得订单应付金额
     */
    private function order_amount($data)
    {
        $amount = $data['goods_amount']
            + $data['shipping_fee']
            + $data['insure_fee']
            + $data['pay_fee']
            + $data['tax']
            + $data['discount'];
        return $amount;
    }

    public function getError()
    {
        return $this->error;
    }

    function initPager($total, $pagesize, $pagetpl, $tplname){
        $page_param = C("VAR_PAGE");
        $page = new \Page($total,$pagesize);
        $page->setLinkWraper("li");
        $page->__set("PageParam", $page_param);
        $page->__set("searching", true);
        $pagesetting=array("listlong" => "5", "first" => "首页", "last" => "尾页", "prev" => "上一页", "next" => "下一页", "list" => "*", "disabledclass" => "");
        $page->SetPager($tplname, $pagetpl,$pagesetting);
        return $page;
    }
}