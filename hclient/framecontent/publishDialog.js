/**
* publishDialog.js - publish/embed dialogue for map, mapspace, savedsearch, smarty, visualization graph
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

function hPublishDialog( _options )
{    
    var _className = "PublishDialog",
    _version   = "0.4",
    options = {
        //container:null,
        mode: null, //mapspace, mapquery, smarty, graph, websearch
        mapwidget:null, 
        mapdocument_id:null  
    },
    popupelement = null,
    popupdialog = null;
    
    function _initControls(){
        
        popupelement = popupdialog.find('#map-embed-dialog');
        
        popupelement.find('.modes').hide();
        
        popupelement.find('.'+options.mode).show();    
        
        if(options.mode=='mapspace' || options.mode=='mapquery')
        {
            popupelement.find('input[type="checkbox"]').on({change:function(){
                _updateUrls();
            }});
            
            var $select = popupelement.find('#map_template');
            
            window.hWin.HEURIST4.ui.createTemplateSelector( $select, [{key:'',title:'Defaut map popup'}] );
            $select.on({change:function(){
                _updateUrls();
            }});  
            
            popupelement.find("#btnExportKML").button().click(_exportKML);
            
            _updateUrls();      
        }else{
            if(options.mode=='smarty'){
                popupelement.find("#btnScheduleSmarty").button().click(_scheduleSmarty);
                popupelement.find("#lbl_mode2").text('javascript wrap');
            }
            
            _fillUrls();      
        }
        
        popupelement.find('input[type="radio"]').on({change:function(event){
            var val = $(event.target).val();
            $(popupelement).find('textarea[id^="code-textbox-"]').hide();
            $(popupelement).find('#code-textbox-'+val).show()
        }});
            
        popupelement = popupelement[0];
        popupdialog.height($(popupelement).height()+15);
    }

    //
    //
    //
    function _fillUrls(){
        //URL
        $(popupelement).find("#code-url").val(options.url); 
        $(popupelement).find("#link-url").attr('href', options.url); 

        
        if(options.mode=='smarty'){

            $(popupelement).find("#code-textbox-embed").val('<iframe src=\'' + options.url +
                '\' width="80%" height="70%" frameborder="0"></iframe>');

            $(popupelement).find("#code-textbox-websafe").val(
                '<script type="text/javascript" src="'+options.url+'&mode=js"><'+'/script>'+
                '<noscript>'+
                '<iframe width="80%" height="70%" frameborder="0" src=\''+options.url+'\'>'+
                '</iframe>'+
                '</noscript>');            
            
        }else{
            
            //readable code        
            $(popupelement).find("#code-textbox-embed").val('<iframe src=\'' + options.url +
                    '\' width="800" height="650" frameborder="0"></iframe>');

            //web safe - encoded
            $(popupelement).find("#code-textbox-websafe").val('<iframe src=\'' + options.url_encoded +
            '\' width="800" height="650" frameborder="0"></iframe>');
            
        }

    }
    
    //
    //
    //
    function _updateUrls(){

        var base_url = window.hWin.HAPI4.baseURL+'viewers/map/map_leaflet.php';
        var params_search,params_search_encoded;
        var layout_params = {};
        
        if(options.mapwidget){
            var hquery = (options.mapwidget)?options.mapwidget.current_query_layer['original_heurist_query']:'';
            
            if($(popupelement).find("#m_query").is(':checked')){
                params_search = window.hWin.HEURIST4.util.composeHeuristQuery2(hquery, false);
                params_search_encoded = window.hWin.HEURIST4.util.composeHeuristQuery2(hquery, true);
            }else{
                params_search = '?';
                params_search_encoded = '?';
            }
        
            if($(popupelement).find("#m_mapdocs").is(':checked')){
                var mapdocs = options.mapwidget.getMapManager().getMapDocumentsIds('visible');
                if(mapdocs.length>0){
                    layout_params['mapdocument'] = mapdocs.join(',');
                }
            }
        }else{
            params_search = '?';
            params_search_encoded = '?';
            
            layout_params['mapdocument'] = options.mapdocument_id;
        }
        params_search_encoded = params_search_encoded + (params_search=='?'?'':'&')+'db='+window.hWin.HAPI4.database;
        params_search = params_search + (params_search=='?'?'':'&')+'db='+window.hWin.HAPI4.database;
        
        //parameters for controls
        layout_params['notimeline'] = !$(popupelement).find("#use_timeline").is(':checked');
        layout_params['nocluster'] = !$(popupelement).find("#use_cluster").is(':checked');
        layout_params['editstyle'] = $(popupelement).find("#editstyle").is(':checked');
        //layout_params['extent'] =  @todo
        if($(popupelement).find("#basemap").is(':checked') && 
                    options.mapwidget && options.mapwidget.basemaplayer_name!='MapBox'){//MapBox is default
            layout_params['basemap'] = options.mapwidget.basemaplayer_name;
        }
        
        var ctrls = [];
        $(popupelement).find('input[name="controls"]:checked').each(
            function(idx,item){ctrls.push($(item).val());}
        );
        if(ctrls.length>0) layout_params['controls'] = ctrls.join(',');
        if(ctrls.indexOf('legend')>=0){
            ctrls = [];
            $(popupelement).find('input[name="legend"]:checked').each(
                function(idx,item){ctrls.push($(item).val());}
            );
            if(ctrls.length>0 && ctrls.length<3) layout_params['legend'] = ctrls.join(',');
        }
        
        if($(popupelement).find('#map_template').val()){
            layout_params['template'] = $(popupelement).find('#map_template').val();
        }
        
        var url     = base_url + params_search;
        var url_enc = base_url + params_search_encoded;
        for(var key in layout_params) {
            if(layout_params.hasOwnProperty(key) && layout_params[key]!==false){
                url = url + '&'+key+'='+(layout_params[key]===true?1:layout_params[key]);
                url_enc = url_enc + '&'+key+'='+(layout_params[key]===true?1:encodeURIComponent(layout_params[key]));
            }
        }
        
        options.url = url;
        options.url_encoded = url_enc;
        
        _fillUrls()

    }
    
    
    //
    //
    //
    function _exportKML(){

        if(options.mapwidget){
            var hquery = (options.mapwidget)?options.mapwidget.current_query_layer['original_heurist_query']:'';
            var query = window.hWin.HEURIST4.util.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, false);
            if(query=='?'){
                window.hWin.HEURIST4.msg.showMsgDlg("Define filter and apply to database");
            }else{
                query = query + '&a=1&depth=1&db='+window.hWin.HAPI4.database;
                var url_kml = window.hWin.HAPI4.baseURL+"export/xml/kml.php" + query;
                var win = window.open(url_kml, "_new");
            }
        }
    }
    
    //
    //
    //
    function _scheduleSmarty(event){
        
        popupdialog.dialog('close');
                        
        $(event.target).off('click');

        var body = $(window.hWin.document).find('body');
        var dim = {h:body.innerHeight(), w:body.innerWidth()};

        window.hWin.HEURIST4.msg.showDialog(options.url_schedule, 
        {   "close-on-blur": false,
            "no-resize": false,
            default_palette_class: 'ui-heurist-publish',
            height: 480,
            width: dim.w*0.9,
            callback: null
        });
        
        
    }
    
    

    //public members
    var that = {
        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},


        openPublishDialog: function( new_options ){
            
            options = new_options; //.mapdocument_id = mapdoc_id;
            
            var sTitle = 'Publish/Embed';
            if(options.mode=='mapspace' || options.mode=='mapquery'){
                sTitle = 'Publish Map';
            }else if(options.mode=='websearch'){
                sTitle = 'Embedding searches';
            }else if(options.mode=='smarty'){
                sTitle = 'Publish report';
            }else if(options.mode=='graph'){
                sTitle = 'Publish Network Diagram';
            }
        
            popupdialog = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
                + 'hclient/framecontent/publishDialog.html?t'
                + window.hWin.HEURIST4.util.random(), 
                    null, window.hWin.HR(sTitle), 
            {  
               container:'embed-publish-popup',
               default_palette_class: 'ui-heurist-publish',
               height: 610, // options.mapdocument_id>0?600:680,
               width: 700,
               close: function(){
                    popupdialog.dialog('destroy');       
                    popupdialog.remove();
                    popupdialog = null;
               },
               open: function(){
                    _initControls();
               }
            });        
        
        /* OLD
            _fillUrls();

            window.hWin.HEURIST4.msg.showElementAsDialog({
                element: popupelement,
                height: 600,
                width: 700,
                title: window.hWin.HR('Publish Map')
            });
        */
            
        },
        
        closePublishDialog: function(){
            if(popupdialog && popupdialog.dialog('instance')){
                popupdialog.dialog('close')
            }
        }    

    }

    return that;  //returns object
};

        
        