<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-19
 * Time: 17:26
 */

namespace Bizauth\Model;

use Common\Model\CommonModel;

class LogModel extends CommonModel
{
    protected $tableName = 'biz_auth_action';

    public function logAction($id, $operator = null, $note = '')
    {
        $auth = D('BizAuth')->find($id);
        if (is_null($operator)) {
            $operator = $id;
        }
        $data = array(
            'auth_id'       => $id,
            'type'          => $auth['auth_type'],
            'status'        => $auth['auth_status'],
            'action_note'   => $note,
            'operator'      => $operator,
            'log_time'      => date('Y-m-d H:i:s', time()),
        );
        return $this->add($data);
    }

    /*
     * 获取未删除的记录
     * @param int $currentPage 当前分页
     * @param in $pageSize 分页记录数
     * @return array
     */
    public function logs($tag = '', $pageSize = 0, $pagetpl = '', $tplName = 'default')
    {
        $where = array();
        if (is_array($tag)) {
            $where = array_merge($where, $tag);
            unset($where['field']);
            unset($where['limit']);
            unset($where['order']);
        } else {
            $tag=sp_param_lable($tag);

            if (!empty($tag['auth_id'])) {
                $where['auth_id'] = $tag['auth_id'];
            }

            if (!empty($tag['user_id'])) {
                $where['user.id'] = $tag['user_id'];
            }
        }

        $field = !empty($tag['field']) ? $tag['field'] : 'log.*,user.user_login,user.nickname,user.user_type';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'log_time DESC';

        $join1 = 'LEFT JOIN __USERS__ user ON user.id = log.operator';

        $data['total'] = $this->alias('log')->join($join1)->where($where)->count();
        $this->alias('log')->field($field)->join($join1)->where($where)->order($order);
        if (empty($pageSize)) {
            $this->limit($limit);
        } else {
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
    public function getLogsPaged($tag = '', $pageSize = 20, $pagetpl = '', $tplName = 'default')
    {
        return $this->logs($tag, $pageSize, $pagetpl, $tplName);
    }

    /*
     * 获得不分页表
     */
    public function getLogsNoPaged($tag = '')
    {
        $data = $this->logs($tag);
        return $data['data'];
    }
}
