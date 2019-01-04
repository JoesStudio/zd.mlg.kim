<?php
namespace Style\Controller;
use Common\Controller\AdminbaseController;
use Style\Model\MaterialClassifyModel;

class MaterialsController extends AdminbaseController {

    public function _initialize() {
        parent::_initialize();
        $this->materialsClassifyModel = D('MaterialsClassify');
    }

    function classify_content(){

        $classify_id = I('post.classify_id') ? I('post.classify_id') : 0;

        if($classify_id){

            $where['classify_id'] = $classify_id;
            $classify = M('StyleMaterialsClassify')->where($where)->find();

            $this->assign('classify',$classify);
            $this->assign('form_url',U('updateClassify',array('classify_id',$classify_id)));
        }else{
            $this->assign('form_url',U('insertClassify'));
        }

        $this->display();
    }

    function insertClassify(){

        if (!$this->materialsClassifyModel->create()){
            $res['state'] = 'fail';
            $res['info'] = $this->materialsClassifyModel->getError();

        }else{

            $data = I('post.');

            if($data['classify_id']){
                $id = $this->materialsClassifyModel()->save($data);

                if($id){
                    $res['state'] = 'success';
                    $res['info'] = '修改成功！';

                }else{
                    $res['state'] = 'fail';
                    $res['info'] = '修改失败！';
                }
            }else{
                unset($data['classify_id']);
                $id = $this->materialsClassifyModel->add();

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

    function classify_list(){

        $classify_list = $this->materialsClassifyModel->select();

        $this->assign('classify_list',$classify_list);
        $this->display();
    }

    function classifyDelete(){
        $classify_id = I('get.classify_id');

        $id = $this->materialsClassifyModel->delete($classify_id);

        if($id){
            $res['state'] = 'success';
        }else{
            $res['state'] = 'fail';
        }
        $this->jsonReturn($res);
    }
}
