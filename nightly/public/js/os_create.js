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
	console.log(filename.replace(regex,''));
	document.querySelectorAll('.sqlfile').forEach(function(sqlfilediv){sqlfilediv.classList.add('hidden');});
	document.querySelector('#'+filename.replace(regex,'')).classList.remove('hidden');	
}
