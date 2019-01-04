<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-15
 * Time: 9:33
 */

namespace Tf\Controller;


use Common\Controller\HomebaseController;

class CardApplyController extends HomebaseController
{
    protected $apply_model;
    function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->apply_model = D('Tf/CardApply');
    }
    

    function apply_post(){
        if(IS_POST){ 
            $post = I('post.');
            $aid = $post['address_id'];
            $sid = $post['shipping_id'];
            $tf_id = $post['tf_id'];        

            if(empty($tf_id) || !is_numeric($tf_id)){
                $this->error('面料id不能为空！');
            } 
            if(empty($aid) || !is_numeric($aid)){
                $this->error('请选择收货地址！');
            }
            if(empty($sid) || !is_numeric($sid)){
                $this->error('请选择配送方式！');
            }

            $address = D('Address/Address')->getAddress(array('user_id'=>sp_get_current_userid(),'address_id'=>$aid));
            $shipping = D('Shipping')->getShipping(array('user_id'=>sp_get_current_userid(),'shipping_id'=>$sid));
            
            $data = array(
            'user_id'           => sp_get_current_userid(),
            'supplier_id'       => $post['supplier_id'],
            'tf_id'             => $tf_id,
            'apply_status'      => 0,   //订单状态；0，未发货；1，已发货；
            'consignee'         => $shipping['shipping_id'],
            'province'          => $address['province'],  
            'city'              => $address['city'],      
            'district'          => $address['district'],       
            'tel'               => $address['tel'],       
            'mobile'            => $address['mobile'],   
            'email'             => $address['email'],
            'shipping_id'       => $shipping['shipping_id'],
            'shipping_name'     => $shipping['shipping_name'],
            'delivery_num'      => '',
            'remark'            => $post['remark'],
            'apply_time'        => date('Y-m-d H:i:s',time()),
        );
            $result = $this->apply_model->add($data);
            if($result){
                //$this->ajaxReturn($result);
                $this->success('提交申请成功！');
            }else{
                $this->error('提交申请失败！'.$this->apply_model->getError());
            }
        }
    }


}