<?php
namespace User\Controller;

use Common\Controller\AdminbaseController;

class AdminController extends AdminbaseController
{
    protected $_model;
    protected $info_model;
    protected $uid;

    public function _initialize()
    {
        parent::_initialize();
        $this->_model = D('Users');
        $this->info_model = D('UserInfo');
        if (isset($_REQUEST['uid'])) {
            $this->uid = I('request.uid');
            $this->assign('uid', $this->uid);
        } else {
            if (!IS_POST) {
                $this->error('请选择用户！');
            }
        }
    }

    public function editinfo()
    {
        $user = $this->_model->getUser($this->uid);
        $this->assign('user', $user);
        $this->assign('areas', D('Areas')->getAreasByDistrict($user['userinfo']['district']));
        $this->display();
    }

    public function editinfo_post()
    {
        if (IS_POST) {
            $data = I('post.userinfo');
            $user_id = I('post.id');

            $result = $this->info_model->saveInfo($data);
            $updata_user = M('Users')->where(array('id'=>$user_id))->save(array('nickname'=>$data['nickname']));
            if (($result && $updata_user)!== false) {
                $this->success('资料已更新！');
            } else {
                $this->error('操作失败！'.$this->info_model->getError());
            }
        }
    }
}
