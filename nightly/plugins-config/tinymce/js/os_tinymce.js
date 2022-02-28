function tinyMCEinit() {
	tinyMCE.init({
	   selector: 'textarea.editor',  //Change this value according to your HTML
	   width: "min(max(40rem,60%),calc(97% - 12rem))",
	   height: "40rem",
	   plugins: "lists",
	   toolbar: "undo redo | styleselect | bold italic | numlist | bullist | link image | forecolor | backcolor",
	   browser_spellcheck: true,
	   entity_encoding: "raw", //default is "named", but Umlaute in HTML entities is not supported by (ODF1.2) XML, so throws error in refuKey-Export
  	   gecko_spellcheck: true,
	});
}
