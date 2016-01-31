/**
* Record manager - Search header
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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


$.widget( "heurist.searchSysUsers", {

    // default options
    options: {
        select_mode: 'manager', //'select_single','select_multi','manager'
        
        //initial filter by title and subset of groups to search
        filter_title: null,
        filter_group_selected:null,
        filter_groups: null,
        
        // callbacks - events
        onstart:null,
        onresult:null
        
    },
    
    _need_load_content:true,

    // the widget's constructor
    _create: function() {
        // prevent double click to select text
        this.element.disableSelection();
    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {

            var that = this;
            
            if(this._need_load_content){        
                this.element.load(top.HAPI4.basePathV4+'hclient/widgets/entity/searchSysUsers.html?t'+Math.random(), 
                function(response, status, xhr){
                    that._need_load_content = false;
                    if ( status == "error" ) {
                        top.HEURIST4.msg.showMsgErr(response);
                    }else{
                        that._init();
                    }
                });
                return;
            }
            
            //init buttons
            this.btn_search_start = this.element.find('#btn_search_start')
                //.css({'width':'6em'})
                .button({label: top.HR("Start search"), text:false, icons: {
                    secondary: "ui-icon-search"
                }});
                        
            this.input_search = this.element.find('#input_search')
                .on('keypress',
                function(e){
                    var code = (e.keyCode ? e.keyCode : e.which);
                        if (code == 13) {
                            top.HEURIST4.util.stopEvent(e);
                            e.preventDefault();
                            that.startSearch();
                        }
                });
            
            
            this.selectRole   = this.element.find('#sel_role');
            this.selectGroup = this.element.find('#sel_group');
            this.selectGroup.empty();
            
            top.HAPI4.EntityMgr.doRequest({a:'search','details':'name', //'DBGSESSID':'423997564615200001;d=1,p=0,c=0',
                        'entity':'sysUGrps','ugr_Type':'workgroup','ugr_ID':that.options.filter_groups},
                    function(response){
                        if(response.status == top.HAPI4.ResponseStatus.OK){
                            var groups = new hRecordSet(response.data).makeKeyValueArray('ugr_Name');
                            
                            if(top.HEURIST4.util.isempty(that.options.filter_groups)){
                                groups.unshift({key:'',title:top.HR('Any Groups')});
                            }
                            
                            top.HEURIST4.ui.createUserGroupsSelect(that.selectGroup.get(0), 
                                [], 
                                groups,
                                function(){ //on load completed
                                    //force search 
                                    //predefined search values
                                    if(!top.HEURIST4.util.isempty(that.options.filter_title)) that.input_search.val(that.options.filter_title);
                                    if(!top.HEURIST4.util.isempty(that.options.filter_group_selected)) that.selectGroup.val(that.options.filter_group_selected);
            
                                    that.selectGroup.change(); //force search
                                });
                            
                        }else{
                            top.HEURIST4.msg.showMsgErr(response);
                        }
                    });
                      
                      
            this._on( this.element.find('select'), {
                change: function(event){
                    if(this.selectGroup.val()>0){
                        that.selectRole.parent().show();
                        //that.element.find('span[for="sel_role"]').show();
                        //that.element.find('#sel_role_lbl').show();
                    }else{
                        that.selectRole.parent().hide();
                        //that.element.find('#sel_role_lbl').hide();
                    }
                    this.startSearch();
                }
            });
            this._on( this.element.find('.ent_search_cb > input'), {  //input[type=checkbox]
                change: this.startSearch });
            this._on( this.btn_search_start, {
                click: this.startSearch });
                
            // help buttons
            top.HEURIST4.ui.initHintButton(this.element.find('#btn_help_hints'));
            top.HEURIST4.ui.initHelper(this.element.find('#btn_help_content'),'System Users',
                top.HAPI4.basePathV4+'context_help/sysUGrps.html #content');
            
            
            var right_padding = top.HEURIST4.util.getScrollBarWidth()+4;
            this.element.find('#div-table-right-padding').css('min-width',right_padding);
        
    },  
    
    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    _setOptions: function( ) {
        this._superApply( arguments );
    },

    /* 
    * private function 
    * show/hide buttons depends on current login status
    */
    _refresh: function(){
        /*
        if(this.options.add_new_record){
            this.btn_add_record.show();
            this.element.find('#lbl_add_record').show();
        }else{
            this.btn_add_record.hide();
            this.element.find('#lbl_add_record').hide();
        }
        */

    },
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements
        //this.select_rectype.remove();
    },
    
    //
    // public methods
    //
    startSearch: function(){
        
            var request = {}
        
            if(this.selectGroup.val()!=''){
                request['ugl_GroupID'] = this.selectGroup.val();
                if(this.selectRole.val()!=''){
                    request['ugl_Role'] = this.selectRole.val();
                }   
            }   
            if(this.input_search.val()!=''){
                request['ugr_Name'] = this.input_search.val();
            }


            if(this.element.find('#cb_selected').is(':checked')){
                request['ugr_ID'] = top.HAPI4.get_prefs('recent_Users');
            }
            if(this.element.find('#cb_modified').is(':checked')){
                var d = new Date(); 
                //d = d.setDate(d.getDate()-7);
                d.setTime(d.getTime()-7*24*60*60*1000);
                request['ugr_Modified'] = '>'+d.toISOString();
            }
            if(this.element.find('#cb_inactive').is(':checked')){
                request['ugr_Enabled'] = 'n';
            }
            
            //noothing defined
            if(false && $.isEmptyObject(request)){
                this._trigger( "onresult", null, {recordset:new hRecordSet()} );
            }else{
                this._trigger( "onstart" );
        
                request['a']          = 'search'; //action
                request['entity']     = 'sysUGrps';
                request['details']    = 'id';
                request['request_id'] = Math.round(new Date().getTime() + (Math.random() * 100));
                request['ugr_Type']    = 'user';
                
                //request['DBGSESSID'] = '423997564615200001;d=1,p=0,c=0';

                var that = this;                                                
                //that.loadanimation(true);
                top.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == top.HAPI4.ResponseStatus.OK){
                            that._trigger( "onresult", null, 
                                {recordset:new hRecordSet(response.data), request:request} );
                        }else{
                            top.HEURIST4.msg.showMsgErr(response);
                        }
                    });

            }
    },
    

});
