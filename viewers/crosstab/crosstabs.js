/*
* Copyright (C) 2005-2016 University of Sydney
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
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/

var crosstabsAnalysis;

//aliases
var Hul = top.HEURIST.util;

/**
*  CrosstabsAnalysis - class for crosstab analysis                                           b
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2013.0530
*/
function CrosstabsAnalysis(_database, _query, _query_domain) {

    var _className = "CrosstabsAnalysis";

    var MAX_FOR_AUTO_RETRIEVE = 6000;
    
    var recordtype;
    var fields3 = {column:{field:0, type:'', values:[], intervals:[]}, row:{}, page:{}};
    //     intervals:{name: , description:, values:[  ] }
    var records_resp;
    var keepCount = 10;
    var needServerRequest = true;
    var inProgress = false;
    var database;
    var query_main;
    var query_domain;
    
    var _currentRecordset = null;

    var _isPopupMode = false;

    function _init(_database, _query, _query_domain)
    {
        if(!Hul.isempty(_query)){
            _isPopupMode = true;
        }else{
            $('#btnCancel').hide();
        }

        database = _database;
        query_main = _query?_query:'';
        query_domain =_query_domain?_query_domain:'all';

        createRectypeSelect($('#cbRectypes').get(0), null, ' ');

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
        
        
    }

    /**
    * update list of fields
    */
    function _onRectypeChange(event){

        needServerRequest = true;

        recordtype = Number(event.target.value);

        var allowedlist = ["enum", "integer", "float", "resource", "relationtype"];//, "date", "freetext"]; //"resource",

        var selObj = createRectypeDetailSelect($('#cbColumns').get(0), recordtype, allowedlist, ' ');
        createRectypeDetailSelect($('#cbRows').get(0), recordtype, allowedlist, ' ');
        createRectypeDetailSelect($('#cbPages').get(0), recordtype, allowedlist, ' ');

        if(selObj.options.length<2){
            $("#vars").hide();
            $("#shows").hide();
            $("#btnPanels").hide();
            $("#nofields").html(recordtype>0?'No suitable fields: numeric, pointer or enumeration types.':'Select record type.');
            $("#nofields").show();
        }else{
            $("#vars").show();
            $("#shows").show();
            $("#nofields").hide();
            $("#btnPanels").show();
        }

        createRectypeDetailSelect($('#cbAggField').get(0), recordtype, ["integer", "float"], false);
        _changeAggregationMode();

        clearIntervals('column');
        clearIntervals('row');
        clearIntervals('page');

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

    /**
    * create set of intervals specific for particular detail type
    */
    function _resetIntervals(event){

        needServerRequest = true;

        var detailid = event.target.value;
        var detailname = (event.target.selectedIndex>=0)?event.target.options[event.target.selectedIndex].text:''; //options[event.target.selectedIndex];
        var name = $(event.target).attr('name');  //type

        var $container = $('#'+name+'Intervals');
        $container.empty();
        fields3[name] = {field:0, fieldname:'', type:'', values:[], intervals:[], allownulls:false};

        if (!(top.HEURIST.rectypes.typedefs[recordtype] &&
            top.HEURIST.rectypes.typedefs[recordtype].dtFields[detailid]))
        {
            $container.hide();
            return;
        }

        //get detail type
        var fi = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex;
        var details = top.HEURIST.rectypes.typedefs[recordtype].dtFields[detailid];
        var detailtype = details[fi['dty_Type']];

        fields3[name] = {field:detailid, fieldname:detailname, type:detailtype, values:[], intervals:[]}

        if(detailtype=="enum" || detailtype=="relationtype") //false &&
        {
            //get all terms and create intervals
            calculateIntervals(name);

        }else if(detailtype=="float" || detailtype=="integer"){
            //get min and max for this detail in database

            var request = { a:'minmax', db: database, rt:recordtype , dt:detailid, session: Math.round((new Date()).getTime()/1000) };

            var baseurl = top.HEURIST.baseURL + "viewers/crosstab/crosstabs_srv.php";
            var params = "a=minmax&db=" + database+'&rt='+recordtype+'&dt='+detailid;
            
            /*
            if(_currentRecordset!=null){
                request['recordset'] = _currentRecordset;
            }else{
                request['q'] = query_main;
                request['w'] = query_domain;
            }
            */
            
            /* OLD
            top.HEURIST.util.getJsonData(baseurl,
                function(response){
                    if(response.status == "OK"){

                        var val0 = parseFloat(response.data.min);
                        var valmax = parseFloat(response.data.max);

                        if(isNaN(val0) || isNaN(valmax)){
                            $container = clearIntervals(name);
                            $container.html('There are no min max values for this field.');
                        }else{
                            fields3[name].values = [val0, valmax];
                            calculateIntervals(name);
                        }

                    }else{
                        alert(response.message);
                    }
                }, params);
            */    
                
           $.ajax({
                url: baseurl,
                type: "POST",
                data: request,
                dataType: "json",
                error: function(jqXHR, textStatus, errorThrown ) {
                    console.log(textStatus+' '+jqXHR.responseText);
                },
                success: function( response, textStatus, jqXHR ){
                    if(response.status == "OK"){

                        var val0 = parseFloat(response.data.min);
                        var valmax = parseFloat(response.data.max);

                        if(isNaN(val0) || isNaN(valmax)){
                            $container = clearIntervals(name);
                            $container.html('There are no min max values for this field.');
                        }else{
                            fields3[name].values = [val0, valmax];
                            calculateIntervals(name);
                        }

                    }else{
                        alert(response.message);
                    }
                }
            });                

            return;

        }else if(detailtype=="resource"){


            //get list of possible values for pointer detail type

            var request = { a:'pointers', db: database, rt:recordtype , dt:detailid };

            var baseurl = top.HEURIST.baseURL + "viewers/crosstab/crosstabs_srv.php";
            var params = "a=pointers&db=" + database+'&rt='+recordtype+'&dt='+detailid+"&q="+query_main+"&w="+query_domain;
            
            if(_currentRecordset!=null){
                request['recordset'] = _currentRecordset;
            }else{
                request['q'] = query_main;
                request['w'] = query_domain;
            }
            
            
            /*
            top.HEURIST.util.getJsonData(baseurl,
                function(response){
                    if(response.status == "OK"){

                        if(!response.data){
                            fields3[name].values = [];
                            $container = clearIntervals(name);
                            $container.html('There are no pointer values for this field.');
                        }else{
                            fields3[name].values = response.data;
                            calculateIntervals(name);
                        }

                    }else{
                        alert(response.message);
                    }
                }, params);*/
                
           $.ajax({
                url: baseurl,
                type: "POST",
                data: request,
                dataType: "json",
                error: function(jqXHR, textStatus, errorThrown ) {
                    console.log(textStatus+' '+jqXHR.responseText);
                },
                success: function( response, textStatus, jqXHR ){
                    if(response.status == "OK"){
                        if(!response.data){
                            fields3[name].values = [];
                            $container = clearIntervals(name);
                            $container.html('There are no pointer values for this field.');
                        }else{
                            fields3[name].values = response.data;
                            calculateIntervals(name);
                        }
                    }else{
                        alert(response.message);
                    }
                }
            });                
                

            return;

        }else if(detailtype=="date"){
            //get min and max for this detail in database

        }else if(detailtype=="freetext"){
            //alphabetically, or if distinct values less that 50 like terms

        }

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

            var fi = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex;
            var details = top.HEURIST.rectypes.typedefs[recordtype].dtFields[fields3[name].field];
            var detailtype = fields3[name].type; //details[fi['dty_Type']];

            var allTerms = details[fi['rst_FilteredJsonTermIDTree']];
            var headerTerms = details[fi['dty_TermIDTreeNonSelectableIDs']];
            var termlist = getPlainTermsList(detailtype, allTerms, headerTerms);

            fields3[name].values = termlist;

            var mcnt = (count>0)?Math.min(termlist.length, count):termlist.length;
            fields3[name].intervals = [];

            var i;
            for (i=0; i<mcnt; i++){
                fields3[name].intervals.push( {name:termlist[i].text, description:termlist[i].text, values:[ termlist[i].id ] });
            }


        }
        renderIntervals(name);
        _autoRetrieve();
    }

    /**
    * render intervals (create divs)
    */
    function renderIntervals(name){

        var $container = $('#'+name+'Intervals');
        $container.empty();

        if(fields3[name].intervals.length<1){
            $container.html('no values defined');
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
            '<img title="edit" src="' +top.HEURIST.baseURL+'common/images/edit_pencil_9x11.gif" '+
            'onclick="{top.HEURIST.search.savedSearchEdit('+sid+');}">';
            editbuttons += '<img  title="delete" src="'+top.HEURIST.baseURL+'common/images/delete6x7.gif" '+
            'onclick="{top.HEURIST.search.savedSearchDelete('+sid+');}"></div>';
            $intdiv.append(editbuttons);
            */

            $('<button>')
            .attr('intid', idx)
            //.button({icons: {primary: "ui-icon-pencil"}, text: false })
            .addClass('crosstab-interval-edit')
            .css({'background-image': 'url('+top.HEURIST.baseURL+'common/images/edit_pencil_9x11.gif)'})
            .click(function( event ) {
                editInterval( name,  $(this).attr('intid') );
            })
            .appendTo($intdiv);

            $('<button>')
            //.button({icons: {primary: "ui-icon-close"}, text: false })
            .attr('intid', idx)
            .addClass('crosstab-interval-edit')
            .css({'background-image': 'url('+top.HEURIST.baseURL+'common/images/delete6x7.gif)'})
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

        var dialogbox;

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
            function __addeditInterval(){

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

                top.HEURIST.util.closePopup(dialogbox.id);  //$dlg.dialog( "close" )
            }


            $dlg.find("#topdiv").append($('<button>').html('Apply').css('margin','1em').click(__addeditInterval));

            dialogbox = Hul.popupElement(window, $dlg.get(0), {height: iHeight, width:320, title:"Edit interval", modal:true} );
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
            alert('There are no more terms available');
        }


    }

    
    function _autoRetrieve(){

        if(!_isPopupMode){
            if (_currentRecordset==null || _currentRecordset.resultCount<1){
                _setMode(3);
            }else if( _currentRecordset.resultCount < MAX_FOR_AUTO_RETRIEVE){
                _setMode(2);
                
                if(!recordtype || isNaN(recordtype) || fields3.row.intervals.length<1 || Number(fields3.row.field)<1){
                    //critical settings are not defined
                    return;
                }else{
                    needServerRequest = true;
                    _doRetrieve();
                }
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
                alert('Preparation in progress');
                return;
            }

            if(!recordtype || recordtype<1){
                alert('Record type is not defined');
                $('#cbRectypes').focus();
                return;
            }
            if(fields3.row.field<1){
                alert('Row field is not defined');
                $('#cbRows').focus();
                return;
            }
            if(fields3.row.intervals.length<1){
                alert('No intervals for field "row" defined');
                $('#cbRows').focus();
                return;
            }

            $("#pmessage").html('Requesting...');
            _setMode(1);
            
        var session_id = Math.round((new Date()).getTime()/1000);    
        
        var request = { a:'crosstab', db: database, 
                rt:recordtype ,
                dt_row:fields3.row.field, 
                dt_rowtype:fields3.row.type,
                session:session_id}
            
        if(_currentRecordset!=null){
            request['recordset'] = _currentRecordset;
        }else{
            request['q'] = query_main;
            request['w'] = query_domain;
        }
        
        params = '';

            if(fields3.page.field>0){

                if(fields3.page.intervals.length<1){
                    alert('No intervals for field "page" defined');
                    $('#cbPages').focus();
                    _setMode(2);
                    return;
                }

                params = params + '&dt_page='+fields3.page.field;
                params = params + '&dt_pagetype='+fields3.page.type;
                
                request['dt_page'] = fields3.page.field;
                request['dt_pagetype'] = fields3.page.type;
            }
            if(fields3.column.field>0){

                if(fields3.column.intervals.length<1){
                    alert('No intervals for field "column" defined');
                    $('#cbColumn').focus();
                    _setMode(2);
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
                _setMode(0);
                inProgress = false;
                },20000);

           function __hideProgress(){
                clearTimeout(to);
                to = 0;
                inProgress = false;
           }                     
                
            var baseurl = top.HEURIST.baseURL + "viewers/crosstab/crosstabs_srv.php";

            /*  OLD
                top.HEURIST.util.getJsonData(baseurl,
                function(response){

                    clearTimeout(to);
                    to = 0;
                    inProgress = false;

                    if(response.status == "OK"){

                        needServerRequest = false;


                        records_resp = response.data;
                        _doRender();
                    }else{
                        alert(response.message);
                    }
                }, params);*/

                
            $.ajax({
                url: baseurl,
                type: "POST",
                data: request,
                dataType: "json",
                error: function(jqXHR, textStatus, errorThrown ) {
                    console.log(textStatus+' '+jqXHR.responseText);
                    __hideProgress();
                },
                success: function( response, textStatus, jqXHR ){
                    __hideProgress();
                    if(response.status == "OK"){
                        needServerRequest = false;
                        records_resp = response.data;
                        _doRender();
                    }else{
                        alert(response.message);
                    }
                }
            });
                
        }else{
            _doRender();
        }

    }

    function rnd(original){
        return Math.round(original*10)/10;
    }

    /**
    * render crosstab data as set of tables
    */
    function _doRender(){

        $("#pmessage").html('Rendering...');
        _setMode(1);

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

        $divres.append('<div>Database name: '+database+'</div>');
        $divres.append('<div>Date and time: '+ (new Date()) +'</div>');
        $divres.append('<div>Type of analysis: Crosstab</div>');
        //$divres.append('<div>Title (name) of saved analysis: '+ +'</div>');
        //????? $divres.append('<div>Record type analysed: '++'</div>');
        $divres.append('<div>Query string: q='+query_main+'&w='+query_domain +'</div>');
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

        $divres.append('<div>Type of value displayed: '+aggregationMode+'</div>');

        $divres.append('<div>---------------------------------</div>');
        //Type of value displayed (count, average, sum)


        if(plen<1){
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
        _setMode(2);
    }//_doRender

    /**
    * render particular page (group)
    */
    function doRenderPage(pageName, records){

        //fields3 {column:{field:0, type:'', values:[], intervals:[]}
        //     intervals:{name: , description:, values:[  ] }

        var supressZero = $('#rbSupressZero').get(0).checked;
        var showValue = $('#rbShowValue').get(0).checked;
        var showTotalsRow = $('#rbShowTotals').get(0).checked;//$('#rbShowTotalsRow').get(0).checked;
        var showTotalsColumn = $('#rbShowTotals').get(0).checked; //$('#rbShowTotalsColumn').get(0).checked;
        var showPercentageRow = $('#rbShowPercentRow').get(0).checked;
        var showPercentageColumn = $('#rbShowPercentColumn').get(0).checked;
        var supressBlankRow = !$('#rbShowBlanks').get(0).checked;
        var supressBlankColumn = supressBlankRow;
        var supressBlankPage = supressBlankRow;

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

        //reset output array for rows
        for (i=0; i<rlen; i++){

            rows[i].output = [];
            rows[i].avgcount = [];
            rows[i].percent_col = [];
            rows[i].percent_row = [];


            rows[i].total = 0;
            rows[i].percent = 0;
            rows[i].isempty = true;


            for (j=0; j<clen; j++){
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


        var currow_val, row_interval_idx;

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

            for (j=0; j<clen; j++){  //cols
                columns[j].total = 0;
                for (i=0; i<rlen; i++){  //rows
                    if(rows[i].avgcount[j]>1){
                        rows[i].output[j] = rnd(rows[i].output[j]/rows[i].avgcount[j]);
                    }

                    rows[i].total = rows[i].total + rows[i].output[j];
                    columns[j].total = columns[j].total + rows[i].output[j];
                }

                columns[j].total = rnd(columns[j].total/rlen);
            }

            grantotal = 0;
            for (i=0; i<rlen; i++){
                rows[i].total = rnd(rows[i].total/clen);
                grantotal = grantotal + rows[i].total;
            }
            grantotal = rnd(grantotal/rlen);


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


        if(grantotal!=0){
            $divres.append('<h2 class="crosstab-page">'+pageName+'</h2>');
            $divres.append($table);

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
        val = parseFloat(val);
        if(type=="enum" || type=="resource" || type=="relationtype"){
            return (values.indexOf(val)>=0);
        }else{
            return (val>=values[0] && val<=values[1]);
        }
    }

    function _changeAggregationMode(){
        needServerRequest = true;

        if($('#cbAggField').get(0).length<1){
            $('#aggSum').hide();
            $('#aggAvg').hide();
            $('#divAggField').hide();

            $('#aggregationModeCount').get(0).checked = true; //val("count");
        }else{
            $('#aggSum').css('display','inline-block');
            $('#aggAvg').css('display','inline-block');
            $('#divAggField').css('display','inline-block');
        }
        var aggMode = $("input:radio[name=aggregationMode]:checked").val();
        if ( aggMode == "count" ) {

            $('#cbAggField').attr('disabled','disabled');
            //$('#divAggField').hide();
        }else{
            $('#cbAggField').removeAttr('disabled');
            //$('#divAggField').css('display','inline-block');
        }

        if ( aggMode == "avg" ) {
            $("#rbShowPercentColumn").attr('disabled','disabled');
            $("#rbShowPercentRow").attr('disabled','disabled');
            $("#rbShowValue").attr('disabled','disabled');
            $("#rbShowPercentColumn").get(0).checked = false;
            $("#rbShowPercentRow").get(0).checked = false;
            $("#rbShowValue").get(0).checked = true;
        }else{
            $("#rbShowValue").removeAttr('disabled');
            $("#rbShowPercentColumn").removeAttr('disabled');
            $("#rbShowPercentRow").removeAttr('disabled');
        }

    }


    /**
    * create/fill SELECT for rectypes
    *
    * rectypeList - constraint options to this list
    */
    function createRectypeSelect(selObj, rectypeList, sFirstEmptyEntry) {

        if(selObj==null){
            selObj = document.createElement("select");
        }else{
            $(selObj).empty();
        }

        var rectypes = top.HEURIST.rectypes,
        index;

        if(!rectypes) return selObj;


        if(sFirstEmptyEntry){
            Hul.addoption(selObj, '', sFirstEmptyEntry);
        }

        if(rectypeList){

            if(!Hul.isArray(rectypeList)){
                rectypeList = rectypeList.split(',');
            }

            for (var idx in rectypeList)
            {
                if(idx){
                    var rectypeID = rectypeList[idx];
                    var name = rectypes.names[rectypeID];
                    if(!Hul.isnull(name))
                    {
                        Hul.addoption(selObj, rectypeID, name);
                    }
                }
            }
        }else{
            for (index in rectypes.groups){
                if (index == "groupIDToIndex" ||
                    rectypes.groups[index].showTypes.length < 1) {
                    continue;
                }
                var grp = document.createElement("optgroup");
                grp.label = rectypes.groups[index].name;
                selObj.appendChild(grp);

                for (var recTypeIDIndex in rectypes.groups[index].showTypes)
                {
                    var rectypeID = rectypes.groups[index].showTypes[recTypeIDIndex];
                    var name = rectypes.names[rectypeID];

                    if(!Hul.isnull(name)){
                        Hul.addoption(selObj, rectypeID, name);
                    }
                }
            }
        }

        return selObj;
    }

    /**
    * create/fill SELECT for details of given recordtype
    *
    * rectypeList - constraint options to this list
    */
    function createRectypeDetailSelect(selObj, rectype, allowedlist, sFirstEmptyEntry) {

        if(selObj==null){
            selObj = document.createElement("select");
        }else{
            $(selObj).empty();
        }

        if(!(top.HEURIST.rectypes && top.HEURIST.rectypes.typedefs)) return selObj;

        var rectypes = top.HEURIST.rectypes.typedefs[rectype],
        dtyID;

        if(!rectypes) return selObj;


        if(sFirstEmptyEntry){
            Hul.addoption(selObj, '', sFirstEmptyEntry);
        }

        var fi = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex['rst_DisplayName'],
        fit = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex['dty_Type'];

        var details = rectypes.dtFields;
        
        if(true){
            
            //gather by group
            var groups = {};
            for (dtyID in details){
                if(dtyID){
                    
                    var dtype = details[dtyID][fit];

                    if(allowedlist==null || allowedlist.indexOf(dtype)>=0)
                    {
                        if(groups[dtype]){
                            groups[dtype].push(dtyID);
                        }else{
                            groups[dtype] = [dtyID];
                        }
                    }
                }
            }

            var i,j, dtypes = Object.keys(groups);
            for (i=0; i<dtypes.length; i++){
                
                var optgroup = document.createElement("optgroup");
                optgroup.label = top.HEURIST.detailTypes.lookups[dtypes[i]];
                selObj.appendChild(optgroup);
                
                for (j=0; j<groups[dtypes[i]].length; j++){
                    
                        dtyID =  groups[dtypes[i]][j];
                        var name = details[dtyID][fi];
                        if(!Hul.isnull(name)){
                            
                            var opt = new Option(name, dtyID);
                            opt.className = "depth2";
                            opt.depth = 2;
                            optgroup.appendChild(opt);
                            
                            //Hul.addoption(selObj, dtyID, '...'+name);
                        }
                    
                }
                
            }
       
        }else{
            
        
            for (dtyID in details){
                if(dtyID){

                    if(allowedlist==null || allowedlist.indexOf(details[dtyID][fit])>=0)
                    {
                        var name = details[dtyID][fi];

                        if(!Hul.isnull(name)){
                            Hul.addoption(selObj, dtyID, name);
                        }
                    }
                }
            }
        
        }


        return selObj;
    }

    /**
    *
    */
    function getPlainTermsList(datatype, termIDTree, headerTerms) {
        //var selObj = Hul.createTermSelectExt(null, datatype, termIDTree, headerTermIDsList);

        var terms = top.HEURIST.terms;//.treesByDomain[datatype];

        if(!isNaN(Number(termIDTree))){
            //this is vocabulary id - show list of all terms for this vocab
            var tree = terms.treesByDomain[datatype];
            termIDTree = tree[termIDTree];
        }else{
            termIDTree = (typeof termIDTree == "string") ? $.parseJSON(termIDTree) : null;
            if(termIDTree==null){
                termIDTree =terms.treesByDomain[datatype];
            }
        }
        headerTerms = ( Hul.isArray(headerTerms)   //instanceof(Array)
            ? headerTerms
            : (( typeof(headerTerms) === "string" && !Hul.isempty(headerTerms) )
                ? $.parseJSON(headerTerms)
                : [] ));

        var selObj = Hul.createTermSelectExt(termIDTree, headerTerms, datatype, null, false);

        //createTermSelectExt: function(termIDTree, disabledTermIDsList, datatype, defaultTermID, isAddFirstEmpty) { // Creates the preview

        var reslist = [];

        if(selObj){
            for (var i=0; i<selObj.length; i++){
                if(!selObj.options[i].disabled){
                    reslist.push({id:parseInt(selObj.options[i].value), text:selObj.options[i].text});
                }
            }
        }

        $(selObj).remove();

        return reslist;
    }
    
    function _setMode(mode){

        $("#inporgress").hide();
        if(mode==3){
            $("#divres").hide();
            $("#qform").hide();
            $("#div_empty").show();
        }else{
            $("#divres").show();
            $("#qform").show();
            $("#div_empty").hide();
        }
        if(mode==1){
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
    //public members
    //
    var that = {

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            return (strClass === _className);
        },

        onRectypeChange: function(event){
            _onRectypeChange(event);
        },

        changeAggregationMode: function(){
            _changeAggregationMode();
            _autoRetrieve();
        },

        resetIntervals: function(event){
            _resetIntervals(event);
        },

        doRetrieve: function(){
            _doRetrieve();
        },

        doSave: function(){
            //_doRetrieve();
            alert('Sorry. Not implemented yet');
        },

        doCancel: function(){
            window.close(null);
        },

        setMode: function(mode){
            _setMode(mode);
        },

        doPrint: function(){
            alert('Sorry. Not implemented yet');
        },

        assignRecordset: function(recordset){

            _currentRecordset = recordset;
            //change value of rectype selector
            if(!($('#cbRectypes').val()>0) && recordset['first_rt']>0){
                $('#cbRectypes').val(recordset['first_rt']);
                $('#cbRectypes').change();
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

    _init(_database, _query, _query_domain);  // initialize before returning
    return that;

}