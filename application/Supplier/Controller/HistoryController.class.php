<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-06-06
 * Time: 10:59
 */

namespace Supplier\Controller;


use Common\Controller\SupplierbaseController;

class HistoryController extends SupplierbaseController
{
    function index(){
        $goods_id = I('get.id');
        $type = I('get.type');

        if($type == 1){
            $tf_info = D('Tf/Tf')->getTf($goods_id);//获取该面料信息
            $this->assign('tf_info',$tf_info);
        }else{
            $supplier_info = D('BizMember')->getMember($goods_id );//获取该面料商信息
            $this->assign('supplier_info',$supplier_info);
        }
        

        //从历史记录表中统计总浏览数
        $browse_sum = D('History')->where(array('goods_id'=>$goods_id,'type'=>$type,'isdelete'=>0))->count();
    
        $uids = D('History')->getUserIds("goods_id:".$goods_id.";type:".$type.";
        group:user_id;");
        $list = array();
        if(!empty($uids)){
            $uids = implode(',',$uids);

            $field = 'info.*,province.name as province_name,city.name as city_name,district.name as district_name'
                .',(SELECT COUNT(h_tf.id) FROM '.C('DB_PREFIX').'history h_tf
            WHERE h_tf.user_id=info.user_id AND h_tf.supplier_id='.$this->memberid.' AND h_tf.type=1) 
            as h_tf_count';

            $list = D('UserInfo')->getInfosPaged("field:$field;
            _string:info.user_id IN($uids);
            order:FIELD(info.user_id,$uids);");
        }
  
        
        $this->assign('browse_sum',$browse_sum);
        $this->assign('list',$list);
        $this->display(":History/index");
    }
    
   

}