<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-06-03
 * Time: 17:22
 */

namespace Order\Service;

use Tf\Service\TfUnionService;
use Order\Model\OrderModel;
use Address\Model\AddressModel;
use Common\Model\ShippingModel;

class TfOrderService
{
    protected $tfUnionService;
    protected $orderModel;
    protected $addressModel;
    protected $shippingModel;
    protected $tfOrderView;
    protected $error;

    public function __construct()
    {
        $this->tfUnionService = new TfUnionService();
        $this->orderModel = new OrderModel();
        $this->addressModel = new AddressModel();
        $this->shippingModel = new ShippingModel();
        $this->tfOrderView = M('TfEasyorderView');
    }


    /*
     * 获取未删除的记录
     * @param int $currentPage 当前分页
     * @param in $pageSize 分页记录数
     * @return array
     */
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


        $alias = 'o';
        $field = !empty($tag['field']) ? $tag['field'] : '*';
        $order = !empty($tag['order']) ? $tag['order'] : "add_time DESC";

        $data['total'] = $this->tfOrderView->where($where)->count();

        $this->tfOrderView->field($field)->where($where)->order($order);
        if (empty($pageSize)) {
            if (!empty($tag['limit'])) {
                $this->tfOrderView->limit($tag['limit']);
            }
        } else {
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->tfOrderView->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }

        $data['data'] = $this->tfOrderView->select();

        if (!empty($data['data'])) {
            foreach ($data['data'] as $k => $v) {
                $goods = array(
                    'tf_id' => $v['goods_id'],
                    'tf_code' => $v['goods_sn'],
                    'tf_name' => $v['goods_name'],
                    'price' => $v['goods_price'],
                    'number' => $v['goods_number'],
                    'sku' => json_decode($v['goods_sku'], true),
                    'sku_id' => $v['goods_sku_id'],
                    'thumb' => $v['goods_thumb'],
                );
                $data['data'][$k]['goods'] = $goods;
            }
        }

        return $data;
    }

    /*
     * 获得分页的订单列表
     */
    function getRowsPaged($tag = '', $pageSize = 20, $pagetpl = '', $tplName = 'default')
    {
        return $this->rows($tag, $pageSize, $pagetpl, $tplName);
    }

    /*
     * 获得不分页的订单表
     */
    function getRowsNoPaged($tag = '')
    {
        $data = $this->rows($tag);
        return $data['data'];
    }

    function getOrder($id)
    {
        if (is_numeric($id)) {
            $where['id'] = $id;
        } elseif (is_array($id)) {
            $where = $id;
        } else {
            $where = array();
        }
        $data = $this->tfOrderView->where($where)->find();

        if (!empty($data)) {
            $goods = array(
                'tf_id' => $data['goods_id'],
                'tf_code' => $data['goods_sn'],
                'tf_name' => $data['goods_name'],
                'price' => $data['goods_price'],
                'number' => $data['goods_number'],
                'sku' => json_decode($data['goods_sku'], true),
                'sku_id' => $data['goods_sku_id'],
                'thumb' => $data['goods_thumb']
            );
            $data['goods'] = $goods;
        }
        return $data;
    }

    public function buildEasyOrder($tfCode, $sku_id, $number, $user_id, $address_id, $shipping_id, $postscript = '', $getdata = false)
    {
        $tf = $this->tfUnionService->getTf($tfCode);
        if (empty($tf)) {
            $this->error = L('NO_DATA_ERR');
            return false;
        }

        $sku = $this->tfUnionService->unionSkuModel
            ->where(array(
                'original' => $tf['source'],
                'id' => $sku_id,
                'tf_id' => $tf['id'],
            ))
            ->find();
        if (empty($sku)) {
            $this->error = L('NO_DATA_ERR');
            return false;
        }

        $goods[0] = array(
            'goods_id' => $tf['id'],
            'goods_name' => $tf['name'],
            'goods_sn' => $tf['tf_code'],
            'goods_number' => $number,
            'goods_price' => $sku['sku_price'],
            'goods_sku_id' => $sku['id'],
            'goods_color_code' => $sku['color_code'],
            'goods_sku' => json_encode($sku),
            'parent_id' => 0,
            'goods_thumb' => $tf['thumb'],
        );

        $orderTf = array(
            'issource' => $tf['source'],
            //'tf_id' => $tf['id'],
            'supplier_id' => $tf['supplier_id'],
            //'source_id' => $tf['source'] ? $tf['id'] : $tf['source_id'],
            'source_supplier_id' => $tf['source_supplier_id'],
        );

        $result = $this->buildTfOrder($orderTf, $goods, $user_id, $tf['supplier_id'], $address_id, $shipping_id, $postscript, $getdata);

        return $result;
    }

    //代客找版订单创建
    public function buildAgencyFindEasyOrder($paymentData) {

        //商品总额
        $data = array(
            'order_sn' => $this->build_order_no(), //订单号
            'user_id' => $paymentData['uid'],
            'supplier_id' => 0,
            'postscript' => 0, //订单附言，由客户提交
            'shipping_id' => '',    //配送方式
            'shipping_name' => '',  //配送方式代号
            'pay_id' => 0,       //支付方式，在选择支付方式的时候再修改
            'pay_name' => '',      //支付方式代号
            'goods_amount' => $paymentData['total_fee'],   //商品总额
            'shipping_fee' => '',    //运费，根据shipping_area的运费信息来计算，现阶段暂定为0
            'insure_fee' => 0.00,    //保价费，根据shipping表的insure字段来计算，现阶段暂定为0
            'pay_fee' => 0.00,    //支付方式费，根据payment的pay_fee来计算，现阶段暂定为0
            'order_amount' => $paymentData['total_fee'],   //应付款
            'invoice_no' => '',  //发货单号
            'to_buyer' => '',  //给客户的留言
            'pay_note' => $paymentData['apply_id'],  //付款备注
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
            'order_type' => 5, //1米样 2拼单 3色卡 5 代客找版
        );

        return $this->createAgencyFindOrder($data);
    }

    public function createAgencyFindOrder($data) {
        $orderModel = new OrderModel();

        $orderModel->startTrans();
            $result = $orderModel->add($data);
            if ($result === false) {
                $this->error = '保存订单信息失败！';
                return false;
            }

            $orderId = $result;

        $orderModel->commit();
        return $orderId;
    }

    public function buildColorboardEasyOrder($tfArr, $sku_id, $number, $user_id, $address_id, $shipping_id, $postscript = '', $getdata = false)
    {
//        return $tfCode;
        $tf = $this->tfUnionService->getTf($tfArr);

//        return $tf;
        if (empty($tf)) {
            $this->error = L('NO_DATA_ERR');
            return false;
        }

        $serviceFee = 5;
        $goods[0] = array(
            'goods_id' => $tf['id'],
            'goods_name' => $tf['name'],
            'goods_sn' => $tf['tf_code'],
            'goods_number' => $number,
            'goods_price' => 0,
            "goods_service_fee" => $serviceFee,
            'goods_sku_id' => 0,
            'goods_color_code' => 0,
            'goods_sku' => '',
            'parent_id' => 0,
            'goods_thumb' => json_decode($tf['img'], true)['photo'],
        );

        $orderTf = array(
            'issource' => $tf['source'],
            //'tf_id' => $tf['id'],
            'supplier_id' => $tf['supplier_id'],
            //'source_id' => $tf['source'] ? $tf['id'] : $tf['source_id'],
            'source_supplier_id' => $tf['source_supplier_id'],
        );

//        return 3333;
        $result = $this->buildTfOrder($orderTf, $goods, $user_id, $tf['supplier_id'], $address_id, $shipping_id, $postscript, $getdata);

        return $result;
    }

    /*
     * 生成订单
     * @param int $user_id 用户id
     * @param int $address_id 收货地址id
     * @param int $shipping_id 配送方式id
     * @param string $postscript 订单附言
     * @param int $order_type 1米样 2拼单 3色卡
     * @param bool $getdata 不添加订单只返回预处理订单数据
     */
    function buildTfOrder($orderTf, $goods, $user_id, $supplier_id = 0, $address_id, $shipping_id, $postscript = '', $getdata = false)
    {
        if (empty($goods)) {
            $this->error = '购物清单为空！';
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

        $tsfmType = I("post.colorboard");
        if ($tsfmType == 1) {
            $transportFee = 0;
        } else {
            //判断地址是否是广东省
            if ($address['province_name'] == "广东省") {
                $transportFee = 7;
            } else {
                $transportFee = 15;
            }
        }


        //商品总额
        $goods_amount = $this->goods_amount($goods);
        $data = array(
            'order_sn' => $this->build_order_no(), //订单号
            'user_id' => $user_id,
            'supplier_id' => $supplier_id,
            'postscript' => $postscript, //订单附言，由客户提交
            'shipping_id' => $shipping['shipping_id'],    //配送方式
            'shipping_name' => $shipping['shipping_name'],  //配送方式代号
            'pay_id' => 0,       //支付方式，在选择支付方式的时候再修改
            'pay_name' => '',      //支付方式代号
            'goods_amount' => $goods_amount,   //商品总额
            'shipping_fee' => $transportFee,    //运费，根据shipping_area的运费信息来计算，现阶段暂定为0
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
            'order_type' => 1, //1米样 2拼单 3色卡
        );

        $data['order_amount'] = $this->order_amount($data);
        $data = array_merge($data, $address);   //添加收货信息

        $data['goods'] = $goods;
        $data['tf'] = $orderTf;

        if ($getdata) {
            return $data;
        } else {
            return $this->createTfOrder($data);
        }
    }

    /*
     * 生成订单
     * @param int $user_id 用户id
     * @param int $address_id 收货地址id
     * @param int $shipping_id 配送方式id
     * @param string $postscript 订单附言
     * @param int $order_type 1米样 2拼单 3色卡
     * @param bool $getdata 不添加订单只返回预处理订单数据
     */
    function buildColorboardOrder($orderTf, $goods, $user_id, $supplier_id = 0, $address_id, $shipping_id, $postscript = '', $getdata = false)
    {
        if (empty($goods)) {
            $this->error = '购物清单为空！';
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

        //商品总额
//        $goods_amount = $this->goods_amount($goods);
        $data = array(
            'order_sn' => $this->build_order_no(), //订单号
            'user_id' => $user_id,
            'supplier_id' => $supplier_id,
            'postscript' => $postscript, //订单附言，由客户提交
            'shipping_id' => $shipping['shipping_id'],    //配送方式
            'shipping_name' => $shipping['shipping_name'],  //配送方式代号
            'pay_id' => 0,       //支付方式，在选择支付方式的时候再修改
            'pay_name' => '',      //支付方式代号
            'goods_amount' => 0,   //商品总额
            'shipping_fee' => $goods['transportFee'],    //运费，根据shipping_area的运费信息来计算，现阶段暂定为0
            'insure_fee' => 0.00,    //保价费，根据shipping表的insure字段来计算，现阶段暂定为0
            'pay_fee' => 0.00,    //支付方式费，根据payment的pay_fee来计算，现阶段暂定为0
            'order_amount' => $goods['totalFee'],   //应付款
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
            'order_type' => 3, //1米样 2拼单 3色卡
        );

        $data['order_amount'] = $this->order_amount($data);
        $data = array_merge($data, $address);   //添加收货信息

        $data['goods'] = $goods;
        $data['tf'] = $orderTf;

        if ($getdata) {
            return $data;
        } else {
            return $this->createColorboardOrder($data);
        }
    }

    private function createColorboardOrder($data)
    {

        $orderModel = new OrderModel();
        $goodsModel = M('OrderGoods');
        $orderTfModel = M('OrderTf');

        $orderModel->startTrans();
        if ($orderModel->create($data) !== false) {

            $result = $orderModel->add($data);
            if ($result === false) {
                $this->error = '保存订单信息失败！';
                return false;
            }

            $orderId = $result;
        } else {
            $this->error = $orderModel->getError();
            return false;
        }


        $orderTf = $data['tf'];
        $orderTf['order_id'] = $orderId;
        $orderTfModel->startTrans();
        if ($orderTfModel->create($orderTf) !== false) {
            $result = $orderTfModel->add($orderTf);
            if ($result === false) {
                $orderModel->rollback();
                $this->error = '保存订单信息失败！';
                return false;
            }
        } else {
            $orderModel->rollback();
            $this->error = $orderTfModel->getError();
            return false;
        }

        $orderModel->commit();
//        $goodsModel->commit();
        $orderTfModel->commit();

        return $orderId;
    }

    private function createTfOrder($data)
    {
        $orderModel = new OrderModel();
        $goodsModel = M('OrderGoods');
        $orderTfModel = M('OrderTf');

        $orderModel->startTrans();
        if ($orderModel->create($data) !== false) {
            $result = $orderModel->add($data);
            if ($result === false) {
                $this->error = '保存订单信息失败！';
                return false;
            }
            $orderId = $result;
        } else {
            $this->error = $orderModel->getError();
            return false;
        }


        $orderTf = $data['tf'];
        $orderTf['order_id'] = $orderId;
        $orderTfModel->startTrans();
        if ($orderTfModel->create($orderTf) !== false) {
            $result = $orderTfModel->add($orderTf);
            if ($result === false) {
                $orderModel->rollback();
                $this->error = '保存订单信息失败！';
                return false;
            }
        } else {
            $orderModel->rollback();
            $this->error = $orderTfModel->getError();
            return false;
        }

        //保存清单数据，任何出错都会导致回滚数据
        $goods = $data['goods'];
        $goodsModel->startTrans();
        foreach ($goods as $k => $v) {
            $v['order_id'] = $orderId;

            //检验goods数据
            $result = $goodsModel->create($v);
            if ($result === false) {
                $orderTfModel->rollback();
                $goodsModel->rollback();
                $orderModel->rollback();
                $this->error = '保存清单失败！';
                return false;
            }

            //保存goods
            $result = $goodsModel->add($v);
            if ($result === false) {
                $orderTfModel->rollback();
                $goodsModel->rollback();
                $orderModel->rollback();
                $this->error = '保存清单失败！';
                return false;
            }
        }


        $orderModel->commit();
        $goodsModel->commit();
        $orderTfModel->commit();

        return $orderId;
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
            $amount += $goods['goods_price'] * $goods['goods_number'] + $goods['goods_service_fee'];
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

    function initPager($total, $pagesize, $pagetpl, $tplname)
    {
        $page_param = C("VAR_PAGE");
        $page = new \Page($total, $pagesize);
        $page->setLinkWraper("li");
        $page->__set("PageParam", $page_param);
        $page->__set("searching", true);
        $pagesetting = array("listlong" => "5", "first" => "首页", "last" => "尾页", "prev" => "上一页", "next" => "下一页", "list" => "*", "disabledclass" => "");
        $page->SetPager($tplname, $pagetpl, $pagesetting);
        return $page;
    }


}