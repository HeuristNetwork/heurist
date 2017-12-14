/**
* importCSV.js: UI for delimeted data import
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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

$(document).ready( init );

function init() {

    $("#div-progress").hide();

    var _dialogbox;

    var select_rectype = $("#sa_rectype");
    createRectypeSelect( select_rectype.get(0), null, 'select...' );

    var allowed = Object.keys(top.HEURIST.detailTypes.lookups);
    allowed.splice(allowed.indexOf("separator"),1);
    //allowed.splice(allowed.indexOf("relmarker"),1);
    allowed.splice(allowed.indexOf("file"),1);
    allowed.splice(allowed.indexOf("geo"),1);
    //
    document.title = "Import Records from "+currentSessionName;

    //change title in parent dialog
    if(window.frameElement){
    var reference_to_parent_dialog = window.frameElement.getAttribute('parent-dlg-id');
    if( reference_to_parent_dialog ){
        var ele = parent.document.getElementById(reference_to_parent_dialog);
        $(ele.parentElement).find('.ui-dialog-title').text( 'Import Records from '+currentSessionName );
        //dialog( "option", "title", 'Import Records from '+currentSessionName );
    }
    }

    select_rectype.change(function (event){

        var sel = event && event.target;
        if(!sel) return;

        var rectype = Number(sel.value);
        //fill detailtypes selects for import
        var select_fieldtype = $('select[id^="sa_dt_"]');
        select_fieldtype.each(function() {

            var allowed_ft = allowed.slice(0);
            allowed_ft.push("geo");
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

        //$('#btnStartMatch').hide();
        $('#btnStartMatch').attr("disabled", "disabled");
        $('#btnStartMatch').css('color','#CCC');
        $('#btnStartImport').attr("disabled", "disabled");// .hide();
        $('#btnStartImport').css('color','#CCC');

        //id field
        if(rectype){
            $('#btnSkipMatch').removeAttr("disabled");
            $('#btnSkipMatch').css('color','#666');

            $("#div_idfield").show();
            $('input[id^="cbsa_dt_"]').parent().show();
            $('input[id^="cbsa_keyfield_"]').parent().show();

            $("#idfield").val(top.HEURIST.rectypes.names[rectype]+' ID');

            /* OLD VERSION - FULLY WORK
            //ID field
            //uncheck id field radiogroup - matching
            $("#idfield").val('');

            //matching -- id fields
            $('option[class^="idfield2_"]').hide(); //hide all
            var sel_rt = $('option[class="idfield2_'+rectype+'"]');
            sel_rt.show(); //show for this rt only

            var sval = rectype? (top.HEURIST.rectypes.names[rectype]+' ID'+(sel_rt.length>0?sel_rt.length+1:'')) :'';
            $("#new_idfield").val(sval);
            $("#new_idfield2").val(sval);

            if(sel_rt.length>0){
            $("#rb_idfield1").attr("checked", true); //ART new
            onExistingIDfieldSelect();
            $("#idfield").val(sel_rt[0].value);

            $('#div_idfield_exist').show();
            $('#div_idfield_new').hide();
            $("#span2").html('"'+sel.options[sel.selectedIndex].text+'"');
            if(sel_rt.length==1){
            $("#span1").html('"'+sel_rt.html()+'"  as a field name');
            $("#span3").html('this field name');
            }else{
            $("#span1").html(sel_rt.length+' columns');
            $("#span3").html('one of existing columns');
            }
            }else{
            $('#div_idfield_exist').hide();
            $('#div_idfield_new').show();
            }*/
            //---------------------

            //import -- id radiogroup
            $('option[class^="idfield_"]').hide(); //hide all
            sel_rt = $('option[class^="idfield_'+rectype+'"]');
            sel_rt.show(); //show for this rt only
            if(sel_rt.length>0)
                $('#idfield_separator').show();
            else
                $('#idfield_separator').hide();


            /*
            $('span[class^="idfield_"]').hide(); //hide all
            $('span[class^="idfield_"]').children(':first').attr('checked', false); //uncheck all
            sel_rt = $('span[class^="idfield_'+rectype+'"]');

            if(sel_rt.length<1){
            //alert("There are no fields defined as ID field for the selected record type ");
            }else{
            sel_rt.show();  //show current only
            }*/


            $("#recid_field").val('');

        }else{
            $("#div_idfield").hide();
            $('#btnSkipMatch').attr("disabled", "disabled");
            $('#btnSkipMatch').css('color','#CCC');
        }
        $(".analized2").hide();  //hide after rectype change
        if ($("#divPreviousBtn").is(":visible")) $(".analized3").show();


    }); //end record type change

    //Loads values for record from import table
    getValues(0);
    var isbtnvis = false;

    //init values for mapping form
    if(!top.HEURIST.util.isnull(form_vals.sa_rectype)){

        //alert("reinit "+form_vals.sa_rectype);

        select_rectype.val(form_vals.sa_rectype).change();

        //init import form
        for (var key in form_vals){
            if((key.indexOf('sa_dt_')==0 && form_vals[key]!='')
                || (key.indexOf('sa_keyfield_')==0 && form_vals[key]!=''))
            {
                var fieldname = form_vals[key];
                if(key.indexOf('sa_keyfield_')==0){
                    key = 'sa_dt_'+key.substr(12);
                }

                ($('#'+key).parent()).show();
                $('#'+key).val(fieldname);   //select
                //$('#cb'+key).attr('checked', 'checked');
                var cb = document.getElementById('cb'+key);  //checkbox
                if(cb) cb.checked = true;
                //$('#cb'+key).parent().show();
                isbtnvis = true;
                //ART 0307  - switch to import automatically ???
                // form_vals.sa_mode = 1;
            }
        }
        onFtSelect(-1);

        if(form_vals.auto_switch_to_import!=1){

            //init matching form
            for (var key in form_vals){
                if(key.indexOf('sa_keyfield_')==0 && form_vals[key]!=''){
                    $('#'+key).parent().show();
                    $('#'+key).val(form_vals[key]);
                    var cb = document.getElementById('cb'+key);
                    if(cb) cb.checked = true;
                    /*$('#cb'+key).attr('checked', 'checked');
                    $('#cb'+key).attr('checked', 'true');
                    $('#cb'+key).prop('checked', 'checked');
                    $('#cb'+key).prop('checked', true);*/
                    //$('#cb'+key).parent().show();
                }
            }
            onFtSelect2(-1);

        }


        //init id field radiogroup for matching
        if(form_vals["idfield"]){
            $("#idfield").val(form_vals["idfield"]);
        }

        /* OLD VERION FULL WORK
        if(form_vals["idfield"]){
        $("#rb_idfield1").attr("checked", true);
        onExistingIDfieldSelect();
        $("#idfield").val(form_vals["idfield"]);
        }else{
        if(form_vals["new_idfield"]){
        var sel_rt = $('option[class="idfield2_'+form_vals.sa_rectype+'"]');
        sel_rt = sel_rt.filter(function () { return $(this).html() == form_vals["new_idfield"]; });
        if(sel_rt.length==1){
        $("#rb_idfield1").attr("checked", true);
        onExistingIDfieldSelect();
        $("#idfield").val(sel_rt[0].value);
        }else{
        $("#new_idfield").val(form_vals["new_idfield"]); //name of new id field
        $("#new_idfield2").val(form_vals["new_idfield"]); //name of new id field
        }
        }
        }
        */
        //init id fields for import
        var id_field = '';
        var id_field_name = $("#idfield").val();
        if(!top.HEURIST.util.isempty(form_vals["recid_field"])){
            id_field = form_vals["recid_field"];
        }else if(!top.HEURIST.util.isempty( id_field_name )){ //find by name
            id_field = $('#recid_field > option:contains("' + id_field_name + '")').val();
        }
        //alert("ID:"+id_field+'  1:'+form_vals["recid_field"]+' 2:'+$("#idfield").val());
        $("#recid_field").val(id_field);
        if(id_field !=''){
            onRecIDselect2(); //(form_vals["recid_field"].substr(6));
            //TODO!!!! $(".analized").show();
        }

    }

    $( "#tabs_actions" ).tabs({active: form_vals.sa_mode, activate: function(event, ui){
        showUpdMode(ui.newTab.index());
    } });
    $( "#tabs_actions ul.ui-tabs-nav" ).hide();


    if(form_vals.auto_switch_to_import==1){
        $( "#tabs_actions" ).tabs( "option", "active", 1 );
    }else{
        showUpdMode();
    }

    $(".analized2").show();
    $(".analized3").hide();

    if(form_vals['error_message']){
        if(form_vals['error_message'].indexOf('Mapping')===0){

            $("#btnMatchCancel").click(function(){
                if(_dialogbox) top.HEURIST.util.closePopup(_dialogbox.id);
                _dialogbox = null;
            });
            $("#btnMatchProceed").click(function(){
                $("#ignore_insert").val(1);
                $("#input_step").val(2);
                hideThisFrame();
                document.forms[0].submit();
                if(_dialogbox) top.HEURIST.util.closePopup(_dialogbox.id);
                _dialogbox = null;
            });

            $("#btnUnMatchDownload").click(function(){
                var url = 'importCSV.php/import.csv?db='+currentDb+'&getsession='+$('#import_id').val()+'&mode=1&idfield='+$("#recid_field").val();
                window.open(url,'_blank')
            });

            var divUnmatchedRes = $("#divUnmatchedRes");
            var divUnmatchedBtns = $("#divUnmatchedBtns");
            $("#btnUnMatchDelete").click(function(){

                divUnmatchedRes.html('Deleting unmatched records...');
                divUnmatchedBtns.hide();

                $.ajax({
                    url: top.HEURIST.baseURL+'import/delimited/importCSV.php',
                    type: "POST",
                    data: {deleteunmatched: $('#import_id').val(), idfield:$("#recid_field").val(), db:currentDb},
                    dataType: "text",
                    cache: false,
                    error: function(jqXHR, textStatus, errorThrown ) {
                        //alert('Error connecting server. '+textStatus);
                    },
                    success: function( response, textStatus, jqXHR ){
                        if(response){
                            if(response>0){
                                divUnmatchedRes.html('Removed '+response+' recrods');
                                divUnmatchedBtns.hide();
                            }else{
                                divUnmatchedRes.html(response);
                                divUnmatchedBtns.show();
                            }
                        }
                    }
                });

            });


            _dialogbox = top.HEURIST.util.popupElement(top.window, document.getElementById('divMatchingPopup'),
                {title:'Missed required fields',height:400,width:800});
        }else{
            alert(form_vals['error_message']);
        }
    }

    //form_vals = null;

} //end init function

//
// Update progress counter for record update/insert
//
function update_counts(processed, added, updated, total){
    $("#div-progress2").html("Added: "+added+" Updated:"+updated+". Processed "+processed+" records for "+total+" input rows");
}

function doMatchingAfterDisambig(){

    if(currDialog!=null){
        currDialog.dialog('close');
        currDialog = null;
    }
    
    doMatching();
}

//
// Start search/matching
//
function doMatching(){

    //detect if this is multivalue matching
    var cb_keyfields1 = $('input[id^="cbsa_keyfield_"]:checked');
    var cb_keyfields2 = $('input[id^="cbsa_keyfield_"][multivalue="yes"]:checked');
    if(cb_keyfields1.length==0){
        alert('You must select one or more KEY fields for matching');
    }else if(cb_keyfields2.length>1){
        //alert('You selected more than one Key field with multivalues (values separated by |). This field can be the only ONE. Unselect other fields');
        //alert('You selected Key field with multivalues (values separated by |). This field can be the only ONE. Unselect other fields');
        var lst = [];
        cb_keyfields2.each(function(){
            lst.push($(this).attr('column'));
            //lst.push($("#sa_key"+id).children("option").is("selected").text());
        });


        alert('You have selected more than one multi-value column ('+lst.join(',')+')\r\n'+
            'You may only have one multi-value column in your key columns, since there is no way to work out the correct '+
            'combination of multiple values across multiple columns. Please rationalise your data to allow matching with zero or one multi-value key fields.');

    }else{

        var r = true;
        if(cb_keyfields2.length>0){
            r = true;
            /* 2016-02-01 hide this warning since all id fields are considered as mutlivalue
            r = confirm('You have a multi-value column ('+cb_keyfields2.attr('column')+
                ') in your key columns. The record identifier column created by this matching process will have multiple values '+
                'corresponding with the number of values in this column for each record.\r\n\r\n'+
                'When used as the key field in the insert/update step, a record will be created for each value. '+
                'When used as a data field, the data field will have repeated values in the record.');
            */    
            if(r){
                $("#mvm").val(1);
                $("#multifield").val(cb_keyfields2.val());
            }else{
                return;
            }
        }

        //warn if id field already exists
        var idfield = $("#idfield").val();
        if(form_vals["idfield"]!=idfield && $('input[id^="cbsa_keyfield_"][column="'+idfield+'"]').length>0){
            r = confirm('The specified ID field "'+idfield+'" has already been created. Its values will be overwritten. Please confirm');
        }

        if(r){
            doDatabaseUpdate(0,0);
        }

    }
}
//
// Start import OR records IDs assign
//
function doDatabaseUpdate(cnt_insert_nonexist_id, cnt_errors){

    var r = true;
    if(cnt_insert_nonexist_id>0){
        r = confirm("Your input data contains "+cnt_insert_nonexist_id+" rows with record IDs in the selected ID column which "+
            "do not exist in the database. Do you want to proceed and create new records with these specific IDs?")
    }
    if(r && cnt_errors>0){
        r = confirm("There are errors in the data. It is better to fix these in the source file and then "+
            "process it again, as uploading faulty data generally leads to major fix-up work. "+
            "Are you sure you want to proceed?");
        //"\r\n"+
        //"Temporary workaround: You may ignore the errors reported for your multi-value field(s) provided you are sure that your data contains valid terms.\r\n"+
        //"BEWARE: Any invalid / unmatched terms in multi-value fields will not be imported"
    }
    if(r){
        $("#input_step").val(3); //start real import
        hideThisFrame();
        document.forms[0].submit();
    }
}

//
//
//
function onExistingIDfieldSelect(){
    //var sel_rt = $('option[class^="idfield2_"]').filter(':visible'); //do not work
    var rectype = $("#sa_rectype").val();
    var sel_rt = $('option[class="idfield2_'+rectype+'"]');
    if(sel_rt.length>1){//more than 1 field defined for record type
        $('#rb_idfield1_div').show();
    }
    $('#rb_idfield0_div').hide();

    var sel = $('#idfield');
    if(sel[0].selectedIndex==0){
        sel.val(sel_rt[0].value);
    }
}

//
// switch modes
//
function showUpdMode(newval){

    if(!top.HEURIST.util.isnull(newval)){
        $("#sa_mode").val(newval);
    }

    if( $("#sa_mode").val()=="1" ){   // import
        $(".importing").show();
        $(".matching").hide();
    }else{                            // matching
        $(".importing").hide();
        $(".matching").show();

        //reset everything
        if(newval===0)
            $("#sa_rectype").val('').change();

    }
    $(".analized").hide();
}

//
// show/hide checkbox on fieldtype select
//
function onFtSelect(ind){

    var isok = false;
    var cb_keyfields = $('input[id^="cbsa_dt_"]:checked');
    if(cb_keyfields.length>0){
        isok = true;
        cb_keyfields.each(function(){
            isok  = isok && ($('select[id="'+this.id.substr(2)+'"]').val()!="");
        });
    }
    if(isok){
        $('#btnStartImport').removeAttr("disabled"); //.show();
        $('#btnStartImport').css('color','#666');
    }else{
        $('#btnStartImport').attr("disabled", "disabled"); //.hide();
        $('#btnStartImport').css('color','#CCC');
    }

    /*if($('select[id^="sa_dt_"][value!=""]').length>0){
    $('#btnStartImport').show();
    }else{
    $('#btnStartImport').hide();
    }*/

    if(ind>=0){
        $(".analized2").hide();
        if ($("#divPreviousBtn").is(":visible")) $(".analized3").show();
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

    var isok = false;
    var cb_keyfields = $('input[id^="cbsa_keyfield_"]:checked');
    if(cb_keyfields.length>0){
        isok = true;
        cb_keyfields.each(function(){
            isok  = isok && ($('select[id="'+this.id.substr(2)+'"]').val()!="");
        });
    }

    if(isok){
        $('#btnStartMatch').removeAttr("disabled"); //.show();
        $('#btnStartMatch').css('color','#666');
    }else{
        $('#btnStartMatch').attr("disabled", "disabled");
        $('#btnStartMatch').css('color','#CCC');
    }
    /*
    if(isok){
        $('#btnStartMatch').show();
    }else{
        $('#btnStartMatch').hide();
    }*/

    /*
    if($('select[id^="sa_keyfield_"][value!=""]').length>0){
    $('#btnStartMatch').show();
    }else{
    $('#btnStartMatch').hide();
    }*/

    if(ind>=0){
        $(".analized2").hide();
        if ($("#divPreviousBtn").is(":visible")) $(".analized3").show();
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
        $sel.val('');
        $sel.parent().hide();
    }
    onFtSelect(ind);
}

function showHideSelect2(ind){
    $sel = $('#sa_keyfield_'+ind);
    $ch = $('#cbsa_keyfield_'+ind);
    if($ch.is(":checked")){
        $sel.parent().show();
    }else{
        $sel.val('');
        $sel.parent().hide();
    }
    onFtSelect2(ind);
}

//
//
//
function onRecIDselect2(){
    var fld = $("#recid_field").val();
    if(fld){
        var ind = fld.substr(6);
        onRecIDselect(ind);
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
    if(ind>=0){

        //disable for current
        $("#cbsa_dt_"+ind).attr('disabled',true); //'disabled');
        $("#cbsa_dt_"+ind).attr('checked', false);
        showHideSelect(ind);
        //$("#recid_field").val('field_'+ind);
    }
}

function onUpdateModeSet(event){

    if ($("#sa_upd2").is(":checked")) {
        $("#divImport2").css('display','block');
    }else{
        $("#divImport2").css('display','none');
    }
}

var currDialog = null;

//
// Show error, matched or new records
//
function showRecords(divname){

    if (divname=='mapping'){
        if(currDialog!=null){
            currDialog.dialog('close');
        }
    }else{

        var $dlg = $('div[id="main_'+divname+'"]');
        //keep            
        var element = $dlg[0];
        var originalParentNode = element.parentNode;
        //element.parentNode.removeChild(element);

        var options = {
                    autoOpen: true,
                    height: 550,
                    width: 800,
                    modal: true,
                    resizable: false,
                    draggable: false,
                    title: 'Import delimited',
                    close: function(event, ui){

                        //var element = popup.element.parentNode.removeChild(popup.element);
                        element.style.display = "none";
                        originalParentNode.appendChild(element);

                        currDialog = null; //$dlg.remove();
                    }
        };
                    

        $dlg.dialog(options);
        //$dlg.dialog('open');
        currDialog = $dlg;
    
    }

    //$('div[id^="main_"]').hide();
    //$('div[id="main_'+divname+'"]').show();

    if(divname=='error'){
            if($( "#tabs_records" ).length>0){
            $( "#tabs_records" ).tabs();
        }
    }

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
            url: top.HEURIST.baseURL+'import/delimited/importCSV.php',
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
                        if(top.HEURIST.util.isnull(response[i])){
                            sval = "&nbsp;";
                        }else{

                            var isIdx = ($('option[class^="idfield2_"][value="field_'+(i-1)+'"]').size()>0);
                            var sval = response[i].substr(0,100);

                            if(isIdx && response[i]<0){
                                sval = "&lt;New Record&gt;";
                            }else if(sval==""){
                                sval = "&nbsp;";
                            }else if(response[i].length>100){
                                sval = sval + '&#8230;';
                            }
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
    
    var res = false;    
    var rectype = $("#sa_rectype").val();
    if(rectype>0){

        if( $("#sa_mode").val()!="1"){ //matching

            var cb_keyfields = $('input[id^="cbsa_keyfield_"]:checked');
            if(cb_keyfields.length>0){

                if ( ($("#div_idfield_new").is(":visible") && $("#new_idfield").val()=='')
                    ||
                    ($("#div_idfield_exist").is(":visible") && $("#rb_idfield0").is(":checked") && $("#new_idfield").val()=='')
                ){

                    alert("To search/match records you must define New column to hold record identifiers");

                }else if($("#div_idfield_exist").is(":visible") && $("#idfield").val()==''){

                    alert("If you wish to redo the matching, select column for ID field");

                }else {
                    res = true;
                }

            }else{
                alert("To search/match records you must select at least one key field in import data");
            }



        }else{ //importing

            var select_fieldtype = $('select[id^="sa_dt_"][value!=""]');
            if(select_fieldtype.length>0){
                res = true;
            }else{
                alert("You need to map import data to record's fields. Please select at least one field type.");
            }
        }

    }else{
        alert("Select record type!");
    }

    if(res){
        hideThisFrame();
    }
    
    return res;
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

                        var label = name+' ['+ top.HEURIST.detailTypes.lookups[details[dtyID][fit]]+']';

                        arrterm.push([dtyID, label, (details[dtyID][fir]=="required") ]);

                        if(details[dtyID][fit]=="geo"){
                            arrterm.push([ dtyID+'_long', label+' Longitude', false ]);
                            arrterm.push([ dtyID+'_lat', label+' Latitude', false ]);
                        }

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

//
// add term list preview for error term field
//
function showTermListPreview(dty_ID){

    var td = top.HEURIST.detailTypes.typedefs[dty_ID];
    if(td) {
        var deftype = td.commonFields;
        var fi = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex;
        var _currentDomain = (deftype[fi["dty_Type"]]=="enum") ?"enum":"relation";

        var parentdiv = document.getElementById('termspreview'+dty_ID);
        parentdiv.innerHTML = '';

        var el_sel = top.HEURIST.util.createTermSelect(deftype[fi.dty_JsonTermIDTree], deftype[fi.dty_TermIDTreeNonSelectableIDs], _currentDomain, null);
        el_sel.style.backgroundColor = "#cccccc";
        el_sel.width = 180;
        el_sel.style.maxWidth = '180px';
        el_sel.onchange =  function(event){ event.target.selectedIndex=0; };
        parentdiv.appendChild(el_sel);
    }
}

