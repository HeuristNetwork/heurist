/**
* ruleBuilder.js  - dialog to define rules to search related records
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
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
        content: {}, 
        query: '',
        recordtypes: [],  //array or record types from current main search result - otherwise show all rectypes
        
        is_search_allowed: false,
        query_request: {},   // keep current query request
        // callback
        onremove: null
    },
    
    _selection: null,     // current set of selected records
    _arr_fields:[],
    _arr_rectypes:[],
    _has_relation:'0',
    _has_pointers:'0',
    _has_rev_relation:'0',
    _has_rev_pointers:'0',

    // the widget's constructor
    _create: function() {

        var that = this;
        
        this.element.addClass('rulebuilder');
        
        //create list/combobox of source record types
        var cont = $('<div>').css({'padding-left':( (this.options.level-1)*20)+'px', 'text-align':'left','width':'250px'}).appendTo(this.element);
        this.select_source_rectype = $( "<select>" )
                .addClass('text ui-corner-all')
                .appendTo( cont );
                
                
        top.HEURIST4.util.createRectypeSelect(this.select_source_rectype.get(0), null, false);

        //create list/combobox of pointer/relmarker fields
        this.select_fields = $( "<select>" )
                .addClass('text ui-corner-all')
                .appendTo( $('<div>').appendTo(this.element) );
        
        //create list/combobox of relation types
        this.select_reltype = $( "<select>" )
                .addClass('text ui-corner-all')
                .appendTo( $('<div>').appendTo(this.element) ).hide();
        
        //create list/combobox of target record types
        this.select_target_rectype = $( "<select>" )
                .addClass('text ui-corner-all')
                .appendTo( $('<div>').appendTo(this.element) );

        //        
        this.additional_filter = $( "<input>" ).addClass('text ui-corner-all')
                .appendTo( $('<div>').appendTo(this.element) );
        
        /*this.btn_save   = $( "<button>", {text:'Save'} ).appendTo(this.element);
        this.btn_cancel = $( "<button>", {text:'Cancel'} ).appendTo(this.element);*/
        this.div_btn =  $('<div>').appendTo(this.element);
        this.btn_delete = $( "<button>", {text:'Delete'} ).button().appendTo(this.div_btn);

        if(this.options.level<3)
        this.btn_add_next_level = $( "<button>", {text:'Add Level'} ).button().appendTo(this.div_btn);

        
        /*if(this.options.is_search_allowed){
            this.debug_search = $( "<button>", {text:'Search'} ).appendTo(this.element);
            this.debug_label = $( "<label>" ).css('padding-left','10px').appendTo(this.element);
            this._on( this.debug_search, { click: this._debugSearch });
        }*/
        
        //event handlers
        this._on( this.select_source_rectype, { change: this._onSelectRectype });
        
        this._on( this.select_fields, { change: this._onSelectFieldtype });

        this._on( this.select_target_rectype, { change: this._generateQuery });
        this._on( this.select_reltype, { change: this._generateQuery });
        
        this._on( this.btn_delete, {click: function( event ){ this._trigger( "onremove", event, { id:this.element.attr('id') } ) } } );
        
        if(this.options.level<3)
        this._on( this.btn_add_next_level, {click: function( event ){ 
            
             var new_level = this.options.level + 1;
            
             $("<div>").uniqueId().ruleBuilder({level:new_level,
                                        onremove: function(event, data){
                                              $('#'+data.id).remove();    //remove this rule builder
                                        } }).appendTo(this.element);;
        }});
        
        //-----------------------     listener of global events
        /*var sevents = top.HAPI4.Event.ON_REC_SEARCHSTART+' '+top.HAPI4.Event.ON_REC_SEARCHRESULT; 
        
        $(this.document).on(sevents, function(e, data) {

            if(e.type == top.HAPI4.Event.ON_REC_SEARCHSTART){
                
                that._query_request = data;  //keep current query request 
                
            }else if(e.type == top.HAPI4.Event.ON_REC_SELECT){
                
                   if(data) data = data.selection;
                
                   if(data && (typeof data.isA == "function") && data.isA("hRecordSet") ){
                       that._selection = data;
                   }else{
                       that._selection = null
                   }
                
            }
            //that._refresh();
        });*/        

        /* can not bind event handler for parent document - need to be triggered directly
        if(top && top.document)        
        $(top.document).on(top.HAPI4.Event.ON_REC_SEARCHRESULT, function(e, data) {

            if(e.type == top.HAPI4.Event.ON_REC_SEARCHRESULT){
                  //that.option("recordset", data); //hRecordSet
                  alert('1111');s
            }
        });
        
        $(top.parent.document).on(top.HAPI4.Event.ON_REC_SEARCHRESULT, function(e, data) {

            if(e.type == top.HAPI4.Event.ON_REC_SEARCHRESULT){
                  //that.option("recordset", data); //hRecordSet
                  alert('22222');s
            }
        });
        */
        
        this._onSelectRectype();
        this._refresh();
        

    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
        
    },
    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    /*_setOptions: function( ) {
        this._superApply( arguments );
    },*/
    
    _setOption: function( key, value ) {
        this._super( key, value );
        if ( key === "recordtypes" ) {

            top.HEURIST4.util.createRectypeSelect(this.select_source_rectype.get(0), value, false);
            this._onSelectRectype();
            this._refresh();
        }
    },    

    /* 
    * private function 
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

        /*if(top.HAPI4.currentUser.ugr_ID>0){
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
        
        //$(this.document).off(top.HAPI4.Event.ON_REC_SEARCHSTART+' '+top.HAPI4.Event.ON_REC_SELECT);
        if(parent && parent.document)        
            $(parent.document).off(top.HAPI4.Event.ON_REC_SEARCHRESULT);
        
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
        this.div_btn.remove();
    },
    
    
    //update relation and target selectors
    _onSelectRectype: function(event){
        
            var rt_ID = this.select_source_rectype.val(); //event.target.value;
            
            //find all relation types
            // a. pointer fields
            // b. relation types for relmarkers
            // c. all recortypes contrained in pointer and relmarkers fields
            
            var rectypes = top.HEURIST4.rectypes,
                details = rectypes.typedefs[rt_ID];

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
            for (dtyID in details){
                if(dtyID){
                    
                    if(details[dtyID][fi_type]=='resource' || details[dtyID][fi_type]=='relmarker'){
                        
                        var name = details[dtyID][fi_name];

                        if(!top.HEURIST4.util.isempty(name)){
                            
                            //find constraints
                            var constraints = details[dtyID][fi_rectypes];
                            constraints = ( typeof(constraints) === "string" && !top.HEURIST4.util.isempty(constraints) )
                                ? constraints.split(","):[];  // $.parseJSON(temp) 
                            if(!top.HEURIST4.util.isArray(constraints)){
                                constraints = [constraints];
                            }
                            if(constraints.length>0) arr_rectypes = arr_rectypes.concat(constraints);
                    
                            //
                            if(details[dtyID][fi_type]=='relmarker'){
                                
                                this._has_relation = '1';
                            
                                var temp = details[dtyID][fi_term_dis];
                                temp = ( typeof(temp) === "string" && !top.HEURIST4.util.isempty(temp) )
                                    ?  temp.split(",") :[];
                                if(temp.length>0) arr_terms_dis = arr_terms_dis.concat(temp);
                            
                                arr_fields.push({key:dtyID, title:name, terms:details[dtyID][fi_term], terms_dis:temp, rectypes:constraints});
                                
                            }else{
                                this._has_pointers = '1';
                                
                                arr_fields.push({key:dtyID, title:name, rectypes:constraints});
                            }

                        }
                    }
                    
                }
            }
            //find all reverse links (pointers and relation that point to selected rt_ID)
            var alldetails = rectypes.typedefs;
            for (rtyID in alldetails)
            if(rtyID){
                details = alldetails[rtyID].dtFields;
                for (dtyID in details){
                    if(dtyID){
                        
                        if(details[dtyID][fi_type]=='resource' || details[dtyID][fi_type]=='relmarker'){
                            
                            var name = details[dtyID][fi_name];

                            if(!top.HEURIST4.util.isempty(name)){
                                
                                //find constraints
                                var constraints = details[dtyID][fi_rectypes];
                                constraints = ( typeof(constraints) === "string" && !top.HEURIST4.util.isempty(constraints) )
                                    ? constraints.split(","):[];  // $.parseJSON(temp) 
                                if(!top.HEURIST4.util.isArray(constraints)){
                                    constraints = [constraints];
                                }
                                //verify that selected record type is in this constaint
                                if(constraints.length<1 || constraints.indexOf(rt_ID)<0) continue;
                                
                                arr_rectypes.push(rtyID);

                                var isnotfound = true;
                                var i, len = arr_fields.length;
                                for (i=0;i<len;i++){
                                    if(arr_fields[i].key == dtyID){
                                        arr_fields[i].rectypes.push(rtyID);
                                        isnotfound = false;
                                        break;
                                    }
                                }
                        
                                //
                                if(isnotfound){
                                    if(details[dtyID][fi_type]=='relmarker'){
                                        
                                        this._has_rev_relation = '1';
                                    
                                        var temp = details[dtyID][fi_term_dis];
                                        temp = ( typeof(temp) === "string" && !top.HEURIST4.util.isempty(temp) )
                                            ?  temp.split(",") :[];
                                        if(temp.length>0) arr_terms_dis = arr_terms_dis.concat(temp);
                                    
                                        arr_fields.push({key:dtyID, title:'is '+name, terms:details[dtyID][fi_term], terms_dis:temp, rectypes:[rtyID], isreverse:true });
                                        
                                    }else{
                                        this._has_rev_pointers = '1';
                                        
                                        arr_fields.push({key:dtyID, title:'is '+name, rectypes:[rtyID], isreverse:true });
                                    }
                                }

                            }
                        }
                        
                    }
                }
            }

            
            
            
            arr_rectypes = $.unique(arr_rectypes);
            //arr_terms_dis = $.unique(arr_terms_dis);
            
            this._arr_fields = arr_fields;
            this._arr_rectypes = arr_rectypes;
        
            //fill selectors        
            /*top.HEURIST4.util.createRectypeSelect(this.select_target_rectype.get(0), arr_rectypes, 'any');
            
            if(this.select_target_rectype.find("option").length>2){
                this.select_target_rectype.show();
            }else{
                this.select_target_rectype.hide();
            }
            */
            if(arr_fields.length>1){
               arr_fields.unshift({key:'', title:'any'});
               //this.select_fields.show();
            }else{
               //this.select_fields.hide();
            }
            
            top.HEURIST4.util.createSelector(this.select_fields.get(0), arr_fields);
            
            this._onSelectFieldtype(null);
            
            // selObj, datatype, termIDTree, headerTermIDsList, defaultTermID, topOptions, needArray
            //top.HEURIST4.util.createTermSelectExt(this.select_reltype.get(0), 'relation', arr_terms, arr_terms_dis, null, arr_fields, false);
        
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

            var dt_ID = event?event.target.value:'',
                is_not_relation = true,
                is_not_selected = true; //field is not defined/selected
            if(dt_ID!='') {
                
                var i, len = this._arr_fields.length;
                for (i=0;i<len;i++){
                    var arr_field = this._arr_fields[i];
                    if(arr_field.key == dt_ID){
                        if(arr_field.terms){
                            is_not_relation = false;
                            //this.label_3.show();
                            this.select_reltype.show();
                            top.HEURIST4.util.createTermSelectExt(this.select_reltype.get(0), 'relation', arr_field.terms, arr_field.terms_dis, null, 'any', false);
                        }
                        //reduced list of constraints
                        top.HEURIST4.util.createRectypeSelect(this.select_target_rectype.get(0), arr_field.rectypes, arr_field.rectypes.length>1?'any':null);
                        is_not_selected = false;
                        break;
                    }
                }
            }
            if(is_not_relation){
                //this.label_3.hide();
                this.select_reltype.hide();    
            }
            if(is_not_selected){
                //show all constraints
                top.HEURIST4.util.createRectypeSelect(this.select_target_rectype.get(0), this._arr_rectypes, this._arr_rectypes.length>1?'any':null);
            }
            
            /*if(this.select_target_rectype.find("option").length>2){
                this.label_4.show();
                this.select_target_rectype.show();
            }else{
                this.label_4.hide();
                this.select_target_rectype.hide();
            }*/
            
            this._generateQuery();
    },
    
    //
    // compose search query
    //
    _generateQuery: function(){
        
        this.options.query = '';
            
        //query is possible if there is at least on resourse or relmarker field    
        if(this._arr_fields.length>0) {
            
            var rt_target = '';
            
            if(!top.HEURIST4.util.isempty(this.select_target_rectype.val())){
                rt_target = this.select_target_rectype.val();
            }else{
                var opts = this.select_target_rectype.find("option");
                if(opts.length==2){
                    rt_target = $(opts[1]).attr('value');
                }
            }
            if(!top.HEURIST4.util.isempty(rt_target)) rt_target = 't:'+rt_target+' ';

            var rt_source = this.select_source_rectype.val();
            
            var dt_ID = this.select_fields.val();
            
            if(!top.HEURIST4.util.isempty(dt_ID)){ //particular field is selected
            
                var fld = this._findField(dt_ID);
            
                if(this.select_reltype.is(":visible")){
                    
                    var rel_type = this.select_reltype.val();
                    if(!top.HEURIST4.util.isempty(rel_type)) rel_type = '-'+rel_type;
                    
                    this.options.query = rt_target + 'related'+(fld && fld.isreverse?'_to:':'from:')+rt_source+rel_type;    
                }else{
                    this.options.query = rt_target + 'linked'+(fld && fld.isreverse?'_to:':'from:')+rt_source+'-'+dt_ID;    
                }
                
            }else{ //field is not selected - search for all relations and links
            
                this.options.query = rt_target + 'links:'+rt_source+'-'+this._has_relation+this._has_pointers+this._has_relation+this._has_rev_pointers;
            /* @todo
                if(this._has_relation){
                     this.options.queries.push(rt_target + 'relatedfrom:'+rt_source);
                }
                if(this._has_pointers){
                     this.options.queries.push(rt_target + 'linkedfrom:'+rt_source);
                }
                */
            }
            
        }
        
        this._refresh();
    },

    //select recordtype, field, reltype and ta    
    initContent: function(){
        
        if(this.options.content){
            
            //parse rule query string
            
            var query = this.options.content.query;
            var levels = this.options.content.levels;
            
            var rt_target, rt_source, field_id, relation_id;
             
            var parts = query.split(' ');
            if(trim(parts[0]).indexOf('t:')==0){
                rt_target = substr(trim(parts[0]),2);
                parts[0] = parts[1];
            }
            //get source and field
            //var 
            
            
        }
        
        
    },
    
    
    /*
    * debug search (not used) 
    _debugSearch: function(){
        
        var qsearch = '';
        if(this.additional_filter.val()!=''){
            qsearch = this.additional_filter.val();
        } else {
            //return;
        }
        
        if(!top.HEURIST4.util.isempty(this.options.query)){
            qsearch = this.options.query; //ies[0] + ' ' + qsearch;
        }
        //to do this.options.queries.join(' OR ')

        var request = {q: qsearch, w: 'a', source:this.element.attr('id') };  //f: this.options.searchdetails, 
        
        
        if(this.options.query_request && this.options.query_request.q){
            request.tq = this.options.query_request.q;
            if(this.options.query_request.s) request.ts = this.options.query_request.s;
            if(this.options.query_request.l) request.tl = this.options.query_request.l;
            if(this.options.query_request.o) request.to = this.options.query_request.o;
        }

        // perform search
        top.HAPI4.RecordMgr.search(request, $(this.document));
        
    },
    */
    
    /**
    * return array of queries
    * 
    * @returns {Array}
    */
    getQuery: function(){
        
        this._generateQuery();
        
        return this.options.query;
/*
        if(top.HEURIST4.util.isempty(this.options.queries)){
            return [];
        }else{
        
            var qsearch = '';
            if(this.additional_filter.val()!=''){
                qsearch = this.additional_filter.val();
                $.each(this.options.queries, function(index, value){
                    this.options.queries[index] = this.options.queries[index] + ' ' + qsearch;
                });
            }
            
            return this.options.queries;
        }
*/        
    },
    
    getRules: function(){

           // original rule array
           // rules:[ {query:query, levels:[]}, ....  ]           
          
           //refresh query
           this._generateQuery(); //this.queries(); 
           
           var rules = []; 
           
           //loop all dependent
           $.each( this.element.children('.rulebuilder') , function( index, value ) {
               
                var subrule = $(value).ruleBuilder("getRules");
                rules.push(subrule);  
                
           }); 
        
           return {query:this.options.query, levels:rules};
    }

});
