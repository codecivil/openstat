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
require_once('../../core/data/filedata.php');
require_once('../../core/data/info.php');

/*
require_once('../../core/auth.php');
require_once('../../core/edit.php');
require_once('../../core/db_functions.php');
require_once('../../core/frontend_functions.php');
require_once('../../core/getParameters.php');
*/

if ( isset($PARAMETER['submit']) AND $PARAMETER['submit'] == 'logout' ) { logout(); }
if ( isset($PARAMETER['submit']) AND $PARAMETER['submit'] == 'changePassword' ) { 
	$vtime = floor(time()/300);
	$v = hash("sha512", $vtime.$_SESSION['os_user'].$_SESSION['os_dbpwd'], false);
	$u = $_SESSION['os_username'];
	logout('html/changePassword.php?v='.$v.'&u='.$u);
}
if ( isset($PARAMETER['submit']) AND $PARAMETER['submit'] != 'logout' AND $PARAMETER['submit'] != 'changePassword' ) { logout('login.php?e='.$PARAMETER['submit']); }
/*if ( isset($PARAMETER['submit']) AND $PARAMETER['submit'] == 'noextension' ) { logout('login.php?e=noextension'); }
if ( isset($PARAMETER['submit']) AND $PARAMETER['submit'] == 'notprivate' ) { logout('login.php?e=notprivate'); }
if ( isset($PARAMETER['submit']) AND $PARAMETER['submit'] == 'extensionupdate' ) { logout('login.php?e=extensionupdate'); }
*/
if ( ! isset($PARAMETER['table']) ) { $PARAMETER['table'] = 'os_all'; };
$table = $PARAMETER['table'];

$username = $_SESSION['os_rolename'];
$password = $_SESSION['os_dbpwd'];

//get changelog
$changelog_array = explode('======',file_get_contents('../../changelog'),3);
$changelog = $changelog_array[0]."======".$changelog_array[1];
$olderchangelog = $changelog_array[2];	
//extension file (with version)
$_ext = scandir('../xpi',SCANDIR_SORT_DESCENDING)[0];

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname) or die ("Connection failed.");
mysqli_set_charset($conn,"utf8");

//get user config
$_config = getConfig($conn);
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="de-DE">
	<head profile="http://gmpg.org/xfn/11">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta http-equiv="Content-Script-Type" content="text/javascript"/>
	<meta name="language" content="de-DE" />
	<meta name="content-language" content="de-DE" />
	<meta http-equiv="imagetoolbar" content="no" />
	<meta name="author" content="<?php echo($author); ?>">
	<meta name="description" content="openStat v<?php echo($versionnumber); ?>">
	<title>openStat</title>
	<link rel="stylesheet" type="text/css" media="screen, projection" href="/css/fontsize_<?php echo($_config['_fontSize']); ?>.css" id="cssFontSize" />
	<link rel="stylesheet" type="text/css" media="screen, projection" href="/css/config_colors_<?php echo($_config['_colors']); ?>.css" id="cssColors" />
	<link rel="stylesheet" type="text/css" media="screen, projection, print" href="/css/main.css" />
	<link rel="stylesheet" type="text/css" media="screen, projection, print" href="/plugins/fontawesome/css/all.css" />
	<script type="text/javascript" src="/js/main.js"></script>
	<script type="text/javascript" src="/js/os.js"></script>
	<script type="text/javascript" src="/js/import.js"></script>
	<script type="text/javascript" src="/plugins/tinymce/js/tinymce.js"></script>
	<script type="text/javascript" src="/plugins/tinymce/js/os_tinymce.js"></script>
</head>
<body onload="setTimeout( function () { callFunction(document.getElementById('formFilters'),'applyFilters','results_wrapper'); } ,1500);">
<div hidden id="generator"></div>
<div id="statusbar">
	<div id="logo">
		<img id="customer_logo" src='/img/logo.png' />
	</div>
	<div id="usermenu">
		<div id="user">
			<div id="logout" title="Abmelden">
				<form method="post" id="logoutForm">
					<input type="submit" name="submit" value="logout" id="logoutBtn" hidden />
					<label for="logoutBtn"> 
						<i class="fas fa-power-off"></i>
					</label>
				</form>
			</div>
			<div id="loggedin">
				<form method="POST" id="usernameForm" onsubmit="callFunction(this,'changeUserName','',false,'','changeUserName',''); return false;" class="inline">
					<input id="userName" name="userName" type="text" value="<?php echo($_SESSION['os_username']); ?>" title="Benutzernamen ändern">
				</form>
				als <b><?php
				if ( $_SESSION['os_parent'] > 0 ) {
					echo($_SESSION['os_parentname']); 
				} else {
					echo($_SESSION['os_rolename']); 				
				} ?>
				</b>
			</div>
			<div id="changePassword" title="Passwort ändern (mit automatischer Abmeldung!)">
				<form method="post" id="changePasswordForm">
					<input type="submit" name="submit" value="changePassword" id="changePwdBtn" hidden /> 
					<label for="changePwdBtn" class="submit">
						<i class="fas fa-key"></i>
					</label>
				</form>				
			</div>
		</div>
		<div id="fontsize">
			<form method="post" id="fontsizeForm" onchange="callFunction(this,'changeConfig','',false,'','restrictResultWidth'); reloadCSS();">
				<input type="radio" name="_fontSize" value="10" id="fs10" hidden <?php if ( $_config['_fontSize'] == "10") { ?>checked<?php }?> >
				<label for="fs10"><i class="fas fa-font" style="font-size: 0.8rem;"></i></label>
				<input type="radio" name="_fontSize" value="12" id="fs12" hidden <?php if ( $_config['_fontSize'] == "12") { ?>checked<?php }?> >
				<label for="fs12"><i class="fas fa-font" style="font-size: 0.9rem;"></i></label>
				<input type="radio" name="_fontSize" value="14" id="fs14" hidden <?php if ( $_config['_fontSize'] == "14") { ?>checked<?php }?> >
				<label for="fs14"><i class="fas fa-font" style="font-size: 1rem;"></i></label>
				<input type="radio" name="_fontSize" value="16" id="fs16" hidden <?php if ( $_config['_fontSize'] == "16") { ?>checked<?php }?> >
				<label for="fs16"><i class="fas fa-font" style="font-size: 1.1rem;"></i></label>
				<input type="radio" name="_fontSize" value="18" id="fs18" hidden <?php if ( $_config['_fontSize'] == "18") { ?>checked<?php }?> >
				<label for="fs18"><i class="fas fa-font" style="font-size: 1.2rem;"></i></label>
			</form>
		</div>
		<div id="colors">
			<form method="post" id="colorsForm" onchange="callFunction(this,'changeConfig'); reloadCSS();">
				<legend><i class="fas fa-palette"></i></legend>
				<select id="colorsSelect" name="_colors">
					<?php
					$_colors = glob('../css/config_colors_*.css');
					foreach ( $_colors as $_color ) {
						$colorname = str_replace('.css','',str_replace('../css/config_colors_','',$_color));
					?>
						<option value="<?php echo($colorname); ?>" <?php if ( $_config['_colors'] == $colorname) { ?>selected<?php }?>><?php echo($colorname); ?></option>
					<?php
					}
					?>
				</select>
			</form>
		</div>
		<div id="info">
			<form id="opszInfoForm"></form>
			<label for="opszInfo">&nbsp;<i class="fas fa-info-circle"></i>&nbsp;</label>
			<input form="opszInfoForm" type="checkbox" hidden id="opszInfo">
			<div>
				<b>Letztes Update:</b>: <?php html_echo($versiondate); ?> <label for="wasistneu" class="whatsnew" onclick="document.getElementById('wasistneu_wrapper').scrollIntoView()">Was ist neu?</label><br />
				<b>Version:</b> <?php html_echo($versionnumber); ?><br />
				<b>Autor:</b> <?php html_echo($author); ?><br />
				<b>Lizenz:</b> <?php html_echo($license); ?><br />
			</div>
		</div>
	</div> 
</div>
<div class="clear"></div>
<div id="important"></div>
<div id="alsoimportant"></div>
<div id="wasistneu_wrapper">
	<form id="whatsNewForm">
		<input type="checkbox" id="wasistneu" hidden>
		<div id="wasistneudiv"><h1>Was ist neu in...</h1><pre><?php html_echo($changelog); ?>...</pre></div>
		<input type="checkbox" id="waswarneu" hidden>
		<label for="waswarneu" onclick="setTimeout(function(){document.getElementById('wasistneu_wrapper').scrollIntoView();},100)"><i class="fas fa-chevron-right"></i></label>
		<label for="waswarneu"><i class="fas fa-chevron-down"></i></label>
		<div id="waswarneudiv"><pre><?php html_echo($olderchangelog); ?></pre></div>
	</form>
</div>
<div id="wrapper">
		<div id="showHistory" title="Chronik">
		<div class="inline"><i class="fas fa-history"></i></div>
		<div id="showHistoryBack" class="inline disabled" hidden onclick="restoreHistory(-1);" title="zurück"><i class="fas fa-chevron-left"></i></div>				
		<div id="showHistory11" class="hidden" hidden onclick="restoreHistory(11);">&bull;</div>	
		<div id="showHistory10" class="hidden" hidden onclick="restoreHistory(10);">&bull;</div>	
		<div id="showHistory9" class="hidden" hidden onclick="restoreHistory(9);">&bull;</div>	
		<div id="showHistory8" class="hidden" hidden onclick="restoreHistory(8);">&bull;</div>	
		<div id="showHistory7" class="hidden" hidden onclick="restoreHistory(7);">&bull;</div>	
		<div id="showHistory6" class="hidden" hidden onclick="restoreHistory(6);">&bull;</div>	
		<div id="showHistory5" class="hidden" hidden onclick="restoreHistory(5);">&bull;</div>	
		<div id="showHistory4" class="hidden" hidden onclick="restoreHistory(4);">&bull;</div>	
		<div id="showHistory3" class="hidden" hidden onclick="restoreHistory(3);">&bull;</div>	
		<div id="showHistory2" class="hidden" hidden onclick="restoreHistory(2);">&bull;</div>	
		<div id="showHistory1" class="hidden" hidden onclick="restoreHistory(1);">&bull;</div>	
		<div id="showHistoryForward" class="inline disabled" hidden onclick="restoreHistory(0);" title="vor"><i class="fas fa-chevron-right"></i></div>				
		</div>
<div id="sidebar">
	<div id="config" class="section">
		<form id="formChooseConfig" class="noform" method="post" action="" onsubmit="callFunction(this,'copyConfig').then(()=>callFunction('_','updateSidebarCustom','sidebar')).then(()=>{ return false; });" >
		<?php //save button and load input like in openStat.plan explained ?>
			<label for="config_save" class="<?php echo($config_save_class); ?>" title="Konfiguration speichern"><i class="fas fa-save"></i></label>
			<input hidden type="submit" id="config_save">
			<label for="config_load" title="Konfiguration laden"><i class="fas fa-clipboard-check"></i></label>
			<input hidden type="button" id="config_load" onclick="callFunction(this.closest('form'),'changeConfig').then(()=>callFunction('_','updateSidebarCustom','sidebar')).then(()=>{ return false; });">
			<label for="config_remove" title="Konfiguration löschen"><i class="fas fa-trash-alt"></i></label>
			<input hidden type="button" id="config_remove" onclick="_onAction('delete',this.closest('form'),'removeConfig'); document.getElementById('db__config__text').value = 'Default'; document.getElementById('db__config__list').value = 'Default'; callFunction(this.closest('form'),'changeConfig').then(()=>callFunction('_','updateSidebarCustom','sidebar')).then(()=>{ return false; });">
			<div class="unite">
				<label for="db__config__list"></label>
				<input type="text" id="db__config__text" name="configname" class="db_formbox" value="" autofocus disabled hidden>
				<select id="db__config__list" name="configname" class="db_formbox" onchange="callFunction(this.closest('form'),'changeConfig').then(()=>callFunction('_','updateSidebarCustom','sidebar')).then(()=>{ return false; });">
				<!--	<option value="none"></option> -->
					<?php foreach ( $options as $value ) { 
						$_sel = '';
						if ( isset($_config_array['configname']) AND $_config_array['configname'] == $value ) { $_sel = 'selected'; };
						?>				
						<option value="<?php echo($value); ?>" <?php echo($_sel); ?> ><?php echo($value); ?></option>
					<?php } ?>
				</select>
				<label class="toggler" for="minus_config">&nbsp;<i class="fas fa-arrows-alt-h"></i></label>
				<input id="minus_config" class="minus" type="button" value="+" onclick="_toggleOption('_config_')" title="Erlaubt die Eingabe eines neuen Wertes" hidden>
			</div>
			<div class="clear"></div>
		</form>
	</div>
	<div id="filters">
		<div id="addfilters">
			<label for="toggleAddFilter"><h1 class="center"><i class="fas fa-plus"></i></h1></label>
			<input type="checkbox" hidden id="toggleAddFilter" class="toggle">
			<form id="formAddFilters" class="form" method="post" action="../php/addFilters.php" onsubmit="return addFilters(this);">
					<input type="submit" value="Auswählen" >
				<?php
					unset($_stmt_array); $_stmt_array = array(); $key_array = array();
					$_stmt_array['stmt'] = "SELECT keymachine,keyreadable FROM ".$table."_permissions";
					$_result_array = execute_stmt($_stmt_array,$conn,true); //keynames as last array field 
					if ($_result_array['dbMessageGood']) { $key_array = $_result_array['result']; };
					foreach ( $key_array as $key ) 
					{ 
						if ( ! array_key_exists($key['keymachine'],$_config) ) 
						{ ?> 
						<input 
							name="<?php html_echo($key['keymachine']); ?>" 
							id="add_<?php html_echo($key['keymachine']); ?>" 
							type="checkbox" 
							value="add"
						/>
						<label for="add_<?php html_echo($key['keymachine']); ?>"><?php html_echo(explode(': ',$key['keyreadable'])[0]); ?></label><br>
						<?php }
					}
					unset($key);
				?>
			</form>
		</div>
	</div>
</div>
</div>
<div hidden id="history"><div hidden id="history_level">1</div></div>
<div id="results_wrapper" class="popup">
	<?php includeFunctions('RESULTS',$conn); ?>
<!--	<div class="headline"><h1></h1></div> -->
</div>
<div class="popup_wrapper hidden"></div>
<form method="post" id="trashForm" class="trash" onsubmit="return false;">
	<input 
		type="text" 
		name="trash"
		id="trash"
		hidden
	/>
</form>

<script>
	function restrictResultWidth () {
		setTimeout(function () { document.getElementById('results_wrapper').style.maxWidth = document.body.offsetWidth - document.getElementById('sidebar').offsetWidth - 5*parseFloat(getComputedStyle(document.documentElement).fontSize) + "px"; }, 500);
	}
	setTimeout(function () {
		switch(document.getElementById('generator').innerText) {
			case '':
				window.location = window.location+'?submit=noextension';
				break;
			case '<?php echo($_ext); ?>':
				break;
			default:
				window.location = window.location+'?submit=extensionupdate';
				break;
		}
	},500);
	setTimeout(function () {
		callFunction('_','updateSidebar','sidebar',false,'','restrictResultWidth').then(()=>{
	//	processForm(document.getElementById('formAddFilters'),'../php/updateSidebar.php','sidebar');
		<?php 
			unset($value);
			if ( isset($_config['_openids']) )
			{
				foreach ( $_config['_openids'] as $value ) { 
					if ( isset($value) ) {
					?>
					callJSFunction('<?php echo(json_encode($value)); ?>',openIds);
				<?php } 
				}
			}  ?>
		})
	},1000);
</script> 
</body>
</html>

