<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-15
 * Time: 11:36
 */

namespace Tf\Model;


use Think\Model\ViewModel;

class TfSkuViewModel extends ViewModel
{
    public $viewFields = array(
        'Sku'=>array(
            '_table'    => '__TEXTITLE_FABRIC_SKU__',
            '_as'       => 'sku',
            'id'        => 'sku_id',
            'key_name'  => 'sku_custom_key',
            'value_text'=> 'sku_custom_value',
            ''
        ),
        'Tf'=>array(
            '_table'    => '__TEXTILE_FABRIC__',
            '_as'       => 'tf',
            'id','name','code','img','spec','width','weight','material','component',
            'function','purpose','`describe`','visible_range','status',
        ),
        'Vend'=>array(
            '_table'    => '__BIZ_MEMBER__',
            '_as'       => 'vend',
            '_on'       => 'vend.id=tf.vend_id',
            'id'        => 'vend_id',
            'short_name','long_name','logo','main_core','salesman','custom_service',
        ),
        'Cat'=>array(
            '_table'    => '__TEXTILE_FABRIC_CATS__',
            '_as'       => 'cat',
            '_on'       => 'cat.id=tf.cid',
            'title'     => 'cat_title',
            'code'      => 'cat_code',
            'en_title'  => 'cat_en_title',
            'remark'    => 'cat_remark',
            'img'       => 'cat_img',
        ),
    );

}