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
	$_return = array('status' => 'OK', 'log' => array(), 'js' => '', 'message' => array('ok' => 'true', 'text' => '')); //log and js may be objects; have to be returned by any field function
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
		$_return['message']['text'] = "e-Mail an ".$_config['To']." wurde erfolgreich gesendet."; 
	} else {
		$_return['status'] = "Fehler";
		$_return['log']['error'] = "e-Mail konnte nicht versendet werden.";
        $_return['message']['ok'] = "false";
		$_return['message']['text'] = "e-Mail konnte nicht gesendet werden.";
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

//create Open Document files from templates covering different scenarios
function createFromTemplate(array $_config,array $trigger,array $PARAM,mysqli $conn) {
	//this must be done at the very beginning of EVERY field function
	//$_config contains the used function config after replacing placeholders by actual values
	$_return = array('status' => 'OK', 'log' => array(), 'js' => '', 'message' => array('ok' => 'true', 'text' => '')); //log and js may be objects; have to be returned by any field function
    /* _config structure
     * 
     * {
     *  <namemachine>:
     *      {
     *          "namereadable": <string>,
     *          "description": <string>,
     *          "src": <filename/path with suffix>, !rewuired,
     *          "filename"; <string, placeholders...>,
     *          "vars": {
     *              <key used in odf without %%, only A-Z,0-9 allowed>: <string or placeholder, if statements...>
     *           }
     *      }, ...
     * }
     */
    $workdirs = ['../../core/templates/','../../vendor/templates/']; //later entries win over former ones
    
    //throw error if src is not set
    if ( ! isset($_config['src']) ) { $_return['log']['error'] .= 'Templatequelldatei ist nicht gesetzt. '; $_return['status'] = "Fehler"; } 
    
    //remove trailing spaces
    $_config['src'] = preg_replace('/^[ ]*/','',preg_replace('/[ ]*$/','',$_config['src']));
    $_return['log']['error'] .= $_config['src'].'; ';
    $filetype = preg_replace('/.*\./','',$_config['src']);
    $mimetype = array(
        "ods" => "application/vnd.oasis.opendocument.spreadsheet",
        "odt" => "application/vnd.oasis.opendocument.text",
        "odp" => "application/vnd.oasis.opendocument.presentation"
    );
    
    //throw error for unsupported file type
    if ( ! isset($mimetype[$filetype]) ) { $_return['log']['error'] .= 'Der Dateityp der Templatequelldatei wird nicht unterstützt. '; $_return['status'] = "Fehler"; }
 
    //find correct workdir
    $workdir = '';
    foreach( $workdirs as $potential_workdir ) {
        if ( file_exists($potential_workdir.'/'.$_config['src']) ) {
            $workdir = $potential_workdir;
        }
    }
    
    if ( $workdir == '' ) {  $_return['log']['error'] .= "Templatequelldatei ".$_config['src']."existiert nicht.";  $_return['status'] = "Fehler"; };
    
    $zip = new ZipArchive;
    $filesToModify = array('content.xml','styles.xml');
    $now = date('Y-m-d_His');
    //throw error if src does not exist or file(-system ) permissions are wrong
    try { copy($workdir.$_config['src'],'/tmp/'.$_config['src'].'.'.$now); } catch (Throwable $e) { $_return['log']['error'] .= "Templatequelldatei existiert nicht oder /tmp ist nicht beschreibbar: " . $e->getMessage() . PHP_EOL;  $_return['status'] = "Fehler"; }
    
    //return if an error has occured
    if ( $_return['status'] == "Fehler ") {
        $_return['js'] = $_return['log']['error']; 
        $_return['message']['ok'] = "false";
        $_return['message']['text'] = $_return['log']['error']; 
        return $_return;
    }
    
    if ($zip->open('/tmp/'.$_config['src'].'.'.$now) === TRUE) {
        foreach( $filesToModify as $fileToModify ) {
            //get template content
            $contentxml = $zip->getFromName($fileToModify);

            //replace placeholders in ODF
            if ( isset($_config['vars']) ) {
                preg_match_all("/%([A-Z0-9_]*)%/",$contentxml,$placeholders);
                $keys = $placeholders[1]; //takes the first bracket of the match
                foreach( $keys as $key ) {
                    if ( isset($_config['vars'][$key]) ) {
                        $contentxml = str_replace('%'.$key.'%',$_config['vars'][$key],$contentxml);
                    }
                }
            }

            //Delete the old...
            $zip->deleteName($fileToModify);
            //Write the new...
            $zip->addFromString($fileToModify, $contentxml);
        }
        //And write back to the filesystem.
        $zip->close();
        $export_file = fopen('/tmp/'.$_config['src'].'.'.$now,'r');
        $export_odf = base64_encode(fread($export_file,filesize('/tmp/'.$_config['src'].'.'.$now)));
        fclose($export_file);
        unlink('/tmp/'.$_config['src'].'.'.$now);
        
        //determine new file name
        // // take basname of src if not given
        if ( ! isset($_config['filename']) ) { $_config['filename'] = preg_replace('/.*\//','',$_config['src']); }
        $filenameparts = explode('.',$_config['filename'],2);
        //alway add timestamp
        $filename = $filenameparts[0].'-'.$now.'.'.$filenameparts[1];
        $_js = array( "data" => "data:".$mimetype[$filetype].";charset=utf-8;base64,".$export_odf, "filename" => $filename, "test" => $_config['vars'] ); 
        $_return['js'] = json_encode($_js,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $_return['message']['ok'] = "true";
        $_return['message']['text'] = "Ein ODF wurde erzeugt und im Downloadordner gespeichert."; 
    } else {
		$_return['log']['error'] .= 'Beim Erzeugen des ODF ist ein Fehler aufgetreten '; $_return['status'] = "Fehler";
        $_return['message']['ok'] = "false";
        $_return['message']['text'] = $_return['log']['error']; 
    }
    return $_return;
}

//create entry in another table based on given entry
function createSubsequentEntry(array $_config,array $trigger,array $PARAM,mysqli $conn) {
	//this must be done at the very beginning of EVERY field function
	//$_config contains the used function config after replacing placeholders by actual values
	$_return = array('status' => 'OK', 'log' => array(), 'js' => '', 'message' => array('ok' => 'true', 'text' => '')); //log and js may be objects; have to be returned by any field function
    /* _config structure
     * 
     * {
     *  <namemachine>:
     *      {
     *          "namereadable": <string>,
     *          "description": <string>,
     *          "srcTable": <string> tablemachine of given entry, 
     *          "trgt": [
     *              { "tablemachine": tablemachine of table where new entry is to be created,
     *                 "keys": {
     *                      <key of tablemachine>: <string>, ...
     *                  }
     *              }
     *          ]
     *      }, 
     *   ...
     * }
     */
    // return if table is wrong
    if ( $_config['srcTable'] != $PARAM['table'] ) { return $_return; }
    //
    // from here on $_config['srcTable'] == $PARAM['table'] !!!
    foreach ( $_config['trgt'] as $_singleconf ) {
        //mass editing is cared for in FUNCIONAction, here we always have a single id value (in an array)
        $_id = json_decode($PARAM["id_".$PARAM['table']],true)[0];
        //
        //attribute to src entry if tables are different
        $_PARAMETER = array();
        if ( $_singleconf['tablemachine'] != $_config['srcTable'] ) {
            $_PARAMETER = array($_singleconf['tablemachine']."__id_".$PARAM['table'] => $_id);
        }
        //
        foreach( $_singleconf['keys'] as $key => $value ) {
            $_PARAMETER[$_singleconf['tablemachine'].'__'.$key] = $value;
            //handle dates and datetimes heuristically
            $_date = date_parse($value);
            if ( $_date['error_count'] === 0 ) {
                $_PARAMETER[$_singleconf['tablemachine'].'__'.$key] = $_date['year'].'-'.$_date['month'].'-'.$_date['day'].' '.$_date['hour'].':'.$_date['minute'].':'.$_date['second'];
            }
        }
        $_PARAMETER['dbAction'] = "insertIfNotExists";
        $_PARAMETER['table'] = $_singleconf['tablemachine'];
        $_result = dbAction($_PARAMETER,$conn);
        if ( strpos($_result,' false') > 0 ) {
            $_return['status'] = 'Fehler';
            $_return['log'] .= 'Beim Erzeugen des Eintrags in '.$_SESSION['tablenames'][$_PARAMETER['table']].' ist ein Fehler aufgetreten. ';
            $_return['message']['ok'] = 'false';
            $_return['message']['text'] .= 'Beim Erzeugen des Eintrags in '.$_SESSION['tablenames'][$_PARAMETER['table']].' ist ein Fehler aufgetreten. ';
        } else {
            //case: entry is generated
            if ( preg_match('/^<[^>]*>{"id_'.$_PARAMETER['table'].'":[\d]*}<[^>]*>$/',$_result) == 0) {
                $_id = -1;
                foreach ( $_SESSION['insert_id'] as $insertedidstring ) {
                    if ( preg_match('/id_'.$_PARAMETER['table'].'/', $insertedidstring) == 1 ) {
                        $_id = json_decode($insertedidstring,true)['id_'.$_PARAMETER['table']];
                    }
                }
                if ( $_id > 0 ) {
                    $_return['message']['text'] .= 'Ein <a onclick="document.querySelector(\'#detailsForm'.$_PARAMETER['table'].$_id.'\').onsubmit()">neuer Eintrag</a> wurde in '.$_SESSION['tablenames'][$_PARAMETER['table']].' erzeugt. ';
                } else {
                    $_return['message']['text'] .= json_encode($_SESSION['insert_id']).'Ein neuer Eintrag wurde in '.$_SESSION['tablenames'][$_PARAMETER['table']].' erzeugt. ';
                }
            //case: entry exists
            } else {
                $_id = json_decode(preg_replace('/<[^>]*>/','',$_result),true)['id_'.$_PARAMETER['table']];
                $_return['message']['text'] .= 'Ein <a onclick="document.querySelector(\'#detailsForm'.$_PARAMETER['table'].$_id.'\').onsubmit()">zugehöriger Eintrag</a> in '.$_SESSION['tablenames'][$_PARAMETER['table']].' existiert bereits. ';
            }
        }
        $_return['js'] .= $_result;
    }
    return $_return;
}
?>
