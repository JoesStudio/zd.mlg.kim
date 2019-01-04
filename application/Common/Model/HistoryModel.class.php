<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-01-22
 * Time: 16:05
 */

namespace Common\Model;


class HistoryModel extends CommonModel
{

    function recs($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
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
        }

        $field = !empty($tag['field']) ? $tag['field'] : '*';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'create_date DESC';

        if(!empty($tag['group'])){
            $this->group($tag['group']);
        }
        $data['total'] = $this->where($where)->count();

        $this->field($field)->where($where)->order($order);
        if(!empty($tag['group'])){
            $this->group($tag['group']);
        }

        if(empty($pageSize)){
            $this->limit($limit);
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
        }
        $data['data'] = $this->select();

        return $data;
    }

    function getRecsPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->recs($tag, $pageSize, $pagetpl, $tplName);
    }

    function getRecsNoPaged($tag=''){
        $data = $this->recs($tag);
        return $data['data'];
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

        $where['isdelete'] = isset($where['isdelete']) ? $where['isdelete']:0;

        if (isset($tag['group'])) {
            $table = $this->where($where)->order($order)->buildSql();
            $this->table($table)->alias('tb');
        } else {
            $this->where($where);
        }
        $this->order($order);
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

        $where['isdelete'] = isset($where['isdelete']) ? $where['isdelete']:0;

        if (isset($tag['group'])) {
            $table = $this->where($where)->order($order)->buildSql();
            $this->table($table)->alias('tb');
        } else {
            $this->where($where);
        }
        $this->order($order);
        if(!empty($tag['group'])){
            $this->group($tag['group']);
        }

        $this->limit($limit);
        $data = $this->getField('user_id',true);

        return $data;
    }

    function addHistory($goods_id,$user_id,$supplier_id,$type){
        $last_view = $this->where(array(
            'goods_id'      => $goods_id,
            'user_id'       => $user_id,
            'supplier_id'   => $supplier_id,
            'type'          => $type,
            'isdelete'      => 0,
        ))->order('create_date desc')->getField('create_date');
        
        //15分钟内再次打开不计入历史纪录
        if(time() - strtotime($last_view) < 15 * 60 ){
            return false;
        }

        $data = array(
            'goods_id'      => $goods_id,
            'user_id'       => $user_id,
            'supplier_id'   => $supplier_id,
            'type'          => $type,
            'create_date'   => date('Y-m-d H:i:s'),
        );
        $result = $this->add($data);
        if($result === false){
            $this->error = $this->getDbError();
        } else {
            $fans = D('Supplier/Fans')
            ->where("user_id=$user_id AND member_id=$supplier_id")
            ->setField('brower_date', $data['create_date']);
        }
        return $result;
    }

    function deleteRecByGoods($goods_id,$type,$user_id=null){
        $where['goods_id'] = $goods_id;
        $where['type'] = $type;
        $where['isdelete'] = 0;
        if(!is_null($user_id)){
            $where['user_id'] = $user_id;
        }
        $result = $this->where($where)->setField('isdelete',1);
        if($result === false){
            $this->error = $this->getDbError();
        }
        return $result;
    }

    
    public function log_fans_browsing_time($user_id, $target_id, $type)
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
            ->setField('brower_date', $browsing_time);

        }
    }

    //获取7天内的店铺访问量
    public function getShopVisitHistory($supplyId, $interval, $startDate) {
        $data = D("History")->where("type = 3 and supplier_id = %d and TO_DAYS('$startDate') - TO_DAYS(mlg_history.create_date) <= '$interval' and TO_DAYS('$startDate') - TO_DAYS(mlg_history.create_date) >= 0", $supplyId)->field("
            count(id) as visit_count,
            DATE_FORMAT(mlg_history.create_date, '%Y/%m/%d') as day
        ")->group("day")->cache(60 * 5)->select();

        for ($i = 0; $i <= $interval; $i++) {
            $roomVisitCount[$i]["date"] = date("m/d", strtotime(date("Y-m-d") . " - $i day"));
            $tempDate = date("Y/m/d", strtotime(date("Y-m-d") . " - $i day"));
            $dataLength = count($data);

            if ($dataLength) {
                //遍历数据
                for ($j = 0; $j < $dataLength; $j++) {
                    if ($data[$j]['day'] == $tempDate) {
                        $roomVisitCount[$i]['visit_count'] = intval($data[$j]['visit_count']);
                        break;
                    }
                }

                if ($roomVisitCount[$i]['visit_count'] == null) {
                    $roomVisitCount[$i]['visit_count'] = 0;
                }
            } else {
                $roomVisitCount[$i]['visit_count'] = 0;
            }
        }

        return $roomVisitCount;
    }

    //获取7天内的访客书
    public function getShopUserVisitHistory($supplyId, $interval, $startDate) {
        $data = D("History")->where("type = 3 and supplier_id = %d and TO_DAYS('$startDate') - TO_DAYS(mlg_history.create_date) <= '$interval' and TO_DAYS('$startDate') - TO_DAYS(mlg_history.create_date) >= 0", $supplyId)->field("
            count(select * from mlg_history b where b.) as visit_count,
            DATE_FORMAT(mlg_history.create_date, '%Y/%m/%d') as day
        ")->group("day")->cache(60 * 5)->select();

        for ($i = 0; $i <= $interval; $i++) {
            $roomVisitCount[$i]["date"] = date("m/d", strtotime(date("Y-m-d") . " - $i day"));
            $tempDate = date("Y/m/d", strtotime(date("Y-m-d") . " - $i day"));
            $dataLength = count($data);

            if ($dataLength) {
                //遍历数据
                for ($j = 0; $j < $dataLength; $j++) {
                    if ($data[$j]['day'] == $tempDate) {
                        $roomVisitCount[$i]['visit_count'] = intval($data[$j]['visit_count']);
                        break;
                    }
                }

                if ($roomVisitCount[$i]['visit_count'] == null) {
                    $roomVisitCount[$i]['visit_count'] = 0;
                }
            } else {
                $roomVisitCount[$i]['visit_count'] = 0;
            }
        }

        return $roomVisitCount;
    }

    public function getColorboardHistory($supplyId, $interval, $startDate) {
        $data = D("CollectColorboard")->where("type = 1 and supplier_id = %d and TO_DAYS('$startDate') - TO_DAYS(mlg_collect_colorboard.create_date) <= '$interval' and TO_DAYS('$startDate') - TO_DAYS(mlg_collect_colorboard.create_date) >= 0", $supplyId)->field("
            count(rec_id) as visit_count,
            DATE_FORMAT(mlg_collect_colorboard.create_date, '%Y/%m/%d') as day
        ")->group("day")->select();

        for ($i = 0; $i <= $interval; $i++) {
            $roomVisitCount[$i]["date"] = date("m/d", strtotime(date("Y-m-d") . " - $i day"));
            $tempDate = date("Y/m/d", strtotime(date("Y-m-d") . " - $i day"));
            $dataLength = count($data);

            if ($dataLength) {
                //遍历数据
                for ($j = 0; $j < $dataLength; $j++) {
                    if ($data[$j]['day'] == $tempDate) {
                        $roomVisitCount[$i]['visit_count'] = intval($data[$j]['visit_count']);
                        break;
                    }
                }

                if ($roomVisitCount[$i]['visit_count'] == null) {
                    $roomVisitCount[$i]['visit_count'] = 0;
                }
            } else {
                $roomVisitCount[$i]['visit_count'] = 0;
            }
        }

        return $roomVisitCount;
    }


    public function getGoodsHistory($supplyId, $interval, $startDate) {
        $data = D("CollectGoods")->where("type = 1 and supplier_id = %d and TO_DAYS('$startDate') - TO_DAYS(mlg_collect_goods.create_date) <= '$interval' and TO_DAYS('$startDate') - TO_DAYS(mlg_collect_goods.create_date) >= 0", $supplyId)->field("
            count(rec_id) as visit_count,
            DATE_FORMAT(mlg_collect_goods.create_date, '%Y/%m/%d') as day
        ")->group("day")->select();

        for ($i = 0; $i <= $interval; $i++) {
            $roomVisitCount[$i]["date"] = date("m/d", strtotime(date("Y-m-d") . " - $i day"));
            $tempDate = date("Y/m/d", strtotime(date("Y-m-d") . " - $i day"));
            $dataLength = count($data);

            if ($dataLength) {
                //遍历数据
                for ($j = 0; $j < $dataLength; $j++) {
                    if ($data[$j]['day'] == $tempDate) {
                        $roomVisitCount[$i]['visit_count'] = intval($data[$j]['visit_count']);
                        break;
                    }
                }

                if ($roomVisitCount[$i]['visit_count'] == null) {
                    $roomVisitCount[$i]['visit_count'] = 0;
                }
            } else {
                $roomVisitCount[$i]['visit_count'] = 0;
            }
        }

        return $roomVisitCount;
    }
}