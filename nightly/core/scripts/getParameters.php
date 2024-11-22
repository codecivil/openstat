<?php 
//get parameters
$PARAMETER = array(); 
$action = '';

foreach($_GET as $key=>$value)
{
	if ( $value != '' ) {
		$PARAMETER[$key] = $value;
	}
}

foreach($_POST as $key=>$value)
{
	if ( $value != '' ) {
		$PARAMETER[$key] = $value;
	}
}

foreach($_FILES as $key=>$value)
{
	if ( ! isset($PARAMETER['FILES']) ) { $PARAMETER['FILES'] = array(); }
	if ( $value != '' ) {
		$PARAMETER['FILES'][$key] = $value;
	}
}

//experimental: recover numbers from strings (converted by JS's FormData object at submission)
$PARAMETER = json_decode(json_encode($PARAMETER,JSON_NUMERIC_CHECK),true);
?>
