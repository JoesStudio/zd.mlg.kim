<?php
namespace Style\Controller;
use Common\Controller\AdminbaseController;
use Style\Model\ThumbsTypeModel;

class ThumbsController extends AdminbaseController {

    public function _initialize() {
        parent::_initialize();
        $this->thumbsTypeModel = D('ThumbsType');
    }

    public function type_content(){

        $type_id = I('post.type_id') ? I('post.type_id') : 0;

        if($type_id){

            $where['type_id'] = $type_id;
            $type = M('StyleThumbsType')->where($where)->find();

            $this->assign('type',$type);
            $this->assign('form_url',U('updateType',array('type_id',$type_id)));
        }else{
            $this->assign('form_url',U('insertType'));
        }

        $this->display();
    }

    public function insertType(){

        if (!$this->thumbsTypeModel->create()){
            $res['state'] = 'fail';
            $res['info'] = $this->thumbsTypeModel->getError();

        }else{

            $data = I('post.');

            if($data['type_id']){
                $id = $this->thumbsTypeModel()->save($data);

                if($id){
                    $res['state'] = 'success';
                    $res['info'] = '修改成功！';

                }else{
                    $res['state'] = 'fail';
                    $res['info'] = '修改失败！';
                }
            }else{
                unset($data['type_id']);
                $id = $this->thumbsTypeModel->add();

                if($id){
                    $res['state'] = 'success';
                    $res['info'] = '新增成功！';

                }else{
                    $res['state'] = 'fail';
                    $res['info'] = '新增失败！';
                }
            }

        }
        $this->jsonReturn($res);
    }

    public function type_list(){

        $type_list = $this->thumbsTypeModel->select();

        $this->assign('type_list',$type_list);
        $this->display();
    }

    public function typeDelete(){
        $type_id = I('get.type_id');

        $id = $this->thumbsTypeModel->delete($type_id);

        if($id){
            $res['state'] = 'success';
        }else{
            $res['state'] = 'fail';
        }
        $this->jsonReturn($res);
    }
}
