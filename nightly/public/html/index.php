<?php 
//error_reporting(0); //just in case the webserver does not comply...
//start session
session_start();
//Debug on/off
//$_SESSION['DEBUG'] = true;

//mysqli throws errors
mysqli_report(MYSQLI_REPORT_STRICT);

if ( ! isset($_SESSION['os_user']) OR ! isset($_SESSION['os_dbpwd']) ) { header('Location:/login.php'); exit(); } //redirect to login page if not logged in

// set openids as SESSION variable for bookkeeping
$_SESSION['os_opennow'] = array();

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
require_once('../../core/data/debugdata.php');
require_once('../../settings.php');

//include vendor info data
$core = glob('../../vendor/data/*.php');

foreach ( $core as $component )
{
	require_once($component);
}
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

//extension file (with version)
$_ext = scandir('../xpi',SCANDIR_SORT_DESCENDING)[0];

// Create connection
try {
	$conn = new mysqli($servername, $username, $password, $dbname); 
} catch(Exception $e) { 
	exit;
}
mysqli_set_charset($conn,"utf8");

//get user config
$_config = getConfig($conn);

//update version
$whatsnewclass = "changed";
if ( isset($_config['version']) AND $_config['version'] == $versionnumber ) { $whatsnewclass = ""; $oldversion = $_config['version']; }
changeConfig(array("version"=>$versionnumber),$conn);

//update and parse CSS choice
$_firsttimecss = false;
if ( ! isset($_config['css']) ) { changeConfig(array("css"=>""),$conn); $_config['css'] = ""; $_firsttimecss = true; }
if ( $_config['css'] == '_' ) { changeConfig(array("css"=>""),$conn); $_config['css'] = ""; }
if ( isset($PARAMETER['css']) ) { changeConfig(array("css"=>$PARAMETER['css']),$conn); $_config['css'] = $PARAMETER['css']; }
 
//get changelog
if ( isset($_config['version']) AND $_config['version'] != $versionnumber ) {
	$changelog_array = explode('v'.$_config['version'],file_get_contents('../../changelog_user'),2);
	$changelog = $changelog_array[0];
	$olderchangelog = 'v'.$_config['version'].PHP_EOL.$changelog_array[1];	
} else {
	$changelog_array = explode('======',file_get_contents('../../changelog_user'),3);
	$changelog = $changelog_array[0]."======".$changelog_array[1];
	$olderchangelog = $changelog_array[2];
}

//get login functions 2022-02-18
unset($_stmt_array); $_stmt_array = array();
$_stmt_array['stmt'] = "SELECT functionmachine,functionscope,allowed_roles FROM os_functions WHERE functionflags LIKE '%LOGIN%'";
$_result_array = execute_stmt($_stmt_array,$conn,true);
unset($_result);
$_result = $_result_array['result'];
$_loginfunctions = array();
foreach ( $_result as $_function )
{
	if ( in_array($_SESSION['os_role'],json_decode($_function['allowed_roles'])) OR in_array($_SESSION['os_parent'],json_decode($_function['allowed_roles'])) ) { 
		$_loginfunctions[] = array($_function['functionmachine'] => $_function['functionscope']);
	}
}

//collect filter stats
//to do: do it per table...
$_stmt_array = array();
$_stmt_array['stmt'] = "select tablemachine,keymachine,sum(filtercount) AS _count from os_userstats where userid=? group by tablemachine,keymachine order by _count desc";
$_stmt_array['str_types'] = "i";
$_stmt_array['arr_values'] = array($_SESSION['os_user']);
$_fullstats = execute_stmt($_stmt_array,$conn,true)['result'];
$_fullstats_array = array();
foreach ( $_fullstats as $_item ) {
	if ( ! isset($_fullstats_array[$_item['tablemachine']]) ) { $_fullstats_array[$_item['tablemachine']] = array(); }
	$_fullstats_array[$_item['tablemachine']][$_item['keymachine']] = $_item['_count'];
}
$_SESSION['filterstats'] = json_encode($_fullstats_array);

//get timestamp for forcing fresh ressource loading
$_v = time();
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
	<link rel="stylesheet" type="text/css" media="screen, projection" href="/css/fontsize_<?php echo($_config['_fontSize']); ?>.css?v=<?php echo($_v);?>" id="cssFontSize" />
	<link rel="stylesheet" type="text/css" media="screen, projection" href="/css/config_colors_<?php echo($_config['_colors']); ?>.css?v=<?php echo($_v);?>" id="cssColors" />
	<link rel="stylesheet" type="text/css" media="screen, projection, print" href="/css/main<?php echo($_config['css']); ?>.css?v=<?php echo($_v);?>" />
	<link rel="stylesheet" type="text/css" media="screen, projection, print" href="/css/config_columns_<?php echo(max(1,$_config['columns'])); ?>.css?v=<?php echo($_v);?>" id="cssColumns"/>
	<link rel="stylesheet" type="text/css" media="screen, projection, print" href="/plugins/fontcc/css/fcc1.css?v=<?php echo($_v);?>" />
	<link rel="stylesheet" type="text/css" media="screen, projection, print" href="/plugins/fontawesome/css/all.css?v=<?php echo($_v);?>" />
	<script type="text/javascript" src="/plugins/tinymce/js/tinymce.js?v=<?php echo($_v);?>"></script>
	<script type="text/javascript" src="/plugins/tinymce/js/os_tinymce.js?v=<?php echo($_v);?>"></script>
	<script type="text/javascript" src="/js/main.js?v=<?php echo($_v);?>"></script>
	<script type="text/javascript" src="/js/os.js?v=<?php echo($_v);?>"></script>
	<script type="text/javascript" src="/js/import.js?v=<?php echo($_v);?>"></script>
	<script type="text/javascript" src="/js/fieldfunctions.js?v=<?php echo($_v);?>"></script>
</head>
<body>
<div hidden id="generator"></div>
<div id="veil" class="veiled solid" oncontextmenu="return false"></div>
<!-- HelpMode Button must be topmost in content! -->
<input form="helpModeForm" name="helpmode" value="off" hidden>
<?php $_helpmode = ''; if ( isset($_config['helpmode']) AND $_config['helpmode'] == "on" ) { $_helpmode = "checked";} ?>
<input form="helpModeForm" type="checkbox" <?php echo($_helpmode); ?> hidden id="helpModeBtn" name="helpmode" class="helpMode" value="on" onchange="callFunction(document.getElementById('helpModeForm'),'changeConfig').then(()=>{ toggleHelpTexts(); return false; });" >
<!-- End HelpModeHack -->
<div id="loginfunctions" hidden>
	<?php html_echo(json_encode($_loginfunctions)); ?>
</div>
<div id="statusbar_handle"></div>
<div id="statusbar">
	<div id="logo">
		<img id="customer_logo" src='/img/logo.png' />
	</div>
	<div id="usermenu">
		<div id="user">
			<div id="lock" data-title="Bildschirm sperren">
				<form method="post" id="lockForm" onsubmit="callFunction(this,'lock','veil',false,'veiled').then(()=>{ return false; }); return false;">
					<input type="submit" name="submit" value="lock" id="lockBtn" hidden />
					<label for="lockBtn"> 
						<i class="fas fa-lock"></i>
					</label>
				</form>
			</div>
			<div id="logout" data-title="Abmelden">
				<form method="post" id="logoutForm" onsubmit="callFunction('_','saveFilterLog','').then( () => { sessionStorage.removeItem('recoverentries'); 	window.location = '?submit=logout'; return false; }); return false;">
					<input type="submit" name="submit" value="logout" id="logoutBtn" hidden />
					<label for="logoutBtn"> 
						<i class="fas fa-power-off"></i>
					</label>
				</form>
			</div>
			<div id="loggedin">
				<form  data-title="Klicken, um Benutzernamen zu ändern" method="POST" id="usernameForm" onsubmit="callFunction(this,'changeUserName','',false,'','changeUserName','').then(()=>{ return false; }); return false;" class="inline">
					<input id="userName" name="userName" type="text" value="<?php echo($_SESSION['os_username']); ?>">
				</form>
				als&nbsp; <b><?php
				if ( $_SESSION['os_parent'] > 0 ) {
					echo($_SESSION['os_parentname']); 
				} else {
					echo($_SESSION['os_rolename']); 				
				} ?>
				</b>
			</div>
			<div id="changePassword" data-title="Passwort ändern (mit automatischer Abmeldung!)">
				<form method="post" id="changePasswordForm">
					<input type="submit" name="submit" value="changePassword" id="changePwdBtn" hidden /> 
					<label for="changePwdBtn" class="submit">
						<i class="fas fa-key"></i>
					</label>
				</form>				
			</div>
		</div>
		<?php if ( sizeof($_SESSION['os_secret']) > 0 ) { ?> 
			<div id="authorizations" data-title="Zusätzliche Berechtigungen">
				<form id="authInfoForm"></form>
				<label for="authInfo"><i class="fas fa-id-badge"></i></label>
				<input form="authInfoForm" type="checkbox" hidden id="authInfo" class="userInfo">
				<div>
					<ul>
					<?php
						foreach ( $_SESSION['os_secret'] as $_secret ) {
							echo('<li data-title="'.$_secret['comment'].'">'.$_secret['name'].'</li>');
						}
					?>
					</ul>
				</div>
			</div>
		<?php } ?>
		<div id="display">
			<form id="osDisplayForm"></form>
			<label for="osDisplay" data-title="Anzeigeoptionen">&nbsp;<i class="fas fa-tv"></i>&nbsp;</label>
			<input form="osDisplayForm" type="checkbox" hidden id="osDisplay" class="userInfo">
			<div id="display_options">
				<div id="fontsize" data-title="Schriftgröße wählen">
					<form method="post" id="fontsizeForm" onchange="callFunction(this,'changeConfig','',false,'','restrictResultWidth').then(()=>{ reloadCSS(); });">
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
				<div id="colors" data-title="Farbschema wählen">
					<form method="post" class="statusForm" onchange="callFunction(this,'changeConfig').then(()=>{ reloadCSS(); }); ">
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
				<div id="columns" data-title="Spaltenanzahl">
					<form method="post" class="statusForm" onchange="callFunction(this,'changeConfig').then(()=>{ reloadCSS(); }); ">
						<legend><i class="fas fa-columns"></i></legend>
						<input id="columnsSelect" type="number" min=1 max=3 name="columns" value="<?php echo(max(1,$_config['columns'])); ?>">
					</form>
				</div>
				<div id="styles" data-title="Stil wählen">
					<form method="post" class="statusForm" onchange="callFunction(this,'changeConfig').then(()=>{ window.location = '/'; }); ">
						<legend><i class="fas fa-magic"></i></legend>
						<select id="stylesSelect" name="css">
							<option value="_" <?php if ( $_config['css'] == '') { ?>selected<?php }?>>default</option>
							<?php
							$_styles = glob('../css/main_*.css');
							foreach ( $_styles as $_style ) {
								$stylename = str_replace('.css','',str_replace('../css/main','',$_style));
							?>
								<option value="<?php echo($stylename); ?>" <?php if ( $_config['css'] == $stylename) { ?>selected<?php }?>><?php echo($stylename); ?></option>
							<?php
							}
							?>
						</select>
					</form>
				</div>
			</div>
		</div>
		<div id="info" class="<?php echo($whatsnewclass); ?>">
			<form id="osInfoForm"></form>
			<label for="osInfo">&nbsp;<i class="fas fa-info-circle" data-title="Informationen zur Software"></i>&nbsp;</label>
			<input form="osInfoForm" type="checkbox" hidden id="osInfo" class="userInfo">
			<div>
				<b>Letztes Update:</b>: <?php html_echo($versiondate); ?> <label for="wasistneu" class="whatsnew" onclick="myScrollIntoView(document.getElementById('wasistneu_wrapper'))">Was ist neu?</label><br />
				<b>Version:</b> <?php html_echo($versionnumber); ?><br />
				<b>Autor:</b> <i class="fcc fcc-codecivil-icon"></i><?php html_echo($author); ?><br />
				<?php if ( $contact != '' ) { 
					$_contactbefore = ''; $_contactafter = '';
					if ( preg_match('/[a-zA-Z0-9\.-_].*\@[a-zA-Z0-9\.-_].*/',$contact) ) {
						$_contactbefore = '<a href="mailto:'.$contact.'">'; $_contactafter = '</a>';
					}
				?>
				<b>Kontakt:</b> <?php echo($_contactbefore); html_echo($contact); echo($_contactafter); ?><br />
				<?php } ?>
				<b>Lizenz:</b> <?php html_echo($license); ?><br />
			</div>
		</div>
		<div id="helpmode" data-title="Nun erscheinen Hilfetexte">
			<form method="post" id="helpModeForm"></form>
			<label for="helpModeBtn" class="unlimitedWith">
				&nbsp;
				<i class="fas fa-question-circle"></i>
				<svg
				   xmlns="http://www.w3.org/2000/svg"
				   class="slider"
				   viewBox="0 0 129.5 69.779755"
				   version="1.1"
				  <metadata
					 id="metadata822">
					<rdf:RDF>
					  <cc:Work
						 rdf:about="">
						<dc:format>image/svg+xml</dc:format>
						<dc:type
						   rdf:resource="http://purl.org/dc/dcmitype/StillImage" />
						<dc:title></dc:title>
					  </cc:Work>
					</rdf:RDF>
				  </metadata>
				  <defs
					 id="defs2" />
				  <g
					 inkscape:label="Ebene 1"
					 inkscape:groupmode="layer"
					 id="layer1"
					 transform="translate(-47.130955,-87.863091)">
					<rect
					   style="opacity:0.5;vector-effect:none;fill-opacity:0.5;stroke-width:2.5;stroke-linecap:butt;stroke-linejoin:miter;stroke-miterlimit:4;stroke-dasharray:none;stroke-dashoffset:0;stroke-opacity:1"
					   class="sliderframe"
					   width="127"
					   height="67.279755"
					   x="48.380955"
					   y="89.113091"
					   ry="33.639877" />
					<ellipse
					   style="opacity:1;vector-effect:none;fill-opacity:1;stroke:none;stroke-width:0.03527778;stroke-linecap:butt;stroke-linejoin:miter;stroke-miterlimit:4;stroke-dasharray:none;stroke-dashoffset:0;stroke-opacity:1"
					   class="sliderbutton"
					   cy="122.95308"
					   rx="31.75"
					   ry="28.726189"
					/>
				  </g>
				</svg>
			</label>
			<div>
			</div>	
		</div>
		<div id="clipboard" data-title="Status der Zwischenablage (Leeren durch Anklicken)" onclick="emptyClipboard()"><label class="unlimitedWidth"><i class="fas fa-clipboard"></i></label></div>
		<div id="openEntries">
			<form class="statusForm" onclick="showOpenEntries(this.closest('div'))" onchange="myScrollIntoView(document.getElementById(this.querySelector('select').value))">
				<legend><i class="fas fa-list-ol" data-title="offene Einträge"></i></legend>
				<select></select>
			</form>
		</div>		
		<?php includeFunctions('GLOBAL',$conn); $conn->close(); //2021-07-15 ?>
		<form method="POST" class="function" hidden></form> <!--only for technical purposes: so that includeFunctions has a form to refer to -->
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
		<label for="waswarneu" onclick="setTimeout(function(){myScrollIntoView(document.getElementById('wasistneu_wrapper'));},100)"><i class="fas fa-chevron-right"></i></label>
		<label for="waswarneu"><i class="fas fa-chevron-down"></i></label>
		<div id="waswarneudiv"><pre><?php html_echo($olderchangelog); ?></pre></div>
	</form>
</div>
<div id="wrapper_handle"></div>
<div id="wrapper">
	<div id="showHistory" data-title="Chronik">
		<div class="inline"><i class="fas fa-history"></i></div>
		<div id="showHistoryBack" class="inline disabled" hidden onclick="restoreHistory(-1);" data-title="zurück"><i class="fas fa-chevron-left"></i></div>				
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
		<div id="showHistoryForward" class="inline disabled" hidden onclick="restoreHistory(0);" data-title="vor"><i class="fas fa-chevron-right"></i></div>				
	</div>
	<div id="sidebar">
		<div id="config" class="section">
			<form id="formChooseConfig" class="noform" method="post" action="" onsubmit="callFunction(this,'copyConfig').then(()=>callFunction('_','updateSidebarCustom','sidebar')).then(()=>{ return false; });" >
			<?php //save button and load input like in openStat.plan explained ?>
				<label for="config_save" class="disabled" data-title="Konfiguration speichern"><i class="fas fa-save"></i></label>
				<input hidden type="submit" id="config_save">
				<label for="config_load" data-title="Konfiguration laden"><i class="fas fa-clipboard-check"></i></label>
				<input hidden type="button" id="config_load" onclick="callFunction(this.closest('form'),'changeConfig').then(()=>callFunction('_','updateSidebarCustom','sidebar')).then(()=>{ return false; });">
				<label for="config_remove" data-title="Konfiguration löschen"><i class="fas fa-trash-alt"></i></label>
				<input hidden type="button" id="config_remove" onclick="_onAction('delete',this.closest('form'),'removeConfig'); document.getElementById('db__config__text').value = 'Default'; document.getElementById('db__config__list').value = 'Default'; callFunction(this.closest('form'),'changeConfig').then(()=>callFunction('_','updateSidebarCustom','sidebar')).then(()=>{ return false; });">
				<div class="unite">
					<label for="db__config__list"></label>
					<input type="text" id="db__config__text" name="configname" class="db_formbox" value="" autofocus disabled hidden>
					<select id="db__config__list" name="configname" class="db_formbox" onchange="callFunction(this.closest('form'),'changeConfig').then(()=>callFunction('_','updateSidebarCustom','sidebar')).then(()=>{ return false; });">
						<option value="none"></option>
					</select>
					<label class="toggler" for="minus_config">&nbsp;<i class="fas fa-arrows-alt-h"></i></label>
					<input id="minus_config" class="minus" type="button" value="+" onclick="_toggleOption('_config_')" data-title="Erlaubt die Eingabe eines neuen Wertes" hidden>
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
				</form>
			</div>
		</div>
	</div>
</div>
<div hidden id="history"><div hidden id="history_level">1</div></div>
<div id="results_wrapper" class="popup">
	<?php //includeFunctions('RESULTS',$conn); // $conn is already closed and this part is updated anyway by callFunction(...'applyFilters'...) below! ?>
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

<div id="FUNCTIONresults" hidden></div>

<script>
	async function standard500 () {
		var t0 = performance.now();
		for ( var _i = 0; _i < 500; _i++ ) {
			var testdiv = document.createElement('div');
			testdiv.innerText = "Test";
			testdiv.hidden = "true";
			testdiv.id = "testdiv";
			document.body.appendChild(testdiv);
			document.body.removeChild(document.querySelector('#testdiv'));
		}
		var t1 = performance.now();
//		var _standard500 = 75*(t1-t0);
		var _standard500 = 20*(t1-t0);
		return _standard500
	}
	_saveStateInterval = setInterval(_saveState,300000); //save state every 5min
	_logFiltersInterval = setInterval(_saveFilterLog,1800000); //save filter usage every 30min
	const pxperrem = document.querySelector('#logo img').height/2.5;  //CSS sets logo image to 2.5rem

	async function restrictResultWidth () {
		var _st500 = await standard500();
		setTimeout(function () {
			//only apply for old CSS (if wrapper has no before element)
			if ( document.querySelector('link[href*="main.css"]') ) {
				document.getElementById('results_wrapper').style.maxWidth = document.body.offsetWidth - document.getElementById('sidebar').offsetWidth - 5*parseFloat(getComputedStyle(document.documentElement).fontSize) + "px";
			}
		}, _st500);
	}

	standard500().then( st500 => {
	//	var st500 = standard500();
		console.log("Standard 500ms here: "+st500+"ms");
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
		},st500);
		setTimeout(function () {
			document.getElementById('veil').classList.add('sidebar');
			callFunction('_','updateSidebar','sidebar',false,'','restrictResultWidth').then(()=>{
				callFunction(document.getElementById('formFilters'),'applyFilters','results_wrapper').then(()=>{ 
					document.getElementById('veil').classList.remove('sidebar');
					document.getElementById('veil').classList.add('loginfunctions');
					executeLoginFunctions();
					return false;
				});
		//	processForm(document.getElementById('formAddFilters'),'../php/updateSidebar.php','sidebar');
			<?php 
				unset($value);
				if ( isset($_config['_openids']) )
				{
					$alreadyopen = array();
			?>
				document.getElementById('veil').classList.add('openids');
			<?php	foreach ( $_config['_openids'] as $value ) { 
						if ( isset($value) ) {
							foreach( $value as $tablekey => $number ) {
								$value[$tablekey] = _cleanup($number);
							}
							if ( ! in_array(json_encode($value),$alreadyopen) ) {
								?>
								callJSFunction('<?php echo(json_encode($value)); ?>',openIds);
							<?php
								array_push($alreadyopen,json_encode($value));
							} 
						}
					}
				}  ?>
			})
		},2*st500);
		
		// offer new CSS at first login after change
		<?php if ( $_firsttimecss ) { ?>
			setTimeout(function(){ 
				if ( confirm('Probiere den \u{1D5FB}\u{1D5F2}\u{1D602}\u{1D5F2}\u{1D5FB} \u{1D5E6}\u{1D601}\u{1D5F6}\u{1D5F9} "\u{1F5B5} \u{1FA84}_clear_".\n\nStatuszeile und Tabelllen-/Filtereinstellungen verstecken sich am oberen bzw. linken Rand und erscheinen stets, wenn Du mit dem Mauszeiger dorthin gehst.\n\nDu kannst jederzeit in der Statuszeile mit \u{1F5B5} > \u{1FA84} die Ansichtsart wechseln.\n\nWillst Du den neuen Stil jetzt einschalten?') ) {
					window.location = '/index.php?css=_clear_';
				}
			},3*st500);
		<?php } ?>
		function areWeReadyQ(_size) {
			if ( ! _size ) { _size = 0; }
			let _newsize = document.body.textContent.length;
			if ( _newsize != _size ) { 
//			if ( _newsize != -1 ) { 
				setTimeout(function(){ areWeReadyQ(_newsize); },st500);
			} else { 
				document.getElementById('veil').classList = "";
			}
		}
		setTimeout(areWeReadyQ,3*st500);
			
	});	
</script> 
</body>
</html>

