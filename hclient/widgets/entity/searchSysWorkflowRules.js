/**
* Search header for SysWorkflowRules manager
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

$.widget( "heurist.searchSysWorkflowRules", $.heurist.searchEntity, {

    rty_list:[],
    //
    _initControls: function() {
        this._super();
        
        var that = this;
        
        this.element.find('#inner_title').text( this.options.entity.entityTitlePlural );
        
        //this.btn_search_start.css('float','right');   
        
        this.btn_add_record = this.element.find('#btn_add_record');
            this.btn_add_record
                    .button({label: window.hWin.HR('Add'), showLabel:true, 
                            icon:"ui-icon-plus"})
                    .addClass('ui-button-action')
                    .css({padding:'2px'})
                    .show();
                    
            this._on( this.btn_add_record, {
                        click: function(){
                            this._trigger( "onadd" );
                        }} );
        
        
        //this.input_search_rectype = this.element.find('#input_search_rectype');
        //this.input_search_rectype = window.hWin.HEURIST4.ui.createRectypeSelectNew(this.input_search_rectype.get(0),
        //    {topOptions:'select record type'});
        //this._on(this.input_search_rectype,  { change:this.startSearch });

        /* stage enum filter

        var ed_options = {
            recID: -1,
            dtID: 'sel_search_stage',
            //rectypeID: rectypeID,
            //show_header: false,
            values: [''],
            readonly: false,
            showclear_button: true,
            dtFields:{
                dty_Type: 'enum',
                rst_DisplayName: 'Stage:', rst_DisplayHelpText:'',
                rst_FilteredJsonTermIDTree: '2-9453'
            },
            change: function(){ that.startSearch(); }
            //change:_onAddRecordChange
        };

        var ele = this.element.find('#sel_search_stage');
        ele.editing_input(ed_options);
        ele.find('.btn_add_term').hide().css('visibility','hidden');
        ele.find('.header').hide().css('visibility','hidden');
        
        this.input_search_stage = ele.find('select');
        this.startSearch();            
        */
        
        this.refreshSelectors(); //
    },  
    
    //
    // public methods
    //
    startSearch: function(){
        
            this._super();
            
            var request = {};
        
            if(this.input_search_rectype.val() && this.input_search_rectype.val()!='any'){
                request['swf_RecTypeID'] = this.input_search_rectype.val();        
            }
            //if(this.input_search_stage.val() && this.input_search_stage.val()!='any'){
            //    request['swf_Stage'] = this.input_search_stage.val();    
            //}
            
console.log('startSearch '+request['swf_RecTypeID']);            
            
            if($.isEmptyObject(request) || this.rty_list.length==0){
                this._trigger( "onresult", null, {recordset:new hRecordSet()} );
            }else{
            
                this._trigger( "onstart" );
        
                request['a']          = 'search'; //action
                request['entity']     = this.options.entity.entityName;
                request['details']    = 'list';
                request['request_id'] = window.hWin.HEURIST4.util.random();
                
                var that = this;                                                
                
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            
                            var recset = new hRecordSet(response.data);
                            
                            that._trigger( "onresult", null, 
                                    {recordset:recset, request:request} );
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
                    
            }              
                     
    },
    
    //
    // Create rectype selector
    //
    refreshSelectors: function(rty_ID){
console.log('refreshSelectors '+rty_ID);

        this.input_search_rectype = this.element.find('#input_search_rectype');

        var need_refresh = true;
        if(rty_ID>0){
            if(this.rty_list.indexOf(rty_ID)<0){
                this.rty_list.push(rty_ID);
            }else{
                need_refresh = !this.input_search_rectype.hSelect('instance');
            }
        }else{
            
            var that = this;
            var request = {a:'search', entity:this.options.entity.entityName, details:'rty'};
            
            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        
                        that.rty_list = response.data.records;
                        if(that.rty_list.length>0){
                            if(that.input_search_rectype.hSelect('instance')){
                                that._off(that.input_search_rectype, 'change');
                                that.input_search_rectype.hSelect('destroy');    
                            }
                            that.refreshSelectors( that.rty_list[0] );
                        }else{
                            that._trigger( "onresult", null, {recordset:new hRecordSet()} );
                        }
                        
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
            return;
        }
        
        var rty_selector;
        if( need_refresh ){
            
            rty_selector = window.hWin.HEURIST4.ui.createRectypeSelectNew(
                this.input_search_rectype.get(0), 
                    {rectypeList:  this.rty_list, showAllRectypes: true }); //topOptions:'select record type'
            this._on(rty_selector,  { change:this.startSearch });
            //this.input_search_rectype = this.element.find('#input_search_rectype');
        }else{
            rty_selector = this.input_search_rectype.hSelect();            
        }
        
        if(this.rty_list.length>0){
                
            //var idx = $(this.input_search_rectype).find('option[value="'+rty_ID+'"]').index();
            //this.input_search_rectype[0].selectedIndex = idx;
            
            rty_selector.val(rty_ID);
            rty_selector.hSelect('refresh');
            
            if(rty_selector.val()>0){
                this.startSearch();
            }
                
        }else{
            this._trigger( "onresult", null, {recordset:new hRecordSet()} );
        }
            
    }

});
