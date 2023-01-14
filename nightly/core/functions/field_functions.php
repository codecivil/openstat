<?php
/*
 * $PARAM is the form containing values of table entries
 * $trigger is the initial/changed status info of the function table entry
 * a FIELD function has parameters ($trigger,$PARAM,$conn)
 * 
 * and may get profile info by invoking function getProfiles($conn,$profilefieldname='',$searchstring='',$public=false)
 * 
 * PLEASE, PLEASE query only those profiles you need!
*/ 

//specify headers like to, subject and body in config using $<TABLE>_<KEY> and $trigger[<STATUSNAME>][<KEY>] as placeholders
function emailToClient($trigger,$PARAM,$conn) {
	$_config = getFunctionConfig('emailTo',$conn);
	$_flags = getFunctionFlags('emailTo',$conn); //to be implemented in os_functions.php
	$_tmpconfig = array();
	//determine whether an email has to be sent according to flags
	//...(return if answer is no)
	//replace placeholders
	foreach ( $_config as $header => $value ) {
		preg_match_all('/\$([^ ,]*)/',$value,$matches);
		foreach ( $matches[1] as $pattern ) {
			if ( isset($PARAM[$pattern]) ) {
				$value = preg_replace('/'.$pattern.'/g',$PARAM[$pattern],$value);
			}
		}
		preg_match_all('/\$trigger\[([^\]+]\]\[[^\]+])\]/',$value,$matches);
		foreach ( $matches[1] as $pattern ) {
			$pattern_array = explode('][',$pattern,2);
			if ( isset($trigger[$pattern_array[0]][$pattern_array[1]]) ) {
				$value = preg_replace('/\$trigger\['.$pattern.'\]/g',$triggger[$pattern_array[0]][$pattern_array[1]],$value);
			}
		}
		$_tmpconfig[$header] = $value;
	}
	$_config = json_decode(json_encode($_tmpconfig),true);
	unset($_tmpconfig);
	//construct and send e-mail
	if ( sendmail($_config) ) { return "e-Mail wurde erfolgreich gesendet."; } else { return "e-Mail konnte nicht gesendet werden."; }
}
?>
