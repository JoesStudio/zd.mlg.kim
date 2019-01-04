<?php
namespace Common\Model;
use Common\Model\CommonModel;
class BizRoleModel extends CommonModel{
    
    //自动验证
    protected $_validate = array(
            //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
            array('name', 'require', '角色名称不能为空！', 1, 'regex', CommonModel:: MODEL_BOTH ),
    );
    
    protected $_auto = array(
        array('create_time','mGetDate',CommonModel:: MODEL_INSERT,'function'),
        array('update_time','mGetDate',CommonModel:: MODEL_INSERT,'function'),
                
    );
    
    protected function _before_write(&$data) {
        parent::_before_write($data);
    }
}