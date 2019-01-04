<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-09
 * Time: 17:22
 */

namespace User\Service;
use Think\Model;
class SettleService extends Model
{

    function upload($config=array()){
        if(!$config){
            $config=array(
                'rootPath' => './'.C("UPLOADPATH"),
                'savePath' => './unknown/',
                'maxSize' => 4096000,//500K
                'saveName'   =>    array('uniqid',''),
                'exts'       =>    array('jpg', 'png', 'jpeg'),
                'autoSub'    =>    false,
            );
        }

        $upload = new \Think\Upload($config,'Local');//先在本地裁剪
        $info=$upload->upload();

        if($info){
            $result['status'] = 1;
            $result['info'] = $info;
        }else{
            $result['status'] = 0;
            $result['error'] = $upload->getError();
        }

        return $result;
    }

    function authPhotoUpload(){
        $config=array(
            'rootPath' => './'.C("UPLOADPATH"),
            'savePath' => './biz_auth/',
            'maxSize' => 4096000,//500K
            'saveName'   =>    array('uniqid',''),
            'exts'       =>    array('jpg', 'png', 'jpeg'),
            'autoSub'    =>    false,
        );

        return $this->upload($config);
    }

    function avatarUpload(){
        $config=array(
            'rootPath' => './'.C("UPLOADPATH"),
            'savePath' => './avatar/',
            'maxSize' => 512000,//500K
            'saveName'   =>    array('uniqid',''),
            'exts'       =>    array('jpg', 'png', 'jpeg'),
            'autoSub'    =>    false,
        );

        return $this->upload($config);
    }
}