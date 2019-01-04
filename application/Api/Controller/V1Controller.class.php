<?php

namespace Api\Controller;
use Think\Controller;


class V1Controller extends Controller {

    protected $agencyService;         //代理模型

    public function __construct() {
        $this->agencyService = D("Agency","Service");
    }


    //代客找版提交
    public function submit_agency_apply() {

        $data = array();
        $data['description'] = I("get.des");              //描述
        $data['image'] = $_GET['images'];              //图片数据 数组
        $data['number'] = I("get.number");                 //采购数量
        $data['similar'] = I("get.similar");                 //接受相似或定做
        $data['available'] = I("get.available");     // 1 接受现货  2 接受期货 3 都接受
        $data['time_limit'] = I("get.hour");            //限时找时间
        $data['phone'] = I("get.phone");               //手机号码
        $data['type'] = I("get.type");         //面料还是辅料  1 面料 2 辅料
        $data['phone'] = I("get.phone");         //面料还是辅料  1 面料 2 辅料
        $data['id'] = I("get.id", '');         //修改提交

        $data = $this->agencyService->submitApply($data, sp_get_current_userid());

        $this->ajaxReturn($data, "jsonp");
    }

    //上传代客找版相关图片
    public function upload_find_agency_image() {
//        if (!sp_get_current_userid()) {
//            $this->ajaxReturn(array(
//                "code" => "401",
//                "message" => "请先登录" . sp_get_current_userid()
//            ), "jsonp");
//        }

        $upload = new \Think\Upload();
        $upload->maxSize = 104857600;   // 设置附件上传大小 10M
        $upload->rootPath = "data";
        $upload->savePath = "/upload/agency/";
        $upload->autoSub = false;
        $upload->exts = array (
            'bmp',
            'jpg',
            "png",
            "jpeg",
            "gif"
        ); // 设置附件上传后缀

        $info = $upload->upload();



        if ($info) {
            $info[0] = $info['file'];
            $info[0]['savepath'] = $upload->rootPath . $info[0]['savepath'];

            $this->ajaxReturn(array(
                "code" => "200",
                "message" => "上传成功",
                "data" => array(
                    "path" => $info[0]['savepath'] . $info[0]['savename'],
                    "domain" => C("DOMAIN_URL")
                )
            ), "jsonp");
        } else {
            $this->ajaxReturn(array(
                "code" => "400",
                "message" => "上传失败"
            ), "jsonp");
        }
    }

    //获取用户自己的找版列表
    public function get_my_agency_board() {
        $data['type'] = I("get.type");          //1  面料  2 辅料  3 不限
        $data['status'] = I("get.status");         // 1 待接单 2 已被接单  3 已完成

        $data = $this->agencyService->getMyApplyList(sp_get_current_userid(), $data);

        $this->ajaxReturn($data, "jsonp");
    }

    //取消发布
    public function cancel_agency_apply() {
        $applyId = I("get.id");
        $uid = sp_get_current_userid();

        $data = $this->agencyService->cancelAgencyApply($applyId, $uid);
        $this->ajaxReturn($data, "jsonp");
    }

    //获取最近30天内的聊天记录
    public function get_message_history() {
        $oppositeUid = I("get.id");
        $uid = sp_get_current_userid();

        $data = $this->agencyService->getMessageHistory($oppositeUid, $uid);

        $this->agencyService->setMessageAllRead($uid);

        $this->ajaxReturn($data, "jsonp");
    }

    //获取新信息
    public function get_new_message() {
        $uid = sp_get_current_userid();
        $fromUid = I('get.from_uid');

        $data = $this->agencyService->getMessageNew($uid, $fromUid);
        $this->ajaxReturn($data, "jsonp");
    }

    //获取代客找版内容详情
    public function get_agency_detail() {
        $id = I("get.id");          //订单id

        $result = $this->agencyService->getAgencyDetail($id, sp_get_current_userid());
        $data['apply'] = $result;
        $data['cur_uid'] = sp_get_current_userid();
        $data['is_agency'] = session("agency.uid");

        $this->ajaxReturn($data, "jsonp");
    }

    //版哥提交反馈
    public function submit_agency_response() {
        $id = I("get.id");          //订单id
        $content = $_GET['content'];       //一个数组、包含图片也在这边

        $result = $this->agencyService->submitResponseByAgency($id, sp_get_current_userid(), $content);

        $this->ajaxReturn($result, "jsonp");
    }

    //版哥获取需求列表
    public function get_apply_list() {
        $type = I("get.type");      // 1 面料  2 辅料 3 不限
        $status = I("get.status", 1);      // 1 待接单列表  2 接单中（已接单未完结） 3 已完结

        $data = D("Agency", 'Service')->getAgencyApplyList(sp_get_current_userid(), array(
            "type" => $type,
            "status" => $status
        ));

        $this->ajaxReturn($data, "jsonp");
    }

    //用户获取版哥反馈列表（根据需求）
    public function get_agency_response_list() {
        $id = I("get.id");          //订单id

        $data = $this->agencyService->getAgencyResponseList($id, sp_get_current_userid());
        $this->ajaxReturn($data, "jsonp");
    }

    //版哥获取自己的反馈列表
    public function get_my_response_list() {
        $id = I("get.id");
        $data = $this->agencyService->getMyResponseList($id, sp_get_current_userid());
        $this->ajaxReturn($data, "jsonp");
    }

    //确认、反馈版哥反馈
    public function confirm_agency_response() {
        $id = I("get.id");     //反馈的id
        $status = I("get.status");     //状态  1 是这款  2不是这款

        $data = $this->agencyService->confirmResponse($id, sp_get_current_userid(), $status);
        $this->ajaxReturn($data, "jsonp");
    }

    //版哥接单
    public function accept_apply() {
        $id = I("get.id");

        $data = $this->agencyService->acceptApply($id, sp_get_current_userid());
        $this->ajaxReturn($data, "jsonp");
    }

    //版哥取消订单（转单）
    public function cancel_accepted_apply() {
        $id = I("get.id");

        $data = $this->agencyService->transferApply($id, sp_get_current_userid());
        $this->ajaxReturn($data, "jsonp");
    }

    //发送消息
    public function send_message() {
        $message = $_GET['message'];        //如果是图片 直接传 domain+path 的拼接，不要传对象 类似 http://xxx.com/xxx.jpg
        $type = I("get.type");          //消息类型  1 图片 2  文字
        $toUid = I('get.to_uid');

        $result = D("Agency", 'Service')->sendMessage($type, sp_get_current_userid(), $toUid, $message);

        $this->ajaxReturn($result, "jsonp");
    }

    //用户自助结单
    public function finish_apply() {
        $applyId = I("get.id");
        $uid = sp_get_current_userid();

        $data = $this->agencyService->finishApply($applyId, $uid);
        $this->ajaxReturn($data, "jsonp");
    }

    //自动结单
    public function auto_finish_apply() {
        //系统每个小时检测是否能结单
        //todo
    }

    //获取钱包记录
    public function get_wallet_history() {
        //todo dfdf
        D("Agency", "Service")->getWalletHistory(sp_get_current_userid());
    }

    //获取钱包余额
    public function get_wallet_remain() {
        //todo
    }


    //todo
    //todo 1. 结单自动修改脚本
    public function test1() {
////        session("user",null);//只有前台用户退出
//        $data['type'] = 3;          //1  面料  2 辅料  3 不限
//        $data['status'] = 2;         // 1 待接单 2 已被接单  3 已完成
//        $this->agencyService->getMyApplyList(243, $data);

//        $result = M()->query('select count(c.id) from mlg_agency_response c where c.status = 3 and c.apply_id = 12 limit 0, 1');
//        var_dump($result);

//        var_dump(array($countTmp2, $countTmp1));

        //通知所有版哥
    }
}