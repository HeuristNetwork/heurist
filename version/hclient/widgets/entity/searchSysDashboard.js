/**
* Search header for manageSysUsers manager
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

$.widget( "heurist.searchSysDashboard", $.heurist.searchEntity, {

    //
    _initControls: function() {
        
        var that = this;
        
        this._super();

        //hide all help divs except current mode
        /*
        var smode = this.options.select_mode; 
        this.element.find('.heurist-helper1').find('span').hide();
        this.element.find('.heurist-helper1').find('span.'+smode+',span.common_help').show();
        */
        this.btn_add_record = this.element.find('#btn_add_record')
                .css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Add New Entry"), icon: "ui-icon-plus"})
                .click(function(e) {
                    that._trigger( "onadd" );
                }); 

        this.btn_apply_order = this.element.find('#btn_apply_order')
                .hide()
                .css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Save New Order"), icon: "ui-icon-move-v"})
                .click(function(e) {
                    that._trigger( "onorder" );
                }); 

        this.btn_set_mode = this.element.find('#btn_set_mode')
                .css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("View shortcuts")})
                .click(function(e) {
                    window.hWin.HAPI4.save_pref('prefs_sysDashboard', 
                        {show_as_ribbon:1, 
                         show_on_startup: 1 });     
                    that._trigger( "onclose" );
                    
                    //that._trigger( "viewmode" );
                }); 

        this.btn_close_mode = this.element.find('#btn_close_mode')
                .css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Hide shortcuts")})
                .click(function(e) {
                    window.hWin.HAPI4.save_pref('prefs_sysDashboard', 
                        {show_as_ribbon:1, 
                         show_on_startup:0 });     
                    that._trigger( "onclose" );
                }); 
                
                
        this.btn_show_on_startup = this.element.find('#btn_show_on_startup2')
                .css({'min-width':'9m'})
                    .button({label: window.hWin.HR("Don't show again")})
                .click(function(e) {
                    
                    //don't show  dashboard on startup
                    var params = window.hWin.HAPI4.get_prefs_def('prefs_sysDashboard', {show_as_ribbon:0} );
                    params['show_on_startup'] = 0;
                    window.hWin.HAPI4.save_pref('prefs_sysDashboard', params);     
                    
                    that._trigger( "onclose" );
                }); 
                
                
        this.input_search_inactive = this.element.find('#input_search_inactive');
        this._on(this.input_search_inactive,  { change:this.startSearch });
        
        this.input_sort_type = this.element.find('#input_sort_type');
        this._on(this.input_sort_type,  { change:this.startSearch });
                     
        this._trigger( "oninit", null );
                      
        this.startSearch();            
    },  

    
    //
    // public methods
    //
    startSearch: function(){
        
            this._super();
            
            var request = {}
            
            if(this.options.isViewMode){
                
                request['dsh_Enabled'] = 'y';
                request['sort:dsh_Order'] = '1' 
                
                //if database empty - hide some entries
                if(window.hWin.HAPI4.sysinfo['db_total_records']<1){
                    request['dsh_ShowIfNoRecords'] = 'y';
                }
                
            }else{
                /*
                if(this.input_search.val()!=''){
                    request['dsh_Label'] = this.input_search.val();
                }
                
                this.input_sort_type = this.element.find('#input_sort_type');
                if(this.input_sort_type.val()=='order'){
                    request['sort:dsh_Order'] = '1' 
                }else {
                    request['sort:dsh_Label'] = '1';   
                }
                */
                if(this.input_search_inactive.is(':checked')){
                    request['dsh_Enabled'] = 'n';
                }
                request['sort:dsh_Order'] = '1' 
            }
            
           
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
});
