<?php

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
* standalone image viewer with annonatations
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
* @subpackage  Records/Util
*/


require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
require_once(dirname(__FILE__)."/uploadFile.php");

mysql_connection_select(DATABASE);

if (!@$_REQUEST['ulf_ID'] && !@$_REQUEST['rec_ID']) return; // nothing returned if no ulf_ID parameter

if (@$_REQUEST['ulf_ID']){
    
	$filedata = get_uploaded_file_info_internal($_REQUEST['ulf_ID'], false);
	if($filedata==null) return; // nothing returned if parameter does not match one and only one row

    if(@$_REQUEST['origin']=='recview' && $filedata['remoteSource']=='heurist' && $filedata["mediaType"]=='image'){
        //return resizeImage for this origin
        $filedata['URL'] =
                    HEURIST_BASE_URL."common/php/resizeImage.php?maxw=640&maxh=640&".
                    "file_url=".$filedata['URL'].'&'
                    .(defined('HEURIST_DBNAME') ? "db=".HEURIST_DBNAME : "" );
    }


	$url = $filedata['URL'];
    
	$url = str_replace("\\","\\\\",$url);
	$recID = get_uploaded_file_recordid($filedata['id'], false);

}else{
	$recID = $_REQUEST['rec_ID'];
	$ulf_id = get_uploaded_fileid_by_recid($recID);
	$filedata = get_uploaded_file_info_internal($ulf_id, false);
	if($filedata==null) return; // nothing returned if parameter does not match one and only one row

	$url = $filedata['URL'];
}

	if (array_key_exists('width',$_REQUEST)){
		$width =  $_REQUEST['width'];
	}else{
		$width =  '100%';
	}
	if (array_key_exists('height',$_REQUEST)){
		$height =  $_REQUEST['height'];
	}else{
		$height =  '100%';
	}

	$size = 'style="max-width:'.$width.'; max-height:'.$height.';"';
?>
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<title></title>

<!-- link href="../../external/jquery/jquery-ui-1.8.css" rel="stylesheet" type="text/css"/ -->
<script type="text/javascript" src="../../external/js/simple_js_viewer/script/core/Simple_Viewer_beta_1.1.js"></script>
<script type="text/javascript" src="../../external/js/simple_js_viewer/script/extension/toolbar-ext.js"></script>
<link rel="stylesheet" type="text/css" href="../../external/js/simple_js_viewer/script/extension/toolbar-ext.css" />

<link rel=stylesheet href="../../common/css/global.css">
<script>
            //find heurist object in parent windows or init new one if current window is a top most
            function _detectHeurist( win ){
                if(win.HEURIST4){ //defined
                    return win;
                }

                try{
                    win.parent.document;
                }catch(e){
                    // not accessible - this is cross domain
                    return win;
                }    
                if (win.top == win.self) { 
                    //we are in frame and this is top most window and Heurist is not defined 
                    //lets current window will be heurist window
                    return window;
                }else{
                    return _detectHeurist( win.parent );
                }
            }
            //detect wether this window is top most or inside frame
            window.hWin = _detectHeurist(window);
</script>

</head>

<body class="popup" onresize="onResize()">

<script>

	function onResize(){

		var ele = document.getElementById("image_digitizer_container");
		if(ele!=null){
			var elep = document.getElementById("mediaviewer");
			if (this.innerWidth<590) {
				if(ele.style.height!='44px'){
					ele.style.height = '44px';
					elep.style.bottom = '44px';
				}
			}else{
				if(ele.style.height!='22px'){
					ele.style.height = '22px';
					elep.style.bottom = '22px';
				}
			}
		}
	}

	//find db parameter (similar in utilsLoad)
	function parseParams(paramString) {
		if (! paramString) {
			paramString = window.location.search;
		}
		if (paramString.charAt(0) == '?') {
			paramString = paramString.substring(1);
		}

		paramString = paramString.replace(/[+]/g, ' ');	// frustratingly, decodeURIComponent does not decode '+' to ' '

		var parmBits = paramString.split('&');
		var parms = {};
		for (var i=0; i < parmBits.length; ++i) {
			var equalPos = parmBits[i].indexOf('=');
			var parmName = decodeURIComponent(parmBits[i].substring(0, equalPos));
			if (equalPos >= 0) {
				parms[parmName] = decodeURIComponent(parmBits[i].substring(equalPos+1));
			} else {
				parms[parmName] = null;
			}
		}

		return parms;
	}

	var params = parseParams();
	if(!params['db']){
		params['db'] = '';
	}

	var HRST = {
		VERSION: "<?=HEURIST_VERSION?>",
		parameters: params,
		baseURL: "<?=HEURIST_BASE_URL?>",
		iconBaseURL: "<?=HEURIST_ICON_URL?>",
		external_publish: true,
		database: {
			name: params['db']
			},
		util:{
			isnull: function(obj){
				return ( (typeof obj==="undefined") || (obj===null));
			},
			isempty: function(obj){
				return ( HRST.util.isnull(obj) || (obj==="") || (obj==="null") );
			},


	sendRequest: function(url,callback,postData) {
		// if we don't have a fully formed or root URL then prepend the base path
		if ( !(url.match(/^http:/) || url.match(/^https:/))  &&  ! url.match(/^\//))
			url = HRST.baseURL + url;
		var file = url;
		var req = HRST.util.createXMLHTTPObject();
		if (!req) return;
		var method = (postData) ? "POST" : "GET";
		req.open(method,url,true);// set for asynch call
		if (postData)
			req.setRequestHeader('Content-type','application/x-www-form-urlencoded');
			req.onreadystatechange = function () {// callback for ajax object
				if (req.readyState != 4) return;
				if (req.status != 200 && req.status != 304) {
					if (req.status == 404) {
						alert('H-Util HTTP error file not found' + req.status + " " +file);
					}else if (req.status){
						alert('H-Util HTTP error ' + req.status);
					}
					return;
				}
				callback(req);
			}
		if (req.readyState == 4) return;
		req.send(postData);
	},

	XMLHttpFactories: [
		function () {return new XMLHttpRequest()},
		function () {return new ActiveXObject("Msxml2.XMLHTTP")},
		function () {return new ActiveXObject("Msxml3.XMLHTTP")},
		function () {return new ActiveXObject("Microsoft.XMLHTTP")}
	],

	createXMLHTTPObject: function() {
		var xmlhttp = false;
		for (var i=0;i<HRST.util.XMLHttpFactories.length;i++) {
			try {
				xmlhttp = HRST.util.XMLHttpFactories[i]();
			}
			catch (e) {
				continue;
			}
			break;
		}
		return xmlhttp;
	},

	xhrFormSubmit: function(form, callback) {
	// submit a form via XMLHttpRequest;
	// call the callback with the response text when it is done
		var postData = "";
		for (var i=0; i < form.elements.length; ++i) {
			var elt = form.elements[i];

			// skip over un-selected options
			if ((elt.type == "checkbox"  ||  elt.type == "radio")  &&  ! elt.checked) continue;

			// FIXME: deal with select-multiple at some stage   (perhaps we should use | to separate values)
			// place form data into a stream of name = value pairs
			if (elt.strTemporal && (elt.strTemporal.search(/\|VER/) != -1)) elt.value = elt.strTemporal;	// saw fix to capture simple date temporals.
			if (postData) postData += "&";
			postData += encodeURIComponent(elt.name) + "=" + encodeURIComponent(elt.value);
		}

		HRST.util.sendRequest((form.getAttribute && form.getAttribute("jsonAction")) || form.action, callback, postData);
	},

	evalJson: function() {
		// Note that we use a different regexp from RFC 4627 --
		// the only variables available now to malicious JSON are those made up of the characters "e" and "E".
		// EEEEEEEEEEEEEEEEEEeeeeeeeeeeeeeeeeeEEEEEEEEEEEEEEEEEEEeEEEEEEEEEE
		var re1 = /"(\\.|[^"\\])*"|true|false|null/g;
		var re2 = /[^,:{}\[\]0-9.\-+Ee \n\r\t]/;
		return function(testString) {
			return ! re2.test(testString.replace(re1, " "))  &&  eval("(" + testString + ")");
		};
	}(),

	getJsonData: function(url, callback, postData) {
		HRST.util.sendRequest(url, function(xhr) {

			var obj = HRST.util.evalJson(xhr.responseText);
			if(!obj) {
				alert("Unknow error");
			}else if (obj.error) {
				alert(obj.error);
				obj = null;
			}else if(obj.errors && obj.errors.length>0){
				var rep = obj.errors.join(" ");
				alert(rep);
				obj = null;
			}
			if (callback) callback(obj);
		}, postData);
	},


		} //end util
	};
    
    try{
        top.document;
        if (!top.HEURIST) top.HEURIST = HRST;
    }catch(e){
        window.HEURIST = HRST;
    }
    
    
</script>

<script type="text/javascript" src="../../records/files/initViewer.js"></script>

<?php
if(@$_REQUEST['db']){
if(false && @$_REQUEST['annedit']=='yes'){
	 echo '<div id="mediaviewer" style="position:absolute;top:0;bottom:22px;left:0px;right:0px;"></div>';
?>
<div id="image_digitizer_container" style="text-align:center;position:absolute;height:22px;bottom:0px;left:0px;right:0px;"></div>
<?php
}else{
	 echo '<div id="mediaviewer"></div>';
}
?>
<script type="text/javascript">
	showViewer(document.getElementById('mediaviewer'), "<?=$url?>", <?=$recID?>, 'image');
	onResize();
</script>

<?php
}else{
echo '<div style="margin: auto;position: absolute;bottom: 0;left: 0;top: 0;right: 0;">DATABASE IS NOT DEFINED</div>';
}
?>
</body>
</html>
