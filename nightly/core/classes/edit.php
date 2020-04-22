<?php 
// edit context: $connection, $table, $key
class OpenStatEdit {
	
	public $table = "";
	public $key = "";
	public $connection;// = new mysqli;
	
	public function __construct(string $table, string $key, mysqli $connection) {
		$this->table = $this->_input_escape($table);
		$this->key = $this->_input_escape($key);		
		$this->connection = $connection;
	}
	
	protected function _getOptions() {
		$table = $this->table;
		$key = $this->key;
		$options = array();
		
		$_stmt_array = array();
			$_stmt_array['stmt'] = 'SELECT typelist,edittype,referencetag,role_'.$_SESSION['os_role'].' AS role, restrictrole_'.$_SESSION['os_role'].' AS restrictrole, role_'.$_SESSION['os_parent'].' AS parentrole, restrictrole_'.$_SESSION['os_parent'].' AS restrictparentrole FROM '.$this->table.'_permissions WHERE keymachine = ?';
		$_stmt_array['str_types'] = 's';
		$_stmt_array['arr_values'] = array($this->key);
		$_result = execute_stmt($_stmt_array,$this->connection,true)['result'][0];
		unset($_stmt_array); $_stmt_array = array();
		$_stmt_array['stmt'] = 'SELECT keyreadable FROM '.$this->table.'_permissions WHERE keymachine = ?';
		$_stmt_array['str_types'] = "s";
		$_stmt_array['arr_values'] = array();
		$_stmt_array['arr_values'][] = $this->key;
		$_result_array = execute_stmt($_stmt_array,$this->connection); 
		if ($_result_array['dbMessageGood']) { $keyreadable = $_result_array['result']['keyreadable'][0]; };			

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
				$_stmt_array['stmt'] = 'SELECT `'.$this->key.'` FROM `view__' . $this->table . '__' . $_SESSION['os_role'].'`';
				$options = execute_stmt($_stmt_array,$this->connection)['result'][$this->key];
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
					$conditions = execute_stmt($_stmt_array,$connection,true)['result']; //first rows, then keynames
					if ( ! isset($options) ) { $options = array(); };
					foreach ( $_options as $values ) {
						if ( is_array(json_decode($values)) ) {
							$options = array_merge($options,json_decode($values));
						}
					}
				}
				break;
			default:
				unset($_stmt_array); 
				$_stmt_array['stmt'] = 'SELECT `'.$this->key.'` FROM `view__' . $this->table . '__' . $_SESSION['os_role'].'`';
				$options = execute_stmt($_stmt_array,$this->connection)['result'][$this->key];
				break;
		}
		if ( is_array($options) AND sizeof($options) > 0 ) {
			$options = array_unique($options);
			asort($options);
		}
		return array("options"=>$options, "conditions"=>$conditions);
	}
	
	public function edit(string $_default, bool $_single = true) {
		$_stmt_array = array();
		$_stmt_array['stmt'] = 'SELECT edittype,role_'.$_SESSION['os_role'].' AS role, restrictrole_'.$_SESSION['os_role'].' AS restrictrole, role_'.$_SESSION['os_parent'].' AS parentrole, restrictrole_'.$_SESSION['os_parent'].' AS restrictparentrole FROM '.$this->table.'_permissions WHERE keymachine = ?';
		$_stmt_array['str_types'] = 's';
		$_stmt_array['arr_values'] = array($this->key);
		$_result = execute_stmt($_stmt_array,$this->connection,true)['result'][0];
		unset($_stmt_array); $_stmt_array = array();
		$_stmt_array['stmt'] = 'SELECT keyreadable FROM `'.$this->table.'_permissions` WHERE keymachine = ?';
		$_stmt_array['str_types'] = "s";
		$_stmt_array['arr_values'] = array();
		$_stmt_array['arr_values'][] = $this->key;
		$_result_array = execute_stmt($_stmt_array,$this->connection); 
		if ($_result_array['dbMessageGood']) { 
			$keyreadable = $_result_array['result']['keyreadable'][0];
		};
		$firstrnd = -1;
		$key = $this->key;
		$table = $this->table;
		$options_array = $this->_getOptions();
		$options = $options_array['options'];
		$conditions = $options_array['conditions'];
		
		if ( is_array($options) AND count($options) == 1 ) { $default = $options[0]; } else { $default = $_default; }
		
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
		//separate MULTIPLE keyword, e.g. EXTENSIBLE LIST; MULTIPLE
		$_tmp_array = explode('; ',$_result['edittype']);
		$_result['edittype'] = $_tmp_array[0];
		if ( isset($_tmp_array[1]) AND $_tmp_array[1] == 'MULTIPLE' ) { $_result['multiple'] = true; } else { $_result['multiple'] = false; };
		unset($_tmp_array);
		//
		if ( $_result['edittype'] != 'NONE') {
		?>
		<div class="edit_wrapper<?php echo($_addclasses); ?>">
		<?php };
		$_default_array = '';
		$_arrayed = '';
		if ( $_result['multiple'] ) {
			$_default_array = json_decode($default,true);
			$_arrayed = '[]'; //inputs as arrays
		}
		if ( ! is_array($_default_array) ) { $_default_array = array($default); };
		foreach ( $_default_array as $default ) {
			//the name of input fields is not arrayed! Change this!
			$rnd = rand(0,32768);
			if ( $firstrnd == -1 ) { $firstrnd = $rnd; }
			if ( $_result['multiple'] ) {
			?>
				<div class="searchfield">
					<label  <?php echo($_disabled); ?> class="unlimitedWidth right" onclick="removeContainingDiv(this);"><i class="fas fa-minus"></i></label>
			<?php
			}
		if ( ! $_single AND $_result['edittype'] != 'NONE') {
			?>
			<label class="unlimitedWidth" onclick="_toggleEnabled(<?php echo($rnd); ?>);"><i class="fas fa-pen-square"></i></label>
			<div id="enablable<?php echo($rnd); ?>" class="disabled">
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
			case 'NONE': break;
			case 'EDITOR':
				//this is a third party plugin: tinymce4
				?>
				<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
				<textarea <?php echo($_disabled); ?> id="db_<?php echo($key.$rnd); ?>" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="editor db_formbox db_<?php echo($key); ?>"  value=""><?php echo($default); ?></textarea>
				<div class="clear"></div>
				<?php break;
			case 'SUGGEST':
				?>
				<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
				<input <?php echo($_disabled); ?> type="text" id="db_<?php echo($key.$rnd); ?>_text" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_formbox db_<?php echo($key); ?>" value="<?php echo($default); ?>" onkeyup='_autoComplete(this,<?php echo(json_encode($options)); ?>,<?php echo(json_encode($conditions)); ?>)' autofocus>
				<div class="clear"></div>
				<?php break;
			case 'LIST':
				?>
				<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
				<select <?php echo($_disabled); ?> id="db_<?php echo($key.$rnd); ?>" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_formbox db_<?php echo($key); ?>" onclick="updateSelection(this);" onchange="_onResetFilter(this.value)">
				<!--	<option value="none"></option> -->
					<?php foreach ( $options as $value ) { 
						$_sel = '';
						if ( _cleanup($default) == _cleanup($value) ) { $_sel = 'selected'; };
						?>				
						<option value="<?php echo($value); ?>" <?php echo($_sel); ?> ><?php echo($value); ?></option>
					<?php } ?>
				</select>
				<div id="db_<?php echo($key.$rnd); ?>_conditions" hidden><?php html_echo(json_encode($conditions)); ?></div>
				<div class="clear"></div>
				<?php break;
			case 'CHECKBOX':
				?>
				<label for="db_<?php echo($key.$rnd); ?>_list" class="onlyone"><?php echo($keyreadable); ?></label>
				<div class="left checkbox">
					<?php foreach ( $options as $option ) { ?>
					<div class="left">
						<input <?php echo($_disabled); ?>
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
				<div id="db_<?php echo($key.$rnd); ?>_conditions" hidden><?php html_echo(json_encode($conditions)); ?></div>
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
						<input <?php echo($_disabled); ?>
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
						<input <?php echo($_disabled); ?>
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
						<input <?php echo($_disabled); ?>  type="text" id="db_<?php echo($key.$rnd); ?>_text" name="<?php echo($this->table.'__'.$this->key); ?>[]" class="db_formbox db_<?php echo($key); ?>" value="" onkeyup='_autoComplete(this,<?php echo(json_encode($options)); ?>,<?php echo(json_encode($conditions)); ?>)' autofocus disabled hidden>
						<label class="toggler" for="minus<?php echo($rnd); ?>">&nbsp;<i class="fas fa-plus"></i></label>
						<input <?php echo($_disabled); ?>  id="minus<?php echo($rnd); ?>" class="minus" type="button" value="+" onclick="_toggleOption('<?php echo($key.$rnd); ?>')" title="Erlaubt die Eingabe eines neuen Wertes" hidden>
					</div>
				</div>
				<div id="db_<?php echo($key.$rnd); ?>_conditions" hidden><?php html_echo(json_encode($conditions)); ?></div>
				<div class="clear"></div>
				<?php break;
			case 'EXTENSIBLE LIST':
				?>
				<label for="db_<?php echo($key.$rnd); ?>_list" class="onlyone"><?php echo($keyreadable); ?></label>
				<input <?php echo($_disabled); ?>  type="text" id="db_<?php echo($key.$rnd); ?>_text" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_formbox db_<?php echo($key); ?>" value="" onkeyup='_autoComplete(this,<?php echo(json_encode($options)); ?>,<?php echo(json_encode($conditions)); ?>)' autofocus disabled hidden>
				<select <?php echo($_disabled); ?>  id="db_<?php echo($key.$rnd); ?>_list" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_formbox db_<?php echo($key); ?>" onclick="updateSelection(this);" onchange="_onResetFilter(this.value)">
				<!--	<option value="none"></option> -->
					<?php foreach ( $options as $value ) { 
						$_sel = '';
						if ( _cleanup($default) == _cleanup($value) ) { $_sel = 'selected'; };
						?>				
						<option value="<?php echo($value); ?>" <?php echo($_sel); ?> ><?php echo($value); ?></option>
					<?php } ?>
				</select>
				<label class="toggler" for="minus<?php echo($rnd); ?>">&nbsp;<i class="fas fa-arrows-alt-h"></i></label>
				<input <?php echo($_disabled); ?>  id="minus<?php echo($rnd); ?>" class="minus" type="button" value="+" onclick="_toggleOption('<?php echo($key.$rnd); ?>')" title="Erlaubt die Eingabe eines neuen Wertes" hidden>
				<div id="db_<?php echo($key.$rnd); ?>_list_conditions" hidden><?php html_echo(json_encode($conditions)); ?></div>
				<div class="clear"></div>
				<?php break;
			case 'MULTIPLE EXTENSIBLE LIST':
				$_default_array = json_decode($_default,true);
				if ( ! is_array($_default_array) ) { $_default_array = array($default); };
				$_hidden = "";
				?>
				<label for="db_<?php echo($key.$rnd); ?>_plus" class="onlyone"><?php echo($keyreadable); ?></label>
				<label id="db_<?php echo($key.$rnd); ?>_plus" onclick="addSearchfield(this,<?php echo($rnd); ?>);" title="Zusätzlicher Eintrag"><i class="fas fa-plus"></i></label>
				<div class="clear"></div>
				<?php foreach ( $_default_array as $default ) {
				?>
					<div class="searchfield">
						<label for="db_<?php echo($key.$rnd); ?>_list" style="opacity: 0"><?php echo($keyreadable); ?></label>
						<input <?php echo($_disabled); ?>  type="text" id="db_<?php echo($key.$rnd); ?>_text" name="<?php echo($this->table.'__'.$this->key); ?>[]" class="db_formbox db_<?php echo($key); ?>" value="" onkeyup='_autoComplete(this,<?php echo(json_encode($options)); ?>,<?php echo(json_encode($conditions)); ?>)' autofocus disabled hidden>
						<select <?php echo($_disabled); ?>  id="db_<?php echo($key.$rnd); ?>_list" name="<?php echo($this->table.'__'.$this->key); ?>[]" class="db_formbox db_<?php echo($key); ?>" onclick="updateSelection(this);" onchange="_onResetFilter(this.value)">
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
						<label class="toggler" for="minus<?php echo($rnd); ?>">&nbsp;<i class="fas fa-arrows-alt-h"></i></label>
						<input <?php echo($_disabled); ?>  id="minus<?php echo($rnd); ?>" class="minus" type="button" value="+" onclick="_toggleOption('<?php echo($key.$rnd); ?>')" title="Erlaubt die Eingabe eines neuen Wertes" hidden>
						<div id="db_<?php echo($key.$rnd); ?>_list_conditions" hidden><?php html_echo(json_encode($conditions)); ?></div>
						<div class="clear"></div>
					</div>
				<?php 
					$rnd = rand(0,32768);
					}
				break;
			case 'SUGGEST BEFORE LIST':
				?>
				<label for="db_<?php echo($key.$rnd); ?>_list" class="onlyone"><?php echo($keyreadable); ?></label>
				<input <?php echo($_disabled); ?> type="text" id="db_<?php echo($key.$rnd); ?>_text" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_formbox db_<?php echo($key); ?>" value="<?php echo($default); ?>" onkeyup='_autoComplete(this,<?php echo(json_encode($options)); ?>,<?php echo(json_encode($conditions)); ?>)' autofocus>
				<select <?php echo($_disabled); ?> id="db_<?php echo($key.$rnd); ?>_list" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_formbox db_<?php echo($key); ?>" onclick="updateSelection(this);" onchange="_onResetFilter(this.value)" disabled hidden>
				<!--	<option value="none"></option> -->
					<?php foreach ( $options as $value ) { 
						$_sel = '';
						if ( _cleanup($default) == _cleanup($value) ) { $_sel = 'selected'; };
						?>				
						<option value="<?php echo($value); ?>" <?php echo($_sel); ?> ><?php echo($value); ?></option>
					<?php } ?>
				</select>
				<label class="toggler" for="minus<?php echo($rnd); ?>">&nbsp;<i class="fas fa-arrows-alt-h"></i></label>
				<input <?php echo($_disabled); ?> id="minus<?php echo($rnd); ?>"class="minus" type="button" value="+" onclick="_toggleOption('<?php echo($key.$rnd); ?>')" title="Erlaubt die Eingabe eines neuen Wertes" hidden>
				<div id="db_<?php echo($key.$rnd); ?>_list_conditions" hidden><?php html_echo(json_encode($conditions)); ?></div>
				<div class="clear"></div>
				<?php break;
			case 'DATE':
				?>
				<label class="date" for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
				<input <?php echo($_disabled); ?> name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" id="db_<?php echo($key.$rnd); ?>" type="date" value="<?php echo($default); ?>" class="db_<?php echo($key); ?>" />
				<div class="clear"></div>
				<?php break;
			case 'DATETIME':
				$default_array = explode(' ',$default,2);
				?>
				<label class="date" for="db_<?php echo($key.$rnd.'date'); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
				<input <?php echo($_disabled); ?> id="db_<?php echo($key.$rnd.'date'); ?>" type="date" value="<?php echo($default_array[0]); ?>" class="db_<?php echo($key); ?>" onchange="_updateDateTime(this.id);"/>
				<input <?php echo($_disabled); ?> id="db_<?php echo($key.$rnd.'time'); ?>" type="time" value="<?php echo($default_array[1]); ?>" class="db_<?php echo($key); ?>" onchange="_updateDateTime(this.id);"/>
				<input <?php echo($_disabled); ?> hidden name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" id="db_<?php echo($key.$rnd); ?>" type="text" value="<?php echo($default); ?>" class="db_<?php echo($key); ?>" />
				<div class="clear"></div>
				<?php break;
			case 'INTEGER':
				?>
				<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
				<input <?php echo($_disabled); ?> name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" id="db_<?php echo($key.$rnd); ?>" type="number" step="1" placeholder="0" value="<?php echo($default); ?>" class="db_<?php echo($key); ?>" />
				<div class="clear"></div>
				<?php break;
			case 'DECIMAL':
				?>
				<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
				<input <?php echo($_disabled); ?> name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" id="db_<?php echo($key.$rnd); ?>" type="number" step="0.01" placeholder="0.00"  value="<?php echo($default); ?>" class="db_<?php echo($key); ?>" />
				<div class="clear"></div>
				<?php break;
			case 'TABLE':	
				?>
				<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
				<input <?php echo($_disabled); ?> name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" id="db_<?php echo($key.$rnd); ?>" class="db_<?php echo($key); ?>" type="button" value="Bearbeiten" onclick="editTable(this.closest('form'),'tbl_<?php echo($key); ?>');"/>
				<div class="clear"></div>
				<?php break;
			case 'FREELONGER':
			case 'FREE':
				?>
				<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
				<textarea <?php echo($_disabled); ?> id="db_<?php echo($key.$rnd); ?>" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_formbox db_<?php echo($key.' '.$_result['edittype']); ?> "  value=""><?php echo($default); ?></textarea>
				<div class="clear"></div>
				<?php break;
			case 'SECRET':
				?>
				<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
				<input type="password" <?php echo($_disabled); ?> id="db_<?php echo($key.$rnd); ?>" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_formbox db_<?php echo($key); ?>"  value="">
				<div class="clear"></div>
				<?php break;
			case 'EMAIL':
				?>
				<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
				<input <?php echo($_disabled); ?> type="email" id="db_<?php echo($key.$rnd); ?>" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_formbox db_<?php echo($key); ?>"  value="<?php echo($default); ?>">
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
							<label onclick="removeContainingDiv(this);"><i class="fas fa-minus"></i></label>
							<input <?php echo($_disabled); ?> hidden name="<?php echo($this->table.'__'.$this->key); ?>['filedescription'][]" id="db_<?php echo($key.$rnd); ?>_filedescription_<?php echo($i); ?>" type="text" value="<?php echo($default['filedescription'][$i]); ?>" class="db_<?php echo($key); ?>" />
							<input <?php echo($_disabled); ?> hidden name="<?php echo($this->table.'__'.$this->key); ?>['filepath'][]" id="db_<?php echo($key.$rnd); ?>_filepath_<?php echo($i); ?>" type="text" value="<?php echo($default['filepath'][$i]); ?>" class="db_<?php echo($key); ?>" />
						</div>
					<?php }
				?>
				</div>
				<input <?php echo($_disabled); ?> hidden name="<?php echo($this->table.'__'.$this->key); ?>['filedescription'][]" id="db_<?php echo($key.$rnd); ?>_filedescription" type="text" class="db_<?php echo($key); ?>" placeholder="Beschreibung"/>
				<input <?php echo($_disabled); ?> hidden name="<?php echo($this->table.'__'.$this->key); ?>['FILES']" id="db_<?php echo($key.$rnd); ?>_filepath" type="file" multiple class="db_<?php echo($key); ?>" />
				<div class="clear"></div>
				<?php break;
			case 'FILESPATH':
				// 'filedescription': index 4001
				// 'filepath': index 4002;
				require('../../core/data/filedata.php');
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
									<label class="unlimitedWidth nofloat" onclick="removeContainingDiv(this);"><i class="fas fa-minus"></i></label>
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
									<label  <?php echo($_disabled); ?> class="unlimitedWidth nofloat" onclick="removeContainingDiv(this);"><i class="fas fa-minus"></i></label>
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
							<input <?php echo($_disabled); ?> name="<?php echo($this->table.'__'.$this->key); ?>[4001][]" id="db_<?php echo($key.$rnd); ?>_filedescription" type="text" class="left db_<?php echo($key); ?>" placeholder="Beschreibung"/>
							<input <?php echo($_disabled); ?> hidden name="<?php echo($this->table.'__'.$this->key); ?>[4002][]" id="db_<?php echo($key.$rnd); ?>_filepath" type="text" class="db_<?php echo($key); ?>" />
							<div class="frame left filelink">
								<iframe name='Index' id="Index" src="/php/browseFileserver.php"
									onload="reloadCSS(this); document.getElementById('db_<?php echo($key.$rnd); ?>_filepath').value = this.contentWindow.document.getElementById('label').innerText.slice(1); "
									frameborder="0" border="0" cellspacing="0"
									style="border-style: none;width: 100%; height: 5rem; padding-left: 0.5rem;">
								</iframe>
							</div>
						</div>
						<label class="toggler unlimitedWidth" for="minus<?php echo($rnd); ?>">&nbsp;<i class="fas fa-plus"></i></label>
						<input <?php echo($_disabled); ?>  id="minus<?php echo($rnd); ?>" class="minus" type="button" value="+" onclick="_toggleOption('<?php echo($key.$rnd); ?>')" title="Erlaubt die Eingabe eines neuen Wertes" hidden>
					</div>
				</div>
				<div class="clear"></div>
				<?php break;			
			default:
				?>
				<label for="db_<?php echo($key.$rnd); ?>" class="onlyone"><?php echo($keyreadable); ?></label>
				<input <?php echo($_disabled); ?> spellcheck="true" type="text" id="db_<?php echo($key.$rnd); ?>" name="<?php echo($this->table.'__'.$this->key.$_arrayed); ?>" class="db_formbox db_<?php echo($key); ?>"  value="<?php echo($default); ?>">
				<div class="clear"></div>
				<?php break;
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
			<label class="unlimitedWidth" id="db_<?php echo($key.$firstrnd); ?>_plus" onclick="addSearchfield(this,<?php echo($firstrnd); ?>);" title="Zusätzlicher Eintrag"><i class="fas fa-plus"></i></label>
			<div class="clear"></div>
		<?php }
		if ( $_result['edittype'] != 'NONE') {
		?>
		</div>
		<?php };
		return $_result['edittype'];		
	}

	public function choose(array $checked) {
		$_stmt_array = array();
		$_stmt_array['stmt'] = 'SELECT typelist,edittype,referencetag,role_'.$_SESSION['os_role'].' AS role, restrictrole_'.$_SESSION['os_role'].' AS restrictrole, role_'.$_SESSION['os_parent'].' AS parentrole, restrictrole_'.$_SESSION['os_parent'].' AS restrictparentrole FROM '.$this->table.'_permissions WHERE keymachine = ?';
		$_stmt_array['str_types'] = 's';
		$_stmt_array['arr_values'] = array($this->key);
		$_result = execute_stmt($_stmt_array,$this->connection,true)['result'][0];
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
		if ( $_result['multiple'] ) {
			//use index 2001 for the choice of OR or AND
			if ( ! isset($checked[2001]) ) { $checked[2001] = "-500"; }
			?>
			<div>
				<label class="orand" for="<?php html_echo($this->key.$option.$rnd); ?>_orand">oder</label>
				<input type="range" id="<?php html_echo($this->key.$option.$rnd); ?>_orand" name="<?php html_echo($this->table.'__'.$this->key); ?>[2001]" min="-500" max="-499" value="<?php echo($checked[2001]); ?>" step="1">
				<label class="orand" for="<?php html_echo($this->key.$option.$rnd); ?>_orand">und</label>
			</div><?php
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
			case 'SECRET':
			case 'NONE': break;
			case 'TEXT':
			case 'EMAIL':
			case 'PHONE':
			case 'FREE':
			case 'EDITOR':
			case 'FILESPATH':
				?>
					<label onclick="addSearchfield(this);" title="Neues Suchfeld"><i class="fas fa-plus"></i></label>
					<?php foreach ( $checked as $searchterm ) {
						if ( sizeof($checked) > 1 AND ( $searchterm == "_not" OR $searchterm == "_all" OR $searchterm == "-500" OR $searchterm == "-499" ) ) { continue; }
						?>
						<div class="searchfield">
							<input 
								class="searchfield"
								name="<?php html_echo($this->table.'__'.$this->key); ?>[]" 
								type="text" 
								value="<?php if ( $searchterm != "_all" ) { html_echo($searchterm); } ?>"
							/>
							<label onclick="removeContainingDiv(this);"><i class="fas fa-minus"></i></label>
							<br />
						</div>
					<?php }
					break;
			case 'DATETIME':
			case 'DATE':
				//use index 1001,1002,1003 for date and datetime values
				?>
					<label onclick="addSearchfield(this);" title="Neues Suchfeld"><i class="fas fa-plus"></i></label>

					<?php 
						if ( ! is_array($checked) OR sizeof($checked) <= 1) {
						?>
						<div class="searchfield">
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
							<label onclick="removeContainingDiv(this);"><i class="fas fa-minus"></i></label>
							<br />
							<br />
						</div>					
					<?php } else {
						for ( $i = 0; $i < sizeof($checked[1001]); $i++ ) {
						?>
							<div class="searchfield">
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
								<label onclick="removeContainingDiv(this);"><i class="fas fa-minus"></i></label>
								<br />
								<br />
							</div>					
						<?php } 
						} 
					break;
			case 'INTEGER':
				?>
					<label onclick="addSearchfield(this);" title="Neues Suchfeld"><i class="fas fa-plus"></i></label>
					<?php foreach ( $checked as $searchterm ) {
						?>
						<div class="searchfield">
							<input 
								name="<?php html_echo($this->table.'__'.$this->key); ?>[]" 
								type="number"
								step="1"
								placeholder="0" 
								value="<?php if ( $searchterm != "_all" ) { html_echo($searchterm); } ?>"
							/>
							<label onclick="removeContainingDiv(this);"><i class="fas fa-minus"></i></label>
						</div>
						<br />
					<?php }
					break;
			case 'DECIMAL':
				?>
					<label onclick="addSearchfield(this);" title="Neues Suchfeld"><i class="fas fa-plus"></i></label>
					<?php foreach ( $checked as $searchterm ) {
						?>
						<div class="searchfield">
							<input 
								name="<?php html_echo($this->table.'__'.$this->key); ?>[]" 
								type="number"
								step="0.01"
								placeholder="0.00" 
								value="<?php if ( $searchterm != "_all" ) { html_echo($searchterm); } ?>"
							/>
							<label onclick="removeContainingDiv(this);"><i class="fas fa-minus"></i></label>
						</div>
						<br />
					<?php }
				    break;
			case 'CHECKBOX':
			case 'EXTENSIBLE CHECKBOX':
			case 'TABLE':				
			case 'SUGGEST':
			case 'LIST':
			case 'SUGGEST BEFORE LIST':
			case 'EXTENSIBLE LIST':
			default:
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
					<?php }; ?>
				<?php break;
		}	
		if ( $_result['edittype'] != 'NONE' ) {
		?>
		</div>
		<?php }
	}

	protected function _input_escape(string $inputstring) { return $inputstring; } //do later

}



?>
