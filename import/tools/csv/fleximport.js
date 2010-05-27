FlexImport = (function () {

function _addOpt(sel, val, text) {
	return $("<option>")
		.val(val)
		.html(text)
		.appendTo(sel)[0];
}

return {

	fields: [],
	lineHashes: {},
	columnCount: 0,
	recTypeSelect: null,
	recType: null,
	workgroupSelect: null,
	workgroups: {},
	workgroupKeywords: {},
	colSelectors: [],
	cols: [],
	subTypes: [],
	records: [],
	lineRecordMap: {},
	lineErrorMap: {},
	constChunkSize: 5, // controls the number of records per request for saving records
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
		var separator = $("#csv-separator").val();
		var terminator = $("#csv-terminator").val();
		var quote = $("#csv-quote").val();
		var lineRegex, fieldRegex, doubleQuoteRegex;

		if (terminator == "\\n") terminator = "\n";

		var switches = (terminator == "\n") ? "m" : "";

		if (quote == "'") {
			lineRegex = new RegExp(terminator + "(?=(?:[^']*'[^']*')*(?![^']*'))", switches);
			fieldRegex = new RegExp(separator + "(?=(?:[^']*'[^']*')*(?![^']*'))", switches);
		} else {
			lineRegex = new RegExp(terminator + "(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))", switches);
			fieldRegex = new RegExp(separator + "(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))", switches);
		}
		doubleQuoteRegex = new RegExp(quote + quote, "g");

		var lines = $("#csv-textarea").val().split(lineRegex);
		var i, l = lines.length;
		for (i = 0; i < l; ++i) {
			if (lines[i].length > 0) {
				FlexImport.fields[i] = lines[i].split(fieldRegex);
				for (var j = 0; j < FlexImport.fields[i].length; ++j) {
					FlexImport.fields[i][j] = FlexImport.fields[i][j].replace(doubleQuoteRegex, quote);
					FlexImport.columnCount = Math.max(FlexImport.columnCount, j + 1);
				}
			}
		}
		//we have parsed the input so remove the textarea
		$("#csv-entry-div").remove();

		$("#info-p").html(
			"Found <b>" + FlexImport.fields.length + "</b> rows of data," +
			" in <b>" + FlexImport.fields[0].length + "</b> columns. " +
			"<a href='#' onclick=\"window.location.reload(); return false;\">Start over</a>"
		);

		FlexImport.createRecTypeOptions();
	},

	createRecTypeOptions: function () {
		var e = $("#rec-type-select-div")[0];
		e.appendChild(document.createTextNode("Select record type: "));
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

		var p = e.appendChild(document.createElement("p"));
		p.appendChild(document.createTextNode("Workgroup for tags: "));
		FlexImport.workgroupSelect = p.appendChild(document.createElement("select"));
		FlexImport.workgroupSelect.onchange = function() {
			FlexImport.workgroupKeywords = {};
			if (this.value) {
				var wgkwds = HKeywordManager.getWorkgroupKeywords(FlexImport.workgroups[this.value]);
				var i, l = wgkwds.length;
				for (i = 0; i < l; ++i) {
					FlexImport.workgroupKeywords[wgkwds[i].getName()] = wgkwds[i];
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
		p.appendChild(document.createTextNode(" (NOTE: Workgroup tags must be pre-existing!  Create them "));
		var a = p.appendChild(document.createElement("a"));
			a.target = "_blank";
			a.href = HAPI.HeuristBaseURL + "admin/workgroups/workgroup_keyword_manager.php"
			a.innerHTML = "here";
		p.appendChild(document.createTextNode(" then start over.)"));

		p = e.appendChild(document.createElement("p"));
		p.appendChild(document.createTextNode("Select column assignments, then "));
		var button = p.appendChild(document.createElement("input"));
			button.type = "button";
			button.value = "create records";
			button.onclick = function() { FlexImport.loadReferencedRecords(); };
		p.appendChild(document.createTextNode(" (doesn't save to Heurist yet)"));

		p = e.appendChild(document.createElement("p"));
		a = p.appendChild(document.createElement("a"));
		a.target = "_blank";
		a.href = HAPI.HeuristBaseURL +"admin/describe/bib_detail_dump.php#rt" + FlexImport.recType.getID();
		a.innerHTML = "Detail requirements for " + FlexImport.recType.getName() + " records";


		var table = document.createElement("table");
		table.id = "col-select-table";
		var tbody = table.appendChild(document.createElement("tbody"));
		var tr = tbody.appendChild(document.createElement("tr"));
		tr.id = "col-select-row";
		var td, sel, opt;
		td = tr.appendChild(document.createElement("td"));
		var i, l = FlexImport.columnCount;
		for (i = 0; i < l; ++i) {
			// add column select header for selecting detail type for this column
			td = tr.appendChild(document.createElement("td"));
			sel = td.appendChild(document.createElement("select"));
			sel.onchange = function() {
				if (this.value != "tags"  &&  this.value != "keywords") {
					//search if this detail is in another column and remove it from the other column if it is
					var j, m = FlexImport.colSelectors.length;
					for (j = 0; j < m; ++j) {
						var s = FlexImport.colSelectors[j];
						if (s != this  &&  s.value == this.value) {
							s.selectedIndex = 0;
							if (s.subTypeSelect) {
								s.parentNode.removeChild(s.subTypeSelect);
							}
						}
					}
				}
				// for types that have subtypes show select for subtypes
				if (this.value != "url"  &&  this.value != "notes"  &&
					this.value != "tags"  &&  this.value != "keywords"  &&
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
			opt = sel.appendChild(document.createElement("option"));
			opt.value = null; opt.innerHTML = "do not import";
			_addOpt(sel, "url", "URL");
			_addOpt(sel, "notes", "Notes");
			_addOpt(sel, "tags", "Tag(s)");
			_addOpt(sel, "keywords", "Workgroup Tag(s)");

			var reqDetailTypes = HDetailManager.getRequiredDetailTypesForRecordType(FlexImport.recType);
			var detailTypes = HDetailManager.getDetailTypesForRecordType(FlexImport.recType);
			var d, rdl = reqDetailTypes.length;
			var dl = detailTypes.length;
			var rdIndex = {};
			var grp = sel.appendChild(document.createElement("optgroup"));
			grp.label = "Required Fields";
			for (d = 0; d < rdl; ++d) {
				var rdID = reqDetailTypes[d].getID();
				var opt = _addOpt(sel, rdID,  HDetailManager.getDetailNameForRecordType(FlexImport.recType, reqDetailTypes[d]));
				opt.className = "required";
				for (var r = 0; r < dl; ++r){
					if (rdID == detailTypes[r].getID()) {
						rdIndex[r] = true;
					}
				}
			}
			grp = sel.appendChild(document.createElement("optgroup"));
			grp.label = "Other Fields";
			for (d = 0; d < dl; ++d) {
				if (rdIndex[d]) continue;
				var opt = _addOpt(sel, detailTypes[d].getID(), HDetailManager.getDetailNameForRecordType(FlexImport.recType, detailTypes[d]));
			}
		}

		// create rest of table filling it with the csv analysed data
		for (var i = 0; i < FlexImport.fields.length; ++i) {
			var inputRow = FlexImport.fields[i];
			tr = tbody.appendChild(document.createElement("tr"));
			if (FlexImport.lineErrorMap[i] && FlexImport.lineErrorMap[i].invalidRecord){
				tr.className = "invalidRecord";
			}
			td = tr.appendChild(document.createElement("td"));
			td.innerHTML = i + 1; //row number
			//for each column
			for (var j = 0; j < FlexImport.columnCount; ++j) {
				td = tr.appendChild(document.createElement("td"));
				if (inputRow.length > j) {
					//strip quotes if necessary
					if (inputRow[j].toString().match("\"")) {
						inputRow[j] = inputRow[j].replace(/"/g, "");
					}
					if (FlexImport.lineErrorMap[i] && FlexImport.lineErrorMap[i][j]){
						td.className = "invalidInput";
						var temp = "";
						if (inputRow[j]) {
							temp += "Correct value : " + inputRow[j];
						}else {
							temp += "Enter a correct value here."
						}
						var ed = td.appendChild(document.createElement("input"));
						ed.value = temp;
						ed.className = "invalidInput";
						ed.cleared = false;
						ed.onfocus = function () {
								if (!this.cleared) {
									this.value = "";
									this.cleared = true;
								}
						};
						ed.row = i;
						ed.col = j;
						ed.onblur = function () {
							if (this.cleared) {
								FlexImport.fields[this.row][this.col] = this.value;
							}
						};
						var p = td.appendChild(document.createElement("p"));
						p.className = "errorMsg";
						p.innerHTML = FlexImport.lineErrorMap[i][j];
					} else {
						td.appendChild(document.createTextNode(inputRow[j]));
					}
				}
			}
		}

		e.appendChild(table);
	},

	// This function preloads all records necessary for REFERENCE detail types
	loadReferencedRecords: function () {
		var detailType;
		var refCols = [];
		var recIDs = [];
		var recID = "";
		var valCheck = {};

		var i, l = FlexImport.colSelectors.length;
		for (i = 0; i < l; ++i) {
			if (FlexImport.colSelectors[i].selectedIndex > 0) {
				FlexImport.cols[i] = FlexImport.colSelectors[i].value;
			}
			FlexImport.subTypes[i] = FlexImport.colSelectors[i].subTypeSelect ? FlexImport.colSelectors[i].subTypeSelect.value : null;
			if ( FlexImport.cols[i]  &&  FlexImport.cols[i]!=="tags"   &&  FlexImport.cols[i]!== "keywords" && FlexImport.cols[i] !== "url" && FlexImport.cols[i] !== "notes") {
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
				FlexImport.cols[j] != "keywords") {
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

		// show command button for saving records
		var e = $("#records-div")[0];
		e.innerHTML = "<p>If you are happy with the records created below, then <input type=button value=\"save records\" onclick=\"FlexImport.Saver.saveRecords();\"></p>";
		e.innerHTML += "<p>If you would like to edit the input, then press <input type=button value=\"edit input data\" onclick=\"FlexImport.createColumnSelectors();\"></p>";
		e.innerHTML += "<p>records created:</p>";
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
			if (! FlexImport.cols[j]  ||  (FlexImport.cols[j]=="tags" && tags)  ||  (FlexImport.cols[j]=="keywords" && kwds)) continue;
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
			} else if (FlexImport.cols[j] == "keywords") {
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
		// create records
		l = FlexImport.fields.length;
		for (var i = 0; i < l; ++i) {

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
				if (! FlexImport.cols[j]  ||  (FlexImport.cols[j]=="tags" && tags)  ||  (FlexImport.cols[j]=="keywords" && kwds)) continue;
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
				} else if (FlexImport.cols[j] == "keywords") {
					if (! kwds) {
						kwds = true;
						var temp = "<p>";
						var ks = record.getKeywords();
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
				}
			} // for j in FlexImport.fields loop
		} // for i = 0 loop
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

		if (recType.getID() == 52) {
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
				} else if (FlexImport.cols[j] == "keywords") {
					var vals = val.split(",");
					for (var v = 0; v < vals.length; ++v) {
						if (FlexImport.workgroupKeywords[vals[v]]) {
							hRec.addKeyword(FlexImport.workgroupKeywords[vals[v]])
						}
					}
				} else if (FlexImport.cols[j]) { // detail is generic so prepare to addDetail
					detailType = HDetailManager.getDetailTypeById(FlexImport.cols[j]);
					var vals;
					if (HDetailManager.getDetailRepeatable(recType, detailType)) {
						vals = val.split( detailType.getVariety() == HVariety.GEOGRAPHIC ? "|" : "," ); //FIXME multi-valued fields from CSV format might have | as delimiter
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
			logError("invalidRecord", " Missing unknown reqired detail.");
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
				if (recordIDColumn == undefined) {
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
			else {
				line.push("");
			}
		}
		$textarea.append(line.join() + "\n");

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
				$("#records-div").html("<b>" + r.length + "</b> records saved for a total of " + FlexImport.recEnd);
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
					(HAPI.instance ? "the \""+HAPI.instance+"\" Heurist instance" : "Heurist") + ". Are you sure you want to continue?")) return;

			_getChunk();
		}
	};
})();
