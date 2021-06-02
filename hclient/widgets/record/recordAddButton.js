/**
/**
* recordAddButton.js - button to add new record
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

$.widget( "heurist.recordAddButton",{

    // default options
    options: {
        add_record_label: null,
        
        RecTypeID: 0,
        OwnerUGrpID: 0,
        NonOwnerVisibility: null,
        RecTags: null,
        NonOwnerVisibilityGroups: null
    },
    
    _init:function(){
        
        var ele = $('<button>').appendTo(this.element);
        
        var c2 = this.element.parent().attr('style');
        ele.attr('style',c2);
        this.element.parent().css({border:'none',background:'none'});
        
        if(!this.options.add_record_label){

            if(this.options.RecTypeID>0 && $Db.rty(this.options.RecTypeID,'rty_Name')){
                this.options.add_record_label = 'Add '+window.hWin.HEURIST4.util.htmlEscape($Db.rty(this.options.RecTypeID,'rty_Name'));
            }else{
                this.options.add_record_label = 'Add Record';
            }
        }       
        ele.button({label:this.options.add_record_label});
        
        var that = this;
             
        this._on(ele, {click: function(e){
                window.hWin.HEURIST4.ui.openRecordEdit(-1, null,{new_record_params:this.options,
                
                    selectOnSave:true,
                    onselect:function(event, data){
                        if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                            
                            $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CUSTOM_EVENT, 
                                {restartSearch:true,
                                    source:that.element.attr('id'), search_realm:that.options.search_realm} );
                            
                        }                
                    }
                });
            }});
    }    
  
});
