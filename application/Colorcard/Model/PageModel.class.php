<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-08
 * Time: 15:28
 */

namespace Colorcard\Model;



use Common\Model\CommonModel;

class PageModel extends CommonModel
{
    protected $tableName = 'colorcard_page';

    function getPages($id){
        if(is_array($id)){
            $where = $id;
        }else{
            $where['card_id'] = $id;
        }
        $rs = $this->where($where)->select();

        $data = array();
        $ids = array();
        foreach($rs as $row){
            array_push($ids, $row['page_id']);
            $data[$row['card_id']][$row['page_id']] = $row;
        }
        if (!empty($ids)) {
            $item_groups = D('Colorcard/Item')->getItems(array('page_id'=>array('IN',$ids)));
            foreach($data as $card_id => $pages){
                foreach($pages as $page_id => $page){
                    $data[$card_id][$page_id]['items'] = $item_groups[$page_id];
                }
            }
        }
        return $data;
    }
}