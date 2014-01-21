/**
* requires apps/tag_manager.js
*
*/
$.widget( "heurist.rec_list", {

  // default options
  options: {
    view_mode: 'list', // list|icons|thumbnails   @toimplement detail, condenced
    recordset: null,

    actionbuttons: "tags,share,more,sort,view", //list of visible buttons add, tag, share, more, sort, view
    multiselect: true,

    isapplication:true,

    // callbacks
    onselect: null
  },

  _allbuttons: ["add","tags","share","more","sort","view"],

  // the constructor
  _create: function() {

    var that = this;

    this.div_toolbar = $( "<div>" ).css({'width': '100%'}).appendTo( this.element );
    this.div_content = $( "<div>" )
        .css({'left':0,'right':0,'overflow-y':'auto','padding':'0.2em','position':'absolute','top':'5em','bottom':'0'})
        //.position({my: "left top", at: "left bottom", of: this.div_toolbar })
        .appendTo( this.element );


    this.btn_add = $( "<button>", {
                    text: top.HR("add"),
                    title: top.HR("add new record")
            })
            .addClass('logged-in-only')
            .appendTo( this.div_toolbar )
            .button({icons: {
                        primary: "ui-icon-circle-plus"
                    }});

    //-----------------------
    this.btn_tags = $( "<button>", {text: top.HR("tags")} )
            .addClass('logged-in-only')
            .appendTo( this.div_toolbar )
            .button({icons: {
                        primary: "ui-icon-tag",
                        secondary: "ui-icon-triangle-1-s"
                    },text:false});

    this.menu_tags = null;

    this._on( this.btn_tags, {
        click: function() {
          $('.menu-or-popup').hide(); //hide other
          if(this.menu_tags){

              var menu = $( this.menu_tags )
                    .tag_manager( 'option', 'record_ids', null )
                    .show()
                    .position({my: "left top", at: "left bottom", of: this.btn_tags });

              function _hidethispopup(event) {
                  if($(event.target).closest(menu).length==0){
                        menu.hide();
                  }else{
                        $( document ).one( "click", _hidethispopup);
                        return false;
                  }
              }

              $( document ).one( "click", _hidethispopup);


          }else{

              if($.isFunction($('body').tag_manager)){
                  this._initTagMenu();
              }else{
                  $.getScript(top.HAPI.basePath+'apps/tag_manager.js', function(){ that._initTagMenu(); } );
              }

          }
          return false;
        }
    });

    //-----------------------
    this.btn_share = $( "<button>", {text: "share"} )
            .addClass('logged-in-only')
            .appendTo( this.div_toolbar )
            .button({icons: {
                        secondary: "ui-icon-triangle-1-s"
                    },text:true});

    this.menu_share = $('<ul>'+
        '<li id="menu-share-access"><a href="#">Access</a></li>'+
        '<li id="menu-share-notify"><a href="#">Notify</a></li>'+
        '<li id="menu-share-embed"><a href="#">Embed / link</a></li>'+
        '<li id="menu-share-export"><a href="#">Export</a></li>'+
        '</ul>')
            .addClass('menu-or-popup')
            .css('position','absolute')
            .appendTo( this.document.find('body') )
            .menu({
                select: function( event, ui ) {
                    //ui.item.attr('id');
                }})
            .hide();

    this._on( this.btn_share, {
        click: function() {
          $('.menu-or-popup').hide(); //hide other
          var menu = $( this.menu_share )
                .show()
                .position({my: "left top", at: "left bottom", of: this.btn_share });
          $( document ).one( "click", function() { menu.hide(); });
          return false;
        }
    });

    //-----------------------
    this.btn_more = $( "<button>", {text: "more"} )
            .addClass('logged-in-only')
            .appendTo( this.div_toolbar )
            .button({icons: {
                        secondary: "ui-icon-triangle-1-s"
                    },text:true});


    this.menu_more = $('<ul>'+
        '<li id="menu-more-relate"><a href="#">Relate to</a></li>'+
        '<li id="menu-more-rate"><a href="#">Rate</a></li>'+
        '<li id="menu-more-merge"><a href="#">Merge</a></li>'+
        '<li id="menu-more-delete"><a href="#">Delete</a></li>'+
        '</ul>')
            .addClass('menu-or-popup')
            .css('position','absolute')
            .appendTo( this.document.find('body') )
            .menu({
                select: function( event, ui ) {
                    //ui.item.attr('id');
                }})
            .hide();

    this._on( this.btn_more, {
        click: function() {
          $('.menu-or-popup').hide(); //hide other
          var menu = $( this.menu_more )
              //.css('width', this.btn_more.width())
              .show()
              .position({my: "right top", at: "right bottom", of: this.btn_more });
          $( document ).one( "click", function() { menu.hide(); });
          return false;
        }
    });

    //-----------------------
    this.btn_view = $( "<button>", {text: "view"} )
            .css('float','right')
            .css('width', '10em')
            .appendTo( this.div_toolbar )
            .button({icons: {
                        secondary: "ui-icon-triangle-1-s"
                    },text:true});

    this.menu_view = $('<ul>'+
        '<li id="menu-view-list"><a href="#">'+top.HR('list')+'</a></li>'+
        //'<li id="menu-view-detail"><a href="#">Details</a></li>'+
        '<li id="menu-view-icons"><a href="#">'+top.HR('icons')+'</a></li>'+
        '<li id="menu-view-thumbs"><a href="#">'+top.HR('thumbs')+'</a></li>'+
        '</ul>')
            .addClass('menu-or-popup')
            .css('position','absolute')
            .appendTo( this.document.find('body') )
            .menu({
                select: function( event, ui ) {
                    var mode = ui.item.attr('id');
                    mode = mode.substr(10);
                    that._applyViewMode(mode);
                }})
            .hide();

    this._on( this.btn_view, {
        click: function(e) {
          $('.menu-or-popup').hide(); //hide other
          var menu_view = $( this.menu_view )
            .show()
            .position({my: "right top", at: "right bottom", of: this.btn_view });
          $( document ).one( "click", function() {  menu_view.hide(); });
          return false;
        }
    });


    //-----------------------     listener of global events
    var sevents = top.HAPI.Event.LOGIN+' '+top.HAPI.Event.LOGOUT;
    if(this.options.isapplication){
        sevents = sevents + ' ' + top.HAPI.Event.ON_REC_SEARCHRESULT + ' ' + top.HAPI.Event.ON_REC_SEARCHSTART;
    }

    $(this.document).on(sevents, function(e, data) {

        if(e.type == top.HAPI.Event.LOGIN){

            that._refresh();

        }else  if(e.type == top.HAPI.Event.LOGOUT)
        {
            that.option("recordset", null);

        }else if(e.type == top.HAPI.Event.ON_REC_SEARCHRESULT){

            that.option("recordset", data); //hRecordSet
            that.loadanimation(false);

        }else if(e.type == top.HAPI.Event.ON_REC_SEARCHSTART){

            that.option("recordset", null);
            that.loadanimation(true);

        }
        //that._refresh();
    });
/*
    if(this.options.isapplication){
        $(this.document).on(top.HAPI.Event.ON_REC_SEARCHRESULT, function(e, data) {
            that.option("recordset", data); //hRecordSet
            that._refresh();
        });
    }
*/

    this._refresh();

  }, //end _create

  _setOptions: function() {
        // _super and _superApply handle keeping the right this-context
        this._superApply( arguments );
        this._refresh();
  },
/*
  _setOption: function( key, value ) {
      this._super( key, value );
      this._refresh();
  },
*/

  /* private function */
  _refresh: function(){

      // repaint current record set
      this._renderRecords();  //@todo add check that recordset really changed
      this._applyViewMode();

      if(top.HAPI.currentUser.ugr_ID>0){
            $(this.div_toolbar).find('.logged-in-only').css('visibility','visible');
            $(this.div_content).find('.logged-in-only').css('visibility','visible');
      }else{
            $(this.div_toolbar).find('.logged-in-only').css('visibility','hidden');
            $(this.div_content).find('.logged-in-only').css('visibility','hidden');
      }

      var abtns = (this.options.actionbuttons?this.options.actionbuttons:"tags,share,more,sort,view").split(',');
      var that = this;
      $.each(this._allbuttons, function(index, value){

          var btn = that['btn_'+value];
          if(btn){
              btn.css('display',($.inArray(value, abtns)<0?'none':'inline-block'));
          }
      });


  },

  // events bound via _on are removed automatically
  // revert other modifications here
  _destroy: function() {

    $(this.document).off(top.HAPI.Event.LOGIN+' '+top.HAPI.Event.LOGOUT+' '+top.HAPI.Event.ON_REC_SEARCHRESULT);

    var that = this;
    $.each(this._allbuttons, function(index, value){
        var btn = that['btn_'+value];
        if(btn) btn.remove();
    });

    // remove generated elements
    this.div_toolbar.remove();
    this.div_content.remove();


    this.menu_tags.remove();
    this.menu_share.remove();
    this.menu_more.remove();
    this.menu_view.remove();

  },


  _initTagMenu: function() {

       this.menu_tags = $('<div>')
            .addClass('menu-or-popup')
            .css('position','absolute')
            .appendTo( this.document.find('body') )
            .tag_manager()
            .hide();

       this.btn_tags.click();
  },

  _applyViewMode: function(newmode){

        //var $allrecs = this.div_content.find('.recordDiv');
        if(newmode){
            var oldmode = this.options.view_mode;
            this.options.view_mode = newmode;
            //this.option("view_mode", newmode);
            this.div_content.removeClass(oldmode)
        }else{
            newmode = this.options.view_mode;
        }
        this.div_content.addClass(newmode);

        this.btn_view.button( "option", "label", top.HR(newmode));
  },

  // @todo move record related stuff to HAPI
  _renderRecords: function(){

        if(this.div_content){
            var $allrecs = this.div_content.find('.recordDiv');
            this._off( $allrecs, "click");
            this.div_content.empty();  //clear
        }

       if(this.options.recordset){
               this.loadanimation(false);

               var recs = this.options.recordset.getRecords();

               //for(i=0; i<recs.length; i++){
               //$.each(this.options.records.records, this._renderRecord)
               var recID;
               for(recID in recs) {
                 if(recID){
                    this._renderRecord(recs[recID]);
                 }
               }

               $allrecs = this.div_content.find('.recordDiv');
               this._on( $allrecs, {
                        click: this._recordDivOnClick
               });
       }

  },

  /**
  * create div for given record
  *
  * @param record
  */
  _renderRecord: function(record){

        var recset = this.options.recordset;
        function fld(fldname){
            return recset.fld(record, fldname);
        }

/*
           0 .'bkm_ID,'
           1 .'bkm_UGrpID,'
           2 .'rec_ID,'
           3 .'rec_URL,'
           4 .'rec_RecTypeID,'
           5 .'rec_Title,'
           6 .'rec_OwnerUGrpID,'
           7 .'rec_NonOwnerVisibility,'
           8 .'rec_URLLastVerified,'
           9 .'rec_URLErrorMessage,'
          10 .'bkm_PwdReminder ';
          11  thumbnailURL - may not exist
*/

        var recID = fld('rec_ID');
        var rectypeID = fld('rec_RecTypeID');

        $recdiv = $(document.createElement('div'));

        $recdiv
            .addClass('recordDiv')
            .attr('id', 'rd'+recID )
            .attr('recID', recID )
            .attr('bkmk_id', fld('bkm_ID') )
            .attr('rectype', rectypeID )
            //.attr('title', 'Select to view, Ctrl-or Shift- for multiple select')
            //.on("click", that._recordDivOnClick )
            .appendTo(this.div_content);

        $(document.createElement('div'))
            .addClass('recTypeThumb')
            .css('background-image', 'url('+ top.HAPI.iconBaseURL + 'thumb/th_' + rectypeID + '.png)')
            .appendTo($recdiv);

        if(fld['thumbnailURL']){
        $(document.createElement('div'))
            .addClass('recTypeThumb')
            .css('background-image', 'url('+ fld['thumbnailURL'] + ')')
            .appendTo($recdiv);
        }

        $iconsdiv = $(document.createElement('div'))
            .addClass('recordIcons')
            .attr('recID', recID )
            .attr('bkmk_id', fld('bkm_ID') )
            .appendTo($recdiv);

        //record type icon
        $('<img>',{
                src:  top.HAPI.basePath+'assets/16x16.gif',
                title: '@todo rectypeTitle'.htmlEscape()
            })
            //!!! .addClass('rtf')
            .css('background-image', 'url('+ top.HAPI.iconBaseURL + rectypeID + '.png)')
            .appendTo($iconsdiv);

        //bookmark icon - asterics
        $('<img>',{
                src:  top.HAPI.basePath+'assets/13x13.gif'
            })
            .addClass(fld('bkm_ID')?'bookmarked':'unbookmarked')
            .appendTo($iconsdiv);

        $('<div>',{
                title: fld('rec_Title')
            })
            .addClass('recordTitle')
            .html(fld('rec_URL') ?("<a href='"+fld('rec_URL')+"' target='_blank'>"+fld('rec_Title') + "</a>") :fld('rec_Title') )
            .appendTo($recdiv);

        $('<div>',{
                id: 'rec_edit_link',
                title: 'Click to edit record'
            })
            .addClass('logged-in-only')
            .button({icons: {
                        primary: "ui-icon-pencil"
                            },
                     text:false})
            .click(function( event ) {
                event.preventDefault();
                window.open(top.HAPI.basePath + "php/recedit.php?db="+top.HAPI.database+"&q=ids:"+recID, "_blank");
            })
            .appendTo($recdiv);


/*
        var editLinkIcon = "<div id='rec_edit_link' class='logged-in-only'><a href='"+
            top.HEURIST.basePath+ "records/edit/editRecord.html?sid=" +
            top.HEURIST.search.results.querySid + "&recID="+ res[2] +
            (top.HEURIST.database && top.HEURIST.database.name ? '&db=' + top.HEURIST.database.name : '');

        if (top.HEURIST.user && res[6] && (top.HEURIST.user.isInWorkgroup(res[6])|| res[6] == top.HEURIST.get_user_id()) || res[6] == 0) {
            editLinkIcon += "' target='_blank' title='Click to edit record'><img src='"+
                            top.HEURIST.basePath + "common/images/edit-pencil.png'/></a></div>";
        }else{
            editLinkIcon += "' target='_blank' title='Click to edit record extras only'><img src='"+
                            top.HEURIST.basePath + "common/images/edit-pencil-no.png'/></a></div>";
        }
*/

  },

  _recordDivOnClick: function(event){

       //var $allrecs = this.div_content.find('.recordDiv');

        var $rdiv = $(event.target);

        if(!$rdiv.hasClass('recordDiv')){
            $rdiv = $rdiv.parents('.recordDiv');
        }

        if(this.options.multiselect && event.ctrlKey){

            if($rdiv.hasClass('selected')){
                $rdiv.removeClass('selected');
                //$rdiv.removeClass('ui-state-highlight');
            }else{
                $rdiv.addClass('selected');
                //$rdiv.addClass('ui-state-highlight');
            }
        }else{
            //remove seletion from all recordDiv
            this.div_content.find('.selected').removeClass('selected');
            $rdiv.addClass('selected');

            //var record = this.options.recordset.getById($rdiv.attr('recID'));
        }

        var selected = this.getSelected();

        if(selected.length()>0){
            if(this.options.isapplication){
                $(this.document).trigger(top.HAPI.Event.ON_REC_SELECT, [ selected ]);
            }
            this._trigger( "onselect", event, selected );
         }
 },

 /**
 * return hRecordSet of selected records
 */
 getSelected: function(){

        var selected = [];
        var that = this;
        this.div_content.find('.selected').each(function(ids, rdiv){
            var record = that.options.recordset.getById($(rdiv).attr('recid'));
            selected.push(record);
        });

        return that.options.recordset.getSubSet(selected);
 },

 loadanimation: function(show){
     if(show){
         this.div_content.css('background','url('+top.HAPI.basePath+'assets/loading-animation-white.gif) no-repeat center center');
     }else{
        this.div_content.css('background','none');
     }
 }


});
