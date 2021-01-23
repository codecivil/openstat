function _onResetFilter(val,el) {
	if (el) { document.getElementById("db_"+el).value = "none"; }
// make new query at every change; uncomment to restrict to filter removals
//	if ( val == "none" ) {
		if ( val != "delete" || confirm("Wollen Sie den Eintrag wirklich löschen?") ) {
			console.log(val);
//			document.getElementById('db_options').submit();
		}
//	}
}

//wrong place here, but anyway: maybe we want to disable editing of noupdate@getDetails, noinsert@newEntry and correspondingly call getDetails and newEntry via callPHPfunction (with callback script!)
function _onAction(val,el,fct,div,add,classes,callback,arg) {
	//full argument set (classes,callback,arg) added on 2019-09-02
// make new query at every change; uncomment to restrict to filter removals
//	if ( val == "none" ) {
	if ( val == "pleasechoose" ) { return; }
	if ( val != "delete" || confirm("Wollen Sie den Eintrag wirklich löschen? Davon abhängige Einträge werden ebenfalls gelöscht.") ) {
		var clone_el = el.cloneNode(true);
		switch(val) {
			case 'insert':
				_disableClass(el,'noinsert');
				break;
			case 'edit':
				_disableClass(el,'noupdate');
				break;
		}
		callFunction(el,fct,div,add,classes,callback,arg).then(()=>{
			if ( val == "delete" ) { _close(el); };
			el = clone_el.cloneNode(true);
		});
 	}
//	}
}

//disables all elements of class _className inside el
function _disableClass(el,_className) {
	_elements = el.getElementsByClassName(_className);
	for ( i = 0; i < _elements.length; i++ ) {
		_inputs = _elements[i].getElementsByTagName('input');
		for ( j = 0; j < _inputs.length; j++ ) {
			_inputs[j].disabled = true;
		}
		_selects = _elements[i].getElementsByTagName('select');
		for ( j = 0; j < _selects.length; j++ ) {
			_selects[j].disabled = true;
		}
		_textareas = _elements[i].getElementsByTagName('textarea');
		for ( j = 0; j < _textareas.length; j++ ) {
			_textareas[j].disabled = true;
		}
	}
}

function _addOption(keyname) {
		document.getElementById("db_"+keyname+"_list").disabled = true;
		document.getElementById("db_"+keyname+"_list").setAttribute("hidden", true);
		document.getElementById("db_"+keyname+"_text").disabled = false;
		document.getElementById("db_"+keyname+"_text").removeAttribute("hidden");
//		document.getElementById('db_options').submit();
}

function _toggleOption(keyname) {
	//was == false in next line
	if ( ! document.getElementById("db_"+keyname+"_list") ) { return; }
	var minus = document.getElementById("db_"+keyname+"_list").parentElement.querySelector('.minus');
	if ( minus.disabled ) {
		document.getElementById("db_"+keyname+"_list").disabled = true;
		document.getElementById("db_"+keyname+"_text").disabled = true;
		return;	
	}
	if ( document.getElementById("db_"+keyname+"_list").disabled != true ) {
		document.getElementById("db_"+keyname+"_list").disabled = true;
		document.getElementById("db_"+keyname+"_list").setAttribute("hidden", true);
		document.getElementById("db_"+keyname+"_text").disabled = false;
		document.getElementById("db_"+keyname+"_text").removeAttribute("hidden");
	} else {
		document.getElementById("db_"+keyname+"_list").disabled = false;
		document.getElementById("db_"+keyname+"_list").removeAttribute("hidden");
		document.getElementById("db_"+keyname+"_text").disabled = true;
		document.getElementById("db_"+keyname+"_text").setAttribute("hidden",true);
	}
}

var suggest_length_old = 0; var suggest_length = 0;

function _autoComplete(el,suggest_array,suggest_conditions) {
	suggest_length_old = suggest_length; suggest_length = el.value.length;
	var suggestions = el.parentElement.querySelector('.suggestions');
	for (i=0; i<Object.keys(suggest_array).length; i++) {
		j = Object.keys(suggest_array)[i];
		var suggestion = suggest_array[j]; var sugindex = j;
		if ( suggest_length >= 3 && suggest_array[j] && suggest_array[j].indexOf(el.value) > -1 ) {
			console.log(el);
			if ( ! el.nextElementSibling.querySelector('.sug'+sugindex) ) {
				//create div
				var sugdiv = document.createElement('div');
				sugdiv.textContent = suggestion;
				sugdiv.classList = "sug"+sugindex;
				sugdiv.onmousedown = function () { this.parentElement.previousElementSibling.value = this.textContent; suggest_length_old = suggest_length; suggest_length = this.textContent.length; };
				sugdiv.onmouseover = function () { this.style.background='var(--background-marked)'; }
				sugdiv.onmouseout = function () { this.style.background='var(--background-details)'; }
				el.nextElementSibling.appendChild(sugdiv);
			}
		} else {
			if ( el.nextElementSibling.querySelector('.sug'+sugindex) ) {
				el.nextElementSibling.removeChild(el.nextElementSibling.querySelector('.sug'+sugindex));
			}
		}
	};
}

function _oldautoComplete(el,suggest_array,suggest_conditions) {
	var t = 0;
	suggest_length_old = suggest_length; suggest_length = el.value.length;
	if (suggest_length_old >= suggest_length) { return; }
	for (i=0; i<Object.keys(suggest_array).length; i++) {
		j = Object.keys(suggest_array)[i];
		if (suggest_array[j] && suggest_array[j].indexOf(el.value) > -1) { t++; var p=j; } 
	}
	if (t == 1) { 
		el.value = suggest_array[p];
		suggest_length_old = suggest_length; suggest_length = el.value.length;
	}
}

function updateSelection(el) {
	var id = el.id;
	var option = el.getElementsByTagName('option'); 
	for (j=0; j<option.length; j++) {
		option[j].disabled = false;
	}
	var conditions = JSON.parse(document.getElementById(id+'_conditions').innerText);
	var conditions_met = 0;
	for (i=0; i<conditions.length; i++) {
		var depends_on_key = conditions[i].depends_on_key;
		var depends_on_value = conditions[i].depends_on_value;
		var allowed_values = conditions[i].allowed_values;
		//do not restrict on EXTENSIBLE LISTS: mark in allowed_values with "***"
		if ( allowed_values.indexOf('\"***\"') > -1 ) { continue; }
		//
		if ( el.closest('form').querySelector('[name*="'+depends_on_key+'"]') ) {
			_hits = el.closest('form').querySelectorAll('[name*="'+depends_on_key+'"]');
			for (k=0; k<_hits.length; k++) {
				if ( _hits[k].value == depends_on_value ) {
					conditions_met++;
					for (j=0; j<option.length; j++) {
						var match = allowed_values.indexOf(option[j].value);
						if ( match == -1 ) { option[j].disabled = true; }; 
					}
				}
			}
		}
	}
	console.log(conditions_met);
	if ( conditions_met == 0 ) {
	for (i=0; i<conditions.length; i++) {
		var depends_on_key = conditions[i].depends_on_key;
		var depends_on_value = conditions[i].depends_on_value;
		var allowed_values = conditions[i].allowed_values;
		//do not restrict on EXTENSIBLE LISTS: mark in allowed_values with "***"
		if ( allowed_values.indexOf('\"***\"') > -1 ) { continue; }
		//
		if ( !(depends_on_value) && !(depends_on_key) ) {
			for (j=0; j<option.length; j++) {
				var match = allowed_values.indexOf(option[j].value);
//				console.log(option[j].value+' '+match);
				if ( match == -1 ) { option[j].disabled = true; }
				} 
			}
		}
	}	
}

function addSearchfield(el,rnd) {
	var rndregexp = new RegExp(rnd,'g');
	var rndnew = Math.floor(Math.random() * 32768 );
	var parent = el.closest('div');
	var lastfield = parent.querySelectorAll('div.searchfield')[parent.querySelectorAll('div.searchfield').length - 1];
	var orig = parent.getElementsByClassName('searchfield')[0];
	var _allinputs = orig.querySelectorAll('input,select,textarea');
	_allinputs.forEach(	function(_input) { 
		if ( ! _input.name.endsWith('[]') ) { _input.name += '[]'; }
	});
	var clone = orig.cloneNode(true);
	var _newinputs = clone.querySelectorAll('input,select,textarea');
	_newinputs.forEach(	function(_input) { 
		_input.value = '';
	});
	if ( rnd ) { clone.innerHTML = clone.innerHTML.replace(rndregexp,rndnew); }
//	clone.getElementsByTagName('label')[0].removeAttribute('hidden');
//	parent.appendChild(clone);
	parent.insertBefore(clone,lastfield.nextSibling);
	return false;
}

function _setValue(el,_value,_position) {
	_form = el.closest('div').querySelector('form');
	_form.querySelectorAll('input')[_position].value = _value;
	thecheckbox = _form.closest('tr').querySelector('td').getElementsByTagName('input')[0];
	if ( thecheckbox.checked ) {
		document.getElementById('editTableName').value = _value;
		callFunction(document.getElementById('formMassEdit'),'newEntry','_popup_',false,'details new').then(()=>{ return false; });
	} else {
		setTimeout(function(){callPHPFunction(_form,'newEntry','_popup_','details new').then(()=>{ return false; });},500);
	}
}

function newEntryFromEntry(tablefrom,idfrom,tableto) {
	var formobj = new Object();
	formobj['id_'+tablefrom] = idfrom;
	formobj.table = new Array();
	formobj.table[0] = tableto;
	document.getElementById('trash').value = JSON.stringify(formobj);
	setTimeout(function(){callPHPFunction('_','newEntry','_popup_','details new').then(()=>{ return false; });},500);
}

function _toggleColumn(el,key) {
	var _table = el.closest('table');
	_table.querySelectorAll('.'+key).forEach(
		function(td){
			if ( td.classList.contains('hidecolumn') ) { 
				td.classList.remove('hidecolumn');
				td.previousSibling.classList.add('disabled'); 
			} else { 
				td.classList.add('hidecolumn');
				td.previousSibling.classList.remove('disabled');
			}
		}
	)
}

function _toggleEditAll(form,master) {
	document.querySelectorAll('input[form="'+form+'"]').forEach(function(el){el.checked = document.getElementById(master).checked;});
}

function _toggleEnabled(number) {
	div = document.getElementById('enablable'+number);
	if ( div.classList.contains('disabled') ) { div.classList.remove('disabled'); } else { div.classList.add('disabled'); };
	div.querySelectorAll('input').forEach(function(el){ 
		el.disabled = ! el.disabled;
		//generate the correct initial disabled setting for EXTENSIBLE LIST, LIST THEN SUGGEST...
		if ( el.id.split('_')[1] ) { _toggleOption(el.id.split('_')[1]); _toggleOption(el.id.split('_')[1]); }
	});
	div.querySelectorAll('select').forEach(function(el){ 
		el.disabled = ! el.disabled;
		//generate the correct initial disabled setting for EXTENSIBLE LIST, LIST THEN SUGGEST...
		if ( el.id.split('_')[1] ) { _toggleOption(el.id.split('_')[1]); _toggleOption(el.id.split('_')[1]); }
	});
	div.querySelectorAll('textarea').forEach(function(el){ el.disabled = ! el.disabled; });
}

function _toggleStatColumn(columnnumber,numberofrows) {
	//columnnumber -= 1;
	if ( columnnumber == -1 ) {
		document.getElementById('stat'+0+'x'+0).checked = false;
		document.getElementById('content'+0+"x"+0).checked = false;
		return;
	}
	if ( columnnumber == 0 ) {
		document.getElementById('stat'+0+'x'+0).checked = false;
		document.getElementById('content'+0+"x"+0).checked = ! document.getElementById('content0x0').checked;
		if ( document.getElementById('content'+0+"x"+0).checked ) { statnumber = columnnumber+1; } else { statnumber = columnnumber; };
		for ( var j = 1; j < numberofrows+1; j++ ) {
			if ( document.getElementById('stat'+j+"x"+1) ) { 
				document.getElementById('stat'+j+'x'+1).checked = false;
				document.getElementById('content'+j+"x"+1).checked = false;
			}
		}
		return;
	}
	document.getElementById('stat'+0+'x'+0).checked = false;
	document.getElementById('content'+0+"x"+0).checked = true;
	for ( var i = 1; i < columnnumber; i++ ) { 
		_var = function (i) {
			for ( var j = 1; j < numberofrows+1; j++ ) {
				if ( document.getElementById('stat'+j+"x"+i) ) {
					document.getElementById('stat'+j+'x'+i).checked = false;
					document.getElementById('content'+j+"x"+i).checked = true;
				} }
		} (i);
	}
	_state = ! document.getElementById('content'+1+"x"+columnnumber).checked;
	if ( _state ) { statnumber = columnnumber+1; } else { statnumber = columnnumber; };
	for ( var j = 1; j < numberofrows+1; j++ ) {
		if ( document.getElementById('stat'+j+"x"+columnnumber) ) { 
			document.getElementById('stat'+j+'x'+columnnumber).checked = false;
			document.getElementById('content'+j+"x"+columnnumber).checked = _state;
		}
	}
	_final = columnnumber+1;
	for ( var j = 1; j < numberofrows+1; j++ ) {
		if ( document.getElementById('stat'+j+"x"+_final) ) { 
			document.getElementById('stat'+j+'x'+_final).checked = false;
			document.getElementById('content'+j+"x"+_final).checked = false;
		}
	}
	var statObj = new Object();
	var statArray = _scrapeStat(statnumber);
	statObj.array = statArray;
	statObj.cnumber = statnumber;
	generateDistribution('bar','hits',statObj,document.getElementById('statGraphicsBarChartHits'));
	generateDistribution('pie','hits',statObj,document.getElementById('statGraphicsPieChartHits'));
	generateDistribution('bar','unique',statObj,document.getElementById('statGraphicsBarChartUnique'));
	generateDistribution('pie','unique',statObj,document.getElementById('statGraphicsPieChartUnique'));
}

function _toggleGraphs(el,_type) {
	var _states = new Array('hits','unique','none');
	var _statGraphics = document.getElementById('statGraphics');
	var _classList = el.classList;
	var _foundClass = false;
	_states.forEach(function(_status,_index,_array) {
		if ( _classList.contains(_status) && ! _foundClass ) {
			var _nextindex = ( _index + 1 ) % 3;
			_classList.remove(_status);
			_hideStat(_status+'.'+_type);
			_classList.add(_array[_nextindex]);
			_unhideStat(_array[_nextindex]+'.'+_type);
			_foundClass = true;
		}	
	});
}


function _hideStat(property) {
	statGraphics = document.getElementById('statGraphics');
	statGraphics.querySelectorAll('.'+property).forEach(function(el){el.style.visibility = 'hidden';});
	statGraphics.querySelectorAll('.'+property).forEach(function(el){el.hidden = true;});
}	

function _unhideStat(property) {
	statGraphics = document.getElementById('statGraphics');
	statGraphics.querySelectorAll('.'+property).forEach(function(el){el.style.visibility = '';});
	statGraphics.querySelectorAll('.'+property).forEach(function(el){el.hidden = false;});
}	

function _toggleStat(property) {
	statGraphics = document.getElementById('statGraphics');
	if ( statGraphics.querySelector('.'+property).style.visibility == '' ) {
		statGraphics.querySelectorAll('.'+property).forEach(function(el){el.style.visibility = 'hidden';});
		statGraphics.querySelectorAll('.'+property).forEach(function(el){el.hidden = true;});
	} else {
		statGraphics.querySelectorAll('.'+property).forEach(function(el){el.style.visibility = '';});
		statGraphics.querySelectorAll('.'+property).forEach(function(el){el.hidden = false;});
	}
}	


function editEntries(form,tablename) {
	thecheckbox = form.closest('tr').querySelector('td').getElementsByTagName('input')[0];
	if ( thecheckbox.checked ) {
		document.getElementById('editTableName').value = tablename;
		callFunction(document.getElementById('formMassEdit'),'getDetails','_popup_',false,'details').then(()=>{ return false; });
	} else {
		callFunction(form,'getDetails','_popup_',false,'details').then(()=>{ return false; });
	}
	return false
}

function _scrapeStat(chosencolumn) {
	//get all numbers in maximal open column number
	_statArray = new Array();
	document.querySelectorAll('label.stat.last').forEach(function(el){
		_string = el.id;
		_column = Number(_string.substring(_string.indexOf('x')+1));
		if ( _column == chosencolumn ) {
			_row = Number(_string.substring(4,_string.indexOf('x')));
			_obj = new Object;
			_obj.row = _row; _obj.column = _column;
			_obj.hits = Number(el.querySelector('span.hits').innerText);
			_statArray.push(_obj);
		}
	});
	document.querySelectorAll('label[for^="stat"]').forEach(function(el){
		_string = el.getAttribute("for");
		_column = Number(_string.substring(_string.indexOf('x')+1));
		if ( _column == chosencolumn ) {
			_row = Number(_string.substring(4,_string.indexOf('x')));
			_obj = new Object;
			_obj.row = _row; _obj.column = _column;
			_obj.hits = Number(el.querySelector('span.hits').innerText);
			_obj.unique = Number(el.querySelector('span.unique').innerText);
			_statArray.push(_obj);
		}
	});
	return _statArray;
}

//type in { 'hits', 'unique'}
function generateDistribution(graphtype,type,statObj,element) {
	var statArray = statObj.array;
	statArray.sort((a,b)=> b[type] - a[type]);
	y_max = statArray[0][type]; y_min = statArray[statArray.length-1][type];
	x_min = 0; x_max = statArray.length;
	y_total = 0; y2_total = 0; y_max = 0;
	statArray.forEach(function(_obj) {
		y_total += _obj[type];
		y2_total += _obj[type]*_obj[type];
		y_max = Math.max(y_max,_obj[type]);
	});
	//statistical data:
	_statData = new Object;
	_statData.median = ( statArray.length == 2*Math.floor(statArray.length/2) ) ? 0.5*(statArray[statArray.length/2-1][type]+statArray[statArray.length/2][type]) : statArray[Math.floor(statArray.length/2)][type];
	console.log(_statData.median);
	_statData.mean = y_total/statArray.length;
	_statData.sd = Math.sqrt(y2_total/statArray.length - _statData.mean*_statData.mean);
	(new Array('median','mean','sd')).forEach(function(statdatum) {
		_statData[statdatum] = Math.floor(100*_statData[statdatum]+0.5)/100;
	});
	//
	statObj.array = statArray;
	statObj.statdata = _statData;
	showGraph(graphtype,y_total,type,statObj,element);
}

function generateFrequencyDistribution(type,statArray) {
	_distr = new Array();
	_total_classes = statArray.length;
	_total_hits = 0;
	statArray.forEach(function(_obj) {
		_total_hits += _obj[type];
		if ( ! _distr[_obj[type]] ) { _distr[_obj[type]] = 1; } else { _distr[_obj[type]]++; }
	});
	_distr_min = Math.min(..._distr); _distr_max = Math.max(..._distr);
}

function showGraph(graphtype,y_total,type,statObj,element) {
	switch(graphtype) {
		case 'bar': showBarGraph(y_max,y_total,type,statObj,element); break;;
		case 'pie': showPieGraph(y_total,type,statObj,element); break;;
	}	
	return;
}

//to be considered
//function showBarGraph(x_min,x_max,y_min,y_max,y_total,type,statArray,element) {
//statArray must be already sorted
function showBarGraph(y_max,y_total,type,statObj,element) {
	var statArray = statObj.array;
	var statData = statObj.statdata;
	var statNumber = statObj.cnumber;
	y_top = Math.max(y_max+1,statData.mean+2*statData.sd);
	var _RangeWidth = 24+Math.floor(Math.log(y_total)/Math.log(10))*12;
	el_innerhtml = '<table>';
	if ( statData.median ) {
		el_innerhtml += '<tr onclick="_toggleStat(\'median\');"><th title="Zeigt den Median">Median</th><td>'+statData.median+'</td></tr>';
	}
	if ( statData.mean ) {
		el_innerhtml += '<tr onclick="_toggleStat(\'mean\');"><th title="Zeigt den Mittelwert">Mittelwert</th><td>'+statData.mean+'</td></tr>';
	}
	if ( statData.mean ) {
		el_innerhtml += '<tr onclick="_toggleStat(\'sd\');"><th title="Zeigt die 1- und 2-SD-Intervalle um den Mittelwert">Standardabweichung</th><td>'+statData.sd+'</td></tr>';
	}
	el_innerhtml += '</table><div id="rangeBottom" hidden></div><div id="rangeTop" hidden></div>';
	/*if ( statArray.length > 20 ) {
		statArray = statArray.slice(0,19);
		el_innerhtml = '<h2>Top 20</h2>'
	} */
	_width = 12*statArray.length;
//	_width_em = (statArray.length+_RangeWidth/12) * 2;
	_width_em = (_width+_RangeWidth+24)/7;
	el_innerhtml += '<svg id="barGraph" width="'+_width_em+'em" height="20em" viewBox="-'+(_RangeWidth+12)+' 0 '+(_width+_RangeWidth+24)+' 120">';
	el_innerhtml += '<style>.bar { font-size: 0.5em; }</style>'
	el_innerhtml += '<style>.unique { fill: var(--color-unique); }</style>'
	el_innerhtml += '<style>.hits { fill: var(--color-hits); }</style>'
	el_innerhtml += '<rect id="statGraphics_Range" x=-'+(_RangeWidth+10)+' y=0 width="'+(_RangeWidth-2)+'" height="100" fill="var(--background-filters)" opacity="0.2" stroke="#000" />'
	el_innerhtml += '<text id="statGraphics_RangeTopValue" x=-'+(_RangeWidth/2+12)+' y=0 text-anchor="middle" fill="var(--color-'+type+')" class="bar">'+y_top+'</text>'
	el_innerhtml += '<text id="statGraphics_RangeBottomValue" x=-'+(_RangeWidth/2+12)+' y=100 text-anchor="middle" fill="var(--color-'+type+')" class="bar">0</text>'
	el_innerhtml += '<text id="statGraphics_RangeInValue" x=-'+(_RangeWidth/2+12)+' y=50 text-anchor="middle" fill="var(--color-'+type+')" class="bar"><tspan class="hits">'+y_total+'</tspan>|<tspan class="unique">'+statArray.length+'</tspan></text>'
	el_innerhtml += '<rect id="statGraphics_chooseRange" x=-'+(_RangeWidth+10)+' width="'+(_RangeWidth-2)+'" y=0  height="100" fill="transparent" stroke="#000" onmousedown="selectRange(event,this.parentElement,'+y_max+','+y_total+',\''+type+'\',\''+encodeURI(JSON.stringify(statObj))+'\');" />'
	statArray = addStatGraphicsLabels(statArray,statNumber);
	statArray.forEach(function(el,index,array){
		el_x = 12*index;
		el_height = 100*el[type]/y_top; el_y = 100 - el_height; text_y = el_y - 2;
		el_innerhtml += '<rect x="'+el_x+'" y="'+el_y+'" width="10" height="'+el_height+'" fill="var(--background-filters)" onclick="console.log(\'klack\');"><title>'+el.title+'</title></rect>';
		el_innerhtml += '<text x="'+el_x+'" y="'+text_y+'" class="bar" fill="var(--color-'+type+')">'+el[type]+'</text>';	
		el_innerhtml += '<text x="'+(el_x+6)+'" y="113" class="bar" transform="rotate(-45 '+el_x+' 113)" fill="var(--color-'+type+')">'+el.label+'</text>';	
	});
	if ( statData.median ) {
		el_median = 100-100*statData.median/y_top;
		el_innerhtml += '<path d="M0 '+el_median+' '+_width+' '+el_median+'Z" stroke="var(--background-addfilters)" stroke-width="1" class="median"><title>Median '+statData.median+'</title></path>'; 
	}
	if ( statData.mean ) {
		el_mean = 100-100*statData.mean/y_top;
		el_innerhtml += '<path d="M0 '+el_mean+' '+_width+' '+el_mean+'Z" stroke="var(--background-tables)" stroke-width="1" class="mean"><title>Mittelwert '+statData.mean+'</title></path>'; 
	}
	if ( statData.sd ) {
		el_meanplussd = 100-100*(statData.mean+statData.sd)/y_top;
		el_meanplus2sd = 100-100*(statData.mean+2*statData.sd)/y_top;
		el_meanminussd = 100-100*(statData.mean-statData.sd)/y_top;
		el_height = el_meanminussd - el_meanplussd;
		el_innerhtml += '<rect x="0" y="'+el_meanplus2sd+'" width="'+_width+'" height="'+(2*el_height)+'" fill="var(--background-tables)" opacity="0.25" class="sd"><title>Intervall 2 SD um Mittelwert ('+(2*statData.sd)+')</title></rect>';
		el_innerhtml += '<rect x="0" y="'+el_meanplussd+'" width="'+_width+'" height="'+el_height+'" fill="var(--background-tables)" opacity="0.5" class="sd"><title>Intervall 1 SD um Mittelwert ('+statData.sd+')</title></rect>'; 
	}
	el_innerhtml += "</svg>";
	element.innerHTML = el_innerhtml;
	element.querySelectorAll('.median,.mean,.sd').forEach(function(el){el.style.visibility = 'hidden';});	
}

function showPieGraph(y_total,type,statObj,element) {
	var statArray = statObj.array;
	var statData = statObj.statdata;
	var statNumber = statObj.cnumber;
	el_innerhtml = '';
	if ( statArray.length > 10 ) {
		statArray = statArray.slice(0,9);
		el_innerhtml = '<h2>Top 10</h2>'
	}
	_angle_old = 0;
	_angle_text_old = 0;
	el_innerhtml += '<svg width="20em" height="20em" viewBox="0 0 240 240">';
	el_innerhtml += '<style>.pie { font-size: 0.8em; }</style>';
//	el_innerhtml += '<style>.median,.mean,.sd { visibility: hidden; }</style>'
	el_innerhtml += '<g transform="translate(120,120)" stroke="#000" stroke-width="1">'
	statArray = addStatGraphicsLabels(statArray,statNumber);
	statArray.forEach(function(el,index,array){
		el_percent = el[type]/y_total;
		el_angle = 2*Math.PI*el_percent;
		//check the svg arc flags on consistency;
		_largeArcFlag = 1;
		if ( el_angle > Math.PI ) { _sweepFlag = 1; } else { _sweepFlag = 0; }
		_angle_new = _angle_old + el_angle; 
		vector_old = new Array(100*Math.sin(_angle_old),-100*Math.cos(_angle_old));
		vector_new = new Array(100*Math.sin(_angle_new),-100*Math.cos(_angle_new));
		vector_el_text = new Array(110*Math.sin(0.5*(_angle_new+_angle_old)),-110*Math.cos(0.5*(_angle_new+_angle_old)));
		vector_el_percent = new Array(0.5*(vector_el_text[0]),0.5*(vector_el_text[1]));
		angle_el_text_sin = 360/(2*Math.PI)*Math.asin(vector_el_text[0]/Math.sqrt(vector_el_text[0]*vector_el_text[0]+vector_el_text[1]*vector_el_text[1]));
		angle_el_text = 360/(2*Math.PI)*Math.atan(-vector_el_text[0]/vector_el_text[1]);
		if ( angle_el_text_sin != angle_el_text ) { angle_el_text -= 180; }
//		console.log(_angle_text_old+','+angle_el_text);
//		while ( angle_el_text < _angle_text_old ) { angle_el_text += 180; }
		_angle_text_old = angle_el_text;
		angle_el_percent = angle_el_text - 90;
		el_innerhtml += '<path d="M0 0 '+vector_old[0]+' '+vector_old[1]+'A100 100 0 '+_sweepFlag+' '+_largeArcFlag+' '+vector_new[0]+' '+vector_new[1]+'Z" fill="var(--background-filters)" opacity="'+(statArray.length-index)/statArray.length+'" onclick="console.log(\'klack\');"><title>'+el.title+'</title></path>';
		el_innerhtml += '<path d="M0 0 '+vector_old[0]+' '+vector_old[1]+'A100 100 0 '+_sweepFlag+' '+_largeArcFlag+' '+vector_new[0]+' '+vector_new[1]+'Z" fill="transparent" onclick="console.log(\'klack\');"><title>'+el.title+'</title></path>';
		el_innerhtml += '<text  stroke="var(--color-'+type+')" fill="var(--color-'+type+')" x="'+vector_el_percent[0]+'" y="'+vector_el_percent[1]+'" class="pie" transform="rotate('+angle_el_percent+' '+vector_el_percent[0]+' '+vector_el_percent[1]+')">'+Math.floor(100*el_percent+0.5)+'%</text>';	
		el_innerhtml += '<text  stroke="var(--color-'+type+')" fill="var(--color-'+type+')" x="'+vector_el_text[0]+'" y="'+vector_el_text[1]+'" class="pie" transform="rotate('+angle_el_text+' '+vector_el_text[0]+' '+vector_el_text[1]+')">'+el.label+'</text>';	
		_angle_old = _angle_new;
	});
	el_innerhtml += "</g></svg>";
	element.innerHTML = el_innerhtml;
}


function addStatGraphicsLabels(statArray,statNumber) {
	console.log('statNumber: '+statNumber);
	statArray.forEach(function(_obj){
		// test since 20200528
		var obj_el = document.querySelector('label[for^="stat'+_obj.row+'x'+_obj.column+'"]');
		if ( ! obj_el ) {
			console.log("last"+_obj.row+"x"+_obj.column);
			var obj_el = document.getElementById("last"+_obj.row+"x"+_obj.column);
		}
		var cont_el = new Array(obj_el);
		// \test
		var el_title = '';
		for (var i = 0; i <= statNumber; i++) {
//			el_title += document.getElementsByClassName('resultTable')[0].querySelectorAll('tr')[_obj.row].querySelectorAll('td')[2*i].innerText + ' ';
		// test since 20200528
			try { 
				cont_el[i+1] = cont_el[i].closest('div.nextlevel').closest('li').querySelector('.value');
				el_title = cont_el[i+1].innerText + ' ' +  el_title; 
			} catch(err) {
				console.log();
			}
		// \test
		}
/*		var el_title = encodeURI(document.getElementsByClassName('resultTable')[0].querySelectorAll('tr')[_obj.row].innerText);
		el_title = el_title.replace(/%09|%A0|%C2/g,' '); //remove characters separating td's in innerText
		el_title = decodeURI(el_title); */
		el_title = el_title.replace(/\t/g,' ').trim();
		var el_oldtitle = '';
		while ( el_oldtitle != el_title ) {
			el_oldtitle = el_title;
			el_title = el_title.replace(/  /g,' ');
		}
		var el_oldlabel = '';
		// test since 20200528
		var el_label = obj_el.closest('div.nextlevel').closest('li').querySelector('.value').innerText;
		// \test
//		var el_label = document.getElementsByClassName('resultTable')[0].querySelectorAll('tr')[_obj.row].querySelectorAll('td')[2*statNumber].innerText;
		while ( el_oldlabel != el_label ) {
			el_oldlabel = el_label;
			el_label = el_label.replace(/  /g,' ');
		}
		_obj.title = el_title;
		// look for most identifying parts of el_label
		var el_label_array = el_label.split(" ");
		if ( el_label_array.length > 1 ) {
			_obj.label = '';
			el_label_array.forEach (function(labelpart) {
				_obj.label += labelpart.substr(0,1);
			});
		} else {
			if ( el_label.match(/[0-9]*$/)[0] != '' ) {
				_obj.label = el_label.substr(0,1)+ el_label.match(/[0-9]*$/)[0];
			} else {
				_obj.label = el_label.substr(0,2);
			}
		}
//		_obj.label = el_label.substr(0,2);
	});
	return statArray;
}

function selectRange(event,svg,y_max,y_total,type,statArrayString) {
	var statObj = JSON.parse(decodeURI(statArrayString));
	var statArray = statObj.array;
	var statData = statObj.statdata;
	var sd = statObj.statdata.sd;
	var y_top = Math.max(y_max+1,statData.mean+2*statData.sd);
	//var y_top = Math.max(y_max,Math.floor(statObj.statdata.mean+2*statObj.statdata.sd+0.99));
	var chooseRange = svg.getElementById('statGraphics_chooseRange');
	var _Range = svg.getElementById('statGraphics_Range');
	var _RangeTop = svg.getElementById('statGraphics_RangeTopValue');
	var _RangeBottom = svg.getElementById('statGraphics_RangeBottomValue');
	var _RangeIn = svg.getElementById('statGraphics_RangeInValue');
//	svg.outerHTML = null;
		//the following is from Frogz's accepted answer to 'Mouse position inside autoscaled SVG' on stackoverlow
	// Find your root SVG element
	//var svg = element.querySelector('svg');

	// Create an SVGPoint for future math
	var pt = svg.createSVGPoint();

	// Get point in global SVG space
	function cursorPoint(evt){
	  pt.x = evt.clientX; pt.y = evt.clientY;
	  return pt.matrixTransform(svg.getScreenCTM().inverse());
	}
	
	//y-value to database value
	function y2v(y,vmax) {
		return vmax*(1-0.01*y)
	}
	
	//database value to y
	function v2y(v,vmax) {
		return 100*(1-v/vmax)
	}
	
	function yOfIntv(y,vmax) {
		return v2y(Math.floor(y2v(y,vmax)+0.5),vmax);
	}
	
	function _moveRange(evt){
		_RangeIn.setAttribute('visibility','hidden');
		var loc = cursorPoint(evt);
		var yint = yOfIntv(loc.y,y_top);
		var vint = Math.floor(y2v(loc.y,y_top)+0.5);
		// Use loc.x and loc.y here
		console.log(loc.y+','+y2v(loc.y,y_top)+','+vint);
		//decide whether top or bottom is moved; first option is top, then bottom
		if ( loc.y < parseFloat(_Range.getAttribute('y'))+0.6*parseFloat(_Range.getAttribute('height')) ) {
			_Range.setAttribute('height',parseFloat(_Range.getAttribute('height'))+parseFloat(_Range.getAttribute('y'))-yint+0.1);
			_Range.setAttribute('y',yint-0.1);
			_RangeTop.setAttribute('y',yint-0.1);
			_RangeTop.innerHTML = vint;
		} else {
			_Range.setAttribute('height',yint-parseFloat(_Range.getAttribute('y')));
			_RangeBottom.setAttribute('y',yint);
			_RangeBottom.innerHTML = vint;
		}
	}
		
	function _calculateInRange() {
		var _RangeArray = statArray.filter(entry=> (entry[type] >= parseFloat(_RangeBottom.innerHTML) && entry[type] <= parseFloat(_RangeTop.innerHTML) ));
		var _RangeSum = 0
		_RangeArray.forEach(function(entry){_RangeSum += entry[type]; });
		_RangeIn.innerHTML = '<tspan class="hits">'+_RangeSum+'</tspan>|<tspan class="unique">'+_RangeArray.length+'</tspan>';	
		_RangeIn.setAttribute('y',parseFloat(_Range.getAttribute('y'))+0.5*parseFloat(_Range.getAttribute('height')));
		if ( parseFloat(_Range.getAttribute('height')) < 5 ) { _RangeIn.setAttribute('y',parseFloat(_RangeIn.getAttribute('y'))+10); }
	}

	chooseRange.addEventListener('mousemove',_moveRange,false);
	svg.addEventListener('mouseup',function(evt){
		chooseRange.removeEventListener('mousemove',_moveRange,false);
		_calculateInRange();
		_RangeIn.setAttribute('visibility','visible');
	},false);
	_moveRange(event);
}
//function showDiscGraph(...) {}
//function show...(...) {}
//function show...(...) {}

//try {
//	if (document.getElementById('message').innerHTML != "") { document.getElementById('db_options').style.display = "none"; }
//} catch (err) { console.log(err); }

function exportCSV() { return false; }

function changeUserName(form,arg,_response) {
	document.getElementById('userName').value = _response;
/*	if ( _response.indexOf('false') > -1 ) {
		document.getElementById('userName').classList.add("error");
		setTimeout(function(){document.getElementById('userName').value = _response;},3000);
	} else {
		document.getElementById('userName').classList.remove("error") ;
	}
*/
}

