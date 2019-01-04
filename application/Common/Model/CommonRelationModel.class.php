<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-11
 * Time: 17:12
 */

namespace Common\Model;


use Think\Model\RelationModel;

class CommonRelationModel extends RelationModel
{
    //protected $autoCheckFields  =   false;

    /**
     * 获取返回数据的关联记录
     * @access protected
     * @param mixed $result  返回数据
     * @param string|array $name  关联名称
     * @param boolean $return 是否返回关联数据本身
     * @return array
     */
    protected function getRelation(&$result,$name='',$return=false) {
        if(!empty($this->_link)) {
            foreach($this->_link as $key=>$val) {
                $mappingName =  !empty($val['mapping_name'])?$val['mapping_name']:$key; // 映射名称
                if(empty($name) || true === $name || $mappingName == $name || (is_array($name) && in_array($mappingName,$name))) {
                    $mappingType = !empty($val['mapping_type'])?$val['mapping_type']:$val;  //  关联类型
                    $mappingClass  = !empty($val['class_name'])?$val['class_name']:$key;            //  关联类名
                    $mappingFields = !empty($val['mapping_fields'])?$val['mapping_fields']:'*';     // 映射字段
                    $mappingCondition = !empty($val['condition'])?$val['condition']:'1=1';          // 关联条件
                    $mappingKey =!empty($val['mapping_key'])? $val['mapping_key'] : $this->getPk(); // 关联键名

                    //关联查询也使用relation
                    $useRelationAgain = !empty($val['relation'])?$val['relation']:false;

                    if(strtoupper($mappingClass)==strtoupper($this->name)) {
                        // 自引用关联 获取父键名
                        $mappingFk   =   !empty($val['parent_key'])? $val['parent_key'] : 'parent_id';
                    }else{
                        $mappingFk   =   !empty($val['foreign_key'])?$val['foreign_key']:strtolower($this->name).'_id';     //  关联外键
                    }
                    // 获取关联模型对象
                    $model = D($mappingClass);
                    switch($mappingType) {
                        case self::HAS_ONE:
                            $pk   =  $result[$mappingKey];
                            $mappingCondition .= " AND {$mappingFk}='{$pk}'";
                            $relationData   =  $model->where($mappingCondition)->field($mappingFields)->find();
                            if (!empty($val['relation_deep'])){
                                $model->relation($useRelationAgain)->getRelation($relationData,$val['relation_deep']);
                            }
                            break;
                        case self::BELONGS_TO:
                            if(strtoupper($mappingClass)==strtoupper($this->name)) {
                                // 自引用关联 获取父键名
                                $mappingFk   =   !empty($val['parent_key'])? $val['parent_key'] : 'parent_id';
                            }else{
                                $mappingFk   =   !empty($val['foreign_key'])?$val['foreign_key']:strtolower($model->getModelName()).'_id';     //  关联外键
                            }
                            $fk   =  $result[$mappingFk];
                            $mappingCondition .= " AND {$model->getPk()}='{$fk}'";
                            $relationData   =  $model->relation($useRelationAgain)->where($mappingCondition)->field($mappingFields)->find();
                            if (!empty($val['relation_deep'])){
                                $model->getRelation($relationData,$val['relation_deep']);
                            }
                            break;
                        case self::HAS_MANY:
                            $pk   =  $result[$mappingKey];
                            $mappingCondition .= " AND {$mappingFk}='{$pk}'";
                            $mappingOrder =  !empty($val['mapping_order'])?$val['mapping_order']:'';
                            $mappingLimit =  !empty($val['mapping_limit'])?$val['mapping_limit']:'';
                            // 延时获取关联记录
                            $relationData   =  $model->relation($useRelationAgain)->where($mappingCondition)->field($mappingFields)->order($mappingOrder)->limit($mappingLimit)->select();
                            if (!empty($val['relation_deep'])){
                                foreach($relationData as $key=>$data){
                                    $model->getRelation($data,$val['relation_deep']);
                                    $relationData[$key]     =   $data;
                                }
                            }
                            break;
                        case self::MANY_TO_MANY:
                            $pk     =   $result[$mappingKey];
                            $prefix =   $this->tablePrefix;
                            $mappingCondition = " {$mappingFk}='{$pk}'";
                            $mappingOrder =  $val['mapping_order'];
                            $mappingLimit =  $val['mapping_limit'];
                            $mappingRelationFk = $val['relation_foreign_key']?$val['relation_foreign_key']:$model->getModelName().'_id';
                            if(isset($val['relation_table'])){
                                $mappingRelationTable   =   preg_replace_callback("/__([A-Z_-]+)__/sU", function($match) use($prefix){ return $prefix.strtolower($match[1]);}, $val['relation_table']);
                            }else{
                                $mappingRelationTable   =   $this->getRelationTableName($model);
                            }
                            $sql = "SELECT b.{$mappingFields} FROM {$mappingRelationTable} AS a, ".$model->getTableName()." AS b WHERE a.{$mappingRelationFk} = b.{$model->getPk()} AND a.{$mappingCondition}";
                            if(!empty($val['condition'])) {
                                $sql   .= ' AND '.$val['condition'];
                            }
                            if(!empty($mappingOrder)) {
                                $sql .= ' ORDER BY '.$mappingOrder;
                            }
                            if(!empty($mappingLimit)) {
                                $sql .= ' LIMIT '.$mappingLimit;
                            }
                            $relationData   =   $this->query($sql);
                            if (!empty($val['relation_deep'])){
                                foreach($relationData as $key=>$data){
                                    $model->getRelation($data,$val['relation_deep']);
                                    $relationData[$key]     =   $data;
                                }
                            }
                            break;
                    }
                    if(!$return){
                        if(isset($val['as_fields']) && in_array($mappingType,array(self::HAS_ONE,self::BELONGS_TO)) ) {
                            // 支持直接把关联的字段值映射成数据对象中的某个字段
                            // 仅仅支持HAS_ONE BELONGS_TO
                            $fields =   explode(',',$val['as_fields']);
                            foreach ($fields as $field){
                                if(strpos($field,':')) {
                                    list($relationName,$nick) = explode(':',$field);
                                    $result[$nick]  =  $relationData[$relationName];
                                }else{
                                    $result[$field]  =  $relationData[$field];
                                }
                            }
                        }else{
                            $result[$mappingName] = $relationData;
                        }
                        unset($relationData);
                    }else{
                        return $relationData;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * 操作关联数据
     * @access protected
     * @param string $opType  操作方式 ADD SAVE DEL
     * @param mixed $data  数据对象
     * @param string $name 关联名称
     * @return mixed
     */
    protected function opRelation($opType,$data='',$name='') {
        $result =   false;
        if(empty($data) && !empty($this->data)){
            $data = $this->data;
        }elseif(!is_array($data)){
            // 数据无效返回
            return false;
        }
        if(!empty($this->_link)) {
            // 遍历关联定义
            foreach($this->_link as $key=>$val) {
                // 操作制定关联类型
                $mappingName =  $val['mapping_name']?$val['mapping_name']:$key; // 映射名称
                if(empty($name) || true === $name || $mappingName == $name || (is_array($name) && in_array($mappingName,$name)) ) {
                    // 操作制定的关联
                    $mappingType = !empty($val['mapping_type'])?$val['mapping_type']:$val;  //  关联类型
                    $mappingClass  = !empty($val['class_name'])?$val['class_name']:$key;            //  关联类名
                    $mappingKey =!empty($val['mapping_key'])? $val['mapping_key'] : $this->getPk(); // 关联键名
                    // 当前数据对象主键值
                    $pk =   $data[$mappingKey];
                    if(strtoupper($mappingClass)==strtoupper($this->name)) {
                        // 自引用关联 获取父键名
                        $mappingFk   =   !empty($val['parent_key'])? $val['parent_key'] : 'parent_id';
                    }else{
                        $mappingFk   =   !empty($val['foreign_key'])?$val['foreign_key']:strtolower($this->name).'_id';     //  关联外键
                    }
                    if(!empty($val['condition'])) {
                        $mappingCondition   =   $val['condition'];
                    }else{
                        $mappingCondition               =   array();
                        $mappingCondition[$mappingFk]   =   $pk;
                    }


                    //关联查询也使用relation
                    $useRelationAgain = !empty($val['relation'])?$val['relation']:false;

                    // 获取关联model对象
                    $model = D($mappingClass);
                    $mappingData    =   isset($data[$mappingName])?$data[$mappingName]:false;
                    if(!empty($mappingData) || $opType == 'DEL') {
                        switch($mappingType) {
                            case self::HAS_ONE:
                                switch (strtoupper($opType)){
                                    case 'ADD': // 增加关联数据
                                        $mappingData[$mappingFk]    =   $pk;
                                        $result   =  $model->add($mappingData);
                                        break;
                                    case 'SAVE':    // 更新关联数据
                                        $result   =  $model->where($mappingCondition)->save($mappingData);
                                        /*$pk   =  $model->getPk();
                                        if(isset($mappingData[$pk])){
                                        }else{
                                            //
                                        }*/
                                        break;
                                    case 'DEL': // 根据外键删除关联数据
                                        $result   =  $model->where($mappingCondition)->delete();
                                        break;
                                }
                                break;
                            case self::BELONGS_TO:
                                break;
                            case self::HAS_MANY:
                                switch (strtoupper($opType)){
                                    case 'ADD'   :  // 增加关联数据
                                        $model->startTrans();
                                        foreach ($mappingData as $vo){
                                            $vo[$mappingFk]    =   $pk;
                                            $result   =  $model->relation($useRelationAgain)->add($vo);
                                        }
                                        $model->commit();
                                        break;
                                    case 'SAVE' :   // 更新关联数据
                                        $model->startTrans();
                                        $pk   =  $model->getPk();
                                        foreach ($mappingData as $vo){
                                            if(isset($vo[$pk])) {// 更新数据
                                                $mappingCondition   =  "$pk ={$vo[$pk]}";
                                                $result   =  $model->where($mappingCondition)->save($vo);
                                            }else{ // 新增数据
                                                $vo[$mappingFk] =  $data[$mappingKey];
                                                $result   =  $model->add($vo);
                                            }
                                        }
                                        $model->commit();
                                        break;
                                    case 'DEL' :    // 删除关联数据
                                        $result   =  $model->where($mappingCondition)->delete();
                                        break;
                                }
                                break;
                            case self::MANY_TO_MANY:
                                $mappingRelationFk = $val['relation_foreign_key']?$val['relation_foreign_key']:$model->getModelName().'_id';// 关联
                                $prefix =   $this->tablePrefix;
                                if(isset($val['relation_table'])){
                                    $mappingRelationTable   =   preg_replace_callback("/__([A-Z_-]+)__/sU", function($match) use($prefix){ return $prefix.strtolower($match[1]);}, $val['relation_table']);
                                }else{
                                    $mappingRelationTable   =   $this->getRelationTableName($model);
                                }
                                if(is_array($mappingData)) {
                                    $ids   = array();
                                    foreach ($mappingData as $vo)
                                        $ids[]   =   $vo[$mappingKey];
                                    $relationId =   implode(',',$ids);
                                }
                                switch (strtoupper($opType)){
                                    case 'ADD': // 增加关联数据
                                        if(isset($relationId)) {
                                            $this->startTrans();
                                            // 插入关联表数据
                                            $sql  = 'INSERT INTO '.$mappingRelationTable.' ('.$mappingFk.','.$mappingRelationFk.') SELECT a.'.$this->getPk().',b.'.$model->getPk().' FROM '.$this->getTableName().' AS a ,'.$model->getTableName()." AS b where a.".$this->getPk().' ='. $pk.' AND  b.'.$model->getPk().' IN ('.$relationId.") ";
                                            $result =   $model->execute($sql);
                                            if(false !== $result)
                                                // 提交事务
                                                $this->commit();
                                            else
                                                // 事务回滚
                                                $this->rollback();
                                        }
                                        break;
                                    case 'SAVE':    // 更新关联数据
                                        if(isset($relationId)) {
                                            $this->startTrans();
                                            // 删除关联表数据
                                            $this->table($mappingRelationTable)->where($mappingCondition)->delete();
                                            // 插入关联表数据
                                            $sql  = 'INSERT INTO '.$mappingRelationTable.' ('.$mappingFk.','.$mappingRelationFk.') SELECT a.'.$this->getPk().',b.'.$model->getPk().' FROM '.$this->getTableName().' AS a ,'.$model->getTableName()." AS b where a.".$this->getPk().' ='. $pk.' AND  b.'.$model->getPk().' IN ('.$relationId.") ";
                                            $result =   $model->execute($sql);
                                            if(false !== $result)
                                                // 提交事务
                                                $this->commit();
                                            else
                                                // 事务回滚
                                                $this->rollback();
                                        }
                                        break;
                                    case 'DEL': // 根据外键删除中间表关联数据
                                        $result =   $this->table($mappingRelationTable)->where($mappingCondition)->delete();
                                        break;
                                }
                                break;
                        }
                        if (!empty($val['relation_deep'])){
                            $model->opRelation($opType,$mappingData,$val['relation_deep']);
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * 创建数据对象 但不保存到数据库
     * @access public
     * @param mixed $data 创建数据
     * @param string $type 状态
     * @return mixed
     */
    public function create($data='',$type='') {
        // 如果没有传值默认取POST数据
        if(empty($data)) {
            $data   =   I('post.');
        }elseif(is_object($data)){
            $data   =   get_object_vars($data);
        }
        // 验证数据
        if(empty($data) || !is_array($data)) {
            $this->error = L('_DATA_TYPE_INVALID_');
            return false;
        }
        // 状态
        $type = $type?:(!empty($data[$this->getPk()])?self::MODEL_UPDATE:self::MODEL_INSERT);
        // 检查字段映射
        if(!empty($this->_map)) {
            foreach ($this->_map as $key=>$val){
                if(isset($data[$key])) {
                    $data[$val] =   $data[$key];
                    unset($data[$key]);
                }
            }
        }
        // 检测提交字段的合法性
        if(isset($this->options['field'])) { // $this->field('field1,field2...')->create()
            $fields =   $this->options['field'];
            unset($this->options['field']);
        }elseif($type == self::MODEL_INSERT && isset($this->insertFields)) {
            $fields =   $this->insertFields;
        }elseif($type == self::MODEL_UPDATE && isset($this->updateFields)) {
            $fields =   $this->updateFields;
        }
        if(isset($fields)) {
            if(is_string($fields)) {
                $fields =   explode(',',$fields);
            }
            // 判断令牌验证字段
            if(C('TOKEN_ON'))   $fields[] = C('TOKEN_NAME');
            foreach ($data as $key=>$val){
                if(!in_array($key,$fields)) {
                    unset($data[$key]);
                }
            }
        }
        // 数据自动验证
        if(!$this->autoValidation($data,$type)) return false;
        // 表单令牌验证
        if(!$this->autoCheckToken($data)) {
            $this->error = L('_TOKEN_ERROR_');
            return false;
        }
        // 验证完成生成数据对象
        $fields =   $this->getDbFields();
        if($this->autoCheckFields) { // 开启字段检测 则过滤非法字段数据
            $diff = array_diff(array_keys($data),$fields);
            foreach ($diff as $key){
                unset($data[$key]);
            }
        }
        $intersect = array_intersect($fields,array_keys($data));
        foreach ($intersect as $key) {
            if(MAGIC_QUOTES_GPC && is_string($data[$key])){
                $data[$key] =   stripslashes($data[$key]);
            }
        }
        // 创建完成对数据进行自动处理
        $this->autoOperation($data,$type);
        $this->_after_create($data,$this->options); //回调
        // 赋值当前数据对象
        $this->data =   $data;
        // 返回创建的数据以供其他调用
        return $data;
    }// 表单验证成功后的回调方法

    protected function _after_create(&$data,$options) {
        // 获取关联数据 并附加到结果中
        if(!empty($options['link']))
            $this->crRelation($data,$options['link']);
    }

    /**
     * 关联数据验证
     * @access protected
     * @param mixed $data  数据对象
     * @param string $name 关联名称
     * @return mixed
     */
    protected function crRelation(&$data='',$name='') {
        if(empty($data) && !empty($this->data)){
            $data = $this->data;
        }elseif(!is_array($data)){
            // 数据无效返回
            return false;
        }
        if(!empty($this->_link)) {
            $fields =   $this->getDbFields();
            // 遍历关联定义
            foreach($this->_link as $key=>$val) {
                // 操作制定关联类型
                $mappingName =  $val['mapping_name']?$val['mapping_name']:$key; // 映射名称
                if(empty($name) || true === $name || $mappingName == $name || (is_array($name) && in_array($mappingName,$name)) ) {
                    // 操作制定的关联
                    $mappingType = !empty($val['mapping_type'])?$val['mapping_type']:$val;  //  关联类型
                    $mappingClass  = !empty($val['class_name'])?$val['class_name']:$key;            //  关联类名
                    $mappingKey =!empty($val['mapping_key'])? $val['mapping_key'] : $this->getPk(); // 关联键名
                    if(strtoupper($mappingClass)==strtoupper($this->name)) {
                        continue; // 自引用关联跳过
                    }
                    // 获取关联model对象
                    $model = D($mappingClass);
                    foreach ($model->getDbFields() as $field) {
                        if (array_key_exists($field, $data) && $field != $mappingKey) {
                            $_data[$field] = $data[$field];
                            unset($data[$field]);
                        }
                    }
                    if ($_data = $model->token(false)->create($_data)) {
                        $data[$mappingName] = $_data;
                        $fields[] = $mappingName;
                    } else {
                        $this->error = $model->getError();
                        return false;
                    }
                }
            }
            // 过滤非法字段数据
            $diff = array_diff(array_keys($data),$fields);
            foreach ($diff as $key){
                unset($data[$key]);
            }
        }
    }

    /**
     * 进行关联查询
     * @access public
     * @param mixed $name 关联名称
     * @return Model
     */
    public function relation($name) {
        $this->options['link']  =   $name;
        $this->autoCheckFields = false; //执行create方法时不过滤字段
        return $this;
    }

    /**
     * 自动表单处理
     * @access public
     * @param array $data 创建数据
     * @param string $type 创建类型
     * @return mixed
     */
    private function autoOperation(&$data,$type) {
        if(false === $this->options['auto']){
            // 关闭自动完成
            return $data;
        }
        if(!empty($this->options['auto'])) {
            $_auto   =   $this->options['auto'];
            unset($this->options['auto']);
        }elseif(!empty($this->_auto)){
            $_auto   =   $this->_auto;
        }
        // 自动填充
        if(isset($_auto)) {
            foreach ($_auto as $auto){
                // 填充因子定义格式
                // array('field','填充内容','填充条件','附加规则',[额外参数])
                if(empty($auto[2])) $auto[2] =  self::MODEL_INSERT; // 默认为新增的时候自动填充
                if( $type == $auto[2] || $auto[2] == self::MODEL_BOTH) {
                    if(empty($auto[3])) $auto[3] =  'string';
                    switch(trim($auto[3])) {
                        case 'function':    //  使用函数进行填充 字段的值作为参数
                        case 'callback': // 使用回调方法
                            $args = isset($auto[4])?(array)$auto[4]:array();
                            if(isset($data[$auto[0]])) {
                                array_unshift($args,$data[$auto[0]]);
                            }
                            if('function'==$auto[3]) {
                                $data[$auto[0]]  = call_user_func_array($auto[1], $args);
                            }else{
                                $data[$auto[0]]  =  call_user_func_array(array(&$this,$auto[1]), $args);
                            }
                            break;
                        case 'field':    // 用其它字段的值进行填充
                            $data[$auto[0]] = $data[$auto[1]];
                            break;
                        case 'ignore': // 为空忽略
                            if($auto[1]===$data[$auto[0]])
                                unset($data[$auto[0]]);
                            break;
                        case 'string':
                        default: // 默认作为字符串填充
                            $data[$auto[0]] = $auto[1];
                    }
                    if(isset($data[$auto[0]]) && false === $data[$auto[0]] )   unset($data[$auto[0]]);
                }
            }
        }
        return $data;
    }
}