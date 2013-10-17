utils = {

	parseParams: function(paramString) {
		if (!paramString) {
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
	},

	/**
	*
	*/
	isnull: function(obj){
		return ( (typeof obj==="undefined") || (obj===null));
	},
	/**
	*
	*/
	isempty: function(obj){
		return ( utils.isnull(obj) || (obj==="") || (obj==="null") );
	},

	getcontent: function(url, isxml){

		//load from HEURIST
		var xmlhttp;
		if (window.XMLHttpRequest)
		{// code for IE7+, Firefox, Chrome, Opera, Safari
			xmlhttp=new XMLHttpRequest();
		}
		else
		{// code for IE6, IE5
			xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
		}
		xmlhttp.open("GET", url, false);  //synchronous
		//async xmlhttp.onload = function(e) {  var arraybuffer = xhr.response; }
		xmlhttp.send();

		/* loads from local storage
		if (window.DOMParser)
		{
			parser=new DOMParser();
			xmlDoc=parser.parseFromString(txt,"text/xml");
		}
		else // Internet Explorer
		{
			xmlDoc=new ActiveXObject("Microsoft.XMLDOM");
			xmlDoc.async=false;
			xmlDoc.loadXML(txt);
		}
		*/

		return (isxml) ?xmlhttp.responseXML :xmlhttp.responseText;
	},

	getnode: function (name, iscontent, parent){

				var node = parent.getElementsByTagName(name);
				if(node && node.length>0){
					if(iscontent){
						return node[0].textContent;
					}else{
						return node[0];
					}
				}else{
					return null;
				}
	},
    
    getnode_bool:function (name, parent, def){
        var s = utils.getnode(name, true, parent);
        if(!utils.isempty(s)){
            return (s=="true");
        }else{
            return def;
        }
    },

    getnode_style:function (name, parent){
        var st = utils.getnode(name, false, parent);
        
        if(utils.isempty(st)){
            return null;
        }else{
        
            var style = {};
            
            style.strokeColor = utils.getnode("strokeColor", true, st);
            style.strokeOpacity = utils.getnode("strokeOpacity", true, st);
            style.strokeWidth = utils.getnode("strokeWidth", true, st);
            style.strokeWeight = utils.getnode("strokeWeight", true, st);
            
            style.fillOpacity = utils.getnode("fillOpacity", true, st);
            style.fillColor = utils.getnode("fillColor", true, st);
            
            style.graphicName = utils.getnode("graphicName", true, st);
            style.pointRadius = utils.getnode("pointRadius", true, st);
            style.rotation = utils.getnode("rotation", true, st);
            style.strokeLinecap = utils.getnode("strokeLinecap", true, st);
            
            return style;
        }
    },    
                    
	checkNetworkStatus: function(callback)
	{
		if (navigator.onLine) {
			// Just because the browser says we're online doesn't mean we're online. The browser lies.
			// Check to see if we are really online by making a call for a static JSON resource on
			// the originating Web site. If we can get to it, we're online. If not, assume we're
			// offline.
			$.ajaxSetup({
				async: false, //true,
				cache: false,
				context: $("#status"),
				dataType: "json",
				error: function (req, status, ex) {
					console.log("Error: " + ex);
					// We might not be technically "offline" if the error is not a timeout, but
					// otherwise we're getting some sort of error when we shouldn't, so we're
					// going to treat it as if we're offline.
					// Note: This might not be totally correct if the error is because the
					// manifest is ill-formed.
					utils.isOnline = false;
                    callback.call(utils);
				},
				success: function (data, status, req) {
					utils.isOnline = (data !== null);
                    callback.call(utils);
				},
				timeout: 500,
				type: "GET",
				url: "ping.js"
			});
			$.ajax();
		}
		else {
			utils.isOnline = false;
            callback.call(this);
		}
	},

	useCacheIfOnline: true,
	isOnline: true,
    
    getCurrentPosition: function(callback){
        //position.coords.latitude, position.coords.longitude
        /*
        if (navigator.geolocation)
        {
            navigator.geolocation.getCurrentPosition(callback);
        }else{
            //browser does not support geolocation
            callback.call(this, null);
        }*/
             function errorCallback() {
                  callback.call(this, null);
             }
        
        
            var geolocation = navigator.geolocation;

            if (geolocation) {
                // We have a real geolocation service.
                try {
                  geolocation.watchPosition(callback, errorCallback, {
                    enableHighAccuracy: true,
                    maximumAge: 5000 // 5 sec.
                  });
                } catch (err) {
                    //browser does not support geolocation
                    errorCallback();
                }
            } else {
                //browser does not support geolocation
                errorCallback();
            }        
        
    },

	// localStorage with image
	loadImg: function(imgid, url)
	{
		var ele = document.getElementById(imgid);

		if(utils.isempty(url)){
			ele.setAttribute("src", "about:blank");
		}else if (!window.localStorage || (!utils.useCacheIfOnline && utils.isOnline) ) { //Local storage not supported or usecache is false
			ele.setAttribute("src", url);
			return;
		}

		var storageFile = localStorage.getItem("img_"+imgid);
			/*JSON.parse(localStorage.getItem("storage"+imgid)) || {},
			storageFilesDate = storageFiles.date,
			date = new Date(),
			todaysDate = (date.getMonth() + 1).toString() + date.getDate().toString();*/

		// Compare date and create localStorage if it's not existing/too old
		//(typeof storageFilesDate === "undefined" || storageFilesDate < todaysDate)
		if (!storageFile)  {

			if(utils.isOnline)
			{
			// Take action when the image has loaded
			ele.addEventListener("load", function () {
				var imgCanvas = document.createElement("canvas"),
					imgContext = imgCanvas.getContext("2d");

				// Make sure canvas is as big as the picture
				imgCanvas.width = ele.width;
				imgCanvas.height = ele.height;

				// Draw image into canvas element
				imgContext.drawImage(ele, 0, 0, ele.width, ele.height);

				// Save image as a data URL
				//temp storageFiles.image = imgCanvas.toDataURL("image/png");

				// Set date for localStorage
				//storageFiles.date = todaysDate;

				// Save as JSON in localStorage
				try {
					localStorage.setItem("img_"+imgid, imgCanvas.toDataURL("image/png")); //JSON.stringify(storageFiles));
				}
				catch (e) {
                    // local storage full or CORS violation
                    var reason = e.name || e.message;
                    if (reason && this.quotaRegEx.test(reason)) {
                    	console.log("Storage failed: cache full");
                        //this.events.triggerEvent("cachefull", {tile: tile});
                    } else {
                    	console.log("Storage failed: " + e.toString());
                        //OpenLayers.Console.error(e.toString());
                    }

				}

				//imgCanvas = null;
			}, false);

				// Set initial image src
				ele.setAttribute("src", url);
			}else{
				ele.setAttribute("src", "about:blank"); //replace to some empty image with message
			}
		}
		else {
			// Use image from localStorage
			//ele.setAttribute("src", storageFile);
			ele.src = storageFile;
		}
	},
 
    getBasePath: function()
    {
                if(top.location.protocol=='file:'){
                    return "http://heuristscholar.org/h3-ao/";
                }else{
                    var installDir = top.location.pathname.replace(/(((\?|admin|applications|common|export|import|records|search|viewers)\/.*)|(index.*))/, "");
                    return top.location.protocol + '//'+top.location.host + installDir;
                }
   }

}