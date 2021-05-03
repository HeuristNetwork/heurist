/**
*  Various utility functions
*
* @todo - split to generic utilities and UI utilities
* @todo - split utilities for hapi and load them dynamically from hapi
*
* @see editing_input.js
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

if (!window.hWin.HEURIST4){
    window.hWin.HEURIST4 = {};
}
//init only once
if (!window.hWin.HEURIST4.util) 
{

window.hWin.HEURIST4.util = {


    isnull: function(obj){
        return ( (typeof obj==="undefined") || (obj===null));
    },

    isempty: function(obj){
        if (window.hWin.HEURIST4.util.isnull(obj)){
            return true;
        }else if(window.hWin.HEURIST4.util.isArray(obj)){
            return obj.length<1;
        }else{
            return (obj==="") || (obj==="null");
        }

    },

    istrue: function(val, def){
        
        def = window.hWin.HEURIST4.util.isnull(def)?true:def;
        
        if(window.hWin.HEURIST4.util.isnull(val)){
            return def;
        }else if(val===true){
            return true;
        }else if(typeof obj==='string'){
            val =  val.toLowerCase();
            return val=='yes' || val=='y'  || val=='true' || val=='t' || val=='1';
        }else{
            return val==1;
        }
    },
    
    byteLength: function(str) {
      // returns the byte length of an utf8 string
      var s = str.length;
      for (var i=str.length-1; i>=0; i--) {
        var code = str.charCodeAt(i);
        if (code > 0x7f && code <= 0x7ff) s++;
        else if (code > 0x7ff && code <= 0xffff) s+=2;
        if (code >= 0xDC00 && code <= 0xDFFF) i--; //trail surrogate
      }
      return s;
    },    
    
    //
    //remove ian's trailing &gt; used to designate a pointer field and replace consistently with >>
    //
    trim_IanGt: function(name){
            if(name){
                name = name.trim();
                if(name.substr(name.length-1,1)=='>') name = name.substr(0,name.length-2);
                return name;
            }else{
                return '';
            }
    },
    

    isNumber: function (n) {
        //return typeof n === 'number' && isFinite(n);
        return !isNaN(parseFloat(n)) && isFinite(n);
    },
    

    //
    //
    //
    checkRegexp:function ( o, regexp ) {
        if ( !( regexp.test( o.val() ) ) ) {
            o.addClass( "ui-state-error" );
            return false;
        } else {
            return true;
        }
    },
    
    //
    // From jquery.validate.js (by joern), contributed by Scott Gonzalez: http://projects.scottsplayground.com/email_address_validation/
    //    
    checkEmail:function ( email_input ) {
        
        return window.hWin.HEURIST4.util.checkRegexp( email_input, /^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i );
        
    },    

    cloneJSON:function (data){
        try{
            return JSON.parse(JSON.stringify(data));
        }catch (ex2){
            console.log('cannot clone json array '+data);
            return [];
        }
    },

    // get current font size in em
    em: function(input) {
        var emSize = parseFloat($("body").css("font-size"));
        return (emSize * input);
    },

    // get current font size in pixels
    px: function(input, ele) {
        
        if(!ele) {
            ele = $("body");   
            fs = ele.css('font-size')
        }
        else {
            fs = ele.css('font-size')
            ele = ele.parent();
        }
        /*
        var rele = $('<span>').html(input).css('font-size', fs).appendTo(ele);
        var res = rele.width();
        rele.remove();
        return res;
        */
        var emSize = parseFloat(fs);
        return (input.length * emSize);
    },

    //
    // enable or disable element
    //
    setDisabled: function(element, mode){
      if(element){
          if(!$.isArray(element)){
                element = [element];
          }
          $.each(element, function(idx, ele){
              ele = $(ele);
              
              //if(mode !== (ele.prop('disabled')=='disabled')){
              
              if($(ele).hSelect("instance")!=undefined){              
                  
                  if (mode) {
                    $(ele).hSelect( "disable" );
                  }else{
                    $(ele).hSelect( "enable" );    
                  }
                  
                  
              }else{
                  if (mode) {
                        ele.prop('disabled', 'disabled');
                        ele.addClass('ui-state-disabled');
                  }else{
                        ele.removeProp('disabled');
                        ele.removeClass('ui-state-disabled ui-button-disabled');
                  }
              }
              
              //}
          });
      }
    },
    
    isIE: function () {
        var myNav = navigator.userAgent.toLowerCase();
        return (myNav.indexOf('msie') != -1) ? parseInt(myNav.split('msie')[1]) : false;
    },
    
    //
    //
    //
    checkProtocolSupport: function(url){
        
        if (window.hWin.HEURIST4.util.isIE()) { //This bastard always needs special treatment
        
            if (typeof (navigator.msLaunchUri) == typeof (Function)) {
                
                navigator.msLaunchUri(url,
                    function () { /* Success */ },
                    function () { /* Failure */ window.hWin.HEURIST4.msg.showMsgErr('Not supported') });
                return;
            }
                
            try {
                var flash = new ActiveXObject("Plugin.mailto");
            } catch (e) {
                //not installed
            }
        } else { //firefox,chrome,opera
            //navigator.plugins.refresh(true);
            var mimeTypes = navigator.mimeTypes;
            var mime = navigator.mimeTypes['application/x-mailto'];
            if(mime) {
                //installed
            } else {
                //not installed
                 window.hWin.HEURIST4.msg.showMsgErr('Not supported');
            }
        }      
      
        
    },

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
                    
                    if($.isArray(query_request.q)){
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
                var rl = window.hWin.HEURIST4.util.cleanRules(rules[k]['levels']);
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
    // both parameter should be JSON array or Object
    //
    mergeHeuristQuery: function(){
        
        var res_query = [];
        
        if(arguments.length>0){

            var idx=1, len = arguments.length;
            
            res_query = arguments[0];
            for (;idx<len;idx++){
                if(arguments[idx])
                   res_query = window.hWin.HEURIST4.util.mergeTwoHeuristQueries(res_query, arguments[idx]);
            }     
        }   
        
        return res_query;
    },

    mergeTwoHeuristQueries: function(query1, query2){
        
        if(jQuery.type(query1) === "string"){
            var notJson = true;
            try{
                //query1 = JSON.parse(query1);
                var query1a = window.hWin.HEURIST4.util.isJSON(query1);
                if( query1a ){
                    query1 = query1a;
                    notJson = false;
                }
            }catch (ex2){
            }
            if(notJson){
                if(window.hWin.HEURIST4.util.isempty(query1)){
                    query1 = {};    
                }else{
                    query1 = {plain: encodeURIComponent(query1)}; //query1.split('"').join('\\\"')};    
                }
            }
        }
        
        if(jQuery.type(query2) === "string"){
            var notJson = true;
            try{
                //query2 = JSON.parse(query2);
                var query2a = window.hWin.HEURIST4.util.isJSON(query2);
                if( query2a ){
                    query2 = query2a;
                    notJson = false;
                }
            }catch (ex2){
            }
            if(notJson){
                if(window.hWin.HEURIST4.util.isempty(query2)){
                    query2 = {};    
                }else{
                    query2 = {plain: encodeURIComponent(query2)}; //query2.split('"').join('\\\"')};    
                }
            }
        }
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
    },
    
    //
    // converts query string to object
    // URL to JSON
    // hQueryParseURL
    parseHeuristQuery: function(qsearch)
    {
        
        var type = -1;
        
        var domain = null, rules = '', rulesonly = 0, notes = '', primary_rt = null, viewmode = '', db='';
        if(qsearch){
            
            
            if(typeof qsearch === 'string' && qsearch.indexOf('?')==0){ //this is query in form of URL params 
                domain  = window.hWin.HEURIST4.util.getUrlParameter('w', qsearch);
                rules   = window.hWin.HEURIST4.util.getUrlParameter('rules', qsearch);
                rulesonly = window.hWin.HEURIST4.util.getUrlParameter('rulesonly', qsearch);
                notes   = window.hWin.HEURIST4.util.getUrlParameter('notes', qsearch);
                viewmode = window.hWin.HEURIST4.util.getUrlParameter('viewmode', qsearch);
                qsearch = window.hWin.HEURIST4.util.getUrlParameter('q', qsearch);
                db = window.hWin.HEURIST4.util.getUrlParameter('db', qsearch);
                
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
                        qsearch = r.q;
                    }
                    if(r.db){
                        db = r.db;
                    }
                    domain = r.w?r.w:'all';
                    primary_rt = r.primary_rt; 
                    rulesonly = r.rulesonly;
                }
            }
            
        }
        
        if(window.hWin.HEURIST4.util.isempty(qsearch)){
            type = window.hWin.HEURIST4.util.isempty(rules) ?-1:2; //empty, rulesonly 
        }else {
            type = window.hWin.HEURIST4.util.isempty(rules) ?0:1; //searchonly, both
        }
        
        domain = (domain=='b' || domain=='bookmark')?'bookmark':'all';
        
        var res = {q:qsearch, w:domain, domain:domain, rules:rules, rulesonly:rulesonly, notes:notes, 
                        primary_rt:primary_rt, viewmode:viewmode, type:type};    
        
        if(!window.hWin.HEURIST4.util.isempty(db)){
            res.db = db;
        }
        
        return res;
    },

    //
    // returns json or false
    //
    isJSON: function(value){
        
            try {
                if(typeof value === 'string'){
                    value = value.replace(/[\n\r]+/g, '');
                    value = $.parseJSON(value);    
                }
                if($.isArray(value) || $.isPlainObject(value)){
                    return value;
                }
            }
            catch (err) {
            } 
            
            return false;       
    },
    
    //
    // get combination query and rules as json array for map query layer
    // Returns current search request as stringified JSON
    //    
    hQueryStringify: function(request){
        
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
        
        
        return JSON.stringify(res);;
    },
    
    //
    //
    //
    hQueryCopyPopup: function(request, pos_element){
        
        var res = window.hWin.HEURIST4.util.hQueryStringify(request);
        
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
    
    //
    // Extract parameter from given URL or from current window.location.search
    //
    getUrlParameter: function getUrlParameter(name, query){

        if(!query){
            query = window.location.search;
        }

        var regexS = "[\\?&]"+name+"=([^&#]*)";
        var regex = new RegExp( regexS );
        var results = regex.exec( query );

        if( results == null ) {
            return null;
        } else {
            try{
                return decodeURIComponent(results[1]);
            }catch (ex){
                return results[1];
                //console.log('cant decode '+name+'='+results[1]);
            }
        }
    },

    isArrayNotEmpty: function (a){
        return (window.hWin.HEURIST4.util.isArray(a) && a.length>0);
    },

    isArray: function (a)
    {
        return $.isArray(a); //Object.prototype.toString.apply(a) === '[object Array]';
    },
    
    isGeoJSON: function(a, allowempty){
        
        if(allowempty && $.isArray(a) && a.length==0){
            return true;   
        }else if($.isPlainObject(a)){
            return (a['type']=='Feature' || a['type']=='FeatureCollection' || a['type']=='GeometryCollection');
        }else{
            return (window.hWin.HEURIST4.util.isArrayNotEmpty(a) && (a[0]['type']=='Feature' || a[0]['type']=='FeatureCollection'));
        }
    },

    /*
    htmlEscape: function(s) {
        return s?s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/'/g, "&#39;").replace(/"/g, "&#34;"):'';
    },
    */
    
    //
    // see php htmlspecialchars
    //
    htmlEscape: function (text) {
      var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',  //&#34
        "'": '&#039;'
      };
      
      if(window.hWin.HEURIST4.util.isempty(text)){
          return '';
      }else{
          return (''+text).replace(/[&<>"']/g, function(m) { return map[m]; })
      }
    },  
    
    //
    // whitelist e.g. "p, img"
    //
    stripTags: function(text, whitelist){
        
        if(whitelist===false){ 
            
            return window.hWin.HEURIST4.util.htmlEscape(text);             
        }else{
            
            var link = document.createElement("span");
            link.style.display = "none";
            link.innerHTML = text;
            document.body.appendChild(link);
            
            //find('*')
            var eles = $(link).find('*');
            if(!window.hWin.HEURIST4.util.isempty(whitelist)){
                eles = eles.not(whitelist);
            }
            
            eles.each(function() {
                var content = $(this).contents();
                $(this).replaceWith(content);
            });   
            
            if(window.hWin.HEURIST4.util.isempty(whitelist)){
                text =  $(link).text().trim();    
            }else{
                text =  $(link).html();    
            }
            
            document.body.removeChild(link); 
            link = null;
            
            return text;
        }
        
    },
    
    //
    //
    //
    stripScripts: function(s) {

        //jquery way        
        return $('<div>').append($.parseHTML(s)).html();
        
        //vanilla way
        /*
        var div = document.createElement('div');
        div.innerHTML = s;
        var scripts = div.getElementsByTagName('script');
        var i = scripts.length;
        while (i--) {
            scripts[i].parentNode.removeChild(scripts[i]);
        }
        return div.innerHTML;
        */
    },

    //
    //
    //
    isObject: function (a)
    {
        return Object.prototype.toString.apply(a) === '[object Object]';
    },

    stopEvent: function(e){
        if (!e) e = window.event;

        if (e) {
            e.cancelBubble = true;
            if (e.stopPropagation) e.stopPropagation();
            e.preventDefault();
        }
        return e;
    },

    //
    // we have to reduce the usage to minimum. Need to implement method in hapi via central controller
    // this method is used 
    // 1) for call H3 scripts in H4 code
    // 2) in case hapi is not inited 
    // 3) for third-party web services
    //
    sendRequest: function(url, request, caller, callback, dataType, timeout){
        
        var action = '';
        
        if(request){
        
            if(!request.db && window.hWin && window.hWin.HAPI4){
                request.db = window.hWin.HAPI4.database;
            }
            
            var action = url.substring(url.lastIndexOf('/')+1);
            if(action.indexOf('.php')>0) {
                action = action.substring(0,action.indexOf('.php'));   
            }
        }
        
        var request_code = {script:action, action:''};
        
        //note jQuery ajax does not properly in the loop - success callback does not work often
        var options = {
            url: url,
            type: "POST",
            data: request,
            cache: false,
            error: function(jqXHR, textStatus, errorThrown ) {
                if(callback){
                    
                    //var UNKNOWN_ERROR = (window.hWin)
                    //        ?window.hWin.ResponseStatus.UNKNOWN_ERROR:'unknown';
                    if(textStatus=='timeout'){
                        
                    }
                            
                    var err_message = (window.hWin.HEURIST4.util.isempty(jqXHR.responseText))
                                            ?'Error_Connection_Reset':jqXHR.responseText;
                    var response = {status:window.hWin.ResponseStatus.UNKNOWN_ERROR, 
                                        message: err_message,
                                        request_code:request_code};
                    
                    if(caller){
                        callback(caller, response);
                    }else{
                        callback(response);
                    }
                }
            },
            success: function( response, textStatus, jqXHR ){
                if(callback){
                    if(caller){

                        if($.isPlainObject(response)){
                            response.request_code = request_code;
                        }
                        callback(caller, response);
                    }else{
                        callback(response);    
                    }
                }
            },
            fail: function(  jqXHR, textStatus, errorThrown ){
                var err_message = (window.hWin.HEURIST4.util.isempty(jqXHR.responseText))?'Error_Connection_Reset':jqXHR.responseText;
                var response = {status:window.hWin.ResponseStatus.UNKNOWN_ERROR, 
                                message: err_message,
                                request_code:request_code};

                if(callback){
                    if(caller){
                        callback(caller, response);
                    }else{
                        callback(response);    
                    }
                }
            }
        }

        if(window.hWin.HEURIST4.util.isnull(dataType)){
            options['dataType'] = 'json';
        }else if(dataType!='auto'){
            options['dataType'] = dataType;    
        }
        if(timeout>0){
            options['timeout'] = timeout;    
        }
        
        $.ajax(options);
    },

    getScrollBarWidth: function() {
        var $outer = $('<div>').css({visibility: 'hidden', width: 100, overflow: 'scroll'}).appendTo('body'),
            widthWithScroll = $('<div>').css({width: '100%'}).appendTo($outer).outerWidth();
        $outer.remove();
        return 100 - widthWithScroll;
    },

    //
    // Parse string date using Temoral library
    //
    parseDates: function(start, end){
        if(window['Temporal'] && (start || end)){   
            //Temporal.isValidFormat(start)){
            if(start==null && end!=null){
                start = end;
                end = null;
            }

            // for VISJS timeline - must be ISO string
            function __forVis(dt){
                if(dt){
                    if(!dt.getMonth()){
                        dt.setMonth(1)
                    }
                    if(!dt.getDay()){
                        dt.setDay(1)
                    }

                    var res = dt.toString('yyyy-MM-ddTHH:mm:ssz');
                    if(false && res.indexOf('-')==0){ //BCE
                        res = res.substring(1);
                        //for proper parsing need 6 digit year
                        res = '-00'+res;//.substring(res.length));
                    }
                    return res;
                }else{
                    return '';
                }

            }    


            try{
                var temporal;
                if(start!="" && $.type( start ) === "string"){

                    if(start.search(/VER=/)!==-1){
                        temporal = new Temporal(start);
                        if(temporal){
                            var dt = temporal.getTDate('PDB');  //probable begin
                            if(!dt) dt = temporal.getTDate('TPQ');

                            if(dt){ //this is range - find end date
                                var dt2 = temporal.getTDate('PDE'); //probable end
                                if(!dt2) dt2 = temporal.getTDate('TAQ');
                                end = __forVis(dt2);
                            }else{
                                dt = temporal.getTDate('DAT');  //simple date
                            }

                            if(dt){
                                start = __forVis(dt);
                            }else{
                                return null;
                            }
                        }
                    }else{
                        start = __forVis(new TDate(start));
                    }
                }

                if(start!="" && $.type( end ) === "string") {
                    if(end.search(/VER=/)!==-1){
                        temporal = new Temporal(end);
                        if(temporal){
                            var dt = temporal.getTDate('PDE'); //probable end
                            if(!dt) dt = temporal.getTDate('TAQ');
                            if(!dt) dt = temporal.getTDate('DAT');
                            end = __forVis(dt);
                        }
                    }else{
                        end = __forVis(new TDate(end));
                    }
                }
            }catch(e){
                return null;
            }
            return [start, end];
        }
        return null;
    },    

    //
    // Get CSS property value for a not yet applied class
    //
    getCSS: function (prop, fromClass) {
        var $inspector = $("<div>").css('display', 'none').addClass(fromClass);
        $("body").append($inspector); // add to DOM, in order to read the CSS property
        try {
            return $inspector.css(prop);
        } finally {
            $inspector.remove(); // and remove from DOM
        }
    },

    //
    //
    //
    cssToJson: function(css){

        var json = {};

        if(css){

            var styles = css.split(';'),
            i= styles.length,
            style, k, v;


            while (i--)
            {
                var pos = styles[i].indexOf(':');
                if(pos>1){
                    k = $.trim(styles[i].substr(0,pos));
                    v = $.trim(styles[i].substr(pos+1));
                }
                /*style = styles[i].split(':');
                k = $.trim(style[0]);
                v = $.trim(style[1]);*/
                if (k && v && k.length > 0 && v.length > 0)
                {
                    if(v==='true')v=true;
                    else if(v==='false')v=false;     
                    json[k] = v;
                }
            }
        }

        return json;        

    },


    /*: function(e){
    for(var r=0,i=0;i<e.length;i++){
    r=(r<<5)-r+e.charCodeAt(i),r&=r;   
    }
    return r
    },*/

    hashString: function(str) {

        var hash = 0, i, c;
        var strlen = str?str.length:0;
        if (strlen == 0) return hash;

        for (i = 0; i < strlen; i++) {
            c = str.charCodeAt(i);
            hash = ((hash<<5)-hash)+c;
            hash = hash & hash; // Convert to 32bit integer
        }
        return hash;    
    },

    formatFileSize: function (bytes) {
        if (typeof bytes !== 'number') {
            return '';
        }
        if (bytes >= 1000000000) {
            return (bytes / 1000000000).toFixed(2) + ' GB';
        }
        if (bytes >= 1000000) {
            return (bytes / 1000000).toFixed(2) + ' MB';
        }
        return (bytes / 1000).toFixed(2) + ' KB';
    },


    //
    // download given url as a file (repalcement of usage A)
    //
    downloadURL: function(url, callback) {
        $idown = $('#idown');

        if ($idown.length==0) {
            $idown = $('<iframe>', { id:'idown' }).hide().appendTo('body');
        }
        if ($.isFunction(callback)) {
            $idown.on('load', callback);   
        }
        $idown.attr('src',url);
    },

    //
    // download content of given element (for example text area) as a text file
    //
    downloadInnerHtml: function (filename, ele, mimeType) {

        var elHtml = $(ele).html();
        window.hWin.HEURIST4.util.downloadData(filename, elHtml, mimeType);
    }, 

    //
    // download some data locally
    //
    downloadData: function (filename, data, mimeType) {

        mimeType = mimeType || 'text/plain';
        var  content = 'data:' + mimeType  +  ';charset=utf-8,' + encodeURIComponent(data);

        var link = document.createElement("a");
        link.setAttribute('download', filename);
        link.setAttribute('href', content);
        if (window.webkitURL != null)
        {
            // Chrome allows the link to be clicked
            // without actually adding it to the DOM.
            link.click();        
            link = null;
        }
        else
        {
            // Firefox requires the link to be added to the DOM
            // before it can be clicked.
            link.onclick = function(){ document.body.removeChild(link); link=null;} //destroy link;
            link.style.display = "none";
            document.body.appendChild(link);
            link.click();        
        }

    },    


    isRecordSet: function(recordset){
        return !window.hWin.HEURIST4.util.isnull(recordset) && $.isFunction(recordset.isA) && recordset.isA("hRecordSet");   
    },

    random: function(){
        //Math.round(new Date().getTime() + (Math.random() * 100));
        return Math.floor((Math.random() * 10000) + 1);
    },

    //scan all frames of current window and return object by name
    findObjInFrame: function(name){

        var i, frames;
        frames = document.getElementsByTagName("iframe");
        for (i = 0; i < frames.length; ++i)
        {  
            if( !window.hWin.HEURIST4.util.isnull(frames[i]['contentWindow'][name])){
                return frames[i]['contentWindow'][name];
            }
        }
        return null;
    },

    getMediaServerFromURL:function(filename){
        filename = filename.toLowerCase();
        if(filename.indexOf('youtu.be')>=0 || filename.indexOf('youtube.com')>=0){
            return 'youtube';
        }else if(filename.indexOf('vimeo.com')>=0){
            return 'vimeo';
        }else if(filename.indexOf('soundcloud.com')>=0){
            return 'soundcloud';            
        }else{
            return null;
        }
    },

    getFileExtension:function(filename){
        // (/[.]/.exec(filename)) ? /[^.]+$/.exec(filename)[0] : undefined;
        // filename.split('.').pop();
        //filename.slice((filename.lastIndexOf(".") - 1 >>> 0) + 2);
        if(filename){
            var res = filename.match(/\.([^\./\?]+)($|\?)/);
            return (res && res.length>1)?res[1]:'';
        }else{
            return '';
        }

    },

    //
    //
    //
    versionCompare: function(v1, v2, options) {
        // determines if the version in the cache (v1) is older than the version in configIni.php (v2)
        // used to detect change in version so that user is prompted to clear cache and reload
        // returns -1 if v1 is older, -2 v1 is newer, +1 if they are the same
        var lexicographical = options && options.lexicographical,
        zeroExtend = options && options.zeroExtend,
        v1parts = v1.split('.'),
        v2parts = v2.split('.');

        function isValidPart(x) {
            return (lexicographical ? /^\d+[A-Za-z]*$/ : /^\d+$/).test(x);
        }

        if (!v1parts.every(isValidPart) || !v2parts.every(isValidPart)) {
            return NaN;
        }

        if (zeroExtend) {
            while (v1parts.length < v2parts.length) v1parts.push("0");
            while (v2parts.length < v1parts.length) v2parts.push("0");
        }

        if (!lexicographical) {
            v1parts = v1parts.map(Number);
            v2parts = v2parts.map(Number);
        }

        var i = 0;
        for (; i < v1parts.length; ++i) {

            if (v1parts[i] == v2parts[i]) {
                continue; // sub elements are the same, continue compare
            }
            else if (v1parts[i] > v2parts[i] || window.hWin.HEURIST4.util.isnull(v2parts[i])) {
                return -2; // cached version is newer, we will still need to clear cache and reload
            }
            else {
                return -1; // cached version is older, we will need to clear cache and reload
            }
        }

        if (v2parts.length == i) {
            return 1; // versions are the saame
        }

        if (v1parts.length != v2parts.length) {
            return -1;
        }

        return 0;
    },

    uniqueArray: function(arr){

        var n = {},r=[];
        for(var i = 0; i < arr.length; i++) 
        {
            if($.isPlainObject(arr[i])){
                r.push(arr[i]);
            }else if (!n[arr[i]]) 
            {
                n[arr[i]] = true; 
                r.push(arr[i]); 
            }
        }
        return r;            
    },

    //not strict search - valuable for numeric vs string 
    findArrayIndex: function(elt, arr /*, from*/)
    {
        if( window.hWin.HEURIST4.util.isempty(arr) ) return -1;

        var len = arr.length;

        var from = Number(arguments[2]) || 0;
        from = (from < 0)
        ? Math.ceil(from)
        : Math.floor(from);
        if (from < 0)
            from += len;

        for (; from < len; from++)
        {
            if (from in arr &&
                arr[from] == elt)
                return from;
        }
        return -1;
    },

    //
    // assumed that sdate is in UTC
    //
    getTimeForLocalTimeZone: function (sdate){
        var date = new Date(sdate+"+00:00");
        return (''+date.getHours()).padStart(2, "0")
        +':'+(''+date.getMinutes()).padStart(2, "0")
        +':'+(''+date.getSeconds()).padStart(2, "0");
    },

    //
    //flflnaixr
    //
    copyStringToClipboard: function(string_to_copy) {
        function handler (event){
            event.clipboardData.setData('text/plain', string_to_copy);
            event.preventDefault();
            document.removeEventListener('copy', handler, true);
        }

        document.addEventListener('copy', handler, true);
        document.execCommand('copy');
    }          
    
}//end util

//-------------------------------------------------------------

String.prototype.htmlEscape = function() {
    return this.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/'/g, "&#39;");
}
String.prototype.capitalize = function() {
    return this.charAt(0).toUpperCase() + this.slice(1);
}
String.prototype.lpad = function(padString, length) {
    var str = this;
    while (str.length < length)
        str = padString + str;
    return str;
}

if (!Array.prototype.indexOf)
{
    Array.prototype.indexOf = function(elt /*, from*/)
    {
        var len = this.length;

        var from = Number(arguments[1]) || 0;
        from = (from < 0)
        ? Math.ceil(from)
        : Math.floor(from);
        if (from < 0)
            from += len;

        for (; from < len; from++)
        {
            if (from in this &&
                this[from] === elt)
                return from;
        }
        return -1;
    };
}



/*
if (!Array.prototype.unique){

    Array.prototype.unique = function()
    {
        
        //return $.grep(this, function(el, index) {
        //    return index === $.inArray(el, this);
        //});
        
        
            var n = {},r=[];
            for(var i = 0; i < this.length; i++) 
            {
                if (!n[this[i]]) 
                {
                    n[this[i]] = true; 
                    r.push(this[i]); 
                }
            }
            return r;        
    };
}
*/
}


$.getMultiScripts = function(arr, path) {
    var _arr = $.map(arr, function(scr) {
        return $.getScript( (path||"") + scr );
    });

    _arr.push($.Deferred(function( deferred ){
        $( deferred.resolve );
    }));

    return $.when.apply($, _arr);
}

//constants for saved searches\
const _NAME = 0, _QUERY = 1, _GRPID = 2;

