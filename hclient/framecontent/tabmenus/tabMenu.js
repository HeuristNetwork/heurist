/**
* Menu with accordion for tab menues
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2018 University of Sydney
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

function hTabMenu() {
    var _className = "TabMenu",
    _version   = "0.4";

   
    //
    //
    //
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
        //.css({'background':'none'});   // ui-corner-all
        
        $( parentdiv ).find('h3')
        .css({border:'none', 'background':'none'});

        parentdiv.find('li').addClass('ui-menu-item').css('background','#99ABBA !important');

        parentdiv.find('li').each(function(idx,item){
            
            var li_icon = 'ui-icon-popup';
            var $link = $(item).find('a');
            if($link.attr('target')=='_blank'){
                li_icon = 'ui-icon-extlink';
            }else if($link.attr('name')=="auto-popup"){
                li_icon = 'ui-icon-arrowthick-1-e';
            }
            
            $('<div class="svs-contextmenu ui-icon '+li_icon+'"></div>').appendTo($(item));
        });

        _initLinks(parentdiv);

        // defaults to open the record type editing (config/build structure) when Manage tab is selected
        $(parentdiv[1]).accordion('option', 'active', 0); //STRUCTURE
        $('a.active-link').click();
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
        
        
        $('#menulink-mimetypes').click(
            function(event){
        
                window.hWin.HEURIST4.ui.showEntityDialog('defFileExtToMimetype',
                        {edit_mode:'inline', width:900});
                event.preventDefault();
                return false;
            }        
        );
        
        
        $('#menulink-database-refresh').click(
            function(event){
                window.hWin.HAPI4.EntityMgr.emptyEntityData(null); //reset all cached data for entities
                window.hWin.HAPI4.SystemMgr.get_defs_all( true, top.document);
                event.preventDefault();
                return false;
            }
        );
        
        $('#menulink-database-browse').click(
            function(event){
                
                $('#frame_container_div').css('top',20).empty();
                window.hWin.HEURIST4.ui.showEntityDialog('sysDatabases', {
                    select_mode:'select_single',
                    isdialog: false,
                    container: $('#frame_container_div'),
                    onselect:function(event, data){

                        if(data && data.selection && data.selection.length==1){
                            var db = data.selection[0];
                            if(db.indexOf('hdb_')===0) db = db.substr(4);
                            window.open( window.hWin.HAPI4.baseURL + '?db=' + db, '_blank');
                        }
                                                
                    }
                });
                event.preventDefault();
                return false;
            }
        );
        
        $('#menulink-database-properties').click(
            function(event){
        
                window.hWin.HEURIST4.ui.showEntityDialog('sysIdentification');
                window.hWin.HEURIST4.util.stopEvent(event);
                //event.preventDefault();
                return false;
            }        
        );

        $('#menulink-database-new').click(
        
        );

        $('#menulink-database-register').click(
        
        );
        
        $('#menulink-database-admin').click(
            function(){
                var tabb = $(window.hWin.document).find('div[layout_id="main_header_tab"]');
                var ele = tabb.find('ul > li');
                ele = $(ele[1]);
                if( ele.is(':visible')){
                    ele.hide();
                }else{
                    ele.show();
                    //$(tabb).tabs({active:1});
                    //$(tabb).tabs('option','active',1);
                    ele.find('a')[0].click();
                }
            }
        );
       
    }
    
    
    function _onPopupLink(event){
        
        var action = $(event.target).attr('id');
        var link = $(event.target);
        var url = link.attr('href');
        
//console.log($(event.target).attr('id'));        
        if(link && link.attr('data-logaction')){
            window.hWin.HAPI4.SystemMgr.user_log(link.attr('data-logaction'));
        }
        
        if(link && link.attr('data-nologin')!='1'){
            
            //check if login
            window.hWin.HAPI4.SystemMgr.verify_credentials(function(){
                //window.hWin.HEURIST4.msg.showDialog(url, options);
                $('.accordion_pnl').find('a').parent().removeClass('item-selected'); //was #menu_container
                link.parent().addClass('item-selected');
                
                __load_frame_content(url);

            });
        }else{
            //window.hWin.HEURIST4.msg.showDialog(url, options);
            $('.accordion_pnl').find('a').parent().removeClass('item-selected');
            link.parent().addClass('item-selected');
            
            __load_frame_content(url);
        }        
        
        
        return false;
    }
    
    function __load_frame_content(url){
        var frm = $('#frame_container').css('top',0);
        if(frm.length==0){
            $('#frame_container_div').empty();
            frm = $('<iframe id="frame_container"></iframe>').appendTo($('#frame_container_div'));
        }
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

    }

    _init();
    return that;  //returns object
}
    