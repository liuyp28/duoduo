<?php
	function sayii_tjrUserList($uid, $tjr=0){
		global $duoduo;
		if($tjr > 0 && $tjr < $uid){
			$tjr_info = array();
			$tjr_list = array();
			$user_list = $duoduo->select('plugin_hn_tjr_user', 'previd_1, previd_2, previd_3, previd_4, previd_5, previd_6, previd_7, previd_8, previd_9', 'id='.$tjr);
			$tjr_info['id'] = $tjr_list['previd_1'] = $uid;
			if(count($user_list) > 0){
				$tjr_info['previd_1'] = $tjr_list['previd_2'] = $tjr;
				for($i=1; $i<=9; $i++){
					$tjr_info['previd_'.($i+1)] = $tjr_list['previd_'.($i+2)] = $user_list['previd_'.$i];
				}
			}else{
				$tjr_info['previd_1'] = $tjr_list['previd_2'] = $tjr;
			}
			$user_id = $duoduo->select('plugin_hn_tjr_user', 'id', 'id='.$uid);
			if($user_id > 0){
				$duoduo->update('plugin_hn_tjr_user', $tjr_info, 'id='.$uid);
				for($i=1; $i<=10; $i++){
					$new_tjr_list = array();
					for($j=0; $j<(11-$i); $j++){
						$new_tjr_list['previd_'.($i+$j)] = $tjr_list['previd_'.($j+1)];
					}
					$next_user_list = $duoduo->select_all('plugin_hn_tjr_user', 'id', 'previd_'.$i.'="'.$uid.'"');
					foreach($next_user_list as $k=>$row){
						$duoduo->update('plugin_hn_tjr_user', $new_tjr_list, 'id="'.$row['id'].'"');
					}
				}
			}else{
				$duoduo->insert('plugin_hn_tjr_user', $tjr_info);
			}
		}
	}
	function sayii_tjr_list($uid, $tjr=0){
		global $duoduo;
		$tjr_list = array();
		if($tjr > 0 && $tjr < $uid){
			$list_array = sayii_tjruserselect($tjr, $i=1);
			$tjr_list['id'] = $next_child_list['previd_1'] = $uid;
			$tjr_list['previd_1'] = $next_child_list['previd_2'] = $tjr;
			for($i=0; $i<9; $i++){
				if($i < count($list_array)){
					$tjr_list['previd_'.($i+2)] = $next_child_list['previd_'.($i+3)] = $list_array[$i];
				}else{
					$tjr_list['previd_'.($i+2)] = $next_child_list['previd_'.($i+3)] = 0;
				}
			}
			//$duoduo->insert('plugin_hn_tjr_user', $tjr_list);
			$user_id = $duoduo->select('plugin_hn_tjr_user', 'id', 'id='.$uid);
			if($user_id > 0){
				$duoduo->update('plugin_hn_tjr_user', $tjr_list, 'id="'.$uid.'"');
			}else{
				$duoduo->insert('plugin_hn_tjr_user', $tjr_list);
			}
			for($i=1; $i<=10; $i++){
				$new_tjr_list = array();
				for($j=0; $j<(11-$i); $j++){
					$new_tjr_list['previd_'.($i+$j)] = $next_child_list['previd_'.($j+1)];
				}
				$next_user_list = $duoduo->select_all('plugin_hn_tjr_user', 'id', 'previd_'.$i.'="'.$uid.'"');
				foreach($next_user_list as $k=>$row){
					$duoduo->update('plugin_hn_tjr_user', $new_tjr_list, 'id="'.$row['id'].'"');
				}
			}
		}
	}
	function sayii_tjruserselect($tjr, $i){
		global $duoduo;
		static $list_array = array();
		$user_id = $duoduo->select('user', 'tjr', 'id='.$tjr);
		if($i < 10 && $user_id != 0){
			$list_array[] = $user_id;
			$i++;
			sayii_tjruserselect($user_id, $i);
		}
		return $list_array;
	}
	//消息极光推送
	function sayii_app_msg_insert($data, $msgset_id=0, $msgset=''){
		global $duoduo;
		$user=$duoduo->select('user','*','id="'.$data['uid'].'"');
		foreach($user as $k=>$v){
			if(!isset($data[$k])){
				$data[$k]=$v;
			}
		}
		$send_app = 0;
		if($msgset_id>0 || !empty($msgset)){
			if($msgset_id>0){
				$msgset = $duoduo->select('msgset', '*', 'id='.$msgset_id);
				//$msgset=$m[$msgset_id];
			}
			$title = $msgset['title'];
			$app_web_content = $msgset['app_web'];
			$app_open = $msgset['app_open'];
			if($app_web_content!='' && $data['uid']>0 && $app_open==1){
				preg_match_all('/\{(.*?)\}/', $app_web_content, $arr);
				foreach($arr[0] as $k=>$v){
					$app_web_content = str_replace($v,$data[$arr[1][$k]],$app_web_content);
				}
				$send_app = 1;
			}
		}else{
			$title = $data['title'];
			if($data['content']!=''){
				$app_web_content = $data['content'];
				$send_app = 1;
			}else{
				return 0;
			}
		}
		
		if($send_app == 1){
			$field_arr['title'] = $title;
			$field_arr['content'] = $app_web_content;
			$field_arr['addtime'] = time();
			$field_arr['see'] = 0;
			$field_arr['uid'] = $data['uid'];
			$field_arr['senduser'] = 0;
			$field_arr['sys'] = $data['sys'];
			$field_arr['sys_name'] = $data['sys_name'];
			$id = $duoduo->insert('plugin_hn_app_msg', $field_arr);
			if($id > 0){
				$re_status[0] = 1;
				$app_key = '86facb7c00a621f92640d5f5';
				$master_secret = '79fd341be59764142debda99';
				if(!class_exists('Client')){
					include_once(DDROOT.'/jpush-api/src/JPush/Client.php');
					$client = new Client($app_key, $master_secret);
				}
				$msg_data_see = array('sys_sms_see', 'my_sms_see', 'fanli_sms_see');
				try{
					if(!isset($client)){
						include_once(DDROOT.'/jpush-api/src/JPush/Client.php');
						$client = new Client($app_key, $master_secret);
					}
					$client->push()
						->setPlatform('all')
						->addAlias('133436')
						//->addRegistrationId('121c83f7602126f2aec')
						->setNotificationAlert($app_web_content)
						->iosNotification($app_web_content, array(
							'extras' => array(
								'type' => $data['sys'],
							),
						))
						->androidNotification($app_web_content, array(
							'extras' => array(
								'type' => $data['sys'],
							),
						))
						->send();
				} catch(\Exception $e){
				//
				}
			}
		}
		return $app_web_content;
	}
?>
