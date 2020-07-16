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
    
    sections: ['design','import','explore','publish','admin'],
    
    menues:{}, //section menu - div with menu actions
    containers:{}, //operation containers (next to section menu)
    
    _myTimeoutId: 0,
    _myTimeoutId2: 0,
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
                    mouseenter: that._expandMainMenuPanel, //mouseenter mouseover
                    mouseleave: that._collapseMainMenuPanel,
                });
                that._on(that.divMainMenu.not('.ui-heurist-explore'), {
                    mouseover: that._mousein_SectionMenu,  
                    mouseleave: that._mouseout_SectionMenu,
                });
                
          
                that._on(that.divMainMenu.find('.ui-heurist-header'),{
                    click: that._openSectionMenu
                });

                //init all menues
                $.each(that.sections, function(i, section){
                    that._loadSectionMenu(section);
                });
                //open explore by default  
                that._switchSection( 'explore' );
                
          });

    }, //end _create
    
    _refresh: function(){
    },
    
    _destroy: function() {
        this.divMainMenu.remove();
    },

    //
    // collapse main menu panel on explore mouseout
    //    
    _collapseMainMenuPanel: function(ele) {
//console.log(' _collapseMainMenuPanel ' );
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
            that.divMainMenu.effect('size',  { to: { width: 90 } }, 300, function(){
                that.divMainMenu.find('.ui-heurist-header2').css({'text-align':'center'});
                that.divMainMenu.find('.menu-text').hide();
            });
        }, 800);
    },
    
    //
    // expand main menu panel on explore maouse in
    //
    _expandMainMenuPanel: function(e) {
//console.log(' _expandMainMenuPanel ' );
        clearTimeout(this._myTimeoutId);
        this._myTimeoutId = 0;
        var that = this;
        this._mouseout_SectionMenu(e);
        this.divMainMenu.effect('size',  { to: { width: 200 } }, 500,
                function(){
                    that.divMainMenu.find('.ui-heurist-header2').css({'text-align':'left'});
                    that.divMainMenu.find('.menu-text').show('fade',300);
                });
    },
    
    //
    // leave only active section
    //    
    _mouseout_SectionMenu: function(e) {
        
        var that = this;
        this._myTimeoutId2 = setTimeout(function() {
        
            var section_name = that._getSectionName(e);
    //console.log(' out '+section_name );        
            if(that._active_section!=section_name )
            {
                $.each(that.sections, function(i, section){
                    if(that._active_section!=section){
                        that.menues[section].css('z-index',0).hide(); 
                    }
                });
            }
        },300);
    },
    
    //
    // show section menu on mouse over
    //
    _mousein_SectionMenu: function(e) {

        clearTimeout(this._myTimeoutId2);
        this._myTimeoutId2 = 0;
        
        var section_name = this._getSectionName(e);
//console.log(' in '+section_name );        
        if(this._active_section!=section_name ){
            //hide all except active
            var that = this;
            $.each(this.sections, function(i, section){
                if(section_name==section){
                    if(section!=='explore')
                        that.menues[section].css('z-index',2).show(); 
                }else if(that._active_section!=section){
                    that.menues[section].css('z-index',0).hide(); 
                }
            });
            
        }
        
    },
    
    //
    //
    //
    _getSectionName: function(e){
        var that = this;
        var section_name = null;
        if(e){
            var ele;
            if($(e.target).hasClass('ui-heurist-header') || $(e.target).hasClass('ui-heurist-header2')){
                ele = $(e.target);
            }else{
                ele = $(e.target).parents('.ui-heurist-header2');
                if(ele.length==0){
                    ele = $(e.target).parents('.ui-heurist-header');
                }
            }
            if(ele.length>0){
            $.each(this.sections, function(i, section){
                if(ele.hasClass('ui-heurist-'+section)){
                    section_name = section;
                    return false; //exit loop
                }
            });
            }
        }
        
        return section_name;
    },
    
    
    //
    // opens section menu permanently and switches container 
    //
    _openSectionMenu: function(e){
        
        var section = this._getSectionName(e);
        this._switchSection2( section );
    },
    
    //
    // loads content of section from mainMenu6_section.html
    //
    _loadSectionMenu: function( section ){
        
        this.menues[section] = $('<div>')
            .addClass('ui-menu6-section ui-heurist-'+section)
            .css({width:'200px'})
            .appendTo( this.element );
            
        this.containers[section] = $('<div>')
            .addClass('ui-menu6-container ui-heurist-'+section)
            .appendTo( this.element );
            
            
        this._on(this.menues[section],{
            mouseover: function(){
                clearTimeout(this._myTimeoutId2);
                this._myTimeoutId2 = 0;
            },
            mouseleave: this._mouseout_SectionMenu
        });
                    
        if(section=='explore'){
            //@todo move to init of this widget
            this.containers[section]
                .css({left:'96px'})
                .show();

            window.hWin.HAPI4.LayoutMgr.appInitAll('SearchAnalyze2', this.containers[section] );
        }else{
            var that = this;
            this.menues[section].load(
                window.hWin.HAPI4.baseURL+'hclient/widgets/dropdownmenus/mainMenu6_'+section+'.html',
                function(){ 
                    that._initSectionMenu(section);
                });
        }
        
    },
    
    //
    // finds menu actions and assigns icon and title 
    // source of all actions in mainMenu widget 
    // see mainMenuXXX.html snippets for descriptions of actions
    //
    _initSectionMenu: function( section ){
        
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
            this._switchSection2(section, true);
            widget.mainMenu('menuActionById', li.attr('data-action'), this.containers[section]); 
        }});
        
    },
    
    //
    // switch section on section menu click
    //
    _switchSection2: function( section, force_show ){

        var that = this;
        if(that._active_section!=section ){
            
            if(that._active_section && that.menues[that._active_section])
            {
                that.containers[that._active_section].css('z-index',0).hide();
                that.menues[that._active_section].hide();
                that.element.removeClass('ui-heurist-'+that._active_section+'-fade');
            }
            
            that._active_section = section;

            //show menu and section 
            if(section != 'explore') {
                that.menues[section].css('z-index',1).show();
            }
            if(force_show || !that.containers[section].is(':empty')){
                that.containers[section].show();    
            }
            
            //change main background
            this.element.addClass('ui-heurist-'+section+'-fade');    
        }
    },

    //
    // switch section on main menu click
    //
    _switchSection: function( section ){

        var that = this;
        var m_class = 'ui-heurist-explore-fade'; //by default;
        var wasChanged = (that._active_section!=section );

        //hide previous section
        if(wasChanged && that._active_section && that.menues[that._active_section])
        {
            that.containers[that._active_section].hide();
            that.menues[that._active_section].hide();
        }

        that._active_section = section;
        
        if(section!=='explore') {
            if(that.menues[section].is(':visible')){ //close on repeat click
                that.menues[section].hide();        
                that.containers[section].hide();
                
                that._active_section = 'explore';
                wasChanged = true;
            }else{
                that.menues[section].show();
                if(!that.containers[section].is(':empty')) that.containers[section].show();
                
                m_class = 'ui-heurist-'+section+'-fade';
            }        
        }
        
        //change main background
        if(wasChanged){
            $.each(this.sections, function(i, sect){
                that.element.removeClass('ui-heurist-'+sect+'-fade');    
            });
            this.element.addClass(m_class);    
            if(that._active_section=='explore')
                    that.containers['explore'].show();
        }
        
    }
    
});
