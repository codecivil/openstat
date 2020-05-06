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
		document.getElementById(id).scrollIntoView();
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
function _close(el,local) { 
	var popup_wrapper = el.closest('.popup_wrapper');
	if ( ! popup_wrapper ) { popup_wrapper = el.closest('.imp_wrapper'); };
	if (popup_wrapper.getElementsByClassName('_table_')[0] && ( !(local) || ! local ) ) {
		var table = popup_wrapper.getElementsByClassName('_table_')[0].innerText;
		var id = popup_wrapper.getElementsByClassName('_id_')[0].innerText;
		if ( id != '') { callJSFunction('{"id_'+table+'":"'+id+'"}',removeOpenId); };
	}
	popup_wrapper.parentNode.removeChild(popup_wrapper);
	return false;
}

function callFunction(form,phpfunction,id,add,classes,callback,arg) {
	//do not callFunction again before completing this call
	if ( document.body.style.cursor == 'progress' ) { setTimeout(function(){ callFunction(form,phpfunction,id,add,classes,callback,arg); },200); return false; }
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
			if (add) { 
				el.innerHTML += _request.responseText;
			} else {
				el.innerHTML = _request.responseText;
			}
			el.className += " "+classes; tinyMCEinit();
			if ( ! document.getElementById('sidebar').contains(el) && ! document.getElementById('results_wrapper').contains(el) ) { el.closest('.popup_wrapper').scrollIntoView(); }
			document.body.style.cursor = 'auto';	
			if (callback) { return window[callback](form,arg,_request.responseText); } else { return false; };	
		}
	} else {
		_request.onload = function() { 	document.body.style.cursor = 'auto'; if (callback) { console.log(callback); return window[callback](form,arg,_request.responseText); } else { return false; }; }
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
	if ( add == false ) 
	{
		callFunction(form,'removeFromConfig','sidebar',true);
	}
	else 
	{
		callFunction(form,'addToConfig');
	} 
	form.reset();
//	processForm(document.getElementById('formFilters'),'../php/updateSidebar.php','sidebar');
	callFunction(document.getElementById('formFilters'),'updateSidebar','sidebar');
	return false;
}

function removeOpenId(form) {
	callFunction(form,'removeOpenId','filters',true);
}

function openIds(form) {
	callFunction(form,'getDetails','_popup_',false,'details');	
	//processForm(form,'../php/getDetails.php','_popup_',false,'details');
	return false;
}

function callJSFunction(_string,_onsubmit) {
	var _trash = document.getElementById('trash');
	var _trashForm = document.getElementById('trashForm');
	_trash.value = _string;
	_onsubmit(trashForm,false);
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
	callFunction(_arg,_function,_target,false,_classes,_function,_arg);
	if (! _arg.classList.contains('noreset') ) { _arg.reset(); }; //why reset at all? not appropriate for exportCSV function
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
	callFunction(form,'getDetails','_popup_',false,'details');
	//processForm(form,'../php/getDetails.php','_popup_',false,'details');
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
				var regex = /^[0-9+\-() ]*$/;
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
	var alllabels = form.querySelectorAll('label[for]');
	for ( i = 0; i < alllabels.length; i++ ) {
		alllabels[i].style.color = 'inherit';
	}
	for ( i = 0; i < invalid.length; i++ ) {
		invalidel = form.querySelector('[name="'+invalid[i]+'"]');
		invalidlabel = form.querySelector('label[for="'+invalidel.id+'"]');
		invalidlabel.style.color = '#a00000';
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
		el = form.parentNode;
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
	arg.closest('.popup_wrapper').scrollIntoView({behavior: "smooth"});
}

