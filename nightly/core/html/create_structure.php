<?php 
// adapt for openStat-extension
session_start();
if ( ! isset($_SESSION['user']) ) { header('Location:/html/admin.php'); exit(); } //redirect to login page if not logged in

require_once('../../core/classes/auth.php');
require_once('../../core/functions/db_functions.php');
require_once('../../core/functions/frontend_functions.php');
require_once('../../core/functions/display_functions.php');

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
</head>
<body>
<?php 
//$ENCRYPTED = '';
$ENCRYPTED = ' ENCRYPTED=YES'; //NO for debug only
$PARAMETER = array(); 
$action = '';
$_warning = '';

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
	if ( $key == "pwdhash" AND in_array($_POST['dbAction'],array("delete","insert","edit")) ) {
		switch($PARAMETER['table']) {
			case 'os_secrets':
				//allowed_roles and allowed_users must be parsed before pwdhash; is this deterministically true?
				$PARAMETER['rolepwds'] = $PARAMETER['allowed_roles'];
				$PARAMETER['allowed_roles'] = json_encode(array_keys(json_decode($PARAMETER['allowed_roles'],true)));
				$PARAMETER['userrolepwds'] = $PARAMETER['allowed_users'];				
				$PARAMETER['allowed_users'] = json_encode(array_keys(json_decode($PARAMETER['allowed_users'],true)));
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

<?php /* Add PARAMETERs to current_keys and create permalink*/
$permalink = "localhost/?";
$current_keys = "";
$filtered = 1;

/* edit types */
$edittype = array();
$edittype['list'] = array('typelist','referencetag','edittype','defaultconfig','allowed_roles','functionscope','functionclasses');
$edittype['id'] = array('id');
/**/

foreach($PARAMETER as $key=>$value)
{
	$current_keys .= ",`".$key."`";
	$permalink .= "&" . urlencode($key) . "=" . urlencode($value);
}
?>

<?php
$servername = $_SESSION['server'];
$username = $_SESSION['user'];
$dbname = $_SESSION['database']; 
/* test database
$username = "d02839e2";
$dbname = "d02839e2"; */
$password = $_SESSION['password'];

// Create connection
try {
	$conn = new mysqli($servername, $username, $password, $dbname); 
} catch(Exception $e) { 
	exit;
}
mysqli_set_charset($conn,"utf8");

//collect most important info
unset($_stmt_array); $_stmt_array = array();
$_stmt_array['stmt'] = "SELECT id,tablemachine,allowed_roles,parentmachine FROM `os_tables`";
$_tables_array = execute_stmt($_stmt_array,$conn)['result'];
$_TABLES = $_tables_array['tablemachine'];
$_TABLES_ID = $_tables_array['id'];
$_TABLES_ALLOW = $_tables_array['allowed_roles'];
$_TABLES_ARRAY = array_combine($_TABLES_ID,$_TABLES);
$_TABLES_ALLOW_ARRAY = array_combine($_TABLES_ID,$_TABLES_ALLOW);
$_TABLES_PARENTMACHINE_ARRAY = array_combine($_TABLES_ID,$_tables_array['parentmachine']);

unset($_stmt_array); $_stmt_array = array();
$_stmt_array['stmt'] = "SELECT id,rolename,parentid FROM `os_roles`";
$_roles_array = execute_stmt($_stmt_array,$conn)['result'];
$_ROLES = $_roles_array['id'];
$_PARENTS = $_roles_array['parentid'];
$_ROLES_NAME = $_roles_array['rolename'];
$_ROLES_ARRAY = array_combine($_ROLES,$_ROLES_NAME);
$_PARENTS_ARRAY = array_combine($_ROLES,$_PARENTS);

//collect sqlimport log
unset($_stmt_array); $_stmt_array = array();
$_stmt_array['stmt'] = "SELECT * FROM osadm_sqlimport";
$osadm_result = execute_stmt($_stmt_array,$conn)['result']; 

//recreate views button
if ( isset($PARAMETER['recreateViews']) ) {
	//recreate views
	unset($_table);
	foreach ( $_TABLES as $_table ) {
		recreateView($_table,$conn);
	}					
}

//logButton
$_save = false;
if ( isset($PARAMETER['logActivity']) ) {
	if ( isset($_SESSION['log']) AND $_SESSION['log'] ) {
		$_SESSION['log'] = false;
		$_save = true;
		$_SESSION['logfinishedtimereadable'] = date('d.m.Y H:i:s');
		$_SESSION['logfinishedtime'] = date('Y-m-d_His');
		$_SESSION['logsaved'] = $_SESSION['logstring']."-- openStatAdmin-log finished ".$_SESSION['logfinishedtimereadable'].PHP_EOL;
		$_SESSION['logstring'] = "";
	} else { 
		$_SESSION['log'] = true;
		$_SESSION['logstring'] = "-- openStatAdmin-log started ".date('d.m.Y H:i:s').PHP_EOL;
	}
	unset($PARAMETER['logActivity']);
}

function readable(string $_string) {
	$_translate = array(
		"parentid" => "Eltern-ID",
		"rolename" => "Rollenbezeichnung",
		"pwdhash" => "Passwort",
		"defaultconfig" => "Standardkonfiguration",
		"username" => "Benutzername",
		"roleid" => "Rollen-ID",
		"rolepwd" => "Rollenpasswort",
		"iconname" => "Name des Icons",
		"tablemachine" => "interner Tabellenname",
		"tablereadable" => "angezeigter Tabellenname",
		"allowed_roles" => "berechtigte Rollen",
		"allowd_users" => "berechtigte Nutzer",
		"delete_roles" => "Rollen mit Löschberechtigung",
		"displayforeign"=> "Anzeigen aus anderen Tabellen",
		"functionmachine" => "interner Funktionsname",
		"functionreadable" => "angezeigter Funktionsname",
		"functionscope" => "Anwendungsfeld",
		"functionclasses" => "CSS Klassen",
		"functiontarget" => "Zielbereich der Funktion",
		"functionconfig" => "Konfiguration der Funktion",
		"functionflags" => "Verhalten der Funktion",
		"keymachine" => "interner Feldname",
		"keyreadable" => "angezeigter Feldname",
		"realid" => "Reihenfolge",
		"typelist" => "Datentyp",
		"edittype" => "Datenmodell",
		"defaultvalue" => "Standardwert",
		"referencetag" => "Referenz",
		"depends_on_key" => "Feldbedingung",
		"depends_on_value" => "Wertbedingung",
		"allowed_values" => "mögliche Werte",
		"tietotables" => "Binde an Tabellen",
		"subtablemachine" => "Untertabelle",
		"parentmachine" => "Elterntabelle",
		"calendarfields" => "Kalenderfelder",
		"secretreadable" => "lesbarer Geheimnisname",
		"secretmachine" => "interner Geheimnisname",
		"pwdhash" => "Geheimnis (Passwort)",
		"identifiers" => "Identifikatoren"
	);
	if ( isset($_translate[$_string]) ) { return $_translate[$_string]; } else { return $_string; }
}

function collectInfo(mysqli $conn) {
	global $_TABLES, $_TABLES_ARRAY, $_TABLES_ALLOW_ARRAY, $_ROLES, $_ROLES_ARRAY, $_PARENTS, $_PARENTS_ARRAY;
	//collect most important info
	unset($_stmt_array); $_stmt_array = array();
	$_stmt_array['stmt'] = "SELECT id,tablemachine,allowed_roles FROM `os_tables`";
	$_tables_array = execute_stmt($_stmt_array,$conn)['result'];
	$_TABLES = $_tables_array['tablemachine'];
	$_TABLES_ID = $_tables_array['id'];
	$_TABLES_ALLOW = $_tables_array['allowed_roles'];
	$_TABLES_ARRAY = array_combine($_TABLES_ID,$_TABLES);
	$_TABLES_ALLOW_ARRAY = array_combine($_TABLES_ID,$_TABLES_ALLOW);

	unset($_stmt_array); $_stmt_array = array();
	$_stmt_array['stmt'] = "SELECT id,rolename,parentid FROM `os_roles`";
	$_roles_array = execute_stmt($_stmt_array,$conn)['result'];
	$_ROLES = $_roles_array['id'];
	$_PARENTS = $_roles_array['parentid'];
	$_ROLES_NAME = $_roles_array['rolename'];
	$_ROLES_ARRAY = array_combine($_ROLES,$_ROLES_NAME);
	$_PARENTS_ARRAY = array_combine($_ROLES,$_PARENTS);
}

function _adminActionBefore(array $PARAMETER, mysqli $conn) {
	global $_TABLES, $_TABLES_ARRAY, $_TABLES_ALLOW_ARRAY, $_TABLES_PARENTMACHINE_ARRAY, $_ROLES, $_ROLES_ARRAY, $_PARENTS, $_PARENTS_ARRAY, $ENCRYPTED, $_warning; //$PARAMETER['pwdhash'] may be changed...
	$_stmt_array = array();
	$_return = array();
	switch($PARAMETER['table']) {
		case 'os_secrets':
			global $PARAMETER; //allows to change the $PARAMETER for upcoming functions
			$_warning = "Geben Sie in allowed_roles und allowed_users die jeweiligen IDs zusammen mit den Rollenpasswörtern im JSON Format an.<br />Änderungen des internen Geheimninsnamens sind derzeit nicht möglich; stattdessen wird ein zusätzlicher Eintrag angelegt.";
			//save secret encrypted with role sql passwd in os_passwords with user = userid(role)
			switch($PARAMETER['dbAction']) {
				case 'edit':
				case 'delete':
				case 'insert':
					//construct the array of roles:password pairs
					$_secretroles = json_decode($PARAMETER['rolepwds'],true); // 'roleid': 'rolepasswd'
					if ( $_secretroles == null ) { $_secretroles = array(); }
					$_secretusers = json_decode($PARAMETER['userrolepwds'],true); // 'userid': 'rolepasswd'
					if ( $_secretusers == null ) { $_secretusers = array(); }
					if ( sizeof($_secretroles) + sizeof($_secretusers) == 0 ) { break; }
					unset($PARAMETER['rolepwds']);
					unset($PARAMETER['userrolepwds']);
					unset($_stmt_array); unset($_result);
					$_stmt_array['stmt'] = "SELECT id,roleid FROM os_users WHERE id IN (".implode(',',array_keys($_secretusers)).")";
					$_secretusers_results = execute_stmt($_stmt_array,$conn)['result'];
					$_secretusers_roles = array_combine($_secretusers_results['id'],$_secretusers_results['roleid']);
					$_secretroles_add = array();
					foreach ( $_secretusers as $_userid => $_secret ) {
						$_secretroles_add[$_secretusers_roles[$_userid]] = $_secret;
					}
					$_secretroles = $_secretroles + $_secretroles_add; //+ is merging with preserving numerical keys!
					//do not allow changes of secret to be recorded in os_secrets if there is no rolepwd
					//test if "passwords" are purely numeric (do not allow purely numerical role passwords!) as they would be if they are not given (role ids would be the values of the array!)
					$_allpwdsaregiven = true;
					foreach ( $_secretroles as $value ) { $_allpwdsaregiven = ( $_allpwdsaregiven AND ! is_numeric($value) ); };
					if ( ! $_allpwdsaregiven ) {
						$PARAMETER['allowed_roles'] = $PARAMETER['rolepwds'];
						$PARAMETER['allowed_users'] = $PARAMETER['userrolepwds'];
						unset($PARAMETER['pwdhash']);
						break;
					};
					//
					unset($_secretusers); unset($_secretusers_results); unset($_secretusers_roles); unset($_secretroles_add);
					//collect encrypted db info of roles
					unset($_stmt_array);
					$_stmt_array['stmt'] = "SELECT userid,roleid,password,nonce,salt FROM os_roles LEFT JOIN ( os_users LEFT JOIN os_passwords AS T2 ON ( os_users.id = userid ) ) ON ( os_roles.id = roleid AND rolename = username) WHERE roleid in (".implode(",",array_keys($_secretroles)).") AND ( secretname IS NULL OR secretname = '' OR secretname LIKE 'role_%' )";
					$_result_array = execute_stmt($_stmt_array,$conn,true); $_result=$_result_array['result'];
					if ( isset($_result) ) {
						foreach ( $_result as $index=>$row ) {
							foreach ($row as $key=>$value) {
								if ( in_array($key,array('password','nonce','salt')) ) {
									$_result[$index][$key] = sodium_hex2bin($value);
								}
							}
						}
					} else { $_stmt_array['error'] = "Kein Rolleneintrag gefunden. "; break; }
					foreach ( $_result as $_result_user ) {
						//encrypt secret by dbpwd
						$_result_user['key'] = sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES,$_secretroles[$_result_user['roleid']],$_result_user['salt'],SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
						$dbpwd = sodium_crypto_secretbox_open($_result_user['password'],$_result_user['nonce'],$_result_user['key']);
						if (! $dbpwd ) { $_stmt_array['error'] = "Rollenpasswort für Rolle ".$_result_user['roleid']." ist falsch."; break; }
						$salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
						$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
						$PARAMETER['genkey'] = sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES,$dbpwd,$salt,SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
						unset($dbpwd);
						$passwd = sodium_crypto_secretbox($PARAMETER['key'],$nonce,$PARAMETER['genkey']);
						unset($PARAMETER['key']);
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "DELETE FROM os_passwords WHERE userid=? AND secretname = ?";
						$_stmt_array['str_types'] = "is";
						$_stmt_array['arr_values'] = array();
						$_stmt_array['arr_values'][] = $_result_user['userid'];
						$_stmt_array['arr_values'][] = $PARAMETER['secretmachine'];
						_execute_stmt($_stmt_array,$conn);
						if ( $PARAMETER['dbAction'] != "delete" ) {
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "INSERT INTO os_passwords (password,salt,nonce,userid,secretname) VALUES (?,?,?,?,?)";
							$_stmt_array['str_types'] = "sssis";
							$_stmt_array['arr_values'] = array();
							$_stmt_array['arr_values'][] = sodium_bin2hex($passwd);
							$_stmt_array['arr_values'][] = sodium_bin2hex($salt);
							$_stmt_array['arr_values'][] = sodium_bin2hex($nonce);
							$_stmt_array['arr_values'][] = $_result_user['userid'];
							$_stmt_array['arr_values'][] = $PARAMETER['secretmachine'];
//							_execute_stmt($_stmt_array,$conn);
						}
					}
					unset($_secretroles);
					break;
			}
			break;
		case 'os_users':
			switch($PARAMETER['dbAction']) {
				case 'edit':
				//do not allow password change to be recorded in os_users if there is no rolepwd
					global $PARAMETER;
					if ( ! isset($PARAMETER['rolepwd']) OR $PARAMETER['rolepwd'] == '' ) { unset($PARAMETER['pwdhash']); };
					break;
			 }
			 break;
		case 'os_roles':
			$_warning = "Bei Wechsel des Passworts werden alle bisherigen Benutzer mit dieser Rolle gelöscht!";
			switch($PARAMETER['dbAction']) {
				case 'insert':
					//create new user of database ('role'), generate password and save it in os_passwords encrypted with the given pwd; after this, create new user
					//of same name and password
					$db_pwd = sodium_bin2hex(random_bytes(32));
					$salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
					$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
					$PARAMETER['genkey'] = sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES,$PARAMETER['key'],$salt,SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
					$passwd = sodium_crypto_secretbox($db_pwd,$nonce,$PARAMETER['genkey']);
					$_stmt_array['stmt'] = "INSERT INTO os_passwords (password,salt,nonce) values (?,?,?)";
					$_stmt_array['str_types'] = "sss";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = sodium_bin2hex($passwd);
					$_stmt_array['arr_values'][] = sodium_bin2hex($salt);
					$_stmt_array['arr_values'][] = sodium_bin2hex($nonce);
					_execute_stmt($_stmt_array,$conn);
					$PARAMETER['rolename'] = substr($PARAMETER['rolename'],0,16);
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "CREATE USER IF NOT EXISTS ".$PARAMETER['rolename']." IDENTIFIED BY '".$db_pwd."';";
					_execute_stmt($_stmt_array,$conn); 
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "REVOKE ALL PRIVILEGES, GRANT OPTION FROM ".$PARAMETER['rolename'];
					_execute_stmt($_stmt_array,$conn); 
					//grant permissions on os_-TABLES
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "GRANT SELECT, UPDATE, INSERT, DELETE ON os_userconfig TO ".$PARAMETER['rolename'].";";
					_execute_stmt($_stmt_array,$conn); 
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "GRANT SELECT, UPDATE, INSERT, DELETE ON os_caldav TO ".$PARAMETER['rolename'].";";
					_execute_stmt($_stmt_array,$conn); 
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "GRANT SELECT ON os_functions TO ".$PARAMETER['rolename'].";";
					_execute_stmt($_stmt_array,$conn); 
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "GRANT SELECT ON os_tables TO ".$PARAMETER['rolename'].";";
					_execute_stmt($_stmt_array,$conn); 
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "FLUSH PRIVILEGES;";
					_execute_stmt($_stmt_array,$conn);  
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "FLUSH LOGS;";
					//_execute_stmt($_stmt_array,$conn);  
					break;
				case 'edit':
					//rolename change
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "SELECT rolename FROM os_roles WHERE id=?";
					$_stmt_array['str_types'] = "i";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = $PARAMETER['id'];
					$oldrolename = execute_stmt($_stmt_array,$conn)['result']['rolename'][0];
					if ( $oldrolename != $PARAMETER['rolename'] ) {
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "RENAME USER '".$oldrolename."' TO '".$PARAMETER['rolename']."'";
						_execute_stmt($_stmt_array,$conn);						
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "UPDATE os_users SET username=? WHERE username=?";
						$_stmt_array['str_types'] = "ss";
						$_stmt_array['arr_values'] = array();
						$_stmt_array['arr_values'][] = $PARAMETER['rolename'];
						$_stmt_array['arr_values'][] = $oldrolename;
						_execute_stmt($_stmt_array,$conn); 
					} 
					//password change
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "SELECT pwdhash FROM os_roles WHERE id=?";
					$_stmt_array['str_types'] = "i";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = $PARAMETER['id'];
					$oldpwdhash = execute_stmt($_stmt_array,$conn)['result']['pwdhash'][0];
					//test if password has changed: do nothing of plaintext password is the same or if the hash presented has not been touched!
					if ( $oldpwdhash != $PARAMETER['pwdhash'] AND $oldpwdhash != $PARAMETER['key'] ) {
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "UPDATE os_users SET pwdhash=? WHERE userid in (SELECT id FROM os_users WHERE username = '".$PARAMETER['rolename']."')";
						$_stmt_array['str_types'] = "s";
						$_stmt_array['arr_values'] = array();
						$_stmt_array['arr_values'][] = $PARAMETER['pwdhash'];
						_execute_stmt($_stmt_array,$conn);
						$db_pwd = sodium_bin2hex(random_bytes(32)); //
						$salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
						$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
						$PARAMETER['genkey'] = sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES,$PARAMETER['key'],$salt,SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
						$passwd = sodium_crypto_secretbox($db_pwd,$nonce,$PARAMETER['genkey']);
						$_stmt_array['stmt'] = "UPDATE os_passwords SET password = ?, salt = ?, nonce = ? WHERE userid in (SELECT id FROM os_users WHERE username = '".$PARAMETER['rolename']."')";
						$_stmt_array['str_types'] = "sss";
						$_stmt_array['arr_values'] = array();
						$_stmt_array['arr_values'][] = sodium_bin2hex($passwd);
						$_stmt_array['arr_values'][] = sodium_bin2hex($salt);
						$_stmt_array['arr_values'][] = sodium_bin2hex($nonce);
						_execute_stmt($_stmt_array,$conn);
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "SET PASSWORD FOR ".$PARAMETER['rolename']." = PASSWORD('".$db_pwd."');";
						_execute_stmt($_stmt_array,$conn); 
						unset($_stmt_array);
						$_stmt_array['stmt'] = "SELECT id FROM os_users WHERE roleid = ? AND username != ?";
						$_stmt_array['str_types'] = "is";
						$_stmt_array['arr_values'] = array();
						$_stmt_array['arr_values'][] = $PARAMETER['id'];
						$_stmt_array['arr_values'][] = $PARAMETER['rolename'];
						$_doomedusers = execute_stmt($_stmt_array,$conn)['result']['id'];
						foreach ( $_doomedusers as $_doomeduser )
						{
							unset($_stmt_array);
							$_stmt_array['stmt'] = "DROP VIEW os_userconfig_".$_doomeduser;
							_execute_stmt($_stmt_array,$conn);
							unset($_stmt_array);
							$_stmt_array['stmt'] = "DELETE FROM os_passwords WHERE userid = ?";
							$_stmt_array['str_types'] = "i";
							$_stmt_array['arr_values'] = array();
							$_stmt_array['arr_values'][] = $_doomeduser;
							_execute_stmt($_stmt_array,$conn);
						}
						unset($_stmt_array);
						//update users? no way! we cannot ask for the password for all users! if rolepwd changes, the users are deleted!
						$_stmt_array['stmt'] = "DELETE FROM os_users WHERE roleid = ? AND username != ?";
						$_stmt_array['str_types'] = "is";
						$_stmt_array['arr_values'] = array();
						$_stmt_array['arr_values'][] = $PARAMETER['id'];
						$_stmt_array['arr_values'][] = $PARAMETER['rolename'];
						_execute_stmt($_stmt_array,$conn);
					} else {
						//do not allow to save pwdhash of pwdhash if it has not changed!
						global $PARAMETER;
						unset($PARAMETER['pwdhash']);
					}
					break;
				case 'delete':
					foreach ( $_TABLES as $_table )
					{
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "ALTER TABLE `".$_table."_permissions` DROP COLUMN `role_".$PARAMETER['id']."`";
						_execute_stmt($_stmt_array,$conn);
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "ALTER TABLE `".$_table."_permissions` DROP COLUMN `restrictrole_".$PARAMETER['id']."`";
						_execute_stmt($_stmt_array,$conn);
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "ALTER TABLE `".$_table."_permissions` DROP COLUMN `restrictrole_".$PARAMETER['id']."`";
						_execute_stmt($_stmt_array,$conn);
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "DROP VIEW `view__".$_table."__".$PARAMETER['id']."`";
						_execute_stmt($_stmt_array,$conn);
					}
					$_stmt_array['stmt'] = "DROP USER ".$PARAMETER['rolename'];
					_execute_stmt($_stmt_array,$conn);
					//recreate views
					unset($_table);
					foreach ( $_TABLES as $_table ) {
						recreateView($_table,$conn);
					}					
					//delete all users with that role consistently
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "SELECT `id` FROM `os_users` WHERE `roleid` = ?;";
					$_stmt_array['str_types'] = "i";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = $PARAMETER['id'];
					$_doomedusers = execute_stmt($_stmt_array,$conn)['result']['id'];
					unset($_doomeduser);
					foreach ( $_doomedusers as $_doomeduser ) {
						$USERPARAMETER = array();
						$USERPARAMETER['id'] = $_doomeduser;
						$USERPARAMETER['table'] = "os_users";
						$USERPARAMETER['dbAction'] = $PARAMETER['dbAction'];
						_adminActionBefore($USERPARAMETER,$conn);
						_dbAction($USERPARAMETER,$conn);
						_adminActionAfter($USERPARAMETER,$conn);
						unset($USERPARAMETER);
					
					}
					/*
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "DELETE FROM `os_users` WHERE `roleid` = ?;";
					$_stmt_array['str_types'] = "i";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = $PARAMETER['id'];
					*/
					break;
			}
			break;
		case 'os_tables':
		//subtable structure todo: case 'edit' and 'delete' here, check what to do in adminActionAfter...  
 			$_warning = "Bitte erzeugen Sie in 'Binde an Tabellen' keine geschlossenen Abhängigkeitsketten!";
			switch($PARAMETER['dbAction']) {
				case 'insert':
					//create proper tables if this is a root table, views if this is a subtable
					if ( ! isset($PARAMETER['parentmachine']) ) {
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "CREATE TABLE `".$PARAMETER['tablemachine']."` (id_".$PARAMETER['tablemachine']." INT NOT NULL AUTO_INCREMENT, changedat TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, changedby INT, code VARCHAR(8) DEFAULT (REPLACE(REPLACE(REPLACE(upper(LEFT(to_base64(UNHEX(sha1(CONCAT(NOW(),RAND())))),8)),'/','1'),'+','2'),'=','3')), PRIMARY KEY (id_".$PARAMETER['tablemachine']."))".$ENCRYPTED.";";
						_execute_stmt($_stmt_array,$conn);
						unset($_table);
						foreach ( $_TABLES as $_table )
						{
							if ( $_table == $PARAMETER['tablemachine'] ) { continue; }
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "ALTER TABLE `".$PARAMETER['tablemachine']."` ADD COLUMN `id_".$_table."` INT DEFAULT NULL;";
							_execute_stmt($_stmt_array,$conn);						
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "ALTER TABLE `".$_table."` ADD COLUMN `id_".$PARAMETER['tablemachine']."` INT DEFAULT NULL;";
							_execute_stmt($_stmt_array,$conn);						
						}
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "CREATE TABLE ".$PARAMETER['tablemachine']."_permissions(id INT NOT NULL AUTO_INCREMENT, keymachine VARCHAR(40), keyreadable VARCHAR(255), subtablemachine VARCHAR(40), realid DECIMAL(6,3), typelist VARCHAR(40), edittype VARCHAR(60), defaultvalue TEXT, referencetag VARCHAR(40), role_0 INT DEFAULT 0, restrictrole_0 TEXT DEFAULT NULL, PRIMARY KEY (id))".$ENCRYPTED.";";
						_execute_stmt($_stmt_array,$conn);
						unset($_role);
						foreach ( $_ROLES as $_role )
						{
							$_stmt_array['stmt'] = "ALTER TABLE ".$PARAMETER['tablemachine']."_permissions ADD COLUMN role_".$_role." INT DEFAULT 0;";
							_execute_stmt($_stmt_array,$conn);						
							$_stmt_array['stmt'] = "ALTER TABLE ".$PARAMETER['tablemachine']."_permissions ADD COLUMN restrictrole_".$_role." TEXT DEFAULT NULL;";
							_execute_stmt($_stmt_array,$conn);						
						}
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "INSERT INTO ".$PARAMETER['tablemachine']."_permissions(keymachine,keyreadable,typelist,edittype,referencetag) values ('changedat','_none_','TIMESTAMP','NONE','_none_')";
						_execute_stmt($_stmt_array,$conn);
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "INSERT INTO ".$PARAMETER['tablemachine']."_permissions(keymachine,keyreadable,typelist,edittype,referencetag) values ('changedby','_none_','VARCHAR(40)','NONE','_none_')";
						_execute_stmt($_stmt_array,$conn);
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "INSERT INTO ".$PARAMETER['tablemachine']."_permissions(keymachine,keyreadable,typelist,edittype,defaultvalue) values ('code','Code','VARCHAR(8)','ID','(REPLACE(REPLACE(REPLACE(upper(LEFT(to_base64(UNHEX(sha1(CONCAT(NOW(),RAND())))),8)),\'/\',\'1\'),\'+\',\'2\'),\'=\',\'3\'))')";
						_execute_stmt($_stmt_array,$conn);
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "UPDATE ".$PARAMETER['tablemachine']."_permissions SET role_0 = 6 WHERE keymachine = 'code';";
						_execute_stmt($_stmt_array,$conn);						
						unset($_role);
						foreach ( $_ROLES as $_role )
						{
							$_stmt_array['stmt'] = "UPDATE ".$PARAMETER['tablemachine']."_permissions SET role_".$_role." = 6 - role_".$_PARENTS_ARRAY[$role]." MOD 8 WHERE keymachine = 'code';";
							_execute_stmt($_stmt_array,$conn);						
						}
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "CREATE TABLE ".$PARAMETER['tablemachine']."_references(id INT NOT NULL AUTO_INCREMENT, referencetag VARCHAR(40), depends_on_key VARCHAR(80), depends_on_value VARCHAR(80), allowed_values TEXT, PRIMARY KEY (id))".$ENCRYPTED.";";
						_execute_stmt($_stmt_array,$conn);
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "INSERT INTO ".$PARAMETER['tablemachine']."_references(referencetag) VALUES ('_none_');";
						_execute_stmt($_stmt_array,$conn);
					} else {
						//add subtable in parent table_permissions
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "ALTER TABLE `".$PARAMETER['parentmachine']."_permissions` ADD COLUMN IF NOT EXISTS `subtablemachine` VARCHAR(40);";
						_execute_stmt($_stmt_array,$conn);						
						//create views for subtables
						// // create view for table
						//problem: this is static and does not change when new fields are added to the subtable, so it is rather empty at "creation"
						//in reality, it is non-existent, so there must be an error in the transaction somewhere
						//indeed: @qry is NULL and hence @qry2 is NULL if there is no subtable field yet...
						//solution for static: include this view in recreateView function
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "SELECT CONCAT('CREATE OR REPLACE ALGORITHM = MERGE VIEW  `".$PARAMETER['tablemachine']."` AS SELECT D.`subtable_".$PARAMETER['tablemachine']."`, T.id_".$PARAMETER['parentmachine'].", T.changedat, T.changedby, T.code";
						foreach ( $_TABLES_ARRAY as $_id_ => $_table ) //this should only loop over base tables!
							{
								if ( $_table == $PARAMETER['parentmachine'] OR $_TABLES_PARENTMACHINE_ARRAY[$_id_] != '' ) { continue; }
								$_stmt_array['stmt'] .= ", T.`id_".$_table."`";
							}
						$_stmt_array['stmt'] .= ",', @qry, ' FROM (SELECT 1 AS `subtable_".$PARAMETER['tablemachine']."`) D CROSS JOIN ".$PARAMETER['parentmachine']." AS T WITH CHECK OPTION') INTO @qry2;";
						$conn->begin_transaction();
						$conn->query("START TRANSACTION;");
						$conn->query("SELECT GROUP_CONCAT(CONCAT('T.',keymachine) ORDER BY realid) INTO @qry FROM ".$PARAMETER['parentmachine']."_permissions WHERE subtablemachine = '".$PARAMETER['tablemachine']."';");
						$conn->query($_stmt_array['stmt']);
						$conn->query("PREPARE stmt FROM @qry2;");
						$conn->query("EXECUTE stmt;");
						$conn->query("COMMIT;");
						$conn->commit();
						// // create view for _permissions
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "CREATE OR REPLACE ALGORITHM = MERGE VIEW  `".$PARAMETER['tablemachine']."_permissions` AS SELECT * FROM ".$PARAMETER['parentmachine']."_permissions WHERE subtablemachine = '".$PARAMETER['tablemachine']."'";
						_execute_stmt($_stmt_array,$conn);						
						// // create view for _references
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "CREATE OR REPLACE ALGORITHM = MERGE VIEW  `".$PARAMETER['tablemachine']."_references` AS SELECT * FROM ".$PARAMETER['parentmachine']."_references WHERE referencetag in (SELECT DISTINCT referencetag FROM `".$PARAMETER['tablemachine']."_permissions`)";
						_execute_stmt($_stmt_array,$conn);						
					}
					foreach ( json_decode($PARAMETER['allowed_roles'],true) as $_role )
					{
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "GRANT SELECT (keymachine,keyreadable,subtablemachine,typelist,edittype,realid,referencetag,role_".$_PARENTS_ARRAY[$_role].",restrictrole_".$_PARENTS_ARRAY[$_role].",role_".$_role.",restrictrole_".$_role.") ON ".$PARAMETER['tablemachine']."_permissions TO ".$_ROLES_ARRAY[$_role].";";
						_execute_stmt($_stmt_array,$conn); 
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "GRANT SELECT ON ".$PARAMETER['tablemachine']."_references TO ".$_ROLES_ARRAY[$_role].";";
						_execute_stmt($_stmt_array,$conn); 	
						unset($_stmt_array);
						$_stmt_array['stmt'] = "SELECT id FROM os_roles WHERE parentid = ?";
						$_stmt_array['str_types'] = "i";
						$_stmt_array['arr_values'] = array();
						$_stmt_array['arr_values'][] = $_role;
						$_children = execute_stmt($_stmt_array,$conn)['result']['id'];
						unset($_child);
						foreach ( $_children as $_child )
						{
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "GRANT SELECT (realid,keymachine,keyreadable,subtablemachine,typelist,edittype,referencetag,role_".$_PARENTS_ARRAY[$_child].",restrictrole_".$_PARENTS_ARRAY[$_child].",role_".$_child.",restrictrole_".$_child.") ON ".$PARAMETER['tablemachine']."_permissions TO ".$_ROLES_ARRAY[$_child].";";
							_execute_stmt($_stmt_array,$conn); 
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "GRANT SELECT ON ".$PARAMETER['tablemachine']."_references TO ".$_ROLES_ARRAY[$_child].";";
							_execute_stmt($_stmt_array,$conn); 	
						}					
					}
					foreach ( json_decode($PARAMETER['delete_roles'],true) as $_role )
					{
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "GRANT DELETE ON `view__".$PARAMETER['tablemachine']."__".$_role."` TO ".$_ROLES_ARRAY[$_role].";";
						_execute_stmt($_stmt_array,$conn); 
						unset($_stmt_array);
						$_stmt_array['stmt'] = "SELECT id FROM os_roles WHERE parentid = ?";
						$_stmt_array['str_types'] = "i";
						$_stmt_array['arr_values'] = array();
						$_stmt_array['arr_values'][] = $_role;
						$_children = execute_stmt($_stmt_array,$conn)['result']['id'];
						unset($_child);
						foreach ( $_children as $_child )
						{
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "GRANT DELETE ON `view__".$PARAMETER['tablemachine']."__".$_child."` TO ".$_ROLES_ARRAY[$_child].";";
							_execute_stmt($_stmt_array,$conn); 
						}
					}
					//recreate views (after dbAction!)
//					unset($_table);
//					foreach ( $_TABLES as $_table ) {
//						recreateView($_table,$conn);
//					}
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "FLUSH PRIVILEGES;";
					//_execute_stmt($_stmt_array,$conn);  	
					break;
				case 'delete':
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "DROP TABLE ".$PARAMETER['tablemachine'];
					_execute_stmt($_stmt_array,$conn);
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "DROP TABLE ".$PARAMETER['tablemachine']."_permissions";
					_execute_stmt($_stmt_array,$conn);
					unset($_stmt_array); $_stmt_array = array();
					// simply do the same for VIEWS in case it was a subtable
					$_stmt_array['stmt'] = "DROP VIEW ".$PARAMETER['tablemachine'];
					_execute_stmt($_stmt_array,$conn);
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "DROP VIEW ".$PARAMETER['tablemachine']."_permissions";
					_execute_stmt($_stmt_array,$conn);
					//
					unset($_table);
					foreach ( $_TABLES as $_table )
					{
						if ( $_table == $PARAMETER['tablemachine'] ) { continue; }
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "ALTER TABLE `".$_table."` DROP COLUMN `id_".$PARAMETER['tablemachine']."`;";
						_execute_stmt($_stmt_array,$conn);						
					}
					foreach ( json_decode($PARAMETER['allowed_roles'],true) as $_role )
					{
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "DROP VIEW view__".$PARAMETER['tablemachine']."__".$_role;
						_execute_stmt($_stmt_array,$conn); 	
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "REVOKE ALL ON view__".$PARAMETER['tablemachine']."__".$_role." FROM ".$_ROLES_ARRAY[$_role].";";
						_execute_stmt($_stmt_array,$conn); 
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "REVOKE ALL ON ".$PARAMETER['tablemachine']."_permissions FROM ".$_ROLES_ARRAY[$_role].";";
						_execute_stmt($_stmt_array,$conn); 
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "REVOKE ALL ON ".$PARAMETER['tablemachine']."_references FROM ".$_ROLES_ARRAY[$_role].";";
						_execute_stmt($_stmt_array,$conn); 	
						unset($_stmt_array);
						$_stmt_array['stmt'] = "SELECT id FROM os_roles WHERE parentid = ?";
						$_stmt_array['str_types'] = "i";
						$_stmt_array['arr_values'] = array();
						$_stmt_array['arr_values'][] = $_role;
						$_children = execute_stmt($_stmt_array,$conn)['result']['id'];
						unset($_child);
						foreach ( $_children as $_child )
						{
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "DROP VIEW view__".$PARAMETER['tablemachine']."__".$_child;
							_execute_stmt($_stmt_array,$conn); 	
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "REVOKE ALL ON view__".$PARAMETER['tablemachine']."__".$_child." FROM ".$_ROLES_ARRAY[$_child].";";
							_execute_stmt($_stmt_array,$conn); 
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "REVOKE ALL ON ".$PARAMETER['tablemachine']."_permissions FROM ".$_ROLES_ARRAY[$_child].";";
							_execute_stmt($_stmt_array,$conn); 
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "REVOKE ALL ON ".$PARAMETER['tablemachine']."_references FROM ".$_ROLES_ARRAY[$_child].";";
							_execute_stmt($_stmt_array,$conn); 	
						}
					}
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "FLUSH PRIVILEGES;";
					_execute_stmt($_stmt_array,$conn); 	
					//recreate views (after dbAction!)
/*					unset($_table);
					foreach ( $_TABLES as $_table ) {
						recreateView($_table,$conn);
					}
*/
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "DROP TABLE ".$PARAMETER['tablemachine']."_references";
					//_execute_stmt($_stmt_array,$conn);
					break;
				case 'edit':
					foreach ( $_TABLES as $_table )
					{
						if ( $_table == $PARAMETER['tablemachine'] ) {
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "ALTER TABLE `".$_table."` CHANGE COLUMN `id_".$_TABLES_ARRAY[$PARAMETER['id']]."` `id_".$PARAMETER['tablemachine']."` INT NOT NULL AUTO_INCREMENT;";
							_execute_stmt($_stmt_array,$conn);
						}
						else
						{
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "ALTER TABLE `".$_table."` CHANGE COLUMN `id_".$_TABLES_ARRAY[$PARAMETER['id']]."` `id_".$PARAMETER['tablemachine']."` INT DEFAULT NULL;";
							_execute_stmt($_stmt_array,$conn);
						}
					}
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "RENAME TABLE `".$_TABLES_ARRAY[$PARAMETER['id']]."` TO `".$PARAMETER['tablemachine']."`";
					_execute_stmt($_stmt_array,$conn);
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "RENAME TABLE `".$_TABLES_ARRAY[$PARAMETER['id']]."_permissions` TO `".$PARAMETER['tablemachine']."_permissions`";
					_execute_stmt($_stmt_array,$conn);
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "RENAME TABLE `".$_TABLES_ARRAY[$PARAMETER['id']]."_references` TO `".$PARAMETER['tablemachine']."_references`";
					_execute_stmt($_stmt_array,$conn);
					unset($_table); unset($_role);
					foreach ( json_decode($_TABLES_ALLOW_ARRAY[$PARAMETER['id']],true) as $_role )
					{
						if ( $_TABLES_ARRAY[$PARAMETER['id']] != $PARAMETER['tablemachine'] ) {
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "RENAME TABLE `view__".$_TABLES_ARRAY[$PARAMETER['id']]."__".$_role."` TO `view__".$PARAMETER['tablemachine']."__".$_role."`";
							_execute_stmt($_stmt_array,$conn);
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "REVOKE ALL ON view__".$_TABLES_ARRAY[$PARAMETER['id']]."__".$_role." FROM ".$_ROLES_ARRAY[$_role].";";
							_execute_stmt($_stmt_array,$conn); 
						}
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "REVOKE ALL ON ".$_TABLES_ARRAY[$PARAMETER['id']]."_permissions FROM ".$_ROLES_ARRAY[$_role].";";
						_execute_stmt($_stmt_array,$conn); 
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "REVOKE ALL ON ".$_TABLES_ARRAY[$PARAMETER['id']]."_references FROM ".$_ROLES_ARRAY[$_role].";";
						_execute_stmt($_stmt_array,$conn); 	
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "REVOKE DELETE ON `view__".$PARAMETER['tablemachine']."__".$_role."` FROM ".$_ROLES_ARRAY[$_role].";";
						_execute_stmt($_stmt_array,$conn); 	
						unset($_stmt_array);
						$_stmt_array['stmt'] = "SELECT id FROM os_roles WHERE parentid = ?";
						$_stmt_array['str_types'] = "i";
						$_stmt_array['arr_values'] = array();
						$_stmt_array['arr_values'][] = $_role;
						$_children = execute_stmt($_stmt_array,$conn)['result']['id'];
						unset($_child);
						foreach ( $_children as $_child )
						{
							if ( $_TABLES_ARRAY[$PARAMETER['id']] != $PARAMETER['tablemachine'] ) {
								unset($_stmt_array); $_stmt_array = array();
								$_stmt_array['stmt'] = "RENAME TABLE `view__".$_TABLES_ARRAY[$PARAMETER['id']]."__".$_child."` TO `view__".$PARAMETER['tablemachine']."__".$_child."`";
								_execute_stmt($_stmt_array,$conn);
								unset($_stmt_array); $_stmt_array = array();
								$_stmt_array['stmt'] = "REVOKE ALL ON view__".$_TABLES_ARRAY[$PARAMETER['id']]."__".$_child." FROM ".$_ROLES_ARRAY[$_child].";";
								_execute_stmt($_stmt_array,$conn); 
							}
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "REVOKE ALL ON ".$_TABLES_ARRAY[$PARAMETER['id']]."_permissions FROM ".$_ROLES_ARRAY[$_child].";";
							_execute_stmt($_stmt_array,$conn); 
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "REVOKE ALL ON ".$_TABLES_ARRAY[$PARAMETER['id']]."_references FROM ".$_ROLES_ARRAY[$_child].";";
							_execute_stmt($_stmt_array,$conn); 	
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "REVOKE DELETE ON `view__".$PARAMETER['tablemachine']."__".$_child."` FROM ".$_ROLES_ARRAY[$_child].";";
							_execute_stmt($_stmt_array,$conn); 	
						}
					}
					unset($_table); unset($_role);
					foreach ( json_decode($PARAMETER['allowed_roles'],true) as $_role )
					{
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "GRANT SELECT (realid,keymachine,keyreadable,subtablemachine,typelist,edittype,referencetag,role_".$_PARENTS_ARRAY[$_role].",restrictrole_".$_PARENTS_ARRAY[$_role].",role_".$_role.",restrictrole_".$_role.") ON ".$PARAMETER['tablemachine']."_permissions TO ".$_ROLES_ARRAY[$_role].";";
						_execute_stmt($_stmt_array,$conn); 
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "GRANT SELECT ON ".$PARAMETER['tablemachine']."_references TO ".$_ROLES_ARRAY[$_role].";";
						_execute_stmt($_stmt_array,$conn); 	
						unset($_stmt_array);
						$_stmt_array['stmt'] = "SELECT id FROM os_roles WHERE parentid = ?";
						$_stmt_array['str_types'] = "i";
						$_stmt_array['arr_values'] = array();
						$_stmt_array['arr_values'][] = $_role;
						$_children = execute_stmt($_stmt_array,$conn)['result']['id'];
						unset($_child);
						foreach ( $_children as $_child )
						{
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "GRANT SELECT (realid,keymachine,keyreadable,subtablemachine,typelist,edittype,referencetag,role_".$_PARENTS_ARRAY[$_child].",restrictrole_".$_PARENTS_ARRAY[$_child].",role_".$_child.",restrictrole_".$_child.") ON ".$PARAMETER['tablemachine']."_permissions TO ".$_ROLES_ARRAY[$_child].";";
							_execute_stmt($_stmt_array,$conn); 
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "GRANT SELECT ON ".$PARAMETER['tablemachine']."_references TO ".$_ROLES_ARRAY[$_child].";";
							_execute_stmt($_stmt_array,$conn); 	
						}
					}
					unset($_role);
					foreach ( json_decode($PARAMETER['delete_roles'],true) as $_role )
					{
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "GRANT DELETE ON `view__".$PARAMETER['tablemachine']."__".$_role."` TO ".$_ROLES_ARRAY[$_role].";";
						_execute_stmt($_stmt_array,$conn); 
						unset($_stmt_array);
						$_stmt_array['stmt'] = "SELECT id FROM os_roles WHERE parentid = ?";
						$_stmt_array['str_types'] = "i";
						$_stmt_array['arr_values'] = array();
						$_stmt_array['arr_values'][] = $_role;
						$_children = execute_stmt($_stmt_array,$conn)['result']['id'];
						unset($_child);
						foreach ( $_children as $_child )
						{
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "GRANT DELETE ON `view__".$PARAMETER['tablemachine']."__".$_child."` TO ".$_ROLES_ARRAY[$_child].";";
							_execute_stmt($_stmt_array,$conn); 
						}
					}
					//recreate views (after dbAction!)
/*					unset($_table);
					foreach ( $_TABLES as $_table ) {
						recreateView($_table,$conn);
					}
*/					
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "FLUSH PRIVILEGES;";
					_execute_stmt($_stmt_array,$conn); 
	
					break;
			}
			break;
		case str_replace('_permissions','',$PARAMETER['table']).'_permissions':
			$_warning = "Explizite Defaultwerte müssen in einfachen Hochkommata stehen, Ausdrücke als Defaultwerte in runden Klammern.";
			$_propertable = str_replace('_permissions','',$PARAMETER['table']);
			unset($_stmt_array);
			if ( $PARAMETER['dbAction'] != '' ) {
				switch($PARAMETER['typelist']) {
					case 'TIMESTAMP': 
					case 'INT':
						$_DEFAULT = '';
						break;
					default:
						$_DEFAULT = ' DEFAULT NULL';
						break;
				}
			}
			//note: expressions as DEFAULT are only supported for mariadb >= 10.2.1
			if ( isset($PARAMETER['defaultvalue']) AND $PARAMETER['defaultvalue'] != '' ) { $_DEFAULT = ' DEFAULT '.$PARAMETER['defaultvalue']; };					
			switch($PARAMETER['dbAction']) {
				case 'edit':
					unset($_stmt_array);
					$_stmt_array['stmt'] = "SELECT keymachine,typelist FROM ".$PARAMETER['table']." WHERE id = ?";
					$_stmt_array['str_types'] = "i";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = $PARAMETER['id'];
					$_former = execute_stmt($_stmt_array,$conn,true)['result'][0];
					unset($_stmt_array);
					$_stmt_array['stmt'] = "ALTER TABLE `".$_propertable."` CHANGE COLUMN `".$_former['keymachine']."` `".$PARAMETER['keymachine']."` ".$PARAMETER['typelist'].$_DEFAULT;
					_execute_stmt($_stmt_array,$conn);
					break;
				case 'insert':
					unset($_stmt_array);
					$_stmt_array['stmt'] = "ALTER TABLE `".$_propertable."` ADD COLUMN `".$PARAMETER['keymachine']."` ".$PARAMETER['typelist'].$_DEFAULT;
					_execute_stmt($_stmt_array,$conn);
					break;
				case 'delete':
					unset($_stmt_array);
					$_stmt_array['stmt'] = "ALTER TABLE `".$_propertable."` DROP COLUMN `".$PARAMETER['keymachine']."`";
					_execute_stmt($_stmt_array,$conn);
					break;
			}
			break;
	}
	if (! isset($_stmt_array['stmt']) ) { $_stmt_array = array('error'=>'Keine Aktion. ','dbMessageGood'=>true); };
	if ( isset($_stmt_array['error']) ) { $_return['dbMessage'] = $_stmt_array['error']; $_return['dbMessageGood'] = "false"; return $_return; } 
	else {
		$_return = _execute_stmt($_stmt_array,$conn);
		return $_return;
	}
}

function _adminActionAfter(array $PARAMETER, mysqli $conn) {
	global $_TABLES, $_TABLES_ARRAY, $_TABLES_ALLOW_ARRAY, $_ROLES, $_ROLES_ARRAY, $_PARENTS, $_PARENTS_ARRAY, $ENCRYPTED, $_warning;
	$_stmt_array = array();
	$_return = array();
	switch($PARAMETER['table']) {
		case 'os_roles':
			switch($PARAMETER['dbAction']) {
				case 'insert':
					// create user with same name as rolename
					$USERPARAMETER = array();
					$_stmt_array['stmt'] = "SELECT id FROM `os_roles` WHERE `rolename` = ?";
					$_stmt_array['str_types'] = "s";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = $PARAMETER['rolename'];
					$_return = _execute_stmt($_stmt_array,$conn); $_result = $_return['result'];
					if ( $_result AND $_result->num_rows > 0 ) {
						while ($row=$_result->fetch_assoc()) {
							foreach ($row as $key=>$value) {
								$USERPARAMETER['roleid'] = $value;
							}
						}
					}			
					$USERPARAMETER['username'] = $PARAMETER['rolename'];
					$USERPARAMETER['rolepwd'] = $PARAMETER['key'];
					$USERPARAMETER['table'] = "os_users";
					$USERPARAMETER['dbAction'] = $PARAMETER['dbAction'];
					$USERPARAMETER['pwdhash'] = $PARAMETER['pwdhash'];
					_adminActionBefore($USERPARAMETER,$conn);
					_dbAction($USERPARAMETER,$conn);
					_adminActionAfter($USERPARAMETER,$conn);
					// insert this info in os_secrets (temporary workaround; in midterm: do not create this new user and create secret directly instead
					unset($_stmt_array);
					$_stmt_array['stmt'] = "INSERT INTO `os_secrets` (secretmachine,secretreadable,allowed_roles,secret) SELECT CONCAT('role_',rolename),CONCAT('Rolle ',rolename),CONCAT('[',os_roles.id,']'),password FROM os_roles LEFT JOIN ( os_users LEFT JOIN os_passwords AS T2 ON ( os_users.id = userid ) ) ON ( os_roles.id = roleid AND rolename = username) WHERE rolename = ?";
					$_stmt_array['str_types'] = "s";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = $PARAMETER['rolename'];
					_execute_stmt($_stmt_array,$conn);
					//
					unset($USERPARAMETER);
					collectInfo($conn);
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "SELECT id,parentid FROM os_roles WHERE rolename = ?";
					$_stmt_array['str_types'] = "s";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = $PARAMETER['rolename'];
					$_result_array = execute_stmt($_stmt_array,$conn,true);
					$_result_id = $_result_array['result'][0]['id'];
					$_result_parentid = $_result_array['result'][0]['parentid'];
					foreach ( $_TABLES as $_table )
					{
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "ALTER TABLE `".$_table."_permissions` ADD COLUMN `role_".$_result_id."` INT DEFAULT 0;";
						_execute_stmt($_stmt_array,$conn);
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "ALTER TABLE `".$_table."_permissions` ADD COLUMN `restrictrole_".$_result_id."` VARCHAR(40) DEFAULT NULL;";
						_execute_stmt($_stmt_array,$conn);
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "GRANT SELECT (keymachine,keyreadable,subtablemachine,typelist,edittype,realid,referencetag,role_".$_result_id.",restrictrole_".$_result_id.") ON ".$_table."_permissions TO ".$_ROLES_ARRAY[$_result_id].";";
						_execute_stmt($_stmt_array,$conn);
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "GRANT SELECT (role_".$_result_parentid.",restrictrole_".$_result_parentid.") ON ".$_table."_permissions TO ".$_ROLES_ARRAY[$_result_id].";";
						_execute_stmt($_stmt_array,$conn);
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "GRANT SELECT ON ".$_table."_references TO ".$_ROLES_ARRAY[$_result_id].";";
						_execute_stmt($_stmt_array,$conn); 	
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "UPDATE ".$_table."_permissions SET role_".$_result_id." = 6 - role_".$_result_parentid." MOD 8 WHERE keymachine = 'code';";
						_execute_stmt($_stmt_array,$conn);						
						//recreate views
						recreateView($_table,$conn);
					}
					break;
			}
			break;
		case 'os_users':
			$_warning = "Passwortwechsel kann nur bei angegebenem Rollenpasswort durchgeführt werden.<br />Bitte geben Sie unter Passwort das neue Passwort im Klartext ein.<br />Die Nutzer mit Rollennamen als Name dürfen nicht gelöscht werden.";
			switch($PARAMETER['dbAction']) {
				case 'insert':
				//insert encrypted role password into os_passwords
					$_stmt_array = array();
					$_stmt_array['stmt'] = "SELECT id,roleid FROM os_users WHERE username = ?";
					$_stmt_array['str_types'] = "s";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = $PARAMETER['username'];
					$_result_array = execute_stmt($_stmt_array,$conn,true); $_result = $_result_array['result'][0];
					if ( isset($_result) ) {
						$_id = $_result['id'];
						$_roleid = $_result['roleid'];
					}
					unset($_stmt_array);
					$_stmt_array['stmt'] = "SELECT id FROM os_passwords WHERE userid IS NULL";
					$_result_array = _execute_stmt($_stmt_array,$conn); $_result=$_result_array['result'];
					if ( $_result AND $_result->num_rows > 0 ) {
					//- see if there is an entry without userid, if yes, then take this for new user
						$_stmt_array['stmt'] = "UPDATE os_passwords SET userid = ? WHERE userid IS NULL";
						$_stmt_array['str_types'] = "i";
						$_stmt_array['arr_values'] = array();
						$_stmt_array['arr_values'][] = $_id;	
						_execute_stmt($_stmt_array,$conn);
					} else {
					//- if not, then look for role entry (with given rolepwd), decrypt that and encrypt with given password.
						$_stmt_array['stmt'] = "SELECT password,nonce,salt FROM os_passwords WHERE userid = (SELECT id FROM os_users WHERE username = (SELECT rolename FROM os_roles WHERE id = '".$PARAMETER['roleid']."')) AND ( secretname IS NULL OR secretname = '' OR secretname LIKE 'role_%' )";
						$_result_array = _execute_stmt($_stmt_array,$conn); $_result=$_result_array['result'];
						$_result_user = array();
						if ( $_result AND $_result->num_rows > 0 ) {
							while ($row=$_result->fetch_assoc()) {
								foreach ($row as $key=>$value) {
										$_result_user[$key] = sodium_hex2bin($value);
//										$_result_user[$key] = $value;
								}
							}
						} else { $_stmt_array['error'] = "Kein Rolleneintrag gefunden. "; break; }
						$_result_user['key'] = sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES,$PARAMETER['rolepwd'],$_result_user['salt'],SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
						unset($PARAMETER['rolepwd']); //too early?
						$dbpwd = sodium_crypto_secretbox_open($_result_user['password'],$_result_user['nonce'],$_result_user['key']);
						$salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
						$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
						$PARAMETER['genkey'] = sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES,$PARAMETER['key'],$salt,SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
						$passwd = sodium_crypto_secretbox($dbpwd,$nonce,$PARAMETER['genkey']);
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "INSERT INTO os_passwords (userid,password,salt,nonce) VALUES (?,?,?,?)";
						$_stmt_array['str_types'] = "isss";
						$_stmt_array['arr_values'] = array();
						$_stmt_array['arr_values'][] = $_id;
						$_stmt_array['arr_values'][] = sodium_bin2hex($passwd);
						$_stmt_array['arr_values'][] = sodium_bin2hex($salt);
						$_stmt_array['arr_values'][] = sodium_bin2hex($nonce);
						_execute_stmt($_stmt_array,$conn);
						}
					//insert defaultconfig, create config and users views and grant permissons on views
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "SELECT rolename,defaultconfig FROM os_roles WHERE id = ?;";
					$_stmt_array['str_types'] = "i";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = $_roleid;
					$_select = execute_stmt($_stmt_array,$conn,true)['result'][0]; 
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "INSERT INTO os_userconfig(userid,config,configname) values (?,?,?);";
					$_stmt_array['str_types'] = "iss";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = $_id;
					$_stmt_array['arr_values'][] = $_select['defaultconfig'];
					$_stmt_array['arr_values'][] = 'Default';
					_execute_stmt($_stmt_array,$conn); 
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "CREATE OR REPLACE ALGORITHM = MERGE VIEW os_userconfig_".$_id." AS SELECT * FROM os_userconfig WHERE userid = '".$_id."';";
					_execute_stmt($_stmt_array,$conn); 
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "GRANT SELECT,UPDATE,INSERT,DELETE ON os_userconfig_".$_id." TO ".$_select['rolename'].";";
					_execute_stmt($_stmt_array,$conn); 
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "CREATE OR REPLACE ALGORITHM = MERGE VIEW os_users_".$_id." AS SELECT username FROM os_users WHERE id = '".$_id."';";
					_execute_stmt($_stmt_array,$conn); 
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "GRANT SELECT,UPDATE ON os_users_".$_id." TO ".$_select['rolename'].";";
					_execute_stmt($_stmt_array,$conn); 
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "FLUSH PRIVILEGES;";
					//_execute_stmt($_stmt_array,$conn); 						
					break;
				case 'delete':
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "DROP VIEW os_userconfig_".$PARAMETER['id'];
					_execute_stmt($_stmt_array,$conn); 
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "DROP VIEW os_users_".$PARAMETER['id'];
					_execute_stmt($_stmt_array,$conn); 
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "DELETE FROM os_passwords WHERE userid=?";
					$_stmt_array['str_types'] = "i";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = $PARAMETER['id'];
					_execute_stmt($_stmt_array,$conn); 
					break;	
				case 'edit':
				//update encrypted role password in os_passwords if new password is given
 					$_stmt_array = array();
					$_stmt_array['stmt'] = "SELECT id,roleid FROM os_users WHERE username = ?";
					$_stmt_array['str_types'] = "s";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = $PARAMETER['username'];
					$_result_array = execute_stmt($_stmt_array,$conn,true); $_result = $_result_array['result'][0];
					if ( isset($_result) ) {
						$_id = $_result['id'];
						$_roleid = $_result['roleid'];
					}
					unset($_stmt_array);
					$_stmt_array['stmt'] = "SELECT password,nonce,salt FROM os_passwords WHERE userid = (SELECT id FROM os_users WHERE username = (SELECT rolename FROM os_roles WHERE id = '".$PARAMETER['roleid']."') AND ( secretname IS NULL OR secretname = '' OR secretname LIKE 'role_%' ))";
					$_result_array = _execute_stmt($_stmt_array,$conn); $_result=$_result_array['result'];
					$_result_user = array();
					if ( $_result AND $_result->num_rows > 0 ) {
						while ($row=$_result->fetch_assoc()) {
							foreach ($row as $key=>$value) {
									$_result_user[$key] = sodium_hex2bin($value);
//										$_result_user[$key] = $value;
							}
						}
					} else { $_stmt_array['error'] = "Kein Rolleneintrag gefunden. "; break; }
					$_result_user['key'] = sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES,$PARAMETER['rolepwd'],$_result_user['salt'],SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
					unset($PARAMETER['rolepwd']); //too early?
					$dbpwd = sodium_crypto_secretbox_open($_result_user['password'],$_result_user['nonce'],$_result_user['key']);
					$salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
					$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
					$PARAMETER['genkey'] = sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES,$PARAMETER['key'],$salt,SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
					$passwd = sodium_crypto_secretbox($dbpwd,$nonce,$PARAMETER['genkey']);
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "UPDATE os_passwords SET password=?, salt=?, nonce=? WHERE userid=? AND ( secretname IS NULL OR secretname = '' OR secretname LIKE 'role_%' )";
					$_stmt_array['str_types'] = "sssi";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = sodium_bin2hex($passwd);
					$_stmt_array['arr_values'][] = sodium_bin2hex($salt);
					$_stmt_array['arr_values'][] = sodium_bin2hex($nonce);
					$_stmt_array['arr_values'][] = $_id;
					_execute_stmt($_stmt_array,$conn);
					break;
			}
			break;
		case str_replace('_permissions','',$PARAMETER['table']).'_permissions':
			$_propertable = str_replace('_permissions','',$PARAMETER['table']);
			switch($PARAMETER['dbAction']) {
				case 'delete':
				case 'edit':
				case 'insert':
					recreateView($_propertable,$conn);
					break;
			}	
			break;
		case 'os_tables':
			switch($PARAMETER['dbAction']) {
				case 'delete':
				case 'edit':
				case 'insert':
					//recreate views (after dbAction!)
					unset($_table);
					foreach ( $_TABLES as $_table ) {
						recreateView($_table,$conn);
					}
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "FLUSH PRIVILEGES;";
					//_execute_stmt($_stmt_array,$conn);
					break;
			}
			break;	
		}
}


function _dbAction(array $PARAMETER,mysqli $conn) {
	$message = "";
	if ( ! isset($PARAMETER['dbAction']) ) { $PARAMETER['dbAction'] = ''; }
	switch($PARAMETER['dbAction']) {
		case 'insert':
			$into = " INTO `" . $PARAMETER['table'] . "` ";
			$komma = "(";
			$arr_values = array();
			$str_types = '';
			$values = " VALUES ";
			foreach($PARAMETER as $key=>$value)
			{
				if ( $value != 'none' AND $value != '' AND $key != 'id' AND $key != 'dbAction' AND $key != 'dbMessage' AND $key != 'table' AND $key != 'key' AND $key != 'genkey' AND $key != 'rolepwd') {
					$into .= $komma . "`" . str_replace("_","_",$key) . "`";
					$values .= $komma. "?";
					$arr_values[] = rtrim($value);
					$str_types .= "s";
					$komma = ",";
				}
			}
			$into .= ")";
			$values .= ")";
			$stmt = "INSERT " . $into . $values . ";";
			$message = "Eintrag wurde hinzugefügt. <a href=\"https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."\">weiter</a>";
			break;
		case 'delete':
			$stmt = "DELETE FROM `" . $PARAMETER['table'] . "` WHERE id = ?;";
			$arr_values = array();
			$arr_values[] = $PARAMETER['id'];
			$str_types = "i";
			$message = "Eintrag ". $PARAMETER['id'] . " wurde gelöscht. <a href=\"https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."\">weiter</a>";
			break;
		case 'edit':
			$komma = "SET ";
			$set = '';
			$arr_values = array();
			$str_types = '';
			foreach($PARAMETER as $key=>$value)
			{
				if ( $value != 'none' AND $value != '' AND $key != 'dbAction' AND $key != 'dbMessage' AND $key != 'table' AND $key != 'key' AND $key != 'genkey' AND $key != 'rolepwd') {
					$set .= $komma . "`" . str_replace("_","_",$key) . "`= ?";
					$komma = ",";
					$arr_values[] = rtrim($value);
					$str_types .= "s";
				}
			}
			$stmt = "UPDATE `" . $PARAMETER['table'] . "`" . $set . " WHERE id = " . $PARAMETER['id'] . ";";
			$message = "Eintrag ". $PARAMETER['id'] . " wurde geändert.  <a href=\"https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."\">weiter</a>";
			break;
		default:
			////get filters
			$where = '';
			$_and=' WHERE ';
			$filtered = 1;
			$arr_values = array();
			$str_types = '';
			foreach($PARAMETER as $key=>$value)
			{
				if ( $value != 'none' AND $value != '' AND $key != 'dbAction' AND $key != 'dbMessage' AND $key != 'table' AND $key != 'key' AND $key != 'genkey' AND $key != 'rolepwd') {
					if ( $key == 'id' ) {
						$where .= $_and . "`". str_replace("_","_",$key) . "` = ?";
					} else {
						$where .= $_and . "`". str_replace("_","_",$key) . "` LIKE CONCAT('%',?,'%')";
					}
					$_and = " AND ";
					$filtered = 1;
					$arr_values[] = $value;
					$str_types .= "s";
				}
			}
			$stmt = "SELECT * FROM `" . $PARAMETER['table'] . "` " . $where  . " ORDER BY `id`;";
			break;
	}
	unset($_stmt_array);
	$_stmt_array = array(); $_stmt_array['stmt'] = $stmt; $_stmt_array['str_types'] = $str_types; $_stmt_array['arr_values'] = $arr_values; $_stmt_array['message'] = $message;  
	$_return=_execute_stmt($_stmt_array,$conn);
	return $_return;
}

function recreateView(string $_propertable, mysqli $conn) {
	global $_TABLES, $_TABLES_ARRAY, $_TABLES_ALLOW_ARRAY, $_TABLES_PARENTMACHINE_ARRAY, $_ROLES, $_ROLES_ARRAY, $_PARENTS, $_PARENTS_ARRAY, $ENCRYPTED, $_warning;
	//recreate view
	//get delete_roles and parentmachine (is it a table or a view?) for this table
	$PARAMETER = array();
	$PARAMETER['table'] = $_propertable.'_permissions';
	$_stmt_array['stmt'] = "SELECT delete_roles,parentmachine FROM os_tables WHERE tablemachine = ?";
	$_stmt_array['str_types'] = "s";
	$_stmt_array['arr_values'] = array();
	$_stmt_array['arr_values'][] = $_propertable;
	$_os_tables_result = execute_stmt($_stmt_array,$conn,true)['result'][0];
	$_delete_roles = $_os_tables_result['delete_roles'];
	$PARAMETER['parentmachine'] = $_os_tables_result['parentmachine'];
	//recreate $_propertable if it is a subtable
	if ( $PARAMETER['parentmachine'] != '' ) {
		unset($_stmt_array); $_stmt_array = array();
		$_stmt_array['stmt'] = "SELECT CONCAT('CREATE OR REPLACE ALGORITHM = MERGE VIEW  `".$_propertable."` AS SELECT D.`subtable_".$_propertable."`, T.id_".$PARAMETER['parentmachine'].", T.changedat, T.changedby, T.code";
		foreach ( $_TABLES_ARRAY as $_id_ => $_table ) //this should only loop over base tables!
			{
				if ( $_table == $PARAMETER['parentmachine'] OR $_TABLES_PARENTMACHINE_ARRAY[$_id_] != '' ) { continue; }
				$_stmt_array['stmt'] .= ", T.`id_".$_table."`";
			}
		$_stmt_array['stmt'] .= ",', @qry, ' FROM (SELECT 1 AS `subtable_".$_propertable."`) D CROSS JOIN ".$PARAMETER['parentmachine']." AS T WITH CHECK OPTION') INTO @qry2;";
		file_put_contents('/var/www/test/openStat/mylog.txt',$_stmt_array['stmt'],FILE_APPEND);
		$conn->begin_transaction();
		$conn->query("START TRANSACTION;");
		$conn->query("SELECT GROUP_CONCAT(CONCAT('T.',keymachine) ORDER BY realid) INTO @qry FROM ".$PARAMETER['parentmachine']."_permissions WHERE subtablemachine = '".$_propertable."';");
		$conn->query($_stmt_array['stmt']);
		$conn->query("PREPARE stmt FROM @qry2;");
		$conn->query("EXECUTE stmt;");
		$conn->query("COMMIT;");
		$conn->commit();
	} elseif ( substr($_propertable,-4) != "MAIN" ) {
		unset($_stmt_array); $_stmt_array = array();
		$_stmt_array['stmt'] = "SELECT CONCAT('CREATE OR REPLACE ALGORITHM = MERGE VIEW  `".$_propertable."MAIN` AS SELECT id_".$_propertable;
		foreach ( $_TABLES_ARRAY as $_id_ => $_table ) //this should only loop over base tables!
			{
				if ( $_table == $_propertable OR $_TABLES_PARENTMACHINE_ARRAY[$_id_] != '' ) { continue; }
				$_stmt_array['stmt'] .= ", `id_".$_table."`";
			}
		$_stmt_array['stmt'] .= ",', @qry, ' FROM ".$_propertable." WITH CHECK OPTION') INTO @qry2;";
		$conn->begin_transaction();
		$conn->query("START TRANSACTION;");
		$conn->query("SELECT GROUP_CONCAT(keymachine ORDER BY realid) INTO @qry FROM ".$_propertable."_permissions WHERE subtablemachine = '' OR subtablemachine IS NULL;");
		$conn->query($_stmt_array['stmt']);
		$conn->query("PREPARE stmt FROM @qry2;");
		$conn->query("EXECUTE stmt;");
		$conn->query("COMMIT;");
		$conn->commit();
		// // create view for _permissions
		unset($_stmt_array); $_stmt_array = array();
		$_stmt_array['stmt'] = "CREATE OR REPLACE ALGORITHM = MERGE VIEW  `".$_propertable."MAIN_permissions` AS SELECT * FROM ".$_propertable."_permissions WHERE subtablemachine = '' OR subtablemachine IS NULL";
		_execute_stmt($_stmt_array,$conn);						
		// // create view for _references
		unset($_stmt_array); $_stmt_array = array();
		$_stmt_array['stmt'] = "CREATE OR REPLACE ALGORITHM = MERGE VIEW  `".$_propertable."MAIN_references` AS SELECT * FROM ".$_propertable."_references WHERE referencetag in (SELECT DISTINCT referencetag FROM `".$_propertable."MAIN_permissions`)";
		_execute_stmt($_stmt_array,$conn);						
		recreateView($_propertable."MAIN",$conn);
	}
	unset($_stmt_array);
	//query os_roles into $PARAMETER['rolename'] in a loop
	$_stmt_array['stmt'] = "SELECT id AS roleid,parentid,rolename FROM os_roles";
	$_result_array = _execute_stmt($_stmt_array,$conn); $_result=$_result_array['result'];
	if ( $_result AND $_result->num_rows > 0 ) {
		while ($row=$_result->fetch_assoc()) {
			foreach ($row as $key=>$value) {
				$PARAMETER[$key] = $value;
			}
			unset($value);
//							foreach ($row as $key=>$value) {
				//to do: add correct privileges to view columns; test this
//								$conn->begin_transaction(MYSQLI_TRANS_START_READ_ONLY); //only supported for >5.6.5, so maybe omit this.
//								we resort to query since all other solutions (prepared statement, multiquery) fail: prepared statements cannot preserve
//								the variable state, multiqueries cannot be repeated withot closing and reopening the connection
				
				//add WHERE to include restrictrole_ values;
				$CREATEVIEW_WHERE = '';
				$CREATEVIEW_AND = '';
				unset($_stmt_array); $_stmt_array = array();
				$_stmt_array['stmt'] = "SELECT keymachine,restrictrole_".$PARAMETER['roleid']." AS role,restrictrole_".$PARAMETER['parentid']." AS parentrole FROM ".$PARAMETER['table'];
				$cv_restrictions = execute_stmt($_stmt_array,$conn,true)['result'];
				if ( isset($cv_restrictions) ) {
					unset($_restrict);
					foreach ( $cv_restrictions as $_restrict )
					{
						 if ( $_restrict['role'] != '' ) {
							$_values = str_replace('THIS_ROLE',$PARAMETER['rolename'],$_restrict['role']);
							$_values = str_replace('CHILD_ROLE','',$_values);
							$_values = str_replace('USER','',$_values);
							$_values = trimList($_values);
							$_values = implode("\',\'",json_decode($_values,true));
							if ( $_values != ',' AND $_values != '' ) {
								$CREATEVIEW_WHERE .= $CREATEVIEW_AND.$_restrict['keymachine']." IN (\'".$_values."\') ";
								$CREATEVIEW_AND = ' AND ';
							}
						}
						 if ( $_restrict['parentrole'] != '' ) {
							$_values = str_replace('CHILD_ROLE',$PARAMETER['rolename'],$_restrict['parentrole']);
							$_values = str_replace('THIS_ROLE','',$_values);
							$_values = str_replace('USER','',$_values);
							$_values = trimList($_values);
							$_values = implode("\',\'",json_decode($_values,true));
							if ( $_values != ',' AND $_values != '' ) {
								$CREATEVIEW_WHERE .= $CREATEVIEW_AND.$_restrict['keymachine']." IN (\'".$_values."\') ";
//											$CREATEVIEW_WHERE .= $CREATEVIEW_AND.$_restrict['keymachine'].' IN ('.$_values.') ';
								$CREATEVIEW_AND = ' AND ';
							}
						}
					}	
				}
				//add WHERE to include tietotables restrictions (do this separately for backward compatibility of older table structures)
				unset($_stmt_array); $_stmt_array = array();
				$_stmt_array['stmt'] = "SELECT tietotables FROM os_tables WHERE tablemachine = ?";
				$_stmt_array['str_types'] = "s";
				$_stmt_array['arr_values'] = array();
				$_stmt_array['arr_values'][] = $_propertable;
				$_tietotables = execute_stmt($_stmt_array,$conn,true)['result'][0]['tietotables'];
				$_tietotables_array = json_decode($_tietotables,true);
				if ( isset($_tietotables_array[$PARAMETER['roleid']]) ) {
					foreach ( $_tietotables_array[$PARAMETER['roleid']] as $_tieingtable ) {
						$CREATEVIEW_WHERE .= $CREATEVIEW_AND." id_".$_tieingtable." IN ( SELECT id_".$_tieingtable." FROM view__".$_tieingtable."__".$PARAMETER['roleid']." ) ";
						$CREATEVIEW_AND = ' AND ';
					}
				}
				if ( $CREATEVIEW_WHERE != '' ) { $CREATEVIEW_WHERE = " WHERE ".$CREATEVIEW_WHERE; }
				$conn->begin_transaction();
				$conn->query("START TRANSACTION;");
				$conn->query("SELECT GROUP_CONCAT(keymachine ORDER BY realid) INTO @qry FROM ".$PARAMETER['table']." WHERE ( `role_".$PARAMETER['roleid']."` + `role_".$PARAMETER['parentid']."` ) MOD 8 != 7;"); //if you add permission types: 8=2^n; 7=2^n-1;
				//id in next line is wrong: replace by id_s of allowed tables for role
				$CREATEVIEW_ID = '';
				if ( $PARAMETER['parentmachine'] != '' ) { $CREATEVIEW_ID = 'subtable_'.$_propertable.','; }
				$CREATEVIEW_KOMMA = ',';
				unset($_tableid);
				foreach ( $_TABLES_ALLOW_ARRAY as $_tableid=>$_allowed_roles )
				{
					//added parentid on 20211109
					//added test for subtables 20220319
					if ( $_TABLES_PARENTMACHINE_ARRAY[$_tableid] == '' AND ( in_array($PARAMETER['roleid'],json_decode($_allowed_roles,true)) OR in_array($PARAMETER['parentid'],json_decode($_allowed_roles,true)) ) ) {
						$CREATEVIEW_ID .= 'id_'.$_TABLES_ARRAY[$_tableid].$CREATEVIEW_KOMMA;
					}	
				}
				//echo("SELECT CONCAT('CREATE OR REPLACE ALGORITHM = MERGE VIEW view__".$_propertable.'__'.$PARAMETER['roleid']." AS SELECT ".$CREATEVIEW_ID."', @qry, ' FROM ".$_propertable.$CREATEVIEW_WHERE." WITH CHECK OPTION') INTO @qry2;");
				$conn->query("SELECT CONCAT('CREATE OR REPLACE ALGORITHM = MERGE VIEW view__".$_propertable.'__'.$PARAMETER['roleid']." AS SELECT ".$CREATEVIEW_ID."', @qry, ' FROM ".$_propertable.$CREATEVIEW_WHERE." WITH CHECK OPTION') INTO @qry2;");
				$conn->query("PREPARE stmt FROM @qry2;");
				$conn->query("EXECUTE stmt;");
				$conn->query("COMMIT;");
				$conn->commit();
				$conn->begin_transaction();
				$conn->query("START TRANSACTION;");
//								$conn->query("REVOKE ALL PRIVILEGES, GRANT OPTION FROM ".$PARAMETER['rolename']);
				$conn->query("REVOKE ALL ON view__".$_propertable."__".$PARAMETER['roleid']." FROM ".$PARAMETER['rolename']);
				$conn->query("COMMIT;");
				$conn->commit();
				$conn->begin_transaction();
				$conn->query("START TRANSACTION;");
				$conn->query("SELECT GROUP_CONCAT(keymachine) INTO @qry1 FROM ".$PARAMETER['table']." WHERE ( `role_".$PARAMETER['roleid']."` + `role_".$PARAMETER['parentid']."` ) MOD 2 < 1;");
				$conn->query("SELECT GROUP_CONCAT(keymachine) INTO @qry2 FROM ".$PARAMETER['table']." WHERE ( `role_".$PARAMETER['roleid']."` + `role_".$PARAMETER['parentid']."` ) MOD 4 < 2;");
				$conn->query("SELECT GROUP_CONCAT(keymachine) INTO @qry4 FROM ".$PARAMETER['table']." WHERE ( `role_".$PARAMETER['roleid']."` + `role_".$PARAMETER['parentid']."` ) MOD 8 < 4;");
				$conn->query("SELECT CONCAT('GRANT SELECT (".$CREATEVIEW_ID."', @qry1,'), UPDATE (".$CREATEVIEW_ID."', @qry2,'), INSERT(".$CREATEVIEW_ID."', @qry4, ') ON view__".$_propertable.'__'.$PARAMETER['roleid']." TO ".$PARAMETER['rolename']."') INTO @qry;");
//wrong place:					$conn->query("GRANT SELECT, UPDATE ON os_userconfig.* TO".$PARAMETER['rolename'].";");
//needs to be in role>insert	$conn->query("GRANT SELECT ON os_functions.* TO".$PARAMETER['rolename'].";");
//								$conn->query("GRANT SELECT (keymachine,keyreadable,subtablemachine,typelist,edittype,referencetag,role_".$PARAMETER['roleid'].",restrictrole_".$PARAMETER['roleid'].") ON ".$PARAMETER['table']."_permissions TO ".$PARAMETER['rolename'].";");
				$conn->query("PREPARE stmt FROM @qry;");
				$conn->query("EXECUTE stmt;");
				$conn->query("FLUSH PRIVILEGES;");
				$conn->query("COMMIT;");
				$conn->commit();
//								if (!$conn->multi_query($query)) { echo("Multiquery has errors: ".$query." ".$PARAMETER['rolename']); };
//								$_stmt_array['stmt'] = "SELECT GROUP_CONCAT(keymachine) INTO @qry FROM os_permissions WHERE `role".$PARAMETER['rolename']."list` > 0";
//								_execute_stmt($_stmt_array,$conn);
//								$_stmt_array['stmt'] = "SELECT CONCAT('CREATE OR REPLACE ALGORITHM = MERGE VIEW view".$PARAMETER['rolename']." AS SELECT id,', @qry, ' FROM os_all') INTO @qry2";
//								_execute_stmt($_stmt_array,$conn);
//								$_stmt_array['stmt'] = "PREPARE stmt FROM @qry2";
//								_execute_stmt($_stmt_array,$conn);
//								$_stmt_array['stmt'] = "EXECUTE stmt";					
//								_execute_stmt($_stmt_array,$conn);
				if ( in_array($PARAMETER['roleid'], json_decode($_delete_roles,true)) OR in_array($PARAMETER['parentid'], json_decode($_delete_roles,true)) )
				{
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "GRANT DELETE ON `view__".$_propertable."__".$PARAMETER['roleid']."` TO ".$PARAMETER['rolename'].";";
					execute_stmt($_stmt_array,$conn); 
				}
//							}
		}
	}
}

function importSQL(array $PARAMETER,mysqli $conn) {
	if ( ! isset($PARAMETER['sqlfile']) ) { return; }
	$_return = array('dbMessage' => '', 'dbMessageGood' => 'true');
	$_sqllines = file('../../sql/'.$PARAMETER['sqlfile']);
	//preprocess OS-extensions:
	$_oldsqllines = array ();
	while ( $_oldsqllines != $_sqllines ) {
		$_oldsqllines = $_sqllines;
		foreach( $_sqllines  as $_index => $_sqlline ) {
			// 'OS_TABLES LIKE'
			preg_match('/OS_TABLES LIKE \'([_%a-z0-9]*)\'/',$_sqlline,$matches);
			if ( $matches[1] != '' ) {
				unset($_stmt_array); $_stmt_array = array();
				$_stmt_array['stmt'] = "SHOW TABLES LIKE '".$matches[1]."';";
				$_loop_tables = execute_stmt($_stmt_array,$conn,true)['result'];
				$_replacement = array ();
				foreach ($_loop_tables as $_loop_table ) {
					// $_loop_table has exactly one key, but of unknown name (unless we look up the database name...)
					foreach ( $_loop_table as $_tablename ) {
						array_push($_replacement,str_replace($matches[0],$_tablename,$_sqlline));
					}
				}
				array_splice($_sqllines,$_index,1,$_replacement);
				break;
				//splice $_sqllines to fit in the loop instead of the placeholder
			}
			// 'OS_ROLES LIKE'
			preg_match('/OS_ROLES LIKE \'([_%A-Za-z0-9]*)\'/',$_sqlline,$matches);
			if ( $matches[1] != '' ) {
				unset($_stmt_array); $_stmt_array = array();
				$_stmt_array['stmt'] = "SELECT rolename from os_roles WHERE rolename != '_none_' AND rolename LIKE '".$matches[1]."';";
				$_loop_roles = execute_stmt($_stmt_array,$conn,true)['result'];
				$_replacement = array ();
				foreach ($_loop_roles as $_loop_role ) {
					// $_loop_table has exactly one key, but of unknown name (unless we look up the database name...)
					foreach ( $_loop_role as $_role ) {
						array_push($_replacement,str_replace($matches[0],$_role,$_sqlline));
					}
				}
				array_splice($_sqllines,$_index,1,$_replacement);
				break;
				//splice $_sqllines to fit in the loop instead of the placeholder
			}
		}
	}
	//
	$_tmpline = '';
	$conn->begin_transaction();
	$conn->query("START TRANSACTION;");
	foreach( $_sqllines  as $_index => $_sqlline ) {
		$_lineno = $_index + 1;
		if (substr(trim($_sqlline), 0, 1) != '#' AND substr(trim($line), 0, 2) != '--') {
			$_tmpline .= $_sqlline;
		}
		if (substr(trim($_tmpline), -1, 1) == ';') {
			if ( !$conn -> query($_tmpline) ) { $_return['dbMessage'] .= $_tmpline."<br />Importfehler in Zeile ".$_lineno." von ".$PARAMETER['sqlfile'].": ". $conn -> error . "<br /><br />"; $_return['dbMessageGood'] = "false"; };
			$_tmpline = '';
		}
	}
	$conn->query("COMMIT;");
	$conn->commit();
	if ( $_return['dbMessageGood'] == 'true' ) { $_return['dbMessage'] = "Import erfolgreich. "; }
	// log import and result in osadm_sqlimport
	unset($_stmt_array); $_stmt_array = array();
	$_stmt_array['stmt'] = "INSERT INTO osadm_sqlimport (sqlfilename,importresult) VALUES (?,?) ON DUPLICATE KEY UPDATE sqlfilename=?, importresult=?;";
	$_stmt_array['str_types'] = "ssss";
	$_stmt_array['arr_values'] = array();
	$_stmt_array['arr_values'][] = $PARAMETER['sqlfile'];
	$_stmt_array['arr_values'][] = $_return['dbMessage'];	
	$_stmt_array['arr_values'][] = $PARAMETER['sqlfile'];
	$_stmt_array['arr_values'][] = $_return['dbMessage'];	
	execute_stmt($_stmt_array,$conn); 
	//
	return $_return;
}

$result_sql = importSQL($PARAMETER,$conn);
$result_admin_before = _adminActionBefore($PARAMETER,$conn);
$result_array = _dbAction($PARAMETER,$conn);
$result_admin_after = _adminActionAfter($PARAMETER,$conn);
if ( isset($result_array['result']) ) { $result = $result_array['result']; }
$dbMessage = $result_admin_before['dbMessage'].$result_array['dbMessage'].$result_admin_after['dbMessage'];
$dbMessageGood = $result_array['dbMessageGood'];


//$result = @$conn->query($stmt);
//if ($result) { $dbMessage = $message; $dbMessageGood = "true"; } else { $dbMessage = "Operation oder Verbindung war nicht erfolgreich. <a href=\"$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']\">weiter</a>"; $dbMessageGood = "false"; };

//close connection
$conn->close();

//Form for adding entry with given Art, plus add new Art, add new columns

//Form for changing entry (plus deletion)
// do the changes after authorization!!! and entry cleaning!!!

//put result into table and prepare form options
$tableel = "<table id=\"db_results\">";
$options = array();
$keys = array();
$rcount = 0;
if ( isset($result) AND $result->num_rows > 0 ) {
	while ($row=$result->fetch_assoc()) {
		$tableel .= "<tr>";
		if ( $rcount == 0 ) {
			$tableel .= "<th></th>";
			if ( $PARAMETER['table'] == 'os_tables' ) {
				$tableel .= "<th></th><th></th>";
			}
			foreach ($row as $key=>$value) {
				$options[$key] = array();
//				if ( ! in_array($key,$main_keys['default']) ) { continue; }
//				if ( $key == "id" ) { continue; }
				$tableel .= "<th>" . readable($key) . "</th>";
			}
			$tableel .= "</tr>";
		}
		$tableel .= "<td><a href=\"?table=".$PARAMETER['table']."&id=". $row['id'] . "\"><i class=\"fas fa-edit\" title=\"Bearbeiten\"></i></a></td>";
		if ( $PARAMETER['table'] == 'os_tables' ) {
			$tableel .= "<td><a href=\"?table=".$row['tablemachine']."_permissions\"><i class=\"fas fa-columns\" title=\"Spalten bearbeiten\"></i></a></td>";
			$tableel .= "<td><a href=\"?table=".$row['tablemachine']."_references\"><i class=\"fas fa-tags\" title=\"Referenzen bearbeiten\"></i></a></td>";
		}
		foreach ($row as $key=>$value) {
			//create, uniquify and sort options
			$options[$key][] = $value;
			$options[$key] = array_unique($options[$key]);
			asort($options[$key]);
//			if ( ! in_array($key,$main_keys['default']) ) { continue; }
//			if ( $key == "id" ) { continue; }
			$tableel .= "<td class=\"" . $key . "\">" . $value . "</td>";
		}
		$tableel .= "</tr>";
		$rcount++;
	}
} else { $tableel .= "<tr><td>Ihre Suche liefert leider keine Ergebnisse.</td><tr>"; };
$tableel .= "</table>";
?>

<div id="title">openStatAdmin</div>
<div id="title_after"></div>
<div id="content">
	<?php if ( $PARAMETER['table'] != '' ) { ?>
		<div>Sie bearbeiten</div>
		<h1 id="db_headline"><?php echo($PARAMETER['table']); ?></h1>
		<div id="message"  class="<?php echo($dbMessageGood); ?>"><?php echo($dbMessage); ?></div>
	<?php } ?>
	<div id="tables">
		<ul>
			<li><a href="https://<?php echo($_SERVER['HTTP_HOST']); ?>/html/admin.php"><i class="fas fa-power-off" title="Abmelden"></i></a></li>
			<li class="separate"></li>
			<li><a href="https://<?php echo($_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']); ?>?table=os_roles"><i class="fas fa-theater-masks" title="Rollen"></i></a></li>
			<li><a href="https://<?php echo($_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']); ?>?table=os_users"><i class="fas fa-users" title="Benutzer"></i></a></li>
			<li><a href="https://<?php echo($_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']); ?>?table=os_tables"><i class="fas fa-table" title="Nutzertabellen"></i></a></li>
			<li><a href="https://<?php echo($_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']); ?>?table=os_functions"><i class="fas fa-briefcase" title="Funktionen"></i></a></li>
			<li><a href="https://<?php echo($_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']); ?>?table=os_secrets"><i class="fas fa-mask" title="Geheimnisse"></i></a></li>
			<li><a href="https://<?php echo($_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']); ?>?site=os_sql"><i class="fas fa-database" title="SQL Import"></i></a></li>
			<li class="separate"></li>
			<li>
				<a>
				<form id="recreateViewsForm" method="POST" class="recreateViews">
					<input type="checkbox" name="recreateViews" checked hidden>
					<label for="submitRecreateViews"><i class="fas fa-binoculars" title="Views aktualisieren"></i></label>
					<input id="submitRecreateViews" type="submit" hidden>
				</form>
				</a>
			</li>
			<li>
				<a>
				<form id="logForm" method="POST" class="logActivity log<?php echo($_SESSION['log']); ?>">
					<input type="checkbox" name="logActivity" checked hidden>
					<label for="submitLog"><i class="fas fa-microphone" title="Logbuch starten/beenden"></i></label>
					<input id="submitLog" type="submit" hidden>
				</form>
				</a>
			</li>
				<?php if ( isset($_SESSION['log']) AND ! $_SESSION['log'] ) { ?>
			<li>
				<a href="data:text/plain;charset=utf-8;base64,<?php echo(base64_encode($_SESSION['logsaved'])); ?>" target="_blank" download="openStatAdmin-Log-<?php echo($_SESSION['logfinishedtime']); ?>.sql" title="openStatAdmin-Log vom <?php echo($_SESSION['logfinishedtimereadable']); ?>"><i class="fas fa-file-download"></i></a>
				<?php
					if ( $_save ) {
						$_logfile = '../../sql/openStatAdmin-Log-'.$_SESSION['logfinishedtime'].'.sql';
						file_put_contents($_logfile,$_SESSION['logsaved']);
					}
				} ?>
			</li>
		</ul>
	</div>
	<?php
	switch($PARAMETER['site']) {
		case 'os_sql':
			$_sql = scandir('../../sql',SCANDIR_SORT_DESCENDING);
			$_warning = "Importieren Sie nur SQL-Dateien, denen Sie vertrauen!"
			?>
			<div id="message" class="<?php echo($result_sql['dbMessageGood']); ?>"><?php echo($result_sql['dbMessage']); ?></div>
			<form id="sql_options" method="POST" action="">
				<fieldset>
					<legend>SQL-Import</legend>
					<label for="submitSQL" class="reset">Importieren</label>
					<input id="submitSQL" type="submit" class="db_formbox" hidden>
					<br><br>
					<div class="warning"><?php echo($_warning); ?></div>
					<select class="db_formbox" name="sqlfile" onchange="_displayFile(this.value)">
						<option value="_none_">[Bitte Datei wählen]</option>
						<?php
						foreach ( $_sql as $_sqlfile ){
						?>
							<option value="<?php html_echo($_sqlfile); ?>"><?php html_echo($_sqlfile); ?></option>
						<?php	
						}
						?>
					</select>
				</fieldset>
			</form>
			<?php
			foreach ( $_sql as $_sqlfile ){
				//$_content = file_get_contents('../../sql/'.$_sqlfile);
				$_content = preg_replace('/\n/','<br />',file_get_contents('../../sql/'.$_sqlfile));
				?>
				<div class="sqlfile hidden" id="<?php html_echo(preg_replace('/\./','',$_sqlfile)); ?>"><?php echo($_content); ?></div>
				<?php
			}
			break;
		default:
			?>
			<form id="db_options" method="POST" action="">
				<fieldset>
					<legend></legend>
					<legend class="reset"><a href="https://<?php echo($_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'].'?table='.$PARAMETER['table']); ?>" title="Alle Parameter zurücksetzen">Alle Filter zurücksetzen</a></legend>
			<!--	Freitextsuche vielleicht später	
					<label for="db_search">Suche</label>
					<input type="text" name="db_search" id="db_search">
			-->
					<div class="warning"><?php echo($_warning); ?></div>
					<?php //filter $options
					foreach ( $options as $key=>$optionlist ) { 
						$default = "";
						if ( count($optionlist) == 1 ) { 
							$default = $optionlist[0];
						};
						$_type = '';
						foreach ( $edittype  as $type=>$list )
						{
							if ( in_array($key,$list) ) { $_type = $type; }
						}
						//implement edittype, referencetable and referencekey for better flexibility (you cannot change key names just in order
						// to change from list to free text...)
						switch($_type) {
							case 'id': 
								if ( count($optionlist) == 1 ) { 
									$PARAMETER['id'] = $optionlist[0];
								};
								break;
							case 'list': 
							?>
								<label for="db_<?php echo($key); ?>"><?php echo(readable($key)); ?></label>
										<input type="text" id="db_<?php echo($key); ?>_text" name="<?php echo($key); ?>" class="db_formbox" value="" onkeyup='_autoComplete(<?php echo(json_encode($optionlist)); ?>,this)' autofocus disabled hidden>
										<select id="db_<?php echo($key); ?>_list" name="<?php echo($key); ?>" class="db_formbox" onchange="_onResetFilter(this.value)">
											<option value="none"></option>
											<?php foreach ( $optionlist as $value ) { 
												$_sel = '';
												if ( (isset($PARAMETER[$key]) AND $PARAMETER[$key] == $value) OR $default == $value ) { $_sel = 'selected'; };
												?>				
												<option value="<?php html_echo($value); ?>" <?php echo($_sel); ?> ><?php html_echo($value); ?></option>
											<?php } ?>
										</select>
										<input class="minus" type="button" value="+" onclick="_addOption('<?php echo($key); ?>')" title="Erlaubt die Eingabe eines neuen Wertes">
								<br><br>
								<?php break;
							default:
							?>
								<label for="db_<?php echo($key); ?>"><?php echo(readable($key)); ?></label>
								<input type="text" id="db_<?php echo($key); ?>" name="<?php echo($key); ?>" class="db_formbox" value="<?php echo(htmlentities($default)); ?>">
								<br>
								<?php break;
						}; } ?>
					<label for="_action" class="action">Aktion</label>
					<select id="_action" name="dbAction" class="db_formbox action" onchange="_onActionCreate(this.value)" title="Aktion bitte erst nach der Bearbeitung der Inhalte wählen.">
						<option value="search" selected>[Bitte wählen]</option>
						<option value="search">Suchen</option>
						<?php if ( isset($PARAMETER['id']) ) { ?>
							<option value="edit">Eintrag ändern</option>
							<option value="delete">Eintrag löschen</option>
						<?php } ?>
						<option value="insert">als neuen Eintrag anlegen</option>
					</select>
					<input type="submit" hidden>	
				</fieldset>
			</form>

			<?php if ( $filtered == 1 ) { ?>
				<?php echo($tableel); ?>
			<?php } else { ; ?>
				<hr>
				<div>Bitte wählen Sie einen Filter.</div>
			<?php };
			break; 
		}
	?>
	<hr>
</div>

<script type="text/javascript" src="/js/os.js"></script>

</body>
</html>

