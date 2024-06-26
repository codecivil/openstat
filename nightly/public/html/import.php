<?php 
//error_reporting(0); //just in case the webserver does not comply...
//start session
session_start();

if ( ! isset($_SESSION['os_user']) ) { header('Location:/login.php'); } //redirect to login page if not logged in

//load classes, functions and constants

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
require_once('../../core/scripts/getParameters.php');

/*
require_once('../../core/auth.php');
require_once('../../core/edit.php');
require_once('../../core/db_functions.php');
require_once('../../core/frontend_functions.php');
require_once('../../core/getParameters.php');
*/

if ( isset($PARAMETER['submit']) AND $PARAMETER['submit'] == 'logout' ) { logout(); }
if ( ! isset($PARAMETER['table']) ) { $PARAMETER['table'] = 'os_all'; };
$table = $PARAMETER['table'];

$username = $_SESSION['os_rolename'];
$password = $_SESSION['os_dbpwd'];

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname) or die ("Connection failed.");
mysqli_set_charset($conn,"utf8");

foreach($_GET as $key=>$value)
{
	if ( $value != 'none' ) {
		$PARAMETER[$key] = $value;
	}
}

foreach($_POST as $key=>$value)
{
	if ( $value != 'none' AND $value != '' ) {
		$PARAMETER[$key] = $value;
	}
	if ( $key == "pwdhash" AND in_array($_POST['dbAction'],array("delete","insert")) ) {
		switch($PARAMETER['table']) {
			case 'os_users':
				$PARAMETER['key'] = $PARAMETER['pwdhash'];
				$PARAMETER['pwdhash'] = sodium_crypto_pwhash_str($PARAMETER['key'],SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
				break;
			case 'os_roles':
				$PARAMETER['key'] = $PARAMETER['pwdhash'];
				$PARAMETER['pwdhash'] = sodium_crypto_pwhash_str($PARAMETER['key'],SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
//				unset($PARAMETER['pwdhash']);
				break;
		}
	}
}

if ( ! isset($PARAMETER['dbAction']) ) { $PARAMETER['dbAction'] = ''; };
if ( ! isset($PARAMETER['table']) ) { $PARAMETER['table'] = ''; };
?>


<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="de-DE">
<head profile="http://gmpg.org/xfn/11">
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta http-equiv="Content-Script-Type" content="text/javascript"/>
		<meta name="language" content="de-DE" />
		<meta name="content-language" content="de-DE" />
		<meta http-equiv="imagetoolbar" content="no" />
		<link rel="stylesheet" type="text/css" media="screen, projection" href="../css/os.css" />
		<link rel="stylesheet" type="text/css" media="screen, projection" href="../css/config_colors_dark.css" />
		<link rel="stylesheet" type="text/css" media="screen, projection, print" href="/plugins/fontawesome/css/all.css" />
		<script type="text/javascript" src="/js/main.js"></script>
		<script type="text/javascript" src="/js/os.js"></script>
		<script type="text/javascript" src="/js/os_create.js"></script>
		<script type="text/javascript" src="/js/import.js"></script>
</head>
<body>
	<div id="headers" hidden>
	<?php
		$_config = getConfig($conn);
		//generate here a json of table headers...
		// $headers = array();
		// $headers[$i]["table"], $headers[$i]["header"], $headers[$i]["type"] (reverse indexes)
		$key_array = array();
		$key_array['keymachine'] = array();
		$key_array['keyreadable'] = array();
		$key_array['edittype'] = array();
		$key_array['table'] = array();
		foreach ( $_config["table"] as $_table ) 
		{
			unset($_stmt_array); $_stmt_array = array();
			$_stmt_array['stmt'] = "SELECT keymachine,keyreadable,edittype FROM ".$_table."_permissions";
			$_result_array = execute_stmt($_stmt_array,$conn); 
			if ($_result_array['dbMessageGood']) { $key_array_add = $_result_array['result']; };
			$key_array['keymachine'] = array_merge($key_array['keymachine'], $key_array_add['keymachine']);
			$key_array['keyreadable'] = array_merge($key_array['keyreadable'], $key_array_add['keyreadable']);
			$key_array['edittype'] = array_merge($key_array['edittype'], $key_array_add['edittype']);
			foreach ($key_array['keymachine'] as $key)
			{
				$key_array['table'][] = $_table;
			}
		}
		$headers = $key_array['keyreadable'];
		echo(json_encode($key_array));
	?>
	</div>
	<div id="fileselection">
		<h1>Wähle zu importierende CSV-Dateien</h1>
		<form>
			<input type="file" multiple id="importFile" accept=".csv,application/csv,text/csv" onchange="matchHeaders(this.files,'headermatch')">
		</form>
	</div>
	<div id="headermatch" hidden>
		<h1>Vorgeschlagene Zuordnung</h1>
        <form id="formShowIdenticalMatches">
            <input type="checkbox" id="notShowIdenticalMatches" checked>
            <label>Nur abweichende Zuordnungen anzeigen</label>
        </form>
		<form id="formHeaderMatch" onsubmit="checkHeaders(this,'importnow'); return false;">
			<div>
				<label><b>Datei</b></label>
				<label><b>Datenbank</b></label>
			</div>
			<br />
			<br />
			<div class="singlematch" hidden>
				<label></label>
				<select>
						<option value="-1" selected>*keine Zuordnung*</option>
					<?php foreach ( $headers as $index=>$header ) 
						{ ?>
							<option value="<?php echo($index); ?>"><?php echo(explode(': ',$header)[0]); ?></option>
						<?php } ?>
				</select>
				<br />
				<br />
			</div>
			<input type="submit" value="Header zuordnen">
		</form>
	</div>
	<div id="importnow" hidden>
		<h1>Importiere</h1>
		<form id="formImport" onsubmit="import()">
			<input id="submitImport" type="submit" value="Importieren" disabled>		
		</form>
	</div>
	
<?php $conn->close(); ?>
