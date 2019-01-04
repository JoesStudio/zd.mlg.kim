<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-12-22
 * Time: 16:59
 */

namespace Tf\Controller;


use Common\Controller\AdminbaseController;

class AdminController extends AdminbaseController
{

    protected $_model;

    function _initialize() {
        parent::_initialize();
        $this->_model = D("TextileFabric");
        $this->tf_terms_model = D('Tf/Term');
        $this->tf_term_rid_model = D('Tf/TermTf');
        $this->assign('tf_terms', $this->tf_terms_model->terms());
        $this->assign('statuses', $this->_model->statuses);
    }

    public function index() {

        $memberList = D('BizMember')->getMembersNoPaged();
        
        $this->assign("memberList",$memberList);

        $where = array();
        if(isset($_REQUEST['status'])){
            $_REQUEST['filter']['on_sale'] = $_REQUEST['on_sale'];
        }
        if(isset($_REQUEST['on_sale'])){
            $_REQUEST['filter']['on_sale'] = $_REQUEST['on_sale'];
        }
        if(isset($_REQUEST['filter'])){
            $filter = I('request.filter');
            if(isset($filter['on_sale'])){
                $where['on_sale'] = $filter['on_sale'];
            }

            if(!empty($filter['supplier_id'])){
                if(isset($filter['supplier_id'])){
                    if($filter['supplier_id'] > 0){
                        $where['vend_id'] = $filter['supplier_id'];
                    }
                }
            }
            if(!empty($filter['keywords'])){
                $where['name|code|component'] = array('LIKE','%'.$filter['keywords'].'%');
            }
            if(!empty($filter['datestart']) && !empty($filter['datefinish'])){
                $where['UNIX_TIMESTAMP(created_at)'] = array('between', strtotime($filter['datestart']) . ',' . strtotime($filter['datefinish']));
            }elseif(!empty($filter['datestart']) && empty($filter['datefinish'])){
                $where['UNIX_TIMESTAMP(created_at)'] = array('egt', strtotime($filter['datestart']));
            }elseif(empty($filter['datestart']) && !empty($filter['datefinish'])){
                $where['UNIX_TIMESTAMP(created_at)'] = array('elt', strtotime($filter['datefinish']));
            }

            
            $this->assign('filter',$filter);
        }

        $result = $this->_model->getTfPaged($where,10);
        $this -> assign("list",$result);

        
        $cats_model = D("Portal/TextileFabricCats");

        $tree = new \Tree();
        $tree->icon = array('&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─ ', '&nbsp;&nbsp;&nbsp;└─ ');
        $tree->nbsp = '&nbsp;&nbsp;&nbsp;';
        $terms = $cats_model->order(array("listorder"=>"asc"))->select();

        $new_terms=array();
        foreach ($terms as $r) {
            //$r['id']=$r['id'];
            $r['parentid']=$r['pid'];
            $r['selected']= (!empty($parentid) && $r['id']==$parentid)? "selected":"";
            $new_terms[] = $r;
        }
        $tree->init($new_terms);
        $tree_tpl="<option value='\$id' \$selected>\$spacer\$title</option>";
        $tree=$tree->get_tree(0,$tree_tpl);

        $this->assign("cats_tree",$tree);

        

        $this -> display();
    }

    //面料名称代码ajax查询
    function cid_ajax(){
        $cid = $_GET['cid'];
        $data['data'] = $TextileFabricName = M("TextileFabricName")->where("cid=$cid")->select();
        $this-> ajaxReturn($data);
    }

    function add(){
        $cid = I('get.cid');
        $name_code = I("get.name_code");
        $vend_id = $_GET['vend_id'];

        if(empty($cid)){
            $this->error("请先选择面料分类！");
        }

        if(empty($name_code)){
            $this->error("请先选择面料名称！");
        }

        if(IS_AJAX){
            $this->success('',U('add',array('vend_id'=>$vend_id,'cid'=>$cid,'name_code'=>$name_code)));
        }

        $supplier = D('BizMember')->getMember($vend_id);
        $this->assign('supplier',$supplier);

        $cdata=D("Portal/TextileFabricCats")->where(array("id" => $cid))->find();
        $this->assign('cat', $cdata);
        $this->assign("cid",$cid);
        $this->assign("cat_name",$cdata["title"]);
        //\\\
        //所属名称
        $cdata=D("Portal/TextileFabricName")->where(array("code" => $name_code))->find();
        $this->assign("name_code",$name_code);
        $this->assign("code_name",$cdata["cname"]);
        //\\\
        //获取所属分类扩展属性
        $cdata=M("CatsPropDef")
            ->alias("a")
            ->join("__PROPERTY__ b ON a.key_id=b.id")   //
            ->join("__TEXTILE_FABRIC_PROP__ c ON a.key_id=c.key_id and c.tf_id=''",'LEFT')   //
            ->where(array("cid" => $cid,"is_sale"=>0)) //非销售属性
            //->order("id DESC")
            ->field('a.*,b.key_name,c.id as tfp_id,c.tf_id,c.value_id,c.value_text')
            //->limit($page->firstRow . ',' . $page->listRows)
            ->select();

        foreach ($cdata as $k => $v) {
            $ids = join(",",array_filter(explode(",",$v["value_id_range"])));
            //echo "ids=".$ids."<br>\n";
            if($ids==""){
                $where =array("key_id"=>$v["key_id"]);
            } else {
                $where =array("key_id"=>$v["key_id"],"id"=>array("in",$ids));
            }
            $vdata=M("PropertyValue")->where($where)->select();

            $cdata[$k]["value"]=$vdata;
        }
        $this->assign("ex_list",$cdata);

        $this->display();
    }

    //保存面料分类
    function add_post(){
        if (IS_POST) {
            $nameCode = I('post.name_code','');
            $cid = I('post.cid/d',0);
            $tfcodeprefix = M('TextileFabricName')->field("CONCAT('{$_POST['vend_id']}',cat_code,code) as prefix")
                ->where("code='{$nameCode}' AND cid='{$cid}'")->find();
            if(empty($tfcodeprefix)){
//                $this->error('分类名称错误！');
            }
            $code = I('post.code', '');
            if ($code == '') {
                $this->error('自编码不能为空！');
            }
            $tf_code = $tfcodeprefix['prefix'].$code;
            $codeExist = M('UnionTfView')
                ->where("tf_code='{$tf_code}'")
                ->count();
            if ($codeExist > 0
            ) {
                $this->error('该编码已经存在，请更换！');
            }


            if(!empty($_POST['photos_alt']) && !empty($_POST['photos_url'])){
                foreach ($_POST['photos_url'] as $key=>$url){
                    $photourl=sp_asset_relative_url($url);    //
                    $img['photo'][]=array("url"=>$photourl,"alt"=>$_POST['photos_alt'][$key]);
                }
            }
            $img['thumb'] = sp_asset_relative_url($_POST['smeta']['thumb']); //

            $cih = new \ImageHash();
            $aHash = $cih::hashImageFile(sp_get_image_preview_url($img['thumb']));
            //$img['thumbHash'] = $aHash;
            $_POST['img_hash']=$aHash;

            $_POST['img']=json_encode($img);                                //主图 相册图集
            $_POST['describe']=htmlspecialchars_decode($_POST['describe']); //面料描述
            //$_POST['describe']=sp_get_image_preview_url($img['thumb']);
            //$_POST['describe']=mysql_real_escape_string(json_encode($img));

            $_POST['cat_code'] = D('Tf/Cat')->where(array('id'=>I('post.cid/d',0)))->getField('code');
            //print_r($_POST);
            $result = $this->_model->addTf($_POST);
            if($result !== false){
                F('all_tf',null);

                /*面料多样性分类*/
                foreach ($_POST['term'] as $mterm_id){
                    $this->tf_term_rid_model->add(array("tf_term_id"=>intval($mterm_id),"tf_id"=>$result));
                }

                $tf_id = $this->_model->getLastInsID();

                /*$sku_model = D('Tf/Sku');
                $skuData = $sku_model->formatData($_POST['sku'],$tf_id);
                $sku_model->SaveToSku($tf_id, $skuData);*/
                $prop_model = D('Tf/Prop');
                $prop_model->SaveToProp($tf_id, $_POST);

                $this->success("添加成功！",U("edit",array('id'=>$result)));
            }else{
                $this->error("添加失败！".$this->_model->getError());
            }
        }
    }

    function edit($id){
        $where['tf_id'] = $id;
        $data = $this->_model->getTf($id);

        $this->assign("smeta",$data['img']);

        
        $cdata=D("Portal/TextileFabricCats")->where(array("id" => $data['cid']))->find();
        $this->assign('cat', $cdata);
        $this->assign("cid",$cid);
        $this->assign("cat_name",$cdata["title"]);
        //\\\
        //所属名称
        $cdata=D("Portal/TextileFabricName")->where(array("code" => $data['name_code']))->find();
        $this->assign("name_code",$name_code);
        $this->assign("code_name",$cdata["cname"]);

        //面料多样性分类
        $rids = $this->tf_term_rid_model->where(array("tf_id"=>$id,"status"=>1))->getField("tf_term_id",true);
        $this->assign('rids',$rids);
        //获取所属分类扩展属性
        $cdata=M("CatsPropDef")
            ->alias("a")
            ->join("__PROPERTY__ b ON a.key_id=b.id")   //
            ->join("__TEXTILE_FABRIC_PROP__ c ON a.key_id=c.key_id and c.tf_id=$id",'LEFT')   //
            ->where(array("cid" => $data['cid'],"is_sale"=>0)) //非销售属性
            //->order("id DESC")
            ->field('a.*,b.key_name,c.id as tfp_id,c.tf_id,c.value_id,c.value_text')
            ->select();

        foreach ($cdata as $k => $v) {
            $ids = join(",",array_filter(explode(",",$v["value_id_range"])));
            if($ids==""){
                $where =array("key_id"=>$v["key_id"]);
            } else {
                $where =array("key_id"=>$v["key_id"],"id"=>array("in",$ids));
            }
            $vdata=M("PropertyValue")->where($where)->select();

            $cdata[$k]["value"]=$vdata;
        }
        $this->assign("ex_list",$cdata);
        //\\\获取所属分类扩展属性

        $this->assign("id",$id);


        $this->assign("data",$data);

        $this->display();
    }

    function edit_post(){
        if (IS_POST) {
            $id = I('post.id/d', 0);
            $code = I('post.code', '');
            if ($code == '') {
                $this->error('自编码不能为空！');
            }
            $newtfcode = $this->_model
                ->field("CONCAT(vend_id,cat_code,name_code,'{$code}') as tf_code")
                ->where("id='{$id}'")
                ->find();
            $codeExist = M('UnionTfView')
                ->where("tf_code='{$newtfcode['tf_code']}' AND NOT (id='{$id}' AND source=1)")
                ->count();
            if ($codeExist > 0
            ) {
                $this->error('该编码已经存在，请更换！');
            }

            if(!empty($_POST['photos_alt']) && !empty($_POST['photos_url'])){
                foreach ($_POST['photos_url'] as $key=>$url){
                    $photourl=sp_asset_relative_url($url);    //
                    $img['photo'][]=array("url"=>$photourl,"alt"=>$_POST['photos_alt'][$key]);
                }
            }
            $img['thumb'] = sp_asset_relative_url($_POST['smeta']['thumb']); //

            $cih = new \ImageHash();
            $aHash = $cih::hashImageFile(sp_get_image_preview_url($img['thumb']));
            //$img['thumbHash'] = $aHash;
            $_POST['img_hash']=$aHash;

            $_POST['img']=json_encode($img);                                //主图 相册图集
            $_POST['describe']=htmlspecialchars_decode($_POST['describe']); //面料描述
            //$_POST['describe']=sp_get_image_preview_url($img['thumb']);
            //$_POST['describe']=mysql_real_escape_string(json_encode($img));

            //print_r($_POST);
            $result = $this->_model->updateTf($_POST);
            if($result !== false){
                F('all_tf',null);
                $tf_id = $_POST['id'];

                /*面料多样性分类*/
                if(empty($_POST['term'])){
                    $this->tf_term_rid_model->where(array("tf_id"=>$tf_id))->delete();
                }else{
                    $this->tf_term_rid_model->where(array("tf_id"=>$tf_id,"tf_term_id"=>array("not in",implode(",", $_POST['term']))))->delete();
                    foreach ($_POST['term'] as $mterm_id){
                        $find_term_relationship=$this->tf_term_rid_model->where(array("tf_id"=>$tf_id,"tf_term_id"=>$mterm_id))->count();
                        if(empty($find_term_relationship)){
                            $this->tf_term_rid_model->add(array("tf_term_id"=>intval($mterm_id),"tf_id"=>$tf_id));
                        }else{
                            $this->tf_term_rid_model->where(array("tf_id"=>$tf_id,"tf_term_id"=>$mterm_id))->save(array("status"=>1));
                        }
                    }
                }

                $prop_model = D('Tf/Prop');
                $prop_model->SaveToProp($tf_id, $_POST);

                $this->success("修改成功！",U("edit",array('id'=>$tf_id)));
            }else{
                $this->error("修改失败！".$this->_model->getError());
            }
        }
    }

    function delete(){
        if(IS_POST){
            $id = I('post.id');
            $fabric = $this->_model->where(array('id'=>$id))->find();
            if($fabric){
                $result = $this->_model->where(array('id'=>$id))->delete();
                if($result !== false){
                    $this->ajaxReturn(array(
                        'status'    => 1,
                        'info'      => '面料已删除！',
                        'data'      => array('id'=>$id),
                    ));
                }else{
                    $this->error($this->_model->getError());
                }
            }else{
                $this->error('错误的操作！');
            }
        }
    }

    function ban(){
        if(IS_POST){
            $id = I('post.id');
            $fabric = $this->_model->where(array('id'=>$id))->find();
            if($fabric){
                if($_GET['unpass']){
                    $result = $this->_model->where(array('id'=>$id))->save(array('status'=>0));
                }

                if($_GET['pass']){
                    $result = $this->_model->where(array('id'=>$id))->save(array('status'=>1));
                }
                
                if($result !== false){
                    if($_GET['unpass']){
                        $this->ajaxReturn(array(
                        'status'    => 1,
                        'info'      => '该面料已下架！',
                        'data'      => array('id'=>$id),
                        ));
                    }

                    if($_GET['pass']){
                        $this->ajaxReturn(array(
                        'status'    => 1,
                        'info'      => '该面料已上架！',
                        'data'      => array('id'=>$id),
                        ));
                    }
                    
                }else{
                    $this->error($this->_model->getError());
                }
            }else{
                $this->error('错误的操作！');
            }
        }
    }

    function secret(){
        if(IS_POST){
            $id = I('post.id');
            $fabric = $this->_model->where(array('id'=>$id))->find();
            if($fabric){
                if($_GET['intimate']){
                    $result = $this->_model->where(array('id'=>$id))->save(array('ispublic'=>0));
                }

                if($_GET['unintimate']){
                    $result = $this->_model->where(array('id'=>$id))->save(array('ispublic'=>1));
                }
                
                if($result !== false){
                    if($_GET['intimate']){
                        $this->ajaxReturn(array(
                        'status'    => 1,
                        'info'      => '该面料已设置公开！',
                        'data'      => array('id'=>$id),
                        ));
                    }

                    if($_GET['unintimate']){
                        $this->ajaxReturn(array(
                        'status'    => 1,
                        'info'      => '该面料已设置私密！',
                        'data'      => array('id'=>$id),
                        ));
                    }
                    
                }else{
                    $this->error($this->_model->getError());
                }
            }else{
                $this->error('错误的操作！');
            }
        }
    }

    public function toggle_public()
    {
        if (IS_POST) {
            $id = I('post.id/d', 0);
            $value = I('post.value/d', 1);
            if ($id == 0) {
                $this->error('传入数据出错！');
            }

            $result = $this->_model->where(array('id' => $id))->setField('ispublic', $value);
            $tf = $this->_model->field('name,ispublic as value')->where(array('id' => $id))->find();
            $data['data'] = $tf['value'];
            $data['status'] = $result !== false ? 1:0;
            $data['info'] = $tf['name'].'已设为<b>'.($data['data'] ? '公开':'私密').'</b>';
            $this->ajaxReturn($data);
        }
    }

    public function toggle_status()
    {
        if (IS_POST) {
            $id = I('post.id/d', 0);
            $value = I('post.value/d', 1);
            if ($id == 0) {
                $this->error('传入数据出错！');
            }

            $result = $this->_model->where(array('id' => $id))->setField('status', $value);
            $tf = $this->_model->field('name,status as value')->where(array('id'=>$id))->find();
            $data['data'] = $tf['value'];
            $data['status'] = $result !== false ? 1:0;
            $data['info'] = $tf['name'].'<b>'.($data['data'] ? '已上线':'已下线').'</b>';
            $this->ajaxReturn($data);
        }
    }
}
