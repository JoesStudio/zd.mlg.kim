<?php
namespace Sms\Controller;

use Common\Controller\SupplierbaseController;

class SupplierController extends SupplierbaseController
{
    protected $_model;

    protected $config = array(
        'appkey'    => '23600529',
        'secretKey' => 'a5adb9f3dcd3450db9348ba5f4322e38',
        'format'    => 'json',
    );

    public function _initialize()
    {
        parent::_initialize();
        $this->_model = D('Sms/Sms');
    }

    public function send($uids = '', $tf_id = 0, $pdtype = 1)
    {
        $member_id = $this->memberid;

        $fans_model = D('Supplier/Fans');
        $fansList = $fans_model->getFansNoPaged("member_id:$member_id;");
        $this->assign('fans_list', $fansList);

        if (!empty($uids)) {
            $selectedIds = $fans_model
            ->where(array('member_id' => $member_id, 'user_id' => array('IN', $uids)))
            ->getField('id', true);
            $this->assign('selectedIds', $selectedIds);
        }

        $tfs = D('Tf/Tf')->getTfNoPaged("vend_id:$member_id;");
        $this->assign('tf_list', $tfs);
        $cards = D('Colorcard/Colorcard')->getCardsNoPaged("supplier_id:$member_id;");
        $this->assign('card_list', $cards);
        $this->display();
    }

    public function send_post()
    {
        if (IS_POST) {
            $post = I('post.');
            if (empty($post['msg_type'])) {
                $this->error('请至少选择一种消息类型！');
            }
            $ids = $post['fans'];
            $fansList = D('Supplier/Fans')
            ->field('info.*')
            ->alias('fans')
            ->join('__USER_INFO__ info ON info.user_id = fans.user_id')
            ->where(array('fans.id' => array('IN', $ids)))
            ->select();

            if (!empty($fansList)) {
                if ($post['send_type'] == 1) {
                    $type = '面料';
                    $data = D('Tf/Tf')->find($post['tf']);
                    $name = $data['name'];
                    $href = leuu('Tf/tf/fabric', array('id' => $data['id']), false, true);
                    $sms_href = leuu('Tf/tf/fabric', array('id' => $data['id']));
                } elseif ($post['send_type'] == 2) {
                    $type = '色卡';
                    $data = D('Colorcard/Colorcard')->find($post['card']);
                    $name = $data['card_name'];
                    $href = leuu('Colorcard/Index/view', array('id' => $data['card_id']), false, true);
                    $sms_href = leuu('Colorcard/Index/view', array('id' => $data['card_id']));
                } else {
                    $this->error('非法操作！');
                }

                $biz_name = $this->member['biz_name'];
                $title = "{$biz_name}给您发送了一个{$type}";
                $content = "{$biz_name}给您发送了一个{$type}：{$name}。<a href=\"{$href}\" class=\"btn-u btn-u-xs\" target=\"_blank\">查看</a>";
                //$content_sms = "{$biz_name}给您发送了一个{$type}：\"{$name}\"，浏览请打开链接{$href}";

                $msg_model = D('Notify/Msg');
                foreach ($fansList as $fans) {
                    if (in_array('normal', $post['msg_type'])) {
                        $msg_model->sendMsg($fans['user_id'], $title, $content, $this->memberid, 2, 1);
                    }
                    if (in_array('sms', $post['msg_type']) && !empty($fans['mobile'])) {
                        $smsData = array(
                            'to'    => $fans['mobile'],
                            'usage' => 'TEXT',
                            'param' => array(
                                'member'  => $this->member['biz_name'],
                                'what'  => "{$type}：\"{$name}\"",
                                'link'  => $sms_href,
                            )
                        );
                        send_sms($smsData, 'SMS_57015009');
                    }
                }

                $this->success('发送成功！');

                // echo $text;
                // echo "<br>";
                // echo $text_sms;
            }
        }
    }
}
