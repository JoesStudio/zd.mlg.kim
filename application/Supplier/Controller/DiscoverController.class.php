<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-01-23
 * Time: 8:59
 */

namespace Supplier\Controller;


use Common\Controller\SupplierbaseController;

class DiscoverController extends SupplierbaseController
{
    function index(){
        $filter = I('request.filter');
        $action = $filter['action'];
        $types = $filter['type'];

        if($action == 'collect'){
            $m_action = D('Collect/Collect');
        }else{
            $m_action = D('History');
        }
        if(!empty($types)){
            $_string = "_string:type IN(".implode(',',$types).");";
        }
        $uids = $m_action->getUserIds("supplier_id:".$this->memberid.";$_string group:user_id;");
        $list = array();
        if(!empty($uids)){
            $uids = implode(',',$uids);

            $field = 'info.*,province.name as province_name,city.name as city_name,district.name as district_name'
                .',(SELECT COUNT(c_tf.rec_id) FROM '.C('DB_PREFIX').'collect_goods c_tf
            WHERE c_tf.user_id=info.user_id AND c_tf.supplier_id='.$this->memberid.' AND c_tf.type=1) 
            as c_tf_count'
                .',(SELECT COUNT(h_tf.id) FROM '.C('DB_PREFIX').'history h_tf
            WHERE h_tf.user_id=info.user_id AND h_tf.supplier_id='.$this->memberid.' AND h_tf.type=1) 
            as h_tf_count'
            .',(SELECT COUNT(fans.id) FROM '.C('DB_PREFIX').'biz_fans fans
            WHERE fans.user_id=info.user_id AND fans.member_id='.$this->memberid.') as isfans';

            $list = D('UserInfo')->getInfosPaged("field:$field;
            _string:info.user_id IN($uids);
            order:FIELD(info.user_id,$uids);");
        }
        $this->assign('list',$list);
        $this->display();
    }
    
    function add_fans(){
        if(IS_POST){
            $user_id = I('post.id');
            $fans_model = D('Supplier/Fans');
            $count = $fans_model
                ->where(array('member_id'=>$this->memberid,'user_id'=>$user_id))
                ->count();
            if($count == 0){
                //查找是否有下单记录
                $data = array(
                    'member_id'     => $this->memberid,
                    'user_id'       => $user_id,
                );
                $fansorder = D('Order/Order')
                    ->field('add_time as order_date,COUNT(order_id) as order_num')
                    ->where(array('user_id'=>$user_id,'supplier_id'=>$this->memberid))
                    ->order('add_time DESC')
                    ->group('user_id')
                    ->find();
                if(!empty($fansorder)){
                    $data['order_num'] = $fansorder['order_num'];
                    $data['order_date'] = date('Y-m-d H:i:s',$fansorder['order_date']);
                    $result = $fans_model->saveFan($data);
                    if($result){
                        $this->ajaxReturn(array(
                            'status'    => 1,
                            'info'      => '已添加好友！',
                            'data'      => array(
                                'id'    => $data['user_id'],
                                'type'  => '1',
                            ),
                        ));
                    }else{
                        $this->error('添加好友失败！');
                    }
                }else{
                    //发送好友申请
                    //send_invite_code();
                    $title = $this->member['biz_name'].'希望加你为好友！';
                    $href = leuu('portal/invite/invitefans',array('code'=>$this->member['biz_code']));
                    $content = '是否要接受'.$this->member['biz_name'].'的好友请求？'
                        .'<a href="'.$href.'" class="btn-u btn-u-xs">接受</a>';
                    D('Notify/Msg')->sendMsg($data['user_id'],$title,$content,$this->memberid);
                    $this->ajaxReturn(array(
                        'status'    => 1,
                        'info'      => '已发送好友申请！',
                        'data'      => array(
                            'id'    => $data['user_id'],
                            'type'  => '2',
                        ),
                    ));
                }
            }else{
                $this->error('TA已经是您好友了！');
            }
        }
    }

}