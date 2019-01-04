<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-06-06
 * Time: 15:27
 */

namespace Order\Service;

use Order\Model\OrderModel;

class DealTfOrderService
{
    protected $orderModel;
    protected $viewModel;

    public function __construct()
    {
        $this->orderModel = new OrderModel();
        $this->viewModel = M('TfEasyorderView');
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
        $field = !empty($tag['field']) ? $tag['field']:'*';
        $order = !empty($tag['order']) ? $tag['order'] : "add_time DESC";

        $data['total'] = $this->viewModel->where($where)->count();

        $this->viewModel->field($field)->where($where)->order($order);
        if (empty($pageSize)) {
            if (!empty($tag['limit'])) {
                $this->viewModel->limit($tag['limit']);
            }
        } else {
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->viewModel->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }

        $data['data'] = $this->viewModel->select();

        if (!empty($data['data'])) {
            foreach ($data['data'] as $k=>$v) {
                $goods = array(
                    'tf_id'=>$v['goods_id'],
                    'tf_code'=>$v['goods_sn'],
                    'tf_name'=>$v['goods_name'],
                    'price'=>$v['goods_price'],
                    'number'=>$v['goods_number'],
                    'sku'=>json_decode($v['goods_sku'],true),
                    'sku_id'=>$v['goods_sku_id'],
                    'thumb'=>$v['goods_thumb'],
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

    function getOrder($id){
        $data = $this->viewModel->find($id);
        if (!empty($data)) {
            $goods = array(
                'tf_id'=>$data['goods_id'],
                'tf_code'=>$data['goods_sn'],
                'tf_name'=>$data['goods_name'],
                'price'=>$data['goods_price'],
                'number'=>$data['goods_number'],
                'sku'=>json_decode($data['goods_sku'],true),
                'sku_id'=>$data['goods_sku_id'],
                'thumb'=>$data['goods_thumb'],
            );
            $data['goods'] = $goods;
        }
        return $data;
    }

    /**
     * 转交代理订单给供应商
     * @param int $id 订单id
     * @return bool
     */
    public function handover($id){
        $order = $this->getOrder($id);
        if(empty($order)){
            $this->error = '该订单无效！';
            return false;
        }
        if($order['issource']){
            $this->error = '该订单非代理订单，不支持转交！';
            return false;
        }
        if($order['handover'] == 1){
            $this->error = '该订单已经转交给供应商，无需重复操作！';
            return false;
        }
        $result = M('OrderTf')
            ->where("order_id='{$id}'")
            ->save(array(
                'handover'=>1,
                'handover_time'=>date('Y-m-d H:i:s'),
            ));
        return $result;
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