<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-04
 * Time: 11:35
 */

namespace Supplier\Model;


use Common\Model\CommonModel;

class ShareModel extends CommonModel
{
    protected $tableName ="share";


    function share($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);

            if (isset($tag['supplier_id'])) {
                $where['supplier_id'] = $tag['supplier_id'];
            }

            if(isset($tag['_string'])){
                $where['_string'] = $tag['_string'];
            }
        }
        
        $field = !empty($tag['field']) ? $tag['field'] : 'share.*,info.nickname,info.mobile,info.email,
        info.desc,info.wechat,info.qq,
        info.address,info.birthday,info.avatar,info.signature,info.ispublic';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'share.id DESC';

        $join1 = 'LEFT JOIN __USER_INFO__ info ON info.user_id = share.user_id';
    
        

        $data['total'] = $this->alias('share')->join($join1)->where($where)->count();

        $this->alias('share')->field($field)->join($join1)->where($where)->order($order);
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
                if($row['target_type']==1){
                    $data['data'][$row['id']]['fabric'] = D('Tf/Tf')->where(array('id'=>$row['target_id']))->find();
                }else{
                    $data['data'][$row['id']]['card'] = D('Colorcard/Colorcard')->where(array('card_id'=>$row['target_id']))->find();
                }
            }
        }

        return $data;
    }

    /* 获取分页的记录 */
    function getSharePaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->share($tag, $pageSize, $pagetpl, $tplName);
    }

    /* 获取不分页的记录 */
    function getShareNoPaged($tag=''){
        $data = $this->share($tag);
        return $data['data'];
    }

    /*
     * 保存分享资料
     * @param array $data 要保存的数据
     * @return int
     */
    function saveShare($data){
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
     * 获取分享资料
     * @param array $data 要保存的数据
     * @return int
     */
    public function getShares($id) {
        if (is_array($id)) {
            $where = $id;
        } else {
            $where['id'] = $id;
        }
        $shares = $this->where($where)->find();

        return $shares;
    }
}
