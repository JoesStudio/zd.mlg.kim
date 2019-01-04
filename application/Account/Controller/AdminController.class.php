<?php
namespace Account\Controller;
use Common\Controller\AdminbaseController;

class AdminController extends AdminbaseController {
	protected $_model;
    protected $mid;

    public function _initialize() {
        parent::_initialize();
        $this->_model = D('Account');
        if (isset($_REQUEST['mid'])) {
            $this->mid = I('request.mid');
            $this->assign('mid', $this->mid);
        } else {
            if (!IS_POST) {
                $this->error('请选择供应商！');
            }
        }
    }
	function index(){
	    $account = $this->_model->getAccount($this->mid);
        $this->assign($account);
		$this->display();
	}
}