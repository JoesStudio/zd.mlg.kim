<?php
namespace Asset\Controller;

use Common\Controller\MemberbaseController;

class IndexController extends MemberbaseController
{
    protected $upload_setting;
    protected $filetypes;
    protected $image_extensions;
    protected $_model;
    protected $uid;
    protected $utype;
    
    public function _initialize()
    {
        $adminid=sp_get_current_admin_id();
        $userid=sp_get_current_userid();
        if (empty($adminid) && empty($userid)) {
            exit("非法上传！");
        }

        if (!empty($adminid)) {
            $this->uid = $adminid;
            $this->utype = 1;
        } elseif (isset($_SESSION['user']['member'])) {
            $this->uid = $_SESSION['user']['member']['id'];
            $this->utype = 2;
        } else {
            $this->uid = $userid;
            $this->utype = 3;
        }

        $this->upload_setting=sp_get_upload_setting();
        
        $this->filetypes=array(
            'image'=>array('title'=>'Image files','extensions'=>$this->upload_setting['image']['extensions']),
            'video'=>array('title'=>'Video files','extensions'=>$this->upload_setting['video']['extensions']),
            'audio'=>array('title'=>'Audio files','extensions'=>$this->upload_setting['audio']['extensions']),
            'file'=>array('title'=>'Custom files','extensions'=>$this->upload_setting['file']['extensions'])
        );
        
        $this->image_extensions=explode(',', $this->upload_setting['image']['extensions']);

        $this->_model = D('Asset');
    }
    
    public function index()
    {
        $upload_setting = $this->upload_setting;
        $filetypes = $this->filetypes;
        $image_extensions = $this->image_extensions;
        
        $filetype = I('get.filetype/s', 'image');
        $mime_type=array();
        if (array_key_exists($filetype, $filetypes)) {
            $mime_type=$filetypes[$filetype];
        } else {
            $this->error('上传文件类型配置错误！');
        }
        
        $multi=I('get.multi', 0, 'intval');
        $app=I('get.app/s', '');
        $upload_max_filesize=$upload_setting[$filetype]['upload_max_filesize'];
        $this->assign('extensions', $upload_setting[$filetype]['extensions']);
        $this->assign('upload_max_filesize', $upload_max_filesize);
        $this->assign('upload_max_filesize_mb', intval($upload_max_filesize/1024));
        $this->assign('mime_type', json_encode($mime_type));
        $this->assign('multi', $multi);
        $this->assign('app', $app);

        $files = $this->_get_listimage();
        $this->assign('files', $files);

        $this->display();
    }

    private function _get_listimage()
    {
        /*$allowFiles = array( ".gif" , ".png" , ".jpg" , ".jpeg" , ".bmp" ); //文件允许格式
        $listSize = 3000; //文件大小限制，单位KB
        $path = './'. C("UPLOADPATH"); //图片路径
                       
        $allowFiles = substr(str_replace(".", "|", join("", $allowFiles)), 1);
        $files = $this->getfiles($path, $allowFiles);*/
        $files = $this->getassets($this->uid, $this->utype);

        if (!count($files)) {
            return array(
                "state" => "no match file",
                "list" => array(),
                "total" => count($files)
            );
        } else {
            return array(
                "state" => "SUCCESS",
                "list" => $files,
                "total" => count($files)
            );
        }
    }

    public function getassets($uid, $utype)
    {
        $files = D('Asset')->getAssetsNoPaged(array(
            'uid'   => $uid,
            'utype' => $utype,
            'status'=> 1,
        ));
        foreach ($files as $key=>$file) {
            $files[$key]['url'] = C("TMPL_PARSE_STRING.__UPLOAD__").$file['filepath'];
        }
        return $files;
    }

    public function getfiles($path, $allowFiles, &$files = array())
    {
        if (!is_dir($path)) return null;
        if(substr($path, strlen($path) - 1) != '/') $path .= '/';
        $handle = opendir($path);
        while (false !== ($file = readdir($handle))) {
            if ($file != '.' && $file != '..') {
                $path2 = $path . $file;
                if (is_dir($path2)) {
                    $this->getfiles($path2, $allowFiles, $files);
                } else {
                    if (preg_match("/\.(".$allowFiles.")$/i", $file)) {
                        $files[] = array(
                            'url'=> substr(SITE_PATH . $path2, strlen($_SERVER['DOCUMENT_ROOT'])),
                            'mtime'=> filemtime($path2)
                        );
                    }
                }
            }
        }
        return $files;
    }

    public function upload_post()
    {
        $upload_setting = $this->upload_setting;
        $filetypes = $this->filetypes;
        $image_extensions = $this->image_extensions;

        if (IS_POST) {
            $all_allowed_exts=array();
            foreach ($filetypes as $mfiletype) {
                array_push($all_allowed_exts, $mfiletype['extensions']);
            }
            $all_allowed_exts=implode(',', $all_allowed_exts);
            $all_allowed_exts=explode(',', $all_allowed_exts);
            $all_allowed_exts=array_unique($all_allowed_exts);
            
            $file_extension=sp_get_file_extension($_FILES['file']['name']);
            $upload_max_filesize=$upload_setting['upload_max_filesize'][$file_extension];
            $upload_max_filesize=empty($upload_max_filesize)?2097152:$upload_max_filesize;//默认2M
            
            $app=I('post.app/s','');
            if(!in_array($app, C('MODULE_ALLOW_LIST'))){
                $app='default';
            }else{
                $app= strtolower($app);
            }
                     
              
            $savepath=$app.'/'.date('Ymd').'/';
            //上传处理类
            $config=array(
                    'rootPath' => './'.C("UPLOADPATH"),
                    'savePath' => $savepath,
                    'maxSize' => $upload_max_filesize,
                    'saveName'   =>    array('uniqid',''),
                    'exts'       =>    $all_allowed_exts,
                    'autoSub'    =>    false,
            );
            $upload = new \Think\Upload($config);// 
            $info=$upload->upload();
            //开始上传
            if ($info) {
                //上传成功
                $oriName = $_FILES['file']['name'];
                
                //写入附件数据库信息
                $first=array_shift($info);
                if(!empty($first['url'])){
                    $url=$first['url'];
                    $storage_setting=sp_get_cmf_settings('storage');
                    $qiniu_setting=$storage_setting['Qiniu']['setting'];
                    $url=preg_replace('/^https/', $qiniu_setting['protocol'], $url);
                    $url=preg_replace('/^http/', $qiniu_setting['protocol'], $url);
                    
                    $preview_url=$url;
                    
                    if(in_array($file_extension, $image_extensions)){
                        if(C('FILE_UPLOAD_TYPE')=='Qiniu' && $qiniu_setting['enable_picture_protect']){
                            $preview_url = $url.$qiniu_setting['style_separator'].$qiniu_setting['styles']['thumbnail300x300'];
                            $url= $url.$qiniu_setting['style_separator'].$qiniu_setting['styles']['watermark'];
                        }
                    }else{
                        $preview_url='';
                        $url=sp_get_file_download_url($first['savepath'].$first['savename'],3600*24*365*50);//过期时间设置为50年
                    }
                }else{
                    $url=C("TMPL_PARSE_STRING.__UPLOAD__").$savepath.$first['savename'];
                    $preview_url=$url;
                }
                $filepath = $savepath.$first['savename'];
                
                /*if ($first['key'] == 'image') {
                    $cih = new \ImageHash();
                    $aHash = $cih::hashImageFile(SITE_PATH.$url);
                } else {
                    $aHash = '';
                }*/

                $meta['info'] = $first;
                //$meta['ImageHash'] = $aHash;
                $data = array(
                    'uid'   => $this->uid,
                    'utype' => $this->utype,
                    'key'   => $first['key'],
                    'filename'  => $oriName,
                    'filesize'  => $first['size'],
                    'filepath'  => $filepath,
                    'uploadtime'=> date('Y-m-d H:i:s'),
                    'status'=> 1,
                    'meta'  => json_encode($meta, 256),
                    'suffix'=> $first['ext'],
                );
                $result = $this->_model->saveAsset($data);
                                
                $this->ajaxReturn(array(
                    'aid'=>$result,
                    'preview_url'=>$preview_url,
                    'filepath'=>$filepath,
                    'url'=>$url,
                    'name'=>$oriName,
                    //'ImageHash'=>$aHash,
                    'status'=>1,
                    'message'=>'success'
                ));
            } else {
                $this->ajaxReturn(array('name'=>'','status'=>0,'message'=>$upload->getError()));
            }
        }
    }
}
