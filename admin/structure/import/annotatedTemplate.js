//@TODO - deprecated (Heurist v.3)
//It is not used anywhere. This code either should be removed or re-implemented wiht new libraries
/**
* annotatedTemplate.js: javascript functionality for annotated template import from HeuristNetwork.org
*
* @note        End of file includes code to be added to a Wordpress site to enable this function
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


//
// init postMessage events
//
if (!window['postMessage'])
    alert("postMessage is not supported");
else {
    if (window.addEventListener) {
        //alert("standards-compliant");
        // For standards-compliant web browsers (ie9+)
        window.addEventListener("message", receiveMessage, false);
    }
    else {
        //alert("not standards-compliant (ie8)");
        window.attachEvent("onmessage", receiveMessage);
    }
}


//
// Send message to content of iframe
// (script in iframe replace div to button and send "check" request
//
function onFrameLoad(){
    //send message to frame
    sendMessage("heurist");
}

function sendMessage(message){
    //send message to frame
    var win = document.getElementById("templates").contentWindow;

    // Specify origin. Should be a domain or a wildcard "*"
    if (win === null || !window['postMessage'])
        alert("postMessage is not supported");
    else
        win.postMessage(message, "*"); 
}

//
// we can recieve 2 kind of messages
// heurist:add:[rectypeid] and heurist:check:[rectypeid]
//
function receiveMessage(event)
{
    var message;
    if (event.origin !== "http://heuristnetwork.org"){
        //if (false) {
        //message = 'You ("' + event.origin + '") are not worthy';
    } else {
        //message = 'I got "' + event.data + '" from "' + event.origin + '"';

        if(event.data.indexOf('heurist:add:')===0){  //RecTypeSource=

            var rectype = event.data.substr(event.data.lastIndexOf(':')+1);
            var _db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));
            var url = top.HEURIST.baseURL + "admin/structure/import/importRectype.php?db=" + _db+"&code="+rectype;

            top.HEURIST.util.popupURL(top, url,
                {   "close-on-blur": true,
                    "no-resize": false,
                    height: 480,
                    width: 620,
                    callback: function(context) {
                        if(! top.HEURIST.util.isnull(context)){
                            //var recID = Math.abs(Number(context.result[0]));
                        }
                    }
            });
        }else if(event.data.indexOf('heurist:check:')===0){ //RecTypeSource=


            var rectype = event.data.substr(event.data.lastIndexOf(':')+1);
            var _db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));
            var _url = top.HEURIST.baseURL + "admin/structure/import/importRectype.php?db=" + _db+"&output=json&checkid="+rectype;

            $.ajax({
                url: _url,
                data: null,
                success: function(data) {
                    if(data['error']){
                        alert(data['error']);
                    }else{
                        sendMessage("heuristexists");
                    }

                },
                dataType: 'json'
            });

            // alert(event.data);
        }
    }
    //alert(message);
}


// -----------------------------------------------------------------------------------------------------------------------------
//          CODE FOR INCLUSION IN WORDPRESS SITE
//
// To allow import an annotated template, the page must have the following piece of html code
// <div id="HeuristRecTypeSource">DB_ID-RECTYPE_ID</div>
// Where DB_ID is id of database and RECTYPE_ID is record type to be imported.
//
// -----------------------------------------------------------------------------------------------------------------------------


//function getRecord


/*
var ContentLocationInDOM = "#someNode > .childNode";
$(document).ready(loadContent);
function loadContent()
{
var QueryURL = “http://anyorigin.com/get?url=” + ExternalURL + “&callback=?”;
$.getJSON(QueryURL, function(data){
if (data && data != null && typeof data == “object” && data.contents && data.contents != null && typeof data.contents == “string”)
{
data = data.contents.replace(/<script[^>]*>[sS]*?</script>/gi, ”);
if (data.length > 0)
{
if (ContentLocationInDOM && ContentLocationInDOM != null && ContentLocationInDOM != “null”)
{
$(‘#queryResultContainer’).html($(ContentLocationInDOM, data));
}
else
{
$(‘#queryResultContainer’).html(data);
}
}
}
});
}
*/

//!!!!!!!!!!!!!!!!!!!!!!   1. insert this code into wordpress  wp-content/themes/[name]/js/heurist_messages.js
/*

if (!window['postMessage'])
alert("postMessage is not supported");
else {
if (window.addEventListener) {
//alert("standards-compliant");
// For standards-compliant web browsers (ie9+)
window.addEventListener("message", receiveMessage, false);
}
else {
//alert("not standards-compliant (ie8)");
window.attachEvent("onmessage", receiveMessage);
}
}
function receiveMessage(event)
{
var message;
//if (event.origin !== "https://Heuristplus.sydney.edu.au"){
if (false) {
message = 'Response: ("' + event.origin + '"), but not from Heuristplus.sydney.edu.au';
} else {

var dv = jQuery('#HeuristRecTypeSource');

if(event.data=="heurist"){

//document.getElementById('topbar').style.display = 'none';
//hide elements - old way
//jQuery('#header-full').hide();
//jQuery('#topbar').hide();
//jQuery('#footer').hide();

//new way
jQuery('#sidebar').hide();
jQuery('#main-header').hide();
jQuery('#main-footer').hide();
jQuery('#page-container').css('padding-top','1px);


//create button
//HeuristRecTypeSource
var rectypeid = dv.text();
if(rectypeid!==""){
dv.html(jQuery("<button>Import this Record type to my Heurist Database!</button>").click(function(){sendMessage("add:"+rectypeid);} )).show();
sendMessage("check:"+rectypeid);
}
}else if(event.data=="heuristexists"){  //
dv.append(jQuery("<br /><label><i>This record type already exists in your database (it may use a different name)</i></label>"));
}

}
//alert(message);
}

function sendMessage(rectypeid){
//send message to frame
var win = window.parent; //contentWindow;

// Specify origin. Should be a domain or a wildcard "*"
if (win === null || !window['postMessage'])
alert("postMessage is not supported");
else
win.postMessage("heurist:"+rectypeid, "*"); 

}
*/

//!!!!!!!!!!!!!!!!!!! 2. insert this code into wordpress  wp-content/themes/[name]/functions.php

/*
function my_custom_js() {
wp_enqueue_script( 'script-name', get_template_directory_uri() . '/js/heurist_messages.js', array(), '1.0.0', false );
}

//add_action('wp_head', 'my_custom_js');
add_action( 'wp_enqueue_scripts', 'my_custom_js' );

*/