<?php
namespace Fans\Controller;

use Common\Controller\MemberbaseController;

class IndexController extends MemberbaseController
{
    protected $fans_model;

    public function _initialize()
    {
        parent::_initialize();
        $this->_model = D('Fans/Suppliers');
    }

    public function index()
    {
        $where = array();
        $where['fans.user_id'] = $this->userid;

        $list = $this->_model->getSuppliersPaged($where);

        $this->assign('list', $list);
        $this->display();
    }

    public function delete()
    {
        if (IS_POST) {
            $id = I('post.id');
            $result = $this->_model->where(array('id'=>$id, 'user_id'=>$this->userid))->delete();
            if($result !== false){
                $this->ajaxReturn(array(
                    'status'    => 1,
                    'info'      => '好友已删除！',
                    'data'      => array('id'=>$id),
                ));
            }else{
                $this->error($this->_model->getError());
            }
        }
    }
}
