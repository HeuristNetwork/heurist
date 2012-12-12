/*
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 */

//
//
//
 function selectExistingLink(linkno) {
	var radios = document.getElementsByName('recID['+linkno+']');
	for (i=0; i < radios.length; ++i) {
		if (radios[i].checked)
			radios[i].parentNode.parentNode.style.backgroundColor = '#C0C0C0';
		else
			radios[i].parentNode.parentNode.style.backgroundColor = '';
	}
}

//
//
//
function selectAllNotes() {
	var noteses = document.getElementsByTagName('input');
	for (i=0; i < noteses.length; ++i)
		if (noteses[i].className == 'use_notes_checkbox') noteses[i].checked = true;
}

//
//
//
function deselectAllNotes() {
	var noteses = document.getElementsByTagName('input');
	for (i=0; i < noteses.length; ++i)
		if (noteses[i].className == 'use_notes_checkbox') noteses[i].checked = false;
}

//
//
//
function checkAll() {
	var i = 1;
	while (document.getElementsByName('link['+i+']').length) {
		var e = document.getElementById('flag'+i);
		if (e) {
			e.checked = true;
			var t = document.getElementById('t'+i).value;
			var n = document.getElementById('n'+i).value;
			if (n.length > t.length) {
				var e2 = document.getElementById('un'+i);
				if (e2) e2.checked = true;
			}
		}
		i++;
	}
}

//
//
//
function unCheckAll() {
	var i = 1;
	while (document.getElementsByName('link['+i+']').length) {
		var e = document.getElementById('flag'+i);
		if (e) {
			e.checked = false;
			e2 = document.getElementById('un'+i);
			if (e2) e2.checked = false;
		}
		i++;
	}
}

//
//
//
function lookup_revert(button, linkno){

	if (button.value == 'Lookup'){
		lookupTitle(button);
	} else {
		var e1 = document.getElementById('t'+linkno);
		var e2 = document.getElementById('at'+linkno);
		var tmp = e1.value;
		e1.value = e2.value;
		e2.value = tmp;
	}
}

//
//
//
function lookupTitle(button) {

	// buttonName should be "lookup[xxx]"; we extract that numeric xxx
	var buttonName = button.name;

	var buttonNum, titleElt, urlElt;
	if (buttonName != 'popup') {
		buttonNum = parseInt(buttonName.substring(7));

		titleElt = document.forms['mainform'].elements['title['+buttonNum+']'];
		urlElt = document.forms['mainform'].elements['link['+buttonNum+']'];
	} else {
		buttonNum = 'popup';
		titleElt = document.getElementById('popupTitle');
		urlElt = document.getElementById('popupUrl');
	}
	if (! titleElt  ||  ! urlElt) return; // can't do anything


	// if we're already grabbing some other title, cancel that one (people will learn not to do this!)
	if (document.forms['mainform'].elements['titlegrabber_lock'].value  &&  document.forms['mainform'].elements['titlegrabber_lock'].value != 'popup')
	{
		var lockedNum = document.forms['mainform'].elements['titlegrabber_lock'].value;
		var lockedTitleElt = document.forms['mainform'].elements['title['+lockedNum+']'];
		var lockedLookupElt = document.forms['mainform'].elements['lookup['+lockedNum+']'];

		if (lockedTitleElt) lockedTitleElt.disabled = false;
		if (lockedLookupElt) lockedLookupElt.disabled = false;
		document.forms['mainform'].elements['titlegrabber_lock'].value = 0;
	}

	document.forms['mainform'].elements['titlegrabber_lock'].value = buttonNum;
	button.disabled = true;
	titleElt.disabled = true;

	frames['grabber'].location.href = 'getTitleFromURL.php?num='+buttonNum+'&url='+escape(urlElt.value);
}

//
//
//
function doBookmark(dbname){

   top.HEURIST.util.popupURL(window, '../records/tags/addTagsPopup.html?db='+dbname,
   				{ callback: function(tags) {
   							document.getElementById('wgTags').value = tags;
   							document.getElementById('adding_tags_elt').value = 1;
   							document.forms[0].submit();
   						}
   				} );

   return false;
}