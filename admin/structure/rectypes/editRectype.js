var Hul = top.HEURIST.util;

var rectypeID = Hul.getUrlParameter('rectypeID', this.location.search);
var db = (top.HEURIST.parameters && top.HEURIST.parameters.db? top.HEURIST.parameters.db :
                        (top.HEURIST.database.name?top.HEURIST.database.name:''));
    


function EditRectype() {

var rectype,
    defaultGroupID,
    supressEditStructure,
    
    titleMaskIsOk = true,
    _keepTitleMask,
    _keepStatus;

var _openEditStrucutreAfterClose = false,
    _isIconWasUpdated = false;
    
    
function init() {
    
    var query = this.location.search;
    
    defaultGroupID = Hul.getUrlParameter('groupID',query);
    supressEditStructure = (Hul.getUrlParameter('supress',query)=='1');

    var typesDropdown;
    var groupDropdown;
    var newRectypeCode = 0; // 0: rectype with rectypeID exists - 1: no rectypeID given, create new - 2: rectype with given ID not found, error
    if(rectypeID) { // Existing rectype, so load with values
        try {
            rectype = top.HEURIST.rectypes.typedefs[rectypeID].commonFields; // If succeeds, rectype with ID from URL exists, fill form with values
            setRtyValues();
        } catch(e) { // Invalid rectypeID, create empty form, and show error message
            newRectypeCode = 2;
        }
    } else {
        newRectypeCode = 1; // No rectypeID given
    }
    if(newRectypeCode > 0) { // Create empty form
        initializeEmptyForm();
        document.getElementById("rty_TitleMask_row").style.display = "none";
    }else{
        document.getElementById("definitdiv").style.display = "none";
    }
    if(newRectypeCode == 2) { // Give error message
        document.getElementById("statusMsg").style.display = "block";
        document.getElementById("statusMsg").innerHTML = "<strong>Error: record type could not be found. Clicking 'save' button will create a new record type.</strong><br /><br />";
    }

    if(supressEditStructure){
     //Ian removed it   document.getElementById('btnSaveAndEditStructure').style.display = 'none';
    }

    setTimeout(function(){document.getElementById("rty_Name").focus();},1000);
}

/**
 * invokes popup to change icon or thumbnail
 */
function _upload_icon(mode) {

    function icon_refresh(context) {
        var img = document.getElementById('imgIcon');
        img.src = img.src.replace(/\?.*/, '') + '?' + (new Date()).getTime();
        _isIconWasUpdated = true;
    }
    function thumb_refresh(context) {
        var img = document.getElementById('imgThumb');
        img.src = img.src.replace(/\?.*/, '') + '?' + (new Date()).getTime();
        _isIconWasUpdated = true;
    }
    
    
    var sURL = top.HEURIST.baseURL + "admin/structure/rectypes/uploadRectypeIcon.php?db="+ db + "&mode="+mode+"&rty_ID="+rectypeID;

    Hul.popupURL(top, sURL, {
            "close-on-blur": false,
            "no-resize": false,
            height: 500, //(mode==0?200:250),
            width: 550,
            callback:function(){
                icon_refresh();
                thumb_refresh();
            } //mode==0?icon_refresh:thumb_refresh
    });

}



function initializeEmptyForm() {
    document.getElementById("infoImage").style.display = "none";
    document.getElementById("rty_ID").innerHTML= "<span style=\"color:#999; font-weight:normal; font-size:11px;\">will be automatically assigned</span>";
    document.getElementById("rty_ShowInLists").checked = true;
    document.getElementById("rty_ShowURLOnEditForm").checked = false;
    document.getElementById("rty_ShowDescriptionOnEditForm").checked = true;
    document.getElementById("rty_NonOwnerVisibility").selectedIndex = 0;
    document.getElementById("divIconAndThumb").style.display = "none";

    getGroups();
    // Set group from URL
    getTypes();
    getStatus();
}

function setRtyValues() {
    document.title = "Record Type # " + rectypeID;
    getGroups();
    getTypes();
    if(rectype) {
        var d = new Date(),
        curtimestamp = d.getMilliseconds();
        document.getElementById("rty_ID").innerHTML = rectypeID;
        try {

            document.getElementById("rectypeIcon").innerHTML =
            "<a href=\"javascript:void(0)\" onClick=\"editRectypeEditor.upload_icon(0)\" title=\"Click to change icon\">"+
            "<img id=\"imgIcon\" src=\""+
            top.HEURIST.iconBaseURL + rectypeID + ".png?" + curtimestamp +
            "\" width=\"16\" height=\"16\"></a>";

            document.getElementById("rectypeThumb").innerHTML =
            "<a href=\"javascript:void(0)\" onClick=\"editRectypeEditor.upload_icon(1)\" title=\"Click to change thumbnail\">"+
            "<img id=\"imgThumb\" src=\""+
            top.HEURIST.iconBaseURL + "thumb/th_" + rectypeID + ".png?" + curtimestamp +
            "\" width=\"75\" height=\"75\"></a>";

        } catch(e) {
            alert(e);
        }
        var fi = top.HEURIST.rectypes.typedefs.commonNamesToIndex;

        if(Hul.isempty(rectype[fi.rty_ConceptID])){
            document.getElementById("div_rty_ConceptID").innerHTML = '';
        }else{
            document.getElementById("div_rty_ConceptID").innerHTML = 'Concept Code: '+rectype[fi.rty_ConceptID];
        }
        document.getElementById("rty_Name").value = rectype[fi.rty_Name];
        document.getElementById("rty_Plural").value = rectype[fi.rty_Plural];
        document.getElementById("rty_Description").value = rectype[fi.rty_Description];
        document.getElementById("rty_OrderInGroup").value = rectype[fi.rty_OrderInGroup];
        document.getElementById("rty_ShowInLists").checked = (Number(rectype[fi.rty_ShowInLists]) === 1);
        document.getElementById("rty_ShowURLOnEditForm").checked = (Number(rectype[fi.rty_ShowURLOnEditForm]) !== 0);
        document.getElementById("rty_ShowDescriptionOnEditForm").checked = (Number(rectype[fi.rty_ShowDescriptionOnEditForm]) !== 0);
        document.getElementById("rty_TitleMask").value = rectype[fi.rty_TitleMask];
        _keepTitleMask = rectype[fi.rty_TitleMask];
        document.getElementById("rty_FlagAsFieldset").checked = (Number(rectype[fi.rty_FlagAsFieldset]) === 1);
        document.getElementById("rty_ReferenceURL").value = rectype[fi.rty_ReferenceURL];
        document.getElementById("rty_AlternativeRecEditor").value = rectype[fi.rty_AlternativeRecEditor];

        document.getElementById("rty_CanonicalTitleMask").value = rectype[fi.rty_CanonicalTitleMask];
        document.getElementById("rty_RecTypeGroupID").value = rectype[fi.rty_RecTypeGroupID];

        document.getElementById("rty_NonOwnerVisibility").value = rectype[fi.rty_NonOwnerVisibility];;
    }
    getStatus();
}

function _addOptionReserved(){
    var selstatus = document.getElementById("rty_Status");
    if(selstatus.length<4){
        var option = document.createElement("option");
        option.text = 'reserved';
        option.value = 'reserved';
        try {
            // for IE earlier than version 8
            selstatus.add(option, sel.options[null]);
        }catch (ex2){
            selstatus.add(option,null);
        }
    }
}

// Create the status dropdown menu with all possible statusses. In case it is reserved, disable the entire form. Always save the previous status in 'var status', so we can change it back to the previous status when someone 'cancels' on the prompt wether they really want to change the status to 'reserved'
function getStatus() {

    var disable_status = false,
    disable_fields = false,
    selstatus = document.getElementById("rty_Status"),
    fi = top.HEURIST.rectypes.typedefs.commonNamesToIndex,
    dbId = Number(top.HEURIST.database.id);

    var original_dbId = (rectype)?Number(rectype[fi.rty_OriginatingDBID]):dbId;
    if(Hul.isnull(original_dbId)) {original_dbId = dbId;}


    if((dbId>0) && (dbId<1001) && (original_dbId===dbId)) {
        _addOptionReserved();
    }


    if(rectype){ //edit

        if(rectype[fi.rty_Status]==='reserved'){

            disable_status = (original_dbId!==dbId) && (original_dbId>0) && (original_dbId<1001);
            disable_fields = true;
            _addOptionReserved();

        }else if(rectype[fi.rty_Status]==='approved'){

            disable_fields = true;
        }

        _keepStatus = rectype[fi.rty_Status];
        selstatus.value = rectype[fi.rty_Status];

    }else{ //new
        selstatus.value = "open";
    }
    toggleAll(disable_status || disable_fields, disable_status);
}

// Change the status. If reserved, disable the entire form. Else, enable the entire form. Always save the previous status in 'var status', so we can change it back to the previous status when someone 'cancels' on the prompt wether they really want to change the status to 'reserved'
function _onChangeStatus(e) {

    var el = e.target;
    if(el.value === "reserved") {

        var changeToReserved = confirm("If you change the status to 'reserved'," +
            " you will no longer be able to modify the definitions of some fields in this record type.\n\nAre you sure?");
        if(changeToReserved) {
            toggleAll(true, false);
        } else {
            el.value = _keepStatus; //restore previous value
        }
    }else if(el.value === "approved") {
        _keepStatus = el.value;
        toggleAll(true, false);
    } else {
        _keepStatus = el.value;
        toggleAll(false, false);
    }

}

// Create the dropdown menu with all possible types, and if an existing rectype is being editted, select the right value
function getTypes() {
    typesDropdown = document.createElement("select");
    typesDropdown.options.add(optionNormal = document.createElement("option"));
    typesDropdown.options.add(optionRelationship = document.createElement("option"));
    typesDropdown.options.add(optionDummy = document.createElement("option"));

    optionNormal.text = "normal";
    optionRelationship.text = "relationship";
    optionDummy.text = "dummy";

    if(rectype) {
        switch(top.HEURIST.rectypes.typedefs[rectypeID].commonFields[top.HEURIST.rectypes.typedefs.commonNamesToIndex.rty_Type]) {
            case "normal":
                typesDropdown[0].selected = "1";
                break;
            case "relationship":
                typesDropdown[1].selected = "1";
                break;
            case "dummy":
                typesDropdown[2].selected = "1";
                break;
        }
    }
    document.getElementById("rty_Type").appendChild(typesDropdown);
}

function getGroups() {
    groupDropdown = document.createElement("select");
    var existingGroup = "",
    index = 0,
    rtg_ID;

    for (index in top.HEURIST.rectypes.groups){
        if(!isNaN(Number(index))) {
            rtg_ID = top.HEURIST.rectypes.groups[index].id;

            optn = document.createElement("option");
            optn.text = top.HEURIST.rectypes.groups[index].name;
            optn.value = rtg_ID;

            groupDropdown.options.add(optn);
            if(rectype) {
                if(rtg_ID == top.HEURIST.rectypes.typedefs[rectypeID].commonFields[top.HEURIST.rectypes.typedefs.commonNamesToIndex.rty_RecTypeGroupID]) {
                    selectedIndex = index;
                    var existingGroup = true;
                }
            }
            else if(defaultGroupID) {
                if(rtg_ID == defaultGroupID) {
                    selectedIndex = index;
                    var existingGroup = true;
                }
            }
            index++;
        }
        if(existingGroup) {
            groupDropdown[selectedIndex].selected = 1;
        }
        document.getElementById("rty_RecTypeGroupID").appendChild(groupDropdown);
    }//for
}
// Toggle fields to disable. Is called when status is set to 'Reserved'. If changed = true, it means that the status is manually changed to reserved, so untill it is saved, it can be changed back. If it was reserved when starting the editRectype, keep it disabled
function toggleAll(disable, reserved) {
    document.getElementById("rty_Status").disabled = reserved;
    document.getElementById("rty_OrderInGroup").disabled = disable;
    document.getElementById("rty_FlagAsFieldset").disabled = disable;

    //document.getElementById("rty_ReferenceURL").disabled = disable;
    //document.getElementById("rty_Name").disabled = disable;
    //document.getElementById("rty_Plural").disabled = disable;
    //groupDropdown.disabled = disable;
    //document.getElementById("rty_ShowInLists").disabled = disable;
    //document.getElementById("rty_TitleMask").disabled = disable;
    //document.getElementById("btn_TitleMask").disabled = disable;
    typesDropdown.disabled = disable;
}


/* NOT USED see Hul.validate Validate value inserted into input field. In this case, make sure it's an integer
function checkIfInteger(evt) {
    if((evt.keyCode) != 9) {
        var theEvent = evt || window.event;
        var key = theEvent.keyCode || theEvent.which;
        key = String.fromCharCode(key);
        var regex = /[0-9]|\./;
        if( !regex.test(key) ) {
            theEvent.returnValue = false;
            theEvent.preventDefault();
        }
    }
}*/

//
function updateRectypeOnServer() {
    testTitleMask(); //then it calls updateRectypeOnServer_continue
}

function updateRectypeOnServer_continue()
{
    if(!titleMaskIsOk){
        alert("Title mask is invalid");
        return;
    }

    var str = getUpdatedFields();

    if(str=="mandatory"){
        //do not close the window

    }else if(str != null) {
        //DEBUG            alert("Stringified changes: " + str);

        // TODO: Change base URL
        var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
        var callback = updateResult;
        var params = "method=saveRT&db="+db+"&definit="+
        (document.getElementById("definit").checked?1:0)+
        "&data=" + encodeURIComponent(str);
        Hul.getJsonData(baseurl, callback, params);

    } else { //nothing changed

        var context = null;
        if(_openEditStrucutreAfterClose){
            //editRecStructure();
            context = {rty_ID:rectypeID, isOpenEditStructure:true };
        }else if(_isIconWasUpdated){
            context = {rty_ID:rectypeID, isOpenEditStructure:false };
        }

        window.close(context);
    }
}

var updateResult = function(context) {
    top.HEURIST.rectypes.saveStatus = context;

    if(!Hul.isnull(context)) {

        var error = false;
        var report = "";

        for(var ind in context.result)
        if(ind!=undefined){
            var item = context.result[ind];
            if(isNaN(item)){
                alert("An error occurred: " + item);
                error = true;
            }else{
                rectypeID = Number(item);
                if(report!="") report = report + ",";
                report = report + Math.abs(rectypeID);
            }
        }

        if(!error) {

            var ss = (rectypeID < 0)?"added":"updated";

            if(report.indexOf(",")>0){
                // this alert is a pain  alert("Record types with IDs :"+report+ " were succesfully "+ss);
            }else{
                // this alert is a pain  alert("Record type with ID " + report + " was succesfully "+ss);
            }

            context.changeTitleMask = false;
            /* art 2014-05-26 - now it updates in editRectypeTitle
            if(rectypeID>0 &
                document.getElementById("rty_TitleMask").value !== _keepTitleMask){
                context.changeTitleMask = true;
            }*/

            rectypeID = Math.abs(rectypeID);

            if(_openEditStrucutreAfterClose){
                //editRecStructure();
                context.isOpenEditStructure = true;
            }

            window.close(context); //send back new HEURIST strcuture
        }
    }
}


/**
 * Opens new popup to edit record structure
 */
function editRecStructure()
{
    Hul.popupURL(top, top.HEURIST.basePath + "admin/structure/fields/editRecStructure.html?db="+db+"&rty_ID="+rectypeID, {
            "close-on-blur": false,
            "no-resize": false,
            height: 480,
            width: 620,
            callback: function(context) {
                if(context == null) {
                    // Canceled
                } else {
                    //alert("Structure is Saved");
                }
            }
    });
}


function getUpdatedFields() {

    if(fromUItoArray(true)=="mandatory"){ //save all changes
        return "mandatory";
    }

    if(rectypeID != null && updatedFields.length > 0) {
        var k;

        var oRectype = {rectype:{
                colNames:{common:[], dtFields:[]},
                defs: {}
        }};
        //fill array of updated fieldnames
        var fieldNames = top.HEURIST.rectypes.typedefs.commonFieldNames;
        var values = [];
        for(k = 0; k < updatedFields.length; k++) {
            oRectype.rectype.colNames.common.push(fieldNames[updatedFields[k]]);
            values.push(updatedDetails[updatedFields[k]]);
        }


        oRectype.rectype.defs[rectypeID] = [];
        oRectype.rectype.defs[rectypeID].push({common:[],dtFields:[]});
        for(val in values) {
            oRectype.rectype.defs[rectypeID][0].common.push(values[val]);
        }

        var str = JSON.stringify(oRectype);
        //var str = YAHOO.lang.JSON.stringify(oRectype);

        return str;
    }
    else {
        return null;
    }

}

var updatedFields = [];
var updatedDetails = [];
//
// fills 2 arrays for changed values - with fieldnames and values
// return "mandatory" in case some required field is not defined
//
function fromUItoArray(isShowWarn) {

    updatedFields  = [];
    updatedDetails = [];

    if(!rectypeID || rectypeID == -1) { // New detail type, so save every field
        rectypeID = -1;
        var rectypeValues = [];
    } else {
        var rectypeValues = top.HEURIST.rectypes.typedefs[rectypeID].commonFields;
    }


    var fieldNames = top.HEURIST.rectypes.typedefs.commonFieldNames;

    var index;
    for(index = 0; index < fieldNames.length; index++) {
        var fieldName = fieldNames[index];
        var fieldNameElement = document.getElementById(fieldName);
        if(fieldNameElement==undefined){
            //todo!!!  why these fields are not defined in this form???? alert("element not found "+fieldName);
            continue;
        }
        var fieldNameElementValue = fieldNameElement.value;

        if(fieldName=="rty_Name" && fieldNameElementValue=="") {
            if(isShowWarn) alert("Name is mandatory field");
            document.getElementById("rty_Name").focus();
            updatedFields = [];
            return "mandatory";
        }
        else if(fieldName=="rty_Plural" && fieldNameElementValue=="") {
            if(isShowWarn) alert("Plural is mandatory field");
            document.getElementById("rty_Plural").focus();
            updatedFields = [];
            return "mandatory";
        }
        else if(fieldName=="rty_Description" && fieldNameElementValue=="") {
            if(isShowWarn) alert("Description is mandatory field");
            document.getElementById("rty_Description").focus();
            updatedFields = [];
            return "mandatory";
        }
        else if(fieldName=="rty_TitleMask" && fieldNameElementValue=="") {


            if(isShowWarn) alert("Title Mask is mandatory field");
            document.getElementById("rty_TitleMask").focus();
            updatedFields = [];
            return "mandatory";

        }else if(fieldName=="rty_RecTypeGroupID") {

            fieldNameElementValue = groupDropdown.value;

        }else
            if(fieldName === "rty_Type" ) { // dropdown menu's. Can't get the value with element.value
            fieldNameElementValue = typesDropdown.value;
        } else if(fieldName === "rty_Status") {
            fieldNameElementValue = document.getElementById("rty_Status").value;
        }

        if(fieldNameElement.type=="checkbox"){
            fieldNameElementValue = fieldNameElement.checked?"1":"0";
        }

        if(rectypeValues[index] !== fieldNameElementValue || rectypeID == -1) {
            //if(Hul.isnull(rectypeValues[index]) && !fieldNameElementValue) { // Because if database value is DOM value is NULL, it doesn't enter that in input field
            //} else {
            //rectypeValues[index] = fieldNameElementValue;
            updatedFields.push(index);
            updatedDetails[index] = fieldNameElementValue;
            //}
        }
    }

    return "ok";
}

/**
* put your comment there...
* 
*/
function testTitleMask()
{
    if(!rectypeID || rectypeID < 0){
        var val = "record [ID]";
        if(document.getElementById("definit").checked &&
            top.HEURIST.magicNumbers && top.HEURIST.magicNumbers['DT_NAME']){
            val = "["+top.HEURIST.detailTypes.names[top.HEURIST.magicNumbers['DT_NAME']]+"]";
        }

        document.getElementById("rty_TitleMask").value = val
        titleMaskIsOk = true;
        updateRectypeOnServer_continue();

    }else{

        var mask = document.getElementById("rty_TitleMask").value;

        var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
            (top.HEURIST.database.name?top.HEURIST.database.name:''));

        var baseurl = top.HEURIST.basePath + "admin/structure/rectypes/editRectypeTitle.php";
        var squery = "rty_id="+rectypeID+"&mask="+mask+"&db="+db+"&check=1"; //verify titlemask

        top.HEURIST.util.sendRequest(baseurl, function(xhr) {
                var obj = xhr.responseText;
                if(obj!="" && obj.match(/\S/)){
                    titleMaskIsOk = false;
                    alert("Title Mask verification:\n"+obj);
                }else{
                    titleMaskIsOk = true;
                    updateRectypeOnServer_continue();
                }
            }, squery);
    }
}

function viewEquivalences() {
    alert("This function has not yet been implemented");
}

/**
 * Edit title mask
 */
function _onEditMask(){

    var maskvalue = document.getElementById("rty_TitleMask").value;

    Hul.popupURL(top, top.HEURIST.basePath +
        "admin/structure/rectypes/editRectypeTitle.html?rectypeID="+rectypeID+"&mask="+encodeURIComponent(maskvalue)+"&db="+db,
        {
            "close-on-blur": false,
            "no-resize": true,
            height: 800,
            width: 800,
            callback: function(newvalue) {
                if(!Hul.isnull(newvalue)){
                    document.getElementById("rty_TitleMask").value = newvalue;
                }
            }
    });
}

        //public members
        var that = {

            createPlural: function() {
                document.getElementById("rty_Plural").value = (document.getElementById("rty_Name").value + 's');
            },

            onChangeStatus: function(e) {
                _onChangeStatus(e);
            },
            
            editMask: function(){
                _onEditMask();
            },
            
            upload_icon: function (mode){
                _upload_icon(mode);
            },
            
            save : function (openEditStructure) {
                _openEditStrucutreAfterClose = openEditStructure;
                updateRectypeOnServer();
            },
            cancel : function () {
                fromUItoArray(false);
                if(updatedFields.length > 0) {
                    var areYouSure = confirm("Changes were made. By cancelling, all changes will be lost. Are you sure?");
                    if(areYouSure) {
                        window.close(null);
                    }
                }
                else {
                    window.close(null);
                }
            }
        };
        init();
        return that;
};
/*var structureFrame = document.getElementById("structureFrame");
var URL = top.HEURIST.basePath + "admin/structure/fields/editRecStructure.html?db="+db+"&rty_ID="+rectypeID;
structureFrame.src = URL;
*/



var needHideTip = true,
hideTimer,
textTip;

/**
 * Show popup div with information about field types in use for given record type
 */
function _showInfoToolTip(event) {

    //tooltip div mouse out
    function __hideToolTip2() {
        needHideTip = true;
        //_hideToolTip();
    }
    //tooltip div mouse over
    function __clearHideTimer2() {
        needHideTip = false;
        clearHideTimer();
    }


    var forceHideTip = true;

    if(!Hul.isnull(rectypeID)){
        if(Hul.isnull(textTip)) {

            var recname = top.HEURIST.rectypes.names[rectypeID];
            if(recname.length>40) { recname = recname.substring(0,40)+"..."; }
            //find all records that reference this type
            var details = top.HEURIST.rectypes.typedefs[rectypeID].dtFields;
            textTip = '<h3>'+recname+'</h3>'+
            '<b>Fields:</b><ul>';

            var detail;
            for(detail in details) {
                textTip = textTip + "<li>"+details[detail][0] + "</li>";
            }
            textTip = textTip + "</ul>";
        } else {
            forceHideTip = false;
        }
    }
    if(!Hul.isnull(textTip)) {
        clearHideTimer();
        needHideTip = true;
        var my_tooltip = $("#toolTip2");

        my_tooltip.mouseover(__clearHideTimer2);
        my_tooltip.mouseout(__hideToolTip2);

        var xy = Hul.getMousePos(event);
        //xy = [posx = event.target.x,posy = event.target.y];


        my_tooltip.html(textTip);    //DEBUG xy[0]+",    "+xy[1]+"<br/>"+

        var border_top = $(window).scrollTop();
        var border_right = $(window).width();
        var border_height = $(window).height();
        var offset =0;

        Hul.showPopupDivAt(my_tooltip, xy,border_top ,border_right ,border_height, offset );

        //hideTimer = window.setTimeout(_hideToolTip, 2000);
    }
    else if(forceHideTip) {
        _hideToolTip();
    }

}

function hideInfo() {
    hideTimer = window.setTimeout(_hideToolTip, 500);
}
function forcehideInfo() {
    hideTimer = window.setTimeout(_hideToolTip, 0);
}
//
//
function clearHideTimer(){
    if (hideTimer) {
        window.clearTimeout(hideTimer);
        hideTimer = 0;
    }
}
function _hideToolTip(){
    if(needHideTip){
        currentTipId = null;
        clearHideTimer();
        var my_tooltip = $("#toolTip2");
        my_tooltip.css( {
                visibility:"hidden",
                opacity:"0"
        });
    }
}

