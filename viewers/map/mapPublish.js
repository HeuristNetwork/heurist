/**
 * mapPublish.js - Defines Leaflet controls for publishing a map and accessing help.
 *
 * @fileOverview This script creates custom Leaflet controls: one for initiating the
 * "Publish Map" dialog and another for displaying help content related to mapping.
 * These controls are intended to be added to a Leaflet map instance.
 * @project     Heurist academic knowledge management system
 *
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov <osmakov@gmail.com>
 * @author      Ian Johnson ian.johnson.heurist@gmail.com
 * @since       4
 */

/* global L */

if((typeof L !=='undefined') && L.Control)
{
    /**
     * Common initialization logic for custom Leaflet controls.
     * Sets options and stores a reference to the map widget.
     * @this L.Control
     * @param {object} options - Options for the control.
     * @param {object} options.mapwidget - Reference to the main mapping widget.
     */
    function commonCodeInit(options)
    {
            options = options || {};
            L.Util.setOptions(this, options);
            this._mapwidget = options.mapwidget;
            L.Control.prototype.initialize.call(this, this.options);
    }

    /**
     * Common logic for creating the control's button element.
     * @this L.Control
     * @param {string} label - The title/tooltip for the button.
     * @param {string} icon - The CSS class for the button's icon (e.g., 'ui-icon-globe').
     * @returns {HTMLElement} The container element for the control button.
     */
    function commonOnAdd(label, icon){
            let container = this._container = L.DomUtil.create('div','leaflet-bar');

            L.DomEvent
              .disableClickPropagation(container)
              .disableScrollPropagation(container);

            $('<a>').attr('title', window.hWin.HR(label))
                .css({'width':'22px','height':'22px','border-radius': '2px','cursor':'pointer','margin':'0.1px'})
                .addClass('ui-icon '+icon)
                .appendTo(container);
            return container; // Added return statement
    }

    /**
     * Leaflet Control for publishing the current map view/query.
     * When clicked, it opens the Heurist publish dialog.
     * @class L.Control.Publish
     * @augments L.Control
     */
    L.Control.Publish = L.Control.extend({

        mapPublish: null,
        _container: null,
        _mapwidget: null,

        /**
         * Initializes the control.
         * @param {object} options - Control options.
         * @param {object} options.mapwidget - Reference to the main mapping widget.
         */
        initialize: commonCodeInit,

        /**
         * Called when the control is added to the map. Creates the button.
         * @param {L.Map} map - The Leaflet map instance.
         * @returns {HTMLElement} The control's container element.
         */
        onAdd: function(map) {
            this._container = commonOnAdd.call(this, 'Publish Map', 'ui-icon-globe'); // Added this._container assignment
            L.DomEvent
                .on(this._container, 'click', this._onClick, this);
            return this._container;
        },

        /**
         * Called when the control is removed from the map.
         * @param {L.Map} map - The Leaflet map instance.
         */
        onRemove: function(map) {
            // Nothing to do here
        },

        /**
         * Handles the click event on the publish button.
         * Opens the Heurist publish dialog.
         * @param {L.Map} map - The Leaflet map instance (passed by Leaflet, not directly used here).
         * @private
         */
        _onClick: function(map) {
           window.hWin.HEURIST4.ui.showPublishDialog( {mode:'mapquery', mapwidget:this._mapwidget} );
        }
    });

    /**
     * Factory function for creating L.Control.Publish instances.
     * @param {object} opts - Options for the L.Control.Publish control.
     * @returns {L.Control.Publish} A new Publish control instance.
     */
    L.control.publish = function(opts) {
        return new L.Control.Publish(opts);
    }

    /**
     * Leaflet Control for displaying help information related to the map.
     * @class L.Control.Help
     * @augments L.Control
     */
    L.Control.Help = L.Control.extend({
        /* The control's container element. */
        _container: null,
        /* Reference to the main mapping widget. */
        _mapwidget: null,

        /**
         * Initializes the control.
         * @param {object} options - Control options.
         * @param {object} options.mapwidget - Reference to the main mapping widget.
         */
        initialize: commonCodeInit,

        /**
         * Called when the control is added to the map. Creates the button and initializes the help functionality.
         * @param {L.Map} map - The Leaflet map instance.
         * @returns {HTMLElement} The control's container element.
         */
        onAdd: function(map) {
            this._container = commonOnAdd.call(this, 'Help', 'ui-icon-help'); // Added this._container assignment
            window.hWin.HEURIST4.ui.initHelper({ button:this._container,
                    url: window.hWin.HRes('mapping_overview #content'),
                    position:{ my: "center center", at: "center center",
                    of: $(window.parent.document) }, no_init:true} );
            return this._container;
        },

        /**
         * Called when the control is removed from the map.
         * @param {L.Map} map - The Leaflet map instance.
         */
        onRemove: function(map) {
            // Nothing to do here
        },

        /**
         * Handles the click event on the help button (currently does nothing as help is initialized on add).
         * @param {L.Map} map - The Leaflet map instance.
         * @private
         */
        _onClick: function(map) {
           //show help popup
           //this.mapPublish.openPublishDialog();
        }
    });

    /**
     * Factory function for creating L.Control.Help instances.
     * @param {object} opts - Options for the L.Control.Help control.
     * @returns {L.Control.Help} A new Help control instance.
     */
    L.control.help = function(opts) {
        return new L.Control.Help(opts);
    }
}
        
        