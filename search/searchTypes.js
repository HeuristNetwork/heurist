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
* brief description of file
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
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
/*
 * Small popup that is invoked by click on icon left to main search input field - it determines 3 main search types
 * 1) record title
 * 2) tags
 * 3) all fields
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 */

var stype_div, stype_anchor;
var stype_selected_number;
var stype_options;
var stype_input;


function init_stype() {
	stype_div = document.createElement('div');
		stype_div.style.position = 'absolute';
		stype_div.id = 'stype_options';

	var stype_div_child = document.createElement('div');
		stype_div_child.id = 'stype_options_child';
		stype_div_child.style.position = 'relative';
	stype_div.appendChild(stype_div_child);

	stype_anchor = document.createElement('a');
		stype_anchor.href = '#';
		stype_anchor.onclick = function() { return false; }
	stype_div_child.appendChild(stype_anchor);

	var q_elt = document.getElementById('q');
		q_elt.style.padding = '0 20px 0 31px';
		q_elt.style.backgroundRepeat = 'no-repeat';
		q_elt.style.backgroundPosition = '3px 3px';
		if (q_elt.attachEvent) q_elt.attachEvent('onmousemove', function(e) {
			if (! e) e = window.event;
			var targ = e.target;  if (! targ) targ = e.srcElement;
			if (e.clientX - top.HEURIST.getPosition(targ).x < 31) {
				targ.style.cursor = 'default';
			} else {
				targ.style.cursor = '';
			}
		});
		else q_elt.setAttribute('onmousemove', "if (event.pageX - top.HEURIST.getPosition(this).x < 31) style.cursor = 'default'; else style.cursor = '';");

		// workaround to make sure that caret position of textbox is preserved
		if (q_elt.attachEvent) q_elt.attachEvent('onmousedown', function(e) {
			if (! e) e = window.event;
			var targ = e.target;  if (! targ) targ = e.srcElement;
			if (e.clientX - top.HEURIST.getPosition(targ).x < 31) {
				window.inclick = 1;
				return false;
			}
			window.inclick = 0;
		});
		else q_elt.setAttribute('onmousedown', "if (event.pageX - top.HEURIST.getPosition(this).x < 31) { window.inclick = 1;  return false; } window.inclick = 0;");
		if (q_elt.attachEvent) q_elt.attachEvent('onmouseup', function(e) {
			if (! e) e = window.event;
			if (window.inclick) {
				window.inclick = 0;
				popup_stype_options();
				return false;
			}
		});
		else q_elt.setAttribute('onmouseup', "if (window.inclick) { window.inclick = 0; popup_stype_options(); return false; }");
	(q_elt.parentNode).insertBefore(stype_div, q_elt);

	stype_input = document.createElement('input');
		stype_input.id = 'stype';
		stype_input.type = 'hidden';
		stype_input.name = 'stype';
		stype_input.value = '';
	q_elt.form.appendChild(stype_input);


	var pos = top.HEURIST.getPosition(q_elt);
	stype_div.style.top = (pos.y + q_elt.offsetHeight) + 'px';
	//stype_div.style.left = (pos.x - 1) + 'px';

	stype_selected_number = 0;
	stype_options = { length: 0 };
}


function new_stype_option(title, tooltip, value, icon) {
	var offset = stype_options.length;
	var new_option = document.createElement('div');
		new_option.id = 'stype-'+offset;
		new_option.className = 'stype_option';
		new_option.value = value;
		new_option.title = tooltip;
		new_option.onclick = select_stype_option;
		new_option.onmouseover = function() { new_option.className = 'stype_option hover'; }
		new_option.onmouseout = function() { new_option.className = 'stype_option'; }
		new_option.style.backgroundImage = 'url(../common/images/stype/16x16-' + icon + ')';
		new_option.appendChild(document.createTextNode(title));
	stype_anchor.appendChild(new_option);
	stype_options[new_option.id] = { 'icon': icon, 'title': title, 'offset': offset, 'value': value };

	if (location.search.indexOf('stype='+value) > 0  ||
	    (offset == 0  &&  location.search.indexOf('stype=') == -1)) {	// first option is selected by default
		var q_elt = document.getElementById('q');
			q_elt.style.backgroundImage = 'url(../common/images/stype/' + icon + ')';
			q_elt.title = title.replace('Search', 'Searching') + ': ' + tooltip;
		stype_input.value = value;
		stype_selected_number = offset;
	}

	++stype_options.length;
}


function popup_stype_options() {
	if (document.getElementById('stype_options').style.display == 'block') {
		// already popped-up
		blur_stype_options();
		return;
	}

	document.getElementById('stype_options').style.display = 'block';
	stype_anchor.onblur = blur_stype_options;
	stype_anchor.focus();
}

function select_stype_option(e) {
	if (! e) e = window.event;

	var targ = null;
	if (e.target) targ = e.target;
	else if (e.srcElement) targ = e.srcElement;
	if (targ.nodeType == 3) targ = targ.parentNode;

	if (! targ.id) return;

	set_stype_option(targ.id);
}

function set_stype_option(id) {
	if (! id  ||  ! stype_options[id]) return;
	var selected_option = stype_options[id];

	stype_anchor.onblur = null;
	stype_div.style.display = 'none';
	stype_selected_number = selected_option.offset;

	var value = stype_input.value = selected_option.value;

	var q_elt = document.getElementById('q');

	q_elt.style.backgroundImage = 'url(../common/images/stype/'+selected_option.icon+')';
	q_elt.title = selected_option.title.replace('Search', 'Searching');
	q_elt.focus();
}

function blur_stype_options() {
	stype_anchor.onblur = null;
	setTimeout(function() { stype_div.style.display = 'none'; }, 300);
}


init_stype();
new_stype_option('Search title fields', 'Words or phrases in search string are matched against record titles. Advanced search instructions can also be entered here', '', 'standard-icon.gif');
new_stype_option('Search tags', 'Words or phrases in search string are matched against personal and workgroup tags', 'key', 'tags2-icon.gif');
new_stype_option('Search all fields', 'Words or phrases in search string are matched against every field in the data (matches from start of field only)', 'all', 'all-icon.gif');
