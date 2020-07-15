/**
* mainMenu6.js : main menu for v6
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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


$.widget( "heurist.mainMenu6", {

    // default options
    options: {
        
    },
    
    menues:{}, //section menu - div with menu actions
    containers:{}, //operation containers (next to section menu)
    
    _myTimeoutId: 0,
    _active_section: null,
    
    divMainMenu: null,  //main div

    // the widget's constructor
    _create: function() {

        var that = this;

        this.element.addClass('ui-menu6')
            .disableSelection();// prevent double click to select text

        //90 200    
        this.divMainMenu = $('<div>')
          .css({position:'absolute',width:'90px',top:'2px',left:'2px',bottom:'4px',cursor:'pointer','z-index':100})
          .appendTo( this.element )
          .load(
            window.hWin.HAPI4.baseURL+'hclient/widgets/dropdownmenus/mainMenu6.html',
          function(){ 

                that.divMainMenu.find('.menu-text').hide();
                
                that._on(that.divMainMenu.find('.ui-heurist-explore'),{
                    mouseenter: that._expandPanel,
                    mouseleave: that._collapsePanel,
                });
                
                that._on(that.divMainMenu.find('.ui-heurist-header'),{
                    click: that._openSectionMenu
                });
                
                
          });

    }, //end _create
    
    _refresh: function(){
    },
    
    _destroy: function() {
        this.divMainMenu.remove();
    },

    //
    //
    //    
    _collapsePanel: function(ele) {
        var that = this;
        this._myTimeoutId = setTimeout(function() {
            /*
            that.divMainMenu.find('.menu-text').fadeOut(300, function(){;
                if(that._myTimeoutId>0){
                    that.divMainMenu.find('.ui-heurist-header2').css({'text-align':'center'});
                    that.divMainMenu.effect('size',  { to: { width: 90 } }, 300);
                }
            });
            */
            that.divMainMenu.find('.menu-text').hide();
            that.divMainMenu.find('.ui-heurist-header2').css({'text-align':'center'});
            that.divMainMenu.effect('size',  { to: { width: 90 } }, 300, function(){
                that.divMainMenu.find('.menu-text').hide();
            });
        }, 800);
    },
    
    //
    //
    //
    _expandPanel: function(ele, parent) {
        clearTimeout(this._myTimeoutId);
        this._myTimeoutId = 0;
        var that = this;
        this.divMainMenu.effect('size',  { to: { width: 200 } }, 500,
                function(){
                    that.divMainMenu.find('.ui-heurist-header2').css({'text-align':'left'});
                    that.divMainMenu.find('.menu-text').show('fade',300);
                });
    },
    
    //
    //
    //
    _openSectionMenu: function(e){
        
        var m_class = null;
        var that = this;
        
        var sections = ['design','import','explore','publish','admin'];
        
        $.each(sections, function(i, section){
            that.element.removeClass('ui-heurist-'+section+'-fade');    
        });
        
        $.each(sections, function(i, section){
            
            if($(e.target).hasClass('ui-heurist-'+section)){
                
                if(!that.menues[section]){
                    
                    that.menues[section] = $('<div>')
                        .addClass('ui-menu6-section ui-heurist-'+section)
                        .css({width:'200px'})
                        .appendTo( that.element );
                        
                    that.containers[section] = $('<div>')
                        .addClass('ui-menu6-container ui-heurist-'+section)
                        .appendTo( that.element );
                        
                    if(section=='explore')
                    {
                        that.containers[section]
                            .css({left:'96px'})
                            .show();

                        window.hWin.HAPI4.LayoutMgr.appInitAll('SearchAnalyze2', that.containers[section] );
                        
                    }else{
                        that.menues[section].load(
                            window.hWin.HAPI4.baseURL+'hclient/widgets/dropdownmenus/mainMenu6_'+section+'.html',
                            function(){ 
                                that._initSectionMenu(section);
                            });

                        
                    }
                }

                if(that._active_section && that._active_section!=section 
                    && that.menues[that._active_section])
                {
                    that.containers[that._active_section].hide();
                    that.menues[that._active_section].hide();
                }

                if(that.menues[section].is(':visible')){ //close on repeat click
                    if(section!=='explore'){
                        that.menues[section].hide();        
                    }
                    m_class = 'ui-heurist-explore-fade'; //by default
                    that._active_section = 'explore';
                }else{
                    that.menues[section].show();
                    m_class = 'ui-heurist-'+section+'-fade';
                    that._active_section = section;
                }
                
                return false; //exit loop
            }
        });
        //change main background
        if(m_class){
            this.element.addClass(m_class);    
            if(that._active_section=='explore' && that.menues['explore'])
                that.menues['explore'].show();
        }
    },
    
    //
    // find menu actions and assign icon and title
    //
    _initSectionMenu: function(section){
        
        var widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('mainMenu');
        if(!widget) return;

        $.each(this.menues[section].find('li[data-action]'),
            function(i, item){
                item = $(item);
                var action_id = item.attr('data-action');
                if( action_id ){
                    var link = widget.mainMenu('menuGetActionLink', action_id);    
                    
                    $('<span class="ui-icon '+link.attr('data-icon')+'"/>'
                     +'<span class="menu-text">'+link.text()+'</span>')
                    .appendTo(item);
                    
                    item.css({'font-size':'smaller', padding:'6px'})
                        .attr('title',link.attr('title'));
                }
            });
            
        this.menues[section].find('.ui-icon')
                    .addClass('ui-heurist-title')  //apply color
                    .css({cursor:'pointer'});
        
        //execute            
        this._on(this.menues[section].find('li[data-action]'),{click:function(e){
            var li = $(e.target);
            if(!li.is('li')) li = li.parents('li');
            this.containers[section].show();
            widget.mainMenu('menuActionById', li.attr('data-action'), this.containers[section]); 
        }});
        
    }

    
});
