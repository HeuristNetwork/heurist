/**
* editRecord.js: Record editing functions loaaded by editRecord.html
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

// -----------------------------------------------------------------------------------------------------------------------

if (! top.HEURIST.edit) {

    top.HEURIST.edit = {

        /**
        * struct for managing modules (tabs, lefthand sidebar) in the edit form
        *
        * @type Object
        */
        modules: {
            'public': { url: 'tabs/publicInfoTab.html', 'link-id': 'public-link', loaded: false, loading: false, changed: false,
                preload: function()
                { return (top.HEURIST.edit.record.bibID  &&  top.HEURIST.edit.record.bibID != 0); },
                title:"Shared information" },
            'personal': { url: 'tabs/personalInfoTab.html', 'link-id': 'personal-link', loaded: false, loading: false, changed: false,
                preload: function()
                { return (top.HEURIST.edit.record.bkmkID  &&  top.HEURIST.edit.record.bkmkID != 0); },
                disabledFunction: function() { top.HEURIST.edit.addMissingBookmark() },
                title:"Private information" },
            'annotation': { url: 'tabs/annotationTab.html', 'link-id': 'annotation-link', loaded: false, loading: false, changed: false,
                preload: function()
                { return true; },
                title:"Text" },
            'workgroups': { url: 'tabs/usergroupsTab.html', 'link-id': 'workgroups-link', loaded: false, loading: false, changed: false,
                preload: function()
                { return (top.HEURIST.edit.record.bibID  &&  top.HEURIST.edit.record.bibID != 0  &&  top.HEURIST.user.workgroups.length > 0); },
                title:"Workgroup tags"},
            'relationships': { url: 'tabs/relationshipsTab.html', 'link-id': 'relationships-link', loaded: false, loading: false, changed: false,
                preload: function()
                { return (top.HEURIST.edit.record.bibID  &&  top.HEURIST.edit.record.bibID != 0); },
                title:"Relationships" }
        },


        /**
        * load module by testing preload conditions. If yes then fix up sidebar
        * then create iFrame with source for tab, create tab and add frame.
        *
        * @param name
        *
        * @returns {Boolean}
        */
        loadModule: function(name) {
            if (! top.HEURIST.edit.modules[name]) return false;// unknown module, do not waste my time
            var module = top.HEURIST.edit.modules[name];
            if (module.loaded  ||  module.loading) return true;//our work is done

            var sidebarLink = document.getElementById(module["link-id"]);

            // check the preload function to see if it should in fact be loaded
            if (module.preload  &&  ! module.preload()) {
                var disabledDescription = sidebarLink.getAttribute("disabledDescription");
                for (var i=0; i < sidebarLink.childNodes.length; ++i) {
                    var child = sidebarLink.childNodes[i];
                    if (child.id == "desc") {
                        if (! sidebarLink.getAttribute("enabledDescription"))
                            sidebarLink.setAttribute("enabledDescription", child.innerHTML);
                        // child.removeChild(child.firstChild);
                        child.innerHTML = "";
                        child.appendChild(document.createTextNode(disabledDescription));
                        break;
                    }
                }
                sidebarLink.title = sidebarLink.getAttribute("disabledTitle");

                return false;
            } else if (sidebarLink && sidebarLink.getAttribute("enabledDescription")) {
                // we are patching a module that was previously disabled but is now enabled
                var enabledDescription = sidebarLink.getAttribute("enabledDescription");
                for (var i=0; i < sidebarLink.childNodes.length; ++i) {
                    var child = sidebarLink.childNodes[i];
                    if (child.id == "desc") {
                        child.removeChild(child.firstChild);
                        child.appendChild(document.createTextNode(enabledDescription));
                        break;
                    }
                }
                sidebarLink.className = sidebarLink.className.replace(/ disabled$/, "");
                sidebarLink.removeAttribute("enabledDescription");
                sidebarLink.title = "";
            }

            module.loading = true;

            var newIframe = top.document.createElement('iframe');
            newIframe.className = "tab";
            newIframe.frameBorder = 0;
            var newHeuristID = newIframe.HEURIST_WINDOW_ID = top.HEURIST.createHeuristWindowID(module.url);
            // FIXME: seems that safari 1 requires these dimensions set explicitly here even though they're in .tab ..?
            newIframe.style.width = "100%";
            newIframe.style.height = "100%";

            var oneTimeOnload = function() {
                // One time onload
                if (module.postload) module.postload.apply(newIframe.contentWindow);
                top.HEURIST.deregisterEvent(newIframe, "load", oneTimeOnload);
            };

            top.HEURIST.registerEvent(newIframe, "load", function() {
                module.loaded = true;
                module.loading = false;
                try {
                    newIframe.contentWindow.HEURIST_WINDOW_ID = newHeuristID;

                    var helpLink = top.document.getElementById("help-link");
                    top.HEURIST.util.setHelpDiv(helpLink,null);

                    var status = top.HEURIST.displayPreferences["input-visibility"];
                    document.getElementById("input-visibility").checked = (status === "all");
                    //init class for body element
                    top.HEURIST.util.setDisplayPreference("input-visibility", status);

                } catch (e) { }
            });

            // create the url for the frame src
            var urlBits = [];
            var parameters = top.HEURIST.edit.record;
            if (parameters.bibID) urlBits.push("recID=" + parameters.bibID);
            if (parameters.bkmkID) urlBits.push("bkmk_id=" + parameters.bkmkID);
            if (HAPI.database) urlBits.push("db=" + HAPI.database);
            var url = module.url;
            if (urlBits.length > 0) url += "?" + urlBits.join("&");
            newIframe.src = url;

            var tabHolder = document.getElementById("tab-holder");
            module.frame = newIframe;
            tabHolder.appendChild(newIframe);

            return true;

        }, // loadModule


        /**
        * show the named module
        *
        * @param name
        *
        * @returns {Boolean}
        */
        showModule: function(name) {
            if (top.HEURIST.edit.preventTabSwitch) return false;

            if (! top.HEURIST.edit.loadModule(name)) {    // make sure the page is in the loading queue ...
                if (top.HEURIST.edit.modules[name].disabledFunction) {
                    top.HEURIST.edit.modules[name].disabledFunction();
                }
                return false;
            }

            var modules = top.HEURIST.edit.modules;

            var tabHolder = document.getElementById("tab-holder");
            // get rid of any non-iframe children ... obviously have to do it backwards or they will change position!
            // also set display: none on all the iframe children
            for (var i=tabHolder.childNodes.length-1; i >= 0; --i) {
                if (! (tabHolder.childNodes[i].className  &&  tabHolder.childNodes[i].className.match(/\btab\b/))) {
                    tabHolder.removeChild(tabHolder.childNodes[i]);
                }
                else if (top.HEURIST.browser.isEarlyWebkit) {
                    if (parseInt(tabHolder.childNodes[i].style.height) > 0) {
                        tabHolder.childNodes[i].savedHeight = tabHolder.childNodes[i].style.height;
                    }
                    tabHolder.childNodes[i].style.height = "0";
                    tabHolder.childNodes[i].style.overflow = "hidden";
                }
                else {
                    tabHolder.childNodes[i].style.display = "none";
                }
            }
            if (top.HEURIST.browser.isEarlyWebkit) {
                modules[name].frame.style.height = modules[name].frame.savedHeight;
            } else {
                modules[name].frame.style.display = "block";
            }

            // presentation stuff: mark the currently visible module
            for (var eachName in modules) {
                if (eachName !== name) {
                    var sidebarLink = document.getElementById(modules[eachName]["link-id"]);
                    sidebarLink.className = sidebarLink.className.replace(/\b\s*selected\s*\b/g, " ");
                }
            }

            // record the current panel so we can bookmark it properly ...
            // the conditional is so that safari 1 doesn't constantly reload
            if (document.location.hash != ("#"+name))
                document.location.replace("#" + name);

            document.getElementById(modules[name]["link-id"]).className += " selected";
            document.body.className = document.body.className.replace(/\b\s*mode-[a-z]+\b|$/, " mode-" + name);

            top.HEURIST.edit.callOnShow(name);

            return true;

        }, //showModule


        /**
        * call on show
        *
        * @param name
        *
        * @returns
        */
        callOnShow:function(name){
            var modules = top.HEURIST.edit.modules;
            if (modules[name].frame.contentWindow.onshow) {
                modules[name].frame.contentWindow.onshow.call(modules[name].frame.contentWindow);
            }else{
                setTimeout(function(){top.HEURIST.edit.callOnShow(name);},500);
            }
        },


        /**
        * load all modules (sidebar tabs)
        *
        * @param
        *
        * @returns
        */
        loadAllModules: function() {
            // first, mark all modules as disabled ...
            var sidebarDiv = document.getElementById("sidebar");
            for (var i=0; i < sidebarDiv.childNodes.length; ++i) {
                var child = sidebarDiv.childNodes[i];
                if (child.className  &&  child.className.match(/\bsidebar-link\b/)) {
                    if (! child.className.match(/\bdisabled\b/)) child.className += " disabled";
                }
            }


            var firstName = null;
            for (var eachName in top.HEURIST.edit.modules) {
                var linkButton = document.getElementById(top.HEURIST.edit.modules[eachName]["link-id"]);

                if (top.HEURIST.edit.loadModule(eachName)) {
                    if (! firstName) firstName = eachName;
                    linkButton.className = linkButton.className.replace(/ disabled$/, "");
                }

                top.HEURIST.registerEvent(linkButton, "click", function(name) { return function() { top.HEURIST.edit.showModule(name); } }(eachName) );
            }

            var hash = document.location.hash.substring(1);
            if (top.HEURIST.edit.modules[hash]  &&  (top.HEURIST.edit.modules[hash].loading  ||  top.HEURIST.edit.modules[hash].loaded)) {
                if (top.HEURIST.edit.showModule(hash)) return;
            }
            if (firstName) {
                top.HEURIST.edit.showModule(firstName);
            }
        }, // loadAllModules


        /**
        * test whether user can edit the record (user/workgroup is owner)
        *
        * @param
        *
        * @returns {Boolean}
        */
        userCanEdit: function() {
            if (top.HEURIST.user.isInWorkgroup(parseInt(window.HEURIST.edit.record.workgroupID))){ // user is owner
                return true;
            }
            return false;
        },


        /**
        * test whether user can change the access settings for the record
        *
        * @param
        *
        * @returns {Boolean}
        */
        userCanChangeAccess: function() {
            if (top.HEURIST.is_wgAdmin(parseInt(window.HEURIST.edit.record.workgroupID))){
                return true;
            }
            return false;
        },


        /**
        * make the form read only so data cannot be edited
        *
        * @param
        *
        * @returns
        */
        setAllInputsReadOnly: function(readonly) {
            for (var i =0; i< top.HEURIST.edit.allInputs.length; i++) {
                if (top.HEURIST.edit.allInputs[i].setReadonly) {
                    top.HEURIST.edit.allInputs[i].setReadonly(readonly);
                }
            }
        },


        /**
        * put your comment there...
        *
        */
        showRecordProperties: function() {
            // fill in the toolbar fields with the details for this record
            //        document.getElementById('rectype-val').innerHTML = '';
            //        document.getElementById('rectype-val').appendChild(document.createTextNode(top.HEURIST.record.rectype));
            if (document) {
                /* TODO: remove? ARTEM commented out 2012-10-08
                if (document.getElementById('rectype-img')) {
                document.getElementById('rectype-img').style.backgroundImage = "url("+ top.HEURIST.iconBaseURL + top.HEURIST.edit.record.rectypeID + ".png)";
                }*/
                if (document.getElementById('title-val')) {
                    document.getElementById('title-val').innerHTML = top.HEURIST.edit.record.title;
                    // document.getElementById('title-val').appendChild(document.createTextNode(top.HEURIST.edit.record.title));
                }
                if (document.getElementById('workgroup-val')) {
                    if (top.HEURIST.edit.record.workgroup) {
                        document.getElementById('workgroup-val').innerHTML = '';
                        document.getElementById('workgroup-val').appendChild(document.createTextNode(top.HEURIST.edit.record.workgroup));
                    } else {
                        document.getElementById('workgroup-val').innerHTML = 'everyone';
                    }
                    if (top.HEURIST.edit.record.visibility) {
                        var recVis = top.HEURIST.edit.record.visibility;
                        var othersAccess = (recVis == "hidden")? "hidden (owners only)" :
                        (recVis == "viewable")? "any logged-in user" :
                        (recVis == "pending")? "pending publication" : "public";
                        document.getElementById('workgroup-access').innerHTML = othersAccess;
                    } else {
                        document.getElementById('workgroup-access').innerHTML = 'visible';
                    }
                    document.getElementById('workgroup-div').style.display = "inline-block";
                }
                if (document.getElementById('workgroup-edit')) {
                    top.HEURIST.edit.setAllInputsReadOnly(!top.HEURIST.edit.userCanEdit());
                    if (top.HEURIST.edit.userCanChangeAccess()){
                        document.getElementById('workgroup-edit').onclick = openWorkgroupChanger;
                        document.getElementById('workgroup-edit').title = "Restrict access by workgroup";
                        document.getElementById('wg-edit-img').src = "../../common/images/edit-pencil.png"
                    }else{
                        document.getElementById('workgroup-edit').onclick = null;
                        document.getElementById('workgroup-edit').title = "Access denied";
                        document.getElementById('wg-edit-img').src = "../../common/images/edit-pencil-no.png"
                    }
                }
            }
        }, // showRecordProperties


        /**
        * If user clicks on Personal tab and the record has not yet been bookmarked, give user option of doing so
        *
        */
        addMissingBookmark: function() {
            if (! top.HEURIST.edit.record.bibID) return;
            if (confirm("You haven't bookmarked this record.\nWould you like to bookmark it now?")) {
                // only call the disabledFunction callback ONCE -- a safeguard against infinite loops
                top.HEURIST.edit.modules.personal.disabledFunction = null;

                // add the bookmark, patch the record structure, and view the personal tab
                top.HEURIST.util.getJsonData(top.HEURIST.basePath + "records/bookmarks/addBookmark.php?recID="
                    + top.HEURIST.edit.record.bibID + "&db=" + HAPI.database, function(vals) {
                        for (var i in vals) {
                            top.HEURIST.edit.record[i] = vals[i];
                        }
                        top.HEURIST.edit.showModule("personal");
                });
            }
        },


        /**
        * Cancels saving of the record and reloads the page, after checking whether user wishes to abandon any changes
        *
        * @param needClose
        *
        */
        cancelSave: function(needClose) {
            // Cancel the saving of the record (reload the page)
            // Out of courtesy, check if there have been any changes that WOULD have been saved.
            var modules = top.HEURIST.edit.modules;
            var changedModuleNames = [];
            for (var eachName in modules) {
                if (modules[eachName].changed)
                    changedModuleNames.push(modules[eachName].title);
            }

            var message;
            if (changedModuleNames.length == 0) {
                // APPARENTLY the correct behaviour when there are no changes is to reload everything.
                // Thanks for the vote of confidence, Ian.
                message = null;

            } else if (changedModuleNames.length == 1) {
                message = "Abandon changes to " + changedModuleNames[0] + " data?";
            } else {
                var lastName = changedModuleNames.pop();
                message = "Abandon changes to " + changedModuleNames.join(", ") + " and " + lastName + " data?";
            }

            if (message === null || confirm(message)) {
                for (var eachName in modules)
                    top.HEURIST.edit.unchanged(eachName);

                if(needClose || top.HEURIST.edit.isAdditionOfNewRecord())
                {
                    top.HEURIST.edit.closeEditWindow();
                } else if (message != null){
                    top.location.reload();
                } else if (message == null){
                    document.getElementById("popup-nothingchanged").style.display = "block";
                    setTimeout(function() {
                        document.getElementById("popup-nothingchanged").style.display = "none";
                        },500);
                }
            }

        },  // cancelSave


        /**
        * Saves the record
        *
        * @param callback
        *
        */
        save_record: function(callback){
            if(top.HEURIST.edit.is_something_chnaged()){

                top.HEURIST.edit.save(callback);

            }else{ //nothing was changed

                //always check required field
                var publicWindow = top.HEURIST.edit.modules['public']
                &&  top.HEURIST.edit.modules['public'].frame
                &&  top.HEURIST.edit.modules['public'].frame.contentWindow;

                if(publicWindow && top.HEURIST.edit.requiredInputsOK(publicWindow.HEURIST.inputs, publicWindow)){

                    if(callback && typeof(callback)==="function")
                    {
                        callback.call(this);

                    } else if(callback){ //force close

                        top.HEURIST.edit.closeEditWindow();

                    }else{
                        document.getElementById("popup-nothingchanged").style.display = "block";
                        setTimeout(function() {
                            document.getElementById("popup-nothingchanged").style.display = "none";
                            },500);
                    }
                }
            }
        }, //save_record


        savePopup: null,


        /**
        * this is an internal function that goes through all modules (sidebar tabs) and calls submit if there are data to be saved
        *
        * @param callback
        *
        */
        save: function(callback) {
            // Attempt to save all the modules that need saving
            // Display a small saving window
            /* TODO: ? remove
            if (! top.HEURIST.edit.savePopup) {
            top.HEURIST.edit.savePopup = top.HEURIST.util.popupURL(top, "img/saving-animation.gif", { "no-save": true, "no-resize": true, "no-titlebar": true });
            }
            */
            var personalWindow = top.HEURIST.edit.modules.personal
            &&  top.HEURIST.edit.modules.personal.frame
            &&  top.HEURIST.edit.modules.personal.frame.contentWindow;

            if (personalWindow  &&  ! personalWindow.tagCheckDone  &&  personalWindow.document.getElementById("tags").value.replace(/^\s+|\s+$/g, "") == "") {
                // personal tags field is empty -- popup the add tags dialogue
                personalWindow.tagCheckDone = true;

                if(top.HEURIST.util.getDisplayPreference('tagging-popup') !== "false"){
                    top.HEURIST.util.popupURL(top, top.HEURIST.basePath
                        + "records/tags/addTagsPopup.html?db="+HAPI.database+"&no-tags", { callback: function(tags) {
                            if (tags) {
                                personalWindow.document.getElementById("tags").value = tags;
                                top.HEURIST.edit.changed("personal");
                            }
                            top.HEURIST.util.setHelpDiv(document.getElementById("help-link"),null);

                            setTimeout(function() { top.HEURIST.edit.save(callback); }, 0);
                    } });
                    return;
                }
            }

            for (var moduleName in top.HEURIST.edit.modules) {
                var module = top.HEURIST.edit.modules[moduleName];

                var contentWindow = module.frame  &&  module.frame.contentWindow;
                if (! contentWindow) continue; /* not loaded yet! no need to save */

                var form = contentWindow.document.forms[0];

                if (form.heuristForceSubmit) {
                    // there are some things that don't set module.changed
                    // but we still want to ensure they are saved
                    form.heuristForceSubmit();
                }

                if (module.changed) { // don't bother saving unchanged tabs
                    if (form.onsubmit  &&  ! form.onsubmit()) {
                        top.HEURIST.edit.showModule(moduleName);
                        return;    // submit failed ... up to the individual form handlers to display messages
                    }

                    // Make a one-shot onload function to mark this module as unchanged and continue saving
                    var moduleUnchangeFunction = function() {
                        top.HEURIST.deregisterEvent(module.frame, "load", moduleUnchangeFunction);
                        top.HEURIST.edit.unchanged(moduleName);
                        top.HEURIST.edit.save(callback);    // will continue where we left off
                    };
                    //trigger this event after save
                    top.HEURIST.registerEvent(module.frame, "load", moduleUnchangeFunction);
                    (form.heuristSubmit || form.submit)();
                    return;
                }
            }

            // If we get here, then every module has been marked as unchanged (i.e. saved or equivalent)
            // Do whatever it is we need to do.

            // TODO: ? remove :  setTimeout(function() { top.HEURIST.util.closePopup(top.HEURIST.edit.savePopup.id); }, 5000);

            top.HEURIST.edit.showRecordProperties();
            setTimeout(function() {
                document.getElementById("popup-saved").style.display = "block";
                setTimeout(function() {
                    document.getElementById("popup-saved").style.display = "none";

                    if(caller_id_element && opener && opener.updateFromChild){
                        opener.updateFromChild(caller_id_element, top.HEURIST.edit.record.title);
                    }

                    if(callback && typeof(callback)==="function"){
                        callback.call(this);
                    }else if (callback){
                        top.HEURIST.edit.closeEditWindow();
                    }
                    }, 1000);
                }, 0);
        }, //save


        /**
        * detects whethere call resutls from addition of a new record or editing of an existing one
        *
        * @returns {Boolean}
        */
        isAdditionOfNewRecord: function(){
            if(top.HEURIST && top.HEURIST.parameters && top.HEURIST.parameters['fromadd']){
                return (top.HEURIST.parameters['fromadd']=="new_bib");
                // new_bib is legacy term (bib = bibliographic record)
            }else{
                return false
            }
        },


        /**
        * try to close the editing window and restore focus to the window that opened it
        *
        * @param isCancel
        @ @param needClose
        */
        closeEditWindow: function(isCancel, needClose) {
            // try to close this window, and restore focus to the window that opened it
            try {
                var topOpener = top.opener;
                top.close();
                if (topOpener) topOpener.focus();
            } catch (e) { }
            s
        },


        /**
        * marks the given module (sidebar tab) as changed (edited).
        * Switches on Save and Save-and-close buttons, turns off close button
        *
        * @param moduleName
        */
        changed: function(moduleName) {
            // mark the given module as changed
            if (! top.HEURIST.edit.modules[moduleName]) return;

            if (top.HEURIST.edit.modules[moduleName].changed) return;    // already changed

            top.HEURIST.edit.modules[moduleName].changed = true;
            var link = document.getElementById(top.HEURIST.edit.modules[moduleName]['link-id']);
            if (link) {
                link.className += ' changed';
                link.title = "Content has been modified";
            }
            
            $("#changed_marker").show();
            

            $("#close-button").hide();
            $("#save-record-buttons").show();
            $("#close-button2").hide();
            $("#save-record-buttons2").show();

        },


        /**
        * mark the given module as unmodified
        *
        * @param moduleName
        */
        unchanged: function(moduleName) {
            // mark the given module as unchanged
            if (! top.HEURIST.edit.modules[moduleName]) return;    // TODO: should raise an exception here ... FIXME

            top.HEURIST.edit.modules[moduleName].changed = false;
            var link = document.getElementById(top.HEURIST.edit.modules[moduleName]['link-id']);
            link.className = link.className.replace(/(^|\s+)changed/, '');
            link.title = "";

            if(!top.HEURIST.edit.is_something_chnaged()){
                $("#save-record-buttons").hide();
                $("#close-button").show();
                $("#save-record-buttons2").hide();
                $("#close-button2").show();
                $("#changed_marker").hide();
            }else{
                $("#changed_marker").show();
            }
        },


        /**
        * put your comment there...
        *
        * @param sid
        * @param recid
        */
        navigate_torecord: function(sid, recid){
            top.HEURIST.edit.save_record(function(){
                location.href = "?db="+HAPI.database+"&sid="+sid+"&recID="+recid;
            });
        },


        /**
        * put your comment there...
        *
        * @returns {Boolean}
        */
        is_something_chnaged: function(){
            var changed = false;
            for (var moduleName in top.HEURIST.edit.modules) {
                if (top.HEURIST.edit.modules[moduleName].changed) {
                    changed = true;
                    break;
                }
            }
            return changed;
        },


        /**
        *
        *
        * @returns {String}
        */
        onbeforeunload: function() {
            var changed = top.HEURIST.edit.is_something_chnaged();
            // FIXME ... we can do better than this
            if (changed) return "You have made changes to the data for this record. If you continue, all changes will be lost.";
        },


        /**
        * put your comment there...
        *
        * @param detailTypeID
        */
        getDetailTypeBasetype: function(detailTypeID) {
            var dtyFieldNamesToDtIndexMap = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex;
            return top.HEURIST.detailTypes.typedefs[detailTypeID].commonFields[dtyFieldNamesToDtIndexMap['dty_Type']];
        },


        /**
        * put your comment there...
        *
        * @param rectypeID
        *
        * @returns {Object}
        */
        getNonRecDetailTypedefs: function(rectypeID) {
            var non_recDTs = {};
            var rfrs = top.HEURIST.rectypes.typedefs[rectypeID].dtFields;
            for (var dt_id in top.HEURIST.detailTypes.typedefs) {
                if (dt_id == "commomFieldNames" || dt_id == "fieldNamesToIndex") continue;
                var skip = false;
                for (var dtID in rfrs) {
                    if (dtID == dt_id) skip = true;
                }
                if (skip) continue;
                non_recDTs[dt_id] = top.HEURIST.detailTypes.typedefs[dt_id];
            }
            return non_recDTs;
        },


        /**
        * try to move focus to the first textbox on the page that will accept focus
        *
        */
        focusFirstElement: function() {
            var elts = document.forms[0].elements;
            for (var i=0; i < elts.length; ++i) {
                if (elts[i].type == 'text') {
                    try {
                        elts[i].focus();
                        return;
                    } catch (e) { }
                }
            }
        },


        allInputs: [],


        /**
        * creates an undeclared input separator on the fly
        *
        * @param label - the label for the separator
        * @param container -DOM element to attach this separator
        */
        createSeparator: function(label,container) {
            if(top && top.HEURIST){
                var dt = top.HEURIST.edit.createFakeDetailType("fakeSep",label,"separator",label,null,null,null)
                var rfr = top.HEURIST.edit.createFakeFieldRequirement(dt,null,null,null,null,1);

                var newSeparator = new top.HEURIST.edit.inputs.BibDetailSeparator("sep1", dt, rfr, [], container);
                if (newSeparator){
                    top.HEURIST.edit.allInputs.push(newSeparator);
                }
                return newSeparator;
            }else{
                return null;
            }
        },


        /**
        * put your comment there...
        *
        * @param dt
        * @param rstDisplayName
        * @param rstDisplayHelpText
        * @param rstDefaultValue
        * @param rstRequirementType
        * @param rstMaxValues
        * @param rstTermIDs
        * @param rstPtrRectypeIDs
        * @param rstTermNonSelectableIDs
        *
        * @returns {Array}
        */
        createFakeFieldRequirement:
        function(dt,rstDisplayName,rstDisplayHelpText,rstDefaultValue,rstRequirementType,rstMaxValues,rstTermIDs,rstPtrRectypeIDs,rstTermNonSelectableIDs) {
            var l = top.HEURIST.rectypes.typedefs.dtFieldNames.length;
            var i;
            var dtyFieldNamesIndexMap = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex;
            var fieldIndexMap = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex;
            var ffr = [];
            for (i=0; i<l; i++){
                ffr.push("");
            }
            ffr[fieldIndexMap['rst_DisplayName']] = (rstDisplayName ? rstDisplayName : (dt ? dt[dtyFieldNamesIndexMap['dty_Name']]:"fake Field"));
            ffr[fieldIndexMap['dty_FieldSetRectypeID']] = dt?dt[dtyFieldNamesIndexMap['dty_FieldSetRectypeID']] : 0;
            ffr[fieldIndexMap['dty_TermIDTreeNonSelectableIDs']] = (dt?dt[dtyFieldNamesIndexMap['dty_TermIDTreeNonSelectableIDs']]:"");
            ffr[fieldIndexMap['rst_TermIDTreeNonSelectableIDs']] =
            rstTermNonSelectableIDs ? rstTermNonSelectableIDs : (dt?dt[dtyFieldNamesIndexMap['dty_TermIDTreeNonSelectableIDs']]:"");
            ffr[fieldIndexMap['rst_MaxValues']] = rstMaxValues ? rstMaxValues : 1;
            ffr[fieldIndexMap['rst_MinValues']] = 0;
            ffr[fieldIndexMap['rst_CalcFunctionID']] = null;
            ffr[fieldIndexMap['rst_DefaultValue']] = rstDefaultValue ? rstDefaultValue : null;
            ffr[fieldIndexMap['rst_DisplayDetailTypeGroupID']] = (dt?dt[dtyFieldNamesIndexMap['dty_DetailTypeGroupID']]:"");
            ffr[fieldIndexMap['rst_DisplayExtendedDescription']] = (dt?dt[dtyFieldNamesIndexMap['dty_ExtendedDescription']]:"");
            ffr[fieldIndexMap['rst_DisplayHelpText']] = rstDisplayHelpText ? rstDisplayHelpText :(dt?dt[dtyFieldNamesIndexMap['dty_HelpText']]:"");
            ffr[fieldIndexMap['rst_DisplayOrder']] = 999;
            ffr[fieldIndexMap['rst_DisplayWidth']] = 50;
            ffr[fieldIndexMap['rst_FilteredJsonTermIDTree']] = rstTermIDs ? rstTermIDs : (dt?dt[dtyFieldNamesIndexMap['dty_JsonTermIDTree']]:"");
            ffr[fieldIndexMap['rst_LocallyModified']] = 0;
            ffr[fieldIndexMap['rst_Modified']] = 0;
            ffr[fieldIndexMap['rst_NonOwnerVisibility']] = (dt?dt[dtyFieldNamesIndexMap['dty_NonOwnerVisibility']]:"viewable");
            ffr[fieldIndexMap['rst_OrderForThumbnailGeneration']] = 0;
            ffr[fieldIndexMap['rst_OriginatingDBID']] = 0;
            ffr[fieldIndexMap['rst_PtrFilteredIDs']] = rstPtrRectypeIDs ? rstPtrRectypeIDs : (dt?dt[dtyFieldNamesIndexMap['dty_PtrTargetRectypeIDs']]:"");
            ffr[fieldIndexMap['rst_RecordMatchOrder']] = 0;
            ffr[fieldIndexMap['rst_RequirementType']] = rstRequirementType ? rstRequirementType :'optional';
            ffr[fieldIndexMap['rst_Status']] = (dt?dt[dtyFieldNamesIndexMap['dty_Status']]:"open");
            return ffr;
        },


        /**
        * put your comment there...
        *
        * @param dtyID
        * @param dtyName
        * @param dtyType
        * @param dtyHelpText
        * @param dtyTermIDs
        * @param dtyTermNonSelectableIDs
        * @param dtyPtrRectypeIDs
        *
        * @returns {Array}
        */
        createFakeDetailType: function(dtyID,dtyName,dtyType,dtyHelpText,dtyTermIDs,dtyTermNonSelectableIDs,dtyPtrRectypeIDs) {
            var l = top.HEURIST.detailTypes.typedefs.commonFieldNames.length;
            var i;
            var fieldIndexMap = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex;
            var fdt = [];
            for (i=0; i<l; i++){
                fdt.push("");
            }
            fdt[fieldIndexMap['dty_ID']] = dtyID ? dtyID : 0;
            fdt[fieldIndexMap['dty_Name']] = dtyName?dtyName : "";
            fdt[fieldIndexMap['dty_Type']] = dtyType ? dtyType : "freetext";
            fdt[fieldIndexMap['dty_HelpText']] = dtyHelpText ? dtyHelpText :"";
            fdt[fieldIndexMap['dty_Status']] = "open";
            fdt[fieldIndexMap['dty_OriginatingDBID']] = 0;
            fdt[fieldIndexMap['dty_DetailTypeGroupID']] = 1;
            fdt[fieldIndexMap['dty_OrderInGroup']] = 999;
            fdt[fieldIndexMap['dty_JsonTermIDTree']] = dtyTermIDs ? dtyTermIDs : "";
            fdt[fieldIndexMap['dty_TermIDTreeNonSelectableIDs']] = dtyTermNonSelectableIDs ? dtyTermNonSelectableIDs : "";
            fdt[fieldIndexMap['dty_PtrTargetRectypeIDs']] = dtyPtrRectypeIDs ? dtyPtrRectypeIDs : "";
            fdt[fieldIndexMap['dty_FieldSetRectypeID']] = 0;
            fdt[fieldIndexMap['dty_ShowInLists']] = 0;
            fdt[fieldIndexMap['dty_NonOwnerVisibility']] = "viewable";
            fdt[fieldIndexMap['dty_Modified']] = 0;
            fdt[fieldIndexMap['dty_LocallyModified']] = 0;
            return fdt;
        },


        /**
        * put your comment there...
        *
        * @param recID
        * @param detailTypeID
        * @param rectypeID
        * @param fieldValues
        * @param container
        * @param stylename_prefix
        *
        * @returns {top.HEURIST.edit.inputs.BibDetailFreetextInput}
        */
        createInput: function(recID, detailTypeID, rectypeID, fieldValues, container, stylename_prefix) {
            // Get Detail Type info
            // 0,"dty_Name" 1,"dty_ExtendedDescription" 2,"dty_Type" 3,"dty_OrderInGroup" 4,"dty_HelpText" 5,"dty_ShowInLists"
            // 6,"dty_Status" 7,"dty_DetailTypeGroupID" 8,"dty_FieldSetRectypeID" 9,"dty_JsonTermIDTree"
            // 10,"dty_TermIDTreeNonSelectableIDs" 11,"dty_PtrTargetRectypeIDs" 12,"dty_ID"
            var dt = top.HEURIST.detailTypes.typedefs[detailTypeID]['commonFields'];
            var dtyFieldNamesIndexMap = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex;
            var rfr = null;
            if (rectypeID) {
                rfr = top.HEURIST.rectypes.typedefs[rectypeID]['dtFields'][detailTypeID];
            }
            if (!rfr) {
                rfr = top.HEURIST.edit.createFakeFieldRequirement(dt,null,null,null,null,(fieldValues.length?fieldValues.length:null));
            }

            var newInput;
            switch (dt[dtyFieldNamesIndexMap['dty_Type']]) {
                case "freetext":
                    newInput = new top.HEURIST.edit.inputs.BibDetailFreetextInput(recID, dt, rfr, fieldValues, container);
                    break;
                case "blocktext":
                    newInput = new top.HEURIST.edit.inputs.BibDetailBlocktextInput(recID, dt, rfr, fieldValues, container);
                    break;
                case "date":
                    newInput = new top.HEURIST.edit.inputs.BibDetailTemporalInput(recID, dt, rfr, fieldValues, container);
                    break;
                case "resource":
                    newInput = new top.HEURIST.edit.inputs.BibDetailResourceInput(recID, dt, rfr, fieldValues, container, stylename_prefix);
                    break;
                case "float":
                    newInput = new top.HEURIST.edit.inputs.BibDetailFloatInput(recID, dt, rfr, fieldValues, container);
                    break;
                case "relationtype":
                case "enum":
                    newInput = new top.HEURIST.edit.inputs.BibDetailDropdownInput(recID, dt, rfr, fieldValues, container, stylename_prefix);
                    break;
                case "file":
                    newInput = new top.HEURIST.edit.inputs.BibDetailURLincludeInput(recID, dt, rfr, fieldValues, container);
                    //newInput = new top.HEURIST.edit.inputs.BibDetailFileInput(recID, dt, rfr, fieldValues, container);
                    break;
                case "geo":
                    newInput = new top.HEURIST.edit.inputs.BibDetailGeographicInput(recID, dt, rfr, fieldValues, container);
                    break;
                case "separator": // note we make sure that values are ignored for a separator as it is not an input
                    newInput = new top.HEURIST.edit.inputs.BibDetailSeparator(recID, dt, rfr, [], container);
                    break;
                case "relmarker": // note we make sure that values are ignored for a relmarker as it gets it's data from the record's related array
                    if (!recID){
                        return null; // only create relmarkers for editing an existing record.
                    }
                    newInput = new top.HEURIST.edit.inputs.BibDetailRelationMarker(recID, dt, rfr, [], container);
                    break;
                // Note: The following types can no longer be created, but are incldued here for backward compatibility
                case "integer":
                    newInput = new top.HEURIST.edit.inputs.BibDetailIntegerInput(recID, dt, rfr, fieldValues, container);
                    break;
                case "year":
                    newInput = new top.HEURIST.edit.inputs.BibDetailYearInput(recID, dt, rfr, fieldValues, container);
                    break;
                case "boolean":
                    newInput = new top.HEURIST.edit.inputs.BibDetailBooleanInput(recID, dt, rfr, fieldValues, container);
                    break;
                case "fieldsetmarker": // note we make sure that values are ignored for a fieldsetmarker as it is a container for a set of details
                    newInput = new top.HEURIST.edit.inputs.BibDetailFieldSetMarker(recID, dt, rfr, [], container);
                    break;
                default:
                    //alert("Type " + dt[2] + " not implemented");
                    newInput = new top.HEURIST.edit.inputs.BibDetailUnknownInput(recID, dt, rfr, fieldValues, container);
            }
            if (newInput){
                top.HEURIST.edit.allInputs.push(newInput);
            }
            return newInput;
        },


        /**
        * put your comment there...
        *
        * @param dtyID
        * @param rtID
        */
        getConstrainedRectypeList: function(dtyID,rtID) {    //saw TODO: change this to terms pass in scrRectypeID
            var listConstdRectype = top.HEURIST.rectypes.typedefs[rtID].dtFields[dtyID]
            [top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex['rst_PtrFilteredIDs']];
            for (var rType in top.HEURIST.edit.record.rtConstraints[rdtID]) {    // saw TODO  need to change this to dTypeID for relmarkers
                if (first) {
                    listConstdRectype += "" + rType;
                    first = false;
                }else{
                    listConstdRectype += "," + rType;
                }
            }
            if (!listConstdRectype) {
                listConstdRectype = "0";
            }
            return listConstdRectype;
        },


        /**
        * put your comment there...
        *
        * @param dTypeID
        *
        * @returns {Object}
        */
        getLookupConstraintsList: function(dTypeID) {    //saw TODO: change this to terms pass in scrRectypeID
            var rdtConstrainedLookups = {};
            var dtRelType = (top.HEURIST.magicNumbers && top.HEURIST.magicNumbers['DT_RELATION_TYPE']? '' + top.HEURIST.magicNumbers['DT_RELATION_TYPE']:'');
            var rdtID = top.HEURIST.edit.record.rtConstraints[dTypeID] ? dTypeID : dtRelType;
            for (var rType in top.HEURIST.edit.record.rtConstraints[rdtID]) {
                var dtRelConstForRecType = top.HEURIST.edit.record.rtConstraints[rdtID][rType];
                if (!dtRelConstForRecType) continue;
                for (var i = 0; i<dtRelConstForRecType.length; i++) {
                    var list = dtRelConstForRecType[i]['trm_ids'];
                    if (list) {
                        list = list.split(",");
                        for (var j = 0; j < list.length; j++) {
                            rdtConstrainedLookups[list[j]] = '';
                        }
                    } else if (dtRelConstForRecType[i]['vcb_ID']) {    // get all the rel lookups for this vocabulary
                        var ontRelations = top.HEURIST.vocabTermLookup[dtRelType][dtRelConstForRecType[i]['vcb_ID']];
                        for (var j = 0; j < ontRelations.length; j++) {
                            rdtConstrainedLookups[ontRelations[j][0]] = '';
                        }
                    }
                }
            }
            empty = true;
            for (var test in rdtConstrainedLookups) {
                empty = false;
                break;
            }
            if (empty) {
                rdtConstrainedLookups = null;
            }
            return rdtConstrainedLookups;
        },



        /**
        * TODO: create snapshot for bookmark
        */


        /**
        * Upload and create thumbnail for a file specified at a URL
        *
        * @param fileInput
        */
        uploadURL: function(fileInput) {
            //get URL.  ARTEM: WARNING! We assume that first element in allInputs is BibURLInput
            var sURL = top.HEURIST.edit.allInputs[0].inputs[0].value;

            if (top.HEURIST.util.isempty(sURL)){ // (typeof sURL==="undefined") || (sURL===null) || (sURL==="") || (sURL==="null") ){
                alert('Please specify URL first');
                return;
            }
            if (sURL.indexOf('https:')==0){
                alert('Sorry, https protocol is not supported for thumbnail generation');
                return;
            }

            top.HEURIST.util.startLoadingPopup();

            var thisRef = this;
            var element = fileInput.parentNode;
            //call thumbnail maker
            top.HEURIST.util.getJsonData(top.HEURIST.basePath + "records/files/saveURLasFile.php?url=" + sURL + "&db=" + HAPI.database,
                function(vals) {
                    top.HEURIST.edit.fileInputURLsaved.call(thisRef, element, vals);
            });

        },


        /**
        * callback function - on completion of download from a URL and saving it as file
        *
        * @param element
        * @param fileDetails
        */
        fileInputURLsaved: function(element, fileDetails) {

            top.HEURIST.util.finishLoadingPopup();
            top.HEURIST.util.sendCoverallToBack();

            var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;

            if (fileDetails.error) {
                // There was an error!  Display it.
                element.input.replaceInput(element);
                //alert(fileDetails.error);

            } else {
                // translate the HFile object back into something we can use here
                // update the BibDetailFileInput to show the file
                element.input.replaceInput(element, fileDetails );
                if (windowRef.changed) windowRef.changed();
            }
        },


        /**
        * Note: starts uploading file as soon as user has browsed the file
        */

        /**
        * Upload a local file to the database file directory
        *
        * @param fileInput
        */
        uploadFileInput: function(fileInput) {
            var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;

            if (fileInput.value == "") return;
            if (! windowRef.HEURIST.uploadsDiv  ||  ! this.document.getElementById("uploads")) {
                var uploadsDiv = windowRef.HEURIST.uploadsDiv = this.document.body.appendChild(this.document.createElement("div"));
                uploadsDiv.id = "uploads";
            }

            var statusDiv = this.document.createElement("div");
            statusDiv.className = "upload-status";
            statusDiv.appendChild(this.document.createTextNode("Uploading file ..."));
            windowRef.HEURIST.uploadsDiv.appendChild(statusDiv);

            var progressBar = this.document.createElement("div");
            progressBar.className = "progress-bar";
            var progressDiv = progressBar.appendChild(this.document.createElement("div"));
            statusDiv.appendChild(progressBar);

            var element = fileInput.parentNode;
            var inProgressSpan = this.document.createElement("span");
            inProgressSpan.className = "in-progress";
            inProgressSpan.appendChild(this.document.createTextNode("Uploading file ..."));
            element.appendChild(inProgressSpan);

            var thisRef = this;
            var saver = new HSaver(
                function(i,f) {
                    top.HEURIST.edit.fileInputUploaded.call(thisRef, element, uploadsDiv, { file: f });
                },
                function(i,e) {
                    top.HEURIST.edit.fileInputUploaded.call(thisRef, element, uploadsDiv, { error: e, origName: i.value.replace(/.*[\/\\]/, "") });
                }
            );
            HeuristScholarDB.saveFile(fileInput,
                saver,
                function(fileInput, bytesUploaded, bytesTotal) {
                    if (bytesUploaded  &&  bytesTotal) {
                        var pc = Math.min(Math.round(bytesUploaded / bytesTotal * 100), 100);
                        if (! progressDiv.style.width  ||  parseInt(progressDiv.style.width) < pc) progressDiv.style.paddingLeft = pc + "%";
                    }
            });
        },


        /**
        * callback function - on completion of file upload
        *
        * @param element
        * @param uploadsDiv
        * @param fileDetails
        */
        fileInputUploaded: function(element, uploadsDiv, fileDetails) {
            var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;


            var closeButton = this.document.createElement("div");
            closeButton.className = "close-button";
            closeButton.onclick = function() { uploadsDiv.parentNode.removeChild(uploadsDiv); };

            uploadsDiv.innerHTML = "";
            uploadsDiv.appendChild(closeButton);

            if (fileDetails.error) {

                // There was an error!  Display it.
                element.input.replaceInput(element);
                uploadsDiv.className = "error";
                var b = uploadsDiv.appendChild(this.document.createElement("b"));
                b.appendChild(this.document.createTextNode(fileDetails.origName));
                b.title = fileDetails.origName;
                uploadsDiv.style.padding = "5px";
                uploadsDiv.appendChild(this.document.createElement("br"));
                uploadsDiv.appendChild(this.document.createTextNode(fileDetails.error));
            } else {
                closeButton.style.display = "none";
                // translate the HFile object back into something we can use here
                var fileObj = {
                    id: fileDetails.file.getID(),
                    origName: fileDetails.file.getOriginalName(),
                    URL: fileDetails.file.getURL(),
                    fileSize: fileDetails.file.getSize(),
                    ext: fileDetails.file.getType()  //extension
                };

                var b = uploadsDiv.appendChild(this.document.createElement("b"));
                var fname = this.document.createTextNode(fileObj.origName);
                b.appendChild(fname);
                b.title = fileObj.origName;
                uploadsDiv.appendChild(this.document.createElement("br"));
                uploadsDiv.appendChild(this.document.createTextNode("File has been uploaded"));
                //does not work top.HEURIST.util.autosizeContainer2(fname, uploadsDiv, "width");
                setTimeout(function() { uploadsDiv.parentNode.removeChild(uploadsDiv); }, 500);

                // update the BibDetailFileInput to show the file
                element.input.replaceInput(element, { file: fileObj });
                if (windowRef.changed) windowRef.changed();
            }
        },


        /**
        * Create input fields for this record
        *
        * @param recID
        * @param rectypeID
        * @param fieldValues
        * @param container
        *
        * @returns {Array}
        */
        createInputsForRectype: function(recID, rectypeID, fieldValues, container)
        {
            var rfrs = top.HEURIST.rectypes.typedefs[rectypeID].dtFields;
            var rstFieldNamesToRdrIndexMap = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex;

            if (! container.ownerDocument) {
                var elt = container;
                do { elt = elt.parentNode; } while (elt.nodeType != 9 /* DOCUMENT_NODE */);
                var documentRef = elt;
            } else {
                var documentRef = container.ownerDocument;
            }
            var windowRef = documentRef.parentWindow  ||  documentRef.defaultView  ||  this.document._parentWindow;

            var inputs = [];

            var cfi = top.HEURIST.rectypes.typedefs.commonNamesToIndex;

            if(top.HEURIST.rectypes.typedefs[rectypeID].commonFields[cfi.rty_ShowDescriptionOnEditForm]!=="0"){
                // If option switched on, the record type description field is displayed as a contextualisation at top of form
                var e = document.getElementById("rty_description");
                if(e){
                    var cfi = top.HEURIST.rectypes.typedefs.commonNamesToIndex;
                    //removed 2012-09-12 why? <span class=\"recID\">" + top.HEURIST.rectypes.typedefs[rectypeID].commonFields[cfi.rty_Name] + ": </span>"
                    e.innerHTML = "<span>" +
                    top.HEURIST.rectypes.typedefs[rectypeID].commonFields[cfi.rty_Description] +"</span>";
                }
            }

            if(top.HEURIST.rectypes.typedefs[rectypeID].commonFields[cfi.rty_ShowURLOnEditForm]!=="0"){

                var defaultURL = (recID && windowRef.parent.HEURIST.edit.record &&
                    windowRef.parent.HEURIST.edit.record.bibID == recID &&
                    windowRef.parent.HEURIST.edit.record.url)? windowRef.parent.HEURIST.edit.record.url : "";
                var required = (rectypeID == top.HEURIST.magicNumbers['RT_INTERNET_BOOKMARK']);    // URL input is only REQUIRED for internet bookmark
                var URLInput = new top.HEURIST.edit.inputs.BibURLInput(container, defaultURL, required, null, true);
                top.HEURIST.edit.allInputs.push(URLInput);
                inputs.push(URLInput);
            }

            var order = top.HEURIST.rectypes.dtDisplayOrder[rectypeID];
            if(order){
                var i, l = order.length;
                for (i = 0; i < l; ++i) {
                    var dtID = order[i];
                    if (rfrs[dtID][rstFieldNamesToRdrIndexMap['rst_RequirementType']] == 'forbidden') continue;

                    var newInput = top.HEURIST.edit.createInput(recID, dtID, rectypeID, fieldValues[dtID] || [], container);
                    if (newInput) {
                        inputs.push(newInput);
                    }
                }
            }

            return inputs;
        }, // createInputsForRectype


        /**
        * Creates input fields for data which is not part of the record structure definition but may be hanging around
        *
        * @param recID
        * @param rectypeID
        * @param fieldValues
        * @param container
        *
        * @returns {Array}
        */
        createInputsNotForRectype: function(recID, rectypeID, fieldValues, container) {
            var rfrs = top.HEURIST.rectypes.typedefs[rectypeID].dtFields;

            var inputs = [];
            for (var dtID in fieldValues) {
                if (rfrs  &&  rfrs[dtID]) continue;

                var input = top.HEURIST.edit.createInput(recID, dtID, 0, fieldValues[dtID] || [], container);

                inputs.push(input);
            }

            return inputs;
        },


        /**
        * Validates that all required fields have values.
        * Invoked onsubmit form in publicInfoTab
        *
        * @param inputs
        * @param windowRef
        *
        * @returns {Boolean}
        */
        requiredInputsOK: function(inputs, windowRef) {
            // Return true if and only if all required fields have been filled in.
            // Otherwise, display a terse message describing missing fields.

            if (windowRef.HEURIST.uploadsInProgress  &&  windowRef.HEURIST.uploadsInProgress.counter > 0) {
                // can probably FIXME if it becomes an issue ... register an autosave with the upload completion handler
                alert("File uploads are in progress - please wait");
                return false;
            }

            var missingFields = [];
            var firstInput = null;

            //check duplication
            var details = {};
            var duplicatedInputs = [];
            var fi_id = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex.dty_ID;
            for (var i=0; i < inputs.length; ++i) {

                if (typeof(inputs[i].getValues) !== "function") {
                    continue;
                }

                var values = inputs[i].getValues();
                var val = null;
                if(values){
                    for (var j=0; j < values.length; ++j) {
                        if(values[j]){
                            val = null;
                            if(typeof(values[j])=="object"){
                                val = values[j].value;
                            }else{
                                val = values[j];
                            }
                            if(val){
                                var det_id = inputs[i].detailType[fi_id];
                                if(details[det_id]){
                                    //check duplication
                                    if(details[det_id].vals.indexOf(val)<0){
                                        details[det_id].vals.push(val);
                                    }else{
                                        if(duplicatedInputs.indexOf(inputs[i].shortName)<0){
                                            duplicatedInputs.push(inputs[i].shortName);
                                        }
                                    }
                                }else{
                                    details[det_id] = {vals:[val]};
                                }
                            }
                        }
                    }
                }

                if(inputs[i].relManager){
                    var open_rels = inputs[i].relManager.openRelationships;
                    for (var k in open_rels) {
                        if(k && open_rels[k] && (!open_rels[k].isempty()) && open_rels[k].isvalid()==null){
                            if(confirm("There is an unsaved relationship in this record.\n "+
                                "Click 'OK' to ignore this relationship.\n Click 'Cancel' to return to edit form")){
                                //inputs[i].relManager.cancelAllOpen();
                            }else{
                                return false;
                            }
                            break;
                        }
                    }
                }


                if (inputs[i].required !== "required") continue;

                if (! inputs[i].verify()) {
                    // disaster! incomplete input
                    /*
                    var niceName = inputs[i].recFieldRequirements[0].toLowerCase();
                    niceName = niceName.substring(0, 1).toUpperCase() + niceName.substring(1);
                    */
                    missingFields.push(inputs[i].shortName + ": requires " + inputs[i].typeDescription);

                    if (! firstInput) firstInput = inputs[i];
                }
            }//for inputs

            if(duplicatedInputs.length>0){
                alert("There are duplicated values in your fields:<br />" + duplicatedInputs.join("<br />"));
                return false;
            }

            if (missingFields.length == 0) {

                return top.HEURIST.edit.datetimeInputsOK(inputs, windowRef)

            }else if (missingFields.length == 1) {
                //"There was a problem with one of your inputs:<br />" +
                alert(missingFields[0]);
            } else {    // many errors
                //"There were problems with your inputs:<br /> - " +
                alert(missingFields.join("<br />"));
            }

            firstInput.focus();
            return false;
        }, // requiredInputsOK


        /**
        * verifies all date fields for valid input
        * Non-temporal values, ie. standard dates, will be converted to YYYY-MM-DD format
        *
        * invoked from requiredInputsOK
        */
        datetimeInputsOK: function(inputs, windowRef) {

            var missingFields = [];
            var firstInput = null;
            var fi_type = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex.dty_Type;

            for (var i=0; i < inputs.length; ++i) {
                if (inputs[i].detailType && inputs[i].detailType[fi_type] === "date"){
                    if (! inputs[i].verifyTemporal(inputs[i])) {

                        missingFields.push("\"" + inputs[i].shortName + "\""); // wrong date format");
                        if (! firstInput) firstInput = inputs[i];
                    }
                }
            }

            if (missingFields.length == 0) {
                return true;
            }else{
                alert("Incorrectly formatted date-time value for<br /> " + missingFields.join("<br /> - "));
                firstInput.focus();
                return false;
            }
        }, // datetimeInputsOK


        /**
        * Function to create scratchpad which floats on top of other components and can be minimised
        * TODO: scratchpad is very difficult to scale and control, reimplement as draggable righthand panel
        *
        * @param name
        * @param value
        * @param parentElement
        * @param options
        */
        createDraggableTextarea: function(name, value, parentElement, options) {
            var elt = parentElement;
            do { elt = elt.parentNode; } while (elt.nodeType != 9 /* DOCUMENT_NODE */);
            var ownerDocument = elt;

            var positionDiv = ownerDocument.createElement("div");
            positionDiv.expando = true;
            positionDiv.isScratchpad = true;
            positionDiv.className = "draggable";
            if (options  &&  options.style) {
                for (var property in options.style) {
                    if (property !== "width"  &&  property !== "height")
                        positionDiv.style[property] = options.style[property];
                }
            }

            var newTable = positionDiv.appendChild(ownerDocument.createElement("table"));
            newTable.border = 0;
            newTable.cellSpacing = 0;
            newTable.cellPadding = 0;
            var newTbody = newTable.appendChild(ownerDocument.createElement("tbody"));

            var headerTd = newTbody.appendChild(ownerDocument.createElement("tr")).appendChild(ownerDocument.createElement("td"));
            headerTd.className = "header";

            // resize handle
            var resizeDiv = ownerDocument.createElement("div");
            resizeDiv.className = "resize-div";
            resizeDiv.onmousemove = function() { return false; };
            top.HEURIST.registerEvent(resizeDiv, "mousedown", function(e) { return top.HEURIST.util.startResize(e, newTextarea, true); });
            headerTd.appendChild(resizeDiv);

            // minimise button
            var minimiseDiv = ownerDocument.createElement("div");
            minimiseDiv.className = "minimise-div";
            headerTd.appendChild(minimiseDiv);

            // description when not minimised
            var descriptionDiv = document.createElement("div");
            descriptionDiv.className = "description";
            if (options  &&  options.description) {
                descriptionDiv.innerHTML = options.description;
            } else {
                descriptionDiv.innerHTML = "click to drag";
            }
            top.HEURIST.registerEvent(descriptionDiv, "mousedown", function(e) {
                return top.HEURIST.util.startMove(e, positionDiv, true);
            });
            headerTd.appendChild(descriptionDiv);

            // short description when minimised
            var minDescriptionDiv = document.createElement("div");
            minDescriptionDiv.className = "min-description";
            if (options  &&  options.minimisedDescription) {
                minDescriptionDiv.innerHTML = options.minimisedDescription;
            } else {
                minDescriptionDiv.innerHTML = "click to restore";
            }
            headerTd.appendChild(minDescriptionDiv);

            // add minimise / restore functions
            var minimise = function() {
                positionDiv.className += " minimised";
                positionDiv.oldBottom = positionDiv.style.bottom;
                positionDiv.oldRight = positionDiv.style.right;
                positionDiv.oldMinWidth = positionDiv.style.minWidth;
                positionDiv.style.bottom = "";
                positionDiv.style.right = "";
                positionDiv.style.minWidth = "";
                headerTd.removeChild(descriptionDiv);
                top.HEURIST.util.setDisplayPreference("scratchpad", "hide");
            };

            var restore = function() {
                positionDiv.className = positionDiv.className.replace(/ minimised/g, "");
                positionDiv.style.bottom = positionDiv.oldBottom;
                positionDiv.style.right = positionDiv.oldRight;
                positionDiv.style.minWidth = positionDiv.oldMinWidth;
                headerTd.insertBefore(descriptionDiv, minDescriptionDiv);
                top.HEURIST.util.setDisplayPreference("scratchpad", "show");
            };

            top.HEURIST.registerEvent(minimiseDiv, "click", minimise);
            top.HEURIST.registerEvent(minDescriptionDiv, "click", restore);

            if (top.HEURIST.util.getDisplayPreference("scratchpad") == "hide") {
                minimise();
            }

            var textareaTd = newTbody.appendChild(ownerDocument.createElement("tr")).appendChild(ownerDocument.createElement("td"));
            var iframeHack = textareaTd.appendChild(ownerDocument.createElement("iframe"));
            iframeHack.frameBorder = 0;    // cover any dropdowns etc; textarea then goes on top of the iframe
            positionDiv.expando = true;
            positionDiv.iframe = iframeHack;
            var newTextarea = textareaTd.appendChild(ownerDocument.createElement("textarea"));
            newTextarea.name = name;
            newTextarea.value = value;
            newTextarea.className = "in";
            if (options  &&  options.style) {
                if (options.style.width) iframeHack.style.width = newTextarea.style.width = options.style.width;
                if (options.style.height) iframeHack.style.height = newTextarea.style.height = options.style.height;
                if (options.style.minWidth) newTextarea.style.minWidth = options.style.minWidth;
                if (options.style.minHeight) newTextarea.style.minHeight = options.style.minHeight;
            }

            if (options.scratchpad) {
                newTextarea.expando = true;
                newTextarea.isScratchpad = true;
                positionDiv.style.zIndex = 100001;
                newTextarea.id = "notes";
            }

            parentElement.appendChild(positionDiv);

            return newTextarea;
        }, // createDraggableTextarea


        calendarViewer: null,

        /**
        * create a button to pop up standard calendar component
        * see also makeTemporalButton for fuzzy date component
        *
        * @param dateBox
        * @param doc
        */
        makeDateButton: function(dateBox, doc) {
            var buttonElt = doc.createElement("input");
            buttonElt.type = "button";
            buttonElt.title = "Pop up calendar widget to enter year-month-day dates";
            buttonElt.className = "date-button";
            if (dateBox.nextSibling)
                dateBox.parentNode.insertBefore(buttonElt, dateBox.nextSibling);
            else
                dateBox.parentNode.appendChild(buttonElt);

            var callback = function(date) {
                if (date) {
                    if(dateBox.value != date){
                        var windowRef = doc.parentWindow  ||  doc.defaultView  ||  this.document._parentWindow;
                        if (windowRef.changed) windowRef.changed();
                    }

                    dateBox.value = date;
                    dateBox.style.width = '14ex';

                    dateBox.strTemporal = "";
                    dateBox.disabled = false;
                    dateBox.disabledStrictly = false;

                    if(top.HEURIST.util.setDisplayPreference){
                        top.HEURIST.util.setDisplayPreference("record-edit-date", date);
                    }
                    top.HEURIST.edit.calendarViewer.close();
                }
            }

            buttonElt.onclick = function() {
                var date = dateBox.value;
                if(top.HEURIST.util.isempty(date) && top.HEURIST.util.getDisplayPreference){
                    date = top.HEURIST.util.getDisplayPreference("record-edit-date");
                }
                top.HEURIST.edit.calendarViewer.showAt(top.HEURIST.util.getOffset(buttonElt), date, callback); //offsetLeft-120
            }

            return buttonElt;

        }, // makeDateButton


        /**
        * create a button to pop up fuzzy date component
        * see also makeDateButton for standard calendar component
        *
        * @param dateBox
        * @param doc
        */
        makeTemporalButton: function(dateBox, doc) {
            var buttonElt = doc.createElement("input");
            buttonElt.type = "button";
            buttonElt.title = "Pop up widget to enter compound date information (uncertain, fuzzy, radiometric etc.)";
            buttonElt.className = "temporal-button";
            if (dateBox.dateButton)
                dateBox.parentNode.insertBefore(buttonElt, dateBox.dateButton.nextSibling);
            else
                dateBox.parentNode.appendChild(buttonElt);

            function disableCtrls (value) {
                dateBox.disabled = value;
                dateBox.disabledStrictly = value;
                dateBox.dateButton.disabled = value;
                dateBox.dateButton.disabledStrictly = value;
                dateBox.dateButton.style.visibility = value ?"hidden":"visible";
            }

            /* Artem Osmakov 2013? : moved function decodeValue to temporalObjectLibrary.js */

            dateBox.initVal = dateBox.value;
            if (dateBox.value) {
                disableCtrls(isTemporal(dateBox.value));

                if (dateBox.value.search(/\|VER/) != -1){
                    dateBox.strTemporal = dateBox.value;
                }else{
                    dateBox.strTemporal = "";
                }
                dateBox.value = temporalToHumanReadableString(dateBox.value);
            }
            dateBox.style.width = (dateBox.value && dateBox.value.length>14?dateBox.value.length:14)+'ex';

            var popupOptions = {
                callback: function(str) {
                    if(str!==undefined){

                        disableCtrls(isTemporal(str));

                        if( dateBox.strTemporal != str) {
                            if (windowRef.changed) windowRef.changed();
                        }
                        dateBox.strTemporal = str;
                        dateBox.value = temporalToHumanReadableString(str);
                        dateBox.style.width = (dateBox.value && dateBox.value.length>14?dateBox.value.length:14)+'ex';
                        if( dateBox.strTemporal != dateBox.value) {
                            buttonElt.title = "Edit compound date " + dateBox.strTemporal;
                        }
                    }
                    top.HEURIST.util.setHelpDiv(document.getElementById("help-link"),null);
                },
                width: "700",
                height: "500"
            };

            var windowRef = doc.parentWindow  ||  doc.defaultView  ||  this.document._parentWindow;
            buttonElt.onclick = function() {
                var buttonPos = top.HEURIST.getPosition(buttonElt, true);
                popupOptions.x = buttonPos.x + 8 - 380;
                if(popupOptions.x<0){
                    popupOptions.x = 0;
                }
                popupOptions.y = buttonPos.y + 8 - 380;
                if(popupOptions.y<0){
                    popupOptions.y = 0;
                }

                top.HEURIST.util.popupURL(windowRef, top.HEURIST.basePath + "common/html/editTemporalObject.html?"
                    + (dateBox.strTemporal ? dateBox.strTemporal : dateBox.value), popupOptions);
            }

            return buttonElt;
        }, // makeTemporalButton

        addOption: function(document, dropdown, text, value) {
            var newOption = document.createElement("option");
            newOption.value = value;
            newOption.appendChild(document.createTextNode(text));
            return dropdown.appendChild(newOption);
        }

    }; // top.HEURIST.edit

    // -----------------------------------------------------------------------------------------------------------------------


    top.HEURIST.edit.inputs = { };


    /**
    * BibDetailInput input
    *
    * @constructor
    * @return {Object}
    */
    top.HEURIST.edit.inputs.BibDetailInput = function(recID, detailType, recFieldRequirements, fieldValues, parentElement, stylename_prefix) {

        if (arguments.length == 0) return;    // for prototyping
        if((typeof stylename_prefix==="undefined") || (stylename_prefix===null)) { stylename_prefix = "input"; }

        // detailType
        //0,"dty_Name" 1,"dty_ExtendedDescription" 2,"dty_Type" 3,"dty_OrderInGroup" 4,"dty_HelpText" 5,"dty_ShowInLists"
        //6,"dty_Status" 7,"dty_DetailTypeGroupID" 8,"dty_FieldSetRectypeID" 9,"dty_JsonTermIDTree"
        //10,"dty_TermIDTreeNonSelectableIDs" 11,"dty_PtrTargetRectypeIDs" 12,"dty_ID"

        // recFieldRequirements
        //0,"rst_DisplayName" 1,"rst_DisplayHelpText" 2,"rst_DisplayExtendedDescription" 3,"rst_DefaultValue" 4,"rst_RequirementType"
        //5,"rst_MaxValues" 6,"rst_MinValues" 7,"rst_DisplayWidth" 8,"rst_RecordMatchOrder" 9,"rst_DisplayOrder"
        //10,"rst_DisplayDetailTypeGroupID" 11,"rst_FilteredJsonTermIDTree" 12,"rst_PtrFilteredIDs" 13,"rst_TermIDTreeNonSelectableIDs"
        //14,"rst_CalcFunctionID" 15,"rst_Status" 16,"rst_OrderForThumbnailGeneration" 17,"dty_TermIDTreeNonSelectableIDs"
        //18,"dty_FieldSetRectypeID"

        var thisRef = this;
        var rstFieldNamesToRdrIndexMap = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex;
        var dtyFieldNamesToDtIndexMap = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex;

        this.recID = recID;
        this.detailType = detailType;
        this.recFieldRequirements = recFieldRequirements;
        this.parentElement = parentElement;
        var elt = parentElement;
        do { elt = elt.parentNode; } while (elt.nodeType != 9 /* DOCUMENT_NODE */);
        this.document = elt;
        this.shortName = recFieldRequirements[rstFieldNamesToRdrIndexMap['rst_DisplayName']];

        var required = recFieldRequirements[rstFieldNamesToRdrIndexMap['rst_RequirementType']];
        if (required == 'optional') required = "optional";
        else if (required == 'required') required = "required";
            else if (required == 'recommended') required = "recommended";
                else required = "";
        this.required = required;
        var maxValue = recFieldRequirements[rstFieldNamesToRdrIndexMap['rst_MaxValues']];
        this.repeatable = ( Number(maxValue) != 1 );//saw TODO this really needs to check many exist

        this.row = this.document.createElement("div");
        parentElement.appendChild(this.row);
        this.row.className = stylename_prefix+"-row " + required;
        if (this.detailType[3] == "separator") this.row.className = "input-row separator";

        var lbl = this.document.createTextNode(recFieldRequirements[rstFieldNamesToRdrIndexMap['rst_DisplayName']]);
        this.headerCell = this.row.appendChild(this.document.createElement("div"));
        this.headerCell.className = stylename_prefix+"-header-cell";
        this.headerCell.appendChild(lbl);    // rfr_name

        if (this.repeatable) {
            var dupImg = this.headerCell.appendChild(this.document.createElement('img'));
            dupImg.src = top.HEURIST.basePath + "common/images/duplicate.gif";
            dupImg.className = "duplicator";
            dupImg.alt = dupImg.title = "Add another " + recFieldRequirements[rstFieldNamesToRdrIndexMap['rst_DisplayName']] + " value";
            top.HEURIST.registerEvent(dupImg, "click", function() { thisRef.duplicateInput.call(thisRef); } );
        }

        this.inputCell = this.row.appendChild(this.document.createElement("div"));
        this.inputCell.className = stylename_prefix+"-cell";

        this.linkSpan = null;

        // make sure that the promptDiv is the last item in the input cell
        var helpText = recFieldRequirements[rstFieldNamesToRdrIndexMap['rst_DisplayHelpText']];
        this.promptDiv = this.inputCell.appendChild(this.document.createElement("div"));
        this.promptDiv.className = "help prompt";
        var helpPrompt = recFieldRequirements[rstFieldNamesToRdrIndexMap['rst_DisplayHelpText']];
        this.promptDiv.innerHTML = helpPrompt;

        this.inputs = [];
        var defaultValue = (top.HEURIST.edit.isAdditionOfNewRecord()?recFieldRequirements[rstFieldNamesToRdrIndexMap['rst_DefaultValue']]:"");

        if (true || this.repeatable=== true) {    //  saw TODO adjust this code for Cardinality , pass in max number and flag red after max
            for (var i=0; i < fieldValues.length; ++i) {
                var ele = this.addInput( fieldValues[i] )

                if(!this.repeatable && i>0){
                    $(ele).after('<div class="prompt" style="color:red">Repeated value for a single value field - please correct</div>');
                }
                //nonsense typeof fieldValues[i] == "string" ? this.addInput( fieldValues[i]) : this.addInput( fieldValues[i]);
            }
            if (fieldValues.length == 0) {
                this.addInput({"value" :  defaultValue} );    // add default value input make it look like bdvalue without id field
            }
        } else { //not used
            if (fieldValues.length > 0) {
                typeof fieldValues == "string" ? this.addInput( fieldValues) : this.addInput( fieldValues[0]);
            } else {
                this.addInput({"value" : defaultValue});
            }
        }
    }; // top.HEURIST.edit.inputs.BibDetailInput


    top.HEURIST.edit.inputs.BibDetailInput.prototype.focus = function() { this.inputs[0].focus(); };

    top.HEURIST.edit.inputs.BibDetailInput.prototype.setReadonly = function(readonly) {
        if (readonly) {
            this.inputCell.className += " readonly";
            inputElem = this.inputCell.getElementsByClassName("in");
            if (inputElem[0]){
                inputElem = inputElem[0];
                inputElem.setAttribute("disabled","disabled");
            }
        } else {
            this.inputCell.className = this.inputCell.className.replace(/\s*\breadonly\b/, "");
            var inputElem = this.inputCell.getElementsByClassName("in");
            if (inputElem[0]){
                inputElem = inputElem[0];
                if(!inputElem['disabledStrictly']){
                    inputElem.removeAttribute("disabled");
                }
            }
        }
        for (var i=0; i < this.inputs.length; ++i) {
            this.inputs[i].readOnly = readonly;
        }
    }; // top.HEURIST.edit.inputs.BibDetailInput.prototype.setReadonly


    top.HEURIST.edit.inputs.BibDetailInput.prototype.duplicateInput = function() { this.addInput(); };

    // 1. specify the correct name for element - based on record ID and detail type ID
    // 2. put element into inputs array
    // 3.
    top.HEURIST.edit.inputs.BibDetailInput.prototype.addInputHelper = function(bdValue, element) {

        var rstFieldNamesToRdrIndexMap = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex;
        var dtyFieldNamesToDtIndexMap = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex;

        this.elementName = "type:" + this.detailType[dtyFieldNamesToDtIndexMap['dty_ID']];
        element.name = (bdValue && bdValue.id)? (this.elementName + "[bd:" + bdValue.id + "]") : (this.elementName + "[]");

        var helpPrompt = this.recFieldRequirements[rstFieldNamesToRdrIndexMap['rst_DisplayHelpText']];

        element.title = helpPrompt?helpPrompt:this.recFieldRequirements[rstFieldNamesToRdrIndexMap['rst_DisplayName']];
        element.setAttribute("bib-detail-type", this.detailType[dtyFieldNamesToDtIndexMap['dty_ID']]);
        var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;

        if (element.value !== undefined) {    /* if this is an input element, register the onchange event */
            top.HEURIST.registerEvent(element, "change", function() { if (windowRef.changed) windowRef.changed(); });

            /* also, His Nibs doesn't like the fact that onchange doesn't fire until after the user has moved to another field,
            * so ... jeez, I dunno.  onkeypress?
            */
            top.HEURIST.registerEvent(element, "keypress", function() { if (windowRef.changed) windowRef.changed(); });
        }
        if (this.detailType[dtyFieldNamesToDtIndexMap['dty_Type']] === "resource"
            || this.detailType[dtyFieldNamesToDtIndexMap['dty_Type']] === "relmarker") {    // dt_type - pointer or relationship marker
            if (this.detailType[dtyFieldNamesToDtIndexMap['dty_PtrTargetRectypeIDs']]) {    // dt_constrain_rectype
                this.constrainrectype = this.detailType[dtyFieldNamesToDtIndexMap['dty_PtrTargetRectypeIDs']];
                // saw TODO  modify this to validate the list first.
            }
            else    this.constrainrectype = 0;
        }
        if (parseFloat(this.recFieldRequirements[rstFieldNamesToRdrIndexMap['rst_DisplayWidth']]) > 0) {    //if the size is greater than zero
            element.style.width = Math.round(2 + Number(this.recFieldRequirements[rstFieldNamesToRdrIndexMap['rst_DisplayWidth']])) + "ex"; //was *4/3
        }

        element.expando = true;
        element.input = this;
        element.bdID = bdValue? bdValue.id : null;

        this.inputs.push(element);
        this.inputCell.insertBefore(element, this.promptDiv);

        if (top.HEURIST.magicNumbers && (top.HEURIST.magicNumbers['DT_DOI']
            && this.detailType[dtyFieldNamesToDtIndexMap['dty_ID']] === top.HEURIST.magicNumbers['DT_DOI']  ||
            top.HEURIST.magicNumbers['DT_ISBN'] && this.detailType[dtyFieldNamesToDtIndexMap['dty_ID']] === top.HEURIST.magicNumbers['DT_ISBN']  ||
            top.HEURIST.magicNumbers['DT_ISSN'] && this.detailType[dtyFieldNamesToDtIndexMap['dty_ID']] === top.HEURIST.magicNumbers['DT_ISSN'])
            &&  bdValue  &&  bdValue.value) { // DOI, ISBN, ISSN
            this.webLookup = this.document.createElement("a");
            if (this.detailType[dtyFieldNamesToDtIndexMap['dty_ID']] === top.HEURIST.magicNumbers['DT_DOI']) {
                this.webLookup.href = "http://dx.doi.org/" + bdValue.value;
            } else if (this.detailType[dtyFieldNamesToDtIndexMap['dty_ID']] === top.HEURIST.magicNumbers['DT_ISBN']) {
                // this.webLookup.href = "http://www.biblio.com/search.php?keyisbn=" + bdValue.value;    // doesn't work anymore
                // this.webLookup.href = "http://www.biblio.com/isbnmulti.php?isbns=" + encodeURIComponent(bdValue.value) + "&stage=1";    // requires POST
                this.webLookup.href = "http://www.biblio.com/search.php?keyisbn=" + encodeURIComponent(bdValue.value);
            } else if (this.detailType[dtyFieldNamesToDtIndexMap['dty_ID']] === top.HEURIST.magicNumbers['DT_ISSN']) {
                var matches = bdValue.value.match(/(\d{4})-?(\d{3}[\dX])/);
                if (matches) {
                    this.webLookup.href = "http://www.oclc.org/firstsearch/periodicals/results_issn_search.asp"+
                    "?database=%25&fulltext=%22%22&results=paged&PageSize=25&issn1=" + matches[1] + "&issn2=" + matches[2]; // look up ISSN
                    // TODO: Update OCLC lookup for ISSN, ISBN
                }
            }

            if (this.webLookup.href) {
                this.webLookup.target = "_blank";
                var span = this.document.createElement("span");
                span.style.paddingLeft = "20px";
                span.style.lineHeight = "16px";
                span.style.backgroundImage = "url("+top.HEURIST.basePath+"common/images/external_link_16x16.gif)";
                span.style.backgroundRepeat = "no-repeat";
                span.style.backgroundPosition = "center left";
                span.appendChild(this.document.createTextNode("look up"));
                this.webLookup.appendChild(span);
                this.inputCell.insertBefore(this.webLookup, this.promptDiv);
            }
        }

    }; // top.HEURIST.edit.inputs.BibDetailInput.prototype.addInputHelper


    top.HEURIST.edit.inputs.BibDetailInput.prototype.getValue = function(input) { return { value: input.value }; };

    top.HEURIST.edit.inputs.BibDetailInput.prototype.getPrimaryValue = function(input) { return input? input.value : ""; };

    top.HEURIST.edit.inputs.BibDetailInput.prototype.inputOK = function(input) {
        // Return false only if the input doesn't match the regex for this input type
        if (! this.regex) return true;
        if (this.getPrimaryValue &&
            typeof(this.getPrimaryValue(input).match) === "function" &&
            this.getPrimaryValue(input).match(this.regex)) {
            return true;
        }
        return false;
    };


    top.HEURIST.edit.inputs.BibDetailInput.prototype.verify = function() {
        // Return true if at least one of this input's values is okay
        for (var i=0; i < this.inputs.length; ++i) {
            if (this.inputOK(this.inputs[i])) return true;
        }
        return false;
    };


    top.HEURIST.edit.inputs.BibDetailInput.prototype.getValues = function() {
        // Return JS object representing the value(s) of this input
        var values = [];
        for (var i=0; i < this.inputs.length; ++i) {
            if (this.inputOK(this.inputs[i])) {
                var inputValue = this.getPrimaryValue(this.inputs[i]);
                if (this.inputs[i].bdID) inputValue.id = this.inputs[i].bdID;
                values.push(inputValue);
            }
        }
        return values;
    };


    /**
    * BibDetailFreetextInput input
    *
    * @constructor
    * @return {Object}
    */
    top.HEURIST.edit.inputs.BibDetailFreetextInput = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
    top.HEURIST.edit.inputs.BibDetailFreetextInput.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
    top.HEURIST.edit.inputs.BibDetailFreetextInput.prototype.parent = top.HEURIST.edit.inputs.BibDetailInput.prototype;
    top.HEURIST.edit.inputs.BibDetailFreetextInput.prototype.regex = new RegExp("\\S");    // text field is okay if it contains non-whitespace
    top.HEURIST.edit.inputs.BibDetailFreetextInput.prototype.typeDescription = "a text value";
    top.HEURIST.edit.inputs.BibDetailFreetextInput.prototype.addInput = function(bdValue) {
        var newInput = this.document.createElement("input");
        newInput.setAttribute("autocomplete", "off");
        newInput.type = "text";
        newInput.title = "Enter a text string";
        newInput.className = "in";
        newInput.style.display = "block";
        if (bdValue) newInput.value = bdValue.value;
        this.addInputHelper.call(this, bdValue, newInput);
        return newInput;
    };

    /**
    * BibDetailIntegerInput input  Note: at 2014, this is ? simply for backward compatibility
    *
    * @constructor
    * @return {Object}
    */
    top.HEURIST.edit.inputs.BibDetailIntegerInput = function() { top.HEURIST.edit.inputs.BibDetailFreetextInput.apply(this, arguments); };
    top.HEURIST.edit.inputs.BibDetailIntegerInput.prototype = new top.HEURIST.edit.inputs.BibDetailFreetextInput;
    top.HEURIST.edit.inputs.BibDetailIntegerInput.prototype.parent = top.HEURIST.edit.inputs.BibDetailFreetextInput.prototype;
    top.HEURIST.edit.inputs.BibDetailIntegerInput.prototype.typeDescription = "an integer value";
    top.HEURIST.edit.inputs.BibDetailIntegerInput.prototype.regex = new RegExp("^\\s*-?\\d+\\s*$");    // obvious integer regex


    /**
    * BibDetailFloatInput input
    *
    * @constructor
    * @return {Object}
    */
    top.HEURIST.edit.inputs.BibDetailFloatInput = function() {
        top.HEURIST.edit.inputs.BibDetailFreetextInput.apply(this, arguments);

        for (var i=0; i < this.inputs.length; ++i) {

            this.inputs[i].onkeypress = function(event){
                if(event && event.charCode>0 && !event.ctrlKey){
                    var newchar = String.fromCharCode(event.charCode);
                    var oldval = event.target.value;

                    if (newchar=="" || (oldval=="" && (newchar=="-" || newchar=="+")) ){
                        return true;
                    }else{

                        var pos = top.HEURIST.util.getCaretPos(event.target);
                        var newval = oldval.substr(0,pos)+newchar+oldval.substr(pos);

                        var regex = top.HEURIST.edit.inputs.BibDetailFloatInput.prototype.regex;
                        var res = newval.match(regex);
                        return (res!=null);
                    }

                }else{
                    return true;
                }

            };
        }
    }; // top.HEURIST.edit.inputs.BibDetailFloatInput


    top.HEURIST.edit.inputs.BibDetailFloatInput.prototype = new top.HEURIST.edit.inputs.BibDetailFreetextInput;
    top.HEURIST.edit.inputs.BibDetailFloatInput.prototype.parent = top.HEURIST.edit.inputs.BibDetailFreetextInput.prototype;
    top.HEURIST.edit.inputs.BibDetailFloatInput.prototype.typeDescription = "a numeric value";
    top.HEURIST.edit.inputs.BibDetailFloatInput.prototype.regex = new RegExp("^([+/-]?(([0-9]+(\\.)?)|([0-9]*\\.[0-9]+)))$");
    //"^\\s*-?(?:\\d+[.]?|\\d*[.]\\d+(?:[eE]-?\\d+))\\s*$");    // extended float regex


    /**
    * BibDetailYearInput input
    *
    * @constructor
    * @return {Object}
    * @deprecated
    */
    top.HEURIST.edit.inputs.BibDetailYearInput = function() { top.HEURIST.edit.inputs.BibDetailFreetextInput.apply(this, arguments); };
    top.HEURIST.edit.inputs.BibDetailYearInput.prototype = new top.HEURIST.edit.inputs.BibDetailFreetextInput;
    top.HEURIST.edit.inputs.BibDetailYearInput.prototype.parent = top.HEURIST.edit.inputs.BibDetailFreetextInput.prototype;
    top.HEURIST.edit.inputs.BibDetailYearInput.prototype.typeDescription = "a year, or \"in press\"";
    top.HEURIST.edit.inputs.BibDetailYearInput.prototype.regex = new RegExp("^\\s*(?:(?:-|ad\\s*)?\\d+(?:\\s*bce?)?|in\\s+press)\\s*$", "i");


    /**
    * BibDetailDateInput input
    *
    * @constructor
    * @return {Object}
    * @deprecated
    */
    top.HEURIST.edit.inputs.BibDetailDateInput = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };

    top.HEURIST.edit.inputs.BibDetailDateInput.prototype = new top.HEURIST.edit.inputs.BibDetailFreetextInput;

    top.HEURIST.edit.inputs.BibDetailDateInput.prototype.parent = top.HEURIST.edit.inputs.BibDetailFreetextInput.prototype;

    top.HEURIST.edit.inputs.BibDetailDateInput.prototype.addInput = function(bdValue) {
        var newDiv = this.document.createElement("div");
        newDiv.className = "date-div";
        newDiv.expando = true;
        newDiv.style.whiteSpace = "nowrap";

        var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
        var textElt = newDiv.textElt = newDiv.appendChild(this.document.createElement("input"));
        textElt.setAttribute("autocomplete", "off");
        textElt.name = newDiv.name;
        textElt.value = bdValue? bdValue.value : "";
        textElt.className = "in";
        textElt.title = "Enter a standard ISO format date (yyyy or yyyy-mm-dd). You may also enter 'today' or 'now', and negative dates for BCE. ";
        textElt.style.width = newDiv.style.width;
        newDiv.style.width = "";
        //top.HEURIST.registerEvent(textElt, "change", function() { if (windowRef.changed) windowRef.changed(); });
        this.addInputHelper.call(this, textElt.value, textElt);
        
        top.HEURIST.edit.makeDateButton(textElt, this.document);
        return newDiv;
    }; // top.HEURIST.edit.inputs.BibDetailDateInput.prototype.addInput


    top.HEURIST.edit.inputs.BibDetailDateInput.prototype.getPrimaryValue = function(input) { return input? input.textElt.value : ""; };

    top.HEURIST.edit.inputs.BibDetailDateInput.prototype.typeDescription = "a date value";

    top.HEURIST.edit.inputs.BibDetailDateInput.prototype.regex = new RegExp("\\S");


    /**
    * BibDetailTemporalInput input
    *
    * @constructor
    * @return {Object}
    */
    top.HEURIST.edit.inputs.BibDetailTemporalInput = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
    top.HEURIST.edit.inputs.BibDetailTemporalInput.prototype = new top.HEURIST.edit.inputs.BibDetailFreetextInput;
    top.HEURIST.edit.inputs.BibDetailTemporalInput.prototype.parent = top.HEURIST.edit.inputs.BibDetailFreetextInput.prototype;
    top.HEURIST.edit.inputs.BibDetailTemporalInput.prototype.addInput = function(bdValue) {
        var newDiv = this.document.createElement("div");
        newDiv.className = "temporal-div";
        newDiv.expando = true;
        newDiv.style.whiteSpace = "nowrap";
        this.addInputHelper.call(this, bdValue, newDiv);

        var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
        var textElt = newDiv.textElt = newDiv.appendChild(this.document.createElement("input"));
        textElt.setAttribute("autocomplete", "off");
        textElt.name = newDiv.name;
        textElt.value = bdValue? bdValue.value : "";
        textElt.className = "in resource-date";
        textElt.title = "Enter a date or click the icons to the right. Date can be 'now' or 'today', or year only - negative for BCE.";
        textElt.style.width = newDiv.style.width;
        newDiv.style.width = "";
        
        top.HEURIST.registerEvent(textElt, "change", function() {
            textElt.strTemporal = null;
            if (windowRef.changed) windowRef.changed();
        }); // top.HEURIST.edit.inputs.BibDetailTemporalInput.prototype.addInput
        top.HEURIST.registerEvent(textElt, "keypress", function() {
            textElt.strTemporal = null;
            if (windowRef.changed) windowRef.changed();
        }); // top.HEURIST.edit.inputs.BibDetailTemporalInput.prototype.addInput

        // TODO: are these relevant any more?
        //    var isTemporal = /^\|\S\S\S=/.test(textElt.value);        //sw check the beginning of the string for temporal format
        //    var isDate = ( !isTemporal && /\S/.test(textElt.value));    //sw not temporal and has non - white space must be a date
        //    if (!isTemporal) top.HEURIST.edit.makeDateButton(textElt, this.document); //sw

        textElt.dateButton = top.HEURIST.edit.makeDateButton(textElt, this.document);
        top.HEURIST.edit.makeTemporalButton(textElt, this.document); //sw
        return newDiv;
    }; // top.HEURIST.edit.inputs.BibDetailTemporalInput.prototype.addInput


    top.HEURIST.edit.inputs.BibDetailTemporalInput.prototype.getValue = function(input) {
        return input && input.textElt ? (input.textElt.strTemporal ? input.textElt.strTemporal :
            (input.textElt.value ? input.textElt.value : "" )) : "";
    };

    top.HEURIST.edit.inputs.BibDetailTemporalInput.prototype.getPrimaryValue = function(input) { return input? input.textElt.value : ""; };
    top.HEURIST.edit.inputs.BibDetailTemporalInput.prototype.typeDescription = "a compound date value";
    top.HEURIST.edit.inputs.BibDetailTemporalInput.prototype.regex = new RegExp("\\S");
    top.HEURIST.edit.inputs.BibDetailTemporalInput.prototype.verifyTemporal = function(){

        for (var i=0; i < this.inputs.length; ++i) {
            var input = this.inputs[i];

            if(input && input.textElt)
            {
                if(input.textElt.strTemporal){
                    continue; // it is assumed that temporal value, if it is set, is validated
                }else if(input.textElt.value){
                    // validate manually entered date
                    try{
                        var tDate = TDate.parse(input.textElt.value);
                        input.textElt.value = tDate.toString();
                    }catch(e) {
                        return false;
                    }
                }
            }
        }

        return true;
    }; // top.HEURIST.edit.inputs.BibDetailTemporalInput.prototype.verifyTemporal


    /**
    * BibDetailBlocktextInput input
    * Input for text fields
    *
    * @constructor
    * @return {Object}
    */
    top.HEURIST.edit.inputs.BibDetailBlocktextInput = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
    top.HEURIST.edit.inputs.BibDetailBlocktextInput.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
    top.HEURIST.edit.inputs.BibDetailBlocktextInput.prototype.parent = top.HEURIST.edit.inputs.BibDetailInput.prototype;
    top.HEURIST.edit.inputs.BibDetailBlocktextInput.prototype.typeDescription = "a text value";
    top.HEURIST.edit.inputs.BibDetailBlocktextInput.prototype.regex = new RegExp("\\S");
    top.HEURIST.edit.inputs.BibDetailBlocktextInput.prototype.addInput = function(bdValue) {
        var newInput = this.document.createElement("textarea");
        newInput.rows = "3";
        newInput.className = "in";
        newInput.title = "Enter a single or multi-line (memo) text value. Drag corner of box to expand."; //ij
        if (bdValue) newInput.value = bdValue.value;

        this.addInputHelper.call(this, bdValue, newInput);

        return newInput;
    };


    /**
    * BibDetailResourceInput input
    * Input for a record pointer (aka resource) field
    *
    * @constructor
    * @return {Object}
    */
    top.HEURIST.edit.inputs.BibDetailResourceInput = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
    top.HEURIST.edit.inputs.BibDetailResourceInput.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
    top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.parent = top.HEURIST.edit.inputs.BibDetailInput.prototype;
    top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.focus = function() { this.inputs[0].textElt.focus(); };
    top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.regex = new RegExp("^[1-9]\\d*$");
    top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.typeDescription = "a record";
    top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.setReadonly = function(readonly) {
        //    this.parent.setReadonly.call(this, readonly);
        for (var i=0; i < this.inputs.length; ++i) {
            elem = this.inputs[i].textElt;
            if (readonly) {
                elem.setAttribute("disabled","disabled");
            }else{
                elem.removeAttribute("disabled");
            }
        }
    };


    top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.addInput = function(bdValue) {
        var thisRef = this;    // provide input reference for closures

        var newDiv = this.document.createElement("div");
        newDiv.className = bdValue && bdValue.value? "resource-div" : "resource-div empty";
        newDiv.expando = true;
        this.addInputHelper.call(this, bdValue, newDiv);
        newDiv.style.width = "";

        if(this.promptDiv.innerHTML){
            /*var br = this.document.createElement("br");
            br.style.lineHeight = "2px";
            newDiv.parentNode.insertBefore(br, this.promptDiv);*/
        }

        var textElt = newDiv.textElt = newDiv.appendChild(this.document.createElement("input"));
        textElt.type = "text";
        textElt.value = textElt.defaultValue = bdValue && bdValue.title ? bdValue.title : "";
        textElt.title = "Click here to search for a record, or drag-and-drop a search value";
        textElt.setAttribute("autocomplete", "off");
        textElt.className = "resource-title";
        textElt.id = "title_"+(new Date()).getTime();
        textElt.style.width = "" + (parseInt(newDiv.style.width)-5) +"ex";
        textElt.onkeypress = function(e) {
            // refuse non-tab key-input
            if (! e) e = window.event;

            if (! newDiv.readOnly  &&  e.keyCode != 9  &&  ! (e.ctrlKey  ||  e.altKey  ||  e.metaKey)) {
                // invoke popup
                thisRef.chooseResource(newDiv);
                return false;
            }
            else return true;    // allow tab or control/alt etc to do their normal thing (cycle through controls)
        };

        top.HEURIST.util.autoSize(textElt, {});

        top.HEURIST.registerEvent(textElt, "click", function() { if (! newDiv.readOnly) thisRef.chooseResource(newDiv); });
        //top.HEURIST.registerEvent(textElt, "mouseup", function() { if (! newDiv.readOnly) thisRef.handlePossibleDragDrop(thisRef, newDiv); });
        //top.HEURIST.registerEvent(textElt, "mouseover", function() { if (! newDiv.readOnly) thisRef.handlePossibleDragDrop(thisRef, newDiv); });

        var hiddenElt = newDiv.hiddenElt = this.document.createElement("input");
        hiddenElt.name = newDiv.name;
        hiddenElt.value = hiddenElt.defaultValue = bdValue? bdValue.value : "0";
        hiddenElt.type = "hidden";
        newDiv.appendChild(hiddenElt);    // have to do this AFTER the type is set

        var removeImg = newDiv.appendChild(this.document.createElement("img"));
        removeImg.src = top.HEURIST.basePath+"common/images/12x12.gif";
        removeImg.className = "delete-resource";
        removeImg.title = "Remove this record pointer";
        var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
        top.HEURIST.registerEvent(removeImg, "click", function() {
            if (! newDiv.readOnly) {
                thisRef.clearResource(newDiv);
                if (windowRef.changed) windowRef.changed();
            }
        });

        var editImg = newDiv.appendChild(this.document.createElement("img"));
        editImg.src = top.HEURIST.basePath +"common/images/edit-pencil.png";
        editImg.className = "edit-resource";
        editImg.title = "Edit this record (opens in a new tab)";

        top.HEURIST.registerEvent(editImg, "click", function() {
            if( hiddenElt.value && !isNaN(Number(hiddenElt.value)) ){
                window.open(top.HEURIST.basePath +"records/edit/editRecord.html?recID=" + hiddenElt.value + "&caller=" + encodeURIComponent(textElt.id) +
                    (top.HEURIST.database && top.HEURIST.database.name ? "&db="+top.HEURIST.database.name:""))
            }
        });

        // TO DO: We really want this record view popping up in a popup window rather than a new tab
        var ViewRec = newDiv.appendChild(this.document.createElement("img"));
        ViewRec.src = top.HEURIST.basePath +"common/images/magglass_15x14.gif";
        ViewRec.className = "view-resource";
        ViewRec.title = "View the record linked via this pointer field (opens in a new tab)";

        top.HEURIST.registerEvent(ViewRec, "click", function() {
            if( hiddenElt.value && !isNaN(Number(hiddenElt.value)) ){
                
                var url = top.HEURIST.basePath +"records/view/renderRecordData.php?recID=" + hiddenElt.value  +
                        (top.HEURIST.database && top.HEURIST.database.name ? "&db="+top.HEURIST.database.name:"");
                
                if (top.HEURIST  &&  top.HEURIST.util  &&  top.HEURIST.util.popupURL) {
                    
                        var dim = top.HEURIST.util.innerDimensions(window);
                        var options = {height:dim.h*0.9, width:700, 'close-on-blur':true};
                    
                        top.HEURIST.util.popupURL(top, url, options);
                        return false;
                }
            }
        });

        if (window.HEURIST && window.HEURIST.parameters && window.HEURIST.parameters["title"]
            &&  bdValue  &&  bdValue.title  &&  windowRef.parent.frameElement) {
            // we've been given a search string for a record pointer field - pop up the search box
            top.HEURIST.registerEvent(windowRef.parent.frameElement, "heurist-finished-loading-popup", function() {
                thisRef.chooseResource(newDiv, bdValue.title);
            });
        }

        return newDiv;
    }; // top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.addInput


    top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.getPrimaryValue = function(input) { return input? input.hiddenElt.value : ""; };

    top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.chooseResourceAuto = function() {
        this.chooseResource(this.inputs[0]);
    }

    top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.chooseResource = function(element, searchValue) {
        if (this.choosing) return;    // we are already choosing a record pointer (aka resource)!
        this.choosing = true;
        var thisRef = this;

        if (! searchValue) searchValue = element.textElt.value;
        var url = top.HEURIST.basePath+"records/pointer/selectRecordFromSearch.html?q="+encodeURIComponent(searchValue) +
        (top.HEURIST.database && top.HEURIST.database.name ? "&db="+top.HEURIST.database.name:"");
        if (top.HEURIST.edit.record)
            url += "&target_recordtype="+top.HEURIST.edit.record.rectypeID;
        if (element.input.constrainrectype)
            url += "&t="+element.input.constrainrectype;
        top.HEURIST.util.popupURL(window, url, {
            callback: function(bibID, bibTitle) {
                if (bibID) element.input.setResource(element, bibID, bibTitle);
                thisRef.choosing = false;
                setTimeout(function() { element.textElt.focus(); }, 100);
                top.HEURIST.util.setHelpDiv(document.getElementById("help-link"),null);
            },
            height: (window.innerHeight<700?window.innerHeight-40:660)
        } );
    }; // top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.chooseResource


    top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.clearResource = function(element) { this.setResource(element, 0, ""); };

    top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.setResource = function(element, bibID, bibTitle) {
        element.textElt.title = element.textElt.value = element.textElt.defaultValue = bibTitle? bibTitle : "";
        element.hiddenElt.value = element.hiddenElt.defaultValue = bibID? bibID : "0";
        if (bibID) {
            element.className = element.className.replace(/(^|\s+)empty(\s+|$)/g, "");
        } else if (! element.className.match(/(^|\s+)empty(\s+|$)/)) {
            element.className += " empty";
        }
        var maxWidth = this.parentElement.width;
        if(!maxWidth || maxWidth>500){
            maxWidth = 500;
        }
        top.HEURIST.util.autoSize(element.textElt, {maxWidth: maxWidth});
        var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
        if (windowRef.changed) windowRef.changed();
    };


    top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.handlePossibleDragDrop = function(input, element) {
        /*
        * Invoked by the mouseup property on record pointer (aka resource) textboxes.
        * We can't reliably detect a drag-drop action, but this is our best bet:
        * if the mouse is released over the textbox and the value is different from what it *was*,
        * then automatically popup the search-for-resource box.
        */
        if (element.textElt.value != element.textElt.defaultValue  &&  element.textElt.value != "") {
            var searchValue = this.calculateDroppedText(element.textElt.defaultValue, element.textElt.value);

            // pause, then clear search value
            setTimeout(function() { element.textElt.value = element.textElt.defaultValue; }, 1000);

            element.input.chooseResource(element, searchValue);
        }
    }; // top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.handlePossibleDragDrop


    top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.calculateDroppedText = function(oldValue, newValue) {
        // If a value is dropped onto a record pointer field which already has a value,
        // the string may be inserted into the middle of the existing string.
        // Given the old value and the new value we can determine the dropped value.
        if (oldValue == "") return newValue;

        // Compare the values character-by-character to find the longest shared prefix
        for (var i=0; i < oldValue.length; ++i) {
            if (oldValue.charAt(i) != newValue.charAt(i)) break;
        }

        // simple cases:
        if (i == oldValue.length) {
            // the input string was dropped at the end
            return newValue.substring(i);
        }
        else if (i == 0) {
            // the input string was dropped at the start
            return newValue.substring(0, newValue.length-oldValue.length);
        }
        else {
            // If we have ABC becoming ABXYBC,
            // then the dropped string could be XYB or BXY.
            // No way to tell the difference -- we always return the former.
            return newValue.substring(i, i + newValue.length-oldValue.length);
        }
    }; // top.HEURIST.edit.inputs.BibDetailResourceInput.prototype.calculateDroppedText


    /**
    * BibDetailBooleanInput input
    * Note: Boolean fields deprecated in Vsn 3, using enumerated (terms list) fields instead
    *
    * @constructor
    * @return {Object}
    */
    top.HEURIST.edit.inputs.BibDetailBooleanInput = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
    top.HEURIST.edit.inputs.BibDetailBooleanInput.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
    top.HEURIST.edit.inputs.BibDetailBooleanInput.prototype.parent = top.HEURIST.edit.inputs.BibDetailInput.prototype;
    top.HEURIST.edit.inputs.BibDetailBooleanInput.prototype.regex = new RegExp("^(?:true|false)$");
    top.HEURIST.edit.inputs.BibDetailBooleanInput.prototype.typeDescription = "either \"yes\" or \"no\"";

    top.HEURIST.edit.inputs.BibDetailBooleanInput.prototype.addInput = function(bdValue) {
        var newInput = this.document.createElement("select");
        top.HEURIST.edit.addOption(this.document, newInput, "", "");
        top.HEURIST.edit.addOption(this.document, newInput, "yes", "true");
        top.HEURIST.edit.addOption(this.document, newInput, "no", "false");

        if (! bdValue) {
            newInput.selectedIndex = 0;
        } else if (bdValue.value === "no"  ||  bdValue.value === "false") {
            newInput.selectedIndex = 2;
        } else if (bdValue.value) {
            newInput.selectedIndex = 1;
        } else {
            newInput.selectedIndex = 0;
        }

        this.addInputHelper.call(this, bdValue, newInput);
        newInput.style.width = "4em";    // Firefox for Mac workaround -- otherwise dropdown is too narrow (has to be done after inserting into page)
        return newInput;
    }; // top.HEURIST.edit.inputs.BibDetailBooleanInput.prototype.addInput


    /**
    * BibDetailDropdownInput input
    * Used for term list (enum) fields
    *
    * @constructor
    * @return {Object}
    */
    top.HEURIST.edit.inputs.BibDetailDropdownInput = function() {top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments);};
    top.HEURIST.edit.inputs.BibDetailDropdownInput.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
    top.HEURIST.edit.inputs.BibDetailDropdownInput.prototype.parent = top.HEURIST.edit.inputs.BibDetailInput.prototype;
    top.HEURIST.edit.inputs.BibDetailDropdownInput.prototype.getPrimaryValue = function(input) {
        return (input && input['selectedIndex']!=undefined && input.selectedIndex !== -1)? (input.options[input.selectedIndex].value) : "";
    };
    top.HEURIST.edit.inputs.BibDetailDropdownInput.prototype.typeDescription = "a value from the dropdown";
    top.HEURIST.edit.inputs.BibDetailDropdownInput.prototype.regex = new RegExp(".");
    top.HEURIST.edit.inputs.BibDetailDropdownInput.prototype.setReadonly = function(readonly) {
        for (var i=0; i < this.inputs.length; ++i) {
            elem = this.inputs[i];
            if (readonly) {
                elem.setAttribute("disabled","disabled");
            }else{
                elem.removeAttribute("disabled");
            }
        }
    };


    top.HEURIST.edit.inputs.BibDetailDropdownInput.prototype.recreateSelector = function(bdValue, needClear){

        if(needClear){
            //find and remove previous selector
            var parent = this.inputCell,
            i = 0;

            var nodes = parent.childNodes;
            while(i<nodes.length) {
                var ele = nodes[i];
                if(ele.className == "repeat-separator"){
                    parent.removeChild(ele);
                }else{
                    i++;
                }
            }

            for (i = 0; i < this.inputs.length; i++) {
                parent.removeChild(this.inputs[i]); //this.inputs.shift()
            }
            this.inputs = [];
        }

        var rstFieldNamesToRdrIndexMap = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex;
        var dtyFieldNamesToDtIndexMap = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex;

        var sAllTerms = this.recFieldRequirements[rstFieldNamesToRdrIndexMap['rst_FilteredJsonTermIDTree']];
        var sDisTerms = this.recFieldRequirements[rstFieldNamesToRdrIndexMap['rst_TermIDTreeNonSelectableIDs']];
        if(!sDisTerms){
            sDisTerms = this.recFieldRequirements[rstFieldNamesToRdrIndexMap['dty_TermIDTreeNonSelectableIDs']];
        }

        var    allTerms = typeof sAllTerms == "string" ? top.HEURIST.util.expandJsonStructure(sAllTerms) : null;
        if(allTerms==null){
            allTerms = (this.detailType[dtyFieldNamesToDtIndexMap['dty_Type']] == "enum")
            ?top.HEURIST.terms.treesByDomain['enum']
            :top.HEURIST.terms.treesByDomain.relation;
        }
        var    disabledTerms = typeof sDisTerms == "string" ? top.HEURIST.util.expandJsonStructure(sDisTerms) : [];


        var _dtyID = this.detailType[dtyFieldNamesToDtIndexMap['dty_ID']];
        if(_dtyID==top.HEURIST.magicNumbers['DT_RELATION_TYPE']){ //specific behaviour - show all
            allTerms = 0;
            disabledTerms = "";
        }

        var newInput = top.HEURIST.util.createTermSelectExt(allTerms, disabledTerms,
            this.detailType[dtyFieldNamesToDtIndexMap['dty_Type']],
            (bdValue && bdValue.value ? bdValue.value : null), true);

        this.addInputHelper.call(this, bdValue, newInput);
        newInput.style.width = "auto";

        //move span before prompt div
        if(this.linkSpan){
            this.inputCell.removeChild(this.linkSpan);
            this.inputCell.insertBefore(this.linkSpan, this.promptDiv);
        }

        return newInput;
    } // top.HEURIST.edit.inputs.BibDetailDropdownInput.prototype.recreateSelector


    top.HEURIST.edit.inputs.BibDetailDropdownInput.prototype.createSpanLinkTerms = function(bdValue){

        var rstFieldNamesToRdrIndexMap = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex;
        var dtyFieldNamesToDtIndexMap = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex;

        var _dtyID = this.detailType[dtyFieldNamesToDtIndexMap['dty_ID']];
        if(Number(_dtyID)==top.HEURIST.magicNumbers['DT_RELATION_TYPE']) //specific behaviour - do not allow edit
        {
            return;
        }

        var sAllTerms = this.recFieldRequirements[rstFieldNamesToRdrIndexMap['rst_FilteredJsonTermIDTree']];
        var isVocabulary = !isNaN(Number(sAllTerms));

        var urlSpan = this.document.createElement("span");
        //urlSpan.style.paddingLeft = "1em";
        urlSpan.style.color = "blue";
        //        urlSpan.style['float'] = "right";
        urlSpan.style.cursor = "pointer";
        var editImg = urlSpan.appendChild(this.document.createElement("img"));
        var viewRec = urlSpan.appendChild(this.document.createElement("img"));
        editImg.src = top.HEURIST.basePath+"common/images/edit-pencil.png";
        urlSpan.appendChild(editImg);
        urlSpan.appendChild(this.document.createTextNode("edit")); //isVocabulary?"add":"list"));

        urlSpan.thisElement = this;
        urlSpan.bdValue = bdValue;

        //open selectTerms to update detailtype
        urlSpan.onclick = function() {
            var _dtyID = this.thisElement.detailType[dtyFieldNamesToDtIndexMap['dty_ID']];
            var type = this.thisElement.detailType[dtyFieldNamesToDtIndexMap['dty_Type']]; //enum or relation
            var _element = this.thisElement;
            var _bdValue = this.bdValue;
            //var allTerms = top.HEURIST.util.expandJsonStructure(recFieldRequirements[11]);
            //var disTerms = termHeaderList;
            //"&datatype="+type+"&all="+allTerms+"&dis="+disTerms+

            //after updating of terms list we have to re-create the selector
            function onSelecTermsUpdate(editedTermTree, editedDisabledTerms) {
                if(editedTermTree || editedDisabledTerms) {
                    // recreate and replace combobox
                    _element.recFieldRequirements[rstFieldNamesToRdrIndexMap['rst_FilteredJsonTermIDTree']] = editedTermTree;
                    _element.recFieldRequirements[rstFieldNamesToRdrIndexMap['rst_TermIDTreeNonSelectableIDs']] = editedDisabledTerms;

                    _element.recreateSelector(_bdValue, true);

                    /* update hidden fields  TODO: deprecated? do we need this any more?
                    Dom.get("dty_JsonTermIDTree").value = editedTermTree;
                    Dom.get("dty_TermIDTreeNonSelectableIDs").value = editedDisabledTerms;
                    _recreateTermsPreviewSelector(Dom.get("dty_Type").value, editedTermTree, editedDisabledTerms);
                    */
                }
                top.HEURIST.util.setHelpDiv(document.getElementById("help-link"),null);
            } // onSelecTermsUpdate

            var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));

            if(isVocabulary){

                if(type!="enum"){
                    type="relation";
                }

                top.HEURIST.util.popupURL(top, top.HEURIST.basePath +
                    "admin/structure/terms/editTermForm.php?domain="+type+"&parent="+Number(sAllTerms)+"&db="+db,
                    {
                        "close-on-blur": false,
                        "no-resize": true,
                        height: 280,
                        width: 650,
                        callback: function(context) {
                            if(context=="ok") {
                                onSelecTermsUpdate(sAllTerms, "");
                            }
                            top.HEURIST.util.setHelpDiv(document.getElementById("help-link"),null);
                        }
                    }
                );

            }else{
                top.HEURIST.util.popupURL(top, top.HEURIST.basePath +
                    "admin/structure/terms/selectTerms.html?detailTypeID="+_dtyID+"&db="+db+"&mode=editrecord",
                    {//options
                        "close-on-blur": false,
                        "no-resize": true,
                        height: 500,
                        width: 750,
                        callback: onSelecTermsUpdate
                    }
                );
            }
        }; // urlSpan.onclick

        this.inputCell.insertBefore(urlSpan, this.promptDiv);
        //this.inputCell.appendChild(urlSpan);

        this.linkSpan = urlSpan;

    } // top.HEURIST.edit.inputs.BibDetailDropdownInput.prototype.createSpanLinkTerms


    top.HEURIST.edit.inputs.BibDetailDropdownInput.prototype.addInput = function(bdValue) {

        var newInput = this.recreateSelector(bdValue, false);

        if(this.inputs.length>1){
            var br = this.document.createElement("div");
            br.style.height = "3px";
            br.className = "repeat-separator";
            this.inputCell.insertBefore(br, newInput);
            //br = this.document.createElement("br");
            //this.inputCell.insertBefore(br, newInput);
        }

        if(this.inputs.length>1 || !top.HEURIST.is_admin()) {return newInput}  //only one edit link and if admin

        this.createSpanLinkTerms(bdValue);
        return newInput;
    }; //top.HEURIST.edit.inputs.BibDetailDropdownInput.prototype.addInput


    // ------------------------------------------------------------------------------------------------------------------------------------

    /**
    *  FILE UPLOAD input control
    */

    /**
    * BibDetailFileInput - FILE UPLOAD input control
    *
    * @constructor
    * @return {Object}
    */
    top.HEURIST.edit.inputs.BibDetailFileInput = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
    top.HEURIST.edit.inputs.BibDetailFileInput.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
    top.HEURIST.edit.inputs.BibDetailFileInput.prototype.parent = top.HEURIST.edit.inputs.BibDetailInput.prototype;
    top.HEURIST.edit.inputs.BibDetailFileInput.prototype.focus = function() { try { this.inputs[0].valueElt.focus(); } catch(e) { } };
    top.HEURIST.edit.inputs.BibDetailFileInput.prototype.typeDescription = "a file";

    top.HEURIST.edit.inputs.BibDetailFileInput.prototype.setReadonly = function(readonly) {
        this.parent.setReadonly.call(this, readonly);
        for (var i=0; i < this.inputs.length; ++i) {
            var elem;
            if (elem = this.inputs[i].removeImg){
                if (readonly) {
                    elem.setAttribute("disabled","disabled");
                }else{
                    elem.removeAttribute("disabled");
                }
            }
            if (elem = this.inputs[i].fileElt){
                if (readonly) {
                    elem.setAttribute("disabled","disabled");
                }else{
                    elem.removeAttribute("disabled");
                }
            }
            if (elem = this.inputs[i].thumbElt){
                if (readonly) {
                    elem.setAttribute("disabled","disabled");
                }else{
                    elem.removeAttribute("disabled");
                }
            }
        }
    }; // top.HEURIST.edit.inputs.BibDetailFileInput.prototype.setReadonly


    top.HEURIST.edit.inputs.BibDetailFileInput.prototype.addInput = function(bdValue) {
        var newDiv = this.document.createElement("div");
        this.addInputHelper.call(this, bdValue, newDiv);
        this.constructInput(newDiv, bdValue);
        return newDiv;
    };

    top.HEURIST.edit.inputs.BibDetailFileInput.prototype.replaceInput = function(inputDiv, bdValue) {
        inputDiv.innerHTML = "";
        this.constructInput(inputDiv, bdValue);
    };


    top.HEURIST.edit.inputs.BibDetailFileInput.prototype.constructInput = function(inputDiv, bdValue) {
        var thisRef = this;    // for great closure
        var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
        var dtyFieldNamesToDtIndexMap = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex;

        if (bdValue  &&  bdValue.file) {
            // A pre-existing file: just display details and a remove button
            var hiddenElt = inputDiv.hiddenElt = this.document.createElement("input");
            hiddenElt.name = inputDiv.name;
            hiddenElt.value = hiddenElt.defaultValue = (bdValue && bdValue.file)? bdValue.file.id : "0";
            hiddenElt.type = "hidden";
            inputDiv.appendChild(hiddenElt);

            var link = inputDiv.appendChild(this.document.createElement("a"));
            if (bdValue.file.nonce) {
                //not used anymore   @todo - remove
                link.href = top.HEURIST.basePath+"records/files/downloadFile.php/" + /*encodeURIComponent(bdValue.file.origName)*/
                "?ulf_ID=" + encodeURIComponent(bdValue.file.nonce)+
                (top.HEURIST.database && top.HEURIST.database.name ? "&db="+top.HEURIST.database.name:"");
            } else if (bdValue.file.URL) {
                link.href = bdValue.file.URL;
            }
            link.target = "_surf";
            link.onclick = function() { top.open(link.href, "", "width=300,height=200,resizable=yes"); return false; };

            link.appendChild(this.document.createTextNode(bdValue.file.origName));    //saw TODO: add a title to this which is the bdValue.file.description

            var linkImg = link.appendChild(this.document.createElement("img"));
            linkImg.src = top.HEURIST.basePath+"common/images/external_link_16x16.gif";
            linkImg.className = "link-image";

            var fileSizeSpan = inputDiv.appendChild(this.document.createElement("span"));
            fileSizeSpan.className = "file-size";
            fileSizeSpan.appendChild(this.document.createTextNode("[" + bdValue.file.fileSize + "]"));

            var removeImg = inputDiv.appendChild(this.document.createElement("img"));
            removeImg.src = top.HEURIST.basePath+"common/images/12x12.gif";
            removeImg.className = "delete-file";
            removeImg.title = "Remove this file";
            var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
            top.HEURIST.registerEvent(removeImg, "click", function() {
                thisRef.removeFile(inputDiv);
                if (windowRef.changed) windowRef.changed();
            });
            inputDiv.valueElt = hiddenElt;
            inputDiv.removeImg = removeImg;
            inputDiv.link = link.href;
            inputDiv.fileType = bdValue.file.ext;
            inputDiv.filedata = bdValue.file;
            inputDiv.className = "file-div";

        } else {
            if (top.HEURIST.browser.isEarlyWebkit) {    // old way of doing things
                var newIframe = this.document.createElement("iframe");
                newIframe.src = top.HEURIST.basePath+"records/files/uploadFileForm.php?recID="
                + windowRef.parent.HEURIST.edit.record.bibID + "&bdt_id=" + this.detailType[dtyFieldNamesToDtIndexMap['dty_ID']];
                newIframe.frameBorder = 0;
                newIframe.style.width = "90%";
                newIframe.style.height = "2em";
                inputDiv.appendChild(newIframe);

                newIframe.submitFunction = function(fileDetails) {
                    inputDiv.input.replaceInput(inputDiv, fileDetails);
                };
            }
            else {
                var fileElt = this.document.createElement("input");
                fileElt.name = inputDiv.name;
                fileElt.className = "file-select";
                fileElt.type = "file";
                fileElt.style.height = 26;
                fileElt.style.border = "none !important";

                inputDiv.appendChild(fileElt);

                var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
                // TODO: remove?
                // top.HEURIST.registerEvent(fileElt, "change", function() { if (windowRef.changed) windowRef.changed(); });
                // (nowadays an uploaded file is automatically saved to the relevant record)
                inputDiv.fileElt = fileElt;

                var thisRef = this;
                fileElt.onchange = function() { top.HEURIST.edit.uploadFileInput.call(thisRef, fileElt); };
                inputDiv.className = "file-div empty";

                //additional button for Thumbnail Image - to create web snap shot
                if(top.HEURIST.edit.record && top.HEURIST.edit.record.rectypeID &&
                    Number(top.HEURIST.edit.record.rectypeID) === top.HEURIST.magicNumbers['RT_INTERNET_BOOKMARK']){
                    var thumbElt = this.document.createElement("input");
                    thumbElt.name = inputDiv.name;
                    thumbElt.value = "Web page snapshot";
                    thumbElt.type = "button";
                    thumbElt.title = "Click here to snapshot the web page indicated by the URL and store as the thumbnail field";
                    thumbElt.onclick = function(){top.HEURIST.edit.uploadURL.call(thisRef, fileElt);}
                    inputDiv.appendChild(thumbElt);
                    inputDiv.thumbElt = thumbElt;
                }
            }

            inputDiv.link = "";
            inputDiv.fileType = "";
        }

        if(this.onchange){
            this.onchange(inputDiv);
        }

        // FIXME: change references to RESOURCE to RECORD
        // FIXME: make sure that all changed() calls are invoked (esp. RESOURCES -- chooseResource, deleteResource ...)

    }; // top.HEURIST.edit.inputs.BibDetailFileInput.prototype.constructInput


    top.HEURIST.edit.inputs.BibDetailFileInput.prototype.removeFile = function(input) {
        // Remove the given file ... this involves hiding the hidden field, setting its value to 0, and perhaps adding a new file input
        if (input.hiddenElt) {
            input.hiddenElt.value = "";
            input.style.display = "none";
        } else {
            input.parentNode.removeChild(input);
        }

        for (var i=0; i < this.inputs.length; ++i) {
            if (input === this.inputs[i]) {
                this.inputs.splice(i, 1);    // remove this input from the list
                break;
            }
        }

        if (this.inputs.length == 0) {    // Oh no!  We just deleted the only file input.  Build a new one (with the old bd_id if had one)
            if (input.bdID) {
                this.addInput({ id: input.bdID });
            } else {
                this.addInput();
            }
        }

        // FIXME: window.changed()

    }; //top.HEURIST.edit.inputs.BibDetailFileInput.prototype.removeFile


    top.HEURIST.edit.inputs.BibDetailFileInput.prototype.getFileData = function() {
        for (var i=0; i < this.inputs.length; ++i) {
            if(this.inputs[0].filedata){
                return this.inputs[0].filedata;
            }
        }
        return "";
    };


    top.HEURIST.edit.inputs.BibDetailFileInput.prototype.setFileData = function(_filedata) {
        if(this.inputs.length>0) {
            this.inputs[0].filedata = _filedata;
        }
    }


    top.HEURIST.edit.inputs.BibDetailFileInput.prototype.getPrimaryValue = function(input) { return input? input.valueElt.value : ""; };

    top.HEURIST.edit.inputs.BibDetailFileInput.prototype.regex = new RegExp("\\S");

    /*
    *  END -------------------------------- FILE UPLOAD input control
    */

    // ------------------------------------------------------------------------------------------------------------------------------------



    /**
    * BibDetailGeographicInput input
    *
    * @constructor
    * @return {Object}
    */
    top.HEURIST.edit.inputs.BibDetailGeographicInput = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
    top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
    top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.parent = top.HEURIST.edit.inputs.BibDetailInput.prototype;
    top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.regex =
    new RegExp("^(?:p|c|r|pl|l) (?:point|polygon|linestring)\\(?\\([-0-9.+, ]+?\\)\\)?$", "i");
    top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.getPrimaryValue = function(input) { return input? input.input.value : ""; };
    top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.typeDescription = "a geographic object";

    top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.setReadonly = function(readonly) {
        this.parent.setReadonly.call(this, readonly);
        for (var i=0; i < this.inputs.length; ++i) {
            this.inputs[i].editLink.style.display = readonly? "none" : "";
            this.inputs[i].removeImg.style.display = readonly? "none" : "";
        }

    }; // top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.setReadonly


    top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.geoValueToDescription = function(geo) {
        function R(x) { return Math.round(x*10000000)/10000000 ; }

        var geoSummary;
        var geoType = geo.type.charAt(0).toUpperCase() + geo.type.substring(1);
        if (geo.type == "point") {
            geoSummary = R(geo.x)+",  "+R(geo.y);
        } else {
            geoSummary = "X "+R(geo.minX)+" - "+R(geo.maxX)+"  Y "+R(geo.minY)+" - "+R(geo.maxY);
        }

        return { type: geoType, summary: geoSummary };

    }; // top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.geoValueToDescription


    top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.wktValueToDescription = function(wkt) {
        // parse a well-known-text value and return the standard description (type + summary)
        var matches = wkt.match(/^(p|c|r|pl|l) (?:point|polygon|linestring)\(?\(([-0-9.+, ]+?)\)/i);
        var typeCode = matches[1];

        var pointPairs = matches[2].split(/,/);
        var X = [], Y = [];
        for (var i=0; i < pointPairs.length; ++i) {
            var point = pointPairs[i].split(/\s+/);
            X.push(parseFloat(point[0]));
            Y.push(parseFloat(point[1]));
        }

        if (typeCode == "p") {
            return { type: "Point", summary: X[0].toFixed(5)+", "+Y[0].toFixed(5) };
        }
        else if (typeCode == "l") {
            return { type: "Path", summary: "X,Y ("+ X.shift().toFixed(5)+","+Y.shift().toFixed(5)+") - ("+X.pop().toFixed(5)+","+Y.pop().toFixed(5)+")" };
        }
        else {
            X.sort();
            Y.sort();

            var type = "Unknown";
            if (typeCode == "pl") type = "Polygon";
            else if (typeCode == "r") type = "Rectangle";
                else if (typeCode == "c") type = "Circle";
                    else if (typeCode == "l") type = "Path";

            var minX = X[0];
            var minY = Y[0];
            var maxX = X.pop();
            var maxY = Y.pop();
            return { type: type, summary: "X "+minX.toFixed(5)+","+maxX.toFixed(5)+" Y "+minY.toFixed(5)+","+maxY.toFixed(5) };
        }
    }; // top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.wktValueToDescription


    top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.addInput = function(bdValue) {
        var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
        var thisRef = this;
        var newDiv = this.document.createElement("div");

        newDiv.className = (bdValue && bdValue.geo) ? "geo-div" : "geo-div empty";
        this.addInputHelper.call(this, bdValue, newDiv);

        var input = this.document.createElement("input");
        input.type = "hidden";
        // This is a bit complicated:
        // We don't put in an input if there's already a value,
        // because MySQL says   bd_geo != geomfromtext(astext(bd_geo))   so we would get false deltas.
        // edit/saveRecordDetails.php leaves bib_detail rows alone if they are not mentioned in $_POST.
        // We give it the name (underscore + name), and only give it the proper name if we try to edit the value.
        if (bdValue  &&  bdValue.id  && ! bdValue.geo) {
            // as from removeGeo - we want to save this deletion!
            input.name = newDiv.name;
        } else {
            input.name = "_" + newDiv.name;
        }
        newDiv.appendChild(input);
        newDiv.input = input;

        var geoImg = this.document.createElement("img");
        geoImg.src = top.HEURIST.basePath+"common/images/16x16.gif";
        geoImg.className = "geo-image";
        geoImg.onmouseout = function(e) { mapViewer.hide(); };

        newDiv.geoImg = geoImg;
        newDiv.appendChild(geoImg);

        var descriptionSpan = newDiv.appendChild(this.document.createElement("span"));
        descriptionSpan.className = "geo-description";
        newDiv.descriptionSpan = descriptionSpan;

        var editLink = this.document.createElement("span")
        newDiv.editLink = editLink;
        editLink.className = "geo-edit";
        editLink.onclick = function() {
            if (top.HEURIST.browser.isEarlyWebkit) {
                alert("Geographic objects use Google Maps API, which doesn't work on this browser - please update to a recent version");
                return;
            }

            HAPI.PJ.store("gigitiser_geo_object", input.value, {
                height: 550,
                width: 780,
                callback: function(_, _, response) {

                    var sURL = top.HEURIST.basePath+"records/edit/digitizer/index.html?" + (response.success ? "edit" : encodeURIComponent(input.value))
                    top.HEURIST.util.popupURL(
                        windowRef,
                        sURL,
                        { callback: function(type, value)
                            {
                                thisRef.setGeo(newDiv, value? (type+" "+value) : "");
                                top.HEURIST.util.setHelpDiv(document.getElementById("help-link"),null);
                            }
                        }
                    );
                }
            });

        }; // editLink.onclick

        var editSpan = newDiv.appendChild(this.document.createElement("span"));
        editSpan.appendChild(editLink);

        var removeImg = newDiv.appendChild(this.document.createElement("img"));
        removeImg.src = top.HEURIST.basePath+"common/images/12x12.gif";
        newDiv.removeImg = removeImg;
        removeImg.className = "delete-geo";
        removeImg.title = "Remove this geographic object";
        top.HEURIST.registerEvent(removeImg, "click", function() {
            thisRef.removeGeo(newDiv);
            if (windowRef.changed) windowRef.changed();
        });

        if (bdValue && bdValue.geo) {
            input.value = bdValue.geo.value;

            geoImg.onmouseover= function(e) {
                //mapViewer.showAt(e, bdValue.geo.value); //dynamic google map (use digitizer.js)
                mapViewer.showAtStatic(e, top.HEURIST.edit.record.bibID, input.value); //static google map (use showMapUrl.php) see mapViewer.js
            };

            var description = this.geoValueToDescription(bdValue.geo);
            descriptionSpan.appendChild(this.document.createElement("b")).appendChild(this.document.createTextNode(" " + description.type));
            descriptionSpan.appendChild(this.document.createTextNode(" " + description.summary + " "));

            editLink.innerHTML = "edit";

        } else {
            descriptionSpan.innerHTML = " ";
            editLink.innerHTML = "add";
        }

        newDiv.style.width = "auto";

        return newDiv;
    }; //top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.addInput


    top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.setGeo = function(element, value) {
        if (! value) return; // "cancel"

        var description = this.wktValueToDescription(value); //get human readable string

        element.input.name = element.input.name.replace(/^_/, "");
        element.input.value = value;
        element.descriptionSpan.innerHTML = "";
        element.descriptionSpan.appendChild(this.document.createElement("b")).appendChild(this.document.createTextNode(" " + description.type));
        element.descriptionSpan.appendChild(this.document.createTextNode(" " + description.summary + " "));
        element.geoImg.onmouseover= function(e) {
            //mapViewer.showAt(e, value);  //dynamic
            mapViewer.showAtStatic(e, top.HEURIST.edit.record.bibID, element.input.value ); //static google map (use showMapUrl.php) see mapViewer.js
        };

        element.editLink.innerHTML = "edit";
        element.className = "geo-div";    // not empty

        var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
        if (windowRef.changed) windowRef.changed();

    }; // top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.setGeo


    top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.removeGeo = function(input) {
        input.parentNode.removeChild(input);

        for (var i=0; i < this.inputs.length; ++i) {
            if (input === this.inputs[i]) {
                this.inputs.splice(i, 1);    // remove this input from the list
                break;
            }
        }

        if (input.bdID) {
            this.addInput({ id: input.bdID });
        } else {
            this.addInput();
        }

    }; // top.HEURIST.edit.inputs.BibDetailGeographicInput.prototype.removeGeo


    /**
    * BibDetailUnknownInput input
    *
    * @constructor
    * @return {Object}
    */
    top.HEURIST.edit.inputs.BibDetailUnknownInput = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
    top.HEURIST.edit.inputs.BibDetailUnknownInput.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
    top.HEURIST.edit.inputs.BibDetailUnknownInput.prototype.typeDescription = "a value of unknown type";

    top.HEURIST.edit.inputs.BibDetailUnknownInput.prototype.addInput = function(bdValue) {
        var dtyFieldNamesToDtIndexMap = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex;

        var newInput = this.document.createElement("div");
        newInput.appendChild(this.document.createTextNode("Input type \"" + this.detailType[dtyFieldNamesToDtIndexMap['dty_Type']] + "\" not implemented"));
        this.addInputHelper.call(this, bdValue, newInput);
        return newInput;
    }; // top.HEURIST.edit.inputs.BibDetailUnknownInput.prototype.addInput


    /**
    * BibDetailSeparator input
    *
    * @constructor
    * @return {Object}
    */
    top.HEURIST.edit.inputs.BibDetailSeparator = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
    top.HEURIST.edit.inputs.BibDetailSeparator.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
    top.HEURIST.edit.inputs.BibDetailSeparator.prototype.typeDescription = "record section heading / separator";

    top.HEURIST.edit.inputs.BibDetailSeparator.prototype.addInput = function(bdValue) {
        var rstFieldNamesToRdrIndexMap = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex;

        var newInput = this.document.createElement("div");
        //newInput.style.border = '1px solid grey';
        //newInput.style.width = '105ex';    // this will effectively override the sizing control in the record definition, may have to remove
        this.addInputHelper.call(this, bdValue, newInput);
        if (this.promptDiv){
            this.promptDiv.className = "";
            this.promptDiv.style.display = "none";
            newInput.title = this.recFieldRequirements[rstFieldNamesToRdrIndexMap['rst_DisplayHelpText']];
        }
        return newInput;
    }; // top.HEURIST.edit.inputs.BibDetailSeparator.prototype.addInput


    /**
    * BibDetailRelationMarker input
    *
    * @constructor
    * @return {Object}
    */
    top.HEURIST.edit.inputs.BibDetailRelationMarker = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
    top.HEURIST.edit.inputs.BibDetailRelationMarker.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
    top.HEURIST.edit.inputs.BibDetailRelationMarker.prototype.typeDescription = "record relationship marker";

    /**
    * put your comment there...
    *
    * @member
    * @param {Object} parentElement
    */
    top.HEURIST.edit.inputs.BibDetailRelationMarker.prototype.changeNotification = function(cmd, relID) {
        if (cmd == "delete") {
            var fakeForm = { action: top.HEURIST.basePath+"records/relationships/saveRelationships.php?db="+top.HEURIST.database.name,
                elements: [ { name: "delete[]", value: relID },
                    { name: "recID", value: this.recID } ] };
            var thisRef = this;
            top.HEURIST.util.xhrFormSubmit(fakeForm, function(json) {
                var val = eval(json.responseText);
                if (val && val.byRectype) {
                    top.HEURIST.edit.record.relatedRecords = val;
                }else if(val.error) {
                    alert(" Problem with relationship marker: " + val.error);
                }
            });
        }
        //    this.windowRef.changed();

    }; // top.HEURIST.edit.inputs.BibDetailRelationMarker.prototype.changeNotification


    top.HEURIST.edit.inputs.BibDetailRelationMarker.prototype.getPrimaryValue = function(input) {
        //this.relManager
        return input? input.value : "";
    };


    /**
    * put your comment there...
    *
    * @constructor
    * @param {Object} parentElement
    */
    top.HEURIST.edit.inputs.BibDetailRelationMarker.prototype.duplicateInput = function() {
        //add new relationship
        this.relManager.allowAddNew();

        //this.addInput();

    }; // top.HEURIST.edit.inputs.BibDetailRelationMarker.prototype.duplicateInput


    /**
    * put your comment there...
    *
    * @constructor
    * @param {Object} parentElement
    */
    top.HEURIST.edit.inputs.BibDetailRelationMarker.prototype.addInput = function(bdValue) {
        var dtyFieldNamesToDtIndexMap = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex;

        this.windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
        var newInput = this.document.createElement("div");
        this.addInputHelper.call(this, bdValue, newInput);
        newInput.id = "relations-tbody";
        var relatedRecords = (    this.recID &&
            parent.HEURIST &&
            parent.HEURIST.edit &&
            parent.HEURIST.edit.record &&
            parent.HEURIST.edit.record.bibID == this.recID &&
            parent.HEURIST.edit.record.relatedRecords ? parent.HEURIST.edit.record.relatedRecords : null);
        this.relManager = new top.RelationManager(newInput,top.HEURIST.edit.record, relatedRecords,
            this.detailType[dtyFieldNamesToDtIndexMap['dty_ID']],this.changeNotification, true, false);
        return newInput;
    }; // top.HEURIST.edit.inputs.BibDetailRelationMarker.prototype.addInput


    /**
    * Addition of reminders (email notifications for this record)
    *
    * @constructor
    * @param {Object} parentElement
    */
    top.HEURIST.edit.Reminder = function(parentElement, reminderDetails) {
        var elt = parentElement;
        do { elt = elt.parentNode; } while (elt.nodeType != 9 /* DOCUMENT_NODE */);
        this.document = elt;

        this.reminderID = reminderDetails.id;

        var who;
        if (reminderDetails.user) {
            who = top.HEURIST.allUsers? top.HEURIST.allUsers[reminderDetails.user][1] : top.HEURIST.get_user_name();
        } else if (reminderDetails.group) {
            who = top.HEURIST.workgroups[reminderDetails.group]
            if (who) who = who.name;
        } else if (reminderDetails.email) {
            who = reminderDetails.email;
        } else {
            who = "";
        }

        this.reminderDiv = this.document.createElement("div");
        this.reminderDiv.className = "reminder-div";
        if (reminderDetails.message != "") {
            this.reminderDiv.innerHTML = "<b>"+who+" "+reminderDetails.frequency+"</b> from <b>" + reminderDetails.when + "</b> with message: \"";
            this.reminderDiv.appendChild(this.document.createTextNode(reminderDetails.message + "\""));    // plaintext
        } else {
            this.reminderDiv.innerHTML = "<b>"+who+" "+reminderDetails.frequency+"</b> from <b>" + reminderDetails.when + "</b>";
        }

        var removeImg = this.reminderDiv.appendChild(this.document.createElement("img"));
        removeImg.src = top.HEURIST.basePath+"common/images/cross.png";
        removeImg.title = "Remove this reminder";
        var thisRef = this;
        removeImg.onclick = function() { if (confirm("Remove this reminder?")) thisRef.remove(); };

        parentElement.appendChild(this.reminderDiv);
    }; // top.HEURIST.edit.Reminder


    /**
    * removal of reminders (emailnotifications for this record)
    *
    * @constructor
    * @param {Object} parentElement
    */
    top.HEURIST.edit.Reminder.prototype.remove = function() {
        var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
        var fakeForm = { action: top.HEURIST.basePath+"records/reminders/saveReminder.php",
            elements: [ { name: "rem_ID", value: this.reminderID },
                { name: "recID", value: windowRef.parent.HEURIST.edit.record.bibID },
                { name: "save-mode", value: "delete" } ] };
        var thisRef = this;
        top.HEURIST.util.xhrFormSubmit(fakeForm, function(json) {
            var val = eval(json.responseText);
            if (val == 1) {
                /* deletion was successful */
                thisRef.reminderDiv.parentNode.removeChild(thisRef.reminderDiv);

                /* find the reminder in HEURIST.edit.record ... */
                var reminders = windowRef.parent.HEURIST.edit.record.reminders;
                for (var i=0; i < reminders.length; ++i) {
                    if (reminders[i].id == thisRef.reminderID) {
                        reminders.splice(i, 1);
                        break;
                    }
                }
            }
            else {
                alert(val.error);
            }
        });
    };  // top.HEURIST.edit.Reminder.prototype.remove


    /**
    * Editing reminder (email notification) input for this record, including targets and frequency
    *
    * @constructor
    * @param {Object} parentElement
    */
    top.HEURIST.edit.inputs.ReminderInput = function(parentElement) {
        var elt = parentElement;
        do { elt = elt.parentNode; } while (elt.nodeType != 9 /* DOCUMENT_NODE */);
        this.document = elt;

        var oneWeekFromNow = new Date(new Date().getTime() + 7 * 24 * 60 * 60 * 1000);
        var month = oneWeekFromNow.getMonth()+1;  if (month < 10) month = "0" + month;
        var day = oneWeekFromNow.getDate();  if (day < 10) day = "0" + day;
        oneWeekFromNow = oneWeekFromNow.getFullYear() + "-" + month + "-" + day;

        var reminderDetails = {
            user: top.HEURIST.get_user_id(),
            group: 0,
            email: "",
            when: oneWeekFromNow,
            frequency: "once",
            message: ""
        };
        this.details = reminderDetails;

        var thisRef = this;

        this.fieldset = parentElement.appendChild(this.document.createElement("fieldset"));
        this.fieldset.className = "reminder";
        this.fieldset.appendChild(this.document.createElement("legend")).appendChild(this.document.createTextNode("Add reminder"));

        var p = this.fieldset.appendChild(this.document.createElement("p"));
        p.appendChild(this.document.createTextNode("Set up email reminders about this record"));

        var tbody = this.fieldset.appendChild(this.document.createElement("div"));
        tbody.className = "reminder-table";

        var tr1 = tbody.appendChild(this.document.createElement("div"));
        var tds = [];
        for (var i=0; i < 6; ++i) {
            var td = tr1.appendChild(this.document.createElement("span"));
            td.className = "col-"+(i+1);
            tds.push(td);
        }

        tds[0].appendChild(this.document.createTextNode("Who"));

        var userTextbox = this.userTextbox = tds[1].appendChild(this.document.createElement("input"));
        this.userTextbox.id = "reminder-user";
        this.userTextbox.name = "reminder-user";
        this.userTextbox.className = "in";
        this.userTextbox.setAttribute("prompt", "Type user name here");
        new top.HEURIST.autocomplete.AutoComplete(userTextbox, top.HEURIST.util.userAutofill,
            { multiWord: false, prompt: this.userTextbox.getAttribute("prompt"),
                nonVocabularyCallback: function(value) {
                    if (value) {
                        alert("Unknown user '"+value+"'");
                        this.textbox.currentWordValue = '';
                        this.textbox.value = '';
                    }
                    return true;
        } });

        tds[2].appendChild(this.document.createTextNode("or"));

        this.groupDropdown = tds[3].appendChild(this.document.createElement("select"));
        this.groupDropdown.className = "group-dropdown";
        this.groupDropdown.name = "reminder-group";
        top.HEURIST.edit.addOption(this.document, this.groupDropdown, "Group ...", "");
        this.groupDropdown.selectedIndex = 0;
        var i = 0;
        for (var j in top.HEURIST.user.workgroups) {
            var groupID = top.HEURIST.user.workgroups[j];
            var group = top.HEURIST.workgroups[groupID];
            if (! group) continue;

            top.HEURIST.edit.addOption(this.document, this.groupDropdown, group.name, groupID);
            if (groupID == reminderDetails.group)
                this.groupDropdown.selectedIndex = i;
        }

        tds[4].appendChild(this.document.createTextNode("or email"));

        this.emailTextbox = tds[5].appendChild(this.document.createElement("input"));
        this.emailTextbox.type = "text";
        this.emailTextbox.className = "in";
        this.emailTextbox.name = "reminder-email";
        this.emailTextbox.value = reminderDetails.email;

        var ut = this.userTextbox, gd = this.groupDropdown, et = this.emailTextbox;
        ut.onchange = function() { gd.selectedIndex = 0; et.value = ""; }
        gd.onchange = function() { ut.value = et.value = ""; }
        et.onchange = function() { gd.selectedIndex = 0; ut.value = ""; }

        var tr2 = tbody.appendChild(this.document.createElement("div"));
        var td = tr2.appendChild(this.document.createElement("span"));
        //td.className = "col-1";
        td.appendChild(this.document.createTextNode("When"));
        td.className = "col-1";
        td = tr2.appendChild(this.document.createElement("span"));
        //td.colSpan = 7;

        this.nowRadioButton = this.document.createElement("input");
        this.nowRadioButton.type = "radio";
        this.nowRadioButton.name = "when";
        this.nowRadioButton.checked = true;
        this.nowRadioButton.onclick = function() { thisRef.whenSpan.style.display = "none"; thisRef.saveButton.value = "Send"; };
        td.appendChild(this.nowRadioButton);
        td.appendChild(this.document.createTextNode("Now"));

        this.laterRadioButton = this.document.createElement("input");
        this.laterRadioButton.type = "radio";
        this.laterRadioButton.name = "when";
        //this.laterRadioButton.style.marginLeft = "20px";
        this.laterRadioButton.onclick = function() { thisRef.whenSpan.style.display = ""; thisRef.saveButton.value = "Store" };
        td.appendChild(this.laterRadioButton);
        td.appendChild(this.document.createTextNode("Later / periodic"));

        this.whenSpan = td.appendChild(this.document.createElement("span"));
        this.whenSpan.style.display = "none";

        this.whenTextbox = this.document.createElement("input");
        this.whenTextbox.type = "text";
        this.whenTextbox.className = "in";
        this.whenTextbox.name = "reminder-when";
        //this.whenTextbox.style.width = "90px";
        //this.whenTextbox.style.marginLeft = "10px";
        this.whenTextbox.value = reminderDetails.when;
        this.whenSpan.appendChild(this.whenTextbox);
        this.whenButton = top.HEURIST.edit.makeDateButton(this.whenTextbox, this.document);

        this.frequencyDropdown = this.whenSpan.appendChild(this.document.createElement("select"));
        this.frequencyDropdown.className = "frequency-dropdown";
        this.frequencyDropdown.name = "reminder-frequency";
        top.HEURIST.edit.addOption(this.document, this.frequencyDropdown, "Once only", "once");
        top.HEURIST.edit.addOption(this.document, this.frequencyDropdown, "Annually", "annually");
        top.HEURIST.edit.addOption(this.document, this.frequencyDropdown, "Monthly", "monthly");
        top.HEURIST.edit.addOption(this.document, this.frequencyDropdown, "Weekly", "weekly");
        top.HEURIST.edit.addOption(this.document, this.frequencyDropdown, "Daily", "daily");
        for (var i=0; i < this.frequencyDropdown.options.length; ++i) {
            if (this.frequencyDropdown.options[i].value == reminderDetails.frequency)
                this.frequencyDropdown.selectedIndex = i;
        }

        var tr3 = tbody.appendChild(this.document.createElement("div"));
        td = tr3.appendChild(this.document.createElement("span"));
        td.className = "col-1";
        td.appendChild(this.document.createTextNode("Message"));

        td = tr3.appendChild(this.document.createElement("span"));
        //td.colSpan = 8;

        this.messageBox = td.appendChild(this.document.createElement("textarea"));
        this.messageBox.className = "in";
        this.messageBox.name = "reminder-message";
        this.messageBox.value = reminderDetails.message;
        this.messageBox.style.width = "100%";
        this.messageBox.style.height = "70px";

        var tr4 = tbody.appendChild(this.document.createElement("div"));
        td = tr4.appendChild(this.document.createElement("span"));
        //td = tr4.appendChild(this.document.createElement("span"));
        //td.colSpan = 1;
        //td.style.verticalAlign = "bottom";
        //td.style.textAlign = "right";
        this.saveButton = this.document.createElement("input");
        this.saveButton.type = "button";
        this.saveButton.value = "Send";
        this.saveButton.style.margin = "0 10px 0 0";
        var thisRef = this;
        this.saveButton.onclick = function() { thisRef.save(thisRef.nowRadioButton.checked); };
        td.appendChild(this.saveButton);
        td = tr4.appendChild(this.document.createElement("span"));
        //td.colSpan = 6;
        td.style.textAlign = "left";
        //td.style.verticalAlign = "baseline";
        //td.style.paddingTop = "10px";
        td.appendChild(this.document.createTextNode("You must Send (now) or Set (periodic) your reminder before saving record. " +
            "Periodic reminders are normally sent shortly after midnight (server time) on the reminder day."));

        var bibIDelt = this.document.createElement("input");
        bibIDelt.type = "hidden";
        bibIDelt.name = "recID";
        bibIDelt.value = parent.HEURIST.edit.record.bibID;
        td.appendChild(bibIDelt);
        var addElt = this.document.createElement("input");
        addElt.type = "hidden";
        addElt.name = "save-mode";
        addElt.value = "add";
        td.appendChild(addElt);

    }; // top.HEURIST.edit.inputs.ReminderInput


    /**
    * Check if reminder (email notification) is empty
    *
    * @type Window
    */
    top.HEURIST.edit.inputs.ReminderInput.prototype.isEmpty = function() {
        if (this.messageBox.value.match(/\S/)) return false;
        return true;
    };


    /**
    * Save reminder (email notification) information for this record
    *
    * @type Window
    */
    top.HEURIST.edit.inputs.ReminderInput.prototype.save = function(immediate, auto) {
        // Verify inputs and save form via XHR
        // If "auto" is set, then we will abort silently rather than alert the user

        if ((! this.userTextbox.value  ||  this.userTextbox.value === this.userTextbox.getAttribute("prompt"))  &&
            ! this.groupDropdown.value  &&  ! this.emailTextbox.value) {
            this.userTextbox.focus();
            if (! auto) alert("You must select a user, a group, or an email address");
            return true;
        }

        if (! immediate) {
            if (! this.whenTextbox.value) {
                this.whenTextbox.focus();
                if (! auto) alert("You must choose a date for the reminder");
                return true;
            }else if (new Date((this.whenTextbox.value).replace(/\-/g,"/")) <= Date.now()){
                this.whenTextbox.focus();
                alert("You must choose a future date (beyond today)");
                return false;
            }
        }

        if (immediate) {
            var immediateElt = document.createElement("input");
            immediateElt.type = "hidden";
            immediateElt.name = "mail-now";
            immediateElt.value = "1";
            this.whenTextbox.parentNode.appendChild(immediateElt);
        }

        var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
        var thisRef = this;
        top.HEURIST.util.xhrFormSubmit(this.messageBox.form, function(json) {
            if (auto) return;

            // callback
            var vals = eval(json.responseText);
            if ((! vals) || (vals.error)) {
                alert("Unknown error while saving reminder, server responded:\n" + vals.error);
            } else if (immediate) {
                alert("Email(s) sent");
            } else {
                // Add the reminder to the record ...
                var newReminder = vals.reminder;
                windowRef.parent.HEURIST.edit.record.reminders.push(newReminder);

                // ... remove the previous inputs ...
                thisRef.fieldset.parentNode.removeChild(thisRef.fieldset);

                // ... add in the new reminder ...
                // FIXME: shouldn't use that getbyid
                new top.HEURIST.edit.Reminder(thisRef.document.getElementById("reminders"), newReminder);

                // ... and add a new set of inputs.
                windowRef.reminderInput = new top.HEURIST.edit.inputs.ReminderInput(thisRef.document.getElementById("reminders"));
            }
        });

    }; // top.HEURIST.edit.inputs.ReminderInput.prototype.save


    /**
    * Editing the record level URL (URL set for internet bookmarks and optionally other record types)
    *
    * @type Window
    */
    top.HEURIST.edit.inputs.BibURLInput = function(parentElement, defaultValue, required, displayValue, canEdit) {
        var elt = parentElement;
        do { elt = elt.parentNode; } while (elt.nodeType != 9 );// DOCUMENT_NODE
        this.document = elt;

        this.typeDescription = "URL (internet address)";
        this.shortName = "URL";

        var row = parentElement.appendChild(this.document.createElement("div"));
        if (required) {    // internet bookmark
            row.className = "input-row required";
            this.required = "required";
        } else {
            row.className = "input-row recommended";
            this.required = "optional";
        }
        var headerCell = row.appendChild(this.document.createElement("div"));
        headerCell.className = "input-header-cell";
        headerCell.appendChild(this.document.createTextNode("URL"));
        this.headerCell  = headerCell;
        var inputCell = row.appendChild(this.document.createElement("div"));
        inputCell.className = "input-cell";
        this.inputCell = inputCell;

        var inputField = inputCell.appendChild(this.document.createElement("input"));
        this.inputs = [ inputField ];
        this.inputs[0].className = "in";
        this.inputs[0].name = "rec_url";
        this.inputs[0].id = "rec_url";
        this.inputs[0].value = defaultValue  ||  "";
        inputCell.inputField = inputField;
        this.inputs[0].onblur = function(e){
            var val= e.target.value;
            if(val!=''){
                if(val.indexOf('://')<0){
                    e.target.value = 'http://'+val;
                }
            }
        };

        if (defaultValue) {
            inputField.style.display = "none";

            var urlOutput = this.document.createElement("a");
            urlOutput.target = "_blank";
            urlOutput.href = defaultValue;

            var linkImg = urlOutput.appendChild(this.document.createElement("img"));
            linkImg.src = top.HEURIST.basePath+"common/images/external_link_16x16.gif";
            linkImg.className = "link-image";
            if(!displayValue) displayValue =  defaultValue;
            displayValue = (displayValue.length>60)? displayValue.substr(0, 60)+'...':displayValue;
            urlOutput.appendChild(this.document.createTextNode(displayValue));

            inputCell.editImg = editImg;
            inputCell.viewRec = viewRec;
            inputCell.appendChild(urlOutput);

            if(canEdit){
                var urlSpan = this.document.createElement("span");
                urlSpan.style.paddingLeft = "1em";
                urlSpan.style.color = "blue";
                urlSpan.style.cursor = "pointer";
                var editImg = urlSpan.appendChild(this.document.createElement("img"));
                editImg.src = top.HEURIST.basePath+"common/images/edit-pencil.png";
                urlSpan.appendChild(editImg);
                urlSpan.appendChild(this.document.createTextNode("edit"));
                var viewRec = urlSpan.appendChild(this.document.createElement("img"));
                viewRec.src = top.HEURIST.basePath+"common/images/magglass_15x14.gif";
                urlSpan.appendChild(viewRec);
                urlSpan.appendChild(this.document.createTextNode("view"));

                urlSpan.onclick = function() {
                    inputCell.removeChild(urlOutput);
                    inputCell.removeChild(urlSpan);
                    inputField.style.display = "";
                };
                inputCell.appendChild(urlSpan);
            }
        }

        var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
        top.HEURIST.registerEvent(this.inputs[0], "change", function() { if (windowRef.changed) windowRef.changed(); });

    }; // top.HEURIST.edit.inputs.BibURLInput


    top.HEURIST.edit.inputs.BibURLInput.prototype.setReadonly = function(readonly) {
        if (readonly) {
            this.inputCell.className += " readonly";
            if (this.inputCell.editImg){
                this.inputCell.editImg.setAttribute("disabled","disabled");
            }
            if (this.inputCell.viewRec){
                this.inputCell.viewRec.setAttribute("disabled","disabled");
            }
            this.inputCell.inputField.setAttribute("disabled","disabled");
        } else {
            this.inputCell.className = this.inputCell.className.replace(/\s*\breadonly\b/, "");
            if (this.inputCell.editImg){
                this.inputCell.editImg.removeAttribute("disabled");
            }
            if (this.inputCell.viewRec){
                this.inputCell.viewRec.removeAttribute("disabled");
            }
            this.inputCell.inputField.removeAttribute("disabled");
        }
    }; // top.HEURIST.edit.inputs.BibURLInput.prototype.setReadonly


    top.HEURIST.edit.inputs.BibURLInput.prototype.focus = function() { this.inputs[0].focus(); };

    top.HEURIST.edit.inputs.BibURLInput.prototype.verify = function() {
        return (this.inputs[0].value != "");
    };

    //
    // the same code is in inputUrlInclude
    //

    top.HEURIST.edit.inputs.BibDetailURLincludeInput = function() { top.HEURIST.edit.inputs.BibDetailInput.apply(this, arguments); };
    top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype = new top.HEURIST.edit.inputs.BibDetailInput;
    top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype.focus = function() { this.inputs[0].textElt.focus(); };
    top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype.typeDescription = "a url to be included";

    /**
    * creates visible input to display file name or URL
    * and invisible to keep real value - either file ID (for uploaded) or URL|source|type for remote resources
    */
    top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype.addInput = function(bdValue) {


        var thisRef = this;    // provide input reference for closures

        var newDiv = this.document.createElement("div");
        newDiv.expando = true;
        newDiv.bdValue = null;
        this.addInputHelper.call(this, bdValue, newDiv);

        var valueVisible = "";
        var valueHidden = "";
        var thumbUrl = top.HEURIST.basePath+"common/images/icon_file.jpg";

        if(bdValue){
            if(bdValue.file){
                //new way
                valueHidden = JSON.stringify(bdValue.file); // YAHOO.lang.

                if(bdValue.file.remoteURL){
                    //remote resource
                    valueVisible = bdValue.file.remoteURL;
                }else{
                    //local uploaded file or file on server
                    valueVisible = bdValue.file.origName;
                    //  Deprecated? newDiv.bdValue =  valueHidden + '|'+ bdValue.file.URL+'|heurist|'+
                    // (bdValue.file.mediaType?bdValue.file.mediaType:'')+'|'+bdValue.file.ext;
                }

                if(bdValue.file.thumbURL){
                    thumbUrl = bdValue.file.thumbURL;
                }

            }
        }

        newDiv.className = top.HEURIST.util.isempty(valueVisible)? "file-resource-div empty" : "file-resource-div";


        var thumbDiv = this.document.createElement("div");
        thumbDiv.className = "thumbPopup";
        thumbDiv.style.backgroundImage = "url("+thumbUrl+")";
        thumbDiv.style.display = "none";
        newDiv.appendChild(thumbDiv);

        var hiddenElt = newDiv.hiddenElt = this.document.createElement("input");
        hiddenElt.name = newDiv.name;
        hiddenElt.value = hiddenElt.defaultValue = valueHidden;
        hiddenElt.type = "hidden";
        newDiv.appendChild(hiddenElt);

        var textElt = newDiv.textElt = newDiv.appendChild(this.document.createElement("input"));
        textElt.type = "text";
        textElt.title = "Click here to upload file or enter the URL";
        textElt.setAttribute("autocomplete", "off");
        textElt.className = "resource-title";
        textElt.onmouseover = function(e){
            if(top.HEURIST && !top.HEURIST.util.isempty(textElt.value)){
                thumbDiv.style.display = "block";
            }
        }
        textElt.onmouseout = function(e){
            thumbDiv.style.display = "none";
        }

        textElt.onkeypress = function(e) {
            // refuse non-tab key-input
            if (! e) e = window.event;

            if (! newDiv.readOnly  &&  e.keyCode != 9  &&  ! (e.ctrlKey  ||  e.altKey  ||  e.metaKey)) {
                // invoke popup
                thisRef.defineURL(newDiv);
                return false;
            }
            else return true;    // allow tab or control/alt etc to do their normal thing (cycle through controls)
        };

        textElt.value = textElt.defaultValue = valueVisible;

        var maxWidth = this.parentElement.width;
        if(!maxWidth || maxWidth>500){
            maxWidth = 500;
        }

        top.HEURIST.util.autoSize(textElt, {maxWidth:maxWidth});

        /*$('input#'+textElt.id).autoGrowInput({
        comfortZone: 50,
        minWidth: 20,
        maxWidth: '90%',
        id:'#'+textElt.id
        });*/


        top.HEURIST.registerEvent(textElt, "click", function() { thisRef.defineURL(newDiv); });
        top.HEURIST.registerEvent(textElt, "mouseup", function() { if (! newDiv.readOnly) thisRef.handlePossibleDragDrop(thisRef, newDiv); });
        top.HEURIST.registerEvent(textElt, "mouseover", function() { if (! newDiv.readOnly) thisRef.handlePossibleDragDrop(thisRef, newDiv); });


        var removeImg = newDiv.appendChild(this.document.createElement("img"));
        removeImg.src = top.HEURIST.basePath+"common/images/12x12.gif";
        removeImg.className = "delete-resource";
        removeImg.title = "Clear";
        var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
        top.HEURIST.registerEvent(removeImg, "click", function() {
            if (! newDiv.readOnly) {
                thisRef.clearURL(newDiv);
                if (windowRef.changed) windowRef.changed();
            }
        });
        return newDiv;
    };


    /**
    *  returns the value - ulf_ID or combined URL|source|type
    */
    top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype.getPrimaryValue = function(input) { return input? input.hiddenElt.value : ""; };

    /**
    * Open popup - to upload file and specify URL
    */
    top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype.defineURL = function(element, editValue) {

        var thisRef = this;
        var _db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));

        if (!editValue) {
            editValue = element.hiddenElt.value; //json string with bdValue.file
        }
        var recID = "";
        if(top.HEURIST.edit.record && top.HEURIST.edit.record.bibID){
            recID = "&recid="+top.HEURIST.edit.record.bibID;
        }

        var url = top.HEURIST.basePath+"records/files/uploadFileOrDefineURL.html?value="+encodeURIComponent(editValue)+recID+"&db="+_db;
        /*if (element.input.constrainrectype){
        url += "&t="+element.input.constrainrectype;
        }*/

        top.HEURIST.util.popupURL(window, url, {
            height: 480,
            width: 640,
            "no-close": true,
            callback: function(isChanged, fileJSON) {

                if(isChanged){

                    if(top.HEURIST.util.isnull(fileJSON)){
                        var r = confirm('You uploaded/changed the file data. If you continue, all changes will be lost.');
                        return r;
                    }else{
                        var filedata = top.HEURIST.util.expandJsonStructure(fileJSON);
                        if(filedata){
                            element.input.setURL(element, ((filedata.remoteSource=='heurist')?filedata.origName:filedata.remoteURL), fileJSON);
                        }
                    }

                }
                var helpDiv = document.getElementById("help-link");
                if(helpDiv) {
                    top.HEURIST.util.setHelpDiv(helpDiv,null);
                }

                return true; //prevent close
            }
        } );
    };

    /**
    * clear value
    */
    top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype.clearURL = function(element) { this.setURL(element, "", ""); };


    /**
    * assign new URL value - returns from uploadFileOrDefineURL
    */
    top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype.setURL = function(element, visibleValue, hiddenValue) {

        element.textElt.value = element.textElt.defaultValue = top.HEURIST.util.isempty(visibleValue)? "" :visibleValue;

        if (top.HEURIST.util.isempty(visibleValue)) {
            element.className += " empty";
        } else if (! element.className.match(/(^|\s+)empty(\s+|$)/)) {
            element.className = element.className.replace(/(^|\s+)empty(\s+|$)/g, "");
        }

        top.HEURIST.util.autoSize(element.textElt, {});

        element.hiddenElt.value = element.hiddenElt.defaultValue = top.HEURIST.util.isempty(hiddenValue)? "" :hiddenValue;

        element.className = top.HEURIST.util.isempty(hiddenValue)?"file-resource-div empty":"file-resource-div";

        var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;
        if (windowRef.changed) windowRef.changed();
    };

    /**
    *  TODO - to implement
    */
    top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype.handlePossibleDragDrop = function(input, element) {};

    /**
    *  TODO - to implement
    */
    top.HEURIST.edit.inputs.BibDetailURLincludeInput.prototype.calculateDroppedText = function(oldValue, newValue) {};



    top.HEURIST.fireEvent(window, "heurist-edit-js-loaded");
}

if (typeof HAPI == "undefined"){
    var windowRef = document.parentWindow  ||  document.defaultView  ||  document._parentWindow;
    if (windowRef.HAPI) {
        windowRef.HAPI.importSymbols(windowRef, this);
    }
}
