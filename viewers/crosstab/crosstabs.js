/*
* Copyright (C) 2005-2020 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/                                                     

/**
*  Corsstabs UI class
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/

var crosstabsAnalysis;

/**
*  CrosstabsAnalysis - class for crosstab analysis                                           
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2013.0530
*/
function CrosstabsAnalysis(_query, _query_domain) {

    var _className = "CrosstabsAnalysis";

    var MAX_FOR_AUTO_RETRIEVE = 6000;
    
    var fields3 = {column:{field:0, type:'', values:[], intervals:[]}, row:{}, page:{}};
    //     intervals:{name: , description:, values:[  ] }
    var records_resp;
    var keepCount = 10;
    var needServerRequest = true;
    var suppressRetrieve = false;
    var inProgress = false;
    var query_main;
    var query_domain;
    
    var $recTypeSelector;
    
    var _currentRecordset = null;
    var _selectedRtyID = null;

    var configEntityWidget = null;
    
    var _isPopupMode = false;

    function _init(_query, _query_domain)
    {
        if(!window.hWin.HEURIST4.util.isempty(_query)){
            _isPopupMode = true;
        }else{
            $('#btnCancel').hide();
        }
        
        $('#btnPanels').find('button').button();


        query_main = _query?_query:'';
        query_domain =_query_domain?_query_domain:'all';

        //record type dropdown    
        $recTypeSelector = window.hWin.HEURIST4.ui.createRectypeSelect( $('#cbRectypes').get(0), null, 
                    window.hWin.HR('select record type'), false );
        $recTypeSelector.hSelect({ change: _onRectypeChange });
        //$rec_select.change(_onRectypeChange);

        $('.showintervals')
        .click(function( event ) {
            var isVisible = showHideIntervals( $(this).attr('tt') );
            if(isVisible){
                $(this).removeClass('collapsed');
            }else{
                $(this).addClass('collapsed');
            }
        });

        //hide left panel(saved searches) and maximize analysis
        //var _kept_width = window.hWin.HAPI4.LayoutMgr.cardinalPanel('getSize', ['east','outerWidth'] );
        //window.hWin.HAPI4.LayoutMgr.cardinalPanel('close', 'west');
        //window.hWin.HAPI4.LayoutMgr.cardinalPanel('sizePane', ['east', (top?top.innerWidth:window.innerWidth)-300 ]);  //maximize width
        
      configEntityWidget = $('#divLoadSettings').configEntity({
        entityName: 'defRecTypes',
        configName: 'crosstabs',
        
        getSettings: function(){ return _getSettings(); }, //callback function to retieve configuration
        setSettings: function( settings ){ _applySettings(settings); }, //callback function to apply configuration
        
        //divLoadSettingsName: this.element
        divSaveSettings: $('#divSaveSettings'),  //element
        showButtons: true,
        useHTMLselect: true          
      });
      
      configEntityWidget.configEntity( 'updateList', $recTypeSelector.val() );    
    }

    /**
    * update list of fields for selected record type
    * 1. columns 2.rows 3. pages 4, aggreagation
    */
    function _onRectypeChange(event, data){

        needServerRequest = true;

        if(data){
            _selectedRtyID = Number(data.item.value);
        }else{
            _selectedRtyID = Number($recTypeSelector.val());
            $recTypeSelector.hSelect("refresh"); 
        }

        var allowedlist = ["enum", "integer", "float", "resource", "relationtype"];//, "date", "freetext"]; //"resource",

        //var selObj = createRectypeDetailSelect($('#cbColumns').get(0), _selectedRtyID, allowedlist, ' ');
        //createRectypeDetailSelect($('#cbRows').get(0), _selectedRtyID, allowedlist, ' ');
        //createRectypeDetailSelect($('#cbPages').get(0), _selectedRtyID, allowedlist, ' ');
        
        var selObj = window.hWin.HEURIST4.ui.createRectypeDetailSelect($('#cbColumns').get(0), _selectedRtyID, allowedlist, ' ', null );
        window.hWin.HEURIST4.ui.createRectypeDetailSelect($('#cbRows').get(0), _selectedRtyID, allowedlist, ' ', null );
        window.hWin.HEURIST4.ui.createRectypeDetailSelect($('#cbPages').get(0), _selectedRtyID, allowedlist, ' ', null );

        if(selObj.find('option').length<2){
            $("#vars").hide();
            $("#shows").hide();
            $("#btnPanels").hide();
            $("#nofields").html(_selectedRtyID>0?'No suitable fields: numeric, pointer or enumeration types.':'Select record type.');
            $("#nofields").show();
        }else{
            $("#vars").show();
            $("#shows").show();
            $("#nofields").hide();
            $("#btnPanels").show();
        }

        //createRectypeDetailSelect($('#cbAggField').get(0), _selectedRtyID, ["integer", "float"], false);
        window.hWin.HEURIST4.ui.createRectypeDetailSelect($('#cbAggField').get(0), _selectedRtyID, ["integer", "float"], false);
        _changeAggregationMode();

        clearIntervals('column');
        clearIntervals('row');
        clearIntervals('page');
        
        
        //get list of settings
        configEntityWidget.configEntity( 'updateList', _selectedRtyID );    
    }


    /**
    * collapse/expand intervals
    */
    function showHideIntervals(name){

        //var name = $(event.target).attr('id');

        var ele = $('#'+name+'Intervals');

        var isVisible = ele.is(':visible');

        if( isVisible ){
            ele.hide();
        }else{
            ele.show();
        }

        return !isVisible;
    }

    /**
    * remove all intervals for given type (page,col,row)
    */
    function clearIntervals(name){
        var $container = $('#'+name+'Intervals');
        $container.empty().hide();
        $container.html('Select field to set intervals');
        fields3[name] = {field:0, type:'', values:[], intervals:[]};
        return $container;
    }

    //
    //
    //
    function _resetAllIntervals(fields, name){
        
        suppressRetrieve = true;
        
        if(!name) name = 'column';
        
        var detailid = fields[name];
        $('#cb'+name[0].toUpperCase()+name.slice(1)+'s').val(detailid);
        
        _resetIntervals_continue(name, detailid, function(){
            if(name == 'column') 
                name = 'row'
            else if(name == 'row') 
                name = 'page'
            else {
                suppressRetrieve = false;
                _autoRetrieve();
                return;
            }
            _resetAllIntervals(fields, name);
        });
    }
    
    /**
    * create set of intervals specific for particular detail type
    * get min and max values
    */
    function _resetIntervals(event){
        needServerRequest = true;
        var detailid = event.target.value;
        var name = $(event.target).attr('name');  //type
        
        _resetIntervals_continue(name, detailid);
    }    
       
    //
    //
    //    
    function _resetIntervals_continue(name, detailid, callback){
        
        var $container = $('#'+name+'Intervals');
        $container.empty();
        fields3[name] = {field:0, fieldname:'', type:'', values:[], intervals:[], allownulls:false};

        //not found
        if ($Db.rst(_selectedRtyID, detailid)==null)
        {
            $container.hide();
            if($.isFunction(callback)) callback.call();
            return;
        }

        //get detail type
        var detailtype = $Db.dty(detailid,'dty_Type');
        var detailname = $Db.rst(_selectedRtyID, detailid, 'rst_DisplayName');


        fields3[name] = {field:detailid, fieldname:detailname, type:detailtype, values:[], intervals:[]}

        if(detailtype=="enum" || detailtype=="relationtype") //false &&
        {
            //get all terms and create intervals
            calculateIntervals(name);

        }else if(detailtype=="float" || detailtype=="integer"){
            //get min and max for this detail in database

            var baseurl = window.hWin.HAPI4.baseURL + "viewers/crosstab/crosstabs_srv.php";
            var request = { a:'minmax', rt:_selectedRtyID , dt:detailid, session: Math.round((new Date()).getTime()/1000) };

            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null,
                function( response ){
                    if(response.status == window.hWin.ResponseStatus.OK){

                        var val0 = parseFloat(response.data.MIN);
                        var valmax = parseFloat(response.data.MAX);

                        if(isNaN(val0) || isNaN(valmax)){
                            $container = clearIntervals(name);
                            $container.html('There are no min max values for this field.');
                            $container.show();
                        }else{
                            fields3[name].values = [val0, valmax];
                            calculateIntervals(name);
                        }
                        
                        if($.isFunction(callback)) callback.call();

                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });                

            return;

        }else if(detailtype=="resource"){

            //get list of possible values for pointer detail type
            var request = { a:'pointers', rt:_selectedRtyID , dt:detailid };
            var baseurl = window.hWin.HAPI4.baseURL + "viewers/crosstab/crosstabs_srv.php";
            
            if(_currentRecordset!=null){
                request['recordset'] = _currentRecordset;  //CSV
            }else{
                request['q'] = query_main;
                request['w'] = query_domain;
            }

            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null,
                function( response ){
                    if(response.status == window.hWin.ResponseStatus.OK){

                        if(!response.data){
                            fields3[name].values = [];
                            $container = clearIntervals(name);
                            $container.html('There are no pointer values for this field.');
                            $container.show();
                        }else{
                            fields3[name].values = response.data;
                            calculateIntervals(name);
                        }
            
                        if($.isFunction(callback)) callback.call();

                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });                

            return;

        }else if(detailtype=="date"){
            //get min and max for this detail in database

        }else if(detailtype=="freetext"){
            //alphabetically, or if distinct values less that 50 like terms

        }

            
        if($.isFunction(callback)) callback.call();
        renderIntervals(name);
    }

    /**
    * create intervals
    */
    function calculateIntervals(name, count)
    {
        if(fields3[name].type=="float" || fields3[name].type=="integer")
        {
            if(count>0){
                keepCount = count;
            }else if(keepCount>0){
                count = keepCount;
            }else{
                count = 10;
                keepCount = 10;
            }

            var val0 = fields3[name].values[0];
            var valmax = fields3[name].values[1];
            fields3[name].intervals = [];

            var delta = (valmax - val0)/count;
            if(fields3[name].type=="integer"){
                delta = Math.round(delta);
                if(Math.abs(delta)<1){
                    delta = delta<0?-1:1;
                }
            }
            var cnt = 0;
            while (val0<valmax && cnt<count){
                var val1 = (val0+delta>valmax)?valmax:val0+delta;
                if(cnt==count-1 && val1!=valmax){
                    val1 = valmax;
                }

                fields3[name].intervals.push( {name:rnd(val0)+' ~ '+rnd(val1), description: rnd(val0)+' ~ '+rnd(val1) , values:[ val0, val1 ] });  //minvalue, maxvalue
                val0 = val1;
                cnt++;
            }

        }else if(fields3[name].type=="resource"){

            var pointers = fields3[name].values;

            var mcnt = (count>0)?Math.min(pointers.length, count):pointers.length;
            fields3[name].intervals = [];

            var i;
            for (i=0; i<mcnt; i++){
                fields3[name].intervals.push( {name:pointers[i].text, description:pointers[i].text, values:[ parseInt(pointers[i].id) ] });
            }

        }else if(fields3[name].type=="enum" || fields3[name].type=="relationtype"){

            var vocab_id = $Db.dty(fields3[name].field, 'dty_JsonTermIDTree');    
            var termlist = $Db.trm_TreeData(vocab_id, 'select'); //{key: code: title:}

            var mcnt = (count>0)?Math.min(termlist.length, count):termlist.length;
            fields3[name].values = [];
            fields3[name].intervals = [];

            var i;
            for (i=0; i<mcnt; i++){
                fields3[name].values.push({text:termlist[i].title, id:termlist[i].key});
                fields3[name].intervals.push( {name:termlist[i].title, description:termlist[i].title, values:[ termlist[i].key ] });
            }


        }
        renderIntervals(name);
        
        if(suppressRetrieve) return;
        
        _autoRetrieve();
    }

    /**
    * render intervals (create divs)
    */
    function renderIntervals(name){

        var $container = $('#'+name+'Intervals');
        $container.empty();

        if(fields3[name].intervals.length<1){
            $container.html('There are no values for these fields in the current results set');
            $container.show();
            return;
        }

        if(fields3[name].values && fields3[name].values.length>0)
        {
            $intdiv = $(document.createElement('div'))
            .css({'padding':'0.4em'})
            .attr('intid', 'b0' )
            .appendTo($container);

            $intdiv
            .append('&nbsp;&nbsp;<label>Number of intervals:</label>')
            .append($('<input id="'+name+'IntCount">').attr('size',6).val(keepCount))
            .append($('<button>',{text: "Reset"})
                .css('margin-left','1em')
                .css('margin-right','3em')
                .click(function( event ) {
                    calculateIntervals(name, parseInt($('#'+name+'IntCount').val()) );
            }));


            $('<button>',{text: "Add"})
            //.button({icons: {primary: "ui-icon-plus"}} )
            .click(function( event ) {
                editInterval(  name, -1 );
            })
            .appendTo($intdiv);

            /* @TODO perhaps
            $intdiv.append($('<input>').attr('type','checkbox').css('margin-left','1em').click(function(event){
            fields3[name].allownulls = event.target.checked;
            })
            ).append('&nbsp;include null values');
            */
        }


        var idx;
        var intervals = fields3[name].intervals;

        $('#'+name+'IntCount').val(intervals.length)

        for (idx=0; idx<intervals.length; idx++){

            var interval = intervals[idx];

            $intdiv = $(document.createElement('div'))
            .addClass('intervalDiv list')
            .css({'padding':'0.2em'})
            .attr('id', name+idx )
            .appendTo($container);

            $('<div>')
            .css({'width':'160px','display':'inline-block'})
            .append(
                $('<div>')
                .html(interval.name)
                .css({'width':'140px', 'font-weight':'bold'} ))
            /*$('<input>')
            .addClass('text ui-widget-content ui-corner-all')
            .val(interval.name)
            .css({'width':'150px'} ))*/
            .appendTo($intdiv);

            $('<div>')
            .html(interval.description)
            .css({'max-width':'250px','width':'250px','display':'inline-block','padding-left':'1.5em'})
            .appendTo($intdiv);

            /*
            var editbuttons = '<div class="saved-search-edit">'+
            '<img title="edit" src="' +window.hWin.HAPI4.baseURL+'common/images/edit_pencil_9x11.gif" '+
            'onclick="{top.HEURIST.search.savedSearchEdit('+sid+');}">';
            editbuttons += '<img  title="delete" src="'+window.hWin.HAPI4.baseURL+'common/images/delete6x7.gif" '+
            'onclick="{top.HEURIST.search.savedSearchDelete('+sid+');}"></div>';
            $intdiv.append(editbuttons);
            */

            $('<button>')
            .attr('intid', idx)
            //.button({icons: {primary: "ui-icon-pencil"}, text: false })
            .addClass('crosstab-interval-edit')
            .css({'background-image': 'url('+window.hWin.HAPI4.baseURL+'common/images/edit_pencil_9x11.gif)'})
            .click(function( event ) {
                editInterval( name,  $(this).attr('intid') );
            })
            .appendTo($intdiv);

            $('<button>')
            //.button({icons: {primary: "ui-icon-close"}, text: false })
            .attr('intid', idx)
            .addClass('crosstab-interval-edit')
            .css({'background-image': 'url('+window.hWin.HAPI4.baseURL+'common/images/delete6x7.gif)'})
            .click(function( event ) {
                removeInterval( name, $(this).attr('intid') );
            })
            .appendTo($intdiv);
        }



    }

    /**
    * remove interval
    */
    function removeInterval( name, idx ){
        fields3[name].intervals.splice(idx,1);

        /*
        var $container = $('#'+name+'Intervals');
        $container.find( '#'+name+idx ).remove();
        */

        renderIntervals(name);
    }

    /**
    * add/edit interval
    */
    function editInterval( name, idx ){

        var $dialogbox;

        //create multiselect list of terms
        var $dlg = $("#terms-dialog");
        if($dlg.length==0){
            $dlg = $('<div>')
            .attr('id','terms-dialog')
            .css('overflow-y', 'auto')
            .appendTo($('body'));
        }
        $dlg.empty();

        var intname = (idx<0)?'new interval':fields3[name].intervals[idx].name;
        $('<div id="topdiv">Label:<input id="intname" value="'+intname+'"></div>')
        //.addClass('intervalDiv list')
        .css({'padding':'0.2em'})
        .appendTo($dlg);

        var iHeight = 220;
        var detailtype = fields3[name].type;
        var cnt=0;


        if ( detailtype=="enum" || detailtype=="resource" || detailtype=="relationtype")
        {

            var i, j,
            termlist = fields3[name].values; //all terms or pointers
            for(i=0; i<termlist.length; i++)
            {
                var notused = true, itself = false;
                var intvalues = fields3[name].intervals;
                for(j=0; j<intvalues.length; j++){
                    if(intvalues[j].values.indexOf( termlist[i].id )>=0){
                        if(idx==j){
                            itself = true;  //itself
                        }else{
                            notused = false;
                        }
                        break;
                    }
                }

                if(notused){

                    $intdiv = $(document.createElement('div'))
                    .addClass('intervalDiv list')
                    .css({'padding':'0.2em'})
                    .attr('intid', idx )
                    .appendTo($dlg);

                    $('<input>')
                    .attr('type','checkbox')
                    .attr('checked', itself)
                    .addClass('recordIcons')
                    .attr('termid',termlist[i].id)
                    .attr('termname',termlist[i].text)
                    .css('margin','0.4em')
                    .appendTo($intdiv);

                    $('<div>')
                    .css('display','inline-block')
                    .addClass('recordTitle')
                    .css('margin-top','0.4em')
                    .html( termlist[i].text )
                    .appendTo($intdiv);

                    cnt++;

                }//notused
            }

            iHeight = 420;

        }else if(detailtype=="float" || detailtype=="integer"){

            iHeight = 180;

            var minv = idx<0?0:fields3[name].intervals[idx].values[0];
            var maxv = idx<0?0:fields3[name].intervals[idx].values[1];

            $intdiv = $(document.createElement('div'))
            .attr('intid', idx )
            .appendTo($dlg);

            $intdiv.append($('<div style="padding-top:0.5em">&nbsp;from:&nbsp;<input id="minval" value="'+minv+'" /></div>'));
            $intdiv.append($('<div style="padding-top:0.5em">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;to:&nbsp;<input id="maxval" value="'+maxv+'" /></div>'));
            /*
            .append( $('<input>')
            .attr('id','minval')
            .val(minv))
            .append($('<label>to</label>'))
            .append( $('<input>')
            .attr('id','maxval')
            .val(maxv));
            */
            cnt++;
        }

        if(cnt>0){
            function __addeditInterval( event ){
                
                $(event.target).off('click');

                if(idx<0){
                    fields3[name].intervals.push( {name:'', description:'', values:[] });
                    idx = fields3[name].intervals.length-1;
                }else{
                    fields3[name].intervals[idx].values = [];
                    fields3[name].intervals[idx].description = '';
                }
                fields3[name].intervals[idx].name = $dlg.find("#intname").val();

                if(detailtype=="enum" || detailtype=="resource" || detailtype=="relationtype"){ //false &&
                    var sels = $dlg.find("input:checked")
                    $.each(sels, function(i, ele){
                        fields3[name].intervals[idx].values.push( parseInt($(ele).attr('termid')) );
                        fields3[name].intervals[idx].description = fields3[name].intervals[idx].description + $(ele).attr('termname')+' ';
                    });

                }else if(detailtype=="float" || detailtype=="integer"){

                    fields3[name].intervals[idx].values.push( parseFloat($dlg.find('#minval').val() ));
                    fields3[name].intervals[idx].values.push(  parseFloat($dlg.find('#maxval').val() ));
                    fields3[name].intervals[idx].description = $dlg.find('#minval').val()+' ~ '+$dlg.find('#maxval').val();

                }

                renderIntervals(name);

                $dialogbox.dialog( "close" );
            }


            $dlg.find("#topdiv").append($('<button>').html('Apply').css('margin','1em').click(__addeditInterval));

            $dialogbox = window.hWin.HEURIST4.msg.showElementAsDialog( 
                    {element:$dlg.get(0), height: iHeight, width:320, title:"Edit interval", modal:true} );
            /*
            $dlg.dialog({
            autoOpen: true,
            height: iHeight,
            width: 220,
            modal: true,
            resizable: false,
            draggable: true,
            title: window.hWin.HR("Select terms"),
            buttons: [
            {text:window.hWin.HR('Apply'), click: __addeditInterval},
            {text:window.hWin.HR('Cancel'), click: function() {
            $( this ).dialog( "close" );
            }}
            ]
            });
            */
        }else{
            window.hWin.HEURIST4.msg.showMsgDlg('There are no more terms available');
        }


    }

    
    //
    //
    //
    function _autoRetrieve(){

        if(!_isPopupMode){
            if (_currentRecordset==null || _currentRecordset.resultCount<1){
                _setMode(3); //no results
            }else if( _currentRecordset.resultCount < MAX_FOR_AUTO_RETRIEVE){
                _setMode(2);
                
                if(!_selectedRtyID || isNaN(_selectedRtyID) || fields3.row.intervals.length<1 || Number(fields3.row.field)<1){
                    //critical settings are not defined
                    return;
                }else{
                    needServerRequest = true;
                    _doRetrieve();
                }
            }else {
                console.log( _currentRecordset.resultCount + ' click update to retrieve values ' );
            }
        }
    }
    
    /**
    * request to server for crosstab data
    */
    function _doRetrieve(){
        /*
        use `hdb_dos_3`;
        select d1.dtl_Value as cls, d2.dtl_Value as rws, count(*) as cnt
        from Records
        left join recDetails d1 on d1.dtl_RecID=rec_ID and d1.dtl_DetailTypeID=85
        left join recDetails d2 on d2.dtl_RecID=rec_ID and d2.dtl_DetailTypeID=81
        where rec_RectypeID=15
        group by d1.dtl_Value, d2.dtl_Value
        order by d2.dtl_Value, cast(d1.dtl_Value as decimal);
        */

        if(needServerRequest){

            if(inProgress){
                window.hWin.HEURIST4.msg.showMsgDlg('Preparation in progress');
                return;
            }

            if(!_selectedRtyID || _selectedRtyID<1){
                window.hWin.HEURIST4.msg.showMsgFlash('Record type is not defined',1000);
                $recTypeSelector.focus();
                
                return;
            }
            if(fields3.row.field<1){
                window.hWin.HEURIST4.msg.showMsgDlg('Row field is not defined');
                $('#cbRows').focus();
                return;
            }
            if(fields3.row.intervals.length<1){
                window.hWin.HEURIST4.msg.showMsgDlg('There are no values for the "'+fields3.row.fieldname+'" field. '
                            +'Please check the set of records you are analysing ');
                $('#cbRows').focus();
                return;
            }

            $("#pmessage").html('Requesting...');
            _setMode(1); //progress
            
        var session_id = Math.round((new Date()).getTime()/1000);    
        
        var request = { a:'crosstab',
                rt:_selectedRtyID ,
                dt_row:fields3.row.field, 
                dt_rowtype:fields3.row.type,
                session:session_id}
            
        if(_currentRecordset!=null){
            request['recordset'] = _currentRecordset; //CSV
        }else{
            request['q'] = query_main;
            request['w'] = query_domain;
        }
        
        params = '';

            if(fields3.page.field>0){

                if(fields3.page.intervals.length<1){
                    window.hWin.HEURIST4.msg.showMsgDlg('There are no values for the "'+fields3.page.fieldname+'" field. '
                            +'Please check the set of records you are analysing ');
                    $('#cbPages').focus();
                    _setMode(2); //results
                    return;
                }

                params = params + '&dt_page='+fields3.page.field;
                params = params + '&dt_pagetype='+fields3.page.type;
                
                request['dt_page'] = fields3.page.field;
                request['dt_pagetype'] = fields3.page.type;
            }
            if(fields3.column.field>0){

                if(fields3.column.intervals.length<1){
                    window.hWin.HEURIST4.msg.showMsgDlg('There are no values for the "'+fields3.column.fieldname+'" field. '
                            +'Please check the set of records you are analysing ');
                    $('#cbColumn').focus();
                    _setMode(2); //results
                    return;
                }

                params = params + '&dt_col='+fields3.column.field;
                params = params + '&dt_coltype='+fields3.column.type;

                request['dt_col'] = fields3.column.field;
                request['dt_coltype'] = fields3.column.type;
            }

            var aggregationMode = $("input:radio[name=aggregationMode]:checked").val();
            if(aggregationMode!="count" && $('#cbAggField').val()){

                request['agg_mode'] = aggregationMode;
                request['agg_field'] = $('#cbAggField').val();

                params = params + '&agg_mode='+aggregationMode;
                params = params + '&agg_field='+request.agg_field;
            }

            inProgress = true;
            var to = setTimeout(function(){
                to = 0;
                _setMode(0); //results
                inProgress = false;
                },120000);

           function __hideProgress(){
                clearTimeout(to);
                to = 0;
                inProgress = false;
           }                     
                
            var baseurl = window.hWin.HAPI4.baseURL + "viewers/crosstab/crosstabs_srv.php";
            
            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null,
                function( response ){
                    __hideProgress();
//
//console.log('finised');
//console.log(response.data);                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        
                        needServerRequest = false;
                        records_resp = response.data;
                        _doRender();

                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });                
                
        }else{
            _doRender();
        }

    }

    //round to two digits
    function rnd(original){
        return Math.round(original*100)/100;
    }

    /**
    * render crosstab data as set of tables
    */
    function _doRender(){

        $("#pmessage").html('Rendering...');
        _setMode(1);//progress

        var pages = fields3.page.intervals;
        var plen = pages.length;

        $divres = $("#divres");
        $divres.hide();
        $divres.empty();

        if(_isPopupMode){
        $divres.append('<div style="text-align:center"><button onclick="crosstabsAnalysis.setMode(0)">Back to form</button>&nbsp;&nbsp;<button onclick="crosstabsAnalysis.doPrint()">Print</button></div>');
        }else{
            $('#btnPrint').show();
        }
        

        $divres.append('<div>Database name: <b>'+window.hWin.HAPI4.database+'</b></div>');
        $divres.append('<div>Date and time: '+ (new Date()) +'</div>');
        $divres.append('<div>Type of analysis: Crosstab</div>');
        //$divres.append('<div>Title (name) of saved analysis: '+ +'</div>');
        //????? $divres.append('<div>Record type analysed: '++'</div>');
        if(_currentRecordset!=null){
            $divres.append('<div>Record count: '+ _currentRecordset['recordCount'] +'</div>');
            $divres.append('<div>Query string: '+_currentRecordset['query_main'] +'</div>');
            
        }else{
            $divres.append('<div>Query string: q='+query_main+'&w='+query_domain +'</div>');
        }
        
            
        //$divres.append('<div>Total number of records: '+ +'</div>');
        //$divres.append('<div>Number of records for each record type</div>');

        //eg. Artefact N=37, Deposit N=12

        var aggregationMode = $("input:radio[name=aggregationMode]:checked").val();
        if(aggregationMode!="count"){
            aggregationMode = (aggregationMode=="avg")?"Average":"Sum";
            aggregationMode = aggregationMode + ' of '+$("#cbAggField option:selected").text();
        }else{
            aggregationMode = "Counts";
        }

        $divres.append('<div>Type of value displayed: <b>'+aggregationMode+'</b></div>');

        $divres.append('<div>---------------------------------</div>');
        //Type of value displayed (count, average, sum)


        if(plen<1){ //less than 1 page
            doRenderPage('', records_resp);
        }else{

            var i, idx, curpage_val, curr_interval_idx=-1, page_interval_idx=-1;
            var records = [];

            //create output array, calculate totals
            var rlen = records_resp.length;
            for (idx=0; idx<rlen; idx++) {
                if(idx){

                    //if(typeof curpage_val==="undefined" ||
                    if(curpage_val!=records_resp[idx][3])
                    {

                        var pval = records_resp[idx][3]; //page value
                        curpage_val = pval;

                        page_interval_idx = -1;
                        for (i=0; i<plen; i++){
                            if( fitToInterval( fields3.page.type, pages[i].values, pval ) ){
                                page_interval_idx = i;
                                break;
                            }
                        }


                        if(page_interval_idx>=0 && curr_interval_idx!=page_interval_idx && records.length>0){
                            if(curr_interval_idx>=0){
                                doRenderPage(fields3.page.fieldname+'. '+pages[curr_interval_idx].name, records);
                            }
                            records = [];
                        }
                    }

                    if(page_interval_idx>=0){
                        curr_interval_idx = page_interval_idx;
                        records.push(records_resp[idx]);
                    }

                }
            }//records
            if(records.length>0){
                doRenderPage(fields3.page.fieldname+'. '+pages[curr_interval_idx].name, records);
            }

        }

        $divres.find('td').css( {'padding':'4px', 'border':'1px dotted gray'} );//{'border':'1px dotted gray'}); //1px solid gray'});
        _setMode(2);//results
    }//_doRender

    /**
    * render particular page (group)
    */
    function doRenderPage(pageName, records){

        //fields3 {column:{field:0, type:'', values:[], intervals:[]}
        //     intervals:{name: , description:, values:[  ] }

        //parameters
        var supressZero = $('#rbSupressZero').is(':checked');
        var showValue = $('#rbShowValue').is(':checked');
        var showTotalsRow = $('#rbShowTotals').is(':checked');//$('#rbShowTotalsRow').is(':checked');
        var showTotalsColumn = $('#rbShowTotals').is(':checked'); //$('#rbShowTotalsColumn').is(':checked');
        var showPercentageRow = $('#rbShowPercentRow').is(':checked');
        var showPercentageColumn = $('#rbShowPercentColumn').is(':checked');
        var supressBlankRow = !$('#rbShowBlanks').is(':checked');
        var supressBlankColumn = supressBlankRow;
        var supressBlankPage = false;

        var aggregationMode = $("input:radio[name=aggregationMode]:checked").val();
        var isAVG = (aggregationMode === "avg");
        if(isAVG){
            showPercentageRow = false;
            showPercentageColumn = false;
        }

        //var $table = $('#tabres');
        //$table.empty();
        var idx,i,j;

        var columns = fields3.column.intervals;
        var rows = fields3.row.intervals;

        var clen = columns.length;
        var rlen = rows.length;

        var hasValues = false;
        var grantotal = 0;
        var colspan = 1;

        if(showPercentageRow && clen>0) colspan++;
        if(showPercentageColumn) colspan++;

        var noColumns = (clen==0);
        if(noColumns){
            clen = 1;
            columns = [];
            columns.push({});
        }

        //reset output array for rows  set all cells to 0
        for (i=0; i<rlen; i++){  //by rows

            rows[i].output = [];
            rows[i].avgcount = [];
            rows[i].percent_col = [];
            rows[i].percent_row = [];


            rows[i].total = 0;
            rows[i].percent = 0;
            rows[i].isempty = true;


            for (j=0; j<clen; j++){  //by cols
                rows[i].output.push(0);
                rows[i].percent_col.push(0);
                rows[i].percent_row.push(0);
                rows[i].avgcount.push(0);
            }
        }
        for (j=0; j<clen; j++){
            columns[j].total = 0;
            columns[j].percent = 0;
            columns[j].isempty = true;
        }


        var currow_val=-1, row_interval_idx;

        //create output array, calculate totals
        for (idx in records){
            if(idx){

                if(currow_val!=records[idx][0]){
                    var rval = records[idx][0]; //row
                    currow_val = rval;
                    //find row interval it fits
                    row_interval_idx = -1;
                    for (i in rows){
                        if( fitToInterval( fields3.row.type, rows[i].values, rval ) ){
                            row_interval_idx = i;
                            break;
                        }
                    }
                }

                if(row_interval_idx>=0)
                {
                    if(noColumns){ //no columns

                        var val = parseFloat(records[idx][2]);   //WARNING - fix for AVG
                        if(!isNaN(val) && rnd(val)!=0){
                            rows[row_interval_idx].output[0] = rows[row_interval_idx].output[0] + rnd(val);
                            rows[row_interval_idx].avgcount[0] ++;
                            grantotal = grantotal + val;
                            rows[row_interval_idx].isempty = false;
                        }

                    }else{

                        for (j=0; j<clen; j++){
                            if( fitToInterval( fields3.column.type, columns[j].values, records[idx][1] ) ){
                                var val = parseFloat(records[idx][2]);   //WARNING - fix for AVG
                                if(!isNaN(val) && rnd(val)!=0){
                                    rows[row_interval_idx].output[j] = rows[row_interval_idx].output[j] + rnd(val);
                                    rows[row_interval_idx].avgcount[j] ++;
                                    rows[row_interval_idx].total = rows[row_interval_idx].total + val;
                                    rows[row_interval_idx].isempty = false;

                                    columns[j].isempty = false;
                                    columns[j].total = columns[j].total + val;
                                }

                                break;
                            }
                        }
                    }
                }
            }
        }//records

        //special calc fo average
        if(isAVG)
        {
            for (i=0; i<rlen; i++){
                rows[i].total = 0;
            }

            var cols_with_values_for_row = [];
            for (i=0; i<rlen; i++){
                cols_with_values_for_row.push(0);
            }
            
            
            for (j=0; j<clen; j++){  //cols
                columns[j].total = 0;
                
                var rows_with_values = 0;
                
                for (i=0; i<rlen; i++){  //rows
                    if(rows[i].avgcount[j]>1){
                        rows[i].output[j] = rnd(rows[i].output[j]/rows[i].avgcount[j]);
                    }

                    if(rows[i].output[j]>0){
                        cols_with_values_for_row[i]++;  
                        rows_with_values++;  
                    } 
                    
                    rows[i].total = rows[i].total + rows[i].output[j];
                    columns[j].total = columns[j].total + rows[i].output[j];
                }

                if(rows_with_values>0) {
                    columns[j].total = rnd(columns[j].total/rows_with_values); //was rlen
                }
            }

            grantotal = 0;
            var cnt_avg = 0;
            for (i=0; i<rlen; i++){
                if(cols_with_values_for_row[i]>0){
                    rows[i].total = rnd(rows[i].total/cols_with_values_for_row[i]); //clen);
                }
                grantotal = grantotal + rows[i].total;
                if(rows[i].total>0) cnt_avg++;
            }
            grantotal = rnd(grantotal/cnt_avg);


        }else{

            //
            //
            if(noColumns){
                if(grantotal!=0){
                    for (i=0; i<rlen; i++){
                        rows[i].percent_row[0] =  rnd(rows[i].output[0]*100/grantotal);
                    }
                }
            }else{

                for (i=0; i<rlen; i++){
                    grantotal = grantotal + rows[i].total;
                }

                //calculate percentage
                if(showPercentageRow || showPercentageColumn){

                    for (j=0; j<clen; j++){
                        for (i=0; i<rlen; i++){


                            if(rows[i].total!=0){
                                rows[i].percent_row[j] =  rnd(rows[i].output[j]*100/rows[i].total);
                            }
                            if(columns[j].total!=0){
                                rows[i].percent_col[j] =  rnd(rows[i].output[j]*100/columns[j].total);
                            }

                            if(grantotal!=0) rows[i].percent = rnd(rows[i].total*100/grantotal);
                        }

                        if(grantotal!=0) columns[j].percent = rnd(columns[j].total*100/grantotal);
                    }

                }
            }

        }

        var s, notemtycolumns = 0;
        //main render output   .css({'border':'1px solid black'})
        var $table = $('<table>').attr('cellspacing','0');

        if(!noColumns){
            var row1 = $('<tr>').appendTo($table);
            for (j=0; j<clen; j++){
                if(supressBlankColumn && columns[j].isempty) continue;
                notemtycolumns++;
            }
            row1.append('<td>&nbsp;</td><td class="crosstab-header0" style="{text-align:center;}" colspan="'+notemtycolumns*colspan+(showTotalsColumn?1:0)+'">'+fields3.column.fieldname+'</td>');
        }

        var $row = $('<tr>').appendTo($table);
        $row.append('<td class="crosstab-header0">'+fields3.row.fieldname+'</td>');

        // render HEADER, reset column totals
        if(noColumns){
            $row.append('<td colspan="'+colspan+'">&nbsp;</td>');
        }else{
            for (j=0; j<clen; j++){
                if(supressBlankColumn && columns[j].isempty) continue;
                $row.append('<td class="crosstab-header" style="{width:'+colspan*4+'em;max-width:'+colspan*4+'em}" colspan="'+colspan+'">'+columns[j].name+'</td>');
                //notemtycolumns++;
            }
            if(showTotalsRow){ //special column for totals
                $row.append('<td class="crosstab-header0" style="{text-align:center;}" colspan="'+colspan+'">totals</td>');  //(showPercentageRow?2:1)  ART2
            }else if(showTotalsColumn){
                $row.append('<td class="crosstab-header0" style="{text-align:center;}">totals</td>');
            }

            if(showPercentageRow && showPercentageColumn){
                $row = $('<tr>').appendTo($table);
                $row.append('<td>&nbsp;</td>');
                for (j=0; j<clen; j++){
                    if(supressBlankColumn && columns[j].isempty) continue;
                    $row.append('<td class="crosstab-value">&nbsp;</td><td class="percent">row%</td><td class="percent">col%</td>');
                }
                if(showTotalsRow || showTotalsColumn){
                    $row.append('<td colspan="'+colspan+'">&nbsp;</td>');  //(showTotalsRow && showPercentageRow?2:1)   ART2
                }
            }
        }


        for (i=0; i<rlen; i++){

            if(supressBlankRow && rows[i].isempty) continue;
            
            hasValues = true;

            $row = $('<tr>').appendTo($table);
            $row.append('<td class="crosstab-header" style="{text-align:left;}">'+rows[i].name+'</td>');

            if(noColumns){
                if(rows[i].output[0]!=0 || !supressZero){
                    s = '<td class="crosstab-value">'+rows[i].output[0] +'</td>'
                    if(showPercentageColumn){
                        s = s+'<td class="percent">'+rows[i].percent_row[0] +'%</td>'
                    }
                    $row.append(s);
                }else{
                    $row.append('<td colspan="'+colspan+'">&nbsp;</td>');
                }
            }else{

                for (j=0; j<clen; j++){

                    if(supressBlankColumn && columns[j].isempty) continue;

                    if(rows[i].output[j]!=0 || !supressZero){
                        s = '<td class="crosstab-value">'+rows[i].output[j] +'</td>'
                        if(showPercentageRow){
                            s = s+'<td class="percent">'+rows[i].percent_row[j] +'%</td>'
                        }
                        if(showPercentageColumn){
                            s = s+'<td class="percent">'+rows[i].percent_col[j] +'%</td>'
                        }

                        $row.append(s);
                    }else{
                        $row.append('<td colspan="'+colspan+'">&nbsp;</td>');
                    }
                }

                if(showTotalsRow){ //special column for totals
                    if(rows[i].total!=0 || !supressZero){
                        s = '<td class="crosstab-total">'+rnd(rows[i].total) +'</td>';
                        if(showPercentageRow){
                            s = s+'<td class="percent">'+rows[i].percent +'%</td>'
                        }
                        if(showPercentageColumn){
                            s = s+'<td class="percent">100%</td>'
                        }
                        $row.append(s);
                    }else{
                        $row.append('<td colspan="'+colspan+'">&nbsp;</td>'); //(showPercentageRow?2:1) ART2
                    }
                }else if(showTotalsColumn){
                    $row.append('<td>&nbsp;</td>');
                }
            }
        }

        // LAST ROW - totals
        if(noColumns){

            if(showTotalsColumn && grantotal!=0){
                $row = $('<tr>').appendTo($table);
                $row.append('<td class="crosstab-header0" >totals</td>');
                $row.append('<td class="crosstab-total" colspan="'+colspan+'">'+rnd(grantotal) +'</td>');
            }

        }else{

            if(showTotalsColumn){ //columns totals - last row in table
                $row = $('<tr>').appendTo($table);
                $row.append('<td class="crosstab-header0">Totals</td>');

                for (j=0; j<clen; j++){
                    if(supressBlankColumn && columns[j].isempty) continue;

                    if(columns[j].total!=0 || !supressZero){
                        s = '<td class="crosstab-total">'+rnd(columns[j].total) +'</td>';

                        if(showPercentageRow){
                            s = s+'<td class="percent">'+(showPercentageColumn?'100%':'&nbsp;')+'</td>'
                        }
                        if(showPercentageColumn){
                            s = s+'<td class="percent">'+  columns[j].percent +'%</td>'
                        }

                        $row.append(s);
                    }else{
                        $row.append('<td colspan="'+colspan+'">&nbsp;</td>');
                    }
                }

                $row.append('<td class="crosstab-total">'+rnd(grantotal)+'</td>');
                if(showPercentageRow || showPercentageColumn){
                    $row.append('<td colspan="'+colspan+'">&nbsp;</td>');  //(showPercentageRow?2:1)
                }
            }else if(showTotalsRow){
                $row = $('<tr>').appendTo($table);
                $row.append('<td colspan="'+(notemtycolumns*colspan+1)+'">&nbsp;</td>');
                $row.append('<td class="crosstab-total" colspan="'+colspan+'">'+grantotal+'</td>');  //(showPercentageRow?2:1) ART2
            }

        }

        if(hasValues){ //grantotal!=0){
            $divres.append('<h2 class="crosstab-page">'+pageName+'</h2>');
            $table.appendTo($divres);

            $divres.append('<div>---------------------------------</div>');

        }else if (!supressBlankPage) {
            $divres.append('<h2 class="crosstab-page">'+pageName+'</h2>');
            $divres.append("<div>empty set</div>");
        }

        /*
        var idx,
        currow,
        s = '';

        for (idx in res){
        if(idx){
        if(currow != res[idx][1]){
        //new row
        currow = res[idx][1];
        s = s + "<br><div style='font-weight:bold;display:inline-block;'>"+currow+"</div>";
        }

        s  = s + "<div style='display:inline-block;padding-left:1em;'>"+res[idx][2]+"</div>";

        }
        }

        //$row = $('<tr>').appendTo($tb);
        $divres.html(s);
        */


    }

    function fitToInterval(type, values, val){
        if(type=="enum" || type=="resource" || type=="relationtype"){
            return (values.indexOf(val)>=0);
        }else{
            val = parseFloat(val);
            return (val>=values[0] && val<=values[1]);
        }
    }

    function _changeAggregationMode(){
        needServerRequest = true;

        if($('#cbAggField').get(0).length<1){
            $('#aggSum').hide();
            $('#aggAvg').hide();
            $('#divAggField').hide();

            $('#aggregationModeCount').prop('checked',true); //val("count");
        }else{
            $('#aggSum').css('display','inline-block');
            $('#aggAvg').css('display','inline-block');
            $('#divAggField').css('display','inline-block');
        }
        var aggMode = $("input:radio[name=aggregationMode]:checked").val();
        if ( aggMode == "count" ) {

            $('#cbAggField').attr('disabled','disabled');
            //$('#divAggField').css('visibility','hidden');
            $('#divAggField').hide();
        }else{
            $('#cbAggField').removeAttr('disabled');
            //$('#divAggField').css('visibility','visible');
            $('#divAggField').css('display','inline-block');
        }

        if ( aggMode == "avg" ) {
            $("#rbShowPercentColumn").attr('disabled','disabled');
            $("#rbShowPercentRow").attr('disabled','disabled');
            $("#rbShowValue").attr('disabled','disabled');
            $("#rbShowPercentColumn").prop('checked',false);
            $("#rbShowPercentRow").prop('checked',false);
            $("#rbShowValue").prop('checked',true);
        }else{
            $("#rbShowValue").removeAttr('disabled');
            $("#rbShowPercentColumn").removeAttr('disabled');
            $("#rbShowPercentRow").removeAttr('disabled');
        }

    }

    //
    // 0,2 - show results
    // 1 - progress
    // 3 - empty set
    //
    function _setMode(mode){

        $("#inporgress").hide();
        if(mode==3){  //no results
            $("#divres").hide();
            $("#qform").hide();
            $("#div_empty").show();
        }else{  
            //show results
            $("#divres").show();
            $("#qform").show();
            $("#div_empty").hide();
        }
        if(mode==1){ //progress
            $("#inporgress").show();
            $("#divres").empty();
            $('#btnPrint').hide();
        }else if(mode==2){
            //$("#divres").show();
        }else{
            //$("#qform").show();
        }
    }
    
    //
    //
    //
    function _getSettings(){
        
        var settings = {
            aggregationMode: $("input:radio[name=aggregationMode]:checked").val(),
            agg_field: $('#cbAggField').val(),
            supressZero: $('#rbSupressZero').is(':checked')?1:0,
            showValue: $('#rbShowValue').is(':checked')?1:0,
            showTotals: $('#rbShowTotals').is(':checked')?1:0,
            
            showPercentageRow: $('#rbShowPercentRow').is(':checked')?1:0,
            showPercentageColumn: $('#rbShowPercentColumn').is(':checked')?1:0,
            supressBlanks: !$('#rbShowBlanks').is(':checked')?1:0,
            fields: {column:fields3.column.field,row:fields3.row.field,page:fields3.page.field}
        };

        return settings;
    }

    //
    //
    //
    function _applySettings( settings ){
        
        clearIntervals('column');
        clearIntervals('row');
        clearIntervals('page');
        
        $('input:radio[value="'+settings.aggregationMode+'"]').prop('checked', true);
        $('#cbAggField').val(settings.agg_field);
        _changeAggregationMode();

        $('#rbSupressZero').prop('checked',settings.supressZero==1);
        $('#rbShowValue').prop('checked',settings.showValue==1);
        $('#rbShowTotals').prop('checked',settings.showTotals==1);
        
        $('#rbShowPercentRow').prop('checked',settings.showPercentageRow==1);
        $('#rbShowPercentColumn').prop('checked',settings.showPercentageColumn==1);
        $('#rbShowBlanks').prop('checked',settings.supressBlanks==0);
        
        _resetAllIntervals(settings.fields);
    }
    
    //
    //public members
    //
    var that = {

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            return (strClass === _className);
        },

        changeAggregationMode: function(){
            _changeAggregationMode();
            _autoRetrieve();
        },

        resetIntervals: function(event){
            _resetIntervals(event);
        },

        doRetrieve: function(){
            needServerRequest = true;
            _doRetrieve();
        },

        doSave: function(){
            //_doRetrieve();
            window.hWin.HEURIST4.msg.showMsgDlg('Sorry. Not implemented yet');
        },

        doCancel: function(){
            window.close(null);
        },

        setMode: function(mode){
            _setMode(mode);
        },

        doPrint: function(){
            window.hWin.HEURIST4.msg.showMsgDlg('Sorry. Not implemented yet');
        },

        assignRecordset: function(recordset){

            _currentRecordset = recordset;
            
            //change value of rectype selector
            var rt = $recTypeSelector.val();
            if(!(rt>0) && recordset['first_rt']>0){
                $recTypeSelector.val(recordset['first_rt']);
                _onRectypeChange();
            }
            
            if(_currentRecordset.resultCount < MAX_FOR_AUTO_RETRIEVE){
                $('#btnUpdate').hide();
            }else{
                $('#btnUpdate').show();
            }
            
            _autoRetrieve();
        },
        
        autoRetrieve:function(){
            _autoRetrieve();
        },

        doRender:function(){
            _doRender();
        }
    };

    _init(_query, _query_domain);  // initialize before returning
    return that;

}