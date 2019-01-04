<?php
namespace Account\Controller;
use Common\Controller\AdminbaseController;
use Account\Model\AccountModel;

class AdminAccountController extends AdminbaseController {
	protected $accountModel;

    public function _initialize() {
        parent::_initialize();
        $this->accountModel = new AccountModel();
    }
	function index(){
	    $account = $this->accountModel->where("member_id=0")->find();
        if(empty($account)){
            $this->accountModel->add(array(
                'member_id' => 0,
                'create_userid' => sp_get_current_admin_id(),
                'create_date'   => date('Y-m-d H:i:s'),
                'modify_userid' => sp_get_current_admin_id(),
                'modify_date'   => date('Y-m-d H:i:s'),
            ));
            $account = $this->accountModel->where("member_id=0")->find();
        }
        $this->assign($account);
		$this->display();
	}
}