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
var buttonDiv;
var visualisationButton;

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
            /*var isVisible = showHideIntervals( $(this).attr('tt') );
            if(isVisible){
                $(this).removeClass('collapsed');
            }else{
                $(this).addClass('collapsed');
            }*/
            var $modal = determineModalType( $(this).attr('tt') );

            $modal.modal('show');
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

        buttonDiv = $("<div></div>");
        visualisationButton = $("<button>Visualise</button>");

        visualisationButton.appendTo(buttonDiv);
    }

    function _OnRowTypeChange(value) {
        console.log('%cvalue', 'color:seagreen', value);
        console.log('$(value)', $(value));
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
     * Determine the modal type, 
     * either row, column or page
     * return its value.
     */
    function determineModalType(name){
        var modal = $('#'+name+'IntervalsModal');

        return modal;

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
        var $modalDialogBody = $('#'+name+'IntervalsBody'); //Hosts the entire body of modal
        var $rowDiv;  //First row within the modal.
        var $leftColDiv;   //Left column of div 
        var $rightColDiv;   //Right column of div
        var $firstRowDiv;  
        var $buttons;
        var $bodyDiv;
        var $addIntervalBtn;
        var $btnDiv;
        var $intervalHeadRow;

        $modalDialogBody.empty();
        $container.empty();

        if(fields3[name].intervals.length<1){
            $container.html('There are no values for these fields in the current results set');
            $container.show();
            return;
        }

        if(fields3[name].values && fields3[name].values.length>0)
        {
            $('#'+name+'Header').text('Assign intervals for: ' + fields3[name].fieldname.toUpperCase());

            //Creates entire element in modal
            $intdiv = $(document.createElement('div'))
            .css({'padding':'0.4em'})
            .attr('intid', 'b0' )
            .addClass('container-fluid')
            .appendTo($modalDialogBody);

            $rowDiv = $(document.createElement('div'))
            .addClass('row '+name)
            .appendTo($intdiv);

            $leftColDiv = $(document.createElement('div'))
            .addClass('col-4')
            .attr('id', 'leftColDiv'+name)
            .css({'padding-right':'2rem'})
            .appendTo($rowDiv);

            $rightColDiv = $(document.createElement('div'))
            .addClass('col-8')
            .attr('id','rightColDiv'+name)
            //.css({'padding-right':'2rem'})
            .appendTo($rowDiv);

            $firstRowDiv = $(document.createElement('div'))
            .addClass('row')
            .css('margin-bottom','1rem')
            .appendTo($rightColDiv);

            $firstRowDiv.append('<div class="col-md-1"></div>')//Blank div
            $firstRowDiv.append('<div class="col-md-8"><h5>Intervals</h5></div>')

            /* Input field to show number of intervals
            $firstRowDiv
            .append('<div class="col-6 form-group"><label>Number of intervals:</label><input id="'+name+'IntCount" size="6" value="'+keepCount+'"></div>')
            */

            //$('<input id="'+name+'IntCount">').attr('size',6).val(keepCount)

            $buttons = $(document.createElement('div'))
            .addClass('col-md-3')
            .append($('<button>',{text: "Reset",class: "btn btn-secondary"})
                .click(function( event ) {
                    calculateIntervals(name, parseInt($('#'+name+'IntCount').val()) );
                }).css('margin-right',"1rem"))
            
            $buttons.appendTo($firstRowDiv);
            /*
            .append($('<button>',{text: "Reset"})
                .css('margin-left','1em')
                .css('margin-right','3em')
                .click(function( event ) {
                    calculateIntervals(name, parseInt($('#'+name+'IntCount').val()) );
            }));
            */

            /*
            $('<button>',{text: "Add"})
            //.button({icons: {primary: "ui-icon-plus"}} )
            .click(function( event ) {
                editInterval(  name, -1 );
            })
            .appendTo($rowDiv);
            */

            /* @TODO perhaps
            $intdiv.append($('<input>').attr('type','checkbox').css('margin-left','1em').click(function(event){
            fields3[name].allownulls = event.target.checked;
            })
            ).append('&nbsp;include null values');
            */

            $intervalHeadRow = $(document.createElement('div'))
            .addClass('row')
            .appendTo($rightColDiv);

            $('<div class="col-md-1">')
            .appendTo($intervalHeadRow);

            $('<div class="col-md-4">')
            .append('<h6>Label<h6>')
            .appendTo($intervalHeadRow);

            $('<div class="col-md">')
            .append('<h6>Value</h6>')
            .appendTo($intervalHeadRow);
        }

        var idx;
        var intervals = fields3[name].intervals;

        $('#'+name+'IntCount').val(intervals.length)

        for (idx=0; idx<intervals.length; idx++){

            var interval = intervals[idx];

            $intdiv = $(document.createElement('div'))
            .addClass('intervalDiv list row')
            .attr('id', name+idx )
            .appendTo($rightColDiv);

            $('<div class="col-md-1 bg-white">')
            .attr('id', name+idx+'ArrowPlacement')
            .appendTo($intdiv);

            $('<div class="col-md-4">')
            //.css({'width':'160px','display':'inline-block'})
            .html(interval.name)
            .css({'font-weight':'bold'} )
            .dblclick(function(event){
                //Collect the interval number of the clicked row
                var intervalElement = $(this).parent();
                var intervalPosition = intervalElement.attr('id').replace(name, '');

                intervalPosition = parseInt(intervalPosition);

                if(intervalPosition >= fields3[name].values.length){
                    //Create input box to change name
                    $(this).html('<input id="changeNameBox" value="'+interval.name+'">');
                    //When user clicks out of input box edit name
                    $('#changeNameBox').blur(function(){
                        var nameChanged = $('#changeNameBox').val();
                        $(this).parent().html(nameChanged);

                        fields3[name].intervals[intervalPosition].name = nameChanged;
                        _doRender();    //Apply to table
                    });
                }
            })
            .appendTo($intdiv);

            if(intervals[idx].values.length > 1){
                var splitDescription = interval.description.split("+");
                var listGroup = $('<ul class="list-group list-group-flush"></ul>');
            
                for(x=0;x<(splitDescription.length-1); x++){
                    var listItem = $('<li class="list-group-item p-0 bg-transparent">')
                    .html(splitDescription[x])
                    .appendTo(listGroup);
                }

                $('<div class="col-md">')
                .append(listGroup)
                .appendTo($intdiv);

            }
            else{
                $('<div class="col-md">')
                .html(interval.description)
                //.css({'max-width':'250px','width':'250px','display':'inline-block','padding-left':'1.5em'})
                .appendTo($intdiv);    
            }

            /*
            var editbuttons = '<div class="saved-search-edit">'+
            '<img title="edit" src="' +window.hWin.HAPI4.baseURL+'common/images/edit_pencil_9x11.gif" '+
            'onclick="{top.HEURIST.search.savedSearchEdit('+sid+');}">';
            editbuttons += '<img  title="delete" src="'+window.hWin.HAPI4.baseURL+'common/images/delete6x7.gif" '+
            'onclick="{top.HEURIST.search.savedSearchDelete('+sid+');}"></div>';
            $intdiv.append(editbuttons);
            */

            if(idx >= fields3[name].values.length){
                $bodyDiv = $('<div class="col-md-2"></div>')
                .appendTo($intdiv);

                $('<button>')
                .attr('intid', idx)
                //.button({icons: {primary: "ui-icon-pencil"}, text: false })
                .addClass('btn btn-warning border-dark')
                .append('<i class="bi bi-pencil"></i>')
                //css({'background-image': 'url('+window.hWin.HAPI4.baseURL+'common/images/edit_pencil_9x11.gif)'})
                .click(function( event ) {
                    renderIntervals(name); //Refresh intervals to remove existing arrows and create a clean display.
                    editInterval( name,  $(this).attr('intid'), false);
                })
                .appendTo($bodyDiv);
    
                $('<button>')
                //.button({icons: {primary: "ui-icon-close"}, text: false })
                .attr('intid', idx)
                .addClass('btn btn-danger border-dark')
                .append('<i class="bi bi-trash"></i>')
                //.css({'background-image': 'url('+window.hWin.HAPI4.baseURL+'common/images/delete6x7.gif)'})
                .click(function( event ) {
                    removeInterval( name, $(this).attr('intid') );
                })
                .appendTo($bodyDiv);
            }
        }

        $btnDiv = $('<div class="row my-2"></div>')
            .appendTo($rightColDiv);

        $addIntervalBtn = $('<button>',{class: "btn btn-success w-100"})
            //.button({icons: {primary: "ui-icon-plus"}} )
            .click(function( event ) {
                renderIntervals(name); //Refresh intervals to remove existing arrows and create a clean display.
                editInterval( name, -1, true);
            })
            .html('<i class="bi bi-plus"></i> Add Interval')
            .attr('id','addInterval');

        $('<div class="col-1">').appendTo($btnDiv);

        $('<div class="col-11">')
            .append($addIntervalBtn)
            .appendTo($btnDiv);
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
        _doRender(); //Render after the removal of a value.
    }

    /**
    * add/edit interval
    */
    function editInterval( name, idx, isAdd ){

        var $editedRow = $('#'+name+idx);

        if(isAdd){
            var $newInterval = $(document.createElement('div'))
            .addClass('intervalDiv list row')
            .appendTo('#rightColDiv'+name)

            $('<div class="col-md-1 bg-white">')
            .attr('id', name+idx+'ArrowPlacement')
            .appendTo($newInterval);

            $('<div class="col-md-4">')
            .append(
                $('<div>')
                .html('newInterval')
                .css({'font-weight':'bold'} ))
            .appendTo($newInterval);

            $('<div class="col-md">')
            .html('empty')
            .appendTo($newInterval);

            $('#addInterval').attr('disabled', true);
        }
        else{
            //Toggle edit background colour for the interval that is being used.
            for(e=0;e<fields3[name].intervals.length;e++){
                if(e == idx){
                    $editedRow.toggleClass("intervalDiv", false);
                    $editedRow.toggleClass("bg-warning", true);
                }
                else{
                    $('#'+name+e).toggleClass("intervalDiv", true);
                    $('#'+name+e).toggleClass("bg-warning", false);
                }
            }
        }
        
        //var $dialogbox;
        var modalEditBox = $('#leftColDiv'+name);
        var $rowDiv;

        //create multiselect list of terms
        var $dlg = $("#terms-dialog");
        
        if($dlg.length==0){
            $dlg = $('<div>')
            .attr('id','terms-dialog')
            .appendTo('body');
        }
        $dlg.empty()
        .appendTo(modalEditBox);

        var intname = (idx<0)?'new interval':fields3[name].intervals[idx].name;

        $rowDiv = $(document.createElement('div'))
        .addClass('row')
        .appendTo($dlg);

        //Heading for left col values
        $('<div id="topdiv" class="col-6">'/*Label:<input id="intname" value="'+intname+'">*/+'</div>')
        //.addClass('intervalDiv list')
        .append('<h5>Available Values</h5>')
        .appendTo($rowDiv);

        $('<div class="col-6">')
        .append('<p>Select and assign to new intervals</p>')
        .appendTo($rowDiv);

        var iHeight = 220;
        var detailtype = fields3[name].type;
        var cnt=0;

        if ( detailtype=="enum" || detailtype=="resource" || detailtype=="relationtype")
        {
            //Creates select all checkbox
            $intdiv = $(document.createElement('div'))
            .addClass('row p-1')
            .appendTo($dlg);

            $listDiv = $(document.createElement('div'))
            .addClass('col-md-1 d-flex align-items-center')
            .appendTo($intdiv);

            $('<input>')
            .attr('type','checkbox')
            .attr('checked', false)
            .attr('id','selectAll')
            .addClass('recordIcons')
            .change(function(){
                //Unchecks selectall checkbox if a value is unchecked.
                var checked = this.checked;
                //selects checkbox which are not disabled in edit mode
                $('input[name="'+name+'Options"]:not(:disabled)').each(function(){
                    this.checked = checked;
                });
            })
            .appendTo($listDiv);

            $('<div>')
            .addClass('recordTitle col-md p-1')
            .html('Select All')
            .appendTo($intdiv);

            //Creates value checkboxes
            var i, j,
            termlist = fields3[name].values; //all terms or pointers
            for(i=0; i<termlist.length; i++)
            {
                var notused = true, itself = false;
                var intvalues = fields3[name].intervals;
                /*for(j=0; j<intvalues.length; j++){
                    if(window.hWin.HEURIST4.util.findArrayIndex(termlist[i].id, intvalues[j])>=0){
                        if(idx==j){
                            itself = true;  //itself
                        }else{
                            notused = false;
                        }
                        break;
                    }
                }
                */

                //Determines which intervals have been allocated to the new interval
                if(termlist.length < intvalues.length && !isAdd){
                    for(k=0;k<intvalues[idx].values.length;k++){
                        if(termlist[i].id == intvalues[idx].values[k]){
                            itself = true;
                            break;
                        }
                    }
               }

                if(notused){

                    $intdiv = $(document.createElement('div'))
                    .addClass('intervalDiv list p-1 row')
                    .attr('intid', idx )
                    .appendTo($dlg);

                    $listDiv = $(document.createElement('div'))
                    .addClass('col-md-1 d-flex align-items-center')
                    .appendTo($intdiv);

                    $('<input>')
                    .attr('type','checkbox')
                    .attr('checked', itself)
                    .attr('disabled', itself)
                    .addClass('recordIcons')
                    .attr('termid',termlist[i].id)
                    .attr('termname',termlist[i].text)
                    .attr('name', name+'Options')
                    .change(function(){
                        //If select all is chosen and user deselects a value, select all checkbox will be unchecked.
                        if(($('input[id=selectAll]').prop('checked') == true) && ($(this).prop('checked') == false)){
                            $('input[id=selectAll]').prop('checked', false);
                        }
                        
                        if($('input[name='+name+'Options]:checked').length == fields3[name].values.length){
                            $('input[id=selectAll]').prop('checked', true);
                        }
                    })
                    //.css('margin','0.4em')
                    .appendTo($listDiv);

                    $('<div>')
                    .addClass('recordTitle col-md p-1')
                    //.css('margin-top','0.4em')
                    .html( termlist[i].text )
                    .appendTo($intdiv);

                    cnt++;

                }//notused

                //Add arrows to fields that already exist in the group
                if(itself){
                    var $removeButton = $('<button></button>')
                    .addClass('btn btn-outline-primary')
                    .attr('valueid',termlist[i].id)
                    .click(function(){
                        //Get the name of the value clicked to remove from group interval
                        var clicked = $(this).attr('valueid');

                        //Find checkbox with same valueid
                        $('input[termid='+ clicked+']')
                        .prop('checked', false)
                        .attr('disabled', false);

                        if($('input[name='+name+'Options]:checked').length != fields3[name].values.length){
                            $('#selectAll').prop('checked', false)
                            .attr('disabled',false);
                        }

                        //Remove arrow button from the selected value
                        $(this).remove();
                    });

                    $('<i class="bi bi-arrow-left"></i>')
                    .appendTo($removeButton);

                    $('#'+name+i+'ArrowPlacement')
                    .append($removeButton);
                }
            }

            if(!isAdd){
                if($('input[name='+name+'Options]:checked').length == fields3[name].values.length){
                    $('#selectAll').prop('checked', true)
                    .attr('disabled',true);
                }
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
                fields3[name].intervals[idx].name = "newInterval";

                if(detailtype=="enum" || detailtype=="resource" || detailtype=="relationtype"){ //false &&
                    var sels = $dlg.find('input[name='+name+'Options]:checked')
                    var isMulti = (sels.length > 1) ? '+' : ''
                    $.each(sels, function(i, ele){
                        fields3[name].intervals[idx].values.push( parseInt($(ele).attr('termid')) );
                        fields3[name].intervals[idx].description = fields3[name].intervals[idx].description + $(ele).attr('termname')+isMulti;
                        
                        /*if($(ele).attr('termid') != fields3[name].values[idx].id){
                            idxToDelete.push($(ele).attr('termid'));
                        }
                        */
                    });
                    //Remove interval after it has been added to another interval
                    /*for(i in fields3[name].intervals){
                        for(x = 0; x<fields3[name].intervals[i].values.length;x++){
                            if((fields3[name].intervals[i].values[x] == fields3[name].intervals[idx].values[x]) 
                                && (fields3[name].intervals[i].values[x] != fields3[name].values[idx].id)){
                                
                                    idxToDelete = i;
                                    fields3[name].intervals.splice(idxToDelete,1);
                                    break;
                            }
                        }
                    }*/
                    
                }else if(detailtype=="float" || detailtype=="integer"){

                    fields3[name].intervals[idx].values.push( parseFloat($dlg.find('#minval').val() ));
                    fields3[name].intervals[idx].values.push(  parseFloat($dlg.find('#maxval').val() ));
                    fields3[name].intervals[idx].description = $dlg.find('#minval').val()+' ~ '+$dlg.find('#maxval').val();

                }

                //Delete intervals 
                /*for(x=0;x<idxToDelete.length; x++){
                    for(a=0;a<fields3[name].intervals.length;a++){
                        if(idxToDelete[x] == fields3[name].intervals[a].values[0]){
                            fields3[name].intervals.splice(a,1);
                        }
                        
                    }
                }
                */
                renderIntervals(name);
                _doRender();

                //$dialogbox.dialog( "close" );
            }


            $dlg.find("#topdiv").append($('<button>').addClass("btn btn-outline-success").attr('id','applyButton').click(__addeditInterval));
            $dlg.find("#applyButton").append('<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-right" viewBox="0 0 16 16">' 
                + '<path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8z"/>'
                + '</svg>');
            $($dlg.find("#applyButton")).appendTo('#'+name+idx+'ArrowPlacement'); //Places arrow at the begining of the edited or newly added interval.
            /*$dialogbox = window.hWin.HEURIST4.msg.showElementAsDialog(
                    {element:$dlg.get(0), height: iHeight, width:320, title:"Edit interval", modal:true} ); */
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

        if($.fn.dataTable.isDataTable("#resultsTable")){
            $("#resultsTable").DataTable().destroy(true);
        }

        $("#pmessage").html('Rendering...');
        _setMode(1);//progress

        var pages = fields3.page.intervals;
        var plen = pages.length;

        $divres = $('#divres');
        $divres.hide();
        $divres.empty();

        if(_isPopupMode){
        $divres.append('<div style="text-align:center"><button onclick="crosstabsAnalysis.setMode(0)">Back to form</button>&nbsp;&nbsp;<button onclick="crosstabsAnalysis.doPrint()">Print</button></div>');
        }else{
            $('#btnPrint').show();
        }

        var date = new Date();
        var showZeroBlankText = "";

        $divres.append('<div></div>')
        $divres.append('<span>DB: <b>'+window.hWin.HAPI4.database+' </b></span>');
        $divres.append('<span>Date and time: '+ (date.getDate() + "/" + (date.getMonth()+1) + "/" + date.getFullYear())+' </span>');
        //$divres.append('<div>Type of analysis: Crosstab</div>');
        //$divres.append('<div>Title (name) of saved analysis: '+ +'</div>');
        //????? $divres.append('<div>Record type analysed: '++'</div>');
        if(_currentRecordset!=null){
            $divres.append('<span>N = '+ _currentRecordset['recordCount'] +' </span>');
            $divres.append('<span>Query string: '+_currentRecordset['query_main'] +' </span>');

        }else{
            $divres.append('<span>Query string: q='+query_main+'&w='+query_domain +' </span>');
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



        $divres.append('<span>Type of value displayed: <b>'+aggregationMode+'</b></span>');

        $divres.append('<div></div>');
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

        //$divres.find('td').css( {'padding':'4px', 'border':'1px dotted gray'} );//{'border':'1px dotted gray'}); //1px solid gray'});
        /*$divres.find('.crosstab-header0').css({
            'border-top':'1px solid black',
        });
        $divres.find('th').css({
            'border-right':'1px solid black'
        });
        */
        
        buttonDiv.appendTo($divres);

        //console.log($.fn.dataTable.isDataTable("table#resultsTable"));

        $(document).ready(function(){
            $(".resultsTable").DataTable({
                "paging" : false,
                "info" : false,
                dom:"Bfrtip",
                buttons:[
                    'csv','pdf','print'
                ]
            }
            );
        });
        
        //console.log($.fn.dataTable.isDataTable("table#resultsTable"));

        _setMode(2);//results
    }//_doRenders

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
        var rowspan = 1;

        if((showPercentageRow || showPercentageColumn) && clen>1) rowspan++;
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


        var currow_val=-1
        var row_interval_idx=[]; //If a value contains more than one value type of the same variable it stores in this array, assisting output.

        //create output array, calculate totals
        for (idx in records){
            var count = 0;
            if(idx){

                if(currow_val!=records[idx][0]){
                    var rval = records[idx][0]; //row
                    currow_val = rval;
                    //find row interval it fits
                    row_interval_idx = [-1];
                    for (i in rows){
                        if( fitToInterval( fields3.row.type, rows[i].values, rval ) ){
                            //Some records may contain more than one value, this stores it in an array and generates based on this.
                            if(count<1){
                                row_interval_idx[0]=i;
                            }
                            else{
                                row_interval_idx[count]=i;
                            }
                            count++;
                        }
                    }
                }

                if(row_interval_idx[0]>=0)
                {
                    if(noColumns){ //no columns
                        var val = parseFloat(records[idx][2]);   //WARNING - fix for AVG
                        //Iterate through each row_interval_idx to add the output of values that contain more than one.
                        for(i=0;i<row_interval_idx.length; i++){
                            if(!isNaN(val) && rnd(val)!=0){
                                rows[row_interval_idx[i]].output[0] = rows[row_interval_idx[i]].output[0] + rnd(val);
                                rows[row_interval_idx[i]].avgcount[0] ++;
                                grantotal = grantotal + val;
                                rows[row_interval_idx[i]].isempty = false;
                            }
                        }
                        

                    }else{

                        for (j=0; j<clen; j++){
                            if( fitToInterval( fields3.column.type, columns[j].values, records[idx][1] ) ){
                                var val = parseFloat(records[idx][2]);   //WARNING - fix for AVG
                                //Iterate through each row_interval_idx to add the output of values that contain more than one.
                                for(k=0;k<row_interval_idx.length; k++){
                                    if(!isNaN(val) && rnd(val)!=0){
                                        rows[row_interval_idx[k]].output[j] = rows[row_interval_idx[k]].output[j] + rnd(val);
                                        rows[row_interval_idx[k]].avgcount[j] ++;
                                        rows[row_interval_idx[k]].total = rows[row_interval_idx[k]].total + val;
                                        rows[row_interval_idx[k]].isempty = false;
    
                                        columns[j].isempty = false;
                                        columns[j].total = columns[j].total + val;
                                    }
                                }
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
        $table.attr("id", "resultsTable");
        $table.attr("class", "display cell-border resultsTable");
        var $rowPercentageHeader;
        var styleTypeHeader = "crosstab-header0";

        //Must have a table header for DataTables to work correctly.
        var $row = $('<thead>').appendTo($table);
        
        if(!noColumns){
            styleTypeHeader = "crosstab-header1";
            var rowHeader1 = $('<tr>');
            for (j=0; j<clen; j++){
                if(supressBlankColumn && columns[j].isempty) continue;
                notemtycolumns++;
            }
            rowHeader1.append('<th>&nbsp;</th><th class="crosstab-header0" style="text-align:left; border-left:1px solid black;" colspan="'+notemtycolumns*colspan+(showTotalsColumn?1:0)+'">'+fields3.column.fieldname+'</th>');
            $row.append(rowHeader1);
        }

        var $rowHeader = $('<tr>');
        $rowHeader.append('<th class="'+styleTypeHeader+'" style="border-left:1px solid black" rowspan="'+rowspan+'">'+fields3.row.fieldname+'</th>');
    
        //$row.append('<th class="crosstab-header0">'+fields3.row.fieldname+'</th>');

        // render HEADER, reset column totals
        if(noColumns){ //If only Variable 1 is chosen
            $rowHeader.append('<th class="crosstab-header0">'+aggregationMode+'</th>');

            //If 'Column %' checkbox is ticked.
            if(showPercentageColumn){
                $rowHeader.append('<th class="percent" style="border-top: 1px solid black;">%</th>')
            }
        }else{ //If two variables are chosen
            //Appends column names of the second variable.
            for (j=0; j<clen; j++){
                if(supressBlankColumn && columns[j].isempty) continue;
                $rowHeader.append('<th class="crosstab-header" style="{width:'+colspan*4+'em;max-width:'+colspan*4+'em}" colspan="'+colspan+'">'+columns[j].name+'</th>');
                //notemtycolumns++;
            }
            if(showTotalsRow){ //special column for totals
                $rowHeader.append('<th class="'+styleTypeHeader+'" style="{text-align:center;}" colspan="'+colspan+'">totals</th>');  //(showPercentageRow?2:1)  ART2
            }else if(showTotalsColumn){
                $rowHeader.append('<th class="'+styleTypeHeader+'" style="{text-align:center;}">totals</th>');
            }

            //If 'Row %' checkbox has been chosen only.
            if(showPercentageRow && !showPercentageColumn){
                $rowPercentageHeader = $('<tr>');
                for(t=0; t<clen;t++){
                    if(supressBlankColumn && columns[t].isempty) continue;
                    $rowPercentageHeader.append('<th class="crosstab-header">'+aggregationMode+'</th><th class="percent">Row%</th>');
                }
                if(showTotalsRow){
                    $rowPercentageHeader.append('<th>&nbsp;</th>');
                    $rowPercentageHeader.append('<th class="percent">%</th>');
                }
            }
            else if(!showPercentageRow && showPercentageColumn){ //If 'Column %' checkbox has been chosen only.
                $rowPercentageHeader = $('<tr>');
                for(t=0; t<clen;t++){
                    if(supressBlankColumn && columns[t].isempty) continue;
                    $rowPercentageHeader.append('<th class="crosstab-header">'+aggregationMode+'</th><th class="percent">Col%</th>');
                }
                if(showTotalsRow){
                    $rowPercentageHeader.append('<th class="crosstab-header">&nbsp;</th>');
                    $rowPercentageHeader.append('<th class="percent">%</th>');  
                }
            }
            else if(showPercentageRow && showPercentageColumn){ //If both 'Column %' and 'Row %' have been chosen.
                $rowPercentageHeader = $('<tr>');
                for (j=0; j<clen; j++){
                    if(supressBlankColumn && columns[j].isempty) continue;
                    $rowPercentageHeader.append('<th class="crosstab-header">&nbsp;</th><th class="percent">Row%</th><th class="percent">Col%</th>');
                }
                if(showTotalsRow || showTotalsColumn){
                    $rowPercentageHeader.append('<th class="crosstab-header">&nbsp;</th><th class="percent">Row%</th><th class="percent">Col%</th>');  //(showTotalsRow && showPercentageRow?2:1)   ART2
                }
            }
        }

        $row.append($rowHeader);
        
        if(!noColumns){
            if((showPercentageRow && showPercentageColumn) || (showPercentageRow && !showPercentageColumn) || (!showPercentageRow && showPercentageColumn)){
                $row.append($rowPercentageHeader);
            }
        }


        //Render row body
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

                    if(showPercentageColumn){
                        $row.append('<td colspan="'+colspan+'">&nbsp;</td>'); //Add extra data for DataTables to work correctly
                    }
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
                        if(showPercentageRow || showPercentageColumn){
                            for(k=0; k<colspan; k++){
                                $row.append('<td colspan="'+1+'">&nbsp;</td>');
                            }
                        }
                        else{
                            $row.append('<td colspan="'+1+'">&nbsp;</td>');
                        }
                    }
                }

                if(showTotalsRow){ //special column for totals
                    if(rows[i].total!=0 || !supressZero){
                        s = '<td class="crosstab-value">'+rnd(rows[i].total) +'</td>';
                        if(showPercentageRow){
                            s = s+'<td class="percent">'+rows[i].percent +'%</td>'
                        }
                        if(showPercentageColumn){
                            s = s+'<td class="percent">100%</td>'
                        }
                        $row.append(s);
                    }else{
                        for(n=0;n<colspan; n++){
                            $row.append('<td>&nbsp;</td>'); //(showPercentageRow?2:1) ART2
                        }
                    }
                }else if(showTotalsColumn){
                    $row.append('<td>&nbsp;</td>');
                }
            }
        }

        // LAST ROW - totals (Footer)
        if(noColumns){

            if(showTotalsColumn && grantotal!=0){
                $row = $('<tfoot>').appendTo($table);

                var $rowFooter = $('<tr>');
                $rowFooter.append('<td class="crosstab-header0" style="border-left:1px solid black; border-bottom: 1px solid black;">totals</td>');
                $rowFooter.append('<td class="crosstab-total">'+rnd(grantotal) +'</td>');

                if(showPercentageColumn){
                    $rowFooter.append('<td class="total-percent">100%</td>');
                }

                $row.append($rowFooter);
            }

        }else{

            if(showTotalsColumn){ //columns totals - last row in table
                $row = $('<tfoot>').appendTo($table);

                var $rowFooter1 = $('<tr>');
                $rowFooter1.append('<td class="crosstab-header0" style="border-left:1px solid black; border-bottom: 1px solid black;">Totals</td>');

                for (j=0; j<clen; j++){
                    if(supressBlankColumn && columns[j].isempty) continue;

                    if(columns[j].total!=0 || !supressZero){
                        s = '<td class="crosstab-total">'+rnd(columns[j].total) +'</td>';

                        if(showPercentageRow){
                            s = s+'<td class="total-percent">'+(showPercentageColumn?'100%':'&nbsp;')+'</td>'
                        }
                        if(showPercentageColumn){
                            s = s+'<td class="total-percent">'+  columns[j].percent +'%</td>'
                        }

                        $rowFooter1.append(s);
                    }else{
                        for(l=0;l<colspan;l++){
                            $rowFooter1.append('<td class="total-percent">&nbsp;</td>');
                        }
                    }
                }

                $rowFooter1.append('<td class="crosstab-total">'+rnd(grantotal)+'</td>');
                if(showPercentageRow && showPercentageColumn){
                    $rowFooter1.append('<td class="total-percent">&nbsp;</td><td class="total-percent">&nbsp;</td>');
                }
                else if(showPercentageRow || showPercentageColumn){
                    $rowFooter1.append('<td class="total-percent" colspan="'+1+'">&nbsp;</td>');  //(showPercentageRow?2:1)
                }
            }else if(showTotalsRow){//??????
                $row = $('<tr>').appendTo($table);
                $row.append('<td colspan="'+(notemtycolumns*colspan+1)+'">&nbsp;</td>');
                $row.append('<td class="crosstab-total" colspan="'+colspan+'">'+grantotal+'</td>');  //(showPercentageRow?2:1) ART2
            }

            $row.append($rowFooter1);

        }

        if(hasValues){ //grantotal!=0){
            $divres.append('<h2 class="crosstab-page">'+pageName+'</h2>');
            $table.appendTo($divres);
            
            $divres.append('<div></div>');

            //$("#modalButton").attr("disabled", false);


        }else if (!supressBlankPage) {
            $divres.append('<h2 class="crosstab-page">'+pageName+'</h2>');
            $divres.append("<div>empty set</div>");
            
            //$("#modalButton").attr("disabled", false);
        
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
            return (window.hWin.HEURIST4.util.findArrayIndex(val,values)>=0); // values.indexOf(val)
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
            //$('#aggSum').css('display','block');
           //$('#aggAvg').css('display','block');
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

    //Export function for table.
    function _exportTable(buttons){
        
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

        OnRowTypeChange: (value) => {
            _OnRowTypeChange(value)
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

    //On click of View Analysis button, modal appears.
    /*$("#modalButton").click(function(){
        window.hWin.HEURIST4.msg.showElementAsDialog(
            {element:$divres.get(0), height: 600, width:1000, title:"Results", modal:true} );
    });
    */

    visualisationButton.click(function(){
        window.hWin.HEURIST4.msg.showMsgDlg('Button feature has not been implemented.');
    });
    return that;

}
