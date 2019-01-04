<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-19
 * Time: 17:26
 */

namespace Notify\Model;


use Common\Model\CommonModel;

class LogModel extends CommonModel
{
    protected $tableName = 'message_action';

    public $types = array(
        'EMAIL'     => '邮件',
        'WECHAT'    => '微信',
        'SMS'       => '短信',
    );

    public $statuses = array('发送失败','发送成功');

    function logAction($id,$type,$status,$note=''){
        $data = array(
            'message_id'    => $id,
            'type'          => $type,
            'status'        => $status,
            'action_note'   => $note,
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
    function logs($tag='',$where=array(), $pageSize=0, $pagetpl='', $tplName='default'){
        $where=is_array($where)?$where:array();
        $tag=sp_param_lable($tag);

        $field = !empty($tag['field']) ? $tag['field'] : '*';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'log_time DESC';

        $data['total'] = $this->where($where)->count();
        if(empty($pageSize)){
            $data['data'] = $this
                ->field($field)
                ->where($where)
                ->order($order)
                ->limit($limit)
                ->select();
        }else{
            $pagesize = intval($pageSize);
            $page_param = C("VAR_PAGE");
            $page = new \Page($data['total'],$pagesize);
            $page->setLinkWraper("li");
            $page->__set("PageParam", $page_param);
            $pagesetting=array("listlong" => "9", "first" => "首页", "last" => "尾页", "prev" => "上一页", "next" => "下一页", "list" => "*", "disabledclass" => "");
            $page->SetPager($tplName, $pagetpl,$pagesetting);
            $data['data'] = $this
                ->field($field)
                ->where($where)
                ->limit($page->firstRow, $page->listRows)
                ->order($order)
                ->select();
            $data['page'] = $page->show($tplName);
        }

        return $data;
    }
    /*
     * 获得分页列表
     */
    function getLogsPaged($tag='',$where=array(), $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->logs($tag,$where, $pageSize, $pagetpl, $tplName);
    }

    /*
     * 获得不分页表
     */
    function getLogsNoPaged($tag='',$where=array()){
        return $this->logs($tag,$where);
    }
}