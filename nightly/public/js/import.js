function similarity_asym(string1,string2) {
	// compute maximal common substrings, sum length squares; compare to square of minimal string length
	// eg., abcd abcbcd, (3²+3²)/(4²) > 1; is this ok?
	// product of lgth: 18/24 < 1
	// two mcs of length a,b intersect in l charactersin string 1 => length1 > a+b-l, length2 > a+b-l , correct for intersection:
	// 2(a+b-l)^2-l^2
	// e.g abab, baba; mcs: aba, bab, 18>16,  
	
	//mcs starting with string1
	//throw no error if strings are empty: not really needed, is it?
	//string1 = string1 + ' ';
	//string2 = string2 + ' ';
	//
	string1 = string1.substr(0,1)+string1.substr(1).toLowerCase();
	string2 = string2.substr(0,1)+string2.substr(1).toLowerCase();
	var sim = 0;
	var _index = 0;
	while ( _index < string1.length ) {
		var contained = true;
		var _length = 0;
		var _index2new = 0;
		while ( contained ) {
			_index2 = _index2new;
			_length += 1;
			_index2new = string2.indexOf( string1.substr(_index,_length) );
			if ( _index2new == -1 || _length + _index > string1.length ) { contained = false; }		
		}
		sim += ( ( string1.length - _index ) * ( string1.length - _index ) - ( string1.length - _index - _length + 1 ) * ( string1.length - _index - _length + 1 ) ) * ( ( string2.length - _index2 ) * ( string2.length - _index2 ) - ( string2.length - _index2 - _length + 1 ) * ( string2.length - _index2 - _length + 1 ) ) / ( 1+Math.log(string1.length/string2.length)*Math.log(string1.length/string2.length) )
		_index += _length;
	}
	sim = sim/( (string1.length)*(string1.length)*(string2.length)*(string2.length) );
	return sim;
}

//the symmetric version
function similarity(string1,string2) { return Math.max(similarity_asym(string1,string2),similarity_asym(string2,string1)); }

//asymmetric! every item of array1 has to be matche, maximal one match for array2
function match_old(array1,array2,threshold) {
	//console.log("("+array1+")\n MATCHING TO\n("+array2+")\n");
	if ( array1.length > array2.length ) { //console.log("first argument is too long"); return -1; 
		}
	for ( var i = 0; i < array1.length; i++ ) {
		sim = 0; sim2 = 0;
		_splice = -1;
		for ( var j = 0; j < array2.length; j++ ) {
			sim2 = Math.max(sim, similarity(array1[i],array2[j]));
			if ( sim2 > sim ) { match[i] = array2[j]; _splice = j; }
			sim = sim2;
		}
		array2.splice(_splice,1);
		//console.log(array1[i]+" => "+match[i]);
	}
}

function match(array1,array2,threshold) {
	if ( threshold  == undefined ) { threshold = 0; }
	//console.log("("+array1+")\n MATCHING TO\n("+array2+")\n");
	options = new Array;
	_count = 0;
	//if ( array1.length > array2.length ) { //console.log("first argument is too long"); return -1; }
	for ( var i = 0; i < array1.length; i++ ) {
		for ( var j = 0; j < array2.length; j++ ) {
			options[_count] = new Object;
			options[_count].rank = similarity(array1[i],array2[j]);
			options[_count].compare = [i,j];
			_count += 1;
		}
	}
	options.sort((x,y) => (y.rank-x.rank));
	matched1 = new Array; matched2 = new Array; //_match = JSON.parse(JSON.stringify(array1)); 
	_match = new Array;
	for (var k = 0; k < options.length; k++ ) {
		if ( matched1[options[k].compare[0]] != "TRUE" && matched2[options[k].compare[1]] != "TRUE" && options[k].rank > threshold ) {
			_match[options[k].compare[0]] = options[k].compare[1];
			matched1[options[k].compare[0]] = "TRUE";
			matched2[options[k].compare[1]] = "TRUE";
		}
	}
	//for debug only
	////console.log(options);
	for ( var i = 0; i < array1.length; i++ ) {
		if ( _match[i] == undefined ) {
			_match[i] = -1; //feed back that there is no proper match
//			_match[i] = 0; //take first (default) option as given if there is no match for the threshold
			//console.log(array1[i]+" => "+array2[_match[i]]);
		}
	}
	//
	return _match;
}

function matchHeaders(_importel,_files,_targetdiv) {
	//read csv column names
	var el = _importel.closest('.popup');
	var _remove = false;
	var _oldmatch = new Array();
	//remove all old singlematches apart form the first (was dummy in first run)
	_singlematches = el.querySelectorAll('.singlematch')
	_singlematches.forEach(function(singlematch,j,a){
		_oldmatch[singlematch.querySelector('label').textContent] = singlematch.querySelector('select').value;
		if ( _remove ) { singlematch.parentNode.removeChild(singlematch); }
		_remove = true;
	});
	el.querySelector('.headersubmitlabel').removeAttribute('hidden');
	_fileheaders = new Array();
	for ( var i = 0; i <  _files.length; i++ ) {
		var _result = function(i) {
			var r = new FileReader();
			var _file = _files[i];
			r.onload = function () { 
				contents = r.result;
				headers = contents.split(/\r?\n/g)[0].replace(/^\"/g,'').replace(/\"$/g,'');
				_fileheaders = _fileheaders.concat(headers.split('","'));
				_fileheaders = _fileheaders.sort().filter(function(el,index,a){return index===a.indexOf(el)}); //sort? and uniq for arrays (see https://stackoverflow.com/questions/4833651/javascript-array-sort-and-unique)  
				_nowmatch(_importel,_files,_fileheaders,i,_oldmatch);
			}
			r.readAsText(_file);
			//console.log("Fileheaders:");			
		} (i);
	}
	el.querySelector('.headermatch').removeAttribute('hidden');
}

function _nowmatch(_importel,_files,_fileheaders,i,_oldmatch) {
	//this script is callback for every onload of an import file; only react for last import!
	if ( _files.length != i+1 ) { return; }
	var el = _importel.closest('.popup');
	//console.log(_fileheaders);
	//get the table headers
	_tableheaders = JSON.parse(el.querySelector('.headers').innerText)['keyreadable'];
	_match = match(_fileheaders,_tableheaders);
	//console.log(_match);
	for ( var j = 0; j < _match.length; j++ ) {
		if ( _fileheaders[j] == "" ) { continue; }
		var clone_el = el.getElementsByClassName('singlematch')[0];
		var parent = el.querySelector('.formHeaderMatch');
		clone = clone_el.cloneNode(true);
		clone.removeAttribute('hidden');
		clone.getElementsByTagName('label')[0].textContent = _fileheaders[j];
		if ( _match[j] != undefined ) { clone.querySelector('option[value="'+_match[j]+'"]').setAttribute('selected',true); }
		if ( _oldmatch[_fileheaders[j]] ) { 
			clone.querySelector('option[value="'+_oldmatch[_fileheaders[j]]+'"]').setAttribute('selected',true);
			clone.classList.add('formermatch');	
		}
		parent.insertBefore(clone,parent.querySelector('input[type="submit"]'));
	}
}

//just for psychological reasons: think twice before you press import
function checkHeaders(_importel,_form,_targetdiv) {
	//check if contents match edittypes?
	var el = _importel.closest('.popup');
	el.querySelector('.submitimportlabel').classList.remove('disabled');
	el.querySelector('.headermatch').removeAttribute('hidden');
	el.querySelector('.importnow').setAttribute('hidden',true);
	//document.getElementById('importnow').scrollIntoView();
	el.querySelector('.submitImport').removeAttribute('disabled');
}

//~ function mainTableComplete(el) {
	//~ var _files = document.getElementById('importFile').files;
	//~ var _tableheadersfull = JSON.parse(document.getElementById('headers').textContent);
	//~ var _tables = _tableheadersfull['table'];
	//~ _tables = _tables.filter(function(el,ii,a){if (a[ii] !== a[ii+1]) { return true; } else { return false; }; });
	//~ try { var _gotID = JSON.parse(el.closest('.popup').querySelector('.gotID').textContent); } catch(err) { var _gotID = new Object(); }
	//~ //console.log(Object.keys(_gotID).length+' '+_files.length+' * '+_tables.length );
	//~ if ( Object.keys(_gotID).length == _files.length * _tables.length && el.closest('.popup').querySelector('.importFinished').textContent == '') {
		//~ importJS(el,true);
		//~ el.closest('.popup').querySelector('.importFinished').textContent = 'yes,maam';
	//~ } 
//~ }

// subtables = false: import into main table and note import IDs, after this into subtables
// subtables = true: import into subtables using main table IDs (not used separately)
function importJS(el,subtables) {
	var _importel = el.closest('.popup');
	_importel.querySelector('.headermatch').setAttribute('hidden',true);
	_importel.querySelector('.importnow').removeAttribute('hidden');
	if ( typeof subtables == 'undefined' || ! subtables ) { 
		var l_min = 0; var l_max = 1;
		//initialize: gotID only if subtables=false, since it is called after that with true and needs the IDs
		el.closest('.popup').querySelector('.gotID').textContent = ''; //(re-)initialize gotID	
		el.closest('.popup').querySelector('.importFinished').textContent = ''; // and importFinished
	} else { 
		var l_min =1; var l_max = 1000;
	};
	_importel.querySelector('.importSuccess').removeAttribute('hidden');
	//check if numbers match numbers
	//for LIST: take closest match, i.e. match([csv-entry],[LIST options]);
	//then import into correct table...
	_fileheaders = new Array();
	_matchedIndexAll = new Array();
	_files = _importel.querySelector('.importFile').files;
	_tableheadersfull = JSON.parse(_importel.querySelector('.headers').textContent);
	_singlematches = [..._importel.getElementsByClassName('singlematch')];
	//debug: "shift is not a function": why?
	_singlematches.shift(); //get rid of the dummy; is the order of appearance respected? if no, then change here...
	_tables = _tableheadersfull['table'];
	_tables = _tables.filter(function(el,ii,a){if (a[ii] !== a[ii+1]) { return true; } else { return false; }; });
	for ( var i = 0; i<_singlematches.length; i++ ) {
		_fileheaders.push(_singlematches[i].querySelector('label').textContent);
		_matchedIndexAll.push(_singlematches[i].querySelector('select').value);
	}
	//console.log(_matchedIndexAll);
	//determine number of insert lines
	_inserts = 0;
	for ( var i = 0; i < _files.length; i++ ) {
		_result = function(i) {
			var r = new FileReader();
			r.onload = function () { 
				contents = r.result.split(/\r?\n/g).filter(Boolean); //removes empty lines in this case
				//console.log(contents.length);
				_inserts += contents.length - 1;
				//console.log('inserts:'+_inserts);
			}
			r.readAsText(_files[i]);
		}(i);
	};
	//
	for ( var i = 0; i <  _files.length; i++ ) {
		_result = function(i) {
			_matchedIndex = new Array();
			var _mainIDel = el.closest('.popup').querySelector('.gotID');
			var _file = _files[i];
			var r = new FileReader();
			var oldrow = new Array();
			r.onload = function () { 
				contents = r.result.split(/\r?\n/g);
				headers = contents.shift().split('","'); //separates headers and contents
//				_parameter = new Object();
				for ( var jj = 0; jj < headers.length; jj++ ) {
					headers[jj] = headers[jj].replace(/\"/g,'');
					oldrow[jj] = ''; //initializes oldrow properly
					if ( _fileheaders.indexOf(headers[jj]) != -1 ) {
						_matchedIndex[jj] = _matchedIndexAll[_fileheaders.indexOf(headers[jj])];
					}
				}
				for ( var j = 0; j < contents.length; j++ ) { //loop through lines of file
					if ( contents[j] == '' ) { continue; }
					if ( contents.length > 2 ) { zeile = ' Z. '+(j+1)	; } else { zeile = ''; }
					var row = contents[j].split('","');
					for ( var l = l_min; l < Math.min(l_max,_tables.length); l++ ) { //loop through tables
						var _table = _tables[l];
						_parameter = new Object();
						_parameter['table'] = _table;
						//define attribution to main table
						//console.log('main: '+_mainIDel.textContent);
						if ( _mainIDel.textContent != '' ) {
							if ( _mainIDel.textContent == '-1' ) {
								var _problems = _importel.querySelector('.importProblems');
								var _problemsDetails = _importel.querySelector('.importProblemsDetails');
								_problems.innerText = parseInt(_problems.innerText) + 1;
								_problemsDetails.innerText += "Zuordnung zur Haupttabelle kann nicht durchgeführt werden: "+_file.name+" Z. "+(zeile+1)+";"; 
							} else {
								_mainID = JSON.parse(_mainIDel.textContent)[i+'_'+j+'_'+0];
								for (var key in _mainID ) {
									_parameter[_table+'__'+key] = _mainID[key];
								}
							}
						}
						//copy old main table data if empty
						if ( l == 0 ) {
							//determine if main table data are all empty
							var _maintablehasdata = false;
							for ( var kk = 0; kk < row.length; kk++ ) { //loop through columns matching the table
								row[kk] = row[kk].replace(/^\"/g,'').replace(/\"$/g,''); //do not twice the single/double quote replacement!
								if ( _tableheadersfull['table'][_matchedIndex[kk]] == _table &&  row[kk] != '' ) {
									//note if row[k] is not empty, so you use it; otherwise you take the old row for that table
									_maintablehasdata = true;
								}
							}
							//copy old data when no data
							if ( ! _maintablehasdata && j > 0 ) {
								for ( var kk = 0; kk < row.length; kk++ ) { //loop through columns matching the table
								//not necessary:	oldrow[kk] = oldrow[kk].replace(/^\"/g,'').replace(/\"$/g,'').replace(/\'/g,'"');
									if ( _tableheadersfull['table'][_matchedIndex[kk]] == _table ) {
										row[kk] = oldrow[kk]; 
									}
								}
							} else {
								oldrow = JSON.parse(JSON.stringify(row)); //works also for multiple empty lines
							}
						}
						//continue if table has no data
						var _tablehasdata = false;
						for ( var kk = 0; kk < row.length; kk++ ) { //loop through columns matching the table
							row[kk] = row[kk].replace(/^\"/g,'').replace(/\"$/g,''); //do not twice the single/double quote replacement!
							if ( _tableheadersfull['table'][_matchedIndex[kk]] == _table &&  row[kk] != '' ) {
								//note if row[k] is not empty, so you use it; otherwise you take the old row for that table
								_tablehasdata = true;
							}
						}
						if ( ! _tablehasdata ) { continue; }
						//
						function _processDate(_string) {
							var _date = _string.split('.');
							if ( _date[0].length < 2 ) { _date[0] = "0"+_date[0]; };
							if ( _date[1] && _date[1].length < 2 ) { _date[1] = "0"+_date[1]; };
							if ( _date[2] ) { _tmpdate = _date[2].split(' '); _date[2] = _tmpdate[0]; if ( _tmpdate[1] ) { _date[3] = ' '+_tmpdate[1]; } else { _date[3] = ''; }; }
							if ( _date[2] && _date[2].length < 3 ) { 
								if ( (new Date()).getFullYear() - _date[2] < 2000 ) {
									_date[2] = "19"+_date[2]; //was [0] on rhs; just a mistake?
								} else {
									_date[2] = "20"+_date[2]; //was [0] on rhs; just a mistake?
								}
							};
							if ( _date[2] ) { _string = _date[2]+'-'+_date[1]+'-'+_date[0]+_date[3]; }
							return _string;
						}
						//
						for ( var k = 0; k < row.length; k++ ) { //loop through columns matching the table
							//handle inner quotation marks (export makes inner double to inner single, so reverse here)
							//doing it twice: does it hurt?
							row[k] = row[k].replace(/^\"/g,'').replace(/\"$/g,'').replace(/\'/g,'"');
							//console.log(k+': '+headers[k]+'_'+_tableheadersfull['keyreadable'][_matchedIndex[k]]);
							/*
							 * to do: import compound fields
							 * until now: import only in the data model format possible
							 */
							var _cmp_lgth = 1;
							if ( _tableheadersfull['table'][_matchedIndex[k]] == _table ) {
								//format compound entries separated by '_+_'
								if ( _tableheadersfull['edittype'][_matchedIndex[k]].indexOf(" + ") > -1 ) {
									_cmp_lgth = _tableheadersfull['edittype'][_matchedIndex[k]].split(' + ').length;
									var _choices = new Array();
									try { _choices = JSON.parse(row[k]); } catch(err) { 
										_choices_cmp1 = row[k].split('|');
										_choices_cmp2 = new Array();
										_choices_cmp1.forEach(function(_choice){
											var _choices_add = _choice.split('_+_');
											for ( var ci = 0; ci < _cmp_lgth; ci++ ) {
												if ( ! _choices[ci] ) { _choices[ci] = new Array(); }
												if ( ! _choices_add[ci] ) { _choices_add[ci] = ''; }
												_choices[ci].push(_choices_add[ci]); 
											}
										});
									};
									row[k] = JSON.stringify(_choices);
								}
								//save full row before looking at components
								var currentrow = row[k];
								var currentedittype = _tableheadersfull['edittype'][_matchedIndex[k]];
								var currentallowedvalues = new Array();
								if ( _tableheadersfull['allowed_values'][_matchedIndex[k]] ) { currentallowedvalues = _tableheadersfull['allowed_values'][_matchedIndex[k]]; }
								var newrow_array = new Array();
								for ( var ci = 0; ci < _cmp_lgth; ci++ ) {
									if ( _cmp_lgth > 1 ) { 
										row[k] = JSON.stringify(JSON.parse(currentrow)[ci]);
										_tableheadersfull['edittype'][_matchedIndex[k]] = currentedittype.split('; ')[0].split(' + ')[ci]+'; MULTIPLE';
										if ( currentallowedvalues[ci] ) { _tableheadersfull['allowed_values'][_matchedIndex[k]] = currentallowedvalues[ci]; }
									}
									//format multiple entries (separated by '|')
									//removed temporarily condition '&& _tableheadersfull['edittype'][_matchedIndex[k]].indexOf(" + ") == -1 ' for special import job...								
									if ( _matchedIndex[k] && ( _tableheadersfull['edittype'][_matchedIndex[k]].indexOf("MULTIPLE") > -1 || _tableheadersfull['edittype'][_matchedIndex[k]] == "CHECKBOX" ) ) {
										var _choices;
										try { _choices = JSON.parse(row[k]); } catch(err) { _choices = row[k].split('|'); };
										//was: 																		 == "LIST; MULTIPLE"
										//console.log('edittype: '+k+' '+_tableheadersfull['edittype'][_matchedIndex[k]].indexOf("LIST"));
										if ( _matchedIndex[k] && ( _tableheadersfull['edittype'][_matchedIndex[k]].indexOf("LIST") == 0 || _tableheadersfull['edittype'][_matchedIndex[k]] == "CHECKBOX" ) ) {
											for ( var c = 0; c < _choices.length; c++ ) {
												var _matchthis = ( _choices[c] != '' ) ? _choices[c] : '*';
												var _bestindex = match([_matchthis],_tableheadersfull['allowed_values'][_matchedIndex[k]]);
												if ( _bestindex == -1 ) {
													_choices[c] = '';
												} else {
													_choices[c] = _tableheadersfull['allowed_values'][_matchedIndex[k]][_bestindex];
												}
											}
										}
										row[k] = JSON.stringify(_choices);
										//console.log(k+': '+row[k]);									
									}
									//select closest value match of (non-multple) LISTs; does not yet work for compund fields with list not on first component		
									if ( _matchedIndex[k] && _tableheadersfull['edittype'][_matchedIndex[k]] == "LIST" ) {
										var _matchthis = ( row[k] != '' ) ? row[k] : '*';
										//console.log(_matchthis);
										//console.log(k+': '+row[k]);
										var _bestindex = match([_matchthis],_tableheadersfull['allowed_values'][_matchedIndex[k]]);
										if ( _bestindex == -1 ) {
											row[k] = '';
										} else {
											row[k] = _tableheadersfull['allowed_values'][_matchedIndex[k]][_bestindex];
										}
										//console.log(k+': '+row[k]);
									}
									if ( _tableheadersfull['edittype'][_matchedIndex[k]].indexOf("DATE") == 0 ) {
										var _choices;
										try { _choices = JSON.parse(row[k]); } catch(err) { _choices = row[k].split('|'); };
										for ( var c = 0; c < _choices.length; c++ ) {
											_choices[c] = _processDate(_choices[c]);
										}
										if ( _tableheadersfull['edittype'][_matchedIndex[k]].indexOf("MULTIPLE") >= 0 ) {
											row[k] = JSON.stringify(_choices);
										} else {
											row[k] = _choices[0];
										}
										//console.log(row[k]); //debug only
									}
									if ( _cmp_lgth > 1 ) { newrow_array[ci] = JSON.parse(row[k]); }
								}
								if ( _cmp_lgth > 1 ) { row[k]= JSON.stringify(newrow_array); _tableheadersfull['edittype'][_matchedIndex[k]] = currentedittype; }
								_parameter[_tableheadersfull['table'][_matchedIndex[k]]+'__'+_tableheadersfull['keymachine'][_matchedIndex[k]]] = row[k];
							}
						}
						//
						//import according to tables; get id of first insert in order to attach it to subsequent tables (--> dbAction "getID")
						// uniquify (ES6 filters...) _tableheadersfull['table'] for this purpose
						//callFunction(form,phpfunction,id,add,classes,callback,arg)
//						_parameter['dbAction'] = 'getID'; 
//						document.getElementById('trash').value = JSON.stringify(_parameter);
//						callFunction('_','dbAction','importSuccess',true,'import','displayImportSuccess',_file.name);
						_arg = new Object;
						_arg.el = el;
						_arg.filescount = _files.length;
						_arg.tablescount = _tables.length;
						_arg.linescount = _inserts;
						_arg.file = i;
						_arg.line = j;
						_arg.table = l;
						_arg.log = _file.name+zeile+" ("+_tableheadersfull['tablemachine2readable'][_table]+")";
						_parameter['dbAction'] = 'insertIfNotExists'; 
						_arg.parameter = _parameter;
						document.getElementById('trash').value = JSON.stringify(_parameter);
						_importSuccessHidden = _importel.querySelector('.importSuccessHidden').id;
						callFunction('_','dbAction',_importSuccessHidden,true,'import','displayImportSuccess',_arg);
//						_parameter['dbAction'] = 'getID'; 
//						document.getElementById('trash').value = JSON.stringify(_parameter);
//						callFunction('_','dbAction','importSuccess',true,'import','displayImportSuccess',_file.name);
						//call...function('_',...) //callPHPfunction with JSON.stringified trashForm instead of proper form...
						//also include test if this already exists in database in dbAction: "insertIfNotExists"
					}
				}
			}
			r.readAsText(_file);
		} (i);
	}
	return false;
}

function displayImportSuccess(_form,_arg,_result) {
	var el = _arg.el.closest('.popup');
	var _imported = el.querySelector('.importImported');
	var _exists = el.querySelector('.importExists');
	var _problems = el.querySelector('.importProblems');
	var _importedDetails = el.querySelector('.importImportedDetails');
	var _existsDetails = el.querySelector('.importExistsDetails');
	var _problemsDetails = el.querySelector('.importProblemsDetails');
	if ( _result.indexOf('Eintrag wurde neu hinzugefügt') > -1 && _importedDetails.textContent.indexOf(_arg.log) == -1 ) { 
		_imported.innerText = parseInt(_imported.innerText) + 1;
		var _newlog = document.createElement('li'); 
		_newlog.textContent = _arg.log;
		_importedDetails.appendChild(_newlog);
	}
	else if ( _result.indexOf('{"id') > -1 && _existsDetails.textContent.indexOf(_arg.log) == -1 ) { 
		_exists.innerText = parseInt(_exists.innerText) + 1;
		var _newlog = document.createElement('li'); 
		_newlog.textContent = _arg.log;
		_existsDetails.appendChild(_newlog);
	}
	else if ( _result.indexOf('Eintrag wurde neu hinzugefügt') == -1 &&  _result.indexOf('{"id') == -1 && _problemsDetails.textContent.indexOf(_arg.log) == -1 ) { 
		_problems.innerText = parseInt(_problems.innerText) + 1;
		var _newlog = document.createElement('li'); 
		_newlog.textContent = _arg.log;
		_problemsDetails.appendChild(_newlog);
	}
	if ( _arg.table == 0 ) {
		_arg.parameter['dbAction'] = 'getID'; 
		document.getElementById('trash').value = JSON.stringify(_arg.parameter);
		_importSuccessHidden = el.querySelector('.importSuccessHidden').id;
		callFunction('_','dbAction',_importSuccessHidden,true,'import','getIDOfInsert',_arg); //inserts into div of class "gotID"
	}
}

function getIDOfInsert(_form,_arg,_result) {
	var _IDel = _arg.el.closest('.popup').querySelector('.gotID');
	//give it back to the import of secondary tables...
	try { var _tmp = JSON.parse(_IDel.textContent); } catch(err) { var _tmp = new Object(); }
	//console.log(_result);
	_tmp[_arg.file+'_'+_arg.line+'_'+_arg.table] = JSON.parse(_result);
	_IDel.textContent = JSON.stringify(_tmp);
	//console.log(Object.keys(_tmp).length+' '+_arg.filescount+' '+ _arg.linescount);
	//start import of subtables if _IDel is complete
//	if ( Object.keys(_tmp).length == _arg.filescount * _arg.linescount && _arg.el.closest('.popup').querySelector('.importFinished').textContent == '') {
//	import into the other tables when main table imports are complete
	if ( Object.keys(_tmp).length == _arg.linescount && _arg.el.closest('.popup').querySelector('.importFinished').textContent == '') {
		_arg.el.closest('.popup').querySelector('.importFinished').textContent = 'yes,maam';
		importJS(_arg.el,true);
		return;
	}; 	
}

//still some problems when imorting secondary table data with same data but different attributions: attribution is neglected!
