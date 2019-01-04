<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-12-10
 * Time: 17:31
 */

namespace Order\Model;


use Common\Model\CommonModel;

class DeliveryModel extends CommonModel
{
    protected $tableName = 'delivery_order';

    public $statuses = array('发货中','已发货');
    // public $statuses = array('发货中','已发货','已收货');

    //自动验证
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('delivery_sn', 'require', '生成发货单号错误！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        array('delivery_sn', '', '生成发货单号错误！', 1, 'unique', CommonModel:: MODEL_INSERT ),
        array('user_id', 'require', '用户身份错误！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        //array('address_id', 'require', '请完善收货人信息！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        //array('shipping_id', 'require', '请完善物流信息！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        //array('goods_amount', 'require', '商品总额有误！', 1, 'regex', CommonModel:: MODEL_BOTH ),
        array('order_amount', 'require', '订单金额有误！', 0, 'regex', CommonModel:: MODEL_BOTH ),
    );

    //自动完成
    protected $_auto = array(
        //array(填充字段,填充内容,填充条件,附加规则)
        array('add_time','time',CommonModel:: MODEL_INSERT,'function'),
        array('update_time','time',CommonModel:: MODEL_INSERT,'function'),
    );

    /**
     * 得到新发货单号
     * @return  string
     */
    function build_delivery_no(){
        /* 选择一个随机的方案 */
        mt_srand((double) microtime() * 1000000);
        return date('YmdHi') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }


    /*
     * 获取未删除的记录
     * @param int $currentPage 当前分页
     * @param in $pageSize 分页记录数
     * @return array
     */
    function deliveries($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
        }

        $field = !empty($tag['field']) ? $tag['field'] : 'delivery.*,info.shipping_time,user.nickname,user.user_login';
        $order = !empty($tag['order']) ? $tag['order'] : 'add_time DESC';

        $this->join('__ORDER_INFO__ info ON info.order_id = delivery.order_id','LEFT');
        $data['total'] = $this->alias('delivery')->where($where)->count();

        $this->alias('delivery')->field($field)->where($where)->order($order);
        $this->join('__ORDER_INFO__ info ON info.order_id = delivery.order_id','LEFT');
        //$this->join('__ORDER_TF__ otf ON otf.order_id=info.order_id','LEFT');
        $this->join('__USERS__ user ON user.id = delivery.action_user','LEFT');
        if(empty($pageSize)){
            if (!empty($tag['limit'])) {
                $this->limit($tag['limit']);
            }
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
        }
        $rs = $this->select();

        if(!empty($rs)){
            $uids = array();
            $ids = array();
            $tmp = array();
            foreach($rs as $row){
                $tmp[$row['delivery_id']] = $row;
                array_push($uids, $row['user_id']);
                array_push($ids, $row['delivery_id']);
            }
            $uids = array_unique($uids);
            $members = D('BizMember')->getMembersNoPaged(array('id'=>array('IN',$uids)));
            foreach($tmp as $key=>$row){
                $tmp[$key]['member'] = $members[$row['user_id']];
            }
            $goods = D('DeliveryGoods')->where(array('delivery_id'=>array('IN',$ids)))->select();
            foreach($goods as $g){
                $tmp[$g['delivery_id']]['goods'][] = $g;
            }
            $data['data'] = $tmp;
        }

        return $data;
    }

    /*
     * 获得分页列表
     */
    function getDeliveriesPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->deliveries($tag, $pageSize, $pagetpl, $tplName);
    }

    /*
     * 获得不分页的表
     */
    function getDeliveriesNoPaged($tag=''){
        $data = $this->deliveries($tag);
        return $data['data'];
    }

    function getDelivery($id){
        if(is_array($id)){
            $where = $id;
        }else{
            $where['delivery_id'] = $id;
        }
        $join1 = 'LEFT JOIN __AREAS__ p ON p.id = o.province';
        $join2 = 'LEFT JOIN __AREAS__ c ON c.id = o.city';
        $join3 = 'LEFT JOIN __AREAS__ d ON d.id = o.district';
        $data = $this
            ->field('o.*,p.name as province_name,c.name as city_name,d.name as district_name')
            ->alias('o')
            ->join($join1)
            ->join($join2)
            ->join($join3)
            ->where($where)
            ->find();
        $data['goods'] = D('DeliveryGoods')->where(array('delivery_id'=>$data['delivery_id']))->select();
        $data['member'] = D('BizMember')->getMember($data['user_id']);
        $data['order'] = D('Order')->getOrder($data['order_id']);
        return $data;
    }

    function getDeliveryByOrder($order_id){
        $where['order_id'] = $order_id;
        $data = $this->getDelivery($where);
        return $data;
    }

    function build_delivery_data($order_id,$invoice_no='',$action_user=0){
        $order = D('Order/Order')->getOrder($order_id);
        if($order){
            $data = array(
                'delivery_sn'   => $this->build_delivery_no(),
                'order_sn'      => $order['order_sn'],
                'order_id'      => $order['order_id'],
                'invoice_no'    => $invoice_no,
                'add_time'      => time(),
                'shipping_id'   => $order['shipping_id'],
                'shipping_name' => $order['shipping_name'],
                'user_id'       => $order['user_id'],
                'supplier_id'   => $order['supplier_id'],
                'action_user'   => $action_user,
                'consignee'     => $order['consignee'],
                'address'       => $order['address'],
                'province'      => $order['province'],
                'city'          => $order['city'],
                'district'      => $order['district'],
                'sign_building' => $order['sign_building'],
                'email'         => $order['email'],
                'zipcode'       => $order['zipcode'],
                'tel'           => $order['tel'],
                'mobile'        => $order['mobile'],
                'best_time'     => $order['best_time'],
                'postscript'    => $order['postscript'],
                'insure_fee'    => $order['insure_fee'],
                'shipping_fee'  => $order['shipping_fee'],
                'status'        => 0,
                'goods'         => array(),
            );
            foreach($order['goods'] as $v){
                $data['goods'][] = array(
                    'goods_id'  => $v['goods_id'],
                    'goods_name'=> $v['goods_name'],
                    'goods_sn'  => $v['goods_sn'],
                    'parent_id' => $v['parent_id'],
                    'send_number'=> $v['goods_number'],
                    'goods_sku' => $v['goods_sku'],
                );
            }
        }else{
            $data = array();
        }
        return $data;
    }

    function buildDelivery($data){
        $result = $this->create($data);
        if($result !== false){
            $result = $this->add();
            if($result !== false){
                $delivery_id = $result;
                $goods = $data['goods'];
                foreach($data['goods'] as $k=>$v){
                    $goods[$k]['delivery_id'] = $delivery_id;
                    $goods[$k]['goods_sku'] = json_encode($goods['goods_sku'], 256);
                }
                $goods_model = D('DeliveryGoods');
                $result = $goods_model->addAll($goods);

                //失败则删除发货单
                if($result !== false){
                    $result = $delivery_id;
                }else{
                    $this->error = $goods_model->getDbError();
                    $this->deleteDelivery($delivery_id);
                }
            }else{
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    function updateDelivery($data){
        $result = $this->create($data);
        if($result !== false){
            $result = $this->save();
            if($result === false){
                $this->error = $this->getDbError();
            }
        }

        return $result;
    }

    function deleteDelivery($id){
        $result= $this->delete($id);
        if($result !== false){
            $goods_model = D('DeliveryGoods');
            $result = $goods_model->where(array('delivery_id'=>$id))->delete();
            if($result === false){
                $this->error = $goods_model->getDbError();
            }
        }
        return $result;
    }

    function toDelivery($id,$invoice_no=''){
        $data = array(
            'delivery_id'   => $id,
            'invoice_no'    => $invoice_no,
            'status'        => 1,
        );
        $result =  $this->updateDelivery($data);
        if($result !== false){
            $delivery = $this->find($id);
            $data = array(
                'order_id'          => $delivery['order_id'],
                'shipping_status'   => 1,
                'shipping_time'     => time(),
                'invoice_no'        => $invoice_no,
            );
            $order_model = D('Order');
            $result = $order_model->updateOrder($data);
            if($result === false){
                $this->error = $order_model->getError();
            }
        }

        return $result;
    }

    function unship($id){
        $data['delivery_id'] = $id;
        $data['status'] = 0;
        $result = $this->updateDelivery($data);
        return $result;
    }

    function setError($error){
        $this->error = $error;
    }

}