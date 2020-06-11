/**
* recordLookupMPCE.js 
* 
*  1) It loads html content from recordLookupMPCE.html - define this file with controls as you wish
*  2) Init these controls and define behaviour in _initControls
* 
* 
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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

$.widget( "heurist.recordLookupMPCE", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 520,
        width:  800,
        modal:  true,
        
        mapping: null, //configuration from record_lookup_configuration.json
        edit_fields:null,  //realtime values from edit form fields 
        edit_record:null,  //recordset of the only record - currently editing record (values before edit start)
        
        title:  'Lookup values for Heurist record',
        
        htmlContent: 'recordLookupMPCE.html',
        helpContent: null, //help file in context_help folder

    },
    
    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        var that = this;
/*        
        this.element.find('fieldset > div > .header').css({width:'80px','min-width':'80px'})
        
        this._on(this.element.find('#btnStartSearch').button(),{
            'click':this._doSearch
        });
        
        this._on(this.element.find('input'),{
            'keypress':this.startSearchOnEnterPress
        });
*/        

//Passing data from the current record
//specifically work title (text), Parisian keyword (term ID), and Project keywords (record ID) as examples of text, terms and record pointers
console.log('EDIT FIELD VALUES');        
console.log(this.options.edit_fields); //array of current field values  {dty_ID:values,......}

        //record before edit status  {d:{dty_ID:values,......}}
        var record = this.options.edit_record.getFirstRecord(); //see core/recordset.js for various methods to manipulate a record
console.log('RECORD');        
console.log(record); 


//Accessing list of values for a vocabulary
//In this case list of all Parisian keywords (vocab 6953) to allow decoding of the IDs and choice from eg. a dropdown

    var terms = window.hWin.HEURIST4.ui.getPlainTermsList('enum',6953);
    
console.log('TERMS');            
console.log(terms); 

    var selObj = window.hWin.HEURIST4.ui.createTermSelectExt2( this.element.find('#select_keywords')[0],
            {datatype:'enum', 
             termIDTree:6953, 
             headerTermIDsList:null,  //array of disabled terms
             defaultTermID:null,   //default/selected term
             topOptions:null,      //top options  [{key:0, title:'...select me...'},....]
             useHtmlSelect:false}); //use native select of jquery select
    

//Reading data for a specific record type
//reading in all Project Keyword records (type 56) to build a session array of all the Project Keywords (Record ID + Title)
//reading in all the Work records (type 55) and associated Parisian Keyword ID and Project Keyword Record IDs. (illustrates how to get values for a terms field)
            var query_request = {q:'t:55,56', w:'all'}; 
            
            query_request['detail'] = 'details'; //or 'ids' - rec id only; or csv of field type ids

            window.hWin.HAPI4.RecordMgr.search(query_request, 
            function( response ){
                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    var recordset = new hRecordSet(response.data);
                    var cnt = 0;
                    
                    recordset.each(function(recID, record){
                        console.log(recID+'  '+this.fld(record,'rec_RecTypeID')+'  '+this.fld(record,'rec_Title'));
                        if(cnt>10) return false;
                        cnt++;
                    });
                    
                    
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });


//Popping up the record pointer selection window
//NOT NEEDED at this time but could be useful if it is simple to describe - do not build any new code 
        this._on(this.element.find('#btnLookup').button(),{
            'click':this._doLookup
        });
        
//Calling the Add Record function
//In this case to add Project Keywords. Parisian keywords are a fixed list of terms so they are simply selecting the list in memory.

        //in background
        this._on(this.element.find('#btnAddNewRecord').button(),{
            'click':function(){this._addNewRecord(true);}
        });
        //in popup editor
        this._on(this.element.find('#btnAddNewRecord2').button(),{
            'click':function(){this._addNewRecord(false);}
        });
        
        //by default action button is disabled
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('#btnDoAction'), false );
        
        return this._super();
    },

    //    
    // Extend list of dialog action buttons (bottom bar of dialog) or change their labeles or event handlers 
    //
    _getActionButtons: function(){
        var res = this._super(); //dialog buttons
        res[1].text = window.hWin.HR('My own action!');
        //res[1].disabled = null;
        return res;
    },

    //
    // Default event handler for "Go" button
    //
    doAction: function(){
        
            // use this function to show "in progress" animation and coverall screen for long operation
            window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());
            
            //use this function to hide caverall/loading
            window.hWin.HEURIST4.msg.sendCoverallToBack();

            //assign values to be send back to edit form - format is similar to this.options.edit_fields
            this._context_on_close = {'1':'New work title'};//, '949':[34,37,38], '3':'description value'};
            this._as_dialog.dialog('close');
    },
    
    //
    // lookup for Project keywords record
    //
    _doLookup: function(){
        
        
            var popup_options = {
                            select_mode: (false)?'select_multi':'select_single',
                            select_return_mode: 'recordset', //or 'ids' (default)
                            edit_mode: 'popup',
                            selectOnSave: true, //it means that select popup will be closed after add/edit is completed
                            title: 'Select or Add record',
                            rectype_set: '56', //  csv of record type ids
                            pointer_mode: null, //or addonly  or browseonly
                            pointer_filter: null, //initial filter
                            parententity: 0,
                            
                            width: 700,
                            height: 600,
                            //new_record_params: {ro: rv:}
                                                          
                            //it returns recordset
                            onselect:function(event, data){
                                     if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                        var recordset = data.selection;
                                        var record = recordset.getFirstRecord();
                                        
                                        var rec_Title = recordset.fld(record,'rec_Title');
                                        if(window.hWin.HEURIST4.util.isempty(rec_Title)){
                                            // no proper selection 
                                            // consider that record was not saved - it returns FlagTemporary=1 
                                            return;
                                        }
                                       
                                        var targetID = recordset.fld(record,'rec_ID');
                                        var rec_Title = recordset.fld(record,'rec_Title');
                                        var rec_RecType = recordset.fld(record,'rec_RecTypeID');
            
                                        window.hWin.HEURIST4.msg.showMsgDlg('You\'ve selected '+rec_Title);

console.log('SELECTED');
console.log(record);    
                                     }
                            }
            };
        
        
            window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);        
        
    },
    
    //
    //
    //
    _addNewRecord: function(isDirectAddition){

        var new_rec_data = {ID:0, RecTypeID:56, 
            no_validation:true, //allows save without filled required field 1061
            details:{957:'keycode', 1:this.element.find('#inpt_name').val(), 3:'Some Definitions'}};      
        //  , 1061: Tag
               
        if( isDirectAddition ){ //
            
            
                window.hWin.HAPI4.RecordMgr.saveRecord( new_rec_data,
                    function(response){ 
                            if(response.status == window.hWin.ResponseStatus.OK){
                                //response.data it returns new record id only
                                window.hWin.HEURIST4.msg.showMsgDlg('You\'ve add new record # '+response.data);
                            } else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                    });
                
        }else{
                //add new record via popup editor dialog
                window.hWin.HEURIST4.ui.openRecordEdit(-1, null,{
                    new_record_params:new_rec_data
                });
                
        }
        
        
    }
            
        
        
});

