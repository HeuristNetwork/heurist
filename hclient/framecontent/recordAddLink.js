
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
function onPageInit(success) //callback function of hAPI initialization
{
    
    if(success)  //system is inited
    {
        
        //source record 
        var source_ID = window.hWin.HEURIST4.util.getUrlParameter("source_ID", window.location.search);
        //destination record
        var target_ID = window.hWin.HEURIST4.util.getUrlParameter("target_ID", window.location.search);

        $('#btn_save').attr('title', 'explanatory rollover' ).button().on('click', 3, addLinks);
        $('#btn_cancel').button().on('click', function(){  window.close()});
        
        
        // find both records details
        var request = {q:'ids:'+source_ID+','+target_ID,w:'a',f:'detail'};
        window.hWin.HAPI4.RecordMgr.search(request, function(response){
            if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                var resdata = new hRecordSet(response.data);
        
                //add SELECT and fill it with values
                var idx, dty, index = 0, rec_titles = [];
                var records = resdata.getRecords();
            
                // create list of 3 columns checkboxes, fieldname and relation type     
                var opposites = []; //recId:recTypeID
                
                for(idx in records){
                    if(idx)
                    {
                        var record = records[idx];

                        var recID  = resdata.fld(record, 'rec_ID'),
                            recName = resdata.fld(record, 'rec_Title'),
                            recRecTypeID = resdata.fld(record, 'rec_RecTypeID');

                        rec_titles.push('<b>'+window.hWin.HEURIST4.rectypes.names[recRecTypeID]+'</b>');
                        $('#rec'+index+'_title').text(recName);                                
                        
                        //recordsPair[recID] = recRecTypeID;
                        opposites.push( { recID:recID, recRecTypeID:recRecTypeID} );
                        
                        index++;
                    }
                }//for
                
                $('#rec_titles').html(rec_titles.join(' and '));
                $('#link_rec_edit').attr('href', window.hWin.HAPI4.baseURL
                                +'records/edit/editRecord.html?db='
                                +window.hWin.HAPI4.database+'&recID='+source_ID);
                $('#link_structure_edit').click(function(){
                     alert('boo!');
                });                
                
                var fi_name = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_DisplayName'], 
                    fi_constraints = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_PtrFilteredIDs'], 
                    fi_type = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex['dty_Type'],
                    fi_term =  window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_FilteredJsonTermIDTree'],
                    fi_term_dis =  window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_TermIDTreeNonSelectableIDs'],
                    hasSomeLinks = false;
                    
                index = 0;
                            
                for(idx in records){
                    if(idx)
                    {
                        var record = records[idx];

                        var recID  = resdata.fld(record, 'rec_ID'),
                            recName = resdata.fld(record, 'rec_Title'),
                            recRecTypeID = resdata.fld(record, 'rec_RecTypeID');

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
                                var values = resdata.values(record, dty);
                                var oppositeRecID = opposites[index==0?1:0]['recID'];
                                
                                //already linked for resource field type
                                var isAlready = (field_type=='resource') && $.isArray(values) && (values.indexOf(oppositeRecID)>=0);
                                
                                //add UI elements
                    $('<div style="line-height:2.5em;"><input type="checkbox" id="rec'+index+'_cb_'+dty+'" '
                    +(isAlready?'disabled checked="checked"':'')
                    +' class="text ui-widget-content ui-corner-all"/>'                                     
                    +'<label id="rec'+index+'_lbl_'+dty+'" style="font-style:italic">'+dtyName+'</label>&nbsp;'
                    +'<select id="rec'+index+'_sel_'+dty+'" class="text ui-widget-content ui-corner-all">'
                        +'<option>relation1</option>'
                    +'</select><div>').appendTo($('#rec'+index));
                    
                    
                                if(field_type=='relmarker'){
                                    
                                    var terms = details[fi_term],
                                        terms_dis = details[fi_term_dis],
                                        currvalue = null;
                                    
                                    window.hWin.HEURIST4.ui.createTermSelectExt($('#rec'+index+'_sel_'+dty).get(0), 
                                            'relation', terms, terms_dis, currvalue, null, false);    
                                }else{
                                    $('#rec'+index+'_sel_'+dty).hide();
                                }
                            }
                            
                            
                        }//for fields
                        index++;
                    }
                }//for
                
                if(!hasSomeLinks){
                    $('#btn_save').hide();
                    $('#mainForm').hide();
                    $('#infoForm').show();
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
    
    
}

