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
	//handle attachments: set internal header "Attachment" to process what and how to attach
	//currently, only ICS-generator
	if ( isset($_config['Attachment']) ) {
		if ( strpos($_config['Attachment'],'ics(') === 0 ) {
			$_attach = json_decode(str_replace(')','',str_replace('ics(','',$_config['Attachment'])),true);
			if ( isset($_attach) and $_attach != null ) {
				//get uid in proper uuid form (and do not leak internal openStat data...)
				if ( isset($_attach['uid']) ) { $_attach['uid'] = uuid($_attach['uid']); }
				$_ics = _generateICS($_attach);
				//modify Body...
				if ( isset($_ics) AND $_ics != '' ) {
					$_config['Attachment'] = array("mimetype" => "text/calendar", "filename" => $_attach['uid'].'.ics', "body" => $_ics);
				} else {
					unset($_config['Attachment']);
				}
			} else {
				unset($_config['Attachment']);
			}			
		}
	}
	//$_config = $result['return'];
	$_return['log'] = array( "From" => $_config['From'], "To" => $_config['To'], "Subject" => $_config['Subject'], "Body" => $_config['Body']);
	if ( isset($_config['Attachment']) ) {
		$_return['log']['Anhang'] = $_config['Attachment']['filename'];
	}
	if ( sendmail($_config) ) {
		$_return['status'] = "OK"; 
		$_return['js'] = "e-Mail an ".$_config['To']." wurde erfolgreich gesendet."; 
	} else {
		$_return['status'] = "Fehler";
		$_return['log']['error'] = "e-Mail konnte nicht versendet werden.";
		$_return['js'] = "e-Mail konnte nicht gesendet werden.";
	}
	return $_return;
}

function _generateICS(array $_ics_array) {
	//retun nothing if start or end time is not set
	if ( ! isset($_ics_array['dtstart']) ) { return; }
	if ( ! isset($_ics_array['dtend']) ) { return; }
	//
	$_body = "BEGIN:VCALENDAR
VERSION:2.0
CALSCALE:GREGORIAN
PRODID:-//openstat//openStat-v1.7.17+//DE
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
";
	$_created = date('Ymd\Thms');
	if ( ! isset($_ics_array['uid']) ) { $_ics_array['uid'] = uuid(); }
	if ( ! isset($_ics_array['summary']) ) { $_ics_array['summary'] = ''; }
	$_body .= "BEGIN:VEVENT
CREATED:".$_created."
LAST-MODIFIED:".$_created."
UID:".$_ics_array['uid']."
DTSTAMP:".$_created."
SUMMARY:".$_ics_array['summary']."
DTSTART;TZID=Europe/Berlin:".datetime2icsTime($_ics_array['dtstart'])."
DTEND;TZID=Europe/Berlin:".datetime2icsTime($_ics_array['dtend'])."
TRANSP:OPAQUE
END:VEVENT
";
	$_body .= "END:VCALENDAR";
	return $_body;
}
?>
