<?php
/* sets up
 * $_result: calendar connection data of calendar set in $PARAMETER
 * $_old_entry: calendar, icsid, etag of os_caldav entry matching $PARAMETER 
 * $_old_result: calendar connection data of calendar of $_old_entry
 * 
 * and executes _CALDAV<dbAction>
 */
function calAction(array $PARAMETER,mysqli $conn) {
	//at the moment, massEditing works only on the same and chosen to be edited (!) calendar. Instead of the line below we should query the databse for the correct 
	//calendar...
	//this is wrong; you must be able to delete entries...
	//if ( ! isset($PARAMETER['id_os_calendars']) OR $PARAMETER['id_os_calendars'] == '' ) { return; };
	//!!! DEAL PROPERLY WITH EMPTY id_os_calendars: how to distinguish mass editing w/ no changing calendars and removing from calendar...
	if ( ! isset($PARAMETER['id_os_calendars']) OR $PARAMETER['id_os_calendars'] == '' ) { $PARAMETER['id_os_calendars'] = ''; };
	//is PARAMETER[id_<table>] alway JSON of an array? If not, make it here... (to do)
	//parse massEditing table id array here and call _CALDAV functions repeatedly...
	$_param_id_table = array();
	switch($PARAMETER['dbAction']) {
		case 'edit':
		case 'delete':
			$_param_id_table = json_decode($PARAMETER['id_'.$PARAMETER['table']]);
			break;
		case 'insert':
			foreach ( $_SESSION['insert_id'] as $insertion ) {
				if ( isset(json_decode($insertion,true)['id_'.$PARAMETER['table']]) ) { $_param_id_table[] = json_decode($insertion,true)['id_'.$PARAMETER['table']]; } 
			}
			unset($_SESSION['insert_id']);
			break;
	}
	if ( sizeof($_param_id_table) == 0 ) { return; }
	$flag_massEdit = false;
	if ( sizeof($_param_id_table) > 1 ) { $flag_massEdit = true; }
	//get info about new id_os_calendar; this is $_result_array for permissions and $_result for connection data
	unset($_stmt_array); $_stmt_array = array(); $_result_array = array();
	$_stmt_array['stmt'] = "SELECT allowed_roles, allowed_users FROM view__os_calendars__".$_SESSION['os_role']." WHERE id_os_calendars = ?";
	$_stmt_array['str_types'] = "i";
	$_stmt_array['arr_values'] = array();
	$_stmt_array['arr_values'][] = $PARAMETER['id_os_calendars'];
	$_localresult = execute_stmt($_stmt_array,$conn,true);
	if ( isset($_localresult['result']) ) { $_result_array = $_localresult['result'][0]; } //keynames as last array field
	//deal with empty result fields
	if ( ! isset($_result_array['allowed_users']) OR ! json_decode($_result_array['allowed_users']) ) { $_result_array['allowed_users'] = '[]'; }
	if ( ! isset($_result_array['allowed_roles']) OR ! json_decode($_result_array['allowed_roles']) ) { $_result_array['allowed_roles'] = '[]'; }
	//
	$_result = array();
	if ( in_array($_SESSION['os_user'],json_decode($_result_array['allowed_users'])) OR in_array($_SESSION['os_role'],json_decode($_result_array['allowed_roles'])) OR in_array($_SESSION['os_parent'],json_decode($_result_array['allowed_roles'])) )
	{ 
		unset($_stmt_array); $_stmt_array = array(); unset($_result_array);
		$_stmt_array['stmt'] = "SELECT calendarurl, secretname, calendaruser FROM view__os_calendars__".$_SESSION['os_role']." WHERE id_os_calendars = ?";
		$_stmt_array['str_types'] = "i";
		$_stmt_array['arr_values'] = array();
		$_stmt_array['arr_values'][] = $PARAMETER['id_os_calendars'];
		$_result = execute_stmt($_stmt_array,$conn,true)['result'][0]; //keynames as last array field 
	}
	foreach( $_param_id_table as $_id_table) {
		$PARAMETER['id_'.$PARAMETER['table']] = $_id_table;
		//get old id_os_calendars from os_caldav table
		unset($_stmt_array); $_stmt_array = array(); $_old_entry = array();
		$_stmt_array['stmt'] = "SELECT id_os_calendars,icsid,etag from os_caldav WHERE tablemachine = ? AND id_table = ? ";
		$_stmt_array['str_types'] = "si";
		$_stmt_array['arr_values'] = array();
		$_stmt_array['arr_values'][] = $PARAMETER['table'];
		$_stmt_array['arr_values'][] = $PARAMETER['id_'.$PARAMETER['table']];
		$_old_entry_array = execute_stmt($_stmt_array,$conn,true);
		if ( isset($_old_entry_array['result']) ) {
			$_old_entry = $_old_entry_array['result'][0]; //keynames as last array field
		} 
		//if massEditing and no id_os_calendars is set, take old value
		if ( $flag_massEdit AND ( ! isset($PARAMETER['id_os_calendars']) OR $PARAMETER['id_os_calendars'] == '' ) ) { $PARAMETER['id_os_calendars'] = $_old_entry['id_os_calendars']; } 
		unset($_stmt_array); $_stmt_array = array(); $_old_result = array();
		if ( $_old_entry['id_os_calendars'] == $PARAMETER['id_os_calendars'] ) {
			$_old_result = $_result; 
		} else {
			unset($_stmt_array); $_stmt_array = array(); $_result_array = array();
			$_stmt_array['stmt'] = "SELECT allowed_roles, allowed_users FROM view__os_calendars__".$_SESSION['os_role']." WHERE id_os_calendars = ?";
			$_stmt_array['str_types'] = "i";
			$_stmt_array['arr_values'] = array();
			$_stmt_array['arr_values'][] = $_old_entry['id_os_calendars'];
			$_localresult = execute_stmt($_stmt_array,$conn,true);
			if ( isset($_localresult['result']) ) { $_result_array = $_localresult['result'][0]; } //keynames as last array field
			//deal with empty result fields
			if ( ! isset($_result_array['allowed_users']) OR ! json_decode($_result_array['allowed_users']) ) { $_result_array['allowed_users'] = '[]'; }
			if ( ! isset($_result_array['allowed_roles']) OR ! json_decode($_result_array['allowed_roles']) ) { $_result_array['allowed_roles'] = '[]'; }
			//
			if ( in_array($_SESSION['os_user'],json_decode($_result_array['allowed_users'])) OR in_array($_SESSION['os_role'],json_decode($_result_array['allowed_roles'])) OR in_array($_SESSION['os_parent'],json_decode($_result_array['allowed_roles'])) )
			{ 
				$_stmt_array['stmt'] = "SELECT calendarurl, secretname, calendaruser FROM view__os_calendars__".$_SESSION['os_role']." WHERE id_os_calendars = ?";
				$_stmt_array['str_types'] = "i";
				$_stmt_array['arr_values'] = array();
				$_stmt_array['arr_values'][] = $_old_entry['id_os_calendars'];
				$_old_result = execute_stmt($_stmt_array,$conn,true)['result'][0]; //keynames as last array field 
			}
		}
		switch($PARAMETER['dbAction']) {
			case 'edit': 
				_CALDAVUpdate($PARAMETER,$_result,$_old_entry,$_old_result,$conn);
				break;
			case 'insert':
				_CALDAVInsert($PARAMETER,$_result,$_old_entry,$_old_result,$conn);
				break;
			case 'delete':
				_CALDAVDelete($PARAMETER,$_result,$_old_entry,$_old_result,$conn);
				break;
		}
	}
	unset($_result);
}

function _CALDAVInsert(array $PARAMETER, array $_result, array $_old_entry, array $_old_result, mysqli $conn)
{
	/*take id from PARAMETER and if not there, then look for SESSION...
	//the table entry may be only updated but the caldav entry newly created!
	if ( isset($PARAMETER['id_'.$PARAMETER['table']]) AND $PARAMETER['id_'.$PARAMETER['table']] != '' ) { $_id_table = $PARAMETER['id_'.$PARAMETER['table']]; }
	//get id of new insert in table
	if ( ! isset($_id_table) AND isset($_SESSION['insert_id']) ) { 
		foreach ( $_SESSION['insert_id'] as $insertion ) {
			if ( isset(json_decode($insertion,true)['id_'.$PARAMETER['table']]) ) { $_id_table = array(json_decode($insertion,true)['id_'.$PARAMETER['table']]); } 
		}
		unset($_SESSION['insert_id']);
	}
	*/
	//return if no calendar is set
	if ( $PARAMETER['id_os_calendars'] == '' OR $PARAMETER['id_os_calendars'] == 'NULL' OR $PARAMETER['id_os_calendars'] == '_NULL_' ) { return; }
	//
	$_id_table = $PARAMETER['id_'.$PARAMETER['table']];
	//PUT request to calendar
	$_icsid = uuid();
	$_uid = uuid();
	$_header = array("Content-Type: text/calendar; charset=utf-8");
	$_created = gmdate('Ymd\THis\Z',strtotime("now"));
	$_times = array();
	$_caldavfields = _CALDAVgetFields($PARAMETER,$conn);
	$_body = _generateVCALENDAR($_created,$_created,$_uid,$_caldavfields['summary'],$_caldavfields['dtstart'],$_caldavfields['dtend']);
	if ( isset($_SESSION['DEBUG']) AND $_SESSION['DEBUG'] ) { file_put_contents($GLOBALS["debugpath"].$GLOBALS["debugfilename"],'CALACTION Insert: '.$PARAMETER['id_os_calendars'].PHP_EOL,FILE_APPEND); }
	$_put = curl_init($_result['calendarurl'].'/'.$_icsid.'.ics');
	curl_setopt($_put, CURLOPT_HEADER, 1);
	curl_setopt($_put, CURLOPT_CUSTOMREQUEST, "PUT");
	curl_setopt($_put, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($_put, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	if ( substr($_result['calendarurl'],0,5) == 'https' ) {
		curl_setopt($_put, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($_put, CURLOPT_SSL_VERIFYHOST, 0);
	}
	//	curl_setopt($_put, CURLOPT_USERNAME, $_result['calendaruser']);
	curl_setopt($_put, CURLOPT_USERPWD, $_result['calendaruser'].':'.$_SESSION['os_secret'][$_result['secretname']]['secret']);
	curl_setopt($_put, CURLOPT_HTTPHEADER, $_header);
	curl_setopt($_put, CURLOPT_POSTFIELDS, $_body);
	$_returned = curl_exec($_put);
	if ( isset($_SESSION['DEBUG']) AND $_SESSION['DEBUG'] ) { file_put_contents($GLOBALS["debugpath"].$GLOBALS["debugfilename"],'CALACTION: '.json_encode($_returned).PHP_EOL,FILE_APPEND); }
	curl_close($_put);
	//extract ETag:
	$_etag = substr($_returned,strpos($_returned,"ETag"));
	$_etag = substr($_etag,strpos($_etag,'"')+1);
	$_etag = substr($_etag,0,strpos($_etag,'"'));
	//save eTag in os_caldav
	unset($_stmt_array); $_stmt_array = array(); unset($_result_array);
	$_stmt_array['stmt'] = "INSERT INTO os_caldav (tablemachine,id_table,id_os_calendars,icsid,etag) VALUES (?,?,?,?,?)";
	$_stmt_array['str_types'] = "siiss";
	$_stmt_array['arr_values'] = array();
	$_stmt_array['arr_values'][] = $PARAMETER['table'];
	$_stmt_array['arr_values'][] = $_id_table;
	$_stmt_array['arr_values'][] = $PARAMETER['id_os_calendars'];
	$_stmt_array['arr_values'][] = $_icsid;
	$_stmt_array['arr_values'][] = $_etag;
	execute_stmt($_stmt_array,$conn,true);
}

//parse table config for calendar entry
function _CALDAVgetFields(array $PARAMETER,mysqli $conn) {
	$_table = $PARAMETER['table'];
	unset($_stmt_array); $_stmt_array = array(); unset($_result_array);
	$_stmt_array['stmt'] = "SELECT calendarfields FROM os_tables WHERE tablemachine = ?";
	$_stmt_array['str_types'] = "s";
	$_stmt_array['arr_values'] = array($_table);
	$_calendarfields_json = execute_stmt($_stmt_array,$conn)['result']['calendarfields'][0];
	$_calendarfields = json_decode($_calendarfields_json,true);
	$_return = array();
	foreach ( $_calendarfields as $_key => $_keydef ) {
		foreach ( $_keydef['fields'] as $fieldindex => $field ) {
			$value = "";
			$field_array = explode('__',$field,2);
			$properfield = $field_array[sizeof($field_array)-1];
			if ( sizeof($field_array) > 1 ) { 
				$fieldtable = $field_array[0];
				if (isset($PARAMETER['id_'.$fieldtable])) {
					unset($_stmt_array); $_stmt_array = array(); unset($_result_array);
					$_stmt_array['stmt'] = "SELECT ".$properfield." FROM view__".$fieldtable."__".$_SESSION['os_role']." WHERE id_".$fieldtable." = ?";
					$_stmt_array['str_types'] = "i";
					$_stmt_array['arr_values'] = array($PARAMETER['id_'.$fieldtable]);
					$value = execute_stmt($_stmt_array,$conn)['result'][$properfield][0];
				}
			} else { 
				if (isset($PARAMETER[$_table.'__'.$properfield])) {
					$value = $PARAMETER[$_table.'__'.$properfield];
				}
			}
			if (isset($_keydef['format'])) {
				$_keydef['format'] = str_replace('#'.$fieldindex,$value,$_keydef['format']);
			} else {
				$_keydef['format'] = $value;
			}
		}
		//make proper datetime format if it is possible (heuristic approach!)
		if ( ($_time = strtotime($_keydef['format'])) !== false ) {
			$_keydef['format'] = date('Ymd\THis',$_time);	
		} 
		//
		$_return[$_key] = $_keydef['format'];
	}
	return $_return;	
}

////obsolete: new version uses calendarfields instead
//dynamically guess best fields for calendar entry
function _CALDAVgetFields_old(array $PARAMETER)
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

function _CALDAVDelete(array $PARAMETER, array $_result, array $_old_entry, array $_old_result, mysqli $conn)
{
	if ( isset($_SESSION['DEBUG']) AND $_SESSION['DEBUG'] ) { file_put_contents($GLOBALS["debugpath"].$GLOBALS["debugfilename"],'CALACTION: Delete'.json_encode($_old_entry).json_encode($_old_result).PHP_EOL,FILE_APPEND); }
	//delete entry in os_caldav and on caldav server
	if ( isset($_old_entry['id_os_calendars']) ) {
//		$_header = array('Content-Type: text/calendar; charset=utf-8','If-Match: "'.$_old_entry['etag'].'"');
		$_header = array('Content-Type: text/calendar; charset=utf-8');
		$_delete = curl_init($_old_result['calendarurl'].'/'.$_old_entry['icsid'].'.ics');
		curl_setopt($_delete, CURLOPT_HEADER, 1);
		curl_setopt($_delete, CURLOPT_CUSTOMREQUEST, "DELETE");
		curl_setopt($_delete, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($_delete, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		if ( substr($_old_result['calendarurl'],0,5) == 'https' ) {
			curl_setopt($_delete, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($_delete, CURLOPT_SSL_VERIFYHOST, 0);
		}
	//	curl_setopt($_delete, CURLOPT_USERNAME, $_result['calendaruser']);
		curl_setopt($_delete, CURLOPT_USERPWD, $_old_result['calendaruser'].':'.$_SESSION['os_secret'][$_old_result['secretname']]['secret']);
		curl_setopt($_delete, CURLOPT_HTTPHEADER, $_header);
		$_returned = curl_exec($_delete);
		if ( isset($_SESSION['DEBUG']) AND $_SESSION['DEBUG'] ) { file_put_contents($GLOBALS["debugpath"].$GLOBALS["debugfilename"],'CALACTION DELETE: '.json_encode($_returned).PHP_EOL,FILE_APPEND); }
		curl_close($_delete);
	}
	//delete also from os_caldav
	unset($_stmt_array); $_stmt_array = array(); unset($_result_array);
	$_stmt_array['stmt'] = "DELETE FROM os_caldav WHERE tablemachine = ? AND id_table = ?";
	$_stmt_array['str_types'] = "si";
	$_stmt_array['arr_values'] = array();
	$_stmt_array['arr_values'][] = $PARAMETER['table'];
	$_stmt_array['arr_values'][] = $PARAMETER['id_'.$PARAMETER['table']];
	if ( isset($_SESSION['DEBUG']) AND $_SESSION['DEBUG'] ) { file_put_contents($GLOBALS["debugpath"].$GLOBALS["debugfilename"],'CALACTION: '.json_encode($_stmt_array).PHP_EOL,FILE_APPEND); }
	execute_stmt($_stmt_array,$conn,true);
}

function _CALDAVUpdate(array $PARAMETER, array $_result, array $_old_entry, array $_old_result, mysqli $conn)
{
	//get caldav data of current entry in order to change it properly (and then do insert actions...)
	//e.g. mass editing may not provide the id_os_calendars and they may differ...
	if ( ! isset($PARAMETER['id_os_calendars']) OR $PARAMETER['id_os_calendars'] == '' ) { 
		unset($_stmt_array); $_stmt_array = array(); unset($_entry_array);
		$_stmt_array['stmt'] = "SELECT id_os_calendars from view__".$PARAMETER['table']."__".$_SESSION['os_role']." WHERE id_".$PARAMETER['table']." = ? ";
		$_stmt_array['str_types'] = "i";
		$_stmt_array['arr_values'] = array();
		$_stmt_array['arr_values'][] = $PARAMETER['id_'.$PARAMETER['table']];
		$PARAMETER['id_os_calendars'] = execute_stmt($_stmt_array,$conn)['result']['id_os_calendars'][0];
	}
	//delete entry if calendar association is empty now
	if ( ! isset($PARAMETER['id_os_calendars']) OR $PARAMETER['id_os_calendars'] == '' OR $PARAMETER['id_os_calendars'] == 'NULL' OR $PARAMETER['id_os_calendars'] == '_NULL_' ) {
		_CALDAVDelete($PARAMETER,$_result,$_old_entry,$_old_result,$conn);
		return;
	}
	//use php-curl to delete and create new entry on caldav server when id_os_calendars changed
	// shortcut for empty old_entry: insert and return
	if ( sizeof($_old_entry) == 0 ) {
		_CALDAVInsert($PARAMETER,$_result,$_old_entry,$_old_result,$conn);
		return;
	}
	//
	if ( $_old_entry['id_os_calendars'] != $PARAMETER['id_os_calendars'] )
	{
		//delete old entry and insert new
		if ( sizeof($_old_result) > 0 )
		{
			_CALDAVDelete($PARAMETER,$_result,$_old_entry,$_old_result,$conn);
		}
		$_SESSION['insert_id'] = array(json_encode(array('id_'.$PARAMETER['table'] => $PARAMETER['id_'.$PARAMETER['table']])));
		_CALDAVInsert($PARAMETER,$_result,$_old_entry,$_old_result,$conn);
	} else {
		//update entry
		$_uid = uuid();
//		$_header = array('Content-Type: text/calendar; charset=utf-8','If-Match: "'.$_old_entry['etag'].'"');
		$_header = array('Content-Type: text/calendar; charset=utf-8');
		$_created = gmdate('Ymd\THis\Z',strtotime("now"));
		$_times = array();
		$_caldavfields = _CALDAVgetFields($PARAMETER,$conn);
		$_body = _generateVCALENDAR($_created,$_created,$_uid,$_caldavfields['summary'],$_caldavfields['dtstart'],$_caldavfields['dtend']);
		$_put = curl_init($_result['calendarurl'].'/'.$_old_entry['icsid'].'.ics');
		curl_setopt($_put, CURLOPT_HEADER, 1);
		curl_setopt($_put, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($_put, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($_put, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		if ( substr($_result['calendarurl'],0,5) == 'https' ) {
			curl_setopt($_put, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($_put, CURLOPT_SSL_VERIFYHOST, 0);
		}
	//	curl_setopt($_put, CURLOPT_USERNAME, $_result['calendaruser']);
		curl_setopt($_put, CURLOPT_USERPWD, $_result['calendaruser'].':'.$_SESSION['os_secret'][$_result['secretname']]['secret']);
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

//supports only summary, dtstart and dtend at the moment; want to make it more flexible?; implement at least description...
function _generateVCALENDAR(string $_created, string $_lastmodified, string $_uid, string $_summary, string $_dtstart, string $_dtend)
{
$_body = "BEGIN:VCALENDAR
VERSION:2.0
CALSCALE:GREGORIAN
PRODID:-//codecivil//openStat-".$GLOBALS['versionnumber']."//DE
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

?>
