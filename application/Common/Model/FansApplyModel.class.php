<?php

namespace Common\Model;

use Common\Model\CommonModel;

class FansApplyModel extends CommonModel
{

    public function recs($tag = '', $pageSize = 0, $pagetpl = '', $tplName = 'default')
    {
        $where = array();
        if (is_array($tag)) {
            $where = array_merge($where, $tag);
        } else {
            $tag=sp_param_lable($tag);

            if (isset($tag['_string'])) {
                $where['_string'] = $tag['_string'];
            }
        }
        $field = !empty($tag['field']) ? $tag['field'] : '*';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'id DESC';
        $data['total'] = $this->where($where)->count();

        if (empty($pageSize)) {
            $this->limit($limit);
        } else {
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
        }

        $data['data'] = $this->select();

        return $data;
    }

    public function getRecsPaged($tag = '', $pageSize = 20, $pagetpl = '', $tplName = 'default')
    {
        return $this->recs($tag, $pageSize, $pagetpl, $tplName);
    }

    public function getRecsNoPaged($tag = '')
    {
        $data = $this->recs($tag);
        return $data['data'];
    }

    public function saveApply($data)
    {
        $result = $this->create($data);
        if ($result !== false) {
            if (isset($this->data[$this->getPk()])) {
                $result = $this->save();
            } else {
                $create_time = $this->data['create_time'];
                $result = $this->add();
            }
            if ($result === false) {
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }
}
