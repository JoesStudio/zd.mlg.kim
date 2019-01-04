<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-01-17
 * Time: 15:32
 */

namespace Demand\Model;


use Common\Model\CommonModel;

class LogModel extends CommonModel
{
    protected $tableName = 'demand_action';

    function logAction($id,$operator=0,$note=''){
        $demand = D('Demand/Demand')->find($id);
        $data = array(
            'demand_id'     => $id,
            'status'        => $demand['demand_status'],
            'trash'         => $demand['demand_trash'],
            'action_note'   => $note,
            'operator'      => $operator,
            'log_time'      => time(),
        );
        return $this->add($data);
    }

    /*
     * 获取未删除的记录
     * @param int $currentPage 当前分页
     * @param in $pageSize 分页记录数
     * @return array
     */
    function logs($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
            unset($where['field']);
            unset($where['limit']);
            unset($where['order']);
        }else{
            $tag=sp_param_lable($tag);
        }

        $field = !empty($tag['field']) ? $tag['field'] : 'log.*,user.user_login,user.nickname';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'log_time DESC';

        if(!empty($tag['user_id'])){
            $where['user_id'] = $tag['user_id'];
        }

        $join1 = '__USERS__ user ON user.id = log.operator';

        $data['total'] = $this->alias('log')->join($join1)->where($where)->count();
        $this->alias('log')->field($field)->join($join1)->where($where)->order($order);
        if(empty($pageSize)){
            $this->limit($limit);
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }
        $data['data'] = $this->select();

        return $data;
    }
    /*
     * 获得分页列表
     */
    function getLogsPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->logs($tag, $pageSize, $pagetpl, $tplName);
    }

    /*
     * 获得不分页表
     */
    function getLogsNoPaged($tag=''){
        $data = $this->logs($tag);
        return $data['data'];
    }

}