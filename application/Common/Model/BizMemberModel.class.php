<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-10
 * Time: 12:30
 */

namespace Common\Model;


class BizMemberModel extends CommonModel
{
    public $statuses = array(
        '1' => '启用',
        '0' => '停用',
    );

    //自动验证
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        //array('type', 'require', '请选择类型！', 0, 'regex', CommonModel:: MODEL_INSERT ),
        //array('area', 'require', '请选择所在的地区！', 0, 'regex', CommonModel:: MODEL_BOTH ),
    );

    //自动完成
    protected $_auto = array(
        //array(填充字段,填充内容,填充条件,附加规则)
        //array('status','1',CommonModel:: MODEL_INSERT),
        //array('operator_id','mGetOperatorId',CommonModel:: MODEL_BOTH,'function'),
        //array('operator','mGetOperator',CommonModel:: MODEL_BOTH,'function'),
        array('create_date','mGetDate',CommonModel:: MODEL_INSERT,'function'),
        array('modify_date','mGetDate',CommonModel:: MODEL_UPDATE,'function'),
    );

    public function getMemberByOpAdmin($user_id)
    {
        $role_id = 1;
        $ops = D('BizOperator')
        ->field('op.*')
        ->alias('op')
        ->join('__BIZ_ROLE_OP__ rop ON rop.op_id=op.id')
        ->join('__BIZ_ROLE__ role ON role.id=rop.role_id')
        ->where("op.user_id=$user_id AND role.id=$role_id")
        ->select();
        $op = reset($ops);
        return $this->getMember($op['member_id']);
    }

    public function getMemberByOpUser($user_id)
    {
        $ops = D('BizOperator')
        ->field('op.*')
        ->alias('op')
        ->join('__BIZ_ROLE_OP__ rop ON rop.op_id=op.id')
        ->join('__BIZ_ROLE__ role ON role.id=rop.role_id')
        ->where("op.user_id=$user_id")
        ->select();
        $op = reset($ops);
        return $this->getMember($op['member_id']);
    }

    function getMember($id){
        if(is_array($id)){
            $where = $id;
        }else{
            $where['id'] = $id;
        }
        $data = $this->where($where)->find();
        if($data){
            $id = $data['id'];
            if(empty($data['biz_code'])){
                $data['biz_code'] = md5($data['create_date'] .' '.$id);
                $this->where(array('id'=>$id))->setField('biz_code',$data['biz_code']);
            }
            $data['contact'] = D('BizContact')->getContactByBm($id);
            $data['auth'] = D('BizAuth')->getAuthByBm($id);

            $account_model = D('BizAccount');
            $data['account'] = $account_model->getAccountByBm($id);
            if(empty($data['account'])){
                $account_model->saveAccount(array('member_id'=>$id));
                $data['account'] = $account_model->getAccountByBm($id);
            }

            $sms_model = D('BizSms');
            $data['sms'] = $sms_model->getSmsByBm($id);
            if(empty($data['sms'])){
                $sms_model->saveSms(array('member_id'=>$id));
                $data['sms'] = $sms_model->getSmsByBm($id);
            }
        }
        return $data;
    }

    function members($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
        }

        $field = !empty($tag['field']) ? $tag['field'] : '*';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'id DESC';

        if(isset($tag['_string'])){
                $where['_string'] = $tag['_string'];
         }

        $data['total'] = $this->where($where)->count();

        $this->field($field)->where($where)->order($order);

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

        $data['data'] = array();
        $ids = array();
        foreach($rs as $row){
            array_push($ids, $row['id']);
            $data['data'][$row['id']] = $row;
        }
        if(!empty($ids)){
            $where = array('member_id'=>array('IN',$ids));
            //$auth = D('BizAuth')->where($where)->select();
            $auth = D('BizAuth')->getAuthsNoPaged($where);
            $contact = M('BizContact')->where($where)->select();
            foreach($auth as $row){
                $data['data'][$row['member_id']]['auth'] = $row;
            }
            foreach($contact as $row){
                $data['data'][$row['member_id']]['contact'] = $row;
            }
        }

        return $data;
    }

    /*
     * 获取未删除的记录
     * @param int $currentPage 当前分页
     * @param in $pageSize 分页记录数
     * @return array
     */
    function getMembersPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        $data = $this->members($tag, $pageSize, $pagetpl, $tplName);
        return $data;
    }

    function getMembersNoPaged($tag=''){
        $data = $this->members($tag);
        return $data['data'];
    }


    function saveMember($data){
        $result = $this->create($data);
        if($result !== false){
            if(isset($this->data[$this->getPk()])){
                $result = $this->save();
            }else{
                $create_date = $this->data['create_date'];
                $result = $this->add();
                if($result){
                    $biz_code = md5("$create_date $result");
                    $this->where(array('id'=>$result))->setField('biz_code',$biz_code);

                    $acData = array(
                        'member_id'     => $result,
                    );
                    D('BizAccount')->saveAccount($acData);
                }
            }
            if($result === false){
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    /*
     * 插入数据
     * @param array $data 要插入的数据
     * @return int
     */
    function addMember($data){
        $result = $this->create($data, self::MODEL_INSERT);
        if($result !== false){
            $result = $this->add();
            if($result !== false){
                //添加联系信息
                if(isset($data['contact'])){
                    $data['contact']['id'] = $data['id'];
                    $contact_model = D('BizContact');
                    $result = $contact_model->addContact($data['contact']);
                    if($result === false){
                        $this->error .= $contact_model->getError();
                    }
                }
                //添加认证信息
                if(isset($data['auth'])){
                    $data['auth']['id'] = $data['id'];
                    $auth_model = D('BizAuth');
                    $result = $auth_model->addAuth($data['auth']);
                    if($result === false){
                        $this->error .= $auth_model->getError();
                    }
                }
            }else{
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    function saveMember2($data=array()){
        if(empty($data)){
            $data = I('post.');
        }

        if(empty($data['id'])){
            $this->error = 'Error Member ID';
            return false;
        }

        $id = $data['id'];
        $count = $this->where(array('id'=>$id))->count();
        $model_type = $count > 0 ? self::MODEL_UPDATE:self::MODEL_INSERT;

        $result = $this->create($data, $model_type);
        if($result !== false){
            $result = $model_type == self::MODEL_INSERT ? $this->add():$this->save();

            //保存联系方式和认证信息
            if($result !== false){
                if(!empty($data['contact'])){
                    $data['contact']['id'] = $id;
                    $contact_model = D('BizContact');
                    $result = $contact_model->saveContact($data['contact']);
                    if($result === false) $this->error .= $contact_model->getError();
                }
                if(!empty($data['auth'])){
                    $data['auth']['id'] = $id;
                    $auth_model = D('BizAuth');
                    $result = $auth_model->saveAuth($data['auth']);
                    if($result === false) $this->error .= $auth_model->getError();
                }
            }else{
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    /*
     * 更新数据
     * @param array $data 要插入的数据
     * @return int
     */
    function updateMember($data){
        $result = $this->create($data);
        if($result !== false){
            $result = $this->save();
            if($result !== false){
                //更新联系信息
                if(isset($data['contact'])){
                    $data['contact']['id'] = $data['id'];
                    $contact_model = D('BizContact');
                    $result = $contact_model->updateContact($data['contact']);
                    if($result === false) $this->error .= $contact_model->getError();
                }
                //更新认证信息
                if(isset($data['auth'])){
                    $data['auth']['id'] = $data['id'];
                    $auth_model = D('BizAuth');
                    $result = $auth_model->updateAuth($data['auth']);
                    if($result === false) $this->error .= $auth_model->getError();
                }
            }else{
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    function getAuthStatus($id){
        return $this->where(array('id'=>$id))->getField('authenticated');
    }

}