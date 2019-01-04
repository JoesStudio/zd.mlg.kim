<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-04
 * Time: 11:35
 */

namespace Demand\Model;


use Common\Model\CommonModel;

class DemandModel extends CommonModel
{
    public $statuses = array(
        '0'     => '待处理',
        '1'     => '待发送',
        '2'     => '待报价',
        '3'     => '待确认',
        '4'     => '已确认',
        '5'     => '无效需求',
    );

    public $units = array('米','克');

    //自动验证
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('demand_end_date', 'require', '请选择一个到期时间！', 0, 'regex', CommonModel:: MODEL_BOTH ),
    );

    //自动完成
    protected $_auto = array(
        //array(填充字段,填充内容,填充条件,附加规则)
        array('demand_status','0',CommonModel:: MODEL_INSERT),
        array('demand_trash','0',CommonModel:: MODEL_INSERT),
        array('demand_created','time',CommonModel:: MODEL_INSERT,'function'),
        array('demand_modified','time',CommonModel::MODEL_UPDATE,'function')
    );

    function demands($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
            if(isset($tag['_string'])){
                $where['_string'] = $tag['_string'];
            }
        }
        
        $field = !empty($tag['field']) ? $tag['field'] : 'demand.*,user.nickname,user.user_login';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'demand_created DESC';

        $join1 = 'LEFT JOIN __USERS__ user ON user.id = demand.user_id';

        $data['total'] = $this->alias('demand')->join($join1)->where($where)->count();

        $this->alias('demand')->field($field)->join($join1)->where($where)->order($order);
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
                $row['demand_contact'] = json_decode($row['demand_contact'],true);
                $row['demand_img'] = json_decode($row['demand_img'],true);
                $data['data'][$row['demand_id']] = $row;
            }
        }

        return $data;
    }

    /* 获取分页的记录 */
    function getDemandsPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->demands($tag, $pageSize, $pagetpl, $tplName);
    }

    /* 获取不分页的记录 */
    function getDemandNoPaged($tag=''){
        $data = $this->demands($tag);
        return $data['data'];
    }

    /*
     * 保存需求
     * @param array $data 要保存的数据
     * @return int
     */
    function saveDemand($data){
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
     * 获取需求
     * @param array $id 需求id
     * @return int
     */
    function getDemand($id){
        if(is_array($id)){
            $where = $id;
            $id = $where['demand_id'];
        }else{
            $where['demand_id'] = $id;
        }

        $where['demand_trash'] = isset($where['demand_trash']) ? $where['demand_trash']:0;
        
        $demand = $this->where($where)->find();
        if(!empty($demand)){
            $demand['demand_img'] = json_decode($demand['demand_img'], true);
            $demand['demand_contact'] = json_decode($demand['demand_contact'], true);
            $demand['task'] = D('Demand/Task')->getTasksNoPaged("demand_id:$id;subData:supplier;");
        }
        return $demand;
    }

    function trash($id){
        $result = $this->where(array('demand_id'=>$id))->setField('demand_trash',1);
        if($result === false){
            $this->error = $this->getDbError();
        }
        return $result;
    }
}