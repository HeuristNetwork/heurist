/**
* Class to search and select record 
* It can be converted to widget later?
* 
* @param rectype_set - allowed record types for search, otherwise all rectypes will be used
* @returns {Object}
* @see editing_input.js
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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

function haddDataMenu() {
    var _className = "addDataMenu",
    _version   = "0.4";

    function _init(){

        var parentdiv = $('.accordion_pnl').accordion({
            heightStyle: "content",
            collapsible: true, 
            active: false,
            icons:{"header": "ui-icon-carat-1-e", "activeHeader": "ui-icon-carat-1-s" }
        });
        
        parentdiv.find('h3').addClass('ui-heurist-header2-fade');
        

        parentdiv.find('div')
        .addClass('menu-list')
        .css('width','100%');
        
        $( parentdiv ).find('h3')
        .css({border:'none', 'background':'none'});

        parentdiv.find('li').addClass('ui-menu-item').css('background','#99ABBA !important');

        parentdiv.find('li').each(function(idx,item){
            $('<div class="svs-contextmenu ui-icon ui-icon-arrowthick-1-e"></div>').appendTo($(item));
        });

        _initLinks(parentdiv);

        $(parentdiv[0]).accordion('option', 'active', 0); //KEYBOARD
        $('#menulink-add-record').click();

    }
    
    
    //init listeners for auto-popup links
    function _initLinks(menu){

        menu.find('[name="auto-popup"]').each(function(){
            var ele = $(this);
            var href = ele.attr('href');
            if(!window.hWin.HEURIST4.util.isempty(href)){
                href = href + (href.indexOf('?')>0?'&':'?') + 'db=' + window.hWin.HAPI4.database;

                if(ele.hasClass('h3link')){
                    href = window.hWin.HAPI4.baseURL + href;
                    //h3link class on menus implies location of older (vsn 3) code
                }
                
                ele.attr('href', href).click(
                    function(event){
                        _onPopupLink(event);
                    }
                );

            }
        });

        menu.find('li').each( function (idx, item)
            {
                if($(item).text()=='-'){
                   $(item).addClass('ui-menu-divider');
                }
            });

        menu.find('.top-menu-only').hide();
        
        
        menu.find('#menu-import-csv').click(
            function(event){
                window.hWin.HAPI4.SystemMgr.user_log('impDelim');
                
                window.hWin.HAPI4.SystemMgr.verify_credentials(
                function(){
                   var url = window.hWin.HAPI4.baseURL + "hclient/framecontent/import/importRecordsCSV.php?db="+ window.hWin.HAPI4.database;
                   
                   var body = $(this.document).find('body');
                   var dim = {h:body.innerHeight(), w:body.innerWidth()};
                   
                   window.hWin.HEURIST4.msg.showDialog(url, {    
                        title: 'Import Records from CSV/TSV',
                        height: dim.h-5,
                        width: dim.w-5,
                        'context_help':window.hWin.HAPI4.baseURL+'context_help/importRecordsCSV.html #content'
                        //callback: _import_complete
                    });
                
                event.preventDefault();
                return false;
                })
            }
        );
        
        
        $('#menulink-add-record').click( //.attr('href', 
            function(event){
/*                
                    var url = window.hWin.HAPI4.baseURL + 'hclient/framecontent/recordAction.php?db='
                            +window.hWin.HAPI4.database
                            +'&action=add_record';

                    window.hWin.HEURIST4.msg.showDialog(url, {height:500, width:600,
                        padding: '0px',
                        resizable:false,
                        title: window.hWin.HR('ownership'),
                        callback: function(context){

                            if(context && context.owner && context.access){
                            }
                            
                        } } );                    //, class:'ui-heurist-bg-light'
*/                
            }
        );
        
    }

    
    //
    //
    //    
    function _onPopupLink(event){
        
        var action = $(event.target).attr('id');
        
        var body = $(this.document).find('body');
        var dim = {h:body.innerHeight   (), w:body.innerWidth()},
        link = $(event.target);

        var options = { title: link.html() };

        if (link.hasClass('small')){
            options.height=dim.h*0.6; options.width=dim.w*0.5;
        }else if (link.hasClass('portrait')){
            options.height=dim.h*0.8; options.width=dim.w*0.55;
            if(options.width<700) options.width = 700;
        }else if (link.hasClass('large')){
            options.height=dim.h*0.8; options.width=dim.w*0.8;
        }else if (link.hasClass('verylarge')){
            options.height = dim.h*0.95;
            options.width  = dim.w*0.95;
        }else if (link.hasClass('fixed')){
            options.height=dim.h*0.8; options.width=800;
        }else if (link.hasClass('fixed2')){
            if(dim.h>700){ options.height=dim.h*0.8;}
            else { options.height=dim.h-40; }
            options.width=800;
        }else if (link.hasClass('landscape')){
            options.height=dim.h*0.5;
            options.width=dim.w*0.8;
        }

        var url = link.attr('href');
        if (link.hasClass('currentquery')) {
            url = url + that._current_query_string
        }
        
        if(link && link.attr('data-logaction')){
            window.hWin.HAPI4.SystemMgr.user_log(link.attr('data-logaction'));
        }
        
        
        if (link.hasClass('refresh_structure')) {
               options['afterclose'] = this._refreshLists;
        }


        if(link.attr('id')=='menulink-add-record'){
            
            window.hWin.HAPI4.SystemMgr.verify_credentials(function(){    
                $('.accordion_pnl').find('a').parent().removeClass('item-selected');
                link.parent().addClass('item-selected');
                
                url = window.hWin.HAPI4.baseURL + 'hclient/framecontent/recordAction.php?db='
                            +window.hWin.HAPI4.database
                            +'&action=add_record';
                __load_frame_content(url);
            });
            
            event.preventDefault();
            return false;
                
        }else if(link.hasClass('embed')) {
        
            //check if login
            window.hWin.HAPI4.SystemMgr.verify_credentials(function(){
                $('.accordion_pnl').find('a').parent().removeClass('item-selected');
                link.parent().addClass('item-selected');
                __load_frame_content(url);
                event.preventDefault();
                return false;
            });
                
                
        }else if(event.target && $(event.target).attr('data-nologin')!='1'){
            //check if login
            window.hWin.HAPI4.SystemMgr.verify_credentials(function(){window.hWin.HEURIST4.msg.showDialog(url, options);});
        }else{
            window.hWin.HEURIST4.msg.showDialog(url, options);
        }        

        return false;
    }
    
    function __load_frame_content(url){
        var frm = $('#frame_container2');
        frm.hide();
        frm.parent().css('background','url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center');
        frm.on('load', function(){
            frm.show();
            frm.css('background','none');
        });
        frm.attr('src', url)
    }    
    
    
    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},
        doAction: function(link_id){
            $('#'+link_id).click();
        }
    }

    _init();
    return that;  //returns object
}
    
            
   
