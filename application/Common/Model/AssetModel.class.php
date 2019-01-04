<?php
namespace Common\Model;

use Common\Model\CommonModel;

class AssetModel extends CommonModel
{

    public function assets($tag = '', $pageSize = 0, $pagetpl = '', $tplName = 'default')
    {
        $where = array();
        if (is_array($tag)) {
            $where = array_merge($where, $tag);
        } else {
            $tag = sp_param_lable($tag);

            if (isset($tag['uid'])) {
                $where['uid'] = $tag['uid'];
            }

            if (isset($tag['utype'])) {
                $where['utype'] = $tag['utype'];
            }

            if (isset($tag['where'])) {
                $where['_string'] = $tag['_string'];
            }
        }

        $field = !empty($tag['field']) ? $tag['field'] : '*';
        $limit = !empty($tag['limit']) ? $tag['limit'] : '0,20';
        $order = !empty($tag['order']) ? $tag['order'] : 'uploadtime DESC';

        $data['total'] = $this->where($where)->count();

        $this->field($field)->where($where)->order($order);

        if (empty($pageSize)) {
            if (isset($tag['limit'])) {
                $this->limit($limit);
            }
        } else {
            $page = $this->initPager($data['total'], intval($pageSize), $pagetpl, $tplName);
            $this->limit($page->firstRow, $page->listRows);
            $data['page'] = $page->show('default');
            $data['totalPages'] = $page->getTotalPages();
        }

        $data['data'] = $this->select();

        return $data;
    }

    /*
     * 获取未删除的记录
     * @param int $currentPage 当前分页
     * @param in $pageSize 分页记录数
     * @return array
     */
    public function getAssetsPaged($tag = '', $pageSize = 20, $pagetpl = '', $tplName = 'default')
    {
        $data = $this->assets($tag, $pageSize, $pagetpl, $tplName);
        return $data;
    }

    public function getAssetsNoPaged($tag = '')
    {
        $data = $this->assets($tag);
        return $data['data'];
    }

    public function saveAsset($data)
    {
        $result = $this->create($data);
        if ($result !== false) {
            if (isset($this->data[$this->getPk()])) {
                $result = $this->save();
            } else {
                $result = $this->add();
            }
            if ($result === false) {
                $this->error = $this->getDbError();
            }
        }
        return $result;
    }

    public function deleteAsset($id)
    {
        if (is_numeric($id)) {
            $where['aid'] = $id;
        } elseif (is_array($id)) {
            $where['aid'] = array('IN', $id);
        } elseif (is_string($id)) {
            $where['aid'] = array('IN', $id);
        } else {
            $this->error = L("ERROR_REQUEST_DATA");
            return false;
        }
        $assets = $this->where($where)->getField('aid,filepath');
        $result = array();
        foreach ($assets as $aid => $filepath) {
            $path = '.' . C("TMPL_PARSE_STRING.__UPLOAD__") . $filepath;

            $this->deleteThumbs($path);
            if (file_exists($path)) {
                unlink($path);
            }
            if ($this->delete($aid)) {
                array_push($result, $aid);
            }
        }
        return $result;
    }

    public function deleteThumbs($path)
    {
        /*if (strpos($path, "/") === 0) {
            $path = SITE_PATH . $path;
        } else {
            $path = C("UPLOADPATH") . $path;
        }*/

        $pathinfo = pathinfo($path);
        $thumbDir = $pathinfo['dirname'];
        $thumbPrefix = '';
        $oExt = $pathinfo['extension'];
        $oName = basename($path, '.' . $oExt);
        $sizes = array(1024, 800, 640, 300, 150, 80);
        foreach ($sizes as $size) {
            $thumbSuffix = '_thumb-' . $size;
            $thumbPath = $thumbDir . '/' . $thumbPrefix . $oName . $thumbSuffix . '.' . $oExt;
            if (file_exists($thumbPath)) {
                unlink($thumbPath);
            }
        }
    }

    protected function _before_write(&$data)
    {
        parent::_before_write($data);
    }
}
