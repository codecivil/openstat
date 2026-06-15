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

//experimental: sort before json_encoding: this makes purely numerical keys numerical, whereas unsorted they become stringy keys!
//unsorted numerical keys occur when in edit.php the order of fields is reversed for technichel reasons
foreach($PARAMETER as $key=>$value) 
{
    if ( is_array($value) ) { ksort($value); $PARAMETER[$key] = $value; }
}

//experimental: recover numbers from strings (converted by JS's FormData object at submission)
$PARAMETER = json_decode(json_encode($PARAMETER,JSON_NUMERIC_CHECK),true);
?>
