function selectExistingLink(linkno) {
	var radios = document.getElementsByName('bib_id['+linkno+']');
	for (i=0; i < radios.length; ++i) {
		if (radios[i].checked)
			radios[i].parentNode.parentNode.style.backgroundColor = '#C0C0C0';
		else
			radios[i].parentNode.parentNode.style.backgroundColor = '';
	}
}


function selectAllNotes() {
	var noteses = document.getElementsByTagName('input');
	for (i=0; i < noteses.length; ++i)
		if (noteses[i].className == 'use_notes_checkbox') noteses[i].checked = true;
}

function deselectAllNotes() {
	var noteses = document.getElementsByTagName('input');
	for (i=0; i < noteses.length; ++i)
		if (noteses[i].className == 'use_notes_checkbox') noteses[i].checked = false;
}


