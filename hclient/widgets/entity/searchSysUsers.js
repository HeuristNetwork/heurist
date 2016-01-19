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
        
        add_new_record: true,
        groups_set: null,  //array of record types to limit search within
        
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
                this.element.load(top.HAPI4.basePathV4+'hclient/widgets/entity/searchSysUsers.html', 
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
                        
            this.btn_add_record = this.element.find('#btn_add_record')
                .css({'min-width':'11.9em'})
                .button({label: top.HR("Create New User"), icons: {
                    primary: "ui-icon-plus"
                }})
                .click(function(e) {
                        //top.HAPI4.SearchMgr.doStop();
                        alert('Add record');
                    });
    
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
            
            top.HAPI4.EntityMgr.doRequest({a:'search','details':'name','entity':'sysUGrps','ugr_Type':'workgroup'},
                    function(response){
                        if(response.status == top.HAPI4.ResponseStatus.OK){
                            var groups = new hRecordSet(response.data).makeKeyValueArray('ugr_Name');
                            
                            if(that.options.groups_set==null){
                                groups.unshift({key:'',title:top.HR('Any Groups')});
                            }
                            
                            top.HEURIST4.ui.createUserGroupsSelect(that.selectGroup.get(0), 
                                null, 
                                groups,
                                function(){ //on load completed
                                    //force search if group set is defined
                                    that.selectGroup.change();
                                });
                            
                        }else{
                            top.HEURIST4.msg.showMsgErr(response);
                        }
                    });
                      
                      
            var ishelp_on = top.HAPI4.get_prefs('help_on')==1;
            this.element.find('.heurist-helper1').css('display',ishelp_on?'block':'none');
    
            
            this._on( this.element.find('select'), {
                change: function(event){
                    if(this.selectGroup.val()>0){
                        that.selectRole.parent().show();
                    }else{
                        that.selectRole.parent().hide();
                    }
                    this.startSearch();
                }
            });
            this._on( this.element.find('input[type=radio]'), {
                change: function(event){
                    this.startSearch();
                }});
            this._on( this.btn_search_start, {
                click: function(event){
                    this.startSearch();
                }});
            
        
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
        
        if(this.options.add_new_record){
            this.btn_add_record.show();
            this.element.find('#lbl_add_record').show();
        }else{
            this.btn_add_record.hide();
            this.element.find('#lbl_add_record').hide();
        }

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
            
            if(this.element.find('#rb_inactive').is(':checked')){
                request['ugr_Enabled'] = 'n';
            }
            
            //noothing defined
            if($.isEmptyObject(request)){
                this._trigger( "onresult", null, {recordset:new hRecordSet()} );
            }else{
                this._trigger( "onstart" );
        
                request['a']          = 'search'; //action
                request['entity']     = 'sysUGrps';
                request['details']    = 'id';
                request['request_id'] = Math.round(new Date().getTime() + (Math.random() * 100));
                request['ugr_Type']    = 'user';

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
    }

});
