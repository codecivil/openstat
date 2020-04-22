function tinyMCEinit() {
	tinyMCE.init({
	   selector: 'textarea.editor',  //Change this value according to your HTML
	   width: "40rem",
	   height: "40rem",
       plugins: "lists",
	   toolbar: "undo redo | styleselect | bold italic | numlist | bullist | link image | forecolor | backcolor",
	   browser_spellcheck: true,
	   gecko_spellcheck: true,
	});
}
