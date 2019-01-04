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
use Tf\Service\TfUnionService;
use Distribution\Service\SupplierService;

class IndexController extends SupplierbaseController
{
    protected $tfModel;
    protected $poolModel;
    protected $poolService;
    protected $tfUnionService;
    protected $supplierService;

    public function __construct()
    {
        parent::__construct();
        $this->tfModel = new TfModel();
        $this->poolModel = new PoolModel();
        $this->poolService = new PoolService();
        $this->tfUnionService = new TfUnionService();
        $this->supplierService = new SupplierService();
    }

    function index()
    {
        $where = "supplier_id<>'{$this->memberid}'";
        $filterCats = M('TextileFabricCats')->field('id,title as name,code')->where("pid=0")->order('listorder ASC, id ASC')->select();
        $this->assign('filterCats', $filterCats);
        $cid = I('get.cid/d',0);
        if($cid > 0){
            $cids = $this->get_subIds(intval($cid));
            array_unshift($cids, $cid);

            if(!empty($cids)){
                $where .= ' AND cat_id IN('.implode(',',$cids).')';
            }
        }
        $ncode = I('get.ncode/s','');
        if(isset($_GET['ncode'])){
            $where .= " AND name_code='{$ncode}'";
            $map['name_code'] = $ncode;
        }
        /* 名称分类选中状态 */
        if($cid > 0){
            $selectedCids = $this->getSelectedCids($cid);
            $catPids = $selectedCids;
            //array_pop($catPids);
            array_unshift($catPids, 0);
            array_push($selectedCids, 0);
        }else{
            $selectedCids = array(0,);
            $catPids = array(0,);
        }
        $catSelectors = array();
        foreach($catPids as $k=>$pid){
            $cats = M('TextileFabricCats')
                ->field('id,title as name,code,IF(id='.intval($selectedCids[$k]).',1,0) as selected')
                ->where("pid='{$pid}'")->select();
            if($cats){
                $catSelectors[$pid] = $cats;
            }
        }
        $this->assign('catSelectors', $catSelectors);
        if($cid > 0){
            $names = M('TextileFabricName')->field("id,cname as name,code,IF(code='{$ncode}',1,0) as selected")
                ->where("cid='{$cid}'")->select();
            $this->assign('names', $names);
        }
        /* 名称分类选中状态 */

        $list = $this->getPoolTfs($where);
        $this->assign('list', $list);

        //我的供应商
        $suppliers = M('DistributionClient')
            ->where("distributor_id='{$this->memberid}' AND status=1")
            ->getField('supplier_id,level');
        $this->assign('suppliers', $suppliers);
        $this->display();
    }

    private function getSelectedCids($id){
        $ids = array($id,);
        $pid =  M('TextileFabricCats')->where("id='{$id}'")->getField('pid');
        if(!empty($pid)){
            $ids = array_merge($this->getSelectedCids($pid), $ids);
        }
        return $ids;
    }

    private function get_subIds($id){
        $ids = array();
        $where['pid'] = $id;
        $catModel = M('TextileFabricCats');
        $list = $catModel->where($where)->getField('id',true);
        if(!empty($list)){
            $ids = array_merge($ids, $list);
            foreach($list as $subId){
                $ids = array_merge($ids, $this->get_subIds(intval($subId)));
            }
        }
        return $ids;
    }

    function suppliers()
    {
        $data = $this->supplierService->getRowsPaged("where:dist_tf_num>0;");
        $this->assign('data', $data);
        $this->display();
    }

    function supplier()
    {
        $id = I("get.id/d", 0);
        $data = $this->supplierService->getSupplier($id);
        if (empty($data)) {
            $this->error('找不到供应商！');
        }
        $this->assign($data);

        $client = M('DistributionClient')
            ->where("distributor_id='{$this->memberid}' AND supplier_id='{$id}'")
            ->find();
        $this->assign('client', $client);

        $fabrics = $this->getPoolTfs("supplier_id='{$id}'");
        $this->assign('fabrics', $fabrics);
        $this->display();
    }

    function apply_client_post()
    {
        if (IS_POST) {
            $id = I('post.id/d', 0);
            if ($id == 0) {
                $this->error("操作失败！");
            }

            if (M('BizMember')->where("id='{$id}' AND biz_status=1 AND biz_trash=0")->count() == 0) {
                $this->error("不存在这个供应商！");
            }

            $clientModel = M('DistributionClient');
            $client = $clientModel->where("supplier_id='{$id}' AND distributor_id='{$this->memberid}'")->find();
            if (!empty($client)) {
                if ($client['status']) {
                    $this->success("您已经是该供应商的客户了！", leuu("index"));
                } else {
                    if (M('DistributionTf')->where("supplier_id='{$id}' AND distributor_id='{$this->memberid}'")->count() > 0) {
                        $clientModel->where("id='{$client['id']}'")->setField('status', 1);
                        $this->success('您已经成为该供应商的客户了！', leuu("index"));
                    }
                }
            }


            $applyModel = M('DistributionClientApply');
            $apply = $applyModel->where("supplier_id='{$id}' AND status=0 AND distributor_id='{$this->memberid}'")->find();
            if (empty($apply)) {
                $result = $applyModel->add(array(
                    'supplier_id' => $id,
                    'distributor_id' => $this->memberid,
                    'status' => 0,
                    'create_time' => date('Y-m-d H:i:s'),
                ));
            } else {
                $result = $applyModel->save(array(
                    'id' => $apply['id'],
                    'modify_time' => date('Y-m-d H:i:s'),
                ));
            }

            if ($result !== false) {
                $this->success("申请已经发送！");
            } else {
                $this->error("操作失败！");
            }
        }
    }

    function apply_post()
    {
        if (IS_POST) {
            $id = I("post.id/d", 0);
            if ($id == 0) {
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
            if (empty($tf)) {
                $this->error('操作失败，面料池没有这个面料！');
            }

            if ($tf['supplier_id'] == $this->memberid) {
                $this->error('操作失败，您不能代理您自己的面料！');
            }

            if (M('DistributionTf')
                    ->where("source_id='{$id}' AND distributor_id='{$this->memberid}' AND status=1")
                    ->count() > 0) {
                $this->success('您已经在代理这款面料了！');
            }

            $applyModel = M('DistributionTfApply');
            $where = "source_id='{$id}' AND distributor_id='{$this->memberid}' AND status=0";
            if ($applyModel->where($where)->count() > 0) {
                $result = $applyModel->where($where)->setField('modify_time', date('Y-m-d H:i:s'));
            } else {
                $result = $applyModel->add(array(
                    'source_id' => $id,
                    'distributor_id' => $this->memberid,
                    'supplier_id' => $tf['supplier_id'],
                    'status' => 0,
                    'create_time' => date('Y-m-d H:i:s'),
                ));
            }

            if ($result === false) {
                $this->error('操作失败！');
            }

            $this->ajaxReturn(array(
                'status' => 1,
                'data' => $id,
                'info' => '申请已发送！'
            ));
        }
    }

    private function getPoolTfs($where = '')
    {
        $alias1 = 'mlg_distribution_tf';
        $alias2 = 'mlg_tf_pool_view';
        $countTf = M("DistributionTf")->field("COUNT({$alias1}.id)")
            ->where("{$alias2}.id={$alias1}.source_id AND {$alias1}.status=1 AND {$alias1}.distributor_id='{$this->memberid}'")
            ->buildSql();

        $field = "*,IF({$countTf}>0,1,0) as distributing";
        $order = "distributing ASC, id DESC";
        if ($where != '') {
            $where = "where:{$where};";
        }
        $list = $this->poolService->getRowsPaged("field:$field;order:$order;status:1;{$where}", 15);
        return $list;
    }

}