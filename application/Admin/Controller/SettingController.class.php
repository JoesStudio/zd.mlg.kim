<?php
namespace Admin\Controller;
use Common\Controller\AdminbaseController;
class SettingController extends AdminbaseController{
	
	protected $options_model;
	
	public function _initialize() {
		parent::_initialize();
		$this->options_model = D("Common/Options");
	}
	
	public function site(){
	    C(S('sp_dynamic_config'));//加载动态配置
		$option=$this->options_model->where("option_name='site_options'")->find();
		$cmf_settings=$this->options_model->where("option_name='cmf_settings'")->getField("option_value");
		$tpls=sp_scan_dir(C("SP_TMPL_PATH")."*",GLOB_ONLYDIR);
		$noneed=array(".","..",".svn");
		$tpls=array_diff($tpls, $noneed);
		$this->assign("templates",$tpls);
		
		$adminstyles=sp_scan_dir("public/simpleboot/themes/*",GLOB_ONLYDIR);
		$adminstyles=array_diff($adminstyles, $noneed);
		$this->assign("adminstyles",$adminstyles);
		if($option){
			$this->assign(json_decode($option['option_value'],true));
			$this->assign("option_id",$option['option_id']);
		}
		
		$cdn_settings=sp_get_option('cdn_settings');
		
		$this->assign("cdn_settings",$cdn_settings);
		
		$this->assign("cmf_settings",json_decode($cmf_settings,true));
		
		$this->display();
	}
	
	public function site_post(){
		if (IS_POST) {
			if(isset($_POST['option_id'])){
				$data['option_id']=I('post.option_id',0,'intval');
			}
			$options=I('post.options/a');
			
			$configs["SP_SITE_ADMIN_URL_PASSWORD"]=empty($options['site_admin_url_password'])?"":md5(md5(C("AUTHCODE").$options['site_admin_url_password']));
			$configs["SP_DEFAULT_THEME"]=$options['site_tpl'];
			$configs["DEFAULT_THEME"]=$options['site_tpl'];
			$configs["SP_ADMIN_STYLE"]=$options['site_adminstyle'];
			$configs["URL_MODEL"]=$options['urlmode'];
			$configs["URL_HTML_SUFFIX"]=$options['html_suffix'];
			$configs["COMMENT_NEED_CHECK"]=empty($options['comment_need_check'])?0:1;
			$comment_time_interval=intval($options['comment_time_interval']);
			$configs["COMMENT_TIME_INTERVAL"]=$comment_time_interval;
			$_POST['options']['comment_time_interval']=$comment_time_interval;
			$configs["MOBILE_TPL_ENABLED"]=empty($options['mobile_tpl_enabled'])?0:1;
			$configs["HTML_CACHE_ON"]=empty($options['html_cache_on'])?false:true;
				
			sp_set_dynamic_config($configs);//sae use same function
				
			$data['option_name']="site_options";
			$data['option_value']=json_encode($options);
			if($this->options_model->where("option_name='site_options'")->find()){
				$result=$this->options_model->where("option_name='site_options'")->save($data);
			}else{
				$result=$this->options_model->add($data);
			}
			
			$cmf_settings=I('post.cmf_settings/a');
			$banned_usernames=preg_replace("/[^0-9A-Za-z_\x{4e00}-\x{9fa5}-]/u", ",", $cmf_settings['banned_usernames']);
			$cmf_settings['banned_usernames']=$banned_usernames;

			sp_set_cmf_setting($cmf_settings);
			
			$cdn_settings=I('post.cdn_settings/a');
			sp_set_option('cdn_settings', $cdn_settings);
			
			if ($result!==false) {
				$this->success("保存成功！");
			} else {
				$this->error("保存失败！");
			}
			
		}
	}
	
	public function password(){
		$this->display();
	}
	
	public function password_post(){
		if (IS_POST) {
			if(empty($_POST['old_password'])){
				$this->error("原始密码不能为空！");
			}
			if(empty($_POST['password'])){
				$this->error("新密码不能为空！");
			}
			$user_obj = D("Common/Users");
			$uid=sp_get_current_admin_id();
			$admin=$user_obj->where(array("id"=>$uid))->find();
			$old_password=I('post.old_password');
			$password=I('post.password');
			if(sp_compare_password($old_password,$admin['user_pass'])){
				if($password==I('post.repassword')){
					if(sp_compare_password($password,$admin['user_pass'])){
						$this->error("新密码不能和原始密码相同！");
					}else{
						$data['user_pass']=sp_password($password);
						$data['id']=$uid;
						$r=$user_obj->save($data);
						if ($r!==false) {
							$this->success("修改成功！");
						} else {
							$this->error("修改失败！");
						}
					}
				}else{
					$this->error("密码输入不一致！");
				}
	
			}else{
				$this->error("原始密码不正确！");
			}
		}
	}
	
	/**
	 * 上传限制设置界面
	 */
	public function upload(){
	    $upload_setting=sp_get_upload_setting();
	    $this->assign($upload_setting);
	    $this->display();
	}
	
	public function upload_post(){
	    if(IS_POST){
	        $result=sp_set_option('upload_setting',I('post.'));
	        if($result!==false){
	            $this->success('保存成功！');
	        }else{
	            $this->error('保存失败！');
	        }
	    }
	    
	}
	
	/**
	 * 清除缓存
	 */
	public function clearcache(){
		sp_clear_cache();
		$this->display();
	}



    public function basic(){
        $option_name = 'site_basic';
        $option_where = array('option_name'=>$option_name);
        if(IS_POST){
            if($_POST['key'] != $option_name) exit();
            $_POST[$option_name]['wxqrcode'] = sp_asset_relative_url($_POST[$option_name]['wxqrcode']);
            $option_value = json_encode(I('post.'.$option_name));

            $count = $this->options_model->where($option_where)->count();
            if($count > 0){
                $r = $this->options_model->where($option_where)->setField('option_value', $option_value);
            }else{
                $post['option_name'] = $option_name;
                $post['option_value'] = $option_value;
                $r = $this->options_model->add($post);
            }

            if ($r!==false) {
                $this->success("保存成功！");
            } else {
                $this->error("保存失败！");
            }
        }else{
            $options = $this->options_model->where($option_where)->getField('option_value');
            $options = json_decode($options, true);

            $navs = M('NavCat')->getField('navcid,name,remark');
            $this->assign('navs',$navs);

            $this->_getNewsTermTree($options['footer_article_term'], 'article','footer_article_terms');

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