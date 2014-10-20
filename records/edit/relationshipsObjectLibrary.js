/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* Constructor for Relationship class a relationship display line. It displays the Relation Type
* with the record title, delete button and edit button.
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @param       parentElement a DOM element where the Relationship will be displayed
* @param       relationshipRec a reference to an object containing the record details for the relationship record
* @param       manager a reference to the RelationManager that manages this Relationship Object
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


if (!top.Relationship) {
	top.Relationship = function(parentElement, relationshipRec, manager) {
		var elt = parentElement;
		do { elt = elt.parentNode; } while (elt.nodeType != 9 /* DOCUMENT_NODE */);
		this.document = elt;

		var thisRef = this;

		this.relationshipRec = relationshipRec;
		this.manager = manager;

		this.tr = this.document.createElement("div");
		this.tr.className = "relation";

		this.relSpan = this.tr.appendChild(this.document.createElement("div"))
		this.relSpan.className = "relation-reltype-and-rec";

		this.relSpan.appendChild(this.document.createTextNode(
				((relationshipRec.role == "Primary") ?relationshipRec.relTerm :relationshipRec.relInvTerm) + " "+
				(relationshipRec.relatedRec? relationshipRec.relatedRec.title : relationshipRec.title) ));



/*
		this.relSpan = this.tr.appendChild(this.document.createElement("div")).appendChild(this.document.createElement("div"));
		this.relSpan.parentNode.className = "rel";
		this.relSpan.appendChild(this.document.createTextNode(relationshipRec.relTerm));

		this.titleSpan = this.tr.appendChild(this.document.createElement("div")).appendChild(this.document.createElement("div"));
		this.titleSpan.parentNode.className = "title";
		this.titleSpan.appendChild(this.document.createTextNode((relationshipRec.relatedRec? relationshipRec.relatedRec.title : relationshipRec.title)));
*/

		var deleteTd = this.tr.appendChild(this.document.createElement("div"));
		deleteTd.className = "delete";
		deleteTd.style.paddingLeft = '4px';
		deleteTd.title = "Delete this relationship";
		deleteTd.appendChild(this.document.createElement("img")).src = top.HEURIST.basePath + "common/images/cross.png";
		deleteTd.onclick = function() {
			thisRef.remove();

			/* 2012-11-05
			if(thisRef.manager.addOtherTd && !thisRef.manager.needAddToAggregation){
					thisRef.manager.addOtherTd.style.display = 'inline-block';
			}*/
		};
		var editTd = this.tr.appendChild(this.document.createElement("div"));
		editTd.className = "edit";
		editTd.title = "Edit this relationship";
		editTd.appendChild(this.document.createElement("img")).src = top.HEURIST.basePath + "common/images/edit-pencil.png";
		editTd.onclick = function() {
			thisRef.edit();
		};
		editTd = this.tr.appendChild(this.document.createElement("div"));
		editTd.className = "edit";
		editTd.title = "Edit related record";
		editTd.appendChild(this.document.createElement("img")).src = top.HEURIST.basePath + "common/images/edit-pencil.png";
		editTd.onclick = function() {
			thisRef.editRelatedRecord();
		};

		var optionalTd = this.tr.appendChild(this.document.createElement("div"));

		this.datesTd = optionalTd.appendChild(this.document.createElement("div")).appendChild(this.document.createElement("div"));
		this.datesTd.parentNode.className = "dates";
		if (relationshipRec.startDate) {
			this.datesTd.appendChild(this.document.createTextNode(temporalToHumanReadableString(relationshipRec.startDate)));
			if (relationshipRec.endDate) {
				this.datesTd.appendChild(this.document.createTextNode(" - " + temporalToHumanReadableString(relationshipRec.endDate)));
			}
		}

		this.notesField = optionalTd.appendChild(this.document.createElement("div")).appendChild(this.document.createElement("div"));
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
	* Helper function that launches the mini-edit.html popup with the record id of the relationship record.
	* @author Tom Murtagh
	* @author Kim Jackson
	* @author Stephen White
	*/
	top.Relationship.prototype.edit = function() {
        var _calendarViewer = top.HEURIST.edit.calendarViewer;
		var thisRef = this;
		var _db =  (top.HEURIST.parameters.db?top.HEURIST.parameters.db : (top.HEURIST.database.name? top.HEURIST.database.name:""));
		top.HEURIST.util.popupURL(window, top.HEURIST.basePath + "records/edit/formEditRecordPopup.html?recID="+this.relationshipRec.relnID +
			"&db="+_db,
		{	width: 940,
		 	height: 640,
			callback: function(newRecTitle, newDetails) {

				if (newDetails) {

					var sURL  = top.HEURIST.basePath + "common/php/loadRecordData.php";

					top.HEURIST.util.getJsonData(sURL,
						function(responce){
							if(responce){
								thisRef.manager.relatedRecords = responce.relationshipRecs;
								thisRef.relationshipRec = responce.relationshipRecs[thisRef.relationshipRec.relnID];

								thisRef.relSpan.innerHTML =
									((thisRef.relationshipRec.role == "Primary") ?thisRef.relationshipRec.relTerm :thisRef.relationshipRec.relInvTerm) + " "+
										(thisRef.relationshipRec.relatedRec? thisRef.relationshipRec.relatedRec.title : thisRef.relationshipRec.title);
							}
                            top.HEURIST.edit.calendarViewer = _calendarViewer;
						},
						"db="+_db+"&recID="+thisRef.manager.recID+"&action=getrelated");
				}

				/*
				var dtRelType = (top.HEURIST.magicNumbers && top.HEURIST.magicNumbers['DT_RELATION_TYPE']? '' + top.HEURIST.magicNumbers['DT_RELATION_TYPE']:'');
				var dtLinkPtr = (top.HEURIST.magicNumbers && top.HEURIST.magicNumbers['DT_TARGET_RESOURCE']? '' + top.HEURIST.magicNumbers['DT_TARGET_RESOURCE']:'');
				if (newDetails) {
					thisRef.relSpan.innerHTML = "";
					if (dtRelType && newDetails[dtRelType] && newDetails[dtRelType][0]) {
						thisRef.relSpan.innerHTML = (newDetails[dtRelType][0]['enumValue'] ?
														newDetails[dtRelType][0]['enumValue'] :
														(newDetails[dtRelType][0]['value'] ?
															newDetails[dtRelType][0]['value']: "" ));
					}
					if (dtLinkPtr && newDetails[dtLinkPtr] && newDetails[dtLinkPtr][0]) {
						thisRef.relSpan.innerHTML += (newDetails[dtLinkPtr][0]['title'] ?
														newDetails[dtLinkPtr][0]['title'] :
														(newDetails[dtLinkPtr][0]['value'] ?
															newDetails[dtLinkPtr][0]['value']: "" ));
					}
				}
				*/
			}
		});
		return false;
	};

	top.Relationship.prototype.editRelatedRecord = function() {
		var thisRef = this;
		var _db =  (top.HEURIST.parameters.db?top.HEURIST.parameters.db : (top.HEURIST.database.name? top.HEURIST.database.name:""));

		if( thisRef.relationshipRec.relatedRec.recID ){
				window.open(top.HEURIST.basePath +"records/edit/editRecord.html?recID=" + thisRef.relationshipRec.relatedRec.recID + "&db="+_db);
		} //"&caller=" + encodeURIComponent(textElt.id) +

		return false;
	};

	top.Relationship.prototype.remove = function() {
		this.tr.parentNode.removeChild(this.tr);
		this.manager.remove(this);
	};

	//**********************************************************************************
	top.EditableRelationship = function(parentElement, relationshipRec, rectypes, dtID, manager) {
		var elt = parentElement;
		do { elt = elt.parentNode; } while (elt.nodeType != 9 /* DOCUMENT_NODE */);
		this.document = elt;
		var rtInterp = top.HEURIST.magicNumbers['RT_INTERPRETATION'];
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
				intrpRec: { title: "", rectype: rtInterp, URL: "", recID: 0 },
				notes: "",
				title: "",
				startDate: null,
				endDate: null
			};
		}

		var tbody;

		if(thisRef.manager.needAddToAggregation){
			this.div = parentElement.appendChild(this.document.createElement("fieldset"));
			this.div.className = "relation editable reminder";

			this.header = this.div.appendChild(this.document.createElement("legend"));
			//this.header.className = "header";
			//this.header.style.marginBottom = "0.5ex";
			this.header.appendChild(this.document.createTextNode("Add new relationship"));
		}else{
			this.div = parentElement.appendChild(this.document.createElement("div"));
			//tbody = parentElement.appendChild(this.document.createElement("div"));
		}
		tbody = this.div.appendChild(this.document.createElement("div"));


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
		td.style.display = "inline-block";

		td = tr.appendChild(this.document.createElement("div"));
		td.style.display = "inline-block";
		//td.className = "input2-header-cell";

		if(thisRef.manager.needAddToAggregation){
			td.style.paddingRight = '10px';
			td.style.paddingLeft = '10px';
			td.appendChild(this.document.createTextNode("This record"));
		}else{
			td.style.padding = '0px';
		}


		//var rfr = top.HEURIST.rectypes.typedefs[top.HEURIST.magicNumbers['RT_RELATION']]['dtFields'][top.HEURIST.magicNumbers['DT_RELATION_TYPE']];

		//td = tr.appendChild(this.document.createElement("div"));
		//td.style.display = "inline-block";
/* OLD WAY
		this.relTypeSelect = top.HEURIST.util.createTermSelect(this.manager.relTerms,
																	(this.manager.termHeadersList || ""),
																	"relation",
																	this.relationshipRec.relTermID);
		this.relTypeSelect.style.marginLeft = '5px';
		this.relTypeSelect.style.padding = '3px';
		this.relTypeSelect.id = "relationship-type";
		this.relTypeSelect.name = "relationship-type";
		td.appendChild(this.relTypeSelect);
*/
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
		// detailType
			//0,"dty_Name" 1,"dty_ExtendedDescription" 2,"dty_Type" 3,"dty_OrderInGroup" 4,"dty_HelpText"
			//5,"dty_ShowInLists" 6,"dty_Status" 7,"dty_DetailTypeGroupID" 8,"dty_FieldSetRectypeID" 9,"dty_JsonTermIDTree"
			//10,"dty_TermIDTreeNonSelectableIDs" 11,"dty_PtrTargetRectypeIDs" 12,"dty_ID"

		//recFieldRequirements
			//0,"rst_DisplayName" 1,"rst_DisplayHelpText" 2,"rst_DisplayExtendedDescription" 3,"rst_DefaultValue"
			//4,"rst_RequirementType" 5,"rst_MaxValues" 6,"rst_MinValues" 7,"rst_DisplayWidth" 8,"rst_RecordMatchOrder"
			//9,"rst_DisplayOrder" 10,"rst_DisplayDetailTypeGroupID" 11,"rst_FilteredJsonTermIDTree" 12,"rst_PtrFilteredIDs"
			//13,"rst_TermIDTreeNonSelectableIDs" 14,"rst_CalcFunctionID" 15,"rst_Status"
			//16,"rst_OrderForThumbnailGeneration" 17,"dty_TermIDTreeNonSelectableIDs"
			//18,"dty_FieldSetRectypeID"

		var fakeBDT = top.HEURIST.edit.createFakeDetailType((this.manager.dtID?this.manager.dtID:
															(top.HEURIST.magicNumbers && top.HEURIST.magicNumbers['DT_RELATION_TYPE']?
																'' + top.HEURIST.magicNumbers['DT_RELATION_TYPE']:'')),
															"",
															"relationtype",
															"",  //help text
															YAHOO.lang.JSON.stringify(this.manager.relTerms),   //terms ids
															(this.manager.termHeadersList || ""), //non selectable
															0); // allowed record pointers
		var fakeBDR = top.HEURIST.edit.createFakeFieldRequirement(fakeBDT);
		fakeBDR[top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex['rst_RequirementType']] = 'required';
		if(this.relationshipRec && this.relationshipRec.relTermID){ //default value for aggregation isPartOf
			fakeBDR[top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex['rst_DefaultValue']] = this.relationshipRec.relTermID;
		}

		this.relationType = new top.HEURIST.edit.inputs.BibDetailDropdownInput(this.manager.recID, fakeBDT, fakeBDR,
										[], td, "input2");
		this.relTypeSelect = this.relationType.inputs[0];
		this.relTypeSelect.id = "relationship-type";
		this.relTypeSelect.name = "relationship-type";

		function _nopadding(ele){
			if(ele.childNodes){
			for (var i=0; i < ele.childNodes.length; ++i) {
				var child = ele.childNodes[i];
				if(child.style){
					child.style.paddingLeft = '0px';
					//child.style.paddingRight = '0px';
					_nopadding(child);
				}
			}
			}
		}
		_nopadding(td);


		fakeBDT = top.HEURIST.edit.createFakeDetailType((top.HEURIST.magicNumbers && top.HEURIST.magicNumbers['DT_TARGET_RESOURCE']?
																'' + top.HEURIST.magicNumbers['DT_TARGET_RESOURCE']:''),
															(thisRef.manager.needAddToAggregation?"Related record":""),
															"resource",
															"",
															null,
															null,
															this.rectypes? this.rectypes : (rectypes ? rectypes : 0));

		fakeBDR = top.HEURIST.edit.createFakeFieldRequirement(fakeBDT);

		this.relatedRecord = new top.HEURIST.edit.inputs.BibDetailResourceInput(this.manager.recID, fakeBDT, fakeBDR, [], tr, "input2"); //was tbody);
		this.relatedRecordID = this.relatedRecord.inputs[0].hiddenElt;
		this.relatedRecordID.name = "relationship-type"; //to avoid save this element


		td = tr.appendChild(this.document.createElement("div"));
		td.className = "input2-header-cell";
		td.style.padding = "0px"; // 10px 0px 10px";
		td.style.height = "22px";


		var saveButton = this.document.createElement("input");
		saveButton.type = "button";
		//saveButton.style.fontWeight = "bold";
		//saveButton.style.fontSize = "2ex";
		saveButton.style.marginRight = "10px";
		saveButton.style.padding = "0px 3px";
		saveButton.value = "Add!!";// relationship";
		saveButton.onclick = function() { thisRef.save(); };

		td.appendChild(saveButton);


		if(thisRef.manager.needAddToAggregation){
			var cancelButton = this.document.createElement("input");
			cancelButton.type = "button";
			cancelButton.value = "Cancel";
			cancelButton.style.padding = "0px 3px";

			cancelButton.onclick = function() {

				/* 2012-11-05
				if(thisRef.manager.addOtherTd && !thisRef.manager.needAddToAggregation){
						thisRef.manager.addOtherTd.style.display = 'inline-block';
				}
				*/

				thisRef.remove();
			};

			td.appendChild(cancelButton);
		}

		// insert a div for optional items
		opt = tbody.appendChild(this.document.createElement("div"));
		opt.className = "resource optional";


		// show hide optional fields
		var optionalLink = this.document.createElement("a");
		optionalLink.href = "#";
		var optionalLinkImg = optionalLink.appendChild(document.createElement("img"));
		optionalLink.appendChild(this.document.createTextNode("Optional"+(thisRef.manager.needAddToAggregation?" fields":"")));
		optionalLink.style.textDecoration = "none";
		optionalLink.style.background = "none";
		optionalLink.title = "Optional Fields";
		//td.appendChild(optionalLink);

		if(thisRef.manager.needAddToAggregation){
			tr = opt.appendChild(this.document.createElement("div"));
			tr.className = "input-row optional";
			td = tr.appendChild(this.document.createElement("div"));
			td.className = "section-header-cell optional";
			td.appendChild(optionalLink);
		}else{
			td.insertBefore(optionalLink, saveButton);
		}


		/*var a = td.appendChild(document.createElement("a"));
		a.href = "#";
		a.innerHTML = "Optional Fields";
		a.onclick = */

		opt = opt.appendChild(this.document.createElement("div"));
		var isvisible = top.HEURIST.util.getDisplayPreference("relationship-optional-fields");
		if (typeof isvisible == "string"){
			isvisible = (isvisible=="true");
		}
		opt.style.display = (isvisible)?"block":"none";
		optionalLinkImg.src = top.HEURIST.basePath +"common/images/"+((isvisible)?"tdown":"tright")+".gif";

		optionalLink.onclick = function(opt)
					{ return function() {
							if(opt.style.display == "block"){
								optionalLinkImg.src = top.HEURIST.basePath +"common/images/tright.gif";
								opt.style.display = "none";
							}else{
								opt.style.display = "block";
								optionalLinkImg.src = top.HEURIST.basePath +"common/images/tdown.gif";
							}
							top.HEURIST.util.setDisplayPreference("relationship-optional-fields", (opt.style.display == "block"));

							return false;
						}
					}(opt);
		//td.appendChild(this.document.createTextNode("Optional Fields"));


		if (top.HEURIST.magicNumbers && top.HEURIST.magicNumbers['DT_INTERPRETATION_REFERENCE'] &&
										top.HEURIST.magicNumbers['RT_INTERPRETATION']) {
			var helpString = "Record the evidence and/or reasoning on which this relationship is based";
			var fakeBDT = top.HEURIST.edit.createFakeDetailType( top.HEURIST.magicNumbers['DT_INTERPRETATION_REFERENCE'],
															"Interpretation",
															"resource",
															helpString,
															null,null,
															'' + top.HEURIST.magicNumbers['RT_INTERPRETATION']);

			var fakeBDR = top.HEURIST.edit.createFakeFieldRequirement(fakeBDT);

			this.interpResource = new top.HEURIST.edit.inputs.BibDetailResourceInput(this.manager.recID, fakeBDT, fakeBDR, [], opt);
			this.interpResourceID = this.interpResource.inputs[0].hiddenElt;
		}

		tr = opt.appendChild(this.document.createElement("div"));
		tr.className = "input-row optional";
		td = tr.appendChild(this.document.createElement("div"));
		td.className = "input-header-cell";
		td.appendChild(this.document.createTextNode("Validity"));
		td = tr.appendChild(this.document.createElement("div"));
		td.className = "input-cell";

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
		td.className = "input-cell";
		this.description = td.appendChild(this.document.createElement("input"));
		this.description.value = "Relationship";
		this.description.className = "in";

		tr = opt.appendChild(this.document.createElement("div"));
		tr.className = "input-row optional";
		td = tr.appendChild(this.document.createElement("div"));
		td.className = "input-header-cell";
		td.appendChild(this.document.createTextNode("Notes"));
		td = tr.appendChild(this.document.createElement("div"));
		td.className = "input-cell";
		this.notes = td.appendChild(this.document.createElement("textarea"));
		this.notes.className = "in";
	};

    top.EditableRelationship.prototype.isempty = function() {
        return (! (this.relTypeSelect.value  ||  this.relatedRecordID.value  ||  this.startDate.value  ||  this.endDate));
    };

    top.EditableRelationship.prototype.isvalid = function() {
        if (this.relTypeSelect.value == "") {
            return "You must select a relationship type";
        }
        if (this.relatedRecordID.value == ""  ||  this.relatedRecordID.value == 0) {
            return "You must select a related record";

        }
        return null;
    };

	top.EditableRelationship.prototype.save = function() {
		if (this.isempty()) return;

        var swarn = this.isvalid();
        if(swarn){
            alert(swarn);
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

				/* ARTEM it is hardcoded for particular frame */
				if (!thisRef.manager.supressHeaders) {
					var newRels = window.frames[4].document.getElementById("newly-added-rels");
					if (!newRels) {
						newRels = document.createElement("div");
						newRels.id = "newly-added-rels";
						newRels.style.marginTop ="20px";
						newRelsHeading = newRels.appendChild(this.document.createElement("div"));
						newRelsHeading.innerHTML = "New Relationships";
						newRelsHeading.className = "relation-title";
						myTR.appendChild(newRels);
					}
				}


				var newReln = new top.Relationship(myTR.parentNode, vals.relationship.relationshipRecs[vals.relnRecID],thisRef.manager);
				myTR.parentNode.insertBefore(newReln.tr, myTR.nextSibling); //saw might be better to store myTR.parentNode as thisref.container

				thisRef.clear();
				thisRef.remove();//removes form after saving

				/*ARTEM - something weired */
				var prevRelnDiv = window.frames[4].document.getElementById("newly-added");
				if (prevRelnDiv) prevRelnDiv.id = "";
				newReln.tr.id = "newly-added";

				thisRef.manager.remove(thisRef);

			}
		});
	};

	top.EditableRelationship.prototype.clear = function() {
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

	top.EditableRelationship.prototype.remove = function() {
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
	* @param supressHeaders a boolean indicating where to show headers for the different types of relations.
	* @param needAddToAggregation - if true - call from 'Relationships' tab, otherwise from main edit tab
	*/
	top.RelationManager = function(parentElement, record, relatedRecords, dtIDRelmarker, changeNotification, supressHeaders, needAddToAggregation) {
		if (!parentElement || !record || isNaN(record.recID)) return null;

		this.supressHeaders = supressHeaders;
		this.needAddToAggregation = needAddToAggregation;
		var thisRef = this;
		this.parentElement = parentElement; // the containing element for display of relationships
		this.rectypeID = parseInt(record.rectypeID);
		this.recID = parseInt(record.recID);
		if (dtIDRelmarker) {	// relmarker detail type which means we possibly have range constraints for tragets and relation terms
			this.dtID = dtIDRelmarker;
		}

		 var rstFieldNamesToRdrIndexMap = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex;

		// save change notification callback in case we change something this should trigger a screen refresh
		if (changeNotification) {
			this.changeNotification = changeNotification;
		}

		//get all constraints for src rectype or global constraints
		this.constraints = (top.HEURIST.rectypes.constraints[this.rectypeID] || top.HEURIST.rectypes.constraints['any']);

		if (Number(dtIDRelmarker)>0) { // we are dealing with a relmark so get definitions and process them for UI

			var rfr = top.HEURIST.rectypes.typedefs[this.rectypeID].dtFields[dtIDRelmarker];
			// get any trgPointer restrictions
			var trgRectypeList = temp = rfr[rstFieldNamesToRdrIndexMap['rst_PtrFilteredIDs']];
			var targetRectypes = {};
			if (temp) {
				temp = temp.split(",");
				for (var i = 0; i < temp.length; i++) {
					targetRectypes[temp[i]] = temp[i];
				}
				this.trgRectypes = targetRectypes;
			}

			// get HeaderTerms list - the values from the structure can be null
			var rfrHdr = rfr[rstFieldNamesToRdrIndexMap['rst_TermIDTreeNonSelectableIDs']];
			if(!rfrHdr){
				rfrHdr = rfr[rstFieldNamesToRdrIndexMap['dty_TermIDTreeNonSelectableIDs']];
			}

			var headerList = {};
			if (rfrHdr) {
				this.termHeadersList = rfrHdr;//top.HEURIST.util.expandJsonStructure(rfrHdr);
			}

			// get relationship terms from relmarker definition
			this.relTerms = top.HEURIST.util.expandJsonStructure(rfr[rstFieldNamesToRdrIndexMap['rst_FilteredJsonTermIDTree']]);
		}
		if (!this.relTerms) { // if no terms were setup in a relmarker then default to all relationships = unconstrained
			//if not defined - take list of terms from
			if(top.HEURIST.magicNumbers && top.HEURIST.magicNumbers['RT_RELATION'] && top.HEURIST.magicNumbers['DT_RELATION_TYPE']){
				var rfr = top.HEURIST.rectypes.typedefs[top.HEURIST.magicNumbers['RT_RELATION']]['dtFields'][top.HEURIST.magicNumbers['DT_RELATION_TYPE']];

				var sAllTerms = rfr[rstFieldNamesToRdrIndexMap['rst_FilteredJsonTermIDTree']];
				var sDisTerms = rfr[rstFieldNamesToRdrIndexMap['dty_TermIDTreeNonSelectableIDs']];

				this.relTerms = top.HEURIST.util.expandJsonStructure(sAllTerms);
				this.termHeadersList = sDisTerms;

			}else{

				this.relTerms = top.HEURIST.terms.treesByDomain.relation;

				if(!this.termHeadersList){
					this.termHeadersList = "";
					for(termID in this.relTerms) { // For every header term
						this.termHeadersList = this.termHeadersList + termID + ",";
					}
				}
			}
		}

		var flatTermIDLookup = null;
		if (this.relTerms && typeof this.relTerms === "object" && dtIDRelmarker) {
			flatTermIDLookup = YAHOO.lang.JSON.stringify(this.relTerms);
			flatTermIDLookup = "," + flatTermIDLookup.match(/(\d+)/g).join(",") + ",";
			if (flatTermIDLookup === ",,"){
				flatTermIDLookup = null;
			}
		}
		// set up structure for the related records
		if (relatedRecords) {
			if (typeof this.trgRectypes === "object" || typeof flatTermIDLookup === "string") { // need to filter relationships for this relmarker
				var found = false;
				var relRecs = null;
				for (relnID in relatedRecords.relationshipRecs) {
					if (typeof this.trgRectypes == "object" && //filter any rectypes not in list (null list = don't constrain
						!this.trgRectypes[relatedRecords.relationshipRecs[relnID].relatedRec.rectype]) {
						continue;
					}
					if (typeof flatTermIDLookup === "string" && //filter any records who's relType in not in list
						flatTermIDLookup.indexOf("," + [relatedRecords.relationshipRecs[relnID].relTermID] + ",") === -1) {
						continue;
					}
					if (!found) {
						found = true;
						relRecs = {'rels':{}, 'byT':{}, 'byRt':{}};
					}
					relRecs.rels[relnID] = relatedRecords.relationshipRecs[relnID];
					var relRectype = relRecs.rels[relnID].relatedRec.rectype;
					var relnType = relRecs.rels[relnID].relTermID;
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
						newRow.style.width = "100%";

						thisRef.parentElement.insertBefore(newRow, tRow.nextSibling);
						var rel = new top.EditableRelationship(newCell, null, rtype || 0, dtID, thisRef);
						rel.nonce = thisRef.getNonce();
						thisRef.openRelationships[rel.nonce] = rel;
						rel.relTypeSelect.focus();
							}; }(titleRow, rectype, dtIDRelmarker);

				this.parentElement.appendChild(titleRow);
			}

				for (var relType in this.relnIDs.byRectype[rectype]) {
					for (var i=0; i < this.relnIDs.byRectype[rectype][relType].length; i++) {
						this.relationships.push(new top.Relationship(this.parentElement,
																this.relatedRecords[this.relnIDs.byRectype[rectype][relType][i]],
																this));
			}
		}
			}
		}

		var addOtherTd = document.createElement("div");
		this.addOtherTd = addOtherTd;
		var addBefore = addOtherTd;
		//var addOtherTd = addOtherTr.appendChild(document.createElement("div"));
		addOtherTd.style.paddingTop = "5px";


		//addOtherTd.colSpan = 7;
		addOtherTd.id = "addRelationshipLink";
		var a = addOtherTd.appendChild(document.createElement("a"));
		a.href = "#";
		a.style.padding = "3px 6px 3px 0px";

		var addImg = a.appendChild(document.createElement("img"));
		addImg.src = top.HEURIST.basePath +"common/images/add-record-small.png";
		addImg.className = "add_records_img";
		if (needAddToAggregation && this.relationships.length > 0) {
			addImg.title = "Add another relationship";
			a.appendChild(document.createTextNode("add more ..."));
		} else {
			addImg.title = "Add a relationship";
			a.appendChild(document.createTextNode("Add a relationship")); //debug 102 "+this.relationships.length));
		}
		a.style.textDecoration = "none";
		a.onclick = function(rtypes, dtID) { return function() {

			//hide Add new relationship for editRecord mode
			if(!needAddToAggregation){
				addOtherTd.style.display = 'none';
			}

			var newRow = document.createElement("div");
			newRow.style.width = "100%";
			newRow.className = "relation";

			var newCell = newRow.appendChild(document.createElement("div"));

			//newCell.colSpan = 7;
			thisRef.parentElement.insertBefore(newRow, addBefore); //thisRef.parentElement.lastChild);

			var rel = new top.EditableRelationship(newCell,null,rtypes,dtID, thisRef);
			rel.nonce = thisRef.getNonce();
			thisRef.openRelationships[rel.nonce] = rel;
			rel.relTypeSelect.focus();

			}; }((trgRectypeList ? trgRectypeList : 0),dtIDRelmarker);

		addOtherTd.addnewlink = a;

		if (!needAddToAggregation){// || this.relationships.length > 0){
			//if on time of creation there is relations - hide this element - aplicable for main editRecord BibDetailRelationMarker
			addOtherTd.style.display = "none";
		}else{
			addOtherTd.style.display = "inline-block";
		}

		this.parentElement.appendChild(addOtherTd);

		if (!needAddToAggregation && this.relationships.length < 1){
			top.HEURIST.util.clickworkaround(a);
		}

		//IJ asked to add special link for aggregation relationship ----------------

		if(needAddToAggregation){

		addOtherTd = document.createElement("div");
		addOtherTd.style.paddingTop = "5px";
		addOtherTd.style.display = "inline-block";
		addOtherTd.id = "addRelationshipAggrLink";
		a = addOtherTd.appendChild(document.createElement("a"));
		a.href = "#";
		a.style.padding = "3px 6px 3px 0px";

		/* this really doesn't belong here - was intended to be in form title bar
		addImg = a.appendChild(document.createElement("img"));
		addImg.src = top.HEURIST.basePath +"common/images/add-record-small.png";
		addImg.className = "add_records_img";
		addImg.title = "Add relationship to Aggregation";
		a.appendChild(document.createTextNode("Add to Aggregation"));
		*/
		a.style.textDecoration = "none";
		a.onclick = function(rtypes,dtID) {	return function() {
			var newRow = document.createElement("div");
			newRow.style.width = "100%";
			newRow.className = "relation";

			var newCell = newRow.appendChild(document.createElement("div"));

			//newCell.colSpan = 7;
			thisRef.parentElement.insertBefore(newRow, addBefore); //thisRef.parentElement.lastChild);

			var relationshipRec = {
				relnID:0,
				role:"",
				relTerm: "",
				relTermID: "IsPartOf",
				relInvTerm: "",
				relInvTermID: "",
				relatedRec: { title: "", rectype: top.HEURIST.magicNumbers['RT_AGGREGATION'], URL: "", recID: 0 },
				intrpRec: { title: "", rectype: top.HEURIST.magicNumbers['RT_INTERPRETATION'], URL: "", recID: 0 },
				notes: "",
				title: "",
				startDate: null,
				endDate: null
			};


			var rel = new top.EditableRelationship(newCell, relationshipRec, ""+top.HEURIST.magicNumbers['RT_AGGREGATION'], dtID, thisRef);
			rel.nonce = thisRef.getNonce();
			thisRef.openRelationships[rel.nonce] = rel;
			rel.relTypeSelect.focus();

			rel.relatedRecord.chooseResourceAuto();

			};
		}((trgRectypeList ? trgRectypeList : 0), dtIDRelmarker);


		this.parentElement.appendChild(addOtherTd);

		}//needAddToAggregation




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

	top.RelationManager.prototype.allowAddNew = function () {

		top.HEURIST.util.clickworkaround(this.addOtherTd.addnewlink);
		//2012-11-05  this.addOtherTd.style.display = 'inline-block';
	}

	top.RelationManager.prototype.saveAllOpen = function () {
		for (var i in this.openRelationships) {
			this.openRelationships[i].save();
		}
	}
    top.RelationManager.prototype.cancelAllOpen = function () {
        for (var i in this.openRelationships) {
            this.openRelationships[i].remove();
        }
    }


	top.RelationManager.prototype.remove = function (relObj) {

		if (relObj instanceof EditableRelationship) {
			delete this.openRelationships[relObj.nonce];
		}else if (this.changeNotification) {
			this.changeNotification("delete", relObj.relationshipRec.relnID);	//saw Check if this can be O and does notify handle it
		}
	}
}