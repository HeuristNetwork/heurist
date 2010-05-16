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
		q_elt.style.paddingLeft = '31px';
		q_elt.style.backgroundRepeat = 'no-repeat';
		q_elt.style.backgroundPosition = '3px 1px';
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
	stype_div.style.left = (pos.x - 1) + 'px';

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
		new_option.style.backgroundImage = 'url(img/stype/16x16-' + icon + ')';
		new_option.appendChild(document.createTextNode(title));
	stype_anchor.appendChild(new_option);
	stype_options[new_option.id] = { 'icon': icon, 'title': title, 'offset': offset, 'value': value };

	if (location.search.indexOf('stype='+value) > 0  ||
	    (offset == 0  &&  location.search.indexOf('stype=') == -1)) {	// first option is selected by default
		var q_elt = document.getElementById('q');
			q_elt.style.backgroundImage = 'url(img/stype/' + icon + ')';
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

	q_elt.style.backgroundImage = 'url(/heurist/img/stype/'+selected_option.icon+')';
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
