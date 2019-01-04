<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-06-02
 * Time: 11:41
 */

namespace Distribution\Service;


class TfService
{
    public $tfModel;
    public function __construct()
    {
        $this->tfModel = M('DistributionTf');
    }

    public function rows($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
            if (isset($tag['supplier_id'])) {
                $where['tf.supplier_id'] = $tag['supplier_id'];
            }
            if (isset($tag['distributor_id'])) {
                $where['tf.distributor_id'] = $tag['distributor_id'];
            }
            if (isset($tag['source_id'])) {
                $where['tf.source_id'] = $tag['source_id'];
            }
            if (isset($tag['status'])) {
                $where['tf.status'] = $tag['status'];
            }
            if (isset($tag['where'])) {
                $where['_string'] = $tag['where'];
            }
        }

        $alias = 'tf';
        $field = !empty($tag['field']) ? $tag['field'] : "tf.*,price.price,
        distributor.biz_logo as distributor_logo,supplier.biz_logo as supplier_logo,
        distributor.biz_name as distributor_name,supplier.biz_name as supplier_name,
        source.img,source.spec,source.width,source.weight,source.material,source.component,
        source.function,IFNULL(shelves.on_sale,0) as on_sale,
        CONCAT(tf.supplier_id,source.cat_code,source.name_code,tf.code) as tf_code,
        source.name as source_name,source.code as source_code,
        CONCAT(source.vend_id,source.cat_code,source.name_code,source.code) as source_tf_code";
        $order = !empty($tag['order']) ? $tag['order'] : "IFNULL(tf.modify_time,tf.create_time) DESC";

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

        $join1 = "__TEXTILE_FABRIC__ source ON source.id=tf.source_id";
        $join2 = "__BIZ_MEMBER__ supplier ON supplier.id=tf.supplier_id";
        $join3 = "__BIZ_MEMBER__ distributor ON distributor.id=tf.distributor_id";
        $join4 = "LEFT JOIN __TEXTILE_FABRIC_SHELVES__ shelves ON shelves.tf_id=source.id";
        $join5 = "LEFT JOIN __DISTRIBUTION_WHOLESALE_RULE__ price ON price.source_id=tf.source_id AND price.level=tf.level";

        $data['total'] = $this->tfModel->alias($alias)->join($join1)->join($join4)->where($where)->count();

        $this->tfModel->alias($alias)->field($field)->where($where)->order($order);
        $this->tfModel->join($join1)->join($join2)->join($join3)->join($join4)->join($join5);
        if(empty($pageSize)){
            if (!empty($tag['limit'])) {
                $this->tfModel->limit($tag['limit']);
            }
        }else{
            $page = $this->tfModel->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->tfModel->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }
        $rs = $this->tfModel->select();

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

    public function getRow($map){
        if(is_array($map)){
            $where = $map;
        }else{
            $where['id'] = $map;
        }

        $field = "tf.*,price.price,
        distributor.biz_logo as distributor_logo,supplier.biz_logo as supplier_logo,
        distributor.biz_name as distributor_name,supplier.biz_name as supplier_name,
        source.img,source.spec,source.width,source.weight,source.material,source.component,
        source.function,source.min_charge,IFNULL(shelves.on_sale,0) as on_sale,
        cat.title as cat_title,cat.code as cat_code,
        sname.cname as name_title,sname.code as name_code,
        CONCAT(tf.supplier_id,source.cat_code,source.name_code,tf.code) as tf_code,
        source.name as source_name,source.code as source_code,
        CONCAT(source.vend_id,source.cat_code,source.name_code,source.code) as source_tf_code";
        $join1 = "__TEXTILE_FABRIC__ source ON source.id=tf.source_id";
        $join2 = "LEFT JOIN __TEXTILE_FABRIC_SHELVES__ shelves ON shelves.tf_id=source.id";
        //$join3 = "__DISTRIBUTION_TF_PRICE__ price ON price.source_id=tf.source_id AND price.distributor_id=tf.distributor_id";
        $join3 = "LEFT JOIN __DISTRIBUTION_WHOLESALE_RULE__ price ON price.source_id=tf.source_id AND price.level=tf.level";
        $join4 = "LEFT JOIN __TEXTILE_FABRIC_CATS__ cat ON cat.id=source.cid";
        $join5 = "LEFT JOIN __TEXTILE_FABRIC_NAME__ sname ON sname.code=source.name_code";
        $join6 = "__BIZ_MEMBER__ supplier ON supplier.id=tf.supplier_id";
        $join7 = "__BIZ_MEMBER__ distributor ON distributor.id=tf.distributor_id";
        $this->tfModel->alias('tf')->field($field);
        $this->tfModel->join($join1)->join($join2)->join($join3)->join($join4)
            ->join($join5)->join($join6)->join($join7);
        $data = $this->tfModel->where($where)->find();
        if(!empty($data)){
            $data['img'] = str_replace("&quot;", '"', $data['img']);
            $data['img'] = str_replace("'", '"', $data['img']);
            $data['img'] = json_decode($data['img'], true);
            $data['thumb'] = $data['img']['thumb'];
        }
        return $data;
    }

}