<?php
namespace Supplier\Controller;

use Common\Controller\AdminbaseController;

class AdminRuleController extends AdminbaseController
{
    protected $_model;

    public function _initialize()
    {
        parent::_initialize();
        $this->_model = M('BizOpauthRule');
    }

    public function index()
    {
        if (IS_AJAX) {
            if (IS_POST) {
                if (isset($_POST['data'])) {
                    $post = I('post.');
                    $action = I('post.action');
                    $data = array();
                    if ($action == 'create') {
                        $ids = array();
                        foreach ($post['data'] as $key => $row) {
                            $row['name'] = strtolower($row['name']);
                            $rs = $this->_model->add($row);
                            if ($rs !== false) {
                                $ids[] = $rs;
                            }
                        }
                        if (!empty($ids)) {
                            $data['data'] = $this->_model->where(array('id' => array('IN', $ids)))->select();
                        }
                    } elseif ($action == 'edit') {
                        $ids = array_keys($post['data']);
                        foreach ($post['data'] as $id => $row) {
                            $row['id'] = $id;
                            $row['name'] = strtolower($row['name']);
                            $result = $this->_model->save($row);
                        }
                        $data['data'] = $this->_model->where(array('id' => array('IN', $ids)))->select();
                    } elseif ($action == 'remove') {
                        $ids = array_keys($post['data']);
                        $this->_model->where(array('id' => array('IN', $ids)))->delete();
                        $data['data'] = $this->_model->where(array('id' => array('IN', $ids)))->select();
                    }
                    $data['status'] = 1;
                    $this->ajaxReturn($data);
                } else {
                    $data['status'] = 1;
                    $data['data'] = $this->_model->select();
                    $this->ajaxReturn($data);
                }
            }
        }
        $this->display();
    }
}
