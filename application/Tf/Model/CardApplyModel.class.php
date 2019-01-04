<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-14
 * Time: 10:11
 */

namespace Tf\Model;


use Common\Model\CommonModel;

class CardApplyModel extends CommonModel
{
    public $statuses = array(
        //'-1'    => '待处理',
        '0'     => '未发货',
        '1'     => '已发货',
        '2'     => '已取消',
    );

    protected $tableName = 'card_apply';

    protected $_auto = array(
        //array(填充字段,填充内容,填充条件,附加规则)
        array('apply_time','mGetDate',CommonModel:: MODEL_INSERT,'function'),
    );

    function applys($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
            if(isset($tag['_string'])){
                $where['_string'] = $tag['_string'];
            }
        }
        
        $field = !empty($tag['field']) ? $tag['field'] : 'apply.*,user.nickname,user.user_login,province.name as province_name,city.name as city_name,district.name as district_name';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'apply_time DESC';

        $join1 = 'LEFT JOIN __USERS__ user ON user.id = apply.user_id';
        $join2 = 'LEFT JOIN __AREAS__ province ON province.id = apply.province';
        $join3 = 'LEFT JOIN __AREAS__ city ON city.id = apply.city';
        $join4 = 'LEFT JOIN __AREAS__ district ON district.id = apply.district';

        $data['total'] = $this->alias('apply')->join($join1)->where($where)->count();

        $this->alias('apply')->field($field)
            ->join($join1)
            ->join($join2)
            ->join($join3)
            ->join($join4)
            ->where($where)
            ->order($order);
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
                $row['tf'] = D('Tf/Tf')->getTf($row['tf_id']);
                $row['supplier'] = D('BizMember')->getMember($row['supplier_id']);
                $row['user'] = D('UserInfo')->getInfo($row['supplier_id']);
                $data['data'][$row['id']] = $row;
            }
        }

        return $data;
    }

    /* 获取分页的记录 */
    function getApplysPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->applys($tag, $pageSize, $pagetpl, $tplName);
    }

    /* 获取不分页的记录 */
    function getApplyNoPaged($tag=''){
        $data = $this->applys($tag);
        return $data['data'];
    }

    

    /*
     * 获取申请
     * @param array $id 获取申请id
     * @return int
     */
    function getApply($id){
        if(is_array($id)){
            $where = $id;
            $id = $where['id'];
        }else{
            $where['apply.id'] = $id;
        }

        $field = 'apply.*,user.nickname,user.user_login,province.name as province_name,city.name as city_name,district.name as district_name';
        
        $join1 = 'LEFT JOIN __USERS__ user ON user.id = apply.user_id';
        $join2 = 'LEFT JOIN __AREAS__ province ON province.id = apply.province';
        $join3 = 'LEFT JOIN __AREAS__ city ON city.id = apply.city';
        $join4 = 'LEFT JOIN __AREAS__ district ON district.id = apply.district';
        
        $apply = $this->alias('apply')->field($field)
                ->join($join1)
                ->join($join2)
                ->join($join3)
                ->join($join4)
                ->where($where)
                ->find();
        if(!empty($apply)){
            $apply['tf'] = D('Tf/Tf')->getTf($apply['tf_id']);
            $apply['user'] = D('UserInfo')->getInfo($apply['user_id']);
            $demand['supplier'] = D('BizMember')->getMember($apply['supplier_id']);
        }
        return $apply;
    }

    
}