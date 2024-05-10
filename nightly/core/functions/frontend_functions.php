<?php
function html_echo($string = '') { if ( isset($string) ) { echo(htmlentities($string)); }; } //use ENT_QUOTES | ENT_IGNORE ?

function link_echo($string = '') { 
    echo(preg_replace('/Video:([^#]*)#/','</pre><a href="$1">Video</a><pre>',$string));    
}
?>
