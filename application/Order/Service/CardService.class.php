<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-04-20
 * Time: 17:29
 */

namespace Order\Service;
use Order\Model\OrderModel;


class CardService
{
    public $error;
    protected $orderModel;

    function __construct()
    {
        $this->orderModel = new OrderModel();
    }

    /**
     * 得到新订单号
     * @return  string
     */
    function buildOrderNo(){
        /* 选择一个随机的方案 */
        mt_srand((double) microtime() * 1000000);
        return date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    function isEntity($cardId){
        $count = M('Colorcard')->where("card_id=$cardId AND isentity=1")->count();
        return $count > 0;
    }

    //检查是否二次制作，条件：是实体色卡，之前已完成过订单
    function isSecondaryOrder($cardId){
        $orderModel = M('OrderInfo');
        $join1 = "INNER JOIN __ORDER_CARDS__ oc ON oc.order_id=o.order_id";
        $join2 = "INNER JOIN __COLORCARD__ c ON c.card_id=oc.card_id";
        $where['oc.card_id'] = $cardId;
        $where['c.isentity'] = 1;       //是实体色卡
        $where['o.order_status'] = 1;   //已确认订单
        $where['o.pay_status'] = 2;     //已付款订单
        $where['o.shipping_status'] = 2;//已收货订单
        $count = $orderModel->alias('o')->join($join1)->join($join2)
            ->where($where)->count();
        return $count > 0;
    }

    function getOffersByCard($cardId){
        $offerModel = M('ColorcardOffer');
        //$field = 'co.*,card.*,tpl.tpl_frontcover';
        $join1 = "INNER JOIN __COLORCARD__ card ON card.card_tpl=co.tpl_id AND card.tf_limit=co.tf_qty";
        //$join2 = "INNER JOIN __COLORCARD_TPL__ tpl ON ctp.tpl_id=co.tpl_id";
        $where['card.card_id'] = $cardId;
        $where['card.isentity'] = 1;
        $data = $offerModel->alias('co')->join($join1)->where($where)->select();
        return $data;
    }

    function buildOrderData($memberId,$cardId,$offerId,$addressId,$shippingId,$postscript,$userId){
        $offer = D('Colorcard/Offer')->getRow($offerId);
        if(empty($offer)){
            $this->error = '传入数据错误';
            return false;
        }

        if($cardId > 0){
            $card = M('Colorcard')->where("card_id=$cardId AND isentity=1")->find();
            if($card['card_tpl'] != $offer['tpl_id']){
                $this->error = '传入数据错误';
                return false;
            }
            if(empty($card)){
                $this->error = '该色卡不是实体色卡！';
                return false;
            }
            //根据初次或二次定价
            $isSecondary = $this->isSecondaryOrder($cardId);
            if($isSecondary){
                $offerPrice = $offer['next_offer'];
            }else{
                $offerPrice = $offer['init_offer'];
            }
        }else{
            $offerPrice = $offer['init_offer'];
        }

        //获取收货地址
        if(is_array($addressId)){
            $address = $addressId;
        }else{
            $address = D('Address/Address')->getAddress(array('user_id'=>$userId,'address_id'=>$addressId));
        }
        unset($address['user_id']);
        //获取物流信息
        if(is_array($shippingId)){
            $shipping = $shippingId;
        }else{
            $shipping = D('Shipping')->getShipping(array('shipping_id'=>$shippingId));
        }
        $data = array(
            'order_sn'          => $this->buildOrderNo(), //订单号
            'user_id'           => $memberId,
            'supplier_id'       => 0,
            'postscript'        => $postscript, //订单附言，由客户提交
            'shipping_id'       => $shipping['shipping_id'],    //配送方式
            'shipping_name'     => $shipping['shipping_name'],  //配送方式代号
            'pay_id'            => 0,       //支付方式，在选择支付方式的时候再修改
            'pay_name'          => '',      //支付方式代号
            'goods_amount'      => $offerPrice*$offer['print_qty'],   //商品总额
            'shipping_fee'      => 0.00,    //运费，根据shipping_area的运费信息来计算，现阶段暂定为0
            'insure_fee'        => 0.00,    //保价费，根据shipping表的insure字段来计算，现阶段暂定为0
            'pay_fee'           => 0.00,    //支付方式费，根据payment的pay_fee来计算，现阶段暂定为0
            'order_amount'      => $offerPrice*$offer['print_qty'],   //应付款
            'invoice_no'        => '',  //发货单号
            'to_buyer'          => '',  //给客户的留言
            'pay_note'          => '',  //付款备注
            'inv_type'          => '',  //发票类型
            'inv_payee'         => '',  //发票抬头
            'inv_content'       => '',  //发票内容
            'tax'               => 0.00,    //税费
            'discount'          => 0.00,    //折扣
            'order_status'      => 0,   //订单状态；0，未确认；1，已确认；2，已取消；3，无效；4，退货；
            'shipping_status'   => 0,   //配送状态；0，未发货；1，已发货；2，已收货；3，备货中
            'pay_status'        => 0,   //支付状态；0，未付款；1，付款中；2，已付款
            'add_time'          => time(),  //订单生成时间
            'confirm_time'      => 0,       //订单确认时间
            'pay_time'          => 0,       //支付时间
            'shipping_time'     => 0,       //配送时间
            'is_colorcard'      => 1,   //是否色卡
            'order_type'        => 4,   //实体色卡
        );
        $data['order_amount'] = $this->orderAmount($data);
        $data = array_merge($data, $address);   //添加收货信息
        $data['goods'][0] = array(
            'goods_id'  => $offer['tpl_id'],
            'goods_name'=> $offer['tpl_name'],
            'goods_sn'  => '',
            'goods_number'  => $offer['print_qty'],
            'goods_price'   => $offerPrice,
            'goods_sku' => json_encode($offer, 256),
            'parent_id' => 0,
            'goods_sku_id'  => $offer['id'],
            'goods_thumb'   => $offer['tpl_thumb'],
        );
        $data['card_id'] = $cardId;
        $data['offer_id'] = $offerId;

        return $data;
    }

    function createOrder($data){
        $orderModel = D('Order/Order');
        $result = $orderModel->addOrder($data);
        if($result === false){
            $this->error = $this->getError();
            return false;
        }
        $orderId = $result;
        $orderCard = array(
            'order_id'  => $orderId,
            'offer_id'  => $data['offer_id'],
            'card_id'   => $data['card_id'],
        );
        $rlsModel = M('OrderCards');
        $result = $rlsModel->add($orderCard);
        if($result === false){
            $this->error = $rlsModel->getDbError();
            return false;
        }
        return $orderId;
    }

    /*
     * 获得订单应付金额
     */
    function orderAmount($data){
        $amount = $data['goods_amount']
            + $data['shipping_fee']
            + $data['insure_fee']
            + $data['pay_fee']
            + $data['tax']
            + $data['discount'];
        return $amount;
    }

    /*
     * 获取未删除的记录
     * @param int $currentPage 当前分页
     * @param in $pageSize 分页记录数
     * @return array
     */
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
            if (isset($tag['where'])) {
                $where['_string'] = $tag['_string'];
            }
        }
        $where['order_type'] = 4;
        $where["order_trash"] = isset($where["order_trash"]) ? $where["order_trash"] : 0;

        $alias = 'o';
        $field = !empty($tag['field']) ? $tag['field'] : "$alias.*,biz.biz_name";
        $order = !empty($tag['order']) ? $tag['order'] : "$alias.add_time DESC";

        foreach ($where as $key => $value) {
            $ignore_fields = array('field', 'order', 'group', 'limit', 'subData', 'join');
            if (in_array($key, $ignore_fields)) {
                unset($where[$key]);
                continue;
            }
            if (strpos($key, '.') === false) {
                $where["$alias.$key"] = $value;
                unset($where[$key]);
            }
        }

        $join1 = "LEFT JOIN __BIZ_MEMBER__ biz ON biz.id=$alias.user_id";
        $join2 = "INNER JOIN __ORDER_CARDS__ oc ON oc.order_id=$alias.order_id";
        $field .= ",oc.offer_id,oc.card_id";

        $join_areap = "LEFT JOIN __AREAS__ province ON province.id = $alias.province";
        $join_areac = "LEFT JOIN __AREAS__ city ON city.id = $alias.city";
        $join_aread = "LEFT JOIN __AREAS__ district ON district.id = $alias.district";
        $field .= ",province.name as province_name,city.name as city_name,district.name as district_name";

        $data['total'] = $this->orderModel->alias($alias)
            ->join($join1)->join($join2)->where($where)->count();

        $this->orderModel->alias($alias)->field($field)
            ->join($join1)->join($join2)->where($where)->order($order);
        $this->orderModel->join($join_areap)->join($join_areac)->join($join_aread);
        if (empty($pageSize)) {
            if (!empty($tag['limit'])) {
                $this->orderModel->limit($tag['limit']);
            }
        } else {
            $page = $this->orderModel->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->orderModel->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }

        $rs = $this->orderModel->select();

        if (!empty($rs)) {
            $ids = array();
            $cardIds = array();
            $data['data'] = $rs;
            $tmp = array();
            foreach ($data['data'] as $row) {
                array_push($ids, $row['order_id']);
                if($row['card_id'] > 0){
                    array_push($cardIds, $row['card_id']);
                }
            }

            $tmpCards = array();
            if(!empty($cardIds)){
                $cards = M('Colorcard')->where(array('card_id'=>array('IN',$cardIds)))
                    ->select();
                foreach($cards as $card){
                    $tmpCards[$card['card_id']] = $card;
                }
            }

            $goods = D('OrderGoods')
                ->where(array('order_id' => array('IN', $ids)))
                ->select();
            foreach ($goods as $g) {
                $g['goods_sku'] = json_decode($g['goods_sku'], true);
                $tmp[$g['order_id']]['goods'][] = $g;
            }
            foreach($data['data'] as $key=>$order){
                $data['data'][$key]['status'] = $this->orderModel->getRealStatus(
                    $order['order_status'],
                    $order['pay_status'],
                    $order['shipping_status']
                );
                $data['data'][$key]['goods'] = $tmp[$order['order_id']]['goods'];
                if($order['card_id'] > 0){
                    $data['data'][$key]['cardinfo'] = $tmpCards[$order['card_id']];
                }
            }
            //$data['data'] = $tmp;
        }

        return $data;
    }

    /*
     * 获得分页的订单列表
     */
    function getOrdersPaged($tag = '', $pageSize = 20, $pagetpl = '', $tplName = 'default')
    {
        return $this->orders($tag, $pageSize, $pagetpl, $tplName);
    }

    /*
     * 获得不分页的订单表
     */
    function getOrdersNoPaged($tag = '')
    {
        $data = $this->orders($tag);
        return $data['data'];
    }

    function getOrder($id){
        if(is_array($id)){
            $where = $id;
            $id = $where['order_id'];
        }else{
            $where['order_id'] = $id;
        }

        $join1 = 'LEFT JOIN __AREAS__ p ON p.id = o.province';
        $join2 = 'LEFT JOIN __AREAS__ c ON c.id = o.city';
        $join3 = 'LEFT JOIN __AREAS__ d ON d.id = o.district';
        $join4 = "LEFT JOIN __BIZ_MEMBER__ biz ON biz.id=o.user_id";
        $join5 = "INNER JOIN __ORDER_CARDS__ oc ON oc.order_id=o.order_id";

        foreach ($where as $key => $value) {
            if (strpos($key, '.') === false) {
                $where["o.$key"] = $value;
                unset($where[$key]);
            }
        }

        $data = $this->orderModel
            ->field('o.*,oc.offer_id,oc.card_id,p.name as province_name,c.name as city_name,d.name as district_name,
            biz.biz_name as supplier_name')
            ->alias('o')
            ->join($join1)->join($join2)->join($join3)->join($join4)->join($join5)
            ->where($where)->find();
        $goods = D('OrderGoods')->where(array('order_id'=>$id))->select();
        foreach($goods as $key=>$row){
            $row['goods_sku'] = json_decode($row['goods_sku'], true);
            $data['goods'][] = $row;
        }
        if($data['card_id'] > 0){
            $data['cardinfo'] = M('Colorcard')->find($data['card_id']);
        }
        $data['status'] = $this->orderModel->getRealStatus(
            $data['order_status'],
            $data['pay_status'],
            $data['shippint_status']
        );
        return $data;
    }

    function buildEntityCard($orderId){
        $order = $this->getOrder($orderId);
        if($order['card_id'] > 0){
            $this->error = '这个订单已经存在色卡了！';
            return false;
        }
        $goods = $order['goods'][0];
        if(!$goods['goods_id']){
            $this->error = '订单数据有误：错误的商品清单';
            return false;
        }

        if(!$order['offer_id']){
            $this->error = '订单数据有误：错误的报价信息';
            return false;
        }

        $offer = M('ColorcardOffer')->find($order['offer_id']);

        if(empty($offer)){
            $this->error = '找不到报价信息';
            return false;
        }

        $tpl = M('ColorcardTpl')->find($order['goods'][0]['goods_id']);

        if(empty($tpl)){
            $this->error = '找不到色卡模板信息';
            return false;
        }

        $cardModel = D('Colorcard/Colorcard');

        $data = array(
            'supplier_id'   => $order['user_id'],
            'card_name'     => "【实体色卡】".$tpl['tpl_name']."（".$offer['tf_qty']."个面料）",
            'card_type'     => 1,
            'card_status'   => 0,
            'card_tpl'      => $tpl['tpl_id'],
            'frontcover'    => $tpl['tpl_frontcover'],
            'backcover'     => $tpl['tpl_backcover'],
            'bgmusic'       => $tpl['tpl_bgmusic'],
            'create_date'   => date('Y-m-d H:i:s'),
            'ispublic'      => 0,
            'isentity'      => 1,
            'tf_limit'      => $offer['tf_qty'],
        );

        $result = $cardModel->saveCard($data);

        if($result === false){
            $this->error = $cardModel->getError();
            return false;
        }

        M('OrderCards')->where("order_id=$orderId")->setField('card_id',$result);
        return $result;
    }

    function getError(){
        return $this->error;
    }

}