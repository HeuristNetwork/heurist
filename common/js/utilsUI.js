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
* Useful functions/vars for Heurist pages -- autosizing, popups, drag/drop wrappers etc
* These are not in utilsLoad.js because they can be loaded asynchronously -- only post-load
* stuff should be put in here.
* Included automatically by heurist.js
* functions are installed as  top.HEURIST.util.*
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
* @subpackage  Common 
*/

window.hWin = detectHeurist(window);

if (!top.HEURIST){
    top.HEURIST = {};
}

    /**
     * Heurist Utility Namespace
     * @namespace
     * @type object
     */
if (! top.HEURIST.util) top.HEURIST.util = {

    setVersion: function() {
        var e = document.getElementById("version");
        if (e) {
            e.innerHTML = top.HEURIST.VERSION;
        }
    },

    setDBName: function() {
        var e = document.getElementById("dbname");
        if (e) {
            e.innerHTML = top.HEURIST.database.name;
        }
    },

    setDocumentTitle: function() {
        if(top.HEURIST.database.name){
            document.title = top.HEURIST.database.name+" [Heurist Vsn "+ top.HEURIST.VERSION +"]";
        }else{
            document.title = "HEURIST " + top.HEURIST.VERSION;
        }
    },

    /* see README.style for comments on the autosize functions */
/**
* put your comment there...
*
* @param elt
*/
    autosizeElement: function(elt) {
        /* Set the size of the given element to that of its parent
        *
        * Opera has a special braindeadness: the parent can't be relied upon to expand
        * to its parent's size, so we have to go directly to the grandparent
        */

        var width = (elt.parentNode.offsetWidth > 0)? elt.parentNode.offsetWidth : (elt.parentNode.parentNode.offsetWidth - 10);
        var height = (elt.parentNode.offsetHeight > 0)? elt.parentNode.offsetHeight : (elt.parentNode.parentNode.offsetHeight - 10);

        elt.style.width = width + "px";
        elt.style.minWidth = width + "px";	// firefox occasionally ignores the width/height setting!
        elt.style.height = height + "px";
        elt.style.minHeight = height + "px";
    },
/**
* Set the size of the given container to that of its contained element with flags for height only or width only
*
* @param elt
* @param cont
* @param {String} flag
*/
    autosizeContainer2: function(elt, cont, flag ) {
        var width = (elt.scrollWidth > 0) ? elt.parentNode.offsetWidth :  (elt.parentNode.parentNode.offsetWidth - 10);
        var height = (elt.parentNode.offsetHeight > 0) ? elt.parentNode.offsetHeight : (elt.parentNode.parentNode.offsetHeight - 10);
        if (!flag) flag = "both";
        switch (flag){
            case "height":
                cont.style.height = height + "px";
                cont.style.minHeight = height + "px";
                break;
            case  "width":
                cont.style.width = width + "px";
                cont.style.minWidth = width + "px";    // firefox occasionally ignores the width/height setting!
                break;
            case "both":
            default:
                cont.style.width = width + "px";
                cont.style.minWidth = width + "px";    // firefox occasionally ignores the width/height setting!
                cont.style.height = height + "px";
                cont.style.minHeight = height + "px";
        }
    },
/**
* autosize all autosize elements in document
*
* @param e
*/
    autosizeAllElements: function(e) {
        // get the document from the event
        var targ = null;
        if (! e) e = window.event;
        if (e) {
            if (e.target) targ = e.target;
            else if (e.srcElement) targ = e.srcElement;
            if (targ  &&  targ.nodeType != 9) {
                targ = null;
            }
        }
        if (! targ) targ = top.document;

        /* automatically resize all DIV elements marked with classname "autosize" */
        var allDivs = targ.getElementsByTagName("div");
        var re = new RegExp("(?:^|\\s+)autosize(?:\\s+|$)");
        for (var i = 0; i < allDivs.length; ++i) {
            if (re.test(allDivs[i].className)) {
                top.HEURIST.util.autosizeElement.call(this, allDivs[i]);
            }
        }
    },
/**
* container for managing current popups
*
* @type Object
*/
    popups: { list: [] },
    
    
/**
* Make a new Heurist-managed popup window.
* parentWindow is the frame that the window should think opened it,
* options is an object, you may supply:
*   url:        "url"   ... open an iframe in the popup window
*   element: ID or element ... a DOM element or element ID to be displayed in the popup
*   callback:   function() { }   ... a function to call when the popup is closed
*   "no-close": true        ... do not present a close button to the user
*   "no-resize": true       ... do not allow the user to resize the window
*   "no-titlebar": true     ... hide the titlebar entirely
*   "close-on-blur": true   ... close the window if the user clicks outside it
*   width / height          ... pixel sizes for the width and height of the window
*   x / y                   ... pixel positions left and top for the window (resp. right and bottom if they are negative)
*   onpopupload: finction() .. a function to call on popup load complete
*
* @param parentWindow
* @param {Object} options
*
* @returns {Object}
*/
    popupWindow: function(parentWindow, options) {
        if (! options) options = {};

        var url = options["url"]? options["url"] : "popup";
        var newHeuristID = top.HEURIST.createHeuristWindowID(url);
        var newPopup = {
            "id": newHeuristID,
            "opener": parentWindow,
            "closeCallback": options["callback"],
            "visible": false
        };

        top.HEURIST.util.popups[newHeuristID] = newPopup;
        top.HEURIST.util.popups.list.push(newPopup);

        newPopup.closeOnBlur = options["close-on-blur"]? true: false;
        top.HEURIST.util.startLoadingPopup();

        var positionDiv = newPopup.positionDiv = top.document.createElement("div");
        positionDiv.className = "popup";
        positionDiv.expando = true;
        var titleDiv = positionDiv.titleDiv = positionDiv.appendChild(top.document.createElement("div"));
        titleDiv.className = "header";

        if (! options["no-close"]) {
            var closeDiv = titleDiv.appendChild(top.document.createElement("div"));
            closeDiv.className = "close-button";
            closeDiv.id = "close";
            closeDiv.title = "Close this window";
        }
        if (! options["no-help"]) {
            var helpDiv = titleDiv.appendChild(top.document.createElement("div"));
            helpDiv.className = "help-button";
            helpDiv.id = "help";
        }
        if (options["title"]) {
            titleSpan = top.document.createElement("span");
            titleSpan.id = "titleSpan";
            titleSpan.innerHTML = (options["title"]);
            titleDiv.appendChild(titleSpan);
        }

        // Put the content-iframe as 100%x100% inside a DIV; we then resize the DIV.
        if (! options["no-resize"]) {
            // This way we can make the iframe display=none while dragging
            var resizeDiv = positionDiv.appendChild(top.document.createElement("div"));
            resizeDiv.className = "resize-handle";
        } else {
            positionDiv.style.backgroundImage = 'none';	// get rid of resize handle indicator
        }

        var popupBody = positionDiv.popupBody = positionDiv.appendChild(top.document.createElement("div"));
        popupBody.className = "popupBody";

        var width = null, height = null;
        if (options["url"]) {
            titleDiv.appendChild(top.document.createTextNode("Loading ..."));
            var newIframe = newPopup.iframe = top.document.createElement("iframe");
            newIframe.HEURIST_WINDOW_ID = newHeuristID;
            newIframe.frameBorder = 0;
            //newIframe.style.display = "none";
            newIframe.style.visibility = "hidden";

            newIframe.close = function() {
                var newArgs = [ newIframe.HEURIST_WINDOW_ID ];

                for (var i=0; i < arguments.length; ++i) {
                    if(i < arguments.length){
                        newArgs.push(arguments[i]);
                    }
                }
                top.HEURIST.util.closePopup.apply(this, newArgs);
            };

            var topWindowDims = top.HEURIST.util.innerDimensions(window.top);
            if (options["width"]) {
                if (options["width"]>topWindowDims.w-40){
                    options["width"] = topWindowDims.w-40;
                }
                popupBody.style.width = options["width"] + "px";
                width = parseInt(options["width"]);
            }
            if (options["height"]) {
                if (options["height"]>topWindowDims.h-40){
                    options["height"] = topWindowDims.h-40;
                }
                popupBody.style.height = options["height"] + "px";
                height = parseInt(options["height"]);
            }
/**
 * Helper function One time onload to set the position
 * and possibly the width and height of the bodyCell
 *
 * @return {boolean} for custom alert
 */
            var oneTimeOnload = function() {
                try {
                    if (! width  &&  newIframe.contentWindow.document.body.getAttribute("width")) {
                        popupBody.style.width = newIframe.contentWindow.document.body.getAttribute("width")+"px";
                        popupBody.style.minWidth = newIframe.contentWindow.document.body.getAttribute("minwidth")+"px";
                        width = parseInt(popupBody.style.width);
                    }
                } catch (e) { }
                if (! width  &&  popupBody.offsetWidth) {
                    width = popupBody.offsetWidth;
                    popupBody.style.width = width + "px";
                }

                try {
                    if (! height  &&  newIframe.contentWindow.document.body.getAttribute("height")) {
                        popupBody.style.height = newIframe.contentWindow.document.body.getAttribute("height")+"px";
                        popupBody.style.minHeight = newIframe.contentWindow.document.body.getAttribute("minheight")+"px";
                        height = parseInt(popupBody.style.height);
                    }
                } catch (e) { }
                if (! height  &&  popupBody.offsetHeight) {
                    height = popupBody.offsetHeight;
                    popupBody.style.height = height + "px";
                }

                var topWindowDims = top.HEURIST.util.innerDimensions(top);
                if (options["x"] < 0) options["x"] = topWindowDims.w - width + parseInt(options["x"]);
                if (options["y"] < 0) options["y"] = topWindowDims.h - height + parseInt(options["y"]);

                positionDiv.style.zIndex = parseInt(top.HEURIST.util.coverall.style.zIndex) + 1;

                var xy = top.HEURIST.util.suggestedPopupPlacement(width, height);
                positionDiv.style.left = (options["x"]? options["x"] : xy.x) + "px";
                positionDiv.style.top = (options["y"]? options["y"] : xy.y) + "px";
                positionDiv.style.visibility = "visible";

                top.HEURIST.util.finishLoadingPopup();

                newIframe.contentWindow.alert = function(txt) {
                    createCustomAlert(txt,args);
                    return true;
                }

                newIframe.style.visibility = "visible";
                newPopup.visible = true;

                top.HEURIST.fireEvent(newIframe, "heurist-finished-loading-popup");

                top.HEURIST.deregisterEvent(newIframe, "load", oneTimeOnload);
            };
            top.HEURIST.registerEvent(newIframe, "load", oneTimeOnload);
            top.HEURIST.registerEvent(newIframe, "load", function() {
                    // Multi-shot onload -- when(ever) document loads, set the title bar
                    try {
                        titleSpan = top.document.createElement("span");
                        titleSpan.id = "titleSpan";
                        titleSpan.innerHTML = (options["title"])?options["title"]:newIframe.contentWindow.document.title;
                        titleSpan.title = newIframe.contentWindow.document.title;
                        titleDiv.replaceChild(titleSpan, titleDiv.lastChild);
                        newIframe.contentWindow.close = newIframe.close;	// make window.close() do what we expect
                        newIframe.contentWindow.popupOpener = parentWindow;	// make a persistent reference to the popup opener
                        newIframe.contentWindow.HEURIST_WINDOW_ID = newHeuristID;

                        top.HEURIST.util.setHelpDiv(helpDiv,null);

                        if(options["onpopupload"]){
                            options["onpopupload"].apply(parentWindow, [newIframe]);
                        }

                    } catch (e) { }	// might get cross-domain woes
            });

            newPopup.element = newIframe;
            newIframe.src = url;

            if (! options["no-close"]) {
                top.HEURIST.registerEvent(closeDiv, "click", function() { newIframe.close(); });
            }
            if (! options["no-help"]) {
                top.HEURIST.registerEvent(helpDiv, "click", function() { top.HEURIST.util.helpToggler(helpDiv); });
            }

            popupBody.expando = true;
            popupBody.iframe = newIframe;
            positionDiv.expando = true;
            positionDiv.iframe = newIframe;

        } else if (options['element']) {
            var element;
            if (options['element'].substring) {	// element ID, not an element
                element = parentWindow.document.getElementById(options['element']);
            } else {
                element = options['element'];
            }
            // get the element out of its current parent (if it has one)
            if (! options["auto-position"]  &&  element.parentNode) {
                newPopup.originalParentNode = element.parentNode;
                element.parentNode.removeChild(element);
            }

            var topWindowDims = top.HEURIST.util.innerDimensions(window.top);

            if (! options["width"]) {
                if (element.offsetWidth) options["width"] = element.offsetWidth;
                else if (element.style.width) options["width"] = parseInt(element.style.width);
                else options["width"] = 300;
            }
            if (! options["height"]) {
                if (element.offsetHeight) options["height"] = element.offsetHeight;
                else if (element.style.height) options["height"] = parseInt(element.style.height);
                else options["height"] = 200;
            }else if (options["height"]>topWindowDims.h-40){
                options["height"] = topWindowDims.h-40;
            }

            if (options["width"]) { popupBody.style.width = options["width"] + "px"; width = parseInt(options["width"]); }
            if (options["height"]) { popupBody.style.height = options["height"] + "px"; height = parseInt(options["height"]); }

            if (! options["auto-position"]) {
                if (options["x"] < 0) options["x"] = topWindowDims.w - width + parseInt(options["x"]);
                if (options["y"] < 0) options["y"] = topWindowDims.h - height + parseInt(options["y"]);

                var xy = top.HEURIST.util.suggestedPopupPlacement(width, height);
                positionDiv.style.left = (options["x"]? options["x"] : xy.x) + "px";
                positionDiv.style.top = (options["y"]? options["y"] : xy.y) + "px";
            }
            positionDiv.style.visibility = "visible";

            top.HEURIST.util.finishLoadingPopup();
            positionDiv.style.zIndex = parseInt(top.HEURIST.util.coverall.style.zIndex) + 1;

            newPopup.visible = true;

            newPopup.element = element;
            element.style.display = "block";

            if (! options["no-close"]) {
                top.HEURIST.registerEvent(closeDiv, "click", function() { top.HEURIST.util.closePopup(newHeuristID); });
            }

            if (! options["title"]) titleDiv.appendChild(top.document.createTextNode("Heurist"));
        }
        if (options["min-width"]) popupBody.style.minWidth = parseInt(options["min-width"]);
        if (options["min-height"]) popupBody.style.minHeight = parseInt(options["min-height"]);


        if (! options["no-resize"]) {
            top.HEURIST.registerEvent(resizeDiv, "mousedown", function(e) { top.HEURIST.util.startResize(e, popupBody); return false; });
        }
        if (options["no-titlebar"]) {
            // remember to hide the TR containing the titlecell, not just the TD
            titleDiv.style.display = "none";
        }
        if (options["close-on-blur"]) {
            // make a click handler on the coverall to close this window
            top.HEURIST.registerEvent(top.HEURIST.util.coverallDiv, "click",
                function() { top.HEURIST.util.closePopup(newHeuristID); });
        }

        top.HEURIST.registerEvent(titleDiv, "mousedown", function(e) {
                if (e && e.target && (e.target.id == "close" || e.target.id == "help")) return;
                top.HEURIST.util.startMove(e, positionDiv);
        });

        if (! options["auto-position"]) {
            top.document.body.appendChild(positionDiv);
        } else {
            // insert the popup where the element was originally
            newPopup.originalParentNode = element.parentNode;
            element.parentNode.replaceChild(positionDiv, element);
        }

        newPopup.originalElementStyleWidth = newPopup.element.style.width;
        newPopup.originalElementStyleHeight = newPopup.element.style.height;
        newPopup.element.style.width = "100%";
        newPopup.element.style.height = "100%";
        popupBody.appendChild(newPopup.element);

        return newPopup;
    },

   /**
    * convenience wrapper for popup using URL
    *
    * @param parentWindow
    * @param url
    * @param {Object} options
    * @see popupWindow()
    */
    popupURL: function(parentWindow, url, options) {
        
        if(typeof jQuery == "undefined" || !hasH4()){
            
            if (! options) {
                options = { "url": url };
            } else {
                options["url"] = url;
            }

            return top.HEURIST.util.popupWindow(parentWindow, options);
            
        }else{
            return window.hWin.HEURIST4.msg.showDialog(url, options);
        }
    },

   /**
    * Convenience wrapper for popup using element
    *
    * @param parentWindow
    * @param element
    * @param {Object} options
    * @see popupWindow()
    */
    popupElement: function(parentWindow, element, options) {
        if (! options) {
            options = { element: element };
        }else{
            options["element"] = element;
        }
        
        if(typeof jQuery == "undefined" || !hasH4()){
            return top.HEURIST.util.popupWindow(parentWindow, options);
        }else{
            return window.hWin.HEURIST4.msg.showElementAsDialog(options);
        }
    },
    /**
     * Convenience wrapper for popup using element with no chrome, resizing, and close-on-blur
     *
     * @param parentWindow
     * @param element
     * @param {Object} options
     * @see popupWindow()
     */
    popupTinyElement: function(parentWindow, element, options) {
        //

        if (! options) options = { element: element };
        else options["element"] = element;

        if (options["no-titlebar"] === undefined) options["no-titlebar"] = true;
        if (options["close-on-blur"] === undefined) options["close-on-blur"] = true;
        if (options["no-resize"] === undefined) options["no-resize"] = true;

        if(typeof jQuery == "undefined" || !hasH4()){
            top.HEURIST.util.popupWindow(parentWindow, options);
            return null;
        }else{
            return window.hWin.HEURIST4.msg.showElementAsDialog(options);
        }
    },

    coverall: null,
    coverallDiv: null,
/**
* bring the coverall to the front (if it exists)
*
* @param invisible
*/
    bringCoverallToFront: function(invisible) {
        if (! top.HEURIST.util.coverall) {
            // Coverall is actually an IFRAME -- otherwise system-window elements such as dropdowns peek through
            var coverall = top.HEURIST.util.coverall = top.document.createElement('iframe');
            coverall.id = "coverall";
            coverall.frameBorder = 0;
            coverall.style.visibility = "hidden";
            coverall.style.zIndex = 100000;
            top.document.body.appendChild(coverall);

            // There is also a DIV on top of the IFRAME, so that we can display messages (can't write on an iframe)
            var coverallDiv = top.HEURIST.util.coverallDiv = top.document.createElement('div');
            coverallDiv.className = "coverall-div";
            coverallDiv.style.visibility = "hidden";
            coverallDiv.style.zIndex = coverall.style.zIndex;
            top.document.body.appendChild(coverallDiv);
        }

        var coverall = top.HEURIST.util.coverall;
        var popupCount = top.HEURIST.util.popups.list.length;
        coverall.style.zIndex = 100000 + ((popupCount - 1) * 2);

        if (invisible  ||  (popupCount == 1  &&  top.HEURIST.util.popups.list[0].closeOnBlur)) {
            // if it's a single level of popups, and that one is marked to close on blur,
            // then don't display an opaque coverall
            coverall.className = "invisible";
        } else {
            coverall.className = "";

            coverall.style.visibility = "visible";
            top.HEURIST.util.coverallDiv.style.visibility = "visible";
        }

        //coverall.style.visibility = "visible";
        //top.HEURIST.util.coverallDiv.style.visibility = "visible";

        // register an ESCAPE key handler on the document
        top.document.onkeypress = top.HEURIST.util.coverallEscapeHandler;
    },
/**
* clear coverall to shut off modality
*/
    sendCoverallToBack: function() {
        top.HEURIST.util.coverallDiv.style.visibility = "hidden";
        top.HEURIST.util.coverall.style.visibility = "hidden";
        //remove escape handler
        top.document.onkeypress = null;
    },
/**
* handle escape key press when coverall is present for popup
*
* @param e
*
* @returns {Boolean}
*/
    coverallEscapeHandler: function(e) {
        if (! e) e = window.event;
        if (! (e.keyCode == 27  ||  e.which == 27)) {	// not the escape key
            return true;
        }

        // if there is a popup loading, stop that.
        // Otherwise, if there is a popup open, [try to] close it.
        var popupsList = top.HEURIST.util.popups.list;
        if (popupsList.length > 0) {
            top.HEURIST.util.closePopup(popupsList[popupsList.length-1].id);

            e = top.HEURIST.util.stopEvent(e);

            return false;
        }
        return true;
    },
/**
* show loading screen
*
*/
    startLoadingPopup: function() {
        top.HEURIST.util.bringCoverallToFront();
        top.HEURIST.util.coverallDiv.style.cursor = "wait";
        top.HEURIST.util.coverallDiv.style.backgroundImage = "";
    },
/**
* hide loading screen
*
*/
    finishLoadingPopup: function() {
        top.HEURIST.util.coverallDiv.style.backgroundImage = "none";	// get rid of "loading" animation
        top.HEURIST.util.coverallDiv.style.cursor = "default";
    },
/**
* get suggestion for placement of popup
*
* @param {Number} width
* @param {Number} height
*
* @returns {Object}
*/
    suggestedPopupPlacement: function(width, height) {
        var POPUP_OFFSET = 15;

        var prevX, prevY, prevChromeW, prevChromeH;
        var previousPopup;
        if (top.HEURIST.util.popups.list.length > 1) {
            // look for the previous visible popup
            for (var i=top.HEURIST.util.popups.list.length-1; i >= 0; --i) {
                if (top.HEURIST.util.popups.list[i].visible) {
                    previousPopup = top.HEURIST.util.popups.list[i];
                    break;
                }
            }
        }
        if (previousPopup) {
            prevX = parseInt(previousPopup.positionDiv.style.left);	if (prevX < 0) prevX = -1;
            prevY = parseInt(previousPopup.positionDiv.style.top);	if (prevY < 0) prevY = -1;
            if(previousPopup.table && previousPopup.popupBody){
                prevChromeW = previousPopup.table.offsetWidth - previousPopup.popupBody.offsetWidth;
                prevChromeH = previousPopup.table.offsetHeight - previousPopup.popupBody.offsetHeight;
            }else{
                prevChromeW = 6;	// 6px of handles
                prevChromeH = 25;	// titlebar plus handles ... give or take
            }

        } else {
            prevX = -1;
            prevY = -1;
            prevChromeW = 6;	// 6px of handles
            prevChromeH = 25;	// titlebar plus handles ... give or take
        }

        // default popup width and height are 300px x 200px
        if (! width) width = 300;
        if (! height) height = 200;

        var topWindowDims = top.HEURIST.util.innerDimensions(top);

        if ((prevX + POPUP_OFFSET + prevChromeW + width) > topWindowDims.w) {
            // width would go offscreen ... don't do that
            prevX = -1;
        }
        if ((prevY + POPUP_OFFSET + prevChromeH + height) > topWindowDims.h) {
            // height would go offscreen ... don't do that
            prevY = -1;
        }

        var x, y;
        x = Math.max(0, Math.round((topWindowDims.w - (width + prevChromeW)) / 2));
        y = Math.max(0, Math.round((topWindowDims.h - (height + prevChromeH)) / 2));

        if (prevX >= 0  &&  Math.abs(x - prevX) < POPUP_OFFSET)
            x = prevX + POPUP_OFFSET;
        if (prevY >= 0  &&  Math.abs(y - prevY) < POPUP_OFFSET)
            y = prevY + POPUP_OFFSET;

        return { x: x, y: y };
    },
/**
* object for managing drag operation
*
* @type Object
*/
    dragDetails: { dragging: false },
/**
* startDrag
*
* @param evt
* @param updateCallback
* @param endCallback
*
* @returns {Boolean}
*/
    startDrag: function(evt, updateCallback, endCallback) {
        if (! evt) evt = window.event;
        if (! evt.view) {
            evt.expando = true;
            evt.view = evt.srcElement.ownerDocument.parentWindow;
        }

        if (top.HEURIST.util.dragDetails.dragging  &&  top.HEURIST.util.dragDetails.endDrag) {
            top.HEURIST.util.dragDetails.endDrag(evt);
        }

        var dragCaptureElement = evt.view.document.createElement("div");
        dragCaptureElement.style.position = "absolute";
        dragCaptureElement.style.width = "100%";
        dragCaptureElement.style.height = "100%";
        dragCaptureElement.style.top = "0";
        dragCaptureElement.style.left = "0";
        dragCaptureElement.style.zIndex = 999999;
        evt.view.document.body.appendChild(dragCaptureElement);

        var dragDetails = {
            dragging: true,
            xy: top.HEURIST.util.getEventPosition(evt),

            updateCallback: updateCallback,
            endCallback: endCallback,

            dragCaptureElement: dragCaptureElement
        };


        top.HEURIST.registerEvent(evt.view.document, "mousemove", updateCallback);
        top.HEURIST.registerEvent(evt.view.document, "mouseup", top.HEURIST.util.endDrag);

        top.HEURIST.util.dragDetails = dragDetails;

        top.HEURIST.util.stopEvent(evt);

        return false;
    },
/**
* end drag
*
* @param evt
*
* @returns {Boolean}
*/
    endDrag: function(evt) {
        if (! evt) evt = window.event;
        if (! evt.view) {
            evt.expando = true;
            evt.view = evt.srcElement.ownerDocument.parentWindow;
        }

        if (top.HEURIST.util.dragDetails.endCallback) top.HEURIST.util.dragDetails.endCallback(evt);

        var dce = top.HEURIST.util.dragDetails.dragCaptureElement;
        dce.parentNode.removeChild(dce);

        top.HEURIST.deregisterEvent(evt.view.document, "mouseup", top.HEURIST.util.endDrag);
        top.HEURIST.deregisterEvent(evt.view.document, "mousemove", top.HEURIST.util.dragDetails.updateCallback);
        top.HEURIST.util.dragDetails = { dragging: false };

        top.HEURIST.util.stopEvent(evt);

        return false;
    },

    /* element resizing hooks for the drag framework */
/**
* put your comment there...
*
* @param evt
* @param element
* @param reversed
*
* @returns {Boolean}
*/
    startResize: function(evt, element, reversed) {
        if (! evt) evt = window.event;
        if (! evt.view) {
            evt.expando = true;
            evt.view = evt.srcElement.ownerDocument.parentWindow;
        }

        if (element && element.substring) element = evt.view.document.getElementById(element);
        if (! element) return;


        if (element.iframe) {
            // iframes mess up dragging by capturing events themselves ... hide them while resizing
            element.iframe.style.display = "none";
        }

        top.HEURIST.util.startDrag(evt, top.HEURIST.util.dragResizer, top.HEURIST.util.endResize, element);

        top.HEURIST.util.dragDetails.element = element;
        top.HEURIST.util.dragDetails.wh = { w: parseInt(element.style.width), h: parseInt(element.style.height) };
        top.HEURIST.util.dragDetails.reversed = reversed? true : false;

        return false;
    },
/**
* start resize drag
*
* @param e
*
* @returns {Boolean}
*/
    dragResizer: function(e) {
        if (! e) e = window.event;
        var newXY = top.HEURIST.util.getEventPosition(e);
        var resizeDetails = top.HEURIST.util.dragDetails;

        if (! resizeDetails.reversed) {
            var newWidth = (newXY.x - resizeDetails.xy.x) + resizeDetails.wh.w;
            var newHeight = (newXY.y - resizeDetails.xy.y) + resizeDetails.wh.h;
        }
        else {
            var newWidth = (resizeDetails.xy.x - newXY.x) + resizeDetails.wh.w;
            var newHeight = (resizeDetails.xy.y - newXY.y) + resizeDetails.wh.h;
        }

        // semi-sane minima
        var minWidth = parseInt(resizeDetails.element.style.minWidth);
        if (minWidth) { if (newWidth < minWidth) newWidth = minWidth; }
        else if (newWidth < 100) newWidth = 100;

        var minHeight = parseInt(resizeDetails.element.style.minHeight);
        if (minHeight) { if (newHeight < minHeight) newHeight = minHeight; }
        else if (newHeight < 50) newHeight = 50;

        resizeDetails.element.style.width = newWidth + "px";
        resizeDetails.element.style.height = newHeight + "px";

        top.HEURIST.util.stopEvent(e);

        return false;
    },
/**
* end resize drag
*
* @param e
*/
    endResize: function(e) {
        var resizeDetails = top.HEURIST.util.dragDetails;
        if (resizeDetails.element  &&  resizeDetails.element.iframe) {
            resizeDetails.element.iframe.style.display = "block";
        }

        if (resizeDetails.element.isScratchpad) {
            top.HEURIST.util.setDisplayPreference("scratchpad-width", parseInt(resizeDetails.element.style.width));
            top.HEURIST.util.setDisplayPreference("scratchpad-height", parseInt(resizeDetails.element.style.height));
        }
    },

/* element moving hooks for the drag framework */
/**
* start drag move
*
* @param evt
* @param element
* @param reversed
*
* @returns {Boolean}
*/
    startMove: function(evt, element, reversed) {
        if (! evt) evt = window.event;
        if (! evt.view) {
            evt.expando = true;
            evt.view = evt.srcElement.ownerDocument.parentWindow;
        }

        if (element && element.substring) element = evt.view.document.getElementById(element);
        if (! element) return;

        if (element.iframe) {
            // iframes mess up dragging by capturing events themselves ... hide them while moving
            element.iframe.style.display = "none";
        }

        top.HEURIST.util.startDrag(evt, top.HEURIST.util.dragMover, top.HEURIST.util.endMove);

        top.HEURIST.util.dragDetails.element = element;
        if (! reversed) {
            if (element.style.right  &&  ! element.style.left) {	// allow right-offset position
                element.style.left = element.offsetLeft + 'px';
                element.style.right = '';
            }
            if (element.style.bottom  &&  ! element.style.top) {	// allow bottom-offset position
                element.style.top = element.offsetTop + 'px';
                element.style.bottom = '';
            }
            if(parseInt(element.style.left)<0){
                element.style.left = '0px';
            }
            if(parseInt(element.style.top)<0){
                element.style.top = '0px';
            }/*else if (parseInt(element.style.top)+40 > top.document.body.scrollHeight){
            element.style.top = (top.document.body.scrollHeight-40)+'px';
            }*/

            top.HEURIST.util.dragDetails.pos = { left: parseInt(element.style.left), top: parseInt(element.style.top) };
        }
        else {
            top.HEURIST.util.dragDetails.pos = { right: parseInt(element.style.right), bottom: parseInt(element.style.bottom) };
        }
        top.HEURIST.util.dragDetails.reversed = reversed? true : false;

        return false;
    },
/**
* move drag object
*
* @param e
*
* @returns {Boolean}
*/
    dragMover: function(e) {
        if (! e) e = window.event;
        var newXY = top.HEURIST.util.getEventPosition(e);
        var moveDetails = top.HEURIST.util.dragDetails;

        if (! moveDetails.reversed) {
            var newLeft = (newXY.x - moveDetails.xy.x) + moveDetails.pos.left;
            var newTop = (newXY.y - moveDetails.xy.y) + moveDetails.pos.top;

            if(newLeft<0) {
                newLeft = 0;
            }
            if(newTop<0) {
                newTop = 0;;
            }

            moveDetails.element.style.left = newLeft + "px";
            moveDetails.element.style.top = newTop + "px";
        }
        else {
            var newRight = (moveDetails.xy.x - newXY.x) + moveDetails.pos.right;
            var newBottom = (moveDetails.xy.y - newXY.y) + moveDetails.pos.bottom;

            moveDetails.element.style.right = newRight + "px";
            moveDetails.element.style.bottom = newBottom + "px";
        }

        top.HEURIST.util.stopEvent(e);

        return false;
    },
/**
* stop the drag move
*
* @param evt
*/
    endMove: function(evt) {
        var moveDetails = top.HEURIST.util.dragDetails;
        if (moveDetails.element  &&  moveDetails.element.iframe) {
            moveDetails.element.iframe.style.display = "block";
        }

        if (moveDetails.element.isScratchpad) {
            top.HEURIST.util.setDisplayPreference("scratchpad-bottom", parseInt(moveDetails.element.style.bottom));
            top.HEURIST.util.setDisplayPreference("scratchpad-right", parseInt(moveDetails.element.style.right));
        }
    },
/**
* wrapper to close last popup
*
*/
    closePopupLast: function() {
        var pups = top.HEURIST.util.popups;
        if(pups && pups.list.length>0){
            var windowID = pups.list[pups.list.length-1].id;
            top.HEURIST.util.closePopup(windowID);
        }
    },
    closePopupAll: function() {
        var origcnt = top.HEURIST.util.popups.list.length;
        var cnt = 0;
        while (top.HEURIST.util.popups && top.HEURIST.util.popups.list.length>0){
            var pups = top.HEURIST.util.popups;
            var windowID = pups.list[pups.list.length-1].id;
            top.HEURIST.util.closePopup(windowID);
            cnt++;
            if(cnt>origcnt){
                break;
            }
        }
    },
/**
* close popup and cleanup structure
*
* @param windowID
*
* @returns {Boolean}
*/
    closePopup: function(windowID) {
        // Close this popup window (or TRY to)
        // If there is a callback, and it returns falsey, then we shan't close.

        if (! windowID  ||  ! top.HEURIST.util.popups[windowID]) {
            return null;	// not one of ours ... what to do?
        }

        var popup = top.HEURIST.util.popups[windowID];
        if (popup.closeCallback) {
            // invoke the callback, using the window that opened the popup as "this", and any passed parameters
            var callbackParameters = [];
            for (var i=1; i < arguments.length; ++i) callbackParameters.push(arguments[i]);

            var rval = popup.closeCallback.apply(popup.opener, callbackParameters);
            if (! rval  &&  rval !== undefined) return false;	// whoopsy! the opener doesn't want you to close yet
        }

        // Remove the window and clean up
        //popup.positionDiv.style.visibility = "hidden";
        // TFM 2007-08-21 ... why was this visibility: hidden and not display: none?
        // the former leaves some artefacts (inner IFRAME still partially visible for a short while)
        popup.positionDiv.style.display = "none";
        popup.visible = false;

        var popups = top.HEURIST.util.popups;
        delete popups[windowID];

        if (popup === top.HEURIST.util.popups.list[top.HEURIST.util.popups.list.length-1]) {
            // Normal (only?) case ... the popup on top is closing

            popups.list.pop();

            if (popups.list.length > 0) {
                // Bring the next-highest window to the front
                top.HEURIST.util.coverall.style.zIndex = parseInt(popups.list[popups.list.length-1].positionDiv.style.zIndex) - 1;
            } else {
                // All popups are closed ... remove the coverall
                top.HEURIST.util.sendCoverallToBack();
            }

        }
        else {
            // Some background popup is closing ... just a bit of administratia

            for (var i=0; i < popups.list.length; ++i) {
                if (popup === popups.list[i]) {
                    popups.list.splice(i, 1);	// remove one element from popups list
                    break;
                }
            }
        }

        // if the popup element was originally in the document, then return it to its rightful parent
        if (popup.originalParentNode) {
            var element = popup.element.parentNode.removeChild(popup.element);
            element.style.display = "none";
            element.style.width = popup.originalElementStyleWidth;
            element.style.height = popup.originalElementStyleHeight;
            // FIXME: element didn't necessarily belong at the end of the parent ... should have a placeholder or something
            popup.originalParentNode.appendChild(element);
        }


        // have to detach the actual deletion from this thread, otherwise the function may return to a non-existent window!
        setTimeout(function() { popup.positionDiv.parentNode.removeChild(popup.positionDiv); }, 0);

        return true;
    },
/**
* find Heurist window
*
* @param win
*/
    parentWindow: function(win) {
        if (top.HEURIST.util.popups[win.HEURIST_WINDOW_ID])
            return top.HEURIST.util.popups[win.HEURIST_WINDOW_ID].opener;
        return null;
    },
/**
* return the coordinates of the given event relative to the document
*
* @param evt
*
* @returns {Object}
*/
    getEventPosition: function(evt) {
        var x = 0, y = 0;
        if (evt.pageX  ||  evt.pageY) {
            x = evt.pageX;
            y = evt.pageY;
        } else if (evt.clientX  ||  evt.clientY) {
            x = evt.clientX + document.body.scrollLeft + document.documentElement.scrollLeft;
            y = evt.clientY + document.body.scrollTop + document.documentElement.scrollTop;
        }

        return { x: x, y: y };
    },
/**
* return the inner dimensions of a window
*
* @param win
*
* @returns {Object}
*/
    innerDimensions: function(win) {
        var w, h;

        if (win.innerHeight) {
            // all except IE
            w = win.innerWidth;
            h = win.innerHeight;
        } else if (win.document.documentElement  &&  win.document.documentElement.clientHeight) {
            // IE6 strict mode
            w = win.document.documentElement.clientWidth;
            h = win.document.documentElement.clientHeight;
        } else if (win.document.body) {
            // other IE
            w = win.document.body.clientWidth;
            h = win.document.body.clientHeight;
        }
        return { w: w, h: h };
    },

/**
* set display preference in all windows below win; save preferences too
* The third and fourth arguments are for recursion: don't use them externally.
*
* @param prefName
* @param value
* @param win
* @param {Array} replacementRegExps
* @param noframes
* @param noclass
*/
    setDisplayPreference: function(prefName, value, win, replacementRegExps, noframes, noclass) {
        var prefs , vals, i,prefChngs = [], newVals = []
        prefString = "";
        if (typeof prefName == "string"){//single pref variable convert to array for code below
            prefs = [prefName];
            vals = [value];
        }else{
            prefs = prefName;
            vals = value;
        }

        if ( top.HEURIST.util.isnull(win) ) { //SAW ARE there any nested calls????
            // top-level stuff
            replacementRegExps = [];

            if (! top.HEURIST.displayPreferences) {	// hmm ... displayPreferences.php didn't load
                // we can wing it for now
                top.HEURIST.displayPreferences = {};
            }

            for (i=0; i<prefs.length; i++){
                if (top.HEURIST.displayPreferences[prefs[i]] && top.HEURIST.displayPreferences[prefs[i]] == vals[i]) {
                    if (top.document.body.className.search(prefs[i])== -1){//if it's not already in the class list
                        prefChngs.push(prefs[i]);
                        newVals.push(vals[i]);
                        replacementRegExps.push(new RegExp('$'));
                    }else{
                        continue;	// we already have that value
                    }
                }else{
                    prefString += '&' + encodeURIComponent(prefs[i]) + '=' + encodeURIComponent(vals[i]);
                    prefChngs.push(prefs[i]);
                    newVals.push(vals[i]);
                    replacementRegExps.push(new RegExp('(^|\\s+)'+ prefs[i] + '-' + top.HEURIST.displayPreferences[prefs[i]] + '(\\s+|$)|^$'));
                    top.HEURIST.displayPreferences[prefs[i]] = vals[i];
                }
            }
            if (prefString){
                top.HEURIST.loadScript(top.HEURIST.baseURL+'common/php/displayPreferences.php?'+
                    'db='+ (top.HEURIST.database && top.HEURIST.database.name ? top.HEURIST.database.name
                        : top.HEURIST.parameters.db ? top.HEURIST.parameters.db:"")+
                    prefString);
            }
            win = top;
        }

        if (!noclass){
            for (i=0; i<prefs.length; i++){
                if (!(vals[i]+"").match(/\s/)) {//if pref value is not blank
                    if (win.document.body.className.search(prefs[i])== -1){//if it's not already in the class list
                        win.document.body.className = win.document.body.className + ' '+prefs[i]+'-'+vals[i]+' ';
                    }else{
                        win.document.body.className = win.document.body.className.replace(replacementRegExps[i], ' '+prefs[i]+'-'+vals[i]+' ');
                    }
                }
            }
        }

        if (!noframes && prefs.length) {
            for (var i=0; i < win.frames.length; ++i) {
                try {
                    // some frames may be from another domain, e.g. addthis
                    top.HEURIST.util.setDisplayPreference(prefs, vals, win.frames[i], replacementRegExps, false, noclass);
                } catch (e) { }
            }
        }
    },

    getDisplayPreference: function(prefName) {
        return top.HEURIST.displayPreferences[prefName];
    },

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
        if ( !(url.match(/^http:/) || url.match(/^https:/))  &&  ! url.match(/^\//))
            url = top.HEURIST.baseURL + url;
            
        var file = url;
        var req = top.HEURIST.util.createXMLHTTPObject();
        if (!req) return;
        var method = (postData) ? "POST" : "GET";
        req.open(method,url,true);// set for asynch call
        if (postData){
            req.setRequestHeader('Content-type','application/x-www-form-urlencoded');
        }
        req.onreadystatechange = function () {// callback for ajax object
            if (req.readyState != 4) return;
            var msg = '';
            if (req.status != 200 && req.status != 304) {
                
                if (req.status == 404) {
                    msg = 'H-Util HTTP error file not found' + req.status + ' ' +file;
                }else if (req.status){
                    if(req.status==500){
                        msg = 'HTTP error 500 reported (internal server error). '
                                +'This may be due to a hiccup in internet connection. Please retry. '
                    }else{
                        msg = 'HTTP error '+req.status+' reported. ';
                    }
                        msg = msg +'If this error pops up repeatedly, please send a bug report (Help > Bug report) or email the Heurist developers (info at HeuristNetwork dot org) and tell us where it occurs.';
                }
                
                if(msg){
                    if(hasH4()){
                        window.hWin.HEURIST4.msg.showMsgErr(msg);                           
                    }else{
                        top.HEURIST.util.showError(msg);
                    }
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
        for (var i=0;i<top.HEURIST.util.XMLHttpFactories.length;i++) {
            try {
                xmlhttp = top.HEURIST.util.XMLHttpFactories[i]();
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

        top.HEURIST.util.sendRequest((form.getAttribute && form.getAttribute("jsonAction")) || form.action, callback, postData);
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
    getJsonData: function(url, callback, postData) {
        top.HEURIST.util.sendRequest(url, function(xhr) {
            
                if(xhr.responseText==""){
                    top.HEURIST.util.showError("No response from server at "+url+", please try again later");
                    obj = null;
                }else{

                    var obj = top.HEURIST.util.evalJson(xhr.responseText);
                    if(!obj) {
                        top.HEURIST.util.showError("Server at "+url+" responded with incorrectly structured data. Excerpt of data: "+xhr.responseText.substring(0,250));
                    }else if (obj.error) {
                        top.HEURIST.util.showError(obj.error, obj.error_title);

                        obj = null;
                    }else if(obj.errors && obj.errors.length>0){
                        var rep = obj.errors.join(" ");
                        top.HEURIST.util.showError(rep, obj.error_title);

                        obj = null;
                    }
                }

                if (callback) callback(obj);

            }, postData);
    },

    //reload rectype, details and terms for top.HEURIST
    reloadStrcuture: function( is_message ) {

        var _updateHEURIST = function(context){

            if(!context) {
                top.HEURIST.util.showError(-1);
            } else {

                top.HEURIST.rectypes = context.rectypes;
                top.HEURIST.detailTypes = context.detailTypes;
                top.HEURIST.terms = context.terms;

                if(is_message==true){
                    var sMsg = 'Database structure definitions in browser memory have been refreshed. <br/>'+
                            'You may need to reload pages to see changes.';
                    if(hasH4()){
                        window.hWin.HEURIST4.msg.showMsgDlg(sMsg);
                    }else{
                        alert(sMsg);
                    }
                }
            }
        }

        var _db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));
        var baseurl = top.HEURIST.baseURL + "common/php/reloadCommonInfo.php";
        var callback = _updateHEURIST;
        var params = "db="+_db;
        top.HEURIST.util.getJsonData(baseurl, callback, params);

    },
/**
* find a list of tag matches
*
* @param term
*
* @returns {Array}
*/
    tagAutofill: function(term) {
        // FIXME: we can improve this a lot by doing very simple caching

        if (term === "") return [];

        var tags = top.HEURIST.user.tags;

/* Do complicated matching:
* We match the (whitespace-separated) words in the term with the words in the tag.
* If there is just one word in the term this is very easy.
* Note that punctuation is treated as whitespace for the purposes of this experiment
*
* We treat the first word of the term as special, and make tags that start with that word float to the top
*/

        term = term.replace(/[^a-zA-Z0-9]/g, " ");    // remove punctuation and other non-alphanums
        term = term.toLowerCase().replace(/^ +| +$/, "");    // trim whitespace from start and end
        var regexSafeTerm = term.replace(/[\\^.$|()\[\]*+?{}]/g, "\\$0");    // neutralise any special regex characters
        var regex;
        if (regexSafeTerm.indexOf(" ") == -1) {
            // term is a single word ... look for it to start any word in the tag
            regex = new RegExp("(.?)\\b" + regexSafeTerm);
        } else {
            // multiple words: match all of them at the start of words in the tag
/*
* for input
* WORD1 WORD2 WORD3 WORD4 ...
* want output
* (?=.*?\bWORD2)(?=.*?\bWORD3)(?=.*?\bWORD4) ... (^.*?)\bWORD1
* This matches all words in the search term using look-ahead (no matter what order they are in)
* except for the first word, which it prefers to match at the beginning of the tag.
* When it matches at the beginning, $1 is empty: we test this to float it to the top of the results.
*/
            var bits = regexSafeTerm.split(/ /);
            var firstBit = bits.shift();

            regexSafeTerm = "(?=.*?\\b" + bits.join(")(?=.*?\\b") + ")(^.*?)\\b" + firstBit;
            regex = new RegExp(regexSafeTerm);
        }
        var startMatches = [];
        var otherMatches = [];

        for (var i=0; i < tags.length; ++i) {
            var match = tags[i].toLowerCase().match(regex);

            if (match  &&  match[1] == "")
                startMatches.push(tags[i]);
            else if (match)
                otherMatches.push(tags[i]);
        }

        // splice start matches at the beginning of the other matches
        var matches = otherMatches;
        startMatches.unshift(0, 0);
        matches.splice.apply(matches, startMatches);

        return matches;
    },
/**
* present new tag and retrieve confirmation to add from user
*
* @param tag
*
* @returns {Boolean}
*/
    showConfirmNewTag: function(tag) {
        // "this" is the autocomplete object
        var that = this;

        if (this.confirmImg) {
            if (! tag) {
                this.confirmImg.parentNode.removeChild(this.confirmImg);
                this.confirmImg = null;
            }
            return false;
        }
        else if (! tag) return false;

        var start = this.textbox.value.indexOf(this.delimiter + this.currentWordValue + this.delimiter) + 1;
        if (start == 0  &&  this.textbox.value.substring(this.textbox.value.length - this.currentWordValue.length) == this.currentWordValue)
            start = this.textbox.value.length - this.currentWordValue.length;
        if (start < 0) start = 0;
        var end = start + this.currentWordValue.length;

        this.currentWordStart = start;
        this.currentWordEnd = end;


        var confirmImg = this.confirmImg = this.document.createElement("div");
        confirmImg.className = "confirmImg";
        confirmList = confirmImg.appendChild(this.document.createElement("ul"));
        var confirmOption = confirmList.appendChild(this.document.createElement("li"));
        confirmOption.className = "option";
        //            confirmOption.style.top = "2px";
        confirmOption.innerHTML = "<div><img src='"+top.HEURIST.baseURL+"common/images/tick-grey.gif'></div>Confirm New Tag";
        confirmOption.onmousedown = function() { top.HEURIST.util.autocompleteConfirm.call(that); return false; };
        var changeOption = confirmList.appendChild(this.document.createElement("li"));
        changeOption.className = "option";
        //            changeOption.style.top = "14px";
        changeOption.innerHTML = "<div><img src='"+top.HEURIST.baseURL+"common/images/cross.png'></div>Change Tag";
        changeOption.onmousedown = function() { top.HEURIST.util.autocompleteChange.call(that); return false; };


        // take a punt at where the caret is
        var offset = this.textbox.value.indexOf(","+tag+",")+1;    // could be somewhere in the middle ...
        if (offset == 0  &&  this.textbox.value.substring(this.textbox.value.length - tag.length) == tag) {
            offset = this.textbox.value.length - tag.length;    // could be at the end ...
        }
        if (offset < 0) offset = 0;    // otherwise it's at the beginning!


        var approxLeft = Math.round(offset * 5.5);
        if (approxLeft > this.textbox.offsetWidth) approxLeft = this.textbox.offsetWidth;
        else if (approxLeft < 20) approxLeft = 20;

        var tagsPosition = top.HEURIST.getPosition(this.textbox);
        confirmImg.style.left = (tagsPosition.x + approxLeft - 10) + "px";
        confirmImg.style.top = (tagsPosition.y + 20) + "px";

        this.document.body.appendChild(confirmImg);
        this.setTextboxRange(start, end);
        this.freeze();
        if (top.HEURIST.edit) top.HEURIST.edit.preventTabSwitch = true;

        return false;
    },
/**
* Handle confirmation of tag
*
*/
    autocompleteConfirm: function() {
        // (this) is the AutoComplete
        var newTag = this.currentWordValue.replace(/^\s+|\s+$/g, "").replace(/\s+/g, " ");
        top.HEURIST.user.tags.push(newTag);

        this.confirmImg.parentNode.removeChild(this.confirmImg);
        this.confirmImg = null;

        if (top.HEURIST.edit) top.HEURIST.edit.preventTabSwitch = false;
        this.unfreeze();

        // move caret to end of text
        if (this.textbox.createTextRange) {
            var range = this.textbox.createTextRange();
            range.collapse(false);
            range.select();
        } else if (this.textbox.setSelectionRange) {
            this.textbox.focus();
            var length = this.textbox.value.length;
            this.textbox.setSelectionRange(length, length);
        }
        this.currentWord = -1;
        this.currentWordValue = null;
    },
/**
* Handle change response for tag
*
*/
    autocompleteChange: function() {
        this.confirmImg.parentNode.removeChild(this.confirmImg);
        this.confirmImg = null;

        if (top.HEURIST.edit) top.HEURIST.edit.preventTabSwitch = false;
        this.unfreeze();

        if (this.currentWordEnd === this.textbox.value.length-1  &&  this.textbox.value.charAt(this.currentWordEnd) === ",") {
            this.textbox.value = this.textbox.value.substring(0, this.textbox.value.length - 1);
        }
        this.textbox.focus();
        this.setTextboxRange(this.currentWordEnd, this.currentWordEnd);
    },
/**
*
* @deprecated
*/
    autocompleteRemove: function() {
        this.confirmImg.parentNode.removeChild(this.confirmImg);
        this.confirmImg = null;

        var start = this.currentWordStart;
        var end = this.currentWordEnd;
        if (this.textbox.value.substring(end, end+this.delimiter.length) == this.delimiter) {
            end += this.delimiter.length;
        }

        this.textbox.value = this.textbox.value.substring(0, start) + this.textbox.value.substring(end);

        if (top.HEURIST.edit) top.HEURIST.edit.preventTabSwitch = false;
        this.unfreeze();

        this.textbox.focus();
        this.setTextboxRange(this.textbox.value.length, this.textbox.value.length);
        this.currentWord = -1;
        this.currentWordValue = null;
    },
/**
* lookup possible matches for term
*
* @param term
*
* @returns {Array}
*/
    userAutofill: function(term) {
        if (term === "") return [];

        var users = top.HEURIST.allUsers;

        term = term.toLowerCase().replace(/\s+/g, " ");
        var cleanTerm = term.replace(/[^a-z0-9]/g, " ");
        cleanTerm = cleanTerm.replace(/^ +| +$/, "");
        if (! cleanTerm) return [];

        var regex = new RegExp('(?=.*' + cleanTerm.split(/ +/).join(')(?=.*') + ')');

        var reallyGoodMatches = [];
        var otherMatches = [];
        for (var i in users) {
            var match = users[i][0].toLowerCase().match(regex)  ||  users[i][1].toLowerCase().match(regex);
            if (! match) continue;

            if (users[i][0].indexOf(term) >= 0  ||  users[i][0].indexOf(term) >= 0)//why are there 2???
                reallyGoodMatches.push(users[i][1]);
            else
                otherMatches.push(users[i][1]);
        }

        reallyGoodMatches.unshift(0);
        reallyGoodMatches.unshift(0);
        otherMatches.splice.apply(otherMatches, reallyGoodMatches);
        return otherMatches;
    },
/**
* set the label for help div, save preference and update class for body element
*
* @param helpDiv
* @param helpStatus
*/
    setHelpDiv: function(helpDiv, helpStatus){

        if (!helpStatus) {
            helpStatus = (top.HEURIST.displayPreferences && top.HEURIST.util.getDisplayPreference("help"))?top.HEURIST.util.getDisplayPreference("help"):"show";
        }

        if(!top.HEURIST.util.isnull(helpDiv))
        {
            var alts_2 = { "hide": "Click here to show help text", "show": "Click here to hide help text" };
            var alts_1 = { "hide": "Show Help", "show": "Hide Help"};

            if($(helpDiv).attr('type') == 'checkbox'){
                
                $(helpDiv).attr('checked', (helpStatus=="show"));
                $(helpDiv).attr('title', alts_2[helpStatus]);
            }else{
                helpDiv.title = alts_2[helpStatus];
                helpDiv.innerHTML = alts_1[helpStatus];
            }
        }

        //init class for body element
        top.HEURIST.util.setDisplayPreference("help", helpStatus);
        
        return helpStatus; 
    },
/**
* toggle help event Handler
*
* @param helpDiv
*/
    helpToggler: function(helpDiv) {
        var helpStatus = top.HEURIST.util.getDisplayPreference("help");

        if (helpStatus === "hide") {
            helpStatus =  "show";
        }else {
            helpStatus =  "hide";
        }
        top.HEURIST.util.setHelpDiv(helpDiv,helpStatus);
    },

    countObjElements: function(obj) {
        var i =0;
        for ( var j in obj) i++;
        return i;
    },


/**
* helper function. utilized in recreateTermsPreviewSelector only
* converts json string to array
*
* @param jsonString
*
* @returns {String}
*/
    expandJsonStructure: function ( jsonString ) {
        var retStruct = "";
        if(jsonString && jsonString !== "") {
            try {
                retStruct = YAHOO.lang.JSON.parse(jsonString);
            } catch(e) {
                try {
                    retStruct = top.HEURIST.util.evalJSON(jsonString);
                } catch(e1) {
                    retStruct = "";
                }
            }
        }
        return retStruct;
    },

    evalJSON: function() {
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
* create and add option to select element
*
* @param sel
* @param value
* @param text
*
* @returns {object}
*/
    addoption: function(sel, value, text)
    {
        var option = document.createElement("option");
        option.text = text;
        option.value = value;
        try {
            // for IE earlier than version 8
            sel.add(option, sel.options[null]);
        }catch (ex2){
            sel.add(option,null);
        }
        return option;
    },

    isArray: function (a)
    {
        return Object.prototype.toString.apply(a) === '[object Array]';
    },
/**
* Helper function that creates a select HTML object filled with an option element for each term "depth first"
* tagged with class depthN and termHeader according to the terms tree depth and if it's id in in the headerList.
* @author Stephen White
* @author Artem Osmakov
* @param termIDTree an array tree of term ids
* @param disabledTermIDsList a comma separated list of term ids to be markered as headers, can be empty
* @param termLookup a lookup array of term names
* @param defaultTermID id of term to show as selected, can be null
* @return selObj an HTML select object node
*/
    createTermSelect: function(termIDTree, disabledTermIDsList, datatype, defaultTermID) { // Creates the preview
        return top.HEURIST.util.createTermSelectExt(termIDTree, disabledTermIDsList, datatype, defaultTermID, false); // Creates the preview
    },
    createTermSelectExt: function(termIDTree, disabledTermIDsList, datatype, defaultTermID, isAddFirstEmpty) { // Creates the preview

        var selObj = document.createElement("select");
        
        var hasTermImage = false;

        if(datatype === "relmarker" || datatype === "relationtype"){
            datatype = "relation";
        }else if(!(datatype=="enum" || datatype=="relation")){
            return selObj;
        }
        var termLookup = top.HEURIST.terms.termsByDomainLookup[datatype];

        var temp = ( top.HEURIST.util.isArray(disabledTermIDsList)   //instanceof(Array)
            ? disabledTermIDsList
            : (typeof(disabledTermIDsList) === "string" && disabledTermIDsList.length > 0 ?
                disabledTermIDsList.split(","):
                []));
        var isNotFirefox = (navigator.userAgent.indexOf('Firefox')<0);
        
        //this is vocabulary id - show list of all terms for this vocab
        if(!isNaN(Number(termIDTree))){
            
            var vocabId = Number(termIDTree);
            if(vocabId<0){
                top.HEURIST.util.addoption(selObj, '', 'No terms defined - please use Manage Structure to fix');
            }else{
            
                var tree = top.HEURIST.terms.treesByDomain[datatype];
                termIDTree = (vocabId==0)?tree:tree[vocabId];
                if (top.HEURIST.util.isEmptyVar(termIDTree)) {
                  var label = document.createElement("span");
                  label.innerHTML = " no terms available ";
                  return label;
                }
                
                if(vocabId==0){ //specific 
                    temp = [];
                    for(termID in termIDTree){
                        if(termID){
                            temp.push(termID);
                        }
                    }
                }
            }
           
        }
        
        var disabledTerms = {};
        for (var id in temp) {
            disabledTerms[temp[id]] = temp[id];
        }        

        function createSubTreeOptions(optgroup, depth, termSubTree, termLookupInner, defaultTermID) {
            var termID;
            var localLookup = termLookupInner;
            var termName, hasImage = false,
            termCode,
            arrterm = [];

            
            function getFullTermLabel(term, domain, withVocab){

                var fi = top.HEURIST.terms['fieldNamesToIndex'];// .treesByDomain[datatype];
                var parent_id = term[ fi['trm_ParentTermID'] ];

                var parent_label = '';

                if(parent_id!=null && parent_id>0){
                    term_parent = top.HEURIST.terms['termsByDomainLookup'][domain][parent_id];
                    if(term_parent){
                        if(!withVocab){
                            parent_id = term_parent[ fi['trm_ParentTermID'] ];
                            if(!(parent_id>0)){
                                return term[ fi['trm_Label']];
                            }
                        }
                        
                        parent_label = getFullTermLabel(term_parent, domain, withVocab);    
                        if(parent_label) parent_label = parent_label + '.';
                    }    
                }
                return parent_label + term[ fi['trm_Label']];
            }
            
            
            for(termID in termSubTree) { // For every term in 'term'
                termName = "";
                termCode = "";

                if(localLookup[termID]){
                    if(true){ //2017-02-28 no more hierarchy in list  was (depth<1){
                        termName = localLookup[termID][top.HEURIST.terms.fieldNamesToIndex['trm_Label']];
                    }else{
                        termName = getFullTermLabel(localLookup[termID], datatype, false);
                    }
                    termCode = localLookup[termID][top.HEURIST.terms.fieldNamesToIndex['trm_Code']];
                    hasImage = localLookup[termID][top.HEURIST.terms.fieldNamesToIndex['trm_HasImage']];
                    

                    if(top.HEURIST.util.isempty(termCode)){
                        termCode = '';
                    }else{
                        termCode = " ("+termCode+")";
                    }
                }

                if(top.HEURIST.util.isempty(termName)) continue;

                arrterm.push([termID, termName, termCode, hasImage]);
            }

            //sort by name
            arrterm.sort(function (a,b){
                    return a[1]<b[1]?-1:1;
            });

            var i=0, cnt= arrterm.length;
            for(;i<cnt;i++) { // For every term in 'term'

                termID = arrterm[i][0];
                termName = arrterm[i][1];
                termCode = arrterm[i][2];
                hasImage = arrterm[i][3];

                if(isNotFirefox && (depth>1 || (optgroup==null && depth>0) )){
                    //for non mozilla add manual indent
                    var a = new Array(depth*2);
                    termName = a.join('…') + termName; //was '. '
                }

                var isDisabled = (disabledTerms[termID]? true:false);
                var hasChildren = ( typeof termSubTree[termID] == "object" && Object.keys(termSubTree[termID]).length>0 );
                var isHeader   = ((disabledTerms[termID]? true:false) && hasChildren);

                //in FF optgroup is allowed on first level only - otherwise it is invisible

                if(isHeader && depth==0) { // header term behaves like an option group
                    //opt.className +=  ' termHeader';

                    var new_optgroup = document.createElement("optgroup");
                    new_optgroup.label = termName;

                    if(optgroup==null){
                        selObj.appendChild(new_optgroup);
                    }else{
                        optgroup.appendChild(new_optgroup);
                    }

                    //A dept of 8 (depth starts at 0) is maximum, to keep it organised
                    createSubTreeOptions( new_optgroup, ((depth<7)?depth+1:depth), termSubTree[termID], localLookup, defaultTermID)
                }else{
                    var opt = new Option(termName+termCode, termID);
                    opt.className = "depth" + depth;
                    opt.disabled = isDisabled;

                    if (termID == defaultTermID ||
                        termName == defaultTermID) {
                        opt.selected = true;
                    }
                    
                    if(!isDisabled){
                        hasTermImage = hasTermImage || hasImage;
                    }

                    if(optgroup==null){
                        selObj.appendChild(opt);
                    }else{
                        optgroup.appendChild(opt);
                    }

                    //second and more levels terms
                    if(hasChildren) {
                        // A depth of 8 (depth starts at 0) is the max indentation, to keep it organised
                        createSubTreeOptions( optgroup, ((depth<7)?depth+1:depth), termSubTree[termID], localLookup, defaultTermID);
                    }
                }
            }
        }

        if(isAddFirstEmpty){
            top.HEURIST.util.addoption(selObj, '', '');
        }

        createSubTreeOptions(null, 0,termIDTree, termLookup, defaultTermID);
        if (!defaultTermID) selObj.selectedIndex = 0;
        if(hasTermImage){
            selObj['data-images'] = 'hasImages';
        }
        
        
        return selObj;
    },

    isnull: function(obj){
        return ( (typeof obj==="undefined") || (obj===null));
    },

    isempty: function(obj){
        return ( top.HEURIST.util.isnull(obj) || (obj==="") || (obj==="null") );
    },

    isEmptyVar: function(obj){
      if( (typeof obj==="undefined") || (obj==="") || (obj===null) ){
        return true;
      }
      if (obj.constructor.name == "Array" ||obj.constructor.name == "Object") {
          for (var i in obj) { return false };
          return true;
      }
      return (obj.toString()).length == 0;
    },
    
    isNumber: function (n) {
        //return typeof n === 'number' && isFinite(n);
        return !isNaN(parseFloat(n)) && isFinite(n);
    },

    validateName: function(name, lbl){
      
            var swarn = "";
            var regex = /[\[\].\$]+/;
            var name = name.toLowerCase();
            if( name=="id" || name=="modified" || name=="rectitle"){
                   swarn = lbl+", you defined, is a reserved word. Please try an alternative";
            }else if (regex.test(name) ) {
                   swarn = lbl+" contains 'full stop' characters which are not permitted in this context. Please use alphanumeric characters.";
            }
            return swarn;
    },
    
    //validate numeric
    validate: function(evt) {
        var theEvent = evt || window.event;
        var key = theEvent.keyCode || theEvent.which;
        if(key==37 || key==39) return;
        key = String.fromCharCode( key );
        var regex = /[0-9]|\./;
        if( !regex.test(key) ) {
            theEvent.returnValue = false;
            theEvent.preventDefault();
        }
    },

/**
* deep cloning of object
*
* @param o
* @todo  naming change for o
*/
    cloneObj: function(o) {
        //return eval($.toJSON(o));

        if(typeof(o) != "object") return o;

        if(o == null) return o;

        if(top.HEURIST.util.isArray(o)){
            var new2 = [];
            for(var i in o) new2.push(top.HEURIST.util.cloneObj(o[i]));
            return new2;
        }else{
            var newO = new Object();
            for(var i in o) newO[i] = top.HEURIST.util.cloneObj(o[i]);
            return newO;
        }
    },

    showError: function(msg, dlg_title){
        if(top.HEURIST.util.isempty(msg)){
            msg = "Incorrect response from server. Please contact development team if this error persists";
        }else if (Number(msg)===-1 || typeof msg !== 'string'){
            msg = "No response from server, please try again later";
        }else if (msg.toLowerCase().indexOf("error")<0 && top.HEURIST.util.isempty(dlg_title)){
            msg = "Error occurred: " + msg;
        }
        
        if(hasH4()){
            msg = {message:msg, error_title:dlg_title};
            window.hWin.HEURIST4.msg.showMsgErr(msg);
        }else{
            alert(msg);    
        }
    },
    showMessage: function(msg){
        if(hasH4()){
            window.hWin.HEURIST4.msg.showMsgDlg(msg);
        }else{
            alert(msg);    
        }
    },

/**
* Returns array that contain the mouse position relative to the document
*
* @param e
*
* @return {Array} xy position
* @todo  match naming of element or object
*/
    getMousePos: function(e){

        var posx = 0;
        var posy = 0;
        if (!e) var e = window.event;
        if (e.pageX || e.pageY)     {
            posx = e.pageX;
            posy = e.pageY;
        }
        else if (e.clientX || e.clientY)     {
            posx = e.clientX
            + document.documentElement.scrollLeft;
            posy = e.clientY
            + document.documentElement.scrollTop;
        }

        return [posx, posy];
    },

    getOffset: function(obj) {

        var x = y = 0;
        var sleft = 0;//obj.ownerDocument.body.scrollLeft;
        var stop = 0; //obj.ownerDocument.body.scrollTop;
        while (obj) {
            x += obj.offsetLeft;
            y += obj.offsetTop;
            obj = obj.offsetParent;
        }
        return [x-sleft, y-stop];
    },

/**
* Adjusts the position of div to prevent it displaying outside of border
*
* @param _div
* @param xy
* @param border_top
* @param border_right
* @param border_height
* @param offset
* @todo  remove extranious parameters and dead code
*/
    showPopupDivAt: function(_div, xy, border_top, border_right, border_height, offset){

        /*var border_top = $(window).scrollTop();
        var border_right = $(window).width();
        var border_height = $(window).height();
        border_top = document.body.scrollTop;
        border_right = document.body.width;
        border_height =  document.body.height;*/

        var div_height =  _div.height();
        var div_width =  _div.width();
        var pageHeight = _div.parents().height();
        var scrollValue = _div.parents().scrollTop();
        if(!offset) {
            offset = 5;
        }
        /*var left_pos;
        var top_pos;
        if(top.HEURIST.util.isnull(offset)){
        offset = 5;
        }

        if( (border_right - offset*2) >= (xy[0] + _div.width()) ) {
        left_pos = xy[0]+offset;
        } else {
        left_pos = xy[0] - _div.width() - offset*2 - 5; // border_right-_div.width()-offset-10; //out of right border
        }

        if( (xy[1] + offset + div_height) < (border_top + border_height) ){ //  (border_top + offset*2) >=  (xy[1] - _div.height())  ) {
        top_pos = xy[1] + offset;
        } else {
        //out of bottom   border_top +
        top_pos = border_top + border_height - div_height - offset;
        }*/

        /*
        if( (top_pos + _div.height()) > (border_top+border_height) ){
        top_pos    = border_top + border_height - _div.height() - offset;
        }*/


        //var lft = _div.css('left');
        left_pos=Math.max(0,Math.min(xy[0]+offset, border_right - div_width));
        top_pos=Math.max(xy[1]-(div_height/2)+offset,0)+scrollValue;

        _div.css( {    left:left_pos+'px',
                top:top_pos+'px',
                visibility:'visible',
                opacity:'1'});
    },

    /**
     * write script - should be used in top of page
     * @todo review with Artem to understand why top.HEURIST.loadScript or window.HEURIST.loadScript doesn't work
     */
    loadScript2: function(doc, url){
        doc.write("<script src=\"" + url + "\"></script>");
    },

    autoSize: function(textElt, o){

        o = $.extend({
                maxWidth: 1000,
                minWidth: 200,
                comfortZone: 10
            }, o);

        var input = $(textElt);

        //var d = this.document.getElementById("testerwidth");
        if($("#testerwidth").length == 0){
            $('<div id="testerwidth"></div>').appendTo('body')
        }

        var testSubject = $('#testerwidth').css({
                position: 'absolute',
                top: -9999,
                left: -9999,
                width: 'auto',
                fontSize: input.css('fontSize'),
                fontFamily: input.css('fontFamily'),
                fontWeight: input.css('fontWeight'),
                letterSpacing: input.css('letterSpacing'),
                whiteSpace: 'nowrap'
        });

        // Enter new content into testSubject
        var escaped = textElt.value.replace(/&/g, '&amp;').replace(/\s/g,'&nbsp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        testSubject.html(escaped);

        // Calculate new width + whether to change
        var testerWidth = testSubject.width(),
        newWidth = (testerWidth + o.comfortZone) >= o.minWidth ? testerWidth + o.comfortZone : o.minWidth;
        if(newWidth > o.maxWidth){
            newWidth = o.maxWidth;
        }
        input.width(newWidth);
        /*currentWidth = input.width(),
        isValidWidthChange = (newWidth < currentWidth && newWidth >= o.minWidth)
        || (newWidth > o.minWidth && newWidth < o.maxWidth);

        // Animate width
        if (isValidWidthChange) {
        input.width(newWidth);
        }
        */
    },
/**
* get a named parameter's value from a query string or location.search
*
* @param name
* @param [query]
*/
    getUrlParameter: function getUrlParameter(name, query){

        if(!query){
            query = window.location.search;
        }

        var regexS = "[\\?&]"+name+"=([^&#]*)";
        var regex = new RegExp( regexS );
        var results = regex.exec( query );

        if( results == null ) {
            return null;
        } else {
            return results[1];
        }
    },
/**
* returns an HTML fragment which displays the rectype's icon followed by it's name
*
* @param recTypeID
*
* @returns {String}
*/
    getRectypeIconAndName: function(recTypeID)
    {
        if(recTypeID && top.HEURIST.rectypes && top.HEURIST.rectypes.names[recTypeID]){
            return "<img src=\""+top.HEURIST.baseURL+"common/images/16x16.gif\" "+
            "style=\"background-image: url('"+top.HEURIST.iconBaseURL+recTypeID+"');\" /> "+top.HEURIST.rectypes.names[recTypeID];
        }else{
            return "";
        }

    },
    
    getRectypeName: function(recTypeID)
    {
        if(recTypeID && top.HEURIST.rectypes && top.HEURIST.rectypes.names[recTypeID]){
            return top.HEURIST.rectypes.names[recTypeID];
        }else{
            return "";
        }

    },
    
/**
* TODO: Artem to doc where this comes from
*
* @param functionName
* @param context
*/
    executeFunctionByName: function (functionName, context /*, args */) {
        var args = Array.prototype.slice.call(arguments).splice(2);
        args = args[0];
        var namespaces = functionName.split(".");
        var func = namespaces.pop();
        for(var i = 0; i < namespaces.length; ++i) {
            if (i<namespaces.length) {
                context = context[namespaces[i]];
            }
        }
        return context[func].apply(this, args);
    },

/**
* stop event propogation
*
* @param e
*/
    stopEvent: function(e){
        if (!e) e = window.event;
        if (e) {
            e.cancelBubble = true;
            if (e.stopPropagation) e.stopPropagation();
        }
        return e;
    },
/**
* onclick handler that solves the Safari issue of not responding to onclick
*
* @param ele
*/
    clickworkaround: function(ele)
    {
        if(navigator.userAgent.indexOf('Safari')>0){
            //Safari so create a click event and dispatch it
            var event = document.createEvent("HTMLEvents");
            event.initEvent("click", true, true);
            ele.dispatchEvent(event);
        }else{
            // direct dispatch
            ele.click();
        }
    },
/**
* Load save search information for a workgroup, if not loaded then getData from server
* put data into top.HEURIST , if already loaded use existing data
* then execute callback.
*
* @param wg_id
* @param callback
*/
    loadWorkgroupDetails: function(wg_id, callback) {
        if (top.HEURIST.workgroups[wg_id].members && //if data already loaded  then just call callback function
            top.HEURIST.workgroups[wg_id].savedSearches &&
            top.HEURIST.workgroups[wg_id].publishedSearches) {
            if (callback) callback(wg_id);
            return;
        }
        // workgroup data not loaded so make asynch call to load it.
        top.HEURIST.util.getJsonData(top.HEURIST.baseURL+"common/php/loadGroupSavedSearches.php?"+
                                        "db="+(top.HEURIST.database.name ? top.HEURIST.database.name: "") +
                                        "&wg_id="+wg_id,
                                        function(obj) {//callback
                                            if (! obj  || obj.error) return;
                                            top.HEURIST.workgroups[wg_id].members = obj.members;
                                            top.HEURIST.workgroups[wg_id].savedSearches = obj.savedSearches;
                                            top.HEURIST.workgroups[wg_id].publishedSearches = obj.publishedSearches;
                                            if (callback) callback(wg_id);
                                        }
                                    );
    },

/**
* Returns current position of curstor in text input
*
* @param ele
*/
    getCaretPos: function (ele){
        // Initialize
          var iCaretPos = 0;
          // IE Support
          if (document.selection) {
            ele.focus ();
            var oSel = document.selection.createRange();
            oSel.moveStart ('character', -ele.value.length);
            iCaretPos = oSel.text.length;

          } // Firefox support
          else if (ele.selectionStart || ele.selectionStart == '0'){
            iCaretPos = ele.selectionStart;
          }
          return (iCaretPos);
    },
    
    //count UTF-8 bytes of a string
    byteLengthOf: function (s){
        //assuming the String is UCS-2(aka UTF-16) encoded
        var n=0;
        for(var i=0,l=s.length; i<l; i++){
            var hi=s.charCodeAt(i);
            if(hi<0x0080){ //[0x0000, 0x007F]
                n+=1;
            }else if(hi<0x0800){ //[0x0080, 0x07FF]
                n+=2;
            }else if(hi<0xD800){ //[0x0800, 0xD7FF]
                n+=3;
            }else if(hi<0xDC00){ //[0xD800, 0xDBFF]
                var lo=s.charCodeAt(++i);
                if(i<l&&lo>=0xDC00&&lo<=0xDFFF){ //followed by [0xDC00, 0xDFFF]
                    n+=4;
                }else{
                    return byteLengthOf2(s);
                    //throw new Error("UCS-2 String malformed");
                }
            }else if(hi<0xE000){ //[0xDC00, 0xDFFF]
                return byteLengthOf2(s);
                //throw new Error("UCS-2 String malformed");
            }else{ //[0xE000, 0xFFFF]
                n+=3;
            }
        }
        return n;
    },
    
    byteLengthOf2: function(text){
        return encodeURIComponent(text).replace(/%[A-F\d]{2}/g, 'U').length;
        //return (unescape(encodeURIComponent(s).length);
        
    },
    
    cleanFilename: function(filename) {
        filename = filename.replace(/\s+/gi, '-'); // Replace white space with dash
        filename= filename.split(/[^a-zA-Z0-9\-\_\.]/gi).join('_');
        return filename;
    },
};
//-----------------------------------------------------------------------------------------------------
if(top.HEURIST.registerEvent) //AJ: it would be better to remove from here
    {
    top.HEURIST.registerEvent(window, "contentloaded", top.HEURIST.util.setVersion);

    /* Invoke  autosizeAllElements()  when the window loads or is resized */
    top.HEURIST.registerEvent(window, "load", function() { setTimeout(top.HEURIST.util.autosizeAllElements, 0); }, false, 99);    // need to autosize very late
    top.HEURIST.registerEvent(window, "resize", function() {
            if (window.resizeTimeout) {
                window.clearTimeout(window.resizeTimeout);
            }
            window.resizeTimeout = window.setTimeout(function() { top.HEURIST.util.autosizeAllElements(); window.resizeTimeout = 0; }, 250);
    });
}

if (!Array.prototype.indexOf)
    {
    Array.prototype.indexOf = function(elt /*, from*/)
    {
        var len = this.length;

        var from = Number(arguments[1]) || 0;
        from = (from < 0)
        ? Math.ceil(from)
        : Math.floor(from);
        if (from < 0)
            from += len;

        for (; from < len; from++)
        {
            if (from in this &&
                this[from] === elt)
                return from;
        }
        return -1;
    };
}

//fancy dialog boxes --------------------------------------------------------------------------
// constants to define the title of the alert and button text.
var ALERT_BUTTON_TEXT = "OK";
var args = "";
// over-ride the alert method only if this a newer browser.
// Older browser will see standard alerts
if(document.getElementById) {
    window.alert = function(txt) {
        createCustomAlert(txt,args);
        return true;
    }
}
/**
* put your comment there...
*
* @param txt
* @param args
*/
function createCustomAlert(txt,args) {
    
    
        if(typeof jQuery == "undefined" || !hasH4()){
            
            
    // shortcut reference to the document object
    d = document;

    // if the modalContainer object already exists in the DOM, bail out.
    if(d.getElementById("modalContainer")) return;

    // create the modalContainer div as a child of the BODY element
    mObj = d.getElementsByTagName("body")[0].appendChild(d.createElement("div"));
    mObj.id = "modalContainer";
    // make sure its as tall as it needs to be to overlay all the content on the page
    mObj.className = "coverall";
    mObj.style.zIndex = 2147483647;// "max";

    // create the DIV that will be the alert
    alertObj = mObj.appendChild(d.createElement("div"));
    alertObj.id = "alertBox";

    // create a paragraph element to contain the txt argument
    msg = alertObj.appendChild(d.createElement("p"));
    msg.innerHTML = txt;

    // create an anchor element to use as the confirmation button.
    btn = alertObj.appendChild(d.createElement("button"));
    btn.id = "closeBtn";
    btn.appendChild(d.createTextNode(ALERT_BUTTON_TEXT));
    btn.href = "#";
    // set up the onclick event to remove the alert when the anchor is clicked
    btn.onclick = function() {
        //if (args = "map") _tabView.set('activeIndex', 0);
        removeCustomAlert();
        return ; }
        
        }else{
            window.hWin.HEURIST4.msg.showMsgDlg(txt, null, ""); // removed "Info" title - inelegant and not useful
        }
        
}

// removes the custom alert from the DOM
function removeCustomAlert() {
    document.getElementsByTagName("body")[0].removeChild(document.getElementById("modalContainer"));return;
}

function hasH4(){
    return window.hWin && window.hWin.HEURIST4;
}

// in H4 we use similar function once in initPage.
// in H3 each page is inited in its own way - so this function is defined here
//
function detectHeurist(win){
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
        return detectHeurist( win.parent );
    }
}
