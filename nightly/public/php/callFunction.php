<?php
//error_reporting(0); //just in case the webserver does not comply...

session_start();
mysqli_report(MYSQLI_REPORT_STRICT);

if ( ! isset($_SESSION['os_user']) ) { /*header('Location:/login.php');*/ echo("LOGGEDOUT"); return "LOGGEDOUT"; } //redirect to login page if not logged in; handle in callFunction (main.js) in order to redirect whole page, not parts of it!

//include system classes
$core = glob('../../core/classes/*.php');

foreach ( $core as $component )
{
	require_once($component);
}

//include system functions
$core = glob('../../core/functions/*.php');

foreach ( $core as $component )
{
	require_once($component);
}

//include vendor functions
$core = glob('../../vendor/functions/*.php');

foreach ( $core as $component )
{
	require_once($component);
}

//include some system scripts and data
require_once('../../core/data/serverdata.php');
require_once('../../core/data/filedata.php');
require_once('../../core/data/debugdata.php');
require_once('../../core/scripts/getParameters.php');

$username = $_SESSION['os_rolename'];
$password = $_SESSION['os_dbpwd'];

$return = array('dbMessageGood'=>'false');
$tries = 0; $_maxtries = 20;
$call_function = $PARAMETER['X_FUNCTION_CALL']; unset($PARAMETER['X_FUNCTION_CALL']);

while ( $tries < $_maxtries AND ( ( isset($return['dbMessageGood']) AND $return['dbMessageGood'] == "false" ) OR ( is_string($return) AND strpos($return,'class="dbMessage false"') != false ) ) ) {
	try { 
		// Create connection
		if ( $username === '' OR $password === '') {
			$conn = null;
		} else { 
			$conn = new mysqli($servername, $username, $password, $dbname);
			mysqli_set_charset($conn,"utf8");
		}

		//was too unstable:
		//$call_function = $_SERVER['HTTP_X_FUNCTION_CALL'];
		//see above: $call_function = $PARAMETER['X_FUNCTION_CALL']; 
		//this has been handled inside dbAction: FILE_Action is called there and $PARAMETER sanitised...
		//do not care about files unless callfunction = FILE_...
		if ( strpos($call_function,'FILE_') != 0 AND $call_function != 'dbAction' ) { unset($PARAMETER['FILES']); }
		$return = $call_function($PARAMETER,$conn);
		unset($_connerror);
		$conn->close();
	} catch(Exception $e) {
		$return = array();	
		$return['dbMessageGood'] = "false";
		$return['dbMessage'] = "Interner Datenbankfehler. Bitte versuche es erneut.";
		$_connerror = '<div class="dbMessage '.$return['dbMessageGood'].'">'.$return['dbMessage'].'</div>';
	}
	if ( $tries > 0 ) { sleep(2); }
	$tries++;
}
if ( isset($_connerror) ) { $return = $_connerror; }
echo($return);
return $return;
?>
