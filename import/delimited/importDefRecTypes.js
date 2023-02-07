/**
* Class to import record types from CSV
*
* @returns {Object}
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Brandon McKay   <blmckay13@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

function hImportDefRecTypes() {
    var _className = "ImportDefRecTypes",
    _version   = "0.6",
    
    _parseddata = null,
    _prepareddata = null;
    
    function _init(){

        var uploadWidget = $('#uploadFile');
        
        //buttons
        var btnUploadFile = $('#btnUploadFile')
                    .css({'xwidth':'120px','font-size':'0.8em'})
                    .button({label: window.hWin.HR('Upload File')})
                    .click(function(e) {
                            uploadWidget.click();
                        });
        var btnParseData = $('#btnParseData')
                    .css({'width':'120px'})
                    .button({label: window.hWin.HR('Analyse'), icons:{secondary: "ui-icon-circle-arrow-e"}})
                    .click(function(e) {
                            _doParse();
                        });
        var btnStartImport = $('#btnImportData')
                    .css({'width':'110px'})
                    .addClass('ui-button-action')
                    .button({label: window.hWin.HR('Import'), icons:{secondary: "ui-icon-circle-arrow-e"}})
                    .click(function(e) { _doPost(); });
                        
        $('#csv_header').change(_redrawPreviewTable);

        window.hWin.HEURIST4.ui.createRectypeGroupSelect($('#field_rtg')[0], [{key: 0, title: 'select rectype group...'}]);
        $('#field_rtg').change(function(e){
            var label = $('#field_rtg').find(':selected').text();

            if($('#field_rtg').hSelect('instance') !== undefined){
                $('#field_rtg').hSelect('widget').attr('title', label);
            }else{
                $('#field_rtg').attr('title', label);
            }

            if($('#field_rtg').val() == 0){
                window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), true);
            }else{
                _doPrepare();
            }
        });

        window.hWin.HEURIST4.util.setDisabled(btnStartImport, true);
         
        var src_content = ''; 
        
        $('#sourceContent').keyup(function(e){
            if(src_content != $(this).val().trim()){
                src_content = $(this).val().trim();
            }
        })                        

        uploadWidget.fileupload({
            url: window.hWin.HAPI4.baseURL +  'hsapi/controller/fileUpload.php', 
            formData: [ {name:'db', value: window.hWin.HAPI4.database}, 
                        {name:'entity', value:'temp'}, //to place file into scratch folder
                        {name:'max_file_size', value:1024*1024}], //'1024*1024'
            autoUpload: true,
            sequentialUploads:true,
            dataType: 'json',
            done: function (e, response) {
                response = response.result;
                if(response.status==window.hWin.ResponseStatus.OK){
                    var data = response.data;
                    $.each(data.files, function (index, file) {
                        if(file.error){
                            $('#sourceContent').val(file.error);
                        }else{
                            
                            var url_get = file.deleteUrl.replace('fileUpload.php','fileGet.php')
                                +'&encoding='+$('#csv_encoding').val()+'&&db='+window.hWin.HAPI4.database;
                            
                            $('#sourceContent').load(url_get, null);
                        }
                    });
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response.message);
                }
                 
                var inpt = this;
                btnUploadFile.off('click');
                btnUploadFile.on({click: function(){
                            $(inpt).click();
                }});                
            }
        });

        $('.column_roles').change(function(e){ 

            var ele = $(e.target);
            if(ele.val()>=0){
                $('.column_roles').each(function(idx, item){
                   if($(item).attr('id')!= ele.attr('id') && $(item).val() == ele.val()){
                       $(item).val(-1);
                   }
                }); 
            }       
            
            //form update array
            _doPrepare();
        });
    }

    
    //
    //
    //
    function _redrawPreviewTable(){
        
        if(_parseddata==null) return;
    
        var i, j, maxcol = 0;
        for(i in _parseddata){
            if(window.hWin.HEURIST4.util.isArrayNotEmpty(_parseddata[i])){
                maxcol = Math.max(maxcol,_parseddata[i].length);
            }
        }
       
        var container = $('#divParsePreview').empty();    
        var tbl  = $('<table>')
                    .addClass('tbmain')
                    .appendTo(container);
                    
        //HEADER FIELDS            
        var headers = [], ifrom=0;
        if( $('#csv_header').is(':checked') ){ 
            
            for(i=0;i<_parseddata.length;i++){
                if(window.hWin.HEURIST4.util.isArrayNotEmpty(_parseddata[i])){
                    
                    for(j=0;j<maxcol;j++){
                        if(j>=_parseddata[i].length || window.hWin.HEURIST4.util.isempty(_parseddata[i][j])){
                            headers.push('column '+j);     
                        }else{
                            headers.push(_parseddata[i][j]); 
                        }
                    }
                    ifrom = i+1;
                    break;
                }
            }
        }else{
            for(j=0;j<maxcol;j++){
                headers.push('column '+j);
            }
        }
        
        //TABLE HEADER
        var tr  = $('<tr>').appendTo(tbl);
        for(j=0;j<maxcol;j++){
            
            var cs = {};
            if(maxcol>3){
                cs['width'] = ((j==0)?20:((j==maxcol-1)?40:10))+'%';
            }
            
            $('<th>').css(cs)
                .addClass('truncate')
                        .text(headers[j]).appendTo(tr);
        }
        
        //TABLE BODY
        for(i=ifrom;i<_parseddata.length;i++){
            var tr  = $('<tr>').appendTo(tbl);
            if(window.hWin.HEURIST4.util.isArrayNotEmpty(_parseddata[i])){
                for(j=0;j<maxcol;j++){
                    
                    $('<td>').addClass('truncate')
                        .text(j<_parseddata[i].length?_parseddata[i][j]:' ').appendTo(tr);
                }
            }
        }
        
        //COLUMN ROLES SELECTORS
        $('.column_roles').empty();
        for(j=-1; j<maxcol; j++){
            var opt = $('<option>',{value:j, text:(j<0)?'select...':headers[j]});                                    
            opt.appendTo($('#field_name'));
            opt.clone().appendTo($('#field_desc'));
            opt.clone().appendTo($('#field_uri'));
        }
        if(maxcol>0){
            $('#field_name').val(0);
            
            //AUTODETECT COLUMN ROLES by name
            for(j=0;j<maxcol;j++){
                var s = headers[j].toLowerCase();
                if(s.indexOf('name')>=0 || s.indexOf('RecTypeName')>=0){
                    $('#field_name').val(j);
                }else if(s=='description'){
                    $('#field_desc').val(j);
                }else if(s.indexOf('uri')>=0 || s.indexOf('url')>=0 || 
                    s.indexOf('reference')>=0 || s.indexOf('semantic')>=0 ){
                    $('#field_uri').val(j);
                }
            }

            _doPrepare();
        }
    }
    
    //
    // parse CSV on server side
    //
    function _doParse(){

        var content = $('#sourceContent').val();

        if(content==''){
            window.hWin.HEURIST4.msg.showMsgFlash('No content entered', 1500);
        }else{
        
            window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));
    
            var request = { content: content,
                            csv_delimiter: $('#csv_delimiter').val(),
                            csv_enclosure: $('#csv_enclosure').val(),
                            csv_linebreak: 'auto',
                            id: window.hWin.HEURIST4.util.random()
                           };
                           

            window.hWin.HAPI4.doImportAction(request, function( response ){

                window.hWin.HEURIST4.msg.sendCoverallToBack();

                if(response.status == window.hWin.ResponseStatus.OK){

                    _parseddata = response.data;
                    
                    $('#csv_header').prop('checked', _parseddata && _parseddata.length>0 && _parseddata[0].length>1);
                    
                    if (!$('#csv_header').is(':checked')) {
                        var firstline_without_quotes = false;
                        var pos = content.indexOf($('#csv_enclosure').val()==2?'"':"'");

                        for(var i=0; i<_parseddata.length; i++){
                            if(window.hWin.HEURIST4.util.isArrayNotEmpty(_parseddata[i])){
                                var len = _parseddata[i].join(',').length;
                                firstline_without_quotes = pos>len;
                                break;
                            }
                        }
                        if(firstline_without_quotes){
                            $('#csv_header').prop('checked', true);  
                        } 
                    }

                    _redrawPreviewTable();
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
        }
    }

    // prepare update array
    //
    function _doPrepare(){
        
        var msg = '';
        
        _prepareddata = [];
        
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(_parseddata)){
            msg = '<i>No data. Upload and parse</i>';
        }else{

            var field_name = $('#field_name').val();
            var field_desc = $('#field_desc').val();

            if(field_name<0 || field_desc<0){

                if(field_name<0) { 
                    msg = 'Name'; 
                }
                if(field_desc<0) { 
                    msg = msg=='' ? 'Description' : ' and Description'; 
                }
                msg = '<span style="color:red">'+ msg +' must be defined</span>';
            }else{

                var field_uri = $('#field_uri').val();
                var i, record, skip_na = 0, skip_dup = 0, skip_long = 0;
                        
                var hasHeader = $('#csv_header').is(':checked');
                i = hasHeader?1:0;        
                        
                for(;i<_parseddata.length;i++){
                    
                    record = {};
                    
                    if(field_name>=_parseddata[i].length) continue;
                    
                    var name = null;
                    
                    if(!window.hWin.HEURIST4.util.isempty(_parseddata[i][field_name])){
                        name = _parseddata[i][field_name].trim();
                    }

                    if(!window.hWin.HEURIST4.util.isempty(name)){

                        if(name.length>500){
                            skip_long++;    
                        }

                        record['rty_Name'] = name;
                        
                        if(field_desc>-1 && field_desc<_parseddata[i].length){
                            record['rty_Description'] = _parseddata[i][field_desc];
                        }
                        if(field_uri>-1 && field_uri<_parseddata[i].length){
                            record['rty_SemanticReferenceURL'] = _parseddata[i][field_uri];
                        }
                       
                        _prepareddata.push(record);
                    }else{
                        skip_na++;
                    }
                }//for

                $('#preparedInfo2').html('');
                
                if(_prepareddata.length==0){
                    msg = '<span style="color:red">No valid data to import</span>';   
                }else{
                    //msg = 'Ready to import: n='+_prepareddata.length;//+' entr'+((_prepareddata.length>1)?'ies':'y');
                    $('#preparedInfo2').html('n = '+_prepareddata.length);
                }
                if(skip_na>0 || skip_dup>0 || skip_long>0){
                    msg = msg + '&nbsp;&nbsp;Record Type Name is';
                }
                if(skip_na>0){
                    msg = msg + ' not defined for '+skip_na+' row'+((skip_na>1)?'s;':';');    
                }
                if(skip_dup>0){
                    msg = msg + ' duplicated for '+skip_dup+' row'+((skip_dup>1)?'s;':';');    
                }
                if(skip_long>0){
                    msg = msg + ' very long (>500 chars) for '+skip_long+' row'+((skip_long>1)?'s':'');    
                }
                
                
            }
        
        }
        
        window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), (_prepareddata.length<1 || _prepareddata.length==skip_na || $('#field_rtg').val() == 0));
        
        $('#preparedInfo').html(msg);
    }
    
    
    //
    // save record types
    //
    function _doPost(){
        
        if(_prepareddata.length<1) return;

        var rtgID = $('#field_rtg').val();

        if(rtgID == 0){
            window.hWin.HEURIST4.msg.showMsgFlash('Please select a record type group', 2000);
            return;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));

        var request = {
            'a'          : 'batch',
            'entity'     : 'defRecTypes',
            'request_id' : window.hWin.HEURIST4.util.random(),
            'fields'     : JSON.stringify(_prepareddata),
            'csv_import' : 1,
            'rtg_ID'     : rtgID
        };
    
        var that = this;

        window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                
                if(response.status == window.hWin.ResponseStatus.OK){

                    var results = response.data;
                    var $tbl = $('.tbmain');
                    var $rows = $tbl.find('tr');
                    var col_num = 0;
                    var update_cache = false;

                    // Add result header
                    if($($rows[0]).find('.post_results').length == 0){
                        $('<th>').addClass('post_results truncate').text('Results').appendTo($($rows[0]));
                    }else{
                        col_num = $($rows[0]).find('th').length - 1;
                    }

                    // Add result data
                    for(var i = 1; i < $rows.length; i++){

                        var $row = $($rows[i]);
                        var data = results[i-1];

                        if($row.length == 0){
                            break;
                        }

                        if(data.indexOf('Created') >= 0){
                            update_cache = true;
                        }

                        if(col_num == 0){
                            $('<td>').addClass('truncate').text(data).appendTo($row);
                        }else{
                            $($row.find('td')[col_num]).text(data);
                        }
                    }

                    if(update_cache){
                        window.hWin.HAPI4.EntityMgr.refreshEntityData('rty', null); // update cache
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
        
    }
    
    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

    }

    _init();
    return that;  //returns object
}