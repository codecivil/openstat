<?php
/*
 * $PARAM is the form containing values of table entries
 * $trigger is the initial/changed status info of the function table entry
 * $config is the name of the key of the function config containing the used config; if '', then the whole config is used
 * a FIELD function has parameters ($config,$trigger,$PARAM,$conn)
 * 
 * and may get profile info by invoking function getProfiles($conn,$profilefieldname='',$searchstring='',$public=false)
 * 
 * PLEASE, PLEASE query only those profiles you need!
*/ 

//specify headers like to, subject and body in config using $<TABLE>_<KEY> and $trigger[<STATUSNAME>][<KEY>] as placeholders
//the field functions gets already preprocessed config!
function emailTo(array $_config,array $trigger,array $PARAM,mysqli $conn) {
	//this must be done at the very beginning of EVERY field function
	//$_config contains the used function config after replacing placeholders by actual values
	$_return = array('log' => '', 'js' => ''); //log and js may be objects; have to be returned by any field function
	/* $result = FUNCTIONpreprocess(__FUNCTION__,$configname,$trigger,$PARAM,$conn);
	if ( ! $result['success']['value'] ) {
		$_return['log'] = __FUNCTION__ .': '.$result['success']['error']; 
		$_return['js'] = 'Bitte dem Administrator melden: '.$result['success']['error'];
		return $_return;
	}
	*/
	//construct and send e-mail
	//$_config = $result['return'];
	if ( sendmail($_config) ) {
		$_return['status'] = "OK"; 
		$_return['log'] = $_config; //to be changed
		$_return['js'] = "e-Mail an ".$_config['To']." wurde erfolgreich gesendet."; 
	} else {
		$_return['status'] = "Fehler";
		$_config['error'] = "e-Mail konnte nicht versendet werden.";
		$_return['log'] = $_config; //to be changed
		$_return['js'] = "e-Mail konnte nicht gesendet werden.";
	}
	return $_return;
}
?>
