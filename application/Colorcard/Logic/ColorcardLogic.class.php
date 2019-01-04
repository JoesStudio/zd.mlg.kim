<?php

/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-09
 * Time: 11:48
 */
namespace Colorcard\Logic;


use Colorcard\Model\ColorcardModel;

class ColorcardLogic extends ColorcardModel
{

    /*
     * 获取未删除的记录
     * @param in $pageSize 分页记录数
     * @return array
     */
    function getPaged($tag='', $where = array(), $pageSize = 20)
    {
        $where['card_trash'] = 0;
        $data = $this->cards($tag, $where, $pageSize, '', 'Admin');
        foreach ($data['data'] as $key => $card) {
            $data['data'][$key]['card_cover'] = json_decode($card['card_cover'], true);
        }
        return $data;
    }

    function formatRequest()
    {
        $post = I('post.');
        unset($post['searchCust']);
        $post['card_cover'] = json_encode($post['cover'], 256);
        unset($post['cover']);
        $post['pages'] = json_decode($_POST['pages'], true);

        $tf_model = D('Tf/Tf');
        foreach ($post['pages'] as $k1 => $page) {
            foreach ($page['items'] as $k2 => $item) {
                if ($item['tf_id'] > 0) {
                    $tf = $tf_model
                        ->field('id,vend_id,cid,name_code,code,name,img,spec,width,weight,material,component,function,purpose')
                        ->find($item['tf_id']);
                    $tf['img'] = json_decode($tf['img'], true);
                    $post['pages'][$k1]['items'][$k2]['item_fabric'] = json_encode($tf, 256);
                }else{
                    $post['pages'][$k1]['items'][$k2]['item_fabric'] = '';
                }
            }
        }

        return $post;
    }

    /*
     * 获得全局设定
     */
    function get_settings(){
        $settings = F('colorcard_settings');
        if(empty($settings)){
            $options_obj = M("Options");
            $option = $options_obj->where("option_name='colorcard_settings'")->find();
            if($option){
                $settings = json_decode($option['option_value'],true);
            }else{
                $settings = array();
            }
            F('colorcard_settings', $settings);
        }
        return $settings;
    }

    function get_user_settings(){}
}