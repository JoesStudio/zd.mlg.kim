<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-05-31
 * Time: 18:12
 */

namespace Distribution\Model;


use Common\Model\CommonModel;

class ApplyModel extends CommonModel
{
    protected $tableName = 'distribution_tf_apply';
    public $statuses = array('未处理', '通过', '不通过', '无效的');

}