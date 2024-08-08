/**
* Class to import terms from CSV
* 
* @param _trm_ParentTermID - id of parent term
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

function hImportDefTerms(_trm_ParentTermID, _vcg_ID, isImportTranslations) {
    const _className = "ImportDefTerms",
    _version   = "0.4";
    
    let _parseddata = null,
    _prepareddata,
    
    _isTranslation = false,
    
    trm_ParentTermID,
    trm_ParentDomain,
    trm_VocabularyID,
    vcg_ID,
    trm_ParentChildren= []; //flat list of children labels in lower case
    
    function _init(_trm_ParentTermID, _vcg_ID, isImportTranslations){
                
        _isTranslation = isImportTranslations;

        trm_ParentTermID = _trm_ParentTermID;
        trm_VocabularyID = 0;
        vcg_ID = _vcg_ID;
        trm_ParentDomain = 'enum';

        if(vcg_ID>0){
            //check group
            if($Db.vcg(vcg_ID)==null){
                    $('body').empty();
                    $('body').html('<h2>Vocabulary group #'+vcg_ID+' not found</h2>');
                    return;
            }else{
                    trm_ParentDomain = $Db.vcg(vcg_ID,'vcg_Domain');
            }
            
            //get all vocabs 
            trm_ParentChildren = $Db.trm_getVocabs();
            $.each(trm_ParentChildren,function(i,trm_ID){
                trm_ParentChildren[i] = $Db.trm(trm_ID, 'trm_Label').toLowerCase();
            });
            /*
            $Db.trm().each(function(trm_ID,rec){
                if($Db.trm(trm_ID, 'trm_VocabularyGroupID')==vcg_ID && !($Db.trm(trm_ID, 'trm_ParentTermID')>0)){
                    trm_ParentChildren.push($Db.trm(trm_ID, 'trm_Label').toLowerCase());        
                }
            });*/
            
            
        }else if(trm_ParentTermID>0){
            //get domain   
            if($Db.trm(trm_ParentTermID)==null){
                    $('body').empty();
                    $('body').html('<h2>Vocabulary #'+trm_ParentTermID+' not found</h2>');
                    return;
            }else{
                trm_VocabularyID = $Db.getTermVocab(trm_ParentTermID);
                trm_ParentDomain = $Db.trm(trm_VocabularyID,'trm_Domain');   
            }

            //get flat list of children labels in lower case
            trm_ParentChildren = $Db.trm_TreeData(trm_VocabularyID, 'labels');
            
        }else{
            $('body').empty();
            $('body').html('<h2>Neither vocabulary group nor vocabulary defined</h2>');
            return;
        }
        
        
        let uploadWidget = $('#uploadFile');
        
        //buttons
        let btnUploadFile = $('#btnUploadFile')
                    .css({'xwidth':'120px','font-size':'0.8em'})
                    .button({label: window.hWin.HR('Upload File')})  //icons:{secondary: "ui-icon-circle-arrow-e"}
                    .on('click',function(e) {
                            uploadWidget.trigger('click');
                        });
        let btnParseData = $('#btnParseData')
                    .css({'width':'120px'})
                    .button({label: window.hWin.HR('Analyse'), icons:{secondary: "ui-icon-circle-arrow-e"}})
                    .on('click',function(e) {
                            _doParse();
                        });
        let btnStartImport = $('#btnImportData')
                    .css({'width':'110px'})
                    .addClass('ui-button-action')
                    .button({label: window.hWin.HR('Import'), icons:{secondary: "ui-icon-circle-arrow-e"}})
                    .on('click',function(e) {

                            let trm_sep = $('#term_separator').val();
                            if(!window.hWin.HEURIST4.util.isempty(trm_sep)){

                                let btns = {};
                                btns['Proceed'] = function() { _doPost(); };
                                btns['Clear character'] = function() { $('#term_separator').val(''); _doPost(); };

                                window.hWin.HEURIST4.msg.showMsgDlg(
                                    'If this character ['+trm_sep+'] appears in your terms they will be split into<br>two or more separate terms nested as hierarchy.<br><br>'
                                    + 'This can generate a complete mess if used unintentionally.', 
                                    btns, 
                                    {title: 'Terms with sub-terms', yes: 'Proceed', no: 'Clear character'}, {default_palette_class: 'ui-heurist-design'}
                                );
                            }else{
                                _doPost();
                            }
                        });
                        
        $('#csv_header').on('change',_redrawPreviewTable);                        
                        
        window.hWin.HEURIST4.util.setDisabled(btnStartImport, true);
         
        let src_content = ''; 
        
        $('#sourceContent').on('keyup', function(e){
            if(src_content != $(this).val().trim()){
                src_content = $(this).val().trim();
                _setCurtain( src_content==''?1:2 );
            }
        })                        
    
    
            uploadWidget.fileupload({
    url: window.hWin.HAPI4.baseURL +  'hserv/controller/fileUpload.php', 
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
                let data = response.data;
                $.each(data.files, function (index, file) {
                    if(file.error){
                        $('#sourceContent').val(file.error);
                    }else{
                        
                        let url_get = file.deleteUrl.replace('fileUpload.php','fileGet.php')
                            +'&encoding='+$('#csv_encoding').val()+'&&db='+window.hWin.HAPI4.database;
                        
                        $('#sourceContent').load(url_get, function(){
                            _setCurtain( 2 );
                            //alert('loaded! '+file.url);
                            //$.ajax({url:file.deleteUrl, type:'DELETE'});
                                                        
                            
                        });
                    }
                });
            }else{
                window.hWin.HEURIST4.msg.showMsgErr({message: response.message, error_title: 'File upload error', status: response.status});
            }
             
                let inpt = this;
                btnUploadFile.off('click');
                btnUploadFile.on({click: function(){
                            $(inpt).trigger('click');
                }});                
            }                    
                        });
                                

                        
                        
        $('.column_roles').on('change',function(e){ 
                
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
        
        if(_isTranslation){
            $('.trm_translation').show();
            $('.trm_import').hide();
        }
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
                opt.appendTo($('#field_term'));
                opt.clone().appendTo($('#field_code'));
                opt.clone().appendTo($('#field_desc'));
                opt.clone().appendTo($('#field_uri'));
                
                opt.clone().appendTo($('#field_ref_term'));
                opt.clone().appendTo($('#field_ref_id'));
                opt.clone().appendTo($('#field_trn_term'));
                opt.clone().appendTo($('#field_trn_desc'));
            }
            if(maxcol>0){
                $('#field_term').val(0);
                
                //AUTODETECT COLUMN ROLES by name
                for(let j=0;j<maxcol;j++){
                    let s = headers[j].toLowerCase();
                    if(s.indexOf('term')>=0 || s.indexOf('label')>=0){
                        $('#field_term').val(j);
                    }else if(s.indexOf('code')>=0){
                        $('#field_code').val(j);
                    }else if(s=='description'){
                        $('#field_desc').val(j);
                    }else if(s.indexOf('uri')>=0 || s.indexOf('url')>=0 || 
                        s.indexOf('reference')>=0 || s.indexOf('semantic')>=0 ){
                        $('#field_uri').val(j);
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
            let content = $('#sourceContent').val();

            _setCurtain(2);
            
            if(content==''){
                //$(recordList).resultList('updateResultSet', new HRecordSet());
            }else{
            
                        window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));
                
                        let request = { content: content,
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
                                      
    //
    // prepare update array
    //
    function _doPrepare(){
        
        if(_isTranslation){
            _doPrepareTranslation();
            return;   
        }
        
        
        let msg = '';
        let skip_na = 0, skip_dup = 0, skip_long = 0;
        
        _prepareddata = [];
        
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(_parseddata)){
            msg = '<i>No data. Upload and parse</i>';
        }else{
        
            let field_term = $('#field_term').val();
            if(field_term<0){
                msg = '<span style="color:red">Term(Label) must be always defined</span>';
            }else{
                
            
                let field_code = $('#field_code').val();
                let field_desc = $('#field_desc').val();
                let field_uri = $('#field_uri').val();
                let i, record, labels = [];
                        
                let hasHeader = $('#csv_header').is(':checked');
                i = hasHeader?1:0;        
                        
                for(;i<_parseddata.length;i++){
                    
                    record = {};
                    
                    if(field_term>=_parseddata[i].length) continue;
                    
                    let lbl = null;
                    
                    if(!window.hWin.HEURIST4.util.isempty(_parseddata[i][field_term])){
                        lbl = _parseddata[i][field_term].trim();
                    }
                    
                    if(!window.hWin.HEURIST4.util.isempty(lbl)){
                        
                        //verify duplication in parent term and in already added
                        // trm_ParentChildren - flat list of children labels in lower case
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
                            record['trm_Domain'] = trm_ParentDomain;
                            
                            if(trm_ParentTermID>0) record['trm_ParentTermID'] = trm_ParentTermID;
                            if(vcg_ID>0) record['trm_VocabularyGroupID'] = vcg_ID;
                            
                            if(field_desc>-1 && field_desc<_parseddata[i].length){
                                record['trm_Description'] = _parseddata[i][field_desc];
                            }
                            if(field_code>-1 && field_code<_parseddata[i].length){
                                record['trm_Code'] = _parseddata[i][field_code];
                            }
                            if(field_uri>-1 && field_uri<_parseddata[i].length){
                                record['trm_SemanticReferenceURL'] = _parseddata[i][field_uri];
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
    // prepare translation update array
    //
    function _doPrepareTranslation(){
        
        let msg = '';
        
        _prepareddata = [];
        
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(_parseddata)){
            msg = '<i>No data. Upload and parse</i>';
        }else{
        
            if($('#field_ref_term').val()==''){ // && $('#field_ref_id').val()<0
                msg = '<span style="color:red">Reference Term(Label) must be always defined</span>';
            }else{
                
                let field_ref_id = $('#field_ref_id').val();
                let field_ref_term = $('#field_ref_term').val();
            
                let field_trn_term = $('#field_trn_term').val();
                let field_trn_desc = $('#field_trn_desc').val();

                let i, record, skip_na = 0, skip_not_found = 0, skip_long = 0;
                        
                let hasHeader = $('#csv_header').is(':checked');
                i = hasHeader?1:0;        
                        
                for(;i<_parseddata.length;i++){
                    
                    if(field_ref_term>=0){
                        if(field_ref_term>=_parseddata[i].length) continue; //out of row extent
                        
                        let lbl = null;
                        
                        if(!window.hWin.HEURIST4.util.isempty(_parseddata[i][field_ref_term])){
                            lbl = _parseddata[i][field_ref_term].trim();
                        }
                        
                        if(window.hWin.HEURIST4.util.isempty(lbl)){
                                skip_na++;
                        }else if(lbl.length>500){
                                skip_long++;    
                        }else
                        if(trm_ParentChildren.indexOf(lbl.toLowerCase())<0){
                                skip_not_found++;
                        }else{
                            
                            record = {ref_id:lbl};
                            
                            if(field_trn_desc>-1 && field_trn_desc<_parseddata[i].length){
                                record['trm_Description'] = _parseddata[i][field_trn_desc];
                            }
                            if(field_trn_term>-1 && field_trn_term<_parseddata[i].length){
                                record['trm_Label'] = _parseddata[i][field_trn_term];
                            }
                            
                            _prepareddata.push( record )
                            
                        }                        
                    } 
                }//for

                $('#preparedInfo2').html('');
                
                if(_prepareddata.length==0){
                    msg = '<span style="color:red">No valid data to import</span>';   
                }else{
                    //msg = 'Ready to import: n='+_prepareddata.length;//+' entr'+((_prepareddata.length>1)?'ies':'y');
                    $('#preparedInfo2').html('n = '+_prepareddata.length);
                }
                if(skip_na>0 || skip_not_found>0 || skip_long>0){
                    msg = msg + '&nbsp;&nbsp;Term (label) is';
                }
                if(skip_na>0){
                    msg = msg + ' not defined for '+skip_na+' row'+((skip_na>1)?'s;':';');    
                }
                if(skip_not_found>0){
                    msg = msg + ' not found for '+skip_not_found+' row'+((skip_not_found>1)?'s;':';');    
                }
                if(skip_long>0){
                    msg = msg + ' very long (>500 chars) for '+skip_long+' row'+((skip_long>1)?'s':'');    
                }
                
                
            }
        
        }
        
        window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), (_prepareddata.length<1));
        
        $('#preparedInfo').html(msg);
    }
    
    
    //
    // save terms
    //
    function _doPost(){
        
        if(_prepareddata.length<1) return;

        window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));

        let request = {
            'a'          : 'batch',
            'entity'     : 'defTerms',
            'request_id' : window.hWin.HEURIST4.util.random()
        };
        
        if(_isTranslation){
            request['set_translations'] = JSON.stringify(_prepareddata);
            request['vcb_ID'] = trm_ParentTermID;
        }else{
            request['fields'] = JSON.stringify(_prepareddata);
            request['term_separator'] = $('#term_separator').val();
        }
    
        let that = this;
        //that.loadanimation(true);
        window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                
                if(response.status == window.hWin.ResponseStatus.OK){
                
                    if(_isTranslation){
                        window.hWin.HEURIST4.msg.sendCoverallToBack();
                        window.close( { result:response.data } );
                    }else{
                        let recIDs = response.data;
                        //refresh local defintions
                        window.hWin.HAPI4.EntityMgr.refreshEntityData('trm',
                                function(){
                                    window.hWin.HEURIST4.msg.sendCoverallToBack();
                                    window.close( { result:recIDs } );            
                                }
                        );
                    }
                    
                }else{
                    window.hWin.HEURIST4.msg.sendCoverallToBack();
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

    _init(_trm_ParentTermID, _vcg_ID, isImportTranslations);
    return that;  //returns object
}
    