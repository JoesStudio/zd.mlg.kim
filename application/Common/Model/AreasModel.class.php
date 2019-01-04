<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-04
 * Time: 17:33
 */

namespace Common\Model;


class AreasModel extends CommonModel
{
    function getAreas($id=0){
        return $this->where(array('parentId'=>$id))->select();
    }

    function getSelectedByDistrict($id){
        $area = $this
            ->field('a.id as district,a.name as district_name,
            b.id as city, b.name as city_name,
            c.id as province, c.name as province_name')
            ->alias('a')
            ->join('__AREAS__ b ON b.id = a.parentId')
            ->join('__AREAS__ c ON c.id = b.parentId')
            ->where(array('a.id'=>$id))
            ->find();
        return $area;
    }

    function getAreasByDistrict($id){
        $data['selected'] = $this->getSelectedByDistrict($id);
        $data['areas']['provinces'] = $this->getAreas(0);
        $data['areas']['cities'] = $this->getAreas($data['selected']['province']);
        $data['areas']['districts'] = $this->getAreas($data['selected']['city']);

        return $data;
    }
}