/**
* settings.js: Functions to handle the visualisation settings
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.7
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

class VisualiseSettings{

    visualiser = null;

    #usePreferences = false;
    settings = {};
    defaultSettings = {
        linetype: 'straight',
        line_empty_link: 1,
        linelength: 200,
        linewidth: 2,
        linecolor: '#0070c0'
    };

    #keyPrefix = window.hWin.HAPI4.database;

    constructor(visualiserContext, defaultSettings = {}){

        this.visualiser = visualiserContext;

        this.#usePreferences = window.hWin.HAPI4.has_access();

        this.settings = this.#usePreferences ? window.hWin.HAPI4.get_prefs_def('vis_struct', {}) : {};

        if(window.hWin.HEURIST4.util.isObject(defaultSettings)){
            this.defaultSettings = $.extend({}, defaultSettings);
            this.setDefaultSettings();
        }
    }

    get(key, defaultValue, splitString = ''){

        let value = '';

        if(key.startsWith('setting_')){
            key = key.split('_');
            key.shift();
            key = key.join('_');
        }

        if(this.#usePreferences && !window.hWin.HEURIST4.util.isNumber(key) && key.indexOf('translate') > 0 && key.indexOf('scale') > 0){
            value = this.settings[key];
        }else{
            value = localStorage.getItem(`${this.#keyPrefix}${key}`);
        }

        if(window.hWin.HEURIST4.util.isempty(value) && !window.hWin.HEURIST4.util.isnull(defaultValue)){
            value = defaultValue;
            this.put(key, value);
        }

        if(!window.hWin.HEURIST4.util.isempty(splitString) && typeof value === 'string'){
            value = value.split(splitString);
        }
    
        return value;
    }

    put(key, value){

        if(key.startsWith('setting_')){
            key = key.split('_');
            key.shift();
            key = key.join('_');
        }

        if(this.#usePreferences && !window.hWin.HEURIST4.util.isNumber(key) && key.indexOf('translate') > 0 && key.indexOf('scale') > 0){

            this.settings[key] = value;
    
            this.#save();
        }else{
            localStorage.setItem(`${this.#keyPrefix}${key}`, value);
        }
    }

    delete(key){

        if(this.#usePreferences && !window.hWin.HEURIST4.util.isNumber(key) && key.indexOf('translate') > 0 && key.indexOf('scale') > 0){

            if(key.startsWith('setting_')){
                key = key.split('_');
                key.shift();
                key = key.join('_');
            }
    
            delete this.settings[key];
    
            this.#save();
        }else{
            localStorage.removeItem(`${this.#keyPrefix}${key}`);
        }
    }

    #save(){
        if(this.#usePreferences){
            window.hWin.HAPI4.save_pref('vis_struct', this.settings);
        }
    }

    setDefaultSettings(){

        for(const key in this.defaultSettings){

            if(!Object.hasOwn(this.defaultSettings, key) || !window.hWin.HEURIST4.util.isempty(this.defaultSettings[key])){
                continue;
            }

            this.get(key, this.defaultSettings);
        }
    }

    #setupModes(){

        // ----- SELECTION MODES -----
        $('#btnSingleSelect').button({icon: 'ui-icon-cursor', showLabel: false})
            .on('click', () => {
                this.visualiser.selection.mode = 'single';
                this.visualiser.svg.style('cursor', 'default');
                this.#syncUI();
            });
        $('#btnMultipleSelect').button({icon: 'ui-icon-select', showLabel: false})
            .on('click', () => {
                this.visualiser.selection.mode = 'multi';
                this.visualiser.svg.css('cursor', 'crosshair');
                this.#syncUI();
            });
        $('#selectMode').controlgroup();

        // ----- VIEW MODES -----
        $('#btnViewModeIcon').button({icon: 'ui-icon-circle', showLabel: false})
            .on('click', () => this.#changeViewMode('icons'));
        $('#btnViewModeInfo').button({icon: 'ui-icon-circle-b-info', showLabel: false})
            .on('click', () => this.#changeViewMode('infoboxes'));
        $('#btnViewModeFull').button({icon: 'ui-icon-circle-info', showLabel: false})
            .on('click', () => this.#changeViewMode('infoboxes_full'));
        $('#setViewMode').controlgroup();

        // ----- GRAVITY MODES -----
        $('#gravityMode0').button()
            .on('click', () => this.#setGravity('off'));
        $('#gravityMode1').button()
            .on('click', () => this.#setGravity('touch'));
        /*$('#gravityMode2').button()
            .on('click', () => this.#setGravity('aggressive'));*/
        $('#setupGravityMode').controlgroup();
    }

    #setupNodes(){

        // ----- NODES -----
        let radius = this.get('entityradius');
        if(radius < this.visualiser.circleSize) radius = this.visualiser.circleSize // min
        else if(radius > this.visualiser.maxEntityRadius) radius = this.visualiser.maxEntityRadius;

        $('#nodesRadius').val(radius).on('change', (event) => {
            this.put('entityradius', $(event.target).val());
            window.d3.selectAll('.node > .background').attr('r', (data) => this.visualiser.getEntityRadius(data.count));
        });

        $('#nodesMode0').button().css('width','35px')
            .on('click', () => this.#setFormulaMode('linear'));
        $('#nodesMode1').button().css('width','35px')
            .on('click', () => this.#setFormulaMode('logarithmic'));
        $('#nodesMode2').button().css('width','35px')
            .on('click', () => this.#setFormulaMode('linunweightedear'));
        $('#setNodesMode').controlgroup();

        if($('#entityColor').length > 0){

            let entityColour = this.get('entitycolor');
            $('#entityColor').val(entityColour)
                .colorpicker({
                    hideButton: false, //show button right to input
                    showOn: 'button',
                    val: entityColour
                })
                .on('change.color', (event, color) => {
                    if(color){
                        this.put('entitycolor', color);
                        this.visualiser.updateShape('circles', ['.node', null, this.get('entitycolor')]); // updateCircles
                        this.visualiser.updateShape('rectangles', ['.node', this.get('entitycolor')]); // updateRectangles
                        this.visualiser.visualise();
                    }
            });
        }
    }

    #setupLinks(){

        // ----- LINKS -----
        $('#linksMode0').button({icon: 'ui-icon-link-streight', showLabel: false})
            .on('click', () => this.#setLinkMode('straight'));
        $('#linksMode1').button({icon: 'ui-icon-link-curved', showLabel: false})
            .on('click', () => this.#setLinkMode('curved'));
        $('#linksMode2').button({icon: 'ui-icon-link-stepped', showLabel: false})
            .on('click', () => this.#setLinkMode('stepped'));

        $('#linksEmpty').on('change', (event) => {
            this.put('line_empty_link', $(event.target).is(':checked') ? 1 : 0);
            this.visualiser.visualise();
            this.#syncUI();
        });

        $('#expand-links').on('change', () => this.visualiser.tick()); // expand single links
        if(this.visualiser.isStructure){ // show all links by default for database structure vis
            $('#expand-links').prop('checked', true);
        }
        $('#setLinksMode').controlgroup();    

        this.#setLinkMode('straight');

        let linksLength = 200;
        $('#linksLength').val(linksLength)
            .on('change', (event) => {

                let newval = $(event.target).val();
                this.put('linelength', newval);

                if(this.get('gravity') != 'off'){
                    this.visualiser.visualise();
                }
            });

        let linksWidth = 2;
        if(linksWidth > this.visualiser.maxLinkWidth) linksWidth = this.visualiser.maxLinkWidth;
        $('#linksWidth').val(linksWidth)
            .on('change', (event) => {

                let newval = $(event.target).val();
                this.put('linewidth', newval);

                this.#refreshLinesWidth();
            });

        this.#setupLinkColours();
    }

    #setupLinkColours(){

        $('#linksPathColor').css({'font-size': '1.8em', 'font-weight': 'bold', color: this.get('linecolor')})
            .on('click', (event) => {
                window.hWin.HEURIST4.util.stopEvent(event);
                $('#linksPathColor_inpt').colorpicker('showPalette');
            });

        $('#linksPathColor_inpt').val('#0070c0')
            .colorpicker({
                hideButton: true, // show button right to input
                showOn: 'both',
                val: this.get('linecolor')
            })
            .on('change.color', (event, color) => {
                if(color){
                    this.put('linecolor', color);
                    $('.bottom-lines.link').attr('stroke', color);
                    $('#linksPathColor').css('color', color);
                    this.visualiser.visualise();
                }
            });

        $('#linksMarkerColor').addClass('ui-icon ui-icon-triangle-1-e')
            .css('color', this.get('markercolor'))
            .on('click', (event) => {
                window.hWin.HEURIST4.util.stopEvent(event);
                $('#linksMarkerColor_inpt').colorpicker('showPalette');
            });

        $('#linksMarkerColor_inpt').val(this.get('markercolor'))
            .colorpicker({
                hideButton: true, // show button right to input
                showOn: 'focus',
                val: this.get('markercolor')
            })
            .on('change.color', (event, color) => {
                if(color){
                    this.put('markercolor', color);
                    $('marker').attr('fill', color);
                    $('#linksMarkerColor').css('color', color);
                    this.visualiser.visualise();
                }
            });
    }

    #setupLabels(){

        // ----- LABELS -----
        this.put('labels', 'on'); // always on
        let isLabelVisible = this.get('labels', 'on') == 'on';

        $('#textOnOff').attr('checked',isLabelVisible)
            .on('change', (event) => {

                let newval = $(event.target).is(':checked') ? 'on' : 'off';
                this.put('labels', newval);

                if(this.visualiser.currentMode == 'icons'){

                    let isLabelVisible = newval == 'on';
                    if(isLabelVisible) {
                        this.visualiser.visualise();
                    }else{
                        window.d3.selectAll('.nodelabel').style('display', 'none');
                    }
                }
            });

        let textLength = this.get('textlength', 200);    
        $('#textLength').val(textLength)
            .on('change', (event) => {

                let newval = $(event.target).val();
                this.put('textlength', newval);

                let isLabelVisible = this.visualiser.currentMode != 'icons' || this.get('labels', 'on') == 'on';
                if(isLabelVisible) this.visualiser.visualise();
            });

        let fontSize = this.get('fontsize', 12);    
        if(isNaN(fontSize) || fontSize < 8) fontSize = 8 // min
        else if(fontSize > 25) fontSize = 25;

        $('#fontSize').val(fontSize)
            .on('change', (event) => {

                let newval = $(event.target).val();
                this.put('fontsize', newval);

                let isLabelVisible = this.visualiser.currentMode != 'icons' || this.get('labels', 'on')=='on';
                if(isLabelVisible) this.visualiser.visualise();
            });
    }

    settingsToUI(){

        let $toolbar = $('#toolbar');
        let $advanced = $('.advanced');
        let $advancedMode = $('#setAdvancedMode');
        let isAdvanced = this.get('advanced');

        $advancedMode.css('cursor', 'hand')
            .on('click', () => {

                let isAdvanced = this.get('advanced');
                isAdvanced = isAdvanced === 'false';

                if(isAdvanced){
                    $advanced.show();
                    $advancedMode.find('a').hide();
                    if(this.visualiser.isStructure){
                        $('#setDivExport').hide();
                    }
                }else{
                    $advanced.hide();
                    $advancedMode.find('a').show();
                }

                this.put('advanced', isAdvanced);
                if(typeof window.onVisualizeResize === 'function'){
                    window.onVisualizeResize();
                }
            });

        if(isAdvanced !== 'false'){
            $advanced.show();
            $advancedMode.find('a').hide();
            if(this.visualiser.isStructure){
                $('#setDivExport').hide();
            }
        }else{
            $advanced.hide();
            $advancedMode.find('a').show();
        }

        this.#setupModes();
        this.#setupNodes();
        this.#setupLinks();
        this.#setupLabels();

        if(this.visualiser.isStructure){
            this.#initRecTypeSelector();
            $('#setDivExport').hide();
        }else{
            $('#setDivExport').show();
            $('#gephi-export').button().on('click', () => this.visualiser.exporter.gephi());
        }

        $toolbar.show();
    }

    #syncUI(){

        let $toolbar = $('#toolbar');

        $toolbar.find('button').removeClass('ui-heurist-btn-header1');
    
        $toolbar.find(`button[value="${this.visualiser.selection.mode}"]`).addClass('ui-heurist-btn-header1');
        $toolbar.find(`button[value="${this.visualiser.currentMode}"]`).addClass('ui-heurist-btn-header1');
    
        let grv = this.get('gravity', 'off');
        if(grv === 'agressive') grv = 'touch';
        $toolbar.find(`button[name="gravityMode"][value="${grv}"]`).addClass('ui-heurist-btn-header1');
        
        let formula = this.get('formula', 'linear');
        $toolbar.find(`button[name="nodesMode"][value="${formula}"]`).addClass('ui-heurist-btn-header1');
        
        let linetype = 'straight'; // this.get('linetype', 'straight');
        $toolbar.find(`button[name="linksMode"][value="${linetype}"]`).addClass('ui-heurist-btn-header1');

        let showEmpty = this.get('line_empty_link', 1) == 1;
        $toolbar.find('#linksEmpty').prop('checked', showEmpty);
    }

    #changeViewMode(mode){

        $('.offset_line').remove();

        if(this.visualiser.currentMode == mode){
            return;
        }

        switch(mode){

            case 'infoboxes':
            case 'infoboxes_full':
            case 'icons':

                this.visualiser.currentMode = mode;
                break;

            default:
                window.hWin.HEURIST4.msg.showMsgFlash(`Unknown display mode "${mode}" selected`, 3000);
                mode = null;
                break;
        }

        if(!mode){
            this.#updateNodeAppearance(mode);
        }

        let showLabels = this.visualiser.currentMode != 'icons' || this.get('labels');
        window.d3.selectAll('.nodelabel').style('display', showLabels ? 'block' : 'none');

        window.d3.selectAll('image.menu-open').each((image) => {
            let event = new MouseEvent('mouseup');
            image.dispatchEvent(event);
        });

        this.#syncUI();

        this.visualiser.tick();

        this.visualiser.updateLabels(); // update labels
    }

    #updateNodeAppearance(mode){

        let info_mode = mode !== 'icons' ? 'initial' : 'none';
        let simple_mode = mode === 'infoboxes' ? 'initial' : 'none';
        let full_mode = mode === 'infoboxes_full' ? 'initial' : 'none';
        let minimal_mode = mode === 'icons' ? 'initial' : 'none';

        window.d3.selectAll('.info-mode').style('display', info_mode);
        window.d3.selectAll('.info-mode-full').style('display', full_mode);
        window.d3.selectAll('line.inner_divider').style('display', full_mode);

        window.d3.selectAll('.rect-info').style('display', simple_mode);
        window.d3.selectAll('.rect-info-full').style('display', full_mode);

        window.d3.selectAll('circle.icon-background, circle.icon-foreground, image.node-icon').style('display', minimal_mode);

        let label_pos = mode === 'icons' ? 29 : 10;
        window.d3.selectAll('text.nodelabel.namelabel').attr('x', label_pos);
    }

    #setGravity(gravity){

        this.put('gravity', gravity);
    
        // Update gravity impact on nodes
        this.visualiser.svg.selectAll('.node').attr('fixed', () => {
            data.fixed = gravity === 'aggressive';
            return data.fixed;
        });

        if(gravity !== 'off') {
            this.visualiser.force.resume(); 
        }     

        this.#syncUI();
    }

    #setFormulaMode(formula){

        this.put('formula', formula);

        window.d3.selectAll('.node > .background').attr('r', (data) => this.visualiser.getEntityRadius(data.count));

        this.#refreshLinesWidth();

        this.#syncUI();
    }

    #refreshLinesWidth(){

        window.d3.selectAll('.bottom-lines').style('stroke-width', (data) => this.visualiser.getLineWidth(data.targetcount));

        window.d3.selectAll('marker')
            .attr('markerWidth', (data) => this.visualiser.getMarkerWidth(data ? data.targetcount : 0))
            .attr('markerHeight', (data) => this.visualiser.getMarkerWidth(data ? data.targetcount : 0));
    }

    #setLinkMode(formula){
        this.put('linetype', formula);
        this.visualiser.visualise();
        this.#syncUI();
    }

    #initRecTypeSelector(){

        let hidePane = window.startup_rectype != 1;
        delete window.startup_rectype;

        let layout_options = { 
            applyDefaultStyles: true,
            center:{
                size: $('#main_content').width(),
                contentSelector: '#main_content'
            },
            west:{
                size:400,
                maxWidth:400,
                spacing_open:15,
                spacing_closed:15,  
                togglerAlign_open:40, // button top value
                togglerAlign_closed:40,
                initClosed:true,
                slidable:false,  // disable sliding
                resizable:false, // disable resizing
                contentSelector: '#list_rectypes',
                onopen_end: () => $('#list_rectypes, #lblShowRectypeSelector').show(),
                onclose_start: () => $('#list_rectypes, #lblShowRectypeSelector').hide(),
                togglerContent_open: '<div class="ui-icon ui-icon-carat-2-w" style="margin-left: 0px;font-size:20px;"></div>',
                togglerContent_closed: '<div class="ui-icon ui-icon-carat-2-e" style="font-size:20px;"></div>'
            }
        };

        let layout = $('body.popup div.layout-container').first().layout(layout_options);
        
        if(!hidePane){ // initClosed option is inconsistent

            setTimeout(() => {

                layout.open('west');
                $('#list_rectypes').show();
                $('#lblShowRectypeSelector').show();

                let refresh_chkbx = window.trigger_checkbox_refresh;
                if(!window.hWin.HEURIST4.util.isempty(refresh_chkbx)){
                    $(`#list_rectypes ${refresh_chkbx}`).trigger('change');
                    delete window.trigger_checkbox_refresh;
                }
            }, 1000);
        }
    }
}