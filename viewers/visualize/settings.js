/**
* settings.js - Functions to handle the visualisation settings
*
* @fileOverview This file provides functions for managing visualization settings.
* It includes retrieving and storing settings (primarily using localStorage or Heurist user preferences),
* checking and initializing default settings, and handling UI interactions for modifying settings
* like line type, node size, colors, gravity, etc.
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4
* @todo Change storage of settings to user session (instead of current usage of localStorage for non-authenticated users).
*/

/* global svg, settings, currentMode, force, visualizeData, circleSize, maxEntityRadius, getEntityRadius,
updateCircles, updateRectangles, tick, maxLinkWidth, getLineWidth, getMarkerWidth, updateScalableElements,
onVisualizeResize */

/**
 * Global object to store visualization preferences if the user has access (is logged in).
 * Falls back to localStorage if user is not authenticated.
 * @type {object}
 */
window.preference_settings = window.hWin?.HAPI4 && window.hWin.HAPI4.has_access() ? window.hWin.HAPI4.get_prefs_def('vis_struct', {}) : {};

/**
* Returns the current displayed URL of the visualization page.
* @returns {string} The current URL.
*/
function getURL() {
    return window.location.href;
}

/**
 * Retrieves a setting value.
 * It first checks Heurist user preferences if the user is authenticated and the key is not numeric or a transform key.
 * Otherwise, it falls back to localStorage, prefixed with the database name.
 * If the value is not found and a default value is provided, the default is stored and returned.
 *
 * @param {string|number} key - The key of the setting to retrieve. If it starts with 'setting_', the prefix is removed.
 * @param {*} [defvalue] - The default value to return (and store) if the setting is not found.
 * @param {string} [split_string=''] - If provided and the retrieved value is a string, the string will be split by this delimiter.
 * @returns {*} The retrieved setting value, potentially split into an array, or the default value.
 */
function getSetting(key, defvalue, split_string = '') {

    let value;

    if(typeof key === 'string' && key.startsWith('setting_')){
        key = key.split('_');
        key.shift();
        key = key.join('_');
    }

    if(Object.keys(window.preference_settings).length === 0 && window.hWin?.HAPI4 && window.hWin.HAPI4.has_access()){
        window.preference_settings = window.hWin.HAPI4.get_prefs_def('vis_struct', {}); // attempt re-retrieval of settings
    }

    if(window.hWin?.HAPI4){

        if(window.hWin.HAPI4.has_access() && !window.hWin.HEURIST4.util.isNumber(key) && key.indexOf('translate') === -1 && key.indexOf('scale') === -1){

            value = Object.hasOwn(window.preference_settings, key) ? window.preference_settings[key] : localStorage.getItem(`${window.hWin.HAPI4.database}${key}`);

            if(!Object.hasOwn(window.preference_settings, key)){
                putSetting(key, value);
            }
        }else{
            value = localStorage.getItem(window.hWin.HAPI4.database+key);
        }
    }else{
        value = localStorage.getItem(window.hWin.HAPI4.database+key);
        value = typeof value === 'string' && window.hWin.HEURIST4.util.isJSON(value) ? window.hWin.HEURIST4.util.isJSON(value) : value;
    }

    if(window.hWin.HEURIST4.util.isempty(value) && !window.hWin.HEURIST4.util.isnull(defvalue)){
        value = defvalue;
        putSetting(key, value);
    }

    if(!window.hWin.HEURIST4.util.isempty(split_string) && typeof value === 'string'){
        value = value.split(split_string);
    }

    return value;
}

/**
* Stores a setting value.
* It first attempts to save to Heurist user preferences if the user is authenticated and the key is appropriate.
* Otherwise, it saves to localStorage, prefixed with the database name.
*
* @param {string|number} key - The key of the setting to store. If it starts with 'setting_', the prefix is removed for preference storage.
* @param {*} value - The value to store.
*/
function putSetting(key, value) {
    
    if(!window.hWin?.HAPI4 ){
        return;
    }

    if(window.hWin.HAPI4.has_access() && !window.hWin.HEURIST4.util.isNumber(key) && key.indexOf('translate') === -1 && key.indexOf('scale') === -1){

        let prefKey = key;
        if(typeof key === 'string' && key.startsWith('setting_')){
            prefKey = key.substring('setting_'.length);
        }

        window.preference_settings[prefKey] = value;
        window.hWin.HAPI4.save_pref('vis_struct', window.preference_settings);
    }else{
        value = typeof value === 'object' ? JSON.stringify(value) : value;
        localStorage.setItem(window.hWin.HAPI4.database+key, value);
    }
}

/**
* Removes a setting from localStorage.
* Note: This does not currently affect Heurist user preferences.
* @param {string|number} key - The key of the setting to remove from localStorage.
*/
function removeSetting(key){
    if(window.hWin?.HAPI4 ){
        localStorage.removeItem(window.hWin.HAPI4.database+key);
    }
}

/**
 * Ensures that default settings are present by calling `getSetting` for each,
 * which will store the default if the setting doesn't exist.
 * This relies on the `settings` object (presumably from the main visualize plugin)
 * to provide the initial default values if not found in storage.
 */
function checkStoredSettings() {
    getSetting(   'setting_linetype', settings.linetype    );
    getSetting(   'setting_line_empty_link', settings.line_empty_link );
    getSetting(   'setting_linelength',    200); //settings.linelength  );
    getSetting(   'setting_linewidth',     2); //settings.linewidth   );
    getSetting(   'setting_linecolor',     'blue'); //settings.linecolor   );
    getSetting(   'setting_markercolor',   settings.markercolor );
    getSetting(   'setting_entityradius',  settings.entityradius);
    getSetting(   'setting_entitycolor',   settings.entitycolor );
    getSetting(   'setting_labels',        settings.labels      );
    getSetting(   'setting_fontsize',      settings.fontsize    );
    getSetting(   'setting_textlength',    settings.textlength  );
    getSetting(   'setting_textcolor',     settings.textcolor   );
    getSetting(   'setting_formula',       settings.formula     );
    getSetting(   'setting_gravity',       settings.gravity     );
    getSetting(   'setting_attraction',    settings.attraction  );
    getSetting(   'setting_fisheye',       settings.fisheye     );
    getSetting(   'setting_translatex',    settings.translatex  );
    getSetting(   'setting_translatey',    settings.translatey  );
    getSetting(   'setting_scale',         settings.scale       );
    getSetting(   'setting_advanced',      settings.advanced    );
}

/**
* Initializes the user interface elements for controlling visualization settings.
* This includes setting up sliders, color pickers, radio buttons, and checkboxes
* for various options like node size, line appearance, labels, and interaction modes.
* It binds event handlers to these UI elements to update settings and refresh the visualization.
*/
function handleSettingsInUI() {

    //add elements on toolbar
    let tBar = $('#toolbar');

    let is_advanced = getSetting('setting_advanced');

    $('#setAdvancedMode').css({cursor:'pointer'}).on('click.visualiser',
        function(){
              let is_advanced_current = getSetting('setting_advanced'); // Use a different variable name to avoid confusion
              is_advanced_current = (is_advanced_current ==='false'); // Strict comparison
                if(is_advanced_current){
                    $('.advanced').show();
                    $('#setAdvancedMode').find('a').hide();
                    if(settings.isDatabaseStructure){
                        $('#setDivExport').hide();
                    }
                }else{
                    $('.advanced').hide();
                    $('#setAdvancedMode').find('a').show();
                }
              putSetting('setting_advanced', is_advanced_current);
              onVisualizeResize();
        }
    );

    if(is_advanced !=='false'){ // Strict comparison
        $('.advanced').show();
        $('#setAdvancedMode').find('a').hide();
        if(settings.isDatabaseStructure){
            $('#setDivExport').hide();
        }
    }else{
        $('.advanced').hide();
        $('#setAdvancedMode').find('a').show();
    }

    // Initialize UI buttons for zoom and refresh
    $('#btnZoomIn').button({icon:'ui-icon-circle-b-plus',showLabel:false}).on('click',
        function(){ zoomBtn(true); }
    );
    $('#btnZoomOut').button({icon:'ui-icon-circle-b-minus',showLabel:false}).on('click',
        function(){ zoomBtn(false); }
    );
    $('#btnFitToExtent').button({icon:'ui-icon-fullscreen',showLabel:false}).on('click',
        function(){ zoomToFit(); }
    );
    $('#btnRefreshData').button({icon:'ui-icon-refresh'}).on('click',
        function(){ location.reload(); } // Simple page reload for refresh
    );

    // Export button
    if(settings.isDatabaseStructure || (isStandAlone && !settings.minimal)){
        $('#embed-export').css('visibility','hidden');
    }else{
        $('#embed-export').button({icon:'ui-icon-globe',showLabel:false}).on('click', showEmbedDialog);
    }

    //-------------------------------

    $('#btnSingleSelect').button({icon:'ui-icon-cursor' , showLabel:false})
        .on('click.visualiser', function(){ window.selectionMode = 'single'; $("#d3svg").css("cursor", "default"); _syncUI();});
    $('#btnMultipleSelect').button({icon: 'ui-icon-select', showLabel:false})
        .on('click.visualiser', function(){ window.selectionMode = 'multi'; $("#d3svg").css("cursor", "crosshair"); _syncUI();});
    $('#setSelectMode').controlgroup();

    $('#btnViewModeIcon').button({icon: 'ui-icon-circle' , showLabel:false})
        .on('click.visualiser', function(){changeViewMode('icons');} );
    $('#btnViewModeInfo').button({icon: 'ui-icon-circle-b-info' , showLabel:false})
        .on('click.visualiser', function(){changeViewMode('infoboxes');} );
    $('#btnViewModeFull').button({icon: 'ui-icon-circle-info' , showLabel:false})
        .on('click.visualiser', function(){changeViewMode('infoboxes_full');} );
    $( "#setViewMode" ).controlgroup();

    $('#gravityMode0').button()
        .on('click.visualiser', () => setGravity('off') );
    $('#gravityMode1').button()
        .on('click.visualiser', () => setGravity('touch') );
    $('#gravityMode2').button()
        .on('click.visualiser', () => setGravity('aggressive') );
    $("#setGravityMode").controlgroup();

    //------------ NODES ----------

    let radius = getSetting('setting_entityradius');
    if(radius<circleSize) radius = circleSize;  //min
    else if(radius>maxEntityRadius) radius = maxEntityRadius; //max
    $('#nodesRadius').val(radius).on('change.visualiser', function(event){ // Added event parameter
        putSetting('setting_entityradius', $(event.target).val());
        window.d3.selectAll(".node > .background").attr("r", function(d) { // This will apply to all nodes, not just the one being changed if that's the intent
                        return getEntityRadius(d.count);
                    });
    });

    $('#nodesMode0').button().css('width','35px')
        .on('click.visualiser', function(){ setFormulaMode('linear'); });
    $('#nodesMode1').button().css('width','40px')
        .on('click.visualiser', function(){ setFormulaMode('logarithmic'); });
    $('#nodesMode2').button().css('width','50px')
        .on('click.visualiser', function(){ setFormulaMode('unweighted'); });
    $( "#setNodesMode" ).controlgroup();

    if($('#entityColor').length > 0){
        $("#entityColor")
        .val(getSetting('setting_entitycolor'))
        .colorpicker({
                        hideButton: false, //show button right to input
                        showOn: "button",
                        val:getSetting('setting_entitycolor')})
        .on('change.color', function(event, color){ // Added event parameter
            if(color){
                putSetting('setting_entitycolor', color);
                updateCircles(".node", null, false);
                updateRectangles(".node", getSetting('setting_entitycolor'));
                visualizeData(); // Consider if a full redraw is always needed
            }
        });
    }

    //------------ LINKS ----------

    $('#linksMode0').button({icon: 'ui-icon-link-straight', showLabel:false})
        .on('click.visualiser', function(){ setLinkMode('straight');} );
    $('#linksMode1').button({icon: 'ui-icon-link-curved', showLabel:false})
        .on('click.visualiser', function(){ setLinkMode('curved');} );
    $('#linksMode2').button({icon: 'ui-icon-link-stepped', showLabel:false})
        .on('click.visualiser', function(){ setLinkMode('stepped');} );

    $('#linksEmpty').on('change.visualiser', function(e){
        putSetting('setting_line_empty_link', $(e.target).is(':checked')?1:0);
        visualizeData();
        _syncUI();
    });
	$('#expand-links').on('change.visualiser', function(){ // expand single links
        tick();
	});
    if(settings.isDatabaseStructure){ // show all links by default for database structure vis
        $('#expand-links').prop('checked', true);
    }

    $( "#setLinksMode" ).controlgroup();

    putSetting('setting_linecolor', '#0070c0');  // Default override
    setLinkMode('straight'); // Default override
    //_syncUI(); // Called later

    let linksLength = 200; //getSetting('setting_linelength', 200); // Use stored or default
    $('#linksLength').val(linksLength).on('change.visualiser', function(event){ // Added event parameter
        let newval = $(event.target).val();
        putSetting('setting_linelength', newval);
        if(getSetting('setting_gravity') != "off"){ // Only redraw if gravity might be affected
            visualizeData();
        }
    });

    let linksWidth = 2; //getSetting('setting_linewidth', 2); // Use stored or default
    if(linksWidth<1) linksWidth = 1;  //min
    else if(linksWidth>maxLinkWidth) linksWidth = maxLinkWidth; //max

    $('#linksWidth').val(linksWidth).on('change.visualiser',
    function(event){ // Added event parameter
        let newval = $(event.target).val();
        putSetting('setting_linewidth', newval);
        refreshLinesWidth();
    });

    $("#linksPathColor")
        .css({'font-size':'1.8em','font-weight':'bold','color':getSetting('setting_linecolor')})
        .on('click.visualiser', function(e){
                window.hWin.HEURIST4.util.stopEvent(e); // Prevent default if it's a link
                $("#linksPathColor_inpt").colorpicker("showPalette");
        });

    $("#linksPathColor_inpt")
        .val('blue') // getSetting('setting_linecolor')
        .colorpicker({
                        hideButton: true, //show button right to input
                        showOn: "both", // Show on focus and button click
                        val:getSetting('setting_linecolor')})
        .on('change.color', function(event, color){ // Added event parameter
            if(color){
                putSetting('setting_linecolor', color);
                $(".bottom-lines.link").attr("stroke", color); // Update existing lines
                $('#linksPathColor').css('color', color); // Update display icon
                visualizeData(); // Redraw might be needed if new lines adopt this color
            }
        });


    $("#linksMarkerColor")
        .addClass('ui-icon ui-icon-triangle-1-e') // Standard jQuery UI icon
        .css({'color':getSetting('setting_markercolor')})
        .on('click.visualiser', function(e){
                window.hWin.HEURIST4.util.stopEvent(e);
                $("#linksMarkerColor_inpt").colorpicker("showPalette");
        });

    $("#linksMarkerColor_inpt")
        .val(getSetting('setting_markercolor'))
        .colorpicker({
                        hideButton: true, //show button right to input
                        showOn: "focus", // Show only on focus
                        val:getSetting('setting_markercolor')})
        .on('change.color', function(event, color){ // Added event parameter
            if(color){
                putSetting('setting_markercolor', color);
                $("marker").attr("fill", color); // Update existing markers
                $('#linksMarkerColor').css('color', color); // Update display icon
                visualizeData(); // Redraw might be needed
            }
        });


    //------------ LABELS ----------

    putSetting('setting_labels', 'on'); // Default override: labels always on initially
    $('#textOnOff').attr('checked', true).on('change.visualiser', function(event){ // Added event parameter

        let newval = $(event.target).is(':checked')?'on':'off';
        putSetting('setting_labels', newval);

        if(window.currentMode=='icons'){
            let isLabelCurrentlyVisible = (newval=='on'); // Use newval

            if(isLabelCurrentlyVisible) {
                visualizeData(); // Redraw to show labels
            }else{
                window.d3.selectAll(".nodelabel").style('display', 'none'); // Hide labels
            }
        }
        // If not in 'icons' mode, labels are typically part of infoboxes which are handled by changeViewMode
    });

    let textLength = getSetting('setting_textlength', 200);
    $('#textLength').val(textLength).on('change.visualiser', function(event){ // Added event parameter
        let newval = $(event.target).val();
        putSetting('setting_textlength', newval);
        let isLabelCurrentlyVisible = (window.currentMode!='icons' || (getSetting('setting_labels', 'on')=='on'));
        if(isLabelCurrentlyVisible) visualizeData(); // Redraw if labels are potentially visible
    });


    let fontSize = getSetting('setting_fontsize', 12);
    if(isNaN(fontSize) || fontSize<8) fontSize = 8;  //min
    else if(fontSize>25) fontSize = 25; //max

    $('#fontSize').val(fontSize).on('change.visualiser', function(event){ // Added event parameter
        let newval = $(event.target).val();
        putSetting('setting_fontsize', newval);
        let isLabelCurrentlyVisible = (window.currentMode!='icons' || (getSetting('setting_labels', 'on')=='on'));
        if(isLabelCurrentlyVisible) visualizeData(); // Redraw if labels are potentially visible
    });

    // Mini toolbar controls
    if(settings.minimal){
        initialiseMiniToolbar();
    }

    if(settings.isDatabaseStructure){
        initRecTypeSelector();
        $('#setDivExport').hide();
    }else{
        $('#setDivExport').show();
        $('#gephi-export').button();
    }

    tBar.show();
}

function initialiseMiniToolbar(){

    $('#showRecordTitles').prop('checked', true).on('change.visualiser', () => { // show node titles
        svg.selectAll('text.nodelabel.namelabel').style('display', $('#showRecordTitles').is(':checked') ? '' : 'none');
    });

    $('#recTitleSize').val(9).on('change.visualiser', () => updateScalableElements('labels'));
    $('#nodeSize').val(window.circleSize).on('change.visualiser', () => updateScalableElements('all'));
    $('#labelLength').val(20).on('change.visualiser', () => {
        const labelLength = $('#labelLength').val();
        const labels = window.d3.selectAll('.nodelabel');

        labels.text((d) => truncateText(d.fullName, labelLength));
    });

    $('#lnkOpenPopup').on('click', () => {

        if(typeof window.visualiserRequest !== 'string'){
            return;
        }

        if(!window.visualiserID){
            window.visualiserID = Math.round(Math.random() * 9000);
            window.addEventListener('beforeunload', () => localStorage.removeItem(`visConnection${window.visualiserID}`));
        }
        const visID = window.visualiserID;
        localStorage.setItem(`visConnection${visID}`, JSON.stringify({q: window.visualiserRequest, timestamp: Date.now()}) );

        const URL = `${window.hWin.HAPI4.baseURL}viewers/visualize/springDiagram.php?db=${window.hWin.HAPI4.database}&visID=${visID}&mini=2`;
        window.hWin.HEURIST4.msg.showDialog(URL, {title: `Record Network Graph`, ok: window.hWin.HR('Cancel'), width: 900, height: 900,
            onOpen: function(event, ui){

                let $dialog = $(this);
                let $dialogContainer = $dialog.parent();
                let $toolbar = $dialogContainer.find('.ui-dialog-titlebar');

                let $btnFullscreen = $('<button>', {
                    class: 'ui-dialog-titlebar-close',
                    title: 'Enlarge popup to maximum size',
                    style: 'right: 3.3em;'
                }).button({icon: 'ui-icon-fullscreen', showLabel: false}).appendTo($toolbar);

                let $btnNewTab = $('<button>', {
                    class: 'ui-dialog-titlebar-close',
                    title: 'Open diagram in a new tab',
                    style: 'right: 6.3em;'
                }).button({icon: 'ui-icon-newwin', showLabel: false}).appendTo($toolbar);

                $btnFullscreen.on('click', () => {

                    let width = window.hWin.innerWidth * 0.9;
                    let height = window.hWin.innerHeight * 0.9;

                    $dialogContainer.width(width);
                    $dialog.height(height);

                    $dialogContainer.position({my: 'center', at: 'center', of: window.hWin});
                });

                $btnNewTab.on('click', () => {

                    localStorage.setItem(`visConnection${visID}`, JSON.stringify({q: window.visualiserRequest, timestamp: Date.now()}) );

                    const URL = `${window.hWin.HAPI4.baseURL}viewers/visualize/springDiagram.php?db=${window.hWin.HAPI4.database}&visID=${visID}&mini=3`;
                    window.open(URL, '_blank');
                });
            }
        });
    });

    $('#showSubToolbar').on('click.visualiser', () => {
        $('#showSubToolbar').hide('slide', {direction: 'left'}, 400, () => $('.dropdown-subbar').show('slide', {direction: 'left'}));
        setTimeout(setupAutoHideToolbar, 500);
    });
    $('#hideSubToolbar').on('click.visualiser', () => {
        $('.dropdown-subbar').hide('slide', {direction: 'left'}, 400, () => $('#showSubToolbar').show('slide', {direction: 'left'}));
    });

    $('#gravityAmount').val(1).on('change.visualiser', () => {

        let amount = Number.parseFloat($('#gravityAmount').val());
        if(!window.force || Number.isNaN(amount) || amount < 0){
            return;
        }
        amount *= 0.1; // get ten percent

        // Apply new gravity
        const gravity = getSetting('setting_gravity');
        window.force.gravity(amount);
        if(gravity !== 'off'){
            window.force.resume();
        }
    });

    if(window.hWin.HEURIST4.util.isFunction(settings.onExpandLevel)){
        $('.graphLevelControl').on('click.visualiser', (e) => handleGraphLeveler(e));
    }else{
        $('#expandedLevels').hide();
    }

    $('#showThematicSettings').on('click.visualiser', () => {
        if($('#thematicSettings').is(':visible')){
            return;
        }
        $('#showThematicContainer').trigger('click')
    });
}

/**
 * Initializes the record type selector panel for database structure visualizations.
 * Sets up a jQuery UI Layout for the west panel containing the list of record types.
 * Handles opening the panel if `window.startup_rectype` indicates it.
 * @private
 */
function initRecTypeSelector(){

    let hidePane = window.startup_rectype != 1; // Check if panel should be initially hidden
    delete window.startup_rectype; // Clean up global flag

    let layout_options = {
        applyDefaultStyles: true,
        center:{
            size: $('#main_content').width(), // Ensure this is calculated correctly at time of call
            contentSelector: '#main_content'
        },
        west:{
            size:400,
            maxWidth:400,
            spacing_open:15,
            spacing_closed:15,
            togglerAlign_open:40, // button top value
            togglerAlign_closed:40,
            initClosed:true, // Default to closed
            slidable:false,  // disable sliding
            resizable:false, // disable resizing
            contentSelector: '#list_rectypes',
            onopen_end: function(){
                $('#list_rectypes').show();
                $('#lblShowRectypeSelector').show();
            },
            onclose_start: function(){ // Use onclose_start to hide before animation
                $('#list_rectypes').hide();
                $('#lblShowRectypeSelector').hide();
            },
            togglerContent_open: '<div class="ui-icon ui-icon-carat-2-w" style="margin-left: 0px;font-size:20px;"></div>',
            togglerContent_closed: '<div class="ui-icon ui-icon-carat-2-e" style="font-size:20px;"></div>'
        }
    };

    let layout = $($('body.popup div.layout-container')[0]).layout(layout_options);

    if(!hidePane){ // If should not be hidden (i.e., startup_rectype was 1)
        setTimeout(function(){ // Delay to ensure layout is fully initialized
            layout.open('west');
            // onopen_end should handle showing elements

            let refresh_chkbx_selector = window.trigger_checkbox_refresh; // Store before deleting
            if(!window.hWin.HEURIST4.util.isempty(refresh_chkbx_selector)){
                $(`#list_rectypes ${refresh_chkbx_selector}`).trigger('change');
                delete window.trigger_checkbox_refresh; // Clean up global flag
            }
        }, 1000); // Adjust delay if needed
    }
}

/**
 * Synchronizes the visual state of toolbar buttons (e.g., selection mode, view mode, gravity)
 * with the current settings by adding/removing a specific CSS class.
 * @private
 */
function _syncUI(){

    $('#toolbar').find('button').removeClass('ui-heurist-btn-selected'); // Base class for styling active buttons

    $('#toolbar').find('button[value="'+window.selectionMode+'"]').addClass('ui-heurist-btn-selected');
    $('#toolbar').find('button[value="'+window.currentMode+'"]').addClass('ui-heurist-btn-selected');

    let grv = getSetting('setting_gravity','off');
    if(grv == 'aggressive' && !settings.minimal) grv = 'touch'; // Normalize 'aggressive' to 'touch' for UI
    $('#toolbar').find('button[name="gravityMode"][value="'+grv+'"]').addClass('ui-heurist-btn-selected');

    let formula = getSetting('setting_formula','linear');
    $('#toolbar').find('button[name="nodesMode"][value="'+formula+'"]').addClass('ui-heurist-btn-selected');

    let linetype = 'straight'; //getSetting('setting_linetype', 'straight'); 
    $('#toolbar').find('button[name="linksMode"][value="'+linetype+'"]').addClass('ui-heurist-btn-selected');

    let is_show_empty = (getSetting('setting_line_empty_link', 1)==1);
    $('#toolbar').find('#linksEmpty').prop('checked', is_show_empty);

}

/**
 * Changes the view mode of the visualization (icons, infoboxes, infoboxes_full).
 * Updates the display style of nodes, labels, and overlays accordingly.
 *
 * @param {string} mode - The target view mode: 'icons', 'infoboxes', or 'infoboxes_full'.
 */
function changeViewMode(mode){
    $(".offset_line").remove(); // Remove any offset lines (related to link drawing?)
    mode = ['icons', 'infoboxes', 'infoboxes_full'].indexOf(mode) === -1 ? 'icons' : mode; // default to icon mode
    if(mode!=window.currentMode){
        window.currentMode = mode; // Update global current mode

        if(mode=='infoboxes'){
            window.d3.selectAll(".info-mode").style('display', 'initial');
            window.d3.selectAll(".info-mode-full").style('display', 'none');
            window.d3.selectAll("line.inner_divider").style('display', 'none');

            window.d3.selectAll(".rect-info-full").style('display', 'none');
            window.d3.selectAll(".rect-info").style('display', 'initial');

            window.d3.selectAll("circle.icon-background, circle.icon-foreground, image.node-icon").style('display', 'none');
            window.d3.selectAll("text.nodelabel.namelabel").attr("x", 10); // Adjust label position

        }else if(mode=='infoboxes_full'){
            window.d3.selectAll(".info-mode").style('display', 'initial');
            window.d3.selectAll(".info-mode-full").style('display', 'initial');
            window.d3.selectAll("line.inner_divider").style('display', 'initial');

            window.d3.selectAll(".rect-info-full").style('display', 'initial');
            window.d3.selectAll(".rect-info").style('display', 'none');

            window.d3.selectAll("circle.icon-background, circle.icon-foreground, image.node-icon").style('display', 'none');
            window.d3.selectAll("text.nodelabel.namelabel").attr("x", 10); // Adjust label position

        }else{ // mode == 'icons'
            window.d3.selectAll(".info-mode, .info-mode-full, line.inner_divider").style('display', 'none');
            window.d3.selectAll("circle.icon-background, circle.icon-foreground, image.node-icon").style('display', 'initial');
            window.d3.selectAll("text.nodelabel.namelabel").attr("x", 29); // Adjust label position for icons
        }

        // Handle label visibility based on current mode and label setting
        let isLabelCurrentlyVisible = window.currentMode == 'icons' || getSetting('setting_labels')=='on';
        window.d3.selectAll(".nodelabel").style('display', isLabelCurrentlyVisible?'block':'none');

        // Close any open menus on nodes (related to overlays)
        $.each(window.d3.selectAll("image.menu-open")[0], function(idx, ele){
            let event = new MouseEvent("mouseup"); // Simulate mouseup to trigger close
            ele.dispatchEvent(event);
        });

        _syncUI(); // Update toolbar button states
        tick(); // Apply force layout changes
        updateScalableElements('all'); // update scaling for all scalable elements
    }
}

/**
 * Sets the gravity mode for the force layout.
 * Updates the 'fixed' attribute of nodes and resumes the force layout if not 'off'.
 *
 * @param {string} gravity - The gravity mode: 'off', 'touch', or 'aggressive'.
 */
function setGravity(gravity) {

    putSetting('setting_gravity',  gravity);

    if(window.hWin.HEURIST4.util.isPositiveInt(window.gravityTimeout)){
        clearTimeout(window.gravityTimeout);
        window.gravityTimeout = null;
    }

    let updateFixedValues = (mode) => {

        // Update gravity impact on nodes
        svg.selectAll('.node').attr('fixed', (d) => {

            if(mode == 'aggressive'){
                d.fixed = false;
                return false;
            }

            // 'off' or 'touch'
            d.fixed = true;
            return true;
        });

        if(gravity !== "off"){
            force.resume();
        }
    };

    if(gravity == 'touch'){

        updateFixedValues('aggressive'); // appoly gravity, for a moment
        window.gravityTimeout = clearTimeout(window.gravityTimeout); // remove gravity timeout from above call

        setTimeout(() => {
            updateFixedValues('touch'); // apply requested gravity
        }, 1000);
    }else{
        updateFixedValues(gravity);
    }

    _syncUI();
}

/**
 * Sets the formula mode for determining node radius (linear, logarithmic, unweighted).
 * Updates the radius of existing nodes and refreshes line widths (as they might depend on node size indirectly).
 *
 * @param {string} formula - The formula mode: 'linear', 'logarithmic', or 'unweighted'.
 */
function setFormulaMode(formula) {
    putSetting('setting_formula', formula);
    // visualizeData(); // Potentially too heavy
    window.d3.selectAll(".node > .background").attr("r", function(d) {
                        return getEntityRadius(d.count); // Re-calculate radius based on new formula
                    });
    refreshLinesWidth(); // Line widths might change if they are relative to node sizes or if formula affects perceived density
    _syncUI();
}

/**
 * Refreshes the stroke width of lines and the size of markers based on current settings.
 * Typically called when node size formula or line width settings change.
 * @private
 */
function refreshLinesWidth(){

    window.d3.selectAll(".bottom-lines").style("stroke-width",
            function(d) { return getLineWidth(d.targetcount); });

    window.d3.selectAll("marker").attr("markerWidth", function(d) {
                    return getMarkerWidth(d?d.targetcount:0); // Ensure 'd' exists
                })
                .attr("markerHeight", function(d) {
                    return getMarkerWidth(d?d.targetcount:0); // Ensure 'd' exists
                });

}


/**
 * Sets the line type for links (straight, curved, stepped).
 * Triggers a redraw of the visualization.
 *
 * @param {string} linetype - The line type: 'straight', 'curved', or 'stepped'.
 */
function setLinkMode(linetype) { // Renamed parameter from formula to linetype for clarity
    putSetting('setting_linetype', linetype);
    visualizeData(); // Redraw to apply new line type
    _syncUI();
}

const GRAPH_EXTEND_LIMIT = 10;
function handleGraphLeveler(event){

    const currentQuery = window.visualiserRequest;
    let parts = typeof currentQuery === 'string' ? currentQuery.split(':') : [];
    if(parts.length !== 2 || parts[0] !== 'ids'){
        return;
    }

    let action = $(event.target).attr('data-value');
    let currentLevel = Number.parseInt(localStorage.getItem('extendedLevel'));

    // Limit expand to first 10 levels (for now)
    if(currentLevel < 0 || Number.isNaN(currentLevel)){
        currentLevel = 0;
    }else if(currentLevel > GRAPH_EXTEND_LIMIT - 1){
        currentLevel = GRAPH_EXTEND_LIMIT - 1;
    }

    if((currentLevel === 0 && action === 'decrease') || (currentLevel === GRAPH_EXTEND_LIMIT - 1 && action === 'increase')){
        window.hWin.HEURIST4.msg.showMsgFlash(`Cannot ${action === 'decrease' ? 'shrink' : 'grow'} graph any further...`, 3000);
        if(action === 'decrease'){
            $('#decreaseGraphLevel').addClass('ui-state-disabled');
        }else{
            $('#increaseGraphLevel').addClass('ui-state-disabled');
        }
        return;
    }

    currentLevel += (action === 'increase' ? 1 : -1);
    let levelLabel = currentLevel + 1;

    let currentRecIDs = parts[1].split(',').map((x) => +x);

    if(action === 'decrease'){ // Trim recent leaves
        if(settings.onExpandLevel.call(this, 'decrease', currentRecIDs)){
            updateExpanderUI(levelLabel, currentLevel);
        }
        return;
    }

    let results = settings.onExpandLevel.call(this, 'nextLevel', currentRecIDs);
    if(!results){
        return;
    }

    expanderPopup(currentLevel, levelLabel, results);
}

function updateExpanderUI(levelLabel, currentLevel){

    document.querySelector('#graphLevel').innerHTML = levelLabel;
    localStorage.setItem('extendedLevel', currentLevel);

    if(currentLevel == GRAPH_EXTEND_LIMIT - 1){
        $('#increaseGraphLevel').addClass('ui-state-disabled');
    }else{
        $('#increaseGraphLevel').removeClass('ui-state-disabled');
    }

    if(currentLevel == 0){
        $('#decreaseGraphLevel').addClass('ui-state-disabled');
    }else{
        $('#decreaseGraphLevel').removeClass('ui-state-disabled');
    }
}

function expanderPopup(currentLevel, nextLevel, extensionResults){

    let expanderContainer = document.querySelector('#expanderSettings');
    let $expanderContainer = $(expanderContainer);
    let optionsContainer = expanderContainer.querySelector('#expanderOptions');

    let newRecords = extensionResults.new;
    let extensionAvailable = extensionResults.extending;

    if(!expanderContainer || !optionsContainer){
        return;
    }

    expanderContainer.querySelector('#expandPrev').innerText = currentLevel;
    expanderContainer.querySelector('#expandNext').innerText = nextLevel;
    optionsContainer.innerHTML = '';

    let recordTypes = new Map();

    if(expanderContainer.getAttribute('data-inited') !== '1'){

        let removeHighlight = () => {

            $expanderContainer.hide('slide', {direction: 'left'});

            let highlightedNodes = document.querySelectorAll('.expandingNode');
            if(!highlightedNodes){
                return;
            }

            for(let i = 0; i < highlightedNodes.length; i++){
                highlightedNodes[i].classList.remove('expandingNode');
            }
        };

        $expanderContainer.find('#btnExpanderCommit').button().on('click', () => {

            let selectedRecTypes = expanderContainer.querySelectorAll('input[type="checkbox"]:checked');
            if(!selectedRecTypes){
                $expanderContainer.hide('slide', {direction: 'left'});
            }

            let newRecIDs = new Set();
            for(let i = 0; i < selectedRecTypes.length; i++){

                let rtyID = Number.parseInt(selectedRecTypes[i].value);
                if(!window.hWin.HEURIST4.util.isPositiveInt(rtyID)){
                    continue;
                }

                let recIDs = recordTypes.get(rtyID);
                if(!recIDs){
                    continue;
                }

                newRecIDs = newRecIDs.union(recIDs);
            }

            removeHighlight();
            updateExpanderUI(nextLevel, currentLevel);
            window.extendableNodes = null;

            settings.onExpandLevel.call(this, 'increase', newRecIDs);
        });
        $expanderContainer.find('#btnExpanderCancel').button().on('click', () => {
            removeHighlight();
            window.extendableNodes = null;
        });
        $expanderContainer.find('#closeExpanderSettings').button().on('click', () => {
            removeHighlight();
            window.extendableNodes = null;
        });

        $expanderContainer.attr('data-inited', 1);
    }

    for(const recID of newRecords){

        if(!Object.hasOwn(nodeRecTypes, recID)){
            continue;
        }
        const rtyID = nodeRecTypes[recID];

        let currentRecIDs = recordTypes.get(rtyID);
        if(!currentRecIDs){
            recordTypes.set(rtyID, new Set([recID]));
        }else{
            currentRecIDs.add(recID);
            recordTypes.set(rtyID, currentRecIDs);
        }
    }

    let hasRow = false;
    for(const [rtyID, recIDs] of recordTypes){

        let optionRow = document.createElement('tr');
        let rtyName = $Db.rty(rtyID, 'rty_Name');

        if(!rtyName){
            continue;
        }
        rtyName = rtyName.length > 20 ? rtyName.slice(0, 20) + '...' : rtyName;

        let content = `
        <td>
            <input type="checkbox"${recIDs.size <= 25 ? ' checked="checked"' : ''} name="nodeTypes" value="${rtyID}" />
        </td>
        <td>
            ${rtyName}
        </td>
        <td>
            ${recIDs.size}
        </td>`;

        optionRow.innerHTML = content;

        optionsContainer.append(optionRow);

        hasRow = true;
    }

    if(hasRow){
        $expanderContainer.show('slide', {direction: 'left'}); // show popup

        for(const recID in extensionAvailable){
            if(!Object.hasOwn(extensionAvailable, recID) || !document.querySelector(`.id${recID}`)){
                continue;
            }
            document.querySelector(`.id${recID}`).classList.add('expandingNode');
        }

        window.extendableNodes = extensionAvailable;
    }
}