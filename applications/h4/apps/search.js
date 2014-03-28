$.widget( "heurist.search", {

  // default options
  options: {
    search_domain: 'a', //current search domain all|bookmark|recently added|recently selected  or a|b|r|s
    search_domain_set: null, // comma separated list of allowed domains  a,b,c,r,s

    isrectype: false,  // show rectype selector
    rectype_set: null, // comma separated list of rectypes, if not defined - all rectypes

    isapplication:true,  // send and recieve the global events

    searchdetails: "map", //level of search results  map - with details, structure - with detail and structure

    has_paginator: false,

    // callbacks
    onsearch: null,  //on start search
    onresult: null   //on search result
  },

  query_request: null,  //current search request - need for "AND" search in current result set
  
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

    this.btn_search_assistant = $( "<button>", {
            text: top.HR("search assistant")
            })
            .appendTo( this.div_search_as_user )
            .button({icons: {
                        primary: "ui-icon-lightbulb"
                    }, text:false});
            
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

    var dset = ((this.options.search_domain_set)?this.options.search_domain_set:'a,b,c').split(',');//,r,s';
    var smenu = "";
    $.each(dset, function(index, value){
        var lbl = that._getSearchDomainLabel(value);
        if(lbl){
            smenu = smenu + '<li id="search-domain-'+value+'"><a href="#">'+top.HR(lbl)+'</a></li>'
        }
    });

    this.menu_search_domain = $('<ul>'+smenu+'</ul>')   //<a href="#">
            .zIndex(9999)
            .css('position','absolute')
            .appendTo( this.document.find('body') )
            .menu({
                select: function( event, ui ) {
                    var mode =  ui.item.attr('id').substr(14);  //(ui.item.attr('id')=="search-domain-b")?"b":"a";
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

    this.search_assistant = null;
    
    //show quick search assistant
    this._on( this.btn_search_assistant, {
        click: function() {
        $('.ui-menu').hide(); //hide other
        $('.menu-or-popup').hide(); //hide other

        if(this.search_assistant){ //inited already

                var popup = $( this.search_assistant )
                    .show()
                    .position({my: "right top+3", at: "right bottom", of: this.input_search });
                    //.position({my: "right top", at: "right bottom", of: this.btn_search_assistant });

                function _hidethispopup(event) {
                      if($(event.target).closest(popup).length==0){
                            popup.hide();
                      }else{
                            $( document ).one( "click", _hidethispopup);
                            //return false;
                      }
                }

                $( document ).one( "click", _hidethispopup);  //hide itself on click outside
        }else{ //not inited yet
                this._initSearchAssistant();
        }

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
        
        that.query_request = data; //keep for search in current result
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
        else if(value=='c') { lbl = 'in current'; } //todo
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
            
            if(this.options.search_domain=="c" && !top.HEURIST.util.isnull(this.query_request)){
                   this.options.search_domain = this.query_request.w;
                   qsearch = this.query_request.q + ' AND ' + qsearch;
            }

            var request = {q: qsearch, w: this.options.search_domain, f: this.options.searchdetails, orig:'main'};

            //that._trigger( "onsearch"); //this widget event
            //that._trigger( "onresult", null, resdata ); //this widget event

            //perform search
            top.HAPI.RecordMgr.search(request, $(this.document));
          }

  }
  
  ,_initSearchAssistant: function(){
    
    var $dlg = this.search_assistant = $( "<div>" )
                .addClass('menu-or-popup text ui-corner-all ui-widget-content')
                .zIndex(9999)
                .css('position','absolute')
                .appendTo( this.document.find('body') )
                .hide();
                
    var that = this;                
    //load template            
    $dlg.load("apps/search_quick.html?t="+(new Date().getTime()), function(){

            //find all labels and apply localization
            $dlg.find('label').each(function(){
                 $(this).html(top.HR($(this).html()));
            });
            
            var select_rectype = $("#sa_rectype");
            var select_fieldtype = $("#sa_fieldtype");
            var select_sortby = $("#sa_sortby");
            var select_terms = $("#sa_termvalue");
            var sortasc =  $('#sa_sortasc');
            $dlg.find("#fld_enum").hide();
            
            top.HEURIST.util.createRectypeSelect( select_rectype.get(0), null, top.HR('Any record type'));
            //top.HEURIST.util.createRectypeDetailSelect( select_fieldtype.get(0), 0, null, top.HR('Any record type'));
            
            var allowed = Object.keys(top.HEURIST.detailtypes.lookups);
            allowed.splice(allowed.indexOf("separator"),1);
            allowed.splice(allowed.indexOf("relmarker"),1);
                   
                   
            that._on( select_rectype, {
                change: function (event){
                    
                    var rectype = (event)?Number(event.target.value):0;
                    top.HEURIST.util.createRectypeDetailSelect(select_fieldtype.get(0), rectype, allowed, top.HR('Any field type'), true);
                    
                    /*select_sortby.html("<option value=t>"+top.HR("record title")+"</option>"+
                        "<option value=rt>"+top.HR("record type")+"</option>"+
                        "<option value=u>"+top.HR("record URL")+"</option>"+
                        "<option value=m>"+top.HR("date modified")+"</option>"+
                        "<option value=a>"+top.HR("date added")+"</option>"+
                        "<option value=r>"+top.HR("personal rating")+"</option>"+
                        "<option value=p>"+top.HR("popularity")+"</option>");*/
                    var topOptions = [{key:'t', title:top.HR("record title")},
                    {key:'rt', title:top.HR("record type")},
                    {key:'u', title:top.HR("record URL")},
                    {key:'m', title:top.HR("date modified")},
                    {key:'a', title:top.HR("date added")},
                    {key:'r', title:top.HR("personal rating")},
                    {key:'p', title:top.HR("popularity")}];       
                        
                    if(Number(rectype)>0){
                        topOptions.push({optgroup:'yes', title:top.HEURIST.rectypes.names[rectype]+' '+top.HR('fields')});
                        /*
                        var grp = document.createElement("optgroup");
                        grp.label =  top.HEURIST.rectypes.names[rectype]+' '+top.HR('fields');
                        select_sortby.get(0).appendChild(grp);
                        */
                    }
                    top.HEURIST.util.createRectypeDetailSelect(select_sortby.get(0), rectype, allowed, topOptions, false);
                    
                    $("#sa_fieldvalue").val("");
                    $dlg.find("#fld_contain").show();
                    $dlg.find("#fld_enum").hide();
                    this.calcShowSimpleSearch();
                }
            });
            that._on( select_fieldtype, {
                change: function(event){
                    
                    var dtID = Number(event.target.value);
                    
                    var detailtypes = top.HEURIST.detailtypes.typedefs;
                    
                    if(Number(dtID)>0 && detailtypes[dtID].commonFields[detailtypes.fieldNamesToIndex['dty_Type']]=='enum'){
                         $dlg.find("#fld_contain").hide();
                         $dlg.find("#fld_enum").show();
                         //fill terms
                         var allTerms = detailtypes[dtID]['commonFields'][detailtypes['fieldNamesToIndex']['dty_JsonTermIDTree']],
                         disabledTerms = detailtypes[dtID]['commonFields'][detailtypes['fieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs']];
                         
                         top.HEURIST.util.createTermSelectExt(select_terms.get(0), "enum", allTerms, disabledTerms, null, false);
                    }else{
                         $dlg.find("#fld_contain").show();
                         $dlg.find("#fld_enum").hide();
                    }
                    
                    this.calcShowSimpleSearch();
                }
            });
            that._on( select_terms, {
                change: function(event){
                    this.calcShowSimpleSearch();
                }
            });
            that._on( select_sortby, {
                change: function(event){
                    this.calcShowSimpleSearch();
                }
            });
            that._on( $("#sa_fieldvalue"), {
                keyup: function(event){
                    this.calcShowSimpleSearch();
                }
            });
            that._on( sortasc, {
                click: function(event){
                    //top.HEURIST.util.stopEvent(event);
                    //sortasc.prop('checked', !sortasc.is(':checked'));
                    this.calcShowSimpleSearch();
                }
            });
    
            select_rectype.trigger('change');
            that.btn_search_assistant.click();
    });

  }
  
  ,calcShowSimpleSearch: function (e) {
      
        var q = $("#sa_rectype").val(); if(q) q = "t:"+q;
        var fld = $("#sa_fieldtype").val(); if(fld) fld = "f:"+fld+":";
        var ctn = $("#fld_enum").is(':visible') ?$("#sa_termvalue").val() 
                                                :$("#sa_fieldvalue").val();    
                                                                
        var asc = ($("#sa_sortasc:checked").length > 0 ? "" : "-");
        var srt = $("#sa_sortby").val();
        srt = (srt == "t" && asc == "" ? "" : ("sortby:" + asc + (isNaN(srt)?"":"f:") + srt));
        
        q = (q? (fld?q+" ": q ):"") + (fld?fld: (ctn?" all:":"")) + (ctn? (isNaN(Number(ctn))?'"'+ctn+'"':ctn):"") + (srt? " " + srt : "");
        if(!q){
            q = "sortby:t";
        }
        
        
        this.input_search.val(q);

        e = top.HEURIST.util.stopEvent(e);
  } 

  // events bound via _on are removed automatically
  // revert other modifications here
  ,_destroy: function() {
    // remove generated elements
    this.btn_search_as_guest.remove();
    this.btn_search_as_user.remove();
    this.btn_search_domain.remove();
    this.btn_search_assistant.remove();
    this.search_assistant.remove();
    this.menu_search_domain.remove();
    this.input_search.remove();
    if(this.select_rectype) this.select_rectype.remove();

    this.div_search_as_user.remove();
    this.div_search_as_guest.remove();
    
    if(this.div_paginator) this.div_paginator.remove();

  }

});
