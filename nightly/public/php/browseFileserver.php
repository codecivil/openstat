<?php
//error_reporting(0); //just in case the webserver does not comply...

session_start();
if ( ! isset($_SESSION['os_user']) ) { header('Location:/login.php'); exit(); } //redirect to login page if not logged in

require_once('../../core/data/filedata.php');
require_once('../../core/data/serverdata.php');

//include system functions
$core = glob('../../core/functions/*.php');

foreach ( $core as $component )
{
	require_once($component);
}

$username = $_SESSION['os_rolename'];
$password = $_SESSION['os_dbpwd'];

try {
	$conn = new mysqli($servername, $username, $password, $dbname); 
} catch(Exception $e) {
	exit;
}
mysqli_set_charset($conn,"utf8");

//get user's fileroot
$_config = getConfig($conn);
$conn->close();

if ( isset($_config['fileroot']) ) { $_fileroot = $_config['fileroot']; } else { $_fileroot = ''; }
if ( isset($_fileroot) AND $_fileroot != '' ) { $fileroot .= '/'.$_fileroot; }
//scan fileserverdir, go one level deeper with every clicked dir, select file at last
//save current path in div?
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="de-DE">
	<head profile="http://gmpg.org/xfn/11">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta http-equiv="Content-Script-Type" content="text/javascript"/>
	<meta name="language" content="de-DE" />
	<meta name="content-language" content="de-DE" />
	<meta http-equiv="imagetoolbar" content="no" />
	<link rel="stylesheet" type="text/css" media="screen, projection" href="/css/fontsize_<?php echo($_config['_fontSize']); ?>.css" id="cssFontSize" />
	<link rel="stylesheet" type="text/css" media="screen, projection" href="/css/config_colors_<?php echo($_config['_colors']); ?>.css" id="cssColors" />
	<link rel="stylesheet" type="text/css" media="screen, projection, print" href="/css/main.css" />
	<link rel="stylesheet" type="text/css" media="screen, projection, print" href="/css/frame.css" />
	<link rel="stylesheet" type="text/css" media="screen, projection, print" href="/plugins/fontawesome/css/all.css" />
	<script type="text/javascript" src="/js/main.js"></script>
	<script type="text/javascript" src="/js/os.js"></script>
	<script type="text/javascript" src="/js/frame.js"></script>
</head>
<body>
	<?php
		$slash = '/';
		if ( ! isset($_POST['path']) ) { $PATH = ''; } else { $PATH = $_POST['path']; }; 
		if ( is_dir($fileroot.'/'.$PATH) ) { $options = scandir($fileroot.'/'.$PATH); } else { $options = array('.','..'); $slash = ''; }
		?>
		<form method="POST">
			<label class="unlimitedWidth filelink" id="label" for="path"><?php echo($PATH.$slash); ?></label>
			<input type="text" id="fullpath" name="path" onchange="adaptOptions(this);" autofocus>
			<select id="path" class="db_formbox" action="" onchange="adaptPath(this);">
			<!--	<option value="none"></option> -->
				<?php foreach ( $options as $value ) { 
					if ( $value == '..' ) { $value = "[zurück]"; }
					if ( $value == '.' ) { if ( sizeof($options) > 2 ) { $value = "[bitte wählen]"; } else { $value = ''; }; }
					?>				
					<option value="<?php echo($value); ?>"><?php echo($value); ?></option>
				<?php } ?>
			</select>
		</form>
</body>
