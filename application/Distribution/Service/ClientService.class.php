<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-06-22
 * Time: 18:13
 */

namespace Distribution\Service;


class ClientService
{
    public $clientView;
    public function __construct()
    {
        $this->clientView = M('DistributionClientView');
    }

    public function rows($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
            if (isset($tag['supplier_id'])) {
                $where['supplier_id'] = $tag['supplier_id'];
            }
            if (isset($tag['distributor_id'])) {
                $where['distributor_id'] = $tag['distributor_id'];
            }
            if (isset($tag['status'])) {
                $where['status'] = $tag['status'];
            }
            if (isset($tag['level'])) {
                $where['level'] = $tag['level'];
            }
            if (isset($tag['where'])) {
                $where['_string'] = $tag['where'];
            }
        }

        $field = !empty($tag['field']) ? $tag['field'] : "*";
        $order = !empty($tag['order']) ? $tag['order'] : "id DESC";

        $data['total'] = $this->clientView->where($where)->count();

        $this->clientView->field($field)->where($where)->order($order);
        if(empty($pageSize)){
            if (!empty($tag['limit'])) {
                $this->clientView->limit($tag['limit']);
            }
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->clientView->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }
        $data['data'] = $this->clientView->select();

        return $data;
    }

    public function getRowsPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->rows($tag, $pageSize, $pagetpl, $tplName);
    }

    public function getRowsNoPaged($tag=''){
        $data = $this->rows($tag);
        return $data['data'];
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