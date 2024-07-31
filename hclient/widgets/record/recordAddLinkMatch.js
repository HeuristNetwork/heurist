/**
* recordAddLinkMatch.js - create links by matching selected fields
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$.widget( "heurist.recordAddLinkMatch", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 520,
        width:  800,
        modal:  true,
        init_scope: 'selected',
        title:  'Foreign Key matching. Add links between records by matching field values',
        
        htmlContent: 'recordAddLinkMatch.html',
        helpContent: 'recordAddLinkMatch.html', //in context_help folder
        
        relationtype: null
        
    },

    source_RecTypeID:null, 
    target_RecTypeID:null,
    sSourceName:'',
    sTargetName:'',
    
    //    
    //
    //
    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR('Create links');
        return res;
    },

    //
    // select option by rectypes
    //
    _fillSelectRecordScope: function (){

        let scope_types = this.options.scope_types;
        this.selectRecordScope.empty();
        
        let useHtmlSelect = false;
        
        if(scope_types=='none'){
            this.selectRecordScope.parent().hide();
            return;    
        }

        let opt, selScope = this.selectRecordScope.get(0); //selector
        //window.hWin.HEURIST4.ui.addoption(selScope,0,'please select the records to be affected …');

        let rty_ID = 0;
        let rectype_Ids = this._currentRecordset.getRectypes();
        
        if(rectype_Ids.length==1){

            rty_ID = rectype_Ids[0];
            this.source_RecTypeID = rty_ID;
            
            window.hWin.HEURIST4.ui.addoption(selScope,
                'all', 'All records: ' + $Db.rty(rty_ID,'rty_Plural'));
                
            if(this._currentRecordsetSelIds.length>0){
                window.hWin.HEURIST4.ui.addoption(selScope,
                    'selected', 'Selected records: ' + $Db.rty(rty_ID,'rty_Plural'));
            }
        }
          
        if(!(rty_ID>0)){
            window.hWin.HEURIST4.msg.showMsgDlg(
        '<b>Mixed record types</b>'
        +'<p>The current query must contain only a single record type (this is enforced to avoid accidental errors).</p>' 
        +'<p>Please select records of a single type, either by individual selection or a revised filter, and repeat this action. </p>');

            return false;
        }
            
        
        this._on( this.selectRecordScope, { change: this._onRecordScopeChange} );        
        //this.selectRecordScope.val(rty_ID);    
        if(selScope.selectedIndex<0) selScope.selectedIndex=0;
        
        window.hWin.HEURIST4.ui.initHSelect(this.selectRecordScope, false);
        
        this._onRecordScopeChange();
        
        return true;
    },

    //
    // overwritten
    //
    _onRecordScopeChange: function () 
    {
        this._fillSelectFieldTypes('source', this.source_RecTypeID);
    },
 
    //
    // find pointer and relationships for selected record type 
    // party - source or target                                            
    // rty_ID - selected record type
    // 
    _fillSelectFieldTypes: function (party, recRecTypeID) {

        this.element.find('#'+party+'_field').empty();
        
        
            
        let details = $Db.rst(recRecTypeID);
            
        if(details)
        {   
            
        if(party=='source')
        {
            
            let fieldPointerSel = this.element.find('#sel_pointer_field');
            fieldPointerSel.empty();
            
            let that = this;
            // get structures for both record types and filter out link and relation maker fields for links
            //                                      and text and numeric fields for matching             
            details.each2(function(dtyID, detail) {
            
            let field_type = $Db.dty(dtyID, 'dty_Type');
            let req_type  = detail['rst_RequirementType'];
            
            //|| field_type=='relmarker')
            if ( (field_type!='resource') || req_type=='forbidden' ) {
                 return true;//continue
            }
            
            //get name, contraints
            let dtyName = detail['rst_DisplayName'];
/* for list of radio buttons                
                //add UI elements
    $('<div class="field_item" style="line-height:2.5em;padding-left:20px">'
    +'<label style="font-style:italic">' //for="cb'+party+'_cb_'+dtyID+'"
    +'<input name="link_field" type="radio" id="cb'+party+'_cb_'+dtyID+'" '
    +' data-party="'+party+'" value="'+dtyID+'" data-type="'+field_type+'"'
    +' class="cb_addlink text ui-widget-content ui-corner-all"/>'                                     
    + dtyName+'</label>&nbsp;'
    + '<div style="display:inline-block;vertical-align:-webkit-baseline-middle;padding-left:20px">'
    + '<div id="rt_'+party+'_sel_'+dtyID+'" style="display:table-row;line-height:21px"></div></div>'
    +'<div>').appendTo(that.element.find('#'+party+'_field'));
    
    
                if(field_type=='relmarker'){
                    that._createInputElement_Relation( party, recRecTypeID, dtyID ); 
                }
                that._on(that.element.find('#cbsource_cb_'+dty),{change:that._fillTargetRecordTypes});
*/            
                window.hWin.HEURIST4.ui.addoption(fieldPointerSel.get(0), dtyID, dtyName);
        });//for fields

        
            //select first to trigger creaton of target select rectype
            if(this.element.find('input[type="radio"][name="link_field"]').length>0){
                $(this.element.find('input[type="radio"][name="link_field"]')[0]).attr('checked','checked').change();
            }
            
            
            this._on( fieldPointerSel, { change: that._fillTargetRecordTypes} );        
            //if(fieldPointerSel.selectedIndex<0) fieldPointerSel.selectedIndex=0;
            window.hWin.HEURIST4.ui.initHSelect(fieldPointerSel, false);
            fieldPointerSel.trigger('change');
            
            
        }//for source 
        
        // create matching field
        let fieldSelect = $('#sel_fieldtype_'+party);
        window.hWin.HEURIST4.ui.createRectypeDetailSelect(fieldSelect.get(0), recRecTypeID, ['freetext','blocktext'], null);
                     //,{useHtmlSelect:true});
        window.hWin.HEURIST4.ui.initHSelect(fieldSelect, false);
        this._on(fieldSelect,{change:this._findMatchesCount});
        
        fieldSelect.trigger('change');

        }//if rectype defined
    },  

    //
    //
    //    
    _findMatchesCount: function(event){
        
        let fieldSelect = $(event.target);
        
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('#btnDoAction'), true);
        
        if(fieldSelect.attr('id')=='sel_fieldtype_source'){

            let cnt_info = this.element.find('#count_source_unique').text('');
            
            if(fieldSelect.val()>0){
                cnt_info.addClass('ui-icon ui-icon-loading-status-balls rotate')
            
                window.HAPI4.RecordMgr.get_aggregations({a:'count_distinct_values',rt:this.source_RecTypeID, dt:fieldSelect.val()}, 
                function(response){     
                    cnt_info.removeClass('ui-icon ui-icon-loading-status-balls rotate')
                    if(response.status == window.hWin.ResponseStatus.OK){
                        cnt_info.text(response.data+' values');                
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
            }
        }
        
        if(this.target_RecTypeID>0){

            let fieldSelectTrg = this.element.find('#sel_fieldtype_target');
            let fieldSelectSrc = this.element.find('#sel_fieldtype_source');

            let cnt_info2 = this.element.find('#count_target_matches').text('');
            
            if(fieldSelectSrc.val()>0 && fieldSelectTrg.val()>0){
                cnt_info2.addClass('ui-icon ui-icon-loading-status-balls rotate')
                
                let that = this;
            
                window.HAPI4.RecordMgr.get_aggregations({a:'count_matches',
                                rec_IDs: this._getRecordsScope().join(','),
                                rty_src:this.source_RecTypeID, 
                                dty_src:fieldSelectSrc.val(),
                                rty_trg:this.target_RecTypeID, 
                                dty_trg:fieldSelectTrg.val()
                }, 
                function(response){     
                    cnt_info2.removeClass('ui-icon ui-icon-loading-status-balls rotate')
                    if(response.status == window.hWin.ResponseStatus.OK){
                        cnt_info2.text(response.data+' matches');                
                        that._enableActionButton();
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
            }
        }
        
    },
    
    //
    // source field has been selectd
    // 1) fill target record selector
    // 2) find unique values for selected field 
    //
    _fillTargetRecordTypes: function(event){

        let dtID = $(event.target).val();
        
        if(window.hWin.HEURIST4.util.isempty(dtID)) return;
        
        let rt_constraints = (dtID>0)?$Db.dty(dtID, 'dty_PtrTargetRectypeIDs'):'';
        
        if(!$.isArray(rt_constraints)){
            if(window.hWin.HEURIST4.util.isempty(rt_constraints)){
                rt_constraints = [];
            }else{
                rt_constraints = rt_constraints.split(',');        
            }
        }
        
        this.targetRtySelect = $('#target_record_type');
        window.hWin.HEURIST4.ui.createRectypeSelectNew(this.targetRtySelect.get(0), 
            {rectypeList:rt_constraints, useHtmlSelect:true, useCounts:true});
        
        this._on( this.targetRtySelect, {
                change: this._onTargetRtySelectChange} );        
        
        window.hWin.HEURIST4.ui.initHSelect(this.targetRtySelect, false);
        
        this._onTargetRtySelectChange();
        
    },
    
    _onTargetRtySelectChange: function(){
        this.element.find('#count_target_rty').text('');
        this.target_RecTypeID = this.targetRtySelect.val(); 
        if(this.target_RecTypeID>0){
            let rty_usage_cnt = $Db.rty(this.target_RecTypeID,'rty_RecCount');
            if(rty_usage_cnt>0){
                this._fillSelectFieldTypes('target', this.target_RecTypeID);
                this.element.find('#count_target_rty').text( rty_usage_cnt + ' records' );
            }
        }     
        if(this.element.find('#count_target_rty').text()==''){
            this.element.find('#sel_fieldtype_target').empty();
            this.element.find('#count_target_matches').empty();
            this._enableActionButton();
        }
        
    },
  
    //
    // create input element for relation selecor
    //
    _createInputElement_Relation: function ( party, rectypeID, dtID ){ 

        if(window.hWin.HEURIST4.util.isempty(dtID)) return;
    
        let $field = this.element.find('#rt_'+party+'_sel_'+dtID).empty();

        let dt = $Db.dty(dtID);

        let vocab_id = dt['dty_JsonTermIDTree'];
        let trm_id = null;

        if(this.options.relationtype){
            trm_id = $Db.trm_InVocab(vocab_id, this.options.relationtype);

            if(!trm_id){
    
                trm_id = $Db.getTermByLabel(vocab_id, this.options.relationtype);

                if(!trm_id){
                    trm_id = $Db.getTermByCode(vocab_id, this.options.relationtype);
                }
            }else{
                trm_id = this.options.relationtype;
            }
        }

        if(!trm_id){
            trm_id = $Db.rst(rectypeID, dtID, 'rst_DefaultValue');
        }
        
        //var dtFields =  window.hWin.HEURIST4.util.cloneJSON($Db.rst(rectypeID, dtID));
        let dtFields = {};
        dtFields['rst_DisplayName'] = 'Relationship type:';//input_label;
        dtFields['rst_RequirementType'] = 'optional';
        dtFields['rst_MaxValues'] = 1;
        dtFields['rst_DisplayWidth'] = '25ex';
        dtFields['dty_Type'] = 'relationtype';
        dtFields['rst_PtrFilteredIDs'] = '';//dt['dty_PtrTargetRectypeIDs'];
        dtFields['rst_FilteredJsonTermIDTree'] = vocab_id;
        dtFields['rst_DefaultValue'] = trm_id;
        dtFields['dtID'] = dtID;
        
        let that = this;

        let ed_options = {
            recID: -1,
            dtID: dtID,

            values: '',// init_value
            readonly: false,

            showclear_button: false,
            showedit_button: true,
            suppress_prompts: true,
            useHtmlSelect:false, 
            //show_header: false,
            //detailtype: 'relationtype',  //overwrite detail type from db (for example freetext instead of memo)
            dtFields:dtFields,
            is_insert_mode: true,

            
            change: function(){
                that._enableActionButton();
            }                                                           
        };

        //$("<div>").attr('id','target_record')
                    //.css({'padding-left':'130px'})
        $field.editing_input(ed_options);
    },
   
    
    //
    //
    //
    getFieldValue: function (input_id) {
        let ele =  this.element.find('#'+input_id);
        if(ele.length>0){
            let sel = ele.editing_input('getValues');
            if(sel && sel.length>0){
                return sel[0];
            }
        }
        return null;
    },

    
    //
    // enable add link button
    //
    _enableActionButton: function (){
        
        let isEnabled = (parseInt($('#count_target_matches').text())>0);
        
        if(isEnabled){
            let sel_field  = this.element.find('input[type="radio"][name="link_field"]:checked');
            
            if(sel_field.attr('data-type')=='relmarker'){
                //in case relmarker check if reltype selected
                let dtyID = sel_field.val()
                let termID = this.getFieldValue('rt_source_sel_'+dtyID);        
                isEnabled = (termID>0);
            }                
        }  
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('#btnDoAction'), !isEnabled );
    }, 
    
    _getRecordsScope: function()
    {
        let isSelection = (this.selectRecordScope.val()=='selected');
        let currentScope = isSelection?this._currentRecordsetSelIds:this._currentRecordset.getIds();
        return currentScope;
        
    },
    
    //
    //
    //
    doAction: function(){
        
        if(this.element.find('#div_result').is(':visible')){
            this.element.parents('.ui-dialog').find('#btnDoAction').button({label:'Create Links'});
            this.element.find('#div_result').hide();
            this.element.find('#div_fieldset').show();
            return;
        }
     
        let ele = this.element.find('input[type="radio"][name="link_field"]:checked');
        let dty_ID = ele.val();
        let trm_ID = 0;
        let data_type = ele.attr('data-type');   //resource or relmarker
        if(data_type!='resource'){
            trm_ID = this.getFieldValue('rt_source_sel_'+dty_ID);
        }
        
        let currentScope = this._getRecordsScope();
        
        let session_id = Math.round((new Date()).getTime()/1000);
        
        let request = {a: 'add_links_by_matching',
                            session: session_id,
                            dty_ID:  dty_ID,
                            trm_ID: trm_ID,
                            rec_IDs: currentScope.join(','),
                            rty_src:  this.source_RecTypeID,
                            dty_src: $('#sel_fieldtype_source').val(),
                            rty_trg: this.target_RecTypeID,
                            dty_trg: $('#sel_fieldtype_target').val(),
                            replace: ($('input[name="to_replace"]:checked').val()=='replace'?1:0)
                    };

        this.element.find('#div_result').empty();
        this._showProgress( session_id, false, 1000 );
        
        let that = this;
        
        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
            
            that._hideProgress();
            
            if(response.status == hWin.ResponseStatus.OK){
                
                that.element.find('#div_fieldset').hide();
                that.element.find('#div_result').html(
'<div style="padding:10px;display:table">'
+`<span class="table-cell">Records passed to process</span><span class="table-cell">&nbsp;&nbsp;${currentScope.length}</span><br><br>`
+`<span class="table-cell">Records updated</span><span class="table-cell">&nbsp;&nbsp;${response.data['records_updated']}</span><br><br>`
+`<span class="table-cell">Links added</span><span class="table-cell">&nbsp;&nbsp;${response.data['added']}</span><br><br>`
+`<span class="table-cell">Links already exist</span><span class="table-cell">&nbsp;&nbsp;${response.data['exist']}</span></div>`)
                .show();
                
                that.element.parents('.ui-dialog').find('#btnDoAction').button({label:'New Action'});
                
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response); 
            }
        });
    }
    
        
});
