<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-05-27
 * Time: 11:51
 */

namespace Distribution\Controller;


use Common\Controller\SupplierbaseController;
use Distribution\Service\PoolService;
use Tf\Model\TfModel;
use Distribution\Model\PoolModel;

class PoolController extends SupplierbaseController
{
    protected $tfModel;
    protected $poolModel;
    protected $poolService;

    public function __construct()
    {
        parent::__construct();
        $this->tfModel = new TfModel();
        $this->poolModel = new PoolModel();
        $this->poolService = new PoolService();
    }

    function index(){

        $alias1 = 'mlg_distribution_tf';
        $alias2 = 'mlg_tf_pool_view';
        $countTf = M("DistributionTf")->field("COUNT({$alias1}.id)")
            ->where("{$alias2}.id={$alias1}.source_id AND {$alias1}.status=1 AND {$alias1}.distributor_id='{$this->memberid}'")
            ->buildSql();

        $field = "*,IF({$countTf}>0,1,0) as distributing";
        $order = "distributing ASC, id DESC";
        $list = $this->poolService->getRowsPaged("field:$field;order:$order;status:1;where:supplier_id<>'{$this->memberid}';",15);
        $this->assign('list', $list);

        //我的供应商
        $suppliers = M('DistributionClient')
            ->where("distributor_id='{$this->memberid}' AND status=1")
            ->getField('supplier_id,level');
        $this->assign('suppliers', $suppliers);
        $this->display();
    }

    function apply_client_post(){
        if(IS_POST){
            $id = I('post.id/d',0);
            if($id == 0){
                $this->error("操作失败！");
            }

            if(M('BizMember')->where("id='{$id}' AND biz_status=1 AND biz_trash=0")->count() == 0){
                $this->error("不存在这个供应商！");
            }

            $clientModel = M('DistributionClient');
            $client = $clientModel->where("supplier_id='{$id}' AND distributor_id='{$this->memberid}'")->find();
            if(!empty($client)){
                if($client['status']){
                    $this->success("您已经是该供应商的客户了！",leuu("index"));
                }else{
                    if(M('DistributionTf')->where("supplier_id='{$id}' AND distributor_id='{$this->memberid}'")->count() > 0){
                        $clientModel->where("id='{$client['id']}'")->setField('status',1);
                        $this->success('您已经成为该供应商的客户了！',leuu("index"));
                    }
                }
            }


            $applyModel = M('DistributionClientApply');
            $apply = $applyModel->where("supplier_id='{$id}' AND status=0 AND distributor_id='{$this->memberid}'")->find();
            if(empty($apply)){
                $result = $applyModel->add(array(
                    'supplier_id'   => $id,
                    'distributor_id'=> $this->memberid,
                    'status'        => 0,
                    'create_time'   => date('Y-m-d H:i:s'),
                ));
            }else{
                $result = $applyModel->save(array(
                    'id'            => $apply['id'],
                    'modify_time'   => date('Y-m-d H:i:s'),
                ));
            }

            if($result !== false){
                $this->success("申请已经发送！");
            }else{
                $this->error("操作失败！");
            }
        }
    }

    function apply_post(){
        if(IS_POST){
            $id = I("post.id/d",0);
            if($id == 0){
                $this->error('操作失败！');
            }

            $field = 'tf.vend_id as supplier_id,tf.name,CONCAT(tf.vend_id,tf.cat_code,tf.name_code,tf.code) as tf_code';
            $where['tf.id'] = $id;
            $where['pool.status'] = 1;
            $tf = $this->tfModel
                ->alias('tf')
                ->field($field)
                ->join("__DISTRIBUTION_POOL__ pool ON pool.source_id=tf.id")
                ->where($where)
                ->find();
            if(empty($tf)){
                $this->error('操作失败，面料池没有这个面料！');
            }

            if($tf['supplier_id'] == $this->memberid){
                $this->error('操作失败，您不能代理您自己的面料！');
            }

            if(M('DistributionTf')->where("source_id='{$id}' AND status=1")->count() > 0){
                $this->success('您已经在代理这款面料了！');
            }

            $applyModel = M('DistributionTfApply');
            $where = "source_id='{$id}' AND distributor_id='{$this->memberid}' AND status=0";
            if($applyModel->where($where)->count() > 0){
                $result = $applyModel->where($where)->setField('modify_time', date('Y-m-d H:i:s'));
            }else{
                $result = $applyModel->add(array(
                    'source_id' => $id,
                    'distributor_id'    => $this->memberid,
                    'supplier_id'       => $tf['supplier_id'],
                    'status'    => 0,
                    'create_time'   => date('Y-m-d H:i:s'),
                ));
            }

            if($result === false){
                $this->error('操作失败！');
            }

            $this->ajaxReturn(array(
                'status'=>1,
                'data'=>$id,
                'info'=>'申请已发送！'
            ));
        }
    }

}