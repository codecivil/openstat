<?php
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
    //expand if given by trashForm (called in this way by importConfig in JS)
    if ( isset($config['trash']) ) { $config = json_decode($config['trash'],true); }
	//
	if ( ! isset($config['configname']) ) { return; }
	$_wait = updateConfig($config,$conn,$config['configname']);
}

//reads config
function getConfig(mysqli $conn, string $configname = 'Default') 
{
	if ( ! isset($_SESSION['os_user']) ) { return; }; // is this really necessary? added 2021-07-14 (see apache error.log for the 10x2s delay at login,logout,chpwd
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

function exportConfig (array $config, mysqli $conn)
{
	if ( ! isset($config['configname']) OR $config['configname'] == "Default" ) { return; }
	$configname = $config['configname'];
	$savedconfig = getConfig($conn,$configname);
    $_config = array();
    foreach ( ["filters","table","table_hierarchy","configname","subtable","showSubtablesOf"] as $_key ) {
        $_config[$_key] = $savedconfig[$_key];
    }
    return base64_encode(json_encode($_config));
}

//importConfig is initialized on JS side
function importConfig(array $PARAM, mysqli $conn ){ return; }

function removeOpenId(array $entry, mysqli $conn)
{
	$conf = getConfig($conn);
	foreach ( $entry as $tableidjson ) {
		$tableid = json_decode($tableidjson,true);
		foreach ( $_SESSION['os_opennow'] as $key=>$value ) {
			if ( $tableid == $value ) { 				
				//unset($conf['_openids'][$key]); array is not associative, so splice it better...
				array_splice($_SESSION['os_opennow'],$key,1); 
				}
		}
		$conf['_openids'] = $_SESSION['os_opennow'];
	}
	return updateConfig($conf,$conn);
}
?>
