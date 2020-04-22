<?php
function html_echo($string = '') { if ( isset($string) ) { echo(htmlentities($string)); }; } //use ENT_QUOTES | ENT_IGNORE ?
?>
