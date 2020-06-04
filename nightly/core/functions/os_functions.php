<?php

function newEntry(array $PARAM,$conn) {
	if ( isset($PARAM['trash']) ) { 
		$_array = json_decode($PARAM['trash'],true);
	} else {
		$_array = $PARAM;
	}
	//get config
	$_config = getConfig($conn);
	$TABLES = $_config['table'];
	$maintable = $TABLES[0];
	//distinguish mass and single edit
	if ( isset($_array['massEdit']) ) {
		if ( is_array($_array['massEdit']) ) {
			$id = array();
			$table = array($_array['massEdit'][0]); unset($_array['massEdit'][0]);
			foreach ( $_array['massEdit'] as $_json ) {
				$_tmparray = json_decode($_json,true);
				if ( isset($_tmparray['id_'.$maintable]) ) { $id[] = $_tmparray['id_'.$maintable]; }
			}
			unset($_tmparray);
		} else {
			return;
		}
	} else {
	/*	if ( ! isset ($STRINGPARAMETER['trash']) ) { return; }
		$PARAMETER = json_decode($STRINGPARAMETER['trash'],true);
		if ( ! isset($PARAMETER['table']) ) { $table = array('os_all'); } else { $table = $PARAMETER['table']; }; */
		$table = $_array['table'];
		$id = array($_array['id_'.$maintable]);
	}
	$PARAM = $_array;
	
	unset($_stmt_array); $_stmt_array = array();
	$_stmt_array['stmt'] = 'SELECT iconname,tablemachine from os_tables';
	/*$_stmt_array['str_types'] = 's';
	$_stmt_array['arr_values'] = array($table);*/
	$_table_result = execute_stmt($_stmt_array,$conn,true)['result'];
	$icon = array();
	for ( $i = 0; $i < sizeof($_table_result); $i++ ) {
		$icon[$_table_result[$i]['tablemachine']] = $_table_result[$i]['iconname'];
	}
	$iconname = $icon[$table[0]];

/*	unset($_stmt_array); $_stmt_array = array();
	$_stmt_array['stmt'] = 'SELECT iconname from os_tables WHERE tablemachine = ?';
	$_stmt_array['str_types'] = 's';
	$_stmt_array['arr_values'] = array($table[0]);
	$iconname = execute_stmt($_stmt_array,$conn)['result']['iconname'][0];
	*/
	
	unset($_stmt_array); $_stmt_array = array();
	$_stmt_array['stmt'] = 'SELECT column_name FROM information_schema.columns WHERE table_name = ?;';
	$_stmt_array['str_types'] = 's';
	$_stmt_array['arr_values'] = array('view__'.$table[0].'__'.$_SESSION['os_role']);

	//get details of the entry
	unset($PARAMETER);
	$result_array = execute_stmt($_stmt_array,$conn);
	$PARAMETER = $result_array['result']['column_name'];
	$dbMessage = $result_array['dbMessage'];
	$dbMessageGood = $result_array['dbMessageGood'];

	$rnd = rand(0,2147483647);
/*	//update config
	$_config = getConfig($conn);
	if ( ! isset($_config['_openids']) ) { $_config['_openids'] = array(); }
	if ( ! in_array($PARAMETER['id'],$_config['_openids']) ) { $_config['_openids'][] = $PARAMETER['id']; }
	updateConfig($_config,$conn); */
	?>


	<div class="content section" onclick="_disableClass(this,'noinsert'); this.onclick = ''; ">
		<?php updateTime(); ?>
		<?php includeFunctions('DETAILS',$conn); ?>	
		<h2 class="db_headline"><i class="fas fa-<?php html_echo($iconname); ?>"></i> Neuer Eintrag <span class="db_headline_id"></span></h2>
		<div class="message" id="message<?php echo($rnd); ?>"><div class="dbMessage" class="<?php echo($dbMessageGood); ?>"><?php echo($dbMessage); ?></div></div>
		<form class="db_options" method="POST" action="" onsubmit="callFunction(this,'dbAction','message'); return false;">
			<input type="text" hidden value="<?php echo($table[0]); ?>" name="table" class="inputtable" />
			<input type="text" hidden value="<?php echo($_SESSION['os_user']); ?>" name="changedby" class="inputid" />
			<div class="fieldset">
				<legend></legend>
		<!--	Freitextsuche vielleicht später	
				<label for="db_search">Suche</label>
				<input type="text" name="db_search" id="db_search">
		-->
				<div class="actionwrapper">
					<label for="_action<?php echo($table.$id[0].$rnd); ?>_sticky" class="action">Aktion</label>
					<select id="_action<?php echo($table.$id[0].$rnd); ?>_sticky" name="dbAction" class="db_formbox" onchange="tinyMCE.triggerSave(); invalid = validate(this,this.closest('form').getElementsByClassName('paramtype')[0].innerText); colorInvalid(this,invalid); if (invalid.length == 0) { updateTime(this); _onAction(this.value,this.closest('form'),'dbAction','message<?php echo($rnd); ?>'); callFunction(this.closest('form'),'calAction',''); }; callFunction(document.getElementById('formFilters'),'applyFilters','results_wrapper',false,'','scrollTo',this); document.getElementById('_action<?php echo($table.$id[0]); ?>_sticky').value = ''; this.scrollIntoView(); return false;" title="Aktion bitte erst nach der Bearbeitung der Inhalte wählen.">
						<option value="pleasechoose" selected>[Bitte erst nach Bearbeitung wählen]</option> <!-- pleasechoose: arbitrary non-empty value, so that the message is returned and not an array-->
						<option value="insert">als neuen Eintrag anlegen</option>
					</select>
				</div>
				<?php 
				// to do: mass edit: do here the multiple $id s and edit dbAction 'insert' to allow for multiple inserts (like in 'edit')
				foreach( $PARAMETER as $key )
				{
					if ( substr($key,0,3) == 'id_'  OR $key == 'table' ) {
						if ( $key == 'id_'.$table[0] OR ! in_array(substr($key,3),$_config['table']) ) { continue; }
						$_tmp_table = substr($key,3);
						?>
						<div class='ID_<?php echo($_tmp_table); ?>' ondragover="allowDrop(event)" ondrop="dropOnDetails(event,this)" ondragstart="drag(event)" ondragenter="dragenter(event)" ondragleave="dragleave(event)" ondragend="dragend(event)">
							<?php
							if ( $key == 'id_'.$maintable ) { ?>
									<label class="unlimitedWidth"><i class="fas fa-<?php echo($icon[$_tmp_table]); ?>"></i> <?php html_echo(sizeof($id)); ?> Einträge (Änderung muss noch gespeichert werden)</label>
									<input type="text" hidden value="<?php html_echo(json_encode($id)); ?>" name="<?php echo($key); ?>" class="inputid" />								
							<?php } else {
								if ( isset($PARAM[$key]) ) { ?>
									<label class="unlimitedWidth"><i class="fas fa-<?php echo($icon[$_tmp_table]); ?>"></i> ID: <?php echo($PARAM[$key]); ?> (Änderung muss noch gespeichert werden)</label>
									<input type="text" hidden value="<?php echo($PARAM[$key]); ?>" name="<?php echo($key); ?>" class="inputid" />
								<?php } else { ?>
									<label class="unlimitedWidth"><i class="fas fa-<?php echo($icon[$_tmp_table]); ?>"></i> (keine Zuordnung)</label>
									<input type="text" hidden value="<?php echo($default); ?>" name="<?php echo($key); ?>" class="inputid" />
								<?php } 
							} ?>
						</div>
						<br />	
						<div class="clear"/>
						<br />
						<?php
					}		
				}

				$PARAMTYPE = array();				
				foreach( $PARAMETER as $key )
				{
					if ( substr($key,0,3) == 'id_' OR $key == 'table' ) { continue; }
					$edit = new OpenStatEdit($table[0],$key,$conn);
					$PARAMTYPE[$table[0].'__'.$key] = $edit->edit('');
					unset($edit);
				}
				
				?>
				<div class="paramtype" hidden><?php html_echo(json_encode($PARAMTYPE)); ?></div>
				<input type="submit" hidden>	
			<//div> <!-- END OF div of class fieldset -->
		</form>
	</div>
<?php }

function printResults() { return; }

function importCSV(array $PARAM,$conn) {
	$rnd = rand(0,2147483647);
?>
	<div class="headers" hidden>
	<?php
		$_config = getConfig($conn);
		//generate here a json of table headers...
		// $headers = array();
		// $headers[$i]["table"], $headers[$i]["header"], $headers[$i]["type"] (reverse indexes)
		unset($_stmt_array); $_stmt_array = array();
		$_stmt_array['stmt'] = 'SELECT iconname,tablemachine,tablereadable from os_tables';
		$_table_result = execute_stmt($_stmt_array,$conn)['result'];
		$_tablereadable = array_combine($_table_result['tablemachine'],$_table_result['tablereadable']);
		$key_array = array();
		$key_array['keymachine'] = array();
		$key_array['keyreadable'] = array();
		$key_array['edittype'] = array();
		$key_array['table'] = array();
		$key_array['referencetag'] = array();
		$key_array['allowed_values'] = array();
		$key_array['tablemachine2readable'] = $_tablereadable;
		foreach ( $_config["table"] as $_table ) 
		{
			unset($_stmt_array); $_stmt_array = array();
			$_stmt_array['stmt'] = "SELECT keymachine,keyreadable,edittype,referencetag FROM ".$_table."_permissions ORDER BY realid";
			$_result_array = execute_stmt($_stmt_array,$conn); 
			if ($_result_array['dbMessageGood']) { $key_array_add = $_result_array['result']; $key_array_add['table'] = array(); };
			foreach ($key_array_add['keymachine'] as $key)
			{
				$key_array_add['table'][] = $_table;
			}
			$old_add = array();
			while ( $old_add != $key_array_add['keyreadable'] ) 
			{
				$old_add = $key_array_add['keyreadable'];
				// disable unselectable and uneditable entries (keyreadable = _none or edittype = ID)
				if (($index = array_search('_none_', $key_array_add['keyreadable'])) !== false ) {
					unset($key_array_add['keyreadable'][$index]);
					unset($key_array_add['keymachine'][$index]);
					unset($key_array_add['edittype'][$index]);
					unset($key_array_add['referencetag'][$index]);
					unset($key_array_add['table'][$index]);
				}
				if (($index = array_search('ID', $key_array_add['edittype'])) !== false ) {
					unset($key_array_add['keyreadable'][$index]);
					unset($key_array_add['keymachine'][$index]);
					unset($key_array_add['edittype'][$index]);
					unset($key_array_add['referencetag'][$index]);
					unset($key_array_add['table'][$index]);
				}
			}
			$key_array['keymachine'] = array_merge($key_array['keymachine'], $key_array_add['keymachine']);
			$key_array['keyreadable'] = array_merge($key_array['keyreadable'], $key_array_add['keyreadable']);
			$key_array['edittype'] = array_merge($key_array['edittype'], $key_array_add['edittype']);
			$key_array['table'] = array_merge($key_array['table'], $key_array_add['table']);
			$key_array['referencetag'] = array_merge($key_array['referencetag'], $key_array_add['referencetag']);
		}
		$edittypes = $key_array['edittype'];
		//get allowd values of all LISTs
		$indexes = array_keys($edittypes,'LIST');
		unset($index);
		foreach ( $indexes as $index ) {
			unset($_stmt_array); $_stmt_array = array(); unset($_result_array);
			$key_array['allowed_values'][$index] = array();
			$_stmt_array['stmt'] = "SELECT allowed_values FROM ".$key_array['table'][$index]."_references WHERE referencetag = ?";
			$_stmt_array['str_types'] = 's';
			$_stmt_array['arr_values'] = array($key_array['referencetag'][$index]);
			$_result_array = execute_stmt($_stmt_array,$conn); 
			if ($_result_array['dbMessageGood']) { 
				foreach ( $_result_array['result']['allowed_values'] as $allowed_value_json )
				{
					$key_array['allowed_values'][$index] = array_merge($key_array['allowed_values'][$index],json_decode($allowed_value_json,true));
				}
				unset($edittypes[$index]);
			}
		}
		$headers = $key_array['keyreadable'];
		//transfer to js on client side
		//print_r($key_array);
		echo(json_encode($key_array));
	?>
	</div>
	<div class="fileselection">
		<h3>Wähle zu importierende CSV-Dateien</h3>
		<label for="csvhinweise<?php echo($rnd); ?>" class="unlimitedWidth"><i class="fas fa-angle-right"></i><i> Hinweise zum Import</i></label>
		<div class="clear"></div><br />
		<input class="toggle" id="csvhinweise<?php echo($rnd); ?>" type="checkbox" hidden>
		<div>
			Eine CSV-Datei kann nur dann korrekt importiert werden, wenn alle Einträge in doppelten Hochkommata (&quot;) stehen und durch Kommata getrennt sind. Dies wird in LibreOffice Calc
			dadurch erreicht, dass man
			<ol>
				<li>alle Datums- und Zahlenspalten in Text umwandelt. Das geschieht durch Vorausstellen eines einfachen Hochkommas in den Einträgen und kann automatisiert werden, indem man
				die entsprechende Spalte anklickt, im Menu auf "Bearbeiten" > "Suchen und ersetzen" geht und dort wählt:
					<ul>
						<li>Suchen: <code>^[0-9]</code></li>
						<li>Ersetzen: <code>'$0</code></li>
						<li>Weitere Optionen: "Nur in Auswahl", "reguläre Ausdrücke" anhaken, alles andere nicht anhaken</li>
					</ul>
				sowie dann "Alles ersetzen" anklickt.
				</li>
				<li>die Datei danach im CSV-Format speichert und nach Bestätigung der Formatauswahl im Menu "Feldoptionen" angibt:
					<ul>
						<li>Zeichensatz: Unicde (UTF-8)</li>
						<li>Feldtrennzeichen: <code>,</code></li>
						<li>Zeichenkettentrennzeichen: <code>"</code></li>
						<li>Haken setzen bei: "Zellinhalt wie angezeigt speichern", "Text zwischen Hochkommas ausgeben"</li>
					</ul>
				</li>
			</ol>
		</div>
		<form class="fileSelectionForm">
			<input type="file" multiple class="importFile" accept=".csv,application/csv,text/csv" onchange="matchHeaders(this,this.files,'headermatch')">
		</form>
	</div>
	<div class="functions headersubmitlabel" hidden>
		<ul>
			<li><label for="headersubmit<?php echo($rnd); ?>" title="Header zuordnen" class="unlimitedWidth"><i class="fas fa-equals"></i></label></li>
			<li><label for="submitImport<?php echo($rnd); ?>" title="Jetzt importieren" class="submitimportlabel disabled unlimitedWidth"><i class="fas fa-file-import"></i></label></li>
		</ul>
	</div>
	<div class="headermatch" hidden>
		<h3>Vorgeschlagene Zuordnung</h3>
		<form class="db_options formHeaderMatch" onsubmit="checkHeaders(this,'importnow'); return false;">
			<div>
				<label><b>Datei</b></label>
				<label class="headermatchheader"><b>Datenbank</b></label>
			</div>
			<br />
			<br />
			<div class="singlematch edit_wrapper" hidden>
				<label></label>
				<select>
					<optgroup>
						<option value="-1" selected>*keine Zuordnung*</option>
					</optgroup>
					<optgroup label="<?php echo($_tablereadable[$key_array['table'][0]]); ?>">
					<?php foreach ( $headers as $index=>$header )
						//do not offer ID type: they have uneditable default values!
						//alas they are considered in js-part and lead to buggy behaviour: indices are not found (import.js l. 133 querySelector...)
						//this is too late: included already in test above (l. 187)
						{ 
						//if ( $key_array['edittype'][$index] == 'ID' ) { continue; }
						?>
							<option value="<?php echo($index); ?>"><?php echo($header); ?></option>
						<?php 
						if ( $key_array['keyreadable'][$index + 1] AND $key_array['table'][$index] != $key_array['table'][$index + 1] ) { ?>
							</optgroup>
							<optgroup label="<?php echo($_tablereadable[$key_array['table'][$index+1]]); ?>">								
						<?php } 
						} ?>
					</optgroup>
				</select>
				<div class="clear"></div>
			</div>
			<input id="headersubmit<?php echo($rnd); ?>" class="unlimitedWidth" type="submit" hidden value="Header zuordnen">
		</form>
		<div class="clear"></div>
	</div>
	<div class="importnow" hidden>
		<form class="formImport" onsubmit="importJS(this); return false;">
			<input id="submitImport<?php echo($rnd); ?>" class="unlimitedWidth submitImport" type="submit" value="Importieren" disabled hidden>		
		</form>
		<div class="clear"></div>
		<div class="importSuccessHidden" id="importSuccessHidden<?php echo($rnd); ?>" hidden></div>
		<div class="importSuccess" hidden>
			<h3>Zusammenfassung</h3>
			<table class="import">
			<tr>
				<th>Importiert</th><td class="importImported">0</td>
			</tr>
			<tr>
				<th>Bereits vorhanden</th><td class="importExists">0</td>
			</tr>
			<tr>
				<th>Nicht importiert</th><td class="importProblems">0</td>
			</tr>
			</table>
			<h3>Importiert</h3>
			<ul class="importImportedDetails"></ul>
			<h3>Bereits vorhanden</h3>
			<ul class="importExistsDetails"></ul>
			<h3>Probleme</h3>
			<ul class="importProblemsDetails"></ul>
		</div>
		<div class="gotID" hidden></div>
		<div class="importFinished" hidden></div>
	</div>	
<?php
}

//find main table complement of filters
//scope: FILTERS
function applyComplement (array $PARAM, $conn) {
	applyFilters($PARAM,$conn,true);
}

//scope: RESULTDETAILS
function exportCSV (array $PARAM, $conn) {
// $PARAM is the mass edit array
// offers export of all mass edit values or only the filtered ones
	if ( isset($PARAM['trash']) ) { 
		$_array = json_decode($PARAM['trash'],true);
	} else {
		$_array = $PARAM;
	}
	?>
	<h3>CSV-Exporte</h3>
	<p>Fenster bitte nach dem Herunterladen schließen</p>
	<?php
	//get config
	$_config = getConfig($conn);
	function _modify_stmt(array $_sql_stmt, array $_array) {
		$WHERE_ADD = '';
		if ( ! isset($_array['massEdit']) ) { return; }
		$OR = '';
		foreach( $_array['massEdit'] as $_idcollection ) {
			if ( ! is_array(json_decode($_idcollection,true)) ) { continue; }
			$WHERE_ADD .= $OR.'(';
			$AND = '';
			foreach ( json_decode($_idcollection,true) as $key=>$id ) {
				$_table = substr($key,3);
				$WHERE_ADD .= $AND."`view__".$_table."__".$_SESSION['os_role']."`.".$key.' = '.$id;
				$AND = ' AND ';
			}
			$WHERE_ADD .= ')';
			$OR = ' OR ';
		}
		$WHEREPOS = strpos($_sql_stmt['stmt'],'WHERE');
		$ORDERBYPOS = strpos($_sql_stmt['stmt'],'ORDER BY');
		if ( $WHEREPOS !== false ) { $_sql_stmt['stmt'] = substr($_sql_stmt['stmt'],0,$WHEREPOS+6).'('.$WHERE_ADD.') AND '.substr($_sql_stmt['stmt'],$WHEREPOS+6); }
		if ( $WHEREPOS === false AND $ORDERBYPOS >= 1) { $_sql_stmt['stmt'] = substr($_sql_stmt['stmt'],0,$ORDERBYPOS-1).' WHERE ('.$WHERE_ADD.') '.substr($_sql_stmt['stmt'],$ORDERBYPOS); }
		return $_sql_stmt;
	}
	function generateCSV($_export,$conn) {
		$firstline = true;
		$keymachine2readble = array();
		$_csv = '';
		foreach ( $_export as $_line ) {
			$komma = '';
			if ( $firstline ) {
				foreach ( $_line as $tablekey=>$value ) {
					$tablemachinearray = explode("__",$tablekey,2);
					$table =  $tablemachinearray[0];
					$keymachine = $tablemachinearray[1];
					unset($_stmt_array); $_stmt_array = array();
					$_stmt_array['stmt'] = "SELECT keyreadable,edittype FROM ".$table."_permissions WHERE keymachine = ?";
					$_stmt_array['str_types'] = "s";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = $keymachine;
					unset($_result_array);
					$_result_array = execute_stmt($_stmt_array,$conn); 
					if ($_result_array['dbMessageGood']) { $keyreadable = $_result_array['result']['keyreadable'][0]; }; //serializes as associative array			
					if ( $_result_array['result']['edittype'][0] != 'ID' ) {
						$keymachine2readble[$tablekey] = $keyreadable;
						if ( $keyreadable != '' ) {
							//handle inner quotation marks (muultiple selections)
							$_csv .= $komma.'"'.$keyreadable.'"';
							$komma = ',';
						}
					}
				}
				$_csv .= PHP_EOL;
			}
			$komma = '';
			foreach ( $_line as $tablekey=>$value ) {
				if ( $keymachine2readble[$tablekey] != '' ) {
					$_csv .= $komma.'"'.preg_replace("/\r?\n/","",str_replace('"','\'',$value)).'"';
					$komma = ',';
				}
			}
			$_csv .= PHP_EOL;
			$firstline = false;
		}
		return $_csv;
	}
	//get export filtered statement
	// export without complement unless the result is empty then
	$_sql_stmt = applyFilters($_config['filters'],$conn,false,false); //(first false: no complement, second false: return only statement)
	$_export_filtered = execute_stmt(_modify_stmt($_sql_stmt,$_array),$conn,true)['result'];
	if ( ! is_array($_export_filtered) ) {
		$_sql_stmt = applyFilters($_config['filters'],$conn,false,false); //(first false: no complement, second false: return only statement)
		$_export_filtered = execute_stmt(_modify_stmt($_sql_stmt,$_array),$conn,true)['result'];
	}
	$filename = 'openStat-Export-gefiltert-'.date('Y-m-d_H-i-s').'.csv';
	$_content = base64_encode(generateCSV($_export_filtered,$conn));
	?>
	<h4>Nur gefilterte Daten der ausgewählten Einträge</h4>
	<div class="export">
		<i class="fas fa-file-download"></i>&nbsp;&nbsp;<a href="data:text/plain;charset=utf-8;base64,<?php echo($_content); ?>" download="<?php echo($filename); ?>"><?php echo($filename); ?></a>
	</div> 
	<?php
	//How to destroy this file most efficiently? By using dataURLs, so the file is only created at the client!
	$_content = '';
	//get export full statement
	$_allkeys = array();
	foreach ( $_config['table'] as $_table ) {
		unset($_stmt_array); $_stmt_array = array();
		$_stmt_array['stmt'] = 'SELECT column_name FROM information_schema.columns WHERE table_name = ?;';
		$_stmt_array['str_types'] = 's';
		$_stmt_array['arr_values'] = array('view__'.$_table.'__'.$_SESSION['os_role']);
		$_raw_columns = execute_stmt($_stmt_array,$conn)['result']['column_name'];
		unset($_stmt_array); $_stmt_array = array();
		$_stmt_array['stmt'] = "SELECT keymachine,edittype FROM ".$_table."_permissions";
		$_col_info = execute_stmt($_stmt_array,$conn)['result'];
		foreach ( $_raw_columns as $column ) {
			if ( in_array($column,$_col_info['keymachine']) AND ! in_array($_col_info['edittype'][array_search($column,$_col_info['keymachine'])],array('ID','NONE')) ) {
				$_allkeys[$_table.'__'.$column] = array('_all');
			}
		}
	}
	// export without complement unless the result is empty then
	$_sql_stmt = applyFilters($_allkeys,$conn,false,false,false); //(first false: no complement, second false: return only statement, third false: do not change config)
	$_export_filtered = execute_stmt(_modify_stmt($_sql_stmt,$_array),$conn,true)['result'];
	if ( ! is_array($_export_filtered) ) {
		$_sql_stmt = applyFilters($_config['filters'],$conn,false,false); //(first false: no complement, second false: return only statement)
		$_export_filtered = execute_stmt(_modify_stmt($_sql_stmt,$_array),$conn,true)['result'];
	}
	$filename = 'openStat-Export-voll-'.date('Y-m-d_H-i-s').'.csv';
	$_content = base64_encode(generateCSV($_export_filtered,$conn));
	?>
	<h4>Alle Daten der ausgewählten Einträge (der ausgewählten Tabellen)</h4>
	<div class="export">
		<i class="fas fa-file-download"></i>&nbsp;&nbsp;<a href="data:text/plain;charset=utf-8;base64,<?php echo($_content); ?>" download="<?php echo($filename); ?>"><?php echo($filename); ?></a>
	</div> 
	<?php
	//How to destroy this file most efficiently? By using dataURLs, so the file is only created at the client!
	$_content = '';
}

function changeUserName(array $PARAM, $conn) {
	unset($_stmt_array); $_stmt_array = array();
	$_stmt_array['stmt'] = "UPDATE os_users_".$_SESSION['os_user']." SET username=? ";
	$_stmt_array['str_types'] = "s";
	$_stmt_array['arr_values'] = array();
	$_stmt_array['arr_values'][] = $PARAM['userName'];
	unset($_result_array);
	$_result_array = _execute_stmt($_stmt_array,$conn); 
	unset($_stmt_array); $_stmt_array = array();
	$_stmt_array['stmt'] = "SELECT username from os_users_".$_SESSION['os_user'];
	unset($_result_username);
	$_SESSION['os_username'] = execute_stmt($_stmt_array,$conn)['result']['username'][0];
//	return $_result_array['dbMessageGood'];			
	return $_SESSION['os_username'];			
}
