<?php
namespace Account\Controller;
use Common\Controller\AdminbaseController;

class AdminWithdrawController extends AdminbaseController {
	protected $_model;

    public function _initialize() {
        parent::_initialize();
        $this->_model = D('Account/Withdraw');
    }

	function index(){
	    if(IS_AJAX){
	        /*$start = I('post.start/d');
            $length = I('post.length/d');
            $page = $start/$length;
            $_GET['p'] = $page+1;
            $data = $this->_model->getRowsPaged('',$length);
            $data['recordsTotal'] = $data['total'];
            $data['recordsFiltered'] = $data['total'];*/
            $data['data'] = $this->_model->getRowsNoPaged();
            $data['status'] = 1;
            $this->ajaxReturn($data);
        }
		$this->display();
	}

	function audit_post(){
	    if(IS_POST){
	        $post = I('post.');
            if(!$post['id']){
                $this->error('传入数据错误');
            }
            if($post['action'] == 2 && empty($post['remark'])){
                $this->error('请注明操作原因');
            }
            $uid = sp_get_current_admin_id();
            $status = $post['action'];
            $result = $this->_model->audit($post['id'],$status,$post['remark'],$uid);
            if($result){
                $this->success('操作成功！');
            }else{
                $this->error($this->_model->getError());
            }
        }
    }

    function transfer_post(){
        if(IS_POST){
            $id = I('post.id/d');
            $result = $this->_model->transfer($id);
            if($result !== false){
                $this->success('已打款');
            }else{
                $this->error($this->_model->getError());
            }
        }
    }
}