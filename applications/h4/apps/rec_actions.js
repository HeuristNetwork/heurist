/**
* requires apps/tag_assign.js
*
*/
$.widget( "heurist.rec_actions", {

  // default options
  options: {
    actionbuttons: "add,tags,share,more", //list of visible buttons add, tag, share, more
    
    record_ids: null //array of record ids the action will be applied for
  },

  _allbuttons: ["add","tags","share","more"],

  // the constructor
  _create: function() {

    var that = this;

    this.btn_add = $( "<button>", {
                    text: top.HR("add"),
                    title: top.HR("add new record")
            })
            .addClass('logged-in-only')
            .appendTo( this.element )
            .button({icons: {
                        primary: "ui-icon-circle-plus"
                    }});

    //-----------------------
    this.btn_tags = $( "<button>", {text: top.HR("tags")} )
            .addClass('logged-in-only')
            .appendTo( this.element )
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
                    .tag_assign( 'option', 'record_ids', that.options.record_ids )
                    .show()
                    .position({my: "left top", at: "left bottom", of: this.btn_tags });

              function _hidethispopup(event) {
                  if($(event.target).closest(menu).length==0 ||  $(event.target).attr('id')=='manageTags'){
                        menu.hide();
                  }else{
                        $( document ).one( "click", _hidethispopup);
                        if ($(event.target).attr('type')!='checkbox'){
                            return false;
                        }
                  }
              }

              $( document ).one( "click", _hidethispopup);


          }else{

              if($.isFunction($('body').tag_assign)){ //already loaded
                  this._initTagMenu();
              }else{
                  $.getScript(top.HAPI.basePath+'apps/tag_assign.js', function(){ that._initTagMenu(); } );
              }

          }
          return false;
        }
    });

    //-----------------------
    this.btn_share = $( "<button>", {text: top.HR("share") } )
            .addClass('logged-in-only')
            .appendTo( this.element )
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
    this.btn_more = $( "<button>", {text: top.HR("more")} )
            .addClass('logged-in-only')
            .appendTo( this.element )
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
    var sevents = top.HAPI.Event.LOGIN+' '+top.HAPI.Event.LOGOUT;

    $(this.document).on(sevents, function(e, data) {
         that._refresh();
    });

    this._refresh();

  }, //end _create

  _setOptions: function() {
        // _super and _superApply handle keeping the right this-context
        this._superApply( arguments );
        this._refresh();
  },

  /* private function */
  _refresh: function(){

      if(top.HAPI.currentUser.ugr_ID>0){
            $(this.element).find('.logged-in-only').css('visibility','visible');
      }else{
            $(this.element).find('.logged-in-only').css('visibility','hidden');
      }

      var abtns = (this.options.actionbuttons?this.options.actionbuttons:"tags,share,more").split(',');
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

    $(this.document).off(top.HAPI.Event.LOGIN+' '+top.HAPI.Event.LOGOUT);

    var that = this;
    $.each(this._allbuttons, function(index, value){
        var btn = that['btn_'+value];
        if(btn) btn.remove();
    });

    // remove generated elements
    //keep globally this.menu_tags.remove();
    this.menu_share.remove();
    this.menu_more.remove();
  },


    /**
    * Adds 'tags management' dialogue to body
    */
  _initTagMenu: function() {

       this.menu_tags = $('#heurist-tags');

       if(this.menu_tags.length<1){  //create new widget
           this.menu_tags = $('<div id="heurist-tags">')
                .addClass('menu-or-popup')
                .css('position','absolute')
                .appendTo( this.document.find('body') )
                .tag_assign( { record_ids: this.options.record_ids } )
                .hide();
       }

       this.btn_tags.click();
  },

});
