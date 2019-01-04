<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-01-22
 * Time: 11:42
 */

namespace Common\Model;


class BizSmsModel extends CommonModel
{

    protected $_auto = array(
        //array(填充字段,填充内容,填充条件,附加规则)
        array('create_date','mGetDate',CommonModel:: MODEL_INSERT,'function'),
        array('modify_date','mGetDate',CommonModel:: MODEL_BOTH,'function'),
        array('create_userid','sp_get_current_userid',CommonModel:: MODEL_INSERT,'function'),
        array('modify_userid','sp_get_current_userid',CommonModel:: MODEL_BOTH,'function'),
    );

    function saveSms($data){
        $result = $this->create($data);
        if($result !== false){
            $result = isset($this->data[$this->getPk()]) ? $this->save():$this->add();
            if($result === false){
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    function getSms($id){
        if(is_array($id)){
            $where = $id;
        }else{
            $where['id'] = $id;
        }
        $data = $this->where($where)->find();
        return $data;
    }

    function getSmsByBm($member_id){
        $where['member_id'] = $member_id;
        $data = $this->getSms($where);
        return $data;
    }

}