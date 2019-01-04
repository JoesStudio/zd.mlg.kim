<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-01-20
 * Time: 11:35
 */

namespace Common\Model;


class UserInfoModel extends CommonModel
{
    //
    protected $tableName = 'user_info';

    function getInfoByUserId($user_id){
        return $this->getInfo(array('user_id'=>$user_id));
    }

    function getInfo($id){
        if(is_array($id)){
            $where = $id;
            if (isset($where['id'])) {
                $where['info.id'] = $where['id'];
                unset($where['id']);
            }
        }else{
            $where['info.id'] = $id;
        }
        unset($id);

        $field = 'info.*,province.name as province_name,city.name as city_name,district.name as district_name';
        $join1 = 'LEFT JOIN __AREAS__ district ON district.id = info.district';
        $join2 = 'LEFT JOIN __AREAS__ city ON city.id = district.parentId';
        $join3 = 'LEFT JOIN __AREAS__ province ON province.id = city.parentId';
        $data = $this->alias('info')->field($field)
            ->join($join1)->join($join2)->join($join3)
            ->where($where)->find();
        return $data;
    }

    function infos($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
            if(!empty($tag['_string'])){
                $where['_string'] = $tag['_string'];
            }
        }

        $field = !empty($tag['field']) ? $tag['field'] : 'info.*,
        province.name as province_name,city.name as city_name,district.name as district_name';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'id DESC';
        $alias = !empty($tag['alias']) ? $tag['alias'] : '';


        if(!empty($alias)){
            $this->alias($alias);
        }
        $data['total'] = $this->alias('info')->where($where)->count();

        if(!empty($alias)){
            $this->alias($alias);
        }
        $this->alias('info')->field($field)->where($where)->order($order);

        $join1 = 'LEFT JOIN __AREAS__ district ON district.id = info.district';
        $join2 = 'LEFT JOIN __AREAS__ city ON city.id = district.parentId';
        $join3 = 'LEFT JOIN __AREAS__ province ON province.id = city.parentId';
        $this->join($join1)->join($join2)->join($join3);

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
            $data['data'][$row['user_id']] = $row;
        }
        return $data;
    }

    function getInfosPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->infos($tag, $pageSize, $pagetpl, $tplName);
    }

    function getInfosNoPaged($tag=''){
        $data = $this->infos($tag);
        return $data['data'];
    }

    function saveInfo($data){
        $result = $this->create($data);
        if($result !== false){
            $result = isset($this->data[$this->getPk()]) ? $this->save():$this->add();
            if($result !== false){
                $id = isset($this->data[$this->getPk()]) ? $this->data[$this->getPk()]:$result;
                $info = $this->find($id);
                if (!empty($info)) {
                    $data = array(
                        'id'        => $info['user_id'],
                        'nickname'  => $info['nickname'],
                        'sex'       => $info['sex'],
                        'user_email'=> $info['email'],
                        'user_mobile'=> $info['mobile'],
                        'avatar'    => $info['avatar'],
                    );
                    D('Users')->saveUser($data);
                }
            }else{
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }
}