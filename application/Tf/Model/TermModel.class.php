<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-01-07
 * Time: 16:38
 */

namespace Tf\Model;


use Common\Model\CommonModel;

class TermModel extends CommonModel
{
    protected $tableName = 'tf_terms';

    function terms($tag=''){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($where, $tag);
        }else{
            $tag=sp_param_lable($tag);
        }

        $field = !empty($tag['field']) ? $tag['field'] : '*';
        $order = !empty($tag['order']) ? $tag['order'] : 'listorder ASC,tf_term_id DESC';

        if(isset($tag['taxonomy'])){
            $where['taxonomy'] = $tag['taxonomy'];
        }

        $this->field($field)->where($where)->order($order);
        if(!empty($tag['limit'])){
            $this->limit($tag['limit']);
        }
        $rs = $this->select();
        $data = array();
        foreach($rs as $row){
            $data[$row['tf_term_id']] = $row;
        }
        return $data;

    }

    function getTerm($id){
        if(is_array($id)){
            $where = $id;
        }else{
            $where['tf_term_id'] = $id;
        }

        $data = $this->where($where)->find();

        $tf_ids = D('Tf/TermTf')
            ->where(array('tf_term_id'=>$data['tf_term_id']))
            ->order('listorder ASC, rid DESC')
            ->getField('tf_id', true);
        if(!empty($tf_ids)){
            $catModel = D('Tf/Cat');
            $catModel->alias('cat')->field('cat.*')->group('cat.id');
            $catModel->join('__TEXTILE_FABRIC__ tf ON tf.cid = cat.id','LEFT');
            $catModel->where(array('tf.id'=>array('IN',$tf_ids)));
            $rs = $catModel->select();
            foreach($rs as $row){
                $data['cats'][$row['id']] = $row;
            }
            if(!empty($data['cats'])){
                $catids = array();
                foreach($data['cats'] as $cat){
                    array_push($catids, $cat['id']);
                }
                $rs = D('Tf/Tf')->getTfNoPaged(array('cid'=>array('IN',$catids),'id'=>array('IN',$tf_ids)));
                foreach($rs as $row){
                    $data['cats'][$row['cid']]['fabrics'][$row['id']] = $row;
                }
            }
        }

        return $data;
    }
}