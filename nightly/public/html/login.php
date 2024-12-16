<?php 
//error_reporting(0); //just in case the webserver does not comply...
session_start();
require_once('../../core/functions/db_functions.php');
require_once('../../core/classes/auth.php');
require_once('../../core/data/serverdata.php');
require_once('../../core/data/logindata.php');
require_once('../../core/data/info.php');
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
try {
	$conn = new mysqli($servername, $username, $password, $dbname); 
} catch(Exception $e) {
	print_r($e);
	exit;
}
mysqli_set_charset($conn,"utf8");
//login

if ( isset($PARAMETER['user']) AND isset($PARAMETER['password']) AND $PARAMETER['user'] != '' ) {
	$_login = new OpenStatAuth($PARAMETER['user'],$PARAMETER['password'],$conn);
	$_success = $_login->login();
	if ( ! isset($_success['error']) ) { header('Refresh:0; url=/index.php'); $conn->close(); exit(); };
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
	<link rel="stylesheet" type="text/css" media="screen, projection" href="/css/login_colors_<?php echo(date('z')); ?>.css" />
	<link rel="stylesheet" type="text/css" media="screen, projection" href="/css/login.css" />
	<link rel="stylesheet" type="text/css" media="screen, projection, print" href="/plugins/fontawesome/css/all.css" />
</head>
<body>
	<div hidden id="generator"></div>
	<div id="wrapper">
		<div id="logo"></div>
		<div id="title"><img src="/img/openStat.svg">openStat</div>
		<div id="title_after"></div>
        <script>
            if ( _browserversion = parseInt(window.navigator.userAgent.replace(/.*Firefox\//,'').replace(/\..*/,'')) ) {
                _browser = "Firefox";
                _extensionlink = "https://addons.mozilla.org/addon/openstat/";
            }
            if ( ! _browserversion && ( _browserversion = parseInt(window.navigator.userAgent.replace(/.*Chrome\//,'').replace(/\..*/,'')) ) ) {
                _browser = "Chrome";
                _extensionlink = "https://chromewebstore.google.com/detail/openstat/mfoblahoofibcaebhefdchnfnojiphjb";
            }
            if ( ! _browserversion ) { _browser = "Unsupported"; }        
        </script>
		<div id="actionneeded">
			<?php 
				if ( isset($_SESSION['e']) AND $_GET['e'] == "noextension" ) { ?>
					<p>Bitte aktiviere die <b>openStat</b>-Erweiterung.</p>
					<p>Wenn Du sie noch nicht installiert hast, kannst Du das hier tun:</p>
					<p><a class="extensionlink"></a></p>
					<p>und <b>erlaube die Aktivierung der Erweiterung in privaten Fenstern</b>.</p>
				<?php };
				if ( isset($_SESSION['e']) AND $_GET['e'] == "extensionupdate" ) { ?>
					<p>Bitte aktualisiere die <b>openStat</b>-Erweiterung:</p>
					<p><a class="extensionlink"></a></p>
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
		<form action="" method="post">
			<label for="user"><i class="fas fa-user"></i></label>
			<input id="user" type="text" name="user" autofocus required <?php echo($disabled); ?>><br /><br />
			<label for="pwd"><i class="fas fa-key"></i></label>
			<input id="pwd" type="password" name="password" required <?php echo($disabled); ?>><br />
			<input id="test" type="submit" hidden <?php echo($disabled); ?>><br /><br />
<!--			<input id="test" type="checkbox" hidden onclick="this.closest('form').submit();" <?php echo($disabled); ?>><br /><br /> -->
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
        if ( _browser == "Unsupported" ) {
            document.querySelector('#actionneeded').innerHTML += '<p>Dies scheint kein unterstützter Browser zu sein. Bitte benutze Firefox.</p>';
            document.querySelectorAll('.extensionlink').forEach(_link => { _link.textContent = "Bitte installiere openStat aus dem passenden Erweiterungsstore."; } );
        } else {
            document.querySelectorAll('.extensionlink').forEach(_link => { _link.textContent = _extensionlink; _link.href = _extensionlink; } );
        }
        if ( 
            ( _browser == "Firefox" && <?php echo($firefox_least_featureversion); ?> > _browserversion ) ||
            ( _browser == "Chrome" && <?php echo($chrome_least_version); ?> > _browserversion )           
        ) {
            document.querySelector('#actionneeded').innerHTML += '<p>Diese Browserversion ist zu alt. Es kann sein, dass manche Funktionen nicht verfügbar sind oder fehlerhaft reagieren. Bitte aktualisiere Deinen Browser.</p>';
        }
	</script>
</body>
