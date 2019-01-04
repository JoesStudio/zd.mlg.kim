<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-04
 * Time: 11:35
 */

namespace Demand\Model;


use Common\Model\CommonModel;

class QuoteModel extends CommonModel
{
    protected $tableName = 'Demand_quote';
    public $statuses = array(
        '0'     => '未审核',
        '1'    => '已审核',
    );

    public $units = array('米','克');

    //自动完成
    protected $_auto = array(
        //array(填充字段,填充内容,填充条件,附加规则)
        array('quote_status','0',CommonModel:: MODEL_INSERT),
        array('quote_trash','0',CommonModel:: MODEL_INSERT),
        array('operator_id','mGetOperatorId',CommonModel:: MODEL_BOTH,'callback'),
        array('operator','mGetOperator',CommonModel:: MODEL_BOTH,'callback'),
        array('created_at','mGetDate',CommonModel:: MODEL_INSERT,'callback'),
        array('updated_at','mGetDate',CommonModel::MODEL_UPDATE,'callback')
    );

    function quotes($tag='', $where=array(), $pageSize=0, $pagetpl='', $tplName='default'){

        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
        }
        
        $field = !empty($tag['field']) ? $tag['field'] : 'quote.*,bm.long_name';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'created_at DESC';

        $join1 = '__BIZ_MEMBER__ bm ON bm.id = quote.vend_id';
        $data['total'] = $this->alias('quote')->join($join1)->where($where)->count();

        if(empty($pageSize)){
            
            $data['data'] = $this
                ->alias('quote')
                ->field($field)
                ->join($join1)
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
            $page->SetPager('default', $pagetpl,$pagesetting);
            $data['data'] = $this
                ->alias('quote')
                ->field($field)
                ->join($join1)
                ->where($where)
                ->order($order)
                ->limit($page->firstRow, $page->listRows)
                ->select();
            $data['page'] = $page->show('default');
        }
        return $data;
    }

    function getQuotesPaged($tag='', $where=array(), $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->Quotes($tag, $where, $pageSize, $pagetpl, $tplName);
    }

    /*
     * 添加需求
     * @param array $data 要插入的数据
     * @return int
     */
    function addDemand($data){
        if($this->create($data)){
            $result = $this->add();
            return $result;
        }else{
            return 0;
        }
    }

    /*
     * 修改需求
     * @param array $data 要插入的数据
     * @return int
     */
    function updateDemand($data){
        if($this->create($data)){
            $result = $this->save();
            return $result;
        }else{
            return 0;
        }
    }

    /*
     * 获取需求
     * @param array $id 需求id
     * @return int
     */
    function getDemand($id){
        $where['demands.demand_trash'] = 0;
        $where['demands.demand_id'] = $id;

        $demand = $this->alias('demands')
            ->join('__BIZ_MEMBER__ cust ON cust.id = demands.cust_id')
            ->where($where)
            ->find();
        $demand['demand_contact'] = json_decode($demand['demand_contact'], true);
        $demand['demand_img'] = json_decode($demand['demand_img'], true);
        return $demand;
    }

    //获取操作人ID
    function mGetOperatorId(){
        if(MODULE_NAME == 'Admin'){
            $uid = sp_get_current_admin_id();
        }else{
            $uid = sp_get_current_userid();
        }
        return $uid;
    }

    //获取操作人名字
    function mGetOperator(){
        if(MODULE_NAME == 'Admin'){
            $uid = sp_get_current_admin_id();
            $user = M('Users')->find($uid);
        }else{
            $user = sp_get_current_user();
        }
        $name = empty($user['nickname']) ? $user['nickname']:$user['user_login'];
        return $name;
    }

    function check_contact_name($contact){
        $contact = json_decode($contact,true);
        return !empty($contact['name']);
    }

    function check_contact_phone($contact){
        $contact = json_decode($contact,true);
        return !empty($contact['phone']);
    }

    //用于获取时间，格式为2012-02-03 12:12:12,注意,方法不能为private
    function mGetDate() {
        return date('Y-m-d H:i:s');
    }

    protected function _before_write(&$data) {
        parent::_before_write($data);
    }
}