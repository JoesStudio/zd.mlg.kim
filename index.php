<?php
/**
 * 入口文件
 * Some rights reserved：www.thinkcmf.com
 */
//set_time_limit(0);
//ini_set('display_errors','on');
//error_reporting(E_ALL);
$origin = isset($_SERVER['HTTP_ORIGIN'])? $_SERVER['HTTP_ORIGIN'] : '';
$allowOrigin = array(
    'http://dev.v1.mlg.kim',
    'http://dev.v1.mlg.kim:8080',
    'http://v1.mlg.kim',
    'http://192.168.0.33:8080',
    'http://192.168.0.26',
    'http://zd1.mlg.kim',
    'http://zdtest.mlg.kim:81'
);
header("Access-Control-Allow-Credentials: true");
if(in_array($origin, $allowOrigin)){
    header('Access-Control-Allow-Origin:'.$origin);
}
//判断请求，options是浏览器的跨域运行判断请求，只发送header
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("Access-Control-Allow-Methods: POST,GET,PUT");
    header("Access-Control-Allow-Headers: ".$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
    exit; //结束，只需要返回头部即可
}

////转移到正式域名
//if ($_SERVER['HTTP_HOST'] == "mlg.zunar.org") {
//    header("Location: http://www.mlg.kim" . $_SERVER['REQUEST_URI']);
//    exit;
//}

if (ini_get('magic_quotes_gpc')) {
    function stripslashesRecursive(array $array)
    {
        foreach ($array as $k => $v) {
            if (is_string($v)) {
                $array[$k] = stripslashes($v);
            } else if (is_array($v)) {
                $array[$k] = stripslashesRecursive($v);
            }
        }
        return $array;
    }
    $_GET = stripslashesRecursive($_GET);
    $_POST = stripslashesRecursive($_POST);
}
//开启调试模式
define("APP_DEBUG", true);
//网站当前路径
define('SITE_PATH', dirname(__FILE__)."/");
//项目路径，不可更改
define('APP_PATH', SITE_PATH . 'application/');
//项目相对路径，不可更改
define('SPAPP_PATH', SITE_PATH.'simplewind/');
//
define('SPAPP', './application/');
//项目资源目录，不可更改
define('SPSTATIC', SITE_PATH.'statics/');
//定义缓存存放路径
define("RUNTIME_PATH", SITE_PATH . "data/runtime/");
//静态缓存目录
define("HTML_PATH", SITE_PATH . "data/runtime/Html/");
//版本号
define("THINKCMF_VERSION", 'X2.2.0');

define("THINKCMF_CORE_TAGLIBS", 'cx,Common\Lib\Taglib\TagLibSpadmin,Common\Lib\Taglib\TagLibHome');

if (function_exists('saeAutoLoader') || isset($_SERVER['HTTP_BAE_ENV_APPID'])) {
} else {
    if (!file_exists("data/install.lock")) {
        if (strtolower($_GET['g'])!="install") {
            header("Location:./index.php?g=install");
            exit();
        }
    }
}
//uc client root
define("UC_CLIENT_ROOT", './api/uc_client/');

if (file_exists(UC_CLIENT_ROOT."config.inc.php")) {
    include UC_CLIENT_ROOT."config.inc.php";
}

//载入框架核心文件
require SPAPP_PATH.'Core/ThinkPHP.php';
