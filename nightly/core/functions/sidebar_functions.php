<?php
function updateSidebar(array $PARAMETER, mysqli $conn, string $custom = '') 
{
	//define function to be able to show subtables recursively
	function showSubtables(string $_tablemachine,array $_result_normal, array $_result, array $_config_array) {
		$table_index = array_search($_tablemachine,$_result_normal['tablemachine']);
		if ( sizeof(array_keys($_result_normal['parentmachine'],$_tablemachine)) > 0 ) {
			$_subrnd = rand(0,2147483647);
		?>
		<label for="toggleSubtables<?php echo($_subrnd); ?>" class="chooseSubtable">
			<i class="fas fa-table"></i>
		</label>
		<input
			type="checkbox"
			hidden
			class="toggle"
			id="toggleSubtables<?php echo($_subrnd); ?>"
			name="showSubtablesOf[]"
			value="<?php echo($_tablemachine); ?>"
			<?php if ( isset($_config_array['showSubtablesOf']) AND in_array($_tablemachine,$_config_array['showSubtablesOf']) ) { ?>
			checked
			<?php } ?>
		>
		<?php
		}
		if ( sizeof(array_keys($_result_normal['parentmachine'],$_tablemachine)) > 1 ) { ?>
			<div class="chooseSubtable">
				<span style="display: inline-block; width: <?php echo($_config_array['table_hierarchy'][$table_index]+1.8); ?>rem">&nbsp;</span>
				<input 
					id="add_<?php html_echo($_tablemachine); ?>_allsubtables" 
					type="checkbox" 
					onclick="_toggleEditAll('formChooseTables',this.id,'.subtableOf<?php echo($_tablemachine); ?>');"
					<?php if ( ! isset($_config_array['subtable']) ) { ?>checked<?php }; ?>
				/>
			</div>
		<?php }
		foreach ( array_keys($_result_normal['parentmachine'],$_tablemachine) as $subtable_index ) {
			$_subtable = $_result[$subtable_index];
			if ( in_array($_SESSION['os_role'],json_decode($_subtable['allowed_roles'])) OR in_array($_SESSION['os_parent'],json_decode($_subtable['allowed_roles'])) ) { ?>
			<div class="chooseSubtable">
				<span style="display: inline-block; width: <?php echo($_config_array['table_hierarchy'][$table_index]+1.8); ?>rem">&nbsp;</span>
				<input 
					name="subtable[]" 
					id="add_<?php html_echo($_subtable['tablemachine']); ?>" 
					type="checkbox" 
					value="<?php html_echo($_subtable['tablemachine']); ?>"
					class="subtableOf<?php echo($_tablemachine); ?>"
					onchange="updateTime(this);"
					form="formChooseTables"
					<?php if ( ! isset($_config_array['subtable']) OR ( isset($_config_array['subtable']) AND in_array($_subtable['tablemachine'],$_config_array['subtable']) ) ) { ?>checked<?php }; ?>
				/>
				<label for="add_<?php html_echo($_subtable['tablemachine']); ?>"><i class="fas fa-<?php html_echo($_subtable['iconname']); ?>"></i> <?php html_echo($_subtable['tablereadable']); ?></label><br>
				<?php 
					showSubtables($_subtable['tablemachine'],$_result_normal,$_result,$_config_array);
				?>
			</div>
			<?php 
			}
		}
	}
	
	if ( ! isset($PARAMETER['table']) ) { $PARAMETER['table'] = array('os_all'); };
	$table = $PARAMETER['table'][0];

	//get user config
	$_config_array = getConfig($conn);
	unset($_stmt_array); $_stmt_array = array();
	$config_save_class = "saved";
	$config_remove_class = "";
	if ( isset($_config_array['configname']) ) {
		$_config_custom = getConfig($conn,$_config_array['configname']);
		if ( $custom != '' ) { $_config_array = $_config_custom; }
		if ( ! isset($_config_array['table_hierarchy']) ) { $_config_array['table_hierarchy'] = array(); }
		if ( ! isset($_config_custom['table_hierarchy']) ) { $_config_custom['table_hierarchy'] = array(); }
		$_compare1 = array(); $_compare2 = array();
		//compare filters, tables and hierarchy
		$_compare1['filters'] = $_config_array['filters']; $_compare2['filters'] = $_config_custom['filters'];
		$_compare1['table'] = $_config_array['table']; $_compare2['table'] = $_config_custom['table'];
		$_compare1['table_hierarchy'] = $_config_array['table_hierarchy']; $_compare2['table_hierarchy'] = $_config_custom['table_hierarchy'];
//		unset($_compare1['configname']); unset($_compare2['configname']);
		if ( $_compare1 == $_compare2 ) { $config_save_class = "disabled"; } else { $config_save_class = "unsaved"; }
		unset($_compare1); unset($_compare2);
		if ( $_config_array['configname'] == 'Default') { $config_remove_class = 'disabled'; } 
	}
	$_config = $_config_array['filters'];
	//parse filter options
	$option_complement = '';
	$option_searchinresults = '';
    $option_statsonly = '';
    $option_compress = '';
	if ( isset($_config['os_OPTIONS']) ) {
			if ( is_array($_config['os_OPTIONS']) AND in_array('complement',$_config['os_OPTIONS']) ) { $option_complement = 'checked'; $option_complement_table = $table; }
			//complement may now also be an array 'complement' => tablemachine
            if ( $option_complement === '' AND is_array($_config['os_OPTIONS']) ) {
                if ( array_key_exists('complement',$_config['os_OPTIONS']) AND isset($_config['os_OPTIONS']['complement']['checked']) ) {
                    $option_complement = 'checked'; $option_complement_table = $_config['os_OPTIONS']['complement']['table'];
                }
            }
			if ( is_array($_config['os_OPTIONS']) AND in_array('searchinresults',$_config['os_OPTIONS']) ) { $option_searchinresults = 'checked'; }
			if ( is_array($_config['os_OPTIONS']) AND in_array('statsonly',$_config['os_OPTIONS']) ) { $option_statsonly = 'checked'; }
			if ( is_array($_config['os_OPTIONS']) AND in_array('compress',$_config['os_OPTIONS']) ) { $option_compress = 'checked'; }
	}
	$_config_tables = $_config_array['table'];
	$_config_hierarchy = $_config_array['table_hierarchy'];
	$_stmt_array['stmt'] = "SELECT configname FROM os_userconfig_".$_SESSION['os_user'];
	$options = execute_stmt($_stmt_array,$conn)['result']['configname'];
//determine table hierarchy structure and table data
	unset($_stmt_array); $_stmt_array = array(); $table_array = array();
	$_stmt_array['stmt'] = "SELECT iconname,tablemachine,tablereadable,allowed_roles FROM os_tables WHERE tablemachine IN ('".implode("','",$_config_tables)."') ORDER BY FIELD (tablemachine,'".implode("','",$_config_tables)."') ";
	$_result_array = execute_stmt($_stmt_array,$conn,true); //keynames as last array field 
	if ($_result_array['dbMessageGood']) { $tables_array = $_result_array['result']; } else {
	?>
		<div class="section">Fehler bei Zugriff auf Tabellen. Bitte wiederholen Sie die Aktion.</div>
	<?php
        print_r($_stmt_array);
		return;
	};
	//array of children of a table
	$_children_table = array(); unset($tindex);
	$_oldhier = -1;
	foreach ( $_config_tables as $tindex => $table )
	{
		if ( ! isset($_config_hierarchy[$tindex]) ) { $_config_hierarchy[$tindex] = min($tindex,1); }
		$_config_hierarchy[$tindex] = max(min($tindex,1),min($_config_hierarchy[$tindex],$_oldhier+1));
		$_oldhier = $_config_hierarchy[$tindex];
	}
	$_parent_table = array(); unset($tindex);
	
	foreach ( $_config_tables as $tindex => $table )
	{
		$_parent_table[$_config_hierarchy[$tindex]] = $table;
		if ( $_config_hierarchy[$tindex] >= 1 ) { 
			if ( isset($_parent_table[$_config_hierarchy[$tindex]-1]) AND isset($_children_table[$_parent_table[$_config_hierarchy[$tindex]-1]]) ) {
				array_push($_children_table[$_parent_table[$_config_hierarchy[$tindex]-1]],$table); 
			} else {
				$_children_table[$_parent_table[$_config_hierarchy[$tindex]-1]] = array($table);
			}
		}
	}
    
    //save computed hierarchy, otherwise the computed hierarchy and the one used in applyFilters differ (is read freshly from config)
    ///(...with unpredictable results...)
    ksort($_config_hierarchy);
    changeConfig(array("table_hierarchy" => array_values($_config_hierarchy)),$conn);
	?>
	<div id="config" class="section">
		<form id="formChooseConfig" class="noform" method="post" action="" onsubmit="callFunction(this,'copyConfig').then(()=>callFunction('_','updateSidebarCustom','sidebar')).then(()=>{ toggleHelpTexts(); return false; }); return false;" >
		<?php //save button and load input like in openStat.plan explained ?>
			<label 
				for="config_save" 
				class="<?php echo($config_save_class); ?>" 
				data-title="Konfiguration speichern"
				<?php if ( $config_save_class == "disabled" ) { ?>onclick="return false;"<?php } ?>
			><i class="fas fa-save"></i></label>
			<input hidden type="submit" id="config_save">
			<label class="load <?php echo($config_save_class); ?>" for="config_load" data-title="Konfiguration laden"><i class="fas fa-clipboard-check"></i></label>
			<input <?php echo($config_save_class); ?> hidden type="button" id="config_load" onclick="callFunction(this.closest('form'),'changeConfig').then(()=>callFunction('_','updateSidebarCustom','sidebar')).then(()=>callFunction(document.querySelector('form#formChooseTables'),'changeConfig')).then(()=>callFunction('_','updateSidebarCustom','sidebar')).then((result)=>{ toggleHelpTexts(); return result; });">
			<label class="config_export <?php echo($config_remove_class); ?>" for="config_export" data-title="Konfiguration exportieren"><i class="fas fa-file-export"></i></label>
			<input <?php echo($config_remove_class); ?> hidden type="button" id="config_export" onclick="callPHPFunction(this.closest('form'),'exportConfig'); return false;">
			<label class="config_import" for="config_import" data-title="Konfiguration importieren"><i class="fas fa-file-import"></i></label>
			<input hidden type="button" id="config_import" onclick="callPHPFunction(this.closest('form'),'importConfig'); return false;">
			<label class="<?php echo($config_remove_class); ?> " for="config_remove" data-title="Konfiguration löschen"><i class="fas fa-trash-alt"></i></label>
			<input <?php echo($config_remove_class); ?> hidden type="button" id="config_remove" onclick="_onAction('delete',this.closest('form'),'removeConfig'); document.getElementById('db__config__text').value = 'Default'; document.getElementById('db__config__list').value = 'Default'; callFunction(this.closest('form'),'changeConfig').then(()=>callFunction('_','updateSidebarCustom','sidebar')).then(()=>{ toggleHelpTexts(); return false; }); return false;">
			<div class="unite">
				<label for="db__config__list"></label>
				<input type="text" id="db__config__text" name="configname" class="db_formbox" value="" autofocus disabled hidden>
				<select id="db__config__list" name="configname" class="db_formbox" onchange="callFunction(this.closest('form'),'changeConfig').then(()=>callFunction('_','updateSidebarCustom','sidebar')).then(()=>callFunction(document.querySelector('form#formChooseTables'),'changeConfig')).then(()=>callFunction('_','updateSidebarCustom','sidebar')).then((result)=>{ toggleHelpTexts(); return result; });">
<!--				<select id="db__config__list" name="configname" class="db_formbox" onchange="callFunction(this.closest('form'),'changeConfig'); callFunction('_','updateSidebarCustom','sidebar'); setTimeout(function(){callFunction(document.querySelector('form#formChooseTables'),'changeConfig'); return callFunction('_','updateSidebarCustom','sidebar');},500);"> -->
				<!--	<option value="none"></option> -->
					<?php foreach ( $options as $value ) { 
						$_sel = '';
						if ( isset($_config_array['configname']) AND $_config_array['configname'] == $value ) { $_sel = 'selected'; };
						?>				
						<option value="<?php echo($value); ?>" <?php echo($_sel); ?> ><?php echo($value); ?></option>
					<?php } ?>
				</select>
				<label class="toggler" for="minus_config">&nbsp;<i class="fas fa-arrows-alt-h"></i></label>
				<input id="minus_config" class="minus" type="button" value="+" onclick="_toggleOption('_config_')" data-title="Erlaubt die Eingabe eines neuen Wertes" hidden>
			</div>
			<div class="clear"></div>
		</form>
	</div>
	<div id="tables" class="section">
		<?php updateTime(); includeFunctions('TABLES',$conn); ?>
		<label for="notoggleTables"><h1 class="center"><i class="fas fa-table"></i></h1></label>
		<input type="checkbox" hidden id="notoggleTables" class="notoggle">
		<form id="formChooseTables" class="noform function" method="post" action="" onsubmit="callFunction(this,'changeConfig').then(()=>callFunction('_','updateSidebar','sidebar')).then(()=>{ processFunctionFlags(this.closest('.section')); toggleHelpTexts(); return false; });return false;" >
			<div class="empty section" ondragover="allowDrop(event)" ondrop="drop(event,this)" ondragenter="dragenter(event)" ondragleave="dragleave(event)"></div>
			<input type="text" hidden value="_none_" name="subtable[]">
			<input type="text" hidden value="_none_" name="showSubtablesOf[]">
			<?php
				//the hidden subtable input above allows to choose no subtable at all
				unset($_stmt_array); $_stmt_array = array();
				$_stmt_array['stmt'] = "SELECT iconname,tablemachine,tablereadable,allowed_roles,parentmachine FROM os_tables";
				$_result_array = execute_stmt($_stmt_array,$conn,true); 
				$_result_array_normal = execute_stmt($_stmt_array,$conn); //first keynames then rows 
				if ($_result_array['dbMessageGood']) 
				{
					unset($_result);
					$_result = $_result_array['result'];
					$_result_normal = $_result_array_normal['result'];
                    //set tablename translation as session variable (other functions need that...)
                    $_SESSION['tablenames'] = array_combine($_result_normal['tablemachine'],$_result_normal['tablereadable']);
                    //back to sidebar...
					foreach ( $_config_tables as $table_index => $checked_tablemachine )
					{
						$_table = $_result[array_search($checked_tablemachine,$_result_normal['tablemachine'])];
						if ( ! isset($_config_hierarchy[$table_index]) ) {
							if ( $table_index == 0 ) { $_config_hierarchy[$table_index] = 0; } else { $_config_hierarchy[$table_index] = 1; }
						}
						if ( in_array($_SESSION['os_role'],json_decode($_table['allowed_roles'])) OR in_array($_SESSION['os_parent'],json_decode($_table['allowed_roles'])) ) { ?>
							<div id="table_<?php html_echo($_table['tablemachine']); ?>" draggable="true" ondragover="allowDrop(event)" ondrop="drop(event,this)" ondragstart="drag(event)" ondragenter="dragenter(event)" ondragleave="dragleave(event)" ondragend="dragend(event)">
								<?php if ( $table_index > 0 ) { ?>
									<span style="display: inline-block; width: <?php echo($_config_hierarchy[$table_index]); ?>em">&nbsp;</span><span class="hier_up" onclick="hierarchy(this,-1);">&nbsp;</span><span>&rdsh;</span><span class="hier_down" onclick="hierarchy(this,1);">&nbsp;</span>
								<?php } ?>
								<input class="hierarchy" name="table_hierarchy[]" hidden type="number" value="<?php echo($_config_hierarchy[$table_index]); ?>">
								<input 
									name="table[]" 
									id="add_<?php html_echo($_table['tablemachine']); ?>" 
									type="checkbox" 
									value="<?php html_echo($_table['tablemachine']); ?>"
									onchange="updateTime(this);"
									<?php if ( in_array($_table['tablemachine'],$_config_tables) ) { ?>checked<?php }; ?>
								/>
								<label for="add_<?php html_echo($_table['tablemachine']); ?>"><i class="fas fa-<?php html_echo($_table['iconname']); ?>"></i> <?php html_echo($_table['tablereadable']); ?></label><br>
								<?php
								//recursively show subtables
								//$_tablemachine is the actual parameter; everything else has just to be passed
								showSubtables($_table['tablemachine'],$_result_normal,$_result,$_config_array);	
								?>
							</div>
						<?php }			
					}
					unset($_table);
					foreach ( $_result as $_table )
					{
						if ( in_array($_table['tablemachine'],$_config_tables) OR $_table['parentmachine'] != '') { continue; }
						if ( in_array($_SESSION['os_role'],json_decode($_table['allowed_roles'])) OR in_array($_SESSION['os_parent'],json_decode($_table['allowed_roles'])) ) { ?>
							<div id="table_<?php html_echo($_table['tablemachine']); ?>" draggable="true" ondragover="allowDrop(event)" ondrop="drop(event,this)" ondragstart="drag(event)" ondragenter="dragenter(event)" ondragleave="dragleave(event)" ondragend="dragend(event)"> 
								<input 
									name="table[]" 
									id="add_<?php html_echo($_table['tablemachine']); ?>" 
									type="checkbox" 
									value="<?php html_echo($_table['tablemachine']); ?>"
									onchange="updateTime(this);"
									<?php if ( in_array($_table['tablemachine'],$_config_tables) ) { ?>checked<?php }; ?>
								/>
								<label for="add_<?php html_echo($_table['tablemachine']); ?>"><i class="fas fa-<?php html_echo($_table['iconname']); ?>"></i> <?php html_echo($_table['tablereadable']); ?></label><br>
							</div>
							<?php }
					}
					
				} 			
			?>
			<hr>
			<label for="formTablesSubmit" class="submitAddFilters" data-title="Tabellenauswahl speichern und anwenden"><h2 class="center"><i class="fas fa-arrow-circle-down"></i> anwenden</h2></label>
			<input hidden id="formTablesSubmit" type="submit" value="Aktualisieren">
		</form>
	</div>
	<div id="filters" class="section"> <!-- class 'section' added on 20220218-->
		<?php updateTime(); includeFunctions('FILTERS',$conn); ?>		
		<div id="addfilters">
			<label for="toggleAddFilter"><h2 class="center"><i class="fas fa-filter"></i><i class="fas fa-plus"></i></h2></label>
			<input type="checkbox" hidden id="toggleAddFilter" class="toggle">
			<form id="formAddFilters" class="form" method="post" action="../php/addFilters.php" onsubmit="addFilters(this); return false;">
					<label class="submitAddFilters" for="submitAddFilters"><h2 class="center"><i class="fas fa-exchange-alt"></i></h2></label>
					<input hidden id="submitAddFilters" type="submit" value="Auswählen" ><br />
				<?php
					unset($table); unset($tindex); //added 2019-09-03
					foreach ( $_config_tables as $tindex => $table )
					{
						$table_array = $tables_array[$tindex];
						?>
						<h2><i class="fas fa-<?php html_echo($table_array['iconname']); ?>"></i><?php html_echo($table_array['tablereadable']); ?></h2>
						<div>
						<?php 
						unset($_stmt_array); $_stmt_array = array(); $key_array = array();
						$_stmt_array['stmt'] = "SELECT keymachine,keyreadable,edittype,subtablemachine FROM ".$table."_permissions ORDER BY CAST(realid AS DECIMAL(6,3))";
						$_result_array = execute_stmt($_stmt_array,$conn,true); //keynames as last array field
						if ($_result_array['dbMessageGood']) { $key_array = $_result_array['result']; };
						//split in jenks top and bottom natural parts
						[$jenks_top,$jenks_bottom] = jenks($_SESSION['os_user'],$table,$key_array,$_config,$conn);
						//
						if ( isset($_children_table[$table]) ) {
							foreach ( $_children_table[$table] as $_child ) {
								if ( ! array_key_exists($table.'__'.$_child,$_config) ) 
								{ ?> 
								<input 
									name="<?php html_echo($table.'__'.$_child); ?>" 
									id="add_<?php html_echo($table.'__'.$_child); ?>" 
									type="checkbox" 
									value="add"
								/>
								<label for="add_<?php html_echo($table.'__'.$_child); ?>"># <i class="fas fa-<?php html_echo($tables_array[array_search($_child,$_config_tables)]['iconname']); ?>"></i> <strong><?php html_echo($tables_array[array_search($_child,$_config_tables)]['tablereadable']); ?></strong></label><br>
								<?php }							
							}
						}
						?>
						<?php
						foreach ( $jenks_top as $key ) 
						{ 
							//test for NONE or unchecked subtables OR artificial subtable_-key
							if ( substr($key['keymachine'],0,9) == 'subtable_' OR $key['edittype'] == 'NONE' OR ( $key['subtablemachine'] != '' AND ! in_array($key['subtablemachine'],$_config_array['subtable']) ) ) { continue; }
							//
							if ( ! array_key_exists($table.'__'.$key['keymachine'],$_config) ) 
							{ ?> 
							<input 
								name="<?php html_echo($table.'__'.$key['keymachine']); ?>" 
								id="add_<?php html_echo($table.'__'.$key['keymachine']); ?>" 
								type="checkbox" 
								value="add"
							/>
							<label for="add_<?php html_echo($table.'__'.$key['keymachine']); ?>"><?php html_echo(explode(': ',$key['keyreadable'])[0]); ?></label><br>
							<?php }
						}
						unset($key);
						if ( sizeof($jenks_bottom) > 0 ) {
						?>
							<input type="checkbox" id="<?php html_echo($table.'__jenksbottom'); ?>" class="toggle" hidden>
							<label class="more" for="<?php html_echo($table.'__jenksbottom'); ?>">
								<span class="open">Weniger...</span>
								<span class="closed">Mehr...</span>
							</label>
							<div class="jenks_bottom form" hidden>
							<?php
							foreach ( $jenks_bottom as $key ) 
							{ 
								//test for NONE or unchecked subtables OR artificial subtable_-key
								if ( substr($key['keymachine'],0,9) == 'subtable_' OR $key['edittype'] == 'NONE' OR ( $key['subtablemachine'] != '' AND ! in_array($key['subtablemachine'],$_config_array['subtable']) ) ) { continue; }
								//
								if ( ! array_key_exists($table.'__'.$key['keymachine'],$_config) ) 
								{ ?> 
								<input 
									name="<?php html_echo($table.'__'.$key['keymachine']); ?>" 
									id="add_<?php html_echo($table.'__'.$key['keymachine']); ?>" 
									type="checkbox" 
									value="add"
								/>
								<label for="add_<?php html_echo($table.'__'.$key['keymachine']); ?>"><?php html_echo(explode(': ',$key['keyreadable'])[0]); ?></label><br>
								<?php }
							}
							unset($key);
							?>
							</div>
							<?php
						}
					?>
					</div>
					<?php
					}
					unset($table); unset($_child);
				?>
			</form>
		</div>
		<hr>
		<form id="formFilters" class="function" method="post" action="" onsubmit="callFunction(this,'applyFilters','results_wrapper').then(()=>callFunction('_','updateSidebar','sidebar')).then(()=>{ rotateHistory(); processFunctionFlags(this.closest('.section')); myScrollIntoView(document.getElementById('results_wrapper')); toggleHelpTexts(); return false; }); return false; ">
			<input hidden type="number" name="page" value="0">
            <input hidden id="formFiltersSearchInResults" type="checkbox" value="searchinresults" name="os_OPTIONS[]" class="fontToggle" <?php echo($option_searchinresults); ?>>
			<label for="formFiltersSearchInResults" class="unlimitedWidth" data-title="in Ergebnissen suchen"><i class="fas fa-list"></i></label>
			<input hidden id="formFiltersComplement" type="checkbox" value="" name="os_OPTIONS[complement][checked]" class="fontToggle" <?php echo($option_complement); ?>>
			<label for="formFiltersComplement" class="unlimitedWidth" data-title="Komplement"><i class="fas fa-puzzle-piece"></i></label>
            <select name="os_OPTIONS[complement][table]" class="fontToggleChild">
                <?php $_matched=false; foreach ( $_config_tables as $_config_tablemachine ) { 
                    $_config_tablereadable = $_result[array_search($_config_tablemachine,$_result_normal['tablemachine'])]['tablereadable'];
                ?>
                    <option value="<?php html_echo($_config_tablemachine); ?>"
                        <?php if ( isset($option_complement_table) AND $_config_tablemachine == $option_complement_table ) {
                            echo(" selected");
                            $_matched = true;
                        } ?>
                    ><?php html_echo($_config_tablereadable); ?></option>
                <?php }
                    if ( ! $_matched ) { ?>
                    <option value="" selected></option>
                <?php } else { ?>
                    <option value=""></option>                    
                <?php } ?>
            </select>
			<input hidden id="formStatsOnly" type="checkbox" value="statsonly" name="os_OPTIONS[]" class="fontToggle" <?php echo($option_statsonly); ?>>
			<label for="formStatsOnly" class="unlimitedWidth" data-title="nur Statistik"><i class="fas fa-chart-simple"></i></label>
			<input hidden id="formCompress" type="checkbox" value="compress" name="os_OPTIONS[]" class="fontToggle" <?php echo($option_compress); ?>>
			<label for="formCompress" class="unlimitedWidth" data-title="Komprimieren: Tabellen ohne Filter nicht anzeigen"><i class="fas fa-compress"></i></label>
			<label for="formFiltersSubmit" class="submitAddFilters" data-title="Filter speichern und anwenden"><h1 class="center"><i class="fas fa-arrow-circle-right"></i> filtern</h1></label>
			<input hidden id="formFiltersSubmit" type="submit" value="Aktualisieren">
			<hr>
			<div class="empty section" ondragover="allowDrop(event)" ondrop="drop(event,this)" ondragenter="dragenter(event)" ondragleave="dragleave(event)"></div>
			<!-- draggable="true"; reihenfolge von oben nach unten definiert pivotansicht in results -->
			<div id="filterlist">
				<?php foreach ( $_config as $tabledotkeymachine => $checked ) {
					if ( ! is_array($checked) OR ! in_array('_all',$checked) ) { continue; } // ignore config settings that do not concern filters				 
					$tablemachinearray = explode("__",$tabledotkeymachine,2);
					$table =  $tablemachinearray[0];
					$keymachine = $tablemachinearray[1];
					unset($_stmt_array); $_stmt_array = array(); $table_array = array();
					$_stmt_array['stmt'] = "SELECT iconname FROM os_tables WHERE tablemachine = ?";
					$_stmt_array['str_types'] = "s";
					$_stmt_array['arr_values'] = array();
					$_stmt_array['arr_values'][] = $table;
					unset($_result_array);
					$_result_array = execute_stmt($_stmt_array,$conn); 
					if ($_result_array['dbMessageGood']) { $table_icon = $_result_array['result']['iconname'][0]; };

					//first look for attributions, then for normal keys
					if ( isset($_children_table[$table]) AND in_array($keymachine,$_children_table[$table]) ) {
						$keyreadable = '#'.$tables_array[array_search($keymachine,$_config_tables)]['tablereadable'];
					} else {
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "SELECT keyreadable FROM ".$table."_permissions WHERE keymachine = ?";
						$_stmt_array['str_types'] = "s";
						$_stmt_array['arr_values'] = array();
						$_stmt_array['arr_values'][] = $keymachine;
						unset($_result_array);
						$_result_array = execute_stmt($_stmt_array,$conn); 
						if ($_result_array['dbMessageGood']) { $keyreadable = explode(': ',$_result_array['result']['keyreadable'][0])[0]; } else { continue; }; //serializes as associative array			
					}
				?>
					<div 
						id="<?php html_echo($keymachine); ?>" 
						class="section" 
						draggable="true" ondragover="allowDrop(event)" ondrop="drop(event,this)" ondragstart="drag(event)" ondragenter="dragenter(event)" ondragleave="dragleave(event)" ondragend="dragend(event)"
						<?php if ( ! in_array($table,$_config_tables ) ) { ?> hidden<?php } ?>
					>
						<input 
							class="shownot"
							hidden
							name="<?php html_echo($table.'__'.$keymachine); ?>[-1]"
							id="<?php html_echo($table.'__'.$keymachine); ?>shownot"
							type="checkbox"
							value="_shownot"
							<?php if ( isset($checked[-1]) ) { ?> checked <?php } ?>
						/>
						<?php if ( ! in_array(_generateFilterStatementForKey($checked),array('','_shownot<br />')) ) { $toggle_checked = "checked"; } else { $toggle_checked = ''; } ?>
						<input type="checkbox" hidden id="toggle<?php html_echo($tabledotkeymachine); ?>" class="toggle" <?php echo($toggle_checked); ?>>
						<label for="toggle<?php html_echo($tabledotkeymachine); ?>">
							<h2>
								<i class="open fas fa-angle-down"></i>
								<i class="closed fas fa-angle-right"></i> 
								<i class="fas fa-<?php html_echo($table_icon); ?>"></i>
								<?php html_echo($keyreadable); ?>
								<label for="trash" onclick="return trash('<?php html_echo($tabledotkeymachine); ?>')">
									<i class="remove fas fa-trash-alt"></i>
								</label>
								<label class="show" for="<?php html_echo($table.'__'.$keymachine); ?>shownot">
									<i class="remove fas fa-eye">&nbsp;</i>
								</label>
								<label class="shownot" for="<?php html_echo($table.'__'.$keymachine); ?>shownot">
									<i class="remove fas fa-eye-slash">&nbsp;</i>
								</label>
							</h2>
						</label>
						<?php
							$edit = new OpenStatEdit($table,$keymachine,$conn);
							$edit->choose($checked);
							unset($edit);
						?>
					</div>
				<?php }; ?>
			</div> <!-- end of 'filterlist' -->
			<div class="empty section" ondragover="allowDrop(event)" ondrop="drop(event,this)" ondragenter="dragenter(event)" ondragleave="dragleave(event)"></div>
		</form>
	</div>
	<?php
}

function updateSidebarCustom(array $PARAMETER, mysqli $conn)
{
	updateSidebar($PARAMETER,$conn,'custom');
} 

// display=false: only return the mysql statement
function applyFilters(array $parameter, mysqli $conn, bool $_complement = false, bool $display = true, bool $changeconf = true, bool $_searchinresults = false, bool $_statsonly = false, bool $_compress = false)
{
	//do not save page number, so extract and forget it:
    if ( isset($parameter['page']) ) {
        $PAGE = $parameter['page'];
    } else {
        $PAGE = 0;
    }
    unset($parameter['page']);
    
    if ( isset($parameter) AND $changeconf ) {
		$config = array('filters'=>$parameter);
		$_wait = changeConfig($config,$conn); //give name so that php waits for return before continuing to read the config
	}

	//use config instead of parameters (since for several tables you do not want to send several forms simultaneously...)
	
	//get config;
	$_config = getConfig($conn);
	if ( $changeconf ) {
		$PARAMETER = $_config['filters'];
	} else {
		$PARAMETER = $parameter;
	}
	$TABLES = $_config['table'];
	if ( isset($_config['table_hierarchy']) ) { $HIERARCHY = $_config['table_hierarchy']; } else { $HIERARCHY = array(); }
	$maintable = $TABLES[0];
	
	//handle options (like complement, searchinresults, statsonly, <more to come>)
	$complement = $_complement;
	$searchinresults = $_searchinresults;
    $statsonly = $_statsonly;
    $compress = $_compress;
	if ( isset($PARAMETER['os_OPTIONS']) ) {
		$_filter_options = $PARAMETER['os_OPTIONS'];
		if ( is_array($_filter_options) ) {
			//give priority to passed arguments:
			$complement = ($_complement OR in_array('complement',$_filter_options) OR ( array_key_exists('complement',$_filter_options) AND isset($_filter_options['complement']['checked']) ) );
            if ( $complement ) {
                if ( isset($_filter_options['complement']) AND isset($_filter_options['complement']['table']) ) {
                    $complementtable = $_filter_options['complement']['table'];
                } else {
                    $complementtable = $maintable;
                }
            }
			$searchinresults = ($_searchinresults OR in_array('searchinresults',$_filter_options));
			$statsonly = ($_statsonly OR in_array('statsonly',$_filter_options));
			$compress = ($_compress OR in_array('compress',$_filter_options));
		}
		unset($PARAMETER['os_OPTIONS']);
	}
	
	//get edittypes (for strict or weak matches of values)
	$_edittypes = array();
	foreach ( $TABLES as $table ) {
		unset($_stmt_array); $_stmt_array = array();
		$_stmt_array['stmt'] = "SELECT keymachine,edittype,defaultvalue FROM ".$table."_permissions";
		$_tmp_result = execute_stmt($_stmt_array,$conn)['result'];
		$_edittypes[$table] = array_combine($_tmp_result['keymachine'],$_tmp_result['edittype']);
		$_defaultvalues[$table] = array_combine($_tmp_result['keymachine'],$_tmp_result['defaultvalue']);
	}	
//	if ( ! isset($PARAMETER['table']) ) { $PARAMETER['table'] = array('os_all'); };
//	$table = $PARAMETER['table'][0];
	//construct query from parameters
	unset($value); unset($tindex);
	$_SELECT = '';
	$komma = '';
	$_FROM = ' FROM '; $_JOIN = ''; $_USING = ''; // table will be viewROLE in live version
	foreach ( $TABLES as $tindex => $table )
	{
		if ( ! isset($HIERARCHY[$tindex]) ) { $HIERARCHY[$tindex] = min($tindex,1); }
	}
	$HIERARCHY[-1] = 0;
	$HIERARCHY[sizeof($TABLES)] = 1;
	$JOINSRC = array();
	$SHOWME = array(); //array of fields to be displayed in results
	unset($tindex);
	$_WHERE = '';
	$komma2 = ' WHERE (';
	$komma0 = " WHERE ";
	foreach ( $TABLES as $tindex => $table )
	{
		//add table to showme (customer request); here for economic reasons...
        //governey by $compress now
        if ( ! $compress ) {
    		array_push($SHOWME,$table.'__id_'.$table);
        }
		//select from former result if requested; also here for economic reasons...
		if ( isset($searchinresults) and $searchinresults ) {
            $_tableresults = json_decode($_SESSION['results'],true)[$table];
            //do not restrict if $_tableresults is empty (== [-1]); otherwise search in complements wont work
            if ( sizeof($_tableresults) > 1 ) {
                $_WHERE .= $komma0.'(`view__'.$table.'__'.$_SESSION['os_role'].'`.id_'.$table.' IS NULL OR `view__'.$table.'__'.$_SESSION['os_role'].'`.id_'.$table.' IN ('.implode(',',json_decode($_SESSION['results'],true)[$table]).') )';
                $komma0 = " AND ";
                $komma2 = " AND (";
            }
		}
		//
		$JOINSRC[$HIERARCHY[$tindex]] = $table;
		$_JOIN = '';
		if ( $tindex > 0 AND $HIERARCHY[$tindex] >= $HIERARCHY[$tindex-1] ) {
			$_JOIN = ' LEFT JOIN ';				
		}
		if ( $tindex > 0 AND $HIERARCHY[$tindex+1] > $HIERARCHY[$tindex] ) {
			$_JOIN .= '(';				
		}
		$_FROM .= $_JOIN . '`view__' . $table . '__' . $_SESSION['os_role'].'`';
		$_USING = ''; $HBRKT = '';
		if ( $HIERARCHY[$tindex+1] <= $HIERARCHY[$tindex] ) {
			for ( $hindex = $HIERARCHY[$tindex+1]; $hindex <= $HIERARCHY[$tindex]; $hindex++ ) {
				$_USING = ' ON `view__' . $JOINSRC[$hindex] . '__' . $_SESSION['os_role'].'`.id_'.$JOINSRC[$hindex-1].' = `view__' . $JOINSRC[$hindex-1] . '__' . $_SESSION['os_role'].'`.id_'.$JOINSRC[$hindex-1]. $HBRKT . $_USING;
				$HBRKT = ')';
			}
		}		
		$_FROM .= $_USING;
		$_SELECT .= '`view__' . $table . '__' . $_SESSION['os_role'].'`.id_'.$table.' AS '.$table.'__'.'id_'.$table.',';
/*		$_FROM .= $_JOIN . '`view__' . $table . '__' . $_SESSION['os_role'].'`' . $_USING;
		$_JOIN = ' LEFT JOIN ';
		$_USING = ' USING (id_'.$maintable.') ';
		$_SELECT .= '`view__' . $table . '__' . $_SESSION['os_role'].'`.id_'.$table.' AS '.$table.'__'.'id_'.$table.',';
		previous working! version (w/o hierarchies)*/ 
	}
	$bracket = '';
	$_ORDER_BY = ' ORDER BY ';
	$_komma_cmp = ' AND '; //conditions for different compounds in one key filter have all to be satisfied
	$_komma_cmp_entry = ' OR '; //a certain index has to match every compound
	$SHOWNOTALL = false;
	$EXT_ORDER_BY = array(); //order in case not all fields are going to be displayed
	if ( ! isset($_SESSION['filterlog']) ) { $_SESSION['filterlog'] = '[]'; }
	$filterlog = json_decode($_SESSION['filterlog'],true);
	foreach ($PARAMETER as $key=>$values) 
	{
		if ( in_array($key,array('table')) ) { continue; };
		//check for checked tables;
		$table = explode('__',$key,2)[0];
		$key = explode('__',$key,2)[1];
		if ( ! in_array($table,$TABLES) ) { continue; };
		//record users filter statistics in session variable
		if ( ! isset($filterlog[$table.'__'.$key]) ) { $filterlog[$table.'__'.$key] = 1; } else { $filterlog[$table.'__'.$key]++; }
		//sort ascending or descending
		$_sort = "";
		if ( array_key_exists(3501,$values) )
		{
			$_sort .= " DESC";
		}
		unset($values[3501]);
		//only filter/also select selection
		if ( array_key_exists(-1,$values) )
		{
			$SHOWNOTALL = true;
		} else {
			if ( ! in_array($table.'__id_'.$table,$SHOWME) ) { array_push($SHOWME,$table.'__id_'.$table); };
			array_push($EXT_ORDER_BY,$table.'__'.$key.$_sort);
			array_push($SHOWME,$table.'__'.$key);
		}
		unset($values[-1]);
		//attribution counts are given by key: <tablemachine attributed,lower hierarchy value>__<tablemachine holding attribution,higher hierarchy value>, values: 5001:[min],5002:[max]
		//implement here
		$ATTR_WHERE = ''; $ATTR_OR = '';
		if ( in_array($key,$TABLES) ) {
			//  handle negation (has to be done separately since attribution counts have to be done before the remaining parsing...)
			if ( array_key_exists(3001,$values) ) {
				$ATTR_OR_PROPER = " AND ";
				$ATTR_LE = " > ";
				$ATTR_GE = " < ";
				$ATTR_DEFAULT_MIN = 1000000000;
				$ATTR_DEFAULT_MAX = 0;
				$ATTR_AND = " OR ";
			} else {
				$ATTR_OR_PROPER = " OR ";		
				$ATTR_LE = " <= ";
				$ATTR_GE = " >= ";		
				$ATTR_DEFAULT_MAX = 1000000000;
				$ATTR_DEFAULT_MIN = 0;
				$ATTR_AND = " AND ";
			}
			$ATTR_SELECT = $komma . 'tmp__' . $table . '__' . $key . '.' . $key . ' AS ' . $table . '__' . $key;
			$ATTR_FROM = ' INNER JOIN ( SELECT `view__' . $table . '__' . $_SESSION['os_role'].'`.id_'.$table.',COUNT(DISTINCT `view__' . $key . '__' . $_SESSION['os_role'].'`.id_'.$key.') AS ' . $key . $_FROM.$_WHERE.$bracket.' GROUP BY `view__' . $table . '__' . $_SESSION['os_role'].'`.id_'.$table.' HAVING ';
			for ( $i = 0; $i < sizeof($values[5003]); $i++ ) {
				if ( $values[5001][$i] === '' AND $values[5002][$i] === '' ) {
					$values[5001][$i] = $ATTR_DEFAULT_MIN;
					$values[5002][$i] = $ATTR_DEFAULT_MAX;
				}
				if ( $values[5001][$i] === '' ) { $values[5001][$i] = 0; }
				if ( $values[5002][$i] === '' ) { $values[5002][$i] = 1000000000; }
				$ATTR_FROM .= $ATTR_OR.'( COUNT(DISTINCT `view__' . $key . '__' . $_SESSION['os_role'].'`.id_'.$key.') '.$ATTR_GE.$values[5001][$i].$ATTR_AND.' COUNT(DISTINCT `view__' . $key . '__' . $_SESSION['os_role'].'`.id_'.$key.') '.$ATTR_LE.$values[5002][$i].')';
				$ATTR_OR=$ATTR_OR_PROPER;
			}
			$ATTR_FROM .= ' ) AS tmp__' . $table . '__' . $key. ' ON tmp__' . $table . '__' . $key . '.id_' . $table . ' = `view__' . $table . '__' . $_SESSION['os_role'].'`.id_'.$table;	
//			$ATTR_WHERE = $komma2.'`view__' . $table . '__' . $_SESSION['os_role'].'`.id_'.$table.' IN ( SELECT `view__' . $table . '__' . $_SESSION['os_role'].'`.id_'.$table.$_FROM.$_WHERE.$bracket.' GROUP BY `view__' . $table . '__' . $_SESSION['os_role'].'`.id_'.$table.' HAVING COUNT(DISTINCT `view__' . $key . '__' . $_SESSION['os_role'].'`.id_'.$key.') >= '.$values[5001][0].' AND COUNT(DISTINCT `view__' . $key . '__' . $_SESSION['os_role'].'`.id_'.$key.') <= '.$values[5002][0].' )';
//			$_WHERE .= $ATTR_WHERE;
			$_SELECT .= $ATTR_SELECT;
			$_FROM .= $ATTR_FROM;
			$_ORDER_BY .= $komma.$table.'__'.$key.$_sort;
			$komma = ',';
//			$komma2 = ') AND (';
			continue;
		}
		//set strong or weak match strings
		switch($_edittypes[$table][$key]) {
/*	MULTIPLE cases do not yet work, since the values ar JSONized...
 * 			case 'EXTENSIBLE LIST; MULTIPLE':
			case 'LIST; MULTIPLE':
			case 'SUGGEST; MULTIPLE':
			case 'SUGGEST BEFORE LIST; MULTIPLE':
*/
//			case 'CHECKBOX':
//			case 'EXTENSIBLE CHECKBOX':
			case 'SUGGEST':
			case 'LIST':
			case 'SUGGEST BEFORE LIST':
			case 'EXTENSIBLE LIST':				
				$_MATCHSTART = "= '";
				$_MATCHEND = "'";
				$_NOT = "!";
				break;
			default:
				$_MATCHSTART = "LIKE CONCAT('%','";
				$_MATCHEND = "','%')";
				$_NOT = " NOT ";
				break;
		}
		//replace the __ by . (HTML5 replaces inner white space by _, so no . possible)
		//$key = str_replace('__','.',$key);
        //the name 'sqlforkey' comes from now obsolete VIRTUAL fields, but may be reactivated in a different way later, sp we keep it as a marker...'
        $_sqlforkey = '`view__' . $table . '__' . $_SESSION['os_role'].'`.`'.$key.'`';
        $_SELECT .= $komma.$_sqlforkey.' AS `'.$table.'__'.$key.'`';
		$_ORDER_BY .= $komma.$table.'__'.$key.$_sort;
		$komma = ',';
		$_orand = 0;
		if ( sizeof($values) > 1 ) {
	//		array_shift($values); //does a renumbering which destroys the indices 1001 and 1002 (probably also key names for that matter)
			unset($values[0]);
			if ( array_key_exists(2001,$values) AND $values[2001] == "-499" )
			{
				$_orand = 1; //" AND ";
			}
			else
			{
				$_orand = 0; //" OR ";
			}
			unset($values[2001]);
			//negation
			if ( array_key_exists(3001,$values) )
			{
				$_orand = 1 - $_orand;
				$_negation = $_NOT;
				$_ge = "<";
				$_le = ">";
				$_komma_date_multiple = " AND ";
				$_komma_date_multiple_inner = " OR ";
				$_komma_cmp = " OR ";
				$_komma_cmp_entry = " AND ";
			}
			else
			{
				$_negation = "";
				$_ge = ">=";
				$_le = "<=";
				$_komma_date_multiple = " OR ";
				$_komma_date_multiple_inner = " AND ";
				$_komma_cmp = " AND ";
				$_komma_cmp_entry = " OR ";
			}
			unset($values[3001]);
			switch($_orand) {
				case 0:
					$_komma_date_inner = " AND ";
					$_komma_outer = " OR ";
					break;
				case 1:
					$_komma_date_inner = " OR ";
					$_komma_outer = " AND ";
					break;
			}
			if ( array_key_exists(1001,$values) )
			//20200525: how to adapt for multiple dates?
			// would need a JSON_TABLE construct for comparison, but this does not (yet) exist in MariaDB, seems to come in 10.6
			// sth like this: select JSON_VALUE(JSON_QUERY(JSON_QUERY(config,'$.filters'),'$.opsz_evaluation__evalbado'),'$.1003[0]') from os_userconfig; have to test for date intervals for all array entries (not just 0)....
			// or better: select config from os_userconfig where JSON_VALUE(JSON_QUERY(JSON_QUERY(config,'$.filters'),'$.opsz_evaluation__evalbado'),'$.1003[0]') IS NOT NULL;
			{
				unset($_stmt_tmp); $_stmt_tmp = array();
				$_stmt_tmp['stmt'] = "SELECT MAX(JSON_LENGTH(".$_sqlforkey.")) AS jsonlength FROM `view__" . $table . "__" . $_SESSION['os_role']."`";
				unset($_jsonlength);
				$_jsonlength = execute_stmt($_stmt_tmp,$conn)['result']['jsonlength'][0];
				for ( $i = 0; $i < sizeof($values[1001]); $i++ )
	//			foreach ($values[1001] as $index=>$value)
				{
		//			$_WHERE .= $komma2.'(`'.$key."` = '".date("Y-m-d H:i:s",$value)."'";
					if ( ! isset($_jsonlength) OR $_jsonlength == 0 ) { $_jsonlength = 1; } 
					$_WHERE .= $komma2.' (';
					$komma3 = '';
					for ( $j = 0; $j < $_jsonlength; $j++ ) {
						$_nullallowed = false;
						$_altnull = array('','');
						if ( ! isset($values[1001][$i]) OR $values[1001][$i] === '' ) { $values[1001][$i] = '1000-01-01'; $_nullallowed = true; $_altnull = array(" OR ( ".$_sqlforkey." IS NULL ) "," OR ( JSON_VALUE(".$_sqlforkey.",'$[".$j."]') = '' ) "); }
						$_WHERE .= $komma3."(((".$_sqlforkey." NOT LIKE '[%' AND ".$_sqlforkey." ".$_ge." '".$values[1001][$i]."')".$_altnull[0];
						$_WHERE .= " OR (".$_sqlforkey." LIKE '[%' AND JSON_VALUE(".$_sqlforkey.",'$[".$j."]') ".$_ge." \"".$values[1001][$i]."\")".$_altnull[1];
						$_WHERE .= ')';
						$komma2 = $_komma_date_multiple_inner;
//						$komma2 = $_komma_date_inner;
						$bracket = ')';
						//_nullallowed is easier here, since '' < any string
						$_nullallowed = false;
						if ( ! isset($values[1002][$i]) OR  $values[1002][$i] === '' ) { $values[1002][$i] = '9999-12-31'; $_nullallowed = true; }
						$_WHERE .= $komma2."((".$_sqlforkey." NOT LIKE '[%' AND ".$_sqlforkey." ".$_le." '".$values[1002][$i]."')";
						$_WHERE .= " OR (".$_sqlforkey." LIKE '[%' AND JSON_VALUE(".$_sqlforkey.",'$[".$j."]') ".$_le." \"".$values[1002][$i]."\")";
						if ( $_nullallowed ) { $_WHERE .= " OR ( ".$_sqlforkey." IS NULL ) "; }
						$_WHERE .= '))';
						$komma2 = $_komma_outer;
						$komma3 = $_komma_date_multiple;
					$bracket = ')';
					}
					$_WHERE .= ') ';
				}
			} elseif ( array_key_exists(5001,$values) )
			{
				for ( $i = 0; $i < sizeof($values[5001]); $i++ )
	//			foreach ($values[1001] as $index=>$value)
				{
		//			$_WHERE .= $komma2.'(`'.$key."` = '".date("Y-m-d H:i:s",$value)."'";
					if ( ! isset($values[5001][$i]) OR $values[5001][$i] === '' ) { $values[5001][$i] = '0'; }
					//$_WHERE .= $komma2.'('.$_sqlforkey." ".$_ge." '".$values[5001][$i]."'"; 
					//DO NOT USE TICKS: THE CONVERSION OF STRINGS TO NUMBERS COSTS A LOT OF TIME!!!
                    $_WHERE .= $komma2.'('.$_sqlforkey." ".$_ge." ".$values[5001][$i]; 
					$komma2 = $_komma_date_inner;
					$bracket = ')';
					if ( ! isset($values[5002][$i]) OR  $values[5002][$i] === '' ) { $values[5002][$i] = '1000000000'; }
					//$_WHERE .= $komma2.''.$_sqlforkey." ".$_le." '".$values[5002][$i]."')";
					//DO NOT USE TICKS: THE CONVERSION OF STRINGS TO NUMBERS COSTS A LOT OF TIME!!!
					$_WHERE .= $komma2.''.$_sqlforkey." ".$_le." ".$values[5002][$i].")";
					$komma2 = $_komma_outer;
					$bracket = ')';
				}
			} elseif ( array_key_exists(6001,$values) )
			{
			// sorry, no better idea: construct compound filters: repeat everything else with apt JSON_QUERY and JSON_VALUE queries...
				//redefine $values: $cmp_values is normally a JSON of an array, but may also contain JSONS of arrays in keys 1001-1003,4001,4002
				$cmp_index = 0;
				$cmp_values = array();
				while ( array_key_exists(6001+$cmp_index,$values) ) {
					array_push($cmp_values,$values[6001+$cmp_index]);
					$cmp_index++;					
				}
				//get maximal multiplicity of entries
				unset($_stmt_tmp); $_stmt_tmp = array();
				$_stmt_tmp['stmt'] = "SELECT MAX(JSON_LENGTH(JSON_QUERY(".$_sqlforkey.",'$[0]'))) AS jsonlength FROM `view__" . $table . "__" . $_SESSION['os_role']."`";
				unset($_jsonlength);
				$_jsonlength = execute_stmt($_stmt_tmp,$conn)['result']['jsonlength'][0];
				//compound means: outer iteration: index of $cmp_values[0]; middle iteration: index of multiple entries (0...$jsonlength-1); inner iteration: compounds
				$_WHERE .= $komma2.' ('; $komma2 = '';
				for ( $i = 0; $i < _len($cmp_values); $i++ ) { // $i is conditionnumber;	
					$_WHERE .= $komma2.' ('; $komma2 = '';
					for ( $j = 0; $j < $_jsonlength; $j++ ) { // $j is entrynumber
						$_WHERE .= $komma2.' ('; $komma2 = '';
						for ( $compoundnumber = 0; $compoundnumber < $cmp_index; $compoundnumber++ ) {
							$_WHERE .= $komma2.' ('; // was: $_komma_cmp
							//to be continued here...; todo: check AND/OR logic; probably need another inner AND/komma4
							if ( array_key_exists(1001,$cmp_values[$compoundnumber]) )
							//20200525: how to adapt for multiple dates?
							// would need a JSON_TABLE construct for comparison, but this does not (yet) exist in MariaDB, seems to come in 10.6
							// sth like this: select JSON_VALUE(JSON_QUERY(JSON_QUERY(config,'$.filters'),'$.opsz_evaluation__evalbado'),'$.1003[0]') from os_userconfig; have to test for date intervals for all array entries (not just 0)....
							// or better: select config from os_userconfig where JSON_VALUE(JSON_QUERY(JSON_QUERY(config,'$.filters'),'$.opsz_evaluation__evalbado'),'$.1003[0]') IS NOT NULL;
							{
					//			foreach ($cmp_values[$compoundnumber][1001] as $index=>$value)
					//			$_WHERE .= $komma2.'(`'.$key."` = '".date("Y-m-d H:i:s",$value)."'";
					//			$komma3 = '';
								$_nullallowed = (((! isset($cmp_values[$compoundnumber][1001][$i])) OR ($cmp_values[$compoundnumber][1001][$i] === '')) AND ((! isset($cmp_values[$compoundnumber][1002][$i])) OR ($cmp_values[$compoundnumber][1002][$i] === '')));
								if ( ! isset($cmp_values[$compoundnumber][1001][$i]) OR $cmp_values[$compoundnumber][1001][$i] === '' ) { $cmp_values[$compoundnumber][1001][$i] = '1000-01-01'; }
					//			$_WHERE .= $komma3."((";
					//			$_WHERE .= "((";
								$_WHERE .= "(IFNULL(JSON_VALUE(JSON_QUERY(".$_sqlforkey.",'$[".$compoundnumber."]'),'$[".$j."]'),'') ".$_ge." \"".$cmp_values[$compoundnumber][1001][$i]."\")";
					//			$_WHERE .= ')';
								$komma2 = $_komma_date_multiple_inner;
		//						$komma2 = $_komma_date_inner;
								$bracket = ')';
								if ( ! isset($cmp_values[$compoundnumber][1002][$i]) OR  $cmp_values[$compoundnumber][1002][$i] === '' ) { $cmp_values[$compoundnumber][1002][$i] = '9999-12-31'; }
					//			$_WHERE .= $komma2."(";
								$_WHERE .= $komma2;
								$_WHERE .= "(IFNULL(JSON_VALUE(JSON_QUERY(".$_sqlforkey.",'$[".$compoundnumber."]'),'$[".$j."]'),'') ".$_le." \"".$cmp_values[$compoundnumber][1002][$i]."\")";
								if ( $_nullallowed ) { $_WHERE .= " OR (IFNULL(JSON_VALUE(JSON_QUERY(".$_sqlforkey.",'$[".$compoundnumber."]'),'$[".$j."]'),'') = '' )"; }
					//			$_WHERE .= '))';
								$komma2 = $_komma_cmp;
								$bracket = ')';
					//			$_WHERE .= ') ';
							} elseif ( array_key_exists(5001,$cmp_values[$compoundnumber]) )
							{
					//			$_WHERE .= $komma2.'(`'.$key."` = '".date("Y-m-d H:i:s",$value)."'";	
								if ( ! isset($cmp_values[$compoundnumber][5001][$i]) OR $cmp_values[$compoundnumber][5001][$i] === '' ) { $cmp_values[$compoundnumber][5001][$i] = '0'; }
					//			$_WHERE .= $komma2.'(JSON_VALUE(JSON_QUERY('.$_sqlforkey."`,'$[".$compoundnumber."]'),'$[".$j."]') ".$_ge." '".$cmp_values[$compoundnumber][5001][$i]."'";
                    //          DO NOT USE TICKS FOR NUMBER VALUES, see above
								$_WHERE .= $komma2.'(JSON_VALUE(JSON_QUERY('.$_sqlforkey."`,'$[".$compoundnumber."]'),'$[".$j."]') ".$_ge." ".$cmp_values[$compoundnumber][5001][$i];
								$komma2 = $_komma_date_inner;
								$bracket = ')';
								if ( ! isset($cmp_values[$compoundnumber][5002][$i]) OR  $cmp_values[$compoundnumber][5002][$i] === '' ) { $cmp_values[$compoundnumber][5002][$i] = '1000000000'; }
					//			$_WHERE .= $komma2.'(JSON_VALUE(JSON_QUERY('.$_sqlforkey.",'$[".$compoundnumber."]'),'$[".$j."]') ".$_le." '".$cmp_values[$compoundnumber][5002][$i]."')";
                    //          DO NOT USE TICKS FOR NUMBER VALUES, see above
								$_WHERE .= $komma2.'(JSON_VALUE(JSON_QUERY('.$_sqlforkey.",'$[".$compoundnumber."]'),'$[".$j."]') ".$_le." ".$cmp_values[$compoundnumber][5002][$i].")";
								$komma2 = $_komma_cmp;
								$bracket = ')';
							}			
							if ( ! array_key_exists(1001,$cmp_values[$compoundnumber]) AND ! array_key_exists(5001,$cmp_values[$compoundnumber]) )
							{
								//no: just search json entry for searchterm, so no index 4001...
								//FILESPATH searchable by filedescription field (4001)
								//if ( array_key_exists(4001,$cmp_values[$compoundnumber]) ) { $cmp_values[$compoundnumber] = $cmp_values[$compoundnumber][4001]; }
					//			$_WHERE .= $komma2.'`'.$key."` = '".$value."'";
//								$_WHERE .= $komma2.'(JSON_VALUE(JSON_QUERY(`view__' . $table . '__' . $_SESSION['os_role'].'`.`'.$key."`,'$[".$compoundnumber."]'),'$[".$j."]') ".$_negation."LIKE CONCAT('%','".$cmp_values[$compoundnumber][$i]."','%')) ";
								$_WHERE .= '(IFNULL(JSON_VALUE(JSON_QUERY('.$_sqlforkey.",'$[".$compoundnumber."]'),'$[".$j."]'),'') ".$_negation.$_MATCHSTART.$cmp_values[$compoundnumber][$i].$_MATCHEND.") ";
			//					$komma2 = " OR ";	
								$komma2 = $_komma_cmp;
								$bracket = ')';
							}
							//do not change $komma2 if no condition was set
							if ( $komma2 != ' WHERE (' ) { $komma2 = ') '.$_komma_cmp.' ('; }
							$_WHERE .= ')';
						}
						//do not change $komma2 if no condition was set
						if ( $komma2 != ' WHERE (' ) { $komma2 = ') '.$_komma_cmp_entry.' ('; }
						$_WHERE .= ')';			
					} 				
					//do not change $komma2 if no condition was set
					if ( $komma2 != ' WHERE (' ) { $komma2 = ') '.$_komma_outer.' ('; }
					$_WHERE .= ')';
				} 				
				$_WHERE .= ') ';
			} // end of compound extra tests
            //begin of NOTES filter
			elseif ( array_key_exists(7001,$values) ) 
			{
				//no multiple notes at the moment...
				//unset($_stmt_tmp); $_stmt_tmp = array();
				//$_stmt_tmp['stmt'] = "SELECT MAX(JSON_LENGTH(`view__" . $table . "__" . $_SESSION['os_role']."`.`".$key."`)) AS jsonlength FROM `view__" . $table . "__" . $_SESSION['os_role']."`";
				//unset($_jsonlength);
				//$_jsonlength = execute_stmt($_stmt_tmp,$conn)['result']['jsonlength'][0];
				$_jsonlength = 2; //[color,note]
				for ( $i = 0; $i < sizeof($values[7001]); $i++ )
	//			foreach ($values[1001] as $index=>$value)
				{
		//			$_WHERE .= $komma2.'(`'.$key."` = '".date("Y-m-d H:i:s",$value)."'";
					//if ( ! isset($_jsonlength) OR $_jsonlength == 0 ) { $_jsonlength = 1; } 
					$_WHERE .= $komma2.' (';
					$komma3 = '';
					$_nullallowed = false;
					$_altnull = "";
					if ( ! isset($values[7001][$i]) OR $values[7001][$i] === '' OR $values[7001][$i] == '[]' OR $values[7001][$i] == '["_all"]' ) { $values[7001][$i] = '["_all"]'; $_nullallowed = true; $_altnull = " OR true "; } //select all colors of notes; a bit dirty but quite short...
					$_WHERE .= $komma3."((";
					$_WHERE .= "(".$_sqlforkey." LIKE '[%' AND JSON_VALUE(".$_sqlforkey.",'$[0]') ".$_negation." IN ('".implode("','",json_decode($values[7001][$i],true))."') ".$_altnull." )";
					$_WHERE .= ')';
					$komma2 = $_komma_date_multiple_inner;
//						$komma2 = $_komma_date_inner;
					$bracket = ')';
					//_nullallowed is easier here, since '' < any string
					$_nullallowed = false;
					if ( ! isset($values[7002][$i]) OR  $values[7002][$i] === '' ) { $values[7002][$i] = ''; $_nullallowed = true; }
					$_WHERE .= $komma2."(";
					//the following line is wrong: must not be in but sth like IN LIKE... does REGEXP solve the problem? Yes, it does!
					$_WHERE .= "(".$_sqlforkey." LIKE '[%' AND JSON_VALUE(".$_sqlforkey.",'$[1]') ".$_negation." REGEXP '".$values[7002][$i]."')";
					if ( $_nullallowed ) { $_WHERE .= " OR ( ".$_sqlforkey." IS NULL ) "; }
					$_WHERE .= '))';
					$komma2 = $_komma_outer;
					$komma3 = $_komma_date_multiple;
					$bracket = ')';
					$_WHERE .= ') ';
				}			
			} //end of NOTES filter
			if ( ! array_key_exists(1001,$values) AND ! array_key_exists(5001,$values) AND ! array_key_exists(6001,$values)  AND ! array_key_exists(7001,$values) )
			{
				//no: just search json entry for searchterm, so no index 4001...
				//FILESPATH searchable by filedescription field (4001)
				//if ( array_key_exists(4001,$values) ) { $values = $values[4001]; }
				foreach ($values as $index=>$value)
				{
		//			$_WHERE .= $komma2.'`'.$key."` = '".$value."'";
					$_WHERE .= $komma2.$_sqlforkey." ".$_negation.$_MATCHSTART.$value.$_MATCHEND;
//					$komma2 = " OR ";
					$komma2 = $_komma_outer;
					$bracket = ')';
				}
			}
			//do not change $komma2 if no condition was set
			if ( $komma2 != ' WHERE (' ) { $komma2 = ') AND ('; }
		}
	}
	//update filterlog session variable
	$_SESSION['filterlog'] = json_encode($filterlog);
	//
	$_WHERE .= $bracket;
	$_WHERE = preg_replace('/IN \(\)/i',"NOT IN (-1)",$_WHERE);
	$_WHERE = preg_replace('/\(\)/',"(0=0)",$_WHERE);
	$_main_stmt_array = array();
	$_main_stmt_array['stmt'] = 'SELECT '.$_SELECT.$_FROM.$_WHERE.$_ORDER_BY; //do not order by id!
//	if ( $SHOWNOTALL ) {
		//order by ids as last ressort, e.g. if there are no other fields in this table
		foreach ( $TABLES as $activetablemachine ) {
			$EXT_ORDER_BY[] = $activetablemachine."__id_".$activetablemachine;
		}
		$_main_stmt_array['stmt'] = 'SELECT DISTINCT '.implode(',',$SHOWME).' FROM ('.$_main_stmt_array['stmt'].') AS T ORDER BY '.implode(',',$EXT_ORDER_BY);
//	}
	//generate complementary statement if desired
	//complementary means: find all ids of main table not occuring in stmt result and display maintable filters
	if ( $complement ) {
		$_SELECT = 'SELECT id_'.$complementtable.' AS '.$complementtable.'__id_'.$complementtable.',';
		$komma = '';
		$_FROM = ' FROM `view__' . $complementtable . '__' . $_SESSION['os_role'].'` '; 
        //coalesce makes a NULL to 0 and so the complement works; note: SELECT x FROM T where x NOT IN (null) is always empty!
		$_WHERE = 'WHERE id_'.$complementtable.' NOT IN ( SELECT COALESCE('.$complementtable.'__id_'.$complementtable.',0) FROM (';
		$bracket = ') AS T ) ';
		$_ORDER_BY = ' ORDER BY ';
		foreach ($PARAMETER as $key=>$values) 
		{
			if ( in_array($key,array('table')) ) { continue; };
			//check for checked tables;
			$table = explode('__',$key,2)[0];
			$key = explode('__',$key,2)[1];
			if ( $table != $complementtable ) { continue; };
            $_sqlforkey = '`view__' . $table . '__' . $_SESSION['os_role'].'`.`'.$key.'`';
			//replace the __ by . (HTML5 replaces inner white space by _, so no . possible)
			//$key = str_replace('__','.',$key);
			$_SELECT .= $komma.$_sqlforkey.' AS '.$table.'__'.$key;
			$_ORDER_BY .= $komma.$table.'__'.$key.$_sort;
			$komma = ',';
		}
        //remove trailing komma of $_SELECT if no paramater of complementtable was selected
        if ( substr($_SELECT,-1) == ',' ) { $_SELECT = substr($_SELECT, 0, -1); }
        //order by id if no filter was selected for complment table
        if ( $_ORDER_BY == ' ORDER BY ' ) { $_ORDER_BY .= $complementtable.'__id_'.$complementtable; }
		$_main_stmt_array['stmt'] = $_SELECT.$_FROM.$_WHERE.$_main_stmt_array['stmt'].$bracket.$_ORDER_BY;
	}
	if ( isset($display) AND !$display ) { return $_main_stmt_array; }
	//print_r($_main_stmt_array); //for debug only
	$filters = generateFilterStatement($PARAMETER,$conn,'os_all',$complement,$searchinresults);
	//do not apply if no filters are set now but not at the last try (double confirmation to apply empty filters; reenables login if too many tables are selected...)
    $_doapply = true;
    if ( $filters == "Keine" ) { $_doapply = false; }
    if ( isset($_SESSION['currentfilters']) AND $_SESSION['currentfilters'] == "Keine" ) { $_doapply = true;}
    //
    $_SESSION['currentfilters'] = $filters;
    if ( $_doapply ) {
        if ( ! $statsonly ) {
            //add paging info to stmt array and apply it in generateResultTable (but not in generateStatTable!)
            if ( isset( $_config['paging'] ) ) { $PAGESIZE = min($_SESSION['max_results'],(int)$_config['paging']); } else { $PAGESIZE = min($_SESSION['max_results'],$_SESSION['paging_default']); }
            $_main_stmt_array['paging'] = [$PAGE,$PAGESIZE];
            $table_results = generateResultTable($_main_stmt_array,$conn);
        } else {
            $table_results = "Nur die Statistikansicht ist aktiv.";
        }
        $stat_results = generateStatTable($_main_stmt_array,$conn);
    } else {
        $table_results = "<p>Du versuchst, ungefilterte Daten anzuzeigen. Das kann zu sehr langen Anfragen führen.</p><p>Wenn Du das trotzdem tun willst, <b>drücke 'filtern' noch einmal</b>.</p>";
        $stat_results = $table_results;
    }
	?>
	<?php updateTime(); includeFunctions('RESULTS',$conn); ?>
	<form class="hidden function"></form>
	<div id="filter_wrapper">
		<h2>Filter</h2>
		<?php echo($filters); ?>
	</div>
	<div id="stat_wrapper">
		<h2>Statistik</h2>
		<div class="comment">
			<p><span class="hits">Anzahl/Summe der Treffer in der Datenbank</span></p>
			<p><span class="unique">Anzahl der verschiedenen Einträge in der Kategorie</span></p>
		</div>
		<div id="statGraphics_wrapper">
			<div id="statGraphics_settings">
				<label class="left unlimitedWidth barchart none" onclick="_toggleGraphs(this,'barchart')"><i class="fas fa-chart-bar"></i>&nbsp;</label> 
				<label class="left unlimitedWidth piechart none" onclick="_toggleGraphs(this,'piechart')"><i class="fas fa-chart-pie"></i>&nbsp;</label> 
			</div>
			<div class="clear"></div>
			<div id="statGraphics">
				<div class="barchart hits" id="statGraphicsBarChartHits" hidden style="visibility: hidden;"></div>
				<div class="barchart unique" id="statGraphicsBarChartUnique" hidden style="visibility: hidden;"></div>
				<div class="piechart hits" id="statGraphicsPieChartHits" hidden style="visibility: hidden;"></div>
				<div class="piechart unique" id="statGraphicsPieChartUnique" hidden style="visibility: hidden;"></div>
			</div>
			<div class="clear"></div>
		</div>
		<div class="statTable">
			<?php echo($stat_results); ?>
		</div>
	</div>
	<div id="details_wrapper">
		<?php includeFunctions('RESULTDETAILS',$conn); ?>
		<h2>Details</h2>
		<div class="resultTable">
			<?php echo($table_results); ?>
		</div>
	</div>
<?php
}

function applyFiltersOnlyChangeConfig(array $parameter, mysqli $conn)
{
	applyFilters($parameter,$conn,false,false,true);
}

// returns top and bottom array of filters of given table for given user and given already chosen filters
// this is a slight variation of jenks natural breaks of two classes: we order descendingly and stop at first *local* minimum of total class
// variations (weighted by class sizes); this saves us some storage and computing time.
// indeed, it is possible to have several local minima and the index of the absolute minimum not being the index of the first local minimum, e.g.
// array(5,5,4,4,3,3,3,3,0,0) has two local minima at indices 4 and 8 (after second and third block of identical numbers), but: 
// jenks_functional(4) = 1.3 and jenks_functional(8) = 0.55
function jenks(int $userid, string $tablemachine, array $key_array, array $already_chosen, mysqli $conn) {
	//return array($key_array,array());
	//collect stats
	$_allkeystats = json_decode($_SESSION['filterstats'],true);
	if ( isset($_allkeystats[$tablemachine]) ) {
		$_keystats = $_allkeystats[$tablemachine];
	} else {
		$_keystats = array();
	}
	foreach ( $key_array as $key ) {
		if ( ! isset($_keystats[$key['keymachine']]) ) { $_keystats[$key['keymachine']] = 0; }
		if ( isset($already_chosen[$tablemachine.'__'.$key['keymachine']])	 ) { unset($_keystats[$key['keymachine']]); } //disregard already chosen keys
	}
	arsort($_keystats); //ensure descending order of frequency
	//recursively compute means and jenks value differences
	$_mu = 0; $_mubar = 0; if ( sizeof($_keystats) > 0 ) { $_mubar = array_sum($_keystats)/sizeof($_keystats); }; $_m = 0; $_n = sizeof($_keystats);
	$_break = false;
	$jenks_top_stats = array();
	foreach ( $_keystats as $_key => $_value ) {
		$_m += 1;
		//...put key,value in jenks_top...
		//test if jenks value increases again:
//		if ( ! $_break AND pow($_value - $_mu,2)/pow($_value-$_mubar,2) <= $_m*($_n-$_m+1)/($_m-1)/($_n-$_m) ) { $_break = true; } //check this formula...
		if ( ! $_break AND $_m < $_n AND ($_m-1)/$_m*pow($_value - $_mu,2) >= ($_n-$_m+1)/($_n-$_m)*pow($_value-$_mubar,2) ) { $_break = true; } //check this formula...
		if ( ! $_break ) { array_push($jenks_top_stats,$_key); } else { break; }
		$_mu += 1/$_m*($_value-$_mu);
		$_mubar -= 1/($_n-$_m)*($_value-$_mubar);
	}
	$jenks_top = array();
	$jenks_bottom = array();
	foreach ( $key_array as $key ) {
		if ( in_array($key['keymachine'],$jenks_top_stats) ) { array_push($jenks_top,$key); } else { array_push($jenks_bottom,$key); }
	}
	// if top is empty show all
	if ( sizeof($jenks_top) == 0 ) { $jenks_top = $jenks_bottom; $jenks_bottom = array(); };
	return array($jenks_top,$jenks_bottom);	
}
?>
