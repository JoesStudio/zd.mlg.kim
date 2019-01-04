<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-04-18
 * Time: 17:37
 */

namespace Account\Model;


use Common\Model\CommonModel;

class WithdrawModel extends CommonModel
{
    protected $tableName = 'biz_withdraw';
    //自动验证
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('member_id', 'require', '传入数据错误！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        array('exchange_amount', 'require', '提现金额错误！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        array('exchange_type', 'require', '提现账号类型错误！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        array('exchange_account', 'require', '提现账号错误！', 1, 'regex', CommonModel:: MODEL_INSERT ),
    );

    protected $_auto = array(
        //array(填充字段,填充内容,填充条件,附加规则)
        array('create_date','mGetDate',CommonModel:: MODEL_INSERT,'function'),
    );

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
        $field = !empty($tag['field']) ? $tag['field'] : "$alias.*,biz.biz_name,
        wa.first_status,wa.first_desc,wa.first_operator_id,wa.first_date,
        wa.second_status,wa.second_desc,wa.second_operator_id,wa.second_date,
        fo.nickname as first_operator,so.nickname as second_operator";
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

        $join1 = "INNER JOIN __BIZ_MEMBER__ biz ON biz.id=$alias.member_id";
        $join2 = "LEFT JOIN __BIZ_WITHDRAW_AUDIT__ wa ON wa.withdraw_id=$alias.id";
        $join3 = "LEFT JOIN __USERS__ fo ON fo.id=wa.first_operator_id";
        $join4 = "LEFT JOIN __USERS__ so ON so.id=wa.second_operator_id";

        $data['total'] = $this->alias($alias)->where($where)->count();

        $this->alias($alias)->field($field)->join($join1)->join($join2)
            ->join($join3)->join($join4)->where($where)->order($order);
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

    public function getRow($id){
        $alias = 'main';
        if(is_array($id)){
            $where = $id;
            $id = $where["$alias.id"];
        }else{
            $where["$alias.id"] = $id;
        }
        $field = "$alias.*,biz.biz_name,
        wa.first_status,wa.first_desc,wa.first_operator_id,wa.first_date,
        wa.second_status,wa.second_desc,wa.second_operator_id,wa.second_date,
        fo.nickname as first_operator,so.nickname as second_operator";
        $join1 = "INNER JOIN __BIZ_MEMBER__ biz ON biz.id=$alias.member_id";
        $join2 = "LEFT JOIN __BIZ_WITHDRAW_AUDIT__ wa ON wa.withdraw_id=$alias.id";
        $join3 = "LEFT JOIN __USERS__ fo ON fo.id=wa.first_operator_id";
        $join4 = "LEFT JOIN __USERS__ so ON so.id=wa.second_operator_id";
        return $this->alias($alias)->field($field)->join($join1)->join($join2)
            ->join($join3)->join($join4)->where($where)->find();
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

    public function withdraw($member_id,$exchange,$action_uid,$remark=''){
        $data = array(
            'member_id' => $member_id,
            'exchange_amount'   => $exchange['amount'],
            'exchange_type'     => $exchange['type'],
            'exchange_account'  => $exchange['account'],
            'remark'    => $remark,
            'receipt_status'    => 0,
            'create_userid'     => $action_uid,
            'create_date'       => date('Y-m-d H:i:s'),
        );
        $withdrwaId =  $this->saveRow($data);

        if($withdrwaId){
            D('Account/Account')->block($member_id,$exchange['amount']);//冻结资金
            $data = array(
                'withdraw_id'   => $withdrwaId,
                'member_id'     => $member_id,
            );
            M('BizWithdrawAudit')->add($data);
        }
        return $withdrwaId;
    }

    //审批
    public function audit($id,$status,$remark='',$uid){
        if(!in_array($status,array(1,2))){
            $this->error = '传入数据错误';
            return false;
        }
        if($status == 2 && $remark == ''){
            $this->error = '请注明操作原因';
            return false;
        }

        $row = $this->getRow($id);

        //$auditModel = M('BizWithdrawAudit');
        //$audit = $auditModel->where("withdraw_id=$id")->find();
        if(!$row){
            $this->error = '传入数据错误';
            return false;
        }

        if($row['first_status'] == 2){
            $this->error = '该提现一审已被驳回，不能继续审核';
            return false;
        }

        if($row['first_status'] > 0 && $row['second_status'] > 0){
            $this->error = '该提现已经审核完毕，请勿重复审核！';
            return false;
        }

        if($row['first_status'] == 0){
            $data = array(
                'id'    => $row['id'],
                'first_status'  => $status,
                'first_desc'    => $remark,
                'first_operator_id' => $uid,
                'first_date'    => date('Y-m-d H:i:s'),
            );
        }else{
            $data = array(
                'id'    => $row['id'],
                'second_status'  => $status,
                'second_desc'    => $remark,
                'second_operator_id' => $uid,
                'second_date'    => date('Y-m-d H:i:s'),
            );
        }

        //保存审核信息
        $auditModel = M('BizWithdrawAudit');
        $result = $auditModel->save($data);
        if($result === false){
            $this->error = $auditModel->getDbError();
            return false;
        }

        //解冻资金
        if($status == 2){
            $acModel = D('Account/Account');
            $result = $acModel->restoreFromBlock($row['member_id'],$row['exchange_amount']);
            if($result === false){
                $this->error('解冻资金失败：'.$acModel->getError());
            }
        }
        return $result;
    }

    //转账
    public function transfer($id){
        $row = $this->getRow($id);
        if(empty($row)){
            $this->error = '传入数据错误';
            return false;
        }
        if($row['first_status'] != 1 || $row['second_status'] != 1){
            $this->error = '当前审批状态不允许打款';
            return false;
        }
        if($row['receipt_status'] == 1){
            $this->error = '该提现已经打过款了';
            return false;
        }

        //修改入款状态
        $result = $this->receipt($id);
        if($result === false){
            return false;
        }

        //记录资金流动
        $tallyModel = D('Account/Tally');
        $result = $tallyModel->tally($row['member_id'],$row['exchange_amount'],'WITHDRAW');
        if($result === false){
            $this->error = $tallyModel->getError();
        }
        return $result;
    }

    //入款
    function receipt($id,$desc=''){
        $data = array(
            'id'    => $id,
            'receipt_status'    => 1,
            'receipt_desc'      => $desc,
            'receipt_date'      => date('Y-m-d H:i:s'),
        );
        $result = $this->saveRow($data);
        return $result;
    }

}