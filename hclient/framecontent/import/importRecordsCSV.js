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
    
    currentSeqIndex = -1,  
    
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

       $('#btnClearAllSessions').click(_doClearSession);
        
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
                            _showRecords2('all', true)  
                        });
        $('#btnClearFile')
                    .css({'width':'160px'})
                    .button({label: top.HR('Clear uploaded file'), icons:{secondary: "ui-icon-circle-close"}})
                    .click(function(e) {
                            _doClearSession(true);
                        });
                        
        $('#btnSetPrimaryRecType').click(_doSetPrimaryRecType); //reset primary rectype
        
        //init navigation links
        $.each($('.navigation'), function(idx, item){
            $(item).click( _getValuesFromImportTable );
        })

        //session id is defined - go to 3d step at once        
        if(imp_ID>0){
            _loadSession();
        }else{
            _showStep(1);
        }
        
        
        //--------------------------- action buttons init

        $('#btnMatchingStart')
                    //.css({'width':'250px'})
                    .css({'font-weight':'bold'})  //Match against existing records
                    .button({icons:{secondary: "ui-icon-circle-arrow-e"}})
                    .click(function(e) {
                            _doMatchingInit();
                        });
/*
        $('#btnMatchingSkip')
                    .button({icons:{secondary: "ui-icon-circle-arrow-e"}})
                    .click(function(e) {
                            _doMatching( true, null );
                        });
*/                        
        $('#btnBackToMatching')
                    //.css({'width':'250px'})
                    .button({label: top.HR('step 1: Match Again'), icons:{primary: "ui-icon-circle-arrow-w"}})
                    .click(function(e) {
                            _showStep(3);
                            _initFieldMapppingTable();
                        });
        
        $('#btnBackToMatching2')
                    //.css({'width':'250px'})
                    .button({label: top.HR('Match Again'), icons:{primary: "ui-icon-circle-arrow-w"}})
                    .click(function(e) {
                            _showStep(3);
                            _initFieldMapppingTable();
                        });
                        
        $('#btnResolveAmbiguous')
                    //.css({'width':'250px'})
                    .css({'font-weight':'bold'})
                    .button({label: top.HR('Resolve ambiguous matches')})
                    .click(function(e) {
                            _showRecords('disamb');
                        });
                        

        $('#btnPrepareStart')
                    //.css({'width':'250px'})
                    .css({'font-weight':'bold'})
                    .button({label: top.HR('Prepare'), icons:{secondary: "ui-icon-circle-arrow-e"}})
                    .click(function(e) {
                            _doPrepare();
                        });

        $('#btnImportStart')
                    //.css({'width':'250px'})
                    .css({'font-weight':'bold'})
                    .button({label: top.HR('Start Insert/Update'), icons:{secondary: "ui-icon-circle-arrow-e"}})
                    .click(function(e) {
                            _doImport();
                        });

/* repalced to help text                        
        $('#btnNextRecType1')
                    .button({label: top.HR('Skip to next record type'), icons:{secondary: "ui-icon-circle-arrow-e"}})
                    .click(function(e) {
                            _skipToNextRecordType();
                        });
        $('#btnNextRecType2')
                    .button({label: top.HR('Skip to next record type'), icons:{secondary: "ui-icon-circle-arrow-e"}})
                    .click(function(e) {
                            _showStep(3);
                            _skipToNextRecordType();
                        });
*/                        

          
          $(window).resize( function(e)
          {
                _adjustButtonPos();
          });          
                       
                       
          //TEST            _doSetPrimaryRecType(); 
    }

    //
    // Remove all sessions
    //
    function _doClearSession(is_current){
        
        if(is_current==true){
             recID = imp_ID;
        }else{
             recID = 0;
        }
        
        top.HEURIST4.util.setDisabled($('#btnClearAllSessions'), true);
        top.HAPI4.EntityMgr.doRequest({a:'delete', entity:'sysImportSessions', recID:recID},
                    function(response){
                        if(response.status == top.HAPI4.ResponseStatus.OK){
                            if(is_current==true){
                                $('#selImportId > option[value="'+recID+'"]').remove();
                                
                                if($('#selImportId > option').length>1){
                                    top.HEURIST4.util.setDisabled($('#btnClearAllSessions'), false);
                                }
                                _showStep(1);
                                top.HEURIST4.msg.showMsgDlg('Import session was cleared');
                            }else{
                                $('#selImportId').empty();
                                top.HEURIST4.msg.showMsgDlg('Import sessions were cleared');
                            }
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
                            $('#dependencies_preview').empty();
                            $('#lblPrimaryRecordType').empty();
                            $('#sa_rectype_sequence').empty();
                            
                            var resp = new hRecordSet( response.data );
                            var record = resp.getFirstRecord();
                            var ses = resp.fld(record, 'imp_session');
                            //DEBUG $('#divFieldMapping2').html( ses );
                            
                            imp_session = (typeof ses == "string") ? $.parseJSON(ses) : null;
                            
                            //init field mapping table
                            if(imp_session){
                                var sequence = imp_session['sequence'];
                                if(!sequence || $.isEmptyObject(sequence)){
                                    _doSetPrimaryRecType(true);
                                }else{
                                    _renderRectypeSequence();
                                }
                            }
                            
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
        
            if($('#sa_primary_rectype > option').length==0){
                top.HEURIST4.ui.createRectypeSelect( $('#sa_primary_rectype').get(0), null, top.HR('select...') );

                //reload dependency tree on select change
                $('#sa_primary_rectype').change( function(event){ 
                        var ele = $(event.target);
                        var treeElement = ele.parents('#divSelectPrimaryRecType').find('#dependencies_preview');
                        _loadRectypeDependencies( treeElement, ele.val() ); 
                });
                
            }else{
                $('#sa_primary_rectype').val(imp_session['primary_rectype']);
            }
        
            buttons[top.HR('OK')]  = function() {
                    
                    $dlg.dialog( "close" );
                    
                    if($('#sa_primary_rectype').val()!=imp_session['primary_rectype']){

                        imp_session['primary_rectype'] = $('#sa_primary_rectype').val();    
                        //prepare sequence object - based on selected rectypes and field names
                        
                         //find marked rectype checkboxes 
                         var treeElement = $('#dependencies_preview');
                         var recTypeID;
                         var rectypes = treeElement.find('.rt_select:checked');
                         rectypes.sort(function(a,b){ return $(a).attr('data-lvl') - $(b).attr('data-lvl')});
                         
                         var i,j,k, sequence = [];
                         for(i=0;i<rectypes.length;i++){
                            recTypeID = $(rectypes[i]).attr('data-rt');
                            //find names of identification fields
                            var ele = treeElement.find('.id_fieldname[data-res-rt="'+recTypeID+'"]');
                            for(j=0;j<ele.length;j++){
                                var isfound = false;
                                for(k=0;k<sequence.length;k++){
                                    if(sequence[k].field == $(ele[j]).text()){
                                        isfound = true;
                                        break;
                                    }
                                }
                                if(!isfound){
                                    sequence.push({field:$(ele[j]).text(), rectype:recTypeID});    
                                }
                            }
                         }
                         if(sequence.length==0){ //no dependencies - add main rectype only
                             recTypeID = imp_session['primary_rectype'];
                             var fname = _getColumnNameForPresetIndex(recTypeID);
                             sequence.push({field:fname, rectype:recTypeID});    
                         }
                        
                         imp_session['sequence'] = sequence;
                        
                        //save session - new primary rectype and sequence
                        var request = { action: 'set_primary_rectype',
                            sequence: imp_session['sequence'],
                            rty_ID: imp_session['primary_rectype'],
                            imp_ID: imp_ID,
                                id: top.HEURIST4.util.random()
                               };
                               
                            top.HAPI4.parseCSV(request, function( response ){
                                
                                if(response.status == top.HAPI4.ResponseStatus.OK){
                                    //and render sequence
                                    _renderRectypeSequence();
                                }else{
                                    _showStep(1);
                                    top.HEURIST4.msg.showMsgErr(response);
                                }
                            });
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
                height: 640,
                width: 900,
                element: document.getElementById('divSelectPrimaryRecType'),
                buttons: buttons
                };
            $dlg = top.HEURIST4.msg.showElementAsDialog(dlg_options);
            $dlg.addClass('ui-heurist-bg-light');
        
    }

    //
    // render sequence ribbon
    //
    function _renderRectypeSequence(){
        
        var i, sequence = imp_session['sequence'];

        $('#sa_rectype_sequence').empty();
        
        //sequence is not defined - select primary record type and define index field names
        if(!sequence || $.isEmptyObject(sequence)){
            _doSetPrimaryRecType(true);
            return false;
        }
        
        _showStep(3);
        
         $('img[id*="img_arrow"]').hide();
         var ele1 = $('#sa_rectype_sequence').empty();
         var s = '';
         
         var initial_selection = 0;
         
         for (i=0;i<sequence.length;i++){
                var recTypeID = sequence[i].rectype;
                var fieldname = sequence[i].field;

                var title = top.HEURIST4.rectypes.names[recTypeID];
                
                var counts = _getInsertUpdateCounts( i );
                if(!(counts[2]==0 && counts[0]>0)){ //not completely matching
                    initial_selection = i;
                }
                var s_count = _getInsertUpdateCountsForSequence( i ); //todo change to field                                    
                
                if(s!=''){
                    s = '<span class="ui-icon ui-icon-arrowthick-1-e rt_arrow" style="vertical-align:super"></span>'+s;
                }       
                s = '<h2 class="select_rectype_seq" data-seq-order="'
                        + i + '">' + title + '<span data-cnt="1" id="rt_count_'
                        + i + '">' + s_count + '</span><br><span data-cnt="1" class="id_fieldname" style="padding-left:0em">'
                        +fieldname+'</h2>'+s;
                 
                if(i==0){
                     $('#lblPrimaryRecordType').text(title);
                }
         }               
                         
         $(s).appendTo(ele1);
         
         $('.select_rectype_seq').click(function(event){

                var sp = $(event.target)
                if($(event.target).attr('data-cnt')>0){
                    sp = $(event.target).parent();
                }
             
                //get next id field
                var idx = sp.attr('data-seq-order');
                
                _skipToNextRecordType(idx);

         });
         
         //select first in sequence
         $('.select_rectype_seq[data-seq-order="'+initial_selection+'"]').click();    
        
         //_initFieldMapppingTable();    
        return true;
    }
    
    //
    // show dependecies list in popup dialog where we choose primary rectype
    //
    function _loadRectypeDependencies( treeElement, preview_rty_ID ){
            
            //request to server to get all dependencies for given primary record type
            var request = { action: 'set_primary_rectype',
                            sequence: null,
                            rty_ID: preview_rty_ID,
                            imp_ID: imp_ID,
                                id: top.HEURIST4.util.random()
                               };
                               
            top.HAPI4.parseCSV(request, function( response ){
                
                //that.loadanimation(false);
                if(response.status == top.HAPI4.ResponseStatus.OK){
                
                    var rectypes = response.data;
                    var rtOrder = _fillDependencyList(rectypes, {levels:{}, rt_fields:{}, depend:{} }, 0, {});    
                    //rt_fields - resourse fields
                    //depend - only required dependencies 
                    
                    var i, j, rt_resourse, rt_ids = Object.keys(rtOrder['levels']), isfound, depth = 0;
                    
                         //fill dependecies list in popup dialog where we choose primary rectype
                         treeElement.empty();
                         var primary_rt = 0;
                         
                         do{
                     
                             isfound = false;
                             for (i=0;i<rt_ids.length;i++){
                                 recTypeID = rt_ids[i];
                                 if(rtOrder['levels'][recTypeID] == depth){
                                     isfound = true;
                                     
                                     var sRectypeItem = '<div>' 
                                        + '<input type="checkbox" class="rt_select" data-rt="'+recTypeID+'" '
                                        +  ' data-lvl="'+depth+'" '
                                        + (depth==0?'checked="checked" disabled="disabled"':'')
                                        + '><b>'
                                        + top.HEURIST4.rectypes.names[recTypeID] 
                                        + '</b>';
                                     
                                     
                                     if(depth==0){ //add PRIMARY field
                                     
                                            //check if index field already defined in preset
                                            var sname = _getColumnNameForPresetIndex(recTypeID);
                                     
                                            sRectypeItem = sRectypeItem 
                                               + ' <span style="font-size:0.8em;font-weight:bold">(primary records type)</span>' 
                                               + '<span class="id_fieldname" style="float:right"  data-res-rt="'+recTypeID+'">'
                                               + sname + '</span>';
                                     }
                                     
                                     
                                     sRectypeItem = sRectypeItem + '</div>';
                                     $(sRectypeItem).appendTo(treeElement);
                                     
                                     
                                     var rt_fields = rtOrder['rt_fields'][recTypeID];
                                     if(rt_fields){
                                         for (j=0;j<rt_fields.length;j++){
                                             
                                               var field = rt_fields[j], sid_fields = '';
                                               //idfields
                                               for (rt_resourse in field['idfields']){
                                                   sid_fields = sid_fields 
                                                        + '<div style="padding-left:2em;display:inline-block;">'
                                                        + '<div style="min-width:150px;display:inline-block">'
                                                        + (sid_fields==''
                                                            ?'<i style="'+(field['required']?'color:red':'')+'">' +field['title'] + '</i>'
                                                            :'') + '</div>'
                                                        + '<span class="ui-icon ui-icon-arrowthick-1-e rt_arrow"></span>'
                                                        + top.HEURIST4.rectypes.names[rt_resourse] + '</div>' 
                                                        
                                                        + '<span style="float:right" class="id_fieldname rename" data-res-rt="'                                                                  + rt_resourse+'">' + field['idfields'][rt_resourse] + '</span><br>';
                                                         
                                               }
                                               
                                               $('<div style="padding-left:2em;">'
                                               //+'<span class="ui-icon ui-icon-triangle-1-e rt_arrow"></span>'
                                               
                                               //+ '<span class="ui-icon ui-icon-arrowthick-1-e rt_arrow"></span>'
                                               //+ field['rt_title']
                                               + sid_fields
                                               +'</div>')
                                                .appendTo(treeElement);
                                                
                                         }
                                     }
                                     
                                     if(depth==0){ 
                                        primary_rt = recTypeID
                                     }
                                 }
                                
                             }
                             depth++;
                         
                         }while(depth<10); //isfound);
                         
                         if(rt_ids.length==1){
                            treeElement.text('No dependencies found');
                         }else{
                             
                             
                            function __rt_checkbox_click(rt_checkbox){                                      
                            
                                    //keep id of clicked to avoid disability
                                     var i, j, keep_id = rt_checkbox.attr('data-rt');
                                    
                                    //all rt that will be checked and disabled
                                     var rt_depend_all = [];
                                     
                                     //get required fields for rectypes that are already marked
                                     $.each(treeElement.find('.rt_select:checked'),function(idx, item){
                                             var recTypeID = $(item).attr('data-rt');    
                                             rt_depend_all.push(recTypeID);
                                     });
                                     top.HEURIST4.util.setDisabled( treeElement.find('.rt_select'), false);
                                     
                                     // find all dependent rectypes
                                     rt_depend_all = _getCrossDependencies(rtOrder['depend'], rt_depend_all, []);

                                     //                                     
                                     if(!rt_checkbox.is(':checked')){
                                         
                                         //disable if marked rt has marked parent 
                                         for(i=0;i<rt_depend_all.length;i++){
                                             
                                             var recTypeID = rt_depend_all[i];
                                             
                                             var depth = rtOrder['levels'][recTypeID],
                                                 need_disable = false;
                                             
                                             if(depth==0){ 
                                                 need_disable = true;
                                             }else{
                                                 //find previous level
                                                 // rt_ids = Object.keys(rtOrder['levels'])
                                                 for(j=0;j<rt_depend_all.length;j++){
                                                    if(i!=j  //not itself
                                                        && (rtOrder['levels'][rt_depend_all[j]]==depth-1)    //from prev level
                                                        && (rtOrder['depend'][rt_depend_all[j]].indexOf(recTypeID)>=0))
                                                    {
                                                        need_disable = true;
                                                        break;    
                                                    }
                                                 }
                                             }
                                             
                                             if(need_disable){
                                                  var cb = treeElement.find('.rt_select[data-rt="'+recTypeID+'"]');
                                                  top.HEURIST4.util.setDisabled(cb, true);
                                             }

                                         }                                         
                                         
                                        /*
                                        var rt_depend_unchecked = _getCrossDependencies(rtOrder['depend'], rt_depend_enable, []);
                                     
                                        //find difference - these rectypes will NOT be disabled   
                                        for(i=0;i<rt_depend_unchecked.length;i++){
                                            if(rt_depend_all.indexOf(rt_depend_unchecked[i])<0){
                                                 rt_depend_enable.push(rt_depend_unchecked[i]);
                                            }
                                        }
                                        */
                                     }
                                     
                                     //mark and disable all of them
                                     for(i=0;i<rt_depend_all.length;i++){
                                            var cb = treeElement.find('.rt_select[data-rt="'+rt_depend_all[i]+'"]');
                                            cb.prop('checked',true);
                                            if(keep_id!=rt_depend_all[i]){
                                                top.HEURIST4.util.setDisabled(cb, true);
                                            }
                                     }
                                }                            
 
                            treeElement.find('.rt_select').click(
                                function(event){ __rt_checkbox_click($(event.target)) }
                            );
                            //click and disable first (primary) record type
                            
                            var cb = treeElement.find('.rt_select[data-rt="'+primary_rt+'"]');
                            cb.prop('checked',true);
                            __rt_checkbox_click(cb); //does not work.trigger('click'); //.click();
                            top.HEURIST4.util.setDisabled(cb, true);

                            //click to remame identification field                            
                            function __idfield_rename(ele_span){

                                //keep id of clicked to avoid disability
                                var i, j, dtyID = ele_span.attr('data-dt');
                                var idfield_name_old = ele_span.text();

                                //show popup to rename
                                top.HEURIST4.msg.showPrompt('Name of identifiecation field', function(idfield_name){
                                    if(!top.HEURIST4.util.isempty(idfield_name)){
                                        
                                        //set span content
                                        ele_span.html(idfield_name);
                                        //change value in rtStruct
                                        //rtOrder['idfields'][dtyID] = idfield_name;

                                    }
                                });



                            }
                            
                            treeElement.find('.rename').click(
                                function(event){ __idfield_rename($(event.target)) }
                            );

                         
                         }
                    
                }else{
                    _showStep(1);
                    top.HEURIST4.msg.showMsgErr(response);
                }

            });        

    }   

    
    //
    //
    //
    function _skipToNextRecordType(seq_index_next){
        
            seq_index_next = Number(seq_index_next);
            currentSeqIndex = Number(currentSeqIndex);
            var seq_index_prev = -1;
            
            if(seq_index_next>=0){ 
                if(seq_index_next<imp_session['sequence'].length-1){
                    seq_index_prev = seq_index_next+1;
                }
            }else{//not defined - take current and next
                seq_index_prev = currentSeqIndex;
                seq_index_next = currentSeqIndex>0?currentSeqIndex-1:0;
            }
        
            //is previous record type have matched records?
            var counts = _getInsertUpdateCounts( seq_index_prev ); 
            if(counts==null || counts[0]>0){
                 __changeRectype();
            }else{
                var sWarning = 
                'By skipping earlier steps in the workflow you may not have the required '
                +'record identifiers to use in record pointer fields. This may be OK if your aim '
                +'is simply to update fields other than pointer fields';
                
                top.HEURIST4.msg.showMsgDlg(sWarning, __changeRectype, 
                        {title:'Confirmation',yes:'Proceed',no:'Cancel'} );                                                             }
            
            function __changeRectype(){
                _showStep(3);
                
                currentSeqIndex = seq_index_next;
                _redrawArrow();
                _initFieldMapppingTable();
            }        
    } 
    
    //
    //
    //
    function _redrawArrow(){
        if(currentSeqIndex>=0){
                $('h2.select_rectype_seq').removeClass('ui-state-focus');
                var selitem = $('h2.select_rectype_seq[data-seq-order="'+currentSeqIndex+'"]').addClass('ui-state-focus');
                
                if(selitem && selitem.length>0){
                
                //position of arrow image
                var ileft = $('#divheader').offset().left+25;
                var iline_top = $('#divheader').offset().top-4;
                var iright = selitem.offset().left + selitem.width()/2;
                var itop = selitem.offset().top + selitem.height() + 7;

                if(currentStep>3){
                    ileft = $('#btnBackToMatching').offset().left+$('#btnBackToMatching').width()+5;
                }

                $('#img_arrow4').css({left: ileft, top: iline_top+1 });  //down to arrow
                $('#img_arrow3').css({left: ileft, top: iline_top+12 });  //arrow
                $('#img_arrow2').css({left: iright, top: itop, height: iline_top-itop+2 }); //line down
                if(ileft<iright){                
                    $('#img_arrow1').css({left: ileft, top: iline_top, width:(iright-ileft+2)});  //line horizontal
                }else{
                    $('#img_arrow1').css({left: iright, top: iline_top, width:(ileft-iright+2)});  //line horizontal
                }
                
                $('img[id*="img_arrow"]').show();
                
                }
        }
    }
    //
    // rt_depend_all - rectypes marked initially
    // rtOrder - rtOrder['depend']
    //
    function _getCrossDependencies(rtOrder, rt_depend_all, result_depend){
        
        var i, recTypeID;            
        for(i=0;i<rt_depend_all.length;i++){
            
            recTypeID =  rt_depend_all[i];
            
            if(result_depend.indexOf(recTypeID)<0){
                result_depend.push(recTypeID);
                if(rtOrder[recTypeID]){ //has required dependent rectypes
                    var result_depend = _getCrossDependencies(rtOrder, rtOrder[recTypeID], result_depend);    
                }
            }
        }

        return result_depend;
    }
    
    //
    // returns list of field/rectypes 
    
    //  with level (depth) 
    //    list of resource fields
    //    list of dependent rectype     
    //
    //  rtOrder 
    //  levels:{},   rt_id:level - list of rectypes with level value - need for proper order in sequence
    //  depend:{},   rt_id:[array of depended rectypes]
    //  rt_fields:{},   resourse fields per rectype - rt_id:{id:ft_id, title:title, rt_ids:ids, 
    //                     rt_title:rectypeNames.join('|'), required,
    //                     idfields:{NAME:resourse_rectypeid}}
    //
    //
    function _fillDependencyList(rectypeTree, rtOrder, depth, uniq_fieldnames){
         //var ele = $(selEle);
         
        if(depth==0){
            if($.isArray(rectypeTree) && rectypeTree.length>0){
                rectypeTree = rectypeTree[0];
                rtOrder['levels'][rectypeTree.key] = 0;
            }
        }         
         
         var title = top.HEURIST4.util.trim_IanGt(rectypeTree.title);
         var rectypes = top.HEURIST4.rectypes;
         
         var i, j, recTypeID, currentTypeID = 0;

         if(rectypeTree.type=='rectype'){
            currentTypeID = rectypeTree.key;
         }else  if (rectypeTree.constraint==1) { 
            currentTypeID = rectypeTree.rt_ids;   
         }

         var rt_fields = [], //resourse fields per rectype
             rt_depend = []; //dependent rectypes 
         
         //list all resourse fields
         if(top.HEURIST4.util.isArrayNotEmpty(rectypeTree.children)){
             for (j=0;j<rectypeTree.children.length;j++){
                 
                   var field = rectypeTree.children[j];
                   if(field.type!='rectype'){
                       
                       var title = top.HEURIST4.util.trim_IanGt(field.title); 
                       
                       var ids = field.rt_ids.split(',');
                       var rectypeNames = [], idfields={};
                       for (i=0;i<ids.length;i++){
                            recTypeID = ids[i];
                            rectypeNames.push(rectypes.names[recTypeID]);
                            
                            if(top.HEURIST4.util.isnull(rtOrder['levels'][recTypeID]) ||
                                (rtOrder['levels'][recTypeID]>0 && rtOrder['levels'][recTypeID] < depth+1))
                            {
                                rtOrder['levels'][recTypeID] = depth+1;    
                            }
                            
                            var id_fieldname = _getColumnNameForPresetIndex(recTypeID);
                            if(imp_session['primary_rectype']!=recTypeID){
                                var pos = id_fieldname.indexOf('Heurist ID');
                                if(id_fieldname.indexOf('Heurist ID')== id_fieldname.length-10){
                                    if(uniq_fieldnames[id_fieldname]>0){
                                        uniq_fieldnames[id_fieldname] = uniq_fieldnames[id_fieldname] + 1;
                                        id_fieldname = id_fieldname + ' ' + uniq_fieldnames[id_fieldname];
                                    }else{
                                        uniq_fieldnames[id_fieldname] = 1;
                                    }
                                }
                            }
                            
                            idfields[recTypeID] = id_fieldname;
                            
                            //rtOrder['idfields'][field['key']] = top.HEURIST4.rectypes.names[recTypeID]+' ID';//recTypeID;
                       }
                       
                       rt_fields.push({id:field['key'], title:title, rt_ids:ids, 
                                        rt_title:rectypeNames.join('|'), required:field['required'], idfields:idfields });
                       if(field['required']) rt_depend = $.unique(rt_depend.concat(ids));
                   }
             }
             
              if(currentTypeID && !rtOrder['rt_fields'][currentTypeID] && rt_fields.length>0){   //!!!
                    rtOrder['rt_fields'][currentTypeID] = rt_fields;
                    rtOrder['depend'][currentTypeID] = rt_depend; //only required dependencies 
              }
             
             for (j=0;j<rectypeTree.children.length;j++){
                  var child = rectypeTree.children[j];
                  rtOrder = _fillDependencyList(child, rtOrder, depth+((currentTypeID>0)?1:0), uniq_fieldnames);
             }
         }
         

         return rtOrder;
    }
    
    //check if index field already defined in preset
    //
    // imp_session['indexes'] = {field_1:10}  fieldname:recordtype_id
    //
    function _getColumnNameForPresetIndex(recTypeID){
        
        var k, sname = top.HEURIST4.rectypes.names[recTypeID] +' Heurist ID'; // this is default name for index field 
                                                                              // to be added into import table
        var rts = Object.keys(imp_session['indexes']);
        for(k=0; k<rts.length; k++){
            if(imp_session['indexes'][rts[k]]==recTypeID){
                var idx_id_fieldname = rts[k].substr(6); //'field_'
                sname = imp_session['columns'][idx_id_fieldname];
                break;
            }
        }
        
        return sname;
    }

    
    //
    // init field mapping table - main table to work with 
    //
    function _initFieldMapppingTable(){
    
        $('#tblFieldMapping > tbody').empty();
                        
        var sIndexes = "",
            sRemain = "",
            sProcessed = "", sID_field = '',
            i = 0,
            len = (imp_session && imp_session['columns'])?imp_session['columns'].length:0;

        //find index field for selected id field
        var stype = (currentStep==3)?'mapping_keys':'mapping_flds';
        var mapping_flds = imp_session['sequence'][currentSeqIndex][stype];  //field index=field type id
        if(!mapping_flds) mapping_flds = {};
        
        //all mapped fields - to place column in "proecessed" section
        var all_mapped = [];
        for  (i=0; i < imp_session['sequence'].length; i++) {
            for  (var fid in imp_session['sequence'][i][stype]) {
                fld = Number(fid);
                if(all_mapped.indexOf(fid)<0) all_mapped.push(Number(fid));
            }
        }
            
        var recStruc = top.HEURIST4.rectypes;    
        
        var idx_id_fieldname = _getFieldIndexForIdentifier(currentSeqIndex);
        
        if (idx_id_fieldname<0) { //id field is not created

            sIndexes = '<tr><td width="75px" align="center">&nbsp;'
                    //+ '<input type="checkbox" checked="checked" disabled="disabled"/>'
                    + '</td>'
                    + '<td  width="75px" align="center">0</td>' // count of unique values
                    + '<td style="width:300px;class="truncate">'+imp_session['sequence'][currentSeqIndex]['field']+'</td>' // column name
                    + '<td style="width:300px;">&nbsp;New column to hold Heurist record IDs</td><td>&nbsp;</td></tr>';
        }
        

        for (i=0; i < len; i++) {

            var isIDfield = (i==idx_id_fieldname);
            var isIndex =  !top.HEURIST4.util.isnull(imp_session['indexes']['field_'+i]);
            var isProcessed = !(isIDfield || isIndex || all_mapped.indexOf(i)<0 ); // top.HEURIST4.util.isnull(mapping_flds[i]) );
            
            //checkbox that marks 'in use'
            var s = '<tr><td width="75px" align="center">&nbsp;<span style="display:none">'
                    +'<input type="checkbox" id="cbsa_dt_'+i+'" value="'+i+'"/></span></td>';

            // count of unique values
            s = s + '<td  width="75px" align="center">'+imp_session['uniqcnt'][i]+'</td>';

            // column names                 padding-left:15px;  <span style="max-width:290px"></span>
            s = s + '<td style="width:300px;class="truncate">'+imp_session['columns'][i]+'</td>';

            // mapping selector
            s = s + '<td style="width:300px;">&nbsp;<span style="display:none;">'
                + '<select id="sa_dt_'+i+'" style="width:280px; font-size: 1em;" data-field="'+i+'" '
                + (isIndex||isIDfield?'class="indexes"':'')+'></select></span>';
            
            s = s + '</td>';

            // cell for value
            s = s + '<td id="impval'+i+'" style="text-align: left;padding-left: 16px;">&nbsp;</td></tr>';

            if(isIDfield){
                      sID_field = sID_field +s;
            }else if(isIndex){
                      sIndexes = sIndexes +s;
            }else if(isProcessed){
                      sProcessed = sProcessed +s;
            }else{
                      sRemain = sRemain +s;
            }
        }//for
        
        if(sID_field!=''){
            sID_field = '<tr height="40" style="valign:bottom"><td class="subh" colspan="5"><br /><b>Heurist ID</b></td></tr>'
                +sID_field;
        }
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
        
        $('#tblFieldMapping > tbody').html(sID_field+sIndexes+sRemain+sProcessed);
        
        //init listeners
        $("input[id^='cbsa_dt_']").change(function(e){
            var cb = $(e.target);
            var idx = cb.val();//attr('id').substr(8);
            if(cb.is(':checked')){
                $('#sa_dt_'+idx).parent().show();
            }else{
                $('#sa_dt_'+idx).parent().hide();
            }
            
            if(currentStep==5){
                _showStep(4); //reset to prepare step
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
        /* todo - check how it works with new layout
        //adjust position of footer with action buttons  
        var content = $('#divFieldMapping');
        var btm = $('#divStep3').height() - (content.position().top + $('#tblFieldMapping').height());
        btm = (btm<80) ?80 :btm;

        content.css('bottom', btm-20);
        $('#divImportActions').height(btm-20);
        */
    }
    
    //
    // by recordtype ID 
    //
    function _getFieldIndexForIdentifier(idx){
        
        if(imp_session['sequence'] && idx>=0 && idx<imp_session['sequence'].length && imp_session['sequence'][idx]){
            var id_fieldname = imp_session['sequence'][idx]['field'];
            var idx_id_fieldname = imp_session['columns'].indexOf(id_fieldname);
            return idx_id_fieldname;
        }else{
            return -1;
        }
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
        var keyfield_sel = '';
        
        var mapping_flds = null;
        if(currentSeqIndex>=0){
            
           
            
            if(currentStep!=3){
                mapping_flds = imp_session['sequence'][currentSeqIndex]['mapping_flds'];
            }else{
                _onMatchModeSet();
            }
            if(!mapping_flds || $.isEmptyObject(mapping_flds)){
                mapping_flds = imp_session['sequence'][currentSeqIndex]['mapping_keys'];
            }
        
            cbs.parent().show(); //show checkboxes
            
            //find key/index field for selected record type
            var idx = _getFieldIndexForIdentifier(currentSeqIndex);
            if(idx>=0){
                    keyfield_sel = 'sa_dt_'+idx;
                    $("#cbsa_dt_"+idx).attr('checked', true).attr('disabled',true);
                    $("#cbsa_dt_"+idx).parent().hide();
                    $('#sa_dt_'+idx).parent().show(); //show selector
            }
            
        }else{
            cbs.parent().hide();
        }

        //var mapping_flds = imp_session[(currentStep==3)?'mapping_keys':'mapping_flds'][rtyID];
        if(!mapping_flds) mapping_flds = {};

        
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
                
                var rtyID = imp_session['sequence'][currentSeqIndex]['rectype'];
                
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
               
               $(sel).change(function(){
                    if(currentStep==5){
                        _showStep(4); //reset to prepare step
                    }
               })
                    
            }
        });
        

        //for selected record type key field does not exist
        if(keyfield_sel==''){
            
        }
        
        
        //show counts
        if(currentSeqIndex>=0){
            
            //show counts in count table 
            counts = _getInsertUpdateCounts( currentSeqIndex );
            
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
            
            //show counts in sequence list
            var s_count = _getInsertUpdateCountsForSequence(currentSeqIndex);
            $('#rt_count_'+currentSeqIndex).html(s_count);
        
            $('#divFieldMapping2').show();

            
            //show hide skip to next rectype buttons if no insert, only update
            if(counts[2]==0 && counts[0]>0){
                $('.skip_step').css('display','inline-block');                
            }else{
                $('.skip_step').hide();                
            }
            
        }else{
            $('#divFieldMapping2').hide();
            $('.skip_step').hide();
        }
        
    }
    
    //
    // show insert/update count in sequence of record types
    //
    function _getInsertUpdateCountsForSequence(idx){
    
         var counts = _getInsertUpdateCounts(idx);                                     
         var s_count = '';
         if(counts[2]!=imp_session['reccount']){
             s_count = '<span style="font-size:0.8em"> [ '+ '<span title="Records matched">'+counts[0]+'</span> ' 
             +  (counts[2]>0?(', <span title="New records to create">'+counts[2]+' new</span>'):'')
             + ']</span>';
         }
         return s_count;
    }
    
    //
    // return counts array from imp_session, set it to default values if it is not defined
    //
    function _getInsertUpdateCounts(idx){
        
        if(idx>=0 && idx<imp_session['sequence'].length){
            var counts = imp_session['sequence'][idx]['counts'];
            
            if(!counts) {
                counts = [0,0,0,0];
                //reccount - total records in import table
                //uniqcnt - unique values per column
                var idx = _getFieldIndexForIdentifier(idx); 
                if(idx>=0){
                    //id column already exists in import table
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

                                var idx_id_fieldname = _getFieldIndexForIdentifier(currentSeqIndex);
                                
                                var isIndex =  (idx_id_fieldname==(i-1)) || !top.HEURIST4.util.isnull(imp_session['indexes']['field_'+(i-1)]);
                                
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
            top.HEURIST4.msg.showMsgErr('Please paste comma or tab-separated data into the content area below');    
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
                                        + '(determined by the first line of the file) = '
                                        + response.data['col_count']
                                        + '. Either change parse parameters or correct source data';
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
                          
                            var id_suggestions = []; 
                            //fill list of columns
                            for(i in response.data.fields){
                                
                                if(response.data.fields[i].indexOf('ID')>=0 || 
                                response.data.fields[i].toLowerCase().indexOf('identifier')>=0){
                                    id_suggestions.push(i);
                                }
                                
                                $('<tr><td style="width:200px">'+response.data.fields[i]+'</td>'
                                +'<td style="width:50px;text-align:center"><input type="checkbox" id="id_field_'+i+'" value="'+i+'"/></td>'
                                +'<td style="width:50px;text-align:center"><input type="checkbox" id="d_field_'+i+'" value="'+i+'"/></td>'
                                +'<td style="width:200px"><select id="id_rectype_'
                                +i+'" class="text ui-widget-content ui-corner-all" style="visibility:hidden"></select></td></tr>').appendTo(tbl);
                            }         
                            
                            
                            function __isAllRectypesSelectedForIdFields(){
                                    
                                    var res = false;
                                    var ele = $.find("input[id^='id_field_']");
                                    $.each(ele, function(idx, item){
                                            if($(item).is(':checked')){
                                                var rectypeid = $('#id_rectype_'+item.value).val();
                                                if(!rectypeid){
                                                    res = true;
                                                    return false;
                                                }
                                            }
                                    }); 
                                    return res;                                    
                            }
                            
                            var select_rectype = $("select[id^='id_rectype']").change(function(evt){
                                top.HEURIST4.util.setDisabled( $('#btnParseStep2'), __isAllRectypesSelectedForIdFields() );
                            });
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
                                $("select[id='id_rectype_"+ cb.val()+"']")  
                                        .css('visibility',  cb.is(':checked')?'visible':'hidden');            
                            
                                top.HEURIST4.util.setDisabled( $('#btnParseStep2'), __isAllRectypesSelectedForIdFields() );
                            });
                            
                            for(i=0; i<id_suggestions.length; i++){
                                $('#id_field_'+id_suggestions[i]).prop('checked',true);
                                $('#id_field_'+id_suggestions[i]).change();
                            }
                            

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
    // Show remarks/help according to current match mode and mapping in main table
    //
    function _onMatchModeSet(){
        
        var shelp = '';
        
        
        if(currentSeqIndex>=0 && imp_session['sequence'][currentSeqIndex]){
            var key_idx = _getFieldIndexForIdentifier(currentSeqIndex); 
            
            var rtyID = imp_session['sequence'][currentSeqIndex]['rectype'];
            
            if(key_idx<0){
                if($('#sa_match1').is(':checked')) $('#sa_match0').prop('checked', true);
                //top.HEURIST4.util.setDisabled($('#sa_match1'), true);
                //$('label[for="sa_match1"]').css('color','lightgray');
                $('#sa_match1').hide();
                $('label[for="sa_match1"]').hide();
            }else{
                $('#sa_match1').show();
                $('label[for="sa_match1"]').show();
                //top.HEURIST4.util.setDisabled($('#sa_match1'), false);
                //$('label[for="sa_match1"]').css('color','');
            }
            
            if($('#sa_match0').is(':checked')){ // normal matching
                
                 shelp = 'Please select one or more columns on which to match <b>'
                 + top.HEURIST4.rectypes.names[rtyID]
                 + '</b> in the incoming data against records already in the database.<br><br>';
                 
                if(key_idx>=0){
                    shelp = shelp + 'The existing identification field "'+imp_session['columns'][key_idx]+'" will be overwritten.' 
                }else{
                    shelp = shelp + 'New identification field will be created.' 
                }

                shelp = shelp + ' Matching sets this ID field for existing records and allows '
                    +'the creation of new records for unmatched rows.';
                
            }else if($('#sa_match1').is(':checked')){ // use id field
                
                shelp = 'The existing identification field "'+imp_session['columns'][key_idx]+'" will be used.<br><br>';
                
                var counts = _getInsertUpdateCounts( currentSeqIndex );
                
                if(counts && counts[1]>0){
                       shelp = shelp + 'It appears that you already have <b>'
                            + top.HEURIST4.rectypes.names[rtyID]
                            + '</b>. '+counts[1]+' rows in import table that match for '
                            + (counts[0]!=counts[1]?counts[0]:'')+' existing records';
                      if(counts[2]>0){
                            shelp = shelp + ' and '+counts[2]+' records will be added';       
                      }
                      shelp = shelp + '.';   
                }else{
                
                    shelp = shelp + 'It does not match any <b>'
                            +top.HEURIST4.rectypes.names[rtyID]+'</b> record, hence '
                                +(key_idx>=0 && imp_session['uniqcnt'][key_idx]>0
                                        ?imp_session['uniqcnt'][key_idx]:imp_session['reccount'])
                                +' records will be added.';
                    
                }
                
            }else if($('#sa_match2').is(':checked')){  //skip matching

                shelp = 'By choosing not to match the incoming data, you will create '
                    +imp_session['reccount']+' new <b>'
                    +top.HEURIST4.rectypes.names[rtyID]+'</b> records - that is one record for every row in import file?<br><br>';

                if(key_idx>=0){
                    shelp = shelp + ' The identification field "'+imp_session['columns'][key_idx]+'" will be filled with new record IDs.' 
                }
                
            }

            if($('#sa_match2').is(':checked')){  //skip matching
                $('#btnMatchingStart').button({label:'Skip matching (import all as new)'});
            }else{
                $('#btnMatchingStart').button({label:'Match against existing records'});
            }
        
        }
        $('#divMatchingSettingHelp').html(shelp);
        
        
    }
 
    //
    //
    //
    function _doMatchingInit(){
        isSkipMatch = $('#sa_match2').is(':checked');
        _doMatching( isSkipMatch, null );
    }
        
    //
    // Find records in Heurist database and assign record id to identifier field in import table
    //
    function _doMatching( isSkipMatch, disamb_resolv ){
        
        if(currentSeqIndex>=0){
        
            var sWarning = '';
            var haveMapping = false; 
            //do we have id field?
            var key_idx = _getFieldIndexForIdentifier(currentSeqIndex); 
            //do we have field to match?
            var field_mapping = {};
            if(!isSkipMatch){
                var ele = $("input[id^='cbsa_dt_']");
                
                $.each(ele, function(idx, item){
                    var item = $(item);
                    if(item.is(':checked')){ // && item.val()!=key_idx){
                        var field_type_id = $('#sa_dt_'+item.val()).val();
                        if(field_type_id && item.val()!=key_idx){ //except id field
                            field_mapping[item.val()] = field_type_id;
                            haveMapping = true;
                        }
                    }
                });
            }
            
            if($('#sa_match0').is(':checked') && !haveMapping){
                top.HEURIST4.msg.showMsgErr('Please select the fields on which you wish to match the data read '
                        +'with records already in the database (if any)');
                return;
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
            
            
            if(false && sWarning){
                top.HEURIST4.msg.showMsgDlg(sWarning, __doMatchingStart, {title:'Confirmation',yes:'Proceed',no:'Cancel'});
            }else{
                __doMatchingStart();
            }

       function __doMatchingStart(){    
         
            var request = {
                imp_ID    : imp_ID,
                action    : 'step3',
                sa_rectype: imp_session['sequence'][currentSeqIndex]['rectype'],
                seq_index: currentSeqIndex,
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
                                
                                top.HEURIST4.msg.showMsgErr('One or more rows in your file match multiple records in the database.<br>'+
                        'Please click <b>Resolve ambiguous matches</b> to view and resolve these ambiguous matches.<br><br> '+
                        'If you have many such ambiguities you may need to select adidtional key fields or edit the incoming '+
                        'data file to add further matching information.');
                        
                                $('.step3 > .normal').hide();
                                $('.step3 > .skip_step').hide();
                                $('.step3 > .need_resolve').show();
                        
                        
                            }else{
                                //ok - go to next step
                                _showStep(4);
                                _initFieldMapppingTable();
                                $('#mr_cnt_disamb').parent().hide();
                                
                            }
                            
                        }else{
                            _showStep(3);
                            top.HEURIST4.msg.showMsgErr(response);
                        }
                    }
            );        
        }
        
            
        
        }else{
            top.HEURIST4.msg.showMsgErr(top.HR('You have to select record type'));
        }
        
    }         
    
    //
    //
    //
    function _doPrepare(){
        
        currentSeqIndex = Number(currentSeqIndex);
        if(!(Number(currentSeqIndex)>=0)){
            top.HEURIST4.msg.showMsgErr(top.HR('You have to select record type'));
            return;
        }
        
        var rtyID = imp_session['sequence'][currentSeqIndex]['rectype'];
        var key_idx = _getFieldIndexForIdentifier(currentSeqIndex); 
        if(!(key_idx>=0)){
            top.HEURIST4.msg.showMsgErr('You must select a record identifier column for <b>'
                + top.HEURIST4.rectypes.names[ rtyID ]
                +'</b> in the first section below. This is used to identify the records to be created/updated');
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
            top.HEURIST4.msg.showMsgErr(
'You have not mapped any columns in the incoming data to fields in the record, '
+'so the records created would be empty. Please select the fields which should '
+'be imported into "'+top.HEURIST4.rectypes.names[rtyID]+'" records.');
            
            
            return;
        }
        
        var request = {
            db        : top.HAPI4.database,
            imp_ID    : imp_ID,
            action    : 'step4',
            sa_rectype: rtyID,
            seq_index: currentSeqIndex,
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
                            $('.step3 > .skip_step').hide();
                            $('.step3 > .need_resolve').show();
                        }else{
                            _showStep(5);
                        }

                    }else{
                        _showStep(4);
                        top.HEURIST4.msg.showMsgErr(response);
                    }
                
                });
        
    } 
    
    
    function _doImport(){

        currentSeqIndex = Number(currentSeqIndex);
        if(!(Number(currentSeqIndex)>=0)){
            top.HEURIST4.msg.showMsgErr(top.HR('You have to select record type'));
            return;
        }
        var rtyID = imp_session['sequence'][currentSeqIndex]['rectype'];
        var key_idx = _getFieldIndexForIdentifier(currentSeqIndex); 

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
            top.HEURIST4.msg.showMsgErr(
'You have not mapped any columns in the incoming data to fields in the record, '
+'so the records created would be empty. Please select the fields which should '
+'be imported into "'+top.HEURIST4.rectypes.names[rtyID]+'" records.');
            return;
        }
        
        var request = {
            db        : top.HAPI4.database,
            imp_ID    : imp_ID,
            action    : 'step5',
            sa_rectype: rtyID,
            seq_index : currentSeqIndex,
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
                        
                        var msg = 'Import of <b>'+top.HEURIST4.rectypes.names[rtyID]+'</b> records completed.<br>'
                          +'<table class="tbresults"><tr><td>Total rows in import table:</td><td>'+ imp_result['total']
                          +'</td></tr><tr><td>Processed:</td><td>'+ imp_result['processed']
                          +'</td></tr><tr><td>Skipped:</td><td>'+ imp_result['skipped']
                          +'</td></tr><tr><td>Records added:</td><td>'+ imp_result['inserted']
                          +'</td></tr><tr><td>Records updated:</td><td>'+ imp_result['updated']
                          +'</td></tr></table>';
                        
                        top.HEURIST4.msg.showMsgDlg(msg, null, 'Import result');
                        
                        //if everything is added - skip to next step
                        var counts = _getInsertUpdateCounts( currentSeqIndex );
                        if(counts[2]==0 && counts[0]>0){ //completely matching - go to next rectype
                                _showStep(3);
                                _renderRectypeSequence();
                                //$('.select_rectype_seq[data-seq-order="'+(currentSeqIndex-1)+'"]').click();    
                        }
                                                
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
            recCount = _getInsertUpdateCounts(currentSeqIndex),
            start_idx = 0;
            
        if(recCount==null) return;
        
        recCount = mode=='all'?imp_session['reccount']:recCount[mode=='insert'?3:1];

        var id_field = null;         
        
        var index_field_idx = _getFieldIndexForIdentifier(currentSeqIndex);
        if(index_field_idx>=0){
             id_field = 'field_'+index_field_idx;
        }
     
        
        var mapping_flds = null;
        if(currentStep!=3){  //import step
            mapping_flds = imp_session['sequence'][currentSeqIndex]['mapping_flds'];
        }
        if(!mapping_flds || $.isEmptyObject(mapping_flds)){
            mapping_flds = imp_session['sequence'][currentSeqIndex]['mapping_keys'];
        }
        //var mapping_flds = imp_session[(currentStep==3)?'mapping_keys':'mapping_flds'][rtyID];
        if(!mapping_flds) mapping_flds = {};
        
        if(!is_download){
        
            dlg_options['title'] = 'Records to be '+(mode=='insert'?'inserted':'updated');
            
            s = '<div class="ent_wrapper"><div style="padding:8px 0 0 10px" class="ent_header">'
                +'<a href="#" class="navigation2" style="display: inline-block;"><span data-dest="first" class="ui-icon ui-icon-seek-first"/></a>'
                +'<a href="#" class="navigation2" style="display:inline-block;"><span data-dest="-1" class="ui-icon ui-icon-triangle-1-w"/></a>'
                +'<div style="display: inline-block;vertical-align: super;">Range <span id="current_range"></span></div>'
                +'<a href="#" class="navigation2" style="display: inline-block;"><span data-dest="1" class="ui-icon ui-icon-triangle-1-e"/></a>'
                +'<a href="#" class="navigation2" style="display: inline-block;"><span data-dest="last" class="ui-icon ui-icon-seek-end"/></a></div>';
            
                
            s = s + '<div class="ent_content_full"><table class="tbmain" style="font-size:0.9em" width="100%"><thead><tr>'; 

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
        
        }
        
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
                                    mode: mode,
                                    output: (is_download)?'csv':'json',
                                    offset: is_download?0:offset,
                                    limit: is_download?0:limit,
                                    table:currentTable,
                                    id: top.HEURIST4.util.random()
                                       };
                                       
                    if(is_download){

                       request['db'] = top.HAPI4.database;
                       request['mapping'] = JSON.stringify(request['mapping']);
                        
                       var keys = Object.keys(request) 
                       var params = [];
                       for(var k=0;k<keys.length;k++){
                           if(!top.HEURIST4.util.isempty(request[keys[k]])){
                                params.push(keys[k]+'='+request[keys[k]]);
                           }
                       }
                       var params = params.join('&');
                       var url = top.HAPI4.basePathV4 + 'hserver/controller/fileParse.php?'+params;
                        
                       top.HEURIST4.util.downloadURL(url);
                        
                    }else{
                    
                        top.HAPI4.parseCSV(request, function( response ){
                            
                            //that.loadanimation(false);
                            if(response.status == top.HAPI4.ResponseStatus.OK){
                            
                                    var response = response.data;
                                    
                                    var table = $dlg.find('.tbmain > tbody');
                                
                                    var i,j, s = '';
                                    $dlg.find("#current_range").html( (offset+1)+':'+(offset+limit) );

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
                        var colname = imp_session['columns'][tabs[k]['field_checked'].substr(6)]; //field_
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
                    var rtyID = imp_session['sequence'][currentSeqIndex]['rectype'];
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
                
            if(currentStep==4){ //prepare
            
                $('h2.step4').css('display','inline-block');
            
                var rtyID = imp_session['sequence']['rectype'];
                var counts = _getInsertUpdateCounts(currentSeqIndex);
               
                var shelp = 'Now select the columns which you wish to import into fields in the <b>'
                + top.HEURIST4.rectypes.names[rtyID]
                + '</b>  records which are '
                + (counts[2]>0?'created ':'')
                + ((counts[0]>0 && counts[2]>0)?' or ':'')
                + (counts[0]>0?'updated':'')
                + (counts[2]>0?'. Since new records are to be created, make sure you select all relevant columns; '
                                +'all Required fields must be mapped to a column.':'');
                
                shelp = shelp + '<br><br>Note that no changes are made to the database when you click the Prepare button.';
                
                $('#divPrepareSettingHelp').html(shelp);
                
                top.HEURIST4.util.setDisabled($('#btnPrepareStart'), false);
                top.HEURIST4.util.setDisabled($('#btnImportStart'), true);
            }else{ //import
            
                $('h2.step5').css('display','inline-block');
                top.HEURIST4.util.setDisabled($('#btnPrepareStart'), true);
                top.HEURIST4.util.setDisabled($('#btnImportStart'), false);
                //$('#divImportSettingHelp').html();
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
        $('#mr_cnt_error').parent().hide();
        $('#mr_cnt_disamb').parent().hide();
        
        if(page==1){
            $('#selImportId').val('');  //clear selection
            imp_ID = 0;
        }else if(page>2){  //matching and import
        
            $("div[class*='step'],h2[class*='step']").hide();
            $('.step'+page).show();
            
            _redrawArrow();            
            
            if(page==3){
                $('.step3 > .normal').show();
                $('.step3 > .need_resolve').hide();
                _onMatchModeSet();
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
        },
        onMatchModeSet:function (event){
            _onMatchModeSet();
        }
        
    }

    _init(_imp_ID, _max_upload_size);
    return that;  //returns object
}
    
    