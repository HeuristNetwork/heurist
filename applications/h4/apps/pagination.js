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
    limit: 10
  },

  // the constructor
  _create: function() {

    var that = this;

    this.element
      // prevent double click to select text
      .disableSelection();
      
    /*this.btn_goto_first = $( "<button>", {
            text: top.HR("first")
            })
            .appendTo( this.element )
            .button({icons: {
                        primary: "ui-icon-seek-first"
                    }})
            .on("click", function(){ that.options.current_page=0; that._doSearch3(); } );*/
    this.btn_goto_prev = $( "<button>", {
            title: top.HR("previous")
            })
            .appendTo( this.element )
            .button({icons: {
                        primary: "ui-icon-seek-prev"
                    },text:false})
            .on("click", function(){ that._doSearch3(that.options.current_page-1); } );
    this.span_pages = $( "<span>" ).appendTo( this.element );

    this.btn_goto_next = $( "<button>", {
            title: top.HR("next")
            })
            .appendTo( this.element )
            .button({icons: {
                        primary: "ui-icon-seek-next"
                    },text:false})
            .on("click", function(){ that._doSearch3(that.options.current_page+1); } );
    /*this.btn_goto_last = $( "<button>", {
            text: top.HR("last")
            })
            .appendTo( this.element )
            .button({icons: {
                        primary: "ui-icon-seek-last"
                    }})
            .on("click", function(){ that.options.current_page=that.options.max_page; that._doSearch3(); } );*/
                    
    
    //-----------------------     listener of global events
    var sevents = top.HAPI.Event.ON_REC_SEARCHRESULT + ' ' + top.HAPI.Event.ON_REC_SEARCHSTART;

    $(this.document).on(sevents, function(e, data) {

        if(e.type == top.HAPI.Event.ON_REC_SEARCHRESULT){

            if(data){
                that.option("count_total", data.count_total()); //hRecordSet
            }else{
                that.option("count_total", 0);
            }
            
        }else if(e.type == top.HAPI.Event.ON_REC_SEARCHSTART){

            //hide all on start search   
            that.option("count_total", 0); //hRecordSet
            that.options.query_request = data;
            if(data.orig != "paginator"){
                that.options.current_page = 0; //reset
            }
        }
        that._refresh();
    });    

    this._refresh();

  }, //end _create

  /* private function */
  _refresh: function(){
        
        
        if(this.options.count_total>0){
           
           this.options.max_page = Math.ceil(this.options.count_total / this.options.limit); 
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
       
       
        if (pageCount < 2) {
            this.element.hide();
            return;
        }else{
            this.element.css('display', 'inline-block');
        }

        // KJ's patented heuristics for awesome useful page numbers
        if (pageCount > 9) {
            if (currentPage < 5) { start = 1; finish = 8; }
            else if (currentPage < pageCount-5) { start = currentPage - 2; finish = currentPage + 4; }
            else { start = pageCount - 7; finish = pageCount; }
        } else {
            start = 1; finish = pageCount;
        }

//    this.span_pages.remove();
        
        
        if (currentPage == 0) {
            this.btn_goto_prev.hide();
        }else{
            this.btn_goto_prev.show();
        }
        if (currentPage == pageCount-1) {
            this.btn_goto_next.hide();
        }else{
            this.btn_goto_next.show();
        }
        
        this.span_pages.html('');
        var that = this;

        if (start != 1) {
            $( "<button>", { text: "1" }).button().appendTo( this.span_pages ).on("click", function(){ that._doSearch3(0); } );
//...            
            
        }
        for (i=start; i <= finish; ++i) {
            $( "<button>", { text: ''+i }).button().appendTo( this.span_pages ).on("click", function(){ that._doSearch3(i-1); } );
        }
        if (finish != pageCount) {
            $( "<button>", { text: ''+pageCount }).button().appendTo( this.span_pages ).on("click", function(){ that._doSearch3(pageCount-1); } );
        }

  },

  // events bound via _on are removed automatically
  // revert other modifications here
  _destroy: function() {
    // remove generated elements
    this.span_pages.remove();
    this.btn_goto_prev.remove();
    this.btn_goto_next.remove();
    //this.btn_goto_last.remove();
  },
  
  _doSearch3: function(page){
      
          this.options.current_page = page;
          if(this.options.current_page>this.options.max_page-1){
                this.options.current_page = this.options.max_page-1;
          }else if(this.options.current_page<0){
                this.options.current_page = 0;
          }
      

          if ( this.options.query_request ) {

            var that = this;
            
            //  l or limit  - limit of records
            //  o or offset

            this.options.query_request.l = this.options.limit;
            this.options.query_request.o = this.options.current_page * this.options.limit;
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
