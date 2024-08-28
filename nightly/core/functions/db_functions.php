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
				if ( strpos($stmt,'view__') === false AND strpos($stmt,'GRANT') === false AND strpos($stmt,'FLUSH') === false AND strpos($stmt,'SELECT') !== 0 AND strpos($stmt,'REVOKE') === false AND strpos($stmt,'CREATE OR REPLACE') === false AND isset($_SESSION['log']) AND ( $_SESSION['log'] OR $log ) ) {
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
	if ( isset($_result_array['result']) AND $_result = $_result_array['result'] AND $_result->num_rows > 0 ) {
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
			//adapt to identifiers
			unset($_stmt_array);
			$_stmt_array['stmt'] = "SELECT identifiers from os_tables where tablemachine=?";
			$_stmt_array['str_types'] = "s";
			$_stmt_array['arr_values'] = $PARAMETER['table'];
			$_identifiers = execute_stmt($_stmt_array,$conn)['result']['identifiers'][0];
			if ( $_identifiers == '' ) { $_identifiers = array(); } else { $_identifiers = json_decode($_identifiers); }
			$IDPARAM = array();
			foreach($_identifiers as $identifier) 
			{
				if ( isset($PARAMETER[$PARAMETER['table'].'__'.$identifier]) ) 
				{ $IDPARAM[$PARAMETER['table'].'__'.$identifier] = $PARAMETER[$PARAMETER['table'].'__'.$identifier]; }
				else
				{ $IDPARAM[$PARAMETER['table'].'__'.$identifier] = ''; }
			}
			// select all parameters if no identiers are given
			if ( sizeof($IDPARAM) == 0 ) { $IDPARAM = $PARAMETER; }
			$select = "SELECT id_".$PARAMETER['table']." FROM `view__".$PARAMETER['table']."__".$_SESSION['os_role']."` ";
			$komma = "WHERE ";
			$where = "";
			$arr_values = array();
			$str_types = '';
			//foreach($IDPARAM as $key=>$value) //ok, but if at import e.g. all idenifiers coincide other data may have been updated, so we must be able to choose the new data somehow!
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
		case 'updateIfExistsElseInsert':
			$PARAMETER['dbAction'] = 'getID';
			$_id = dbAction($PARAMETER,$conn);
			if ( $_id != -1 )
			{ 
				$_tmparray = json_decode($_id,true)['id_'.$PARAMETER['table']];
				if ( ! is_array($_tmparray) ) { $_tmparray = array($_tmparray); }
				$PARAMETER['id_'.$PARAMETER['table']] = json_encode($_tmparray);
				unset($_tmparray);
				$PARAMETER['dbAction'] = 'edit';
				return dbAction($PARAMETER,$conn);			
			} else {
				$PARAMETER['dbAction'] = 'insert';
				return dbAction($PARAMETER,$conn);			
			}
			break;
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
				$into = " INTO `view__" . $PARAMETER['table'] . "INSERT__". $_SESSION['os_role']."` "; //INSERT view is without EXPRESSION modified fields
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
			//delete on tables hanging under PARAMETER['table']
			//shouldnt we delete on all tables where an attribution is detected? Or, should we have a cleanong function removing orphaned entries?
			$config = getConfig($conn);
			function _deleteEntriesOfNextLevel(array $config, mysqli $conn, string $deletefromtable, array $idstobedeleted) {
				// get position and rank of table in config
				$thistable_key = array_search($deletefromtable,$config['table']);
				if ( isset($config['table_hierarchy']) ) {
					$thistable_rank = $config['table_hierarchy'][$thistable_key];
				} else {
					$thistable_rank = min(1,$thistable_key);
				}
				unset($_stmt_array); unset($_result_array); $_stmt_array = array();
				$_stmt_array['stmt'] = "SELECT tablemachine FROM os_tables";
				$tablesmachine = execute_stmt($_stmt_array,$conn)['result']['tablemachine']; //keynames as last array field 
				//remove if table index and rank are greater
				foreach( array_slice($config['table'],$thistable_key+1,null,true) as $index=>$tablemachine )
				{
					$table_rank = 1;
					if ( isset($config['table_hierarchy']) ) {
						$table_rank = $config['table_hierarchy'][$index];
					}
					if ( $table_rank > $thistable_rank ) {
						$_idstobedeletednext = array();
						unset($_stmt_array);
						$_prep = substr(str_repeat(',?',sizeof($idstobedeleted)),1);
						$_types = str_repeat('i',sizeof($idstobedeleted));
						$_stmt_array['stmt'] = 'SELECT id_'.$tablemachine.' AS id FROM `view__'.$tablemachine.'__'.$_SESSION['os_role'].'` WHERE id_'.$deletefromtable.' IN (' . $_prep . ');';
						$_stmt_array['str_types'] = $_types;
						$_stmt_array['arr_values'] = $idstobedeleted;						
						$_idstobedeletednext = execute_stmt($_stmt_array,$conn)['result']['id'];
						$_stmt_array['stmt'] = 'DELETE FROM `view__'.$tablemachine.'__'.$_SESSION['os_role'].'` WHERE id_'.$deletefromtable.' IN (' . $_prep . ');';
						execute_stmt($_stmt_array,$conn);
						if ( sizeof($_idstobedeletednext) > 0 ) { _deleteEntriesOfNextLevel($config,$conn,$tablemachine,$_idstobedeletednext); }
					}
				}
			}
			_deleteEntriesOfNextLevel($config,$conn,$PARAMETER['table'],json_decode($PARAMETER['id_'.$PARAMETER['table']]));
			//
			$_mainidstobedeleted = json_decode($PARAMETER['id_'.$PARAMETER['table']]);
			$_prep = substr(str_repeat(',?',sizeof($_mainidstobedeleted)),1);
			$stmt = "DELETE FROM `view__" . $PARAMETER['table'] . "__" . $_SESSION['os_role']. "` WHERE id_".$PARAMETER['table']." IN (" . $_prep . ");";
			$arr_values = $_mainidstobedeleted;
			$str_types = str_repeat('i',sizeof($_mainidstobedeleted));
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
						$komma = ","; //added 20230904; should be correct
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
			//get DERIVED fields:
			unset($_stmt_array);
			$_stmt_array= array();
			$_stmt_array['stmt'] = "SELECT keymachine FROM ".$PARAMETER['table']."_permissions WHERE edittype LIKE '%; DERIVED'";
			$_derivedkeys = execute_stmt($_stmt_array,$conn)['result']['keymachine'];
			//set DERIVED defaults:
			foreach($_derivedkeys as $derivedkey) {
				$set .= $komma . "`" . $derivedkey . "` = DEFAULT(`" . $derivedkey . "`)";
				$komma = ",";
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
	//get config
	$_config = getConfig($conn);
	//
	unset($_stmt_array); $_stmt_array = array();
	$_stmt_array['stmt'] = 'SELECT iconname,tablemachine,delete_roles,displayforeign,parentmachine,tablereadable from os_tables';
	/*$_stmt_array['str_types'] = 's';
	$_stmt_array['arr_values'] = array($table);*/
	$_table_result = execute_stmt($_stmt_array,$conn,true)['result'];
	$icon = array();
	$delete_roles = array();
	$displayforeign = array();
	for ( $i = 0; $i < sizeof($_table_result); $i++ ) {
		$icon[$_table_result[$i]['tablemachine']] = $_table_result[$i]['iconname'];
		$tablereadable[$_table_result[$i]['tablemachine']] = $_table_result[$i]['tablereadable'];
		$delete_roles[$_table_result[$i]['tablemachine']] = $_table_result[$i]['delete_roles'];
		$displayforeign[$_table_result[$i]['tablemachine']] = $_table_result[$i]['displayforeign'];
	}
	$iconname = $icon[$table];
	//get fields from MAIN and subtables; it's a bit rough, since (id-)fields my occur more than once, but they all carry the same value, and
	//fetch_assoc as used in execute_stmt simply overwrites the values, which is ok:
	//"If two or more columns of the result have the same name, the last column will take precedence and overwrite any previous data."
	unset($_stmt_array); $_stmt_array = array();
	$_stmt_array['stmt'] = 'SELECT * from `view__' . $table . 'MAIN__' . $_SESSION['os_role'] . '`';
	//recursively add subtable views
	function addSubtablesToStmt($table,$_table_result,$_config,$stmt) {
		foreach ( array_filter($_table_result,function($_tmptable) use($table,$_config){ return ( $_tmptable['parentmachine'] == $table ) AND in_array($_tmptable['tablemachine'],$_config['subtable']); }) as $_subtablefull ) {
			$_subtablemachine = $_subtablefull['tablemachine'];
			//$_stmt_array['stmt'] .= ' INNER JOIN view__'.$_subtablemachine.'__'.$_SESSION['os_role'].' USING (id_'.$table.')';
			$stmt .= ' INNER JOIN view__'.$_subtablemachine.'__'.$_SESSION['os_role'].' USING (id_'.$table.')';
			$stmt = addSubtablesToStmt($_subtablefull,$_table_result,$_config,$stmt);
		}
		return $stmt;
	}
	$_stmt_array['stmt'] = addSubtablesToStmt($table,$_table_result,$_config,$_stmt_array['stmt']);
	$_stmt_array['stmt'] .= ' WHERE id_'.$table.' IN ('.implode(',',$id).')';
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
				<h2 class="db_headline clear" oncontextmenu="return transportAttribution(this)"><i class="fas fa-<?php html_echo($iconname); ?>"></i> 
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
		//show trafficLight warnings
		?>
		<div class="trafficLight">
		<?php
			if ( isset($_SESSION['trafficLight']) ) {
				$trafficLight = json_decode($_SESSION['trafficLight'],true);
				foreach ( $id as $singleid ) {
					if ( isset($trafficLight[$table][$singleid]) ) {
						if ( $trafficLight[$table][$singleid]['urgency'] == 1 ) { $_trafficClass = "yellow"; }
						if ( $trafficLight[$table][$singleid]['urgency'] == 2 ) { $_trafficClass = "orange"; }
						if ( $trafficLight[$table][$singleid]['urgency'] == 3 ) { $_trafficClass = "red"; }
						?>
						<div class="<?php echo($_trafficClass); ?>">
							<?php
							html_echo(implode(' | ',$trafficLight[$table][$singleid]['criteria']));
							?>
						</div>
						<?php
					}
				}
			}
		?>
		</div>	
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
		<!--			<select id="_action<?php echo($table.$id[0]); ?>_sticky" name="dbAction" class="db_formbox" onchange="tinyMCE.triggerSave(); invalid = validate(this,this.closest('form').getElementsByClassName('paramtype')[0].innerText); colorInvalid(this,invalid); if (invalid.length == 0) { updateTime(this); _onAction(this.value,this.closest('form'),'dbAction','message<?php echo($table.$id[0]); ?>'); callFunction(this.closest('form'),'calAction','').then(()=>{ callFunction(this.closest('form'),'FUNCTIONAction','FUNCTIONresults').then(()=>{ return false; }); return false; }); }; callFunction(document.getElementById('formFilters'),'applyFilters','results_wrapper',false,'','scrollTo',this).then(()=>{ if ( document.getElementById('_action<?php echo($table.$id[0]); ?>_sticky') ) { document.getElementById('_action<?php echo($table.$id[0]); ?>_sticky').value = ''; myScrollIntoView(this); }; return false; }); return false;" title="Aktion bitte erst nach der Bearbeitung der Inhalte wählen."> -->
					<select id="_action<?php echo($table.$id[0]); ?>_sticky" name="dbAction" class="db_formbox" onchange="tinyMCE.triggerSave(); invalid = validate(this,this.closest('form').getElementsByClassName('paramtype')[0].innerText); colorInvalid(this,invalid); if (invalid.length == 0) { updateTime(this); _onAction(this.value,this.closest('form'),'dbAction','message<?php echo($table.$id[0]); ?>'); callFunction(this.closest('form'),'calAction','').then(()=>{ callFunction(this.closest('form'),'FUNCTIONAction','FUNCTIONresults').then(()=>{ callFunction(document.getElementById('formFilters'),'applyFilters','results_wrapper',false,'','scrollTo',this).then(()=>{ if ( document.getElementById('_action<?php echo($table.$id[0]); ?>_sticky') ) { document.getElementById('_action<?php echo($table.$id[0]); ?>_sticky').value = ''; myScrollIntoView(this); }; return false; }); return false; }); return false; }); }; return false;" title="Aktion bitte erst nach der Bearbeitung der Inhalte wählen.">
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
//				if ( sizeof($id) == 1 ) {
				if ( sizeof($id) == 1 ) {
					$_attribution = array('id_'.$table=>$id[0]);
				} else {
					$_attribution = array();
				}
				foreach( $PARAM as $key=>$default )
				{
					if ( substr($key,0,3) == 'id_'  OR $key == 'table' ) {
						$_enabledisabled = '';
						$_noattribution = "";
						if ( $key == 'id_'.$table OR ! in_array(substr($key,3),$_config['table']) ) { continue; }
						$_tmp_table = substr($key,3);
						if ( array_unique($ALLPARAM[$key]) != array($default) ) { $default = ''; }
						if ( sizeof($id) > 1 ) { 
							$_enablernd = rand(0,2147483647);
							$_enabledisabled = 'disabled';
							$_noattribution = " oder multiple ";
							?>
							<label class="unlimitedWidth" onclick="_toggleEnabled(<?php echo($_enablernd); ?>);"><i class="fas fa-pen-square"></i></label>
							<div id="enablable<?php echo($_enablernd); ?>" class="disabled">
						<?php }
						if ( ! isset($default) OR $default == '' ) { ?>
								<div class='ID_<?php echo($_tmp_table); ?>' id="NeedIDForDrag_<?php echo(rand(0,2147483647)); ?>" draggable="true" ondragover="allowDrop(event)" ondrop="dropOnDetails(event,this)" ondragstart="dragOnDetails(event)" ondragenter="dragenter(event)" ondragleave="dragleave(event)" ondragend="dragend(event)">
									<label class="unlimitedWidth" oncontextmenu="return transportAttribution(this)"><i class="fas fa-<?php echo($icon[$_tmp_table]); ?>"></i> (keine <?php echo($_noattribution); ?>Zuordnung)</label>
									<input type="text" hidden value="<?php echo($default); ?>" <?php echo($_enabledisabled); ?> name="<?php echo($key); ?>" class="inputid" />
									<?php if ( sizeof($id) == 1 ) { ?>
									<span class="newEntryFromEntry" onclick="newEntryFromEntry(this,'<?php echo($_tmp_table); ?>')"><i class="fas fa-plus"></i> <i class="fas fa-<?php echo($icon[$_tmp_table]); ?>"></i></span>
									<?php } ?>
								</div>
								<br />	
								<div class="clear"></div>
								<br />
						<?php		
							if ( sizeof($id) > 1 ) { ?>
								</div> <!-- end of class enablable -->
						<?php
							}
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
							<label class="unlimitedWidth openentry" oncontextmenu="return transportAttribution(this)" for="attributionSubmit_<?php echo($_tmp_table.$rnd); ?>">
								<i class="fas fa-<?php html_echo($icon[$_tmp_table]); ?>"></i> 
								<b><?php html_echo(implode(', ',$_table_result)); ?></b>
								<i class="remove fas fa-trash-alt" onclick="return trashMapping(this);"></i>
							</label>
							<input type="text" hidden value="<?php echo($default); ?>" <?php echo($_enabledisabled); ?> name="<?php echo($key); ?>" class="inputid" />
						</div>
						<br />	
						<div class="clear"></div>
						<?php 
						if ( sizeof($id) > 1 ) { ?>
							</div> <!-- end of class enablable -->
						<?php } ?>
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
				?> <div hidden class="attribution"><?php html_echo(json_encode($_attribution)); ?></div> 
<!--				</div> -->
				<?php
				$PARAMTYPE = array();
				//now show the list parameters
				foreach( $PARAM as $key=>$default )
				{
					if ( array_unique($ALLPARAM[$key]) != array($default) ) { $default = ''; }
					if ( sizeof($id) > 1 ) {
						$_single = false;
                        $_passid = 0;
					} else {
						$_single = true;
                        $_passid = $id[0];
					}
					if ( substr($key,0,9) == 'subtable_' ) {
						$currentsubtablemachine = substr($key,9);
						$_subrnd = rand(0,2147483647);
						?>
						<!-- the header and clickers here... -->
						<input hidden type="checkbox" id="subToggle<?php echo($_subrnd); ?>" class="subtoggle">
						<div class="subtable_header">
							<label for="subToggle<?php echo($_subrnd); ?>">
								<i class="fas fa-angle-right closed">&nbsp;</i>
								<i class="fas fa-angle-down open">&nbsp;</i>
								<i class="fas fa-<?php echo($icon[$currentsubtablemachine]);?>"></i> <b><?php echo($tablereadable[$currentsubtablemachine]); ?></b>
							</label>
						</div>
						<?php
					}
					if ( substr($key,0,9) != 'subtable_' AND substr($key,0,3) != 'id_'  AND $key != 'table' ) {
						$edit = new OpenStatEdit($table,$key,$conn,$_passid);
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

//obsolete but may come in handy later...
function getVirtualValue(string $table,int $id,string $key,mysqli $conn) {
    $_virtual_stmt_array = array();
    $_virtual_stmt_array['stmt'] = 'SELECT edittype, defaultvalue FROM '.$table .'_permissions WHERE keymachine = ?';
    $_virtual_stmt_array['str_types'] = 's';
    $_virtual_stmt_array['arr_values'] = array($key);
    $_virtualornot = execute_stmt($_virtual_stmt_array,$conn,true)['result'][0];
    if ( strpos($_virtualornot['edittype'],'; VIRTUAL') > 0 ) {
        $_sqlforkey = str_replace("'","",preg_replace('/\$([^\$]*)\$/','$1',$_virtualornot['defaultvalue']));
        $_virtual_stmt_array = array();
        $_virtual_stmt_array['stmt'] = 'SELECT '.$_sqlforkey.' AS _value FROM `view__'.$table .'__'.$_SESSION['os_role'].'` WHERE id_'.$table.' = ?';
        $_virtual_stmt_array['str_types'] = 'i';
        $_virtual_stmt_array['arr_values'] = array($id);
        return execute_stmt($_virtual_stmt_array,$conn)['result']['_value'][0];        
    }
    return null;
}

function includeFunctions(string $scope, mysqli $conn)
{ ?>
	<div class="functions">
		<?php
			unset($_stmt_array); $_stmt_array = array();
			$_stmt_array['stmt'] = "SELECT iconname,functionmachine,functionreadable,functionclasses,functiontarget,functionflags,allowed_roles FROM os_functions where functionscope = ?";
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
					if ( in_array($_SESSION['os_role'],json_decode($_function['allowed_roles'])) OR in_array($_SESSION['os_parent'],json_decode($_function['allowed_roles'])) ) { 
						//distinguish FontAwesome and FontCC icons: FontCC iconnames start with 'fcc-'
						if ( substr($_function['iconname'],0,4) == "fcc-" ) {
							$_function['iconclass'] = "fcc ".$_function['iconname'];
						} else {
							$_function['iconclass'] = "fas fa-".$_function['iconname'];
						}
						if ( $_function['functionflags'] == '' OR $_function['functionflags'] == null ) { $_function['functionflags'] = '[]'; } ?>
					<li><label 
						class="unlimitedWidth"
						onclick="callPHPFunction(this.closest('.functions').parentNode.querySelector('form.function'),'<?php echo($_function['functionmachine']); ?>','<?php echo($_function['functiontarget']); ?>','<?php echo($_function['functionclasses']); ?>')"
						data-title="<?php echo($_function['functionreadable']); ?>"
						data-name="<?php echo($_function['functionmachine']); ?>"
						data-flags="<?php html_echo($_function['functionflags']); ?>"
						<?php if ( in_array('HIDDEN',json_decode($_function['functionflags'],true)) ) { ?>hidden<?php } ?>
						><i class="<?php echo($_function['iconclass']); ?>"></i></label></li>
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

//$PARAM is not used, just there for function conformity to registration
function cleanDB(array $PARAM, mysqli $conn) {
	global $debugpath, $debugfilename;
	unset($_stmt_array); $_stmt_array = array();
	$_stmt_array['stmt'] = "SELECT id,iconname,tablemachine,allowed_roles,delete_roles FROM `os_tables`";
	$_tables_array = execute_stmt($_stmt_array,$conn)['result'];
	$_TABLES = $_tables_array['tablemachine'];
	$_TABLES_ID = $_tables_array['id'];
	$_TABLES_ALLOW = $_tables_array['allowed_roles'];
	$_TABLES_DELETE = $_tables_array['delete_roles'];
	$_TABLES_ARRAY = array_combine($_TABLES_ID,$_TABLES);
	$_TABLES_ICON = array_combine($_TABLES_ID,$_tables_array['iconname']);
	$_TABLES_DELETE_ARRAY = array_combine($_TABLES_ID,$_TABLES_DELETE);
	$_TABLES_ALLOW_ARRAY = array_combine($_TABLES_ID,$_TABLES_ALLOW);
	//determine all entries of delete-permitted tables with non-existing attributions in allowed-permitted tables
	$_markedfordeletion = array();
	foreach ( $_TABLES_ID as $_id ) {
		$_markedfordeletion[$_id] = array(0);
	}
	$_oldmarkedfordeletion = array();	
	while ( json_encode($_markedfordeletion) != json_encode($_oldmarkedfordeletion) ) {
		$_oldmarkedfordeletion = json_decode(json_encode($_markedfordeletion));
		foreach ( $_TABLES_ID as $_deleteid ) {
			if ( ! in_array($_SESSION['os_role'],json_decode($_TABLES_DELETE_ARRAY[$_deleteid])) AND ! in_array($_SESSION['os_parent'],json_decode($_TABLES_DELETE_ARRAY[$_deleteid])) ) { continue; }
			foreach ( $_TABLES_ID as $_allowid ) {
				if ( ! in_array($_SESSION['os_role'],json_decode($_TABLES_ALLOW_ARRAY[$_allowid])) AND ! in_array($_SESSION['os_parent'],json_decode($_TABLES_ALLOW_ARRAY[$_allowid])) ) { continue; }
				unset($_stmt_array); $_stmt_array = array(); unset($_result_raw);
				// ... > 0 ...: entries with removed attributions get id 0
				$_stmt_array['stmt'] = "SELECT `view__".$_TABLES_ARRAY[$_deleteid]."__".$_SESSION["os_role"]."`.id_".$_TABLES_ARRAY[$_deleteid]." AS `id` FROM `view__".$_TABLES_ARRAY[$_deleteid]."__".$_SESSION["os_role"]."` LEFT JOIN `view__".$_TABLES_ARRAY[$_allowid]."__".$_SESSION["os_role"]."` USING (id_".$_TABLES_ARRAY[$_allowid].") WHERE `view__".$_TABLES_ARRAY[$_allowid]."__".$_SESSION["os_role"]."`.id_".$_TABLES_ARRAY[$_allowid]." IS NULL AND `view__".$_TABLES_ARRAY[$_deleteid]."__".$_SESSION["os_role"]."`.id_".$_TABLES_ARRAY[$_allowid]." > 0  OR `view__".$_TABLES_ARRAY[$_deleteid]."__".$_SESSION["os_role"]."`.id_".$_TABLES_ARRAY[$_deleteid]." IN ('".implode("','",$_markedfordeletion[$_deleteid])."')";
				$_result_raw = execute_stmt($_stmt_array,$conn);
				if ( isset($_result_raw['result']) ) {
					$_markedfordeletion[$_deleteid] = array_unique(array_merge($_markedfordeletion[$_deleteid],$_result_raw['result']['id']));
					sort($_markedfordeletion[$_deleteid]);
				}
			}
		}
	}
	//present an editable form with entries marked for deletion (maybe with correcting attributions by id?)
	$_maxentries = 0;
	foreach ( $_TABLES_ID as $_id ) {
		$_maxentries = max($_maxentries,sizeof($_markedfordeletion[$_id])-1);
	}
	if ( $_maxentries == 0 ) { ?>
	<div>
		<h3><i class="fas fa-broom"></i> Datenbank bereinigen</h3>
		<p>Die Datenbank ist sauber.</p>
	</div>
	<?php 
		return;	
	}
	?>
	<form method="POST" id="formCleanDB" onsubmit="if ( confirm('Wollen Sie die ausgewählten Einträge wirklich löschen?') ) { callFunction(this,'cleanDBdelete','_popup_',false,'cleanup').then(()=>{ _close(this); return false; }) }; return false;"></form>
	<div>
		<h3><i class="fas fa-broom"></i> Datenbank bereinigen</h3>
		<p>Die folgenden Einträge haben gebrochene Zuordnungen</p>
	</div>
	<?php
	foreach ( $_TABLES_ID as $_id ) {
		array_shift($_markedfordeletion[$_id]); //removes the auxiliary '0' entry
		if ( sizeof($_markedfordeletion[$_id]) > 0 ) {
		?>
			<form>
				<div class="tableicon">
					<input id="delete_<?php echo($_id); ?>" type="checkbox" checked onclick="_toggleEditAll('formCleanDB','delete_<?php echo($_id); ?>','.delete_<?php echo($_id); ?>')">
					<label for="toggleCleanDB_<?php echo($_TABLES_ARRAY[$_id]); ?>"><i class="fas fa-<?php html_echo($_TABLES_ICON[$_id]); ?>"></i></label>
				</div>
			</form>
			<div>
		<?php
		foreach ( $_markedfordeletion[$_id] as $tobedeleted) { 
			$_rnd = rand(0,2147483647); ?>
			<input form="formCleanDB" class="delete_<?php echo($_id); ?>" type="checkbox" name="<?php echo($_TABLES_ARRAY[$_id]); ?>[]" id="cleanDB_<?php echo($_id.'_'.$tobedeleted); ?>" value="<?php echo($tobedeleted); ?>" checked>
			<form method="post" id="deleteOpenForm_<?php echo($_rnd); ?>" class="inline" action="" onsubmit="callFunction(this,'getDetails','_popup_',false,'details','updateSelectionsOfThis').then(()=>{ newEntry(this,'',''); return false; }); return false;">
				<input form="deleteOpenForm_<?php echo($_rnd); ?>" value="<?php echo($tobedeleted); ?>" name="id_<?php echo($_TABLES_ARRAY[$_id]); ?>" hidden="" type="text"><input form="deleteOpenForm_<?php echo($_rnd); ?>" id="deleteOpenSubmit__<?php echo($_rnd); ?>" hidden="" type="submit">
			</form>
			<label for="deleteOpenSubmit__<?php echo($_rnd); ?>" class="link" title="Öffnen"><?php echo($tobedeleted); ?></label>
		<?php }
		?> </div><?php
		}
	} ?>
		<label class="cleanDBsubmit" for="cleanDBsubmit"><i class="fas fa-broom"></i> Auswahl löschen</label>
		<input form="formCleanDB" id="cleanDBsubmit" type="submit" hidden>
	<?php
	//todo: cp and attribute ids by contextmenu event!
}

function cleanDBdelete(array $PARAM, mysqli $conn) {
	foreach ( $PARAM as $deletefromtable => $deleteentries) {
		$_prep = substr(str_repeat(',?',sizeof($deleteentries)),1);
		$_types = str_repeat('i',sizeof($deleteentries));
		unset($_stmt_array);
		$_stmt_array['stmt'] = 'DELETE FROM view__'.$deletefromtable.'__'.$_SESSION['os_role'].' WHERE id_'.$deletefromtable.'  IN ('.$_prep.')';
		$_stmt_array['str_types'] = $_types;
		$_stmt_array['arr_values'] = $deleteentries;
		$_result_raw = execute_stmt($_stmt_array,$conn);
	}
	cleanDB(array(),$conn);
}
?>
