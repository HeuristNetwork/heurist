/**
* Class to import record fields from CSV, also assign directly to record types
*
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
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

function hImportDefDetailTypes(_dtg_ID = null) {
    const _className = "ImportDefDetailTypes",
    _version   = "0.6";
    
    let _parseddata = null,
    _prepareddata = null,
    
    _return_results = !window.hWin.HEURIST4.util.isempty(_dtg_ID);
    
    function _init(){

        let uploadWidget = $('#uploadFile');
        
        //buttons
        let btnUploadFile = $('#btnUploadFile')
                    .css({'xwidth':'120px','font-size':'0.8em'})
                    .button({label: window.hWin.HR('Upload File')})
                    .click(function(e) {
                            uploadWidget.click();
                        });
        let btnParseData = $('#btnParseData')
                    .css({'width':'120px'})
                    .button({label: window.hWin.HR('Analyse'), icons:{secondary: "ui-icon-circle-arrow-e"}})
                    .click(function(e) {
                            _doParse();
                        });
        let btnStartImport = $('#btnImportData')
                    .css({'width':'110px'})
                    .addClass('ui-button-action')
                    .button({label: window.hWin.HR('Import'), icons:{secondary: "ui-icon-circle-arrow-e"}})
                    .click(function(e) { _doPost(); });
                        
        $('#csv_header').change(_redrawPreviewTable);

        window.hWin.HEURIST4.ui.createDetailtypeGroupSelect($('#field_dtg')[0], [{key: 0, title: 'select detail type group...'}]);
        $('#field_dtg').change(function(e){
            let label = $('#field_dtg').find(':selected').text();

            if($('#field_dtg').hSelect('instance') !== undefined){
                $('#field_dtg').hSelect('widget').attr('title', label);
            }else{
                $('#field_dtg').attr('title', label);
            }

            if($('#field_dtg').val() == 0){
                window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), true);
            }else{
                _doPrepare();
            }
        });

        _dtg_ID = _dtg_ID > 0 ? _dtg_ID : 0;
        $('#field_rtg').val(_dtg_ID);

        window.hWin.HEURIST4.util.setDisabled(btnStartImport, true);
         
        let src_content = ''; 
        
        $('#sourceContent').keyup(function(e){
            if(src_content != $(this).val().trim()){
                src_content = $(this).val().trim();
            }
        })                        

        uploadWidget.fileupload({
            url: window.hWin.HAPI4.baseURL +  'hserv/controller/fileUpload.php', 
            formData: [ {name:'db', value: window.hWin.HAPI4.database}, 
                        {name:'entity', value:'temp'}, //to place file into scratch folder
                        {name:'max_file_size', value:1024*1024}], //'1024*1024'
            autoUpload: true,
            sequentialUploads:true,
            dataType: 'json',
            done: function (e, response) {
                response = response.result;
                if(response.status==window.hWin.ResponseStatus.OK){
                    let data = response.data;
                    $.each(data.files, function (index, file) {
                        if(file.error){
                            $('#sourceContent').val(file.error);
                        }else{
                            
                            let url_get = file.deleteUrl.replace('fileUpload.php','fileGet.php')
                                +'&encoding='+$('#csv_encoding').val()+'&&db='+window.hWin.HAPI4.database;
                            
                            $('#sourceContent').load(url_get, null);
                        }
                    });
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response.message);
                }
                 
                let inpt = this;
                btnUploadFile.off('click');
                btnUploadFile.on({click: function(){
                            $(inpt).click();
                }});                
            }
        });

        $('.column_roles').change(function(e){ 

            let ele = $(e.target);
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

        window.hWin.HEURIST4.ui.createEncodingSelect($('#csv_encoding'));
    }

    
    //
    //
    //
    function _redrawPreviewTable(){
        
        if(_parseddata==null) return;
    
        let maxcol = 0;
        for(let i in _parseddata){
            if(window.hWin.HEURIST4.util.isArrayNotEmpty(_parseddata[i])){
                maxcol = Math.max(maxcol,_parseddata[i].length);
            }
        }
       
        let container = $('#divParsePreview').empty();    
        let tbl  = $('<table>')
                    .addClass('tbmain')
                    .appendTo(container);
                    
        //HEADER FIELDS            
        let headers = [], ifrom=0;
        if( $('#csv_header').is(':checked') ){ 
            
            for(let i=0;i<_parseddata.length;i++){
                if(window.hWin.HEURIST4.util.isArrayNotEmpty(_parseddata[i])){
                    
                    for(let j=0;j<maxcol;j++){
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
            for(let j=0;j<maxcol;j++){
                headers.push('column '+j);
            }
        }
        
        //TABLE HEADER
        let tr  = $('<tr>').appendTo(tbl);
        for(let j=0;j<maxcol;j++){
            
            let cs = {};
            if(maxcol>3){
                cs['width'] = ((j==0)?20:((j==maxcol-1)?40:10))+'%';
            }
            
            $('<th>').css(cs)
                .addClass('truncate')
                        .text(headers[j]).appendTo(tr);
        }
        
        //TABLE BODY
        for(let i=ifrom;i<_parseddata.length;i++){
            tr  = $('<tr>').appendTo(tbl);
            if(window.hWin.HEURIST4.util.isArrayNotEmpty(_parseddata[i])){
                for(let j=0;j<maxcol;j++){
                    
                    $('<td>').addClass('truncate')
                        .text(j<_parseddata[i].length?_parseddata[i][j]:' ').appendTo(tr);
                }
            }
        }
        
        //COLUMN ROLES SELECTORS
        $('.column_roles').empty();
        for(let j=-1; j<maxcol; j++){
            let opt = $('<option>',{value:j, text:(j<0)?'select...':headers[j]});                                    
            opt.appendTo($('#field_name'));
            opt.clone().appendTo($('#field_desc'));
            opt.clone().appendTo($('#field_type'));
            opt.clone().appendTo($('#field_vocab'));
            opt.clone().appendTo($('#field_target'));
            opt.clone().appendTo($('#field_uri'));
        }
        if(maxcol>0){
            $('#field_name').val(0);
            
            //AUTODETECT COLUMN ROLES by name
            for(let j=0;j<maxcol;j++){
                let s = headers[j].toLowerCase();
                if(s.indexOf('name')>=0 || s.indexOf('DetailTypeName')>=0){
                    $('#field_name').val(j);
                }else if(s=='description'){
                    $('#field_desc').val(j);
                }else if(s.indexOf('uri')>=0 || s.indexOf('url')>=0 || 
                    s.indexOf('reference')>=0 || s.indexOf('semantic')>=0 ){
                    $('#field_uri').val(j);
                }else if(s.indexOf('type')>=0){
                    $('#field_type').val(j);
                }else if(s.indexOf('vocab')>=0){
                    $('#field_vocab').val(j);
                }else if(s.indexOf('target')>=0){
                    $('#field_target').val(j);
                }
            }

            _doPrepare();
        }
    }
    
    //
    // parse CSV on server side
    //
    function _doParse(){

        let content = $('#sourceContent').val();

        if(content==''){
            window.hWin.HEURIST4.msg.showMsgFlash('No content entered', 1500);
        }else{
        
            window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));
    
            let request = { content: content,
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
                        let firstline_without_quotes = false;
                        let pos = content.indexOf($('#csv_enclosure').val()==2?'"':"'");

                        for(let i=0; i<_parseddata.length; i++){
                            if(window.hWin.HEURIST4.util.isArrayNotEmpty(_parseddata[i])){
                                let len = _parseddata[i].join(',').length;
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
        
        let msg = '';
        
        _prepareddata = [];
        
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(_parseddata)){
            msg = '<i>No data. Upload and parse</i>';
        }else{

            let field_name = $('#field_name').val();
            let field_desc = $('#field_desc').val();
            let field_type = $('#field_type').val();

            if(field_name<0 || field_desc<0 || field_type<0){

                let missing = [];

                if(field_name<0) { 
                    missing.push('Name'); 
                }
                if(field_desc<0) { 
                    missing.push('Description'); 
                }
                if(field_type<0) {
                    missing.push('Type');
                }

                if(missing.length <= 2){
                    msg = missing.join(' and ');
                }else{
                    let last = missing.pop();
                    msg = missing.join(', ');
                    msg += ' and ' + last;
                }

                msg = '<span style="color:red">'+ msg +' must be defined</span>';
            }else{

                let field_vocab = $('#field_vocab').val();
                let field_target = $('#field_target').val();
                let field_uri = $('#field_uri').val();

                let i, record;
                        
                let hasHeader = $('#csv_header').is(':checked');
                i = hasHeader?1:0;        
                        
                for(;i<_parseddata.length;i++){
                    
                    record = {};
                    
                    if(field_name>=_parseddata[i].length) continue;
                    
                    let name = '';

                    if(!window.hWin.HEURIST4.util.isempty(_parseddata[i][field_name])){
                        name = _parseddata[i][field_name].trim();
                    }
                    // Records validate in php
                    record['dty_Name'] = name;
                    
                    if(field_desc>-1 && field_desc<_parseddata[i].length){
                        record['dty_HelpText'] = _parseddata[i][field_desc];
                    }
                    if(field_type>-1 && field_type<_parseddata[i].length){
                        record['dty_Type'] = _parseddata[i][field_type];
                    }
                    if(field_uri>-1 && field_uri<_parseddata[i].length){
                        record['dty_SemanticReferenceURL'] = _parseddata[i][field_uri];
                    }
                    if(field_vocab>-1 && field_vocab<_parseddata[i].length){
                        record['dty_JsonTermIDTree'] = _parseddata[i][field_vocab];
                    }
                    if(field_target>-1 && field_target<_parseddata[i].length){
                        record['dty_PtrTargetRectypeIDs'] = _parseddata[i][field_target];
                    }
                   
                    _prepareddata.push(record);
                }//for

                $('#preparedInfo2').html('');
                
                if(_prepareddata.length==0){
                    msg = '<span style="color:red">No valid data to import</span>';   
                }else{
                    //msg = 'Ready to import: n='+_prepareddata.length;//+' entr'+((_prepareddata.length>1)?'ies':'y');
                    $('#preparedInfo2').html('n = '+_prepareddata.length);
                }
            }
        }
        
        window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), (_prepareddata.length<1 || $('#field_dtg').val() == 0));
        
        $('#preparedInfo').html(msg);
    }
    
    
    //
    // save record types
    //
    function _doPost(){
        
        if(_prepareddata.length<1) return;

        let dtgID = $('#field_dtg').val();

        if(dtgID == 0){
            window.hWin.HEURIST4.msg.showMsgFlash('Please select a detail type group', 2000);
            return;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));

        let request = {
            'a'          : 'batch',
            'entity'     : 'defDetailTypes',
            'request_id' : window.hWin.HEURIST4.util.random(),
            'fields'     : JSON.stringify(_prepareddata),
            'csv_import' : 1,
            'dtg_ID'     : dtgID
        };
    
        let that = this;

        window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                
                if(response.status == window.hWin.ResponseStatus.OK){

                    if(_return_results){

                        window.hWin.HAPI4.EntityMgr.refreshEntityData('dty', function(){
                            window.close({ result: response.data });
                        }); // update cache and return import results

                        return;
                    }

                    let results = response.data;
                    let $tbl = $('.tbmain');
                    let $rows = $tbl.find('tr');
                    let col_num = 0;
                    let update_cache = false;

                    // Add result header
                    if($($rows[0]).find('.post_results').length == 0){
                        $('<th>').addClass('post_results truncate').text('Results').appendTo($($rows[0]));
                    }else{
                        col_num = $($rows[0]).find('th').length - 1;
                    }

                    // Add result data
                    for(let i = 1; i < $rows.length; i++){

                        let $row = $($rows[i]);
                        let data = results[i-1];

                        if(data == null){
                            continue;
                        }
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

                    let entities_refresh = 'dty,rst' + (results['refresh_terms'] == true ? ',trm' : '');

                    if(update_cache){
                        window.hWin.HAPI4.EntityMgr.refreshEntityData(entities_refresh, null); // update cache
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
    }
    
    //public members
    let that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

    }

    _init();
    return that;  //returns object
}