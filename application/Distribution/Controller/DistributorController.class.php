<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-05-31
 * Time: 11:36
 */

namespace Distribution\Controller;

use Common\Controller\SupplierbaseController;
use Distribution\Service\PoolService;
use Tf\Model\TfModel;
use Distribution\Model\PoolModel;
use Distribution\Service\ApplyService;
use Distribution\Service\TfService;
use Tf\Service\TfUnionService;

class DistributorController extends SupplierbaseController
{
    protected $sourceModel;
    protected $poolModel;
    protected $poolService;
    protected $applyService;
    protected $tfService;
    protected $tfUnionService;

    public function __construct()
    {
        parent::__construct();
        $this->sourceModel = new TfModel();
        $this->poolModel = new PoolModel();
        $this->poolService = new PoolService();
        $this->applyService = new ApplyService();
        $this->tfService = new TfService();
        $this->tfUnionService = new TfUnionService();
    }

    public function index(){
        if(IS_AJAX){
            $data['data'] = $this->tfUnionService->getRowsNoPaged("supplier_id:{$this->memberid};source:0;");
            //$data['data'] = $this->tfService->getRowsNoPaged("distributor_id:{$this->memberid};status:1;");
            foreach($data['data'] as $k=>$v){
                $data['data'][$k]['link'] = leuu('Tf/Tf/fabric',array('tfsn'=>$v['tf_code']));
                $data['data'][$k]['supplier_logo'] = get_avatar_url($v['supplier_logo']);
                $data['data'][$k]['source_supplier_logo'] = get_avatar_url($v['source_supplier_logo']);
                $data['data'][$k]['source_supplier_link'] = leuu('Supplier/Index/single',array('id'=>$v['source_supplier_id']));
                $data['data'][$k]['thumb'] = get_thumb_url($v['thumb'], true, 150);
            }
            $data['status'] = 1;
            $this->ajaxReturn($data);
        }else{
            $this->display();
        }
    }

    public function tf(){
        $id = I('get.id/d',0);
        if($id == 0){
            $this->redirect('index');
        }
        $data = $this->tfUnionService->getTf(array(
            "id"=>$id,
            "supplier_id"=>$this->memberid,
            "source"=>0,
            "status"=>1
        ));
        /*$data = $this->tfService->getRow(array(
            'tf.id'=>$id,
            'tf.distributor_id'=>$this->memberid,
        ));*/
        if(empty($data)){
            $this->redirect('index');
        }
        $dist = M('DistributionTf')->find($data['id']);
        $cat_name = M('TextileFabricCats')->where("id='{$data['cid']}'")->getFIeld('title');
        $name_title = M('TextileFabricName')->where("code='{$data['name_code']}'")->getField('cname');
        $price = M('DistributionWholesaleRule')->where(array(
            'source_id'=>$data['source_id'],
            'level'=>$dist['level'],
        ))->getField('price');
        $data['price'] = $price;
        $data['cat_title'] = $cat_name;
        $data['name_title'] = $name_title;
        $this->assign('data',$data);
        $this->display();
    }

    public function tf_post(){
        if(IS_POST){
            $id = I('post.id/d',0);
            $name = I('post.name','');
            $code = I('post.code','');
            if($id == 0){
                $this->error('操作失败！');
            }
            if($name == ''){
                $this->error('请填写品名！');
            }
            if($code == ''){
                $this->error('请填写自编码！');
            }
            if ($code == '') {
                $this->error('自编码不能为空！');
            }

            $newtfcode = $this->tfUnionService->unionModel
                ->field("CONCAT('{$this->memberid}',cat_code,name_code,'{$code}') as new_code,tf_code")
                ->where(array('id'=>$id))->find();
            $codeExist = $this->tfUnionService->unionModel
                ->where("tf_code='{$newtfcode['tf_code']}' AND NOT id='{$id}'")
                ->count();
            if ($codeExist > 0
            ) {
                $this->error('该编码已经存在，请更换！');
            }

            $tf = $this->tfService->tfModel
                ->where(array(
                    'id'=>$id,
                    'distributor_id'=>$this->memberid,
                    'status'=>1,
                ))
                ->find();
            if(empty($tf)){
                $this->error('操作失败！');
            }

            /* 图片 */
            if (!empty($_POST['photos_alt']) && !empty($_POST['photos_url'])) {
                foreach ($_POST['photos_url'] as $key => $url) {
                    $photourl = $url;    //
                    $img['photo'][] = array(
                        "url" => $photourl,
                        "alt" => $_POST['photos_alt'][$key],
                        "dress_status" => $_POST['photos_dress'][$key],
                    );
                }
            }
            $img['thumb'] = $_POST['img']['thumb']; //
            if ($_POST['img']['thumb'] == '') {
                $img['dress_thumb'] = 0;
            } else {
                $img['dress_thumb'] = 1; //
            }

            $result = $this->tfService->tfModel->save(array(
                'id'=>$id,
                'name'=>$name,
                'code'=>$code,
                'img'=>json_encode($img, 256),
            ));

            if($result !== false){
                $this->success('面料信息已保存！');
            }else{
                $this->error('保存失败！');
            }

        }
    }

    public function sku(){
        if(IS_AJAX){
            $id = I('get.id/d',0);
            $data['data'] = $this->tfUnionService->getSkuList(array(
                'tf_id'=>$id,
                'original'=>0,
                'supplier_id'=>$this->memberid,
            ));
            if(!empty($data['data'])){
                foreach($data['data'] as $k=>$v){
                    $data['data'][$k]['dress_img_url'] = $v['dress_img'] ? get_thumb_url($v['dress_img'],true,150):null;
                }
            }
            $data['status'] = 1;
            $this->ajaxReturn($data);
        }else{
            $this->display();
        }
    }

    public function sku_post(){
        if(IS_POST){
            $id = I('post.id/d',0);
            $skuPrice = number_format(I('post.sku_price',0.00),2);
            $groupPrice = number_format(I('post.group_price',0.00),2);
            $skuModel = $this->tfUnionService->unionSkuModel;
            $sku = $skuModel->where("supplier_id='{$this->memberid}' AND original=0 AND id='{$id}'")->find();
            if(empty($sku)){
                $this->error('当前SKU无效！');
            }
            $result = M('DistributionTfSku')->save(array(
                'id'=>$id,
                'sku_price'=>$skuPrice,
                'group_price'=>$groupPrice,
            ));
            if($result !== false){
                $this->success('价格已修改！');
            }else{
                $this->error('价格修改失败！');
            }
        }
    }

    public function application(){
        if(IS_AJAX){
            $data['data'] = $this->applyService->getRowsNoPaged("distributor_id:{$this->memberid};status:0;");
            $data['status'] = 1;
            foreach($data['data'] as $k=>$v){
                $data['data'][$k]['DT_RowId'] = 'row_'.$v['id'];
                $data['data'][$k]['link'] = leuu('Tf/Tf/fabric',array('tfsn'=>$v['tf_code']));
                $data['data'][$k]['supplier_logo'] = get_avatar_url($v['supplier_logo']);
                $data['data'][$k]['supplier_link'] = leuu('Supplier/Index/single',array('id'=>$v['supplier_id']));
                $data['data'][$k]['thumb'] = get_thumb_url($v['thumb'], true, 150);
                $data['data'][$k]['status_text'] = $this->applyService->applyModel->statuses[$v['status']];
            }
            $this->ajaxReturn($data);
        }else{
            $this->display();
        }
    }


    function reapply_post(){
        if(IS_POST){
            $id = I("post.id/d",0);
            if($id == 0){
                $this->error('操作失败！');
            }

            $apply = $this->applyService->applyModel
                ->where(array(
                    'id'=>$id,
                    'distributor_id'=>$this->memberid,
                    'status'=>0,
                ))
                ->find();
            if(empty($apply)){
                $this->error('操作失败，该申请无效！');
            }

            $tfCount = M('DistributionTf')->where(array(
                'source_id'=>$apply['source_id'],
                'distributor_id'=>$this->memberid,
                'status'=>1,
            ))->count();

            if($tfCount > 0){
                $this->applyService->applyModel->where("id='{$id}'")->setField('status', 3);
                $this->ajaxReturn(array(
                    'status'=>1,
                    'info'=>'您已经代理这款面料了！',
                    'data'=>array(
                        'id'=>$id,
                        'status'=>3,
                        'status_text'=>$this->applyService->applyModel->statuses[3],
                    ),
                ));
            }

            $date = date('Y-m-d H:i:s');
            $result = $this->applyService->applyModel
                ->where("id='{$id}'")
                ->setField('modify_time', $date);

            if($result !== false){
                $this->ajaxReturn(array(
                    'status'=>1,
                    'info'=>'已经重新发送代理申请！',
                    'data'=>array(
                        'id'=>$id,
                        'status'=>0,
                        'status_text'=>$this->applyService->applyModel->statuses[0],
                        'modify_date'=>$date,
                    ),
                ));
            }else{
                $this->error('操作失败！');
            }
        }
    }

    function cancel_post(){
        if(IS_POST){
            $id = I("post.id/d",0);
            if($id == 0){
                $this->error('操作失败！');
            }

            $apply = $this->applyService->applyModel
                ->where(array(
                    'id'=>$id,
                    'distributor_id'=>$this->memberid,
                    'status'=>0,
                ))
                ->find();
            if(empty($apply)){
                $this->error('操作失败，该申请无效！');
            }

            $tfCount = M('DistributionTf')->where(array(
                'source_id'=>$apply['source_id'],
                'distributor_id'=>$this->memberid,
                'status'=>1,
            ))->count();

            if($tfCount > 0){
                $this->applyService->applyModel->where("id='{$id}'")->setField('status', 3);
                $this->ajaxReturn(array(
                    'status'=>1,
                    'info'=>'您已经代理这款面料了！',
                    'data'=>array(
                        'id'=>$id,
                        'status'=>3,
                        'status_text'=>$this->applyService->applyModel->statuses[3],
                    ),
                ));
            }

            $result = $this->applyService->applyModel
                ->where("id='{$id}'")
                ->setField('status', 3);

            if($result !== false){
                $this->ajaxReturn(array(
                    'status'=>1,
                    'info'=>'已经取消申请！',
                    'data'=>array(
                        'id'=>$id,
                        'status'=>3,
                        'status_text'=>$this->applyService->applyModel->statuses[3],
                        'modify_date'=>date('Y-m-d H:i:s'),
                    ),
                ));
            }else{
                $this->error('操作失败！');
            }
        }
    }
}