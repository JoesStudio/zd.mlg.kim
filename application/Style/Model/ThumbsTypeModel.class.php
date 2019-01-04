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

class ThumbsTypeModel extends CommonModel
{
    protected $tableName = 'style_thumbs_type';
    protected $pk        = 'type_id';

    protected $_validate = array(
        array('type_name','require','必须填写名称！'),
        array('type_table','require','必须填写表！'),
        array('type_table','checkTable','不存在改数据表，请正确填写！',1,'callback')

    );

    public function checkTable(){

        $table = I('post.type_table');
        $res = Db::query('show tables like "'.$table.'"');

        if($res) return true;

        return false;
    }
}
