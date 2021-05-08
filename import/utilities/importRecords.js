/**
* Class to import records from JSON/XML
* 
* @returns {Object}
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

/*
_importDefinitions - downloads and import defintions from other database

                                               ImportHeurist::getDefintions 
hapi.doImportAction -> importController.php -> ImportHeurist::importDefintions
                                               ImportHeurist::importRecords

*/

function hImportRecords(_max_upload_size) {
    var _className = "ImportRecords",
    _version   = "0.4",
    
    btnUploadData,
    upload_file_name,  //file on server with original uploaded data
    progressInterval,
    session_id,
    rectypesInSource,
    detailtypesInSource,
    source_db;

    var targetMissed = 0;
    var targetDtMissed = 0;
    
    function _init( _max_upload_size){
    
        var uploadWidget = $('#uploadFile');
       
        //init STEP 1  - upload
        $('button').button();
                    
        //buttons
        btnUploadData = $('#btn_UploadData').click(function(e) {
                            if( window.hWin.HAPI4.is_admin() ){
                                uploadWidget.click();    
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr({
                                    status:window.hWin.ResponseStatus.REQUEST_DENIED,
                                    message:'Administrator permissions are required'});    
                            }
                        });
                    
        $('#btn_ImportRt').click(_importDefinitions);

        $('#btn_ImportRecords').click(_importRecords);

        $('#btn_Close').click(function(){window.close()});

        //upload file to server and store intemp file
        
        var uploadData = null;
        var pbar_div = $('#progressbar_div');
        var pbar = $('#progressbar');
        var progressLabel = pbar.find('.progress-label').text('');
        pbar.progressbar({value:0});
                
        $('#progress_stop').button().on({click: function() {
                if(uploadData && uploadData.abort) uploadData.abort();
        }});
                
                uploadWidget.fileupload({
        url: window.hWin.HAPI4.baseURL +  'hsapi/utilities/fileUpload.php', 
        formData: [ {name:'db', value: window.hWin.HAPI4.database}, //{name:'DBGSESSID', value:'424533833945300001;d=1,p=0,c=0'},
                    {name:'max_file_size', value: _max_upload_size},
                    {name:'entity', value:'temp'}, //just place file into scratch folder
                    {name:'autodect', value:1}], //try to detect line and field separator 
        //acceptFileTypes: /(\.|\/)(gif|jpe?g|png)$/i,
        autoUpload: true,
        sequentialUploads:false,
        dataType: 'json',
        //dropZone: $input_img,
        add: function (e, data) {  
        
            _showProgress(0, 0);    
            uploadData = data;//keep this object to use conviniece methods (abort for instance)
            data.submit(); 

        },
        //send: function (e, data) {},
        //start: function(e, data){},
        //change: function(){},
        error: function (jqXHR, textStatus, errorThrown) {
            //!!! $('#upload_form_div').show();
            _hideProgress(0);
            pbar_div.hide();
            if(textStatus!='abort'){
                window.hWin.HEURIST4.msg.showMsgErr(textStatus+' '+errorThrown);
            }
        },
        done: function (e, response) {

                response = response.result;
                if(response.status==window.hWin.ResponseStatus.OK){  //after upload
                    var data = response.data;
                    $.each(data.files, function (index, file) {
                        if(file.error){
                            _hideProgress(0);                
                            window.hWin.HEURIST4.msg.showMsgErr(file.error);
                            return false;
                        }else{
                            
                            upload_file_name = file.name;
                            
                            //save session - new primary rectype and sequence
                            var request = { action: 'import_preview',
                                filename: upload_file_name,
                                id: window.hWin.HEURIST4.util.random()
                            };
                                   
                            //call importController.php                                          
                            window.hWin.HAPI4.doImportAction(request, function( response ){
                                
                                    _hideProgress(0);
                                    if(response.status == window.hWin.ResponseStatus.OK){
                                        //render list of rectypes to be imported
                                        source_db = response.data.database;
                                        var source_db_name = response.data.database_name;
                                        
                                        $('#div_sourcedb').html('Source database:&nbsp;&nbsp;&nbsp;id: <b>'
                                        +(source_db>0?source_db:'0 <span style="color:red">(not registered)</span>')
                                        +'</b>&nbsp;&nbsp;name: '
                                        +(window.hWin.HEURIST4.util.isempty(source_db_name)
                                            ?'<span style="color:red">(not specified)</span>': ('<b>'+source_db_name+'</b>') ))
                                        .show();        
                                                
                                        //assign to global variables for re-use after sync
                                        rectypesInSource = response.data.rectypes;
                                        detailtypesInSource = response.data.detailtypes;
                                        
                                        _showListOfEntityTypes(false);
                                    }else{
                                        _showStep(0);
                                        window.hWin.HEURIST4.msg.showMsgErr(response);
                                    }
                                });
                            
                            
                        }
                    });
                }else{
                    _hideProgress(0);
                    _showStep(0);
                    window.hWin.HEURIST4.msg.showMsgErr(response.message);
                }
                
                //need to reassign  event handler since widget creates temp input
                var inpt = this;
                btnUploadData.off('click');
                btnUploadData.on({click: function(){
                            $(inpt).click();
                }});                
           
        },//done                    
        progressall: function (e, data) { 
                    //$('#divStep0').hide();
                    
                    var progress = parseInt(data.loaded / data.total * 100, 10);
                    progressLabel = pbar.find('.progress-label').text(
                                    Math.ceil(data.loaded/1024)+'Kb / '+Math.ceil(data.total/1024)) + 'Kb ';
                    pbar.progressbar({value: progress});
                    if (data.total>_max_upload_size && uploadData) {
                            uploadData.abort();
                            window.hWin.HEURIST4.msg.showMsgErr(
                            'Sorry, this file exceeds the upload '
                            //+ ((max_file_size<max_post_size)?'file':'(post data)')
                            + ' size limit set for this server ('
                            + Math.round(_max_upload_size/1024/1024) + ' MBytes). '
                            +'Please reduce the file size, or ask your system administrator to increase the upload limit.'
                            );
                    }else if(!pbar_div.is(':visible')){
                        //!!! $('#upload_form_div').hide();
                        _showStep(0);
                        pbar_div.show();
                    }
                }                            
                
                            });                        
     
     
    }

    //
    //
    //
    function _showListOfEntityTypes(afterSync){  

        var rectypes = rectypesInSource;
        var detailtypes = detailtypesInSource;

        var s = '', tsv = 'type\tsource id\tccode\tname in source\ttarget id\tname in target\n';
        var recCount = 0;

        var isAllRecognized = true;
        var sourceMissed = 0;
        var sourceDtMissed = 0;
        var cnt_local_rt = 0;
        var cnt_local_dt = 0;
        targetMissed = 0;
        targetDtMissed = 0;
        
        function __cntlbl(rt, dt){
            
            return (rt>0?(rt+' record type'+(rt>1?'s':'')):'')
                    +((rt>0 && dt>0)?' and ':'')
                    +(dt>0?(dt+' field type'+(dt>1?'s':'')):'')
        }
        

        for(var rtyID in rectypes){

            var rectype = rectypes[rtyID];

            var rectype_source = rectype['code'];
            if(!rectype_source){
                rectype_source = '<span style="color:red">missed</span>';
                sourceMissed++;
            }else if(rectype_source.indexOf('-')<0 || rectype_source.indexOf('0000-')==0 ){
                cnt_local_rt++;
            }

            s = s + '<tr><td>'
                + rectype_source+ '</td><td>'
                + rectype['count']+ '</td><td>'
                //+rectype['target_RecTypeID']+'</td><td>'
                +rectype['name']+ '</td><td>';
            
            if(afterSync && rectype['code'] &&!(rectype['target_RecTypeID']>0)){
                //try to find again
                rectype['target_RecTypeID'] = $Db.getLocalID( 'rty', rectype['code'] );
            }

            var target_id;
            if(rectype['target_RecTypeID']>0){
                target_id = rectype['target_RecTypeID']+'\t'+
                $Db.rty( rectype['target_RecTypeID'], 'rty_Name')+'\n';

                recCount = recCount + rectype['count'];

                s = s + rectype['target_RecTypeID']+'</td></tr>';
            }else{
                if(rectype['code'] && (afterSync || !(source_db>0)) ){
                    target_id = '\tnot found\n';
                    s = s + '<span style="color:red">not found</span></td></tr>';
                }else{
                    target_id = '\t\n';
                    s = s + '</td></tr>';
                }

                isAllRecognized = false;
                targetMissed++;
            }

            tsv = tsv + 'rectype\t'+(rtyID.indexOf('-')?'':rtyID)+'\t'+rectype['code']+'\t'
            +rectype['name']+'\t'+target_id;
        }

        // show missed fields
        if(detailtypes)
            for(var dtyID in detailtypes){
                var detailtype = detailtypes[dtyID];

                var dt_source = detailtype['code'];
                if(!dt_source){
                    dt_source = '<span style="color:red">missed</span>';
                    sourceDtMissed++;
                }else if(dt_source.indexOf('-')<0 || dt_source.indexOf('0000-')==0 ){
                    cnt_local_dt++;
                }
                
                if(afterSync && detailtype['code'] &&!(detailtype['target_dtyID']>0)){
                    //try to find again
                    detailtype['target_dtyID'] = $Db.getLocalID( 'dty', detailtype['code'] );
                }

                var target_id;
                if(detailtype['target_dtyID']>0){
                    target_id = detailtype['target_dtyID']+'\t'+
                        $Db.dty(detailtype['target_dtyID'], 'dty_Name' )+'\n';
                }else{
                    
                    var is_issue = (detailtype['code'] && (afterSync || !(source_db>0)));

                    if(targetDtMissed==0){
                        s = s + '<tr><td colspan="4"><b>field types '+(is_issue?'issues':'')+':</b></td></tr>';    
                    }

                    s = s + '<tr><td>'
                    +dt_source+'</td><td></td><td>'
                    +detailtype['name']+'</td><td>';

                    if(is_issue){
                        target_id = '\tnot found\n';
                        s = s + '<span style="color:red">not found</span></td></tr>';
                    }else{
                        target_id = '\t\n';
                        s = s + '</td></tr>';
                    }

                    
                    isAllRecognized = false;
                    targetDtMissed++;
                }

                tsv = tsv + 'detailtype\t'+(dtyID.indexOf('-')?'':dtyID)
                +'\t'+detailtype['code']+'\t'
                +detailtype['name']+'\t'+target_id;
            }//for

        $('#div_tsv').text(tsv);
        $('.tsv_download').click(function(event){
            window.hWin.HEURIST4.util.stopEvent(event);
            window.hWin.HEURIST4.util.downloadInnerHtml('elements_in_import.tsv',
                $('#div_tsv'),'text/tab-separated-values');
            return false;
        });
        $('#div_RectypeToBeImported').html('<table><tr>'
            +'<td>ID read from file</td><td>Rec count</td><td>Name</td><td>ID in this db</td></tr>'
            +s+'</table>');

        _showStep(1);   

        //hide all remakrs
        $('.import-rem').hide();

        $('.cnt_missed_rt').text(__cntlbl(targetMissed, targetDtMissed));
        $('.cnt_missed_rt3').text(__cntlbl(targetMissed, targetDtMissed));
        $('.cnt_missed_rt2').text(__cntlbl(targetMissed, 0));
        
        if(sourceMissed>0 || sourceDtMissed>0){
            $('.cnt_missed_rt').text(__cntlbl(sourceMissed, sourceDtMissed));
            $('.st1_E').show();

        }else if(!(source_db>0) || source_db==window.hWin.HAPI4.sysinfo.db_registeredid){
            //source database is not registered or the same database
            //thus we can't or not need import defintions
            //show the list of all definitions in source
            $('.st1_notreg').show();

            if(targetMissed>0 || targetDtMissed>0){
                $('.st1_G').show();
            }else if(isAllRecognized){
                $('.st1_D').show();
            }
            $("#divStep2").show();

        }else { //registered

            if(cnt_local_rt>0 || cnt_local_dt>0){
                $('.cnt_local_rt').text(__cntlbl(cnt_local_rt, cnt_local_dt));
                $('.st1_C').show();
            }
            
            if(afterSync){
                
                if(isAllRecognized){

                    $('#btn_ImportRt').hide();
                    $('.st1_AllRecognized_afterSync').show(); //All entity types are recognised.
                    $("#divStep2").show();

                }else {

                    $('.st1_NotRecognized_afterSync').show();

                    if(targetDtMissed==0){
                        //it is possible import if rt are missed, lock if fields are missed

                        if(targetMissed>0){
                            $('.st1_ImportRtError2').show();
                        }

                        _showStep(1);
                        $("#divStep2").show();

                    }else if(targetDtMissed>0) {
                        //import impossible
                        $('.st1_ImportRtError3').show();
                        _showStep(1);    

                    }
                }
            
            }else{
                if(isAllRecognized){
                    //show step2 and option to SYNC
                    $('#btn_ImportRt').show().button('option','label','Synch listed entity types');
                    $('.st1_AllRecognized_beforeSync').show(); //All entity types are recognised. However it is not guaranteed
                    $("#divStep2").show();
                }else{
                    //show option A - download - missing definitions
                    $('#btn_ImportRt').show().button('option','label','Download listed entity types');
                    $('.st1_OfferSync').show(); // rectypes in this file do not yet exist in target db
                }
            }
            
        }

        $('#spanRecCount').text(recCount);

        //no missing defintions
        if(s!=''){
        }else{
            //all record types are already in target database
            //goto import records step
            _showStep(2);
        }
    }                                        


    //
    // import database definitions before import records
    //
    function _importDefinitions(){
            
            session_id = Math.round((new Date()).getTime()/1000);
        
            _showProgress( 0, 1 );
            //$('#divStep1').hide();
            
            var request = { action: 'import_definitions',
                filename: upload_file_name,
                session: session_id,
                id: window.hWin.HEURIST4.util.random()
            };
            
//var _time_debug = new Date().getTime() / 1000;            
                   
                function _afterImportDefinitions( response ){
                    
//console.log('inport defs '+(new Date().getTime() / 1000 - _time_debug));
//_time_debug = new Date().getTime() / 1000;
                    _hideProgress();
                    $('body > div:not(.loading)').show();
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        //refresh database definitions
                        window.hWin.HAPI4.SystemMgr.get_defs_all( false, window.hWin.document, 
                        function(){
                            //verify for missed record types again    
                            _showListOfEntityTypes( true );
                        });
                    }else{
                        //hide progress and stay on step 1
                        $('.st1_ImportRtError').show();
                        
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                        
                        //it is possible import if rt are missed, lock if fields are missed
                        if(targetDtMissed>0) {
                            //import impossible
                            $('.st1_ImportRtError3').show();
                            _showStep(1);    
                            
                        }else if(targetDtMissed==0){
                            //import possible
                            if(targetMissed>0){
                                $('.st1_ImportRtError2').show();
                            }
                            
                            _showStep(1);
                            $("#divStep2").show();
                            
                        }

                        
                    }
                    
                    
                };
                
                window.hWin.HAPI4.doImportAction(request, _afterImportDefinitions);
                //debug _afterImportDefinitions( {status:1, message:'Bla Error!!!!'} );
                //_afterImportDefinitions( {status:window.hWin.ResponseStatus.OK } );
        
    }        

    //
    //
    //
    function _importRecords(){

            //$('#divStep2').hide();
            session_id = Math.round((new Date()).getTime()/1000);  //for progress
        
            var request = { action: 'import_records',
                filename: upload_file_name,
                session: session_id,
                id: window.hWin.HEURIST4.util.random()
            };
            
            _showProgress( session_id, 2 );
            
            /*
            setTimeout(function(){
                _hideProgress(3);
            },4000);
            return;
            */
            
            //see importController       
            window.hWin.HAPI4.doImportAction(request, function( response ){
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        _hideProgress(3);
                        
                        var sMsg = response.data.count_imported+'  records have been imported.<br>'
                            +(response.data.count_ignored>0
                              ?(response.data.count_ignored+'  records have been ignored - record type not determined.<br>')
                              :'')
                            +(response.data.resource_notfound && response.data.resource_notfound.length>0
                              ?(response.data.resource_notfound.length+' resource records not found: '
                                    +response.data.resource_notfound.join(',')+ '<br>')
                              :'');
                        if(response.data.details_empty && response.data.details_empty.length>0){
                            sMsg =  sMsg + (response.data.details_empty.length+' source records with empty details: '
                                    +response.data.details_empty.join(',')+ '<br>')
                        }     
                        
                        $('#spanRecCount2').html(sMsg);
                        
                        //refresh local defintions
                        window.hWin.HAPI4.EntityMgr.refreshEntityData('trm');
                        
                        //window.hWin.HEURIST4.msg.showMsgDlg('Imported '+response.data+' records');
                    }else{
                        _hideProgress(2);
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });

        
    }        
    
    //
    // @todo use msg.showProgress
    //
    function _showProgress( session_id, currentStep ){

        var progressCounter = 0;        
        var progress_url = window.hWin.HAPI4.baseURL + "viewers/smarty/reportProgress.php";

        $('body > div:not(.loading)').hide();//hide all except loading
        $('.loading').show();
        
        $('body').css('cursor', 'progress');

        var pbar = $('#progressbar');
        var progressLabel = pbar.find('.progress-label').text('');
        pbar.progressbar({value:0});
        
        if(session_id>0){
        
            $('#progress_stop').button().on({click: function() {
                
                var request = {terminate:1, t:(new Date()).getMilliseconds(), session:session_id};
                
                window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
                    _hideProgress( currentStep );
                    if(response && response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                        console.log(response);                   
                    }
                });
            } }, 'text');
            
            progressInterval = setInterval(function(){ 
                
                var request = {t:(new Date()).getMilliseconds(), session:session_id};            
                
                window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
                    
                    if(!response || response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                        //if(progressInterval!=null) _hideProgress( currentStep );
                        //console.log(response+'  '+session_id);                   
                    }else{
                        
                        var resp = response?response.split(','):[0,0];
                        
                        if(resp && resp[0]){
                            if(progressCounter>0){
                                if(resp[1]>0){
                                    var val = resp[0]*100/resp[1];
                                    pbar.progressbar( "value", val );
                                    progressLabel.text(resp[0]+' of '+resp[1]);
                                }else{
                                    progressLabel.text('wait...');
                                    //progressLabel.text('');
                                }
                            }else{
                                pbar.progressbar( "value", 0 );
                                progressLabel.text('preparing...');
                            }
                        }else{
                            if(progressInterval!=null) _hideProgress( currentStep );
                        }
                        
                        
                        progressCounter++;
                        
                    }
                },'text');
              
            
            }, 2000);                
            
        }
        
    }
    
    //
    // alwasys call _showStep after this method
    //
    function _hideProgress( currentStep ){
        
        
        $('body').css('cursor','auto');
        
        if(progressInterval!=null){
            
            clearInterval(progressInterval);
            progressInterval = null;
        }
        //$('#progressbar_div').hide();
        
        $('.loading').hide();
        if(currentStep>=0){
            $('body > div:not(.loading)').show();
            _showStep( currentStep );    
        }
        
    }    
       
    /*
    navigation steps
    0 - progress
    1 - select file to upload
    2 - preview and field roles as data and id
    --
    3 - matching: assign rec ids 
    4 - validate import   
    5 - import
    */
    function _showStep(page){
        currentStep = page;
        
        $("div[id^='divStep']").hide();
        $("#divStep"+(page>3?3:page)).show();
    }
    
    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},
    }

    _init(_max_upload_size );
    return that;  //returns object
}
    
    