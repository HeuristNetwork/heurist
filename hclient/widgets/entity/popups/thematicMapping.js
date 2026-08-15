/**
* @file thematicMapping.js
* @brief Provides a popup UI for configuring thematic mapping settings.
* @fileOverview This widget allows users to define rules and settings for thematic mapping, likely used for map visualizations based on record data.
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

/*

It creates thematic map in the following format

{
"title":"name of thematic map",
"symbol":{}, //base symbology. if it is not defined use default layer symbology
"fields":[
{"code":"10:123",  //rules: field id or rt:dt to search field in linked records
    "title":"Person weight","type":"integer",  //besides standard field type we can use aggregate types: sum, count, avg
    "range_type": "exact|equal|log"  // exact (default) - one value or list of values or exact range (suitable for all), 
                                     // equal - values be split by ranges according to current min/max suitable for numeric and dates
                                     // log - logariphmic
    "ranges":[  //sample of exact ranges
       {"value":"40", "title":"","symbol":{ } }, //symbology that overwrites some properties of base symbology
       {"value":"40<>50", "symbol":{ } },
       {"value":"69,71,75", "symbol":{ } }
    
    //sample equal of log ranges - need to find min/max values beforehand
    "range_count":5,   
    "ranges":[  
       {"value":0,symbol":{ } }, //symbology that overwrites some properties of base symbology
       ....
       {"value":5,symbol":{ } }
    ]
},
{"code":"10:15","title":"Gender","type":"enum",
....
}
],
"rectypes":["10"]
}

{
"fields":[}
12:1109

*/

/**
 * @widget heurist.thematicMapping
 * @brief Popup widget for configuring thematic mapping.
 * @augments $.heurist.recordAction
 * @description This widget provides a dialog for users to define thematic maps.
 * Users can create multiple thematic map configurations, each with a title, base symbology,
 * and a set of fields. For each field, ranges or categories can be defined with specific
 * symbology that overrides the base or layer default.
 *
 * @property {number} [height=780] Default height of the dialog.
 * @property {number} [width=1100] Default width of the dialog.
 * @property {boolean} [modal=true] Whether the dialog is modal.
 * @property {string} [title='Define thematic mapping'] Default title of the dialog.
 * @property {string} [default_palette_class='ui-heurist-design'] Default CSS class for theming.
 * @property {?string} maplayer_query The HAPI query string for the map layer, used to determine relevant record types and potentially data ranges.
 * @property {?object|object[]} thematic_mapping The initial thematic mapping configuration(s) to load.
 * @property {string} [path='widgets/entity/popups/'] Path to the widget's HTML template.
 * @property {string} [htmlContent='thematicMapping.html'] Name of the HTML template file.
 */
$.widget( "heurist.thematicMapping", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 780,
        width:  920,
        modal:  true,
        closeOnEscape: false,
        title:  'Define thematic mapping',
        default_palette_class: 'ui-heurist-design', 
        
        maplayer_query: null,
        thematic_mapping: null, //json with all symbols/ranges
        
        path: 'widgets/entity/popups/', //location of this widget
        
        htmlContent: 'thematicMapping.html'
    },
    
    baseLayerSymbol: null, //from layer 
    currentField: 0,
    selectedFields:{},
    enumValues: null, 
    fieldSelected: null,
    popele: null, //element for popup dialog
    maplayer_ids: null, //list of ids from map layer - result of maplayer_query
    previewRecTypeID: 12, //record type used for iconType=rectype preview
    _initialThematicState: null,
    
    /**
     * @brief Destroys the widget and its components.
     * @override
     * @memberof heurist.thematicMapping
     * Calls the parent `_destroy` method. Also specifically destroys the Fancytree instance
     * used for displaying the record type structure if it exists.
     */
    _destroy: function() {
        this._super(); 
        
        let treediv = this.element.find('.rtt-tree');
        if(!treediv.is(':empty') && treediv.fancytree("instance")){
            treediv.fancytree("destroy");
        }
        treediv.remove();
        
    },
    
    _initDialog: function(){
        this.options.beforeClose = ()=>this._onBeforeClose();
        this._super();
    },
        
    /**
     * @brief Gets the action buttons for the dialog.
     * @override
     * @memberof heurist.thematicMapping
     * @returns {object[]} An array of button definition objects for the dialog.
     * Modifies the default "OK" button text to "Save thematic map" and "Cancel" button text.
     */
    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR('Save thematic map');
        res[0].text = window.hWin.HR('Cancel');
        return res;
    },    
        
        
        // The base dialog used to close immediately on Escape, which discarded
        // changes without any warning. Handle Escape here only while this is the
        // topmost dialog; nested editors (symbol/gradient dialogs) must receive it
        // themselves.
    //    this._on($(window.hWin.document), {keydown:function(event){
    //        if(!(event.key === 'Escape' || event.keyCode === 27)) return; });
    _onBeforeClose: function(){

        // No warning is needed when the dialog contents are unchanged.
        if(this._initialThematicState!==null
            && this._initialThematicState===this._serializeThematicState()){
            return true;
        }

        const that = this;
        window.hWin.HEURIST4.msg.showMsgDlg(
            '<br>Discard changes and close the thematic map editor?',
            function(){that.closeDialog(true);});

        return false;
    },

    /**
     * Serialize the editable thematic-map state without saving or validating it.
     * Current theme/field controls are overlaid so unsaved edits are detected.
     *
     * @returns {string}
     */
    _serializeThematicState: function(){

        const themes = window.hWin.HEURIST4.util.cloneJSON(this.options.thematic_mapping || []);

        if(this.currentThemeIdx>=0 && themes[this.currentThemeIdx]){
            const theme = themes[this.currentThemeIdx];

            theme.title = this.element.find('#tm_name').val();
            theme.active = this.element.find('#tm_active').is(':checked');

            const symbol = window.hWin.HEURIST4.util.isJSON(this.element.find('#tm_symbol').val());
            theme.symbol = symbol || '';

            const fields = window.hWin.HEURIST4.util.cloneJSON(this.selectedFields || {});

            if(this.currentField>0 && fields[this.currentField]){
                const field = fields[this.currentField];
                field.title = this.element.find('#f_title').val();

                const ranges = [];
                this.element.find('#f_ranges .field-range').each(function(){
                    const row = $(this);
                    let value;

                    if(row.find('select.val1').length>0){
                        value = row.find('select.val1').val();
                    }else{
                        const val1 = row.find('input.val1').val();
                        const val2 = row.find('input.val2').val();

                        if(val1 && val2){
                            value = val1+'<>'+val2;
                        }else{
                            value = val1 || val2 || '';
                        }
                    }

                    if(value){
                        const rangeSymbol =
                            window.hWin.HEURIST4.util.isJSON(row.find('.field-symbol').val());
                        ranges.push({value:value, symbol:rangeSymbol || ''});
                    }
                });

                field.ranges = ranges;
            }

            theme.fields = Object.keys(fields).map(function(key){
                const field = fields[key];
                if(!field) return null;

                if(Array.isArray(field.ranges)){
                    field.ranges.forEach(function(range){
                        if(range) delete range.uid;
                    });
                }
                return field;
            }).filter(function(field){
                return field && Array.isArray(field.ranges) && field.ranges.length>0;
            });
        }

        return JSON.stringify(themes);
    },
    
                
    /**
     * @brief Initializes the main controls of the widget after HTML content is loaded.
     * @override
     * @memberof heurist.thematicMapping
     * This method, specific to widgets augmenting `$.heurist.recordAction` or `$.heurist.baseAction`,
     * is the primary entry point for UI setup. It initializes:
     * - Selection list for thematic map configurations.
     * - Buttons for adding/removing thematic maps and fields.
     * - Symbol editor for base and range-specific symbology.
     * - Record type selector and field selection tree (Fancytree).
     * - Loads existing thematic mapping configurations or initializes a new one.
     * - Populates record type selector based on `options.maplayer_query` if available.
     */
    _fillSelectRecordScope: function (){

        this.selectRecordScope.empty();

        
        let fields_sel = this.element.find('#selected_fields');
        
        this._on(fields_sel,{change: this._onThemeFieldSelect});

        const btnSelectAnotherField = this.element.find('#btn_select_another_field').button({
            icon:'ui-icon-circle-b-plus'
        });
        this._on(btnSelectAnotherField, {click:function(){
            this._updateFieldSelectionVisibility(true);
        }});



        this._on(this.element.find('button[id^="btn_f"]').button(), 
                                    {click:this._onThemeFieldAction});
        this.element.find('button[id^="btn_f_range_add"]').button({icon:'ui-icon-circle-b-plus'});
        
        this._initSymbolEditor(this.element.find('#tm_symbol'));
        
        this._on(this.element.find('#tm_symbol'), {change:function(){
            this._renderSymbolPreview( null, //update all
                        this.mapDefaultSymbol, this.element.find('#tm_symbol').val(), null);
        }});

        // Keep list labels in sync while the user edits titles. Full validation and
        // range persistence still happen in _saveThematicMap/_saveThemeField.
        this._on(this.element.find('#tm_name'), {input:function(event){
            if(this.currentThemeIdx<0) return;

            const title = $(event.target).val();
            this.options.thematic_mapping[this.currentThemeIdx].title = title;
            this.element.find('#thematic_maps_list option')
                .eq(this.currentThemeIdx).text(title);
        }});

        this._on(this.element.find('#f_title'), {input:function(event){
            if(!(this.currentField>0) || !this.selectedFields[this.currentField]) return;

            const title = $(event.target).val();
            this.selectedFields[this.currentField].title = title;
            this.element.find('#selected_fields option[value="'+this.currentField+'"]')
                .text(title);
        }});
        
        
        this.options.thematic_mapping = window.hWin.HEURIST4.util.isJSON( this.options.thematic_mapping );
        
        //load list of thematic maps
        let themes_list = this.element.find('#thematic_maps_list');
        this._on(themes_list,{change: this._onThematicMapSelect});
        themes_list.empty();
        
        if(this.options.thematic_mapping)
        {
            if(!Array.isArray(this.options.thematic_mapping)) this.options.thematic_mapping = [this.options.thematic_mapping];
        }else{
            this.options.thematic_mapping = [];//
        }
            
        let i=0;
        while(i<this.options.thematic_mapping.length){
            let t_map = this.options.thematic_mapping[i];
            if(t_map.fields){ //with fields - thematic map
                window.hWin.HEURIST4.ui.addoption(themes_list[0], i, t_map.title);
                i++;
            }else{ //without fields - this is base symbol
                this.baseLayerSymbol = t_map;
                this.options.thematic_mapping.splice(i,1);
            }
        }
        
        //default layer symbol
        let def_style = window.hWin.HEURIST4.util.isJSON(this.baseLayerSymbol);
        if(!def_style){
            def_style = window.hWin.HAPI4.get_prefs('map_default_style');
            if(def_style) def_style = window.hWin.HEURIST4.util.isJSON(def_style);
        }
        this.mapDefaultSymbol = window.hWin.HEURIST4.ui.prepareMapSymbol(def_style, null);

        if(themes_list.find('option').length==0){
            this._addThematicMap();
        }else{
            themes_list[0].selectedIndex = 0;
            themes_list.trigger('change');
        }
        

        // Baseline for close-warning detection, after the initial theme/field
        // has been materialised into the editing controls.
        this._initialThematicState = this._serializeThematicState();
        
        this._on(this.element.find('#btn_map_remove').button(), 
                                    {click:this._onThematicMapDelete});

        this._on(this.element.find('#btn_tm_add'),
                                    {click:this._addThematicMap});
                        
        this.popele = this.element.find('#divAutoRanges');
        this._on(this.popele.find('input,select'),{change:this._definePreviewRanges});
        
        
        //
        //
        //
        let selScope = this.selectRecordScope.get(0);
        this.selectRecordScope = window.hWin.HEURIST4.ui.createRectypeSelectNew( selScope,
        {
            topOptions: [{key:'-1',title:'select record type...'}],
            useHtmlSelect: false,
            useCounts: false,
            showAllRectypes: true
        });
        this._on( this.selectRecordScope, {
                change: this._onRecordScopeChange} );        
        
        selScope = this.selectRecordScope.get(0);
        window.hWin.HEURIST4.ui.initHSelect(selScope);
        
        
        //
        // search record types in map query
        //
        if(this.options.maplayer_query){
            
            let request = { q: this.options.maplayer_query,
                    w: 'a',
                    detail: 'count_by_rty'};

            let that = this;
            window.HAPI4.RecordMgr.search(request, function(response){ 

                if(response.status == window.hWin.ResponseStatus.OK){

                    if(response.data && $.isPlainObject(response.data.recordtypes)){
                        let rty_IDs = Object.keys(response.data.recordtypes);
                        
                        if(rty_IDs.length>0){

                            
                            // Use the first record type represented in the layer for
                            // iconType=rectype previews. Fall back to Place (RT 12).
                            that.previewRecTypeID = parseInt(rty_IDs[0]) || 12;
                            that._renderSymbolPreview(null, that.mapDefaultSymbol,
                                that.element.find('#tm_symbol').val(), null);

for(let i=0; i<rty_IDs.length; i++){
                                let name = window.hWin.HEURIST4.util.htmlEscape($Db.rty(rty_IDs[i], 'rty_Name'));
                                
                                let option = document.createElement("option");
                                option.text = name;
                                option.value = rty_IDs[i];
                                $(option).attr('depth', 1);
                                selScope.insertBefore(option, selScope.options[1]);
                            }
                            
                            let option = document.createElement("option");
                            option.text = 'Record types in layer';
                            option.disabled = 'disabled'
                            $(option).attr('group', 1);
                            selScope.insertBefore(option, selScope.options[1]);
                            
                            that.selectRecordScope.val(rty_IDs[0])
                            that.selectRecordScope.hSelect('refresh');
                            that.selectRecordScope.trigger('change');
                            
                            
                            if(response.data.count<1000){
                                //search ids
                                let request2 = { q: that.options.maplayer_query,
                                        w: 'a',
                                        detail: 'ids'};
                                window.HAPI4.RecordMgr.search(request2, function(response){ 
                                    if(response && response.data && Array.isArray(response.data.records)){
                                        that.maplayer_ids = response.data.records.join(',');                                    
                                    }
                                    
                                });
                            }                            
                            
                        }
                    }
                    
                }else{
                    console.error(response.message);
                }
            });            
        
        }else{
            this._onRecordScopeChange();
        }
        
    },
            
    /**
     * @brief Main action performed when the primary dialog button ("Save thematic map") is clicked.
     * @override
     * @memberof heurist.thematicMapping
     * Calls `_saveThematicMap()` to save the current configuration. If successful,
     * it prepends the base layer symbol (if any) to the `thematic_mapping` array,
     * sets this array as the result for `_context_on_close`, and closes the dialog.
     */
    doAction: function(){
        
        //get values
        if(this._saveThematicMap()){
            if(this.baseLayerSymbol){
                this.options.thematic_mapping.unshift(this.baseLayerSymbol);
            }
            this._context_on_close =  this.options.thematic_mapping;
            this.closeDialog(true);
        }
        
        
    },
    
    /**
     * @brief Hides the progress bar and shows the main search/config div.
     * @override
     * @memberof heurist.thematicMapping
     */
    _hideProgress: function (){
        this._super(); 
        this.element.find('#div_search').show();  
    },
    
    /**
     * @brief Handles changes in the selected record scope (record type).
     * @override
     * @memberof heurist.thematicMapping
     * @returns {boolean} Result of the superclass's `_onRecordScopeChange` method.
     * If the selected record type ID has changed, it reloads the field selection tree
     * for the new record type using `_loadRecordTypesTreeView`.
     */
    _onRecordScopeChange: function() 
    {
        let isdisabled = this._super();
        
        
        
        let rtyID = this.selectRecordScope.val();
        
        if(this._selectedRtyID!=rtyID ){
            if(rtyID>0){
                //reload treeview
                this._loadRecordTypesTreeView( rtyID );
            }
        }
        
        return isdisabled;
    },
    
    /**
     * @brief Loads and initializes the Fancytree for selecting fields from a record type.
     * @memberof heurist.thematicMapping
     * @param {number} rtyID The ID of the record type whose fields are to be displayed.
     * Creates a hierarchical tree of fields available for the given record type,
     * allowing users to select fields for thematic mapping. Filters for field types
     * suitable for thematic mapping (enum, date, numeric, resource).
     * Handles lazy loading for related record structures.
     */
    _loadRecordTypesTreeView: function(rtyID){
        
        let that = this;

        if(this._selectedRtyID!=rtyID ){
            
            this._selectedRtyID = rtyID;

            let allowed_fieldtypes = [//'rec_Title','rec_ID',
                'enum','year','date','integer','float','resource']; //'freetext',
            
            //generate treedata from rectype structure
            let treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, rtyID, allowed_fieldtypes );
            
        //load treeview
        let treediv = this.element.find('.rtt-tree');
        if(!treediv.is(':empty') && treediv.fancytree("instance")){
            treediv.fancytree("destroy");
        }
        
        treedata[0].expanded = true;

        treediv.fancytree({
            //extensions: ["filter"],
            //            extensions: ["select"],
            //checkbox: true,
            //selectMode: 3,  // single
            checkbox: false,
            selectMode: 1,  // single
            source: treedata,
            beforeSelect: function(event, data){
                // A node is about to be selected: prevent this, for folder-nodes:
                if( data.node.hasChildren() ){

                    if(data.node.isExpanded()){
                        for(let i = 0; i < data.node.children.length; i++){
                            let node = data.node.children[i];

                            if(node.key == 'term'){ // if node is a term
                                node.setSelected(true); // auto select 'term' option to add term name
                            }
                        }
                    }
                    return false;
                }
            },
            renderNode: function(event, data){
                /*
                if(false && data.node.type == "enum") { 
                    // hide blue and expand arrows for terms
                    $(data.node.span.childNodes[0]).hide();
                    $(data.node.span.childNodes[1]).hide();
                }*/
                if(data.node.parent && (data.node.parent.type == 'resource' || data.node.parent.type == 'rectype')){ // add left border+margin
                    $(data.node.li).attr('style', 'border-left: black solid 1px !important;margin-left: 9px;');
                }
                if(!(data.node.type == 'resource' || data.node.type == 'rectype'))
                {
                    //define action button
                   
                    let item = $(data.node);
                    let item_li = $(data.node.li);
                    if($(item).find('.svs-contextmenu3').length==0){
                     
                        let parent_span = item_li.children('span.fancytree-node');

                        //add icon
                        let actionspan = $('<div class="svs-contextmenu3" style="padding: 0px 20px 0px 0px;" data-parentid="'
                        +item.data.parent_id+'" data-code="'+data.node.key+'">'
                        +'<span class="ui-icon ui-icon-circle-b-plus" title="Add field" style="font-size:0.9em"></span>'
                        +'</div>').appendTo(parent_span);
                        
                        actionspan.find('.ui-icon-circle-b-plus').on('click', function(event){
                            let ele = $(event.target);
                            window.hWin.HEURIST4.util.stopEvent(event);
                            that._addThemeField( ele.parents('[data-code]').attr('data-code') );                           
                        });
                        
                        //hide icons on mouse exit
                        function _onmouseexit(event){
                            let node;
                            if($(event.target).is('li')){
                                node = $(event.target).find('.fancytree-node');
                            }else if($(event.target).hasClass('fancytree-node')){
                                node =  $(event.target);
                            }else{
                                //hide icon for parent 
                                node = $(event.target).parents('.fancytree-node');
                                if(node) node = $(node[0]);
                            }
                            let ele = node.find('.svs-contextmenu3');
                            ele.hide();
                        }               

                        function _onmouseenter(event){
                                let node;
                                if($(event.target).hasClass('fancytree-node')){
                                    node =  $(event.target);
                                }else{
                                    node = $(event.target).parents('.fancytree-node');
                                }
                                if(! ($(node).hasClass('fancytree-loading') )){
                                    let ele = $(node).find('.svs-contextmenu3');
                                    ele.css({'display':'inline-block'});//.css('visibility','visible');
                                }
                            }

                        $(parent_span).on('mouseenter',
                            _onmouseenter
                        ).on('mouseleave',
                            _onmouseexit
                        );               
                        
                    }
                    
                    /*
                    if(data.node.parent && data.node.parent.type == 'enum'){ // make term options inline and smaller
                        $(data.node.li).css('display', 'inline-block');
                        $(data.node.span.childNodes[0]).css('display', 'none');

                        if(data.node.key == 'term'){
                            $(data.node.parent.ul).css({'transform': 'scale(0.8)', 'padding': '0px', 'position': 'relative', 'left': '-12px'});
                        }
                    }
                    */
                }
                if(data.node.type == 'separator'){
                    $(data.node.span).attr('style', 'background: none !important;color: black !important;'); //stop highlighting
                    $(data.node.span.childNodes[1]).hide(); //checkbox for separators
                }
            },
            lazyLoad: function(event, data){
                let node = data.node;
                let parentcode = node.data.code; 
                let rectypes = node.data.rt_ids;

                let res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, 
                    rectypes, allowed_fieldtypes, parentcode );
                if(res.length>1){
                    data.result = res;
                }else{
                    data.result = res[0].children;
                }

                return data;                                                   
            },
            loadChildren: function(e, data){
                setTimeout(function(){
                   
                    },500);
            },
            select: function(e, data) {
               
            },
            click: function(e, data){

                if(data.node.type == 'separator'){
                    return false;
                }

                let isExpander = $(e.originalEvent.target).hasClass('fancytree-expander');
                let setDefaults = !data.node.isExpanded();

                if($(e.originalEvent.target).is('span') && data.node.children && data.node.children.length>0){
                    
                    if(!isExpander){
                        data.node.setExpanded(!data.node.isExpanded());
                    }
                
                    if(setDefaults){
                        for(let i = 0; i < data.node.children.length; i++){
                            let node = data.node.children[i];

                            if(node.key == 'term'){ // if node is a term
                                node.setSelected(true); // auto select 'term' option to add term name
                            }
                        }
                    }
                }else if( data.node.lazy && !isExpander) {
                    data.node.setExpanded( true );
                }
            },
            dblclick: function(e, data) {
                if(data.node.type == 'separator'){
                    return false;
                }
                data.node.toggleSelected();
            },
            keydown: function(e, data) {
                if( e.which === 32 ) {
                    data.node.toggleSelected();
                    return false;
                }
            }
        });
            
        }   
    },

    /**
     * @brief Adds a new thematic map configuration to the list.
     * @memberof heurist.thematicMapping
     * Saves the currently edited thematic map (if any) via `_saveThematicMap()`.
     * Appends a new, empty thematic map object to `this.options.thematic_mapping`.
     * Adds an option for the new map to the selection list and selects it.
     */
    _addThematicMap: function(){
        
        if(this._saveThematicMap()){
        
            let last_idx = this.options.thematic_mapping.length;
            const newname = 'New Thematic map';
            this.options.thematic_mapping.push({title:newname, active:false, fields:[]});

            
            let themes_list = this.element.find('#thematic_maps_list');
            window.hWin.HEURIST4.ui.addoption(themes_list[0], last_idx, newname);
           
           
            
            themes_list[0].selectedIndex = last_idx;
            themes_list.trigger('change');
        }
    },
    
    
    /**
     * @brief Handles deletion of the currently selected thematic map configuration.
     * @memberof heurist.thematicMapping
     * @param {boolean} [unconditional=false] If true, deletes without confirmation.
     * If `unconditional` is false, prompts the user for confirmation.
     * If confirmed, removes the map from `this.options.thematic_mapping` and the UI list.
     * If no maps remain, prompts to exit; otherwise, selects the first map.
     */
    _onThematicMapDelete: function(unconditional){
        if(this.currentThemeIdx>=0){
            
            let that = this;

            if(unconditional!==true){    
                window.hWin.HEURIST4.msg.showMsgDlg('<br>Are you sure?',
                    function(){that._onThematicMapDelete(true);});
                return;
            }
            
            let themes_list = this.element.find('#thematic_maps_list');
            this.options.thematic_mapping.splice(this.currentThemeIdx, 1);
            //remove from select
            themes_list.find('option').eq(this.currentThemeIdx).remove();
            
            if(this.options.thematic_mapping.length==0){
                //offer exit
                
                window.hWin.HEURIST4.msg.showMsgDlg('<br>You removed all thematic maps. Exit this form?',
                    function(){
                        // Return an explicit empty thematic-map result instead of an
                        // empty string. The caller treats a falsey context as Cancel, so
                        // returning '' left the removed thematic map in the underlying
                        // symbology field. Preserve a base symbol, if this widget was
                        // invoked with one, using the same array shape as normal Save.
                        that._context_on_close = that.baseLayerSymbol ? [that.baseLayerSymbol] : [];
                        that.closeDialog(true);
                    });
                
            }else{
                this.currentField = 0;
                this.currentThemeIdx = -1;
                themes_list[0].selectedIndex = 0;
                themes_list.trigger('change');
            }
        }
    },
    
    /**
     * @brief Saves the current thematic map configuration being edited.
     * @memberof heurist.thematicMapping
     * @returns {boolean} True if save was successful or no map was being edited, false if validation failed.
     * If `this.currentThemeIdx` is valid:
     *  - Updates the title, active state, and base symbol from UI inputs.
     *  - Calls `_saveThemeField()` to save the currently edited field's ranges.
     *  - Reconstructs the `fields` array for the current thematic map from `this.selectedFields`,
     *    only including fields that have defined ranges.
     *  - Validates that a title and at least one field with ranges are present.
     *  - Updates the thematic map's title in the selection list.
     */
    _saveThematicMap: function(){
        
        if(this.currentThemeIdx>=0){
        
            let t_map = this.options.thematic_mapping[this.currentThemeIdx];
        
            t_map.title = this.element.find('#tm_name').val();
            t_map.active = this.element.find('#tm_active').is(':checked');
            t_map.symbol = window.hWin.HEURIST4.util.isJSON(this.element.find('#tm_symbol').val());
            if(!t_map.symbol) t_map.symbol =  '';

            t_map.fields = [];
            
            this._saveThemeField();
            
            this.currentField = 0;
            
            let len = Object.keys(this.selectedFields).length;
            for (let k=0;k<len;k++){
                const key = Object.keys(this.selectedFields)[k];
                if(this.selectedFields[key].ranges.length>0){
                    t_map.fields.push( this.selectedFields[key] );    
                }
            }
            
            if(window.hWin.HEURIST4.util.isempty(t_map.title)){
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: 'Title is mandatory',
                    error_title: 'Missing title'
                });
                return false;
            }
            if(t_map.fields.length==0){
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: 'Need to define at least one field with ranges/categories',
                    error_title: 'Missing field'
                });
                return false;
            }

            //rename in the list
            this.element.find('#thematic_maps_list').find('option').eq(this.currentThemeIdx).html(t_map.title);
            
            this.options.thematic_mapping[this.currentThemeIdx] = t_map;
            
        }
        
        return true;
    },

    /**
     * @brief Handles selection of a thematic map from the list.
     * @memberof heurist.thematicMapping
     * @param {Event} [event] The change event object from the selection list.
     * Saves the currently edited thematic map (if any) via `_saveThematicMap()`.
     * Loads the selected thematic map's configuration into the UI fields (title, active state, base symbol).
     * Populates `this.selectedFields` and the "Selected Fields" list based on the loaded map.
     * Triggers selection of the first field in the list.
     */
    _onThematicMapSelect: function( event ){
        
        if(this._saveThematicMap()){
        
            this.currentThemeIdx = event?event.target.selectedIndex:0;
            
            let t_map = this.options.thematic_mapping[this.currentThemeIdx];
        
            this.element.find('#tm_name').val(t_map.title);
            this.element.find('#tm_active').prop('checked', t_map.active);
            
            let base_symbol = window.hWin.HEURIST4.util.isJSON( t_map.symbol );
            base_symbol = (!base_symbol)?'':JSON.stringify(base_symbol);
            this.element.find('#tm_symbol').val(base_symbol).trigger('change');
            
            this.selectedFields = {};
            
            let flds = t_map.fields;
            
            let fields_sel = this.element.find('#selected_fields');
            fields_sel.empty();
            
            if(flds){
                for(let i=0; i<flds.length; i++){
                    let fld = flds[i];
                    let key = fld.code.split(':');
                    key = key[key.length-1];//dty_ID
                    
                    this.selectedFields[key] = fld;
                    
                    window.hWin.HEURIST4.ui.addoption(fields_sel[0], key, fld.title);
                }
                
                fields_sel[0].selectedIndex = 0;
                fields_sel.trigger('change');
            }

            this._updateFieldSelectionVisibility(fields_sel.find('option').length===0);
        }else{
             document.getElementById('thematic_maps_list').selectedIndex = this.currentThemeIdx;
        }
        
    },
    
    //-----------------------
    /**
     * Show or hide the field-selection controls. Once a theme already has fields,
     * the selection tree is kept out of the way until the user explicitly asks to
     * select another field.
     *
     * @param {boolean} showSelector True to show record type/field selection controls.
     */
    _updateFieldSelectionVisibility: function(showSelector){
        const fields_sel = this.element.find('#selected_fields');
        const hasSelectedFields = fields_sel.find('option').length>0;
        const panel = this.element.find('#field_selection_panel');
        const button = this.element.find('#btn_select_another_field');

        if(!hasSelectedFields){
            showSelector = true;
        }

        panel.toggle(showSelector===true);
        button.toggle(hasSelectedFields && showSelector!==true);
    },

    /**
     * @brief Adds a field selected from the Fancytree to the current thematic map's field list.
     * @memberof heurist.thematicMapping
     * @param {string} nodekey The key of the Fancytree node representing the selected field.
     * Extracts field information (code, name) from the node.
     * If the field is not already in `this.selectedFields`, adds it and updates the "Selected Fields" UI list.
     */
    _addThemeField: function( nodekey )
    {
        let tree = $.ui.fancytree.getTree( this.element.find('.rtt-tree') );
        
        let node = tree.getNodeByKey(nodekey);
        
        let key = node.key.split(':');
        key = key[key.length-1];
        
        if(!this.selectedFields[key]){
            this.selectedFields[key] = {code:node.data.code, title:node.data.name, ranges:[]};
            let sel = this.element.find('#selected_fields');
            
            //+' ('+this.selectedFields[key].code+'  '+key+')'
            window.hWin.HEURIST4.ui.addoption(sel[0], key, this.selectedFields[key].title);

            // The newly added field becomes the current field, then collapse the
            // selection tree so the user can concentrate on defining its ranges.
            sel.val(key).trigger('change');
            this._updateFieldSelectionVisibility(false);
        } 
        
    },
    
    
    
    /**
     * @brief Saves the configuration of the currently selected theme field from the UI to `this.selectedFields`.
     * @memberof heurist.thematicMapping
     * If `this.currentField` is set (a field is selected for editing):
     *  - Iterates through the range definition elements in the UI.
     *  - Updates the `value` and `symbol` for each range in the `selfield.ranges` array.
     *  - Removes ranges with empty values.
     *  - Updates the field's title from the UI input.
     */
    _saveThemeField: function(){
        
        if(this.currentField>0){
            let selfield = this.selectedFields[this.currentField];
            //get values from UI
            let main_area = this.element.find('#div_work_area');        
            let f_ranges = main_area.find('#f_ranges');
            
            if(f_ranges.children().length>0){
                $.each(f_ranges.children(), function(i, ele){

                    ele = $(ele);
                    
                    let range_uid = ele.attr('id');

                    $.each(selfield.ranges,function(i,item){
                        if(item.uid  == range_uid){
                            
                            let val1;
                            if(ele.find('select.val1').length>0){
                                val1 = ele.find('select.val1').val();
                            }else{
                                val1 = ele.find('input.val1').val();
                                let val2 = ele.find('input.val2').val();
                                if(val1 && val2) {
                                    val1 = val1+'<>'+val2
                                }else if(val2) {
                                    val1 = val2;
                                }
                            }
                            
                                
                            if(val1){
                                selfield.ranges[i].value = val1;
                                let symb = window.hWin.HEURIST4.util.isJSON(ele.find('.field-symbol').val());
                                selfield.ranges[i].symbol = symb?symb:'';
                                selfield.ranges[i].uid = null;
                                delete selfield.ranges[i].uid;
                            }else{
                                //remove empty value ranges
                                selfield.ranges.splice(i,1);        
                            }
                            
                            return false;
                        }
                    });
                });
            }else{
                selfield.ranges = [];
            }

            selfield.title = main_area.find('#f_title').val();
            
            this.selectedFields[this.currentField] = selfield;
        }
        
    },
    
    /**
     * @brief Handles selection of a field from the "Selected Fields" list for editing its ranges.
     * @memberof heurist.thematicMapping
     * @param {Event} [event] The change event object from the selection list.
     * Saves the configuration of the previously selected field (if any) via `_saveThemeField()`.
     * Sets `this.currentField` to the newly selected field's ID.
     * Clears and repopulates the range definition area (`#f_ranges`) based on the selected field's configuration.
     * Shows the work area if a field is selected, hides it otherwise.
     */
    _onThemeFieldSelect: function(event){                                                           
        
        let main_area = this.element.find('#div_work_area');        
        let f_ranges = main_area.find('#f_ranges');
        let selfield;
        
        //save previous
        this._saveThemeField();
        
        this.currentField = event?$(event.target).val():0;
        
        f_ranges.empty()
        
        if(this.currentField>0){
            this.element.find('#div_work_area').show();    
        
            selfield = this.selectedFields[this.currentField];
            
            //add title
            main_area.find('#f_title').val(selfield.title);
            
            //add ranges elements
            for (let k=0;k<selfield.ranges.length;k++){
                this._defThemeFieldRange(k, selfield.ranges[k]);
            }
        }else{
            this.element.find('#div_work_area').hide();    
        }
    },
    
    /**
     * @brief Defines and renders a single range configuration row in the UI.
     * @memberof heurist.thematicMapping
     * @param {number} idx The index of the range in the field's `ranges` array.
     * @param {object} range The range object containing `value` and `symbol`.
     * Creates HTML elements for editing a range:
     *  - A remove button.
     *  - Input(s) for the range value(s) (a select for enum type, one or two inputs for numeric/date ranges).
     *  - A preview span for the symbol.
     *  - An input for the JSON symbol definition.
     * Populates these elements with the `range` data and sets up symbol editor and event handlers.
     * Assigns a unique ID to the range's UI element and stores it in `range.uid`.
     */
    _defThemeFieldRange: function(idx, range){   

        let selfield = this.selectedFields[this.currentField];
        let key = selfield.code.split(':');
        key = key[key.length-1];//dty_ID
        let dty_Type = $Db.dty(key, 'dty_Type');
        let vocab_id = $Db.dty(key, 'dty_JsonTermIDTree');

        let ele = $('<div style="padding:5px" class="field-range">'
            +'<span class="ui-icon ui-icon-circle-b-close" style="margin:2px 0 0 12px;cursor:pointer"></span>'
            + ((dty_Type=='enum')
            ? '<select class="val1 text ui-widget-content ui-corner-all" style="width:100px;margin-left:5px"></select>'
            : ('<input class="val1 text ui-widget-content ui-corner-all" style="width:100px;margin-left:5px"/>'
              +'<span>&nbsp;&lt;&gt;&nbsp;</span><input class="val2 text ui-widget-content ui-corner-all" style="width:100px"/>'))
            +'<span class="field-symbol-preview" title="Click to edit symbol" '
            +'style="display:inline-block;vertical-align:middle;min-width:76px;height:34px;margin:2px 6px;cursor:pointer"></span>'
            +'<input type="hidden" class="field-symbol"/>'
            +'</div>').appendTo(this.element.find('#f_ranges'));

        ele.uniqueId();
        let uid = ele.attr('id');
        range.uid = uid;

        let val1 = range.value, val2 = '';
        if(val1 && val1.indexOf('<>')>0){
            let vals = val1.split('<>');
            val2 = (vals && vals.length==2)?vals[1]:'';
            val1 = (vals && vals.length==2)?vals[0]:'';
        }

        this._on(ele.find('.field-symbol'), {change:function(event){
            
            this._renderSymbolPreview( $(event.target).parent().find('.field-symbol-preview'), 
                    this.mapDefaultSymbol, this.element.find('#tm_symbol').val(), $(event.target).val());
        }});
        
        if(dty_Type=='enum'){
            window.hWin.HEURIST4.ui.createTermSelect(ele.find('select.val1')[0],
                {vocab_id:vocab_id, //headerTermIDsList:headerTerms,
                    defaultTermID:val1, supressTermCode:true, 
                    useHtmlSelect:false});                
        }else{
            ele.find('input.val1').val(val1)
            ele.find('input.val2').val(val2);
        }
        
        if(range.symbol){
            ele.find('.field-symbol').val($.isPlainObject(range.symbol)?JSON.stringify(range.symbol):range.symbol);    
        }
        ele.find('.field-symbol').trigger('change');

        this._initSymbolEditor( ele.find('.field-symbol') ); 
        
        
        this._on(ele.find('.ui-icon-circle-b-close'),{click:function(event){
            let ele = $(event.target).parents('.field-range');
            let range_uid = ele.attr('id');
            ele.remove();

            let selfield = this.selectedFields[this.currentField];

            $.each(selfield.ranges,function(i,item){
                if(item.uid  == range_uid){
                    selfield.ranges.splice(i,1);     
                    return false;
                }
            });
        }});

    },
    
    /**
     * @brief Initializes the symbol editor for a given symbol input field.
     * @memberof heurist.thematicMapping
     * @param {jQuery} fele The jQuery object for the symbol input field.
     * Makes the input read-only and appends "Reset" and "Open editor" buttons.
     * The "Open editor" button launches the `showEditSymbologyDialog`.
     */
    _initSymbolEditor: function(fele){

        const parent = fele.parent();
        const preview = parent.find('.field-symbol-preview').first();

        const openEditor = function(){
            let current_val = window.hWin.HEURIST4.util.isJSON( fele.val() );
            if(!current_val) current_val = {};
            window.hWin.HEURIST4.ui.showEditSymbologyDialog(current_val, 4, function(new_value){
                fele.val(JSON.stringify(new_value)).trigger('change');
            });
        };

        // Keep JSON as internal state for the existing save logic, but do not expose it.
        fele.attr('type', 'hidden');

        preview.attr('tabindex', '0').on({
            click: openEditor,
            keydown: function(event){
                if(event.key==='Enter' || event.key===' '){
                    event.preventDefault();
                    openEditor();
                }
            }
        });

        $('<span>')
            .addClass('smallbutton ui-icon ui-icon-circlesmall-close')
            .attr('tabindex', '-1')
            .attr('title', 'Reset to inherited symbology')
            .appendTo(parent)
            .css({'line-height':'20px',cursor:'pointer',outline:'none','outline-style':'none',
                  'box-shadow':'none','border-color':'transparent'})
            .on({click:function(){
                window.hWin.HEURIST4.msg.showMsgDlg('<br>Are you sure?', function(){
                    fele.val('').trigger('change');
                });
            }});

        $('<span>Edit</span>', {title:'Open symbology editor'})
            .addClass('smallbutton btn_add_term')
            .css({'line-height':'20px','vertical-align':'middle',cursor:'pointer','text-decoration':'underline'})
            .appendTo(parent)
            .on({click:openEditor});

    },//_initSymbolEditor
    
    /**
     * @brief Handles actions from the toolbar for the currently selected field's ranges.
     * @memberof heurist.thematicMapping
     * @param {Event} event The click event object.
     * Actions:
     *  - `btn_f_remove`: Removes the current field from the thematic map.
     *  - `btn_f_range_add`: Adds a new empty range definition for the current field.
     *  - `btn_f_range_auto`: Opens the dialog to automatically define ranges (`_defineAutoRanges`).
     *  - `btn_f_range_reset`: Removes all ranges for the current field.
     *  - `btn_f_range_symb`: Opens a dialog to set symbol gradients across existing ranges.
     */
    _onThemeFieldAction: function(event){
        
        if(this.currentField>0){
            
            let that = this;
        
            let key = $(event.target).is('button')
                                ?$(event.target).attr('id')
                                :$(event.target).parent().attr('id');
            
            if(key=='btn_f_remove'){
                window.hWin.HEURIST4.msg.showMsgDlg('<br>Are you sure?',
                function(){

                that.element.find('#selected_fields').find('option[value="'+that.currentField+'"]').remove();                
                that.selectedFields[that.currentField] = null;
                delete that.selectedFields[that.currentField];
                that.currentField = 0;
                that._onThemeFieldSelect();
                that._updateFieldSelectionVisibility(
                    that.element.find('#selected_fields option').length===0
                );
                
                });
                
            }else if(key=='btn_f_range_add'){
                
                let selfield = this.selectedFields[this.currentField];
                let idx = selfield.ranges.length
                selfield.ranges.push({value:'', symbol:''});
                
                this._defThemeFieldRange(idx, selfield.ranges[idx]);
            
            }else if(key=='btn_f_range_auto'){
                
                //find min/max and unique values show ranges dialog
                let selfield = this.selectedFields[this.currentField];
                this._defineAutoRanges(selfield.code);
            
            }else if(key=='btn_f_range_reset'){
                
                window.hWin.HEURIST4.msg.showMsgDlg('<br>Are you sure?',
                function(){
                    that.selectedFields[that.currentField].ranges = [];
                    that.element.find('#f_ranges').empty();
                });
                
            }else if(key=='btn_f_range_symb'){

                let selfield = this.selectedFields[this.currentField];
                let cnt = selfield.ranges.length;
                
                window.hWin.HEURIST4.ui.showEditSymbologyDialog({}, 5, function(new_value){
                    let fillGradient = [], colorGradient = [], strokeOpacity = [], fillOpacity = [], iconSize = [];
                    if(new_value.fillColor1 && new_value.fillColor2){
                        fillGradient = window.hWin.HEURIST4.ui.getColourGradient(new_value.fillColor1, new_value.fillColor2, cnt);
                    }
                    if(new_value.strokeColor1 && new_value.strokeColor2){
                        colorGradient = window.hWin.HEURIST4.ui.getColourGradient(new_value.strokeColor1, new_value.strokeColor2, cnt);
                    }
                    
                    function __prepareInt(val){
                        if(!window.hWin.HEURIST4.util.isNumber(val)){
                            val = 0
                        }else{
                            val = parseInt(val);
                        }
                        return val;
                    }

                    new_value.fillOpacity1 = __prepareInt(new_value.fillOpacity1);
                    new_value.fillOpacity2 = __prepareInt(new_value.fillOpacity2);

                    if(new_value.fillOpacity1>0 || new_value.fillOpacity2>0){
                        let step = (new_value.fillOpacity2 - new_value.fillOpacity1)/cnt;
                        let val = new_value.fillOpacity1;
                        for(let i=0; i<cnt; i++){
                            fillOpacity.push((i==cnt-1 || val>new_value.fillOpacity2)?new_value.fillOpacity2:val);
                            val = Math.round(val + step);
                        }
                    }

                    new_value.strokeOpacity1 = __prepareInt(new_value.strokeOpacity1);
                    new_value.strokeOpacity2 = __prepareInt(new_value.strokeOpacity2);

                    if(new_value.strokeOpacity1>0 || new_value.strokeOpacity2>0){
                        let step = (new_value.strokeOpacity2 - new_value.strokeOpacity1)/cnt;
                        let val = new_value.strokeOpacity1;
                        for(let i=0; i<cnt; i++){
                            strokeOpacity.push((i==cnt-1 || val>new_value.strokeOpacity2)?new_value.strokeOpacity2:val);
                            val = Math.round(val + step);
                        }
                    }

                    new_value.iconSize1 = __prepareInt(new_value.iconSize1);
                    new_value.iconSize2 = __prepareInt(new_value.iconSize2);

                    if(new_value.iconSize1>0 || new_value.iconSize2>0){
                        let step = (new_value.iconSize2 - new_value.iconSize1)/cnt;
                        let val = new_value.iconSize1;
                        for(let i=0; i<cnt; i++){
                            iconSize.push((i==cnt-1 || val>new_value.iconSize2)?new_value.iconSize2:val);
                            val = Math.round(val + step);
                        }
                    }

                    
                    let f_ranges = that.element.find('#f_ranges');
                    
                    for(let i=0; i<cnt; i++){
                        
                        let symbol = window.hWin.HEURIST4.util.isJSON(selfield.ranges[i].symbol);
                        if(!symbol) symbol = {};
                        if(fillGradient.length>0){
                            symbol.fillColor = fillGradient[i];
                        }
                        if(colorGradient.length>0){
                            symbol.color = colorGradient[i];
                        }
                        if(fillOpacity.length>0){
                            symbol.fillOpacity = fillOpacity[i];
                        }
                        if(strokeOpacity.length>0){
                            symbol.opacity = strokeOpacity[i];
                        }
                        if(iconSize.length>0){
                            symbol.iconSize = iconSize[i];
                        }
                        
                        selfield.ranges[i].symbol = symbol;
                        //assign to UI
                        f_ranges.find('.field-range[id='+selfield.ranges[i].uid+'] > .field-symbol')
                                        .val(JSON.stringify(symbol)).trigger('change');
                    }
                });
                
                
            }
            
        }
    },
    
    /**
     * @brief Opens a dialog to automatically define ranges for the selected field.
     * @memberof heurist.thematicMapping
     * @param {string} code The field code (e.g., "rtyID:dtyID" or "dtyID").
     * Fetches unique values or min/max for the specified field based on `this.options.maplayer_query`.
     * Presents a dialog (`#divAutoRanges`) allowing the user to configure how ranges are generated
     * (number of intervals, rounding for numeric/date, use all terms or DB values for enums).
     * Calls `_definePreviewRanges` to update the preview as settings change.
     */
    _defineAutoRanges: function(code){
        
        let $dlg;
        
        let field = window.hWin.HEURIST4.query.createFacetQuery(code, true, false);
        field['type'] = $Db.dty(field['id'], 'dty_Type');
         
        this.popele.find('.numeric').hide();

        if (field['type']=='enum'){
            this.popele.find('.enum').show();
        }else{ 
            //'year','date','integer','float','resource'
            this.popele.find('.enum').hide();
            if(field['type']=='date'){
                this.popele.find('.date').show();    
            }else{
                this.popele.find('.numeric').show();    
            }
        }
        
        this.fieldSelected = null;
        this.enumValues = null;
        this.popele.find('input').val('');
        this.popele.find('#int_count').val(10);
        
        let that = this;
        //
        // substitute $IDS in facet query with list of ids OR current query(todo)
        // 
        if(this.options.maplayer_query){

            function __fillQuery(q){
                $(q).each(function(idx, predicate){

                    $.each(predicate, function(key,val)
                        {
                            if( Array.isArray(val) || $.isPlainObject(val) ){
                                __fillQuery(val);
                            }else if( (typeof val === 'string') && (val == '$IDS') ) {
                                //substitute with array of ids
                                predicate[key] = that.maplayer_ids?that.maplayer_ids:that.options.maplayer_query;
                            }
                    });                            
                });
            }        


            let query;
            if( (typeof field['facet'] === 'string') && (field['facet'] == '$IDS') ){ //this is field form target record type
                //replace with list of ids
                query = this.options.maplayer_query;

            }else{
                if(!this.maplayer_ids && !window.hWin.HEURIST4.util.isJSON(this.options.maplayer_query)){
                    window.hWin.HEURIST4.msg.showMsgDlg('To allow thematic map be based on values from linked records, '
                    +'Map layer query must be in JSON format');
                    return;
                }
                
                query = window.hWin.HEURIST4.util.cloneJSON(field['facet']); //clone 
                //change $IDS for current set of target record type
                __fillQuery(query);                
            }

            let request = {q: query, count_query:null, w: 'a', a:'getfacets',
                facet_index: 0, 
                field:  field['id'],
                type:   field['type'],
                step:   0,
                facet_type: 1, //0 direct search search, 1 - select/slider, 2 - list inline, 3 - list column
                facet_groupby: null, //by first char for freetext, by year for dates, by level for enum
                vocabulary_id: null, //special case for firstlevel group - got it from field definitions
                needcount: 0,         
                qname:'',
                //request_id:this._request_id,
                source:this.element.attr('id') }; //, facets: facets

            window.HAPI4.RecordMgr.get_facets(request, function(response){ 
                if(response.status == window.hWin.ResponseStatus.OK){

                    that.fieldSelected = field;

                    if(field['type']=='enum'){
                        that.enumValues = response.data;
                    }else{
                        try{
                            that.popele.find('#int_min').val(response.data[0][0]);
                            that.popele.find('#int_max').val(response.data[0][1]);
                        }catch{
                            console.error('cannot assign min max values');
                        }
                    }

                    that._definePreviewRanges();
                    
                }else{
                    console.error(response.message);
                }
            });            
        }
        
            
        
        let btns = [
                    {text:window.hWin.HR('Apply'),
                        click: function(){

                            $dlg.dialog('close');

                            //create new ranges
                            if(that.preview_ranges.length>0){
                                
                                let selfield = that.selectedFields[that.currentField];
                                selfield.ranges = [];
                                
                                let main_area = that.element.find('#div_work_area');        
                                main_area.find('#f_ranges').empty();
                                
                                //add ranges elements
                                for (let k=0;k<that.preview_ranges.length;k++){
                                    let range = that.preview_ranges[k];
                                    selfield.ranges.push({value:$.isPlainObject(range)?(range.min+'<>'+range.max):range, symbol:''})
                                    that._defThemeFieldRange(k, selfield.ranges[k]);
                                }                                
                                
                            }
                        }
                    },
                    {text:window.hWin.HR('Close'),
                        click: function() { $dlg.dialog('close'); }
                    }
                ];

                $dlg = window.hWin.HEURIST4.msg.showElementAsDialog({
                    window:  window.hWin, //opener is top most heurist window
                    title: window.hWin.HR('Define ranges'),
                    width: 400,
                    height: 600,
                    element:  this.popele[0],
                    resizable: true,
                    buttons: btns,
                    default_palette_class: 'ui-heurist-design'
                });        
    },
    
    /**
     * @brief Event listener for range definition controls; recalculates and displays preview ranges.
     * @memberof heurist.thematicMapping
     * Clears `this.preview_ranges` and the preview UI area.
     * Based on the selected field type (`dty_Type`) and UI inputs (min, max, count, rounding for numeric/date;
     * source for enums - DB values or all terms), it calculates and populates `this.preview_ranges`.
     * Renders the preview of these ranges in `#ranges_preview`.
     */
    _definePreviewRanges: function(){

        this.preview_ranges = [];
        let ranges = [];
        let div_preview = this.popele.find('#ranges_preview').empty();
        
        if(this.fieldSelected==null) return;
        
        let dty_Type = this.fieldSelected['type'];

        if(dty_Type=='enum'){
            
            if(this.popele.find('#enum_db').is(':checked')){
                //actual db values
                for (let i=0; i<this.enumValues.length; i++){
                    ranges.push(this.enumValues[i][0]);
                }
            }else{
                //all available enums 
                let vocab_id = $Db.dty(this.fieldSelected['id'],'dty_JsonTermIDTree');
               
                ranges = $Db.trm_TreeData(vocab_id, 'set');
            }

            for (let i=0; i<ranges.length; i++){
                $('<div style="padding:5px" class="field-range">'
                +'<span style="display:inline-block;width:100px;">'+ranges[i]+'</span>'
                +'<span>'+$Db.trm(ranges[i], 'trm_Label')+'</span>'  
                +'</div>').appendTo(div_preview);
            }
            
        }
        else{

            let minVal = parseFloat(this.popele.find('#int_min').val());
            let maxVal = parseFloat(this.popele.find('#int_max').val());
            let count = parseInt(this.popele.find('#int_count').val());
            let int_round = parseInt(this.popele.find('#int_round').val());
            
            if(isNaN(minVal) || isNaN(maxVal) || isNaN(count) || count<=0 || minVal>maxVal) return;
            
            let step = (maxVal-minVal)/count;
            
            if(dty_Type=='integer'){
                step = Math.round(step);
            }
            
            function __rnd(original){
                if(dty_Type=='float' && int_round<10){
                   
                    //return Math.round(original*multiplier)/multiplier;   
                    return int_round==0?Math.round(original): parseFloat( original.toFixed(int_round) );
                }else if(int_round>=10){
                    return Math.round(original/int_round)*int_round;   
                }else{
                    return original;
                }
            }
            
            if(int_round>=10 && int_round>=maxVal){
                ranges.push({min:minVal, max:maxVal});        
            }else{
                if(int_round>=10 &&  int_round>step){
                    step = int_round;
                }
                
                let cnt = 0;
                let val0 = minVal;
                while (val0<maxVal && cnt<count){
                    
                    let val1 = (val0+step>maxVal)?maxVal:val0+step;
                    if(cnt==count-1 && val1!=maxVal){
                        val1 = maxVal;
                    }else{
                        val1 = __rnd(val1);
                    }
                    
                    ranges.push({min:__rnd(val0), max:val1});    
                    val0 = val1;
                    cnt++;;
                }
            }

            for (let i=0; i<ranges.length; i++){
                $('<div style="padding:5px" class="field-range">'
                +'<span style="display:inline-block;width:100px;">'+ranges[i].min+'</span>'
                +('<span style="display:inline-block;width:50px;">&nbsp;to&nbsp;&lt;&nbsp;</span>'
                +'<span style="display:inline-block;width:100px">'+ranges[i].max+'</span>')
                +'</div>').appendTo(div_preview);
            }
        
        }
        
        this.preview_ranges = ranges;
    },
    
    /**
     * @brief Renders a preview of a symbol based on combined symbology.
     * @memberof heurist.thematicMapping
     * @param {?jQuery} ele The jQuery element to update with the symbol preview. If null, updates all range previews.
     * @param {object} layer_symbol The base symbology from the map layer.
     * @param {string|object} base_symbol The base symbology for the current thematic map (JSON string or object).
     * @param {string|object} range_symbol The symbology specific to the current range (JSON string or object).
     * Merges layer, thematic map base, and range-specific symbols to get the final style.
     * Updates the `background-color`, `border`, etc., of the `ele` to show a visual preview.
     */
    _renderSymbolPreview: function(ele, layer_symbol, base_symbol, range_symbol){

        const that = this;
        const renderer = window.hWin.HEURIST4.ui.renderMapSymbolPreview;

        if(!window.hWin.HEURIST4.util.isFunction(renderer)){
            if(!this._symbolPreviewLoading){
                this._symbolPreviewLoading = $.getScript(
                    window.hWin.HAPI4.baseURL+'hclient/widgets/entity/popups/mapSymbolPreview.js'
                ).done(function(){
                    that._symbolPreviewLoading = null;
                    that._renderSymbolPreview(null, that.mapDefaultSymbol,
                        that.element.find('#tm_symbol').val(), null);
                }).fail(function(){
                    that._symbolPreviewLoading = null;
                    console.error('Unable to load mapSymbolPreview.js');
                });
            }
            return;
        }

        function mergeSymbol(base, override){
            const result = window.hWin.HEURIST4.util.cloneJSON(base || {});
            override = window.hWin.HEURIST4.util.isJSON(override);
            if($.isPlainObject(override)){
                Object.keys(override).forEach(function(key){
                    result[key] = override[key];
                });

                // Keep preview semantics identical to heurist-map thematicSymbolResolver:
                // CircleMarker size is rendered from radius, while historic thematic
                // range definitions vary point size with iconSize. An iconSize override
                // therefore controls the effective circle diameter unless the range also
                // provides an explicit radius.
                if(result.iconType==='circle'
                    && Object.prototype.hasOwnProperty.call(override, 'iconSize')
                    && !Object.prototype.hasOwnProperty.call(override, 'radius')){
                    const diameter = Array.isArray(override.iconSize)
                        ? Number(override.iconSize[0])
                        : Number(override.iconSize);
                    if(Number.isFinite(diameter) && diameter>=0){
                        result.radius = diameter/2;
                    }
                }
            }
            return result;
        }

        let theme_symbol = window.hWin.HEURIST4.util.isJSON(base_symbol);
        theme_symbol = theme_symbol ? theme_symbol : layer_symbol;
        theme_symbol = window.hWin.HEURIST4.ui.prepareMapSymbol(
            window.hWin.HEURIST4.util.cloneJSON(theme_symbol || {}), null);

        if(ele==null){
            renderer(this.element.find('#tm_symbol_preview')[0], theme_symbol,
                {geometryType:null, rectypeId:this.previewRecTypeID || 12});

            this.element.find('#f_ranges .field-range').each(function(){
                const row = $(this);
                that._renderSymbolPreview(row.find('.field-symbol-preview'),
                    layer_symbol, base_symbol, row.find('.field-symbol').val());
            });
            return;
        }

        const effective_symbol = window.hWin.HEURIST4.ui.prepareMapSymbol(
            mergeSymbol(theme_symbol, range_symbol), null);
        renderer($(ele)[0], effective_symbol,
            {geometryType:null, rectypeId:this.previewRecTypeID || 12});
    }

});
