<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-12-16
 * Time: 10:57
 */

namespace Tf\Model;


use Common\Model\CommonModel;

class PropModel extends CommonModel
{
    protected $tableName = 'textile_fabric_prop';

    function SaveToProp($tf_id, $data){
        $EX = $data["ex"];
        $ex_key_name = $data["ex_key_name"];
        $ex_id = $data["ex_id"];

        if(!empty($EX)){
            //删除垃圾数据
            $sReturn =$this
                ->where(array("key_id"=>array("not in",join(",",array_filter(array_keys($EX)))),"tf_id"=>$tf_id))
                ->delete();
        }

        $result = 0;
        foreach ($EX as $k => $v) {
            unset($propData);

            $propData["id"]         = $ex_id[$k];
            $propData["tf_id"]      = $tf_id;
            $propData["key_id"]     = $k;
            $propData["key_name"]   = $ex_key_name[$k];

            if(is_array($v)){
                if(count($v)==1){
                    $propData["value_id"] = $v[0];
                } else {
                    $propData["value_text"] = join(",",$v);
                }
            } else {
                $propData["value_text"] = $v;
            }

            $result = $this->create($propData);
            if($result !== false){
                if(empty($propData['id'])){
                    $result = $this->add();
                }else{
                    $result = $this->save();
                }
                if($result === false){
                    $this->error = $this->getDbError();
                }
            }
        }
        return $result;
    }

}