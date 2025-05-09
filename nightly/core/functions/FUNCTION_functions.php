<?php
/*
 * $PARAM is the form containing values of table entries
 * $param is the initial/changed status info of the function table entry and the value of 'this' (entry number of multiple fields)
 * a FIELD function has parameters ($param,$PARAM,$conn)
 * and may get profile info by certain extra functions like getProfile($searchstring,$profilefieldname)
*/ 
function FUNCTIONAction (array $PARAM, mysqli $conn) {
	// What is the easiest way to find fields of type FUNCTION?
    function getFUNCTIONs(array $PARAM, $FUNCTIONPARAM = array()) {
        foreach ( $PARAM as $rawkey => $value ) {
            $_this = intdiv((int)$rawkey,2); //strange formula: due to "none" being sent as first field entry for combined fields thus
                                             // doubling the number of entries in the function field
            if ( gettype($value) == "array" ) {
                $FUNCTIONPARAM = getFUNCTIONs($value,$FUNCTIONPARAM);
            }
            if ( ! json_decode((string)$value) ) { continue; };
            if ( isset(json_decode($value,true)['type']) AND json_decode($value,true)['type'] == "FUNCTION" ) {
                $valuewithcontext = json_encode(array_merge(json_decode($value,true),array("this" => $_this)));
                $FUNCTIONPARAM[] = $valuewithcontext;
            }
        }
        return $FUNCTIONPARAM;
    }
    $FUNCTIONPARAM = getFUNCTIONs($PARAM);
	//execute any function with its status parameter as argument
	foreach ( $FUNCTIONPARAM as $functionjson ) {
		$functions = json_decode($functionjson,true)['functions'];
		$param = json_decode($functionjson,true)['status'];
		$param['this'] = json_decode($functionjson,true)['this'];
        foreach ( $functions as $functionwithconfig ) {
			if ( $functionwithconfig == "none" ) { continue; }
			if ( $functionwithconfig != '' ) {
				//separate functionname and config part
				$function_array = explode('(',$functionwithconfig,2);
				$function = $function_array[0];
				if ( isset($function_array[1]) ) {
					$configname = str_replace(')','',$function_array[1]);
				}
				else {
					$configname = '';
				}
				//check flags if the function should be executed at all
				$_flags = getFunctionFlags($function,$conn);
				$_goon = false; $_onflag = false;
				if ( in_array("ONCHANGE",$_flags) ) {
					$_onflag = true;
					//execute only if a trigger field has changed
					if ( isset($param['changed']) AND json_encode($param['initial']) != json_encode($param['changed']) ) { $_goon = true; }
				}
				if ( in_array("ONINSERT",$_flags) ) {
					$_onflag = true;
					//execute only if a entry was inserted
					if ( $PARAM['dbAction'] == "insert" ) { $_goon = true; }
				}
				if ( in_array("ONDELETE",$_flags) ) {
					$_onflag = true;
					//execute only if a entry was deleted
					if ( $PARAM['dbAction'] == "delete" ) { $_goon = true; }
				}
				if ( in_array("ONEDIT",$_flags) ) {
					$_onflag = true;
					//execute only if a entry was inserted
					if ( $PARAM['dbAction'] == "edit" ) { $_goon = true; }
				}
				//test for ONTABLES array:
				$_ontables = true;
				if ( ! empty(array_filter($_flags, function($value) { return is_array($value); })) ) {
					foreach ( array_filter($_flags, function($value) { return is_array($value); }) as $_flagarray ) {
						foreach ( $_flagarray as $_flagname => $_flagvalue ) {
							if ( $_flagname == "ONTABLES" AND is_array($_flagvalue) ) {
								//$_flagvalue can either be a list of tablemachines or an array with confignames as keys and lists of 
								//tablemachines as values
								//case: no confignames
								if ( ! isset($_flagvalue[$configname]) ) {
									foreach ( $_flagvalue as $_flagtable ) {
										if ( ! isset($PARAM['id_'.$_flagtable]) OR $PARAM['id_'.$_flagtable] === '' ) {
											$_ontables = false;
										}
									}
								} else {
									foreach ( $_flagvalue[$configname] as $_flagtable ) {
										if ( ! isset($PARAM['id_'.$_flagtable]) OR $PARAM['id_'.$_flagtable] === '' ) {
											$_ontables = false;
										}
									}
								}
							}
						}
					}
				}
				
				$_goon = ( $_goon == $_onflag ) AND $_ontables; // go on if there is no ON*-flag or if any ON-flag-condition has evaluated to true
																// and all necessary tables are activated
				if ( ! $_goon ) { continue; } //pun intended
				//save origingal PARAM
				$ORIGPARAM = json_decode(json_encode($PARAM),true);
				$passtojs_array = array();
				//execute the function for every table id (mass editing)
				foreach ( json_decode($ORIGPARAM['id_'.$ORIGPARAM['table']],true) as $id_table ) {
					$PARAM['id_'.$ORIGPARAM['table']] = json_encode(array($id_table));
					//preprocess config
					$result = FUNCTIONpreprocess($function,$configname,$param,$PARAM,$conn);
					if ( isset($_SESSION['DEBUG']) AND $_SESSION['DEBUG'] ) { file_put_contents($GLOBALS["debugpath"].$GLOBALS["debugfilename"],__FUNCTION__ .json_encode($result).PHP_EOL,FILE_APPEND); }
					if ( ! $result['success']['value'] ) {
						$_return['status'] = "Fehler";
						$_return['log'] = array("Error" => $result['success']['error']); 
						$_return['js'] = 'Bitte dem Administrator melden: '.$result['success']['error'];
						$return = $_return;
						//return $_return;
					} else {
						$config = $result['return'];
						//execute function
						$return = $function($config,$param,$PARAM,$conn);
					}
					//log event
					FUNCTIONeventlog($function,$return,$PARAM,$conn);
					//prepare pass to js
					$passtojs_array[] = $return['js'];
				}
				$passtojs = "'".base64_encode(json_encode($passtojs_array,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))."'";
				?>
				<img src="" onerror="<?php html_echo($function.'(b64DecodeUnicode('.$passtojs.'))'); ?>">
				<?php
                //handle message array
                if ( isset($return['message']) AND is_array($return['message']) AND isset($return['message']['ok']) AND isset($return['message']['text']) ) {
                    $messageb64 = "'".base64_encode($return['message']['text'])."'";
                    ?>
                    <img src="" onerror="document.querySelector('#message<?php html_echo($PARAM['table'].json_decode($PARAM['id_'.$PARAM['table']])[0]); ?>').innerHTML += '<div class=\'dbMessage <?php html_echo($return['message']['ok']); ?>\'>'+b64DecodeUnicode(<?php html_echo($messageb64); ?>)+'</div>'">
                <?php }
			}
		}
	}
}

function FUNCTIONreplacePlaceholders(array $_config,array $trigger,array $PARAM,mysqli $conn) {
	$_tmpconfig = array();
	$success = array("value" => true, "error" => '');
	foreach ( $_config as $header => $value ) {
		if ( gettype($value) == "array" ) {
            $tmpreturn = FUNCTIONreplacePlaceholders($value,$trigger,$PARAM,$conn); 
            $value = $tmpreturn['return'];
            $success['value'] = $success['value'] AND $tmpreturn['success']['value'];
            $success['error'] .= $tmpreturn['success']['error']; 
        }
		else {
			$needProfiles = false; //initialize if we need to look up profiles
			$needEnv = false; //initialize if we need to look up profiles
			//replace triggers
			preg_match_all('/\$trigger\[([^\]]+\]\[[^\]]+)\]/',$value,$matches);
			foreach ( $matches[1] as $pattern ) {
				$pattern_array = explode('][',$pattern,2);
				if ( isset($trigger[$pattern_array[0]][$pattern_array[1]]) ) {
					$value = preg_replace('/\$trigger\['.$pattern_array[0].'\]\['.$pattern_array[1].'\]/',_cleanup($trigger[$pattern_array[0]][$pattern_array[1]]),$value);
				} else {
					$value = preg_replace('/\$trigger\['.$pattern_array[0].'\]\['.$pattern_array[1].'\]/','(ungesetzt)',$value);
				}
			}
			//replace fields
			unset($matches);
			preg_match_all('/\$([^ ,\$\"\'\.\;\:\!\?\)]*)/',$value,$matches);
			$need = array("select" => array(), "from" => array("view__".$PARAM['table']."__".$_SESSION['os_role']), "on" => array(), "where" => array());
			foreach ( $matches[1] as $pattern_specs ) {
                //handle multiple and combined fields, e.g. opsz_vermittlungslisten__vermitteltzu[2][last|this]
                $pattern_split = explode('[',$pattern_specs);
                $pattern = $pattern_split[0];
                unset($pattern_component);
                unset($pattern_entry);
                if ( isset($pattern_split[2]) ) { 
                    $pattern_component = (int)str_replace(']','',$pattern_split[1]);
                    $pattern_entry = str_replace(']','',$pattern_split[2]);
                } elseif ( isset($pattern_split[1]) ) {
                    $pattern_entry = str_replace(']','',$pattern_split[1]);
                }
				if ( strpos($pattern,'PROFILE') === 0 ) { $needProfiles = true; continue; }
				if ( strpos($pattern,'ENV') === 0 ) { $needEnv = true; continue; }
				[$pattern_table,$pattern_key] = explode('__',$pattern,2);
				if ( $pattern_table != $PARAM['table'] and isset($PARAM['id_'.$pattern_table]) ) {
					if ( ! in_array("view__".$pattern_table."__".$_SESSION['os_role']." ON view__".$PARAM['table']."__".$_SESSION['os_role'].'.id_'.$pattern_table.' = view__'. $pattern_table."__".$_SESSION['os_role'].'.id_'.$pattern_table,$need['from']) ) { $need['from'][] = "view__".$pattern_table."__".$_SESSION['os_role']." ON view__".$PARAM['table']."__".$_SESSION['os_role'].'.id_'.$pattern_table.' = view__'. $pattern_table."__".$_SESSION['os_role'].'.id_'.$pattern_table; }
					if ( ! in_array("view__".$pattern_table."__".$_SESSION['os_role'].'.'.$pattern_key.' AS '.$pattern,$need['select']) ) { $need['select'][] = "view__".$pattern_table."__".$_SESSION['os_role'].'.'.$pattern_key.' AS '.$pattern; }
					if ( ! in_array("view__".$pattern_table."__".$_SESSION['os_role'].'.id_'.$pattern_table.' = '.$PARAM['id_'.$pattern_table],$need['where']) ) { $need['where'][] = "view__".$pattern_table."__".$_SESSION['os_role'].'.id_'.$pattern_table.' = '.$PARAM['id_'.$pattern_table]; }					
				} else {	
					if ( isset($PARAM[$pattern]) ) {
                        unset($_json_array);
                        if ( isset($pattern_entry) ) {
                            $_json_array = json_decode(json_encode($PARAM[$pattern]),true);
                            if ( ! is_array($_json_array) ) {
	            				$success['value'] = false; $success['error'] = __FUNCTION__ . ": Komponenten oder multiple Einträge sind im Feld ".$pattern." nicht vorgesehen.";
                            } else {
                                if ( isset($pattern_component) ) {
                                    if ( ! isset($_json_array[$pattern_component]) OR ! is_array($_json_array[$pattern_component]) ) {
                                        $success['value'] = false; $success['error'] = __FUNCTION__ . ": Komponente ".$pattern_component." ist im Feld ".$pattern." nicht vorgesehen.";
                                    } else {
                                        $_json_array = $_json_array[$pattern_component];
                                    }
                                }
                                switch($pattern_entry) {
                                    case "last":
                                        $pattern_entry = sizeof($_json_array)-1;
                                        break;
                                    case "this":
                                        $pattern_entry = (int)$trigger['this'];
                                        break;
                                    default:
                                        $pattern_entry = (int)$pattern_entry;
                                        break;
                                }
                                if ( isset($_json_array[$pattern_entry]) ) {
                                    $_json_array = $_json_array[$pattern_entry];
                                } else {
                                    $_json_array = "(ungesetzt)";
                                }
                            }
                        }
						$value = preg_replace('/\$'.str_replace('[','\[',str_replace(']','\]',$pattern_specs)).'/',_cleanup($_json_array),$value);
					} else {
						//$value = preg_replace('/\$'.$pattern.'/','(ungesetzt)',$value);
						//better: get that value; this makes it compatible to mass editing
                        //
                        //$PARAM['id_'.$pattern_table] might be a number, not an array: this makes it compatible to mass editing (I think):
                        if ( ! is_array($PARAM['id_'.$pattern_table]) ) { $PARAM['id_'.$pattern_table] = array($PARAM['id_'.$pattern_table]); }
						if ( ! in_array("view__".$pattern_table."__".$_SESSION['os_role'].'.'.$pattern_key.' AS '.$pattern,$need['select']) ) { $need['select'][] = "view__".$pattern_table."__".$_SESSION['os_role'].'.'.$pattern_key.' AS '.$pattern; }
						if ( ! in_array("view__".$pattern_table."__".$_SESSION['os_role'].'.id_'.$pattern_table.' IN ('.implode(',',$PARAM['id_'.$pattern_table]).')',$need['where']) ) { $need['where'][] = "view__".$pattern_table."__".$_SESSION['os_role'].'.id_'.$pattern_table.' IN ('.implode(',',$PARAM['id_'.$pattern_table]).')'; }					
					}
				}
			}
			//handle fields from attributions (one new mysql request)
			if ( sizeof($need['select']) > 0 ) {
				unset($stmt_array);
				$stmt_array = array();
                //to be continued: this seems not to work...
				$stmt_array['stmt'] = "SELECT DISTINCT " . implode(',',$need['select']) . " FROM " . implode(' LEFT JOIN ',$need['from']) . " WHERE " . implode(' AND ',$need['where']); 
				$foreignfields = execute_stmt($stmt_array,$conn,true);
				if (! isset($foreignfields['result'])) {
					$success['value'] = false; $success['error'] = __FUNCTION__ . ": Ein oder mehrere Felder konnten nicht gelesen werden";
				} else {
					$foreignfields = $foreignfields['result'][0]; //multiple ids are handled by FUNCTIONAction, here there must be only one table id
					foreach ( $foreignfields as $pattern => $foreignvalue ) {
                        //look for components and multiple field entries
                        unset($matches);
                        preg_match_all('/\$('.$pattern.'[^ ,\$\"\'\.\;\:\!\?\)]*)/',$value,$matches);
                        foreach ( $matches[1] as $pattern_specs ) {
                            //handle multiple and combined fields, e.g. opsz_vermittlungslisten__vermitteltzu[2][last]
                            $pattern_split = explode('[',$pattern_specs);
                            $pattern = $pattern_split[0];
                            unset($pattern_component);
                            unset($pattern_entry);
                            if ( isset($pattern_split[2]) ) { 
                                $pattern_component = (int)str_replace(']','',$pattern_split[1]);
                                $pattern_entry = str_replace(']','',$pattern_split[2]);
                            } elseif ( isset($pattern_split[1]) ) {
                                $pattern_entry = str_replace(']','',$pattern_split[1]);
                            }
                            
                            unset($_json_array);
                            if ( isset($pattern_entry) ) {
                                $_json_array = json_decode($foreignvalue,true);
                                if ( $_json_array === null ) {
                                    $success['value'] = false; $success['error'] = __FUNCTION__ . ": Komponenten oder multiple Einträge sind im Feld ".$pattern." nicht vorgesehen.";
                                } else {
                                    if ( isset($pattern_component) ) {
                                        if ( ! isset($_json_array[$pattern_component]) OR ! is_array($_json_array[$pattern_component]) ) {
                                            $success['value'] = false; $success['error'] = __FUNCTION__ . ": Komponente ".$pattern_component." ist im Feld ".$pattern." nicht vorgesehen.";
                                        } else {
                                            $_json_array = $_json_array[$pattern_component];
                                        }
                                    }
                                    switch($pattern_entry) {
                                        case "last":
                                            $pattern_entry = sizeof($_json_array)-1;
                                            break;
                                        case "this":
                                            $pattern_entry = (int)$trigger['this'];
                                            break;
                                        default:
                                            $pattern_entry = (int)$pattern_entry;
                                            break;
                                    }
                                    if ( isset($_json_array[$pattern_entry]) ) {
                                        $foreignvalue = $_json_array[$pattern_entry];
                                    } else {
                                        $foreignvalue = "(ungesetzt)";
                                    }
                                }
                            }
                            $value = preg_replace('/\$'.$pattern_specs.'/',_cleanup($foreignvalue),$value);
                        }
					}
				}
			}
			//replace PROFILE values, e.g. $PROFILE(name,vorname~Max Müller)[email]
			if ( $needProfiles ) {
				$profiles = getProfiles($conn);
				unset($matches);
				preg_match_all('/\$PROFILE\(\s*([^\s~]*)\s*~\s*([^\)]*)\)\[([^\]]*)\]/',$value,$matches);
				if ( isset($_SESSION['DEBUG']) AND $_SESSION['DEBUG'] ) { file_put_contents($GLOBALS["debugpath"].$GLOBALS["debugfilename"],__FUNCTION__ .": ".json_encode($matches).PHP_EOL,FILE_APPEND); }
				if ( sizeof($matches[1]) > 0 ) {
					$profilecompareto = $matches[1];
					$profilecomparestring = $matches[2];
					$profilefield = $matches[3];
					for ( $i = 0; $i < sizeof($matches[1]); $i++ ) {
						$replaceby = '';
						$sim = 0.2; //threshold for calling it a hit
						foreach ( $profiles as $profile ) {
							$profile_array = json_decode($profile,true);
							$comparetostring = '';
							$compareto_array = explode(',',$profilecompareto[$i]);
							foreach ($compareto_array as $compareto_field) {
								if ( isset($profile_array[$compareto_field]) ) {
									$comparetostring .= $profile_array[$compareto_field].' ';
								}
							}
							$newsim = similarity($comparetostring,$profilecomparestring[$i]);
							if (  $newsim > $sim ) {
								if ( isset($profile_array[$profilefield[$i]]) ) {
									$replaceby = $profile_array[$profilefield[$i]];
									$sim = $newsim; 
								} else {
									$success['value'] = false; $success['error'] = __FUNCTION__ . ": nötige Profildaten sind nicht gesetzt";
									$replaceby = '';
								}
							}
						}
						$value = str_replace($matches[0][$i],_cleanup($replaceby),$value);
					}
				}
			}
			//replace ENV values, e.g. $ENV(date)
			if ( $needEnv ) {
				unset($matches);
				preg_match_all('/\$ENV\(\s*([^\s~]*)\s*\)/',$value,$matches);
                if ( sizeof($matches[1]) > 0 ) {
					for ( $i = 0; $i < sizeof($matches[1]); $i++ ) {
                        switch($matches[1][$i]) {
                            case "date":
        						$value = str_replace($matches[0][$i],date('d.m.Y'),$value);
                                break;
                            case "time":
        						$value = str_replace($matches[0][$i],date('H:m'),$value);
                                break;
                            case "datetime":
        						$value = str_replace($matches[0][$i],date('d.m.Y H:m'),$value);
                                break;
                        }
                    }
                }
            }
		}
		$_tmpconfig[$header] = $value;
	}
	return array("success" => $success, "return" => $_tmpconfig);
}

function FUNCTIONparseIfs(array $_config) {
	$success = array( "value" => true, "error" => "" );
	$_tmpconfig = array();
	foreach ( $_config as $header => $value ) {
		if ( gettype($value) == "array" ) {
            $tmpreturn = FUNCTIONparseIfs($value); 
            $value = $tmpreturn['return'];
            $success['value'] = $success['value'] AND $tmpreturn['success']['value'];
            $success['error'] .= $tmpreturn['success']['error'];
        }
		else {
			//find all %%if without any other %%if until %%endif (deepest nested)
			//and begin replacing there
			//do this recursively
			preg_match_all('/(?<=%%if)((?!%%if)(?!%%endif).)*(?=%%endif)/',$value,$matches);
			while ( isset($matches[0]) and sizeof($matches[0]) > 0 and $matches[0][0] != '') {
				foreach ( $matches[0] as $ifstatement ) {
					//remove blanks between ' and } also for str and strelse...
					preg_match('/(?<=\()\s*(\'([^\']*)\'|([^=]*))\s*(==|<|>|\^=|=\$|\!=)\s*(\'([^\']*)\'|([^\)]*))\s*\)\s*\{\s*(\'([^\']*)\'|([^\}]*))\s*\}\s*(%%else)?\s*[^\{]*\{?\s*(\'([^\']*)\'|([^\}]*))\}?(\s*)/',$ifstatement,$condition);
					if ( sizeof($condition) < 16 ) { $success = array ( "value" => false, "error" => __FUNCTION__ . ": Syntax error in if-statement \"%%if ".$ifstatement."%%endif\"" ); }
					//$lhs = str_replace("'","",$condition[1]);
					//$rhs = str_replace("'","",$condition[2]);
					//$str = str_replace("'","",$condition[3]);
					//$else = $condition[4];
					$lhs = trim($condition[2].$condition[3]);
					$rel = $condition[4];
					$rhs = trim($condition[6].$condition[7]);
					$str = trim($condition[9].$condition[10]);
					$else = $condition[11];
					$strelse = trim($condition[13].$condition[14]);
					$cond_satisfied = false;
					switch($rel) {
						//identity
						case "==":
							if ( $lhs == $rhs ) { $cond_satisfied = true; }
							break;
						//alphabetically or numerically smaller						
						case "<":
							if ( $lhs < $rhs or (float)$lhs < (float)$rhs) { $cond_satisfied = true; }
							break;						
						//alphabetically or numerically bigger						
						case ">":
							if ( $lhs > $rhs or (float)$lhs > (float)$rhs) { $cond_satisfied = true; }
							break;
						//starts with						
						case "^=":
							if ( strpos($lhs,$rhs) === 0 ) { $cond_satisfied = true; }
							break;						
						//ends with						
						case "=$":
							if ( strpos(strrev($lhs),strrev($rhs)) === 0 ) { $cond_satisfied = true; }
							break;
						//inequality						
						case "!=":
							if ( $lhs != $rhs ) { $cond_satisfied = true; }
							break;						
					}
					if ( $cond_satisfied ) {
						$value = str_replace('%%if'.$ifstatement.'%%endif',$str,$value);
					} else {
						if ( $else == '%%else' ) {
							$value = str_replace('%%if'.$ifstatement.'%%endif',$strelse,$value);
						} else {
							$value = str_replace('%%if'.$ifstatement.'%%endif','',$value);
						}
					}
				}
				unset($matches);
				preg_match_all('/(?<=%%if)((?!%%if).)*(?=%%endif)/',$value,$matches);
			}
		}
		$_tmpconfig[$header] = $value;
	}
	return array("success" => $success, "return" => $_tmpconfig);
}

function FUNCTIONpreprocess(string $functionname,string $config,array $trigger,array $PARAM,mysqli $conn) {
	if ( $config != '' ) {
		$_config = getFunctionConfig($functionname,$conn)[$config];
	} else {
		$_config = getFunctionConfig($functionname,$conn);
	}
	//use dbAction values as possible config keys
	if ( isset($_config[$PARAM['dbAction']]) ) {
		$_config = $_config[$PARAM['dbAction']];
	} else {
		$_config = $_config['default'];
	}
	//$_flags = getFunctionFlags($functionname,$conn);
	//determine whether function has to be executed according to flags (as soon as there are those...)
	//...(return if answer is no)
	//replace placeholders
	$_result1 = FUNCTIONreplacePlaceholders($_config,$trigger,$PARAM,$conn);
	//parse if else endif statements
	$_result = FUNCTIONparseIfs($_result1['return']); // $_result1['success'] error status array('value' => bool,'error' => string), $_result1['return']: string; parsing result
	$_result['success']['value'] = $_result1['success']['value'] AND $_result['success']['value'];
	$_result['success']['error'] = $_result1['success']['error'].'; '.$_result['success']['error'];
	if ( isset($_SESSION['DEBUG']) AND $_SESSION['DEBUG'] ) { file_put_contents($GLOBALS["debugpath"].$GLOBALS["debugfilename"],__FUNCTION__ .": ".json_encode($_result).PHP_EOL,FILE_APPEND); }
	return $_result;
}

function FUNCTIONeventlog(string $functionmachine,array $logdata,array $PARAM,mysqli $conn) {
	unset($_stmt_array);
	$_stmt_array = array();
	$_stmt_array['stmt'] = "SELECT functionreadable FROM os_functions WHERE functionmachine = ?";
	$_stmt_array['str_types'] = 's';
	$_stmt_array['arr_values'] = array($functionmachine);
	$FUNCTIONREADABLE = execute_stmt($_stmt_array,$conn)['result']['functionreadable'][0];
	//log for every id (table + attributions)
	$IDTABLES = array(); $IDVALUES = array();
	foreach ( $PARAM as $tablekey => $value ) {
		if (strpos($tablekey,'id_') === 0) {
			$IDTABLES[] = $tablekey;
			if ( is_array(json_decode($value)) ) { $IDVALUES[] = json_decode($value)[0]; } else { $IDVALUES[] = $value; }
		}
	}
	unset($_stmt_array);
	$_stmt_array = array();
	$_stmt_array['stmt'] = "INSERT INTO view__os_events__".$_SESSION['os_role']."(".implode(',',$IDTABLES).",eventfunction,eventstatus,eventlog) VALUES (".implode(',',$IDVALUES).",'".$FUNCTIONREADABLE."','".$logdata['status']."','".json_encode($logdata['log'],JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."')";
	if ( isset($_SESSION['DEBUG']) AND $_SESSION['DEBUG'] ) { file_put_contents($GLOBALS["debugpath"].$GLOBALS["debugfilename"],__FUNCTION__ .": ".json_encode($_stmt_array).PHP_EOL,FILE_APPEND); }
	execute_stmt($_stmt_array,$conn);
}

// returns array of JSON strings of machine profiles with index 0 being the JSON string of the user's private profile
// $profilefieldname and $searchstring are optional parameters
// if you want to specify $searchstring but not $profilefieldname use $profilefieldname = "_ALL")
// if you want to get public profiles instead specify $public = true (for displaying in function results)
function getProfiles(mysqli $conn, string $profilefieldname='', string $searchstring='', bool $public=false) {
	$profiles = array();
	//get own profile info
	unset($_stmt_array);
	$_stmt_array['stmt'] = 'SELECT _private from os_userprofiles WHERE userid = ?';
	$_stmt_array['str_types'] = "i";
	$_stmt_array['arr_values'] = array();
	$_stmt_array['arr_values'][] = $_SESSION['os_user'];
	$myProfile = execute_stmt($_stmt_array,$conn)['result']['_private'][0];
	if ( isset($myProfile) ) { $profiles = array($myProfile); }
	//get other profiles; you must read them from _machine (_public is collected by a separate function so the developper is aware of this..)
	unset($_stmt_array);
	$_stmt_array['stmt'] = 'SELECT _machine from os_userprofiles WHERE userid != ?';
	$_stmt_array['str_types'] = "i";
	$_stmt_array['arr_values'] = array();
	$_stmt_array['arr_values'][] = $_SESSION['os_user'];
	$otherProfiles = execute_stmt($_stmt_array,$conn)['result']['_machine'];
	if (is_array($otherProfiles)) {
		$profiles = array_merge($profiles,$otherProfiles);
	}
	//filter by fieldname first
	if ( $profilefieldname != '' AND $profilefieldname != '_ALL') {
		$filteredprofiles = array();
		foreach ( $profiles as $profile ) {
			$filteredprofiles[] = json_encode(array('userid' => json_decode($profile,true)['userid'],$profilefieldname => json_decode($profile,true)[$profilefieldname]),JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
		$profiles = json_decode(json_encode($filteredprofiles,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),true);
	}
	//filter by searchstring
	if ( $searchstring != '' ) {
		$filteredprofiles = array();
		foreach ( $profiles as $profile ) {
			if ( preg_match($searchstring,$profile) ) {
				$filteredprofiles[] = $profile;
			}
		}
		$profiles = json_decode(json_encode($filteredprofiles,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),true);
	}
	return $profiles;
}

function similarity_asym(string $string1,string $string2) {
	// compute maximal common substrings, sum length squares; compare to square of minimal string length
	// eg., abcd abcbcd, (3²+3²)/(4²) > 1; is this ok?
	// product of lgth: 18/24 < 1
	// two mcs of length a,b intersect in l charactersin string 1 => length1 > a+b-l, length2 > a+b-l , correct for intersection:
	// 2(a+b-l)^2-l^2
	// e.g abab, baba; mcs: aba, bab, 18>16,  
	
	//mcs starting with string1
	//throw no error if strings are empty: not really needed, is it?
	//string1 = string1 + ' ';
	//string2 = string2 + ' ';
	//
	if ( $string1 === '' ) { $string1 = ' '; }
	if ( $string2 === '' ) { $string2 = ' '; }
	$string1 = substr($string1,0,1).strtolower(substr($string1,1));
	$string2 = substr($string2,0,1).strtolower(substr($string2,1));
	$sim = 0;
	$_index = 0;
	while ( $_index < strlen($string1) ) {
		$contained = true;
		$_length = 0;
		$_index2new = 0;
		while ( $contained ) {
			$_index2 = $_index2new;
			$_length += 1;
			$_index2new = strpos($string2,substr($string1,$_index,$_length));
			if ( $_index2new === false || $_length + $_index > strlen($string1) ) { $contained = false; }		
		}
		$sim += ( ( strlen($string1) - $_index ) * ( strlen($string1) - $_index ) - ( strlen($string1) - $_index - $_length + 1 ) * ( strlen($string1) - $_index - $_length + 1 ) ) * ( ( strlen($string2) - $_index2 ) * ( strlen($string2) - $_index2 ) - ( strlen($string2) - $_index2 - $_length + 1 ) * ( strlen($string2) - $_index2 - $_length + 1 ) ) / ( 1+log(strlen($string1)/strlen($string2))*log(strlen($string1)/strlen($string2)) );
		$_index += $_length;
	}
	$sim = $sim/( strlen($string1)*strlen($string1)*strlen($string2)*strlen($string2) );
	return $sim;
}

function similarity(string $string1,string $string2) {
	return max(similarity_asym($string1,$string2),similarity_asym($string2,$string1));
}

//sends mail as txt and html with customer logo
function sendmail(array $headers) {
	$boundary = '=-'.randomString(24);
	$boundary_text = '=-'.randomString(24);
	$additional_headers = array(
//		"Content-Type"  => "multipart/alternative; boundary=\"=-nEwq7ZLdmq7gg7KoqWYj\"",
		"Content-Type"  => "multipart/mixed; boundary=\"".$boundary."\"",
		"MIME-Version" => "1.0"
	);
	$argument = array();
	$argument[2] = <<< EndOfBody
--_boundary_
Content-Type: multipart/alternative; boundary="_boundary_inner_";

--_boundary_inner_
Content-Type: text/plain; charset="UTF-8"

_body.txt_
--_boundary_inner_
Content-Type: text/html; charset="utf-8"

<!DOCTYPE html>
<html dir="ltr">
<head>
</head>
<body style="text-align:left; direction:ltr;">
<style>
.logo, .text { float: left; font-family: sans-serif; }
.logo { width: 100px; margin: 10px; }
.text { width: calc(100vw - 200px); }
.text p:first-child { font-size: 30px; line-height: 100px; display: inline; }
</style>
<div class="logo">
<picture>
<img src="data:image/png;base64,_logo.b64_" width="100%">
</picture>
</div>
<div class="text">
<p>_body.html_</p>
</div>
</body>
</html>
--_boundary_inner_--
	
EndOfBody;
	foreach ( $headers as $header => $value ) {
		$processed = false;
		if ( $header == "To" ) { $argument[0] = $value; $processed = true; }
		if ( $header == "Subject" ) { $argument[1] = $value; $processed = true; }
		if ( $header == "Body" ) { 
			$argument[2] = str_replace('_body.txt_',$value,$argument[2]);
			$argument[2] = str_replace('_body.html_',preg_replace('/[\r\n]+/','</p><p>',$value),$argument[2]);
			$argument[2] = str_replace('_logo.b64_',base64_encode(file_get_contents('../../vendor/img/logo.png')),$argument[2]);
			$processed = true;
		}
		if ( $header == "Attachment" ) {
			$attach = <<< EndOfBody
--_boundary_
Content-Type: _mimetype_; charset="utf-8"; method=REQUEST; name="_filename_"
Content-Disposition: attachment; filename="_filename_"
Content-Transfer-Encoding: base64

_attachmentbody_

EndOfBody;
			$attach = str_replace('_mimetype_',$value['mimetype'],$attach);
			$attach = str_replace('_filename_',$value['filename'],$attach);
			$attach = str_replace('_attachmentbody_',base64_encode($value['body']),$attach);
//			$attach = str_replace('_attachmentbody_',json_encode($value),$attach);
			$argument[2] .= $attach;
			$processed = true;
		}
		if ( ! $processed ) { $additional_headers[$header] = $value; }
	}
	$argument[2] .= '--_boundary_--';
	$argument[2] = str_replace('_boundary_inner_',$boundary_text,$argument[2]);
	$argument[2] = str_replace('_boundary_',$boundary,$argument[2]);
	return mail($argument[0],$argument[1],$argument[2],$additional_headers);
}
?>
