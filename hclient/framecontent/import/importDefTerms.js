/**
* Class to import terms from CSV
* 
* @param _trm_ParentTermID - id of parent term
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

function hImportDefTerms(_trm_ParentTermID) {
    var _className = "ImportDefTerms",
    _version   = "0.4",
    
    _parseddata = null,
    _prepareddata,
    
    trm_ParentTermID,
    trm_ParentDomain,
    trm_ParentChildren= [];
    
    function _init(_trm_ParentTermID){
    
        trm_ParentTermID = _trm_ParentTermID;

        if(trm_ParentTermID>0){
            //find parent entry
            var allterms;
            if(window.hWin.HEURIST && window.hWin.HEURIST.terms){
                allterms = window.hWin.HEURIST.terms;
            }else if(window.hWin.HEURIST4 && window.hWin.HEURIST4.terms){
                allterms = window.hWin.HEURIST4.terms;
            }
            
            //get domain   
            if(window.hWin.HEURIST4.util.isnull(allterms.termsByDomainLookup.enum[trm_ParentTermID])){
                if(window.hWin.HEURIST4.util.isnull(allterms.termsByDomainLookup.relation[trm_ParentTermID])){
                            $('body').empty();
                            $('body').html('<h2>Parent term #'+trm_ParentTermID+' not found</h2>');
                            return;
                }else{
                    trm_ParentDomain = 'relation';
                }
            }else{
                trm_ParentDomain = 'enum';
            }

            //todo use window.hWin.HEURIST4.ui.getChildrenLabels
                
            //get list of children labels
            function __getSiblings(children){
                for(trmID in children){
                    if(children.hasOwnProperty(trmID)){
                        if(trmID==trm_ParentTermID){
                            for(var id in children[trmID]){
                                if(children[trmID].hasOwnProperty(id)){
                                    var term = allterms.termsByDomainLookup[trm_ParentDomain][id];
                                    if(term && term[0])
                                        trm_ParentChildren.push(term[0].toLowerCase());
                                }
                            }
                            break;
                        }else{
                            __getSiblings(children[trmID]);
                        }
                    }
                }
            }
            
            var trmID, tree = allterms.treesByDomain[trm_ParentDomain];
            __getSiblings(tree);
                
            
        }else{
            $('body').empty();
            $('body').html('<h2>Parent term is not defined</h2>');
            return;
        }
        
        
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
    url: window.hWin.HAPI4.baseURL +  'hserver/utilities/fileUpload.php', 
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
            if(response.status==window.hWin.HAPI4.ResponseStatus.OK){
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
                            
/*
deleteUrl:"http://127.0.0.1/h4-ao/hserver/utilities/fileUpload.php?file=Book_ansi.txt"
name:Book_ansi.txt
url:"http://127.0.0.1/HEURIST_FILESTORE/artem_delete01/scratch/Book_ansi.txt"
*/                            
                            
                            
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
                opt.appendTo($('#field_term'));
                opt.clone().appendTo($('#field_code'));
                opt.clone().appendTo($('#field_desc'));
            }
            if(maxcol>0){
                $('#field_term').val(0);
                
                //AUTODETECT COLUMN ROLES by name
                for(j=0;j<maxcol;j++){
                    var s = headers[j].toLowerCase();
                    if(s=='term' || s=='label'){
                        $('#field_term').val(j);
                    }else if(s=='code' || s=='standard code'){
                        $('#field_code').val(j);
                    }else if(s=='description'){
                        $('#field_desc').val(j);
                    }
                }
                
                _doPrepare();
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
            
                        var request = { content: content,
                                        csv_delimiter: $('#csv_delimiter').val(),
                                        csv_enclosure: $('#csv_enclosure').val(),
                                        csv_linebreak: $('#csv_linebreak').val(),
                                        id: window.hWin.HEURIST4.util.random()
                                       };
                                       

                        window.hWin.HAPI4.parseCSV(request, function( response ){

                            //that.loadanimation(false);
                            if(response.status == window.hWin.HAPI4.ResponseStatus.OK){

                                _parseddata = response.data;
                                
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
        
            var field_term = $('#field_term').val();
            if(field_term<0){
                msg = '<span style="color:red">Term(Label) must be always defined</span>';
            }else{
                
            
                var field_code = $('#field_code').val();
                var field_desc = $('#field_desc').val();
                var i, record, skip_na = 0, skip_dup = 0, skip_long = 0, labels = [];
                        
                var hasHeader = $('#csv_header').is(':checked');
                i = hasHeader?1:0;        
                        
                for(;i<_parseddata.length;i++){
                    
                    record = {};
                    
                    if(field_term>=_parseddata[i].length) continue;
                    
                    var lbl = null;
                    
                    if(!window.hWin.HEURIST4.util.isempty(_parseddata[i][field_term])){
                        lbl = _parseddata[i][field_term].trim();
                    }
                    
                    if(!window.hWin.HEURIST4.util.isempty(lbl)){
                        
                        //verify duplication in parent term and in already added
                        if(trm_ParentChildren.indexOf(lbl.toLowerCase())>=0 || 
                           labels.indexOf(lbl.toLowerCase())>=0)
                        {
                                skip_dup++;
                        }else{
                            
                            if(lbl.length>500){
                                skip_long++;    
                            }
                            labels.push(lbl.toLowerCase());
                            record['trm_Label'] = lbl;
                            record['trm_ParentTermID'] = trm_ParentTermID;
                            record['trm_Domain'] = trm_ParentDomain;
                            
                            if(field_desc>-1 && field_desc<_parseddata[i].length){
                                record['trm_Description'] = _parseddata[i][field_desc];
                            }
                            if(field_code>-1 && field_code<_parseddata[i].length){
                                record['trm_Code'] = _parseddata[i][field_code];
                            }
                           
                            _prepareddata.push(record);
                        
                        }
                        
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
                    msg = msg + '&nbsp;&nbsp;Term (label) is';
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
        
        window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), (_prepareddata.length<1 || _prepareddata.length==skip_na));
        
        $('#preparedInfo').html(msg);
    }
    
    
    //
    // save terms
    //
    function _doPost(){
        
        if(_prepareddata.length<1) return;
        
    
            var request = {
                'a'          : 'save',
                'entity'     : 'defTerms',
                'request_id' : window.hWin.HEURIST4.util.random(),
                'fields'     : _prepareddata                     
                };
                
                var that = this;                                                
                //that.loadanimation(true);
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){

                            var recIDs = response.data;
                            
                            window.hWin.HAPI4.SystemMgr.get_defs({terms:'all', mode:2}, function(response){
                                if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                                /*    
                            window.hWin.HEURIST4.msg.showMsgDlg(recIDs.length
                                + ' term'
                                + (recIDs.length>1?'s were':' was')
                                + ' added.', null, 'Terms imported'); // Title was an unhelpful and inelegant "Info"
                                */    
                                    window.close( {result:recIDs, parent:trm_ParentTermID, terms:response.data.terms } );
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr('Cannot obtain term definitions, possible database corruption, please consult Heurist developers');
                                }
                            });
                            
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

    _init(_trm_ParentTermID);
    return that;  //returns object
}
    