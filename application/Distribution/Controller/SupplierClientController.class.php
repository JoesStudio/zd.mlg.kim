<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-06-01
 * Time: 16:22
 */

namespace Distribution\Controller;


use Common\Controller\SupplierbaseController;
use Distribution\Service\TfService;
use Distribution\Service\ClientService;

class SupplierClientController extends SupplierbaseController
{
    protected $applyModel;
    protected $clientModel;
    protected $tfService;
    protected $clientService;

    public function __construct()
    {
        parent::__construct();
        $this->applyModel = M('DistributionClientApply');
        $this->clientModel = M('DistributionClient');
        $this->tfService = new TfService();
        $this->clientService = new ClientService();
    }

    public function index()
    {
        if (IS_AJAX) {
            $field = 'id,distributor_id,distributor_name,distributor_logo,level,remark,create_date';
            $strNow = "UNIX_TIMESTAMP(NOW())";
            $field .= ",getDistTotalAmount(id,0,NULL) as amount_all";
            $field .= ",getDistTotalAmount(id,{$strNow}-3600*24*30,NULL) as amount_30";
            $field .= ",getDistTotalAmount(id,{$strNow}-3600*24*90,NULL) as amount_90";
            $data['data'] = $this->clientService->getRowsNoPaged("field:$field;supplier_id:{$this->memberid};status:1;");

            foreach ($data['data'] as $k => $v) {
                $data['data'][$k]['avatar'] = get_avatar_url($v['distributor_logo']);
                $data['data'][$k]['setting_link'] = leuu('client', array('id' => $v['id']));
                $data['data'][$k]['report_link'] = leuu('SupplierReport/client', array('id' => $v['id']));
            }
            $data['status'] = 1;
            $data['sql'] = $this->clientService->clientView->getLastSql();
            $data['memberid'] = $this->memberid;
            $this->ajaxReturn($data);
        } else {
            $this->display();
        }
    }

    public function client()
    {
        $id = I('get.id/d', 0);
        if ($id == 0) {
            $this->redirect('index');
        }

        $client = $this->clientModel
            ->alias('client')
            ->field('client.*,biz.biz_name as distributor_name,biz.biz_logo as distributor_logo')
            ->join('__BIZ_MEMBER__ biz ON biz.id=client.distributor_id')
            ->where("client.id='{$id}' AND client.status=1")
            ->find();
        $this->assign('client', $client);

        $this->display();
    }

    public function set_level()
    {
        if (IS_POST) {
            $id = I('post.id/d', 0);
            $level = I('post.value/d', 1);
            $result = M('DistributionClient')
                ->where(array(
                    'id' => $id,
                    'supplier_id' => $this->memberid
                ))
                ->setField('level', $level);
            if($result !== false){
                $this->success('商户等级已更新！');
            }else{
                $this->error('操作失败！');
            }
        }
    }

    public function distfs($id)
    {
        $data['data'] = $this->tfService->getRowsNoPaged("distributor_id:{$id};status:1;");
        foreach ($data['data'] as $k => $v) {
            $data['data'][$k]['thumb'] = get_thumb_url($v['thumb'], true, 150);
        }
        $data['status'] = 1;
        $this->ajaxReturn($data);
    }

    public function tf_setting($id)
    {
        $where['source_id'] = $id;
        $where['supplier_id'] = $this->memberid;
        $priceList = M('DistributionWholesaleRule')
            ->where($where)
            ->getField('level,price');
        $this->ajaxReturn(array(
            'status' => 1,
            'data' => $priceList,
        ));
    }

    public function tf_setting_post()
    {
        if (IS_POST) {
            $id = I('post.id/d', 0);
            $level = I('post.level/d', 0);
            if ($id == 0) {
                $this->error('操作失败！');
            }
            if ($level < 1 || $level > 5) {
                $this->error('设置的等级不正确！');
            }

            $result = M('DistributionTf')
                ->where(array(
                    'id' => $id,
                    'supplier_id' => $this->memberid,
                ))
                ->setField('level', $level);
            if ($result !== false) {
                $this->success('已保存设置！');
            } else {
                $this->error('操作失败！');
            }
        }
    }

    public function application()
    {
        if (IS_AJAX) {
            $data['data'] = $this->applyModel
                ->alias("apply")
                ->field("apply.*,client.biz_name as distributor_name")
                ->where("apply.supplier_id='{$this->memberid}' AND apply.status=0")
                ->join("__BIZ_MEMBER__ client ON client.id=apply.distributor_id")
                ->order("IFNULL(apply.modify_time,apply.create_time) DESC")
                ->select();
            $data['status'] = 1;
            //$data['sql'] = $this->applyModel->getLastSql();
            $this->ajaxReturn($data);
        } else {
            $this->display();
        }
    }

    public function accept()
    {
        if (IS_POST) {
            $id = I('post.id/d', 0);
            $level = I('post.level/d', 1);
            $remark = I('post.remark');
            if ($level > 5 || $level < 1) {
                $level = 1;
            }
            $apply = $this->applyModel->where("id='{$id}' AND supplier_id='{$this->memberid}' AND status=0")->find();
            if (empty($apply)) {
                $this->error('操作失败，该申请或许已经失效！');
            }

            $client = $this->clientModel
                ->where("supplier_id='{$this->memberid}' AND distributor_id='{$apply['distributor_id']}'")
                ->find();

            $this->applyModel->startTrans();
            $this->applyModel->save(array(
                "id" => $id,
                "status" => 1,
            ));

            if (!empty($client)) {
                if ($client['status'] == 1) {
                    $this->success('已添加客户！');
                }

                $result = $this->clientModel->save(array(
                    'id' => $client['id'],
                    'level' => $level,
                    'remark' => $remark,
                    'status' => 1,
                ));
            } else {
                $result = $this->clientModel->add(array(
                    'supplier_id' => $apply['supplier_id'],
                    'distributor_id' => $apply['distributor_id'],
                    'level' => $level,
                    'remark' => $remark,
                    'status' => 1,
                ));
            }

            if ($result === false) {
                $this->applyModel->rollback();
                $this->error('操作失败！');
            } else {
                $this->applyModel->commit();
                $this->success('已添加客户！');
            }
        }
    }

    public function ignore()
    {
        if (IS_POST) {
            $id = I('post.id/d', 0);
            $result = $this->applyModel
                ->where("id='{$id}' AND supplier_id='{$this->memberid}' AND status=0")
                ->setField('status', 3);
            if ($result !== false) {
                $this->ajaxReturn(array(
                    'status' => 1,
                    'info' => '操作成功！',
                    'data' => $id
                ));
            } else {
                $this->error("操作失败!");
            }
        }
    }

}