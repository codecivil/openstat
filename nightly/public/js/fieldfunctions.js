//emailTo js function
// success: string
function emailTo(success) {
	alert(success);
}

//createFromTemplate js function
// _filestring: string
function createFromTemplate(_filestring) {
	let _file = JSON.parse(_filestring);
    let _download = document.createElement('a');
    _download.href = _file.data;
    _download.download = _file.filename;
    _download.click();
}
