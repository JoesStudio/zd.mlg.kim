<?php
namespace Common\Model;
use Common\Model\CommonModel;
class UsersModel extends CommonModel
{
    public $types = array(
        '10'    => '管理员',
        '20'    => '会员',
    );
    
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('user_login', 'require', '用户名称不能为空！', 1, 'regex', CommonModel:: MODEL_INSERT  ),
        array('user_pass', 'require', '密码不能为空！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        array('user_login', 'require', '用户名称不能为空！', 0, 'regex', CommonModel:: MODEL_UPDATE  ),
        array('user_pass', 'require', '密码不能为空！', 0, 'regex', CommonModel:: MODEL_UPDATE  ),
        array('user_login','','用户名已经存在！',0,'unique',CommonModel:: MODEL_BOTH ), // 验证user_login字段是否唯一
        //array('mobile','require','请填写手机号！',0,'regex',CommonModel:: MODEL_BOTH ), // 验证mobile字段是否唯一
        //array('mobile','','手机号已经存在！',0,'unique',CommonModel:: MODEL_BOTH ), // 验证mobile字段是否唯一
        //array('user_email','require','邮箱不能为空！',0,'regex',CommonModel:: MODEL_BOTH ), // 验证user_email字段是否唯一
        //array('user_email','','邮箱帐号已经存在！',0,'unique',CommonModel:: MODEL_BOTH ), // 验证user_email字段是否唯一
        //array('user_email','email','邮箱格式不正确！',0,'',CommonModel:: MODEL_BOTH ), // 验证user_email字段格式是否正确
    );
    
    protected $_auto = array(
        array('create_time','mGetDate',CommonModel:: MODEL_INSERT,'callback'),
        array('modify_type','mGetDate',CommonModel:: MODEL_UPDATE,'callback'),
        array('birthday','',CommonModel::MODEL_UPDATE,'ignore')
    );

    /*
     * 获取会员表
     * @param int $currentPage 当前分页
     * @param in $pageSize 分页记录数
     * @return array
     */
    function getUsers($where=array(), $currentPage=null, $pageSize=20){
        $data['total'] = $this->where($where)->count();
        $this->where($where);
        if(!is_null($currentPage)){
            $data['currentPage'] = $currentPage;
            $data['pageSize'] = $pageSize;
            $this->page($currentPage, $pageSize);
        }
        $data['data'] = $this->select();
        return $data;
    }

    function users($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);

            if(isset($tag['user_status'])){
                $where['user_status'] = $tag['user_status'];
            }
        }

        $field = !empty($tag['field']) ? $tag['field'] : '*';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'id DESC';

        $where['user_type'] = array('BETWEEN','20,29');


        $data['total'] = $this->where($where)->count();

        $this->field($field)->where($where)->order($order);

        if(empty($pageSize)){
            $this->limit($limit);
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
        }

        $rs = $this->select();

        $data['data'] = array();
        foreach($rs as $row){
            $data['data'][$row['id']] = $row;
        }

        return $data;
    }

    function getUsersPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->users($tag, $pageSize, $pagetpl, $tplName);
    }

    function getUsersNoPaged($tag=''){
        $data = $this->users($tag);
        return $data['data'];
    }

    function getNormalUsers($where=array(), $currentPage=null, $pageSize=20){
        $map['user_type'] = 20;
        $where = array_merge($where, $map);
        return $this->getUsers($where, $currentPage, $pageSize);
    }

    function getAdmins($where=array(), $currentPage=null, $pageSize=20){
        $map['user_type'] = 10;
        $where = array_merge($where, $map);
        return $this->getUsers($where, $currentPage, $pageSize);
    }

    function saveUser($data){
        if(isset($data['user_pass'])){
            $data['user_pass'] = sp_password($data['user_pass']);
        }
        $result = $this->create($data);
        if($result !== false){
            if(isset($this->data[$this->getPk()])){
                $result = $this->save();
            }else{
                $create_time = $this->data['create_time'];
                $result = $this->add();
                if($result){
                    $user_code = md5("$create_time $result");
                    $result2 = $this->where(array('id'=>$result))->setField('user_code', $user_code);
                    if($result2 === false){
                        $this->error = '生成用户代码失败！';
                        return false;
                    }
                }
            }
            if($result === false){
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    function addUser($data){
        $result = $this->create($data);
        if($result !== false){
            $create_time = $this->data['create_time'];
            $result = $this->add();
            if($result === false){
                $this->error = $this->getDbError();
            }
            $user_code = md5("$create_time $result");
            $this->where(array('id'=>$result))->setField($user_code);
        }
        return $result;
    }

    function updateUser($data){
        $result = $this->create($data);
        if($result !== false){
            $result = $this->save();
            if($result === false){
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    function getUser($id){
        if(is_array($id)){
            $where = $id;
        }else{
            $where['id'] = $id;
        }
        $data = $this->where($where)->find();
        if(!empty($data)){
            $id = $data['id'];
            if(empty($data['user_code'])){
                $data['user_code'] = md5($data['create_time'] .' '.$data['id']);
                $this->where(array('id'=>$data['id']))->setField('user_code',$data['user_code']);
            }


            $info_model = D('UserInfo');
            $data['userinfo'] = $info_model->getInfo(array('user_id'=>$id));
            if(empty($data['userinfo'])){
                $info = array(
                    'user_id'   => $data['id'],
                );
                $info_id = $info_model->saveInfo($info);
                $data['userinfo'] = $info_model->getInfo($info_id);
            }

            $operators = D('BizOperator')->getOperatorByUser($data['id']);
            if (!empty($operators)) {
                $data['operators'] = $operators;
                $operator = reset($operators);
                $data['operator'] = $operator;
                $member = D('BizMember')->getMember($operator['member_id']);
                if (!empty($member)) {
                    $data['member'] = $member;
                }
            }
        }
        return $data;
    }

    function getUserByOpenId($openid){
        $where['openid'] = $openid;
        $map['_complex'] = $where;
        $map['openid'] = array('neq', '');
        $data = $this->getUser($where);
        return $data;
    }

    public function deleteUser($id)
    {
        $where = array('user_id' => $id);
        M('Cart')->where($where)->delete();
        M('FansApply')->where("(`from`=$id AND `from_type`=1) OR (`to`=$id AND `to_type`=1)")->delete();
        M('CollectGoods')->where($where)->delete();
        M('History')->where($where)->delete();
        M('RoleUser')->where($where)->delete();
        M('UserAddress')->where($where)->delete();
        //Order, Delivery
        //Demand, Msg
        M('BizFans')->where($where)->delete();
        M('BizOperator')->where($where)->delete();
        M('UserInfo')->where($where)->delete();
        $result = $this->where(array('id' => $id))->delete();
        if ($result === false) {
            $this->error = $this->getDbError();
        }
        return $result;
    }
    
    //用于获取时间，格式为2012-02-03 12:12:12,注意,方法不能为private
    function mGetDate() {
        return date('Y-m-d H:i:s');
    }
    
    protected function _before_write(&$data) {
        parent::_before_write($data);
        
        if(!empty($data['user_pass']) && strlen($data['user_pass'])<25){
            $data['user_pass']=sp_password($data['user_pass']);
        }
    }
    
}

