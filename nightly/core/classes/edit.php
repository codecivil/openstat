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
				$_splice = array();
				foreach ( $options as $_index=>$option ) {
					if ( is_array(json_decode($option,true)) ) {
						$_splice[] = $option;
						$options = array_merge($options,json_decode($option,true));
					}
				}
				unset($option); unset($_index);
				$options = array_diff($options,$_splice);
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
		//separate MULTIPLE keyword, e.g. EXTENSIBLE LIST; MULTIPLE
		$_tmp_array = explode('; ',$_result['edittype']);
		$_result['edittype'] = $_tmp_array[0];
		//print_r($_tmp_array);
		if ( isset($_tmp_array[1]) AND $_tmp_array[1] == 'MULTIPLE' ) { $_result['multiple'] = true; } else { $_result['multiple'] = false; };
		unset($_tmp_array);
		//
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
				<div id="enablable<?php echo($rnd); ?>" class="disabled">
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
						<div class="note" data-title="Notiz erstellen oder bearbeiten">
							<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
							<input type="checkbox" id="note_delete_cb<?php echo($rnd); ?>" class="note_cb" onclick="note_delete(this)" hidden>
							<?php foreach ( $_cbcolors as $_cbcolor ) {
							?>
							<input type="radio" onclick="note_show(this)" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>[]" value="<?php echo($_cbcolor); ?>" <?php if ( $default[0] == $_cbcolor ) { echo(" checked "); } ?> id="note_<?php echo($_cbcolor); ?>_cb<?php echo($rnd); ?>" class="note_cb note_<?php echo($_cbcolor); ?>" hidden>
							<?php } ?>
							<div class="note_wrapper">
								<label for="note_delete_cb<?php echo($rnd); ?>" class="unlimitedWidth note_delete"><i class="fas fa-minus-square"></i></label>
							<?php foreach ( $_cbcolors as $_cbcolor ) {
							?>
								<label for="note_<?php echo($_cbcolor); ?>_cb<?php echo($rnd); ?>" class="unlimitedWidth note_<?php echo($_cbcolor); ?>"><i class="far fa-square"></i></label>
							<?php } ?>
								<textarea  <?php echo($_disabled.' '.$_onchange_text.' '.$_maxlength); ?> onchange="note_synctext(this)" spellcheck="true" type="text" id="db_<?php echo($key.$rnd); ?>" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>[]" class="db_formbox db_<?php echo($key.' note_'.$default[0]); ?>"  value="" rows="3" wrap="hard"><?php echo($default[1]); ?></textarea>
							</div>
						</div>
						<?php break;
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
			//return if user is not allowed
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
					case 'NONE': break;
					case 'TEXT':
					case 'EMAIL':
					case 'PHONE':
					case 'FREE':
					case 'EDITOR':
					case 'FILESPATH':
						?>
							<label <?php echo($_searchfieldcompound); ?> onclick="addSearchfield(this);" data-title="Neues Suchfeld"><i class="fas fa-plus"></i></label>
							<?php foreach ( $checked as $searchterm ) {
								if ( sizeof($checked) > 1 AND ( $searchterm == "_not" OR $searchterm == "_all" OR $searchterm == "-500" OR $searchterm == "-499" ) ) { continue; }
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
					case 'DATETIME':
					case 'DATE':
						//use index 1001,1002,1003 for date and datetime values
						?>
							<label <?php echo($_searchfieldcompound); ?> onclick="addSearchfield(this);" data-title="Neues Suchfeld"><i class="fas fa-plus"></i></label>

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
									<br />
									<label>von</label>
									<input 
										name="<?php html_echo($this->table.'__'.$this->key); ?>[1001][]" 
										type="date" 
										value=""
									/>
									<label>bis</label>
									<input 
										name="<?php html_echo($this->table.'__'.$this->key); ?>[1002][]" 
										type="date" 
										value=""
										/>
									<label <?php echo($_searchfieldcompound); ?> onclick="removeContainingDiv(this);" data-title="Feld löschen"><i class="fas fa-minus"></i></label>
									<br />
									<br />
								</div>					
							<?php } else {
								for ( $i = 0; $i < sizeof($checked[1001]); $i++ ) {
								?>
									<div class="searchfield<?php echo($_searchfieldcompound); ?>">
										<label>Kürzel</label>
										<input 
											name="<?php html_echo($this->table.'__'.$this->key); ?>[1003][]" 
											type="text" 
											value="<?php html_echo($checked[1003][$i]); ?>"
											required
										/>
										<br />
										<label>von</label>
										<input 
											name="<?php html_echo($this->table.'__'.$this->key); ?>[1001][]" 
											type="date" 
											value="<?php html_echo($checked[1001][$i]); ?>"
										/>
										<label>bis</label>
										<input 
											name="<?php html_echo($this->table.'__'.$this->key); ?>[1002][]" 
											type="date" 
											value="<?php html_echo($checked[1002][$i]); ?>"
										/>
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
