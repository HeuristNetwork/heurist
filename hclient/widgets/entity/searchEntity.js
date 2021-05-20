/**
* Search header for Entity Manager - BASE widget
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


$.widget( "heurist.searchEntity", {

    // default options
    options: {
        select_mode: 'manager', //'select_single','select_multi','manager'
        
        //initial filter by title and subset of groups to search
        filter_title: null,
        filter_group_selected:null,
        filter_groups: null,
        
        //request for initial filter
        initial_filter: null,
        search_form_visible: true,

        use_cache: false,
        
        /* callbacks - events
        onstart:null,
        onresult:null,
        onfilter:null,*/
        
        entity:{}
    },
    
    _need_load_content:true, // do not load search form html content

    // the widget's constructor
    _create: function() {
        // prevent double click to select text
        this.element.disableSelection();
    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {

            var that = this;
            
            if(this._need_load_content && this.options.entity.searchFormContent){        
                this.element.load(window.hWin.HAPI4.baseURL+'hclient/widgets/entity/'+this.options.entity.searchFormContent+'?t'+window.hWin.HEURIST4.util.random(), 
                function(response, status, xhr){
                    that._need_load_content = false;
                    if ( status == "error" ) {
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }else{
                        that._initControls();
                    }
                });
                return;
            }else{
                //template for search not defined 
                // define btn_search_start and input_search at least in manageEntity
                that._initControls();
            }
            
    },
    
    //
    //
    //
    _initControls: function() {
            
            //init buttons
            this.btn_search_start = this.element.find('#btn_search_start')
                //.css({'width':'6em'})
                .button({label: window.hWin.HR("Start search"), showLabel:false, 
                        icon:"ui-icon-search", iconPosition:'end'});
                 
                    
            //this is default search field - define it in your instance of html            
            this.input_search = this.element.find('#input_search');
            if(this.input_search.length==0) this.input_search = this.element.find('.input_search');
            if(!window.hWin.HEURIST4.util.isempty(this.options.filter_title)) {
                this.input_search.val(this.options.filter_title);    
            }
            
            this._on( this.input_search, { keypress: this.startSearchOnEnterPress });
            this._on( this.btn_search_start, {
                click: this.startSearch });
                
            this._on( this.element.find('.ent_search_cb input'), {  //input[type=radio]
                change: this.startSearch });
                
            // summary button - to show various counts for entity 
            // number of group members, records by rectypes, tags usage
            this.btn_summary = this.element.find('#btn_summary')
                .button({label: window.hWin.HR("Show/refresh counts"), text:false, icons: {
                    secondary: "ui-icon-retweet"
                }});
            if(this.btn_summary.length>0){
                this._on( this.btn_summary, { click: this.startSearch });
            }
                
            var right_padding = window.hWin.HEURIST4.util.getScrollBarWidth()+4;
            this.element.find('#div-table-right-padding').css('min-width',right_padding);
        
        
            //EXTEND this.startSearch();
            window.hWin.HEURIST4.ui.disableAutoFill( this.element.find( 'input' ) );
            
    },  
    
    //
    //
    //
    startSearchOnEnterPress: function(e){
        
        var code = (e.keyCode ? e.keyCode : e.which);
        if (code == 13) {
            window.hWin.HEURIST4.util.stopEvent(e);
            e.preventDefault();
            this.startSearch();
        }

    },
    
    //
    // use_cache = true
    // load entire entity data and work with cached on client side recordset
    // applicabele for counts < ~1500
    //
    startSearchInitial: function(){
        
            this._trigger( "onstart" );
            
            var that = this;
            window.hWin.HAPI4.EntityMgr.getEntityData(this.options.entity.entityName, false,
                function(response){
                        that._trigger( "onresult", null, {recordset:response} );
            
                        that.startSearch(); //apply filter
                        
                });
    },

    //
    // public methods
    //
    startSearch: function(){
        //TO EXTEND        
    },
    

});
