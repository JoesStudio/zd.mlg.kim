<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-05-27
 * Time: 11:53
 */

namespace Distribution\Model;


use Common\Model\CommonModel;

class PoolModel extends CommonModel
{
    protected $tableName = 'distribution_pool';

    public function rows($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
            if (isset($tag['supplier_id'])) {
                $where['supplier_id'] = $tag['supplier_id'];
            }
            if (isset($tag['source_id'])) {
                $where['source_id'] = $tag['source_id'];
            }
            if (isset($tag['status'])) {
                $where['status'] = $tag['status'];
            }
            if (isset($tag['where'])) {
                $where['_string'] = $tag['_string'];
            }
        }

        $alias = 'main';
        $field = !empty($tag['field']) ? $tag['field'] : "tf.*";
        $order = !empty($tag['order']) ? $tag['order'] : "$alias.create_time DESC";

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

        $join1 = "__TEXTILE_FABRIC__ tf ON tf.id=$alias.source_id";

        $data['total'] = $this->alias($alias)->join($join1)->where($where)->count();

        $this->alias($alias)->field($field)->join($join1)->where($where)->order($order);
        if(empty($pageSize)){
            if (!empty($tag['limit'])) {
                $this->limit($tag['limit']);
            }
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }
        $rs = $this->select();

        if(!empty($rs)){
            foreach($rs as $k => $v){
                if(isset($v['img'])) $rs[$k]['img'] = json_decode($v, true);
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

}