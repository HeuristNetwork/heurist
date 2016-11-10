/**
* exportDelimitedForRectype.js: functions to implement CSV/TSV export of data, used by exportDelimitedForRectype.html
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney

* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


// global variables  Note: all globals are prefixed with g_ to make them identifiable
// in the code

var g_fields = [];				//pointer relations to import
var g_recTypeSelect = null;		//Record Type Selection object
var g_recDetailSelect = null;	//Detail Type Selection object

var g_delimiterSelect = null;
var g_dtIDsCheckbox = null;
var g_quoteSelect = null;

var g_detailTypes = [];
var g_recType = null;
var g_recTypeLoaded;
var g_usedQuery = null;

var g_exportMap = [];
var g_exportMapNames = [];
var g_cols = [];
var g_records = [];


/*
This function gets the record types from HAPI and loads them into a Select element
for multi-select, so the user can select the output data (record type + fields).
*/
function getRecTypes() {
    var e = document.getElementById("select-rec-type");

    removeChildren(e);

    g_recTypeSelect = e.appendChild(document.createElement("select"));
    // once a recordtype is selected show the detailTypes
    g_recTypeSelect.onchange = function() {
        g_recType = null;
        g_records = [];
        getDetailTypes()
    };
    var opt = document.createElement("option");
    opt.innerHTML = "record type...";
    opt.disabled = true;
    opt.selected = true;
    g_recTypeSelect.appendChild(opt);
    var recTypes = HRecordTypeManager.getRecordTypes();
    var l = recTypes.length;
    for (var i = 0; i < l; ++i) {
        opt = document.createElement("option");
        opt.value = recTypes[i].getID();
        opt.innerHTML = recTypes[i].getName();
        g_recTypeSelect.appendChild(opt);
    }
    
    g_recTypeSelect.selectedIndex  = 1;
    g_recTypeSelect.onchange();
}


/*
This function reads in the Detail Types (fields) for the selected Record Type.
*/
function getDetailTypes(){
    var e = document.getElementById("select-detail-type");

    //remove the old selection so we can recreate it
    removeChildren(e);

    //create a multi-selection list of Detail Types   FIXME -- Should handle the single-type case
    g_recDetailSelect = e.appendChild(document.createElement("select"));
    g_recDetailSelect.multiple = true;
    g_recDetailSelect.onchange = function(){ updateExportMap() };
    g_recType = HRecordTypeManager.getRecordTypeById(g_recTypeSelect.value);
    g_detailTypes = HDetailManager.getDetailTypesForRecordType(g_recType);

    //add option for each detail type where the value=index into g_detailTypes and text=detail name (record type specific)
    var l = g_detailTypes.length;
    g_recDetailSelect.size = l>15?15:l;
    for (var d = 0; d < l; ++d) {
        addOpt(g_recDetailSelect, d, HDetailManager.getDetailNameForRecordType(g_recType, g_detailTypes[d]));
    }

    updateExportMap();
}


function addOpt(sel, val, text) {
    var opt = sel.appendChild(document.createElement("option"));
    opt.value = val;
    opt.innerHTML = text;
    return opt;
}


function updateExportMap() {
    //clean up for multiple re-entry
    var el = document.getElementById("export-detail-map");

    //remove the old selection so we can recreate it
    removeChildren(el);

    while (g_exportMap.length>0){
        g_exportMap.pop();
    }
    g_exportMapNames = [];

    g_dtIDsCheckbox = document.getElementById('dtIDsCheckbox');

    //if multiple select then for each selected pointer create input field marking id with detailType id
    var table = el.appendChild(document.createElement("table"));
    table.id = "export-map-table";
    var tr = table.appendChild(document.createElement("tr"));
    tr.id = "export-map-row";
    var td, sel, opt;
    td = tr.appendChild(document.createElement("td"));
    td.innerHTML = "Record ID";
    g_exportMapNames.push("Record ID");

    var l = g_recDetailSelect.options.length;
    for (var i = 0; i < l; ++i) {
        if (g_recDetailSelect.options[i].selected) {
            sel = g_recDetailSelect.options[i];
            if (g_dtIDsCheckbox.checked) {
                td = tr.appendChild(document.createElement("td"));
                td.innerHTML = g_detailTypes[sel.value].getID() + ":";
            }
            td = tr.appendChild(document.createElement("td"));
            td.innerHTML = sel.text;
            g_exportMap.push(sel.value);
            g_exportMapNames.push(sel.text);
            /* version 2 should allow the selection of the detail type for the referenced object type (constrained case)
            // if constrained case then show select list for field in referenced record type
            // note that this will require multiple pass record retrieval
            if (g_detailTypes[i].getVariety() == HVariety.REFERENCE) {
            var constrRecType = g_detailTypes[i].getConstrainedRecordType();
            if (constrRecType){
            // add select to column
            //fill options for each detail type for contrained record type
            }
            */
        }
    }

    refreshRecordData();
}


function removeChildren (e) {
    while (e.firstChild){
        e.removeChild(e.firstChild);
    }
}


function loadAllRecords(query, options, loader) {
    var records = [];
    var baseSearch = new HSearch(query, options);
    var bulkLoader = new HLoader(
        function(s, r, c) {	// onload
            records.push.apply(records, r);
            var elres = document.getElementById('results');

            if (r.length < 100) {
                // we've loaded all the records: invoke the loader's onload
                elres.innerHTML = '<br><b>Loaded ' + records.length + ' of ' + c + ' records </b>(select below with Ctrl-A, copy and paste to file as required)</b>';
                loader.onload(baseSearch, records);
            }
            else {
                elres.innerHTML = '<b>Loaded ' + records.length + ' of ' + c + ' records so far ...</b>';

                // might be more records to load: do a search with an offset specified
                var search = new HSearch(query + " offset:"+records.length, options);
                HeuristScholarDB.loadRecords(search, bulkLoader);
            }
        },
        loader.onerror
    );
    HeuristScholarDB.loadRecords(baseSearch, bulkLoader);
}


function getRecords() {
    
    if(top.HEURIST4 && top.HAPI4 && top.HEURIST4.util && !top.HEURIST4.util.isnull(top.HEURIST4.current_query_request)){
        getRecordsByH4();
        return;
    }

    if(g_recType==null){
        alert("Please select a record type");
        return;
    }
    if(g_exportMap.length<1){
        alert("Please select fields to export");
        return;
    }

    if (!g_recType || (g_recType != g_recTypeLoaded) || (document.getElementById("queryInput").value!=g_usedQuery))
    {
        g_usedQuery = document.getElementById("queryInput").value;
        var myQuery = "type:" + g_recTypeSelect.value + " " + g_usedQuery;

        var loader = new HLoader(
            function(s,r) {
                showRecordData(r);
                g_records = r;
                g_recTypeLoaded = g_recType;
            },
            function(s,e) {
                alert("System error - getRecords failed: " + e);
        });
        loadAllRecords(myQuery, null, loader);
    }else{
        showRecordData(g_records);
    }
}


//extracts url from urlinclude value - perhaps move it to HAPI?
function extractURL(urlinclude){
    if(urlinclude && urlinclude!=""){
        var ar = urlinclude.split('|');
        return ar[0];
    }else{
        return  urlinclude;
    }
}


//remove textarea for multiple exports
function clearOutput(){
    if(document.getElementById("queryInput").value!=g_usedQuery){
        var e = document.getElementById("records-p");
        removeChildren(e);
        var elres = document.getElementById('results');
        elres.innerHTML = "";
    }
}


function refreshRecordData(){
    //var recDisplay = e.appendChild(document.createElement("textarea"));
    showRecordData(g_records);
}


function showRecordData(hRecords) {

    g_delimiterSelect = document.getElementById('delimiterSelect');
    var strDelim = g_delimiterSelect.value;

    //g_quoteSelect = document.getElementById('quoteSelect');
    //var quoteDelim = g_quoteSelect.value;

    if(strDelim=='0') {
        strDelim = '';
    }
    var strRowTerm = "\n";

    var e = document.getElementById("records-p");
    removeChildren(e);

    var e = document.getElementById("records-p");

    if(g_exportMapNames.length<1 && hRecords.length<1){
        return;
    }

    //create output area
    var recDisplay = e.appendChild(document.createElement("textarea"));
    recDisplay.id = "csv-textarea";

    var lines = "";
    var dl;

    // Generate header if required
    if (document.getElementById("includeFieldNamesCheckbox").checked) {

        var k = g_exportMapNames.length,
        line = "";
        for (var j = 0; j < k; j++) {
            if (g_dtIDsCheckbox.checked && j>0) {
                var hDty = g_detailTypes[g_exportMap[j-1]];
                line += hDty.getID() + strDelim;
            }
            line += (g_exportMapNames[j]+strDelim);
        }
        lines = line.slice(0,-1) + strRowTerm;
    }

    // Generate rows of data
    var l = hRecords.length;
    for (var i = 0; i < l; ++i) {
        var line = hRecords[i].getID()+ strDelim;
        var k = g_exportMap.length;
        for (var j = 0; j < k; ++j) {
            var hDty = g_detailTypes[g_exportMap[j]];
            var baseType = hDty.getVariety();
            if (g_dtIDsCheckbox.checked) {
                line += hDty.getID() + strDelim;
            }
            if ( HDetailManager.getDetailRepeatable(g_recType, hDty)){ //repeatable detail case
                var details = hRecords[i].getDetails(hDty);
                if (!details[0]){  //null detail
                    line += strDelim;
                }else {
                    if (baseType == HVariety.URLINCLUDE) {

                        var field = "";
                        dl = details.length;
                        for(var d = 0; d < dl; ++d){ //Ver 2 will need to extract the value for the selected detail type
                            field += extractURL(details[d]) +("|");
                        }
                        line += csv_escape(field.slice(0,-1)) + strDelim;  // trim off last delimiter


                    }else if (baseType == HVariety.REFERENCE) { //multi-valued reference detail
                        dl = details.length;
                        for(var d = 0; d < dl; ++d){ //Ver 2 will need to extract the value for the selected detail type
                            line += details[d].getID() +("|");
                        }
                        line = line.slice(0,-1) + strDelim;  // trim off last delimiter
                    }else if (baseType == HVariety.GEOGRAPHIC) { //geographic object
                        var field = "";
                        dl = details.length;
                        for(var d = 0; d < dl; ++d){ //Ver 2 will need to extract the value for the selected detail type
                            field += details[d].getWKT() +("|");
                        }
                        line += csv_escape(field.slice(0,-1)) + strDelim;  // trim off last delimiter
                    }else if (baseType == HVariety.FILE) { //file
                        var field = "";
                        dl = details.length;
                        for(var d = 0; d < dl; ++d){ //Ver 2 will need to extract the value for the selected detail type
                            field += details[d].getURL() +("|");
                        }
                        line += csv_escape(field.slice(0,-1)) + strDelim;  // trim off last delimiter
                    }else{ // multi-valued non-reference case
                        line += dblquote_escape(details.join("|")) + strDelim;
                    }
                }
            }else{ // single valued cases
                if (baseType == HVariety.URLINCLUDE) {
                    line += csv_escape(extractURL(hRecords[i].getDetail(hDty)))+strDelim;

                }else if (baseType == HVariety.REFERENCE) { //reference case
                    var record = hRecords[i].getDetail(hDty);
                    if (record) {//reference  Ver 2 will need to extract the value for the selected detail type
                        line += record.getID()+strDelim;
                    }else{//null reference
                        line += strDelim;
                    }
                }else if (baseType == HVariety.GEOGRAPHIC) { //geographic object
                    var geoDetail = hRecords[i].getDetail(hDty);
                    line += (geoDetail ? csv_escape(geoDetail.getWKT()) : '') +strDelim;
                }else if (baseType == HVariety.FILE) { //file
                    var fileDetail = hRecords[i].getDetail(hDty);
                    line += (fileDetail ? csv_escape(fileDetail.getURL()) : '') +strDelim;
                }else if (baseType == HVariety.ENUMERATION || baseType == HVariety.RELATIONTYPE ) { //enum or relation
                    var termDetail = hRecords[i].getDetail(hDty);
                    line += (termDetail ? csv_escape(termDetail) : '') +strDelim;
                }else{//general case
                    line += dblquote_escape(hRecords[i].getDetail(hDty))+strDelim;
                }
            }
        }
        lines += line.slice(0,-1) + strRowTerm;  //remove last delimiter and terminate line
    }
    recDisplay.value = lines;
}


function dblquote_escape(str) {
    if (!str) {
        return '';
    }
    return '"' + str.replace(/"/g, '""') + '"';
}


function csv_escape(str) {
    if (!str) {
        return '';
    }
    if (g_delimiterSelect.value == "," && str.match(/[",]/)||(str.match(/[\n\t]/))) {
        return '"' + str.replace(/"/g, '""') + '"';
    }
    return str;
}

//-------------------------
function getRecordsByH4(){
    
    var request = top.HEURIST4.current_query_request;
    if(!request.w) request.w = 'all';
    if(!request.a) request.a = '1';
    //request.detail = [] array of fields to download
    
    var len = g_exportMapNames.length,
        details = [];
    
    for (var j = 0; j < len; j++) {
        if (j>0) {
            var hDty = g_detailTypes[g_exportMap[j-1]];
            details.push( hDty.getID() );
        }
    }
    request.detail = details;
    
    top.HAPI4.SearchMgr.doSearchWithCallback( request, function( recordset, original_recordset ){

        if(recordset){
            //alert("got: "+recordset.length());
            _showRecordDataH4(recordset)
        }

    });    
    
}

function _showRecordDataH4(recordset) {

    g_delimiterSelect = document.getElementById('delimiterSelect');
    var strDelim = g_delimiterSelect.value;

    //g_quoteSelect = document.getElementById('quoteSelect');
    //var quoteDelim = g_quoteSelect.value;

    if(strDelim=='0') {
        strDelim = '';
    }
    var strRowTerm = "\n";

    var e = document.getElementById("records-p");
    removeChildren(e);

    var e = document.getElementById("records-p");

    if(g_exportMapNames.length<1 && recordset.length()<1){
        return;
    }

    //create output area
    var recDisplay = e.appendChild(document.createElement("textarea"));
    recDisplay.id = "csv-textarea";

    var lines = "";
    var dl;

    // Generate header if required
    if (document.getElementById("includeFieldNamesCheckbox").checked) {

        var k = g_exportMapNames.length,
        line = "";
        for (var j = 0; j < k; j++) {
            if (g_dtIDsCheckbox.checked && j>0) {
                var hDty = g_detailTypes[g_exportMap[j-1]];
                line += hDty.getID() + strDelim;
            }
            line += (g_exportMapNames[j]+strDelim);
        }
        lines = line.slice(0,-1) + strRowTerm;
    }

    // Generate rows of data
    var len = recordset.length();
    
    var recs = recordset.getRecords();
    var rec_order = recordset.getOrder();
    var recID;
    
    for (var i = 0; i < len; ++i) {
        recID = rec_order[i];
        if(recID && recs[recID]){
            
            var line = recID + strDelim;
            var record = recs[recID];
            
            var k = g_exportMap.length;
            for (var j = 0; j < k; ++j) {
                    var hDty = g_detailTypes[g_exportMap[j]];
                    
                    if (g_dtIDsCheckbox.checked) {
                        line += hDty.getID() + strDelim;
                    }
                    var baseType = hDty.getVariety();
            
                    var val = record['d'][hDty.getID()];
                    
                    if(top.HEURIST4.util.isnull(val)){
                        line += strDelim;
                    }else{
                        var field = '';
                        if (baseType == HVariety.ENUMERATION || baseType == HVariety.RELATIONTYPE ){
                            var domain = (baseType == HVariety.RELATIONTYPE)?'relation':'enum';
                            if(top.HEURIST4.util.isArrayNotEmpty(val)){
                                for (var m = 0; m < val.length; m++) {
                                   var term = top.HEURIST4.terms.termsByDomainLookup[domain][val[m]];
                                   if(term) val[m] = term[0];
                                }
                            }
                        }
                        
                        if(top.HEURIST4.util.isArrayNotEmpty(val)){
                            field = val.join('|');
                        }else{
                            field = val;
                        }
                        line += ((!top.HEURIST4.util.isempty(field))?dblquote_escape(field):'') + strDelim;
                        
                    }
                    
                    
            }//for
            lines += line.slice(0,-1) + strRowTerm;
        }
    }//for
    recDisplay.value = lines; 
}
