//global variables  Note: all globals are prefixed with g_ to make them identifiable
//in the code

var g_fields = [];											//pointer relations to import
var g_recTypeSelect = null;							//Record Type Selection object
var g_recDetailSelect = null;					//Detail Type Selection object
var g_textDetailSelect = null;
var g_text2DetailSelect = null;
var g_delimiterSelect = null;
var g_queryInput = null;
var g_dtIDsCheckbox = null;
var g_detailTypes = [];
var g_recType;
var g_recTypeLoaded;
var g_exportMap = [];
var g_cols = [];
var g_records = [];

/*
This function gets the record types from HAPI and loads them into a Select element
	for multi-select, so the user can select the output data.
*/
function getRecTypes() {
	var e = document.getElementById("select-rec-type");
	e.appendChild(document.createTextNode("Select record type: "));
	g_recTypeSelect = e.appendChild(document.createElement("select"));
	// once a recordtype is selected show the detailTypes
	g_recTypeSelect.onchange = function() { getDetailTypes() };
	var opt = document.createElement("option");
	opt.innerHTML = "record type...";
	opt.disabled = true;
	opt.selected = true;
	g_recTypeSelect.appendChild(opt);
	var recTypes = HRecordTypeManager.getRecordTypes();
	var l = recTypes.length;
	for (var i = 0; i < l; ++i) {
		opt = document.createElement("option");
		opt.value = recTypes[i].getID();
		opt.innerHTML = recTypes[i].getName();
		g_recTypeSelect.appendChild(opt);
	}
}
/*
This function reads in the Detail Types for the selected Record Type.
*/
function getDetailTypes(){
	var e = document.getElementById("select-detail-type");
	//remove the old selection so we can recreate it
	removeChildren(e);
	removeChildren(document.getElementById("export-detail-map"));
	g_textDetailSelect = document.createTextNode("Select reference types: ");
	e.appendChild(g_textDetailSelect);

	//create a multi-selection list of Detail Types   FIXME -- Should handle the single-type case
	g_recDetailSelect = e.appendChild(document.createElement("select"));
	g_recDetailSelect.multiple = true;
	g_recDetailSelect.onchange = function(){ updateExportMap() };
	g_recType = HRecordTypeManager.getRecordTypeById(g_recTypeSelect.value);
	g_detailTypes = HDetailManager.getDetailTypesForRecordType(g_recType);

	//add option for each detail type where the value=index into g_detailTypes and text=detail name (record type specific)
	var l = g_detailTypes.length;
	for (var d = 0; d < l; ++d) {
		addOpt(g_recDetailSelect, d, HDetailManager.getDetailNameForRecordType(g_recType, g_detailTypes[d]));
	}

	// add search string box
	e.appendChild(document.createElement("br"));
	e.appendChild(document.createTextNode("enter additional query string: "));
	g_queryInput = document.createElement("input");
	e.appendChild(g_queryInput);

	// output detail IDs as well?
	e.appendChild(document.createElement("br"));
	e.appendChild(document.createTextNode("output detail type IDs as well?: "));
	g_dtIDsCheckbox = document.createElement("input");
	g_dtIDsCheckbox.type = "checkbox";
	g_dtIDsCheckbox.onchange = function(){ updateExportMap() };
	e.appendChild(g_dtIDsCheckbox);

	//create selection for choosing the delimiter
	e.appendChild(document.createElement("br"));
	e.appendChild(document.createTextNode("use "));
	g_delimiterSelect = e.appendChild(document.createElement("select"));
	addOpt(g_delimiterSelect,",","comma"); //default
	addOpt(g_delimiterSelect,"\t","tab");
	e.appendChild(document.createTextNode(" as delimiter"));

	//add button for getting the records
	e.appendChild(document.createElement("br"));
	g_text2DetailSelect = document.createTextNode("then ");
	e.appendChild(g_text2DetailSelect);
	var button = e.appendChild(document.createElement("input"));
	button.type = "button";
	button.value = "get records";
	button.onclick = function() { getRecords(); };

}

function addOpt(sel, val, text) {
	var opt = sel.appendChild(document.createElement("option"));
	opt.value = val;
	opt.innerHTML = text;
	return opt;
}

function updateExportMap() {
	//clean up for multiple re-entry
	var e1 = document.getElementById("export-detail-map");
	removeChildren(e1);
	while (g_exportMap.length>0){
		g_exportMap.pop();
	}

	//if multiple select then for each selected pointer create input field marking id with detailType id
	var table = e1.appendChild(document.createElement("table"));
	table.id = "export-map-table";
	var tr = table.appendChild(document.createElement("tr"));
	tr.id = "export-map-row";
	var td, sel, opt;
	td = tr.appendChild(document.createElement("td"));
	td.innerHTML = "record ID";
	var l = g_recDetailSelect.options.length;
	for (var i = 0; i < l; ++i) {
		if (g_recDetailSelect.options[i].selected) {
			sel = g_recDetailSelect.options[i];
			if (g_dtIDsCheckbox.checked) {
				td = tr.appendChild(document.createElement("td"));
				td.innerHTML = g_detailTypes[sel.value].getID() + ":";
			}
			td = tr.appendChild(document.createElement("td"));
			td.innerHTML = sel.text;
			g_exportMap.push(sel.value);
/* version 2 should allow the selection of the detail type for the referenced object type (constrained case)
		// if constrained case then show select list for field in referenced record type
		// note that this will require multiple pass record retrieval
			if (g_detailTypes[i].getVariety() == HVariety.REFERENCE) {
				var constrRecType = g_detailTypes[i].getConstrainedRecordType();
				if (constrRecType){
				// add select to column
				//fill options for each detail type for contrained record type
			}
*/
		}
	}
}

function removeChildren (e) {
	while (e.firstChild){
		e.removeChild(e.firstChild);
	}
}

function loadAllRecords(query, options, loader) {
	var records = [];
	var baseSearch = new HSearch(query, options);
	var bulkLoader = new HLoader(
		function(s, r, c) {	// onload
			records.push.apply(records, r);
			if (r.length < 100) {
				// we've loaded all the records: invoke the loader's onload
				document.getElementById('results').innerHTML = '<b>Loaded ' + records.length + ' of ' + c + ' records </b>';
				loader.onload(baseSearch, records);
			}
			else {
				document.getElementById('results').innerHTML = '<b>Loaded ' + records.length + ' of ' + c + ' records so far ...</b>';

				// might be more records to load: do a search with an offset specified
				var search = new HSearch(query + " offset:"+records.length, options);
				HeuristScholarDB.loadRecords(search, bulkLoader);
			}
		},
		loader.onerror
	);
	HeuristScholarDB.loadRecords(baseSearch, bulkLoader);
}

function getRecords() {
	if (!g_recType || (g_recType != g_recTypeLoaded)){
		var myQuery = "type:" + g_recTypeSelect.value + " " + g_queryInput.value;

		var loader = new HLoader(
			function(s,r) {
				showRecordData(r);
				g_records = r;
				g_recTypeLoaded = g_recType;
			},
			function(s,e) {
				alert("getRecords failed: " + e);
			});
		loadAllRecords(myQuery, null, loader);
	}else{
		showRecordData(g_records);
	}
}


function showRecordData(hRecords) {
	var strDelim = g_delimiterSelect.value;
	var strRowTerm = "\n";
	var e = document.getElementById("records-p");
	//remove textarea for multiple exports
	removeChildren(e);
	//create output area
	var recDisplay = e.appendChild(document.createElement("textarea"));
	recDisplay.id = "csv-textarea";

	var lines = "";
	var dl;
	var l = hRecords.length;
	for (var i = 0; i < l; ++i) {
		var line = hRecords[i].getID()+ strDelim;
		var k = g_exportMap.length;
		for (var j = 0; j < k; ++j) {
			if (g_dtIDsCheckbox.checked) {
				line += g_detailTypes[g_exportMap[j]].getID() + strDelim;
			}
			if ( HDetailManager.getDetailRepeatable(g_recType, g_detailTypes[g_exportMap[j]])){ //repeatable detail case
				var details = hRecords[i].getDetails(g_detailTypes[g_exportMap[j]]);
				if (!details[0]){  //null detail
					line += strDelim;
				}else {
					if (g_detailTypes[g_exportMap[j]].getVariety() == HVariety.REFERENCE) { //multi-valued reference detail
						dl = details.length;
						for(var d = 0; d < dl; ++d){ //Ver 2 will need to extract the value for the selected detail type
							line += details[d].getID() +("|");
						}
						line = line.slice(0,-1) + strDelim;  // trim off last delimiter
					}else if (g_detailTypes[g_exportMap[j]].getVariety() == HVariety.GEOGRAPHIC) { //geographic object
						var field = "";
						dl = details.length;
						for(var d = 0; d < dl; ++d){ //Ver 2 will need to extract the value for the selected detail type
							field += details[d].getWKT() +("|");
						}
						line += csv_escape(field.slice(0,-1)) + strDelim;  // trim off last delimiter
					}else{ // multi-valued non-reference case
						line += csv_escape(details.join("|")) + strDelim;
					}
				}
			}else{ // single valued cases
				if (g_detailTypes[g_exportMap[j]].getVariety() == HVariety.REFERENCE) { //reference case
					var record = hRecords[i].getDetail(g_detailTypes[g_exportMap[j]]);
					if (record) {//reference  Ver 2 will need to extract the value for the selected detail type
						line += record.getID()+strDelim;
					}else{//null reference
						line += strDelim;
					}
				}else if (g_detailTypes[g_exportMap[j]].getVariety() == HVariety.GEOGRAPHIC) { //geographic object
					line += csv_escape(hRecords[i].getDetail(g_detailTypes[g_exportMap[j]]).getWKT())+strDelim;
				}else{//non-reference case
					line += csv_escape(hRecords[i].getDetail(g_detailTypes[g_exportMap[j]]))+strDelim;
				}
			}
		}
		lines += line.slice(0,-1) + strRowTerm;  //remove last delimiter and terminate line
	}
	recDisplay.value = lines;
}

function csv_escape(str) {
	if (! str) {
		return '';
	}
	if (g_delimiterSelect.value == "," && str.match(/[",\n\t]/)) {
		return '"' + str.replace(/\n/g,"\\n").replace(/\t/g,"\\t").replace(/"/g, '""') + '"';
	}else if (str.match(/[\n\t]/)) {
		return str.replace(/\n/g,"\\n").replace(/\t/g,"\\t");
	}
	return str;
}
