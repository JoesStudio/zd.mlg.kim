<?php
/* * 
 * 系统权限配置，用户角色管理
 */
namespace Supplier\Controller;

use Common\Controller\AdminbaseController;

class AdminRbacController extends AdminbaseController {

    protected $role_model, $auth_access_model;

    public function _initialize() {
        parent::_initialize();
        $this->role_model = D("Common/BizRole");
    }

    /**
     * 角色管理列表
     */
    public function index() {
        $data = $this->role_model->order(array("listorder" => "ASC", "id" => "DESC"))->select();
        $this->assign("roles", $data);
        $this->display();
    }

    /**
     * 添加角色
     */
    public function roleadd() {
        $this->display();
    }
    
    /**
     * 添加角色
     */
    public function roleadd_post() {
        if (IS_POST) {
            if ($this->role_model->create()!==false) {
                if ($this->role_model->add()!==false) {
                    $this->success("添加角色成功",U("index"));
                } else {
                    $this->error("添加失败！");
                }
            } else {
                $this->error($this->role_model->getError());
            }
        }
    }

    /**
     * 删除角色
     */
    public function roledelete() {
        $id = I("get.id",0,'intval');
        if ($id == 1) {
            $this->error("超级管理员角色不能被删除！");
        }
        $role_user_model=M("BizRoleOp");
        $count=$role_user_model->where(array('role_id'=>$id))->count();
        if($count>0){
            $this->error("该角色已经有用户！");
        }else{
            $status = $this->role_model->delete($id);
            if ($status!==false) {
                $this->success("删除成功！", U('index'));
            } else {
                $this->error("删除失败！");
            }
        }
        
    }

    /**
     * 编辑角色
     */
    public function roleedit()
    {
        $id = I("get.id", 0, 'intval');
        if ($id == 1) {
            $this->error("超级管理员角色不能被修改！");
        }
        $data = $this->role_model->where(array("id" => $id))->find();
        if (!$data) {
            $this->error("该角色不存在！");
        }
        $this->assign("data", $data);
        $this->display();
    }
    
    /**
     * 编辑角色
     */
    public function roleedit_post()
    {
        $id = I("request.id", 0, 'intval');
        if ($id == 1) {
            $this->error("超级管理员角色不能被修改！");
        }
        if (IS_POST) {
            if ($this->role_model->create()!==false) {
                if ($this->role_model->save()!==false) {
                    $this->success("修改成功！", U('index'));
                } else {
                    $this->error("修改失败！");
                }
            } else {
                $this->error($this->role_model->getError());
            }
        }
    }

    /**
     * 角色授权
     */
    public function authorize()
    {
        $role_id = I('get.id/d', 0);
        if ($role_id == 0) {
            $this->error('传入数据错误！');
        }
        if (IS_AJAX) {
            if (IS_POST) {
                $rule_model = D('BizOpauthRule');
                $data['status'] = 1;
                $data['data'] = $rule_model
                ->alias('rule')
                ->field("rule.*,IF(access.rule_name IS NULL OR access.rule_name = '', 0, 1) as is_checked")
                ->join("LEFT JOIN __BIZ_OPAUTH_ACCESS__ access ON access.rule_name=rule.name AND access.type=rule.type AND access.role_id=$role_id")
                ->where("rule.status=1")
                ->select();
                $this->ajaxReturn($data);
            }
        }
        $this->display();
    }
    
    /**
     * 角色授权
     */
    public function authorize_post()
    {
        $this->auth_access_model = D("Common/BizOpauthAccess");
        if (IS_POST) {
            $roleid = I("post.roleid", 0, 'intval');
            if (!$roleid) {
                $this->error("需要授权的角色不存在！");
            }
            $this->auth_access_model->where(array("role_id"=>$roleid,'type'=>'supplier_url'))->delete();
            $ids = I("post.ids");
            if (is_array($ids) && !empty($ids)) {
                $rules = D('BizOpauthRule')->where(array('id'=>array('IN', $ids)))->getField("id,name,type");
                if (!empty($rules)) {
                    $data = array();
                    foreach ($rules as $rule) {
                        $data[] = array(
                            'role_id'   => $roleid,
                            'rule_name' => $rule['name'],
                            'type'      => $rule['type'],
                        );
                    }
                    $this->auth_access_model->addAll($data);
                }
                $this->success('角色已授权！');
            } else {
                $this->success("没有接收到数据，执行清除授权成功！");
            }
        }
    }
}
