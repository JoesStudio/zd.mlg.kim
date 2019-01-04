<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-12-22
 * Time: 16:59
 */

namespace Tf\Controller;


use Common\Controller\SupplierbaseController;
use Tf\Service\TfUnionService;
use Think\Upload;
use Wx\Common\Wechat;
use Org\Net\Http;

class SupplierController extends SupplierbaseController
{

    protected $_model;
    protected $tfUnionService;

    function __construct()
    {
        parent::__construct();
        $this->_model = D("Tf/Tf");
        $this->tf_terms_model = D('Tf/Term');
        $this->tf_term_rid_model = D('Tf/TermTf');
        $this->assign('tf_terms', $this->tf_terms_model->terms());
        $this->tfUnionService = new TfUnionService();
    }

    public function test() {
        $this->importOne("5aba4fa2bbaa8/changhengbuye");
    }

    public function upload_zip()
    {
        $upload = new \Think\Upload();
        $upload->maxSize = 10485760000;   // 设置附件上传大小 10M
        $upload->rootPath = "data";
        $upload->savePath = "/upload/importzip/";
        $upload->autoSub = false;
        $upload->exts = array (
            'rar',
            'zip'
        ); // 设置附件上传后缀

        $info = $upload->upload();

        if ($info) {
            $info[0] = $info['file'];
            $info[0]['savepath'] = $upload->rootPath . $info[0]['savepath'];

            $zip = new \PclZip($info[0]['savepath'] . $info[0]['savename']);

            if (($list = $zip->listContent()) == 0) {
                die("Error : ".$zip->errorInfo(true));
            }

            $filename = explode(".", $info[0]['savename']);
            $filename = array_slice($filename, 0, count($filename) - 1);

            $filename = implode("", $filename);
            if (!is_dir("/data/upload/importzip/" . $filename)) {
                mkdir("/data/upload/importzip/" . $filename, 0777);
            }

            $toDir = 'data/upload/importzip/' . $filename . "/";

            $zip->extract($toDir);//解压

            $this->importOne($filename);
            @unlink($info[0]['savepath'] . $info[0]['savename']);

            $this->ajaxReturn(array(
                "code" => "200",
                "message" => "文件上传成功 正在处理中请勿关闭页面",
                "path" => $info[0]['savepath'] . $info[0]['savename']
            ));
        } else {
            $this->ajaxReturn(array (
                "code" => "400",
                "message" => $upload->getError()
            ));
        }
    }

    public function get_biz_info(){
        $user = session('user');
        $bizOperator = M('BizOperator')->where(array('user_id'=>$user['id']))->select();
        $biz_info = D('BizMember')->getMember($bizOperator[0]['member_id']);
        $res['id'] = $biz_info['id'];
        $res['biz_name'] = $biz_info['biz_name'];
        $res['biz_address'] = $biz_info['contact']['contact_province_name'].$biz_info['contact']['contact_city_name'].$biz_info['contact']['contact_district_name'].$biz_info['contact']['contact_address'];
        $res['biz_tel'] = $biz_info['contact']['contact_tel'].'或者'.$biz_info['contact']['contact_mobile'];
        return $res;
    }

    //自动生成面料编号
    public function auto_ft_num(){
        $biz_info = $this->get_biz_info();
        $num = rand(1,9).rand(1,9).rand(1,9).rand(1,9).rand(1,9).'#';
        $tmp = M('TextileFabric')->where(array('vend_id'=>$biz_info['id'],'name'=>$num))->select();
            while(!tmp){
                $num = rand(1,9).rand(1,9).rand(1,9).rand(1,9).rand(1,9).'#';
            }
            return $num;
    }

    public function upload_html_new(){

        if (sp_is_mobile()) {
            $wechat = new Wechat();

            $signPackage = $wechat->getSignPackage();

            $image = new \Think\Image();

            //获取面料商信息
            $biz_info = $this->get_biz_info();
            //自动生成自编号
            $biz_num = $this->auto_ft_num();

            $this->assign('biz_num',$biz_num);
            $this->assign('biz_info',$biz_info);
            $this->assign('signPackage',json_encode($signPackage));
        }else{
            $this->error('请使用微信浏览器访问！');
        }

        $this->display();
    }

    public function tf_list_new(){

        $list = M('TextileFabric')->where(array('material_id'=>999))->select();

        foreach($list as $key => $row){


            $row['img'] = str_replace("&quot;", '"', $row['img']);
            $row['img'] = str_replace("'", '"', $row['img']);

            $row['img'] = json_decode($row['img'],true);
            $list[$key]['img'] = $row['img'];
        }

        $this->assign('list',$list);

        $this->display();
    }

    public function tf_content_new(){

        $tf_id = $_GET['id'];


        $list = M('TextileFabric')->where(array('id'=>$tf_id))->select();

            $list[0]['img'] = str_replace("&quot;", '"', $list[0]['img']);
            $list[0]['img'] = str_replace("'", '"', $list[0]['img']);

            $list[0]['img'] = json_decode($list[0]['img'],true);
            $img = $list[0]['img']['photo'];

            $this->assign('img',$img);

            $list = M('TextileFabricSku')->where(array('tf_id'=>$tf_id))->select();

            $this->assign('list',$list);

        $this->display();
    }

    public function add_tf_new(){
        $_POST['cid'] = 107;
        $images = $_POST['images'];
        $_POST['describe'] = serialize($_POST['images']);
        unset($_POST['images']);
        $_POST['code'] = $_POST['name'];
        $_POST['status'] = 1;
        $_POST['material_id'] = 999;

        $dd = D("TextileFabric")->add($_POST);


        foreach($images as $value) {


            $ddd = D("TextileFabricSku")->add(array(
                "tf_id" => $dd,
                "value_text" => $value,
            ));

        }

        echo json_encode($dd);exit;
    }

    public function set_master_img(){

        $wechat = new Wechat();

        $media_id = $_POST['serverId'];

        $wx_image_url = $wechat->api->get_media($media_id);

        $local_url = 'data/upload/wx_server/'.time().'.jpg';

        Http::curlDownload($wx_image_url,$local_url);
        $res = json_encode(array("photo" => $local_url));
        echo json_encode($res);exit;

    }

    public function get_wx_server_image(){

        $wechat = new Wechat();

        $media_id = $_POST['serverId'];

        $wx_image_url = $wechat->api->get_media($media_id);

        $local_url = 'data/upload/wx_server/'.time().'.jpg';

        Http::curlDownload($wx_image_url,$local_url);

        $HEX = $this->get_HEX($local_url);
        echo json_encode($HEX);exit;
    }

    public function get_HEX($image_url){

        $i = imagecreatefromjpeg($image_url); //图片路径

        $count = 0;
        $key = 0;
        $rr = 0;
        $gg = 0;
        $bb = 0;

        for ($x = 0; $x < imagesx($i); $x++) {
            for ($y = 0; $y < imagesy($i); $y++) {
                $rgb = imagecolorat($i, $x, $y);
                $r   = ($rgb >> 16) & 0xFF;
                $g   = ($rgb >> 8) & 0xFF;
                $b   = $rgb & 0xFF;
                $key ++;
                $rr += (int)$r;
                $gg += (int)$g;
                $bb += (int)$b;
            }

        }

        $mr = round($rr/$key);
        $mg = round($gg/$key);
        $mb = round($bb/$key);

        return $this->rgb2html($mr,$mg,$mb);
    }

    function rgb2html($r, $g=-1, $b=-1)
    {
        if (is_array($r) && sizeof($r) == 3)
            list($r, $g, $b) = $r;
        $r = intval($r); $g = intval($g);
        $b = intval($b);
        $r = dechex($r<0?0:($r>255?255:$r));
        $g = dechex($g<0?0:($g>255?255:$g));
        $b = dechex($b<0?0:($b>255?255:$b));
        $color = (strlen($r) < 2?'0':'').$r;
        $color .= (strlen($g) < 2?'0':'').$g;
        $color .= (strlen($b) < 2?'0':'').$b;
        return '#'.$color;
    }

    public function upload_do_new(){
        //获取底图
        //获取色值且进行排序处理
        //自动生成对应的图片，并且以店铺ID+款式ID+色值进行命名+jpg后缀
        //设置存放图片的目录
        //录入数据库
        //反馈前端
    }

    public function upload_all_zip()
    {
        $upload = new \Think\Upload();
        $upload->maxSize = 10485760000;   // 设置附件上传大小 10M
        $upload->rootPath = "data";
        $upload->savePath = "/upload/importzip/";
        $upload->autoSub = false;
        $upload->exts = array (
            'rar',
            'zip'
        ); // 设置附件上传后缀

        $info = $upload->upload();

        if ($info) {
            $info[0] = $info['file'];
            $info[0]['savepath'] = $upload->rootPath . $info[0]['savepath'];

            $zip = new \PclZip($info[0]['savepath'] . $info[0]['savename']);

            if (($list = $zip->listContent()) == 0) {
                die("Error : ".$zip->errorInfo(true));
            }

            $filename = explode(".", $info[0]['savename']);
            $filename = array_slice($filename, 0, count($filename) - 1);

            $filename = implode("", $filename);
            if (!is_dir("/data/upload/importzip/" . $filename)) {
                mkdir("/data/upload/importzip/" . $filename, 0777);
            }

            $toDir = 'data/upload/importzip/' . $filename . "/";

            $zip->extract($toDir);//解压

            //批量处理
            $tmpHanler = opendir($toDir);
            $tmpXlsName = "";
            //获取excel文件
            while($file = readdir($tmpHanler)) {
                if (preg_match("/(xlsx|xls)$/", $file) > 0) {
                    $tmpXlsName = $file;
                    break;
                }
            }
            closedir($tmpHanler);
            if (!$tmpXlsName) {
                exit("xls文件为空");
            }

            require_once ("simplewind/Lib/Util/Pinyin.class.php");
            $py = new \PinYin();

            //文件夹批量处理
            $tmpHanler = opendir($toDir);
            while($file = readdir($tmpHanler)) {
                if (is_dir($toDir . "/" . $file) && $file != ".." && $file != ".") {
                    $newFileName = $py->getAllPY(iconv("GB2312", "UTF-8//IGNORE", $file));
                    rename($toDir . $file, $toDir . $newFileName);

                    copy($toDir . $tmpXlsName, $toDir . $newFileName . "/" . $tmpXlsName);
                    $this->importOne($filename . "/" . $newFileName);
                }
            }
            closedir($tmpHanler);

            @unlink($info[0]['savepath'] . $info[0]['savename']);

            $this->ajaxReturn(array(
                "code" => "200",
                "message" => "文件上传成功 正在处理中请勿关闭页面",
                "path" => $info[0]['savepath'] . $info[0]['savename']
            ));
        } else {
            $this->ajaxReturn(array (
                "code" => "400",
                "message" => $upload->getError()
            ));
        }
    }

    public function importOne($fileDirName) {
//    public function importOne() {
//        $fileDirName = "5ab8ca0b933c1";
//        $fileDirName = "5aba393c7caeb";

        require_once ("simplewind/Lib/Util/Pinyin.class.php");
        $py = new \PinYin();

        set_time_limit(0);
        Vendor("PHPExcel.PHPExcel");
        Vendor("PHPExcel.PHPExcel.IOFactory");

        $basePath = "data/upload/importzip/" . $fileDirName . "/";

        $tmpXlsName = "";
        $tmpHanler = opendir($basePath);
        while($file = readdir($tmpHanler)) {
            if (preg_match("/(xlsx|xls)$/", $file) > 0) {
                $tmpXlsName = $file;
            }

            if (is_dir($basePath . $file) && $file != ".." && $file != ".") {
                $newName = strtr($file, "#", "_");
                rename($basePath . $file, $basePath . $newName);
            }
        }
        closedir($tmpHanler);

        if ($tmpXlsName == "") {
            return exit("不存在xls文件");
        }

        $objPHPExcel = \PHPExcel_IOFactory::load($basePath . $tmpXlsName);

        foreach ($objPHPExcel->getWorksheetIterator() as $worksheet) {
            $worksheets[] = $worksheet->toArray();
        }

        $importBizNameCache = array();
        $bizData = "";
        $lastBizName = "";

        foreach($worksheets[0] as $key => $tf) {

            $code = $tf[1];
            $bizName = $tf[0];

            $tmpCode = strtr($code, "#", "_");

            if ($tf[0] == "") {
                $tf[0] = $lastBizName;
            }

            if ($key > 0 && $tf[0] != "" && $tmpCode && is_dir($basePath . $tmpCode)) {

                $lastBizName = $tf[0];

                if ($importBizNameCache['bizName'] != $bizName && $bizName != "") {
                    $bizData = D("BizMember")->where("biz_name = '%s'", trim($bizName))->find();
                    $importBizNameCache = $bizData;
                }

                if ($bizData['biz_name'] == "") {
                    D("BizMember")->add(array(
                        "biz_name" => $bizName,
                        "biz_status" => 1,
                        "create_date" => date("Y-m-d H:i:s", time())
                    ));

                    $bizData = D("BizMember")->where("biz_name = '%s'", trim($bizName))->find();
                    $importBizNameCache = $bizData;
                }

//                echo "======================================START\n";

                //关键数据
                $txtExsitsFlag = 0;
                $detailImgHTML = "";
                $imageArr = array();


                //读取首层目录的txt文件
                $tmpHanler = opendir($basePath);
                while(($file2 = readdir($tmpHanler)) && $txtExsitsFlag == 0) {
                    if ($code . ".txt" == $file2) {
                        $txtExsitsFlag = 1;
                        $txtValue = array();
                        $txtContent = explode("\n", file_get_contents($basePath . $code . ".txt"));
                        foreach ($txtContent as $value) {

                            if(!$value){
                                continue;
                            }

                            $txtValue[] = array(
                                "color" => substr($value, 0, 7),
                                "sku" => 1000
                            );
                        }

                        if (count($txtValue) == 0) {
                            exit("txt文件格式检查失败");
                        }

                        break;
                    }
                }
                closedir($tmpHanler);

                if (!$txtExsitsFlag) {
                    continue;
                }

                $dirHandle = opendir($basePath . $tmpCode);
                while($file = readdir($dirHandle)) {

                    //遍历布料信息文件夹内部
                    if ($file != '.' && $file != '..' && !preg_match("/__MACOS__/", $file)) {

                        $fileName_arr_tmp = basename($file);



                            $arr_tmp = explode('.',$fileName_arr_tmp);
                            $fileName_arr[(int)$arr_tmp[0]] = $fileName_arr_tmp;


                    }
                }
                unset($fileName_arr[0]);

                //照片按照文件名排序
                ksort($fileName_arr);

                foreach($fileName_arr as $fileName){



                    if (preg_match("/(png|jpg|jpeg|bmp)/", $fileName) &&  !strpos($fileName, "thumb")) {
                        //图片文件
                        $tmp = explode(".", $fileName);
                        $tmp2 = explode("_", $tmp[0]);

                        if (count($tmp2) == 2) {
                            if (!is_array($imageArr[$tmp2[0]])) {
                                $imageArr[$tmp2[0]] = array();
                            }

                            $imageArr[$tmp2[0]][] = $basePath . $tmpCode . "/" . $fileName;
                        } else if ($tmp[0]) {
                            if (!is_array($imageArr[$tmp[0]])) {
                                $imageArr[$tmp[0]] = array();
                            }

                            $imageArr[$tmp[0]][] = $basePath . $tmpCode . "/" . $fileName;
                        }
                    }
                }

                if (is_dir($basePath . $tmpCode . "/detail")) {
                    $dirDetailHandle = opendir($basePath . $tmpCode . "/detail");

                    while($file = readdir($dirDetailHandle)) {
                        //遍历布料信息文件夹内部
                        if ($file != '.' && $file != '..' && !preg_match("/__MACOS__/", $file)) {

                            if(preg_match("/[\x7f-\xff]/", $file)){

                                $newFileName = $py->getAllPY(iconv("GB2312", "UTF-8//IGNORE", $file));
                                rename($basePath . $tmpCode . "/detail" . '/' . $file, $basePath . $tmpCode . "/detail" . '/' . $newFileName);

                                $fileName = basename($newFileName);

                            }else{
                                $fileName = basename($file);
                            }

                            if (preg_match("/(png|jpg|jpeg|bmp)/", $fileName) && !strpos($fileName, "thumb")) {
                                //图片文件
                                $imagePath = get_thumb_url('/' . $basePath . $tmpCode . "/detail/" . $fileName, true, 600);
                                $detailImgHTML .= "<img src='" . $imagePath . "' class='detail-img' />";
                            }
                        }
                    }
                }

                closedir($dirHandle);

                if (!$txtExsitsFlag) {
                    continue;
                }
echo '<pre>';print_r($bizData);
                $data = D('TextileFabric')->where("code = '%s' and vend_id = %d", $code, $bizData['id'])->find();

                //update 数据存在
                if ($data) {

                    $tfId = $data['id'];

                    //主要数据更新

                    $postData = array();
                    $postData['material'] = $tf[10];
                    $postData['width'] = $tf[3];
                    $postData['weight'] = $tf[4];
                    $postData['describe'] = $detailImgHTML;
                    $postData['component'] = $tf[10];
                    $postData['price'] = $tf[5];
                    $postData['vend_id'] = $bizData['id'];
                    $postData['modify_date'] = date("Y-m-d H:i:s");
                    $postData['updated_at'] = date("Y-m-d H:i:s");
                    $postData['name'] = $tf[2];
                    if ($postData['name'] == "") {
                        $postData['name'] = $tf[1];
                    }

                    $postData['kim_located'] = $tf[15] ? : '';
                    $postData['colorboard_located'] = $tf[16] ? : '';
                    $postData['fans_level'] = 3;
                    $postData['ispublic'] = 1;


                    $rand = mt_rand(0, count($txtValue) - 1);
                    $postData['img'] = json_encode(array("photo" => $imageArr[array_keys($imageArr)[$rand]][0]));

                    $tmp = D("TextileFabricMaterial")->where("name = '%s'", $tf[17])->find();
                    $postData['material_id'] = intval($tmp['id']);

                    $tmp = D("TextileFabricMaterial")->where("name = '%s'", $tf[18])->find();
                    $postData['sub_material_id'] = intval($tmp['id']);

                    $tmp = D("TextileFabricWeave")->where("name = '%s'", $tf[11])->find();
                    $postData['weave_id'] = intval($tmp['id']);

                    $tmp = D("TextileFabricCraft")->where("name = '%s'", $tf[12])->find();
                    $postData['craft_id'] = intval($tmp['id']);

                    $tmp = D("TextileFabricYarn")->where("name = '%s'", $tf[13])->find();
                    $postData['yarn_id'] = intval($tmp['id']);

                    $tmp = D("TextileFabricVisual")->where("name = '%s'", $tf[14])->find();
                    $postData['visual_id'] = intval($tmp['id']);

                    D('TextileFabric')->where("id = %d", $data['id'])->save($postData);

                    //库存更新
                    D("TextileFabricSku")->where("tf_id = %d", $tfId)->delete();
                    $key = 0;

                    foreach($txtValue as  $value) {

                        if ($value) {

                            $dd = D("TextileFabricSku")->add(array(
                                "tf_id" => $tfId,
                                "value_text" => $value['color'],
                                "sku_price" => $tf[5],
                                "sku_num" => intval($value['sku']),
                                "sku_unit" => 1,
                                "sample_enabled" => 1,
                                "large_price" => 200,
                                "large_unit" => "米",
                                "min_charge" => intval($tf[8]),
                                "color_code" => $value['color'],
                                "group_price" => 200,
                                "group_unit" => "米",
                                "group_enable" => "米",
                                "max_buy" => 200,
                                "sku_type" => 1,
                                "dress_img" => json_encode($imageArr[array_keys($imageArr)[$key]])
                            ));

                        }

                        $key++;
                    }
                } else {

                    //新增
                    //主要数据更新
                    $postData['material'] = $tf[10];
                    $postData['create_date'] = date("Y-m-d H:i:s");
                    $postData['created_at'] = date("Y-m-d H:i:s");
                    $postData['updated_at'] = date("Y-m-d H:i:s");
                    $postData['modify_date'] = date("Y-m-d H:i:s");
                    $postData['width'] = $tf[3];
                    $postData['code'] = $code;
                    $postData['price'] = $tf[5];
                    $postData['cid'] = "107";
                    $postData['vend_id'] = $bizData['id'];
                    $postData['status'] = 1;
                    $postData['weight'] = $tf[4];
                    $postData['describe'] = $detailImgHTML;

                    $postData['component'] = $tf[10];

                    $postData['name'] = $tf[2];
                    if ($postData['name'] == "") {
                        $postData['name'] = $tf[1];
                    }

                    $postData['kim_located'] = $tf[15] ? : '';
                    $postData['colorboard_located'] = $tf[16] ? : '';
                    $postData['fans_level'] = 3;
                    $postData['ispublic'] = 1;

                    $rand = mt_rand(0, count($txtValue) - 1);
                    $postData['img'] = json_encode(array("photo" => $imageArr[array_keys($imageArr)[$rand]][0]));

                    $tmp = D("TextileFabricMaterial")->where("name = '%s'", $tf[17])->find();
                    $postData['material_id'] = intval($tmp['id']);

                    $tmp = D("TextileFabricMaterial")->where("name = '%s'", $tf[18])->find();
                    $postData['sub_material_id'] = intval($tmp['id']);

                    $tmp = D("TextileFabricWeave")->where("name = '%s'", $tf[11])->find();
                    $postData['weave_id'] = intval($tmp['id']);

                    $tmp = D("TextileFabricCraft")->where("name = '%s'", $tf[12])->find();
                    $postData['craft_id'] = intval($tmp['id']);

                    $tmp = D("TextileFabricYarn")->where("name = '%s'", $tf[13])->find();
                    $postData['yarn_id'] = intval($tmp['id']);

                    $tmp = D("TextileFabricVisual")->where("name = '%s'", $tf[14])->find();
                    $postData['visual_id'] = intval($tmp['id']);

                    $tfId = D('TextileFabric')->where("id = %d", $data['id'])->add($postData);

                    $key = 0;

                    //库存更新
                    foreach($txtValue as $value) {

                        if ($value) {
                            $data = D("TextileFabricSku")->add(array(
                                "tf_id" => $tfId,
                                "value_text" => $value['color'],
                                "sku_price" => intval($tf[5]),
                                "sku_num" => intval($value['sku']),
                                "sku_unit" => 1,
                                "sample_enabled" => 1,
                                "large_price" => 200,
                                "large_unit" => "米",
                                "min_charge" => intval($tf[8]),
                                "color_code" => $value['color'],
                                "group_price" => 200,
                                "group_unit" => "米",
                                "group_enable" => "米",
                                "max_buy" => 200,
                                "sku_type" => 1,
                                "dress_img" => json_encode($imageArr[array_keys($imageArr)[$key]])
                            ));
                        }
                        $key++;
                    }

                }

                $count = M('TextileFabricShelves')->where("tf_id=" . $tfId)->count();
                if($count > 0){
                    $result = M('TextileFabricShelves')->where("tf_id=" . $tfId)->setField('on_sale', 1);
                }else{
                    $result = M('TextileFabricShelves')->add(array(
                        'tf_id'=> $tfId,
                        'on_sale'=>1
                    ));
                }
            }

        }
    }

    public function index()
    {
        if(sp_is_mobile() && !IS_AJAX){
            $this->display();
            exit();
        }
        $where = array();
        $where['source'] = 1;


        if (isset($_REQUEST['on_sale'])) {
            $_REQUEST['filter']['on_sale'] = $_REQUEST['on_sale'];
        }

        if (isset($_REQUEST['filter'])) {
            $filter = I('request.filter');
            if (isset($filter['on_sale']) && $filter['on_sale'] !== '') {
                $where['on_sale'] = $filter['on_sale'];
            }
            if (!empty($filter['keywords'])) {
                $where['name|code|component'] = array('LIKE', '%' . $filter['keywords'] . '%');
            }
            if (!empty($filter['datestart']) && !empty($filter['datefinish'])) {
                $where['UNIX_TIMESTAMP(create_date)'] = array('between', strtotime($filter['datestart']) . ',' . strtotime($filter['datefinish']));
            } elseif (!empty($filter['datestart']) && empty($filter['datefinish'])) {
                $where['UNIX_TIMESTAMP(create_date)'] = array('egt', strtotime($filter['datestart']));
            } elseif (empty($filter['datestart']) && !empty($filter['datefinish'])) {
                $where['UNIX_TIMESTAMP(create_date)'] = array('elt', strtotime($filter['datefinish']));
            }
            $this->assign('filter', $filter);
        }

        $where['vend_id'] = $this->memberid;
        $result = $this->tfUnionService->getRowsPaged($where, 10);
        //$result = $this->_model->getTfPaged($where, 10);
        $this->assign("list", $result);

        if(sp_is_mobile() && IS_AJAX){
            $html = $this->fetch('more');
            $result['html'] = $html;
            unset($result['data']);
            $this->ajaxReturn($result);
        }else{


            $cats_model = D("Portal/TextileFabricCats");

            $tree = new \Tree();
            $tree->icon = array('&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─ ', '&nbsp;&nbsp;&nbsp;└─ ');
            $tree->nbsp = '&nbsp;&nbsp;&nbsp;';
            $terms = $cats_model->order(array("listorder" => "asc"))->select();

            $new_terms = array();
            foreach ($terms as $r) {
                //$r['id']=$r['id'];
                $r['parentid'] = $r['pid'];
                $r['selected'] = (!empty($parentid) && $r['id'] == $parentid) ? "selected" : "";
                $new_terms[] = $r;
            }
            $tree->init($new_terms);
            $tree_tpl = "<option value='\$id' \$selected>\$spacer\$title</option>";
            $tree = $tree->get_tree(0, $tree_tpl);

            $this->assign("cats_tree", $tree);
            $this->display();
        }
    }

    //面料名称代码ajax查询
    function cid_ajax()
    {
        $cid = $_GET['cid'];
        $data['data'] = M("TextileFabricName")->where("cid=$cid")->select();
        $this->ajaxReturn($data);
    }

    function add()
    {
        $vend_id = $this->memberid;
        $cid = I('get.cid');
        $name_code = I("get.name_code");

        if (empty($cid)) {
            $this->error("请先选择面料分类！");
        }

//        if (empty($name_code)) {
//            $this->error("请先选择面料名称！");
//        }

        if (IS_AJAX) {
            $this->success('', U('add', array('cid' => $cid, 'name_code' => $name_code)));
        }

        $supplier = $this->member;
        $this->assign('supplier', $supplier);

        $cdata = D("TextileFabricCats")->where(array("id" => $cid))->find();
        $this->assign('cat', $cdata);
        $this->assign("cid", $cid);
        $this->assign("cat_name", $cdata["title"]);
        //\\\
        //所属名称
        $cdata = D("Portal/TextileFabricName")->where(array("code" => $name_code))->find();
        $this->assign("name_code", $name_code);
        $this->assign("code_name", $cdata["cname"]);
        //\\\
        //获取所属分类扩展属性
        $cdata = M("CatsPropDef")
            ->alias("a")
            ->join("__PROPERTY__ b ON a.key_id=b.id")//
            ->join("__TEXTILE_FABRIC_PROP__ c ON a.key_id=c.key_id and c.tf_id=''", 'LEFT')//
            ->where(array("cid" => $cid, "is_sale" => 0))//非销售属性
            //->order("id DESC")
            ->field('a.*,b.key_name,c.id as tfp_id,c.tf_id,c.value_id,c.value_text')
            //->limit($page->firstRow . ',' . $page->listRows)
            ->select();

        foreach ($cdata as $k => $v) {
            $ids = join(",", array_filter(explode(",", $v["value_id_range"])));
            //echo "ids=".$ids."<br>\n";
            if ($ids == "") {
                $where = array("key_id" => $v["key_id"]);
            } else {
                $where = array("key_id" => $v["key_id"], "id" => array("in", $ids));
            }
            $vdata = M("PropertyValue")->where($where)->select();

            $cdata[$k]["value"] = $vdata;
        }
        $this->assign("ex_list", $cdata);

        /*面料多样式分类*/

        $tf_terms_model = M("Tf_terms");
        $tf_terms = $tf_terms_model->order(array('listorder' => "asc"))->select();
        $this->assign("tf_terms", $tf_terms);


        //供应商列表
        $vend_list = D('BizMember')->field('id,biz_name')->select();
        $this->assign("vend_list", $vend_list);
        //\\\
        $this->display();
    }

    //保存面料分类
    function add_post()
    {
        if (IS_POST) {
            $nameCode = I('post.name_code','');
            $cid = I('post.cid/d',0);
            $tfcodeprefix = M('TextileFabricName')->field("CONCAT('{$this->memberid}',cat_code,code) as prefix")
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

            $_POST['vend_id'] = $this->memberid;
            if (!empty($_POST['photos_alt']) && !empty($_POST['photos_url'])) {
                foreach ($_POST['photos_url'] as $key => $url) {
                    $photourl = $url;    //
                    $img['photo'][] = array(
                        "url" => $photourl,
                        "alt" => $_POST['photos_alt'][$key],
                        "dress_status" => $_POST['photos_dress'][$key],
                    );
                }
            }
            $img['thumb'] = $_POST['smeta']['thumb']; //
            if ($_POST['smeta']['thumb'] == '') {
                $img['dress_thumb'] = 0;
            } else {
                $img['dress_thumb'] = $_POST['smeta']['dress_thumb']; //
            }

            $cih = new \ImageHash();
            $aHash = $cih::hashImageFile(sp_get_image_preview_url($img['thumb']));
            //$img['thumbHash'] = $aHash;
            $_POST['img_hash'] = $aHash;

            $_POST['img'] = json_encode($img);                                //主图 相册图集
            $_POST['describe'] = htmlspecialchars_decode($_POST['describe']); //面料描述
            //$_POST['describe']=sp_get_image_preview_url($img['thumb']);
            //$_POST['describe']=mysql_real_escape_string(json_encode($img));

            $_POST['cat_code'] = D('Tf/Cat')->where(array('id' => I('post.cid/d', 0)))->getField('code');

            // print_r($_POST);
            $result = $this->_model->addTf($_POST);
            if ($result !== false) {
                F('all_tf', null);

                /*面料多样性分类*/
                foreach ($_POST['term'] as $mterm_id) {
                    $this->tf_term_rid_model->add(array("tf_term_id" => intval($mterm_id), "tf_id" => $result));
                }

                $tf_id = $this->_model->getLastInsID();

                /*$sku_model = D('Tf/Sku');
                $skuData = $sku_model->formatData($_POST['sku'],$tf_id);
                $sku_model->SaveToSku($tf_id, $skuData);*/
                $prop_model = D('Tf/Prop');
                $prop_model->SaveToProp($tf_id, $_POST);

                $this->success("添加成功！", U("edit", array('id' => $result)));
            } else {
                $this->error("添加失败！" . $this->_model->getError());
            }
        }
    }

    function edit($id)
    {
        $where['tf_id'] = $id;
        $where['vend_id'] = $this->memberid;
        $data = $this->_model->getTf(array('id' => $id, 'vend_id' => $this->memberid));

        // var_dump($data);

        $this->assign("smeta", $data['img']);


        $cdata = D("Portal/TextileFabricCats")->where(array("id" => $data['cid']))->find();
        $this->assign('cat', $cdata);
        $this->assign("cid", $cid);
        $this->assign("cat_name", $cdata["title"]);
        //\\\
        //所属名称
        $cdata = D("Portal/TextileFabricName")->where(array("code" => $data['name_code']))->find();
        $this->assign("name_code", $name_code);
        $this->assign("code_name", $cdata["cname"]);

        //面料多样性分类
        $rids = $this->tf_term_rid_model->where(array("tf_id" => $id, "status" => 1))->getField("tf_term_id", true);
        $this->assign('rids', $rids);

        //获取所属分类扩展属性
        $cdata = M("CatsPropDef")
            ->alias("a")
            ->join("__PROPERTY__ b ON a.key_id=b.id")//
            ->join("__TEXTILE_FABRIC_PROP__ c ON a.key_id=c.key_id and c.tf_id=$id", 'LEFT')//
            ->where(array("cid" => $data['cid'], "is_sale" => 0))//非销售属性
            //->order("id DESC")
            ->field('a.*,b.key_name,c.id as tfp_id,c.tf_id,c.value_id,c.value_text')
            ->select();

        foreach ($cdata as $k => $v) {
            $ids = join(",", array_filter(explode(",", $v["value_id_range"])));
            if ($ids == "") {
                $where = array("key_id" => $v["key_id"]);
            } else {
                $where = array("key_id" => $v["key_id"], "id" => array("in", $ids));
            }
            $vdata = M("PropertyValue")->where($where)->select();

            $cdata[$k]["value"] = $vdata;
        }
        $this->assign("ex_list", $cdata);
        //\\\获取所属分类扩展属性

        //    SKU
        $eWhere = array("tf_id" => $id);
        $sku_list = M("TextileFabricSku")->where($eWhere)->order('key_name')->select();
        foreach ($sku_list as $k => $v) {
            if ($k > 0 && $v["key_name"] == $sku_list[$k - 1]["key_name"]) {
                $v["is_sub"] = 1;
            } else {
                $v["is_sub"] = 0;
            }

            $sku_list[$k] = $v;
        }
        //print_r($sku_list);
        $this->assign("sku_list", $sku_list);
        //\\\ SKU

        $this->assign("id", $id);

        $this->assign("data", $data);

        if (sp_is_mobile()) {
            $wx = new \Wx\Common\Wechat();
            $this->assign('jsApiParams', json_encode($wx->getSignPackage(), 256));
        }

        $this->display();
    }

    function edit_post()
    {
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

            if (!empty($_POST['photos_alt']) && !empty($_POST['photos_url'])) {
                foreach ($_POST['photos_url'] as $key => $url) {
                    $photourl = $url;    //
                    $img['photo'][] = array(
                        "url" => $photourl,
                        "alt" => $_POST['photos_alt'][$key],
                        "dress_status" => $_POST['photos_dress'][$key],
                    );
                }
            }
            $img['thumb'] = $_POST['smeta']['thumb']; //
            if ($_POST['smeta']['thumb'] == '') {
                $img['dress_thumb'] = 0;
            } else {
                $img['dress_thumb'] = $_POST['smeta']['dress_thumb']; //
            }

            #$cih = new \ImageHash();
            #$aHash = $cih::hashImageFile(sp_get_image_preview_url($img['thumb']));
            //$img['thumbHash'] = $aHash;
            #$_POST['img_hash'] = $aHash;

            #$_POST['img'] = json_encode($img);                                //主图 相册图集
            $_POST['describe'] = htmlspecialchars_decode($_POST['describe']); //面料描述
            //$_POST['describe']=sp_get_image_preview_url($img['thumb']);
            //$_POST['describe']=mysql_real_escape_string(json_encode($img));

            //print_r($_POST);
            unset($_POST['vend_id']);
            $result = $this->_model->updateTf($_POST);
            if ($result !== false) {
                F('all_tf', null);
                $tf_id = $_POST['id'];

                /*面料多样性分类*/
                if (empty($_POST['term'])) {
                    $this->tf_term_rid_model->where(array("tf_id" => $tf_id))->delete();
                } else {
                    $this->tf_term_rid_model->where(array("tf_id" => $tf_id, "tf_term_id" => array("not in", implode(",", $_POST['term']))))->delete();
                    foreach ($_POST['term'] as $mterm_id) {
                        $find_term_relationship = $this->tf_term_rid_model->where(array("tf_id" => $tf_id, "tf_term_id" => $mterm_id))->count();
                        if (empty($find_term_relationship)) {
                            $this->tf_term_rid_model->add(array("tf_term_id" => intval($mterm_id), "tf_id" => $tf_id));
                        } else {
                            $this->tf_term_rid_model->where(array("tf_id" => $tf_id, "tf_term_id" => $mterm_id))->save(array("status" => 1));
                        }
                    }
                }

                /*$sku_model = D('Tf/Sku');
                $skuData = $sku_model->formatData($_POST['sku'],$tf_id);
                $sku_model->SaveToSku($tf_id, $skuData);*/
                $prop_model = D('Tf/Prop');
                $prop_model->SaveToProp($tf_id, $_POST);

                $this->success("修改成功！");
            } else {
                $this->error("修改失败！" . $this->_model->getError());
            }
        }
    }

    function delete()
    {
        if (IS_POST) {
            $id = I('post.id');
            $fabric = $this->_model->where(array('id' => $id))->find();
            if ($fabric) {
                $result = $this->_model->where(array('id' => $id))->delete();
                if ($result !== false) {
                    $this->ajaxReturn(array(
                        'status' => 1,
                        'info' => '面料已删除！',
                        'data' => array('id' => $id),
                    ));
                } else {
                    $this->error($this->_model->getError());
                }
            } else {
                $this->error('错误的操作！');
            }
        }
    }

    function ban()
    {
        if (IS_POST) {
            $id = I('post.id');
            $fabric = $this->_model->where(array('id' => $id))->find();
            if ($fabric) {
                if ($_GET['unpass']) {
                    $result = $this->_model->where(array('id' => $id))->save(array('status' => 0));
                }

                if ($_GET['pass']) {
                    $result = $this->_model->where(array('id' => $id))->save(array('status' => 1));
                }

                if ($result !== false) {
                    if ($_GET['unpass']) {
                        $this->ajaxReturn(array(
                            'status' => 1,
                            'info' => '该面料已下架！',
                            'data' => array('id' => $id),
                        ));
                    }

                    if ($_GET['pass']) {
                        $this->ajaxReturn(array(
                            'status' => 1,
                            'info' => '该面料已上架！',
                            'data' => array('id' => $id),
                        ));
                    }

                } else {
                    $this->error($this->_model->getError());
                }
            } else {
                $this->error('错误的操作！');
            }
        }
    }

    function secret()
    {
        if (IS_POST) {
            $id = I('post.id');
            $fabric = $this->_model->where(array('id' => $id))->find();
            if ($fabric) {
                if ($_GET['intimate']) {
                    $result = $this->_model->where(array('id' => $id))->save(array('ispublic' => 0));
                }

                if ($_GET['unintimate']) {
                    $result = $this->_model->where(array('id' => $id))->save(array('ispublic' => 1));
                }

                if ($result !== false) {
                    if ($_GET['intimate']) {
                        $this->ajaxReturn(array(
                            'status' => 1,
                            'info' => '该面料已设置公开！',
                            'data' => array('id' => $id),
                        ));
                    }

                    if ($_GET['unintimate']) {
                        $this->ajaxReturn(array(
                            'status' => 1,
                            'info' => '该面料已设置私密！',
                            'data' => array('id' => $id),
                        ));
                    }

                } else {
                    $this->error($this->_model->getError());
                }
            } else {
                $this->error('错误的操作！');
            }
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
            $tf = $this->_model->field('name,status as value')->find($id);
            $data['data'] = $tf['value'];
            $data['status'] = $result !== false ? 1 : 0;
            $data['info'] = $tf['name'] . '已设为<b>' . ($data['data'] ? '已上线' : '已下线') . '</b>';
            $this->ajaxReturn($data);
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
            $data['status'] = $result !== false ? 1 : 0;
            $data['info'] = $tf['name'] . '已设为<b>' . ($data['data'] ? '公开' : '私密') . '</b>';
            $this->ajaxReturn($data);
        }
    }

    public function toggle_recommended()
    {
        if (IS_POST) {
            $id = I('post.id/d', 0);
            $value = I('post.value/d', 0);
            if ($id == 0) {
                $this->error('传入数据出错！');
            }

            $result = $this->_model->where(array('id' => $id))->setField('recommended', $value);
            $tf = $this->_model->field('name,recommended as value')->where(array('id' => $id))->find();
            $data['data'] = $tf['value'];
            $data['status'] = $result !== false ? 1 : 0;
            $data['info'] = $tf['name'] . '已设为<b>' . ($data['data'] ? '已推荐' : '未推荐') . '</b>';
            $this->ajaxReturn($data);
        }
    }

    public function toggle_istop()
    {
        if (IS_POST) {
            $id = I('post.id/d', 0);
            $value = I('post.value/d', 0);
            if ($id == 0) {
                $this->error('传入数据出错！');
            }

            $result = $this->_model->where(array('id' => $id))->setField('istop', $value);
            $tf = $this->_model->field('name,istop as value')->where(array('id' => $id))->find();
            $data['data'] = $tf['value'];
            $data['status'] = $result !== false ? 1 : 0;
            $data['info'] = $tf['name'] . '已设为<b>' . ($data['data'] ? '已置顶' : '未置顶') . '</b>';
            $this->ajaxReturn($data);
        }
    }

    public function print_label($id)
    {
        $tf = $this->_model->find($id);
        if (empty($tf)) {
            $this->error('找不到面料');
        }
        $this->assign('tf', $tf);
        $this->assign('id', $id);
        $this->assign('url', leuu('Tf/Tf/fabric', array('id' => $id), false, true));
        $this->display();
    }

    public function dress_thumb()
    {
        $id = I('get.id/d', 0);
        $img = $this->_model->where("id='{$id}' AND vend_id='{$this->memberid}'")->getField('img');
        $img = str_replace("&quot;", '"', $img);
        $img = str_replace("'", '"', $img);
        $img = json_decode($img, true);
        $this->assign('img', $img);
        $this->display();
    }

    public function toggle_dress_thumb()
    {
        if (IS_POST) {
            $post = I('post.');
            $id = $post['id'];
            $key = $post['key'];
            $value = $post['value'];

            $img = $this->_model->where("id='{$id}' AND vend_id='{$this->memberid}'")->getField('img');
            $img = str_replace("&quot;", '"', $img);
            $img = str_replace("'", '"', $img);
            $img = json_decode($img, true);
            if (empty($img)) {
                $this->error('操作失败！');
            }

            if ($key == 'main') {
                $img['dress_thumb'] = $value;
            } else {
                $img['photo'][$key]['dress_status'] = $value;
            }

            $json = json_encode($img, 256);
            $this->_model->where("id='{$id}' AND vend_id='{$this->memberid}'")->setField('img', $json);

            $this->ajaxReturn(array(
                'status' => 1,
                'data' => $value,
                'info' => '操作成功！'
            ));
        }
    }


}
