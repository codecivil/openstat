<?php

//returns the $PARAMETER array enriched by the new FILES info...
function FILE_Action(array $_PARAMETER, mysqli $conn) {
	unset($_PARAMETER['FILES']); return $_PARAMETER;
	//do nothing as along as distinction between FILES and FILESPATH is not properly implemented
	require('../../core/data/filedata.php');
	$message = '';
	
	//allow json encoded parameters in 'trash' variable
	if ( isset($_PARAMETER['trash']) ) { 
	$PARAMETER = json_decode($_PARAMETER['trash'],true);
	} else {
		$PARAMETER = $_PARAMETER;
	}

	if ( ! is_array($PARAMETER) ) { return; }

	if ( ! isset($PARAMETER['dbAction']) ) { $PARAMETER['dbAction'] = ''; }
	switch($PARAMETER['dbAction']) {
		case 'insert':
		case 'edit':
			//remove user deleted files
			foreach ( $PARAMETER as $key=>$value ) {
				if ( isset($value['filepath']) ) {
					$dir = dirname($value['filepath'][0]);
					//remove only imported files in $fileroot
					if ( strpos($dir,$fileroot) == 0 ) {
						$filelist = scandir($dir);
						foreach ( $filelist as $filename )
						{
							if ( ! in_array($value['filepath'],$dir.'/'.$filename) ) { unlink($dir.'/'.$filename); }
						}					
					}
				}
			}
			//create new files and return the new values...
			unset($key); unset($value);
			//the file arrays are transferred as: $PARAMETER['FILES'][table_key][error/name/tmp_name...][numeric index] !!!!
			if ( isset($PARAMETER['FILES']) ) {
				$res = gnupg_init();
				gnupg_addencryptkey($res,'EBD608B2F05037DBB54360C49B77CAE8A63887AC','');
				foreach ( $PARAMETR['FILES'] as $tablekey=>$filefield ) {
					$table = explode('__',$tablekey)[0];
					$dir = $fileroot.'/'.$tablekey.'_'.$PARAMETER['id_'.$table]; 
					mkdir($dir, 0700);
					for ( $i = 0; $i < sizeof($filefield['name']); $i++ ) {
						$new_name = bin2hex(random_bytes(8));
						$extension = pathinfo($filefield['name'], PATHINFO_EXTENSION);
						//get file
						$plaintext = file_get_contents($filefield['tmp_name'][$i]);
						//encrypt
						$ciphertext = gnupg_encrypt($res,$plaintext); unset($plaintext);
						//write to disc
						$bytes = file_put_contents($dir.'/'.$new_name, $ciphertext);
						//generate new parameters
						$PARAMETER[$tablekey]['filepath'][] = $dir.'/'.$new_name;
					}
				}
				unset($PARAMETER['FILES']);	
			}
			break;
		case 'delete':
			break;
		case 'getID':
			break;
		case 'insertIfNotExists':
			break;
	}
	return $PARAMETER;		
}

function openFile($PARAMETER)
{
	$rnd = rand(0,32767);
	$filename = basename($PARAMETER['trash']);
	$fullname = $PARAMETER['trash'];
	$_error = '';
	if ( ! symlink($fullname,'../../public/'.$filename) ) { $_error = "Fehlende Berechtigungen in Webroot; bitte Administrator kontaktieren."; }
	?>
	<div class="filepreview">
	<?php updateTime(); ?>
	<?php if ( $_error != '' ) { ?>
		<div class="error"><?php echo($_error); ?></div>
	<?php return; } ?>
	<div class="db_headline_wrapper"><h2>Vorschau von <a href="<?php echo($filename); ?>?v=<?php echo($rnd); ?>" target="_blank"><?php echo($filename); ?></a></h2></div>
	<iframe
		src='<?php echo($filename); ?>?v=<?php echo($rnd); ?>' 
		onload="document.getElementById('trash').value = '<?php echo($filename); ?>'; callFunction('_','_unlink').then(()=>{ document.getElementById('trash').value = ''; });"
		frameborder="0" border="0" cellspacing="0"
		class="_iframe"
	>
	</iframe>
	</div>
	<?php
}

function _unlink($PARAMETER)
{
	$filename = basename($PARAMETER['trash']);
	if ( is_link('../../public/'.$filename) ) { unlink('../../public/'.$filename); }
	clearstatcache();
}

function _openFile($PARAMETER)
{
	?>
	<?php header('Content-Type: '.mime_content_type($PARAMETER['trash'])); header('Content-Disposition: attachment; filename="'.basename($PARAMETER['trash']).'"'); readfile($PARAMETER['trash']); ?>
	<?php
}

?>
