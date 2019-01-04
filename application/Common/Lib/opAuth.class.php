<?php
namespace Common\Lib;

/**
 * ThinkCMF权限认证类
 */
class opAuth{

    //默认配置
    protected $_config = array(
    );

    public function __construct() {
    }

    /**
      * 检查权限
      * @param name string|array  需要验证的规则列表,支持逗号分隔的权限规则或索引数组
      * @param opid  int           认证用户的id
      * @param relation string    如果为 'or' 表示满足任一条规则即通过验证;如果为 'and'则表示需满足所有规则才能通过验证
      * @return boolean           通过验证返回true;失败返回false
     */
    public function check($opid,$name,$relation='or') {
        if(empty($opid)){
            return false;
        }
        if (is_string($name)) {
            $name = strtolower($name);
            if (strpos($name, ',') !== false) {
                $name = explode(',', $name);
            } else {
                $name = array($name);
            }
        }
        $list = array(); //保存验证通过的规则名
        
        $role_user_model=M("BizRoleOp");
        
        $role_user_join = '__BIZ_ROLE__ as b on a.role_id =b.id';
        
        $groups=$role_user_model->alias("a")->join($role_user_join)->where(array("op_id"=>$opid,"status"=>1))->getField("role_id",true);
        
        //最高管理员
        if(in_array(1, $groups)){
            return true;
        }

        //没有角色
        if(empty($groups)){
            return false;
        }

        //如果规则表没有这些规则就通过
        $require_rule = M('BizOpauthRule')->where(array('name'=>array('in', $name),'status'=>1))->count();
        if (empty($require_rule)) {
            return true;
        }
        
        $auth_access_model=M("BizOpauthAccess");
        
        $join = '__BIZ_OPAUTH_RULE__ as b on a.rule_name =b.name';
        
        $rules=$auth_access_model->alias("a")->join($join)->where(array("a.role_id"=>array("in",$groups),"b.name"=>array("in",$name)))->select();
        

        foreach ($rules as $rule){
            if (!empty($rule['condition'])) { //根据condition进行验证
                $oper = $this->getOpInfo($opid);//获取用户信息,一维数组
            
                $command = preg_replace('/\{(\w*?)\}/', '$oper[\'\\1\']', $rule['condition']);
                //dump($command);//debug
                @(eval('$condition=(' . $command . ');'));
                if ($condition) {
                    $list[] = strtolower($rule['name']);
                }
            }else{
                $list[] = strtolower($rule['name']);
            }
        }
        
        if ($relation == 'or' and !empty($list)) {
            return true;
        }
        $diff = array_diff($name, $list);
        if ($relation == 'and' and empty($diff)) {
            return true;
        }
        return false;
    }
    
    /**
     * 获得用户资料
     */
    private function getOpInfo($opid) {
        static $opinfo=array();
        if(!isset($opinfo[$opid])){
            $opinfo[$opid]=M("BizOperator")->where(array('id'=>$opid))->find();
        }
        return $opinfo[$opid];
    }

}
