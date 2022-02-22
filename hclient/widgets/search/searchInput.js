/**
* Simplified Search input form with possible preliminary filter
* This widget is not used in main interface. It is listed aming available widgets in CMS
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


$.widget( "heurist.searchInput", {

    // default options
    options: {
        _is_publication: false,

        sup_filter: null, //additional filter
        
        search_domain: 'a', //current search domain all|bookmark|recently added|recently selected  or a|b|r|s
        search_domain_set: null, // comma separated list of allowed domains  a,b,c,r,s

        search_button_label: '',
        search_input_label: '',
        show_search_assistant: true,
        
        button_class: 'ui-heurist-btn-header1',
        
        preliminary_filter: null,
        
        // callbacks
        onsearch: null,  //on start search
        onresult: null,   //on search result
        
        search_page: null, //target page (for CMS)
        search_realm:  null  //accepts search/selection events from elements of the same realm only
    },

    query_request:null,

    _is_publication:false, //this is CMS publication - take css from parent
    
    // the constructor
    _create: function() {
        
        var that = this;
        
        if(this.element.parent().attr('data-heurist-app-id') || this.element.hasClass('cms-element')){
            
            this.options.button_class = '';
            this._is_publication = true;
            //this is CMS publication - take bg from parent
            this.element.addClass('ui-widget-content').css({'background':'none','border':'none'});
        }else if(!this.options.is_h6style){
            this.element.css({height:'100%','font-size':'0.8em'});
            this.element.addClass('ui-widget-content');
        }else {
            this.options.button_class = '';
        }
        
        //------------------------------------------- filter inputs                        

        //1> Search functions container
        this.div_search   = $('<div>')
                .css({ 'width':'100%', display: 'flex','justify-content': 'center', flex: '1 1 auto' })
                .appendTo( this.element ); 
        
        //header-label
        this.div_search_header = $('<div>')
        .css({'width':'auto', flex: '1 1 50px', 'text-align':'right'})
        .appendTo( this.div_search );
        
        // Search field
        this.div_search_input = $('<div>')
        .css({'width':'auto', flex: '2 1 200px', 'text-align':'right'}) 
        .appendTo( this.div_search );

        this.input_search = $( "<textarea>" )
        .css({//'margin-right':'0.2em', 
            'height':'27px', 'width':'99%' , 
            'max-height':'70px', 
			'resize':'none', 
            'min-height':'27px', 'line-height': '14px', 
            'min-width':'80px' }) 
        .attr('placeholder','define filter')   
        .addClass("text ui-widget-content ui-corner-all")
        .appendTo(  this.div_search_input );
        
        //
        // quick filter builder buttons
        //
        this.div_buttons = $('<div>')
            .css({'text-align': 'center', flex: '0 1 30px', 'vertical-align': 'baseline'})
            .appendTo( this.div_search );
        
        var linkGear = $('<a>',{href:'#', 
            title:window.hWin.HR('Build a filter expression using a form-driven approach')})
            .css({'display':'inline-block','opacity':'0.5','margin-top': '0.6em', width:'20px'})
            .addClass('ui-icon ui-icon-magnify-explore') //was ui-icon-gear was ui-icon-filter-form
            .appendTo(this.div_buttons);
        this._on( linkGear, {  click: this.showSearchAssistant });
        
        //
        // search/filter buttons - may be Search or Bookmarks according to settings and whether logged in
        //
        this.div_search_as_user = $('<div>') //.css({'min-width':'18em','padding-right': '10px'})
        .css({flex: '0 1 100px','text-align':'right'}) 
        .appendTo( this.div_search );

        this.btn_start_search = $( "<button>", {
            label: window.hWin.HR(this.options.search_button_label), 
            title: "Apply the filter/search in the search field and display results in the central panel below"
        })
        .css({'min-height':'30px','min-width':'90px'})
        .appendTo( this.div_search_as_user )
        .addClass(this.options.button_class+' ui-button-action')
        .button({showLabel:true, icon:this._is_publication?'ui-icon-search':'ui-icon-filter'});


        // bind click events
        this._on( this.btn_start_search, {
            click:  function(){
                //that.option("search_domain", "a");
                that._doSearch(true);}
        });
   
        this._on( this.input_search, {
            keypress: function(e){
                var code = (e.keyCode ? e.keyCode : e.which);
                if (code == 13) {
                    window.hWin.HEURIST4.util.stopEvent(e);
                    e.preventDefault();
                    that._doSearch(true);
                }
            },
            keydown: function(e){
                var code = (e.keyCode ? e.keyCode : e.which);
                if (code == 65 && e.ctrlKey) {
                    e.target.select();
                }
            }
        });
        
        
        $(this.document).on(
            window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH, 
            function(e, data) { that._onSearchGlobalListener(e, data) } );
        

        this._refresh();

    }, //end _create

   /* private function */
   _refresh: function(){
       
       this.btn_start_search.button('option','label', window.hWin.HR(this.options.search_button_label)); 
       
       if(this.options.search_input_label){
            this.div_search_header.show();
            this.div_search_header.text( this.options.search_input_label ).css({'padding-right':'4px'});     
       }else{
            this.div_search_header.hide();
       }
        
       if(this.options.show_search_assistant) {
            this.div_buttons.show();
       }else{
            this.div_buttons.hide();
       }
       
   },
  
   _setFocus: function(){
      
        if(this.input_search.is(':visible')) {
                try{
                    this.input_search.focus();
                }catch(e){}
        }

   },


   //
   // search from input - query is defined manually
   //
   _doSearch: function(){

        var qsearch = this.input_search.val();
        
        qsearch = qsearch.replace(/,\s*$/, "");

        if ( qsearch ) {

            // q - query string
            // w  all|bookmark
            // stype  key|all   - key-search tags, all-title and pointer record title, by default rec_Title

            var that = this;

            if(this.options.sup_filter){
                qsearch = window.hWin.HEURIST4.util.mergeHeuristQuery(this.options.sup_filter, qsearch);    
            }
            
            window.hWin.HAPI4.SystemMgr.user_log('search_Record_direct');
            
            var request = {}; //window.hWin.HEURIST4.util.parseHeuristQuery(qsearch);

            request.q = qsearch;
            request.w  = this.options.search_domain;
            request.detail = 'ids'; //'detail';
            request.source = this.element.attr('id');
            request.search_realm = this.options.search_realm;
            request.search_page = this.options.search_page;
            
            this.query_request = request;

            window.hWin.HAPI4.RecordSearch.doSearch( this, request );

        }

    }



    /**
    *  public method
    *
    * @returns {Boolean}
    */
    , showSearchAssistant: function() {
        
        if(event && (event.detail === 2)){
            //ignore dbl click
            return;
        }
        
        var that = this;

        
        if(this.options.is_h6style){
            var widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('mainMenu6');
            if(widget){
                    var pos = this.element.offset();
                    widget.mainMenu6('show_ExploreMenu', null, 'searchBuilder', {top:pos.top+10, left:pos.left});
            }
        }else{
            
            if(!$.isFunction($('body')['showSearchBuilder'])){ //OK! widget script js has been loaded
            
                var path = window.hWin.HAPI4.baseURL + 'hclient/widgets/search/';
                var scripts = [ path+'searchBuilder.js', 
                                path+'searchBuilderItem.js',
                                path+'searchBuilderSort.js'];
                $.getMultiScripts(scripts)
                .done(function() {
                    showSearchBuilder({ search_realm:that.options.search_realm, 
                                        search_page:that.options.search_page});
                }).fail(function(error) {
                    //console.log(error);                
                    window.hWin.HEURIST4.msg.showMsgWorkInProgress();
                }).always(function() {
                    // always called, both on success and error
                });
            
                return;            
            }

            
            showSearchBuilder({search_realm:that.options.search_realm,
                               search_page:that.options.search_page});
        }
        
    }
 

    // events bound via _on are removed automatically
    // revert other modifications here
    ,_destroy: function() {

       
        this.btn_start_search.remove();  // bookamrks search on
        this.input_search.remove();
        
        this.div_search_as_user.remove();
        this.div_search.remove();
        
        
        $(this.document).off(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
          + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH);
        
    },
    
    
    //
    //
    //
    _isSameRealm: function(data){
        return (!this.options.search_realm && (!data || window.hWin.HEURIST4.util.isempty(data.search_realm)))
        ||
        (this.options.search_realm && (data && this.options.search_realm==data.search_realm));
    },

    
    _onSearchGlobalListener: function(e, data){

       var that = this;

       if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART)
       {

           //accept events from the same realm only
           if(!that._isSameRealm(data)) return;

           //data is search query request
           if(data.reset){
               that.input_search.val('');
               that.input_search.change();
           }else            
               //topids not defined - this is not rules request
               if(window.hWin.HEURIST4.util.isempty(data.topids) && data.apply_rules!==true){

                   //request is from some other widget (outside)
                   if(data.source!=that.element.attr('id')){
                       var qs;
                       if($.isArray(data.q)){
                           qs = JSON.stringify(data.q);
                       }else{
                           qs = data.q;
                       }

                       if(!window.hWin.HEURIST4.util.isempty(qs)){

                           if(qs.length<10000){
                               that.input_search.val(qs);
                               //that.options.search_domain = data.w;
                               that.query_request = data;
                               that._refresh();
                           }
                       }
                   }

                   that.input_search.change();
               }
       }else 
           if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){ //search completed

               //accept events from the same realm only
               if(!that._isSameRealm(data)) return;

               window.hWin.HEURIST4.util.setDisabled(this.input_search, false);

               this._setFocus();
           }



    }


});
