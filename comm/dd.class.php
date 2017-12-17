<?php
class duoduo {

	public $link;
	public $webset;
	
	public $dbserver;
	public $dbuser;
	public $dbpass;
	public $dbname;
	public $BIAOTOU;
	public $sql_log=0;
	public $lastsql;
	public $trade_id_uid_arr=array();  //存储trade_id和uid的关系，以便后续订单直接比对
	public $trade_temp=array();        //存储没有uid的临时订单，回滚数据用
	public $user_temp=array();         //会员临时数据，定位会员是求得的会员信息放在这里，以便后续代码直接使用
	public $sql_row_count=0;
	public $row_count=0;
	public $sql_cache_table_arr=array('mall');//定义设置缓存的表
	public $sql_cache_time='86400';//定义默认缓存时间 
	public $sql_cache_status=1;
	public $query;
	public $zhiling_config;
	
	function __destruct(){
		if(!defined('UC_CONNECT')){
			$this->close();
		}
	}
	
	function connect(){
		$this->link = mysql_connect($this->dbserver, $this->dbuser, $this->dbpass);
	    if($this->link==''){
			PutInfo("数据库连接失败！<br/>");
		}
		$query=$this->select_db($this->dbname);
		if($query!=1){
			$sql = "CREATE DATABASE IF NOT EXISTS `".$this->dbname."` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci";
			$query=$this->query($sql);
			if($query==''){
				PutInfo('没有创建数据库的权限！');
			}
			$this->select_db($this->dbname);
		}
		$this->query("set names utf8");
		return $this->link;
	}
	
	function select_db($dbname){
	    return mysql_select_db($dbname,$this->link);
	}
	
	function ping(){
	    if(mysql_ping()==''){
		    $this->close();
			$this->connect();
		}
	}
	
	function close(){
	    mysql_close($this->link);
	}

	function query($sql,$alert=0) {
		$this->lastsql = $sql;
		if($alert==1){
			echo $sql;
		}
		if($this->webset['sql_log']==1 || $this->sql_log==1){
			$str=$sql.'-----'.get_client_ip().'-----'.date('H:i:s')."\r\n";
			$filename=DDROOT.'/data/temp/sql/'.date('Ym').'/'.date('d').'_'.md5(DDKEY).'.log';
			create_file($filename,$str,1,1);
		}
		if(defined('ADMIN')){
			$str=get_client_ip().'-----'.$sql.'-----'.date('H:i:s')."\r\n";
			$filename=DDROOT.'/data/temp/admin_sql/'.date('Ym').'/'.date('d').'_'.md5(DDKEY).'.log';
			create_file($filename,$str,1,1);
		}
		$this->query=$query=mysql_query($sql,$this->link);
		if($query==''){//echo $this->error().'<br/>'.$sql;exit;
			if($this->webset['sql_debug']==1){
				$filename=DDROOT.'/data/temp/error_sql/'.date('Ym').'/'.date('d').'_'.md5(DDKEY).'.log';
				create_file($filename,$this->error().'---'.$sql."\r\n",1,1);
				//echo $this->error().'<br/>'.$sql;exit;
			}
		}
		else{
			if($this->sql_row_count==1){
				$this->sql_row_count=0;
				$sql="select row_count() as c";
				$a=$this->select2arr($sql);
				$this->row_count=$a[0]['c'];
			}
			return $query;
		}
	}

	function fetch_array($query, $result_type = MYSQL_ASSOC) {
		return mysql_fetch_array($query, $result_type);
	}
	
	function num_rows($query){
	    return mysql_num_rows($query);
	}
	
	function insert_id(){
	    return mysql_insert_id($this->link);
	}
	
	function free_result(&$query){
		$re=mysql_free_result($query);
		/*if($re!=1){
		    print_r(debug_backtrace());
			exit;
		}*/
	}

	function error() {
		return mysql_error();
	}
	
	function get_version($isformat = true) {
	    $rs = $this->query("SELECT VERSION();");
	    $row = $this->fetch_array($rs,MYSQL_NUM);
	    $mysql_version = $row[0];
	    $this->free_result($rs);
	    if ($isformat) {
		    $mysql_versions = explode(".", trim($mysql_version));
		    $mysql_version = number_format($mysql_versions[0] . "." . $mysql_versions[1], 2);
	    }
	    return $mysql_version;
    }
	
	function show_index($table){
		$query = $this->query("SHOW INDEX FROM ".BIAOTOU.$table);
		if($query!=''){
			while ($arr = $this->fetch_array($query)) {
				if($arr['Key_name']=='PRIMARY'){
					$index_info[] = $arr['Column_name'];
				}
				else{
					$index_info[] = $arr['Key_name'];
				}
			}
		}
		return $index_info;
	}
	
	public function con($a) {
		if(!is_array($a)) return $a;
		$where = '';
		$temp=array();
		$i = 0;
		if(!is_array($a[0])){
		    foreach($a as $k=>$v){
    	        $temp[]=$k."='".$this->real_escape_string($v)."'";
            }
			$where='where '.implode(' and ',$temp);
		}
		else{
		    foreach ($a as $row) {
			    if ($i == 0) {
				    $where .= ' where ';
			    } else {
				    if ($row['c'] == '') {
					    $where .= ' and ';
				    } else {
					    $where .= ' ' . $row['c'] . ' ';
				    }

			    }
			    if ($row['e'] == '') {
				    $row['e'] = '=';
			    }
			    $where .= $row['f'] . ' ' . $row['e'] . ' "' . $this->real_escape_string($row['v']) . '"';
			    $i++;
		    }
		}
		return $where;
	}
	
	function get_query_conditions($query_item){
        foreach($query_item as $row){
			if($row['f']!=''){
			    if($row['v']!=='' && $row['v']!=='%%'){
					if(isset($row['equal']) && $row['equal']=='in'){
					    $str1[] = $row['f']." ".$row['e']." ".$row['v'];
					}
					else{
					    $str1[] = $row['f']." ".$row['e']." '".$row['v']."'";
					}
			    }
			}
			else{
			    foreach($row as $arr){
				    if($arr['v']!=='' && $arr['v']!=='%%'){
						if($arr['e']=='in'){
						    $str2[] = $arr['f']." ".$arr['e']." ".$arr['v'];
						}
						else{
						    $str2[] = $arr['f']." ".$arr['e']." '".$arr['v']."'";
						}
			        }
				}
				if($str2[1]!=''){
					$str3='('.implode(' or ',$str2).')';
				}
				elseif($str2[1]=='' && $str2[0]!=''){
				    $str3=$str2[0];
				}
				else{
				    $str3='';
				}
			}
			
        }
		
		if(isset($str3) && $str3!='') $str1[]=$str3;
		if($str1[1]!=''){
			return implode(' and ',$str1);
		}
		elseif($str1[1]=='' && $str1[0]!=''){
			return $str1[0];
		}
		else{
			 return '1=1';
		}
    }
	
	function sql_to_array($sql){
		$arr=array();
		$query=$this->query($sql);
		while ($row = $this->fetch_array($query)) {
			$arr[]=$row;
		}
		//if(!isset($arr[1])){$arr=$arr[0];}
		$this->free_result($query);
		return $arr;
	}
	
	function select2arr($sql,$x=0) {
		$query = $this->query($sql);
		while ($row = $this->fetch_array($query)) {
			$arr[] = $row;
		}
		if($x==0){
		    $re = $arr;
		}
		else{
		    $re = $arr[0];
		}
		$this->free_result($query);
		if(!is_array($re)){$re=array();}
		return $re;
	}
	
	function table_cache($table){
		$table_cache=0;
		if($this->sql_cache_status==1){
			foreach($this->sql_cache_table_arr as $v){
				if(($table==$v || strpos($table, $v.' ')) && INDEX==1){
					$table_cache=1;
					break;
				}
			}
		}
		return $table_cache;
	}
	
	function table_cache_dir($table){
		return implode('_',$table);
	}

	function select($table, $sel_field, $where='1', $alert = 0) {
		$arr='';
		$t=array();
		if(strstr($table,',')!=''){
			$a=explode(',',$table);
			foreach($a as $v){
				$b=explode(' ',$v);
				$t[]=$b[0];
			}
		    $table=str_replace(',',','.$this->BIAOTOU,$table);
		}
		$sql = "select $sel_field from " . $this->BIAOTOU . $table . " where $where limit 1";
		if ($alert == 1) {
			echo $sql;
		}
		
		if(empty($t)){
			$table_cache=$this->table_cache($table);
			$t=array($table);
		}
		else{
			foreach($t as $v){
				if($this->table_cache($v)==1){
					$table_cache=1;
					break;
				}
			}
		}
		if($table_cache==1){
			$query=get_ddcache($sql,'sql/'.$this->table_cache_dir($t),$this->sql_cache_time);
			if($query!=false){
				return $query;
			}
		}
		if($query!=false){
			return $query;
		}
		$query = $this->query($sql);
		if ($query!='') {
			while ($row = $this->fetch_array($query)) {
				if(strpos($sel_field, ",")!==false or strpos($sel_field, "*")!==false) {
					$arr = $row;
				} 
				else{
					$arr = array_pop($row);
				}
			}
			$this->free_result($query);
		}
		if($table_cache==1){
			set_ddcache($sql,$arr,'sql/'.$this->table_cache_dir($t));
		}
		return $arr;
	}
	
	function select_limit($table, $sel_field, $where='1',$limit='1', $alert = 0) {
		$arr='';

		if(strstr($table,',')!=''){
		    $table=str_replace(',',','.$this->BIAOTOU,$table);
		}
		$sql = "select $sel_field from " . $this->BIAOTOU . $table . " where $where limit ".$limit;
		if ($alert == 1) {
			echo $sql;
		}
		$query = $this->query($sql);
		if ($query!='') {
			while ($row = $this->fetch_array($query)) {
				if(strpos($sel_field, ",")!==false or strpos($sel_field, "*")!==false) {
					$arr = $row;
				} 
				else{
					$arr = array_pop($row);
				}
			}
			$this->free_result($query);
		}
		return $arr;
	}
	
	function select_all($table, $sel_field, $where='1=1', $alert = 0) {
		$arr=array();
		$t=array();
		if(strstr($table,',')!=''){
			$a=explode(',',$table);
			foreach($a as $v){
				$b=explode(' ',$v);
				$t[]=$b[0];
			}
		    $table=str_replace(',',','.$this->BIAOTOU,$table);
		}
		$sql = "select $sel_field from ".$this->BIAOTOU.$table." where $where ";
		if ($alert == 1) {
			echo $sql;
		}
		if(empty($t)){
			$table_cache=$this->table_cache($table);
			$t=array($table);
		}
		else{
			foreach($t as $v){
				if($this->table_cache($v)==1){
					$table_cache=1;
					break;
				}
			}
		}
		if($table_cache==1){
			$query=get_ddcache($sql,'sql/'.$this->table_cache_dir($t),$this->sql_cache_time);
			if($query!=false){
				return $query;
			}
		}
		$query = $this->query($sql);
		if ($query!='') {
			while ($row = $this->fetch_array($query)) {
				$arr[] = $row;
			}
			$this->free_result($query);
		}
		if($table_cache==1){
			set_ddcache($sql,$arr,'sql/'.$this->table_cache_dir($t));
		}
		return $arr;
	}
	
	function select_all_key($table, $sel_field, $where='1=1', $key='id', $alert = 0) {
		$arr = array ();
		if(strstr($table,',')!=''){
		    $table=str_replace(',',','.$this->BIAOTOU,$table);
		}
		$sql = "select $sel_field from ".$this->BIAOTOU.$table." where $where ";
		if ($alert == 1) {
			echo $sql;
		}
		$query = $this->query($sql);
		if ($query!='') {
			while ($row = $this->fetch_array($query)) {
				$arr[$row[$key]] = $row;
			}
			$this->free_result($query);
		}
		return $arr;
	}
	
	function select_1_field($table, $sel_field='title', $where='1=1', $alert = 0) { //1个字段，输出一维数组
		$arr=array();
		$sql = "select $sel_field from ".$this->BIAOTOU.$table." where $where ";
		if ($alert == 1) {
			echo $sql;
		}
		$query = $this->query($sql);
		if ($query!='') {
			while ($row = $this->fetch_array($query,MYSQL_NUM)) {
				$arr[] = $row[0];
			}
			$this->free_result($query);
		}
		return $arr;
	}
	
	function select_2_field($table, $sel_field='id,title', $where='1=1', $alert = 0) { //2个字段，输出一维数组，第一个字段是键名，第二个字段是键值
		$arr=array();
		$sql = "select $sel_field from ".$this->BIAOTOU.$table." where $where ";
		if ($alert == 1) {
			echo $sql;
		}
		$query = $this->query($sql);
		if ($query!='') {
			while ($row = $this->fetch_array($query,MYSQL_NUM)) {
				$arr[$row[0]] = $row[1];
			}
			$this->free_result($query);
		}
		return $arr;
	}
	
	function select_3_field($table, $sel_field='id,title,content', $where='1=1', $alert = 0) { //3个字段，输出二维数组，第一个字段是键名，第二，三组成数字作为子数组
		$arr=array();
		$field_arr=explode(',',$sel_field);
		$sql = "select $sel_field from ".$this->BIAOTOU.$table." where $where ";
		if ($alert == 1) {
			echo $sql;
		}
		$query = $this->query($sql);
		if ($query!='') {
			while ($row = $this->fetch_array($query)) {
				$arr[$row[$field_arr[0]]] = array($field_arr[1]=>$row[$field_arr[1]],$field_arr[2]=>$row[$field_arr[2]]);
			}
			$this->free_result($query);
		}
		return $arr;
	}
	
    function update($table, $set_con_arr, $where,$limit=1,$alert = 0) {
		$set = '';
	    if (!array_key_exists(0,$set_con_arr)) {
		    $set_arr[0] = $set_con_arr;
	    } else {
		    $set_arr = $set_con_arr;


	    }

	    if(!array_key_exists('f',$set_arr[0])){
            foreach ($set_arr[0] as $k => $v) {
            	$set = "`$k`='".$this->real_escape_string($v)."'," . $set;
            }
			$set = substr($set, 0, strlen($set) - 1);
	    }
        else{
        	foreach ($set_arr as $k => $v) {
		        if (!isset($v['e']) || $v['e'] == '' || $v['e']=='=') {
			        $temp[] = "`" . $v['f'] . "`='" . $this->real_escape_string($v['v']) . "'";
		        } else {
			        $temp[] = "`" . $v['f'] . "`=`" . $v['f'] . "`" . $v['e'] . "'" . $this->real_escape_string($v['v']) . "'";
		        }
	        }
	        $set = implode(',', $temp);
        }
	    if($limit==0){
			$limit='';
		}
		elseif($limit>0){
			$limit=' limit '.$limit;
		}
	    $sql = "update " . $this->BIAOTOU . $table . " set " . $set . " where " . $where . $limit;
		if ($alert == 0) {
			$this->query($sql);
			return $set_arr;
		}
		elseif ($alert == 1) {
			$this->query($sql);
			echo $sql;
		}
	}
	
	/*
	$data=array(
		array(
			'k'=>'id',
			'v'=>'1',
			'data'=>array(
				'a'=>1,
				'b'=>2,
				'c'=>3
			)
		),
		array(
			'k'=>'id',
			'v'=>'2',
			'data'=>array(
				'a'=>11,
				'b'=>22,
				'c'=>33
			)
		),
	);
	*/
	function updates($table,$data){
		$a=array();
		$where='';
		foreach($data as $key=>$row){
			if($key==0){
				$where='`'.$row['k'].'` in ("'.$row['v'].'",';
			}
			else{
				$where.='"'.$row['v'].'",';
			}
			$i=0;
			foreach($row['data'] as $k=>$v){
				if(isset($a[$i])){
					$a[$i]['data'][$row['v']]=$v;
				}
				else{
					$a[$i]=array('k'=>$k,'wk'=>$row['k'],'data'=>array($row['v']=>$v));
				}
				$i++;
			}
		}
		$where=preg_replace('/,$/','',$where);
		$where.=')';
		$sql='UPDATE '.$this->BIAOTOU.$table.' SET ';
		foreach($a as $row){
			$sql.='`'.$row['k'].'` = CASE `'.$row['wk']."`\r\n";
			foreach($row['data'] as $k=>$v){
				$sql.=' WHEN "'.$k.'" THEN "'.$this->real_escape_string($v).'"';
			}
			$sql.=' END,'."\r\n";
		}
		$sql=preg_replace('/,$/','',trim($sql))."\r\nwhere ".$where;
		return $this->query($sql);
	}
	
	function update_serialize($field,$key,$value,$alert=0){
	    $str=$this->select('webset','val','var="'.$field.'"');
		$row=unserialize($str);
		$row[$key]=$value;
		$data=array('val'=>serialize($row));
		$this->update('webset',$data,'var="'.$field.'"');
	}
	
	function real_escape_string($v){
		if(strpos($v,"'")!==false){
			$v=str_replace("'","\'",$v);
		}
		if(strpos($v,'\"')===false && strpos($v,"\'")===false){
			$v=mysql_real_escape_string($v);
		}
		return $v;
	}
	
	function insert($table, $field_arr, $alert = 0) {
		$s='';
		foreach ($field_arr as $k => $v) {
			$v=$this->real_escape_string($v);
			$s.="`" . $k . "`="."'" . $v . "',";
		}
		$s=preg_replace('/,$/','',$s);
		$sql = 'insert into '.$this->BIAOTOU.$table.' set '.$s;
		$query = $this->query($sql);
		if ($query!=''){
			$re = $this->insert_id();
		}
	    else{
			$re = $this->error();
		}
		if ($alert == 1) {
			echo $sql . "<br/>";
		}
		return $re;
	}
	
	function inserts($table,$field_arr){
		$t=array();
		foreach($field_arr[0] as $k=>$v){
			$field[]='`'.$k.'`';
		}
		$value=$field_arr;
		foreach($value as $row){
			foreach($row as $k=>$v){
				$row[$k]="'".$v."'";
			}
			$t[]=implode(',',$row);
		}
		$t='('.implode('),(',$t).')';
		$sql='insert into '.$this->BIAOTOU.$table.' ('.implode(',',$field).') values'.$t;
		return $query = $this->query($sql);
	}
	
	function insert_select($table, $field_arr,$where){
		$ziduan='';
		$zhi='';
		foreach($field_arr as $k=>$v){
			$ziduan.='`'.$k.'`,';
			$zhi.="'".$this->real_escape_string($v)."',";
		}
		$ziduan=preg_replace('/,$/','',$ziduan);
		$zhi=preg_replace('/,$/','',$zhi);
		$sql="INSERT INTO ".$this->BIAOTOU.$table."(".$ziduan.") SELECT ".$zhi." FROM dual WHERE not exists (select id from ".$this->BIAOTOU.$table." where ".$where.")";
		$this->query($sql);
		return (int)$this->insert_id();
	}
	
	function get_table_struct($tablename){
		$table_struct=array();
		$query = $this->query('show fields from `' . $this->BIAOTOU . $tablename.'`');
		if($query==''){
			$basedata=include(DDROOT.'/data/array/basedata.php');
			return $basedata[$tablename];
		}
		while ($arr = $this->fetch_array($query)) {
			$info = $arr['Type'];
			if ($arr['Null'] == 'NO') {
				$info .= ' NOT NULL';
			}
			$type = strtolower(preg_replace('/\((.*)\)/', '', $arr['Type']));
			if ($type == 'int' or $type == 'tinyint' or $type == 'bigint' or $type == 'double') { //数字类型<br>
				if ($arr['Default'] != '') {
					$info .= ' default "' . $arr['Default'] . '"';
				}
			} else {
				if ($arr['Null'] == 'YES') {
					if ($arr['Default'] != '') {
						$info .= ' default "' . $arr['Default'] . '"';
					} else {
						$info .= ' default NULL';
					}
				}
			}

			if ($arr['Extra'] != '') {
				$info .= ' ' . $arr['Extra'];
			}

			$table_struct[$arr['Field']] = $info;

			if ($arr['Key'] != '') {
				if ($arr['Key'] == 'PRI') {
					$duoduo_table_index = 'PRIMARY KEY  (`' . $arr['Field'] . '`)';
				}
				elseif ($arr['Key'] == 'UNI') {
					$duoduo_table_index .= ',UNIQUE KEY `' . $arr['Field'] . '` (`' . $arr['Field'] . '`)';
				}
				elseif($arr['Key'] == 'MUL'){
					$duoduo_table_index .= ',KEY `' . $arr['Field'] . '` (`' . $arr['Field'] . '`)';
				}
			}
		}
		$table_struct['duoduo_table_index'] = $duoduo_table_index;
		return $table_struct;
	}
	
	function admin_log($do){
		$data=array('mod'=>MOD,'admin_name'=>$_SESSION['ddadmin']['name'],'addtime'=>TIME,'ip'=>get_client_ip(),'do'=>$do);
		$this->insert('adminlog',$data);
		if(rand(1,10)==1){  //十分之一的概率删除60天前的日志
			$this->delete('adminlog','addtime<"'.(TIME-3600*24*60).'"');
			
			if(date('d')>=28){
				$a=dd_glob(DDROOT.'/data/temp/admin_sql/');
				foreach($a as $v){
					if($v!=date("Ym")){
						deldir($v);
					}
				}
			}
		}
	}
	
	function mingxi_insert($field_arr, $alert = 0,$tsmsg='') {
		$user=$this->select('user','money,jifenbao,jifen','id="'.$field_arr['uid'].'"');
		$field_arr['leave_money']=$user['money'];
		$field_arr['leave_jifenbao']=$user['jifenbao'];
		$field_arr['leave_jifen']=$user['jifen'];
		if(!array_key_exists('addtime',$field_arr)){
			$field_arr['addtime']=date('Y-m-d H:i:s');
	    }
		return $this->insert('mingxi',$field_arr,$alert);
	}
	
	function msg_insert($data, $msgset_id=0, $msgset = ''){ //带有站内信id，启用站内信模板，否则需要data带有title和content
		$user=$this->select('user','*','id="'.$data['uid'].'"');
		foreach($user as $k=>$v){
			if(!isset($data[$k])){
				$data[$k]=$v;
			}
		}
		
		/*if(!isset($data['email']) || $data['email']==''){
			$data['email']=$user['email'];
		}
		if(!isset($data['mobile']) || $data['mobile']=='' || $data['mobile']==0){
			$data['mobile']=$user['mobile'];
		}*/
		
		$send_web=0;
		$send_email=0;
		$send_sms=0;
        if($msgset_id>0 || !empty($msgset)){
			if($msgset_id>0){
				$m=dd_get_cache('msgset');
				$msgset=$m[$msgset_id];
			}
		    
			$title= $msgset['title'];
			$web_content=$msgset['web'];
			$web_open=$msgset['web_open'];
			$sms_content=$msgset['sms'];
			$sms_open=$msgset['sms_open'];
			
			$email_content=$msgset['email'];
			$email_open=$msgset['email_open'];

			if($web_content!='' && $data['uid']>0 && $web_open==1){
				preg_match_all('/\{(.*?)\}/',$web_content,$arr);
        		foreach($arr[0] as $k=>$v){
	        		$web_content=str_replace($v,$data[$arr[1][$k]],$web_content);
        		}
				$send_web=1;
			}
			
			if($sms_content!='' && $data['mobile']!='' && $sms_open==1 && $this->webset['sms']['open']==1){
				if($msgset_id==2 || $msgset_id==3){ //提现
					$sms_money=(float)$data['money'];
					$sms_content=array('money'=>$sms_money);
				}
				elseif($msgset_id==999 || $msgset_id==998 || $msgset_id==11){ //验证码
					$sms_content=array('yzm'=>$data['yzm']);
				}
				elseif($msgset_id==4){  //兑换成功（如果是优惠券兑换，有兑换码）
					if($data['code']!=''){
						$data['code']=str_replace('认领代码：','',$data['code']);
					}
					$sms_content=array('code'=>$data['code']);
				}
				elseif($msgset_id==5){ //兑换失败
					$sms_content=array();
				}
				elseif($msgset_id==7){ //退款
					$sms_content=array('trade_id'=>$data['trade_id']);
				}
				elseif($msgset_id==9 || $msgset_id==10){ //站长奖励 站长扣除
					$sms_content=array('money'=>$data['money']);
				}
				if(is_array($sms_content)){
					$send_sms=1;
				}
			}
			
			if($email_open==1 && $data['email']!='' && $email_content!=''){
				preg_match_all('/\{(.*?)\}/',$email_content,$arr);
        		foreach($arr[0] as $k=>$v){
	        		$email_content=str_replace($v,$data[$arr[1][$k]],$email_content);
        		}
				$send_email=1;
			}
		}
		else{
		    $title=$data['title'];
			if($data['content']!=''){
				$web_content=$data['content'];
				$send_web=1;
			}
			if($user['mobile']!=''){
				$sms_content=$data['content'];
				$send_sms=0;
			}
			if($data['email']!=''){
				$email_content=$data['content'];
				$send_email=1;
			}
		}
		if($data['email']!=''){
			$r=reg_web_email($data['email']);
			if($r['1']==''){
				$send_email=0;
			}
		}
		$re_status=array(0,0,0);
		
		if($send_web==1){
			$field_arr['addtime']=date('Y-m-d H:i:s');
			$field_arr['see']=0;
			$field_arr['senduser']=0;
			$field_arr['uid']=$data['uid'];
			$field_arr['title']=$title;
			$field_arr['content']=$web_content;
			$id=$this->insert('msg',$field_arr);
			if($id>0){
				$re_status[0]=1;
			}
		}

		if($send_sms==1){
			$ddopen=fs('ddopen');
			$ddopen->sms_ini($this->webset['sms']['pwd']);
			$ddopen->sms_send($data['mobile'],$sms_content,$msgset_id);
			$re_status[1]=1;
		}
		
		if($send_email==1){
			if($this->webset['smtp']['xingshi']==0){
				mail_send($data['email'], $title, $email_content);
			}
			else{
				$a=array('email'=>$data['email'],'title'=>$title,'content'=>$email_content,'type'=>1);
				$this->cron($a,'insert');
			}
			$re_status[2]=1;
			/*$re=mail_send($data['email'], $title, $email_content);
			if($re>0){
				$re_status[2]=1;
			}*/
		}
		
		return $web_content;
	}
	
	function replace($table, $field_arr, $alert = 0) {
		$field = "";
		$values = "";
		foreach ($field_arr as $k => $v) {
			$v=$this->real_escape_string($v);
			$field = "`" . $k . "`," . $field;
			$values = "'" . $v . "'," . $values;
		}
		
		$field = substr($field, 0, strlen($field) - 1);
		$values = substr($values, 0, strlen($values) - 1);
		$sql = "replace into $this->BIAOTOU$table($field) values ($values);";
		$query = $this->query($sql);
		if ($alert == 0) {
			if ($query)
				return mysql_insert_id();
			else
				return $this->error();
		}
		elseif ($alert == 1) {
			echo $sql . "<br/>";
		}
		elseif ($alert == 2) {
			echo $sql;

			if ($query)
				return mysql_insert_id();
			else
				return $this->error();
		}
	}
	
	function left_join($table, $join, $sel_field, $where='1=1', $alert = 0) {
		$arr = array ();
		$sql = "select $sel_field from ".$this->BIAOTOU.$table." LEFT JOIN ".$this->BIAOTOU.$join." where $where ";
		if ($alert == 1) {
			echo $sql;
		}
		$query = $this->query($sql);
		if ($query!='') {
			while ($row = $this->fetch_array($query)) {
				$arr[] = $row;
			}
			$this->free_result($query);
		}
		return $arr;
	}
	
	function left_join_ex($table, $join, $sel_field, $where='1=1', $alert = 0) {
		$arr = array ();
		$sql = "select $sel_field from ".$this->BIAOTOU.$table." LEFT JOIN ".$this->BIAOTOU.$join." on $where ";
		if ($alert == 1) {
			echo $sql;
		}
		$query = $this->query($sql);
		if ($query!='') {
			while ($row = $this->fetch_array($query)) {
				$arr[] = $row;
			}
			$this->free_result($query);
		}
		return $arr;
	}
	
	function sel_one_arr_sql($table, $sel_field, $where, $alert = 0) {
		$arr = array ();
		if(strstr($table,',')!=''){
		    $table=str_replace(',',','.$this->BIAOTOU,$table);
		}
		$sql = "select $sel_field from ".$this->BIAOTOU.$table." where $where ";
		if ($alert == 1) {
			echo $sql;
		}
		$query = $this->query($sql);
		if ($query!='') {
			while ($row = $this->fetch_array($query)) {
				$arr[] = $row[$sel_field];
			}
			$this->free_result($query);
		}
		return $arr;
	}
	
	function sel_page_sql($table, $sel_field, $where, $frmnum ,$pagesize) {
		$arr = array ();
		if($frmnum>=1000){ //自己估算的临界点
		    $sql="select $sel_field From ".$this->BIAOTOU.$table." Where id >=(Select id From ".$this->BIAOTOU.$table." Order By id limit $frmnum,1) and ".$where." limit $pagesize";
		}
		else{
		    $sql = "select $sel_field from ".$this->BIAOTOU.$table." where $where limit $frmnum ,$pagesize";
		}
		$query = $this->query($sql);
		if ($query!='') {
			while ($row = $this->fetch_array($query)) {
				$arr[] = $row;
			}
			$this->free_result($query);
		}
		return $arr;
	}

	function count($table,$where='',$alert=0){
		if($where!=''){$where ='where '.$where;}
		if(strpos($table,',')!==false){
		    $table=str_replace(',',','.$this->BIAOTOU,$table);
		}
	    $sql='select count(1) as num from '.$this->BIAOTOU.$table." ".$where;
		if($alert==1){
		    echo $sql;
		}
	    $query = $this->query($sql);
		if($query){
			$row=$this->fetch_array($query);
			$this->free_result($query);
		}
	    return $row['num']?$row['num']:0;
    }
	
	function count_orther($table,$where='',$alert=0){
		if($where!=''){$where ='where '.$where;}
		if(strpos($table,',')!==false){
		    $table=str_replace(',',',',$table);
		}
	    $sql='select count(1) as num from '.$table." ".$where;
		if($alert==1){
		    echo $sql;
		}
	    $query = $this->query($sql);
	    $row=$this->fetch_array($query);
		$this->free_result($query);
	    return $row['num']?$row['num']:0;
    }
	
	function sum($table,$count_field,$where='1=1',$alert=0){
		if($where!=''){$where ='where '.$where;}
		if(strpos($table,',')!==false){
		    $table=str_replace(',',','.$this->BIAOTOU,$table);
		}
		$select_field='';
		if(strpos($count_field,',')!==false){
			$field_arr=explode(',',$count_field);
			foreach($field_arr as $k=>$v){
				$select_field.='sum(`'.$v.'`) as `'.$v.'`,';
			}
			$select_field=preg_replace('/,$/','',$select_field);
		}
		else{
			$select_field='sum('.$count_field.') as sum';
		}
	    $sql="select ".$select_field." from ".$this->BIAOTOU.$table." ".$where;
		if($alert==1){echo $sql.'<br/>';}
	    $query = $this->query($sql);
		if($query){
			$row=$this->fetch_array($query);
			$this->free_result($query);
			if(count($row)==1){
				return $row['sum']?round($row['sum'],2):0;
			}
			else{
				foreach($row as $k=>$v){
					$row[$k]=(float)$v;
				}
				return $row;
			}
		}
		return 0;
    }
	
	function delete($table,$where,$alert=0){
	    $sql="delete from ".$this->BIAOTOU.$table." where $where";
		$query = $this->query($sql);
		if($alert==1){
		    echo $sql;
		}
		if($query!=''){return 1;}
		else{return mysql_error();}
	}
	
	function delete_id_in($ids,$table=MOD,$alert=0){
	    $where="id IN(".$ids.")";
		$re=$this->delete($table,$where,$alert=0);
		return $re;
	}
	
	function creat_table($name, $field) {
		if (!array_key_exists('duoduo_table_index', $field)) { //如果没有标明索引key，默认一个空
			$field['duoduo_table_index'] = '';
		}

		//$sql = 'DROP TABLE IF EXISTS `' . $this->BIAOTOU . $name . '`;';
		//$this->query($sql);
		$sql = 'CREATE TABLE IF NOT EXISTS `' . $this->BIAOTOU . $name . '` (';
		foreach ($field as $k => $v) {
			if ($k != 'duoduo_table_index') {
				$sql .= '`' . $k . '` ' . $v . ',';
			} else {
				if($v!=''){
				    $sql .= $v;
				}
			}
		}
		$sql .= ') ENGINE=MyISAM DEFAULT CHARSET=utf8;';
		$query=$this->query($sql);
		return $query;
	}
	
	function delete_table($table){
		$query=$this->query("show tables like '".$this->BIAOTOU.$table."'"); 
		$row=$this->fetch_array($query);
		if(!empty($row)){
		    $sql="DROP TABLE `".$this->BIAOTOU.$table."` ";
		    $query=$this->query($sql);
		}
		else{
		    return $this->error();
		}
	}
	
	function show_fields($table,$field=''){
		$query=$this->query('show fields from `'.$this->BIAOTOU.$table.'`;');
		$field_arr=array();
        while($arr=$this->fetch_array($query)){
		    $field_arr[]=$arr['Field'];
		}
		$this->free_result($query);
		if($field==''){
			return $field_arr;
		}
		else{
			if(in_array($field,$field_arr)){
				return 1;
			}
			else{
				return 0;
			}
		}
	}
	
	function show_states($table){
		$sql="SHOW TABLE STATUS LIKE '".$this->BIAOTOU.$table."'";
		$query=$this->query($sql);
		$arr=$this->fetch_array($query);
		return $arr;
	}

	function reapaire_field($table, $field_name, $field_info) {
		$sql = "ALTER TABLE `" . $this->BIAOTOU . $table . "` add `" . $field_name . '` ' . $field_info . ";";
		$this->query($sql);
	}
	
	function update_sort($id,$sort,$table){
		$sort=(int)$sort;
		if($sort<=0){
			$sort=DEFAULT_SORT;
		}
		else{
			$data=array('sort'=>DEFAULT_SORT);
			$this->update($table,$data,'sort="'.$sort.'"',0);
		}
		$data=array('sort'=>$sort);
		$this->update($table,$data,'id="'.$id.'"');
	}
	
function set_constants($table) {
	$data = array ();
	$sql = "show fields from " . $this->BIAOTOU . $table; //得到全表信息
	$query = $this->query($sql);
	while ($row = $this->fetch_array($query)) {
		$data[] = str_replace('_', '', strtoupper($row['Field']));
	}

	$data = array_diff($data, array ('ID'));

	$sql = "select * from " . $this->BIAOTOU .$table." where id=1";
	$query = $this->query($sql);
	$i = 1;
	$set = $this->fetch_array($query, MYSQL_NUM);

	for ($i = 1; $i < count($set); $i++) {
		define($data[$i], $set[$i]);
	}
}

	//////////////////////////////////////////////////////////////////////////////////////////
	
	public function do_report($row){ //淘宝获取订单处理办法
	    if($row['outer_code']=='null'){$row['outer_code']='';}
		$row['trade_id']=number_format($row['trade_id'],0,'',''); //处理json将数字变成科学计数法的错误
		$id=$this->select('tradelist', 'id', 'trade_id="'.$row['trade_id'].'"');
		if($id>0){ return false;} //订单存在，处理结束
		$i=0; //插入订单
		$j=0; //返利订单

		$row['uid']=$row['outer_code']?$row['outer_code']:0;
		if(strpos($row['uid'],'a')!==false){
			$a=explode('a',$row['uid']);
			$row['uid']=(int)$a[1];
			$row['platform']=(int)$a[0];
		}
		$row['uid']=(float)$row['uid'];
		if($row['uid']>0){
		    $user=$this->select('user','*','id="'.$row['uid'].'"');
			
			if(!$user['id']){
				$user['type']=0;
			}
			
			$row['fxje']=fenduan($row['commission'],$this->webset['fxbl'],$user['type']);
			$row['jifen']=(int)($row['fxje']*$this->webset['jifenbl']);
			
			if($user['id']>0){   //会员存在，添加确认时间
			    $row['qrsj']=TIME;
				$row['checked']=2;
		    }
			if($user['tjr']>0){
			    $tjr_user=$this->select('user','id,ddusername','id="'.$user['tjr'].'"');
		        if($tjr_user['id']>0){
			        $user['tjr_name']=$tjr_user['ddusername'];
				    $row['tgyj']=round($row['fxje']*$this->webset['tgbl'],2);;
		        }
		    }
		} 
		else{
			$row['fxje']=fenduan($row['commission'],$this->webset['fxbl'],0);
			$row['jifen']=(int)($row['fxje']*$this->webset['jifenbl']);
		}

		$row['jifenbao']=jfb_data_type($row['fxje']*TBMONEYBL);
		
		if($row['jifenbao']==0){
			return false;
		}
		
		$row['item_title'] = preg_replace('/\'|"/','',$row['item_title']);
		$row['shop_title'] = preg_replace('/\'|"/','',$row['shop_title']);
		$row['seller_nick'] = preg_replace('/\'|"/','',$row['seller_nick']);
		
		$row['outer_code']=$row['outer_code']?$row['outer_code']:'';
		$row['app_key']=1;
		$row['status']=5;
		$row['trade_id_former']=$row['trade_id'];
		$row['mini_trade_id'] = substr($row['trade_id'],0,8).substr($row['trade_id'],-4,4);
		$row['relate_id']=$this->insert('tradelist',$row);
		if($user['id']>0 && $row['relate_id']>0){
		    $this->rebate($user,$row,2);
	    }
	}
	
	public function do_paipai_report($row){ //拍拍获取订单处理办法
		$id=$this->select('paipai_order', 'id', 'dealId="'.$row['dealId'].'"');
		if($id>0){ return false;} //订单存在，处理结束
		$i=0; //插入订单
		$j=0; //返利订单
		
		$trade['dealId']=$row['dealId'];
		$trade['discount']=round($row['discount']/10000,2); //佣金比率
		$trade['careAmount']=round($row['careAmount']/100,2); //实际付款
		$trade['commission']=round($row['brokeragePrice']/100,2); //佣金
		$trade['commName']=addslashes($row['commName']);
		$trade['shopName']=addslashes($row['shopName']);
		$trade['outInfo']=$row['outInfo']?$row['outInfo']:'';
		$trade['realCost']=$row['realCost'];
		$trade['bargainState']=$row['bargainState'];
		$trade['chargeTime']=$row['chargeTime'];
		$trade['commNum']=$row['commNum'];
		$trade['commId']=$row['commId'];
		$trade['classId']=$row['classId'];
		$trade['className']=$row['className'];
		$trade['shopId']=$row['shopId'];
		$trade['uid']=$row['outInfo']?$row['outInfo']:0;
		
		if($trade['uid']>0){
		    $user=$this->select('user','*','id="'.$trade['uid'].'"');
			if(!$user['id']){
				$user['level']=0;
			}
			$trade['fxje']=fenduan($trade['commission'],$this->webset['paipaifxbl'],$user['type']);
			$trade['jifen']=(int)($trade['fxje']*$this->webset['jifenbl']);
			
			if($user['id']>0){   //会员存在，添加确认时间
			    $trade['addtime']=TIME;
				$trade['checked']=2;
		    }
			if($user['tjr']>0){
			    $tjr_user=$this->select('user','id,ddusername','id="'.$user['tjr'].'"');
		        if($tjr_user['id']>0){
			        $user['tjr_name']=$tjr_user['ddusername'];
				    $trade['tgyj']=round($trade['fxje']*$this->webset['tgbl'],2);
		        }
		    }
		} 
		else{
			$trade['fxje']=fenduan($trade['commission'],$this->webset['paipaifxbl'],0);
			$trade['jifen']=(int)($trade['fxje']*$this->webset['jifenbl']);
		}

		$trade['relate_id']=$this->insert('paipai_order',$trade);
		if($user['id']>0 && $trade['relate_id']>0){
		    $this->rebate($user,$trade,17);
	    }
	}
	
	function PassStar($str,$start,$end=0,$dot="*",$charset="UTF-8"){
		$len = mb_strlen($str,$charset);
		if($start==0||$start>$len){
			$start = 1;
		}
		if($end!=0&&$end>$len){
			$end = $len-2;
		}
		$endStart = $len-$end;
		$top = mb_substr($str, 0,$start,$charset);
		$bottom = "";
		if($endStart>0){
			$bottom =  mb_substr($str, $endStart,$end,$charset);
		}
		$len = $len-mb_strlen($top,$charset);
		$len = $len-mb_strlen($bottom,$charset);
		$newStr = $top;
		for($i=0;$i<$len;$i++){
			$newStr.=$dot;
		}
		$newStr.=$bottom;
		return $newStr;
	}
	
	//获取订单跟踪表中匹配用户id
	function getregorderuid($order_id)
	{  
	    $orderuser=$this->select('plugin_hn_regorder','*','oid="'.$order_id.'"');
	    if($orderuser['id']>0)
	    {
			$result_uid=$orderuser['uid'];
			$this->delete('plugin_hn_regorder','id="'.$orderuser['id'].'"');
			return $result_uid;
	    }
		unset($orderuser);
	    return 0;
	}
	
	//写入待推送消息表
	function RegPushInfo($uid,$oid,$orderstatus,$paytime,$payprice,$title,$num,$fxje,$jifen,$createtime)
	{
		if(!empty($uid) && !empty($oid) && !empty($orderstatus) && !empty($title) && !empty($num))
		{
			$data=array('uid'=>$uid,'oid'=>$oid,'orderstatus'=>$orderstatus,'paytime'=>$paytime,'payprice'=>$payprice,'title'=>$title,'num'=>$num,'fxje'=>$fxje,'jifen'=>$jifen,'createtime'=>$createtime);
		    $this->insert('plugin_hn_pushmsg',$data);
		}
	}
	
	//写入分红数据待推送消息表
	function RegPushFhInfo($uid,$msgtype,$tgyj,$jltype,$source)
	{
		//if(!empty($uid) && !empty($msgtype) && !empty($tgyj) && !empty($jltype) && !empty($source))
	    if($uid>0)
		{
			 $tsdata=array('uid'=>$uid,'oid'=>0,'orderstatus'=>$msgtype,'payprice'=>0,'paytime'=>date('Y-m-d H:i:s'),'createtime'=>date('Y-m-d H:i:s'),'title'=>'团队收益<=>'.$tgyj.'<=>'.$jltype.'<=>'.$source,'num'=>0,'fxje'=>0,'jifen'=>0,'ispushqq'=>1);	//sayii 2017-11-21 被动收入修改为团队收益
			 $this->insert('plugin_hn_pushmsg',$tsdata);
			 unset($tsdata); 
			
		}
	}
	
	//写入分红数据待推送消息表高级版
	function RegPushFhInfoEx($uid,$msgtype,$msgtitle,$jltype,$jlmsg,$source)
	{
		//if(!empty($uid) && !empty($msgtype) && !empty($tgyj) && !empty($jltype) && !empty($source))
	    if($uid>0)
		{
			 $tsdata=array('uid'=>$uid,'oid'=>0,'orderstatus'=>$msgtype,'payprice'=>0,'paytime'=>date('Y-m-d H:i:s'),'createtime'=>date('Y-m-d H:i:s'),'title'=>$msgtitle.'<=>'.$jlmsg.'<=>'.$jltype.'<=>'.$source,'num'=>0,'fxje'=>0,'jifen'=>0,'ispushqq'=>1);
			 $this->insert('plugin_hn_pushmsg',$tsdata);
			 unset($tsdata); 
			
		}
	}
	
	//写入业务状态消息
	function RegPushYwztInfo($uid,$msgtype,$msgtitle,$ywstatus,$remark)
	{
		//if(!empty($uid) && !empty($msgtype) && !empty($tgyj) && !empty($jltype) && !empty($source))
	    if($uid>0)
		{
			 $tsdata=array('uid'=>$uid,'oid'=>0,'orderstatus'=>$msgtype,'payprice'=>0,'paytime'=>date('Y-m-d H:i:s'),'createtime'=>date('Y-m-d H:i:s'),'title'=>$msgtitle.'<=>'.$ywstatus.'<=>'.$remark,'num'=>0,'fxje'=>0,'jifen'=>0,'ispushqq'=>1);
			 $this->insert('plugin_hn_pushmsg',$tsdata);
			 unset($tsdata); 
			
		}
	}
	
	//写入提现消息
	function RegPushTiXianInfo($uid, $msgtype=6, $msgtitle){
		if($uid > 0){
			$tsdata=array('uid'=>$uid,'oid'=>0,'orderstatus'=>$msgtype,'payprice'=>0,'paytime'=>date('Y-m-d H:i:s'),'createtime'=>date('Y-m-d H:i:s'),'title'=>$msgtitle,'num'=>0,'fxje'=>0,'jifen'=>0,'ispushqq'=>0);
			 $this->insert('plugin_hn_pushmsg',$tsdata);
			 unset($tsdata); 
		}
	}

	
	//订单审计	
function Audit_Order($id,$order_realnum,$real_pay_fee,$do=1){
	    //echo '订单:'.$id.' 真实数量:'.$order_realnum;
	    //echo '订单:'.$trade_id.'完成核查';
		//return;
	    $trade=$this->select('tradelist','id,num_iid,item_title,outer_code,checked,fxje,jifenbao,jifen,tgyj,trade_id,commission,uid,pay_time,item_num,status','id="'.$id.'"');
		$trade_id=preg_replace('/_\d+/','',$trade['trade_id']);
		$pj_jifenbao=$trade['jifenbao']/$trade['item_num'];  //每份商品集分宝均值
		$pj_jifen=$trade['jifen']/$trade['item_num'];        //每份商品积分均值
		$pj_tgyj=$trade['tgyj']/$trade['item_num'];          //每份推广佣金
		
		$ycsl=$trade['item_num']-$order_realnum;             //异常数量
		$yc_jifengbao=$ycsl*$pj_jifenbao;                    //异常集分宝
		$yc_jifen=$ycsl*$pj_jifen;                           //异常积分
		$yc_tgyj=$ycsl*$pj_tgyj;                             //异常推荐佣金
		
		if($trade['status']==2)
		{
			/*
			$data=array(array('f'=>'money','e'=>'+','v'=>-$mcptgyj));
			$this->update_user_mingxi($data,$cpuser['id'],33,$trade_id);
			$this->update('cptgyj',$data,'uid="'.$trade['uid'].'"');
			$data=array('audit_status'=>1,'audit_describe'=>'订单数量异常，实际下单数量为'.$order_realnum,'item_num'=>$order_realnum,'item_num'=>$order_realnum),;
			$this->update('tradelist',$data,'id="'.$trade['id'].'"');
			*/
		  if($trade['uid']==0)
		  {
			  $this->delete('tradelist','id="'.$id.'"');
		  }
		}
		else
		{
		  if($trade['uid']==0)
		  {
			  $this->delete('tradelist','id="'.$id.'"');
		  }
		
		if($trade['id']>0 && $trade['uid']>0 && $trade['checked']==2 && $trade['audit_status']==0){
		    $user=$this->select('user','ddusername,tjr,txstatus,tbtxstatus,money,jifenbao,dhstate,email,mobile,mobile_test','id="'.$trade['uid'].'"');
			if($user['ddusername']!=''){ //会员存在，剪掉金额，积分，等级
				if($trade['jifenbao']>0){
					//$data=array(array('f'=>'jifenbao','e'=>'+','v'=>-$trade['jifenbao']));
					$data=array(array('f'=>'jifenbao','e'=>'+','v'=>-$yc_jifengbao));
				}
				elseif($trade['fxje']>0){
					//$data=array(array('f'=>'money','e'=>'+','v'=>-$trade['fxje']));
					$data=array(array('f'=>'money','e'=>'+','v'=>-$trade['fxje']));
				}
				//$data[]=array('f'=>'jifen','e'=>'+','v'=>-$trade['jifen']);
				$data[]=array('f'=>'jifen','e'=>'+','v'=>-$yc_jifen);
				//$data[]=array('f'=>'level','e'=>'-','v'=>1);
				if($user['tbtxstatus']==1 && $user['jifenbao']-$yc_jifengbao<0){ //如果会员处在提现中并且现有金额小于退款金额，取消提现状态
					$why='您的淘宝订单'.$trade_id.'数据异常发生扣款，所以取消本次提现';
					$tixian=$this->select('tixian','id,money','uid="'.$trade['uid'].' and type=1" order by id desc');
					$user_data[]=array('f'=>'jifenbao','e'=>'+','v'=>$tixian['money']);
					$user_data[]=array('f'=>'tbtxstatus','e'=>'=','v'=>0);
					$msg_data=array('uid'=>$trade['uid'],'money'=>$tixian['money'],'why'=>$why,'email'=>$user['email']);
					if($user['mobile_test']==1){
						$msg_data['mobile']=$user['mobile'];
					}
		            $msg=$this->msg_insert($msg_data,3); //提现失败3号站内信
		            $tixian_data=array('f'=>'status','e'=>'=','v'=>'2');
					$this->update('tixian',$tixian_data,'id="'.$tixian['id'].'"');
					$this->update('user',$user_data,'id="'.$trade['uid'].'"');
				}
				if($user['dhstate']==1){ //如果会员处在兑换中
					$why='您的淘宝订单'.$trade_id.'数据异常发生扣款，所以取消本次兑换';
					$msg_data=array('uid'=>$trade['uid'],$why,'goods_title'=>$duihuan['goods_title'],'email'=>$user['email']);
					if($user['mobile_test']==1){
						$msg_data['mobile']=$user['mobile'];
					}
					
					$duihuan=$this->select('duihuan as a,huan_goods as b','a.id,a.spend,a.mode,a.title as goods_title','a.uid="'.$trade['uid'].'" and a.huan_goods_id=b.id order by a.id desc');
					//if($mode==1 && $user['jifenbao']-$duihuan['spend']<0){  //金额兑换，现有金额小于退款金额，取消本次兑换
					if($mode==1 && $duihuan['spend']-$yc_jifengbao<0){  //金额兑换，现有金额小于退款金额，取消本次兑换
                        $user_data[]=array('f'=>'jifenbao','e'=>'+','v'=>$duihuan['spend']);
						$user_data[]=array('f'=>'dhstate','e'=>'=','v'=>0);
		                $this->msg_insert($msg_data,5); //兑换失败5号站内信
		                $duihuan_data=array('f'=>'status','e'=>'=','v'=>'2');
						$this->update('duihuan',$duihuan_data,'id="'.$duihuan['id'].'"');
						$this->update('user',$user_data,'id="'.$trade['uid'].'"');
					}
					elseif($mode==2 && $duihuan['spend']-$yc_jifen<0){  //积分兑换，现有积分小于退款积分，取消本次兑换
					    $user_data[]=array('f'=>'jifen','e'=>'+','v'=>$duihuan['spend']);
						$user_data[]=array('f'=>'dhstate','e'=>'=','v'=>0);
		                $this->msg_insert($msg_data,5); //兑换失败5号站内信
		                $duihuan_data=array('f'=>'status','e'=>'=','v'=>'2');
						$this->update('duihuan',$duihuan_data,'id="'.$duihuan['id'].'"');
						$this->update('user',$user_data,'id="'.$trade['uid'].'"');
					}
				}
				//$mingxi_id=12+$do; //明细id，do为1是退款，明细id是13，do为2是删除，明细id是14
				if($this->webset['taoapi']['freeze']==1){ //冻结类型1，减去冻结佣金
					/*$d=date('Ym',strtotime($trade['pay_time']));
					$income_id=$this->select('income','id','uid="'.$trade['uid'].'" and date="'.$d.'"');
					if($income_id>0){
						$data=array(array('f'=>'money','e'=>'-','v'=>$trade['fxje']),array('f'=>'jifen','e'=>'-','v'=>$trade['jifen']));
						$this->update('income',$data,'id="'.$income_id.'"');
						$mingxi_data=array('uid'=>$trade['uid'],'money'=>-$trade['fxje'],'jifen'=>-$trade['jifen'],'shijian'=>$mingxi_id,'source'=>$trade_id);
						$this->mingxi_insert($data);
					}
					else{
						$this->update_user_mingxi($data,$trade['uid'],$mingxi_id,$trade_id);
					}*/
				}
				else{
					$this->update_user_mingxi($data,$trade['uid'],34,$trade_id);
				}
			}
			else{
			    return '没有确认用户！';
			}
			if($trade['tgyj']>0 && $user['tjr']>0){
				if($this->webset['taoapi']['freeze']==1){ //冻结类型1，减去冻结佣金
					/*$income_id=$this->select('income','id','uid="'.$user['tjr'].'" and date="'.$d.'"');
					if($income_id>0){
						$data=array(array('f'=>'money','e'=>'-','v'=>$trade['tgyj']));
						$this->update('income',$data,'id="'.$income_id.'"');
						$mingxi_data=array('uid'=>$user['tjr'],'money'=>-$trade['tgyj'],'shijian'=>15,'source'=>$user['ddusername']);
						$this->mingxi_insert($data);
					}*/
				}
				else{
					$data=array(array('f'=>'money','e'=>'+','v'=>-$yc_tgyj));
					//$this->update_user_mingxi($data,$user['tjr'],15,$user['ddusername']);
					$this->update_user_mingxi($data,$user['tjr'],33,$trade_id);
					$this->update('tgyj',$data,'uid="'.$trade['uid'].'"');
				}
			}
			
			if($user['tjr']>0){
			    $tjr_user=$this->select('user','id,ddusername,cpid,tjr,cptype','id="'.$user['tjr'].'"');
			    if($tjr_user['id']>0){
			        $have_tyj=1;
			    }
			}
			
			if($have_tyj)
			{
			    if(!is_null($tjr_user['cpid']))
			    {
			        $mcptjr_id=$tjr_user['id'];
			        $have_mcp=1;
			    }
			}
			
			//是否有一推合作商
			if($tjr_user['tjr']>0){
			    $invite1cpid_user=$this->select('user','id,ddusername,cpid,tjr,cptype','id="'.$tjr_user['tjr'].'"');
			    if($invite1cpid_user['id']>0 && $trade['fxje']>0 && !is_null($invite1cpid_user['cpid'])){
			        //$user['tjr_name']=$tjr_user['ddusername'];
			        $cp1tjr_id=$invite1cpid_user['id'];
			        $have_cp1=1;
			    }
			}
			
			//是否有二推合作商
			if($invite1cpid_user['tjr']>0){
			    $invite2cpid_user=$this->select('user','id,ddusername,cpid,tjr,cptype','id="'.$invite1cpid_user['tjr'].'"');
			    if($invite2cpid_user['id']>0 && $trade['fxje']>0 && !is_null($invite2cpid_user['cpid'])){
			        //$user['tjr_name']=$tjr_user['ddusername'];
			        $cp2tjr_id=$invite2cpid_user['id'];
			        $have_cp2=1;
			    }
			}
			
			//是否有三推合作商
			if($invite2cpid_user['tjr']>0){
			    $invite3cpid_user=$this->select('user','id,ddusername,cpid,tjr,cptype','id="'.$invite2cpid_user['tjr'].'"');
			    if($invite3cpid_user['id']>0 && $trade['fxje']>0 && !is_null($invite3cpid_user['cpid'])){
			        //$user['tjr_name']=$tjr_user['ddusername'];
			        $cp3tjr_id=$invite3cpid_user['id'];
			        $have_cp3=1;
			    }
			}

			
			$mcptgyj=0;  //直推合作商推广佣金(判断直推用户是否是合作商)
			$cp1tgyj=0; //一推合作商推广佣金(判断是否有一推合作商)
			$cp2tgyj=0; //二推合作商推广佣金(判断是否有二推合作商)
			$cp3tgyj=0; //三推合作商推广佣金(判断是否有三推合作商)
			
			if($trade['commission']>0)
			{
			$profit=$trade['commission']-$trade['fxje']-$trade['tgyj']-($trade['commission']*0.2); //0944利润
			$pj_profit=$profit/$trade['item_num'];   //每件纯利
			$yc_profit=$pj_profit*$ycsl;             //异常纯利     
            $profit=$yc_profit;                      //扣减后异常纯利			
			
			//判断直推是否合作商
			if($have_mcp==1){
			    $mcptgyj=$profit*($tjr_user['cptype']/100);    //直推合作商佣金
			    //alert("退回直推合作商佣金".$mcptgyj);
			}
			
			//判断是否有一推合作商
			if($have_cp1)
			{
			    if($invite1cpid_user['cptype']=='50')
			    {
			        $cp1tgyj=$profit*0.2;
			    }elseif($invite1cpid_user['cptype']=='40')
			    {
			        $cp1tgyj=$profit*0.15;
			    }elseif($invite1cpid_user['cptype']=='30')
			    {
			        $cp1tgyj=$profit*0.12;
			    }elseif($invite1cpid_user['cptype']=='20')
			    {
			        $cp1tgyj=$profit*0.09;
			    }
			    //$cp1tgyj=$profit*0.05;    //一推合作商佣金
			    //alert("一推合作商佣金".$cp1tgyj);
			}
			//判断是否有二推合作商
			if($have_cp2)
			{
			    if($invite2cpid_user['cptype']=='50')
			    {
			        $cp2tgyj=$profit*0.1;
			    }elseif($invite2cpid_user['cptype']=='40')
			    {
			        $cp2tgyj=$profit*0.075;
			    }elseif($invite2cpid_user['cptype']=='30')
			    {
			        $cp2tgyj=$profit*0.06;
			    }elseif($invite2cpid_user['cptype']=='20')
			    {
			        $cp2tgyj=$profit*0.04;
			    }
			    //$cp2tgyj=$profit*0.03;   //二推合作商佣金
			    //alert("二推合作商佣金".$cp2tgyj);
			}
			//判断是否有三推合作商
			if($have_cp3)
			{
			    if($invite3cpid_user['cptype']=='50')
			    {
			        $cp3tgyj=$profit*0.05;
			    }elseif($invite3cpid_user['cptype']=='40')
			    {
			        $cp3tgyj=$profit*0.04;
			    }elseif($invite3cpid_user['cptype']=='30')
			    {
			        $cp3tgyj=$profit*0.03;
			    }elseif($invite3cpid_user['cptype']=='20')
			    {
			        $cp3tgyj=$profit*0.02;
			    }
			    //$cp2tgyj=$profit*0.03;   //三推合作商佣金
			    //alert("二推合作商佣金".$cp2tgyj);
			}
			
			
			//判断是否有一推合作商
			//if($have_cp1==1)
			//{
			    //$cp1tgyj=$profit*0.05;    //一推合作商佣金
			    //alert("退回一推合作商佣金".$cp1tgyj);
			//}
			//判断是否有二推合作商
			//if($have_cp2==1)
			//{
			//    $cp2tgyj=$profit*0.03;   //二推合作商佣金
			    //alert("退回二推合作商佣金".$cp2tgyj);
			//}
			}
			
			//如果直推是合作商，扣减直推合作商佣金
			if($trade['commission']>0 && $have_mcp==1){
			    if($this->webset['taoapi']['freeze']==1){ //冻结类型1，减去冻结佣金
			        /*$income_id=$this->select('income','id','uid="'.$user['tjr'].'" and date="'.$d.'"');
			         if($income_id>0){
			         $data=array(array('f'=>'money','e'=>'-','v'=>$trade['tgyj']));
			         $this->update('income',$data,'id="'.$income_id.'"');
			         $mingxi_data=array('uid'=>$user['tjr'],'money'=>-$trade['tgyj'],'shijian'=>15,'source'=>$user['ddusername']);
			         $this->mingxi_insert($data);
			         }*/
			    }
			    else{
			        $cpuser=$this->select('user','id,ddusername,tjr,txstatus,tbtxstatus,money,jifenbao,dhstate,email,mobile,mobile_test,cpid','id="'.$tjr_user['id'].'"');
			        $data=array(array('f'=>'money','e'=>'+','v'=>-$mcptgyj));
			        $this->update_user_mingxi($data,$cpuser['id'],33,$trade_id);
			        $this->update('cptgyj',$data,'uid="'.$trade['uid'].'"');
			        $this->cplevelminus($mcptgyj, $cpuser['id']);
			        //alert("直推退回操作".$mcptgyj);
			    }
			}
			//如果有一推合作商，扣减一推合作商佣金
			if($trade['commission']>0 && $have_cp1==1){
			    if($this->webset['taoapi']['freeze']==1){ //冻结类型1，减去冻结佣金
			        /*$income_id=$this->select('income','id','uid="'.$user['tjr'].'" and date="'.$d.'"');
			         if($income_id>0){
			         $data=array(array('f'=>'money','e'=>'-','v'=>$trade['tgyj']));
			         $this->update('income',$data,'id="'.$income_id.'"');
			         $mingxi_data=array('uid'=>$user['tjr'],'money'=>-$trade['tgyj'],'shijian'=>15,'source'=>$user['ddusername']);
			         $this->mingxi_insert($data);
			         }*/
			    }
			    else{
			        $cp1user=$this->select('user','id,ddusername,tjr,txstatus,tbtxstatus,money,jifenbao,dhstate,email,mobile,mobile_test,cpid','id="'.$cpuser['tjr'].'"');
			        $data=array(array('f'=>'money','e'=>'+','v'=>-$cp1tgyj));
			        $this->update_user_mingxi($data,$cp1user['id'],33,$trade_id);
			        $this->update('cptgyj',$data,'uid="'.$trade['uid'].'"');
			        $this->cplevelminus($cp1tgyj, $cp1user['id']);
			        //alert("一推退回操作".$cpuser['tjr'].'|'.$cp1tgyj);
			    }
			}
			//如果有二推合作商，扣减二推合作商佣金
			if($trade['commission']>0 && $have_cp2==1){
			    if($this->webset['taoapi']['freeze']==1){ //冻结类型1，减去冻结佣金
			        /*$income_id=$this->select('income','id','uid="'.$user['tjr'].'" and date="'.$d.'"');
			         if($income_id>0){
			         $data=array(array('f'=>'money','e'=>'-','v'=>$trade['tgyj']));
			         $this->update('income',$data,'id="'.$income_id.'"');
			         $mingxi_data=array('uid'=>$user['tjr'],'money'=>-$trade['tgyj'],'shijian'=>15,'source'=>$user['ddusername']);
			         $this->mingxi_insert($data);
			         }*/
			    }
			    else{
			        $cp2user=$this->select('user','id,ddusername,tjr,txstatus,tbtxstatus,money,jifenbao,dhstate,email,mobile,mobile_test,cpid','id="'.$cp1user['tjr'].'"');
			        $data=array(array('f'=>'money','e'=>'+','v'=>-$cp2tgyj));
			        $this->update_user_mingxi($data,$cp2user['id'],33,$trade_id);
			        $this->update('cptgyj',$data,'uid="'.$trade['uid'].'"');
			        $this->cplevelminus($cp2tgyj, $cp2user['id']);
			        //alert("二推退回操作".$cp1user['tjr'].'|'.$cp2tgyj);
			    }
			}
			
			//如果有三推合作商，扣减三推合作商佣金
			if($trade['commission']>0 && $have_cp3==1){
			    if($this->webset['taoapi']['freeze']==1){ //冻结类型1，减去冻结佣金
			        /*$income_id=$this->select('income','id','uid="'.$user['tjr'].'" and date="'.$d.'"');
			         if($income_id>0){
			         $data=array(array('f'=>'money','e'=>'-','v'=>$trade['tgyj']));
			         $this->update('income',$data,'id="'.$income_id.'"');
			         $mingxi_data=array('uid'=>$user['tjr'],'money'=>-$trade['tgyj'],'shijian'=>15,'source'=>$user['ddusername']);
			         $this->mingxi_insert($data);
			         }*/
			    }
			    else{
			        $cp3user=$this->select('user','id,ddusername,tjr,txstatus,tbtxstatus,money,jifenbao,dhstate,email,mobile,mobile_test,cpid','id="'.$cp2user['tjr'].'"');
			        $data=array(array('f'=>'money','e'=>'+','v'=>-$cp3tgyj));
			        $this->update_user_mingxi($data,$cp3user['id'],33,$trade_id);
			        $this->update('cptgyj',$data,'uid="'.$trade['uid'].'"');
			        $this->cplevelminus($cp3tgyj, $cp3user['id']);
			        //alert("二推退回操作".$cp1user['tjr'].'|'.$cp2tgyj);
			    }
				
			}
			
			//$this->ddtuiguang_refund(2,$trade['id'],$trade['item_title']);
			$data=array('audit_status'=>1,'audit_describe'=>'订单数量异常，实际下单数量为'.$order_realnum);
			$this->update('tradelist',$data,'id="'.$trade['id'].'"');
		    $word='1';
		}
		else{
			return '订单状态不符';
		}
		
		//if($do==1){  //备注核查
			
			//$word='订单:'.$trade_id.'完成核查';
		//}
		
		/*
		if($do==1){  //退款订单
			$data=array('checked'=>-1,'status'=>4);
			$this->update('tradelist',$data,'id="'.$trade['id'].'"');
			$word='完成退款';
		}
		elseif($do==2){  //删除订单
			$this->delete('tradelist','id="'.$trade['id'].'"');
			$word='完成删除';
		}
		*/
		/*
		$msg_data=array('uid'=>$trade['uid'],'trade_id'=>$trade_id,'email'=>$user['mobile']);
		if($user['mobile_test']==1){
			$msg_data['mobile']=$user['mobile'];
		}*/
		//$msg=$this->msg_insert($msg_data,7); //退款7号站内信
		return $word;
		}
		unset($trade);
	}	
	
	
			//合作商推业绩增加（自动升降级）
	function cplevelplus($tgyj,$cpuserid){
	    $iscpappraise=$this->select('webset', 'val', 'var="iscpappraise"');
	    if($iscpappraise['val']==1)
	    {
	        $cp=$this->select('cplevel','id,cpmoney,cpdate','cpuserid="'.$cpuserid.'"');
	        //$cpwebset=$this->select('webset','val','var="cplevel"');
	        $arr = $this->select_all('webset', '*', 'var="cplevel"');
	        $webset = array ();
	        foreach ($arr as $row) {
	            if ($row['type'] == 1) {
	                $row['val'] = unserialize($row['val']);
	            } else {
	                $row['val'] = $row['val'];
	            }
	            $webset[$row['var']] = $row['val'];
	        }
	        //alert($webset['cplevel'][0]['level']);
	        $timenow=time();
	        $timenowSt=date('Y-m-d H:i:s',$timenow);
	        //alert($timenow);
	        if($cp['id']>0)
	        {
	            $subtime=time()-strtotime($cp['cpdate']);
	            if($subtime>=2592000)
	            {
	               if($cp['cpmoney']>=$webset['cplevel'][2]['level'])
	               {
	                   $cpdata=array('cptype'=>'50');
	                   $this->update('user',$cpdata,'id='.$cpuserid);
	                   $cpemptydata=array('cpmoney'=>'0.00','cpdate'=>$timenowSt);
	                   $this->update('cplevel',$cpemptydata,'cpuserid='.$cpuserid);
	               }
	               elseif($cp['cpmoney']>=$webset['cplevel'][1]['level'] && $cp['cpmoney']<$webset['cplevel'][2]['level'])
	               {
	                   $cpdata=array('cptype'=>'40');
	                   $this->update('user',$cpdata,'id='.$cpuserid);
	                   $cpemptydata=array('cpmoney'=>'0.00','cpdate'=>$timenowSt);
	                   $this->update('cplevel',$cpemptydata,'cpuserid='.$cpuserid);
	               }
	               elseif($cp['cpmoney']>$webset['cplevel'][0]['level'] && $cp['cpmoney']<$webset['cplevel'][1]['level'])
	               {
	                   $cpdata=array('cptype'=>'30');
	                   $this->update('user',$cpdata,'id='.$cpuserid);
	                   $cpemptydata=array('cpmoney'=>'0.00','cpdate'=>$timenowSt);
	                   $this->update('cplevel',$cpemptydata,'cpuserid='.$cpuserid);
	               }
	               else
	               {
	                   $cpdata=array('cptype'=>'30');
	                   $this->update('user',$cpdata,'id='.$cpuserid);
	                   $cpemptydata=array('cpmoney'=>'0.00','cpdate'=>$timenowSt);
	                   $this->update('cplevel',$cpemptydata,'cpuserid='.$cpuserid);
	               }
	               $data=array('f'=>'cpmoney','e'=>'+','v'=>$tgyj);
	               $this->update('cplevel',$data,'cpuserid='.$cpuserid);
	            }
	            else
	            {
	                $data=array('f'=>'cpmoney','e'=>'+','v'=>$tgyj);
	                $this->update('cplevel',$data,'cpuserid='.$cpuserid);
	            }
	        }
	        else{
	            $data=array('cpuserid'=>$cpuserid,'cpmoney'=>$tgyj,'cpdate'=>$timenowSt);
	            $this->insert('cplevel',$data);
	        }
	    }
	}
	
	//合作商推业绩减少（自动升降级）
	function cplevelminus($tgyj,$cpuserid){
	    $iscpappraise=$this->select('webset', 'val', 'var="iscpappraise"');
	    if($iscpappraise['val']==1)
	    {
	        $cp=$this->select('cplevel','id,cpmoney,cpdate','cpuserid="'.$cpuserid.'"');
	        //$cpwebset=$this->select('webset','val','var="cplevel"');
	        $arr = $this->select_all('webset', '*', 'var="cplevel"');
	        $webset = array ();
	        foreach ($arr as $row) {
	            if ($row['type'] == 1) {
	                $row['val'] = unserialize($row['val']);
	            } else {
	                $row['val'] = $row['val'];
	            }
	            $webset[$row['var']] = $row['val'];
	        }
	        //alert($webset['cplevel'][0]['level']);
	        $timenow=time();
	        $timenowSt=date('Y-m-d H:i:s',$timenow);
	        //alert($timenow);
	        if($cp['id']>0)
	        {
	            $subtime=time()-strtotime($cp['cpdate']);
	            if($subtime>=2592000)
	            {
	               if($cp['cpmoney']>=$webset['cplevel'][2]['level'])
	               {
	                   $cpdata=array('cptype'=>'50');
	                   $this->update('user',$cpdata,'id='.$cpuserid);
	                   $cpemptydata=array('cpmoney'=>'0.00','cpdate'=>$timenowSt);
	                   $this->update('cplevel',$cpemptydata,'cpuserid='.$cpuserid);
	               }
	               elseif($cp['cpmoney']>=$webset['cplevel'][1]['level'] && $cp['cpmoney']<$webset['cplevel'][2]['level'])
	               {
	                   $cpdata=array('cptype'=>'40');
	                   $this->update('user',$cpdata,'id='.$cpuserid);
	                   $cpemptydata=array('cpmoney'=>'0.00','cpdate'=>$timenowSt);
	                   $this->update('cplevel',$cpemptydata,'cpuserid='.$cpuserid);
	               }
	               elseif($cp['cpmoney']>$webset['cplevel'][0]['level'] && $cp['cpmoney']<$webset['cplevel'][1]['level'])
	               {
	                   $cpdata=array('cptype'=>'30');
	                   $this->update('user',$cpdata,'id='.$cpuserid);
	                   $cpemptydata=array('cpmoney'=>'0.00','cpdate'=>$timenowSt);
	                   $this->update('cplevel',$cpemptydata,'cpuserid='.$cpuserid);
	               }
	               else
	               {
	                   $cpdata=array('cptype'=>'30');
	                   $this->update('user',$cpdata,'id='.$cpuserid);
	                   $cpemptydata=array('cpmoney'=>'0.00','cpdate'=>$timenowSt);
	                   $this->update('cplevel',$cpemptydata,'cpuserid='.$cpuserid);
	               }
	               $data=array('f'=>'cpmoney','e'=>'-','v'=>$tgyj);
	               $this->update('cplevel',$data,'cpuserid='.$cpuserid);
	            }
	            else
	            {
	                $data=array('f'=>'cpmoney','e'=>'-','v'=>$tgyj);
	                $this->update('cplevel',$data,'cpuserid='.$cpuserid);
	            }
	        }
	        else{
	            $data=array('cpuserid'=>$cpuserid,'cpmoney'=>$tgyj,'cpdate'=>$timenowSt);
	            $this->insert('cplevel',$data);
	        }
	    }
	}
	
		//合作商推广佣金
	function cptgfz($tgyj,$tjrid,$uid,$username,$relate_id,$pay_time='',$freeze=0, $cp_fh){
	    if($tgyj>0){
	        $tjr_tgyj=$this->select('cptgyj','id,money','tjrid="'.$tjrid.'" and uid="'.$uid.'"');
	        $tjr_tgyj['money']=(float)$tjr_tgyj['money'];
	
	        if($tjr_tgyj['money']+$tgyj>$this->webset['tgfz']){
	            $tgyj=$this->webset['tgfz']-$tjr_tgyj['money'];
	        }
	
	        if($tgyj>0){
	            /* if($tjr_tgyj['money']+$tgyj>=$this->webset['tgfz']){
	                unset($data);
	                $data=array(array('f'=>'tjr','e'=>'=','v'=>0),array('f'=>'tjr_over','e'=>'=','v'=>$tjrid));
	                $this->update('user',$data,'id='.$uid);
	            } */
	            $set_con_arr=array('f'=>'money','v'=>$tgyj,'e'=>'+');
	            $this->update_user_mingxi($set_con_arr, $tjrid,30,$username,0,$freeze,$pay_time,$relate_id);
				$set_fh_arr=array('f'=>$cp_fh,'v'=>$tgyj,'e'=>'+');
				$this->update('plugin_hn_tjr_user', $set_fh_arr, 'id='.$uid);
	        }
	    }
	}
	
	//合作商推广佣金
	function refund_cptgfz($tgyj,$tjrid,$uid,$username,$relate_id,$pay_time='',$freeze=0){
	    if($tgyj>0){
	        $tjr_tgyj=$this->select('cptgyj','id,money','tjrid="'.$tjrid.'" and uid="'.$uid.'"');
	        $tjr_tgyj['money']=(float)$tjr_tgyj['money'];
	
	        if($tjr_tgyj['money']+$tgyj>$this->webset['tgfz']){
	            $tgyj=$this->webset['tgfz']-$tjr_tgyj['money'];
	        }
	
	        if($tgyj>0){
	            if(!$tjr_tgyj['id']){
	                $data=array('tjrid'=>$tjrid,'uid'=>$uid,'money'=>$tgyj);
	                $this->insert('cptgyj',$data);
	            }
	            else{
	                $data=array('f'=>'money','e'=>'-','v'=>$tgyj);
	                $this->update('cptgyj',$data,'id='.$tjr_tgyj['id']);
	            }
	
	            if($tjr_tgyj['money']+$tgyj>=$this->webset['tgfz']){
	                unset($data);
	                $data=array(array('f'=>'tjr','e'=>'=','v'=>0),array('f'=>'tjr_over','e'=>'=','v'=>$tjrid));
	                $this->update('user',$data,'id='.$uid);
	            }
	
	            $set_con_arr=array('f'=>'money','v'=>$tgyj,'e'=>'-');
	            $this->update_user_mingxi($set_con_arr, $tjrid,31,$username,0,$freeze,$pay_time,$relate_id);
	        }
	    }
	}
	
	
	
public function rebate($user,$trade,$shijian=0){ //user数组包含会员名，会员id，会员等级，推荐人  trade数组包含订单全部信息，带有id号说明是找回订单
	    if($shijian==0){
	        exit('缺少指定事件');
	    }
	    //判断订单来源
	    if(isset($trade['trade_id'])){
	        $dingdan='taobao';
	        $fxbl=$this->webset['fxbl'];
	        $trade_id=TAOTYPE==1?$trade['trade_id_former']:$trade['trade_id'];
	        	
	        if($trade['commission']==0){
	            return false;
	        }
	        if(!isset($trade['fxje']) || $trade['fxje']==0){
	            $trade['fxje']=fenduan($trade['commission'], $this->webset['fxbl'], $user['type']);
	            $trade['jifenbao']=jfb_data_type($trade['fxje'] * TBMONEYBL);
	            $trade['jifen']=(int)($trade['fxje'] * $this->webset['jifenbl']);
	        }
	    }
	    elseif(isset($trade['order_code'])){
	        $dingdan='mall';
	        $fxbl=$this->webset['mallfxbl'];
	        $trade_id=$trade['mall_name'].'返利，订单号'.$trade['order_code'];
	    }
	    elseif(isset($trade['dealId'])){
	        $dingdan='paipai';
	        $fxbl=$this->webset['paipaifxbl'];
	        $trade_id=$trade['dealId'];
	    }
	
	
	
	    $tgyj=0;    //普通推广佣金
	    $mcptgyj=0;  //直推合作商推广佣金(判断直推用户是否是合作商)
	    $cp1tgyj=0; //一推合作商推广佣金(判断是否有一推合作商)
	    $cp2tgyj=0; //二推合作商推广佣金(判断是否有二推合作商)
	    $cp3tgyj=0; //三推合作商推广佣金(判断是否有三推合作商)
	    $have_tgyj=0; //是否有推荐人
	    $have_mcp=0;  //直接推荐人是否是合作商
	    $have_cp1=0;  //是否有一推合作商
	    $have_cp2=0;  //是否有二推合作商
	    $have_cp3=0;  //是否有三推合作商
		$unioncptgyj=0;    //联盟合作商推广佣金
		$have_unioncp=0;   //是否有联盟合作商
		$unioncp_cj='cp_fh_1';   //合作商层级
	
	    $user['name']=$user['ddusername']?$user['ddusername']:$user['name'];
	    if($user['tjr']>0){
	        $tjr_user=$this->select('user','id,ddusername,cpid,tjr,cptype,usertype','id="'.$user['tjr'].'"');
	        if($tjr_user['id']>0 && $trade['fxje']>0){
	            $user['tjr_name']=$tjr_user['ddusername'];
	            $have_tgyj=1;
	        }
	    }
	    
	    if($have_tgyj)
	    {
	    //直推是否是合作商
	      if($tjr_user['usertype']>0){
	        /*
	         //$minvitecpid_user=$this->select('user','id,ddusername','cpid="'.$tjr_user[''].'"');
	         if($minvitecpid_user['id']>0 && $trade['fxje']>0){
	         //$user['tjr_name']=$tjr_user['ddusername'];
	         $cptjr_id=$invite1cpid_user['id'];
	         $have_mcptgyj=1;
	         }
	         */
	        if($trade['fxje']>0){
	            //$user['tjr_name']=$tjr_user['ddusername'];
	            $mcptjr_id=$tjr_user['id'];
	            $have_mcp=1;
	        }
	      }
	    }
	
	    //是否有一推合作商
	    if($tjr_user['tjr']>0){
	        $invite1cpid_user=$this->select('user','id,ddusername,cpid,tjr,cptype,usertype','id="'.$tjr_user['tjr'].'"');
	        if($invite1cpid_user['id']>0 && $trade['fxje']>0 && $invite1cpid_user['usertype']>0){
	            //$user['tjr_name']=$tjr_user['ddusername'];
	            $cp1tjr_id=$invite1cpid_user['id'];
	            $have_cp1=1;
	        }
	    }
	
	    //是否有二推合作商
	    if($invite1cpid_user['tjr']>0){
	        $invite2cpid_user=$this->select('user','id,ddusername,cpid,tjr,cptype,usertype','id="'.$invite1cpid_user['tjr'].'"');
	        if($invite2cpid_user['id']>0 && $trade['fxje']>0 && $invite2cpid_user['usertype']>0){
	            //$user['tjr_name']=$tjr_user['ddusername'];
	            $cp2tjr_id=$invite2cpid_user['id'];
	            $have_cp2=1;
	        }
	    }
	    
	    //是否有三推合作商
	    if($invite2cpid_user['tjr']>0){
	        $invite3cpid_user=$this->select('user','id,ddusername,cpid,tjr,cptype,usertype','id="'.$invite2cpid_user['tjr'].'"');
	        if($invite3cpid_user['id']>0 && $trade['fxje']>0 && $invite3cpid_user['usertype']>0){
	            //$user['tjr_name']=$tjr_user['ddusername'];
	            $cp3tjr_id=$invite3cpid_user['id'];
	            $have_cp3=1;
	        }
	    }
	
	
	    if($trade['id']>0){  //如果是找回订单（前台和后台），带有订单id，从新计算返利值
	        $trade['relate_id']=$trade['id'];

	        if(isset($trade['order_code'])){
	            //$fxje = fenduan($trade['commission'],$fxbl,$user['type']);
				$fxje=$trade['fxje'];
	            $jifen=$trade['jifen'];
	        }
	        else{
	            $fxje=fenduan($trade['commission'],$fxbl,$user['type']);
	            $jifen=round($fxje*$this->webset['jifenbl']);
	        }
	
	        if($trade['fxje']==0){
	            $fxje=0;
	        }
	        	
	        if($have_tgyj==1){
	            $tgyj=round($fxje*$this->webset['tgbl'],2);
	        }
	
	        //判断是淘宝订单还是商城订单
	        if($dingdan=='taobao'){
	            $jifenbao=jfb_data_type($fxje*TBMONEYBL);  //集分宝格式化
	            $data=array('fxje'=>$fxje,'jifenbao'=>$jifenbao,'jifen'=>$jifen,'tgyj'=>$tgyj,'qrsj'=>TIME,'outer_code'=>$user['id'],'uid'=>$user['id'],'checked'=>2,'status'=>5);
	            if(array_key_exists('ddjt',$trade)){
	                $data['ddjt']=$trade['ddjt'];
	            }
	            $table_name='tradelist';
	        }
	        elseif($dingdan=='paipai'){
	            $data=array('fxje'=>$fxje,'jifen'=>$jifen,'tgyj'=>$tgyj,'addtime'=>TIME,'outInfo'=>$user['id'],'uid'=>$user['id'],'checked'=>2);
	            $table_name='paipai_order';
	        }
	        elseif($dingdan=='mall'){
	            $mall_fan_type=$this->select('mall','type','id="'.$trade['mall_id'].'"');
	            if($mall_fan_type==2){
	                $fxje=0;
	            }
	            $data=array('fxje'=>$fxje,'jifen'=>$jifen,'tgyj'=>$tgyj,'qrsj'=>TIME,'uid'=>$user['id'],'status'=>1);
	            $table_name='mall_order';
	        }
	        	
	        $this->update($table_name,$data,'id="'.$trade['id'].'"');
	    }
	    else{
	        $fxje=$trade['fxje'];
	        $jifen=$trade['jifen'];
	        $jifenbao=$trade['jifenbao'];
	        if($have_tgyj==1){
	            $tgyj=round($fxje*$this->webset['tgbl'],2);
	        }
	    }
	    $trade['relate_id']=(int)$trade['relate_id'];
	
	    //给会员加返利，等级，积分
	    $set_con_arr=array(array('f'=>'money','v'=>$fxje,'e'=>'+'),array('f'=>'level','v'=>1,'e'=>'+'),array('f'=>'jifen','v'=>$jifen,'e'=>'+'));
	
	    if($dingdan=='taobao'){  //淘宝订单返的是集分宝
	        $set_con_arr[0]['f']='jifenbao';
	        $set_con_arr[0]['v']=$jifenbao;
	    }
		if($dingdan=='mall'){  //京东订单返的是集分宝
	        $set_con_arr[0]['f']='jifenbao';
	        $set_con_arr[0]['v']=$fxje;
	    }
	
	    if($trade['commission']>=$this->webset['taoapi']['freeze_limit'] && $this->webset['taoapi']['freeze']==1 && $dingdan=='taobao'){ //淘宝订单有冻结返利
	        //$freeze=1;
	    }
	    else{
	        $freeze=0;
	    }
	
	
	    $profit=$trade['commission']-$fxje-$tgyj-($trade['commission']*0.2); //0944利润  后面20%包括阿里费用及本司风险费用
		$unioncptgyj=$profit;
	    //判断直推是否合作商
	    if($have_mcp){
	        $mcptgyj=$profit*($tjr_user['cptype']/100);    //直推合作商佣金
			$unioncptgyj=$unioncptgyj-$mcptgyj;
	        //alert("直推合作商比例".$tjr_user['cptype']);
	        //alert("直推合作商佣金".$mcptgyj);
	    }
	    //判断是否有一推合作商
	    if($have_cp1)
	    {
	        if($invite1cpid_user['cptype']=='100')
	        {
				$unioncp_cj='cp_fh_2';
				$have_unioncp=1;
				$uniontjr_id=$cp1tjr_id;
				$unionpretjr_id=$mcptjr_id;
			}
	        elseif($invite1cpid_user['cptype']=='50')
	        {
	            $cp1tgyj=$profit*0.24;
	        }elseif($invite1cpid_user['cptype']=='40')
	        {
	            $cp1tgyj=$profit*0.22;
	        }elseif($invite1cpid_user['cptype']=='30')
	        {
	            $cp1tgyj=$profit*0.2;
	        }elseif($invite1cpid_user['cptype']=='20')
	        {
	            $cp1tgyj=$profit*0.18;
	        }
			$unioncptgyj=$unioncptgyj-$cp1tgyj;
	        //$cp1tgyj=$profit*0.05;    //一推合作商佣金
	        //alert("一推合作商佣金".$cp1tgyj);
	    }
	    //判断是否有二推合作商
	    if($have_cp2)
	    {
	        if($invite2cpid_user['cptype']=='100')
	        {
				$unioncp_cj='cp_fh_3';
				$have_unioncp=1;
				$uniontjr_id=$cp2tjr_id;
				$unionpretjr_id=$cp1tjr_id;
			}
	        elseif($invite2cpid_user['cptype']=='50')
	        {
	            $cp2tgyj=$profit*0.16;
	        }elseif($invite2cpid_user['cptype']=='40')
	        {
	            $cp2tgyj=$profit*0.14;
	        }elseif($invite2cpid_user['cptype']=='30')
	        {
	            $cp2tgyj=$profit*0.1;
	        }elseif($invite2cpid_user['cptype']=='20')
	        {
	            $cp2tgyj=$profit*0.08;
	        }
			$unioncptgyj=$unioncptgyj-$cp2tgyj;
	        //$cp2tgyj=$profit*0.03;   //二推合作商佣金
	        //alert("二推合作商佣金".$cp2tgyj);
	    }
	    //判断是否有三推合作商
	    if($have_cp3)
	    {
			if($invite3cpid_user['cptype']=='100')
	        {
				$unioncp_cj='cp_fh_4';
				$have_unioncp=1;
				$uniontjr_id=$cp3tjr_id;
				$unionpretjr_id=$cp2tjr_id;
			}
	        elseif($invite3cpid_user['cptype']=='50')
	        {
	            $cp3tgyj=$profit*0.1;
	        }elseif($invite3cpid_user['cptype']=='40')
	        {
	            $cp3tgyj=$profit*0.08;
	        }elseif($invite3cpid_user['cptype']=='30')
	        {
	            $cp3tgyj=$profit*0.06;
	        }elseif($invite3cpid_user['cptype']=='20')
	        {
	            $cp3tgyj=$profit*0.04;
	        }
			$unioncptgyj=$unioncptgyj-$cp3tgyj;
	        //$cp2tgyj=$profit*0.03;   //三推合作商佣金
	        //alert("二推合作商佣金".$cp2tgyj);
	    }
	    
	
	    $this->update_user_mingxi($set_con_arr, $user['id'],$shijian,$trade_id,0,$freeze,$trade['pay_time'],$trade['relate_id']); //冻结佣金，带有下单时间
	    //推广佣金
	    if($have_tgyj)
	    {
			if($tgyj>0)
			{
				$this->tgfz($tgyj,$user['tjr'],$user['id'],$trade_id.'=>会员'.$user['name'],$trade['relate_id'],$trade['pay_time'],$freeze);
			    $this->RegPushFhInfo($user['tjr'],8,sprintf('%.2f',$tgyj),'推荐奖励','来源会员:'.$this->PassStar($user['name'],2,2).' 订单号:'.$this->PassStar(preg_replace('/_\d+/','',$trade_id),4,6).'邀请更多好友使用公众号，享更多惊喜，详情咨询客服！');
				//$tsmsg=array('uid'=>$user['tjr'],'oid'=>0,'orderstatus'=>8,'payprice'=>0,'paytime'=>date('Y-m-d H:i:s'),'createtime'=>date('Y-m-d H:i:s'),'title'=>'testkk','num'=>0,'fxje'=>0,'jifen'=>0,'ispushqq'=>1);
			}
	    }
	    //直推合作商推广佣金
	    if($have_mcp)
	    {
			if($mcptgyj>0)
			{
			   $this->cptgfz($mcptgyj,$mcptjr_id,$user['id'],$trade_id.'=>会员'.$user['name'],$trade['relate_id']+'|'+$tjr_user['cptype']+'%',$trade['pay_time'],$freeze, 'cp_fh_1');
			   $this->RegPushFhInfo($mcptjr_id,8,sprintf('%.2f',$mcptgyj),'一团','来源会员:'.$this->PassStar($user['name'],2,2).' 订单号:'.$this->PassStar(preg_replace('/_\d+/','',$trade_id),4,6).'邀请更多好友使用公众号，享更多惊喜，详情咨询客服！');
			}
			
	    }
	    //一推合作商推广佣金
	    if($have_cp1)
	    {
			if($cp1tgyj>0)
			{
				$this->cptgfz($cp1tgyj,$cp1tjr_id,$user['id'],$trade_id.'=>会员'.$user['name'],$trade['relate_id']+'|%5',$trade['pay_time'],$freeze, 'cp_fh_2');
			   $this->RegPushFhInfo($cp1tjr_id,8,sprintf('%.2f',$cp1tgyj),'二团','来源会员:'.$this->PassStar($user['name'],2,2).' 订单号:'.$this->PassStar(preg_replace('/_\d+/','',$trade_id),4,6).'邀请更多好友使用公众号，享更多惊喜，详情咨询客服！');
			}
	    }
	    //二推合作商推广佣金
	    if($have_cp2)
	    {
			if($cp2tgyj>0)
			{
			  $this->cptgfz($cp2tgyj,$cp2tjr_id,$user['id'],$trade_id.'=>会员'.$user['name'],$trade['relate_id']+'|%3',$trade['pay_time'],$freeze, 'cp_fh_3');
			  $this->RegPushFhInfo($cp2tjr_id,8,sprintf('%.2f',$cp2tgyj),'三团','来源会员:'.$this->PassStar($user['name'],2,2).' 订单号:'.$this->PassStar(preg_replace('/_\d+/','',$trade_id),4,6).'邀请更多好友使用公众号，享更多惊喜，详情咨询客服！');
			}
	    }
	    //三推合作商推广佣金
	    if($have_cp3)
	    {
			if($cp3tgyj>0)
			{
			   $this->cptgfz($cp3tgyj,$cp3tjr_id,$user['id'],$trade_id.'=>会员'.$user['name'],$trade['relate_id']+'|%3',$trade['pay_time'],$freeze, 'cp_fh_4');
			  $this->RegPushFhInfo($cp3tjr_id,8,sprintf('%.2f',$cp3tgyj),'四团','来源会员:'.$this->PassStar($user['name'],2,2).' 订单号:'.$this->PassStar(preg_replace('/_\d+/','',$trade_id),4,6).'邀请更多好友使用公众号，享更多惊喜，详情咨询客服！');
			}
	    }
		//联盟合作商推广佣金
	    if($have_unioncp)
	    {
			if($unioncptgyj>0)
			{
			   $this->cptgfz($unioncptgyj,$uniontjr_id,$user['id'],$trade_id.'=>会员'.$user['name'],$trade['relate_id']+'|%3',$trade['pay_time'],$freeze,$unioncp_cj);
			   $this->RegPushFhInfo($uniontjr_id,8,sprintf('%.2f',$unioncptgyj),'联盟合作商','来源会员:'.$this->PassStar($user['name'],2,2).' 订单号:'.$this->PassStar(preg_replace('/_\d+/','',$trade_id),4,6).'邀请更多好友使用公众号，享更多惊喜，详情咨询客服！');
			}
	    }
	    unset($tsmsg);
	    if($table_name=='mall_order'){
	        $this->ddtuiguang($user['id'],$trade['relate_id'],$fxje,$table_name);
	    }
	    else{
	        if($table_name=='tradelist'){
	            $goods_id=$trade['num_iid'];
	        }
	        else{
	            $goods_id=$trade['commId'];
	        }
	        $this->ddtuiguang($user['id'],$trade['relate_id'],$fxje,$table_name,$goods_id);
	    }
		
		if($user['level'] < 1){
			$this->firstreward($user['id'], $trade_id, $trade['relate_id'], 38);
		}
	}
	
	
	function firstreward($uid, $trade_id, $relate_id, $shijian=0){
		if($shijian==0){
		    exit('缺少指定事件');
		}
		$config = $this->zhiling_config();
		if($config['newuser_reward_radio'] == 1){
			$user=$this->select('user', 'money,jifenbao,jifen', 'id="'.$uid.'"');
			$jifenbao = $user['jifenbao'] + $config['newuser_reward']*TBMONEYBL;
			$this->update('user', array('jifenbao'=>$jifenbao), 'id='.$uid);
			
			$mingxi_arr = array (
				'money' => 0,
				'jifenbao' => $config['newuser_reward']*TBMONEYBL,
				'jifen' => 0,
				'uid' => $uid,
				'shijian' => $shijian,
				'source' => $trade_id,
				'relate_id' => $relate_id
			);
			$mingxi_arr['leave_money']=$user['money'];
			$mingxi_arr['leave_jifenbao']=$jifenbao;
			$mingxi_arr['leave_jifen']=$user['jifen'];
			$mingxi_arr['addtime']=date('Y-m-d H:i:s');
			$this->insert('mingxi',$mingxi_arr);
		}
	}
	//新用户第一次下单后取消订单取消奖励
	function firstrewardcancel($uid, $trade_id, $shijian=40){
		if($shijian==0){
		    exit('缺少指定事件');
		}
		$config = $this->zhiling_config();
		if($config['newuser_reward_radio'] == 1){
			$user=$this->select('user', 'money,jifenbao,jifen', 'id="'.$uid.'"');
			$jifenbao = $user['jifenbao'] - $config['newuser_reward']*TBMONEYBL;
			$this->update('user', array('jifenbao'=>$jifenbao), 'id="'.$uid.'"');
			
			$mingxi_arr = array(
				'money' => 0,
				'jifenbao' => -$config['newuser_reward']*TBMONEYBL,
				'jifen' => 0,
				'uid' => $uid,
				'shijian' => $shijian,
				'source' => $trade_id,
				'relate_id' => ''
			);
			$mingxi_arr['leave_money']=$user['money'];
			$mingxi_arr['leave_jifenbao']=$jifenbao;
			$mingxi_arr['leave_jifen']=$user['jifen'];
			$mingxi_arr['addtime']=date('Y-m-d H:i:s');
			$this->insert('mingxi',$mingxi_arr);
		}
	}
	
	function use_fanlibl($uid,$num_iid,$pay_fee,$create_time){
		return 0;
		/*if(isset($GLOBALS[$uid.'_'.$num_iid])){
			return $GLOBALS[$uid.'_'.$num_iid];
		}
		$fanli_bi=$this->select('goods','fanli_bl','data_id="'.$num_iid.'"');
		$re=0;
		if($fanli_bi>0){
			$buy_log_id=$this->select('buy_log','id','iid="'.$num_iid.'" and uid="'.$uid.'" and day<="'.$create_time.'"');
			if($buy_log_id>0){
				$re=round($pay_fee*($fanli_bi/100),2);
				$GLOBALS[$uid.'_'.$num_iid]=$re;
			}
		}
		return $re;*/
	}
	
	function ddtuiguang($uid,$order_id,$fxje,$mall,$goods_id='',$date=''){
		//exit($uid.'--'.$order_id.'--'.$fxje.'--'.$mall.'--'.$goods_id.'--'.$date);
		if($mall=='mall_order'){
			$mall=1;
		}
		elseif($mall=='tradelist'){
			$mall=2;
		}
		elseif($type=='paipai_order'){
			$mall=3;
		}
		
		if($mall==1){
			$ddtuiguang=$this->select('ddtuiguang','*','status=0 and mall="'.$mall.'" and uid="'.$uid.'" and order_id="'.$order_id.'"');
		}
		elseif($mall==2 || $mall==3){
			$ddtuiguang=$this->select('ddtuiguang','*','status=0 and mall="'.$mall.'" and uid="'.$uid.'" and goods_id="'.$goods_id.'"');
		}
		
		if(empty($ddtuiguang)) return -1;
		$fuid=(int)$ddtuiguang['fuid'];
		$shuju_id=(int)$ddtuiguang['shuju_id'];
		$code=$ddtuiguang['code'];
		if($fuid==0) return -2;
		$fuid=(int)$this->select('user','id','id="'.$fuid.'"');
		if($fuid==0) return -3;
		if($fuid==$uid) return -4;

		if($code=='goods'){ //值得买商品商城类型
			$bili=(float)$this->webset['baoliao_jiangli_bili'];
			if($bili==0) return -5;
			$jiangli=round($fxje*$bili,2);
			if($ddtuiguang['title']==''){
				$title=$this->select('goods','title','id="'.$shuju_id.'"');
			}
			else{
				$title=$ddtuiguang['title'];
			}
			$shijian_id=25;
			$ddtuiguang_update_data[]=array('f'=>'order_id','v'=>(int)$order_id,'e'=>'=');
		}
		elseif($code=='share'){
			$bili=(float)$this->webset['baoliao_jiangli_bili'];
			if($bili==0) return -6;
			$jiangli=round($fxje*$bili,2);
			if($ddtuiguang['title']==''){
				$title=$this->select('baobei','title','id="'.$shuju_id.'"');
			}
			else{
				$title=$ddtuiguang['title'];
			}
			$shijian_id=26;
			$ddtuiguang_update_data[]=array('f'=>'order_id','v'=>$order_id,'e'=>'=');
		}
		
		$update_user_data[]=array('f'=>'money','v'=>$jiangli,'e'=>'+');
		$this->update_user_mingxi($update_user_data,$fuid,$shijian_id,$title,0,0,'',$order_id);
		$ddtuiguang_update_data[]=array('f'=>'status','v'=>1,'e'=>'=');
		$ddtuiguang_update_data[]=array('f'=>'pay_time','v'=>SJ,'e'=>'=');
		$ddtuiguang_update_data[]=array('f'=>'money','v'=>$jiangli,'e'=>'=');
		$this->update('ddtuiguang',$ddtuiguang_update_data,'id="'.$ddtuiguang['id'].'"');
		
		return 1;
	}
	
	function ddtuiguang_refund($mall,$order_id,$item_title){
		$ddtuiguang=$this->select('ddtuiguang','*','mall="'.$mall.'" && order_id="'.$order_id.'"');
		if(!empty($ddtuiguang)){
			$data=array('f'=>'money','v'=>-$ddtuiguang['money'],'e'=>'+');
			if($ddtuiguang['code']=='goods'){
				$shijian_id=27;
			}
			elseif($ddtuiguang['code']=='share'){
				$shijian_id=28;
			}
			$this->update_user_mingxi($data,$ddtuiguang['fuid'],$shijian_id,$item_title,0,'','',$trade['id']);
		}
		return 1;
	}
	
	function ddtuiguang_insert($data){
		$fuid=(int)$data['fuid'];
		if($fuid==0) return 0;
		$uid=$data['uid'];
		if($uid==0) return 0;
		if($fuid==$uid) return 0;
		$order_id=(int)$data['order_id'];
		$mall=$data['mall'];
		$status=$data['status'];
		$code=$data['code'];
		$goods_id=$data['goods_id'];
		$shuju_id=$data['shuju_id'];
		$title=$data['title'];
		
		$bili=(float)$this->webset['baoliao_jiangli_bili'];
		if($bili==0) return -1;

		if($code=='goods'){
			$title=$this->select('goods','title','id="'.$shuju_id.'"');
		}
		else{
			$title=$this->select('baobei','title','id="'.$shuju_id.'"');
		}
		$data=array('fuid'=>$fuid,'uid'=>$uid,'order_id'=>$order_id,'goods_id'=>$goods_id,'mall'=>$mall,'code'=>$code,'shuju_id'=>$shuju_id,'title'=>$title,'date'=>date('Ymd'));
		
		$weiyi=substr(md5(http_build_query($data)),8,16);
		$id=(int)$this->select('ddtuiguang','id','weiyi="'.$weiyi.'"');
		if($id==0){
			$data['weiyi']=$weiyi;
			$this->insert('ddtuiguang',$data);
		}
		return 1;
	}
	
	function tgfz($tgyj,$tjrid,$uid,$username,$relate_id,$pay_time='',$freeze=0){
		if($tgyj>0){
			$tjr_tgyj=$this->select('tgyj','id,money','tjrid="'.$tjrid.'" and uid="'.$uid.'"');
			$tjr_tgyj['money']=(float)$tjr_tgyj['money'];

			if($tjr_tgyj['money']+$tgyj>$this->webset['tgfz']){
				$tgyj=$this->webset['tgfz']-$tjr_tgyj['money'];
			}

			if($tgyj>0){
				if(!$tjr_tgyj['id']){
					$data=array('tjrid'=>$tjrid,'uid'=>$uid,'money'=>$tgyj);
					$this->insert('tgyj',$data);
				}
				else{
					$data=array('f'=>'money','e'=>'+','v'=>$tgyj);
					$this->update('tgyj',$data,'id='.$tjr_tgyj['id']);
				}

				if($tjr_tgyj['money']+$tgyj>=$this->webset['tgfz']){
					unset($data);
					$data=array(array('f'=>'tjr','e'=>'=','v'=>0),array('f'=>'tjr_over','e'=>'=','v'=>$tjrid));
					$this->update('user',$data,'id='.$uid);
				}

				$set_con_arr=array('f'=>'money','v'=>$tgyj,'e'=>'+');
		    	$this->update_user_mingxi($set_con_arr, $tjrid,6,$username,0,$freeze,$pay_time,$relate_id);
				
				/* $tsdata=array('uid'=>$tjrid,'oid'=>0,'orderstatus'=>8,'paytime'=>date('Y-m-d H:i:s'),'createtime'=>date('Y-m-d H:i:s'),'payprice'=>0,'title'=>'被动收入<=>'.$tgyj.'金币<=>'.$type.'<=>订单号'.$username.'    ^_^加油哦！邀请更多好友加入，获取更多惊喜！','num'=>0,'fxje'=>0,'jifen'=>0,'ispushqq'=>1);
		        $this->insert('plugin_hn_pushmsg',$tsdata);
				unset($tsdata); */
			}
		}
	}
	
function refund($id,$do=1){
	    $trade=$this->select('tradelist','id,num_iid,item_title,outer_code,checked,fxje,jifenbao,jifen,tgyj,trade_id,commission,uid,pay_time,checked','id="'.$id.'"');
		$trade_id=preg_replace('/_\d+/','',$trade['trade_id']);
		
		if($trade['id']>0 && $trade['uid']>0 && $trade['checked']==2){
		    $user=$this->select('user','ddusername,tjr,txstatus,tbtxstatus,money,jifenbao,dhstate,email,mobile,mobile_test','id="'.$trade['uid'].'"');
			if($user['ddusername']!=''){ //会员存在，剪掉金额，积分，等级
				if($trade['jifenbao']>0){
					$data=array(array('f'=>'jifenbao','e'=>'+','v'=>-$trade['jifenbao']));
				}
				elseif($trade['fxje']>0){
					$data=array(array('f'=>'money','e'=>'+','v'=>-$trade['fxje']));
				}
				$data[]=array('f'=>'jifen','e'=>'+','v'=>-$trade['jifen']);
				$data[]=array('f'=>'level','e'=>'-','v'=>1);
				if($user['tbtxstatus']==1 && $user['jifenbao']-$trade['jifenbao']<0){ //如果会员处在提现中并且现有金额小于退款金额，取消提现状态
					$why='您的淘宝订单'.$trade_id.'发生退款，所以取消本次提现';
					$tixian=$this->select('tixian','id,money','uid="'.$trade['uid'].' and type=1" order by id desc');
					$user_data[]=array('f'=>'jifenbao','e'=>'+','v'=>$tixian['money']);
					$user_data[]=array('f'=>'tbtxstatus','e'=>'=','v'=>0);
					$msg_data=array('uid'=>$trade['uid'],'money'=>$tixian['money'],'why'=>$why,'email'=>$user['email']);
					if($user['mobile_test']==1){
						$msg_data['mobile']=$user['mobile'];
					}
		            $msg=$this->msg_insert($msg_data,3); //提现失败3号站内信
		            $tixian_data=array('f'=>'status','e'=>'=','v'=>'2');
					$this->update('tixian',$tixian_data,'id="'.$tixian['id'].'"');
					$this->update('user',$user_data,'id="'.$trade['uid'].'"');
				}
				if($user['dhstate']==1){ //如果会员处在兑换中
					$why='您的淘宝订单'.$trade_id.'发生退款，所以取消本次兑换';
					$msg_data=array('uid'=>$trade['uid'],$why,'goods_title'=>$duihuan['goods_title'],'email'=>$user['email']);
					if($user['mobile_test']==1){
						$msg_data['mobile']=$user['mobile'];
					}
					
					$duihuan=$this->select('duihuan as a,huan_goods as b','a.id,a.spend,a.mode,a.title as goods_title','a.uid="'.$trade['uid'].'" and a.huan_goods_id=b.id order by a.id desc');
					if($mode==1 && $user['jifenbao']-$duihuan['spend']<0){  //金额兑换，现有金额小于退款金额，取消本次兑换
                        $user_data[]=array('f'=>'jifenbao','e'=>'+','v'=>$duihuan['spend']);
						$user_data[]=array('f'=>'dhstate','e'=>'=','v'=>0);
		                $this->msg_insert($msg_data,5); //兑换失败5号站内信
		                $duihuan_data=array('f'=>'status','e'=>'=','v'=>'2');
						$this->update('duihuan',$duihuan_data,'id="'.$duihuan['id'].'"');
						$this->update('user',$user_data,'id="'.$trade['uid'].'"');
					}
					elseif($mode==2 && $user['jifen']-$duihuan['spend']<0){  //积分兑换，现有积分小于退款积分，取消本次兑换
					    $user_data[]=array('f'=>'jifen','e'=>'+','v'=>$duihuan['spend']);
						$user_data[]=array('f'=>'dhstate','e'=>'=','v'=>0);
		                $this->msg_insert($msg_data,5); //兑换失败5号站内信
		                $duihuan_data=array('f'=>'status','e'=>'=','v'=>'2');
						$this->update('duihuan',$duihuan_data,'id="'.$duihuan['id'].'"');
						$this->update('user',$user_data,'id="'.$trade['uid'].'"');
					}
				}
				$mingxi_id=12+$do; //明细id，do为1是退款，明细id是13，do为2是删除，明细id是14
				if($this->webset['taoapi']['freeze']==1){ //冻结类型1，减去冻结佣金
					/*$d=date('Ym',strtotime($trade['pay_time']));
					$income_id=$this->select('income','id','uid="'.$trade['uid'].'" and date="'.$d.'"');
					if($income_id>0){
						$data=array(array('f'=>'money','e'=>'-','v'=>$trade['fxje']),array('f'=>'jifen','e'=>'-','v'=>$trade['jifen']));
						$this->update('income',$data,'id="'.$income_id.'"');
						$mingxi_data=array('uid'=>$trade['uid'],'money'=>-$trade['fxje'],'jifen'=>-$trade['jifen'],'shijian'=>$mingxi_id,'source'=>$trade_id);
						$this->mingxi_insert($data);
					}
					else{
						$this->update_user_mingxi($data,$trade['uid'],$mingxi_id,$trade_id);
					}*/
				}
				else{
					$this->update_user_mingxi($data,$trade['uid'],$mingxi_id,$trade_id);
				}
			}
			else{
			    return '没有确认用户！';
			}
			if($trade['tgyj']>0 && $user['tjr']>0){
				if($this->webset['taoapi']['freeze']==1){ //冻结类型1，减去冻结佣金
					/*$income_id=$this->select('income','id','uid="'.$user['tjr'].'" and date="'.$d.'"');
					if($income_id>0){
						$data=array(array('f'=>'money','e'=>'-','v'=>$trade['tgyj']));
						$this->update('income',$data,'id="'.$income_id.'"');
						$mingxi_data=array('uid'=>$user['tjr'],'money'=>-$trade['tgyj'],'shijian'=>15,'source'=>$user['ddusername']);
						$this->mingxi_insert($data);
					}*/
				}
				else{
					$data=array(array('f'=>'money','e'=>'+','v'=>-$trade['tgyj']));
					//$this->update_user_mingxi($data,$user['tjr'],15,$user['ddusername']);
					$this->update_user_mingxi($data,$user['tjr'],15,$trade_id);
					$this->update('tgyj',$data,'uid="'.$trade['uid'].'"');
				}
			}
			
			if($user['tjr']>0){
			    $tjr_user=$this->select('user','id,ddusername,cpid,tjr,cptype,usertype','id="'.$user['tjr'].'"');
			    if($tjr_user['id']>0){
			        $have_tgyj=1;
			    }
			}
			
			if($have_tgyj)
			{
			    if($tjr_user['usertype']>0)
			    {
			        $mcptjr_id=$tjr_user['id'];
			        $have_mcp=1;
			    }
			}
			
			//是否有一推合作商
			if($tjr_user['tjr']>0){
			    $invite1cpid_user=$this->select('user','id,ddusername,cpid,tjr,cptype,usertype','id="'.$tjr_user['tjr'].'"');
			    if($invite1cpid_user['id']>0 && $trade['fxje']>0 && $invite1cpid_user['usertype']>0){
			        //$user['tjr_name']=$tjr_user['ddusername'];
			        $cp1tjr_id=$invite1cpid_user['id'];
			        $have_cp1=1;
			    }
			}
			
			//是否有二推合作商
			if($invite1cpid_user['tjr']>0){
			    $invite2cpid_user=$this->select('user','id,ddusername,cpid,tjr,cptype,usertype','id="'.$invite1cpid_user['tjr'].'"');
			    if($invite2cpid_user['id']>0 && $trade['fxje']>0 && $invite2cpid_user['usertype']>0){
			        //$user['tjr_name']=$tjr_user['ddusername'];
			        $cp2tjr_id=$invite2cpid_user['id'];
			        $have_cp2=1;
			    }
			}
			
			//是否有三推合作商
			if($invite2cpid_user['tjr']>0){
			    $invite3cpid_user=$this->select('user','id,ddusername,cpid,tjr,cptype,usertype','id="'.$invite2cpid_user['tjr'].'"');
			    if($invite3cpid_user['id']>0 && $trade['fxje']>0 && $invite3cpid_user['usertype']>0){
			        //$user['tjr_name']=$tjr_user['ddusername'];
			        $cp3tjr_id=$invite3cpid_user['id'];
			        $have_cp3=1;
			    }
			}

			
			$mcptgyj=0;  //直推合作商推广佣金(判断直推用户是否是合作商)
			$cp1tgyj=0; //一推合作商推广佣金(判断是否有一推合作商)
			$cp2tgyj=0; //二推合作商推广佣金(判断是否有二推合作商)
			$cp3tgyj=0; //三推合作商推广佣金(判断是否有三推合作商)
			$unioncptgyg=0;  //联盟合作商推广佣金
			$unioncp_cj='cp_fh_1';   //合作商层级
			
			if($trade['commission']>0)
			{
			$profit=$trade['commission']-$trade['fxje']-$trade['tgyj']-($trade['commission']*0.2); //0944利润
			$unioncptgyj=$profit;
			//判断直推是否合作商
			if($have_mcp==1){
			    $mcptgyj=$profit*($tjr_user['cptype']/100);    //直推合作商佣金
				$unioncptgyj=$unioncptgyj-$mcptgyj;
			    //alert("退回直推合作商佣金".$mcptgyj);
			}
			
			//判断是否有一推合作商
			if($have_cp1)
			{
			    if($invite1cpid_user['cptype']=='100')
	            {
				    $have_unioncp=1;
				    $uniontjr_id=$cp1tjr_id;
				    $unionpretjr_id=$mcptjr_id;
					$have_cp1=0;
					$unioncp_cj='cp_fh_2';
			    }
	            elseif($invite1cpid_user['cptype']=='50')
			    {
			        $cp1tgyj=$profit*0.24;
					$unioncptgyj=$unioncptgyj-$cp1tgyj;
			    }elseif($invite1cpid_user['cptype']=='40')
			    {
			        $cp1tgyj=$profit*0.22;
					$unioncptgyj=$unioncptgyj-$cp1tgyj;
			    }elseif($invite1cpid_user['cptype']=='30')
			    {
			        $cp1tgyj=$profit*0.2;
					$unioncptgyj=$unioncptgyj-$cp1tgyj;
			    }elseif($invite1cpid_user['cptype']=='20')
			    {
			        $cp1tgyj=$profit*0.18;
					$unioncptgyj=$unioncptgyj-$cp1tgyj;
			    }
				
			    //$cp1tgyj=$profit*0.05;    //一推合作商佣金
			    //alert("一推合作商佣金".$cp1tgyj);
			}
			//判断是否有二推合作商
			if($have_cp2)
			{
			    if($invite2cpid_user['cptype']=='100')
	            {
				    $have_unioncp=1;
					$have_cp2=0;
				    $uniontjr_id=$cp2tjr_id;
				    $unionpretjr_id=$cp1tjr_id;
					$unioncp_cj='cp_fh_3';
			    }
	            elseif($invite2cpid_user['cptype']=='50')
			    {
			        $cp2tgyj=$profit*0.16;
					$unioncptgyj=$unioncptgyj-$cp2tgyj;
			    }elseif($invite2cpid_user['cptype']=='40')
			    {
			        $cp2tgyj=$profit*0.14;
					$unioncptgyj=$unioncptgyj-$cp2tgyj;
			    }elseif($invite2cpid_user['cptype']=='30')
			    {
			        $cp2tgyj=$profit*0.1;
					$unioncptgyj=$unioncptgyj-$cp2tgyj;
			    }elseif($invite2cpid_user['cptype']=='20')
			    {
			        $cp2tgyj=$profit*0.08;
					$unioncptgyj=$unioncptgyj-$cp2tgyj;
			    }
			    //$cp2tgyj=$profit*0.03;   //二推合作商佣金
			    //alert("二推合作商佣金".$cp2tgyj);
			}
			//判断是否有三推合作商
			if($have_cp3)
			{
			    if($invite3cpid_user['cptype']=='100')
	            {
				    $have_unioncp=1;
				    $uniontjr_id=$cp3tjr_id;
				    $unionpretjr_id=$cp2tjr_id;
					$have_cp3=0;
					$unioncp_cj='cp_fh_4';
			    }
	            elseif($invite3cpid_user['cptype']=='50')
			    {
			        $cp3tgyj=$profit*0.1;
					$unioncptgyj=$unioncptgyj-$cp3tgyj;
			    }elseif($invite3cpid_user['cptype']=='40')
			    {
			        $cp3tgyj=$profit*0.08;
					$unioncptgyj=$unioncptgyj-$cp3tgyj;
			    }elseif($invite3cpid_user['cptype']=='30')
			    {
			        $cp3tgyj=$profit*0.06;
					$unioncptgyj=$unioncptgyj-$cp3tgyj;
			    }elseif($invite3cpid_user['cptype']=='20')
			    {
			        $cp3tgyj=$profit*0.04;
					$unioncptgyj=$unioncptgyj-$cp3tgyj;
			    }
			}
			
			
			//判断是否有一推合作商
			//if($have_cp1==1)
			//{
			    //$cp1tgyj=$profit*0.05;    //一推合作商佣金
			    //alert("退回一推合作商佣金".$cp1tgyj);
			//}
			//判断是否有二推合作商
			//if($have_cp2==1)
			//{
			//    $cp2tgyj=$profit*0.03;   //二推合作商佣金
			    //alert("退回二推合作商佣金".$cp2tgyj);
			//}
			}
			
			//如果直推是合作商，扣减直推合作商佣金
			if($trade['commission']>0 && $have_mcp==1){
			    if($this->webset['taoapi']['freeze']==1){ //冻结类型1，减去冻结佣金
			        /*$income_id=$this->select('income','id','uid="'.$user['tjr'].'" and date="'.$d.'"');
			         if($income_id>0){
			         $data=array(array('f'=>'money','e'=>'-','v'=>$trade['tgyj']));
			         $this->update('income',$data,'id="'.$income_id.'"');
			         $mingxi_data=array('uid'=>$user['tjr'],'money'=>-$trade['tgyj'],'shijian'=>15,'source'=>$user['ddusername']);
			         $this->mingxi_insert($data);
			         }*/
			    }
			    else{
			        $cpuser=$this->select('user','id,ddusername,tjr,txstatus,tbtxstatus,money,jifenbao,dhstate,email,mobile,mobile_test,cpid','id="'.$tjr_user['id'].'"');
			        $data=array(array('f'=>'money','e'=>'+','v'=>-$mcptgyj));
			        $this->update_user_mingxi($data,$cpuser['id'],31,$trade_id);
					$set_fh_arr1=array('f'=>'cp_fh_1','v'=>$mcptgyj,'e'=>'-');
					$this->update('plugin_hn_tjr_user', $set_fh_arr1, 'id='.$trade['uid']);
					$cpdata=array('tjrid'=>$cpuser['id'],'uid'=>$user['id'],'money'=>-$mcptgyj);
	                $this->insert('cptgyj',$cpdata);
			        //$this->update('cptgyj',$data,'uid="'.$trade['uid'].'"');
			        //$this->cplevelminus($mcptgyj, $cpuser['id']);
			        //alert("直推退回操作".$mcptgyj);
			    }
			}
			//如果有一推合作商，扣减一推合作商佣金
			if($trade['commission']>0 && $have_cp1==1){
			    if($this->webset['taoapi']['freeze']==1){ //冻结类型1，减去冻结佣金
			        /*$income_id=$this->select('income','id','uid="'.$user['tjr'].'" and date="'.$d.'"');
			         if($income_id>0){
			         $data=array(array('f'=>'money','e'=>'-','v'=>$trade['tgyj']));
			         $this->update('income',$data,'id="'.$income_id.'"');
			         $mingxi_data=array('uid'=>$user['tjr'],'money'=>-$trade['tgyj'],'shijian'=>15,'source'=>$user['ddusername']);
			         $this->mingxi_insert($data);
			         }*/
			    }
			    else{
			        $cp1user=$this->select('user','id,ddusername,tjr,txstatus,tbtxstatus,money,jifenbao,dhstate,email,mobile,mobile_test,cpid','id="'.$cpuser['tjr'].'"');
			        $data=array(array('f'=>'money','e'=>'+','v'=>-$cp1tgyj));
			        $this->update_user_mingxi($data,$cp1user['id'],31,$trade_id);
					$set_fh_arr1=array('f'=>'cp_fh_2','v'=>$cp1tgyj,'e'=>'-');
					$this->update('plugin_hn_tjr_user', $set_fh_arr1, 'id='.$trade['uid']);
					$cpdata=array('tjrid'=>$cp1user,'uid'=>$cpuser['id'],'money'=>-$cp1tgyj);
	                $this->insert('cptgyj',$cpdata);
			        //$this->update('cptgyj',$data,'uid="'.$trade['uid'].'"');
			        //$this->cplevelminus($cp1tgyj, $cp1user['id']);
			        //alert("一推退回操作".$cpuser['tjr'].'|'.$cp1tgyj);
			    }
			}
			//如果有二推合作商，扣减二推合作商佣金
			if($trade['commission']>0 && $have_cp2==1){
			    if($this->webset['taoapi']['freeze']==1){ //冻结类型1，减去冻结佣金
			        /*$income_id=$this->select('income','id','uid="'.$user['tjr'].'" and date="'.$d.'"');
			         if($income_id>0){
			         $data=array(array('f'=>'money','e'=>'-','v'=>$trade['tgyj']));
			         $this->update('income',$data,'id="'.$income_id.'"');
			         $mingxi_data=array('uid'=>$user['tjr'],'money'=>-$trade['tgyj'],'shijian'=>15,'source'=>$user['ddusername']);
			         $this->mingxi_insert($data);
			         }*/
			    }
			    else{
			        $cp2user=$this->select('user','id,ddusername,tjr,txstatus,tbtxstatus,money,jifenbao,dhstate,email,mobile,mobile_test,cpid','id="'.$cp1user['tjr'].'"');
			        $data=array(array('f'=>'money','e'=>'+','v'=>-$cp2tgyj));
			        $this->update_user_mingxi($data,$cp2user['id'],31,$trade_id);
					$set_fh_arr1=array('f'=>'cp_fh_3','v'=>$cp2tgyj,'e'=>'-');
					$this->update('plugin_hn_tjr_user', $set_fh_arr1, 'id='.$trade['uid']);
					$cpdata=array('tjrid'=>$cp2user,'uid'=>$cp1user['id'],'money'=>-$cp2tgyj);
	                $this->insert('cptgyj',$cpdata);
			        //$this->update('cptgyj',$data,'uid="'.$trade['uid'].'"');
			        //$this->cplevelminus($cp2tgyj, $cp2user['id']);
			        //alert("二推退回操作".$cp1user['tjr'].'|'.$cp2tgyj);
			    }
			}
			
			//如果有三推合作商，扣减三推合作商佣金
			if($trade['commission']>0 && $have_cp3==1){
			    if($this->webset['taoapi']['freeze']==1){ //冻结类型1，减去冻结佣金
			        /*$income_id=$this->select('income','id','uid="'.$user['tjr'].'" and date="'.$d.'"');
			         if($income_id>0){
			         $data=array(array('f'=>'money','e'=>'-','v'=>$trade['tgyj']));
			         $this->update('income',$data,'id="'.$income_id.'"');
			         $mingxi_data=array('uid'=>$user['tjr'],'money'=>-$trade['tgyj'],'shijian'=>15,'source'=>$user['ddusername']);
			         $this->mingxi_insert($data);
			         }*/
			    }
			    else{
			        $cp3user=$this->select('user','id,ddusername,tjr,txstatus,tbtxstatus,money,jifenbao,dhstate,email,mobile,mobile_test,cpid','id="'.$cp2user['tjr'].'"');
			        $data=array(array('f'=>'money','e'=>'+','v'=>-$cp3tgyj));
			        $this->update_user_mingxi($data,$cp3user['id'],31,$trade_id);
					$set_fh_arr1=array('f'=>'cp_fh_4','v'=>$cp3tgyj,'e'=>'-');
					$this->update('plugin_hn_tjr_user', $set_fh_arr1, 'id='.$trade['uid']);
					$cpdata=array('tjrid'=>$cp3user,'uid'=>$cp2user['id'],'money'=>-$cp3tgyj);
	                $this->insert('cptgyj',$cpdata);
			        //$this->update('cptgyj',$data,'uid="'.$trade['uid'].'"');
			        //$this->cplevelminus($cp3tgyj, $cp3user['id']);
			        //alert("二推退回操作".$cp1user['tjr'].'|'.$cp2tgyj);
			    }
			}
			
			//如果有联盟合作商，扣减联盟合作商佣金
			if($trade['commission']>0 && $have_unioncp==1){
			    if($this->webset['taoapi']['freeze']==1){ //冻结类型1，减去冻结佣金
			        /*$income_id=$this->select('income','id','uid="'.$user['tjr'].'" and date="'.$d.'"');
			         if($income_id>0){
			         $data=array(array('f'=>'money','e'=>'-','v'=>$trade['tgyj']));
			         $this->update('income',$data,'id="'.$income_id.'"');
			         $mingxi_data=array('uid'=>$user['tjr'],'money'=>-$trade['tgyj'],'shijian'=>15,'source'=>$user['ddusername']);
			         $this->mingxi_insert($data);
			         }*/
			    }
			    else{
			        $unioncpuser=$this->select('user','id,ddusername,tjr,txstatus,tbtxstatus,money,jifenbao,dhstate,email,mobile,mobile_test,cpid','id="'.$uniontjr_id.'"');
			        $data=array(array('f'=>'money','e'=>'+','v'=>-$unioncptgyj));
			        $this->update_user_mingxi($data,$unioncpuser['id'],31,$trade_id);
					$set_fh_arr1=array('f'=>$unioncp_cj,'v'=>$unioncptgyj,'e'=>'-');
					$this->update('plugin_hn_tjr_user', $set_fh_arr1, 'id='.$trade['uid']);
					$cpdata=array('tjrid'=>$unioncpuser,'uid'=>$uniontjr_id,'money'=>-$unioncptgyj);
	                $this->insert('cptgyj',$cpdata);
			        //$this->update('cptgyj',$data,'uid="'.$trade['uid'].'"');
			        //$this->cplevelminus($cp3tgyj, $cp3user['id']);
			        //alert("二推退回操作".$cp1user['tjr'].'|'.$cp2tgyj);
			    }
			}
			$this->ddtuiguang_refund(2,$trade['id'],$trade['item_title']);
			if($user['level'] == 1){
				$this->firstrewardcancel($trade['uid'], $trade_id, 40);
			}
		}
		else{
			return '订单状态不符';
		}
		if($do==1){  //退款订单
			$data=array('checked'=>-1,'status'=>4);
			$this->update('tradelist',$data,'id="'.$trade['id'].'"');
			$word='完成退款';
		}
		elseif($do==2){  //删除订单
			$this->delete('tradelist','id="'.$trade['id'].'"');
			$word='完成删除';
		}
		$msg_data=array('uid'=>$trade['uid'],'trade_id'=>$trade_id,'email'=>$user['mobile']);
		if($user['mobile_test']==1){
			$msg_data['mobile']=$user['mobile'];
		}
		$msg=$this->msg_insert($msg_data,7); //退款7号站内信
		return $word;
	}
	
	/* 京东退款
	   添加时间2017-11-9 2:58
	*/
	function sayii_jd_refund($id,$do=1){
	    $trade=$this->select('mall_order','id,order_time,mall_name,mall_id,uid,order_code,item_count,item_price,sales,commission,fxje,jifen,tgyj,status,unique_id','id="'.$id.'"');
		$trade_id=preg_replace('/_\d+/','',$trade['order_code']);
		
		if($trade['id']>0 && $trade['uid']>0 && $trade['status']==1){
		    $user=$this->select('user','ddusername,tjr,txstatus,tbtxstatus,money,level,jifenbao,dhstate,email,mobile,mobile_test','id="'.$trade['uid'].'"');
			if($user['ddusername']!=''){ //会员存在，剪掉金额，积分，等级
				if($trade['fxje']>0){
					$data=array(array('f'=>'jifenbao','e'=>'+','v'=>-$trade['fxje']));
				}
				$data[]=array('f'=>'jifen','e'=>'+','v'=>-$trade['jifen']);
				$data[]=array('f'=>'level','e'=>'-','v'=>1);
				if($user['tbtxstatus']==1 && $user['jifenbao']-$trade['fxje']<0){//如果会员处在提现中并且现有金额小于退款金额，取消提现状态
					$why='您的京东订单'.$trade_id.'发生退款，所以取消本次提现';
					$tixian=$this->select('tixian','id,money','uid="'.$trade['uid'].' and type=1" order by id desc');
					$user_data[]=array('f'=>'jifenbao','e'=>'+','v'=>$tixian['money']);
					$user_data[]=array('f'=>'tbtxstatus','e'=>'=','v'=>0);
					$msg_data=array('uid'=>$trade['uid'],'money'=>$tixian['money'],'why'=>$why,'email'=>$user['email']);
					if($user['mobile_test']==1){
						$msg_data['mobile']=$user['mobile'];
					}
		            $msg=$this->msg_insert($msg_data,3); //提现失败3号站内信
		            $tixian_data=array('f'=>'status','e'=>'=','v'=>'2');
					$this->update('tixian',$tixian_data,'id="'.$tixian['id'].'"');
					$this->update('user',$user_data,'id="'.$trade['uid'].'"');
				}
				if($user['dhstate']==1){ //如果会员处在兑换中
					$why='您的京东订单'.$trade_id.'发生退款，所以取消本次兑换';
					$msg_data=array('uid'=>$trade['uid'],$why,'goods_title'=>$duihuan['goods_title'],'email'=>$user['email']);
					if($user['mobile_test']==1){
						$msg_data['mobile']=$user['mobile'];
					}
					
					$duihuan=$this->select('duihuan as a,huan_goods as b','a.id,a.spend,a.mode,a.title as goods_title','a.uid="'.$trade['uid'].'" and a.huan_goods_id=b.id order by a.id desc');
					$mode = isset($duihuan['mode'])?$duihuan['mode']:0;
					if($mode==1 && $user['jifenbao']-$duihuan['spend']<0){  //金额兑换，现有金额小于退款金额，取消本次兑换
                        $user_data[]=array('f'=>'jifenbao','e'=>'+','v'=>$duihuan['spend']);
						$user_data[]=array('f'=>'dhstate','e'=>'=','v'=>0);
		                $this->msg_insert($msg_data,5); //兑换失败5号站内信
		                $duihuan_data=array('f'=>'status','e'=>'=','v'=>'2');
						$this->update('duihuan',$duihuan_data,'id="'.$duihuan['id'].'"');
						$this->update('user',$user_data,'id="'.$trade['uid'].'"');
					}elseif($mode==2 && $user['jifen']-$duihuan['spend']<0){  //积分兑换，现有积分小于退款积分，取消本次兑换
					    $user_data[]=array('f'=>'jifen','e'=>'+','v'=>$duihuan['spend']);
						$user_data[]=array('f'=>'dhstate','e'=>'=','v'=>0);
		                $this->msg_insert($msg_data,5); //兑换失败5号站内信
		                $duihuan_data=array('f'=>'status','e'=>'=','v'=>'2');
						$this->update('duihuan',$duihuan_data,'id="'.$duihuan['id'].'"');
						$this->update('user',$user_data,'id="'.$trade['uid'].'"');
					}
				}
				$mingxi_id = 23; //明细id，do为1是退款，明细id是23
				if($this->webset['taoapi']['freeze']==1){ //冻结类型1，减去冻结佣金
					/*$d=date('Ym',strtotime($trade['pay_time']));
					$income_id=$this->select('income','id','uid="'.$trade['uid'].'" and date="'.$d.'"');
					if($income_id>0){
						$data=array(array('f'=>'money','e'=>'-','v'=>$trade['fxje']),array('f'=>'jifen','e'=>'-','v'=>$trade['jifen']));
						$this->update('income',$data,'id="'.$income_id.'"');
						$mingxi_data=array('uid'=>$trade['uid'],'money'=>-$trade['fxje'],'jifen'=>-$trade['jifen'],'shijian'=>$mingxi_id,'source'=>$trade_id);
						$this->mingxi_insert($data);
					}
					else{
						$this->update_user_mingxi($data,$trade['uid'],$mingxi_id,$trade_id);
					}*/
				}else{
					$this->update_user_mingxi($data,$trade['uid'],$mingxi_id,$trade_id);
				}
			}else{
			    return '没有确认用户！';
			}
			if($trade['tgyj']>0 && $user['tjr']>0){
				if($this->webset['taoapi']['freeze']==1){ //冻结类型1，减去冻结佣金
					/*$income_id=$this->select('income','id','uid="'.$user['tjr'].'" and date="'.$d.'"');
					if($income_id>0){
						$data=array(array('f'=>'money','e'=>'-','v'=>$trade['tgyj']));
						$this->update('income',$data,'id="'.$income_id.'"');
						$mingxi_data=array('uid'=>$user['tjr'],'money'=>-$trade['tgyj'],'shijian'=>15,'source'=>$user['ddusername']);
						$this->mingxi_insert($data);
					}*/
				}else{
					$data=array(array('f'=>'money','e'=>'+','v'=>-$trade['tgyj']));
					//$this->update_user_mingxi($data,$user['tjr'],15,$user['ddusername']);
					$this->update_user_mingxi($data,$user['tjr'],15,$trade_id);
					$this->update('tgyj',$data,'uid="'.$trade['uid'].'"');
				}
			}
			
			if($user['tjr']>0){
			    $tjr_user=$this->select('user','id,ddusername,cpid,tjr,cptype,usertype','id="'.$user['tjr'].'"');
			    if($tjr_user['id']>0){
			        $have_tgyj=1;
			    }
			}
			
			if($have_tgyj){
			    if($tjr_user['usertype']>0){
			        $mcptjr_id=$tjr_user['id'];
			        $have_mcp=1;
			    }
			}
			
			//是否有一推合作商
			if($tjr_user['tjr']>0){
			    $invite1cpid_user=$this->select('user','id,ddusername,cpid,tjr,cptype,usertype','id="'.$tjr_user['tjr'].'"');
			    if($invite1cpid_user['id']>0 && $trade['fxje']>0 && $invite1cpid_user['usertype']>0){
			        //$user['tjr_name']=$tjr_user['ddusername'];
			        $cp1tjr_id=$invite1cpid_user['id'];
			        $have_cp1=1;
			    }
			}
			
			//是否有二推合作商
			if($invite1cpid_user['tjr']>0){
			    $invite2cpid_user=$this->select('user','id,ddusername,cpid,tjr,cptype,usertype','id="'.$invite1cpid_user['tjr'].'"');
			    if($invite2cpid_user['id']>0 && $trade['fxje']>0 && $invite2cpid_user['usertype']>0){
			        //$user['tjr_name']=$tjr_user['ddusername'];
			        $cp2tjr_id=$invite2cpid_user['id'];
			        $have_cp2=1;
			    }
			}
			
			//是否有三推合作商
			if($invite2cpid_user['tjr']>0){
			    $invite3cpid_user=$this->select('user','id,ddusername,cpid,tjr,cptype,usertype','id="'.$invite2cpid_user['tjr'].'"');
			    if($invite3cpid_user['id']>0 && $trade['fxje']>0 && $invite3cpid_user['usertype']>0){
			        //$user['tjr_name']=$tjr_user['ddusername'];
			        $cp3tjr_id=$invite3cpid_user['id'];
			        $have_cp3=1;
			    }
			}

			
			$mcptgyj=0;  //直推合作商推广佣金(判断直推用户是否是合作商)
			$cp1tgyj=0; //一推合作商推广佣金(判断是否有一推合作商)
			$cp2tgyj=0; //二推合作商推广佣金(判断是否有二推合作商)
			$cp3tgyj=0; //三推合作商推广佣金(判断是否有三推合作商)
			$unioncptgyg=0;  //联盟合作商推广佣金
			$unioncp_cj='cp_fh_1';   //合作商层级
			
			if($trade['commission']>0){
				$fxje_money = $trade['fxje']/TBMONEYBL;
				$profit = $trade['commission']-$fxje_money-$trade['tgyj']-($trade['commission']*0.2); //0944利润
				$unioncptgyj = $profit;
				//判断直推是否合作商
				if($have_mcp==1){
					$mcptgyj=$profit*($tjr_user['cptype']/100);    //直推合作商佣金
					$unioncptgyj=$unioncptgyj-$mcptgyj;
					//alert("退回直推合作商佣金".$mcptgyj);
				}
			
			//判断是否有一推合作商
			if($have_cp1){
			    if($invite1cpid_user['cptype']=='100'){
				    $have_unioncp=1;
				    $uniontjr_id=$cp1tjr_id;
				    $unionpretjr_id=$mcptjr_id;
					$have_cp1=0;
					$unioncp_cj='cp_fh_2';
			    }elseif($invite1cpid_user['cptype']=='50'){
			        $cp1tgyj=$profit*0.24;
					$unioncptgyj=$unioncptgyj-$cp1tgyj;
			    }elseif($invite1cpid_user['cptype']=='40'){
			        $cp1tgyj=$profit*0.22;
					$unioncptgyj=$unioncptgyj-$cp1tgyj;
			    }elseif($invite1cpid_user['cptype']=='30'){
			        $cp1tgyj=$profit*0.2;
					$unioncptgyj=$unioncptgyj-$cp1tgyj;
			    }elseif($invite1cpid_user['cptype']=='20'){
			        $cp1tgyj=$profit*0.18;
					$unioncptgyj=$unioncptgyj-$cp1tgyj;
			    }
				
			    //$cp1tgyj=$profit*0.05;    //一推合作商佣金
			    //alert("一推合作商佣金".$cp1tgyj);
			}
			//判断是否有二推合作商
			if($have_cp2){
			    if($invite2cpid_user['cptype']=='100'){
				    $have_unioncp=1;
					$have_cp2=0;
				    $uniontjr_id=$cp2tjr_id;
				    $unionpretjr_id=$cp1tjr_id;
					$unioncp_cj='cp_fh_3';
			    }elseif($invite2cpid_user['cptype']=='50'){
			        $cp2tgyj=$profit*0.16;
					$unioncptgyj=$unioncptgyj-$cp2tgyj;
			    }elseif($invite2cpid_user['cptype']=='40'){
			        $cp2tgyj=$profit*0.14;
					$unioncptgyj=$unioncptgyj-$cp2tgyj;
			    }elseif($invite2cpid_user['cptype']=='30')
			    {
			        $cp2tgyj=$profit*0.1;
					$unioncptgyj=$unioncptgyj-$cp2tgyj;
			    }elseif($invite2cpid_user['cptype']=='20'){
			        $cp2tgyj=$profit*0.08;
					$unioncptgyj=$unioncptgyj-$cp2tgyj;
			    }
			    //$cp2tgyj=$profit*0.03;   //二推合作商佣金
			    //alert("二推合作商佣金".$cp2tgyj);
			}
			//判断是否有三推合作商
			if($have_cp3){
			    if($invite3cpid_user['cptype']=='100'){
				    $have_unioncp=1;
				    $uniontjr_id=$cp3tjr_id;
				    $unionpretjr_id=$cp2tjr_id;
					$have_cp3=0;
					$unioncp_cj='cp_fh_4';
			    }elseif($invite3cpid_user['cptype']=='50'){
			        $cp3tgyj=$profit*0.1;
					$unioncptgyj=$unioncptgyj-$cp3tgyj;
			    }elseif($invite3cpid_user['cptype']=='40'){
			        $cp3tgyj=$profit*0.08;
					$unioncptgyj=$unioncptgyj-$cp3tgyj;
			    }elseif($invite3cpid_user['cptype']=='30'){
			        $cp3tgyj=$profit*0.06;
					$unioncptgyj=$unioncptgyj-$cp3tgyj;
			    }elseif($invite3cpid_user['cptype']=='20'){
			        $cp3tgyj=$profit*0.04;
					$unioncptgyj=$unioncptgyj-$cp3tgyj;
			    }
			}
			
			
			//判断是否有一推合作商
			//if($have_cp1==1)
			//{
			    //$cp1tgyj=$profit*0.05;    //一推合作商佣金
			    //alert("退回一推合作商佣金".$cp1tgyj);
			//}
			//判断是否有二推合作商
			//if($have_cp2==1)
			//{
			//    $cp2tgyj=$profit*0.03;   //二推合作商佣金
			    //alert("退回二推合作商佣金".$cp2tgyj);
			//}
			}
			
			//如果直推是合作商，扣减直推合作商佣金
			if($trade['commission']>0 && $have_mcp==1){
			    if($this->webset['taoapi']['freeze']==1){ //冻结类型1，减去冻结佣金
			        /*$income_id=$this->select('income','id','uid="'.$user['tjr'].'" and date="'.$d.'"');
			         if($income_id>0){
			         $data=array(array('f'=>'money','e'=>'-','v'=>$trade['tgyj']));
			         $this->update('income',$data,'id="'.$income_id.'"');
			         $mingxi_data=array('uid'=>$user['tjr'],'money'=>-$trade['tgyj'],'shijian'=>15,'source'=>$user['ddusername']);
			         $this->mingxi_insert($data);
			         }*/
			    }else{
			        $cpuser=$this->select('user','id,ddusername,tjr,txstatus,tbtxstatus,money,jifenbao,dhstate,email,mobile,mobile_test,cpid','id="'.$tjr_user['id'].'"');
			        $data=array(array('f'=>'money','e'=>'+','v'=>-$mcptgyj));
			        $this->update_user_mingxi($data,$cpuser['id'],31,$trade_id);
					$set_fh_arr1=array('f'=>'cp_fh_1','v'=>$mcptgyj,'e'=>'-');
					$this->update('plugin_hn_tjr_user', $set_fh_arr1, 'id='.$trade['uid']);
					$cpdata=array('tjrid'=>$cpuser['id'],'uid'=>$user['id'],'money'=>-$mcptgyj);
	                $this->insert('cptgyj',$cpdata);
			        //$this->update('cptgyj',$data,'uid="'.$trade['uid'].'"');
			        //$this->cplevelminus($mcptgyj, $cpuser['id']);
			        //alert("直推退回操作".$mcptgyj);
			    }
			}
			//如果有一推合作商，扣减一推合作商佣金
			if($trade['commission']>0 && $have_cp1==1){
			    if($this->webset['taoapi']['freeze']==1){ //冻结类型1，减去冻结佣金
			        /*$income_id=$this->select('income','id','uid="'.$user['tjr'].'" and date="'.$d.'"');
			         if($income_id>0){
			         $data=array(array('f'=>'money','e'=>'-','v'=>$trade['tgyj']));
			         $this->update('income',$data,'id="'.$income_id.'"');
			         $mingxi_data=array('uid'=>$user['tjr'],'money'=>-$trade['tgyj'],'shijian'=>15,'source'=>$user['ddusername']);
			         $this->mingxi_insert($data);
			         }*/
			    }else{
			        $cp1user=$this->select('user','id,ddusername,tjr,txstatus,tbtxstatus,money,jifenbao,dhstate,email,mobile,mobile_test,cpid','id="'.$cpuser['tjr'].'"');
			        $data=array(array('f'=>'money','e'=>'+','v'=>-$cp1tgyj));
			        $this->update_user_mingxi($data,$cp1user['id'],31,$trade_id);
					$set_fh_arr1=array('f'=>'cp_fh_2','v'=>$cp1tgyj,'e'=>'-');
					$this->update('plugin_hn_tjr_user', $set_fh_arr1, 'id='.$trade['uid']);
					$cpdata=array('tjrid'=>$cp1user,'uid'=>$cpuser['id'],'money'=>-$cp1tgyj);
	                $this->insert('cptgyj',$cpdata);
			        //$this->update('cptgyj',$data,'uid="'.$trade['uid'].'"');
			        //$this->cplevelminus($cp1tgyj, $cp1user['id']);
			        //alert("一推退回操作".$cpuser['tjr'].'|'.$cp1tgyj);
			    }
			}
			//如果有二推合作商，扣减二推合作商佣金
			if($trade['commission']>0 && $have_cp2==1){
			    if($this->webset['taoapi']['freeze']==1){ //冻结类型1，减去冻结佣金
			        /*$income_id=$this->select('income','id','uid="'.$user['tjr'].'" and date="'.$d.'"');
			         if($income_id>0){
			         $data=array(array('f'=>'money','e'=>'-','v'=>$trade['tgyj']));
			         $this->update('income',$data,'id="'.$income_id.'"');
			         $mingxi_data=array('uid'=>$user['tjr'],'money'=>-$trade['tgyj'],'shijian'=>15,'source'=>$user['ddusername']);
			         $this->mingxi_insert($data);
			         }*/
			    }else{
			        $cp2user=$this->select('user','id,ddusername,tjr,txstatus,tbtxstatus,money,jifenbao,dhstate,email,mobile,mobile_test,cpid','id="'.$cp1user['tjr'].'"');
			        $data=array(array('f'=>'money','e'=>'+','v'=>-$cp2tgyj));
			        $this->update_user_mingxi($data,$cp2user['id'],31,$trade_id);
					$set_fh_arr1=array('f'=>'cp_fh_3','v'=>$cp2tgyj,'e'=>'-');
					$this->update('plugin_hn_tjr_user', $set_fh_arr1, 'id='.$trade['uid']);
					$cpdata=array('tjrid'=>$cp2user,'uid'=>$cp1user['id'],'money'=>-$cp2tgyj);
	                $this->insert('cptgyj',$cpdata);
			        //$this->update('cptgyj',$data,'uid="'.$trade['uid'].'"');
			        //$this->cplevelminus($cp2tgyj, $cp2user['id']);
			        //alert("二推退回操作".$cp1user['tjr'].'|'.$cp2tgyj);
			    }
			}
			
			//如果有三推合作商，扣减三推合作商佣金
			if($trade['commission']>0 && $have_cp3==1){
			    if($this->webset['taoapi']['freeze']==1){ //冻结类型1，减去冻结佣金
			        /*$income_id=$this->select('income','id','uid="'.$user['tjr'].'" and date="'.$d.'"');
			         if($income_id>0){
			         $data=array(array('f'=>'money','e'=>'-','v'=>$trade['tgyj']));
			         $this->update('income',$data,'id="'.$income_id.'"');
			         $mingxi_data=array('uid'=>$user['tjr'],'money'=>-$trade['tgyj'],'shijian'=>15,'source'=>$user['ddusername']);
			         $this->mingxi_insert($data);
			         }*/
			    }else{
			        $cp3user=$this->select('user','id,ddusername,tjr,txstatus,tbtxstatus,money,jifenbao,dhstate,email,mobile,mobile_test,cpid','id="'.$cp2user['tjr'].'"');
			        $data=array(array('f'=>'money','e'=>'+','v'=>-$cp3tgyj));
			        $this->update_user_mingxi($data,$cp3user['id'],31,$trade_id);
					$set_fh_arr1=array('f'=>'cp_fh_4','v'=>$cp3tgyj,'e'=>'-');
					$this->update('plugin_hn_tjr_user', $set_fh_arr1, 'id='.$trade['uid']);
					$cpdata=array('tjrid'=>$cp3user,'uid'=>$cp2user['id'],'money'=>-$cp3tgyj);
	                $this->insert('cptgyj',$cpdata);
			        //$this->update('cptgyj',$data,'uid="'.$trade['uid'].'"');
			        //$this->cplevelminus($cp3tgyj, $cp3user['id']);
			        //alert("二推退回操作".$cp1user['tjr'].'|'.$cp2tgyj);
			    }
			}
			
			//如果有联盟合作商，扣减联盟合作商佣金
			if($trade['commission']>0 && $have_unioncp==1){
			    if($this->webset['taoapi']['freeze']==1){ //冻结类型1，减去冻结佣金
			        /*$income_id=$this->select('income','id','uid="'.$user['tjr'].'" and date="'.$d.'"');
			         if($income_id>0){
			         $data=array(array('f'=>'money','e'=>'-','v'=>$trade['tgyj']));
			         $this->update('income',$data,'id="'.$income_id.'"');
			         $mingxi_data=array('uid'=>$user['tjr'],'money'=>-$trade['tgyj'],'shijian'=>15,'source'=>$user['ddusername']);
			         $this->mingxi_insert($data);
			         }*/
			    }else{
			        $unioncpuser=$this->select('user','id,ddusername,tjr,txstatus,tbtxstatus,money,jifenbao,dhstate,email,mobile,mobile_test,cpid','id="'.$uniontjr_id.'"');
			        $data=array(array('f'=>'money','e'=>'+','v'=>-$unioncptgyj));
			        $this->update_user_mingxi($data,$unioncpuser['id'],31,$trade_id);
					$set_fh_arr1=array('f'=>$unioncp_cj,'v'=>$unioncptgyj,'e'=>'-');
					$this->update('plugin_hn_tjr_user', $set_fh_arr1, 'id='.$trade['uid']);
					$cpdata=array('tjrid'=>$unioncpuser,'uid'=>$uniontjr_id,'money'=>-$unioncptgyj);
	                $this->insert('cptgyj',$cpdata);
			        //$this->update('cptgyj',$data,'uid="'.$trade['uid'].'"');
			        //$this->cplevelminus($cp3tgyj, $cp3user['id']);
			        //alert("二推退回操作".$cp1user['tjr'].'|'.$cp2tgyj);
			    }
			}
			$this->ddtuiguang_refund(2,$trade['id'],$trade['item_title']);
			if($user['level'] == 1){
				$this->firstrewardcancel($trade['uid'], $trade_id, 40);
			}
		}else{
			return '订单状态不符';
		}
		if($do==1){  //退款订单
			$data = array('status'=>-1);
			$this->update('mall_order',$data,'id="'.$trade['id'].'"');
			$word='完成退款';
		}
		$msg_data=array('uid'=>$trade['uid'],'trade_id'=>$trade_id,'email'=>$user['mobile']);
		if($user['mobile_test']==1){
			$msg_data['mobile']=$user['mobile'];
		}
		$msg=$this->msg_insert($msg_data,7); //退款7号站内信
		return $word;
	}
	
	function update_user_mingxi($set_con_arr, $uid, $shijian, $source = '', $alert = 0, $freeze = 0,$date='',$relate_id=0,$tsmsg='') { //在update会员数据中，如果金额和积分发生了变化，自动插入一条明细，带有时间编号和数据来源，$freeze表示此金额是否为冻结返利
		$money=0;
		$jifenbao=0;
		$jifen=0;
		if ($freeze == 1) { //冻结返利
		    $date=date('Ym',strtotime($date));  //下单的月份
		    $set = array ();
			if (!array_key_exists(0, $set_con_arr)) {
				$set_arr[0] = $set_con_arr;
			} else {
				$set_arr = $set_con_arr;
			}

			if (!array_key_exists('f', $set_arr[0])) {
				foreach ($set_arr[0] as $k => $v) {
					$arr['f'] = $k;
					$arr['e'] = '+';
					$arr['v'] = $v;
					$set[] = $arr;
				}
			} else {
				foreach ($set_arr as $k => $v) {
					if ($v['e'] == '' || $v['e'] == '=') {
						$arr['e'] = '=';
					} else {
						$arr['e'] = $v['e'];
					}
					$arr['f'] = $v['f'];
					$arr['e'] = '+';
					$arr['v'] = $v['v'];
					$set[] = $arr;
				}
			}
			
			foreach($set as $k=>$v){
			    if($v['f']=='money' && $v['v']>0){
				    $money=$v['v'];
					unset($set[$k]);
				}
				if($v['f']=='jifen' && $v['v']>0){
				    $jifen=$v['v'];
					unset($set[$k]);
				}
			}

			$income_id=$this->select('income','id','uid="'.$uid.'" and date="'.$date.'"');
			if(!$income_id){
				$data=array('money'=>$money,'jifen'=>$jifen,'uid'=>$uid,'date'=>$date);
			    $this->insert('income',$data);
			}
			else{
			    $data=array(array('f'=>'money','e'=>'+','v'=>$money),array('f'=>'jifen','e'=>'+','v'=>$jifen));
				$this->update('income',$data,'id="'.$income_id.'"');
			}

			if(!empty($set)){
				foreach($set as $k=>$v){ //重置数组，使数组第一个元素的键名为0
			        $set1[]=$v;
			    }
			   $this->update('user', $set1, 'id="' . $uid . '"',1, $alert);
			}
		}
		else{
		    $set_arr = $this->update('user', $set_con_arr, 'id="' . $uid . '"',1, $alert);
		    foreach ($set_arr as $row) {
			    if (($row['f'] == 'money' && abs($row['v']) > 0)) {
				    $money = $row['v'];
			    }
				if (($row['f'] == 'jifenbao' && abs($row['v']) > 0)) {
				    $jifenbao = $row['v'];
			    }
			    if (($row['f'] == 'jifen' && abs($row['v']) > 0)) {
				    $jifen = $row['v'];
			    }
		    }
		}
		$this->level_update($uid);
		$data = array (
			'money' => $money,
			'jifenbao' => $jifenbao,
			'jifen' => $jifen,
			'uid' => $uid,
			'shijian' => $shijian,
			'source' => $source,
			'relate_id' => $relate_id
		);
		return $this->mingxi_insert($data, $alert,$tsmsg);
	}
	
	function check_user($username,$type=''){
		if($this->webset['ucenter']['open']==1){
			include DDROOT.'/comm/uc_define.php';
            include_once DDROOT.'/uc_client/client.php';
			$uc_name=iconv("utf-8","utf-8//IGNORE",$username);
			$ucresult = uc_user_checkname($uc_name);
			if($type==''){
				if($ucresult!=1) return 'false';
		    	else return 'true';
			}
		    else{
				if($ucresult!=1) return 'true';
		    	else return 'false';
			}
		}
		else{
		    $id=$this->select('user','id',"ddusername='$username'");
			if($type==''){
				if($id>0) return 'false';
		    	else return 'true';
			}
		    else{
				if($id>0) return 'true';
		    	else return 'false';
			}
		}
	}
	
	function check_oldpass($oldpass,$uid){
		if($this->webset['ucenter']['open']==1){
			include DDROOT.'/comm/uc_define.php';
            include_once DDROOT.'/uc_client/client.php';
			$username=$this->select('user','ddusername','id="'.$uid.'"');
			$uc_name=iconv("utf-8","utf-8//IGNORE",$username);
			list ($ucid, $uc_name, $pwd, $email) = uc_user_login($uc_name, $oldpass);
			if($ucid<=0){
			    return 'false';
			}
			else{
			    return 'true';
			}
		}
		else{
			$id=$this->select('user','id',"id='$uid' and ddpassword='".md5($oldpass)."'");
			if($id>0) return 'true';
			else return 'false';
		}
	}
	
	function check_email($email){
		if($this->webset['ucenter']['open']==1){
			include DDROOT.'/comm/uc_define.php';
            include_once DDROOT.'/uc_client/client.php';
			$ucresult = uc_user_checkemail($email);
			if($ucresult!=1){
			    return 'false';
			}
			else{
			    return 'true';
			}
		}
		else{
		    $id=$this->select('user','id',"email='$email'");
		    if($id>0) return 'false';
		    else return 'true';
		}
	}
	
	function check_alipay($alipay){
	    $id=$this->select('user','id',"alipay='$alipay'");
		if($id>0) return 'false';
		else return 'true';
	}
	
	function check_my_email($email,$dduserid){
	    $id=$this->select('user','id',"id<>'$dduserid' and email='".$email."'");
		if($id>0){
		    return $id;
		}
		else{
		    return 0;
		}
	}

	function check_my_field($f,$v,$dduserid){
	    $id=$this->select('user','id',"id<>'$dduserid' and ".$f."='".$v."'");
		if($id>0){
		    return $id;
		}
		else{
		    return 0;
		}
	}
	
	function webset($l=0) {
		$arr = $this->select_all('webset', '*', 'type=1 or type=0');
		if(empty($arr)){
			return false;
		}
		$webset = array ();
		foreach ($arr as $row) {
			if ($row['type'] == 1) {
				$row['val'] = unserialize($row['val']);
			} else {
				$row['val'] = $row['val'];
			}
			$webset[$row['var']] = $row['val'];
		}

		$arr = $this->select_all('webset', '*', 'type=2');
		if(empty($arr)){
			return false;
		}

		foreach ($arr as $row) {
			if($row['id']<88){
				$name = str_replace('_', '', strtoupper($row['var']));
			}
			else{
				$name = strtoupper($row['var']);
			}
			
			$constant[$name]=$row['val'];
			if($l==101){
				define($name,$row['val']);
			}
		}

		dd_float($constant);
		dd_set_cache('constant',$constant);
		
		$webset['taoapi']['jssdk_key']=$webset['taoapi']['appkey'];
		$webset['taoapi']['jssdk_secret']=$webset['taoapi']['secret'];
		
		$webset['duomai']['wzbh']=sprintf("%03s", $webset['duomai']['wzbh']);

		if($l==101){
			return $webset;
		}
		dd_set_cache('webset',$webset);

		if($l==1){return;} //只更新webset就结束
	}
	
	function zhiling_config(){
		include_once DDROOT . '/plugin/zns/comm.php';
		include_once ZNS_PLUGIN . '/function.php';
		$this->zhiling_config = qq_get_cache('config', 'json');
		return $this->zhiling_config;
	}
	
	
	function phpwind($user,$forward){ //$user包含uid,username,password(原始密码),email,money,credit,time,cktime
	    if($this->webset['phpwind']['open']==0){
		    return $forward;
		}
		//$forward=htmlspecialchars($forward);
		$passport_key = $this->webset['phpwind']['key'];
        $userdb = array();
		$jumpurl = $this->webset['phpwind']['url'];
		
		$userdb['uid']		= $user['id'];
        $userdb['username']	= $user['name'];
        $userdb['password']	= $user['password'];
        $userdb['email']	= $user['email'];
        $userdb['money']	= 0;
        $userdb['credit']	= 0;
        $userdb['time']		= TIME;
        $userdb['cktime']	= $user['cookietime'] > 0 ? (TIME + $user['cookietime']) : 1200;
		
		$userdb_encode = '';
        foreach($userdb as $key=>$val){
	        $userdb_encode .= $userdb_encode ? "&$key=$val" : "$key=$val";
        }
		
        $userdb_encode = str_replace('=', '', StrCode($userdb_encode,$passport_key));

        if(substr($jumpurl, -1, 1) != '/') $jumpurl .= '/';
		
		if(MOD=='user' && (ACT=='login' || ACT=='register' || ACT=='info')){
			$action='login';
		}
		elseif(MOD=='user' && ACT=='exit'){
			$action='quit';
		}

		$verify = md5($action.$userdb_encode.$forward.$passport_key);
	    $url = $jumpurl."passport_client.php?action=".$action."&userdb=".rawurlencode($userdb_encode)."&forward=".rawurlencode($forward)."&verify=".rawurlencode($verify);
		return $url;
	}
	
	function duihuan($row,$admin=1){
		$goods_id=$row['goods_id'];
		$uid=$row['uid'];
		$email=$row['email'];
		$mobile=$row['mobile'];
		$jifenbao=$row['jifenbao'];
		$jifen=$row['jifen'];
		$title=$row['title'];
		$array=$row['array'];
		$auto=$row['auto'];
		$mode=$row['mode'];
		$num=$row['num'];
		$dh_id=$row['dh_id'];
		$re=1;
		if($admin==0){ //前台申请兑换，数量就减$num
			$huan_goods_arr[]=array('f'=>'num','e'=>'-','v'=>$num);
		}
	    if($array!='' && $auto+$admin>=1){
			$code_arr=unserialize($array);	
			if(!empty($code_arr)&&count($code_arr)>=$num){
				foreach($code_arr as $k=>$v){
					if(count($codes)<$num){
						$codes[]=$v;
						unset($code_arr[$k]);
						$array=serialize($code_arr);
					}else{
						break;
					}
				}
				$codestr=implode("、",$codes);
				$code='认领代码：'.$codestr;
				$huan_goods_arr[]=array('f'=>'array','e'=>'=','v'=>$array);
			}else{
			    $code='认领代码库存不足，请与管理员联系！';
			}
			$re=2;
		}
		else{
		    $code='';
			$array='';
		}
		
		if($auto+$admin>=1){ //如果是自动兑换或者后台审核
			$data=array('uid'=>$uid,'shijian'=>10,'source'=>$title);
			if($mode==1){
				$data['jifenbao']=-$jifenbao;
			}
			elseif($mode==2){
				$data['jifen']=-$jifen;
			}
			else{
				exit('数据错误');
			}
		    $this->mingxi_insert($data);
			$msg_data=array('uid'=>$uid,'goods_title'=>$title,'code'=>$code,'code'=>$code);
			
			if($mobile!=''){
				$msg_data['mobile']=$mobile;
			}
			if($email!=''){
				$msg_data['email']=$email;
			}
			
		    $this->msg_insert($msg_data,4); //兑换成功4号站内信

			$this->update('duihuan',array("code"=>$code), 'id='.$dh_id);
			if(!empty($huan_goods_arr)){
				$this->update('huan_goods',$huan_goods_arr,'id="'.$goods_id.'"');
			}
		}
		if($admin==0 && $auto==0){ //前台兑换，不是自动发货，商品数量减1
			$this->update('huan_goods',$huan_goods_arr,'id="'.$goods_id.'"');
		}
		
		return $re;
	}
	
	function no_pay_trade($pay_time,$jifenbao,$fxje=0){
		if($this->webset->taoapi['freeze']==0){
			return 0;
		}
		if(!is_numeric($pay_time)){
			$pay_time=strtotime($pay_time);
		}
		if(isset($this->webset['taoapi']['freeze_limit_user'][$dduser['type']])){
			$this->webset['taoapi']['freeze_limit']=$this->webset['taoapi']['freeze_limit_user'][$dduser['type']];
		}
		
		$freeze_limit=$this->webset['taoapi']['freeze_limit'];
		$money_freeze_limit=$this->webset['taoapi']['freeze_limit'];
		$freeze_sday=strtotime($this->webset['taoapi']['freeze_sday']);

		if($pay_time>$freeze_sday && TIME-$pay_time<3600*24*TAO_FREEZE_DAY && ($jifenbao>0 && $jifenbao>=$freeze_limit)){
			return 1;
		}
		else{
			return 0;
		}
	}
	
	function freeze_user($dduser){ //dduser是一个数组，包含id,money,jifenbao,jifen
		if($this->webset['taoapi']['freeze']==1){
			/*$freeze_money=$this->sum('income','money','uid="'.$dduser['id'].'"');
			$freeze_jifen=$this->sum('income','jifen','uid="'.$dduser['id'].'"');
			
			$dduser['live_money']=$dduser['money'];  //可用金额
			$dduser['freeze_money']=$freeze_money;  //冻结金额
			$dduser['money']+=$freeze_money; //总金额
			$dduser['live_jifen']=$dduser['jifen'];  //可用积分
			$dduser['freeze_jifen']=$freeze_jifen;  //冻结积分
			$dduser['jifen']+=$freeze_jifen; //总积分*/
			
		}
		elseif($this->webset['taoapi']['freeze']==2){
			$datetime=date('Y-m-d H:i:s',strtotime("-".TAO_FREEZE_DAY." day"));
			if($datetime<$this->webset['taoapi']['freeze_sday']){
				$datetime=$this->webset['taoapi']['freeze_sday'];
			}
			if(isset($this->webset['taoapi']['freeze_limit_user'][$dduser['type']])){
				$this->webset['taoapi']['freeze_limit']=$this->webset['taoapi']['freeze_limit_user'][$dduser['type']];
			}
			$freeze=$this->sum('tradelist','fxje,jifenbao,jifen','uid="'.$dduser['id'].'" and checked=2 and  pay_time>="'.$datetime.'" and jifenbao>"'.$this->webset['taoapi']['freeze_limit'].'"');
			$freeze_jifenbao=$freeze['jifenbao'];
			$freeze_jifen=$freeze['jifen'];
			
			$freeze_limit_money=round($this->webset['taoapi']['freeze_limit']/TBMONEYBL,2);
			$freeze['freeze_money']=$this->sum('tradelist','fxje','uid="'.$dduser['id'].'" and checked=2 and  pay_time>="'.$datetime.'" and fxje>"'.$freeze_limit_money.'" and jifenbao=0');
			
			/*$tg_limit=round($freeze_limit_money*$this->webset['tgbl'],2);
			
			$tg_freeze_money=$this->sum('mingxi','money','uid="'.$dduser['id'].'" and shijian=6 and money>"'.$tg_limit.'" and  addtime>="'.$datetime.'"');
			$freeze['freeze_money']+=$tg_freeze_money;*/
			
			$dduser['live_jifenbao']=data_type(round($dduser['jifenbao']-$freeze_jifenbao,2),TBMONEYTYPE);  //可用金额
			$dduser['freeze_jifenbao']=$freeze_jifenbao;  //冻结金额
			$dduser['live_jifen']=$dduser['jifen']-$freeze_jifen;  //可用积分
			$dduser['freeze_jifen']=$freeze_jifen;  //冻结积分
			$dduser['live_money']=round($dduser['money']-$freeze['freeze_money'],2);
			$dduser['freeze_money']=$freeze['freeze_money'];
		}
		else{
			$dduser['freeze_jifenbao']=0;
			$dduser['freeze_jifen']=0;
			$dduser['live_jifenbao']=jfb_data_type($dduser['jifenbao']);
			$dduser['live_jifen']=$dduser['jifen'];
			$dduser['live_money']=$dduser['money'];
			$dduser['freeze_money']=0;
		}
		
		if(defined('INDEX')){
			if($dduser['live_jifenbao']<0) $dduser['live_jifenbao']=0;
			if($dduser['live_jifen']<0) $dduser['live_jifen']=0;
		}
		
		return $dduser;
	}
	
	function user_money_from_buy($uid){
		$num=(float)$this->sum('mingxi','money','uid="'.$uid.'" and shijian in (2,3,8,12,17,18)');
		return round($num,2);
	}
	
	function tixian($id,$do='yes',$ddback=0,$api_ok='',$api_return='',$toolwhy=''){
		$data=array();
		$row=$this->select('tixian as a,user as b','a.*,b.txstatus as user_status,b.tbtxstatus as user_tbstatus,b.ddusername,b.email,b.mobile as user_mobile,b.mobile_test','a.id="'.$id.'" and a.uid=b.id');
		$money=(float)$row['money'];
		$money2=(float)$row['money2'];
		$type=$row['type'];
		
		if($row['wait']==1 && $do=='yes' && $ddback==0 && $this->webset['tixian']['ddpay']==1){
			if(strpos($_SERVER['HTTP_REFERER'],'act=addedi')!==false){ //来源是提现处理页，返回错误状态
				return array('s'=>0,'r'=>$row['api_return']);
			}
			else{  //来源是提现列表页，返回正确状态（批量提现）
				return array('s'=>1,'r'=>$row);
			}
		}
		
		if($row['tool']==3){
			$txtool=$row['code'];
		}
		elseif($row['tool']==2){
			$txtool='财付通：'.$row['code'];


		}
		elseif($row['tool']==1){
			$txtool='支付宝：'.$row['code'];
		}

		if($row['status']!=0){
	    	return array('s'=>0,'r'=>'ID：'.$row['id'].'此订单状态不是待审核'); //数据错误
		}
		
		if($type==1){
			$alipay=$row['code'];
			$yitixian_f='tbyitixian';  //集分宝提现，已提现字段名
			$money_f='jifenbao';
			$txstatus_f='tbtxstatus';
			if($row['user_tbstatus']!=1) return array('s'=>0,'r'=>'ID：'.$row['id'].'会员集分宝提现不是提现中'); //数据错误
		}
		elseif($type==2){
			$yitixian_f='yitixian';  //金额提现，已提现字段名
			$money_f='money';
			$txstatus_f='txstatus';
			if($row['user_status']!=1) return array('s'=>0,'r'=>'ID：'.$row['id'].'会员金额提现不是提现中'); //数据错误
		}
	
		$user_data=array(array('f'=>$txstatus_f,'e'=>'=','v'=>0));  //不管提现处理的结果，会员的提现状态都是0
	
		$msg_data=array('uid'=>$row['uid'],'username'=>$row['ddusername'],'money'=>$money.($type==1?TBMONEY:'元'),'txtool'=>$txtool,'email'=>$row['email']);
	
		if($do=='yes'){
			if($ddback==1){
				if($api_ok=='' || $api_return==''){
					return array('s'=>0,'r'=>'参数错误'); //数据错误
				}
			}
			else{
				if($type==1 && $this->webset['tixian']['ddpay']==1){
					$ddopen=fs('ddopen');
					$ddopen->ini();
					$api=$ddopen->pay_jifenbao($alipay,$money2,$id,$row['realname'],$row['mobile']);
					$api_return=$api['r']?$api['r']:'未知错误';
					if($api['s']==1){
						$api_ok=1;
					}
					else{
						$api_ok=0;
					}
				}
				else{
					$api_ok=1;
					$api_return='';
				}
			}

			if($api_ok==1){
				unset($data);
				$mingxi_data=array('uid'=>$row['uid'],$money_f=>-$money,'shijian'=>9,'source'=>$txtool,'relate_id'=>$row['id']);
				//$this->mingxi_insert($mingxi_data);
				$txstatus=0;
				$msg=$this->msg_insert($msg_data,2); //提现成功2号站内信
				unset($data);
				$data=array(array('f'=>'status','e'=>'=','v'=>'1'),array('f'=>'wait','e'=>'=','v'=>'0'),array('f'=>'api_return','e'=>'=','v'=>$api_return));
				$user_data[]=array('f'=>$yitixian_f,'e'=>'+','v'=>$row['money']);
				$user_data[]=array('f'=>'lasttixian','e'=>'=','v'=>TIME);
		
				if($this->webset['tixian']['hytxjl']>0){
		    		$user=$this->select('user','ddusername,tjr,tjr_over,hytx','id="'.$row['uid'].'"');
					if($user['tjr_over']>0) $user['tjr']=$user['tjr_over'];
		    		if($user['tjr']>0 && $user['hytx']==0){
		        		$tjr_data=array('f'=>'money','e'=>'+','v'=>$this->webset['tixian']['hytxjl']);
						$this->update_user_mingxi($tjr_data, (int)$user['tjr'],11,$user['ddusername']); //好友提现奖励，11号明细
						$this->update('user',array('hytx'=>1),'id="'.$row['uid'].'"');
		    		}
				}
			}
			else{  //如果多多集分宝发放接口反馈错误码，会员和提现数据的状态不变
				unset($data);
				$data[]=array('f'=>'api_return','e'=>'=','v'=>$api_return);
				if($api['s']==2){ //等待发送中
					$data[]=array('f'=>'wait','e'=>'=','v'=>1); 
				}
				$this->update('tixian',$data,'id="'.$id.'"');
				
				if(strpos($_SERVER['HTTP_REFERER'],'act=addedi')!==false){ //来源是提现处理页，返回错误状态
					return array('s'=>0,'r'=>$api_return);
				}
				else{  //来源是提现列表页，返回正确状态（批量提现）
					return array('s'=>1,'r'=>$row);
				}
			}
			$msgtitle = date('Y-m-d H:i:s', $row['addtime']) . '<=>' . $row['code'] . '<=>' . $row['money'] . '<=>' . TBMONEY . '<=>' . '提现成功' . '<=>' . '恭喜你， 您的提现申请已经审核通过并打款， 请查收！';
			$this->RegPushTiXianInfo($row['uid'], 6, $msgtitle);
		}
		elseif($do=='no'){
			if($row['wait']==1){
				$ddopen=fs('ddopen');
				$ddopen->ini();
				$api=$ddopen->cancel_jifenbao($id);
			}
			$user_data[]=array('f'=>$money_f,'e'=>'+','v'=>$money);
			$msg_data['why']=$_POST['why'];
			$msg=$this->msg_insert($msg_data,3); //提现失败3号站内信
			$data[]=array('f'=>'status','e'=>'=','v'=>'2');
			$data[]=array('f'=>'why','e'=>'=','v'=>$_POST['why']);
			//sayii 2017-11-21添加下面模块
			$toolwhy = empty($_POST['why'])?$toolwhy:$_POST['why'];
			$msgtitle = date('Y-m-d H:i:s', $row['addtime']) . '<=>' . $row['code'] . '<=>' . $row['money'] . '<=>' . TBMONEY . '<=>' . '提现失败' . '<=>' . '您有一笔提现申请失败，提现' . $row['money'] . TBMONEY . '已返回您的账户。' . $toolwhy;
			$this->RegPushTiXianInfo($row['uid'], 6, $msgtitle);
			
			//sayii 2017-11-21提现失败添加到明细
			if($type == 1){	//集分宝提现
				$money_f = 'jifenbao';
			}elseif($type == 2){  //金额提现
				$money_f = 'money';
			}
			$mingxi_data=array('uid'=>$row['uid'],$money_f=>$money,'shijian'=>41,'source'=>$toolwhy,'relate_id'=>$row['id']);
		}
		$data[]=array('f'=>'dotime','e'=>'=','v'=>TIME);
		$this->update('tixian',$data,'id="'.$id.'"');
		$this->update('user',$user_data,'id="'.$row['uid'].'"');
		$this->mingxi_insert($mingxi_data);	//sayii 2017-11-21 添加
		$a=array('s'=>1,'r'=>$row);
		return $a;
	}
	
	function set_webset($var,$val,$jiance=1){
		if(preg_match('/[A-Z]{1,50}/',$var)){ //大写字母，是常量定义
			$type=2;
			$var=strtolower($var);
		}
		else{
			if(is_array($val)){
				$val=serialize($val);
				$type=1;
			}
			else{
				$type=0;
			}
		}
		
		if($jiance==1){
			$webset_field=$this->select_2_field('webset','id,var','1=1');
			$id=(int)array_search($var,$webset_field);
			if($id==0){
				$id=(int)array_search(strtoupper($var),$webset_field);
			}
		}
		else{
			$id=1;

		}
		if($id>0){
			$data=array('val'=>$val,'type'=>$type);
			$this->update('webset',$data,'var="'.$var.'"');
		}
		else{
			$data=array('val'=>$val,'var'=>$var,'type'=>$type);
			$this->insert('webset',$data);
		}
	}
	function webset_part($k,$r){
		$webset_field=$this->select('webset','val','var="'.$k.'"');
		$webset_field=unserialize($webset_field);
		$post_arr[$k] = $webset_field;
		foreach($webset_field as $a=>$b){
			foreach($r as $c=>$d){
				if($a==$c){
					$post_arr[$k][$a] = $d; 
				}else{
					$post_arr[$k][$c] = $d;
				}
			}
		}
		if(empty($webset_field)){
			$post_arr[$k] = $r;
		}
		return $post_arr;
	}
	
	function cron($p=0,$action='select'){
		if($action=='insert'){
			$type=$p['type'];
			unset($p['type']);
			$p=dd_xuliehua($p);
			$data=array('val'=>$p,'type'=>$type);
			$this->insert('cron_content',$data);
			return true;
		}
		
		if(isset($p['type'])){
			$type=$p['type'];
		}
		else{
			$type=0;
		}
		if(isset($p['limit'])){
			$limit=$p['limit'];
		}
		else{
			$limit=5;
		}
		if(isset($p['ids'])){
			$limit=count($p['ids']);
			$ids=implode(',',$p['ids']);
		}
		
		if($type==0){
			$where='1';
		}
		else{
			$where='type='.$type;
		}
		if(isset($ids)){
			$where.=' and id in('.$ids.')';
		}
		$crons=$this->select_all('cron_content','id,val,type',$where.' limit '.$limit);
		foreach($crons as $row){
			switch($row['type']){
				case 1:  //邮件计划任务
					$data=dd_unxuliehua($row['val']);
					mail_send($data['email'], $data['title'], $data['content'],$data['from'],$data['pwd'],$data['smtp']);
					$this->delete('cron_content','id='.$row['id']);
				break;
			}
		}
	}
	
	/**
     *$arr 数据传入
     *$result 统计传入
     *$result['update_num'] 返回更新数目
     *$result['insert_num'] 返回插入数目
	 *$result['chongfu'] 重复数据
     */
    function trade_import($shuju) {
		include_once(DDROOT.'/comm/php_lock.class.php');
		$phplock=new phplock('trade_import');
		if($phplock->status==0){
			$re=array('s'=>0,'r'=>'请求过多，请稍后再试');
		    return $re;
		}
		
		$data_arr=array();
        $status_arr = include (DDROOT . '/data/status_arr.php'); //订单状态
		foreach($status_arr as $k=>$v){
			$status_arr[$k]=strip_tags($v);
		}
        
		if (count($shuju[1])>8 && count($shuju[1]) < 20) {  //7格以内是如意淘的订单
            jump("-1", "你导入的文件不符合，请重新导出淘宝客推广明细表");
        }
		
		if(count($shuju[1])==8){
			unset($shuju[1]);
			$_shuju=array();
			$cart=array();
			$i=0;
			$j=0;
			foreach($shuju as $k=>$row){
				if($row[4]=='订单失效'){
					unset($shuju[$k]);
					continue;
				}
				
				if(!isset($cart['a_'.$row[3]])){  //如果订单号不存在
					$cart['a_'.$row['3']]=array('k'=>$k,'status'=>$row[4]);
					$_shuju[$k]=$row;
					$i++;
				}
				else{ //如果订单号存在，增加佣金
					$_k=$cart['a_'.$row['3']]['k'];
					$_shuju[$_k][5]=$_shuju[$_k][5]+$row[5];
					$_shuju[$_k][7]=$_shuju[$_k][7]+$row[7];
					$j++;
				}
			}
			sort($_shuju);
			unset($shuju);
			foreach($_shuju as $k=>$row){
				$shuju[$k]=array(1=>$row[1],3=>'淘宝订单',4=>0,5=>'淘宝掌柜',6=>'淘宝掌柜',7=>1,8=>0,9=>$row[4],12=>0,13=>0,14=>$row[5],16=>$row[7],17=>$row[6],19=>$row[7],20=>0,21=>0,25=>$row[3]);
			}
		}
		//每个参数的坐标，下次只需要改这里
		$create_time_key=1;//创建日期
		$click_time_key=2;//点击日期
		$item_title_key=3;//商品信息
		$num_iid_key=4;//商品ID
		$seller_nick_key=5;//掌柜旺旺
		$shop_title_key=6;//所属店铺
		$item_num_key=7;//商品数
		$pay_price_key=8;//商品单价
		$status_key=9;//订单状态
		$fencheng_key=12;//分成比例
		$real_pay_fee_key=13;//实际成交额
		$yugu_key=14;//效果预估
		$commission_key=16;//预估收入（实际佣金）
		$pay_time_key=17;//结算时间
		$commission_rate_key=18;//商品佣金比例
		$commission_yongjin_key=19;//佣金金额
		$commission_rate_butie_key=20;//补贴比率
		$commission_butie_key=21;//补贴金额
		$trade_id_former_key=25;//订单编号
		$media_id_key=27;//媒体id
		$trade_id_click_time_arr=array();
		
		$taopid=dd_get_cache('taopid');
		$media_id_arr=array();
		foreach($taopid as $row){
			$pid=$row['pid'];
			$pid=str_replace('mm_','',$pid);
			$pid=explode('_',$pid);
			$media_id_arr[]=$pid[1];
		}

        foreach ((array)$shuju as $row) {
			
			if((float)$row[$num_iid_key]==0){
				$row[$num_iid_key]=dd_crc32($row[$item_title_key].$row[$pay_price_key]);
			}
            if (!preg_match('/\d+/',$row[$trade_id_former_key]) || strlen($row[$trade_id_former_key])<8) { //订单号不存在的都删掉
                continue;
            }
			if(!in_array($row[$media_id_key],$media_id_arr)){
				//continue;
			}
            unset($arr);
			$arr['status'] = array_search($row[$status_key], $status_arr);

            $arr['create_time'] = $row[$create_time_key];
            if ($row[$pay_time_key]) {
                $arr['pay_time'] = date("Y-m-d H:i:s", strtotime($row[$pay_time_key]));
            }
			$arr['item_title'] = preg_replace('/\'|"/','',$row[$item_title_key]);
			$arr['shop_title'] = preg_replace('/\'|"/','',$row[$shop_title_key]);
			$arr['seller_nick'] = preg_replace('/\'|"/','',$row[$seller_nick_key]);
            $arr['num_iid'] = $row[$num_iid_key];
			//商品ID必须是数字
			if(!is_numeric($arr['num_iid'])){
				jump("-1", "文件格式不对");
			}
            $arr['item_num'] = $row[$item_num_key];
			//商品数必须是数字还是大于0
			if(!is_numeric($arr['item_num'])||$arr['item_num']<=0){
				jump("-1", "文件格式不对");
			}
            $arr['pay_price'] = $row[$pay_price_key];
			//支付价格必须是数字
			if(!is_numeric($arr['pay_price'])){
				jump("-1", "文件格式不对");
			}
            $arr['real_pay_fee'] = $row[$real_pay_fee_key];
			//支付价格必须是数字
			if(!is_numeric($arr['real_pay_fee'])){
				jump("-1", "文件格式不对");
			}
			$trade_id_click_time_arr[$row[$trade_id_former_key]]=$row[$click_time_key];
            if ($row[$commission_rate_key] != '0.00%') {
                $commission_rate = $row[$commission_rate_key];
            } 
			elseif ($row[$commission_rate_butie_key] != '0.00%') {
                $commission_rate = $row[$commission_rate_butie_key];
            }
			else{
				$commission_rate='0.00%';
			}
			
			if($row[$commission_butie_key]==0){
				$_commission_rate_butie_key=sprintf("%01.3f", str_replace('%', '', $row[$commission_rate_butie_key]) / 100);
				$row[$commission_butie_key]=round($row[$real_pay_fee_key]*$_commission_rate_butie_key,2);
			}
			
			$zhiyoubutie=0;
            $arr['commission_rate'] = sprintf("%01.3f", str_replace('%', '', $commission_rate) / 100);
			
			$arr['commission']=0;
			if($arr['status']==5){
				$arr['commission'] =(float)$row[$commission_key];
			}
			else{
				if($row[$yugu_key]>0){
					$arr['commission'] =(float)$row[$yugu_key];
				}
			}
			
			if($arr['commission']==0){
				if($row[$commission_butie_key]>0){
					$zhiyoubutie=1;
					$arr['commission'] =(float)$row[$commission_butie_key];
				}else{
					$arr['commission']=0;
				}
			}

			if((int)$this->webset['taoapi']['butie']==0 && $zhiyoubutie==0 && $arr['commission']>0){  //如果该订单不是只有补贴，那么佣金需要把补贴减去，如果商品佣金只有补贴，那就不减
				$a=$arr['commission']-(float)$row[$commission_butie_key];
				if($a>0){
					$arr['commission']=$a;
				}
			}
			//如果有第三方分成，把分出去的佣金剪掉
			/*$fencheng=$row[$fencheng_key];
			$fencheng=sprintf("%01.3f", str_replace('%', '', $fencheng) / 100);
			$arr['commission']=$arr['commission']*$fencheng;*/
			
            $arr['trade_id_former'] = $row[$trade_id_former_key];
			$arr['mini_trade_id'] = substr($row[$trade_id_former_key],0,8).substr($row[$trade_id_former_key],-4,4);
			$arr['trade_id']=$row[$trade_id_former_key]."_".$arr['num_iid'];
            $arr['qrsj'] = 0;
            $arr['tgyj'] = 0;
            $arr['platform'] = 0;
			$arr['outer_code'] = '';
			$arr['uid'] = 0;
			
			$arr['fxje'] = fenduan($arr['commission'], $this->webset['fxbl'], 0);
            $arr['jifenbao'] = jfb_data_type($arr['fxje'] * TBMONEYBL);
            $arr['jifen'] = (int)($arr['fxje'] * $this->webset['jifenbl']);

			foreach($data_arr as $k=>$shuzu){
				if($arr['num_iid']==$shuzu['num_iid'] && $arr['trade_id']==$shuzu['trade_id']){
					$data_arr[$k]['item_num']+=$arr['item_num'];
					$data_arr[$k]['real_pay_fee']+=$arr['real_pay_fee'];
					$data_arr[$k]['commission']+=$arr['commission'];
					
					$data_arr[$k]['fxje']+=$arr['fxje'];
					$data_arr[$k]['jifenbao']+=$arr['jifenbao'];
					$data_arr[$k]['jifen']+=$arr['jifen'];
					
					$chongfu[] = $data_arr[$k];
					$chongfu_arr=1;
					break;
				}
				else{
					$chongfu_arr=0;
				}
			}

			if($chongfu_arr==0){
				$data_arr[] = $arr;
			}
        }
		$num=0;
		$trade_id_click_time_arr=var_export($trade_id_click_time_arr,1);
		$phpcode='<?php return '.$trade_id_click_time_arr.';?>';
		file_put_contents(DDROOT.'/data/temp/trade_id_click_time_arr.php',$phpcode);
        foreach ((array)$data_arr as $vo) {
			//导入临时表
			$tradedata_id = $this->select('tradelist_temp', 'id', 'trade_id="' . $vo['trade_id'] . '" order by id asc');
			if($tradedata_id){
				$this->update('tradelist_temp', $vo, 'id="' . $tradedata_id. '"');
			}else{
				$vo['addtime']=TIME;
				$id=$this->insert('tradelist_temp', $vo);
				if($id>0){
					$num++;
				}
			}
        }
		$r=array();
		$r['total']=count($data_arr);
		$r['insert_num']=$num;
		unset($shuju);
		unset($data_arr);
        return $r;
    }
	
    function trade_ruku($arr, $result) {
		$aa = $this->select('tradelist', 'id,shop_title', 'mini_trade_id="' . $arr['mini_trade_id'] . '" and shop_title="'.$arr['shop_title'].'" and addtime=0 order by id desc');  //如果是api获取的，停止执行
		if($aa['id']>0){
			return $result;
		}
		$getuid=$this->getregorderuid($arr['trade_id_former']);
		if($getuid>0)
		{
			$arr['uid']=$getuid;
		}
		$tradedata = $this->select('tradelist', '*', 'trade_id="' . $arr['trade_id'] . '" order by id asc');
		if($tradedata['id']>0){ //存在订单
			$arr['id']=$tradedata['id'];
			if($tradedata['addtime']==0){ //是api获取的
				$result['bubian_num']++;
				return $result;
			}
			else{  //是导入的				
				if ($arr['status'] != $tradedata['status'] && $tradedata['status']!=4 && $tradedata['status']!=5){ //状态发生变化
					
					if($tradedata['status']==5&&$arr['status']!=4){
						//订单是失效或者结算状态，不计算
						$result['bubian_num']++;
					}
					else{
						if($tradedata['uid']){
							$arr['uid']=$tradedata['uid'];
						}
						$arr=$this->complete_trade_arr($arr);

						if($arr['status']==5){ //结算订单
							$this->update('tradelist', $arr, 'id="' . $tradedata['id'] . '"');
							$result['update_num']++;
							if($arr['uid']>0){
								$user = $this->user_temp;
								if($arr['uid']>0&&$user['id']>0){
									$this->RegPushInfo($arr['uid'],$arr['trade_id_former'],$arr['status'],$arr['pay_time'],$arr['real_pay_fee'],$arr['item_title'],$arr['item_num'],$arr['fxje'],$arr['jifen'],$arr['create_time']);
									unset($arr['status']);
									$this->rebate($user, $arr, 8); //8号明细，确认淘宝订单
								}
							}
							//$this->RegPushInfo($arr['uid'],$arr['trade_id_former'],$arr['status']);
							
						}
						else{
							$this->update('tradelist', $arr, 'id="' . $tradedata['id'] . '"');
							$result['update_num']++;
							//$this->RegPushInfo($arr['uid'],$arr['trade_id_former'],$arr['status']);
							$this->RegPushInfo($arr['uid'],$arr['trade_id_former'],$arr['status'],$arr['pay_time'],$arr['real_pay_fee'],$arr['item_title'],$arr['item_num'],$arr['fxje'],$arr['jifen'],$arr['create_time']);
						}
					    //$this->RegPushInfo($arr['uid'],$arr['trade_id_former'],$arr['status']);
					}
				}else{
					$result['bubian_num']++;
				}
			}
		}
		else{
			if($arr['status']==4){  //失效订单不入库
				$result['bubian_num']++;
				return $result;
			}
			$arr['addtime'] = TIME;
			
			if($tradedata['uid']){
				$arr['uid']=$tradedata['uid'];
			}
			
			$arr=$this->complete_trade_arr($arr);
			$arr['category_id']=1;  //导入的订单，category_id设置为1
            $insert_id = $this->insert('tradelist', $arr);
			if($insert_id>0){
				$this->trade_temp[$insert_id]=$arr;
				$arr['id']=$insert_id;				
				if($arr['status']==5 && $arr['uid']>0){
					if(empty($this->user_temp) || $arr['uid']!=$this->user_temp['id']){ //理论上这种情况不会发生，为了防止万一
						$user=$this->select('user','*','id="'.$arr['uid'].'"');
					}
					else{
						$user=$this->user_temp;
					}
                	$this->rebate($user, $arr, 8); //8号明细，确认淘宝订单
				}
				//$this->RegPushInfo($arr['uid'],$arr['trade_id_former'],$arr['status']);
				$this->RegPushInfo($arr['uid'],$arr['trade_id_former'],$arr['status'],$arr['pay_time'],$arr['real_pay_fee'],$arr['item_title'],$arr['item_num'],$arr['fxje'],$arr['jifen'],$arr['create_time']);
			}
            $result['insert_num']++;
		}

        return $result;
    }
	
	function complete_trade_arr($arr){
		$trade_id=$arr['trade_id_former'];
		$mini_trade_id=$arr['mini_trade_id'];
		$num_iid=$arr['num_iid'];
		$create_time=$arr['create_time'];
		$item_title=$arr['item_title'];
		$user_id=0;
		if($arr['uid']){
			$user_id=$arr['uid'];
		}else{
			if($this->webset['taoapi']['auto_fanli']==0){
				return $arr;
			}
			
			if(isset($this->trade_id_uid_arr[$trade_id])){
				if($this->trade_id_uid_arr[$trade_id]>0){
					$user_id=$this->trade_id_uid_arr[$trade_id];
				}
			}
			else{
				$a=$this->reg_trade_uid($trade_id,$num_iid,$create_time,$item_title);
				
				if($a['uid']>0){
					
					foreach($this->trade_temp as $k=>$row){
						if(preg_replace('/_\d+$/','',$row['trade_id'])==$trade_id){
							$user = $this->select("user", '*', "id=" . $a['uid']);
							if($user['id']>0){
								$row['fxje']=fenduan($row['commission'], $this->webset['fxbl'], $user['type']);
								$row['jifenbao']=jfb_data_type($row['fxje'] * TBMONEYBL);
								$row['jifen']=(int)($row['fxje'] * $this->webset['jifenbl']);
							}
						
							$this->update('tradelist',array('outer_code'=>$a['uid'],'uid'=>$a['uid'],'checked'=>3,'fxje'=>$row['fxje'],'jifenbao'=>$row['jifenbao'],'jifen'=>$row['jifen']),'id="'.$k.'"');
							if($row['status']==5){
								$row['id']=$k;
								$this->rebate($user, $row, 8); //8号明细，确认淘宝订单
							}
							unset($this->trade_temp[$k]);
						}
					}
					
					$this->trade_id_uid_arr[$trade_id]=$a['uid'];
					$user_id=$a['uid'];
				}
			}
		}
		
		if($user_id>0){
			$arr['outer_code']=$arr['uid']=$user_id;
			if($arr['status']==5){
				$arr['checked']=2;
			}
			else{
				$arr['checked']=3;
			}
			
			$user=$this->select('user','*','id="'.$user_id.'"');
			
			$re=$this->use_fanlibl($user_id,$num_iid,$arr['real_pay_fee'],$create_time);
			if($re>0){
				$arr['fxje']=fenduan($re,$this->webset['fxbl'],$user['type']);
			}
			else{
				$arr['fxje']=fenduan($arr['commission'],$this->webset['fxbl'],$user['type']);
			}

			$arr['jifenbao']=jfb_data_type($arr['fxje'] * TBMONEYBL);
			$arr['jifen']=(int)($arr['fxje']*$this->webset['jifenbl']);
			
			$this->user_temp=$user;
		}
		else{
			$this->user_temp=array();
		}
		return $arr;
	}
	
	function reg_trade_uid($trade_id,$num_iid,$create_time,$item_title){
		$trade_id_click_time_arr=include(DDROOT.'/data/temp/trade_id_click_time_arr.php');
		if(isset($trade_id_click_time_arr[$trade_id]) && $trade_id_click_time_arr[$trade_id]!=''){
			$create_time=$trade_id_click_time_arr[$trade_id];
		}
		if($this->webset['taoapi']['auto_fanli_plan']['trade_uid']==1){
			$trade_uid=substr($trade_id,-4,4);
			$uid=$this->select('trade_uid','uid','trade_uid="'.$trade_uid.'"');
			$uid=preg_replace('/^,/','',$uid);$uid=preg_replace('/,$/','',$uid); //防止出现数据库数据错误，开头和结尾有逗号
			if($this->webset['taoapi']['auto_fanli_plan']['trade_uid_force']==1 && strpos($uid,',')===false){ //如果不包含逗号，说明只有一个会员后四位匹配
				return array('uid'=>$uid,'trade_id'=>$trade_id);
			}
			$where='uid in ('.$uid.')';
		}
		else{
			$where='1';
		}
		$t=(int)$this->webset['taoapi']['auto_fanli_plan']['time'];
		if($t>0){
			$st=date("Y-m-d H:i:s",strtotime($create_time." -".$t." second"));
			$et=date("Y-m-d H:i:s",strtotime($create_time." +".$t." second"));
			$where.=' and day>"'.$st.'" and day<"'.$et.'"';
		}
		if($this->webset['taoapi']['auto_fanli_plan']['equal']==1){
			$where.=' and (iid="'.$num_iid.'" or (keyword<>"" and LOCATE(`keyword` , "'.$item_title.'")))';
		}
			
		$buy_log=$this->select_all('buy_log','*',$where);
		if(!empty($buy_log)){
			$_buy_log=array();
			if($this->webset['taoapi']['auto_fanli_plan']['equal']==1){
				$pre=$this->webset['taoapi']['auto_fanli_plan']['per'];
				$pre=round($pre/100,2);
				foreach($buy_log as $row){
					if($row['iid']!='' && $row['iid']!=0){
						if($row['iid']==$num_iid){
							$_buy_log[$row['uid']]=$row;
						}
					}
					elseif($row['keyword']!=''){
						if(utf8_count($item_title,$row['keyword'])>=$pre){
							$_buy_log[$row['uid']]=$row;
						}
					}
				}
			}
			else{
				$_buy_log=$buy_log;
			}
			if(count($_buy_log)==1){
				$t = array_pop($_buy_log);
				return array('uid'=>$t['uid'],'trade_id'=>$trade_id);
			}
		}
		return array('uid'=>0,'trade_id'=>0);
	}
	//注意查看下
	function trade_uid($uid,$trade_id,$type='add'){
		$trade_id=preg_replace('/_\d+$/','',$trade_id);
		$trade_uid=substr($trade_id,-4,4);
		if($type=='add'){
			if($trade_uid>0 && $uid>0){
				$user=$this->select('user','id,trade_uid','id="'.$uid.'"');
				if($user['trade_uid']==0) $user['trade_uid']='';
				if($user['trade_uid']!=''){
					$trade_uid_arr=explode(',',$user['trade_uid']);
				}
				else{
					$trade_uid_arr=array();
				}
				if(!in_array($trade_uid,$trade_uid_arr)){
					$trade_uid_arr[]=$trade_uid;
					$data=array('trade_uid'=>implode(',',$trade_uid_arr));
					$this->update('user',$data,'id="'.$uid.'"');
				}
				
				$b=$this->select('trade_uid','*','trade_uid="'.$trade_uid.'"');
				if($b['id']>0){
					$uid_arr=explode(',',$b['uid']);
					if(!in_array($uid,$uid_arr)){
						$data=array('uid'=>$b['uid'].','.$uid);
						$this->update('trade_uid',$data,'id="'.$b['id'].'"');
					}
				}
				else{
					$data=array('trade_uid'=>$trade_uid,'uid'=>$uid);
					$this->insert('trade_uid',$data);
				}
			}
		}
		elseif($type=='del'){
			$uids=$this->select('trade_uid','uid','trade_uid="'.$trade_uid.'"');
			if($uids==$uid){
				$this->delete('trade_uid','trade_uid="'.$trade_uid.'"');
				$trade_uid=0;
				
			}
			else{
				$uid_arr=explode(',',$uids);
				$k=array_search($uid,$uid_arr);
				if(isset($k)){
					unset($uid_arr[$k]);
				}
				
				$data=array('uid'=>implode(',',$uid_arr));
				$this->update('trade_uid',$data,'trade_uid="'.$trade_uid.'"');
				
				$user=$this->select('user','id,trade_uid','id="'.$uid.'"');
				$trade_uid_arr=explode(',',$user['trade_uid']);
				$k=array_search($uid,$uid_arr);
				unset($trade_uid_arr[$k]);
				$trade_uid=implode(',',$trade_uid_arr);
			}
			$this->update('user',array('trade_uid'=>$trade_uid),'id="'.$uid.'"');
		}
	}
	
	function dd_refund($arr,$shijian=0,$t=0){//arr数组包含以下信息：uid会员id money金额 jifen积分 jifenbao集分宝 source原因来源  relate_id 订单id  plugin表示数据来源 / $shijian 事件id  /  $t默认0 扣钱  1 加钱
		
		if($t==0){
			$e='-';	
		}elseif($t==1){
			$e='+';	
		}else{
			exit('dd_refund error!');
		}
		$uid=$arr['uid'];
		if($uid>0 && $shijian!='0'){
			//插入明细
			unset($mingxi_arr);
			$mingxi_arr=array('uid'=>$uid,'shijian'=>$shijian,'source'=>$arr['source']);
			//会员数据更新
			unset($user_arr);
			if($arr['money']>0){
				$user_arr[]=array('f'=>'money','e'=>$e,'v'=>$arr['money']);
				$mingxi_arr['money']=$e.$arr['money'];
				$tjr_money1=round($arr['money']*$this->webset['tgbl'],2);
			}
			if($arr['jifen']>0){
				$user_arr[]=array('f'=>'jifen','e'=>$e,'v'=>$arr['jifen']);
				$mingxi_arr['jifen']=$e.$arr['jifen'];
			}
			if($arr['jifenbao']>0){
				$user_arr[]=array('f'=>'jifenbao','e'=>$e,'v'=>$arr['jifenbao']);
				$mingxi_arr['jifenbao']=$e.$arr['jifenbao'];
				$tjr_money2=round(($arr['jifenbao']*$this->webset['tgbl']/TBMONEYBL),2);
			}
			$this->update('user',$user_arr,'id="'.$uid.'"');
			$this->mingxi_insert($mingxi_arr);
			if($t==1){//推荐人奖励
				$tgyj=$tjr_money1+$tjr_money2;
				$user=$this->select('user','id,tjr,ddusername','id="'.$arr['uid'].'"');
				if($user['tjr']>0){
					$this->tgfz($tgyj,$user['tjr'],$user['id'],$user['ddusername'],$arr['relate_id']);
				}
			}
		}
	}
	
	function get_user(){
		$userlogininfo=unserialize(get_cookie('userlogininfo')); 
		$hcookieuid = $userlogininfo['uid']; 
		$hcookiepassword = $userlogininfo['ddpassword']; 
		$hcookiesavetime = $userlogininfo['ddsavetime'];
		if($hcookieuid>0 && $hcookiepassword<>NULL){	
			$user=$this->select('user','*',"id='".$hcookieuid."' and ddpassword='".$hcookiepassword."'");
		}
		else{
			$user['id']=0;
		}
		return $user;
	}
	
	function sign(){
		$webset=$this->webset;
		$dduser=$this->dduser;
		if($dduser['id']==0){
			$dduser=$this->get_user();
		}
		
		if($webset['sign']['open']==0){
		    $re=array('s'=>0,'r'=>'签到未开启');
		    return $re;
		}
		if($dduser['id']==0){
			$re=array('s'=>0,'r'=>'会员未登陆');
		    return $re;
		}
		
		include_once(DDROOT.'/comm/php_lock.class.php');
		$phplock=new phplock('sign_'.$dduser['id']);
		if($phplock->status==0){
			$re=array('s'=>0,'r'=>'请求过多，请稍后再试');
		    return $re;
		}
		
		$todaytime=strtotime(date('Y-m-d 00:00:00'));
		$webset['sign']['money']=(float)$webset['sign']['money'];
		$webset['sign']['jifenbao']=(float)$webset['sign']['jifenbao'];
		$webset['sign']['jifen']=(float)$webset['sign']['jifen'];
		$dduser['signtime']=$this->select('user','signtime','id="'.$dduser['id'].'"');
		if($dduser['signtime']<$todaytime){
		    $data=array(array('f'=>'money','e'=>'+','v'=>$webset['sign']['money']),array('f'=>'jifenbao','e'=>'+','v'=>$webset['sign']['jifenbao']),array('f'=>'jifen','e'=>'+','v'=>$webset['sign']['jifen']),array('f'=>'signtime','e'=>'=','v'=>time()),array('f'=>'signnum','e'=>'+','v'=>1));
		    $this->update('user',$data,'id="'.$dduser['id'].'"');
			$data=array('uid'=>$dduser['id'],'shijian'=>4,'money'=>$webset['sign']['money'],'jifenbao'=>$webset['sign']['jifenbao'],'jifen'=>$webset['sign']['jifen']);
		    $this->mingxi_insert($data);
		    $re=array('s'=>1,'r'=>array('money'=>$webset['sign']['money'],'jifenbao'=>$webset['sign']['jifenbao'],'jifen'=>$webset['sign']['jifen'],'signtime'=>time()));
		    return $re;
		}
		else{
			$re=array('s'=>0,'r'=>'今天已签到');
		    return $re;
		}
	}
	
	function lianxu_sign($open,$sign_jifenbao){
		$duoduo=$this;
		$dduser=$this->dduser;
		$webset=$duoduo->webset;
		$uid=$dduser['id'];
		
		if($open==1){
			$sign_arr=$sign_jifenbao;//7次签到奖励
			if($dduser['signnum']>=7){
				$dduser['signnum']=0;
			}

			$sign=$sign_arr[$dduser['signnum']]; //本次签到奖励
			$signday=date('Y-m-d',$dduser["signtime"]);
			$today=date('Y-m-d');
			$yesterday=date("Y-m-d",strtotime("-1 day"));
			if($signday == $today){//今天已签到
				$json_data=array('s'=>1,'r'=>'您今天已签到！请明天继续，明天签到可获得 '.$sign.' '.TBMONEY,'signnum'=>$dduser['signnum']);
			}
			else{
				if($yesterday>$signday){
					$sign=$sign_arr[0];
					$dduser['signnum']=0;
				}
				$data=array(array('f'=>'jifenbao','e'=>'+','v'=>$sign),array('f'=>'signtime','e'=>'=','v'=>TIME),array('f'=>'signnum','e'=>'=','v'=>$dduser['signnum']+1));
				$duoduo->update('user',$data,'id="'.$dduser['id'].'"');
				$data=array('uid'=>$dduser['id'],'shijian'=>4,'jifenbao'=>$sign);
				$duoduo->mingxi_insert($data);
				$json_data=array('s'=>1,'r'=>'成功签到！获得 '.$sign.' '.TBMONEY,'signnum'=>$dduser['signnum']+1);	
			}
			return $json_data;
		}
		else{
			$webset=$duoduo->webset;
		
	    	if($webset['sign']['open']==1){
				$todaytime=strtotime(date('Y-m-d 00:00:00'))+$webset['corrent_time'];
				$webset['sign']['money']=(float)$webset['sign']['money'];
				$webset['sign']['jifenbao']=(float)$webset['sign']['jifenbao'];
				$webset['sign']['jifen']=(float)$webset['sign']['jifen'];
				if($dduser['signtime']<$todaytime){
					$str='';
					if($webset['sign']['money']>0){
						$str.=' '.$webset['sign']['money'].'元';
					}
					if($webset['sign']['jifenbao']>0){
						$str.=' '.$webset['sign']['jifenbao'].TBMONEY;
					}
					if($webset['sign']['jifen']>0){
						$str.=' '.$webset['sign']['jifen'].'积分';
					}
				    $data=array(array('f'=>'money','e'=>'+','v'=>$webset['sign']['money']),array('f'=>'jifenbao','e'=>'+','v'=>$webset['sign']['jifenbao']),array('f'=>'jifen','e'=>'+','v'=>$webset['sign']['jifen']),array('f'=>'signtime','e'=>'=','v'=>TIME));
				    $duoduo->update('user',$data,'id="'.$dduser['id'].'"');
					$data=array('uid'=>$dduser['id'],'shijian'=>4,'money'=>$webset['sign']['money'],'jifenbao'=>$webset['sign']['jifenbao'],'jifen'=>$webset['sign']['jifen']);
				    $duoduo->mingxi_insert($data);
				    $json_data=array('s'=>1,'r'=>'签到奖励'.$str);
				}
				else{
					$json_data=array('s'=>1,'r'=>'您今天已签到');
				}
			}
			else{
				$json_data=array('s'=>1,'r'=>'签到已关闭');
			}
			return $json_data;
		}
	}
	
	function buy_log($param){
		if($this->webset['taoapi']['auto_fanli']==0){
			return false;
		}
		$buy_log_data['iid']=$param['iid']?$param['iid']:0;
		$buy_log_data['uid']=$param['uid']?$param['uid']:$this->dduser['id'];
		$buy_log_data['day']=date('Y-m-d H:i:s');
		$buy_log_data['keyword']=$param['keyword']?$param['keyword']:'';
		
		//10秒内重复的数据不让提交
		if($buy_log_data['uid']>0){
			if($buy_log_data['iid']>0){
				$where=' and iid="'.$buy_log_data['iid'].'"';
			}
			else{
				$where=' and keyword="'.$buy_log_data['keyword'].'"';
			}
			$buy_log_id=(int)$this->select('buy_log','id','uid="'.$buy_log_data['uid'].'" '.$where.' and day>"'.date("Y-m-d H:i:s",strtotime("-10 sec")).'"');
			
			if($buy_log_id==0 && ($buy_log_data['iid']>0 || $buy_log_data['keyword']!='')){
				$this->insert('buy_log',$buy_log_data);
			}
		}
	}
	
	function login($device='pc'){
		$webset=$this->webset;
		$ip=$_SERVER['REMOTE_ADDR'];
		$show_yzm=login_error('check');
		
		if (isset($_POST['sub']) && $_POST['sub'] != '') {
			if (limit_ip('user_limit_ip')) {
				jump(-1, '您的IP禁止登陆');
			} 
		
			$username = trim($_POST['username']);
			$password = trim($_POST['password']);
			$remember = trim($_POST['remember'])?trim((int)$_POST['remember']):0;
			$md5pwd = md5($password);
			$from = trim($_POST['from']);
		
			$errorid = 0;
			
			if($show_yzm==1){
				if($device=='app'){
				
				}
				else{
					$captcha = trim($_POST['captcha']);
					if (reg_captcha($captcha) == 0) {
						jump(u('user','login'), '验证码错误');
					}
				}
			}
		
			if ($webset['ucenter']['open'] == 1) {
				include DDROOT . '/comm/uc_define.php';
				include_once DDROOT . '/uc_client/client.php';
				$uc_name = iconv("utf-8", "utf-8//IGNORE", $username);
				list ($ucid, $uc_name, $pwd, $email) = uc_user_login($uc_name, $password); //第一次查询用户名
				
				if ($ucid == -1) { // 如果失败在查询邮箱
					list ($ucid, $uc_name, $pwd, $email) = uc_user_login($username, $password, 2);
				} 
				if ($ucid > 0) {
					$duser = $this -> select('user','*', 'ddusername="' . $username . '" or email="' . $username . '" and del=0');
					$id = $duser['id'];
					if (!$id) { // 不存在就插入多多
						$info['ddusername'] = $username;
						$info['ddpassword'] = $md5pwd;
						$info['email'] = $email;
						$info['regtime'] = SJ;
						$info['regip'] = get_client_ip();
						$info['lastlogintime'] = SJ;
						$info['loginnum'] = 1;
						$info['money'] = (float)$webset['user']['reg_money'];
						$info['jifen'] = (int)$webset['user']['reg_jifen'];
						$info['jifenbao'] = (float)$webset['user']['reg_jifenbao'];
						$info['ddpassword'] = $md5pwd;
						$info['tjr'] = 0;
						$info['ucid'] = $ucid;
						$info['jihuo'] = 1;
		
						$uid = $this -> insert('user', $info, 0); //插入会员
						if ($uid <= 0) {
							echo '插入会员失败' . mysql_error();
							exit;
						} 
		
						if ($webset['user']['reg_money'] > 0 || $webset['user']['reg_jifen'] > 0) { // 注册送大于0时，发送明细和站内信
							unset ($info);
							$info['uid'] = $uid;
							$info['shijian'] = 1;
							$info['money'] = $webset['user']['reg_money'];
							$info['jifen'] = $webset['user']['reg_jifen'];
							$info['jifenbao'] = $webset['user']['reg_jifenbao'];
							$mingxi_id = $this -> mingxi_insert($info);
							if (!$mingxi_id) {
								echo '插入明细失败';
								exit;
							} 
						} 
		
						$tg = $webset['tgbl'];
						unset($data);
						$data['uid'] = $uid;
						$data['username'] = $username;
						$data['tg'] = $tg;
						$data['webnick'] = $webset['webnick'];
						$data['email'] = $email;
						$this -> msg_insert($data, 1); //1号站内信
					} elseif ($duser['ddpassword'] != md5($password)) { // 存在该会员，更新密码
						$data = array('ddpassword' => md5($password));
						$this -> update('user', $data, 'id="' . $id . '"');
					}
				} else {
					login_error('insert');
					jump(-1, '账号密码错误');
				} 
			} 
		
			$shield_arr = dd_get_cache('no_words'); //屏蔽词语
			
			$username_pass = reg_name($username, 3, 30, $shield_arr);
			if ($username_pass == -1) {
				jump(-1, '用户名不合法');
			} elseif ($username_pass == -2) {
				jump(-1, '包含非法词汇');
			} 
		
			$password_pass = reg_password($password);
			if ($password_pass == 0) {
				jump(-1, '密码位数错误');
			} 
		
			$dduser = $this -> select('user', 'id,ddusername,email,jihuo', "(ddusername='" . $username . "' or email='" . $username . "') and ddpassword='" . $md5pwd . "' and del=0");
			$uid = $dduser['id'];
			if ($uid > 0) { // 如果会员存在
				$id = $dduser['id'];
				$username = $dduser['ddusername'];
				$email = $dduser['email'];
				$jihuo = $dduser['jihuo'];
				if ($jihuo == 0 && $webset['user']['jihuo']==1) {
					jump(u('user', 'jihuo', array('uid' => $id)), '您的账号需要激活！');
				} 
		
				if ($remember == 1) {
					$life = 3600 * 24 * 100;
				} else {
					$life = '';
				} 
				user_login($uid, $md5pwd, $life); //登陆状态
				$set_con_arr = array(array('f' => 'ddpassword', 'v' => $md5pwd), array('f' => 'lastlogintime', 'v' => SJ), array('f' => 'loginnum', 'e' => '+', 'v' => 1), array('f' => 'logip', 'e' => '=', 'v' => get_client_ip()));
				$this -> update('user', $set_con_arr, 'id="' . $uid . '"');
				if ($webset['ucenter']['open'] == 1 && $ucid > 0 && AJAX == 0) {
					echo $ucsynlogin = uc_user_synlogin($ucid); //同步登陆代码
				}
				if ($from != '') {
					$goto = $from;
				} else {
					$goto = u('user', 'index');
				} 
				if (strpos($goto, 'http') === false) {
					$goto = SITEURL . '/' . $goto;
				} 
		
				if ($webset['phpwind']['open'] == 1 && AJAX == 0) {
					$user['id'] = $uid;
					$user['name'] = $username;
					$user['password'] = $md5pwd;
					$user['email'] = $email;
					$user['cookietime'] = $life;
					$goto = $this -> phpwind($user, $goto);
				}
				jump($goto);
			} else {
				login_error('insert');
				jump(-1, '账号密码错误');
			} 
		} 
		else {
			if (isset($_GET['url'])) {
				$url_from = $_GET['url'];
			} 
			elseif (isset($_GET['forward'])) {
				$url_from = $_GET['forward'];
			} 
			elseif (isset($_GET['from'])) {
				$url_from = $_GET['from'];
			} 
			else {
				$url_from = $_SERVER['HTTP_REFERER'];
			}
			$parameter['url_from']=$url_from;
			$parameter['show_yzm']=$show_yzm;
			return $parameter;
		}
	}
	
	function register($device='pc'){
		$webset=$this->webset;
		$duoduo=$this;
		if ($webset['yinxiangma']['open'] == 1 && $device=='pc') {
			include (DDROOT . "/api/YinXiangMaLib.php"); //印象验证码
			$yinxiangma = YinXiangMa_GetYinXiangMaWidget();
		}
	
		if (array_key_exists('web', $_POST)) {
			$webname = $_POST['webname'];
			$webid = $_POST['webid'];
			if(strlen($webid)>25){
				$webid = authcode($webid, 'DECODE', DDKEY);
			}
			$web = $_POST['web'];
		} else {
			$webname = '';
			$webid = '';
			$web = '';
		}

		if (isset ($_POST['username']) && $_POST['username'] != '') {
			unset ($_POST['sub']);
			$tjr = (int) get_cookie("tjr");
			$regtype = (int)$_POST['regtype'];
			unset($_POST['regtype']);
			$captcha = trim($_POST['captcha']);
			$from = trim($_POST['from']);
			$username = trim($_POST['username']);
			$password = trim($_POST['password']);
			$md5pwd = md5($password);
			$email = trim($_POST['email']);
			$qq = trim($_POST['qq']);
			$alipay = trim($_POST['alipay']);
			$tjrname = trim($_POST['tjrname']);
			$tbnick = trim($_POST['tbnick']);
			$ip = get_client_ip();
			if($regtype==1){
				$yzm=$_POST['mobile_jym'];
				unset($_POST['mobile_jym']);
				if($yzm==''){
					jump(-1, '短信验证码不能为空');
				}
				$yzm_file_name=dd_crc32(DDKEY).$username;
				$session_file=DDROOT.'/data/temp/session/'.date('Ymd').'/'.$yzm_file_name.'.yzm';
				if(!file_exists($session_file)){
					jump(-1, '短信验证码不存在');
				}
				else{
					$yzm_f=file_get_contents($session_file);
					$yzm_arr=explode('|',$yzm_f);
					unlink($session_file);
					if(TIME-$yzm_arr[1]>10*60 || $yzm_arr[0]!=$yzm){ //验证码5分钟过期
						jump(-1, '短信验证码错误');
					}
				}
			}
			if($webset['user']['need_tbnick']==1 && $tbnick==''&&TAOTYPE==1 && $webid==''){
				jump(-1,'请填写淘宝账号');
			}
			
			if($tjrname!=''){
				$tjrid=$duoduo->select('user','id','ddusername="'.$tjrname.'" or email="'.$tjrname.'"');
				if($tjrid>0){
					$tjr = $tjrid;
				}
				else{
					jump(-1,'推荐人ID错误');
				}
			}
			elseif ($tjr > 0) {
				$tjr = (float)$duoduo->select('user', 'id', 'id="' . $tjr . '"');
			}

			if (limit_ip('user_limit_ip', $ip)) {
				jump(-1, '禁用IP');
			}
	
			$shield_arr = dd_get_cache('no_words'); //屏蔽词语
			if ($webid =='') {
				if($webset['yinxiangma']['open']==1 && $device=='pc'){
					$YinXiangMa_response = YinXiangMa_ValidResult($_POST['YinXiangMa_challenge'], $_POST['YXM_level'][0], $_POST['YXM_input_result']);
					if ($YinXiangMa_response != "true") {
						jump(-1, '验证码错误');
					}
				}
				else{
					if (reg_captcha($captcha) == 0) {
						jump(-1, '验证码错误');
					}
				}
			}
	
			$username_pass = reg_name($username, 3, 15, $shield_arr);
			if ($username_pass == -1) {
				jump(-1, '用户名不合法');
			}
			elseif ($username_pass == -2) {
				jump(-1, '包含非法词汇');
			}
			elseif ($duoduo->check_user($username) == 'false') {
				jump(-1, '用户名已存在');
			}
	
			$password_pass = reg_password($password);
			if ($password_pass == 0) {
				jump(-1, '密码位数错误');
			}
	
			$email_pass = reg_email($email);
			if ($email_pass == 0) {
				jump(-1, '邮箱格式错误');
			}
			elseif ($duoduo->check_email($email) == 'false') {
				jump(-1, '邮箱已存在');
			}
	
			if ($webset['user']['need_qq'] == 1 && $webid == '') {
				$qq_pass = reg_qq($qq);
				if ($qq_pass == 0) {
					jump(-1, 'QQ格式错误');
				}
			}
	
			if ($webset['user']['need_alipay'] == 1 && $webid == '') {
				$alipay_pass = reg_alipay($alipay);
				if ($alipay_pass == 0) {
					jump(-1, '支付宝格式错误');
				}
				elseif ($duoduo->check_alipay($alipay) == 'false') {
					jump(-1, '支付宝已存在');
				}
			}
	
			if ($webset['user']['reg_between'] > 0) {
				$regtime = $duoduo->select('user', 'regtime', 'regip="' . $ip . '" order by id desc');
				$regtime = strtotime($regtime);
				if ($regtime > 0 && TIME - $regtime < $webset['user']['reg_between'] * 3600) {
					jump(-1, '注册受限');
				}
			}
	
			if ($webset['ucenter']['open'] == 1) {
				include DDROOT . '/comm/uc_define.php';
				include_once DDROOT . '/uc_client/client.php';
				$uc_name = iconv("utf-8", "utf-8//IGNORE", $username);
				$ucid = uc_user_register($uc_name, $password, $email);
	
				if ($ucid == -1) {
					jump(-1, '用户名不合法');
				}
				elseif ($ucid == -2) {
					jump(-1, '包含非法词汇');
				}
				elseif ($ucid == -3) {
					jump(-1, '用户名已存在');
				}
				elseif ($ucid == -4) {
					jump(-1, '邮箱格式错误');
				}
				elseif ($ucid == -5) {
					jump(-1, '邮箱格式错误');
				}
				elseif ($ucid == -6) {
					jump(-1, '邮箱已存在');
				}
				elseif ($ucid <= 0) {
					jump(-1, '邮箱已存在');
				}
			} else {
				$ucid = 0;
			}
	
			$info = arr_diff($_POST, array (
				'sub',
				'captcha',
				'from',
				'agree',
				'password_confirm',
				'password',
				'username',
				'web',
				'webid',
				'webname',
				'YinXiangMa_response',
				'YinXiangMa_challenge',
				'YXM_level',
				'YXM_input_result',
				'YinXiangMa_pk',
				'tjrname',
				'mod',
				'act',
				'apireg',
				'callback',
				'_',
				'mobile_jym',
			));
			$info['regtime'] = SJ;
			$info['regip'] = $ip;
			$info['lastlogintime'] = SJ;
			$info['loginnum'] = 1;
			$info['money'] = (float) $webset['user']['reg_money'];
			$info['jifenbao'] = (float) $webset['user']['reg_jifenbao'];
			$info['jifen'] = (int) $webset['user']['reg_jifen'];
			$info['level'] = (int) $webset['user']['reg_level'];
			$info['ddpassword'] = $md5pwd;
			$info['ddusername'] = $username;
			$info['tjr'] = $tjr;
			$info['ucid'] = $ucid;
			if($regtype==1){
				$info['mobile'] = $username;
				$info['mobile_test'] = 1;
			}
			if ($webset['user']['jihuo'] == 1) { //如果需要激活，会员初始的激活状态为0
				$info['jihuo'] = 0;
			} else {
				$info['jihuo'] = 1;
			}
	
			$uid = $duoduo->insert('user', $info, 0); //插入会员
			if ($uid <= 0) {
				exit (mysql_error() . '插入会员失败');
			}
	
			if ($web != '') {
				$data = array (
					'webid' => $webid,
					'webname' => $webname,
					'web' => $web,
					'uid' => $uid
				);
				$duoduo->insert('apilogin', $data, 0);
			}
			
			if($webset['user']['need_tbnick']==1 && $tbnick!=''&&TAOTYPE==1){
				$trade_uid=get_4_tradeid($tbnick);
				if($trade_uid[0]>0){
					$duoduo->trade_uid($uid,$trade_uid[0]);
				}
			}
			
			
			$tg = $webset['tgbl'];
			if ($webset['user']['jihuo'] == 0) { //如果需要激活，会员初始的激活状态为0
				unset ($data);
				$data['uid'] = $uid;
				$data['username'] = $username;
				$data['tg'] = $tg;
				$data['webnick'] = WEBNAME;
				$data['email'] = $email;
				$msg_zhuce = $duoduo->msg_insert($data, 1); //1号站内信
			}
	
			if ($webset['user']['reg_money'] > 0 || $webset['user']['reg_jifen'] > 0 || $webset['user']['reg_jifenbao'] > 0) { //注册送大于0时，发送明细
				unset ($info);
				$info['uid'] = $uid;
				$info['shijian'] = 1;
				$info['money'] = (float) $webset['user']['reg_money'];
				$info['jifenbao'] = (float) $webset['user']['reg_jifenbao'];
				$info['jifen'] = (int) $webset['user']['reg_jifen'];
				$mingxi_id = $duoduo->mingxi_insert($info);
				if (!$mingxi_id) {
					echo '插入明细失败';
					exit;
				}
			}
	
			if ($webset['user']['jihuo'] == 1) {
				jump(u('user', 'jihuo', array (
					'uid' => $uid
				)));
			}
	
			user_login($uid, $md5pwd);
			if ($from != '') {
				$goto = $from;
			} else {
				$goto = u('user', 'index');
			}
			if (strpos($goto, 'http://') === false) {
				$goto = SITEURL . '/' . $goto;
			}
			if ($webset['phpwind']['open'] == 1) {
				$user['id'] = $uid;
				$user['name'] = $username;
				$user['password'] = $password;
				$user['email'] = $email;
				$user['cookietime'] = 1200;
				$goto = $duoduo->phpwind($user, $goto);
			}
			if ($webset['ucenter']['open'] == 1 && $ucid > 0 && AJAX == 0) {
				echo $ucsynlogin = uc_user_synlogin($ucid); //同步登陆代码
			}
			
			/*dd_session_start();
			if($_SESSION["avatar"]){
				function make_avatar_path($uid, $dir = '.') {
					$uid = sprintf("%010d", $uid);
					$dir1 = substr($uid, 0, 4);
					$dir2 = substr($uid, 4, 4);
					//$dir3 = substr($uid, 5, 2);
					!is_dir($dir.'/'.$dir1) && mkdir($dir.'/'.$dir1, 0777);
					!is_dir($dir.'/'.$dir1.'/'.$dir2) && mkdir($dir.'/'.$dir1.'/'.$dir2, 0777);
					return $dir.'/'.$dir1.'/'.$dir2.'/';
				}
				$save_pic_dir=make_avatar_path($uid,DDROOT.'/upload/avatar');
				include(DDROOT.'/comm/thumb.class.php');
				$avatar_data=file_get_contents($_SESSION["avatar"]);
				$picname=$save_pic_dir.'a'.$uid;
				file_put_contents($picname,$avatar_data);
				$picname=change_img($picname,'jpg');
		
				$uid = sprintf("%02d", $uid);
				$avatar_id=substr($uid, -2);
				
				$t = new ThumbHandler();
				$t->setSrcImg($picname);
				$t->setCutType(1);
				$t->setDstImg($save_pic_dir.$avatar_id."_avatar_small.jpg");
				$t->createImg(48,48);
				
				copy($picname,$save_pic_dir.$avatar_id."_avatar_middle.jpg");
				rename($picname,$save_pic_dir.$avatar_id."_avatar_big.jpg");
	
				unset($_SESSION["avatar"]);
			}*/
			if($device=='pc' || AJAX==1){
				jump($goto);
			}
			else{
				return array('s'=>1,'r'=>$uid);
			}
		} 
		else {
			/**
			* @name 用户注册
			* @copyright duoduo123.com
			* @example 示例user_register();
			* @param $field 字段
			* @return $parameter 结果集合
			*/
			if($device=='pc'){
				$apps = $duoduo->select_all('api', 'title,code', 'open=1 order by sort desc');
			}
	
			if (isset ($_GET['url'])) {
				$url_from = $_GET['url'];
			}
			elseif (isset ($_GET['forward'])) {
				$url_from = $_GET['forward'];
			}
			elseif (isset ($_GET['from'])) {
				$url_from = $_GET['from'];
			}
			else {
				$url_from = $_SERVER['HTTP_REFERER'];
			}
	
			if ($webname != '') {
				$apireg = authcode($_POST['apireg'], 'DECODE', DDKEY);
				if ($apireg != 1 && $device=='pc') {
					error_html('参数错误！');
				}
				$default_name = $webname;
				if ($webset['user']['autoreg'] == 1) {
					$default_email = $webid . '@' . $web . '.com';
					$default_qq = 11111;
				}
				$default_pwd = dd_crc32(DDKEY . $webid);
				$default_pwd2 = $default_pwd;
			} else {
				$default_name = '';
				$default_pwd = '';
				$default_pwd2 = '';
				$default_email = '';
				$default_qq = '';
			}
	
			if ($webset['user']['autoreg'] == 1 && $web != '') {
				$auto_submit = 1;
				dd_session_start();
			} else {
				$auto_submit = 0;
			}
	
			if (count($apps) > 0) {
				$app_show = 1;
			} else {
				$app_show = 0;
			}
			unset($duoduo);
			$parameter['apps'] = $apps;
			$parameter['url_from'] = $url_from;
			$parameter['default_name'] = $default_name;
			$parameter['default_pwd'] = $default_pwd;
			$parameter['default_pwd2'] = $default_pwd2;
			$parameter['default_email'] = $default_email;
			$parameter['default_qq'] = $default_qq;
			$parameter['auto_submit'] = $auto_submit;
			$parameter['yinxiangma'] = $yinxiangma;
			$parameter['url_from'] = $url_from;
			$parameter['web'] = $web;
			$parameter['webid'] = $webid;
			$parameter['webname'] = $webname;
			$parameter['app_show'] = $app_show;
			$parameter['apps'] = $apps;
			$parameter['webset'] = $webset;
			return $parameter;
		}
	}
	function level_update($user){
		if(!is_array($user) && (int)$user>0){
			$user=$this->select('user','id,level,type','id="'.$user.'"');
		}
		$uid=$user['id'];
		if($uid==0){
			return 0;
		}
		$level_type=$this->webset['level'];
		$m=count($level_type)-1;
		for($i=$m;$i>=0;$i--){
			if($user['type']<$i && $user['level']>=$level_type[$i]['level']){
				$l_data=array('type'=>$i);
				$this->update('user',$l_data,'id="'.$uid.'"');
				return 1;
			}
		}
	}
}
?>
