/**
* Top Main Menu panel
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


$.widget( "heurist.mainMenu", {

    // default options
    options: {
        // callbacks
    },

    // the widget's constructor
    _create: function() {

        var that = this;

        this.element
        // prevent double click to select text
        .disableSelection();

        this.div_logo = $( "<div>")
        .addClass('logo')
        .css('width','150px')
        .css('float','left')
        .appendTo( this.element )
        .button();

        // bind click events
        this._on( this.div_logo, {
            click: function(){$( "#heurist-about" ).dialog("open");}
        });

        //'padding-left':'15px', 'display':'inline-block',  'vertical-align': 'middle'
        this.div_dbname = $( "<div>").css({'float':'left', 'padding-left':'2em', 'text-align':'center' }).appendTo(this.element);

        $("<div>").css({'font-size':'1.6em', 'font-style':'italic'}).text(top.HAPI4.database).appendTo( this.div_dbname );
        $("<div>").css({'font-size':'0.8em'}).text("v"+top.HAPI4.sysinfo.version).appendTo( this.div_dbname );

        // MAIN MENU-----------------------------------------------------
        
        this.divMainMenu = $( "<div>").css({'float':'right', 'padding-right':'2em', 'text-align':'right' }).appendTo(this.element);
        this.divCurrentUser = $( "<div>",{'id':'divCurrentUser'}).appendTo(this.divMainMenu);
        this.divMainMenuItems = $( "<div>").appendTo(this.divMainMenu);
        
        
        //show hide function
        var _hide = function(ele) {
                $( ele ).delay(500).hide();
            },
            _show = function(ele, parent) {
                $('.menu-or-popup').hide(); //hide other
                var menu = $( ele )
                //.css('width', this.btn_user.width())
                .show()
                .position({my: "right top", at: "right bottom", of: parent });
                $( document ).one( "click", function() { menu.hide(); });
                return false;
            };
        
        
        this.btn_Help = $( "<button>",{
            text: "Help"
        })
        .css('float','right')
        .appendTo( this.divMainMenuItems )
        .button();
        
        this.menu_Help = $('<ul>')
        .load('apps/others/mainMenuHelp.html', function(){
            that.menu_Help.addClass('menu-or-popup')
            .css('position','absolute')
            .appendTo( that.document.find('body') )
            .menu();
        })
        .position({my: "right top", at: "right bottom", of: this.btn_Help })
        .hide();
                
        this.btn_Export = $( "<button>",{
            text: "Export"
        })
        .css('float','right')
        .addClass('logged-in-only')
        .appendTo( this.divMainMenuItems )
        .button();
        
        this.menu_Export = $('<ul>')
        .load('apps/others/mainMenuExport.html', function(){
            that.menu_Export.addClass('menu-or-popup')
            .css('position','absolute')
            .appendTo( that.document.find('body') )
            .menu();
        })
        .position({my: "right top", at: "right bottom", of: this.btn_Export })
        .hide();

        this.btn_Import = $( "<button>",{
            text: "Import"
        })
        .css('float','right')
        .addClass('logged-in-only')
        .appendTo( this.divMainMenuItems )
        .button();
        
        this.menu_Import = $('<ul>')
        .load('apps/others/mainMenuImport.html', function(){
            that.menu_Import.addClass('menu-or-popup')
            .css('position','absolute')
            .appendTo( that.document.find('body') )
            .menu();
        })
        .position({my: "right top", at: "right bottom", of: this.btn_Import })
        .hide();
  
        this.btn_Database = $( "<button>",{
            text: "Database"
        })
        .css('float','right')
        .addClass('logged-in-only')
        .appendTo( this.divMainMenuItems )
        .button();
        
        this.menu_Database = $('<ul>')
        .load('apps/others/mainMenuDatabase.html', function(){
            that.menu_Database.addClass('menu-or-popup')
            .css('position','absolute')
            .appendTo( that.document.find('body') )
            .menu();
        })
        .position({my: "right top", at: "right bottom", of: this.btn_Database })
        .hide();
        
        this.btn_user = $( "<button>",{
            text: "My Profile"
        })
        .addClass('logged-in-only')
        .appendTo( this.divMainMenuItems )
        .button();
        //.css('line-height','0.2em');
        
        this.menu_user = $('<ul>')
        .load('apps/others/mainMenuProfile.html', function(){
            that.menu_user.addClass('menu-or-popup')
            .css('position','absolute')
            .appendTo( that.document.find('body') )
            .menu();
            that._initLinks(that.menu_user);
        })
        .position({my: "right top", at: "right bottom", of: this.btn_user })
        .hide();
        
        //show/hide menu on button hover
        this._on( this.btn_user, {
            mouseenter : function(){_show(this.menu_user, this.btn_user)},
            mouseleave : function(){_hide(this.menu_user)}
        });
        this._on( this.menu_user, {
            mouseenter : function(){_show(this.menu_user, this.btn_user)},
            mouseleave : function(){_hide(this.menu_user)}
        });      
        this._on( this.btn_Database, {
            mouseenter : function(){_show(this.menu_Database, this.btn_Database)},
            mouseleave : function(){_hide(this.menu_Database)}
        });
        this._on( this.menu_Database, {
            mouseenter : function(){_show(this.menu_Database, this.btn_Database)},
            mouseleave : function(){_hide(this.menu_Database)}
        });
        this._on( this.btn_Import, {
            mouseenter : function(){_show(this.menu_Import, this.btn_Import)},
            mouseleave : function(){_hide(this.menu_Import)}
        });
        this._on( this.menu_Import, {
            mouseenter : function(){_show(this.menu_Import, this.btn_Import)},
            mouseleave : function(){_hide(this.menu_Import)}
        });
        this._on( this.btn_Export, {
            mouseenter : function(){_show(this.menu_Export, this.btn_Export)},
            mouseleave : function(){_hide(this.menu_Export)}
        });
        this._on( this.menu_Export, {
            mouseenter : function(){_show(this.menu_Export, this.btn_Export)},
            mouseleave : function(){_hide(this.menu_Export)}
        });
        this._on( this.btn_Help, {
            mouseenter : function(){_show(this.menu_Help, this.btn_Help)},
            mouseleave : function(){_hide(this.menu_Help)}
        });
        this._on( this.menu_Help, {
            mouseenter : function(){_show(this.menu_Help, this.btn_Help)},
            mouseleave : function(){_hide(this.menu_Help)}
        });        
        
        //init 
        
        
        
        this._refresh();

    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
        
    },
    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    _setOptions: function( ) {
        this._superApply( arguments );
    },

    /* 
    * private function 
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

        if(top.HAPI4.currentUser.ugr_ID>0){ 
            this.divCurrentUser.html( top.HAPI4.currentUser.ugr_FullName +
            ': <a href="../../common/connect/login.php?logout=1&amp;db='+top.HAPI4.database+'">log&nbsp;out</a>'
            );
            
        }else{
            //not logged -guest
            $(this.element).find('.logged-in-only').hide();
            $(this.element).find('.logged-out-only').show();
            
            this.divCurrentUser.empty();
        }
        
    },
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements
        this.btn_user.remove();
        this.menu_user.remove();
        this.btn_Database.remove();
        this.menu_Database.remove();
        this.btn_Import.remove();
        this.menu_Import.remove();
        this.btn_Export.remove();
        this.menu_Export.remove();
        this.btn_Help.remove();
        this.menu_Help.remove();

    },
    
    //init listeners for auto-popup links
    _initLinks: function(menu){
        
        var that = this;
        
        menu.find('[name="auto-popup"]').each(function(){
            var ele = $(this);
            var href = ele.attr('href');
            if(!top.HEURIST4.util.isempty(href)){
                href = href + (href.indexOf('?')>0?'&amp;':'?') + 'db=' + top.HAPI4.database;
                ele.attr('href', href);
                that._on(ele, {
                    click: function(event){ 
                        
                        var $dlg = $("#heurist-dialog");
                        $dlg.empty();
                        
                        this.dosframe = $( "<iframe>" ).appendTo( $dlg );
                        this.dosframe.attr('src',  $(event.target).attr('href'));
                        $dlg.dialog({
                                autoOpen: true,
                                height: 420,
                                width: 480,
                                modal: true,
                                //resizable: false,
                                //draggable: false,
                                title: 'KUKU' //this.html()
                        });
                        
                        event.preventDefault();
                        return false;
                    }
                });
            }
        });
    
    }
    

});
