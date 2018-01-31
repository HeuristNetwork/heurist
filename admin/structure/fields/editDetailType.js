/**
* editDetailType.js
* A form to edit field type, or create a new field type. It is utilized as pop-up from manageDetailTypes and manageRectypes
* it may call another pop-ups: selectTerms and selectRectype
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @author      Stephen White
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


/**  NOT USED
* Validates value inserted into input field. In this case, make sure it's an integer
* used to Hul.validate order in group value (now hidden)
* @param evt - the evt object for this keypress
*/
function checkIfInteger(evt) {
    if((evt.keyCode) !== 9) {
        var theEvent = evt || window.event;
        var key = theEvent.keyCode || theEvent.which;
        key = String.fromCharCode(key);
        var regex = /[0-9]|\./;
        if( !regex.test(key) ) {
            theEvent.returnValue = false;
            theEvent.preventDefault();
        }
    }
}

//aliases
var Dom = YAHOO.util.Dom,
Hul = top.HEURIST.util;

var detailTypeEditor;
/**
* DetailTypeEditor - class for pop-up edit field type window
*
* public methods
*
* save - sends data to server and closes the pop-up window in case of success
* cancel - checks if changes were made, shows warning and closes the window
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2011.0427
*/

function DetailTypeEditor() {

    var _className = "DetailTypeEditor",
    _detailType, //field type to edit
    _dtyID,     // its ID
    _dty_Type,  // from parameters
    _updatedFields = [], //field names which values were changed to be sent to server
    _updatedDetails = [], //field values
    _keepStatus,// Keeps current status for rollback if user decided to keep it
    _keepType,	// Keeps current datatype for rollback
    _db,
    _dialogbox;

    /**
    * Initialization of input form
    *
    * Reads GET parameters, creates group selector and invokes the method that fills values for inputs
    */
    function _init() {

        var dtgID, _dty_PtrTargetRectypeIDs;

        // reads parameters from GET request
        if (location.search.length > 1) {
            top.HEURIST.parameters = top.HEURIST.parseParams(location.search);
            _dtyID = top.HEURIST.parameters.detailTypeID;
            dtgID = top.HEURIST.parameters.groupID;

            _dty_Type = top.HEURIST.parameters.dty_Type;
            _dty_PtrTargetRectypeIDs = top.HEURIST.parameters.dty_PtrTargetRectypeIDs;
            
            if(_dtyID){
                var dt = top.HEURIST.detailTypes.typedefs[_dtyID];
                if(!Hul.isnull(dt)){
                    _detailType = dt.commonFields;
                }
            }
            if(Hul.isnull(dtgID) && top.HEURIST.detailTypes.groups){
                dtgID = top.HEURIST.detailTypes.groups[0].id;
            }
        }

        _db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));


        if (_dtyID && Hul.isnull(_detailType) ){
            Dom.get("msg").style.visibility = "visible";
            Dom.get("statusMsg").innerHTML = "Error: field type #"+_dtyID+"  not be found. Clicking 'save' button will create a new Field Type.";
        }

        var fi = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex,
        disable_status = false,
        disable_fields = false,
        selstatus = Dom.get("dty_Status"),
        dbId = Number(top.HEURIST.database.id);

        var original_dbId = (Hul.isnull(_detailType))?dbId:Number(_detailType[fi.dty_OriginatingDBID]);
        if(Hul.isnull(original_dbId)) {original_dbId = dbId;}

        if((dbId>0) && (dbId<1001) && (original_dbId===dbId)) {
            _addOptionReserved();
        }

        
        $('input[name="enumType"]').change(
            function(event){
                if($(event.target).val()=='individual'){
                    $('#enumIndividual').css('display','inline-block');//show();
                    $('#enumVocabulary').hide();
                }else{
                    $('#enumIndividual').hide();
                    $('#enumVocabulary').css('display','inline-block');//show();
                }
            }
        );


        //creates new empty field type in case ID is not defined
        if(Hul.isnull(_detailType)){
            _dtyID =  -1;

            _initFieldTypeComboBox();

            _detailType = new Array();

            _detailType[fi.dty_Name] = '';
            _detailType[fi.dty_ExtendedDescription] = '';
            _detailType[fi.dty_Type] = '';
            _detailType[fi.dty_OrderInGroup] = 0;
            _detailType[fi.dty_HelpText] = '';
            _detailType[fi.dty_ShowInLists] = 1;
            _detailType[fi.dty_Status] = 'open';
            _detailType[fi.dty_DetailTypeGroupID] = dtgID;
            _detailType[fi.dty_FieldSetRectypeID] = null;
            _detailType[fi.dty_JsonTermIDTree] = null;
            _detailType[fi.dty_TermIDTreeNonSelectableIDs] = null;
            _detailType[fi.dty_PtrTargetRectypeIDs] = null;
            _detailType[fi.dty_NonOwnerVisibility] = 'viewable';

            
            //take type from parameters
            if(_dty_Type && top.HEURIST.detailTypes.lookups[_dty_Type]){ //valid type
                _detailType[fi.dty_Type] = _dty_Type;
            }else{
                Dom.get("dty_Type").disabled = false;    
            }
            //
            if(_dty_PtrTargetRectypeIDs){  
                _detailType[fi.dty_PtrTargetRectypeIDs] = _dty_PtrTargetRectypeIDs;
            }

            
            $("#topdiv_closebtn").click(function(){if(_dialogbox) top.HEURIST.util.closePopup(_dialogbox.id);});
            $('#btnSave').attr('value','Create Field');
        }else{
            $('#btnSave').attr('value','Save Field');
            
            //var el = Dom.get("dty_Type");
            //el.val(top.HEURIST.detailTypes.lookups[value]);
            _dty_Type = _detailType[fi.dty_Type];
            
            if(_detailType[fi.dty_Status]==='reserved'){ //if reserved - it means original dbid<1001

                disable_status = (original_dbId!==dbId) && (original_dbId>0) && (original_dbId<1001);
                disable_fields = true;
                _addOptionReserved();

            }else if(_detailType[fi.dty_Status]==='approved'){
                disable_fields = true;
            }
        }
        
        if(_dty_Type && top.HEURIST.detailTypes.lookups[_dty_Type]){ //valid type            
            
            var el = Dom.get("dty_Type");

            Hul.addoption(el, _dty_Type, top.HEURIST.detailTypes.lookups[_dty_Type]);
            el.disabled = false;

            if(_dty_Type=='float' || _dty_Type=='date'){
                Hul.addoption(el, 'freetext', top.HEURIST.detailTypes.lookups['freetext']);
            }else if(_dty_Type=='freetext'){
                Hul.addoption(el, 'blocktext', top.HEURIST.detailTypes.lookups['blocktext']);
            }else if(_dty_Type=='blocktext'){
                Hul.addoption(el, 'freetext', top.HEURIST.detailTypes.lookups['freetext']);
            }else{
                el.disabled = true;
            }
        }
                
        

        //disable if reserved
        _toggleAll(disable_status || disable_fields, disable_status);

        _keepStatus = _detailType[fi.dty_Status]; // Keeps current status for rollback
        selstatus.value = _keepStatus;
        _keepType = _detailType[fi.dty_Type]; // Keeps current datatype

        // creates and fills group selector
        _initGroupComboBox();

        //fills input with values from _detailType array
        _fromArrayToUI();

        setTimeout(function(){Dom.get("dty_Name").focus();},1000);
    }

    /**
    * adds reserved option to status dropdown list
    */
    function _addOptionReserved(){
        var selstatus = Dom.get("dty_Status");
        if(selstatus.length<4){
            Hul.addoption(selstatus, 'reserved','reserved');
        }
    }

    /**
    * Toggle fields to disable. Is called when status is set to 'Reserved'.
    */
    function _toggleAll(disable, reserved) {

        //Dom.get("dty_Name").disabled = disable;
        //Dom.get("dty_DetailTypeGroupID").disabled = disable;
        Dom.get("dty_Status").disabled = reserved;
        Dom.get("dty_OrderInGroup").disabled = disable;
        //Dom.get("dty_ShowInLists").disabled = disable;
        /* Ian's request 2012-02-07
        Dom.get("termsPreview").disabled = disable;
        Dom.get("btnSelTerms").disabled = disable;
        Dom.get("btnSelRecType1").disabled = disable;
        Dom.get("btnSelRecType2").disabled = disable;
        */
    }

    function _preventSel(event){
        event.target.selectedIndex=0;
    }

    /**
    * recreateTermsVocabSelector
    * creates and fills selector with list of Vocabularies
    */
    function _recreateTermsVocabSelector(datatype, toselect)  {

//        var prev = Dom.get("termsVocab");
//        prev.innerHTML = "";

        if(Hul.isempty(datatype)) return;

        var vocabId = toselect ?toselect: Number(Dom.get("dty_JsonTermIDTree").value),
        sel_index = -1;
        if(isNaN(vocabId)){
            vocabId = 0;
        }

        var dom = (datatype === "relation" || datatype === "relmarker" || datatype === "relationtype")?"relation":"enum",
        fi_label = top.HEURIST.terms.fieldNamesToIndex['trm_Label'];
        var termID,
        termName,
        termTree = top.HEURIST.terms.treesByDomain[dom],
        terms = top.HEURIST.terms.termsByDomainLookup[dom];

        var el_sel = $('#selVocab').get(0);
        $(el_sel).empty();

        Hul.addoption(el_sel, -1, 'select...');
        
        //add to temp array
        var vocabs = [];
        for(termID in termTree) { // For every term in first levet of tree
            if(!Hul.isnull(termID)){
                termName = terms[termID][fi_label];
                vocabs.push({key:termID, title:termName });
            }
        }
        //sort array 
        vocabs.sort(function(a,b){ return a.title<b.title?-1:1; });

        for(var idx in vocabs) { // For every term in first levet of tree
            if(vocabs[idx]){
                Hul.addoption(el_sel, vocabs[idx].key, vocabs[idx].title);
                if(Number(vocabs[idx].key)==vocabId){
                    sel_index = el_sel.length-1;
                }
            }
        }

        if(sel_index<0) {
            sel_index = (Dom.get("dty_JsonTermIDTree").value!='' && vocabId==0)?el_sel.length-1:0;
        }
        el_sel.selectedIndex = sel_index;

        el_sel.onchange =  _changeVocabulary;
        el_sel.style.maxWidth = '120px';

        _changeVocabulary(null);
    }

    /**
    *
    */
    function _changeVocabulary(event){

        var el_sel;

        if(event){
            el_sel = event.target;
        }else{
            el_sel = Dom.get("selVocab");
        }

        var    btn_addsel = Dom.get("btnAddSelTerm"),
        editedTermTree = "",
        divAddSelTerm = Dom.get("divAddSelTerm"),
        divAddVocab = Dom.get("divAddVocab"),
        divAddVocab2 = Dom.get("divAddVocab2");
  
        if(el_sel.value > 0){ //individual selection
            editedTermTree = el_sel.value;
        }

        if(event){
            Dom.get("dty_JsonTermIDTree").value = editedTermTree;
            Dom.get("dty_TermIDTreeNonSelectableIDs").value = "";
            _recreateTermsPreviewSelector(Dom.get("dty_Type").value, editedTermTree, "");
        }

    }

    /**
    * recreateTermsPreviewSelector
    * creates and fills selector for Terms Tree if datatype is enum, relmarker, relationtype
    * @param datatype an datatype
    * @allTerms - JSON string with terms
    * @disabledTerms  - JSON string with disabled terms
    */
    function _recreateTermsPreviewSelector( datatype, allTerms, disabledTerms ) {

        allTerms = Hul.expandJsonStructure(allTerms);
        disabledTerms = Hul.expandJsonStructure(disabledTerms);

        if (typeof disabledTerms.join === "function") {
            disabledTerms = disabledTerms.join(",");
        }

        //remove old selector
        var prev = Dom.get("termsPreview1"),
        i;
        
        $(prev).empty();
        $('#termsPreview2').empty();

        if(!Hul.isempty(allTerms)) {
            var el_sel = Hul.createTermSelect(allTerms, disabledTerms, datatype, null);
            el_sel.style.backgroundColor = "#cccccc";
            el_sel.onchange =  _preventSel;
            $(el_sel).addClass('sel_width');
            $(prev).append($('<label style="width:60px;min-width:60px">Preview</label>'));                            
            prev.appendChild(el_sel);
            $('#termsPreview2').append($('<label style="width:60px;min-width:60px">Preview</label>'));
            $('#termsPreview2').append($(el_sel).clone());
        }else{
            //$(prev).css('display','none');
        }
    }

    /**
    * recreateRecTypesPreview - creates and fills selector for Record(s) pointers if datatype
    * is fieldsetmarker, relmarker, resource
    *
    * @param type an datatype
    * @value - comma separated list of rectype IDs
    */
    function _recreateRecTypesPreview(type, value) {

        var divRecType = Dom.get( (type==="fieldsetmarker")? "dty_FieldSetRecTypeIDPreview" : "dty_PtrTargetRectypeIDsPreview" );
        var txt = "";
        if(divRecType===null) {
            return;
        }

        if(value) {
            var arr = value.split(","),
            newvalue = "",
            ind, dtName;
            for (ind in arr) {
                var ind2 = Number(arr[Number(ind)]);
                if(!isNaN(ind2)){
                    dtName = top.HEURIST.rectypes.names[ind2];
                    if(!Hul.isnull(dtName)){
                        if (!txt) {
                            newvalue = ind2;
                            txt = dtName;
                        }else{
                            newvalue += "," + ind2;
                            txt += ", " + dtName;
                        }
                    }
                }
            } //for

            if(value!=newvalue){ //some records may be deleted - remove them from list
                _setRecordsPointerValue(newvalue);
            }
        }
        if(txt==""){
            txt = "unconstrained";
        }
        if (!Hul.isnull(txt) && txt.length > 40){
            divRecType.title = txt;
            txt = txt.substr(0,40) + "&#8230";
        }else{
            divRecType.title = "";
        }
        divRecType.innerHTML = txt;
    }

    /**
    * _onAddVocabOrTerms
    *
    * Add new vocavulary or add child to currently selected
    */
    function _onAddVocabOrTerms(is_add_vocab){

        var type = Dom.get("dty_Type").value;
        var allTerms = Dom.get("dty_JsonTermIDTree").value;
        var disTerms = Dom.get("dty_TermIDTreeNonSelectableIDs").value;

        if(type!="enum"){
            type="relation";
        }

        var el_sel = Dom.get("selVocab");
        
        var vocab_id =  el_sel.value>0?el_sel.value:''; //keep value

        Hul.popupURL(top, top.HEURIST.baseURL +
                "admin/structure/terms/editTermForm.php?treetype="+type+"&parent="+(is_add_vocab?0:el_sel.value)+"&db="+_db,
                {
                    "close-on-blur": false,
                    "no-resize": true,
                    "no-close": true, //hide close button
                    title: 'Edit Vocabulary',
                    height: 340,
                    width: 700,
                    callback: function(context) {
                        if(context!="") {

                            if(context=="ok"){    //after edit term tree
                                //_recreateTermsPreviewSelector(type, allTerms, "");
                                _recreateTermsVocabSelector(type, vocab_id);
                                _recreateTermsPreviewSelector(type, vocab_id, "");
                                
                            }else if(!Hul.isempty(context)) { //after add new vocab
                                Dom.get("dty_JsonTermIDTree").value =  context;
                                Dom.get("dty_TermIDTreeNonSelectableIDs").value = "";
                                _recreateTermsVocabSelector(type, context);
                                _recreateTermsPreviewSelector(type, context, null);
                            }
                        }
                    }
            });

    }

    /**
    * onSelectTerms
    *
    * Shows a popup window where user can select terms to create a term tree as wanted
    */
    function _onSelectTerms(){
        
        var type = Dom.get("dty_Type").value;
        var allTerms = Dom.get("dty_JsonTermIDTree").value;
        var disTerms = Dom.get("dty_TermIDTreeNonSelectableIDs").value;

        if(type!="enum"){
            type="relation";
        }
        
        
            Hul.popupURL(top, top.HEURIST.baseURL +
                "admin/structure/terms/selectTerms.html?dtname="+_dtyID+"&datatype="+type+"&all="+allTerms+"&dis="+disTerms+"&db="+_db,
                {
                    "close-on-blur": false,
                    "no-resize": true,
                    title: 'Select terms',
                    height: 500,
                    width: 750,
                    callback: function(editedTermTree, editedDisabledTerms) {
                        if(editedTermTree || editedDisabledTerms) {
                            //update hidden fields
                            Dom.get("dty_JsonTermIDTree").value = editedTermTree;
                            Dom.get("dty_TermIDTreeNonSelectableIDs").value = editedDisabledTerms;
                            _recreateTermsPreviewSelector(type, editedTermTree, editedDisabledTerms);
                        }
                    }
            });

    }

    /**
    * onSelectRectype
    *
    * listener of "Select Record Type" buttons
    * Shows a popup window where you can select record types
    */
    function _onSelectRectype()
    {

        var type = Dom.get("dty_Type").value;
        if(type === "relmarker" || type === "resource" || type === "fieldsetmarker")
        {

            var args,URL;

            if(type === "fieldsetmarker") {
                if(Dom.get("dty_FieldSetRecTypeID")) {
                    args = Dom.get("dty_FieldSetRecTypeID").value;
                }
            }
            if(type === "relmarker" || type === "resource") {
                if(Dom.get("dty_PtrTargetRectypeIDs")) {
                    args = Dom.get("dty_PtrTargetRectypeIDs").value;
                }
            }

            if(args) {
                URL =  top.HEURIST.baseURL + "admin/structure/rectypes/selectRectype.html?type=" + type + "&ids=" + args+"&db="+_db;
            } else {
                URL =  top.HEURIST.baseURL + "admin/structure/rectypes/selectRectype.html?type=" + type+"&db="+_db;
            }

            Hul.popupURL(top, URL, {
                "close-on-blur": false,
                "no-resize": true,
                title: 'Select Record Type',
                height: 480,
                width: 540,
                callback: function(recordTypesSelected) {
                    if(!Hul.isnull(recordTypesSelected)) {
                        _setRecordsPointerValue(recordTypesSelected);
                        _recreateRecTypesPreview(type, recordTypesSelected);
                    }
                }
            });
        }
    }

    function _setRecordsPointerValue(recordTypesSelected)
    {
        var type = Dom.get("dty_Type").value;
        if(type === "fieldsetmarker") { // Change comma seperated list to right format
            Dom.get("dty_FieldSetRecTypeID").value = recordTypesSelected;
        } else {
            Dom.get("dty_PtrTargetRectypeIDs").value = recordTypesSelected;
        }
    }


    /**
    * Initialization of group selector
    *
    * Gets all groups in HEURIST DB, creates and adds oprions to group selector
    */
    function _initGroupComboBox() {

        var el = Dom.get("dty_DetailTypeGroupID"),
        dtg_ID,
        index;

        for (index in top.HEURIST.detailTypes.groups){
            if(!isNaN(Number(index))) {
                dtg_ID = top.HEURIST.detailTypes.groups[index].id;
                var grpName = top.HEURIST.detailTypes.groups[index].name;

                Hul.addoption(el, dtg_ID, grpName);
            }
        } //for
    }

    /**
    * Init field type selector
    */
    function _initFieldTypeComboBox() {
        var el = Dom.get("dty_Type"),
        text,
        value;

        Hul.addoption(el, "", "-- Select data type --");

        for (value in top.HEURIST.detailTypes.lookups){
            if(!Hul.isnull(Number(value))) {
                if(!(value==="relationtype" || value==="year" || value==="boolean" || value==="integer")){
                    text = top.HEURIST.detailTypes.lookups[value];
                    Hul.addoption(el, value, text);
                }
            }
        } //for
    }

    /**
    *  Fills inputs with values from _detailType array
    */
    function _fromArrayToUI(){

        var i,
        el,
        fnames = top.HEURIST.detailTypes.typedefs.commonFieldNames,
        fi = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex;

        if (Hul.isempty(_detailType[fi.dty_ConceptID])){
            Dom.get('div_dty_ConceptID').innerHTML = '';
        }else{
            Dom.get('div_dty_ConceptID').innerHTML = 'Concept ID: '+_detailType[fi.dty_ConceptID];
        }

        if(_dtyID==top.HEURIST.magicNumbers['DT_RELATION_TYPE']){
            _detailType[fi.dty_JsonTermIDTree] = "0";
            _detailType[fi.dty_TermIDTreeNonSelectableIDs] = "";
            Dom.get('defineTerms').style.display = 'none';
        }


        for (i = 0, l = fnames.length; i < l; i++) {
            var fname = fnames[i];
            el = Dom.get(fname);
            if(!Hul.isnull(el)){
                if ( fname==='dty_ShowInLists' ) { // dty_ShowInLists
                    el.checked = (Number(_detailType[fi.dty_ShowInLists])===1);
                }else{
                    el.value = _detailType[i];
                }
            }
        }

        //to trigger setting visibilty for div with terms tree and record pointer
        that.keepType = Dom.get("dty_Type").value;
        _onChangeType(null);

        // create preview for Terms Tree and record pointer
        var vocabId = Hul.isempty(Dom.get("dty_JsonTermIDTree").value)?0:Number(Dom.get("dty_JsonTermIDTree").value);
        $('input[name="enumType"][value="'+(isNaN(vocabId)?'individual':'vocabulary')+'"]').attr('checked',true).change();
        
        _recreateTermsVocabSelector(_detailType[fi.dty_Type], null);
        _recreateTermsPreviewSelector(
            _detailType[fi.dty_Type],
            _detailType[fi.dty_JsonTermIDTree],
            _detailType[fi.dty_TermIDTreeNonSelectableIDs]);

        _recreateRecTypesPreview(_detailType[fi.dty_Type],
            ((_detailType[fi.dty_Type]==="fieldsetmarker")
                ?_detailType[fi.dty_FieldSetRectypeID]:_detailType[fi.dty_PtrTargetRectypeIDs]) );

        if (_dtyID<0){
            Dom.get("dty_ID").innerHTML = '<span style="color:#999">will be automatically assigned</span>';
            document.title = "Create new base field type";
        }else{
            Dom.get("dty_ID").innerHTML =  _dtyID;
            document.title = "Field Type # " + _dtyID+" '"+_detailType[fi.dty_Name]+"'";
            var aUsage = top.HEURIST.detailTypes.rectypeUsage[_dtyID];
            var iusage = (Hul.isnull(aUsage)) ? 0 : aUsage.length;
            var warningImg = "<img src='" + top.HEURIST.baseURL + "common/images/url_warning.png'>";

            if(iusage > 0) {

                Dom.get("msg").style.visibility = "visible";
                Dom.get("statusMsg").innerHTML = warningImg + "WARNING: Changes to this field type will affect all record types (" + iusage + ") in which it is used";

            }
        }
                
        if (Hul.isempty(_dty_Type)){
            $("#typeValue").show();
            $("#dty_Type").hide();
        }else{
            $("#typeValue").hide();
            $("#dty_Type").show();
        }
    }


    /**
    * Stores the changed values and verifies mandatory fields
    *
    * Compares data in input with values and in _detailType array, then
    * gathers changed values from UI elements (inputs) into 2 arrays _updatedFields and _updatedDetails
    * this function is invoked in 2 places:
    * 1) in cancel method - to check if something was changed and show warning
    * 2) in save (_updateDetailTypeOnServer) - to gather the data to send to server
    *
    * @param isShowWarn - show alert about empty mandatory fields, it is false for cancel
    * @return "mandatory" in case there are empty mandatory fields (it prevents further saving on server)
    *           or "ok" if all mandatory fields are filled
    */
    function _fromUItoArray(isShowWarn){

        _updatedFields = [];
        _updatedDetails = [];

        var el = Dom.get("dty_ShowInLists");
        el.value = el.checked?1:0;

        var i;
        var fnames = top.HEURIST.detailTypes.typedefs.commonFieldNames;

        //take only changed values
        for (i = 0, l = fnames.length; i < l; i++){
            var fname = fnames[i];
            el = $('#'+fname);
      
            if( el.length>0 ){
                if (Hul.isempty(el.val())) {
                    if(fname=='dty_Status'){
                        el.val('open');
                    }else if(fname=='dty_NonOwnerVisibility'){
                        el.val('viewable');
                    }
                }

        
                if(_dtyID<0 || (el.val()!==String(_detailType[i]) && !(el.val()==="" && _detailType[i]===null)))
                {
                    _updatedFields.push(fname);
                    _updatedDetails.push(el.val());
                }
            }
            
            
            /*el = Dom.get(fname);
            if( !Hul.isnull(el) ){
                if(_dtyID<0 || (el.value!==String(_detailType[i]) && !(el.value==="" && _detailType[i]===null)))
                {
                    _updatedFields.push(fname);
                    _updatedDetails.push(el.value);
                }
            }*/
        }

        // check mandatory fields
        var swarn = "";
        var dt_name = Dom.get("dty_Name").value;
        if(dt_name==="") {
            swarn = "Name is mandatory field"
        }else{
            swarn = Hul.validateName(dt_name, "Field 'Default field type name'");
        }
        if(swarn!=""){
            if(isShowWarn) {
                top.HEURIST.util.showError(swarn);
            }
            Dom.get("dty_Name").focus();
            _updatedFields = [];
            return "mandatory";
        }

        if(Dom.get("dty_HelpText").value==="") {
            if(isShowWarn) {
                top.HEURIST.util.showError("Help text is mandatory field");
            }
            Dom.get("dty_HelpText").focus();
            _updatedFields = [];
            return "mandatory";
        }

        if(Dom.get("dty_Type").value==="enum"){
            var dd = Dom.get("dty_JsonTermIDTree").value;
            if( dd==="" || dd==="{}" ) {
                if(isShowWarn) {
                    top.HEURIST.util.showError(
                        'Please select or add a vocabulary. Vocabularies must contain at least one term.', 'Warning');
                }
                _updatedFields = [];
                return "mandatory";
            }
        }
        var val = Dom.get("dty_Type").value;
        if(Hul.isempty(val)){
            if(isShowWarn) {
                top.HEURIST.util.showError("Data Type is mandatory field");
            }
            Dom.get("dty_Type").focus();
            _updatedFields = [];
            return "mandatory";
        }

        return "ok";
    }

    /**
    * Http response listener
    *
    * shows information about result of operation of saving on server and closes this pop-up window in case of success
    *
    * @param context - data from server
    */
    function _updateResult(context) {
        $('#btnSave').removeAttr('disabled');

        if(!Hul.isnull(context)){

            var error = false,
            report = "",
            ind;

            for(ind in context.result){
                if( !Hul.isnull(ind) ){
                    var item = context.result[ind];
                    if(isNaN(item)){
                        Hul.showError(item);
                        error = true;
                    }else{
                        _dtyID = Number(item);
                        if(report!=="") {
                            report = report + ",";
                        }
                        report = report + Math.abs(_dtyID);
                    }
                }
            }

            if(!error){
                var ss = (_dtyID < 0)?"added":"updated";

                if(report.indexOf(",")>0){
                    // this alert is a pain: alert("Field types with IDs :"+report+ " were succesfully "+ss);
                }else{
                    // this alert is a pain: alert("Field type with ID " + report + " was succesfully "+ss);
                }
                window.close(context); //send back new HEURIST strcuture
            }
        }
    }

    /**
    * Apply form
    * private method for public method "save"
    * 1. gather changed data from UI (_fromUItoArray) to _updatedFields, _updatedDetails
    * 2. creates object to be sent to server
    * 3. sends data to server
    */
    function _updateDetailTypeOnServer()
    {
        if($('#btnSave').is(":disabled")) return;
        $('#btnSave').attr('disabled','disabled');

        //1. gather changed data
        if(_fromUItoArray(true)==="mandatory"){ //save all changes
            $('#btnSave').removeAttr('disabled');
            return;
        }

        var str = null;

        //2. creates object to be sent to server
        if(_dtyID !== null && _updatedFields.length > 0){
            var k,
            val;
            var oDetailType = {detailtype:{
                colNames:{common:[]},
                defs: {}
            }};

            //fill array of updated fieldnames
            //var fieldNames = top.HEURIST.detailTypes.typedefs.commonFieldNames;

            var values = [];
            for(k = 0; k < _updatedFields.length; k++) {
                oDetailType.detailtype.colNames.common.push(_updatedFields[k]);
                values.push(_updatedDetails[k]);
            }

            oDetailType.detailtype.defs[_dtyID] = {};
            oDetailType.detailtype.defs[_dtyID].common = [];
            for(val in values) {
                oDetailType.detailtype.defs[_dtyID].common.push(values[val]);
            }
            str = YAHOO.lang.JSON.stringify(oDetailType);
        }


        if(str !== null) {

            // 3. sends data to server
            var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
            var callback = _updateResult;
            var params = "method=saveDT&db="+_db+"&data=" + encodeURIComponent(str);
            Hul.getJsonData(baseurl, callback, params);
        } else {
            window.close(null);
        }
    }


    

    /**
    * onChangeType - listener for datetype selector
    *
    * Sets visibilty for div with terms tree and record pointer
    * Clears hidden fields for term tree and pointer in case of changing type
    * is invoked explicitely in _fromArrayToUI
    */
    function _onChangeType(event){

        var el = Dom.get("dty_Type"); //event.target;

        //prevent setting outdated types for new field type
        if( event!=null && (el.value==="relationtype" || el.value==="year" || el.value==="boolean" || el.value==="integer")){
            top.HEURIST.util.showError('You selected an outdated type. It is not allowed anymore');
            if(that.keepType){
                el.value = that.keepType;
            }else{
                el.selectedIndex = 0;
            }
            return;
        }

        Dom.get("pnl_relmarker").style.display = "none";
        Dom.get("pnl_enum").style.display = "none";
        Dom.get("pnl_fieldsetmarker").style.display = "none";

        var changeToNewType = true;
        if( event!=null && ((that.keepType==="resource") || (that.keepType==="relmarker") || (that.keepType==="enum")
            || (that.keepType==="relationtype") || (that.keepType==="fieldsetmarker"))
            && el.value!==that.keepType){
            changeToNewType = confirm("If you change the type to '"+el.value+	//saw TODO change this to the selected options text
                "' you will lose all your vocabulary settings for type '"+that.keepType+
                "'.\n\nAre you sure?");
        }

        if(changeToNewType) {
            //clear hidden fields
            if(that.keepType!=el.value){
                
                Dom.get("dty_JsonTermIDTree").value = "";
                Dom.get("dty_TermIDTreeNonSelectableIDs").value = "";
                Dom.get("dty_PtrTargetRectypeIDs").value = "";
                Dom.get("dty_FieldSetRecTypeID").value = "";
                that.keepType = el.value;
                _recreateTermsVocabSelector(that.keepType, null);
                _recreateTermsPreviewSelector(that.keepType, null, null);
                _recreateRecTypesPreview(that.keepType, null);

                if((el.value=="freetext" || el.value=="blocktext") && _dtyID<0){
                    if(hasH4()){
                        $("#topdiv_closebtn").hide();
                    }
                    $("#field_types_context_help").load(top.HEURIST.baseURL+'context_help/field_data_types.html #content_body');
                    
                    _dialogbox = Hul.popupElement(window, $("#info_div").get(0), {height: 550, width:800, title:"Choosing appropriate field types", modal:true} );
                }
            }
        }else{
            el.value = that.keepType;  //rollback
            Dom.get("typeValue").value = top.HEURIST.detailTypes.lookups[that.keepType];
        }

        // setting visibility
        switch(el.value)
        {
            case "resource":
                Dom.get("pnl_relmarker").style.display = "block";
                break;
            case "relmarker":
                Dom.get("pnl_relmarker").style.display = "block";
            case "enum":
            case "relationtype":
                Dom.get("pnl_enum").style.display = "block";
                break;
            case "fieldsetmarker": //NOT USED ANYMORE
                Dom.get("pnl_fieldsetmarker").style.display = "block";
                break;
            default:
        }
    }

    /**
    *	status selector listener (not used)
    */
    function _onChangeStatus(e){

        var el = e.target;
        if(el.value === "reserved") {
            var changeToReserved = confirm("If you change the status to 'reserved'," +
                " some fields of this detailtype will no longer be able to change.\n\nAre you sure?");
            if(changeToReserved) {
                _toggleAll(true, false);
            } else {
                el.value = that.keepStatus; //restore previous value
            }
        }else if(el.value === "approved") {
            that.keepStatus = el.value;
            _toggleAll(true, false);
        } else {
            that.keepStatus = el.value;
            _toggleAll(false, false);
        }

    }

    function _onPreventChars(event){

        event = event || window.event;
        var charCode = typeof event.which == "number" ? event.which : event.keyCode;
        if (charCode && charCode > 31)
        {
            var keyChar = String.fromCharCode(charCode);
            // Old test only allowed specific characters, far too restrictive. New test only restrcts characters which will pose a problem
            // if(!/^[a-zA-Z0-9$_<> /,–—]+$/.test(keyChar)){
            if(/^[{}'"\[\]]+$/.test(keyChar)){
                event.cancelBubble = true;
                event.returnValue = false;
                event.preventDefault();
                if (event.stopPropagation) event.stopPropagation();
                
                if(hasH4()){
                    window.hWin.HEURIST4.msg.showMsgFlash('Restricted characters: [ ] { } \' "',700,null,event.target);
                }
                setTimeout(function(){
                        $(event.target).focus();
                }, 750);
                
                return false;
                
            }
        }
        return true;
    }
        
    
    //public members
    var that = {

        /** Keeps current status for rollback if user decided to keep it */
        keepStatus: _keepStatus,
        /** Keeps current datatype for rollback  */
        keepType: _keepType,

        /**
        *	Apply form - sends data to server and closes this pop-up window in case of success
        */
        save : function () {
            _updateDetailTypeOnServer();
        },

        onPreventChars: function(event) {
            _onPreventChars(event);
        },        
        
        //
        //
        onDataTypeClick: function(event){
			
		    var el = Dom.get("dty_Type"); //e.target;
		    if(true){ //}!el.value){
		    	
		    	
			var body = $(this.document).find('body');
            var dim = { h:530, w:800 };//Math.max(900, body.innerWidth()-10) };		    	
		    	
            Hul.popupURL(window, top.HEURIST.baseURL +
                "admin/structure/fields/selectFieldType.html?&db="+_db,
                {
                    "close-on-blur": false,
                    //"no-resize": true,
                    //"no-close": true, //hide close button
                    title: 'Select data type of field',
                    height: dim.h,
                    width: dim.w,
                    callback: function(context) {
                        if(context!="" && context!=undefined) {
                            
                            var changeToNewType = true;
                            if(((el.value==="resource") || (el.value==="relmarker") || (el.value==="enum"))  && el.value!==context){
                                changeToNewType = confirm("If you change the type to '"+top.HEURIST.detailTypes.lookups[context]+ 
                                    "' you will lose all your vocabulary settings for type '"+top.HEURIST.detailTypes.lookups[el.value]+
                                    "'.\n\nAre you sure?");
                            }

                            if(changeToNewType) {
                               Dom.get("typeValue").value = top.HEURIST.detailTypes.lookups[context];
                               el.value = context;
                                _onChangeType(null);
                            }                            
                                                
                            
                        }
                    }
              });
				
		    }
	
		},
        
        /**
        *	handles change type event
        */
        onChangeType : _onChangeType,

        /**
        *	handles change status event
        */
        onChangeStatus : _onChangeStatus,

        /**
        *	handles change status event
        */
        onAddTermsToVocab : function() { _onAddVocabOrTerms(false); },

        onAddVocabulary : function() { _onAddVocabOrTerms(true); },

        onSelectTerms : function() { _onSelectTerms(); },
        
        /**
        *	handles change status event
        */
        onSelectRectype : _onSelectRectype,

        showOtherTerms: function(){

            var allTerms = Dom.get("dty_JsonTermIDTree").value;
            var type = Dom.get("dty_Type").value;
            if(type!="enum"){
                type="relation";
            }

            var el_sel = Dom.get("selVocab");
            var vocab_id =  el_sel.value>0?el_sel.value:'';

            top.HEURIST.util.popupURL(top, top.HEURIST.baseURL + "admin/structure/terms/editTerms.php?"+
                "popup=1&vocabid="+vocab_id+"&treetype="+type+"&db="+_db,
                {
                    "close-on-blur": false,
                    "no-resize": false,
                    title: 'Edit Terms',
                    height: 750,
                    width: 1200,
                    callback: function(needTreeReload) {
                        _recreateTermsVocabSelector(type, vocab_id);
                        _recreateTermsPreviewSelector(type, vocab_id, "");
                    }
            });

        },


        /**
        * Cancel form - checks if changes were made, shows warning and closes the window
        */
        cancel : function () {
            _fromUItoArray(false);
            if(_updatedFields.length > 0) {
                var areYouSure = confirm("Changes were made. By cancelling, all changes will be lost. Are you sure?");
                if(areYouSure) {
                    window.close(null);
                }
            }else{
                window.close(null);
            }
        },

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            return (strClass === _className);
        }

    };

    _init();  // initialize before returning
    return that;
}
