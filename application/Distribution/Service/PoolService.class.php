<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-05-27
 * Time: 12:22
 */

namespace Distribution\Service;

use Distribution\Model\PoolModel;
use Tf\Model\TfModel;

class PoolService
{
    public $poolModel;
    protected $sourceModel;
    public $poolView;
    protected $supplierView;

    public function __construct(){
        $this->poolModel = new PoolModel();
        $this->sourceModel = new TfModel();
        $this->poolView = M('TfPoolView');
        $this->supplierView = M('TfPoolSupplierView');
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

        $alias = 'main';
        $field = !empty($tag['field']) ? $tag['field'] : "id,name,code,tf_code,img,spec,width,weight,material,
        component,function,on_sale,purpose,create_date,modify_date,supplier_id,supplier_name,supplier_logo,
        pool_id,status,level";
        $order = !empty($tag['order']) ? $tag['order'] : "create_date DESC";

        $data['total'] = $this->poolView->where($where)->count();

        $this->poolView->field($field)->where($where)->order($order);
        if(empty($pageSize)){
            if (!empty($tag['limit'])) {
                $this->poolView->limit($tag['limit']);
            }
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->poolView->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }
        $rs = $this->poolView->select();

        if(!empty($rs)){
            foreach($rs as $k => $v){
                if(isset($v['img'])){
                    $v['img'] = str_replace("&quot;", '"', $v['img']);
                    $v['img'] = str_replace("'", '"', $v['img']);
                    $v['img'] = json_decode($v['img'], true);
                    $rs[$k]['img'] = $v['img'];
                    $rs[$k]['thumb'] = $v['img']['thumb'];
                }
            }
        }

        $data['data'] = $rs;
        return $data;
    }

    public function getRowsPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->rows($tag, $pageSize, $pagetpl, $tplName);
    }

    public function getRowsNoPaged($tag=''){
        $data = $this->rows($tag);
        return $data['data'];
    }

    public function getItem($tfId){
        $data = $this->poolModel->where("source_id='{$tfId}'")->find();
        if(!empty($data)){
            $ruleModel = M('DistributionWholesaleRule');
            $data['price'] = $ruleModel
                ->where("source_id='{$tfId}'")
                ->order("level ASC")
                ->getField('level,price');
        }
        return $data;
    }

    public function initPoolItem($tfId){
        $supplierId = $this->sourceModel->where("id='{$tfId}'")->getField('vend_id');
        if($supplierId === null){
            return false;
        }

        $data = $this->poolModel->where("source_id='{$tfId}' AND supplier_id='{$supplierId}'")->find();
        if(!empty($data)){
            return $data;
        }

        $id = $this->poolModel->add(array(
            'parent_id' => 0,
            'source_id' => $tfId,
            'supplier_id'   => $supplierId,
            'status'    => 0,
        ));

        if($id === false){
            return false;
        }

        $ruleModel = M('DistributionWholesaleRule');
        $rules = array();
        for($i=1;$i<=5;$i++){
            array_push($rules, array(
                'source_id' => $tfId,
                'level'     => $i,
            ));
        }
        if($ruleModel->addAll($rules) === false){
            return false;
        }else{
            $data = $this->poolModel->find($id);
            $data['price'] = $ruleModel
                ->where("source_id='{$tfId}'")
                ->order("level ASC")
                ->getField('level,price');
            return $data;
        }
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