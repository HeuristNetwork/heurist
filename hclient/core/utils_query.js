/**
* HEURIST QUERY utility functions
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

if (!window.hWin.HEURIST4){
    window.hWin.HEURIST4 = {};
}
//init only once
if (!window.hWin.HEURIST4.query) 
{

window.hWin.HEURIST4.query = {

    //--- HEURIST QUERY ROUTINE -------
    
    //
    // from object to query string
    // JSON to URL
    // hQueryComposeURL
    composeHeuristQueryFromRequest: function(query_request, encode){
            var query_string = 'db=' + window.hWin.HAPI4.database;
            
            var mapdocument = window.hWin.HEURIST4.util.getUrlParameter('mapdocument', window.hWin.location.search);
            if(mapdocument>0){
                query_string = query_string + '&mapdocument='+mapdocument;
            }
        
            if(!window.hWin.HEURIST4.util.isnull(query_request)){

                query_string = query_string + '&w='+query_request.w;
                
                if(!window.hWin.HEURIST4.util.isempty(query_request.q)){
                    
                    if($.isArray(query_request.q) || $.isPlainObject(query_request.q)){
                        sq = JSON.stringify(query_request.q);
                    }else{
                        sq = query_request.q;
                    }
                    
                    if(encode){
                        sq = encodeURIComponent(sq);
                    }
                    
                    query_string = query_string + '&q=' + sq;
                }
                
                var rules = query_request.rules;
                if(!window.hWin.HEURIST4.util.isempty(rules)){
                    if($.isArray(query_request.rules) || $.isPlainObject(query_request.rules)){
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

    /* 
                JSON to URL
    params:{
    q,
    w or domain,
    rules,
    rulesonly
    notes
    viewmode
    }}
    
    */
    composeHeuristQuery2: function(params, encode){
        if(params){

            var query, rules = params.rules;
            var query_to_save = [];

            if(!(window.hWin.HEURIST4.util.isempty(params.w) || params.w=='all' || params.w=='a')){
                query_to_save.push('w='+params.w);
            }

            if(!window.hWin.HEURIST4.util.isempty(params.q)){

                if($.isArray(params.q) || $.isPlainObject(params.q)){
                    query = JSON.stringify(params.q);
                } else{
                    query = params.q;
                }
                query_to_save.push('q='+ (encode?encodeURIComponent(query):query) );
            }


            if(!window.hWin.HEURIST4.util.isempty(rules)){


                if($.isArray(params.rules) || $.isPlainObject(params.rules)){
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

    //
    // removes codes section and empty levels
    //
    cleanRules: function(rules){
        
        if(window.hWin.HEURIST4.util.isempty(rules)){
            return null;
        }
        
        rules = window.hWin.HEURIST4.util.isJSON(rules); //parses if string
        
        if(rules===false){
            return null;
        }
        
        for(var k=0; k<rules.length; k++){
            delete rules[k]['codes'];
            var rl = null;
            if(rules[k]['levels'] && rules[k]['levels'].length>0){
                var rl = window.hWin.HEURIST4.query.cleanRules(rules[k]['levels']);
            }
            if(rl==null){
                delete rules[k]['levels'];    
            }else{
                rules[k]['levels'] = rl;    
            }
            
        }
        
        return rules;        
    },

    //
    // both parameter should be JSON array or Object (rules are ignored)
    //
    mergeHeuristQuery: function(){
        
        var res_query = [];
        
        if(arguments.length>0){

            var idx=1, len = arguments.length;
            
            res_query = arguments[0];
            for (;idx<len;idx++){
                if(arguments[idx])
                   res_query = window.hWin.HEURIST4.query.mergeTwoHeuristQueries(res_query, arguments[idx]);
            }     
        }   
        
        return res_query;
    },
    
    mergeTwoHeuristQueries: function(query1, query2){

        //return object  {q:, rules:, plain:}
        function __prepareQuery(query){
            
            var query_a, rules = false, sPlain = false;
            var isJson = false;
            
            var query_a = window.hWin.HEURIST4.util.isJSON(query);
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
            var res = {q:query};    
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
        if(jQuery.type(query2) === "string"){
            var notJson = true;
            try{
                //query2 = JSON.parse(query2);
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

        var q1 = __prepareQuery(query1);
        var q2 = __prepareQuery(query2);

        if(q1['plain'] && q2['plain'])
        {
            return q1['plain']+' '+q2['plain'];
        }else{
            var query1 = q1['q'], query2 = q2['q'];
            
            if(window.hWin.HEURIST4.util.isnull(query1) || $.isEmptyObject(query1)){
                return query2;
            }
            if(window.hWin.HEURIST4.util.isnull(query2) || $.isEmptyObject(query2)){
                return query1;
            }
            if(!$.isArray(query1)){
                query1 = [query1];
            }
            if(!$.isArray(query2)){
                query2 = [query2];
            }
        
            return query1.concat(query2)    
        }
        
    },
    
    //
    // converts query string to object
    // URL to JSON
    // hQueryParseURL
    //
    parseHeuristQuery: function(qsearch)
    {
        
        var type = -1;
        
        var query = '', domain = null, rules = '', rulesonly = 0, notes = '', primary_rt = null, viewmode = '', db='';
        if(qsearch){
            
            var res = {};
            
            if(typeof qsearch === 'string' && qsearch.indexOf('?')==0){ //this is query in form of URL params 
                domain  = window.hWin.HEURIST4.util.getUrlParameter('w', qsearch);
                rules   = window.hWin.HEURIST4.util.getUrlParameter('rules', qsearch);
                rulesonly = window.hWin.HEURIST4.util.getUrlParameter('rulesonly', qsearch);
                notes   = window.hWin.HEURIST4.util.getUrlParameter('notes', qsearch);
                viewmode = window.hWin.HEURIST4.util.getUrlParameter('viewmode', qsearch);
                query = window.hWin.HEURIST4.util.getUrlParameter('q', qsearch);
                db = window.hWin.HEURIST4.util.getUrlParameter('db', qsearch);
                
                res.ui_notes = notes;
                
            }else{ //it may be aquery in form of json
            
                var r = window.hWin.HEURIST4.util.isJSON(qsearch);
                if(r!==false){
                    
                    if(window.hWin.HEURIST4.util.isArray(r.rectypes)){
                        r.type = 3; //'faceted';
                        r.w = (r.domain=='b' || r.domain=='bookmark')?'bookmark':'all';
                        r.domain = r.w;
                        return r;
                    }
                    
                    if(r.rules){
                        rules = r.rules;
                    }
                    if(r.q){
                        query = r.q;
                    }else if(r.type!=3 && !r.rules) {
                        query = r;
                    }
                    
                    if(r.db){
                        db = r.db;
                    }
                    domain = r.w?r.w:'all';
                    primary_rt = r.primary_rt; 
                    rulesonly = r.rulesonly;
                    
                    //localized name and note
                    $(Object.keys(r)).each(function(i,key){
                        if(key.indexOf('ui_name')==0 || key.indexOf('ui_notes')==0){
                            res[key] = r[key];
                        }
                    });
                }else{ //usual string
                    query = qsearch;
                }
            }
            
        }
        
        if(window.hWin.HEURIST4.util.isempty(query)){
            type = window.hWin.HEURIST4.util.isempty(rules) ?-1:2; //empty, rulesonly 
        }else {
            type = window.hWin.HEURIST4.util.isempty(rules) ?0:1; //searchonly, both
        }
        
        domain = (domain=='b' || domain=='bookmark')?'bookmark':'all';
        
        res = $.extend(res, {q:query, w:domain, domain:domain, rules:rules, rulesonly:rulesonly, 
                            primary_rt:primary_rt, viewmode:viewmode, type:type});    
        
        if(!window.hWin.HEURIST4.util.isempty(db)){
            res.db = db;
        }
        
        return res;
    },

    //
    // get combination query and rules as json array for map query layer
    // Returns current search request as stringified JSON
    //    
    hQueryStringify: function(request, query_only){
        
        var res = {};
        
        if(window.hWin.HEURIST4.util.isempty(request.q)){
            return '';
        }else {
            var r = window.hWin.HEURIST4.util.isJSON(request.q);
            if(r!==false){
                if(r.facets) return ''; //faceted search not allowed for map queries
                res['q'] = r; //JSON.stringify(r);
            }else{
                res['q'] = request.q;
            }
        }
        
        if(query_only===true){
            res = res['q'];  
        }else{ 
        
            if(!window.hWin.HEURIST4.util.isempty(request.rules)){
                //cleanRules?
                var r = window.hWin.HEURIST4.util.isJSON(request.rules);
                if(r!==false){
                    if(r.facets) return ''; //faceted search not allowed for map queries
                    res['rules'] = r; //JSON.stringify(r);
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
        
        return JSON.stringify(res);;
    },
    
    //
    //
    //
    hQueryCopyPopup: function(request, pos_element){
        
        var res = window.hWin.HEURIST4.query.hQueryStringify(request);
        
        var buttons = {};
        buttons[window.hWin.HR('Copy')]  = function() {
            
            var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
            var target = $dlg.find('#dlg-prompt-value')[0];
            target.focus();
            target.setSelectionRange(0, target.value.length);
            var succeed;
            try {
                succeed = document.execCommand("copy");
                
                $dlg.dialog( "close" );
            } catch(e) {
                succeed = false;
                alert('Not supported by browser');
            }                            
            
        }; 
        buttons[window.hWin.HR('Close')]  = function() {
            var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
            $dlg.dialog( "close" );
        };
        
        var opts = {width:450, buttons:buttons, default_palette_class: 'ui-heurist-explore'}
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
            '<label>Edit and copy the string and paste into the Mappable Query filter field</label>'
            + '<textarea id="dlg-prompt-value" class="text ui-corner-all" '
            + ' style="min-width: 200px; margin-left:0.2em;margin-top:10px;" rows="3" cols="70">'
            + res
            +'</textarea>',null,'Copy query string', opts);
        
    },

}//end utils_query
