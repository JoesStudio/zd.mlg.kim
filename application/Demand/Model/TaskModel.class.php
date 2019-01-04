<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-01-19
 * Time: 11:18
 */

namespace Demand\Model;


use Common\Model\CommonModel;

class TaskModel extends CommonModel
{
    protected $tableName = 'demand_task';

    //分配限额
    public $taskLimit = 3;

    public $statuses = array(
        0   => '待处理',
        1   => '已处理',
        2   => '超时未处理',
    );

    function getTask($id){
        if(is_array($id)){
            $where = $id;
            if(array_key_exists('demand_id',$where)){
                $where['task.demand_id'] = $where['demand_id'];
                unset($where['demand_id']);
            }
        }else{
            $where['task.task_id'] = $id;
        }

        $join1 = '__DEMAND__ demand ON demand.demand_id = task.demand_id';
        $data = $this->alias('task')->join($join1)->where($where)->find();
        if($data){
            $data['demand_contact'] = json_decode($data['demand_contact'],true);
            $data['demand_img'] = json_decode($data['demand_img'],true);
            $data['user'] = D('Users')->getUser($data['user_id']);
        }
        return $data;
    }

    /*
     * 获取未删除的记录
     * @param int $currentPage 当前分页
     * @param in $pageSize 分页记录数
     * @return array
     */
    function tasks($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
            if(array_key_exists('demand_id',$where)){
                $where['task.demand_id'] = $where['demand_id'];
                unset($where['demand_id']);
            }
            if(array_key_exists('subData',$where)){
                unset($where['subData']);
            }
        }else{
            $tag=sp_param_lable($tag);
            if(isset($tag['demand_id'])){
                $where['demand.demand_id'] = $tag['demand_id'];
            }
            if(isset($tag['supplier_id'])){
                $where['supplier_id'] = $tag['supplier_id'];
            }
            if(isset($tag['task_status'])){
                $where['task_status'] = $tag['task_status'];
            }
            if(isset($tag['_string'])){
                $where['_string'] = $tag['_string'];
            }
        }

        $field = !empty($tag['field']) ? $tag['field'] : '*';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'task_created DESC';

        $join1 = '__DEMAND__ demand ON demand.demand_id = task.demand_id';

        $where['demand_trash'] = 0;

        $data['total'] = $this->alias('task')->join($join1)->where($where)->count();

        $this->alias('task')->field($field)->join($join1)->where($where)->order($order);
        if(empty($pageSize)){
            $this->limit($limit);
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }

        $rs = $this->select();

        if(!empty($rs)){
            $uids = array();
            $sup_ids = array();
            $ids = array();
            $tmp = array();
            foreach($rs as $row){
                $row['demand_contact'] = json_decode($row['demand_contact'],true);
                $row['demand_img'] = json_decode($row['demand_img'],true);
                $tmp[$row['task_id']] = $row;
                array_push($uids, $row['user_id']);
                array_push($sup_ids, $row['supplier_id']);
                array_push($ids, $row['order_id']);
            }
            $uids = array_unique($uids);
            $sup_ids = array_unique($sup_ids);

            //subData 是需要获取的子数据，逗号分割
            if(isset($tag['subData'])){
                $subs = is_array($tag['subData']) ? $tag['subData']:explode(',',$tag['subData']);
                foreach($subs as $sub){
                    switch($sub){
                        case 'supplier':
                            $suppliers = D('BizMember')->getMembersNoPaged(array('id'=>array('IN',$sup_ids)));
                            break;
                        case 'user':
                            $users = D('Users')->getUsersNoPaged(array('id'=>array('IN',$uids)));
                            break;
                        default:
                    }
                }
                foreach($tmp as $key=>$row){
                    if(isset($suppliers)){
                        $tmp[$key]['supplier'] = $suppliers[$row['supplier_id']];
                    }
                    if(isset($users)){
                        $tmp[$key]['user'] = $users[$row['user_id']];
                    }
                }
            }

            $data['data'] = $tmp;
        }

        return $data;
    }

    /*
     * 获得分页的订单列表
     */
    function getTasksPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->tasks($tag, $pageSize, $pagetpl, $tplName);
    }

    /*
     * 获得不分页的订单表
     */
    function getTasksNoPaged($tag=''){
        $data =  $this->tasks($tag);
        return $data['data'];
    }

    function assignTasks($demand_id,$supplier_ids){
        $result = array();
        foreach($supplier_ids as $supplier_id){
            $data = array(
                'demand_id'     => $demand_id,
                'supplier_id'   => $supplier_id,
                'task_status'   => 0,
                'task_created'  => time(),
            );
            $result[$supplier_id]['result'] = $this->saveTask($data);
            if($$result[$supplier_id]['result'] === false){
                $result[$supplier_id]['error'] = $this->getError();
            }
        }
        return $result;
    }

    function saveTask($data){
        $result = $this->create($data);
        if($result !== false){
            $result = empty($this->data[$this->getPk()]) ? $this->add():$this->save();
            if($result === false){
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }
}