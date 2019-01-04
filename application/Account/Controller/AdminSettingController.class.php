<?php
/**
 * Created by PhpStorm.
 * User: Jason
 * Date: 2016-09-07
 * Time: 16:06
 */

namespace Account\Controller;


use Common\Controller\AdminbaseController;

class AdminSettingController extends AdminbaseController
{
    protected $options_model;
    function _initialize()
    {
        parent::_initialize();
        $this->options_model = M('Options');
    }

    function setting(){
        $this->options_model = M('Options');
        $where = array('option_name'=>'account_configs');
        if(IS_POST){
            $post = I('post.');
            $config = json_encode($post);
            $count = $this->options_model->where($where)->count();

            if($count){
                $result = $this->options_model->where("option_name='account_configs'")->setField('option_value',$config);
            }else{
                $data = array(
                    'option_name'=>'account_configs',
                    'option_value'=>$config,
                    'autoload'=>0
                );
                $result = $this->options_model->add($data);
            }

            $this->success('保存成功！');
        }else{
            $option_value = $this->options_model->where($where)->getField('option_value');
            $wx_configs = json_decode($option_value,true);
            $this->assign($wx_configs);
            $this->display();
        }
    }

}