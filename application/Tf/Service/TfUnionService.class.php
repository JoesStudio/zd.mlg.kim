<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-06-02
 * Time: 11:41
 */

namespace Tf\Service;


use Think\Model;

class TfUnionService
{
    public $tfModel;
    public $distModel;
    public $unionModel;
    public $unionSkuModel;

    public function __construct()
    {
        $this->tfModel = M('TextileFabric');
        $this->distModel = M('DistributionTf');
        $this->unionModel = M('UnionTfView');
        $this->unionSkuModel = M('UnionSkuView');
    }

    function rows($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);

            if(isset($tag['on_sale'])){
                $where['on_sale'] = $tag['on_sale'];
            }
            if(isset($tag['supplier_id'])){
                $where['supplier_id'] = $tag['supplier_id'];
            }
            if(isset($tag['source'])){
                $where['source'] = $tag['source'];
            }
            if(isset($tag['_string'])){
                $where['_string'] = $tag['_string'];
            }
        }

        $fields = $this->unionModel->getDbFields();
        $exclueFields = array('describe',);

        $field = !empty($tag['field']) ? $tag['field'] : array_diff($fields, $exclueFields);
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'create_date DESC,id DESC';

        foreach ($where as $key => $value) {
            $ignore_fields = array('field', 'order', 'group', 'limit', 'join', 'where');
            if (in_array($key, $ignore_fields)) {
                unset($where[$key]);
                continue;
            }
        }

        $data['total'] = $this->unionModel->alias('tf')->where($where)->count();

        $this->unionModel->field($field)->alias('tf')->where($where)->order($order);
        if(empty($pageSize)){
            if (isset($tag['limit'])) {
                $this->unionModel->limit($limit);
            }
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->unionModel->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }
        $rs = $this->unionModel->select();

        if(!empty($rs)){
            foreach($rs as $k=>$row){
                $row['img'] = str_replace("&quot;", '"', $row['img']);
                $row['img'] = str_replace("'", '"', $row['img']);
                $rs[$k]['img'] = json_decode($row['img'],true);
                $rs[$k]['thumb'] = $rs[$k]['img']['thumb'];
            }
        }

        $data['data'] = $rs;
        return $data;
    }

    function getRowsPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->rows($tag, $pageSize, $pagetpl, $tplName);
    }

    function getRowsNoPaged($tag=''){
        $data = $this->rows($tag);
        return $data['data'];
    }

    function getSkuList($where){
        $data = $this->unionSkuModel->where($where)->select();
        $skuModel = M('DistributionTfSku');
        foreach($data as $k=>$v){
            //初始化还没存在的代理sku
            if($v['original'] == 0 && is_null($v['id'])){
                $skuData = array(
                    'tf_id'=>$v['tf_id'],
                    'source_sku_id'=>$v['original_id'],
                    'sku_price'=>$v['sku_price'],
                    'group_price'=>$v['group_price'],
                );
                if($skuModel->create($skuData) !== false){
                    $skuId = $skuModel->add($skuData);
                    if($skuId !== false){
                        $data[$k]['id'] = $skuId;
                    }else{
                        unset($data[$k]);
                    }
                }else{
                    unset($data[$k]);
                }
            }
        }
        return $data;
    }

    function getTf($where=array(),$field='*'){

        if(is_string($where)){

            $tf_code = $where;
            $where = [];
            $where['tf_code'] = $tf_code;
        }

        $tf = $this->unionModel->field($field)->where($where)->find();

        return $tf;
    }

    function checkUniqueTfCode($code,$tfId,$source){
        $tf = $this->unionModel->field('id,source')->where(array('tf_code'=>$code))->find();
        if(empty($tf)){
            return true;
        }
        if($tf['id'] == $tfId && $tf['source'] == $source){
            return true;
        }
        return false;
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