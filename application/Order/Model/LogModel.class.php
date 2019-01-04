<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-18
 * Time: 11:48
 */

namespace Order\Model;


use Common\Model\CommonModel;

class LogModel extends CommonModel
{
    protected $tableName = 'order_action';

    /*
     * 操作记录
     * @param int $order_id 订单id
     * @param int $user_id 用户id
     * @param string $note 备注
     */
    function logAction($order_id, $user_id, $note='',$place=0){
        $order = D('Order/Order', 'Logic')->getOrder($order_id);
        if($order){
            $data = array(
                'order_id'          => $order_id,
                'action_user'       => $user_id,
                'order_status'      => $order['order_status'],
                'shipping_status'   => $order['shipping_status'],
                'pay_status'        => $order['pay_status'],
                'action_place'      => $place,
                'action_note'       => $note,
                'log_time'          => time(),
            );

            return $this->add($data);
        }else{
            return 0;
        }
    }


    /*
     * 获取未删除的记录
     * @param int $currentPage 当前分页
     * @param in $pageSize 分页记录数
     * @return array
     */
    function logs($tag='',$where=array(), $pageSize=0, $pagetpl='', $tplName='default'){
        $where = is_array($where) ? $where:array();
        $tag=sp_param_lable($tag);

        $field = !empty($tag['field']) ? $tag['field'] : 'logs.*,user.nickname';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'log_time DESC';

        if(isset($tag['order_id']) && !isset($where['order_id'])){
            $where['order_id'] = $tag['order_id'];
        }

        if(isset($tag['action_place']) && !isset($where['action_place'])){
            $where['action_place'] = $tag['action_place'];
        }

        $data['total'] = $this->where($where)->count();

        $this->alias('logs')->field($field)
            ->join('__USERS__ user ON user.id = logs.action_user','LEFT')
            ->where($where)->order($order);

        if(empty($pageSize)){
            $this->limit($limit);
        }else{
            $pagesize = intval($pageSize);
            $page_param = C("VAR_PAGE");
            $page = new \Page($data['total'],$pagesize);
            $page->setLinkWraper("li");
            $page->__set("PageParam", $page_param);
            $pagesetting=array("listlong" => "9", "first" => "首页", "last" => "尾页", "prev" => "上一页", "next" => "下一页", "list" => "*", "disabledclass" => "");
            $page->SetPager($tplName, $pagetpl,$pagesetting);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show($tplName);
        }

        $data = $this->select();

        return $data;
    }
    /*
     * 获得分页的记录
     */
    function getLogsPaged($tag='', $where=array(), $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->logs($tag, $where, $pageSize, $pagetpl, $tplName);
    }

    /*
     * 获得不分页的记录
     */
    function getLogsNoPaged($tag='', $where=array()){
        return $this->logs($tag, $where);
    }

}