<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-05-31
 * Time: 17:54
 */

namespace Distribution\Service;


use Distribution\Model\PoolModel;
use Distribution\Model\ApplyModel;
use Tf\Model\TfModel;

class ApplyService
{
    protected $poolModel;
    protected $tfModel;
    public $applyModel;
    public $applyView;

    public function __construct(){
        $this->poolModel = new PoolModel();
        $this->tfModel = new TfModel();
        $this->applyModel = new ApplyModel();
        $this->applyView = M('DistributionTfApplyView');
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
            if (isset($tag['source_id'])) {
                $where['source_id'] = $tag['source_id'];
            }
            if (isset($tag['status'])) {
                $where['status'] = $tag['status'];
            }
            if (isset($tag['dist_tf_status'])) {
                $where['dist_tf_status'] = $tag['dist_tf_status'];
            }
            if (isset($tag['where'])) {
                $where['_string'] = $tag['_string'];
            }
        }

        $field = !empty($tag['field']) ? $tag['field'] : "*";
        $order = !empty($tag['order']) ? $tag['order'] : "IFNULL(modify_time,create_time) DESC";

        foreach ($where as $key => $value) {
            $ignore_fields = array('field', 'order', 'group', 'limit', 'join', 'where');
            if (in_array($key, $ignore_fields)) {
                unset($where[$key]);
                continue;
            }
        }

        $data['total'] = $this->applyView->where($where)->count();

        $this->applyView->field($field)->where($where)->order($order);
        if(empty($pageSize)){
            if (!empty($tag['limit'])) {
                $this->applyView->limit($tag['limit']);
            }
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->applyView->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }
        $rs = $this->applyView->select();

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
    public function rows2($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
            if (isset($tag['supplier_id'])) {
                $where['apply.supplier_id'] = $tag['supplier_id'];
            }
            if (isset($tag['distributor_id'])) {
                $where['apply.distributor_id'] = $tag['distributor_id'];
            }
            if (isset($tag['source_id'])) {
                $where['apply.source_id'] = $tag['source_id'];
            }
            if (isset($tag['status'])) {
                $where['apply.status'] = $tag['status'];
            }
            if (isset($tag['where'])) {
                $where['_string'] = $tag['_string'];
            }
        }

        $alias = 'apply';
        $field = !empty($tag['field']) ? $tag['field'] : "apply.id,apply.source_id,apply.supplier_id,
        apply.distributor_id,apply.status,apply.create_time,apply.modify_time,
        distributor.biz_name as distributor_name,supplier.biz_name as supplier_name,
        tf.name,tf.img,tf.code, CONCAT(tf.vend_id,tf.cat_code,tf.name_code,tf.code) as tf_code";
        $order = !empty($tag['order']) ? $tag['order'] : "IFNULL(apply.modify_time,apply.create_time) DESC";

        foreach ($where as $key => $value) {
            $ignore_fields = array('field', 'order', 'group', 'limit', 'join', '_string', 'where');
            if (in_array($key, $ignore_fields)) {
                unset($where[$key]);
                continue;
            }
            if (strpos($key, '.') === false) {
                $where["$alias.$key"] = $value;
                unset($where[$key]);
            }
        }

        $join1 = "__TEXTILE_FABRIC__ tf ON tf.id=apply.source_id";
        $join2 = "__BIZ_MEMBER__ supplier ON supplier.id=apply.supplier_id";
        $join3 = "__BIZ_MEMBER__ distributor ON distributor.id=apply.distributor_id";

        $data['total'] = $this->applyModel->alias($alias)->join($join1)->where($where)->count();

        $this->applyModel->alias($alias)->field($field)->where($where)->order($order);
        $this->applyModel->join($join1)->join($join2)->join($join3);
        if(empty($pageSize)){
            if (!empty($tag['limit'])) {
                $this->applyModel->limit($tag['limit']);
            }
        }else{
            $page = $this->applyModel->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->applyModel->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }
        $rs = $this->applyModel->select();

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

    public function getRow($id){
        if(is_array($id)){
            $where = $id;
        }else{
            $where['id'] = $id;
        }
        $row = $this->applyView->where($where)->find();
        if(!empty($row['img'])){
            $row['img'] = str_replace("&quot;", '"', $row['img']);
            $row['img'] = str_replace("'", '"', $row['img']);
            $row['img'] = json_decode($row['img'], true);
            if(!empty($row['img'])){
                $row['thumb'] = $row['img']['thumb'];
            }
        }
        return $row;
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