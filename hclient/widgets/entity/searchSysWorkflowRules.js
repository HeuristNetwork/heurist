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

    //
    _initControls: function() {
        this._super();
        
        var that = this;
        
        this.element.find('#inner_title').text( this.options.entity.entityTitlePlural );
        
        //this.btn_search_start.css('float','right');   
        
        this.btn_add_record = this.element.find('#btn_add_record');
        this.btn_add_record
                    .button({label: window.hWin.HR('Add set of Rules'), showLabel:true, 
                            icon:"ui-icon-plus"})
                    .addClass('ui-button-action')
                    .css({padding:'2px'})
                    .show();
                    
        this._on( this.btn_add_record, {
                        click: function(){
                                this._trigger( "onadd" );    
                        }} );

        this._on( this.element.find('#edit_swf_vocab'), {
                        click: function(){
                            this._trigger('onvocabedit');
                        }
        });
        
        this.input_search_rectype = this.element.find('#input_search_rectype');
        var rty_selector = window.hWin.HEURIST4.ui.createRectypeSelectNew(this.input_search_rectype.get(0), 
                {rectypeList:  null, 
                 showAllRectypes: true}); //topOptions:'select record type'
        this._on(rty_selector,  { change:this.startSearch });

        if(!window.hWin.HEURIST4.allUsersCache){

            //get all users
            var request = {a:'search', entity:'sysUsers', details:'fullname'};
            var that = this;
        
            window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    var recordset = new hRecordSet(response.data);
                    window.hWin.HEURIST4.allUsersCache = {};                    
                    recordset.each2(function(id,rec){
                        window.hWin.HEURIST4.allUsersCache[id] = rec['ugr_FullName'];
                    });
                    
                    that.startSearch();
                    
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
        
        }else{
            this.startSearch();     
        }
    },  
    
    //
    // public methods
    //
    startSearch: function(){
        
            this._super();
            
            var request = {};
        
            if(this.input_search_rectype.val() && this.input_search_rectype.val()!='any'){
                request['swf_RecTypeID'] = this.input_search_rectype.val();        
                request['sort:swf_Order'] = 1;
            }else{
                
                var recset = $Db.swf();
                var id;
                if(recset.length()>0){
                    id = recset.fld(recset.getFirstRecord(),'swf_RecTypeID');
                }else{
                    //get first
                    id = this.input_search_rectype.find('option[value!=0]:first').attr('value');
                }

                this._setOption('rty_ID', id);
                return;
            }
            
            this._trigger( "onfilter", null, request); 
            
    },
    
    //
    //
    //    
    getSelectedRty: function(){
        return this.input_search_rectype.val();
    },

    //
    //
    //    
    setButton: function(is_empty){

        if(is_empty){
            this.btn_add_record.button({label:window.hWin.HR('Add set of Rules')});    
        }else{
            this.btn_add_record.button({label:window.hWin.HR('Add Stage')});
        }
        
    },

    //
    //
    //
    _setOption: function( key, value ) {
        this._super( key, value );
        if(key == 'rty_ID'){
            this.input_search_rectype.val(value);
            this.input_search_rectype.hSelect('refresh');
            this.startSearch();
        }
    },
});
