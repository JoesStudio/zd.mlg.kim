<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-05-05
 * Time: 16:22
 */

namespace Tf\Controller;


use Common\Controller\HomebaseController;

class DressController extends HomebaseController
{
    protected $tf_model;
    protected $remote_host = '119.23.133.50';
    protected $server_url;

    function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->tf_model = D('Tf/Tf');
        $this->server_url = 'http://' . $this->remote_host;
    }

    function dress()
    {
        /*if(sp_is_mobile()){
            $this->error('在线试衣功能暂未在手机浏览器上开放，请在电脑浏览器上打开本页面');
        }*/
        $id = I('get.id/d');
        $where['id'] = $id;
        $where['status'] = 1;
        $data = $this->tf_model->where($where)->find();
        if (empty($data)) {
            $this->error('找不到面料');
        }
        $this->assign('id',$id);

        $supplier_id = $data['vend_id'];
        $uid = sp_get_current_userid();

        if (sp_is_user_login()) {
            if (empty($_SESSION['user']['member']) || $supplier_id != $_SESSION['user']['member']['id']) {
                $fans = D('Supplier/Fans')
                    ->where("user_id=$uid AND member_id=$supplier_id")
                    ->find();
                if ($data['ispublic'] == 0 && $fans['level'] < $data['fans_level']) {
                    $this->error('这是私密面料，联系面料商咨询详细情况！');
                }
            }
        } else {
            if ($data['ispublic'] == 0) {
                $this->error('这是私密面料，联系面料商咨询详细情况！');
            }
        }

        //从图集获取贴图
        $img = M('TextileFabric')->where("id='{$id}' AND vend_id='{$supplier_id}'")->getField('img');
        $img = str_replace("&quot;", '"', $img);
        $img = str_replace("'", '"', $img);
        $img = json_decode($img, true);

        $this->assign("tf_img", $img);

        //从sku获取贴图
        $skuList = D('Tf/Sku')->where("tf_id=$id")->select();

        if (empty($skuList)) {
            $this->error('该面料没有可用的试衣颜色');
        }

        $models = M('DressModel')->where("status=1")->select();
        $this->assign('models', $models);

        $this->assign('data', $data);
        $this->assign('skulist', $skuList);

        $settings = $this->get_settings();
        $this->assign('settings', $settings);
        $this->display();
    }

    private function get_settings(){
        $options = F("dress_settings");
        if(empty($options)){
            $options_obj = M("Options");
            $option = $options_obj->where("option_name='dress_settings'")->find();
            if($option){
                $options = json_decode($option['option_value'],true);
            }else{
                $options = array();
            }
            F("dress_settings", $options);
        }
        return $options;
    }

    function dress_list()
    {
        $tf_id = I('get.tf_id');
        $where['tf_id'] = $tf_id;
        $where['path'] = array('neq', '');
        $result['data'] = M('Dress')->where($where)->select();
        $this->ajaxReturn($result);
    }

    function get_dress()
    {
        $sku_id = I('get.sku_id/d', 0);
        $model_id = I('get.model_id/d', 0);
        $zoom = I('get.zoom/d', 0);
        $x = I('get.x/d', 0);
        $y = I('get.y/d', 0);
        $requested = I('get.requested/d', 0);
        if (abs($zoom) > 9 || abs($x) > 9 || abs($y) > 9) {
            $this->error('缩放或平移超出范围！');
        }

        $tf_id = D('Tf/Sku')->where("id=$sku_id")->getField('tf_id');
        if (!$tf_id) {
            $this->error('请选择面料颜色！');
        }
        $model = M('DressModel')->find($model_id);
        if (empty($model)) {
            $this->error('请选择模特！');
        }

        $prefix = $model['num'] + 100;
        $prefix .= ($zoom >= 0 ? '1' : '2') . abs($zoom);
        $prefix .= ($x >= 0 ? '2' : '1') . abs($x);
        $prefix .= ($y >= 0 ? '2' : '1') . abs($y);
        $tfFileName = "tf_{$tf_id}_{$sku_id}.jpg";
        $filename = $prefix . $tfFileName;
        $isrc = "$prefix\\$tfFileName";

        //服务器是否存在图片，是则直接显示图片，否则生成图片
        if ($this->remote_file_exists($filename)) {
            $remote_file = $this->server_url . "/aa/$filename";
            $result = array(
                'status' => 1,
                'data' => array(
                    'state' => 'EXIST',
                    'url' => $remote_file,
                )
            );
        }else{
            //是否已经请求过，是则返回处理中状态，否则发起处理请求
            if(!$requested){
                //判断远端是否存在面料图片
                if (!$this->remote_file_exists($tfFileName)) {
                    $request_url = $this->server_url."/async.php?sku_id=$sku_id";
                    $content = file_get_contents($request_url);
                    $status = json_decode($content, true);
                    if(!$status['status']){
                        $this->error('加载在线试衣服务器面料贴图失败，请联系管理员。');
                    }
                }

                $request_url = $this->server_url . "/deal.php?isrc=$isrc";
                $content = file_get_contents($request_url);
                $result = array(
                    'status' => 1,
                    'data' => array(
                        'state' => 'REQUESTED',
                        'request' => $isrc,
                        'result'    => $content
                    )
                );
            }else{
                $result = array(
                    'status' => 1,
                    'data' => array(
                        'state' => 'PROCESSING'
                    )
                );
            }
        }
        $this->ajaxReturn($result);
    }

    function get_dress_by_thumb()
    {
        $key = I('get.key');
        $tf_id = I('get.id/d',0);
        $model_id = I('get.model_id/d', 0);
        $zoom = I('get.zoom/d', 0);
        $x = I('get.x/d', 0);
        $y = I('get.y/d', 0);
        $requested = I('get.requested/d', 0);
        if (abs($zoom) > 9 || abs($x) > 9 || abs($y) > 9) {
            $this->error('缩放或平移超出范围！');
        }

        if($tf_id == 0) {
            $this->error('找不到贴图！');
        }
        $img = M('TextileFabric')->where("id='{$tf_id}'")->getField('img');
        $img = str_replace("&quot;", '"', $img);
        $img = str_replace("'", '"', $img);
        $img = json_decode($img, true);
        if($key == 'main'){
            $pic = $img['thumb'];
        }else{
            $pic = $img['photo'][$key];
        }

        $model = M('DressModel')->find($model_id);
        if (empty($model)) {
            $this->error('请选择模特！');
        }

        $prefix = $model['num'] + 100;
        $prefix .= ($zoom >= 0 ? '1' : '2') . abs($zoom);
        $prefix .= ($x >= 0 ? '2' : '1') . abs($x);
        $prefix .= ($y >= 0 ? '2' : '1') . abs($y);
        $tfFileName = "tf_{$tf_id}_key_{$key}.jpg";
        $filename = $prefix . $tfFileName;
        $isrc = "$prefix\\$tfFileName";

        //服务器是否存在图片，是则直接显示图片，否则生成图片
        if ($this->remote_file_exists($filename)) {
            $remote_file = $this->server_url . "/aa/$filename";
            $result = array(
                'status' => 1,
                'data' => array(
                    'state' => 'EXIST',
                    'url' => $remote_file,
                )
            );
        }else{
            //是否已经请求过，是则返回处理中状态，否则发起处理请求
            if(!$requested){
                //判断远端是否存在面料图片
                if (!$this->remote_file_exists($tfFileName)) {
                    $request_url = $this->server_url."/async.php?id=$tf_id&key=$key";
                    $content = file_get_contents($request_url);
                    $status = json_decode($content, true);
                    if(!$status['status']){
                        $this->error('加载在线试衣服务器面料贴图失败，请联系管理员。');
                    }
                }

                $request_url = $this->server_url . "/deal.php?isrc=$isrc";
                $content = file_get_contents($request_url);
                $result = array(
                    'status' => 1,
                    'data' => array(
                        'state' => 'REQUESTED',
                        'request' => $isrc,
                        'result'    => $content
                    )
                );
            }else{
                $result = array(
                    'status' => 1,
                    'data' => array(
                        'state' => 'PROCESSING'
                    )
                );
            }
        }
        $this->ajaxReturn($result);
    }

    public function exist($url){
        $path = SITE_PATH.C('UPLOADPATH').$url;
        $data['exist'] = file_exists($path);
        $this->ajaxReturn($data);
    }

    //判断是否存在远程图片
    private function remote_file_exists($name)
    {
        $content = file_get_contents($this->server_url . "/exist.php?name=$name");
        $exist = json_decode($content, true);
        return $exist['exist'];
    }

    private function get_dress2($sku_id, $model_id, $zoom = 0, $x = 0, $y = 0)
    {
        if (abs($zoom) > 9 || abs($x) > 9 || abs($y) > 9) {
            $this->error('传入数据错误！');
        }

        $dress_path = $this->dress_exist($sku_id, $model_id, $zoom, $x, $y);
        if (!$dress_path) {
            $this->dl_remote_dress($sku_id, $model_id, $zoom, $x, $y);
        }
        $this->ajaxReturn(array(
            'status' => 1,
            'data' => array(
                'state' => 'EXIST',
                'url' => $dress_path
            )
        ));

    }

    //判断图片是否存在本地
    private function dress_exist($sku_id, $model_id, $zoom = 0, $x = 0, $y = 0)
    {
        if (abs($zoom) > 9 || abs($x) > 9 || abs($y) > 9) {
            return false;
        }

        $where['d.sku_id'] = $sku_id;
        $where['d.model_id'] = $model_id;
        $where['d.zoom'] = $zoom;
        $where['d.x'] = $x;
        $where['d.y'] = $y;
        $field = "d.*,dm.num";

        $join = "INNER JOIN __DRESS_MODEL__ dm ON dm.id=d.model_id";
        $dress = M('Dress')->field($field)->alias('d')
            ->join($join)->where($where)->find();
        if (empty($dress) || $dress['path'] == '') {
            return false;
        }

        $file = "./" . C("UPLOADPATH") . $dress['path'];
        if (!file_exists($file)) {
            return false;
        }
        return get_thumb_url($dress['path']);
    }

    //下载远程图片
    private function dl_remote_dress($sku_id, $model_id, $zoom = 0, $x = 0, $y = 0)
    {
        if (abs($zoom) > 9 || abs($x) > 9 || abs($y) > 9) {
            $this->error('传入数据错误！');
        }

        $tf_id = D('Tf/Sku')->where("id=$sku_id")->getField('tf_id');
        if (!$tf_id) {
            $this->error('不存在的面料！');
        }
        $model = M('DressModel')->find($model_id);
        if (empty($model)) {
            $this->error('找不到模特！');
        }

        $prefix = $model['num'] + 100;
        $prefix .= ($zoom >= 0 ? '1' : '2') . abs($zoom);
        $prefix .= ($x >= 0 ? '2' : '1') . abs($x);
        $prefix .= ($y >= 0 ? '2' : '1') . abs($y);
        $tfFileName = "tf_{$tf_id}_{$sku_id}.jpg";
        $filename = $prefix . $tfFileName;
        $isrc = "$prefix\\$tfFileName";
        $assetpath = "dress/$filename";

        //判断远端是否存在面料图片
        if (!$this->remote_file_exists($tfFileName)) {
            $content = file_get_contents($this->server_url."/async.php?sku_id=$sku_id");
            $status = json_decode($content, true);
            if(!$status['status']){
                $this->error('远端不存在面料图片');
            }
        }

        if ($this->remote_file_exists($filename)) {
            $remote_file = $this->server_url . "/aa/$filename";
            $result = array(
                'status' => 1,
                'data' => array(
                    'state' => 'EXIST',
                    'url' => $remote_file,
                )
            );
        }else{
            $request_url = $this->server_url . "/deal.php?isrc=$isrc";
            file_get_contents($request_url);
            $result = array(
                'status' => 1,
                'data' => array(
                    'state' => 'REQUESTED',
                    'request' => $isrc,
                )
            );
        }
        $this->ajaxReturn($result);



        //远程的图片是否存在，存在则下载，不存在则请求生成一个
        /*if ($this->remote_file_exists($filename)) {
            $remote_file = $this->server_url . "/aa/$filename";
            $savepath = "./" . C('UPLOADPATH') . $assetpath;
            $http = new \Org\Net\Http();
            $http->curlDownload($remote_file, $savepath);
            $saveDress = array(
                'tf_id' => $tf_id,
                'sku_id' => $sku_id,
                'model_id' => $model_id,
                'zoom' => $zoom,
                'x' => $x,
                'y' => $y,
                'path' => $assetpath,
            );
            $dress_id = M('Dress')->where(array(
                'tf_id' => $tf_id,
                'sku_id' => $sku_id,
                'model_id' => $model_id,
                'zoom' => $zoom,
                'x' => $x,
                'y' => $y,
            ))->getField('id');
            if ($dress_id) {
                M('Dress')->where("id=$dress_id")->save($saveDress);
            } else {
                M('Dress')->add($saveDress);
            }
            if (file_exists($savepath)) {
                $result = array(
                    'status' => 1,
                    'data' => array(
                        'state' => 'DOWNLOADING',
                        'filename' => $filename,
                        'remote_url' => $remote_file,
                        'url' => get_thumb_url($assetpath),
                    )
                );
            } else {
                $result = array(
                    'status' => 1,
                    'data' => array(
                        'state' => 'DOWNLOADED',
                        'filename' => $filename,
                        'url' => get_thumb_url($assetpath),
                    )
                );
            }
        } else {
            $request_url = $this->server_url . "/deal.php?isrc=$isrc";
            file_get_contents($request_url);
            $result = array(
                'status' => 1,
                'data' => array(
                    'state' => 'REQUESTED',
                    'request' => $isrc
                )
            );
        }*/
        $this->ajaxReturn($result);
    }

}