<?php
/**
 * Created by IntelliJ IDEA.
 * User: lizhug
 * Date: 3/28 0028
 * Time: 15:03
 */
//代客找版

namespace Api\Service;
use Order\Service\TfOrderService;
use Wx\Common\Wechat;


class AgencyService {
    // wechat模块
    protected $wechat;
    // api模块
    protected $api;

    public function __construct() {
        $this->wx = new Wechat(get_wx_configs());
        // api模块 - 包含各种系统主动发起的功能
        $this->api = $this->wx->api;
    }

    protected $MAX_ACCEPT = 1;
    protected $tfOrderService;

    //提交申请
    public function submitApply($data, $uid) {

        if (!$uid) {
            return array(
                "code" => "401",
                "message" => "请先登录"
            );
        }


        if (!$data['description']) {
            return array(
                "code" => "400",
                "message" => "描述不能为空"
            );
        }

        if (!$data['time_limit']) {
            return array(
                "code" => "400",
                "message" => "限时找时间不能为空"
            );
        }
        if (count($data['image']) == 0) {
            return array(
                "code" => "400",
                "message" => "请上传图片"
            );
        }


        //接受现货默认
        if (!$data['available']) {
            $data['available'] = 1;
        }

        //接受定做默认
        if (!$data['similar']) {
            $data['similar'] = 1;
        }

        if (!$data['phone']) {
            $data['phone'] = "";
        }

        if (!$data['type']) {
            $data['type'] = 1;
        }

        if (!$data['sub_type']) {
            $data['sub_type'] = 1;
        }

        //已经提交、属于修改
        if ($data['id']) {
            D("AgencyApply")->where("id = %d", $data['id'])->save(array(
                "description" => $data['description'],
                "phone" => $data['phone'],
                "image" => json_encode($data['image']),
                "is_similar" => $data['similar'],
                "update_time" => date("Y-m-d H:i:s", time()),
                "is_available" => $data['available']
            ));

            return array(
                "code" => "200",
                "message" => "修改成功"
            );
        } else {

            //todo 计算要付款的数额
            $totalFee = 0.01;

//            $priceList = array(
//                3 => array(80, 80),
//                6 => array(60, 60),
//                24 => array(30, 30)
//            );

            if ($data['time_limit'] == 3) {
                $totalPrice = 80;
            } else if ($data['time_limit'] == 6) {
                $totalPrice = 50;
            } else {
                $totalPrice = 30;
            }

//            $totalFee = $totalPrice;
            $totalFee = 0.01;

            $applyId = D("AgencyApply")->add(array(
                "apply_status" => 3,
                "pay_total" => $totalPrice,
                "fee_available" => $totalPrice,
                "description" => $data['description'],
                "phone" => $data['phone'],
                "uid" => $uid,
                "order_id" => 0,
                "type" => $data['type'],
                "sub_type" => $data['sub_type'],
                "image" => json_encode($data['image']),
                "ctime" => date("Y-m-d H:i:s"),
                "update_time" => date("Y-m-d H:i:s", time()),
                "time_limit" => intval($data['time_limit']),
                "is_similar" => $data['similar'],
                "is_available" => $data['available']
            ));

            $this->tfOrderService = new TfOrderService();
            $orderId = $this->tfOrderService->buildAgencyFindEasyOrder(array(
                "uid" => $uid,
                "total_fee" => $totalFee,
                "apply_id" => $applyId
            ));

            $where['id'] = $applyId;
            D("AgencyApply")->where($where)->save(array(
                "order_id" => $orderId
            ));

            return array(
                "code" => "201",
                "data" => array(
                    "apply_id" => $applyId,
                    "order_id" => $orderId
                ),
                "message" => "提交成功，前往支付"
            );
        }
    }

    //标记位已付款并通知
    public function setApplyPayed($id) {
        $data = D("AgencyApply")->where("order_id = %d", $id)->find();
        if ($data && $data['apply_status'] == 3) {
            //变成已支付
            D("AgencyApply")->where("order_id = %d", $id)->save(array(
                "apply_status" => 2,
                "update_time" => date("Y-m-d H:i:s")
            ));
            //通知所有版哥
            $agencyUser = D("AgencyUser")->field("uid")->select();
            foreach ($agencyUser as $value) {
                $openId = D("Users")->where("id = " . $value['uid'])->field("openid")->find();

                if ($openId) {
                    $this->api->sendTemplateMessage(
                        array(
                            'touser' => $openId['openid'],
                            'template_id' => "Vz-Y41EEG0SvdmBbVmH8inrZn8S36GI0-_Y_17z-Caw",
                            "url" => C("NEW_DOMAIN_URL") . "agency/list/1",
                            "topcolor" => "#FF0000",
                            "data" => array(
                                "first" => array(
                                    "value" => "有用户发布了新的找版需求 请及时查看接单\n",
                                    "color" => "#0aa33c"
                                ),
                                "keyword1" => array(
                                    "value" => "代客找版",
                                    "color" => "#0aa33c"
                                ),
                                "keyword2" =>  array(
                                    "value" => date("Y-m-d H:i:s", time()),
                                    "color" => "#000"
                                ),
                                "remark" =>  array(
                                    "value" => "\n>>>点击查看订单",
                                    "color" => "#f00"
                                ),
                            )
                        )
                    );
                }
            }

        }

    }

    //用户获取版哥反馈列表
    public function getAgencyResponseList($id, $uid) {

        if (!$id || !$uid) {
            return array(
                "code" => "400",
                "message" => "请先登录或输入订单 id"
            );
        }

        $where['b.apply_id'] = $id;
        $where['a.uid'] = $uid;

        $data = D("AgencyResponse")->alias("b")
            ->join("LEFT JOIN mlg_agency_apply a ON b.apply_id = a.id")
            ->where($where)
            ->field("
                b.content, 
                b.ctime,
                b.handle_time,
                b.status as response_status,
                b.agency_user_id,
                b.id as response_id,
                b.id as apply_id
            ")->order("b.ctime desc")
            ->select();

        if ($data) {
            return array(
                "code" => "200",
                "data" => $data,
                "message" => "获取成功"
            );
        } else {
            return array(
                "code" => "400",
                "message" => "暂无数据"
            );
        }
    }

    //版哥获取自己的反馈列表
    public function getMyResponseList($id, $uid) {

        if (!$id || !$uid) {
            return array(
                "code" => "400",
                "message" => "请先登录或输入订单 id"
            );
        }

        $where['b.agency_user_id'] = $uid;
        $where['b.apply_id'] = $id;

        $data = D("AgencyResponse")->alias("b")->join("LEFT JOIN mlg_agency_apply a ON a.id = b.apply_id")->where($where)->field("
            b.content,
            b.ctime,
            b.status as response_status,
            b.handle_time,
            a.uid as apply_uid,
            b.apply_id,
            b.id
        ")->order("b.ctime desc")->select();

        if ($data) {
            return array(
                "code" => "200",
                "data" => $data,
                "message" => "获取成功"
            );
        } else {
            return array(
                "code" => "400",
                "message" => "暂无数据"
            );
        }
    }

    //版哥转单（取消订单）
    public function transferApply($id, $uid) {
        if (!$id || !$uid) {
            return array(
                "code" => "400",
                "message" => "请先登录或输入订单 id"
            );
        }

        $whereAccept = array();
        $whereAccept['apply_id'] = $id;
        $whereAccept['agency_user_id'] = $uid;

        if (!D("AgencyAccept")->where($whereAccept)->find()) {
            return array(
                "code" => "400",
                "message" => "您未接受此次订单"
            );
        }

        //已经被采纳的不能转单
        $whereResponse['apply_id'] = $id;
        $whereResponse['agency_user_id'] = $uid;
        $whereResponse['status'] = 1;
        if (D("AgencyResponse")->where($whereResponse)->find()) {
            return array(
                "code" => "400",
                "message" => "此订单已采纳 不能转单"
            );
        }

        D("AgencyAccept")->where($whereAccept)->save(array(
            "transfer_apply" => 2
        ));

        return array(
            "code" => "200",
            "message" => "转单成功"
        );
    }

    //版哥接单
    public function acceptApply($id, $uid) {

        if (!$id || !$uid) {
            return array(
                "code" => "400",
                "message" => "请先登录或输入订单 id"
            );
        }

        //检查当前用户是否是版哥
        $data = D("AgencyUser")->where("uid = %d", $uid)->find();
        if (!$data) {
            return array(
                "code" => "400",
                "message" => "您不是版哥，请先加入"
            );
        }

        //判断当前订单是否还允许被接单
        //状态是可接单 数量没有超出订单可别接的上限 要注意转单的情况 自己没有接过这个单
        $applyData = D("AgencyApply")->where("id = %d", $id)->find();
        if ($applyData['apply_status'] != 2) {
            return array(
                "code" => "400",
                "message" => "订单状态不可接单"
            );
        }

        $whereAccept = array();
        $whereAccept['apply_id'] = $id;
        $whereAccept['transfer_apply'] = array("NOT IN", array(2, 3));       //3代表已经转单完成
        if (D("AgencyAccept")->where($whereAccept)->count() >= $this->MAX_ACCEPT) {     //现在没个订单最多接收1次
            return array(
                "code" => "400",
                "message" => "该订单已经达到接单上限"
            );
        }

        $whereAccept = array();
        $whereAccept['apply_id'] = $id;
        $whereAccept['agency_user_id'] = $uid;
        $whereAccept['transfer_apply'] = array("NEQ", 3);

        if (D("AgencyAccept")->where($whereAccept)->find()) {
            return array(
                "code" => "400",
                "message" => "您已接过此订单，无需重复"
            );
        }

        //接单写入
        D("AgencyAccept")->add(array(
            "transfer_apply" => 1,
            "agency_user_id" => $uid,
            "ctime" => date("Y-m-d H:i:s", time()),
            "apply_id" => $id
        ));

        //如果是转单 则需要把转单的人状态改掉
        $tmpData = D("AgencyAccept")->where("apply_id = %d and transfer_apply = 2", $id)->find();
        if ($tmpData) {
            D("AgencyAccept")->where("id = %d", $tmpData['id'])->save(array(
                "transfer_apply" => 3
            ));
        }

        //通知用于接单
        $openId = D("Users")->where("id = " . $applyData['uid'])->field("openid")->find();
        $this->api->sendTemplateMessage(
            array(
                'touser' => $openId['openid'],
                'template_id' => "Vz-Y41EEG0SvdmBbVmH8inrZn8S36GI0-_Y_17z-Caw",
                "url" => C("NEW_DOMAIN_URL") . "agency/board/2",
                "topcolor" => "#FF0000",
                "data" => array(
                    "first" => array(
                        "value" => "有版哥接受了您的找版订单\n",
                        "color" => "#0aa33c"
                    ),
                    "keyword1" => array(
                        "value" => "代客找版",
                        "color" => "#0aa33c"
                    ),
                    "keyword2" =>  array(
                        "value" => date("Y-m-d H:i:s", time()),
                        "color" => "#000"
                    ),
                    "remark" =>  array(
                        "value" => "\n>>>点击查看版哥的反馈结果",
                        "color" => "#f00"
                    ),
                )
            )
        );

        return array(
            "code" => "200",
            "message" => "接单成功"
        );
    }

    //版哥提交反馈
    public function submitResponseByAgency($id, $uid, $content) {
        //判断 apply_id与此版哥是否有对应关系（是否有权限提交反馈）
        $where['apply_id'] = $id;
        $where['agency_user_id'] = $uid;
        $where['transfer_apply'] = 1;
        $data = D("AgencyAccept")->where($where)->find();

        if (!$data) {
            return array(
                "code" => "400",
                "message" => "请先接单再提交反馈"
            );
        }

        foreach($content as $value) {
            $value = json_decode($value, true);
            if ($value['name'] && $value['imageList'] && $value['add'] && $value['phone'] && $value['price']) {
                D("AgencyResponse")->add(array(
                    "content" => json_encode($value),
                    "ctime" => date("Y-m-d H:i:s", time()),
                    "status" => 3,
                    "apply_id" => $id,
                    "agency_user_id" => $uid
                ));
            } else {
                return array(
                    "code" => "400",
                    "message" => "部分信息未填写或者图片未上传"
                );
            }
        }

        //通知用户版哥已提交
        $applyWhere['id'] = $id;
        $applyData = D("AgencyApply")->where($applyWhere)->find();
        $openId = D("Users")->where("id = " . $applyData['uid'])->field("openid")->find();

        $this->api->sendTemplateMessage(
            array(
                'touser' => $openId['openid'],
                'template_id' => "Vz-Y41EEG0SvdmBbVmH8inrZn8S36GI0-_Y_17z-Caw",
                "url" => C("NEW_DOMAIN_URL") . "agency/response/" . $id,
                "topcolor" => "#FF0000",
                "data" => array(
                    "first" => array(
                        "value" => "您的找版订单有版哥提交了新的反馈\n",
                        "color" => "#0aa33c"
                    ),
                    "keyword1" => array(
                        "value" => "代客找版",
                        "color" => "#0aa33c"
                    ),
                    "keyword2" =>  array(
                        "value" => date("Y-m-d H:i:s", time()),
                        "color" => "#000"
                    ),
                    "remark" =>  array(
                        "value" => "\n>>>请点击及时查看并处理版哥的反馈",
                        "color" => "#f00"
                    ),
                )
            )
        );


        return array(
            "code" => "200",
            "message" => "处理成功"
        );
    }

    //是这款\不是这款，用户处理版哥的反馈内容
    public function confirmResponse($id, $uid, $status) {
        if (!$id || !$uid) {
            return array(
                "code" => "400",
                "message" => "请先登录或输入反馈 id"
            );
        }

        if (!$status) {
            return array(
                "code" => "400",
                "message" => "请选择反馈状态"
            );
        }

        //判断当前用户是否有权限来处理
        $data = D("AgencyResponse")->where("id = %d", $id)->find();
        $dataApply = D("AgencyApply")->where("id = %d", $data['apply_id'])->find();
        if ($dataApply['uid'] != $uid) {
            return array(
                "code" => "400",
                "message" => "您没有操作此订单的权限"
            );
        }

        //对于是这款的情况，需要检查余额是否充足，用来扣除给版哥
        if ($status == 1) {
            //扣款
            if (!$dataApply['fee_available']) {
                return array(
                    "code" => "401",
                    "message" => "余额不足，请充值后再确认此款信息"
                );
            }

            //自动变成已完成
            D("AgencyApply")->where("id = %d", $data['apply_id'])->save(array(
                "fee_available" => 0,
                "status" => 1
            ));

            D("AgencyResponse")->where("agency_user_id = %d and status <> 2", $data['agency_user_id'])->save(array(
                "status" => 1,
                "handle_time" => date("Y-m-d H:i:s", time())
            ));

            //版哥钱包增加
            D("AgencyUser")->where("uid = %d", $data['agency_user_id'])->save(array(
                "wallet" => $dataApply['fee_available']
            ));
        }

        $openId = D("Users")->where("id = " . $data['agency_user_id'])->field("openid")->find();
        $this->api->sendTemplateMessage(
            array(
                'touser' => $openId['openid'],
                'template_id' => "Vz-Y41EEG0SvdmBbVmH8inrZn8S36GI0-_Y_17z-Caw",
                "url" => C("NEW_DOMAIN_URL") . "agency/response_agency/" . $data['apply_id'],
                "topcolor" => "#FF0000",
                "data" => array(
                    "first" => array(
                        "value" => "用户对您的信息提交了反馈\n",
                        "color" => "#0aa33c"
                    ),
                    "keyword1" => array(
                        "value" => "代客找版",
                        "color" => "#0aa33c"
                    ),
                    "keyword2" =>  array(
                        "value" => date("Y-m-d H:i:s", time()),
                        "color" => "#000"
                    ),
                    "remark" =>  array(
                        "value" => "\n>>>点击查看用户的反馈结果",
                        "color" => "#f00"
                    ),
                )
            )
        );

        $where['id'] = $id;
        D("AgencyResponse")->where($where)->save(array(
            "status" => $status,
            "handle_time" => date("Y-m-d H:i:s", time())
        ));

        return array(
            "code" => "200",
            "message" => "处理成功"
        );
    }

    //版哥获取客户需求列表
    public function getAgencyApplyList($uid, $data) {
        //获取对应type的信息
        if (!$uid) {
            return array(
                "code" => "400",
                "message" => "请先登录"
            );
        }

        if (!$data['status']) {
            return array(
                "code" => "400",
                "message" => "请填写状态"
            );
        }

        if (!session("agency.uid")) {
            return array(
                "code" => "400",
                "message" => "您不是版哥没有权限"
            );
        }

        if ($data['status'] == 1) {
            $where['b.apply_status'] = 2;       //可接单 接单的人数不超过设定的值
            $where['_string'] = "(select count(a.id) from mlg_agency_accept a where a.apply_id = b.id and a.transfer_apply = 1) < " . $this->MAX_ACCEPT;
        } else if ($data['status'] == 2) {
            $where['b.apply_status'] = 2;       //接单中
            $where['c.agency_user_id'] = session("agency.uid");
            $where['c.transfer_apply'] = array("NEQ", 3);            //没有转单
        } else if ($data['status'] == 3) {
            $where['b.apply_status'] = 3;       //可接单
            $where['c.agency_user_id'] = session("agency.uid");
            $where['c.transfer_apply'] = array("NEQ", 3);            //没有转单
        }

        $data = D("AgencyApply")->alias("b")->join("LEFT JOIN mlg_agency_accept c ON c.apply_id = b.id")->where($where)->order("b.ctime desc")->field("
        b.description,
        b.apply_status,
        b.pay_total,
        b.fee_available,
        b.description,
        b.id,
        b.image,
        b.uid,
        b.ctime,
        b.update_time,
        b.type,
        b.sub_type,
        b.time_limit,
        b.is_similar,
        b.is_available,
        (select count(c.id) from mlg_agency_response c where c.apply_id = b.id and c.agency_user_id = '$uid' and status = 1) as confirmed_response,
        (select count(c.id) from mlg_agency_response c where c.apply_id = b.id and c.agency_user_id = '$uid' and status = 2) as rejected_response,
        (select count(c.id) from mlg_agency_response c where c.apply_id = b.id and c.agency_user_id = '$uid' and status = 3) as unconfirmed_response
        ")->select();

        return array(
            "code" => "200",
            "message" => "请求成功",
            "data" => $data
        );
    }

    //获取我发布的需求列表
    public function getMyApplyList($uid, $data) {

        if (!$uid) {
            return array(
                "code" => "400",
                "message" => "请先登录"
            );
        }

        $where = array();
        $where['b.uid'] = $uid;
        $where['b.apply_status'] = $data['status'];
        if ($data['status'] == 1) {
            $where['b.apply_status'] = array("IN", array(2, 3));
            $where['_string'] = "(select count(e.id) from mlg_agency_accept e where e.apply_id = b.id) = 0";              //接单中状态是2，但是accept的数量大于0  且不是转单中的状态
        } else if ($data['status'] == 2) {
            $where['b.apply_status'] = 2;               //接单中状态是2，但是accept的数量大于0
            $where['_string'] = "(select count(e.id) from mlg_agency_accept e where e.apply_id = b.id) > 0";              //接单中状态是2，但是accept的数量大于0  且不是转单中的状态
        } else if ($data['status'] == 3){       //已完成包含 真的完成了和取消的
            $where['b.apply_status'] = array("IN", array(3, 4));
        }

        $data = D("AgencyApply")->alias("b")->where($where)->order("b.ctime desc")->field("
            b.description,
            b.apply_status,
            b.pay_total,
            b.fee_available,
            b.description,
            b.id,
            b.image,
            b.uid,
            b.ctime,
            b.update_time,
            b.type,
            b.sub_type,
            b.time_limit,
            b.is_similar,
            b.is_available,
            b.order_id,
            (select count(c.id) from mlg_agency_response c where c.status = 3 and c.apply_id = b.id) as new_response,
            (select count(c.id) from mlg_agency_response c where c.apply_id = b.id and c.status = 1) as confirmed_response,
        (select count(c.id) from mlg_agency_response c where c.apply_id = b.id and c.status = 2) as rejected_response,
        (select count(c.id) from mlg_agency_response c where c.apply_id = b.id and c.status = 3) as unconfirmed_response,
            (select d.agency_user_id from mlg_agency_accept d where d.apply_id = b.id limit 0, 1) as agency_user_id
        ")->select();

        return array(
            "code" => "200",
            "message" => "请求成功",
            "data" => $data
        );
    }

    // 用户结单
    public function finishApply($applyId, $uid) {

        if (!$applyId || !$uid) {
            return array(
                "code" => "400",
                "message" => "请先登录或输入订单 id"
            );
        }

        $data = D("AgencyApply")->alias("a")->where("a.id = %d", $applyId)->field("
        a.id,
        a.apply_status,
        a.uid,
        a.pay_total")->find();

        if ($uid != $data['uid']) {
            return array(
                "code" => "400",
                "message" => "您没有权限结单订单"
            );
        }

        D("AgencyApply")->where("id = %d", $applyId)->save(array(
            "apply_status" => 1
        ));

        return array(
            "code" => "200",
            "message" => "结单成功"
        );
    }

    //取消需求发布
    public function cancelAgencyApply($applyId, $uid) {

        if (!$applyId || !$uid) {
            return array(
                "code" => "400",
                "message" => "请先登录或输入订单 id"
            );
        }

        $data = D("AgencyApply")->alias("a")->where("a.id = %d", $applyId)->field("
        a.id,
        a.apply_status,
        a.uid,
        a.pay_total,
        (select count(b.id) from mlg_agency_accept b where b.apply_id = a.id and b.transfer_apply <> 3) as total_accept
        ")->find();

        if ($uid != $data['uid']) {
            return array(
                "code" => "400",
                "message" => "您没有权限取消此订单"
            );
        }

        //已完成和已取消的订单无需取消
        if ($data['apply_status'] == 1 && $data['apply_status'] !== 4) {
            return array(
                "code" => "400",
                "message" => "此订单无需取消操作"
            );
        }

        if ($data['total_accept'] > 0) {
            return array(
                "code" => "400",
                "message" => "改订单正在接单中，无法取消"
            );
        }

        D("AgencyApply")->where("id = %d", $applyId)->save(array(
            "apply_status" => 4
        ));

        return array(
            "code" => "200",
            "message" => "取消成功"
        );
    }

    //订单详情
    public function getAgencyDetail($id, $uid) {
        if (!$id || !$uid) {
            return array(
                "code" => "400",
                "message" => "请先登录或输入订单 id"
            );
        }

        $data = D("AgencyApply")->alias("a")->where("id = %d", $id)->field("
        a.apply_status,
        a.ctime,
        a.description,
        a.id,
        a.image,
        a.is_available,
        a.is_similar,
        a.order_id,
        a.pay_total,
        a.phone,
        a.sub_type,
        a.type,
        a.time_limit,
        a.uid,
        (select b.agency_user_id from mlg_agency_accept b where b.apply_id = a.id limit 0, 1) as agency_user_id,
        (select count(b.agency_user_id) from mlg_agency_accept b where b.apply_id = a.id limit 0, 1) as agency_count
        ")->find();

        $userArray = D("AgencyUser")->getField("uid", true);
        if (!is_array($userArray)) {
            $userArray = array();
        }

        $userArray[] = $data['uid'];

        if (!in_array($uid, $userArray)) {
            return array(
                "code" => "400",
                "message" => "您没有权限查看此详情"
            );
        }

        return array(
            "code" => "200",
            "data" => $data,
            "message" => "请求成功"
        );
    }

    //获取聊天记录
    public function getMessageHistory($oppositeUid, $uid) {
        if (!$oppositeUid || !$uid) {
            return array(
                "code" => "400",
                "message" => "您没有权限获取此项纪录"
            );
        }

        //获取30天内的聊天记录
        $where = array();
        $where['ctime'] = array("BETWEEN", array(date("Y-m-d H:i:s", time() - 3600 * 24 * 30), date("Y-m-d H:i:s", time())));
        $where['_string'] = "(from_uid = '$oppositeUid' and to_uid = '$uid') or (from_uid = '$uid' and to_uid = '$oppositeUid')";

        $data = D("AgencyMessage")->where($where)->order("ctime desc")->select();

        return array(
            "code" => "200",
            "data" => $data,
            "message" => "获取成功"
        );
    }

    //设置消息为已读
    public function setMessageAllRead($uid) {
        $where = array();
        $where['to_uid'] = $uid;
        D("AgencyMessage")->where($where)->save(array(
            "is_read" => 1
        ));
    }

    //获取最新的消息（ajax）暂时不用长连接
    public function getMessageNew($uid, $fromUid) {

        if (!$uid || !$fromUid) {
            return array(
                "code" => "400",
                "message" => "用户id不能为空"
            );
        }

        $where = array();
        $where['to_uid'] = $uid;
        $where['from_uid'] = $fromUid;
        $where['is_read'] = 0;
        $data = D("AgencyMessage")->where($where)->order("ctime desc")->select();

        return array(
            "code" => "200",
            "message" => "获取成功",
            "data" => $data
        );
    }



    //发送消息
    public function sendMessage($type, $fromUid, $toUid, $message) {
        $countTmp1 = D("AgencyApply")->alias("a")->join("mlg_agency_accept b")->where("a.uid = %d and b.agency_user_id = %d and b.transfer_apply <> 3 and a.id = b.apply_id", $fromUid, $toUid)->select();
        $countTmp2 = D("AgencyApply")->alias("a")->join("mlg_agency_accept b")->where("a.uid = %d and b.agency_user_id = %d and b.transfer_apply <> 3 and a.id = b.apply_id", $toUid, $fromUid)->select();

        if (!$countTmp1 && !$countTmp2) {
            return array(
                "code" => "400",
                "message" => "您没有权限发送聊天信息"
            );
        }

        if (!$toUid || !$fromUid) {
            return array(
                "code" => "400",
                "message" => "用户id不能为空"
            );
        }

        D("AgencyMessage")->add(array(
            "from_uid" => $fromUid,
            "to_uid" => $toUid,
            "ctime" => date("Y-m-d H:i:s", time()),
            "is_read" => 0,
            "status" => 1,
            "type" => $type,
            "message" => $message
        ));

        $openId = D("Users")->where("id = " . $toUid)->field("openid")->find();

        $this->api->sendTemplateMessage(
            array(
                'touser' => $openId['openid'],
                'template_id' => "Vz-Y41EEG0SvdmBbVmH8inrZn8S36GI0-_Y_17z-Caw",
                "url" => C("NEW_DOMAIN_URL") . "agency/talking/" . $fromUid,
                "topcolor" => "#FF0000",
                "data" => array(
                    "first" => array(
                        "value" => "有用户通过找版对您留言，请及时查看\n",
                        "color" => "#0aa33c"
                    ),
                    "keyword1" => array(
                        "value" => "代客找版",
                        "color" => "#0aa33c"
                    ),
                    "keyword2" =>  array(
                        "value" => date("Y-m-d H:i:s", time()),
                        "color" => "#000"
                    ),
                    "remark" =>  array(
                        "value" => "\n>>>点击进入会话聊天",
                        "color" => "#f00"
                    ),
                )
            )
        );

        return array(
            "code" => "200",
            "message" => "发送成功"
        );
    }
}