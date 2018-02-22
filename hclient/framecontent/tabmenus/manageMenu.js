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

function hmanageMenu() {
    var _className = "manageMenu",
    _version   = "0.4";

    //
    // show db structure visualization in popup dialog
    //
    function _showDbSummary(){

        var url = window.hWin.HAPI4.baseURL+ "hclient/framecontent/visualize/databaseSummary.php?popup=1&db=" + window.hWin.HAPI4.database;

        var body = $(top.document).find('body');
        var dim = {h:body.innerHeight(), w:body.innerWidth()};

        window.hWin.HEURIST4.msg.showDialog(url, { height:dim.h*0.8, width:dim.w*0.8, title:'Database Summary',} );

    }
    
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
            $('<div class="svs-contextmenu ui-icon ui-icon-arrowthick-1-e"></div>').appendTo($(item));
        });


        _initLinks(parentdiv);

        //$('#frame_container').attr('src', window.hWin.HAPI4.baseURL+'admin/structure/rectypes/manageRectypes.php?db='+window.hWin.HAPI4.database);           }
        // defaults to open the record type editing (config/build structure) when Manage tab is selected
        $(parentdiv[1]).accordion('option', 'active', 0); //STRUCTURE
        $('#linkEditRectypes').click();
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
        
        $('#menulink-database-refresh').click(
            function(event){
                window.hWin.HAPI4.SystemMgr.get_defs_all( true, top.document);
                event.preventDefault();
                return false;
            }
        );
        
        $('#menulink-database-browse').click(
            function(event){
                
                window.hWin.HEURIST4.ui.showEntityDialog('sysDatabases', {
                    select_mode:'select_single',
                    //isdialog: false,
                    //container: ,
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
                event.preventDefault();
                return false;
            }        
        );

        $('#menulink-database-new').click(
        
        );

        $('#menulink-database-register').click(
        
        );
        
        
/*
        $('#menulink-database-admin').click( //.attr('href', 
            function(event){
                window.hWin.open(window.hWin.HAPI4.baseURL+'admin/adminMenuStandalone.php?db='+window.hWin.HAPI4.database, '_blank');
                event.preventDefault();
                return false;
            }
        );
*/        
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
        var frm = $('#frame_container');
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
    