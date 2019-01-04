<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-06-07
 * Time: 16:35
 */

namespace Order\Service;

use Order\Model\OrderModel;
use Order\Service\DealTfOrderService;
use Order\Model\DeliveryModel;

class DealTfDeliveryService
{
    protected $orderModel;
    protected $orderService;
    protected $deliveryModel;
    protected $goodsModel;
    protected $deliveryView;

    public function __construct()
    {
        $this->orderService = new DealTfOrderService();
        $this->orderModel = new OrderModel();
        $this->deliveryModel = new DeliveryModel();
        $this->goodsModel = M('DeliveryGoods');
        $this->deliveryView = M('TfDeliveryView');
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
            if (isset($tag['source_supplier_id'])) {
                $where['source_supplier_id'] = $tag['source_supplier_id'];
            }
            if (isset($tag['where'])) {
                $where['_string'] = $tag['where'];
            }
        }

        $field = !empty($tag['field']) ? $tag['field']:'*';
        $order = !empty($tag['order']) ? $tag['order'] : "add_time DESC";

        $data['total'] = $this->deliveryView->where($where)->count();

        $this->deliveryView->field($field)->where($where)->order($order);
        if (empty($pageSize)) {
            if (!empty($tag['limit'])) {
                $this->deliveryView->limit($tag['limit']);
            }
        } else {
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->deliveryView->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }

        $data['data'] = $this->deliveryView->select();

        if (!empty($data['data'])) {
            foreach ($data['data'] as $k=>$v) {
                $data['data'][$k]['status_text'] = $this->deliveryModel->statuses[$v['status']];
                $goods = array(
                    'tf_id'=>$v['goods_id'],
                    'tf_code'=>$v['goods_sn'],
                    'tf_name'=>$v['goods_name'],
                    'number'=>$v['goods_number'],
                    'sku'=>json_decode($v['goods_sku'],true),
                );
                $data['data'][$k]['goods'] = $goods;
            }
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

    function getDelivery($id){
        $data = $this->deliveryView->find($id);
        if(!empty($data)){
            $goods = array(
                'tf_id'=>$data['goods_id'],
                'tf_code'=>$data['goods_sn'],
                'tf_name'=>$data['goods_name'],
                'number'=>$data['goods_number'],
                'sku'=>json_decode($data['goods_sku'],true),
            );
            $data['goods'] = $goods;
        }
        return $data;
    }

    function getDeliveryByOrder($orderId){
        $data = $this->deliveryView->where("order_id='{$orderId}'")->find();
        if(!empty($data)){
            $goods = array(
                'tf_id'=>$data['goods_id'],
                'tf_code'=>$data['goods_sn'],
                'tf_name'=>$data['goods_name'],
                'number'=>$data['goods_number'],
                'sku'=>json_decode($data['goods_sku'],true),
            );
            $data['goods'] = $goods;
        }
        return $data;
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