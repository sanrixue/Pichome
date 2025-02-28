<?php
namespace core\dzz;
use DB;
class Tpsql extends Tpdb{

    /**
     * 架构函数 读取数据库配置信息
     * @access public
     * @param array $config 数据库配置数组
     */
    public function __construct($config=''){ 
        if ( !extension_loaded('mysql') ) {
            echo lang('does_mysqli');
            exit;
            //E(L('_NOT_SUPPERT_').':mysql');
        }
        if(!empty($config)) {
            $this->config   =   $config;
            if(empty($this->config['params'])) {
                $this->config['params'] =   '';
            }
        } 
    }

    /**
     * 连接数据库方法
     * @access public
     * @throws ThinkExecption
     */
    public function connect($config='',$linkNum=1,$force=false) { 
        $this->linkID[$linkNum]  =DB::linknum(); 
        if ( !isset($this->linkID[$linkNum]) ) {
            if(empty($config))  $config =   $this->config;
            // 处理不带端口号的socket连接情况
            $host = $config['dbhost'].(isset($config['hostport'])?":{$config['hostport']}":'');
            // 是否长连接
            $pconnect   =$config['pconnect']; // !empty($config['params']['persist'])? $config['params']['persist']:$this->pconnect;
            if($pconnect) { 
                $this->linkID[$linkNum] = mysql_pconnect( $host, $config['dbuser'], $config['dbpw'],131072);
            }else{ 
                $this->linkID[$linkNum] = mysql_connect( $host, $config['dbuser'], $config['dbpw'],true,131072); 
            }
            if ( !$this->linkID[$linkNum] || (!empty($config['dbname']) && !mysql_select_db($config['dbname'], $this->linkID[$linkNum])) ) {
                echo lang('database_error');exit;
                //E(mysql_error());
            }
            $dbVersion = mysql_get_server_info($this->linkID[$linkNum]);
            //使用UTF8存取数据库
            mysql_query("SET NAMES 'utf-8'", $this->linkID[$linkNum]);
            //设置 sql_model
            if($dbVersion >'5.0.1'){
                mysql_query("SET sql_mode=''",$this->linkID[$linkNum]);
            }
            // 标记连接成功
            $this->connected    =   true;
            // 注销数据库连接配置信息
            //if(1 != C('DB_DEPLOY_TYPE')) unset($this->config);
        }
        //print_r(  $this->linkID[$linkNum]   );exit;
        return $this->linkID[$linkNum];
    }

    /**
     * 释放查询结果
     * @access public
     */
    public function free() {
        mysql_free_result($this->queryID);
        $this->queryID = null;
    }

    /**
     * 执行查询 返回数据集
     * @access public
     * @param string $str  sql指令
     * @return mixed
     */
    public function query($str) { 
        if(0===stripos($str, 'call')){ // 存储过程查询支持
            $this->close();
            $this->connected    =   false;
        } 
        $this->initConnect(false);
        if ( !$this->_linkID ) return false;
        $this->queryStr = $str;
        //释放前次的查询结果
        if ( $this->queryID ) {    $this->free();    }
        //N('db_query',1);
        // 记录开始执行时间
        //G('queryStartTime'); 
        $this->queryID = mysql_query($str, $this->_linkID);
        $this->debug();
        if ( false === $this->queryID ) { 
            $this->error();
            return false;
        } else {
            $this->numRows = mysql_num_rows($this->queryID);
            return $this->getAll();
        }
    }

    /**
     * 执行语句
     * @access public
     * @param string $str  sql指令
     * @return integer|false
     */
    public function execute($str) {
        $this->initConnect(true);
        if ( !$this->_linkID ) return false;
        $this->queryStr = $str;
        //释放前次的查询结果
        if ( $this->queryID ) {    $this->free();    }
        //N('db_write',1);
        // 记录开始执行时间
        //G('queryStartTime');
        $result =   mysql_query($str, $this->_linkID) ;
        $this->debug();
        if ( false === $result) {
            $this->error();
            return false;
        } else {
            $this->numRows = mysql_affected_rows($this->_linkID);
            $this->lastInsID = mysql_insert_id($this->_linkID);
            return $this->numRows;
        }
    }

    /**
     * 启动事务
     * @access public
     * @return void
     */
    public function startTrans() {
        $this->initConnect(true);
        if ( !$this->_linkID ) return false;
        //数据rollback 支持
        if ($this->transTimes == 0) {
            mysql_query('START TRANSACTION', $this->_linkID);
        }
        $this->transTimes++;
        return ;
    }

    /**
     * 用于非自动提交状态下面的查询提交
     * @access public
     * @return boolen
     */
    public function commit() {
        if ($this->transTimes > 0) {
            $result = mysql_query('COMMIT', $this->_linkID);
            $this->transTimes = 0;
            if(!$result){
                $this->error();
                return false;
            }
        }
        return true;
    }

    /**
     * 事务回滚
     * @access public
     * @return boolen
     */
    public function rollback() {
        if ($this->transTimes > 0) {
            $result = mysql_query('ROLLBACK', $this->_linkID);
            $this->transTimes = 0;
            if(!$result){
                $this->error();
                return false;
            }
        }
        return true;
    }

    /**
     * 获得所有的查询数据
     * @access private
     * @return array
     */
    private function getAll() {
        //返回数据集
        $result = array();
        if($this->numRows >0) {
            while($row = mysql_fetch_assoc($this->queryID)){
                $result[]   =   $row;
            }
            mysql_data_seek($this->queryID,0);
        }
        return $result;
    }

    /**
     * 取得数据表的字段信息
     * @access public
     * @return array
     */
    public function getFields($tableName) {
        $result =   $this->query('SHOW COLUMNS FROM '.$this->parseKey($tableName));
        $info   =   array();
        if($result) {
            foreach ($result as $key => $val) {
                $info[$val['Field']] = array(
                    'name'    => $val['Field'],
                    'type'    => $val['Type'],
                    'notnull' => (bool) (strtoupper($val['Null']) === 'NO'), // not null is empty, null is yes
                    'default' => $val['Default'],
                    'primary' => (strtolower($val['Key']) == 'pri'),
                    'autoinc' => (strtolower($val['Extra']) == 'auto_increment'),
                );
            }
        }
        return $info;
    }

    /**
     * 取得数据库的表信息
     * @access public
     * @return array
     */
    public function getTables($dbName='') {
        if(!empty($dbName)) {
           $sql    = 'SHOW TABLES FROM '.$dbName;
        }else{
           $sql    = 'SHOW TABLES ';
        }
        $result =   $this->query($sql);
        $info   =   array();
        foreach ($result as $key => $val) {
            $info[$key] = current($val);
        }
        return $info;
    }

    /**
     * 替换记录
     * @access public
     * @param mixed $data 数据
     * @param array $options 参数表达式
     * @return false | integer
     */
    public function replace($data,$options=array()) {
        foreach ($data as $key=>$val){
            $value   =  $this->parseValue($val);
            if(is_scalar($value)) { // 过滤非标量数据
                $values[]   =  $value;
                $fields[]     =  $this->parseKey($key);
            }
        }
        $sql   =  'REPLACE INTO '.$this->parseTable($options['table']).' ('.implode(',', $fields).') VALUES ('.implode(',', $values).')';
        return $this->execute($sql);
    }

    /**
     * 插入记录
     * @access public
     * @param mixed $datas 数据
     * @param array $options 参数表达式
     * @param boolean $replace 是否replace
     * @return false | integer
     */
    public function insertAll($datas,$options=array(),$replace=false) {
        if(!is_array($datas[0])) return false;
        $fields = array_keys($datas[0]);
        array_walk($fields, array($this, 'parseKey'));
        $values  =  array();
        foreach ($datas as $data){
            $value   =  array();
            foreach ($data as $key=>$val){
                $val   =  $this->parseValue($val);
                if(is_scalar($val)) { // 过滤非标量数据
                    $value[]   =  $val;
                }
            }
            $values[]    = '('.implode(',', $value).')';
        }
        $sql   =  ($replace?'REPLACE':'INSERT').' INTO '.$this->parseTable($options['table']).' ('.implode(',', $fields).') VALUES '.implode(',',$values);
        return $this->execute($sql);
    }

    /**
     * 关闭数据库
     * @access public
     * @return void
     */
    public function close() {
        if ($this->_linkID){
            mysql_close($this->_linkID);
        }
        $this->_linkID = null;
    }

    /**
     * 数据库错误信息
     * 并显示当前的SQL语句
     * @access public
     * @return string
     */
    public function error() {
        $this->error = mysql_errno().':'.mysql_error($this->_linkID);
        if('' != $this->queryStr){
            $this->error .= "\n [ SQL语句 ] : ".$this->queryStr;
        }
        //trace($this->error,'','ERR'); 
        return $this->error;
    }

    /**
     * SQL指令安全过滤
     * @access public
     * @param string $str  SQL字符串
     * @return string
     */
    public function escapeString($str) {
        if($this->_linkID) {
            return mysql_real_escape_string($str,$this->_linkID);
        }else{
            return mysql_escape_string($str);
        }
    }

    /**
     * 字段和表名处理添加`
     * @access protected
     * @param string $key
     * @return string
     */
    protected function parseKey(&$key) {
        $key   =  trim($key);
        if(!preg_match('/[,\'\"\*\(\)`.\s]/',$key)) {
           $key = '`'.$key.'`';
        }
        return $key;
    }
}