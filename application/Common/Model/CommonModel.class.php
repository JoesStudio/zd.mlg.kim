<?php

/* * 
 * 公共模型
 */

namespace Common\Model;
use Think\Model;

class CommonModel extends Model {

    /**
     * 删除表
     */
    final public function drop_table($tablename) {
        $tablename = C("DB_PREFIX") . $tablename;
        return $this->query("DROP TABLE $tablename");
    }

    /**
     * 读取全部表名
     */
    final public function list_tables() {
        $tables = array();
        $data = $this->query("SHOW TABLES");
        foreach ($data as $k => $v) {
            $tables[] = $v['tables_in_' . strtolower(C("DB_NAME"))];
        }
        return $tables;
    }

    /**
     * 检查表是否存在 
     * $table 不带表前缀
     */
    final public function table_exists($table) {
        $tables = $this->list_tables();
        return in_array(C("DB_PREFIX") . $table, $tables) ? true : false;
    }

    /**
     * 获取表字段 
     * $table 不带表前缀
     */
    final public function get_fields($table) {
        $fields = array();
        $table = C("DB_PREFIX") . $table;
        $data = $this->query("SHOW COLUMNS FROM $table");
        foreach ($data as $v) {
            $fields[$v['Field']] = $v['Type'];
        }
        return $fields;
    }

    /**
     * 检查字段是否存在
     * $table 不带表前缀
     */
    final public function field_exists($table, $field) {
        $fields = $this->get_fields($table);
        return array_key_exists($field, $fields);
    }

    //获取操作人ID
    function mGetOperatorId(){
        if(MODULE_NAME == 'Admin'){
            $uid = sp_get_current_admin_id();
        }else{
            $uid = sp_get_current_userid();
        }
        return $uid;
    }

    //获取操作人名字
    function mGetOperator(){
        if(MODULE_NAME == 'Admin'){
            $uid = sp_get_current_admin_id();
            $user = M('Users')->find($uid);
        }else{
            $user = sp_get_current_user();
        }
        $name = empty($user['nickname']) ? $user['nickname']:$user['user_login'];
        return $name;
    }

    //用于获取时间，格式为2012-02-03 12:12:12,注意,方法不能为private
    function mGetDate() {
        return date('Y-m-d H:i:s');
    }
    
    protected function _before_write(&$data) {
        
    }

    function mGetErrorByCode($code){
        switch($code){
            case -1:
                return $this->getError();
            break;
            case 0:
                return $this->getDbError();
                break;
            default:
                return '操作错误！';
        }
    }

    function initPager($total, $pagesize, $pagetpl, $tplname){
        $page_param = C("VAR_PAGE");
        $page = new \Page($total,$pagesize);
        $page->setLinkWraper("li");
        $page->__set("PageParam", $page_param);
        $page->__set("searching", true);
        $pagesetting=array("listlong" => "5", "first" => "首页", "last" => "尾页", "prev" => "上一页", "next" => "下一页", "list" => "*", "disabledclass" => "");
        $page->SetPager($tplname, $pagetpl,$pagesetting);
        return $page;
    }

}

