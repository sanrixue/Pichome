<?php

if(!defined('IN_OAOOA')) {
        exit('Access Denied');
}

$operation = isset($_GET['operation']) ? trim($_GET['operation']):'';
if($operation == 'filelist'){
    $sql = "select distinct  r.rid from %t  r ";
    $wheresql = " r.isdelete < 1 ";
    $params = ['pichome_resources'];
    $appid = isset($_GET['appid']) ? trim($_GET['appid']):'';
	$page = isset($_GET['page']) ? intval($_GET['page']):1;
    $perpage = isset($_GET['perpage']) ? intval($_GET['perpage']):30;
	$start = ($page - 1)*$perpage;
	$limitsql = "limit $start,".$perpage;
 
	if(!isset($_GET['order'])){
        //获取用户默认排序方式
        $sortdata = C::t('user_setting')->fetch_by_skey('pichomesortfileds');
        $sortfilearr = ['btime'=>1,'mtime'=>2,'dateline'=>3,'name'=>4,'size'=>5,'grade'=>6,'duration'=>7,'whsize'=>8];
        if($sortdata){
            $sortdatarr = unserialize($sortdata);
            $order = $sortdatarr['filed']?$sortfilearr[$sortdatarr['filed']]:1;
            $asc = ($sortdatarr['sort']) ? $sortdatarr['sort']:'desc';
        }else{
            $order =1;
            $asc = 'desc';
        }
    }else{
        $order = isset($_GET['order']) ? intval($_GET['order']):1;
        $asc = (isset($_GET['asc']) &&  trim($_GET['asc'])) ? trim($_GET['asc']):'desc';
    }
	
	$orderarr = [];
	$orderparams = [];
    
    $appid = isset($_GET['appid']) ? trim($_GET['appid']) : '';
    $fids = isset($_GET['fids']) ? trim($_GET['fids']):'';
    if ($appid) {
        $wheresql .= ' and r.appid = %s  ';
        $para[] = $appid;
    }
    if ($fids) {
        $sql .= " LEFT JOIN %t fr on fr.rid = r.rid ";
        $wheresql .= ' and fr.fid  in(%n)';
        $fidarr = explode(',',$fids);
        $para[] = $fidarr;
        $params[] = 'pichome_folderresources';
        $folderdata = [];
        foreach(DB::fetch_all("select fname,fid from %t where fid in(%n)",array('pichome_folder',$fidarr)) as $v){
            $folderdata[$v['fid']] = $v['fname'];
        }
    }
    //关键词条件
    $keyword = isset( $_GET[ 'keyword' ] ) ? htmlspecialchars( $_GET[ 'keyword' ] ) : '';
    if ( $keyword ) {
        $sql .= " LEFT JOIN  %t c on c.rid = r.rid left join %t rt on r.rid = rt.rid left join %t t on rt.tid= t.tid ";
        $params[] = 'pichome_comments';
        $params[] = 'pichome_resourcestag';
        $params[] = 'pichome_tag';
        if(!in_array('pichome_resources_attr',$params)){
            $sql .= "left join %t ra on r.rid = ra.rid";
            $params[] = 'pichome_resources_attr';
        }
        $keywords = array();
        $arr1 = explode( '+', $keyword );
        foreach ( $arr1 as $value1 ) {
            $value1 = trim( $value1 );
            $arr2 = explode( ' ', $value1 );
            $arr3 = array();
            foreach ( $arr2 as $value2 ) {
                $arr3[] = "r.name LIKE %s";
                $para[] = '%' . $value2 . '%';
                $arr3[] = "ra.link LIKE %s";
                $para[] = '%' . $value2 . '%';
                $arr3[] = "ra.desc LIKE %s";
                $para[] = '%' . $value2 . '%';
                $arr3[] = "c.annotation LIKE %s";
                $para[] = '%' . $value2 . '%';
                $arr3[] = "t.tagname LIKE %s";
                $para[] = '%' . $value2 . '%';
            }
            $keywords[] = "(" . implode( " OR ", $arr3 ) . ")";
        }
        if ( $keywords ) {
            $wheresql .= " and (" . implode( " AND ", $keywords ) . ")";
        }
    }

    //颜色条件
    if(isset($_GET['color'])){
        $persion = isset($_GET['persion']) ? intval($_GET['persion']) :0;
        $maxColDist = 764.8339663572415;
        $similarity = 90+(10/100)*$persion;
        $color = trim($_GET['color']);
        $rgbcolor = hex2rgb($color);
        $sql .= " left join %t p on r.rid = p.rid ";
        $params[] = 'pichome_palette';
        $wheresql .= "and round((%d-sqrt((((2+(p.r+%d)/2)/256)*(pow((%d-p.r),2))+(4*pow((%d-p.g),2)) + (((2+(255-(p.r+%d)/2))/256))*(pow((%d-p.b), 2)))))/%d,4)*100 >= %d";
        if(empty($para))$para = array($maxColDist,$rgbcolor['r'],$rgbcolor['r'],$rgbcolor['g'],$rgbcolor['r'],$rgbcolor['b'],$maxColDist,$similarity);
        else $para = array_merge($para,array($maxColDist,$rgbcolor['r'],$rgbcolor['r'],$rgbcolor['g'],$rgbcolor['r'],$rgbcolor['b'],$maxColDist,$similarity));
        $orderarr[] = 'round((%d-sqrt((((2+(p.r+%d)/2)/256)*(pow((%d-p.r),2))+(4*pow((%d-p.g),2)) + (((2+(255-(p.r+%d)/2))/256))*(pow((%d-p.b), 2)))))/%d,4)*100*p .weight desc';
        if(empty($orderparams))$orderparams = array($maxColDist,$rgbcolor['r'],$rgbcolor['r'],$rgbcolor['g'],$rgbcolor['r'],$rgbcolor['b'],$maxColDist,$similarity);
        else $orderparams = array_merge($orderparams,array($maxColDist,$rgbcolor['r'],$rgbcolor['r'],$rgbcolor['g'],$rgbcolor['r'],$rgbcolor['b'],$maxColDist,$similarity));
    }
    //标签条件
    if(isset($_GET['tag'])){
        $tagwherearr = [];
        $tagrelative = isset($_GET['tagrelative']) ? intval($_GET['tagrelative']):1;
        if(!in_array('pichome_resources_attr',$params)){
            $sql .= "left join %t ra on r.rid = ra.rid";
            $params[] = 'pichome_resources_attr';
        }
        $tag = trim($_GET['tag']);
        if($tag == -1){
            $wheresql  .= " and ra.tag =  '' ";
        }else{
            $tagval = explode(',',trim($_GET['tag']));
            if(!empty($tagval)){
                $tagdata=[];
				if($appid){
					foreach(DB::fetch_all("select t.tagname,t.tid,tr.cid from %t  t left  join %t tr on t.tid = tr.tid where t.tid in(%n) and tr.appid=%s",array('pichome_tag','pichome_tagrelation',$tagval,$appid)) as $tv){
					    $tagdata[] = array('tagname'=>$tv['tagname'],'tid'=>intval($tv['tid']),'cid'=>$tv['cid']);
					}
				}else{
					foreach(DB::fetch_all("select tagname,tid from %t where tid in(%n) ",array('pichome_tag',$tagval)) as $tv){
					    $tagdata[] = array('tagname'=>$tv['tagname'],'tid'=>intval($tv['tid']));
					}
				}
             
				//print_r($tagdata);die;
             /*   foreach(C::t('pichome_tag')->fetch_all($tagval) as $tv){
                    $tagdata[] = array('tagname'=>$tv['tagname'],'tid'=>intval($tv['tid']));
                }*/
            }
            foreach($tagval as $v){
                $tagwherearr[] = " find_in_set(%d,ra.tag)";
                $para[] = $v;
            }
            if($tagrelative){
                $wheresql  .= " and (" . implode( " or ", $tagwherearr ) . ")";
            }else{
                $wheresql .= " and (" . implode( " and ", $tagwherearr ) . ")";
            }
        }
        
    }
    
    //时长条件
    if(isset($_GET['duration'])){
        if(!in_array('pichome_resources_attr',$params)){
            $sql .= "left join %t ra on r.rid = ra.rid";
            $params[] = 'pichome_resources_attr';
        }
        $durationarr = explode('_',$_GET['duration']);
        $dunit = isset($_GET['dunit']) ? trim($_GET['dunit']):'s';
        if($durationarr[0]){
            $wheresql  .= " and ra.duration >= %d";
            $para[] =($dunit == 'm') ? $durationarr[0]*60:$durationarr[0];
        }
        
        if($durationarr[1]){
            $wheresql  .= " and ra.duration <= %d";
            $para[] = ($dunit == 'm') ? $durationarr[1]*60:$durationarr[1];
        }
    }
    //标注条件
    if(isset($_GET['comments'])){
        $sql .=" left join %t c on r.rid = c.rid";
        $params[] = 'pichome_comments';
        $comments = intval($_GET['comments']);
        $cval = isset($_GET['cval']) ?  trim($_GET['cval']):'';
        if(!$comments){
            $wheresql .= " and  isnull(c.annotation) ";
        }else{
            if($cval){
                $cvalarr = explode(',',$cval);
                $cvalwhere = [];
                foreach($cvalarr as $cv){
                    $cvalwhere[]= " c.annotation like %s";
                    $para[] = '%' . $cv . '%';
                }
                $wheresql  .= " and (" . implode( " or ", $cvalwhere ) . ")";
            }else{
                $wheresql  .= " and  !isnull(c.annotation)";
            }
        }
    }
    //注释条件
    if (isset($_GET['desc'])) {
        if(!in_array('pichome_resources_attr',$params)){
            $sql .= "left join %t ra on r.rid = ra.rid";
            $params[] = 'pichome_resources_attr';
        }
        $desc = intval($_GET['desc']);
        $descval = isset($_GET['descval']) ? trim($_GET['descval']) : '';
        if (!$desc) {
            $wheresql .= " and  (isnull(ra.desc) or ra.desc='') ";
        } else {
            if ($descval) {
                $descvalarr = explode(',', $descval);
                $descvalwhere = [];
                foreach ($descvalarr as $dv) {
                    $descvalwhere[] = "  ra.desc  like %s";
                    $para[] = '%' . $dv . '%';
                }
                $wheresql .= " and (" . implode(" or ", $descvalwhere) . ")";
            } else {
                $wheresql .= " and   ra.desc !='' ";
            }
        }
    }
    //链接条件
    if (isset($_GET['link'])) {
        if(!in_array('pichome_resources_attr',$params)){
            $sql .= "left join %t ra on r.rid = ra.rid";
            $params[] = 'pichome_resources_attr';
        }
        $link = intval($_GET['link']);
        $linkval = isset($_GET['linkval']) ? trim($_GET['linkval']) : '';
        if (!$link) {
            $wheresql .= " and  (isnull(ra.link) or ra.link='') ";
        } else {
            if ($linkval) {
                $linkvalarr = explode(',', $linkval);
                $linkvalwhere = [];
                foreach ($linkvalarr as $lv) {
                    $linkvalwhere[] = "  ra.link  like %s";
                    $para[] = '%' . $lv . '%';
                }
                $wheresql .= " and (" . implode(" or ", $linkvalwhere) . ")";
            } else {
                $wheresql .= " and  ra.link !='' ";
            }
        }
    }
    //形状条件
    if (isset($_GET['shape'])) {
        $shape = trim($_GET['shape']);
        $shapes = explode(',', $shape);
        //指定宽高比
        // $swidth = 0;
        // $sheight = 0;
        // if(isset($_GET['shapesize'])){
        //     $shapesize = trim($_GET['shapesize']);
        //     $shapesizes = explode(':',$shapesize);
        //     $swidth = intval($shapesizes[0]);
        //     $sheight = intval($shapesizes[1]);
        // }
        $shapewherearr = [];
        foreach ($shapes as $v) {
            switch ($v) {
                case 7://方图
                    $shapewherearr[] = '  round((r.width / r.height) * 100) = %d';
                    $para[] = 100;
                    break;
                case 8://横图
                    $shapewherearr[] = '  round((r.width / r.height) * 100) > %d and  round((r.width / r.height) * 100) < 250';
                    $para[] = 100;
                    break;
                case 5://细长横图
                    $shapewherearr[] = '  round((r.width / r.height) * 100) >= %d';
                    $para[] = 250;
                    break;
                case 6://细长竖图
                    $shapewherearr[] = '  round((r.width / r.height) * 100) <= %d';
                    $para[] = 40;
                    break;
                case 9://竖图
                    $shapewherearr[] = '  round((r.width / r.height) * 100) < %d and round((r.width / r.height) * 100) > %d';
                    $para[] = 100;
                    $para[] = 40;
                    break;
                case 1://4:3
                    $shapewherearr[] = '  round((r.width / r.height) * 100) = %d';
                    $para[] = round((4 / 3) * 100);
                    break;
                case 2://3:4
                    $shapewherearr[] = '  round((r.width / r.height) * 100) = %d';
                    $para[] = (3 / 4) * 100;
                    break;
                case 3://16:9
                    $shapewherearr[] = '  round((r.width / r.height) * 100) = %d';
                    $para[] = round((16 / 9) * 100);
                    break;
                case 4://9:16
                    $shapewherearr[] = '  round((r.width / r.height) * 100) = %d';
                    $para[] = round((9 / 16) * 100);
                    break;
                /*   case 10:
                       $shapewherearr[] = '  round((r.width / r.height) * 100) = %d';
                       $para[] = ($swidth / $sheight) * 100;
                       break;*/
            }
        }
		if(isset($_GET['shapesize'])){
		    $shapesize = trim($_GET['shapesize']);
		    $shapesizes = explode(':',$shapesize);
		    $swidth = intval($shapesizes[0]);
		    $sheight = intval($shapesizes[1]);
			$shapewherearr[] = '  round((r.width / r.height) * 100) = %d';
			$para[] = ($swidth / $sheight) * 100;
		}
        if ($shapewherearr) {
            $wheresql .= " and (" . implode(" or ", $shapewherearr) . ")";
        }
    }
    //评分条件
    if(isset($_GET['grade'])){
        $grade = trim($_GET['grade']);
        $grades = explode(',',$grade);
        $wheresql  .= " and r.grade in(%n)";
        $para[] = $grades;
    }
    //类型条件
    if(isset($_GET['ext'])){
        $ext = trim($_GET['ext']);
        $exts = explode(',',$ext);
        $wheresql  .= " and r.ext in(%n)";
        $para[] = $exts;
    }
    //添加日期
    if(isset($_GET['btime'])){
        $btime = explode('_',$_GET['btime']);
        $bstart = strtotime($btime[0]);
        $bend = strtotime($btime[1])+ 24 * 60 * 60;
        if($bstart){
            $wheresql  .= " and r.btime > %d";
            //将时间补足13位
            $para[] = $bstart*1000;
        }
        if($bend){
            $wheresql  .= " and r.btime < %d";
            //将时间补足13位
            $para[] = $bend*1000;
        }
    }
    //修改日期
    if(isset($_GET['dateline'])){
        $dateline = explode('_',$_GET['dateline']);
        $dstart = strtotime($dateline[0]);
        $dend = strtotime($dateline[1])+ 24 * 60 * 60;
        if($dstart){
            $wheresql  .= " and r.dateline > %d";
            //将时间补足13位
            $para[] = $dstart*1000;
        }
        
        if($dend){
            $wheresql  .= " and r.dateline < %d";
            //将时间补足13位
            $para[] = $dend*1000;
        }
    }
    //创建日期
    if(isset($_GET['mtime'])){
        $mtime = explode('_',$_GET['mtime']);
        $mstart = strtotime($mtime[0]);
        $mend = strtotime($mtime[1])+ 24 * 60 * 60;
        if($mstart){
            $wheresql  .= " and r.mtime > %d";
            //将时间补足13位
            $para[] = $mstart*1000;
        }
        
        if($mend){
            $wheresql  .= " and r.mtime < %d";
            //将时间补足13位
            $para[] =$mend*1000;
        }
    }
    //尺寸条件
    if(isset($_GET['wsize']) || isset($_GET['hsize'])){
        $wsizearr = explode('_',$_GET['wsize']);
        $hsizearr = explode('_',$_GET['hsize']);
        if($wsizearr[0]){
            $wheresql  .= " and r.width >= %d";
            $para[] = intval($wsizearr[0]);
        }
        if($wsizearr[1]){
            $wheresql  .= " and r.width <= %d";
            $para[] = intval($wsizearr[1]);
        }
        if($hsizearr[0]){
            $wheresql  .= " and r.height >= %d";
            $para[] = intval($hsizearr[0]);
        }
        if($hsizearr[1]){
            $wheresql  .= " and r.height <= %d";
            $para[] = intval($hsizearr[1]);
        }
    }
    
    //大小条件
    if(isset($_GET['size'])){
        $size = explode('_',$_GET['size']);
        $unit = isset($_GET['unit']) ? intval($_GET['unit']):1;
        switch ($unit){
            case 0://b
                $size[0] = $size[0];
                $size[1] = $size[1];
                break;
            case 1://kb
                $size[0] = $size[0]*1024;
                $size[1] = $size[1]*1024;
                break;
            case 2://mb
                $size[0] = $size[0]*1024*1024;
                $size[1] = $size[1]*1024*1024;
                break;
            case 3://gb
                $size[0] = $size[0]*1024*1024*1024;
                $size[1] = $size[1]*1024*1024*1024;
                break;
        }
        if($size[0]){
            $wheresql  .= " and r.szie > %d";
            $para[] = $size[0];
        }
        if($size[1]){
            $wheresql  .= " and r.szie < %d";
            $para[] = $size[1];
        }
    }
    $data = [];
    if(!empty($para)) $params = array_merge($params,$para);
    $rids = [];
    switch($order){
        case 1://添加日期
            $orderarr[] = ' r.btime '.$asc;
            break;
        case 2://创建日期
            $orderarr[] = ' r.mtime '.$asc;
            break;
        case 3://修改日志
            $orderarr[] = ' r.dateline '.$asc;
            break;
        case 4://标题
            $orderarr[] = ' r.name '.$asc;
            break;
        case 5://大小
            $orderarr[] = ' r.size '.$asc;
            break;
        case 6://评分
            $orderarr[] = ' r.grade '.$asc;
            break;
        case 7://时长
            if(!in_array('pichome_resources_attr',$params)){
                $sql .= "left join %t ra on r.rid = ra.rid";
                $params[] = 'pichome_resources_attr';
            }
            $orderarr[] = ' ra.duration '.$asc;
            break;
        case 8://尺寸
            $orderarr[] = ' r.width*r.height '.$asc;
            break;
        default:
            $orderarr[] = ' r.dateline '.$asc;
    }
    $ordersql = implode(',',$orderarr);
    if(!empty($orderparams))  $params = array_merge($params,$orderparams);
    foreach ( DB::fetch_all( " $sql where $wheresql order by $ordersql $limitsql", $params) as $value ) {
        $rids[] = $value['rid'];
    }
    $data = array();
   if(!empty($rids)) $data = C::t('pichome_resources')->getdatasbyrids($rids);
    if ( count( $rids ) >= $perpage ) {
        $total = $start + $perpage * 2 - 1;
    } else {
        $total = $start + count( $rids );
    }
    $return = array(
        'appid' => $appid,
        'total' => $total,
        'data' => $data ? $data : array(),
        'param' => array(
            'order' => $order,
            'page' => $page,
            'perpage' => $perpage,
            'total' => $total,
            'asc' => $asc,
            'keyword' => $keyword,
            'tagdata'=>$tagdata,
            'folderdata'=>isset($folderdata) ? $folderdata:array()
        )
    );
	exit(json_encode(array('data'=>$return)));
}
