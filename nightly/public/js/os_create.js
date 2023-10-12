function _onActionCreate(val,el) {
	if (el) { document.getElementById("db_"+el).value = "none"; }
// make new query at every change; uncomment to restrict to filter removals
//	if ( val == "none" ) {
		if ( val != "delete" || confirm("Wollen Sie den Eintrag wirklich löschen? Davon abhängige Einträge werden ebenfalls gelöscht.") ) {
			console.log(val);
			document.getElementById('db_options').submit();
		}
//	}
}

function _displayFile(filename) {
	regex = /\./g;
	document.querySelectorAll('.sqlfile').forEach(function(sqlfilediv){sqlfilediv.classList.add('hidden');});
	document.querySelector('#'+filename.replace(regex,'')).classList.remove('hidden');	
}

function importScript(_lineno) {
	if ( _lineno == undefined ) { _lineno = 0; }
	_return = { 'dbMessage': '', 'dbMessageGood':'true' };
	try { scriptline = document.querySelector('.scriptfile:not(.hidden) .scriptline').textContent } catch(err) { console.log("stopped"); return false; }
	_lineno += 1;
	//comments with # or --
	if ( scriptline.trim().match(/^#|^--/) == null ) {
		if ( scriptline.trim().match(/^\?/) == null ) { 
			_return.dbMessage += "Zeile "+_lineno+" ist keine Anfrage; ignoriert<br />";
			_return.dbMessageGood = 'false';
		}
		let _req = new XMLHttpRequest();
		_req.onload = function() {
			if ( _return.dbMessageGood == 'true' ) {
				if ( _req.responseText.match('nicht erfolgreich') == null ) {
					_return.dbMessage = "Zeile "+_lineno+" ausgeführt.";
				} else {
					_return.dbMessage = "Zeile "+_lineno+" konnte nicht ausgeführt werden.";
					_return.dbMessageGood = 'false';
				}
			}
			//remove processed line
			document.querySelector('.scriptfile:not(.hidden) .scriptline').remove();
			//add result to message
			document.querySelector('#message').innerHTML += '<div class="message '+_return.dbMessageGood+'">'+_return.dbMessage+'</div>';
			//recurse
			importScript(_lineno);
		}
		_req.open("GET",scriptline);
		_req.send();
	} else {
		//ignore comments
		//remove comment line
		document.querySelector('.scriptfile:not(.hidden) .scriptline').remove();
		//recurse
		importScript(_lineno);
	}
}
