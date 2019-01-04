<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-04
 * Time: 9:42
 */

function getJsApiParams(){
    $wx = new \Wx\Common\Wechat();
    return $wx->getSignPackage();
}

function getCatSubIds($cid, $self=true){
    $ids = array();
    if($self) array_push($ids, $cid);

    $model = M('TextileFabricCats');

    $subIds = $model->where("pid=$cid")->getField('id', true);
    if(!empty($subIds)){
        $ids = array_merge($ids, $subIds);
    }
    foreach($subIds as $subId){
        $sSubIds = getCatSubIds($subId, false);
        if(!empty($sSubIds)){
            $ids = array_merge($ids, getCatSubIds($subId, false));
        }
    }
    return $ids;
}

function getCatSubCodes($code, $self=true){
    $model = M('TextileFabricCats');
    $cid = $model->where("code='$code'")->getField('id');
    if($cid){
        $cids = getCatSubIds($cid, $self);
        $codes = empty($cids) ? array():$model->where(array('id'=>array('IN',$cids)))->getField('code', true);
        return $codes;
    }
    return array();
}

function get_delta_time($time1, $time2=null, $asc=true)
{
    if (is_null($time2)) $time2 = time();
    $time1 = is_string($time1) ? strtotime($time1):$time1;
    $time2 = is_string($time2) ? strtotime($time2):$time2;
    $delta = $asc ? $time1 - $time2:$time2 - $time1;
    $days = floor($delta/3600/24);
    $hours = floor(($delta%(3600*24))/3600);  //%取余
    $minutes = floor(($delta%(3600*24))%3600/60);
    $seconds = floor(($delta%(3600*24))%60);
    return array(
        'day'   => $days,
        'hour'  => $hours,
        'minute'=> $minutes,
        'second'=> $seconds,
    );
}

/*
 * 记录好友的最后浏览时间
*/

/**
 * 检查权限
 * @param name string|array  需要验证的规则列表,支持逗号分隔的权限规则或索引数组
 * @param opid  int           认证操作者的id
 * @param relation string    如果为 'or' 表示满足任一条规则即通过验证;如果为 'and'则表示需满足所有规则才能通过验证
 * @return boolean           通过验证返回true;失败返回false
 */
function opauth_check($opid, $name = null, $relation = 'or')
{
    $iauth_obj = new \Common\Lib\opAuth();
    if (empty($name)) {
        $name = strtolower(MODULE_NAME."/".CONTROLLER_NAME."/".ACTION_NAME);
    }
    return $iauth_obj->check($opid, $name, $relation);
}

function get_avatar_url($file)
{
    $default_img = '/public/images/headicon.png';
    if (empty($file)) {
        return $default_img;
    }
    if (strpos($file, "http") === 0) {
        return $file;
    } else {
        $img = sp_get_image_preview_url($file);
        $img = str_replace(sp_get_host(), '', $img);
        if (file_exists($_SERVER['DOCUMENT_ROOT'].$img)) {
            return $img;
        } else {
            return $default_img;
        }
    }
}

function get_thumb_url($file, $getthumb=false, $size="300")
{
    $default_img = '/public/images/default-thumbnail.png';
    if (empty($file)) {
        return $default_img;
    }
    $host = sp_get_host();
    /*$host_nowww = str_replace('http://www.', 'http://', $host);
    if(strpos($file, $host_nowww) === 0){
        $file = str_replace('http://', 'http://www.', $file);
    }*/
    if (strpos($file, "http") === 0 && strpos($file, $host) !== 0) {
        return $file;
    } else {
        $img = sp_get_image_preview_url($file);

        if($getthumb){
            $oExt = pathinfo($img, PATHINFO_EXTENSION);
            $oName = basename($img, '.'.$oExt);
            $tName = "{$oName}_thumb-{$size}.{$oExt}";
            //$thumbName = 'thumb_'.$oName;
            $thumb = str_replace(basename($img), $tName, $img);
            $thumb = str_replace($host, '', $thumb);
            if (file_exists($_SERVER['DOCUMENT_ROOT'].$thumb)) {
                return $host.$thumb;
            }else{
                create_thumb($file, $size);
                if(file_exists($_SERVER['DOCUMENT_ROOT'].$thumb)){
                    return $host.$thumb;
                }

                $thumb = str_replace(basename($img), 'thumb_'.basename($img), $img);
                $thumb = str_replace($host, '', $thumb);
                if (file_exists($_SERVER['DOCUMENT_ROOT'].$thumb)) {
                    return $host.$thumb;
                }
            }


        }
        $img = str_replace($host, '', $img);

        if (file_exists($_SERVER['DOCUMENT_ROOT'].$img)) {
            return $host.$img;
        } else {
            return $host.$default_img;
        }
    }
}

function create_thumb($file, $size){
    if(strpos($file,"/")===0){
        $img_path = SITE_PATH.$file;
    }else{
        $img_path = C("UPLOADPATH").$file;
    }
    $Img = new \Think\UploadImage();//实例化图片类对象
    //是图像文件生成缩略图
    $thumbWidth		= $size;
    $thumbHeight	= $size;
    $thumbPrefix    = '';
    $thumbSuffix    = '_thumb-'.$size;

    $pathinfo = pathinfo($img_path);

    $oExt = $pathinfo['extension'];
    $oName = basename($img_path, '.'.$oExt);
    //$tName = "{$oName}_thumb-{$size}.{$oExt}";
    $thumbPath = $pathinfo['dirname'];

    $thumbname	=	$thumbPath.'/'.$thumbPrefix.$oName.$thumbSuffix.'.'.$oExt;
    $Img->thumb($img_path,$thumbname,'',$thumbWidth,$thumbHeight,true);
}

function be_fans_by_code(){
    if(sp_is_user_login()){
        $code = session('invite_code');
        //已登录
        $invite_member = D('BizMember')->where("biz_code='$code'")->find();
        if(!empty($invite_member) && !empty($invite_member['biz_code'])){
            $user = session('user');
            $fans_model = D('Supplier/Fans');
            $count = $fans_model
                ->where(array('member_id'=>$invite_member['id'],'user_id'=>$user['id']))
                ->count();
            //未成为好友的情况下
            if($count > 0){
                session('invite_code',null);
            }else{
                $fansData = array(
                    'member_id'     => $invite_member['id'],
                    'user_id'       => $user['id'],
                );
                //查找是否有下单记录
                $fansorder = D('Order/Order')
                    ->field('add_time as order_date,COUNT(order_id) as order_num')
                    ->where(array('user_id'=>$user['id'],'supplier_id'=>$invite_member['id']))
                    ->order('add_time DESC')
                    ->group('user_id')
                    ->find();
                if(!empty($fansorder)){
                    $fansData['order_num'] = $fansorder['order_num'];
                    $fansData['order_date'] = date('Y-m-d H:i:s', $fansorder['order_date']);
                }
                //成为好友
                $result = $fans_model->saveFan($fansData);
                if($result !== false){
                    session('invite_code',null);
                }
                return array(
                    'fans_id'    => $result,
                    'biz_name'  => $invite_member['biz_name'],
                );
            }
        }
    }
    return false;
}

function sp_is_mobile_bind(){
    return !empty($_SESSION['user']['userinfo']['mobile']);
}

/**
 * 获取后台管理设置的网站信息，此类信息一般用于前台，推荐使用sp_get_site_options
 */
function get_basic_options(){
    $site_options = F("site_basic");
    if(empty($site_options)){
        $options_obj = M("Options");
        $option = $options_obj->where("option_name='site_basic'")->find();
        if($option){
            $site_options = json_decode($option['option_value'],true);
        }else{
            $site_options = array();
        }
        F("site_basic", $site_options);
    }
    return $site_options;
}

function is_weixin(){
    if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ) {
        return true;
    }
    return false;
}

function get_wx_api(){
    $config = get_wx_configs();
    $wxApi = new \Gaoming13\WechatPhpSdk\Api(array(
        'appId' => $config['appId'],
        'appSecret'    => $config['appSecret'],
        'mchId' => $config['mchId'], //微信支付商户号
        'key' => $config['key'], //微信商户API密钥
        'get_access_token' => function(){
            return S('wechat_token');
        },
        'save_access_token' => function($token) {
            S('wechat_token', $token);
        }
    ));

    return $wxApi;
}

/**
 * 获取微信公众号配置
 */
function get_wx_configs(){
    $wx_configs = F("wx_configs");
    if(empty($wx_configs)){
        $options_obj = M("Options");
        $option = $options_obj->where("option_name='wx_configs'")->find();
        if($option){
            $wx_configs = json_decode($option['option_value'],true);
        }else{
            $wx_configs = array();
        }
        F("wx_configs", $wx_configs);
    }
    return $wx_configs;
}

// 说明：获取完整URL
function curPageURL(){
    $pageURL = 'http';
    if ($_SERVER["HTTPS"] == "on"){
        $pageURL .= "s";
    }
    $pageURL .= "://";
    if ($_SERVER["SERVER_PORT"] != "80"){
        $pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
    }
    else{
        $pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
    }
    return $pageURL;
}

function switch_user_type(){
    $user = sp_get_current_user();
    if(!empty($user)){
        switch($user['user_type']){
            case '21':
                session('user_type','supplier');
                break;
            case '22':
                session('user_type','customer');
                break;
            default:
                session('user_type','customer');
        }
    }
}

function delta_time($t1,$t2="now"){
    $t1 = is_numeric($t1) ? $t1:(int)strtotime($t1);
    $t2 = is_numeric($t2) ? $t2:(int)strtotime($t2);
    $delta = $t2 - $t1;
    if($delta < 600){
        $timestr = '刚刚';
    }elseif($delta < 3600){
        $timestr = (int)($delta / 60) . ' 分钟前';
    }elseif($delta < 86400){
        $timestr = (int)($delta / 3600) . ' 小时前';
    }elseif($delta < 2678400){
        $timestr = (int)($delta / 86400) . ' 天前';
    }elseif($delta < 31536000){
        $timestr = (int)($delta / 2678400) . ' 个月前';
    }else{
        $timestr = (int)($delta / 31536000) . ' 年前';
    }
    return $timestr;
}

function qrcode($url='http://www.xxx.com/',$level=3,$size=10){
    Vendor('phpqrcode');
    $errorCorrectionLevel =intval($level) ;//容错级别
    $matrixPointSize = intval($size);//生成图片大小
    //生成二维码图片
    //echo $_SERVER['REQUEST_URI'];
    $object = new \QRcode();
    $object->png($url, false, $errorCorrectionLevel, $matrixPointSize, 2);
}

//获取操作人ID
function mGetOperatorId(){
    if(MODULE_NAME == 'Admin'){
        $uid = sp_get_current_admin_id();
    }else{
        $uid = sp_get_current_userid();
    }
    return $uid;
}

//获取操作人名字
function mGetOperator(){
    if(MODULE_NAME == 'Admin'){
        $uid = sp_get_current_admin_id();
        $user = M('Users')->find($uid);
    }else{
        $user = sp_get_current_user();
    }
    $name = empty($user['nickname']) ? $user['nickname']:$user['user_login'];
    return $name;
}

//用于获取时间，格式为2012-02-03 12:12:12,注意,方法不能为private
function mGetDate() {
    return date('Y-m-d H:i:s');
}


/**
 * 文章分页查询方法
 * @param string $tag  查询标签，以字符串方式传入,例："cid:1,2;field:post_title,post_content;limit:0,8;order:post_date desc,listorder desc;where:id>0;"<br>
 *  ids:调用指定id的一个或多个数据,如 1,2,3<br>
 *  cid:数据所在分类,可调出一个或多个分类数据,如 1,2,3 默认值为全部,在当前分类为:'.$cid.'<br>
 *  field:调用post指定字段,如(id,post_title...) 默认全部<br>
 *  limit:数据条数,默认值为10,可以指定从第几条开始,如3,8(表示共调用8条,从第3条开始)<br>
 *  order:排序方式，如：post_date desc<br>
 *  where:查询条件，字符串形式，和sql语句一样
 * @param int $pagesize 每页条数.
 * @param string $pagetpl 以字符串方式传入,例："{first}{prev}{liststart}{list}{listend}{next}{last}"
 * @return array 带分页数据的文章列表

 */

function ad_sql_posts_paged($tag,$pagesize=20,$pagetpl='{first}{prev}{liststart}{list}{listend}{next}{last}'){
    $where=array();
    $tag=sp_param_lable($tag);
    $field_default = 'a.tid,a.object_id,a.listorder,b.id,b.post_keywords,b.post_source,b.post_date,b.post_title,b.post_excerpt,b.post_content,
        b.smeta,b.comment_count,b.post_hits,b.post_like,b.istop,b.recommended,c.user_login,c.nickname,
        d.term_id,d.name,d.path';
    $field = !empty($tag['field']) ? $tag['field'] : $field_default;
    $limit = !empty($tag['limit']) ? $tag['limit'] : '';
    $order = !empty($tag['order']) ? $tag['order'] : 'post_date';

    //根据参数生成查询条件
    $where['a.status'] = array('eq',1);
    $where['post_status'] = array('eq',1);

    if (isset($tag['cid'])) {
        $where['a.term_id'] = array('in',$tag['cid']);
    }


    if (isset($tag['path'])) {
        //获取该分类下的所有记录
        $path = $tag['path'];
        $where['_string'] = "d.path LIKE '%-$path-%' OR d.path LIKE '%-$path' OR d.path LIKE '$path-%'";
    }

    if (isset($tag['ids'])) {
        $where['object_id'] = array('in',$tag['ids']);
    }

    if (isset($tag['where'])) {
        $where['_string'] = $tag['where'];
    }

    $join = "".C('DB_PREFIX').'posts as b on a.object_id =b.id';
    $join2= "".C('DB_PREFIX').'users as c on b.post_author = c.id';
    $join3= "".C('DB_PREFIX').'terms as d on a.term_id = d.term_id';
    $rs= M("TermRelationships");
    $totalsize=$rs->alias("a")->join($join)->join($join2)->join($join3)->field($field)->where($where)->count();

    import('Page');
    if ($pagesize == 0) {
        $pagesize = 20;
    }
    $PageParam = C("VAR_PAGE");
    $page = new \Page($totalsize,$pagesize);
    $page->setLinkWraper("li");
    $page->__set("PageParam", $PageParam);
    $page->SetPager('default', $pagetpl, array("listlong" => "9", "first" => "首页", "last" => "尾页", "prev" => "上一页", "next" => "下一页", "list" => "*", "disabledclass" => ""));
    $posts=$rs->alias("a")->join($join)->join($join2)->join($join3)->field($field)->where($where)->order($order)->limit($page->firstRow . ',' . $page->listRows)->select();

    $content['posts']=$posts;
    $content['page']=$page->show('default');
    $content['count']=$totalsize;
    return $content;
}


//分类文章数
function ad_count_cat_posts($tag){
    $where=array();
    $tag=sp_param_lable($tag);

    //根据参数生成查询条件
    $where['a.status'] = array('eq',1);
    $where['post_status'] = array('eq',1);

    if (isset($tag['cid'])) {
        $where['a.term_id'] = array('in', get_term_sub_ids($tag['cid']));
    }

    if (isset($tag['path'])) {
        $where['path'] = array('like',$tag['path']);
    }

    if (isset($tag['ids'])) {
        $where['object_id'] = array('in',$tag['ids']);
    }

    if (isset($tag['where'])) {
        $where['_string'] = $tag['where'];
    }

    $count = M("TermRelationships")->alias("a")->where($where)->count();

    return $count;
}

//分类及子分类ids
function get_term_sub_ids($term_id,$self=true){
    $term_obj = M("Terms");
    $where['status'] = 1;
    $terms = $term_obj->field('term_id,name,parent,path')->where($where)->select();
    $subs = array();
    if($self){
        $subs[] = $term_id;
    }
    $subs = array_merge($subs, ad_get_subs_cids($terms, $subs[0]));
    $subs = implode(',', $subs);
    return $subs;
}

function ad_get_subs_cids($categorys,$catId=0){
    $subs=array();
    foreach($categorys as $item){
        if($item['parent']==$catId){
            $subs[]=$item['term_id'];
            $subs=array_merge($subs, ad_get_subs_cids($categorys, $item['term_id']));
        }
    }
    return $subs;
}

//分类目录
function ad_categories($term_id=0,$taxonomy="article",$order="listorder ASC"){
    $html = '';
    if($order == ''){
        $order = "listorder ASC";
    }
    $categories = M('Terms')->where(array("taxonomy"=>$taxonomy,"parent"=>$term_id))->order($order)->select();
    
    if($categories){
        $html = '<ul class="list-unstyled cat-link-list">';
        foreach($categories as $k => $v){
            $html .= '<li>';
            $count = ad_count_cat_posts("cid:$v[term_id]");
            $url = leuu('List/index', array('id'=>$v['term_id']));
            $html .= "<a href=\"$url\" title=\"$v[name]\">$v[name] <span>[$count]</span></a>";
            $html .= ad_categories($v['term_id']);
            $html .= '</li>';
        }
        $html .= '</ul>';
    }
    
    return $html;
}
//生成短链接
function shortUrl($url){
    $result = sprintf("%u",crc32($url));
    $show = '';
    while($result  >0){
        $s = $result % 62;
        if($s > 35){
            $s=chr($s+61);
        }elseif($s>9 && $s<=35){
            $s=chr($s+55);
        }
        $show .= $s;
        $result = floor($result / 62);
    }
    return $show;
}