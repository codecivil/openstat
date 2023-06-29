<?php
	require_once('../../core/functions/frontend_functions.php');
	if ( isset($_GET['o']) ) { $orient = '_'.$_GET['o']; } else { $orient = ''; }
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="de-DE">
	<head profile="http://gmpg.org/xfn/11">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta http-equiv="Content-Script-Type" content="text/javascript"/>
	<meta name="language" content="de-DE" />
	<meta name="content-language" content="de-DE" />
	<meta http-equiv="imagetoolbar" content="no" />
	<link rel="stylesheet" type="text/css" media="screen, projection" href="/css/fontsize_10.css" id="cssFontSize" />
	<link rel="stylesheet" type="text/css" media="screen, projection, print" href="/css/print<?php html_echo($orient); ?>.css" />
	<link rel="stylesheet" type="text/css" media="screen, projection, print" href="/plugins/fontawesome/css/all.css" />
	<script type="text/javascript" src="/js/main.js"></script>
	<script type="text/javascript" src="/js/os.js"></script>
	<script type="text/javascript" src="/plugins/tinymce/js/tinymce.js"></script>
	<script type="text/javascript" src="/plugins/tinymce/js/os_tinymce.js"></script>
</head>
<body>
</body>
