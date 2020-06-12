/*
* Copyright (C) 2005-2020 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


    var last_vals = new Object();

    var updateTimeoutID = 0,
        allowUpdate = false;

    function update(elt) {
        
        if(!allowUpdate){
            return;
        }

        if (updateTimeoutID) { clearTimeout(updateTimeoutID); updateTimeoutID = 0; }

        var q_elt = document.getElementById('q');

        if(elt.name == 'wgtag'){
            elt = document.getElementById('tag');
        }

        var snippet,
            eltname = elt.name,
            eltvalue = elt.value;

        if(eltname == 'tag'){
            var elt2 = document.getElementById('wgtag');
            if(elt2 && elt2.selectedIndex>0){
                var tag = elt2.value;
                if (tag.indexOf(' ')) tag = '"' + tag + '"';
                eltvalue = eltvalue + ' ' + tag;
            }
        }else if (eltname === "type") {
            // update the list of record-type-specific bib-detail-types
            var typeOptgroup = document.getElementById("rectype-specific-fields");
            var typeSelect = document.getElementById("fieldtype");
            var prevValue = typeSelect.options[typeSelect.selectedIndex].value; //previous value for fieldtype select

            typeOptgroup.innerHTML = "";    // remove all record-type-specific options

            var rt = elt.options[elt.selectedIndex].value.replace(/"/g, "");;
            for (var rftID in window.hWin.HEURIST4.rectypes.names) {
                if (window.hWin.HEURIST4.rectypes.names[rftID] === rt) {
                    rt = rftID;
                    break;
                }
            }
            var bdr = window.hWin.HEURIST4.rectypes.typedefs[rt];
            if (! bdr) {
                // no type specified; hide type-specific options
                typeOptgroup.style.display = "none";
            }
            else {
                var bdts = window.hWin.HEURIST4.detailtypes.typedefs;
                for (var bdtID in bdr['dtFields']) {
                    typeOptgroup.appendChild(new Option(bdr['dtFields'][bdtID][0], '"' + bdts[bdtID]['commonFields'][0] + '"'));
                }
                typeOptgroup.label = window.hWin.HEURIST4.rectypes.names[rt] + " fields";
                typeOptgroup.style.display = "";
            }

            for (var i=0; i < typeSelect.options.length; ++i) {
                if (typeSelect.options[i].value === prevValue) {
                    typeSelect.selectedIndex = i;
                    break;
                }
            }
        }


        if (eltvalue || eltname == 'field') {

            if (eltname == 'field') {
                var ft_elt = document.getElementById('fieldtype');
                if (ft_elt.selectedIndex == 0)
                    snippet = HQuery.makeQuerySnippet('all', eltvalue);
                else
                    snippet = HQuery.makeQuerySnippet('field:'+ft_elt.options[ft_elt.selectedIndex].value, eltvalue);

            } else if (eltname == 'fieldtype') {
                var field_elt = document.getElementById('field');
                if (field_elt.value == '') return;
                if (elt.selectedIndex == 0)
                snippet = HQuery.makeQuerySnippet('all', field_elt.value);
                else
                snippet = HQuery.makeQuerySnippet('field:'+elt.options[elt.selectedIndex].value, field_elt.value);

            } else if (eltname == 'sortby') {
//                if (eltvalue.match(/^f:|^field:/)) {
//                    var sortby_field = document.getElementById('ascdesc').value + eltvalue + (document.getElementById('sortby_multiple').checked? '' : ':m');
//                    snippet = HQuery.makeQuerySnippet(eltname, sortby_field);
//                } else {
                    snippet = HQuery.makeQuerySnippet(eltname, document.getElementById('ascdesc').value + eltvalue);
//                }

            } else {
                snippet = HQuery.makeQuerySnippet(eltname, eltvalue);
            }
        } else {
            snippet = '';
        }

        if (snippet == last_vals[eltname]  &&  q_elt.value.indexOf(snippet) >= 0) return;

        var new_q_val;
        if (last_vals[eltname]  &&  eltname != 'fieldtype') {
            // attempt to replace the existing value for this field with the new value
            new_q_val = (' '+q_elt.value).replace(last_vals[eltname], snippet);

            // if we couldn't find the existing value, then just concatenate the snippet
            if (new_q_val == (' '+q_elt.value)) { new_q_val = q_elt.value + ' ' + snippet; }
        } else {
            if (q_elt.value)
            new_q_val = q_elt.value + ' ' + snippet;
            else
            new_q_val = snippet;
        }

        if (new_q_val.match(/\bOR\b(?!.*\bAND\b)/)  &&  new_q_val.length > snippet.length  &&  ! snippet.match(/^sortby:/)) {
            /*
            One of the snippets contains an OR, so search construction will not do what the user expects ...
            e.g. if they type  xxx  in the title box, and  x OR y  in the tag box,
            then the constructed search would be
            xxx tag:x OR tag:y
            which would find title xxx and tag x, OR tag y
            when what they want is title xxx and tag x, OR title xxx and tag y.
            i.e. the OR is not distributed.
            Now, we could do a mass-o piece-by-piece OR distribution,
            or we can introduce top-top-level AND.
            Guess which is easier and more flexible? (and more likely to DWIM?)
            */
            new_q_val = new_q_val.replace(snippet, 'AND '+snippet);
        }

        last_vals[eltname] = snippet;
        if (eltname == 'fieldtype') last_vals['field'] = '';
        q_elt.value = new_q_val.replace(/^\s*AND\b|\bAND\s*$|\bAND\s+(?=AND\b)/g, '').replace(/^\s+|\s+$/g, '');
    }


    function do_search() {
        /*
        var s_elt = top.document.getElementById('s');
        var s_val = document.getElementById('sortby').value;
        if (s_val && s_val.match(/^f:\d+$/)) {
        var f_elt = top.document.getElementById('f');
        f_elt.value = s_val;
        f_elt.disabled = false;
        }
        s_elt.value = s_val;
        */
        window.close(document.getElementById('q').value);
    }


    function load_query() {
        
        $('button').button();
        
        /*init rectype and group selector
        $recTypeSelector = window.hWin.HEURIST4.ui.createRectypeSelect( $('#sel_record_type').get(0), null, 
                    window.hWin.HR('select record type'), true );
        $recTypeSelector.change(function(event){ update(event.target) } )
        //$recTypeSelector.hSelect({ change:update( $('#sel_record_type').get(0) ) });
        */
        
        var params = window.hWin.HEURIST4.util.parseHeuristQuery(location.search);

        var q_str = decodeURIComponent(params["q"]);
        var q_bits = null;
        
        if(window.hWin.HEURIST4.util.isempty(q_str)){
            document.getElementById('q').value = '';
        }else{
            document.getElementById('q').value = q_str;
            q_bits = HQuery.parseQuery(q_str);
        }

        if (!window.hWin.HEURIST4.util.isnull(q_bits)) {
            for (q_key in q_bits) {
                if (document.getElementById(q_key)) {
                    var val = '';
                    for (var i=0; i < q_bits[q_key].length; ++i) {
                        if (val) val += ' OR ';
                        val += q_bits[q_key][i].join(' ');
                    }
                    if (q_key == 'sortby'  &&  val[0] == '-') {
                        document.getElementById(q_key).value = val.substring(1);
                        document.getElementById('ascdesc').value = '-';
                    }
                    document.getElementById(q_key).value = val;

                } else if (q_key.match(/^field:\d+$/)) {
                    document.getElementById('fieldtype').value = q_key.substr(6);
                    document.getElementById('field').value = q_bits[q_key];

                } else if (q_key == 'all') {
                    document.getElementById('fieldtype').value = '';
                    document.getElementById('field').value = q_bits[q_key];
                }
            }
        }

        reconstruct_query();

        // document.getElementById('tag').focus();
    }


    function reconstruct_query() {
        // reconstruct the query in the SEARCH box (using the canonical fully-modified form)

        var field_names = ['title', 'tag', 'url', 'type', 'user']; //'notes',

        var q_val = '';
        for (var i=0; i < field_names.length; ++i) {
            var field_val = document.getElementById(field_names[i]).value;
            if (! field_val) continue;

            if (! q_val) {
                q_val = HQuery.makeQuerySnippet(field_names[i], field_val);
            } else {
                if (q_val.match(/\bOR\b(?!.*AND\b)/)) q_val += ' AND';    // query contains an OR with no trailing AND
                q_val += ' ' + HQuery.makeQuerySnippet(field_names[i], field_val);
            }
        }

        if (document.getElementById('field').value) {
            var field_val = document.getElementById('field').value;
            var field_spec = '';
            if (document.getElementById('fieldtype').value)
            field_spec = HQuery.makeQuerySnippet('field:' + document.getElementById('fieldtype').value, field_val);
            else
            field_spec = HQuery.makeQuerySnippet('all', field_val);

            if (! q_val) {
                q_val = field_spec;
            } else {
                if (q_val.match(/\bOR\b(?!.*AND\b)/)) q_val += ' AND';    // query contains an OR with no trailing AND
                q_val += ' ' + field_spec;
            }
        }

        var sortby = document.getElementById('sortby').value;
        var sign = document.getElementById('ascdesc').value;
        if (! (sortby == 't'  &&  sign == '')) {
            if (! q_val) {
                q_val = 'sortby:' + sign + sortby;
            } else {
                if (q_val.match(/\bOR\b(?!.*AND\b)/)) q_val += ' AND';    // query contains an OR with no trailing AND
                q_val += ' sortby:' + sign + sortby;
            }
        }

        document.getElementById('q').value = q_val;
    }


    function keypress(e) {
        var targ;
        if (! e) e = window.event;
        if (e.target) targ = e.target;
        else if (e.srcElement) targ = e.srcElement;
        if (targ.nodeType == 3) targ = targ.parentNode;

        var code = e.keyCode;
        if (e.which) code = e.which;

        if (code == 13) do_search();
        if (code == 32 || code == 37 || code == 39) return true;
        if ((code < 8) || (code >= 10  &&  code < 040) || (code == 041) || (code >= 043 && code <= 046) || (code >= 050 && code <= 053) || (code == 057) || (code >= 073 && code <= 0100) || (code >= 0133 && code <= 0136) || (code == 0140) || (code >= 0173 && code <= 0177)) return false;

        if (updateTimeoutID) clearTimeout(updateTimeoutID);
        updateTimeoutID = setTimeout(function() { update(targ) }, 100);

        return true;
    }

    var filterTimeout = 0;
    function invoke_refilter() {
        if (filterTimeout) clearTimeout(filterTimeout);
        filterTimeout = setTimeout(refilter_usernames, 250);
    }

    function refilter_usernames() {
        if (filterTimeout) clearTimeout(filterTimeout);
        filterTimeout = 0;


        var user_search_val = document.getElementById('user_search').value.toLowerCase().replace(/^\s+|\s+$/g, '');
        var user_elt = document.getElementById('user');
        var all_user_elt = document.getElementById('users_all');

        if (user_search_val == '') {
            user_elt.options[0].text = '(specify partial user name)';
            user_elt.selectedIndex = 0;
            return;
        }

        user_elt.disabled = true;
        user_elt.options[0].text = user_search_val + ' [searching]';
        user_elt.selectedIndex = 0;

        user_elt.length = 1;

        var num_matches = 0;
        var first_match = 0;
        for (var i=0; i < all_user_elt.options.length; ++i) {
            if (all_user_elt.options[i].text.toLowerCase().indexOf(user_search_val) >= 0) {
                user_elt.options[user_elt.options.length] = new Option('\xA0'+all_user_elt.options[i].text, all_user_elt.options[i].value);
                ++num_matches;
            }
        }

        if (num_matches > 0) {
            if (num_matches == 1) {
                user_elt.options[0].text = user_search_val + '\xA0\xA0\xA0[1 match]';
            } else {
                user_elt.options[0].text = user_search_val + '\xA0\xA0\xA0[' + num_matches + ' matches]';
            }
            user_elt.selectedIndex = 0;
            user_elt.options[0].disabled = true;

        } else {
            user_elt.options[0].text = user_search_val + ' [no matches]';
        }

        user_elt.disabled = false;
        user_elt.options[0].style.color = user_elt.style.color = 'gray';
    }

    function reset_usernames() {
        if (filterTimeout) clearTimeout(filterTimeout);
        filterTimeout = 0;

        var user_elt = document.getElementById('user');
        user_elt.options[0].text = '(matching users)';
        user_elt.options[0].style.color = user_elt.style.color = 'black';
        while (user_elt.length > 1)
        user_elt.remove(user_elt.length - 1);
    }

    function keypressRedirector(e) {
        if (! e) e = window.event;
        if (e.keyCode) code = e.keyCode;
        else if (e.which) code = e.which;

        var user_search_elt = document.getElementById('user_search');
        var user_elt = document.getElementById('user');

        if (code >= 48  && code <= 126  ||  code == 32) {
            user_search_elt.value += String.fromCharCode(code);
            refilter_usernames();

            e.cancelBubble = true;
            return false;
        } else if (code == 8) {
            user_search_elt.value = user_search_elt.value.substring(0, user_search_elt.value.length-1);
            refilter_usernames();

            e.cancelBubble = true;
            return false;
        }

        return true;
    }

    function clear_fields() {
        document.getElementById('q').value='';
        var elts = document.getElementsByTagName('input');
        for (var i = 0; i < elts.length; i++){
            elts[i].value = elts[i].defaultValue;
        }

        var elts = document.getElementsByTagName('select');
        for (var i = 0; i < elts.length; i++){
            elts[i].selectedIndex = 0;
        }
        reset_usernames();
    }

    //
    //
    //
    function add_to_search(id){
        allowUpdate = true;
        var ele = document.getElementById(id);
        update(ele);
        allowUpdate = false;
    }

    //
    //
    //
    function setIndividually(ele){
        allowUpdate = !ele.checked;
        var elts = document.getElementsByTagName('button');
        for (var i = 0; i < elts.length; i++){
            if(elts[i].id!="btnSearch"){
                elts[i].style.visibility = allowUpdate?"hidden":"visible";
            }
        }
    }

    function handleFieldSelect(e){

        var dtID = parseInt(e.target.value);
        var isEnum = false;

        //if enum selector exist remove it
        var prev = document.getElementById("ft-enum-container");
        var inp_fld = document.getElementById("ft-input-container");
        while(prev.childNodes.length>0) {
                   if(prev.childNodes.length>0){
                        prev.removeChild(prev.childNodes[0]);
                   }
        }

        if (dtID>0){

            var dtyDefs = window.hWin.HEURIST4.detailtypes.typedefs;
            isEnum = (dtyDefs[dtID] && dtyDefs[dtID]['commonFields'][dtyDefs['fieldNamesToIndex']['dty_Type']] === 'enum');
            // if detatilType is enumeration then create a select for the values.
            if (isEnum){

                //create selector from typedef
                var allTerms = dtyDefs[dtID]['commonFields'][dtyDefs['fieldNamesToIndex']['dty_JsonTermIDTree']],
                    disabledTerms = dtyDefs[dtID]['commonFields'][dtyDefs['fieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs']];
                    
                var enumSelector = window.hWin.HEURIST4.ui.createTermSelectExt2(null,
                {datatype:'enum', termIDTree:allTerms, headerTermIDsList:disabledTerms,
                    defaultTermID:null, topOptions:'select term', supressTermCode:true, useHtmlSelect:true});
                    
                enumSelector.attr('id',"enum-selector").css({'max-wdith':'180px',width:'180px'}).appendTo($(prev));

                enumSelector.change(function(e){
                        var term_id = Number(e.target.value);
                        var fld = document.getElementById("field");
                        fld.value = (term_id>0)?term_id:'';
                        update(fld);
                });
                
                //attach onchange handler
                /*enumSelector.hSelect({ change: function(e, data){
                        var term_id = Number(data.item.value);
                        var fld = document.getElementById("field");
                        fld.value = term_id;
                        update(fld);
                }
                });*/
            }
        }

        if(!isEnum){
            prev.style.display = "none";
            inp_fld.style.display = "inline-block";
        }else{
            prev.style.display = "inline-block";
            inp_fld.style.display = "none";
        }

        //top.HEURIST.search.calcShowSimpleSearch(e);
    }
