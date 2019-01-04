<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-12-26
 * Time: 10:13
 */

namespace Colorcard\Model;


use Common\Model\CommonModel;

class TplModel extends CommonModel
{
    protected $tableName = 'colorcard_tpl';

    public $statuses = array(
        '0'     => '禁用',
        '1'     => '启用',
    );

    //自动验证
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('tpl_type', 'require', '请选择模板类型', 1, 'regex', CommonModel:: MODEL_INSERT ),
    );
    /*
     * 获取未删除的记录
     * @param int $currentPage 当前分页
     * @param in $pageSize 分页记录数
     * @return array
     */
    function tpls($tag='',$where=array(), $pageSize=0, $pagetpl='', $tplName='default'){
        $where = is_array($where) ? $where:array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
            if(isset($tag['isentity'])){
                $where['isentity'] = $tag['isentity'];
            }
        }

        $field = !empty($tag['field']) ? $tag['field'] : '*';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'tpl_id DESC';

        $data['total'] = $this->where($where)->count();

        $this->field($field)->where($where)->order($order);
        if(empty($pageSize)){
            if (!empty($tag['limit'])) {
                $this->limit($limit);
            }
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
        }

        $rs = $this->select();

        $tmp = array();
        foreach($rs as $row){
            $row['smeta'] = json_decode($row['smeta'], true);
            $row['status_text'] = $this->statuses[$row['tpl_status']];
            $tmp[$row['tpl_id']] = $row;
        }

        $data['data'] = $tmp;

        return $data;
    }

    /*
     * 获得分页列表
     */
    function getTplsPaged($tag='', $where=array(), $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->tpls($tag, $where, $pageSize, $pagetpl, $tplName);
    }

    /*
     * 获得不分页的表
     */
    function getTplsNoPaged($tag='', $where=array()){
        $data = $this->tpls($tag, $where);
        return $data['data'];
    }

    function getTpl($id){
        $data = $this->find($id);
        $data['smeta'] = json_decode($data['smeta'], true);
        $data['pages'] = D('Colorcard/TplPage')->getPages($id);
        return $data;
    }

    function addTpl($data){
        $result = $this->create($data);
        if($result !== false){
            $result = $this->add();
            if($result === false){
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    function updateTpl($data){
        $result = $this->create($data);
        if($result !== false){
            $result = $this->save();
            if($result === false){
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    function saveTpl($data){
        if(is_array($data['smeta'])){
            $data['smeta'] = json_encode($data['smeta'], 256);
        }
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