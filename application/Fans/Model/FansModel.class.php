<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-04
 * Time: 11:35
 */

namespace Fans\Model;


use Common\Model\CommonModel;

class FansModel extends CommonModel
{
    protected $tableName ="biz_fans";

    protected $_auto = array(
        //array(填充字段,填充内容,填充条件,附加规则)
        array('create_time','mGetDate',CommonModel:: MODEL_INSERT,'function'),
    );

    function fans($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);

            if (isset($tag['member_id'])) {
                $where['member_id'] = $tag['member_id'];
            }

            if(isset($tag['_string'])){
                $where['_string'] = $tag['_string'];
            }
        }
        
        $field = !empty($tag['field']) ? $tag['field'] : 'fans.*,info.nickname,info.mobile,info.email,
        info.desc,info.wechat,info.qq,
        info.address,info.birthday,info.avatar,info.signature,info.ispublic,
        province.name as province_name,city.name as city_name,district.name as district_name';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'fans.brower_date DESC';

        $join1 = '__USER_INFO__ info ON info.user_id = fans.user_id';
        $join2 = 'LEFT JOIN __AREAS__ district ON district.id = info.district';
        $join3 = 'LEFT JOIN __AREAS__ city ON city.id = district.parentId';
        $join4 = 'LEFT JOIN __AREAS__ province ON province.id = city.parentId';

        $data['total'] = $this->alias('fans')->join($join1)->join($join2)->join($join3)->join($join4)->where($where)->count();

        $this->alias('fans')->field($field)->join($join1)->join($join2)->join($join3)->join($join4)->where($where)->order($order);
        if(empty($pageSize)){
            $this->limit($limit);
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }
        $rs = $this->select();

        $data['data'] = array();
        if(!empty($rs)){
            foreach($rs as $row){
                $data['data'][$row['id']] = $row;
            }
        }

        return $data;
    }

    /* 获取分页的记录 */
    function getFansPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->fans($tag, $pageSize, $pagetpl, $tplName);
    }

    /* 获取不分页的记录 */
    function getFansNoPaged($tag=''){
        $data = $this->fans($tag);
        return $data['data'];
    }

    /*
     * 保存客户资料
     * @param array $data 要保存的数据
     * @return int
     */
    function saveFan($data){
        $result = $this->create($data);
        if($result !== false){
            $result = isset($this->data[$this->getPk()]) ? $this->save():$this->add();
            if($result === false){
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    function saveFans($data){
        $result = $this->create($data);
        if($result !== false){
            $result = isset($this->data[$this->getPk()]) ? $this->save():$this->add();
            if($result === false){
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    /*
     * 获取客户资料
     * @param array $id 需求id
     * @return int
     */
    function getFan($id){
        if(is_array($id)){
            $where = $id;
        }else{
            $where['id'] = $id;
        }

        $where['is_delete'] = isset($where['is_delete']) ? $where['is_delete']:0;
        
        $fans = $this->where($where)->find();
        
        return $fans;
    }

    public function getFans($id)
    {
        if (is_array($id)) {
            $where = $id;
        } else {
            $where['id'] = $id;
        }

        $where['is_delete'] = isset($where['is_delete']) ? $where['is_delete']:0;
        
        $fans = $this->where($where)->find();

        if ($fans) {
            $fans['userinfo'] = D('UserInfo')->getInfo(array('user_id' => $fans['user_id']));
        }
        
        return $fans;
    }

    function getOrderFans($where=array()){
        $order_model = D('Order/Order');
        $field = 'o.add_time as order_date,COUNT(o.order_id) as order_num,
        user.user_id,user.nickname,user.avatar,user.mobile,user.email,user.sex';
        $order = 'order_num DESC, o.add_time DESC';
        $join1 = '__USER_INFO__ user ON user.user_id=o.user_id';
        $join2 = 'LEFT JOIN __BIZ_FANS__ fans ON fans.user_id = o.user_id AND fans.member_id = o.supplier_id';
        $data = $order_model->alias('o')
            ->field($field)
            ->join($join1)
            ->join($join2)
            ->where($where)
            ->order($order)
            ->group('o.user_id')
            ->select();
        return $data;
    }

    public function setLevel($id, $level)
    {
        $result = $this->where(array('id' => $id))->setField('level', $level);
        if ($result === false) {
            $this->error = $this->getDbError();
        }
        return $result;
    }
}
