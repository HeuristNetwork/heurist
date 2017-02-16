
/*
* Copyright (C) 2005-2016 University of Sydney
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
* recordAddLink.js
* Adds link field or create relationship between 2 records
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/  
var sSourceName, sTargetName;

function onPageInit(success) //callback function of hAPI initialization
{
    
    if(success)  //system is inited
    {
        
        //source record 
        var source_ID = window.hWin.HEURIST4.util.getUrlParameter("source_ID", window.location.search);
        //destination record
        var target_ID = window.hWin.HEURIST4.util.getUrlParameter("target_ID", window.location.search);

        $('#btn_save').attr('title', 'explanatory rollover' ).button().on('click', function(){
            addLinks();
        });
        window.hWin.HEURIST4.util.setDisabled($('#btn_save'), true);
        $('#btn_cancel').button().on('click', function(){  window.close()});
        
        
        // find both records details
        var request = {q:'ids:'+source_ID+','+target_ID,w:'a',f:'detail'};
        window.hWin.HAPI4.RecordMgr.search(request, function(response){
            if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                var resdata = new hRecordSet(response.data);
        
                //add SELECT and fill it with values
                var idx, dty, rec_titles = [];
                var records = resdata.getRecords();
            
                // create list of 3 columns checkboxes, fieldname and relation type     
                var opposites = []; //recId:recTypeID
                
                var record = resdata.getById(source_ID);
                sSourceName = resdata.fld(record, 'rec_Title');
                var recRecTypeID = resdata.fld(record, 'rec_RecTypeID');

                rec_titles.push('<b>'+window.hWin.HEURIST4.rectypes.names[recRecTypeID]+'</b>');
                $('#rec0_title').html(
                    'Source ['+window.hWin.HEURIST4.rectypes.names[recRecTypeID]+'] <h2 style="padding-top:5px">'+sSourceName+'</h2>'
                );                                
                //recordsPair[recID] = recRecTypeID;
                opposites.push( { recID:source_ID, recRecTypeID:recRecTypeID} );

                record = resdata.getById(target_ID);
                sTargetName = resdata.fld(record, 'rec_Title');
                recRecTypeID = resdata.fld(record, 'rec_RecTypeID');

                rec_titles.push('<b>'+window.hWin.HEURIST4.rectypes.names[recRecTypeID]+'</b>');
                $('#rec1_title').html(
                    'Target ['+window.hWin.HEURIST4.rectypes.names[recRecTypeID]+'] <h2 style="padding-top:5px">'+sTargetName+'</h2>'
                );                                
                //recordsPair[recID] = recRecTypeID;
                opposites.push( { recID:target_ID, recRecTypeID:recRecTypeID} );
                    
                
                $('#rec_titles').html(rec_titles.join(' and '));
                $('#link_rec_edit').attr('href', window.hWin.HAPI4.baseURL
                                +'records/edit/editRecord.html?db='
                                +window.hWin.HAPI4.database+'&recID='+source_ID);
                
                var fi_name = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_DisplayName'], 
                    fi_constraints = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_PtrFilteredIDs'], 
                    fi_type = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex['dty_Type'],
                    fi_term =  window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_FilteredJsonTermIDTree'],
                    fi_term_dis =  window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_TermIDTreeNonSelectableIDs'],
                    hasSomeLinks = false;
                    
                var index = 0;
                            
                for(idx in opposites){
                    if(idx)
                    {
                        var recID  = opposites[idx]['recID'],
                            recRecTypeID = opposites[idx]['recRecTypeID'];

                        // get structures for both record types and filter out link and relation maker fields
                        for (dty in window.hWin.HEURIST4.rectypes.typedefs[recRecTypeID].dtFields) {
                            
                            var field_type = window.hWin.HEURIST4.detailtypes.typedefs[dty].commonFields[fi_type];
                            
                            if(!(field_type=='resource' || field_type=='relmarker')){
                                 continue;
                            }
                            
                            var details = window.hWin.HEURIST4.rectypes.typedefs[recRecTypeID].dtFields[dty];

                            //get name, contraints
                            var dtyName = details[fi_name];
                            var dtyPtrConstraints = details[fi_constraints];
                            var recTypeIds = null;
                            if(!window.hWin.HEURIST4.util.isempty(dtyPtrConstraints)){
                                recTypeIds = dtyPtrConstraints.split(',');
                            }
                            
                            var oppositeRecTypeID = opposites[index==0?1:0]['recRecTypeID'];
                            
                            //check if contraints satisfy to opposite record type
                            if( recTypeIds==null || recTypeIds.indexOf(oppositeRecTypeID)>=0 ){
                                
                                hasSomeLinks = true; //reset flag
                                
                                //get value of field and compare with opposite record id
                                var record = resdata.getById(recID);    
                                var values = resdata.values(record, dty);
                                var oppositeRecID = opposites[index==0?1:0]['recID'];
                                
                                //already linked for resource field type
                                var isAlready = (field_type=='resource') && $.isArray(values) && (values.indexOf(oppositeRecID)>=0);
                                
                                //add UI elements
                    $('<div style="line-height:2.5em;padding-left:20px"><input type="checkbox" id="cb'+recID+'_cb_'+dty+'" '
                    +(isAlready?'disabled checked="checked"'
                        :' class="cb_addlink"  data-recid="'
                            +recID+'" data-dtyid="'+dty+'" data-target="'+oppositeRecID+'" data-type="'+field_type+'"')
                    +' class="text ui-widget-content ui-corner-all"/>'                                     
                    +'<label style="font-style:italic" for="cb'+recID+'_cb_'+dty+'">'+dtyName+'</label>&nbsp;'
                    +'<select id="rec'+recID+'_sel_'+dty+'" class="text ui-widget-content ui-corner-all">'  
                        +'<option>relation type</option>'
                    +'</select><div>').appendTo($('#rec'+index));
                    
                                if(index==1){
                                    $('#rec1_hint').show();
                                }
                    
                    
                                if(field_type=='relmarker'){
                                    
                                    var terms = details[fi_term],
                                        terms_dis = details[fi_term_dis],
                                        currvalue = null;
                                    
                                    window.hWin.HEURIST4.ui.createTermSelectExt($('#rec'+recID+'_sel_'+dty).get(0), 
                                            'relation', terms, terms_dis, currvalue, null, false);    
                                }else{
                                    $('#rec'+recID+'_sel_'+dty).hide();
                                }
                            }
                            
                            
                        }//for fields
                        index++;
                    }
                }//for
                
                if(!hasSomeLinks){
                    
                    var rectypeID =(opposites[0]['recID']==source_ID)
                                        ?opposites[0]['recRecTypeID']
                                        :opposites[1]['recRecTypeID'];
                    
                    $('#link_structure_edit').click(function(){
                         window.open(window.hWin.HAPI4.baseURL+"/admin/adminMenuStandalone.php?db="+
                                window.hWin.HAPI4.database+
                                "&mode=rectype&rtID="+rectypeID, "_blank");
                    });                
                    
                    $('#btn_save').hide();
                    $('#mainForm').hide();
                    $('#infoForm').show();
                }else{    
                    $('.cb_addlink').click(function(){
                        window.hWin.HEURIST4.util.setDisabled($('#btn_save'), !$('.cb_addlink').is(':checked'));
                    });
                }

            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
            
            
        });
        
        
        
        
        
        
        
        

    }
}

/**
*  main function 
* 
*/
function addLinks(){


    /*       recIDs - list of records IDS to be processed
    *       rtyID  - filter by record type
    *       dtyID  - detail field to be added
    *       for addition: val: | geo: | ulfID: - value to be added
    *       for edit sVal - search value,  rVal - replace value
    *       for delete: sVal  
    *       tag  = 0|1  - add system tag to mark processed records
    */
    
    var RT_RELATION = window.hWin.HAPI4.sysinfo['dbconst']['RT_RELATION'], //1
        DT_TARGET_RESOURCE = window.hWin.HAPI4.sysinfo['dbconst']['DT_TARGET_RESOURCE'], //5
        DT_RELATION_TYPE = window.hWin.HAPI4.sysinfo['dbconst']['DT_RELATION_TYPE'], //6
        DT_PRIMARY_RESOURCE = window.hWin.HAPI4.sysinfo['dbconst']['DT_PRIMARY_RESOURCE']; //7
    
    var requests = []
    
    $('.cb_addlink').each(function(index, ele){

        if($(ele).is(':checked')){

            if($(ele).attr('data-type')=='resource'){
                requests.push({a: 'add',
                    recIDs: $(ele).attr('data-recid'),
                    dtyID:  $(ele).attr('data-dtyid'),
                    val:    $(ele).attr('data-target')});
            }else{ //relmarker
            
                var recID = $(ele).attr('data-recid'),
                    dtyID = $(ele).attr('data-dtyid'),
                    termID = $('#rec'+recID+'_sel_'+dtyID).val(),
                    sRelation = $('#rec'+recID+'_sel_'+dtyID).find('option:selected').text();
            
                var details = {};
                details['t:'+DT_PRIMARY_RESOURCE] = [ recID ];
                details['t:'+DT_RELATION_TYPE] = [ termID ];
                details['t:'+DT_TARGET_RESOURCE] = [$(ele).attr('data-target')];
                
                requests.push({a: 'save',    //add new relationship record
                    
                    ID:-1, //new record
                    RecTypeID: RT_RELATION,
                    RecTitle: 'Relationship ('+sSourceName+' '+sRelation+' '+sTargetName+')',
                    details: details });

            }
        }

        }
    );//each
    
    addLinkOrRelation(0, requests);
    
}

//
// individual action
//
function addLinkOrRelation(idx, requests){

    if(idx<requests.length){

        var request = requests[idx];
        
        var hWin = window.hWin;
        
        function __callBack(response){
                if(response.status == hWin.HAPI4.ResponseStatus.OK){
                    idx = idx + 1;
                    addLinkOrRelation(idx, requests);
                }else{
                    hWin.HEURIST4.msg.showMsgErr(response);
                }
        }        
    
        if(request.a=='add'){
            window.hWin.HAPI4.RecordMgr.details(request, __callBack);
        }else{
            window.hWin.HAPI4.RecordMgr.save(request, __callBack);
        }
    }else if(requests.length>0){
        window.close('Link'+(requests.length>1?'s':'')+' created...');
    }
}