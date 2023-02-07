/*
* Copyright (C) 2005-2023 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
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
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
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

	if (button.value == 'Lookup Title'){
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

    // button.display.style = 'none';
    
	// buttonName should be "lookup[xxx]"; we extract that numeric xxx
	var buttonName = button.name;

	var buttonNum, titleElt, urlElt;
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
    
	var baseurl = window.hWin.HAPI4.baseURL+'import/hyperlinks/getTitleFromURL.php';//'?num='+buttonNum+'&url='+escape(urlElt.value);
    
    window.hWin.HEURIST4.util.sendRequest(baseurl, 
            {db:window.hWin.HAPI4.database,
             url:urlElt.value,
             num:buttonNum},
            null, 
    function(response){
        if(response.status==window.hWin.ResponseStatus.OK){
            
                    response = response.data;
                    if(!window.hWin.HEURIST4.util.isnull(response)){
                        var num = response.num;
                        
                        var lockedLookupElt = document.forms['mainform'].elements['lookup['+num+']'];
                        var lockedTitleElt = document.forms['mainform'].elements['title['+num+']'];
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

//
//
//
function doBookmark(){
    
   if ($('input.check_link:checked').length==0){
        window.hWin.HEURIST4.msg.showMsgFlash('Select at least one link');
        return;
   }
    
   var opts = {
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