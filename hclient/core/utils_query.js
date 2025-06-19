/**
 * @file utils_query.js
 * @brief Provides utility functions for constructing, parsing, and manipulating Heurist search queries.
 * @fileOverview This file defines the `HEURIST4.query` namespace. Its functions facilitate
 * the conversion between Heurist query objects (often JSON-based) and URL query strings.
 * Key functionalities include:
 * - Composing URL query strings from request objects (`composeHeuristQueryFromRequest`, `composeHeuristQuery2`).
 * - Parsing URL query strings or JSON query representations into a standardized query object (`parseHeuristQuery`).
 * - Merging multiple query objects (`mergeHeuristQuery`, `mergeTwoHeuristQueries`).
 * - Cleaning and simplifying query rule structures (`cleanRules`).
 * - Stringifying query objects for specific uses, like map queries (`hQueryStringify`).
 * - Generating human-readable plain text descriptions of queries (`stringQueryToPlainText`, `jsonQueryToPlainText`).
 * - Creating facet query structures (`createFacetQuery`).
 * - Helper for displaying a "copy query string" popup (`hQueryCopyPopup`).
 * These utilities are central to how search and filtering are handled throughout the Heurist client.
 * @package Heurist academic knowledge management system
 * @subpackage hclient\core
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 4.0
 */

if (!window.hWin.HEURIST4){
    window.hWin.HEURIST4 = {};
}
//init only once
if (!window.hWin.HEURIST4.query) 
{

/**
 * @namespace HEURIST4.query
 * @memberof HEURIST4
 * @description Provides a collection of utility functions for constructing, parsing, merging,
 * and interpreting Heurist search queries. These functions handle the conversion between
 * internal query representations (often JSON objects) and URL query strings,
 * as well as generating human-readable descriptions of queries.
 */
window.hWin.HEURIST4.query = {

    //--- HEURIST QUERY ROUTINES ------- (This comment can be removed if all routines below are documented)
    
    /**
     * Composes a Heurist URL query string from a query request object.
     * Includes database, mapdocument (if present in current URL), 'w' (domain), 'q' (query),
     * 'rules', and 'rulesonly' parameters.
     *
     * @param {Object} [query_request] - The query request object.
     * @param {string} [query_request.w] - The domain or scope of the query (e.g., 'all', 'bookmark', record type ID).
     * @param {string|Object|Array} [query_request.q] - The main query part. Can be a string, object, or array.
     *                                                  If object/array, it will be JSON stringified.
     * @param {string|Object|Array} [query_request.rules] - The query rules. If object/array, it will be JSON stringified.
     * @param {boolean|number} [query_request.rulesonly] - If true or 1, indicates only rules should be applied.
     *                                                     If 2, also indicates a specific rules-only mode.
     * @param {boolean} [encode=false] - If true, URI encodes the 'q' and 'rules' parameter values.
     * @returns {string} The composed URL query string (e.g., "db=mydb&w=all&q=...").
     */
    composeHeuristQueryFromRequest: function(query_request, encode){
            let query_string = 'db=' + window.hWin.HAPI4.database;
            
            let mapdocument = window.hWin.HEURIST4.util.getUrlParameter('mapdocument', window.hWin.location.search);
            if(mapdocument>0){
                query_string = query_string + '&mapdocument='+mapdocument;
            }
        
            if(!window.hWin.HEURIST4.util.isnull(query_request)){

                if(!window.hWin.HEURIST4.util.isempty(query_request.w)){
                    query_string = query_string + '&w='+query_request.w;
                }
                
                if(!window.hWin.HEURIST4.util.isempty(query_request.q)){
                    
                    let sq;

                    if(Array.isArray(query_request.q) || $.isPlainObject(query_request.q)){
                        sq = JSON.stringify(query_request.q);
                    }else{
                        sq = query_request.q;
                    }
                    
                    if(encode){
                        sq = encodeURIComponent(sq);
                    }
                    
                    query_string = query_string + '&q=' + sq;
                }
                
                let rules = query_request.rules;
                if(!window.hWin.HEURIST4.util.isempty(rules)){
                    if(Array.isArray(query_request.rules) || $.isPlainObject(query_request.rules)){
                        rules = JSON.stringify(query_request.rules);
                    }
                    //@todo simplify rules array - rempove redundant info
                    query_string = query_string + '&rules=' + 
                        (encode?encodeURIComponent(rules):rules);
                        
                    if(query_request.rulesonly==true) query_request.rulesonly=1;    
                    if(query_request.rulesonly>0){
                        query_string = query_string + '&rulesonly=' + query_request.rulesonly;
                    }
                }
                
                        
            }else{
                query_string = query_string + '&w=all';
            }        
            return query_string;        
    },

    /**
     * Composes a Heurist URL query string from a parameters object (alternative version).
     * This version focuses on specific parameters like 'q', 'w' (or 'domain'), 'rules', 'rulesonly',
     * 'notes', and 'viewmode'.
     *
     * @param {Object} [params] - The parameters object.
     * @param {string|Object|Array} [params.q] - The main query part. Stringified if object/array.
     * @param {string} [params.w] - The domain/scope. Alias: `params.domain`.
     * @param {string} [params.domain] - Alternative for `params.w`.
     * @param {string|Object|Array} [params.rules] - Query rules. Stringified if object/array.
     * @param {boolean|number} [params.rulesonly] - Rules-only mode flag.
     * @param {string} [params.notes] - Notes associated with the query.
     * @param {string} [params.viewmode] - View mode for the query results.
     * @param {boolean} [encode=false] - If true, URI encodes 'q', 'rules', and 'notes' values.
     * @returns {string} The composed URL query string, starting with '?' (e.g., "?w=all&q=...").
     *                   Returns "?" if no parameters are provided.
     */
    composeHeuristQuery2: function(params, encode){
        if(params){

            let query, rules = params.rules;
            let query_to_save = [];

            if(!(window.hWin.HEURIST4.util.isempty(params.w) || params.w=='all' || params.w=='a')){
                query_to_save.push('w='+params.w);
            }

            if(!window.hWin.HEURIST4.util.isempty(params.q)){

                if(Array.isArray(params.q) || $.isPlainObject(params.q)){
                    query = JSON.stringify(params.q);
                } else{
                    query = params.q;
                }
                query_to_save.push('q='+ (encode?encodeURIComponent(query):query) );
            }


            if(!window.hWin.HEURIST4.util.isempty(rules)){


                if(Array.isArray(params.rules) || $.isPlainObject(params.rules)){
                    rules = JSON.stringify(params.rules);
                } else{
                    rules = params.rules;
                }
                query_to_save.push('rules='+ (encode?encodeURIComponent(rules):rules));
                
                if(params.rulesonly==true) params.rulesonly=1;    
                if(params.rulesonly>0){
                    query_to_save.push('rulesonly=' + params.rulesonly);
                }
            }

            if(!window.hWin.HEURIST4.util.isempty(params.notes)){
                query_to_save.push('notes='+ (encode?encodeURIComponent(params.notes):params.notes));
            }

            if(!window.hWin.HEURIST4.util.isempty(params.viewmode)){
                query_to_save.push('viewmode='+ params.viewmode);
            }

            return '?'+query_to_save.join('&');

        }else
            return '?';
    },

    /**
     * Cleans a Heurist query rules structure by recursively removing 'codes' properties
     * and empty 'levels' arrays from each rule object.
     *
     * @param {Object[]|string} rules - An array of rule objects or a JSON string representing it.
     *                                  Each rule object can have 'codes' and 'levels' properties.
     * @returns {Object[]|null} The cleaned array of rule objects, or `null` if the input
     *                          is empty or invalid JSON.
     */
    cleanRules: function(rules){
        
        if(window.hWin.HEURIST4.util.isempty(rules)){
            return null;
        }
        
        rules = window.hWin.HEURIST4.util.isJSON(rules); //parses if string
        
        if(rules===false){
            return null;
        }
        
        for(let k=0; k<rules.length; k++){
            delete rules[k]['codes'];
            let rl = null;
            if(rules[k]['levels'] && rules[k]['levels'].length>0){
                rl = window.hWin.HEURIST4.query.cleanRules(rules[k]['levels']);
            }
            if(rl==null){
                delete rules[k]['levels'];    
            }else{
                rules[k]['levels'] = rl;    
            }
            
        }
        
        return rules;        
    },

    /**
     * Merges multiple Heurist query objects or query strings into a single query representation.
     * It iteratively calls `mergeTwoHeuristQueries` for each argument.
     *
     * @param {...(Object|string)} arguments - A variable number of Heurist query objects or query strings to merge.
     * @returns {Object[]|string} The merged query. If all inputs were plain strings and successfully merged as such,
     *                            a concatenated string is returned. Otherwise, an array of query objects is returned.
     *                            Returns an empty array if no arguments are provided.
     */
    mergeHeuristQuery: function(){
        
        let res_query = [];
        
        if(arguments.length>0){

            let idx=1, len = arguments.length;
            
            res_query = arguments[0];
            for (;idx<len;idx++){
                if(arguments[idx])
                   res_query = window.hWin.HEURIST4.query.mergeTwoHeuristQueries(res_query, arguments[idx]);
            }     
        }   
        
        return res_query;
    },
    
    /**
     * Merges two Heurist queries (which can be query objects, JSON strings, or plain text query strings).
     * - If both queries are determined to be plain text strings (not parsable as JSON or JSON with a 'q' property),
     *   they are concatenated with a space.
     * - Otherwise, queries are normalized (parsed if JSON strings, wrapped if plain text) into query objects.
     * - Rules (`rules` property) from the input objects are preserved if present but are not merged;
     *   the rules from the first query object (`query1`) would typically take precedence if the result is treated as a single query structure.
     * - If one query is empty or null, the other is returned.
     * - If both are valid query objects, their 'q' parts (which become arrays) are concatenated.
     *
     * The internal helper `__prepareQuery` is used to normalize input queries.
     *
     * @param {Object|string} query1 - The first query (JSON object, JSON string, or plain text string).
     * @param {Object|string} query2 - The second query (JSON object, JSON string, or plain text string).
     * @returns {Object[]|string|Object} The merged query.
     *                                   - Returns a concatenated string if both inputs were plain text.
     *                                   - Returns an array of query objects if inputs were structured queries.
     *                                   - Returns one of the inputs if the other was empty/null.
     *                                   - May return a single query object if one input was empty.
     */
    mergeTwoHeuristQueries: function(query1, query2){

        // Internal helper function: __prepareQuery
        // Normalizes a query input (string or object) into a consistent object structure:
        // {q: query_object, rules: rules_object|false, plain: plain_text_string|false}
        function __prepareQuery(query){
            
            let rules = false, sPlain = false;
            let isJson = false;
            
            let query_a = window.hWin.HEURIST4.util.isJSON(query);
            if( query_a ){
                query = query_a; //converted to json    
                
                if(query_a['q']){
                    query = query_a['q'];
                    if(query_a['rules']){
                        rules = query_a['rules'];    
                    }
                    query_a = window.hWin.HEURIST4.util.isJSON(query);
                    if( query_a ){
                        query = query_a;
                        isJson = true;
                    }
                }else{
                    isJson = true;    
                }
            }
                    
            if(!isJson){
                if(window.hWin.HEURIST4.util.isempty(query)){
                    query = {};    
                }else{
                    sPlain = query;
                    query = {plain: encodeURIComponent(query)}; //query1.split('"').join('\\\"')};    
                }
            }
            let res = {q:query};    
            if(rules){
                res['rules'] = rules;
            }
            if(sPlain){
                res['plain'] = sPlain;
            }else{
                res['plain'] = false;
            }
            
            return res;
        }

/*        
        var sPlain1 = false, sPlain2 = false;
        if(typeof query2 === "string"){
            var notJson = true;
            try{
               
                var query2a = window.hWin.HEURIST4.util.isJSON(query2);
                if( query2a ){
                    if(query2a['q']){
                        query2 = query2a['q'];    
                        if(query2a['rules']){
                            rules2 = query2a['rules'];    
                        }
                        if(window.hWin.HEURIST4.util.isJSON(query2)){
                            notJson = false;
                        }
                    }else{
                        query2 = query2a;
                        notJson = false;
                    }
                }
            }catch (ex2){
            }
            if(notJson){
                if(window.hWin.HEURIST4.util.isempty(query2)){
                    query2 = {};    
                }else{
                    sPlain2 = query2;
                    query2 = {plain: encodeURIComponent(query2)}; //query2.split('"').join('\\\"')};    
                }
            }
        }
*/        

        let q1 = __prepareQuery(query1);
        let q2 = __prepareQuery(query2);

        if(q1['plain'] && q2['plain'])
        {
            return q1['plain']+' '+q2['plain'];
        }else{
            query1 = q1['q'];
            query2 = q2['q'];
            
            if(window.hWin.HEURIST4.util.isnull(query1) || $.isEmptyObject(query1)){
                return query2;
            }
            if(window.hWin.HEURIST4.util.isnull(query2) || $.isEmptyObject(query2)){
                return query1;
            }
            if(!Array.isArray(query1)){
                query1 = [query1];
            }
            if(!Array.isArray(query2)){
                query2 = [query2];
            }
        
            return query1.concat(query2)    
        }
        
    },
    
    /**
     * Parses a Heurist query, which can be provided as a URL query string (e.g., from `window.location.search`)
     * or as a JSON string/object, into a standardized query object.
     * The resulting object includes properties like `q` (main query), `w` (domain/scope, also aliased as `domain`),
     * `rules`, `rulesonly`, `notes`, `primary_rt`, `viewmode`, `db`, and `type` (indicating query complexity).
     * It also preserves UI-related properties like `ui_name` and `ui_notes`.
     * Handles faceted search structures by identifying `rectypes` array and setting type to 3.
     *
     * @param {string|Object} qsearch - The query input.
     *                                  - If a string starting with '?', it's treated as a URL query string.
     *                                  - Otherwise, it's treated as a JSON string or a direct query object.
     * @returns {Object} A standardized query object.
     *                   Properties:
     *                   - `q`: The core query part (string or parsed JSON).
     *                   - `w`: Domain/scope ('all', 'bookmark', etc.).
     *                   - `domain`: Alias for `w`.
     *                   - `rules`: Parsed rules string (or original if not JSON).
     *                   - `rulesonly`: Parsed rulesonly flag.
     *                   - `notes`: Notes from the query.
     *                   - `primary_rt`: Primary record type from the query.
     *                   - `viewmode`: Viewmode from the query.
     *                   - `db`: Database name from the query.
     *                   - `type`: An integer indicating the query type/complexity:
     *                             - -1: Empty query.
     *                             -  0: Search query only.
     *                             -  1: Search query with rules.
     *                             -  2: Rules only.
     *                             -  3: Faceted search (if `rectypes` array found in JSON input).
     *                   - `ui_name...`: Any properties from input starting with `ui_name`.
     *                   - `ui_notes...`: Any properties from input starting with `ui_notes`.
     *                   Returns a basic structure with `type: -1` if `qsearch` is empty.
     */
    parseHeuristQuery: function(qsearch)
    {

        let res = {};
        let type = -1;
        
        let query = '', domain = null, rules = '', rulesonly = 0, notes = '', primary_rt = null, viewmode = '', db='';
        if(qsearch){
            
            if(typeof qsearch === 'string' && qsearch.indexOf('?')==0){ //this is query in form of URL params 
                domain  = window.hWin.HEURIST4.util.getUrlParameter('w', qsearch);
                rules   = window.hWin.HEURIST4.util.getUrlParameter('rules', qsearch);
                rulesonly = window.hWin.HEURIST4.util.getUrlParameter('rulesonly', qsearch);
                notes   = window.hWin.HEURIST4.util.getUrlParameter('notes', qsearch);
                viewmode = window.hWin.HEURIST4.util.getUrlParameter('viewmode', qsearch);
                query = window.hWin.HEURIST4.util.getUrlParameter('q', qsearch);
                db = window.hWin.HEURIST4.util.getUrlParameter('db', qsearch);
                
                res.ui_notes = notes; // Preserve notes if coming from URL params directly
                
            }else{ //it may be a query in form of json
            
                let r = window.hWin.HEURIST4.util.isJSON(qsearch);
                if(r!==false){ // Successfully parsed as JSON or was already an object
                    
                    if(Array.isArray(r.rectypes)){ // Special handling for faceted search structure
                        r.type = 3; // faceted
                        r.w = (r.domain=='b' || r.domain=='bookmark')?'bookmark':'all';
                        r.domain = r.w;
                        return r; // Return early as this structure is specific
                    }
                    
                    if(r.rules){
                        rules = r.rules;
                    }
                    if(r.q){
                        query = r.q;
                    }else if(r.type!=3 && !r.rules) { // If not faceted and no rules, the object itself might be the query
                        query = r;
                    }
                    
                    if(r.db){
                        db = r.db;
                    }
                    domain = r.w?r.w:'all';
                    primary_rt = r.primary_rt; 
                    rulesonly = r.rulesonly;
                    viewmode = r.viewmode; // Capture viewmode from JSON object
                    
                    //localized name and note
                    $(Object.keys(r)).each(function(i,key){
                        if(key.indexOf('ui_name')==0 || key.indexOf('ui_notes')==0){
                            res[key] = r[key];
                        }
                    });
                }else{ //usual string (not URL params, not JSON)
                    query = qsearch;
                }
            }
            
        }
        
        if(window.hWin.HEURIST4.util.isempty(query)){
            type = window.hWin.HEURIST4.util.isempty(rules) ?-1:2; //empty (-1), rulesonly (2)
        }else {
            type = window.hWin.HEURIST4.util.isempty(rules) ?0:1; //searchonly (0), both (1)
        }
        
        domain = (domain=='b' || domain=='bookmark')?'bookmark':'all'; // Normalize domain
        
        res = $.extend(res, {q:query, w:domain, domain:domain, rules:rules, rulesonly:rulesonly, 
                            primary_rt:primary_rt, viewmode:viewmode, type:type});    
        
        if(!window.hWin.HEURIST4.util.isempty(db)){
            res.db = db;
        }
        
        return res;
    },

    /**
     * Stringifies a Heurist query request object into a JSON string.
     * This is often used for preparing queries for map layers or saved searches.
     * It can optionally include only the 'q' part or the full request (q, rules, w, rulesonly, db, svs).
     * Faceted searches (identified by `r.facets`) will result in an empty string as they are not allowed for this stringification purpose.
     *
     * @param {Object} request - The query request object.
     * @param {string|Object} [request.q] - The main query part.
     * @param {string|Object} [request.rules] - Query rules.
     * @param {string} [request.w] - Domain/scope.
     * @param {boolean|number} [request.rulesonly] - Rules-only flag.
     * @param {string} [request.database] - Database name (also checks `request.db`).
     * @param {string} [request.db] - Alternative for database name.
     * @param {number} [request.svs] - Saved search ID. If present and `query_only` is false, only `svs` is included.
     * @param {boolean} [query_only=false] - If true, only the 'q' part of the request is stringified.
     *                                      If false, the entire relevant request object is stringified.
     * @returns {string} A JSON string representation of the query, or an empty string if the input
     *                   is unsuitable (e.g., empty query, faceted search for map query).
     */
    hQueryStringify: function(request, query_only){
        
        let res = {};
        
        if(window.hWin.HEURIST4.util.isPositiveInt(request.svs) && !query_only){
            res['svs'] = request.svs;
        }else if(window.hWin.HEURIST4.util.isempty(request.q)){
            return '';
        }else{
            let r = window.hWin.HEURIST4.util.isJSON(request.q);
            if(r!==false){
                if(r.facets) return ''; //faceted search not allowed for map queries
                res['q'] = r;
            }else{
                res['q'] = request.q;
            }
        }
        
        if(query_only===true){
            res = res['q'];
        }else{ 
        
            if(!window.hWin.HEURIST4.util.isempty(request.rules)){
                //cleanRules? // Consider if cleanRules should be applied here.
                let r = window.hWin.HEURIST4.util.isJSON(request.rules);
                if(r!==false){
                    if(r.facets) return ''; //faceted search not allowed for map queries
                    res['rules'] = r;
                }else{
                    res['rules'] = request.rules;
                }
            }

            if(!window.hWin.HEURIST4.util.isempty(request.w) && !(request.w=='a' || request.w=='all')){
                    res['w'] = request.w;
            }
            
            if(request.rulesonly==1 || request.rulesonly==true){
                    res['rulesonly'] = 1;
            }else if(request.rulesonly==2){
                    res['rulesonly'] = 2;
            }

            if(request.database){
                    res['db'] = request.database;
            }else if(request.db){
                    res['db'] = request.db;
            }
        }
        
        return JSON.stringify(res);
    },
    
    /**
     * Displays a popup dialog that shows the JSON stringified version of a query request
     * (obtained via `hQueryStringify`) in a textarea, allowing the user to copy it.
     * Typically used for copying queries for map layers or other advanced uses.
     *
     * @param {Object} request - The Heurist query request object to be stringified and displayed.
     * @param {jQuery|Object} [pos_element] - Element or jQuery UI position object to position the dialog relative to.
     *                                        If an object, expects `my`, `at`, `of` properties.
     *                                        If a jQuery element, positions dialog to its 'right bottom'.
     * @returns {void}
     */
    hQueryCopyPopup: function(request, pos_element){
        
        let res = window.hWin.HEURIST4.query.hQueryStringify(request);
        
        let buttons = {};
        buttons[window.hWin.HR('Copy')]  = function() {
            
            let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
            let target = $dlg.find('#dlg-prompt-value');

            window.hWin.HEURIST4.util.copyStringToClipboard(target.text());
        }; 
        buttons[window.hWin.HR('Close')]  = function() {
            let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
            $dlg.dialog( "close" );
        };
        
        let opts = {width:450, buttons:buttons, default_palette_class: 'ui-heurist-explore'}
        if(pos_element){
            if(pos_element.my){
                opts.my = pos_element.my;
                opts.at = pos_element.at;
                opts.of = pos_element.of;
            }else{
                opts.my = 'left top';
                opts.at = 'right bottom';
                opts.of = pos_element
            }
        }        
        
        window.hWin.HEURIST4.msg.showPrompt(
            '<label for="dlg-prompt-value">Edit and copy the string and paste into the Mappable Query filter field</label>'
            + '<textarea id="dlg-prompt-value" class="text ui-corner-all" '
            + ' style="min-width: 200px; margin-left:0.2em;margin-top:10px;" rows="3" cols="70">'
            + res
            +'</textarea>',null,'Copy query string', opts);
        
    },

    /**
     * Creates a structured facet query object from a colon-separated code string.
     * The code string typically represents a path through record types and fields
     * (e.g., "rtid1:dtid1:rtid2:dtid2:final_dtid").
     * It determines the target record type ID (`rtid`), the final field ID (`id`),
     * and the query path code (`code`).
     * If `need_query` is true, it also constructs a nested query structure (`facet`)
     * for searching facet values, handling link and relation directions.
     *
     * @param {string} code - A colon-separated string representing the facet path.
     *                        e.g., "10:lf123:20:f456" where 10, 20 are rt_IDs, lf123, f456 are dty_IDs.
     *                        Link types (lt, lf, rt, rf) are handled specially.
     * @param {boolean} [need_query=false] - If true, generates the nested 'facet' query structure
     *                                       for retrieving facet values.
     * @param {boolean} [respect_relation_direction=false] - If true, distinguishes between 'related_to' and 'relatedfrom'
     *                                                       for relation fields when `need_query` is true.
     *                                                       Otherwise, uses 'related'.
     * @returns {Object} A facet query object with properties:
     *                   - `id`: The final detail type ID (field ID) in the path.
     *                   - `rtid`: The record type ID associated with the final field.
     *                   - `code`: The input code string excluding the final field ID.
     *                   - `facet` (optional): If `need_query` was true, this contains the nested query structure.
     *                   - `relation_direction` (optional): If `need_query` was true and the path involves relations/links,
     *                                                    this indicates the direction of the first link/relation from the target.
     */
    createFacetQuery: function(code, need_query, respect_relation_direction){

        let result = {};
        
        code = code.split(':');

        let dtid = code[code.length-1];
        let linktype = dtid.substr(0,2);
        if(linktype=='lt' || linktype=='lf' || linktype=='rt' || linktype=='rf'){
            //unconstrained link, assume title field of target type
            code.push('0');         // Placeholder for rt_ID of the target type of the unconstrained link (usually resolved later)
            code.push('title');     // Assume 'title' field for unconstrained links by default
        }

        result['id']   = code[code.length-1]; //last dty_ID
        result['rtid'] = code[code.length-2]; // rt_ID for the field 'id'
        
        //creates lists of queries to search facet values
        if(need_query===true){  //not direct input

            // Internal recursive helper to build the 'facet' query part
            function __crt( idx, depth ){
                let res = null;
                if(idx>0){  //this is relation or link

                    res = [];

                    let pref = '';
                    let qp = {};

                    if(code[idx]>0){ //if rt_ID is 0 - unconstrained link target type
                        qp['t'] = code[idx];
                        res.push(qp);
                    }

                    //for facet queries direction will be reverted for links/relations
                    let fld = code[idx-1]; //link/relation field dty_ID
                    if(fld.indexOf('lf')==0){ // linked_from field
                        pref = 'linked_to';    
                    }else if(fld.indexOf('lt')==0){ // linked_to field
                        pref = 'linkedfrom';    
                    }else if(fld.indexOf('rf')==0){ // related_from field
                        pref = respect_relation_direction?'related_to':'related';    
                    }else if(fld.indexOf('rt')==0){ // related_to field
                        pref = respect_relation_direction?'relatedfrom':'related';
                    }
                     
                    if(depth==0 && pref !== ''){ // Store direction of the first segment if it's a link/relation
                        result['relation_direction'] = pref;
                    }

                    qp = {};
                    qp[pref+':'+fld.substr(2)] = __crt(idx-2, depth+1);    
                    res.push(qp);
                }else{ //this is simple field (end of recursion, or direct field of initial type)
                    res = '$IDS'; // Placeholder for the actual IDs to be searched
                }
                return res;
            }
            result['facet'] = __crt( code.length-2, 0 );
        }

        code.pop(); // Remove the final field ID from the code path
        result['code'] = code.join(':');  // qcode without last dty_ID (path to the field's record type)
        
        return result;
    },

    /**
     * Converts a plain text Heurist query string into a more human-readable HTML formatted string.
     * It first tries to parse subqueries enclosed in parentheses recursively.
     * Then, it attempts to convert field-prefixed parts (e.g., "title:something") into a JSON-like
     * structure which is then processed by `jsonQueryToPlainText`.
     * If the input is already JSON or invalid, it directly calls `jsonQueryToPlainText`.
     *
     * @param {string} query - The plain text query string.
     * @returns {string} An HTML string representing the human-readable version of the query.
     *                   Returns an empty string if the query is empty or invalid.
     */
    stringQueryToPlainText: function(query){

        const getSubquery = /\(([^[)])*\)/g; // Regex to find parenthesized subqueries
        const removeParenthesis = /(?:^[\s(]+)|(?:[\s)]+$)/g; // Regex to remove leading/trailing spaces and parentheses

        query = typeof query === 'string' ? query.replaceAll(/\s+/g, ' ').trim() : query; // remove double spacing, and leading + trailing spaces
        let is_invalid = typeof query !== 'string' || query === '' || /^[^\w"]/.exec(query) !== null; // Basic check for invalid query start

        let json_query_from_string; // To store potentially parsed JSON from string
        if(typeof query === 'string'){
            json_query_from_string = window.hWin.HEURIST4.util.isJSON(query);
        }

        if(json_query_from_string || is_invalid && typeof query !== 'object'){ // If already JSON or invalid (and not an object)
            return is_invalid ? '' : window.hWin.HEURIST4.query.jsonQueryToPlainText(json_query_from_string || query);
        }

        // Recursively process subqueries
        let subqueries = [...query.matchAll(getSubquery)];
        for(const subqueryMatch of subqueries){ // Iterate using of for match objects
            let subqueryContent = subqueryMatch[0].replace(removeParenthesis, '');
            let processedSubquery = window.hWin.HEURIST4.query.stringQueryToPlainText(subqueryContent);
            query = query.replace(subqueryMatch[0], `(${processedSubquery})`); // Keep parentheses for structure if needed by jsonQueryToPlainText
        }

        let parts = [...query.matchAll(/(?:".*?"|[^"\s]+)+(?=\s*|\s*$)/g)]; // extract terms, respecting quotes

        // Convert plain query to json query structure for jsonQueryToPlainText
        let json_query_parts = [];
        for(const part of parts){
            if(part?.length > 0 && part[0].indexOf(':') > 0){ // Field-prefixed term
                let pieces = part[0].split(':');
                let key;
                let searchValue;
                if (pieces.length > 1) {
                    key = pieces.shift(); // The part before the first colon is the key
                    searchValue = pieces.join(':').replaceAll(/(^"|"$)/g, ''); // Join the rest, remove surrounding quotes
                    json_query_parts.push({ [key]: searchValue });
                } else { // Not a valid field:value, treat as plain text if necessary or part of a larger structure
                    json_query_parts.push({ plain: part[0].replaceAll(/(^"|"$)/g, '') });
                }
            } else if (part?.length > 0) { // Plain text term
                 json_query_parts.push({ plain: part[0].replaceAll(/(^"|"$)/g, '') });
            }
        }

        if(json_query_parts.length == 0 && typeof query === 'string' && query.trim() !== ''){ // If no structured parts but query is not empty
             return query; // Return the original query string as is, likely a single plain text search.
        } else if(json_query_parts.length == 0){
            return '';
        }

        return window.hWin.HEURIST4.query.jsonQueryToPlainText(json_query_parts);
    },

    /**
     * Converts a Heurist JSON query object (or an array of query objects) into a human-readable HTML formatted string.
     * It iterates through query parts, identifies record types, fields, conditions, and logical operators (AND/OR),
     * and constructs a descriptive string. It uses database schema information ($Db) for labels.
     * Handles various query keys like 't' (type), 'ids', 'title', field IDs (e.g., 'f123'),
     * 'any', 'all', 'plain', 'svs' (saved search), 'db', 'sortby', link/relation keys.
     *
     * @param {Object|Array|string} query - The Heurist JSON query object, an array of such objects,
     *                                      or a string that can be parsed by `stringQueryToPlainText`.
     * @param {boolean} [is_sub_query=false] - True if this is processing a sub-query (e.g., within 'any' or 'all'),
     *                                         which affects formatting (no "Searching all records" prefix).
     * @param {boolean} [use_or=false] - If true and processing an array of conditions, joins them with "OR" instead of "AND".
     * @returns {string} An HTML string representing the human-readable version of the query.
     *                   Returns an empty string if the query is empty or invalid.
     *                   Includes a legend for special characters like '==', '≠≠', '<>', '><' if used.
     *                   May include a warning for invalid multi-record-type queries.
     */
    jsonQueryToPlainText: function(query, is_sub_query = false, use_or = false){

        const commaListRegex = /^\d+(?:,\d+)*$/;
        let plain_text = '';
        // Attempt to parse if query is a string and might be JSON
        let queryObj = typeof query === 'string' ? window.hWin.HEURIST4.util.isJSON(query) : query;

        if (typeof query === 'string' && queryObj === false) { // Was a string but not valid JSON
             // If it's a sub_query, it might be a plain search term from a more complex string query
            return is_sub_query ? query : window.hWin.HEURIST4.query.stringQueryToPlainText(query);
        }
        if (queryObj === false || window.hWin.HEURIST4.util.isempty(queryObj)) {
            return plain_text; // Return empty if not valid JSON or is empty
        }

        query = queryObj; // Use the parsed object or original if it was an object
        query = Array.isArray(query) ? query : Object.entries(query).map((part) => { return {[part[0]]: part[1]}; });
        let rty_ID = null; // Stores current record type context
        let deconstructed = []; // Stores parts of the human-readable query
        let sortby = []; // Stores sort criteria

        // --- Internal Helper Functions ---
        // (These are not JSDoc'd as they are internal to jsonQueryToPlainText)

        function handleRectype(rty_IDs_input){ // Renamed to avoid conflict with outer rty_ID
            let rty_IDs = rty_IDs_input;
            if(window.hWin.HEURIST4.util.isPositiveInt(rty_IDs) || ( typeof rty_IDs === 'string' && commaListRegex.exec(rty_IDs) )){
                rty_IDs = window.hWin.HEURIST4.util.isPositiveInt(rty_IDs) ? [String(rty_IDs)] : rty_IDs.split(',').filter((id) => window.hWin.HEURIST4.util.isPositiveInt(id));
            }else if (typeof rty_IDs === 'string' && !window.hWin.HEURIST4.util.isPositiveInt(rty_IDs)){ // Potentially a record type name
                 rty_IDs = [rty_IDs]; // Keep as string for $Db.rtyByName
            }else if (!Array.isArray(rty_IDs)){
                 rty_IDs = [String(rty_IDs)];
            }


            let labels = [];
            let valid_ids = [];
            for(const id_or_name of rty_IDs){
                let label = id_or_name;
                let current_id = id_or_name;
                if (window.hWin.HEURIST4.util.isPositiveInt(id_or_name)) {
                    label = $Db.rty(id_or_name, 'rty_Name') ?? id_or_name;
                } else { // Assume it's a name
                    current_id = $Db.rtyByName(id_or_name); // Get ID by name
                    if (current_id) label = id_or_name; // Use name if ID found
                    else current_id = id_or_name; // Fallback if name not found
                }
                labels.push(label);
                if (window.hWin.HEURIST4.util.isPositiveInt(current_id)) valid_ids.push(current_id);
            }

            rty_ID = valid_ids.join(','); // Update outer rty_ID context with actual IDs
            deconstructed.unshift(`Find: <strong>${window.hWin.HEURIST4.util.stripTags(labels.join(' | '))}</strong><br>`);
        }

        function handleDefault(key_input, field_input, value_input){
            let key = key_input;
            let field = field_input;
            let value = value_input;
            let type = '';
            let conditional = '';

            if(field.indexOf(':') > 0){ // e.g. "fldType:123"
                field = field.split(':');
                field = field[field.length-1]; // take the ID part
            }else if(key != 'f' && key.startsWith('f')){ // e.g. "f123"
                let match = key.match(/\d+/); // Extract numeric part
                field = match === null ? 'Any field' : key.substring(1);
            }else if(window.hWin.HEURIST4.util.isPositiveInt(key)){ // If key itself is a field ID (e.g. {123: "value"})
                field = key; // The key is the field ID
                value = value_input; // The value is the search term for this field
            }


            if(window.hWin.HEURIST4.util.isPositiveInt(field)){
                type = $Db.dty(field, 'dty_Type');
                let field_name = $Db.rst(rty_ID, field, 'rst_DisplayName') ?? $Db.dty(field, 'dty_Name');
                field = field_name ?? `Field ID ${field}`; // Use name or fallback to ID
            }

            if(key === 'r' && (field === field_input || field === 'Any field')){ // Relation type field handling (no specific field ID in key)
                value = typeof value !== 'string' ? String(value) : value;
                let condPrefix = value.startsWith('-') ? 'not' : '';
                let valToParse = value.startsWith('-') ? value.substring(1) : value;

                if(window.hWin.HEURIST4.util.isPositiveInt(valToParse) || commaListRegex.exec(valToParse)){
                    let term_ids = valToParse.split(',');
                    term_ids = term_ids.filter((id) => window.hWin.HEURIST4.util.isPositiveInt(id));
                    let term_labels = term_ids.map((id) => $Db.trm(id, 'trm_Label'));
                    valToParse = term_labels.filter((trm) => !window.hWin.HEURIST4.util.isempty(trm)).join(' | ');
                }
                conditional = `<em>Relationship type</em> that is ${condPrefix} a match or is ${condPrefix} a child of "${valToParse}"`;
            }else if(key === 'r' || key === 'relf' || key === 'rf'){ // Relation field with specific field ID
                field = `Relationship <em>${field}</em>`;
            }

            // Handle link/relation subqueries
            if(key.startsWith('link') || key.startsWith('related')){
                let linking = key.includes('link') ? 'Linked' : 'Related';
                let direction = key.includes('from') ? 'from' : (key.includes('to') ? 'to' : '');

                if(window.hWin.HEURIST4.util.isPositiveInt(value) || ( typeof value === 'string' && commaListRegex.exec(value) )){
                    let recIDs = window.hWin.HEURIST4.util.isPositiveInt(value) ? value : value.split(',').filter((id) => window.hWin.HEURIST4.util.isPositiveInt(id)).join(',');
                    conditional = `<br>Search for ${linking} Records ${direction} record ID(s): ${recIDs}`;
                }else{
                    let sub_query = window.hWin.HEURIST4.query.jsonQueryToPlainText(value, true) ?? 'Missing sub query';
                    conditional = `<br>Search ${linking} Records ${direction} <em>${field}</em>:<br><div style="padding:5px;">${sub_query}</div>`;
                }
                field = ''; // Field name is incorporated into the conditional string
            }
            return [field, conditional, type];
        }

        function handleAnyAll(type, value_input){
            let is_any = type === 'any';
            let sub_text = window.hWin.HEURIST4.query.jsonQueryToPlainText(value_input, true, is_any) ?? 'Missing sub query';
            deconstructed.push(`${is_any ? 'Meets one of the following filters:<div style="margin-left:5px;">' : 'Meets all of the following filters:<div style="margin-left:5px;">'}${sub_text}</div>`);
        }
        // --- End Internal Helper Functions ---

        let idx = query.findIndex((obj) => Object.hasOwn(obj, 't') || Object.hasOwn(obj, 'type'));
        if(idx >= 0){ // Process 't' or 'type' first if it exists
            let typeObj = query.splice(idx, 1)[0];
            handleRectype(typeObj.t || typeObj.type);
        }

        let multi_rectype = false; // Flag for multiple 't'/'type' definitions
        let usingSavedSearch = false;

        for(const part of query){ // Iterate using of for direct objects
            let field_key = Object.keys(part)[0]; // Should only be one key per object in the array
            let value = part[field_key];
            let cond = '';

            let key_parts = field_key.split(':');
            let main_key = key_parts.shift(); // The primary part of the key (e.g., 'f', 'title', 'linkto')
            let field_specifier = key_parts.join(':'); // The rest, could be field ID or sub-type for links

            let field_display_name = ''; // Human-readable field name
            let field_type = 'freetext'; // Default field type

            switch(main_key){
                case 'ids': case 'id': field_display_name = 'Record IDs'; break;
                case 'title': field_display_name = 'Record Titles'; break;
                case 'url': case 'u': field_display_name = 'Record URLs'; break;
                case 'notes': case 'n': field_display_name = 'Record Notes'; break;
                case 'added': field_display_name = 'Record Creation date'; field_type = 'date'; break;
                case 'date': case 'modified': field_display_name = 'Record Last Modification'; field_type = 'date'; break;
                case 'addedby': field_display_name = 'Record Creator'; break;
                case 'owner': case 'workgroup': case 'wg': field_display_name = 'Record Owner'; break;
                case 'tag': case 'keyword': case 'kwd': field_display_name = 'Record Tags'; break;
                case 'visibility': case 'access': field_display_name = 'Record Accessibility'; break;
                case 'user':
                    value = String(value).split(',');
                    value = value.length == 0 || value[0] == '' ? ' User' : `: "${value.filter((usr_ID) => window.hWin.HEURIST4.util.isPositiveInt(usr_ID)).join(',')}"`;
                    cond = `Records Bookmarked by${value}`;
                    break;
                case 'before': case 'after':
                    cond = `Records last modified ${main_key} ${value}`; field_type = 'date';
                    break;
                case 'sortby':
                    let sort_val = typeof value !== 'string' ? '' : window.hWin.HEURIST4.query.sortbyValue(value, rty_ID);
                    if (sort_val) sortby.push(sort_val);
                    continue; // Skip adding to deconstructed
                case 'exists':
                     field_display_name = $Db.dty(field_specifier, 'dty_Name') || `Field ID ${field_specifier}`;
                    cond = `<em>${field_display_name}</em> ${String(value).toLowerCase() === 'false' || value === 0 ? 'does not exist' : 'exists'}`;
                    break;
                case 't': case 'type': // Should have been handled already if primary, otherwise it's a multi-type error
                    if(deconstructed.length > 0 || rty_ID !== null) multi_rectype = true; else handleRectype(value);
                    continue;
                case 'all': case 'any':
                    handleAnyAll(main_key, value);
                    continue;
                case 'plain':
                    cond = window.hWin.HEURIST4.query.stringQueryToPlainText(value); // Recursively parse plain string
                    break;
                case 'svs':
                    cond = `Using saved search #${value}`; usingSavedSearch = true;
                    break;
                case 'db':
                    cond = `Using the database(s): ${value}`;
                    break;
                case 'q': continue; // 'q' usually wraps the main query array, skip direct processing of 'q' key

                default: // Assumed to be a field-specific query (e.g. f:123, fldType:id, or direct ID if key is numeric)
                    let default_parts = handleDefault(main_key, field_specifier || main_key, value);
                    field_display_name = default_parts[0];
                    cond = default_parts[1];
                    field_type = default_parts[2];
                    break;
            }

            if(window.hWin.HEURIST4.util.isempty(field_display_name) && window.hWin.HEURIST4.util.isempty(cond)){
                continue;
            }

            // If cond wasn't fully formed by switch/handleDefault, extract it now
            cond = window.hWin.HEURIST4.util.isempty(cond) ? window.hWin.HEURIST4.query.extractCondition(value, field_type) : cond;
            if(window.hWin.HEURIST4.util.isempty(cond)){
                continue;
            }

            deconstructed.push(cond.replace('__FIELD__', field_display_name));
        }

        if(rty_ID && deconstructed.length > 0){ // If a record type was set and there are conditions
             // The first element of deconstructed is already the "Find: RecordType" string due to unshift in handleRectype
        } else if(!is_sub_query && !usingSavedSearch && !rty_ID){ // No record type defined, not a subquery, not a saved search
            plain_text = `Searching all records${deconstructed.length > 0 || sortby.length > 0 ? '<div style="padding: 5px 10px;">' : ''}`;
        } else if (is_sub_query || (usingSavedSearch && deconstructed.length > 0)) { // For subqueries or saved searches with other conditions
            plain_text = '<div style="padding: 5px 10px;">';
        }


        plain_text += deconstructed.join(`<br>${use_or ? 'OR ' : 'AND '}`);
        plain_text += sortby.length == 0 ? '' : `${deconstructed.length > 0 ? '<br>' : ''}<strong>SORT BY</strong> ${sortby.join(', ')}`;
        plain_text += (deconstructed.length > 0 || sortby.length > 0) && (rty_ID || !is_sub_query && !usingSavedSearch || (usingSavedSearch && deconstructed.length > 0)) ? '</div>' : '';


        plain_text = window.hWin.HEURIST4.util.stripTags(plain_text, 'br, em, b, strong, u, i, div'); // Allow specific HTML tags

        if(!is_sub_query){ // Add legend and warnings only for top-level query display
            let legend = [];
            if(plain_text.includes(' == ')) legend.push('== Exact match');
            if(plain_text.includes(' ≠≠ ')) legend.push('≠≠ Not exact match');
            if(plain_text.includes(' <> ')) legend.push('<> overlap/between');
            if(plain_text.includes(' >< ')) legend.push('>< falls between');
            plain_text += legend.length > 0 ? `<br>[Key: ${legend.join(', ')}]` : '';
    
            if(multi_rectype){
                plain_text += `<br><hr><br><strong>Warning</strong>:<br>
                    You appear to be attempting to filter by multiple individual record types; as it's impossible for a record to be multiple types at the same time, this will always provide an empty result.<br>
                    Please remove the un-necessary record type filterings or combine them into one as a comma separated list (e.g. {"t":"10,11,12"}).`;
            }
        }
        return plain_text;
    },

    /**
     * Extracts and formats a human-readable condition string from a query field's value and type.
     * It handles various operators/prefixes in the value string (e.g., '=', '-', '@', '%', '<', '>')
     * and formats them into phrases like "== value", "≠≠ value", "contains any of: value", "starts with: value",
     * "is value", "value1 <> value2" (for ranges).
     * For 'enum' type and numeric values, it attempts to resolve term labels from the database.
     *
     * @param {string|number} value - The value part of a query condition.
     * @param {string} type - The data type of the field (e.g., 'enum', 'date', 'freetext').
     * @returns {string} A formatted string representing the condition (e.g., "<em>__FIELD__</em> == \"example\"").
     *                   The placeholder "__FIELD__" is intended to be replaced by the actual field name later.
     *                   Returns an empty string if the value cannot be meaningfully formatted.
     */
    extractCondition: function(value, type){

        const commaListRegex = /^\d+(?:,\d+)*$/;
        let res = '';

        if(typeof value !== 'string' && typeof value !== 'number'){
            return res;
        }
        value = String(value); // Ensure value is a string for string methods

        if(type === 'enum' && (window.hWin.HEURIST4.util.isPositiveInt(value) || commaListRegex.exec(value) )){
            let term_ids = value.split(',');
            term_ids = term_ids.filter((id) => window.hWin.HEURIST4.util.isPositiveInt(id));

            let term_labels = term_ids.map((id) => {
                let trm_Code = $Db.trm(id, 'trm_Code') !== '' ? ` [code ${$Db.trm(id, 'trm_Code')}]` : '';
                return `${$Db.trm(id, 'trm_Label')}${trm_Code}`;
            });
            value = term_labels.filter((trm) => !window.hWin.HEURIST4.util.isempty(trm)).join(' | ');
        }

        let val = '';
        if(value === 'NULL'){
            res = `Missing`;
        }else if(window.hWin.HEURIST4.util.isempty(value)){
            res = `Exists`;
        }else if(value.startsWith('=') || value.startsWith('-')){
            val = value.substring(1);
            res = `${value.startsWith('-') ? '≠≠' : '=='} "${val}"`;
        }else if(value.startsWith('@++') || value.startsWith('@--')){
            val = value.substring(3);
            res = `contains ${value.startsWith('@++') ? 'all' : 'none'} of: ${val}`;
        }else if(value.startsWith('@')){
            val = value.substring(1);
            res = `contains any of: ${val}`;
        }else if(value.startsWith('%') && value.endsWith('%') && value.length > 1){ // Contains (improved)
             val = value.substring(1, value.length -1);
             res = `contains "${val}"`;
        }else if(value.startsWith('%')){ // Ends with
            val = value.substring(1);
            res = `ends with "${val}"`;
        }else if(value.endsWith('%')){ // Starts with
            val = value.slice(0, -1);
            res = `starts with "${val}"`;
        }else if(value.startsWith('<') || value.startsWith('>')){
            let compare = value.slice(0, value.includes('=') ? 2 : 1); // Handles <=, >=, <, >
            val = value.substring(compare.length);
            res = `${compare} ${val}`;
        }else if(value.includes('<>') || value.includes('><')){
            let compare = value.includes('<>') ? '<>' : '><';
            let parts = value.split(compare);
            if (parts.length === 2) {
                 res = `${parts[0].trim()} ${compare} ${parts[1].trim()}`;
            } else { // Fallback for malformed range
                 res = `is "${value}"`;
            }
        }else if(type == 'date'){
            let parts = value.split('/');
            if(parts.length == 2){ // Assumed date range d1/d2
                res = `${parts[0]} <> ${parts[1]}`;
            }else{
                res = `is "${value}"`; // Single date
            }
        }else{
            res = `contains "${value}"`; // Default for freetext and other types
        }

        return `<em>__FIELD__</em> ${res}`;
    },

    /**
     * Converts a sort key (and optional direction prefix '-') into a human-readable string.
     * Handles common sort keys like 'id', 'title', 'modified', 'added', 'record type',
     * 'rating', 'popularity', and field-specific sorts (e.g., 'f:123' or 'field:fieldname').
     *
     * @param {string} value - The sort key string, possibly prefixed with '-' for descending order.
     * @param {string|number} [rty_ID] - The record type ID, used to resolve display names for field-specific sorts.
     * @returns {string} A human-readable string describing the sort criterion (e.g., "Record Title ascending order").
     *                   Returns an empty string or a partially resolved string if the sort key is unknown or field name cannot be resolved.
     */
    sortbyValue: function(value, rty_ID){

        let res = '';
        const is_negate = value[0] === '-';
        value = is_negate ? value.substring(1) : value;

        switch(value){
            case 'id':
                res = 'Record ID';
                break;
            case 'url':
                res = 'Record URL';
                break;
            case 'm':
            case 'modified':
                res = 'Last Modified';
                break;
            case 'a':
            case 'added':
                res = 'Created Date';
                break;
            case 't':
            case 'title':
                res = 'Record Title';
                break;
            case 'rt':
            case 'record type':
                res = 'Record Type';
                break;
            case 'r':
            case 'rating':
                res = 'Your Ratings';
                break;
            case 'p':
            case 'popularity':
                res = 'Your Bookmarks';
                break;
            default:
                if(value.startsWith('f:') || value.startsWith('field:')){

                    let parts = value.split(':');
                    parts.shift();
                    value = parts.join(':');

                    res = value;
                    if(window.hWin.HEURIST4.util.isPositiveInt(value)){
                        if(rty_ID>0){
                            res = $Db.rst(rty_ID, value, 'rst_DisplayName');    
                        }else{
                            res = null;
                        }
                        res = res ?? $Db.dty(value, 'dty_Name');
                    }
                }
                break;
        }

        res += ` ${is_negate ? 'descending' : 'ascending'} order`;

        return res;
    }
    
}
}
