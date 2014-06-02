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
createRectypeSelect( select_rectype.get(0), null, 'select...' );

var allowed = Object.keys(top.HEURIST.detailTypes.lookups);
allowed.splice(allowed.indexOf("separator"),1);
allowed.splice(allowed.indexOf("relmarker"),1);
allowed.splice(allowed.indexOf("file"),1);
allowed.splice(allowed.indexOf("geo"),1);
//
document.title = "Import Records from "+currentSessionName;


select_rectype.change(function (event){
    
    var sel = event && event.target;
    if(!sel) return;
    
    var rectype = Number(sel.value);
    //fiel detailtypes selects for import
    var select_fieldtype = $('select[id^="sa_dt_"]');
    select_fieldtype.each(function() {
        
        var allowed_ft = allowed;
        var topitems = [{key:'',title:'...'},{key:'url',title:'Record URL'},{key:'scratchpad',title:'Record Notes'}];
        if($(this).hasClass('indexes')){
            allowed_ft = ["resource"];
            topitems = [{key:'',title:'...'}];
        }
        
        createRectypeDetailSelect(this, rectype, allowed_ft, topitems, false);
    });
    
    //fill detailtype selects for matching table
    var select_fieldtype = $('select[id^="sa_keyfield_"]');
    select_fieldtype.each(function() {
        
        var allowed_ft = allowed;
        var topitems = [{key:'',title:'...'},{key:'id',title:'Record ID'},{key:'url',title:'Record URL'},{key:'scratchpad',title:'Record Notes'}];
        createRectypeDetailSelect(this, rectype, allowed_ft, topitems, false);
    });
    
    //uncheck and enable all checkboxes on rectype change
    $('input[id^="cbsa_dt_"]').attr('checked', false); //false);
    $('input[id^="cbsa_dt_"]').attr('disabled',false); 
    
    $('input[id^="cbsa_keyfield_"]').attr('checked', false);
    $('input[id^="cbsa_dt_"]').parent().hide();
    $('input[id^="cbsa_keyfield_"]').parent().hide();
    
    //hide all selects
    $('select[id^="sa_keyfield_"]').each(function(){
        $(this).val('');
        $(this).parent().hide();
    });
    $('select[id^="sa_dt_"]').each(function(){
        $(this).val('');
        $(this).parent().hide();
    });
    
    $('#btnStartMatch').hide();
    $('#btnStartImport').hide();
    
    //id field 
    if(rectype){
        $("#div_idfield").show();
        $('input[id^="cbsa_dt_"]').parent().show();
        $('input[id^="cbsa_keyfield_"]').parent().show();
        
        //uncheck id field radiogroup - matching
        $("#new_idfield").val(rectype?top.HEURIST.rectypes.names[rectype]+' ID':'');
        //$('input[id^="rb_dt_"]').attr('checked', false);
        $('tr[class^="idfield_"]').hide(); //hide all
        var sel_rt = $('tr[class^="idfield_'+rectype+'"]');
        //check existing by default
        if(sel_rt.length>0){  //select first from existng
            sel_rt.show();  //show current only
            $(sel_rt[0]).children(':first').children(':first').attr('checked', true);
            $("#idf_reuse").show();
        }else{ //select new field
            $("#rb_dt_new").attr('checked', true);
            $("#idf_reuse").hide();
        }
        
        //import -- id radiogroup
        $('span[class^="idfield_"]').hide(); //hide all
        $('span[class^="idfield_"]').children(':first').attr('checked', false); //uncheck all
        sel_rt = $('span[class^="idfield_'+rectype+'"]');
        
        if(sel_rt.length<1){
            //alert("There are no fields for selected record type defined as ID field");
        }else{
            sel_rt.show();  //show current only    
        }
        
        $("#recid_field").val('');

    }else{
        $("#div_idfield").hide();
    }
    
});

//Loads values for record from import table
getValues(0);

//init values for mapping form
if(!top.HEURIST.util.isnull(form_vals.sa_rectype)){
    select_rectype.val(form_vals.sa_rectype).change();
    
    //init import form
    for (var key in form_vals){
        if(key.indexOf('sa_dt_')==0 && form_vals[key]!=''){
            ($('#'+key).parent()).show();
            $('#'+key).val(form_vals[key]);
            //*$('#cb'+key).attr('checked', 'checked');
            document.getElementById('cb'+key).checked = true;
            //$('#cb'+key).parent().show();
            isbtnvis = true;
        }
    }
    onFtSelect(-1);
    
    //init matching form
    for (var key in form_vals){
        if(key.indexOf('sa_keyfield_')==0 && form_vals[key]!=''){
            $('#'+key).parent().show();
            $('#'+key).val(form_vals[key]);
            /*$('#cb'+key).attr('checked', 'checked');
            $('#cb'+key).attr('checked', 'true');
            $('#cb'+key).prop('checked', 'checked');
            $('#cb'+key).prop('checked', true);*/
            document.getElementById('cb'+key).checked = true;
            //$('#cb'+key).parent().show();
        }
    }    
    onFtSelect2(-1);
    
    //init id field radiogroup for matching
    if(form_vals["new_idfield"]){
        $("#new_idfield").val(form_vals["new_idfield"]); //name of new id field
        //$("#rb_dt_new").attr('checked', form_vals["idfield"]=="field_new");
    }
    var rb = $('input[id^="rb_dt_"][value="'+form_vals["idfield"]+'"]');
    if(rb.length>0){
        rb.attr('checked', true);
    }
    
    //init id fields for import
    rb = $('input[id^="recid_"][value="'+form_vals["recid"]+'"]');
    if(rb.length>0){
        rb.attr('checked', true);
    }
    $("#recid_field").val(form_vals["recid_field"]);
    if(form_vals["recid_field"]!=''){
        onRecIDselect(form_vals["recid_field"].substr(6));
        
        //TODO!!!! $(".analized").show();
    }
    
}

$( "#tabs_actions" ).tabs({active: form_vals.sa_mode, activate: function(event, ui){
        $("#sa_mode").val(ui.newTab.index());
        showUpdMode();    
  } });

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
    
    if( $("#sa_mode").val()=="1" ){
        $(".importing").show();   
        $(".matching").hide();   
    }else{
        $(".importing").hide();
        $(".matching").show();   
    }
    $(".analized").hide();
}

//
// show/hide checkbox on fieldtype select
//
function onFtSelect(ind){
    
    if($('select[id^="sa_dt_"][value!=""]').length>0){
        $('#btnStartImport').show();
    }else{
        $('#btnStartImport').hide();
    }
    
    if(ind>=0){
        $(".analized").hide();
    }
    
    return; 
    
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
// on matching field select
function onFtSelect2(ind){
    
    //var sels = $('select[id^="sa_keyfield_"]');
    
    if($('select[id^="sa_keyfield_"][value!=""]').length>0){
    //if( $( 'select[id^="sa_keyfield_"]' ).not( '[value=""]' ).length>0 ){  value!=""
        $('#btnStartMatch').show();
    }else{
        $('#btnStartMatch').hide();
    }
    
    if(ind>=0){
        $(".analized").hide();
    }
    
    return;
    
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
function showHideSelect(ind){
    $sel = $('#sa_dt_'+ind);
    $ch = $('#cbsa_dt_'+ind);
    if($ch.is(":checked")){
        $sel.parent().show();
    }else{
        $(".analized").hide();
        $sel.val('');
        $sel.parent().hide();
    }
}

function showHideSelect2(ind){
    $sel = $('#sa_keyfield_'+ind);
    $ch = $('#cbsa_keyfield_'+ind);
    if($ch.is(":checked")){
        $sel.parent().show();
    }else{
        $(".analized").hide();
        $sel.val('');
        $sel.parent().hide();
    }
}

//
// on RecordID selection - disable checkbox, set value for hidden recid_field
//
function onRecIDselect(ind){
    
    /*if($("#recid_field").val()!=''){
        //enable previous
        var ind2 = $("#recid_field").val().substr(6);
        $("#cbsa_dt_"+ind2).attr('disabled','');
    }*/
    
    $('input[id^="cbsa_dt_"]').attr('disabled',false); //enable all
    
    //disable for current
    $("#cbsa_dt_"+ind).attr('disabled',true); //'disabled');
    $("#cbsa_dt_"+ind).attr('checked', false);
    showHideSelect(ind);
    $("#recid_field").val('field_'+ind);
}

function onUpdateModeSet(event){
    
    if ($("#sa_upd2").is(":checked")) {
        $("#divImport2").css('display','inline-block');
    }else{
        $("#divImport2").css('display','none');
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
  if(currentTable && recCount>0){
    
    if(dest==0){
        currentId=1;
    }else if(dest==recCount){
        currentId=recCount;
    }else{
        currentId = currentId + dest;
    }
    if(currentId<1) {
        currentId=1;   
    }else if (currentId>recCount){
        currentId = recCount;
    }
    
                 $.ajax({
                         url: top.HEURIST.basePath+'import/delimited/importCSV.php',
                         type: "POST",
                         data: {recid: currentId, table:currentTable, db:currentDb},
                         dataType: "json",
                         cache: false,
                         error: function(jqXHR, textStatus, errorThrown ) {
                              //alert('Error connecting server. '+textStatus);
                         },
                         success: function( response, textStatus, jqXHR ){
                             if(response){
                                 
                                var i;
                                $("#currrow_0").html(response[0]);      
                                $("#currrow_1").html(response[0]);
                                for(i=1; i<response.length;i++){
                                    var isIdx = ($('input[id^="rb_dt_"][value="field_'+(i-1)+'"]').size()>0);
                                    var sval = response[i];
                                    if(isIdx && response[i]<0){
                                        sval = "&lt;New Record&gt;";
                                    }else if(sval==""){
                                        sval = "&nbsp;";
                                    }
                                    
                                    if($("#impval"+(i-1)).length>0)
                                        $("#impval"+(i-1)).html(sval);    
                                    if($("#impval_"+(i-1)).length>0)
                                        $("#impval_"+(i-1)).html(sval);  
                                }
                             }
                         }
                     });
  }
  return false;    
}

//
// onsubmit event listener - basic validation that at least one field is mapped
//
function verifySubmit()
{
    var rectype = $("#sa_rectype").val();
    if(rectype>0){
        
        if( $("#sa_mode").val()!="1"){ //matching

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