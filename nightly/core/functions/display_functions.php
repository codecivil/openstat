<?php
function trimList(string $a)
{
	$b = '';
	while ( $a != $b ) {
		$b = $a;
		$a=str_replace(',,',',',$b);
		$a=str_replace('[,','[',$a);
		$a=str_replace('{,','{',$a);
		$a=str_replace(',]',']',$a);
		$a=str_replace(',}','}',$a);
		$a=str_replace('""','',$a);
		$a=str_replace('[]','',$a);
		$a=str_replace('{}','',$a);
	}
	return $a;
}

function _evalRestrictions(string $restriction, string $generation, string $rolename, string $username)
{
	switch($generation) {
		case 'CHILD':
			$_values = str_replace('CHILD_ROLE','',$restriction);
			$_values = str_replace('THIS_ROLE',$rolename,$_values);
			$_values = str_replace('USER',$username,$_values);
			$_values = trimList($_values);
			//$_values = implode("\',\'",json_decode($_values,true));
			break;
		case 'PARENT':
			$_values = str_replace('CHILD_ROLE',$rolename,$restriction);
			$_values = str_replace('THIS_ROLE','',$_values);
			$_values = str_replace('USER',$username,$_values);
			$_values = trimList($_values);
			//$_values = implode("\',\'",json_decode($_values,true));
			break;
		default:
			//$_values = implode("\',\'",json_decode($restriction,true));
			$_values = $restriction;
			break;
	}
	return $_values;
}

//no variable type: must allow for NULL to be processed without error
function _cleanup($value,$separator = '<br />')
{
	$newvalue = '';
	if ( is_array($value) ) {
		$value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}
	if ( is_array(json_decode($value,true)) ) {
		$values = json_decode($value,true);
		//forget filepaths, take only filedescriptions
		if ( isset($values[4001]) ) { $values = $values[4001]; }
		//format compound fields
		if ( is_array($values[0]) ) {
			$komma = '';
			for ( $i = 0; $i < sizeof($values[0]); $i++ ) {
				for ( $j = 0; $j < sizeof($values); $j++ ) {
					$newvalue .= $komma._cleanup($values[$j][$i]);
					$komma = ', ';
				}
				$komma = $separator;
			}
			$values = array($newvalue);
		}
		//clean up every component of the array
		foreach ( $values as $index=>$entry ) {
			$values[$index] = _cleanup($entry);
		}
		$value = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}
	//format dates (and times) to locale (here: german) 
	if ( DateTime::createFromFormat('Y-m-d H:i:s', $value) !== FALSE) { 
		$value = DateTime::createFromFormat('Y-m-d H:i:s', $value)->format('d.m.Y H:i:s');
	}
	if ( DateTime::createFromFormat('Y-m-d H:i', $value) !== FALSE) { 
		$value = DateTime::createFromFormat('Y-m-d H:i', $value)->format('d.m.Y H:i');
	}
	if ( DateTime::createFromFormat('Y-m-d', $value) !== FALSE) { 
		$value = DateTime::createFromFormat('Y-m-d', $value)->format('d.m.Y');
	}
	//format numbers (number_format, negative red...)
	if ( is_numeric($value) ) {
		$_class = '';
		if ( $value < 0 ) { $_class="error"; }
		if ( floor($value) != $value ) {
			$value = number_format($value,2,',','.');
		}
		if ( $_class == 'error' ) { $value = "<span class=\"error\">".$value."</span>"; }
	}
	//write json arrays nicer
	if ( strpos($value,'["') > -1 ) {
		$value = str_replace('[','',$value);
		$value = str_replace(']','',$value);
		$value = str_replace('"','',$value);
		$value = str_replace(',',', ',$value);
	}
	return $value;
}

function _strip_tags($value,$length = -1)
{
	if ( is_array($value) ) {
		$value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}
	if ( is_array(json_decode($value,true)) ) {
		$values = json_decode($value,true);
		//forget filepaths, take only filedescriptions
		if ( isset($values[4001]) ) { $values = $values[4001]; }
		//
		foreach ( $values as $index=>$entry ) {
			$values[$index] = _strip_tags($entry,$length);
		}
		$value = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}
	if ( strip_tags($value) != $value ) {
		$border = '"';
	} else {
		$border = '';
	}
	if ( $length == -1 ) {
		$value = $border.strip_tags($value).$border;
	} else {
		if ( strlen(strip_tags($value)) > $length ) {
			$dots = '...';
		} else {
			$dots = '';
		}
		$value = $border.substr(strip_tags($value),0,$length).$dots.$border;
	}
	if ( $value == '' ) { $value = '[k.A.]'; }
	return $value;
}
?>
