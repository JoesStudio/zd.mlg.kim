<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-08
 * Time: 15:28
 */

namespace Colorcard\Model;



use Common\Model\CommonModel;

class ItemModel extends CommonModel
{
    protected $tableName = 'colorcard_item';

    function getItems($id){
        if(is_array($id)){
            $where = $id;
        }else{
            $where['page_id'] = $id;
        }
        $rs = $this->where($where)->select();

        $data = array();
        foreach($rs as $row){
            $row['tf'] = json_decode($row['item_fabric'], true);
            $data[$row['page_id']][$row['item_id']] = $row;
        }
        return $data;
    }

    public function saveItem($data)
    {
        $result = $this->create($data);
        if ($result !== false) {
            $result = isset($this->data[$this->getPk()]) ? $this->save():$this->add();
            if ($result === false) {
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }
}