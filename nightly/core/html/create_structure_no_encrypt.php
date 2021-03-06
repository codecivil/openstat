<?php 
session_start();
if ( ! isset($_SESSION['user']) ) { header('Location:/html/admin.php'); } //redirect to login page if not logged in

require_once('../../core/classes/auth.php');
require_once('../../core/functions/db_functions.php');
require_once('../../core/functions/frontend_functions.php');

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
$PARAMETER = array(); 
$action = '';

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
$conn = new mysqli($servername, $username, $password, $dbname) or die ("Connection failed.");
mysqli_set_charset($conn,"utf8");

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
	global $_TABLES, $_TABLES_ARRAY, $_TABLES_ALLOW_ARRAY, $_ROLES, $_ROLES_ARRAY, $_PARENTS, $_PARENTS_ARRAY;
	$_stmt_array = array();
	$_return = array();
	switch($PARAMETER['table']) {
		case 'os_roles':
			switch($PARAMETER['dbAction']) {
				case 'insert':
					//create new user of database ('role'), generate password and save it in os_passwords encrypted with the given pwd; after this, create new user
					//of same name and password
					$db_pwd = sodium_bin2hex(random_bytes(32)); //
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
					unset($_stmt_array); $_stmt_array = array();
					$PARAMETER['rolename'] = substr($PARAMETER['rolename'],0,16);
					$_stmt_array['stmt'] = "CREATE USER IF NOT EXISTS ".$PARAMETER['rolename']." IDENTIFIED BY '".$db_pwd."';";
					_execute_stmt($_stmt_array,$conn); 
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "REVOKE ALL PRIVILEGES, GRANT OPTION FROM ".$PARAMETER['rolename'];
					_execute_stmt($_stmt_array,$conn); 
					//grant permissions on os_-TABLES
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "GRANT SELECT, UPDATE ON os_userconfig TO ".$PARAMETER['rolename'].";";
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
					//only password change is implemented
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
					}
					$_stmt_array['stmt'] = "DROP USER ".$PARAMETER['rolename'];
					_execute_stmt($_stmt_array,$conn);
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "DELETE FROM `os_users` WHERE `roleid` = ?;";
					$_stmt_array['str_types'] = "i";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = $PARAMETER['id'];
					break;
				case 'edit':
					//unset($stmt_array); $_stmt_array = array();
					//$_stmt_array['error'] = "Rollennamen können nicht geändert werden.";
					break;					
			}
			break;
		case 'os_tables':
			switch($PARAMETER['dbAction']) {
				case 'insert':
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "CREATE TABLE `".$PARAMETER['tablemachine']."` (id_".$PARAMETER['tablemachine']." INT NOT NULL AUTO_INCREMENT, changedat TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, changedby INT, PRIMARY KEY (id_".$PARAMETER['tablemachine']."));";
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
					$_stmt_array['stmt'] = "CREATE TABLE ".$PARAMETER['tablemachine']."_permissions(id INT NOT NULL AUTO_INCREMENT, keymachine VARCHAR(40), keyreadable VARCHAR(255), realid DECIMAL(6,3), typelist VARCHAR(40), edittype VARCHAR(20), referencetag VARCHAR(40), role_0 INT DEFAULT 0, restrictrole_0 TEXT DEFAULT NULL, PRIMARY KEY (id));";
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
					$_stmt_array['stmt'] = "CREATE TABLE ".$PARAMETER['tablemachine']."_references(id INT NOT NULL AUTO_INCREMENT, referencetag VARCHAR(40), depends_on_key VARCHAR(80), depends_on_value VARCHAR(80), allowed_values TEXT, PRIMARY KEY (id));";
					_execute_stmt($_stmt_array,$conn);
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "INSERT INTO ".$PARAMETER['tablemachine']."_references(referencetag) VALUES ('_none_');";
					_execute_stmt($_stmt_array,$conn);
					foreach ( json_decode($PARAMETER['allowed_roles'],true) as $_role )
					{
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "GRANT SELECT (keymachine,keyreadable,typelist,edittype,referencetag,role_".$_PARENTS_ARRAY[$_role].",restrictrole_".$_PARENTS_ARRAY[$_role].",role_".$_role.",restrictrole_".$_role.") ON ".$PARAMETER['tablemachine']."_permissions TO ".$_ROLES_ARRAY[$_role].";";
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
							$_stmt_array['stmt'] = "GRANT SELECT (keymachine,keyreadable,typelist,edittype,referencetag,role_".$_PARENTS_ARRAY[$_child].",restrictrole_".$_PARENTS_ARRAY[$_child].",role_".$_child.",restrictrole_".$_child.") ON ".$PARAMETER['tablemachine']."_permissions TO ".$_ROLES_ARRAY[$_child].";";
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
						$_stmt_array['stmt'] = "GRANT SELECT (keymachine,keyreadable,typelist,edittype,referencetag,role_".$_PARENTS_ARRAY[$_role].",restrictrole_".$_PARENTS_ARRAY[$_role].",role_".$_role.",restrictrole_".$_role.") ON ".$PARAMETER['tablemachine']."_permissions TO ".$_ROLES_ARRAY[$_role].";";
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
							$_stmt_array['stmt'] = "GRANT SELECT (keymachine,keyreadable,typelist,edittype,referencetag,role_".$_PARENTS_ARRAY[$_child].",restrictrole_".$_PARENTS_ARRAY[$_child].",role_".$_child.",restrictrole_".$_child.") ON ".$PARAMETER['tablemachine']."_permissions TO ".$_ROLES_ARRAY[$_child].";";
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
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "FLUSH PRIVILEGES;";
					_execute_stmt($_stmt_array,$conn); 	
					break;
			}
			break;
		case str_replace('_permissions','',$PARAMETER['table']).'_permissions':
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
	if (! isset($_stmt_array['stmt']) ) { $_stmt_array = array('error'=>'Keine Aktion','dbMessageGood'=>true); };
	if ( isset($_stmt_array['error']) ) { $_return['dbMessage'] = $_stmt_array['error']; $_return['dbMessageGood'] = "false"; return $_return; } 
	else {
		$_return = _execute_stmt($_stmt_array,$conn);
		return $_return;
	}
}

function _adminActionAfter(array $PARAMETER, mysqli $conn) {
	global $_TABLES, $_TABLES_ARRAY, $_TABLES_ALLOW_ARRAY, $_ROLES, $_ROLES_ARRAY, $_PARENTS, $_PARENTS_ARRAY;
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
						$_stmt_array['stmt'] = "GRANT SELECT (role_".$_result_id.",restrictrole_".$_result_id.") ON ".$_table."_permissions TO ".$_ROLES_ARRAY[$_result_id].";";
						_execute_stmt($_stmt_array,$conn);
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "GRANT SELECT (role_".$_result_parentid.",restrictrole_".$_result_parentid.") ON ".$_table."_permissions TO ".$_ROLES_ARRAY[$_result_id].";";
						_execute_stmt($_stmt_array,$conn);
					}
					break;
			}
			break;
		case 'os_users':
			switch($PARAMETER['dbAction']) {
				case 'insert':
				//insert encrypted role password into os_passwd
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
						$_stmt_array['stmt'] = "SELECT password,nonce,salt FROM os_passwords WHERE userid = (SELECT id FROM os_users WHERE username = (SELECT rolename FROM os_roles WHERE id = '".$PARAMETER['roleid']."') )";
						$_result_array = _execute_stmt($_stmt_array,$conn); $_result=$_result_array['result'];
						$_result_user = array();
						if ( $_result AND $_result->num_rows > 0 ) {
							while ($row=$_result->fetch_assoc()) {
								foreach ($row as $key=>$value) {
										$_result_user[$key] = sodium_hex2bin($value);
//										$_result_user[$key] = $value;
								}
							}
						} else { $_stmt_array['error'] = "Kein Rolleneintrag gefunden."; break; }
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
					//insert defaultconfig, create config view and grant permissons on view
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "SELECT rolename,defaultconfig FROM os_roles WHERE id = ?;";
					$_stmt_array['str_types'] = "i";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = $_roleid;
					$_select = execute_stmt($_stmt_array,$conn,true)['result'][0]; 
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "INSERT INTO os_userconfig(userid,config) values (?,?);";
					$_stmt_array['str_types'] = "is";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = $_id;
					$_stmt_array['arr_values'][] = $_select['defaultconfig'];
					_execute_stmt($_stmt_array,$conn); 
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "CREATE OR REPLACE ALGORITHM = MERGE VIEW os_userconfig_".$_id." AS SELECT * FROM os_userconfig WHERE userid = '".$_id."';";
					_execute_stmt($_stmt_array,$conn); 
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "GRANT SELECT,UPDATE ON os_userconfig_".$_id." TO ".$_select['rolename'].";";
					_execute_stmt($_stmt_array,$conn); 
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "FLUSH PRIVILEGES;";
					//_execute_stmt($_stmt_array,$conn); 						
					break;						
			}
			break;
		case str_replace('_permissions','',$PARAMETER['table']).'_permissions':
			$_propertable = str_replace('_permissions','',$PARAMETER['table']);
			switch($PARAMETER['dbAction']) {
				case 'delete':
				case 'edit':
				case 'insert':
					//recreate view
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
								if ( $CREATEVIEW_WHERE != '' ) { $CREATEVIEW_WHERE = " WHERE ".$CREATEVIEW_WHERE; }
								$conn->begin_transaction();
								$conn->query("START TRANSACTION;");
								$conn->query("SELECT GROUP_CONCAT(keymachine ORDER BY realid) INTO @qry FROM ".$PARAMETER['table']." WHERE ( `role_".$PARAMETER['roleid']."` + `role_".$PARAMETER['parentid']."` ) MOD 8 != 7;"); //if you add permission types: 8=2^n; 7=2^n-1;
								//id in next line is wrong: replace by id_s of allowed tables for role
								$CREATEVIEW_ID = '';
								$CREATEVIEW_KOMMA = ',';
								foreach ( $_TABLES_ALLOW_ARRAY as $_tableid=>$_allowed_roles )
								{
									if ( in_array($PARAMETER['roleid'],json_decode($_allowed_roles,true)) ) {
										$CREATEVIEW_ID .= 'id_'.$_TABLES_ARRAY[$_tableid].$CREATEVIEW_KOMMA;
									}	
								}
								$conn->query("SELECT CONCAT('CREATE OR REPLACE ALGORITHM = MERGE VIEW view__".$_propertable.'__'.$PARAMETER['roleid']." AS SELECT ".$CREATEVIEW_ID."', @qry, ' FROM ".$_propertable.$CREATEVIEW_WHERE."') INTO @qry2;");
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
//								$conn->query("GRANT SELECT (keymachine,keyreadable,typelist,edittype,referencetag,role_".$PARAMETER['roleid'].",restrictrole_".$PARAMETER['roleid'].") ON ".$PARAMETER['table']."_permissions TO ".$PARAMETER['rolename'].";");
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
//							}
						}
					}
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
				if ( $value != 'none' AND $value != '' AND $key != 'dbAction' AND $key != 'dbMessage' AND $key != 'table' AND $key != 'key' AND $key != 'genkey' AND $key != 'rolepwd') {
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
			$message = "Eintrag wurde hinzugefügt. <a href=\"http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."\">weiter</a>";
			break;
		case 'delete':
			$stmt = "DELETE FROM `" . $PARAMETER['table'] . "` WHERE id = ?;";
			$arr_values = array();
			$arr_values[] = $PARAMETER['id'];
			$str_types = "i";
			$message = "Eintrag ". $PARAMETER['id'] . " wurde gelöscht. <a href=\"http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."\">weiter</a>";
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
			$message = "Eintrag ". $PARAMETER['id'] . " wurde geändert.  <a href=\"http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."\">weiter</a>";
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
			foreach ($row as $key=>$value) {
				$options[$key] = array();
//				if ( ! in_array($key,$main_keys['default']) ) { continue; }
//				if ( $key == "id" ) { continue; }
				$tableel .= "<th>" . $key . "</th>";
			}
			$tableel .= "</tr>";
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
		$tableel .= "<td><a href=\"?table=".$PARAMETER['table']."&id=". $row['id'] . "\"><i class=\"fas fa-edit\" title=\"Bearbeiten\"></i></a></td>";
		if ( $PARAMETER['table'] == 'os_tables' ) {
			$tableel .= "<td><a href=\"?table=".$row['tablemachine']."_permissions\"><i class=\"fas fa-columns\" title=\"Spalten bearbeiten\"></i></a></td>";
			$tableel .= "<td><a href=\"?table=".$row['tablemachine']."_references\"><i class=\"fas fa-tags\" title=\"Referenzen bearbeiten\"></i></a></td>";
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
			<li><a href="http://<?php echo($_SERVER['HTTP_HOST']); ?>/html/admin.php"><i class="fas fa-power-off" title="Abmelden"></i></a></li>
			<li><a href="http://<?php echo($_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']); ?>?table=os_roles"><i class="fas fa-theater-masks" title="Rollen"></i></a></li>
			<li><a href="http://<?php echo($_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']); ?>?table=os_users"><i class="fas fa-users" title="Benutzer"></i></a></li>
			<li><a href="http://<?php echo($_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']); ?>?table=os_tables"><i class="fas fa-table" title="Nutzertabellen"></i></a></li>
			<li><a href="http://<?php echo($_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']); ?>?table=os_functions"><i class="fas fa-briefcase" title="Funktionen"></i></a></li>
		</ul>
	</div>
	<form id="db_options" method="POST" action="">
		<fieldset>
			<legend></legend>
			<legend><a href="http://<?php echo($_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'].'?table='.$PARAMETER['table']); ?>" title="Alle Parameter zurücksetzen">Alle Filter zurücksetzen</a></legend>
	<!--	Freitextsuche vielleicht später	
			<label for="db_search">Suche</label>
			<input type="text" name="db_search" id="db_search">
	-->
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
						<label for="db_<?php echo($key); ?>"><?php echo($key); ?></label>
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
						<label for="db_<?php echo($key); ?>"><?php echo($key); ?></label>
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
	<?php }; ?>
	<hr>
</div>

<script type="text/javascript" src="/js/os.js"></script>

</body>
</html>

