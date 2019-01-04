<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-01-17
 * Time: 14:42
 */

namespace Sms\Model;


use Common\Model\CommonModel;

class SmsModel extends CommonModel
{
    protected $tableName = 'sms_log';

    //自动完成
    protected $_auto = array(
        //array(填充字段,填充内容,填充条件,附加规则)
        array('create_time','mGetDate',CommonModel:: MODEL_INSERT,'function'),
    );

    function logSms($data){
        $result = $this->create($data);
        if($result !== false){
            $result = isset($this->data[$this->getPk()]) ? $this->save():$this->add();
            if($result === false){
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    function checkCode($to, $code, $extend=null){
        $where['to'] = $to;
        $where['code'] = $code;
        $where['usage'] = 'CODE';
        $where['status'] = 1;
        $where['used'] = 0;

        $log = $this->where($where)->find();
        if($log){
            if(!empty($log['expire_time']) && strtotime($log['expire_time']) < time()){
                return array(false, 'SMS_CODE_EXPIRED', $log['rec_id']);
            }else{
                //$this->setUsed($log['rec_id']);
                return array(true, null, $log['rec_id']);
            }
        }else{
            return array(false, 'SMS_CODE_NOTFOUND', null);
        }
    }

    function setUsed($id){
        $data = array(
            'rec_id'    => $id,
            'used'      => 1,
            'use_time'  => date('Y-m-d H:i:s'),
        );
        return $this->logSms($data);
    }

}