<?php
namespace Asset\Controller;

use Common\Controller\AdminbaseController;

class AssetController extends AdminbaseController
{
    protected $upload_setting;
    protected $filetypes;
    protected $image_extensions;
    protected $_model;
    protected $uid;
    protected $utype;

    public function _initialize()
    {
        parent::_initialize();
        $this->uid = sp_get_current_admin_id();
        $this->utype = 1;

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

    public function plupload()
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
        $thumb = I('get.thumb', 0, 'intval');
        $upload_max_filesize=$upload_setting[$filetype]['upload_max_filesize'];
        $this->assign('extensions', $upload_setting[$filetype]['extensions']);
        $this->assign('upload_max_filesize', $upload_max_filesize);
        $this->assign('upload_max_filesize_mb', intval($upload_max_filesize/1024));
        $this->assign('mime_type', json_encode($mime_type));
        $this->assign('multi', $multi);
        $this->assign('app', $app);
        $this->assign('thumb', $thumb);

        $files = $this->_get_listimage();
        $this->assign('files', $files);

        $this->display();
    }

    public function index()
    {
        $upload_setting = $this->upload_setting;
        $filetypes = $this->filetypes;
        $image_extensions = $this->image_extensions;

        $filetype = I('get.filetype/s','image');
        $mime_type=array();
        if(array_key_exists($filetype, $filetypes)){
            $mime_type=$filetypes[$filetype];
        }else{
            $this->error('上传文件类型配置错误！');
        }

        $multi=I('get.multi',1,'intval');
        $app=I('get.app/s','');
        $upload_max_filesize=$upload_setting[$filetype]['upload_max_filesize'];
        $this->assign('extensions',$upload_setting[$filetype]['extensions']);
        $this->assign('upload_max_filesize',$upload_max_filesize);
        $this->assign('upload_max_filesize_mb',intval($upload_max_filesize/1024));
        $this->assign('mime_type',json_encode($mime_type));
        $this->assign('multi',$multi);
        $this->assign('app',$app);

        $files = $this->_get_listimage();
        $this->assign('files', $files);

        $this->display();
    }

    public function build_thumbs(){
        $Img = new \Think\UploadImage();//实例化图片类对象
        $assets = M('Asset')->select();

        //是图像文件生成缩略图
        $thumbWidth		=	explode(',','1024,800,640,300,150,80');
        $thumbHeight		=	explode(',','1024,800,640,300,150,80');
        $thumbPrefix		=	explode(',','');
        $thumbSuffix = explode(',','_thumb-1024,_thumb-800,_thumb-640,_thumb-300,_thumb-150,_thumb-80');

        foreach($assets as $asset){
            $img_path = C("UPLOADPATH").$asset['filepath'];
            $meta = json_decode($asset['meta'], true);
            if(strpos($asset['filepath'],'ueditor') === 0) continue;

            $thumbPath    =  $meta['info']['savepath'];
            $realFilename = basename($meta['info']['savename']);
            for($i=0,$len=count($thumbWidth); $i<$len; $i++) {
                $thumbname	=	C("UPLOADPATH").$thumbPath.$thumbPrefix[$i].substr($realFilename,0,strrpos($realFilename, '.')).$thumbSuffix[$i].'.'.$meta['info']['ext'];
                if(file_exists($thumbname)) continue;
                $result = $Img->thumb($img_path,$thumbname,'',$thumbWidth[$i],$thumbHeight[$i],true);

                if($result)
                    echo "$thumbname<br>";
            }
        }
        exit('finish');
    }

    public function delete()
    {
        $ids = I('post.id');
        if (empty($ids)) {
            $this->error('请选择要删除的文件！');
        }
        $where['aid'] = array('IN', $ids);
        $where['uid'] = $this->uid;
        $where['utype'] = $this->utype;
        $ids = $this->_model->where($where)->getField('aid', true);
        if (empty($ids)) {
            $this->error(L('ERROR_REQUEST_DATA'));
        }

        $result = $this->_model->deleteAsset($ids);
        if (!empty($result)) {
            $this->ajaxReturn(array(
                'info'  => '已删除'.count($result).'个文件',
                'status'=> 1,
                'data'  => $result,
            ));
        } else {
            $this->ajaxReturn(array(
                'info'  => '未删除文件',
                'status'=> 0,
            ));
        }
    }

    private function _get_listimage()
    {
        /*$allowFiles = array( ".gif" , ".png" , ".jpg" , ".jpeg" , ".bmp" ); //文件允许格式
        $listSize = 3000; //文件大小限制，单位KB
        $path = './'. C("UPLOADPATH"); //图片路径

        $allowFiles = substr(str_replace(".", "|", join("", $allowFiles)), 1);
        $files = $this->getfiles($path, $allowFiles);*/
        $files = $this->getassets();

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

    function getassets()
    {
        $files = D('Asset')->getAssetsNoPaged(array(
            'uid'   => $this->uid,
            'utype' => $this->utype,
            'status'=> 1,
        ));
        foreach ($files as $key=>$file) {
            $url = C("TMPL_PARSE_STRING.__UPLOAD__").$file['filepath'];
            $files[$key]['url'] = $url;
            //$files[$key]['mtime'] = $url;
        }
        return $files;
    }

    function getfiles($path, $allowFiles, &$files = array())
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
            foreach ($filetypes as $mfiletype){
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
            $upload = new \Think\UploadFile();// 实例化上传类
            $upload->maxSize = 3000000 ;// 设置附件上传大小
            $upload->allowExts = array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型
            $upload->allowTypes = array('image/jpg','image/jpeg','image/pjpeg','image/png','image/gif');
            $upload->savePath = './'.C("UPLOADPATH").$savepath;// 设置附件上传目录
            $upload->saveRule = 'uniqid';

            //缩略图配置
            $upload->thumb = I('get.thumb', 0, 'intval') ? true:false;  //是否生成缩略图
            $upload->thumbMaxWidth = '1024,800,640,300,150,80';
            $upload->thumbMaxHeight = '1024,800,640,300,150,80';
            $upload->thumbPrefix = '';
            $upload->thumbSuffix = '_thumb-1024,_thumb-800,_thumb-640,_thumb-300,_thumb-150,_thumb-80';
            $upload->thumbRemoveOrigin = false;

            //开始上传
            if ($upload->upload()) {
                $info = $upload->getUploadFileInfo();
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
                $thumbpath = $savepath.$upload->thumbPrefix.$first['savename'];

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
                    'utype' => 1,
                    'key'   => $first['key'],
                    'filename'  => $oriName,
                    'filesize'  => $first['size'],
                    'filepath'  => $filepath,
                    'uploadtime'=> date('Y-m-d H:i:s'),
                    'status'=> 1,
                    'meta'  => json_encode($meta, 256),
                    'suffix'=> $first['ext'],
                    'thumb' => $thumbpath,
                );
                $result = $this->_model->saveAsset($data);

                $this->ajaxReturn(array(
                    'aid'=>$result,
                    'preview_url'=>$preview_url,
                    'filepath'=>$filepath,
                    'thumb' => $thumbpath,
                    'url'=>$url,
                    'name'=>$oriName,
                    //'ImageHash'=>$aHash,
                    //'info'=> $first,
                    'status'=>1,
                    'message'=>'success'
                ));
            } else {
                $this->ajaxReturn(array('name'=>'','status'=>0,'message'=>$upload->getError()));
            }
        }
    }
}
