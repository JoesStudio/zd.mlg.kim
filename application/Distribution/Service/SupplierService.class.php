<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-06-20
 * Time: 11:42
 */

namespace Distribution\Service;


class SupplierService
{
    public $supplierView;
    public function __construct()
    {
        $this->supplierView = M('TfPoolSupplierView');
    }

    public function rows($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
            if (isset($tag['type'])) {
                $where['type'] = $tag['type'];
            }
            if (isset($tag['num'])) {
                $where['dist_tf_num'] = $tag['num'];
            }
            if (isset($tag['where'])) {
                $where['_string'] = $tag['where'];
            }
        }

        $field = !empty($tag['field']) ? $tag['field'] : "*";
        $order = !empty($tag['order']) ? $tag['order'] : "id DESC";

        $data['total'] = $this->supplierView->where($where)->count();

        $this->supplierView->field($field)->where($where)->order($order);
        if(empty($pageSize)){
            if (!empty($tag['limit'])) {
                $this->supplierView->limit($tag['limit']);
            }
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->supplierView->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }
        $data['data'] = $this->supplierView->select();

        return $data;
    }

    public function getRowsPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->rows($tag, $pageSize, $pagetpl, $tplName);
    }

    public function getRowsNoPaged($tag=''){
        $data = $this->rows($tag);
        return $data['data'];
    }

    public function getSupplier($id){
        return $this->supplierView->where("id='{$id}'")->find();
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