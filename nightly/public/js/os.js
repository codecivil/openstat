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
		let _before = new Object();
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
	let _before = new Object();
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

//contextMenu: decide on element type what to offer or what to do immediately
function _contextMenu(el) {
	if ( document.getElementById('contextmenu') ) { return false; }
	let menuwrapper = document.createElement('div');
	let menu = document.createElement('menu');
	if ( el.closest('table') ) {
		li = document.createElement('li');
		li.textContent = "Sortierung umkehren";
		li.classList.add("contextmenu");
		li.addEventListener('click',function(){ 
			_toggleSortTableIn(el.closest('table'),Array.prototype.indexOf.call(el.parentElement.children,el));
			document.getElementById('contextmenu').remove();
			document.removeEventListener('click', _removeContextmenu);
		});
		menu.appendChild(li);
		li = document.createElement('li');
		li.textContent = "Spalte verstecken";
		li.classList.add("contextmenu");
		li.addEventListener('click',function(){
			el.onclick();
			document.getElementById('contextmenu').remove();
			document.removeEventListener('click', _removeContextmenu);
		});
		menu.appendChild(li);
	}
	menuwrapper.appendChild(menu);
	elrect = el.getBoundingClientRect();
	console.log(elrect);
	menuwrapper.style.position = "fixed";
	menuwrapper.style.top = elrect.bottom+"px";
	menuwrapper.style.left = elrect.left+"px";
	menuwrapper.id = "contextmenu";
	document.body.appendChild(menuwrapper);
	//eventListener function:
	function _removeContextmenu(e) {
		if (e.target.offsetParent != document.getElementById('contextmenu') ) {
			document.getElementById('contextmenu').remove();
		}
		document.removeEventListener('click', _removeContextmenu);
	}
	document.addEventListener('click', _removeContextmenu);
}

//reverses order from position-th column on
function _toggleSortTableFrom(tableel,position) {
	let tablecopy = tableel.cloneNode(true);
	let tabletarget = document.createElement('table');
	let oldjson = '';
	let lasttr = null;
	tableel.querySelectorAll('tr').forEach(tr => {
		newjson = _childElements2JSON(tr,position);
		console.log(newjson);
		if ( oldjson != newjson ) {
			tabletarget.appendChild(tr);
		}
		if ( oldjson == newjson ) {
			tabletarget.insertBefore(tr,lasttr);
		}
		lasttr = tr;
		oldjson = newjson;
	});
	tabletarget.id = tableel.id;
	tableel.replaceWith(tabletarget);
	//to be continued
}

//reverses order in position-th column (needs the tableel to have an id!)
function _toggleSortTableIn(tableel,position) {
	_toggleSortTableFrom(tableel,position);
	tableel = document.getElementById(tableel.id);
	_toggleSortTableFrom(tableel,position+1);
}

//limit: end at the limit-th child element
function _childElements2JSON(el,limit) {
	_counter = 0
	_array = []
	for (const child of el.children) {
		if ( _counter < limit ) {
			_array.push(child.textContent);
			_counter++;
		}
	}
	return JSON.stringify(_array)
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
	//data-hidden as string attribute!
	if ( ! ('hidden' in el.dataset) ) { el.dataset.hidden = el.hidden; } // get initial settings for hidden (e.g. for EXTENSIBLE data models)
	var id = el.id.replace(/_list$/,''); //so it works for EXTENSIBLE data models, where you have _list and _text ids.
	id = id.replace(/_functions$/,''); //so it works for FUNCTION data model, where you have _functions as hidden select.
	var option = el.getElementsByTagName('option');
	var _disabled = new Array(); //the state for checkboxes: activate all options matching a checked checkbox
	for (j=0; j<option.length; j++) {
		option[j].disabled = false;
		_disabled[j] = true;
		//2022-02-28
		option[j].hidden = false;
	}
	var conditions = new Array();
	if ( document.getElementById(id+'_conditions') ) { conditions = JSON.parse(document.getElementById(id+'_conditions').innerText); }
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
							if ( match == -1 && ! option[j].selected ) { option[j].disabled = true; option[j].hidden = true; }; //do not disallow current value!? important for EXTENSIBLE LISTs
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
							if ( match > -1 || option[j].selected ) { _disabled[j] = false; }; //do not disallow current value!? important for EXTENSIBLE LISTs
						}
					}
					option[j].disabled = _disabled[j];
					option[j].hidden = _disabled[j];
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
					if ( match == -1 && ! option[j].selected ) { option[j].disabled = true; option[j].hidden = true;} //do not disallow current value!? important for EXTENSIBLE LISTs
				} 
			}
		}
	}
	//console.log(id,conditions_met,_show_matched,_hide_matched);
	if ( el.querySelectorAll('option:checked:disabled').length > 0 ) {
		el.querySelectorAll('option:checked:disabled')[0].selected = false;
	}
	//_SHOW_ and _HIDE_ now acting:
	let _label = el;
	if ( el.parentElement.querySelector('[for="'+el.id+'"]') ) { _label = el.parentElement.querySelector('[for="'+el.id+'"]'); } // el and _label share the same properties int hre rest of this function
	if ( _hide_matched && ! _show_matched ) {
		// if MULTIPLE and NOT local simply hide the full searchfield
		if ( el.closest('.edit_wrapper') && el.id.indexOf('[') == -1 ) {
			el.closest('.edit_wrapper').querySelectorAll('.searchfield').forEach(function(_searchfield){
				_searchfield.hidden = true;
			});
			el.closest('.edit_wrapper').querySelectorAll('label[id$=_plus]').forEach(function(_plus){
				_plus.hidden = true;
			});
		}
		//
//		el.style.opacity = 0;
//		_label.style.opacity = 0;
		el.style.visibility = 'hidden';
		_label.style.visibility = 'hidden';
		setTimeout(function(){
			el.hidden = true; el.disabled = true;
			if ( el.parentElement.querySelector('[for="'+el.id+'"]') ){ el.parentElement.querySelector('[for="'+el.id+'"]').hidden = true; }		
			}, 700);
	} else {
		// is this compatible with mass editing or non-editing permissions?
		// it is not working for extensible lists! how to decide which input method must be shown? to be continued
//		el.hidden = false; el.disabled = false;
		// if MULTIPLE simply hide the full searchfield
		if ( el.closest('.edit_wrapper') ) {
			el.closest('.edit_wrapper').querySelectorAll('.searchfield').forEach(function(_searchfield){
				_searchfield.hidden = false;
			});
			el.closest('.edit_wrapper').querySelectorAll('label[id$=_plus]').forEach(function(_plus){
				_plus.hidden = false;
			});
		}
		//
		el.hidden = (el.dataset.hidden === 'true'); el.disabled = ( el.dataset.hidden === 'true' );
		if ( el.parentElement.querySelector('[for="'+el.id+'"]') ) { el.parentElement.querySelector('[for="'+el.id+'"]').hidden = ( el.dataset.hidden === 'true' ); }
		setTimeout(function(){
			if ( el.dataset.hidden == "false" ) { //new in 2022-02-26
//				el.style.opacity = 1;
				el.style.visibility = 'visible';
//				_label.style.opacity = 'initial'; //to do: deal with labels in multiple entries: they must have opacity 0
				_label.style.visibility = 'initial'; //to do: deal with labels in multiple entries: they must have opacity 0
				if ( el.closest('.edit_wrapper' ) ) {
				// compare to main_*.css, e.g.: .details label ~  .searchfield + .searchfield label.onlyone { opacity: 0.5; }
					el.closest('.edit_wrapper').querySelectorAll('.searchfield + .searchfield label.onlyone').forEach(function(_onlyone) {
						_onlyone.style.visibility = 'hidden';
					});
					el.closest('.edit_wrapper').querySelectorAll('label ~ .searchfield + .searchfield label.onlyone').forEach(function(_onlyone) {
						_onlyone.style.visibility = 'visible';
					});
				}
			}	
		},100);
	}
}

//update selection of all class members of array of classes given by json_string
//currently, in local use it loops if a subfield has conditions as well as dependencies!
function updateSelectionOfClasses(el) {
	if ( el.parentElement.querySelector('.dependencies') ) {
		dependency_divs = el.parentElement.querySelectorAll('.dependencies');
	} else {
		if ( el.closest('.edit_wrapper') && el.closest('.edit_wrapper').querySelector('.dependencies') ) {
			dependency_divs = el.closest('.edit_wrapper').querySelectorAll('.dependencies');
		}
		else return false
	}
	//take only the closest dependency_div (upwards) sibling for compounds
	let cprx = new RegExp('\\[[\\d]*\\]');
	if ( el.id.match(cprx) != null ) {
		let prev = el.previousElementSibling;
		let maxdist = 3;
		let dist = 0;
		while ( ! prev.classList.contains("dependencies") && dist < maxdist ) {
			prev = prev.previousElementSibling;
			dist++;
		}
		if ( dist < maxdist ) { dependency_divs = [prev]; }
	} 
	for ( let dependency_div of dependency_divs ) {
		json_string = dependency_div.textContent;
		var _classes = JSON.parse(json_string);
		//too much recursion here currently: inquire
		_classes.forEach(function(_class) {
			_classMembers = document.getElementsByClassName(_class);
			for ( let member of _classMembers ) {
				//update the selection
				updateSelection(member);
				//fire the change event (which otherwise would not fire)
				//  this is deprecated:
				//    ev = document.createEvent('Event');
				//    ev.initEvent('change', true, false);
				//  instead:
				ev = new CustomEvent('change', { bubbles: true });
				member.dispatchEvent(ev);
		//too much recursion... (above)
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
		if ( _input.name != '' && ! _input.name.endsWith('[]') ) { _input.name += '[]'; }
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
		let _recover = sessionStorage.getItem('recoverentries');
		if( _recover == 'true' || ( _recover == null && window.confirm('Es gibt ungespeicherte Daten. Willst Du diese wiederherstellen?') ) ) {
			sessionStorage.setItem('recoverentries','true');
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
		} else { sessionStorage.setItem('recoverentries','false'); }
		sessionStorage.removeItem(JSON.stringify(key));
		setTimeout(function(){ sessionStorage.removeItem('recoverentries'); },120000); //forget choice after 2 minutes
	}
	//process function flags
	processFunctionFlags(el);
	//position notes correctly
	styleNotes();
	//add help text functionality
	document.querySelector('#helpModeBtn').removeEventListener('click',toggleHelpTexts);
	document.querySelector('#helpModeBtn').addEventListener('click',toggleHelpTexts);
	toggleHelpTexts();
    //execute show/hide for new entries
    if ( key._id_ == "new" )  { updateSelectionOfClasses(el.querySelector('.section')); }
}

function toggleHelpTexts(evt) {
	if ( document.querySelector('#helpModeBtn').checked ) {
		document.querySelectorAll('[data-title]').forEach(helpitem => {
			helpitem.onmouseover = positionHelpField;
			helpitem.onmouseout = removeHelpField;
		});
	} else {
		document.querySelectorAll('[data-title]').forEach(helpitem => {
			helpitem.querySelectorAll('.afterlike').forEach(afterlike => afterlike.remove());
			helpitem.onmouseover = null;
			helpitem.onmouseout = null;
		});
	}
}

function positionHelpField(evt) {
	if ( document.querySelector('#helpModeBtn').checked ) {
		let helpitem = evt.target.closest('[data-title]')	;
		let afterlike = document.createElement('div');
		afterlike.classList.add('afterlike');
		afterlike.style.top = "calc( "+evt.clientY+"px + 0.5rem )";
		afterlike.style.left = evt.clientX+"px";
		afterlike.innerText = helpitem.dataset.title;
		helpitem.appendChild(afterlike);
	}
}

function removeHelpField(evt) {
	evt.target.closest('[data-title]').querySelectorAll('.afterlike').forEach(afterlike => afterlike.remove());
	if ( ! document.querySelector('#helpModeBtn').checked ) {
		evt.target.closest('[data-title]').removeAttribute('onmouseover');
		evt.target.closest('[data-title]').removeAttribute('onmouseout');
	}
}

function styleNotes() {
	// implement the NOTEs position and sync
	document.querySelectorAll('.note_edit').forEach(function(_note){
		_note.closest('.edit_wrapper').style.position = "sticky";
		_note.closest('.edit_wrapper').style.top = "8rem";	
		_note.closest('.edit_wrapper').style.display = "flex";	
		_note.closest('.edit_wrapper').style.width = "10rem";	
		_note.closest('.edit_wrapper').style.height = "0";	
		_note.closest('.edit_wrapper').style.left = "calc(100% - 14rem)";	
		_note.closest('.edit_wrapper').style.zIndex = "5";
		if ( _note.querySelector('.note_wrapper textarea').value == '' ) {
			_note.querySelector('.note_wrapper textarea').style.visibility = 'hidden';
		} else {
			_note.style.opacity = 1;
// This prevents that the note flows awkwardly into next entry, but creates a 14rem space at entry location; but only if there is a note...
			_note.closest('.edit_wrapper').style.height = "14rem";	
		}
	});	
}

function updateNoteColorsInput(el) {
	if ( el.classList.contains('note_all') && el.checked) {
		el.parentElement.querySelectorAll('.note_cb ~ .note_cb').forEach(note_cb => note_cb.checked = false);
	} else {
		el.parentElement.querySelector('.note_all').checked = false;
	}
	if ( el.parentElement.querySelectorAll('.note_cb:checked').length == 0 ) {  
		el.parentElement.querySelector('.note_all').checked = true;
	}
	let _value = new Array();
	el.parentElement.querySelectorAll('.note_cb:checked').forEach(_checked => { _value.push(_checked.value); });
	el.parentElement.querySelector('.note_colors_input').value = JSON.stringify(_value);
}

//el: element whose functions child div shall be processed
function processFunctionFlags(el) {
	let functionList = el.querySelectorAll('.functions li label');
	functionList.forEach(function(_function) {
		_functionflags = JSON.parse(_function.dataset.flags);
		_functionflags.forEach(function(_flag) {
			switch(_flag) {
				//flag "HIDDEN" is treated in db_functions.php: hide it before it reaches the client!
				//flag "LOGIN" is treated by executeLoginFunctions (does not depend on el, has to be called only once)
				case 'AUTO':
					let ev = new Event('click');
					_function.dispatchEvent(ev);
					break;;
			}
		});
	});
}

function executeLoginFunctions() {
	functionList = document.querySelectorAll('.functions li label[data-flags*="LOGIN"]');
	functionList.forEach(function(_function) {
		let ev = new Event('click');
		_function.dispatchEvent(ev);
	});
	return false;
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

function _saveFilterLog() {
	callFunction('_','saveFilterLog','').then( () => { return false; });
	return false;
}

function _toggleColumn(el,key) {
	console.log(el.textContent);
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

function _toggleEditAll(form,master,selector='') {
	document.querySelectorAll('input[form="'+form+'"]'+selector).forEach(function(el){el.checked = document.getElementById(master).checked;});
}

function _toggleEnabled(number) {
	div = document.getElementById('enablable'+number);
	if ( div.classList.contains('disabled') ) { div.classList.remove('disabled'); } else { div.classList.add('disabled'); };
	div.querySelectorAll('input').forEach(function(el){ 
		el.disabled = ! el.disabled;
		//generate the correct initial disabled setting for EXTENSIBLE LIST, LIST THEN SUGGEST...
		if ( el.id.split('_')[2] ) { _toggleOption(el.id.split('_')[1]); _toggleOption(el.id.split('_')[1]); }
	});
	div.querySelectorAll('select').forEach(function(el){ 
		el.disabled = ! el.disabled;
		//generate the correct initial disabled setting for EXTENSIBLE LIST, LIST THEN SUGGEST...
		if ( el.id.split('_')[2] ) { _toggleOption(el.id.split('_')[1]); _toggleOption(el.id.split('_')[1]); }
	});
	div.querySelectorAll('textarea').forEach(function(el){ el.disabled = ! el.disabled; });
	//for tinymce editors:
	//to be continued
	//_waitForEditorThenToggle(div);
}

function _waitForEditorThenToggle(div,_counter) {
	//give up after 10 tries
	if ( ! _counter ) { _counter = 0; }
	if ( _counter >= 10 ) { return; }
	//
	if ( ! div.querySelector('iframe') ) {
		_counter++; 
		setTimeout(function(){ console.log("waiting for TinyMCE: "+_counter); _waitForEditorThenToggle(div,_counter); },500); 
	} else {
		console.log("found TinyMCE editor");
		div.querySelectorAll('iframe').forEach(function(_iframe){
			let _editor = _iframe.contentDocument.body;
			_editor.setAttribute("contenteditable", _editor.getAttribute("contenteditable")==="false"); 
		});
	}
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

function _togglePinnedRolling(_select) {
	console.log('aha');
	if ( _select.value == "none" ) { _select.closest('span').querySelector('.toggle').checked = false; }
	// this does not work: onclick and checked attribute have a race condition...
	if (  _select.closest('span').querySelector('.toggle').checked == false ) { _select.closest('span').querySelector('select').value = "none"; }
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
	//check if single entry is already open and then scroll to it and exit
	let _notopen = new Promise((resolve,reject) => {
		let _maybe;
		if ( _maybe = form.querySelector('input[name=id_'+tablename+']') ) {
			let _value = _maybe.value;
			let _allpopups = document.querySelectorAll('.popup');
			let _checked = 0;
			_allpopups.forEach(popup => {
				if ( popup.querySelector('._id_') && popup.querySelector('._id_').textContent == _value && popup.querySelector('._table_') && popup.querySelector('._table_').textContent == tablename ) {
					popup.scrollIntoView();
					reject();
				} else {
					_checked++;
					if ( _checked == _allpopups.length ) { resolve(); }
				}
			});
		}
	});
	_notopen.then(() => {
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
	},() => { return false; });
	return false
}

// callback function for getDetails
function updateSelectionsOfThis(form,arg,responsetext) {
	// identify new entry window by reload div
	if ( ! responsetext ) { return false; }
    if ( responsetext.match(/form=\"(reload[^\"]*)\"/) ) {
    	_reloadid = responsetext.match(/form=\"(reload[^\"]*)\"/)[1];
    }
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
	// implement the NOTEs position and sync (globally)
	styleNotes();
}

function note_show(el) {
	el.parentElement.querySelector('.note_wrapper textarea').style.visibility = "visible";
	el.parentElement.style.opacity = 1;
// This prevents that the note flows awkwardly into next entry, but creates a 14rem space at entry location; but only if there is a note...
	el.closest('.edit_wrapper').style.height = "14rem";
	return false
}

function note_delete(el) {
	let _textarea = el.parentElement.querySelector('.note_wrapper textarea');
	_textarea.value = '';
	_textarea.style.visibility = 'hidden';
    el.parentElement.querySelector('.note_empty').checked = true;
	el.parentElement.style.opacity = "";
// This prevents that the note flows awkwardly into next entry, but creates a 14rem space at entry location; but only if there is a note...
	el.closest('.edit_wrapper').style.height = "0";
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
		el_innerhtml += '<tr onclick="_toggleStat(\'median\');"><th data-title="Zeigt den Median">Median</th><td>'+statData.median+'</td></tr>';
	}
	if ( statData.mean ) {
		el_innerhtml += '<tr onclick="_toggleStat(\'mean\');"><th data-title="Zeigt den Mittelwert">Mittelwert</th><td>'+statData.mean+'</td></tr>';
	}
	if ( statData.mean ) {
		el_innerhtml += '<tr onclick="_toggleStat(\'sd\');"><th data-title="Zeigt die 1- und 2-SD-Intervalle um den Mittelwert">Standardabweichung</th><td>'+statData.sd+'</td></tr>';
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
	document.getElementById('userName').blur();
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

//this a callback for callFunction
function cleanDB(form,arg,response) {
	//only let one cleaning window live
	document.querySelectorAll('.popup.cleanup').forEach(function(cleanup) {
		if ( cleanup != document.querySelector('.popup.cleanup') ) {
			_close(cleanup,true);
		}
	});
}

//this a callback for callFunction
function exportConfig(form,arg,response) {
    let _download = document.createElement('a')
    _download.href = "data:text/plain;charset=utf-8;base64,"+response;
    _download.download = "openStat-"+form.querySelector('select[name=configname]').value.replace(/[^a-zA-Z0-9_\-]/g,'')+".conf";
    _download.click();
    console.log(_download);
}

function importConfig(form,arg,response) {
    let _imports = document.createElement('input');
    _imports.type = "file";
    _imports.setAttribute('multiple',true);
    _imports.setAttribute('accept','.conf');
    _imports.onchange = function() {
        _files = _imports.files;
    	for ( var i = 0; i < _files.length; i++ ) {
            _result = function(i) {
                var r = new FileReader();
                r.onload = function () {
                    //if configname is Default, do not continue:
                    if ( JSON.parse(r.result).configname == "Default" ) { return false; }
                    document.getElementById('trash').value = r.result;
                    callFunction('_','updateCustomConfig').then(()=>callFunction('_','updateSidebarCustom','sidebar').then(()=>{ return false; }));
                }
                r.readAsText(_files[i]);
            }(i);
        };
    };
    _imports.click();
}
//action: copy unless it is an attribution field; then copy if clipboard is empty, paste otherwise
//to be continued...
function transportAttribution(el) {
	//
	//look for entry info depending on containing window
	//
	let _info = new Object();
	let _copy = true;
	let _infoel = null;
	//results or trafficLight
//was:	if ( document.getElementById('results_wrapper').contains(el) ) {
	if ( el.closest('td') ) {
		infoel = el.closest('td').querySelector('form input[type=text]');
		_info._table = infoel.name;
		_info._id = infoel.value;
	}
	//entry headline 
	else if ( el.closest('.popup.details') && el.closest('.db_headline_wrapper') ) {
		infoel = el.closest('.popup.details').querySelector('div.hidden')
		_info._table = 'id_'+infoel.querySelector('div._table_').innerText;
		_info._id = infoel.querySelector('div._id_').innerText;
	}
	//entry attribution
	else if ( el.closest('.popup.details') ) {
		infoel = el.nextElementSibling;
		_info._table = infoel.name;
		_info._id = infoel.value;
		if ( sessionStorage.getItem('attribution_clipboard') != null ) { _copy = false; }
	}
	//
	// do nothing if disabled
	//
	if ( infoel.disabled ) { return false; }
	//
	// now do the action 
	//
	if ( _copy ) {
		if (el.querySelector('i')) { _info._icon = el.querySelector('i').className; }
		else if (el.parentElement.querySelector('i')) { _info._icon = el.parentElement.querySelector('i').className; }
		else { _info._icon = ''; }
		sessionStorage.setItem('attribution_clipboard',JSON.stringify(_info));
		document.getElementById('clipboard').innerHTML = '<label class="unlimitedWidth"><i class="fas fa-clipboard-check"></i> <small><i class="'+_info._icon+'"></i> '+_info._id+'</small></label>';
	} else {
		//paste in attribution field
		_info = JSON.parse(sessionStorage.getItem('attribution_clipboard'));
		if ( el.nextElementSibling.name == _info._table ) {
			el.nextElementSibling.value = _info._id;
			el.querySelector('i').nextSibling.textContent = ' (ID: '+_info._id+'; Änderung muss noch abgeschickt werden)';
			if (el.querySelector('b')) { el.querySelector('b').remove(); }
		}
	}
	return false;
}

function emptyClipboard() {
	sessionStorage.removeItem('attribution_clipboard');
	document.getElementById('clipboard').innerHTML = '<label class="unlimitedWidth"><i class="fas fa-clipboard"></i></label>';
}

function myScrollIntoView(el) {
	if ( el != null ) {
		let _scroll = 5.7; //scroll under the statusbar
		//if inside an entry, scroll under entry header
		if ( el.closest('.popup_wrapper .fieldset') ) { _scroll = 14.4; }
		new Promise((resolve,reject)=>{ el.scrollIntoView(); resolve();}).then(()=>{ 
			window.scrollBy({ top: -_scroll*pxperrem, behavior: 'smooth'});
		});
	}
}

//this is now a callback function for an empty PHP function
//actually, it's toggling
function showEmptyFields(_form,_arg,resp) {
	let el = _form.closest('.popup_wrapper');
	let target = el.querySelector('.message');
	console.log(target);
	//here is the toggle:
	if ( target.querySelector('ul.emptyfields') ) {
		target.innerHTML = '';
		el.querySelectorAll('.edit_wrapper').forEach(function(editwrapper) {
			editwrapper.style.background = "initial";
		});	
		return;
	}
	//
	target.innerHTML = '';
	//color the unfilled fields and keep it on the books
	let _notFilledFields = new Array();
	el.querySelectorAll('.edit_wrapper').forEach(function(editwrapper) {
		editwrapper.style.background = "initial";	
		let _filled = false;
		editwrapper.querySelectorAll('select:not([hidden]),input:not([hidden]),textarea:not([hidden])').forEach(function(_input) {
			if ( _input.value != '' ) { _filled = true; } else { _inputid = _input.id; }
		});
		if ( ! _filled ) { 
			editwrapper.style.background = "var(--background-warning)";
			console.log(editwrapper.querySelectorAll('label'));
			_notFilledFields.push({_id: _inputid, _label: editwrapper.querySelector('label.onlyone,label.files').innerText});
		}
	});
	//create the message
	let _ul = document.createElement('ul');
	_ul.classList.add('emptyfields');
	_ul.style.maxWidth = 'calc( '+getComputedStyle(target).width+' - 1rem )';
	target.appendChild(_ul);
	_notFilledFields.forEach(function(_notFilledField) {
		_li = document.createElement('li');
		_a = document.createElement('a');
		_a.textContent = _notFilledField._label;
		_a.dataset.target = _notFilledField._id;
		_a.onclick = function() { myScrollIntoView(document.getElementById(this.dataset.target).previousElementSibling); };
		_li.appendChild(_a);
		_ul.appendChild(_li);
	});
}

function showOpenEntries(el) {
	let _selectedValue = el.querySelector('select').value;
	el.querySelector('select').innerHTML = '';
	let _option = document.createElement('option');
	_option.value = 'results_wrapper';
	_option.textContent = 'Filter/Statistik/Details';
	if ( _option.value == _selectedValue ) { _option.selected = true; }
	el.querySelector('select').appendChild(_option);
	let openEntries = document.querySelectorAll('.popup_wrapper .popup');
	openEntries.forEach(function(_openEntry){
		if ( ! _openEntry.querySelector('.db_headline') ) { return; }
		let _option = document.createElement('option');
		_option.value = _openEntry.id;
		_option.textContent = _openEntry.querySelector('.db_headline').innerHTML.replace(/.*\/i>/,'').replace(/<span.*/,'').substr(0,25);
		if ( _option.value == _selectedValue ) { _option.selected = true; }
		el.querySelector('select').appendChild(_option);
	});
	return false;
}

//callback functions for editProfile
//responsetext is new profile popup on first call, db success for subsequent calls
function editProfile(form,arg,responsetext) {
	let responseObj = new Object();
	try { responseObj = JSON.parse(responsetext); } catch(err) { responseObj = new Object(); responseObj.dbMessage = ''; responseObj.dbMessageGood = "true"; }
	//keep only one profile popup
	let first=true;
	document.querySelectorAll('.profile').forEach(function(_profile){
		if ( ! first ) { _profile.closest('.popup_wrapper').remove(); first = false; }
		first = false;
	});
	let submitProfile = document.querySelector('.profile .submitProfile');
	let messageProfile = document.querySelector('.profile .dbMessage');
	//fill message div
	if ( responseObj.dbMessageGood ) {
		messageProfile.classList.add(responseObj.dbMessageGood);
	}
	if ( responseObj.dbMessage ) {
		messageProfile.textContent = responseObj.dbMessage;
	}
	//show save button if entries change
	document.querySelectorAll('.profile input[type=text],.profile input[type=email]').forEach(function(_input){
		_input.onkeyup = function() { submitProfile.classList.add('changed'); }
	});
	document.querySelectorAll('.profile input[type=checkbox]').forEach(function(_input){
		_input.onchange = function() { submitProfile.classList.add('changed'); }
	});
	if ( responseObj.dbMessageGood == "true" ) { submitProfile.classList.remove('changed'); }
	return false
}

// el is the profile form in the popup
function updateProfile(el,json) {
	//remove result message
	el.closest('.profile').querySelector('.dbMessage').textContent = '';
	//update hidden fields
	el.querySelectorAll('input[type=checkbox]').forEach(function(cb){
		if (cb.dataset && cb.dataset.name && cb.dataset.scope ) {
			if ( cb.checked ) {
				el.querySelector('input[name='+cb.dataset.name+cb.dataset.scope+']').value = el.querySelector('input[name='+cb.dataset.name+'_private]').value;
			} else {
				el.querySelector('input[name='+cb.dataset.name+cb.dataset.scope+']').value = '';
			}
		}
	});
	//validate entries
	let invalid = validate(el,json);
	colorInvalid(el,invalid);
	//update os_profiles
	if ( invalid.length == 0 ){ callFunction(el,'updateProfile','',false,'','editProfile').then(()=>{ return false; }); }
	return false
}

function _FUNCTIONobserveChanges(el) {
	let _parent = el.parentElement;
	//execute functions if hidden //seems not to fire for dynamic _HIDE_ and _SHOW_
	if (_parent.querySelector('.db_function_check').hidden) { _parent.querySelector('.db_function_check').checked = true; }
	//
	//what for?: if (_parent.querySelector('.db_function_check').checked) {
		_FUNCTIONStatus(el,'changed');
	//}
}

/*
 * FUNCTION values are JSON strings with attributes
 * "type": "FUNCTION" (in order to be recognized on server side),
 * "status": initial and changed values of relevant fields
 * "functions": FIELD functions to be executed
*/
function _FUNCTIONStatus(el,statusname) {
	if ( ! statusname ) { let statusname = 'initial'; }
	inputfield = el.closest('.edit_wrapper');
	if ( inputfield.querySelector('div[id$=_conditions]') ) { 
		_conditions_element = el.closest('.edit_wrapper').querySelector('div[id$=_conditions]');
	}
	let _status = new Object();
	if ( _conditions_element != undefined ) {
		let _conditions = JSON.parse(_conditions_element.textContent);
		for ( let _condition of _conditions ) {
			if ( _condition.depends_on_key != '' ) {
				if ( el.closest('form').querySelector('.db_'+_condition.depends_on_key+':not([disabled])') ) {
					//take last item instead of first (changed at 2023-09-28)
					value = [...el.closest('form').querySelectorAll('.db_'+_condition.depends_on_key+':not([disabled])')].slice(-1)[0].value;
				} else {
					value = '';
				}
				_status[_condition.depends_on_key] = value;
			}
		}
	}
	try {
		function_field_obj = JSON.parse(inputfield.querySelector('.db_function_field').value);
	} catch(e) {
		function_field_obj = new Object();
	}
	if ( ! function_field_obj.status ) { function_field_obj.status = new Object(); }
	if ( ! function_field_obj.functions ) { function_field_obj.functions = new Object(); }
	function_field_obj.status[statusname] = _status;
	function_field_obj.functions = new Array();
	function_field_obj.type = 'FUNCTION';
	//functions must be empty if execution box is not checked
	if ( inputfield.querySelector('.db_function_check').checked ) {
		for ( let option of inputfield.querySelector('.db_function_functions').options ) { if (! option.disabled) {function_field_obj.functions.push(option.value);} }
	} else {
		function_field_obj.functions = ["none"];
	}
	inputfield.querySelector('.db_function_field').value = JSON.stringify(function_field_obj);
	return false
}
