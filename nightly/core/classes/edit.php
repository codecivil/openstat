<?php 
// edit context: $connection, $table, $key

/* compound structures:
 * edittype = TYPE1+TYPE2+...
 * to do: 
 * 	keyreadable= Headline: readable1 + readable2 +... (now;: keyreadable = readable1 + readable2....)
 *	_getOptions($compound)
 * not here but in db_functions.php, import.php
 * 	result handling including sorting of compounds
 * 	import/export handling 
*/

class OpenStatEdit {
	
	public $table = "";
	public $key = "";
	public $connection;// = new mysqli;
	
	public function __construct(string $table, string $key, mysqli $connection) {
		$this->table = $this->_input_escape($table);
		$this->key = $this->_input_escape($key);		
		$this->connection = $connection;
	}
	
	protected function _getOptions(int $compound = -1) { // -1 means not compound, else it is the compound number
		$effective_compound = max(0,$compound);
		$table = $this->table;
		$key = $this->key;
		$options = array();
		
		$_stmt_array = array();
		$_stmt_array['stmt'] = 'SELECT typelist,edittype,referencetag,role_'.$_SESSION['os_role'].' AS role, restrictrole_'.$_SESSION['os_role'].' AS restrictrole, role_'.$_SESSION['os_parent'].' AS parentrole, restrictrole_'.$_SESSION['os_parent'].' AS restrictparentrole FROM '.$this->table.'_permissions WHERE keymachine = ?';
		$_stmt_array['str_types'] = 's';
		$_stmt_array['arr_values'] = array($this->key);
		$_result = execute_stmt($_stmt_array,$this->connection,true)['result'][0];
		$_result['edittype'] = explode(' + ',$_result['edittype'])[$effective_compound];
		$_result['referencetag'] = explode(' + ',$_result['referencetag'])[$effective_compound];
		unset($_stmt_array); $_stmt_array = array();
		$_stmt_array['stmt'] = 'SELECT keyreadable FROM '.$this->table.'_permissions WHERE keymachine = ?';
		$_stmt_array['str_types'] = "s";
		$_stmt_array['arr_values'] = array();
		$_stmt_array['arr_values'][] = $this->key;
		$_result_array = execute_stmt($_stmt_array,$this->connection); 
		if ($_result_array['dbMessageGood']) { $keyreadable = explode(' + ',$_result_array['result']['keyreadable'][0])[$effective_compound]; };			

		$rnd = rand(0,32768);
		$key = $this->key;
		$table = $this->table;
		$connection = $this->connection;
		$options = array();
		$conditions = array();
		//test permissions
		/// if not 'select':
		if ( ($_result['role']+$_result['parentrole']) % 2 > 0 ) { $_result['edittype'] = 'NONE'; } //simple way for no options etc.; does not survive to choose or edit...
		if ( $_result['restrictrole'] != '' OR $_result['restrictparentrole'] != '' ) {
			$_tmp_role = _evalRestrictions($_result['restrictrole'],'CHILD',$_SESSION['os_rolename'],$_SESSION['os_username']);
			$_tmp_parentrole = _evalRestrictions($_result['restrictparentrole'],'PARENT',$_SESSION['os_rolename'],$_SESSION['os_username']);
			$_tmp_role_array = json_decode($_tmp_role,true); if ( ! is_array($_tmp_role_array) ) { $_tmp_role_array = array(); };
			$_tmp_parentrole_array = json_decode($_tmp_parentrole,true); if ( ! is_array($_tmp_parentrole_array) ) { $_tmp_parentrole_array = array(); };
			$options = array_merge($_tmp_role_array,$_tmp_parentrole_array);
			$_result['edittype'] = 'NONE';
		}
		//
		//implement MULTIPLE (for LISTs e.g.) (elements of array as options, not the JSON encode...)
		//already done, just need to strip away the MULTIPLE part
		$_result['edittype']= explode('; ',$_result['edittype'],2)[0];
		//
		// get conditions
		unset($_stmt_array); 
		$_stmt_array['stmt'] = 'SELECT depends_on_key,depends_on_value,allowed_values FROM `'.$this->table.'_references` WHERE referencetag = ?';
		$_stmt_array['str_types'] = "s";
		$_stmt_array['arr_values'] = array();
		$_stmt_array['arr_values'][] = $_result['referencetag'];
		$conditions = execute_stmt($_stmt_array,$connection,true)['result']; //first rows, then keynames
		// get dependencies (conditions associated to depends_on_key)
		unset($_stmt_array); 
		//$_stmt_array['stmt'] = 'SELECT keymachine,referencetag,depends_on_key,depends_on_value,allowed_values FROM `'.$this->table.'_permissions` LEFT JOIN `'.$this->table.'_references` USING referencetag WHERE depends_on_key LIKE ?';
		$_stmt_array['stmt'] = "SELECT keymachine,".$this->table."_permissions.referencetag AS fullreferencetag,".$this->table."_references.referencetag AS singlereferencetag,depends_on_key,depends_on_value,allowed_values FROM `".$this->table."_permissions` LEFT JOIN `".$this->table."_references` ON ".$this->table."_permissions.referencetag LIKE CONCAT('%',".$this->table."_references.referencetag,'%') WHERE depends_on_key LIKE '".$this->key."%'";
		unset($_result_dep);
		$dependencies = execute_stmt($_stmt_array,$connection,true)['result']; //first rows, then keynames
		//!!!!!
		switch($_result['edittype']) {
			case 'TEXT':
			case 'EMAIL':
			case 'PHONE':
			case 'DATETIME': 
			case 'DATE': 
			case 'INTEGER':
			case 'DECIMAL':
			case 'TABLE':
			case 'FREE':
			case 'FREELONGER':
			case 'EDITOR':
			case 'FILESPATH':
			case 'NONE':
			case 'NOTE':
				break;
			case 'SUGGEST':
			case 'EXTENSIBLE LIST':
			case 'EXTENSIBLE CHECKBOX':
			case 'SUGGEST BEFORE LIST':
				unset($_stmt_array); 
				if ( $compound == -1 ) {
					$_stmt_array['stmt'] = 'SELECT `'.$this->key.'` FROM `view__' . $this->table . '__' . $_SESSION['os_role'].'` ORDER BY `'.$this->key.'`';
					$_here_key = $this->key;
				} else {
					$_stmt_array['stmt'] = "SELECT JSON_QUERY(`".$this->key."`,'$[" .$compound."]') FROM `view__" . $this->table . "__" . $_SESSION['os_role']."`";
					$_here_key = "JSON_QUERY(`".$this->key."`,'$[" .$compound."]')";
				}		
				$options = execute_stmt($_stmt_array,$this->connection)['result'][$_here_key];
				if ( ! isset($options) ) { $options=array(); }
				$_splice = array();
				foreach ( $options as $_index=>$option ) {
					if ( is_array(json_decode($option,true)) ) {
						$_splice[] = $option;
						$options = array_merge($options,json_decode($option,true));
					}
				}
				unset($option); unset($_index);
				$options = array_diff($options,$_splice);
			case 'FUNCTION':
			case 'CHECKBOX':
			case 'LIST':
			/* REFERENCE (tag, depends_on_key, depends_on_value, allowed_values as json); several rows with same tag are applied with AND (intersection of allowed values)
		set always a default: tag,default,default,list (not ANDed with other rows) */
				unset($_stmt_array); 
				$_stmt_array['stmt'] = 'SELECT depends_on_key,depends_on_value,allowed_values FROM `'.$this->table.'_references` WHERE referencetag = ?';
				$_stmt_array['str_types'] = "s";
				$_stmt_array['arr_values'] = array();
				$_stmt_array['arr_values'][] = $_result['referencetag'];
				unset($_result_array);
				$_result_array = execute_stmt($_stmt_array,$this->connection); 
				if ( isset($_result_array['dbMessageGood']) ) {
					$_options = $_result_array['result']['allowed_values'];
					if ( ! isset($options) ) { $options = array(); };
					foreach ( $_options as $values ) {
						//remove "***" signalling that all extended values of EXTENSIBLE LISTs are also allowed
						//remove "_SHOW_" and "_HIDE_" signalling that the field is shown or hidden
						$values = preg_replace('/\"\*\*\*\"\,/','',$values);
						$values = preg_replace('/\,\"\*\*\*\"/','',$values);
						$values = preg_replace('/\"\*\*\*\"/','',$values);
						$values = preg_replace('/\"_SHOW_\"\,/','',$values);
						$values = preg_replace('/\,\"_SHOW_\"/','',$values);
						$values = preg_replace('/\"_SHOW_\"/','',$values);
						$values = preg_replace('/\"_HIDE_\"\,/','',$values);
						$values = preg_replace('/\,\"_HIDE_\"/','',$values);
						$values = preg_replace('/\"_HIDE_\"/','',$values);
						if ( is_array(json_decode($values)) ) {
							$options = array_merge($options,json_decode($values));
						}
					}
				}
				break;
			case 'CALENDAR':
				unset($_stmt_array); 
				$options = array(array("id_os_calendars" => "_NULL_","calendarreadable" => "*kein Kalender"));
				$_stmt_array['stmt'] = 'SELECT id_os_calendars,allowed_roles,allowed_users,calendarreadable FROM `view__os_calendars__'.$_SESSION['os_role'].'`';
				$preliminary_options = execute_stmt($_stmt_array,$this->connection,true)['result'];
				foreach ( $preliminary_options as $option ) {
					//deal with empty result fields
					if ( ! isset($option['allowed_users']) OR ! json_decode($option['allowed_users']) ) { $option['allowed_users'] = '[]'; }
					if ( ! isset($option['allowed_roles']) OR ! json_decode($option['allowed_roles']) ) { $option['allowed_roles'] = '[]'; }
					//
					if ( in_array($_SESSION['os_user'],json_decode($option['allowed_users'],true)) OR in_array($_SESSION['os_role'],json_decode($option['allowed_roles'],true)) OR in_array($_SESSION['os_parent'],json_decode($option['allowed_roles'],true)) ) {
						$options[] = $option;
					}
				}
				break;
			default:
				unset($_stmt_array); 
				$_stmt_array['stmt'] = 'SELECT `'.$this->key.'` FROM `view__' . $this->table . '__' . $_SESSION['os_role'].'`';
				$options = execute_stmt($_stmt_array,$this->connection)['result'][$this->key];
				break;
		}
		if ( is_array($options) AND sizeof($options) > 0 AND ! is_array($options[0]) ) {
			$options = array_unique($options);
			asort($options);
		}
		return array("options"=>$options, "conditions"=>$conditions, "dependencies"=>$dependencies);
	}
	
	public function edit(string $_default, bool $_single = true) {
		$default = $_default;
		$_stmt_array = array();
		$_stmt_array['stmt'] = 'SELECT edittype,typelist,role_'.$_SESSION['os_role'].' AS role, restrictrole_'.$_SESSION['os_role'].' AS restrictrole, role_'.$_SESSION['os_parent'].' AS parentrole, restrictrole_'.$_SESSION['os_parent'].' AS restrictparentrole FROM '.$this->table.'_permissions WHERE keymachine = ?';
		$_stmt_array['str_types'] = 's';
		$_stmt_array['arr_values'] = array($this->key);
		$_result = execute_stmt($_stmt_array,$this->connection,true)['result'][0];
		unset($_stmt_array); $_stmt_array = array();
		$_stmt_array['stmt'] = 'SELECT keyreadable,subtablemachine FROM `'.$this->table.'_permissions` WHERE keymachine = ?';
		$_stmt_array['str_types'] = "s";
		$_stmt_array['arr_values'] = array();
		$_stmt_array['arr_values'][] = $this->key;
		$_result_array = execute_stmt($_stmt_array,$this->connection);
		if ($_result_array['dbMessageGood']) { 
			$keyreadable = $_result_array['result']['keyreadable'][0];
			$subtablemachine = $_result_array['result']['subtablemachine'][0];
		};
		$firstrnd = -1;
		$key = $this->key;
		$table = $this->table;
		//test permissions
		/// if neither update nor insert: just statement of default
		$_disabled = '';
		if ( ($_result['role']+$_result['parentrole']) % 8 > 5 OR ! $_single ) { $_disabled = 'disabled'; }
		/// add classes for edit_wrapper according to restrictions
		$_addclasses = '';
		if ( ($_result['role']+$_result['parentrole']) % 4 > 1 ) { $_addclasses .= ' noupdate'; } else { $_addclasses .= ' update'; };
		if ( ($_result['role']+$_result['parentrole']) % 8 > 3 ) { $_addclasses .= ' noinsert'; } else { $_addclasses .= ' insert'; };
		/// if restrictions are set, present them as closed list
		if ( $_result['restrictrole'] != '' OR $_result['restrictparentrole'] != '' ) { $_result['edittype'] = 'LIST'; };
		//
		// look for length restrictions in typelist
		$_maxlength = '';
		if ( preg_match('/VARCHAR/',$_result['typelist']) == 1 ) {
			$_maxlength = ' maxlength='.preg_replace('/\)/','',preg_replace('/VARCHAR\(/','',$_result['typelist'])).' ';
		}
		//
		//separate MULTIPLE, DERIVED, CHECKED, UNCHECKED keywords, e.g. EXTENSIBLE LIST; MULTIPLE or FUNCION; CHECKED
		$_tmp_array = explode('; ',$_result['edittype']);
		$_result['edittype'] = $_tmp_array[0];
		//print_r($_tmp_array);
		if ( isset($_tmp_array[1]) AND $_tmp_array[1] == 'MULTIPLE' ) { $_result['multiple'] = true; } else { $_result['multiple'] = false; };//multiple entries
		if ( isset($_tmp_array[1]) AND $_tmp_array[1] == 'DERIVED' ) { $_result['derived'] = true; } else { $_result['derived'] = false; }; //compute values from other fields
		$_result['FUNCTIONchecked'] = "user";
		if ( isset($_tmp_array[1]) AND $_tmp_array[1] == 'CHECKED' ) { $_result['FUNCTIONchecked'] = "true"; } //only for FUNCTIONS: automatically checked
		if ( isset($_tmp_array[1]) AND $_tmp_array[1] == 'UNCHECKED' ) { $_result['FUNCTIONchecked'] = "false"; } //only for FUNCTIONS: automatically checked
		unset($_tmp_array);
		//disable if derived
		if ( $_result['derived'] ) { $_addclasses = " noupdate noinsert"; $_disabled = 'disabled'; } //values of derived fields are determined by other fields
		//preliminary: compound structures
		$_tmp_array = explode(' + ',$_result['edittype']);
		$_result['edittype_array'] = $_tmp_array;
		$_result['edittype'] = $_tmp_array[0];
		$_keyreadable_array = explode(' + ',$keyreadable);
		if ( isset($_tmp_array[1]) ) { 
			$_result['compound'] = true; 
			$_keyreadable_headline = explode(': ',$_keyreadable_array[0])[0];
			$_keyreadable_array[0] = explode(': ',$_keyreadable_array[0])[1];
		} else { $_result['compound'] = false; };
		unset($_tmp_array);
		//
		if ( $_result['edittype'] != 'NONE') {
			if ( $subtablemachine != '' ) { $_addclasses .= " subtable"; }
		?>
		<div class="edit_wrapper<?php echo($_addclasses); ?>" data-subtable="<?php echo($subtablemachine); ?>">
		<?php };
		$_default_array = '';
		$_arrayed = '';
		if ( $_result['multiple'] ) {
			$_default_array = json_decode($default,true);
			$_arrayed = '[]'; //inputs as arrays
		}
		//preliminary: compound structures
		if ( $_result['compound'] ) {
			$_default_array = json_decode($default,true);
			$_arrayed = '[]'; //inputs as arrays
			?>
				<label><?php echo($_keyreadable_headline); ?></label><div class='clear'></div>
			<?php
		/*	foreach ( $_result['edittype_array'] as $_index => $_edittype ) {
				if ( ! isset($_default_array[$_index]) ) { $_default_array[$_index] = ''; }
			}
		*/
		}
		if ( ! is_array($_default_array) ) { $_default_array = array(array($default)); };
		if ( is_array($_default_array) AND ! is_array($_default_array[0]) ) { $_default_array = array($_default_array); };
		$tmpkey = $key;
		$_defaultsize = sizeof($_default_array[0]);
		$_editsize = sizeof($_result['edittype_array']);
		for ( $indexdefault = 0; $indexdefault < $_defaultsize; $indexdefault++ ) {
			$rnd = rand(0,32768);
			if ( $firstrnd == -1 ) { $firstrnd = $rnd; }
			if ( $_result['multiple'] ) {
			?>
				<div class="searchfield">
					<label  <?php echo($_disabled); ?> class="unlimitedWidth right" onclick="removeContainingDiv(this);" data-title="Feld löschen"><i class="fas fa-minus"></i></label>
			<?php
			}
			if ( ! $_single AND $_result['edittype'] != 'NONE') {
				?>
				<label class="unlimitedWidth" onclick="_toggleEnabled(<?php echo($rnd); ?>);"><i class="fas fa-pen-square"></i></label>
				<div id="enablable<?php echo($rnd); ?>" class="enablable disabled">
				<?php
			}
			for ($indexedit = 0; $indexedit < $_editsize; $indexedit++ ) {
				if ( $_editsize == 1 ) { $thiscompound = -1; } else { $thiscompound = $indexedit; }
				$_result['edittype'] = $_result['edittype_array'][$indexedit];
				//restore original key for getting options
				$this->key = $tmpkey;
				$options_array = $this->_getOptions($thiscompound);
				$options = $options_array['options'];
				$conditions = $options_array['conditions'];
				$dependencies = $options_array['dependencies'];
				if ( ! is_array($dependencies) ) { $dependencies = array(); }
				//look if component is a depends_on_key and designate component of dependent field
				$_onchange = array();
				foreach ( $dependencies as $dependency ) {
					if ( $dependency['depends_on_key'] == $this->key OR $dependency['depends_on_key'] == $this->key.'['.$indexedit.'];local' ) {
//						$_depindex = array_search($dependency['singlereferencetag'],explode(' + ',$dependency['fullreferencetag']));
						$_depindices = array_keys(explode(' + ',$dependency['fullreferencetag']),$dependency['singlereferencetag']);
						foreach ( $_depindices as $_depindex ) {
							$_onchange[] = "db_".$dependency['keymachine'];
							$_onchange[] = "db_".$dependency['keymachine'].'['.$_depindex.']';
						}
					}
				}
				$_onchange = array_values(array_unique($_onchange));
				if ( sizeof($_onchange) > 0 ) { 
					?>
					<div hidden class="dependencies"><?php echo(json_encode($_onchange)); ?></div>
					<?php
					$_onchange_text = "onchange=\"updateSelectionOfClasses(this)\"";
					$_onchange_function = "updateSelectionOfClasses(this)";
				} else { 
					$_onchange_text = ''; $_onchange_function = '';
				}
				//dirty but effective:
				//$_disabled .= " ".$_onchange;
				//
				if ( is_array($options) AND count($options) == 1 ) { $default = $options[0]; } else { $default = $_default; }
				
				if ( $_result['multiple'] AND $_result['compound'] ) {
					$key = $tmpkey.'['.$indexedit.']';
					$this->key = $key;

				}
				?>
					<div id="db_<?php echo($key.$rnd); ?>_conditions" hidden><?php html_echo(json_encode($conditions)); ?></div>
				<?php
				$default = $_default_array[$indexedit][$indexdefault];
				$keyreadable = $_keyreadable_array[$indexedit];
				//the name of input fields is not arrayed! Change this!
				switch($_result['edittype']) {
					/*
					 * FREE free text
					 * EDITOR	tinymce4 editor for free text (plugin)
					 * SUGGEST free text with suggestions from existing entries
					 * LIST closed list of options given by 'referencetag' (entry in reference table of allowed values dependent on value in other inputs:)
					 * EXTENSIBLE LIST like LIST, but with button for adding a free text values
					 * DATE
					 * INTEGER
					 * DECIMAL
					 * TABLE (json array  of "table":"keynames" as referencetag) entry has an own table with given subset of "child_"keynames identified with keynames of "table", set up as FOREIGN KEY
					*/
					case 'NONE': break;
					case 'EDITOR':
						//this is a third party plugin: tinymce4
						?>
						<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
						<textarea <?php echo($_disabled.' '.$_onchange_text); ?> id="db_<?php echo($key.$rnd); ?>" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="editor db_formbox db_<?php echo($key); ?>"  value=""><?php echo($default); ?></textarea>
						<img hidden src="" onerror="if (_enablable = this.closest('.enablable')) { _waitForEditorThenToggle(_enablable); };">
						<div class="clear"></div>
						<?php break;
					case 'SUGGEST':
						?>
						<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
						<input <?php echo($_disabled.' '.$_onchange_text.' '.$_maxlength); ?> type="text" id="db_<?php echo($key.$rnd); ?>_text" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_formbox db_<?php echo($key); ?>" value="<?php echo($default); ?>" onkeyup='_autoComplete(this,<?php echo(preg_replace("/\'/","&apos;",json_encode($options))); ?>,<?php echo(json_encode($conditions)); ?>)' autofocus>
						<div class="suggestions"></div>
						<div class="clear"></div>
						<?php break;
					case 'LIST':
						?>
						<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
						<select <?php echo($_disabled); ?> id="db_<?php echo($key.$rnd); ?>" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_formbox db_<?php echo($key); ?>" onclick="updateSelection(this);" onchange="_onResetFilter(this.value);<?php echo($_onchange_function); ?>">
						<!--	<option value="none"></option> -->
							<?php foreach ( $options as $value ) { 
								$_sel = '';
								if ( _cleanup($default) == _cleanup($value) ) { $_sel = 'selected'; };
								?>				
								<option value="<?php echo($value); ?>" <?php echo($_sel); ?> ><?php echo($value); ?></option>
							<?php } ?>
						</select>
						<div class="clear"></div>
						<?php break;
					case 'FUNCTION':
						$_show_option = array();
						foreach ( $options as $value ) {
							//check if all necessary tables are activated
							if ( $value == "none" ) { continue; }
							if ( $value != '' ) {
								//separate functionname and config part
								$function_array = explode('(',$value,2);
								$function = $function_array[0];
								if ( isset($function_array[1]) ) {
									$configname = str_replace(')','',$function_array[1]);
								}
								else {
									$configname = '';
								}
								//$ontablesflag is an array with one element containing under "ONTABLES" the array of tables or confignames and the tables..
								$ontablesflag = array_filter(getFunctionFlags($function,$this->connection), function($_flag) { return is_array($_flag) AND isset($_flag['ONTABLES']); });
								$necessary_tables_array = array();
								foreach ( $ontablesflag as $ontables_array ) {
									if ( $configname != '' AND isset($ontables_array['ONTABLES'][$configname]) ) {
										$necessary_tables_array = $ontables_array['ONTABLES'][$configname];
									} else {
										$necessary_tables_array = $ontables_array['ONTABLES'];
									}
								}
								$tables_array = array();
								if ( ! empty($necessary_tables_array) ) { //save a db query if there is nothing to test  
									$tables_array = getConfig($this->connection)['table'];
								}
								//compare those two arrays:
								if ( $necessary_tables_array == array_intersect($necessary_tables_array,$tables_array) ) {
									$_show_option[] = $value;
								}
							}
						}
						if ( empty($_show_option) ) { break; }
						//check checked status
						//function field was checked when "functions" of $default is not ["none"]
						if ( $_result['FUNCTIONchecked'] == "false" ) { $_checked = ""; }
						if ( $_result['FUNCTIONchecked'] == "true" ) { $_checked = "checked"; }
						if ( $_result['FUNCTIONchecked'] == "user" ) {
							$default_array = json_decode($default,true);
							if ( isset($default_array) AND isset($default_array['functions']) AND $default_array['functions'] != array("none") ) {
								$_checked = "checked";
							} else {
								$_checked = "";
							}
						}	
						?>				
						<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
						<input type="checkbox" <?php echo($_disabled.' '.$_checked); ?> id="db_<?php echo($key.$rnd); ?>" class="db_function_check db_<?php echo($key); ?>" onchange="_FUNCTIONobserveChanges(this)">
						<select hidden <?php echo($_disabled); ?> id="db_<?php echo($key.$rnd); ?>_functions" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_<?php echo($key); ?>  db_function_functions" onchange="_FUNCTIONobserveChanges(this)">
							<option value="none" selected ></option> 
						<!--
							Do not select any of the allowed functions, since then they may not be updated according to the conditions...
							
							It is on purpose that the select above has the same name as the input[type=text] below:
							js will querySelector the first (select): ok
							php will send the value of the second: also ok
							
							but I admit, it's a bit shaky and not good to maintain...
						-->
							<?php foreach ( $_show_option as $value ) {
								if ( $value == "none" ) { continue; }
								if ( $value != '' ) {
								?>				
								<option value="<?php echo($value); ?>"><?php echo($value); ?></option>
							<?php }
							}
							 ?>
						</select>
						<input type="number" hidden <?php echo($_disabled); ?> class="db_function_changes" value="0">
						<input type="text" hidden <?php echo($_disabled); ?> class="db_function_field" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" value="<?php echo($default); ?>">
						<!-- That is the image hack: missing src leads to error and thus to js execution even if this is added way after loading the page... -->
						<img src onerror="_FUNCTIONStatus(this,'initial')">
						<div class="clear"></div>
						<?php break;
					case 'CHECKBOX':
						?>
						<label for="db_<?php echo($key.$rnd); ?>_list" class="onlyone"><?php echo($keyreadable); ?></label>
						<div class="left checkbox">
							<?php foreach ( $options as $option ) { ?>
							<div class="left">
								<input <?php echo($_disabled.' '.$_onchange_text); ?>
									name="<?php html_echo($this->table.'__'.$this->key); ?>[]" 
									id="db_<?php html_echo($this->key.$option.$rnd); ?>" 
									type="checkbox" 
									value="<?php html_echo($option); ?>"
									<?php 
										$_default_array = json_decode($default,true);
										if ( ! is_array($_default_array) ) { $_default_array = array(); };
										if ( in_array($option,$_default_array) OR $option == $default ) { ?> checked <?php }; ?> 
								/>
								<label class="unlimitedWidth" for="db_<?php html_echo($this->key.$option.$rnd); ?>">
									<?php 
									if ( in_array($option,$_default_array) OR $option == $default ) { ?><b><?php };
									html_echo($option); 
									if ( in_array($option,$_default_array) OR $option == $default ) { ?></b><?php }; 
									?>&nbsp;&nbsp;
								</label>
							</div>
							<?php }; ?>
						</div>
						<div class="clear"></div>
						<?php break;
					case 'EXTENSIBLE CHECKBOX':
						?>
						<label for="db_<?php echo($key.$rnd); ?>_list" class="onlyone"><?php echo($keyreadable); ?></label>
						<div class="left checkbox">
							<?php 
								$_default_array = json_decode($default,true);
								if ( ! is_array($_default_array) ) { $_default_array = array(); };
								//first, the checked entries
								foreach ( $options as $option ) { 
									if ( ! in_array($option,$_default_array) AND $option != $default ) { continue; }
							?>
							<div class="left">
								<input <?php echo($_disabled.' '.$_onchange_text); ?>
									name="<?php html_echo($this->table.'__'.$this->key); ?>[]" 
									id="db_<?php html_echo($this->key.$option.$rnd); ?>" 
									type="checkbox" 
									value="<?php html_echo($option); ?>"
									checked
									/>
								<label class="unlimitedWidth" for="db_<?php html_echo($this->key.$option.$rnd); ?>">
									<?php 
									if ( in_array($option,$_default_array) OR $option == $default ) { ?><b><?php };
									html_echo($option); 
									if ( in_array($option,$_default_array) OR $option == $default ) { ?></b><?php }; 
									?>&nbsp;&nbsp;
								</label>
							</div>
							<?php }; 
								//now, the unchecked values
								foreach ( $options as $option ) { 
									if ( in_array($option,$_default_array) OR $option == $default ) { continue; }
							?>
							<div class="left">
								<input <?php echo($_disabled.' '.$_onchange_text); ?>
									name="<?php html_echo($this->table.'__'.$this->key); ?>[]" 
									id="db_<?php html_echo($this->key.$option.$rnd); ?>" 
									type="checkbox" 
									value="<?php html_echo($option); ?>"
									/>
								<label class="unlimitedWidth" for="db_<?php html_echo($this->key.$option.$rnd); ?>">
									<?php 
									if ( in_array($option,$_default_array) OR $option == $default ) { ?><b><?php };
									html_echo($option); 
									if ( in_array($option,$_default_array) OR $option == $default ) { ?></b><?php }; 
									?>&nbsp;&nbsp;
								</label>
							</div>
							<?php }; ?>
							<div id="db_<?php echo($key.$rnd); ?>_list"></div>
							<div class="left">
								<input <?php echo($_disabled.' '.$_onchange_text.' '.$_maxlength); ?>  type="text" id="db_<?php echo($key.$rnd); ?>_text" name="<?php echo($this->table.'__'.$this->key); ?>[]" class="db_formbox db_<?php echo($key); ?>" value="" onkeyup='_autoComplete(this,<?php echo(preg_replace("/\'/","&apos;",json_encode($options))); ?>,<?php echo(json_encode($conditions)); ?>)' autofocus disabled hidden>
								<div class="suggestions"></div>
								<label class="toggler" for="minus<?php echo($rnd); ?>" data-title="Zwischen Auswahl und Eingabe wechseln">&nbsp;<i class="fas fa-plus"></i></label>
								<input <?php echo($_disabled); ?>  id="minus<?php echo($rnd); ?>" class="minus" type="button" value="+" onclick="_toggleOption('<?php echo($key.$rnd); ?>')" data-title="Erlaubt die Eingabe eines neuen Wertes" hidden>
							</div>
						</div>
						<div class="clear"></div>
						<?php break;
					case 'EXTENSIBLE LIST':
						?>
						<label for="db_<?php echo($key.$rnd); ?>_list" class="onlyone"><?php echo($keyreadable); ?></label>
						<input <?php echo($_disabled.' '.$_onchange_text.' '.$_maxlength); ?>  type="text" id="db_<?php echo($key.$rnd); ?>_text" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_formbox db_<?php echo($key); ?>" value="" onkeyup='_autoComplete(this,<?php echo(preg_replace("/\'/","&apos;",json_encode($options))); ?>,<?php echo(json_encode($conditions)); ?>)' autofocus disabled hidden>
						<div class="suggestions"></div>
						<select <?php echo($_disabled); ?>  id="db_<?php echo($key.$rnd); ?>_list" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_formbox db_<?php echo($key); ?>" onclick="updateSelection(this);" onchange="_onResetFilter(this.value);<?php echo($_onchange_function); ?>">
						<!--	<option value="none"></option> -->
							<?php foreach ( $options as $value ) { 
								$_sel = '';
								if ( _cleanup($default) == _cleanup($value) ) { $_sel = 'selected'; };
								?>				
								<option value="<?php echo($value); ?>" <?php echo($_sel); ?> ><?php echo($value); ?></option>
							<?php } ?>
						</select>
						<label class="toggler" for="minus<?php echo($rnd); ?>" data-title="Zwischen Auswahl und Eingabe wechseln">&nbsp;<i class="fas fa-arrows-alt-h"></i></label>
						<input <?php echo($_disabled); ?>  id="minus<?php echo($rnd); ?>" class="minus" type="button" value="+" onclick="_toggleOption('<?php echo($key.$rnd); ?>')" data-title="Erlaubt die Eingabe eines neuen Wertes" hidden>
						<div class="clear"></div>
						<?php break;
					case 'MULTIPLE EXTENSIBLE LIST':
						$_default_array = json_decode($_default,true);
						if ( ! is_array($_default_array) ) { $_default_array = array($default); };
						$_hidden = "";
						?>
						<label for="db_<?php echo($key.$rnd); ?>_plus" class="onlyone"><?php echo($keyreadable); ?></label>
						<label id="db_<?php echo($key.$rnd); ?>_plus" onclick="addSearchfield(this,<?php echo($rnd); ?>);" data-title="Zusätzlicher Eintrag"><i class="fas fa-plus"></i></label>
						<div class="clear"></div>
						<?php foreach ( $_default_array as $default ) {
						?>
							<div class="searchfield">
								<label for="db_<?php echo($key.$rnd); ?>_list" style="opacity: 0"><?php echo($keyreadable); ?></label>
								<input <?php echo($_disabled.' '.$_onchange_text.' '.$_maxlength); ?>  type="text" id="db_<?php echo($key.$rnd); ?>_text" name="<?php echo($this->table.'__'.$this->key); ?>[]" class="db_formbox db_<?php echo($key); ?>" value="" onkeyup='_autoComplete(this,<?php echo(preg_replace("/\'/","&apos;",json_encode($options))); ?>,<?php echo(json_encode($conditions)); ?>)' autofocus disabled hidden>
								<div class="suggestions"></div>
								<select <?php echo($_disabled); ?>  id="db_<?php echo($key.$rnd); ?>_list" name="<?php echo($this->table.'__'.$this->key); ?>[]" class="db_formbox db_<?php echo($key); ?>" onclick="updateSelection(this);" onchange="_onResetFilter(this.value);<?php echo($_onchange_function); ?>">
								<!--	<option value="none"></option> -->
									<?php foreach ( $options as $value ) { 
										$_sel = '';
										if ( _cleanup($default) == _cleanup($value) ) { $_sel = 'selected'; };
										?>				
										<option value="<?php echo($value); ?>" <?php echo($_sel); ?> ><?php echo($value); ?></option>
									<?php } 
										$_hidden = "hidden";
									?>
								</select>
								<label class="toggler" for="minus<?php echo($rnd); ?>" data-title="Zwischen Auswahl und Eingabe wechseln">&nbsp;<i class="fas fa-arrows-alt-h"></i></label>
								<input <?php echo($_disabled); ?>  id="minus<?php echo($rnd); ?>" class="minus" type="button" value="+" onclick="_toggleOption('<?php echo($key.$rnd); ?>')" data-title="Erlaubt die Eingabe eines neuen Wertes" hidden>
								<div class="clear"></div>
							</div>
						<?php 
							$rnd = rand(0,32768);
							}
						break;
					case 'SUGGEST BEFORE LIST':
						?>
						<label for="db_<?php echo($key.$rnd); ?>_list" class="onlyone"><?php echo($keyreadable); ?></label>
						<input <?php echo($_disabled.' '.$_onchange_text.' '.$_maxlength); ?> type="text" id="db_<?php echo($key.$rnd); ?>_text" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_formbox db_<?php echo($key); ?>" value="<?php echo($default); ?>" onkeyup='_autoComplete(this,<?php echo(preg_replace("/\'/","&apos;",json_encode($options))); ?>,<?php echo(json_encode($conditions)); ?>)' autofocus>
						<div class="suggestions"></div>
						<select <?php echo($_disabled); ?> id="db_<?php echo($key.$rnd); ?>_list" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_formbox db_<?php echo($key); ?>" onclick="updateSelection(this);" onchange="_onResetFilter(this.value);<?php echo($_onchange_function); ?>" disabled hidden>
						<!--	<option value="none"></option> -->
							<?php foreach ( $options as $value ) { 
								$_sel = '';
								if ( _cleanup($default) == _cleanup($value) ) { $_sel = 'selected'; };
								?>				
								<option value="<?php echo($value); ?>" <?php echo($_sel); ?> ><?php echo($value); ?></option>
							<?php } ?>
						</select>
						<label class="toggler" for="minus<?php echo($rnd); ?>" data-title="Zwischen Auswahl und Eingabe wechseln">&nbsp;<i class="fas fa-arrows-alt-h"></i></label>
						<input <?php echo($_disabled); ?> id="minus<?php echo($rnd); ?>"class="minus" type="button" value="+" onclick="_toggleOption('<?php echo($key.$rnd); ?>')" data-title="Erlaubt die Eingabe eines neuen Wertes" hidden>
						<div class="clear"></div>
						<?php break;
					case 'DATE':
						?>
						<label class="date onlyone" for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
						<input <?php echo($_disabled.' '.$_onchange_text); ?> name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" id="db_<?php echo($key.$rnd); ?>" type="date" value="<?php echo($default); ?>" class="db_<?php echo($key); ?>" />
						<div class="clear"></div>
						<?php break;
					case 'DATETIME':
						$default_array = explode(' ',$default,2);
						?>
						<label class="date onlyone" for="db_<?php echo($key.$rnd.'date'); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
						<input <?php echo($_disabled); ?> id="db_<?php echo($key.$rnd.'date'); ?>" type="date" value="<?php echo($default_array[0]); ?>" class="db_<?php echo($key); ?>" onchange="_updateDateTime(this.id);<?php echo($_onchange_function); ?>"/>
						<input <?php echo($_disabled); ?> id="db_<?php echo($key.$rnd.'time'); ?>" type="time" value="<?php echo($default_array[1]); ?>" class="db_<?php echo($key); ?>" onchange="_updateDateTime(this.id);<?php echo($_onchange_function); ?>"/>
						<input <?php echo($_disabled); ?> hidden name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" id="db_<?php echo($key.$rnd); ?>" type="text" value="<?php echo($default); ?>" class="db_<?php echo($key); ?>" />
						<div class="clear"></div>
						<?php break;
					case 'INTEGER':
						?>
						<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
						<input <?php echo($_disabled.' '.$_onchange_text); ?> name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" id="db_<?php echo($key.$rnd); ?>" type="number" step="1" placeholder="0" value="<?php echo($default); ?>" class="db_<?php echo($key); ?>" />
						<div class="clear"></div>
						<?php break;
					case 'DECIMAL':
						?>
						<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
						<input <?php echo($_disabled.' '.$_onchange_text); ?> name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" id="db_<?php echo($key.$rnd); ?>" type="number" step="0.01" placeholder="0.00"  value="<?php echo($default); ?>" class="db_<?php echo($key); ?>" />
						<div class="clear"></div>
						<?php break;
					case 'TABLE':	
						?>
						<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
						<input <?php echo($_disabled.' '.$_onchange_text); ?> name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" id="db_<?php echo($key.$rnd); ?>" class="db_<?php echo($key); ?>" type="button" value="Bearbeiten" onclick="editTable(this.closest('form'),'tbl_<?php echo($key); ?>');"/>
						<div class="clear"></div>
						<?php break;
					case 'FREELONGER':
					case 'FREE':
						?>
						<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
						<textarea <?php echo($_disabled.' '.$_onchange_text); ?> id="db_<?php echo($key.$rnd); ?>" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_formbox db_<?php echo($key.' '.$_result['edittype']); ?> "  value=""><?php echo($default); ?></textarea>
						<div class="clear"></div>
						<?php break;
					case 'SECRET':
						?>
						<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
						<input type="password" <?php echo($_disabled.' '.$_onchange_text); ?> id="db_<?php echo($key.$rnd); ?>" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_formbox db_<?php echo($key); ?>"  value="">
						<div class="clear"></div>
						<?php break;
					case 'EMAIL':
						?>
						<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
						<input <?php echo($_disabled.' '.$_onchange_text); ?> type="email" id="db_<?php echo($key.$rnd); ?>" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_formbox db_<?php echo($key); ?>"  value="<?php echo($default); ?>">
						<div class="clear"></div>
						<?php break;
					case 'CALENDAR':
						?>
						<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
						<select <?php echo($_disabled); ?> id="db_<?php echo($key.$rnd); ?>" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_formbox calendar db_<?php echo($key); ?>" onclick="updateSelection(this);" onchange="_onResetFilter(this.value); _setIdCal(this); <?php echo($_onchange_function); ?>">
							<?php foreach ( $options as $option ) { 
								$value = $option['id_os_calendars'];
								$_sel = '';
								if ( _cleanup($default) == _cleanup($value) ) { $_sel = 'selected'; };
								?>				
								<option value="<?php echo($value); ?>" <?php echo($_sel); ?> ><?php echo($option['calendarreadable']); ?></option>
							<?php } ?>
						</select>
						<div class="clear"></div>
						<?php break;
					case 'FILES':
						//work in progress; does not work
						$default_array = json_decode($default,true);
						?>		
						<div>	
							<label class="files" for="db_<?php echo($key.$rnd.'files'); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
							<div></div> 
							<div></div> 
						<?php // divs above just for having at least two divs for removeContainingDiv below
							for ( $i = 0; $i < sizeof($default_array['filepath']); $i++ )
							{ ?>
								<div onclick="document.getElementById('trash').value = '<?php echo($default_array['filepath'][$i]); ?>'; callFunction('_','openFile','_popup_'); document.getElementById('trash').value = '';">
									<label><?php echo($default['filepath'][$i]); ?>)</label>
									<label onclick="removeContainingDiv(this);" data-title="Feld löschen"><i class="fas fa-minus"></i></label>
									<input <?php echo($_disabled.' '.$_onchange_text); ?> hidden name="<?php echo($this->table.'__'.$this->key); ?>['filedescription'][]" id="db_<?php echo($key.$rnd); ?>_filedescription_<?php echo($i); ?>" type="text" value="<?php echo($default['filedescription'][$i]); ?>" class="db_<?php echo($key); ?>" <?php echo($_maxlength); ?>/>
									<input <?php echo($_disabled.' '.$_onchange_text); ?> hidden name="<?php echo($this->table.'__'.$this->key); ?>['filepath'][]" id="db_<?php echo($key.$rnd); ?>_filepath_<?php echo($i); ?>" type="text" value="<?php echo($default['filepath'][$i]); ?>" class="db_<?php echo($key); ?>" />
								</div>
							<?php }
						?>
						</div>
						<input <?php echo($_disabled.' '.$_onchange_text); ?> hidden name="<?php echo($this->table.'__'.$this->key); ?>['filedescription'][]" id="db_<?php echo($key.$rnd); ?>_filedescription" type="text" class="db_<?php echo($key); ?>" placeholder="Beschreibung" <?php echo($_maxlength); ?>/>
						<input <?php echo($_disabled.' '.$_onchange_text); ?> hidden name="<?php echo($this->table.'__'.$this->key); ?>['FILES']" id="db_<?php echo($key.$rnd); ?>_filepath" type="file" multiple class="db_<?php echo($key); ?>" />
						<div class="clear"></div>
						<?php break;
					case 'FILESPATH':
						// 'filedescription': index 4001
						// 'filepath': index 4002;
						// determine fileroot
						require('../../core/data/filedata.php');
						$_fileroot = getConfig($this->connection)['fileroot'];
		//				if ( isset($_fileroot) AND $_fileroot != '' ) { $fileroot .= $_fileroot; }				
						//
						$default_array = json_decode($default,true);
						?>		
						<div>	
							<label class="files" for="db_<?php echo($key.$rnd.'filespath'); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
							<div></div> 
							<div></div> 
							<div id="db_<?php echo($key.$rnd.'filespath'); ?>">
						<?php // divs above just for having at least two divs for removeContainingDiv below
							if ( isset($default_array[4002]) ) {
								for ( $i = 0; $i < sizeof($default_array[4002]); $i++ )
								{ 
									if ( $default_array[4001][$i] == '' AND $default_array[4002][$i] == '' ) { continue; }
									if ( $default_array[4001][$i] == '' ) { $default_array[4001][$i] = basename($default_array[4002][$i]); }
									?>
									<div class="filesfield">
										<label class="fullWidth"><?php echo($default_array[4001][$i]); ?>&nbsp;&nbsp;
										<?php if ( is_dir($fileroot.'/'.$default_array[4002][$i]) ) { 
											$_files = scandir($fileroot.'/'.$default_array[4002][$i]);
											?>
											<label class="unlimitedWidth nofloat hover"><?php echo($default_array[4002][$i]); ?></label>
											<label class="unlimitedWidth nofloat" onclick="removeContainingDiv(this);" data-title="Feld löschen"><i class="fas fa-minus"></i></label>
											<ul class="hover nostyle">
											<?php
											foreach ( $_files as $_file )
											{
												if ( ! is_dir($fileroot.'/'.$default_array[4002][$i].$_file) ) { ?>						
												<li class="filelink" onclick="document.getElementById('trash').value = '<?php echo($fileroot.'/'.$default_array[4002][$i].$_file); ?>'; callFunction('_','openFile','_popup_'); document.getElementById('trash').value = '';">
													<div class="clear"></div>
													<span style="opacity: 0"><?php echo($default_array[4001][$i]); ?>&nbsp;&nbsp;</span>
													<?php echo($_file); ?>
												</li>	
												<?php }
											}
											?>
											</ul>
										<?php } else { 
											if ( is_file($fileroot.'/'.$default_array[4002][$i]) ) {									
											?>
												<label class="unlimitedWidth filelink nofloat hover" onclick="document.getElementById('trash').value = '<?php echo($fileroot.'/'.$default_array[4002][$i]); ?>'; callFunction('_','openFile','_popup_'); document.getElementById('trash').value = '';"><?php echo($default_array[4002][$i]); ?></label>
											<?php } else { ?>
												<label class="unlimitedWidth disabled nofloat hover"><?php echo($default_array[4002][$i]); ?></label>
											<?php } ?>
											<label  <?php echo($_disabled); ?> class="unlimitedWidth nofloat" onclick="removeContainingDiv(this);" data-title="Feld löschen"><i class="fas fa-minus"></i></label>
										<?php } ?>
										</label>
										<input hidden name="<?php echo($this->table.'__'.$this->key); ?>[4001][]" id="db_<?php echo($key.$rnd); ?>_filedescription_<?php echo($i); ?>" type="text" value="<?php echo($default_array[4001][$i]); ?>" class="db_<?php echo($key); ?>" />
										<input hidden name="<?php echo($this->table.'__'.$this->key); ?>[4002][]" id="db_<?php echo($key.$rnd); ?>_filepath_<?php echo($i); ?>" type="text" value="<?php echo($default_array[4002][$i]); ?>" class="db_<?php echo($key); ?>" />
										<div class="clear"></div>
										<label style="opacity: 0" class="files" for="db_<?php echo($key.$rnd.'filespath'); ?>"><?php echo($keyreadable); ?></label>
									</div>
								<?php }
							}
						?>
								<div id="db_<?php echo($key.$rnd); ?>_list" hidden></div>
								<div id="db_<?php echo($key.$rnd); ?>_text" disabled hidden>
									<input <?php echo($_disabled.' '.$_onchange_text); ?> name="<?php echo($this->table.'__'.$this->key); ?>[4001][]" id="db_<?php echo($key.$rnd); ?>_filedescription" type="text" class="left db_<?php echo($key); ?>" placeholder="Beschreibung"/>
									<input <?php echo($_disabled.' '.$_onchange_text); ?> hidden name="<?php echo($this->table.'__'.$this->key); ?>[4002][]" id="db_<?php echo($key.$rnd); ?>_filepath" type="text" class="db_<?php echo($key); ?>" />
									<div class="frame left filelink">
										<iframe name='Index' id="Index" src="/php/browseFileserver.php"
											onload="reloadCSS(this); if ( this.contentWindow.document.getElementById('label').innerText.slice(1) != '' ) { document.getElementById('db_<?php echo($key.$rnd); ?>_filepath').value = '<?php echo($_fileroot.'/'); ?>'+this.contentWindow.document.getElementById('label').innerText.slice(1); } else { document.getElementById('db_<?php echo($key.$rnd); ?>_filepath').value = ''; }"
											frameborder="0" border="0" cellspacing="0"
											style="border-style: none;width: 100%; height: 5rem; padding-left: 0.5rem;">
										</iframe>
									</div>
								</div>
								<label class="toggler unlimitedWidth" for="minus<?php echo($rnd); ?>" data-title="Zwischen Auswahl und Eingabe wechseln">&nbsp;<i class="fas fa-plus"></i></label>
								<input <?php echo($_disabled); ?>  id="minus<?php echo($rnd); ?>" class="minus" type="button" value="+" onclick="_toggleOption('<?php echo($key.$rnd); ?>')" data-title="Erlaubt die Eingabe eines neuen Wertes" hidden>
							</div>
						</div>
						<div class="clear"></div>
						<?php break;			
					case 'NOTE':
						$default = json_decode($default);
						if ( ! is_array($default) ) { $default = array('theme',''); }
						$_cbcolors = array("blue","green","yellow","red","theme");
						?>
						<div class="note note_edit" data-title="Notiz erstellen oder bearbeiten">
							<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
							<input type="checkbox" id="note_delete_cb<?php echo($rnd); ?>" class="note_cb" onclick="note_delete(this)" hidden <?php echo($_disabled); ?>>
							<input type="radio" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>[]" value="" <?php if ( $default[0] == '' ) { echo(" checked "); } ?> id="note_empty_cb<?php echo($rnd); ?>" class="note_cb note_empty" hidden <?php echo($_disabled); ?>>
							<?php foreach ( $_cbcolors as $_cbcolor ) {
							?>
							<input type="radio" onclick="note_show(this)" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>[]" value="<?php echo($_cbcolor); ?>" <?php if ( $default[0] == $_cbcolor ) { echo(" checked "); } ?> id="note_<?php echo($_cbcolor); ?>_cb<?php echo($rnd); ?>" class="note_cb note_<?php echo($_cbcolor); ?>" hidden <?php echo($_disabled); ?>>
							<?php } ?>
							<div class="note_wrapper">
								<label for="note_delete_cb<?php echo($rnd); ?>" class="unlimitedWidth note_delete"><i class="fas fa-minus-square"></i></label>
							<?php foreach ( $_cbcolors as $_cbcolor ) {
							?>
								<label for="note_<?php echo($_cbcolor); ?>_cb<?php echo($rnd); ?>" class="unlimitedWidth note_<?php echo($_cbcolor); ?>"><i class="far fa-square"></i></label>
							<?php } ?>
								<textarea  <?php echo($_disabled.' '.$_onchange_text.' '.$_maxlength); ?> onchange="note_synctext(this)" spellcheck="true" type="text" id="db_<?php echo($key.$rnd); ?>" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>[]" class="textarea db_formbox db_<?php echo($key.' note_'.$default[0]); ?>"  value="" rows="3" wrap="hard"><?php echo($default[1]); ?></textarea>
							</div>
						</div>
						<?php break;
					case 'JSON': //readonly data model
						$default = preg_replace('/\n/',' ',$default);
						//$default = preg_replace('/\\/','',$default);
						$default = json_decode($default,true);
						if ( $default == null ) { break; }
						function _nest(array $obj) {
							?>
							<ul class="json">
							<?php
							foreach ( $obj as $_key => $_value ) {
								?>
								<li><b><?php html_echo($_key); ?>:</b>
								<?php
								if ( gettype($_value) == 'array' ) { _nest($_value); } else { html_echo($_value); }
								?>
								</li>
							<?php
							}
							?>
							</ul>
							<?php
						}
						?>
						<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
						<div <?php echo($_disabled.' '.$_onchange_text); ?> id="db_<?php echo($key.$rnd); ?>" class="db_formbox db_<?php echo($key.' '.$_result['edittype']); ?> "><?php _nest($default); ?></div>
						<div class="clear"></div>
						<?php
						break;
					default:
						?>
						<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
						<input <?php echo($_disabled.' '.$_onchange_text.' '.$_maxlength); ?> spellcheck="true" type="text" id="db_<?php echo($key.$rnd); ?>" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_formbox db_<?php echo($key); ?>"  value="<?php echo($default); ?>">
						<div class="clear"></div>
						<?php break;
				}
			}	
			if ( ! $_single AND $_result['edittype'] != 'NONE') {
	//was:		if ( ! $_single ) {
				?>
				</div> <!-- end of class enablable -->
			<?php
				}
			if ( $_result['multiple'] ) {
					?>
					</div>
			<?php }
		}
		if ( $_result['multiple'] ) { ?>
			<label for="db_<?php echo($key.$firstrnd); ?>_plus" style="opacity: 0"><?php echo($keyreadable); ?></label>
			<label class="unlimitedWidth" id="db_<?php echo($key.$firstrnd); ?>_plus" onclick="addSearchfield(this,<?php echo($firstrnd); ?>);" data-title="Zusätzlicher Eintrag"><i class="fas fa-plus"></i></label>
			<div class="clear"></div>
		<?php }
		if ( $_result['edittype'] != 'NONE') {
		?>
		</div>
		<?php };
		return $_result['edittype'];		
	}

	public function choose(array $checked) {
		//do not bother if visible or not:
		unset($checked[-1]);
		$_stmt_array = array();
		$_stmt_array['stmt'] = 'SELECT typelist,edittype,referencetag,keyreadable,role_'.$_SESSION['os_role'].' AS role, restrictrole_'.$_SESSION['os_role'].' AS restrictrole, role_'.$_SESSION['os_parent'].' AS parentrole, restrictrole_'.$_SESSION['os_parent'].' AS restrictparentrole FROM '.$this->table.'_permissions WHERE keymachine = ?';
		$_stmt_array['str_types'] = 's';
		$_stmt_array['arr_values'] = array($this->key);
		$_result = execute_stmt($_stmt_array,$this->connection,true)['result'][0];
		//if it is an attribution, then $_result is not set:
		if ( ! isset($_result) ) {
			$_stmt_array = array();
			$_stmt_array['stmt'] = 'SELECT tablereadable AS keyreadable,allowed_roles,iconname FROM os_tables WHERE tablemachine = ?';
			$_stmt_array['str_types'] = 's';
			$_stmt_array['arr_values'] = array($this->key);
			$_result = execute_stmt($_stmt_array,$this->connection,true)['result'][0];
			//return if user is not explicitly allowed (no parent heritage...)
			if ( ! in_array($_SESSION['os_role'],json_decode($_result['allowed_roles'])) ) { return; }
			$_result['edittype'] = 'INTEGER';
			$_result['role'] = '0';
			$_result['restrictrole'] = '';
			$_result['parentrole'] = '0';
			$_result['restrictparentrole'] = '';
		}
		$options_array = $this->_getOptions();
		$options = $options_array['options'];

		$rnd = rand(0,32768);
		$key = $this->key;
		$table = $this->table;
		
		// test permissions
		/// if restrictions are set, present them as closed list
		if ( $_result['restrictrole'] != '' OR $_result['restrictparentrole'] != '' ) { $_result['edittype'] = 'LIST'; }
		/// if no select: do not show
		if ( ($_result['role']+$_result['parentrole']) % 2 > 0 ) { $_result['edittype'] = 'NONE'; }
		//
		//separate MULTIPLE keyword, e.g. EXTENSIBLE LIST; MULTIPLE
		$_tmp_array = explode('; ',$_result['edittype']);
		$_result['edittype'] = $_tmp_array[0];
		if ( isset($_tmp_array[1]) AND $_tmp_array[1] == 'MULTIPLE' ) { $_result['multiple'] = true; } else { $_result['multiple'] = false; };
		unset($_tmp_array);
		//
		//seperate compound keys
		$_tmp_array = explode(' + ',$_result['edittype']);
		$_result['edittype_array'] = $_tmp_array;
		$_result['referencetag_array'] = explode(' + ',$_result['referencetag']);
		if ( isset($_tmp_array[1]) ) { $_result['compound'] = true; } else { $_result['compound'] = false; };
		unset($_tmp_array);
		$tmpkey = $key;
		$tmpchecked = $checked;
		//
		if ( $_result['edittype'] != 'NONE' ) {
		?>
		<div class="form">
			<input name="<?php html_echo($this->table.'__'.$this->key); ?>[]" id="<?php html_echo($this->table.'__'.$this->key); ?>all" type="checkbox" value="_all" checked hidden />
			<?php
			/* "NOT": */
			//use index 3001 for the choice between positive or NOT
			?>
			<div>
				<input 
					hidden
					name="<?php html_echo($this->table.'__'.$this->key); ?>[3001]"
					id="<?php html_echo($this->table.'__'.$this->key); ?>not"
					type="checkbox"
					value="_not"
					<?php if ( isset($checked[3001]) ) { ?> checked <?php } ?>
				/>
				<label class="not" for="<?php html_echo($this->table.'__'.$this->key); ?>not"><i class="fas fa-arrows-alt-h"></i></label>
			</div>
			<?php
			/* "ASC|DESC": */
			//use index 3501 for the choice between ascending or descending ordering
			?>
			<div class="desc">
				<input 
					hidden
					name="<?php html_echo($this->table.'__'.$this->key); ?>[3501]"
					id="<?php html_echo($this->table.'__'.$this->key); ?>desc"
					type="checkbox"
					value="&#91;absteigend&#93;"
					<?php if ( isset($checked[3501]) ) { ?> checked <?php } ?>
				/>
				<label class="desc" for="<?php html_echo($this->table.'__'.$this->key); ?>desc"><i class="fas fa-arrows-alt-h"></i></label>
			</div>
		<?php }	
		if ( $_result['multiple'] OR $_result['edittype'] == "CHECKBOX" OR $_result['edittype'] == "EXTENSIBLE CHECKBOX" ) {
			//use index 2001 for the choice of OR or AND
			if ( ! isset($checked[2001]) ) { $checked[2001] = "-500"; }
			?>
			<div>
				<label class="orand" for="<?php html_echo($this->key.$option.$rnd); ?>_orand">oder</label>
				<input type="range" id="<?php html_echo($this->key.$option.$rnd); ?>_orand" name="<?php html_echo($this->table.'__'.$this->key); ?>[2001]" min="-500" max="-499" value="<?php echo($checked[2001]); ?>" step="1">
				<label class="orand" for="<?php html_echo($this->key.$option.$rnd); ?>_orand">und</label>
			</div><?php
		}
		if ( $_result['compound'] ) {
			$_result['keyreadable_array'] = explode(' + ',explode(': ',$_result['keyreadable'])[1]);
			?>
			<label onclick="addSearchfield(this);" data-title="Neues Suchfeld"><i class="fas fa-plus"></i></label>
			<?php
			//determine length of checked
			$_len = $this->_len($tmpchecked);
		} else {
			$_len = 1;
		};
		for ( $item = 0; $item < $_len; $item++ ) {
			if ( $_result['compound'] ) {
				?>
				<div class="searchfield compound">
				<?php
			}
			unset($indexedit); unset($_edittype);
			foreach ( $_result['edittype_array'] as $indexedit => $_edittype ) {
				$_searchfieldcompound = "";
				//use indices 6001+ for compounds of keys
				if ( $_result['compound'] ) {
					//restore original key for getting options
					$this->key = $tmpkey;
					$options_array = $this->_getOptions($indexedit);
					$options = $options_array['options'];
					$this->key = $tmpkey.'['.(6001+$indexedit).']';
					$key = $this->key;
					$_result['edittype'] = $_edittype;
					$_result['referencetag'] = $_result['referencetag_array'][$indexedit];
					$checked = $this->_extract($tmpchecked,$indexedit,$item);
					if ( ! is_array($checked) OR sizeof($checked) == 0 ) { $checked = array('_all'); }; 
					$_searchfieldcompound = "hidden";
					?>
						<div class="choose_headline"><?php echo(_cleanup($_result['keyreadable_array'][$indexedit])); ?></div>
					<?php
				}
				switch($_result['edittype']) {
					/*
					 * FREE free text
					 * EDITOR	tinymce4 editor for free text (plugin)
					 * SUGGEST free text with suggestions from existing entries
					 * LIST closed list of options given by 'referencetag' (entry in reference table of allowed values dependent on value in other inputs:)
					 * EXTENSIBLE LIST like LIST, but with button for adding a free text values
					 * DATE
					 * INTEGER
					 * DECIMAL
					 * TABLE (json array  of "table":"keynames" as referencetag) entry has an own table with given subset of "child_"keynames identified with keynames of "table", set up as FOREIGN KEY
					*/
					case 'CALENDAR':
					case 'SECRET':
					case 'FUNCTION':
					case 'NONE': break;
					case 'TEXT':
					case 'EMAIL':
					case 'PHONE':
					case 'FREE':
					case 'FREELONGER':
					case 'EDITOR':
					case 'FILESPATH':
						?>
							<label <?php echo($_searchfieldcompound); ?> onclick="addSearchfield(this);" data-title="Neues Suchfeld"><i class="fas fa-plus"></i></label>
							<?php 
							$_actuallychecked = array();
							foreach ( $checked as $searchterm ) {
								if ( sizeof($checked) > 1 AND ( $searchterm == "[absteigend]" OR $searchterm == "_not" OR $searchterm == "_all" OR $searchterm == "-500" OR $searchterm == "-499" ) ) { continue; }
								array_push($_actuallychecked,$searchterm);
							}
							if ( sizeof($_actuallychecked) == 0 ) { $_actuallychecked = array(""); }
							foreach ( $_actuallychecked as $searchterm ) {
								?>
								<div class="searchfield<?php echo($_searchfieldcompound); ?>">
									<input 
										class="searchfield"
										name="<?php html_echo($this->table.'__'.$this->key); ?>[]" 
										type="text" 
										value="<?php if ( $searchterm != "_all" ) { html_echo($searchterm); } ?>"
									/>
									<label <?php echo($_searchfieldcompound); ?> onclick="removeContainingDiv(this);" data-title="Feld löschen"><i class="fas fa-minus"></i></label>
									<br />
								</div>
							<?php }
							break;
					case 'NOTE':
						?>
							<label <?php echo($_searchfieldcompound); ?> onclick="addSearchfield(this,'<?php echo($rnd); ?>');" data-title="Neues Suchfeld"><i class="fas fa-plus"></i></label>
							<?php 
							$_cbcolors = array("blue","green","yellow","red","theme");
							if ( ! isset($checked[7001]) OR sizeof($checked[7001]) == 0 ) {
								$_index = "0";
								?>
								<div class="note note_choose searchfield<?php echo($_searchfieldcompound); ?>">
									<input type="text" hidden name="<?php echo($this->table.'__'.$this->key); ?>[7001][]" class="note_colors_input" value="[]">
									<input type="checkbox" onchange="updateNoteColorsInput(this)" value="_all" id="note_all_choose<?php echo($rnd.'_'.$_index); ?>" class="note_cb note_all" hidden checked>
								<?php foreach ( $_cbcolors as $_cbcolor ) {
								?>
									<input type="checkbox" onchange="updateNoteColorsInput(this)" value="<?php echo($_cbcolor); ?>" id="note_<?php echo($_cbcolor); ?>_choose<?php echo($rnd.'_'.$_index); ?>" class="note_cb note_<?php echo($_cbcolor); ?>" hidden>
								<?php } ?>
									<div class="note_wrapper">
										<label for="note_all_choose<?php echo($rnd.'_'.$_index); ?>" class="unlimitedWidth note_all">alle</label>
									<?php foreach ( $_cbcolors as $_cbcolor ) {
									?>
										<label for="note_<?php echo($_cbcolor); ?>_choose<?php echo($rnd.'_'.$_index); ?>" class="unlimitedWidth note_<?php echo($_cbcolor); ?>"><i class="far fa-square"></i></label>
									<?php } ?>
										<br />
										<input 
											class="searchfield textarea"
											name="<?php html_echo($this->table.'__'.$this->key); ?>[7002][]" 
											type="text" 
											value=""
										/>
									</div>
										<label <?php echo($_searchfieldcompound); ?> onclick="removeContainingDiv(this);" data-title="Feld löschen"><i class="fas fa-minus"></i></label>
										<br />
								</div>
							<?php
							} else {
								for ( $_index = 0; $_index < sizeof($checked[7001]); $_index++ ) {
									?>
								<div class="note note_choose searchfield<?php echo($_searchfieldcompound); ?>">
									<input type="text" hidden name="<?php echo($this->table.'__'.$this->key); ?>[7001][]" class="note_colors_input" value="<?php html_echo($checked[7001][$_index]); ?>">
									<input type="checkbox" onchange="updateNoteColorsInput(this)" value="_all" id="note_all_choose<?php echo($rnd.'_'.$_index); ?>" class="note_cb note_all" hidden <?php if ( in_array('_all',json_decode($checked[7001][$_index],true)) ) { echo(" checked "); } ?>>
								<?php foreach ( $_cbcolors as $_cbcolor ) {
								?>
									<input type="checkbox" onchange="updateNoteColorsInput(this)" value="<?php echo($_cbcolor); ?>"  <?php if ( in_array($_cbcolor,json_decode($checked[7001][$_index],true)) ) { echo(" checked "); } ?> id="note_<?php echo($_cbcolor); ?>_choose<?php echo($rnd.'_'.$_index); ?>" class="note_cb note_<?php echo($_cbcolor); ?>" hidden>
								<?php } ?>
									<div class="note_wrapper">
										<label for="note_all_choose<?php echo($rnd.'_'.$_index); ?>" class="unlimitedWidth note_all">alle</label>
									<?php foreach ( $_cbcolors as $_cbcolor ) {
									?>
										<label for="note_<?php echo($_cbcolor); ?>_choose<?php echo($rnd.'_'.$_index); ?>" class="unlimitedWidth note_<?php echo($_cbcolor); ?>"><i class="far fa-square"></i></label>
									<?php } ?>
										<br />
										<input 
											class="searchfield textarea"
											name="<?php html_echo($this->table.'__'.$this->key); ?>[7002][]" 
											type="text" 
											value="<?php if ( $checked[7002][$_index] != "_all" ) { html_echo($checked[7002][$_index]); } ?>"
										/>
									</div>
										<label <?php echo($_searchfieldcompound); ?> onclick="removeContainingDiv(this);" data-title="Feld löschen"><i class="fas fa-minus"></i></label>
										<br />
								</div>
								<?php }
								?>
								<img src="" onerror="updateNoteColorsInput(this.closest('.form').querySelector('.note .note_cb')); return false" />
								<?php
							}
							break;
					case 'DATETIME':
					case 'DATE':
						//use index 1001,1002,1003,1004,1005 for date and datetime values
						//1003: name
						//1001: begin
						//1002: end
						//1004; begin rolling
						//1005: end rolling
						//1006: current date (for rolling)
						$_rolling_options = array(
							array(
								"display" => "nein",
								"value" => "none",
								"extras" => "selected"
							),
							array(
								"display" => "Tag",
								"value" => "day",
								"extras" => ""
							),
							array(
								"display" => "Monat",
								"value" => "month",
								"extras" => ""
							),
							array(
								"display" => "Quartal",
								"value" => "quarter",
								"extras" => ""
							),
							array(
								"display" => "Halbjahr",
								"value" => "semiyear",
								"extras" => ""
							),
							array(
								"display" => "Jahr",
								"value" => "year",
								"extras" => ""
							),
						)
						?>
							<label <?php echo($_searchfieldcompound); ?> onclick="addSearchfield(this,<?php echo($rnd); ?>);" data-title="Neues Suchfeld"><i class="fas fa-plus"></i></label>

							<?php 
		// before 20200525:						if ( ! is_array($checked) OR sizeof($checked) <= 1) {
		//before 20200527:						if ( ! is_array($checked) OR sizeof($checked[1001]) <= 1) {
								if ( ! is_array($checked) OR sizeof($checked[1001]) == 0) {
								?>
								<div class="searchfield<?php echo($_searchfieldcompound); ?>">
									<label>Kürzel</label>
									<input 
										name="<?php html_echo($this->table.'__'.$this->key); ?>[1003][]" 
										type="text" 
										value="Periode 1"
										required
									/>
									<input 
										name="<?php html_echo($this->table.'__'.$this->key); ?>[1006][]" 
										type="date" 
										value="<?php html_echo(date('Y-m-d')); ?>"
										hidden
									/>
									<br />
									<label>von</label>
									<input 
										name="<?php html_echo($this->table.'__'.$this->key); ?>[1001][]" 
										type="date" 
										value=""
									/>
									<span>
										<input 
											id="<?php html_echo($this->table.'__'.$this->key.$rnd); ?>__rollstart"
											type="checkbox" 
											class="toggle" 
											onchange="_togglePinnedRolling(this)"
											hidden
										>
										<label for="<?php html_echo($this->table.'__'.$this->key.$rnd); ?>__rollstart">
											<span data-title="rollend" class="open"><i class="fcc fcc-calendar-rolling"></i></span>
											<span data-title="fixiert" class="closed"><i class="fas fa-map-pin"></i></span>
										</label>
										<select 
											name="<?php html_echo($this->table.'__'.$this->key); ?>[1004][]"
											class="db_formbox form inline" 
											onchange="_togglePinnedRolling(this)"
										>
										<?php foreach( $_rolling_options as $_rolling_option ) {
										?>
											<option value="<?php echo($_rolling_option["value"]); ?>" <?php echo($_rolling_option["extras"]); ?>><?php echo($_rolling_option["display"]); ?></option>
										<?php } ?>
										</select>
									</span>
									<br />
									<label>bis</label>
									<input 
										name="<?php html_echo($this->table.'__'.$this->key); ?>[1002][]" 
										type="date" 
										value=""
										/>
									<span>
										<input 
											id="<?php html_echo($this->table.'__'.$this->key.$rnd); ?>__rollend"
											type="checkbox" 
											class="toggle" 
											onchange="_togglePinnedRolling(this)"
											hidden
										>
										<label for="<?php html_echo($this->table.'__'.$this->key.$rnd); ?>__rollend">
											<span data-title="rollend" class="open"><i class="fcc fcc-calendar-rolling"></i></span>
											<span data-title="fixiert" class="closed"><i class="fas fa-map-pin"></i></span>
										</label>
										<select 
											name="<?php html_echo($this->table.'__'.$this->key); ?>[1005][]"
											class="db_formbox form inline" 
											onchange="_togglePinnedRolling(this)"
										>
										<?php foreach( $_rolling_options as $_rolling_option ) {
										?>
											<option value="<?php echo($_rolling_option["value"]); ?>" <?php echo($_rolling_option["extras"]); ?>><?php echo($_rolling_option["display"]); ?></option>
										<?php } ?>
										</select>
									</span>
									<label <?php echo($_searchfieldcompound); ?> onclick="removeContainingDiv(this);" data-title="Feld löschen"><i class="fas fa-minus"></i></label>
									<br />
									<br />
								</div>					
							<?php } else {
								for ( $i = 0; $i < sizeof($checked[1001]); $i++ ) {
									//roll dates if set
									foreach ( [1004,1005] as $_roll ) {
										if ( isset($checked[$_roll][$i]) AND $checked[$_roll][$i] != "none" AND isset($checked[1006][$i]) ) {
											switch($checked[$_roll][$i]) {
												case "day":
													$_difftime = floor(time()/86400)*86400-DateTime::createFromFormat('Y-m-d',$checked[1006][$i])->format('U');
													$_diffinterval = DateInterval::createFromDateString($_difftime.' seconds');
													$checked[$_roll-3][$i] = date_add(DateTime::createFromFormat('Y-m-d',$checked[$_roll-3][$i]),$_diffinterval)->format('Y-m-d');
													break;
												case "month":
													$_diffyear = date('Y') - DateTime::createFromFormat('Y-m-d',$checked[1006][$i])->format('Y');
													$_diffmonth = date('m') - DateTime::createFromFormat('Y-m-d',$checked[1006][$i])->format('m');
													//$_diffday: if end day is last of month, let the new end day also be the last day of the month
													//so: push it one day ahead
													$_diffinterval = DateInterval::createFromDateString('1 day');
													if ( $_roll == 1005 ) { $checked[1002][$i] = date_add(DateTime::createFromFormat('Y-m-d',$checked[1002][$i]),$_diffinterval)->format('Y-m-d'); }
													//do years
													$_diffinterval = DateInterval::createFromDateString($_diffyear.' years');
													$checked[$_roll-3][$i] = date_add(DateTime::createFromFormat('Y-m-d',$checked[$_roll-3][$i]),$_diffinterval)->format('Y-m-d');
													//do months
													$_diffinterval = DateInterval::createFromDateString($_diffmonth.' months');
													$checked[$_roll-3][$i] = date_add(DateTime::createFromFormat('Y-m-d',$checked[$_roll-3][$i]),$_diffinterval)->format('Y-m-d');
													//push one day back
													$_diffinterval = DateInterval::createFromDateString('-1 day');
													if ( $_roll == 1005 ) { $checked[1002][$i] = date_add(DateTime::createFromFormat('Y-m-d',$checked[1002][$i]),$_diffinterval)->format('Y-m-d'); }
													break;
												case "quarter":
													$_diffyear = date('Y') - DateTime::createFromFormat('Y-m-d',$checked[1006][$i])->format('Y');
													$_diffmonth = 3*( floor((date('n')-1)/3) - floor((DateTime::createFromFormat('Y-m-d',$checked[1006][$i])->format('n')-1)/3) );
													//$_diffday: if end day is last of month, let the new end day also be the last day of the month
													//so: push it one day ahead
													$_diffinterval = DateInterval::createFromDateString('1 day');
													if ( $_roll == 1005 ) { $checked[1002][$i] = date_add(DateTime::createFromFormat('Y-m-d',$checked[1002][$i]),$_diffinterval)->format('Y-m-d'); }
													//do years
													$_diffinterval = DateInterval::createFromDateString($_diffyear.' years');
													$checked[$_roll-3][$i] = date_add(DateTime::createFromFormat('Y-m-d',$checked[$_roll-3][$i]),$_diffinterval)->format('Y-m-d');
													//do quarters
													$_diffinterval = DateInterval::createFromDateString($_diffmonth.' months');
													$checked[$_roll-3][$i] = date_add(DateTime::createFromFormat('Y-m-d',$checked[$_roll-3][$i]),$_diffinterval)->format('Y-m-d');
													//push one day back
													$_diffinterval = DateInterval::createFromDateString('-1 day');
													if ( $_roll == 1005 ) { $checked[1002][$i] = date_add(DateTime::createFromFormat('Y-m-d',$checked[1002][$i]),$_diffinterval)->format('Y-m-d'); }
													break;
												case "semiyear":
													$_diffyear = date('Y') - DateTime::createFromFormat('Y-m-d',$checked[1006][$i])->format('Y');
													$_diffmonth = 6*( floor((date('n')-1)/6) - floor((DateTime::createFromFormat('Y-m-d',$checked[1006][$i])->format('n')-1)/6) );
													//$_diffday: if end day is last of month, let the new end day also be the last day of the month
													//so: push it one day ahead
													$_diffinterval = DateInterval::createFromDateString('1 day');
													if ( $_roll == 1005 ) { $checked[1002][$i] = date_add(DateTime::createFromFormat('Y-m-d',$checked[1002][$i]),$_diffinterval)->format('Y-m-d'); }
													//do years
													$_diffinterval = DateInterval::createFromDateString($_diffyear.' years');
													$checked[$_roll-3][$i] = date_add(DateTime::createFromFormat('Y-m-d',$checked[$_roll-3][$i]),$_diffinterval)->format('Y-m-d');
													//do quarters
													$_diffinterval = DateInterval::createFromDateString($_diffmonth.' months');
													$checked[$_roll-3][$i] = date_add(DateTime::createFromFormat('Y-m-d',$checked[$_roll-3][$i]),$_diffinterval)->format('Y-m-d');
													//push one day back
													$_diffinterval = DateInterval::createFromDateString('-1 day');
													if ( $_roll == 1005 ) { $checked[1002][$i] = date_add(DateTime::createFromFormat('Y-m-d',$checked[1002][$i]),$_diffinterval)->format('Y-m-d'); }
													break;
												case "year":
													//$_diffday: if end day last of feb, it should also be last of feb in the new year
													//so: push it one day ahead
													$_diffinterval = DateInterval::createFromDateString('1 day');
													if ( $_roll == 1005 ) { $checked[1002][$i] = date_add(DateTime::createFromFormat('Y-m-d',$checked[1002][$i]),$_diffinterval)->format('Y-m-d'); }
													//do years
													$_diffyear = date('Y') - DateTime::createFromFormat('Y-m-d',$checked[1006][$i])->format('Y');
													$_diffinterval = DateInterval::createFromDateString($_diffyear.' years');
													$checked[$_roll-3][$i] = date_add(DateTime::createFromFormat('Y-m-d',$checked[$_roll-3][$i]),$_diffinterval)->format('Y-m-d');
													//push one day back
													$_diffinterval = DateInterval::createFromDateString('-1 day');
													if ( $_roll == 1005 ) { $checked[1002][$i] = date_add(DateTime::createFromFormat('Y-m-d',$checked[1002][$i]),$_diffinterval)->format('Y-m-d'); }
													break;
											}
										}
									}
								?>
									<div class="searchfield<?php echo($_searchfieldcompound); ?>">
										<label>Kürzel</label>
										<input 
											name="<?php html_echo($this->table.'__'.$this->key); ?>[1003][]" 
											type="text" 
											value="<?php html_echo($checked[1003][$i]); ?>"
											required
										/>
										<input 
											name="<?php html_echo($this->table.'__'.$this->key); ?>[1006][]" 
											type="date" 
											value="<?php html_echo(date('Y-m-d')); ?>"
											hidden
										/>
										<br />
										<label>von</label>
										<input 
											name="<?php html_echo($this->table.'__'.$this->key); ?>[1001][]" 
											type="date" 
											value="<?php html_echo($checked[1001][$i]); ?>"
										/>
										<span>
											<input 
												id="<?php html_echo($this->table.'__'.$this->key.$rnd.'_'.$i); ?>__rollstart"
												type="checkbox" 
												class="toggle" 
												onchange="_togglePinnedRolling(this)"
												hidden
												<?php if ( $checked[1004][$i] != "none" ) { ?>checked<?php } ?>>
											<label for="<?php html_echo($this->table.'__'.$this->key.$rnd.'_'.$i); ?>__rollstart">
												<span data-title="rollend" class="open"><i class="fcc fcc-calendar-rolling"></i></span>
												<span data-title="fixiert" class="closed"><i class="fas fa-map-pin"></i></span>
											</label>
											<select 
												name="<?php html_echo($this->table.'__'.$this->key); ?>[1004][]"
												class="db_formbox form inline" 
												onchange="_togglePinnedRolling(this)"
											>
											<?php foreach( $_rolling_options as $_rolling_option ) {
											?>
												<option value="<?php echo($_rolling_option["value"]); ?>" <?php if ( $checked[1004][$i] == $_rolling_option["value"] ) { echo("selected"); } ?>><?php echo($_rolling_option["display"]); ?></option>
											<?php } ?>
											</select>
										</span>
										<br />
										<label>bis</label>
										<input 
											name="<?php html_echo($this->table.'__'.$this->key); ?>[1002][]" 
											type="date" 
											value="<?php html_echo($checked[1002][$i]); ?>"
										/>
										<span>
											<input 
												id="<?php html_echo($this->table.'__'.$this->key.$rnd.'_'.$i); ?>__rollend"
												type="checkbox" 
												class="toggle" 
												onchange="_togglePinnedRolling(this)"
												hidden
												<?php if ( $checked[1005][$i] != "none" ) { ?>checked<?php } ?>>
											<label for="<?php html_echo($this->table.'__'.$this->key.$rnd.'_'.$i); ?>__rollend">
												<span data-title="rollend" class="open"><i class="fcc fcc-calendar-rolling"></i></span>
												<span data-title="fixiert" class="closed"><i class="fas fa-map-pin"></i></span>
											</label>
											<select 
												name="<?php html_echo($this->table.'__'.$this->key); ?>[1005][]"
												class="db_formbox form inline" 
												onchange="_togglePinnedRolling(this)"
											>
											<?php foreach( $_rolling_options as $_rolling_option ) {
											?>
												<option value="<?php echo($_rolling_option["value"]); ?>" <?php if ( $checked[1005][$i] == $_rolling_option["value"] ) { echo("selected"); } ?>><?php echo($_rolling_option["display"]); ?></option>
											<?php } ?>
											</select>
										</span>
										<label <?php echo($_searchfieldcompound); ?> onclick="removeContainingDiv(this);" data-title="Feld löschen"><i class="fas fa-minus"></i></label>
										<br />
										<br />
									</div>					
								<?php } 
								} 
							break;
					case 'INTEGER':
						//use index 5001,5002,5003 for decimal range values
						?>
							<label <?php echo($_searchfieldcompound); ?> onclick="addSearchfield(this);" data-title="Neues Suchfeld"><i class="fas fa-plus"></i></label>

							<?php 
								if ( ! is_array($checked) OR sizeof($checked) <= 1) {
								?>
								<div class="searchfield<?php echo($_searchfieldcompound); ?>">
									<label>Kürzel</label>
									<input 
										name="<?php html_echo($this->table.'__'.$this->key); ?>[5003][]" 
										type="text" 
										value="Bereich 1"
										required
									/>
									<br />
									<label>von</label>
									<input 
										name="<?php html_echo($this->table.'__'.$this->key); ?>[5001][]" 
										type="number"
										step="1"
										placeholder="0" 
										value="<?php html_echo($checked[5001][$i]); ?>"
									/>
									<label>bis</label>
									<input 
										name="<?php html_echo($this->table.'__'.$this->key); ?>[5002][]" 
										type="number"
										step="1"
										placeholder="0" 
										value="<?php html_echo($checked[5002][$i]); ?>"
										/>
									<label <?php echo($_searchfieldcompound); ?> onclick="removeContainingDiv(this);" data-title="Feld löschen"><i class="fas fa-minus"></i></label>
									<br />
									<br />
								</div>					
							<?php } else {
								for ( $i = 0; $i < sizeof($checked[5001]); $i++ ) {
								?>
									<div class="searchfield<?php echo($_searchfieldcompound); ?>">
										<label>Kürzel</label>
										<input 
											name="<?php html_echo($this->table.'__'.$this->key); ?>[5003][]" 
											type="text" 
											value="<?php html_echo($checked[5003][$i]); ?>"
											required
										/>
										<br />
										<label>von</label>
										<input 
											name="<?php html_echo($this->table.'__'.$this->key); ?>[5001][]" 
											type="number"
											step="1"
											placeholder="0" 
											value="<?php html_echo($checked[5001][$i]); ?>"
										/>
										<label>bis</label>
										<input 
											name="<?php html_echo($this->table.'__'.$this->key); ?>[5002][]" 
											type="number"
											step="1"
											placeholder="0" 
											value="<?php html_echo($checked[5002][$i]); ?>"
										/>
										<label <?php echo($_searchfieldcompound); ?> onclick="removeContainingDiv(this);" data-title="Feld löschen"><i class="fas fa-minus"></i></label>
										<br />
										<br />
									</div>					
								<?php } 
								} 
							break;
					case 'DECIMAL':
						//use index 5001,5002,5003 for decimal range values
						?>
							<label <?php echo($_searchfieldcompound); ?> onclick="addSearchfield(this);" data-title="Neues Suchfeld"><i class="fas fa-plus"></i></label>

							<?php 
								if ( ! is_array($checked) OR sizeof($checked) <= 1) {
								?>
								<div class="searchfield<?php echo($_searchfieldcompound); ?>">
									<label>Kürzel</label>
									<input 
										name="<?php html_echo($this->table.'__'.$this->key); ?>[5003][]" 
										type="text" 
										value="Bereich 1"
										required
									/>
									<br />
									<label>von</label>
									<input 
										name="<?php html_echo($this->table.'__'.$this->key); ?>[5001][]" 
										type="number"
										step="0.01"
										placeholder="0.00" 
										value="<?php html_echo($checked[5001][$i]); ?>"
									/>
									<label>bis</label>
									<input 
										name="<?php html_echo($this->table.'__'.$this->key); ?>[5002][]" 
										type="number"
										step="0.01"
										placeholder="0.00" 
										value="<?php html_echo($checked[5002][$i]); ?>"
										/>
									<label <?php echo($_searchfieldcompound); ?> onclick="removeContainingDiv(this);" data-title="Feld löschen"><i class="fas fa-minus"></i></label>
									<br />
									<br />
								</div>					
							<?php } else {
								for ( $i = 0; $i < sizeof($checked[5001]); $i++ ) {
								?>
									<div class="searchfield<?php echo($_searchfieldcompound); ?>">
										<label>Kürzel</label>
										<input 
											name="<?php html_echo($this->table.'__'.$this->key); ?>[5003][]" 
											type="text" 
											value="<?php html_echo($checked[5003][$i]); ?>"
											required
										/>
										<br />
										<label>von</label>
										<input 
											name="<?php html_echo($this->table.'__'.$this->key); ?>[5001][]" 
											type="number"
											step="0.01"
											placeholder="0.00" 
											value="<?php html_echo($checked[5001][$i]); ?>"
										/>
										<label>bis</label>
										<input 
											name="<?php html_echo($this->table.'__'.$this->key); ?>[5002][]" 
											type="number"
											step="0.01"
											placeholder="0.00" 
											value="<?php html_echo($checked[5002][$i]); ?>"
										/>
										<label <?php echo($_searchfieldcompound); ?> onclick="removeContainingDiv(this);" data-title="Feld löschen"><i class="fas fa-minus"></i></label>
										<br />
										<br />
									</div>					
								<?php } 
								} 
							break;
					case 'CHECKBOX':
					case 'EXTENSIBLE CHECKBOX':
					case 'TABLE':				
					case 'SUGGEST':
					case 'LIST':
					case 'SUGGEST BEFORE LIST':
					case 'EXTENSIBLE LIST':
					default:
						if ( $_result['compound'] ) { 
							// add empty option for matching every $option
							?>
							<select name="<?php echo($this->table.'__'.$this->key.'[]'); ?>" class="db_formbox db_<?php echo($key); ?>" onclick="updateSelection(this);" onchange="_onResetFilter(this.value)">
								<option value=""></option>
								<?php foreach ( $options as $option ) { 
									$_sel = '';
									if ( in_array($option,$checked) ) { $_sel = 'selected'; };
									?>				
									<option value="<?php echo($option); ?>" <?php echo($_sel); ?> ><?php echo($option); ?></option>
								<?php } ?>
							</select>
				<?php } else {
						?>
							<?php foreach ( $options as $option ) { ?>
								<input 
									name="<?php html_echo($this->table.'__'.$this->key); ?>[]" 
									id="<?php html_echo($this->key.$option.$rnd); ?>" 
									type="checkbox" 
									value="<?php html_echo($option); ?>"
									<?php if ( in_array($option,$checked) ) { ?> checked <?php }; ?> 
								/>
								<label for="<?php html_echo($this->key.$option.$rnd); ?>"><?php html_echo($option); ?></label><br>
							<?php }; 
						} ?>
						<?php break;
				}
			}
			if ( $_result['compound'] ) { ?>
					<label onclick="removeContainingDiv(this);" data-title="Feld löschen"><i class="fas fa-minus"></i></label>
				</div> <!-- end of exterior searchfield -->
			<?php }
		}
		if ( $_result['edittype'] != 'NONE' ) {
		?>
		</div>
		<?php }
	}

	protected function _input_escape(string $inputstring) { return $inputstring; } //do later

	//special functions for compound structures
	protected function _len(array $checked,int $indexedit=0) {
		$_checked_length = 1;
		if ( isset($checked[6001][0]) ) {
			$_checked_length = sizeof($checked[6001+$indexedit]);
		} else {
			foreach ( $checked[6001] as $index=>$value ) {
				$_checked_length = sizeof($value);
				break;
			}
		}
		return $_checked_length;
	}
	protected function _extract(array $checked, int $compound, int $item) {
		if ( isset($checked[6001+$compound][0]) ) {
			$_extracted = array($checked[6001+$compound][$item]);
		} else {
			$_extracted = array();
			foreach ( $checked[6001+$compound] as $index=>$value ) {
				if ( ! is_array($value) ) { $value = array($value); }
				$_extracted[$index] = array($value[$item]);
			}
		}
		return $_extracted;
	}

}



?>
