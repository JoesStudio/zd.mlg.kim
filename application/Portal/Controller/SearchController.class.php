<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2014 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
/**
 * 搜索结果页面
 */
namespace Portal\Controller;

use Common\Controller\HomebaseController;

class SearchController extends HomebaseController {
    //文章内页
    public function index() {
		$keyword = I("request.keyword");
		
		// if (empty($keyword)) {
		// 	$this -> error("关键词不能为空！请重新输入！");
		// }

		
		
        if(!empty($keyword)){

			
			//搜索面料
			$map = array();
            $map['name|code'] = array('LIKE','%'.$keyword.'%');
			$map['ispublic'] = 1;
        	$tf_list = D('Tf/TextileFabric')->getTfPaged($map);

			//搜索色卡
			$where = array();
			$where['card_name'] = array('LIKE','%'.$keyword.'%');
			$where['card_status'] = 20;
			$where['card_type'] = 2;
			$where['card_trash'] = 0;
        	$card_list = D('Colorcard/Colorcard')->getCardsPaged($where);

			//搜索面料商
			$condition = array();
			$condition['biz_name'] = array('LIKE','%'.$keyword.'%');
			$condition['biz_status'] = 1;
        	$supplier_list = D('BizMember')->getMembersPaged($condition);
			

			$search_result = array();
			$search_result['tf_list'] = $tf_list;
			$search_result['card_list'] = $card_list;
			$search_result['supplier_list'] = $supplier_list;


        }
        

		

         $this -> assign("search_result",$search_result);
		
		
		$this -> display(":search");
    }
    
    
}
