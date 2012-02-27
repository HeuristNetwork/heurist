/*
* importRecordsFromDelimited, javascript functions for importing comma or tab delimited data
* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
* @link: http://HeuristScholar.org
* @license http://www.gnu.org/licenses/gpl-3.0.txt
* @package Heurist academic knowledge management system
* @todo
*/

FlexImport = (function () {

	function _addOpt(sel, val, text, selected) {
	return $("<option>")
		.val(val)
		.html(text)
		.attr('selected', selected ? true :false)
		.appendTo(sel)[0];
	}

	return {

	valSep : '|',
	fields: [],
	lineHashes: {},
	columnCount: 0,
	hasHeaderRow: false,
	recTypeSelect: null,
	recTypeSelectSavedMapping: null,
	recType: null,
	errorSummary: null,
	wrong_columns: 0,
	wrong_values: 0,
	workgroupSelect: null,
	workgroups: {},
	workgroupTags: {},
	colSelectors: [],
	cols: [],
	subTypes: [],
	records: [],
	lineRecordMap: {},
	lineErrorMap: {},
	constChunkSize: 5,	// controls the number of records per request for saving records
						//FIXME  need to develop an algorithm for Chunk size
	recStart: 0,
	recEnd: 5,
	SavRecordChunk: [],

	clearRecords: function () {
		FlexImport.recStart = 0;
		FlexImport.recEnd = FlexImport.constChunkSize;
		var i;
		for (i in FlexImport.lineRecordMap) {
			delete FlexImport.lineRecordMap[i];
		}
		FlexImport.lineRecordMap = {};
		for (i in FlexImport.lineErrorMap) {
			delete FlexImport.lineErrorMap[i];
		}
		FlexImport.lineErrorMap = {};
		for (i in FlexImport.lineHashes) {
			delete FlexImport.lineHashes[i];
		}
		FlexImport.lineHashes = {};
		for (i = 0; i < FlexImport.records.length; ++i) {
			delete FlexImport.records[i];
		}
		FlexImport.records = [];
	},

	analyseCSV: function () {

		var txt = $("#csv-textarea").val();
		if(!txt || txt.length<3){
			alert('Please paste some data');
			return;
		}

		var separator = $("#csv-separator").val();
		var terminator = $("#csv-terminator").val();
		this.quote = $("#csv-quote").val();
		this.valSep = $("#val-separator").val();
		this.hasHeaderRow = $("#csv-header").attr("checked");
		var lineRegex, fieldRegex, doubleQuoteRegex;

		if (terminator == "\\n") terminator = "\n";

		var switches = (terminator == "\n") ? "m" : "";

		if (this.quote == "'") {
			lineRegex = new RegExp(terminator + "(?=(?:[^']*'[^']*')*(?![^']*'))", switches);
			fieldRegex = new RegExp(separator + "(?=(?:[^']*'[^']*')*(?![^']*'))", switches);
		} else {
			lineRegex = new RegExp(terminator + "(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))", switches);
			fieldRegex = new RegExp(separator + "(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))", switches);
		}
		doubleQuoteRegex = new RegExp(this.quote + this.quote, "g");

		var lines = txt.split(lineRegex);
		var i, l = lines.length;
		for (i = 0; i < l; ++i) {
			if (lines[i].length > 0) {
				FlexImport.fields[i] = lines[i].split(fieldRegex);
				for (var j = 0; j < FlexImport.fields[i].length; ++j) {
					FlexImport.fields[i][j] = FlexImport.fields[i][j].replace(doubleQuoteRegex, this.quote);
					FlexImport.columnCount = Math.max(FlexImport.columnCount, j + 1);
				}
			}
		}
		//we have parsed the input so remove the textarea
		$("#csv-entry-div").remove();

		$("#info-p").html(
			"Found <b>" + (this.hasHeaderRow ? FlexImport.fields.length - 1:FlexImport.fields.length) + "</b> rows of data," +
			" in <b>" + FlexImport.fields[0].length + "</b> columns. " +
			"<a href='"+HeuristBaseURL+"import/delimited/importRecordsFromDelimited.html?db="+HAPI.database+"' ><b>Start over</b></a>"
		);

		FlexImport.createRecTypeOptions();

		FlexImport.loadSavedMappings();
	},

	//load mapping from file
	reapplyMapping: function(){

			function _onLoadSavedMappingsContent(context){

				if(context.response.indexOf("Error:")==0){
					alert(context.response );
					return;
				}

				var savedMapping = context.response.split(",");

				if(savedMapping.length<1){
					alert("Error: Empty mapping");
					return;
				}

				var recTypeID = Number(FlexImport.recTypeSelect.value);

				if(recTypeID!==Number(savedMapping[0])){
					FlexImport.recTypeSelect.value = savedMapping[0];
					FlexImport.createColumnSelectors();
				}

				var i, j=1,
					m = savedMapping.length,
					l = FlexImport.colSelectors.length;

				for (i = 0; i < l; ++i) {
					if(j<m){
						FlexImport.colSelectors[i].value = savedMapping[j];
						j++;
					}else{
						FlexImport.colSelectors[i].selectedIndex = 0;
					}
				}
			}

			var baseurl = HeuristBaseURL+"import/delimited/importDelimitedMapping.php";
			var callback = _onLoadSavedMappingsContent;
			var params = "mode=load&file="+FlexImport.recTypeSelectSavedMapping.value+"&db=" + HAPI.database;
			top.HEURIST.util.sendRequest(baseurl, callback, params);

	},

	//load list of saved mappings
	loadSavedMappings: function () {

			function _onLoadSavedMappingsList(context){

				var savedMapping = context.response.split("|");

				var e = $("#rec-type-select-div")[0];
				e.appendChild(document.createTextNode("  or select saved mapping for record type: "));
				FlexImport.recTypeSelectSavedMapping = e.appendChild(document.createElement("select"));
				FlexImport.recTypeSelectSavedMapping.onchange = function() {
						FlexImport.reapplyMapping();
				};
				var opt = document.createElement("option");
				opt.innerHTML = "to select...";
				opt.disabled = true;
				opt.selected = true;
				FlexImport.recTypeSelectSavedMapping.appendChild(opt);

				var i, l = savedMapping.length;
				for (i = 0; i < l; ++i) {
					opt = document.createElement("option");
					opt.value = savedMapping[i];
					opt.innerHTML = savedMapping[i];
					FlexImport.recTypeSelectSavedMapping.appendChild(opt);
				}

			}

			var baseurl = HeuristBaseURL+"import/delimited/importDelimitedMapping.php";
			var callback = _onLoadSavedMappingsList;
			var params = "mode=list&db=" + HAPI.database;
			top.HEURIST.util.sendRequest(baseurl, callback, params);

	},

	saveMappings: function () {

			function _onSaveMappingsList(context){
				alert(context.response);
			}

			var sel = FlexImport.recTypeSelect;

			if(sel && sel.selectedIndex>0){

				var recordType = sel.options[sel.selectedIndex].text;

				var i,
					atleastOne = false,
					content = [],
					l = FlexImport.colSelectors.length;

				content.push(sel.value);

				for 	(i = 0; i < l; ++i) {
					if (FlexImport.colSelectors[i].selectedIndex > 0) {
						atleastOne = true;
						content.push(FlexImport.colSelectors[i].value);
					}else{
						content.push("0");
					}
				}

				if(atleastOne){
					var baseurl = HeuristBaseURL+"import/delimited/importDelimitedMapping.php";
					var callback = _onSaveMappingsList;
					var params = "mode=save&db=" + HAPI.database+"&file="+recordType+"&content="+content.join(",");
					top.HEURIST.util.sendRequest(baseurl, callback, params);
				}else{
					alert("Define at least one mapping value");
				}

			}
	},

	createRecTypeOptions: function () {
		var e = $("#rec-type-select-div")[0];
		e.appendChild(document.createTextNode("Step 2: Select record type: "));
		FlexImport.recTypeSelect = e.appendChild(document.createElement("select"));
		FlexImport.recTypeSelect.onchange = function() { FlexImport.createColumnSelectors() };
		var opt = document.createElement("option");
		opt.innerHTML = "record type...";
		opt.disabled = true;
		opt.selected = true;
		FlexImport.recTypeSelect.appendChild(opt);
		var recTypes = HRecordTypeManager.getRecordTypes();
		var i, l = recTypes.length;
		for (i = 0; i < l; ++i) {
			opt = document.createElement("option");
			opt.value = recTypes[i].getID();
			opt.innerHTML = recTypes[i].getName();
			FlexImport.recTypeSelect.appendChild(opt);
		}
	},

	createColumnSelectors: function () {
		var e = $("#col-select-div")[0];

		FlexImport.recType = HRecordTypeManager.getRecordTypeById(FlexImport.recTypeSelect.value);

		// remove the previous record type record display since we will recreate it here
		$(e).empty();
		$("#records-div").empty();
		$("#records-div-info").empty();

		var p = e.appendChild(document.createElement("p"));
		p.appendChild(document.createTextNode("Workgroup for tags: "));
		FlexImport.workgroupSelect = p.appendChild(document.createElement("select"));
		FlexImport.workgroupSelect.onchange = function() {
			FlexImport.workgroupTags = {};
			if (this.value) {
				var wgkwds = HWorkgroupTagManager.getWorkgroupTags(FlexImport.workgroups[this.value]);
				var i, l = wgkwds.length;
				for (i = 0; i < l; ++i) {
					FlexImport.workgroupTags[wgkwds[i].getName()] = wgkwds[i];
				}
			}
		}

		var wgs = HWorkgroupManager.getWorkgroups();
		_addOpt(FlexImport.workgroupSelect, "", "select...");
		var i, l = wgs.length;
		for (i = 0; i < l; ++i) {
			FlexImport.workgroups[wgs[i].getID()] = wgs[i];
			_addOpt(FlexImport.workgroupSelect, wgs[i].getID(), wgs[i].getName());
		}
		p.appendChild(document.createTextNode(" NOTE: Workgroup tags must already exist. "));
		var a = p.appendChild(document.createElement("a"));
			a.target = "_blank";
			a.href = HeuristBaseURL + "admin/ugrps/editGroupTags.php?db="+HAPI.database
			a.innerHTML = "Create new tags";
		p.appendChild(document.createTextNode(" then start over"));

		p = e.appendChild(document.createElement("p"));
		p.appendChild(document.createTextNode("Select column assignments, then "));

		var button = p.appendChild(document.createElement("input"));
			button.type = "button";
			button.value = " Save field mapping ";
			button.onclick = function() { FlexImport.saveMappings(); };
		p.appendChild(document.createTextNode(" then "));

		var button = p.appendChild(document.createElement("input"));
			button.type = "button";
			button.value = " Prepare records ";
			button.onclick = function() { FlexImport.loadReferencedRecords(); };
		p.appendChild(document.createTextNode(" SAFE - doesn't write to the database"));


		p = e.appendChild(document.createElement("p"));
		a = p.appendChild(document.createElement("a"));
		a.target = "_blank";
			a.href = HeuristBaseURL +"admin/describe/listRectypeDescriptions.php?db="+ HAPI.database +"#rt" + FlexImport.recType.getID();
		a.innerHTML = "Show field list for <b>" + FlexImport.recType.getName() + "</b>";


		var i, l = FlexImport.columnCount;
		var table = document.createElement("table");
		table.id = "col-select-table";
		var tbody = table.appendChild(document.createElement("tbody"));
		//create header row if supplied
		var headerRow = null;
		if (this.hasHeaderRow){
			headerRow = FlexImport.fields[0];
			tr = tbody.appendChild(document.createElement("tr"));
			tr.id = "col-header-row";
			td = tr.appendChild(document.createElement("td"));
			td.innerHTML = "Column Heading"; //
			for (i = 0; i < headerRow.length; ++i) {
				td = tr.appendChild(document.createElement("td"));
				if(headerRow[i].charAt(0)=='"'){
					headerRow[i] = headerRow[i].substr(1,headerRow[i].length-2);
				}
				td.innerHTML = headerRow[i];
			}
		}
		//create row of field type selectors
		var tr = tbody.appendChild(document.createElement("tr"));
		tr.id = "col-select-row";
		var td, sel, opt;
		td = tr.appendChild(document.createElement("td"));
		td.innerHTML = "row number";
		for (i = 0; i < l; ++i) {
			// add column select header for selecting detail type for this column
			td = tr.appendChild(document.createElement("td"));
			sel = td.appendChild(document.createElement("select"));
			sel.onchange = function() {
				if (this.value != "tags"  &&  this.value != "wgTags") {
					//search if this detail is in another column and remove it from the other column if it is
					// todo: should we stop people putting data from two columns into the same field? I think not. Maybe popup an alert
					var j, m = FlexImport.colSelectors.length;
					for (j = 0; j < m; ++j) {
						var s = FlexImport.colSelectors[j];
						if (s != this  &&  s.value == this.value) {
							s.selectedIndex = 0;
							if (s.subTypeSelect) { alert("Warning: you've selected the same target field for more than one soruce field");
								// s.parentNode.removeChild(s.subTypeSelect);
							}
						}
					}
				}
				// for types that have subtypes show select for subtypes
				if (this.value != "url"  &&  this.value != "notes"  &&
					this.value != "tags"  &&  this.value != "wgTags"  &&
					HDetailManager.getDetailTypeById(this.value).getVariety() == HVariety.GEOGRAPHIC) {
					this.subTypeSelect = this.parentNode.appendChild(document.createElement("select"));
					var vals = [
						[ HGeographicType.POINT, "POINT" ],
						[ HGeographicType.BOUNDS, "BOUNDS" ],
						[ HGeographicType.POLYGON, "POLYGON" ],
						[ HGeographicType.PATH, "PATH" ],
						[ HGeographicType.CIRCLE, "CIRCLE"]
					];
					var v, m = vals.length;
					for (v = 0; v < m; ++v) {
						var o = this.subTypeSelect.appendChild(document.createElement("option"));
						o.value = vals[v][0];
						o.innerHTML = vals[v][1];
					}
				} else {   //FIXME add code here to create selection of reference type data (currently handles only heurist ids)
					if (this.subTypeSelect) {
						this.parentNode.removeChild(this.subTypeSelect);
					}
				}
			};

			// fill in comlumn selector options for recType
			FlexImport.colSelectors[i] = sel;
			var columnName = "";
			// if the user supplied a header row and there is a collumn heading for the current column
			if (this.hasHeaderRow && headerRow && headerRow[i]){
				columnName = headerRow[i].toLowerCase();
				columnName = columnName.replace(/^\s*/,"");
				columnName = columnName.replace(/\s*$/,"");
			}
			opt = sel.appendChild(document.createElement("option"));
			opt.value = null; opt.innerHTML = "do not import";

			var grp = sel.appendChild(document.createElement("optgroup"));
			grp.label = " ";

			_addOpt(sel, "tags", "Tag(s)", columnName == "tag(s)");
			_addOpt(sel, "wgTags", "Workgroup Tag(s)", columnName == "workgroup tag(s)");

			var reqDetailTypes = HDetailManager.getRequiredDetailTypesForRecordType(FlexImport.recType);
			var detailTypes = HDetailManager.getDetailTypesForRecordType(FlexImport.recType);
			var d, k, rdl = reqDetailTypes.length;
			var dl = detailTypes.length;

			var alist = [];

			alist.push({id:'url', name:'URL', selected:(columnName == 'url'), req:false});
			alist.push({id:'notes', name:'Notes', selected:(columnName == 'notes'), req:false});

			for (d = 0; d < dl; ++d) {
				var rdName = HDetailManager.getDetailNameForRecordType(FlexImport.recType, detailTypes[d]);
				var det_id = detailTypes[d].getID();
				var isrequired = false;

				for (k = 0; d < rdl; ++k) {
					if(det_id = reqDetailTypes[k].getID()){
						isrequired = true;
						break;
					}
				}

				alist.push({id:det_id, name:rdName, selected:(columnName == rdName.toLowerCase()), req:isrequired});
			}

			//sort by name
			alist.sort(function (a,b){
				return a.name<b.name?-1:1;
			});

			grp = sel.appendChild(document.createElement("optgroup"));
			grp.label = " ";

			//create options for select
			for (d = 0; d < alist.length; ++d) {
				var opt = _addOpt(sel, alist[d].id, alist[d].name, alist[d].selected);
				if(alist[d].req){
					opt.className = "required";
				}
			}

//_addOpt(sel, val, text, selected)

/* it works!!! but it used before 2012-02-14
			var rdIndex = {};
			var grp = sel.appendChild(document.createElement("optgroup"));
			grp.label = "Required fields";
			//todo: doesn't list required fields under this heading, lsits required and optional under the Other fields heading
			for (d = 0; d < rdl; ++d) {
				var rdID = reqDetailTypes[d].getID();
				var rdName = HDetailManager.getDetailNameForRecordType(FlexImport.recType, reqDetailTypes[d]);
				var opt = _addOpt(sel, rdID,  rdName, columnName == rdName.toLowerCase());
				opt.className = "required";
				for (var r = 0; r < dl; ++r){
					if (rdID == detailTypes[r].getID()) {
						rdIndex[r] = true;
					}
				}
			}
			grp = sel.appendChild(document.createElement("optgroup"));
			grp.label = "Optional fields";
			for (d = 0; d < dl; ++d) {
				if (rdIndex[d]) continue;
				var rdName = HDetailManager.getDetailNameForRecordType(FlexImport.recType, detailTypes[d]);
				var opt = _addOpt(sel, detailTypes[d].getID(),rdName,columnName == rdName.toLowerCase());
			}
*/
			if(i<FlexImport.cols.length &&  FlexImport.cols[i]){
				sel.value = FlexImport.cols[i];
			}
		}

		// create rest of table filling it with the csv analysed data
		for (var i = this.hasHeaderRow ? 1:0; i < FlexImport.fields.length; ++i) {
			var inputRow = FlexImport.fields[i];
			tr = tbody.appendChild(document.createElement("tr"));
			if (FlexImport.lineErrorMap[i] && FlexImport.lineErrorMap[i].invalidRecord){
				tr.className = "invalidRecord";
			}
			td = tr.appendChild(document.createElement("td"));
			td.innerHTML = i + (this.hasHeaderRow?0:1); //row number
			//for each column
			for (var j = 0; j < FlexImport.columnCount; ++j) {
				td = tr.appendChild(document.createElement("td"));
				if (inputRow.length > j) {
					//strip quotes if necessary
					re = new RegExp ("^\\s*" + this.quote + "(.*)" +this.quote +"\\s*$");
					if (inputRow[j].toString().match(re)) {
						inputRow[j] = inputRow[j].toString().match(re)[1];
					}
					if (FlexImport.lineErrorMap[i] && FlexImport.lineErrorMap[i][j]){
						//td.className = "invalidInput";
						var p = td.appendChild(document.createElement("p"));
						p.id = "wrin"+i+"_"+j;
						p.className = "errorMsg";
						p.innerHTML = FlexImport.lineErrorMap[i][j];

					} else {
						var str = inputRow[j];
						if(str.length>50){
							str = str.substring(0,50)+"..";
						}
						td.appendChild(document.createTextNode(str));
					}
				}
			}
		}

		e.appendChild(table);

		FlexImport.showErrorSummary(table);

	},

	//
	// creates the table with list of wrong and unrecognized values
	//
	showErrorSummary: function (before) {

		var eS = FlexImport.errorSummary;

		if(eS){

			var e = $("#col-select-div")[0];

			var table = document.createElement("table");
			table.id = "col-select-table";
			var tbody = table.appendChild(document.createElement("tbody"));

			var headerRow = FlexImport.fields[0];
			var haserr = false;

			for (var j = 0; j < FlexImport.columnCount; ++j) {
				if(eS[j]){
					tr = tbody.appendChild(document.createElement("tr"));
					td = tr.appendChild(document.createElement("td"));
					td.innerHTML = (this.hasHeaderRow)?headerRow[j]:("column "+j); //name of column with wrong values

					td = tr.appendChild(document.createElement("td"));
					td.style.width = 220;

					for (var i = 0; i < eS[j].length; ++i) {

						/*var temp = "";
						if (eS[j][i]) {
							temp += "Correct value : " + eS[j][i];
						}else {
							temp += "Enter a correct value here."
						}*/
						var p = td.appendChild(document.createElement("div"));
						var ed = p.appendChild(document.createElement("input"));
						ed.id = "edcorrect"+j;
						ed.style.width = 120;
						ed.value = eS[j][i];
						ed.className = "invalidInput";
						ed.cleared = false;
						/*ed.onfocus = function () {
								if (!this.cleared) {
									this.value = "";
									this.cleared = true;
								}
						};*/

						var btn = p.appendChild(document.createElement("button"));
						btn.col = j;
						btn.wrongValue = (eS[j][i]?eS[j][i]:"");
						btn.innerHTML = "Modify data";
						btn.onclick = function () {

								//was FlexImport.fields[this.row][this.col] = this.value;
								var edinput = document.getElementById("edcorrect"+this.col);
								var wrongValue = this.wrongValue;
								var i, len = FlexImport.fields.length;
								for (i = 1; i < len; ++i) {
									if(wrongValue==FlexImport.fields[i][this.col]){
										FlexImport.fields[i][this.col] = edinput.value;
										this.wrongValue = edinput.value;
										//find the table cell with wrong value
										var td_tocorrect = document.getElementById("wrin"+i+"_"+this.col);
										if(td_tocorrect){
											td_tocorrect.innerHTML = edinput.value;
										}
									}
								}
						};

						//td.appendChild(document.createElement("br"));
					}
					//var s = eS[j].join("<br/>");
					//td.innerHTML = s;

					td = tr.appendChild(document.createElement("td"));
					if(FlexImport.colSelectors[j].selectedIndex>0){

						var a = td.appendChild(document.createElement("a"));
						a.href = "#";
						a.innerHTML = "Edit field definition";
						a.id2 = FlexImport.colSelectors[j].value;
						var _onEditClick = function (e){

							var dtid = e.target.id2;
							var url = HeuristBaseURL+
								"admin/structure/editDetailType.html?db="+HAPI.database+"&detailTypeID="+dtid;

							top.HEURIST.util.popupURL(top, url,
							{   "close-on-blur": false,
								"no-resize": false,
								height: 680,
								width: 660,
								callback: function(context) {
								}
							});

						};
						a.onclick = _onEditClick;
					}
					haserr = true;
				}
			}
			if(haserr){
				e.insertBefore(table, before);
                e.insertBefore(document.createTextNode("Unrecognised values in imported data (edit red values below and click Modify Data, then Prepare Records again. Alternatively you can edit the field definitions with the link to the right of each value.)"), table);
			}

		}
	},

	// This function preloads all records necessary for REFERENCE detail types
	loadReferencedRecords: function ()
	{
		var detailType;
		var refCols = [];
		var recIDs = [];
		var recID = "";
		var valCheck = {};
		//detect what fields to be imported
		var i, l = FlexImport.colSelectors.length;
		for (i = 0; i < l; ++i) {
			if (FlexImport.colSelectors[i].selectedIndex > 0) {
				FlexImport.cols[i] = FlexImport.colSelectors[i].value;
			}else if(i<FlexImport.cols.length){
				FlexImport.cols[i] = undefined;
			}
			FlexImport.subTypes[i] = FlexImport.colSelectors[i].subTypeSelect ? FlexImport.colSelectors[i].subTypeSelect.value : null;
			if ( FlexImport.cols[i]  &&  FlexImport.cols[i]!=="tags"   &&  FlexImport.cols[i]!== "wgTags" && FlexImport.cols[i] !== "url" && FlexImport.cols[i] !== "notes") {
				detailType = HDetailManager.getDetailTypeById(FlexImport.cols[i]);
				//mark which columns have the REFERENCE identifying data
				if (detailType.getVariety() == HVariety.REFERENCE) {
					if (HDetailManager.getDetailRepeatable(FlexImport.recType, detailType)) {
						refCols[i]=2;
					} else {
						refCols[i]=1;
					}
				}
			}
		}
		// build string for the query of referenced heurist records to load into cache
		// FIXME  add code to handle record type field value queries for lookup type queries
		// Example- type:Person field:"Given names":Bruce
		if (refCols.length > 0) {
			for (var i = 0; i < FlexImport.fields.length; ++i) {
				for (var j in refCols) {
					if (!j) continue; // skip any undefined entries
					var tempRecIDs = FlexImport.fields[i][j];
					if (tempRecIDs) {
						tempRecIDs = tempRecIDs.split(","); // split into array of ids with comma as delimiter
						if (refCols[j] == 1) { // non repeatable so just take the first value. FIXME add display warning
							tempRecIDs = tempRecIDs.splice(0,1);
						}
						for (var k =0; k < tempRecIDs.length; k++) {
							recID = parseInt( tempRecIDs[k]);
							if (recID && !valCheck[recID]) {
								recIDs.push(recID);
								valCheck[recID] = true;
							}
						}
					}
				}
			}
		}
		if (recIDs.length > 0) {
			var myquery = "ids:" + recIDs.join(",");
			FlexImport.Loader.loadRecords(myquery)
		} else {
			FlexImport.createRecords();
		}
	},

	transformDates: function () {
		var i, j, detailType, val, reString, re, matches, dateTransform, indices,
		pad = function (s) {
			s = String(s);
			return s.length < 2  ?  "0" + s  :  s;
		};

		for (j = 0; j < FlexImport.cols.length; ++j) {
			if (parseInt(FlexImport.cols[j]) > 0) {
				detailType = HDetailManager.getDetailTypeById(FlexImport.cols[j]);
				if (detailType.getVariety() === HVariety.DATE) {
					dateTransform = null;
					for (i = 0; i < FlexImport.fields.length; ++i) {
						val = FlexImport.fields[i][j];
						if (val.match(/\|VER\=/i)) {	// temporal string so don't transform
							try{
								Temporal.parse(val);
							}catch(e){
								alert("Warning: temporal format in column " + j + ", row " + i + ": " + val +
										" will not create temporal object - " + e );
							}
							continue;
						}
						reString = "(\\d\\d?)\\/(\\d\\d?)\\/(\\d{4})";
						re = new RegExp(reString);
						matches = val.match(re);
						if (matches) {
							if (matches[1] > 12) {
								if (dateTransform  &&  (dateTransform[0] != reString  ||  dateTransform[1][1] != 2)) {
									alert("Warning: inconsistent date format in column " + j + ", row " + i + ": " + val);
								} else {
									dateTransform = [ reString, [3, 2, 1] ];
								}
							} else if (matches[2] > 12) {
								if (dateTransform  &&  (dateTransform[0] != reString  ||  dateTransform[1][1] != 1)) {
									alert("Warning: inconsistent date format in column " + j + ", row " + i + ": " + val);
								} else {
									dateTransform = [ reString, [3, 1, 2] ];
								}
							}
						} else {
							reString = "(\\d{4})\\/(\\d\\d?)\\/(\\d\\d?)";
							re = new RegExp(reString);
							matches = val.match(re);
							if (matches) {
								if (dateTransform  &&  dateTransform[0] != reString) {
									alert("Warning: inconsistent date format in column " + j + ", row " + i + ": " + val);
								} else {
									dateTransform = [ reString, [1, 2, 3] ];
								}
							}
						}
					}
					if (dateTransform) {
						re = new RegExp(dateTransform[0]);
						indices = dateTransform[1];
						for (i = 0; i < FlexImport.fields.length; ++i) {
							val = FlexImport.fields[i][j];
							matches = val.match(re);
							if (matches) {
								FlexImport.fields[i][j] =
										matches[indices[0]] + "-" +
									pad(matches[indices[1]]) + "-" +
									pad(matches[indices[2]]);
							}
						}
					}
				}
			}
		}
	},

	makeHash: function (fields) {
		hash = "";
		for (var j = 0; j < fields.length; ++j) {
			if (FlexImport.cols[j]  &&
				FlexImport.cols[j] != "tags"  &&
				FlexImport.cols[j] != "wgTags") {
				hash += fields[j];
			}
		}
		return hash;
	},


	createRecords: function () {
		var detailType;
		var record;
		var error;
		var val;

		FlexImport.clearRecords();
		FlexImport.colSelectors = [];
		$("#col-select-div").empty();

		//e.innerHTML += ("<p>If there are errors: <input type=button value=\"Go back\" onclick=\"FlexImport.createColumnSelectors();\"></p>");


		var e = $("#records-div")[0];

		var table = e.appendChild(document.createElement("table"));
		var tbody = table.appendChild(document.createElement("tbody"));
		var tr, td;

		// header row
		tr = tbody.appendChild(document.createElement("tr"));
		td = tr.appendChild(document.createElement("td"));
		var tags = false;
		var kwds = false;
		var j, l = FlexImport.cols.length;
		for (j = 0; j < l; ++j) {
			if (! FlexImport.cols[j]  ||  (FlexImport.cols[j]=="tags" && tags)  ||  (FlexImport.cols[j]=="wgTags" && kwds)) continue;
			td = tr.appendChild(document.createElement("td"));
			if (FlexImport.cols[j] == "url") {
				td.innerHTML = "URL";
			} else if (FlexImport.cols[j] == "notes") {
				td.innerHTML = "Notes";
			} else if (FlexImport.cols[j] == "tags") {
				if (! tags) {
					tags = true;
					td.innerHTML = "Tag(s)";
				}
			} else if (FlexImport.cols[j] == "wgTags") {
				if (! kwds) {
					kwds = true;
					td.innerHTML = "Workgroup Tag(s)";
				}
			} else if (FlexImport.cols[j]) {
				detailType = HDetailManager.getDetailTypeById(FlexImport.cols[j]);
				td.innerHTML = HDetailManager.getDetailNameForRecordType(FlexImport.recType, detailType);
			}
		}

		var now = new Date();
		var y = now.getFullYear();
		var m = now.getMonth() + 1; if (m < 10) m = "0" + m;
		var d = now.getDate(); if (d < 10) d = "0" + d;
		var h = now.getHours(); if (h < 10) h = "0" + h;
		var min = now.getMinutes(); if (min < 10) min = "0" + min;
		var s = now.getSeconds(); if (s < 10) s = "0" + s;

		var importTag = "FlexImport " + y + "-" + m + "-" + d + " " + h + ":" + min + ":" + s;
		try {
			HTagManager.addTag(importTag);
		} catch(e) {
			alert("error adding Tag :" + e);
		}

		FlexImport.transformDates();
		FlexImport.reqDetailsMap = {};
		var reqDetails = HDetailManager.getRequiredDetailTypesForRecordType(FlexImport.recType);
		for (var r =0; r < reqDetails.length; ++r) {
			FlexImport.reqDetailsMap[reqDetails[r].getID()] = true;
		}

		FlexImport.errorSummary = new Array(FlexImport.columnCount);
		FlexImport.wrong_columns = 0;
		FlexImport.wrong_values = 0;

		// create records
		l = FlexImport.fields.length;
		var istart = (FlexImport.hasHeaderRow)?1:0;
		for (var i = istart; i < l; ++i) {

			var lineHash = FlexImport.makeHash(FlexImport.fields[i]);

			if (FlexImport.lineHashes[lineHash] != undefined) {
				// we've already created a record for an identical input line
				record = FlexImport.lineRecordMap[FlexImport.lineHashes[lineHash]];
				error = [FlexImport.lineHashes[lineHash]];
			} else {
				FlexImport.lineHashes[lineHash] = i;
				var ret = FlexImport.createRecord(FlexImport.recType, importTag, FlexImport.fields[i]);
				record = ret.record;
				error = ret.error;
				if (!error || !error.invalidRecord) {	// only add valid records to be saved note erroneous optional data doesn't prohibit saving (it's ignored)
					FlexImport.records.push(record);
				}
			}
			FlexImport.lineRecordMap[i] = record;
			if (error) {
				FlexImport.lineErrorMap[i] = error;
			}

			// display record using the information from the initialized record (this ensures correct formating)
			tr = tbody.appendChild(document.createElement("tr"));
			if (error && error.invalidRecord) {
				tr.className = "invalidRecord";
				tr.title = error.invalidRecord;
			}
			td = tr.appendChild(document.createElement("td"));
			td.innerHTML = i + 1;

			tags = false; kwds = false;
			for (var j = 0; j < FlexImport.fields[i].length; ++j) {
				if (! FlexImport.cols[j]  ||  (FlexImport.cols[j]=="tags" && tags)  ||  (FlexImport.cols[j]=="wgTags" && kwds)) continue;

				var inputRow = FlexImport.fields[i];

				td = tr.appendChild(document.createElement("td"));
				if (FlexImport.cols[j] == "url") {
					td.innerHTML = "<p>" + record.getURL() + "</p>";
				} else if (FlexImport.cols[j] == "notes") {
					td.innerHTML = "<p>" + record.getNotes() + "</p>";
				} else if (FlexImport.cols[j] == "tags") {
					if (! tags) {
						tags = true;
						td.innerHTML = "<p>" + record.getTags().join(", ") + "</p>";
					}
				} else if (FlexImport.cols[j] == "wgTags") {
					if (! kwds) {
						kwds = true;
						var temp = "<p>";
						var ks = record.getWgTags();
						for (var k = 0; k < ks.length; ++k) {
							temp += (k > 0 ? ", " : "") + ks[k].getName();
						}
						td.innerHTML = temp + "</p>";
					}
				} else if (FlexImport.cols[j]) { // display the other details from the created record
					detailType = HDetailManager.getDetailTypeById(FlexImport.cols[j]);
					td.innerHTML = "<p>" + record.getDetails(detailType).join("\n") + "</p>";
				}
				if (error && error[j]) {
					td.className = "invalidInput";
					td.innerHTML += "<p class=errorMsg>" + error[j] + "</p>";

						var eS = FlexImport.errorSummary;
						if(eS[j]){
							if(eS[j].indexOf(inputRow[j])<0){
								FlexImport.wrong_values++;
								eS[j].push(inputRow[j]);
							}
						}else{
							FlexImport.wrong_columns++;
							FlexImport.wrong_values++;
							eS[j] = [];
							eS[j].push(inputRow[j]);
						}
				}
			} // for j in FlexImport.fields loop
		} // for i = 0 loop

		// show command button for saving records
		var e = $("#records-div-info")[0];

		if(FlexImport.wrong_values>0){
			e.innerHTML = "<p class='invalidInput'>There are "+FlexImport.wrong_values+" unexpected values in "+
							FlexImport.wrong_columns+" columns. "+
							"<input type=button value=\"Go back\" onclick=\"FlexImport.createColumnSelectors();\"></p>";
		}else{
			e.innerHTML = "<p>If records appear OK: <input type=button value=\"Save records\" onclick=\"FlexImport.Saver.saveRecords();\">&nbsp;&nbsp;This step updates the database (irreversible, except by editing the database)</p>";
			e.innerHTML += "<p><b>Records prepared for import:</b></p>";
		}
	},


	createRecord: function (recType, importTag, fields) {
		var hRec,err;

		var logError = function (key, msg) {
			if (!err) {
				err = {};
			}
			if (!err[key]) {
				err[key] = msg;
			}else{
				err[key] += "\n" + msg;
			}
		}

		if (top.HEURIST.magicNumbers && top.HEURIST.magicNumbers['RT_RELATION'] && recType.getID() == top.HEURIST.magicNumbers['RT_RELATION']) {//MAGIC NUMBER
			hRec = new HRelationship();
		}
		else {
			hRec = new HRecord();
		}
		hRec.setRecordType(recType);
		hRec.addToPersonalised();
		hRec.addTag(importTag);

		for (var j = 0; j < fields.length; ++j) {

			// skip unassigned columns
			if (! FlexImport.cols[j]  ||  FlexImport.cols[j] == "") {
				continue;
			}

			// get detail value
			val = fields[j];
			if (! val) {
				if (FlexImport.reqDetailsMap[FlexImport.cols[j]]) {
					var name = HDetailManager.getDetailNameForRecordType(FlexImport.recType,HDetailManager.getDetailTypeById(FlexImport.cols[j]));
					logError( j, "Null value found for required field : " + name + "(" + FlexImport.cols[j] + ")");
					logError("invalidRecord", " Missing " + name +".");
				}
				detailType = HDetailManager.getDetailTypeById(FlexImport.cols[j]);
				if (detailType && detailType.getVariety() == HVariety.REFERENCE) {
					logError( j, "Resource record ID is not defined");
				}
				continue;
			}
			try {
				// set records detail value
				if (FlexImport.cols[j] == "url") {
					hRec.setURL(val);
				} else if (FlexImport.cols[j] == "notes") {
					hRec.setNotes(val);
				} else if (FlexImport.cols[j] == "tags") {
					var vals = val.split(",");
					for (var v = 0; v < vals.length; ++v) {
						HTagManager.addTag(vals[v]);	// ensure the tag exists
						hRec.addTag(vals[v]);
					}
				} else if (FlexImport.cols[j] == "wgTags") {
					var vals = val.split(",");
					for (var v = 0; v < vals.length; ++v) {
						if (FlexImport.workgroupTags[vals[v]]) {
							hRec.addWgTag(FlexImport.workgroupTags[vals[v]])
						}
					}
				} else if (FlexImport.cols[j]) { // detail is generic so prepare to addDetail
					detailType = HDetailManager.getDetailTypeById(FlexImport.cols[j]);
					var vals;
					if (HDetailManager.getDetailRepeatable(recType, detailType)) {
						vals = val.split( detailType.getVariety() == HVariety.GEOGRAPHIC ? "|" : this.valSep ); //FIXME multi-valued fields from CSV format might have | as delimiter
					} else {
						vals = [ val ];
					}

					if (detailType.getVariety() == HVariety.GEOGRAPHIC) {
						for (var v = 0; v < vals.length; ++v) {
							vals[v] = new HGeographicValue(HGeographicType.abbreviationForType(FlexImport.subTypes[j]), vals[v]);
						}
					} else if (detailType.getVariety() == HVariety.REFERENCE) {
						// FIXME add code to load object(s) for reference and verify that it's the constrained type. output log entry for invalid data
						if (vals.length == 1 && vals[0].indexOf(",",0) != -1) {	// ??? how is this possible - multi-valued in a non-repeating field, shouldn't we select first and warn user
							vals = vals[0].split(",");
						}
						var l = vals.length;
						for (var v = 0; v < l; ++v) {
							var temp = vals[v];
							if(parseInt(vals[v])<1){
								logError(j," Record id is not defined");
							}else{
							vals[v] = HeuristScholarDB.getRecord(parseInt(vals[v]));
							if (!vals[v]) { //there was an error loading the referenced record so mark it
								logError(j," Record id:" + temp + " not found.");
								vals.splice(v,1);	// remove the val in order to ignore it
								v--;
								l = vals.length;
							}else if (detailType.getConstrainedRecordType &&	//check to make sure the record matches constraints
										detailType.getConstrainedRecordType() &&
										detailType.getConstrainedRecordType().getID() != vals[v].getRecordType().getID()){
								logError(j,"Constraint error - Record id:" + temp + " is not type: " + detailType.getConstrainedRecordType().getName() );
							}
							}
						}
					}
					// FIXME now we should also validate against constraints table for enum values.
					if (vals) {
						hRec.setDetails(detailType, vals);
					}
				}
			}catch(e) {
				logError(j,e);
			}
		}
		if ((!err || !err.invalidRecord) && !hRec.isValid()) { // if record is invalid and hasn't been flagged yet, must be a missing req detail
			logError("invalidRecord", " Missing unknown required detail.");
		}
		return {record : hRec, error : err};
	},

	outputCSV: function () {
		var $textarea, l, i, k, j, line, recordIDColumn, recordID, dtID;

		var csvField = function (s) {
			if (s.match(/"/)) {
				return '"' + s.replace(/"/g, '""') + '"';
			}
			else if (s.match(/,/)) {
				return '"' + s + '"';
			}
			else return s;
		};

		$textarea = $("<textarea id='csv-textarea'>");
		$("<div>").append($textarea).appendTo("body");

		line = [];
		l = FlexImport.cols.length;
		for (i = 0; i < l; ++i) {
			if (FlexImport.cols[i]) {
				if (recordIDColumn == undefined) {// insert the rec id just before the first import data column
					recordIDColumn = i;
					line.push(FlexImport.recType.getName() + " Record ID");
				}
				dtID = parseInt(FlexImport.cols[i]);
				if (dtID > 0) {
					line.push(HDetailManager.getDetailTypeById(dtID).getName());
				}
				else {
					line.push(FlexImport.cols[i]);
				}
			}
			else { // column not imported let the user know
				line.push("not imported");
			}
		}
		$textarea.append(line.join() + "\n");

		$("#result-message").html('Your data was successfully imported and has been rewritten below. <br/>Record Ids have been inserted into your original data in column ' + (recordIDColumn + 1) +
					' below. <br/> Note that columns not used in the import are identified in the header row as "not imported"');

		l = FlexImport.fields.length;
		for (i = 0; i < l; ++i) {
			recordID = FlexImport.lineRecordMap[i].getID();
			line = [];
			k = FlexImport.fields[i].length;
			for (j = 0; j < k; ++j) {
				if (j === recordIDColumn) {
					line.push(recordID);
				}
				line.push(csvField(FlexImport.fields[i][j]));
			}
			$textarea.append(line.join() + "\n");
		}
	}
	};

})();


FlexImport.Loader = (function () {
	// helper function to load all records for a given query
	// this is necessary because of the page limit set in HAPI
	function _loadAllRecords (query, options, loader) {
		var records = [];
		var baseSearch = new HSearch(query, options);
		var bulkLoader = new HLoader(
			function(s, r) {	// onload
				records.push.apply(records, r);
				if (r.length < 100) {
					// we've loaded all the records: invoke the original loader's onload
					$("#results").html('<b>Loaded ' + records.length + ' records </b>');
					loader.onload(baseSearch, records);
				}
				else { // more records to retrieve
					$("#results").html('<b>Loaded ' + records.length + ' records so far ...</b>');

					//  do a search with an offset specified for retrieving the next page of records
					var search = new HSearch(query + " offset:"+records.length, options);
					HeuristScholarDB.loadRecords(search, bulkLoader);
				}
			},
			loader.onerror
		);
		HeuristScholarDB.loadRecords(baseSearch, bulkLoader);
	}

	return {
		//loads records from server using a helper function to ensure that we get all the records
		loadRecords: function (myquery) {
			var loader = new HLoader(
				function(s, r) { // onload
					if(FlexImport.fields){
						$("#results").html('<b>Loaded ' + FlexImport.fields.length + ' records </b>');
					}else{
						$("#results").html('<b>No records loaded</b>');
					}
					FlexImport.createRecords();
				},
				function(s,e) { // onerror
					alert("failed to load: " + e);
				}
			);
			_loadAllRecords(myquery, null, loader);
		}
	};
})();


FlexImport.Saver = (function () {

	//loads chunkSize records into the savRecord array
	function _getChunk() {
		var i;
		FlexImport.recEnd = FlexImport.recStart + FlexImport.constChunkSize;
		if (FlexImport.recEnd > FlexImport.records.length) {
			FlexImport.recEnd = FlexImport.records.length;
		}

		FlexImport.SavRecordChunk = [];

		if (FlexImport.recEnd > FlexImport.recStart) {
			//get a Chunk of records to save
			for (i=0; i < (FlexImport.recEnd-FlexImport.recStart); i++) {
				FlexImport.SavRecordChunk[i] = FlexImport.records[i+FlexImport.recStart];
			}
			_saveChunk();
		} else {
			// finished
			FlexImport.outputCSV();
		}
	}

	//saves the records in savRecords array
	function _saveChunk() {

			var saver = new HSaver(
			function(r) {
				$("#rec-type-select-div").empty();
				$("#rec-type-select-div").empty();
				$("#records-div").html("Saved <b>" + FlexImport.recEnd+ "</b> records");
                // r.length gives the number of records in the chunk being saved
				FlexImport.recStart = FlexImport.recEnd;
				_getChunk();
			},
			function(r,e) {
				alert("record save failed: " + e);
			});
		HeuristScholarDB.saveRecords(FlexImport.SavRecordChunk, saver);

	}

	return {
		saveRecords: function () {
			if (! confirm("This will attempt to save all the displayed records to " +
					(HAPI.database ? "the \""+HAPI.database+"\" Heurist database" : "Heurist") + ". Are you sure you want to continue?")) return;

			_getChunk();
		}
	};
})();
