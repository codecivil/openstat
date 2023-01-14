<?php
/*
 * $PARAM is the form containing values of table entries
 * $param is the initial/changed status info of the function table entry
 * a FIELD function has parameters ($param,$PARAM,$conn)
 * and may get profile info by certain extra functions like getProfile($searchstring,$profilefieldname)
*/ 
function FUNCTIONAction (array $PARAM, mysqli $conn) {
	// What is the easiest way to find fields of type FUNCTION?
	$FUNCTIONPARAM = array();
	foreach ( $PARAM as $rawkey => $value ) {
		if ( isset(json_decode($value)['type']) AND json_decode($value)['type'] == "FUNCTION" ) {
			$FUNCTIONPARAM[] = $value;
		}
	}
	switch($PARAM['dbAction']) {
		case 'delete':
			break;
		case 'edit':
		case 'insert':
			//execute any function with its status parameter as argument
			foreach ( $FUNCTIONPARAM as $functionjson ) {
				$functions = json_decode($functionjson)['functions'];
				$param = json_decode($functionjson)['status'];
				foreach ( $functions as $function ) {
					if ( $function != '' ) {
						$function($param,$PARAM,$conn);
					}
				}
			}
			break;
	}
}

// returns array of JSON strings of machine profiles with index 0 being the JSON string of the user's private profile
// $profilefieldname and $searchstring are optional parameters
// if you want to specify $searchstring but not $profilefieldname use $profilefieldname = "_ALL")
// if you want to get public profiles instead specify $public = true (for displaying in function results)
function getProfiles(mysqli $conn, string $profilefieldname='', string $searchstring='', bool $public=false) {
	//get own profile info
	unset($_stmt_array);
	$_stmt_array['stmt'] = 'SELECT _private from os_userprofiles WHERE userid = ?';
	$_stmt_array['str_types'] = "i";
	$_stmt_array['arr_values'] = array();
	$_stmt_array['arr_values'][] = $_SESSION['os_user'];
	$myProfile = _execute_stmt($_stmt_array)['result']['_private'][0];
	//get other profiles; you must read them from _machine (_public is collected by a separate function so the developper is aware of this..)
	unset($_stmt_array);
	$_stmt_array['stmt'] = 'SELECT _machine from os_userprofiles WHERE userid != ?';
	$_stmt_array['str_types'] = "i";
	$_stmt_array['arr_values'] = array();
	$_stmt_array['arr_values'][] = $_SESSION['os_user'];
	$otherProfiles = _execute_stmt($_stmt_array)['result']['_machine'];
	$profiles = array_merge(array($myProfile),$otherProfiles);
	//filter by fieldname first
	if ( $profilefieldname != '' AND $profilefieldname != '_ALL') {
		$filteredprofiles = array();
		foreach ( $profiles as $profile ) {
			$filteredprofiles[] = json_encode(array(json_decode($profile,true)['userid'],json_decode($profile,true)[$profilefieldname]));
		}
		$profiles = json_decode(json_encode($filteredprofiles));
	}
	//filter by searchstring
	if ( $searchstring != '' ) {
		$filteredprofiles = array();
		foreach ( $profiles as $profile ) {
			if ( preg_match($searchstring,$profile) ) {
				$filteredprofiles[] = $profile;
			}
		}
		$profiles = json_decode(json_encode($filteredprofiles));
	}
	return $profiles;
}

function sendmail(array $headers) {
	$additional_headers = array();
	$argument = array();
	foreach ( $headers as $header => $value ) {
		$processed = false;
		if ( $header == "To" ) { $argument[0] = $value; $processed = true; }
		if ( $header == "Subject" ) { $argument[1] = $value; $processed = true; }
		if ( $header == "Body" ) { $argument[2] = $value; $processed = true; }
		if ( ! $processed ) { $additional_headers[$header] = $value; }
	}
	return mail($argument[0],$argument[1],$argument[2],$additional_headers);
}
?>
