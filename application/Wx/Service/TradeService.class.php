<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-04-24
 * Time: 14:10
 */

namespace Wx\Service;

use Wx\Common\Wechat;
use Wx\Model\TradeModel;
use Order\Model\OrderModel;

class TradeService
{
    protected $wx;
    protected $tradeModel;
    protected $orderModel;
    protected $error = '';

    public function __construct()
    {
        $this->wx = new Wechat();
        $this->tradeModel = new TradeModel();
        $this->orderModel = new OrderModel();
    }

    //根据订单关闭交易单
    function closeOrderTrade($orderId){
        $where['order_id'] = $orderId;
        $where['trade_state'] = 'NOTPAY';
        $nos = $this->tradeModel->where($where)->getField('out_trade_no',true);
        foreach($nos as $outTradeNo){
            $this->closeTrade($outTradeNo);
        }
        return true;
    }

    //根据id关闭交易单
    function closeTradeById($tradeId){
        $trade = $this->tradeModel->where("trade_id=$tradeId")->find();
        if (empty($trade)) {
            $this->error = "找不到交易单";
            return false;
        }
        return $this->closeTrade($trade['out_trade_no']);

    }

    //关闭交易单
    function closeTrade($outTradeNo)
    {
        $response = $this->wx->api->wxPayCloseOrder($outTradeNo);


        if ($response['return_code'] == 'FAIL') {
            $this->error = $response['return_msg'];
            return false;
        }

        if ($response['result_code'] == 'FAIL') {
            $this->error = $response['err_code'] . ': ' . $response['err_code_des'];
            return false;
        }

        $result = $this->tradeModel
            ->where("out_trade_no='$outTradeNo'")
            ->setField('trade_state', 'CLOSED');

        if ($result === false) {
            $this->error = $this->tradeModel->getDbError();
            return false;
        }

        return true;
    }

}