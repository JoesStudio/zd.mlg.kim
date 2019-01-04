<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-05-27
 * Time: 16:34
 */

namespace Distribution\Controller;


use Common\Controller\SupplierbaseController;
use Distribution\Service\PoolService;
use Tf\Model\TfModel;
use Distribution\Model\PoolModel;
use Distribution\Service\ApplyService;
use Tf\Service\TfUnionService;

class SupplierController extends SupplierbaseController
{
    protected $tfModel;
    protected $poolModel;
    protected $poolService;
    protected $applyService;
    protected $wholesaleModel;
    protected $tfUnionService;

    public function __construct()
    {
        parent::__construct();
        $this->tfModel = new TfModel();
        $this->poolModel = new PoolModel();
        $this->poolService = new PoolService();
        $this->applyService = new ApplyService();
        $this->wholesaleModel = M('DistributionWholesaleRule');
        $this->tfUnionService = new TfUnionService();
    }

    public function index()
    {
        if (IS_AJAX) {
            $field = 'id,name,img,spec,width,weight,material,component,function,purpose,tf_code,on_sale';
            $strNow = "UNIX_TIMESTAMP(NOW())";
            $field .= ",getDistTfTotalAmount(id,0,NULL) as amount_all";
            $field .= ",getDistTfTotalAmount(id,{$strNow}-3600*24*30,NULL) as amount_30";
            $field .= ",getDistTfTotalAmount(id,{$strNow}-3600*24*90,NULL) as amount_90";
            $field .= ",getDistTfTotalNumber(id,0,NULL) as number_all";
            $field .= ",getDistTfTotalNumber(id,{$strNow}-3600*24*30,NULL) as number_30";
            $field .= ",getDistTfTotalNumber(id,{$strNow}-3600*24*90,NULL) as number_90";
            $data['data'] = $this->poolService
                ->getRowsNoPaged("field:{$field};supplier_id:{$this->memberid};status:1;order:create_date DESC;");
            foreach ($data['data'] as $k => $v) {
                $data['data'][$k]['thumb'] = get_thumb_url($v['thumb'], true, 150);
                $data['data'][$k]['link'] = leuu('Tf/Tf/fabric', array('tfsn' => $v['tf_code']));
                unset($data['data'][$k]['img']);
            }
            $data['status'] = 1;
            $this->ajaxReturn($data);
        } else {
            $this->display();
        }
    }

    public function fabrics()
    {
        if (IS_AJAX) {
            $data['data'] = $this->poolService
                ->getRowsNoPaged("supplier_id:{$this->memberid};order:status DESC,create_date DESC;");
            foreach ($data['data'] as $k => $v) {
                $data['data'][$k]['thumb'] = get_thumb_url($v['thumb'], true, 150);
                $data['data'][$k]['link'] = leuu('Tf/Tf/fabric', array('tfsn' => $v['tf_code']));
            }
            $data['status'] = 1;
            $this->ajaxReturn($data);
        } else {
            $this->display();
        }
    }

    /**
     * 单独的面料分销设置
     */
    public function tf()
    {
        $tfId = I('get.id/d', 0);
        if ($tfId == 0) {
            $this->error('该面料不存在！');
        }

        $where['id'] = $tfId;
        $where['vend_id'] = $this->memberid;
        $tf = $this->tfModel->where($where)->find();
        if (empty($tf)) {
            $this->error('该面料不存在！');
        }

        $tf['img'] = json_decode($tf['img'], true);

        $setting = $this->poolService->getItem($tfId);

        // 初始化分销设置
        if (empty($setting)) {
            $setting = $this->poolService->initPoolItem($tfId);
            if ($setting === false) {
                $this->error('初始化分销设置失败！');
            }
        } else {
            for ($i = 1; $i <= 5; $i++) {
                if (!isset($setting['price'][$i])) {
                    $this->wholesaleModel->add(array(
                        'source_id' => $tfId,
                        'level' => $i,
                    ));
                    $setting['price'][$i] = 0.00;
                }
            }
        }


        $this->assign('setting', $setting);
        $this->assign('tf', $tf);

        $this->display();
    }

    public function tf_post()
    {
        if (IS_POST) {
            $post = I('post.');
            $levels = $post['level'];
            if (!$post['id']) {
                $this->error('非法操作！');
            }

            $tf = $this->poolModel->find($post['id']);
            if (empty($tf)) {
                $this->error('操作失败，面料池不存在这个面料！');
            }
            if ($tf['supplier_id'] != $this->memberid) {
                $this->error('操作失败，您没有权限操作这个面料！');
            }

            $data = array(
                'id' => $post['id'],
                'level' => $post['level'],
                'status' => $post['status'],
            );

            foreach ($levels as $k => $v) {
                $this->wholesaleModel
                    ->where("source_id='{$tf['source_id']}' AND level='{$k}'")
                    ->setField('price', $v);
            }

            $result = $this->poolModel->save($data);
            if ($result !== false) {
                $this->success('分销设置已保存！');
            } else {
                $this->error('操作失败！');
            }
        }
    }

    public function toggle_status()
    {
        if (IS_POST) {
            $id = I('post.id/d', 0);
            $value = I('post.value/d', 0);
            if ($id == 0) {
                $this->error('传入数据出错！');
            }

            $tf_name = $this->tfModel->where("id='$id'")->getField('name');

            $setting = $this->pool
                ->where("source_id='{$id}' AND supplier_id='{$this->memberid}'")
                ->getField('source_id,status');

            $this->poolModel->startTrans();
            // 初始化分销设置
            if (empty($setting)) {
                $setting = $this->poolService->initPoolItem($id);
                if ($setting === false) {
                    $this->poolModel->rollback();
                    $this->ajaxReturn(array(
                        'status' => 0,
                        'data' => $setting,
                        'info' => '初始化分销设置失败！'
                    ));
                    $this->error('初始化分销设置失败！');
                }
            }

            if ($setting['status'] != $value) {
                $this->poolModel
                    ->where("source_id='{$id}' AND supplier_id='{$this->memberid}'")
                    ->setField('status', $value);

                $setting = $this->poolModel
                    ->where("source_id='{$id}' AND supplier_id='{$this->memberid}'")
                    ->find();
            }
            $this->poolModel->commit();

            $data['level'] = $setting['level'];
            $data['data'] = $setting['status'];
            $data['info'] = "<b>$tf_name</b>" . $setting['status'] ? '已开启分销' : '已关闭分销';
            $data['status'] = 1;

            $this->ajaxReturn($data);
        }
    }

    public function set_status()
    {
        $ids = I('post.ids', array());
        $status = I('post.value/d', 0);
        if (empty($ids)) {
            $this->error('操作失败！');
        }

        $pool = M('TfPoolView')
            ->where("id IN(" . implode(',', $ids) . ") AND supplier_id='{$this->memberid}'")
            ->select();
        $this->poolModel->startTrans();
        foreach ($pool as $k => $v) {
            if (is_null($v['pool_id'])) {
                $setting = $this->poolService->initPoolItem($v['id']);
                if ($setting === false) {
                    $this->poolModel->rollback();
                    $this->error('初始化分销设置失败！');
                }
            }
            if (intval($v['status']) !== intval($status)) {
                $result = $this->poolModel->where("source_id='{$v['id']}'")->setField('status', $status);
                if ($result === false) {
                    $this->poolModel->rollback();
                    $this->error('操作失败！');
                }
            }
        }
        $this->poolModel->commit();
        $this->success('操作成功！');
    }

    public function application()
    {
        if (IS_AJAX) {
            $data['data'] = $this->applyService->getRowsNoPaged("supplier_id:{$this->memberid};status:0;dist_tf_status:0;");
            foreach ($data['data'] as $k => $v) {
                $data['data'][$k]['thumb'] = get_thumb_url($v['thumb'], true, 150);
                $data['data'][$k]['link'] = leuu('Tf/Tf/fabric', array('tfsn' => $v['tf_code']));
                $data['data'][$k]['avatar'] = get_avatar_url($v['distributor_logo']);
            }
            $data['status'] = 1;
            $this->ajaxReturn($data);
        } else {
            $this->display();
        }
    }

    public function view_apply()
    {
        $id = I('get.id/d', 0);
        if ($id == 0) {
            $this->redirect('index');
        }
        $where['id'] = $id;
        $where['status'] = 0;
        $where['supplier_id'] = $this->memberid;
        $where['dist_tf_status'] = 0;
        $apply = $this->applyService->getRow($where);
        if (empty($apply)) {
            $this->error('该申请不存在！');
        }
        $this->assign('apply', $apply);

        $priceList = $this->wholesaleModel->where("source_id='{$apply['source_id']}'")->getField('level,price');
        $this->assign('priceList', $priceList);
        //var_dump($apply);
        $this->display();
    }

    public function accept_post()
    {
        if (IS_POST) {
            $id = I('post.id/d', 0);
            $where['id'] = $id;
            $where['status'] = 0;
            $where['supplier_id'] = $this->memberid;
            $where['dist_tf_status'] = 0;
            $apply = $this->applyService->getRow($where);
            if (empty($apply)) {
                $this->error('操作失败，该申请无效！');
            }

            $apply['name'] = I('post.name/s', '');
            $apply['code'] = I('post.code/s', '');
            $apply['dist_tf_level'] = I('post.level/d', 1);
            if ($apply['name'] == '') {
                $this->error('面料名称必填！');
            }
            if ($apply['code'] == '') {
                $this->error('面料自编号必填！');
            }

            $this->applyService->applyModel->startTrans();
            list($result, $error) = $this->acceptApply($apply);
            if ($result === false) {
                $this->applyService->applyModel->rollback();
                $this->error($error);
            } else {
                $this->applyService->applyModel->commit();
                $this->success('代理已批准！', leuu('application'));
            }
        }
    }

    public function reject_post()
    {
        $id = I('post.id/d', 0);
        $applyModel = $this->applyService->applyModel;
        $apply = $applyModel->where("id='$id' AND supplier_id='{$this->memberid}' AND status=0")->find();
        if (empty($apply)) {
            $this->error('操作失败！');
        }

        $result = $applyModel->save(array(
            "id" => $id,
            "status" => 2,
            "modify_time" => date('Y-m-d H:i:s'),
        ));

        if ($result === false) {
            $this->error('操作失败！');
        } else {
            $this->success('申请已拒绝！', leuu('application'));
        }
    }

    public function audit_apply_post()
    {
        $ids = I('post.ids', array());
        $status = I('post.value/d', 0);
        if (empty($ids)) {
            $this->error('操作失败！');
        }

        if($status == 1){
            $list = $this->applyService->applyView
                ->where(array(
                    'id' => array('IN', $ids),
                    'supplier_id' => $this->memberid,
                    'status' => 0,
                    'dist_tf_status' => 0,
                ))
                ->select();

            if(empty($list)){
                $this->error('您选中的申请是无效的！');
            }

            $this->applyService->applyModel->startTrans();
            foreach($list as $apply){
                list($result, $error) = $this->acceptApply($apply);
                if($result === false){
                    $this->applyService->applyModel->rollback();
                    $this->error($error);
                }
            }

            $this->applyService->applyModel->commit();
        }elseif($status == 2){
            $result = $this->applyService->applyModel
                ->where(array(
                    'id'=>array('IN', $ids),
                    'supplier_id'=>$this->memberid,
                    'status'=>0,
                ))
                ->save(array(
                    'status'=>2,
                    'modify_time'=>date('Y-m-d H:i:s'),
                ));
            if($result === false){
                $this->error('操作失败！');
            }
        }
        $this->success('操作成功！');
    }

    private function acceptApply($apply){


        $name = $apply['name'];
        $code = $apply['code'];
        $level = $apply['dist_tf_level'];

        $img = M('TextileFabric')->where("id='{$apply['source_id']}'")->getField('img');

        /* 检验数据 BEGIN */
        $newtfcode = M('TextileFabric')->field("CONCAT('{$apply['distributor_id']}',cat_code,name_code,'{$code}') as tf_code")
            ->where("id='{$apply['source_id']}'")->find();
        if (empty($newtfcode)) {
            return array(false, '该面料不存在');
        }
        if ($this->tfUnionService->unionModel->where("tf_code='{$newtfcode['tf_code']}'")->count() > 0) {
            return array(false, '该编号已经存在，请修改后再提交！');
        }

        $price = M('DistributionWholesaleRule')
            ->where("source_id='{$apply['source_id']}' AND level='{$level}'")
            ->getField('price');
        if (floatval($price) == 0.00 || empty($price)) {
            return array(false, '该面料尚未设置代理价格！');
        }
        /* 检验数据 END */

        /* 写入数据 BEGIN */
        //增加一个客户
        if (is_null($apply['client_id'])) {
            $clientData = array(
                'supplier_id' => $apply['supplier_id'],
                'distributor_id' => $apply['distributor_id'],
                'level' => I('post.client_level/d', 1),
                'status' => 1,
                'create_date' => date('Y-m-d H:i:s'),
            );
            $result = M('DistributionClient')->add($clientData);
            if ($result === false) {
                return array(false, '操作失败，添加客户数据出错！');
            }
        }

        //保存代理面料
        $distTfModel = M('DistributionTf');
        if (is_null($apply['dist_tf_id'])) {
            $result = $distTfModel->add(array(
                'source_id' => $apply['source_id'],
                'supplier_id' => $apply['supplier_id'],
                'distributor_id' => $apply['distributor_id'],
                'name' => $name,
                'code' => $code,
                'img'   => !empty($img) ? $img:'',
                'level' => $level,
                'status' => 1,
                'create_time' => date('Y-m-d H:i:s'),
            ));
            $distTfId = $result;
        } else {
            $result = $distTfModel->save(array(
                'id' => $apply['dist_tf_id'],
                'name' => $name,
                'code' => $code,
                'level' => $level,
                'status' => 1,
                'modify_time' => date('Y-m-d H:i:s'),
            ));
            $distTfId = $apply['dist_tf_id'];
        }
        if ($result === false) {
            return array(false, '操作失败！');
        }

        //更新sku信息
        $orgSkus = M('TextileFabricSku')
            ->where("tf_id='{$apply['source_id']}'")
            ->getField('id,sku_price,group_price');
        /* 删除不存在的shu BEGIN */
        if(!empty($orgSkus)){
            $distSkuModel = M('DistributionTfSku');
            $result = $distSkuModel->where(array(
                'tf_id' => $distTfId,
                'source_sku_id' => array('NOT IN', array_keys($orgSkus)),
            ))->delete();
            if ($result === false) {
                return array(false, '操作失败，初始化面料失败！');
            }
        }
        /* 删除不存在的shu END */
        $existOrgSkuIds = M('DistributionTfSku')
            ->where("tf_id='{$distTfId}'")
            ->getField('source_sku_id', true);
        $newSkusData = array();
        foreach ($orgSkus as $orgSkuId => $sku) {
            if (empty($existOrgSkuIds) || !in_array($orgSkuId, $existOrgSkuIds)) {
                $newSkusData[] = array(
                    'tf_id' => $distTfId,
                    'source_sku_id' => $orgSkuId,
                    'sku_price' => $sku['sku_price'],
                    'group_price' => $sku['group_price'],
                );
            }
        }
        if (!empty($newSkusData)) {
            $result = $distSkuModel->addALl($newSkusData);
            if ($result === false) {
                return array(false, '操作失败，初始化面料失败！');
            }
        }

        //更改申请状态
        $result = $this->applyService->applyModel->save(array(
            'id' => $apply['id'],
            'status' => 1,
            'modify_time' => date('Y-m-d H:i:s'),
        ));
        if ($result === false) {
            return array(false, '操作失败！');
        } else {
            return array(true, null);
        }
    }

}