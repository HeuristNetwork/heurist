/*
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 */

/**
* Constructor for Relationship class a relationship display line. It displays the Relation Type
* with the record title, delete button and edit button.
* @author Tom Murtagh
* @author Kim Jackson
* @author Stephen White
* @param parentElement a DOM element where the Relationship will be displayed
* @param relationshipRec a reference to an object containing the record details for the relationship record
* @param manager a reference to the RelationManager that manages this Relationship Object
*/
var Relationship = function(parentElement, relationshipRec, manager) {
	var elt = parentElement;
	do { elt = elt.parentNode; } while (elt.nodeType != 9 /* DOCUMENT_NODE */);
	this.document = elt;

	var thisRef = this;

	this.relationshipRec = relationshipRec;
	this.manager = manager;

	this.tr = this.document.createElement("div");
	this.tr.className = "relation";

	var deleteTd = this.tr.appendChild(this.document.createElement("div"));
	deleteTd.className = "delete";
	deleteTd.title = "Delete this relationship";
	deleteTd.appendChild(this.document.createElement("img")).src = top.HEURIST.basePath + "common/images/cross.gif";
	deleteTd.onclick = function() { thisRef.remove(); };
	var editTd = this.tr.appendChild(this.document.createElement("div"));
	editTd.className = "edit";
	editTd.title = "Edit this relationship";
	editTd.appendChild(this.document.createElement("img")).src = top.HEURIST.basePath + "common/images/edit-pencil.png";
	editTd.onclick = function() { thisRef.edit(); };

	this.relSpan = this.tr.appendChild(this.document.createElement("div")).appendChild(this.document.createElement("div"));
	this.relSpan.parentNode.className = "rel";
	this.relSpan.appendChild(this.document.createTextNode(relationshipRec.relTerm));

	this.titleSpan = this.tr.appendChild(this.document.createElement("div")).appendChild(this.document.createElement("div"));
	this.titleSpan.parentNode.className = "title";
	this.titleSpan.appendChild(this.document.createTextNode((relationshipRec.relatedRec? relationshipRec.relatedRec.title : relationshipRec.title)));

//	this.ellipsesTd1 = this.tr.appendChild(this.document.createElement("td"));
//	this.ellipsesTd1.className = "ellipses";
//	this.ellipsesTd1.innerHTML = "&nbsp;...";

	this.datesTd = this.tr.appendChild(this.document.createElement("div")).appendChild(this.document.createElement("div"));
	this.datesTd.parentNode.className = "dates";
	if (relationshipRec.startDate) {
		this.datesTd.appendChild(this.document.createTextNode(relationshipRec.startDate));
		if (relationshipRec.endDate) {
			this.datesTd.appendChild(this.document.createTextNode(" - " + relationshipRec.endDate));
		}
	}

	this.notesField = this.tr.appendChild(this.document.createElement("div")).appendChild(this.document.createElement("div"));
	this.notesField.parentNode.className = "notes-field";
	this.notesField.appendChild(this.document.createTextNode(relationshipRec.notes || ""));

//	this.ellipsesTd2 = this.tr.appendChild(this.document.createElement("td"));
//	this.ellipsesTd2.className = "ellipses";
//	this.ellipsesTd2.innerHTML = "&nbsp;...";

	parentElement.appendChild(this.tr);
};

function countObjElements(obj) {
	var i =0;
	for ( var j in obj) i++;
	return i;
}

/**
* Helper function that lanches the mini-edit.html popup with the record id of the relationship record.
* @author Tom Murtagh
* @author Kim Jackson
* @author Stephen White
*/
Relationship.prototype.edit = function() {
	var thisRef = this;
	top.HEURIST.util.popupURL(window, top.HEURIST.basePath + "records/edit/formEditRecordPopup.html?bib_id="+this.relationshipRec.relnID,
	{ callback: function(newRecTitle, newDetails) {
			if (newRecTitle) {	//saw this gets a title from the record which does match the inital title format and mini-edit always returns a title.
				thisRef.titleSpan.innerHTML ="";
				var newTitle = "" +	//saw Enum change
					(newDetails['200'][0]['enumValue'] ?newDetails['200'][0]['enumValue'] :
						newDetails['200'][0]['value'] ? newDetails['200'][0]['value']: "" ) +
					": "  +
					(newDetails['199'][0]['title'] ?newDetails['199'][0]['title'] :
						newDetails['199'][0]['value'] ? newDetails['199'][0]['value']: "" );
				thisRef.titleSpan.appendChild(thisRef.document.createTextNode(" " + newTitle + " "));
			}
			/* TFM 2008/01/09 ... we don't do notes in the short view anymore
			if (newDetails  &&  newDetails[201]) {
			thisRef.notesDiv.innerHTML = "";
			thisRef.notesDiv.appendChild(thisRef.document.createTextNode(newDetails[201][0]));
			}
			*/
		}
	});
	return false;
};
Relationship.prototype.remove = function() {
	this.tr.parentNode.removeChild(this.tr);
	this.manager.remove(this);
};

//**********************************************************************************
var EditableRelationship = function(parentElement, relationshipRec, rectypes, dtID, manager) {
	var elt = parentElement;
	do { elt = elt.parentNode; } while (elt.nodeType != 9 /* DOCUMENT_NODE */);
	this.document = elt;

	var rectype = null;
	if (rectypes && rectypes.search(",") != -1) {
		this.rectypes = rectypes;
		rectype = rectypes.split(",")[0]; // get the first
	}
	var thisRef = this;
	this.manager = manager;

	if (relationshipRec) {
		this.relationshipRec = relationshipRec;
	}
	else {
		this.relationshipRec = {
			relnID:0,
			role:"",
			relTerm: "",
			relTermID: "",
			relInvTerm: "",
			relInvTermID: "",
			relatedRec: { title: "", rectype: (rectypes ? rectypes : 0), URL: "", recID: 0 },
			intrpRec: { title: "", rectype: "182", URL: "", recID: 0 },
			notes: "",
			title: "",
			startDate: null,
			endDate: null
		};
	}

	this.div = parentElement.appendChild(this.document.createElement("div"));
	this.div.className = "relation editable";

	this.header = this.div.appendChild(this.document.createElement("div"));
	this.header.className = "header";
	this.header.style.marginBottom = "0.5ex";
	this.header.appendChild(this.document.createTextNode("Add new relationship"));

	var tbody = this.div.appendChild(this.document.createElement("div"));
	tbody.className = "resource";

/*	var tr = tbody.appendChild(this.document.createElement("div"));
	tr.className = "input-row";
	var td = tr.appendChild(this.document.createElement("div"));
	td.className = "input-header-cell";
	td.appendChild(this.document.createTextNode("Using Vocabulary"));

	td = tr.appendChild(this.document.createElement("div"));
	this.relVocab = td.appendChild(this.document.createElement("select"));
	this.relVocab.id = "vocabulary";
	this.relVocab.name = "vocabulary";
	var firstOption = this.relVocab.options[0] = new Option("(show all vocabularies)", "");
	firstOption.selected = true;

	var relVocabs = top.HEURIST.vocabLookup.relationships;
	for (var vocab in relVocabs) {
		if (!countObjElements(top.HEURIST.vocabTermLookup[vocab])) continue;
		this.relVocab.options[this.relVocab.length] = new Option(relVocabs[vocab],vocab);
		if (vocab == relVocabulary)
			this.relVocab.value = relVocabulary;
	}
*/
	tr = tbody.appendChild(this.document.createElement("div"));
	tr.className = "input-row";
	td = tr.appendChild(this.document.createElement("div"));
	td.className = "input-header-cell";
	td.appendChild(this.document.createTextNode("This record"));

	td = tr.appendChild(this.document.createElement("div"));
	this.relTypeSelect = td.appendChild(
							top.HEURIST.edit.createTermSelect(this.manager.relTerms,
																(this.termHeadersList || ""),
																top.HEURIST.terms.termsByDomainLookup.relation,
																null));
	this.relTypeSelect.id = "relationship-type";
	this.relTypeSelect.name = "relationship-type";
/*	var firstOption = this.relTypeSelect.options[0] = new Option("(select relationship type)", "");
	firstOption.disabled = true;
	firstOption.selected = true;


	// saw TODO fileter the terms list  - only allow selection on relations which are not at their limit.
	// saw TODO later - we need to also check the limits for the Target Rectypes and constrain terms accordingly
	if (dtID && dtID != 200) {
		var allowedLookups = top.HEURIST.edit.getLookupConstraintsList(dtID);
	}
	var relTypes = top.HEURIST.vocabTermLookup[200];  //saw need to change restricted reltypes from rel_constraints pass in a param
	for (var ont in relTypes) {
		var grp = document.createElement("optgroup");
		grp.label = top.HEURIST.vocabLookup[ont];
		this.relTypeSelect.appendChild(grp);
		for (var i = 0; i < relTypes[ont].length; ++i) {
			var rdl = relTypes[ont][i];
			if (allowedLookups && allowedLookups[rdl[0]] === undefined) continue;
			this.relTypeSelect.options[this.relTypeSelect.length] = new Option(rdl[1], rdl[0]);
			if (ont == 1 && "IsRelatedTo" === rdl[1])
				this.relTypeSelect.value = rdl[0];
		}
	}
*/
	// filter rectypes for constraints
	var fakeBDT = ["Related record","", "resource",
					null,null,1,null,
					null,null,null,null,
					this.rectypes? this.rectypes : (rectypes ? rectypes : 0),199];
	var fakeBDR = ["Related record", "", "",null,"required",
					1,1,60,null,null,
					null,null,this.rectypes? this.rectypes : (rectypes ? rectypes : 0),null,null,
					null,null,null,null];
	this.relatedRecord = new top.HEURIST.edit.inputs.BibDetailResourceInput(fakeBDT, fakeBDR, [], tbody);
	this.relatedRecordID = this.relatedRecord.inputs[0].hiddenElt;


	tr = tbody.appendChild(this.document.createElement("div"));
	tr.className = "input-row";
	td = tr.appendChild(this.document.createElement("div"));
		td.className = "input-header-cell";
	td = tr.appendChild(this.document.createElement("div"));
		td.className = "input-cell";

	var saveButton = this.document.createElement("input");
	saveButton.type = "button";
	saveButton.style.fontWeight = "bold";
	saveButton.value = "Add relationship";
	saveButton.onclick = function() { thisRef.save(); };

	td.appendChild(saveButton);


	var cancelButton = this.document.createElement("input");
	cancelButton.type = "button";
	cancelButton.value = "Cancel";
	cancelButton.onclick = function() { thisRef.remove(); };

	td.appendChild(cancelButton);

	// insert a div for optional items
	opt = tbody.appendChild(this.document.createElement("div"));
	opt.className = "resource optional";

	tr = opt.appendChild(this.document.createElement("div"));
	tr.className = "input-row optional";
	td = tr.appendChild(this.document.createElement("div"));
	td.className = "section-header-cell optional";
	td.appendChild(this.document.createTextNode("Optional Fields"));

	fakeBDT = ["Interpretation","", "resource",
					null,null,1,null,
					null,null,null,null,
					182,638];
	var helpString = "Record the evidence and/or reasoning on which this relationship is based";
	fakeBDR = ["Interpretation", helpString, "",null,"optional",
					1,0,60,null,null,
					null,null,182,null,null,
					null,null,null,null];
	this.interpResource = new top.HEURIST.edit.inputs.BibDetailResourceInput(fakeBDT, fakeBDR, [], opt);
	this.interpResourceID = this.interpResource.inputs[0].hiddenElt;

	tr = opt.appendChild(this.document.createElement("div"));
	tr.className = "input-row optional";
	td = tr.appendChild(this.document.createElement("div"));
	td.className = "input-header-cell";
	td.appendChild(this.document.createTextNode("Validity"));
	td = tr.appendChild(this.document.createElement("div"));

	this.startDate = td.appendChild(this.document.createElement("input"));
	this.startDate.className = "in";
	this.startDate.style.width = "90px";
	top.HEURIST.edit.makeDateButton(this.startDate, this.document);	//saw TODO makeTemporal for Temporal switch

	var untilSpan = td.appendChild(this.document.createElement("span"));
	untilSpan.appendChild(this.document.createTextNode("until"));
	untilSpan.style.padding = "0 1em";

	this.endDate = td.appendChild(this.document.createElement("input"));
	this.endDate.className = "in";
	this.endDate.style.width = "90px";
	top.HEURIST.edit.makeDateButton(this.endDate, this.document);//saw TODO makeTemporal for Temporal switch

	tr = opt.appendChild(this.document.createElement("div"));
	tr.className = "input-row optional";
	td = tr.appendChild(this.document.createElement("div"));
	td.className = "input-header-cell";
	td.appendChild(this.document.createTextNode("Description"));
	td = tr.appendChild(this.document.createElement("div"));
	this.description = td.appendChild(this.document.createElement("input"));
	this.description.value = "Relationship";
	this.description.className = "in";

	tr = opt.appendChild(this.document.createElement("div"));
	tr.className = "input-row optional";
	td = tr.appendChild(this.document.createElement("div"));
	td.className = "input-header-cell";
	td.appendChild(this.document.createTextNode("Notes"));
	td = tr.appendChild(this.document.createElement("div"));
	this.notes = td.appendChild(this.document.createElement("textarea"));
	this.notes.className = "in";
};

EditableRelationship.prototype.save = function() {
	if (! (this.relTypeSelect.value  ||  this.relatedRecordID.value  ||  this.startDate.value  ||  this.endDate)) return;

	if (this.relTypeSelect.value == "") {
		alert("You must select a relationship type");
		return;
	}
	if (this.relatedRecordID.value == ""  ||  this.relatedRecordID.value == 0) {
		alert("You must select a related record");
		return;
	}

	var fakeForm = { action: top.HEURIST.basePath +"records/relationships/saveRelationships.php",
		elements: [
		{ name: "recID", value: this.manager.recID},
		{ name: "save-mode", value: "new" },
		{ name: "RelTermID", value: this.relTypeSelect.value },	//saw Enum change - nothing to do on the save side value is the id
		{ name: "RelatedRecID", value: this.relatedRecordID.value },
		{ name: "InterpRecID", value: this.interpResourceID.value },
		{ name: "Notes", value: this.notes.value },
		{ name: "Title", value: this.description.value },
		{ name: "StartDate", value: this.startDate.value },
		{ name: "EndDate", value: this.endDate.value }
		] };
	var thisRef = this;
	var windowRef = window;

	top.HEURIST.util.xhrFormSubmit(fakeForm, function(json) {
		var vals = eval(json.responseText);
		if (! vals) return;

		if (vals.error) {
			alert("Error while saving:\n" + vals.error);
		}else if (vals.relationship) {
			parent.HEURIST.edit.record.relatedRecords = vals.relationship;

			var myTR = thisRef.div.parentNode.parentNode;
			var newReln = new Relationship(myTR.parentNode, vals.relationship.relationshipRecs[vals.relnRecID],thisRef.manager);
			myTR.parentNode.insertBefore(newReln.tr, myTR.nextSibling); //saw might be better to store myTR.parentNode as thisref.container

			thisRef.clear();

			var prevRelnDiv = windowRef.document.getElementById("newly-added");
			if (prevRelnDiv) prevRelnDiv.id = "";

			newReln.tr.id = "newly-added";

			thisRef.manager.remove(thisRef);
		}
	});
};

EditableRelationship.prototype.clear = function() {
	//this.relTypeSelect.selectedIndex = 0;	// saw removed to maintain the selected value for repeated entries of the type relation
	this.relatedRecord.inputs[0].hiddenElt.value = "0";
	this.relatedRecord.inputs[0].textElt.value = "";
	this.interpResource.inputs[0].hiddenElt.value = "0";
	this.interpResource.inputs[0].textElt.value = "";
	this.description.value = "Relationship";
	this.notes.value = "";
	this.startDate.value = "";
	this.endDate.value = "";
};

EditableRelationship.prototype.remove = function() {
	var thisRef = this;
	setTimeout(function() {
		thisRef.clear();

		var myTD = thisRef.div.parentNode;
		var myTR = myTD.parentNode;
		myTD.removeChild(thisRef.div);
		if (myTD.childNodes.length === 0  &&  myTR.childNodes.length === 1) {
			myTR.parentNode.removeChild(myTR);
		}
		thisRef.manager.remove(thisRef);
	}, 0);
};

/**
* Constructor for RelationManager class which manages the relation display objects for a record.
* It displays the Relation Type with the record title, delete button and edit button.
* @author Tom Murtagh
* @author Kim Jackson
* @author Stephen White
* @param parentElement a DOM element where the Relationship will be displayed
* @param rectypeID the id of the type of the record being managed
* @param dtIDRelmarker the id of the detail (relmarker) type, can be omitted
* @param relatedRecords array of records related to the managed record indexed by recID, can be null
* @param changeNotification a callback for notifying the owner that the relations in this manager have changed
* @param supressHeaders a boolean indicating where to show headers for teh different types of relations.
*/
var RelationManager = function(parentElement, record, relatedRecords, dtIDRelmarker, changeNotification, supressHeaders) {
	if (!parentElement || isNaN(record.recID)) return null;
	var thisRef = this;
	this.parentElement = parentElement;
	this.rectypeID = parseInt(record.rectypeID);
	this.recID = parseInt(record.recID);
	if (dtIDRelmarker) {
		this.dtID = dtIDRelmarker;
	}

	// save change notification callback in case we change something this should trigger a screen refresh
	if (changeNotification) {
		this.changeNotification = changeNotification;
	}

	//get all constraints for src rectype or global constraints
	this.constraints = (top.HEURIST.rectypes.constraints[this.rectypeID] || top.HEURIST.rectypes.constraints['0']);

	if (dtIDRelmarker) { // we are dealing with a relmark so get definitions and process them for UI
		// get any trgPointer restrictions
		var trgRectypeList = temp = top.HEURIST.rectypes.typedefs[this.rectypeID].dtFields[dtIDRelmarker][12];
		var targetRectypes = {};
		if (temp) {
			temp = temp.split(",");
			for (var i = 0; i < temp.length; i++) {
				targetRectypes[temp[i]] = temp[i];
			}
			this.trgRectypes = targetRectypes;
		}

		// get HeaderTerms list - the values from the structure can be null
		var dtyHdr = top.HEURIST.rectypes.typedefs[this.rectypeID].dtFields[dtIDRelmarker][17];
		var rfrHdr = top.HEURIST.rectypes.typedefs[this.rectypeID].dtFields[dtIDRelmarker][13];
		temp = (dtyHdr? (rfrHdr? dtyHdr.concat(",",rfrHdr):dtyHdr) : rfrHdr);
		var headerList = {};
		if (temp) {
			temp = temp.split(",");
			for (var i = 0; i < temp.length; i++) {
				headerList[temp[i]] = temp[i];
			}
			this.termHeadersList = headerList;
		}

		// get relationship terms from relmarker definition
		this.relTerms = eval(top.HEURIST.rectypes.typedefs[this.rectypeID].dtFields[dtIDRelmarker][11]);
	}
	if (!this.relTerms) { // if no terms were setup in a relmarker then default to all relationships = unconstrained
		this.relTerms = top.HEURIST.terms.treesByDomain.relation;
	}

	// set up structure for the related records
	if (relatedRecords) {
		if (typeof this.trgRectypes == "object") { // need to only use related in this set
			var found = false;
			var relRecs = null;
			for (relnID in relatedRecords.relationshipRecs) {
				if (this.trgRectypes[relatedRecords.relationshipRecs[relnID].relatedRec.rectype]) {
					if (!found) {
						found = true;
						relRecs = {'rels':{}, 'byT':{}, 'byRt':{}};
			}
					relRecs.rels[relnID] = relatedRecords.relationshipRecs[relnID];
					var relRectype = relRecs.rels[relnID].relatedRec.rectype;
					var relnType = relRecs.rels[relnID].termID;
					if (!relRecs.byT[relnType]) {
						relRecs.byT[relnType] = {};
			}
					if (!relRecs.byT[relnType][relRectype]) {
						relRecs.byT[relnType][relRectype] = [relnID];
					} else {
						relRecs.byT[relnType][relRectype].push(relnID);
					}
					if (!relRecs.byRt[relRectype]) {
						relRecs.byRt[relRectype] = {};
					}
					if (!relRecs.byRt[relRectype][relnType]) {
						relRecs.byRt[relRectype][relnType] = [relnID];
					} else {
						relRecs.byRt[relRectype][relnType].push(relnID);
					}
				}
			}
			if (found) {
				this.relatedRecords = relRecs.rels;
				this.relnIDs = {};
				this.relnIDs.byTerm = relRecs.byT;
				this.relnIDs.byRectype = relRecs.byRt;
			}
		} else {
			this.relatedRecords = relatedRecords.relationshipRecs;
			this.relnIDs = {};
			this.relnIDs.byTerm = relatedRecords.byTerm;
			this.relnIDs.byRectype = relatedRecords.byRectype;
		}
	}
		// calculate constraints for termset and constrained rectypes removing existing record counts
		// if 0 then warn if over limit then flag error
	if (false  && dtIDRelmarker) {
		if (typeof targetRectypes == "object") {
			for (var term in this.constraints) {
				for (var index in this.constraints[term]){
					switch (index) {
						case 0:
						case '0':	//0 = "wild card" matches any thing so skip it
						case 'offspring':
						case 'inheritCnstrnt':
							continue;

						default:
							if (! targetRectypes[index]){
								delete(this.constraints[term][index]);
		}
		}
	}
	}
		}
	}


	this.openRelationships ={};

	this.getNonce = function () {
		var nonce;
		do {
			nonce = Math.floor(Math.random() * 1000000);
		} while (thisRef.openRelationships[nonce]);
		return nonce;
	}

	this.relationships = [];

	if (this.relatedRecords && this.relnIDs && this.relnIDs.byRectype && typeof this.relnIDs.byRectype == "object") {
	// create sections for each group of related records of the same type
		for (var rectype in this.relnIDs.byRectype) {
		// create a section header
		if (!supressHeaders) {
			var titleRow = document.createElement("div");
			titleRow.className = "relation-title";
			var titleCell = titleRow.appendChild(document.createElement("div"));
			titleCell.appendChild(document.createElement("span")).appendChild(document.createTextNode(
				(top.HEURIST.rectypes.pluralNames[rectype] || "Other") + ": "));
			// create a button for adding new relationships to the group
			var a = titleCell.appendChild(document.createElement("a"));
			a.href = "#";
			a.innerHTML = "add";
			a.onclick = function(tRow, rtype, dtID) { return function() {
					var newRow = document.createElement("div");
					var newCell = newRow.appendChild(document.createElement("div"));
					thisRef.parentElement.insertBefore(newRow, tRow.nextSibling);
					var rel = new EditableRelationship(newCell, null, rtype || 0,dtID,thisRef);
					rel.nonce = thisRef.getNonce();
					thisRef.openRelationships[rel.nonce] = rel;
					rel.relTypeSelect.focus();
						}; }(titleRow, rectype, dtIDRelmarker);

			this.parentElement.appendChild(titleRow);
		}

			for (var relType in this.relnIDs.byRectype[rectype]) {
				for (var i=0; i < this.relnIDs.byRectype[rectype][relType].length; i++) {
					this.relationships.push(new Relationship(this.parentElement,
															this.relatedRecords[this.relnIDs.byRectype[rectype][relType][i]],
															this));
		}
	}
		}
	}

	var addOtherTr = document.createElement("div");
	var addOtherTd = addOtherTr.appendChild(document.createElement("div"));
	addOtherTd.style.paddingTop = "20px";
	addOtherTd.colSpan = 7;
	var a = addOtherTd.appendChild(document.createElement("a"));
	a.href = "#";
		var addImg = a.appendChild(document.createElement("img"));
		addImg.src = top.HEURIST.basePath +"common/images/add-record-small.png";
		addImg.className = "add_records_img";
	if (this.relationships.length > 0) {
		addImg.title = "Add another relationship";
		a.appendChild(document.createTextNode("add more ..."));
	} else {
		addImg.title = "Add a relationship";
		a.appendChild(document.createTextNode("Add a relationship"));
	}
	a.style.textDecoration = "none";
	a.onclick = function(rtypes,dtID) { return function() {
		var newRow = document.createElement("div");
		var newCell = newRow.appendChild(document.createElement("div"));
		newCell.colSpan = 7;
		thisRef.parentElement.appendChild(newRow);
		var rel = new EditableRelationship(newCell,null,rtypes,dtID,thisRef);
		rel.nonce = thisRef.getNonce();
		thisRef.openRelationships[rel.nonce] = rel;
		rel.relTypeSelect.focus();
		}; }((trgRectypeList ? trgRectypeList : 0),dtIDRelmarker);
	this.parentElement.appendChild(addOtherTr);


//saw TODO  fix ellipses as they have been temporarily disabled  4/4/10
/*
	// check title lengths and if too long show ellipses
	var testDiv = document.createElement("div");
	testDiv.id = "test-div";
	document.body.appendChild(testDiv);

	if (this.relationships.length > 0) {
		var titleWidth = this.relationships[0].titleSpan.offsetWidth;
		var otherWidth = this.relationships[0].notesField.offsetWidth;
		for (var i=0; i < this.relationships.length; ++i) {
			rel = this.relationships[i];

			testDiv.innerHTML = rel.titleSpan.innerHTML;
			if (testDiv.offsetWidth > titleWidth) rel.ellipsesTd1.style.visibility = "visible";

			testDiv.innerHTML = rel.notesField.innerHTML;
			if (testDiv.offsetWidth > otherWidth) rel.ellipsesTd2.style.visibility = "visible";
		}
	}
	testDiv.parentNode.removeChild(testDiv);
*/
}

RelationManager.prototype.saveAllOpen = function () {
	for (var i in this.openRelationships) {
		this.openRelationships[i].save();
	}
}

RelationManager.prototype.remove = function (relObj) {
	if (relObj instanceof EditableRelationship) {
		delete this.openRelationships[relObj.nonce];
	}else if (this.changeNotification) {
		this.changeNotification("delete", relObj.relationshipRec.relnID);	//saw Check if this can be O and does notify handle it
	}
}
