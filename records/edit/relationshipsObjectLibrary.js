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
* @param details a reference to an object containing the record details for the relationship record
* @param manager a reference to the RelationManager that manages this Relationship Object
*/
var Relationship = function(parentElement, details, manager) {
	var elt = parentElement;
	do { elt = elt.parentNode; } while (elt.nodeType != 9 /* DOCUMENT_NODE */);
	this.document = elt;

	var thisRef = this;

	this.details = details;
	this.manager = manager;

	this.tr = this.document.createElement("tr");
	this.tr.className = "relation";

	var deleteTd = this.tr.appendChild(this.document.createElement("td"));
	deleteTd.className = "delete";
	deleteTd.title = "Delete this relationship";
	deleteTd.appendChild(this.document.createElement("img")).src = top.HEURIST.basePath + "common/images/cross.gif";
	deleteTd.onclick = function() { thisRef.remove(); };
	var editTd = this.tr.appendChild(this.document.createElement("td"));
	editTd.className = "edit";
	editTd.title = "Edit this relationship";
	editTd.appendChild(this.document.createElement("img")).src = top.HEURIST.basePath + "common/images/edit-pencil.png";
	editTd.onclick = function() { thisRef.edit(); };

	this.relSpan = this.tr.appendChild(this.document.createElement("td")).appendChild(this.document.createElement("div"));
	this.relSpan.parentNode.className = "rel";
	this.relSpan.appendChild(this.document.createTextNode(details.RelationValue));

	this.titleSpan = this.tr.appendChild(this.document.createElement("td")).appendChild(this.document.createElement("div"));
	this.titleSpan.parentNode.className = "title";
	this.titleSpan.appendChild(this.document.createTextNode((details.OtherResource? details.OtherResource.Title : details.Title)));

//	this.ellipsesTd1 = this.tr.appendChild(this.document.createElement("td"));
//	this.ellipsesTd1.className = "ellipses";
//	this.ellipsesTd1.innerHTML = "&nbsp;...";

	this.datesTd = this.tr.appendChild(this.document.createElement("td")).appendChild(this.document.createElement("div"));
	this.datesTd.parentNode.className = "dates";
	if (details.StartDate) {
		this.datesTd.appendChild(this.document.createTextNode(details.StartDate));
	}

	this.notesField = this.tr.appendChild(this.document.createElement("td")).appendChild(this.document.createElement("div"));
	this.notesField.parentNode.className = "notes-field";
	this.notesField.appendChild(this.document.createTextNode(details.Notes || ""));

//	this.ellipsesTd2 = this.tr.appendChild(this.document.createElement("td"));
//	this.ellipsesTd2.className = "ellipses";
//	this.ellipsesTd2.innerHTML = "&nbsp;...";

	parentElement.appendChild(this.tr);
};

/**
* Helper function that lanches the mini-edit.html popup with the record id of the relationship record.
* @author Tom Murtagh
* @author Kim Jackson
* @author Stephen White
*/
Relationship.prototype.edit = function() {
	var thisRef = this;
	top.HEURIST.util.popupURL(window, top.HEURIST.basePath + "records/edit/formEditRecordPopup.html?bib_id="+this.details.bibID,
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

var EditableRelationship = function(parentElement, details, reftype,dtID, relVocabulary, manager) {
	var elt = parentElement;
	do { elt = elt.parentNode; } while (elt.nodeType != 9 /* DOCUMENT_NODE */);
	this.document = elt;

	if (reftype && reftype.search(",") != -1) {
		this.reftypes = reftype;
		reftype = reftype.split(",")[0];
	}
	var thisRef = this;
	this.manager = manager;

	if (details) {
		this.details = details;
	}
	else {
		this.details = {
			RelationType: "",
			OtherResource: { Title: "", Reftype: (reftype ? reftype : 0), URL: "", bibID: 0 },
			Notes: "",
			Title: "",
			StartDate: null,
			EndDate: null
		};
	}

	this.div = parentElement.appendChild(this.document.createElement("div"));
	this.div.className = "relation editable";

	this.header = this.div.appendChild(this.document.createElement("div"));
	this.header.className = "header";
	this.header.style.marginBottom = "0.5ex";
	this.header.appendChild(this.document.createTextNode("Add new relationship"));

	var detailsElt = this.div.appendChild(this.document.createElement("div"));
	detailsElt.className = "resource";

	var table = detailsElt.appendChild(this.document.createElement("table"));
	var tbody = table.appendChild(this.document.createElement("tbody"));

	var tr = tbody.appendChild(this.document.createElement("tr"));
	tr.className = "input-row";
	var td = tr.appendChild(this.document.createElement("td"));
	td.className = "input-header-cell";
	td.appendChild(this.document.createTextNode("Using Vocabulary"));

	td = tr.appendChild(this.document.createElement("td"));
	this.relOnt = td.appendChild(this.document.createElement("select"));
	this.relOnt.id = "vocabulary";
	this.relOnt.name = "vocabulary";
	var firstOption = this.relOnt.options[0] = new Option("(select a vocabulary)", "");
	firstOption.disabled = true;
	firstOption.selected = true;

	var onts = top.HEURIST.vocabularyLookup;
	if (!relVocabulary) relVocabulary = 1; // if the calling code didn't pass an vocabulary then set it to basic
	for (var ont in onts) {
		this.relOnt.options[this.relOnt.length] = new Option(onts[ont],ont);
		if (ont == relVocabulary)
		this.relOnt.value = relVocabulary;
	}

	tr = tbody.appendChild(this.document.createElement("tr"));
	tr.className = "input-row";
	td = tr.appendChild(this.document.createElement("td"));
	td.className = "input-header-cell";
	td.appendChild(this.document.createTextNode("This record"));

	td = tr.appendChild(this.document.createElement("td"));
	this.relType = td.appendChild(this.document.createElement("select"));
	this.relType.id = "relationship-type";
	this.relType.name = "relationship-type";
	var firstOption = this.relType.options[0] = new Option("(select relationship type)", "");
	firstOption.disabled = true;
	firstOption.selected = true;

	if (dtID && dtID != 200) {
		var allowedLookups = top.HEURIST.edit.getLookupConstraintsList(dtID);
	}
	var relTypes = top.HEURIST.bibDetailLookups[200];  //saw need to change restricted reltypes from rel_constraints pass in a param
	for (var ont in relTypes) {
		var grp = document.createElement("optgroup");
		grp.label = top.HEURIST.vocabularyLookup[ont];
		this.relType.appendChild(grp);
		for (var i = 0; i < relTypes[ont].length; ++i) {
			var rdl = relTypes[ont][i];
			if (allowedLookups && allowedLookups[rdl[0]] === undefined) continue;
			this.relType.options[this.relType.length] = new Option(rdl[1], rdl[0]);
			if (ont == 1 && "IsRelatedTo" === rdl[1])
				this.relType.value = rdl[0];
		}
	}

	var fakeBDT = [199, "Related record", "resource", this.reftypes? this.reftypes : (reftype ? reftype : 0)];
	var fakeBDR = ["Related record", "", "", "N", 0, null, 0];
	this.otherResource = new top.HEURIST.edit.inputs.BibDetailResourceInput(fakeBDT, fakeBDR, [], tbody);
	this.otherResourceID = this.otherResource.inputs[0].hiddenElt;


	tr = tbody.appendChild(this.document.createElement("tr"));
	tr.className = "input-row";
	td = tr.appendChild(this.document.createElement("td"));
	td = tr.appendChild(this.document.createElement("td"));

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


	tr = tbody.appendChild(this.document.createElement("tr"));
	td = tr.appendChild(this.document.createElement("td"));
	td.colSpan = 2;
	td.style.height = "2em";

	tr = tbody.appendChild(this.document.createElement("tr"));
	tr.className = "input-row optional";
	td = tr.appendChild(this.document.createElement("td"));
	td.className = "section-header-cell optional";
	td.appendChild(this.document.createTextNode("OPTIONAL FIELDS"));

	var fakeBDT = [638, "Interpretation", "resource", 182];
	var helpString = "Record the evidence and/or reasoning on which this relationship is based";
	var fakeBDR = ["Interpretation", helpString, "", "O", 0, null, 0];
	this.interpResource = new top.HEURIST.edit.inputs.BibDetailResourceInput(fakeBDT, fakeBDR, [], tbody);
	this.interpResourceID = this.interpResource.inputs[0].hiddenElt;

	tr = tbody.appendChild(this.document.createElement("tr"));
	tr.className = "input-row optional";
	td = tr.appendChild(this.document.createElement("td"));
	td.className = "input-header-cell";
	td.appendChild(this.document.createTextNode("Validity"));
	td = tr.appendChild(this.document.createElement("td"));

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

	tr = tbody.appendChild(this.document.createElement("tr"));
	tr.className = "input-row optional";
	td = tr.appendChild(this.document.createElement("td"));
	td.className = "input-header-cell";
	td.appendChild(this.document.createTextNode("Description"));
	td = tr.appendChild(this.document.createElement("td"));
	this.description = td.appendChild(this.document.createElement("input"));
	this.description.value = "Relationship";
	this.description.className = "in";

	tr = tbody.appendChild(this.document.createElement("tr"));
	tr.className = "input-row optional";
	td = tr.appendChild(this.document.createElement("td"));
	td.className = "input-header-cell";
	td.appendChild(this.document.createTextNode("Notes"));
	td = tr.appendChild(this.document.createElement("td"));
	this.notes = td.appendChild(this.document.createElement("textarea"));
	this.notes.className = "in";
};

EditableRelationship.prototype.save = function() {
	if (! (this.relType.value  ||  this.otherResourceID.value  ||  this.startDate.value  ||  this.endDate)) return;

	if (this.relType.value == "") {
		alert("You must select a relationship type");
		return;
	}
	if (this.otherResourceID.value == ""  ||  this.otherResourceID.value == 0) {
		alert("You must select a related record");
		return;
	}

	var fakeForm = { action: top.HEURIST.basePath +"records/relationships/saveRelationships.php",
		elements: [
		{ name: "bib_id", value: parent.HEURIST.record.bibID },
		{ name: "save-mode", value: "new" },
		{ name: "RelationType", value: this.relType.value },	//saw Enum change - nothing to do on the save side value is the id
		{ name: "OtherResource", value: this.otherResourceID.value },
		{ name: "InterpResource", value: this.interpResourceID.value },
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
		}
		else if (vals.relationship) {
			parent.HEURIST.record.relatedRecords[ vals.relationship.bibID ] = vals.relationship;

			var myTR = thisRef.div.parentNode.parentNode;
			var newReln = new Relationship(myTR.parentNode, vals.relationship,thisRef.manager);
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
	//this.relType.selectedIndex = 0;	// saw removed to maintain the selected value for repeated entries of the type relation
	this.otherResource.inputs[0].hiddenElt.value = "0";
	this.otherResource.inputs[0].textElt.value = "";
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
* Constructor for RelationManager class which manages the relation display objects for a record. It displays the Relation Type
* with the record title, delete button and edit button.
* @author Tom Murtagh
* @author Kim Jackson
* @author Stephen White
* @param parentElement a DOM element where the Relationship will be displayed
* @param details a reference to an object containing the record details for the relationship record
*/
var RelationManager = function(parentElement, reftypeID, relatedRecords, bibDetailTypeID, changeNotification, supressHeaders) {
	if (!parentElement || isNaN(reftypeID)) return null;
	var thisRef = this;
	this.parentElement = parentElement;
	this.reftypeID = parseInt(reftypeID);
	if (relatedRecords) {
		this.relatedRecords = relatedRecords;
	}
	if (changeNotification) {
		this.changeNotification = changeNotification;
	}
	this.openRelationships ={};

	if (bibDetailTypeID) {
		var constrainRecTypes = {};
		var constrRecTypeList = temp = top.HEURIST.edit.getRecTypeConstraintsList(bibDetailTypeID);
		if (temp) {
			temp = temp.split(",");
			for (var i = 0; i < temp.length; i++) {
				constrainRecTypes[temp[i]] = temp[i];
			}
		}else{
			constrainRecTypes = "0";
		}
	}

	this.relatedRecordsByType = {};
	var relatedRecordsNoType = [];
	for (var bib_id in relatedRecords) {
		var rec = relatedRecords[bib_id];
		if (rec.OtherResource && rec.OtherResource.Reftype) {
			if (constrainRecTypes && !constrainRecTypes[rec.OtherResource.Reftype])  continue;
			if (! this.relatedRecordsByType[rec.OtherResource.Reftype]) {
				this.relatedRecordsByType[rec.OtherResource.Reftype] = [ rec ];
			}
			else {
				this.relatedRecordsByType[rec.OtherResource.Reftype].push(rec);
			}
		}
		else {
			relatedRecordsNoType.push(rec);
		}
	}
	if (relatedRecordsNoType.length > 0) {
		this.relatedRecordsByType[""] = relatedRecordsNoType;
	}

	this.getNonce = function () {
		var nonce;
		do {
			nonce = Math.floor(Math.random() * 1000000);
		} while (thisRef.openRelationships[nonce]);
		return nonce;
	}

	this.relationships = [];
	// create sections for each group of related records of the same type
	for (var reftype in this.relatedRecordsByType) {
		// create a section header
		if (!supressHeaders) {
			var titleRow = document.createElement("tr");
			titleRow.className = "relation-title";
			var titleCell = titleRow.appendChild(document.createElement("td"));
			titleCell.colSpan = 7;
			titleCell.appendChild(document.createElement("span")).appendChild(document.createTextNode(
				(top.HEURIST.reftypes.pluralNames[reftype] || "Other") + ": "));
			// create a button for adding new relationships to the group
			var a = titleCell.appendChild(document.createElement("a"));
			a.href = "#";
			a.innerHTML = "add";
			a.onclick = function(tRow, rtype, dtID) { return function() {
					var newRow = document.createElement("tr");
					var newCell = newRow.appendChild(document.createElement("td"));
					newCell.colSpan = 7;
					thisRef.parentElement.insertBefore(newRow, tRow.nextSibling);
					var rel = new EditableRelationship(newCell, null, rtype || 0,dtID,null,thisRef);
					rel.nonce = thisRef.getNonce();
					thisRef.openRelationships[rel.nonce] = rel;
					rel.relType.focus();
					}; }(titleRow, reftype,bibDetailTypeID);

			this.parentElement.appendChild(titleRow);
		}

		for (var i=0; i < this.relatedRecordsByType[reftype].length; ++i) {	//saw reftype maybe constrained modify to handle constrained set of reftypes
			this.relationships.push(new Relationship(this.parentElement, this.relatedRecordsByType[reftype][i],this));
		}
	}
	var addOtherTr = document.createElement("tr");
	var addOtherTd = addOtherTr.appendChild(document.createElement("td"));
	addOtherTd.style.paddingTop = "20px";
	addOtherTd.colSpan = 7;
	var a = addOtherTd.appendChild(document.createElement("a"));
	a.href = "#";
	if (this.relationships.length > 0) {
		var addImg = a.appendChild(document.createElement("img"));
		addImg.src = top.HEURIST.basePath +"common/images/add-record-small.png";
		addImg.className = "add_records_img";
		addImg.title = "Add another relationship";
		a.appendChild(document.createTextNode("add more ..."));
	}
	else {
		var addImg = a.appendChild(document.createElement("img"));
		addImg.src = top.HEURIST.basePath +"common/images/add-record-small.png";
		addImg.className = "add_records_img";
		addImg.title = "Add a relationship";
		a.appendChild(document.createTextNode("Add a relationship"));
	}
	a.style.textDecoration = "none";
	a.onclick = function(rtype,dtID) { return function() {
		var newRow = document.createElement("tr");
		var newCell = newRow.appendChild(document.createElement("td"));
		newCell.colSpan = 7;
		thisRef.parentElement.appendChild(newRow);
		var rel = new EditableRelationship(newCell,null,rtype,dtID,null,thisRef);
		rel.nonce = thisRef.getNonce();
		thisRef.openRelationships[rel.nonce] = rel;
		rel.relType.focus();
		}; }((constrRecTypeList ? constrRecTypeList : 0),bibDetailTypeID);
	this.parentElement.appendChild(addOtherTr);

	// check title lengths and if too long show ellipses
	var testDiv = document.createElement("div");
	testDiv.id = "test-div";
	document.body.appendChild(testDiv);

//	if (this.relationships.length > 0) {  saw TODO  fix ellipses as they have been temporarily disabled  4/4/10
	if (false) {
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
		this.changeNotification("delete", relObj.details.bibID);
	}
}
