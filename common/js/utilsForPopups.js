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
* Popup window functions
* Open the given URL in a nicely blinged-out iframe popup in the top-level window (even if invoked from a nested frame)
* which will be cancelled by the user clicking outside it.
* The width and height of the popup are controlled by the width and height of the <body> of the loaded document* brief description of file
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


var page_size = null;
var view_size = null;

function popup_url(url, x, y) {
	if (arguments.length == 1) {
		var offset = (top.popup_windows? top.popup_windows.length : 0) * 8;
		x = 100 + offset; y = 50 + offset;
	}

	cover_top_document();

	var outer_popup_div = top.document.createElement('div');
	outer_popup_div.className = 'outer_popup';
	outer_popup_div.expando = 1;
	outer_popup_div.x = x;
	outer_popup_div.y = y;

	if (top.pageYOffset)
		scrolltop = top.pageYOffset;
	else if (top.document.documentElement && top.document.documentElement.scrollTop)
		scrolltop = top.document.documentElement.scrollTop;
	else if (top.document.body)
		scrolltop = top.document.body.scrollTop;
	else
		scrolltop = 0;

	outer_popup_div.style.filter = 'progid:DXImageTransform.Microsoft.Alpha(opacity=0)';
	outer_popup_div.style.top = scrolltop + 'px';

	var shadow_popup_div = top.document.createElement('div');
	shadow_popup_div.className = 'shadow_popup';

	var inner_popup_div = top.document.createElement('div');
	inner_popup_div.className = 'inner_popup';

	var titlebar_table = top.document.createElement('table');
	titlebar_table.className = 'title_bar';

	titlebar_table.appendChild(top.document.createElement('tbody'));
	titlebar_table.firstChild.appendChild(top.document.createElement('tr'));
	var titlebar_dragbar = top.document.createElement('td');
	titlebar_dragbar.className = 'drag_bar';
	addListener(titlebar_dragbar, 'mousedown', popup_begin_drag);
	titlebar_table.firstChild.firstChild.appendChild(titlebar_dragbar);

	var cancel_button = top.document.createElement('td');
	cancel_button.className = 'cancel';
	cancel_button.innerHTML = '&nbsp;<img src="../images/x2.gif">';
	addListener(cancel_button, 'click', cancel_click);
	titlebar_table.firstChild.firstChild.appendChild(cancel_button);

	var inner_popup_iframe = top.document.createElement('iframe');
	inner_popup_iframe.className = 'inner_popup';
	inner_popup_iframe.frameBorder = 0;
	inner_popup_iframe.expando = 1;
	inner_popup_iframe.loader_fn = function() { inner_popup_iframe_loaded(inner_popup_iframe, outer_popup_div); }
	addListener(inner_popup_iframe, 'load', inner_popup_iframe.loader_fn);

	if (! top.timeoutLength) top.timeoutLength = 5000;
	inner_popup_iframe.timeoutID = setTimeout(function() { popup_timeout(inner_popup_iframe); }, top.timeoutLength);

	inner_popup_div.appendChild(titlebar_table);
	inner_popup_div.appendChild(inner_popup_iframe);

	outer_popup_div.appendChild(shadow_popup_div);
	outer_popup_div.appendChild(inner_popup_div);

	top.frosted_glass.style.zIndex = (top.popup_windows? top.popup_windows.length : 0)*2;
	outer_popup_div.style.zIndex = (top.popup_windows? top.popup_windows.length : 0)*2 + 1;
	top.coverall_div.appendChild(outer_popup_div);
	inner_popup_iframe.src = url;

	if (! top.popup_windows) top.popup_windows = new Array();
	top.popup_windows.push(outer_popup_div);

	inner_popup_iframe.contentWindow._opener = window;
}


function inner_popup_iframe_loaded(ifr, popup_div) {
	removeListener(ifr, 'load', ifr.loader_fn);

	var width = parseInt(ifr.contentWindow.document.body.style.width);
	var height = parseInt(ifr.contentWindow.document.body.style.height);
	if (! width) return;
	clearTimeout(ifr.timeoutID);

	top.coverall_div.style.cursor = '';

	ifr.style.width = (width+18) + 'px';	/* I DON'T KNOW WHERE THE 18/16 COMES FROM! Need them to stop moz scrollbars. */
	ifr.style.height = (height+16) + 'px';

	popup_div.childNodes[1].style.width = '18px';
	popup_div.childNodes[1].style.height = '18px';
	var titlebar_height = parseInt(popup_div.childNodes[1].childNodes[0].offsetHeight);

	var fudgeAmt = 0;
	if (ifr.contentWindow.document.body.style.overflow != 'hide') {
		fudgeAmt = 16;
	}

	var i;
	for (i=0; i < popup_div.childNodes.length; ++i) {
		popup_div.childNodes[i].style.width = (width + 18 + fudgeAmt) + 'px';
		popup_div.childNodes[i].style.height = (height + 16 + fudgeAmt + titlebar_height) + 'px';
	}

	popup_div.style.left = popup_div.x + 'px';
	popup_div.style.top = popup_div.y + 'px';
	popup_div.style.filter = '';

	ifr.contentWindow.close = close_popup;
}


function popup_timeout(ifr) {
	removeListener(ifr, 'load', ifr.loader_fn);
	top.close_popup();
	alert('Problem contacting server - please try again in a moment');

	// gradually increase the timeout interval each time it occurs - compensate for slow connections
	top.timeoutLength += 1500;
}


function cover_top_document() {
	/* Cover the entire page in a translucent iframe; this blocks out input elements (e.g. dropdowns) in IE.
	 * Then cover the iframe in a transparent div; this captures click events.
	 */
	if (top.coverall_iframe) {
		if (top.coverall_iframe.style.display == 'none') {
			top.coverall_iframe.style.display = 'block';
			top.coverall_div.style.display = 'block';
		}
		return;
	}

	page_size = get_page_size();
	view_size = get_view_size();

	top.coverall_iframe = top.document.createElement('div');
	top.coverall_iframe.className = 'coverall';
	top.coverall_iframe.src = '../html/blank.html';
	top.coverall_iframe.frameBorder = 0;
	top.document.body.appendChild(top.coverall_iframe);
		top.coverall_iframe.style.width = page_size.w + 'px';
		top.coverall_iframe.style.height = page_size.h + 'px';

	top.coverall_div = top.document.createElement('div');
	top.coverall_div.className = 'coverall';
	top.document.body.appendChild(top.coverall_div);
		top.coverall_div.style.width = page_size.w + 'px';
		top.coverall_div.style.height = page_size.h + 'px';

	top.frosted_glass = top.document.createElement('div');
	top.frosted_glass.className = 'frosted_glass';
	top.coverall_div.appendChild(top.frosted_glass);
		top.frosted_glass.style.width = page_size.w + 'px';
		top.frosted_glass.style.height = page_size.h + 'px';
		top.frosted_glass.style.zIndex = 1;

	top.coverall_cover = top.document.createElement('div');
	top.coverall_cover.className = 'coverall_cover';
	top.coverall_div.appendChild(top.coverall_cover);
	top.coverall_div.style.cursor = 'wait';

	//addListener(top.coverall_div, 'click', cancel_click);;
		/* removed this ... on IE a mousedown / mouseup on the titlebar was still firing a click event */
}


function cancel_click(e) {
/*
	if (! e) e = window.event;

	var targ;
	if (e.target) targ = e.target;
	else if (e.srcElement) targ = e.srcElement;
	if (targ.nodeType == 3) targ = targ.parentNode;	// safari bug

	var outer_div = targ.parentNode.parentNode.parentNode.parentNode.parentNode;

	// remove the outer_div from the top.popup_windows array ... should use a mutex
	for (i=0; i < top.popup_windows.length; ++i) {
		if (top.popup_windows[i] == outer_div) {
			top.popup_windows.splice(i, 1);
			break;
		}
	}

	outer_div.parentNode.removeChild(outer_div);

	if (top.popup_windows.length == 0) {
		top.coverall_iframe.style.display = 'none';
		top.coverall_div.style.display = 'none';
	}
*/
	top.close_popup();

	return endEvent(e);
}


function close_popup(_top) {
	/* cancel the most recent popup */
	if (! _top) _top = top;
	var most_recent = _top.popup_windows.pop();

	if (_top.popup_windows.length == 0) {
		_top.frosted_glass.style.zIndex = 0;
		_top.coverall_iframe.style.display = 'none';
		_top.coverall_div.style.display = 'none';
	} else {
		_top.frosted_glass.style.zIndex = (_top.popup_windows.length-1)*2;
	}

	most_recent.parentNode.removeChild(most_recent);
}


function get_scrollbar_size() {
	/* scrollbar size determination that works on almost everything except IE */
	if (top.scrollbar_size != undefined) return top.scrollbar_size;


	var scr = document.createElement('div');
	scr.style.position = 'absolute';
	scr.style.top = '-1000px';
	scr.style.width = '100px';
	scr.style.height = '50px';
	scr.style.overflow = 'hidden';

	var inn = document.createElement('div');
	inn.style.width = '100%';
	inn.style.height = '200px';

	scr.appendChild(inn);
	document.body.appendChild(scr);

	// Width of the inner div sans scrollbar
	var wNoScroll = inn.offsetWidth;
	// Add the scrollbar
	scr.style.overflow = 'auto';
	// Width of the inner div width scrollbar
	var wScroll = inn.offsetWidth;

	document.body.removeChild(scr);

	if (wNoScroll == wScroll) {
		top.scrollbar_size = get_scrollbar_size2();
	} else {
		top.scrollbar_size = (wNoScroll - wScroll);
	}

	return top.scrollbar_size;
}

function get_scrollbar_size2() {
	/* Scrollbar size determination that works on everything except mozilla ... sigh.
	 * Looks at the height of a textarea with and without horizontal scrollbars;
	 * mozilla steals typing space from the user instead of resizing the element
	 */

	var ta = document.createElement('textarea');
		ta.style.position = 'absolute';
		ta.style.left = '-1000px';
		ta.rows = 2;
		ta.cols = 80;
		ta.wrap = 'off';
	document.body.appendChild(ta);

	var height = ta.offsetHeight;
	ta.wrap = 'soft';
	height = (height - ta.offsetHeight)

	document.body.removeChild(ta);

	return height;
}


function get_view_size() {
	/* get the size of the viewport */
	var x,y;
	if (top.innerHeight) { // all except Explorer
		x = top.innerWidth;
		y = top.innerHeight;
	} else if (top.document.documentElement && top.document.documentElement.clientHeight) {
		// Explorer 6 Strict Mode
		x = top.document.documentElement.clientWidth;
		y = top.document.documentElement.clientHeight;
	} else if (top.document.body) { // other Explorers
		x = top.document.body.clientWidth;
		y = top.document.body.clientHeight;
	}

	var dims = new Object();
	dims.w = x;  dims.h = y;
	return dims;
}


function get_page_size() {
	/* get the total page size, including any that has scrolled off the bottom / sides */
	var x,y;
	var test1 = top.document.body.scrollHeight;
	var test2 = top.document.body.offsetHeight
	var test3 = top.innerHeight;
	if (test1 >= test2) {	// all but Explorer Mac
		x = Math.max(top.document.body.scrollWidth, (top.innerWidth? top.innerWidth : 0));
		y = Math.max(top.document.body.scrollHeight, (top.innerHeight? top.innerHeight : 0));
	} else {	// Explorer Mac;
     			//would also work in Explorer 6 Strict, Mozilla and Safari
		x = top.document.body.offsetWidth;
		y = top.document.body.offsetHeight;
	}

	var dims = new Object();
	dims.w = x;  dims.h = y;
	return dims;
}


function addListener(obj, event_name, handler) {
	if (obj.attachEvent)
		obj.attachEvent('on'+event_name, handler);
	else
		obj['on'+event_name] = handler;
return;

	if (obj.addEventListener)
		obj.addEventListener(event_name, handler, false);
	else
		obj.attachEvent('on'+event_name, handler);
}


function removeListener(obj, event_name, handler) {
	if (obj.detachEvent)
		obj.detachEvent('on'+event_name, handler);
	else
		obj['on'+event_name] = null;
return;

	if (obj.removeEventListener)
		obj.removeEventListener(event_name, handler, false);
	else
		obj.detachEvent('on'+event_name, handler);
}


function endEvent(e) {
	e.cancelBubble = true;
	if (e.stopPropagation) e.stopPropagation();
	return false;
}


function popup_begin_drag(e) {
	if (! e) e = window.event;

	var targ;
	if (e.target) targ = e.target;
	else if (e.srcElement) targ = e.srcElement;
	if (targ.nodeType == 3) targ = targ.parentNode;	// safari bug

	// targ is the titlebar, which makes its great-great-GREAT-grand-parent the outer-div
	var outer_div = targ.parentNode.parentNode.parentNode.parentNode.parentNode;

	if ((e.which && e.which == 3) || (! e.which && e.button && e.button == 2)) return endEvent(e);	// right-click

	outer_div.expando = true;
	outer_div.begin_click_pos = { x: e.screenX, y: e.screenY };
	outer_div.begin_window_pos = { x: parseInt(outer_div.style.left), y: parseInt(outer_div.style.top) };
	outer_div.move_handler = function(e) { return popup_continue_drag(e, outer_div); };
	outer_div.up_handler = function(e) { return popup_end_drag(e, outer_div); };

	outer_div.childNodes[0].style.display = 'none';
	outer_div.childNodes[1].childNodes[0].style.display = 'none';
	outer_div.childNodes[1].childNodes[1].style.display = 'none';
	outer_div.childNodes[1].style.MozOpacity = '0.5';
	outer_div.childNodes[1].style.filter = 'progid:DXImageTransform.Microsoft.Alpha(opacity=50)';

	top.coverall_cover.style.display = 'block';
	top.coverall_cover.style.cursor = 'move';

	addListener(top.coverall_cover, 'mousemove', outer_div.move_handler);
	addListener(top.coverall_cover, 'mouseup', outer_div.up_handler);

	return endEvent(e);
}


function popup_continue_drag(e, outer_div) {
	if (! e) e = window.event;

	var click_diff_x = e.screenX - outer_div.begin_click_pos.x;
	var click_diff_y = e.screenY - outer_div.begin_click_pos.y;

	var new_pos_x = (outer_div.begin_window_pos.x + click_diff_x);
	var new_pos_y = (outer_div.begin_window_pos.y + click_diff_y);

	if (! outer_div.limit_x) {
		outer_div.expando = 1;

		if (! page_size) page_size = get_page_size();

		var childWidth = parseInt(outer_div.childNodes[1].style.width);
		var childHeight = parseInt(outer_div.childNodes[1].style.height);

		outer_div.limit_x = page_size.w - childWidth - 20;
		outer_div.limit_y = page_size.h - childHeight - 20;
	}
	if (new_pos_x < 0) new_pos_x = 0;
	else if (new_pos_x > outer_div.limit_x) new_pos_x = outer_div.limit_x;
	if (new_pos_y < 0) new_pos_y = 0;
	else if (new_pos_y > outer_div.limit_y) new_pos_y = outer_div.limit_y;

	outer_div.style.left = new_pos_x + 'px';
	outer_div.style.top = new_pos_y + 'px';

	return endEvent(e);
}


function popup_end_drag(e, outer_div) {
	popup_continue_drag(e, outer_div);	// update drag position

	// clean up the event handlers
	removeListener(top.coverall_cover, 'mousemove', outer_div.move_handler);
	removeListener(top.coverall_cover, 'mouseup', outer_div.up_handler);
	top.coverall_cover.style.cursor = '';
	top.coverall_cover.style.display = 'none';

	outer_div.childNodes[0].style.display = '';
	outer_div.childNodes[1].childNodes[0].style.display = '';
	outer_div.childNodes[1].childNodes[1].style.display = '';
	outer_div.childNodes[1].style.MozOpacity = '';
	outer_div.childNodes[1].style.filter = '';

	return endEvent(e);
}
