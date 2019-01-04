<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-04-20
 * Time: 10:02
 */

namespace Colorcard\Model;


use Common\Model\CommonModel;

class OfferModel extends CommonModel
{
    protected $tableName = 'colorcard_offer';
    //自动验证
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('tpl_id', 'require', '请选择版式！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        array('tf_qty', 'require', '请选择产品数量！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        array('pattern_price', 'require', '请输入打版价格！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        array('print_qty', 'require', '请选择印刷数量！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        array('big_price', 'require', '请输入大货印刷单价！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        array('photo_price', 'require', '请输入拍照单价！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        array('design_price', 'require', '请输入设计单价！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        array('overall_price', 'require', '请输入统筹单价！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        array('init_profit', 'require', '请输入初次制作利润！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        array('next_profit', 'require', '请输入二次制作利润！', 1, 'regex', CommonModel:: MODEL_INSERT ),
    );

    protected $_auto = array(
        //array(填充字段,填充内容,填充条件,附加规则)
        //array('create_date','mGetDate',CommonModel:: MODEL_INSERT,'function'),
    );

    public function rows($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
            if (isset($tag['tpl_id'])) {
                $where['tpl_id'] = $tag['tpl_id'];
            }
            if (isset($tag['where'])) {
                $where['_string'] = $tag['_string'];
            }
        }

        $alias = 'main';
        $field = !empty($tag['field']) ? $tag['field'] : "*";
        $order = !empty($tag['order']) ? $tag['order'] : "$alias.tpl_id ASC,$alias.tf_qty ASC,
        $alias.print_qty ASC,$alias.id ASC";

        foreach ($where as $key => $value) {
            $ignore_fields = array('field', 'order', 'group', 'limit', 'join', '_string', 'where');
            if (in_array($key, $ignore_fields)) {
                unset($where[$key]);
                continue;
            }
            if (strpos($key, '.') === false) {
                $where["$alias.$key"] = $value;
                unset($where[$key]);
            }
        }

        $field .= ",tpl.tpl_frontcover as tpl_thumb";
        $join1 = "INNER JOIN __COLORCARD_TPL__ tpl ON tpl.tpl_id=$alias.tpl_id";

        $data['total'] = $this->alias($alias)->join($join1)->where($where)->count();

        $this->alias($alias)->field($field)->join($join1)->where($where)->order($order);
        if(empty($pageSize)){
            if (!empty($tag['limit'])) {
                $this->limit($tag['limit']);
            }
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }
        $rs = $this->select();

        $data['data'] = $rs;
        return $data;
    }

    public function getRowsPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->rows($tag, $pageSize, $pagetpl, $tplName);
    }

    public function getRowsNoPaged($tag=''){
        $data = $this->rows($tag);
        return $data['data'];
    }

    public function getRow($id){
        $alias = 'main';
        if(is_array($id)){
            $where = $id;
            $id = $where["$alias.id"];
        }else{
            $where["$alias.id"] = $id;
        }
        $field = "$alias.*,tpl.tpl_frontcover as tpl_thumb";
        $join1 = "INNER JOIN __COLORCARD_TPL__ tpl ON tpl.tpl_id=$alias.tpl_id";
        return $this->alias($alias)->field($field)->join($join1)->where($where)->find();
    }

    public function saveRow($data){
        $result = $this->create($data);
        if($result !== false){
            $result = isset($this->data[$this->getPk()]) ? $this->save():$this->add();
            if($result === false){
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

}