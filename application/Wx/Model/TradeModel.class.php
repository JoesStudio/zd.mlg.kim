<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-01-05
 * Time: 10:55
 */

namespace Wx\Model;


use Common\Model\CommonModel;
use Wx\Common\Wechat;

class TradeModel extends CommonModel
{
    protected $tableName = 'wx_trades';

    function buildOutTradeNo()
    {
        /* 选择一个随机的方案 */
        mt_srand((double)microtime() * 1000000);
        return date('YmdHis') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }


    /*
     * 获取未删除的记录
     * @param int $currentPage 当前分页
     * @param in $pageSize 分页记录数
     * @return array
     */
    function trades($tag = '', $pageSize = 0, $pagetpl = '', $tplName = 'default')
    {
        $where = array();
        if (is_array($tag)) {
            $where = array_merge($where, $tag);
        } else {
            $tag = sp_param_lable($tag);
        }

        $field = !empty($tag['field']) ? $tag['field'] : '*';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'trade_id DESC';

        if (!empty($tag['order_id'])) {
            $where['order_id'] = $tag['order_id'];
        }
        if (!empty($tag['state'])) {
            $where['trade_state'] = $tag['state'];
        }
        if (!empty($tag['type'])) {
            $where['order_type'] = $tag['type'];
        }

        $data['total'] = $this->where($where)->count();

        $this->field($field)->where($where)->order($order);
        if (empty($pageSize)) {
            $this->limit($limit);
        } else {
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }

        $rs = $this->select();

        if (!empty($rs)) {
            $data['data'] = array();
            foreach ($rs as $row) {
                $data['data'][$row['trade_id']] = $row;
            }
        }

        return $data;
    }

    /*
     * 获得分页的订单列表
     */
    function getTradesPaged($tag = '', $pageSize = 20, $pagetpl = '', $tplName = 'default')
    {
        return $this->trades($tag, $pageSize, $pagetpl, $tplName);
    }

    /*
     * 获得不分页的订单表
     */
    function getTradesNoPaged($tag = '')
    {
        $data = $this->trades($tag);
        return $data['data'];
    }

    function getTradeByOrderId($order_id)
    {
        //
    }

    function getUnpaidTrade($order_id, $type = 'JSAPI', $otype = "ORDER")
    {
        $where['order_id'] = $order_id;
        $where['trade_state'] = 'NOTPAY';
        $where['trade_type'] = $type;
        $where['time_start'] = array('ELT', time());
        $where['order_type'] = $otype;
        $where['_string'] = 'IF(time_expire = null,1,time_expire > ' . time() . ')';
        $data = $this->where($where)->find();
        return $data;
    }

    function getTrade($id)
    {
        if (is_array($id)) {
            $where = $id;
        } else {
            $where['trade_id'] = $id;
        }
        $data = $this->where($where)->find();
        return $data;
    }

    function createTrade($data)
    {
        $result = $this->create($data);
        if ($result !== false) {
            $result = $this->add();
            if ($result == false) {
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    function updateTrade($data)
    {
        $result = $this->create($data);
        if ($result !== false) {
            $result = $this->save();
            if ($result == false) {
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    function closeOrderTrades($orderId, $type)
    {
        $rows = $this->getTradesNoPaged("order_id:$orderId;type:$type;");
        if ($rows) {
            foreach ($rows as $row) {
                $this->closeTrade($row['out_trade_no']);
            }
        }
    }

    function closeTrade($out_trade_no)
    {
        $wx = new Wechat();
        $closeorder = $wx->api->wxPayCloseOrder($out_trade_no);


        if ($closeorder['return_code'] == 'FAIL') {
            return array($closeorder['return_msg'], null, $closeorder);
        }

        if ($closeorder['result_code'] == 'FAIL') {
            $err = $closeorder['err_code'] . ': ' . $closeorder['err_code_des'];
            return array($err, null, $closeorder);
        }

        $result = $this->where(array('out_trade_no' => $out_trade_no))->setField('trade_state', 'CLOSED');

        return array($this->getDbError(), $result, $closeorder);
    }
}