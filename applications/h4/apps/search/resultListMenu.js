/**
* Menu for result list
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


$.widget( "heurist.resultListMenu", {

    // default options
    options: {
        // callbacks
    },

    // the widget's constructor
    _create: function() {

        var that = this;

        this.element
        .css('font-size', '0.8em')
        // prevent double click to select text
        .disableSelection();
        
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
        
        
        this.btn_Search = $( "<button>",{
            text: "Search"
        }).appendTo( this.element ).button();
        
        this.menu_Search = $('<ul>')
        .load('apps/search/resultListMenuSearch.html', function(){
            that.menu_Search.addClass('menu-or-popup')
            .css('position', 'absolute')
            .appendTo( that.document.find('body') )
            .menu();
        })
        .position({my: "right top", at: "left bottom", of: this.btn_Search })
        .hide();

        this.btn_Selected = $( "<button>",{
            text: "Selected"
        }).appendTo( this.element ).button();
        
        this.menu_Selected = $('<ul>')
        .load('apps/search/resultListMenuSelected.html', function(){
            that.menu_Selected.addClass('menu-or-popup')
            .css('position', 'absolute')
            .appendTo( that.document.find('body') )
            .menu();
        })
        .position({my: "right top", at: "left bottom", of: this.btn_Selected })
        .hide();

        this.btn_Collected = $( "<button>",{
            text: "Collected"
        }).appendTo( this.element ).button();
        
        this.menu_Collected = $('<ul>')
        .load('apps/search/resultListMenuCollected.html', function(){
            that.menu_Collected.addClass('menu-or-popup')
            .css('position', 'absolute')
            .appendTo( that.document.find('body') )
            .menu();
        })
        .position({my: "right top", at: "left bottom", of: this.btn_Collected })
        .hide();

        this.btn_Layout = $( "<button>",{
            text: "Layout"
        }).appendTo( this.element ).button();
        
        this.menu_Layout = $('<ul>')
        .load('apps/search/resultListMenuLayout.html', function(){
            that.menu_Layout.addClass('menu-or-popup')
            .css('position', 'absolute')
            .appendTo( that.document.find('body') )
            .menu();
        })
        .position({my: "left top", at: "left bottom", of: this.btn_Layout })
        .hide();
                
        
        //show/hide menu on button hover
        this._on( this.btn_Search, {
            mouseenter : function(){_show(this.menu_Search, this.btn_Search)},
            mouseleave : function(){_hide(this.menu_Search)}
        });
        this._on( this.menu_Search, {
            mouseenter : function(){_show(this.menu_Search, this.btn_Search)},
            mouseleave : function(){_hide(this.menu_Search)}
        });      
        this._on( this.btn_Selected, {
            mouseenter : function(){_show(this.menu_Selected, this.btn_Selected)},
            mouseleave : function(){_hide(this.menu_Selected)}
        });
        this._on( this.menu_Selected, {
            mouseenter : function(){_show(this.menu_Selected, this.btn_Selected)},
            mouseleave : function(){_hide(this.menu_Selected)}
        });
        this._on( this.btn_Collected, {
            mouseenter : function(){_show(this.menu_Collected, this.btn_Collected)},
            mouseleave : function(){_hide(this.menu_Collected)}
        });
        this._on( this.menu_Collected, {
            mouseenter : function(){_show(this.menu_Collected, this.btn_Collected)},
            mouseleave : function(){_hide(this.menu_Collected)}
        });
        this._on( this.btn_Layout, {
            mouseenter : function(){_show(this.menu_Layout, this.btn_Layout)},
            mouseleave : function(){_hide(this.menu_Layout)}
        });
        this._on( this.menu_Layout, {
            mouseenter : function(){_show(this.menu_Layout, this.btn_Layout)},
            mouseleave : function(){_hide(this.menu_Layout)}
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

    },
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements
        this.btn_Search.remove();
        this.menu_Search.remove();
        this.btn_Selected.remove();
        this.menu_Selected.remove();
        this.btn_Collected.remove();
        this.menu_Collected.remove();
        this.btn_Layout.remove();
        this.menu_Layout.remove();

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
