<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-07
 * Time: 14:56
 */

namespace Colorcard\Model;



use Common\Model\CommonModel;

class ColorcardModel extends CommonModel
{

    public $statuses = array(
        '0'     => '草稿',
        '10'    => '待定稿',
        '20'    => '已定稿',
        '99'    => '',
    );

    //自动验证
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('card_no', 'require', '请填写色卡编号！', 1, 'regex', CommonModel:: MODEL_UPDATE ),
        array('supplier_id', 'require', '请选择供应商！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        array('card_type', 'require', '请选择色卡类型', 1, 'regex', CommonModel:: MODEL_INSERT ),
        array('card_tpl', 'require', '请选择色卡模板！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        array('card_name', 'require', '请填写品名！', 1, 'regex', CommonModel:: MODEL_UPDATE ),
    );

    //自动完成
    protected $_auto = array(
        //array(填充字段,填充内容,填充条件,附加规则)
        array('card_status','0',CommonModel:: MODEL_INSERT),
        array('card_trash','0',CommonModel:: MODEL_INSERT),
        array('card_price','0.00',CommonModel:: MODEL_INSERT),
        array('create_date','mGetDate',CommonModel:: MODEL_INSERT,'callback'),
        array('modify_date','mGetDate',CommonModel::MODEL_UPDATE,'callback')
    );

    /*
     * 获取未删除的记录
     * @param int $currentPage 当前分页
     * @param in $pageSize 分页记录数
     * @return array
     */
    function cards($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);

            if(!empty($tag['supplier_id'])){
                $where['supplier_id'] = $tag['supplier_id'];
            }
            if(!empty($tag['card_type'])){
                $where['card_type'] = $tag['card_type'];
            }
            if(!empty($tag['ispublic'])){
                $where['ispublic'] = $tag['ispublic'];
            }

            if(!empty($tag['card_status'])){
                $where['card_status'] = $tag['card_status'];
            }
        }
        if (isset($where['field'])) unset($where['field']);
        if (isset($where['limit'])) unset($where['limit']);
        if (isset($where['order'])) unset($where['order']);

        $field = !empty($tag['field']) ? $tag['field'] : 'c.*,biz.biz_name';
        $order = !empty($tag['order']) ? $tag['order'] : 'create_date DESC';


        $where['card_trash'] = isset($tag['card_trash']) ? $tag['card_trash']:0;

        $join1 = 'LEFT JOIN __BIZ_MEMBER__ biz ON biz.id=c.supplier_id';

        foreach ($where as $key => $value) {
            if (in_array($key, array('field', 'limit', 'order','_string'))) continue;
            if (strpos($key, '.') === false) {
                $where["c.$key"] = $value;
                unset($where[$key]);
            }
        }

        $data['total'] = $this->alias('c')->where($where)->count();
        $this->alias('c')->field($field)->where($where)->order($order);
        $this->join($join1);
        if(empty($pageSize)){
            if (!empty($tag['limit'])) {
                $this->limit($tag['limit']);
            }
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }
        $rs = $this->select();

        $ids = array();
        $tpl_ids = array();
        $tmp = array();
        foreach($rs as $key=>$row){
            array_push($ids, $row['card_id']);
            array_push($tpl_ids, $row['card_tpl']);
            $tmp[$row['card_id']] = $row;
        }
        if(!empty($ids)){
            $tpls = D('Colorcard/Tpl')->getTplsNoPaged(array('tpl_id' => array('IN', $tpl_ids)));
            $page_groups = D('Colorcard/Page')->getPages(array('card_id'=>array('IN',$ids)));
            foreach($tmp as $card_id => $card){
                $tmp[$card_id]['tpl'] = $tpls[$card['card_tpl']];
                $tmp[$card_id]['pages'] = $page_groups[$card_id];
            }
        }

        $data['data'] = $tmp;

        return $data;
    }

    function getCardsPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->cards($tag, $pageSize, $pagetpl, $tplName);
    }

    function getCardsNoPaged($tag=''){
        $data = $this->cards($tag);
        return $data['data'];
    }

    public function saveCard($data)
    {
        $result = $this->create($data);
        if ($result !== false) {
            $result = isset($this->data[$this->getPk()]) ? $this->save():$this->add();
            if ($result === false) {
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    /*
     * 添加色卡
     * @param array $data 要插入的数据
     * @return int
     */
    function addCard($data){
        if($this->create($data)){
            $result = $this->add();
            if($result){
                $this->addPages($result, $data['pages']);
            }
            return $result;
        }else{
            return -1;
        }
    }

    function addPages($card_id,$data){
        $result = 0;
        $_model = D('ColorcardPage');
        foreach($data as $page){
            $page['card_id'] = $card_id;
            $result = $_model->add($page);
            if($result){
                $this->addItems($result,$page['items']);
            }
        }
        return $result;
    }

    function addItems($page_id,$data){
        $result = 0;
        $_model = D('ColorcardItem');
        foreach($data as $item){
            $item['page_id'] = $page_id;
            $result = $_model->add($item);
        }
        return $result;
    }

    /*
     * 修改色卡
     * @param array $data 要插入的数据
     * @return int
     */
    function updateCard($data){
        if($this->create($data)){
            $result = $this->save();

            if($result){
                if($data['pages']){
                    $oldData = $this->getCard($data['card_id']);

                    $oldPageIds = array_keys($oldData['pages']);
                    $oldItemIds = array();
                    foreach($oldData['pages'] as $page){
                        $oldItemIds = array_merge($oldItemIds, array_keys($page['items']));
                    }

                    $pages_model = D('ColorcardPage');
                    $items_model = D('ColorcardItem');
                    $updatePageIds = array();
                    //$updateItemIds = array();
                    foreach($data['pages'] as $page){
                        if(isset($page['page_id'])){
                            $pages_model->save($page);
                            array_push($updatePageIds, $page['page_id']);
                            foreach($page['items'] as $item){
                                $items_model->save($item);
                                //array_push($updateItemIds, $item['item_id']);
                            }
                        }else{
                            $page['card_id'] = $data['card_id'];
                            $page['page_id'] = $pages_model->add($page);
                            foreach($page['items'] as $item){
                                if(is_null($item['tf_id'])){
                                    $item['tf_id'] = 0;
                                }
                                $item['page_id'] = $page['page_id'];
                                $items_model->add($item);
                            }
                        }
                    }
                    $deletePageIds = array_diff($oldPageIds, $updatePageIds);

                    if($deletePageIds){
                        $pages_model->where(array('page_id'=>array('in',$deletePageIds)))->delete();
                        $items_model->where(array('page_id'=>array('in',$deletePageIds)))->delete();
                    }
                }
            }
            return $result;
        }else{
            return -1;
        }
    }

    /*
     * 获取色卡
     */
    function getCard($id){
        if(is_array($id)){
            $where = $id;
        }else{
            $where['cards.card_trash'] = 0;
            $where['cards.card_id'] = $id;
        }

        $card = $this
            ->alias('cards')
            ->field('cards.*,biz.biz_name')
            ->join('__BIZ_MEMBER__ biz ON biz.id = cards.supplier_id')
            ->where($where)
            ->find();
        if($card){
            $card['member'] = D('BizMember')->find($card['supplier_id']);

            $card['thumb'] = $card['frontcover'];

            if($card['custom_frontcover'] > 0){
                $card['frontcover'] = M('ColorcardTplPage')->find($card['custom_frontcover']);
                if(!empty($card['frontcover'])){
                    $card['frontcover']['smeta'] = json_decode($card['frontcover']['smeta'],true);
                    $card['frontcover']['data'] = json_decode($card['custom_frontcover_smeta'],true);
                }
            }
            if($card['custom_backcover'] > 0){
                $card['backcover'] = M('ColorcardTplPage')->find($card['custom_backcover']);
                if(!empty($card['backcover'])){
                    $card['backcover']['smeta'] = json_decode($card['backcover']['smeta'],true);
                    $card['backcover']['data'] = json_decode($card['custom_backcover_smeta'],true);
                }
            }

            $card['tpl'] = D('Colorcard/Tpl')->find($card['card_tpl']);

            $card['pages'] = D('Colorcard/Page')
                ->where(array('card_id'=>$card['card_id']))
                ->order('listorder ASC')
                ->getField('page_id,card_id,listorder,page_tpl,page_type,page_front_bgurl,page_back_bgurl');

            if($card['pages']){
                $pageIds = array_keys($card['pages']);
                $items = D('Colorcard/Item')
                    ->where(array('page_id'=>array('in',$pageIds)))
                    ->order('listorder ASC')
                    ->select();
                foreach($items as $key=>$item){
                    $item['item_fabric'] = json_decode($item['item_fabric'], true);
                    $item['link'] = $item['item_fabric']['tf_code'] ?
                        leuu('Tf/Tf/fabric', array('tfsn'=>$item['item_fabric']['tf_code']), false, true):
                        leuu('Tf/Tf/fabric', array('id'=>$item['tf_id'],'sr'=>$item['tf_source']), false, true);
                    $item['link'] = $item['tf_id'] > 0 ? $item['link']:'';
                    $card['pages'][$item['page_id']]['items'][$item['item_id']] = $item;
                }
            }

        }
        return $card;
    }

    /*
     * 设置状态
     */
    function setStatus($id, $status){
        if(is_array($id)){
            $where = $id;
        }else{
            $where['card_id'] = $id;
        }
        return $this->where($where)->setField('card_status', $status);
    }

    /*
     * 设置未定稿
     */
    function setUnconfirmed($id){
        return $this->setStatus($id, 0);
    }

    /*
     * 设置正在定稿
     */
    function setConfirming($id){
        return $this->setStatus($id, 10);
    }

    /*
     * 设置已定稿
     */
    function setConfirmed($id){
        return $this->setStatus($id, 20);
    }

    function getCardByCode($code){
        $where['cards.card_trash'] = 0;
        $where['cards.card_no'] = $code;
        return $this->getCard($where);
    }

    function checkTfLimit($cardId,$pageTplId=0){
        $limit = $this->where("card_id=$cardId")->getField("tf_limit");
        if($limit == 0){
            return true;
        }
        $join1 = "INNER JOIN __COLORCARD_PAGE__ page ON page.card_id=card.card_id";
        $join2 = "INNER JOIN __COLORCARD_ITEM__ item ON item.page_id=page.page_id";
        $tfCount = $this->alias('card')->join($join1)->join($join2)
            ->where("(page.page_type=1 OR page.page_type=3) AND card.card_id=$cardId")
            ->count();
        if($tfCount > $limit){
            return false;
        }

        if($pageTplId > 0){
            $increase = M('ColorcardTplPage')
                ->where("(pg_type=1 OR pg_type=3) AND pg_id=$pageTplId")
                ->getField("pg_item_num");
            if($increase > 0 && ($tfCount + $increase) > $limit){
                return false;
            }
        }
        return true;
    }

    //获取操作人ID
    function mGetOperatorId(){
        if(MODULE_NAME == 'Admin'){
            $uid = sp_get_current_admin_id();
        }else{
            $uid = sp_get_current_userid();
        }
        return $uid;
    }

    //获取操作人名字
    function mGetOperator(){
        if(MODULE_NAME == 'Admin'){
            $uid = sp_get_current_admin_id();
            $user = M('Users')->find($uid);
        }else{
            $user = sp_get_current_user();
        }
        $name = empty($user['nickname']) ? $user['nickname']:$user['user_login'];
        return $name;
    }

    //用于获取时间，格式为2012-02-03 12:12:12,注意,方法不能为private
    function mGetDate() {
        return date('Y-m-d H:i:s');
    }
}