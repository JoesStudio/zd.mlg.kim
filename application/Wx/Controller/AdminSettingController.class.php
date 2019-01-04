<?php
/**
 * Created by PhpStorm.
 * User: Jason
 * Date: 2016-09-07
 * Time: 16:06
 */

namespace Wx\Controller;


use Common\Controller\AdminbaseController;
use Common\Model\WxModel;

class AdminSettingController extends AdminbaseController
{
    protected $options_model;
    function _initialize()
    {
        parent::_initialize();
        $this->options_model = M('Options');
    }

    function config(){
        $where = array('option_name'=>'wx_configs');
        if(IS_POST){
            $post = I('post.config');
            $config = json_encode($post);
            $count = $this->options_model->where($where)->count();

            if($count){
                $result = $this->options_model->where("option_name='wx_configs'")->setField('option_value',$config);
            }else{
                $data = array(
                    'option_name'=>'wx_configs',
                    'option_value'=>$config,
                    'autoload'=>0
                );
                $result = $this->options_model->add($data);
            }

            $this->success('保存成功！');
        }else{
            $option_value = $this->options_model->where($where)->getField('option_value');
            $wx_configs = json_decode($option_value,true);
            $this->assign('config',$wx_configs);
            $this->display();
        }
    }

    function menu(){
        $where = array('option_name'=>'wx_menu');
        if(IS_POST){
            $menu_json = $_POST['json'];
            $count = $this->options_model->where($where)->count();

            if($count){
                $this->options_model->where($where)->setField('option_value',$menu_json);
            }else{
                $data = array(
                    'option_name'=>'wx_menu',
                    'option_value'=>$menu_json,
                    'autoload'=>0
                );
                $this->options_model->add($data);
            }

            $Wx = new WxModel();
            $Wx->api->create_menu($menu_json);

            $this->success('菜单已更新！');
        }else{
            //$option_value = $this->options_model->where($where)->getField('option_value');
            $Wx = new WxModel();
            $option_value = $Wx->api->get_menu();
            $option_value = json_encode($option_value[1]->menu,JSON_UNESCAPED_UNICODE);
            $this->assign('menu_json',$option_value);
            $this->assign('menu',json_decode($option_value,true));
            $this->display();
        }
    }

}