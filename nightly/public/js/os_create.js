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
