<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-01-07
 * Time: 16:38
 */

namespace Tf\Model;


use Common\Model\CommonModel;

class TermTfModel extends CommonModel
{
    protected $tableName = 'tf_term_relationships';

    function termTfs($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $tfModel = D('Tf/Tf');

        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
        }

        $field = !empty($tag['field']) ? $tag['field'] : 'tf.*,r.term_tf_id as term_id,r.listorder';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'created_at DESC,id DESC';

        $join = 'LEFT JOIN __TEXTILE_FABRIC__ tf ON tf.id = r.tf_id';

        $data['total'] = $this->alias('r')->join($join)->where($where)->count();

        $this->field($field)->alias('r')->join($join)->where($where)->order($order);
        if(empty($pageSize)){
            $this->limit($limit);
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
        }
        $rs = $this->select();

        $fabrics = array();
        if(!empty($rs)){
            $cids = array();
            $uids = array();
            foreach($rs as $row){
                $row['img'] = str_replace("&quot;", '"', $row['img']);
                $row['img'] = str_replace("'", '"', $row['img']);

                $row['img'] = json_decode($row['img'],true);
                $row['tf_code'] = $row['vend_id'].$row['cid'].$row['code'].$row['name_code'];
                $fabrics[$row['id']] = $row;
                array_push($cids, $row['cid']);
                array_push($uids, $row['vend_id']);
            }
            $cids = array_unique($cids);
            $uids = array_unique($uids);
            $cats = D('Tf/Cat')->cats(array('id'=>array('IN',$cids)));
            $members = D('BizMember')->getMembersNoPaged(array('id'=>array('IN',$uids)));


            foreach($fabrics as $key=>$row){
                $fabrics[$key]['cat'] = $cats[$row['cid']];
                $fabrics[$key]['supplier'] = $members[$row['vend_id']];
            }
        }

        $data['data'] = $fabrics;


        return $data;
    }

}