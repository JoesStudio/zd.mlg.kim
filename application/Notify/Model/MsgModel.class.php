<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-19
 * Time: 12:21
 */

namespace Notify\Model;


use Common\Model\CommonModel;

class MsgModel extends CommonModel
{
    protected $tableName = 'message';

    public $utypes = array('系统', '会员', '面料商', '管理员');

    /*
     * 获取未删除的记录
     * @param int $currentPage 当前分页
     * @param in $pageSize 分页记录数
     * @return array
     */
    function msgs($tag='', $pageSize=0, $pagetpl='', $tplName='default'){
        $where = array();
        if(is_array($tag)){
            $where = array_merge($tag, $where);
        }else{
            $tag=sp_param_lable($tag);

            if(isset($tag['sender_id'])){
                $where['sender_id'] = $tag['sender_id'];
            }

            if(isset($tag['receiver_id'])){
                $where['receiver_id'] = $tag['receiver_id'];
            }

            if(isset($tag['sender_type'])){
                $where['sender_type'] = $tag['sender_type'];
            }

            if(isset($tag['receiver_type'])){
                $where['receiver_type'] = $tag['receiver_type'];
            }

            if(isset($tag['readed'])){
                $where['readed'] = $tag['readed'];
            }
        }

        $field = !empty($tag['field']) ? $tag['field'] : '*';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'sent_time DESC';
        //$join_tables = isset($tag['join']) ? implode(',',$tag['join']):array();

        $where['deleted'] = isset($where['deleted']) ? $where['deleted']:0;


        $data['total'] = $this->where($where)->count();

        $this->field($field)->where($where)->order($order);
        $this->join('__MESSAGE_TEXT__ ON __MESSAGE_TEXT__.tid = __MESSAGE__.tid','LEFT');

        if(empty($pageSize)){
            $this->limit($limit);
        }else{
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show($tplName);
        }

        $data['data'] = $this->select();

        return $data;
    }
    /*
     * 获得分页列表
     */
    function getMsgsPaged($tag='', $pageSize=20, $pagetpl='', $tplName='default'){
        return $this->msgs($tag, $pageSize, $pagetpl, $tplName);
    }

    /*
     * 获得不分页表
     */
    function getMsgsNoPaged($tag=''){
        $data =  $this->msgs($tag);
        return $data['data'];
    }

    function getUnread($user_id, $receiver_type = 1){
        return $this->getMsgsNoPaged("receiver_id:$user_id;receiver_type:$receiver_type;readed:0;");
    }

    function getUnreadNum($receiver_id, $receiver_type = 1){
        $where['receiver_id'] = $receiver_id;
        $where['readed'] = 0;
        $where['deleted'] = 0;
        $where['receiver_type'] = $receiver_type;
        return $this->where($where)->count();
    }

    function getMsg($id){
        $where['message_id'] = $id;
        $msg = $this
            ->alias('msg')
            ->field('msg.*,text.message_text')
            ->join('__MESSAGE_TEXT__ text ON text.tid = msg.tid', 'LEFT')
            ->where($where)
            ->find();
        return $msg;
    }

    function setRead($id){
        $where = is_array($id) ? $id:array('message_id'=>$id);
        $data['read_time'] = time();
        $data['readed'] = 1;
        $result = $this->where($where)->save($data);
        if($result === false){
            $this->error = $this->getDbError();
        }
        return $result;
    }

    function setDeleted($id){
        $where = is_array($id) ? $id:array('message_id'=>$id);
        $data['deleted'] = 1;
        $result = $this->where($where)->save($data);
        if($result === false){
            $this->error = $this->getDbError();
        }
        return $result;
    }

    public function get_name($uid, $utype)
    {
        switch ($utype) {
            case 0:
                $name = '系统';
                break;
            case 1:
                $name = M('UserInfo')->where(array('user_id' => $uid))->getField('nickname');
                break;
            case 2:
                $name = M('BizMember')->where(array('id' => $uid))->getField('biz_name');
                break;
            case 3:
                $name = M('Users')->where(array('id' => $uid))->getField('nickname');
                break;
            default:
                $name = '';
        }
        return $name;
    }

    /*
     * 发送消息
     * @param int $uid 接收者id
     * @param string $title 标题
     * @param string $content 内容
     */
    public function sendMsg($uid, $title, $content, $sender_id = 0, $sender_type = 0, $receiver_type = 1)
    {
        $tid = D('MessageText')->add(array('message_text'=>$content));
        $data = array(
            'tid'       => $tid,
            'sender_id' => $sender_id,
            'sender_type' => $sender_type,
            'sender_name' => $this->get_name($sender_id, $sender_type),
            'receiver_id'=> $uid,
            'receiver_type' => $receiver_type,
            'receiver_name' => $this->get_name($uid, $receiver_type),
            'sent_time' => time(),
            'type'      => 0,
            'title'     => $title,
        );

        $result = $this->add($data);

        if ($result === false) {
            D('MessageText')->delete($tid);
        }
        return $result;
    }

    function sendMail($id, $note=''){
        $msg = $this->getMsg($id);
        $email = $msg['receiver_email'];
        $title = $msg['title'];
        $content = $msg['message_text'];
        if(!empty($email)){
            $result = sp_send_email($email, $title, $content);
            $status = $result['error'] ? 0:1;
            D('Notify/Log')->logAction($id,'EMAIL',$status,$note);
            return $result;
        }else{
            return array();
        }
    }

    function sendSMS($id, $note=''){
        $msg = $this->getMsg($id);
        $mobile = $msg['receiver_mobile'];
        $content = $msg['message_text'];
        if(!empty($mobile)){
            //$result = send_sms($mobile, $content);
            $result = 1;
            $status = $result;
            D('Notify/Log')->logAction($id,'SMS',$status,$note);
            return $result;
        }else{
            return array();
        }
    }

}