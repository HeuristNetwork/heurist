/*
* Copyright (C) 2005-2016 University of Sydney
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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


function htmlEscape(val) {
	return ((val || "") + "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

function formatDetails(bib, prefix, indent) {
	if (! prefix) prefix = "";
	if (! indent) indent = 0;

	var html = "";
	var bdr = top.HEURIST.rectypes.typedefs[bib.rectype];

	for (var bdt_id in bib.values) {
// saw changed since new common data does override code in the query no need to do it here.
//		var name = /* prefix + " " + */ bdr[bdt_id]? bdr[bdt_id][0]  :  top.HEURIST.detailTypes.typedefs[bdt_id][1];
		var name = bdr['dtFields'][bdt_id][0];	//saw TODO: test this for changed record type where dt not in struct

		if (top.HEURIST.detailTypes.typedefs[bdt_id]['commonFields'][2] != "resource") {
			html += "<tr><td style='padding-left: " + indent + "em;'><i>" + htmlEscape(name) + "</i></td><td>";
 			for (var i=0; i < bib.values[bdt_id].length; ++i)
				html += htmlEscape(bib.values[bdt_id][i]) + "<br>";
			html += "</td></tr>";
		}
		else {
 			for (var i=0; i < bib.values[bdt_id].length; ++i) {
				var innerBibID = parseInt(bib.values[bdt_id][i]);
				var innerBib = bibs[innerBibID];
				if (! innerBib) continue;
				var rectype = top.HEURIST.rectypes.names[innerBib.rectype];

				if (innerBib.rectype == 75) {//MAGIC NUMBER
					// author editor is treated specially
					html += "<tr><td style='padding-left: " + indent + "em;'><i>" + prefix + " Author/Editor</i></td><td>" + htmlEscape(innerBib.title) + "</td></tr>";
				}
				else html += formatDetails(innerBib, rectype, indent+1);
			}
		}
	}

	return html;
}


function gotoNext() {
	// rearrange the alt bibids list
	var firstBibID = altBibIDs.shift();
	altBibIDs.push(firstBibID);
	fillInColumn(1, altBibIDs[0]);
}


function fillInColumn(colNum, bibID) {
	var bibCell = document.getElementById("bib" + colNum);
	var titleCell = document.getElementById("title" + colNum);
	var detailsCell = document.getElementById("details" + colNum);
	var notesCell = document.getElementById("notes" + colNum);

	var bib = bibs[bibID];

	if (! colNum) bibCell.innerHTML = "<b>Imported " + htmlEscape(top.HEURIST.rectypes.names[bib.rectype]).toLowerCase() + " record</b>";
	else {
		bibCell.innerHTML = "<b>Existing record #" + bibID + "</b>";
		if (altBibIDs.length > 0) {
			bibCell.innerHTML += " [<a href='#' onclick='gotoNext(); return false;'>view next</a>]";
		}
	}

	titleCell.innerHTML = "<b>" + htmlEscape(bib.title) + "</b>";

	notesCell.innerHTML = htmlEscape(bib.notes).replace(/\n/g, "<br>");

	detailsCell.innerHTML = "<table cellpadding=3><tbody>" + formatDetails(bib) + "</tbody></table>";
}
