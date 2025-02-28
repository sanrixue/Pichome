<?php
    if (!defined('IN_OAOOA')) {
        exit('Access Denied');
    }
    
    $operation = isset($_GET['operation']) ? trim($_GET['operation']) : '';
    global $_G;
    if ($operation == 'addsearch') {//增加关键词搜索次数
        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
        $appid = isset($_GET['appid']) ? trim($_GET['appid']) : '';
        $ktype = isset($_GET['ktype']) ? intval($_GET['ktype']) : 0;
        if (!$keyword) exit();
        C::t('pichome_searchrecent')->add_search($keyword, $appid, $ktype);
    } elseif ($operation == 'getsearchtag') {//最近搜索标签和热门标签
        $appid = isset($_GET['appid']) ? trim($_GET['appid']) : '';
        //$recenttag = C::t('pichome_searchrecent')->fetch_renctent_search_tag($appid);
        $percachename = 'pichome_searchhot';
        //$recenttag = C::t('pichome_searchrecent')->fetch_renctent_search_tag($appid);
        $hotdatas = false;
        $hotdatas = C::t('cache')->fetch($percachename.$appid);
        if(!$hotdatas){
            $tmpkey = $percachename.$appid;
            $hotdatas = C::t('pichome_searchrecent')->fetch_hotkeyword_by_appid($appid);
            $setarr = ['cachekey'=>$tmpkey,'cachevalue'=>serialize($hotdatas),'dateline'=>time()];
            C::t('cache')->insert($setarr);
        }else{
            
            $hotdatas = unserialize($hotdatas['cachevalue']);
        }
        //$hottags = C::t('pichome_tag')->fetch_data_by_hot();
        exit(json_encode(array('hottags' => $hotdatas)));
    } elseif ($operation == 'getsearchfolder') {//最近搜索目录和目录信息
        $appid = isset($_GET['appid']) ? trim($_GET['appid']) : '';
        $hotsearchnum = C::t('pichome_searchrecent')->fetch_renctent_search_foldername($appid);
        $folderdatanum = C::t('pichome_folder')->fetch_all_folder_by_appid($appid);
        exit(json_encode(array('hotsearchnum' => $hotsearchnum, 'folderdatanum' => $folderdatanum)));
    } elseif ($operation == 'searchmenu_num') {
        $sql = "   %t r ";
        $skey = isset($_GET['skey']) ? trim($_GET['skey']) : '';
        $wheresql = " r.isdelete < 1 ";
        $params = ['pichome_resources'];
        $appid = isset($_GET['appid']) ? trim($_GET['appid']) : '';
        $fids = isset($_GET['fids']) ? trim($_GET['fids']) : '';
        if ($appid) {
            $wheresql .= ' and r.appid = %s ';
            $para[] = $appid;
        }
        if ($fids) {
            $sql .= " LEFT JOIN %t fr on fr.rid = r.rid ";
            $wheresql .= ' and fr.fid  in(%n)';
            $fidarr = explode(',', $fids);
            $para[] = $fidarr;
            $params[] = 'pichome_folderresources';
        }
        //关键词条件
        $keyword = isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : '';
        if ($keyword) {
            $sql .= " LEFT JOIN  %t c on c.rid = r.rid left join %t  rt on r.rid = rt.rid left join %t t on rt.tid= t.tid ";
            $params[] = 'pichome_comments';
            $params[] = 'pichome_resourcestag';
            $params[] = 'pichome_tag';
            if (!in_array('pichome_resources_attr', $params)) {
                $sql .= "left join %t ra on r.rid = ra.rid";
                $params[] = 'pichome_resources_attr';
            }
            $keywords = array();
            $arr1 = explode('+', $keyword);
            foreach ($arr1 as $value1) {
                $value1 = trim($value1);
                $arr2 = explode(' ', $value1);
                $arr3 = array();
                foreach ($arr2 as $value2) {
                    $arr3[] = " r.name LIKE %s ";
                    $para[] = '%' . $value2 . '%';
                    $arr3[] = " ra.link LIKE %s ";
                    $para[] = '%' . $value2 . '%';
                    $arr3[] = " ra.desc LIKE %s ";
                    $para[] = '%' . $value2 . '%';
                    $arr3[] = " c.annotation LIKE %s ";
                    $para[] = '%' . $value2 . '%';
                    $arr3[] = " t.tagname LIKE %s ";
                    $para[] = '%' . $value2 . '%';
                }
                $keywords[] = "(" . implode(" OR ", $arr3) . ")";
            }
            if ($keywords) {
                $wheresql .= " and (" . implode(" AND ", $keywords) . ")";
            }
        }
        //颜色条件
        if (isset($_GET['color'])) {
            $persion = isset($_GET['persion']) ? intval($_GET['persion']) : 0;
            $maxColDist = 764.8339663572415;
            $similarity = 80 + (20 / 100) * $persion;
            $color = trim($_GET['color']);
            $rgbcolor = hex2rgb($color);
            $sql .= " left join %t p on r.rid = p.rid ";
            $params[] = 'pichome_palette';
            $wheresql .= "and round((%d-sqrt((((2+(p.r+%d)/2)/256)*(pow((%d-p.r),2))+(4*pow((%d-p.g),2)) + (((2+(255-(p.r+%d)/2))/256))*(pow((%d-p.b), 2)))))/%d,4)*100 >= %d";
            if (!empty($para)) $para = array_merge($para, array($maxColDist, $rgbcolor['r'], $rgbcolor['r'], $rgbcolor['g'], $rgbcolor['r'], $rgbcolor['b'], $maxColDist, $similarity));
            else  $para = array($maxColDist, $rgbcolor['r'], $rgbcolor['r'], $rgbcolor['g'], $rgbcolor['r'], $rgbcolor['b'], $maxColDist, $similarity);
        }
        //标签条件
        if (isset($_GET['tag'])) {
            $tagwherearr = [];
            $tagrelative = isset($_GET['tagrelative']) ? intval($_GET['tagrelative']) : 1;
            if (!in_array('pichome_resources_attr', $params)) {
                $sql .= "left join %t ra on r.rid = ra.rid";
                $params[] = 'pichome_resources_attr';
            }
            $tag = trim($_GET['tag']);
            if ($tag == -1) {
                $wheresql .= " and ra.tag =  '' ";
            } else {
                $tagval = explode(',', trim($_GET['tag']));
                foreach ($tagval as $v) {
                    $tagwherearr[] = " find_in_set(%d,ra.tag)";
                    $para[] = $v;
                }
                if ($tagrelative) {
                    $wheresql .= " and (" . implode(" or ", $tagwherearr) . ")";
                } else {
                    $wheresql .= " and (" . implode(" and ", $tagwherearr) . ")";
                }
            }
            
        }
        
        //时长条件
        if (isset($_GET['duration'])) {
            if (!in_array('pichome_resources_attr', $params)) {
                $sql .= "left join %t ra on r.rid = ra.rid";
                $params[] = 'pichome_resources_attr';
            }
            $durationarr = explode('_', $_GET['duration']);
            $dunit = isset($_GET['dunit']) ? trim($_GET['dunit']) : 's';
            if ($durationarr[0]) {
                $wheresql .= " and ra.duration >= %d";
                $para[] = ($dunit == 'm') ? $durationarr[0] * 60 : $durationarr[0];
            }
            
            if ($durationarr[1]) {
                $wheresql .= " and ra.duration <= %d";
                $para[] = ($dunit == 'm') ? $durationarr[1] * 60 : $durationarr[1];
            }
        }
        //标注条件
        if (isset($_GET['comments'])) {
            $sql .= " left join %t c on r.rid = c.rid";
            $params[] = 'pichome_comments';
            $comments = intval($_GET['comments']);
            $cval = isset($_GET['cval']) ? trim($_GET['cval']) : '';
            if (!$comments) {
                $wheresql .= " and  isnull(c.annotation) ";
            } else {
                if ($cval) {
                    $cvalarr = explode(',', $cval);
                    $cvalwhere = [];
                    foreach ($cvalarr as $cv) {
                        $cvalwhere[] = " c.annotation like %s";
                        $para[] = '%' . $cv . '%';
                    }
                    $wheresql .= " and (" . implode(" or ", $cvalwhere) . ")";
                } else {
                    $wheresql .= " and  !isnull(c.annotation)";
                }
            }
        }
        //注释条件
        if (isset($_GET['desc'])) {
            if (!in_array('pichome_resources_attr', $params)) {
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
                    $wheresql .= " and ra.desc !=''";
                }
            }
        }
        //链接条件
        if (isset($_GET['link'])) {
            if (!in_array('pichome_resources_attr', $params)) {
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
            // 	$shapesize = trim($_GET['shapesize']);
            // 	$shapesizes = explode(':',$shapesize);
            // 	$swidth = intval($shapesizes[0]);
            // 	$sheight = intval($shapesizes[1]);
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
                        $para[] = (4 / 3) * 100;
                        break;
                    case 2://3:4
                        $shapewherearr[] = '  round((r.width / r.height) * 100) = %d';
                        $para[] = (3 / 4) * 100;
                        break;
                    case 3://16:9
                        $shapewherearr[] = '  round((r.width / r.height) * 100) = %d';
                        $para[] = (16 / 9) * 100;
                        break;
                    case 4://9:16
                        $shapewherearr[] = '  round((r.width / r.height) * 100) = %d';
                        $para[] = (9 / 16) * 100;
                        break;
                    /*case 10:
                        $shapewherearr[] = '  round((r.width / r.height) * 100) = %d';
                        $para[] = ($swidth / $sheight) * 100;
                        break;*/
                }
            }
            if (isset($_GET['shapesize'])) {
                $shapesize = trim($_GET['shapesize']);
                $shapesizes = explode(':', $shapesize);
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
        if (isset($_GET['grade'])) {
            $grade = trim($_GET['grade']);
            $grades = explode(',', $grade);
            $wheresql .= " and r.grade in(%n)";
            $para[] = $grades;
        }
        //类型条件
        if (isset($_GET['ext'])) {
            $ext = trim($_GET['ext']);
            $exts = explode(',', $ext);
            $wheresql .= " and r.ext in(%n)";
            $para[] = $exts;
        }
        //添加日期
        if (isset($_GET['btime'])) {
            $btime = explode('_', $_GET['btime']);
            $bstart = strtotime($btime[0]);
            $bend = strtotime($btime[1]) + 24 * 60 * 60;
            if ($bstart) {
                $wheresql .= " and r.btime > %d";
                //将时间补足13位
                $para[] = $bstart * 1000;
            }
            if ($bend) {
                $wheresql .= " and r.btime < %d";
                //将时间补足13位
                $para[] = $bend * 1000;
            }
        }
        //修改日期
        if (isset($_GET['dateline'])) {
            $dateline = explode('_', $_GET['dateline']);
            $dstart = strtotime($dateline[0]);
            $dend = strtotime($dateline[1]) + 24 * 60 * 60;
            if ($dstart) {
                $wheresql .= " and r.dateline > %d";
                //将时间补足13位
                $para[] = $dstart * 1000;
            }
            
            if ($dend) {
                $wheresql .= " and r.dateline < %d";
                //将时间补足13位
                $para[] = $dend * 1000;
            }
        }
        //创建日期
        if (isset($_GET['mtime'])) {
            $mtime = explode('_', $_GET['mtime']);
            $mstart = strtotime($mtime[0]);
            $mend = strtotime($mtime[1]) + 24 * 60 * 60;
            if ($mstart) {
                $wheresql .= " and r.mtime > %d";
                //将时间补足13位
                $para[] = $mstart * 1000;
            }
            
            if ($mend) {
                $wheresql .= " and r.mtime < %d";
                //将时间补足13位
                $para[] = $mend * 1000;
            }
        }
        //尺寸条件
        if (isset($_GET['wsize']) || isset($_GET['hsize'])) {
            $wsizearr = explode('_', $_GET['wsize']);
            $hsizearr = explode('_', $_GET['hsize']);
            if ($wsizearr[0]) {
                $wheresql .= " and r.width >= %d";
                $para[] = intval($wsizearr[0]);
            }
            if ($wsizearr[1]) {
                $wheresql .= " and r.width <= %d";
                $para[] = intval($wsizearr[1]);
            }
            if ($hsizearr[0]) {
                $wheresql .= " and r.height >= %d";
                $para[] = intval($hsizearr[0]);
            }
            if ($hsizearr[1]) {
                $wheresql .= " and r.height <= %d";
                $para[] = intval($hsizearr[1]);
            }
        }
        
        //大小条件
        if (isset($_GET['size'])) {
            $size = explode('_', $_GET['size']);
            $unit = isset($_GET['unit']) ? intval($_GET['unit']) : 1;
            switch ($unit) {
                case 0://b
                    $size[0] = $size[0];
                    $size[1] = $size[1];
                    break;
                case 1://kb
                    $size[0] = $size[0] * 1024;
                    $size[1] = $size[1] * 1024;
                    break;
                case 2://mb
                    $size[0] = $size[0] * 1024 * 1024;
                    $size[1] = $size[1] * 1024 * 1024;
                    break;
                case 3://gb
                    $size[0] = $size[0] * 1024 * 1024 * 1024;
                    $size[1] = $size[1] * 1024 * 1024 * 1024;
                    break;
            }
            if ($size[0]) {
                $wheresql .= " and r.szie > %d";
                $para[] = $size[0];
            }
            if ($size[1]) {
                $wheresql .= " and r.size < %d";
                $para[] = $size[1];
            }
        }
        $timedataarr = array(
            1 => array(
                'start' => strtotime(date("Y-m-d", time())) * 1000,
                'end' => (strtotime(date("Y-m-d", time())) + 24 * 60 * 60) * 1000,
                'val' => 1,
                'label' => '今日'
            ),
            -1 => array(
                'start' => strtotime(date("Y-m-d", strtotime("-1 day"))) * 1000,
                'end' => (strtotime(date("Y-m-d", time())) + 24 * 60 * 60) * 1000,
                'val' => -1,
                'label' => '昨日'
            ),
            -7 => array(
                'start' => strtotime(date("Y-m-d", strtotime("-7 day"))) * 1000,
                'end' => (strtotime(date("Y-m-d", time())) + 24 * 60 * 60) * 1000,
                'val' => -7,
                'label' => '最近7日'
            ),
            -30 => array(
                'start' => strtotime(date("Y-m-d", strtotime("-30 day"))) * 1000,
                'end' => (strtotime(date("Y-m-d", time())) + 24 * 60 * 60) * 1000,
                'val' => -30,
                'label' => '最近30日'
            ),
            -90 => array(
                'start' => strtotime(date("Y-m-d", strtotime("-90 day"))) * 1000,
                'end' => (strtotime(date("Y-m-d", time())) + 24 * 60 * 60) * 1000,
                'val' => -90,
                'label' => '最近90日'
            ),
            -365 => array(
                'start' => strtotime(date("Y-m-d", strtotime("-365 day"))) * 1000,
                'end' => (strtotime(date("Y-m-d", time())) + 24 * 60 * 60) * 1000,
                'val' => -365,
                'label' => '最近365日'
            ),
        );
        $shapedataarr = array(
            1 => array(
                'start' => 'round((4 / 3) * 100)',
                'end' => '',
                'val' => 1,
                'lablename' => '4:3'
            ),
            2 => array(
                'start' => 'round((3 / 4) * 100)',
                'end' => '',
                'val' => 2,
                'lablename' => '3:4'
            ),
            3 => array(
                'start' => 'round((16 / 9) * 100)',
                'end' => '',
                'val' => 3,
                'lablename' => '16:9'
            ),
            4 => array(
                'start' => 'round((9 / 16) * 100)',
                'end' => '',
                'val' => 4,
                'lablename' => '9:16'
            ),
            5 => array(
                'start' => 250,
                'end' => 0,
                'val' => 5,
                'lablename' => '细长横图'
            ),
            6 => array(
                'start' => 0,
                'end' => 40,
                'val' => 6,
                'lablename' => '细长竖图'
            ),
            7 => array(
                'start' => 100,
                'end' => '',
                'val' => 7,
                'lablename' => '方图'
            ),
            8 => array(
                'start' => 100,
                'end' => 250,
                'val' => 8,
                'lablename' => '横图'
            ),
            9 => array(
                'start' => 40,
                'end' => 100,
                'val' => 9,
                'lablename' => '竖图'
            )
        
        );
        //标签统计
        if ($skey == 'tag') {
            $sql .= ' left join %t rt on rt.rid=r.rid';
            $params[] = 'pichome_resourcestag';
            if (!empty($para)) $params = array_merge($params, $para);
            //每个标签对应文件个数
            $tdata = [];
            //所有符合条件标签id
            $tids = [];
            foreach (DB::fetch_all("select rt.tid,r.rid from $sql   where $wheresql", $params) as $v) {
                if (!isset($tdata[$v['tid']])) $tdata[$v['tid']]['num'] = 1;
                else $tdata[$v['tid']]['num'] += 1;
                if ($v['tid']) $tids[] = $v['tid'];
            }
            //统计所有标签，去掉重复标签
            $tids = array_unique($tids);
            //标签id对应标签名称数组
            $tagdata = [];
            foreach (DB::fetch_all("select tagname,tid from %t where tid in(%n)", array('pichome_tag', $tids)) as $v) {
                $tagdata[$v['tid']] = $v['tagname'];
            }
            //最后返回数组
            $data = [];
            //含分类标签数据数组
            $catdata = [];
            //如果有appid则获取标签分类数据
            if ($appid) {
                //有分类的标签id
                $hascattids = [];
                foreach (DB::fetch_all("select  tr.cid,tr.tid,g.catname from %t  tr
                left join %t  g on g.cid=tr.cid where tid in(%n)", array('pichome_tagrelation', 'pichome_taggroup', $tids)) as $v) {
                    $hascattids[] = $v['tid'];
                    if (!isset($catdata[$v['cid']])) {
                        $catdata[$v['cid']]['catname'] = $v['catname'];
                        $catdata[$v['cid']]['tdatas'][$v['tid']] = ['tagname' => $tagdata[$v['tid']], 'num' => $tdata[$v['tid']]['num'], 'tid' => intval($v['tid'])];
                        $catdata[$v['cid']]['num'] = $tdata[$v['tid']]['num'];
                    } else {
                        $catdata[$v['cid']]['tdatas'][$v['tid']] = ['tagname' => $tagdata[$v['tid']], 'num' => $tdata[$v['tid']]['num'], 'tid' => intval($v['tid'])];
                        $catdata[$v['cid']]['num'] += $tdata[$v['tid']]['num'];
                    }
                }
                //没有分类的标签id
                $nocattids = array_diff($tids, $hascattids);
                //未分类数据
                if (!empty($nocattids)) {
                    $catdata['nottag']['catname'] = '未分类';
                    foreach ($nocattids as $v) {
                        $catdata['nottag']['tdatas'][$v] = ['tagname' => $tagdata[$v], 'num' => $tdata[$v]['num'], 'tid' => intval($v)];
                        $catdata['nottag']['num'] += $tdata[$v]['num'];
                    }
                }
            }
            //分类标签数据
            $data['catdata'] = $catdata;
            //标签不含分类数据
            $alltagdata = [];
            foreach ($tdata as $tid => $num) {
                if (isset($tagdata[$tid])) $alltagdata[$tid] = array('tid' => intval($tid), 'tagname' => $tagdata[$tid], 'num' => $num['num']);
                else $alltagdata[-1] = array('tid' => -1, 'tagname' => '未打标签', 'num' => $num['num']);
            }
            $data['alltagdata'] = $alltagdata;
        } elseif ($skey == 'shape') {
            //形状统计
            $presql = ' case ';
            $prepara = [];
            foreach ($shapedataarr as $sv) {
                if ($sv['start'] && $sv['end'] === '') {
                    $presql .= ' when round((r.width/r.height) * 100) = %i  then %d ';
                    $prepara[] = $sv['start'];
                } else {
                    $presql .= ' when round((r.width/r.height) * 100) > %d ' . (($sv['end']) ? ' and    round((r.width/r.height)*100) <= %d then %d' : ' then %d');
                    $prepara[] = $sv['start'];
                }
                
                if ($sv['end']) $prepara[] = $sv['end'];
                $prepara[] = $sv['val'];
            }
            if ($presql) {
                $presql .= ' end as %s';
                $prepara[] = 'shapedata';
            }
            
            if (!empty($para)) $params = array_merge($params, $para);
            if (!empty($prepara)) $shapeparams = array_merge($prepara, $params);
            //echo "select  $presql FROM $sql where $wheresql";
            // print_r($shapeparams);
            //print_r(DB::fetch_all("select  r.rid,$presql FROM $sql where $wheresql", $shapeparams));
            // die;
            foreach (DB::fetch_all("select  $presql FROM $sql where $wheresql", $shapeparams) as $value) {
                if (!isset($data[$value['shapedata']]) && $shapedataarr[$value['shapedata']]['val']) {
                    $data[$value['shapedata']]['num'] = 1;
                    $data[$value['shapedata']]['lablename'] = $shapedataarr[$value['shapedata']]['lablename'];
                    $data[$value['shapedata']]['val'] = $shapedataarr[$value['shapedata']]['val'];
                } elseif($data[$value['shapedata']]['num']){
                    $data[$value['shapedata']]['num']++;
                }
            }
            //将3:4 9:16 细长竖图归类到竖图
            $data[9]['num'] = ($data[9]['num'] ? $data[9]['num']:0) + ($data[2]['num'] ? $data[2]['num'] : 0) + ($data[4]['num'] ? $data[4]['num'] : 0) + ($data[6]['num'] ? $data[6]['num'] : 0);
        
            if($data[9]['num']) {
                $data[9]['lablename'] = $shapedataarr[9]['lablename'];
                $data[9]['val'] = $shapedataarr[9]['val'];
            }else{
                unset($data[9]);
            }
            //将4:3 16:9 细长横图图归类到横图
                $data[8]['num'] = ($data[8]['num'] ?  $data[8]['num']:0) + ($data[1]['num'] ? $data[1]['num'] : 0) + ($data[3]['num'] ? $data[3]['num'] : 0) + ($data[5]['num'] ? $data[5]['num'] : 0);
            if ($data[8]['num']){
                $data[8]['val'] = $shapedataarr[8]['val'];
                $data[8]['lablename'] = $shapedataarr[8]['lablename'];
            }
            else{
                unset($data[8]);
            }
        } elseif ($skey == 'grade') {
            //评分统计
            if (!empty($para)) $params = array_merge($params, $para);
            $data = DB::fetch_all("select count(r.rid) as num,r.grade  from $sql   where $wheresql group by r.grade", $params);
        } elseif ($skey == 'ext') {
            //类型统计
            if (!empty($para)) $params = array_merge($params, $para);
            $data = DB::fetch_all("select count(r.rid) as num,r.ext from $sql   where $wheresql group by r.ext", $params);
            
        } elseif ($skey == 'btime') {
            //添加时间
            $presql = ' case ';
            $prepara = [];
            foreach ($timedataarr as $sv) {
                $presql .= ' when r.btime >= %d ' . (($sv['end']) ? ' and  r.btime < %d then %d' : ' then %d');
                $prepara[] = $sv['start'];
                if ($sv['end']) $prepara[] = $sv['end'];
                $prepara[] = $sv['val'];
            }
            if ($presql) {
                $presql .= ' end as %s';
                $prepara[] = 'btimedata';
            }
            if (!empty($para)) $params = array_merge($params, $para);
            if (!empty($prepara)) $params = array_merge($prepara, $params);
            foreach (DB::fetch_all("select  $presql  FROM $sql  where$wheresql", $params) as $value) {
                if (!$value['btimedata']) continue;
                if (!isset($data[$value['btimedata']])) {
                    $data[$value['btimedata']]['num'] = 1;
                    $data[$value['btimedata']]['val'] = $timedataarr[$value['btimedata']]['val'];
                    $data[$value['btimedata']]['label'] = $timedataarr[$value['btimedata']]['label'];
                } else {
                    $data[$value['btimedata']]['num']++;
                }
            }
            //将今天昨天归类到最近七天，将最近七天归到最近30天，将近30天归到最近90天，将最近90天归到最近365天
            //将今天昨天归类到最近七天，将最近七天归到最近30天，将近30天归到最近90天，将最近90天归到最近365天
            $data[-7]['num'] =(isset($data[-7]['num']) ? intval($data[-7]['num']):0) + (isset($data[1]['num']) ? intval($data[1]['num']):0) + (isset($data[-1]['num']) ? intval($data[-1]['num']):0);
            if($data[-7]['num']) $data[-7] = array('num'=>$data[-7]['num'],'val'=>$timedataarr[-7]['val'],'label'=>$timedataarr[-7]['label']);
            $data[-30]['num'] =(isset($data[-30]['num']) ? intval($data[-30]['num']):0) + (isset($data[-7]['num']) ? intval($data[-7]['num']):0);
            if($data[-30]['num']) $data[-30] =  array('num'=>$data[-30]['num'],'val'=>$timedataarr[-30]['val'],'label'=>$timedataarr[-30]['label']);
            $data[-90]['num'] =(isset($data[-90]['num']) ? intval($data[-90]['num']):0) + (isset($data[-30]['num']) ? intval($data[-30]['num']):0);
            if($data[-90]['num']) $data[-90] = array('num'=>$data[-90]['num'],'val'=>$timedataarr[-90]['val'],'label'=>$timedataarr[-90]['label']);
            $data[-365]['num'] =(isset($data[-365]['num']) ? intval($data[-365]['num']):0) + (isset($data[-90]['num']) ? intval($data[-90]['num']):0);
            if($data[-365]['num']) $data[-365] =  array('num'=>$data[-365]['num'],'val'=>$timedataarr[-365]['val'],'label'=>$timedataarr[-365]['label']);
            foreach($data as $k=>$v){
                if($v['num'] == 0) unset($data[$k]);
            }
            krsort($data);
        } elseif ($skey == 'mtime') {
            //创建时间
            $presql = ' case ';
            $prepara = [];
            foreach ($timedataarr as $sv) {
                $presql .= ' when r.mtime >= %d ' . (($sv['end']) ? ' and  r.mtime < %d then %d' : ' then %d');
                $prepara[] = $sv['start'];
                if ($sv['end']) $prepara[] = $sv['end'];
                $prepara[] = $sv['val'];
            }
            if ($presql) {
                $presql .= ' end as %s';
                $prepara[] = 'mtimedata';
            }
            if (!empty($para)) $params = array_merge($params, $para);
            if (!empty($prepara)) $params = array_merge($prepara, $params);
            foreach (DB::fetch_all("select  $presql FROM $sql  where $wheresql", $params) as $value) {
                if (!$value['mtimedata']) continue;
                if (!isset($data[$value['mtimedata']])) {
                    $data[$value['mtimedata']]['num'] = 1;
                    $data[$value['mtimedata']]['val'] = $timedataarr[$value['mtimedata']]['val'];
                    $data[$value['mtimedata']]['label'] = $timedataarr[$value['mtimedata']]['label'];
                } else {
                    $data[$value['mtimedata']]['num']++;
                }
            }
            //将今天昨天归类到最近七天，将最近七天归到最近30天，将近30天归到最近90天，将最近90天归到最近365天
            $data[-7]['num'] =(isset($data[-7]['num']) ? intval($data[-7]['num']):0) + (isset($data[1]['num']) ? intval($data[1]['num']):0) + (isset($data[-1]['num']) ? intval($data[-1]['num']):0);
            if($data[-7]['num']) $data[-7] = array('num'=>$data[-7]['num'],'val'=>$timedataarr[-7]['val'],'label'=>$timedataarr[-7]['label']);
            $data[-30]['num'] =(isset($data[-30]['num']) ? intval($data[-30]['num']):0) + (isset($data[-7]['num']) ? intval($data[-7]['num']):0);
            if($data[-30]['num']) $data[-30] =  array('num'=>$data[-30]['num'],'val'=>$timedataarr[-30]['val'],'label'=>$timedataarr[-30]['label']);
            $data[-90]['num'] =(isset($data[-90]['num']) ? intval($data[-90]['num']):0) + (isset($data[-30]['num']) ? intval($data[-30]['num']):0);
            if($data[-90]['num']) $data[-90] = array('num'=>$data[-90]['num'],'val'=>$timedataarr[-90]['val'],'label'=>$timedataarr[-90]['label']);
            $data[-365]['num'] =(isset($data[-365]['num']) ? intval($data[-365]['num']):0) + (isset($data[-90]['num']) ? intval($data[-90]['num']):0);
            if($data[-365]['num']) $data[-365] =  array('num'=>$data[-365]['num'],'val'=>$timedataarr[-365]['val'],'label'=>$timedataarr[-365]['label']);
            foreach($data as $k=>$v){
                if($v['num'] == 0) unset($data[$k]);
            }
            krsort($data);
        } elseif ($skey == 'dateline') {
            //修改时间
            $presql = ' case ';
            $prepara = [];
            foreach ($timedataarr as $sv) {
                $presql .= ' when r.dateline >= %d ' . (($sv['end']) ? ' and  r.dateline < %d then %d' : ' then %d');
                $prepara[] = $sv['start'];
                if ($sv['end']) $prepara[] = $sv['end'];
                $prepara[] = $sv['val'];
            }
            if ($presql) {
                $presql .= ' end as %s';
                $prepara[] = 'datelinedata';
            }
            if (!empty($para)) $params = array_merge($params, $para);
            if (!empty($prepara)) $params = array_merge($prepara, $params);
            foreach (DB::fetch_all("select  $presql FROM $sql  where$wheresql ", $params) as $value) {
                if (!$value['datelinedata']) continue;
                if (!isset($data[$value['datelinedata']])) {
                    $data[$value['datelinedata']]['num'] = 1;
                    $data[$value['datelinedata']]['val'] = $timedataarr[$value['datelinedata']]['val'];
                    $data[$value['datelinedata']]['label'] = $timedataarr[$value['datelinedata']]['label'];
                } else {
                    $data[$value['datelinedata']]['num']++;
                }
            }
            //将今天昨天归类到最近七天，将最近七天归到最近30天，将近30天归到最近90天，将最近90天归到最近365天
            //将今天昨天归类到最近七天，将最近七天归到最近30天，将近30天归到最近90天，将最近90天归到最近365天
            $data[-7]['num'] =(isset($data[-7]['num']) ? intval($data[-7]['num']):0) + (isset($data[1]['num']) ? intval($data[1]['num']):0) + (isset($data[-1]['num']) ? intval($data[-1]['num']):0);
            if($data[-7]['num']) $data[-7] = array('num'=>$data[-7]['num'],'val'=>$timedataarr[-7]['val'],'label'=>$timedataarr[-7]['label']);
            $data[-30]['num'] =(isset($data[-30]['num']) ? intval($data[-30]['num']):0) + (isset($data[-7]['num']) ? intval($data[-7]['num']):0);
            if($data[-30]['num']) $data[-30] =  array('num'=>$data[-30]['num'],'val'=>$timedataarr[-30]['val'],'label'=>$timedataarr[-30]['label']);
            $data[-90]['num'] =(isset($data[-90]['num']) ? intval($data[-90]['num']):0) + (isset($data[-30]['num']) ? intval($data[-30]['num']):0);
            if($data[-90]['num']) $data[-90] = array('num'=>$data[-90]['num'],'val'=>$timedataarr[-90]['val'],'label'=>$timedataarr[-90]['label']);
            $data[-365]['num'] =(isset($data[-365]['num']) ? intval($data[-365]['num']):0) + (isset($data[-90]['num']) ? intval($data[-90]['num']):0);
            if($data[-365]['num']) $data[-365] =  array('num'=>$data[-365]['num'],'val'=>$timedataarr[-365]['val'],'label'=>$timedataarr[-365]['label']);
            foreach($data as $k=>$v){
                if($v['num'] == 0) unset($data[$k]);
            }
            krsort($data);
        } elseif ($skey == 'grouptag') {
            //标签分类id
            $cid = isset($_GET['cid']) ? trim($_GET['cid']) : '';
            $sql .= ' left join %t rt on rt.rid=r.rid left join %t tr on tr.tid=rt.tid ';
            $params[] = 'pichome_resourcestag';
            $params[] = 'pichome_tagrelation';
            $wheresql .= ' and tr.cid = %s';
            $para[] = $cid;
            if (!empty($para)) $params = array_merge($params, $para);
            //每个标签对应文件个数
            $tdata = [];
            //所有符合条件标签id
            $tids = [];
            foreach (DB::fetch_all("select rt.tid,r.rid from $sql   where $wheresql", $params) as $v) {
                if (!isset($tdata[$v['tid']])) $tdata[$v['tid']]['num'] = 1;
                else $tdata[$v['tid']]['num'] += 1;
                if ($v['tid']) $tids[] = $v['tid'];
            }
            //统计所有标签，去掉重复标签
            $tids = array_unique($tids);
            //标签id对应标签名称数组
            $tagdata = [];
            foreach (DB::fetch_all("select tagname,tid from %t where tid in(%n)", array('pichome_tag', $tids)) as $v) {
                $tagdata[$v['tid']] = $v['tagname'];
            }
            //最后返回数组
            $data = [];
            foreach ($tdata as $tid => $num) {
                if (isset($tagdata[$tid])) $data[$tid] = array('tid' => intval($tid), 'tagname' => $tagdata[$tid], 'num' => $num['num']);
            }
        }
        exit(json_encode($data));
    } elseif ($operation == 'search_menu') {
        $skey = isset($_GET['skey']) ? trim($_GET['skey']) : '';
        $data = array();
        exit(json_decode($data));
    } elseif ($operation == 'getsearchmenudata') {//获取筛选项
    
    } elseif ($operation == 'setshow') {//设置显示字段
        $showfileds = isset($_GET['showfileds']) ? trim($_GET['showfileds']) : '';
        $other = isset($_GET['other']) ? trim($_GET['other']) : '';
        // $allowsetarr = ['name', 'tagging', 'extension', 'other', 'size', 'filesize', 'grade', 'btime', 'dateline', 'mtime'];
        // $showfiledarr = explode(',', $showfileds);
        // foreach ($showfiledarr as $k => $v) {
        //     if (!in_array($v, $allowsetarr)) {
        //         unsert($showfiledarr[$k]);
        //     }
        // }
        // $showfileds = implode(',', $showfileds);
        $uid = getglobal('uid');
        if (!$uid) exit(json_encode(array('error' => true)));
        C::t('user_setting')->update_by_skey('pichomeshowfileds', serialize(array('filed' => $showfileds, 'other' => $other)), $uid);
        exit(json_encode(array('success' => true)));
    } elseif ($operation == 'setsort') {//设置排序方式
        $sortfiled = isset($_GET['sortfiled']) ? trim($_GET['sortfiled']) : '';
        $allowsortarr = ['name', 'size', 'whsize', 'ext', 'size', 'grade', 'filesize', 'mtime', 'dateline', 'btime', 'duration'];
        if (!in_array($sortfiled, $allowsortarr)) exit(json_encode(array('error' => true)));
        $sort = isset($_GET['sort']) ? trim($_GET['sort']) : '';
        $uid = getglobal('uid');
        if (!$uid) exit(json_encode(array('error' => true)));
        
        C::t('user_setting')->update_by_skey('pichomesortfileds', serialize(array('filed' => $sortfiled, 'sort' => $sort)), $uid);
        exit(json_encode(array('success' => true)));
        
    } elseif ($operation == 'setlayout') {//设置布局方式
        $layout = isset($_GET['layout']) ? trim($_GET['layout']) : '';
        $uid = getglobal('uid');
        if (!$uid) exit(json_encode(array('error' => true)));
        C::t('user_setting')->update_by_skey('pichomelayout', serialize(array('layout' => $layout)), $uid);
        exit(json_encode(array('success' => true)));
    } elseif ($operation == 'likewords') {//联想词
        $keyword = isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : '';
        //取出name,desc,tagname,comments和最近搜索中包含关键词的内容各10条
        $resourceslikeword = C::t('pichome_resources')->fetch_like_words($keyword);
        $resourcesattrlikeword = C::t('pichome_resources_attr')->fetch_like_words($keyword);
        $taglikeword = C::t('pichome_tag')->fetch_like_words($keyword);
        $commentlikeword = C::t('pichome_comments')->fetch_like_words($keyword);
        $recentlikekeyword = C::t('pichome_searchrecent')->fetch_like_words($keyword);
        //获得所有包含关键词的数组
        $likekeywords = array_merge($resourceslikeword, $taglikeword, $commentlikeword, $resourcesattrlikeword, $recentlikekeyword);
        $likekeyworddata = [];
        //按关键词出现的位置得到二维数组
        foreach ($likekeywords as $v) {
            $index = strpos($keyword, $v);
            $likekeyworddata[$index][] = $v;
        }
        //以出现的位置排序
        ksort($likekeyworddata);
        $returnlikekeywords = [];
        //得到关键词排序后的数组
        foreach ($likekeyworddata as $v) {
            $returnlikekeywords = array_merge($returnlikekeywords, $v);
        }
        //去除重复的关键词
        $returnlikekeywords = array_unique($returnlikekeywords);
        //取出前10个关键词
        $returnlikekeywords = array_slice($returnlikekeywords, 0, 10);
        
        exit(json_encode(array('likewords' => $returnlikekeywords)));
    } elseif ($operation == 'screensetting') {//筛选设置
        $uid = getglobal('uid');
        if (!$uid) exit(json_encode(array('error' => true)));
        if (!submitcheck('settingsubmit')) {
            $screen = C::t('user_setting')->fetch_by_skey('pichomeuserscreen', $uid);
        } else {
            $screen = isset($_GET['screen']) ? trim($_GET['screen']) : '';
            $uid = getglobal('uid');
            C::t('user_setting')->update_by_skey('pichomeuserscreen', $screen, $uid);
            exit(json_encode(array('success' => true)));
        }
    } elseif ($operation == 'getscreen') {//获取筛选项
        $appid = isset($_GET['appid']) ? trim($_GET['appid']) : '';
        if ($appid) {
            if ($data = DB::fetch_first("select * from %t where appid=%s ", array('pichome_vapp', $appid))) {
                $data['filter'] = unserialize($data['filter']);
                // if($data['filter']){
                    foreach ($data['filter'] as $k => $v) {
                        if ($v['key'] == 'tag' && $v['chacked'] == 1) {
                            if (isset($v['group'])) {
                                foreach ($v['group'] as $key => $tg) {
                                    $tgdata = C::t('pichome_taggroup')->fetch($tg['cid']);
                                    if ($tgdata) {
                                        $tgdata['chacked'] = $tg['chacked'];
                                        $data['filter'][$k]['group'][$key] = $tgdata;
                                    }else{
                                        unset($data['filter'][$k]['group'][$key]);
                                    }
                                }
                            }
                        }
                    }
                    $pichomefilterfileds = $data['filter'];
                    exit(json_encode(array('success' => true, 'data' => $pichomefilterfileds)));
                // }else{
                //     if (isset($setting['pichomefilterfileds'])) {
                //         exit(json_encode(array('success' => true, 'data' => $setting['pichomefilterfileds'])));
                //     } else {
                //         $setting = C::t('setting')->fetch_all('pichomefilterfileds');
                //         exit(json_encode(array('success' => true, 'data' =>$setting['pichomefilterfileds'])));
                //     }
                // }
                
            } else {
                exit(json_encode(array('error' => true)));
            }
        } else {
            if (isset($_G['setting']['pichomefilterfileds'])) {
                exit(json_encode(array('success' => true, 'data' => $_G['setting']['pichomefilterfileds'])));
            } else {
                $setting = C::t('setting')->fetch_all('pichomefilterfileds');
                exit(json_encode(array('success' => true, 'data' =>$setting['pichomefilterfileds'])));
            }
        }
        
    } elseif ($operation == 'createshare') {//分享
        $rid = isset($_GET['rid']) ? trim($_GET['rid']) : '';
        if ($shareurl = C::t('pichome_share')->add_share($rid)) {
            exit(json_encode(array('success' => $shareurl)));
        } else {
            exit(json_encode(array('error' => true)));
        }
    } elseif ($operation == 'getapp') {
        $apps = [];
        foreach (DB::fetch_all("select * from %t where 1", array('pichome_vapp')) as $v) {
            $apps[] = $v;
        }
        exit(json_encode(array('vapps' => $apps)));
    } elseif ($operation == 'reloadresources') {//更新单个文件资源
        $rid = isset($_GET['rid']) ? trim($_GET['rid']) : '';
        if (!$rid) exit(json_encode(array('error' => 'empty resources')));
        include_once dzz_libfile('eagleexport');
        $eagleexport = new eagleexport();
        if ($eagleexport->exportAloneFile($rid)) {
            exit(json_encode(array('success' => true)));
        }
        exit(json_encode(array('success' => true)));
    } elseif ($operation == 'sharedetails') {//分享详情数据
        $id = isset($_GET['id']) ? trim($_GET['id']) : '';
        $shareid = dzzdecode($id, '', 0);
        $sharedata = C::t('pichome_share')->fetch($shareid);
        if (!$sharedata) exit(json_encode(array('error' => 'share is not exists')));
        $resourcesdata = C::t('pichome_resources')->fetch_by_rid($sharedata['filepath']);
        exit(json_encode(array('data' => $resourcesdata)));
    } elseif ($operation == 'getapptagcat') {
        $appid = isset($_GET['appid']) ? trim($_GET['appid']) : '';
        $catdata = C::t('pichome_taggroup')->fetch_by_appid($appid);
        exit(json_encode($catdata));
    } elseif ($operation == 'appset') {
        Hook::listen('adminlogin');//检查是否登录，未登录跳转到登录界面
        $appid = isset($_GET['appid']) ? trim($_GET['appid']) : '';
        if (!$appid) exit(json_encode(array('error' => true)));
        $setarr = [
            'filter' => isset($_GET['filter']) ? serialize($_GET['filter']) : '',
            'share' => isset($_GET['share']) ? intval($_GET['share']) : 0,
            'download' => isset($_GET['download']) ? intval($_GET['download']) : 0,
        ];
        C::t('pichome_vapp')->update($appid, $setarr);
        exit(json_encode(array('success' => true)));
    }