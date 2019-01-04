<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-10
 * Time: 16:43
 */

namespace Common\Model;

use Think\Model\RelationModel;

class BizAuthModel extends CommonModel
{

    public $types = array(
        1   => '企业',
        2   => '个人',
    );
    public $statuses = array(
        0   => '未认证',
        1   => '已认证',
        2   => '正在审核',
        3   => '认证失败',
    );

    public function getAuthInfo($id){
        $where['auth.id'] = $id;
        $this->alias('auth')->field('auth.*,province.name as auth_province_name,
        city.name as auth_city_name,district.name as auth_district_name');
        $this->join('__AREAS__ province ON province.id = auth.auth_province','LEFT');
        $this->join('__AREAS__ city ON city.id = auth.auth_city','LEFT');
        $this->join('__AREAS__ district ON district.id = auth.auth_district','LEFT');
        $data = $this->where($where)->find();
        return $data;
    }

    public function getAuthByBm($member_id){
        $where['member_id'] = $member_id;
        return $this->getAuth($where);
    }

    /*
     * 获取未删除的记录
     * @param int $currentPage 当前分页
     * @param in $pageSize 分页记录数
     * @return array
     */
    public function auths($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
            
            if (isset($tag['auth_status'])) {
                $where['auth_status'] = $tag['auth_status'];
            }
            
            if (isset($tag['member_id'])) {
                $where['member_id'] = $tag['member_id'];
            }
        }

        $field = !empty($tag['field']) ? $tag['field'] : '*';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'id DESC';

        

        $data['total'] = $this->where($where)->count();

        $this->field($field)->where($where)->order($order);
        if(empty($pageSize)){
            $this->limit($limit);
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }

        $this->alias('auth')->field('auth.*,province.name as auth_province_name,
        city.name as auth_city_name,district.name as auth_district_name');
        $this->join('__AREAS__ province ON province.id = auth.auth_province','LEFT');
        $this->join('__AREAS__ city ON city.id = auth.auth_city','LEFT');
        $this->join('__AREAS__ district ON district.id = auth.auth_district','LEFT');
        
        $rs = $this->select();

        foreach($rs as $row){
            $data['data'][$row['member_id']] = $row;
        }

        return $data;
    }

    /*
     * 获得分页的订单列表
     */
    public function getAuthsPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->auths($tag, $pageSize, $pagetpl, $tplName);
    }

    /*
     * 获得不分页的订单表
     */
    public function getAuthsNoPaged($tag=''){
        $data =  $this->auths($tag);
        return $data['data'];
    }

    public function getAuth($id){
        if(is_array($id)){
            $where = $id;
        }else{
            $where['auth.id'] = $id;
        }
        $this->alias('auth')->field('auth.*,province.name as auth_province_name,
        city.name as auth_city_name,district.name as auth_district_name');
        $this->join('__AREAS__ province ON province.id = auth.auth_province','LEFT');
        $this->join('__AREAS__ city ON city.id = auth.auth_city','LEFT');
        $this->join('__AREAS__ district ON district.id = auth.auth_district','LEFT');
        $data = $this->where($where)->find();
        return $data;
    }

    public function addAuth($data){
        $result = $this->create($data, self::MODEL_INSERT);
        if($result !== false){
            $result = $this->add();
            if($result === false) $this->error = $this->getDbError();
        }
        return $result;
    }

    public function updateAuth($data){
        $result = $this->create($data);
        if($result !== false){
            $result = $this->save();
            if($result === false){
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }


    public function saveAuth($data){
        $result = $this->create($data);
        if($result !== false){
            $result = isset($this->data[$this->getPk()]) ? $this->save():$this->add();
            if($result === false){
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }
}
