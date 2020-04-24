<?php
require_once('../../core/functions/db_functions.php');
require_once('../../core/classes/auth.php');
require_once('../../core/data/info.php');
$PARAMETER = array(); 
$PARAMETER['server'] = '';
$PARAMETER['user'] = '';
$PARAMETER['password'] = '';
$PARAMETER['database'] = '';

$action = '';
$disabled = '';

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

if ( isset($_GET['e']) ) { $disabled = "disabled"; }

// Create connection
$conn = new mysqli($PARAMETER['server'], $PARAMETER['user'], $PARAMETER['password'], $PARAMETER['database']) or die ("Connection failed.");
if ( ! $conn->connect_errno ) { 
	$conn->close();
	session_start();
	$_SESSION = $PARAMETER;
	header('Location:/html/create_structure.php');
} else { logout(''); }

//rerun 'e'-Tests if loaded for the second time: reload without parameters every second time
//does not work in admin.php because the session has been destroyed in between
/*if ( isset($PARAMETER['e']) ) { 
	if ( isset($_SESSION['e']) ) { $_SESSION['e'] = ! $_SESSION['e']; } else { $_SESSION['e'] = true; }
	unset($PARAMETER['e']);	
} else { unset($_SESSION['e']); };

if ( isset($_SESSION['e']) AND ! $_SESSION['e'] ) { header('Refresh:0; url=/html/admin.php'); };
if ( isset($_SESSION['e']) ) { $disabled = "disabled"; }
*/

//mysqli_set_charset($conn,"utf8");

//extension file (with version)
$_ext = scandir('../xpi',SCANDIR_SORT_DESCENDING)[0];

?>

<!DOCTYPE HTML>
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="de-DE">
<head profile="http://gmpg.org/xfn/11">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta http-equiv="Content-Script-Type" content="text/javascript"/>
	<meta name="language" content="de-DE" />
	<meta name="content-language" content="de-DE" />
	<meta name="description" content="openStat v<?php echo($versionnumber); ?>">
	<title>openStat Admin</title>
	<link rel="stylesheet" type="text/css" media="screen, projection" href="/css/login_colors.css" />
	<link rel="stylesheet" type="text/css" media="screen, projection" href="/css/login.css" />
	<link rel="stylesheet" type="text/css" media="screen, projection, print" href="/plugins/fontawesome/css/all.css" />
</head>
<body>
	<div hidden id="generator"></div>
	<div id="wrapper">
		<a href="/"><div id="logo"></div></a>
		<div id="title">openStatAdmin</div>
		<div id="title_after"></div>
		<div id="error">
			<?php 
				if ( isset($_success['error']) ) { echo($_success['error']); }; 
				if ( isset($_GET['e']) AND $_GET['e'] == "noextension" ) { ?>
					<p>Bitte aktiviere die <b>openStat</b>-Erweiterung für Firefox.</p>
					<p>Wenn Du sie noch nicht installiert hast, kannst Du das hier tun:</p>
					<p><a href="https://addons.mozilla.org/addon/openstat/">https://addons.mozilla.org/addon/openstat/</a></p>
					<p>und <b>erlaube die Aktivierung der Erweiterung in privaten Fenstern</b>.</p>
					<p>Benutze bitte den "Zurück"-Button des Browsers, um die Seite danach neu zu laden.</p>
				<?php };
				if ( isset($_GET['e']) AND $_GET['e'] == "extensionupdate" ) { ?>
					<p>Bitte aktualisiere die <b>openStat</b>-Erweiterung für Firefox:</p>
					<p><a href="https://addons.mozilla.org/addon/openstat/">https://addons.mozilla.org/addon/openstat/</a></p>
					<p>und <b>erlaube die Aktivierung der Erweiterung in privaten Fenstern</b>.</p>
				<?php };
				if ( isset($_GET['e']) AND $_GET['e'] == "notprivate" ) { ?>
					Bitte öffne das Administrationspanel (ohne Parameter) in einem privaten Fenster (Strg+Umschalt+P). 
				<?php };
			?>
		</div>
		<form action="" method="post">
			<label for="server"><i class="fas fa-server"></i></label>
			<input id="server" type="text" name="server" required <?php echo($disabled); ?>><br /><br />
			<label for="database"><i class="fas fa-database"></i></label>
			<input id="database" type="text" name="database" required <?php echo($disabled); ?>><br /><br />
			<label for="user"><i class="fas fa-user"></i></label>
			<input id="user" type="text" name="user" required <?php echo($disabled); ?>><br /><br />
			<label for="pwd"><i class="fas fa-key"></i></label>
			<input id="pwd" type="password" name="password" required <?php echo($disabled); ?>><br />
			<input id="test" type="checkbox" hidden onclick="this.closest('form').submit();" <?php echo($disabled); ?>><br /><br />
			<label for="test"><i class="fas fa-arrow-right"></i></label>
		</form>
	</div>
	<script>
		<?php if ( ! isset($_SESSION['e']) ) { 
		?>
		setTimeout(function () {
			switch(document.getElementById('generator').innerText) {
				case '':
					if (window.location.toString().indexOf('?e=') == -1 ) { window.location = window.location+'?e=noextension'; };
					break;
				case '<?php echo($_ext); ?>':
					break;
				default:
					if (window.location.toString().indexOf('?e=') == -1 )  { window.location = window.location+'?e=extensionupdate'; };
					break;
			}
		},500);
		<?php }; ?>
	</script>
</body>
