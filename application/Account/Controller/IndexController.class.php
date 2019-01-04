<?php
namespace Account\Controller;
use Common\Controller\SupplierbaseController;

class IndexController extends SupplierbaseController {

    protected $_model;
    protected $ac_setting;
    public function _initialize(){
        parent::_initialize();
        $this->_model = D('Account');
        $setting = D('Options')
            ->where(array('option_name'=>'account_configs'))
            ->getField('option_value');
        $this->ac_setting = json_decode($setting, true);
    }

	function index(){
	    $account = $this->_model->getAccount($this->memberid);
        $this->assign('account', $account);
	    $this->display();
	}
}