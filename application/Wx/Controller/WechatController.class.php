<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-09-22
 * Time: 10:21
 */

namespace Wx\Controller;


use Common\Controller\WxbaseController;

class WechatController extends WxbaseController
{
    function __construct() {
        parent::__construct();
    }

    public function test() {
        $data = M("CollectGoods")->where(array("type" => 3, "user_id" => 648, "goods_id" => 1))->find();
        var_dump($data);
    }

    /*
     * 接收来自微信客户端的消息，判断消息类型执行对应的功能
     */
    function index(){
        $msg = $this->wechat->serve();
        if((string) $msg->MsgType == 'event'){
            $event = (string) $msg->Event;
            switch($event){
                case 'SCAN':
                    $this->do_scan();
                    break;
                case 'subscribe':
                    $this->do_subscribe();
                    break;
                /*case 'unsubscribe':
                    $this->do_unsubscribe();
                    break;*/
                default:
                    $this->we7_api();
            }
        }else{
            $this->we7_api();
        }
    }

    /*
     * 扫描带参数二维码，用户已关注时的事件推送
     */
    protected function do_scan(){
        $msg = $this->wechat->serve();
        $openid = (string) $msg->FromUserName;
        $eventkey = (string) $msg->EventKey;
        $ticket = (string) $msg->Ticket;
        $sceneArr = explode("_", $eventkey);

        $where = array(
            'scene_str' => $eventkey,
            'ticket'    => $ticket,
            'status'    => 1,
        );
        $qrcode_model = M('WxQrcode');
        $qrcode = $qrcode_model->where($where)->find();
        if (!empty($qrcode)) {
            $qrcode_model->where(array('id' => $qrcode['id']))->setInc('scan_count', 1);
            $log = array(
                'qr_id' => $qrcode['id'],
                'openid'=> $openid,
                'create_date'   => date('Y-m-d H:i:s'),
            );
            M('WxQrcodeLog')->add($log);

            if ($sceneArr[0] == "shop") {
                // 店铺关注
//                    $data = D('BizMember')->getMember($sceneArr[1]);
                $uid = M('Users')->where(array('openid'=>$openid))->getField('id');
                if (!M("CollectGoods")->where(array("type" => 3, "user_id" => $uid, "goods_id" => $sceneArr[1]))->find()) {
                    M("CollectGoods")->add(array(
                        "type" => 3,
                        "user_id" => $uid,
                        "goods_id" => $sceneArr[1],
                        "create_date" => date('Y-m-d H:i:s'),
                        "is_attention" => 0
                    ));
                }

                $this->reply_target($openid, $sceneArr[1], 5);
            }

            if (in_array($qrcode['type'], array(3, 4)) && $qrcode['status'] == 1) {
                $this->reply_target($openid, $qrcode['target_id'], $qrcode['type']);
            }

        } else if ($sceneArr[0] == "shop") {

                // 店铺关注
//                    $data = D('BizMember')->getMember($sceneArr[1]);

            $uid = M('Users')->where(array('openid'=>$openid))->getField('id');
            if (!M("CollectGoods")->where(array("type" => 3, "user_id" => $uid, "goods_id" => $sceneArr[1]))->find()) {
                M("CollectGoods")->add(array(
                    "type" => 3,
                    "user_id" => $uid,
                    "goods_id" => $sceneArr[1],
                    "create_date" => date('Y-m-d H:i:s'),
                    "is_attention" => 0
                ));
            }

            $this->reply_target($openid, $sceneArr[1], 5);

        } else {
            $this->we7_api();
        }
        exit();
    }

    public function reply_target($openid, $target_id, $type)
    {
        if ($type == 3) {
            $data = D('Tf/Tf')->getTf($target_id);
            if ($data) {
                $this->api->send($openid, array(
                    'type'  => 'news',
                    'articles'  => array(
                        array(
                            'title'     => $data['name'],
                            'description'=> $data['component'],
                            'picurl'    => get_thumb_url($data['img']['thumb']),
                            'url'       => leuu('Tf/Tf/fabric', array('id' => $data['id']), false, true),
                        ),
                    ),
                ));
            } else {
                $this->api->send($openid, '找不到面料！');
            }
        }
        if ($type == 4) {
            $data = D('Colorcard/Colorcard')->getCard($target_id);
            if ($data) {
                $this->api->send($openid, array(
                    'type'  => 'news',
                    'articles'  => array(
                        array(
                            'title'     => $data['card_name'],
                            'description'=> $data['biz_name'],
                            'picurl'    => get_thumb_url($data['frontcover']),
                            'url'       => leuu('Colorcard/Index/view', array('id' => $data['card_id'],'show_type'=>$data['tpl']['show_type']), false, true),
                        ),
                    ),
                ));
            } else {
                $this->api->send($openid, '找不到色卡！');
            }
        }
        if ($type == 5) {
            $data = D('BizMember')->getMember($target_id);
            if ($data) {
                $this->api->send($openid, array(
                    'type'  => 'news',
                    'articles'  => array(
                        array(
                            'title'     => $data['biz_name'],
                            'description'=> $data['biz_intro'],
                            'picurl'    => get_thumb_url($data['biz_logo']),
                            'url'       => leuu('Supplier/Index/single', array('id' => $data['id']), false, true),
                        ),
                    ),
                ));
            } else {
                $this->api->send($openid, '找不到色卡！');
            }
        }
        exit();
    }

    /*
     * 扫描带参数二维码，用户未关注时，进行关注后的事件推送
     */
    protected function do_subscribe(){
        $msg = $this->wechat->serve();
        $qrcode_model = M('WxQrcode');
        $openid = (string) $msg->FromUserName;

        //带参数二维码关注
        if($msg->EventKey){
            $eventkey = (string) $msg->EventKey;
            $ticket = (string) $msg->Ticket;
            $scene_str = substr($eventkey, 8);
            $where = array(
                'scene_str' => $scene_str,
                'ticket'    => $ticket,
                'status'    => 1,
            );
            $sceneArr = explode("_", $scene_str);
            $qrcode = $qrcode_model->where($where)->find();

            $uid = M('Users')->where(array('openid'=>$openid))->getField('id');

            if (!$uid) {
                $Wx = new \Wx\Common\Wechat();
                $Wx->regByOpenId($openid);
            }

            if (!empty($qrcode)) {
                $qrcode_model->where(array('id' => $qrcode['id']))->setInc('scan_count', 1);
                $log = array(
                    'qr_id' => $qrcode['id'],
                    'openid'=> $openid,
                    'create_date'   => date('Y-m-d H:i:s'),
                );
                $log_model = M('WxQrcodeLog');
                $log_model->add($log);


                if ($sceneArr[0] == "shop") {
                    // 店铺关注
//                    $data = D('BizMember')->getMember($sceneArr[1]);
                    $uid = M('Users')->where(array('openid'=>$openid))->getField('id');
                    if (!M("CollectGoods")->where(array("type" => 3, "user_id" => $uid, "goods_id" => $sceneArr[1]))->find()) {
                        M("CollectGoods")->add(array(
                            "type" => 3,
                            "user_id" => $uid,
                            "goods_id" => $sceneArr[1],
                            "create_date" => date('Y-m-d H:i:s'),
                            "is_attention" => 0
                        ));
                    }

                    $this->reply_target($openid, $sceneArr[1], 5);
                }

                if (in_array($qrcode['type'], array(3, 4)) && $qrcode['status'] == 1) {
                    $this->reply_target($openid, $qrcode['target_id'], $qrcode['type']);
                }
            } else if ($sceneArr[0] == "shop") {
                // 店铺关注
                $uid = M('Users')->where(array('openid'=>$openid))->getField('id');
                if (!M("CollectGoods")->where(array("type" => 3, "user_id" => $uid, "goods_id" => $sceneArr[1]))->find()) {
                    M("CollectGoods")->add(array(
                        "type" => 3,
                        "user_id" => $uid,
                        "goods_id" => $sceneArr[1],
                        "create_date" => date('Y-m-d H:i:s'),
                        "is_attention" => 0
                    ));
                }
//                    $data = D('BizMember')->getMember($sceneArr[1]);
                $this->reply_target($openid, $sceneArr[1], 5);
            }
        }else{
            $eventkey = null;
            $ticket = null;
        }

        $this->we7_api();

        //作绑定处理
        //$bind = $this->_connect_account($openid, $eventkey);
        exit();
    }

    /*
     * 取消关注
     */
    protected function do_unsubscribe(){
        $msg = $this->wechat->serve();
        $qrcode_model = M('WxQrcode');
        $openid = (string) $msg->FromUserName;

        //禁用会员
        //$this->_set_user_status($openid,0);
    }

    /*
     * 使用微擎API
     */
    protected function we7_api(){
        $data['get'] = json_encode($_GET);
        $data['input'] = file_get_contents('php://input');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://zd.mlg.kim/we7/api2.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);    // 要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_HEADER, 0); // 不要http header 加快效率
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_POST, 1);    // post 提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $output = curl_exec($ch);
        curl_close($ch);
        if ($output) {
            $this->wechat->reply($output);
        } else {
            $this->wechat->reply("欢迎关注面料馆，点击【找面料】即可搜寻您想要的面料信息，还可以通过【代客找版】足不出户，资深版哥帮您来找版");
        }

    }

    /*
     * 生成绑定账号二维码
     */
    public function bind_qrcode($url=true){
        if(sp_get_current_user()){
        }else{
            $this->error('请登录！');
        }
    }

    public function get_qrcode($ticket){
        list($err, $data) = $this->api->get_qrcode($ticket);
        header('Content-type: image/jpg');
        echo $data;
    }

    /*
     * 绑定系统账号，根据openid查找会员账号，没有就注册并绑定账号
     */
    private function _connect_account($openid, $sceneid=null){
        $data = array();
        $info = $this->api->get_user_info($openid);
        $user = D('Users')->getUserByOpenId($openid);

        if(!$user){
            $nickname = (string)$info[1]->nickname;
            $sex = (int)$info[1]->sex;
            $headimgurl = (string)$info[1]->headimgurl;
            $_POST['wx_register'] = 1;
            $_POST['openid'] = $openid;
            $_POST['username'] = 'wxuser_'.time();
            $_POST['password'] = $openid;
            $_POST['nickname'] = $nickname;
            $_POST['sex'] = $sex;
            $_POST['avatar'] = $headimgurl;
            $_POST['user_status'] = 1;
            $data['id'] = R('User/Register/doregister');
        }

        return $data;
    }

    /*
     * 设置用户状态
     */
    private function _set_user_status($openid,$status){
        M('Users')->where(array('openid'=>$openid))->setField('user_status',$status);
    }

    public function test_template() {

        $this->api->sendTemplateMessage(
            array(
                'touser' => "oPF6tw8R62HaUMwO2EEPrB9hh_tI",
                'template_id' => "Vz-Y41EEG0SvdmBbVmH8inrZn8S36GI0-_Y_17z-Caw",
                "url" => "http://www.baidu.com",
                "topcolor" => "#FF0000",
                "data" => array(
                    "first" => "2222",
                    "keyword1" => "3333",
                    "keyword2" => "4444",
                    "remark" => "5555"
                )
            )
        );
    }

}