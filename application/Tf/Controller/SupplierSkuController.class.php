<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-12-23
 * Time: 11:01
 */
namespace Tf\Controller;

use Common\Controller\SupplierbaseController;

class SupplierSkuController extends SupplierbaseController
{
    protected $_model;
    protected $tf_model;

    public function _initialize()
    {
        parent::_initialize();
        $this->_model = D('Tf/Sku');
        $this->tf_model = D('Tf/Tf');
        $this->assign('types', $this->_model->types);
    }

    public function index()
    {
        if (IS_AJAX) {
            if (IS_POST) {
                if (isset($_POST['data'])) {
                    $post = I('post.');
                    $action = I('post.action');
                    $data = array();
                    if ($action == 'edit') {
                        $ids = array_keys($post['data']);
                        foreach ($post['data'] as $id => $row) {
                            $row['id'] = $id;
                            $row['value_text'] = $row['value_text'.$row['value_type']];
                            $where["tf_id"] = $row['tf_id'];
                            $where["_string"] = "color_code='{$row['color_code']}' AND id<>$id";
                            $check_color_code = $this->_model->where($where)->count();

                            if($check_color_code > 0){
                                $this->error("此编号已经存在，请重新输入！");
                            }else{
                                $result = $this->_model->saveSku($row);
                            }

                            if ($result === false) {
                                $data['result'][$id]['state'] = 'error';
                                $data['result'][$id]['msg'] = "操作失败(ID:$id)：".$this->_model->getError();
                            }
                        }
                        $data['data'] = $this->_model->where(array('id' => array('IN', $ids)))->select();
                    } elseif ($action == 'create') {
                        $ids = array();

                        foreach ($post['data'] as $key => $row) {
                            $row['value_text'] = $row['value_text'.$row['value_type']];
                            $where["tf_id"] = $row['tf_id'];
                            $where["color_code"] = $row['color_code'];
                            $check_color_code = $this->_model->where($where)->count();

                            if($check_color_code > 0){
                                $this->error("此编号已经存在，请重新输入！");
                            }else{
                                $result = $this->_model->saveSku($row);
                            }

                            if ($result !== false) {
                                $ids[] = $result;
                            } else {
                                $data['result'][$key]['state'] = 'error';
                                $data['result'][$key]['msg'] = "操作失败：".$this->_model->getError();
                            }
                        }
                        if(!empty($ids)){
                        $data['data'] = $this->_model->where(array('id' => array('IN', $ids)))->select();
                        }
                    } elseif ($action == 'remove') {
                        $ids = array_keys($post['data']);
                        if(!empty($ids)){
                        $this->_model->where(array('id' => array('IN', $ids)))->delete();
                        $data['data'] = $this->_model->where(array('id' => array('IN', $ids)))->select();
                        }
                    }
                    $data['status'] = 1;
                    $data['data'] = $this->_formatData($data['data']);

                    $this->ajaxReturn($data);
                } else {
                    $id = I('request.id/d', 0);
                    $data['status'] = 1;
                    $data['data'] = $this->_model->where("tf_id=$id")->select();
                    $data['data'] = $this->_formatData($data['data']);
                    $this->ajaxReturn($data);
                }
            }
        }
        $id = I('request.id/d', 0);
        $tf = $this->tf_model->getTf($id);
        if (empty($tf)) {
            $this->error('传入数据错误！');
        }
        $this->assign('tf', $tf);
        $this->display();
    }


    private function _formatData($data)
    {
        foreach ($data as $k => $v) {
            $data[$k]['value_thumb'] = get_thumb_url($v['value_text'], true, 150);
            $data[$k]['value_text1'] = $v['value_type'] == 1 ? $v['value_text']:'';
            $data[$k]['value_text2'] = $v['value_type'] == 2 ? $v['value_text']:'';
            $data[$k]['value_text3'] = $v['value_type'] == 3 ? $v['value_text']:'';
            $data[$k]['dress_img_url'] = $v['dress_img'] ? get_thumb_url($v['dress_img'], true, 150):'';
        }
        return $data;
    }
}
