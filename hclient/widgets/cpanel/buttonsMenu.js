/**
* buttonsMenu.js - dropdown menu grouped in buttons
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$.widget( "heurist.buttonsMenu", {

    // default options
    options: {
        is_h6style: true,
        menu_class:null,
        
        //if content is not defined here it takes this.element.html()
        menuContent:null, //html snippet with ul/li menu items
        menuContentFile:null, //html snippet file with menu
        
        // callbacks
        manuActionHandler:null
    },
    
    divMainMenuItems:null, //parent UL
    menuBtns:[],
    menuSubs:[],

    // the widget's constructor
    _create: function() {

        let that = this;

        this.element
        .css('font-size', '1.2em')
        // prevent double click to select text
        .disableSelection();
        
        this._initMenu(()=>{
            
            if(that.divMainMenuItems==null){
                return;
            }
            
            //complete intialization 
            that.divMainMenuItems.menu();

            // 'width':'100px', 
            that.divMainMenuItems.find('li').css({'padding':'0 3px 3px','text-align':'center'}); // center, place gap and setting width
            
            that.divMainMenuItems.find('li.autowidth').css('width','auto');

            
            if(that.options.menu_class!=null){
                 that.element.addClass( that.options.menu_class );   
            }else{
                that.divMainMenuItems.find('.ui-menu-item > a').addClass('ui-widget-content');    
            }
           
            that.divMainMenuItems.children('li').children('a').children('.ui-icon-right').css({right: '2px', left:'unset'});

            that._refresh();
        });

    }, //end _create

    _refresh: function() {
        
    },
    
    //
    // custom, widget-specific, cleanup.
    //
    _destroy: function() {
        
        this.menuBtns.forEach((item)=>{
           $(item).remove();
        });
        this.menuSubs.forEach((item)=>{
           $(item).remove();
        });
        this.menuBtns = [];
        this.menuSubs = [];

    },

    //
    //
    //
    _initMenu: function(callback){

        let that = this;
        let myTimeoutId = -1;

        //show hide function
        //show hide functions
        let _hide = function(ele) {
            myTimeoutId = setTimeout(function() {
                $( ele ).hide();
                }, 1);
           
        };

        let _show = function(ele, parent) {
            clearTimeout(myTimeoutId);

            $('.menu-or-popup').hide(); //hide other
            let menu = $( ele )
            //.css('width', this.btn_user.width())
            .show()
            .position({my: "left top", at: "left bottom", of: parent, collision:'none' });
           

            return false;
        };

        if(this.options.menuContentFile){

            $.get(window.hWin.HAPI4.baseURL+this.options.menuContentFile,
                function(response){
                    that.options.menuContent = response;
                    that._initMenu(callback);
            });
            return;
        }

        let top_levels;
        
        if(this.options.menuContent){
            top_levels = $(this.options.menuContent).find('ul'); //find top level 
        }else{
            top_levels = this.element.find('ul'); //find top levels 
        }
        
            let usr_exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);

            if(top_levels.length==0){
                //@todo error
                console.log('menu content is not defined');
                return;
            }
            
            this.element.empty();
            this.divMainMenuItems = $('<ul>').addClass('horizontalmenu').appendTo(this.element);

            for(let i=0; i<top_levels.length; i++){

                //init top level buttons
                const top_level = $(top_levels[i]);
                let menuID = top_level.attr('id');
                menuID = menuID?`data-action="${menuID}"`:'';
                const menuName =  window.hWin.HR(top_level.attr('title'));
                let menuCss =  top_level.attr('style');
                menuCss = menuCss?` style="${menuCss}"`:'';
                let linkCss =  top_level.attr('link-style');
                if(!linkCss) {linkCss = '';}
                let menuLabel =  window.hWin.HR(top_level.attr('data-label'));
                if(!menuLabel) {menuLabel = menuName;}
                const menuTitle =  window.hWin.HR(top_level.attr('title'));
                const competency_level =  window.hWin.HR(top_level.attr('data-competency'));


                let right_padding = '2px';
                let icon_left = top_level.attr('data-icon-left');
                if(icon_left){
                    icon_left = `<span class="ui-icon ${icon_left}"></span>`;
                    right_padding = '22px';
                }else{
                    icon_left = '';
                }
                
                let icon_righ = top_level.attr('data-icon');
                if(!icon_righ){
                    icon_righ = 'ui-icon-carat-d';    
                }
                icon_righ = (icon_righ!='none')?`<span class="ui-icon-right ui-icon ${icon_righ}"></span>`:'';

                let link = $(`<a ${menuID} href="#" style="padding:2px 22px 2px ${right_padding} !important;${linkCss}" title="${menuTitle}">${icon_left}<span>${menuLabel}</span>${icon_righ}</a>`);
                
                
                this.menuBtns[menuName] = $('<li'+menuCss+'>').append(link).appendTo( this.divMainMenuItems ); //adds to ul

                /*
                if(false && competency_level>=0){
                    this.menuBtns[menuName].addClass('heurist-competency'+competency_level);    
                    if(usr_exp_level>competency_level){
                        this.menuBtns[menuName].hide();    
                    }
                }*/
                
                let submenu = top_level.find('li');
                
                if(submenu.length==0){ //without children
                    
                    this._on( this.menuBtns[menuName], {
                        click : this.menuActionHandler });
                    this.menuBtns[menuName].addClass('autowidth');
                    
                }else{
                    
                    $.each(submenu, this._initActionItem);

                    this.menuSubs[menuName] = top_level.hide();

                    this.menuSubs[menuName].addClass('menu-or-popup')
                    .css('position','absolute')
                    .appendTo( that.document.find('body') )
                    //.addClass('ui-menu-divider-heurist')
                    .menu({
                        icons: { submenu: "ui-icon-circle-triangle-e" },
                        select: function(event, ui){ that.menuActionHandler(event, ui) } });

                    /* not tested
                    if(window.hWin.HAPI4.has_access()){
                        this.menuSubs[menuName].find('.logged-in-only').show();
                    }else{
                        this.menuSubs[menuName].find('.logged-in-only').hide();
                    }

                    this.menuSubs[menuName].find('li[data-user-experience-level]').each(function(){
                        if(usr_exp_level > $(this).data('exp-level')){  //data-competency
                            $(this).hide();    
                        }else{
                            $(this).show();    
                        }
                    });

                    //localization                
                    this.menuSubs[menuName].find('li[id^="menu-"]').each(function(){
                        let menu_id = $(this).attr('id');
                        let item = $(this).find('a');
                        const label = window.hWin.HR( menu_id );
                        if(label!=menu_id) item.text(label);
                        const hint = window.hWin.HR( menu_id+'-hint');
                        if(hint!=(menu_id+'-hint')){
                            item.attr('title',hint);    
                        }
                    });
                    */

                    this.menuSubs[menuName].find('li').css('padding-left',0);

                    this._on( this.menuBtns[menuName], {
                        mouseenter : function(){_show(this.menuSubs[menuName], this.menuBtns[menuName])},
                        mouseleave : function(){_hide(this.menuSubs[menuName])}
                    });
                    this._on( this.menuSubs[menuName], {
                        mouseenter : function(){_show(this.menuSubs[menuName], this.menuBtns[menuName])},
                        mouseleave : function(){_hide(this.menuSubs[menuName])}
                    });
                }
            }//for
            
            callback.call(); //init completed

    },

    //
    // callback function
    //
    menuActionHandler: function(event, ui) {

        event.preventDefault(); 
        let ele;
        
        if(ui?.item){
            ele = ui.item;
        }else{
            ele = $(event.target);
            if(ele.is('span')){
                ele = ele.parent();
            }
        }
        
        let action_id = ele.attr('data-action');
        if(!action_id){
            action_id = ele.attr('id');
        }
        if(this.options.manuActionHandler){
            this.options.manuActionHandler.call(this, action_id);
        }
        
        return false; 
    },
    
    //
    //
    //
    _initActionItem: function(idx, item){
                        
        item = $(item);
        let action_id = item.attr('data-action');
        
        if( !action_id ){
            return
        }
            
        let action = window.hWin.HAPI4.actionHandler.findActionById(action_id);
            
        if(!action){
            return;   
        }
                
        let action_icon = action.data?.icon?action.data.icon:'';

        let action_label = window.hWin.HR( action_id ); 
        if(!action_label){ //localized version not found
            action_label = action.text;
        }
        let action_hint = window.hWin.HR( action_id+'-hint' ); 
        if(!action_hint){ //localized version not foind
            action_hint = action.title;
        }

        $(`<a data-action="${action_id}" href="#" title="${action_hint}">${action_label}</a>`)
                .appendTo(item);
    }
    
});
