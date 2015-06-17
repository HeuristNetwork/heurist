if (!top.HEURIST4){
    top.HEURIST4 = {};
}
if (! top.HEURIST4.ajax) top.HEURIST4.ajax = {

/**
* sendRequest
*  XMLHttpRequest stuff from www.quirksmode.org modified for HEURIST
*  that handles the asynchronous service call
*  on completion it calls the callback with the request object
* @author www.quirksmode.org XMLHttpRequest stuff modified for HEURIST
* @author Tom Murtagh
* @author Kim Jackson
* @param url Fully form, root or heurist relative URL to the server application that will service this data
* @param callback a function that will be passed the req object from the HTTPrequest application
* @param postData data that will be sent to the service application
*/
    sendRequest: function(url,callback,postData) {
        // if we don't have a fully formed or root URL then prepend the base path
        if (! url.match(/^http:/)  &&  ! url.match(/^\//))
            url = top.HAPI4.basePath + url;
        var file = url;
        var req = top.HEURIST4.ajax.createXMLHTTPObject();
        if (!req) return;
        var method = (postData) ? "POST" : "GET";
        req.open(method,url,true);// set for asynch call
        if (postData){
            req.setRequestHeader('Content-type','application/x-www-form-urlencoded');
        }
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

/**
* create XMLHttp obj for request
*
* @returns {Object}
*/
    createXMLHTTPObject: function() {
        var xmlhttp = false;
        for (var i=0;i<top.HEURIST4.ajax.XMLHttpFactories.length;i++) {
            try {
                xmlhttp = top.HEURIST4.ajax.XMLHttpFactories[i]();
                xmlhttp = top.HEURIST4.ajax.XMLHttpFactories[i]();
            }
            catch (e) {
                continue;
            }
            break;
        }
        return xmlhttp;
    },
/**
* put your comment there...
*
* @param form
* @param callback
*/
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
            if (elt.strTemporal && (elt.strTemporal.search(/\|VER/) != -1)) elt.value = elt.strTemporal;    // saw fix to capture simple date temporals.
            if (postData) postData += "&";
            postData += encodeURIComponent(elt.name) + "=" + encodeURIComponent(elt.value);
        }

        top.HEURIST4.ajax.sendRequest((form.getAttribute && form.getAttribute("jsonAction")) || form.action, callback, postData);
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
/**
* thin wrapper for sendRequest that translates the results into a JSon object and calls teh callback function
*
* @param url
* @param callback
* @param postData
*/
    getJsonData: function(url, callback, request) {
        
        var postData = "";
        for (var paramName in request){
             postData = postData+paramName+"="+encodeURIComponent(request[paramName])+"&";
        }
        
        top.HEURIST4.ajax.sendRequest(url, function(xhr) {
            
                var obj;
                if(xhr.responseText==""){
                    obj = {status:top.HAPI4.ResponseStatus.UNKNOWN_ERROR, 
                            message: "No response from server at "+url+", please try again later" };
                }else{
                    
                    try {
                        obj = $.parseJSON(xhr.responseText);
                    }
                    catch (err) {
                        obj = {status:top.HAPI4.ResponseStatus.UNKNOWN_ERROR, 
                                message: "Server at "+url+" responded with incorrectly structured data. Excerpt of data: "+xhr.responseText.substring(0,250)};
                    }
                    
                    /*
                    var obj = top.HEURIST4.ajax.evalJson(xhr.responseText);
                    if(!obj) {
                        obj = {status:top.HAPI4.ResponseStatus.UNKNOWN_ERROR, 
                                message: "Server at "+url+" responded with incorrectly structured data. Excerpt of data: "+xhr.responseText.substring(0,250)};
                    }*/
                }

                if (callback) callback(obj);

            }, postData);
    }
    
}
