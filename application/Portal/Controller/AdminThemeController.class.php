<?php
/**
 * Created by PhpStorm.
 * User: Jason
 * Date: 2016-09-01
 * Time: 10:26
 */

namespace Portal\Controller;


use Common\Controller\AdminbaseController;

class AdminThemeController extends AdminbaseController
{

    protected $_model;

    function _initialize() {
        parent::_initialize();
        $this->_model = D("Common/Options");
    }

    public function home(){
        $option_name = 'theme_home';
        $option_where = array('option_name'=>$option_name);
        if(IS_POST){
            if($_POST['key'] != $option_name) exit();
            $_POST['theme_home']['bgmp3'] = sp_asset_relative_url($_POST['theme_home']['bgmp3']);
            $option_value = json_encode(I('post.theme_home'));

            $data = $this->_model->where($option_where)->find();
            if($data){
                $r = $this->_model->where($option_where)->setField('option_value', $option_value);
            }else{
                $post['option_name'] = $option_name;
                $post['option_value'] = $option_value;
                $post['autoload'] = 0;
                $r = $this->_model->add($post);
            }

            if ($r!==false) {
                $this->success("保存成功！");
            } else {
                $this->error("保存失败！");
            }
        }else{
            $options = $this->_model->where($option_where)->getField('option_value');
            $options = json_decode($options, true);

            $slidecats=M('SlideCat')->where("cat_status!=0")->select();
            $this->assign("slidecats",$slidecats);

            $this->_getNewsTermTree($options['news'], 'article','news_terms');

            $this->assign('options',$options);
            $this->display();
        }
    }

    private function _getNewsTermTree($term=array(),$taxonomy='article',$assign_name='taxonomys'){
        $result = M('Terms')->where(array('taxonomy'=>$taxonomy))->order(array("listorder"=>"asc"))->select();

        $tree = new \Tree();
        $tree->icon = array();
        $tree->nbsp = '';

        $ids = array();
        foreach ($result as $r) {
            $r['id']=$r['term_id'];
            $r['parentid']=$r['parent'];
            if(is_array($term)){
                $r['selected']=in_array($r['term_id'], $term)?"selected":"";
                $r['checked'] =in_array($r['term_id'], $term)?"checked":"";
            }else{
                $r['selected']=$r['term_id'] ==$term?"selected":"";
                $r['checked'] =$r['term_id'] ==$term?"checked":"";
            }
            $array[] = $r;
        }

        $tree->init($array);
        $str="<option value='\$id' \$selected>\$spacer\$name</option>";
        $taxonomys = $tree->get_tree(0, $str);
        $this->assign($assign_name, $taxonomys);
    }

}