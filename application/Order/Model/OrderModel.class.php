<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-16
 * Time: 10:32
 */

namespace Order\Model;


use Common\Model\CommonModel;

class OrderModel extends CommonModel
{
    protected $tableName = 'order_info';

    public $statuses = array(
        'unpaid' => '待付款',
        'unshipped' => '待发货',
        'unreceived' => '待收货',
        'received' => '已完成',
        'closed' => '已关闭',
        'refunded' => '退款中',
    );

    //订单状态
    public $orderStatuses = array('未确认', '已确认', '已取消', '无效', '退货');
    //支付状态
    public $payStatuses = array('未付款', '付款中', '已付款');
    //配送状态
    public $shippingStatuses = array('未发货', '已发货', '已收货', '配货中', '发货中');

    //相应状态下允许的动作
    public $allow_act = array(
        'order' => array(
            array('confirm', 'pay', 'unpay', 'cancel', 'invalid', 'after_service','modify_price'),
            array('pay', 'unpay', 'prepare', 'ship', 'to_delivery', 'unship', 'receive', 'return', 'cancel', 'invalid', 'after_service','modify_price'),
            array(),
            array(),
            array('unreturn')
        ),
        'pay' => array(
            array('confirm', 'pay', 'cancel', 'invalid', 'after_service','modify_price'),
            array('pay', 'unpay', 'cancel', 'invalid', 'after_service','modify_price'),
            array('unpay', 'prepare', 'ship', 'to_delivery', 'unship', 'receive', 'return', 'cancel', 'invalid', 'after_service'),
        ),
        'ship' => array(
            array('confirm', 'pay', 'unpay', 'prepare', 'ship', 'cancel', 'invalid', 'after_service','modify_price'),
            array('unship', 'receive', 'return', 'after_service'),
            array('return', 'after_service'),
            array('pay', 'unpay', 'ship', 'cancel', 'invalid', 'after_service'),
            array('to_delivery'),
        )
    );

    //动作
    public $operation = array(
        //基本
        'confirm' => '确认',
        'cancel' => '取消',
        //'invalid'   => '无效',

        //付款
        'pay' => '付款',
        'unpay' => '设为未付款',

        //修改价格
        'modify_price' => '修改价格',
        
        //配送
        'prepare' => '配货',
        'ship' => '生成发货单',
        'to_delivery' => '去发货',
        'unship' => '未发货',
        'receive' => '已收货',
        //'return'    => '退货',

        //其他
        //'after_service'=> '售后',
        //'unreturn'  => '取消退货',
    );

    //自动验证
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('order_sn', 'require', '生成订单号错误！', 1, 'regex', CommonModel:: MODEL_INSERT),
        array('order_sn', '', '生成订单号错误！', 1, 'unique', CommonModel:: MODEL_INSERT),
        array('user_id', 'require', '用户身份错误！', 1, 'regex', CommonModel:: MODEL_INSERT),
        array('address_id', 'require', '请完善收货人信息！', 1, 'regex', CommonModel:: MODEL_INSERT),
        array('shipping_id', 'require', '请完善物流信息！', 1, 'regex', CommonModel:: MODEL_INSERT),
        //array('goods_amount', 'require', '商品总额有误！', 1, 'regex', CommonModel:: MODEL_BOTH ),
        array('order_amount', 'require', '订单金额有误！', 0, 'regex', CommonModel:: MODEL_BOTH),
    );

    //自动完成
    protected $_auto = array(
        //array(填充字段,填充内容,填充条件,附加规则)
        array('add_time', 'time', CommonModel:: MODEL_INSERT, 'function'),
    );


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
            if (isset($tag['supplier_id'])) {
                $where['supplier_id'] = $tag['supplier_id'];
            }
            if (isset($tag['order_type'])) {
                $where['order_type'] = $tag['order_type'];
            }
            if (isset($tag['where'])) {
                $where['_string'] = $tag['_string'];
            }
        }

        $is_colorcard = $where['is_colorcard'];
        $where['order_type'] = isset($tag['order_type']) ? $tag['order_type'] : 1;
        if ($is_colorcard) {
            $where['order_type'] = 3;
        }
        $order_type = $where['order_type'];
        $where["order_trash"] = isset($where["order_trash"]) ? $where["order_trash"] : 0;


        $alias = 'o';
        if (empty($tag['field'])) {
            if ($order_type == 3) {
                $field = "$alias.*,biz.biz_name as buyer_name";
            } elseif ($order_type == 2) {
                $field = "$alias.*,user.nickname as user_name,biz.biz_name as supplier_name,rls.group_id";
            } else {
                $field = "$alias.*,user.nickname as user_name,biz.biz_name as supplier_name";
            }
        } else {
            $field = $tag['field'];
        }
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


        if ($order_type == 3) {
            $join1 = "LEFT JOIN __BIZ_MEMBER__ biz ON biz.id=$alias.user_id";
            $this->join($join1);
        } elseif ($order_type == 2) {
            $join1 = "__ORDER_GROUP_RLS__ rls ON rls.order_id=$alias.order_id";
            $join2 = "__ORDER_GROUP__ gr ON gr.id=rls.group_id";
            $join3 = "LEFT JOIN __USER_INFO__ user ON user.user_id=$alias.user_id";
            $join4 = "LEFT JOIN __BIZ_MEMBER__ biz ON biz.id=$alias.supplier_id";
            $this->join($join1)->join($join2)->join($join3)->join($join4);
        } else {
            $join1 = "LEFT JOIN __USER_INFO__ user ON user.user_id=$alias.user_id";
            $join2 = "LEFT JOIN __BIZ_MEMBER__ biz ON biz.id=$alias.supplier_id";
            $this->join($join1)->join($join2);
        }

        $join_areap = "LEFT JOIN __AREAS__ province ON province.id = $alias.province";
        $join_areac = "LEFT JOIN __AREAS__ city ON city.id = $alias.city";
        $join_aread = "LEFT JOIN __AREAS__ district ON district.id = $alias.district";
        $field .= ",province.name as province_name,city.name as city_name,district.name as district_name";

        $data['total'] = $this->alias($alias)->where($where)->count();

        $this->alias($alias)->field($field)->where($where)->order($order);
        $this->join($join_areap)->join($join_areac)->join($join_aread);
        if ($order_type == 3) {
            $this->join($join1);
        } elseif ($order_type == 2) {
            $this->join($join1)->join($join2)->join($join3)->join($join4);
        } else {
            $this->join($join1)->join($join2);
        }
        if (empty($pageSize)) {
            if (!empty($tag['limit'])) {
                $this->limit($tag['limit']);
            }
        } else {
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }

        $rs = $this->select();

        if (!empty($rs)) {
            // $uids = array();
            // $sup_ids = array();
            $ids = array();
            $tmp = array();
            foreach ($rs as $row) {
                $tmp[$row['order_id']] = $row;
                // array_push($uids, $row['user_id']);
                // array_push($sup_ids, $row['supplier_id']);
                array_push($ids, $row['order_id']);
            }
            /*$uids = array_unique($uids);
            $sup_ids = array_unique($sup_ids);
            $users = D('Users')->getUsersNoPaged(array('id'=>array('IN',$uids)));
            $suppliers = D('BizMember')->getMembersNoPaged(array('id'=>array('IN',$sup_ids)));
            foreach($tmp as $key=>$row){
                $tmp[$key]['user'] = $users[$row['user_id']];
                $tmp[$key]['supplier'] = $suppliers[$row['supplier_id']];
            }*/
            $goods = D('OrderGoods')
                ->where(array('order_id' => array('IN', $ids)))
                ->select();
            foreach ($goods as $g) {
                $g['goods_sku'] = json_decode($g['goods_sku'], true);
                $tmp[$g['order_id']]['goods'][] = $g;
            }
            $data['data'] = $tmp;
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

    function getOrder($order_id)
    {
        if (is_array($order_id)) {
            $where = $order_id;
            $order_id = $where['order_id'];
        } else {
            $where['order_id'] = $order_id;
        }
        $join1 = 'LEFT JOIN __AREAS__ p ON p.id = o.province';
        $join2 = 'LEFT JOIN __AREAS__ c ON c.id = o.city';
        $join3 = 'LEFT JOIN __AREAS__ d ON d.id = o.district';
        $join4 = "LEFT JOIN __USER_INFO__ user ON user.user_id=o.user_id";
        $join5 = "LEFT JOIN __BIZ_MEMBER__ biz ON biz.id=o.supplier_id";

        foreach ($where as $key => $value) {
            if (strpos($key, '.') === false) {
                $where["o.$key"] = $value;
                unset($where[$key]);
            }
        }

        $data = $this
            ->field('o.*,p.name as province_name,c.name as city_name,d.name as district_name,
            user.nickname as user_name,biz.biz_name as supplier_name')
            ->alias('o')
            ->join($join1)
            ->join($join2)
            ->join($join3)
            ->join($join4)
            ->join($join5)
            ->where($where)
            ->find();
        $goods = D('OrderGoods')->where(array('order_id' => $order_id))->select();
        foreach ($goods as $key => $row) {
            $row['goods_sku'] = json_decode($row['goods_sku'], true);
            $data['goods'][$row['goods_id']] = $row;
        }
        $data['user'] = D('Users')->getUser($data['user_id']);
        $data['supplier'] = D('BizMember')->getMember($data['supplier_id']);
        return $data;
    }

    function addOrder($data)
    {
        $result = $this->create($data);
        if ($result !== false) {
            $result = $this->add();
            if ($result !== false) {
                $order_id = $result;
                $goods = $data['goods'];
                foreach ($data['goods'] as $k => $v) {
                    $goods[$k]['order_id'] = $order_id;
                }
                $goods_model = D('OrderGoods');
                $result = $goods_model->addAll($goods);

                //失败则删除订单
                if ($result === false) {
                    $this->error = $goods_model->getDbError();
                } else {
                    $result = $order_id;
                }
            } else {
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    function createOrder($data){
        return $this->addOrder($data);
    }

    function updateOrder($data)
    {
        $result = $this->create($data);

        if ($result !== false) {

            $result = $this->save();
            if ($result === false) {
                $this->error = $this->getDbError();
            }
        }

        return $result;
    }

    function deleteOrder($id)
    {
        $result = $this->delete($id);
        if ($result !== false) {
            $goods_model = D('OrderGoods');
            $result = $goods_model->where(array('order_id' => $id))->delete();
            if ($result === false) {
                $this->error = $goods_model->getDbError();
            }
        }
        return $result;
    }

    function getStatus($order_id)
    {
        if (is_array($order_id)) {
            $where = $order_id;
        } else {
            $where['order_id'] = $order_id;
        }
        $field = 'order_status,pay_status,shipping_status';
        $data = $this->field($field)->where($where)->find();
        return $data;
    }

    //确认订单
    function setConfirm($id)
    {
        $data['order_id'] = $id;
        $data['order_status'] = 1;
        $data['confirm_time'] = time();
        return $this->updateOrder($data);
    }

    //付款
    function setPaid($id, $time = null)
    {
        $order = $this->find($id);
        if (is_null($time)) $time = time();
        if ($order['order_status'] == 0) {
            $data['order_status'] = 1;
            $data['confirm_time'] = $time;
        }
        $data['order_id'] = $id;
        $data['pay_status'] = 2;
        $data['pay_time'] = $time;
        $result = $this->updateOrder($data);
        if ($result !== false) {
            $tallyModel = D('Account/Tally');
            switch ((int)$order['order_type']) {
                case 1:
                    //面料商入账到冻结资金
                    $orderTf = M('OrderTf')->where("order_id='{$id}'")->find();
                    if(empty($orderTf)){
                        $tallyResult = false;
                    }elseif((int)$orderTf['issource'] == 1){
                        $tallyResult = $tallyModel->tally($orderTf['supplier_id'], $order['order_amount'], 'NEW_ORDER', $id);
                    }elseif((int)$orderTf['issource'] == 0){
                        $tallyResult = $tallyModel->tally($orderTf['source_supplier_id'], $order['order_amount'], 'NEW_ORDER', $id);
                    }else{
                        $tallyResult = false;
                    }
                    break;
                case 2:
                    $group = M('OrderGroup')
                        ->field('g.*')
                        ->alias('g')
                        ->join('__ORDER_GROUP_RLS__ rls ON rls.group_id=g.id')
                        ->where("rls.order_id='{$id}'")->find();
                    if(empty($group)){
                        $tallyResult = false;
                    }elseif((int)$group['issource'] == 1){
                        $tallyResult = $tallyModel->tally($group['supplier_id'], $order['order_amount'], 'NEW_ORDER', $id);
                    }elseif((int)$group['issource'] == 0){
                        $tallyResult = $tallyModel->tally($group['source_supplier_id'], $order['order_amount'], 'NEW_ORDER', $id);
                    }else{
                        $tallyResult = false;
                    }
                    break;
                case 3:
                    //D('Account/Tally')->tally();
                    $tallyResult = true;
                    break;
                case 4:
                    //面料商扣除资金
                    $tallyResult = $tallyModel->tally($order['user_id'],$order['order_amount'],'PAY_CARDORDER', $id);
                    //面料馆入账到冻结资金
                    $tallyResult = $tallyResult && $tallyModel->tally(0, $order['order_amount'],'NEW_CARDORDER', $id);
                    break;
                case 5:
                    //代客找版付款资金处理
                    return $order;

                default:
                    $tallyResult = false;
            }
            if($tallyResult === false){
                return false;
            }
        }
        if ($result !== false && $order['order_type'] == 2) {

            D('Order/Group')->check_prepare_by_order($order['order_id']);
        }
        return $result;
    }

    //设为未付款
    function setUnpaid($id)
    {
        $data['order_id'] = $id;
        $data['pay_status'] = 0;
        $data['pay_time'] = time();
        return $this->updateOrder($data);
    }

    //配货
    function setPrepare($id)
    {
        $data['order_id'] = $id;
        $data['shipping_status'] = 3;
        return $this->updateOrder($data);
    }

    //已收货
    function setReceived($id)
    {
        $order = $this->find($id);
        if (empty($order)) {
            $this->error = '传入数据错误';
            return false;
        }
        $data['order_id'] = $id;
        $data['shipping_status'] = 2;
        $result = $this->updateOrder($data);

        if ($result !== false) {
            switch ($order['order_type']) {
                case 1:
                    //面料商解冻资金
                    $orderTf = M('OrderTf')->where("order_id='{$id}'")->find();
                    if(empty($orderTf)){
                        $result = false;
                    }elseif($orderTf['issource'] == 1){
                        $result = D('Account/Account')->restoreFromBlock($orderTf['supplier_id'], $order['order_amount']);
                    }elseif($orderTf['issource'] == 0){
                        $result = D('Account/Account')->restoreFromBlock($orderTf['source_supplier_id'], $order['order_amount']);
                    }else{
                        $result = false;
                    }
                    break;
                case 2:
                    $group = M('OrderGroup')
                        ->field('g.*')
                        ->alias('g')
                        ->join('__ORDER_GROUP_RLS__ rls ON rls.group_id=g.id')
                        ->where("rls.order_id='{$id}'")->find();
                    //面料商解冻资金
                    if(empty($group)){
                        $result = false;
                    }elseif($group['issource'] == 1){
                        $result = D('Account/Account')->restoreFromBlock($group['supplier_id'], $order['order_amount']);
                    }elseif($group['issource'] == 0){
                        $result = D('Account/Account')->restoreFromBlock($group['source_supplier_id'], $order['order_amount']);
                    }else{
                        $result = false;
                    }
                    break;
                case 3:
                    //D('Account/Tally')->tally();
                    $result = true;
                    break;
                case 4:
                    //面料馆账户解冻资金
                    $result = D('Account/Account')->restoreFromBlock(0, $order['order_amount']);
                    break;
                default:
                    $result = false;
            }
            if($result === false){
                return false;
            }
        }

        if ($result !== false && $order['order_type'] == 2) {
            D('Order/Group')->check_receive_by_order($id);
        }
        return $result;
    }

    //生成发货单
    function readyForShipment($id)
    {
        $data['order_id'] = $id;
        $data['shipping_status'] = 4;
        return $this->updateOrder($data);
    }

    //设置为未发货/发货中
    function unship($id)
    {
        $count = D('Delivery')->where(array('order_id' => $id))->count();
        $data['order_id'] = $id;
        //如果存在发货单则设为发货中
        $data['shipping_status'] = $count > 0 ? 4 : 0;
        if ($count == 0) {
            $data['invoice_no'] = '';
        }
        $data['shipping_time'] = 0;
        $result = $this->updateOrder($data);
        return $result;
    }

    //取消
    function cancel($id)
    {
        if($id == 0){
            $this->error = '传入数据错误';
            return false;
        }
        $order = $this->find($id);
        if(empty($order)){
            $this->error = '传入数据错误';
            return false;
        }
        $status = $this->getRealStatus($order['order_status'],
            $order['pay_status'],
            $order['shipping_status']);
        if(!in_array($status,array('unpaid','unshipped','unreceived'))){
            $this->error = '当前订单状态不允许取消！';
            return false;
        }
        $data['order_id'] = $id;
        $data['order_status'] = 2;
        $result = $this->updateOrder($data);
        if($result !== false){
            //需要退钱
            if(in_array($status,array('unshipped','unreceived'))){
                switch ($order['order_type']) {
                    case 1:
                        //微信退钱，从冻结资金退回
                        break;
                    case 2:
                        //D('Account/Tally')->tally();
                        break;
                    case 4:
                        $this->refundColorcard($id);
                        break;
                    default:
                }
            }
        }
        return $result;
    }

    function refundColorcard($id){
        $order = $this->find($id);
        if($order){
            $payment = M('Payment')->find($order['pay_id']);
            if(!empty($payment)){
                $tallyModel = D('Account/Tally');
                switch ($payment['pay_code']) {
                    case 'BALANCE':
                        $tallyModel->tally(0,$order['order_amount'],'REFUND_CARDORDER');
                        $tallyModel->tally($order['user_id'],$order['order_amount'],'GET_REFUND_CARDORDER');
                        break;
                    case 'WECHAT':
                        break;
                    default:
                }
            }
        }
    }

    function trash($id)
    {
        $data['order_id'] = $id;
        $data['order_trash'] = 1;
        return $this->updateOrder($data);
    }

    function setError($error)
    {
        $this->error = $error;
    }

    function getRealStatus($o,$p,$s,$text=false){
        if($o < 2 && $p != 2){
            $result = 'unpaid';
        }elseif($o == 1 && $p == 2){
            if(in_array($s, array(0,3,4))){
                $result = 'unshipped';
            }elseif($s == 1){
                $result = 'unreceived';
            }elseif($s == 2){
                $result = 'received';
            }else{
                $result = null;
            }
        }elseif(in_array($o, array(2,3))){
            $result = 'closed';
        }elseif($o == 4){
            $result = 'refunded';
        }else{
            $result = null;
        }

        if($text){
            if(!is_null($result)){
                return $this->statuses[$result];
            }else{
                return '未知状态';
            }
        }else{
            return $result;
        }
    }


}