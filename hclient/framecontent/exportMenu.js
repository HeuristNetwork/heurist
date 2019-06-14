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
* @copyright   (C) 2005-2019 University of Sydney
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

function hexportMenu() {
    var _className = "exportMenu",
    _version   = "0.4";

    function _init(){

        _initMenu();        
        
    }

    function _initMenu(){
        
        var parentdiv = $('#menu_container');

        parentdiv.addClass('ui-menu ui-widget ui-widget-content ui-corner-all');
                
        parentdiv.find('li').addClass('ui-menu-item');
                
       _initLinks(parentdiv);
    }
    
    
    //init listeners for auto-popup links
    function _initLinks(menu){

        $('.export-button').each(function(){
            var ele = $(this);
            
            ele.parent().css('padding','5px');
            //find url
            var lnk = ele.parent().find('a').css({'text-decoration':'none','color':'black'});
            var href = lnk.attr('href');
            if(!window.hWin.HEURIST4.util.isempty(href)){
                
                if(href!='#'){
                href = href + (href.indexOf('?')>0?'&':'?') + 'db=' + window.hWin.HAPI4.database;
                    if(lnk.hasClass('h3link')){
                        //h3link class on menus implies location of older (vsn 3) code
                        href = window.hWin.HAPI4.baseURL + href;
                    }
                }
                
                lnk.attr('href', href).click(
                    function(event){
                        var ele = $(event.target);
                        if(ele.is('span')){
                            ele = ele.parent();
                        }
                        var action = ele.attr('data-action');
                        if(action){
                            _menuActionHandler(event, action, ele.attr('data-logaction'));
                            return false;
                        }else{
                            _onPopupLink(event);
                        }
                    }
                );
            }

            ele.button().click(
                    function(event){
                        $(this).parent().find('a').click();
                    });
            
        });

        
       //old way - REMOVE
        menu.find('[name="auto-popup"]').each(function(){
            var ele = $(this);
            var href = ele.attr('href');
            if(!window.hWin.HEURIST4.util.isempty(href)){
                href = href + (href.indexOf('?')>0?'&':'?') + 'db=' + window.hWin.HAPI4.database;

                if(ele.hasClass('h3link')){
                    //h3link class on menus implies location of older (vsn 3) code
                    href = window.hWin.HAPI4.baseURL + href;
                }
                
                ele.attr('href', href).click(
                    function(event){
                        _onPopupLink(event);
                    }
                );

            }
        });

       menu.find('li').click(function(event){
            var action = $(event.target).attr('id');
            if(!action){
                action = $(event.target).parent().attr('id')
            }
            if(action){
               _menuActionHandler(event, action, $(event.target).attr('data-logaction'));
               return false;
            }
      });

        
    }

    
    //
    //
    //    
    function _onPopupLink(event){
        
        var action = $(event.target).attr('id');
        
        var body = $(window.hWin.document).find('body');
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
        
            if(!window.hWin.HEURIST4.util.isnull(window.hWin.HEURIST4.current_query_request)){
                var q = encodeURIComponent(window.hWin.HEURIST4.current_query_request.q);
                if(!window.hWin.HEURIST4.util.isempty(q)) q = '&'+q;
                if(!window.hWin.HEURIST4.util.isempty(window.hWin.HEURIST4.current_query_request.rules)){
                    q = q + '&rules=' + encodeURIComponent(window.hWin.HEURIST4.current_query_request.rules);
                }
                url = url + '&w=all&a=1' + q;
            }
        
        
        
        if (link.hasClass('refresh_structure')) {
               options['afterclose'] = this._refreshLists;
        }

        if(link && link.attr('data-logaction')){
            window.hWin.HAPI4.SystemMgr.user_log(link.attr('data-logaction'));
        }

        if(event.target && $(event.target).attr('data-nologin')!='1'){
            //check if login
            window.hWin.HAPI4.SystemMgr.verify_credentials(function(){window.hWin.HEURIST4.msg.showDialog(url, options);});
        }else{
            window.hWin.HEURIST4.msg.showDialog(url, options);
        }        

        event.preventDefault();
        return false;
    }
    
    //
    // similar in resultListMenu
    //
    function isResultSetEmpty(){
        var recIDs_all = window.hWin.HAPI4.getSelection("all", true);
        if (window.hWin.HEURIST4.util.isempty(recIDs_all)) {
            window.hWin.HEURIST4.msg.showMsgDlg('No results found. '
            +'Please modify search/filter to return at least one result record.');
            return true;
        }else{
            return false;
        }
    }
    
    //
    //
    //
    function _menuActionHandler(event, action, action_log){

        if(action_log){
            window.hWin.HAPI4.SystemMgr.user_log(action_log);
        }
        
        window.hWin.HAPI4.SystemMgr.verify_credentials(
        function(){
        
        if(action == "menu-export-csv"){
        
           
            if(isResultSetEmpty()) return;
            
            //window.hWin.HAPI4.currentRecordsetSelection is assigned in resultListMenu

            window.hWin.HEURIST4.ui.showRecordActionDialog('recordExportCSV');
            
        }else if(action == "menu-export-hml-resultset"){ // Current resultset, including rules-based expansion iof applied
            _exportRecords('hml',true,false,false); // isAll, includeRelated, multifile = separate files
        }else if(action == "menu-export-hml-selected"){ // Currently selected records only
            _exportRecords('hml',false,false,false);
        }else if(action == "menu-export-hml-plusrelated"){ // Current resulteset plus any related records
            _exportRecords('hml',true,true,false);
        }else if(action == "menu-export-hml-multifile"){ // selected + related
            _exportRecords('hml',true,false,true);
        }else if(action == "menu-export-json-multifile"){ 
            _exportRecords('json',true,false,true);  //all, multifile
        }else if(action == "menu-export-geojson-multifile"){ 
            _exportRecords('geojson',true,false,true);  //all, multifile
        }else if(action == "menu-export-gephi"){ 
            _exportRecords('gephi',true,false,false); 
        }else if(action == "menu-export-kml"){
            _exportKML(true);
        }else if(action == "menu-export-rss"){
            _exportFeed('rss');
        }else if(action == "menu-export-atom"){
            _exportFeed('atom');
        }
      
        });
        
        event.preventDefault();
    }    
    
    //
    //
    //
    function _exportRecords(format, isAll, includeRelated, multifile){ // isAll = resultset, false = current selection only

        var q = "",
        layoutString,rtFilter,relFilter,ptrFilter,
        depth = 0;

        if(isAll){

            if(!window.hWin.HEURIST4.util.isnull(window.hWin.HEURIST4.current_query_request)){
                q = encodeURIComponent(window.hWin.HEURIST4.current_query_request.q);
                if(!window.hWin.HEURIST4.util.isempty(window.hWin.HEURIST4.current_query_request.rules)){
                    q = q + '&rules=' + encodeURIComponent(window.hWin.HEURIST4.current_query_request.rules);
                }
            }

        }else{    //selected only

            if (!window.hWin.HEURIST4.util.isArrayNotEmpty(this._selectionRecordIDs)) {
                window.hWin.HEURIST4.msg.showMsgDlg("Please select at least one record to export");
                return false;
            }
            q = "ids:"+this._selectionRecordIDs.join(",");

        }

        if (includeRelated){

            depth = 1;
        }

        if(q!=''){
            
            var script; 
            var params = '';
            if(format=='hml'){
                script = 'export/xml/flathml.php';                
                params =  'depth='+(includeRelated?1:0)
                          + (multifile?'&file=1':'');    
                
            }else{
                script = 'hsapi/controller/record_output.php';
                params = 'format='+format+'&file=0&defs=0&extended='+($('#extendedJSON').is(':checked')?2:1);
            }
            

            var url = window.hWin.HAPI4.baseURL + script + '?' +
            "w=all"+
            "&a=1"+
            "&depth="+depth +
            "&q=" + q +
            /*(layoutString ? "&" + layoutString : "") +
            (selFilter ? "&" + selFilter : "") +
            (rtFilter ? "&" + rtFilter : "") +
            (relFilter ? "&" + relFilter : "") +
            (ptrFilter ? "&" + ptrFilter : "") +*/
            "&db=" + window.hWin.HAPI4.database
            +'&'+params;

            window.open(url, '_blank');
        }

        return false;
    }
     
    function _exportKML(isAll){

        var q = "";
        if(isAll){

            q = window.hWin.HEURIST4.util.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, false);

            if(q=='?'){
                window.hWin.HEURIST4.msg.showMsgDlg("Define filter and apply to database");
                return;
            }


        }else{

            if (!window.hWin.HEURIST4.util.isArrayNotEmpty(this._selectionRecordIDs)) {
                window.hWin.HEURIST4.msg.showMsgDlg("Please select at least one record to export");
                return false;
            }
            q = "?w=all&q=ids:"+this._selectionRecordIDs.join(",");
        }

        if(q!=''){
            var url = window.hWin.HAPI4.baseURL + "export/xml/kml.php" + q + "&a=1&depth=1&db=" + window.hWin.HAPI4.database;
            window.open(url, '_blank');
        }

        return false;
    }

    function _exportFeed(mode){

        if(!window.hWin.HEURIST4.util.isnull(window.hWin.HEURIST4.current_query_request)){
            var q = encodeURIComponent(window.hWin.HEURIST4.current_query_request.q);

            if(!window.hWin.HEURIST4.util.isempty(q)){
                var w = window.hWin.HEURIST4.current_query_request.w;
                if(window.hWin.HEURIST4.util.isempty(w)) w = 'a';
                if(mode=='rss') {
                    mode = '';
                }else{
                    mode = '&feed='+mode;
                }
                var rules = '';
                if(!window.hWin.HEURIST4.util.isempty(window.hWin.HEURIST4.current_query_request.rules)){
                    rules = '&rules=' + encodeURIComponent(window.hWin.HEURIST4.current_query_request.rules);
                }


                var url = window.hWin.HAPI4.baseURL + 'export/xml/feed.php?&q=' + q + '&w=' + w + '&db=' + window.hWin.HAPI4.database + mode + rules;
                window.hWin.open(url, '_blank');
            }
        }
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
    