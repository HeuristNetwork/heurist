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

function onFrameLoad(){
    
        //send message to frame    
        var win = document.getElementById("templates").contentWindow;

        // Specify origin. Should be a domain or a wildcard "*"
        if (win == null || !window['postMessage'])
            alert("postMessage is not supported");
        else
            win.postMessage("heurist", "*"); // "http://heuristscholar.org");    
    
}

function receiveMessage(event)
{
            var message;
            //if (event.origin !== "http://heuristnetwork.org"){
            if (false) {
                message = 'You ("' + event.origin + '") are not worthy';
            } else {
                message = 'I got "' + event.data + '" from "' + event.origin + '"';
            }
            alert(message);
}

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

/* insert this code into wordpress  template/js/heurist_messages.js

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
                message = 'You ("' + event.origin + '") are not worthy';
            } else {
                message = 'I got "' + event.data + '" from "' + event.origin + '"';
                
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
                dv.html(jQuery("<button>Import this Record type to my Heurist Database</button>").click(function(){sendMessage("add:"+rectypeid);} )).show();
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

insert this code into wordpress  template/functions.php


function my_custom_js() {
    wp_enqueue_script( 'script-name', get_template_directory_uri() . '/js/heurist_messages.js', array(), '1.0.0', false );
}

//add_action('wp_head', 'my_custom_js');
add_action( 'wp_enqueue_scripts', 'my_custom_js' );

*/