<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-09
 * Time: 18:05
 */

namespace Common\Model;


class MemberModel extends CommonModel
{
    protected $tableName = 'biz_member';

    function updateMember($data, $where=array()){
        if($this->create($data)){
            if($where){
                $this->where($where);
            }
            return $this->save();
        }else{
            return 0;
        }
    }
}