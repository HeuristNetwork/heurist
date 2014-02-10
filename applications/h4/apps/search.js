$.widget( "heurist.search", {

  // default options
  options: {
    search_domain: 'a', //current search domain all|bookmark|recently added|recently selected  or a|b|r|s
    search_domain_set: null, // comma separated list of allowed domains  a,b,r,s

    isrectype: false,  // show rectype selector
    rectype_set: null, // comma separated list of rectypes, if not defined - all rectypes

    isapplication:true,  // send and recieve the global events

    searchdetails: "map", //level of search results  map - with details, structure - with detail and structure

    has_paginator: false,

    // callbacks
    onsearch: null,  //on start search
    onresult: null   //on search result
  },

  // the constructor
  _create: function() {

    var that = this;

    this.input_search = $( "<input>", {width:'30%'} )
            .css('margin-right','0.2em')
            .addClass("text ui-widget-content ui-corner-all")
            .appendTo( this.element );

    this.div_search_as_guest = $('<span>')
            .css('display', 'inline-black')
            .addClass('logged-out-only')
            .appendTo( this.element );

    this.btn_search_as_guest = $( "<button>", {
            text: top.HR("search")
            })
            .appendTo( this.div_search_as_guest )
            .button({icons: {
                        primary: "ui-icon-search"
                    }});

    this.div_search_as_user = $('<span>')
            .addClass('logged-in-only')
            .appendTo( this.element );

    this.btn_search_as_user = $( "<button>", {
            text: top.HR("search")
            })
            .css('width', '12em')
            .appendTo( this.div_search_as_user )
            .button({icons: {
                        primary: "ui-icon-search"
                    }});

    this.btn_search_domain = $( "<button>", {
            text: top.HR("search option")
            })
            .appendTo( this.div_search_as_user )
            .button({icons: {
                        primary: "ui-icon-triangle-1-s"
                    }, text:false});

    this.div_search_as_user.buttonset();

    var dset = ((this.options.search_domain_set)?this.options.search_domain_set:'a,b').split(',');//,r,s';
    var smenu = "";
    $.each(dset, function(index, value){
        var lbl = that._getSearchDomainLabel(value);
        if(lbl){
            smenu = smenu + '<li id="search-domain-'+value+'"><a href="#">'+lbl+'</a></li>'
        }
    });

    this.menu_search_domain = $('<ul>'+smenu+'</ul>')   //<a href="#">
            .zIndex(9999)
            .css('position','absolute')
            .appendTo( this.document.find('body') )
            .menu({
                select: function( event, ui ) {
                    var mode = (ui.item.attr('id')=="search-domain-b")?"b":"a";
                    that.option("search_domain", mode);
                    that._refresh();
                }})
            .hide();

    this._on( this.btn_search_domain, {
        click: function() {
            $('.ui-menu').hide(); //hide other
            var menu = $( this.menu_search_domain )
                .css('width', this.div_search_as_user.width())
                .show()
                .position({my: "right top", at: "right bottom", of: this.btn_search_domain });
          $( document ).one( "click", function() { menu.hide(); });
          return false;
        }
    });


    if(this.options.isrectype){

         $("<label>for&nbsp;</label>").appendTo( this.element );

        this.select_rectype = $( "<select>" )
                .addClass('text ui-widget-content ui-corner-all')
                .css('width','auto')
                .css('max-width','200px')
                //.val(value)
                .appendTo( this.element );
    }

    // bind click events
    this._on( this.btn_search_as_user, {
      click: "doSearch"
    });

    this._on( this.btn_search_as_guest, {
      click: "doSearch"
    });

    this._on( this.input_search, {
        keypress: function(e){
                var code = (e.keyCode ? e.keyCode : e.which);
                if (code == 13) {
                    that.doSearch();
                }
        }
    });

    if(this.options.has_paginator){
        if($.isFunction($('body').pagination)){
            this._initPagination();
        }else{
             $.getScript(top.HAPI.basePath+'apps/pagination.js', function() {
                 if($.isFunction($('body').pagination)){
                     that._initPagination();
                 }else{
                     top.HEURIST.util.showMsgErr('Widget pagination not loaded!');
                 }        
             });          
        }
    }
   

    //global listener

    $(this.document).on(top.HAPI.Event.LOGIN+' '+top.HAPI.Event.LOGOUT, function(e, data) {
        that._refresh();
    });
    $(this.document).on(top.HAPI.Event.ON_REC_SEARCHSTART, function(e, data){
        if(data.orig != 'main'){
            that.input_search.val(data.q);
            that.options.search_domain = data.w;
            that._refresh();
        }
    });


    this._refresh();


  }, //end _create
  
  _initPagination: function(){
        this.div_paginator = $('<span>')
                .css('display', 'inline-block')
                .appendTo( this.element )
                .pagination();
  },

  _setOption: function( key, value ) {
      this._super( key, value );

      if(key=='rectype_set' || key=='search_domain'){
            this._refresh();
      }
  },

  /* private function */
  _refresh: function(){

      if(top.HAPI.currentUser.ugr_ID>0){
            $(this.element).find('.logged-in-only').show(); //.css('visibility','visible');
            //$(this.element).find('.logged-in-only').css('visibility','visible');

            //$('.logged-out-only').css('visibility','hidden');
            $(this.element).find('span.logged-out-only').hide();
      }else{
            $(this.element).find('.logged-in-only').hide();
            ///$(this.element).find('.logged-in-only').css('visibility','hidden');
            //$('.logged-out-only').css('visibility','visible');
            $(this.element).find('span.logged-out-only').show();
      }

      this.btn_search_as_user.button( "option", "label", this._getSearchDomainLabel(this.options.search_domain));

      if(this.select_rectype){
            this.select_rectype.empty();
            top.HEURIST.util.createRectypeSelect(this.select_rectype.get(0), this.options.rectype_set, !this.options.rectype_set);
      }
  },
/*
  _handleKeyPress: function(e){
      var code = (e.keyCode ? e.keyCode : e.which);
      if (code == 13) {
          this.doSearch();
      }
  },
*/
  _getSearchDomainLabel: function(value){
        var lbl = null;
        if(value=='b' || value=='bookmark') { lbl = 'my bookmarks'; }
        else if(value=='r') { lbl = 'recently added'; } //not implemented
        else if(value=='s') { lbl = 'recently selected'; } //not implemented
        else { lbl = 'all records'; this.options.search_domain='a';}
        return lbl;
  },

  doSearch: function(){

            var qsearch = this.input_search.val();
            if( this.select_rectype && this.select_rectype.val()){
                qsearch = qsearch + ' t:'+this.select_rectype.val();
            }

          if ( qsearch ) {

            // q - query string
            // w  all|bookmark
            // stype  key|all   - key-search tags, all-title and pointer record title, by default rec_Title

            var that = this;

            var request = {q: qsearch, w: this.options.search_domain, f: this.options.searchdetails, orig:'main'};

            //that._trigger( "onsearch"); //this widget event
            //that._trigger( "onresult", null, resdata ); //this widget event

            //perform search
            top.HAPI.RecordMgr.search(request, $(this.document));
          }

  },

  // events bound via _on are removed automatically
  // revert other modifications here
  _destroy: function() {
    // remove generated elements
    this.btn_search_as_guest.remove();
    this.btn_search_as_user.remove();
    this.btn_search_domain.remove();
    this.menu_search_domain.remove();
    this.input_search.remove();
    if(this.select_rectype) this.select_rectype.remove();

    this.div_search_as_user.remove();
    this.div_search_as_guest.remove();
    
    if(this.div_paginator) this.div_paginator.remove();

  }

});
