<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-04-13
 * Time: 16:02
 */

namespace Account\Model;


use Common\Model\CommonModel;

class AccountModel extends CommonModel
{
    protected $tableName = 'biz_account';

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

    public function getAccount($id){
        if(is_array($id)){
            $where = $id;
            $id = $where['member_id'];
        }else{
            $where['member_id'] = $id;
        }

        $alias = 'main';

        $field = "$alias.*,biz.biz_name,biz.biz_logo,cu.nickname as create_user,mu.nickname as modify_user";

        $join1 = "INNER JOIN __BIZ_MEMBER__ biz ON biz.id=$alias.member_id";
        $join2 = "LEFT JOIN __USER_INFO__ cu ON cu.user_id=$alias.create_userid";
        $join3 = "LEFT JOIN __USER_INFO__ mu ON mu.user_id=$alias.modify_userid";

        foreach ($where as $key => $value) {
            if (strpos($key, '.') === false) {
                $where["$alias.$key"] = $value;
                unset($where[$key]);
            }
        }

        $data = $this->alias($alias)->field($field)
            ->join($join1)->join($join2)->join($join3)
            ->where($where)->find();

        return $data;
    }

    //直接从可用资金调整金额，需记录资金流动
    //可用操作：面料商充值、面料商支付色卡订单、取消已支付的米样订单、取消已支付的色卡订单
    function adjustMoney($id,$money,$action_uid=0){
        $row = $this->where("member_id=$id")->find();
        if(empty($row)){
            $this->error = '缺少账户';
            return false;
        }
        $data = array(
            'id'    => $row['id'],
            'modify_user'   => $action_uid,
            'modify_date'   => date('Y-m-d H:i:s'),
        );
        if($money > 0){
            $data['amount_current'] = $row['amount_current'] + abs($money);
            $data['amount_credit_sum'] = $row['amount_credit_sum'] + abs($money);
        }else{
            $data['amount_current'] = $row['amount_current'] - abs($money);
            $data['amount_debit_sum'] += $row['amount_debit_sum'] + abs($money);
        }

        return $this->saveRow($data);
    }

    //冻结可用资金
    //可用操作：面料商申请提现
    function block($id,$money,$action_uid=0){
        $row = $this->where("member_id=$id")->find();
        if(empty($row)){
            $this->error = '缺少账户';
            return false;
        }
        $data = array(
            'id'    => $row['id'],
            'modify_user'   => $action_uid,
            'modify_date'   => date('Y-m-d H:i:s'),
        );
        if($money <= 0){
            $this->error = '非法金额数值';
            return false;
        }
        $data['amount_current'] = $row['amount_current'] - $money;  //可用资金减少
        $data['amount_block'] = $row['amount_block'] + $money;  //冻结资金增加

        return $this->saveRow($data);
    }

    //冻结资金收到款
    //可用操作：面料商收到已付款的订单，管理员后台收到已付款的色卡订单
    function creditToBlock($id,$money,$action_uid=0){
        $row = $this->where("member_id=$id")->find();
        if(empty($row)){
            $this->error = '缺少账户';
            return false;
        }
        $data = array(
            'id'    => $row['id'],
            'modify_user'   => $action_uid,
            'modify_date'   => date('Y-m-d H:i:s'),
        );
        if($money <= 0){
            $this->error = '非法金额数值';
            return false;
        }
        $data['amount_block'] = $row['amount_block'] + $money;  //冻结资金增加
        return $this->saveRow($data);
    }

    //从冻结资金还原
    //可用操作：管理员驳回提现申请、完成米样订单、取消已支付色卡订单
    function restoreFromBlock($member_id,$money,$action_uid=0){
        $row = $this->where("member_id=$member_id")->find();
        if(empty($row)){
            $this->error = '缺少账户';
            return false;
        }
        $data = array(
            'id'    => $row['id'],
            'modify_user'   => $action_uid,
            'modify_date'   => date('Y-m-d H:i:s'),
        );
        if($money <= 0){
            $this->error = '非法金额数值';
            return false;
        }
        $data['amount_current'] = $row['amount_current'] + $money;  //可用资金减少
        $data['amount_block'] = $row['amount_block'] - $money;  //冻结资金增加

        return $this->saveRow($data);
    }

    //从冻结资金扣除，需记录资金流动
    //可用操作：管理员通过提现审批并打款后、取消已支付米样订单、取消已支付的色卡订单
    function debitFromBlock($id,$money,$action_uid=0){
        $row = $this->where("member_id=$id")->find();
        if(empty($row)){
            $this->error = '缺少账户';
            return false;
        }
        $data = array(
            'id'    => $row['id'],
            'modify_user'   => $action_uid,
            'modify_date'   => date('Y-m-d H:i:s'),
        );
        if($money <= 0){
            $this->error = '非法金额数值';
            return false;
        }
        $data['amount_block'] = $row['amount_block'] - $money;  //从冻结资金扣除
        $data['amount_debit_sum'] += $row['amount_debit_sum'] + $money; //增加支出总额
        return $this->saveRow($data);
    }

    function saveRow($data){
        if(!isset($this->data[$this->getPk()])){
            $this->error = '传入数据错误';
            return false;
        }
        $result = $this->create($data);
        if($result !== false){
            $result = $this->save();
            if($result === false){
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }
}