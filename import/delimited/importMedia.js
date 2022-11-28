/**
* Class to import recUploadedFiles from CSV
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

function hImportMedia() {
    var _className = "ImportMedia",
    _version   = "0.4",
    
    _parseddata = null,
    _prepareddata;
    
    function _init(){
        
        var uploadWidget = $('#uploadFile');
        
        //buttons
        var btnUploadFile = $('#btnUploadFile')
                    .css({'xwidth':'120px','font-size':'0.8em'})
                    .button({label: window.hWin.HR('Upload File')})  //icons:{secondary: "ui-icon-circle-arrow-e"}
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
                    .click(function(e) {
                                _doPost();
                        });
                        
        $('#csv_header').change(_redrawPreviewTable);                        
                        
        window.hWin.HEURIST4.util.setDisabled(btnStartImport, true);
         
        var src_content = ''; 
        
        $('#sourceContent').keyup(function(e){
            if(src_content != $(this).val().trim()){
                src_content = $(this).val().trim();
                _setCurtain( src_content==''?1:2 );
            }
        })                        
    
    
            uploadWidget.fileupload({
    url: window.hWin.HAPI4.baseURL +  'hsapi/controller/fileUpload.php', 
    formData: [ {name:'db', value: window.hWin.HAPI4.database}, 
                {name:'entity', value:'temp'}, //to place file into scratch folder
                {name:'max_file_size', value:1024*1024}], //'1024*1024'
    //acceptFileTypes: /(\.|\/)(gif|jpe?g|png)$/i,
    autoUpload: true,
    sequentialUploads:true,
    dataType: 'json',
    //dropZone: $input_img,
    // add: function (e, data) {  data.submit(); },
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
                        
                        $('#sourceContent').load(url_get, function(){
                            _setCurtain( 2 );
                            //alert('loaded! '+file.url);
                            //$.ajax({url:file.deleteUrl, type:'DELETE'});
                                                        
                            
                        });
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
                opt.appendTo($('#field_url'));
                opt.clone().appendTo($('#field_desc'));
            }
            if(maxcol>0){
                //$('#field_term').val(0);
                
                //AUTODETECT COLUMN ROLES by name
                for(j=0;j<maxcol;j++){
                    var s = headers[j].toLowerCase();
                    if(s.indexOf('url')>=0 || s.indexOf('path')>=0 || s.indexOf('uri')>=0){
                        $('#field_url').val(j);
                        _doPrepare();
                        break;
                    }
                }
                _setCurtain(3);
            }
        
    }
    
    //
    // parse CSV on server side
    //
    function _doParse(){
            
            //noothing defined
            var content = $('#sourceContent').val();

            _setCurtain(2);
            
            if(content==''){
                //$(recordList).resultList('updateResultSet', new hRecordSet());
            }else{
            
                        window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));
                
                        var request = { content: content,
                                        csv_delimiter: $('#csv_delimiter').val(),
                                        csv_enclosure: $('#csv_enclosure').val(),
                                        csv_linebreak: $('#csv_linebreak').val(),
                                        id: window.hWin.HEURIST4.util.random()
                                       };
                                       

                        window.hWin.HAPI4.doImportAction(request, function( response ){

                            window.hWin.HEURIST4.msg.sendCoverallToBack();
                            
                            //that.loadanimation(false);
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
    
    function _setCurtain(step){
        
        if(step<3){
            $('#divCurtain').show();   
            $('.column_roles').empty();
            $('#divParsePreview').empty();
            $('#preparedInfo').empty();
        }
        if(step==1){
            $('#divCurtain').css('width','400px');
        }else if(step==2){
            $('#divCurtain').css('width','200px');
        }else if(step==3){
            $('#divCurtain').hide();   
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
        
            var field_url = $('#field_url').val();
            if(field_url<0){
                msg = '<span style="color:red">URL/Path must be always defined</span>';
            }else{
                
                var field_desc = $('#field_desc').val();
                var field_desc_sep = ', Download '; //$('#field_desc_sep').val(); // "), Donwload "
                var field_desc_concat = $('#field_desc_concat').is(':checked'); 
                var multival_separator = $('#multival_separator').val();
                
                var i, record, skip_na = 0, skip_dup = 0, skip_long = 0, urls = [];
                        
                var hasHeader = $('#csv_header').is(':checked');
                i = hasHeader?1:0;        
                        
                for(;i<_parseddata.length;i++){
                    
                    
                    
                    if(field_url>=_parseddata[i].length) continue;
                    
                    var _url = null, _descriptions = [];
                    
                    if(!window.hWin.HEURIST4.util.isempty(_parseddata[i][field_url])){
                        _url = _parseddata[i][field_url].trim();
                    }
                    if(multival_separator){
                        _urls = _url.split(multival_separator);    
                    }else{
                        _urls = [_url];
                    }

                    if(!window.hWin.HEURIST4.util.isempty(field_desc) && field_desc>0){
                        
                        var desc = _parseddata[i][field_desc];
                        
                        //remove leading Donwload
                        if(desc){
                            if(desc.indexOf(' Download')==0){
                                 desc = desc.substring(9);
                            }
                            desc = desc.trim();
                            _descriptions = desc.split(field_desc_sep);
                        }
                    }

                    
                    for(var j=0; j<_urls.length; j++){
                        
                        _url = _urls[j].trim();
                    
                        if(!window.hWin.HEURIST4.util.isempty(_url)){
                            
                            //verify duplication in parent term and in already added
                            if(urls.indexOf(_url.toLowerCase())>=0)
                            {
                                    skip_dup++;
                            }else{
                                
                                if(_url.length>1000){
                                    skip_long++;    
                                }
                                
                                
                                var _desc = (j<_descriptions.length)?(_descriptions[j]+')'):'';
                                if(field_desc_concat){ //add other fields to description
                                    for(var k=0;k<_parseddata[i].length;k++){
                                        if(k!=field_url && k!=field_desc){
                                            _desc = _parseddata[i][k] + ', '+_desc;
                                        }
                                    }
                                }
                                
                                urls.push(_url.toLowerCase());
                                
                                record = {};
                                record['ulf_ExternalFileReference'] = _url;
                                record['ulf_Description'] = _desc;
                               
                                _prepareddata.push(record);
                            }
                        }else{
                            skip_na++;
                        }
                    }// _urls
                }//for

                $('#preparedInfo2').html('');
                
                if(_prepareddata.length==0){
                    msg = '<span style="color:red">No valid data to import</span>';   
                }else{
                    //msg = 'Ready to import: n='+_prepareddata.length;//+' entr'+((_prepareddata.length>1)?'ies':'y');
                    $('#preparedInfo2').html('n = '+_prepareddata.length);
                }
                if(skip_na>0 || skip_dup>0 || skip_long>0){
                    msg = msg + '&nbsp;&nbsp;URL/Path is';
                }
                if(skip_na>0){
                    msg = msg + ' not defined for '+skip_na+' row'+((skip_na>1)?'s;':';');    
                }
                if(skip_dup>0){
                    msg = msg + ' duplicated for '+skip_dup+' row'+((skip_dup>1)?'s;':';');    
                }
                if(skip_long>0){
                    msg = msg + ' very long (>1000 chars) for '+skip_long+' row'+((skip_long>1)?'s':'');    
                }
                
                
            }
        
        }
        
        window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), (_prepareddata.length<1 || _prepareddata.length==skip_na));
        
        $('#preparedInfo').html(msg);
    }
    
    
    //
    // Start import
    //
    function _doPost(){
        
        if(_prepareddata.length<1) return;
        
        //console.log(_prepareddata);

        window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));

        var request = {
            'a'          : 'batch',
            'entity'     : 'recUploadedFiles',
            'request_id' : window.hWin.HEURIST4.util.random(),
            'fields'     : JSON.stringify(_prepareddata),
            'csv_import' : 1,
            'is_download': $('#field_download').is(':checked')?1:0
        };
    
        var that = this;
        //that.loadanimation(true);
        window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                
                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    window.hWin.HEURIST4.msg.showMsgDlg(response.data);
                    
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
    