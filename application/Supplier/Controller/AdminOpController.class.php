<?php
namespace Supplier\Controller;

use Common\Controller\AdminbaseController;

class AdminOpController extends AdminbaseController
{
    protected $_model;
    protected $mid;

    public function _initialize()
    {
        parent::_initialize();
        $this->_model = D('BizOperator');
        $this->mid = I('get.mid/d', 0);
        $this->assign('mid', $this->mid);
    }

    public function index()
    {
        $mid = $this->mid;
        if (IS_AJAX) {
            if (IS_POST) {
                if (isset($_POST['data'])) {
                    $post = I('post.');
                    $action = I('post.action');
                    $data = array();
                    if ($action == 'create') {
                        $ids = array();
                        foreach ($post['data'] as $key => $row) {
                            if(empty($row['expire_date'])){
                                $row['expire_date'] = null;
                            }
                            $rs = $this->_model->saveOperator($row);
                            if ($rs !== false) {
                                $ids[] = $rs;
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
                        $ids = array_keys($post['data']);
                        $result = $this->_model->deleteOperator($ids);
                        if ($result === false) {
                            $data['status'] = 0;
                            $data['info'] = $this->_model->getError();
                        } else {
                            $data['status'] = 1;
                        }
                    }
                    if (isset($ids) && !empty($ids)) {
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
        ->join('LEFT JOIN __BIZ_OPERATOR__ op ON op.user_id=user.user_id AND op.member_id='.$this->mid)
        ->where($where)
        ->select();
        $this->ajaxReturn(array('data'=>$data,'status'=>1));
    }
}
