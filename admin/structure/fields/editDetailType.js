/**
* editDetailType.js
* A form to edit field type, or create a new field type. It is utilized as pop-up from manageDetailTypes and manageRectypes
* it may call another pop-ups: selectTerms and selectRectype
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

//aliases
var Hul = window.hWin.HEURIST4.util;

var detailTypeEditor;
/**
* DetailTypeEditor - class for pop-up edit field type window
*
* public methods
*
* save - sends data to server and closes the pop-up window in case of success
* cancel - checks if changes were made, shows warning and closes the window
*
* @author Artem Osmakov <artem.osmakov@sydney.edu.au>
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
            
            _dtyID = window.hWin.HEURIST4.util.getUrlParameter('detailTypeID', location.search);
            dtgID = window.hWin.HEURIST4.util.getUrlParameter('groupID', location.search);
            _dty_Type = window.hWin.HEURIST4.util.getUrlParameter('dty_Type', location.search);
            _dty_PtrTargetRectypeIDs = window.hWin.HEURIST4.util.getUrlParameter('dty_PtrTargetRectypeIDs', location.search);
            
            if(_dtyID){
                var dt = window.hWin.HEURIST4.detailtypes.typedefs[_dtyID];
                if(!Hul.isnull(dt)){
                    _detailType = dt.commonFields;
                }
            }
            if(Hul.isnull(dtgID) && window.hWin.HEURIST4.detailtypes.groups){
                dtgID = window.hWin.HEURIST4.detailtypes.groups[0].id;
            }
        }

        _db = window.hWin.HAPI4.database;


        if (_dtyID && Hul.isnull(_detailType) ){
            $("#msg").css('visibility', "visible");
            $("#statusMsg").html("Error: field type #"+_dtyID+"  not be found. Clicking 'save' button will create a new Field Type.");
        }

        var fi = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex,
        disable_status = false,
        disable_fields = false,
        selstatus = document.getElementById("dty_Status"),
        dbId = Number(window.hWin.HAPI4.sysinfo['db_registeredid']);

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
            if(_dty_Type && window.hWin.HEURIST4.detailtypes.lookups[_dty_Type]){ //valid type
                _detailType[fi.dty_Type] = _dty_Type;
            }else{
                document.getElementById("dty_Type").disabled = false;    
            }
            //
            if(_dty_PtrTargetRectypeIDs){  
                _detailType[fi.dty_PtrTargetRectypeIDs] = _dty_PtrTargetRectypeIDs;
            }

            
            //@todo $("#topdiv_closebtn").click(function(){if(_dialogbox) top.HEURIST.util.closePopup(_dialogbox.id);});
            $('#btnSave').attr('value','Create Field');
        }else{
            $('#btnSave').attr('value','Save Field');
            
            //var el = document.getElementById("dty_Type");
            //el.val(window.hWin.HEURIST4.detailtypes.lookups[value]);
            _dty_Type = _detailType[fi.dty_Type];
            
            if(_detailType[fi.dty_Status]==='reserved'){ //if reserved - it means original dbid<1001

                disable_status = (original_dbId!==dbId) && (original_dbId>0) && (original_dbId<1001);
                disable_fields = true;
                _addOptionReserved();

            }else if(_detailType[fi.dty_Status]==='approved'){
                disable_fields = true;
            }
        }
        
        if(_dty_Type && window.hWin.HEURIST4.detailtypes.lookups[_dty_Type]){ //valid type            
            
            var el = document.getElementById("dty_Type");

            window.hWin.HEURIST4.ui.addoption(el, _dty_Type, window.hWin.HEURIST4.detailtypes.lookups[_dty_Type]);
            el.disabled = false;

            if(_dty_Type=='float' || _dty_Type=='date'){
                window.hWin.HEURIST4.ui.addoption(el, 'freetext', window.hWin.HEURIST4.detailtypes.lookups['freetext']);
            }else if(_dty_Type=='freetext'){
                window.hWin.HEURIST4.ui.addoption(el, 'blocktext', window.hWin.HEURIST4.detailtypes.lookups['blocktext']);
            }else if(_dty_Type=='blocktext'){
                window.hWin.HEURIST4.ui.addoption(el, 'freetext', window.hWin.HEURIST4.detailtypes.lookups['freetext']);
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

        setTimeout(function(){document.getElementById("dty_Name").focus();},1000);
    }

    /**
    * adds reserved option to status dropdown list
    */
    function _addOptionReserved(){
        var selstatus = document.getElementById("dty_Status");
        if(selstatus.length<4){
            window.hWin.HEURIST4.ui.addoption(selstatus, 'reserved','reserved');
        }
    }

    /**
    * Toggle fields to disable. Is called when status is set to 'Reserved'.
    */
    function _toggleAll(disable, reserved) {

        //document.getElementById("dty_Name").disabled = disable;
        //document.getElementById("dty_DetailTypeGroupID").disabled = disable;
        document.getElementById("dty_Status").disabled = reserved;
        document.getElementById("dty_OrderInGroup").disabled = disable;
        //document.getElementById("dty_ShowInLists").disabled = disable;
        /* Ian's request 2012-02-07
        document.getElementById("termsPreview").disabled = disable;
        document.getElementById("btnSelTerms").disabled = disable;
        document.getElementById("btnSelRecType1").disabled = disable;
        document.getElementById("btnSelRecType2").disabled = disable;
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

//        var prev = document.getElementById("termsVocab");
//        prev.innerHTML = "";

        if(Hul.isempty(datatype)) return;

        var vocabId = toselect ?toselect: Number(document.getElementById("dty_JsonTermIDTree").value),
        sel_index = -1;
        if(isNaN(vocabId)){
            vocabId = 0;
        }

        var dom = (datatype === "relation" || datatype === "relmarker" || datatype === "relationtype")?"relation":"enum",
        fi_label = window.hWin.HEURIST4.terms.fieldNamesToIndex['trm_Label'];
        var termID,
        termName,
        termTree = window.hWin.HEURIST4.terms.treesByDomain[dom],
        terms = window.hWin.HEURIST4.terms.termsByDomainLookup[dom];

        var el_sel = $('#selVocab').get(0);
        $(el_sel).empty();

        window.hWin.HEURIST4.ui.addoption(el_sel, -1, 'select...');
        
        //add to temp array
        var vocabs = [];
        for(termID in termTree) { // For every term in first level of tree
            if(!Hul.isnull(termID)){
                termName = terms[termID][fi_label];
                vocabs.push({key:termID, title:termName });
            }
        }
        //sort array 
        vocabs.sort(function(a,b){ return a.title<b.title?-1:1; });

        for(var idx in vocabs) { // For every term in first level of tree
            if(vocabs[idx]){
                window.hWin.HEURIST4.ui.addoption(el_sel, vocabs[idx].key, vocabs[idx].title);
                if(Number(vocabs[idx].key)==vocabId){
                    sel_index = el_sel.length-1;
                }
            }
        }

        if(sel_index<0) {
            sel_index = (document.getElementById("dty_JsonTermIDTree").value!='' && vocabId==0)?el_sel.length-1:0;
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
            el_sel = document.getElementById("selVocab");
        }

        var    btn_addsel = document.getElementById("btnAddSelTerm"),
        editedTermTree = "",
        divAddSelTerm = document.getElementById("divAddSelTerm"),
        divAddVocab = document.getElementById("divAddVocab"),
        divAddVocab2 = document.getElementById("divAddVocab2");
  
        if(el_sel.value > 0){ //individual selection
            editedTermTree = el_sel.value;
        }

        if(event){
            document.getElementById("dty_JsonTermIDTree").value = editedTermTree;
            document.getElementById("dty_TermIDTreeNonSelectableIDs").value = "";
            _recreateTermsPreviewSelector(document.getElementById("dty_Type").value, editedTermTree, "");
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

        
        /*allTerms = $.parseJSONHul.expandJsonStructure(allTerms);
        disabledTerms = Hul.expandJsonStructure(disabledTerms);
        if (typeof disabledTerms.join === "function") {
            disabledTerms = disabledTerms.join(",");
        }
        */

        //remove old selector
        var prev = document.getElementById("termsPreview1"),
        i;
        
        $(prev).empty();
        $('#termsPreview2').empty();

        if(!Hul.isempty(allTerms)) {
            //var el_sel = Hul.createTermSelect(allTerms, disabledTerms, datatype, null);
            
            $input = createTermSelectExt2(null,
                {datatype:datatype, termIDTree:allTerms, headerTermIDsList:disabledTerms,
                    defaultTermID:null, topOptions:false, supressTermCode:true, useHtmlSelect:false});
            
            $input.css({'backgroundColor':'#cccccc'}).addClass('sel_width').change(_preventSel).show();
            
            $(prev).append($('<label style="width:60px;min-width:60px">Preview</label>')).append($input);                                      
            $('#termsPreview2')
                .append($('<label style="width:60px;min-width:60px">Preview</label>'))
                .append($input.clone());
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

        var divRecType = document.getElementById( (type==="fieldsetmarker")? "dty_FieldSetRecTypeIDPreview" : "dty_PtrTargetRectypeIDsPreview" );
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
                    dtName = window.hWin.HEURIST4.rectypes.names[ind2];
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
            txt = "select...";
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
    * Add new vocabulary or add child to currently selected
    */
    function _onAddVocabOrTerms(is_add_vocab){

        var type = document.getElementById("dty_Type").value;
        var dt_name = document.getElementById("dty_Name").value;
        var allTerms = document.getElementById("dty_JsonTermIDTree").value;
        var disTerms = document.getElementById("dty_TermIDTreeNonSelectableIDs").value;

        if(type!="enum"){
            type="relation";
        }

        var el_sel = document.getElementById("selVocab");
        
        var vocab_id =  el_sel.value>0?el_sel.value:''; //keep value
        var is_frist_time = true;

        var sURL = window.hWin.HAPI4.baseURL +
                "admin/structure/terms/editTermForm.php?treetype="+type+"&parent="+(is_add_vocab?0:el_sel.value)
                +"&db="+window.hWin.HAPI4.database;
        window.hWin.HEURIST4.msg.showDialog(sURL, {

                    "close-on-blur": false,
                    "no-resize": true,
                    noClose: true, //hide close button
                    title: 'Edit Vocabulary',
                    height: 340,
                    width: 700,
                    onpopupload:function(dosframe){
                        var ele = $(dosframe.contentDocument).find('#trmName');
                        if(is_add_vocab && is_frist_time){
                           is_frist_time = false;
                           if( !window.hWin.HEURIST4.util.isempty(dt_name)){
                                ele.val( dt_name+' vocab' );    
                           }
                        }
                        ele.focus();
                    },
                    callback: function(context) {
                        if(context!="") {

                            if(context=="ok"){    //after edit term tree
                                _recreateTermsVocabSelector(type, vocab_id);
                                _recreateTermsPreviewSelector(type, vocab_id, "");
                            }else if(!Hul.isempty(context)) { //after add new vocab
                                document.getElementById("dty_JsonTermIDTree").value =  context;
                                document.getElementById("dty_TermIDTreeNonSelectableIDs").value = "";
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
        
        var type = document.getElementById("dty_Type").value;
        var allTerms = document.getElementById("dty_JsonTermIDTree").value;
        var disTerms = document.getElementById("dty_TermIDTreeNonSelectableIDs").value;

        if(type!="enum"){
            type="relation";
        }
        
        
        var sURL = window.hWin.HAPI4.baseURL +
                "admin/structure/terms/selectTerms.html?dtname="+_dtyID+"&datatype="+type+"&all="
                +allTerms+"&dis="+disTerms+"&db="+_db;
                
        window.hWin.HEURIST4.msg.showDialog(sURL, {
                    "close-on-blur": false,
                    "no-resize": true,
                    noClose: true, //hide close button
                    title: 'Select terms',
                    height: 500,
                    width: 750,
                    callback: function(editedTermTree, editedDisabledTerms) {
                        if(editedTermTree || editedDisabledTerms) {
                            //update hidden fields
                            document.getElementById("dty_JsonTermIDTree").value = editedTermTree;
                            document.getElementById("dty_TermIDTreeNonSelectableIDs").value = editedDisabledTerms;
                            _recreateTermsPreviewSelector(type, editedTermTree, editedDisabledTerms);
                        }
                    }
            });

    }
    
    function _showOtherTerms(isvocab){

        var type = document.getElementById("dty_Type").value;
        if(type!="enum"){
            type="relation";
        }
        
        var allTerms = document.getElementById("dty_JsonTermIDTree").value;
        var disTerms = document.getElementById("dty_TermIDTreeNonSelectableIDs").value;

        var sURL = window.hWin.HAPI4.baseURL + "admin/structure/terms/editTerms.php?"+
            "popup=1&treetype="+type+"&db="+_db;
        
        var vocab_id = 0;
        
        if(isvocab){
            var el_sel = document.getElementById("selVocab");
            vocab_id =  el_sel.value>0?el_sel.value:'';
            sURL = sURL + '&vocabid='+vocab_id;
        }
        
        window.hWin.HEURIST4.msg.showDialog(sURL, {
                "close-on-blur": false,
                "no-resize": false,
                title: (type=='relation')?'Manage Relationship types':'Manage Terms',
                height: (type=='relation')?820:780,
                width: 950,
                afterclose: function() {
                    if(isvocab){
                        _recreateTermsVocabSelector(type, vocab_id);
                        _recreateTermsPreviewSelector(type, vocab_id, "");
                    }else{
                        _recreateTermsPreviewSelector(type, allTerms, disTerms);
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

        var type = document.getElementById("dty_Type").value;
        if(type === "relmarker" || type === "resource" || type === "fieldsetmarker")
        {

            var args, sURL;

            if(type === "fieldsetmarker") { //not used
                if(document.getElementById("dty_FieldSetRecTypeID")) {
                    args = document.getElementById("dty_FieldSetRecTypeID").value;
                }
            }
            if(type === "relmarker" || type === "resource") {
                if(document.getElementById("dty_PtrTargetRectypeIDs")) {
                    args = document.getElementById("dty_PtrTargetRectypeIDs").value;
                }
            }

            if(args) {
                sURL =  window.hWin.HAPI4.baseURL + "admin/structure/rectypes/selectRectype.html?type=" 
                    + type + "&dtID="+_dtyID+"&ids=" + args+"&db="+_db;
            } else {
                sURL =  window.hWin.HAPI4.baseURL + "admin/structure/rectypes/selectRectype.html?type=" + type+"&db="+_db;
            }

            window.hWin.HEURIST4.msg.showDialog(sURL, {
                "close-on-blur": false,
                "no-resize": true,
                title: 'Select Record Type',
                height: 600,
                width: 640,
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
        var type = document.getElementById("dty_Type").value;
        if(type === "fieldsetmarker") { // Change comma seperated list to right format
            document.getElementById("dty_FieldSetRecTypeID").value = recordTypesSelected;
        } else {
            document.getElementById("dty_PtrTargetRectypeIDs").value = recordTypesSelected;
        }
    }


    /**
    * Initialization of group selector
    *
    * Gets all groups in HEURIST DB, creates and adds oprions to group selector
    */
    function _initGroupComboBox() {

        var el = document.getElementById("dty_DetailTypeGroupID"),
        dtg_ID,
        index;

        for (index in window.hWin.HEURIST4.detailtypes.groups){
            if(!isNaN(Number(index))) {
                dtg_ID = window.hWin.HEURIST4.detailtypes.groups[index].id;
                var grpName = window.hWin.HEURIST4.detailtypes.groups[index].name;

                window.hWin.HEURIST4.ui.addoption(el, dtg_ID, grpName);
            }
        } //for
    }

    /**
    * Init field type selector
    */
    function _initFieldTypeComboBox() {
        var el = document.getElementById("dty_Type"),
        text,
        value;

        window.hWin.HEURIST4.ui.addoption(el, "", "-- Select data type --");

        for (value in window.hWin.HEURIST4.detailtypes.lookups){
            if(!Hul.isnull(Number(value))) {
                if(!(value==="relationtype" || value==="year" || value==="boolean" || value==="integer")){
                    text = window.hWin.HEURIST4.detailtypes.lookups[value];
                    window.hWin.HEURIST4.ui.addoption(el, value, text);
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
        fnames = window.hWin.HEURIST4.detailtypes.typedefs.commonFieldNames,
        fi = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex;

        if (Hul.isempty(_detailType[fi.dty_ConceptID])){
            document.getElementById('div_dty_ConceptID').innerHTML = '';
        }else{
            document.getElementById('div_dty_ConceptID').innerHTML = 'Concept ID: '+_detailType[fi.dty_ConceptID];
        }

        if( _dtyID== window.hWin.HAPI4.sysinfo['dbconst']['DT_RELATION_TYPE'] ){
            _detailType[fi.dty_JsonTermIDTree] = "0";
            _detailType[fi.dty_TermIDTreeNonSelectableIDs] = "";
            document.getElementById('defineTerms').style.display = 'none';
        }


        for (i = 0, l = fnames.length; i < l; i++) {
            var fname = fnames[i];
            el = document.getElementById(fname);
            if(!Hul.isnull(el)){
                if ( fname==='dty_ShowInLists' ) { // dty_ShowInLists
                    el.checked = (Number(_detailType[fi.dty_ShowInLists])===1);
                }else{
                    el.value = _detailType[i];
                }
            }
        }

        //to trigger setting visibilty for div with terms tree and record pointer
        that.keepType = document.getElementById("dty_Type").value;
        _onChangeType(null);

        // create preview for Terms Tree and record pointer
        var vocabId = Hul.isempty(document.getElementById("dty_JsonTermIDTree").value)?0:Number(document.getElementById("dty_JsonTermIDTree").value);
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
            document.getElementById("dty_ID").innerHTML = '<span style="color:#999">will be automatically assigned</span>';
            document.title = "Create new base field type";
        }else{
            document.getElementById("dty_ID").innerHTML =  _dtyID;
            document.title = "Field Type # " + _dtyID+" '"+_detailType[fi.dty_Name]+"'";
            var aUsage = window.hWin.HEURIST4.detailtypes.rectypeUsage[_dtyID];
            var iusage = (Hul.isnull(aUsage)) ? 0 : aUsage.length;
            var warningImg = "<img src='" + window.hWin.HAPI4.baseURL + "common/images/url_warning.png'>";

            if(iusage > 0) {

                document.getElementById("msg").style.visibility = "visible";
                document.getElementById("statusMsg").innerHTML = warningImg + "WARNING: Changes to this field type will affect all record types (" + iusage + ") in which it is used";

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
    function _fromUItoArray(isShowWarn, ignoreFields){

        _updatedFields = [];
        _updatedDetails = [];
        
        if(!ignoreFields) ignoreFields = [];

        var el = document.getElementById("dty_ShowInLists");
        el.value = el.checked?1:0;

        var i;
        var fnames = window.hWin.HEURIST4.detailtypes.typedefs.commonFieldNames;

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
            
            
            /*el = document.getElementById(fname);
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
        var dt_name = document.getElementById("dty_Name").value;
        if(dt_name==="") {
            swarn = "Name is mandatory field"
        }else{
            swarn = window.hWin.HEURIST4.ui.validateName(dt_name, "Field 'Default field type name'", 255);
        }
        if(swarn!=""){
            if(isShowWarn){
                window.hWin.HEURIST4.msg.showMsgFlash(swarn);
            }
            document.getElementById("dty_Name").focus();
            _updatedFields = [];
            return "mandatory";
        }

        if(document.getElementById("dty_HelpText").value==="") {
            if(isShowWarn) {
                window.hWin.HEURIST4.msg.showMsgErr('Help text is mandatory field');
            }
            document.getElementById("dty_HelpText").focus();
            _updatedFields = [];
            return "mandatory";
        }
        
        ll = document.getElementById("dty_HelpText").value.length;
        if(ll>255) {
            window.hWin.HEURIST4.msg.showMsgFlash('Sorry, help text exceeds the maximum allowed length - 255 characters - by '
                    +(ll-255)+' characters. Please reduce length.');
            document.getElementById("dty_HelpText").focus();
            _updatedFields = [];
            return "mandatory";
        }

        if(document.getElementById("dty_Type").value==="enum"){
            var dd = document.getElementById("dty_JsonTermIDTree").value;
            if( dd==="" || dd==="{}" ) {
                if(isShowWarn) {
                    window.hWin.HEURIST4.msg.showMsgErr(
                        'Please select or add a vocabulary. Vocabularies must contain at least one term.', 'Warning');
                }
                _updatedFields = [];
                return "mandatory";
            }
        }else if(document.getElementById("dty_Type").value==="relmarker"){
            
            var dd = document.getElementById("dty_JsonTermIDTree").value;
            if( dd==="" || dd==="{}" ) {
                if(isShowWarn) {
                    window.hWin.HEURIST4.msg.showMsgDlg(
                        'Please select or add relationship types',null, 'Warning');
                }
                _updatedFields = [];
                return "mandatory";
            }

            var dd = document.getElementById("dty_PtrTargetRectypeIDs").value;
            if( dd==="" ) {
                if(isShowWarn) {
                    window.hWin.HEURIST4.msg.showMsgDlg(
                        'Please select target record type. Unconstrained relationship is not allowed',null, 'Warning');
                }
                _updatedFields = [];
                return "mandatory";
            }
        }else if(document.getElementById("dty_Type").value==="resource" && ignoreFields.indexOf('dty_PtrTargetRectypeIDs')<0){
            
            var dd = document.getElementById("dty_PtrTargetRectypeIDs").value;
            if( dd==="" ) {
                if(isShowWarn) {
                    window.hWin.HEURIST4.msg.showPrompt(
'Please select target record type(s) for this entity pointer field before clicking the Create Field button.'
+'<br><br>We strongly recommend NOT creating an unconstrained entity pointer unless you have a very special reason for doing so, as all the clever stuff that Heurist does with wizards for building facet searches, rules, visualisation etc. depend on knowing what types of entities are linked. It is also good practice to plan your connections carefully. If you really wish to create an unconstrained entity pointer - not recommended - check this box <input id="dlg-prompt-value" class="text ui-corner-all" '
                + ' type="checkbox" value="1"/>', 
    function(value){
        if(value==1){
            ignoreFields.push('dty_PtrTargetRectypeIDs');
            _updateDetailTypeOnServer( ignoreFields );
        }
    }, {title:'Target record type(s) should be set',yes:'Continue',no:'Cancel'});
                    _updatedFields = [];
                    return "mandatory";
                }
            }
        }
        
        
        var val = document.getElementById("dty_Type").value;
        if(Hul.isempty(val)){
            if(isShowWarn) {
                window.hWin.HEURIST4.msg.showMsgErr("Data Type is mandatory field");
            }
            document.getElementById("dty_Type").focus();
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
    function _updateResult(response) {
        $('#btnSave').removeAttr('disabled');

            if(response.status == window.hWin.ResponseStatus.OK){
        
                var error = false,
                report = "",
                ind;
                
                var context = response.data;

                for(ind in context.result){
                    if( !Hul.isnull(ind) ){
                        var item = context.result[ind];
                        if(isNaN(item)){
                            window.hWin.HEURIST4.msg.showMsgErr(item);
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
                    
                    var dty_ID = Math.abs(Number(context.result[0]));
                    window.hWin.HEURIST4.rectypes = context.rectypes;
                    window.hWin.HEURIST4.detailtypes = context.detailtypes;
                    window.close(context);
                }
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
    }

    /**
    * Apply form
    * private method for public method "save"
    * 1. gather changed data from UI (_fromUItoArray) to _updatedFields, _updatedDetails
    * 2. creates object to be sent to server
    * 3. sends data to server
    */
    function _updateDetailTypeOnServer(ignoreFields)
    {
        if($('#btnSave').is(":disabled")) return;
        $('#btnSave').attr('disabled','disabled');
        
        if(!ignoreFields) ignoreFields = [];

        //1. gather changed data
        if(_fromUItoArray(true, ignoreFields)==="mandatory"){ //save all changes
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
            //var fieldNames = window.hWin.HEURIST4.detailtypes.typedefs.commonFieldNames;

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
            
            function __performSave(){
                // 3. sends data to server
                var baseurl = window.hWin.HAPI4.baseURL + "admin/structure/saveStructure.php";
                var callback = _updateResult;
                
                var request = {method:'saveDT', db:window.hWin.HAPI4.database, data:oDetailType};
                window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, callback);
            }
            
            
            if(window.hWin.HEURIST4.detailtypes.typedefs[_dtyID] &&
               'reserved'==window.hWin.HEURIST4.detailtypes.typedefs[_dtyID].commonFields[window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex.dty_Status])
            {
                    
                        if(window.hWin.HAPI4.sysinfo['pwd_ReservedChanges']){ //password defined
                        
                            window.hWin.HEURIST4.msg.showPrompt('Enter password: ',
                                function(password_entered){
                                    
                                    window.hWin.HAPI4.SystemMgr.action_password({action:'ReservedChanges', password:password_entered},
                                        function(response){
                                            if(response.status == window.hWin.ResponseStatus.OK && response.data=='ok'){
                                                __performSave();
                                            }else{
                                                window.hWin.HEURIST4.msg.showMsgFlash('Wrong password');
                                            }
                                        }
                                    );
                                    
                                },
                            'Certain changed to fields designated Status = Reserved can only be made by the system administrator (not the database owner) as they have special uses elsewhere. A special password is required.', {password:true});
                        }else{
                            window.hWin.HEURIST4.msg.showMsgDlg('Reserved field changes are not allowed unless a challenge password is set - please consult system administrator');
                        }
                        $('#btnSave').removeAttr('disabled');
                        return false;
            }

            
            __performSave();
            
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

        var el = document.getElementById("dty_Type"); //event.target;

        //prevent setting outdated types for new field type
        if( event!=null && (el.value==="relationtype" || el.value==="year" || el.value==="boolean" || el.value==="integer")){
            window.hWin.HEURIST4.msg.showMsgErr('You selected an outdated type. It is not allowed anymore');
            if(that.keepType){
                el.value = that.keepType;
            }else{
                el.selectedIndex = 0;
            }
            return;
        }

        document.getElementById("pnl_relmarker").style.display = "none";
        document.getElementById("pnl_enum").style.display = "none";
        document.getElementById("pnl_fieldsetmarker").style.display = "none";

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
                
                document.getElementById("dty_JsonTermIDTree").value = "";
                document.getElementById("dty_TermIDTreeNonSelectableIDs").value = "";
                document.getElementById("dty_PtrTargetRectypeIDs").value = "";
                document.getElementById("dty_FieldSetRecTypeID").value = "";
                that.keepType = el.value;
                _recreateTermsVocabSelector(that.keepType, null);
                _recreateTermsPreviewSelector(that.keepType, null, null);
                _recreateRecTypesPreview(that.keepType, null);

                if((el.value=="freetext" || el.value=="blocktext") && _dtyID<0){

                    $("#topdiv_closebtn").hide();
                    $("#field_types_context_help").load(window.hWin.HAPI4.baseURL+'context_help/field_data_types.html #content_body');
                    
                    _dialogbox = window.hWin.HEURIST4.msg.showElementAsDialog({element:$("#info_div").get(0),
                            height: 550, width:800, title:"Choosing appropriate field types", modal:true} );
                }
            }
        }else{
            el.value = that.keepType;  //rollback
            document.getElementById("typeValue").value = window.hWin.HEURIST4.detailtypes.lookups[that.keepType];
        }

        // setting visibility
        switch(el.value)
        {
            case "resource":
                document.getElementById("pnl_relmarker").style.display = "block";
                break;
            case "relmarker":
                document.getElementById("pnl_relmarker").style.display = "block";
            case "enum":
            case "relationtype":
                document.getElementById("pnl_enum").style.display = "block";
                break;
            case "fieldsetmarker": //NOT USED ANYMORE
                document.getElementById("pnl_fieldsetmarker").style.display = "block";
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
            return window.hWin.HEURIST4.ui.preventChars(event);
        },        
        
        //
        //
        onDataTypeClick: function(event){
			
		    var el = document.getElementById("dty_Type"); //e.target;
		    if(true){ //}!el.value){
		    	
		    	
			var body = $(this.document).find('body');
            var dim = { h:530, w:800 };//Math.max(900, body.innerWidth()-10) };		    	
		    	
            var sURL = window.hWin.HAPI4.baseURL +
                "admin/structure/fields/selectFieldType.html?&db="+_db;
            window.hWin.HEURIST4.msg.showDialog(sURL, {
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
                                changeToNewType = confirm("If you change the type to '"+window.hWin.HEURIST4.detailtypes.lookups[context]+ 
                                    "' you will lose all your vocabulary settings for type '"+window.hWin.HEURIST4.detailtypes.lookups[el.value]+
                                    "'.\n\nAre you sure?");
                            }

                            if(changeToNewType) {
                               document.getElementById("typeValue").value = window.hWin.HEURIST4.detailtypes.lookups[context];
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

        showOtherTerms: function(isvocab){ _showOtherTerms(isvocab); },

        
        /**
        *	handles change status event
        */
        onSelectRectype : _onSelectRectype,

        
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
