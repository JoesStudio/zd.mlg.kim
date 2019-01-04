<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-04-24
 * Time: 12:13
 */

namespace Account\Controller;


use Common\Controller\SupplierbaseController;
use Order\Model\OrderModel;
use Account\Model\AccountModel;
use Account\Model\TallyModel;
use Wx\Model\TradeModel;
use Wx\Service\TradeService;

class PayController extends SupplierbaseController
{
    protected $accountModel;
    protected $orderModel;
    protected $tallyModel;
    function __construct()
    {
        parent::__construct();
        $this->accountModel = new AccountModel();
        $this->orderModel = new OrderModel();
        $this->tallyModel = new TallyModel();
    }

    function cardorder(){
        if(IS_POST){
            $id = I('post.id/d', 0);
            if($id == 0){
                $this->error('传入数据错误！');
            }
            $payment = M('Payment')->where("pay_code='BALANCE'")->find();
            if(empty($payment)){
                $this->error = '找不到支付方式！';
                return false;
            }
            $result = M('OrderInfo')->save(array(
                'order_id'  => $id,
                'pay_id'    => $payment['pay_id'],
                'pay_name'  => $payment['pay_name'],
            ));
            if($result === false){
                $this->error = '支付失败：服务器繁忙，请稍后重试！';
            }
            $where['order_id'] = $id;
            $where['order_type'] = 4;
            $where['order_trash'] = 0;
            $where['user_id'] = $this->memberid;
            $order = $this->orderModel->where($where)->find();
            if(empty($order)){
                $this->error('找不到订单！');
            }
            if($order['pay_status'] == 2){
                $this->error('该订单已经支付过了！');
            }
            if($order['order_status'] > 1){
                $this->error('该订单是无效的！');
            }
            $account = $this->accountModel->getAccount($this->memberid);
            $orderAmount = $order['order_amount'];
            $accountCurrent = $account['amount_current'];
            if($orderAmount > $accountCurrent){
                $this->error('您的余额不足，请充值后再支付，或者使用微信支付！');
                exit();
            }

            //设置已支付
            $result = $this->orderModel->setPaid($id);
            if($result === false){
                $this->error($this->orderModel->getError());
            }else{
                //关闭微信交易单
                $tradeService = new TradeService();
                $tradeService->closeOrderTrade($id);
                $this->success('支付成功！', leuu('Order/SupplierCard/view',array('id'=>$id)));
            }
        }
    }

}