<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-06-09
 * Time: 17:01
 */

namespace Order\Service;

use Tf\Service\TfUnionService;
use Order\Model\OrderModel;
use Address\Model\AddressModel;
use Common\Model\ShippingModel;
use Order\Model\GroupModel;

class DealGroupService
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
                    'issource'=>$v['issource'],
                    'source_id'=>$v['source_id'],
                    'source_supplier_id'=>$v['source_supplier_id'],
                    'source_supplier_name'=>$v['source_supplier_name'],
                    'source_supplier_logo'=>$v['source_supplier_logo'],
                    'handover'=>$v['handover'],
                    'handover_time'=>$v['handover_time'],
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
                'issource'=>$v['issource'],
                'source_id'=>$v['source_id'],
                'source_supplier_id'=>$v['source_supplier_id'],
                'source_supplier_name'=>$v['source_supplier_name'],
                'source_supplier_logo'=>$v['source_supplier_logo'],
                'handover'=>$v['handover'],
                'handover_time'=>$v['handover_time'],
            );
            $group['goods'] = $goods;
            $order['goods'] = $goods;
            $order['group'] = $group;
            return $order;
        }else{
            return null;
        }
    }

    function getGroup($id){
        $groups = $this->getRowsNoPaged(array('group_id'=>$id));
        if(!empty($groups)){
            $group = $groups[0];
            return $group;
        }else{
            return null;
        }
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