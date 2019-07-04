/**
* ruleBuilder.js  - dialog to define rules to search related records
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

$.widget( "heurist.ruleBuilder", {

    // default options
    options: {
        level: 0,
        rules: {}, // JSON: {query:String , codes:[], levels:[]}
        // codes: list of codes: source rt,dt_id,term_id,target rt,filter - it is more convenient to restore/save
        recordtypes: null,  //array or record types - list of source rt (from current main search result or from parent) - otherwise show all rectypes
        init_source_rt:null,

        //debug is_search_allowed: false,
        //debug query_request: {},   // keep current query request

        // callback
        onremove: null
    },

    _selection: null,     // current set of selected records
    _arr_fields:[],
    _arr_rectypes:[],          //list of all target rectypes for current selected source rt
    //_arr_rectypes_subset:[],   //list of all target rectypes for current selected source rt and selected field type

    _has_relation:'0',
    _has_pointers:'0',
    _has_rev_relation:'0',
    _has_rev_pointers:'0',

    // the widget's constructor
    _create: function() {

        var that = this;

        this.element.addClass('rulebuilder');

       if(this.options.level>1) {
            $('<div>')
                .css({'width':'1.5em',
                'font-size':'0.8em', 'color':'gray',
                'margin-left':( (this.options.level-1)*20-10)+'px'})
                .html(this.options.level-1).appendTo(this.element);
       }

        //create list/combobox of source record types
        var cont = $('<div>')
            .css({'padding-top':(this.options.level==1?'10px':0),
                  'text-align':'left','width':'230px'}).appendTo(this.element);

        this.select_source_rectype = $( "<select>" )
        .attr('title', 'The starting point entity type for this rule. The result set will be expanded by following pointers/relationships from/to this type' )
        .addClass('text ui-corner-all ui-widget-content')
        .appendTo( cont );

        window.hWin.HEURIST4.ui.createRectypeSelect(this.select_source_rectype.get(0), this.options.recordtypes, 
                                                            (this.options.level==1?'select....':false), true );

        //create list/combobox of pointer/relmarker fields
        this.select_fields = $( "<select>" )
        .attr('title', 'The pointer and relationship fields in the starting point entities and in entities which point at the starting point entities' )
        .addClass('text ui-corner-all ui-widget-content')
        .appendTo( $('<div>').appendTo(this.element) );

        //create list/combobox of relation types
        this.select_reltype = $( "<select>" )
        .attr('title', 'The type of pointer or relationship which is followed to add entities to the current result set' )
        .addClass('text ui-corner-all ui-widget-content')
        .appendTo( $('<div>').appendTo(this.element) ).hide();

        //create list/combobox of target record types
        this.select_target_rectype = $( "<select>" )
        .attr('title', 'The entity type(s) which will be added to the current result set by this rule' )
        .addClass('text ui-corner-all ui-widget-content')
        .appendTo( $('<div>').appendTo(this.element) );

        //
        this.additional_filter = $( "<input>" ).addClass('text ui-corner-all ui-widget-content').css({'width':'220px'})
        .attr('title', 'Add an additional Heurist query string which will filter the set of records retrieved by this rule' )
        .appendTo( $('<div>').css({'width':'220px'}).appendTo(this.element) );

        /*this.btn_save   = $( "<button>", {text:'Save'} ).appendTo(this.element);
        this.btn_cancel = $( "<button>", {text:'Cancel'} ).appendTo(this.element);*/

        //(this.options.level<3)?'12em':
        this.div_btn2 =  $('<div>').css({'width':'auto'}).appendTo(this.element); //,'margin-left':'0.5em'

        this.btn_delete = $( "<button>", {text:'Delete'})
        .attr('title', 'Delete this step in the rule' )
        .css('font-size','0.8em')
        .button({icons: { primary: "ui-icon-closethick" }, text:false}).appendTo(this.div_btn2);

        if(this.options.level<3)
            this.div_btn =  $('<div>').css({'display':'block','margin': '4px 0 0 '+this.options.level*25+'px'}).appendTo(this.element); //,'margin-left':'0.5em'
            this.btn_add_next_level = $( "<button>", {text:'Add Step '+this.options.level} )
            .attr('title', 'Adds another step to this rule' )
            .css('font-size','0.8em')
            .button().appendTo(this.div_btn);




        /* TODO: remove debu code
        if(this.options.is_search_allowed){
        this.debug_search = $( "<button>", {text:'Search'} ).appendTo(this.element);
        this.debug_label = $( "<label>" ).css('padding-left','10px').appendTo(this.element);
        this._on( this.debug_search, { click: this._debugSearch });
        }*/

        //event handlers
        this._on( this.select_source_rectype, { change: this._onSelectRectype });

        this._on( this.select_fields, { change: this._onSelectFieldtype });

        //this._on( this.select_target_rectype, { change: this._generateQuery });
        //this._on( this.select_reltype, { change: this._generateQuery });

        this._on( this.btn_delete, {click: this._removeRule }); // function( event ){ this._trigger( "onremove", event, { id:this.element.attr('id') } ) } } );

        if(this.options.level<3)
            this._on( this.btn_add_next_level, {click: function( event ){ this._addChildRule(null); }});


        this._initRules();

        //this._onSelectRectype();
        //this._refresh();


    }, //end _create

    //
    //
    //
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

    //
    //
    //
    _addChildRule: function(rules){

        var new_level = this.options.level + 1;
        var that = this;

        $("<div>").uniqueId().ruleBuilder({level:new_level,
            rules: rules,
            init_source_rt: this._getTargetRt(),
            onremove: function(event, data){      //not used
                $('#'+data.id).remove();    //remove this RuleSets builder
        } }).appendTo(this.element);

        //disable itself
        $.each(this.element.find("select"), function(index, value){
            var ele = $(value);
            if(ele.parent().parent()[0] == that.element[0]){
                $(value).prop('disabled', true);
            }
        });

    },

    // Any time the widget is called with no arguments or with only an option hash,
    // the widget is initialized; this includes when the widget is created.
    _init: function() {

    },
    // TODO: remove big block of debug or old code
    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    /*_setOptions: function( ) {
    this._superApply( arguments );
    },*/

    /*
    _setOption: function( key, value ) {
    this._super( key, value );
    if ( key === "recordtypes" ) {

    window.hWin.HEURIST4.ui.createRectypeSelect(this.select_source_rectype.get(0), value, false, true);
    this._onSelectRectype();
    this._refresh();
    }
    },*/

    /*
    * private function
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

        // TODO: remove big block of debug or old code
        /*if(window.hWin.HAPI4.currentUser.ugr_ID>0){
        $(this.element).find('.logged-in-only').css('visibility','visible');
        }else{
        $(this.element).find('.logged-in-only').css('visibility','hidden');
        }*/
        //if(this.debug_label)
        //this.debug_label.html(this.options.queries.join(' OR '));
    },
    //
    // custom, widget-specific, cleanup.
    _destroy: function() {

        // remove generated elements
        this.select_source_rectype.remove();
        this.select_target_rectype.remove();
        this.select_fields.remove();
        this.select_reltype.remove();
        this.additional_filter.remove();

        /*this.label_1.remove();
        this.label_2.remove();
        this.label_3.remove();
        this.label_4.remove();
        this.label_5.remove();*/

        if(this.debug_label) this.debug_label.remove();
        this.div_btn2.remove();
        if(this.div_btn) his.div_btn.remove();
    },


    //update relation and target selectors
    _onSelectRectype: function(event){

        var rt_ID = this.select_source_rectype.val(); //event.target.value;

        //find all relation types
        // a. pointer fields
        // b. relation types for relmarkers
        // c. all recordtypes contrained in pointer and relmarkers fields

        var rectypes = window.hWin.HEURIST4.rectypes,
        details = rectypes.typedefs[rt_ID],
        source_rt_name = rectypes.names[rt_ID];
        

        if(details){
            details = details.dtFields;
        }else{
            return;
        }

        var fi_name = rectypes.typedefs.dtFieldNamesToIndex['rst_DisplayName'],
        fi_type = rectypes.typedefs.dtFieldNamesToIndex['dty_Type'],
        fi_rectypes = rectypes.typedefs.dtFieldNamesToIndex['rst_PtrFilteredIDs'],
        fi_term = rectypes.typedefs.dtFieldNamesToIndex['rst_FilteredJsonTermIDTree'],
        fi_term_dis = rectypes.typedefs.dtFieldNamesToIndex['rst_TermIDTreeNonSelectableIDs'];

        var arr_fields = [], arr_rectypes = []; //arr_terms = [], arr_terms_dis = [],

        this._has_relation = '0';
        this._has_pointers = '0';
        this._has_rev_relation = '0';
        this._has_rev_pointers = '0';

        var rtyID, dtyID;
        for (dtyID in details){ //serch on record type structure definition
            if(dtyID){

                if(details[dtyID][fi_type]=='resource' || details[dtyID][fi_type]=='relmarker'){

                    var name = details[dtyID][fi_name];

                    if(!window.hWin.HEURIST4.util.isempty(name)){

                        name = window.hWin.HEURIST4.util.trim_IanGt(name);

                        //find constraints
                        var constraints = details[dtyID][fi_rectypes];
                        constraints = ( typeof(constraints) === "string" && !window.hWin.HEURIST4.util.isempty(constraints) )
                        ? constraints.split(","):[];  // $.parseJSON(temp)
                        if(!window.hWin.HEURIST4.util.isArray(constraints)){
                            constraints = [constraints];
                        }
                        if(constraints.length>0) arr_rectypes = arr_rectypes.concat(constraints);

                        //
                        if(details[dtyID][fi_type]=='relmarker'){ // relationship marker field

                            this._has_relation = '1';

                            var temp = details[dtyID][fi_term_dis];
                            temp = ( typeof(temp) === "string" && !window.hWin.HEURIST4.util.isempty(temp) )
                            ?  temp.split(",") :[];
                            if(temp.length>0) arr_terms_dis = arr_terms_dis.concat(temp);
                            arr_fields.push({key:dtyID, title: source_rt_name + ' >> ' + name, 
                                    terms:details[dtyID][fi_term], terms_dis:temp, rectypes:constraints});

                        }else{ // pointer field

                            this._has_pointers = '1';
                            arr_fields.push({key:dtyID, title:source_rt_name + ' >> ' + name, rectypes:constraints});
                        }

                    }
                }

            }
        }
        //find all reverse links (pointers and relation that point to selected rt_ID)
        var alldetails = rectypes.typedefs;
        for (rtyID in alldetails)
            if(rtyID && rtyID!=rt_ID){
                details = alldetails[rtyID].dtFields;
                for (dtyID in details){
                    if(dtyID){

                        if(details[dtyID][fi_type]=='resource' || details[dtyID][fi_type]=='relmarker'){

                            var name = details[dtyID][fi_name];

                            if(!window.hWin.HEURIST4.util.isempty(name)){

                                name = window.hWin.HEURIST4.util.trim_IanGt(name);

                                //find constraints
                                var constraints = details[dtyID][fi_rectypes];
                                constraints = ( typeof(constraints) === "string" && !window.hWin.HEURIST4.util.isempty(constraints) )
                                ? constraints.split(","):[];  // $.parseJSON(temp)
                                if(!window.hWin.HEURIST4.util.isArray(constraints)){
                                    constraints = [constraints];
                                }
                                //verify that selected record type is in this constaint
                                if(constraints.length<1 || constraints.indexOf(rt_ID)<0) continue;

                                //temp arr_rectypes.push(rtyID);

                                var isnotfound = true;
                                var i, len = arr_fields.length;
                                for (i=0;i<len;i++){
                                    if( (arr_fields[i].key+'r') == dtyID){   //this reverse field may be added already
                                        //temp arr_fields[i].rectypes.push(rtyID);
                                        if(arr_fields[i].isreverse){
                                            arr_fields[i].rectypes.push(rtyID); //temp
                                        
                                            var tlen = arr_fields[i].title.length; //text on dropdown already too long    
                                            if(tlen<73){
                                                if(tlen>=70){
                                                    arr_fields[i].title = arr_fields[i].title.substr(0,70)+'...';
                                                }else{
                                                    var rt_name = alldetails[rtyID].commonFields[0];
                                                    //'<< '+rt_name + ' . ' + name
                                                    arr_fields[i].title = '<< '+rt_name+' | '+arr_fields[i].title.substr(3,tlen-1);
                                                }
                                            }
                                            isnotfound = false;
                                            break;
                                        }

                                    }
                                }//for fields

                                //
                                if(isnotfound){ //it means this is reverse

                                    name = window.hWin.HEURIST4.detailtypes.typedefs[dtyID].commonFields[1];
                                    name = window.hWin.HEURIST4.util.trim_IanGt(name);

                                    var rt_name = alldetails[rtyID].commonFields[0];

                                    if(details[dtyID][fi_type]=='relmarker'){ // reverse relationship marker

                                        this._has_rev_relation = '1';

                                        var temp = details[dtyID][fi_term_dis];
                                        temp = ( typeof(temp) === "string" && !window.hWin.HEURIST4.util.isempty(temp) ) 
                                                        ?  temp.split(",") :[];
                                                        
                                        if(temp.length>0) arr_terms_dis = arr_terms_dis.concat(temp);

                                        arr_fields.push({key:(dtyID+'r'), title:'<< '
                                                    +rt_name + ' . ' + name, terms:details[dtyID][fi_term],

                                            terms_dis:temp, rectypes:[rtyID], isreverse:true, dtyID:dtyID });

                                    }else{ // reverse pointer

                                        this._has_rev_pointers = '1';
                                        arr_fields.push({key:(dtyID+'r'), title:'<< '
                                                    +rt_name + ' . ' + name, rectypes:[rtyID], isreverse:true, dtyID:dtyID });
                                    }
                                } // reverse pointer

                            }
                        }

                    }
                }
        }

        arr_rectypes = $.unique(arr_rectypes);
        //arr_terms_dis = $.unique(arr_terms_dis);

        this._arr_fields = arr_fields;
        this._arr_rectypes = arr_rectypes;
        //this._arr_rectypes_subset = arr_rectypes;

        // TODO: remove big block of debug or old code
        //fill selectors
        /*window.hWin.HEURIST4.ui.createRectypeSelect(this.select_target_rectype.get(0), arr_rectypes, 'any');

        if(this.select_target_rectype.find("option").length>2){
        this.select_target_rectype.show();
        }else{
        this.select_target_rectype.hide();
        }
        */
        if(arr_fields.length!=1){
            arr_fields.unshift({key:'', title:'Any pointer or relationship'}); //add any as a first element
            this.select_fields.prop('disabled', false);
            //this.select_fields.show();
        }else if (arr_fields.length==1){
            //this.select_fields.hide();
            this.select_fields.prop('disabled', false); //true);
        }

        window.hWin.HEURIST4.ui.createSelector(this.select_fields.get(0), arr_fields);
        this.select_fields.prop("selectedIndex",0);//.val('');
        this._onSelectFieldtype();

        // TODO: remove big block of debug or old code
        // selObj, datatype, termIDTree, headerTermIDsList, defaultTermID, topOptions, needArray
        //window.hWin.HEURIST4.ui.createTermSelectExt(this.select_reltype.get(0), 'relation', arr_terms, arr_terms_dis, null, arr_fields, false);

    },


    _findField: function(dt_ID){

        var i, len = this._arr_fields.length;
        for (i=0;i<len;i++){
            var arr_field = this._arr_fields[i];
            if(arr_field.key == dt_ID){
                return arr_field;
            }
        }
        return null;
    },

    //
    // load relation types
    //
    _onSelectFieldtype: function(event){

        var dt_ID_key = this.select_fields.val(); //event?event.target.value:'',
        is_not_relation = true,
        is_not_selected = true; //field is not defined/selected
        if(dt_ID_key!='') {

            var i, len = this._arr_fields.length;
            for (i=0;i<len;i++){
                var arr_field = this._arr_fields[i];
                if(arr_field.key == dt_ID_key){
                    if(arr_field.terms){
                        is_not_relation = false;
                        //this.label_3.show();
                        this.select_reltype.show();
                        this.select_reltype.prop('disabled', false);
                        window.hWin.HEURIST4.ui.createTermSelectExt(this.select_reltype.get(0), 'relation', arr_field.terms, arr_field.terms_dis, null, 'Any relationship type', false, true);
                    }
                    //reduced list of constraints
                    window.hWin.HEURIST4.ui.createRectypeSelect(this.select_target_rectype.get(0), arr_field.rectypes, null, true); //arr_field.rectypes.length>1?'any':null);
                    if(arr_field.rectypes.length!=1){
                        window.hWin.HEURIST4.ui.addoption(this.select_target_rectype.get(0), '', 'Any record (entity) type');
                        this.select_target_rectype.val(0);
                        this.select_target_rectype.prop('disabled', false);
                    }else{
                        //this.select_target_rectype.prop('disabled', true);
                        this.select_target_rectype.prop('selectedIndex',0);
                    }
                    //this._arr_rectypes_subset = arr_field.rectypes;
                    is_not_selected = false;
                    break;
                }
            }
            if(is_not_relation){
                //this.label_3.hide();
                this.select_reltype.show();
                window.hWin.HEURIST4.ui.createSelector(this.select_reltype.get(0), [{key:'pointer', title:'pointer'}]);
                this.select_reltype.prop('disabled', true);
            }
        }else{
            this.select_reltype.hide();
        }
        if(is_not_selected){
            //show all constraints
            window.hWin.HEURIST4.ui.createRectypeSelect(this.select_target_rectype.get(0), this._arr_rectypes , null, true); //this._arr_rectypes.length>1?'any':null);
            if(this._arr_rectypes.length>1){
                window.hWin.HEURIST4.ui.addoption(this.select_target_rectype.get(0), '', 'Any record (entity) type');
            }
            this.select_target_rectype.prop('disabled', this.select_target_rectype.find("option").length==1);

            //this._arr_rectypes_subset = this._arr_rectypes;
        }

        /*if(this.select_target_rectype.find("option").length>2){
        this.label_4.show();
        this.select_target_rectype.show();
        }else{
        this.label_4.hide();
        this.select_target_rectype.hide();
        }*/

        //this._generateQuery();
    },


    // TODO: remove big block of debug or old code
    //
    // compose search query
    //
    /*
    _generateQuery: function(){

    this.options.query = '';
    //query is possible if there is at least one record pointer or relmarker field
    if(this._arr_fields.length>0) {

    var rt_target = '';

    if(!window.hWin.HEURIST4.util.isempty(this.select_target_rectype.val())){
    rt_target = this.select_target_rectype.val();
    }else{
    var opts = this.select_target_rectype.find("option");
    if(opts.length==2){
    rt_target = $(opts[1]).attr('value');
    }
    }
    if(!window.hWin.HEURIST4.util.isempty(rt_target)) rt_target = 't:'+rt_target+' ';

    var rt_source = this.select_source_rectype.val();

    var dt_ID = this.select_fields.val();

    if(!window.hWin.HEURIST4.util.isempty(dt_ID)){ //particular field is selected

    var fld = this._findField(dt_ID);

    if(this.select_reltype.is(":visible")){

    var rel_type = this.select_reltype.val();
    if(!window.hWin.HEURIST4.util.isempty(rel_type)) rel_type = '-'+rel_type;

    this.options.query = rt_target + 'related'+(fld && fld.isreverse?'_to:':'from:')+rt_source+rel_type;
    }else{
    this.options.query = rt_target + 'linked'+(fld && fld.isreverse?'_to:':'from:')+rt_source+'-'+dt_ID;
    }

    }else{ //field is not selected - search for all relations and links
    this.options.query = rt_target + 'links:'+rt_source+'-'+this._has_relation+this._has_pointers+this._has_relation+this._has_rev_pointers;
    }

    }

    this._refresh();
    },
    */

    _getTargetRt: function(){
        var rt_target = '';
        if(!window.hWin.HEURIST4.util.isempty(this.select_target_rectype.val())){
            rt_target = this.select_target_rectype.val();
        }else{
            var opts = this.select_target_rectype.find("option");
            if(opts.length==2){
                rt_target = $(opts[1]).attr('value');
            }
        }
        return rt_target;
    },

    // TODO: remove big block of debug or old code
    /*
    [rt_source, dt_ID, rel_term_id, rt_target, filter, linktype]
    Source rectype,
    pointer or relation field id,
    relation type (term) id,
    Target rectype,
    Filter ,
    linktype  0 links (any), 1 linedfrom, 2 linkedto, 3 relationfrom, 4 relatedto


    */
    _getCodes: function(){

        var rt_source   = this.select_source_rectype.val();

        if(!rt_source){
            return null;
        }


        var dt_ID = this.select_fields.val();
        var rel_term_id = this.select_reltype.val();
        var filter = this.additional_filter.val();

        var rt_target = this._getTargetRt();

        var linktype = 0;

        if(!window.hWin.HEURIST4.util.isempty(dt_ID)){ //particular field is selected
            var fld = this._findField(dt_ID);
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

        return [rt_source, dt_ID, rel_term_id, rt_target, filter, linktype];
    },

    _getQuery: function(codes){

        var query = '';

        if(window.hWin.HEURIST4.util.isArray(codes) && codes.length==6){

            var rt_source = codes[0];
            var dt_ID     = codes[1];
            var rel_type  = codes[2];
            var rt_target = codes[3];
            var filter    = codes[4];
            var linktype  = codes[5]; //0-all,1-linkto,2-linkfrom,3-relto,4-relfrom

            if(!window.hWin.HEURIST4.util.isempty(rt_target)) rt_target = 't:'+rt_target+' ';

            if(linktype>0){

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

    //select recordtype, field, reltype and ta
    _initRules: function(){

        if(this.options.rules){

            var codes = this.options.rules.codes;
            if(window.hWin.HEURIST4.util.isArray(codes) && codes.length==6){

                var rt_source = codes[0];
                var dt_ID     = codes[1];
                var rel_type  = codes[2]; //empty string for pointer
                var rt_target = codes[3];
                var filter    = codes[4];
                var linktype  = codes[5]; //0-all,1-linkto,2-linkfrom,3-relto,4-relfrom


                //assign values from codes to html elements
                this.select_source_rectype.val(rt_source);
                this._onSelectRectype();

                this.select_fields.val( dt_ID + ((linktype==1 || linktype==3)?'r':'') );
                this._onSelectFieldtype();

                if(isNaN(linktype) || linktype<0 || linktype>4){
                    linktype = 0;
                }    
                if(window.hWin.HEURIST4.util.isempty(rel_type)){
                    if(linktype==1 || linktype==2){
                        rel_type = 'pointer';
                    }else{
                        rel_type = '';
                    }
                }
                
                this.select_reltype.val(rel_type);
                this.select_target_rectype.val(rt_target);
                this.additional_filter.val(filter);

                //add and init subrules
                var that = this;
                if(window.hWin.HEURIST4.util.isArray(this.options.rules.levels))
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

    //
    // fill options.rules
    // 1. generate current query string and codes array
    // 2. recursion for all dependent rules
    //
    getRules: function(){

        // original rule array
        // rules:[ {query:query, codes:[], levels:[]}, ....  ]

        //refresh query
        var codes = this._getCodes();
        if(codes==null){
            return null;
        }else{
            var query = this._getQuery(codes);

            var sub_rules = [];

            //loop all dependent
            $.each( this.element.children('.rulebuilder') , function( index, value ) {

                var subrule = $(value).ruleBuilder("getRules");
                if(subrule!=null)  sub_rules.push(subrule);

            });

            this.options.rules = {query:query, codes:codes, levels:sub_rules};
            return this.options.rules;
        }
    }

});
