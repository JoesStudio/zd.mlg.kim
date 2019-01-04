<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-15
 * Time: 11:32
 */

namespace Cart\Model;


use Common\Model\CommonModel;

class CartModel extends CommonModel
{
    /*
     * 添加商品到购物车
     */
    function addItem($user_id=0,$sku_id,$number=1){
        $sku = D('Tf/Sku')->find($sku_id);
        if(empty($sku)) return false;

        $tf = D('Tf/Tf')->find($sku['tf_id']);
        if(empty($tf)) return false;

        $where['user_id'] = $user_id;
        $where['goods_sku_id'] = $sku_id;

        $cart = $this->where($where)->find();
        if(!empty($cart)){
            $data = array(
                'rec_id'        => $cart['rec_id'],
                'goods_name'    => $tf['name'],
                'goods_price'   => empty($sku['sku_price']) ? 0:$sku['sku_price'],
                'goods_number'  => $cart['goods_number'] + $number,
                'goods_sku'     => json_encode($sku, 256),
            );
        }else{
            $data = array(
                'user_id'       => $user_id,
                'session_id'    => session_id(),
                'goods_id'      => $sku['tf_id'],
                'goods_name'    => $tf['name'],
                'goods_price'   => empty($sku['sku_price']) ? 0:$sku['sku_price'],
                'goods_number'  => $number,
                'goods_sku_id'  => $sku_id,
                'goods_sku'     => json_encode($sku, 256),
                'is_colorcard'  => 0,
                'parent_id'     => 0,
                'can_handsel'   => 1,
                'supplier_id'   => $tf['vend_id'],
            );
        }

        $result = $this->create($data);
        if($result !== false){
            if(isset($data['rec_id'])){
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

    /*
     * 添加商品到购物车
     */
    function addColorboardItem($user_id=0,$sku_id,$number=1){
        $sku = D('Tf/Sku')->find($sku_id);
        if(empty($sku)) return false;

        $tf = D('Tf/Tf')->find($sku['tf_id']);
        if(empty($tf)) return false;

        $where['user_id'] = $user_id;
        $where['goods_sku_id'] = $sku_id;

        $cart = $this->where($where)->find();
        if(!empty($cart)){
            $data = array(
                'rec_id'        => $cart['rec_id'],
                'goods_name'    => $tf['name'],
                'goods_price'   => empty($sku['sku_price']) ? 0:$sku['sku_price'],
                'goods_number'  => $cart['goods_number'] + $number,
                'goods_sku'     => json_encode($sku, 256),
            );
        }else{
            $data = array(
                'user_id'       => $user_id,
                'session_id'    => session_id(),
                'goods_id'      => $sku['tf_id'],
                'goods_name'    => $tf['name'],
                'goods_price'   => empty($sku['sku_price']) ? 0:$sku['sku_price'],
                'goods_number'  => $number,
                'goods_sku_id'  => $sku_id,
                'goods_sku'     => json_encode($sku, 256),
                'is_colorcard'  => 1,
                'parent_id'     => 0,
                'can_handsel'   => 1,
                'supplier_id'   => $tf['vend_id'],
            );
        }

        $result = $this->create($data);
        if($result !== false){
            if(isset($data['rec_id'])){
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

    function changeNum($id,$number){
        if(is_array($id)){
            $where = $id;
        }else{
            $where['rec_id'] = $id;
        }
        return $this->where($where)->setField('goods_number', $number);
    }

    /*
     * 移除一条商品
     */
    function removeItem($id){
        if(is_array($id)){
            $where = $id;
        }else{
            $where['rec_id'] = $id;
        }
        $result = $this->where($where)->delete();
        if($result === false){
            $this->error = $this->getDbError();
        }
        return $result;
    }

    /*
     * 获取购物车商品数量
     */
    function getItemsCount($user_id){
        $where[is_numeric($user_id) ? 'user_id':'session_id'] = $user_id;
        return $this->where($where)->count();
    }
    function getAllItemsCount($user_id){
        $where[is_numeric($user_id) ? 'user_id':'session_id'] = $user_id;
        return $this->where($where)->sum('goods_number');
    }

    /*
     * 获取购物车金额总计
     */
    function getTotal($user_id){
        $where[is_numeric($user_id) ? 'user_id':'session_id'] = $user_id;
        $where['is_checked'] = 1;
        $where['is_colorcard'] = 0;
        $sum = $this->where($where)->sum('goods_price*goods_number');
        if(is_null($sum)){
            $sum = 0.00;
        }
        return $sum;
    }

    /*
     * 清空购物车
     */
    function cleanAllItems($user_id){
        $where[is_numeric($user_id) ? 'user_id':'session_id'] = $user_id;
        return $this->where($where)->delete();
    }

    /*
     * 获取购物车
     */
    function getCart($user_id, $supplier_id=null, $full=false){
        $where[is_numeric($user_id) ? 'user_id':'session_id'] = $user_id;
        $where['is_colorcard'] = 1;
        if(!is_null($supplier_id)){
            $where['supplier_id'] = $supplier_id;
        }
        $rs = $this->where($where)->select();

        $cart = array();
        if($rs){
            //按供应商分组，加入面料，sku详细信息
            $tf_ids = array();
            $sku_ids = array();
            foreach($rs as $row){
                $cart[$row['supplier_id']]['goods'][] = $row;
                if($row['is_checked']){
                    $cart[$row['supplier_id']]['checked_goods'][] = $row['rec_id'];
                }
                if($full){
                    array_push($tf_ids, $row['goods_id']);
                    array_push($sku_ids, $row['goods_sku_id']);
                }
            }
            if($full){
                $fabrics = D('Tf/Tf')->getTfNoPaged(array('id'=>array('IN',$tf_ids)));
                $skus = D('Tf/Sku')->skus(array('id'=>array('IN',$sku_ids)));
            }

            $suppliers = D('BizMember')->getMembersNoPaged(array('id'=>array('IN',array_keys($cart))));
            foreach($cart as $key=>$row){
                $cart[$key] = array_merge($suppliers[$key], $row);
                if($full){
                    foreach($row['goods'] as $k=>$v){
                        $cart[$key]['goods'][$k]['tf'] = $fabrics[$v['goods_id']];
                        $cart[$key]['goods'][$k]['goods_sku'] = $skus[$v['goods_sku_id']];
                    }
                }
            }
        }

        return $cart;
    }

    /*
     * 将指定会话的购物车合并到指定的用户
     */
    function migrateCart($session_id,$user_id){
        $where['session_id'] = $session_id;
        $where['user_id'] = 0;
        return $this->save($where)->setField('user_id', $user_id);
    }

    function check($id,$is_checked=1){
        if(is_array($id)){
            $where = $id;
        }else{
            $where['rec_id'] = $id;
        }
        $result = $this->where($where)->setField('is_checked', $is_checked);
        if($result === false){
            $this->error = $this->getDbError();
        }
        return $result;
    }

}