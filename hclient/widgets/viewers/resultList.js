/*
 * @file hclient/widgets/viewers/resultList.js
 * @brief A widget that displays a list of records, allowing for different view modes (list, grid, table).
 * @fileOverview
 * This file defines the `heurist.resultList` jQuery UI widget. This widget is responsible for
 * rendering a collection of records in various formats (list, grid, table). It handles features
 * like incremental rendering, view mode switching, selection management, and interaction
 * with a recordset. It also supports pagination and displays messages for empty or loading states.
 *
 * Key functionalities include:
 * - Displaying records in list, grid, or table views.
 * - Incremental rendering of records for performance.
 * - Handling record selection and hover states.
 * - Responding to changes in the underlying recordset.
 * - Providing controls for view mode switching and pagination.
 * - Customizable rendering of record details.
 *
 * @package HeuristClient
 * @subpackage hclient\widgets\viewers
 * @link http://www.heuristscholar.org
 * @copyright Copyright (c) 2009-2020, University of Sydney
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @author Nicolas Padfield <nicolas.padfield@sydney.edu.au>
 * @author Stephen White <stephen.white@sydney.edu.au>
 * @since 2.4
 */

// ResultList widget
//
// TODO: HView.ResultList (based on HView.RecordList) ??
//
// HView.RecordList rewrite, stage 1 (replacement)
// (was js/display/resultList.js)
//
// Differences from HView.RecordList:
// - uses RecordSet, not HRecordList object
// - builds HTML itself, not using HRecordListItem objects
// - simpler, more efficient, esp. for large lists
//
// TODO:
// - save settings
// - improve paging (integrate with RecordSet.seek)
// - accessibility review
// - review options and events (align with other widgets)
// - framework for viewing related items (see prototype in HeuristPlay)
// - framework for editing related items (see prototype in HeuristPlay)

(function($) {

    /**
     * @class heurist.resultList
     * @memberof heurist
     * @augments jQuery.Widget
     * @description A widget that displays a list of records, allowing for different view modes
     * (e.g., list, grid, table). It handles incremental rendering, selection, and interaction
     * with a {@link heurist.RecordSet}.
     *
     * The widget provides various options to customize its appearance and behavior,
     * including how records are displayed, which view modes are available, and how
     * pagination is handled.
     *
     * @example
     * $('#myResultList').resultList({
     *     recordset: myRecordSet,
     *     view_modes: ['list', 'grid'],
     *     default_view_mode: 'grid',
     *     show_details_in_list: true
     * });
     *
     * @param {object} options Configuration options for the widget.
     */
    $.widget( "heurist.resultList", {
        // TODO: "getter" for options (that are not settable after init)
        //       (eg. recordset, show_nav_buttons, show_view_modes, ...) ???
        // TODO: "getter" for internal status (eg. current view_mode, current selection, ...) ???
        // TODO: "visible" option/method ???
        // TODO: "disabled" option/method ???

        /**
         * @typedef {object} heurist.resultList.options
         * @description Options for configuring the resultList widget.
         * @property {heurist.RecordSet} recordset The recordset to display. This is a mandatory option.
         * @property {boolean} [show_nav_buttons=true] If true, navigation buttons (paging) are shown.
         * @property {boolean} [show_view_modes=true] If true, view mode selection buttons are shown.
         * @property {Array<string>} [view_modes=['list','grid','table']]
         *  An array of strings defining the available view modes.
         *  Possible values are 'list', 'grid', and 'table'.
         * @property {string} [default_view_mode='list'] The initial view mode to display.
         *  Must be one of the modes specified in `view_modes`.
         * @property {boolean} [show_checkboxes=true] If true, checkboxes are shown for record selection.
         * @property {boolean} [show_record_type_icon=true] If true, record type icons are displayed.
         * @property {boolean} [show_details_in_list=false]
         *  If true, detailed information is shown for records in 'list' view.
         *  If false, a more compact list view is used.
         * @property {boolean} [show_details_in_grid=true]
         *  If true, detailed information is shown for records in 'grid' view.
         *  If false, a more compact grid view is used.
         * @property {boolean} [show_tooltips=true] If true, tooltips are shown for record titles.
         * @property {boolean} [show_itemNo=true] If true, item numbers are displayed for each record.
         * @property {string} [grid_image_field='image']
         *  The name of the field to use for displaying images in 'grid' view.
         * @property {string} [grid_image_field_fallback='screenshot']
         *  A fallback field for images in 'grid' view if `grid_image_field` is not found or empty.
         * @property {number} [page_size=100] The number of records to display per page.
         * @property {number} [increment_size=20]
         *  The number of records to render incrementally when scrolling.
         *  Used to improve performance with large lists.
         * @property {boolean} [empty_selection_on_refresh=false]
         *  If true, the current selection is cleared when the list is refreshed
         *  (e.g., due to recordset changes or view mode changes).
         * @property {string|null} [startup_message=null]
         *  A message to display when the list is initially empty or before any records are loaded.
         *  Can be a string or HTML.
         * @property {string|null} [empty_message=null]
         *  A message to display when the recordset is empty and no records are available.
         *  Can be a string or HTML. Defaults to "No items to display."
         * @property {string} [wrapper_tag='div']
         *  The HTML tag to use for the main wrapper element of each record item.
         * @property {string} [wrapper_class='h-recordlist-item']
         *  The CSS class to apply to the main wrapper element of each record item.
         * @property {Object<string, Function>|null} [custom_renderers=null]
         *  An object where keys are view modes (e.g., 'list', 'grid') and values are callback functions
         *  for custom rendering of records in that view mode.
         *  The function receives the record object and should return an HTML string or jQuery object.
         *  Example: `custom_renderers: { 'my_custom_view': function(record) { return '<div>Custom: ' + record.getLabel() + '</div>'; } }`
         * @property {boolean} [highlight_on_hover=true] If true, records are highlighted on mouse hover.
         * @property {boolean} [select_on_click=true] If true, records are selected/deselected on click.
         * @property {boolean} [multi_select=true] If true, multiple records can be selected (e.g., using checkboxes or Ctrl/Shift keys).
         * @property {string|null} [table_fields=null]
         *  A comma-separated string of field names to display as columns in 'table' view.
         *  If null, a default set of fields may be used or an error might occur if not properly configured.
         * @property {string} [table_field_delimiter=',']
         *  The delimiter used in the `table_fields` string.
         * @property {boolean} [table_show_hidden_fields=false]
         *  If true, hidden fields (as defined in the record type's schema) are included in 'table' view if listed in `table_fields`.
         * @property {boolean} [table_show_system_fields=false]
         *  If true, system fields (e.g., 'id', 'createdBy') are included in 'table' view if listed in `table_fields`.
         * @property {boolean} [table_allow_html=false]
         *  If true, HTML content within table cells is rendered as HTML. Otherwise, it's treated as plain text.
         * @property {string} [table_default_field_type='text']
         *  The default field type to assume for columns in 'table' view if not specified in the schema.
         * @property {string} [tooltip_field='Description']
         *  The field to use for generating tooltips if `show_tooltips` is true.
         * @property {boolean} [defer_rendering=false]
         *  If true, initial rendering of records is deferred until explicitly triggered,
         *  which can be useful in complex UI setups.
         * @property {boolean} [is_publication=false]
         *  Indicates if the list is being used in a "publication" context, which might affect
         *  rendering or behavior (e.g., link generation).
         * @property {string} [nav_bar_pos='bottom']
         *  Position of the navigation bar ('top', 'bottom', or 'both').
         * @property {boolean} [search_on_recordset_change=true]
         *  If true, the list automatically refreshes and re-renders when the underlying recordset changes.
         * @property {object|null} [parentView=null]
         *  A reference to a parent view or widget, if this resultList is part of a larger composite view.
         *  Used for event propagation or coordinated behavior.
         * @property {string} [uniqueIdPrefix='hrl-']
         *  A prefix used for generating unique IDs for elements within the widget,
         *  helping to avoid ID collisions in complex pages.
         * @property {string|null} [fixed_header_selector=null]
         *  A CSS selector for a fixed header element. If provided, the widget will adjust its layout
         *  to account for the fixed header, especially for table view headers.
         * @property {number} [scroll_load_threshold=300]
         *  The distance in pixels from the bottom of the scrollable area that triggers loading
         *  of more items when `increment_size` is used.
         * @property {boolean} [debug=false] If true, enables verbose logging to the console for debugging purposes.
         */
        options: {
            recordset: null, // mandatory
            show_nav_buttons: true,
            show_view_modes: true,
            view_modes: ['list','grid','table'], // list, grid, table
            default_view_mode: 'list', // list, grid, table
            show_checkboxes: true,
            show_record_type_icon: true,
            show_details_in_list: false, // compact list view
            show_details_in_grid: true,  // rich grid view
            show_tooltips: true,
            show_itemNo: true,
            grid_image_field: 'image', // TODO: make this an array of fields ???
            grid_image_field_fallback: 'screenshot', // TODO: make this an array of fields ???
            page_size: 100, // items displayed at any one time (TODO: not full implemented yet - just means how many items are loaded from server)
            increment_size: 20, // items added to display from current page with "show more" / scroll
            empty_selection_on_refresh: false, // if true, selection is cleared when list is refreshed (eg. recordset change, view_mode change)
            startup_message: null, // eg. "Please enter search criteria"
            empty_message: null, // default "No items to display." (set in _renderEmptyMessage())
            wrapper_tag: 'div',
            wrapper_class: 'h-recordlist-item',
            custom_renderers: null, // call-back function(s) for custom rendering of items (keyed by view_mode)
            highlight_on_hover: true,
            select_on_click: true,
            multi_select: true, // if false, selection replaces, not adds to current selection (unless shift/ctrl key used)
            table_fields: null, // comma separated string of fields to display (otherwise use record type default fields ???)
            table_field_delimiter: ',',
            table_show_hidden_fields: false,
            table_show_system_fields: false, // eg. id, createdBy, ...
            table_allow_html: false, // allow HTML in table cells
            table_default_field_type: 'text', // default field type for table columns
            tooltip_field: 'Description', // TODO: make this an array of fields ???
            defer_rendering: false, // if true, initial rendering of records is deferred
            is_publication: false, // if true, then links are to public view of records
            nav_bar_pos: 'bottom', // top, bottom, both
            search_on_recordset_change: true, // if false, then need to call refresh() manually
            parentView: null, // parent view (if any) - for event propagation etc
            uniqueIdPrefix: 'hrl-', // prefix for unique IDs
            fixed_header_selector: null, // if table view has a fixed header, provide its selector here
            scroll_load_threshold: 300, // px from bottom to trigger load more
            debug: false
        },

        // privates
        _view_mode: null, // current view mode (string)
        _record_divs: null, // an object, stores all current record divs, keyed by record id
        _table_columns: null, // array of field names for table view
        _table_header_rendered: false, // true if table header has been rendered
        _nav_buttons_need_update: true, // true if nav buttons need to be updated (eg. after recordset change)

        _event_handlers_set: false, // true if event handlers have been set
        _is_publication: false, // true if this is a publication view (set from options)
        _query_request: null, // stores the current query request object (if any)
        _listScrollTop: 0, // stores scroll position of list between refreshes
        _icon_timer_suffix: '', // suffix for icon URLs to force refresh (if list icons are changed)
        _records_rendered_count: 0, // number of records currently rendered in the list
        _total_records_in_rs: 0, // total number of records in the recordset according to last count
        _rendering_incrementally: false, // flag to prevent concurrent incremental renders
        _last_rendered_record_idx: -1, // index of the last record rendered in the current batch
        _scroll_handler_bound: false, // Flag to track if the scroll handler is bound

        /**
         * @function _create
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Initializes the resultList widget. Sets up the initial view mode,
         * creates the basic DOM structure, and binds initial event handlers.
         * It determines if the view is for a publication and sets up a suffix for icon
         * URLs to allow cache busting if icons are updated.
         */
        _create: function() {
            var self = this;
            var o = self.options;

            if (o.debug) console.log('resultList._create()', o);

            self._is_publication = o.is_publication;
            self._icon_timer_suffix = ('&t='+window.hWin.HEURIST4.util.random());

            // TODO: make this more robust (eg. check if valid view mode)
            self._view_mode = o.default_view_mode;
            if (!self.options.view_modes.includes(self._view_mode)) {
                self._view_mode = self.options.view_modes[0] || 'list'; // Fallback to first available or 'list'
            }


            self.element.addClass('h-result-list-widget');
            self.element.html(''); // clear existing content

            // create main containers for nav buttons and list content
            if (o.show_nav_buttons || o.show_view_modes) {
                self.navTop = $('<div class="h-result-list-nav h-result-list-nav-top"></div>');
                self.navBottom = $('<div class="h-result-list-nav h-result-list-nav-bottom"></div>');

                if (o.nav_bar_pos === 'top') {
                    self.element.append(self.navTop);
                } else if (o.nav_bar_pos === 'bottom') {
                    self.element.append(self.navBottom);
                } else if (o.nav_bar_pos === 'both') {
                    self.element.append(self.navTop);
                    self.element.append(self.navBottom);
                }
            }

            self.content = $('<div class="h-result-list-content"></div>');
            if (self._view_mode === 'table' && o.fixed_header_selector) {
                self.content.css('padding-top', $(o.fixed_header_selector).height());
            }

            self.element.append(self.content);

            self._record_divs = {}; // init record_divs cache
            self._table_columns = []; // init table_columns cache
            self._table_header_rendered = false;

            if (o.recordset) {
                // initial setup based on recordset
                self._total_records_in_rs = o.recordset.count();
                if (!o.defer_rendering) {
                    self._refresh();
                } else {
                    self._renderStartupMessage(); // Show startup message if rendering is deferred
                }
            } else {
                self._renderStartupMessage(); // Show startup message if no recordset
                console.error('resultList: recordset option is mandatory');
            }
            self._initControls(); // initialize controls (nav buttons, view modes)
            self._bindScrollHandler(); // for incremental rendering on scroll

        },

        /**
         * @function _isSameRealm
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Checks if the current widget's realm (derived from its element ID or a parent view)
         * matches the realm of the provided recordset. This is used to ensure the widget only responds
         * to events from recordsets relevant to its display context.
         * @param {heurist.RecordSet} recordset The recordset to check.
         * @returns {boolean} True if the realms match or if realm checking is not applicable, false otherwise.
         */
        _isSameRealm: function(recordset) {
            var self = this;
            if (!recordset || !recordset.getRealm()) { // if recordset has no realm, assume it's global or context doesn't matter
                return true;
            }
            var myRealm = self.element.attr('id') || (self.options.parentView && self.options.parentView.element.attr('id'));
            if (!myRealm) { // if widget has no specific realm, assume it can display any recordset
                return true;
            }
            return recordset.getRealm() === myRealm;
        },


        /**
         * @function _initControls
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Initializes the navigation and view mode controls for the widget.
         * This includes creating paging buttons (first, prev, next, last, show more) and
         * view mode switcher buttons (list, grid, table). Event handlers for these
         * controls are also set up here.
         */
        _initControls: function() {
            var self = this;
            var o = self.options;

            if (o.debug) console.log('resultList._initControls()');

            var navContainers = [];
            if (o.nav_bar_pos === 'top') {
                navContainers.push(self.navTop);
            } else if (o.nav_bar_pos === 'bottom') {
                navContainers.push(self.navBottom);
            } else if (o.nav_bar_pos === 'both') {
                navContainers.push(self.navTop, self.navBottom);
            }

            navContainers.forEach(function(nav) {
                if (!nav) return;
                nav.empty(); // Clear previous controls

                if (o.show_nav_buttons) {
                    nav.append('<button class="h-nav-button h-nav-first">First</button>');
                    nav.append('<button class="h-nav-button h-nav-prev">Prev</button>');
                    nav.append('<span class="h-nav-page-info"></span>');
                    nav.append('<button class="h-nav-button h-nav-next">Next</button>');
                    nav.append('<button class="h-nav-button h-nav-last">Last</button>');
                    nav.append('<button class="h-nav-button h-nav-show-more">Show More</button>');
                }

                if (o.show_view_modes && o.view_modes.length > 1) {
                    var vmGroup = $('<div class="h-view-mode-group"></div>');
                    o.view_modes.forEach(function(mode) {
                        vmGroup.append('<button class="h-view-mode-button" data-mode="' + mode + '">' +
                            mode.charAt(0).toUpperCase() + mode.slice(1) + '</button>');
                    });
                    nav.append(vmGroup);
                }
            });

            // Event handlers (delegated for navTop and navBottom if they exist)
            var eventTarget = (self.navTop || self.navBottom) ? self.element : null;

            if (eventTarget) {
                eventTarget.off('.resultListNav').on('click.resultListNav', '.h-nav-first', function() {
                    if ($(this).is('.disabled')) return;
                    o.recordset.seek(0);
                }).on('click.resultListNav', '.h-nav-prev', function() {
                    if ($(this).is('.disabled')) return;
                    o.recordset.seek(Math.max(0, o.recordset.getSeekPos() - o.page_size));
                }).on('click.resultListNav', '.h-nav-next', function() {
                    if ($(this).is('.disabled')) return;
                    o.recordset.seek(Math.min(self._total_records_in_rs - o.page_size, o.recordset.getSeekPos() + o.page_size));
                }).on('click.resultListNav', '.h-nav-last', function() {
                    if ($(this).is('.disabled')) return;
                    o.recordset.seek(Math.max(0, self._total_records_in_rs - o.page_size));
                }).on('click.resultListNav', '.h-nav-show-more', function() {
                    if ($(this).is('.disabled')) return;
                    self._renderRecordsIncrementally(true); // forceShowMore = true
                }).on('click.resultListNav', '.h-view-mode-button', function() {
                    var newMode = $(this).data('mode');
                    if (newMode !== self._view_mode) {
                        self.applyViewMode(newMode);
                    }
                });
            }
            self._updateNavButtons();
            self._updateViewModeButtons();
        },

        /**
         * @function _setOptions
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Handles setting multiple options on the widget after initialization.
         * It iterates over the provided options object and calls `_setOption` for each key-value pair.
         * Finally, it triggers a refresh of the widget to apply the changes.
         * @param {object} options An object containing option key-value pairs to set.
         */
        _setOptions: function(options) {
            var self = this;
            if (self.options.debug) console.log('resultList._setOptions()', options);
            // Call the base _setOptions method
            this._super(options);
            // Re-initialize or update parts of the widget as needed
            // For example, if 'recordset' or 'view_modes' change, we might need to re-render.
            self._refresh(); // A general refresh might be too broad for some options.
                             // Consider more targeted updates if performance becomes an issue.
        },

        /**
         * @function _setOption
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Sets a single option on the widget. This method is called by jQuery UI's
         * option infrastructure. It updates the internal option value and then triggers specific
         * update logic based on which option was changed. For example, changing `recordset`
         * or `view_mode` will trigger a full refresh.
         * @param {string} key The name of the option to set.
         * @param {*} value The new value for the option.
         */
        _setOption: function(key, value) {
            var self = this;
            if (self.options.debug) console.log('resultList._setOption()', key, value);

            this._super(key, value); // store the new option value

            // Handle specific option changes
            switch (key) {
                case "recordset":
                    // If the recordset changes, we need to unbind old events and bind new ones
                    self._unbindRecordsetEvents();
                    self.options.recordset = value; // _super already did this, but for clarity
                    self._total_records_in_rs = self.options.recordset ? self.options.recordset.count() : 0;
                    self._bindRecordsetEvents();
                    self._refresh(); // Full refresh for new data
                    break;
                case "view_mode": // Note: This should ideally be handled by applyViewMode method
                    if (self.options.view_modes.includes(value)) {
                        self.applyViewMode(value);
                    } else {
                        console.warn('resultList: attempted to set invalid view_mode', value);
                        // revert to old value or a default
                        this.options.view_mode = self._view_mode;
                    }
                    break;
                case "default_view_mode":
                     // This is typically used at init, but if changed later, update _view_mode if current is default
                    if (self._view_mode === this.options.default_view_mode && self.options.view_modes.includes(value)) {
                        self._view_mode = value; // Update internal current view_mode
                    }
                    this.options.default_view_mode = value; // _super did this
                    self._refresh(); // May need refresh if view_mode changed
                    break;
                case "show_nav_buttons":
                case "show_view_modes":
                case "nav_bar_pos":
                    self._initControls(); // Re-initialize controls layout
                    self._refresh(); // Content might need repositioning
                    break;
                case "page_size":
                case "increment_size":
                    // These affect how data is fetched/displayed, so refresh
                    self._refresh();
                    break;
                case "is_publication":
                    self._is_publication = value;
                    self._refresh(); // Links might change
                    break;
                case "fixed_header_selector":
                    // Adjust content padding if fixed header changes
                    if (self._view_mode === 'table' && value) {
                        self.content.css('padding-top', $(value).height());
                    } else {
                        self.content.css('padding-top', '');
                    }
                    break;
                // For options that only affect rendering of items, a refresh is usually sufficient
                case "show_checkboxes":
                case "show_record_type_icon":
                case "show_details_in_list":
                case "show_details_in_grid":
                case "show_tooltips":
                case "show_itemNo":
                case "grid_image_field":
                case "grid_image_field_fallback":
                case "wrapper_tag":
                case "wrapper_class":
                case "custom_renderers":
                case "table_fields":
                case "table_allow_html":
                case "tooltip_field":
                    self._refresh();
                    break;
            }
        },

        /**
         * @function _refresh
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Core method to refresh the entire list display. This is called when
         * significant changes occur, such as recordset updates, view mode changes, or option
         * modifications that affect the overall layout or data representation. It clears existing
         * records, re-renders them according to the current view mode and options, updates
         * navigation controls, and rebinds necessary event handlers.
         */
        _refresh: function() {
            var self = this;
            var o = self.options;

            if (o.debug) console.log('resultList._refresh() called. Current view mode:', self._view_mode);

            // Store current scroll position of the content div
            self._listScrollTop = self.content.scrollTop();

            if (o.empty_selection_on_refresh) {
                if (o.recordset) {
                    o.recordset.clearSelection(); // Assuming RecordSet has a clearSelection method
                }
                self._trigger('selectionChanged', null, { selection: [] });
            }

            // Clear existing content (record items)
            self.clearAllRecordDivs(); // This also clears _record_divs cache

            // Reset rendering state
            self._records_rendered_count = 0;
            self._last_rendered_record_idx = -1;
            self._table_header_rendered = false; // Force table header re-render if applicable

            if (o.recordset) {
                self._total_records_in_rs = o.recordset.count(); // Update total count

                if (self._total_records_in_rs === 0) {
                    self._renderEmptyMessage();
                } else {
                    // If view mode is table, parse/prepare table columns first
                    if (self._view_mode === 'table') {
                        self._parseTableColumns(); // Ensure _table_columns is up-to-date
                        self._renderTableHeader(); // Render header before records
                    }
                    // Start rendering records incrementally
                    self._renderRecordsIncrementally();
                }
            } else {
                self._renderEmptyMessage(); // Or a "no recordset" message
                console.warn('resultList._refresh: No recordset available.');
            }

            self._updateNavButtons();
            self._updateViewModeButtons();
            self._bindRecordsetEvents(); // Ensure events are (re)bound

            // Restore scroll position after content is potentially re-added
            // This needs to be done carefully, perhaps after records are rendered.
            // For now, set it, but it might be overridden by incremental rendering.
            self.content.scrollTop(self._listScrollTop);

            // Trigger a refreshed event
            self._trigger('refreshed');
        },

        /**
         * @function _adjustHeadersPos
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Adjusts the position of table headers, typically used when the table
         * has a fixed header that needs to align with the scroll position of the content.
         * This is particularly relevant if the `fixed_header_selector` option is used.
         * Note: This method's implementation might be a placeholder or depend on a specific
         * fixed header plugin/logic not fully shown.
         */
        _adjustHeadersPos: function() {
            // This function is likely related to fixed table headers.
            // If using a plugin like jQuery.floatThead, this might trigger its reflow.
            // For a simple fixed header implementation, this might adjust 'top' or 'transform' CSS.
            var self = this;
            if (self.options.debug) console.log('resultList._adjustHeadersPos()');

            if (self._view_mode === 'table' && self.options.fixed_header_selector) {
                // Example: If the header is floated, ensure its position is updated.
                // This is highly dependent on how the fixed header is implemented.
                // If it's pure CSS `position: sticky`, this might not be needed.
                // If it's JavaScript-positioned, this is where that logic would go.
                var $fixedHeader = $(self.options.fixed_header_selector);
                if ($fixedHeader.length) {
                    // Potentially trigger a reflow or update for a floating header plugin
                    // e.g., if ($fixedHeader.data('floatThead')) $fixedHeader.floatThead('reflow');
                    // Or manually adjust:
                    // var scrollTop = self.content.scrollTop();
                    // $fixedHeader.css('top', scrollTop + 'px'); // This is a naive example
                }
            }
        },

        /**
         * @function _showHideOnWidth
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Placeholder for logic that might show or hide elements within the
         * result list based on the widget's width. This could be used for responsive
         * design adjustments, like hiding certain details in smaller views.
         * Currently, it logs a debug message if debugging is enabled.
         */
        _showHideOnWidth: function() {
            // This function could be used for responsive adjustments based on width.
            // For example, hiding/showing columns in table view, or simplifying item display.
            if (this.options.debug) console.log('resultList._showHideOnWidth() - placeholder');
            // Example:
            // var width = this.element.width();
            // if (width < 600) {
            //    this.element.addClass('compact-view');
            // } else {
            //    this.element.removeClass('compact-view');
            // }
        },

        /**
         * @function _destroy
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Cleans up the widget when it is destroyed. This includes removing
         * added CSS classes, unbinding all event handlers (general, recordset-specific,
         * and scroll handlers), and emptying the widget's element to remove all
         * generated DOM content. It ensures that the widget does not leave any orphaned
         * elements or event listeners.
         */
        _destroy: function() {
            if (this.options.debug) console.log('resultList._destroy()');

            // Unbind all event handlers
            this._unbindRecordsetEvents();
            this._unbindScrollHandler();
            this.element.off('.resultListNav'); // Events for nav buttons

            // For any delegated event handlers on self.element or self.content:
            this.content.off('.resultListItem'); // Item click, hover events
            this.element.off('.resultList'); // Any other specific events

            // Remove added classes
            this.element.removeClass('h-result-list-widget');
            if (this._view_mode) {
                 this.element.removeClass('h-view-mode-' + this._view_mode);
            }

            // Empty the element to remove all generated DOM
            this.element.empty();

            // Nullify references to DOM elements if created by the widget itself
            this.navTop = null;
            this.navBottom = null;
            this.content = null;
            this._record_divs = null;
            this._table_columns = null;

            // Call the base destroy method
            this._super();
        },

        /**
         * @function _removeNavButtons
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Removes the navigation buttons (top and bottom nav bars) from the DOM.
         * This is typically called if `show_nav_buttons` or `show_view_modes` is set to false
         * after initialization, or during a major structural refresh where controls are rebuilt.
         */
        _removeNavButtons: function() {
            if (this.options.debug) console.log('resultList._removeNavButtons()');
            if (this.navTop) {
                this.navTop.remove();
                this.navTop = null;
            }
            if (this.navBottom) {
                this.navBottom.remove();
                this.navBottom = null;
            }
        },

        /**
         * @function applyViewMode
         * @memberof heurist.resultList
         * @instance
         * @description Switches the display to a new view mode (e.g., 'list', 'grid', 'table').
         * It updates the internal state, changes CSS classes on the main element to reflect
         * the new view mode, and triggers a full refresh of the list to re-render items
         * in the new format. If the specified mode is not one of the available `view_modes`
         * or is the same as the current mode, no action is taken.
         * @param {string} newMode The view mode to apply (e.g., 'list', 'grid', 'table').
         * @fires heurist.resultList#viewModeChanged
         */
        applyViewMode: function(newMode) {
            var self = this;
            var o = self.options;

            if (o.debug) console.log('resultList.applyViewMode()', newMode);

            if (newMode === self._view_mode || !o.view_modes.includes(newMode)) {
                if (o.debug && newMode !== self._view_mode) {
                    console.warn('resultList: Invalid view mode requested:', newMode);
                }
                return; // No change or invalid mode
            }

            // Remove old view mode class
            if (self._view_mode) {
                self.element.removeClass('h-view-mode-' + self._view_mode);
                self.content.removeClass('h-content-' + self._view_mode); // Also for content div
            }

            self._view_mode = newMode;

            // Add new view mode class
            self.element.addClass('h-view-mode-' + self._view_mode);
            self.content.addClass('h-content-' + self._view_mode);

            // Adjust content padding for fixed header if switching to/from table view
            if (self._view_mode === 'table' && o.fixed_header_selector) {
                var headerHeight = $(o.fixed_header_selector).outerHeight() || 0;
                self.content.css('padding-top', headerHeight + 'px');
            } else {
                self.content.css('padding-top', ''); // Remove padding if not table or no fixed header
            }

            self._refresh(); // This will re-render everything in the new mode

            self._updateViewModeButtons(); // Update button states

            /**
             * @event heurist.resultList#viewModeChanged
             * @description Triggered when the view mode of the result list changes.
             * @type {object}
             * @property {string} newMode The new view mode that has been applied.
             */
            self._trigger('viewModeChanged', null, { newMode: self._view_mode });
        },

        /**
         * @function clearAllRecordDivs
         * @memberof heurist.resultList
         * @instance
         * @description Removes all currently rendered record items from the display and clears
         * the internal cache (`_record_divs`) that stores references to these DOM elements.
         * This is typically called before a full refresh or when the widget is being destroyed.
         */
        clearAllRecordDivs: function() {
            var self = this;
            if (self.options.debug) console.log('resultList.clearAllRecordDivs()');

            // Iterate over _record_divs and remove each element from the DOM
            // for (var recId in self._record_divs) {
            //     if (self._record_divs.hasOwnProperty(recId) && self._record_divs[recId]) {
            //         self._record_divs[recId].remove();
            //     }
            // }
            // A more direct way if all items are children of self.content
            if (self.content) {
                self.content.empty(); // This removes all children, including table, list items, etc.
            }


            self._record_divs = {}; // Reset the cache
            self._records_rendered_count = 0; // Reset rendered count
            self._last_rendered_record_idx = -1; // Reset last rendered index
            self._table_header_rendered = false; // Table header will need to be re-rendered
        },

        /**
         * @function updateResultSet
         * @memberof heurist.resultList
         * @instance
         * @description Updates the widget with a new RecordSet. It unbinds event handlers
         * from the old recordset (if any), sets the new recordset, updates the total record count,
         * binds event handlers to the new recordset, and then triggers a full refresh of the list.
         * @param {heurist.RecordSet} newRecordset The new recordset to display.
         */
        updateResultSet: function(newRecordset) {
            var self = this;
            var o = self.options;
            if (o.debug) console.log('resultList.updateResultSet()', newRecordset);

            if (self.options.recordset === newRecordset) {
                if (o.debug) console.log('resultList.updateResultSet: new recordset is same as old, forcing refresh.');
                self._refresh(); // force refresh even if same object, data might have changed internally
                return;
            }

            self._unbindRecordsetEvents();
            o.recordset = newRecordset;
            self._total_records_in_rs = o.recordset ? o.recordset.count() : 0;

            if (o.recordset) {
                self._bindRecordsetEvents();
            }
            self._refresh();
        },


        /**
         * @function _renderRecordsIncrementally
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Renders a batch of records into the list. This method is central to the
         * incremental loading and display of records, improving performance for large datasets.
         * It determines the next chunk of records to render based on `increment_size` or `page_size`
         * (for initial load) and appends them to the content area. It handles different view modes
         * (list, grid, table) by calling appropriate rendering helpers.
         * @param {boolean} [forceShowMore=false] If true, forces rendering the next increment
         * even if scroll thresholds haven't been met. Used by the "Show More" button.
         */
        _renderRecordsIncrementally: function(forceShowMore = false) {
            var self = this;
            var o = self.options;

            if (self._rendering_incrementally && !forceShowMore) return; // Prevent concurrent calls unless forced

            if (o.debug) console.log('resultList._renderRecordsIncrementally() starting. Already rendered:', self._records_rendered_count, 'Total in RS:', self._total_records_in_rs);

            if (!o.recordset || self._total_records_in_rs === 0) {
                if (o.debug) console.log('resultList._renderRecordsIncrementally: No recordset or recordset empty.');
                self._renderEmptyMessage(); // Ensure empty message is shown
                self._updateNavButtons(); // Update nav buttons (likely disable show more)
                return;
            }

            // If we've already rendered everything
            if (self._records_rendered_count >= self._total_records_in_rs) {
                if (o.debug) console.log('resultList._renderRecordsIncrementally: All records already rendered.');
                self._updateNavButtons(); // Ensure "Show More" is disabled
                if (self.content.find('.h-loading-indicator').length > 0) { // remove if any loading exists
                     self.content.find('.h-loading-indicator').remove();
                }
                 if (self._records_rendered_count === 0 && self._total_records_in_rs === 0) {
                    // This case should be caught earlier, but as a fallback:
                    self._renderEmptyMessage();
                }
                return;
            }

            self._rendering_incrementally = true;
            self.content.find('.h-startup-message, .h-empty-message').remove(); // Remove startup/empty messages

            // Add loading indicator
            if (self.content.find('.h-loading-indicator').length === 0 && self._records_rendered_count < self._total_records_in_rs) {
                self.content.append('<div class="h-loading-indicator">Loading...</div>');
            }


            var startIndex = self._last_rendered_record_idx + 1;
            var numToRender;

            if (self._records_rendered_count === 0) { // Initial render batch
                numToRender = Math.min(o.page_size, self._total_records_in_rs);
            } else { // Subsequent incremental render
                numToRender = Math.min(o.increment_size, self._total_records_in_rs - self._records_rendered_count);
            }

            if (o.debug) console.log('resultList._renderRecordsIncrementally: startIndex:', startIndex, 'numToRender:', numToRender);


            if (numToRender <= 0) {
                self.content.find('.h-loading-indicator').remove();
                self._rendering_incrementally = false;
                self._updateNavButtons(); // Update nav states (e.g. disable "show more")
                return;
            }

            // Fetch records from recordset
            // Assuming recordset.getRecords(startIndex, numToRender) returns an array of record objects
            // This part might need adjustment based on actual RecordSet API
            // For now, let's assume RecordSet loads records itself and we can iterate
            // For simplicity, using a loop and getRecordAt(index)
            // In a real scenario, RecordSet might have a more efficient way to get a range or page

            var recordsToRender = [];
            for (var i = 0; i < numToRender; i++) {
                var record = o.recordset.getRecordAt(startIndex + i);
                if (record) {
                    recordsToRender.push(record);
                } else {
                    // This might happen if recordset is out of sync or sparse
                    if (o.debug) console.warn('resultList: Record not found at index', startIndex + i);
                    break; // Stop if a record is missing
                }
            }

            if (recordsToRender.length === 0 && numToRender > 0) {
                 if (o.debug) console.log('resultList._renderRecordsIncrementally: No records returned from recordset for the range.');
                 self.content.find('.h-loading-indicator').remove();
                 self._rendering_incrementally = false;
                 self._updateNavButtons();
                 // If this was the very first attempt and nothing rendered, show empty.
                 if (self._records_rendered_count === 0) {
                    self._renderEmptyMessage();
                 }
                 return;
            }


            var $container;
            if (self._view_mode === 'table') {
                // Ensure table structure exists
                var $table = self.content.find('> table.h-result-table');
                if ($table.length === 0) { // Should have been created by _renderTableHeader
                    console.error("resultList: Table element not found for rendering records.");
                    self.content.find('.h-loading-indicator').remove();
                    self._rendering_incrementally = false;
                    return;
                }
                $container = $table.find('> tbody');
                if ($container.length === 0) { // Should also exist
                     console.error("resultList: Table tbody element not found for rendering records.");
                     $container = $('<tbody></tbody>').appendTo($table); // Create if missing
                }
            } else {
                $container = self.content; // For list and grid, append directly to content or a sub-container
                // Could also be a specific ul/div inside self.content for list/grid items
                var $listOrGridContainer = self.content.find('> .h-items-container');
                if ($listOrGridContainer.length === 0) {
                    $listOrGridContainer = $('<div class="h-items-container h-view-mode-' + self._view_mode + '"></div>').appendTo(self.content);
                }
                 $container = $listOrGridContainer;
            }


            recordsToRender.forEach(function(record, indexInBatch) {
                var recId = record.getId();
                var currentOverallIndex = startIndex + indexInBatch;
                var $itemDiv;

                if (self._record_divs[recId]) { // Already rendered? Should not happen with correct logic
                    if (o.debug) console.warn('resultList: Record', recId, 'already in _record_divs during incremental render. Skipping.');
                    // We should update it if data changed, but incremental render assumes new items.
                    // For now, skip to avoid duplicates. A full _refresh handles updates.
                    return; // continue to next record
                }

                if (o.custom_renderers && typeof o.custom_renderers[self._view_mode] === 'function') {
                    $itemDiv = o.custom_renderers[self._view_mode](record, self);
                } else {
                    switch (self._view_mode) {
                        case 'list':
                            $itemDiv = self._renderRecord_html(record, 'list', currentOverallIndex);
                            break;
                        case 'grid':
                            $itemDiv = self._renderRecord_html(record, 'grid', currentOverallIndex);
                            break;
                        case 'table':
                            $itemDiv = self._renderRecord_tableRow(record, currentOverallIndex); // This returns a <tr>
                            break;
                        default:
                            $itemDiv = $('<div>Unsupported view mode: ' + self._view_mode + '</div>');
                    }
                }

                if ($itemDiv) {
                    $itemDiv.attr('data-record-id', recId); // Ensure record ID is on the element
                    $container.append($itemDiv);
                    self._record_divs[recId] = $itemDiv; // Cache it
                    self._records_rendered_count++;
                    self._last_rendered_record_idx = currentOverallIndex;

                    // Bind item-specific events (hover, click)
                    self._bindItemEvents($itemDiv);

                    // Trigger event for each rendered item (optional, can be chatty)
                    // self._trigger('itemRendered', null, { record: record, element: $itemDiv });
                }
            });

            self.content.find('.h-loading-indicator').remove(); // Remove loading indicator

            // Restore scroll position to where it was before adding new items,
            // but only if it's not a forced "show more" (where user expects to see new items at bottom)
            if (!forceShowMore && self._listScrollTop > 0) {
                 // This might need adjustment. If items are added above current view, scroll needs to compensate.
                 // For append, scrollTop should generally be preserved unless content height change is massive.
                 // self.content.scrollTop(self._listScrollTop);
            }


            self._rendering_incrementally = false;
            self._updateNavButtons(); // Update states of nav buttons (e.g., "Show More")

            // If all records are now rendered, trigger an event
            if (self._records_rendered_count >= self._total_records_in_rs) {
                self._trigger('allRecordsRendered');
                if (o.debug) console.log('resultList: All records rendered.');
            }

            // If after rendering, the content is still scrollable and we haven't filled the viewport,
            // and not all records are shown, call again to fill up space (common for initial load).
            // This check needs to be careful to avoid infinite loops.
            var scrollContainer = self.content; // or window, depending on layout
            var moreToLoad = self._records_rendered_count < self._total_records_in_rs;
            if (moreToLoad && !forceShowMore && (scrollContainer.prop('scrollHeight') <= scrollContainer.prop('clientHeight'))) {
                if (o.debug) console.log('resultList: Content not filling viewport, rendering more.');
                // Check if we actually rendered anything in this pass to prevent loop if numToRender was 0
                if (recordsToRender.length > 0) {
                     self._renderRecordsIncrementally(); // Recursive call
                }
            }
        },

        /**
         * @function renderMessage
         * @memberof heurist.resultList
         * @instance
         * @description Displays a message within the result list's content area. This is used
         * for startup messages, empty list messages, or other notifications. It clears any
         * existing records or messages before showing the new one.
         * @param {string} message The HTML or text message to display.
         * @param {string} [messageClass='h-result-list-message'] An optional CSS class to apply to the message container.
         */
        renderMessage: function(message, messageClass) {
            var self = this;
            if (self.options.debug) console.log('resultList.renderMessage()', message);

            self.clearAllRecordDivs(); // Clear any existing records

            // Ensure messageClass has a default
            messageClass = messageClass || 'h-result-list-message';

            // Remove any other existing messages of different types
            self.content.find('.h-startup-message, .h-empty-message, .h-result-list-message').remove();

            var $messageDiv = $('<div></div>')
                .addClass(messageClass)
                .html(message);
            self.content.append($messageDiv);
        },


        /**
         * @function _renderEmptyMessage
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Renders a predefined "empty" message if no records are available in the
         * recordset. Uses `options.empty_message` if provided, otherwise a default message.
         * This is called when the recordset is confirmed to be empty.
         */
        _renderEmptyMessage: function() {
            var self = this;
            var o = self.options;
            if (o.debug) console.log('resultList._renderEmptyMessage()');

            var message = o.empty_message !== null ? o.empty_message : window.hlang.getLang('No items to display.');
            // Clear content first to remove loading indicators or previous items
            self.content.empty();
            self._record_divs = {}; // Reset cache
            self._records_rendered_count = 0;
            self._last_rendered_record_idx = -1;

            var $messageDiv = $('<div></div>')
                .addClass('h-empty-message')
                .html(message);
            self.content.append($messageDiv);
            self._updateNavButtons(); // Update nav, "show more" should be disabled
        },

        /**
         * @function _renderStartupMessage
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Renders a startup message. This message is typically shown when the
         * widget is first initialized, especially if `options.defer_rendering` is true or
         * if there's no initial recordset. Uses `options.startup_message`.
         */
        _renderStartupMessage: function() {
            var self = this;
            var o = self.options;
            if (o.debug) console.log('resultList._renderStartupMessage()');

            if (o.startup_message) {
                // Clear content first
                self.content.empty();
                self._record_divs = {}; // Reset cache
                self._records_rendered_count = 0;
                self._last_rendered_record_idx = -1;

                var $messageDiv = $('<div></div>')
                    .addClass('h-startup-message')
                    .html(o.startup_message);
                self.content.append($messageDiv);
            }
            // else, no startup message is configured, so the list might just appear empty
            // until data is loaded or an empty message is shown.
        },

        /**
         * @function _renderStartupMessageComposedFromRecord
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description This method appears to be a specialized version or alternative to
         * `_renderStartupMessage`. It seems intended to compose a startup message using
         * details from a specific record, possibly a "context" record.
         * Note: The implementation details (e.g., how `rec` is obtained) are not fully clear
         * from the snippet and might depend on external logic or specific use cases.
         * @param {object} rec The record object to use for composing the startup message.
         * (The type of this object should ideally be {@link heurist.HRecord} or similar).
         */
        _renderStartupMessageComposedFromRecord: function(rec) {
            var self = this;
            var o = self.options;
            if (o.debug) console.log('resultList._renderStartupMessageComposedFromRecord()', rec);

            if (!rec || typeof rec.getLabel !== 'function') {
                if (o.debug) console.warn('resultList: Invalid record provided for startup message.');
                self._renderStartupMessage(); // Fallback to generic startup message
                return;
            }

            // Example: "Items related to [Record Label]"
            // The actual message composition would depend on the requirements.
            // This is a placeholder for what such a function might do.
            var message = window.hlang.getLang('Items related to') + ' ' + rec.getLabel();
            if (o.startup_message) { // If a generic startup message is also defined, perhaps append or prepend
                message = o.startup_message + '<br/>' + message;
            }

            self.renderMessage(message, 'h-startup-message');
        },


        /**
         * @function _renderRecord_html_stub
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Creates a basic HTML stub for a record item. This stub typically includes
         * a wrapper element with essential attributes like record ID and selection state.
         * It's used as a common starting point for more detailed rendering in different view modes.
         * @param {object} record The record object to render. (Should be {@link heurist.HRecord})
         * @param {string} viewMode The current view mode ('list', 'grid').
         * @param {number} itemNo The sequential number of the item in the list.
         * @returns {jQuery} A jQuery object representing the record item's basic structure.
         */
        _renderRecord_html_stub: function(record, viewMode, itemNo) {
            var self = this;
            var o = self.options;
            var recId = record.getId();
            var recTypeId = record.getRecTypeId();
            var recType = record.getRecType(); // Assuming HRecord has getRecType() returning an object with iconHtml, label etc.

            var $item = $('<' + o.wrapper_tag + '>')
                .addClass(o.wrapper_class)
                .addClass('h-record-' + viewMode + '-item') // e.g., h-record-list-item
                .attr('data-record-id', recId)
                .attr('data-record-type-id', recTypeId);

            if (record.isSelected()) {
                $item.addClass('h-selected');
            }

            // Checkbox
            if (o.show_checkboxes) {
                var $checkbox = $('<input type="checkbox" class="h-record-select-checkbox">')
                    .prop('checked', record.isSelected())
                    .on('click', function(e) { // Direct handler for immediate feedback & model update
                        e.stopPropagation(); // Prevent click from bubbling to item click if different actions
                        var isChecked = $(this).is(':checked');
                        o.recordset.setSelectionById(recId, isChecked, !o.multi_select); // Third param: clearOthers
                        // Visual feedback is handled by recordset 'selectionChanged' event typically
                    });
                $item.append($checkbox);
            }

            // Record Type Icon
            if (o.show_record_type_icon && recType) {
                var iconHtml = recType.iconHtml || // Ideal: recType object has pre-formatted icon HTML
                               (recType.getIconUrl ? '<img src="' + recType.getIconUrl() + self._icon_timer_suffix + '" alt="' + (recType.label || '') + '"/>' : '');
                if (iconHtml) {
                    $item.append('<span class="h-record-type-icon">' + iconHtml + '</span>');
                }
            }

            // Item Number
            if (o.show_itemNo) {
                $item.append('<span class="h-item-number">' + (itemNo + 1) + '.</span>');
            }
            return $item;
        },

        /**
         * @function _renderRecord_html
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Renders the HTML representation of a single record for 'list' or 'grid' view.
         * It starts with a stub from `_renderRecord_html_stub` and then adds more details like
         * title, thumbnail (for grid), and other fields based on the view mode and widget options.
         * @param {object} record The record object to render. (Should be {@link heurist.HRecord})
         * @param {string} viewMode The view mode ('list' or 'grid').
         * @param {number} itemNo The sequential number of the item in the list.
         * @returns {jQuery} A jQuery object representing the fully rendered record item.
         */
        _renderRecord_html: function(record, viewMode, itemNo) {
            var self = this;
            var o = self.options;
            var recLabel = record.getLabel() || record.getShortDesc() || ('Record ' + record.getId()); // Fallback label

            var $item = self._renderRecord_html_stub(record, viewMode, itemNo);

            // Title / Label
            var $title = $('<span class="h-record-title"></span>');
            var titleLink = '#'; // Placeholder, should be actual link if applicable
            if (self._is_publication) {
                titleLink = window.hWin.HEURIST.hgo('view', record.getId(), { pub: true });
            } else {
                 // Link to open in workspace, might involve JS call
                 // titleLink = 'javascript:void(0);'; // Or specific function call
                 // For now, let's assume a simple link or just text
            }

            // If there's a specific link generation logic in HRecord or elsewhere:
            // titleLink = record.getViewLink({is_publication: self._is_publication});

            var $anchor = $('<a></a>').attr('href', titleLink).text(recLabel);
            if (!self._is_publication) { // Prevent default if it's a JS action link
                $anchor.on('click', function(e) {
                    e.preventDefault();
                    // Trigger an event or call a method to open the record
                    self._trigger('itemActivated', null, { record: record, element: $item });
                    // Example: window.hWin.HEURIST.mainApp.displayRecord(record.getId());
                });
            }
            $title.append($anchor);

            if (o.show_tooltips) {
                var tooltipText = record.getFieldValue(o.tooltip_field) || recLabel;
                $anchor.attr('title', tooltipText);
                // Potentially initialize a tooltip plugin here if not using native titles
            }
            $item.append($title);


            // Details specific to view modes
            if (viewMode === 'grid') {
                if (o.show_details_in_grid) { // "Rich" grid view
                    var $details = $('<div class="h-record-details"></div>');
                    // Image
                    var imageUrl = record.getFieldValue(o.grid_image_field) || record.getFieldValue(o.grid_image_field_fallback);
                    if (imageUrl) {
                        // Check if it's a Heurist file URL and needs formatting
                        if (typeof imageUrl === 'string' && imageUrl.startsWith('hfile://')) {
                            var fileId = imageUrl.substring(8); // length of "hfile://"
                             // Assuming a utility to get public/thumbnail URL for a file ID
                            imageUrl = window.hWin.HEURIST.getFileUrl(fileId, { thumbnail: true, pub: self._is_publication });
                        }
                        $details.append('<img src="' + imageUrl + '" class="h-grid-image" alt="' + recLabel + '">');
                    } else {
                        // Placeholder if no image
                        $details.append('<div class="h-grid-no-image"><span>No Image</span></div>');
                    }
                    // Other details for grid (e.g., short description)
                    var shortDesc = record.getShortDesc(150); // Max 150 chars
                    if (shortDesc) {
                        $details.append('<p class="h-record-shortdesc">' + हेमा.util.escapeHTML(shortDesc) + '</p>');
                    }
                    $item.append($details);
                }
            } else if (viewMode === 'list') {
                if (o.show_details_in_list) { // "Detailed" list view
                    var $details = $('<div class="h-record-details"></div>');
                    // Example: display a few key fields
                    var detailFields = record.getFields(['Subject', 'Date', 'Place']); // Example fields
                    var dl = $('<dl></dl>');
                    detailFields.forEach(function(field) {
                        if (field.value) { // Assuming getFields returns {label: '', value: ''}
                            dl.append('<dt>' + हेमा.util.escapeHTML(field.label) + '</dt>');
                            dl.append('<dd>' + हेमा.util.escapeHTML(field.value) + '</dd>');
                        }
                    });
                    if (dl.children().length > 0) {
                        $details.append(dl);
                    }
                    // Or a short description
                    var shortDesc = record.getShortDesc(250); // Max 250 chars
                    if (shortDesc) {
                        $details.append('<p class="h-record-shortdesc">' + हेमा.util.escapeHTML(shortDesc) + '</p>');
                    }
                    $item.append($details);
                }
            }
            return $item;
        },

        /**
         * @function _recordDivOnHover
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Handles the mouseenter and mouseleave events for a record item.
         * Adds or removes a hover class if `options.highlight_on_hover` is true.
         * It also triggers `itemMouseEnter` and `itemMouseLeave` events.
         * @param {jQuery.Event} e The jQuery event object.
         * @param {jQuery} $item The jQuery object for the record item being hovered.
         */
        _recordDivOnHover: function(e, $item) {
            var self = this;
            var o = self.options;
            var recordId = $item.data('record-id');
            var record = o.recordset.getById(recordId);

            if (!record) return;

            if (e.type === 'mouseenter') {
                if (o.highlight_on_hover) {
                    $item.addClass('h-hover');
                }
                /**
                 * @event heurist.resultList#itemMouseEnter
                 * @description Triggered when the mouse pointer enters a record item.
                 * @type {object}
                 * @property {object} record The {@link heurist.HRecord} associated with the item.
                 * @property {jQuery} element The jQuery element of the item.
                 */
                self._trigger('itemMouseEnter', e, { record: record, element: $item });
            } else { // mouseleave
                if (o.highlight_on_hover) {
                    $item.removeClass('h-hover');
                }
                /**
                 * @event heurist.resultList#itemMouseLeave
                 * @description Triggered when the mouse pointer leaves a record item.
                 * @type {object}
                 * @property {object} record The {@link heurist.HRecord} associated with the item.
                 * @property {jQuery} element The jQuery element of the item.
                 */
                self._trigger('itemMouseLeave', e, { record: record, element: $item });
            }
        },

        /**
         * @function _recordDivOnClick
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Handles the click event on a record item. If `options.select_on_click`
         * is true, it updates the record's selection state in the recordset.
         * It also triggers an `itemClick` event and, if the item becomes selected and
         * not in multi-select mode (or Shift/Ctrl not used), an `itemActivated` event.
         * @param {jQuery.Event} e The jQuery event object.
         * @param {jQuery} $item The jQuery object for the clicked record item.
         */
        _recordDivOnClick: function(e, $item) {
            var self = this;
            var o = self.options;
            var recordId = $item.data('record-id');
            var record = o.recordset.getById(recordId);

            if (!record) return;

            if (o.select_on_click) {
                // Determine if it's a multi-select operation
                var clearOthers = !o.multi_select || (!e.ctrlKey && !e.metaKey && !e.shiftKey);
                var rangeSelect = o.multi_select && e.shiftKey && o.recordset.getLastSelectedId();

                if (rangeSelect) {
                    // Select a range of items
                    // This requires RecordSet to have a method like selectRangeTo(recordId)
                    // o.recordset.selectRangeTo(recordId);
                    // For now, let's assume RecordSet's setSelectionById handles range logic
                    // or we simplify by just toggling current if shift is held without more complex range logic.
                    // A proper range select would need the index of last selected and current item.
                    o.recordset.setSelectionById(recordId, !record.isSelected(), false, true); // isSelected, clearOthers=false, isRange=true
                } else {
                    o.recordset.setSelectionById(recordId, !record.isSelected(), clearOthers);
                }
                // The 'selectionChanged' event from RecordSet will update the item's class.
            }

            /**
             * @event heurist.resultList#itemClick
             * @description Triggered when a record item is clicked.
             * @type {object}
             * @property {object} record The {@link heurist.HRecord} associated with the item.
             * @property {jQuery} element The jQuery element of the item.
             * @property {jQuery.Event} originalEvent The original jQuery click event.
             */
            self._trigger('itemClick', e, { record: record, element: $item, originalEvent: e });

            // Check if the item is now selected (after the click logic)
            // record.isSelected() might not be updated yet if selection is async or handled by event.
            // Rely on the state after setSelectionById call, assuming it's synchronous for the HRecord object state.
            if (record.isSelected() && (!o.multi_select || (!e.ctrlKey && !e.metaKey && !e.shiftKey))) {
                 // If not multi-selecting, or if multi-selecting but without modifier keys,
                 // consider it an activation.
                 // For true multi-select activation (e.g. on dblclick), a different event/handler would be needed.
                /**
                 * @event heurist.resultList#itemActivated
                 * @description Triggered when an item is "activated" (typically a single click
                 * that also selects it, or if it was already selected and is clicked again without
                 * modifier keys in a multi-select context). This is often used to open or view the item.
                 * @type {object}
                 * @property {object} record The {@link heurist.HRecord} associated with the item.
                 * @property {jQuery} element The jQuery element of the item.
                 */
                self._trigger('itemActivated', e, { record: record, element: $item });
            }
        },


        /**
         * @function _updateNavButtons
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Updates the state (enabled/disabled, text) of navigation buttons
         * (First, Prev, Next, Last, Show More) and the page information display.
         * This is based on the current position in the recordset, the total number of records,
         * and the number of records currently rendered.
         */
        _updateNavButtons: function() {
            var self = this;
            var o = self.options;

            if (!o.show_nav_buttons || (!self.navTop && !self.navBottom)) {
                return; // Nav buttons are not visible or not initialized
            }
            if (!o.recordset) {
                 // Disable all buttons if no recordset
                (self.navTop || self.navBottom).find('.h-nav-button').addClass('disabled');
                (self.navTop || self.navBottom).find('.h-nav-page-info').text('');
                return;
            }


            var rs = o.recordset;
            var currentPos = rs.getSeekPos(); // Position of the first item in the current "page" or view
            var totalRecords = self._total_records_in_rs;
            var pageSize = o.page_size; // How many items are conceptually on a "page"
            var recordsRendered = self._records_rendered_count;

            var navTargets = [];
            if (self.navTop) navTargets.push(self.navTop);
            if (self.navBottom) navTargets.push(self.navBottom);

            navTargets.forEach(function($nav) {
                // Page info
                var pageInfoText = '';
                if (totalRecords > 0) {
                    // Example: "1-20 of 150" (if page_size is 20, and we are on first page)
                    // This needs to be based on conceptual pages, not just rendered items.
                    // For now, let's use rendered count if it's simpler or page_size is more like a load size.
                    var startItem = currentPos + 1;
                    var endItem = Math.min(currentPos + pageSize, totalRecords);
                    // If using incremental show more, pageInfo might reflect rendered items
                    // pageInfoText = recordsRendered + ' of ' + totalRecords;
                    pageInfoText = startItem + '-' + endItem + ' of ' + totalRecords;

                } else {
                    pageInfoText = window.hlang.getLang('No items');
                }
                $nav.find('.h-nav-page-info').text(pageInfoText);

                // First/Prev buttons
                if (currentPos > 0) {
                    $nav.find('.h-nav-first, .h-nav-prev').removeClass('disabled');
                } else {
                    $nav.find('.h-nav-first, .h-nav-prev').addClass('disabled');
                }

                // Next/Last buttons
                if (currentPos + pageSize < totalRecords) {
                    $nav.find('.h-nav-next, .h-nav-last').removeClass('disabled');
                } else {
                    $nav.find('.h-nav-next, .h-nav-last').addClass('disabled');
                }

                // Show More button
                if (recordsRendered < totalRecords) {
                    $nav.find('.h-nav-show-more').removeClass('disabled').show();
                } else {
                    $nav.find('.h-nav-show-more').addClass('disabled').hide();
                }
            });
            self._nav_buttons_need_update = false;
        },

        /**
         * @function _updateViewModeButtons
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Updates the visual state of view mode buttons (e.g., 'list', 'grid', 'table')
         * to highlight the currently active view mode.
         */
        _updateViewModeButtons: function() {
            var self = this;
            var o = self.options;

            if (!o.show_view_modes || o.view_modes.length <= 1) {
                return; // No view mode buttons to update
            }

            var navTargets = [];
            if (self.navTop) navTargets.push(self.navTop);
            if (self.navBottom) navTargets.push(self.navBottom);

            navTargets.forEach(function($nav) {
                $nav.find('.h-view-mode-button').each(function() {
                    var $button = $(this);
                    if ($button.data('mode') === self._view_mode) {
                        $button.addClass('active');
                    } else {
                        $button.removeClass('active');
                    }
                });
            });
        },

        /**
         * @function _parseTableColumns
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Parses the `options.table_fields` string (a comma-separated list of
         * field names) into an array stored in `_table_columns`. This array is then used
         * to determine which columns to render in the table view. If `table_fields` is not
         * provided, it might attempt to use default fields from the record type, though this
         * fallback logic isn't fully detailed here and might depend on `HRecord` capabilities.
         */
        _parseTableColumns: function() {
            var self = this;
            var o = self.options;
            self._table_columns = []; // Reset

            if (o.table_fields && typeof o.table_fields === 'string') {
                self._table_columns = o.table_fields.split(o.table_field_delimiter)
                    .map(function(fieldName) { return fieldName.trim(); })
                    .filter(function(fieldName) { return fieldName.length > 0; });
            } else if (o.recordset && o.recordset.count() > 0) {
                // Fallback: Try to get default fields from the first record's type
                // This requires HRecord and HRecType to have such functionality
                var firstRecord = o.recordset.getRecordAt(0);
                if (firstRecord && typeof firstRecord.getRecType === 'function') {
                    var recType = firstRecord.getRecType();
                    if (recType && typeof recType.getDefaultFields === 'function') {
                        // Assuming getDefaultFields returns an array of field name strings or {name: '...', label: '...'} objects
                        self._table_columns = recType.getDefaultFields().map(function(field) {
                            return (typeof field === 'string') ? field : field.name;
                        });
                    }
                }
            }

            if (self._table_columns.length === 0) {
                // Final fallback: use a few common fields if nothing else defined
                // self._table_columns = ['RecId', 'RecType', 'ShortDesc']; // Example
                if (o.debug) console.warn('resultList: No table columns defined or derivable. Table view might be empty or show minimal info.');
                // It might be better to show a message or just the record label if no columns.
            }
            if (o.debug) console.log('resultList._parseTableColumns, result:', self._table_columns);
        },

        /**
         * @function _renderTableHeader
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Renders the header row (`<thead>`) for the table view. It uses the
         * `_table_columns` array to create table header cells (`<th>`). If a fixed header
         * is specified via `options.fixed_header_selector`, this method might also handle
         * integrating with or setting up that fixed header.
         * It ensures the header is rendered only once per refresh.
         */
        _renderTableHeader: function() {
            var self = this;
            var o = self.options;

            if (self._view_mode !== 'table' || self._table_header_rendered) {
                return;
            }

            if (o.debug) console.log('resultList._renderTableHeader()');

            // Ensure _table_columns is populated
            if (!self._table_columns || self._table_columns.length === 0) {
                self._parseTableColumns(); // Attempt to parse them if not already done
                if (self._table_columns.length === 0) {
                     if (o.debug) console.warn('resultList: Cannot render table header, no columns defined.');
                    return; // No columns to render
                }
            }

            // Clear existing table content (if any, though should be handled by clearAllRecordDivs)
            // self.content.find('table.h-result-table').remove();

            var $table = self.content.find('> table.h-result-table');
            if ($table.length === 0) {
                 $table = $('<table class="h-result-table"></table>').appendTo(self.content);
            }
            $table.find('thead').remove(); // Remove old header if exists

            var $thead = $('<thead></thead>').appendTo($table);
            var $tr = $('<tr></tr>').appendTo($thead);

            // Optional: Add a column for checkboxes if shown
            if (o.show_checkboxes) {
                $tr.append('<th class="h-col-checkbox"></th>'); // Empty header for checkbox column
            }
            // Optional: Add a column for item numbers if shown
            if (o.show_itemNo) {
                $tr.append('<th class="h-col-itemNo">#</th>');
            }


            self._table_columns.forEach(function(fieldName) {
                // TODO: Get a proper label for the fieldName (e.g., from schema or HRecType)
                var fieldLabel = fieldName; // Placeholder
                if (o.recordset && o.recordset.count() > 0) {
                    var firstRecord = o.recordset.getRecordAt(0);
                    if (firstRecord) {
                        var field = firstRecord.getField(fieldName); // Assuming getField returns {label: ...}
                        if (field && field.label) {
                            fieldLabel = field.label;
                        }
                    }
                }
                $('<th></th>').text(fieldLabel).appendTo($tr);
            });

            // If using a fixed header plugin (like floatThead), initialize it here.
            // Example: if ($.fn.floatThead) { $table.floatThead({ headerCellSelector: 'tr:first>th' }); }
            // Or if fixed_header_selector is used for a custom fixed header solution,
            // this might be the place to clone the header into that selector.

            self._table_header_rendered = true;
            // Ensure tbody exists for records
            if ($table.find('tbody').length === 0) {
                $('<tbody></tbody>').appendTo($table);
            }
        },

        /**
         * @function _renderRecord_tableRow
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Renders a single record as a table row (`<tr>`) for the table view.
         * It iterates over `_table_columns` to create table data cells (`<td>`) for each field
         * in the record. It also handles selection state, checkboxes, and item numbers
         * according to widget options.
         * @param {object} record The record object to render. (Should be {@link heurist.HRecord})
         * @param {number} itemNo The sequential number of the item in the list.
         * @returns {jQuery} A jQuery object representing the `<tr>` element for the record.
         */
        _renderRecord_tableRow: function(record, itemNo) {
            var self = this;
            var o = self.options;
            var recId = record.getId();

            var $tr = $('<tr></tr>')
                .addClass(o.wrapper_class) // Apply general wrapper class styling
                .addClass('h-record-table-row') // Specific class for table rows
                .attr('data-record-id', recId)
                .attr('data-record-type-id', record.getRecTypeId());

            if (record.isSelected()) {
                $tr.addClass('h-selected');
            }

            // Checkbox cell
            if (o.show_checkboxes) {
                var $checkbox_td = $('<td></td>').addClass('h-col-checkbox');
                var $checkbox = $('<input type="checkbox" class="h-record-select-checkbox">')
                    .prop('checked', record.isSelected())
                    .on('click', function(e) {
                        e.stopPropagation();
                        o.recordset.setSelectionById(recId, $(this).is(':checked'), !o.multi_select);
                    });
                $checkbox_td.append($checkbox);
                $tr.append($checkbox_td);
            }

            // Item number cell
            if (o.show_itemNo) {
                $('<td></td>').addClass('h-col-itemNo').text(itemNo + 1).appendTo($tr);
            }

            // Data cells from _table_columns
            self._table_columns.forEach(function(fieldName) {
                var fieldValue = record.getFieldValue(fieldName, {
                    show_hidden: o.table_show_hidden_fields,
                    show_system: o.table_show_system_fields
                    // We might need a 'raw' or 'formatted_for_display' option here too
                });

                // Convert fieldValue to a displayable string. HRecord.getFieldValue should ideally handle this.
                if (fieldValue === null || typeof fieldValue === 'undefined') {
                    fieldValue = '';
                } else if (typeof fieldValue === 'object') {
                    // Complex objects might need special formatting (e.g., linked records, files)
                    // For now, try to get a label or string representation
                    fieldValue = fieldValue.label || fieldValue.name || JSON.stringify(fieldValue);
                } else {
                    fieldValue = String(fieldValue); // Ensure it's a string
                }


                var $td = $('<td></td>');
                if (o.table_allow_html) {
                    $td.html(fieldValue);
                } else {
                    $td.text(fieldValue);
                }

                // Add class for field type for potential styling, e.g. "h-field-type-date"
                var field = record.getField(fieldName);
                if (field && field.type) {
                    $td.addClass('h-field-type-' + field.type.toLowerCase());
                } else {
                    $td.addClass('h-field-type-' + o.table_default_field_type.toLowerCase());
                }
                // Add class for field name for specific styling, e.g. "h-field-name-Title"
                $td.addClass('h-field-name-' + fieldName.replace(/\s+/g, '-'));


                $tr.append($td);
            });

            return $tr;
        },

        /**
         * @function _bindItemEvents
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Binds hover (mouseenter, mouseleave) and click events to a given record item element.
         * These events trigger the `_recordDivOnHover` and `_recordDivOnClick` methods respectively.
         * This function centralizes event binding for individual items.
         * @param {jQuery} $item The jQuery object representing the record item.
         */
        _bindItemEvents: function($item) {
            var self = this;
            // Namespace events for easy unbinding e.g. .resultListItem
            $item.on('mouseenter.resultListItem', function(e) {
                self._recordDivOnHover(e, $(this));
            }).on('mouseleave.resultListItem', function(e) {
                self._recordDivOnHover(e, $(this));
            }).on('click.resultListItem', function(e) {
                self._recordDivOnClick(e, $(this));
            });
        },

        /**
         * @function _bindRecordsetEvents
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Binds event handlers to the associated recordset. This includes listeners for
         * when the recordset changes (e.g., new search results), when the selection within the
         * recordset changes, and when individual records are updated. Ensures that these handlers
         * are only bound once.
         * @listens heurist.RecordSet#recordsetChanged
         * @listens heurist.RecordSet#selectionChanged
         * @listens heurist.RecordSet#recordUpdated
         */
        _bindRecordsetEvents: function() {
            var self = this;
            var o = self.options;

            if (!o.recordset || self._event_handlers_set) {
                return;
            }

            if(o.debug) console.log('resultList._bindRecordsetEvents()');

            o.recordset.element.on('recordsetChanged.' + self.widgetName, function(e, data) {
                if (self._isSameRealm(o.recordset)) {
                    self._onRecordsetChanged(e, data);
                }
            });
            o.recordset.element.on('selectionChanged.' + self.widgetName, function(e, data) {
                if (self._isSameRealm(o.recordset)) {
                    self._onSelectionChanged(e, data);
                }
            });
            o.recordset.element.on('recordUpdated.' + self.widgetName, function(e, data) {
                if (self._isSameRealm(o.recordset)) {
                    self._onRecordUpdated(e, data);
                }
            });

            self._event_handlers_set = true;
        },

        /**
         * @function _unbindRecordsetEvents
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Unbinds all event handlers previously attached to the recordset.
         * This is important when the widget is destroyed or when the recordset is replaced
         * to prevent memory leaks and unintended behavior.
         */
        _unbindRecordsetEvents: function() {
            var self = this;
            var o = self.options;

            if (o.recordset && o.recordset.element && self._event_handlers_set) {
                if(o.debug) console.log('resultList._unbindRecordsetEvents()');
                o.recordset.element.off('.' + self.widgetName); // Unbind all namespaced events
            }
            self._event_handlers_set = false;
        },

        /**
         * @function _onRecordsetChanged
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Event handler for the `recordsetChanged` event from the recordset.
         * Typically triggers a full refresh of the list to display the new data, but only if
         * `options.search_on_recordset_change` is true. Updates total record count.
         * @param {jQuery.Event} e The event object.
         * @param {object} data Additional data passed with the event, potentially including
         * details about the change. (Currently unused in this method).
         */
        _onRecordsetChanged: function(e, data) {
            var self = this;
            var o = self.options;
            if (o.debug) console.log('resultList._onRecordsetChanged()', data);

            self._total_records_in_rs = o.recordset ? o.recordset.count() : 0; // Update total count
            self._nav_buttons_need_update = true; // Mark nav buttons for update

            if (o.search_on_recordset_change) {
                self._refresh();
            } else {
                // If not automatically refreshing, at least update nav buttons
                // as record count might have changed.
                self._updateNavButtons();
            }
        },

        /**
         * @function _onSelectionChanged
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Event handler for the `selectionChanged` event from the recordset.
         * Updates the visual selection state of affected record items in the list (adding/removing
         * 'h-selected' class). It also re-triggers a `selectionChanged` event on the widget itself.
         * @param {jQuery.Event} e The event object.
         * @param {object} data Additional data passed with the event, including:
         * @param {Array<number|string>} data.selectedIds Array of IDs of newly selected records.
         * @param {Array<number|string>} data.deselectedIds Array of IDs of newly deselected records.
         * @param {Array<number|string>} data.selection The complete list of currently selected record IDs.
         */
        _onSelectionChanged: function(e, data) {
            var self = this;
            var o = self.options;
            if (o.debug) console.log('resultList._onSelectionChanged()', data);

            // Update selected items
            if (data.selectedIds) {
                data.selectedIds.forEach(function(recId) {
                    if (self._record_divs[recId]) {
                        self._record_divs[recId].addClass('h-selected')
                            .find('input.h-record-select-checkbox').prop('checked', true);
                    }
                });
            }

            // Update deselected items
            if (data.deselectedIds) {
                data.deselectedIds.forEach(function(recId) {
                    if (self._record_divs[recId]) {
                        self._record_divs[recId].removeClass('h-selected')
                            .find('input.h-record-select-checkbox').prop('checked', false);
                    }
                });
            }
            // Forward the event
            self._trigger('selectionChanged', e, data);
        },

        /**
         * @function _onRecordUpdated
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Event handler for the `recordUpdated` event from the recordset.
         * If the updated record is currently displayed in the list, this method re-renders
         * that specific record item to reflect the changes.
         * @param {jQuery.Event} e The event object.
         * @param {object} data Additional data passed with the event, including:
         * @param {object} data.record The {@link heurist.HRecord} object that was updated.
         */
        _onRecordUpdated: function(e, data) {
            var self = this;
            var o = self.options;
            if (o.debug) console.log('resultList._onRecordUpdated()', data);

            var record = data.record;
            if (record && self._record_divs[record.getId()]) {
                var $oldItem = self._record_divs[record.getId()];
                var itemIndex = $oldItem.index(); // Get index relative to its siblings (for itemNo)
                                                // This might be tricky if items are not direct children or if in table.
                                                // A more robust way might be to find its original itemNo if stored.
                                                // For now, let's assume itemNo is less critical for an update in place.
                                                // Or, find the record's actual index in the *currently rendered sequence*
                var recordOverallIndex = -1;
                // A simple way if _last_rendered_record_idx is reliable and items are in order:
                // Iterate through visible items to find its display index.
                // However, for simplicity, let's assume we can find the record in the recordset
                // and determine its current display index if needed.
                // For now, we will just use the index of the DOM element being replaced.

                var $newItem;
                if (o.custom_renderers && typeof o.custom_renderers[self._view_mode] === 'function') {
                    $newItem = o.custom_renderers[self._view_mode](record, self);
                } else {
                    switch (self._view_mode) {
                        case 'list':
                            $newItem = self._renderRecord_html(record, 'list', itemIndex); // Pass index for itemNo
                            break;
                        case 'grid':
                            $newItem = self._renderRecord_html(record, 'grid', itemIndex);
                            break;
                        case 'table':
                            $newItem = self._renderRecord_tableRow(record, itemIndex);
                            break;
                        default:
                            return; // Should not happen
                    }
                }

                if ($newItem) {
                    $newItem.attr('data-record-id', record.getId());
                    $oldItem.replaceWith($newItem);
                    self._record_divs[record.getId()] = $newItem;
                    self._bindItemEvents($newItem); // Rebind events to the new element
                    self._trigger('itemUpdated', null, { record: record, element: $newItem });
                }
            }
        },

        /**
         * @function _bindScrollHandler
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Binds the scroll event handler (`_onScroll`) to the content container.
         * This is used for infinite scrolling/incremental loading of records when the user
         * scrolls near the bottom of the list. Ensures the handler is only bound once.
         */
        _bindScrollHandler: function() {
            var self = this;
            if (self._scroll_handler_bound) {
                return;
            }
            if (self.options.debug) console.log('resultList._bindScrollHandler()');
            // Debounce the scroll handler to avoid excessive calls during rapid scrolling
            self.content.on('scroll.resultList', $.proxy(self._onScroll, this)); // Using jQuery.proxy to maintain 'this' context
            self._scroll_handler_bound = true;
        },

        /**
         * @function _unbindScrollHandler
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Unbinds the scroll event handler from the content container.
         * This is called when the widget is destroyed or when scroll-based loading is no longer needed.
         */
        _unbindScrollHandler: function() {
            var self = this;
            if (!self._scroll_handler_bound) {
                return;
            }
            if (self.options.debug) console.log('resultList._unbindScrollHandler()');
            self.content.off('scroll.resultList');
            self._scroll_handler_bound = false;
        },

        /**
         * @function _onScroll
         * @memberof heurist.resultList
         * @instance
         * @private
         * @description Event handler for the scroll event on the content container.
         * Checks if the user has scrolled close to the bottom of the currently rendered content.
         * If so, and if there are more records to load and not already rendering, it calls
         * `_renderRecordsIncrementally` to load and display the next batch of records.
         * @param {jQuery.Event} e The scroll event object.
         */
        _onScroll: function(e) {
            var self = this;
            var o = self.options;

            if (self._rendering_incrementally || self._records_rendered_count >= self._total_records_in_rs) {
                return; // Don't do anything if already loading or all records are loaded
            }

            var $container = $(e.target); // This should be self.content
            var scrollTop = $container.scrollTop();
            var scrollHeight = $container.prop('scrollHeight');
            var containerHeight = $container.innerHeight(); // Use innerHeight for visible area

            // Check if scrolled near the bottom
            if (scrollHeight - (scrollTop + containerHeight) < o.scroll_load_threshold) {
                if (o.debug) console.log('resultList._onScroll: Reached scroll threshold, loading more records.');
                self._renderRecordsIncrementally();
            }
        }

    }); // end $.widget

})(jQuery);
