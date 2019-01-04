<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-18
 * Time: 14:38
 */

namespace Common\Model;



class ShippingModel extends CommonModel
{
    public $statuses = array('禁用','启用');

    /*
     * 获取未删除的记录
     * @param int $currentPage 当前分页
     * @param in $pageSize 分页记录数
     * @return array
     */
    function shippings($tag='', $where=array(), $pageSize=0, $pagetpl='', $tplName='default'){
        $where=is_array($where)?$where:array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
        }

        $field = !empty($tag['field']) ? $tag['field'] : '*';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'shipping_id DESC';


        $where['enabled'] = isset($where['enabled']) ? $where['enabled']:1;
        $data['total'] = $this->where($where)->count();
        $this->field($field)->where($where)->order($order);
        if(empty($pageSize)){
            $this->limit($limit);
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
        }
        $rs = $this->select();
        $data['data'] = array();
        foreach($rs as $row){
            $data['data'][$row['shipping_id']] = $row;
        }
        return $data;
    }

    function getShippingPaged($tag='', $where=array(), $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->shippings($tag, $where, $pageSize, $pagetpl, $tplName);
    }

    function getShippingNoPaged($tag='', $where=array()){
        $data =  $this->shippings($tag, $where);
        return $data['data'];
    }

    function getShipping($id){
        if(is_array($id)){
            $where = $id;
        }else{
            $where['shipping_id'] = $id;
        }
        $address =  $this
            ->where($where)
            ->find();
        return $address;
    }

    /*
     * 添加
     * @param array $data 要插入的数据
     * @return int
     */
    function addShipping($data){
        if($this->create($data)){
            $result = $this->add();
            return $result;
        }else{
            return -1;
        }
    }

    /*
     * 修改
     * @param array $data 要插入的数据
     * @return int
     */
    function updateShipping($data){
        if($this->create($data)){
            $result = $this->save();
            return $result;
        }else{
            return -1;
        }
    }

}