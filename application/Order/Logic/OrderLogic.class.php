<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-16
 * Time: 11:12
 */

namespace Order\Logic;


use Order\Model\OrderModel;

class OrderLogic extends OrderModel
{

    /**
     * 得到新订单号
     * @return  string
     */
    function build_order_no(){
        /* 选择一个随机的方案 */
        mt_srand((double) microtime() * 1000000);
        return date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    /*
     * 生成订单清单初始数据
     * @param int $user_id 用户id
     * @param int $supplier_id 供应商id，如果为数字则针对某个供应商的购物车商品，
     */
    function build_order_goods_data($user_id, $supplier_id=null, $is_colorcard=0){
        $where['user_id'] = $user_id;
        if(!is_null($supplier_id)){
            $where['supplier_id'] = $supplier_id;
        }
        $where['is_checked'] = 1;
        $where['is_colorcard'] = $is_colorcard;
        $goods = D('Cart/Cart')->where($where)->select();

        $data = array();
        foreach($goods as $item){
            $tf = D('Tf/Tf')->getTfBySkuId($item['goods_sku_id']);
            $data[] = array(
                'goods_id'      => $item['goods_id'],
                /*'goods_name'    => $item['goods_name'],
                'goods_sn'      => $item['goods_sn'],
                'goods_number'  => $item['goods_number'],
                'goods_price'   => $item['goods_price'],
                'goods_sku_id'  => $item['goods_sku_id'],
                'goods_sku'     => $item['goods_sku'],*/
                'goods_name'    => $tf['name'],
                'goods_sn'      => $tf['tf_code'],
                'goods_number'  => $item['goods_number'],
                'goods_price'   => $tf['sku']['sku_price'],
                'goods_sku_id'  => $item['goods_sku_id'],
                'goods_sku'     => json_encode($tf['sku'], 256),
                'parent_id'     => $item['parent_id'],
            );
        }
        return $data;
    }

    /*
     * 生成订单
     * @param int $user_id 用户id
     * @param int $address_id 收货地址id
     * @param int $shipping_id 配送方式id
     * @param string $postscript 订单附言
     * @param bool $getdata 不添加订单只返回预处理订单数据
     */
    function buildOrder($user_id,$supplier_id=0,$address_id,$shipping_id,$postscript='',$goods=array(),$order_type=1,$is_colorcard=0,$getdata=false){
        //获取收货地址
        if(is_array($address_id)){
            $address = $address_id;
        }else{
            $address = D('Address/Address')->getAddress(array('user_id'=>$user_id,'address_id'=>$address_id));
        }
        //获取物流信息
        if(is_array($shipping_id)){
            $shipping = $shipping_id;
        }else{
            $shipping = D('Shipping')->getShipping(array('user_id'=>$user_id,'shipping_id'=>$shipping_id));
        }
        //获取订单商品清单
        if(empty($goods)){
            $goods = $this->build_order_goods_data($user_id, $supplier_id, $is_colorcard);
        }
        //商品总额
        $goods_amount = $this->goods_amount($goods);
        $data = array(
            'order_sn'          => $this->build_order_no(), //订单号
            'user_id'           => $user_id,
            'supplier_id'       => $supplier_id,
            'postscript'        => $postscript, //订单附言，由客户提交
            'shipping_id'       => $shipping['shipping_id'],    //配送方式
            'shipping_name'     => $shipping['shipping_name'],  //配送方式代号
            'pay_id'            => 0,       //支付方式，在选择支付方式的时候再修改
            'pay_name'          => '',      //支付方式代号
            'goods_amount'      => $goods_amount,   //商品总额
            'shipping_fee'      => 0.00,    //运费，根据shipping_area的运费信息来计算，现阶段暂定为0
            'insure_fee'        => 0.00,    //保价费，根据shipping表的insure字段来计算，现阶段暂定为0
            'pay_fee'           => 0.00,    //支付方式费，根据payment的pay_fee来计算，现阶段暂定为0
            'order_amount'      => $goods_amount,   //应付款
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
            'is_colorcard'      => $is_colorcard,   //是否色卡
            'order_type'        => $order_type,
        );

        $data['order_amount'] = $this->order_amount($data);
        $data = array_merge($data, $address);   //添加收货信息

        $data['goods'] = $goods;

        if($getdata){
            //$this->create($data);
            //$data['goods'] = $goods;
            return $data;
        }else{
            return $this->addOrder($data);
        }
    }

    /*
     * 获得订单应付金额
     */
    function order_amount($data){
        $amount = $data['goods_amount']
            + $data['shipping_fee']
            + $data['insure_fee']
            + $data['pay_fee']
            + $data['tax']
            + $data['discount'];
        return $amount;
    }

    /*
     * 获得商品总额
     */
    function goods_amount($data){
        $amount = 0.00;
        foreach($data as $goods){
            $amount += $goods['goods_price'] * $goods['goods_number'];
        }
        return $amount;
    }

    /*
     * 生成色卡订单
     * @param int $id 色卡id
     * @param in $number 购买数量
     * @param int $address_id 收货地址id
     * @param int $shipping_id 配送方式id
     * @param string $postscript 订单附言
     * @param bool $getdata 不添加订单只返回预处理订单数据
     */
    function buildCardOrder($id, $number, $address_id, $shipping_id, $postscript = '',$getdata=false)
    {
        $card = D('Colorcard/Colorcard','Logic')->getCard($id);
        //检查色卡是否已定稿
        if($card['card_status'] < 20){
            $result['status'] = 0;
            $result['error'] = L('CARD_NOT_CONFIRM');
            return $result;
        }

        //根据色卡生成购物清单
        $goods[0] = array(
            'goods_id'      => $card['card_id'],
            'goods_name'    => $card['card_name'],
            'goods_sn'      => $card['card_code'],
            'goods_number'  => $number,
            'goods_price'   => $card['card_price'],
            'goods_sku'     => '',
            'parent_id'     => 0,
            'goods_thumb'   => $card['frontcover'],
        );

        $rs = $this->buildOrder($card['vend_id'],0,$address_id,$shipping_id,$postscript,$goods,3,1,$getdata);

        if($getdata === true){
            $result['data'] = $rs;
        }else{
            if($rs > 0){
                $result['status'] = 1;
                $result['order_id'] = $rs;
            }else{
                $result['status'] = 0;
                $result['error'] = $this->mGetErrorByCode($rs);
            }
        }
        return $result;
    }

    function buildEasyOrder($sku_id, $number, $user_id, $address_id, $shipping_id, $postscript = '',$getdata=false){
        $tf = D('Tf/Tf')->getTfBySkuId($sku_id);
        if(empty($sku_id) || empty($tf)){
            $this->error = L('NO_DATA_ERR');
            return false;
        }

        $goods[0] = array(
            'goods_id'      => $tf['id'],
            'goods_name'    => $tf['name'],
            'goods_sn'      => $tf['tf_code'],
            'goods_number'  => $number,
            'goods_price'   => $tf['selected_sku']['sku_price'],
            'goods_sku_id'  => $tf['selected_sku']['id'],
            'goods_color_code'  => $tf['selected_sku']['color_code'],
            'goods_sku'     => json_encode($tf['selected_sku']),
            'parent_id'     => 0,
            'goods_thumb'   => $tf['img']['thumb'],
        );

        $result = $this->buildOrder($user_id,$tf['vend_id'],$address_id,$shipping_id,$postscript,$goods,1,0,$getdata);

        return $result;
    }


    function buildCardEasyOrder($card_id, $number, $supplier_id, $address_id, $shipping_id, $postscript = '',$getdata=false){
        $card = D('Colorcard/Colorcard')->find($card_id);
        if(empty($card_id) || empty($card)){
            $this->error = L('NO_DATA_ERR');
            return false;
        }

        $goods[0] = array(
            'goods_id'      => $card['card_id'],
            'goods_name'    => $card['card_name'],
            'goods_sn'      => $card['card_code'],
            'goods_number'  => $number,
            'goods_price'   => $card['card_price'],
            'goods_sku_id'  => 0,
            'goods_sku'     => '',
            'parent_id'     => 0,
            'goods_thumb'   => $card['frontcover'],
        );

        $result = $this->buildOrder($supplier_id,0,$address_id,$shipping_id,$postscript,$goods,3,1,$getdata);

        return $result;
    }

    function getCardOrder($id){
        $data = $this->getOrder($id);
        return $data;
    }

    function check_access($order_id, $user_id){
        $where['order_id'] = $order_id;
        $where['user_id'] = $user_id;
        return $this->where($where)->count();
    }
}