<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-01-20
 * Time: 18:46
 */

namespace Common\Model;


class BizOperatorModel extends CommonModel
{

    //自动验证
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('member_id', 'require', '请选择面料商！', 0, 'regex', CommonModel:: MODEL_INSERT ),
        array('user_id', 'require', '请选择用户！', 0, 'regex', CommonModel:: MODEL_BOTH ),
        array('user_id', 'check_unquid_user', '这个会员已经是另一个面料商的运营人员了！', 0, 'callback', CommonModel:: MODEL_INSERT ),
        array('role_id', 'require', '请选择角色！', 0, 'regex', CommonModel:: MODEL_BOTH ),
    );

    //自动完成
    protected $_auto = array(
        //array(填充字段,填充内容,填充条件,附加规则)
        array('create_date','mGetDate',CommonModel:: MODEL_INSERT,'function'),
    );

    protected function check_unquid_user($data)
    {
        return D('BizOperator')->where("user_id=$data")->count() > 0 ? false:true;
    }

    public function opers($tag = '', $pageSize = 0, $pagetpl = '', $tplName = 'default')
    {
        $where = array();
        if (is_array($tag)) {
            $where = array_merge($where, $tag);
        } else {
            $tag=sp_param_lable($tag);
            if (!empty($tag['member_id'])) {
                $where['member_id'] = $tag['member_id'];
            }
            if (!empty($tag['_string'])) {
                $where['_string'] = $tag['_string'];
            }
        }

        $alias = 'op';
        $is_expired = 'IF(UNIX_TIMESTAMP() > UNIX_TIMESTAMP(op.expire_date) AND NOT isNULL(op.expire_date) AND role_id > 1,1,0) as is_expired';
        $field = !empty($tag['field']) ? $tag['field'] : "$alias.*,biz.biz_name,user.avatar,user.nickname as user_name,
        role.id as role_id,role.name as role_name,role.status as role_status,role.remark as role_remark,$is_expired";
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : "$alias.id DESC";

        $join1 = "__BIZ_MEMBER__ biz ON biz.id=$alias.member_id";
        $join2 = "__USER_INFO__ user ON user.user_id=$alias.user_id";
        $join3 = "__BIZ_ROLE_OP__ rop ON rop.op_id=$alias.id";
        $join4 = "__BIZ_ROLE__ role ON role.id=rop.role_id";
        
        $except = array('_string', 'field', 'limit', 'order');
        foreach ($where as $key => $value) {
            if (!in_array($key, $except) && strpos($key, '.') === false) {
                $where["$alias.$key"] = $value;
                unset($where[$key]);
            }
        }

        $this->join($join1)->join($join2)->join($join3)->join($join4);
        $data['total'] = $this->alias($alias)->where($where)->count();

        $this->join($join1)->join($join2)->join($join3)->join($join4);
        $this->alias($alias)->field($field)->where($where)->order($order);

        if (empty($pageSize)) {
            if (!empty($tag['limit'])) {
                $this->limit($limit);
            }
        } else {
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
        }

        $data['data'] = $this->select();
        
        $all = D('BizOpauthRule')->field('*,1 as is_allow')->select();
        foreach ($data['data'] as $key => $row) {
            if ($row['role_id'] == 1) {
                $data['data'][$key]['rules'] = $all;
            } else {
                $data['data'][$key]['rules'] = D('BizOpauthRule')
                ->alias('rule')
                ->field('rule.*,IF(isNULL(access.rule_name),0,1) is_allow')
                ->join('LEFT JOIN __BIZ_OPAUTH_ACCESS__ access ON access.type=rule.type AND access.rule_name=rule.name AND access.role_id='.$row['role_id'])
                ->select();
            }
        }

        /*$data['data'] = array();
        $ids = array();
        foreach ($rs as $row) {
            array_push($ids, $row['id']);
            $data['data'][$row['id']] = $row;
        }*/

        return $data;
    }

    /*
     * 获取未删除的记录
     * @param int $currentPage 当前分页
     * @param in $pageSize 分页记录数
     * @return array
     */
    function getOpersPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        $data = $this->opers($tag, $pageSize, $pagetpl, $tplName);
        return $data;
    }

    function getOpersNoPaged($tag=''){
        $data = $this->opers($tag);
        return $data['data'];
    }

    function newOperator($data){
        $result = $this->create($data);
        if($result !== false){
            $result = $this->add();
            if($result === false){
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    public function saveOperator($data)
    {
        $result = $this->create($data);
        if ($result !== false) {
            if (isset($this->data[$this->getPk()])) {
                $result = $this->save();
                if ($result !== false) {
                    D('BizRoleOp')
                    ->where(array('op_id'=>$data[$this->getPk()]))
                    ->setField('role_id', $data['role_id']);
                }
            } else {
                $result = $this->add();
                if ($result !== false) {
                    D('BizRoleOp')->add(array('op_id'=>$result,'role_id'=>$data['role_id']));
                }
            }
            if ($result === false) {
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    public function deleteOperator($ids)
    {
        if (is_array($ids)) {
            $where['id'] = array('IN', $ids);
            $rWhere['op_id'] = array('IN', $ids);
        } else {
            $where['id'] = $ids;
            $rWhere['op_id'] = $ids;
        }
        $result = $this->where($where)->delete();
        if ($result !== false) {
            D('BizRoleOp')->where($rWhere)->delete();
        }
        if ($result === false) {
            $this->error = $this->getDbError();
        }
        return $result;
    }

    public function getOperatorByUser($user_id)
    {
        $where['user_id'] = $user_id;
        $is_expired = 'IF(UNIX_TIMESTAMP() > UNIX_TIMESTAMP(op.expire_date) AND NOT isNULL(op.expire_date) AND UNIX_TIMESTAMP(op.expire_date)<>0 AND rop.role_id > 1,1,0) as is_expired';
        $rs = $this
        ->alias('op')
        ->field("op.*,rop.role_id,$is_expired")
        ->join('__BIZ_ROLE_OP__ rop ON rop.op_id=op.id')
        ->where($where)
        ->select();
        $data = array();
        if (!empty($rs)) {
            foreach ($rs as $row) {
                $data[$row['member_id']] = $row;
            }
        }
        return $data;
    }
}
