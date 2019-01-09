/**
* Search header for manageDefRecTypes manager
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
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

$.widget( "heurist.searchDefRecTypes", $.heurist.searchEntity, {

    //
    _initControls: function() {
        
        var that = this;
        
        //this.widgetEventPrefix = 'searchDefRecTypes';
        
        this._super();

        //hide all help divs except current mode
        var smode = this.options.select_mode; 
        this.element.find('.heurist-helper1').find('span').hide();
        this.element.find('.heurist-helper1').find('span.'+smode+',span.common_help').show();
        
        this.btn_add_record = this.element.find('#btn_add_record');
        this.btn_find_record = this.element.find('#btn_find_record');

        if(this.options.edit_mode=='none'){
            this.btn_add_record.hide();
            this.btn_find_record.hide();
        }else{
            /*
            this.btn_add_record.css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Add New Record Type"), icon: "ui-icon-plus"})
                .click(function(e) {
                    that._trigger( "onadd" );
                }); 

            this.btn_find_record.css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Find/Add Record Type"), icon: "ui-icon-search"})
                .click(function(e) {
                    that._trigger( "onfind" );
                }); 
                
            //@todo proper alignment
            if(this.options.edit_mode=='inline'){
                this.btn_add_record.css({'float':'left','border-bottom':'1px lightgray solid',
                'min-height': '2.4em', 'margin-bottom': '0.4em'});    
            }
            */                       
        }
        
        this._on(this.input_search_type,  { change:this.startSearch });
        
        //@todo - possible to remove
        if( this.options.rtg_ID>0 ){
            this.input_search_group.parent().hide();
            this.input_search_group.val(this.options.rtg_ID);
        }else if( this.options.rtg_ID<0 ){  //addition of recctype to group
            //find any rt not in given group
            //exclude this group from selector
            this.input_search_group.find('option[value="'+Math.abs(this.options.rtg_ID)+'"]').remove();
        }else{
            this.btn_find_record.hide();
        }
             
        this.input_sort_type = this.element.find('#input_sort_type');
        this._on(this.input_sort_type,  { change:this.startSearch });
           
        this.reloadGroupSelector();
                      
        if( this.options.import_structure ){
            //this.element.find('#div_show_already_in_db').css({'display':'inline-block'});    
            this.chb_show_already_in_db = this.element.find('#chb_show_already_in_db');
            this._on(this.chb_show_already_in_db,  { change:this.startSearch });
        }
        if( this.options.simpleSearch){
            this.element.find('#div_search_group').hide();
            this.element.find('#input_sort_type_div').hide();
        }
        
       
        if($.isFunction(this.options.onInitCompleted)){
            this.options.onInitCompleted.call();
        }
        
        //this.startSearch();            
    },  
    
    //
    //
    //
    reloadGroupSelector: function (rectypes){
        
        this.input_search_group = this.element.find('#input_search_group');   //rectype group

        window.hWin.HEURIST4.ui.createRectypeGroupSelect(this.input_search_group[0],
                                            [{key:'any',title:'any group'}], rectypes);
        this._on(this.input_search_group,  { change:this.startSearch });
        
    },
    
    //
    // public methods
    //
    startSearch: function(){
        
            this._super();
            
            var request = {}
        
            if(this.input_search.val()!=''){
                request['rty_Name'] = this.input_search.val();
            }
            
            if( this.options.rtg_ID<0 ){
                //not in given group
                request['not:rty_RecTypeGroupID'] = Math.abs(this.options.rtg_ID);
            }
        
            if(this.input_search_group.val()>0){
                request['rty_RecTypeGroupID'] = this.input_search_group.val();
            }
            
            
            if(this.chb_show_already_in_db && !this.chb_show_already_in_db.is(':checked')){
                    request['rty_ID_local'] = '=0';
            }
            
            this.input_sort_type = this.element.find('#input_sort_type');
            if(this.input_sort_type.val()=='recent'){
                request['sort:rty_Modified'] = '-1' 
            }else if(this.input_sort_type.val()=='id'){
                request['sort:rty_ID'] = '1';   
            }else{
                request['sort:rty_Name'] = '1';   
            }
  
            if(this.options.use_cache){
            
                this._trigger( "onfilter", null, request);            
            }else
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

                //request['DBGSESSID'] = '423997564615200001;d=1,p=0,c=0';

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
