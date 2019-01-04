<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-06-21
 * Time: 16:43
 */

namespace Distribution\Controller;


use Common\Controller\SupplierbaseController;
use Distribution\Service\PoolService;
use Tf\Service\TfUnionService;

class SupplierReportController extends SupplierbaseController
{
    protected $poolService;
    protected $tfUnionService;
    public function __construct()
    {
        parent::__construct();
        $this->poolService = new PoolService();
        $this->tfUnionService = new TfUnionService();
    }

    public function tf(){
        $id = I('get.id/d',0);
        if(IS_AJAX){
            $clientView = M('DistributionClientView');
            $field = 'id,distributor_id,distributor_name,distributor_logo,level,status,remark,create_date';
            $strNow = "UNIX_TIMESTAMP(NOW())";
            $field .= ",getDistTotalAmount(id,0,NULL) as amount_all";
            $field .= ",getDistTotalAmount(id,{$strNow}-3600*24*30,NULL) as amount_30";
            $field .= ",getDistTotalAmount(id,{$strNow}-3600*24*90,NULL) as amount_90";
            $clients = $clientView
                ->field($field)
                ->where("supplier_id='{$this->memberid}' AND status=1")
                ->select();
            foreach($clients as $k=>$v){
                $clients[$k]['avatar'] = get_avatar_url($v['distributor_logo']);
            }

            $data['data'] = $clients;
            $data['status'] = 1;
            //$data['tf'] = $tf;
            $this->ajaxReturn($data);
        }else{
            $field = 'id,name,img,spec,width,weight,material,component,function,purpose,tf_code,on_sale';
            $strNow = "UNIX_TIMESTAMP(NOW())";
            $field .= ",getDistTfTotalAmount(id,0,NULL) as amount_all";
            $field .= ",getDistTfTotalAmount(id,{$strNow}-3600*24*30,NULL) as amount_30";
            $field .= ",getDistTfTotalAmount(id,{$strNow}-3600*24*90,NULL) as amount_90";
            $field .= ",getDistTfTotalNumber(id,0,NULL) as number_all";
            $field .= ",getDistTfTotalNumber(id,{$strNow}-3600*24*30,NULL) as number_30";
            $field .= ",getDistTfTotalNumber(id,{$strNow}-3600*24*90,NULL) as number_90";

            $tf = $this->poolService->poolView->field($field)
                ->where("id='{$id}'")
                ->find();
            if(empty($tf)){
                $this->error('该面料不存在！');
            }
            $tf['img'] = json_decode($tf['img'], true);

            $this->assign($tf);
            $this->display();
        }
    }

    public function client(){
        if(IS_AJAX){
            $id = I('get.id/d',0);
            $field = 'source_id as id,source_name as name,source_tf_code as tf_code,img';
            $strNow = "UNIX_TIMESTAMP(NOW())";
            $field .= ",getDistTfTotalAmount(source_id,0,NULL) as amount_all";
            $field .= ",getDistTfTotalAmount(source_id,{$strNow}-3600*24*30,NULL) as amount_30";
            $field .= ",getDistTfTotalAmount(source_id,{$strNow}-3600*24*90,NULL) as amount_90";
            $data['data'] = $this->tfUnionService
                ->getRowsNoPaged("field:$field;source:0;source_supplier_id:{$this->memberid};supplier_id:{$id};status:1;");
            foreach($data['data'] as $k=>$v){
                $data['data'][$k]['thumb'] = get_thumb_url($v['thumb'], true, 150);
            }
            $data['status'] = 1;
            $this->ajaxReturn($data);
        }else{
            $id = I('get.id/d',0);
            $clientView = M('DistributionClientView');
            $field = 'id,distributor_id,distributor_name,distributor_logo,level,remark,create_date';
            $strNow = "UNIX_TIMESTAMP(NOW())";
            $field .= ",getDistTotalAmount(id,0,NULL) as amount_all";
            $field .= ",getDistTotalAmount(id,{$strNow}-3600*24*30,NULL) as amount_30";
            $field .= ",getDistTotalAmount(id,{$strNow}-3600*24*90,NULL) as amount_90";

            $client = $clientView->field($field)->where("id='{$id}'")->find();
            if(empty($client)){
                $this->error('该客户不存在！');
            }

            $this->assign($client);
            $this->display();
        }
    }

}