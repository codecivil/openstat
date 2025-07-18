<?php
function logout(string $redirect = 'login.php') { 
	if ( session_status() === PHP_SESSION_NONE ){ session_start(); } //2021-07-22 seemed not to be necessary, threw Notice: session already started, so we better check
	// Unset all of the session variables.
	$_SESSION = array();

	// If it's desired to kill the session, also delete the session cookie.
	// Note: This will destroy the session, and not just the session data!
	if (ini_get("session.use_cookies")) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000,
			$params["path"], $params["domain"],
			$params["secure"], $params["httponly"]
		);
	}

	// Finally, destroy the session.
	session_destroy();
	if ( $redirect != '' ) {
		header('Location:/'.$redirect);
	}
}


function saveFilterLog(array $PARAM, $conn) {
	//adapt: read $_SESSION['filterlog'] (JSON) and put into database
	//record users filter statistics
	if ( ! isset($_SESSION['filterlog']) OR $_SESSION['filterlog'] == '[]' ) { return; }
	$filterlog = json_decode($_SESSION['filterlog']);
	$_stmt_array = array();
	$_stmt_array['stmt'] = "INSERT INTO os_userstats (userid,tablemachine,keymachine,filtercount) VALUES ";
	$_stmt_array['str_types'] = "";
	$_stmt_array['arr_values'] = array();
	$komma = '';
	foreach ( $filterlog as $tablekey => $filtercount ) {
		$tablekey_array = explode('__',$tablekey,2);
		$table = $tablekey_array[0];
		$key = $tablekey_array[1];
		$_stmt_array['stmt'] .= $komma."(?,?,?,?)";
		$_stmt_array['str_types'] .= "issi";
		array_push($_stmt_array['arr_values'],$_SESSION['os_user'],$table,$key,$filtercount);
		$komma = ",";
	}
	$tmpresult = execute_stmt($_stmt_array,$conn);
	$_SESSION['filterlog'] = '[]';
	//and keep only last 50 entries
	unset($_stmt_array); $_stmt_array = array();
	$_stmt_array['stmt'] = "DELETE FROM os_userstats WHERE userid=? AND id NOT IN ( SELECT id FROM os_userstats us1 WHERE ( SELECT count(*) FROM os_userstats us2 WHERE userid=? AND us1.id <= us2.id ) <= 50 );"; //use of LIMIT & IN not yet possible
	$_stmt_array['str_types'] = "ii";
	$_stmt_array['arr_values'] = array($_SESSION['os_user'],$_SESSION['os_user']);
	$tmpresult = execute_stmt($_stmt_array,$conn);
}

function newEntry(array $PARAM,$conn) {
	if ( isset($PARAM['trash']) ) { 
		$_array = json_decode($PARAM['trash'],true);
	} else {
		$_array = $PARAM;
	}
	//get config
	$_config = getConfig($conn);
	$TABLES = $_config['table'];
	$SUBTABLES = $_config['subtable'];
	$maintable = $TABLES[0];
	//distinguish mass and single edit
	if ( isset($_array['massEdit']) ) {
		if ( is_array($_array['massEdit']) ) {
			$id = array();
			$table = array($_array['massEdit'][0]); unset($_array['massEdit'][0]);
			foreach ( $_array['massEdit'] as $_json ) {
				$_tmparray = json_decode($_json,true);
				if ( isset($_tmparray['id_'.$maintable]) ) { $id[] = (int)$_tmparray['id_'.$maintable]; }
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
		$id = array((int)$_array['id_'.$maintable]);
	}
	$PARAM = $_array;
	
	unset($_stmt_array); $_stmt_array = array();
	$_stmt_array['stmt'] = 'SELECT iconname,tablemachine,parentmachine,identifiers from os_tables';
	/*$_stmt_array['str_types'] = 's';
	$_stmt_array['arr_values'] = array($table);*/
	$_table_result = execute_stmt($_stmt_array,$conn,true)['result'];
	$icon = array();
	for ( $i = 0; $i < sizeof($_table_result); $i++ ) {
		$icon[$_table_result[$i]['tablemachine']] = $_table_result[$i]['iconname'];
		$identifiers[$_table_result[$i]['tablemachine']] = $_table_result[$i]['identifiers'];
	}
	$iconname = $icon[$table[0]];
	$mainidentifiers = $identifiers[$table[0]];
/*	unset($_stmt_array); $_stmt_array = array();
	$_stmt_array['stmt'] = 'SELECT iconname from os_tables WHERE tablemachine = ?';
	$_stmt_array['str_types'] = 's';
	$_stmt_array['arr_values'] = array($table[0]);
	$iconname = execute_stmt($_stmt_array,$conn)['result']['iconname'][0];
	*/
	
	unset($_stmt_array); $_stmt_array = array();
	$_stmt_array['stmt'] = 'SELECT column_name FROM information_schema.columns WHERE table_name = ?;';
	$_stmt_array['str_types'] = 's';
	$_stmt_array['arr_values'] = array('view__'.$table[0].'INSERT__'.$_SESSION['os_role']);

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
		<div class="right" onclick="_close(this);"><i class="fas fa-times-circle"></i></div>
		<?php updateTime(); ?>
		<div class="clear"></div>
		<?php includeFunctions('DETAILS',$conn); ?>	
		<h2 class="db_headline clear"><i class="fas fa-<?php html_echo($iconname); ?>"></i> Neuer Eintrag <span class="db_headline_id"></span></h2>
		<div class="message" id="message<?php echo($rnd); ?>"><div class="dbMessage" class="<?php echo($dbMessageGood); ?>"><?php echo($dbMessage); ?></div></div>
		<form class="db_options function" method="POST" action="" onsubmit="callFunction(this,'dbAction','message').then(()=>{ return false; }); return false;">
			<input type="text" hidden value="<?php echo($table[0]); ?>" name="table" class="inputtable" />
			<input type="text" hidden value="<?php echo($_SESSION['os_user']); ?>" name="changedby" class="inputid" />
			<div class="fieldset">
				<legend></legend>
		<!--	Freitextsuche vielleicht später	
				<label for="db_search">Suche</label>
				<input type="text" name="db_search" id="db_search">
		-->
				<div class="actionwrapper">
					<label for="_action<?php echo($table[0].$id[0].$rnd); ?>_sticky" class="action">Aktion</label>
					<select id="_action<?php echo($table[0].$id[0].$rnd); ?>_sticky" name="dbAction" class="db_formbox" onchange="tinyMCE.triggerSave(); invalid = validate(this,this.closest('form').getElementsByClassName('paramtype')[0].innerText); colorInvalid(this,invalid); if (invalid.length == 0) { updateTime(this); _onAction(this.value,this.closest('form'),'dbAction','message<?php echo($rnd); ?>'); callFunction(this.closest('form'),'calAction','').then(()=>{ return false; }); }; callFunction(document.getElementById('formFilters'),'applyFilters','results_wrapper',false,'','scrollTo',this).then(()=>{ document.getElementById('_action<?php echo($table[0].$id[0].$rnd); ?>_sticky').value = 'pleasechoose'; myScrollIntoView(this); return false; });" title="Aktion bitte erst nach der Bearbeitung der Inhalte wählen.">
						<option value="pleasechoose" selected>[Bitte erst nach Bearbeitung wählen]</option> <!-- pleasechoose: arbitrary non-empty value, so that the message is returned and not an array-->
						<option value="insert">als neuen Eintrag anlegen</option>
						<?php if ( $mainidentifiers != '' AND $mainidentifiers != '[]' ) { ?>
						<option value="updateIfExistsElseInsert">alten Eintrag ggf. überschreiben; sonst neu anlegen</option>
						<?php } ?>
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
									<label class="unlimitedWidth" oncontextmenu="return transportAttribution(this)"><i class="fas fa-<?php echo($icon[$_tmp_table]); ?>"></i> <?php html_echo(sizeof($id)); ?> Einträge (Änderung muss noch gespeichert werden)</label>
									<input type="text" hidden value="<?php html_echo(json_encode($id)); ?>" name="<?php echo($key); ?>" class="inputid" />								
							<?php } else {
								if ( isset($PARAM[$key]) ) { ?>
									<label class="unlimitedWidth" oncontextmenu="return transportAttribution(this)"><i class="fas fa-<?php echo($icon[$_tmp_table]); ?>"></i> ID: <?php echo($PARAM[$key]); ?> (Änderung muss noch gespeichert werden)</label>
									<input type="text" hidden value="<?php echo($PARAM[$key]); ?>" name="<?php echo($key); ?>" class="inputid" />
								<?php } else { ?>
									<label class="unlimitedWidth" oncontextmenu="return transportAttribution(this)"><i class="fas fa-<?php echo($icon[$_tmp_table]); ?>"></i> (keine Zuordnung)</label>
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
					//take transferred $PARAMs as default values
					if ( isset($PARAM[$key]) ) { 
						$default = $PARAM[$key];
					} else {
						$default = "";
					}
					$edit = new OpenStatEdit($table[0],$key,$conn);
					$PARAMTYPE[$table[0].'__'.$key] = $edit->edit($default);
					unset($edit);
				}
				
				?>
				<div class="paramtype" hidden><?php html_echo(json_encode($PARAMTYPE)); ?></div>
				<input type="submit" hidden>	
			<//div> <!-- END OF div of class fieldset -->
		</form>
	</div>
<?php }

function printResults(array $PARAM, $conn) {
	//determine additional _scope_ from $PARAM, e.g. '::PORTRAIT'
	if ( ! isset($PARAM['_scope_']) ) { $PARAM['_scope_'] = ''; }
	$_config = getFunctionConfig('printResults'.$PARAM['_scope_'],$conn);
	if ( isset($_config['orientation']) ) { return $_config['orientation']; } else { return ''; }
}

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
			$_stmt_array['stmt'] = "SELECT keymachine,keyreadable,edittype,referencetag FROM ".$_table."_permissions WHERE ( `role_".$_SESSION['os_role']."` + `role_".$_SESSION['os_parent']."` ) MOD 8 < 4 ORDER BY realid"; //only select fields the user may insert!
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
				// disable unselectable and uneditable entries (keyreadable = _none or edittype = ID or editytype = *";EXPRESSION")
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
				if (($index = array_search('EXPRESSION', preg_replace('/.*\; EXPRESSION/','EXPRESSION',$key_array_add['edittype']))) !== false ) {
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
		//get allowd values of all LISTs and derivatives (multiple, compound)
		$indexes = array_keys(preg_grep('/LIST/',$edittypes));
//		$indexes = array_keys($edittypes,'LIST');
//		$indexes = array_merge($indexes,array_keys($edittypes,'LIST; MULTIPLE'));
		$indexes = array_merge($indexes,array_keys($edittypes,'CHECKBOX'));
		unset($index);
		foreach ( $indexes as $index ) {
			//setup for compound fields
			$_referencetag_array = explode(' + ',$key_array['referencetag'][$index]);
			$_edittype_array = explode(' + ',explode('; ',$key_array['edittype'][$index])[0]);
			$_cmp_lgth = sizeof($_edittype_array);
			$key_array['allowed_values'][$index] = array();
			for ( $i = 0; $i < $_cmp_lgth; $i++ ) {
				if ( $_cmp_lgth > 1 ) { $key_array['allowed_values'][$index][$i] = array(); }
				if ( $_edittype_array[$i] != 'LIST' AND $_edittype_array[$i] != 'CHECKBOX') { continue; }
				unset($_stmt_array); $_stmt_array = array(); unset($_result_array);
				$_stmt_array['stmt'] = "SELECT allowed_values FROM ".$key_array['table'][$index]."_references WHERE referencetag = ?";
				$_stmt_array['str_types'] = 's';
				$_stmt_array['arr_values'] = array($_referencetag_array[$i]);
				$_result_array = execute_stmt($_stmt_array,$conn); 
				if ($_result_array['dbMessageGood']) { 
					foreach ( $_result_array['result']['allowed_values'] as $allowed_value_json )
					{
						if ( $_cmp_lgth > 1 ) {
							$key_array['allowed_values'][$index][$i] = array_merge($key_array['allowed_values'][$index][$i],json_decode($allowed_value_json,true));
						} else {
							$key_array['allowed_values'][$index] = array_merge($key_array['allowed_values'][$index],json_decode($allowed_value_json,true));
						}
					}
					unset($edittypes[$index]);
				}					
			}
		}
		$headers = $key_array['keyreadable'];
		//transfer to js on client side
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
			<div>
				<input type="checkbox" class="import_overwrite" id="import_overwrite<?php echo($rnd); ?>" checked hidden>
				<label class="unlimitedWidth" for="import_overwrite<?php echo($rnd); ?>" title="Klicken, um zu ändern"><i class="fas fa-angle-right"></i>&nbsp;</label>
			</div>
			<div class="clear"></div>
			<input type="file" multiple class="importFile" accept=".csv,application/csv,text/csv" onchange="matchHeaders(this,this.files,'headermatch')">
		</form>
	</div>
	<div class="functions headersubmitlabel" hidden>
		<ul>
			<li><label for="headersubmit<?php echo($rnd); ?>" data-title="Header zuordnen" class="unlimitedWidth"><i class="fas fa-equals"></i></label></li>
			<li><label for="submitImport<?php echo($rnd); ?>" data-title="Jetzt importieren" class="submitimportlabel disabled unlimitedWidth"><i class="fas fa-file-import"></i></label></li>
		</ul>
	</div>
	<div class="headermatch" hidden>
		<h3>Vorgeschlagene Zuordnung</h3>
        <form class="db_options" id="formShowIdenticalMatches">
            <input type="checkbox" id="notShowIdenticalMatches" checked onchange="toggleMatches(this)">
            <label>Nur abweichende Zuordnungen anzeigen</label>
        </form>
        <div class="clear"></div>
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
							<option value="<?php echo($index); ?>"><?php echo(explode(': ',$header)[0]); ?></option>
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
				<th>Aktualisiert</th><td class="importExists">0</td>
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
		<div><b>Bitte schließe dieses Fenster nach dem Import. Für einen weiteren Import drücke dann den Importbutton erneut.</b></div>
	</div>	
<?php
}

//find main table complement of filters
//scope: FILTERS
function applyComplement (array $PARAM, $conn) {
	applyFilters($PARAM,$conn,true);
}

//search in current results
//scope: FILTERS
function applyFiltersOnResults(array $parameter, mysqli $conn)
{
	applyFilters($parameter,$conn,false,true,true,true);
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
	<?php
		if  ( ! isset($_array['massEdit']) OR sizeof($_array['massEdit']) <= 1 ) {
			?>
			<p>Sie haben keine Datensätze ausgewählt.</p>
			<?php
			return;
		}
	?>
	<p>Fenster bitte nach dem Herunterladen schließen</p>
	<p>Sie exportieren <?php echo(sizeof($_array['massEdit'])-1); ?> Datensätze.</p>
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
					if ($_result_array['dbMessageGood']) { $keyreadable = explode(': ',$_result_array['result']['keyreadable'][0])[0]; }; //serializes as associative array			
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
            //do not export EXPRESSION fields
			if ( in_array($column,$_col_info['keymachine']) AND ! in_array($_col_info['edittype'][array_search($column,$_col_info['keymachine'])],array('ID','NONE')) AND ! strpos($_col_info['edittype'][array_search($column,$_col_info['keymachine'])],"; EXPRESSION") ) {
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

//$functionnamescope="functionname::scope"
function getFunctionConfig(string $functionnamescope, mysqli $conn)
{
	$functionnamescope_array = explode('::',$functionnamescope);
	$functionname = $functionnamescope_array[0];
	if ( sizeof($functionnamescope_array) > 1 ) { $functionscope = $functionnamescope_array[1]; }
	unset($_stmt_array); $_stmt_array = array();
	$_stmt_array['stmt'] = "SELECT functionconfig from os_functions where functionmachine = ?";
	$_stmt_array['str_types'] = 's';
	$_stmt_array['arr_values'] = array($functionname);
	if (isset($functionscope)) {
		$_stmt_array['stmt'] .= " AND functionscope = ?";
		$_stmt_array['str_types'] .= 's';
		$_stmt_array['arr_values'][] = $functionscope;
	}
	$_config = json_decode(execute_stmt($_stmt_array,$conn,true)['result'][0]['functionconfig'],true);
	return $_config;
}

//$functionnamescope="functionname::scope"
function getFunctionFlags(string $functionnamescope, mysqli $conn)
{
	$functionnamescope_array = explode('::',$functionnamescope);
	$functionname = $functionnamescope_array[0];
	if ( sizeof($functionnamescope_array) > 1 ) { $functionscope = $functionnamescope_array[1]; }
	unset($_stmt_array); $_stmt_array = array();
	$_stmt_array['stmt'] = "SELECT functionflags from os_functions where functionmachine = ?";
	$_stmt_array['str_types'] = 's';
	$_stmt_array['arr_values'] = array($functionname);
	if (isset($functionscope)) {
		$_stmt_array['stmt'] .= " AND functionscope = ?";
		$_stmt_array['str_types'] .= 's';
		$_stmt_array['arr_values'][] = $functionscope;
	}
	$_flags = json_decode(execute_stmt($_stmt_array,$conn,true)['result'][0]['functionflags'],true);
	return $_flags;
}

function trafficLight(array $PARAM, mysqli $conn)
{
	$tables = $PARAM['table'];
	$userconfig = getConfig($conn);
	
	$_config = getFunctionConfig('trafficLight',$conn);

	// return if no criteria are set
	if ( ! isset($_config['criteria']) ) { return; }
	
	unset($_stmt_array); $_stmt_array = array();
	$_stmt_array['stmt'] = 'SELECT iconname,tablemachine,identifiers from os_tables';
	/*$_stmt_array['str_types'] = 's';
	$_stmt_array['arr_values'] = array($table);*/
	$_table_result = execute_stmt($_stmt_array,$conn,true)['result'];
	$icon = array();
	$tableidentifiers = array();
	for ( $i = 0; $i < sizeof($_table_result); $i++ ) {
		$icon[$_table_result[$i]['tablemachine']] = $_table_result[$i]['iconname'];
		$tableidentifiers[$_table_result[$i]['tablemachine']] = $_table_result[$i]['identifiers'];
		if ( $tableidentifiers[$_table_result[$i]['tablemachine']] === '') {
			$tableidentifiers[$_table_result[$i]['tablemachine']] = array();
		} else {
			$tableidentifiers[$_table_result[$i]['tablemachine']] = json_decode($tableidentifiers[$_table_result[$i]['tablemachine']]);
		}
	}
	
	if (sizeof($tables) == 0) { return; }
	// expand templates
	// returns array of expanded criteria for one criterion
	// replaces criterion by the array values in $parent if given


	function _expandTemplate($criterialist) {
		$expanded_criterialist = json_decode(json_encode($criterialist),true);
		foreach ( $criterialist as $_index => $criterion ) {
			$expanded_criterion = array($criterion);
			if ( isset($criterion['template']) ) {
				$expanded_criterion = array();
				//due to recursion inside the loop we need/must only to use the first template var here
				foreach ( array_slice($criterion['template'],0,1,true) as $_template_var => $_template_values ) {
					if ( is_string($_template_values) ) { $_template_values = range(explode('...',$_template_values)[0],explode('...',$_template_values)[1]); }
					foreach ( $_template_values as $_template_value ) {
						$expanded_criterion_instance = json_decode(str_replace('--'.$_template_var,$_template_value,json_encode($criterion)),true);
						unset($expanded_criterion_instance['template'][$_template_var]);
						if ( sizeof($expanded_criterion_instance['template']) == 0 ) { unset($expanded_criterion_instance['template']); }
						//recursion: expand the remaining vars for this instance
						$expanded_criterion_instance_array = _expandTemplate(array($expanded_criterion_instance));
						//
						$expanded_criterion = array_merge($expanded_criterion,$expanded_criterion_instance_array);
					}
				}
			}
			//recursion: look if subcriteria have to be expanded
			foreach ( $expanded_criterion as $_attribute => $_value ) {
				if ( is_array($_value) ) { $expanded_criterion[$_attribute] = _expandTemplate($_value); }
			}
			//return the expanded criterion
			if ( gettype($_index) == "integer" ) {
				$expanded_index = array_search($criterion,$expanded_criterialist);
				array_splice($expanded_criterialist,$expanded_index,1,$expanded_criterion);
			}
		}
		return $expanded_criterialist;
	}
	
	//expand the templates
	$_config['criteria'] = _expandTemplate($_config['criteria']);
	//end expand the templates
	$rnd = rand(0,2147483647);
	?>
	<div class="imp_wrapper">
		<div class="imp_close"><i class="fas fa-times-circle" onclick="_close(this,true);"></i></div>
		<form method="post" id="reload<?php echo($rnd); ?>" class="inline tools" action="" onsubmit="callPHPFunction(document.getElementById('formChooseTables'),'trafficLight','important',''); return false;">
			<input form="reload<?php echo($rnd); ?>" id="submitReload<?php echo($rnd); ?>" type="submit" hidden />
			<label class="unlimitedWidth date" data-title="neu laden" for="submitReload<?php echo($rnd); ?>"><i class="fas fa-redo-alt"></i></label>
		</form>
		<div class="inline tools"><label for="toggleTrafficLight__config"><i class="fas fa-tools"></i></label></div>
		<input class="toggle" type="checkbox" id="toggleTrafficLight__config" hidden>
		<div>
			<form id="trafficLightSettingsForm" action="" method="post" onsubmit="callFunction(this,'changeConfig').then(()=>{ return false; }); return false;">
				<h2>Kriterien</h2>
			<?php
			$_criteria_names = array();
			foreach ( $_config['criteria'] as $criterion ) {
				if ( ! isset($_criteria_names[$criterion['associated_table']]) ) { $_criteria_names[$criterion['associated_table']] = array(); }
				$_criteria_names[$criterion['associated_table']][] = $criterion['name'];
				$_criteria_names[$criterion['associated_table']] = array_unique($_criteria_names[$criterion['associated_table']]);
				asort($_criteria_names[$criterion['associated_table']]); 
			}
			foreach ( $_criteria_names as $assoc_table => $criteria_names_of_assoc_table ) {
				?>
				<h3><i class="fas fa-<?php html_echo($icon[$assoc_table]); ?>"></i></h3>
				<?php
				foreach ( $criteria_names_of_assoc_table as $criterion_name ) {
					$checked = "checked";
					if ( isset($userconfig['trafficLight']) AND ! in_array($criterion_name,$userconfig['trafficLight']) ) { $checked = ""; }
				?>
				<input type="checkbox" name="trafficLight[]" value="<?php echo($criterion_name); ?>" onchange="document.getElementById('submitTrafficLightSettingsForm').click()" <?php echo($checked); ?>>
				<label><?php echo($criterion_name); ?></label><br />
				<?php	
				}
			}
			?>
			<input id="submitTrafficLightSettingsForm" type="submit" hidden>
			</form>
		</div>
		<h2 class="center"><i class="fas fa-traffic-light"></i></h2>
		<br />
	<?php
	$_SESSION['trafficLight'] = '[]';
	foreach ( $tables as $table ) {
		$ids = array(); $resultin = array(); $_param = array();
		foreach ( $_config['criteria'] as $criterion ){
			if ( ( ! isset($userconfig['trafficLight']) OR in_array($criterion['name'],$userconfig['trafficLight']) ) AND $criterion['associated_table'] == $table ) {
				$resultout_array = _parseCriterion($resultin,$_param,$criterion,$tables,$conn);
				$resultout = $resultout_array[0];
				$_param = $resultout_array[1];
                //execute callback if given (callback is a json string of a list of callback function names, '_' is proteceted and
                //means: go on and display result)
                if ( isset($criterion['callback']) ) {
                    $_callback_array = json_decode($criterion['callback']);
                    foreach ( $_callback_array as $_callback ) {
                        if ( function_exists('_callback_'.$_callback) AND $_callback != '_' ) {
                           '_callback_'.$_callback($table,$resultout,$conn);
                       }
                   }
                }
                //go on and display if callback is not given or contains '_'
                if ( ! isset($criterion['callback']) OR ( isset($criterion['callback']) AND in_array('_',$_callback_array) ) ) {
                    foreach ( $resultout as $id ) {
                        if ( ! is_array($ids[$criterion['table']]) OR ! isset($ids[$criterion['table']]) ) { $ids[$criterion['table']] = array(); } 
                        $_criteriondetail = ''; if ( isset($_param['id'.$id]) ) { $_criteriondetail = ": ".$_param['id'.$id]; }
                        if ( ! array_key_exists($id,$ids[$criterion['table']]) ) { $ids[$criterion['table']][$id] = array(); $ids[$criterion['table']][$id]['urgency'] = 0; $ids[$criterion['table']][$id]['criteria'] = array(); }
                        if ( $criterion['urgency'] == "+" ) { $ids[$criterion['table']][$id]['urgency'] += 1; }
                        if ( $criterion['urgency'] == "-" ) { $ids[$criterion['table']][$id]['urgency'] -= 1; }
                        if ( is_int($criterion['urgency']) ) { $ids[$criterion['table']][$id]['urgency'] = max($ids[$criterion['table']][$id]['urgency'],$criterion['urgency']); }
                        if (! substr_in_array($criterion['name'],$ids[$criterion['table']][$id]['criteria']) ) { array_push($ids[$criterion['table']][$id]['criteria'],$criterion['name'].$_criteriondetail); } 
                    }
                }
			}
		}
		//save result as session variable (so we can use this when opening such an item)
		$_SESSION['trafficLight'] = json_encode(array_merge(json_decode($_SESSION['trafficLight'],true),$ids));
		//for debug: $_SESSION['trafficLight'] = '{"opsz_aufnahme": { "568": { "urgency": 2, "criteria": ["Test nicht bestanden"] } } }';
		//
		if ( sizeof($ids) > 0 ) {
			$result_size = 0;
			foreach ( $ids as $ids_in_table ) { 
				unset($ids_in_table[0]);	
				$result_size += sizeof($ids_in_table);
			}
		?>
			<div class="tableicon"><label for="toggleTrafficLight_<?php echo($table); ?>"><i class="fas fa-<?php html_echo($icon[$table]); ?>"></i></label>&nbsp; <?php echo($result_size); ?></div>
			<input class="toggle" type="checkbox" id="toggleTrafficLight_<?php echo($table); ?>" hidden>
		<?php
		}
		foreach ( array_keys($ids) as $idstable ) {
			//use table identifiers if not set in function config
			if ( isset($_config['identifiers'][$idstable]) ) {
				$identifiers = implode(',',$_config['identifiers'][$idstable]);
			} else {
				$identifiers = implode(',',$tableidentifiers[$idstable]);
			}
			$identifiers_esc = "'".str_replace(",","','",$identifiers)."'";
			unset($_stmt_array); $_stmt_array = array();
			$_stmt_array['stmt'] = 'SELECT keymachine,keyreadable from '.$idstable.'_permissions WHERE keymachine IN ('.$identifiers_esc.')';
			$_identifiers_result = execute_stmt($_stmt_array,$conn)['result'];
			$_identifiers_readable = array_combine($_identifiers_result['keymachine'],$_identifiers_result['keyreadable']);						
			unset($_stmt_array); $_stmt_array = array();
			$_stmt_array['stmt'] = 'SELECT id_'.$idstable.','.$identifiers.' from view__'.$idstable.'__'.$_SESSION['os_role'].' WHERE id_'.$idstable.' IN ('.implode(',',array_keys($ids[$idstable])).') ORDER BY '.$identifiers;
			$_table_result = execute_stmt($_stmt_array,$conn,true)['result'];
			unset($_stmt_array); $_stmt_array = array();
			$_stmt_array['stmt'] = 'SELECT id_'.$table.',id_'.$idstable.' from view__'.$table.'__'.$_SESSION['os_role'].' WHERE id_'.$idstable.' IN ('.implode(',',array_keys($ids[$idstable])).')';
			$_assoc_table_result = execute_stmt($_stmt_array,$conn,true)['result'];
			if ( ! $_table_result OR sizeof($_table_result) == 0 ) { continue; }
			?>
			<div>
				<table>
					<tr>
						<?php
						foreach ( $_config['identifiers'][$idstable] as $identifier ) {
						?>
							<th onclick="sortTable(this);" data-title="sortieren"><?php echo($_identifiers_readable[$identifier]); ?></th>
						<?php
						}
						?>
						<th onclick="sortTable(this);" data-title="sortieren">Kriterien</th>
					</tr>
			<?php
			foreach ( $_table_result as $_item ) {
				$_rnd = rand(0,32767);
				if ( $ids[$idstable][$_item['id_'.$idstable]]['urgency'] == 1 ) { $_class = "yellow"; }
				if ( $ids[$idstable][$_item['id_'.$idstable]]['urgency'] == 2 ) { $_class = "orange"; }
				if ( $ids[$idstable][$_item['id_'.$idstable]]['urgency'] > 2 ) { $_class = "red"; }
				?>
				<tr class="<?php html_echo($_class); ?>">
					<?php
					foreach ( $_config['identifiers'][$idstable] as $identifier ) {
					?>
						<td><?php html_echo($_item[$identifier]); ?></td>
					<?php } ?>
					<td><?php html_echo(implode(' | ',$ids[$idstable][$_item['id_'.$idstable]]['criteria'])); ?></td>
					<td>
						<form method="post" id="ampelForm_<?php echo($_rnd); ?>" class="inline" action="" onsubmit="callFunction(this,'getDetails','_popup_',false,'details','updateSelectionsOfThis').then(()=>{ newEntry(this,'',''); return false; }); return false;"><input form="ampelForm_<?php echo($_rnd); ?>" value="<?php echo($_item['id_'.$idstable]); ?>" name="id_<?php echo($idstable); ?>" hidden="" type="text"><input form="ampelForm_<?php echo($_rnd); ?>" id="ampelSubmit__<?php echo($_rnd); ?>" hidden="" type="submit"></form>
						<label for="ampelSubmit__<?php echo($_rnd); ?>" data-title="ID: <?php echo($_item['id_'.$idstable]); ?>" oncontextmenu="return transportAttribution(this)"><i class="fas fa-<?php echo($icon[$idstable]); ?>"></i></label>
					</td>
					<?php
					foreach ( $_assoc_table_result as $_assoc_item ) {
						if ( $_assoc_item['id_'.$idstable] == $_item['id_'.$idstable] ) {
							$_rnd = rand(0,32767);
							?>
							<td>
								<form method="post" id="ampelForm_<?php echo($_rnd); ?>" class="inline" action="" onsubmit="callFunction(this,'getDetails','_popup_',false,'details','updateSelectionsOfThis').then(()=>{ newEntry(this,'',''); return false; }); return false;"><input form="ampelForm_<?php echo($_rnd); ?>" value="<?php echo($_assoc_item['id_'.$table]); ?>" name="id_<?php echo($table); ?>" hidden="" type="text"><input form="ampelForm_<?php echo($_rnd); ?>" id="ampelSubmit__<?php echo($_rnd); ?>" hidden="" type="submit"></form>
								<label for="ampelSubmit__<?php echo($_rnd); ?>" data-title="ID: <?php echo($_assoc_item['id_'.$table]); ?>" oncontextmenu="return transportAttribution(this)"><i class="fas fa-<?php echo($icon[$table]); ?>"></i></label>
							</td>							
							<?php
						}
					}
					?>
				</tr>
				<?php
			}
			?>
				</table>
				<br />
		</div>
		<?php 
		} 
	}
}

//concats array values of same key; used in _parseCriterion
function array_concat(array $arr1,array $arr2) {
	$_arr = array();
	foreach ( array_diff_key($arr1,$arr2) as $_key => $_value ) {
		$_arr[$_key] = $_value;
	}
	foreach ( array_diff_key($arr2,$arr1) as $_key => $_value ) {
		$_arr[$_key] = $_value;
	}
	foreach ( array_intersect_key($arr1,$arr2) as $_key => $_value ) {
		$_arr[$_key] = $_value.', '.$arr2[$_key];
	}
	return $_arr;
}

function _parseCriterion(array $resultin, array $_param, array $criterion, array $tables, mysqli $conn) {
	//make tables to views
	foreach ( $tables as $cftable ) {
		if ( isset($criterion['sql']) ){
			$criterion['sql'] = preg_replace('/([^_])('.$cftable.')/','${1}view__${2}__'.$_SESSION['os_role'],$criterion['sql']);
		} 
	}
	//included 'not': use notimplies whenever possible, since otherwise we have to define an _all array, what costs a lot if time!
	$_logical = array("and","or","not","notimplies","implies");
	$_logical = array_values(array_intersect($_logical,array_keys($criterion)));
	if ( isset($_logical[0]) ) { 
		//rewrite for some keywords before parsing subcriteria:
		switch ($_logical[0]) {
			case 'implies':
				//binary relation
				//rewrite implies(a,b) to ( !a or b )
				$criterion['or'] = array();
				$criterion['or'][0]=array("not"=>$criterion['implies'][0]);
				$criterion['or'][1]=$criterion['implies'][1];
				unset($criterion['implies']);
				$_logcial[0] = 'or';
				break;
		}
		$subcriteria = $criterion[$_logical[0]] ;
		if ( ! $subcriteria[0] ) { $subcriteria = array($subcriteria); } // this is for "not": applies only to one criterion, so is no array of criteria by design
		foreach ($subcriteria as $index => $subcriterion ) {
			$subcriterion['table'] = $criterion['table'];
			$subcriterion['associated_table'] = $criterion['associated_table'];
			$subresultout_array = _parseCriterion($resultin,$_param,$subcriterion,$tables,$conn);
			$subresultout = $subresultout_array[0];
			$_subparam = $subresultout_array[1];
//			$_param = array_merge($_param,$_subparam); //overwrites previous display values
			$_param = array_concat($_param,$_subparam); //concats all display values; os_functiom
			switch($_logical[0]) {
				case 'and':
					if ( $index == 0 ) { $resultin = $subresultout; }
					else { $resultin = array_values(array_intersect($resultin,$subresultout)); }
					break;
				case 'or': 
					$resultin = array_unique(array_merge($resultin,$subresultout));
					break;
				case 'not':
					if (! isset($_all) ) {
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "SELECT id_".$criterion['table']." AS id FROM view__".$criterion['table']."__".$_SESSION['os_role'];
						$_all = execute_stmt($_stmt_array,$conn)['result']['id'];
					}
					$resultin = array_diff($_all,$subresultout);
					break;
				case 'notimplies':
					// binary relation
					// this is special: notimplies(a,b) is equivalent to (a AND !b), so we dont need $_all but can array_diff to result of a; this is much faster!
					if ( $index == 0 ) { $resultin = $subresultout; }
					if ( $index == 1 ) { $resultin = array_diff($resultin,$subresultout); }
					break;
			}
		}
		return array($resultin,$_param);
	} else {
	//proper parsing
	//the following only works for simple queries: fix it for multiple select, from, where and 'distinct'... keywords
		$_where_array = preg_split('/ WHERE /i',$criterion['sql'],2);
		//added 2023-10-10
		if ( isset($_where_array[1]) ) { $_where = $_where_array[1]; } else { $_where = ''; }
		if ( isset($_where) AND $_where != '' ) { $_where = " WHERE ".$_where; }
		$_from_array = preg_split('/ FROM /i',$_where_array[0],2);
		$_from = $_from_array[1];
		$_select_array = preg_split('/SELECT /i',$_from_array[0],2);
		$_select = $_select_array[1];
		unset($_stmt_array); $_stmt_array = array();
		$_resultout = array (); $_param = array ();
//was:		$_stmt_array['stmt'] = "SELECT id_".$criterion['table'].",".$_select." AS PARAM FROM view__".$_from."__".$_SESSION['os_role']." WHERE ".$_where." GROUP BY id_".$criterion['table']." ORDER BY PARAM DESC";
		$_stmt_array['stmt'] = "SELECT id_".$criterion['table'].",".$_select." AS PARAM FROM ".$_from.$_where." GROUP BY id_".$criterion['table']." ORDER BY PARAM DESC";
		$_return = execute_stmt($_stmt_array,$conn,true);
		if ( isset($_return['result']) ) { 
			foreach ( $_return['result'] as $_maybe ) {
				$value = $_maybe['PARAM'];
				//what was this for? residue of a loop?
				//if ( $key == 'id_'.$criterion['table'] ) { continue; }
				$_push = false;
				switch($criterion['relation']) {
					case '>':
						if ( $value > $criterion['benchmark'] ) { $_push = true; }
						break;
					case '>=':
						if ( $value >= $criterion['benchmark'] ) { $_push = true; }
						break;
					case '<':
						if ( $value < $criterion['benchmark'] ) { $_push = true; }
						break;
					case '<=':
						if ( $value <= $criterion['benchmark'] ) { $_push = true; }
						break;
					case '=':
						if ( $value == $criterion['benchmark'] ) { $_push = true; }
						break;
					case '!=':
						if ( $value != $criterion['benchmark'] ) { $_push = true; }
						break;
					case 'contains':
						if ( strpos($value,$criterion['benchmark']) ) { $_push = true; }
						break;
					case 'beginswith':
						if ( strpos($value,$criterion['benchmark']) == 0 ) { $_push = true; }
						break;
					case 'endswith':
						if ( strpos($value,$criterion['benchmark']) == strlen($value) - strlen($criterion['benchmark']) ) { $_push = true; }
						break;
					case 'notcontains':
						if ( ! strpos($value,$criterion['benchmark']) ) { $_push = true; }
						break;
					case 'notbeginswith':
						if ( strpos($value,$criterion['benchmark']) !== 0 ) { $_push = true; }
						break;
					case 'notendswith':
						if ( strpos($value,$criterion['benchmark']) !== strlen($value) - strlen($criterion['benchmark']) ) { $_push = true; }
						break;
				}
				if ( $_push ) { 
					array_push($_resultout,$_maybe['id_'.$criterion['table']]);
					if ( isset($criterion['display']) ) {
						//need non-numeric keys for array_merge to do what we want
						if ( isset($_param['id'.$_maybe['id_'.$criterion['table']]]) ) {
							$_param['id'.$_maybe['id_'.$criterion['table']]] .= $value.' '.$criterion['display'];
						} else {
							$_param['id'.$_maybe['id_'.$criterion['table']]] = $value.' '.$criterion['display'];
						}
					}
				}
			}
		}
		return array($_resultout,$_param);
	}
}

function lock(array $PARAM, ?mysqli $conn) {
	$lock = new OpenStatAuth('','',$conn);
	$lock->lock();
	?>
	<div>
		<form id="unlockForm" method="post" onsubmit="if ( document.getElementById('unlock_user').value == '<?php echo($_SESSION['os_username']); ?>' ) { callFunction(this,'unlock','veil',false,'','unlock','').then(() => { return false; }); return false; }">
		<?php if ( isset($PARAM['error']) ) { ?>
			<div class="error"><?php echo(substr($PARAM['error'],14)); ?></div>
		<?php } ?>
			<label for="unlock_user" data-title="Benutzername"><i class="fas fa-user"></i></label>
			<input id="unlock_user" type="text" name="unlock_user" value="<?php echo($_SESSION['os_username']); ?>" readonly required <?php echo($disabled); ?>><br /><br />
			<label for="unlock_pwd" data-title="Passwort"><i class="fas fa-key"></i></label>
			<input id="unlock_pwd" type="password" name="unlock_pwd" required <?php echo($disabled); ?>><br /><br />
			<input id="unlock_test" type="submit" hidden <?php echo($disabled); ?>><br /><br />
			<label for="unlock_test" class="labelcenter"><i class="fas fa-arrow-right"></i></label>		
		</form>
	</div>
	<?php
}

function findDuplicates(array $PARAM, mysqli $conn) {
//to be implemented
}

function unlock(array $PARAM, ?mysqli $conn) {
	// Create connection
	require('../../core/data/serverdata.php');
	require('../../core/data/logindata.php');
	$conn = new mysqli($servername, $username, $password, $dbname) or die ("Connection failed.");
	mysqli_set_charset($conn,"utf8");
	// Unlock
	$unlock = new OpenStatAuth($PARAM['unlock_user'],$PARAM['unlock_pwd'],$conn);
	$success = $unlock->login();
	if ( isset($success['error']) ) { $PARAM = array('error' => $success['error']); lock($PARAM,$conn); }
	$conn->close();
	unset($servername); unset($username); unset($password); unset($dbname);
}

//thanks to https://newbedev.com/php-function-to-generate-v4-uuid and https://www.uuidgenerator.net/dev-corner/php
function uuid(string $seed='') {
	//if seed given, make it exactly 16 bytes
	if (isset($seed) AND strlen($seed) > 0) {
		$data = substr($seed,0,16);
		while ( strlen($data) < 16 ) { $data = substr($data.'#'.$data,0,16); }		
	} else {
		$data = random_bytes(16);
	}
	$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
	$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
	return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

//nothing to do on server side; this is just there to obey the function formalism
function showEmptyFields($PARAM,$conn) {
	return;
}

//function scope: GLOBAL (in statusbar)
//classes: profile section
function editProfile(array $PARAM, mysqli $conn) {
	$fields = array('vorname','name','telefon','email');
	$inputtypes = array('name'=>'text','vorname'=>'text','telefon'=>'text','email'=>'email');
	$datatypes = json_encode(array('name_private'=>'TEXT','vorname_private'=>'TEXT','telefon_private'=>'PHONE','email_private'=>'EMAIL')); //only the _private fields are visible (text) inputs
	//to do: use these types...
	unset($_stmt_array); $_stmt_array = array();
	$_stmt_array['stmt'] = 'SELECT * from os_userprofiles WHERE userid = ?';
	$_stmt_array['str_types'] = "i";
	$_stmt_array['arr_values'] = array();
	$_stmt_array['arr_values'][] = $_SESSION['os_user'];
	unset($_result);
	$_result = execute_stmt($_stmt_array,$conn,true)['result'][0];
	$_public = json_decode($_result['_public'],true);
	$_machine = json_decode($_result['_machine'],true);
	$_private = json_decode($_result['_private'],true);
	$rnd = rand(0,2147483647);
	?>
	<h2>Profil: <?php echo($_SESSION['os_username']); ?></h2>
	<p>Deine Profildaten können stets von Funktionen Deiner Sitzung verwendet werden.</p>
	<p>Sollen auch Funktionen anderer Nutzer darauf zugreifen können,
	muss <em>Funktionen</em> angehakt sein.</p>
	<p>Sollen Funktionen anderen Nutzern auch Daten aus Deinem Profil anzeigen dürfen, muss <em>Benutzer</em> angehakt sein.</p>
	<p>Die Profildaten sind gegen unbefugten Zugriff gesichert, aber nicht auf demselben hohen Niveau wie Tabelleneinträge oder Passwörter. Gib daher bitte keine sensiblen 
	privaten Informationen im Profil an.</p>
	<div class="message"><div class="dbMessage"></div></div>
	<form method="POST" onsubmit="updateProfile(this,'<?php html_echo($datatypes); ?>'); return false" id="profileCheckBoxes">
	<!-- disable submit by enter -->
		<input type="submit" disabled style="display: none" aria-hidden="true" />
		<?php if ( isset($_result) ) { ?>
			<input type="text" hidden name="profile_exists" value="true">
		<?php } ?>
		<table>
		<thead>
			<tr><th></th><th></th><th title="Funktionen anderer Benutzer dürfen das Feld benutzen, aber nicht anzeigen">Funktionen</th><th title="Andere Benutzer dürfen das Feld auch einsehen">Benutzer</th></tr>
			<tr><td></td><td></td><td><input type="checkbox" id="profileCheckFunctions" onclick="_toggleEditAll('profileCheckBoxes','profileCheckFunctions','.profileCheckFunctions')"></td><td><input type="checkbox" id="profileCheckUsers" onclick="_toggleEditAll('profileCheckBoxes','profileCheckUsers','.profileCheckUsers')"></td></tr>
		</thead>
		<tbody>
		<?php foreach ( $fields as $_field ) {
			if ( isset($_private[$_field]) ) { $this_private = $_private[$_field]; } else { $this_private = ''; } 
			if ( isset($_machine[$_field]) ) { $this_machine = $_machine[$_field]; } else { $this_machine = ''; } 
			if ( isset($_public[$_field]) ) { $this_public = $_public[$_field]; } else { $this_public = ''; } 
		?>
		<tr>
			<th class="right"><label for="profile_<?php html_echo($_field.'_private'); ?>"><?php html_echo(ucfirst(str_replace('_',' ',$_field))); ?></label></th>
			<td><input type="<?php echo($inputtypes[$_field]); ?>" value="<?php html_echo($this_private); ?>" id="profile_<?php html_echo($_field.'_private'); ?>" name="<?php html_echo($_field.'_private'); ?>"></td>
			<td><input type="checkbox" form="profileCheckBoxes" class="profileCheckFunctions" data-name="<?php html_echo($_field); ?>" data-scope="<?php echo('_machine'); ?>" <?php if ( $this_private != '' AND $this_private == $this_machine ) { echo("checked "); } ?>></td>
			<td><input type="checkbox" form="profileCheckBoxes" class="profileCheckUsers" data-name="<?php html_echo($_field); ?>" data-scope="<?php echo('_public'); ?>" <?php if ( $this_private != '' AND  $this_private == $this_public ) { echo("checked "); } ?>></td>
			<td>
				<input type="<?php echo($inputtypes[$_field]); ?>" hidden name="<?php html_echo($_field.'_machine'); ?>">
				<input type="<?php echo($inputtypes[$_field]); ?>" hidden name="<?php html_echo($_field.'_public'); ?>">
			</td>
		</tr>
		<?php } ?>			
		</tbody>
		</table>
		<div class="submitProfile" data-title="Profil speichern"><label class="submitProfileLabel" for="submitProfile<?php echo($rnd);?>"><i class="fas fa-save"></i></label><input id="submitProfile<?php echo($rnd);?>" type="submit" hidden></div>
	</form>
	<?php
}

function updateProfile(array $PARAM, mysqli $conn) {
//to be continued: save profile PARAMS in os_profiles and return success state
	//collect fields into json bundles
	$json = array();
	foreach ( array('_private','_machine','_public') as $suffix ) {
		$json[$suffix] = '[]';
		foreach ( $PARAM as $key => $value ) {
			if ( preg_match('/'.$suffix.'$/',$key) ) {
				$json[$suffix] = json_encode(array_merge(json_decode($json[$suffix],true),array(str_replace($suffix,'',$key) => $value)));
			}
		}
	}
	if ( isset($PARAM['profile_exists']) ) {
		unset($PARAM['profile_exists']);
		unset($_stmt_array); $_stmt_array = array();
		$_stmt_array['stmt'] = 'UPDATE os_userprofiles SET userid = ?,_private = ?,_machine = ?,_public = ?';
		$_stmt_array['str_types'] = "isss";
		$_stmt_array['arr_values'] = array($_SESSION['os_user'],$json['_private'],$json['_machine'],$json['_public']);
		$_stmt_array['message'] = "Profil wurde aktualisiert.";
		unset($_result);
		$_result = _execute_stmt($_stmt_array,$conn); //with execute_stmt the dbMessage does not work, instead fails and $_result is null
		
	} else {
		unset($_stmt_array); $_stmt_array = array();
		$_stmt_array['stmt'] = 'INSERT INTO os_userprofiles (userid,_private,_machine,_public) VALUES (?,?,?,?)';
		$_stmt_array['str_types'] = "isss";
		$_stmt_array['arr_values'] = array($_SESSION['os_user'],$json['_private'],$json['_machine'],$json['_public']);
		$_stmt_array['message'] = "Profil wurde angelegt.";
		unset($_result);
		$_result = _execute_stmt($_stmt_array,$conn);
	}
	return json_encode($_result);
}

function killRunningRequest(array $PARAM, mysqli $conn) {
    if ( isset($_SESSION['running_connection_id']) ) {
        $stmt_array = array();
        $stmt_array['stmt'] = "KILL CONNECTION ".$_SESSION['running_connection_id'];
        _execute_stmt($stmt_array,$conn);
        unset($_SESSION['running_connection_id']);
        return "Laufende Anfrage wurde abgebrochen.";
    } else {
        return "Es läuft gerade keine Anfrage.";
    }
}

function datetime2icsTime(string $_datetime) {
	preg_match('/(\d*)\.(\d*)\.(\d*)/',$_datetime,$matches);
	if ( isset($matches[1]) ) {
		$_datetime = str_replace($matches[0],$matches[3].$matches[2].$matches[1],$_datetime);
	}
	return str_replace('-','',str_replace(':','',str_replace(' ','T',$_datetime)));
}

function randomString(int $length) {
	return substr(str_replace('["+","/","="]','o',base64_encode(random_bytes(floor(0.75*$length)+1))),0,$length);
}
