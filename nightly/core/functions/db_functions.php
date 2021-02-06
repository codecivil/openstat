<?php
//returns raw result
//logs if $_SESSION['log'] is set and true (in order to avoid changing all execute_stmts), or if parameter $log is set and true
function _execute_stmt(array $stmt_array, mysqli $conn, bool $log = false)
{
	$stmt = ''; $str_types = ''; $arr_values = ''; $message = '';
	if (isset($stmt_array['stmt']) ) { $stmt = $stmt_array['stmt']; };
	if (isset($stmt_array['str_types']) ) { $str_types = $stmt_array['str_types']; };
	if (isset($stmt_array['arr_values']) ) {  $arr_values = $stmt_array['arr_values']; };
	if (isset($stmt_array['message']) ) { $message = $stmt_array['message']; }
	$dbMessage = ''; $dbMessageGood = '';
	if ( !$statement = $conn->prepare($stmt) ) { $dbMessage = "Verbindung war nicht erfolgreich. "; $dbMessageGood = "false"; }
	else {
		if ( $str_types != '' AND !$statement->bind_param($str_types, ...$arr_values) ) { $dbMessage = "Übertragung war nicht erfolgreich. "; $dbMessageGood = "false"; }
		else {
			if ( !$statement->execute() ) { $dbMessage = "Operation war nicht erfolgreich. "; $dbMessageGood = "false"; }
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
	$_return = array();
	if ( isset($result) ) { $_return['result'] = $result; }; $_return['dbMessage'] = $dbMessage; $_return['dbMessageGood'] = $dbMessageGood; $_return['insert_id'] = $conn->insert_id;
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

//initialize new config
function initConfig(array $config, mysqli $conn)
{
	if ( ! isset($config['configname']) ) { return; }
	unset($_stmt_array);
	$_stmt_array = array();
	$json_config = json_encode($config);
	$_stmt_array['stmt'] = "INSERT INTO os_userconfig_".$_SESSION['os_user']." (userid,configname) VALUES (?,?)";
	$_stmt_array['str_types'] = "is";
	$_stmt_array['arr_values'] = array();
	$_stmt_array['arr_values'][] = $_SESSION['os_user'];
	$_stmt_array['arr_values'][] = $config['configname'];
	$_result_array = execute_stmt($_stmt_array,$conn); 
//	if ($_result_array['dbMessageGood']) { }; //define an output area for db result messages! 
}

//delete config
function removeConfig(array $config, mysqli $conn)
{
	if ( ! isset($config['configname']) ) { return; }
	//do not remove the default config
	if ( $config['configname'] == 'Default' ) { return; }
	//
	unset($_stmt_array);
	$_stmt_array = array();
	$_stmt_array['stmt'] = "DELETE FROM os_userconfig_".$_SESSION['os_user']." WHERE userid = ? AND configname = ?";
	$_stmt_array['str_types'] = "is";
	$_stmt_array['arr_values'] = array();
	$_stmt_array['arr_values'][] = $_SESSION['os_user'];
	$_stmt_array['arr_values'][] = $config['configname'];
	$_result_array = execute_stmt($_stmt_array,$conn); 
//	if ($_result_array['dbMessageGood']) { }; //define an output area for db result messages! 
}

//replaces config by given array
function updateConfig(array $config, mysqli $conn, string $configname = 'Default')
{
	if ( sizeof($config) > 0 )
	{
		unset($_stmt_array);
		$_stmt_array = array();
		$json_config = json_encode($config);
		$_stmt_array['stmt'] = "SELECT id FROM os_userconfig_".$_SESSION['os_user']." WHERE userid = ? AND configname = ?";
		$_stmt_array['str_types'] = "is";
		$_stmt_array['arr_values'] = array();
		$_stmt_array['arr_values'][] = $_SESSION['os_user'];
		$_stmt_array['arr_values'][] = $configname;
		$_result_configname = execute_stmt($_stmt_array,$conn); 
		unset($_stmt_array);
		if ( isset($_result_configname['result']) ) {
			$_stmt_array['stmt'] = "UPDATE os_userconfig_".$_SESSION['os_user']." SET config = ? WHERE userid = ? AND configname = ?";
			$_stmt_array['str_types'] = "sis";
			$_stmt_array['arr_values'] = array();
			$_stmt_array['arr_values'][] = $json_config;
			$_stmt_array['arr_values'][] = $_SESSION['os_user'];
			$_stmt_array['arr_values'][] = $configname;
			$_result_array = execute_stmt($_stmt_array,$conn); 
		} else {
			$_stmt_array = array();
			$_stmt_array['stmt'] = "INSERT INTO os_userconfig_".$_SESSION['os_user']." (config,userid,configname) VALUES (?,?,?)";
			$_stmt_array['str_types'] = "sis";
			$_stmt_array['arr_values'] = array();
			$_stmt_array['arr_values'][] = $json_config;
			$_stmt_array['arr_values'][] = $_SESSION['os_user'];
			$_stmt_array['arr_values'][] = $configname;
			$_result_array = execute_stmt($_stmt_array,$conn); 		
		} 
		//define an output area for db result messages!
		if ($_result_array['dbMessageGood']) { }; //define an output area for db result messages!
	}
}

function updateCustomConfig(array $config, mysqli $conn)
{
	if ( ! isset($config['configname']) ) { return; }
	$_wait = updateConfig($config,$conn,$config['configname']);
}

//reads config
function getConfig(mysqli $conn, string $configname = 'Default') 
{
	unset($_stmt_array);
	$_stmt_array = array();
	$_stmt_array['stmt'] = "SELECT config FROM os_userconfig_".$_SESSION['os_user']." WHERE userid = ? AND configname = ?";
	$_stmt_array['str_types'] = "is";
	$_stmt_array['arr_values'] = array();
	$_stmt_array['arr_values'][] = $_SESSION['os_user'];
	$_stmt_array['arr_values'][] = $configname;
	$_result_array = execute_stmt($_stmt_array,$conn); 
	if ($_result_array['dbMessageGood']) { $_config = json_decode($_result_array['result']['config'][0],true); }; //serializes as associative array
	return $_config; 
}

//adds (and removes) filter information to config
function addToConfig(array $diff, mysqli $conn, bool $add=true, string $configname = 'Default')
{
	$_config = getConfig($conn,$configname);
/*	unset($_stmt_array);
	$_stmt_array = array();
	$_stmt_array['stmt'] = "SELECT config FROM os_userconfig WHERE userid = ?";
	$_stmt_array['str_types'] = "i";
	$_stmt_array['arr_values'] = array();
	$_stmt_array['arr_values'][] = $_SESSION['os_user'];
	$_result_array = execute_stmt($_stmt_array,$conn); 
	if ($_result_array['dbMessageGood']) { $_config = json_decode($_result_array['result']['config'][0],true); }; //serializes as associative array */ 
	if ($add) 
	{
		foreach ( $diff as $key=>$value ) {
			if ( ! array_key_exists($key,$_config['filters']) ) 
			{
				$_config['filters'][$key] = array('_all');
			}
		}
	}
	else
	{
		foreach ( $diff as $key=>$value ) {
			if ( array_key_exists($value,$_config['filters']) ) 
			{
				unset($_config['filters'][$value]);
			}
		}
	}

	$_wait = updateConfig($_config,$conn,$configname);
}

//short form for removal
function removeFromConfig(array $diff, mysqli $conn, string $configname = 'Default')
{
	$_wait = addToConfig($diff,$conn,false,$configname);
}

//changes and adds other config; overwrites old values by new ones
function changeConfig(array $newconf, mysqli $conn, string $configname = 'Default')
{
	//callFunction cannot transfer fixed variables like configname, so we have to transfer it inside newconf...
	//if ( array_key_exists('configname',$newconf) ) { $configname=$newconf['configname']; unset($newconf['configname']); changeConfig($newconf,$conn,$configname); return; }
	//
	$conf = getConfig($conn,$configname);
	foreach ( $newconf as $key=>$value )
	{
		$conf[$key] = $value;
		if ( is_array($value) ) {
			foreach ( $value as $key2=>$value2 )
			{
				if ( ! isset($value2) OR $value2 == '' ) { unset($conf[$key][$key2]); };
				if ( is_array($value2) ) {
					foreach ( $value2 as $key3=>$value3 )
					{
						if (  ! isset($value3) OR $value3 == '' ) { unset($conf[$key][$key2][$key3]); };
						if ( is_array($value3) ) {
							foreach ( $value3 as $key4=>$value4 )
							{
								if (  ! isset($value4) ) { unset($conf[$key][$key2][$key3][$key4]); };
							}
						}
					}
				}
			}
		}
	}
	$_wait = updateConfig($conf,$conn,$configname);
}

function changeCustomConfig (array $newconf, mysqli $conn)
{
	if ( ! isset($newconf['configname']) ) { return; }
	$_wait = changeConfig($newconf,$conn,$newconf['configname']);
}

function copyConfig(array $config, mysqli $conn)
{
	if ( ! isset($config['configname']) ) { return; }
	$configname = $config['configname'];
	$defaultconfig = getConfig($conn);
	$defaultconfig['configname'] = $config['configname'];	
	$_wait = updateConfig($defaultconfig,$conn,$configname);
	$_wait = updateConfig($defaultconfig,$conn);
}

function removeOpenId(array $entry, mysqli $conn)
{
	$conf = getConfig($conn);
	foreach ( $entry as $tableidjson ) {
		$tableid = json_decode($tableidjson,true);
		foreach ( $conf['_openids'] as $key=>$value ) {
			if ( $tableid == $value ) { 				
				//unset($conf['_openids'][$key]); array is not associative, so splice it better...
				array_splice($conf['_openids'],$key,1); 
				}
		}
	}
	return updateConfig($conf,$conn);
}

function generateFilterStatement(array $parameters, mysqli $conn, string $_table = 'os_all', bool $complement = false)
{
	function _addToFilterStatement ($values,$filter_results,$komma,$_newkomma = '',$keyreadable,$index,$value,$tmpvalue = '',$separator = '',$emptyisall = false) {
		switch($index) {
			case 1001:  
				if ( json_encode($value) == '[""]' ) { $value = array('1970-01-01'); };
//						$filter_results .= $komma . ' von '. _cleanup(json_encode($value)) . '<br>'; $komma = ' ';
				$tmpvalue = $value;
				break;
			case 1002:  
				if ( json_encode($value) == '[""]' ) { $value = array('2070-01-01'); };
				if ( $separator == '' ) { $separator = ', <br /><span style="opacity:0"><b>'.$keyreadable.'</b> = </span>'; }
				$value_combined = array_combine($tmpvalue,$value);
				foreach ( $value_combined as $von=>$bis ) {
					$filter_results .= $komma . ' von ' . _cleanup($von) . ' bis '. _cleanup($bis); $komma = $separator;
				}
				unset($tmpvalue);
				break;
			case 5001:  
				if ( json_encode($value) == '[""]' ) { $value = array('0'); };
//						$filter_results .= $komma . ' von '. _cleanup(json_encode($value)) . '<br>'; $komma = ' ';
				$tmpvalue = $value;
				break;
			case 5002:  
				if ( json_encode($value) == '[""]' ) { $value = array('1000000000'); };
				if ( $separator == '' ) { $separator = ', <br /><span style="opacity:0"><b>'.$keyreadable.'</b> = </span>'; }
				$value_combined = array_combine($tmpvalue,$value);
				foreach ( $value_combined as $von=>$bis ) {
					$filter_results .= $komma . ' von ' . _cleanup($von) . ' bis '. _cleanup($bis); $komma = $separator;
				}
				unset($tmpvalue);
				break;
			case 6001:
				$cmp_index = 0;
				$cmp_values = array();
				while ( array_key_exists(6001+$cmp_index,$values) ) {
					array_push($cmp_values,$values[6001+$cmp_index]);
					$cmp_index++;					
				}
				$filter_length = _len($cmp_values);
				$separator = ' + ';
				for ( $j = 0; $j < $filter_length; $j++ ) { // $j is item nunmber
					$item_values = array();
					for ( $i = 0; $i < $cmp_index; $i++ ) { // $i is compound number
						array_push($item_values,_extract($cmp_values,$i,$j));
					}
					foreach ( $item_values as $compound ) {
						foreach ( $compound as $cmpindex=>$cmpvalue ) 
						{
							$_tmpresult = _addToFilterStatement($compound,$filter_results,$komma,$_newkomma,$keyreadable,$cmpindex,$cmpvalue,$tmpvalue,$separator,true); // 'true': empty is 'all'
							$separator = ' + ';
							$filter_results = $_tmpresult[0]; $tmpvalue = $_tmpresult[1]; $komma = $_tmpresult[2];
						}
					}
					$komma = $_newkomma.' <br /><span style="opacity:0"><b>'.$keyreadable.'</b> = </span>';
				}
				break;
			case 1003:
			case 5003:
			case 2001:
			case 3001:
				break;
			default: 
				if ( $emptyisall AND $value == '' ) { $value = '[ungefiltert]'; }
				if ( $value != '_all' AND $index < 6001 ) { 
					$filter_results .= $komma . _cleanup($value); 
					if ( $separator == '' ) { $komma = $_newkomma; } else { $komma = $separator; }
				}; 
				break;
		}
		return array($filter_results,$tmpvalue,$komma);
	}
	$_config = getConfig($conn);
	$_TABLES = $_config['table'];
	$filter_results = '';
	if ( $complement ) { $filter_results = '<b>Komplement von</b><br /><br />'; }
	foreach ( $parameters as $tablekey=>$values )
	{
		if ( isset($parameters['table']) ) { $table = $parameters['table'][0]; } else { $table = $_table; };
		if ( ! strpos($tablekey,'__') OR strpos($tablekey,'__id_') ) { continue; }
		
		$tablekey_array = explode('__',$tablekey,2);
		$table = $tablekey_array[0];
		$key = $tablekey_array[1];
		if ( in_array($key,array('table')) ) { continue; }
		if ( ! in_array($table,$_TABLES) ) { continue; }
		unset($_stmt_array); $_stmt_array = array();
		if ( in_array($key,$_TABLES) ) {
			$_stmt_array['stmt'] = "SELECT CONCAT('#',tablereadable) AS keyreadable FROM os_tables WHERE tablemachine = ?";
			$_stmt_array['str_types'] = "s";
			$_stmt_array['arr_values'] = array();
			$_stmt_array['arr_values'][] = $key;
			$_tmp_result = array();
		} else {
			$_stmt_array['stmt'] = "SELECT keyreadable FROM ".$table."_permissions WHERE keymachine = ?";
			$_stmt_array['str_types'] = "s";
			$_stmt_array['arr_values'] = array();
			$_stmt_array['arr_values'][] = $key;
			$_tmp_result = array();
		}
		$keyreadable = explode(': ',execute_stmt($_stmt_array,$conn)['result']['keyreadable'][0])[0];
		unset($values[-1]); $tmpvalues = $values; unset($tmpvalues[2001]);
		if ( sizeof($tmpvalues) > 1 ) 
		{
			$filter_results .= '<b>'.$keyreadable.'</b>';
			$komma = ' = ';
			unset($index); unset($value);
			if ( isset($values[2001]) AND $values[2001] == "-499" ) { $_newkomma = '+'; } else { $_newkomma = ', '; }
			if ( isset($values[3001]) ) { $komma = " &#8800; "; }
			$tmpvalue = '';
			foreach ( $values as $index=>$value ) 
			{
				$_tmpresult = _addToFilterStatement($values,$filter_results,$komma,$_newkomma,$keyreadable,$index,$value,$tmpvalue);
				$filter_results = $_tmpresult[0]; $tmpvalue = $_tmpresult[1]; $komma = $_tmpresult[2];
			}
		$filter_results .= '<br />';
		}
	}
	if ( $filter_results == '' ) { $filter_results = "Keine"; }
	return $filter_results;
}

function generateResultTable(array $stmt_array, mysqli $conn, string $table = 'os_all')
{
	$_result_array = _execute_stmt($stmt_array,$conn); $result = $_result_array['result'];
	$_config = getConfig($conn);
	if ( isset($_config['hiddenColumns']) ) { $HIDDEN = $_config['hiddenColumns']; } else { $HIDDEN = array(); };
	$TABLES = $_config['table'];
	$ICON = array(); $TABLEREADABLE = array();
	foreach ( $TABLES as $table )
	{
		unset($_stmt_array); $_stmt_array = array();
		$_stmt_array['stmt'] = "SELECT iconname,tablereadable FROM os_tables WHERE tablemachine = ?";
		$_stmt_array['str_types'] = "s";
		$_stmt_array['arr_values'] = array();
		$_stmt_array['arr_values'][] = $table;
		$_thisresult = execute_stmt($_stmt_array,$conn)['result'];
		$ICON[$table] = $_thisresult['iconname'][0];
		$TABLEREADABLE[$table] = $_thisresult['tablereadable'][0];
		unset($_thisresult);
	}
	unset($table);
	unset($oldvalue);
	$oldvalue = array();
	$table_results = "<form id=\"formMassEdit\" method=\"POST\" class=\"noreset\"></form>";
	$table_results .= "<table id=\"db_results\">";
	$rcount = 0;
	if ( $result->num_rows > 0 ) {
		while ($row=$result->fetch_assoc()) {
				$table_results .= "<tr>";
				if ( $rcount == 0 ) {
					$table_results .= "<th>
						<input 
							form=\"formMassEdit\"
							type=\"checkbox\"
							id=\"editAll\"
							onclick=\"_toggleEditAll('formMassEdit','editAll')\"
						>
						<input 
							form=\"formMassEdit\"
							type=\"text\"
							id=\"editTableName\"
							name=\"massEdit[]\"
							hidden
						>
						</th>";
					foreach ($row as $tablekey=>$value) {
						if ( strpos($tablekey,'__id_') > 0 ) { continue; }
						$tablekey_array = explode('__',$tablekey,2);
						$table = $tablekey_array[0];
						$key = $tablekey_array[1];
						if ( in_array($key,$TABLES) ) {
							$keyreadable = "#".$TABLEREADABLE[$key];
						} else {						
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "SELECT keyreadable FROM ".$table."_permissions WHERE keymachine = ?";
							$_stmt_array['str_types'] = "s";
							$_stmt_array['arr_values'] = array();
							$_stmt_array['arr_values'][] = $key;
							$_tmp_result = array();
							$keyreadable = explode(': ',execute_stmt($_stmt_array,$conn)['result']['keyreadable'][0])[0];
						}
						if ( in_array($tablekey,$HIDDEN) ) { $_hidden = 'hidecolumn'; } else { $_hidden = ''; }
						$table_results .= "<th class=\"disabled\" title=\"".$keyreadable."\" onclick=\"_toggleColumn(this,'". $tablekey ."');\"><i class=\"fas fa-angle-down\"></i></th><th class=\"tableheader " . $tablekey . " " . $_hidden . "\" onclick=\"_toggleColumn(this,'". $tablekey ."');\">" . $keyreadable . "</th>";
					}
					$table_results .= "</tr><tr>";
				}
				$_editvalue = array();
				foreach ( $TABLES as $table ) 
				{
					if ( isset($row[$table.'__'.'id_'.$table]) ) {
						$_editvalue['id_'.$table] = $row[$table.'__'.'id_'.$table];
					}
				} 
				$table_results .= "<td>
					<input
						form=\"formMassEdit\"
						type=\"checkbox\"
						name=\"massEdit[]\"
						value=\"".htmlentities(json_encode($_editvalue))."\"
					></td>"; 
				foreach ($row as $key=>$value) {
					if ( strpos($key,'__id_') > 0 ) { continue; }
					$value = _cleanup($value);
					$table = explode('__',$key,2)[0];
					if ( in_array($key,$HIDDEN) ) { $_hidden = 'hidecolumn'; } else { $_hidden = ''; }
					if ( isset($oldvalue[$table]) AND $row[$table.'__'.'id_'.$table] == $oldvalue[$table] ) {
						$table_results .= "<td>&nbsp;</td><td class=\"disabled " . $key . " " . $_hidden . "\">" . $value . "</td>";
					} else {
						$table_results .= "<td>&nbsp;</td><td class=\"" . $key . " " . $_hidden . "\">" . $value . "</td>";
					}
					$oldvalue[$key] = $value;
				}
				foreach ( $TABLES as $table ) 
				{
					if ( isset($row[$table.'__'.'id_'.$table]) ) {
						$table_results .= "<td title=\"ID: ".$row[$table.'__'.'id_'.$table]."\" id=\"detailsTd_". $table . $row[$table.'__'.'id_'.$table] . "\" draggable=\"true\" ondragover=\"allowDrop(event)\" ondrop=\"dropOnDetails(event,this)\" ondragstart=\"dragOnDetails(event)\" ondragenter=\"dragenter(event)\" ondragleave=\"dragleave(event)\" ondragend=\"dragend(event)\"><form method=\"post\" id=\"detailsForm". $table . $row[$table.'__'.'id_'.$table] . "\" class=\"inline\" action=\"\" onsubmit=\"editEntries(this,'".$table."'); return false\"><input hidden form=\"detailsForm". $table . $row[$table.'__'.'id_'.$table] . "\"type=\"text\" value=\"". $row[$table.'__'.'id_'.$table] . "\" name=\"id_".$table."\" /><input form=\"detailsForm". $table . $row[$table.'__'.'id_'.$table] . "\" id=\"detail". $table . $row[$table.'__'.'id_'.$table] . "\" type=\"submit\" hidden /></form>";
						if ( isset($oldvalue[$table]) AND $row[$table.'__'.'id_'.$table] == $oldvalue[$table] ) {
							$table_results .= "<label for=\"detail". $table . $row[$table.'__'.'id_'.$table] . "\"><i class=\"disabled fas fa-".$ICON[$table]."\"></i></label></td>";
						} else {
							$table_results .= "<label for=\"detail". $table . $row[$table.'__'.'id_'.$table] . "\"><i class=\"fas fa-".$ICON[$table]."\"></i></label></td>";
						}
						$oldvalue[$table] = $row[$table.'__'.'id_'.$table];
					} else {
						$table_results .= "<td>&nbsp;</td>";
					}
				}
				$rnd2 = rand(0,32767);
				$table_results .= "
				<td>
					<input type=\"checkbox\" hidden class=\"toggle\" id=\"newEntries_".$rnd2."\">
					<label for=\"newEntries_".$rnd2."\"><i class=\"fas fa-plus\"></i></label>
					<div class=\"form newEntry\">
						<input type=\"checkbox\" hidden class=\"toggle\" id=\"newEntries_".$rnd2."\">
						<label for=\"newEntries_".$rnd2."\"><i class=\"fas fa-plus\"></i>&nbsp;</label>";
				foreach ( $TABLES as $table ) {
					if ( $table == $TABLES[0] ) { continue; }
					$table_results .= "<label onclick=\"_setValue(this,'".$table."',1);\"><i class=\"fas fa-".$ICON[$table]."\"></i>&nbsp;</label>";
				}
				$table_results .= "
						<form method=\"POST\">
							<input type=\"number\" hidden name=\"id_".$TABLES[0]."\" value=\"".$row[$TABLES[0].'__id_'.$TABLES[0]]."\">
							<input type=\"text\" hidden name=\"table[]\" value=\"\">
						</form>					
					</div>
				</td>";
				$table_results .= "</tr> ";
				$rcount++;
		}
	} else { $table_results .= "<tr><td>Ihre Suche liefert leider keine Ergebnisse.</td><tr>"; };
	$table_results .= "</table>";
	return $table_results;
}

function generateStatTable (array $stmt_array, mysqli $conn, string $table = 'os_all') 
{
	$_result_array = execute_stmt($stmt_array,$conn,true); //flip the result array to get rows
	$result = $_result_array['result'];
	$table_results = "<div id=\"db_stat\">";
	$rcount = 0;
	$vrcount = array();
	$ccount = 0;
	$oldrow = array();
	$ccount_old = array();
	$rcount_old = array();
	$tmp_ccount_old = array();
	$tmp_rcount_old = array();
	$keys = array();
	$vrcount = array(); //varying rows /unique rows
	$vrcount_old = array(); 
	$rrcount = array(); //result rows
	$rrcount_old = array();
	$keyreadable = array();
	$edittype = array();
	
	//sortArray by Thomas Heuer
	function sortArray($data, $field)
	{
    if(!is_array($field)) $field = array($field);
    usort($data, function($a, $b) use($field) {
      $retval = 0;
      foreach($field as $fieldname) {
        if($retval == 0 AND $fieldname != '_none_') $retval = strnatcmp($a[$fieldname],$b[$fieldname]);
      }
      return $retval;
    });
    return $data;
	}
	
	//getConfig
	$config = getConfig($conn);
	
	if ( isset($result) AND sizeof($result) > 0 ) {
		foreach ( $result as $result_index => $_row ) {
//		for ( $i = 0; $i < sizeof($result); $i++ ) {
//			$row = $result[$i];
			$row_left = ''; $row_right = ''; $row_mostright = '';
			if ( $rcount == 0 ) {
				$ccount0 = 0;
				foreach ($_row as $tablekey=>$value) {
					if ( strpos($tablekey,'__id_')  ) { continue; }
					$tablekey_array = explode('__',$tablekey,2);
					$table = $tablekey_array[0];
					$key = $tablekey_array[1];
					$keys[] = $tablekey;
					$vrcount[$tablekey] = 0;
					$rrcount[$tablekey] = 0;
					$oldrow[$tablekey] = "x678dscjncxydsv&%/&"; //something that surely is no entry in the table (NULL etc my occur...)
					if ( $key == "id" ) { continue; }
					if ( in_array($key,$config['table']) ) {
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "SELECT CONCAT('#',tablereadable) AS keyreadable FROM os_tables WHERE tablemachine = ?";
						$_stmt_array['str_types'] = "s";
						$_stmt_array['arr_values'] = array();
						$_stmt_array['arr_values'][] = $key;
						$_tmp_result = execute_stmt($_stmt_array,$conn)['result'];
						$keyreadable[$key] = explode(': ',$_tmp_result['keyreadable'][0])[0];
						$edittype[$tablekey] = 'INTEGER';
					} else {					
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "SELECT keyreadable,edittype FROM ".$table."_permissions WHERE keymachine = ?";
						$_stmt_array['str_types'] = "s";
						$_stmt_array['arr_values'] = array();
						$_stmt_array['arr_values'][] = $key;
						$_tmp_result = execute_stmt($_stmt_array,$conn)['result'];
						$keyreadable[$key] = explode(': ',$_tmp_result['keyreadable'][0])[0];
						$edittype[$tablekey] = $_tmp_result['edittype'][0];
					}
					$table_results .= '<div class="nextlevel"><div class="unique value header" onclick="_toggleStatColumn('.$ccount0.','.sizeof($result).')">'.$keyreadable[$key].'</div>'; $row_right .= '</div>';
					$ccount0++;
				}
				$keys[] = '_none_'; $vrcount['_none_'] = 0; $rrcount['_none_'] = 0;
				$oldrow['_none_'] = "x678dscjncxydsv&%/&"; //something that surely is no entry in the table (NULL etc my occur...)
				$table_results .= $row_right; $row_right = '';
				$table_results .= '<hr>';
				$table_results .= '<div class="nextlevel">';
				$table_results .= '<input type="checkbox" class="toggle" id="stat'.$rcount.'x'.$ccount.'" hidden >'; 
				$table_results .= '<input type="checkbox" class="toggle" id="content'.$rcount.'x'.$ccount.'" hidden >'; 
				$table_results .= '<div class="column form"><label for="content'.$rcount.'x'.$ccount.'"><ul>';
				$tmp_rcount_old[$keys[0]] = $rcount;
				$tmp_ccount_old[$keys[0]] = $ccount;
			}
			unset($row); $row = array(); $row = $_row; $row['_none_'] = ''; $edittype['_none_'] = '';
			$rcount++;
			$ccount = 0;
			$new = false;
			$mustresort = false;
			for ( $i = 0; $i < sizeof($keys); $i++ ) {
				if ( $key == "id" ) { continue; }
				$key = $keys[$i];
				$value = _cleanup($row[$keys[$i]]); //need control over the whole key array: first, last...
				switch($edittype[$key]) {
					case 'DATE':
					case 'DATETIME':
						if ( ! isset($config['filters'][$key][1001]) ) { $value = $row[$key]; break; }
						$mustresort = true;
						for ( $ii = 0; $ii < sizeof($config['filters'][$key][1001]); $ii++ ) {
							if ( strtotime($row[$key]) >= strtotime($config['filters'][$key][1001][$ii]) AND  ( strtotime($row[$key]) <= strtotime($config['filters'][$key][1002][$ii]) OR $config['filters'][$key][1002][$ii] == '' ) )
							{
								if ( isset($config['filters'][$key][1003][$ii]) AND $config['filters'][$key][1003][$ii] != '' ) {
									$result[$result_index][$keys[$i]] = $config['filters'][$key][1003][$ii];
								} else {
									$number = $ii+1;
									$result[$result_index][$keys[$i]] = 'Zeitraum '.$number;
								}
							}		 
						}
						break;
					case 'INTEGER':
					case 'DECIMAL':
						if ( ! isset($config['filters'][$key][5001]) ) { $value = $row[$key]; break; }
						$mustresort = true;
						for ( $ii = 0; $ii < sizeof($config['filters'][$key][5001]); $ii++ ) {
							if ( $row[$key] >= $config['filters'][$key][5001][$ii] AND  ( $row[$key] <= $config['filters'][$key][5002][$ii] OR $config['filters'][$key][5002][$ii] == '' ) )
							{
								$result[$result_index][$keys[$i].'_value'] = $result[$result_index][$keys[$i]];
								if ( isset($config['filters'][$key][5003][$ii]) AND $config['filters'][$key][5003][$ii] != '' ) {
									$result[$result_index][$keys[$i]] = $config['filters'][$key][5003][$ii];
								} else {
									$number = $ii+1;
									$result[$result_index][$keys[$i]] = 'Bereich '.$number;
								}
							}		 
						}
						break;
				}
			}
		}
		//now re-sort after value replacements!
		if ( $mustresort ) { $result = sortArray($result,$keys); }
		//
		$rcount = 0;
		foreach ( $result as $_row ) {
			unset($row); $row = array(); $row = $_row; $row['_none_'] = ''; $edittype['_none_'] = '';
			$rcount++;
			$ccount = 0;
			$new = false;
			$row_left = ''; $row_right = ''; $row_mostright = '';
			for ( $i = 0; $i < sizeof($keys); $i++ ) {			
				if ( $key == "id" ) { continue; }
				$key = $keys[$i];
				$value = _cleanup($row[$keys[$i]]); //need control over the whole key array: first, last...
//			foreach ($row as $key=>$value) {
//				$ccount++; $rrcount[$key]++; //echo($key.':'.$rrcount[$key].' ');
				$ccount++; 
				//take sum if key is a number field, count items otherwise
				switch($edittype[$key]) {
					case 'INTEGER':
					case 'DECIMAL':
						$rrcount[$key] += $row[$key.'_value'];
						break;
					default:
						$rrcount[$key]++;
						break;
				}
//				if ( $vrcount[$oldkey] = 1) { $vrcount[$key] = 1; };

				if ($new OR $oldrow[$key] != $value )
				{
//					$row_right = '</li>'.$row_right;
					$row_left .= '<li><div class="value">'.$value.'</div>';
					$vrcount[$key]++; //count every new entry
					if ( isset($keys[$i+1]) )
					{
						$row_left .= '<div class="nextlevel">';
						$row_left .= '<input type="checkbox" class="toggle" id="stat'.$rcount.'x'.$ccount.'" hidden >'; 
						$row_left .= '<input type="checkbox" class="toggle" id="content'.$rcount.'x'.$ccount.'" hidden >'; 
						$row_left .= '<div class="column form"><label for="content'.$rcount.'x'.$ccount.'"><ul>';
					}
				} 

				if ($new)
				{
					if ( $rcount > 1 ) {
						$row_right = '</div>'.$row_right;
						if ( isset($keys[$i+1]) ) 
						{
							$row_right = '<label class="stat" for="stat'.$rcount_old[$key].'x'.$ccount_old[$key].'"><span class="hits">'.$rrcount_old[$key].'</span>|<span class="unique">'.$vrcount_old[$key].'</span></label>'.$row_right;
						}
						else
						{
							$row_right = '<label class="stat last" id="last'.$rcount_old[$key].'x'.$ccount_old[$key].'"><span class="hits">'.$rrcount_old[$key].'</span></label>'.$row_right;
						}
						$row_right = '</li></ul></label></div>'.$row_right;
					}
				}

				if ( ($new OR $oldrow[$key] != $value ) AND isset($keys[$i+1]) ) 
				{ 
					$tmp_rcount_old[$keys[$i+1]] = $rcount;
					$tmp_ccount_old[$keys[$i+1]] = $ccount;
					$vrcount_old[$keys[$i+1]] = $vrcount[$keys[$i+1]];
					$rrcount_old[$keys[$i+1]] = $rrcount[$keys[$i+1]];
					$vrcount[$keys[$i+1]] = 0;
					$rrcount[$keys[$i+1]] = 0;
				}

				if ( $rcount == sizeof($result) ) {
					$row_mostright = '</div>'.$row_mostright;
						if ( isset($keys[$i+1]) ) 
						{
							$row_mostright = '<label class="stat" for="stat'.$tmp_rcount_old[$key].'x'.$tmp_ccount_old[$key].'"><span class="hits">'.$rrcount[$key].'</span>|<span class="unique">'.$vrcount[$key].'</span></label>'.$row_mostright;
						}
						else
						{
							$row_mostright = '<label class="stat last" id="last'.$tmp_rcount_old[$key].'x'.$tmp_ccount_old[$key].'"><span class="hits">'.$rrcount[$key].'</span></label>'.$row_mostright;
						}
					$row_mostright = '</li></ul></label></div>'.$row_mostright;
				}

				if ( $oldrow[$key] != $value ) { $new = true; }
	
				$oldrow[$key] = $value;
				$oldkey = $key;
			}
			for ( $i = 0; $i < sizeof($keys); $i++ ) {
				$rcount_old[$keys[$i]] = $tmp_rcount_old[$keys[$i]];
				$ccount_old[$keys[$i]] = $tmp_ccount_old[$keys[$i]];
				$vrcount_old[$keys[$i]] = $vrcount[$keys[$i]];
				$rrcount_old[$keys[$i]] = $rrcount[$keys[$i]];
			}			
			$table_results .= $row_right.$row_left.$row_mostright;
		}
//		$table_results .= '</li></ul></div>';
//		$table_results .= '<label for="STAT'.$rcount_old[$key].'x'.$ccount_old[$key].'">'.$vrcount[$key].'</label>';
//		$table_results .= '</div>';
// probably still wrong for more than three keys: need sequences of row sections...?
// analyse mistake in Name Nachname Land > Muster 1 label stat3x1 should be stat2x1!
	}
	$table_results .= '</div>';
	return $table_results;
}

function dbAction(array $_PARAMETER,mysqli $conn) {
	$message = '';
	if ( isset($_SESSION['DEBUG']) AND $_SESSION['DEBUG'] ) { file_put_contents($debugpath.$debugfilename,'PARAM: '.json_encode($_PARAMETER).PHP_EOL,FILE_APPEND); }
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
					$set .= $komma . "`" . $properkey . "`= ?";
					$komma = ",";
					if ( is_array($value) ) { $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); } //added on 20190719
					$arr_values[] = rtrim($value);
					if ( substr($key,0,3) == 'id_') { $str_types .= "i"; } else { $str_types .= "s"; } //replace by a proper type query...
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
	if ( isset($_SESSION['DEBUG']) AND $_SESSION['DEBUG'] ) { file_put_contents($debugpath.$debugfilename,'STATEMENT: '.json_encode($_stmt_array).PHP_EOL,FILE_APPEND); }
	$_return=_execute_stmt($_stmt_array,$conn);
	$_SESSION['insert_id'][] = json_encode(array( 'id_'.$PARAMETER['table'] => $_return['insert_id'] ));
	$return = '<div class="dbMessage '.$_return['dbMessageGood'].'">'.$_return['dbMessage'].'<div hidden class="insertID">'.json_encode($_SESSION['insert_id']).'</div></div>';
	if ( isset($_SESSION['DEBUG']) AND $_SESSION['DEBUG'] ) { file_put_contents($debugpath.$debugfilename,json_encode($_return).PHP_EOL,FILE_APPEND); }
	if ( isset($_SESSION['DEBUG']) AND $_SESSION['DEBUG'] ) { file_put_contents($debugpath.$debugfilename,PHP_EOL,FILE_APPEND); }
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

//returns the calendar login data
function calAction(array $PARAMETER,mysqli $conn) {
	if ( ! isset($PARAMETER['id_os_calendars']) OR $PARAMETER['id_os_calendars'] == '' ) { return; };
	unset($_stmt_array); $_stmt_array = array(); unset($_result_array);
	$_stmt_array['stmt'] = "SELECT allowed_roles, allowed_users FROM view__os_calendars__".$_SESSION['os_role']." WHERE id_os_calendars = ?";
	$_stmt_array['str_types'] = "i";
	$_stmt_array['arr_values'] = array();
	$_stmt_array['arr_values'][] = $PARAMETER['id_os_calendars'];
	$_result_array = execute_stmt($_stmt_array,$conn,true)['result'][0]; //keynames as last array field
	$_result = array();
	if ( in_array($_SESSION['os_user'],json_decode($_result_array['allowed_users'])) OR in_array($_SESSION['os_role'],json_decode($_result_array['allowed_roles'])) OR in_array($_SESSION['os_parent'],json_decode($_result_array['allowed_roles'])) )
	{ 
		unset($_stmt_array); $_stmt_array = array(); unset($_result_array);
		$_stmt_array['stmt'] = "SELECT calendarurl, calendarpwd, calendaruser FROM view__os_calendars__".$_SESSION['os_role']." WHERE id_os_calendars = ?";
		$_stmt_array['str_types'] = "i";
		$_stmt_array['arr_values'] = array();
		$_stmt_array['arr_values'][] = $PARAMETER['id_os_calendars'];
		$_result = execute_stmt($_stmt_array,$conn,true)['result'][0]; //keynames as last array field 
	}
	if ( sizeof($_result) > 0 ) 
	{
		switch($PARAMETER['dbAction']) {
			case 'edit': 
				_CALDAVUpdate($PARAMETER,$_result,$conn);
				break;
			case 'insert':
				_CALDAVInsert($PARAMETER,$_result,$conn);
				break;
			case 'delete':
				_CALDAVDelete($PARAMETER,$_result,$conn);
				break;
		}
	}
	unset($_result);
}

function _CALDAVInsert(array $PARAMETER, array $_result, mysqli $conn)
{
	//get id of insert in table
	if ( ! isset($_SESSION['insert_id']) ) { return; } else { $_id_table = $_SESSION['insert_id']; unset($_SESSION['insert_id']); }; 
	//PUT request to calendar
	$_icsid = rand(0,2147483647);
	$_uid = rand(0,2147483647);
	$_header = array("Content-Type: text/calendar; charset=utf-8");
	$_created = gmdate('Ymd\THis\Z',strtotime("now"));
	$_times = array();
	$_caldavfields = _CALDAVgetFields($PARAMETER);
	$_body = _generateVCALENDAR($_created,'',$_uid,$_caldavfields['summary'],$_caldavfields['dtstart'],$_caldavfields['dtend']);
	$_put = curl_init($_result['calendarurl'].'/'.$_icsid.'.ics');
	curl_setopt($_put, CURLOPT_HEADER, 1);
	curl_setopt($_put, CURLOPT_CUSTOMREQUEST, "PUT");
	curl_setopt($_put, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($_put, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
//	curl_setopt($_put, CURLOPT_USERNAME, $_result['calendaruser']);
	curl_setopt($_put, CURLOPT_USERPWD, $_result['calendaruser'].':'.$_result['calendarpwd']);
	curl_setopt($_put, CURLOPT_HTTPHEADER, $_header);
	curl_setopt($_put, CURLOPT_POSTFIELDS, $_body);
	$_returned = curl_exec($_put);
	curl_close($_put);
	//extract ETag:
	$_etag = substr($_returned,strpos($_returned,"ETag"));
	$_etag = substr($_etag,strpos($_etag,'"')+1);
	$_etag = substr($_etag,0,strpos($_etag,'"'));
	//save eTag in os_caldav
	unset($_stmt_array); $_stmt_array = array(); unset($_result_array);
	$_stmt_array['stmt'] = "INSERT INTO os_caldav (tablemachine,id_table,id_os_calendars,icsid,etag) VALUES (?,?,?,?,?)";
	$_stmt_array['str_types'] = "siiis";
	$_stmt_array['arr_values'] = array();
	$_stmt_array['arr_values'][] = $PARAMETER['table'];
	$_stmt_array['arr_values'][] = $_id_table;
	$_stmt_array['arr_values'][] = $PARAMETER['id_os_calendars'];
	$_stmt_array['arr_values'][] = $_icsid;
	$_stmt_array['arr_values'][] = $_etag;
	execute_stmt($_stmt_array,$conn,true);
}
function _CALDAVgetFields(array $PARAMETER)
{
	$_return = array();	
	foreach ( $PARAMETER as $key=>$value )
	{
		if ( ($_time = strtotime($value)) !== false ) 
		{ $_times[] = $_time; }
		else
		{
			$properkey_array = explode('__',$key,2);
			$properkey = $properkey_array[sizeof($properkey_array)-1];
			if  ( substr($properkey,0,3) != 'id_' AND $properkey != 'table' AND substr($properkey,0,7) != 'changed' AND ! isset($_summary) )
			{
				$_summary = substr($value,0,30);
			}
		}
	}
	if ( sizeof($_times) > 1 )
	{
		if ( $_times[0] < $_times[1] ) { $_dtstart = $_times[0]; $_dtend = $_times[1]; }
		else { $_dtstart = $_times[1]; $_dtend = $_times[0]; }
	}
	if ( sizeof($_times) == 1 )
	{
		$_dtstart = $_times[0]; $_dtend = '';
	}
	if ( sizeof($_times) == 0 ) { return; }
	$_dtstart = date('Ymd\THis',$_dtstart);	
	$_dtend = date('Ymd\THis',$_dtend);
	$_return['summary'] = $_summary; $_return['dtstart'] = $_dtstart; $_return['dtend'] = $_dtend;
	return $_return;					
}

function _CALDAVDelete(array $PARAMETER, array $_result, mysqli $conn)
{
	//delete entry in os_caldav and on caldav server
	unset($_stmt_array); $_stmt_array = array(); unset($_result_array);
	$_stmt_array['stmt'] = "SELECT icsid,etag FROM os_caldav WHERE tablemachine = ? AND id_table = ?";
	$_stmt_array['str_types'] = "si";
	$_stmt_array['arr_values'] = array();
	$_stmt_array['arr_values'][] = $PARAMETER['table'];
	$_stmt_array['arr_values'][] = $PARAMETER['id_'.$PARAMETER['table']];
	$_result_array = execute_stmt($_stmt_array,$conn,true)['result'][0];
	$_header = array('If-Match: "'.$_result_array['etag'].'"');
	$_delete = curl_init($_result['calendarurl'].'/'.$_result_array['icsid'].'ics');
	curl_setopt($_delete, CURLOPT_HEADER, 1);
	curl_setopt($_delete, CURLOPT_CUSTOMREQUEST, "DELETE");
	curl_setopt($_delete, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($_delete, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
//	curl_setopt($_delete, CURLOPT_USERNAME, $_result['calendaruser']);
	curl_setopt($_delete, CURLOPT_USERPWD, $_result['calendaruser'].':'.$_result['calendarpwd']);
	curl_setopt($_delete, CURLOPT_HTTPHEADER, $_header);
	$_returned = curl_exec($_delete);
	curl_close($_delete);
	//delete also from os_caldav
	unset($_stmt_array); $_stmt_array = array(); unset($_result_array);
	$_stmt_array['stmt'] = "DELETE FROM os_caldav WHERE tablemachine = ? AND id_table = ?";
	$_stmt_array['str_types'] = "si";
	$_stmt_array['arr_values'] = array();
	$_stmt_array['arr_values'][] = $PARAMETER['table'];
	$_stmt_array['arr_values'][] = $PARAMETER['id_'.$PARAMETER['table']];
	execute_stmt($_stmt_array,$conn,true);
}

function _CALDAVUpdate(array $PARAMETER, array $_result, mysqli $conn)
{
	//get caldav data of current entry in order to change it properly (and then do insert actions...)
	//use php-curl to delete and create new entry on caldav server when id_os_calendars changed
	unset($_stmt_array); $_stmt_array = array(); unset($_result_array);
	$_stmt_array['stmt'] = "SELECT id_os_calendars,icsid,etag from os_caldav WHERE tablemachine = ? AND id_table = ? ";
	$_stmt_array['str_types'] = "si";
	$_stmt_array['arr_values'] = array();
	$_stmt_array['arr_values'][] = $PARAMETER['table'];
	$_stmt_array['arr_values'][] = $PARAMETER['id_'.$PARAMETER['table']];
	$_result_array = execute_stmt($_stmt_array,$conn,true)['result'][0]; //keynames as last array field 
	if ( $_result_array['id_os_calendars'] != $PARAMETER['id_os_calendars'] )
	{
		//delete old entry and insert new
		unset($_stmt_array); $_stmt_array = array();
		$_stmt_array['stmt'] = "SELECT allowed_roles, allowed_users FROM view__os_calendars__".$_SESSION['os_role']." WHERE id_os_calendars = ?";
		$_stmt_array['str_types'] = "i";
		$_stmt_array['arr_values'] = array();
		$_stmt_array['arr_values'][] = $_result_array['id_os_calendars'];
		$_old_result_array = execute_stmt($_stmt_array,$conn,true)['result'][0]; //keynames as last array field
		$_old_result = array();
		if ( in_array($_SESSION['os_user'],json_decode($_old_result_array['allowed_users'])) OR in_array($_SESSION['os_role'],json_decode($_old_result_array['allowed_roles'])) OR in_array($_SESSION['os_parent'],json_decode($_old_result_array['allowed_roles'])) )
		{ 
			unset($_stmt_array); $_stmt_array = array(); unset($_old_result_array);
			$_stmt_array['stmt'] = "SELECT calendarurl, calendarpwd, calendaruser FROM view__os_calendars__".$_SESSION['os_role']." WHERE id_os_calendars = ?";
			$_stmt_array['str_types'] = "i";
			$_stmt_array['arr_values'] = array();
			$_stmt_array['arr_values'][] = $_result_array['id_os_calendars'];
			$_old_result = execute_stmt($_stmt_array,$conn,true)['result'][0]; //keynames as last array field 
		}
		if ( sizeof($_old_result) > 0 )
		{
			_CALDAVDelete($PARAMETER,$_old_result,$conn);
		}
		$_SESSION['insert_id'] = $PARAMETER['id_'.$PARAMETER['table']];
		_CALDAVInsert($PARAMETER,$_result,$conn);
	} else {
		//update entry
		$_uid = rand(0,2147483647);
		$_header = array('Content-Type: text/calendar; charset=utf-8','If-Match: "'.$_result_array['etag'].'"');
		$_created = gmdate('Ymd\THis\Z',strtotime("now"));
		$_times = array();
		$_caldavfields = _CALDAVgetFields($PARAMETER);
		$_body = _generateVCALENDAR('',$_created,$_uid,$_caldavfields['summary'],$_caldavfields['dtstart'],$_caldavfields['dtend']);
		$_put = curl_init($_result['calendarurl'].'/'.$_result_array['icsid'].'.ics');
		curl_setopt($_put, CURLOPT_HEADER, 1);
		curl_setopt($_put, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($_put, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($_put, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	//	curl_setopt($_put, CURLOPT_USERNAME, $_result['calendaruser']);
		curl_setopt($_put, CURLOPT_USERPWD, $_result['calendaruser'].':'.$_result['calendarpwd']);
		curl_setopt($_put, CURLOPT_HTTPHEADER, $_header);
		curl_setopt($_put, CURLOPT_POSTFIELDS, $_body);
		$_returned = curl_exec($_put);
		curl_close($_put);
		//extract ETag:
		$_etag = substr($_returned,strpos($_returned,"ETag"));
		$_etag = substr($_etag,strpos($_etag,'"')+1);
		$_etag = substr($_etag,0,strpos($_etag,'"'));
		//save eTag in os_caldav
		unset($_stmt_array); $_stmt_array = array(); unset($_result_array);
		$_stmt_array['stmt'] = "UPDATE os_caldav SET etag = ? WHERE tablemachine = ? AND id_table = ?";
		$_stmt_array['str_types'] = "ssi";
		$_stmt_array['arr_values'] = array();
		$_stmt_array['arr_values'][] = $_etag;
		$_stmt_array['arr_values'][] = $PARAMETER['table'];
		$_stmt_array['arr_values'][] = $PARAMETER['id_'.$PARAMETER['table']];
		execute_stmt($_stmt_array,$conn,true);		
	}
}

function _generateVCALENDAR(string $_created, string $_lastmodified, string $_uid, string $_summary, string $_dtstart, string $_dtend)
{
$_body = "BEGIN:VCALENDAR
VERSION:2.0
CALSCALE:GREGORIAN
PRODID:-//SabreDAV//SabreDAV 3.1.3//EN
X-WR-CALNAME:
X-APPLE-CALENDAR-COLOR:
BEGIN:VTIMEZONE
TZID:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
CREATED:".$_created."
LAST-MODIFIED:".$_lastmodified."
UID:".$_uid."
DTSTAMP:".$_created."
SUMMARY:".$_summary."
DTSTART;TZID=Europe/Berlin:".$_dtstart."
DTEND;TZID=Europe/Berlin:".$_dtend."
TRANSP:OPAQUE
X-MOZ-GENERATION:1				
END:VEVENT
END:VCALENDAR";
return $_body;
}

//returns the $PARAMETER array enriched by the new FILES info...
function FILE_Action(array $_PARAMETER, mysqli $conn) {
	unset($_PARAMETER['FILES']); return $_PARAMETER;
	//do nothing as along as distinction between FILES and FILESPATH is not properly implemented
	require('../../core/data/filedata.php');
	$message = '';
	
	//allow json encoded parameters in 'trash' variable
	if ( isset($_PARAMETER['trash']) ) { 
	$PARAMETER = json_decode($_PARAMETER['trash'],true);
	} else {
		$PARAMETER = $_PARAMETER;
	}

	if ( ! is_array($PARAMETER) ) { return; }

	if ( ! isset($PARAMETER['dbAction']) ) { $PARAMETER['dbAction'] = ''; }
	switch($PARAMETER['dbAction']) {
		case 'insert':
		case 'edit':
			//remove user deleted files
			foreach ( $PARAMETER as $key=>$value ) {
				if ( isset($value['filepath']) ) {
					$dir = dirname($value['filepath'][0]);
					print_r($dir);
					//remove only imported files in $fileroot
					if ( strpos($dir,$fileroot) == 0 ) {
						$filelist = scandir($dir);
						foreach ( $filelist as $filename )
						{
							if ( ! in_array($value['filepath'],$dir.'/'.$filename) ) { unlink($dir.'/'.$filename); }
						}					
					}
				}
			}
			//create new files and return the new values...
			unset($key); unset($value);
			//the file arrays are transferred as: $PARAMETER['FILES'][table_key][error/name/tmp_name...][numeric index] !!!!
			if ( isset($PARAMETER['FILES']) ) {
				$res = gnupg_init();
				gnupg_addencryptkey($res,'EBD608B2F05037DBB54360C49B77CAE8A63887AC','');
				foreach ( $PARAMETR['FILES'] as $tablekey=>$filefield ) {
					$table = explode('__',$tablekey)[0];
					$dir = $fileroot.'/'.$tablekey.'_'.$PARAMETER['id_'.$table]; 
					mkdir($dir, 0700);
					for ( $i = 0; $i < sizeof($filefield['name']); $i++ ) {
						$new_name = bin2hex(random_bytes(8));
						$extension = pathinfo($filefield['name'], PATHINFO_EXTENSION);
						//get file
						$plaintext = file_get_contents($filefield['tmp_name'][$i]);
						//encrypt
						$ciphertext = gnupg_encrypt($res,$plaintext); unset($plaintext);
						//write to disc
						$bytes = file_put_contents($dir.'/'.$new_name, $ciphertext);
						//generate new parameters
						$PARAMETER[$tablekey]['filepath'][] = $dir.'/'.$new_name;
					}
				}
				unset($PARAMETER['FILES']);	
			}
			break;
		case 'delete':
			break;
		case 'getID':
			break;
		case 'insertIfNotExists':
			break;
	}
	return $PARAMETER;		
}

function logout(string $redirect = 'login.php') { 
	session_start(); 
	// Unset all of the session variables.
	$_SESSION = array();

	// If it's desired to kill the session, also delete the session cookie.
	// Note: This will destroy the session, and not just the session data!
	if (ini_get("session.use_cookies")) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000,
			$params["path"], $params["domain"],
			$params["secure"], $params["httponly"]
		);
	}

	// Finally, destroy the session.
	session_destroy();
	if ( $redirect != '' ) {
		header('Location:/'.$redirect);
	}
}


function updateSidebar(array $PARAMETER, mysqli $conn, string $custom = '') 
{
	if ( ! isset($PARAMETER['table']) ) { $PARAMETER['table'] = array('os_all'); };
	$table = $PARAMETER['table'][0];

	//get user config
	$_config_array = getConfig($conn);
	unset($_stmt_array); $_stmt_array = array();
	$config_save_class = "saved";
	$config_remove_class = "";
	if ( isset($_config_array['configname']) ) {
		$_config_custom = getConfig($conn,$_config_array['configname']);
		if ( $custom != '' ) { $_config_array = $_config_custom; }
		$_compare1 = array(); $_compare2 = array();
		//compare filters, tables and hierarchy
		$_compare1['filters'] = $_config_array['filters']; $_compare2['filters'] = $_config_custom['filters'];
		$_compare1['table'] = $_config_array['table']; $_compare2['table'] = $_config_custom['table'];
		$_compare1['table_hierarchy'] = $_config_array['table_hierarchy']; $_compare2['table_hierarchy'] = $_config_custom['table_hierarchy'];
//		unset($_compare1['configname']); unset($_compare2['configname']);
		if ( $_compare1 == $_compare2 ) { $config_save_class = "disabled"; } else { $config_save_class = "unsaved"; }
		unset($_compare1); unset($_compare2);
		if ( $_config_array['configname'] == 'Default') { $config_remove_class = 'disabled'; } 
	}
	$_config = $_config_array['filters'];
	$_config_tables = $_config_array['table'];
	$_config_hierarchy = $_config_array['table_hierarchy'];
	$_stmt_array['stmt'] = "SELECT configname FROM os_userconfig_".$_SESSION['os_user'];
	$options = execute_stmt($_stmt_array,$conn)['result']['configname'];
//determine table hierarchy structure and table data
	unset($_stmt_array); $_stmt_array = array(); $table_array = array();
	$_stmt_array['stmt'] = "SELECT iconname,tablemachine,tablereadable,allowed_roles FROM os_tables WHERE tablemachine IN ('".implode("','",$_config_tables)."') ORDER BY FIELD (tablemachine,'".implode("','",$_config_tables)."') ";
	$_result_array = execute_stmt($_stmt_array,$conn,true); //keynames as last array field 
	if ($_result_array['dbMessageGood']) { $tables_array = $_result_array['result']; } else {
	?>
		<div class="section">Fehler bei Zugriff auf Tabellen. Bitte wiederholen Sie die Aktion. <?php print_r($_stmt_array); ?></div>
	<?php
		return;
	};
	//array of children of a table
	$_children_table = array(); unset($tindex);
	$_oldhier = -1;
	foreach ( $_config_tables as $tindex => $table )
	{
		if ( ! isset($_config_hierarchy[$tindex]) ) { $_config_hierarchy[$tindex] = min($tindex,1); }
		$_config_hierarchy[$tindex] = max(min($tindex,1),min($_config_hierarchy[$tindex],$_oldhier+1));
		$_oldhier = $_config_hierarchy[$tindex];
	}
	$_parent_table = array(); unset($tindex);
	
	foreach ( $_config_tables as $tindex => $table )
	{
		$_parent_table[$_config_hierarchy[$tindex]] = $table;
		if ( $_config_hierarchy[$tindex] >= 1 ) { 
			if ( isset($_parent_table[$_config_hierarchy[$tindex]-1]) AND isset($_children_table[$_parent_table[$_config_hierarchy[$tindex]-1]]) ) {
				array_push($_children_table[$_parent_table[$_config_hierarchy[$tindex]-1]],$table); 
			} else {
				$_children_table[$_parent_table[$_config_hierarchy[$tindex]-1]] = array($table);
			}
		}
	}
	?>
	<div id="config" class="section">
		<form id="formChooseConfig" class="noform" method="post" action="" onsubmit="callFunction(this,'copyConfig').then(()=>callFunction('_','updateSidebarCustom','sidebar')).then(()=>{ return false; }); return false;" >
		<?php //save button and load input like in openStat.plan explained ?>
			<label 
				for="config_save" 
				class="<?php echo($config_save_class); ?>" 
				title="Konfiguration speichern"
				<?php if ( $config_save_class == "disabled" ) { ?>onclick="return false;"<?php } ?>
			><i class="fas fa-save"></i></label>
			<input hidden type="submit" id="config_save">
			<label class="load <?php echo($config_save_class); ?>" for="config_load" title="Konfiguration laden"><i class="fas fa-clipboard-check"></i></label>
			<input <?php echo($config_save_class); ?> hidden type="button" id="config_load" onclick="callFunction(this.closest('form'),'changeConfig').then(()=>callFunction('_','updateSidebarCustom','sidebar')).then(()=>callFunction(document.querySelector('form#formChooseTables'),'changeConfig')).then(()=>callFunction('_','updateSidebarCustom','sidebar')).then((result)=>{ return result; });">
			<label class="<?php echo($config_remove_class); ?> " for="config_remove" title="Konfiguration löschen"><i class="fas fa-trash-alt"></i></label>
			<input <?php echo($config_remove_class); ?> hidden type="button" id="config_remove" onclick="_onAction('delete',this.closest('form'),'removeConfig'); document.getElementById('db__config__text').value = 'Default'; document.getElementById('db__config__list').value = 'Default'; callFunction(this.closest('form'),'changeConfig').then(()=>callFunction('_','updateSidebarCustom','sidebar')).then(()=>{ return false; }); return false;">
			<div class="unite">
				<label for="db__config__list"></label>
				<input type="text" id="db__config__text" name="configname" class="db_formbox" value="" autofocus disabled hidden>
				<select id="db__config__list" name="configname" class="db_formbox" onchange="callFunction(this.closest('form'),'changeConfig').then(()=>callFunction('_','updateSidebarCustom','sidebar')).then(()=>callFunction(document.querySelector('form#formChooseTables'),'changeConfig')).then(()=>callFunction('_','updateSidebarCustom','sidebar')).then((result)=>{ return result; });">
<!--				<select id="db__config__list" name="configname" class="db_formbox" onchange="callFunction(this.closest('form'),'changeConfig'); callFunction('_','updateSidebarCustom','sidebar'); setTimeout(function(){callFunction(document.querySelector('form#formChooseTables'),'changeConfig'); return callFunction('_','updateSidebarCustom','sidebar');},500);"> -->
				<!--	<option value="none"></option> -->
					<?php foreach ( $options as $value ) { 
						$_sel = '';
						if ( isset($_config_array['configname']) AND $_config_array['configname'] == $value ) { $_sel = 'selected'; };
						?>				
						<option value="<?php echo($value); ?>" <?php echo($_sel); ?> ><?php echo($value); ?></option>
					<?php } ?>
				</select>
				<label class="toggler" for="minus_config">&nbsp;<i class="fas fa-arrows-alt-h"></i></label>
				<input id="minus_config" class="minus" type="button" value="+" onclick="_toggleOption('_config_')" title="Erlaubt die Eingabe eines neuen Wertes" hidden>
			</div>
			<div class="clear"></div>
		</form>
	</div>
	<div id="tables" class="section">
		<?php updateTime(); includeFunctions('TABLES',$conn); ?>
		<label for="notoggleTables"><h1 class="center"><i class="fas fa-table"></i></h1></label>
		<input type="checkbox" hidden id="notoggleTables" class="notoggle">
		<form id="formChooseTables" class="noform" method="post" action="" onsubmit="callFunction(this,'changeConfig').then(()=>callFunction('_','updateSidebar','sidebar')).then(()=>{ return false; });return false;" >
			<div class="empty section" ondragover="allowDrop(event)" ondrop="drop(event,this)" ondragenter="dragenter(event)" ondragleave="dragleave(event)"></div>
			<?php
				unset($_stmt_array); $_stmt_array = array();
				$_stmt_array['stmt'] = "SELECT iconname,tablemachine,tablereadable,allowed_roles FROM os_tables";
				$_result_array = execute_stmt($_stmt_array,$conn,true); 
				$_result_array_normal = execute_stmt($_stmt_array,$conn); //first keynames then rows 
				if ($_result_array['dbMessageGood']) 
				{
					unset($_result);
					$_result = $_result_array['result'];
					$_result_normal = $_result_array_normal['result'];
					foreach ( $_config_tables as $table_index => $checked_tablemachine )
					{
						$_table = $_result[array_search($checked_tablemachine,$_result_normal['tablemachine'])];
						if ( ! isset($_config_hierarchy[$table_index]) ) {
							if ( $table_index == 0 ) { $_config_hierarchy[$table_index] = 0; } else { $_config_hierarchy[$table_index] = 1; }
						}
						if ( in_array($_SESSION['os_role'],json_decode($_table['allowed_roles'])) OR in_array($_SESSION['os_parent'],json_decode($_table['allowed_roles'])) ) { ?>
							<div id="table_<?php html_echo($_table['tablemachine']); ?>" draggable="true" ondragover="allowDrop(event)" ondrop="drop(event,this)" ondragstart="drag(event)" ondragenter="dragenter(event)" ondragleave="dragleave(event)" ondragend="dragend(event)">
								<?php if ( $table_index > 0 ) { ?>
									<span style="display: inline-block; width: <?php echo($_config_hierarchy[$table_index]); ?>em">&nbsp;</span><span class="hier_up" onclick="hierarchy(this,-1);">&nbsp;</span><span>&rdsh;</span><span class="hier_down" onclick="hierarchy(this,1);">&nbsp;</span>
								<?php } ?>
								<input class="hierarchy" name="table_hierarchy[]" hidden type="number" value="<?php echo($_config_hierarchy[$table_index]); ?>">
								<input 
									name="table[]" 
									id="add_<?php html_echo($_table['tablemachine']); ?>" 
									type="checkbox" 
									value="<?php html_echo($_table['tablemachine']); ?>"
									onchange="updateTime(this);"
									<?php if ( in_array($_table['tablemachine'],$_config_tables) ) { ?>checked<?php }; ?>
								/>
								<label for="add_<?php html_echo($_table['tablemachine']); ?>"><i class="fas fa-<?php html_echo($_table['iconname']); ?>"></i> <?php html_echo($_table['tablereadable']); ?></label><br>
							</div>
						<?php }			
					}
					unset($_table);
					foreach ( $_result as $_table )
					{
						if ( in_array($_table['tablemachine'],$_config_tables) ) { continue; }
						if ( in_array($_SESSION['os_role'],json_decode($_table['allowed_roles'])) OR in_array($_SESSION['os_parent'],json_decode($_table['allowed_roles'])) ) { ?>
							<div id="table_<?php html_echo($_table['tablemachine']); ?>" draggable="true" ondragover="allowDrop(event)" ondrop="drop(event,this)" ondragstart="drag(event)" ondragenter="dragenter(event)" ondragleave="dragleave(event)" ondragend="dragend(event)"> 
								<input 
									name="table[]" 
									id="add_<?php html_echo($_table['tablemachine']); ?>" 
									type="checkbox" 
									value="<?php html_echo($_table['tablemachine']); ?>"
									onchange="updateTime(this);"
									<?php if ( in_array($_table['tablemachine'],$_config_tables) ) { ?>checked<?php }; ?>
								/>
								<label for="add_<?php html_echo($_table['tablemachine']); ?>"><i class="fas fa-<?php html_echo($_table['iconname']); ?>"></i> <?php html_echo($_table['tablereadable']); ?></label><br>
							</div>
							<?php }
					}
					
				} 			
			?>
			<hr>
			<label for="formTablesSubmit" class="submitAddFilters" ><h2 class="center"><i class="fas fa-arrow-circle-down"></i></h2></label>
			<input hidden id="formTablesSubmit" type="submit" value="Aktualisieren">
		</form>
	</div>
	<div id="filters">
		<?php updateTime(); includeFunctions('FILTERS',$conn); ?>		
		<div id="addfilters">
			<label for="toggleAddFilter"><h2 class="center"><i class="fas fa-filter"></i><i class="fas fa-plus"></i></h2></label>
			<input type="checkbox" hidden id="toggleAddFilter" class="toggle">
			<form id="formAddFilters" class="form" method="post" action="../php/addFilters.php" onsubmit="addFilters(this); return false;">
					<label class="submitAddFilters" for="submitAddFilters"><h2 class="center"><i class="fas fa-exchange-alt"></i></h2></label>
					<input hidden id="submitAddFilters" type="submit" value="Auswählen" ><br />
				<?php
					unset($table); unset($tindex); //added 2019-09-03
					foreach ( $_config_tables as $tindex => $table )
					{
						$table_array = $tables_array[$tindex];
						?>
						<h2><i class="fas fa-<?php html_echo($table_array['iconname']); ?>"></i><?php html_echo($table_array['tablereadable']); ?></h2>
						<?php 
						unset($_stmt_array); $_stmt_array = array(); $key_array = array();
						$_stmt_array['stmt'] = "SELECT keymachine,keyreadable,edittype FROM ".$table."_permissions ORDER BY realid";
						$_result_array = execute_stmt($_stmt_array,$conn,true); //keynames as last array field
						if ($_result_array['dbMessageGood']) { $key_array = $_result_array['result']; };
						if ( isset($_children_table[$table]) ) {
							foreach ( $_children_table[$table] as $_child ) {
								if ( ! array_key_exists($table.'__'.$_child,$_config) ) 
								{ ?> 
								<input 
									name="<?php html_echo($table.'__'.$_child); ?>" 
									id="add_<?php html_echo($table.'__'.$_child); ?>" 
									type="checkbox" 
									value="add"
								/>
								<label for="add_<?php html_echo($table.'__'.$_child); ?>"># <i class="fas fa-<?php html_echo($tables_array[array_search($_child,$_config_tables)]['iconname']); ?>"></i> <strong><?php html_echo($tables_array[array_search($_child,$_config_tables)]['tablereadable']); ?></strong></label><br>
								<?php }							
							}
						}
						?>
						<?php
						foreach ( $key_array as $key ) 
						{ 
							if ( $key['edittype'] == 'NONE' ) { continue; }
							if ( ! array_key_exists($table.'__'.$key['keymachine'],$_config) ) 
							{ ?> 
							<input 
								name="<?php html_echo($table.'__'.$key['keymachine']); ?>" 
								id="add_<?php html_echo($table.'__'.$key['keymachine']); ?>" 
								type="checkbox" 
								value="add"
							/>
							<label for="add_<?php html_echo($table.'__'.$key['keymachine']); ?>"><?php html_echo(explode(': ',$key['keyreadable'])[0]); ?></label><br>
							<?php }
						}
						unset($key);
					}
					unset ($table); unset($_child);
				?>
			</form>
		</div>
		<hr>
		<form id="formFilters" method="post" action="" onsubmit="callFunction(this,'applyFilters','results_wrapper').then(()=>callFunction('_','updateSidebar','sidebar')).then(()=>{ rotateHistory(); return false; }); return false; ">
			<label for="formFiltersSubmit" class="submitAddFilters" ><h1 class="center"><i class="fas fa-arrow-circle-right"></i></h1></label>
			<input hidden id="formFiltersSubmit" type="submit" value="Aktualisieren">
			<hr>
			<div class="empty section" ondragover="allowDrop(event)" ondrop="drop(event,this)" ondragenter="dragenter(event)" ondragleave="dragleave(event)"></div>
			<!-- draggable="true"; reihenfolge von oben nach unten definiert pivotansicht in results -->
			<div id="filterlist">
				<?php foreach ( $_config as $tabledotkeymachine => $checked ) {
					if ( ! is_array($checked) OR ! in_array('_all',$checked) ) { continue; } // ignore config settings that do not concern filters				 
					$tablemachinearray = explode("__",$tabledotkeymachine,2);
					$table =  $tablemachinearray[0];
					$keymachine = $tablemachinearray[1];
					unset($_stmt_array); $_stmt_array = array(); $table_array = array();
					$_stmt_array['stmt'] = "SELECT iconname FROM os_tables WHERE tablemachine = ?";
					$_stmt_array['str_types'] = "s";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = $table;
					unset($_result_array);
					$_result_array = execute_stmt($_stmt_array,$conn); 
					if ($_result_array['dbMessageGood']) { $table_icon = $_result_array['result']['iconname'][0]; };

					//first look for attributions, then for normal keys
					if ( isset($_children_table[$table]) AND in_array($keymachine,$_children_table[$table]) ) {
						$keyreadable = '#'.$tables_array[array_search($keymachine,$_config_tables)]['tablereadable'];
					} else {
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "SELECT keyreadable FROM ".$table."_permissions WHERE keymachine = ?";
						$_stmt_array['str_types'] = "s";
						$_stmt_array['arr_values'] = array();
						$_stmt_array['arr_values'][] = $keymachine;
						unset($_result_array);
						$_result_array = execute_stmt($_stmt_array,$conn); 
						if ($_result_array['dbMessageGood']) { $keyreadable = explode(': ',$_result_array['result']['keyreadable'][0])[0]; } else { continue; }; //serializes as associative array			
					}
				?>
					<div 
						id="<?php html_echo($keymachine); ?>" 
						class="section" 
						draggable="true" ondragover="allowDrop(event)" ondrop="drop(event,this)" ondragstart="drag(event)" ondragenter="dragenter(event)" ondragleave="dragleave(event)" ondragend="dragend(event)"
						<?php if ( ! in_array($table,$_config_tables ) ) { ?> hidden<?php } ?>
					>
						<input 
							class="shownot"
							hidden
							name="<?php html_echo($table.'__'.$keymachine); ?>[-1]"
							id="<?php html_echo($table.'__'.$keymachine); ?>shownot"
							type="checkbox"
							value="_shownot"
							<?php if ( isset($checked[-1]) ) { ?> checked <?php } ?>
						/>
						<input type="checkbox" hidden id="toggle<?php html_echo($tabledotkeymachine); ?>" class="toggle">
						<label for="toggle<?php html_echo($tabledotkeymachine); ?>">
							<h2>
								<i class="open fas fa-angle-down"></i>
								<i class="closed fas fa-angle-right"></i> 
								<i class="fas fa-<?php html_echo($table_icon); ?>"></i>
								<?php html_echo($keyreadable); ?>
								<label for="trash" onclick="return trash('<?php html_echo($tabledotkeymachine); ?>')">
									<i class="remove fas fa-trash-alt"></i>
								</label>
								<label class="show" for="<?php html_echo($table.'__'.$keymachine); ?>shownot">
									<i class="remove fas fa-eye">&nbsp;</i>
								</label>
								<label class="shownot" for="<?php html_echo($table.'__'.$keymachine); ?>shownot">
									<i class="remove fas fa-eye-slash">&nbsp;</i>
								</label>
							</h2>
						</label>
						<?php
							$edit = new OpenStatEdit($table,$keymachine,$conn);
							$edit->choose($checked);
							unset($edit);
						?>
					</div>
				<?php }; ?>
			</div> <!-- end of 'filterlist' -->
			<div class="empty section" ondragover="allowDrop(event)" ondrop="drop(event,this)" ondragenter="dragenter(event)" ondragleave="dragleave(event)"></div>
		</form>
	</div>
	<?php
}

function updateSidebarCustom(array $PARAMETER, mysqli $conn)
{
	updateSidebar($PARAMETER,$conn,'custom');
} 

// display=false: only return the mysql statement
function applyFilters(array $parameter, mysqli $conn, bool $complement = false, bool $display = true, bool $changeconf = true)
{
	if ( isset($parameter) AND $changeconf ) {
		$config = array('filters'=>$parameter);
		$_wait = changeConfig($config,$conn); //give name so that php waits for return before continuing to read the config
	}

	//use config instead of parameters (since for several tables you do not want to send several forms simultaneously...)
	
	//get config;
	$_config = getConfig($conn);
	if ( $changeconf ) {
		$PARAMETER = $_config['filters'];
	} else {
		$PARAMETER = $parameter;
	}
	$TABLES = $_config['table'];
	$HIERARCHY = $_config['table_hierarchy'];
	$maintable = $TABLES[0];
	
	//get edittypes (for strict or weak matches of values)
	$_edittypes = array();
	foreach ( $TABLES as $table ) {
		unset($_stmt_array); $_stmt_array = array();
		$_stmt_array['stmt'] = "SELECT keymachine,edittype FROM ".$table."_permissions";
		$_tmp_result = execute_stmt($_stmt_array,$conn)['result'];
		$_edittypes[$table] = array_combine($_tmp_result['keymachine'],$_tmp_result['edittype']);
	}	
//	if ( ! isset($PARAMETER['table']) ) { $PARAMETER['table'] = array('os_all'); };
//	$table = $PARAMETER['table'][0];
	//construct query from parameters
	unset($value); unset($tindex);
	$_SELECT = '';
	$komma = '';
	$_FROM = ' FROM '; $_JOIN = ''; $_USING = ''; // table will be viewROLE in live version
	foreach ( $TABLES as $tindex => $table )
	{
		if ( ! isset($HIERARCHY[$tindex]) ) { $HIERARCHY[$tindex] = min($tindex,1); }
	}
	$HIERARCHY[-1] = 0;
	$HIERARCHY[sizeof($TABLES)] = 1;
	$JOINSRC = array();
	$SHOWME = array(); //array of fields to be displayed in results
	unset($tindex);
	foreach ( $TABLES as $tindex => $table )
	{
		//add table to showme (customer request); here for economic reasons...
		array_push($SHOWME,$table.'__id_'.$table);
		//
		$JOINSRC[$HIERARCHY[$tindex]] = $table;
		$_JOIN = '';
		if ( $tindex > 0 AND $HIERARCHY[$tindex] >= $HIERARCHY[$tindex-1] ) {
			$_JOIN = ' LEFT JOIN ';				
		}
		if ( $tindex > 0 AND $HIERARCHY[$tindex+1] > $HIERARCHY[$tindex] ) {
			$_JOIN .= '(';				
		}
		$_FROM .= $_JOIN . '`view__' . $table . '__' . $_SESSION['os_role'].'`';
		$_USING = ''; $HBRKT = '';
		if ( $HIERARCHY[$tindex+1] <= $HIERARCHY[$tindex] ) {
			for ( $hindex = $HIERARCHY[$tindex+1]; $hindex <= $HIERARCHY[$tindex]; $hindex++ ) {
				$_USING = ' ON `view__' . $JOINSRC[$hindex] . '__' . $_SESSION['os_role'].'`.id_'.$JOINSRC[$hindex-1].' = `view__' . $JOINSRC[$hindex-1] . '__' . $_SESSION['os_role'].'`.id_'.$JOINSRC[$hindex-1]. $HBRKT . $_USING;
				$HBRKT = ')';
			}
		}		
		$_FROM .= $_USING;
		$_SELECT .= '`view__' . $table . '__' . $_SESSION['os_role'].'`.id_'.$table.' AS '.$table.'__'.'id_'.$table.',';
/*		$_FROM .= $_JOIN . '`view__' . $table . '__' . $_SESSION['os_role'].'`' . $_USING;
		$_JOIN = ' LEFT JOIN ';
		$_USING = ' USING (id_'.$maintable.') ';
		$_SELECT .= '`view__' . $table . '__' . $_SESSION['os_role'].'`.id_'.$table.' AS '.$table.'__'.'id_'.$table.',';
		previous working! version (w/o hierarchies)*/ 
	}
	$_WHERE = '';
	$komma2 = ' WHERE (';
	$bracket = '';
	$_ORDER_BY = ' ORDER BY ';
	$_komma_cmp = ' AND '; //conditions for different compounds in one key filter have all to be satisfied
	$_komma_cmp_entry = ' OR '; //a certain index has to match every compound
	$SHOWNOTALL = false;
	$EXT_ORDER_BY = array(); //order in case not all fields are going to be displayed
	foreach ($PARAMETER as $key=>$values) 
	{
		if ( in_array($key,array('table')) ) { continue; };
		//check for checked tables;
		$table = explode('__',$key,2)[0];
		$key = explode('__',$key,2)[1];
		if ( ! in_array($table,$TABLES) ) { continue; };
		//only filter/also select selection
		if ( array_key_exists(-1,$values) )
		{
			$SHOWNOTALL = true;
		} else {
			if ( ! in_array($table.'__id_'.$table,$SHOWME) ) { array_push($SHOWME,$table.'__id_'.$table); };
			array_push($EXT_ORDER_BY,$table.'__'.$key);
			array_push($SHOWME,$table.'__'.$key);
		}
		unset($values[-1]);
		//attribution counts are given by key: <tablemachine attributed,lower hierarchy value>__<tablemachine holding attribution,higher hierarchy value>, values: 5001:[min],5002:[max]
		//implement here
		$ATTR_WHERE = ''; $ATTR_OR = '';
		if ( in_array($key,$TABLES) ) {
			$ATTR_SELECT = $komma . 'tmp__' . $table . '__' . $key . '.' . $key . ' AS ' . $table . '__' . $key;
			$ATTR_FROM = ' INNER JOIN ( SELECT `view__' . $table . '__' . $_SESSION['os_role'].'`.id_'.$table.',COUNT(DISTINCT `view__' . $key . '__' . $_SESSION['os_role'].'`.id_'.$key.') AS ' . $key . $_FROM.$_WHERE.$bracket.' GROUP BY `view__' . $table . '__' . $_SESSION['os_role'].'`.id_'.$table.' HAVING ';
			for ( $i = 0; $i < sizeof($values[5003]); $i++ ) {
				if ( $values[5001][$i] == '' ) { $values[5001][$i] = 0; }
				if ( $values[5002][$i] == '' ) { $values[5002][$i] = 1000000000; }
				$ATTR_FROM .= $ATTR_OR.'( COUNT(DISTINCT `view__' . $key . '__' . $_SESSION['os_role'].'`.id_'.$key.') >= '.$values[5001][$i].' AND COUNT(DISTINCT `view__' . $key . '__' . $_SESSION['os_role'].'`.id_'.$key.') <= '.$values[5002][$i].')';
				$ATTR_OR=" OR ";
			}
			$ATTR_FROM .= ' ) AS tmp__' . $table . '__' . $key. ' ON tmp__' . $table . '__' . $key . '.id_' . $table . ' = `view__' . $table . '__' . $_SESSION['os_role'].'`.id_'.$table;	
//			$ATTR_WHERE = $komma2.'`view__' . $table . '__' . $_SESSION['os_role'].'`.id_'.$table.' IN ( SELECT `view__' . $table . '__' . $_SESSION['os_role'].'`.id_'.$table.$_FROM.$_WHERE.$bracket.' GROUP BY `view__' . $table . '__' . $_SESSION['os_role'].'`.id_'.$table.' HAVING COUNT(DISTINCT `view__' . $key . '__' . $_SESSION['os_role'].'`.id_'.$key.') >= '.$values[5001][0].' AND COUNT(DISTINCT `view__' . $key . '__' . $_SESSION['os_role'].'`.id_'.$key.') <= '.$values[5002][0].' )';
//			$_WHERE .= $ATTR_WHERE;
			$_SELECT .= $ATTR_SELECT;
			$_FROM .= $ATTR_FROM;
			$_ORDER_BY .= $komma.$table.'__'.$key;
			$komma = ',';
//			$komma2 = ') AND (';
			continue;
		}
		//set strong or weak match strings
		switch($_edittypes[$table][$key]) {
			case 'EXTENSIBLE LIST; MULTIPLE':
			case 'LIST; MULTIPLE':
			case 'SUGGEST; MULTIPLE':
			case 'SUGGEST BEFORE LIST; MULTIPLE':
			case 'CHECKBOX':
			case 'EXTENSIBLE CHECKBOX':
			case 'SUGGEST':
			case 'LIST':
			case 'SUGGEST BEFORE LIST':
			case 'EXTENSIBLE LIST':				
				$_MATCHSTART = "= '";
				$_MATCHEND = "'";
				$_NOT = "!";
				break;
			default:
				$_MATCHSTART = "LIKE CONCAT('%','";
				$_MATCHEND = "','%')";
				$_NOT = " NOT ";
				break;
		}
		//replace the __ by . (HTML5 replaces inner white space by _, so no . possible)
		//$key = str_replace('__','.',$key);
		$_SELECT .= $komma.'`view__' . $table . '__' . $_SESSION['os_role'].'`.'.$key.' AS `'.$table.'__'.$key.'`';
		$_ORDER_BY .= $komma.$table.'__'.$key;
		$komma = ',';
		$_orand = 0;
		if ( sizeof($values) > 1 ) {
	//		array_shift($values); //does a renumbering which destroys the indices 1001 and 1002 (probably also key names for that matter)
			unset($values[0]);
			if ( array_key_exists(2001,$values) AND $values[2001] == "-499" )
			{
				$_orand = 1; //" AND ";
			}
			else
			{
				$_orand = 0; //" OR ";
			}
			unset($values[2001]);
			//negation
			if ( array_key_exists(3001,$values) )
			{
				$_orand = 1 - $_orand;
				$_negation = $_NOT;
				$_ge = "<";
				$_le = ">";
				$_komma_date_multiple = " AND ";
				$_komma_date_multiple_inner = " OR ";
				$_komma_cmp = " OR ";
				$_komma_cmp_entry = " AND ";
			}
			else
			{
				$_negation = "";
				$_ge = ">=";
				$_le = "<=";
				$_komma_date_multiple = " OR ";
				$_komma_date_multiple_inner = " AND ";
				$_komma_cmp = " AND ";
				$_komma_cmp_entry = " OR ";
			}			
			unset($values[3001]);
			switch($_orand) {
				case 0:
					$_komma_date_inner = " AND ";
					$_komma_outer = " OR ";
					break;
				case 1:
					$_komma_date_inner = " OR ";
					$_komma_outer = " AND ";
					break;
			}
			if ( array_key_exists(1001,$values) )
			//20200525: how to adapt for multiple dates?
			// would need a JSON_TABLE construct for comparison, but this does not (yet) exist in MariaDB, seems to come in 10.6
			// sth like this: select JSON_VALUE(JSON_QUERY(JSON_QUERY(config,'$.filters'),'$.opsz_evaluation__evalbado'),'$.1003[0]') from os_userconfig; have to test for date intervals for all array entries (not just 0)....
			// or better: select config from os_userconfig where JSON_VALUE(JSON_QUERY(JSON_QUERY(config,'$.filters'),'$.opsz_evaluation__evalbado'),'$.1003[0]') IS NOT NULL;
			{
				unset($_stmt_tmp); $_stmt_tmp = array();
				$_stmt_tmp['stmt'] = "SELECT MAX(JSON_LENGTH(`view__" . $table . "__" . $_SESSION['os_role']."`.`".$key."`)) AS jsonlength FROM `view__" . $table . "__" . $_SESSION['os_role']."`";
				unset($_jsonlength);
				$_jsonlength = execute_stmt($_stmt_tmp,$conn)['result']['jsonlength'][0]; 				
				for ( $i = 0; $i < sizeof($values[1001]); $i++ )
	//			foreach ($values[1001] as $index=>$value)
				{
		//			$_WHERE .= $komma2.'(`'.$key."` = '".date("Y-m-d H:i:s",$value)."'";
					$_WHERE .= $komma2.' (';
					$komma3 = '';
					for ( $j = 0; $j < $_jsonlength; $j++ ) {
						if ( ! isset($values[1001][$i]) OR $values[1001][$i] == '' ) { $values[1001][$i] = '1970-01-01'; }
						$_WHERE .= $komma3."(((`view__" . $table . "__" . $_SESSION['os_role']."`.`".$key."` NOT LIKE '[%' AND `view__" . $table . "__" . $_SESSION['os_role']."`.`".$key."` ".$_ge." '".$values[1001][$i]."')";
						$_WHERE .= " OR (`view__" . $table . "__" . $_SESSION['os_role']."`.`".$key."` LIKE '[%' AND JSON_VALUE(`view__" . $table . "__" . $_SESSION['os_role']."`.`".$key."`,'$[".$j."]') ".$_ge." \"".$values[1001][$i]."\")";
						$_WHERE .= ')';
						$komma2 = $_komma_date_multiple_inner;
//						$komma2 = $_komma_date_inner;
						$bracket = ')';
						if ( ! isset($values[1002][$i]) OR  $values[1002][$i] == '' ) { $values[1002][$i] = '2070-01-01'; }
						$_WHERE .= $komma2."((`view__" . $table . "__" . $_SESSION['os_role']."`.`".$key."` NOT LIKE '[%' AND `view__" . $table . "__" . $_SESSION['os_role']."`.`".$key."` ".$_le." '".$values[1002][$i]."')";
						$_WHERE .= " OR (`view__" . $table . "__" . $_SESSION['os_role']."`.`".$key."` LIKE '[%' AND JSON_VALUE(`view__" . $table . "__" . $_SESSION['os_role']."`.`".$key."`,'$[".$j."]') ".$_le." \"".$values[1002][$i]."\")";
						$_WHERE .= '))';
						$komma2 = $_komma_outer;
						$komma3 = $_komma_date_multiple;
					$bracket = ')';
					}
					$_WHERE .= ') ';
				}
			} elseif ( array_key_exists(5001,$values) )
			{
				for ( $i = 0; $i < sizeof($values[5001]); $i++ )
	//			foreach ($values[1001] as $index=>$value)
				{
		//			$_WHERE .= $komma2.'(`'.$key."` = '".date("Y-m-d H:i:s",$value)."'";
					if ( ! isset($values[5001][$i]) OR $values[5001][$i] == '' ) { $values[5001][$i] = '0'; }
					$_WHERE .= $komma2.'(`view__' . $table . '__' . $_SESSION['os_role'].'`.`'.$key."` ".$_ge." '".$values[5001][$i]."'";
					$komma2 = $_komma_date_inner;
					$bracket = ')';
					if ( ! isset($values[5002][$i]) OR  $values[5002][$i] == '' ) { $values[5002][$i] = '1000000000'; }
					$_WHERE .= $komma2.'`view__' . $table . '__' . $_SESSION['os_role'].'`.`'.$key."` ".$_le." '".$values[5002][$i]."')";
					$komma2 = $_komma_outer;
					$bracket = ')';
				}
			} elseif ( array_key_exists(6001,$values) )
			{
			// sorry, no better idea: construct compound filters: repeat everything else with apt JSON_QUERY and JSON_VALUE queries...
				//redefine $values: $cmp_values is normally a JSON of an array, but may also contain JSONS of arrays in keys 1001-1003,4001,4002
				$cmp_index = 0;
				$cmp_values = array();
				while ( array_key_exists(6001+$cmp_index,$values) ) {
					array_push($cmp_values,$values[6001+$cmp_index]);
					$cmp_index++;					
				}
				//get maximal multiplicity of entries
				unset($_stmt_tmp); $_stmt_tmp = array();
				$_stmt_tmp['stmt'] = "SELECT MAX(JSON_LENGTH(JSON_QUERY(`view__" . $table . "__" . $_SESSION['os_role']."`.`".$key."`,'$[0]'))) AS jsonlength FROM `view__" . $table . "__" . $_SESSION['os_role']."`";
				unset($_jsonlength);
				$_jsonlength = execute_stmt($_stmt_tmp,$conn)['result']['jsonlength'][0];
				//compound means: outer iteration: index of $cmp_values[0]; middle iteration: index of multiple entries (0...$jsonlength-1); inner iteration: compounds
				$_WHERE .= $komma2.' ('; $komma2 = '';
				for ( $i = 0; $i < _len($cmp_values); $i++ ) { // $i is conditionnumber;	
					$_WHERE .= $komma2.' ('; $komma2 = '';
					for ( $j = 0; $j < $_jsonlength; $j++ ) { // $j is entrynumber
						$_WHERE .= $komma2.' ('; $komma2 = '';
						for ( $compoundnumber = 0; $compoundnumber < $cmp_index; $compoundnumber++ ) {
							$_WHERE .= $komma2.' ('; // was: $_komma_cmp
							//to be continued here...; todo: check AND/OR logic; probably need another inner AND/komma4
							if ( array_key_exists(1001,$cmp_values[$compoundnumber]) )
							//20200525: how to adapt for multiple dates?
							// would need a JSON_TABLE construct for comparison, but this does not (yet) exist in MariaDB, seems to come in 10.6
							// sth like this: select JSON_VALUE(JSON_QUERY(JSON_QUERY(config,'$.filters'),'$.opsz_evaluation__evalbado'),'$.1003[0]') from os_userconfig; have to test for date intervals for all array entries (not just 0)....
							// or better: select config from os_userconfig where JSON_VALUE(JSON_QUERY(JSON_QUERY(config,'$.filters'),'$.opsz_evaluation__evalbado'),'$.1003[0]') IS NOT NULL;
							{
					//			foreach ($cmp_values[$compoundnumber][1001] as $index=>$value)
					//			$_WHERE .= $komma2.'(`'.$key."` = '".date("Y-m-d H:i:s",$value)."'";
					//			$komma3 = '';
								if ( ! isset($cmp_values[$compoundnumber][1001][$i]) OR $cmp_values[$compoundnumber][1001][$i] == '' ) { $cmp_values[$compoundnumber][1001][$i] = '1970-01-01'; }
					//			$_WHERE .= $komma3."((";
					//			$_WHERE .= "((";
								$_WHERE .= "(JSON_VALUE(JSON_QUERY(`view__" . $table . "__" . $_SESSION['os_role']."`.`".$key."`,'$[".$compoundnumber."]'),'$[".$j."]') ".$_ge." \"".$cmp_values[$compoundnumber][1001][$i]."\")";
					//			$_WHERE .= ')';
								$komma2 = $_komma_date_multiple_inner;
		//						$komma2 = $_komma_date_inner;
								$bracket = ')';
								if ( ! isset($cmp_values[$compoundnumber][1002][$i]) OR  $cmp_values[$compoundnumber][1002][$i] == '' ) { $cmp_values[$compoundnumber][1002][$i] = '2070-01-01'; }
					//			$_WHERE .= $komma2."(";
								$_WHERE .= $komma2;
								$_WHERE .= "(JSON_VALUE(JSON_QUERY(`view__" . $table . "__" . $_SESSION['os_role']."`.`".$key."`,'$[".$compoundnumber."]'),'$[".$j."]') ".$_le." \"".$cmp_values[$compoundnumber][1002][$i]."\")";
					//			$_WHERE .= '))';
								$komma2 = $_komma_cmp;
								$bracket = ')';
					//			$_WHERE .= ') ';
							} elseif ( array_key_exists(5001,$cmp_values[$compoundnumber]) )
							{
					//			$_WHERE .= $komma2.'(`'.$key."` = '".date("Y-m-d H:i:s",$value)."'";	
								if ( ! isset($cmp_values[$compoundnumber][5001][$i]) OR $cmp_values[$compoundnumber][5001][$i] == '' ) { $cmp_values[$compoundnumber][5001][$i] = '0'; }
								$_WHERE .= $komma2.'(JSON_VALUE(JSON_QUERY(`view__' . $table . '__' . $_SESSION['os_role'].'`.`'.$key."`,'$[".$compoundnumber."]'),'$[".$j."]') ".$_ge." '".$cmp_values[$compoundnumber][5001][$i]."'";
								$komma2 = $_komma_date_inner;
								$bracket = ')';
								if ( ! isset($cmp_values[$compoundnumber][5002][$i]) OR  $cmp_values[$compoundnumber][5002][$i] == '' ) { $cmp_values[$compoundnumber][5002][$i] = '1000000000'; }
								$_WHERE .= $komma2.'(JSON_VALUE(JSON_QUERY(`view__' . $table . '__' . $_SESSION['os_role'].'`.`'.$key."`,'$[".$compoundnumber."]'),'$[".$j."]') ".$_le." '".$cmp_values[$compoundnumber][5002][$i]."')";
								$komma2 = $_komma_cmp;
								$bracket = ')';
							}			
							if ( ! array_key_exists(1001,$cmp_values[$compoundnumber]) AND ! array_key_exists(5001,$cmp_values[$compoundnumber]) )
							{
								//no: just search json entry for searchterm, so no index 4001...
								//FILESPATH searchable by filedescription field (4001)
								//if ( array_key_exists(4001,$cmp_values[$compoundnumber]) ) { $cmp_values[$compoundnumber] = $cmp_values[$compoundnumber][4001]; }
					//			$_WHERE .= $komma2.'`'.$key."` = '".$value."'";
//								$_WHERE .= $komma2.'(JSON_VALUE(JSON_QUERY(`view__' . $table . '__' . $_SESSION['os_role'].'`.`'.$key."`,'$[".$compoundnumber."]'),'$[".$j."]') ".$_negation."LIKE CONCAT('%','".$cmp_values[$compoundnumber][$i]."','%')) ";
								$_WHERE .= '(IFNULL(JSON_VALUE(JSON_QUERY(`view__' . $table . '__' . $_SESSION['os_role'].'`.`'.$key."`,'$[".$compoundnumber."]'),'$[".$j."]'),'') ".$_negation.$_MATCHSTART.$cmp_values[$compoundnumber][$i].$_MATCHEND.") ";
			//					$komma2 = " OR ";	
								$komma2 = $_komma_cmp;
								$bracket = ')';
							}
							//do not change $komma2 if no condition was set
							if ( $komma2 != ' WHERE (' ) { $komma2 = ') '.$_komma_cmp.' ('; }
							$_WHERE .= ')';
						}
						//do not change $komma2 if no condition was set
						if ( $komma2 != ' WHERE (' ) { $komma2 = ') '.$_komma_cmp_entry.' ('; }
						$_WHERE .= ')';			
					} 				
					//do not change $komma2 if no condition was set
					if ( $komma2 != ' WHERE (' ) { $komma2 = ') '.$_komma_outer.' ('; }
					$_WHERE .= ')';
				} 				
				$_WHERE .= ') ';
			} // end of compound extra tests
			if ( ! array_key_exists(1001,$values) AND ! array_key_exists(5001,$values) AND ! array_key_exists(6001,$values) )
			{
				//no: just search json entry for searchterm, so no index 4001...
				//FILESPATH searchable by filedescription field (4001)
				//if ( array_key_exists(4001,$values) ) { $values = $values[4001]; }
				foreach ($values as $index=>$value)
				{
		//			$_WHERE .= $komma2.'`'.$key."` = '".$value."'";
					$_WHERE .= $komma2.'`view__' . $table . '__' . $_SESSION['os_role'].'`.`'.$key."` ".$_negation.$_MATCHSTART.$value.$_MATCHEND;
//					$komma2 = " OR ";
					$komma2 = $_komma_outer;
					$bracket = ')';
				}
			}
			//do not change $komma2 if no condition was set
			if ( $komma2 != ' WHERE (' ) { $komma2 = ') AND ('; }
		}
	}
	$_WHERE .= $bracket;
	$_main_stmt_array = array();
	$_main_stmt_array['stmt'] = 'SELECT '.$_SELECT.$_FROM.$_WHERE.$_ORDER_BY; //do not order by id!
//	if ( $SHOWNOTALL ) {
		$_main_stmt_array['stmt'] = 'SELECT DISTINCT '.implode(',',$SHOWME).' FROM ('.$_main_stmt_array['stmt'].') AS T ORDER BY '.implode(',',$EXT_ORDER_BY);
//	}
	//generate complementary statement if desired
	//complementary means: find all ids of main table not occuring in stmt result and display maintable filters
	if ( $complement ) {
		$_SELECT = 'SELECT id_'.$maintable.' AS '.$maintable.'__id_'.$maintable.',';
		$komma = '';
		$_FROM = ' FROM `view__' . $maintable . '__' . $_SESSION['os_role'].'` '; 
		$_WHERE = 'WHERE id_'.$maintable.' NOT IN ( SELECT '.$maintable.'__id_'.$maintable.' FROM (';
		$bracket = ') AS T ) ';
		$_ORDER_BY = ' ORDER BY ';
		foreach ($PARAMETER as $key=>$values) 
		{
			if ( in_array($key,array('table')) ) { continue; };
			//check for checked tables;
			$table = explode('__',$key,2)[0];
			$key = explode('__',$key,2)[1];
			if ( $table != $maintable ) { continue; };
			//replace the __ by . (HTML5 replaces inner white space by _, so no . possible)
			//$key = str_replace('__','.',$key);
			$_SELECT .= $komma.'`view__' . $table . '__' . $_SESSION['os_role'].'`.'.$key.' AS `'.$table.'__'.$key.'`';
			$_ORDER_BY .= $komma.$table.'__'.$key;
			$komma = ',';
		}
		$_main_stmt_array['stmt'] = $_SELECT.$_FROM.$_WHERE.$_main_stmt_array['stmt'].$bracket.$_ORDER_BY;
	}
	if ( isset($display) AND !$display ) { return $_main_stmt_array; }
	//print_r($_main_stmt_array); //for debug only
	$filters = generateFilterStatement($PARAMETER,$conn,'os_all',$complement);
	$table_results = generateResultTable($_main_stmt_array,$conn);
	$stat_results = generateStatTable($_main_stmt_array,$conn);
	?>
	<?php updateTime(); includeFunctions('RESULTS',$conn); ?>
	<form class="hidden"></form>
	<div id="filter_wrapper">
		<h2>Filter</h2>
		<?php echo($filters); ?>
	</div>
	<div id="stat_wrapper">
		<h2>Statistik</h2>
		<div class="comment">
			<p><span class="hits">Anzahl/Summe der Treffer in der Datenbank</span></p>
			<p><span class="unique">Anzahl der verschiedenen Einträge in der Kategorie</span></p>
		</div>
		<div id="statGraphics_wrapper">
			<div id="statGraphics_settings">
				<label class="left unlimitedWidth barchart none" onclick="_toggleGraphs(this,'barchart')"><i class="fas fa-chart-bar"></i>&nbsp;</label> 
				<label class="left unlimitedWidth piechart none" onclick="_toggleGraphs(this,'piechart')"><i class="fas fa-chart-pie"></i>&nbsp;</label> 
			</div>
			<div class="clear"></div>
			<div id="statGraphics">
				<div class="barchart hits" id="statGraphicsBarChartHits" hidden style="visibility: hidden;"></div>
				<div class="barchart unique" id="statGraphicsBarChartUnique" hidden style="visibility: hidden;"></div>
				<div class="piechart hits" id="statGraphicsPieChartHits" hidden style="visibility: hidden;"></div>
				<div class="piechart unique" id="statGraphicsPieChartUnique" hidden style="visibility: hidden;"></div>
			</div>
			<div class="clear"></div>
		</div>
		<div class="statTable">
			<?php echo($stat_results); ?>
		</div>
	</div>
	<div id="details_wrapper">
		<?php includeFunctions('RESULTDETAILS',$conn); ?>
		<h2>Details</h2>
		<div class="resultTable">
			<?php echo($table_results); ?>
		</div>
	</div>
<?php
}

function applyFiltersOnlyChangeConfig(array $parameter, mysqli $conn)
{
	applyFilters($parameter,$conn,false,false,true);
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
			$id = array($value);
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
	if ( ! isset($_config['_openids']) ) { $_config['_openids'] = array(); }
	//no openids in mass edit
	if ( ! in_array($_array,$_config['_openids']) AND ! isset($_array['massEdit']) ) { $_config['_openids'][] = $_array; }
	updateConfig($_config,$conn);
	?>

	<div class="hidden"><div class="_table_"><?php html_echo($table); ?></div><div class="_id_"><?php if ( sizeof($id) == 1 ) { html_echo($id[0]); } else { echo('-1'); }; ?></div></div>
	<div class="content section" onclick="_disableClass(this,'noupdate'); this.onclick = ''; ">
		<?php $rnd=rand(0,2147483647); ?>
		<?php 
		//only for single edit
		if ( sizeof($id) == 1 ) { ?>
			<form method="post" id="reload<?php echo($rnd); ?>" class="inline" action="" onsubmit="callFunction(this,'getDetails','_popup_',false,'details','_close',true).then(()=>{ return false; }); return false;">
				<input hidden form="reload<?php echo($rnd); ?>" type="text" value="<?php html_echo($id[0]); ?>" name="id_<?php html_echo($table); ?>" />
				<input form="reload<?php echo($rnd); ?>" id="submitReload<?php echo($rnd); ?>" type="submit" hidden />
				<label class="unlimitedWidth date" title="neu laden" for="submitReload<?php echo($rnd); ?>"><i class="fas fa-redo-alt"></i></label>
			</form>
			<?php updateTime(); updateLastEdit($PARAM['changedat']); ?>
			<div class="db_headline_wrapper"><h2 class="db_headline"><i class="fas fa-<?php html_echo($iconname); ?>"></i> 
			<?php
				$_tmp_keys = array_keys($_config['filters']);
				unset($value); unset($index);
				foreach ( $_tmp_keys as $index=>$value )
				{
					if ( substr($value,0,strlen($table)) != $table ) { unset($_tmp_keys[$index]); }
					else { $_tmp_keys[$index] = substr($value,strlen($table)+2); }
					//exclude attributions for now:
					for ( $i = 0; $i < sizeof($_table_result); $i++ ) {
						if ( $_tmp_keys[$index] == $_table_result[$i]['tablemachine'] ) { unset($_tmp_keys[$index]); }
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
			<?php 
			// for mass edit
			updateTime(); ?>
			<div class="db_headline_wrapper"><h2 class="db_headline"><i class="fas fa-<?php html_echo($iconname); ?>"></i>&nbsp; <?php echo(sizeof($id)); ?> Einträge </h2></div>
		<?php } ?>	
		<div class="message" id="message<?php echo($table.$id[0]); ?>"><div class="dbMessage" class="<?php echo($dbMessageGood); ?>"><?php echo($dbMessage); ?></div></div>
		<form class="db_options" method="POST" action="" onsubmit="callFunction(this,'dbAction','message').then(()=>{ return false; }); return false;">
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
					<select id="_action<?php echo($table.$id[0]); ?>_sticky" name="dbAction" class="db_formbox" onchange="tinyMCE.triggerSave(); invalid = validate(this,this.closest('form').getElementsByClassName('paramtype')[0].innerText); colorInvalid(this,invalid); if (invalid.length == 0) { updateTime(this); _onAction(this.value,this.closest('form'),'dbAction','message<?php echo($table.$id[0]); ?>'); callFunction(this.closest('form'),'calAction','').then(()=>{ return false; }); }; callFunction(document.getElementById('formFilters'),'applyFilters','results_wrapper',false,'','scrollTo',this).then(()=>{ if ( document.getElementById('_action<?php echo($table.$id[0]); ?>_sticky') ) { document.getElementById('_action<?php echo($table.$id[0]); ?>_sticky').value = ''; this.scrollIntoView(); }; return false; }); return false;" title="Aktion bitte erst nach der Bearbeitung der Inhalte wählen.">
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
								<label class="unlimitedWidth">
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
										$value = _cleanup(_strip_tags($fresult['result'][0][$fkey]));
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
			if ( $scope == "FILTERS" ) { $index = 1; } else { $index = 0; };
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
						onclick="callPHPFunction(this.closest('.functions').parentNode.getElementsByTagName('form')[<?php echo($index); ?>],'<?php echo($_function['functionmachine']); ?>','<?php echo($_function['functiontarget']); ?>','<?php echo($_function['functionclasses']); ?>')"
						title="<?php echo($_function['functionreadable']); ?>"
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
	<div class="time" title="zuletzt geladen"><i class="fas fa-clock"></i> <?php echo(date("H:i:s")); ?></div>
	<?php
}

function updateLastEdit(string $datetime)
{
	?>
	<div class="time" title="zuletzt gespeichert"><i class="fas fa-pencil-alt"></i> <?php echo(DateTime::createFromFormat('Y-m-d H:i:s', $datetime)->format('d.m.Y H:i')); ?></div>
	<?php
}

function trimList(string $a)
{
	$b = '';
	while ( $a != $b ) {
		$b = $a;
		$a=str_replace(',,',',',$b);
		$a=str_replace('[,','[',$a);
		$a=str_replace('{,','{',$a);
		$a=str_replace(',]',']',$a);
		$a=str_replace(',}','}',$a);
		$a=str_replace('""','',$a);
		$a=str_replace('[]','',$a);
		$a=str_replace('{}','',$a);
	}
	return $a;
}

function _evalRestrictions(string $restriction, string $generation, string $rolename, string $username)
{
	switch($generation) {
		case 'CHILD':
			$_values = str_replace('CHILD_ROLE','',$restriction);
			$_values = str_replace('THIS_ROLE',$rolename,$_values);
			$_values = str_replace('USER',$username,$_values);
			$_values = trimList($_values);
			//$_values = implode("\',\'",json_decode($_values,true));
			break;
		case 'PARENT':
			$_values = str_replace('CHILD_ROLE',$rolename,$restriction);
			$_values = str_replace('THIS_ROLE','',$_values);
			$_values = str_replace('USER',$username,$_values);
			$_values = trimList($_values);
			//$_values = implode("\',\'",json_decode($_values,true));
			break;
		default:
			//$_values = implode("\',\'",json_decode($restriction,true));
			$_values = $restriction;
			break;
	}
	return $_values;
}

//no variable type: must allow for NULL to be processed without error
function _cleanup($value)
{
	if ( is_array($value) ) {
		$value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}
	if ( is_array(json_decode($value,true)) ) {
		$values = json_decode($value,true);
		//forget filepaths, take only filedescriptions
		if ( isset($values[4001]) ) { $values = $values[4001]; }
		//format compound fields
		if ( is_array($values[0]) ) {
			$komma = '';
			for ( $i = 0; $i < sizeof($values[0]); $i++ ) {
				for ( $j = 0; $j < sizeof($values); $j++ ) {
					$newvalue .= $komma._cleanup($values[$j][$i]);
					$komma = ', ';
				}
				$komma = '<br />';
			}
			$values = array($newvalue);
		}
		//clean up every component of the array
		foreach ( $values as $index=>$entry ) {
			$values[$index] = _cleanup($entry);
		}
		$value = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}
	//format dates (and times) to locale (here: german) 
	if ( DateTime::createFromFormat('Y-m-d H:i:s', $value) !== FALSE) { 
		$value = DateTime::createFromFormat('Y-m-d H:i:s', $value)->format('d.m.Y');
	}
	if ( DateTime::createFromFormat('Y-m-d', $value) !== FALSE) { 
		$value = DateTime::createFromFormat('Y-m-d', $value)->format('d.m.Y');
	}
	//format numbers (number_format, negative red...)
	if ( is_numeric($value) ) {
		$_class = '';
		if ( $value < 0 ) { $_class="error"; }
		if ( floor($value) != $value ) {
			$value = number_format($value,2,',','.');
		}
		if ( $_class == 'error' ) { $value = "<span class=\"error\">".$value."</span>"; }
	}
	//write json arrays nicer
	if ( strpos($value,'["') > -1 ) {
		$value = str_replace('[','',$value);
		$value = str_replace(']','',$value);
		$value = str_replace('"','',$value);
		$value = str_replace(',',', ',$value);
	}
	return $value;
}

function _strip_tags($value,$length = -1)
{
	if ( is_array($value) ) {
		$value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}
	if ( is_array(json_decode($value,true)) ) {
		$values = json_decode($value,true);
		//forget filepaths, take only filedescriptions
		if ( isset($values[4001]) ) { $values = $values[4001]; }
		//
		foreach ( $values as $index=>$entry ) {
			$values[$index] = _strip_tags($entry,$length);
		}
		$value = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}
	if ( strip_tags($value) != $value ) {
		$border = '"';
	} else {
		$border = '';
	}
	if ( $length == -1 ) {
		$value = $border.strip_tags($value).$border;
	} else {
		if ( strlen(strip_tags($value)) > $length ) {
			$dots = '...';
		} else {
			$dots = '';
		}
		$value = $border.substr(strip_tags($value),0,$length).$dots.$border;
	}
	if ( $value == '' ) { $value = '[k.A.]'; }
	return $value;
}

function openFile($PARAMETER)
{
	$rnd = rand(0,32767);
	$filename = basename($PARAMETER['trash']);
	$fullname = $PARAMETER['trash'];
	$_error = '';
	if ( ! symlink($fullname,'../../public/'.$filename) ) { $_error = "Fehlende Berechtigungen in Webroot; bitte Administrator kontaktieren."; }
	?>
	<div class="filepreview">
	<?php updateTime(); ?>
	<?php if ( $_error != '' ) { ?>
		<div class="error"><?php echo($_error); ?></div>
	<?php return; } ?>
	<div class="db_headline_wrapper"><h2>Vorschau von <a href="<?php echo($filename); ?>?v=<?php echo($rnd); ?>" target="_blank"><?php echo($filename); ?></a></h2></div>
	<iframe
		src='<?php echo($filename); ?>?v=<?php echo($rnd); ?>' 
		onload="document.getElementById('trash').value = '<?php echo($filename); ?>'; callFunction('_','_unlink').then(()=>{ document.getElementById('trash').value = ''; });"
		frameborder="0" border="0" cellspacing="0"
		class="_iframe"
	>
	</iframe>
	</div>
	<?php
}

function _unlink($PARAMETER)
{
	$filename = basename($PARAMETER['trash']);
	if ( is_link('../../public/'.$filename) ) { unlink('../../public/'.$filename); }
	clearstatcache();
}

function _openFile($PARAMETER)
{
	?>
	<?php header('Content-Type: '.mime_content_type($PARAMETER['trash'])); header('Content-Disposition: attachment; filename="'.basename($PARAMETER['trash']).'"'); readfile($PARAMETER['trash']); ?>
	<?php
}

function _len(array $checked, int $offset = 0) {
	$_checked_length = 1;
	if ( isset($checked[$offset][0]) ) {
		$_checked_length = sizeof($checked[$offset]);
	} else {
		foreach ( $checked[$offset] as $index=>$value ) {
			$_checked_length = sizeof($value);
			break;
		}
	}
	return $_checked_length;
}

function _extract(array $checked, int $compound, int $item, int $offset = 0) {
	if ( isset($checked[$offset+$compound][0]) ) {
		$_extracted = array($checked[$offset+$compound][$item]);
	} else {
		$_extracted = array();
		foreach ( $checked[$offset+$compound] as $index=>$value ) {
			if ( ! is_array($value) ) { $value = array($value); }
			$_extracted[$index] = array($value[$item]);
		}
	}
	return $_extracted;
}


?>
