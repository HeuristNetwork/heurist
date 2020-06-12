    
/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

var Hul = window.hWin.HEURIST4.util;

var rectypeID = window.hWin.HEURIST4.util.getUrlParameter('rectypeID', location.search);

var db = window.hWin.HAPI4.database;



function EditRectype() {

var rectype,
    defaultGroupID,
    supressEditStructure,

    titleMaskIsOk = true,
    _keepTitleMask,
    _keepStatus,
    _rectype_icon = '',
    _selected_fields = null,
    _this_window;

var _openEditStrucutreAfterClose = false,
    _isIconWasUpdated = false;


function init() {
    
    _this_window = window;

    var query = this.location.search;

    defaultGroupID = Hul.getUrlParameter('groupID',query);
    supressEditStructure = (Hul.getUrlParameter('supress',query)=='1');

    var typesDropdown;
    var groupDropdown;
    var newRectypeCode = 0; // 0: rectype with rectypeID exists 
                            // 1: no rectypeID given, create new 
                            // 2: rectype with given ID not found, error
    if(rectypeID) { // Existing rectype, so load with values
        try {
            rectype = window.hWin.HEURIST4.rectypes.typedefs[rectypeID].commonFields; // If succeeds, rectype with ID from URL exists, fill form with values
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
        if(newRectypeCode == 1){
            _upload_icon(3);
            
            //_select_fields();
        }
        
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
* select minimum set of fields for new record type
*/
function _select_fields(continue_save){

    var sURL = window.hWin.HAPI4.baseURL + "admin/structure/rectypes/editRectypeSelFields.html";

    var that = this;
    
    window.hWin.HEURIST4.msg.showDialog(sURL, {
            "close-on-blur": false,
            "no-resize": false,
            height: 500, //(mode==0?200:250),
            width: 700,
            title:' Select fields for new record type',
            afterclose:function(){
                if($.isFunction(continue_save)){
                    continue_save.call(that);
                }
            },
            callback:function(context){
                _selected_fields = context;
            } 
    });
    
}

/**
 * invokes popup to change icon or thumbnail
 *  0 icon
 *  1 thumb
 *  3 select from lib
 */
function _upload_icon(mode) {

    function icon_refresh(context) {
        if(mode==3){
            if(context){  //icon from library
               var img = document.getElementById('imgIcon2');
               img.src = window.hWin.HAPI4.baseURL + "admin/setup/iconLibrary/16px/" + context;
               img.width = 16;
               img.height = 16;
            } 
        }else{  //uploaded
               var img = document.getElementById('imgIcon');
               if(!img) img = document.getElementById('imgIcon2');
               img.src = window.hWin.HAPI4.iconBaseURL + rectypeID + '&t='+ (new Date()).getTime();
               img.width = 16;
               img.height = 16;
        }
        _isIconWasUpdated = true;
    }
    function thumb_refresh(context) {
        if(mode==3){
            if(context){
               _rectype_icon = context;   //name  of icon from library
               var img = document.getElementById('imgThumb2');
               img.src = window.hWin.HAPI4.baseURL + "admin/setup/iconLibrary/64px/" + context;
               img.width = 64;
               img.height = 64;
            }
        }else{
            _rectype_icon = 'set';
            var img = document.getElementById('imgThumb');
            if(!img) img = document.getElementById('imgThumb2');
            img.src = window.hWin.HAPI4.iconBaseURL + 'thumb/th_' + rectypeID + '.png&t='+ (new Date()).getTime();
            img.width = 64;
            img.height = 64;
        }
        _isIconWasUpdated = true;
    }


    var sURL = window.hWin.HAPI4.baseURL + "admin/structure/rectypes/uploadRectypeIcon.php?db="
            + db + "&mode="+mode+"&rty_ID="+rectypeID
            + "&rty_Name=" + document.getElementById("rty_Name").value;

    window.hWin.HEURIST4.msg.showDialog(sURL, {
            "close-on-blur": false,
            "no-resize": false,
            height: 600, //(mode==0?200:250),
            width: 1100,
            title:' Select thumbnail and icon',
            callback:function(context){
                icon_refresh(context);
                thumb_refresh(context);
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
    document.getElementById("divIconAndThumbNew").style.display = "block";
    
    document.getElementById("btnSelectIcon").onclick = function(){_upload_icon(3)};


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

            var sURL = window.hWin.HAPI4.baseURL + "admin/structure/rectypes/uploadRectypeIcon.php?db="
            + db + "&mode=4&rty_ID="+rectypeID;
            $.get( sURL, function(response){
            
                if(response=='1'){
                    
                    _rectype_icon = 'set';
                    
                    document.getElementById("rectypeIcon").innerHTML =
                    "<a href=\"javascript:void(0)\" onClick=\"editRectypeEditor.upload_icon(0)\" title=\"Click to change icon\">"+
                    "<img id=\"imgIcon\" src=\""+
                    window.hWin.HAPI4.iconBaseURL + rectypeID + "&t=" + curtimestamp +
                    "\" width=\"16\" height=\"16\"></a>";

                    document.getElementById("rectypeThumb").innerHTML =
                    "<a href=\"javascript:void(0)\" onClick=\"editRectypeEditor.upload_icon(1)\" title=\"Click to change thumbnail\">"+
                    "<img id=\"imgThumb\" src=\""+
                    window.hWin.HAPI4.iconBaseURL + "thumb/th_" + rectypeID + ".png&t=" + curtimestamp +
                    "\" width=\"64\" height=\"64\"></a>";
                }else{
                    _rectype_icon = '';
                    document.getElementById("divIconAndThumb").style.display = "none";
                    document.getElementById("divIconAndThumbNew").style.display = "block";
                    document.getElementById("btnSelectIcon").onclick =  function(){_upload_icon(0)};
                }
            
            });

        } catch(e) {
            alert(e);
        }
        var fi = window.hWin.HEURIST4.rectypes.typedefs.commonNamesToIndex;

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
    fi = window.hWin.HEURIST4.rectypes.typedefs.commonNamesToIndex,
    dbId = Number(window.hWin.HAPI4.sysinfo['db_registeredid']);

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
        switch(window.hWin.HEURIST4.rectypes.typedefs[rectypeID].commonFields[window.hWin.HEURIST4.rectypes.typedefs.commonNamesToIndex.rty_Type]) {
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

    for (index in window.hWin.HEURIST4.rectypes.groups){
        if(!isNaN(Number(index))) {
            rtg_ID = window.hWin.HEURIST4.rectypes.groups[index].id;

            optn = document.createElement("option");
            optn.text = window.hWin.HEURIST4.rectypes.groups[index].name;
            optn.value = rtg_ID;

            groupDropdown.options.add(optn);
            if(rectype) {
                if(rtg_ID == window.hWin.HEURIST4.rectypes.typedefs[rectypeID].commonFields[window.hWin.HEURIST4.rectypes.typedefs.commonNamesToIndex.rty_RecTypeGroupID]) {
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

//
function updateRectypeOnServer() {
    if($('#btnSave').is(":disabled")) return;
    $('#btnSave').prop('disabled','disabled');
    
    testTitleMask(); //then it calls updateRectypeOnServer_continue
}

function updateRectypeOnServer_continue()
{
    if(!titleMaskIsOk){
    $('#btnSave').prop('disabled','');
        //$('#btnSave').removeAttr('disabled');
        alert("Title mask is invalid");
        return;
    }
    if(_rectype_icon==''){
    $('#btnSave').prop('disabled','');
        //$('#btnSave').removeAttr('disabled');
        alert("Please select icon for new record type");
        return;
    }

    var oRec = getUpdatedFields();

    if(oRec=="mandatory"){
    $('#btnSave').prop('disabled','');
        //$('#btnSave').removeAttr('disabled');
        //do not close the window

    }else if(oRec != null) {
        
        if( !(rectypeID > 0) && _selected_fields==null) {
            _select_fields( updateRectypeOnServer_continue );
            return;
        }

        // TODO: Change base URL
        var baseurl = window.hWin.HAPI4.baseURL + "admin/structure/saveStructure.php";
        var callback = updateResult;
        
        var request = {method:'saveRT', 
            db:window.hWin.HAPI4.database, 
            data: oRec,            
            definit: document.getElementById("definit").checked?1:0};
        
        if(_selected_fields!=null){
            request['newfields'] = _selected_fields;
        }

        if(!(rectypeID>0)){   //assign for new                                          
            request['icon'] = _rectype_icon;
        }

        window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, callback);

    } else { //nothing changed

        var context = null;
        if(_openEditStrucutreAfterClose){
            //editRecStructure();
            context = {rty_ID:rectypeID, isOpenEditStructure:true };
        }else if(_isIconWasUpdated){
            context = {rty_ID:rectypeID, isOpenEditStructure:false };
        }

        _this_window.close(context);
    }
}

var updateResult = function(response) {
    $('#btnSave').prop('disabled','');
    //window.hWin.HEURIST4.rectypes.saveStatus = context; //????

    if(response.status == window.hWin.ResponseStatus.OK){

        var error = false;
        var report = "";

        for(var ind in response.data.result)
        if(ind!=undefined){
            var item = response.data.result[ind];
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

            response = response.data;
            
            response.changeTitleMask = false;
            /* art 2014-05-26 - now it updates in editRectypeTitle
            if(rectypeID>0 &
                document.getElementById("rty_TitleMask").value !== _keepTitleMask){
                context.changeTitleMask = true;
            }*/

            rectypeID = Math.abs(rectypeID);

            
            if(_openEditStrucutreAfterClose){
                //editRecStructure();
                response.isOpenEditStructure = true;
            }

            _this_window.close(response); //send back new HEURIST strcuture
        }
    }else{
        window.hWin.HEURIST4.msg.showMsgErr(response);
    }                                        
}


/**
 * Opens new popup to edit record structure
 */
function editRecStructure()
{
   var sURL = window.hWin.HAPI4.baseURL + "admin/structure/fields/editRecStructure.html?db="+db+"&rty_ID="+rectypeID;
        
   window.hWin.HEURIST4.msg.showDialog(sURL, {     
            "close-on-blur": false,
            "no-resize": false,
            title: 'RECORD STRUCTURE',
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
        var fieldNames = window.hWin.HEURIST4.rectypes.typedefs.commonFieldNames;
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

        //var str = JSON.stringify(oRectype);

        return oRectype;
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
        var rectypeValues = window.hWin.HEURIST4.rectypes.typedefs[rectypeID].commonFields;
    }

    var swarn = window.hWin.HEURIST4.ui.validateName($('#rty_Name').val(), "Field 'Name'");
    if(swarn!=''){
        if(isShowWarn) alert(swarn);
        document.getElementById("rty_Name").focus();
        updatedFields = [];
        return "mandatory";
    }
    

    var fieldNames = window.hWin.HEURIST4.rectypes.typedefs.commonFieldNames;

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
        if(document.getElementById("definit").checked && window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME']){
            val = "["+window.hWin.HEURIST4.detailtypes.names[window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME']]+"]";
        }

        document.getElementById("rty_TitleMask").value = val
        titleMaskIsOk = true;
        updateRectypeOnServer_continue();

    }else{

        var mask = document.getElementById("rty_TitleMask").value;
        
        var baseurl = window.hWin.HAPI4.baseURL + "hsapi/controller/rectype_titlemask.php";

        var request = {rty_id:rectypeID, mask:mask, db:window.hWin.HAPI4.database, check:1}; //verify titlemask
        
        window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, 
            function (response) {
                if(response.status != window.hWin.ResponseStatus.OK || response.message){
                    titleMaskIsOk = false;
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }else{
                    titleMaskIsOk = true;
                    updateRectypeOnServer_continue();
                }                                        
            }
        );

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

   var sURL = window.hWin.HAPI4.baseURL +
        "admin/structure/rectypes/editRectypeTitle.html?rectypeID="+rectypeID+"&mask="+encodeURIComponent(maskvalue)+"&db="+db;
        
   window.hWin.HEURIST4.msg.showDialog(sURL, {     
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

            onPreventChars: function(event) {
                return window.hWin.HEURIST4.ui.preventChars(event);
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
                        _this_window.close(null);
                    }
                }
                else {
                    _this_window.close(null);
                }
            }
        };
        init();
        return that;
};
/*var structureFrame = document.getElementById("structureFrame");
var URL = window.hWin.HAPI4.baseURL + "admin/structure/fields/editRecStructure.html?db="+db+"&rty_ID="+rectypeID;
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

            var recname = window.hWin.HEURIST4.rectypes.names[rectypeID];
            if(recname.length>40) { recname = recname.substring(0,40)+"..."; }
            //find all records that reference this type
            var details = window.hWin.HEURIST4.rectypes.typedefs[rectypeID].dtFields;
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

        var xy = window.hWin.HEURIST4.ui.getMousePos(event);
        //xy = [posx = event.target.x,posy = event.target.y];


        my_tooltip.html(textTip);

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

