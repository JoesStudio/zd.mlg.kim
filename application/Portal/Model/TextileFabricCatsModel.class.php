<?php
namespace Portal\Model;
use Common\Model\CommonModel;
class TextileFabricCatsModel extends CommonModel {
	
	/*
	 * term_id category name description pid path status
	 */
	
	//自动验证
	protected $_validate = array(
			//array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
			array('title', 'require', '分类名称不能为空！', 1, 'regex', 3),
	);
	
	protected function _after_insert($data,$options){
		parent::_after_insert($data,$options);

	}
	
	protected function _after_update($data,$options){
		parent::_after_update($data,$options);

	}
	
	protected function _before_write(&$data) {
		parent::_before_write($data);
	}
	

}