/*
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 */

function _popupDisambiguation(matches, continueAction) {
	var document = top.document;

	var disambig = document.createElement("div");
		disambig.style.width = "480px";
		disambig.style.height = "320px";

	var disambigInner = disambig.appendChild(document.createElement("div"));
		disambigInner.style.margin = "5px";
		disambigInner.style.fontSize = "11px";

	disambigInner.innerHTML =
		"<div style='margin-bottom: 5px; font-size: 13px;'><b>These details look familiar</b></div>" +
		"<div style='width: 300px; margin-bottom: 10px;'>Please select an existing record instead from the list below, or confirm that you want to create a new one.</div>";


	var table = disambigInner.appendChild(document.createElement("table"));
		table.style.width = "100%";
		table.cellPadding = "0";
		table.cellSpacing = "0";
		table.border = "0";
	var tbody = table.appendChild(document.createElement("tbody"));

	var continueButton = document.createElement("input");
		continueButton.type = "button";
		continueButton.value = "Continue";
		continueButton.style.margin = "5px";
		continueButton.style.fontWeight = "bold";
		continueButton.disabled = true;

	var choices = [];
	for (var i=0; i < matches.length; ++i) {
		var match = matches[i];

		var tr = tbody.appendChild(document.createElement("tr"));
			tr.className = "option";
		var td = tr.appendChild(document.createElement("td"));
			td.className = "radio";
			td.style.padding = "5px 0";
		var radioButton;
		try {
			// this is for IE
			radioButton = document.createElement("<input type=radio name=choice/>");
		} catch (e) {
			radioButton = document.createElement("input");
			radioButton.type = "radio";
			radioButton.name = "choice";
		}
			radioButton.value = match.id;
			radioButton.expando = 1;
			radioButton.details = match;
			radioButton.style.padding = 0;
			radioButton.style.margin = 0;
			radioButton.style.verticalAlign = "middle";
			radioButton.onclick = function() { continueButton.disabled = false; };
		td.appendChild(radioButton);
		choices.push(radioButton);

		var td = tr.appendChild(document.createElement("td"));
		var nobr = td.appendChild(document.createElement("nobr"));
			nobr.appendChild(document.createTextNode(match.title));
		var nobr = td.appendChild(document.createElement("nobr"));
			nobr.appendChild(document.createTextNode(" ["));
			var a = nobr.appendChild(document.createElement("a"));
				a.href = "#";
				a.onclick = function(bibID) { return function() {
					top.HEURIST.util.popupURL(window, top.HEURIST.basePath + "records/edit/formEditRecordPopup.html?bib_id=" + bibID);
					return false;
				} }(match.id);
				a.appendChild(document.createTextNode("view / edit details"));
			nobr.appendChild(document.createTextNode("] "));
		var b = td.appendChild(document.createElement("b"));
			b.appendChild(document.createTextNode("#" + match.id));
	}

	var tr = tbody.appendChild(document.createElement("tr"));
		tr.className = "option";
	var td = tr.appendChild(document.createElement("td"));
		td.className = "radio";
		td.style.padding = "5px 0";
	var radioButton;
	try {
		radioButton = document.createElement("<input type=radio name=choice/>");
	} catch (e) {
		radioButton = document.createElement("input");
		radioButton.type = "radio";
		radioButton.name = "choice";
	}
		radioButton.value = -1;
		radioButton.style.padding = 0;
		radioButton.style.margin = 0;
		radioButton.style.verticalAlign = "middle";
		radioButton.onclick = function() { continueButton.disabled = false; };
	td.appendChild(radioButton);
	choices.push(radioButton);

	var td = tr.appendChild(document.createElement("td"));
	var nobr = td.appendChild(document.createElement("nobr"));
		nobr.appendChild(document.createTextNode("None of the above, create a new record"));


	continueButton.onclick = function() {
		var choice = null;
		for (var i=0; i < choices.length; ++i) {
			if (choices[i].checked) {
				choice = choices[i];
				break;
			}
		}
		if (! choice) return;

		/* okay! We have a decision.
		 * If the choice is -1 then the user has confirmed that they want to add the new record, so we will force that.
		 * If the choice is anything else, then we invoke the popup-close function with the relevant details.
		 */
		continueAction(choice);

		// FIXME: surely there is a better way to get the id of the top popup!
		top.HEURIST.util.closePopup(top.HEURIST.util.popups.list[top.HEURIST.util.popups.list.length-1].id);
	};
	disambigInner.appendChild(continueButton);

	top.HEURIST.util.popupElement(window, disambig);
}
