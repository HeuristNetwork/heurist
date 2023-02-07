/**
* mapPublish.js - publish map dialogue
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
if((typeof L !=='undefined') && L.Control)
{
    
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
        
        //this.mapPublish = new hMapPublish({container: $(container), mapwidget:this._mapwidget});
        
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
        
       window.hWin.HEURIST4.ui.showPublishDialog( {mode:'mapquery', mapwidget:this._mapwidget} );
       //this.mapPublish.openPublishDialog();
        
       
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
        
        window.hWin.HEURIST4.ui.initHelper({ button:container,
                url:window.hWin.HAPI4.baseURL+'context_help/mapping_overview.html #content',
                position:{ my: "center center", at: "center center", 
                of: $(window.parent.document) }, no_init:true} ); 
        
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

}
        
        