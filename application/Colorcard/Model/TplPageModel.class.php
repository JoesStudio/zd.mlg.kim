<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-12-26
 * Time: 10:18
 */

namespace Colorcard\Model;


use Common\Model\CommonModel;

class TplPageModel extends CommonModel
{
    protected $tableName = 'colorcard_tpl_page';


    public $statuses = array(
        '0'     => '禁用',
        '1'     => '启用',
    );

    public $types = array(
        '1'     => '面料',
        '2'     => '图片',
        '3'     => '双面面料',
        '4'     => '封面',
        '5'     => '封底',
    );

    function getPages($tpl_id,$all=false){
        $where['tpl_id'] = $tpl_id;
        if(!$all){
            $where['pg_type'] = array('NOTIN', '4,5');
        }
        $data = $this->where($where)->select();
        foreach($data as $key=>$row){
            $data[$key]['status_text'] = $this->statuses[$row['pg_status']];
        }
        return $data;
    }

    function savePage($data){
        $result = $this->create($data);
        if($result !== false){
            if($this->__isset($this->getPk())){
                $result = $this->save();
            }else{
                $result = $this->add();
            }
            if($result === false){
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

}