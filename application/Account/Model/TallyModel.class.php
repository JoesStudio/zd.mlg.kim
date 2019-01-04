<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-04-13
 * Time: 18:44
 */

namespace Account\Model;


use Common\Model\CommonModel;

class TallyModel extends CommonModel
{
    const TALLY_TYPE_DEBIT = 1;
    const TALLY_TYPE_CREDIT = 2;
    protected $tableName = 'biz_account_tally';
    public $subjects = array(
        'RECHARGE'  => 1,
        'WITHDRAW'  => 2,
        'NEW_ORDER' => 3,
        'PAY_CARDORDER' => 4,
        'NEW_CARDORDER' => 5,
        'GET_REFUND_CARDORDER'  => 6,
        'REFUND_CARDORDER'  => 7,
        'NEW_GROUPORDER'    => 8,
    );
    //自动验证
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('member_id', 'require', '传入数据错误！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        array('tally_money', 'require', '传入数据错误！', 1, 'regex', CommonModel:: MODEL_INSERT ),
    );

    protected $_auto = array(
        //array(填充字段,填充内容,填充条件,附加规则)
        array('tally_date','mGetDate',CommonModel:: MODEL_INSERT,'function'),
    );

    public $accountModel;

    public function __construct()
    {
        parent::__construct();
        $this->accountModel = new AccountModel();
    }

    public function rows($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
            if (isset($tag['member_id'])) {
                $where['member_id'] = $tag['member_id'];
            }
            if (isset($tag['where'])) {
                $where['_string'] = $tag['_string'];
            }
        }

        $alias = 'main';
        $field = !empty($tag['field']) ? $tag['field'] : "$alias.*";
        $order = !empty($tag['order']) ? $tag['order'] : "$alias.create_date DESC";

        foreach ($where as $key => $value) {
            $ignore_fields = array('field', 'order', 'group', 'limit', 'join', '_string', 'where');
            if (in_array($key, $ignore_fields)) {
                unset($where[$key]);
                continue;
            }
            if (strpos($key, '.') === false) {
                $where["$alias.$key"] = $value;
                unset($where[$key]);
            }
        }

        $data['total'] = $this->alias($alias)->where($where)->count();

        $this->alias($alias)->field($field)->where($where)->order($order);
        if(empty($pageSize)){
            if (!empty($tag['limit'])) {
                $this->limit($tag['limit']);
            }
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }
        $rs = $this->select();

        $data['data'] = $rs;
        return $data;
    }

    public function getRowsPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->rows($tag, $pageSize, $pagetpl, $tplName);
    }

    public function getRowsNoPaged($tag=''){
        $data = $this->rows($tag);
        return $data['data'];
    }

    public function saveRow($data){
        $result = $this->create($data);
        if($result !== false){
            $result = isset($this->data[$this->getPk()]) ? $this->save():$this->add();
            if($result === false){
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    //记录资金流动
    public function tally($member_id, $money, $subject, $object_id=0){
        if($money === 0){
            $this->error = '金额不能为0';
            return false;
        }
        switch(strtoupper($subject)){
            case 'RECHARGE':    //充值入账
                $tally_type = self::TALLY_TYPE_CREDIT;
                break;
            case 'WITHDRAW':    //提现打款入账
                $tally_type = self::TALLY_TYPE_DEBIT;
                break;
            case 'NEW_GROUPORDER':
            case 'NEW_ORDER':  //收到已付款的订单
                $tally_type = self::TALLY_TYPE_CREDIT;
                break;
            case 'PAY_CARDORDER':   //余额支付色卡订单
                $tally_type = self::TALLY_TYPE_DEBIT;
                break;
            case 'NEW_CARDORDER':   //后台收到已付款的色卡订单
                $tally_type = self::TALLY_TYPE_CREDIT;
                break;
            case 'REFUND_CARDORDER':    //面料馆色卡订单退款
                $tally_type = self::TALLY_TYPE_DEBIT;
                break;
            case 'GET_REFUND_CARDORDER':    //面料商收到色卡订单退款
                $tally_type = self::TALLY_TYPE_CREDIT;
                break;
            default:
                $this->error = '账单类型错误';
                return false;
                break;
        }
        $tally_subject = $this->subjects[$subject];
        if(!$tally_subject){
            $tally_subject = 0;
        }
        $data = array(
            'member_id'     => $member_id,
            'tally_type'    => $tally_type,
            'tally_money'   => abs($money),
            'tally_subject' => $tally_subject,
            'tally_code'    => $subject,
            'object_id'     => $object_id,
        );
        $result = $this->saveRow($data);
        if($result === false){
            return false;
        }

        //下面是操作账户资金部分
        switch(strtoupper($subject)){
            case 'RECHARGE':    //充值入账
                $result = $this->accountModel->adjustMoney($member_id,$money);
                break;
            case 'WITHDRAW':    //提现打款入账
                $result = $this->accountModel->debitFromBlock($member_id,$money);
                break;
            case 'NEW_GROUPORDER':
            case 'NEW_ORDER':  //收到已付款的订单
                $result = $this->accountModel->creditToBlock($member_id,$money);
                break;
            case 'PAY_CARDORDER':   //余额支付色卡订单
                $result = $this->accountModel->adjustMoney($member_id,-abs($money));
                break;
            case 'NEW_CARDORDER':   //收到已付款的色卡订单
                $result = $this->accountModel->creditToBlock(0,$money);
                break;
            case 'REFUND_CARDORDER':    //面料馆色卡订单退款
                $result = $this->accountModel->adjustMoney(0,-abs($money));
                break;
            case 'GET_REFUND_CARDORDER':    //面料商收到色卡订单退款
                $result = $this->accountModel->adjustMoney($member_id,abs($money));
                break;
            default:
                $result = true;
                break;
        }
        if($result === false){
            $this->error = $this->accountModel->getError();
        }
        return $result;
    }

}