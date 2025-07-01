/**
* @file ruleBuilder.js
* @brief Dialog to define rules to search related records.
* @fileOverview This file defines the `ruleBuilder` jQuery UI widget.
* This widget provides an interface for users to construct rules for finding
* records related to a current search result set. It allows specifying
* source record types, relationship/pointer fields, target record types,
* and additional filters to refine the search for related records.
* Rules can be nested to create multi-step relationship searches.
*
* @project     Heurist academic knowledge management system
* @package  hclient\widgets\search
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

/* global showSearchBuilder */

/**
 * @widget heurist.ruleBuilder
 * @description
 * jQuery UI widget for defining rules to search for related records.
 * It allows users to build complex, multi-level queries by specifying
 * relationships and filters between record types.
 */
$.widget( "heurist.ruleBuilder", {

    /**
     * @memberof heurist.ruleBuilder
     * @instance
     * @property {Object} options - Default options for the widget.
     * @property {number} options.level - The nesting level of the current rule.
     * @property {Object} options.rules - JSON object representing the initial rules to load.
     *                                    Example: `{query:String , codes:[], levels:[]}`.
     *                                    `codes` is an array: `[source_rt, dt_id, term_id, target_rt, filter, linktype]`.
     * @property {?string[]|string} options.recordtypes - Array or comma-separated string of source record type IDs
     *                                                    to limit the initial selection. If null, all record types are shown.
     * @property {?number} options.init_source_rt - The initial source record type ID to select.
     * @property {?function} options.onremove - Callback function triggered when a rule is removed.
     */
    options: {
        level: 0,
        rules: {}, // JSON: {query:String , codes:[], levels:[]}
        // codes: list of codes: source rt,dt_id,term_id,target rt,filter - it is more convenient to restore/save
        recordtypes: null,  //array or record types - list of source rt (from current main search result or from parent) - otherwise show all rectypes
        init_source_rt:null,

        // callback
        onremove: null
    },

    /**
     * @memberof heurist.ruleBuilder
     * @instance
     * @private
     * @property {?Object} _selection - Current set of selected records (not actively used in current code).
     */
    _selection: null,     // current set of selected records
    /**
     * @memberof heurist.ruleBuilder
     * @instance
     * @private
     * @property {Array<Object>} _arr_fields - Stores information about direct and reverse resource/relation fields.
     */
    _arr_fields:[],       // all direct and reverse resource (record pointer) and relation fields
    
    /**
     * @memberof heurist.ruleBuilder
     * @instance
     * @private
     * @property {Array<string>} _arr_rectypes - List of all target record type IDs for the currently selected source record type.
     */
    _arr_rectypes:[],          //list of all target rectypes for current selected source rt

    /**
     * @memberof heurist.ruleBuilder
     * @instance
     * @private
     * @description Widget creation method. Initializes the UI elements for rule building.
     */
    _create: function() {

       let that = this;

       this.element.addClass('rulebuilder');

       if(this.options.level>1) {
            $('<div>')
                .css({'width':'1.5em',
                'font-size':'0.8em', 'color':'gray',
                'margin-left':( (this.options.level-1)*20-10)+'px'})
                .html(this.options.level-1).appendTo(this.element);
       }

        //create list/combobox of source record types
       let cont = $('<div>')
            .css({'padding-top':(this.options.level==1?'10px':0),
                  'text-align':'left','width':'230px'}).appendTo(this.element);

       this.select_source_rectype = $( "<select>" )
            .attr('title', 'The starting point entity type for this rule. The result set will be expanded by following pointers/relationships from/to this type' )
            .addClass('text ui-corner-all ui-widget-content')
            .appendTo( cont );

        
       if(that.options.recordtypes && !Array.isArray(that.options.recordtypes)){
           that.options.recordtypes = that.options.recordtypes.split(',');
       }
             
       window.hWin.HEURIST4.ui.createRectypeSelect(that.select_source_rectype.get(0), 
                                                        that.options.recordtypes, 
       (that.options.level==1 && (!that.options.recordtypes || that.options.recordtypes.length>1)?'select....':false), true );

        //create list/combobox of pointer/relmarker fields
        this.select_fields = $( "<select>" )
        .attr('title', 'The pointer and relationship fields in the starting point entities and in entities which point at the starting point entities' )
        .addClass('text ui-corner-all ui-widget-content')
        .appendTo( $('<div>').appendTo(this.element) );

        //create list/combobox of relation types
        this.select_reltype = $( "<select>" )
        .attr('title', 'The type of pointer or relationship which is followed to add entities to the current result set' )
        .addClass('text ui-corner-all ui-widget-content')
        .css({'visibility':'hidden'})
        .appendTo( $('<div>').appendTo(this.element) );

        //create list/combobox of target record types
        this.select_target_rectype = $( "<select>" )
        .attr('title', 'The entity type(s) which will be added to the current result set by this rule' )
        .addClass('text ui-corner-all ui-widget-content')
        .appendTo( $('<div>').appendTo(this.element) );

        //
        this.additional_filter = $( "<input>" ).addClass('text ui-corner-all ui-widget-content').css({'width':'220px'})
        .attr('title', 'Add an additional Heurist query string which will filter the set of records retrieved by this rule' )
        .appendTo( $('<div>').css({'width':'220px'}).appendTo(this.element) );

        /*this.btn_save   = $( "<button>", {label:'Save'} ).appendTo(this.element);
        this.btn_cancel = $( "<button>", {label:'Cancel'} ).appendTo(this.element);*/

        //(this.options.level<3)?'12em':
        this.div_btn2 =  $('<div>').css({'width':'60px'}).appendTo(this.element); //,'margin-left':'0.5em'

        this.btn_edit = $( "<button>")
        .attr('title', 'Create additional filter' )
        .css('font-size','0.8em')
        .button({icon: "ui-icon-pencil", label:'Filter', showLabel:false}).appendTo(this.div_btn2);
        
        this.btn_delete = $( "<button>")
        .attr('title', 'Delete this step in the rule' )
        .css('font-size','0.8em')
        .button({icon: "ui-icon-closethick", label:'Delete', showLabel:false}).appendTo(this.div_btn2);

        if(this.options.level<3)
            this.div_btn =  $('<div>').css({'display':'block','margin': '4px 0 0 '+this.options.level*25+'px'}).appendTo(this.element); //,'margin-left':'0.5em'
            this.btn_add_next_level = $( "<button>" )
            .attr('title', 'Adds another step to this rule' )
            .css('font-size','0.8em')
            .button({label:'Add Step '+this.options.level}).appendTo(this.div_btn);


        //event handlers
        this._on( this.select_source_rectype, { change: this._onSelectRectype });

        this._on( this.btn_delete, {click: this._removeRule });
        this._on( this.btn_edit, {click: this._editFilter }); 

        if(this.options.level<3)
            this._on( this.btn_add_next_level, {click: function( event ){ this._addChildRule(null); }});


        this._initRules();
    }, //end _create

    /**
     * @memberof heurist.ruleBuilder
     * @instance
     * @private
     * @description Opens the search builder dialog to edit the additional filter for the current rule.
     *              The target record type of the current rule is passed to the search builder.
     */
    _editFilter: function(){
            
            showSearchBuilder({is_for_rules: true, 
                                     rty_ID: this.select_target_rectype.val(),
                              input_element: this.additional_filter});
    },
    
    /**
     * @memberof heurist.ruleBuilder
     * @instance
     * @private
     * @description Removes the current rule element from the DOM.
     *              If it's the last child of a parent rule, re-enables the parent's select elements.
     */
    _removeRule: function(){
        //$('#'+this.element.attr('id')).remove();    //remove itself

        //check if parent rulebuilder has no more children
        if(this.element.parent().children('.rulebuilder').length==1){
            this.element.parent().find("select").each(function(idx, sel){
                if($(sel).find("option").length>1)
                    $(sel).prop('disabled', false); });
        }

        this.element.remove();

    },

    /**
     * @memberof heurist.ruleBuilder
     * @instance
     * @private
     * @description Adds a new child rule builder instance to the current rule.
     *              Disables the select elements of the current rule.
     * @param {?Object} rules - The initial rules to load into the new child rule builder.
     */
    _addChildRule: function(rules){

        let new_level = this.options.level + 1;
        let that = this;

        $("<div>").uniqueId().ruleBuilder({level:new_level,
            rules: rules,
            init_source_rt: this._getTargetRt(),
            onremove: function(event, data){      //not used
                $('#'+data.id).remove();    //remove this RuleSets builder
        } }).appendTo(this.element);

        //disable itself
        $.each(this.element.find("select"), function(index, value){
            let ele = $(value);
            if(ele.parent().parent()[0] == that.element[0]){
                $(value).prop('disabled', true);
            }
        });

    },

    /**
     * @memberof heurist.ruleBuilder
     * @instance
     * @private
     * @description Widget destruction method. Removes generated UI elements.
     */
    _destroy: function() {

        // remove generated elements
        this.select_source_rectype.remove();
        this.select_target_rectype.remove();
        this.select_fields.remove();
        this.select_reltype.remove();
        this.additional_filter.remove();

        this.div_btn2.remove();
        if(this.div_btn) this.div_btn.remove();
    },


    /**
     * @memberof heurist.ruleBuilder
     * @instance
     * @private
     * @description Event handler for when the source record type selection changes.
     *              Updates the available fields and target record types based on the new selection.
     * @param {Event} event - The change event.
     */
    _onSelectRectype: function(event){

        let rt_ID = this.select_source_rectype.val();

        //find all relation types
        // a. pointer fields
        // b. relation types for relmarkers
        // c. all recordtypes contrained in pointer and relmarkers fields

        let vocab_id;
        let arr_direct = {};
        let arr_reverse = [];
        let arr_rectypes = []; //all targets
        
        let all_structs = $Db.rst_idx2();
        for (let rty_ID in all_structs){
            
            let recset = all_structs[rty_ID];
            recset.each2(function(dtyID, record){
            
           
            let fieldtype = $Db.dty(dtyID, 'dty_Type');
            
            if(fieldtype=='resource' || fieldtype=='relmarker'){
                const rtyID = record['rst_RecTypeID'];
                
                let constraints = $Db.dty(dtyID, 'dty_PtrTargetRectypeIDs');
                constraints = ( typeof(constraints) === "string" && !window.hWin.HEURIST4.util.isempty(constraints) )
                                ? constraints.split(","):[];  // JSON.parse(temp)
                vocab_id = 0;
                       
                if(rtyID==rt_ID){
                    //direct
                    let name = record['rst_DisplayName'];
                    name = window.hWin.HEURIST4.util.trim_IanGt(name);
                
                    if(fieldtype=='relmarker'){
                        vocab_id = $Db.dty(dtyID, 'dty_JsonTermIDTree');
                    }    
                    arr_direct[dtyID] = {key:dtyID, 
                                title: (vocab_id>0?'>> ':'> ')+name, 
                                terms:vocab_id, rectypes:constraints};
                    
                    arr_rectypes = arr_rectypes.concat(constraints);
                    
                }else if(constraints.indexOf(rt_ID)>=0){
                    
                    const rt_name = $Db.rty(rtyID, 'rty_Name');
                    //is it inversed
                    let name = $Db.dty(dtyID, 'dty_Name');
                    name = window.hWin.HEURIST4.util.trim_IanGt(name);
                    
                    if(fieldtype=='relmarker'){
                        vocab_id = $Db.dty(dtyID, 'dty_JsonTermIDTree');
                    }
                    
                    let key = (dtyID+'r'+rtyID);
                    
                    /*
                    if(arr_reverse[key]){
                        //already exists - add rectype name
                        let title = arr_reverse[key].title;
                        let tlen = title.length; //text on dropdown already too long    
                        if(tlen<73){
                            if(tlen>=70){
                                title = title.substr(0,70)+'...';
                            }else{
                                title = '<< '+rt_name+' | '+title.substr(3,tlen-1);
                            }
                        }
                        arr_reverse[key].title = title;
                        arr_reverse[key].rectypes.push(rtyID);
                    }else{  */
                    
                    arr_reverse.push({key:key, 
                                    title: (vocab_id>0?'<< ':'< ')+name
                                        +' <span style="font-size:smaller;font-style:italic">[in '+rt_name + ']</span>', 
                                    terms:vocab_id,
                                    rectypes:[rtyID], isreverse:true, dtyID:dtyID });
                    
                }
            }
        });

        }
        this._arr_rectypes = $.uniqueSort(arr_rectypes);
        
        //make list of options for selector
        // relation than links
        this._arr_fields = arr_direct;
        
        let has_pointer = false;
        let has_relation = false;
        let arr_options = [];
        let arr_link = [], arr_rels = [];
        for(let dtyID in arr_direct){
            let opt = {key:arr_direct[dtyID].key, title:arr_direct[dtyID].title};
            if(arr_direct[dtyID].terms>0){
                has_relation = true;
                arr_rels.push(opt);    
            }else{
                has_pointer = true;
                arr_link.push(opt); 
            }
        }   
        if(arr_link.length>0 || arr_rels.length>0){
            arr_options.push({key:0, title:'Pointers > and Relationships >>', disabled:true, group:1}); //optgroup:true, 
            arr_options = arr_options.concat(arr_link);
            arr_options = arr_options.concat(arr_rels);
        }
        
        arr_link = [];
        arr_rels = [];
        
        arr_reverse.sort(function(a,b){return a.dtyID<b.dtyID?-1:1})
        
        for(let i=0; i<arr_reverse.length; i++){
            let item = arr_reverse[i];
            let opt = {key:item.key, title:item.title};
            if(item.terms>0){
                has_relation = true;
                arr_rels.push(opt);    
            }else{
                has_pointer = true;
                arr_link.push(opt); 
            }
            this._arr_fields[item.key] = item;
        }
        if(arr_link.length>0 || arr_rels.length>0){
            arr_options.push({key:0, title:'Referenced by', disabled:true, group:1}); //optgroup:true, 
            arr_options = arr_options.concat(arr_link);
            arr_options = arr_options.concat(arr_rels);
        }
        
        /* OLD WAY
        if(arr_rels.length>0){
            arr_options.push({optgroup:true, key:0, title:'Relationships', disabled:true, group:1});
            arr_options = arr_options.concat(arr_rels);
        }
        if(arr_link.length>0){
            arr_options.push({optgroup:true, key:0, title:'Pointers', disabled:true, group:1});
            arr_options = arr_options.concat(arr_link);
        }
        */        
         //add any as a first element
        if(has_pointer && has_relation){
            arr_options.unshift({key:'', title:'Any pointer or relationship'});
        }else if(has_pointer){
            arr_options.unshift({key:'', title:'Any pointer'});
        }else if(has_relation){
            arr_options.unshift({key:'', title:'Any relationship'});
        }

        //fill field selector
        window.hWin.HEURIST4.ui.createSelector(this.select_fields.get(0), arr_options);


        this.select_fields.prop('disabled', false);
        
        this.select_fields.prop("selectedIndex",0);
       
        this._onSelectFieldtype();
        
       
        let sel = window.hWin.HEURIST4.ui.initHSelect(this.select_fields, false);
        sel.hSelect( "widget" ).css({'font-size':'0.9em','max-width':'200px'});
        let that = this;
        this.select_fields = sel;
        sel.hSelect({change: function( event, data ) {
                        //that.select_fields.val(data.item.value);//change value for underlaying html select
                        that._onSelectFieldtype();
                }});
        
    },

    /**
     * @memberof heurist.ruleBuilder
     * @instance
     * @private
     * @description Finds field information from the internal `_arr_fields` array by its ID.
     * @param {string|number} dt_ID - The ID of the field to find.
     * @returns {?Object} The field object if found, otherwise undefined.
     */
    _findField: function(dt_ID){
        return this._arr_fields[dt_ID];
    },

    /**
     * @memberof heurist.ruleBuilder
     * @instance
     * @private
     * @description Event handler for when the selected field (pointer/relationship) changes.
     *              Updates the relationship type selector and target record type selector based on the selected field.
     * @param {Event} [event] - The change event (optional).
     */
    _onSelectFieldtype: function(event){

        let dt_ID_key = this.select_fields.val(), //event?event.target.value:''
            is_not_relation = true,
            is_not_selected = true; //field is not defined/selected

        if(dt_ID_key!='' && dt_ID_key!=0) {
            
            let arr_field = this._findField(dt_ID_key);

            if(arr_field){
                if(arr_field.terms){
                    is_not_relation = false;
                   
                    this.select_reltype.css({'visibility':'visible'});
                    this.select_reltype.prop('disabled', false);
                    let sel = window.hWin.HEURIST4.ui.createTermSelect(this.select_reltype.get(0),
                                {vocab_id:arr_field.terms, topOptions:'Any relationship type'});
                    sel.hSelect( "widget" ).css('font-size','0.9em');
                    this.select_reltype = sel;

                }
                //reduced list of constraints
                window.hWin.HEURIST4.ui.createRectypeSelect(this.select_target_rectype.get(0), arr_field.rectypes, null, true);
                if(arr_field.rectypes.length!=1){
                    window.hWin.HEURIST4.ui.addoption(this.select_target_rectype.get(0), '', 'Any record (entity) type');
                    this.select_target_rectype.val(0);
                    this.select_target_rectype.prop('disabled', false);
                }else{
                   
                    this.select_target_rectype.prop('selectedIndex',0);
                }
               
                is_not_selected = false;
            }

            if(is_not_relation){
               
                this.select_reltype.css({'visibility':'visible'});
                window.hWin.HEURIST4.ui.createSelector(this.select_reltype.get(0), [{key:'pointer', title:'pointer'}]);
                this.select_reltype.prop('disabled', true);
            }
        }else{
            this.select_reltype.css({'visibility':'hidden'});
        }
        if(is_not_selected){
            //show all constraints
            window.hWin.HEURIST4.ui.createRectypeSelect(this.select_target_rectype.get(0), this._arr_rectypes , null, true);
            if(this._arr_rectypes.length>1){
                window.hWin.HEURIST4.ui.addoption(this.select_target_rectype.get(0), '', 'Any record (entity) type');
            }
            this.select_target_rectype.prop('disabled', this.select_target_rectype.find("option").length==1);

           
        }

    },

    /**
     * @memberof heurist.ruleBuilder
     * @instance
     * @private
     * @description Gets the currently selected target record type ID.
     *              If "Any record type" is selected, it attempts to determine a single target if only one is available.
     * @returns {string} The target record type ID, or an empty string.
     */
    _getTargetRt: function(){
        let rt_target = '';
        if(!window.hWin.HEURIST4.util.isempty(this.select_target_rectype.val())){
            rt_target = this.select_target_rectype.val();
        }else{
            let opts = this.select_target_rectype.find("option");
            if(opts.length==2){
                rt_target = $(opts[1]).attr('value');
            }
        }
        return rt_target;
    },

    /**
     * @memberof heurist.ruleBuilder
     * @instance
     * @private
     * @description Retrieves the current rule definition as an array of codes.
     *              Format: `[source_rt, dt_id, rel_term_id, target_rt, filter, linktype]`
     * @returns {?Array<string|number>} An array representing the rule codes, or null if the source record type is not selected.
     */
    _getCodes: function(){

        let rt_source   = this.select_source_rectype.val();

        if(!rt_source){
            return null;
        }


        let dt_ID = this.select_fields.val();
        let rel_term_id = this.select_reltype.val();
        let filter = this.additional_filter.val();

        let rt_target = this._getTargetRt();

        let linktype = 0;

        if(!window.hWin.HEURIST4.util.isempty(dt_ID)){ //particular field is selected
            let fld = this._findField(dt_ID);
            if(rel_term_id=="pointer"){
                rel_term_id = '';
                linktype = (fld && fld.isreverse)?1:2; //link to/from
            }else{
                linktype = (fld && fld.isreverse)?3:4; //relatiom to/from
            }
            if(fld && fld.isreverse){
                dt_ID = fld.dtyID; //without 'r' suffix
            }
        }
        
        
        if(window.hWin.HEURIST4.util.isempty(rel_term_id) || rel_term_id<1){
            rel_term_id = '';
        }

        //1:2; link to/from
        //3:4; relatiom to/from
        return [rt_source, dt_ID, rel_term_id, rt_target, filter, linktype];
        
    },

    /**
     * @memberof heurist.ruleBuilder
     * @instance
     * @private
     * @description Generates a Heurist query string based on the provided rule codes.
     * @param {Array<string|number>} codes - An array of rule codes: `[source_rt, dt_ID, rel_type, target_rt, filter, linktype]`.
     * @returns {string} The generated Heurist query string.
     */
    _getQuery: function(codes){

        let query = '';

        if(Array.isArray(codes) && codes.length==6){

            let rt_source = codes[0];
            let dt_ID     = codes[1];
            let rel_type  = codes[2];
            let rt_target = codes[3];
            let filter    = codes[4];
            let linktype  = codes[5]; //0-all,1-linkto,2-linkfrom,3-relto,4-relfrom

            if(!window.hWin.HEURIST4.util.isempty(rt_target)) rt_target = 't:'+rt_target+' ';

            if(linktype == 5){
                if(!window.hWin.HEURIST4.util.isempty(rel_type)) rel_type = '-'+rel_type;
                query = `${rt_target}related${rt_source}${rel_type}`;
            }else if(linktype>0){

                if(linktype>2){
                    if(!window.hWin.HEURIST4.util.isempty(rel_type)) rel_type = '-'+rel_type;
                    query = rt_target + 'related'+(linktype==3?'_to:':'from:')+rt_source+rel_type;
                }else{
                    query = rt_target + 'linked'+(linktype==1?'_to:':'from:')+rt_source+'-'+dt_ID;
                }

            }else{
                query = rt_target + 'links:'+rt_source;     //all links
            }

            query = query + ' ' + filter;
        }
        return query;
    },

    /**
     * @memberof heurist.ruleBuilder
     * @instance
     * @private
     * @description Initializes the rule builder UI based on the `options.rules`.
     *              Parses the rule definition (either from `codes` or `query` object)
     *              and sets the values of the select elements and filter input.
     *              Recursively initializes child rules if present.
     */
    _initRules: function(){

        if(this.options.rules){
            let codes = this.options.rules.codes;
            
            let query = window.hWin.HEURIST4.util.isJSON(this.options.rules.query);
            
            if(!codes && query){
                    
                //parse query
                let link, keys = Object.keys(query);
                
                if(keys.length<1) return;
                
                let link_keys = ['links','lt','lf','rt','rf','related'];
                let link2 = keys[0].split(':');
                
                if(link_keys.indexOf(link2[0])>=0){
                    //for example: {"links":[{"t":"10"}]}
                    //         or  {"lf:1086":[{"t":102}]}
                    link = keys[0];    
                }else if(keys.length>1){
                    //for example: {"t":"5","lt:15":[{"t":"10"}],"plain":"Petia"}
                    link = keys[1]; //always second 
                }else{
                    //link not defined - broken rule
                    return;
                }

                let linkdata = query[link];
                
                link = link.split(':');
                
                let linktype = 0;
                let dty_ID = 0;
                let trm_ID = 0;
                                
                switch (link[0]) {
                    case 'links': linktype = 0; break;
                    case 'lt': linktype = 1; break;
                    case 'lf': linktype = 2; break;
                    case 'rt': linktype = 3; break;
                    case 'rf': linktype = 4; break;
                    case 'related': linktype = 5; break;
                    default: break;
                }
                
                if(linktype>0 && link.length>1){
                    dty_ID = link[1];
                }
                
                let rt_source = '';
                for(let i=0; i<linkdata.length; i++){
                    if(linkdata[i]['t']){
                        rt_source = linkdata[i]['t'];
                    }else if(linkdata[i]['r']){
                        trm_ID = linkdata[i]['r'];
                    }
                }
                //extract filter - since 3d element
                let filter = [];
                for(let i=2; i<keys.length; i++){
                    if(keys[i]=='plain'){
                        filter = query['plain'];
                        break;
                    }else{
                        let token = {};
                        token[keys[i]] = query[keys[i]];
                        filter.push(token);
                    }
                }
                
                if(Array.isArray(filter)){
                    filter =  (filter.length==0)?'':JSON.stringify(filter);
                }
                
                //0=source,1=dty_ID,2=vocab_id,3=target,4=filter,5=linktype
                codes = [rt_source, dty_ID, trm_ID, query['t'], filter, linktype];
                    
            }
            
            if(Array.isArray(codes) && codes.length==6){

                const rt_source = codes[0];
                const dt_ID     = codes[1];
                let rel_type  = codes[2]; //empty string for pointer
                const rt_target = codes[3];
                let filter    = codes[4];
                let linktype  = codes[5]; //0-all,1-linkto,2-linkfrom,3-relto,4-relfrom,5-related


                //assign values from codes to html elements
                this.select_source_rectype.val(rt_source);
                this._onSelectRectype();

                
                let sel_field = dt_ID;
                if(linktype==1 || linktype==3){
                    sel_field = sel_field + 'r';
                    if(rt_target>0){
                        sel_field = sel_field + rt_target;
                    }
                }

                this.select_fields.val( sel_field );
                this.select_fields.hSelect('refresh');
                this._onSelectFieldtype();

                if(isNaN(linktype) || linktype<0 || linktype>5){
                    linktype = 0;
                }    
                if(!(rel_type>0)){
                    if(linktype==1 || linktype==2){
                        rel_type = 'pointer';
                    }else{
                        rel_type = '';
                    }
                }
                
                if(rel_type==''){
                    this.select_fields.prop("selectedIndex",0);
                }else{
                    this.select_reltype.val(rel_type);
                }
             
                if(this.select_reltype.hSelect("instance"))
                        this.select_reltype.hSelect('refresh');
                this.select_target_rectype.val(rt_target);
                this.additional_filter.val(filter);

                //add and init subrules
                let that = this;
                if(Array.isArray(this.options.rules.levels))
                    $.each( this.options.rules.levels , function( index, value ) {
                        that._addChildRule(value);
                    });

                return;
            }
        }

        if(!window.hWin.HEURIST4.util.isempty(this.options.init_source_rt)){
            this.select_source_rectype.val(this.options.init_source_rt);
            this.select_source_rectype.prop('disabled', true);
        }
        this._onSelectRectype();

    },

    /**
     * @memberof heurist.ruleBuilder
     * @instance
     * @description Gets the current rule definition, including sub-rules, in an older format.
     *              The rule is represented as an object: `{query:String , codes:Array, levels:Array<Object>}`.
     * @returns {?Object} The rule object, or null if the current rule is incomplete.
     * @deprecated Use `getRules` for the current JSON format.
     */
    getRulesOld: function(){

        // original rule array
        // rules:[ {query:query, codes:[], levels:[]}, ....  ]

        //refresh query
        let codes = this._getCodes();
        if(codes==null){
            return null;
        }else{
            let query = this._getQuery(codes);

            let sub_rules = [];

            //loop all dependent
            $.each( this.element.children('.rulebuilder') , function( index, value ) {

                let subrule = $(value).ruleBuilder("getRulesOld");
                if(subrule!=null)  sub_rules.push(subrule);

            });

            this.options.rules = {query:query, codes:codes, levels:sub_rules};
            return this.options.rules;
        }
    },

    /**
     * @memberof heurist.ruleBuilder
     * @instance
     * @description Gets the current rule definition, including sub-rules, in a JSON format.
     *              The rule is represented as an object: `{query:Object, levels:Array<Object>}`.
     *              The `query` object follows the Heurist JSON query structure.
     * @returns {?Object} The rule object in JSON format, or null if the current rule is incomplete.
     */
    getRules: function(){

        // original rule array
        // rules:[ {q:query, levels:[]}, ....  ]
        
        //1:2; link to/from
        //3:4; relatiom to/from
       

        //refresh query
        let codes = this._getCodes();
        if(codes==null){
            return null;
        }else{
           
            
            const rt_source = parseInt(codes[0]),
                dty_ID    = parseInt(codes[1]),
                trm_ID    = parseInt(codes[2]),
                rt_target = parseInt(codes[3]),
                linktype = codes[5];
            let filter = codes[4];

            let link = '';
            switch (linktype) {
                case 0: link = 'links'; break;
                case 1: link = 'lt'; break;
                case 2: link = 'lf'; break;
                case 3: link = 'rt'; break; //'rt'
                case 4: link = 'rf'; break; //'rf'
                case 5: link = 'related'; break;
                default: link = 'links'; break;
            }
            
            if(linktype>0 && dty_ID>0){
                link = link+':'+dty_ID;
            }
            let query = (rt_target>0)?{"t":rt_target}:{};
            query[link] = [{"t":rt_source}];
            
            //additional filter
            if(filter){
                
                if(window.hWin.HEURIST4.util.isJSON(filter))
                {
                    filter = window.hWin.HEURIST4.util.isJSON(filter);
                    if(!Array.isArray(filter)){
                        filter = [filter];
                    }
                    for(let i=0; i<filter.length; i++){
                        let key = Object.keys(filter[i]);
                        query[key[0]] = filter[i][key[0]];    
                    }
                    
                }else{
                    query['plain'] = filter;
                }
            }
            
            if(trm_ID>0 && linktype>2){ //relation type
                query[link].push({"r":trm_ID});        
            }

            let sub_rules = [];

            //loop all dependent
            $.each( this.element.children('.rulebuilder') , function( index, value ) {

                let subrule = $(value).ruleBuilder("getRules");
                if(subrule!=null)  sub_rules.push(subrule);

            });

            this.options.rules = {query:query, levels:sub_rules};
            return this.options.rules;
        }
    }
    

});
