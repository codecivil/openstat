<?php 
//error_reporting(0); //just in case the webserver does not comply...
session_start();
require_once('../../core/functions/db_functions.php');
require_once('../../core/functions/os_functions.php');
require_once('../../core/classes/auth.php');
require_once('../../core/data/serverdata.php');
require_once('../../core/data/chpwddata.php');
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
}

//extension file (with version)
$_ext = scandir('../xpi',SCANDIR_SORT_DESCENDING)[0];

//rerun 'e'-Tests if loaded for the second time: reload without parameters every second time
if ( isset($PARAMETER['e']) ) { 
	if ( isset($_SESSION['e']) ) { $_SESSION['e'] = ! $_SESSION['e']; } else { $_SESSION['e'] = true; }
	unset($PARAMETER['e']);	
} else { unset($_SESSION['e']); };

if ( isset($_SESSION['e']) AND ! $_SESSION['e'] ) { header('Refresh:0; url=/login.php'); };
if ( isset($_SESSION['e']) ) { $disabled = "disabled"; }

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname) or die ("Connection failed.");
mysqli_set_charset($conn,"utf8");
//login

if ( isset($PARAMETER['user']) && !empty($PARAMETER['user']) ) {
	$_login = new OpenStatAuth($PARAMETER['user'],$PARAMETER['password'],$conn);
	$_success = $_login->login();
	if ( ! isset($_success['error']) ) {
		$vtime1 = floor(time()/300);
		$vtime2 = floor(time()/300)-1;
		$v1 = hash("sha512", $vtime1.$_SESSION['os_user'].$_SESSION['os_dbpwd'], false);
		$v2 = hash("sha512", $vtime2.$_SESSION['os_user'].$_SESSION['os_dbpwd'], false);
		$expiry = 0;
		if ( $PARAMETER['v'] == $v1 ) { $expiry = 300*($vtime1+2); }
		if ( $PARAMETER['v'] == $v2 ) { $expiry = 300*($vtime1+1); }
		if ( $PARAMETER['v'] != $v1 AND $PARAMETER['v'] != $v2) {
			$_success['error'] = "Änderungszeitraum ist abgelaufen.";
		} elseif ( 
			! isset($PARAMETER['passwordnew']) OR
			strlen($PARAMETER['passwordnew'])<8 OR 
			preg_match_all("/[0-9]/",$PARAMETER['passwordnew']) == 0 OR
			preg_match_all("/[0-9]/",$PARAMETER['passwordnew']) > strlen($PARAMETER['passwordnew'])/2 OR
			( preg_match_all("/[A-Z]/",$PARAMETER['passwordnew']) == 0 AND
			preg_match_all("/[a-z]/",$PARAMETER['passwordnew']) == 0 )
			)
		{
			$_success['error'] = "Das neue Passwort ist zu schwach.";
		} else {
			//change password
			$PARAMETER['key'] = $PARAMETER['passwordnew'];
			$PARAMETER['pwdhash'] = sodium_crypto_pwhash_str($PARAMETER['key'],SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
			$salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
			$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
			$PARAMETER['genkey'] = sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES,$PARAMETER['key'],$salt,SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
			$passwd = sodium_crypto_secretbox($_SESSION['os_dbpwd'],$nonce,$PARAMETER['genkey']);
			unset($_stmt_array); $_stmt_array = array();
			$_stmt_array['stmt'] = "UPDATE os_users SET pwdhash=? WHERE id = ?";
			$_stmt_array['str_types'] = "si";
			$_stmt_array['arr_values'] = array();
			$_stmt_array['arr_values'][] = $PARAMETER['pwdhash'];
			$_stmt_array['arr_values'][] = $_SESSION['os_user'];
			_execute_stmt($_stmt_array,$conn);
			unset($_stmt_array); $_stmt_array = array();
			$_stmt_array['stmt'] = "UPDATE os_passwords SET password=?, salt=?, nonce=? WHERE userid = ?";
			$_stmt_array['str_types'] = "sssi";
			$_stmt_array['arr_values'] = array();
			$_stmt_array['arr_values'][] = sodium_bin2hex($passwd);
			$_stmt_array['arr_values'][] = sodium_bin2hex($salt);
			$_stmt_array['arr_values'][] = sodium_bin2hex($nonce);
			$_stmt_array['arr_values'][] = $_SESSION['os_user'];
			_execute_stmt($_stmt_array,$conn);
			unset($PARAMETER);
			logout();
		}
		session_unset();
	};
	unset($PARAMETER);
} 

$conn->close();
?>

<!DOCTYPE HTML>
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="de-DE">
<head profile="http://gmpg.org/xfn/11">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta http-equiv="Content-Script-Type" content="text/javascript"/>
	<meta name="language" content="de-DE" />
	<meta name="content-language" content="de-DE" />
	<meta name="description" content="openStat v<?php echo($versionnumber); ?>">
	<title>openStat Login</title>
	<link rel="icon" type="image/x-icon" href="favicon.ico"/>
	<link rel="stylesheet" type="text/css" media="screen, projection" href="/css/login_colors.css" />
	<link rel="stylesheet" type="text/css" media="screen, projection" href="/css/login.css" />
	<link rel="stylesheet" type="text/css" media="screen, projection, print" href="/plugins/fontawesome/css/all.css" />
</head>
<body>
	<div hidden id="generator"></div>
	<div id="wrapper">
		<a href="/"><div id="logo"></div></a>
		<div id="title"><img src="/img/openStat.svg"><span style="font-size: 0.8rem">openStat</span><br /><i class="fas fa-user-lock"></i> newPass</div>	
		<div id="countDown"></div>
		<div id="title_after"></div>
		<div id="actionneeded">
			<?php 
				if ( isset($_SESSION['e']) AND $_GET['e'] == "noextension" ) { ?>
					<p>Bitte aktiviere die <b>openStat</b>-Erweiterung für Firefox.</p>
					<p>Wenn Du sie noch nicht installiert hast, kannst Du das hier tun:</p>
					<p><a href="https://addons.mozilla.org/addon/openstat/">https://addons.mozilla.org/addon/openstat/</a></p>
					<p>und <b>erlaube die Aktivierung der Erweiterung in privaten Fenstern</b>.</p>
				<?php };
				if ( isset($_SESSION['e']) AND $_GET['e'] == "extensionupdate" ) { ?>
					<p>Bitte aktualisiere die <b>openStat</b>-Erweiterung für Firefox:</p>
					<p><a href="https://addons.mozilla.org/addon/openstat/">https://addons.mozilla.org/addon/openstat/</a></p>
					<p>und <b>erlaube die Aktivierung der Erweiterung in privaten Fenstern</b>.</p>
				<?php };
				if ( isset($_SESSION['e']) AND $_GET['e'] == "notprivate" ) { ?>
					Bitte öffne <b>openStat</b> in einem privaten Fenster (Strg+Umschalt+P).
				<?php };
			 ?>
		</div>
		<div id="error">
			<?php 
				if ( isset($_success['error']) ) { echo($_success['error']); };
			 ?>
		</div>
		<form action="" method="post" onsubmit="if ( ! passwordsMatch(this) ){ return false; }">
			<label for="user" title="Benutzername"><i class="fas fa-user"></i></label>
			<input id="user" type="text" name="user" value="<?php echo($_GET['u']); ?>" readonly required <?php echo($disabled); ?>><br /><br />
			<label for="pwd" title="Aktuelles Passwort"><i class="fas fa-key"></i></label>
			<input id="pwd" type="password" name="password" required <?php echo($disabled); ?>><br /><br />
			<label for="pwdnew" title="Neues Passwort (mind. 8 Stellen; Buchstaben und Zahlen; Sonderzeichen optional)" class="white"><i class="fas fa-key"></i></label>
			<input id="pwdnew" type="password" name="passwordnew" required <?php echo($disabled); ?>><br /><br />
			<label for="pwdrepeat" title="Neues Passwort wiederholen" class="white"><i class="fas fa-key"></i></label>
			<input id="pwdrepeat" type="password" required <?php echo($disabled); ?>><br />
			<input id="test" type="submit" hidden <?php echo($disabled); ?>><br /><br />
<!--			<input id="test" type="checkbox" hidden onclick="if ( passwordsMatch(this) ){ this.closest('form').submit(); };" <?php echo($disabled); ?>><br /><br /> -->
			<label for="test"><i class="fas fa-arrow-right"></i></label>
		</form>
	</div>
	<script>
		<?php if ( ! isset($_SESSION['e']) ) { 
		?>
		setTimeout(function () {
			switch(document.getElementById('generator').innerText) {
				case '':
					window.location = window.location+'?e=noextension';
					break;
				case '<?php echo($_ext); ?>':
					break;
				default:
					window.location = window.location+'?e=extensionupdate';
					break;
			}
		},500);
		<?php }; ?>
		function passwordsMatch(el) {
			pwd1 = document.getElementById('pwdnew').value;
			pwd2 = document.getElementById('pwdrepeat').value;
			if ( pwd1 == pwd2 ) { 
				document.getElementById('error').innerText = '';
				return true; }
			else {
				document.getElementById('error').innerText = "Die neuen Passwörter stimmen nicht überein.";
				return false;
			}
		}
		function countdown(start) {
			counter = setInterval(function(){
				start=start-1;
				if ( start > 0 ) { 
					document.getElementById('countDown').innerText = Math.floor(start/60) + "min " + start%60 + "s";
				} else {
					document.getElementById('countDown').innerText = "";
					clearInterval(counter);
				} 
			},1000);
		}
		countdown(<?php echo(max(array($expiry - time(),0))); ?>);
	</script>
</body>
