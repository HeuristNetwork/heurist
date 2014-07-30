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
        var win = document.getElementById("templates").contentWindow;

        // Specify origin. Should be a domain or a wildcard "*"
        if (win == null || !window['postMessage'])
            alert("postMessage is not supported");
        else
            win.postMessage("heurist", "*"); // "http://heuristscholar.org");    
    
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
                
                if(event.data.indexOf('heurist:add:RecTypeSource=')===0){
                    
                    var rectype = event.data.substr(event.data.lastIndexOf('=')+1);
                    var _db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));
                    var url = top.HEURIST.basePath + "admin/structure/import/importRectype.php?db=" + _db+"&id="+rectype;
                    
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
                }else if(event.data.indexOf('heurist:check:RecTypeSource=')===0){
                   /* 
                    var rectype = event.data.substr(event.data.lastIndexOf('=')+1);
                    var _db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));
                    var _url = top.HEURIST.basePath + "admin/structure/import/importRectype.php?db=" + _db+"&output=json&checkid="+rectype;
                    
                    $.ajax({
                        url: _url,
                        data: null,
                        success: function(data) {
                            if(data['error']){
                                //alert(data['error']);
                            }else{
                                win.postMessage("heuristexists", "*");    
                            }
                            
                        },
                        dataType: 'json'
                    });                    
                   */ 
                    // alert(event.data);
                }
            }
            //alert(message);
}

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
            //if (event.origin !== "http://heuristscholar.org"){
            if (false) {
                message = 'Response: ("' + event.origin + '"), but not from HeuristScholar.org';
            } else {
                message = 'RECORD TYPE IMPORT UNDER DEVELOPMENT LATE APRIL 2014. Response: "' + event.data + '" from "' + event.origin + '"';
                
                var dv = jQuery('#HeuristRecTypeSource');
                
                if(event.data=="heurist"){
                
                //document.getElementById('topbar').style.display = 'none';
                jQuery('#header-full').hide();
                jQuery('#topbar').hide();
                jQuery('#footer').hide();
                
                //create button
                //HeuristRecTypeSource
                var rectypeid = dv.text(); 
                if(rectypeid!==""){
                dv.html(jQuery("<button>Import this Record type to my Heurist Database!</button>").click(function(){sendMessage("add:"+rectypeid);} )).show();
                sendMessage("check:"+rectypeid);
                }
                }else if(event.data=="heuristexists"){
                dv.append(jQuery("<br /><label><i>This record type is already in your database - a new record type will be created</i></label>"));
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
            win.postMessage("heurist:"+rectypeid, "*"); // "http://heuristscholar.org");    

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