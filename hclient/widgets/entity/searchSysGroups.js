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


$.widget( "heurist.searchSysGroups", {

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
                this.element.load(top.HAPI4.basePathV4+'hclient/widgets/entity/searchSysGroups.html?t'+Math.random(), 
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

            this._on( this.element.find('.ent_search_cb > input'), {  //input[type=radio]
                change: this.startSearch });
            this._on( this.btn_search_start, {
                click: this.startSearch });
                
            // help buttons
            top.HEURIST4.ui.initHintButton(this.element.find('#btn_help_hints'));
            top.HEURIST4.ui.initHelper(this.element.find('#btn_help_content'),'System Users',
                top.HAPI4.basePathV4+'context_help/sysUGrps.html #content');
            
            var right_padding = top.HEURIST4.util.getScrollBarWidth()+4;
            this.element.find('#div-table-right-padding').css('min-width',right_padding);
        
        
            this.startSearch();
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
        
            if(this.input_search.val()!=''){
                request['ugr_Name'] = this.input_search.val();
            }

            // actually we may take list of groups from currentUser['ugr_Groups']
            if(!this.element.find('#rb_anygroup').is(':checked')){
            
                request['ugl_UserID'] = top.HAPI4.currentUser['ugr_ID'];
                
                if(this.element.find('#rb_admin').is(':checked')){
                    request['ugl_Role'] = 'admin';
                }
                if(this.element.find('#rb_member').is(':checked')){
                    request['ugl_Role'] = 'member';
                }
            }
            
            //nothing defined
            if(false && $.isEmptyObject(request)){
                this._trigger( "onresult", null, {recordset:new hRecordSet()} );
            }else{
                this._trigger( "onstart" );
        
                request['a']          = 'search'; //action
                request['entity']     = 'sysUGrps';
                request['details']    = 'id';
                request['request_id'] = Math.round(new Date().getTime() + (Math.random() * 100));
                request['ugr_Type']    = 'workgroup';
                
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
