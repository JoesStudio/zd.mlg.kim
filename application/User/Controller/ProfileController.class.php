<?php

/**
 * 会员中心
 */
namespace User\Controller;
use Common\Controller\MemberbaseController;
class ProfileController extends MemberbaseController {
    protected $users_model;
    protected $member_model;
    protected $contact_model;
	protected $user_info_model;

	function _initialize(){
		parent::_initialize();
        $this->users_model = M('Users');
        $this->member_model = M('Biz_member');
        $this->contact_model = M('Biz_contact');
        $this->user_info_model = M('User_info');
	}
	public function user_info(){
        $user_info = session('user');
        $user_id = $user_info['id'];

        $join1 = 'LEFT JOIN __BIZ_MEMBER__ b ON b.id = a.id';
        $join2 = 'LEFT JOIN __BIZ_CONTACT__ c ON c.id = a.id';
        $join3 = 'LEFT JOIN __AREAS__ d ON d.id = c.contact_province';
        $join4 = 'LEFT JOIN __AREAS__ e ON e.id = c.contact_city';
        $join5 = 'LEFT JOIN __AREAS__ f ON f.id = c.contact_district';
        $biz_info=$this->users_model
        ->alias('a')
        ->field('a.*,b.*,c.*,d.name as contact_province,e.name as contact_city,f.name as contact_district')
        ->join($join1)
        ->join($join2)
        ->join($join3)
        ->join($join4)
        ->join($join5)
        ->where("a.id=$user_id")
        ->find();

        $this->assign('biz_info',$biz_info);
        
        $this->display(":profile/personal-data");
    }

    //用户资料
	public function edit() {

        $user_info = session('user');
        $user_id = $user_info['id'];

        $join1 = 'LEFT JOIN __BIZ_MEMBER__ b ON b.id = a.id';
        $join2 = 'LEFT JOIN __BIZ_CONTACT__ c ON c.id = a.id';
        $join3 = 'LEFT JOIN __AREAS__ d ON d.id = c.contact_province';
        $join4 = 'LEFT JOIN __AREAS__ e ON e.id = c.contact_city';
        $join5 = 'LEFT JOIN __AREAS__ f ON f.id = c.contact_district';
        $biz_info=$this->users_model
        ->alias('a')
        ->field('a.*,b.*,c.*,d.name as contact_province,e.name as contact_city,f.name as contact_district')
        ->join($join1)
        ->join($join2)
        ->join($join3)
        ->join($join4)
        ->join($join5)
        ->where("a.id=$user_id")
        ->find();

        $this->assign('biz_info',$biz_info);
    	$this->display(":profile/change-personal-data");
    }
    
    public function edit_post() {
    	if(IS_POST){
            $_POST['id']=session("user.id");

    		if (($this->users_model->create() && $this->member_model->create() && $this->contact_model->create())!==false) {

				if ($this->users_model->save()!==false) {
                    if($this->member_model->save()!==false){
                        if($this->contact_model->save()!==false){
                            sp_update_current_user($this->user);
                         $this->success("保存成功！",U("user/profile/user_info"));
                        }
                    }
                   
				} else {
					$this->error("保存失败！");
				}
			} else {
				$this->error($this->users_model->getError());
			}
    	}
    	
    }
    
    public function password() {
		$this->assign($this->user);
    	$this->display();
    }
    
    public function password_post() {
    	if (IS_POST) {
    	    $old_password=I('post.old_password');
    		if(empty($old_password)){
    			$this->error("原始密码不能为空！");
    		}
    		
    		$password=I('post.password');
    		if(empty($password)){
    			$this->error("新密码不能为空！");
    		}
    		
    		$uid=sp_get_current_userid();
    		$admin=$this->users_model->where(array('id'=>$uid))->find();
    		if(sp_compare_password($old_password, $admin['user_pass'])){
    			if($password==I('post.repassword')){
    				if(sp_compare_password($password, $admin['user_pass'])){
    					$this->error("新密码不能和原始密码相同！");
    				}else{
    					$data['user_pass']=sp_password($password);
    					$data['id']=$uid;
    					$r=$this->users_model->save($data);
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
    
    
    function bang(){
    	$oauth_user_model=M("OauthUser");
    	$uid=sp_get_current_userid();
    	$oauths=$oauth_user_model->where(array("uid"=>$uid))->select();
    	$new_oauths=array();
    	foreach ($oauths as $oa){
    		$new_oauths[strtolower($oa['from'])]=$oa;
    	}
    	$this->assign("oauths",$new_oauths);
    	$this->display();
    }
    
    function avatar(){
		$this->assign($this->user);
    	$this->display();
    }
    
    function avatar_upload(){
    	$config=array(
    			'rootPath' => './'.C("UPLOADPATH"),
    			'savePath' => './avatar/',
    			'maxSize' => 512000,//500K
    			'saveName'   =>    array('uniqid',''),
    			'exts'       =>    array('jpg', 'png', 'jpeg'),
    			'autoSub'    =>    false,
    	);
    	$upload = new \Think\Upload($config,'Local');//先在本地裁剪
    	$info=$upload->upload();
    	//开始上传
    	if ($info) {
    	//上传成功
    	//写入附件数据库信息
    		$first=array_shift($info);
    		$file=$first['savename'];
    		session('avatar',$file);
    		$this->ajaxReturn(sp_ajax_return(array("file"=>$file),"上传成功！",1),"AJAX_UPLOAD");
    	} else {
    		//上传失败，返回错误
    		$this->ajaxReturn(sp_ajax_return(array(),$upload->getError(),0),"AJAX_UPLOAD");
    	}
    }
    
    function avatar_update(){
        $session_avatar=session('avatar');
    	if(!empty($session_avatar)){
    		$targ_w = I('post.w',0,'intval');
    		$targ_h = I('post.h',0,'intval');
    		$x = I('post.x',0,'intval');
    		$y = I('post.y',0,'intval');
    		$jpeg_quality = 90;
    		
    		$avatar=$session_avatar;
    		$avatar_dir=C("UPLOADPATH")."avatar/";
    		
    		$avatar_path=$avatar_dir.$avatar;
    		
    		$image = new \Think\Image();
    		$image->open($avatar_path);
    		$image->crop($targ_w, $targ_h,$x,$y);
    		$image->save($avatar_path);
    		
    		$result=true;
    		
    		$file_upload_type=C('FILE_UPLOAD_TYPE');
    		if($file_upload_type=='Qiniu'){
    		    $upload = new \Think\Upload();
    		    $file=array('savepath'=>'','savename'=>'avatar/'.$avatar,'tmp_name'=>$avatar_path);
    		    $result=$upload->getUploader()->save($file);
    		}
    		if($result===true){
    		    $userid=sp_get_current_userid();
    		    $result=$this->user_info_model->where(array("user_id"=>$userid))->save(array("avatar"=>'avatar/'.$avatar));
                $_SESSION['user']['userinfo']['avatar'] = 'avatar/'.$avatar;
    		    /*session('user.avatar','avatar/'.$avatar);*/
    		    if($result){
    		        $this->success("头像更新成功！");
    		    }else{
    		        $this->error("头像更新失败！");
    		    }
    		}else{
    		    $this->error("头像保存失败！");
    		}
    		
    	}
    }
    public function do_avatar() {
		$imgurl=I('post.imgurl');
		//去'/'
		$imgurl=str_replace('/','',$imgurl);
		$old_img=$this->user['avatar'];
		$this->user['avatar']=$imgurl;
		$res=$this->users_model->where(array("id"=>$this->userid))->save($this->user);		
		if($res){
			//更新session
			session('user',$this->user);
			//删除旧头像
			sp_delete_avatar($old_img);
		}else{
			$this->user['avatar']=$old_img;
			//删除新头像
			sp_delete_avatar($imgurl);
		}
		$this->ajaxReturn($res);
	}       
}