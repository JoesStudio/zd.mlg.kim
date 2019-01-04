<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-04-13
 * Time: 18:21
 */

namespace Account\Model;


use Common\Model\CommonModel;

class RechargeModel extends CommonModel
{
    protected $tableName = 'biz_account_recharge';

    //自动验证
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('member_id', 'require', '传入数据错误！', 1, 'regex', CommonModel:: MODEL_INSERT),
        array('recharge_money', 'require', '请填写充值金额！', 1, 'regex', CommonModel:: MODEL_INSERT),
    );

    protected $_auto = array(
        //array(填充字段,填充内容,填充条件,附加规则)
        array('create_date', 'mGetDate', CommonModel:: MODEL_INSERT, 'function'),
        //array('recharge_date','mGetDate',CommonModel:: MODEL_INSERT,'function'),
    );

    public function rows($tag = '', $pageSize = 0, $pagetpl = '', $tplName = 'default')
    {
        $where = array();
        if (is_array($tag)) {
            $where = array_merge($where, $tag);
        } else {
            $tag = sp_param_lable($tag);
            if (isset($tag['member_id'])) {
                $where['member_id'] = $tag['member_id'];
            }
            if (isset($tag['trash'])) {
                $where['recharge_trash'] = $tag['trash'];
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
        if (empty($pageSize)) {
            if (!empty($tag['limit'])) {
                $this->limit($tag['limit']);
            }
        } else {
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }
        $rs = $this->select();

        $data['data'] = $rs;
        return $data;
    }

    public function getRowsPaged($tag = '', $pageSize = 20, $pagetpl = '', $tplName = 'default')
    {
        return $this->rows($tag, $pageSize, $pagetpl, $tplName);
    }

    public function getRowsNoPaged($tag = '')
    {
        $data = $this->rows($tag);
        return $data['data'];
    }

    public function getRow($id)
    {
        if (is_array($id)) {
            $where = $id;
            $id = $where['id'];
        } else {
            $where['id'] = $id;
        }

        return $this->where($where)->find();
    }

    function saveRow($data)
    {
        $result = $this->create($data);
        if ($result !== false) {
            $result = isset($this->data[$this->getPk()]) ? $this->save() : $this->add();
            if ($result === false) {
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    //充值
    public function recharge($member_id, $money, $action_uid = 0, $remark = '')
    {
        $data = array(
            'member_id' => $member_id,
            'recharge_money' => $money,
            'receipt_status' => 0,
            'remark' => $remark,
            'create_userid' => $action_uid,
        );
        return $this->saveRow($data);
    }

    //充值反馈
    public function receipt($id, $status, $desc = '')
    {
        $data = array(
            'id' => $id,
            'receipt_status' => $status,
            'receipt_date' => date('y-m-d H:i:s'),
            'receipt_desc' => $desc,
        );
        $result = $this->saveRow($data);

        //记录资金流动
        if ($result !== false) {
            $recharge = $this->where("id=$id")->find($id);
            if ($recharge) {
                D('Account/Tally')->tally($recharge['member_id'], $recharge['recharge_money'], 'RECHARGE');
            }
        }

        return $result;
    }

    //取消充值
    function cancel($id)
    {
        $row = $this->getRow($id);
        if (empty($row)) {
            $this->error = '该充值记录不存在或已被删除！';
            return false;
        }
        $id = $row['id'];
        if ($row['recharge_trash']) {
            $this->error = '该充值记录不存在或已被删除！';
            return false;
        }
        if ($row['receipt_status']) {
            $this->error = '该充值已经付款了';
            return false;
        }
        D('Wx/Trade')->closeOrderTrades($id, 'RECHARGE');
        return $this->trash($id);
    }

    //删除记录
    function trash($id)
    {
        $data = array(
            'id' => $id,
            'recharge_trash' => 1,
        );
        $result = $this->save($data);
        if ($result === false) {
            $this->error = '删除记录失败！';
        }
        return $result;
    }

}