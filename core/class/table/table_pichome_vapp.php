<?php
    if(!defined('IN_OAOOA')) {
        exit('Access Denied');
    }
    class table_pichome_vapp extends dzz_table
    {
        public function __construct()
        {
            
            $this->_table = 'pichome_vapp';
            $this->_pk = 'appid';
            $this->_pre_cache_key = 'pichome_vapp';
            $this->_cache_ttl = 3600;
            parent::__construct();
        }
        private function code62($x) {
            $show = '';
            while($x > 0) {
                $s = $x % 62;
                if ($s > 35) {
                    $s = chr($s+61);
                } elseif ($s > 9 && $s <=35) {
                    $s = chr($s + 55);
                }
                $show .= $s;
                $x = floor($x/62);
            }
            return $show;
        }
        public function getSid($url) {
            $url = crc32($url);
            $result = sprintf("%u", $url);
            return self::code62($result);
        }
        public function insert($setarr){
            $path = $setarr['path'];
            if($appid = DB::result_first("select appid from %t where path = %s",array($this->_table,$path))){
                return $appid;
            }else{
                $setarr['appid'] = $this->getSid($setarr['path']);
                if(parent::insert($setarr)){
                    return $setarr['appid'];
                }
            }
        }
        
        public function fetch_by_path($path){
            return  DB::fetch_first("select * from %t where path = %s",array($this->_table,$path));
        }
        //获取不重复的应用名称
        public function getNoRepeatName($name)
        {
            static $i = 0;
            if (DB::result_first("select COUNT(*) from %t where appname=%s ", array($this->_table, $name))) {
                $name = preg_replace("/\(\d+\)/i", '', $name) . '(' . ($i + 1) . ')';
                $i += 1;
                return $this->getNoRepeatName($name);
            } else {
                return $name;
            }
        }
     
        //删除虚拟应用
        public function delete_vapp_by_appid($appid){
            //删除文件表数据
            C::t('pichome_resources')->delete_by_appid($appid);
            //删除目录表数据
            C::t('pichome_folder')->delete_by_appid($appid);
            //删除目录文件关系表数据
            C::t('pichome_folderresources')->delete_by_appid($appid);
            //删除标签分类表数据
            C::t('pichome_taggroup')->delete_by_appid($appid);
            //删除标签关系表数据
            C::t('pichome_tagrelation')->delete_by_appid($appid);
            //删除最近搜索表数据
            C::t('pichome_searchrecent')->delete_by_appid($appid);
            return parent::delete($appid);
        }
        
        public function fetch_all_sharedownlod(){
            $downshare = array();
            foreach(DB::fetch_all("select appid,download,share from %t where 1",array($this->_table)) as $v){
                $downshare[$v['appid']]=['share'=>$v['share'],'download'=>$v['download']];
            }
            return $downshare;
        }
    }