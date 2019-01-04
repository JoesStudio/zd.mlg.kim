<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-14
 * Time: 10:11
 */

namespace Supplier\Model;


use Common\Model\CommonModel;

class TfModel extends CommonModel
{
    public $statuses = array(
        //'-1'    => '待处理',
        '1'     => '已上架',
        '0'     => '未上架',
    );

    protected $tableName = 'textile_fabric';

    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('vend_id', 'require', '供应商id不能为空！', 1, 'regex', CommonModel:: MODEL_INSERT),
        array('name', 'require', '品名称不能为空！', 1, 'regex', CommonModel:: MODEL_BOTH),
        array('code', 'require', '自编码不能为空！', 1, 'regex', CommonModel:: MODEL_BOTH),
    );

    protected $_auto = array(
        //array(填充字段,填充内容,填充条件,附加规则)
        array('created_at','mGetDate',CommonModel:: MODEL_INSERT,'function'),
        array('create_date','mGetDate',CommonModel:: MODEL_INSERT,'function'),
        array('modify_date','mGetDate',CommonModel:: MODEL_UPDATE,'function'),
    );

    function getTf($id, $fields="supplier,cat,sku"){
        $fields = explode(',',$fields);
        if(is_array($id)){
            $where = $id;
        }else{
            $where['id'] = $id;
        }
        if(isset($where['tf_code'])){
            $where['CONCAT(vend_id,cat_code,name_code,code)'] = $where['tf_code'];
            unset($where['tf_code']);
        }
        $tf = $this
            ->field('*,vend_id as supplier_id,CONCAT(vend_id,cat_code,name_code,code) as tf_code')
            ->where($where)->find();
        if($tf){
            if(in_array('supplier',$fields)){
                $tf['supplier'] = D('BizMember')->getMember($tf['vend_id']);
            }
            if(in_array('cat',$fields)){
                $tf['cat'] = D('Tf/Cat')->getCat($tf['cid']);
            }
            if(in_array('sku',$fields)){
                $tf['sku'] = D('Tf/Sku')->where(array('tf_id' => $tf['id']))->order('id DESC')->select();
                $prices = array();
                $group_prices = array();
                foreach($tf['sku'] as $sku){
                    $prices[] = $sku['sku_price'];
                    $group_prices[] = $sku['group_price'];
                }
                $tf['min_price'] = min($prices);
                $tf['max_price'] = max($prices);
                $tf['min_group_price'] = min($group_prices);
                $tf['max_group_price'] = max($group_prices);
            }
            $tf['tfname'] = D('TextileFabricName')->where(array('code'=>$tf['name_code']))->find();
            $tf['tf_code'] = $tf['cat']['code'].$tf['name_code'].$tf['code'].$tf['vend_id'];

            // $tf['img'] = str_replace("&quot;", '"', $tf['img']);
            // $tf['img'] = str_replace("'", '"', $tf['img']);
            // $tf['img'] = json_decode($tf['img'], true);
        }
        return $tf;
    }

    function getTfByCode($code){
        $where['tf_code'] = $code;
        return $this->getTf($where);
    }

    function getTfBySkuId($sku_id){
        $data = array();
        $sku = D('Tf/Sku')->find($sku_id);
        if($sku){
            $data = $this->getTf($sku['tf_id']);
            $data['selected_sku'] = $sku;
        }
        return $data;
    }

    /*
     * 获取未删除的记录
     * @param int $currentPage 当前分页
     * @param in $pageSize 分页记录数
     * @return array
     */
    function fabrics($tag='', $pageSize= 20, $pagetpl='', $tplName='default'){
       // $tag['limit'] = (I("post.p", 1) - 1) * 1 . ", 1";

        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
            if(isset($tag['cid'])){
                $where['cid'] = $tag['cid'];
            }
            if(isset($tag['_string'])){
                $where['_string'] = $tag['_string'];
            }
        }

        if(isset($where['on_sale'])){
            $where['IFNULL(shelves.on_sale,0)'] = $tag['on_sale'];
            unset($where['on_sale']);
        }

        $field = !empty($tag['field']) ? $tag['field'] : 'tf.*,
        CONCAT(tf.vend_id,tf.cat_code,tf.name_code,tf.code) as tf_code,IFNULL(shelves.on_sale,0) as on_sale';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'tf.created_at DESC,id DESC';

        $subData = isset($tag['subData']) ? $tag['subData'] : 'cat,supplier';
        $subData = explode(',', $subData);

        foreach ($where as $key => $value) {
            $ignore_fields = array('field', 'order', 'group', 'limit', 'subData', 'join');
            if (in_array($key, $ignore_fields)) {
                unset($where[$key]);
                continue;
            }
            if (strpos($key, 'tf.') === false 
            && strpos($key, '(') === false && $key != 'on_sale'
            && $key !== '_string' && $key !== '_complex') {
                $where["tf.$key"] = $value;
                unset($where[$key]);
            }
        }

        $join = 'LEFT JOIN __TEXTILE_FABRIC_SHELVES__ shelves ON shelves.tf_id=tf.id';

        $data['total_condition'] = $this->alias('tf')->join($join)->where($where)->count();
        $data['total'] = $data['total_condition'];

        $this->alias('tf')->field($field)->join($join)->limit($limit)->where($where)->order($order);

        $rs = $this->select();

        $fabrics = array();
        if(!empty($rs)){
            $cids = array();
            $uids = array();
            foreach($rs as $row){
                $row['skus'] = D('Tf/Sku')->where(array('tf_id'=>$row['id']))->select();
                $row['img'] = str_replace("&quot;", '"', $row['img']);
                $row['img'] = str_replace("'", '"', $row['img']);
                
                $row['img'] = json_decode($row['img'],true);
                $fabrics[$row['id']] = $row;
                array_push($cids, $row['cid']);
                array_push($uids, $row['vend_id']);
            }
            $cids = array_unique($cids);
            $uids = array_unique($uids);
            if (in_array('cat', $subData)) {
                $cats = D('Tf/Cat')->cats(array('id'=>array('IN',$cids)));
            }
            if (in_array('supplier', $subData)) {
                $members = D('BizMember')->getMembersNoPaged(array('id'=>array('IN',$uids)));
            }


            if (!empty($subData)) {
                foreach($fabrics as $key=>$row){
                    if (in_array('cat', $subData)) {
                        $fabrics[$key]['cat'] = $cats[$row['cid']];
                    }
                    if (in_array('supplier', $subData)) {
                        $fabrics[$key]['supplier'] = $members[$row['vend_id']];
                    }
                }
            }
        }

        $data['data'] = $fabrics;

        return $data;
    }

    function getTfPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->fabrics($tag, $pageSize, $pagetpl, $tplName);
    }

    function getTfNoPaged($tag=''){
        $data = $this->fabrics($tag);
        return $data['data'];
    }

    function term_fabrics($tag){
        //
    }

    function addTf($data){
        $result = $this->create($data);
        if($result !== false){
            $result = $this->add();
            if($result === false){
                $this->error = $this->getDbError();
            }
        }
        if(isset($data['on_sale']) && $result !== false){
            M('TextileFabricShelves')->add(array(
                'tf_id'=>$result,
                'on_sale'=>$data['on_sale']
            ));
        }
        return $result;
    }

    function updateTf($data){
        $result = $this->create($data);
        if($result !== false){
            $result = $this->where(array('id'=>$data['id']))->save();
            if($result === false){
                $this->error = $this->getDbError();
            }
        }
        if(isset($data['on_sale'])){
            M('TextileFabricShelves')->where(array('tf_id'=>$data['id']))->setField('on_sale', $data['on_sale']);
        }
        return $result;
    }

    function deleteTf($id){

        $result = $this->delete($id);

        if($result !== false){
            M('TextileFabricShelves')->where(array('tf_id'=>$id))->delete();
            D('Tf/Sku')->where(array('tf_id'=>$id))->delete();
            D('Tf/Prop')->where(array('tf_id'=>$id))->delete();
        }else{
            $this->error = $this->getDbError();
        }
        return $result;
    }
}
