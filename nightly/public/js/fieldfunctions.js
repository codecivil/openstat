//emailTo js function
// success: string
function emailTo(success) {
	alert(success);
}

//createFromTemplate js function
// _filestring: string of an array
function createFromTemplate(_filestring) {
    console.log(_filestring);
    let _filearray = JSON.parse(_filestring);
    _filearray.forEach(_file => {
        _file = JSON.parse(_file);
        let _download = document.createElement('a');
        _download.setAttribute('href', _file.data);
        _download.setAttribute('download', _file.filename);
        _download.click();
    });
}

function createSubsequentEntry(_result) {
    //display it in alsoimportant? Or how to get message section of orig entry?
    document.querySelector('#alsoimportant').innerHTML = _result;
}
