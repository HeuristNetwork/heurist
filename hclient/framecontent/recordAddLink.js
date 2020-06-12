
/*
* Copyright (C) 2005-2020 University of Sydney
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
* recordAddLink.js
* Adds link field or create relationship between 2 records
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/  
function onPageInit(success) //callback function of hAPI initialization
{
    
    if(success)  //system is inited
    {
        var recordAddLink = new hRecordAddLink();
    }    
/*        
        // find both records details
        var request = {q:'ids:'+source_ID+','+target_ID,w:'a',f:'detail'};
        window.hWin.HAPI4.RecordMgr.search(request, function(response){
            if(response.status == window.hWin.ResponseStatus.OK){
                var resdata = new hRecordSet(response.data);
        
                //add SELECT and fill it with values
                var idx, dty, rec_titles = [];
                var records = resdata.getRecords();
            
                // create list of 3 columns checkboxes, fieldname and relation type     
                var opposites = []; //recId:recTypeID
                
                var record = resdata.getById(source_ID);
                sSourceName = resdata.fld(record, 'rec_Title');
                var recRecTypeID = resdata.fld(record, 'rec_RecTypeID');

                rec_titles.push('<b>'+window.hWin.HEURIST4.rectypes.names[recRecTypeID]+'</b>');
                $('#rec0_title').text(sSourceName);
                
                $('#source_rectype').text(window.hWin.HEURIST4.rectypes.names[recRecTypeID]);
                $('#source_rectype_img').css('background-image', 'url("'+top.HAPI4.iconBaseURL+recRecTypeID+'")');
                
                //recordsPair[recID] = recRecTypeID;
                opposites.push( { recID:source_ID, recRecTypeID:recRecTypeID} );

                record = resdata.getById(target_ID);
                sTargetName = resdata.fld(record, 'rec_Title');
                recRecTypeID = resdata.fld(record, 'rec_RecTypeID');

                rec_titles.push('<b>'+window.hWin.HEURIST4.rectypes.names[recRecTypeID]+'</b>');
                $('#rec1_title').text(sTargetName);                                
                $('#target_rectype').text(window.hWin.HEURIST4.rectypes.names[recRecTypeID]);
                $('#target_rectype_img').css('background-image', 'url("'+top.HAPI4.iconBaseURL+recRecTypeID+'")');
                
                //recordsPair[recID] = recRecTypeID;
                opposites.push( { recID:target_ID, recRecTypeID:recRecTypeID} );
                    
                
                $('#rec_titles').html(rec_titles.join(' and '));
                $('#link_rec_edit').attr('href', window.hWin.HAPI4.baseURL
                                +'?fmt=edit&db='
                                +window.hWin.HAPI4.database+'&recID='+source_ID);
                
                var fi_name = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_DisplayName'], 
                    fi_constraints = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_PtrFilteredIDs'], 
                    fi_type = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex['dty_Type'],
                    fi_term =  window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_FilteredJsonTermIDTree'],
                    fi_term_dis =  window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_TermIDTreeNonSelectableIDs'],
                    hasSomeLinks = false;
                    
                var index = 0;
                            
                for(idx in opposites){
                    if(idx)
                    {
                        var recID  = opposites[idx]['recID'],
                            recRecTypeID = opposites[idx]['recRecTypeID'];

                        // get structures for both record types and filter out link and relation maker fields
                        for (dty in window.hWin.HEURIST4.rectypes.typedefs[recRecTypeID].dtFields) {
                            
                            var field_type = window.hWin.HEURIST4.detailtypes.typedefs[dty].commonFields[fi_type];
                            
                            if(!(field_type=='resource' || field_type=='relmarker')){
                                 continue;
                            }
                            
                            var details = window.hWin.HEURIST4.rectypes.typedefs[recRecTypeID].dtFields[dty];

                            //get name, contraints
                            var dtyName = details[fi_name];
                            var dtyPtrConstraints = details[fi_constraints];
                            var recTypeIds = null;
                            if(!window.hWin.HEURIST4.util.isempty(dtyPtrConstraints)){
                                recTypeIds = dtyPtrConstraints.split(',');
                            }
                            
                            var oppositeRecTypeID = opposites[index==0?1:0]['recRecTypeID'];
                            
                            //check if constraints satisfy to opposite record type
                            if( recTypeIds==null || recTypeIds.indexOf(oppositeRecTypeID)>=0 ){
                                
                                hasSomeLinks = true; //reset flag
                                
                                //get value of field and compare with opposite record id
                                var record = resdata.getById(recID);    
                                var values = resdata.values(record, dty);
                                var oppositeRecID = opposites[index==0?1:0]['recID'];
                                
                                //already linked for resource field type
                                var isAlready = (field_type=='resource') && $.isArray(values) && (values.indexOf(oppositeRecID)>=0);
                                
                                if(index==1)
                                 dtyName = dtyName + ' [ reverse link, target to source ]';
                                
                                //add UI elements
                    $('<div style="line-height:2.5em;padding-left:20px"><input type="checkbox" id="cb'+recID+'_cb_'+dty+'" '
                    +(isAlready?'disabled checked="checked"'
                        :' class="cb_addlink"  data-recid="'
                            +recID+'" data-dtyid="'+dty+'" data-target="'+oppositeRecID+'" data-type="'+field_type+'"')
                    +' class="text ui-widget-content ui-corner-all"/>'                                     
                    +'<label style="font-style:italic" for="cb'+recID+'_cb_'+dty+'">'+dtyName+'</label>&nbsp;'
                    +'<select id="rec'+recID+'_sel_'+dty+'" class="text ui-widget-content ui-corner-all">'  
                        +'<option>relation type</option>'
                    +'</select><div>').appendTo($('#rec'+index));
                    
                                if(index==1){
                                    $('#rec1_hint').show();
                                }
                    
                    
                                if(field_type=='relmarker'){
                                    
                                    var terms = details[fi_term],
                                        terms_dis = details[fi_term_dis],
                                        currvalue = null;
                                    
                                    window.hWin.HEURIST4.ui.createTermSelectExt($('#rec'+recID+'_sel_'+dty).get(0), 
                                            'relation', terms, terms_dis, currvalue, null, false);    
                                }else{
                                    $('#rec'+recID+'_sel_'+dty).hide();
                                }
                            }
                            
                            
                        }//for fields
                        index++;
                    }
                }//for
                
                if(!hasSomeLinks){  //no suitable links
                    
                    var rectypeID =(opposites[0]['recID']==source_ID)
                                        ?opposites[0]['recRecTypeID']
                                        :opposites[1]['recRecTypeID'];
                    
                    $('#link_structure_edit').click(function(){
                         window.open(window.hWin.HAPI4.baseURL+"/admin/adminMenuStandalone.php?db="+
                                window.hWin.HAPI4.database+
                                "&mode=rectype&rtID="+rectypeID, "_blank");
                    });                
                    
                    $('#btn_save').hide();
                    $('#mainForm').hide();
                    $('#infoForm').show();
                }else{ 
                    $('.cb_addlink').click(function(){
                        window.hWin.HEURIST4.util.setDisabled($('#btn_save'), !$('.cb_addlink').is(':checked'));
                    });
                    //auto mark the single field
                    if($('.cb_addlink').length==1){
                        $('.cb_addlink').prop('checked', true);
                        window.hWin.HEURIST4.util.setDisabled($('#btn_save'), false);
                    }
                   
                }

            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
            
            
        });

*/
}

/*

if source_ID, target_ID not defined it uses 
    window.hWin.HAPI4.currentRecordset
    window.hWin.HAPI4.currentRecordsetSelection
to determine the scope of records to be affected    

*/    
function hRecordAddLink() {
    var _className = "RecordAddLink",
    _version   = "0.4",

    source_ID, target_ID, relmarker_dty_ID, source_AllowedTypes,
    sSourceName, sTargetName,
    
    onlyReverse,

    source_RecTypeID, 
    target_RecTypeID,
    selectRecordScope, allSelectedRectypes;
    
    
    function _init(){

        //source record 
        source_ID = window.hWin.HEURIST4.util.getUrlParameter("source_ID", window.location.search);
        //relmarker field type id 
        relmarker_dty_ID = window.hWin.HEURIST4.util.getUrlParameter("dty_ID", window.location.search);
        
        source_AllowedTypes = window.hWin.HEURIST4.util.getUrlParameter("source_RecTypes", window.location.search);
        
        onlyReverse = (window.hWin.HEURIST4.util.getUrlParameter("reverse", window.location.search)==1);
        
        if(source_ID>0 && relmarker_dty_ID>0){
            $('#helpmsg').text('Select relation type and target record');
        }else if(target_ID>0 && onlyReverse){
            $('#helpmsg').text('Select source record and pointer(link) or relation type');
        }
        $('#helpmsg').text('');
        
        //destination record
        target_ID = window.hWin.HEURIST4.util.getUrlParameter("target_ID", window.location.search);

        //init buttons
        $('#btn_save').attr('title', 'explanatory rollover' ).button().addClass('ui-state-disabled');
        $('#btn_cancel').button().on('click', function(){  window.close()});

        selectRecordScope = $('#sel_record_scope');
        
        source_RecTypeID = null;
        
        if(window.hWin.HEURIST4.util.isempty(source_ID)){
            //source record id is not defined - use current record set to detect the scope of records
            
            //fill selector for records: all, selected, by record type
            selectRecordScope
            .on('change',
                function(e){
                    _onRecordScopeChange();
                }
            );
            _fillSelectRecordScope();
            
            $('#div_source1').hide();
            $('#div_source2').css('display','inline-block');
            
        }else{
            getRecordValue(source_ID, 'source');
        }

        if(window.hWin.HEURIST4.util.isempty(target_ID)){
            //show record selector

            $('#div_target1').hide();
            $('#div_target2').css('display','inline-block');
        }else{
            getRecordValue(target_ID, 'target');

            if(source_AllowedTypes && relmarker_dty_ID>0){
                
                source_AllowedTypes = source_AllowedTypes.split(',');
                
                _fillSelectFieldTypes('source', source_AllowedTypes[0], null);
                _createInputElement_RecordSelector('source', source_AllowedTypes);
            }
        }

    }
    
    //
    //
    //
    function _fillSelectRecordScope(){

        selectRecordScope.empty();


        if(!window.hWin.HAPI4.currentRecordset){
            //debug
            window.hWin.HAPI4.currentRecordset = new hRecordSet({count: "1",offset: 0,reccount: 1,records: [1069], rectypes:[25]});
            //return;
        }

        var opt, new_optgroup, selScope = selectRecordScope.get(0);

        var rectype_Ids = window.hWin.HAPI4.currentRecordset.getRectypes();
        
        if(rectype_Ids.length>1){
            
            opt = new Option("please select the records to be affected …", "");
            selScope.appendChild(opt);
        }  
        var hasSelection = (window.hWin.HAPI4.currentRecordsetSelection &&  window.hWin.HAPI4.currentRecordsetSelection.length > 0);

        if(hasSelection){            
            new_optgroup = document.createElement("optgroup");
            new_optgroup.label = 'All records';
            new_optgroup.depth = 0;
            selScope.appendChild(new_optgroup);
        }
        
        for (var rty in rectype_Ids){
            if(rty>=0){
                rty = rectype_Ids[rty];
                opt = new Option(window.hWin.HEURIST4.rectypes.pluralNames[rty], rty);
                if(hasSelection){
                    opt.className = 'depth1';
                    new_optgroup.appendChild(opt);    
                }else{
                    selScope.appendChild(opt);
                }
            }
        }
            
        if(hasSelection){ //make the same section in recordAction.js?
            
            rectype_Ids = [];
            var sels = window.hWin.HAPI4.currentRecordsetSelection;
            for (var idx in sels){ //find all selected rectypes
              if(idx>=0){  
                var rec = window.hWin.HAPI4.currentRecordset.getById(sels[idx]);
                var rt = Number(window.hWin.HAPI4.currentRecordset.fld(rec, 'rec_RecTypeID'));
                if(rectype_Ids.indexOf(rt)<0) rectype_Ids.push(rt);
              }
            }
            
            if(rectype_Ids.length>0){
                new_optgroup = document.createElement("optgroup");
                new_optgroup.label = 'Selected';
                new_optgroup.depth = 0;
                selScope.appendChild(new_optgroup);
            }
            
            for (var rty in rectype_Ids){
                if(rty>=0){
                    rty = rectype_Ids[rty];
                    opt = new Option(window.hWin.HEURIST4.rectypes.pluralNames[rty], rty);
                    if(hasSelection){
                        opt.className = 'depth1';
                        new_optgroup.appendChild(opt);    
                    }else{
                        selScope.appendChild(opt);
                    }
                    $(opt).attr('data-select',1);
                }
            }
            
        }
        
        _onRecordScopeChange();
    }
    
    //
    //  fill possible pointer and relation fields for selected record type
    //  clear target record if selected rectype not in constraints scope
    //
    function _onRecordScopeChange() {
         source_RecTypeID = selectRecordScope.val(); 
         _fillSelectFieldTypes('source', source_RecTypeID);
    }
    
    
    //
    // find pointer and relationships for selected record type 
    // party - source or target                                            
    // rty_ID - selected record type
    // 
    function _fillSelectFieldTypes(party, recRecTypeID, oppositeRecTypeID) {
        
        var fi_name = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_DisplayName'], 
            fi_constraints = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_PtrFilteredIDs'], 
            fi_type = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex['dty_Type'],
            fi_term =  window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_FilteredJsonTermIDTree'],
            fi_term_dis =  window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_TermIDTreeNonSelectableIDs'],
            hasSomeLinks = false;

        $('#'+party+'_field').empty();
        
        //var $fieldset = $('#target_field').empty(); //@todo clear target selection only in case constraints were changed
            
        if(window.hWin.HEURIST4.rectypes.typedefs[recRecTypeID]) {   
        // get structures for both record types and filter out link and relation maker fields
        for (dty in window.hWin.HEURIST4.rectypes.typedefs[recRecTypeID].dtFields) {
            
            var field_type = window.hWin.HEURIST4.detailtypes.typedefs[dty].commonFields[fi_type];
            
            if(!(field_type=='resource' || field_type=='relmarker')){
                 continue;
            }
            if(relmarker_dty_ID>0){  //detail id is defined in URL
            
                if( relmarker_dty_ID==dty && (party=='source') ){  //&& (source_ID>0 || target_ID>0)
                    
                }else{
                    continue;
                }
            }
            
            var details = window.hWin.HEURIST4.rectypes.typedefs[recRecTypeID].dtFields[dty];

            //get name, contraints
            var dtyName = details[fi_name];
            var dtyPtrConstraints = details[fi_constraints];
            var recTypeIds = null;
            if(!window.hWin.HEURIST4.util.isempty(dtyPtrConstraints)){
                recTypeIds = dtyPtrConstraints.split(',');
            }
            
            //check if constraints satisfy to opposite record type
            // no constraints,  opposite party is not specified yet,  opposit rty_ID satifies to the constraint
            if(recTypeIds==null || 
               window.hWin.HEURIST4.util.isempty(oppositeRecTypeID) || 
               recTypeIds.indexOf(oppositeRecTypeID)>=0 ){
                
                hasSomeLinks = true; //reset flag
                
                /*
                //get value of field and compare with opposite record id
                var record = resdata.getById(recID);    
                var values = resdata.values(record, dty);
                var oppositeRecID = opposites[index==0?1:0]['recID'];
                //already linked for resource field type
                var isAlready = (field_type=='resource') && $.isArray(values) && (values.indexOf(oppositeRecID)>=0);
                */
                var isAlready = false;
                
                if(party=='target')
                 dtyName = dtyName + ' [ reverse link, target to source ]';
                
                //add UI elements
    $('<div class="field_item" style="line-height:2.5em;padding-left:20px">'
    +'<label style="font-style:italic">' //for="cb'+party+'_cb_'+dty+'"
    +'<input name="link_field" type="radio" id="cb'+party+'_cb_'+dty+'" '
    +(isAlready?'disabled checked="checked"'
        :' data-party="'+party+'" value="'+dty+'" data-type="'+field_type+'"')
    +' class="cb_addlink text ui-widget-content ui-corner-all"/>'                                     
    + dtyName+'</label>&nbsp;'
    + '<div style="display:inline-block;vertical-align:top;padding-left:20px">'
    + '<div id="rt_'+party+'_sel_'+dty+'" style="display:table-row"></div></div>'
    //+'<select id="rec'+party+'_sel_'+dty+'" class="text ui-widget-content ui-corner-all" style="margin-left:30px;">'  
    //    +'<option>relation type</option></select>'
    +'<div>').appendTo($('#'+party+'_field'));
    
    /*
                if(party=='target'){
                    $('#rec1_hint').show();
                }
    */                
                if(field_type=='relmarker'){
                    
                    var terms = details[fi_term],
                        terms_dis = details[fi_term_dis],
                        currvalue = null;
                    
                    _createInputElement_Relation( party, recRecTypeID, dty ); 
                    
      /*
                    window.hWin.HEURIST4.ui.createTermSelectExt2($('#rec'+party+'_sel_'+dty).get(0),
                        {datatype:'relation', termIDTree:terms, headerTermIDsList:terms_dis,
                            defaultTermID:currvalue, topOptions:null, needArray:false, useHtmlSelect:true});
     */               
                }else{
                    //$('#rec'+party+'_sel_'+dty).hide();   //hide relation type selector
                    $('#rt_'+party+'_sel_'+dty).hide();   //hide relation type selector
                }
            }

            if(party=='source' && window.hWin.HEURIST4.util.isempty(target_ID)){
                    $('#cbsource_cb_'+dty).change(_createInputElement);
            }else{
                    //enable add link button
                    $('#cb'+party+'_cb_'+dty).change(_enableActionButton);
            }
            
            
            if( relmarker_dty_ID>0 && (party=='source' && source_ID>0) ){            
                break;
            }
        }//for fields
        
        if(relmarker_dty_ID>0){
            //hide radio - since it is the only one field in list
            $('#source_field').find('.field_item').css('padding-left','0');
            $('#source_field').find('input[type=radio]').hide().click(); //prop('checked',true).
            /*$('#source_field').find('label').text('Relationship type:')
                .css({'font-style':'normal',display:'inline-block',width:'95px'});*/
        }
                            
        
        if(party=='source' && window.hWin.HEURIST4.util.isempty(source_ID)){

            if($('input[type="radio"][name="link_field"]').length>0){
                $($('input[type="radio"][name="link_field"]')[0]).attr('checked','checked').change();
            }

            if(window.hWin.HEURIST4.util.isempty(target_ID)){
                //add reverse link option
                $('<div style="line-height:2.5em;padding-left:20px"><input name="link_field" type="radio" id="cbsource_cb_0" '
                    + ' data-party="source" value="0"'
                    +' class="cb_addlink text ui-widget-content ui-corner-all"/>'                                     
                    +'<label style="font-style:italic;line-height: 1em;" for="cbsource_cb_0">Reverse links: Add links to the target record rather than the current selection<br><span style="width:1.5em;display:inline-block;"/>(where appropriate record pointer or relationship marker fields exist in the target record)</label><div>')
                .appendTo($('#source_field'))
                .change(_createInputElement);
            }
        }
        /*else if(party=='target' && target_ID>0 &&
            window.hWin.HEURIST4.util.isempty(source_ID) && relmarker_dty_ID>0){

                if($('input[type="radio"][name="link_field"]').length>0){
                    $($('input[type="radio"][name="link_field"]')[0]).attr('checked','checked').change();
                }

                _createInputElement_forSource(relmarker_dty_ID);
            }*/
        }//if rectype defined

    }   
    
    //
    // enable add link button
    //
    function _enableActionButton(){
        
        var isEnabled = ((target_ID>0 || getFieldValue('target_record')>0) && 
                        $('input[type="radio"][name="link_field"]:checked').val()>0);
        $('#btn_save').addClass('ui-state-disabled').off('click');    
        if(isEnabled){
            $('#btn_save').removeClass('ui-state-disabled').click(addLinks);    
        }
    } 
    
    //
    // record selector (for target mainly)     (@todo move to utils_ui ???)
    //
    function _createInputElement_RecordSelector(party, rt_constraints){
        
        if(!$.isArray(rt_constraints)){
            if(window.hWin.HEURIST4.util.isempty(rt_constraints)){
                rt_constraints = [];
            }else{
                rt_constraints = rt_constraints.split(',');        
            }
        }
        
        $('#div_'+party+'1').hide();
        var $fieldset = $('#div_'+party+'2').empty();
        
        var dtID = 0;
        var typedefs = window.hWin.HEURIST4.rectypes.typedefs;
        var fi = typedefs.dtFieldNamesToIndex;
        var dtFields = [];
        
        /*if(typedefs[rectypeID].dtFields[dtID]){
            dtFields = window.hWin.HEURIST4.util.cloneJSON(typedefs[rectypeID].dtFields[dtID]);
        }else{*/
        
        //get first resource field and reset constraints
        for(rtid in typedefs){
            if(typedefs[rtid]){
                for(dtid in typedefs[rtid].dtFields){
                    if(typedefs[rtid].dtFields[dtid][fi['dty_Type']]=='resource'){
                        dtFields = window.hWin.HEURIST4.util.cloneJSON(typedefs[rtid].dtFields[dtid]);
                        dtID = dtid;
                        break;
                    }
                }        
            }
        }
        dtFields[fi['rst_PtrFilteredIDs']] = rt_constraints;    
        dtFields[fi['rst_DisplayName']] = '';//input_label;
        dtFields[fi['rst_RequirementType']] = 'optional';
        dtFields[fi['rst_RequirementType']] = 'optional';
        dtFields[fi['rst_MaxValues']] = 1;
        
        var that = this;

        var ed_options = {
            recID: -1,
            dtID: dtID,
            //rectypeID: rectypeID,
            rectypes: window.hWin.HEURIST4.rectypes,
            values: '',// init_value
            readonly: false,

            showclear_button: false,
            suppress_prompts: true,
            show_header: false,
            detailtype: 'resource',  //overwrite detail type from db (for example freetext instead of memo)
            dtFields:dtFields,
            
            change: function(){
                var rec_id = getFieldValue(party+'_record');
                if(party=='source'){
                    source_ID = rec_id;    
                }else{
                    target_ID = rec_id;    
                }
                
                //getRecordValue(rec_id, party);
                //_enableActionButton();
            }    
        };

        $("<div>").attr('id',party+'_record')
                    //.css({'padding-left':'130px'})
                    .editing_input(ed_options).appendTo($fieldset)
                    .find('input').css({'font-weight':'bold'});        
    }

    //
    // create input element to select target record
    //
    function _createInputElement( event ){ //input_id, input_label, init_value){

        $('#div_target1').hide();
    
        var $fieldset = $('#div_target2').empty();

        var dtID = $(event.target).val();//
        
        _enableActionButton();

        if(window.hWin.HEURIST4.util.isempty(dtID)) return;

        
        var rectypeID = source_RecTypeID; //selectRecordScope.val(); 
        
        //_createInputElement_step2(rectypeID, dtID, $fieldset);
        
        
        var typedefs = window.hWin.HEURIST4.rectypes.typedefs;
        var fi = typedefs.dtFieldNamesToIndex;
        var dtFields = [];
        
        if(typedefs[rectypeID].dtFields[dtID]){
            dtFields = window.hWin.HEURIST4.util.cloneJSON(typedefs[rectypeID].dtFields[dtID]);
        }else{
            //get first resource field and reset constraints
            for(rtid in typedefs){
                if(typedefs[rtid]){
                    for(dtid in typedefs[rtid].dtFields){
                        if(typedefs[rtid].dtFields[dtid][fi['dty_Type']]=='resource'){
                            dtFields = window.hWin.HEURIST4.util.cloneJSON(typedefs[rtid].dtFields[dtid]);
                            break;
                        }
                    }        
                }
            }
            dtFields[fi['rst_PtrFilteredIDs']] = '';    
        }

        dtFields[fi['rst_DisplayName']] = '';//input_label;
        dtFields[fi['rst_RequirementType']] = 'optional';
        dtFields[fi['rst_RequirementType']] = 'optional';
        dtFields[fi['rst_MaxValues']] = 1;
        //dtFields[fi['rst_DisplayWidth']] = 50; //@todo set 50 for freetext and resource
        //dtFields[fi['rst_DisplayWidth']] = 50;
        
        //if(window.hWin.HEURIST4.util.isnull(init_value)) init_value = '';
        
        var that = this;

        var ed_options = {
            recID: -1,
            dtID: dtID,
            //rectypeID: rectypeID,
            rectypes: window.hWin.HEURIST4.rectypes,
            values: '',// init_value
            readonly: false,

            showclear_button: false,
            suppress_prompts: true,
            show_header: false,
            detailtype: 'resource',  //overwrite detail type from db (for example freetext instead of memo)
            dtFields:dtFields,
            
            change: function(){
                var rec_id = getFieldValue('target_record');
                getRecordValue(rec_id, 'target');
                _enableActionButton();
            }    
        };

        $("<div>").attr('id','target_record')
                    //.css({'padding-left':'130px'})
                    .editing_input(ed_options).appendTo($fieldset)
                    .find('input').css({'font-weight':'bold'});
    }

    //
    // create input element for relation selecor
    //
    function _createInputElement_Relation( party, rectypeID, dtID ){ 
//AAA
        if(window.hWin.HEURIST4.util.isempty(dtID)) return;
    
        var $field = $('#rt_'+party+'_sel_'+dtID).empty();

        
        var typedefs = window.hWin.HEURIST4.rectypes.typedefs;
        var fi = typedefs.dtFieldNamesToIndex;
        var dtFields =  window.hWin.HEURIST4.util.cloneJSON(typedefs[rectypeID].dtFields[dtID]);

        dtFields[fi['rst_DisplayName']] = 'Relationship type:';//input_label;
        dtFields[fi['rst_RequirementType']] = 'optional';
        dtFields[fi['rst_RequirementType']] = 'optional';
        dtFields[fi['rst_MaxValues']] = 1;
        
        var that = this;

        var ed_options = {
            recID: -1,
            dtID: dtID,
            //rectypeID: rectypeID,
            rectypes: window.hWin.HEURIST4.rectypes,
            values: '',// init_value
            readonly: false,

            showclear_button: false,
            showedit_button: true,
            suppress_prompts: true,
            useHtmlSelect:true, 
            //show_header: false,
            detailtype: 'relationtype',  //overwrite detail type from db (for example freetext instead of memo)
            dtFields:dtFields,
            
            change: function(){
                _enableActionButton();
            }    
        };

        $("<div>").attr('id','target_record')
                    //.css({'padding-left':'130px'})
        $field.editing_input(ed_options);
    }
    
    
    //
    //
    //
    function getFieldValue(input_id) {
        var ele = $('#'+input_id);
        if(ele.length>0){
            var sel = ele.editing_input('getValues');
            if(sel && sel.length>0){
                return sel[0];
            }
        }
        return null;
    }

    function getRecordValue(rec_id, party) {
        
        var request = {q:'ids:'+rec_id, w:'e',f:'detail'};  //w=e everything including temporary
        
        window.hWin.HAPI4.RecordMgr.search(request, function(response){
            if(response.status == window.hWin.ResponseStatus.OK){
                var resdata = new hRecordSet(response.data);
        
                //add SELECT and fill it with values
                var idx, dty, rec_titles = [];
                var records = resdata.getRecords();
            
                var record = resdata.getById(rec_id);
                var rec_title = resdata.fld(record, 'rec_Title');
                if(!rec_title) rec_title = 'Record title is not defined yet';

                var recRecTypeID = resdata.fld(record, 'rec_RecTypeID');
                
                if(party=='source'){
                    sSourceName = rec_title;    
                    source_RecTypeID = recRecTypeID;
                }else{
                    sTargetName = rec_title;    
                    target_RecTypeID = recRecTypeID;
                }
                

                rec_titles.push('<b>'+window.hWin.HEURIST4.rectypes.names[recRecTypeID]+'</b>');
                $('#'+party+'_title').text(rec_title);
                $('#'+party+'_rectype').text(window.hWin.HEURIST4.rectypes.names[recRecTypeID]);
                $('#'+party+'_rectype_img').addClass('rt-icon').css('background-image', 'url("'+top.HAPI4.iconBaseURL+recRecTypeID+'")');
                
                //find fields
                var oppositeRecTypeID = (party=='target')?source_RecTypeID:null; 
                
                if(!(relmarker_dty_ID>0))
                    _fillSelectFieldTypes(party, recRecTypeID, oppositeRecTypeID);
                
                $('#div_'+party+'1').css('display','inline-block');
                if(party=='target'){
                    if(target_ID>0){
                        target_RecTypeID = recRecTypeID;
                        $('#target_title').show();    
                    }else{
                        $('#target_title').hide();    
                        $('#target_rectype_img').hide(); 
                        $('#target_rectype').css({'margin-top':0,'margin-left':'5px'}); 
                        $('#div_target1').css({'display':'block','padding-left':'120px'}); 
                        $('#div_target2').find('.link-div').css({'background':'none','border':'none'});
                        $('#div_target2').find('a').css({'font-weight':'bold','font-size':'1.05em'});
                    }
                }else{
                    if(source_ID>0){
                        source_RecTypeID = recRecTypeID;
                        _fillSelectFieldTypes('source', source_RecTypeID);
                        if(target_ID>0){
                            getRecordValue(target_ID, 'target');
                        }
                    }
                }
    
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
            
            
        });
    } 
    
    

    /**
    *  main action function 
    */
    function addLinks(){


        /*       recIDs - list of records IDS to be processed
        *       rtyID  - filter by record type
        *       dtyID  - detail field to be added
        *       for addition: val: | geo: | ulfID: - value to be added
        *       for edit sVal - search value,  rVal - replace value
        *       for delete: sVal  
        *       tag  = 0|1  - add system tag to mark processed records
        */
        
        var RT_RELATION = window.hWin.HAPI4.sysinfo['dbconst']['RT_RELATION'], //1
            DT_TARGET_RESOURCE = window.hWin.HAPI4.sysinfo['dbconst']['DT_TARGET_RESOURCE'], //5
            DT_RELATION_TYPE = window.hWin.HAPI4.sysinfo['dbconst']['DT_RELATION_TYPE'], //6
            DT_PRIMARY_RESOURCE = window.hWin.HAPI4.sysinfo['dbconst']['DT_PRIMARY_RESOURCE']; //7
        
        var idx, requests = [], targetIDs = [], sourceIDs=[], currentScope=[];
        
        var ele = $('input[type="radio"][name="link_field"]:checked');
        var dtyID = ele.val()
        var data_type = ele.attr('data-type');
        var isReverce = (ele.attr('data-party')=='target');
        
        if(!(source_ID>0)){
            
            var rty_ID = Number(source_RecTypeID);
            
            var opt = selectRecordScope.find(":selected");
            var isSelection = (opt.attr('data-select')==1);
            
            if(isSelection){
                var sels = window.hWin.HAPI4.currentRecordsetSelection;
                for (idx in sels){ //find all selected rectypes
                    if(idx>=0){  
                        var rec = window.hWin.HAPI4.currentRecordset.getById(sels[idx]);
                        var rt = Number(window.hWin.HAPI4.currentRecordset.fld(rec, 'rec_RecTypeID'));
                        if(rt==rty_ID){
                            currentScope.push(sels[idx]);  
                        }
                    }
                }
            }else{
                 currentScope = window.hWin.HAPI4.currentRecordset.getIdsByRectypeId(rty_ID);
            }
                
        }else{
            currentScope = [source_ID];
        }
        
        
        if(isReverce){
                        
            var kp = sSourceName;
            sSourceName = sTargetName;
            sTargetName = kp;
            
            targetIDs = currentScope;
            sourceIDs = [(target_ID>0) ?target_ID :getFieldValue('target_record')];
        }else{
            sourceIDs = currentScope;
            targetIDs = [(target_ID>0) ?target_ID :getFieldValue('target_record')];
        }
        
        var res = {};

        if(data_type=='resource'){
            
                for(idx in targetIDs){
                   if(idx>=0){ 
                        requests.push({a: 'add',
                            recIDs: sourceIDs.join(','),
                            dtyID:  dtyID,
                            val:    targetIDs[idx]});
                   }
                }
                
                res = {rec_ID: targetIDs[0], rec_Title:sTargetName, rec_RecTypeID:target_RecTypeID };
                
        }else{ //relmarker
                /*
                var rl_ele = $('#rec'+(isReverce?'target':'source')+'_sel_'+dtyID);
                var termID = rl_ele.val(),
                    sRelation = rl_ele.find('option:selected').text();
                */
                var termID = getFieldValue('rt_'+(isReverce?'target':'source')+'_sel_'+dtyID);
        
                for(idx in currentScope){
                
                    var details = {};
                    details['t:'+DT_PRIMARY_RESOURCE] = [ isReverce?sourceIDs[0]:sourceIDs[idx] ];
                    details['t:'+DT_RELATION_TYPE] = [ termID ];
                    details['t:'+DT_TARGET_RESOURCE] = [ isReverce?targetIDs[idx]:targetIDs[0] ];
                    
                    
                    if(window.hWin.HEURIST4.util.isempty(sSourceName)){
                       var record = window.hWin.HAPI4.currentRecordset.getById( isReverce?sourceIDs[0]:sourceIDs[idx] );
                       sSourceName =  window.hWin.HAPI4.currentRecordset.fld(record, 'rec_Title');
                       source_RecTypeID = window.hWin.HAPI4.currentRecordset.fld(record, 'rec_RecTypeID');
                    }
                    if(window.hWin.HEURIST4.util.isempty(sTargetName)){
                       var record = window.hWin.HAPI4.currentRecordset.getById( isReverce?targetIDs[idx]:targetIDs[0] );
                       sTargetName =  window.hWin.HAPI4.currentRecordset.fld(record, 'rec_Title');
                    }
                    
                    
                    requests.push({a: 'save',    //add new relationship record
                        ID:-1, //new record
                        RecTypeID: RT_RELATION,
                        //RecTitle: 'Relationship ('+sSourceName+' '+sRelation+' '+sTargetName+')',
                        details: details });
                     
                }//for
                res = {
                    source:{rec_ID: sourceIDs[0], rec_Title:sSourceName, rec_RecTypeID:source_RecTypeID},
                    target:{rec_ID: targetIDs[0], rec_Title:sTargetName, rec_RecTypeID:target_RecTypeID},
                    //rec_ID: targetIDs[0], rec_Title:sTargetName, rec_RecTypeID:target_RecTypeID,
                    relation_recID:0, trm_ID:termID };
        }
        
        addLinkOrRelation(0, requests, res);
    }

    //
    // individual action
    //
    function addLinkOrRelation(idx, requests, res){

        if(idx<requests.length){

            var request = requests[idx];
            
            var hWin = window.hWin;
            
            function __callBack(response){
                    if(response.status == hWin.ResponseStatus.OK){
                        if(requests[idx].a=='s'){
                            res.relation_recID = response.data; //add rec id
                        }
                        idx = idx + 1;
                        addLinkOrRelation(idx, requests, res);
                    }else{
                        hWin.HEURIST4.msg.showMsgErr(response);
                    }
            }        
        
            if(request.a=='add'){  //add link - batch update - add new field
                window.hWin.HAPI4.RecordMgr.batch_details(request, __callBack);
            }else{ //add relationship - add new record
                window.hWin.HAPI4.RecordMgr.save(request, __callBack);
            }
        }else if(requests.length>0){
            res.count = requests.length;
            window.close(res);//'Link'+(requests.length>1?'s':'')+' created...');
        }
    }
       
    
    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

    }

    
    _init();
    return that;  //returns object
}
