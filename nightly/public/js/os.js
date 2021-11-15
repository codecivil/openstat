function _onResetFilter(val,el) {
	if (el) { document.getElementById("db_"+el).value = "none"; }
// make new query at every change; uncomment to restrict to filter removals
//	if ( val == "none" ) {
		if ( val != "delete" || confirm("Wollen Sie den Eintrag wirklich löschen?") ) {
			//console.log(val);
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
		//update html "selected" attribute before cloning
//		el.querySelectorAll('option').forEach(function(opt){if ( opt.selected ) {opt.setAttribute('selected','');} else { opt.removeAttribute('selected'); };});
		//
//		var clone_el = el.cloneNode(true);
		//clone included iframes
//		var _iframes = new Object();
//		el.querySelectorAll('iframe').forEach(function(_iframe){_iframes[_iframe.id] = document.importNode(_iframe.contentWindow.document.body,true).outerHTML;});
		var _before = new Object();
		switch(val) {
			case 'insert':
				_before = _disableClass(el,'noinsert');
				break;
			case 'edit':
				_before = _disableClass(el,'noupdate');
				break;
		}
		callFunction(el,fct,div,add,classes,callback,arg).then(()=>{
			//close entry on delete:
			if ( val == "delete" ) { _close(el); };
			//close entry window and open newly created entry if insert was successful:
			if ( val == "insert" && el.closest('.section').querySelector('.message') && el.closest('.section').querySelector('.message').textContent.indexOf('Eintrag wurde neu hinzugefügt') > -1 ) {
				var insert_id = el.closest('.section').querySelector('.insertID').textContent;
				var formobj = new Object();
				formobj.massEdit = JSON.parse(insert_id);
				document.getElementById('trash').value = JSON.stringify(formobj);
				setTimeout(function(){callFunction('_','getDetails','_popup_',false,'details','updateSelectionsOfThis').then(()=>{ _close(el); return false; });},500);
			}
//this just changes el to a node outside the document; purpose was to revert the _disable above; solution below?
//			el = clone_el.cloneNode(true);	

//			el.parentElement.replaceChild(clone_el,el);
			//does not yet work... to be continued
//			el.querySelectorAll('iframe').forEach(function(_iframe){console.log(_iframes[_iframe.id]);_iframe.srcdoc = 'data:text/html;charset=utf-8,' + encodeURI(_iframes[_iframe.id]); console.log('iframe.contentWindow =', _iframe.contentWindow);});		
//			el = clone_el;
			for (const [_key, _value] of Object.entries(_before)) {
				document.getElementById(_key).disabled = _value;
			}
		});
 	}
//	}
}

//disables all elements of class _className inside el
function _disableClass(el,_className) {
	var _before = new Object();
	_elements = el.getElementsByClassName(_className);
	for ( i = 0; i < _elements.length; i++ ) {
		_inputs = _elements[i].getElementsByTagName('input');
		for ( j = 0; j < _inputs.length; j++ ) {
			_before[_inputs[j].id] = _inputs[j].disabled;
			_inputs[j].disabled = true;
		}
		_selects = _elements[i].getElementsByTagName('select');
		for ( j = 0; j < _selects.length; j++ ) {
			_before[_selects[j].id] = _selects[j].disabled;
			_selects[j].disabled = true;
		}
		_textareas = _elements[i].getElementsByTagName('textarea');
		for ( j = 0; j < _textareas.length; j++ ) {
			_before[_textareas[j].id] = _textareas[j].disabled;
			_textareas[j].disabled = true;
		}
	}
	return _before;
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

//to be continued: how to deal with conditionless CALENDARS (like EXTENSIBLE LISTS, but with restrictibility!)
function updateSelection(el) {
	var id = el.id;
	var option = el.getElementsByTagName('option');
	var _disabled = new Array(); //the state for checkboxes: activate all options matching a checked checkbox
	for (j=0; j<option.length; j++) {
		option[j].disabled = false;
		_disabled[j] = true;
	}
	var conditions = JSON.parse(document.getElementById(id+'_conditions').innerText);
	var conditions_met = 0;
	var _show_matched = false;
	var _hide_matched = false;
	for (i=0; i<conditions.length; i++) {
		var depends_on_key = conditions[i].depends_on_key.split(';')[0];
		var depends_local = conditions[i].depends_on_key.split(';')[1];
		if ( typeof depends_local == 'undefined' ) { depends_local = ''; }
		var depends_on_value = conditions[i].depends_on_value;
		var allowed_values = conditions[i].allowed_values;
		if ( allowed_values.indexOf('\"***\"') > -1 ) { continue; }
		//
		if ( depends_local != '' ) { var search_el = el.closest('.searchfield'); } else { var search_el = el.closest('form'); }
		if ( search_el.querySelector('[name*="'+depends_on_key+'"]') ) {
			_hits = search_el.querySelectorAll('[name*="'+depends_on_key+'"]');
			//restrict successively for almost all fields
			if ( _hits[0].type != "checkbox" ) {
				for (k=0; k<_hits.length; k++) {
					if ( _hits[k].value == depends_on_value ) {
						conditions_met++;
						//to be continued: parse _SHOW_, _HIDE_ as allowed_values; there are no "options" for these values; only works if non-empty conditions are set and met
						//different parsing: show if there is no _HIDE_ or at least one _SHOW_, hide if there is at least one _HIDE_ and no _SHOW_ (matching)
						//do not restrict on EXTENSIBLE LISTS: mark in allowed_values with "***"
						if ( allowed_values.indexOf('_SHOW_') > -1 ) { _show_matched = true; }
						if ( allowed_values.indexOf('_HIDE_') > -1 ) { _hide_matched = true; }
						for (j=0; j<option.length; j++) {
							var match = allowed_values.indexOf(option[j].value);
							if ( match == -1 ) { option[j].disabled = true; }; 
						}
					}
				}
			} else {
			//do the opposite for checkboxes
			//here is sth wrong: to be continued
				_hits = search_el.querySelectorAll(':checked[name*="'+depends_on_key+'"]');
				for (j=0; j<option.length; j++) {
					for (k=0; k<_hits.length; k++) {
						if ( _hits[k].value == depends_on_value ) {
							conditions_met++;
							if ( allowed_values.indexOf('_SHOW_') > -1 ) { _show_matched = true; }
							if ( allowed_values.indexOf('_HIDE_') > -1 ) { _hide_matched = true; }
							var match = allowed_values.indexOf(option[j].value);
							if ( match > -1 ) { _disabled[j] = false; }; 
						}
					}
					option[j].disabled = _disabled[j];
				}
			}
		}
	}
	if ( conditions_met == 0 ) {
		for (i=0; i<conditions.length; i++) {
			var depends_on_key = conditions[i].depends_on_key.split(';')[0];
			try { var depends_local = conditions[i].depends_on_key.split(';')[1]; } catch(err) { var depends_local = ''; }
			var depends_on_value = conditions[i].depends_on_value;
			var allowed_values = conditions[i].allowed_values;
			//do not restrict on EXTENSIBLE LISTS: mark in allowed_values with "***"
			if ( allowed_values.indexOf('\"***\"') > -1 ) { continue; }
			//
			if ( !(depends_on_value) && !(depends_on_key) ) {
				if ( allowed_values.indexOf('_SHOW_') > -1 ) { _show_matched = true; }
				if ( allowed_values.indexOf('_HIDE_') > -1 ) { _hide_matched = true; }
				for (j=0; j<option.length; j++) {
					var match = allowed_values.indexOf(option[j].value);
					if ( match == -1 ) { option[j].disabled = true; }
				} 
			}
		}
	}
	if ( el.querySelectorAll('option:checked:disabled').length > 0 ) {
		el.querySelectorAll('option:checked:disabled')[0].selected = false;
	}
	//_SHOW_ and _HIDE_ now acting:
	let _label = el.parentElement.querySelector('[for="'+el.id+'"]');
	if ( _hide_matched && ! _show_matched ) {
		el.style.opacity = 0;
		_label.style.opacity = 0;
		setTimeout(function(){
			el.hidden = true; el.disabled = true;
			el.parentElement.querySelector('[for="'+el.id+'"]').hidden = true;		
			}, 700);
	} else {
		// is this compatible with mass editing or non-editing permissions?
		el.hidden = false; el.disabled = false;
		el.parentElement.querySelector('[for="'+el.id+'"]').hidden = false;
		setTimeout(function(){
			el.style.opacity = 1;	
			_label.style.opacity = 1;	
			},100)
	}
	//now only missing: autoupdateSelection at opening (getDetails)
}

//update selection of all class members of array of classes given by json_string
function updateSelectionOfClasses(el) {
	dependency_divs = el.parentElement.querySelectorAll('.dependencies');
	for ( let dependency_div of dependency_divs ) {
		json_string = dependency_div.textContent;
		var _classes = JSON.parse(json_string);
		_classes.forEach(function(_class) {
			_classMembers = document.getElementsByClassName(_class);
			for ( let member of _classMembers ) {
				//update the selection
				updateSelection(member);
				//fire the change event (which otherwise would not fire)
				ev = document.createEvent('Event');
				ev.initEvent('change', true, false);
				member.dispatchEvent(ev);
			}
		});
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
		setTimeout(function(){callPHPFunction(_form,'newEntry','_popup_','details new');},500);
	}
}

//problem here: the inputid classes covers only already existent attributions, so we have to create the input field if not existent...
function _setIdCal(el) {
	let form = el.closest('form');
	if ( ! form.querySelector('.inputid[name="id_os_calendars"]') ) { return; }
	form.querySelector('.inputid[name="id_os_calendars"]').value = el.value;
}

function newEntryFromEntry(el,tableto) {
	var formobj = JSON.parse(el.parentElement.parentElement.querySelector('.attribution').innerText);
	console.log(formobj);
	formobj.table = new Array();
	formobj.table[0] = tableto;
	document.getElementById('trash').value = JSON.stringify(formobj);
	setTimeout(function(){callPHPFunction('_','newEntry','_popup_','details new');},500);
}

function _form2obj(form) {
	var formData = new FormData (form);
	var obj = new Object();
	for (var key of formData.keys()) {
		obj[key] = formData.getAll(key);
	}
	return obj;
}

//newEntry also applies to opening existent entries!; distinguished in function...
//how to distiguis new login: do not ask for every open item...
function newEntry(form,arg,response) {
	var key = new Object;
	var el = document.querySelector('.popup.details');
	// old entries:
	if ( document.querySelector('.new') ) {
		key._table_ = el.querySelector('.inputtable').value;
		key._id_ = 'new';
	} else {
		if ( el.querySelector('._table_') ) {
			key._table_ = el.querySelector('._table_').innerText;
		}
		if ( el.querySelector('._id_') ) {
			key._id_ = el.querySelector('._id_').innerText;
		}
	}
	var _form = el.querySelector('.db_options');
	if ( sessionStorage.getItem(JSON.stringify(key)) != null && sessionStorage.getItem(JSON.stringify(key)) !== JSON.stringify(_form2obj(_form)) ) {
		if( window.confirm('Es gibt eine ungespeicherte Version dieses Eintrags. Wollen Sie sie wiederherstellen?') ) {
			var obj = JSON.parse(sessionStorage.getItem(JSON.stringify(key)));
			for (var k in obj) {
				try { var _fields = el.querySelectorAll(':not(:disabled)[name="'+k+'"]'); } catch(err) { var _fields = new Array(); }
				//add searchfields for multiple values...
				if ( _fields.length > 0) {
					var _rnd = _fields[0].id
					_fields[0].classList.forEach ( function(cl) {
						_rnd = _rnd.replace(cl,'');
					})
					_rnd = _rnd.replace(/[^\d]*/g,'');
					for ( var j = 0; j < obj[k].length-_fields.length; j++ ) {
						addSearchfield(_fields[0].parentElement.parentElement,_rnd);
					}
				}
				_fields = el.querySelectorAll(':not(:disabled)[name="'+k+'"]');
				_fields.forEach( function (_field) {
					if ( _field.id.endsWith('_list') ) {
						var _keyname = _field.id.replace('db_','').replace('_list','');
						_toggleOption(_keyname);
					}
				});
				_fields = el.querySelectorAll(':not(:disabled)[name="'+k+'"]');
				for (var i = 0; i < _fields.length; i++) {						
					try { _fields[i].value = obj[k][i]; } catch(err) { continue; }
				}
			}
		}
		sessionStorage.removeItem(JSON.stringify(key));
	}
}

function _saveState() {
	tinyMCEinit();
	try { tinyMCEinit(); tinyMCE.triggerSave(); } catch(err) { console.log('No TinyMCE'); }
	document.querySelectorAll('.details').forEach(
	function(_el) {
		var key = new Object;
		if ( _el.classList.contains('new') ) {
			try { 
				key._table_ = _el.querySelector('.inputtable').value;
				key._id_ = 'new';
			} catch(err) { return; }
		} else {
			try {
				key._table_ = _el.querySelector('._table_').innerText;
				key._id_ = _el.querySelector('._id_').innerText;
			} catch(err) { return; }		
		}
		var _form = _el.querySelector('.db_options');
		sessionStorage.setItem(JSON.stringify(key),JSON.stringify(_form2obj(_form)));
	});
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
	//also use it outside the result set then w/o mass editing)
	if ( ! form.closest('tr') ) { callFunction(form,'getDetails','_popup_',false,'details','updateSelectionsOfThis').then(()=>{ newEntry(form,'',''); return false; }); return false; }
	//
	thecheckbox = form.closest('tr').querySelector('td').getElementsByTagName('input')[0];
	if ( thecheckbox.checked ) {
		document.getElementById('editTableName').value = tablename;
		callFunction(document.getElementById('formMassEdit'),'getDetails','_popup_',false,'details','updateSelectionsOfThis').then(()=>{ return false; });
	} else {
		callFunction(form,'getDetails','_popup_',false,'details','updateSelectionsOfThis').then(()=>{ newEntry(form,'',''); return false; });
	}
	return false
}

// callback function for getDetails
function updateSelectionsOfThis(form,arg,responsetext) {
	// identify new entry window by reload div
	if ( ! responsetext ) { return false; }
	_reloadid = responsetext.match(/form=\"(reload[^\"]*)\"/)[1];
	// updateSelections in the parent edit_wrapper
	el = document.getElementById(_reloadid).closest('.section');
	updateSelectionOfClasses(el);
	//show/hide calendar fields
	if ( ! el.querySelector('.inputid[name="id_os_calendars"]') ) {
		el.querySelectorAll('.calendar').forEach(function(calendar){
			let edit_wrapper = calendar.closest('.edit_wrapper');
			edit_wrapper.remove();
		});
	}
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

/*
 * sorts a table by the column of the given table element
 * DOMNode	tableel
 * todo: preserve classes of rows!
 */
function sortTable(tableel) {
	var table = tableel.closest('table');
	var prev = tableel; var columnnumber = -1;
	while ( prev != null ) { prev = prev.previousElementSibling; columnnumber++; }
	var table_array = new Array();
	var headers = '';
	for ( let row of table.rows ) {
		ta_length = table_array.push({"classes": row.className, "data": new Array()});
		for ( let td of row.children ) {
			if ( td.tagName == "TH" ) { headers = row.innerHTML; }
			if ( td.tagName == "TD" ) { table_array[ta_length-1].data.push(td.innerHTML); }
		}
		if ( table_array[ta_length-1].data.length == 0 ) { table_array.pop(); }
	}
	//sort and reverse sorting if nothing has changed
	old_table_json = JSON.stringify(table_array);
	table_array.sort( (row1,row2) => parseInt(row2.data[columnnumber]) - parseInt(row1.data[columnnumber]) || row1.data[columnnumber].localeCompare(row2.data[columnnumber]) );
	if ( old_table_json == JSON.stringify(table_array) ) {
		table_array.reverse();
	}
	var newInnerHTML = '<tr>' + headers + '</tr>';
	table_array.forEach( row => {
		newInnerHTML += '<tr class="' + row.classes + '">';
		row.data.forEach( td => {
			newInnerHTML += '<td>' + td + '</td>';
		})
		newInnerHTML += '</tr>';
	})
	table.innerHTML = newInnerHTML; 
}

//this a callback for callFunction
function unlock(form,arg,response) {
	var veil = document.getElementById('veil')
	if (veil.innerText == '' ){
		veil.className = '';
	}
//	return false
}
