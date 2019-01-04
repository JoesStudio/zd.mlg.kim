<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-04-13
 * Time: 16:02
 */

namespace Style\Model;

use Common\Model\CommonModel;
use Think\Db;

class MaterialsClassifyModel extends CommonModel
{
    protected $tableName = 'style_materials_classify';
    protected $pk        = 'classify_id';

    protected $_validate = array(
        array('classify_name','require','必须填写名称！')
    );

}
