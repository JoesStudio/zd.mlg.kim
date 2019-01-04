<?php
namespace Account\Controller;
use Common\Controller\SupplierbaseController;

class WithdrawController extends SupplierbaseController {

    protected $_model;
    protected $ac_setting;
    public function _initialize(){
        parent::_initialize();
        $this->_model = D('Account/Withdraw');
        $setting = D('Options')
            ->where(array('option_name'=>'account_configs'))
            ->getField('option_value');
        $this->ac_setting = json_decode($setting, true);
    }

	function index(){
	    $this->assign('setting',$this->ac_setting['withdraw']);
	    $this->display();
	}

    public function logs(){
        if(IS_AJAX){
            $member_id = $this->memberid;
            $data['data'] = $this->_model->getRowsNoPaged("member_id:$member_id");
            $data['status'] = 1;
            $this->ajaxReturn($data);
        }
        $this->display();
    }

    public function create_post(){
        if(IS_POST){
            $post = I('post.');
            $setting = $this->ac_setting['withdraw'];
            $min = (float)$setting['min'];
            $max = (float)$setting['max'];
            if($setting['min'] > 0 && $post['exchange_amount'] < $min){
                $this->error('提现金额太小');
            }
            if($setting['max'] > 0 && $post['exchange_amount'] > $max){
                $this->error('提现金额太大');
            }
            if($post['exchange_amount'])
            $exchange = array(
                'amount'    => $post['exchange_amount'],
                'type'      => $post['exchange_type'],
                'account'   => $post['exchange_account'],
            );
            $result = $this->_model->withdraw($this->memberid, $exchange, $this->userid, $post['remark']);
            if($result !== false){
                $this->success('',leuu('logs'));
            }else{
                $this->error($this->_model->getError());
            }
        }
    }
}