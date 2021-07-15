
/**
* selectTectype.js : select the record/entity types to be used in writing the FAIMS tablet app configuration
*                    Selection of record types as the primary entry points also selects any dependent record types required
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

    var Hul = top.HEURIST.util;

    //show list of selected rectypes    
    function showSelectedRecTypes(){
        
        var $mdiv = $("#selectedRectypes");
        $mdiv.empty();
        var grp = top.HEURIST.rectypes.groups;
        
                var newvalue = "", txt="",
                    ind, dtName, rtID, grpID, hasSubnames;
                   
                var $table_div = $("<div>").css({'width':'100%','overflow-y':'auto'});      //'max-height':'350px',
                var $table =$("<table>").appendTo($table_div);
                
                $("<tr>").css('text-align','left').html("<th>Entity&nbsp;&nbsp;</th><th>Form&nbsp;&nbsp;</th><th>Entity/Record type [references]</th>").appendTo($table);
                
                            
                       for (grpID in grp) {
                            if(!isNaN(grpID)){
                                dtName = top.HEURIST.rectypes.groups[grpID].name;
                                arr =  top.HEURIST.rectypes.groups[grpID].showTypes;
                                if(!Hul.isnull(dtName) && arr.length>0){
                                    
                                    //find dependent recordtypes
                                     var $tr =$("<tr>")
                                        .append($("<td>").attr('colspan','2'))
                                        .append($("<td>").css({'font-weight':'bold', 'font-style':'normal', 'font-size':'1.3em', padding:'10px 0px 3px 0px'}).text(dtName));
                                     $tr.appendTo($table);

                                     var i=0, idx;                                     
                                     for (idx in arr) {
                                         rtID = arr[idx];
                                         if(!isNaN(rtID)){
                                         
                                            /*if(i>0){
                                               $tr =$("<tr>").append($("<td>").text(" "));
                                            }*/     

                                            $tr =$("<tr>");
                                            
                                            if(top.HEURIST.rectypes['typedefs'][rtID]){

                                                    //subdependent
                                                    var deprts2 = findDependentRecordTypes(rtID);
                                            
                                                    $tr.append($("<td>").css('text-align','center').append("<input type='checkbox' id='crt"+rtID+"' name='crt[]' value='"+rtID+"' depended='"+deprts2.join(",")
                                                                        +"' onclick='onRtCheckBox(this);'>"))
                                                      .append($("<td>").css({'text-align':'center'})
                                                                    .append("<input type='checkbox' id='fff"+rtID+"' disabled=disabled' onclick='{return false;}'>")
                                                                    .append("<input type='checkbox' id='frt"+rtID+"' value='"+rtID+"' style='display:none;' name='frt[]'>"));
                                                    
                                                    dtName = "<b>"+top.HEURIST.rectypes.names[rtID]+"</b>"; 
                                            
                                                    hasSubnames=0;
                                                    var subnames = [];
                                                    for (j=0;j<deprts2.length;j++){
                                                        subnames.push(top.HEURIST.rectypes.names[deprts2[j]]);
                                                    }
                                                    if(subnames.length>0){
                                                       dtName = dtName + " &nbsp;&nbsp; [" + subnames.join(", "); 
                                                       hasSubnames = 1;   
                                                    }
                                            
                                            }else{
                                                $tr.append($("<td>",{'colspan':2}).html('rt#'+rtID));
                                                dtName = " not found";
                                            }
                                            
                                            if (hasSubnames) {closeBracket=']'} else {closeBracket=''};
                                            $tr.append($("<td>").css({'font-weight':'normal', 'font-style':'normal', 'font-size':'0.8em'}).append(dtName).append(closeBracket));
                                            $tr.appendTo($table);
                                            
                                            i++;
                                         }
                                     }//for rectypes
                                }
                            }
                        } //for

                $mdiv.append($("<p>").css('width','600px').append($("<i>").html('<b>Please select ONLY entity (record) types below which you want represented as an entry point on the first screen</b><br />'+
                    'Any entity which is referenced by a pointer or relationship marker within these entity types (eg. a context within a site, '+
                    'an artefact, sample or feature within a context) is indicated between [ ] and will be included automatically in the appropriate locations. The Form column '+
                    'shows whether a data entry form will be included in the app.')));
                        
                $mdiv.append($table_div);                
                $mdiv.append($("<p>").append($("<i>").html('Select additional top level tab groups for your app:')));                

                // We always require the control tab and start/stop synch as the app hangs inconveniently when synching, so you need to be able to turn it off
                $("<div>").css('font-weight','bold').append("<input type='checkbox' checked='checked' disabled onclick='onCtCheckBox(event)' id='mainct'>").append("<label for='mainct'>Control tab<label>").appendTo($mdiv);
                $("<div>").css('padding-left','40px').append("<input type='checkbox' checked='checked' id='ct1' name='ct1' value='1'><label for='ct1'>Start synching on start<label>").appendTo($mdiv);
                
                $("<div>").css('padding-left','40px').append("<input type='checkbox' checked='checked' id='ct2' name='ct2' value='1'><label for='ct2'>Start Internal GPS on start<label>").appendTo($mdiv);
                $("<div>").css('padding-left','40px').append("<input type='checkbox' id='ct3'><label for='ct3' name='ct3' value='1'>Connect to External GPS (leave unchecked if no external GPS)<label>").appendTo($mdiv);

                /* Feb 2014: Brian says tracklog requires extra logic which is not yet available, so this option has been hidden - code exists in exportFAIMS.php to write appropriate logic in bsh file */
                $("<div>").css('padding-left','40px').css('display','none').append("<input type='checkbox' id='ct4' name='ct4' value='1'><label for='ct4'>Switch tracklog on/off (tracklog unavailable if not checked)<label>").appendTo($mdiv);

                $("<br>").appendTo($mdiv);
                

                $("<div>").css('font-weight','bold').append("<input type='checkbox' id='mainmt' name='mainmt' value='1' checked='checked' onclick='onMtCheckBox(event)'><label for='mainmt'>Map tab<label>").appendTo($mdiv);
                if(map_records.length>0){
                     var k=0;
                     for (; k<map_records.length; k++) {
                            //map_records[k]['rec_RecTypeID']
                            var sname = "mt"+map_records[k]['rec_ID'];
                            $("<div>").css('padding-left','40px').append("<input type='checkbox' id='"+sname+"' name='mt[]' value='"+map_records[k]['rec_ID']+"'><label for='"+sname+"'> "+
                                        map_records[k]['rec_Title']+
                                        "  ["+top.HEURIST.rectypes.names[map_records[k]['rec_RecTypeID']]+"]<label>").appendTo($mdiv);
                            
                     }
                } else {
                    $("<div>").css('padding-left','40px').css('padding-bottom','20px').append("No map layers in database. You may add layers manually before loading module in FAIMS server.").appendTo($mdiv);                    
                }

//                $("<div>").css('font-weight','bold').append("<input type='checkbox' id='ct5' name='ct5' value='1' checked='checked' ><label for='ct5'>Add Certainty and Annotation to constrained data (vocabs)<label>").appendTo($mdiv);
                        
            //$("#rt_selected").val(recordTypesSelected);
            $("#buttondiv").css('display','block');                        
        
    }

    //returns array of recordtypes dependent on given (constains in resopurces and relmarkers)    
    function findDependentRecordTypes(rtID){
        
        var idx_rst_dt_type  = top.HEURIST.rectypes['typedefs']['dtFieldNamesToIndex']["dty_Type"];
        var idx_rst_pointers = top.HEURIST.rectypes['typedefs']['dtFieldNamesToIndex']["rst_PtrFilteredIDs"];
        var rst_fields = top.HEURIST.rectypes['typedefs'][rtID]['dtFields'];
        var res = [];
        
        for (idx in rst_fields) {
            if(Hul.isNumber(idx)){
                var field = rst_fields[idx];
                var dt_type = field[idx_rst_dt_type];
                if(dt_type=="relmarker" || dt_type=="resource"){
                    var dt_pointers = field[idx_rst_pointers];
                    if(!Hul.isempty(dt_pointers)){
                        var ids = dt_pointers.split(",");
                        res = res.concat(ids);
                    }
                }
            }
        }//for
        
        return res;
    }//end findDependentRecordTypes
      
    function onRtCheckBox(args){
        
        var deps = [];
        //find all crt checkboxes
        $('input[id^="crt"]').each(function(ind){
            if($(this).is(":checked")){
                var dep = $(this).attr('depended')
                dep = dep.split(",");
                dep.push( this.id.substring(3) );
                //gather all dependent rectypes
                deps = deps.concat(dep);
            }
        });
        
        //check frt that are in dependent list
        $('input[id^="fff"]').each(function(ind){
              var rtid = this.id.substring(3);
              var ischecked = deps.indexOf( rtid )>=0;
              $(this).attr('checked', ischecked);
              $('#frt'+rtid).attr('checked', ischecked); //.val(ischecked?rtid:'');
        });
        
/*        
        var ele = args[0];
        var rtid = ele.id;
        $("#frt"+rtid.substring(3)).attr('checked', ele.checked);
        $.each(args, function( index, value ) {
            if(index>0)
                $("#frt"+value).attr('checked', ele.checked);
        });
*/        
    }
    
    function onCtCheckBox(event){
        
        var ischecked = event.target.checked;
        
        $('input[id^="ct"]').each(function(ind){
            if(ischecked){
                $(this).removeAttr('disabled');  
            }else{
                $(this).attr('disabled', 'disabled');  
            }
              
        });
    }
        
    function onMtCheckBox(event){
        
        var ischecked = event.target.checked;
        
        $('input[id^="mt"]').each(function(ind){
            if(ischecked){
                $(this).removeAttr('disabled');  
            }else{
                $(this).attr('disabled', 'disabled');  
            }
              
        });
    }
    
    
    function validateForm(){
        var n = $('input[id^="crt"]:checked').length;
        if(n<1){
            alert('Please select at least one entity type');
            return false;            
        }else{
            document.forms['startform'].submit();
        }
    }
    
    
