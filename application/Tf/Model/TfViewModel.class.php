<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-15
 * Time: 9:49
 */

namespace Tf\Model;


use Think\Model\ViewModel;

class TfViewModel extends ViewModel
{
    public $viewFields = array(
        'Tf'=>array(
            '_table'    => '__TEXTILE_FABRIC__',
            '_as'       => 'tf',
            '_type'     => 'LEFT',
            'id','name','code','img','spec','width','weight','material','component',
            'function','purpose','`describe`','visible_range','status',
        ),
        'Vend'=>array(
            '_table'    => '__BIZ_MEMBER__',
            '_as'       => 'vend',
            '_on'       => 'tf.vend_id=vend.id',
            '_type'     => 'LEFT',
            'id'        => 'vend_id',
            'short_name','long_name','logo','main_core','salesman','custom_service',
            ),
        'Cat'=>array(
            '_table'    => '__TEXTILE_FABRIC_CATS__',
            '_as'       => 'cat',
            '_on'       => 'tf.cid=cat.id',
            'title'     => 'cat_title',
            'code'      => 'cat_code',
            'en_title'  => 'cat_en_title',
            'remark'    => 'cat_remark',
            'img'       => 'cat_img',
            ),
    );
}