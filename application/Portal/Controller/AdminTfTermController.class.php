<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2014 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Tuolaji <479923197@qq.com>
// +----------------------------------------------------------------------
namespace Portal\Controller;
use Common\Controller\AdminbaseController;
class AdminTfTermController extends AdminbaseController {
	
	protected $Tf_term_model;
	protected $taxonomys=array("fashion"=>"时尚","hot"=>"热销","knit"=>"针织","printing"=>"印花","chiffon"=>"雪纺","korea"=>"韩国","italy"=>"意大利","area"=>"区域","cotton"=>"纯棉","fibre"=>"纯麻","fiber"=>"纤维","mixture"=>"混合面料");

	function _initialize() {
		parent::_initialize();
		$this->Tf_term_model = M("Tf_terms");
		$this->assign("taxonomys",$this->taxonomys);
	}
	function index(){
		$tf_trem_list = $this->Tf_term_model->order(array("listorder"=>"asc"))->select();
		
		$this->assign("tf_trem_list", $tf_trem_list);
		$this->display();
	}
	
	
	function add(){
	 	
	 	$this->display();
	}
	
	function add_post(){
		if (IS_POST) {
			if ($this->Tf_term_model->create()!==false) {
				if ($this->Tf_term_model->add()!==false) {
					$this->success("添加成功！",U("AdminTfTerm/index"));
				} else {
					$this->error("添加失败！");
				}
			} else {
				$this->error($this->Tf_term_model->getError());
			}
		}
	}
	
	function edit(){
		$id = intval(I("get.id"));
		$data=$this->Tf_term_model->where(array("tf_term_id" => $id))->find();
		$this->assign('data',$data);
		$this->display();
	}
	
	function edit_post(){
		if (IS_POST) {
			if ($this->Tf_term_model->create()!==false) {
				if ($this->Tf_term_model->save()!==false) {
					$this->success("修改成功！");
				} else {
					$this->error("修改失败！");
				}
			} else {
				$this->error($this->Tf_term_model->getError());
			}
		}
	}
	
	//排序
	public function listorders() {
		$status = parent::_listorders($this->Tf_term_model);
		if ($status) {
			$this->success("排序更新成功！");
		} else {
			$this->error("排序更新失败！");
		}
	}
	
	/**
	 *  删除
	 */
	public function delete() {
		$id = intval(I("get.id"));
		if ($this->Tf_term_model->delete($id)!==false) {
			$this->success("删除成功！");
		} else {
			$this->error("删除失败！");
		}
	}
	
}