/**
* Advanced query builder
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @note        Completely revised for Heurist version 4
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


$.widget( "heurist.search_advanced", $.heurist.recordAction, {

    // default options
    options: {
        is_h6style: true,
        is_json_query: false,    
        
        isdialog: false, 
        supress_dialog_title: true,
        mouseover: null, //callback to prevent close h6 menu 
        menu_locked: null, //callback to prevent close h6 menu on mouse exit
        
        button_class: 'ui-button-action',
        
        currentRecordset: {},  //stub
        search_realm: null
    },
    
    current_query:null,
    current_query_json:null,
    
    _init: function(){
        
        this.element.css('overflow','hidden');
        
        this.options.htmlContent = window.hWin.HAPI4.baseURL+'hclient/widgets/search/search_advanced.html'
                            +'?t='+window.hWin.HEURIST4.util.random();
        this._super();        
    },

    //  
    // invoked from _init after loading of html content
    //
    _initControls:function(){
        
        var that = this;
        
        if(!this.options.is_h6style){
             this.element.find('.ui-heurist-header').hide();
             this.element.find('.ent_wrapper').css('top',0);
        }
        
      
        this.popupDialog();
        
        
        window.hWin.HAPI4.addEventListener(this, window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            function(data) { 
                that._recreateSelectors();
            });
            

        this._recreateSelectors();       
        
        return true;
    },
    
    //
    //
    //
    _recreateSelectors: function(){
        
        var that = this;
        
    },
    
    //
    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {

        //$(window.hWin.document).off(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);
        window.hWin.HAPI4.removeEventListener(this, window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);        

        
        //this.div_entity_btns.remove();
    },

    //
    //
    //
    doAction: function(){
/*        
        this.calcShowSimpleSearch();
        var request = {};
            request.q = this.options.is_json_query ?this.current_query_json :this.current_query;
            request.w  = 'a';
            request.detail = 'ids';
            request.source = this.element.attr('id');
            request.search_realm = this.options.search_realm;
            
            window.hWin.HAPI4.SearchMgr.doSearch( this, request );
*/        
        this.closeDialog();
    }

    

});
