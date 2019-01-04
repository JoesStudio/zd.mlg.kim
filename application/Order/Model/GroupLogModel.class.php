<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-18
 * Time: 11:48
 */

namespace Order\Model;


use Common\Model\CommonModel;

class GroupLogModel extends CommonModel
{
    protected $tableName = 'order_group_action';

    /*
     * 操作记录
     * @param int $order_id 订单id
     * @param int $user_id 用户id
     * @param string $note 备注
     */
    function logAction($group_id, $user_id, $action, $note=''){
        $group = D('Order/Group')->getGroup($group_id);
        if (empty($group)) {
            $this->error = L('NO_DATA_ERROR');
            return false;
        }
        $data = array(
            'group_id'          => $group_id,
            'action_user'       => $user_id,
            'action_name'       => $action,
            'group_status'      => $group['group_status'],
            'action_note'       => $note,
            'log_time'          => date('Y-m-d H:i:s'),
        );

        $result = $this->add($data);
        if ($result === false) {
            $this->error = $this->getDbError();
        }
        return $result;
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
        }else{
            $tag=sp_param_lable($tag);

            if (isset($tag['group_status'])){
                $where['group_status'] = $tag['group_status'];
            }
            if (isset($tag['group_id'])){
                $where['group_id'] = $tag['group_id'];
            }
        }

        $alias = 'log';
        $field = !empty($tag['field']) ? $tag['field'] : "$alias.*,user.nickname";
        $order = !empty($tag['order']) ? $tag['order'] : "$alias.log_time DESC";

        foreach ($where as $key => $value) {
            if(in_array($key, array('order','limit','field','join'))) {
                unset($where[$key]);
                continue;
            };
            if (strpos($key, '.') === false) {
                $where["$alias.$key"] = $value;
                unset($where[$key]);
            }
        }

        $data['total'] = $this->alias($alias)->where($where)->count();

        $this->alias($alias)->field($field)
            ->join("LEFT JOIN __USERS__ user ON user.id = $alias.action_user")
            ->where($where)->order($order);

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

        $data = $this->select();

        return $data;
    }
    /*
     * 获得分页的记录
     */
    function getLogsPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->logs($tag, $pageSize, $pagetpl, $tplName);
    }

    /*
     * 获得不分页的记录
     */
    function getLogsNoPaged($tag=''){
        $data =  $this->logs($tag);
        return $data['data'];
    }

}