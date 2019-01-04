<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-18
 * Time: 14:38
 */

namespace Address\Model;


use Common\Model\CommonModel;

class AddressModel extends CommonModel
{
    protected $tableName = 'user_address';

    //自动验证
    protected $_validate = array(
        //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
        array('address_id', 'require', '缺少地址id！', 1, 'regex', CommonModel:: MODEL_UPDATE ),
        array('address_name', 'require', '请填写地址名称！', 0, 'regex', CommonModel:: MODEL_BOTH ),
        array('user_id', 'require', '缺少所属用户！', 1, 'regex', CommonModel:: MODEL_INSERT ),
        array('province', 'require', '请选择所在的地区！', 1, 'regex', CommonModel:: MODEL_BOTH ),
        array('city', 'require', '请选择所在的地区！', 1, 'regex', CommonModel:: MODEL_BOTH ),
        array('district', 'require', '请选择所在的地区！', 1, 'regex', CommonModel:: MODEL_BOTH ),
        array('address', 'require', '请填写详细地址！', 1, 'regex', CommonModel:: MODEL_BOTH ),
        array('consignee', 'require', '请填写联系人姓名！', 1, 'regex', CommonModel:: MODEL_BOTH ),
        array('mobile', 'require', '请填写联系手机！', 1, 'regex', CommonModel:: MODEL_BOTH ),
    );

    protected $_auto = array(
        //array(填充字段,填充内容,填充条件,附加规则)
        array('country','0',CommonModel:: MODEL_INSERT),
        array('created_time','time',CommonModel:: MODEL_INSERT,'function'),
        array('modified_time','time',CommonModel:: MODEL_BOTH,'function'),
    );

    /*
     * 获取未删除的记录
     * @param int $currentPage 当前分页
     * @param in $pageSize 分页记录数
     * @return array
     */
    function addresses($tag='', $where=array(), $pageSize=0, $pagetpl='', $tplName='default'){
        $where=is_array($where)?$where:array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
        }

        $field = !empty($tag['field']) ? $tag['field'] : 'ad.*,user.nickname,user_login,
                province.name as province_name,city.name as city_name,district.name as district_name';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'ad.address_id DESC';

        if(isset($tag['user_id'])){
            $where['user_id'] = $tag['user_id'];
        }

        if(isset($where['user_id'])){
            $order = 'FIELD(ad.is_default,1,0)';
        }

        $join2 = 'LEFT JOIN __AREAS__ province ON province.id = ad.province';
        $join3 = 'LEFT JOIN __AREAS__ city ON city.id = ad.city';
        $join4 = 'LEFT JOIN __AREAS__ district ON district.id = ad.district';
        $join5 = 'LEFT JOIN __USERS__ user ON user.id = ad.user_id';

        $data['total'] = $this->where($where)->count();
        $this->field($field)->alias('ad')
            ->join($join2)
            ->join($join3)
            ->join($join4)
            ->join($join5)
            ->where($where)
            ->order($order);
        if(empty($pageSize)){
            $this->limit($limit);
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
        }
        $rs = $this->select();
        $data['data'] = array();
        foreach($rs as $row){
            $data['data'][$row['address_id']] = $row;
        }
        return $data;
    }

    function getAddressPaged($tag='', $where=array(), $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->addresses($tag, $where, $pageSize, $pagetpl, $tplName);
    }

    function getAddressNoPaged($tag='', $where=array()){
        $data = $this->addresses($tag, $where);
        return $data['data'];
    }

    function getAddress($id){
        if(is_array($id)){
            $where = $id;
            if(isset($where['address_id'])){
                $where['ad.address_id'] = $where['address_id'];
                unset($where['address_id']);
            }
            if(isset($where['user_id'])){
                $where['ad.user_id'] = $where['user_id'];
                unset($where['user_id']);
            }
        }else{
            $where['ad.address_id'] = $id;
        }
        $address =  $this
            ->field('ad.*,user.nickname,user_login,
                province.name as province_name,city.name as city_name,district.name as district_name')
            ->alias('ad')
            ->join('__AREAS__ province ON province.id = ad.province','LEFT')
            ->join('__AREAS__ city ON city.id = ad.city','LEFT')
            ->join('__AREAS__ district ON district.id = ad.district','LEFT')
            ->join('__USERS__ user ON user.id = ad.user_id','LEFT')
            ->where($where)
            ->find();
        return $address;
    }

    /*
     * 添加
     * @param array $data 要插入的数据
     * @return int
     */
    function addAddress($data){
        $result = $this->create($data);
        if($result !== false){
            $result = $this->add();
            if($result !== false){
                if($data['is_default'] == 1){
                    $this->setDefault($result);
                }
            }else{
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    function setDefault($address_id){
        $user_id = $this->where(array('address_id'=>$address_id))->getField('user_id');
        $this->where(array('user_id'=>$user_id,'address_id'=>array('neq',$address_id)))
            ->setField('is_default',0);
        $result = $this->where(array('address_id'=>$address_id))->setField('is_default',1);
        if($result === false){
            $this->error = $this->getDbError();
        }
        return $result;
    }

    /*
     * 修改
     * @param array $data 要插入的数据
     * @return int
     */
    function updateAddress($data){
        $result = $this->create($data);
        if($result !== false){
            $result = $this->save();
            if($result !== false){
                if($data['is_default'] == 1){
                    $this->setDefault($data['address_id']);
                }
            }else{
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    /*
     * 删除地址
     */
    function deleteAddress($id){
        if(is_array($id)){
            $where = $id;
        }else{
            $where['address_id'] = $id;
        }
        $result = $this->where($where)->delete();
        if($result === false){
            $this->error = $this->getDbError();
        }
        return $result;
    }

    function check_access($user_id, $address_id){
        return $this->where(array('user_id'=>$user_id,'address_id'=>$address_id))->count();
    }

}