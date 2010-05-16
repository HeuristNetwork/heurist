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
	if (document.forms['mainform'].elements['titlegrabber_lock'].value  &&  document.forms['mainform'].elements['titlegrabber_lock'].value != 'popup') {
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

	frames['grabber'].location.href = 'titlegrabber.php?num='+buttonNum+'&url='+escape(urlElt.value);
}
