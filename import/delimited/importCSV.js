/**
* importCSV.js: UI for delimeted data import
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
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

/**
* Init function - it is invoked on document ready
* 
* 1) Fills rectype select
* 2) Assign onchange for this select - to load list of fields for selected rectype
* 3) Loads values for record from import table
* 4) Init values for mapping form (checkboxes and selected field in dropdowns)
*/
$(function() {
    
$("#div-progress").hide();
    
var select_rectype = $("#sa_rectype");
createRectypeSelect( select_rectype.get(0), null, 'Select record type' );

var allowed = Object.keys(top.HEURIST.detailTypes.lookups);
allowed.splice(allowed.indexOf("separator"),1);
allowed.splice(allowed.indexOf("relmarker"),1);
allowed.splice(allowed.indexOf("file"),1);
allowed.splice(allowed.indexOf("geo"),1);


select_rectype.change(function (event){
    
    var sel = event && event.target;
    if(!sel) return;
    
    var rectype = Number(sel.value);
    var select_fieldtype = $('select[id^="sa_dt_"]');
    select_fieldtype.each(function() {
        
        var allowed_ft = allowed;
        var topitems = [{key:'',title:'...'},{key:'id',title:'Record ID'},{key:'url',title:'Record URL'},{key:'notes',title:'Record Notes'}];
        if($(this).hasClass('indexes')){
            allowed_ft = ["resource"];
            topitems = [{key:'',title:'...'}];
        }
        
        createRectypeDetailSelect(this, rectype, allowed_ft, topitems, false);
    });
    
    //hide all checkboxes on rectype change
    $('input[id^="cbsa_dt_"]').each(function(){
        $(this).attr('checked', false);
        $(this).parent().hide();
    });
    
    //matching table
    var select_fieldtype = $('select[id^="sa_keyfield_"]');
    select_fieldtype.each(function() {
        
        var allowed_ft = allowed;
        var topitems = [{key:'',title:'...'},{key:'id',title:'Record ID'},{key:'url',title:'Record URL'},{key:'notes',title:'Record Notes'}];
        createRectypeDetailSelect(this, rectype, allowed_ft, topitems, false);
    });
    
    //hide all checkboxes on rectype change
    $('input[id^="cbsa_keyfield_"]').each(function(){
        $(this).attr('checked', false);
        $(this).parent().hide();
    });
    
    //uncheck id field radiogroup
    $("#rb_dt_new").attr('checked', true);
    $('tr[class^="idfield_"]').hide();
    $(".idfield_"+rectype).show();
    $("#new_idfield").val(rectype?top.HEURIST.rectypes.names[rectype]+' ID':'');
    
});

//Loads values for record from import table
getValues(0);

//init values for mapping form
if(!top.HEURIST.util.isnull(form_vals.sa_rectype)){
    select_rectype.val(form_vals.sa_rectype).change();
    
    //init inport form
    for (var key in form_vals){
        if(key.indexOf('sa_dt_')==0 && form_vals[key]!=''){
            //$('#'+key).parent().show();
            $('#'+key).val(form_vals[key]);
            $('#cb'+key).attr('checked', true);
            $('#cb'+key).parent().show();
        }
    }
    
    //init matching form
    for (var key in form_vals){
        if(key.indexOf('sa_keyfield_')==0 && form_vals[key]!=''){
            //$('#'+key).parent().show();
            $('#'+key).val(form_vals[key]);
            $('#cb'+key).attr('checked', true);
            $('#cb'+key).parent().show();
        }
    }    
    
    if(form_vals[new_idfield]){
        $("#new_idfield").val(form_vals["new_idfield"]);
        //$("#rb_dt_new").attr('checked', form_vals["idfield"]=="field_new");
    }
    var rb = $('input[id^="rb_dt__"][value="'+form_vals["idfield"]+'"]');
    if(rb.length>0){
        rb.attr('checked', true);
    }
    
}

showUpdMode();

}); //end init function

//
// Update progress counter for record update/insert
//
function update_counts(added, updated, total){
    $("#div-progress2").html("added: "+added+" updated:"+updatred+"  total:"+total);
}

//
// Start import OR records IDs assign
//
function doDatabaseUpdate(){
    $("#input_step").val(3);
    document.forms[0].submit();
}

//
// switch modes
//
function showUpdMode(){
    
    if($("#sa_mode1").is(":checked")){
        $(".importing").show();   
        $(".matching").hide();   
    }else{
        $(".importing").hide();
        $(".matching").show();   
    }
}

//
// show/hide checkbox on fieldtype select
//
function showFtSelect(ind){
    $sel = $('#sa_dt_'+ind);
    $ch = $('#cbsa_dt_'+ind);
    if($sel.val()==''){
        $ch.parent().hide();
        $ch.attr('checked', false);
    }else{
        $ch.parent().show();
        $ch.attr('checked', true);
    }
}
function showFtSelect2(ind){
    $sel = $('#sa_keyfield_'+ind);
    $ch = $('#cbsa_keyfield_'+ind);
    if($sel.val()==''){
        $ch.parent().hide();
        $ch.attr('checked', false);
    }else{
        $ch.parent().show();
        $ch.attr('checked', true);
    }
}

//
// show/hide fieldtype select on checkbox click
//
function hideFtSelect(ind){
    $sel = $('#sa_dt_'+ind);
    if($sel.parent().is(":visible")){
        $sel.val('');
        $sel.parent().hide();
    }else{
        $sel.parent().show();
    }
}

//
// Show error, matched or new records 
//
function showRecords(divname){
    
    $('div[id^="main_"]').hide();
    $('div[id="main_'+divname+'"]').show();
    
}

//
// Loads values for record from import table
//
function getValues(dest){
    if(dest==0){
        currentId=1;
    }else if(dest==recCount){
        currentId=recCount;
    }else{
        currentId = currentId + dest;
        if(currentId<1) {
            currentId=1;   
        }else if (currentId>recCount){
            currentId = recCount;
        }
    }
    
                 $.ajax({
                         url: top.HEURIST.basePath+'import/delimited/importCSV.php',
                         type: "POST",
                         data: {recid: currentId, table:currentTable, db:currentDb},
                         dataType: "json",
                         cache: false,
                         error: function(jqXHR, textStatus, errorThrown ) {
                              alert('Error connecting server '+textStatus);
                         },
                         success: function( response, textStatus, jqXHR ){
                             if(response){
                                var i;
                                for(i=1; i<response.length;i++){
                                    if($("#impval"+(i-1)).length>0)
                                        $("#impval"+(i-1)).html(response[i]);    
                                    if($("#impval_"+(i-1)).length>0)
                                        $("#impval_"+(i-1)).html(response[i]);    
                                }
                             }
                         }
                     });
    
}

//
// onsubmit event listener - basic validation that at least one field is mapped
//
function verifySubmit()
{
    var rectype = $("#sa_rectype").val();
    if(rectype>0){
        
        if( $("#sa_mode0").is(":checked")){ //matching

            var cb_keyfields = $('input[id^="cbsa_keyfield_"]:checked');
            if(cb_keyfields.length>0){
                
                var cb_idfield = $('input[id^="rb_dt_"]:checked');
                if(cb_idfield.length>0){
                    return true;
                }else{
                    alert("To search/match records you have to select ID field or define new one");
                }

            }else{
                alert("To search/match records you have to select at least one key field in import data");
            }
            
            
            
        }else{ //importing

            var select_fieldtype = $('select[id^="sa_dt_"][value!=""]');
            if(select_fieldtype.length>0){
                return true;
            }else{
                alert("You have to map import data to record's fields. Select at least one field type!");
            }
        }
        
    }else{
        alert("Select record type!");
    }

    return false;
}

//
// create SELECT element (see h4/utils)
//
function createSelector(selObj, topOptions) {
        if(selObj==null){
            selObj = document.createElement("select");
        }else{
            $(selObj).empty();
        }
        
        if(top.HEURIST.util.isArray(topOptions)){
            var idx;
            if(topOptions){  //list of options that must be on top of list
                for (idx in topOptions)
                {
                    if(idx){
                        var key = topOptions[idx].key;
                        var title = topOptions[idx].title;
                        if(!top.HEURIST.util.isnull(title))
                        {
                             if(!top.HEURIST.util.isnull(topOptions[idx].optgroup)){
                                var grp = document.createElement("optgroup");
                                grp.label =  title;
                                selObj.appendChild(grp);
                             }else{
                                top.HEURIST.util.addoption(selObj, key, title);    
                             }
                             
                        }
                    }
                }
            }        
        }else if(!top.HEURIST.util.isempty(topOptions)){
           top.HEURIST.util.addoption(selObj, '', topOptions); 
        }
        
        
        return selObj;
}
    
//
// create rectype SELECT element (see h4/utils)
//
function createRectypeSelect(selObj, rectypeList, topOptions) {

        createSelector(selObj, topOptions);

        var rectypes = top.HEURIST.rectypes,
            index;

        if(!rectypes) return selObj;


        if(rectypeList){

            if(!top.HEURIST.util.isArray(rectypeList)){
                   rectypeList = rectypeList.split(',');
            }

            for (var idx in rectypeList)
            {
                if(idx){
                    var rectypeID = rectypeList[idx];
                    var name = rectypes.names[rectypeID];
                    if(!top.HEURIST.util.isnull(name))
                    {
                         top.HEURIST.util.addoption(selObj, rectypeID, name);
                    }
                }
            }
        }else{
            for (index in rectypes.groups){
                    if (index == "groupIDToIndex" ||
                      rectypes.groups[index].showTypes.length < 1) {
                      continue;
                    }
                    var grp = document.createElement("optgroup");
                    grp.label = rectypes.groups[index].name;
                    selObj.appendChild(grp);

                    for (var recTypeIDIndex in rectypes.groups[index].showTypes)
                    {
                          var rectypeID = rectypes.groups[index].showTypes[recTypeIDIndex];
                          var name = rectypes.names[rectypeID];

                          if(!top.HEURIST.util.isnull(name)){
                                var opt = top.HEURIST.util.addoption(selObj, rectypeID, name);
                          }
                    }
            }
        }

        return selObj;
}

//
// create detailtype SELECT element (see h4/utils)
//
function createRectypeDetailSelect(selObj, rectype, allowedlist, topOptions, showAll) {

        createSelector(selObj, topOptions);
        
        var dtyID, details;

        if(Number(rectype)>0){
            //structure not defined 
            if(!(top.HEURIST.rectypes && top.HEURIST.rectypes.typedefs)) return selObj;
            var rectypes = top.HEURIST.rectypes.typedefs[rectype];
            
            if(!rectypes) return selObj;
            details = rectypes.dtFields;
            
            var fi = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex['rst_DisplayName'],
                fit = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex['dty_Type'],
                fir = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex['rst_RequirementType'];
            
            var arrterm = [];
            
            for (dtyID in details){
               if(dtyID){

                   if(allowedlist==null || allowedlist.indexOf(details[dtyID][fit])>=0)
                   {
                          var name = details[dtyID][fi];

                          if(!top.HEURIST.util.isnull(name)){
                                arrterm.push([dtyID, name+' ['+details[dtyID][fit]+']', (details[dtyID][fir]=="required")]);
                          }
                   }
               }
            }
            
            //sort by name
            arrterm.sort(function (a,b){ return a[1]<b[1]?-1:1; });
            //add to select
            var i=0, cnt= arrterm.length;
            for(;i<cnt;i++) {
                var opt = top.HEURIST.util.addoption(selObj, arrterm[i][0], arrterm[i][1]);  
                if(arrterm[i][2]){
                    opt.className = "required";
                }                
            }
            
        }else if(showAll){ //show all detail types
        
            if(!top.HEURIST.detailTypes) return selObj;
            
            var detailtypes = top.HEURIST.detailTypes;
            var fit = detailtypes.typedefs.fieldNamesToIndex['dty_Type'];
            
            
            for (index in detailtypes.groups){
                    if (index == "groupIDToIndex" ||
                      detailtypes.groups[index].showTypes.length < 1) {   //ignore empty group
                      continue;
                    }
                    
                    var arrterm = [];

                    for (var dtIDIndex in detailtypes.groups[index].showTypes)
                    {
                          var detailID = detailtypes.groups[index].showTypes[dtIDIndex];
                          if(allowedlist==null || allowedlist.indexOf(detailtypes.typedefs[detailID].commonFields[fit])>=0)
                          {
                              var name = detailtypes.names[detailID];

                              if(!top.HEURIST.util.isnull(name)){
                                    arrterm.push([detailID, name]);
                              }
                          }
                    }
                    
                    if(arrterm.length>0){
                        var grp = document.createElement("optgroup");
                        grp.label = detailtypes.groups[index].name;
                        selObj.appendChild(grp);
                        //sort by name
                        arrterm.sort(function (a,b){ return a[1]<b[1]?-1:1; });
                        //add to select
                        var i=0, cnt= arrterm.length;
                        for(;i<cnt;i++) {
                            top.HEURIST.util.addoption(selObj, arrterm[i][0], arrterm[i][1]);  
                        }
                    }
                    
            }
            
        }


        return selObj;
}