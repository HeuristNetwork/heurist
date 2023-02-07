/**
* Search header for usrReminders manager
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
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

$.widget( "heurist.searchUsrReminders", $.heurist.searchEntity, {

    //
    _initControls: function() {
        this._super();
        
        var that = this;
        
        this.btn_search_start.css('float','right');   
        
        this.input_search_group = this.element.find('#input_search_group');
        this.input_sort_rectitle = this.element.find('#input_sort_rectitle');
        this.input_sort_sdate = this.element.find('#input_sort_sdate');
        this.input_sort_recent =  this.element.find('#input_sort_recent');
        this._on(this.input_sort_rectitle,  { change:this.startSearch });
        this._on(this.input_sort_sdate,  { change:this.startSearch });
        this._on(this.input_sort_recent,  { change:this.startSearch });
        this._on(this.input_search_group,  { change:this.startSearch });
        //this._on( this.input_search, { keyup: this.startSearch });
        
        this.startSearch();            
    },  
    
    //
    // public methods
    //
    startSearch: function(){
        
            this._super();
            
            var request = {}
        
            var val = this.input_search_group.val();
            if(val!='any'){
                if(val=='Workgroup') request['rem_ToWorkgroupID'] = '-NULL';
                else if(val=='User') request['rem_ToUserID'] = '-NULL';
                else if(val=='Email') request['rem_ToEmail'] = '-NULL';
            }
            
            request['rem_Message'] = this.input_search.val();    
            
            if(this.input_sort_rectitle.is(':checked')){
                request['sort:rem_RecTitle'] = '1';
            }else
            if(this.input_sort_recent.is(':checked')){
                request['sort:rem_Modified'] = '-1' 
            }else
            if(this.input_sort_sdate.is(':checked')){
                request['sort:rem_StartDate'] = '-1' 
            }

            if($.isEmptyObject(request)){
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
                            that._trigger( "onresult", null, 
                                {recordset:new hRecordSet(response.data), request:request} );
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
                    
            }  
                     
    },

});
