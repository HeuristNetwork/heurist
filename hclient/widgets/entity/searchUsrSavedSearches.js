/**
* Search header for UsrSavedSearches manager
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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

$.widget( "heurist.searchUsrSavedSearches", $.heurist.searchEntity, {

    //
    _initControls: function() {
        
        var that = this;
        
        this.input_search_group = this.element.find('#input_search_group');   //user group
        var topOptions = [{key:'any',title:'any group'},{key:window.hWin.HAPI4.user_id(),title:'My Filters'}];
        
        if(window.hWin.HAPI4.is_admin()){
            window.hWin.HEURIST4.ui.createUserGroupsSelect(this.input_search_group[0], 'all_my_first' , 
                        topOptions);
        }else{
            window.hWin.HEURIST4.ui.createUserGroupsSelect(this.input_search_group[0], null, 
                        topOptions);
        }
        
        this._super();

        //hide all help divs except current mode
        var smode = this.options.select_mode; 
        this.element.find('.heurist-helper1').find('span').hide();
        this.element.find('.heurist-helper1').find('span.'+smode+',span.common_help').show();
        
        this.btn_add_record = this.element.find('#btn_add_record');

        if(this.options.edit_mode=='none' || this.options.search_form_visible==false){
            this.btn_add_record.hide();
        }else{
            this.btn_add_record.css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Add New Filter"), icon: "ui-icon-plus"})
                .click(function(e) {
                    that._trigger( "onadd" );
                }); 

            //@todo proper alignment
            if(this.options.edit_mode=='inline'){
                this.btn_add_record.css({'float':'left','border-bottom':'1px lightgray solid',
                'min-height': '2.4em', 'margin-bottom': '0.4em'});    
            }                       
        }
        
        this._on(this.input_search_group,  { change:this.startSearch });
        
        if( this.options.svs_UGrpID>0 ){ //show filters from given group only
            this.input_search_group.parent().hide();
            this.input_search_group.val(this.options.svs_UGrpID);
            
            if(!window.hWin.HAPI4.is_admin()){
                this.btn_add_record.hide();
            }
        }
             
        this.input_sort_type = this.element.find('#input_sort_type');
        this._on(this.input_sort_type,  { change:this.startSearch });
                      
        this.startSearch();            
    },  

    
    //
    // public methods
    //
    startSearch: function(){
        
            this._super();
            
            var request = {}
            
            if(this.options.initial_filter!=null){
                request = this.options.initial_filter;
                if(this.options.search_form_visible){
                    this.options.initial_filter = null;
                }
            }
                
            if(this.input_search.val()!=''){
                request['svs_Name'] = this.input_search.val();
            }
            
            if(this.input_search_group.val()>0){
                request['svs_UGrpID'] = this.input_search_group.val();
            }
            
            this.input_sort_type = this.element.find('#input_sort_type');
            if(this.input_sort_type.val()=='name'){
                request['sort:svs_Name'] = '1' 
            }else if(this.input_sort_type.val()=='recent'){
                request['sort:svs_ID'] = '-1' 
            }else{
                request['sort:svs_Name'] = '1';   
            }
                 
            
/*
            if(this.element.find('#cb_selected').is(':checked')){
                request['ugr_ID'] = window.hWin.HAPI4.get_prefs('recent_Users');
            }
            if(this.element.find('#cb_modified').is(':checked')){
                var d = new Date(); 
                //d = d.setDate(d.getDate()-7);
                d.setTime(d.getTime()-7*24*60*60*1000);
                request['ugr_Modified'] = '>'+d.toISOString();
            }
*/            
            
            
            
            if(false && $.isEmptyObject(request)){
                this._trigger( "onresult", null, {recordset:new hRecordSet()} );
            }else{
                this._trigger( "onstart" );
        
                request['a']          = 'search'; //action
                request['entity']     = this.options.entity.entityName;
                request['details']    = 'id'; //'id';
                request['request_id'] = window.hWin.HEURIST4.util.random();
                
                //we may search users in any database
                request['db']     = this.options.database;

                var that = this;                                                
           
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            that._trigger( "onresult", null, 
                                {recordset:new hRecordSet(response.data), request:request} );
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
                    
            }            
    }
});
