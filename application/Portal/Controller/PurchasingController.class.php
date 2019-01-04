<?php 

namespace Portal\Controller;
use Common\Controller\HomebaseController;
/*
 *面料求购
*/
class PurchasingController extends HomebaseController {

        protected $demand_model;
	protected $quote_model;
	function _initialize(){
		parent::_initialize();
                $this->demand_model = M('Demand');
		$this->quote_model = M('Demand_quote');

	}

	Public function index(){

		$where=array("demand_trash"=>0,"demand_status"=>0);

		$count = $this->demand_model->where($where)->count();// 查询满足要求的总记录数
        $Page  = new \Think\Page($count,10);// 实例化分页类 
        //定制分页样式
        $Page->rollPage = 5;//数码页数量
        $Page->lastSuffix = false;// 最后一页不显示总页数
        $Page->setConfig('prev','上一页');
        $Page->setConfig('next','下一页');
        $Page->setConfig('first','首页');
        $Page->setConfig('last','尾页');          
        $Page->setConfig('theme',"%FIRST% %UP_PAGE%  %LINK_PAGE% %DOWN_PAGE% %END%");//自定义分页的位置
        $show = bootstrap_page_style($Page->show());// 调用bootstrap_page_style 函数输出定制分页样式，
    
        $demand_list = $this->demand_model
        ->alias('a')
        ->where($where)
        ->order('a.demand_created desc')
        ->limit($Page->firstRow.','.$Page->listRows)
        ->select();
     
        $this->assign('demand_list', $demand_list);
        $this->assign('page',$show);


		$this->display(":fabric-buy-list");
	}

	public function purchasing_single(){

		$demand_id = $_GET["demand_id"];
        $join1 = "LEFT JOIN __AREAS__ b ON b.id = a.demand_province";
        $join2 = "LEFT JOIN __AREAS__ c ON c.id = a.demand_city";
        $join3 = "LEFT JOIN __AREAS__ d ON d.id = a.demand_area";
		$demand = $this->demand_model->alias("a")->field("a.*,b.name as demand_province,c.name as demand_city,d.name as demand_area")->join($join1)->join($join2)->join($join3)->where(array("demand_id"=>$demand_id))->find();
        $this->assign("demand",$demand);

        /*报价列表*/
        $join4="LEFT JOIN __BIZ_MEMBER__ biz ON biz.id=quote.vend_id";

        $count = $this->quote_model->where(array("demand_id"=>$demand['demand_id']))->count();// 查询满足要求的总记录数
        $Page  = new \Think\Page($count,10);// 实例化分页类 
        //定制分页样式
        $Page->rollPage = 5;//数码页数量
        $Page->lastSuffix = false;// 最后一页不显示总页数
        $Page->setConfig('prev','上一页');
        $Page->setConfig('next','下一页');
        $Page->setConfig('first','首页');
        $Page->setConfig('last','尾页');          
        $Page->setConfig('theme',"%FIRST% %UP_PAGE%  %LINK_PAGE% %DOWN_PAGE% %END%");//自定义分页的位置
        $show = bootstrap_page_style($Page->show());

        $demand_quotes = $this->quote_model->alias("quote")->field('quote.*,biz.long_name')->join($join4)->where(array("demand_id"=>$demand['demand_id']))->order("demand_created desc")->limit($Page->firstRow.','.$Page->listRows)->select();

        $this->assign("demand_quotes",$demand_quotes);
        $this->assign('page',$show);

		$this->display(":fabric-buy-single");
	}

    public function quote_post(){
        if(IS_POST){
             if($this->quote_model->create()){
                if($this->quote_model->add()!==false){
                    $this->success("报价成功！");
                }else{
                    $this->error("报价失败！");   
                } 
             }else{
                 $this->error($this->quote_model->getError());
             }   
        }
}
}























 ?>