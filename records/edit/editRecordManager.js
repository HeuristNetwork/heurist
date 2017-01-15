$.getMultiScripts = function(arr, path) {
    var _arr = $.map(arr, function(scr) {
        return $.getScript( (path||"") + scr );
    });

    _arr.push($.Deferred(function( deferred ){
        $( deferred.resolve );
    }));

    return $.when.apply($, _arr);
}    

var dbname;
var bugReportURL;
var workgroupPopupID;
var caller_id_element;

$(top.document).ready( function(){
    //$.getScript('../../common/js/utilsLoad.js', onDocumentReady );
    onDocumentReady();
}
);

//
//
//
function onDocumentReady(){
    
    dbname = top.HEURIST.getQueryVar("db");
    
    $.getMultiScripts([
          //'../../common/js/utilsLoad.js',
          '../../common/php/displayPreferences.php?db='+dbname,
          '../../common/php/getMagicNumbers.php?db='+dbname,
          '../../common/php/loadUserInfo.php?db='+dbname,
          '../../common/php/loadCommonInfo.php?db='+dbname,
          //'../../common/php/loadHAPI.php?db='+dbname,
          //'editRecord.js'
    ])
        .done(onScriptsReady)
        .fail(function(error) {
            console.log(error);         
            //alert('Error on script laoding '+error);
        }).always(function() {
            // always called, both on success and error
        }); 
    
}

//
// it is invoked after document ready and all scripts are loaded
//
function onScriptsReady() {

    if (!top.HEURIST.baseURL_V3) {
        top.HEURIST.baseURL_V3 = location.protocol + "//" + location.hostname + top.HEURIST.baseURL_V3;
    }
    
    //var dbname = top.HEURIST.getQueryVar("db");
    
    if (! top.HEURIST.is_logged_in()) {

        var lsearch = top.location.search;
        if(dbname){
            dbname = "db="+dbname+"&";
        }
        //redirect to login
        top.location.replace(top.HEURIST.baseURL_V3 +
        "common/connect/login.php?"+dbname+
        "last_uri=" + escape(top.HEURIST.baseURL_V3 + "records/edit/editRecord.html" + lsearch ));
        return;
    }

    var document = top.document;
    
    caller_id_element = top.HEURIST.parameters["caller"]? top.HEURIST.parameters["caller"] :
    (window.location.search.match(/caller=(\d+).*$/)? window.location.search.match(/caller=(\d+).*$/)[1] : "");

    var bibID =(top.HEURIST.parameters["recID"]? top.HEURIST.parameters["recID"] :
    (window.location.search.match(/recID=(\d+).*$/)? window.location.search.match(/recID=(\d+).*$/)[1] : ""));
    
    if (!dbname) {
        dbname = top.HEURIST.database && top.HEURIST.database.name ? top.HEURIST.database.name: "";
    }

    top.HEURIST.util.setDBName();
    document.getElementById("home-link").href = top.HEURIST.baseURL_V3 + "?db=" +dbname;
    
    document.getElementById("version").innerHTML = 'v'+top.HEURIST.VERSION;

    //
    // from display preferences
    //
    var canCloseWindow = false;
    try {
        // see if we would be able to close this window with JS
        if (top.opener != top && top.HEURIST_WINDOW_ID) {
            canCloseWindow = true;
        }
        // otherwise, fall through to a different detail action, and hide the close-window option
    } catch (e) {
        document.getElementById("close-window-option").style.display = "none";
    }

    switch (top.HEURIST.displayPreferences["action-on-save"]) {
        case "close":
        if (canCloseWindow) {
            //ART document.getElementById("act-close").checked = true;
            break;
        }
        default:
        /*  IJ request 2012-10-08
        if (top.HEURIST && top.HEURIST.parameters && top.HEURIST.parameters['fromadd']) {
        document.getElementById("act-close").checked = true;
        } else {
        document.getElementById("act-stay").checked = true;
        }
        */
        //ART document.getElementById("act-stay").checked = true;
    }

    var helpLink = top.document.getElementById("ui-pref-showhelp");
    top.HEURIST.util.setHelpDiv(helpLink,null);

    //            
    //
    //
    bugReportURL = top.HEURIST.baseURL_V3 + 'export/email/formEmailRecordPopup.html?rectype=bugreport&db='+dbname;

    var queryStr = "",
    recordEvent = "record";
    if (bibID){
        queryStr = "recID=" + bibID;
    }
    if (top.HEURIST.parameters["bkmk_id"]){
        if (queryStr.length > 0) queryStr += "&";
        queryStr += "bkmk_id="+top.HEURIST.parameters["bkmk_id"];
    }
    if ( queryStr) {
        //0318 top.HEURIST.loadScript(top.HEURIST.baseURL_V3 +"common/php/loadRecordData.php?db="+dbname+"&"+queryStr,true);
        $.getScript(top.HEURIST.baseURL_V3 +"common/php/loadRecordData.php?db="+dbname+"&"+queryStr,
            function(){
                  top.HEURIST.edit.loadAllModules();
                  onRecordDataLoaded();
                  createRectypeChanger();
            }
            );
    }
    
    if (!top.HAPI && typeof HAPI == "object"){
        HAPI.importSymbols(this,top);
    }
    //0318 top.HEURIST.contentLoaded();
    //0318 top.HEURIST.fireEvent(top, "heurist-edit-html-loaded");
    setEditMode();
    
    window.onbeforeunload = top.HEURIST.edit.onbeforeunload; //prevent exit 
    
    //0318 top.HEURIST.whenLoaded([recordEvent, "edit-js", "edit-html"], top.HEURIST.edit.loadAllModules);
    //0318 top.HEURIST.whenLoaded([recordEvent, "edit-js", "HAPI", "edit-html"], createRectypeChanger );
    
    testWorkgroupMessage();
    
}

//
//
//
function onRecordDataLoaded(){
        var document = top.document;
    
        var rec = (top && top.HEURIST && top.HEURIST.edit && top.HEURIST.edit.record ? top.HEURIST.edit.record:null);
        var recID =  ((rec?rec.bibID : 0) || (window.HEURIST ? window.HEURIST.parameters["recID"]:0) || (top.HEURIST ? top.HEURIST.parameters["recID"]:0));
        if (! rec) {
            //                    window.location.replace(top.HEURIST.baseURL_V3 +"common/html/msgInvalidRecID.html"+ (recID ? "?" + recID : ""));
             top.HEURIST.util.showError('Cannot load record '+recID);
             return;
        }
        if (rec.denied) {
            //                    window.location.replace(window.HEURIST.baseURL_V3 +"common/html/msgAccessDenied.html"+ (recID ? "?" + recID : ""));
        }
        if (rec.replacedBy) {
            // This record has been deprecated in favour of another ...
            // Load that page (and make a best effort to stop this one from continuing)
            //                    window.location.replace("?recID=" + rec.replacedBy +"&db="+dbname);
        }
        
        if(top.HEURIST && top.HEURIST.parameters && top.HEURIST.parameters['fromadd']=="new_bib"){
            document.getElementById('div-duplication').style.display = 'none';    
        }else{
            document.getElementById('div-duplication').style.display = 'block';
        }
        
        document.getElementById("adddate").appendChild(document.createTextNode("added: " + top.HEURIST.edit.record.adddate));
        if(top.HEURIST.edit.record.moddate)
            document.getElementById("moddate").appendChild(document.createTextNode("updated: " + top.HEURIST.edit.record.moddate));
        
        top.HEURIST.edit.showRecordProperties();
        if(opener && opener.HAPI4 && opener.HAPI4.currentRecordset.length()>1){
            
                //find position in order array
                var order = opener.HAPI4.currentRecordset.getOrder();
                var idx = order.indexOf(Number(recID));
            
                document.getElementById("search-nav").innerHTML =
                "<div style=\"padding:10px 0;\">Record "+(idx+1)+" of "+order.length+" search results</div>" +
                "<div>" +
                (idx>0 ? "<a class=\"button\" style=\"height:18px !important\" title='It will save changes' "
                +"onclick='{top.HEURIST.edit.navigate_torecord(null, "+order[idx-1]+
                "); return false;}'><img src=\""+top.HEURIST.baseURL_V3+"/common/images/nav_prev.png\">previous</a>" : "") +
                (idx<order.length-1 ? "<a class=\"button\" style=\"float:right; height:18px !important\" title='It will save changes' "
                +"onclick='{top.HEURIST.edit.navigate_torecord(null,"+order[idx+1]+");"
                +" return false;}'>next<img src=\""+top.HEURIST.baseURL_V3+"/common/images/nav_next.png\"></a>" : "") +
                "<div style=\"clear:both\"></div></div>";
            
        }else if (top.HEURIST.parameters["sid"]) {
            var surl = top.HEURIST.baseURL_V3+"records/edit/setResultsNavigation.php?db="+dbname+"&s="+top.HEURIST.parameters["sid"]+"&id="+top.HEURIST.edit.record.bibID;
            top.HEURIST.util.getJsonData(surl, function(context) {
                if (!context || context.count<1) return;
                document.getElementById("search-nav").innerHTML =
                "<div style=\"padding:10px 0;\">Record "+context.pos+" of "+context.count+" search results</div>" +
                "<div>" +
                (context.prev ? "<a class=\"button\" style=\"height:18px !important\" title='It will save changes' onclick='{top.HEURIST.edit.navigate_torecord(\""+top.HEURIST.parameters["sid"]+
                '",'+context.prev+"); return false;}'><img src=\""+top.HEURIST.baseURL_V3+"/common/images/nav_prev.png\">previous</a>" : "") +
                (context.next ? "<a class=\"button\" style=\"float:right; height:18px !important\" title='It will save changes' onclick='{top.HEURIST.edit.navigate_torecord(\""+
                top.HEURIST.parameters["sid"]+'",'+context.next+"); return false;}'>next<img src=\""+top.HEURIST.baseURL_V3+"/common/images/nav_next.png\"></a>" : "") +

                //(context.prev ? "<a class=\"button\" style=\"height:18px !important\" href=?db="+dbname+"&sid="+top.HEURIST.parameters["sid"]+"&recID="
                // +context.prev+"><img src=\""+top.HEURIST.baseURL_V3+"/common/images/nav_prev.png\">previous</a>" : "") +
                //(context.next ? "<a class=\"button\" style=\"float:right; height:18px !important\" href=?db="+dbname+"&sid="+top.HEURIST.parameters["sid"]+
                // "&recID="+context.next+">next<img src=\""+top.HEURIST.baseURL_V3+"/common/images/nav_next.png\"></a>" : "") +
                "<div style=\"clear:both\"></div></div>";
            });
        }

        var contactLinkSubject = "HEURIST v" + top.HEURIST.VERSION + " user:"+top.HEURIST.get_user_username() + " edit";
        if (top.HEURIST.edit.record && top.HEURIST.edit.record.bibID) contactLinkSubject += " bibID:"+top.HEURIST.edit.record.bibID;
        if (top.HEURIST.edit.record && top.HEURIST.edit.record.bkmkID) contactLinkSubject += " bkmkID:"+top.HEURIST.edit.record.bkmkID;
        top.document.getElementById("contact-link").href += "?subject=" + encodeURIComponent(contactLinkSubject);
        
        
        var surl = top.HEURIST.baseURL_V3+"admin/verification/listFieldTypeDefinitionErrorsCompact.php?db="+dbname+"&rt="+top.HEURIST.edit.record.rectypeID;
        top.HEURIST.util.getJsonData(surl, function(context) {
            if( !top.HEURIST.util.isEmptyVar(context) ){
                var surl = top.HEURIST.baseURL_V3+"admin/verification/listFieldTypeDefinitionErrorsCompact.php?db="+dbname+"&data="+ JSON.stringify(context);
                top.HEURIST.util.popupURL(top, surl, {width:480, height:420});
            }

        });
}


//----------------------------

//
//
//
function setEditMode(){
    var document = top.document;
    
    if(top.HEURIST.util.getDisplayPreference('record-edit-advancedmode')=="true"){
        $(".edit-mode-depended").css('display','block');
        $("#rectype-change").css('display','inline-block');
        $("#page").css('left','200px');
        $("#page").css('top','120px');
        document.getElementById('ui-pref-simplemode').checked = false;
        document.getElementById('ui-pref-tabs').checked = true;
        //$("#ui-pref-simplemode").prop('checked','');
    }else{
        $(".edit-mode-depended").css('display','none');
        $("#rectype-change").css('display','none');
        $("#page").css('left','10px');
        $("#page").css('top','120px');
        document.getElementById('ui-pref-tabs').checked = false;
        document.getElementById('ui-pref-simplemode').checked = true;
        //$("#ui-pref-simplemode").prop('checked','checked');
    }
}


//
// reresh element after edit resource record in another window
//
function updateFromChild( ele_id_toupdate, value){
    //var document = top.document;
    
    //alert(ele_id_toupdate+" ,  "+value);
    var afrm = document.getElementsByTagName("iframe");
    var i=0, cnt = afrm?afrm.length:0;
    for (;i<cnt;i++){
        var d = afrm[i].contentDocument;
        var ele = d.getElementById(ele_id_toupdate);
        if(ele){
            ele.value = value;
            break;
        }
    }
}

//
//
//
function editRecordType_inNewWindow(){
    //var document = top.document;

    window.open("../../admin/adminMenuStandalone.php?db="+
    top.HEURIST.database.name+
    "&mode=rectype&rtID="+
    top.HEURIST.edit.record.rectypeID, "_blank");

    var smsg = "<p>Changes made to the record type will not become active until you reload the edit form (hit page reload in your browser).</p>"+
    "<br/>Please SAVE the record first in order not to lose data";

    var ele = document.getElementById("editRectype-message-text");
    ele.innerHTML = smsg;
    ele = document.getElementById("editRectype-message");

    var w = top.HEURIST.util.popupTinyElement(top, ele,
    { "no-titlebar": false, "no-close": false, width: 330, height: 140 });

}

//
//
//
function editRecordType(){
    //var document = top.document;

    if(top.HEURIST.edit.is_something_chnaged()){
            var message = "Click OK to save changes and modify the record structure.\n"
+"If you are unable to save changes, click Cancel and open\n"
+"structure modification in a new tab (icon on the right of the link)";
            if (confirm(message)) {
                top.HEURIST.edit.save_record(editRecordType);
            }
            return;
    }


    var URL = top.HEURIST.baseURL_V3 + "admin/structure/fields/editRecStructure.html?db="+
                top.HEURIST.database.name+
                "&rty_ID="+top.HEURIST.edit.record.rectypeID;
    //this.location.replace(URL);
    var win = this.window;

    var dim = top.HEURIST.util.innerDimensions(win);

    top.HEURIST.util.popupURL(top, URL, {
            "close-on-blur": false,
            "no-resize": false,
            height: dim.h*0.9,
            width: 860,
            "no-close": true,
            closeCallback: function(){ alert('kiki'); },
            callback: function(context) {
                    if(!top.HEURIST.util.isnull(context) && context && win.location) {
                        //reload this page
                        win.location.reload();
                    }
            }
    });
}

//
// show the specific reminder for recordtype
//
function onAddNewRecordMessage(){

    if(!top.HEURIST.magicNumbers) return;

    if(top.HEURIST && top.HEURIST.parameters && top.HEURIST.parameters['fromadd']=="new_bib"){

        var smsg = '';

        if(top.HEURIST.edit.record.rectypeID == top.HEURIST.magicNumbers['RT_MAP_LAYER']){
            smsg = "<p>You will need to add the new map layer to the current map document and to any other map documents on which you wish it to appear, by editing each of the map documents in turn</p>";
        }

        if(smsg!=''){
            var ele = document.getElementById("editRectype-message-text");
            ele.innerHTML = smsg;
            ele = document.getElementById("editRectype-message");

            var w = top.HEURIST.util.popupTinyElement(top, ele,
            { "no-titlebar": false, "no-close": false, width: 330, height: 140 });
        }

    }
}

function createRectypeChanger() {
    var rectypeEdit = document.getElementById("rectype-edit");

    //<span class=\"recID\">" + top.HEURIST.rectypes.typedefs[rectypeID].commonFields[cfi.rty_Name] + ": </span>"
    var rectypeName = document.getElementById("rectype-name");
    rectypeName.innerHTML = top.HEURIST.util.getRectypeIconAndName(top.HEURIST.edit.record.rectypeID);//top.HEURIST.edit.record.rectype;

    var rectypeValSelect = document.createElement("select");
    rectypeValSelect.id = "rectype-select";
    rectypeValSelect.onchange = changeRectype;
    rectypeEdit.appendChild(rectypeValSelect);

    // rectypes displayed in Groups by group display order then by display order within group
    // TODO: This should be using common function with other places record types are being displayed
    // viz. adding record types, selecting record types for pointer field constraints etc.
    var index;
    for (index in top.HEURIST.rectypes.groups){
        if (index == "groupIDToIndex" ||
        top.HEURIST.rectypes.groups[index].showTypes.length < 1) continue;
        var grp = document.createElement("optgroup");
        grp.label = top.HEURIST.rectypes.groups[index].name;
        rectypeValSelect.appendChild(grp);
        for (var recTypeIDIndex in top.HEURIST.rectypes.groups[index].showTypes) {
            var recTypeID = top.HEURIST.rectypes.groups[index].showTypes[recTypeIDIndex];
            var name = top.HEURIST.rectypes.names[recTypeID];
            rectypeValSelect.appendChild(new Option(name, recTypeID));
            if (name == top.HEURIST.edit.record.rectype) {
                rectypeValSelect.selectedIndex = rectypeValSelect.options.length-1;
            }
        }
    }

    if(top.HEURIST.is_admin()){
        var edt = document.getElementById("admin_editrectype");
        edt.innerHTML = "<a href='#' onclick='{editRecordType(); return false;}' style='font-size:1.3em;padding:0;border: none; vertical-align: 7px;'>Modify record structure<img src='../../common/images/edit-pencil.png'></a>"+
        "<a href='#' class='externalLink' style='border:none' onclick='{editRecordType_inNewWindow(); return false;}' style='font-size:1.3em;padding:0 3px;'></a>";

        ;
    }
    if(top.HEURIST.edit.record && top.HEURIST.edit.record.isTemporary){
        top.document.title = "Add ["+top.HEURIST.edit.record.rectype + ", "+ top.HEURIST.edit.record.rectypeID + "] "+top.document.title;
    }else{
        top.document.title = top.HEURIST.edit.record.title + " [" + top.HEURIST.edit.record.rectype + ", "+ top.HEURIST.edit.record.rectypeID +"] ID:"+top.HEURIST.edit.record.recID +
        ".  "+top.document.title;
    }


    onAddNewRecordMessage();
}

function changeRectype() {
    var rectypeValue = document.getElementById("rectype-select").value;
    if (top.HEURIST.edit.record.rectype != rectypeValue) {
        top.HEURIST.edit.record.rectypeID = rectypeValue;
        top.HEURIST.edit.record.rectype = top.HEURIST.rectypes.names[rectypeValue];

        // update the public info frame to reflect the change of rectype
        top.HEURIST.edit.showModule("public");
        top.HEURIST.edit.modules["public"].frame.contentWindow.setRectype(rectypeValue);
        top.HEURIST.edit.showRecordProperties();
    }
}


function openWorkgroupChanger() {
    if (top.HEURIST.edit.record.visibility == "hidden") {
        document.getElementById("non-wg-hide").checked = true;
    } else if (top.HEURIST.edit.record.visibility == "viewable"){
        document.getElementById("non-wg-view").checked = true;
    } else if (top.HEURIST.edit.record.visibility == "pending"){
        document.getElementById("non-wg-pending").checked = true;
    } else {
        document.getElementById("non-wg-public").checked = true;
    }

    var wgVal = document.getElementById("new-workgroup-value");
    while (wgVal.childNodes.length) {
        wgVal.removeChild(wgVal.lastChild);
    }
    var recOwnerID = top.HEURIST.edit.record.workgroupID;
    wgVal.options[0] = new Option("Everyone (no restriction)", "0");
    wgVal.options[1] = new Option(top.HEURIST.get_user_name(), top.HEURIST.get_user_id());
    wgVal.selectedIndex = recOwnerID == top.HEURIST.get_user_id() ? 1:0;
    for (var i=0; i < top.HEURIST.user.workgroups.length; ++i) {
        var wgID = top.HEURIST.user.workgroups[i];
        var wgName = top.HEURIST.workgroups[wgID].name;
        wgVal.options[i+2] = new Option(wgName, wgID);

        if (wgID == recOwnerID){
            wgVal.selectedIndex = i+2;
        }
    }


    var wgEditor = document.getElementById("workgroup-editor");
    wgEditor.style.display = "block";

    var linkPos = top.HEURIST.getPosition(document.getElementById("workgroup-edit"));
    top.HEURIST.util.popupElement(window, wgEditor,
    { width: (wgEditor.offsetWidth<360?360:wgEditor.offsetWidth), height: wgEditor.offsetHeight, x: linkPos.x-200, y: linkPos.y+20, "no-titlebar": true, "no-resize": true });
    setTimeout(function() { wgVal.focus(); }, 0);
}

function closeWorkgroupChanger(cancel) {
    if (! cancel) {
        var wgValue = document.getElementById("new-workgroup-value").value;
        var wgVisValue = document.getElementById("non-wg-hide").checked? "hidden" : (document.getElementById("non-wg-view").checked?"viewable" : (document.getElementById("non-wg-pending").checked?"pending" : "public"));
        if (top.HEURIST.edit.record.workgroupID != wgValue  ||  top.HEURIST.edit.record.visibility != wgVisValue) {
            top.HEURIST.edit.record.workgroupID = wgValue;
            top.HEURIST.edit.record.workgroup = wgValue > 0 ? (wgValue == top.HEURIST.get_user_id() ? top.HEURIST.get_user_name() : top.HEURIST.workgroups[wgValue].name) : "";
            top.HEURIST.edit.record.visibility = wgVisValue;

            top.HEURIST.edit.modules["public"].frame.contentWindow.setWorkgroupProperties(wgValue, wgVisValue);

            top.HEURIST.edit.showRecordProperties();
        }
    }

    var popupsList = top.HEURIST.util.popups.list;
    top.HEURIST.util.closePopup(popupsList[popupsList.length-1].id);
}


function testWorkgroupMessage() {
    //0318 top.HEURIST.deregisterEvent(window, "load", testWorkgroupMessage);

    if (top.HEURIST.parameters["wg"] == top.HEURIST.get_user_id() ||
    !(top.HEURIST.parameters["wg"] ||
    top.HEURIST.parameters["wgkwd"])) {
        return;
    }
    var wg = top.HEURIST.workgroups[parseInt(top.HEURIST.parameters["wg"])];

    document.getElementById("workgroup-message-wgname").appendChild(document.createTextNode(wg? wg.name : ""));

    var w = top.HEURIST.util.popupElement(top, document.getElementById("workgroup-message"), { "no-titlebar": true, width: 400, height: 300 });
    workgroupPopupID = w.id;
}
