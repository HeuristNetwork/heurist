//aliases
var Hul = top.HEURIST.util,
	rty_ID;

/**
* get rectype ID
*/
function init(){

	if (location.search.length > 1) {
		window.HEURIST.parameters = top.HEURIST.parseParams(location.search);
		rty_ID = window.HEURIST.parameters.rty_ID;
		initPreview();
	}
}

/**
* Shows all our chanages on preview tab
*
* It utilizes methods from HEURIST.edit
*
* 1) Saves all changes if some row (detail type) is expanded
* 2) Loads all changes top.HEURIST.rectypes.typedefs[rty_ID].dtFields
* 3) Creates inputs with createInputsForRectype
*/
function initPreview(){


			top.HEURIST.edit.allInputs = [];
			//top.HEURIST.edit.inputs = {};
			top.HEURIST.edit.modules = {};

			top.HEURIST.edit.record = {bdValuesByType:{},
					bibID: null,
					bkmkID: null,
					comments:[],
					isTemporary:[],
					moddate:"",
					quickNotes:"",
					recID: "0",
					rectype: "", //name of rectype
					rectypeID: rty_ID,
					relatedRecords:[],
					reminders:[],
					retrieved:"",
					rtConstraints:[],
					tagString:"",
					title:"kala mala",
					url:"",
					workgroup:"Heurist",
					workgroupID: "12",
					workgroupTags:[]

			};


		//loadPublicEditFrame();
		//setTimeout(renderInputs, 500);
		renderInputs();
}


function renderInputs() {

	var rectype = rty_ID,
		inputs;
	// Clear out any existing inputs

	var allInputs = document.getElementById("all-inputs");
	while (allInputs.childNodes.length > 0){
		allInputs.removeChild(allInputs.childNodes[0]);
	}

	var showAllDiv = document.getElementById("show-all-div");
	if (showAllDiv){
		showAllDiv.parentNode.removeChild(showAllDiv);
	}

	var innerDims = Hul.innerDimensions(window);

	if (rectype) {

		var defaultInputValues = {};

		/*if(!window.HEURIST.edit){
			window.HEURIST.edit = top.HEURIST.edit;
		}*/

		inputs = top.HEURIST.edit.createInputsForRectype(null, rectype, defaultInputValues, allInputs);

		renderShowAll();

		//renderAdditionalDataSection(allInputs, rectype);
		//window.HEURIST.inputs = inputs;
	}

}

function renderShowAll() {
	var hrRow = document.getElementById("all-inputs").appendChild(document.createElement("div"));
		hrRow.className = "separator_row";
		hrRow.style.margin = "20px 0 0 0";

	var showDiv = document.getElementById("all-inputs").appendChild(document.createElement("div"));
		showDiv.id = "show-all-div";
		showDiv.className = "title-row not-optional-fields";

	var showLink = showDiv.appendChild(document.createElement("div")).appendChild(document.createElement("a"));
		showLink.className = "additional-header-cell not-optional-fields";
		showLink.style.fontWeight = "bold";
		showLink.href = "#";
		showLink.appendChild(document.createTextNode("Show all fields"));
		showLink.onclick = function() {
			Hul.setDisplayPreference("input-visibility", "all");
			//Dom.get("input-visibility").checked = true;
			return false;
		};

	var showInnerDiv = showDiv.appendChild(document.createElement("div"));
		showInnerDiv.appendChild(document.createTextNode("Only "));
		showInnerDiv.className = "input-cell";
	var showInnerSpan = showInnerDiv.appendChild(document.createElement("span"));
		showInnerSpan.className = "required-only";
		showInnerSpan.style.fontWeight = "bold";
		showInnerSpan.appendChild(document.createTextNode("required"));
		showInnerSpan = showInnerDiv.appendChild(document.createElement("span"));
		showInnerSpan.className = "recommended";
		showInnerSpan.style.fontWeight = "bold";
		showInnerSpan.appendChild(document.createTextNode("recommended"));
		showInnerDiv.appendChild(document.createTextNode(" fields are currently visible"));
}


