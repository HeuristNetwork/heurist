/**
*  Pagination control
*/
$.widget( "heurist.pagination", {

  // default options
  options: {
    isapplication:true,  // send and recieve the global events
    
    current_page: 0,
    max_page: 0,
    count_total: null,
    query_request: null,
    limit: 200
  },
  
    
 
  // the constructor
  _create: function() {

    var that = this;

    this.element
      // prevent double click to select text
      .disableSelection();
      
    this.span_info = $("<label>").appendTo(
            $( "<div>").css({'display':'inline-block','min-width':'10em','padding':'0 2em 0 2em'}).appendTo( this.element ));
    
    this.span_buttons = $( "<span>").appendTo( this.element );
            
    this.btn_goto_prev = $( "<button>", {
                title: top.HR("previous"), text: 'A'
            })
            .appendTo( this.span_buttons )
            .button({icons: {
                        primary: "ui-icon-seek-prev"
                    },text:false})
            .on("click", function(){ that._doSearch3(that.options.current_page-1); } );
    this.span_pages = $( "<span>" ).appendTo( this.span_buttons );

    this.btn_goto_next = $( "<button>", {
                title: top.HR("next"), text: 'A'
            })
            .appendTo( this.span_buttons )
            .button({icons: {
                        primary: "ui-icon-seek-next"
                    },text:false})
            .on("click", function(){ that._doSearch3(that.options.current_page+1); } );
               
    this.options.limit = top.HAPI.get_prefs('search_limit');               
               
    this.btn_search_limit = $( "<button>", {
                text: this.options.limit,
                title: top.HR('records per request')
            })
            //.css('width', '4em')
            .appendTo( this.span_buttons )
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
                    if(newlimit!=that.options.limit){
                    
                        top.HAPI.currentUser['ugr_Preferences']['search_limit'] = newlimit;
                        if(top.HAPI.is_logged()){
                            //save preference in session
                            top.HAPI.SystemMgr.save_prefs({'search_limit': newlimit},
                                function(response){
                                    if(response.status != top.HAPI.ResponseStatus.OK){
                                        top.HEURIST.util.showMsgErr(response.message);
                                    }
                                }
                            );
                        }
                    
                        that.option("limit", newlimit);
                        that._doSearch3(0);
                    }
                }})
            .hide();

    this._on( this.btn_search_limit, {
        click: function() {
            $('.ui-menu').hide(); //hide other
            var menu = $( this.menu_search_limit )
                .show()
                .position({my: "right top", at: "right bottom", of: this.btn_search_limit });
          $( document ).one( "click", function() { menu.hide(); });
          return false;
        }
    });
    
    
    //-----------------------     listener of global events
    var sevents = top.HAPI.Event.LOGIN+' '+top.HAPI.Event.ON_REC_SEARCHRESULT + ' ' + top.HAPI.Event.ON_REC_SEARCHSTART;

    $(this.document).on(sevents, function(e, data) {

        if(e.type == top.HAPI.Event.ON_REC_SEARCHRESULT){

            if(data){
                that.option("count_total", data.count_total()); //hRecordSet
            }else{
                that.option("count_total", 0);
            }
            that._refresh();
        }else if(e.type == top.HAPI.Event.ON_REC_SEARCHSTART){

            //hide all on start search   
            that.option("count_total", 0); //hRecordSet
            that.options.query_request = data;
            if(data.orig != "paginator"){
                that.options.current_page = 0; //reset
            }
            
        }else if(e.type == top.HAPI.Event.ON_REC_SEARCHRESULT){
            that._refresh();    
        }
        
    });    

    this._refresh();

  }, //end _create

  /* private function */
  _refresh: function(){
      
        this.options.limit = top.HAPI.get_prefs('search_limit');
        this.btn_search_limit.button( "option", "label", this.options.limit);
        
        if(this.options.count_total>0){
            
           var limit = this.options.limit;
           
           this.options.max_page = Math.ceil(this.options.count_total / limit); 
           if(this.options.current_page>this.options.max_page-1){
                this.options.current_page = 0;
           }

        }else{ //hide all 
           this.options.max_page = 0;
        }      
       
        var pageCount = this.options.max_page;
        var currentPage = this.options.current_page;
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
  
  _renderRecNumbers: function(){
        var limit = this.options.limit;
        if(this.options.count_total>0){
                var rec_no_first = this.options.current_page*limit+1;
                var rec_no_last =  rec_no_first+limit-1;
                if (rec_no_last>this.options.count_total) { rec_no_last = this.options.count_total; }
                this.span_info.html( rec_no_first+"-"+rec_no_last+"/"+this.options.count_total);
        }else{
                this.span_info.html('');
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
      
          if(top.HEURIST.util.isNumber(page)){
                this.options.current_page = page;
          }
          if(this.options.current_page>this.options.max_page){
                this.options.current_page = this.options.max_page;
          }else if(this.options.current_page<0){
                this.options.current_page = 0;
          }
      

          if ( this.options.query_request ) {

            var that = this;
            
            //  l or limit  - limit of records
            //  o or offset

            var limit = this.options.limit;
            this.options.query_request.l = limit;
            this.options.query_request.o = this.options.current_page * limit;
            this.options.query_request.orig = "paginator";

            if(that.options.isapplication){
                $(that.document).trigger(top.HAPI.Event.ON_REC_SEARCHSTART, [ this.options.query_request ]);
            }

            //get hapi and perform search
            top.HAPI.RecordMgr.search(this.options.query_request,
                function(response)
                {
                    var resdata = null;
                    if(response.status == top.HAPI.ResponseStatus.OK){
                        resdata = new hRecordSet(response.data);
                    }else{
                        top.HEURIST.util.showMsgErr(response.message);
                    }
                    if(that.options.isapplication){
                            $(that.document).trigger(top.HAPI.Event.ON_REC_SEARCHRESULT, [ resdata ]);
                    }
                }

            );

          }

  }  

});
