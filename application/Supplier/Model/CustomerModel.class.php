<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-04
 * Time: 11:35
 */

namespace Supplier\Model;


use Common\Model\CommonModel;

class CustomerModel extends CommonModel
{
    protected $tableName ="Biz_fans";
    function customers($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
            if(isset($tag['_string'])){
                $where['_string'] = $tag['_string'];
            }
        }
        
        $field = !empty($tag['field']) ? $tag['field'] : 'customer.*';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'customer.favorite_date DESC';

        /*$join1 = 'LEFT JOIN __ORDER_INFO__ info ON info.user_id = user.id';*/

        $data['total'] = $this->alias('customer')->where($where)->count();

        $this->alias('customer')->field($field)->where($where)->order($order);
        if(empty($pageSize)){
            $this->limit($limit);
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }
        $rs = $this->select();

        $data['data'] = array();
        if(!empty($rs)){
            foreach($rs as $row){
                $data['data'][$row['id']] = $row;
            }
        }

        return $data;
    }

    /* 获取分页的记录 */
    function getCustomersPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->customers($tag, $pageSize, $pagetpl, $tplName);
    }

    /* 获取不分页的记录 */
    function getCustomersNoPaged($tag=''){
        $data = $this->customers($tag);
        return $data['data'];
    }

    /*
     * 保存客户资料
     * @param array $data 要保存的数据
     * @return int
     */
    function saveCustomer($data){
        $result = $this->create($data);
        if($result !== false){
            $result = isset($this->data[$this->getPk()]) ? $this->save():$this->add();
            if($result === false){
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    /*
     * 获取客户资料
     * @param array $id 需求id
     * @return int
     */
    function getCustomer($id){
        if(is_array($id)){
            $where = $id;
        }else{
            $where['id'] = $id;
        }

        $where['customer_status'] = isset($where['customer_status']) ? $where['customer_status']:1;
        
        $customer = $this->where($where)->find();
        
        return $customer;
    }

}