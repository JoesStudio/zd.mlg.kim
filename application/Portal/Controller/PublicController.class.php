<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-26
 * Time: 10:25
 */

namespace Portal\Controller;


use Common\Controller\HomebaseController;

class PublicController extends HomebaseController
{
    function get_areas(){
        $pid = I('get.id');
        $cache_path = DATA_PATH.'areas/';
        $data['data'] = F("areas_$pid", '', $cache_path);
        if(empty($data['data'])){
            $data['data'] = D('Areas')->where(array('parentId'=>$pid))->select();
            F("areas_$pid", $data['data'], $cache_path);
        }
        $this->ajaxReturn($data);
    }

    function qrcode($url='http://zd.mlg.kim/',$level=3,$size=10){
        Vendor('phpqrcode');
        $errorCorrectionLevel =intval($level) ;//容错级别
        $matrixPointSize = intval($size);//生成图片大小
        //生成二维码图片
        //echo $_SERVER['REQUEST_URI'];
        $object = new \QRcode();
        $object->png(urldecode($url), false, $errorCorrectionLevel, $matrixPointSize, 2);
    }

    public function qrleuu($ug = 'Portal', $um = 'Index', $ua = 'index', $up = array())
    {
        $url = leuu("$ug/$um/$ua", $up, false, true);
        $this->qrcode($url);
    }

    public function search_user()
    {
        $keyword = I('request.term/s');
        $where['nickname'] = array('LIKE', "%$keyword%");
        $list = D('UserInfo')->field('user_id as id, nickname as value')->where($where)->select();
        $this->ajaxReturn(array(
            'data'  => $list
        ));
    }

    public function search_supplier()
    {
        $keyword = I('request.term/s');
        $where['biz_name'] = array('LIKE', "%$keyword%");
        $list = D('BizMember')->field('id, biz_name as value')->where($where)->select();
        $this->ajaxReturn(array(
            'data'  => $list
        ));
    }

    public function wx_qr($id, $type = 3)
    {
        $_model = D('Wx/Qr');
        if (!in_array($type, array(3, 4, 5))) {
            $type = 3;
        }
        $where['type'] = $type;
        $where['target_id'] = $id;
        $qrcode = $_model->where($where)->find();
        if (empty($qrcode)) {
            $wx = new \Wx\Common\Wechat();
            switch($type){
                case 3:
                    list($err, $qrcode) = $wx->createTfQr($id);
                    break;
                case 4:
                    list($err, $qrcode) = $wx->createCardQr($id);
                    break;
                case 5:
                    list($err, $qrcode) = $wx->createSupplierQr($id);
                    break;
                default:
            }
        }
        $this->ajaxReturn(array(
            'url'   => $qrcode['url']
        ));
    }

    public function link(){
        $url = $_GET['url']; 
        if(isset($url) && !empty($url)){ 
            $result = M('Share')->where(array('share_url'=>$url))->find();
            $real_url = $result['real_url'];
            
            if(!empty($real_url)){
                if(sp_is_mobile()){
                    $this->redirect('Colorcard/Index/visit_verify',array('su'=>$url,'id'=>$result['target_id']));   
                }else{
                    $this->error('请在微信端访问该链接！');
                }
                
                // header('Location: ' . $real_url); 
            }else{ 
                $this->error('此链接已经失效！');
            } 
        }else{ 
            $this->error('此链接已经失效！');
        } 
    }
}