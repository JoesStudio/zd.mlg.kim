<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-10
 * Time: 16:43
 */

namespace Common\Model;



class BizContactModel extends CommonModel
{
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
    );

    //自动完成
    protected $_auto = array(
        //array(填充字段,填充内容,填充条件,附加规则)
        //array('status','1',CommonModel:: MODEL_INSERT),
        array('operator_id','mGetOperatorId',CommonModel:: MODEL_BOTH,'function'),
        array('operator','mGetOperator',CommonModel:: MODEL_BOTH,'function'),
        array('created_at','mGetDate',CommonModel:: MODEL_INSERT,'function'),
        array('updated_at','mGetDate',CommonModel:: MODEL_UPDATE,'function')
    );

    function getContact($id){
        if(is_array($id)){
            $where = $id;
        }else{
            $where['contact.id'] = $id;
        }
        $this->alias('contact')->field('contact.*,province.name as contact_province_name,
        city.name as contact_city_name,district.name as contact_district_name');
        $this->join('__AREAS__ province ON province.id = contact.contact_province','LEFT');
        $this->join('__AREAS__ city ON city.id = contact.contact_city','LEFT');
        $this->join('__AREAS__ district ON district.id = contact.contact_district','LEFT');
        $data = $this->where($where)->find();
        return $data;
    }

    function getContactByBm($member_id){
        $where['member_id'] = $member_id;
        return $this->getContact($where);
    }

    function addContact($data){
        $result = $this->create($data, self::MODEL_INSERT);
        if($result !== false){
            $result = $this->add();
            if($result === false) $this->error = $this->getDbError();
        }
        return $result;
    }

    function updateContact($data){
        $result = $this->create($data);
        if($result !== false){
            $result = $this->save();
            if($result === false){
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    public function saveContact($data)
    {
        if (!isset($data[$this->getPk()]) && isset($data['member_id'])) {
            $contact = $this->where(array('member_id' => $data['member_id']))->find();
            if (!empty($contact)) {
                $data[$this->getPk()] = $contact[$this->getPk()];
            }
        }

        $result = $this->create($data);
        if ($result !== false) {
            if (isset($this->data[$this->getPk()])) {
                $result = $this->save();
            } else {
                $result = $this->add();
            }
            if ($result === false) {
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }
}
