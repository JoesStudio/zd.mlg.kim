<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-12-15
 * Time: 10:20
 */

namespace Tf\Model;


use Common\Model\CommonModel;

class SkuModel extends CommonModel
{
    protected $tableName = 'textile_fabric_sku';

    public $types = array(
        1   => '文字',
        2   => '颜色',
        3   => '图片',
    );

    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('color_code', 'require', '颜色编号不能为空', 1, 'regex', CommonModel:: MODEL_INSERT  ),
        array('color_code', 'require', '颜色编号不能为空', 1, 'regex', CommonModel:: MODEL_UPDATE  ),
    );

    function tfSkuList($tf_id){
        $data = $this->skus("tf_id:$tf_id;");
        return $data;
    }

    function skus($tag='', $where=array()){
        $where = is_array($where) ? $where:array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);

            if(isset($tag['tf_id'])){
                $where['tf_id'] = $tag['tf_id'];
            }
        }

        $field = !empty($tag['field']) ? $tag['field'] : '*';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'id DESC';

        $rs = $this->field($field)->where($where)->order($order)->limit($limit)->select();

        $data = array();
        if($rs){
            foreach($rs as $row){
                $data[$row['id']] = $row;
            }
        }


        return $data;
    }

    function formatData2($request, $tf_id){
        $data = array();
        foreach($request["sku_id"] as $k => $v) {
            unset($skuData);
            if(!empty($request["One"][$k])){
                $skuData = array(
                    'id'        => $request["sku_id"][$k],
                    'tf_id'     => $tf_id,
                    //'key_id'    => $request["sku_key_id"][$k],
                    'key_name'  => $request["One"][$k],
                    //'value_id'  => $request["sku_value_id"][$k],
                    'value_text'=> $request["Two"][$k],
                    //'sku_img'   => $request["sku_img"][$k],
                    'sku_price' => $request["sku_price"][$k],
                    'sku_num'   => $request["sku_num"][$k],
                    'sku_unit'  => $request["sku_unit"][$k],
                    'min_charge'  => $request["min_charge"][$k],
                );
                if(!is_numeric($k)){
                    unset($skuData["id"]);
                }
                $data[] = $skuData;
            }
        }

        return $data;
    }

    function formatData($request, $tf_id){
        $data = array();
        foreach($request as $k=>$sku){
            if(!empty($sku["One"])){
                $skuData = array(
                    'id'        => $k,
                    'tf_id'     => $tf_id,
                    //'key_id'    => $sku["sku_key_id"],
                    'key_name'  => $sku["One"],
                    //'value_id'  => $sku["sku_value_id"],
                    'value_text'=> $sku["Two"],
                    //'sku_img'   => $sku["sku_img"],
                    'sku_price' => $sku["sku_price"],
                    'sku_num'   => $sku["sku_num"],
                    'sku_unit'  => $sku["sku_unit"],
                    'min_charge'  => $sku["min_charge"],
                );
                if(!is_numeric($k)){
                    unset($skuData["id"]);
                }
                $data[] = $skuData;
            }
        }
        return $data;
    }

    function SaveToSku($tf_id, $data){
        $save_ids = array();
        foreach($data as $v){
            if(isset($v['id'])){
                array_push($save_ids, $v['id']);
            }
        }
        $whereDel['tf_id'] = $tf_id;
        if(!empty($save_ids)){
            $whereDel['id'] = array('NOT IN',$save_ids);
        }
        $sReturn = $this->where($whereDel)->delete();//删除垃圾数据

        $result = 0;
        foreach($data as $k=>$v){
            $result = $this->create($v);
            if($result !== false){
                if(isset($v['id'])){
                    $result = $this->save();
                }else{
                    $result = $this->add();
                }

                if($result === false){
                    $this->error = $this->getDbError();
                }
            }
        }
        return $result;
    }

    public function saveSku($data)
    {
        if (!isset($data[$this->getPk()])) {
            if (!isset($data['min_charge']) || $data['min_charge'] == '') {
                $data['min_charge'] = D('Tf/Tf')->where(array('id' => $data['tf_id']))->getField('min_charge');
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
