<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-12-07
 * Time: 10:44
 */

namespace Collect\Model;


use Common\Model\CommonModel;

class CollectModel extends CommonModel
{
    protected $tableName = 'collect_goods';

    public $types = array(
        1   => '面料',
        2   => '色卡',
        3   => '面料商',
    );

    /*
     * 获取收藏记录
     * @param int $currentPage 当前分页
     * @param in $pageSize 分页记录数
     * @return array
     */
    function colls($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);

            if(isset($tag['user_id'])){
                $where['user_id'] = $tag['user_id'];
            }

            if(isset($tag['type'])){
                $where['type'] = $tag['type'];
            }
        }

        $where['type'] = isset($where['type']) ? $where['type']:1;
        $type = $where['type'];

        $field = !empty($tag['field']) ? $tag['field'] : '*';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'create_date DESC';

        $data['total'] = $this->where($where)->count();

        $this->field($field)->where($where)->order($order);
        if($pageSize == 0){
            $this->limit($limit);
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show($tplName);
            $data['totalPages'] = $page->getTotalPages();
        }
        $rs = $this->select();


        if(!empty($rs)){
            $gids = array();
            foreach($rs as $row){
                array_push($gids, $row['goods_id']);
            }
            $gids = array_unique($gids);
            if($type == 3){
                $goods = D('BizMember')->getMembersNoPaged(array('id'=>array('IN',$gids)));
                foreach($goods as $key => $value){
                    $goods[$key]['img']['photo'] = 'data/upload/' . $value['biz_logo'];
                }   
            }elseif($type == 2){
                $goods = D('Colorcard/Colorcard')->getCardsNoPaged(array('card_id'=>array('IN',$gids)));

                $supplier_ids = array();
                foreach($goods as $card_id => $card){
                    array_push($supplier_ids, $card['vend_id']);
                }
                if(!empty($supplier_ids)){
                $suppliers = D('BizMember')->getMembersNoPaged(array('id'=>array('IN',$supplier_ids)));
                }
                foreach($goods as $card_id => $card){
                    $goods[$card_id]['supplier'] = $suppliers[$card['vend_id']];
                }
            }else{
                $goods = D('Tf/Tf')->getTfNoPaged(array('id'=>array('IN',$gids)));
            }

            foreach($rs as $key=>$row){
                $rs[$key]['goods'] = $goods[$row['goods_id']];
            }
        }

        $data['data'] = $rs;

        return $data;
    }

    function getCollPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->colls($tag, $pageSize, $pagetpl, $tplName);
    }

    function getCollNoPaged($tag=''){
        return $this->colls($tag);
    }

    function getGoodsIds($tag=''){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
            if(!empty($tag['user_id'])){
                $where['user_id'] = $tag['user_id'];
            }
            if(!empty($tag['supplier_id'])){
                $where['supplier_id'] = $tag['supplier_id'];
            }
            if(!empty($tag['type'])){
                $where['type'] = $tag['type'];
            }
            if(!empty($tag['goods_id'])){
                $where['goods_id'] = $tag['goods_id'];
            }
            if(!empty($tag['_string'])){
                $where['_string'] = $tag['_string'];
            }
        }

        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'create_date DESC';

        $this->where($where)->order($order);
        if(!empty($tag['group'])){
            $this->group($tag['group']);
        }

        $this->limit($limit);
        $data = $this->getField('goods_id',true);

        return $data;
    }

    function getUserIds($tag=''){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
            if(!empty($tag['user_id'])){
                $where['user_id'] = $tag['user_id'];
            }
            if(!empty($tag['supplier_id'])){
                $where['supplier_id'] = $tag['supplier_id'];
            }
            if(!empty($tag['type'])){
                $where['type'] = $tag['type'];
            }
            if(!empty($tag['goods_id'])){
                $where['goods_id'] = $tag['goods_id'];
            }
            if(!empty($tag['_string'])){
                $where['_string'] = $tag['_string'];
            }
        }

        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'create_date DESC';

        $this->where($where)->order($order);
        if(!empty($tag['group'])){
            $this->group($tag['group']);
        }

        $this->limit($limit);
        $data = $this->getField('user_id',true);

        return $data;
    }

    function collectStatus($goods_id, $uid, $type=1){
        $where['goods_id'] = $goods_id;
        $where['user_id'] = $uid;
        $where['type'] = $type;
        $count = $this->where($where)->count();
        return $count;
    }

    function toggle($goods_id, $uid, $type=1){
        if($this->collectStatus($goods_id, $uid, $type)){
            $where['goods_id'] = $goods_id;
            $where['user_id'] = $uid;
            $where['type'] = $type;
            $result = $this->where($where)->delete();
        }else{
            switch($type){
                case 3:
                    $supplier_id = $goods_id;
                    break;
                case 2:
                    $supplier_id = D('Colorcard/Colorcard')->where(array('card_id'=>$goods_id))->getField('supplier_id');
                    break;
                default:
                    $supplier_id = D('Tf/Tf')->where(array('id'=>$goods_id))->getField('vend_id');
            }
            $data['goods_id'] = $goods_id;
            $data['user_id'] = $uid;
            $data['supplier_id'] = $supplier_id;
            $data['create_date'] = date('Y-m-d H:i:s');
            $data['is_attention'] = 0;
            $data['type'] = $type;
            $result = $this->add($data);
            if ($result !== false) {
                D('Supplier/Fans')
                ->where("user_id=$uid AND member_id=$supplier_id")
                ->setField('favorite_date', $data['create_date']);
            }
        }

        if(!$result){
            $this->error = $this->getDbError();
        }
        return $result;
    }

    public function log_fans_collect_time($user_id, $target_id, $type)
    {
        if ($type == 1) {
            $target = D('Tf/Tf')->where("id=$target_id")->find();
            $supplier_id = $target['vend_id'];
        } elseif ($type == 2) {
            $target = D('Colorcard/Colorcard')->where("id=$target_id")->find();
            $supplier_id = $target['supplier_id'];
        } elseif ($type == 3) {
            $target = D('BizMember')->where("id=$target_id")->find();
            $supplier_id = $target['id'];
        }
        if ($supplier_id > 0) {
            $browsing_time = date('Y-m-d H:i:s');
            D('Supplier/Fans')
            ->where("user_id=$user_id AND member_id=$supplier_id")
            ->setField('favorite_date', $browsing_time);
        }
    }
}