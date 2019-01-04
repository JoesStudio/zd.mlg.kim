<?php
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2014 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
namespace Portal\Controller;
use Common\Controller\HomebaseController;
use Portal\Service\ApiService;

/**
 * 首页
 */
class IndexController extends HomebaseController {
    protected $tf_model;
	protected $tf_term_model;

    function _initialize() {
        parent::_initialize();
        $this->tf_model = M("Textile_fabric");
        $this->biz_model = M("Biz_member");

    }

    //首页 
	public function index() {
//        header("location: /Tf/List");


//        $options_model = M('Options');
//        $home_opts = $options_model->where(array('option_name'=>'theme_home'))->getField('option_value');
//        $home_opts = json_decode($home_opts, true);
//
//        $home = F('theme_home_data');
//        if(empty($home)){
//
//            $home['home_slides'] = $trend_slides = sp_getslide($home_opts['main_banner']);
//
//            $home['fashion'] = D('Tf/Term')->getTerm(array('taxonomy'=>'fashion'));
//
//            $home['suppliers'] = D('BizMember')->getMembersNoPaged('limit:8;user_type:21;user_status:1;');
//
//            $home['tfcats'] = D('Tf/Cat')->cats("pid:0;");
//
//            $home['news'] = ApiService::postsByTermId($home_opts['news'],'');
//
//            F('theme_home_data', $home);
//        }
//        $this->assign($home);
//

        if (!sp_is_mobile()) {
            $this->display(":index");
        } else {
            $this->display(":index");
//            header("location: " . C("NEW_DOMAIN_URL"));
//            header("location: /Tf/list");
        }

    }

    function index2(){
        $home_slides=sp_getslide("portal_index");

        $home_slides=empty($home_slides)?$default_home_slides:$home_slides;
        $this->assign("home_slides",$home_slides);

        $trend_slides=sp_getslide("index_trend");
        $trend_slides=empty($trend_slides)?$default_home_slides:$trend_slides;
        $this->assign("trend_slides",$trend_slides);


        $join = "LEFT JOIN __TF_TERM_RELATIONSHIPS__ b ON b.tf_term_id = a.tf_term_id";
        $join1 = "LEFT JOIN __TEXTILE_FABRIC__ c ON c.id = b.tf_id ";

        /*时尚潮流*/
        $fashion_tf = $this->tf_term_model->alias('a')->join($join)->join($join1)->where('taxonomy="fashion"')->order(array('created_at'=>"desc"))->select();
        /*热销*/
        $hot_tf = $this->tf_term_model->alias('a')->join($join)->join($join1)->where('taxonomy="hot"')->order(array('created_at'=>"desc"))->select();

        $term_fashion = $this->tf_term_model->where('taxonomy="fashion"')->find();
        $term_hot = $this->tf_term_model->where('taxonomy="hot"')->find();

        $this->assign("fashion_tf",$fashion_tf);
        $this->assign("hot_tf",$hot_tf);
        $this->assign("term_fashion",$term_fashion);
        $this->assign("term_hot",$term_hot);


        /*韩国馆*/
        $korea_tf = $this->tf_term_model->alias('a')->join($join)->join($join1)->where('taxonomy="korea"')->order(array('created_at'=>"desc"))->select();
        /*意大利馆*/
        $italy_tf = $this->tf_term_model->alias('a')->join($join)->join($join1)->where('taxonomy="italy"')->order(array('created_at'=>"desc"))->select();

        $term_korea = $this->tf_term_model->where('taxonomy="korea"')->find();
        $term_italy = $this->tf_term_model->where('taxonomy="italy"')->find();

        $this->assign("korea_tf",$korea_tf);
        $this->assign("italy_tf",$italy_tf);
        $this->assign("term_korea",$term_korea);
        $this->assign("term_italy",$term_italy);

        /*纯棉/麻*/
        $cotton_tf = $this->tf_term_model->alias('a')->join($join)->join($join1)->where('taxonomy="cotton"')->order(array('created_at'=>"desc"))->select();
        $fibre_tf = $this->tf_term_model->alias('a')->join($join)->join($join1)->where('taxonomy="fibre"')->order(array('created_at'=>"desc"))->select();

        $term_cotton = $this->tf_term_model->where('taxonomy="cotton"')->find();
        $term_fibre = $this->tf_term_model->where('taxonomy="fibre"')->find();

        $this->assign("cotton_tf",$cotton_tf);
        $this->assign("term_cotton",$term_cotton);
        $this->assign("fibre_tf",$fibre_tf);
        $this->assign("term_fibre",$term_fibre);

        /*商家专场*/
        $biz_list = $this->biz_model->where(array("recommend"=>1,"status"=>1,"authenticated"=>1,"type"=>1,"vend_id"=>array('neq','')))->order(array('created_at'=>"desc"))->select();
        $this->assign("biz_list",$biz_list);

        $this->display(":index");
    }

    public function test()
    {
        $this->error('测试错误！');
    }

    public function about(){
        $this->display(":about-us");
    }
    public function contact(){
        $this->display(":contact-us");
    }
    public function help(){
        $this->display(":help-center");
    }
    public function safeguard(){
        $this->display(":help-center1");
    }
    public function register(){
        $this->display(":help-center2");
    }
    public function statement(){
        $this->display(":help-center3");
    }

    public function build_thumbs(){
        $Img = new \Think\Image();//实例化图片类对象
        $assets = M('Asset')->select();
        foreach($assets as $asset){
            $img_path = C("UPLOADPATH").$asset['filepath'];
            if(!file_exists($img_path)) continue;
            $oName = end(explode('/', $asset['filepath']));
            $thumbName = 'thumb_'.$oName;
            $thumb = str_replace($oName, $thumbName, $asset['filepath']);
            $thumb = str_replace($asset['filepath'], '', $thumb);
            if(file_exists(C("UPLOADPATH").$thumb)) continue;
            echo $img_path."<br>";
            $Img->open($img_path);
            $Img->thumb(540,540);



            $Img->save(C("UPLOADPATH").$thumb);
        }
        exit('finish');
    }
    public function build_thumbs2(){
        $files = $this->my_scandir('data/upload');
    }

    function buildThumb($filename, $filepath){
        $filesize=abs(filesize($filepath));
        if($filesize<20480) return false;
        $ext = strtolower(substr(strrchr($filename, '.'), 1));
        if($ext == 'jpg' || $ext == 'jpeg' || $ext == 'png'){
            $Img = new \Think\Image();//实例化图片类对象
            if(!file_exists($filepath)) return false;
            $thumbName = 'thumb_'.$filename;
            $thumb = str_replace($filename, $thumbName, $filepath);
            if(file_exists($thumb)) return false;
            echo $thumb."<br>";
            $Img->open($filepath);
            $Img->thumb(540,540);
            $Img->save($thumb);
            return true;
        }
        return false;
    }

    function my_scandir($dir){
        $files=array();
        if(is_dir($dir)){
            if($handle=opendir($dir)){
                while(($file=readdir($handle))!==false){
                    if($file!='.' && $file!=".."){
                        if(is_dir($dir."/".$file)){
                            $files[$file]=$this->my_scandir($dir."/".$file);
                        }else{
                            $files[]=$dir."/".$file;  //获取文件的完全路径
                            $filesnames[]=$file;      //获取文件的文件名称
                            $this->buildThumb($file, $dir."/".$file);
                        }
                    }
                }
            }
        }
        closedir($handle);
        return $files;
        //return $filesnames;
    }

}


