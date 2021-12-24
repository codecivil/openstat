<?php
//returns raw result
//logs if $_SESSION['log'] is set and true (in order to avoid changing all execute_stmts), or if parameter $log is set and true
function _execute_stmt(array $stmt_array, mysqli $conn, bool $log = false)
{
	global $debugpath, $debugfilename;
	if ( isset($_SESSION['DEBUG']) AND $_SESSION['DEBUG'] ) { file_put_contents($GLOBALS["debugpath"].$GLOBALS["debugfilename"],'STATEMENT: '.json_encode($stmt_array).PHP_EOL,FILE_APPEND); }
	$stmt = ''; $str_types = ''; $arr_values = ''; $message = '';
	if (isset($stmt_array['stmt']) ) { $stmt = $stmt_array['stmt']; };
	if (isset($stmt_array['str_types']) ) { $str_types = $stmt_array['str_types']; };
	if (isset($stmt_array['arr_values']) ) {  $arr_values = $stmt_array['arr_values']; };
	if (isset($stmt_array['message']) ) { $message = $stmt_array['message']; }
	$dbMessage = ''; $dbMessageGood = '';
	//often saving of new entries is rejected; I think this is due to an overload of the mariadb server; so, cynic who I am, let's try it more often...
	//this is due to a mariadb crash (caused by RAND probably); waiting for more than 10 seconds actually works... (but need to find a solution for the login,
	//which takes 2 min in this setting...
	$tries = 0; $maxtries = 1; //$maxtries is reduced here to 1 because we have now the tries in callFunction.php where it is better controlled.  
	while( $tries < $maxtries AND !$statement = $conn->prepare($stmt) ) { $tries++; }
	if ( $tries == $maxtries ) { $dbMessage = "Verbindung war nicht erfolgreich. "; $dbMessageGood = "false"; }
	else {
		$tries = 0;
		while ( $tries < $maxtries  AND $str_types != '' AND !$statement->bind_param($str_types, ...$arr_values) ) { $tries++; /*$statement = $conn->prepare($stmt);*/ }
		if ( $tries == $maxtries ) { $dbMessage = "Übertragung war nicht erfolgreich. "; $dbMessageGood = "false"; }
		else {
			$tries = 0;
			while ( $tries < $maxtries AND !$statement->execute() ) { $tries++; /* $statement = $conn->prepare($stmt); $statement->bind_param($str_types, ...$arr_values); */ }
			if ( $tries == $maxtries ) { $dbMessage = "Operation war nicht erfolgreich. "; $dbMessageGood = "false"; }
			else {
				$dbMessage = $message; $dbMessageGood = "true";
				$result = $statement->get_result();
				//log if log is enabled and stmt is not internal (grants, revokes, selects...)
				if ( strpos($stmt,'view__') === false AND strpos($stmt,'GRANT') === false AND strpos($stmt,'FLUSH') === false AND strpos($stmt,'SELECT') !== 0 AND strpos($stmt,'REVOKE') === false AND isset($_SESSION['log']) AND ( $_SESSION['log'] OR $log ) ) {
					//test for unchanging ALTER TABLE statements
					$_stmt_exploded = explode('`',$stmt); // index 3 aand 5 are the old and new id_-names
					if ( ! isset($_stmt_exploded[3]) OR ! isset($_stmt_exploded[5]) OR $_stmt_exploded[3] != $_stmt_exploded[5] ) {
						$logstring = $stmt;
						foreach ( $arr_values as $value ) {
							$logstring = preg_replace('/\?/',"'".$value."'",$logstring,1);
						}
						$_semicolon = ";";
						if ( preg_match('/\;$/',trim($logstring)) == 1 ){ $_semicolon = ''; }
						$_SESSION['logstring'] .= $logstring.$_semicolon.PHP_EOL;
					}
				} 
			}
		}
	}
	if ( isset($result) ) { $statement->close(); } // added 2021-07-22
	$_return = array();
	if ( isset($result) ) { $_return['result'] = $result; };
	$_return['dbMessage'] = $dbMessage;
	$_return['dbMessageGood'] = $dbMessageGood;
	if ( $conn AND $conn != null ) { $_return['insert_id'] = $conn->insert_id; }
	return $_return;
}

//returns result as three dimensional array: index1 = 'result','dbMessage','dbMessageGood';  index2 = key; index3 of 'result' = row number;
//$flip=true: flip index2 and index3; defaults to false
function execute_stmt(array $stmt_array, mysqli $conn, bool $flip = false)
{
	$_result_array = _execute_stmt($stmt_array,$conn);
	//if ( ! isset($_result_array['result']) ) { print_r($stmt_array); }; //for debug only
	$return = array(); $return['dbMessage'] = $_result_array['dbMessage']; $return['dbMessageGood'] = $_result_array['dbMessageGood']; $return['result'] = array(); $return['insert_id'] = $_result_array['insert_id']; $index = 0;
	if ( $_result = $_result_array['result'] AND $_result->num_rows > 0 ) {
		while ($row=$_result->fetch_assoc()) {
			if ( isset($flip) AND $flip ) { $return['result'][$index] = array(); }
			unset($value);
			foreach ($row as $key=>$value) {
				//problem are NULL values in the field values! Handle them!
				if ( is_null($key) ) { $key = ''; }
				if ( is_null($value) ) { $value = ''; }
				
				if ( (! isset($flip) OR ! $flip ) AND ! isset($return['result'][$key]) ) { $return['result'][$key] = array(); }
				if ( isset($flip) AND $flip ) { $return['result'][$index][$key] = $value; } else { $return['result'][$key][$index] = $value; }
			}
			$index++;
		}
		return $return;
	}
}


function dbAction(array $_PARAMETER,mysqli $conn) {
	$message = '';
	if ( isset($_SESSION['DEBUG']) AND $_SESSION['DEBUG'] ) { file_put_contents($GLOBALS["debugpath"].$GLOBALS["debugfilename"],'PARAM: '.json_encode($_PARAMETER).PHP_EOL,FILE_APPEND); }
	//allow json encoded parameters in 'trash' variable
	if ( isset($_PARAMETER['trash']) ) { 
	$PARAMETER = json_decode($_PARAMETER['trash'],true);
	} else {
		$PARAMETER = $_PARAMETER;
	}

	if ( ! is_array($PARAMETER) ) { return; }

	if ( ! isset($PARAMETER['dbAction']) ) { $PARAMETER['dbAction'] = ''; }
	
	//first do FILE actions
	$PARAMETER = FILE_Action($PARAMETER,$conn);
	switch($PARAMETER['dbAction']) {
		case 'getID':
			$select = "SELECT id_".$PARAMETER['table']." FROM `view__".$PARAMETER['table']."__".$_SESSION['os_role']."` ";
			$komma = "WHERE ";
			$where = "";
			$arr_values = array();
			$str_types = '';
			foreach($PARAMETER as $key=>$value)
			{
				if ( substr($key,0,strlen($PARAMETER['table'])) === $PARAMETER['table'] AND $value != '' ) {
				// $value != 'none' AND $value != '' AND $key !='id_'.$PARAMETER['table'] AND $key != 'dbAction' AND $key != 'dbMessage' AND $key != 'table' AND $key != 'key' AND $key != 'genkey' AND $key != 'rolepwd') {
					$properkey_array = explode('__',$key,2);
					$properkey = $properkey_array[sizeof($properkey_array)-1];
					$where .= $komma . "`" . $properkey . "` = ?";
					if ( is_array($value) ) { $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); } //added on 20190719
					$arr_values[] = rtrim($value);
					if ( substr($key,0,3) == 'id_' ) { $str_types .= "i"; } else { $str_types .= "s"; } //replace by a proper type query...
					$komma = " AND ";
				}
			}
			$stmt = $select . $where;
			break;
		case 'insertIfNotExists':
			$PARAMETER['dbAction'] = 'getID';
			$_id = dbAction($PARAMETER,$conn);
			if ( $_id == -1 )
			{ 
				$PARAMETER['dbAction'] = 'insert';
				return dbAction($PARAMETER,$conn);			
			} else {
				$return = '<div class="dbMessage">'.$_id.'</div>';
				return $return;				
			}
			break; //correct? inserted on 20200516
		case 'insert':
			$_SESSION['insert_id'] = array($PARAMETER['table']);
			$config = getConfig($conn);
			$maintable = $config['table'][0];
			//if there is no assignment, define empty $_MAINIDS as array of length 1
			if ( isset($PARAMETER['id_'.$maintable]) ) { $_MAINIDS = json_decode($PARAMETER['id_'.$maintable],true); } else { $_MAINIDS = array(""); };
			//this is only an array for maintable entries, but we need an array always:
			if ( ! is_array($_MAINIDS) ) { $_MAINIDS = array($_MAINIDS); }
 			foreach ( $_MAINIDS as $_index=>$mainid ) {
				if ( isset($PARAMETER['id_'.$maintable]) ) { $PARAMETER['id_'.$maintable] = $mainid; }; 
				$into = " INTO `view__" . $PARAMETER['table'] . "__". $_SESSION['os_role']."` ";
				$komma = "(";
				$arr_values = array();
				$str_types = '';
				$values = " VALUES ";
				foreach($PARAMETER as $key=>$value)
				{
					if ( $value != 'none' AND $value != '' AND $key !='id_'.$PARAMETER['table'] AND $key != 'dbAction' AND $key != 'dbMessage' AND $key != 'table' AND $key != 'key' AND $key != 'genkey' AND $key != 'rolepwd') {
						$properkey_array = explode('__',$key,2);
						$properkey = $properkey_array[sizeof($properkey_array)-1];
						$into .= $komma . "`" . $properkey . "`";
						$values .= $komma. "?";
						if ( is_array($value) ) { $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); } //added on 20190719
						$arr_values[] = rtrim($value);
						if ( substr($key,0,3) == 'id_' ) { $str_types .= "i"; } else { $str_types .= "s"; } //replace by a proper type query...
						$komma = ",";
					}
				}
				$into .= ")";
				$values .= ")";
				$stmt = "INSERT " . $into . $values . ";";
				//add here SELECT LAST_INSERT_ID() and pass it on (to hidden div in $message? YES).
				$message = "Eintrag wurde neu hinzugefügt.";
				//execute immediately if not last array item
				if ( isset($_MAINIDS[$_index+1]) ) {
					unset($_stmt_array);
					$_stmt_array = array(); $_stmt_array['stmt'] = $stmt; $_stmt_array['str_types'] = $str_types; $_stmt_array['arr_values'] = $arr_values; $_stmt_array['message'] = $message;  
					$_return=_execute_stmt($_stmt_array,$conn);
					$_SESSION['insert_id'][] = json_encode(array( 'id_'.$PARAMETER['table'] => $_return['insert_id'] ));
					$return = '<div class="dbMessage '.$_return['dbMessageGood'].'">'.$_return['dbMessage'].'</div>';
				}
			}
			break;
		case 'delete':
			//delete in all tables if selected table is current main table
			$config = getConfig($conn);
			if ( $PARAMETER['table'] == $config['table'][0] )
			{
				unset($_stmt_array); unset($_result_array); $_stmt_array = array();
				$_stmt_array['stmt'] = "SELECT tablemachine FROM os_tables";
				$tablesmachine = execute_stmt($_stmt_array,$conn)['result']['tablemachine']; //keynames as last array field 
				foreach ($tablesmachine as $tablemachine)
				{
					if ( $tablemachine == $PARAMETER['table'] ) { continue; }
					$_stmt_array['stmt'] = 'DELETE FROM `view__'.$tablemachine.'__'.$_SESSION['os_role'].'` WHERE `id_'.$PARAMETER['table'].'` = ?';
					$_stmt_array['str_types'] = "i";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = $PARAMETER['id_'.$PARAMETER['table']];
					$message = "Eintrag ". $PARAMETER['id_'.$PARAMETER['table']] . " wurde gelöscht. ";
					execute_stmt($_stmt_array,$conn);
				}
				
			}
			//
			$stmt = "DELETE FROM `view__" . $PARAMETER['table'] . "__" . $_SESSION['os_role']. "` WHERE id_".$PARAMETER['table']." IN (" . implode(',',json_decode($PARAMETER['id_'.$PARAMETER['table']])) . ");";
//			$arr_values = array();
//			$arr_values[] = $PARAMETER['id_'.$PARAMETER['table']];
//			$str_types = "i";
			if ( sizeof(json_decode($PARAMETER['id_'.$PARAMETER['table']],true)) > 1 ) {
				$message = "Einträge ". implode(',',json_decode($PARAMETER['id_'.$PARAMETER['table']],true)) . " wurden gelöscht.";
			} else {
				$message = "Eintrag ". implode(',',json_decode($PARAMETER['id_'.$PARAMETER['table']],true)) . " wurde gelöscht.";
			}
			break;
		case 'edit':
			$komma = "SET ";
			$set = '';
			$arr_values = array();
			$str_types = '';
			foreach($PARAMETER as $key=>$value)
			{
				if ( $value != 'none' AND $value != '' AND $key != 'id_'.$PARAMETER['table'] AND $key != 'dbAction' AND $key != 'dbMessage' AND $key != 'table' AND $key != 'key' AND $key != 'genkey' AND $key != 'rolepwd') {
					$properkey_array = explode('__',$key,2);
					$properkey = $properkey_array[sizeof($properkey_array)-1];
					if ( $value == "_NULL_" OR $value == "0001-01-01" ) {
						$set .= $komma . "`" . $properkey . "`= NULL";						
					} //added 20211014; enable entry removal by "_NULL_" or "0001-01-01"
					else {
						$set .= $komma . "`" . $properkey . "`= ?";
						$komma = ",";
						if ( is_array($value) ) { $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); } //added on 20190719
						$arr_values[] = rtrim($value);
						if ( substr($key,0,3) == 'id_') { $str_types .= "i"; } else { $str_types .= "s"; } //replace by a proper type query...
					}
				}
			}
			$stmt = "UPDATE `view__" . $PARAMETER['table'] . "__" . $_SESSION['os_role']. "`" . $set . " WHERE id_".$PARAMETER['table']." IN (" . implode(',',json_decode($PARAMETER['id_'.$PARAMETER['table']])) . ");";
			if ( sizeof(json_decode($PARAMETER['id_'.$PARAMETER['table']],true)) > 1 ) {
				$message = "Einträge ". implode(',',json_decode($PARAMETER['id_'.$PARAMETER['table']],true)) . " wurden geändert.";
			} else {
				$message = "Eintrag ". implode(',',json_decode($PARAMETER['id_'.$PARAMETER['table']],true)) . " wurde geändert.";
			}
			break;
		default:
			////get filters
			$where = '';
			$_and=' WHERE ';
			$filtered = 1;
			$arr_values = array();
			$str_types = '';
			foreach($PARAMETER as $key=>$value)
			{
				if ( $value != 'none' AND $value != '' AND $key != 'dbAction' AND $key != 'dbMessage' AND $key != 'table' AND $key != 'key' AND $key != 'genkey' AND $key != 'rolepwd') {
					$where .= $_and . "`". $key . "` LIKE CONCAT('%',?,'%')";
					$_and = " AND ";
					$filtered = 1;
					if ( is_array($value) ) { $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); } //added on 20190719
					$arr_values[] = $value;
					if ( substr($key,0,3) == 'id') { $str_types .= "i"; } else { $str_types .= "s"; } //replace by a proper type query...
				}
			}
			$stmt = "SELECT * FROM `view__" . $PARAMETER['table'] . "__" . $_SESSION['os_role'] . "` " . $where  . " ORDER BY `id_".$PARAMETER['table']."`;";
			break;
	}
	unset($_stmt_array);
	$_stmt_array = array(); $_stmt_array['stmt'] = $stmt; $_stmt_array['str_types'] = $str_types; $_stmt_array['arr_values'] = $arr_values; $_stmt_array['message'] = $message;  
	if ( isset($_SESSION['DEBUG']) AND $_SESSION['DEBUG'] ) { file_put_contents($GLOBALS["debugpath"].$GLOBALS["debugfilename"],'STATEMENT: '.json_encode($_stmt_array).PHP_EOL,FILE_APPEND); }
	$_return=_execute_stmt($_stmt_array,$conn);
	$_SESSION['insert_id'][] = json_encode(array( 'id_'.$PARAMETER['table'] => $_return['insert_id'] ));
	$_SESSION['insert_id'] = array_unique($_SESSION['insert_id']);
	$return = '<div class="dbMessage '.$_return['dbMessageGood'].'">'.$_return['dbMessage'].'<div hidden class="insertID">'.json_encode($_SESSION['insert_id']).'</div></div>';
	if ( isset($_SESSION['DEBUG']) AND $_SESSION['DEBUG'] ) { file_put_contents($GLOBALS["debugpath"].$GLOBALS["debugfilename"],json_encode($_return).PHP_EOL,FILE_APPEND); }
	if ( isset($_SESSION['DEBUG']) AND $_SESSION['DEBUG'] ) { file_put_contents($GLOBALS["debugpath"].$GLOBALS["debugfilename"],PHP_EOL,FILE_APPEND); }
	//return the results for searches and the error statement in all other cases
//	if ( isset($PARAMETER['dbAction']) AND $PARAMETER['dbAction'] != '' ) { return $return; } else { return $_return; }
	if ( isset($PARAMETER['dbAction']) ) { 
		switch($PARAMETER['dbAction']) {
			case '': 
				return $_return; 
				break;
			case 'getID':
				if ( $_return['result']->num_rows > 0 ) { return json_encode($_return['result']->fetch_assoc()); } else { return -1; }
				break;
			default:
				return $return;
				break;			
		}
	}
}

function getDetails($PARAMETER,$conn) 
{
	if ( isset($PARAMETER['trash']) ) { 
		$_array = json_decode($PARAMETER['trash'],true);
	} else {
		$_array = $PARAMETER;
	}

	if ( ! is_array($_array) ) { return; }
	//distinguish mass and single edit
	if ( isset($_array['massEdit']) ) {
		if ( is_array($_array['massEdit']) ) {
			$id = array();
			$table = $_array['massEdit'][0]; unset($_array['massEdit'][0]);
			foreach ( $_array['massEdit'] as $_json ) {
				$_tmparray = json_decode($_json,true);
				if ( isset($_tmparray['id_'.$table]) ) { $id[] = $_tmparray['id_'.$table]; }
			}
			unset($_tmparray);
		} else {
			return;
		}
	} else {
		//	$tablekey_array = json_decode($PARAMETER['trash'],true);
		foreach ( $_array as $key=>$value )
		{
			$table = substr($key,3); //key begins with id_
			if ( ! is_array($value) ) { $id = array($value); } else { $id = $value; }
		} 
	}
	//}
	unset($_stmt_array); $_stmt_array = array();
	$_stmt_array['stmt'] = 'SELECT iconname,tablemachine,delete_roles,displayforeign from os_tables';
	/*$_stmt_array['str_types'] = 's';
	$_stmt_array['arr_values'] = array($table);*/
	$_table_result = execute_stmt($_stmt_array,$conn,true)['result'];
	$icon = array();
	$delete_roles = array();
	$displayforeign = array();
	for ( $i = 0; $i < sizeof($_table_result); $i++ ) {
		$icon[$_table_result[$i]['tablemachine']] = $_table_result[$i]['iconname'];
		$delete_roles[$_table_result[$i]['tablemachine']] = $_table_result[$i]['delete_roles'];
		$displayforeign[$_table_result[$i]['tablemachine']] = $_table_result[$i]['displayforeign'];
	}
	$iconname = $icon[$table];
	unset($_stmt_array); $_stmt_array = array();
	$_stmt_array['stmt'] = 'SELECT * from `view__' . $table . '__' . $_SESSION['os_role'].'` WHERE id_'.$table.' IN ('.implode(',',$id).')';
//	$_stmt_array['str_types'] = 'i';
//	$_stmt_array['arr_values'] = array($id);

	//get details of the entry
	//unset($PARAMETER);
	$result_array = execute_stmt($_stmt_array,$conn);
	//only add to config at success and when not massEdit
	if ( isset($result_array['result']) AND ! isset($_array['massEdit']) ) { 
		$_SESSION['os_opennow'][] = $_array;
	} 
	//
	$PARAM = array();
	foreach ( $result_array['result'] as $key=>$value_array )
	{
		$PARAM[$key] = $result_array['result'][$key][0];
	}
	$ALLPARAM = $result_array['result'];
	$dbMessage = $result_array['dbMessage'];
	$dbMessageGood = $result_array['dbMessageGood'];

	//get foreign key values
	$foreign_array = json_decode($displayforeign[$table], true);
	$foreignkeys_array = array();
	if ( is_array($foreign_array) ) {
		foreach ( $foreign_array  as $foreign )
		{
			$ftable = explode('__',$foreign,2)[0];
			$fkey = explode('__',$foreign,2)[1];
			$foreignkeys_array[$ftable][] = $fkey;
		}
	}
	//update config
	$_config = getConfig($conn);
	$_config['_openids'] = $_SESSION['os_opennow'];
	updateConfig($_config,$conn);
	?>

	<div class="hidden"><div class="_table_"><?php html_echo($table); ?></div><div class="_id_"><?php if ( sizeof($id) == 1 ) { html_echo($id[0]); } else { echo('-1'); }; ?></div></div>
	<div class="content section" onclick="_disableClass(this,'noupdate'); this.onclick = ''; ">
		<?php $rnd=rand(0,2147483647); ?>
		<?php 
		//only for single edit
		if ( sizeof($id) == 1 ) { 
			foreach ( $_table_result as $_potentialtable ) {
				if ( isset($PARAM['id_'.$_potentialtable['tablemachine']]) AND $PARAM['id_'.$_potentialtable['tablemachine']] != '' AND $_potentialtable['tablemachine'] != $table ) {
					?>
					<form hidden id="attributionForm_<?php echo($_potentialtable['tablemachine'].$rnd); ?>" method="post" action="" onsubmit="editEntries(this,'<?php echo($_potentialtable['tablemachine']); ?>'); return false;">
						<input form="attributionForm_<?php echo($_potentialtable['tablemachine'].$rnd); ?>" type="text" value="<?php echo($PARAM['id_'.$_potentialtable['tablemachine']]); ?>" name="id_<?php echo($_potentialtable['tablemachine']); ?>" hidden>
						<input form="attributionForm_<?php echo($_potentialtable['tablemachine'].$rnd); ?>" id="attributionSubmit_<?php echo($_potentialtable['tablemachine'].$rnd); ?>" type="submit" hidden>
					</form>
					<?php
				}
			}			
			?>
			<div class="db_headline_wrapper">
				<div class="right" onclick="_close(this);"><i class="fas fa-times-circle"></i></div>
				<!-- Is the following class function always correct? -->
				<form method="post" id="reload<?php echo($rnd); ?>" class="left function" action="" onsubmit="callFunction(this,'getDetails','_popup_',false,'details','_close',true).then(()=>{ return false; }); return false;">
					<input hidden form="reload<?php echo($rnd); ?>" type="text" value="<?php html_echo($id[0]); ?>" name="id_<?php html_echo($table); ?>" />
					<input form="reload<?php echo($rnd); ?>" id="submitReload<?php echo($rnd); ?>" type="submit" hidden />
					<label class="unlimitedWidth date" data-title="neu laden" for="submitReload<?php echo($rnd); ?>"><i class="fas fa-redo-alt"></i></label>
				</form>
				<?php updateTime(); updateLastEdit($PARAM['changedat']); ?>
				<h2 class="db_headline clear"><i class="fas fa-<?php html_echo($iconname); ?>"></i> 
			<?php
				$_tmp_keys = array_keys($_config['filters']);
				unset($value); unset($index);
				foreach ( $_tmp_keys as $index=>$value )
				{
					if ( substr($value,0,strlen($table)) != $table ) { unset($_tmp_keys[$index]); }
					else { $_tmp_keys[$index] = substr($value,strlen($table)+2); }
					//exclude attributions for now:
					for ( $i = 0; $i < sizeof($_table_result); $i++ ) {
						if ( isset($_tmp_keys[$index]) AND $_tmp_keys[$index] == $_table_result[$i]['tablemachine'] ) { unset($_tmp_keys[$index]); }
					}
				}
				$_tmp_keys = array_values($_tmp_keys);
				if ( sizeof($_tmp_keys) == 0 ) { $_tmp_keys = array('id_'.$table); }
				unset($_stmt_array); $_stmt_array = array();
				$_stmt_array['stmt'] = 'SELECT '.implode(',',$_tmp_keys).' FROM `view__' . $table . '__' . $_SESSION['os_role'].'` WHERE id_'.$table.' = ?';
				$_stmt_array['str_types'] = 'i';
				$_stmt_array['arr_values'] = $id;
				$_table_result = execute_stmt($_stmt_array,$conn,true)['result'][0];
				unset($value); unset($index);
				foreach ( $_table_result  as $index=>$value )
				{
					$_table_result[$index] = _strip_tags(_cleanup($value),20);
				}						
				html_echo(implode(', ',$_table_result)); ?>
				<span hidden class="db_headline_id"><?php echo($id[0]); ?></span></h2></div>
			<?php includeFunctions('DETAILS',$conn); ?>	
		<?php }  else { ?>
			<div class="db_headline_wrapper">
				<div class="right" onclick="_close(this);"><i class="fas fa-times-circle"></i></div>
			<?php 
			// for mass edit
			updateTime(); ?>
				<h2 class="db_headline clear"><i class="fas fa-<?php html_echo($iconname); ?>"></i>&nbsp; <?php echo(sizeof($id)); ?> Einträge </h2></div>
		<?php } 
		?>	
		<div class="message" id="message<?php echo($table.$id[0]); ?>"><div class="dbMessage" class="<?php echo($dbMessageGood); ?>"><?php echo($dbMessage); ?></div></div>
		<form class="db_options function" method="POST" action="" onsubmit="callFunction(this,'dbAction','message').then(()=>{ return false; }); return false;">
			<input type="text" hidden value="<?php echo($table); ?>" name="table" class="inputtable" />
			<input type="text" hidden value="<?php html_echo(json_encode($id)); ?>" name="id_<?php echo($table); ?>" class="inputid" />
			<input type="text" hidden value="<?php echo($_SESSION['os_user']); ?>" name="changedby" class="inputid" />
			<div class="fieldset">
				<legend></legend>
		<!--	Freitextsuche vielleicht später	
				<label for="db_search">Suche</label>
				<input type="text" name="db_search" id="db_search">
		-->
				<div class="actionwrapper">
					<label for="_action<?php echo($table.$id[0]); ?>_sticky" class="action">Aktion</label>
					<select id="_action<?php echo($table.$id[0]); ?>_sticky" name="dbAction" class="db_formbox" onchange="tinyMCE.triggerSave(); invalid = validate(this,this.closest('form').getElementsByClassName('paramtype')[0].innerText); colorInvalid(this,invalid); if (invalid.length == 0) { updateTime(this); _onAction(this.value,this.closest('form'),'dbAction','message<?php echo($table.$id[0]); ?>'); callFunction(this.closest('form'),'calAction','').then(()=>{ return false; }); }; callFunction(document.getElementById('formFilters'),'applyFilters','results_wrapper',false,'','scrollTo',this).then(()=>{ if ( document.getElementById('_action<?php echo($table.$id[0]); ?>_sticky') ) { document.getElementById('_action<?php echo($table.$id[0]); ?>_sticky').value = ''; this.scrollIntoView(); }; return false; }); return false;" data-title="Aktion bitte erst nach der Bearbeitung der Inhalte wählen.">
						<option value="" selected>[Bitte erst nach Bearbeitung wählen]</option>
						<?php if ( isset($PARAM['id_'.$table]) ) { ?>
							<option value="edit">Eintrag ändern</option>
							<?php if ( in_array($_SESSION['os_role'],json_decode($delete_roles[$table],true)) ) {
							?>
								<option value="delete">Eintrag löschen</option>
							<?php } 
							}?>
						<option value="insert">als neuen Eintrag anlegen</option>
					</select>
				</div>
				<div class="clear"></div>
<!--				<div class="assignments"> -->
				<?php 
				//list the assignments for single edit (perhaps later for mass edit)
				if ( sizeof($id) == 1 ) {
					$_attribution = array('id_'.$table=>$id[0]);
					foreach( $PARAM as $key=>$default )
					{
						if ( substr($key,0,3) == 'id_'  OR $key == 'table' ) {
							if ( $key == 'id_'.$table OR ! in_array(substr($key,3),$_config['table']) ) { continue; }
							$_tmp_table = substr($key,3);
							if ( ! isset($default) OR $default == '' ) { ?>
								<div class='ID_<?php echo($_tmp_table); ?>' id="NeedIDForDrag_<?php echo(rand(0,2147483647)); ?>" draggable="true" ondragover="allowDrop(event)" ondrop="dropOnDetails(event,this)" ondragstart="dragOnDetails(event)" ondragenter="dragenter(event)" ondragleave="dragleave(event)" ondragend="dragend(event)">
									<label class="unlimitedWidth"><i class="fas fa-<?php echo($icon[$_tmp_table]); ?>"></i> (keine Zuordnung)</label>
									<input type="text" hidden value="<?php echo($default); ?>" name="<?php echo($key); ?>" class="inputid" />
									<span class="newEntryFromEntry" onclick="newEntryFromEntry(this,'<?php echo($_tmp_table); ?>')"><i class="fas fa-plus"></i> <i class="fas fa-<?php echo($icon[$_tmp_table]); ?>"></i></span>
								</div>
								<br />	
								<div class="clear"></div>
								<br />
							<?php		
								continue; }
							//save non-empty attributions in php array
							$_attribution[$key] = $default;
							$_tmp_keys = array_keys($_config['filters']);
							unset($value);
							foreach ( $_tmp_keys as $index=>$value )
							{
								if ( substr($value,0,strlen($_tmp_table)) != $_tmp_table ) { unset($_tmp_keys[$index]); }
								else { $_tmp_keys[$index] = substr($value,strlen($_tmp_table)+2); }
							}
							$_tmp_keys = array_values($_tmp_keys);
							if ( sizeof($_tmp_keys) == 0 ) { $_tmp_keys = array('id_'.$_tmp_table); };
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = 'SELECT '.implode(',',$_tmp_keys).' FROM `view__' . $_tmp_table . '__' . $_SESSION['os_role'].'` WHERE '.$key.' = ?';
							$_stmt_array['str_types'] = 'i';
							$_stmt_array['arr_values'] = array($default);
							$_table_result = execute_stmt($_stmt_array,$conn,true)['result'][0];
							unset($value);
							foreach ( $_table_result  as $index=>$value )
							{
								$_table_result[$index] = _strip_tags(_cleanup($value),20);
							}						
							?>
							<div class='ID_<?php echo($_tmp_table); ?>' id="NeedIDForDrag_<?php echo(rand(0,2147483647)); ?>" draggable="true" ondragover="allowDrop(event)" ondrop="dropOnDetails(event,this)" ondragstart="dragOnDetails(event)" ondragenter="dragenter(event)" ondragleave="dragleave(event)" ondragend="dragend(event)">
								<label class="unlimitedWidth openentry" for="attributionSubmit_<?php echo($_tmp_table.$rnd); ?>">
									<i class="fas fa-<?php html_echo($icon[$_tmp_table]); ?>"></i> 
									<b><?php html_echo(implode(', ',$_table_result)); ?></b>
									<i class="remove fas fa-trash-alt" onclick="return trashMapping(this);"></i>
								</label>
								<input type="text" hidden value="<?php echo($default); ?>" name="<?php echo($key); ?>" class="inputid" />
							</div>
							<br />	
							<div class="clear"></div>
							<table>
							<?php
							$ctable = $_tmp_table;
							if ( isset($PARAM['id_'.$ctable]) AND $PARAM['id_'.$ctable] > 0 AND isset($foreignkeys_array[$ctable]) AND sizeof($foreignkeys_array[$ctable]) > 0 ) {
								foreach ( $foreignkeys_array[$ctable] as $fkey ) {
									unset($_stmt_array); $_stmt_array = array();
									$_stmt_array['stmt'] = 'SELECT `'.$fkey.'` from `view__' . $ctable . '__' . $_SESSION['os_role'].'` WHERE id_'.$ctable.' = ?';
									$_stmt_array['str_types'] = 'i';
									$_stmt_array['arr_values'] = array($PARAM['id_'.$ctable]);
									$fresult = execute_stmt($_stmt_array,$conn,true);
									if ( isset($fresult['result']) AND sizeof($fresult['result']) > 0 ) {
										$value = json_decode($fresult['result'][0][$fkey],true);
										if ( $value != null ) {
											if (is_array($value) AND isset($value[0]) AND is_array($value[0])) {
												$value = _cleanup($fresult['result'][0][$fkey],' | ');
											} else {
												$value = _cleanup(_strip_tags($fresult['result'][0][$fkey]));
											}
										} else {
											$value = _cleanup(_strip_tags($fresult['result'][0][$fkey]));
										}
										unset($_stmt_array); $_stmt_array = array();
										$_stmt_array['stmt'] = 'SELECT keyreadable from '.$ctable . '_permissions WHERE keymachine = ?';
										$_stmt_array['str_types'] = 's';
										$_stmt_array['arr_values'] = array($fkey);
										$fkeyreadable = explode(': ',execute_stmt($_stmt_array,$conn)['result']['keyreadable'][0])[0];
										?>
										<tr>
											<td class="unlimitedWidth"><i class="fas fa-<?php echo($icon[$ctable]); ?>"></i> <?php html_echo($fkeyreadable); ?></td>
											<td class="bold fkey fkey-<?php html_echo($ctable); ?>-<?php html_echo($fkey); ?>"><?php html_echo($value); ?></td>
										</tr>
										<?php
									}
								}
							} ?>
							</table>
							<br />
							<?php }
					}
					?> <div hidden class="attribution"><?php html_echo(json_encode($_attribution)); ?></div> <?php
				} ?>
<!--				</div> -->
				<?php
				$PARAMTYPE = array();
				//now show the list parameters
				foreach( $PARAM as $key=>$default )
				{
					if ( array_unique($ALLPARAM[$key]) != array($default) ) { $default = ''; }
					if ( sizeof($id) > 1 ) {
						$_single = false;
					} else {
						$_single = true;
					}
					if ( substr($key,0,3) != 'id_'  AND $key != 'table' ) {
						$edit = new OpenStatEdit($table,$key,$conn);
						$PARAMTYPE[$table.'__'.$key] = $edit->edit($default,$_single);
						unset($edit);
					}
				}
				
				?>
				<div class="paramtype" hidden><?php html_echo(json_encode($PARAMTYPE)); ?></div>
				<input type="submit" hidden>	
			</div> <!-- END OF div of class fieldset-->
		</form>
	</div>
<?php }

function includeFunctions(string $scope, mysqli $conn)
{ ?>
	<div class="functions">
		<?php
			unset($_stmt_array); $_stmt_array = array();
			$_stmt_array['stmt'] = "SELECT iconname,functionmachine,functionreadable,functionclasses,functiontarget,allowed_roles FROM os_functions where functionscope = ?";
			$_stmt_array['str_types'] = "s";
			$_stmt_array['arr_values'] = array();
			$_stmt_array['arr_values'][] = $scope;
			$_result_array = execute_stmt($_stmt_array,$conn,true);
			//which form is the relevant:
			//obsolete: they are now the ones with class "function"
			//if ( $scope == "FILTERS" ) { $index = 1; } else { $index = 0; };
			// 
			if ($_result_array['dbMessageGood']) 
			{ ?>
				<ul>
			<?php 
				unset($_result);
				$_result = $_result_array['result'];
				foreach ( $_result as $_function )
				{
					if ( in_array($_SESSION['os_role'],json_decode($_function['allowed_roles'])) OR in_array($_SESSION['os_parent'],json_decode($_function['allowed_roles'])) ) { ?>
					<li><label 
						class="unlimitedWidth"
						onclick="callPHPFunction(this.closest('.functions').parentNode.querySelector('form.function'),'<?php echo($_function['functionmachine']); ?>','<?php echo($_function['functiontarget']); ?>','<?php echo($_function['functionclasses']); ?>')"
						data-title="<?php echo($_function['functionreadable']); ?>"
						><i class="fas fa-<?php echo($_function['iconname']); ?>"></i></label></li>
					<?php } 
				}				
			?>
				</ul>
		<?php } ?>		
	</div>
<?php
}

function updateTime()
{
	?>
	<div class="time" data-title="zuletzt geladen"><i class="fas fa-clock"></i> <?php echo(date("H:i:s")); ?></div>
	<?php
}

function updateLastEdit(string $datetime)
{
	?>
	<div class="time" data-title="zuletzt gespeichert"><i class="fas fa-pencil-alt"></i> <?php echo(DateTime::createFromFormat('Y-m-d H:i:s', $datetime)->format('d.m.Y H:i')); ?></div>
	<?php
}

?>
