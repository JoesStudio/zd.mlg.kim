<?php
namespace Supplier\Controller;

use Common\Controller\SupplierbaseController;

class OperatorController extends SupplierbaseController
{
    protected $_model;

    public function _initialize()
    {
        parent::_initialize();
        $this->_model = D('BizOperator');
        $this->assign('mid', $this->memberid);
    }

    public function index()
    {
        $mid = $this->memberid;
        if (IS_AJAX) {
            if (IS_POST) {
                if (isset($_POST['data'])) {
                    $post = I('post.');
                    $action = I('post.action');
                    $data = array();
                    if ($action == 'create') {
                        $ids = array();
                        foreach ($post['data'] as $key => $row) {
                            $row['member_id'] = $mid;
                            if(empty($row['expire_date'])){
                                $row['expire_date'] = null;
                            }
                            $result = $this->_model->saveOperator($row);
                            if ($result !== false) {
                                $ids[] = $result;
                            } else {
                                $data['result'][$key]['state'] = 'error';
                                $data['result'][$key]['msg'] = "操作失败：".$this->_model->getError();
                            }
                        }
                        $data['status'] = 1;
                    } elseif ($action == 'edit') {
                        $ids = array_keys($post['data']);
                        foreach ($post['data'] as $id => $row) {
                            $row['id'] = $id;
                            unset($row['user_id']);
                            unset($row['member_id']);
                            if(empty($row['expire_date'])){
                                $row['expire_date'] = null;
                            }
                            $result = $this->_model->saveOperator($row);
                            if ($result === false) {
                                $data['result'][$id]['state'] = 'error';
                                $data['result'][$id]['msg'] = "操作失败(ID:$id)：".$this->_model->getError();
                            }
                        }
                        $data['status'] = 1;
                    } elseif ($action == 'remove') {
                        $my_opid = $this->user['operator']['id'];
                        if (array_key_exists($my_opid, $post['data']) && $this->user['operator']['role_id'] == 1) {
                            unset($post['data'][$my_opid]);
                            $data['result'][$my_opid]['state'] = 'error';
                            $data['result'][$my_opid]['msg'] = "操作不允许(ID:$my_opid)：您不能删除当前使用中的运营管理人员！";
                        }
                        $ids = array_keys($post['data']);
                        if (!empty($ids)) {
                            $result = $this->_model->deleteOperator($ids);
                        } else {
                            $result = true;
                        }
                        if ($result === false) {
                            $data['status'] = 0;
                            $data['info'] = $this->_model->getError();
                        } else {
                            $data['status'] = 1;
                        }
                    }
                    if (isset($ids) && !empty($ids) && $action != 'remove') {
                        $data['data'] = $this->_model->getOpersNoPaged(array('id'=>array('IN', $ids)));
                    }
                    $this->ajaxReturn($data);
                } else {
                    $data['status'] = 1;
                    $data['data'] = $this->_model->getOpersNoPaged("member_id:$mid;");
                    $this->ajaxReturn($data);
                }
            }
        } else {
            $roles = D('BizRole')
            ->field('name as label, id as value')
            ->where(array('status'=>1,'id'=>array('gt',1)))->order('id DESC')->select();
            foreach ($roles as $id => $name) {
                $roles[$id]['not_allow'] = D('BizOpauthRule')
                ->alias('rule')
                ->field('rule.*')
                ->join("LEFT JOIN __BIZ_OPAUTH_ACCESS__ access ON access.type=rule.type AND access.rule_name=rule.name AND access.role_id=$id")
                ->where("isNULL(access.rule_name)")
                ->select();
            }
            $this->assign('roles', $roles);
            $this->display();
        }
    }

    public function search_user()
    {
        $keyword = I('get.search/s');
        if (empty($keyword)) {
            $this->error('请输入关键词！');
        }

        $where['user.mobile|user.nickname|user.email'] = array('LIKE', "%$keyword%");
        $where['_string'] = "op.id IS NULL";
        $data = D('UserInfo')
        ->alias('user')
        ->field('user.nickname as label, user.user_id as value')
        ->join('LEFT JOIN __BIZ_OPERATOR__ op ON op.user_id=user.user_id AND op.member_id='.$this->memberid)
        ->where($where)
        ->select();
        $this->ajaxReturn(array('data'=>$data,'status'=>1));
    }
}
