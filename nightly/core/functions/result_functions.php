<?php
function _addToFilterStatement ($values,$filter_results,$komma,$_newkomma = '',$keyreadable,$index,$value,$tmpvalue = '',$separator = '',$emptyisall = false) {
	switch($index) {
		case 1001:  
			if ( json_encode($value) == '[""]' ) { $value = array('[unbestimmt]'); };
//						$filter_results .= $komma . ' von '. _cleanup(json_encode($value)) . '<br>'; $komma = ' ';
			$tmpvalue = $value;
			break;
		case 1002:  
			if ( json_encode($value) == '[""]' ) { $value = array('[unbestimmt]'); };
			if ( $separator === '' ) { $separator = ', <br /><span style="opacity:0"><b>'.$keyreadable.'</b> = </span>'; }
			$value_combined = array_combine($tmpvalue,$value);
			foreach ( $value_combined as $von=>$bis ) {
				$filter_results .= $komma . ' von ' . _cleanup($von) . ' bis '. _cleanup($bis); $komma = $separator;
			}
			unset($tmpvalue);
			break;
		case 7001:
			$tmpvalue = array();
			foreach ( $value as $colors ) {
				if ( $colors == '["_all"]' ) { $tmpvalue[] = ''; continue; }
				$colors = json_decode($colors,true);
				$tmpstring = '';
				foreach ($colors as $color) {
					$tmpstring .= '<span class="note_'.$color.'">&nbsp;&nbsp;&nbsp;</span>';
				}
				$tmpvalue[] = $tmpstring;
			}
			break;
		case 7002:
			$value_combined = array_combine($tmpvalue,$value);
			foreach ($value_combined as $colors => $text) {
				$filter_results .= $komma . $colors . _cleanup($text);
				if ( $separator === '' ) { $komma = $_newkomma; } else { $komma = $separator; }
			} 
			break;
		case 5001:  	
//				if ( json_encode($value) == '[""]' ) { $value = array('0'); };
			if ( json_encode($value) == '[""]' ) { $value = array('[unbestimmt]'); };
//						$filter_results .= $komma . ' von '. _cleanup(json_encode($value)) . '<br>'; $komma = ' ';
			$tmpvalue = $value;
			break;
		case 5002:  
//				if ( json_encode($value) == '[""]' ) { $value = array('1000000000'); };
			if ( json_encode($value) == '[""]' ) { $value = array('[unbestimmt]'); };
			if ( $separator === '' ) { $separator = ', <br /><span style="opacity:0"><b>'.$keyreadable.'</b> = </span>'; }
			$value_combined = array_combine($tmpvalue,$value);
			foreach ( $value_combined as $von=>$bis ) {
				$filter_results .= $komma . ' von ' . _cleanup($von) . ' bis '. _cleanup($bis); $komma = $separator;
			}
			unset($tmpvalue);
			break;
		case 6001:
			$cmp_index = 0;
			$cmp_values = array();
			while ( array_key_exists(6001+$cmp_index,$values) ) {
				array_push($cmp_values,$values[6001+$cmp_index]);
				$cmp_index++;					
			}
			$filter_length = _len($cmp_values);
			$separator = ' + ';
			for ( $j = 0; $j < $filter_length; $j++ ) { // $j is item nunmber
				$item_values = array();
				for ( $i = 0; $i < $cmp_index; $i++ ) { // $i is compound number
					array_push($item_values,_extract($cmp_values,$i,$j));
				}
				foreach ( $item_values as $compound ) {
					foreach ( $compound as $cmpindex=>$cmpvalue ) 
					{
						$_tmpresult = _addToFilterStatement($compound,$filter_results,$komma,$_newkomma,$keyreadable,$cmpindex,$cmpvalue,$tmpvalue,$separator,true); // 'true': empty is 'all'
						$separator = ' + ';
						$filter_results = $_tmpresult[0]; $tmpvalue = $_tmpresult[1]; $komma = $_tmpresult[2];
					}
				}
				$komma = $_newkomma.' <br /><span style="opacity:0"><b>'.$keyreadable.'</b> = </span>';
			}
			break;
		case 1003:
		case 1004:
		case 1005:
		case 1006:
		case 5003:
		case 2001:
		case 3001:
			break;
		default: 
			if ( $emptyisall AND $value === '' ) { $value = '[ungefiltert]'; }
			if ( $value != '_all' AND $index < 6001 ) { 
				$filter_results .= $komma . _cleanup($value); 
				if ( $separator === '' ) { $komma = $_newkomma; } else { $komma = $separator; }
			}; 
			break;
	}
    if ( ! isset($tmpvalue) ) { $tmpvalue = null; }
	return array($filter_results,$tmpvalue,$komma);
}

function _generateFilterStatementForKey($values) {
	$tmpvalue = '';
	$filter_results = '';
	$komma = '';
	$_newkomma = '';
	$tmpvalue = '';
	$keyreadable = '';
	$_old_filter_results = $filter_results;
	foreach ( $values as $index=>$value ) 
	{
		$_tmpresult = _addToFilterStatement($values,$filter_results,$komma,$_newkomma,$keyreadable,$index,$value,$tmpvalue);
		$filter_results = $_tmpresult[0]; $tmpvalue = $_tmpresult[1]; $komma = $_tmpresult[2];
	}
	//now remove filters only containing 'unbestimmt' and 'ungefiltert'
	$_diff_results = str_replace($_old_filter_results,'',$filter_results);
	$_diff_results = str_replace('<b>'.$keyreadable.'</b> = ','',$_diff_results);
	$_diff_results = str_replace('<b>'.$keyreadable.'</b> &#8800; ','',$_diff_results);
	preg_match('/[^(\[ungefiltert\])(\[unbestimmt\])(von)(bis) +]/',$_diff_results,$_foundfilters);
	if ( ! isset($_foundfilters[0]) ) {
		$filter_results = $_old_filter_results;
	} else {
		$filter_results .= '<br />';
	}
	return $filter_results;
}

function generateFilterStatement(array $parameters, mysqli $conn, string $_table = 'os_all', bool $complement = false, bool $searchinresults = false)
{
	$_config = getConfig($conn);
	$_TABLES = $_config['table'];
	$filter_results = '';
	if ( $complement ) { $filter_results = '<b>Komplement von</b><br /><br />'; }
	if ( $searchinresults ) { 
		if ( substr($_SESSION['currentfilters'],0,7) != '<em>In:' ) {
			$filter_results .= '<em>In:<br />';
		} else {
			$filter_results .= '<em>';
		}
		$filter_results .= $_SESSION['currentfilters'];
		$filter_results .= '</em><br />';
	}
	foreach ( $parameters as $tablekey=>$values )
	{
		$_old_filter_results = $filter_results;
		if ( isset($parameters['table']) ) { $table = $parameters['table'][0]; } else { $table = $_table; };
		if ( ! strpos($tablekey,'__') OR strpos($tablekey,'__id_') ) { continue; }
		
		$tablekey_array = explode('__',$tablekey,2);
		$table = $tablekey_array[0];
		$key = $tablekey_array[1];
		if ( in_array($key,array('table')) ) { continue; }
		if ( ! in_array($table,$_TABLES) ) { continue; }
		unset($_stmt_array); $_stmt_array = array();
		if ( in_array($key,$_TABLES) ) {
			$_stmt_array['stmt'] = "SELECT CONCAT('#',tablereadable) AS keyreadable FROM os_tables WHERE tablemachine = ?";
			$_stmt_array['str_types'] = "s";
			$_stmt_array['arr_values'] = array();
			$_stmt_array['arr_values'][] = $key;
			$_tmp_result = array();
		} else {
			$_stmt_array['stmt'] = "SELECT keyreadable FROM ".$table."_permissions WHERE keymachine = ?";
			$_stmt_array['str_types'] = "s";
			$_stmt_array['arr_values'] = array();
			$_stmt_array['arr_values'][] = $key;
			$_tmp_result = array();
		}
		$keyreadable = explode(': ',execute_stmt($_stmt_array,$conn)['result']['keyreadable'][0])[0];
		unset($values[-1]); $tmpvalues = $values; unset($tmpvalues[2001]);
		if ( sizeof($tmpvalues) > 1 ) 
		{
			$filter_results .= '<b>'.$keyreadable.'</b>';
			$komma = ' = ';
			unset($index); unset($value);
			if ( isset($values[2001]) AND $values[2001] == "-499" ) { $_newkomma = '+'; } else { $_newkomma = ', '; }
			if ( isset($values[3001]) ) { $komma = " &#8800; "; }
			$tmpvalue = '';
			foreach ( $values as $index=>$value ) 
			{
				$_tmpresult = _addToFilterStatement($values,$filter_results,$komma,$_newkomma,$keyreadable,$index,$value,$tmpvalue);
				$filter_results = $_tmpresult[0]; $tmpvalue = $_tmpresult[1]; $komma = $_tmpresult[2];
			}
			//now remove filters only containing 'unbestimmt' and 'ungefiltert'
			$_diff_results = str_replace($_old_filter_results,'',$filter_results);
			$_diff_results = str_replace('<b>'.$keyreadable.'</b> = ','',$_diff_results);
			$_diff_results = str_replace('<b>'.$keyreadable.'</b> &#8800; ','',$_diff_results);
			preg_match('/[^(\[ungefiltert\])(\[unbestimmt\])(von)(bis) +]/',$_diff_results,$_foundfilters);
			if ( ! isset($_foundfilters[0]) ) {
				$filter_results = $_old_filter_results;
			} else {
				$filter_results .= '<br />';
			}
		}
	}
	if ( $filter_results === '' ) { $filter_results = "Keine"; }
	return $filter_results;
}

function generateResultTable(array $stmt_array, mysqli $conn, string $table = 'os_all')
{
    //deal with paging
    if ( ! isset($stmt_array['paging']) ) {
        $stmt_array['paging'] = [0,min($_SESSION['max_results'],$_SESSION['paging_default'])];
    }
    $stmt_array['stmt'] .= ' LIMIT '.(string)((int)$stmt_array['paging'][0]*(int)$stmt_array['paging'][1]).','.(string)((int)$stmt_array['paging'][1]+1);
    //
    $_starttime = microtime(true);
	$_result_array = _execute_stmt($stmt_array,$conn); $result = $_result_array['result'];
    $_endtime = microtime(true);
    if ( $result->num_rows > $_SESSION['exec_forecast_threshold'] ) {
        $_SESSION['sql_execution_rate'] = ($_endtime - $_starttime) / $result->num_rows;
    }
    //test if execution will exceed max_execution_time
    if ( isset($_SESSION['details_execution_rate']) AND isset($_SESSION['max_execution_time']) ) {
        if ( $_SESSION['details_execution_rate'] * $result->num_rows > $_SESSION['max_execution_time'] ) {
            return "<p>Die Erzeugung der Detailliste würde länger dauern als erlaubt. Bitte spezifiziere Deine Tabellen- und Filtereinstellungen.</p>";
        }
    }
    //test if page buttons are necessary
    if ( $result->num_rows > $stmt_array['paging'][1] ) { $_addnextpagebutton = true; } else {$_addnextpagebutton = false; }
    if ( $stmt_array['paging'][0] > 0 ) { $_addprevpagebutton = true ; } else { $_addprevpagebutton = false; }
    //
	$_config = getConfig($conn);
	if ( isset($_config['hiddenColumns']) ) { $HIDDEN = $_config['hiddenColumns']; } else { $HIDDEN = array(); };
	$TABLES = $_config['table'];
	$ICON = array(); $TABLEREADABLE = array();
	unset($_SESSION['results']);
	$_sessionresults = array();
	foreach ( $TABLES as $table )
	{
		unset($_stmt_array); $_stmt_array = array();
		$_stmt_array['stmt'] = "SELECT iconname,tablereadable FROM os_tables WHERE tablemachine = ?";
		$_stmt_array['str_types'] = "s";
		$_stmt_array['arr_values'] = array();
		$_stmt_array['arr_values'][] = $table;
		$_thisresult = execute_stmt($_stmt_array,$conn)['result'];
		$ICON[$table] = $_thisresult['iconname'][0];
		$TABLEREADABLE[$table] = $_thisresult['tablereadable'][0];
		unset($_thisresult);
		//reset the results session variables;
		$_sessionresults[$table] = array(-1);
	}
	unset($table);
	unset($oldvalue);
	$oldvalue = array();
	$table_results = "<form id=\"formMassEdit\" method=\"POST\" class=\"noreset function\"></form>";
    //add page buttons at top
    //if ( $_addprevpagebutton ){
        //$table_results .= "<div class=\"prevBtn pageBtn inline\" onclick=\"document.querySelector('[name=page]').value = ".(string)((int)$stmt_array['paging'][0]-1)."; document.querySelector('#formFilters').onsubmit(); return false;\"><i class=\"fas fa-chevron-left\"></i></div>";
    //}
    if ( $_addprevpagebutton OR $_addnextpagebutton ){
        $table_results .= "<label class=\"unlimitedWidth\">Seite </label><input class=\"pageNumber\" type=\"number\" min=1 onchange=\"document.querySelector('[name=page]').value = this.value -1; document.querySelector('#formFilters').onsubmit(); return false;\" value=\"".(string)((int)$stmt_array['paging'][0]+1)."\">";
    }
    //if ( $_addnextpagebutton ){
        //$table_results .= "<div class=\"nextBtn pageBtn inline\" onclick=\"document.querySelector('[name=page]').value = ".(string)((int)$stmt_array['paging'][0]+1)."; document.querySelector('#formFilters').onsubmit(); return false;\"><i class=\"fas fa-chevron-right\"></i></div>";
    //}
    //
	$table_results .= "<table id=\"db_results\">";
	$rcount = 0;
	if ( $result->num_rows > 0 ) {
		while ($row=$result->fetch_assoc()) {
				$table_results .= "<tr>";
				if ( $rcount == 0 ) {
					$table_results .= "<th>
						<input 
							form=\"formMassEdit\"
							type=\"checkbox\"
							id=\"editAll\"
							onclick=\"_toggleEditAll('formMassEdit','editAll')\"
						>
						<input 
							form=\"formMassEdit\"
							type=\"text\"
							id=\"editTableName\"
							name=\"massEdit[]\"
							hidden
						>
						</th>";
					foreach ($row as $tablekey=>$value) {
						if ( strpos($tablekey,'__id_') > 0 ) { continue; }
						$tablekey_array = explode('__',$tablekey,2);
						$table = $tablekey_array[0];
						$key = $tablekey_array[1];
						if ( in_array($key,$TABLES) ) {
							$keyreadable = "#".$TABLEREADABLE[$key];
						} else {						
							unset($_stmt_array); $_stmt_array = array();
							$_stmt_array['stmt'] = "SELECT keyreadable FROM ".$table."_permissions WHERE keymachine = ?";
							$_stmt_array['str_types'] = "s";
							$_stmt_array['arr_values'] = array();
							$_stmt_array['arr_values'][] = $key;
							$_tmp_result = array();
							$keyreadable = explode(': ',execute_stmt($_stmt_array,$conn)['result']['keyreadable'][0])[0];
						}
						if ( in_array($tablekey,$HIDDEN) ) { $_hidden = 'hidecolumn'; } else { $_hidden = ''; }
						$table_results .= "<th class=\"disabled\" title=\"".$keyreadable."\" oncontextmenu=\"_contextMenu(this)\" onclick=\"_toggleColumn(this,'". $tablekey ."');\"><i class=\"fas fa-angle-down\"></i></th><th class=\"tableheader " . $tablekey . " " . $_hidden . "\"  oncontextmenu=\"_contextMenu(this); return false;\" onclick=\"_toggleColumn(this,'". $tablekey ."');\">" . $keyreadable . "</th>";
					}
					$table_results .= "</tr><tr>";
				}
                //do not show the last result if it exceeds the pagesize (it is used to determine if there is a next page)
                if ( $rcount >= $stmt_array['paging'][1] ) { $rcount++; continue; }
                //
				$_editvalue = array();
				foreach ( $TABLES as $table ) 
				{
					if ( isset($row[$table.'__'.'id_'.$table]) ) {
						$_editvalue['id_'.$table] = $row[$table.'__'.'id_'.$table];
						//populate results session variable
						$_sessionresults[$table][] = $row[$table.'__'.'id_'.$table];
					}
				} 
				$table_results .= "<td>
					<input
						form=\"formMassEdit\"
						type=\"checkbox\"
						name=\"massEdit[]\"
						value=\"".htmlentities(json_encode($_editvalue))."\"
					></td>"; 
				foreach ($row as $key=>$value) {
					if ( strpos($key,'__id_') > 0 ) { continue; }
					$value = _cleanup($value);
					$table = explode('__',$key,2)[0];
					if ( in_array($key,$HIDDEN) ) { $_hidden = 'hidecolumn'; } else { $_hidden = ''; }
					if ( isset($oldvalue[$table]) AND $row[$table.'__'.'id_'.$table] == $oldvalue[$table] ) {
						$table_results .= "<td>&nbsp;</td><td class=\"disabled " . $key . " " . $_hidden . "\">" . $value . "</td>";
					} else {
						$table_results .= "<td>&nbsp;</td><td class=\"" . $key . " " . $_hidden . "\">" . $value . "</td>";
					}
					$oldvalue[$key] = $value;
				}
				foreach ( $TABLES as $table ) 
				{
					if ( isset($row[$table.'__'.'id_'.$table]) ) {
						$table_results .= "<td title=\"ID: ".$row[$table.'__'.'id_'.$table]."\" id=\"detailsTd_". $table . $row[$table.'__'.'id_'.$table] . "\" draggable=\"true\" ondragover=\"allowDrop(event)\" ondrop=\"dropOnDetails(event,this)\" ondragstart=\"dragOnDetails(event)\" ondragenter=\"dragenter(event)\" ondragleave=\"dragleave(event)\" ondragend=\"dragend(event)\"><form method=\"post\" id=\"detailsForm". $table . $row[$table.'__'.'id_'.$table] . "\" class=\"inline\" action=\"\" onsubmit=\"editEntries(this,'".$table."'); return false\"><input hidden form=\"detailsForm". $table . $row[$table.'__'.'id_'.$table] . "\"type=\"text\" value=\"". $row[$table.'__'.'id_'.$table] . "\" name=\"id_".$table."\" /><input form=\"detailsForm". $table . $row[$table.'__'.'id_'.$table] . "\" id=\"detail". $table . $row[$table.'__'.'id_'.$table] . "\" type=\"submit\" hidden /></form>";
						if ( isset($oldvalue[$table]) AND $row[$table.'__'.'id_'.$table] == $oldvalue[$table] ) {
							$table_results .= "<label for=\"detail". $table . $row[$table.'__'.'id_'.$table] . "\"><i oncontextmenu=\"return transportAttribution(this)\" class=\"disabled fas fa-".$ICON[$table]."\"></i></label></td>";
						} else {
							$table_results .= "<label for=\"detail". $table . $row[$table.'__'.'id_'.$table] . "\"><i oncontextmenu=\"return transportAttribution(this)\" class=\"fas fa-".$ICON[$table]."\"></i></label></td>";
						}
						$oldvalue[$table] = $row[$table.'__'.'id_'.$table];
					} else {
						$table_results .= "<td>&nbsp;</td>";
					}
				}
				$rnd2 = rand(0,32767);
                //add only new entey icons if attribution to main table exists (may be filtered with complement...)
				if ( isset($row[$TABLES[0].'__id_'.$TABLES[0]]) ) {
                    $table_results .= "
                    <td>
                        <input type=\"checkbox\" hidden class=\"toggle\" id=\"newEntries_".$rnd2."\">
                        <label for=\"newEntries_".$rnd2."\"><i class=\"fas fa-plus\"></i></label>
                        <div class=\"form newEntry\">
                            <input type=\"checkbox\" hidden class=\"toggle\" id=\"newEntries_".$rnd2."\">
                            <label for=\"newEntries_".$rnd2."\"><i class=\"fas fa-plus\"></i>&nbsp;</label>";
                    foreach ( $TABLES as $table ) {
                        if ( $table == $TABLES[0] ) { continue; }
                        $table_results .= "<label onclick=\"_setValue(this,'".$table."',1);\"><i class=\"fas fa-".$ICON[$table]."\"></i>&nbsp;</label>";
                    }
                    $table_results .= "
                            <form method=\"POST\">
                                <input type=\"number\" hidden name=\"id_".$TABLES[0]."\" value=\"".$row[$TABLES[0].'__id_'.$TABLES[0]]."\">
                                <input type=\"text\" hidden name=\"table[]\" value=\"\">
                            </form>					
                        </div>
                    </td>";
                }
				$table_results .= "</tr> ";
				$rcount++;
		}
	} else { $table_results .= "<tr><td>Deine Suche liefert leider keine Ergebnisse.</td><td>".$_result_array['dbMessage']."</td></tr>"; };
	$table_results .= "</table>";
	$_SESSION['results']=json_encode($_sessionresults);
	unset($_sessionresults);
    //compute rate in seconds per result
    if ( $result->num_rows > $_SESSION['exec_forecast_threshold'] ) {
        $_endtime = microtime(true);
        $_SESSION['details_execution_rate'] = ($_endtime - $_starttime) / $result->num_rows;
    }
    //add page buttons at bottom
    //if ( $_addprevpagebutton ){
        //$table_results .= "<div class=\"prevBtn pageBtn inline\" onclick=\"document.querySelector('[name=page]').value = ".(string)((int)$stmt_array['paging'][0]-1)."; document.querySelector('#formFilters').onsubmit(); return false;\"><i class=\"fas fa-chevron-left\"></i></div>";
    //}
    if ( $_addprevpagebutton OR $_addnextpagebutton ){
        $table_results .= "<label class=\"unlimitedWidth\">Seite </label><input class=\"pageNumber\" type=\"number\" min=1 onchange=\"document.querySelector('[name=page]').value = this.value - 1; document.querySelector('#formFilters').onsubmit(); return false;\" value=\"".(string)((int)$stmt_array['paging'][0]+1)."\">";
    }
    //if ( $_addnextpagebutton ){
        //$table_results .= "<div class=\"nextBtn pageBtn inline\" onclick=\"document.querySelector('[name=page]').value = ".(string)((int)$stmt_array['paging'][0]+1)."; document.querySelector('#formFilters').onsubmit(); return false;\"><i class=\"fas fa-chevron-right\"></i></div>";
    //}
    return $table_results;
}

function generateStatTable (array $stmt_array, mysqli $conn, string $table = 'os_all') 
{
    $_starttime = microtime(true);
	$_result_array = execute_stmt($stmt_array,$conn,true); //flip the result array to get rows
	if ( isset($_result_array['result']) ) { $result = $_result_array['result']; } else { $result = array(); }
    $_endtime = microtime(true);
    if ( sizeof($result) > $_SESSION['exec_forecast_threshold'] ) {
        $_SESSION['sql_execution_rate'] = ($_endtime - $_starttime) / sizeof($result);
    }
    //test if execution will exceed max_execution_time
    if ( isset($_SESSION['stat_execution_rate']) AND isset($_SESSION['max_execution_time']) ) {
        if ( $_SESSION['stat_execution_rate'] * sizeof($result) > $_SESSION['max_execution_time'] ) {
            return "<p>Die Erzeugung der Statistik würde länger dauern als erlaubt. Bitte spezifiziere Deine Tabellen- und Filtereinstellungen.</p>";
        }
    }
    //
	$table_results = "<div id=\"db_stat\">";
	$rcount = 0;
	$vrcount = array();
	$ccount = 0;
	$oldrow = array();
	$ccount_old = array();
	$rcount_old = array();
	$tmp_ccount_old = array();
	$tmp_rcount_old = array();
	$keys = array();
	$vrcount = array(); //varying rows /unique rows
	$vrcount_old = array(); 
	$rrcount = array(); //result rows
	$rrcount_old = array();
	$keyreadable = array();
	$edittype = array();
	
	//sortArray by Thomas Heuer
	function sortArray($data, $field)
	{
    if(!is_array($field)) $field = array($field);
    usort($data, function($a, $b) use($field) {
      $retval = 0;
      foreach($field as $fieldname) {
        if($retval == 0 AND $fieldname != '_none_') $retval = strnatcmp($a[$fieldname],$b[$fieldname]);
      }
      return $retval;
    });
    return $data;
	}
	
	//getConfig
	$config = getConfig($conn);
	
	if ( isset($result) AND sizeof($result) > 0 ) {
		foreach ( $result as $result_index => $_row ) {
//		for ( $i = 0; $i < sizeof($result); $i++ ) {
//			$row = $result[$i];
			$row_left = ''; $row_right = ''; $row_mostright = '';
			if ( $rcount == 0 ) {
				$ccount0 = 0;
				foreach ($_row as $tablekey=>$value) {
					if ( strpos($tablekey,'__id_')  ) { continue; }
					$tablekey_array = explode('__',$tablekey,2);
					$table = $tablekey_array[0];
					$key = $tablekey_array[1];
					$keys[] = $tablekey;
					$vrcount[$tablekey] = 0;
					$rrcount[$tablekey] = 0;
					$oldrow[$tablekey] = "x678dscjncxydsv&%/&"; //something that surely is no entry in the table (NULL etc my occur...)
					if ( $key == "id" ) { continue; }
					if ( in_array($key,$config['table']) ) {
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "SELECT CONCAT('#',tablereadable) AS keyreadable FROM os_tables WHERE tablemachine = ?";
						$_stmt_array['str_types'] = "s";
						$_stmt_array['arr_values'] = array();
						$_stmt_array['arr_values'][] = $key;
						$_tmp_result = execute_stmt($_stmt_array,$conn)['result'];
						$keyreadable[$key] = explode(': ',$_tmp_result['keyreadable'][0])[0];
						$edittype[$tablekey] = 'INTEGER';
					} else {					
						unset($_stmt_array); $_stmt_array = array();
						$_stmt_array['stmt'] = "SELECT keyreadable,edittype FROM ".$table."_permissions WHERE keymachine = ?";
						$_stmt_array['str_types'] = "s";
						$_stmt_array['arr_values'] = array();
						$_stmt_array['arr_values'][] = $key;
						$_tmp_result = execute_stmt($_stmt_array,$conn)['result'];
						$keyreadable[$key] = explode(': ',$_tmp_result['keyreadable'][0])[0];
						$edittype[$tablekey] = explode('; ',$_tmp_result['edittype'][0])[0]; //for getting rid of modifiers (MULTIPLE or DERIVED)
					}
					$table_results .= '<div class="nextlevel"><div class="unique value header" onclick="_toggleStatColumn('.$ccount0.','.sizeof($result).')">'.$keyreadable[$key].'</div>'; $row_right .= '</div>';
					$ccount0++;
				}
				$keys[] = '_none_'; $vrcount['_none_'] = 0; $rrcount['_none_'] = 0;
				$oldrow['_none_'] = "x678dscjncxydsv&%/&"; //something that surely is no entry in the table (NULL etc my occur...)
				$table_results .= $row_right; $row_right = '';
				$table_results .= '<hr>';
				$table_results .= '<div class="nextlevel">';
				$table_results .= '<input type="checkbox" class="toggle" id="stat'.$rcount.'x'.$ccount.'" hidden >'; 
				$table_results .= '<input type="checkbox" class="toggle" id="content'.$rcount.'x'.$ccount.'" hidden >'; 
				$table_results .= '<div class="column form"><label for="content'.$rcount.'x'.$ccount.'"><ul>';
				$tmp_rcount_old[$keys[0]] = $rcount;
				$tmp_ccount_old[$keys[0]] = $ccount;
			}
			unset($row); $row = array(); $row = $_row; $row['_none_'] = ''; $edittype['_none_'] = '';
			$rcount++;
			$ccount = 0;
			$new = false;
			$mustresort = false;
			for ( $i = 0; $i < sizeof($keys); $i++ ) {
				//was before, not after $key definition: if ( $key == "id" ) { continue; }
				$key = $keys[$i];
				if ( $key == "id" ) { continue; }
				$value = _cleanup($row[$keys[$i]]); //need control over the whole key array: first, last...
				switch($edittype[$key]) {
					case 'DATE':
					case 'DATETIME':
						if ( ! isset($config['filters'][$key][1001]) ) { $value = $row[$key]; break; }
						$mustresort = true;
						for ( $ii = 0; $ii < sizeof($config['filters'][$key][1001]); $ii++ ) {
							if ( strtotime($row[$key]) >= strtotime($config['filters'][$key][1001][$ii]) AND  ( strtotime($row[$key]) <= strtotime($config['filters'][$key][1002][$ii]) OR $config['filters'][$key][1002][$ii] === '' ) )
							{
								if ( isset($config['filters'][$key][1003][$ii]) AND $config['filters'][$key][1003][$ii] != '' ) {
									$result[$result_index][$keys[$i]] = $config['filters'][$key][1003][$ii];
								} else {
									$number = $ii+1;
									$result[$result_index][$keys[$i]] = 'Zeitraum '.$number;
								}
							}		 
						}
						break;
					case 'INTEGER':
					case 'DECIMAL':
						if ( ! isset($config['filters'][$key][5001]) ) { $value = $row[$key]; break; }
						$mustresort = true;
						for ( $ii = 0; $ii < sizeof($config['filters'][$key][5001]); $ii++ ) {
							if ( $row[$key] >= $config['filters'][$key][5001][$ii] AND  ( $row[$key] <= $config['filters'][$key][5002][$ii] OR $config['filters'][$key][5002][$ii] === '' ) )
							{
								$result[$result_index][$keys[$i].'_value'] = $result[$result_index][$keys[$i]];
								if ( isset($config['filters'][$key][5003][$ii]) AND $config['filters'][$key][5003][$ii] != '' ) {
									$result[$result_index][$keys[$i]] = $config['filters'][$key][5003][$ii];
								} else {
									$number = $ii+1;
									$result[$result_index][$keys[$i]] = 'Bereich '.$number;
								}
							}		 
						}
						break;
				}
			}
		}
		//now re-sort after value replacements!
		if ( $mustresort ) { $result = sortArray($result,$keys); }
		//
		$rcount = 0;
		foreach ( $result as $_row ) {
			unset($row); $row = array(); $row = $_row; $row['_none_'] = ''; $edittype['_none_'] = '';
			$rcount++;
			$ccount = 0;
			$new = false;
			$row_left = ''; $row_right = ''; $row_mostright = '';
			for ( $i = 0; $i < sizeof($keys); $i++ ) {			
				if ( $key == "id" ) { continue; }
				$key = $keys[$i];
				$value = _cleanup($row[$keys[$i]]); //need control over the whole key array: first, last...
//			foreach ($row as $key=>$value) {
//				$ccount++; $rrcount[$key]++; //echo($key.':'.$rrcount[$key].' ');
				$ccount++; 
				//take sum if key is a number field, count items otherwise
				switch($edittype[$key]) {
					case 'INTEGER':
					case 'DECIMAL':
						//take max if key is a counting attributions field (all values are the same and should not be added up)
						if ( substr($keyreadable[explode('__',$key,2)[1]],0,1) == '#' ) {
							$rrcount[$key] = max($rrcount[$key],$row[$key.'_value']);
						} else {
							$rrcount[$key] += $row[$key.'_value'];
						}
						break;
					default:
						$rrcount[$key]++;
						break;
				}
//				if ( $vrcount[$oldkey] = 1) { $vrcount[$key] = 1; };

				if ($new OR $oldrow[$key] != $value )
				{
//					$row_right = '</li>'.$row_right;
					$row_left .= '<li><div class="value">'.$value.'</div>';
					$vrcount[$key]++; //count every new entry
					if ( isset($keys[$i+1]) )
					{
						$row_left .= '<div class="nextlevel">';
						$row_left .= '<input type="checkbox" class="toggle" id="stat'.$rcount.'x'.$ccount.'" hidden >'; 
						$row_left .= '<input type="checkbox" class="toggle" id="content'.$rcount.'x'.$ccount.'" hidden >'; 
						$row_left .= '<div class="column form"><label for="content'.$rcount.'x'.$ccount.'"><ul>';
					}
				} 

				if ($new)
				{
					if ( $rcount > 1 ) {
						$row_right = '</div>'.$row_right;
						if ( isset($keys[$i+1]) ) 
						{
							$row_right = '<label class="stat" for="stat'.$rcount_old[$key].'x'.$ccount_old[$key].'"><span class="hits">'.$rrcount_old[$key].'</span>|<span class="unique">'.$vrcount_old[$key].'</span></label>'.$row_right;
						}
						else
						{
							$row_right = '<label class="stat last" id="last'.$rcount_old[$key].'x'.$ccount_old[$key].'"><span class="hits">'.$rrcount_old[$key].'</span></label>'.$row_right;
						}
						$row_right = '</li></ul></label></div>'.$row_right;
					}
				}

				if ( ($new OR $oldrow[$key] != $value ) AND isset($keys[$i+1]) ) 
				{ 
					$tmp_rcount_old[$keys[$i+1]] = $rcount;
					$tmp_ccount_old[$keys[$i+1]] = $ccount;
					$vrcount_old[$keys[$i+1]] = $vrcount[$keys[$i+1]];
					$rrcount_old[$keys[$i+1]] = $rrcount[$keys[$i+1]];
					$vrcount[$keys[$i+1]] = 0;
					$rrcount[$keys[$i+1]] = 0;
				}

				if ( $rcount == sizeof($result) ) {
					$row_mostright = '</div>'.$row_mostright;
						if ( isset($keys[$i+1]) ) 
						{
							$row_mostright = '<label class="stat" for="stat'.$tmp_rcount_old[$key].'x'.$tmp_ccount_old[$key].'"><span class="hits">'.$rrcount[$key].'</span>|<span class="unique">'.$vrcount[$key].'</span></label>'.$row_mostright;
						}
						else
						{
							$row_mostright = '<label class="stat last" id="last'.$tmp_rcount_old[$key].'x'.$tmp_ccount_old[$key].'"><span class="hits">'.$rrcount[$key].'</span></label>'.$row_mostright;
						}
					$row_mostright = '</li></ul></label></div>'.$row_mostright;
				}

				if ( $oldrow[$key] != $value ) { $new = true; }
	
				$oldrow[$key] = $value;
				$oldkey = $key;
			}
			for ( $i = 0; $i < sizeof($keys); $i++ ) {
				$rcount_old[$keys[$i]] = $tmp_rcount_old[$keys[$i]];
				$ccount_old[$keys[$i]] = $tmp_ccount_old[$keys[$i]];
				$vrcount_old[$keys[$i]] = $vrcount[$keys[$i]];
				$rrcount_old[$keys[$i]] = $rrcount[$keys[$i]];
			}			
			$table_results .= $row_right.$row_left.$row_mostright;
		}
//		$table_results .= '</li></ul></div>';
//		$table_results .= '<label for="STAT'.$rcount_old[$key].'x'.$ccount_old[$key].'">'.$vrcount[$key].'</label>';
//		$table_results .= '</div>';
// probably still wrong for more than three keys: need sequences of row sections...?
// analyse mistake in Name Nachname Land > Muster 1 label stat3x1 should be stat2x1!
	}
	$table_results .= '</div>';
    //compute rate in seconds per result
    if ( sizeof($result) > $_SESSION['exec_forecast_threshold'] ) {
        $_endtime = microtime(true);
        $_SESSION['stat_execution_rate'] = ($_endtime - $_starttime) / sizeof($result);
    }
	return $table_results;
}

function _len(array $checked, int $offset = 0) {
	$_checked_length = 1;
	if ( isset($checked[$offset][0]) ) {
		$_checked_length = sizeof($checked[$offset]);
	} else {
		foreach ( $checked[$offset] as $index=>$value ) {
			$_checked_length = sizeof($value);
			break;
		}
	}
	return $_checked_length;
}

function _extract(array $checked, int $compound, int $item, int $offset = 0) {
	if ( isset($checked[$offset+$compound][0]) ) {
		$_extracted = array($checked[$offset+$compound][$item]);
	} else {
		$_extracted = array();
		foreach ( $checked[$offset+$compound] as $index=>$value ) {
			if ( ! is_array($value) ) { $value = array($value); }
			$_extracted[$index] = array($value[$item]);
		}
	}
	return $_extracted;
}

?>
