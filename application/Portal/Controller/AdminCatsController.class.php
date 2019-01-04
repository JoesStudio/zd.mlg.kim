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
class AdminCatsController extends AdminbaseController {
    
    protected $cats_model;
    //protected $taxonomys=array("article"=>"文章","picture"=>"图片");
    
    function _initialize() {
        parent::_initialize();
        $this->cats_model = D("Portal/TextileFabricCats");
        $this->code_model = D("Portal/TextileFabricName");
        //$this->cats_model = M("TextileFabricCats");
        //$this->assign("taxonomys",$this->taxonomys);
    }
    function index(){
        $result = $this->cats_model->order(array("listorder"=>"asc"))->select();
        
        $tree = new \Tree();
        $tree->icon = array('&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─ ', '&nbsp;&nbsp;&nbsp;└─ ');
        $tree->nbsp = '&nbsp;&nbsp;&nbsp;';
        foreach ($result as $r) {
            $r['str_manage'] = '<a href="' . U("AdminCats/add", array("pid" => $r['id'])) . '">添加子类</a> | <a href="' . U("AdminCats/edit", array("id" => $r['id'])) . '">'.L('EDIT').'</a> | <a class="js-ajax-delete" href="' . U("AdminCats/delete", array("id" => $r['id'])) . '">'.L('DELETE').'</a> <a href="' . U("AdminCats/code_list", array("id" => $r['id'])) . '" class="pull-right">'.'名称编码'.'</a>';
            $url="#";//U('portal/list/index',array('id'=>$r['id']));
            $r['url'] = $url;
            $r['taxonomys'] = $this->taxonomys[$r['taxonomy']];
            $r['id']=$r['id'];
            $r['parentid']=$r['pid'];
            $array[] = $r;
        }
        
        $tree->init($array);
        $str = "<tr>
                    <td><input name='listorders[\$id]' type='text' size='3' value='\$listorder' class='input input-order'></td>
                    <td>\$id</td>
                    <td>\$pid</td>
                    <td>\$spacer <a href='\$url' target='_blank'>\$title</a></td>
                    <td>\$en_title</td>
                    <td>\$code</td>
                    <td>\$str_manage</td>
                </tr>";
        $taxonomys = $tree->get_tree(0, $str);
        $this->assign("taxonomys", $taxonomys);
        $this->display();
    }
    
    //添加新面料分类
    function add(){
        //var_dump( $this->terms_model );
        
         $parentid = intval(I("get.pid"));
         $tree = new \Tree();
         $tree->icon = array('&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─ ', '&nbsp;&nbsp;&nbsp;└─ ');
         $tree->nbsp = '&nbsp;&nbsp;&nbsp;';
         $terms = $this->cats_model->order(array("listorder"=>"asc"))->select();
         
         $new_terms=array();
         foreach ($terms as $r) {
             $r['id']=$r['id'];
             $r['parentid']=$r['pid'];
             $r['selected']= (!empty($parentid) && $r['id']==$parentid)? "selected":"";
             $new_terms[] = $r;
         }
         $tree->init($new_terms);
         $tree_tpl="<option value='\$id' \$selected>\$spacer\$title</option>";
         $tree=$tree->get_tree(0,$tree_tpl);
         
         $this->assign("terms_tree",$tree);
         $this->assign("pid",$parentid);
         $this->display("cats_form");
    }
    //编辑面料分类
    function edit(){
        $id = intval(I("get.id"));
        $this->assign("sel",I('get.select'));
        
        $data=$this->cats_model->where(array("id" => $id))->find();
        $tree = new \Tree();
        $tree->icon = array('&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─ ', '&nbsp;&nbsp;&nbsp;└─ ');
        $tree->nbsp = '&nbsp;&nbsp;&nbsp;';
        $terms = $this->cats_model->where(array("id" => array("NEQ",$id)))->order(array("listorder"=>"asc"))->select();
        
        $new_terms=array();
        foreach ($terms as $r) {
            $r['id']=$r['id'];
            $r['parentid']=$r['pid'];
            $r['selected']=$data['pid']==$r['id']?"selected":"";
            $new_terms[] = $r;
        }
        
        $tree->init($new_terms);
        $tree_tpl="<option value='\$id' \$selected>\$spacer\$title</option>";
        $tree=$tree->get_tree(0,$tree_tpl);
        
        $this->assign("terms_tree",$tree);
        $this->assign("data",$data);
     
        $this->assign("img",json_decode($data['img'],true));
        $this->display("cats_form");
    }

    //保存面料分类
    function post(){
        if (IS_POST) {

            if ($this->cats_model->create()!==false) {
                if(empty($_POST['id'])){
                    $_POST['img']['thumb'] = sp_asset_relative_url($_POST['img']['thumb']);
                    $_POST['img']['icon'] = sp_asset_relative_url($_POST['img']['icon']);
                    $_POST['img']=json_encode($_POST['img']);
                    if ($this->cats_model->add($_POST)!==false) {
         
                        F('all_cats',null);
                        $this->success("添加成功！",U("AdminCats/index"));
                    } else {
               
                        $this->error("添加失败！");
                    }
                } else {
                    $update_codes = $this->code_model->where(array('cid'=>$_POST['id']))->save(array('cat_code'=>$_POST['code']));
    
                    $_POST['img']['thumb'] = sp_asset_relative_url($_POST['img']['thumb']);
                    $_POST['img']['icon'] = sp_asset_relative_url($_POST['img']['icon']);
                    $_POST['img']=json_encode($_POST['img']);
                    if ($update_codes!==false && $this->cats_model->save($_POST)!==false) {
                        F('all_cats',null);
                        $this->success("修改成功！",U("AdminCats/index"));
                    } else {
                        $this->error("修改失败！");
                    }                    
                }
            } else {
                $this->error($this->cats_model->getError());
            }
        }
    }
        
    //排序
    public function listorders() {
        $status = parent::_listorders($this->cats_model);
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
        $count = $this->cats_model->where(array("pid" => $id))->count();
        
        if ($count > 0) {
            $this->error("该菜单下还有子类，无法删除！");
        }
        
        if ($this->cats_model->delete($id)!==false) {
            $this->success("删除成功！");
        } else {
            $this->error("删除失败！");
        }
    }
    
    public function prop(){
        $id = I('get.id',0,'intval');  //面料分类ＩＤ
        if($id==0){
            //$this->success("添加成功！", U("AdminCats/add"));
            $this->assign("add",1);
            $this->display("cats_prop_def");
        } else {
            $data=$this->cats_model->where(array("id" => $id))->find();
            $this->assign("data",$data);
    //var_dump($data);        
            
            $where = array("cid"=>$id);  //
            $_model=M("CatsPropDef");
            
            $count=$_model->where($where)->count();  
            $page = $this->page($count, 20);
            
            $list = $_model
            ->alias("a")
            ->join("__PROPERTY__ b ON a.key_id=b.id")   //
            ->where($where)
            ->order("id DESC")
            ->field('a.*,b.key_name as key_name')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();
            
            foreach ($list as $k => $v) {
                //select GROUP_CONCAT(id,value) from mlg_property_value where id in(1,2,3)
                
                 $ids = join(",",array_filter(explode(",",$v["value_id_range"])));
                 if($ids<>""){
                    $sel = M("PropertyValue")
                    ->where(array("id"=>array("in",$ids)))
                    ->field('GROUP_CONCAT(id,value) as aa')->select();
                    $value_range = $sel[0]["aa"];
                    //->getField("GROUP_CONCAT(id,value) as aa");   
                 } else {
                    $value_range=(M("PropertyValue")->where(array("key_id"=>$v["key_id"]))->count()>0)?"(所有)":"(自定义)"; 
                 }            
                     
                $v["value_range"]=$value_range;
                $list[$k] = $v;
            }
            
            $this->assign('list', $list);
            $this->assign("page", $page->show('Admin'));        
            
            $this->display("cats_prop_def");
        }
    }       
    
    //添加分类关联的属性
    public function prop_add(){
        $id = I('get.id',0,'intval');    //面料分类ＩＤ
        $data=$this->cats_model->where(array("id" => $id))->find();
        $this->assign("data",$data);   //分类对象
        
//var_dump($data);
//
        $_model=M("Property");
        
        $list = $_model->order("id DESC")->select();
        $this->assign('keylist', $list);
//\\\
        
        $this->display("cats_prop_def_form");
    }
    
    //修改 分类关联的属性
    public function prop_edit(){
        $id = I('get.id',0,'intval');      //关联行ＩＤ

        
//var_dump($data);
      
        $_model=M("CatsPropDef");
        $vend=$_model->where(array("id"=>$id))->find();
        $this->assign($vend);
        
        $data=$this->cats_model->where(array("id" => $vend["cid"]))->find();
        $this->assign("data",$data);  //面料分类对象
//
        $_model=M("Property");
        
        $list = $_model->order("id DESC")->select();
        $this->assign('keylist', $list);
//\\\                
        $this->display("cats_prop_def_form");        
    }
    public function prop_delete() {
        $id = intval(I("get.id"));

        $_model=M("CatsPropDef");
        
        if ($_model->delete($id)!==false) {
            $this->success("删除成功！");
        } else {
            $this->error("删除失败！");
        }
        
        if(isset($_POST['ids'])){
            $ids = I('post.ids/a');
            
            if ($_model->where(array('id'=>array('in',$ids)))->delete()!==false) {
                $this->success("删除成功！");
            } else {
                $this->error("删除失败！");
            }
        }        
    }    
    public function prop_post(){
        if(IS_POST){
                $_model=M("CatsPropDef");
                if ($_model->create()!==false) {
                    
                    
                    $data['cid'] =  $_POST['cid'];
                    $data['key_id'] =  $_POST['key_id'];
                    $data['is_must'] = ($_POST['is_must']==1)?$_POST['is_must']:0;
                    $data['is_multi'] = ($_POST['is_multi']==1)?$_POST['is_multi']:0;
                    $data['is_sale'] = ($_POST['is_sale']==1)?$_POST['is_sale']:0;
                    
                    if(!empty($_POST['id'])){
                        $data['id'] =  $_POST['id'];
                        $result=$_model->save($data);
                        if ($result!==false) {
                            $this->success("保存成功！", U("AdminCats/prop",array('id'=>$_POST['cid'])));   //
                        } else {
                            $this->error("保存失败！");
                        }                        
                    } else {
                        $result=$_model->add($data);
                        if ($result!==false) {
                            $this->success("添加成功！", U("AdminCats/prop",array('id'=>$_POST['cid'])));
                        } else {
                            $this->error("添加失败！");
                        }
                    }
                } else {
                    $this->error($_model->getError());
                }


        }        
    }
    
    //编辑　属性值侯选范围
    public function prop_range(){
        $id = I('get.id',0,'intval');      //关联行ＩＤ
      
        $_model=M("CatsPropDef");
        $prop = $_model
            ->alias("a")
            ->join("__PROPERTY__ b ON a.key_id=b.id")   //
            ->where(array("a.id"=>$id))
            ->order("id DESC")
            ->field('a.*,b.key_name as key_name')
            ->find();  //一维数组
        $this->assign('prop', $prop);
        
        $sel = M("PropertyValue")
                    ->where(array("key_id"=>$prop["key_id"]))
                    ->select();        
        $this->assign('value_list', $sel);
              
        $this->display("cats_prop_def_range");        
    }
    
    public function prop_range_post(){
        if(isset($_POST['ids'])){
            $ids = I('post.ids/a');
            
            //var_dump($ids);exit;
            
                $_model=M("CatsPropDef");
                if ($_model->create()!==false) {
                    if(!empty($_POST['id'])){
                        $data["id"]=$_POST['id'];
                        $data["value_id_range"] = join(",",$ids);
                        $result=$_model->save($data);
                        if ($result!==false) {
                            $this->success("保存成功！", U("AdminCats/prop_range",array('id'=>$_POST['id'])));   //
                        } else {
                            $this->error("保存失败！");
                        }                        
                    } else {
                            $this->error("添加失败！");
                    }
                } else {
                    $this->error($_model->getError());
                }
        }    
    }

    public function code_list(){
        $cid = I('get.id',0,'intval');
        $one_cid = $this->cats_model->where(array('id'=>$cid))->field('title')->find();
        $cid_title = $one_cid['title'];
        $list = $this->code_model->where(array('cid'=>$cid))->order('id desc')->select();

        $this->assign('cid_title',$cid_title);
        $this->assign('list',$list);

        $this->display(":AdminCats/code_list");
    }
    public function code_add(){
         $cid = I('get.id',0,'intval');
         $one_cid = $this->cats_model->where(array('id'=>$cid))->field('title')->find();
         $cid_title = $one_cid['title'];
         $this->assign('cid_title',$cid_title);
        $this->display();
    }

    public function code_add_post(){
        if (IS_POST) {
            $cid = $_POST['cid'];
            $code = $this->cats_model->where(array('id'=>$cid))->field('code')->find();
            $_POST['cat_code'] = $code['code'];
			if ($this->code_model->create()!==false) {
				if ($this->code_model->add()!==false) {
					$this->success("添加成功！",U("AdminCats/index"));
				} else {
					$this->error("添加失败！");
				}
			} else {
				$this->error($this->code_model->getError());
			}
		}
    }

    public function code_edit(){
        $id = I('get.id',0,'intval');
        $one_code = $this->code_model->where(array('id'=>$id))->find();

        $one_cid = $this->cats_model->where(array('id'=>$one_code['cid']))->field('title')->find();
        $cid_title = $one_cid['title'];
        
        $this->assign('cid_title',$cid_title);
        $this->assign('one_code',$one_code);
        $this->display();
    }

    public function code_edit_post(){
        if (IS_POST) {
			if ($this->code_model->create()!==false) {
				if ($this->code_model->save()!==false) {
					$this->success("修改成功！",U("AdminCats/code_list"));
				} else {
					$this->error("修改失败！");
				}
			} else {
				$this->error($this->code_model->getError());
			}
		}
    }
    public function delete_code() {
        $id = intval(I("get.id"));
        if ($this->code_model->delete($id)!==false) {
            $this->success("删除成功！");
        } else {
            $this->error("删除失败！");
        }
    }

}