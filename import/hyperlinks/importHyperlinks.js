/**
* importHyperlinks.js
* 
* Provides client-side JavaScript functions for the hyperlink import functionality, including UI interactions and data handling.
* 
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Stephen White
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       3.1.0
*/

/**
 * Highlights the selected radio button option when disambiguating existing links.
 * @param {number} linkno - The identifier for the link group.
 * @return {void}
 */
 function selectExistingLink(linkno) {
	let radios = document.getElementsByName('recID['+linkno+']');
	for(let i=0; i < radios.length; ++i) {
		if (radios[i].checked)
			radios[i].parentNode.parentNode.style.backgroundColor = '#C0C0C0';
		else
			radios[i].parentNode.parentNode.style.backgroundColor = '';
	}
}

/**
 * Checks all "Use Notes" checkboxes on the page.
 * @return {void}
 */
function selectAllNotes() {
	let noteses = document.getElementsByTagName('input');
	for(let i=0; i < noteses.length; ++i)
		if (noteses[i].className == 'use_notes_checkbox') noteses[i].checked = true;
}

/**
 * Unchecks all "Use Notes" checkboxes on the page.
 * @return {void}
 */
function deselectAllNotes() {
	let noteses = document.getElementsByTagName('input');
	for(let i=0; i < noteses.length; ++i)
		if (noteses[i].className == 'use_notes_checkbox') noteses[i].checked = false;
}

/**
 * Checks all main link selection checkboxes and their corresponding "Use Notes" if applicable.
 * @return {void}
 */
function checkAll() {
	let i = 1;
	while (document.getElementsByName('link['+i+']').length) {
		let e = document.getElementById('flag'+i);
		if (e) {
			e.checked = true;
			let t = document.getElementById('t'+i).value;
			let n = document.getElementById('n'+i).value;
			if (n.length > t.length) {
				let e2 = document.getElementById('un'+i);
				if (e2) e2.checked = true;
			}
		}
		i++;
	}
}

/**
 * Unchecks all main link selection checkboxes and their corresponding "Use Notes" checkboxes.
 * @return {void}
 */
function unCheckAll() {
	let i = 1;
	while (document.getElementsByName('link['+i+']').length) {
		let e = document.getElementById('flag'+i);
		if (e) {
			e.checked = false;
			let e2 = document.getElementById('un'+i);
			if (e2) e2.checked = false;
		}
		i++;
	}
}

/**
 * Handles the click event for a button that can either "Lookup Title" or "Revert" a title.
 * If current value is "Lookup Title", calls `lookupTitle`.
 * Otherwise, swaps the current title with an alternative title stored in a hidden field.
 * @param {HTMLElement} button - The button element that was clicked.
 * @param {number} linkno - The identifier for the link group.
 * @return {void}
 */
function lookup_revert(button, linkno){

	if (button.value == 'Lookup Title'){
		lookupTitle(button);
	} else {
		let e1 = document.getElementById('t'+linkno);
		let e2 = document.getElementById('at'+linkno);
		let tmp = e1.value;
		e1.value = e2.value;
		e2.value = tmp;
	}
}


/**
 * Fetches the title of a webpage based on its URL.
 * Updates the corresponding title input field and changes the button text to "Revert".
 * Handles UI locking to prevent multiple simultaneous lookups.
 * @param {HTMLElement} button - The "Lookup Title" button element that was clicked.
 * @return {void}
 */
function lookupTitle(button) {

    // button.display.style = 'none';
    
	// buttonName should be "lookup[xxx]"; we extract that numeric xxx
	let buttonName = button.name;

	let buttonNum, titleElt, urlElt;
	if (buttonName != 'popup') {
        //lookup title
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
		let lockedNum = document.forms['mainform'].elements['titlegrabber_lock'].value;
		let lockedTitleElt = document.forms['mainform'].elements['title['+lockedNum+']'];
		let lockedLookupElt = document.forms['mainform'].elements['lookup['+lockedNum+']'];

		if (lockedTitleElt) lockedTitleElt.disabled = false;
		if (lockedLookupElt) lockedLookupElt.disabled = false;
		document.forms['mainform'].elements['titlegrabber_lock'].value = 0;
	}

	document.forms['mainform'].elements['titlegrabber_lock'].value = buttonNum;
	button.disabled = true;
	titleElt.disabled = true;
    
	let baseurl = window.hWin.HAPI4.baseURL+'import/hyperlinks/getTitleFromURL.php';//'?num='+buttonNum+'&url='+escape(urlElt.value);
    
    window.hWin.HEURIST4.util.sendRequest(baseurl, 
            {db:window.hWin.HAPI4.database,
             url:urlElt.value,
             num:buttonNum},
            null, 
    function(response){
        if(response.status==window.hWin.ResponseStatus.OK){
            
                    response = response.data;
                    if(!window.hWin.HEURIST4.util.isnull(response)){
                        let num = response.num;
                        
                        let lockedLookupElt = document.forms['mainform'].elements['lookup['+num+']'];
                        let lockedTitleElt = document.forms['mainform'].elements['title['+num+']'];
                        lockedTitleElt.disabled = false;
                        lockedLookupElt.disabled = false;
                    
                        if(response.error){
                            lockedLookupElt.value = 'URL error';
                            lockedLookupElt.title = "";
                            window.hWin.HEURIST4.msg.showMsgErr(response.error);
                        }else{
                            lockedLookupElt.value = 'Revert';
                            lockedLookupElt.title = "Revert title";
                            lockedTitleElt.value = response.title;
                        }
                    }
            
        }else{
            window.hWin.HEURIST4.msg.showMsgErr(response);    
        }
    },'json');
}


/**
 * Initiates the bookmarking process for selected links.
 * If no links are checked, it shows a flash message.
 * Otherwise, it opens a dialog for adding tags and then submits the main form.
 * @return {void}
 */
function doBookmark(){
    
   if ($('input.check_link:checked').length==0){
        window.hWin.HEURIST4.msg.showMsgFlash('Select at least one link');
        return;
   }
    
   let opts = {
       title: 'Bookmark selected URLs',
       modes: ['bookmark_url'],
       groups: 'personal',
        onClose:
           function( context ){
               if(context){
                     document.getElementById('wgTags').value = context.join(',');
                     document.getElementById('adding_tags_elt').value = 1;
                     document.forms[0].submit();
               }
           }
   }; 


   window.hWin.HEURIST4.ui.showRecordActionDialog('recordTag', opts); 
    
/*
   top.HEURIST.util.popupURL(window, top.HEURIST.baseURL+'records/tags/addTagsPopup.html?bookmark-only=1&db='+dbname,
   				{   
                    title: 'Add tags and bookmark',
                    height:400, width:550,
                    callback: function(tags) {
                        if(tags=='~~~~bookmark-only~~~~'){
                            document.getElementById('wgTags').value = '';
                            document.getElementById('adding_tags_elt').value = 1;
                            document.forms[0].submit();
                        }else if(tags){
                            document.getElementById('wgTags').value = tags;
                            document.getElementById('adding_tags_elt').value = 1;
                            document.forms[0].submit();
                        }
   						}
   				} );
*/
   return false;
}