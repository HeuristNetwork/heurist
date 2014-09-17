/**
* Pagination control for query results listing 
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
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


$.widget( "heurist.pagination", {

    // default options
    options: {
        isapplication: true,  // send and recieve the global events
        showcounter: false,

        //move to vars
    },

    current_page: 0,
    max_page: 0,
    count_total: null,
    query_request: null,  //current search request
    limit: 200,

    // the constructor
    _create: function() {

        var that = this;

        this.element
        // prevent double click to select text
        .disableSelection();

        this.span_info = $( "<div>")
        .css({'display':'inline-block','min-width':'10em','padding':'0 2em 0 2em'})
        .appendTo( this.element );

        this.span_buttons = $( "<span>").appendTo( this.element );

        this.btn_goto_prev = $( "<button>", {
            title: top.HR("previous"), text: 'A'
        })
        .appendTo( this.span_buttons )
        .button({icons: {
            primary: "ui-icon-seek-prev"
            },text:false})
        .on("click", function(){ that._doSearch3(that.current_page-1); } );
        this.span_pages = $( "<span>" ).appendTo( this.span_buttons );

        this.btn_goto_next = $( "<button>", {
            title: top.HR("next"), text: 'A'
        })
        .appendTo( this.span_buttons )
        .button({icons: {
            primary: "ui-icon-seek-next"
            },text:false})
        .on("click", function(){ that._doSearch3(that.current_page+1); } );

        this.limit = top.HAPI4.get_prefs('search_limit');               

        this.btn_search_limit = $( "<button>", {
            text: this.limit,
            title: top.HR('records per request')
        })
        //.css('width', '4em')
        .appendTo( this.element )
        .button({icons: { secondary: "ui-icon-triangle-1-s" }});

        var smenu = '<li id="search-limit-10"><a href="#">10</a></li>'+
        '<li id="search-limit-50"><a href="#">50</a></li>'+
        '<li id="search-limit-100"><a href="#">100</a></li>'+
        '<li id="search-limit-200"><a href="#">200</a></li>';                

        this.menu_search_limit = $('<ul>'+smenu+'</ul>')   //<a href="#">
        .zIndex(9999)
        .css('position','absolute')
        .appendTo( this.document.find('body') )
        .menu({
            select: function( event, ui ) {
                var newlimit = Number(ui.item.attr('id').substring(13));
                if(newlimit!=that.limit){

                    top.HAPI4.currentUser['ugr_Preferences']['search_limit'] = newlimit;
                    if(top.HAPI4.is_logged()){
                        //save preference in session
                        top.HAPI4.SystemMgr.save_prefs({'search_limit': newlimit},
                            function(response){
                                if(response.status != top.HAPI4.ResponseStatus.OK){
                                    top.HEURIST4.util.showMsgErr(response.message);
                                }
                            }
                        );
                    }

                    that.limit = newlimit;
                    that._doSearch3(0);
                }
        }})
        .hide();

        this._on( this.btn_search_limit, {
            click: function() {
                $('.ui-menu').not('.horizontalmenu').hide(); //hide other
                var menu = $( this.menu_search_limit )
                .show()
                .position({my: "right top", at: "right bottom", of: this.btn_search_limit });
                $( document ).one( "click", function() { menu.hide(); });
                return false;
            }
        });


        //-----------------------     listener of global events
        var sevents = top.HAPI4.Event.LOGIN+' '+top.HAPI4.Event.ON_REC_SEARCHRESULT + ' ' + top.HAPI4.Event.ON_REC_SEARCHSTART;

        $(this.document).on(sevents, function(e, data) {

            if(e.type == top.HAPI4.Event.ON_REC_SEARCHRESULT){

                if(data){
                    that.count_total = data.count_total();
                }else{
                    that.count_total = 0;
                }
                that._refresh();
            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCHSTART){

                //hide all on start search   
                that.count_total = 0; //hRecordSet
                that.query_request = data;
                if(data.orig != "paginator"){
                    that.current_page = 0; //reset
                }

            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCHRESULT){
                that._refresh();    
            }

        });    

        this._refresh();

    }, //end _create

    /* private function */
    _refresh: function(){

        this.limit = top.HAPI4.get_prefs('search_limit');
        this.btn_search_limit.button( "option", "label", this.limit);

        if(this.count_total>0){

            var limit = this.limit;

            this.max_page = Math.ceil(this.count_total / limit); 
            if(this.current_page>this.max_page-1){
                this.current_page = 0;
            }

        }else{ //hide all 
            this.max_page = 0;
        }      

        var pageCount = this.max_page;
        var currentPage = this.current_page;
        var start = 0;
        var finish = 0;

        this._renderRecNumbers();

        if (pageCount < 2) {
            this.span_buttons.hide();
            return;
        }else{
            this.span_buttons.css('display', 'inline-block');
        }

        // KJ's patented heuristics for awesome useful page numbers
        if (pageCount > 9) {
            if (currentPage < 5) { start = 1; finish = 8; }
            else if (currentPage < pageCount-4) { start = currentPage - 2; finish = currentPage + 4; }
                else { start = pageCount - 7; finish = pageCount; }
        } else {
            start = 1; finish = pageCount;
        }


        /*if (currentPage == 0) {
        this.btn_goto_prev.hide();
        }else{
        this.btn_goto_prev.show();
        }
        if (currentPage == pageCount-1) {
        this.btn_goto_next.hide();
        }else{
        this.btn_goto_next.show();
        }*/


        this.span_pages.empty();
        var that = this;

        if (start != 1) {    //force first page
            $( "<button>", { text: "1"}).button()
            .appendTo( this.span_pages ).on("click", function(){ that._doSearch3(0); } );
            if(start!=2){
                $( "<span>" ).html("..").appendTo( this.span_pages );
            }
        }
        for (i=start; i <= finish; ++i) {
            var $btn = $( "<button>", { text: ''+i, id: 'page'+(i-1) }).button().appendTo( this.span_pages )
            .on("click", function(event){ 
                var page = Number(event.target.id.substring(4));    
                that._doSearch3(page); 
            } );
            if(i-1==currentPage){        
                $btn.button('disable').addClass('ui-state-active').removeClass('ui-state-disabled');
            }
        }
        if (finish != pageCount) { //force last page
            if(finish!= pageCount-1){
                $( "<span>" ).html("..").appendTo( this.span_pages );
            }
            $( "<button>", { text: ''+pageCount }).button().appendTo( this.span_pages ).on("click", function(){ that._doSearch3(pageCount-1); } );
        }

    },

    // show current records range and total count
    //
    _renderRecNumbers: function(){
        if(this.options.showcounter){
            var limit = this.limit;
            if(this.count_total>0){
                var rec_no_first = this.current_page*limit+1;
                var rec_no_last =  rec_no_first+limit-1;
                if (rec_no_last>this.count_total) { rec_no_last = this.count_total; }
                this.span_info.html( rec_no_first+"-"+rec_no_last+"/"+this.count_total);
            }else{
                this.span_info.html('');
            }
        }else{
            this.span_info.css('display','none');
        }
    },

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        // remove generated elements
        this.span_pages.remove();
        this.btn_goto_prev.remove();
        this.btn_goto_next.remove();
        this.span_info.remove();
        this.span_buttons.remove();
    },

    _doSearch3: function(page){

        if(top.HEURIST4.util.isNumber(page)){
            this.current_page = page;
        }
        if(this.current_page>this.max_page-1){
            this.current_page = this.max_page-1;
        }else if(this.current_page<0){
            this.current_page = 0;
        }


        if ( this.query_request ) {

            var that = this;

            //  l or limit  - limit of records
            //  o or offset

            var limit = this.limit;
            this.query_request.l = limit;
            this.query_request.o = this.current_page * limit;
            this.query_request.source = this.element.attr('id'); // "paginator";

            top.HAPI4.RecordMgr.search(this.query_request, $(this.document));

        }

    }  

});
