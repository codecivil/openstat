<?php
//error_reporting(0); //just in case the webserver does not comply...

session_start();
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
require_once('../../core/scripts/getParameters.php');

$username = $_SESSION['os_rolename'];
$password = $_SESSION['os_dbpwd'];

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname) or die ("Connection failed.");
mysqli_set_charset($conn,"utf8");

//was too unstable:
//$call_function = $_SERVER['HTTP_X_FUNCTION_CALL'];
$call_function = $PARAMETER['X_FUNCTION_CALL']; unset($PARAMETER['X_FUNCTION_CALL']);
//this has been handled inside dbAction: FILE_Action is called there and $PARAMETER sanitised...
//do not care about files unless callfunction = FILE_...
if ( strpos($call_function,'FILE_') != 0 AND $call_function != 'dbAction' ) { unset($PARAMETER['FILES']); }
$return = $call_function($PARAMETER,$conn);
$conn->close();
echo($return);
return $return;
?>
