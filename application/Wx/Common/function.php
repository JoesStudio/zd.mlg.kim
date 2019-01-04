<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-12-29
 * Time: 15:54
 */

function login_session($user, $referer=true){
    session('user',$user);
    session('wxbind_data',null);
    setcookie("uid", sp_get_current_userid(), time() + 3600 * 24, "/", ".mlg.kim");
    setcookie("session_mlg", session_id(), time() + 3600 * 24, "/", ".mlg.kim");

    //判断是不是版哥，是的话把uid写上
    if (D("AgencyUser")->where("uid = %d", sp_get_current_userid())->find()) {
        session("agency", array(
            "uid" => sp_get_current_userid()
        ));
    }

    //写入此次登录信息
    $data = array(
        'last_login_time' => date("Y-m-d H:i:s"),
        'last_login_ip' => get_client_ip(0,true),
    );
    M('Users')->where("id=".$user["id"])->save($data);

    if($referer){
        $user_type = session('user_type');
        $default_url = leuu('User/Center/index');
        $session_login_http_referer=session('login_http_referer');
        //$redirect=empty($session_login_http_referer)?$default_url:$session_login_http_referer;
        $redirect = $default_url;
        session('login_http_referer','');
    }else{
        $redirect = '';
    }

    return $redirect;
}