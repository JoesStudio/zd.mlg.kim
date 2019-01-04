<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-12-07
 * Time: 12:16
 */

namespace Tf\Model;


use Common\Model\CommonModel;

class CatModel extends CommonModel
{
    protected $tableName = 'textile_fabric_cats';

    function cats($tag=''){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
        }

        $field = !empty($tag['field']) ? $tag['field'] : '*';
        $order = !empty($tag['order']) ? $tag['order'] : 'listorder ASC';

        if(isset($tag['pid'])){
            $where['pid'] = $tag['pid'];
        }

        $this->field($field)->where($where)->order($order);
        if(!empty($tag['limit'])){
            $this->limit($tag['limit']);
        }
        $rs = $this->select();
        $data = array();
        foreach($rs as $row){
            $data[$row['id']] = $row;
        }

        return $data;

    }

    function getCat($id){
        $where['id'] = $id;
        $data = $this->where($where)->find();
        return $data;
    }

    function getAllSubIds($pid){
        $ids = $this->where("pid=$pid")->getField('id',true);
        if(!empty($ids)){
            foreach($ids as $pid){
                $ids = array_merge($ids, $this->getAllSubIds($pid));
            }
        }else{
            $ids = array();
        }
        return $ids;
    }



}