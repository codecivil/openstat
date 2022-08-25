function allowDrop(ev) {
    ev.preventDefault();
}

function drag(ev) {
    ev.dataTransfer.setData("text", ev.target.id);
    ev.target.style.opacity="0.5";
}

function dragOnDetails(ev) {
    ev.dataTransfer.setData("text", ev.target.id + '=' + ev.target.getElementsByTagName('input')[0].name+'='+ev.target.getElementsByTagName('input')[0].value);
    ev.target.style.opacity="0.5";
}

function dragenter(ev) {
	console.log(ev.target);
//    ev.target.style.margin="20px 0 0 0";	
}

function dragleave(ev) {
//    ev.target.style.margin="0 0 0 0";	
}

function dragend(ev) {
//    ev.target.style.margin="0 0 0 0";	
    var data = ev.dataTransfer.getData("text");
    if (document.getElementById(data)) {document.getElementById(data).style.opacity="1";}
}

function drop(ev,el) {
    ev.preventDefault();
//    ev.target.style.margin="0 0 0 0";
    var data = ev.dataTransfer.getData("text");
//    ev.target.appendChild(document.getElementById(data));
    document.getElementById(data).style.opacity="1";
//	ev.target.parentNode.insertBefore(document.getElementById(data),ev.target);
	el.parentNode.insertBefore(document.getElementById(data),el);
	updateTime(el);
}

function dropOnDetails(ev,el) {
    ev.preventDefault();
//    ev.target.style.margin="0 0 0 0";
    var data = ev.dataTransfer.getData("text");
	var data_array = data.split("="); 
	var data_src_id = data_array[0]
	var data_table = data_array[1];
	var data_id = data_array[2];
    if (document.getElementById(data_src_id)) { document.getElementById(data_src_id).style.opacity="1"; }
	if ( el.className == "ID_"+data_table.substr(3) ) {
		el.getElementsByTagName('input')[0].value = data_id;
		el.getElementsByTagName('label')[0].innerHTML = '<i class="'+el.getElementsByTagName('i')[0].className+'"></i> [ID: ' + data_id + '] (Änderung muss noch abgeschickt werden)';
	}
	updateTime(el);
}

function trashMapping(el) {
	var _parent = el.closest('div');
	_parent.getElementsByTagName('input')[0].value = undefined;
	_parent.getElementsByTagName('label')[0].innerHTML = '<i class="'+_parent.getElementsByTagName('i')[0].className+'"></i> (keine Zuordnung; Änderung muss noch abgeschickt werden)';
	return false;
}

function publishResponse(form,id,add,classes) {
	processForm(form,form.action,id,add,classes);
	return false;
}

//publishes response html code of a submitted form with custom PHP script (other than action) on html element with given id
//add=true: do not replace, but append
//classes: append classes to the html element
function processForm(form,phpscript,id,add,classes) {
	var _request = new XMLHttpRequest();
	if ( id == '_popup_' ) {
		var popup_wrapper = document.createElement('div');
		popup_wrapper.className = "popup_wrapper";
		var popup_close = document.createElement('div');
		popup_close.className = "popup_close";
		popup_close.onclick = function() { close(this); };
		popup_close.innerHTML = '<i class="fas fa-times-circle"></i>';
		popup_wrapper.appendChild(popup_close);
		var popup = document.createElement('div');
		popup.className = "popup";
		var idnumber = Math.floor(Math.random()*32768);
		popup.id = 'popup_'+idnumber;
		popup_wrapper.appendChild(popup);
		before = document.getElementsByClassName('popup_wrapper')[0];
		document.body.insertBefore(popup_wrapper,before);
		id = popup.id;
		myScrollIntoView(document.getElementById(id));
	}
	if ( id != '' ) {
		var el = document.getElementById(id);
		if (add) { var oldhtml = el.innerHTML; } else { var oldhtml = ''; }
		_request.onload = function() { el.innerHTML = oldhtml + _request.responseText; el.className += " "+classes; }
	}
	_request.open(form.method,phpscript,true);
	_request.send(new FormData (form));
	return false;
}

//local=true: do not update config
//response: not used, just formally needed for a callback of callFunction
function _close(el,local,response) { 
	var popup_wrapper = el.closest('.popup_wrapper');
	var key = new Object();
	if ( ! popup_wrapper ) { popup_wrapper = el.closest('.imp_wrapper'); };
	// for old entries
	if (popup_wrapper.getElementsByClassName('_table_')[0] && ( !(local) || ! local ) ) {
		var table = popup_wrapper.getElementsByClassName('_table_')[0].innerText;
		var id = popup_wrapper.getElementsByClassName('_id_')[0].innerText;
		key._table_ = table;
		key._id_ = id;
		if ( id != '') { callJSFunction('{"id_'+table+'":"'+id+'"}',removeOpenId); };
	}
	// for new entries
	if (! popup_wrapper.getElementsByClassName('_table_')[0] && ( !(local) || ! local ) ) {
		try { key._table_ = popup_wrapper.querySelector('.inputtable').value; } catch(err) { key._table_ = 'none'; }
		key._id_ = 'new';
	}
	if ( key._table_ != 'none' ) { 
		sessionStorage.removeItem(JSON.stringify(key));
	}
	popup_wrapper.parentNode.removeChild(popup_wrapper);
	updateSelectionsOfThis(el,false,response);
	return false;
}

function callFunction(form,phpfunction,id,add,classes,callback,arg) {
	return new Promise((resolve,reject) => {
		callAsyncFunction(form,phpfunction,id,add,classes,callback,arg,resolve);
	})
}
function callAsyncFunction(form,phpfunction,id,add,classes,callback,arg,resolve) {
	//postpone other calls to callFunction (works only for second calls; thirds, fourths... need an additional setTimeout > max(200 ms,process time of first call) in order not to be processed before!)
	//if ( document.body.style.cursor == 'progress' ) { setTimeout(function(){ callFunction(form,phpfunction,id,add,classes,callback,arg); },200); return false; }
	//
	document.body.style.cursor = 'progress';	
	var _request = new XMLHttpRequest();
	if ( form == '_') { form = document.getElementById('trashForm'); };
	if ( id == '_popup_' ) {
		var popup_wrapper = document.createElement('div');
		popup_wrapper.className = "popup_wrapper";
		var popup_close = document.createElement('div');
		popup_close.className = "popup_close";
		popup_close.onclick = function() { _close(this); };
		popup_close.innerHTML = '<i class="fas fa-times-circle"></i>';
		popup_wrapper.appendChild(popup_close);
		var popup = document.createElement('div');
		popup.className = "popup";
		var idnumber = Math.floor(Math.random()*32768);
		popup.id = 'popup_'+idnumber;
		popup_wrapper.appendChild(popup);
		before = document.getElementsByClassName('popup_wrapper')[0];
		document.body.insertBefore(popup_wrapper,before);

		id = popup.id;
	}
	if ( typeof id != 'undefined' && id != '' ) {
		var el = document.getElementById(id);
//		if (add) { var oldhtml = el.innerHTML; } else { var oldhtml = ''; }
		_request.onload = function() {
			if ( _request.responseText == "LOGGEDOUT" ) { window.location = "/login.php"; };
			if (add) { 
				el.innerHTML += _request.responseText;
			} else {
				el.innerHTML = _request.responseText;
			}
			if (classes) { 
				//not compatible with current usage: "details new": el.classList.add(classes); 
				el.className += " "+classes;
			};
			tinyMCEinit();
			document.body.style.cursor = 'auto';
			if ( ! document.getElementById('sidebar').contains(el) && ! document.getElementById('results_wrapper').contains(el) && el.id != 'veil' ) { myScrollIntoView(el.closest('.popup_wrapper')); }
			if (callback && window[callback]) { resolve(window[callback](form,arg,_request.responseText)); /*return window[callback](form,arg,_request.responseText);*/ } else { resolve(false); return false; };	
		}
	} else {
		_request.onload = function() { 	
 			if ( _request.responseText == "LOGGEDOUT" ) {  window.location = "/login.php"; };
			document.body.style.cursor = 'auto';
			if (callback) { resolve(window[callback](form,arg,_request.responseText)); return false; } else { resolve(false); return false; };
		}
	}
	_request.open(form.method,'../php/callFunction.php',true);
	//next line is unstable, better use new form field, see below
	//_request.setRequestHeader('X-Function-Call',phpfunction);
	formdata = new FormData (form);
	formdata.append('X_FUNCTION_CALL',phpfunction);
	_request.send(formdata);
//	if (callback) { return callback(form,arg); } else { return false; }
	return false;
}

//add or remove filters; remove for add=false;
function addFilters(form,add) {
	var myPromise = '';
	if ( add == false ) 
	{
		myPromise = callFunction(form,'removeFromConfig','sidebar',true);
	}
	else 
	{
		myPromise = callFunction(form,'addToConfig');
	} 
	myPromise.then(()=> {
		form.reset();
	//	processForm(document.getElementById('formFilters'),'../php/updateSidebar.php','sidebar');
		callFunction(document.getElementById('formFilters'),'updateSidebar','sidebar').then(()=>{ return false; });
		return false;
	});
	return false;
}

function removeOpenId(form) {
	callFunction(form,'removeOpenId','filters',true).then(()=>{ return false; });
}

function openIds(form) {
	callFunction(form,'getDetails','_popup_',false,'details','updateSelectionsOfThis').then(()=>{ newEntry(form,'',''); return false; });	
	//processForm(form,'../php/getDetails.php','_popup_',false,'details');
	return false;
}

function callJSFunction(_string,_onsubmit) {
	var _trash = document.getElementById('trash');
	var _trashForm = document.getElementById('trashForm');
	_trash.value = _string;
	_onsubmit(_trashForm,false);
	_trashForm.reset();
	return false;
}

function trash(key) {
	callJSFunction(key,addFilters);
	return false;
}

//call an PHP function with one string argument in $PARAMETER['trash']
function callPHPFunction(_arg,_function,_target,_classes) {
//	var _form = document.getElementById('trashForm');
//	var _input = document.getElementById('trash');
//	_input.value = _arg;
	callFunction(_arg,_function,_target,false,_classes,_function,_arg).then(()=>{ 
		if ( document.getElementById(_target) ) { myScrollIntoView(document.getElementById(_target)); }
		if (! _arg.classList || ! _arg.classList.contains('noreset') ) { _arg.reset(); }; //why reset at all? not appropriate for exportCSV function
		return false;
	})
	return false;
}

function removeContainingDiv(el) {
	var div = el.closest('div');
	//we have now always a "not" div, so at least 2 divs must remain
	//this is not true for FILESPATH selections: we also want to remove the only one and it is not a searchfield
	if ( div.parentNode.querySelectorAll('div.searchfield').length > 1 || div.parentNode.querySelectorAll('div.filesfield').length > 0) {
		//was > 2 and getElementsByTagName('div') 
		div.parentNode.removeChild(div);
	} else {
		if ( div.getElementsByTagName('input').length > 0 ) { div.getElementsByTagName('input')[0].value = ''; };
		if ( div.getElementsByTagName('select').length > 0 ) { div.getElementsByTagName('select')[0].value = ''; };
		if ( div.getElementsByTagName('textarea').length > 0 ) { div.getElementsByTagName('textarea')[0].value = ''; };
	}
	return false;
}

function reloadCSS(el) {
	if ( ! el ) { el = this; } else { el = el.contentWindow; }
	var fontSize = document.querySelector('input[name="_fontSize"]:checked').value;
	var colors = document.getElementById('colorsSelect').value;
	el.document.getElementById('cssFontSize').href = "/css/fontsize_"+fontSize+".css";
	el.document.getElementById('cssColors').href = "/css/config_colors_"+colors+".css";
	return false;
}

function editTable(form,tablename) {
	var table = form.getElementsByClassName['inputtable'][0];
	var old_tablename = table.value;
	table.value = tablename;
	callFunction(form,'getDetails','_popup_',false,'details','updateSelectionsOfThis').then(()=>{
		//processForm(form,'../php/getDetails.php','_popup_',false,'details');
		newEntry(form,'','');
		table.value = old_tablename;
		return false;
	});
	table.value = old_tablename;
	return false;
}

function _updateDateTime(id) {
	//'time' and 'date' have same length, so we do not need to distinguish
	var public_id = id.substr(0,id.length-4);
	var _input = document.getElementById(public_id);
	var _input_date = document.getElementById(public_id+'date');
	var _input_time = document.getElementById(public_id+'time');
	_input.value = _input_date.value+' '+_input_time.value;
}

function updateTime(el) {
	var now = new Date();
	var _time = el.closest('.section').getElementsByClassName('time')[0];
	var _hours = ( now.getHours() < 10 ) ? "0"+now.getHours() : now.getHours();
	var _minutes = ( now.getMinutes() < 10 ) ? "0"+now.getMinutes() : now.getMinutes();
	var _seconds = ( now.getSeconds() < 10 ) ? "0"+now.getSeconds() : now.getSeconds();
	_time.innerHTML = '<i class="fas fa-clock"></i> '+_hours+':'+_minutes+':'+_seconds;
	return false;
}

function validate(el,json)
{
	var _return = new Array();
	var form = el.closest('form');
	var edittypes = JSON.parse(json);
	var editfieldnames = Object.keys(edittypes);
	var value;
	for ( i = 0; i < editfieldnames.length; i++ ) {
		if ( ! form.querySelector('[name="'+editfieldnames[i]+'"]') ) { continue; }
		value = form.querySelector('[name="'+editfieldnames[i]+'"]').value;
		switch(edittypes[editfieldnames[i]]) {
/*			dates are validated by the browser by the input method; if you have to resort to this at a later time, do include an empty/00-00... value as valid!
			case 'DATE':
			case 'DATETIME': 
				if ( ! Date.parse(value) ) { _return.push(editfieldnames[i]); };
				break;
*/
			//case 'EMAIL': email is validated by the browser
			case 'PHONE':
				var regex = /^[0-9+\-() \/,]*$/;
				if ( ! regex.test(value) ) { _return.push(editfieldnames[i]); };
				break;
			default:
				break;
		}
	}
	return _return;
}

function colorInvalid(el,invalid) {
	var form = el.closest('form');
	var message = form.closest('.section').querySelector('.message');
	//uncolor all labels
	var alllabels = form.querySelectorAll('label[for]');
	for ( i = 0; i < alllabels.length; i++ ) {
		alllabels[i].style.color = 'inherit';
	}
	//remove all invalid message parts
	first = true;
	message.querySelectorAll('.dbMessage').forEach(function(dbMessage){
			if ( first ) { dbMessage.textContent = ''; first = false; } else { dbMessage.remove(); }
		});
	//color labels of invalid entries
	for ( i = 0; i < invalid.length; i++ ) {
		invalidel = form.querySelector('[name="'+invalid[i]+'"]');
		invalidlabel = form.querySelector('label[for="'+invalidel.id+'"]');
		invalidlabel.style.color = '#a00000';
		//put invalid entries into the message part
		var invalidmessage = document.createElement('div');
		invalidmessage.classList.add('dbMessage','false');
		invalidmessage.textContent += 'Ungültig: '+invalidlabel.textContent;
		message.appendChild(invalidmessage);
	}	
}

/* obsolete: replace by php-curl in db_functions...
 * 
function calAction(form,arg,responsetext) {
	var calendarData = JSON.parse(responsetext);
	if ( calendarData.calendarurl != '' && calendarData.calendarpwd != '' && calendarData.calendaruser != '' ) {
		_request = new XMLHttpRequest();
		_request.id = Math.floor(Math.random()*1000000000);
		_request.open("PUT",calendarData.calendarurl+'/'+_request.id+'.ics',true,calendarData.calendaruser,calendarData.calendarpwd);
		_request.setRequestHeader('Content-Type','text/calendar; charset=utf-8');
		_request.body = "BEGIN VCALENDAR\n";
		_request.body += "BEGIN VEVENT\n";
		_now = _date2calString(new Date());
		_request.body += "CREATED:"+_now+"\n";
		var _dates = form.querySelectorAll('input[type="date"]');
		var _times = form.querySelectorAll('input[type="time"]');
		var _datetimes = array();
		var _datetimesString = array();
		for ( i = 0; i < Math.min(_dates.length,_times.length,2); i++ ) {
			_datetimes[i] = new Date(_dates[i].value+" "+_times[i].value);
			_datetimesString[i] = _date2calString(_datetimes[i]);
		}

		_request.body += "END VCALENDAR";
		_request.send(request.body);
	}
}

function _date2calString(_datetime) {
	_datetimesString = new Object;
	_datetimesString.year = _datetime.getYear();
	_datetimesString.month = ( _datetime.getMonth() < 10 ) ? "0"+_datetime.getMonth() : _datetime.getMonth();
	_datetimesString.date = ( _datetime.getDate() < 10 ) ? "0"+_datetime.getDate() : _datetime.getDate();
	_datetimesString.hours = ( _datetime.getHours() < 10 ) ? "0"+_datetime.getHours() : _datetime.getHours();
	_datetimesString.minutes = ( _datetime.getMinutes() < 10 ) ? "0"+_datetime.getMinutes() : _datetime.getMinutes();
	_datetimesString.value = _datetimesString.year+_datetimesString.month+_datetimesString.date+"T"+_datetimesString.hours+_datetimesString.minutes+"00";
	return _datetimesString;
}
*/

function printResults(form,arg,responsetext) {
	_print = window.open("/html/print.html","print");
	_print.document.body.onload = function () {
		if (form.closest('.section')) {
			el = form.closest('.section');
		} else {	
			el = form.parentNode;
		}
		el_print = _print.document.importNode(el,true);
		_print.document.body.appendChild(el_print);
		_print.tinyMCEinit();
	}
}

function improveLayout() {
//	var nextlevel = document.getElementsByClassName('nextlevel').
}

//this is a callback function for callFunction(), so has to have arguments form, arg, responsetext; we only need arg, which is the element the function
//is called on
function scrollTo(form,arg,text) {
	if ( arg.closest('.popup_wrapper') ) { arg.closest('.popup_wrapper').scrollIntoView({behavior: "smooth"}); };
}

//remove oldest for first five levles, newest for older ones
function rotateHistory() {
	var sidebar = document.getElementById('sidebar');
	var el = document.getElementById('history');
	var level = parseInt(el.querySelector('#history_level').innerText);
	var tmpel = document.createElement('div');
	tmpel.innerText = sidebar.innerHTML;
	// do nothing if user has not changed historic settings (apart from time stamps) //still wrong: random ids are different: how to eliminate?
	if ( el.querySelector('#history'+level) && (
		el.querySelector('#history'+level).innerText.replace(/[0-9]{2}:[0-9]{2}:[0-9]{2}/g,'').replace(/(id|for)=*.{0,40}[0-9]{1,9}/g,'') == tmpel.innerText.replace(/[0-9]{2}:[0-9]{2}:[0-9]{2}/g,'').replace(/(id|for)=*.{0,40}[0-9]{1,9}/g,'')
		)
	) { return; }
	// for debug only
/*	if ( el.querySelector('#history'+level) ) {
		_eq = true;
		cp1 = el.querySelector('#history'+level).innerText.replace(/[0-9]{2}:[0-9]{2}:[0-9]{2}/g,'');
		cp2 = tmpel.innerText.replace(/[0-9]{2}:[0-9]{2}:[0-9]{2}/g,'');
		for ( var i = 0; i < cp1.length; i++ ) {
			if ( ! _eq ) { continue; }
			if ( cp1.substring(0,i).localeCompare(cp2.substring(0,i)) != 0 ) {
				_eq = false;
				console.log(cp1.substring(0,i));
				console.log(cp2.substring(0,i));
			} 
		}
	}
*/
	//
	if ( level < 6 ) {
		if ( el.querySelector('#history11') ) { el.querySelector('#history11').remove(); };
		for ( var i = 10; i >= level ; i-- ) {
			if ( el.querySelector('#history'+i) ) { el.querySelector('#history'+i).id = 'history'+(i+1); } 
		}
		var hist1 = document.createElement('div');
		hist1.id = "history"+level;
		hist1.innerText = document.getElementById('sidebar').innerHTML;
		el.appendChild(hist1);
		showHistoryLevel(level);
	} else {
		if ( el.querySelector('#history1') ) { el.querySelector('#history1').remove(); };
		for ( var i = 2; i < level ; i++ ) {
			if ( el.querySelector('#history'+i) ) { el.querySelector('#history'+i).id = 'history'+(i-1); } 
		}
		var hist1 = document.createElement('div');
		var newlevel = level - 1;
		hist1.id = "history"+newlevel;
		hist1.innerText = document.getElementById('sidebar').innerHTML;
		el.appendChild(hist1);		
		document.getElementById('history_level').innerText = newlevel;
		showHistoryLevel(newlevel);
	}
}

function restoreHistory(version) {
	if ( version == -1 ) { version = Math.min(parseInt(document.getElementById('history_level').innerText) + 1, 11); }
	if ( version == 0 ) { version = Math.max(parseInt(document.getElementById('history_level').innerText) - 1, 1); }
	if ( ! document.getElementById('history'+version) ) { return; }
	document.getElementById('sidebar').innerHTML = document.getElementById('history'+version).innerText;
	document.getElementById('history_level').innerText = version;
	callFunction(document.getElementById('formFilters'),'applyFiltersOnlyChangeConfig').then(()=>{
		showHistoryLevel(version);
	});
}

function showHistoryLevel(level) {
	level = parseInt(level);
	var hist = document.getElementById('history');
	var show = document.getElementById('showHistory');
	var currentlevel = hist.querySelector('#history_level').innerText;
	for ( var i = 1; i < 12; i++ ) {
		if ( hist.querySelector('#history'+i) ) { show.querySelector('#showHistory'+i).classList.remove('hidden'); show.querySelector('#showHistory'+i).classList.add('inline'); } else { show.querySelector('#showHistory'+i).classList.remove('inline'); show.querySelector('#showHistory'+i).classList.add('hidden'); }
		if ( i == level ) { show.querySelector('#showHistory'+i).classList.add('marked'); } else { show.querySelector('#showHistory'+i).classList.remove('marked'); }
	}
	if ( hist.querySelector('#history'+(level+1)) ) { document.getElementById('showHistoryBack').classList.remove('disabled'); } else { document.getElementById('showHistoryBack').classList.add('disabled'); };
	if ( hist.querySelector('#history'+(level-1)) ) { document.getElementById('showHistoryForward').classList.remove('disabled'); } else { document.getElementById('showHistoryForward').classList.add('disabled'); };
}

//raise or lower table hierarchies (pm=1 or -1)
function hierarchy(el,pm) {
	var max = 1;
	if ( el.parentElement.previousElementSibling ) { max = parseInt(el.parentElement.previousElementSibling.querySelector('.hierarchy').value) + 1; }
	var _thisvalue = el.parentElement.querySelector('.hierarchy').value;
	el.parentElement.querySelector('.hierarchy').value = Math.max(1,Math.min(parseInt(_thisvalue)+parseInt(pm),max));
	el.parentElement.querySelector('span').style.width = el.parentElement.querySelector('.hierarchy').value+"em";
}
