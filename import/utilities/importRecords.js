/**
* Class to import records from JSON/XML
* 
* @returns {Object}
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
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

function hImportRecords(_imp_ID, _max_upload_size, _format) {
    var _className = "ImportRecords",
    _version   = "0.4",
    
    upload_file_name,  //file on server with original uploaded data
    
    function _init(_imp_ID, _max_upload_size, format){
    
        imp_ID = _imp_ID;
        
        if(format=='xml'){
            //$('#divKmlIntro').show();
            //$('.format-csv').hide();
        }else{
            //$('.format-csv').show();
            //$('#divKmlIntro').hide();
        }

        var uploadWidget = $('#uploadFile');
        
        //init STEP 1  - upload
        $('button').button();
                    
        //buttons
        $('#btnUploadData').click(function(e) {
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
        
            uploadData = data;//keep this object to use conviniece methods (abort for instance)
            data.submit(); 

        },
        //send: function (e, data) {},
        //start: function(e, data){},
        //change: function(){},
        error: function (jqXHR, textStatus, errorThrown) {
            //!!! $('#upload_form_div').show();
            _showStep(0);
            pbar_div.hide();
            if(textStatus!='abort'){
                window.hWin.HEURIST4.msg.showMsgErr(textStatus+' '+errorThrown);
            }
        },
        done: function (e, response) {

                //!!! $('#upload_form_div').show();                
                pbar_div.hide();       //hide progress bar
                response = response.result;
                if(response.status==window.hWin.ResponseStatus.OK){  //after upload
                    var data = response.data;
                    $.each(data.files, function (index, file) {
                        if(file.error){
                            window.hWin.HEURIST4.msg.showMsgErr(file.error);
                        }else{
                            
                            upload_file_name = file.name;
                            
                            //save session - new primary rectype and sequence
                            var request = { action: 'import_preview',
                                filename: upload_file_name,
                                id: window.hWin.HEURIST4.util.random()
                            };
                                   
                            window.hWin.HAPI4.doImportAction(request, function( response ){
                                    
                                    if(response.status == window.hWin.ResponseStatus.OK){
                                        //and render sequence
                                        _showStep(1);
                                    }else{
                                        _showStep(0);
                                        window.hWin.HEURIST4.msg.showMsgErr(response);
                                    }
                                });
                            
                            
                        }
                    });
                }else{
                    //$('#divFieldRolesHeader').show();
                    _showStep(0);
                    window.hWin.HEURIST4.msg.showMsgErr(response.message);
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
    function _importDefinitions(){
        
            //save session - new primary rectype and sequence
            var request = { action: 'import_defintions',
                filename: upload_file_name,
                id: window.hWin.HEURIST4.util.random()
            };
                   
            window.hWin.HAPI4.doImportAction(request, function( response ){
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        //and render sequence
                        _showStep(2);
                    }else{
                        _showStep(1);
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
        
    }        

    //
    //
    //
    function _importRecords(){
        
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
        $("#divStep"+(page>2?2:page)).show();
    }
    
    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},
    }

    _init(_imp_ID, _max_upload_size, _format);
    return that;  //returns object
}
    
    