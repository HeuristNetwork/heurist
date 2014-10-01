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
        // callbacks
        queries:[]
    },
    
    _query_request: {}, //keep current query request
    _selection: null,     //current set of selected records
    _arr_fields:[],
    _arr_rectypes:[],
    _has_relation:false,
    _has_pointers:false,

    // the widget's constructor
    _create: function() {

        var that = this;
        
        
        //create list/combobox of source record types
        this.select_source_rectype = $( "<select>" )
                .addClass('menu-or-popup text ui-corner-all ui-widget-content')
                .appendTo(this.element);
                
        top.HEURIST4.util.createRectypeSelect(this.select_source_rectype.get(0), null, false);

        //create list/combobox of pointer/relmarker fields
        this.select_fields = $( "<select>" )
                .addClass('menu-or-popup text ui-corner-all ui-widget-content')
                .appendTo(this.element);
        
        //create list/combobox of relation types
        this.select_reltype = $( "<select>" )
                .addClass('menu-or-popup text ui-corner-all ui-widget-content')
                .appendTo(this.element).hide();
        
        //create list/combobox of target record types
        this.select_target_rectype = $( "<select>" )
                .addClass('menu-or-popup text ui-corner-all ui-widget-content')
                .appendTo(this.element);

        this.debug_label = $( "<label>" ).appendTo(this.element);
        this.debug_search = $( "<button>", {text:'Search'} ).appendTo(this.element);
        
        //event handlers
        this._on( this.select_source_rectype, { change: this._onSelectRectype });
        
        this._on( this.select_fields, { change: this._onSelectFieldtype });

        this._on( this.select_target_rectype, { change: this._generateQuery });
        this._on( this.select_reltype, { change: this._generateQuery });
        
        this._on( this.debug_search, { click: this._debugSearch });
        
        
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
        
        $(this.document).on(top.HAPI4.Event.ON_REC_SEARCHRESULT, function(e, data) {

            if(e.type == top.HAPI4.Event.ON_REC_SEARCHRESULT){
                  //that.option("recordset", data); //hRecordSet
            }
        });
        
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
        
        this.debug_label.html(this.options.queries.join(' OR '));
    },
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        
        //$(this.document).off(top.HAPI4.Event.ON_REC_SEARCHSTART+' '+top.HAPI4.Event.ON_REC_SELECT);
        $(this.document).off(top.HAPI4.Event.ON_REC_SEARCHRESULT);
        
        // remove generated elements
        this.select_source_rectype.remove();
        this.select_target_rectype.remove();
        this.select_fields.remove();
        this.select_reltype.remove();
        
        if(this.debug_label) this.debug_label.remove();
    },
    
    
    //update relation and target selectors
    _onSelectRectype: function(event){
        
            var rt_ID = event.target.value;
            
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
            
            this._has_relation = false;
            this._has_pointers = false;

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
                                
                                this._has_relation = true;
                            
                                var temp = details[dtyID][fi_term_dis];
                                temp = ( typeof(temp) === "string" && !top.HEURIST4.util.isempty(temp) )
                                    ?  temp.split(",") :[];
                                if(temp.length>0) arr_terms_dis = arr_terms_dis.concat(temp);
                            
                                arr_fields.push({key:dtyID, title:name, terms:details[dtyID][fi_term], terms_dis:temp, rectypes:constraints});
                                
                            }else{
                                this._has_pointers = true;
                                
                                arr_fields.push({key:dtyID, title:name, rectypes:constraints});
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
                            this.select_reltype.show();
                            top.HEURIST4.util.createTermSelectExt(this.select_reltype.get(0), 'relation', arr_field.terms, arr_field.terms_dis, null, 'any', false);
                        }
                        //reduced list of constraints
                        top.HEURIST4.util.createRectypeSelect(this.select_target_rectype.get(0), arr_field.rectypes, 'any');
                        is_not_selected = false;
                        break;
                    }
                }
            }
            if(is_not_relation){
                this.select_reltype.hide();    
            }
            if(is_not_selected){
                //show all constraints
                top.HEURIST4.util.createRectypeSelect(this.select_target_rectype.get(0), this._arr_rectypes, 'any');
            }
            
            if(this.select_target_rectype.find("option").length>2){
                this.select_target_rectype.show();
            }else{
                this.select_target_rectype.hide();
            }
            
            this._generateQuery();
    },
    
    _generateQuery: function(){
        
        this.options.queries = [];
            
        //query is possible if there is at least on resourse or relmarker field    
        if(this._arr_fields.length>0) {
            
            var rt_target = '';
            
            if(this.select_target_rectype.val()!=''){
                rt_target = this.select_target_rectype.val();
            }else{
                var opts = this.select_target_rectype.find("option");
                if(opts.length==2){
                    rt_target = $(opts[1]).attr('value');
                }
            }
            if(rt_target!='') rt_target = 't:'+rt_target+' ';

            var rt_source = this.select_source_rectype.val();
            
            var dt_ID = this.select_fields.val();
            
            if(dt_ID!=''){ //particular field is selected
            
                if(this.select_reltype.is(":visible")){
                    
                    var rel_type = this.select_reltype.val();
                    if(rel_type!='') rel_type = ':'+rel_type;
                    
                    this.options.queries.push(rt_target + 'relatedfrom:'+rt_source+rel_type);    
                }else{
                    this.options.queries.push(rt_target + 'linkedfrom:'+rt_source+':'+dt_ID);    
                }
                
            }else{
                if(this._has_relation){
                     this.options.queries.push(rt_target + 'relatedfrom:'+rt_source);
                }
                if(this._has_pointers){
                     this.options.queries.push(rt_target + 'linkedfrom:'+rt_source);
                }
            }
            
        }
        
        this._refresh();
    },
    
    _debugSearch: function(){
            //alert(this.options.queries.join(' OR '));
            var qsearch = this.options.queries.join(' OR ');
            var request = {q: qsearch, w: 'a', source:this.element.attr('id') };  //f: this.options.searchdetails, 

            //that._trigger( "onsearch"); //this widget event
            //that._trigger( "onresult", null, resdata ); //this widget event

            //perform search
            top.HAPI4.RecordMgr.search(request, $(this.document));
        
    },
    
});
