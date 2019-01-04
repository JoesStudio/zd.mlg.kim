<?php
/**
 * 后台首页
 */
namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class IndexController extends AdminbaseController {
	
	public function _initialize() {
	    empty($_GET['upw'])?"":session("__SP_UPW__",$_GET['upw']);//设置后台登录加密码	    
		parent::_initialize();
		$this->initMenu();
	}
	
    /**
     * 后台框架首页
     */
    public function index() {
        //$this->load_menu_lang();
        //$this->assign("SUBMENU_CONFIG", D("Common/Menu")->menu_json());

        $orders_model = D('Order/Order');
        $unconfirm_card_num = $orders_model
            ->where(array('order_status'=>0,'is_colorcard'=>1,'order_trash'=>0))
            ->count();

        $this->assign('unconfirm_card_num',$unconfirm_card_num);
        $unconfirm_num = $orders_model
            ->where(array('order_status'=>0,'is_colorcard'=>0,'order_trash'=>0))
            ->count();
        $this->assign('unconfirm_num',$unconfirm_num);

        $submenus = D("Common/Menu")->menu_json();
        $this->assign('mainmenu',$this->getsubmenu($submenus));

       	$this->display();
        
    }
    
    private function load_menu_lang(){
        if (!C('LANG_SWITCH_ON',null,false)) return;
        $default_lang=C('DEFAULT_LANG');
        
        $langSet=C('ADMIN_LANG_SWITCH_ON',null,false)?LANG_SET:$default_lang;
        
	    $apps=sp_scan_dir(SPAPP."*",GLOB_ONLYDIR);
	    $error_menus=array();
	    foreach ($apps as $app){
	        if(is_dir(SPAPP.$app)){
	            if($default_lang!=$langSet){
	                $admin_menu_lang_file=SPAPP.$app."/Lang/".$langSet."/admin_menu.php";
	            }else{
	                $admin_menu_lang_file=SITE_PATH."data/lang/$app/Lang/".$langSet."/admin_menu.php";
	                if(!file_exists_case($admin_menu_lang_file)){
	                    $admin_menu_lang_file=SPAPP.$app."/Lang/".$langSet."/admin_menu.php";
	                }
	            }
	            
	            if(is_file($admin_menu_lang_file)){
	                $lang=include $admin_menu_lang_file;
	                L($lang);
	            }
	        }
	    }
    }

    function getsubmenu($submenus, $current_level=1){
        $nav_class = $current_level > 1 ? 'submenu':'nav nav-list';
        $str = '<ul class="'.$nav_class.'">';
        foreach ($submenus as $menu) {
            $str .= '<li>';
            $menu_name = $menu['name'];
            $url = empty($menu['items']) ? 'javascript:openapp(\''.$menu['url'].'\',\''.$menu['id'].'\',\''.$menu_name.'\',true);':'#';

            $dropdown_class = !empty($menu['items']) ? 'dropdown-toggle':'';
            $str .= '<a class="'.$dropdown_class.'" href="'.$url.'" title="'.$menu_name.'">';
            if($current_level == 1){
                $menu['icon'] = $menu['icon'] ? $menu['icon']:'desktop';
                $str .= '<i class="fa fa-lg fa-fw fa-'.$menu['icon'].'"></i>';
            }elseif($current_level == 2){
                $str .= '<i class="fa fa-caret-right"></i>';
            }elseif($current_level == 3){
                $str .= '&nbsp;<i class="fa fa-double-right"></i>';
            }
            $str .= '<span class="menu-text">'.$menu_name.'</span>';
            if (!empty($menu['items'])) {
                $str .= '<b class="arrow fa fa-angle-right normal"></b>';
                if($current_level == 1){
                    $str .='<i class="fa fa-reply back"></i><span class="menu-text back">返回</span>';
                }
            }
            $str .= '</a>';
            if (!empty($menu['items'])) {
                $str .= $this->getsubmenu($menu['items'], $current_level+1);
            }
            $str .= '</li>';
        }
        $str .= '</ul>';
        return $str;
    }

}

