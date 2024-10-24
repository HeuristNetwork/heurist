/**
* Class to import records from JSON/XML
* 
* @returns {Object}
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/*
_importDefinitions - downloads and import defintions from other database

                                               ImportHeurist::getDefintions - reads import file and returns rty to be imported
hapi.doImportAction -> importController.php -> ImportHeurist::importDefintions - import/sync definitions
                                               ImportHeurist::importRecords - import records

*/

function hImportRecords(_max_upload_size) {
    const _className = "ImportRecords",
    _version   = "0.4";
    
    let btnUploadData,
    upload_file_name,  //file on server with original uploaded data
    progressInterval,
    session_id,
    rectypesInSource,
    detailtypesInSource,
    source_db,
    source_db_url;

    let targetMissed = 0;
    let targetDtMissed = 0;
    
    function _init( _max_upload_size){
    
        let uploadWidget = $('#uploadFile');
       
        //init STEP 1  - upload
        $('button').button();
                    
        //buttons
        btnUploadData = $('#btn_UploadData').on('click', function(e) {
                            if( window.hWin.HAPI4.is_admin() ){
                                uploadWidget.trigger('click');    
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr({
                                    status:window.hWin.ResponseStatus.REQUEST_DENIED,
                                    message:'Administrator permissions are required'});    
                            }
                        });
                    
        $('#btn_ImportRt').on('click',_importDefinitions);

        $('#btn_ImportRecords').on('click',_importRecords);
        
        $('#sa_insert').on('click',_onUpdateModeSet);
        $('#sa_update').on('click',_onUpdateModeSet);

        $('#btn_Close').on('click', function(){window.close()});
        
        $('#sel_UniqueIdField').on('change', function(event){

            if(window.hWin.HEURIST4.util.isempty($(event.target).val())){
                $('#sa_mode').hide();
                $('#spanRecCount').parent().show();
            }else{
                $('#sa_mode').show();
                $('#spanRecCount').parent().hide();
            }
            
        });
        

        //upload file to server and store intemp file
        
        let uploadData = null;
        let pbar_div = $('#progressbar_div');
        let pbar = $('#progressbar');
        let progressLabel = pbar.find('.progress-label').text('');
        pbar.progressbar({value:0});
                
        $('#progress_stop').button().on({click: function() {
                if(uploadData && uploadData.abort) uploadData.abort();
        }});
                
                uploadWidget.fileupload({
        url: window.hWin.HAPI4.baseURL +  'hserv/controller/fileUpload.php', 
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

                let msg = textStatus+' '+errorThrown;
                if(textStatus == 'error'){
                    msg = 'An unknown error occurred while attempting to upload your CSV file.<br>This may be due to an unstable or slow internet connection.';
                }

                window.hWin.HEURIST4.msg.showMsgErr({message: msg, error_title: 'File upload error', status: window.hWin.ResponseStatus.UNKNOWN_ERROR});
            }
        },
        done: function (e, response) {

                response = response.result;
                if(response.status==window.hWin.ResponseStatus.OK){  //after upload
                    let data = response.data;
                    $.each(data.files, function (index, file) {
                        if(file.error){
                            _hideProgress(0);                
                            window.hWin.HEURIST4.msg.showMsgErr({message: file.error, error_title: 'File upload error'});
                            return false;
                        }else{
                            
                            upload_file_name = file.name;
                            
                            //save session - new primary rectype and sequence
                            let request = { action: 'import_preview',
                                filename: upload_file_name,
                                id: window.hWin.HEURIST4.util.random()
                            };
                                   
                            //call importController.php                                          
                            window.hWin.HAPI4.doImportAction(request, function( response ){
                                
                                    _hideProgress(0);
                                    if(response.status == window.hWin.ResponseStatus.OK){
                                        //render list of rectypes to be imported
                                        source_db = response.data.database;
                                        source_db_url = response.data.database_url;
                                        let source_db_name = response.data.database_name;
                                        
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
                    window.hWin.HEURIST4.msg.showMsgErr({message: response.message, error_title: 'File upload error', status: response.status});
                }
                
                //need to reassign  event handler since widget creates temp input
                let inpt = this;
                btnUploadData.off('click');
                btnUploadData.on({click: function(){
                            $(inpt).trigger('click');
                }});                
           
        },//done                    
        progressall: function (e, data) { 
                    //$('#divStep0').hide();
                    
                    let progress = parseInt(data.loaded / data.total * 100, 10);
                    progressLabel = pbar.find('.progress-label').text(
                                    Math.ceil(data.loaded/1024)+'Kb / '+Math.ceil(data.total/1024)) + 'Kb ';
                    pbar.progressbar({value: progress});
                    if (data.total>_max_upload_size && uploadData) {
                            uploadData.abort();
                            window.hWin.HEURIST4.msg.showMsgErr({
                                message:'Sorry, this file exceeds the upload '
                                       +` size limit set for this server (${Math.round(_max_upload_size/1024/1024)} MBytes). `
                                       +'Please reduce the file size, or ask your system administrator to increase the upload limit.',
                                error_title: 'Uploaded file is too large',
                                status: window.hWin.ResponseStatus.ACTION_BLOCKED
                            });
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

        let rectypes = rectypesInSource;
        let detailtypes = detailtypesInSource;

        let s = '', tsv = 'type\tsource id\tccode\tname in source\ttarget id\tname in target\n';
        let sel_options = '<option value="">select...</option><option value="rec_ID">record ID</option>';
        let sel_dty_ids = []; 
        let recCount = 0;

        let isAllRecognized = true;
        let sourceMissed = 0;
        let sourceDtMissed = 0;
        let cnt_local_rt = 0;
        let cnt_local_dt = 0;
        targetMissed = 0;
        targetDtMissed = 0;
        
        function __cntlbl(rt, dt){
            
            return (rt>0?(rt+' record type'+(rt>1?'s':'')):'')
                    +((rt>0 && dt>0)?' and ':'')
                    +(dt>0?(dt+' field type'+(dt>1?'s':'')):'')
        }
        

        for(let rtyID in rectypes){

            let rectype = rectypes[rtyID];

            let rectype_source = rectype['code'];
            if(!rectype_source){
                rectype_source = '<span style="color:red">missing</span>';
                sourceMissed++;
            }else if(rectype_source.indexOf('-')<0 || parseInt(rectype_source.split('-')[0])==0 ){
                cnt_local_rt++;
            }

            if(afterSync && rectype['code'] &&!(rectype['target_RecTypeID']>0)){
                //try to find again
                rectype['target_RecTypeID'] = $Db.getLocalID( 'rty', rectype['code'] );
            }

            s = s + '<tr><td>'
                + rectype_source+ '</td><td>'
                + rectype['count']+ '</td><td>'
                + rectype['name']+ '</td><td>'
                + rectype_source+ '</td><td>'
                + rectype['target_RecTypeID']
                +'</td></tr>';
            
            let target_id;
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
            for(let dtyID in detailtypes){
                let detailtype = detailtypes[dtyID];

                let dt_source = detailtype['code'];
                if(!dt_source){
                    dt_source = '<span style="color:red">missing</span>';
                    sourceDtMissed++;
                }else {
                    
                    if(dt_source.indexOf('-')<0 || parseInt(dt_source.split('-')[0])==0 ){
                        cnt_local_dt++;
                    }
                 
                    if(sel_dty_ids.indexOf(dt_source)<0){
                        sel_dty_ids.push(dt_source);
                        sel_options = sel_options + 
                                '<option value="'+dt_source+'">'+detailtype['name']+'</option>';
                    }
                }
                
                
                if(afterSync && detailtype['code'] &&!(detailtype['target_dtyID']>0)){
                    //try to find again
                    detailtype['target_dtyID'] = $Db.getLocalID( 'dty', detailtype['code'] );
                }

                let target_id;
                if(detailtype['target_dtyID']>0){
                    target_id = detailtype['target_dtyID']+'\t'+
                        $Db.dty(detailtype['target_dtyID'], 'dty_Name' )+'\n';
                }else{
                    
                    let is_issue = (detailtype['code'] && (afterSync || !(source_db>0)));

                    if(targetDtMissed==0){
                        s = s + '<tr><td colspan="4"><b>field types '+(is_issue?'issues':'')+':</b></td></tr>';    
                    }

                    s = s + '<tr><td>'
                    +dt_source+'</td><td></td><td>'
                    +detailtype['name']+'</td><td>'
                    +dt_source+'</td><td>';

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
            }//for detailtypes

        $('#div_tsv').text(tsv);
        $('.tsv_download').on('click', function(event){
            window.hWin.HEURIST4.util.stopEvent(event);
            window.hWin.HEURIST4.util.downloadInnerHtml('elements_in_import.tsv',
                $('#div_tsv'),'text/tab-separated-values');
            return false;
        });
        $('#div_RectypeToBeImported').html('<table><tr>'
            +'<td>ID read from file</td><td>Rec count</td><td>Name</td><td>Target DB concept</td><td>ID in this db</td></tr>'
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

        }else if(source_db==window.hWin.HAPI4.sysinfo.db_registeredid && window.hWin.HAPI4.sysinfo.db_registeredid>0){ //!(source_db>0) ||  TT 2022-12-21
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
            
            if(source_db==0 && window.hWin.HAPI4.sysinfo.db_registeredid==0){
                $('#btn_SameStructure').parent().show();
            }else{
                $('#btn_SameStructure').parent().hide();
                $('#btn_SameStructure').prop('checked', false);
            }

        }

        $('#spanRecCount').text(recCount);
        $('#sel_UniqueIdField').html(sel_options);

        //no missing defintions
        if(s==''){
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
            
            let request = { action: 'import_definitions',
                filename: upload_file_name,
                session: session_id,
                id: window.hWin.HEURIST4.util.random()
            };
            
                function _afterImportDefinitions( response ){
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
    }        

    //
    //
    //
    function _importRecords(){

            //$('#divStep2').hide();
            session_id = Math.round((new Date()).getTime()/1000);  //for progress
        
            let request = { action: 'import_records',
                filename: upload_file_name,
                session: session_id,
                id: window.hWin.HEURIST4.util.random()
            };
            
            if($('#btn_SameStructure').is(':checked')){
                request['same_source'] = 1;
            }

            if(!window.hWin.HEURIST4.util.isempty($('#sel_UniqueIdField').val())){
            
                request['unique_field_id'] = $('#sel_UniqueIdField').val();
                request['allow_insert'] = $('#sa_insert').is(':checked')?1:0;
                
                if($('#sa_update').is(':checked')){
                
                    let update_mode = $("input[name='sa_upd']:checked"). val();
   /*             
    *   - 1 owerwrite current record completely  (Load new values, replacing all existing values for these records/fields)
    *   - 2 Add new values without deletion of existing values (duplicates are ignored) 
    *   - 3 Add new values only if field is empty (new values ignored for non-empty fields) 
    *   - 4 Replace existing values with new values, retain existing value if no new value supplied
    */
                    request['update_mode'] = update_mode;
                }else{
                    request['update_mode'] = 0;
                }
                
            }
            
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
                        
                        let sMsg = response.data.count_imported+'  records have been processed in total.<br>'
                            +response.data.count_inserted+'  records have been inserted.<br>'
                            +response.data.count_updated+'  records have been updated.<br>'
                            +(response.data.count_ignored>0
                              ?(response.data.count_ignored+'  records have been ignored - record type not determined.<br>')
                              :'');
                              
                        if(response.data.resource_notfound && response.data.resource_notfound.length>0){

                            let mdata = response.data.resource_notfound;
                            let sList = '';
                            let rec_ids = [];
                            
                            sList = sList + '<tr><td>ID in source</td><td>Record ID</td><td>Field</td><td>Value</td></tr>'
                            for(let i=0; i<mdata.length; i++){
                                sList = sList + '<tr><td>'+mdata[i][1]+'</td><td>'+mdata[i][0]
                                     +'</td><td>'+mdata[i][2]+'</td><td>'+mdata[i][3]+'</td></tr>';
                                if(rec_ids.indexOf(mdata[i][0])<0) rec_ids.push(mdata[i][0]);
                            }
                            
                            sMsg = '<span class="ui-state-error">'+rec_ids.length 
                                + ' records have pointers which do not point to records in the database</span>' 
                                + '<div style="padding:10px">'
                                + '<a href="#" onclick="{ $(event.target).hide(); $(\'#missed_pointers\').show();}">see list</a>'
                                + '&nbsp;&nbsp;&nbsp;&nbsp;<a href="'
                                + window.hWin.HAPI4.baseURL +  '?db=' + window.hWin.HAPI4.database+'&q=ids:' + rec_ids.join(',')
                                +'" target="_blank">see records as search</a></div>'
                                + '<div style="display:none" id="missed_pointers"><span class="heurist-helper3">Note: existing records referenced by their Heurist record IDs must be specified in the form H-ID-nnn, otherwise the value is taken as a reference to the ID of a record within the XML file you are importing, specified by the &lt;id&gt; tag.</span><br><table>'
                                + sList + '</table></div>';
                        }
                              
                        if(response.data.details_empty && response.data.details_empty.length>0){
                            sMsg =  sMsg + (response.data.details_empty.length+' source records with empty details: '
                                    +response.data.details_empty.join(',')+ '<br>')
                        }     
                        
                        $('#spanRecCount2').html(sMsg);
                        
                        //refresh local defintions
                        window.hWin.HAPI4.SystemMgr.get_defs_all( false, window.hWin.document);    
                        //window.hWin.HAPI4.EntityMgr.refreshEntityData('trm');    
                        
                        
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

        let progressCounter = 0;        
        let progress_url = window.hWin.HAPI4.baseURL + "hserv/controller/progress.php";

        $('body > div:not(.loading)').hide();//hide all except loading
        $('.loading').show();
        
        $('body').css('cursor', 'progress');

        let pbar = $('#progressbar');
        let progressLabel = pbar.find('.progress-label').text('');
        pbar.progressbar({value:0});
        
        if(session_id>0){
        
            $('#progress_stop').button().on({click: function() {
                
                let request = {terminate:1, t:(new Date()).getMilliseconds(), session:session_id};
                
                window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
                    _hideProgress( currentStep );
                    if(response && response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                        console.error(response);                   
                    }
                });
            } }, 'text');
            
            progressInterval = setInterval(function(){ 
                
                let request = {t:(new Date()).getMilliseconds(), session:session_id};            
                
                window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
                    
                    if(!response || response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                        console.error(response, session_id);                   
                    }else{
                        
                        let resp = response?response.split(','):[0,0];
                        
                        if(resp && resp[0]){
                            if(progressCounter>0){
                                if(resp[1]>0){
                                    let val = resp[0]*100/resp[1];
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
        $("div[id^='divStep']").hide();
        $("#divStep"+(page>3?3:page)).show();
    }
    
    //
    //
    //
    function _onUpdateModeSet(){
      
        if(!$('#sa_insert').prop('checked') && !$('#sa_update').prop('checked'))
        {
            $('#sa_insert').prop('checked', true);
            $('#sa_update').prop('checked', true);
        }
        
        if($('#sa_update').is(':checked')){
            $('#divUpdateSetting').show();
        }else{
            $('#divUpdateSetting').hide();
        }
        
    }
    
    //public members
    let that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},
    }

    _init(_max_upload_size );
    return that;  //returns object
}
    
    