<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-02-27
 * Time: 17:57
 */

namespace Order\Model;


use Common\Model\CommonModel;

class GroupModel extends CommonModel
{
    protected $tableName = 'order_group';

    public $statuses = array('拼单中', '已成团', '已完成', '已关闭');

    const STATUS_WAITING    = 0;
    const STATUS_PREPARING  = 1;
    const STATUS_FINISHED   = 2;
    const STATUS_CLOSED     = 3;

    public $actions = array(
        'grouped'   => '成团',
        'prepare'   => '配货',
        'ship'      => '生成发货单',
        'unship'    => '设为未发货',
        'to_delivery'   => '去发货',
        'cancel'    => '取消',
    );

    //自动验证
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('group_sn', 'require', '生成拼单号错误！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        array('group_sn', '', '生成拼单号错误！', 1, 'unique', CommonModel:: MODEL_INSERT ),
        array('goods_id', 'require', '请选择面料！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        array('group_amount', 'require', '订单金额有误！', 0, 'regex', CommonModel:: MODEL_BOTH ),
    );

    //自动完成
    protected $_auto = array(
        //array(填充字段,填充内容,填充条件,附加规则)
        array('create_date','mGetDate',CommonModel:: MODEL_INSERT,'function'),
        array('modify_date','mGetDate',CommonModel:: MODEL_UPDATE,'function'),
    );



    /*
     * 获取未删除的记录
     * @param int $currentPage 当前分页
     * @param in $pageSize 分页记录数
     * @return array
     */
    function groups($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);

            if (isset($tag['group_status'])){
                $where['group_status'] = $tag['group_status'];
            }
            if (isset($tag['goods_id'])){
                $where['goods_id'] = $tag['goods_id'];
            }
            if (isset($tag['supplier_id'])){
                $where['supplier_id'] = $tag['supplier_id'];
            }
        }

        $alias = 'gr';
        $field = !empty($tag['field']) ? $tag['field'] : "$alias.*";
        $order = !empty($tag['order']) ? $tag['order'] : "$alias.create_date DESC";

        foreach ($where as $key => $value) {
            if(in_array($key, array('order','limit','field','join'))) continue;
            if (strpos($key, '.') === false) {
                $where["$alias.$key"] = $value;
                unset($where[$key]);
            }
        }

        $where["$alias.group_trash"] = isset($where["$alias.group_trash"]) ? $where["$alias.group_trash"]:0;

        $data['total'] = $this->alias($alias)->where($where)->count();

        $this->alias($alias)->field($field)->where($where)->order($order);
        if(empty($pageSize)){
            if (!empty($tag['limit'])) {
                $this->limit($tag['limit']);
            }
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }

        $rs = $this->select();

        if(!empty($rs)){
            foreach($rs as $group){
                $tag = array(
                    //'field' => 'o.order_id,o.goods_amount,o.add_time,o.pay_status,o.order_status,o.user_id,user.nickname as user_name',
                    'field' => 'o.*,user.nickname as user_name',
                    'order_type'    => 2,
                    'order_status'  => array('IN', '0,1'),
                    'rls.group_id'  => $group['id'],
                );
                $orders = D('Order/Order')->getOrdersNoPaged($tag);
                if ($orders) {
                    $group['paid_user_number'] = 0; //已付款用户数
                    $group['paid_goods_number'] = 0;//已付款商品数
                    foreach ($orders as $row) {
                        $row['goods_number'] = $row['goods'][0]['goods_number'];
                        if ($row['pay_status'] == 2){
                            $group['paid_user_number']++;
                            $group['paid_goods_number'] += $row['goods_number'];
                        }
                        $group['orders'][$row['order_id']] = $row;
                    }
                }

                $data['data'][$group['id']] = $group;
            }
        }
        return $data;
    }

    /*
     * 获得分页的订单列表
     */
    function getGroupsPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->groups($tag, $pageSize, $pagetpl, $tplName);
    }

    /*
     * 获得不分页的订单表
     */
    function getGroupsNoPaged($tag=''){
        $data = $this->groups($tag);
        return $data['data'];
    }

    public function getGroup($id)
    {
        if(is_array($id)){
            $where = $id;
            $id = $where['id'];
        }else{
            $where['id'] = $id;
        }

        $group = $this->where($where)->find();
        if ($group) {
            $tag = array(
                //'field' => 'o.order_id,o.goods_amount,o.add_time,o.pay_status,o.order_status,o.user_id,user.nickname as user_name',
                //'field' => 'o.*,user.nickname as user_name',
                'order_type'    => 2,
                'order_status'  => array('IN', '0,1'),
                'rls.group_id'  => $group['id'],
            );
            $orders = D('Order/Order')->getOrdersNoPaged($tag);
            if ($orders) {
                $group['paid_user_number'] = 0; //已付款用户数
                $group['paid_goods_number'] = 0;//已付款商品数
                foreach ($orders as $row) {
                    $row['goods_number'] = $row['goods'][0]['goods_number'];
                    if ($row['pay_status'] == 2){
                        $group['paid_user_number']++;
                        $group['paid_goods_number'] += $row['goods_number'];
                    }
                    $group['orders'][] = $row;
                }
            }
        }

        //可用操作
        $group['actions']['prepare'] = 0;   //备货
        $group['actions']['ship'] = 0;      //生成发货单
        $group['actions']['to_delivery'] = 0;//去发货
        foreach ($group['orders'] as $order) {
            if ($order['pay_status'] == 2) {
                if ($group['actions']['prepare'] == 0 && $order['shipping_status'] == 0) {
                    $group['actions']['prepare'] = 1;
                }
                if ($group['actions']['ship'] == 0 &&
                in_array($order['shipping_status'],array(0,3))) {
                    $group['actions']['ship'] = 1;
                }
                if ($group['actions']['to_delivery'] == 0 && $order['shipping_status'] == 4) {
                    $group['actions']['to_delivery'] = 1;
                }
            }
        }
        if ($group['actions']['prepare']) {
            $group['actions']['to_delivery'] = 0;
        }
        if ($group['actions']['ship']) {
            $group['actions']['to_delivery'] = 0;
        }

        return $group;
    }

    public function createGroup($sku_id, $expire_date, $goods_number, $user_id, $address_id, $shipping_id, $postscript = '', $getdata = false)
    {
        $tf = D('Tf/Tf')->getTfBySkuId($sku_id);
        if (empty($tf)) {
            $this->error = L('ERROR_REQUEST_DATA');
        }
        $sku = $tf['selected_sku'];

        $data = array(
            'supplier_id'   => $tf['vend_id'],
            'group_sn'      => $this->build_group_sn(),
            'group_status'  => 0,
            'goods_id'      => $tf['id'],
            'goods_sku_id'  => $sku['id'],
            'goods_sn'      => $tf['tf_code'],
            'goods_name'    => $tf['name'],
            'goods_thumb'   => $tf['img']['thumb'],
            'goods_content' => json_encode($tf, 256),
            'goods_unit'    => $sku['group_unit'],
            'goods_price'   => $sku['group_price'],
            'goods_number'  => $goods_number,
            'goods_amount'  => $sku['group_price']*$goods_number,
            'group_amount'  => $sku['group_price']*$goods_number,
            'min_charge'    => $sku['min_charge'],
            'expire_date'   => date('Y-m-d H:i:s', strtotime($expire_date)),
        );

        if ($getdata) {
            return $data;
        } else {
            $result = $this->saveGroup($data);
            if ($result !== false) {
                $result = $this->buildGroupOrder($result, $goods_number, $user_id, $address_id, $shipping_id, $postscript);
            }
            return $result;
        }
    }

    public function joinGroup($group_id, $goods_number, $user_id, $address_id, $shipping_id, $postscript = '')
    {
        $group = $this->getGroup($group_id);
        if(empty($group_id) || empty($group)){
            $this->error = L('NO_DATA_ERR');
            return false;
        }

        $group_number = $group['goods_number'] + $goods_number;
        $goods_amount = $group['goods_price'] * $group_number;
        $group_amount = $group['goods_price'] * $group_number;

        $data = array(
            'id'        => $group_id,
            'goods_number'  => $group_number,
            'goods_amount'  => $goods_amount,
            'group_amount'  => $group_amount,
        );

        $result = $this->saveGroup($data);
        if ($result !== false) {
            $result = $this->buildGroupOrder($group_id, $goods_number, $user_id, $address_id, $shipping_id, $postscript);
        }
        return $result;
    }

    function buildGroupOrder($group_id, $number, $user_id, $address_id, $shipping_id, $postscript = '', $getdata=false){
        $group = $this->getGroup($group_id);
        if(empty($group_id) || empty($group)){
            $this->error = L('NO_DATA_ERR');
            return false;
        }
        $tf = json_decode($group['goods_content'], true);

        $goods[0] = array(
            'goods_id'      => $group['goods_id'],
            'goods_name'    => $group['goods_name'],
            'goods_sn'      => $group['goods_sn'],
            'goods_number'  => $number,
            'goods_price'   => $group['goods_price'],
            'goods_sku_id'  => $group['goods_sku_id'],
            'goods_sku'     => json_encode($tf['selected_sku'], 256),
            'parent_id'     => 0,
            'goods_thumb'   => $group['goods_thumb'],
        );

        $order_model = D('Order/Order', 'Logic');
        $result = $order_model->buildOrder($user_id,$group['supplier_id'],$address_id,$shipping_id,$postscript,$goods,2,0,$getdata);
        if ($result !== false) {
            M('OrderGroupRls')->add(array(
                'group_id'  => $group_id,
                'order_id'  => $result,
            ));
        } else {
            $this->error = $order_model->getError();
        }

        return $result;
    }


    public function saveGroup($data)
    {
        $result = $this->create($data);
        if ($result !== false) {
            $result = isset($this->data[$this->getPk()]) ? $this->save():$this->add();
            if ($result === false) {
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }


    /**
     * 得到新订单号
     * @return  string
     */
    public function build_group_sn(){
        /* 选择一个随机的方案 */
        mt_srand((double) microtime() * 1000000);
        return date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    public function check_prepare_by_order($order_id)
    {
        $gr = M('TfGrouporderView')->where("order_id='{$order_id}'")->find();

        //已付款数量与下单数量相等，已付款数量达到最低消费
        if ($gr['paid_goods_number'] == $gr['group_goods_number']
            && $gr['paid_goods_number'] >= $gr['group_min_charge']) {
            $result = $this->where("id='{$gr['group_id']}'")->setField('group_status', self::STATUS_PREPARING);
        }
    }

    public function check_prepare($group_id)
    {
        $gr = M('TfGrouporderView')->where("group_id='{$group_id}'")->find();

        //已付款数量与下单数量相等，已付款数量达到最低消费
        if ($gr['paid_goods_number'] == $gr['group_goods_number']
            && $gr['paid_goods_number'] >= $gr['group_min_charge']) {
            $result = $this->where("id='{$gr['group_id']}")->setField('group_status', self::STATUS_PREPARING);
        }
    }

    public function check_receive_by_order($order_id)
    {
        $group_id = M('OrderGroupRls')->where("order_id=$order_id")->getField('group_id');
        $this->check_receive($group_id);
    }

    public function check_receive($group_id)
    {
        $group = $this->getGroup($group_id);
        //已付款数量与下单数量相等，已付款数量达到最低消费
        $all_received = true;
        foreach ($group['orders'] as $order) {
            if ($order['shipping_status'] != 2) {
                $all_received = false;
                break;
            }
        }
        if ($all_received) {
            $result = $this
                ->where("id=$group_id")
                ->setField('group_status', self::STATUS_FINISHED);
        }
    }
}