<?php
namespace Fans\Model;

use Common\Model\CommonModel;

class SuppliersModel extends CommonModel
{
    protected $tableName ="biz_fans";

    public function suppliers($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);

            if (isset($tag['user_id'])) {
                $where['user_id'] = $tag['user_id'];
            }

            if(isset($tag['_string'])){
                $where['_string'] = $tag['_string'];
            }
        }
        
        $field = !empty($tag['field']) ? $tag['field'] : 'fans.*,biz.biz_name,biz.biz_intro,biz.biz_logo,
        biz.biz_type,auth.auth_address as address,
        province.name as province_name,city.name as city_name,district.name as district_name';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'fans.brower_date DESC';

        $join1 = '__BIZ_MEMBER__ biz ON biz.id = fans.member_id';
        $join2 = 'LEFT JOIN __AREAS__ district ON district.id = auth.auth_district';
        $join3 = 'LEFT JOIN __AREAS__ city ON city.id = district.parentId';
        $join4 = 'LEFT JOIN __AREAS__ province ON province.id = city.parentId';
        $join5 = '__BIZ_AUTH__ auth ON auth.member_id = fans.member_id';

        $data['total'] = $this->alias('fans')
        ->join($join1)->join($join5)->join($join2)->join($join3)->join($join4)
        ->where($where)->count();

        $this->alias('fans')->field($field)
        ->join($join1)->join($join5)->join($join2)->join($join3)->join($join4)
        ->where($where)->order($order);
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
    function getSuppliersPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->suppliers($tag, $pageSize, $pagetpl, $tplName);
    }

    /* 获取不分页的记录 */
    function getSuppliersNoPaged($tag=''){
        $data = $this->suppliers($tag);
        return $data['data'];
    }
}
