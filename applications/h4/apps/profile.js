/**
*  Login Button (if status is log off) 
*  or 
*  Add New Record, Profile and Option Buttons with drop down menu 
*/
$.widget( "heurist.profile", {

  // default options
  options: {
    // callbacks
    onlogin: null,
    onlogout: null
  },

  // the constructor
  _create: function() {

    var that = this;

    this.element
      // prevent double click to select text
      .disableSelection();

    //--------------------- HELP     
    this.btn_help = $( "<button>", {text: top.HR("Help")} )
            .css('float','right')
            .appendTo( this.element )
            .button({icons: {
                        primary: "ui-icon-help"
                    },text:false});
      
    this._on( this.btn_help, {
        click: function() {
          window.open(top.HAPI.sysinfo.help, '_blank');
          return false;
        }
    });

      
    //---------------------  OPTIONS BUTTON WITH DROPDOWN MENU
    this.btn_options = $( "<button>", {text: top.HR("options")} )
            .css('float','right')
            .appendTo( this.element )
            .button({icons: {
                        primary: "ui-icon-gear",
                        secondary: "ui-icon-triangle-1-s"
                    },text:false});

    this.menu_options = $('<ul id="menu-options">'+
        '<li id="menu-options-db-select"><a href="#">'+top.HR('Databases')+'</a></li>'+
        '<li id="menu-options-db-design" class="logged-in-only"><a href="#">'+top.HR('Design database')+'</a></li>'+
        '<li id="menu-options-db-summary" class="logged-in-only"><a href="#">'+top.HR('Database Summary')+'</a></li>'+
        '<li id="menu-options-import" class="logged-in-only"><a href="#">'+top.HR('Import data')+'</a>'+
            '<ul>'+
                '<li><a href="#">CSV</a></li>'+
                '<li><a href="#">KML</a></li>'+
                '<li><a href="#">Zotero</a></li>'+
                '<li><a href="#">'+top.HR('Files in situ')+'</a></li>'+
                '<li><a href="#">Email</a></li>'+
                '<li id="menu-options-import-faims"><a href="#">FAIMS</a></li>'+
            '</ul>'+
        '</li>'+
        (top.HAPI.sys_registration_allowed?'<li id="menu-options-register" class="logged-out-only"><a href="#">'+top.HR('Register')+'</a></li>':'')+
        '<li id="menu-options-help"><a href="#">'+top.HR('Help')+'</a></li>'+
        '<li id="menu-options-bug" class="logged-in-only"><a href="#">'+top.HR('Report bug')+'</a></li>'+
        '<li id="menu-options-about"><a href="#">'+top.HR('About')+'</a></li>'+
        '</ul>')
            .addClass('menu-or-popup')
            .css('position','absolute')
            .css('width','12em')
            .appendTo( this.document.find('body') )
            .menu({
                select: function( event, ui ) {
                    var action = ui.item.attr('id');
                    if(action == "menu-options-db-select"){

                        var $dlg = $("#heurist-dialog");
                        $dlg.empty();
                        $dlg.load("php/databases.php .db-list", function(){
                            $dlg.dialog({
                                autoOpen: true,
                                height: 420,
                                width: 480,
                                modal: true,
                                resizable: false,
                                draggable: false,
                                title: top.HR("Select database")
                            })
                        });
                    }else if(action == "menu-options-register"){
                           that.editProfile();
                    }else if(action == "menu-options-about"){
                        $( "#heurist-about" ).dialog("open");
                    }else if(action == "menu-options-help"){
                        window.open(top.HAPI.sysinfo.help, '_blank');                        
                    }else if(action == "menu-options-db-design"){
                        window.open(top.HAPI.basePathOld+'admin/adminMenu.php?db='+top.HAPI.database, '_blank');                        
                    }else if(action == "menu-options-db-summary"){
                        
                        if($.isFunction($('body').rectype_manager)){ //already loaded
                            showManageRecordTypes();
                        }else{
                            $.getScript(top.HAPI.basePath+'apps/rectype_manager.js', function(){ showManageRecordTypes(); } );
                        }
                        
                    }else if(action == "menu-options-import-faims"){

                        var $dlg = $("#heurist-dialog");
                        $dlg.empty();
                        $dlg.load("php/sync/faims.php?db="+top.HAPI.database+" .utility-content", function(){
                            $dlg.dialog({
                                autoOpen: true,
                                height: 480,
                                width: 640,
                                modal: true,
                                resizable: false,
                                draggable: false,
                                title: top.HR("FAIMS sync")
                            })
                        });
                    }
                }})
            .position({my: "right top", at: "right bottom", of: this.btn_options })    
            .hide();

    
    //show/hide menu on button click        
    this._on( this.btn_options, {
        click: function() {
          $('.menu-or-popup').hide(); //hide other
          var menu = $( this.menu_options )
                .show()
                .position({my: "right top", at: "right bottom", of: this.btn_options });
                
          //hide on click outside
          $( document ).one( "click", function() { menu.hide(); });
          return false;
        }
    });

    //---------------------  LOGIN BUTTON
    if(top.HAPI.sysinfo.registration_allowed==1){
    this.btn_register = $( "<button>", {
                    text: top.HR('Register')
            })
            .css('float','right')
            .addClass('logged-out-only')
            .appendTo( this.element )
            .button();
    }
    
    this.btn_login = $( "<button>", {
                    text: top.HR('Login')
            })
            .css('float','right')
            .addClass('logged-out-only')
            .appendTo( this.element )
            .button({icons: {
                        primary: "ui-icon-key"
                    }});
        
    if(!top.HAPI.is_ui_normal()){            
        //'color':'red','background-color':'white',
        this.login_welcome = $("<div>")
                .html(top.HR((top.HAPI.sysinfo.registration_allowed==1)?"Please log in":"Please contact to register"))
                .css({'font-size':'1.2em', 'padding-right':'4px',
                            'float':'right','width':'50%','text-align':'right', 'margin-right':'4px'})
                .addClass('logged-out-only2 ui-state-error ui-corner-all').appendTo( this.element );
                
        /* flashing prompt                      
        this.blink_interval = setInterval(function(){ 
                    that.login_welcome.is(":visible")?that.login_welcome.hide():that.login_welcome.show() 
                    },500);
       */             
    }
                    
    //--------------------- PROFILE BUTTON
    this.btn_user = $( "<button>",{
                    text: "username"
            })
            .css('float','right')
            .addClass('logged-in-only')
            .appendTo( this.element )
            .button({icons: {
                        primary: "ui-icon-person",
                        secondary: "ui-icon-triangle-1-s"
                    }});
    this.menu_user = $('<ul>'+
        '<li id="menu-user-preferences"><a href="#">'+top.HR('Preferences')+'</a></li>'+
        '<li id="menu-user-profile"><a href="#">'+top.HR('My User Info')+'</a></li>'+
        '<li id="menu-user-groups"><a href="#">'+top.HR('My Groups')+'</a></li>'+
        '<li id="menu-user-tags"><a href="#">'+top.HR('Manage Tags')+'</a></li>'+
        '<li id="menu-user-files"><a href="#">'+top.HR('Manage Files')+'</a></li>'+
        '<li id="menu-user-reminders"><a href="#">'+top.HR('Reminders')+'</a></li>'+
        '<li id="menu-user-svs"><a href="#">'+top.HR('Saved Searches')+'</a></li>'+
        '<li id="menu-user-faceted"><a href="#">'+top.HR('Faceted Search')+'</a></li>'+
        '<li id="menu-user-logout"><a href="#">'+top.HR('Log out')+'</a></li>'+
        '</ul>')
            .addClass('menu-or-popup')
            .css('position','absolute')
            .appendTo( this.document.find('body') )
            .menu({
                select: function( event, ui ) {
                    var action = ui.item.attr('id');
                    if(action == "menu-user-logout"){
                        that.logout();
                    }else if(action == "menu-user-preferences"){

                        that.editPreferences();
                    }else if(action == "menu-user-profile"){

                        that.editProfile();
                    }else if(action == "menu-user-tags"){
                        if($.isFunction($('body').tag_manager)){ //already loaded
                            showManageTags();
                        }else{
                            $.getScript(top.HAPI.basePath+'apps/tag_manager.js', function(){ showManageTags(); } );
                        }
                    }else if(action == "menu-user-faceted"){
                        if($.isFunction($('body').search_faceted_wiz)){ //already loaded
                            showSearchFacetedWizard();
                        }else{
                            $.getScript(top.HAPI.basePath+'apps/search_faceted_wiz.js', function(){ showSearchFacetedWizard(); } );
                        }
                    }else if(action == "menu-user-svs"){
                        if($.isFunction($('body').svs_manager)){ //already loaded
                            showManageSavedSearches();
                        }else{
                            $.getScript(top.HAPI.basePath+'apps/svs_manager.js', function(){ showManageSavedSearches(); } );
                        }
                    }else if(action == "menu-user-files"){
                        if($.isFunction($('body').file_manager)){ //already loaded
                            showManageFiles();
                        }else{
                            $.getScript(top.HAPI.basePath+'apps/file_manager.js', function(){ showManageFiles(); } );
                        }
                    }

                    
                }})
            .position({my: "right top", at: "right bottom", of: this.btn_user })
            .hide();

    //show/hide menu on button click        
    this._on( this.btn_user, {
        click: function() {
          $('.menu-or-popup').hide(); //hide other
          var menu = $( this.menu_user )
                .css('width', this.btn_user.width())
                .show()
                .position({my: "right top", at: "right bottom", of: this.btn_user });
          $( document ).one( "click", function() { menu.hide(); });
          return false;
        }
    });

    //---------------------  ADD NEW RECORD BUTTON
    this.btn_record = $( "<button>", {
                    text: top.HR("add new record")
            })
            .css('float','right')
            .addClass('logged-in-only')
            .appendTo( this.element )
            .button({icons: {
                        primary: "ui-icon-circle-plus",
                        secondary: "ui-icon-triangle-1-s"
                    }});

    this.select_rectype = $( "<select>" )
                .addClass('menu-or-popup text ui-corner-all ui-widget-content')
                .attr("size","15")
                .css({'position':'absolute'})   //,'border':'none'
                .appendTo(this.document.find('body'));
    this.select_rectype.position({my: "right top", at: "right bottom", of: that.btn_record })
                .hide();

    top.HEURIST.util.createRectypeSelect(this.select_rectype.get(0), null, false);

                    

    this._on( this.btn_record, {
      click: function() {
        $('.menu-or-popup').hide(); //hide other
        that.select_rectype.show()
                           .position({my: "right top", at: "right bottom", of: that.btn_record });
        $( document ).one( "click", function() { that.select_rectype.hide(); });
        return false;
          //window.open(top.HAPI.basePath + "php/recedit.php?db="+top.HAPI.database, "_blank");
      }
    });
    this._on( this.select_rectype, {
      click: function(event) {

          var recordtype = event.target.value;

          if(!top.HEURIST.editing){ //create new object
                top.HEURIST.editing = new hEditing();
          }
          // add new record
          top.HEURIST.editing.add(recordtype);

          /* open in new window
          window.open(top.HAPI.basePath + "php/recedit.php?db="+top.HAPI.database+"&rt="+recordtype, "_blank");
          */
      }
    });


    //---------------------
    this.login_dialog = $( "<div>" )
    .appendTo( this.element );


    // bind click events
    this._on( this.btn_login, {
      click: "login"
    });
    if(this.btn_register){
    this._on( this.btn_register, {
      click: "editProfile"
    });
    }

    /*this._on( this.btn_record, {
      click: "logout"
    });*/

/*
    this._on( this.btn_logout, {
      click: "islogged"
    });
*/



    this._refresh();

  }, //end _create

  /* 
  * private function 
  * show/hide buttons depends on current login status
  */
  _refresh: function(){

      if(top.HAPI.currentUser.ugr_ID>0){ 
            if(this.blink_interval){ 
                    clearInterval(this.blink_interval);
                    this.blink_interval = null;
            }
            $(this.element).find('.logged-in-only').show(); //.css('visibility','visible');
            $(this.element).find('.logged-out-only').hide(); //.css('visibility','hidden');
            $(this.element).find('.logged-out-only2').hide(); //.css('visibility','hidden');
            this.btn_user.button( "option", "label", top.HAPI.currentUser.ugr_FullName);
            $('#menu-options li.logged-in-only').css('display','block');
            $('#menu-options li.logged-out-only').css('display','none');
      }else{
            //not logged -guest
            $(this.element).find('.logged-in-only').hide(); //.css('visibility','hidden');
            $(this.element).find('.logged-out-only').show(); //.css('visibility','visible');
            $('#menu-options li.logged-in-only').css('display','none');
            $('#menu-options li.logged-iut-only').css('display','block');
      }

  },

  /*
  islogged: function(){

        var that = this;

        top.HAPI.SystemMgr.is_logged(
            function(response){
                if(response.status == top.HAPI.ResponseStatus.OK){
                    alert(response.data);
                }else{
                    top.HEURIST.util.showMsgErr(response.message);
                }
            }
        );


  },      */

  logout: function(){

        var that = this;

        top.HAPI.SystemMgr.logout(
            function(response){
                if(response.status == top.HAPI.ResponseStatus.OK){
                    top.HAPI.setCurrentUser(null);
                    $(that.document).trigger(top.HAPI.Event.LOGOUT);
                    that._refresh();
                }else{
                    top.HEURIST.util.showMsgErr(response);
                }
            }
        );

  },

  login: function(){

    if(  this.login_dialog.is(':empty') )
    {
        var that = this;
        var $dlg = this.login_dialog;

        //load login dialogue
        $dlg.load("apps/profile_login.html?t="+(new Date().getTime()), function(){

            //find all labels and apply localization
            $dlg.find('label').each(function(){
                 $(this).html(top.HR($(this).html()));
            });
            
            var allFields = $dlg.find('input');
            var message = $dlg.find('.messages');
            var isreset = false;
            
            function __doLogin(){

                  allFields.removeClass( "ui-state-error" );

                  if(isreset){
                      var rusername = $dlg.find('#reset_username');
                      if(top.HEURIST.util.checkLength( rusername, "username", message, 1, 0 ))
                      {
                         top.HAPI.SystemMgr.reset_password({username: rusername.val()}, function(response){
                                if(response.status == top.HAPI.ResponseStatus.OK){
                                    $dlg.dialog( "close" );
                                    top.HEURIST.util.showMsgDlg(top.HR('Your password has been reset. You should receive an email shortly with your new password'), null, "Info");
                                }else{
                                    top.HEURIST.util.showMsgErr(response);
                                }
                         });
                      }
                  }else{
                  
                      var username = $dlg.find('#username');
                      var password = $dlg.find('#password');
                      var session_type = $dlg.find('input[name="session_type"]');

                      var bValid = top.HEURIST.util.checkLength( username, "username", message, 3, 16 )
                               && top.HEURIST.util.checkLength( password, "password", message, 3, 16 );

                      if ( bValid ) {

                        //get hapi and perform login
                        top.HAPI.SystemMgr.login({username: username.val(), password:password.val(), session_type:session_type.val()},
                            function(response){
                                if(response.status == top.HAPI.ResponseStatus.OK){

                                    top.HAPI.setCurrentUser(response.data.currentUser);
                                    top.HAPI.sysinfo = response.data.sysinfo;

                                    $(that.document).trigger(top.HAPI.Event.LOGIN, [top.HAPI.currentUser]);

                                    $dlg.dialog( "close" );
                                    that._refresh();
                                }else{
                                    message.addClass( "ui-state-highlight" );
                                    message.text(response.message);
                                }
                            }

                        );

                      }
                  }
            }

            //start login on enter press
            allFields.on("keypress",function(event){
                  var code = (event.keyCode ? event.keyCode : event.which);
                  if (code == 13) {
                      __doLogin();
                  }
            });
            
            $dlg.find("#link_restore").on("click", function(){
                isreset = true;
                $dlg.dialog("option","title",top.HR('Reset password'));
                $("#btn_login2").button("option","label",top.HR('Reset password'));
                //$dlg.find("#btn_login2").button("option","label",top.HR('Reset password'));
                $dlg.find("#fld_reset").show();
                $dlg.find("#fld_login").hide();
                $dlg.find(".messages").removeClass( "ui-state-highlight" ).text('');
            });

            // login dialog definition
            $dlg.dialog({
              autoOpen: false,
              //height: 300,
              width: 350,
              modal: true,
              resizable: false,
              title: top.HR('Login'),
              buttons: [
                {text:top.HR('Login'), click: __doLogin, id:'btn_login2'},
                {text:top.HR('Cancel'), click: function() {
                  $( this ).dialog( "close" );
                }}
              ],
              close: function() {
                allFields.val( "" ).removeClass( "ui-state-error" );
              },
              open: function() {
                isreset = false;
                $dlg.dialog("option","title",top.HR('Login'));
                $("#btn_login2").button("option","label",top.HR('Login'));
                //$dlg.find("#btn_login2").button("option","label",top.HR('Login'));
                $dlg.find("#fld_reset").hide();
                $dlg.find("#fld_login").show();
                $dlg.find(".messages").removeClass( "ui-state-highlight" ).text('');
              }
            });

            $dlg.dialog("open");
        });//load html
    }else{
        //show dialogue
        this.login_dialog.dialog("open");
    }
  },
  
  editProfile: function()
  {
        if($.isFunction($('body').profile_edit)){
            
            if(!this.div_profile_edit || this.div_profile_edit.is(':empty') ){
                this.div_profile_edit = $('<div>').appendTo( this.element );
            }
            this.div_profile_edit.profile_edit({'ugr_ID': top.HAPI.currentUser.ugr_ID});
            
        }else{
             var that = this;
             $.getScript(top.HAPI.basePath+'apps/profile_edit.js', function() {
                 if($.isFunction($('body').profile_edit)){
                     that.editProfile();
                 }else{
                     top.HEURIST.util.showMsgErr('Widget profile edit not loaded!');
                 }        
             });          
        }
      
  },

  /**
  * Open Edit Preferences dialog
  */
  editPreferences: function()
  {
        var $dlg = $("#heurist-dialog");
        $dlg.empty();

        $dlg.load("apps/profile_preferences.html", function(){

            //find all labels and apply localization
            $dlg.find('label').each(function(){
                 $(this).html(top.HR($(this).html()));
            })
            $dlg.find('.header').css({'min-width':'300px', 'width':'300px'});

            populateLanguages();

            //assign values to form fields from top.HAPI.currentUser['ugr_Preferences']
            var prefs = top.HAPI.currentUser['ugr_Preferences'];
            var allFields = $dlg.find('input,select');

            var currentTheme = prefs['layout_theme'];
            var themeSwitcher = $("#layout_theme").themeswitcher({currentTheme:currentTheme,
                    onSelect: function(){
                        currentTheme = this.currentTheme;
                    }});
            
            //form prefs to ui
            allFields.each(function(){
                    if(prefs[this.id]){
                        if(this.type=="checkbox"){
                            this.checked = (prefs[this.id]=="1" || prefs[this.id]=="true")
                        }else{
                            $(this).val(prefs[this.id]);
                        }
                    };
            });

            function __doSave(){

                    var request = {};

                                allFields.each(function(){
                                    if(this.type=="checkbox"){
                                        request[this.id] = this.checked?"1":"0";
                                    }else{
                                        request[this.id] = $(this).val();
                                    }
                                });
                                request.layout_theme = currentTheme; //themeSwitcher.getSelected();//    getCurrentTheme();
                                //$('#layout_theme').themeswitcher.


                    //save preferences in session
                    top.HAPI.SystemMgr.save_prefs(request,
                        function(response){
                            if(response.status == top.HAPI.ResponseStatus.OK){

                                var prefs = top.HAPI.currentUser['ugr_Preferences'];
                                var ask_reload = (prefs['layout_language'] != request['layout_language'] ||
                                                 prefs['layout_theme'] != request['layout_theme']);
                                
                                top.HAPI.currentUser['ugr_Preferences'] = request;
                                /*allFields.each(function(){
                                    top.HAPI.currentUser['ugr_Preferences'][this.id] = $(this).val();
                                });*/

                                $dlg.dialog( "close" );
                                
                                if(ask_reload){
                                    top.HEURIST.util.showMsgDlg('Reload page to apply new settings?',
                                    function(){
                                        window.location.reload();
                                    }, 'Confirmation');
                                }
                                
                            }else{
                                var message = $dlg.find('.messages');
                                message.addClass( "ui-state-highlight" );
                                message.text(response.message);
                            }
                        }
                    );
            }

            $dlg.dialog({
                autoOpen: true,
                height: 420,
                width: 640,
                modal: true,
                resizable: false,
                draggable: true,
                title: top.HR("Preferences"),
                buttons: [
                    {text:top.HR('Save'), click: __doSave},
                    {text:top.HR('Cancel'), click: function() {
                      $( this ).dialog( "close" );
                    }}
                ]
            })
        });

  },


  // events bound via _on are removed automatically
  // revert other modifications here
  _destroy: function() {
    // remove generated elements
    this.select_rectype.remove();
    this.btn_record.remove();
    this.btn_user.remove();
    this.btn_options.remove();
    this.btn_help.remove();
    this.btn_login.remove();
    if(this.btn_register) this.btn_register.remove();

    this.menu_user.remove();
    this.menu_options.remove();

    this.login_dialog.remove();

    if(this.login_welcome) {this.login_welcome.remove(); };
    if(this.blink_interval){clearInterval(this.blink_interval);}
    if(this.div_profile_edit) {this.div_profile_edit.remove(); };
  }

});
