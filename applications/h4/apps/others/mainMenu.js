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
        .css('width','160px')
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
        
        this.divCurrentUser = $( "<div>",{'id':'divCurrentUser'}).css('padding-bottom','4px').appendTo(this.divMainMenu);
        
        this.divMainMenuItems = $('<ul>').addClass('horizontalmenu').appendTo(this.divMainMenu);
        
        this._initMenu('Profile');
        this._initMenu('Database');
        this._initMenu('Import');
        this._initMenu('Export');
        this._initMenu('Help');
        this.divMainMenuItems.menu();
        
        // SECOND LINE --------------------------------------------------
        this.div_BottomRow = $("<div>").css({ 'position':'absolute', top:46, left: '2px', right:0, 'padding-right': '2em' }).appendTo( this.element );
        
        this.btn_switch_to_design = $( "<button>", {
            text: top.HR("go to designer view")
        })
        .css('width','160px')
        .appendTo( this.div_BottomRow )
        .button()
        .click(function( event ) {
            var url = top.HAPI4.basePathOld + "admin/adminMenu.php?db=" + top.HAPI4.database;        
            window.open(url, "_blank");
        });
        
        this.btn_add_record = $( "<button>", {
            text: top.HR("add new record")
        })
        .css('float','right')
        .addClass('logged-in-only')
        .appendTo( this.div_BottomRow )
        .button({icons: {
            primary: "ui-icon-circle-plus"
        }})
        .click( function(){ that._addNewRecord(); });
        
        
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
        this.btn_Profile.remove();
        this.menu_Profile.remove();
        this.btn_Database.remove();
        this.menu_Database.remove();
        this.btn_Import.remove();
        this.menu_Import.remove();
        this.btn_Export.remove();
        this.menu_Export.remove();
        this.btn_Help.remove();
        this.menu_Help.remove();

    },

    _initMenu: function(name){
        
        var that = this;

        //show hide function
        var _hide = function(ele) {
                $( ele ).delay(800).hide();
            },
            _show = function(ele, parent) {
                $('.menu-or-popup').hide(); //hide other
                var menu = $( ele )
                //.css('width', this.btn_user.width())
                .show()
                .position({my: "left top", at: "left bottom", of: parent });
                //$( document ).one( "click", function() { menu.hide(); });
                return false;
            };
            
        var link = $('<a>',{
            text: name, href:'#'
        });
        
        this['btn_'+name] = $('<li>').append(link)
        .appendTo( this.divMainMenuItems );
        
        this['menu_'+name] = $('<ul>')          
        .load('apps/others/mainMenu'+name+'.html?t='+(new Date().getTime()), function(){
            that['menu_'+name].addClass('menu-or-popup')
            .css('position','absolute')
            .appendTo( that.document.find('body') )
            .menu();
            
            that._initLinks(that['menu_'+name]);
        })
        //.position({my: "left top", at: "left bottom", of: this['btn_'+name] })
        .hide();
        
        this._on( this['btn_'+name], {
            mouseenter : function(){_show(this['menu_'+name], this['btn_'+name])},
            mouseleave : function(){_hide(this['menu_'+name])}
        });
        this._on( this['menu_'+name], {
            mouseenter : function(){_show(this['menu_'+name], this['btn_'+name])},
            mouseleave : function(){_hide(this['menu_'+name])}
        });        
        
        
    },
    
    //init listeners for auto-popup links
    _initLinks: function(menu){
        
        var that = this;
        
        menu.find('[name="auto-popup"]').each(function(){
            var ele = $(this);
            var href = ele.attr('href');
            if(!top.HEURIST4.util.isempty(href)){
                href = href + (href.indexOf('?')>0?'&':'?') + 'db=' + top.HAPI4.database;
                ele.attr('href', href);
                that._on(ele, {
                    click: function(event){ 
                        /*
                        var body = this.document.find('body');
                        var dim = {h:body.innerHeight(), w:body.innerWidth()},
                            link = $(event.target),
                            options = {};
                        if (link.hasClass('small')){
                            options.height=dim.h*0.55; options.width=dim.w*0.5;
                        }else if (link.hasClass('portrait')){
                            options.height=dim.h*0.8; options.width=dim.w*0.5;
                        }else if (link.hasClass('large')){
                            options.height=dim.h*0.8; options.width=dim.w*0.8;
                        }else if (link.hasClass('verylarge')){
                            options.height = dim.h*0.95; 
                            options.width  = dim.w*0.95;
                        }else if (link.hasClass('fixed')){
                            options.height=dim.h*0.8; options.width=800;
                        }else if (link.hasClass('landscape')){
                            options.height=dim.h*0.5;
                            options.width=dim.w*0.8;
                        }
                        top.HEURIST.util.popupURL(top, link.attr('href'), options);
                        */
                        
                        
                        var body = this.document.find('body');
                        var dim = {h:body.innerHeight(), w:body.innerWidth()},
                            link = $(event.target),
                            that = this;

                        var options = { title: link.html() };

                        if (link.hasClass('small')){
                            options.height=dim.h*0.55; options.width=dim.w*0.5;
                        }else if (link.hasClass('portrait')){
                            options.height=dim.h*0.8; options.width=dim.w*0.5;
                        }else if (link.hasClass('large')){
                            options.height=dim.h*0.8; options.width=dim.w*0.8;
                        }else if (link.hasClass('verylarge')){
                            options.height = dim.h*0.95; 
                            options.width  = dim.w*0.95;
                        }else if (link.hasClass('fixed')){
                            options.height=dim.h*0.8; options.width=800;
                        }else if (link.hasClass('landscape')){
                            options.height=dim.h*0.5;
                            options.width=dim.w*0.8;
                        }
                        
                        top.HEURIST4.util.showDialog(link.attr('href'), options);
                      
                        event.preventDefault();
                        return false;
                    }
                });
            }
        });
    
    },
    
    _addNewRecord: function(){
        
        
        var url = top.HAPI4.basePathOld+ "records/add/addRecordPopup.php?db=" + top.HAPI4.database;
        
        Hul.showDialog(url, { height:450, width:700, 
                    callback:function(responce) {
/*                        
                var sURL = top.HEURIST.basePath + "common/php/reloadCommonInfo.php";
                top.HEURIST.util.getJsonData(
                    sURL,
                    function(responce){
                        if(responce){
                            top.HEURIST.rectypes.usageCount = responce;
                            top.HEURIST.search.createUsedRectypeSelector(true);
                        }
                    },
                    "db="+_db+"&action=usageCount");
*/                        
                    }
            });
            
    }
    

});
