/**
* mapPublish.js - publish map dialogue
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

L.Control.Publish = L.Control.extend({
    
    mapPublish: null,
    _container: null,
    _mapwidget: null,
    
    initialize: function(options) {

        options = options || {};
        
        L.Util.setOptions(this, options);
        
        this._mapwidget = options.mapwidget;
        
        L.Control.prototype.initialize.call(this, this.options);
    },

    
    onAdd: function(map) {
        
        var container = this._container = L.DomUtil.create('div','leaflet-bar');

        L.DomEvent
          .disableClickPropagation(container)
          .disableScrollPropagation(container);
        
        this.mapPublish = new hMapPublish({container: $(container), mapwidget:this._mapwidget});
        
        $('<a>').attr('title', window.hWin.HR('Publish Map'))
            .css({'width':'22px','height':'22px','border-radius': '2px','cursor':'pointer','margin':'0.1px'})
            .addClass('ui-icon ui-icon-globe')
            .appendTo(container);
        
        L.DomEvent
            .on(container, 'click', this._onClick, this);
        
        return container;
    },

    onRemove: function(map) {
        // Nothing to do here
    },
    
    _onClick: function(map) {
        
       this.mapPublish.openPublishDialog();
        
       
    }
});

L.control.publish = function(opts) {
    return new L.Control.Publish(opts);
}

//HELP control

L.Control.Help = L.Control.extend({
    
    _container: null,
    _mapwidget: null,
    
    initialize: function(options) {

        options = options || {};
        
        L.Util.setOptions(this, options);
        
        this._mapwidget = options.mapwidget;
        
        L.Control.prototype.initialize.call(this, this.options);
    },

    
    onAdd: function(map) {
        
        var container = this._container = L.DomUtil.create('div','leaflet-bar');

        L.DomEvent
          .disableClickPropagation(container)
          .disableScrollPropagation(container);
        
        $('<a>').attr('title', window.hWin.HR('Help'))
            .css({'width':'22px','height':'22px','border-radius': '2px','cursor':'pointer','margin':'0.1px'})
            .addClass('ui-icon ui-icon-help')
            .appendTo(container);
        
        window.hWin.HEURIST4.ui.initHelper(container, null, 
                window.hWin.HAPI4.baseURL+'context_help/mapping_overview.html #content',
                { my: "center center", at: "center center", of: $(window.hWin.document).find('body') },true);
        
        /*L.DomEvent
            .on(container, 'click', this._onClick, this);*/
        
        return container;
    },

    onRemove: function(map) {
        // Nothing to do here
    },
    
    _onClick: function(map) {
       //show help popup 
       //this.mapPublish.openPublishDialog();
    }
});

L.control.help = function(opts) {
    return new L.Control.Help(opts);
}


//        
// create accordion with 3(4) panes
// load prededifned list of base layers
// call refresh map documents
//        
//$.widget( "heurist.mapmanager", {
    
function hMapPublish( _options )
{    
    var _className = "MapPublish",
    _version   = "0.4",
    options = {
        //container:null,
        //mapwidget:null,   
    },
    popupelement = null,
    popupdialog = null;
        
    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    function _init ( _options ) {
        options = $.extend(options, _options);
        
        
        /* OLD
        var ele = $('<div>').appendTo(options.container);
        //create div
        ele.load(window.hWin.HAPI4.baseURL+'viewers/map/mapPublish.html?t'
            +window.hWin.HEURIST4.util.random(), 
        function(response, status, xhr){
            if ( status == "error" ) {
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }else{
                _initControls();
            }
        });
        */
    }
    
    function _initControls(){
        
        //popupelement = options.container.find('#map-embed-dialog');
        popupelement = popupdialog.find('#map-embed-dialog');
        
        popupelement.find('input[type="checkbox"]').on({change:function(){
            _fillUrls();
        }});
        
        var $select = popupelement.find('#map_template');
        
        window.hWin.HEURIST4.ui.createTemplateSelector( $select, [{key:'',title:'na'}] );
        $select.on({change:function(){
            _fillUrls();
        }});        
        
        popupelement = popupelement[0];
    }

    function _fillUrls(){

        var hquery = options.mapwidget.current_query_layer['original_heurist_query'];
        var base_url = window.hWin.HAPI4.baseURL+'viewers/map/map_leaflet.php';
        var params_search,params_search_encoded;
        var layout_params = {};
        
        if($(popupelement).find("#m_query").is(':checked')){
            params_search = window.hWin.HEURIST4.util.composeHeuristQuery2(hquery, false);
            params_search_encoded = window.hWin.HEURIST4.util.composeHeuristQuery2(hquery, true);
        }else{
            params_search = '?';
            params_search_encoded = '?';
        }
        params_search_encoded = params_search_encoded + (params_search=='?'?'':'&')+'db='+window.hWin.HAPI4.database;
        params_search = params_search + (params_search=='?'?'':'&')+'db='+window.hWin.HAPI4.database;
        
        
        if($(popupelement).find("#m_mapdocs").is(':checked')){
            var mapdocs = options.mapwidget.getMapDocuments('visible');
            if(mapdocs.length>0){
                layout_params['mapdocument'] = mapdocs.join(',');
            }
        }
        
        //parameters for controls
        layout_params['notimeline'] = !$(popupelement).find("#use_timeline").is(':checked');
        layout_params['nocluster'] = !$(popupelement).find("#use_cluster").is(':checked');
        layout_params['editstyle'] = $(popupelement).find("#editstyle").is(':checked');
        //layout_params['extent'] =  @todo
        if($(popupelement).find("#basemap").is(':checked') && options.mapwidget.basemaplayer_name!='MapBox'){//MapBox is default
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

        //URL
        $(popupelement).find("#code-textbox3").val(url); 

        //readable code        
        $(popupelement).find("#code-textbox").val('<iframe src=\'' + url +
            '\' width="800" height="650" frameborder="0"></iframe>');

        //web safe - encoded
        $(popupelement).find("#code-textbox2").val('<iframe src=\'' + url_enc +
        '\' width="800" height="650" frameborder="0"></iframe>');
    }
    

    //public members
    var that = {
        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},


        openPublishDialog: function(){
        
            popupdialog = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL+'viewers/map/mapPublish.html?t'
                +window.hWin.HEURIST4.util.random(), 
                    null, window.hWin.HR('Publish Map'), 
            {  container:'map-publish-popup',
               height: 680,
               width: 700,
               close: function(){
                    popupdialog.dialog('destroy');       
                    popupdialog.remove();
                    popupdialog = null;
               },
               open: function(){
                    _initControls();
                    _fillUrls();
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
            
        }

    }

    _init( _options );
    return that;  //returns object
};

        
        