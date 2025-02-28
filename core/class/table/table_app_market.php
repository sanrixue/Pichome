<?php
/*
 * @copyright   QiaoQiaoShiDai Internet Technology(Shanghai)Co.,Ltd
 * @license     https://www.oaooa.com/licenses/
 * 
 * @link        https://www.oaooa.com
 * @author      zyx(zyx@oaooa.com)
 */

if(!defined('IN_OAOOA')) {
	exit('Access Denied');
}

class table_app_market extends dzz_table
{
	public function __construct() {

		$this->_table = 'app_market';
		$this->_pk    = 'appid';
		$this->_pre_cache_key = 'app_market_';
		$this->_cache_ttl = 60*60;

		parent::__construct();
	}
	public function update($appid,$setarr){
		if(($ret=parent::update($appid,$setarr)) && isset($setarr['available'])){
			//如果是启用或关闭时，更新钩子表的status字段
			C::t('hooks')->update_by_appid($appid,array('status'=>intval($setarr['available'])));
		}
		return $ret;
	}

	public function fetch_by_appid($appid,$havecount=false){ //返回一条数据同时加载统计表数据
	 	global $_G;
		$appid = intval($appid);
		if(!$data=parent::fetch($appid)) return array();
		if($data['appico']!='dzz/images/default/icodefault.png' && !preg_match("/^(http|ftp|https|mms)\:\/\/(.+?)/i", $data['appico'])){
			$data['appico']=$_G['setting']['attachurl'].$data['appico'];
		}
		$data['fileext']=$data['fileext']?explode(',',$data['fileext']):array();
		$data['icon']=$data['appico'];
		$data['title']=$data['appname'];
		$data['url']=replace_canshu($data['appurl']);
		$data['noticeurl']=replace_canshu($data['noticeurl']);
        if($data['uids']){
            $datauidarr= explode(',',$data['uids']);
            $uids=$orgids = [];
            foreach($datauidarr as $v){
                if(strpos($v,'u_') === 0){
                    $uids[]= intval(str_replace('u_','',$v));
                }elseif(strpos($v,'g_') === 0){
                    $orgid = intval(str_replace('g_','',$v));
                    $pathkey = DB::result_first("select pathkey from %t  where  orgid = %d",array('organization',$orgid));
                    foreach(DB::fetch_all("select orgid from %t where pathkey LIKE %s",array('organization',$pathkey.'%')) as $ov){
                        $orgids[] = $ov['orgid'];
                    }
                }
            }
            $data['uids'] = ['uids'=>$uids,'orgids'=>$orgids];
            foreach(DB::fetch_all("select uid from %t  where orgid in(%n)",array('organization_user',$orgids)) as $v){
                $uids[] = $v['uid'];
            }
            $data['memberids']=$uids;
        }
		/*if($havecount){
			$data['viewnum']=intval($count['viewnum']);
			$data['replynum']=intval($count['replynum']);
			$data['downnum']=intval($count['downnum']);
			$data['star']=intval($count['star']);
			$data['starnum']=intval($count['starnum']);
		}*/
		return $data;
	}
	public function fetch_all_by_appid($appids,$havecount=false){ //返回数据同时加载统计表数据
		 global $_G;
		 if(!$appids) return false;
		 if(!is_array($appids)){
			 $appids=array($appids);
		 }
		 $return=array();
		 foreach($appids as $appid){
			$appid = intval($appid);
			if(!$data=parent::fetch($appid)) continue;
			if($data['appico']!='dzz/images/default/icodefault.png' && !preg_match("/^(http|ftp|https|mms)\:\/\/(.+?)/i", $data['appico'])){
				$data['appico']=$_G['setting']['attachurl'].$data['appico'];
			}
             if($data['uids']){
                 $datauidarr= explode(',',$data['uids']);
                 $uids=$orgids = [];
                 foreach($datauidarr as $v){
                     if(strpos($v,'u_') === 0){
                         $uids[]= intval(str_replace('u_','',$v));
                     }elseif(strpos($v,'g_') === 0){
                         $orgid = intval(str_replace('g_','',$v));
                         $pathkey = DB::result_first("select pathkey from %t  where  orgid = %d",array('organization',$orgid));
                         foreach(DB::fetch_all("select orgid from %t where pathkey LIKE %s",array('organization',$pathkey.'%')) as $ov){
                             $orgids[] = $ov['orgid'];
                         }
                     }
                 }
                 foreach(DB::fetch_all("select uid from %t  where orgid in(%n)",array('organization_user',$orgids)) as $v){
                     $uids[] = $v['uid'];
                 }
                 $data['memberids']=$uids;
             }
			$data['fileext']=$data['fileext']?explode(',',$data['fileext']):array();
			$data['icon']=$data['appico'];
			$data['title']=$data['appname'];
			$data['url']=replace_canshu($data['appurl']);
			$data['appadminurl']=replace_canshu($data['appadminurl']);
			if($data['showadmin'] && empty($data['appadminurl'])){
				$data['appadminurl']=replace_canshu($data['appurl']);
			}
			$data['noticeurl']=replace_canshu($data['noticeurl']);
			/*if($havecount){
				$data['viewnum']=intval($count['viewnum']);
				$data['replynum']=intval($count['replynum']);
				$data['downnum']=intval($count['downnum']);
				$data['star']=intval($count['star']);
				$data['starnum']=intval($count['starnum']);
			}*/
			$data['appname']=lang($data['appname']);
			$return[$appid]=$data;
		}
		return $return;
	}
	public function delete_by_appid($appids){ //删除应用
		global $_G;
	    if(!is_array($appids)) $appids=array(intval($appids));
		
		$data=DB::fetch_all("SELECT * FROM %t WHERE appid IN(%n)",array($this->_table,$appids));
		foreach($data as $value){
			
			if(strpos($value['appico'],'appico')===0){//删除应用图标
				@unlink($_G['setting']['attachdir'].$value['appico']);
			}
			C::t('app_pic')->delete_by_appid($value['appid']);//删除介绍图片
			C::t('app_open')->delete_by_appid($value['appid']);//删除打开方式；
			C::t('app_relative')->delete_by_appid($value['appid']);//删除标签
			C::t('app_user')->delete_by_appid($value['appid']); //删除用户默认打开方式
			C::t('app_organization')->delete_by_appid($value['appid']);//删除部门应用
			C::t('hooks')->delete_by_appid($value['appid']);//删除相关钩子
		}
		$this->delete($appids);
		return true;
	}
	/*public function fetch_all_by_tagid($classid,$count=false,$force=false){
		if($force || ($data = $this->fetch_cache(intval($classid),'app_market_class_') === false)) {
			$data=DB::fetch_all("select * from %t where classid= %d ",array($this->_table,$classid));
			if(!empty($data)) $this->store_cache(intval($classid), $data, 3600,'app_market_class_');
		}
		return $count?count($data):$data;
	}*/
	
	public function get_appid_by_appurl($appurl){ 
	  	return DB::fetch_all("select appid from %t where appurl=%s",array($this->_table,$appurl));
		
	}
	public function fetch_all_by_notdelete($uid=0){ //取得所有默认不能删除的应用
	    $params = array($this->_table);
		if($uid && $space=getuserbyuid($uid)){
			if($space['groupid']==1){//系统管理员
				 $l=" `group` = '1'";
				if($notappids=C::t('app_organization')->fetch_notin_appids_by_uid($uid)){
					$l.=" and appid  NOT IN (".dimplode($notappids).") ";
				}
				 $sql="`position`>0 and (`group`='0' OR `group`=2 OR `group`=3 OR (".$l.")) "; 
			}elseif($space['groupid']==2){
				 $l=" (`group` = '1')";
				if($notappids=C::t('app_organization')->fetch_notin_appids_by_uid($uid)){
					$l.=" and appid  NOT IN (".dimplode($notappids).") ";
				}
				$sql=" `position`>0 and (`group` = '2' OR `group`='0' or (".$l."))";
			}else{								//普通成员
				//属于普通用户应用但不属于特定部门的应用
                $l=" (`group` = '1')";
                $sql=" isnull(uids) and `position`>0 and (`group`='0' or  (".$l."))";
                $orsql = " (`position`>0) and ";
                $orsqlarr = ['FIND_IN_SET(%s,uids)'];
                $orparams = ['u_'.$uid];
                $orgarr = [];
                foreach(C::t('organization_user')->fetch_org_by_uid($uid) as $v){
                    $tmporgarr = C::t('organization')->fetch_parent_by_orgid($v);
                    $orgarr = array_merge($orgarr,$tmporgarr);
                }
                $orgarr = array_unique($orgarr);
                foreach($orgarr as $orgid){
                    $orsqlarr[] = 'FIND_IN_SET(%s,uids)';
                    $orparams[] = 'g_'.$orgid;
                }
                if(count($orsqlarr) > 1){
                    $orsql .= '('.implode(' or ',$orsqlarr).')';
                    $params = array_merge($params,$orparams);
                }else{
                    $orsql .= $orsqlarr[0];
                    $params = array_merge($params,$orparams);
                }
                
            }
		}else{ //游客
			$sql="`position`>0 and (`group`='-1' or `group`='0')";
		}
        if(!$orsql) $orsql = 0;
	  	return DB::fetch_all("select * from %t where  (($sql ) or ($orsql)) and  notdelete>0 and available>0 order by disp ",$params,'appid');
	}
	public function fetch_all_by_default($uid=0,$hasnoavailable=false){ //取得所有默认的应用
        $params = array($this->_table);
		if($uid && $space=getuserbyuid($uid)){
			if($space['groupid']==1){//系统管理员
				 $l="`group` = '1'";
				 $sql="`position`>0 and (`group`='0' OR `group`='2' OR `group`='3' OR (".$l."))"; 
			}elseif($space['groupid']==2){//机构和部门管理员
				 $l=" (`group` = '1')";
				$sql=" `position`>0 and (`group` = '2' OR `group`='0' or (".$l."))";
			}else{//普通成员
				//属于普通用户应用但不属于特定部门的应用
				$l=" (`group` = '1')";
				$sql=" (isnull(uids) or uids='') and `position`>0 and (`group`='0' or  (".$l."))";
				$orsql = " (`position`>0) and ";
				$orsqlarr = ['FIND_IN_SET(%s,uids)'];
				$orparams = ['u_'.$uid];
				$orgarr = [];
				foreach(C::t('organization_user')->fetch_org_by_uid($uid) as $v){
                    $tmporgarr = C::t('organization')->fetch_parent_by_orgid($v);
                    $orgarr = array_merge($orgarr,$tmporgarr);
                }
				$orgarr = array_unique($orgarr);
				foreach($orgarr as $orgid){
                    $orsqlarr[] = 'FIND_IN_SET(%s,uids)';
				    $orparams[] = 'g_'.$orgid;
                }
				if(count($orsqlarr) > 1){
				    $orsql .= '('.implode(' or ',$orsqlarr).')';
                    $params = array_merge($params,$orparams);
                }else{
				    $orsql .= $orsqlarr[0];
				    $params = array_merge($params,$orparams);
                }
				
			}
		}else{ //游客
			$sql="`position`>0 and (`group`='-1' or `group`='0')";
		}
		if(!$orsql) $orsql = 0;

        if($hasnoavailable){
            return DB::fetch_all("select * from %t where (($sql ) or ($orsql)) order by disp ",$params,'appid');
        }else{
            return DB::fetch_all("select * from %t where (($sql ) or ($orsql))and available>0 order by disp ",$params,'appid');
        }
	  	
	}
	
	public function fetch_appid_by_mod($mod,$match=0){//$match==1表示全匹配，默认模糊匹配
	    $sql='';
		$param=array($this->_table);
		if(!$match){
			$sql=" appurl LIKE %s";
			$param[]='%'.$mod.'%';
		}else{
			$sql=" appurl = %s";
			$param[]=$mod;
		}
		return DB::result_first("select appid from %t where $sql ",$param);
	}
	public function fetch_by_identifier($identifier,$app_path='dzz'){
	    return DB::fetch_first("select * from %t where app_path=%s and identifier=%s ",array($this->_table,$app_path,$identifier));
	}
	public function fetch_by_mod(){
	    return DB::fetch_first("select * from %t where app_path=%s and identifier=%s ",array($this->_table,CURSCRIPT,CURMODULE));
	}
	public function fetch_all_identifier($available=0){
		$data=array();
		$sql="identifier!=''";
		if($available){
			$sql.=" and `available`>0";
		}
		foreach(DB::fetch_all("select appid,identifier,app_path from %t where %i ",array($this->_table,$sql)) as $value){
			$data[$value['appid']]=$value;
		}
		return $data;
	}
	public function fetch_all_by_appurl($appurl,$identifier){
		
		return DB::fetch_all("select * from %t where appurl=%s and identifier=%s",array($this->_table,$appurl,$identifier));
	}
	public function fetch_appico_by_appid($appid){
	    $appid = intval($appid);
        return DB::result_first("select appico from %t where appid = %d",array($this->_table,$appid));
    }
	
}
