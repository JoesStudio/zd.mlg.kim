<?php
namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class PropController extends AdminbaseController{
    public function index(){
        $type = I('get.type');
    
        
        $_model=M("Property");
        
        $count=$_model->where($where)->count();
        $page = $this->page($count, 20);
/*
        $this->posts_model
        ->alias("a")
        ->join("__USERS__ c ON a.post_author = c.id")
        ->where($where)
        ->limit($page->firstRow , $page->listRows)
        ->order("a.post_date DESC");
        if(empty($term_id)){
            $this->posts_model->field('a.*,c.user_login,c.nickname');
        }else{
            $this->posts_model->field('a.*,c.user_login,c.nickname,b.listorder,b.tid');
            $this->posts_model->join("__TERM_RELATIONSHIPS__ b ON a.id = b.object_id");
        }
        $posts=$this->posts_model->select();
        
        SELECT a.*,GROUP_CONCAT(b.value) as val FROM `mlg_property` a LEFT JOIN mlg_property_value b ON a.id=b.key_id;
*/        
        $list = $_model
        ->alias("a")                                      //
        //->join("__PROPERTY_VALUE__ b ON a.id=b.key_id")   //
        ->where($where)
        ->order("id DESC")
        //->field('a.*,GROUP_CONCAT(b.value) as val')
        ->field('a.*,(select GROUP_CONCAT(value) from mlg_property_value where key_id = a.id) as val')
        
        ->limit($page->firstRow . ',' . $page->listRows)
        ->select();
        
        $this->assign('list', $list);
        $this->assign("page", $page->show('Admin'));
        
        $this->display();
    }

    public function add(){
        $this->display("prop");
    }  
    public function edit(){
        $id = I('get.id',0,'intval');
      
        $_model=M("Property");
        $keyname=$_model->where(array("id"=>$id))->find();
        $this->assign("keyname",$keyname);    
        
        $_model_pv=M("PropertyValue");
        //---22---
        $vid = I('get.vid',0,'intval');
        $keyvalue=$_model_pv->where(array("id"=>$vid))->find();      
        $this->assign("keyvalue",$keyvalue);        
        //\\\-22--
        //---33---
        $where = array("key_id"=>$id);  //
        $count=$_model_pv->where($where)->count();
        $page = $this->page($count, 20);
        
        $list = $_model_pv
        ->where($where)
        ->order("id DESC")
        ->limit($page->firstRow . ',' . $page->listRows)
        ->select();
        
        $this->assign('list', $list);
        $this->assign("page", $page->show('Admin'));                
        //\\\-33--
        
        $this->display("prop");
    }   
    
    public function post(){
        if(IS_POST){
      
                $_model=M("Property");
                if ($_model->create()!==false) {
                    if(!empty($_POST['id'])){
                        $result=$_model->save();
                        if ($result!==false) {
                            $this->success("保存成功！");   //, U("Prop/index")
                        } else {
                            $this->error("保存失败！");
                        }                        
                    } else {
                        $result=$_model->add();
                        if ($result!==false) {
                            $this->success("添加成功！", U("Prop/index"));
                        } else {
                            $this->error("添加失败！");
                        }
                    }
                } else {
                    $this->error($_model->getError());
                }


        }
    }

	public function delete(){
	    $id = I('get.id',0,'intval');

        $_model=M("Property");
		if ($_model->delete($id)!==false) {
			$this->success("删除成功！");
		} else {
			$this->error("删除失败！");
		}
	}

    public function value_post(){
        if(IS_POST){
      
                $_model=M("PropertyValue");
                if ($_model->create()!==false) {
                    if(!empty($_POST['id'])){
                        $result=$_model->save(); 
                        if ($result!==false) {
                            //echo $result.",".U("Prop/edit",array("id"=>$keyname["id"]));
                            $this->success("保存成功！", U("Prop/edit",array("id"=>$_POST["key_id"])));   //
                        } else {
                            $this->error("保存失败！");
                        }                        
                    } else {
                        $result=$_model->add();
                        if ($result!==false) {
                            $this->success("添加成功！", U("Prop/edit",array("id"=>$_POST["key_id"])));
                        } else {
                            $this->error("添加失败！");
                        }
                    }
                } else {
                    $this->error($_model->getError());
                }


        }
    }

    public function value_delete(){
        $id = I('get.id',0,'intval');

        $_model=M("PropertyValue");
        if ($_model->delete($id)!==false) {
            $this->success("删除成功！");
        } else {
            $this->error("删除失败！");
        }
    }


}