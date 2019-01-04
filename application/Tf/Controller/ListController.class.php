<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2014 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
/**
 * 列表页
 */
namespace Tf\Controller;

use Common\Controller\HomebaseController;

class ListController extends HomebaseController {

    public function index() {

        if(sp_is_mobile() && !IS_AJAX){
            $materialMenu = D("TextileFabricMaterial")->order("order_index desc")->select();
            $this->assign("material_menu", $materialMenu);

            $materialMenu = D("TextileFabricView")->select();
            $this->assign("view_menu", $materialMenu);

            $weaveMenu = D("TextileFabricWeave")->select();
            $this->assign("weave_menu", $weaveMenu);

            $materialMenu = D("TextileFabricCraft")->order("order_index desc")->select();
            $this->assign("craft_menu", $materialMenu);

            $this->display();
            exit();
        }

        $color = I("request.color");  //面料类型
        $idArr = array(0);
        $map = array();
        $map['ispublic'] = 1;
        $map['on_sale'] = 1;
        $map['limit'] = (I("post.p", 1) - 1) * 20 . ", 20";


        if ($color) {
            $data = D("TextileFabricSku")->field("value_text, tf_id")->select();
            foreach ($data as $key => $value) {
                $color2 = $value['value_text'];
                if (is_color_range($color, $color2)) {
                    $idArr[] = $value['tf_id'];
                }
            }

            $map['id'] = array("in", $idArr);

            $_model = D('TextileFabric');

            $map['field'] = 'tf.*,CONCAT(tf.vend_id,tf.cat_code,tf.name_code,tf.code) as tf_code
        ,IFNULL(shelves.on_sale,0) as on_sale';
            $map['field'] .= ',(SELECT COUNT(rec_id) FROM '.C('DB_PREFIX')
                .'collect_goods where goods_id=tf.id AND user_id='.sp_get_current_userid().
                ' AND type=1) as is_collected';

            //最近上新
            if(isset($late_time) && !empty($late_time)){
                $after_time = time()-$late_time;
                $map['UNIX_TIMESTAMP(tf.created_at)'] = array('egt',$after_time);
            }

            $list = $_model->getTfPaged($map, 20);
            $this -> assign("list",$list);

        } else {

            $_model = D('TextileFabric');
            $cats_model = M("TextileFabricCats");

            $page_size = I("request.ps",20);
            $late_time = I("request.ts");
            $catid = I("request.id");

            $materialtype = I("request.materialtype");  //面料类型
            if ($materialtype) {
                $map['material_id'] = array("IN", $materialtype);
            }

            $submaterialtype = I("request.submaterialtype");  //面料类型
            if ($materialtype) {
                $map['sub_material_id'] = array("IN", $submaterialtype);
            }

            $viewType = I("request.viewtype");  //面料类型
            if ($viewType) {
                $map['view_id'] = array("IN", $viewType);
            }

            $craftType = I("request.crafttype");  //面料类型
            if ($craftType) {
                $map['craft_id'] = array("IN", $craftType);
            }

            $weaveType = I("request.weavetype");  //面料类型
            if ($weaveType) {
                $map['weave_id'] = array("IN", $weaveType);
            }

            $yarnAndVisualType = I("request.yarn_and_visual");  //纱线类型
            if ($yarnAndVisualType) {
                $map2 = [];
                $map2['_logic'] = 'or';
                foreach($yarnAndVisualType as $value) {
                    $tmpMap["yarn_id"] = $value[0];
                    $tmpMap['visual_id'] = $value[1];
                    $tmpMap['_logic'] = 'and';

                    $map2[] = $tmpMap;
                }

                $map['_complex'] = $map2;
            }

            $where = array();
            if(!empty($catid)){
                $catids = D('Tf/Cat')->getAllSubIds($catid);
                array_push($catids, $catid);
                if(!empty($catids)){
                    $where['cid'] = array('IN',$catids);
                }
            }

            $data=$cats_model->where($where)->find();
            $this -> assign("title",$data["title"]);


//            $limit = I("request.p") ? I("request.p") - 1 . ", 20" : 0 . ", 20";

//            $map['limit'] = $limit;
            $search = I('request.search/s','');
            if(!empty($search)){
                $map['name|tf.code|tf.spec|tf.material|tf.component|tf.function|tf.purpose'] = array('LIKE','%'.$search.'%');
            }

            $orderBy = I("request.orderby");
            if ($orderBy == 1) {
                $map['order'] = "tf.price ASC";
            } else if ($orderBy == 2) {
                $map['order'] = "tf.price desc";
            } else if ($orderBy == 3) {
                $map['order'] = "tf.created_at ASC";
            } else if ($orderBy == 4) {
                $map['order'] = "tf.created_at desc";
            }

            if(!empty($where)) $map['_complex'] = $where;

            $map['field'] = 'tf.*,CONCAT(tf.vend_id,tf.cat_code,tf.name_code,tf.code) as tf_code
        ,IFNULL(shelves.on_sale,0) as on_sale';
            $map['field'] .= ',(SELECT COUNT(rec_id) FROM '.C('DB_PREFIX')
                .'collect_goods where goods_id=tf.id AND user_id='.sp_get_current_userid().
                ' AND type=1) as is_collected';

            //最近上新
            if(isset($late_time) && !empty($late_time)){
                $after_time = time()-$late_time;
                $map['UNIX_TIMESTAMP(tf.created_at)'] = array('egt',$after_time);
            }

            $list = $_model->getTfPaged($map,$page_size);
            $this -> assign("list",$list);
        }

//        var_dump($list['data']);

        if(sp_is_mobile() && IS_AJAX){
            $html = $this->fetch('more');
            $list['status'] = 1;
            $list['html'] = $html;
            $this->ajaxReturn($list);
        }else{
            $this -> display();
        }
    }

    //手机版上的最近上新面料
    public function new_online() {
         if(sp_is_mobile() && !IS_AJAX){
            $this->display(':new_online/index');
            exit();
        }
        $_model = D('TextileFabric');
        $cats_model = M("TextileFabricCats");
        
        $page_size = I("request.ps", 20);
        $late_time = I("request.ts");
        $catid = I("request.id");


        $where = array();
        if(!empty($catid)){
            $catids = D('Tf/Cat')->getAllSubIds($catid);
            array_push($catids, $catid);
            if(!empty($catids)){
                $where['cid'] = array('IN',$catids);
            }
        }
        
        $data=$cats_model->where($where)->find();
        $this -> assign("title",$data["title"]);


        $map = array();
        $search = I('request.search/s','');
        if(!empty($search)){
            $map['name|tf.code|tf.spec|tf.material|tf.component|tf.function|tf.purpose'] = array('LIKE','%'.$search.'%');
        }
        if(!empty($where)) $map['_complex'] = $where;

        $map['field'] = 'tf.*,CONCAT(tf.vend_id,tf.cat_code,tf.name_code,tf.code) as tf_code
        ,IFNULL(shelves.on_sale,0) as on_sale';
        $map['field'] .= ',(SELECT COUNT(rec_id) FROM '.C('DB_PREFIX')
            .'collect_goods where goods_id=tf.id AND user_id='.sp_get_current_userid().
            ' AND type=1) as is_collected';


        $map['ispublic'] = 1;

        $map['on_sale'] = 1;

        //上新1月个内
        $after_time = time()-2592000;
        $map['UNIX_TIMESTAMP(created_at)'] = array('egt',$after_time);

        $list = $_model->getTfPaged($map,$page_size);
        $this -> assign("list",$list);

        if(sp_is_mobile() && IS_AJAX){
            $html = $this->fetch(':new_online/more');
            $list['status'] = 1;
            $list['html'] = $html;
            $this->ajaxReturn($list);
        }else{
            $this -> display(':new_online/index');
        }
    }

    public function form() {
        $this->display("List:form");
    }

    public function submit_form() {
        $data = I("post.data");

        if (!$data['name']) {
            $resData = array(
                "code" => "400",
                "message" => "请填写档口名称"
            );
        }

        if (!$data['address']) {
            $resData = array(
                "code" => "400",
                "message" => "请填写档口地址"
            );
        }

        if (!$data['phone']) {
            $resData = array(
                "code" => "400",
                "message" => "请填写联系电话"
            );
        }

        if (!$data['time']) {
            $resData = array(
                "code" => "400",
                "message" => "请选择上门取件时间"
            );
        }

        //写入数据
        D("RequestForm")->add(array(
            "name" => $data['name'],
            "address" => $data['address'],
            "phone" => $data['phone'],
            "pick_time" => $data['time'],
            "ctime" => date("Y-m-d H:i:s", time())
        ));

        $resData = array(
            "code" => "200",
            "message" => "提交成功"
        );

        $this->ajaxReturn($resData);
    }
}
