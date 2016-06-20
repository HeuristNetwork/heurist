/**
* Class to import records from CSV
* 
* @param _imp_ID - id of import session
* @returns {Object}
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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

function hImportRecordsCSV(_imp_ID, _max_upload_size) {
    var _className = "ImportRecordsCSV",
    _version   = "0.4",

    imp_ID,   //import session
    imp_session,  //json with session parameters
    
    currentStep, 
    currentId,  //currect record id in import tabel to PREVIEW data
    upload_file_name,  //file on server with original uploaded data
    encoded_file_name; //file on server with UTF8 encoded data
    
    function _init(_imp_ID, _max_upload_size){
    
        imp_ID = _imp_ID;
        
        //init STEP 1  - upload
        
        //buttons
        var btnUploadFile = $('#btnUploadData')
                    .css({'width':'120px'})
                    .button({label: top.HR('Upload Data'), icons:{secondary: "ui-icon-circle-arrow-e"}})
                    .click(_uploadData);

        //get list of sessions and fill selector                        
        var selImportID = $('#selImportId').change(function(e){
           if(e.target.value>0){
                imp_ID = e.target.value;      
                _loadSession();    
           }
        });
        top.HEURIST4.ui.createEntitySelector( selImportID.get(0), 
                    {entity:'SysImportSessions', filter_group:'0,'+top.HAPI4.currentUser['ugr_ID']}, 
                    top.HR('select uploaded file...'),
                    function(){
                        top.HEURIST4.util.setDisabled($('#btnClearAllSessions'), selImportID.find('option').length<2 );
                    });
                        
        $('#btnClearAllSessions').click(_doClearAllSessions);
        
        //upload file to server and store intemp file
        var uploadData = null;
        var pbar_div = $('#progressbar_div');
        var pbar = $('#progressbar');
        var progressLabel = pbar.find('.progress-label').text('');
        pbar.progressbar({value:0});
                
        $('#progress_stop').button().on({click: function() {
                if(uploadData && uploadData.abort) uploadData.abort();
        }});
                
        var uploadWidget = $('#uploadFile');
        
        var btnUploadFile = $('#btnUploadFile')
                    .css({'width':'120px'})
                    .button({label: top.HR('Upload File'), icons:{secondary: "ui-icon-circle-arrow-n"}})
                    .click(function(e) {
                            uploadWidget.click();
                        });
                
                
                uploadWidget.fileupload({
        url: top.HAPI4.basePathV4 +  'hserver/utilities/fileUpload.php', 
        formData: [ {name:'db', value: top.HAPI4.database}, //{name:'DBGSESSID', value:'424533833945300001;d=1,p=0,c=0'},
                    {name:'max_file_size', value: _max_upload_size},
                    {name:'entity', value:'temp'}],  //just place file into scratch folder
        //acceptFileTypes: /(\.|\/)(gif|jpe?g|png)$/i,
        autoUpload: true,
        sequentialUploads:false,
        dataType: 'json',
        //dropZone: $input_img,
        add: function (e, data) {  
        
            uploadData = data;//keep this object to use conviniece methods (abort for instance)
            data.submit(); 

        },
        //send: function (e, data) {},
        //start: function(e, data){},
        //change: function(){},
        error: function (jqXHR, textStatus, errorThrown) {
            //!!! $('#upload_form_div').show();
            _showStep(1);
            pbar_div.hide();
            if(textStatus!='abort'){
                top.HEURIST4.msg.showMsgErr(textStatus+' '+errorThrown);
            }
        },
        done: function (e, response) {

                //!!! $('#upload_form_div').show();                
                pbar_div.hide();       //hide progress bar
                response = response.result;
                if(response.status==top.HAPI4.ResponseStatus.OK){
                    var data = response.data;
                    $.each(data.files, function (index, file) {
                        if(file.error){
                            top.HEURIST4.msg.showMsgErr(file.error);
                        }else{
                            /*
                            $('#divParsePreview').load(file.url, function(){
                            //$.ajax({url:file.deleteUrl, type:'DELETE'});
                            }); */
                            
                            upload_file_name = file.name;
                            top.HEURIST4.util.setDisabled($('#csv_encoding'), false);
                            _showStep(2);
                            
                            top.HEURIST4.util.setDisabled( $('#btnParseStep2'), true );
                            $('#divFieldRolesHeader').hide();
                            $('#divParsePreview').empty();
                            $('#divFieldRoles').empty();
                            
                            //_doParse(1);
                        }
                    });
                }else{
                    _showStep(1);
                    top.HEURIST4.msg.showMsgErr(response.message);
                }
                
                //need to reassign  event handler since widget creates temp input
                var inpt = this;
                btnUploadFile.off('click');
                btnUploadFile.on({click: function(){
                            $(inpt).click();
                }});                
           
        },//done                    
        progressall: function (e, data) { // to implement
                    var progress = parseInt(data.loaded / data.total * 100, 10);
                    progressLabel = pbar.find('.progress-label').text(data.loaded+' / '+data.total);
                    pbar.progressbar({value: progress});
                    if (data.total>_max_upload_size && uploadData) {
                            uploadData.abort();
                            top.HEURIST4.msg.showMsgErr(
                            'Sorry, this file exceeds the upload '
                            //+ ((max_file_size<max_post_size)?'file':'(post data)')
                            + ' size limit set for this server ('
                            + Math.round(max_size/1024/1024) + ' MBytes). '
                            +'Please reduce the file size, or ask your system administrator to increase the upload limit.'
                            );
                    }else if(!pbar_div.is(':visible')){
                        //!!! $('#upload_form_div').hide();
                        _showStep(0);
                        pbar_div.show();
                    }
                }                            
                
                            });                        

                        
        //init STEP 2  - preview, select field roles and parse
        $('#btnBackToStart2')
                    .css({'width':'160px'})
                    .button({label: top.HR('Back to start'), icons:{primary: "ui-icon-circle-arrow-w"}})
                    .click(function(e) {
                            //@todo - remove temp file
                            _showStep(1);
                            $('#selImportId').val('');
                        });
                        
        $('#btnParseStep1')
                    .css({'width':'160px'})
                    .button({label: top.HR('Analyse data'), icons:{secondary: "ui-icon-circle-arrow-e"}})
                    .click(function(e) {
                            _doParse(1);
                        });

        $('#btnParseStep2')
                    .css({'width':'180px'})
                    .button({label: top.HR('Continue'), icons:{secondary: "ui-icon-circle-arrow-e"}})
                    .click(function(e) {
                           _doParse(2); 
                        });
        top.HEURIST4.util.setDisabled( $('#btnParseStep2'), true );
        $('#divFieldRolesHeader').hide();
                        
        //init STEP 3 - matching and import
        $('#btnBackToStart')
                    .css({'width':'160px'})
                    .button({label: top.HR('Back to start'), icons:{primary: "ui-icon-circle-arrow-w"}})
                    .click(function(e) {
                            _showStep(1);
                        });
        $('#btnDownloadFile')
                    .css({'width':'180px'})
                    .button({label: top.HR('Download data to file'), icons:{secondary: "ui-icon-circle-arrow-s"}})
                    .click(function(e) {
                            
                        });
        $('#btnClearFile')
                    .css({'width':'160px'})
                    .button({label: top.HR('Clear uploaded file'), icons:{secondary: "ui-icon-circle-close"}})
                    .click(function(e) {
                            
                        });
                        
        $('#sa_rectype').change(_initFieldMapppingTable);               
        $('#btnSetPrimaryRecType').click(_doSetPrimaryRecType);
        
        //init navigation links
        $.each($('.navigation'), function(idx, item){
            $(item).click( _getValuesFromImportTable );
        })

        //session id is defined - go to 3d step at once        
        if(imp_ID>0){
              _loadSession();
        }
        _showStep(1);
        
        
        //--------------------------- action buttons init

        $('#btnMatchingSkip')
                    .css({'width':'250px'})
                    .button({label: top.HR('Import as new (skip matching)') })
                    .click(function(e) {
                            _doMatching( true, null );
                        });
        $('#btnMatchingStart')
                    .css({'width':'250px'})
                    .button({label: top.HR('Match against existing records'), icons:{secondary: "ui-icon-circle-arrow-e"}})
                    .click(function(e) {
                            _doMatching( false, null );
                        });

        $('#btnBackToMatching')
                    .css({'width':'250px'})
                    .button({label: top.HR('Back: Match Again'), icons:{primary: "ui-icon-circle-arrow-w"}})
                    .click(function(e) {
                            _showStep(3);
                            _initFieldMapppingTable();
                        });
        
        $('#btnBackToMatching2')
                    .css({'width':'250px'})
                    .button({label: top.HR('Back: Match Again'), icons:{primary: "ui-icon-circle-arrow-w"}})
                    .click(function(e) {
                            _showStep(3);
                            _initFieldMapppingTable();
                        });
                        
        $('#btnResolveAmbiguous')
                    .css({'width':'250px'})
                    .button({label: top.HR('Resolve ambiguous matches')})
                    .click(function(e) {
                            _showRecords('disamb');
                        });
                        

        $('#btnPrepareStart')
                    .css({'width':'250px'})
                    .button({label: top.HR('Prepare Insert/Update'), icons:{secondary: "ui-icon-circle-arrow-e"}})
                    .click(function(e) {
                            _doPrepare();
                        });

        $('#btnImportStart')
                    .css({'width':'250px'})
                    .button({label: top.HR('Start Insert/Update'), icons:{secondary: "ui-icon-circle-arrow-e"}})
                    .click(function(e) {
                            _doImport();
                        });
          
          $(window).resize( function(e)
          {
                _adjustButtonPos();
          });          
                        
    }

    //
    // Remove all sessions
    //
    function _doClearAllSessions(){
        
        top.HEURIST4.util.setDisabled($('#btnClearAllSessions'), true);
        top.HAPI4.EntityMgr.doRequest({a:'delete', entity:'sysImportSessions', recID:0},
                    function(response){
                        if(response.status == top.HAPI4.ResponseStatus.OK){
                            $('#selImportId').empty();
                            top.HEURIST4.msg.showMsgDlg('Import sessions were cleared','Info');
                        }else{
                            top.HEURIST4.msg.showMsgErr(response);
                            top.HEURIST4.util.setDisabled($('#btnClearAllSessions'), false);
                        }
                    });
    }

    //
    //
    //
    function _loadSession(){
        
        if(imp_ID>0){
        
            var request = {
                    'a'          : 'search',
                    'entity'     : 'sysImportSessions',
                    'details'    : 'list',
                    'imp_ID'     : imp_ID
            };
            
            top.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        
                        if(response.status == top.HAPI4.ResponseStatus.OK){
                        
                            //clear selectors
                            $('#sa_rectype').empty().val('');
                            $('#sa_rectypes_preview').empty().val('');
                            
                            var resp = new hRecordSet( response.data );
                            var record = resp.getFirstRecord();
                            var ses = resp.fld(record, 'imp_session');
                            //DEBUG $('#divFieldMapping2').html( ses );
                            
                            imp_session = (typeof ses == "string") ? $.parseJSON(ses) : null;
                            
                            
                            //init field mapping table
                            _showStep(3);
                            _loadRectypeDependencies($('#sa_rectype').get(0));
                            
                            
                        }else{
                            top.HEURIST4.msg.showMsgErr(response);
                        }
                    }
            );        
        
        
        }
    }
    
    //
    //  open dialog to choose primary record type
    //
    function _doSetPrimaryRecType(is_initial){
        
            var $dlg, buttons = {};
        
            if($('#sa_primary_rectype > options').length==0){
                top.HEURIST4.ui.createRectypeSelect( $('#sa_primary_rectype').get(0), null, top.HR('select...') );
                $('#sa_primary_rectype').change( function(event){ 
                        var ele = $(event.target);
                        var sleEle = ele.parents('#divSelectPrimaryRecType').find('#sa_rectypes_preview');
                        _loadRectypeDependencies( sleEle.get(0), ele.val() ); 
                });
            }
        
            buttons[top.HR('OK')]  = function() {
                    
                    $dlg.dialog( "close" );
                    
                    if($('#sa_primary_rectype').val()!=imp_session['primary_rectype']){
                        imp_session['primary_rectype'] = $('#sa_primary_rectype').val();    
                        _loadRectypeDependencies( $('#sa_rectype').get(0) );
                    }
                    
                }; 
            buttons[top.HR('Cancel')]  = function() {
                    $dlg.dialog( "close" );
                    if(is_initial==true){
                         _showStep(1);
                    }
            };
        
            var dlg_options = {
                title:'Select primary record type and dependencies',
                height:'400px',
                element: document.getElementById('divSelectPrimaryRecType'),
                buttons: buttons
                };
            $dlg = top.HEURIST4.msg.showElementAsDialog(dlg_options);
        
    }

    //
    //  load list of record types as tree into sa_rectype
    //
    function _loadRectypeDependencies( selEle, preview_rty_ID ){
            
            var is_preview = (preview_rty_ID>0);
            
            //main rectype is not defined - open select dialog
            if(!( is_preview ||  imp_session['primary_rectype']>0)){
                 _doSetPrimaryRecType(true);
                 return;
            }
        
            /*if(is_preview){
                selEle = $('#sa_rectypes_preview').get(0)
            }else{
                selEle = $('#sa_rectype').get(0)
            }*/    
            
            //request to server to get all dependencies for given primary record type
            var request = { action: 'set_primary_rectype',
                            is_preview: (is_preview?1:0),
                            rty_ID: is_preview?preview_rty_ID:imp_session['primary_rectype'],
                            imp_ID: imp_ID,
                                id: top.HEURIST4.util.random()
                               };
                               
                               
            top.HAPI4.parseCSV(request, function( response ){
                
                //that.loadanimation(false);
                if(response.status == top.HAPI4.ResponseStatus.OK){
                
                    var rectypes = response.data;
                    
                    top.HEURIST4.ui.createRectypeTreeSelect(selEle, rectypes, null, 0);    
                    
                    if(is_preview){
                        var options = $(selEle).find('option');
                        if(options.length==1){
                            $(options[0]).text('No dependencies found');
                        }
                    }else{
                        _initFieldMapppingTable();    
                    } 
                    
                }else{
                    _showStep(1);
                    top.HEURIST4.msg.showMsgErr(response);
                }

            });        

    }    
    
    //
    // init field mapping table - main table to work with 
    //
    function _initFieldMapppingTable(){
    
        $('#tblFieldMapping > tbody').empty();
        
                        
        var sIndexes = "",
            sRemain = "",
            sProcessed = "",
            i = 0,
            len = (imp_session && imp_session['columns'])?imp_session['columns'].length:0;
            
        //find index field for selected record type    
        var rtyID = $("#sa_rectype").val();

        var mapping_flds = imp_session[(currentStep==3)?'mapping_keys':'mapping_flds'];
        if(mapping_flds) mapping_flds = mapping_flds[rtyID];
        if(!mapping_flds) mapping_flds = {};
            
        var recStruc = top.HEURIST4.rectypes;    

        for (i=0; i < len; i++) {

            var isIndex =  !top.HEURIST4.util.isnull(imp_session['indexes']['field_'+i]);
            var isProcessed = !( isIndex || top.HEURIST4.util.isnull(mapping_flds[i]) );
            
            //checkbox that marks 'in use'
            var s = '<tr><td width="75px" align="center">&nbsp;<span style="display:none;">'
                    +'<input type="checkbox" id="cbsa_dt_'+i+'" value="'+i+'"/></span></td>';

            // count of unique values
            s = s + '<td  width="75px" align="center">'+imp_session['uniqcnt'][i]+'</td>';

            // column names                 padding-left:15px;  <span style="max-width:290px"></span>
            s = s + '<td style="width:300px;class="truncate">'+imp_session['columns'][i]+'</td>';

            // mapping selector
            s = s + '<td style="width:300px;">&nbsp;<span style="display:none;">'
                + '<select id="sa_dt_'+i+'" style="width:280px; font-size: 1em;" data-field="'+i+'" '
                + (isIndex?'class="indexes"':'')+'></select></span>';
            
            s = s + '</td>';

            // cell for value
            s = s + '<td id="impval'+i+'" style="text-align: left;padding-left: 16px;">&nbsp;</td></tr>';

            if(isIndex){
                      sIndexes = sIndexes +s;
            }else if(isProcessed){
                      sProcessed = sProcessed +s;
            }else{
                      sRemain = sRemain +s;
            }
        }//for
        
        if(sIndexes!=''){
            sIndexes = '<tr height="40" style="valign:bottom"><td class="subh" colspan="5"><br /><b>Identifiers (Pointer fields)</b></td></tr>'
                +sIndexes;
        }
        if(sRemain!=''){
            sRemain = '<tr height="40" style="valign:bottom"><td class="subh" colspan="5"><br /><b>Remaining Data</b></td></tr>'
                +sRemain;
        }
        if(sProcessed!=''){
            sProcessed = '<tr height="40" style="valign:bottom"><td class="subh" colspan="5"><br /><b>Already used</b></td></tr>'
                +sProcessed;
        }
        
        $('#tblFieldMapping > tbody').html(sIndexes+sRemain+sProcessed);
        
        //init listeners
        $("input[id^='cbsa_dt_']").change(function(e){
            var cb = $(e.target);
            var idx = cb.val();//attr('id').substr(8);
            if(cb.is(':checked')){
                $('#sa_dt_'+idx).parent().show();
            }else{
                $('#sa_dt_'+idx).parent().hide();
            }
            
        });
        
        //init selectors
        _initFieldMapppingSelectors();
        //load data
        _getValuesFromImportTable();            
        //
        
    }    

    //
    //
    //    
    function _adjustButtonPos(){
        //adjust position of footer with action buttons  
        var content = $('#divFieldMapping');
        var btm = $('#divStep3').height() - (content.position().top + $('#tblFieldMapping').height());
        btm = (btm<150) ?150 :btm;

        content.css('bottom', btm-20);
        $('#divImportActions').height(btm-20);
    }
    
    //
    // by recordtype ID 
    //
    function _getFieldIndexForIdentifier(rtyID){
            var i;
            var keyfields = Object.keys(imp_session['indexes']);
            for (i=0;i<keyfields.length;i++){
                if(imp_session['indexes'][keyfields[i]] == rtyID){
                    var idx = keyfields[i].substr(6); //field_
                    return idx;
                }
            }
            return -1;
    }
    
    
    
    //
    // init field mapping selectors after change of rectype
    //
    function _initFieldMapppingSelectors(){
        
        var sels = $("select[id^='sa_dt_']"); //all selectors
        
        var cbs = $("input[id^='cbsa_dt_']"); //all checkboxes
        cbs.attr('checked', false).attr('disabled', false);
        sels.parent().hide();
        
        //fill selectors with fields of selected record type
        var rtyID = $("#sa_rectype").val();
        var keyfield_sel = '';
        
        var mapping_flds = null;
        if(currentStep!=3){
            mapping_flds = (imp_session['mapping_flds'])?imp_session['mapping_flds'][rtyID]:{};
        }
        if(!mapping_flds || $.isEmptyObject(mapping_flds)){
            mapping_flds = (imp_session['mapping_keys'])?imp_session['mapping_keys'][rtyID]:{};
        }
        //var mapping_flds = imp_session[(currentStep==3)?'mapping_keys':'mapping_flds'][rtyID];
        if(!mapping_flds) mapping_flds = {};
        
        if(rtyID>0){
            cbs.parent().show(); //show checkboxes
            
            //find key/index field for selected record type
            var idx = _getFieldIndexForIdentifier(rtyID);
            if(idx>=0){
                    keyfield_sel = 'sa_dt_'+idx;
                    $("#cbsa_dt_"+idx).attr('checked', true).attr('disabled',true);
                    $('#sa_dt_'+idx).parent().show(); //show selector
            }
            
        }else{
            cbs.parent().hide();
        }
        
        var allowed = Object.keys(top.HEURIST4.detailtypes.lookups);
        allowed.splice(allowed.indexOf("separator"),1);
        allowed.splice(allowed.indexOf("file"),1);
        allowed.splice(allowed.indexOf("resource"),1);
        //allowed.splice(allowed.indexOf("relmarker"),1);
        if(currentStep==3){ //matching step
            allowed.splice(allowed.indexOf("geo"),1);
        }
        
        var topitems = [{key:'',title:'...'},{key:'url',title:'Record URL'},{key:'scratchpad',title:'Record Notes'}];
        
        var allowed2 = ['resource'];
        var topitems2 = [{key:'',title:'...'}]; 

        $.each(sels, function (idx, item){
            var $item = $(item);
            if($item.attr('id')==keyfield_sel){
                top.HEURIST4.ui.createSelector(item, [{key:'id',title:'Record ID'}] );  //the only option for current id field
            }else{
                
                var field_idx = $(item).attr('data-field');
                var dt_id = mapping_flds[field_idx];
                var selected_value = (!top.HEURIST4.util.isnull(dt_id))?dt_id:null;
                
                var sel = top.HEURIST4.ui.createRectypeDetailSelect(item, rtyID, 
                    $item.hasClass('indexes')?allowed2:allowed, 
                    $item.hasClass('indexes')?topitems2:topitems,
                    {show_dt_name:true, 
                     show_latlong:(currentStep==4), 
                     show_required:(currentStep==4)});    
                    
               if(!top.HEURIST4.util.isnull(selected_value)){
                        $("#cbsa_dt_"+field_idx).attr('checked', true);
                        $(item).parent().show(); //show selector
                        $(item).val(dt_id);
               }
                    
            }
        });
        

        //for selected record type key field does not exist
        if(keyfield_sel==''){
            
        }
        
        
        //show counts
        if(rtyID>0){
            
            counts = _getInsertUpdateCounts();
            
            $('#mrr_cnt_update').text(counts[0]);                                 
            $('#mrr_cnt_insert').text(counts[2]);                                 
            if(counts[1]>0){
                $('#mrr_cnt_update_rows').text(counts[1]);                                     
                $('.mrr_update').show();                                     
            }else{
                $('.mrr_update').hide();                                     
            }
            if(counts[3]>0){
                $('#mrr_cnt_insert_rows').text(counts[3]);                                     
                $('.mrr_insert').show();                                     
            }else{
                $('.mrr_insert').hide();                                     
            }

        
            $('#divFieldMapping2').show();
        }else{
            $('#divFieldMapping2').hide();
        }
        
        
        
    }
    
    function _getInsertUpdateCounts(){
        
        var rtyID = $("#sa_rectype").val();
        if(rtyID>0){
            
            var counts = imp_session['counts'];
            if(counts) counts = counts[rtyID];
            if(!counts) {
                counts = [0,0,0,0];
                //reccount - total records in import table
                //uniqcnt - unique values per column
                var idx = _getFieldIndexForIdentifier(rtyID);
                if(idx>=0){
                    counts[2] = counts[3] = imp_session['uniqcnt'][idx];
                }else{
                    //all to be inserted
                    counts[2] = counts[3] = imp_session['reccount'];
                }
            }
            return counts;
        }else{
            return null;
        }
        
    }
    
    //
    // Loads values for record from import table (preview values in main table)
    //
    function _getValuesFromImportTable(event){
        
        if(!imp_session) return;
        
        var currentTable = imp_session['import_table']; 
        var recCount     = Number(imp_session['reccount']); 
        
        
        if(currentTable && recCount>0){
            
            var dest = 0;
            if(event){
                dest = $(event.target).attr('data-dest');  
            } 
            
            if(Number(dest)==0){
                currentId=1;
            }else if(dest=='last'){
                currentId = recCount;
            }else{
                dest = Number(dest);
                if(isNaN(dest)){
                    currentId = 1;
                }else{
                    currentId = currentId + dest;
                }
            }
            if(currentId<1) {
                currentId=1;
            }else if (currentId>recCount){
                currentId = recCount;
            }
            
            
            var request = { action: 'records',
                            imp_ID: currentId,
                            table:currentTable,
                            id: top.HEURIST4.util.random()
                               };
            
            top.HAPI4.parseCSV(request, function( response ){
                
                //that.loadanimation(false);
                if(response.status == top.HAPI4.ResponseStatus.OK){
                
                    var response = response.data;
                    
                        var i;
                        $("#current_row").html(response[0]);

                        for(i=1; i<response.length;i++){
                            if(top.HEURIST4.util.isnull(response[i])){
                                sval = "&nbsp;";
                            }else{

                                var isIndex =  !top.HEURIST4.util.isnull(imp_session['indexes']['field_'+(i-1)]);
                                
                                var sval = response[i].substr(0,100);

                                if(isIndex && response[i]<0){
                                    sval = "&lt;New Record&gt;";
                                }else if(sval==""){
                                    sval = "&nbsp;";
                                }else if(response[i].length>100){ //add ... 
                                    sval = sval + '&#8230;';
                                }
                            }

                            if($("#impval"+(i-1)).length>0)
                                $("#impval"+(i-1)).html(sval);
                        }

                    
                }else{
                    _showStep(1);
                    top.HEURIST4.msg.showMsgErr(response);
                }

            });        
            
            
/*
            $.ajax({
                url: top.HAPI4.basePathV3+'import/delimited/importCSV.php',
                type: "POST",
                data: {recid: currentId, table:currentTable, db:top.HAPI4.database},
                dataType: "json",
                cache: false,
                error: function(jqXHR, textStatus, errorThrown ) {
                    //alert('Error connecting server. '+textStatus);
                },
                success: function( response, textStatus, jqXHR ){
                    if(response){

                        var i;
                        $("#current_row").html(response[0]);

                        for(i=1; i<response.length;i++){
                            if(top.HEURIST4.util.isnull(response[i])){
                                sval = "&nbsp;";
                            }else{

                                var isIndex =  !top.HEURIST4.util.isnull(imp_session['indexes']['field_'+(i-1)]);
                                
                                var sval = response[i].substr(0,100);

                                if(isIndex && response[i]<0){
                                    sval = "&lt;New Record&gt;";
                                }else if(sval==""){
                                    sval = "&nbsp;";
                                }else if(response[i].length>100){ //add ... 
                                    sval = sval + '&#8230;';
                                }
                            }

                            if($("#impval"+(i-1)).length>0)
                                $("#impval"+(i-1)).html(sval);
                        }
                    }
                }
            });*/
        }
        
        _adjustButtonPos();
        
        return false;
    }
    
    //
    // upload data that was pasted in textarea
    //
    function _uploadData(){
        
        var csvdata = $('#sourceContent').val();
        
        if(csvdata){
            
            _showStep(0);
        
            var request = { action: 'step0',
                              data: csvdata,
                                id: top.HEURIST4.util.random()
                               };
            top.HAPI4.parseCSV(request, function( response ){
                
                //that.loadanimation(false);
                if(response.status == top.HAPI4.ResponseStatus.OK){
                
                    upload_file_name = response.data.filename; //filename only
                    $('#csv_encoding').val('UTF-8');
                    top.HEURIST4.util.setDisabled($('#csv_encoding'), true);
                    _showStep(2);
                    
                    top.HEURIST4.util.setDisabled( $('#btnParseStep2'), true );
                    $('#divFieldRolesHeader').hide();
                    $('#divParsePreview').empty();
                    $('#divFieldRoles').empty();
                    
                    //_doParse(1);
                    
                }else{
                    _showStep(1);
                    top.HEURIST4.msg.showMsgErr(response);
                }

            });        
        
        }else{
            top.HEURIST4.msg.showMsgErr('Paste csv/tsv to content area first');    
        }
    }
    
    //
    // parse CSV on server side
    // step1 - encode and get preview
    // step2 - field roles, prepare and save into file 
    //
    function _doParse(step){
        /*    
        keyfield
        datefield
        memofield
        */
        
                _showStep(0);
        
                var request = { action: step==2?'step2':'step1',
                                csv_delimiter: $('#csv_delimiter').val(),
                                csv_linebreak: $('#csv_linebreak').val(),
                                csv_enclosure: $('#csv_enclosure').val(),
                                csv_mvsep: $('#csv_mvsep').val(),
                                csv_dateformat: $('#csv_dateformat').val(),
                                csv_encoding: $('#csv_encoding').val(),
                                id: top.HEURIST4.util.random()
                               };

                var container  = $('#divParsePreview');
                var container2 = $('#divFieldRoles');
                               
                if(step==1){
                    request['upload_file_name'] = upload_file_name; //filename only
                    
                    encoded_file_name = '';
                    top.HEURIST4.util.setDisabled( $('#btnParseStep2'), true );
                    $('#divFieldRolesHeader').hide();
                    container.empty();
                    container2.empty();
                }else{
                    request['encoded_filename'] = encoded_file_name; //full path
                    request['original_filename'] = upload_file_name; //filename only
                    request['keyfield'] = {};
                    request['datefield'] = [];
                    var ele = $.find("input[id^='id_field_']");
                    $.each(ele, function(idx, item){
                            if($(item).is(':checked')){
                                var rectypeid = $('#id_rectype_'+item.value).val();
                                if(!rectypeid){
                                    rectypeid = 0;
                                }
                                request['keyfield']['field_'+item.value] = rectypeid;
                            }
                    }); 
                    
                    ele = $.find("input[id^='d_field_']");
                    $.each(ele, function(idx,item){
                            if($(item).is(':checked')){
                                request['datefield'].push(item.value )   
                            }
                    }); 
                }               
                 
                

                top.HAPI4.parseCSV(request, function( response ){
                    
                    _showStep(2);                    
                    //that.loadanimation(false);
                    if(response.status == top.HAPI4.ResponseStatus.OK){
                       
                        if(jQuery.type(response.data) === "string"){
                            $( top.HEURIST4.msg.createAlertDiv(response.data)).appendTo(container);
                            return;
                        }
                        
                        
//{filename,col_count,errors:  , err_encoding: , fields:, values:}                            
//errors:  "cnt"=>count($fields), "no"=>$line_no, "line"
//err_encoding: "no"=>$line_no, "line"
                        var tbl,i,j,
                            haveErrors = false;

                        
                        //something went wrong - index fields have wrong values, problem with encoding or column number mismatch
                        var container3 = container.find('#error_messages');
                        if(container3.length==0){
                            container3 = $('<div>',{id:'error_messages'}).appendTo(container);
                        }else{
                            container3.empty();
                        }

                        if(top.HEURIST4.util.isArrayNotEmpty(response.data['err_colnums'])){

                            var msg = 'Wrong field count in parsed data. Expected field count '
                                        +response.data['col_count']
                                        +'. Either change parse parameters or correct source data';
                            $( top.HEURIST4.msg.createAlertDiv(msg)).appendTo(container3);

                            tbl  = $('<table><tr><th>Line#</th><th>Field count</th><th>Raw data</th></tr>')
                                    .addClass('tbpreview')
                                    .appendTo(container3);
                                    
                            for(i in response.data.err_colnums){

                                $('<tr><td>'+response.data.err_colnums[i]['no']+'</td>'
                                    +'<td>'+response.data.err_colnums[i]['cnt']+'</td>'
                                    +'<td>'+response.data.err_colnums[i]['line']+'</td></tr>').appendTo(tbl);
                            }         
                            $('<hr>').appendTo(container3);
                            haveErrors = true;
                        }
                        if(top.HEURIST4.util.isArrayNotEmpty(response.data['err_encoding'])){

                            var msg = ' Wrong encoding detected in import file. At least '
                                        +response.data['err_encoding'].length
                                        +'lines have such issue';
                                
                            $( top.HEURIST4.msg.createAlertDiv(msg)).appendTo(container3);

                            tbl  = $('<table><tr><th>Line#</th><th>Raw data</th></tr>')
                                    .addClass('tbpreview')
                                    .appendTo(container3);
                                    
                            for(i in response.data.err_encoding){
                                $('<tr><td>'+response.data.err_encoding[i]['no']+'</td>'
                                    +'<td>'+response.data.err_encoding[i]['line']+'</td></tr>').appendTo(tbl);
                            }         
                            $('<hr>').appendTo(container3);
                            haveErrors = true;
                        }
                        
                        if(response.data['err_keyfields']){
                            var keyfields = Object.keys( response.data['err_keyfields'] );
                            if(keyfields.length>0){

                                var msg = 'Field you marked as identifier contain wrong or out of range values';
                                    
                                $( top.HEURIST4.msg.createAlertDiv(msg)).appendTo(container3);

                                tbl  = $('<table><tr><th>Field</th><th>Non integer values</th><th>Out of range</th></tr>')
                                    .addClass('tbpreview')
                                    .appendTo(container3);
                                
                                for (i=0;i<keyfields.length;i++){
                                    
                                    var issues = response.data['err_keyfields'][keyfields[i]];
                                
                                    $('<tr><td>'+response.data['fields'][keyfields[i]]+'</td>'
                                        +'<td>'+issues[0]+'</td><td>'+issues[1]+'</td></tr>').appendTo(tbl);
                                }         
                            }
                            $('<hr>').appendTo(container3);
                            haveErrors = true;
                        }

                        top.HEURIST4.util.setDisabled( $('#btnParseStep2'), haveErrors);
                        if(haveErrors){
                                $('#divFieldRolesHeader').hide();
                        }else{
                                $('#divFieldRolesHeader').show();
                        }

                        //preview parser
                        if(response.data.step==1 && response.data.values){
                                
                            encoded_file_name = response.data.encoded_filename;
                                      
                            $('<h2 style="margin:10px">'+top.HR('Parse results.')
                                    +(response.data.values.length<100?'':' First 100 rows')
                                    +'</h2>').appendTo(container);

                            tbl  = $('<table>').addClass('tbpreview').appendTo(container);
                            
                            var _parseddata = response.data.values;
                            var maxcol = 0;
                            
                            var tr  = '<tr>'
                            for(i in response.data.fields){
                                tr = tr+'<th>'+response.data.fields[i]+'</th>';
                            }                   
                            tr  = tr+'</tr>';     
                            $(tr).appendTo(tbl);
                            for(i in response.data.values){
                                
                                if(top.HEURIST4.util.isArrayNotEmpty(_parseddata[i])){
                                    tr  = '<tr>';
                                    for(j in _parseddata[i]){
                                        tr = tr+'<td>'+_parseddata[i][j]+'</td>';
                                    }
                                    tr  = tr+'</tr>';
                                    maxcol = Math.max(maxcol,_parseddata[i].length);
                                    $(tr).appendTo(tbl);
                                }
                            }
                            
                            
                            //<tr><th>Column</th><th>Is date?</th><th>Is key?</th><th>For record type</th></tr>
                            //add header for list of columns
                            tbl  = $('<table>').addClass('tbfields').appendTo(container2);
                           
                            //fill list of columns
                            for(i in response.data.fields){
                                $('<tr><td style="width:200px">'+response.data.fields[i]+'</td>'
                                +'<td style="width:50px;text-align:center"><input type="checkbox" id="id_field_'+i+'" value="'+i+'"/></td>'
                                +'<td style="width:50px;text-align:center"><input type="checkbox" id="d_field_'+i+'" value="'+i+'"/></td>'
                                +'<td style="width:200px"><select id="id_rectype_'
                                +i+'" class="text ui-widget-content ui-corner-all" style="visibility:hidden"></select></td></tr>').appendTo(tbl);
                            }         
                            
                            
                            var select_rectype = $("select[id^='id_rectype']");
                            $.each(select_rectype, function(idx, item){
                                top.HEURIST4.ui.createRectypeSelect( item, null, 'select...' );    
                            });

                            $("input[id^='d_field']").change(function(evt){
                                var cb = $(evt.target); 
                                top.HEURIST4.util.setDisabled( $('#id_field_'+cb.val()), cb.is(':checked') );
                            });
                            
                            $("input[id^='id_field']").change(function(evt){
                                var cb = $(evt.target);
                                top.HEURIST4.util.setDisabled( $('#btnParseStep2'), false );
                                $('#divFieldRolesHeader').show();

                                top.HEURIST4.util.setDisabled( $('#d_field_'+cb.val()), cb.is(':checked') );
                                $("select[id='id_rectype_"+ cb.val()+"']")    //attr('id').substr(9)
                                        .css('visibility',  cb.is(':checked')?'visible':'hidden');            
                            });
                            

                        }else if(response.data.import_id>0){
                            
                            imp_ID = response.data.import_id;      
                            if($('#selImportId > option').length<1){
                            top.HEURIST4.ui.addoption($('#selImportId').get(0), null, top.HR('select uploaded file...'));    
                            }
                            top.HEURIST4.ui.addoption($('#selImportId').get(0), imp_ID, response.data.import_name);
                            _loadSession();    
                        }
                        
                    }else{
                        if(step==1 && response.status=='unknown' && response.message=='Error_Connection_Reset'){
                            top.HEURIST4.msg.showMsgErr('It appears that your file is not in UTF8. Please select correct encoding');
                        }else{
                            top.HEURIST4.msg.showMsgErr(response);
                        }
                    }

                });
    }
 
    //
    // Find records in Heurist database and assign record id to identifier field in import table
    //
    function _doMatching( isSkipMatch, disamb_resolv ){
        
        var rtyID = $("#sa_rectype").val();
        if(rtyID>0){
        
            var sWarning = '';
            var haveMapping = false; 
            //do we have id field?
            var key_idx = _getFieldIndexForIdentifier(rtyID); 
            //do we have field to match?
            var field_mapping = {};
            if(!isSkipMatch){
                var ele = $("input[id^='cbsa_dt_']");
                
                $.each(ele, function(idx, item){
                    var item = $(item);
                    if(item.is(':checked')){ // && item.val()!=key_idx){
                        var field_type_id = $('#sa_dt_'+item.val()).val();
                        if(field_type_id){
                            field_mapping[item.val()] = field_type_id;
                            if(item.val()!=key_idx) haveMapping = true;
                        }
                    }
                });
            }
                //verify setting
                // id is defined
                // mapping defined -> values in id field will be overwritten
                //         not defined -> use value in id field
                // id is not defined
                // mapping defined ->  new field will be created
                //         not defined -> import as new     
            
                if(key_idx>=0){
                    if(haveMapping){
                        if(disamb_resolv==null){
                        sWarning = 'You choose to match the incoming data and selected the existing identification field "'
                                +imp_session['columns'][key_idx]
                                +'".<br>It means it will be filled with new values.<br><br>Proceed?';
                        }
                    }else{
//@todo here and on server side
// match against id field, set to -1 if not found ???                       
                        /*
                        sWarning = '<h3>WARNING</h3>'
     + (isSkipMatch?'':'You have not selected any colulm to field mappping<br><br>')                   
     +'By choosing not to match the incoming data, you will create '
     + 'XYZ(TODO!!!!)' //imp_session['uniqcnt'][key_idx]
     +' new records - that is one record for every unique value in identification field.<br><br>Proceed without matching?';
     */
     //+'If a value is repeated in many rows of your input data it will create multiple rows even though you are using it as the key field';     
                    }
                }else{
                    if(haveMapping){
                        sWarning = '';
                    }else{
                        sWarning = '<h3>WARNING</h3>'
     + (isSkipMatch?'':'You have not selected any colulm to field mappping<br><br>')                   
     +'By choosing not to match the incoming data, you will create '
     +imp_session['reccount']+' new records - that is one record for every row.<br><br>Proceed without matching?';
                    }
                }
            
            
            if(sWarning){
                top.HEURIST4.msg.showMsgDlg(sWarning, __doMatchingStart , "Confirmation");
            }else{
                __doMatchingStart();
            }

       function __doMatchingStart(){    
         
            var request = {
                imp_ID    : imp_ID,
                action    : 'step3',
                sa_rectype: rtyID,
                mapping   : field_mapping,
                skip_match: isSkipMatch?1:0
            };
            if(key_idx>=0){
                request['idfield']=imp_session['columns'][key_idx]; //key_idx;            
            }
            if(disamb_resolv!=null){
                request['disamb_resolv']=disamb_resolv;          
            }
            
            _showStep(0);
        
            top.HAPI4.parseCSV(request, 
                    function(response){
                        
                        if(response.status == top.HAPI4.ResponseStatus.OK){
                            
                            _showStep(3);
                            
                            imp_session = response.data; //assign to global var

                            var res = imp_session['validation'];
                            
                            $('#mr_cnt_update').text(res['count_update']);                                 
                            $('#mr_cnt_insert').text(res['count_insert']);                                 
                            if(res['count_update_rows']>0){
                                $('#mr_cnt_update_rows').text(res['count_update_rows']);                                     
                                $('.mr_update').show();                                     
                            }else{
                                $('.mr_update').hide();                                     
                            }
                            if(res['count_insert_rows']>0){
                                $('#mr_cnt_insert_rows').text(res['count_insert_rows']);                                     
                                $('.mr_insert').show();                                     
                            }else{
                                $('.mr_insert').hide();                                     
                            }
                            
                            var disambig_keys = Object.keys(res['disambiguation']);
                            
                            if(disambig_keys.length>0){
                                //imp_session = (typeof ses == "string") ? $.parseJSON(ses) : null;
                                $('#mr_cnt_disamb').text(disambig_keys.length);                                 
                                $('#mr_cnt_disamb').parent().show();
                                
                                top.HEURIST4.msg.showMsgErr('One or more rows in your file match multiple records in the database. '+
                        'Please click on "Rows with ambiguous match" to view and resolve these ambiguous matches.<br><br> '+
                        'If you have many such ambiguities you may need to select adidtional key fields or edit the incoming '+
                        'data file to add further matching information.');
                        
                                $('.step3 > .normal').hide();
                                $('.step3 > .need_resolve').show();
                        
                        
                            }else{
                                
                                /*
                                if(!(key_idx>=0)){
                                    //recreate selectors
                                    _initFieldMapppingTable();
                                    _initFieldMapppingSelectors();
                                }
                                _getValuesFromImportTable();
                                */
                                
                                _showStep(4);
                                _initFieldMapppingTable();
                                $('#mr_cnt_disamb').parent().hide();
                                
                            }
                            $('#divMatchingResult').show();
                            
                        }else{
                            _showStep(3);
                            top.HEURIST4.msg.showMsgErr(response);
                        }
                    }
            );        
        }
        
            
        
        }else{
            top.HEURIST4.msg.showMsgErr(top.HR('You have to select record type'));
            $("#sa_rectype").focus();
        }
        
    }         
    
    //
    //
    //
    function _doPrepare(){
        
        var rtyID = $("#sa_rectype").val();
        if(!(rtyID>0)){
            top.HEURIST4.msg.showMsgErr(top.HR('You have to select record type'));
            $("#sa_rectype").focus();
            return;
        }
        var key_idx = _getFieldIndexForIdentifier(rtyID); 
        if(!(key_idx>=0)){
            top.HEURIST4.msg.showMsgErr(top.HR('You have to define identifier field for selected record type'));
            return;
        }
        
        //do we have field to match?
        var haveMapping = false; 
        var field_mapping = {};
        var ele = $("input[id^='cbsa_dt_']");
        
        $.each(ele, function(idx, item){
            var item = $(item);
            if(item.is(':checked')){ // && item.val()!=key_idx){
                var field_type_id = $('#sa_dt_'+item.val()).val();
                if(field_type_id){
                    field_mapping[item.val()] = field_type_id;
                    if(item.val()!=key_idx) haveMapping = true;
                }
            }
        });
            
        if(!haveMapping){
            top.HEURIST4.msg.showMsgErr(top.HR('You have to select at least one column to map heurist record field'));
            return;
        }
        
        var request = {
            db        : top.HAPI4.database,
            imp_ID    : imp_ID,
            action    : 'step4',
            sa_rectype: rtyID,
            mapping   : field_mapping,
            ignore_insert: 0,
            recid_field: 'field_'+key_idx //imp_session['columns'][key_idx]
        };

        request['DBGSESSID']='425288446588500001;d=1,p=0,c=0';
        
        
        _showStep(0);
    
        //top.HAPI4.parseCSV(request, 
        
        var url = top.HAPI4.basePathV3 + 'import/delimited/importCSV.php';
        
        top.HEURIST4.util.sendRequest(url, request, null, 
                function(response){
                    
                    if(response.status == top.HAPI4.ResponseStatus.OK){
                        
                        _showStep(4);
                        
                        imp_session = response.data; //assign to global var

                        var res = imp_session['validation'];
                        
                        $('#mr_cnt_update').text(res['count_update']);                                 
                        $('#mr_cnt_insert').text(res['count_insert']);                                 
                        if(res['count_update_rows']>0){
                            $('#mr_cnt_update_rows').text(res['count_update_rows']);                                     
                            $('.mr_update').show();                                     
                        }else{
                            $('.mr_update').hide();                                     
                        }
                        if(res['count_insert_rows']>0){
                            $('#mr_cnt_insert_rows').text(res['count_insert_rows']);                                     
                            $('.mr_insert').show();                                     
                        }else{
                            $('.mr_insert').hide();                                     
                        }
                        
                        if(res['count_error']>0){
                            //imp_session = (typeof ses == "string") ? $.parseJSON(ses) : null;
                            $('#mr_cnt_error').text(res['count_error']);                                 
                            $('#mr_cnt_error').parent().show();
                            
                            top.HEURIST4.msg.showMsgErr((res['count_error']==1?'One row':(res['count_error']+' rows'))
                            +' in your file '+(res['count_error']==1?'has':'have')+' wrong values for fields you matched.<br><br> '
                            + 'It is better to fix these in the source file and then process it again, as uploading faulty data generally leads to major fix-up work.');                            
                    
                            $('.step3 > .normal').hide();
                            $('.step3 > .need_resolve').show();
                        }else{
                            _showStep(5);
                        }

                        $('#divMatchingResult').show();
                        
                    }else{
                        _showStep(4);
                        top.HEURIST4.msg.showMsgErr(response);
                    }
                
                });
        
    } 
    
    
    function _doImport(){

        var rtyID = $("#sa_rectype").val();
        if(!(rtyID>0)){
            top.HEURIST4.msg.showMsgErr(top.HR('You have to select record type'));
            $("#sa_rectype").focus();
            return;
        }
        var key_idx = _getFieldIndexForIdentifier(rtyID); 
        if(!(key_idx>=0)){
            top.HEURIST4.msg.showMsgErr(top.HR('You have to define identifier field for selected record type'));
            return;
        }
        
        //do we have field to match?
        var haveMapping = false; 
        var field_mapping = {};
        var ele = $("input[id^='cbsa_dt_']");
        
        $.each(ele, function(idx, item){
            var item = $(item);
            if(item.is(':checked')){ // && item.val()!=key_idx){
                var field_type_id = $('#sa_dt_'+item.val()).val();
                if(field_type_id && item.val()!=key_idx){ //do not include id field into mapping
                    field_mapping[item.val()] = field_type_id;
                    haveMapping = true;
                }
            }
        });
            
        if(!haveMapping){
            top.HEURIST4.msg.showMsgErr(top.HR('You have to select at least one column to map heurist record field'));
            return;
        }
        
        var request = {
            db        : top.HAPI4.database,
            imp_ID    : imp_ID,
            action    : 'step5',
            sa_rectype: rtyID,
            mapping   : field_mapping,
            ignore_insert: 0,
            recid_field: 'field_'+key_idx, //imp_session['columns'][key_idx]
            sa_upd: $("input[name='sa_upd']:checked"). val(),
            sa_upd2: $("input[name='sa_upd2']:checked"). val()
        };
        
        request['DBGSESSID']='425288446588500001;d=1,p=0,c=0';
        
        _showStep(0);
    
        //top.HAPI4.parseCSV(request, 
        var url = top.HAPI4.basePathV3 + 'import/delimited/importCSV.php';
        
        top.HEURIST4.util.sendRequest(url, request, null, 
                function(response){
                    
                    if(response.status == top.HAPI4.ResponseStatus.OK){
                        
                        _showStep(5);
                        
                        imp_session = response.data; //assign to global var

                        var imp_result = imp_session['import_report'];
                        
                        var msg = '<table class="tbresults"><tr><td>Total rows in import table:</td><td>'+ imp_result['total']
                          +'</td></tr><tr><td>Processed:</td><td>'+ imp_result['processed']
                          +'</td></tr><tr><td>Skipped:</td><td>'+ imp_result['skipped']
                          +'</td></tr><tr><td>Records added:</td><td>'+ imp_result['inserted']
                          +'</td></tr><tr><td>Records updated:</td><td>'+ imp_result['updated']
                          +'</td></tr></table>';
                        
                        //'Processed: '+ imp_result['processed']
                        top.HEURIST4.msg.showMsgDlg(msg);
                        /*
                        , 'Import report'
                        );*/

                        $('#divMatchingResult').show();
                        
                    }else{
                        _showStep(5);
                        top.HEURIST4.msg.showMsgErr(response);
                    }
                
                });
        
    } 

    //
    //  Request to server side and get records for insert ot update from import table
    // 
    function _showRecords2(mode, is_download){
     
        var s = '';
        var container = $('#divPopupPreview');
        var dlg_options = {};
        var $dlg;
        var offset = 0, limit=10, 
            recCount = _getInsertUpdateCounts(),
            start_idx = 0;
            
        if(recCount==null) return;
        
        recCount = recCount[mode=='insert'?3:1];
        
        
        dlg_options['title'] = 'Records to be '+(mode=='insert'?'inserted':'updated');
        
        s = '<div class="ent_wrapper"><div style="padding:8px 0 0 10px" class="ent_header">'
            +'<a href="#" class="navigation2" style="display: inline-block;"><span data-dest="first" class="ui-icon ui-icon-seek-first"/></a>'
            +'<a href="#" class="navigation2" style="display:inline-block;"><span data-dest="-1" class="ui-icon ui-icon-triangle-1-w"/></a>'
            +'<div style="display: inline-block;vertical-align: super;">Range <span id="current_range"></span></div>'
            +'<a href="#" class="navigation2" style="display: inline-block;"><span data-dest="1" class="ui-icon ui-icon-triangle-1-e"/></a>'
            +'<a href="#" class="navigation2" style="display: inline-block;"><span data-dest="last" class="ui-icon ui-icon-seek-end"/></a></div>';
        
            
        s = s + '<div class="ent_content_full"><table class="tbmain" style="font-size:0.9em" width="100%"><thead><tr>'; 

        var id_field = null;         
        var rtyID = $("#sa_rectype").val();
        
        var index_field_idx = _getFieldIndexForIdentifier(rtyID);
        if(index_field_idx>=0){
             id_field = 'field_'+index_field_idx;
        }
     
        
        var mapping_flds = null;
        if(currentStep!=3){  //import step
            mapping_flds = (imp_session['mapping_flds'])?imp_session['mapping_flds'][rtyID]:{};
        }
        if(!mapping_flds || $.isEmptyObject(mapping_flds)){
            mapping_flds = (imp_session['mapping_keys'])?imp_session['mapping_keys'][rtyID]:{};
        }
        //var mapping_flds = imp_session[(currentStep==3)?'mapping_keys':'mapping_flds'][rtyID];
        if(!mapping_flds) mapping_flds = {};
        
        
        //HEADER - field names
        var j, i=0, fieldnames = Object.keys(mapping_flds);
        
        if(mode=="update"){
            s = s + '<th width="30px">Record ID</th>';
        }else if(fieldnames.length==0) {
            s = s + '<th width="30px">Line #</th>';
        }else{
            start_idx = 1;
        }
        
        if(fieldnames.length>0){
            for(;i<fieldnames.length;i++){
                var colname = imp_session['columns'][fieldnames[i]];
                if(fieldnames[i]!=index_field_idx){
                    s = s + '<th>'+colname+'</th>';
                }
            }
        }else{  //all
        
            fieldnames = imp_session['columns'];
            for(;i<fieldnames.length;i++){
                var colname = fieldnames[i];
                s = s + '<th>'+colname+'</th>';
            }
        }

        s = s + '</tr></thead><tbody></tbody></table></div></div>';
        
        dlg_options['element'] = container.get(0);
        container.html(s);
            
        $dlg = top.HEURIST4.msg.showElementAsDialog(dlg_options);
        
        $.each($dlg.find('.navigation2'), function(idx, item){
            $(item).click( __loadRecordsFromImportTable );
        })
        
        
        __loadRecordsFromImportTable();
        
        
        function __loadRecordsFromImportTable(event){
        
            if(!imp_session) return;
            
            var currentTable = imp_session['import_table']; 
        
            if(currentTable && recCount>0){
            
                    var dest = 0;
                    if(event){
                        dest = $(event.target).attr('data-dest');  
                    } 
                    
                    
                    if(Number(dest)=='first'){
                        offset = 0;
                    }else if(dest=='last'){
                        offset = Math.floor(recCount/limit) * limit;
                    }else{
                        dest = Number(dest);
                        if(isNaN(dest)){
                            offset = 0;
                        }else{
                            offset = offset + dest*limit;
                        }
                    }
                    if (offset>recCount){
                        offset = Math.floor(recCount/limit) * limit;
                    }                    
                    if(offset<0){
                        offset = 0;
                    }

                    var request = { action: 'records',
                                    id_field: id_field,
                                    mapping: mapping_flds,
                                    is_insert: (mode=='insert')?1:0,
                                    is_download: (is_download)?1:0,
                                    offset: offset,
                                    limit: limit,
                                    table:currentTable,
                                    mapping:mapping_flds,
                                    id: top.HEURIST4.util.random()
                                       };
                    
                    top.HAPI4.parseCSV(request, function( response ){
                        
                        //that.loadanimation(false);
                        if(response.status == top.HAPI4.ResponseStatus.OK){
                        
                                var response = response.data;
                                
                                var table = $dlg.find('.tbmain > tbody');
                            
                                var i,j, s = '';
                                 $dlg.find("#current_range").html( offset+1)+':'+(offset+limit) );

                                for(i=0; i<response.length;i++){
                                    var row = response[i];
                                    if(start_idx>0) row.shift();
                                    s = s+'<tr>';
                                    s = s+'<td>'+ row.join('</td><td>') +'</td>';
                                    s = s+'</tr>';
                                }
                                
                                table.html(s);
                            
                        }else{
                            top.HEURIST4.msg.showMsgErr(response);
                        }

                    });        
            }
        }
        
     
    }
    
    //
    //  Compose table and show it in popup
    // 
    function _showRecords(mode){
        
        var res = imp_session['validation'];
        var s = '';
        var container = $('#divPopupPreview');
        var dlg_options = {};
        var $dlg;
        
        if(mode=='disamb'){
            
            s = '<div>The following rows match with multiple records. This may be due to the existence of duplicate '
            +'records in your database, but you may not have chosen all the fields required to correctly disambiguate '
            +'the incoming rows against existing data records.</div><br/><br/>'
            +'<table class="tbmain" width="100%"><thead><tr><th>Key values</th><th>Count</th><th>Records in Heurist</th></tr>';

            
            var buttons = {};
            buttons[top.HR('Confirm and cotinue to assign IDs')]  = function() {
                    
                    var keyvalues = Object.keys(res['disambiguation']);
                    var disamb_resolv = {};  //recid=>keyvalue
                    $dlg.find('.sel_disamb').each(function(idx, item){
                         disamb_resolv[$(item).val()] = keyvalues[$(item).attr('data-key')];
                    });

                    $dlg.dialog( "close" );
                    
                    _doMatching(false, disamb_resolv);
                }; 
            buttons[top.HR('Close')]  = function() {
                    $dlg.dialog( "close" );
            };
            
            dlg_options = {
                title:'Disambiguation',
                buttons: buttons
                };
            
            var j, i=0, keyvalues = Object.keys(res['disambiguation']);
            
            for(i=0;i<keyvalues.length;i++){

                var keyvalue = keyvalues[i].split(imp_session['csv_mvsep']);
                keyvalue.shift(); //remove first element
                keyvalue = keyvalue.join(';&nbsp;&nbsp;');
                
                //list of heurist records
                var disamb = res['disambiguation'][keyvalues[i]];
                var recIds = Object.keys(disamb);
                        
                s = s + '<tr><td>'+keyvalue+'</td><td>'+recIds.length+'</td><td>'+
                        '<select class="sel_disamb" data-key="'+i+'">';                

                for(j=0;j<recIds.length;j++){
                    s = s +  '<option value="'+recIds[j]+'">[rec# '+recIds[j]+'] '+disamb[recIds[j]]+'</option>';
                }
                s = s + '</select>&nbsp;'
                + '<a href="#" onclick="{window.open(\''+top.HAPI4.basePathV4+'?db='+top.HAPI4.database
                + '&q=ids:' + recIds.join(',') + '\', \'_blank\');}">view records</a></td></tr>';
            }
            
            s = s + '</table><br><br>'
            +'<div>Please select from the possible matches in the dropdowns. You may not be able to determine the correct records'
            +' if you have used an incomplete set of fields for matching.</div>';
        
        }else if(mode=='error'){    //------------------------------------------------------------------------------------------- 

                var is_missed = false;
                var tabs = res['error'];
                var k = 0;

                if(tabs.length>1){

                    s = s + '<div id="tabs_records"><ul>';
                    
                    for (;k<tabs.length;k++){
                        var colname = imp_session['columns'][tabs[k]['field_checked'].substr(6)];
                        s = s + '<li><a href="#rec__'+k+'" style="color:red">'
                                    +colname+'<br><span style="font-size:0.7em">'
                                    +tabs[k]['short_message']+'</span></a></li>';
                    }
                    s = s + '</ul>';
                }

                for (k=0;k<tabs.length;k++){
                    var rec_tab = tabs[k];
                    s = s + '<div id="rec__'+k+'">'

                    var cnt = rec_tab['count_error'];
                    var records = rec_tab['recs_error'];

                    if(cnt>records.length){
                        s = s + "<div class='error'><b>Only the first "+records.length+" of "+cnt+" rows are shown</b></div>";
                    }

                    var checked_field  = rec_tab['field_checked'];

                    var ismultivalue = checked_field && imp_session['multivals'][checked_field.substr(6)];//highlight errors individually
                    
                    s = s + "<div><span class='error'>Values in red are invalid: </span> "+rec_tab['err_message']+"<br/><br/></div>";

                    var is_missed = (rec_tab['err_message'].indexOf('a value must be supplied')>0);


                    //all this code only for small asterics
                    var rtyID = $("#sa_rectype").val();
                    var recStruc = null;
                    var idx_reqtype;
                    
                    if(rtyID){
                        recStruc = top.HEURIST4.rectypes['typedefs'][rtyID]['dtFields'];
                        idx_reqtype = top.HEURIST4.rectypes['typedefs']['dtFieldNamesToIndex']['rst_RequirementType'];
                    }


                    var detDefs = top.HEURIST4.detailtypes;
                    var detLookup = detDefs['lookups'];
                    detDefs = detDefs['typedefs'];
                    var idx_dt_type = detDefs['fieldNamesToIndex']['dty_Type'];
    /*
        //find distinct terms values
        $is_enum = false;
        if(!$is_missed){
            $err_col = 0;
            $m = 1;
            foreach($mapped_fields as $field_name=>$dt_id) {
                if($field_name==$checked_field && @$detDefs[$dt_id]){
                    $err_col = $m;

                    $dttype = $detDefs[$dt_id]['commonFields'][$idx_dt_type];
                    $is_enum = ($dttype=='enum' || $dttype=='relationtype');
                    break;
                }
                $m++;
            }

            if($is_enum){
                $distinct_value = array();
                if($records && is_array($records)) {
                    foreach ($records as $row) {
                        $value = $row[$err_col];
                        if(!in_array($value, $distinct_value)){
                            array_push($distinct_value, $value);
                        }
                    }
                }

                if(count($distinct_value)>0){
                    //print distinct term values
                    print '<div style="display:none;padding-bottom:10px;" id="distinct_terms_'.$k.'"><br>';
                    foreach ($distinct_value as $value) {
                        print '<div style="margin-left:30px;">'.$value.' </div>';
                    }
                    print '</div>';
                    print '<div><a href="#" onclick="{top.HEURIST.util.popupTinyElement(window, document.getElementById(\'distinct_terms_'.
                    $k.'\'),{\'no-close\':false, \'no-titlebar\':false });}">Get list of unrecognised terms</a>'.
                    ' (can be imported into terms tree)<br/>&nbsp;</div>';
                }
            }

        }//end find distinct terms values
    */
                s = s + '<table class="tbmain" width="100%"><thead><tr>' 
                        + '<th width="20px">Line #</th>';

                //HEADER - only error field
                var err_col = 0;
                
                //var mapped_fields = imp_session['validation']['mapped_fields'];
                var j, i, fieldnames = Object.keys(res['mapped_fields']);

                for(i=0;i<fieldnames.length;i++){
                
                    
                    if(fieldnames[i] == checked_field){
                        
                        var colname = imp_session['columns'][fieldnames[i].substr(6)];
                        var dt_id = res['mapped_fields'][fieldnames[i]]; //from validation
                        
                        if(recStruc[dt_id][idx_reqtype] == "required"){
                            colname = colname + "*";
                        }
                        /* @TODO generate term selector
                        if(is_enum){
                            $showlink = '&nbsp;<a href="#" onclick="{showTermListPreview('.$dt_id.')}">show list of terms</a>';
                        }else{
                            $showlink = '';
                        }
                        
                        if($is_enum){
                            $colname = $colname."<div id='termspreview".$dt_id."'></div>"; //container for
                        }
                        */
                        
                        
                        s = s + '<th style="color:red">'+colname
                             + '<br><font style="font-size:10px;font-weight:normal">'
                             + (dt_id>0?detLookup[detDefs[dt_id]['commonFields'][idx_dt_type]] :dt_id)
                             + '</th>';
                             
                        err_col = i;
                        break;
                    }
                }

                s = s + '<th>Record content</th></tr></thead>';
                
                //BODY
                for(i=0;i<records.length;i++){

                    var row = records[i];
                    s = s + '<tr><td class="truncate">'+row[0]+'</td>'; //line#
                    if(is_missed){
                        s = s + "<td style='color:red'>&lt;missing&gt;</td>";
                    } else if(ismultivalue){
                        s = s + "<td class='truncate'>"+row[err_col+1]+"</td>";
                    } else {
                        s = s + "<td class='truncate' style='color:red'>"+row[err_col+1]+"</td>";
                    }
                    //TODO - print row content of line
                    /*
                    for(j=0;j<row.length;j++){
                        s = s +  '<td class="truncate">'+(row[j]?row[j]:"&nbsp;")+'</td>';
                    }
                    */
                    s = s + "<td>&nbsp;</td></tr>";
                }
            
                s = s + '</table></div>';
                
            }//tabs

            if(tabs.length>1){
                s = s + '</div>';
            }
            
            dlg_options['title'] = 'Records with errors';
            
        }else if(res['count_'+mode+'_rows']>0){ //-------------------------------------------------------------------------------
            
            dlg_options['title'] = 'Records to be '+(mode=='insert'?'inserted':'updated');
            
            s = '<table class="tbmain" width="100%"><thead><tr>'; 

            if(mode=="update"){
                s = s + '<th width="30px">Record ID</th>';
            }       
            s = s + '<th width="20px">Line #</th>';

            //HEADER - field names
            var j, i=0, fieldnames = Object.keys(res['mapped_fields']);
            for(;i<fieldnames.length;i++){
                
                var colname = imp_session['columns'][fieldnames[i].substr(6)];
                s = s + '<th>'+colname+'</th>';
            }

            s = s + '</tr></thead>';
            
            //BODY
            var records = res['recs_'+mode];
            for(i=0;i<records.length;i++){

                var row = records[i];
                s = s + "<tr>";
                for(j=0;j<row.length;j++){
                    s = s +  '<td class="truncate">'+(row[j]?row[j]:"&nbsp;")+'</td>';
                }
                s = s + "</tr>";
            }
            
            s = s + '</table>';
        }
        
        if(s!=''){
            dlg_options['element'] = container.get(0);
            container.html(s);
            
            if(container.find("#tabs_records").length>0){
                    $("#tabs_records").tabs();
            }
            
            $dlg = top.HEURIST4.msg.showElementAsDialog(dlg_options);
        }
        
    } 

    //
    //
    //
    function _onUpdateModeSet(){
            if ($("#sa_upd2").is(":checked")) {
                $("#divImport2").css('display','block');
            }else{
                $("#divImport2").css('display','none');
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
        $("#divStep"+(page>2?3:page)).show();
        $('#divMatchingResult').hide();
        $('#mr_cnt_error').parent().hide();
        $('#mr_cnt_disamb').parent().hide();
        
        if(page==1){
            $('#selImportId').val('');  //clear selection
        }else if(page>2){  //matching and import
        
            $("div[class*='step'],h2[class*='step']").hide();
            $('.step'+page).show();
            $('#divPrepareResult').hide();
            
            if(page==3){
                $('.step3 > .normal').show();
                $('.step3 > .need_resolve').hide();
            }else{
                //show either prepare import or start import
                _onUpdateModeSet();
            }
            
        }
    }
    
    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        showRecords: function (mode) {_showRecords(mode)},
        showRecords2: function (mode, is_download) {_showRecords2(mode, is_download)},
        
        onUpdateModeSet:function (event){
            _onUpdateModeSet();
        }
        
    }

    _init(_imp_ID, _max_upload_size);
    return that;  //returns object
}
    
    