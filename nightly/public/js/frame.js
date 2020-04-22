function adaptPath(el) {
	switch(el.value) {
		case '':
		case '[bitte wählen]': 
			document.getElementById('fullpath').value = document.getElementById('label').innerText;
			break;
		case '[zurück]':
			document.getElementById('fullpath').value = document.getElementById('label').innerText.substring(0,document.getElementById('label').innerText.slice(0,-1).lastIndexOf('/'));
			break;			
		default:
			document.getElementById('fullpath').value = document.getElementById('label').innerText + el.value;
			break; 
	}
	el.closest('form').submit();
}

function adaptOptions(el) {		
	el.parentNode.querySelectorAll('option').forEach(function (_option) {
			if ( _option.value.indexOf(el.value) != 0 ) { _option.setAttribute('hidden',true); } else { _option.removeAttribute('hidden'); }
		});
}
