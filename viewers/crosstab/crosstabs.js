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

/*
* Global variables
*/
var crosstabsAnalysis;
var intervalsNumeric;
var intervalsValues;
var minMax = [];
var originalOutliers = [];

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
        //Make width run to 100% of col
        $('#cbRectypes-button').css('width', '100%');
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

      //Reconfigure save buttons
      $('.inpt_save_setting_name').addClass('rounded-0')
      $('span.btn-rename').addClass('bg-warning saveEditButton');
      $('span.btn-remove').css('margin', '2px');
      $('span.btn-remove').addClass('bg-danger saveRemoveButton');
      $('.btn-remove span:first-child').removeClass('ui-icon-delete');
      $('.btn-remove span:first-child').addClass('ui-icon-trash saveIconRemoveButton ');
      $('.btn-rename span:first-child').addClass('saveIconEditButton');
      $('.btnSaveSettings').html('<i class="ui-icon ui-icon-save"></i> Save')

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

        var allowedlist = ["enum", "integer", "float", "resource", "relationtype", "date"];//, "date", "freetext"]; //"resource",

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
            createErrorMessage($('#errorContainerRecChange'), _selectedRtyID>0?'No suitable fields: numeric, pointer or enumeration types.':'Select record type.');
            $('#errorContainerRecChange').removeClass('d-none')
            //$("#nofields").html(_selectedRtyID>0?'No suitable fields: numeric, pointer or enumeration types.':'Select record type.');
            //$("#nofields").show();
        }else{
            $("#vars").show();
            $("#shows").show();
            $('#errorContainerRecChange').addClass('d-none');
            //$("#nofields").hide();
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
        var $headerModal = $('#'+name+'Header');
        var $container = $('#'+name+'IntervalsBody');
        var $tooltip = $('#'+name+'Tooltip')
        var $editButton = $('#'+name+'Vars').find('button');
        var $errorContainer = $('#errorContainer');

        $headerModal.empty();
        $container.empty();
        $errorContainer.addClass('d-none');
        $editButton.prop('disabled', true);
        $tooltip.attr('title','Select field to set intervals');

        $('#rowVars,#columnVars,#pageVars').each(function(i, ele){
            if($(ele).attr('style')){
                $(ele).removeAttr('style');
            }
        });

        fields3[name] = {field:0, type:'', values:[], intervals:[]};

        return $container;
    }

    //
    //Apply intervals from saved file
    //Replace intervals with saved intervals
    //Only used when settings have been saved.
    //
    function _applySavedIntervals(allFields, counts, name){
        fields3['column'] = allFields['column'];
        fields3['row'] = allFields['row'];
        fields3['page'] = allFields['page'];

        for(d=0;d<3;d++){
            if(d==0) name = 'column'; 
            if(d==1) name = 'row';
            if(d==2) name = 'page';

            $('#cb'+name[0].toUpperCase()+name.slice(1)+'s').val(fields3[name].field);

            if(fields3[name].type=="enum" || fields3[name].type=="resource" || fields3[name].type=="relationtype"){
                if(fields3[name].intervals.length > 0){
                    renderIntervals(name, false);
                    for(j=0;j<fields3[name].intervals.length;j++){
                        __addeditInterval( name, j, false);
                    }
                }
            }
            else if(fields3[name].type=="float" || fields3[name].type=="integer"){
                if(fields3[name].intervals.length > 0){
                    minMax[0] = fields3[name].values[0];
                    minMax[1] = fields3[name].values[1];
                    keepCount = counts;
                    renderIntervals(name);
                }
            }
        
        }

        _doRetrieve();
        $('#bottomContainer').removeClass('d-none');

    }

    //
    //Redundant function when settings are saved.
    //
    function _resetAllIntervals(fields, name, notSaved){

        suppressRetrieve = true;

        if(!name) name = 'column';

        var detailid = fields[name];
        $('#cb'+name[0].toUpperCase()+name.slice(1)+'s').val(detailid);

        _resetIntervals_continue(name, detailid, false, function(){
            if(name == 'column')
                name = 'row'
            else if(name == 'row')
                name = 'page'
            else {
                suppressRetrieve = false;
                _autoRetrieve();
                return;
            }
            _resetAllIntervals(fields, name, false);
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

        _resetIntervals_continue(name, detailid, true);
    }

    //
    //
    //
    function _resetIntervals_continue(name, detailid, notSaved, callback){

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
            calculateIntervals(name, null,true);

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
                            minMax[0] = fields3[name].values[0];    //Store min value in seperate array to save the copy as to use in the rendering.
                            minMax[1] = fields3[name].values[1];    //Store min value in seperate array to save the copy as to use in the rendering.
                            calculateIntervals(name, null, true);
                            $('#bottomContainer').removeClass('d-none');
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
                            calculateIntervals(name, null, true);
                            $('#bottomContainer').removeClass('d-none');
                        }

                        if($.isFunction(callback)) callback.call();

                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });

            return;

        }else if(detailtype=="date"){
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
                            minMax[0] = fields3[name].values[0];    //Store min value in seperate array to save the copy as to use in the rendering.
                            minMax[1] = fields3[name].values[1];    //Store min value in seperate array to save the copy as to use in the rendering.
                            calculateIntervals(name, null, true);
                            $('#bottomContainer').removeClass('d-none');
                        }

                        if($.isFunction(callback)) callback.call();

                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });

            return;

        }else if(detailtype=="freetext"){
            //alphabetically, or if distinct values less that 50 like terms

        }

        if($.isFunction(callback)) callback.call();
        $('#bottomContainer').removeClass('d-none');    //Show table results
        if(notSaved) renderIntervals(name, true);  //DisplayPopup
    }

    /**
    * create intervals
    */
    function calculateIntervals(name, count, notSaved)
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


        }else if(fields3[name].type=="date" ){
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
            if(fields3[name].type=="date"){
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
        }

        if(notSaved) renderIntervals(name, true);

        if(suppressRetrieve) return;

        _autoRetrieve();
    }

    /**
    * render intervals (create divs)
    */
    function renderIntervals(name, notSaved){

        //var $container = $('#'+name+'Intervals');
        var $modalDialogBody = $('#'+name+'IntervalsBody'); //Hosts the entire body of modal
        
        var detailtype = fields3[name].type;

        $modalDialogBody.empty();
        //$container.empty();

        if(fields3[name].intervals.length<1){
            var errorMessage = 'There are no values for these fields in the current results set.';
            createErrorMessage($('#errorContainer'), errorMessage);
            $('#errorContainer').removeClass('d-none');

            //Highlight area (animation)
            $('#'+name+'Vars').animate(
                {backgroundColor : "#FFF3cd"}, 500
            );

            //Disable Button.
            $('#'+name+'Vars').find('button').prop('disabled', true);

            /*
            $container.html('There are no values for these fields in the current results set');
            $container.show();
            */
            return;
        }

        if(fields3[name].values && fields3[name].values.length>0)
        {
            var totalVars = 0;  //Holds number of fields that have no style
            //Remove previous error animation style
            $('#'+name+'Vars').removeAttr('style');

            //Check to see if all fields have no style and remove error message.
            $('#rowVars,#columnVars,#pageVars').each(function(i, ele){
                if($(ele).attr('style') == null){
                    totalVars++;
                }
                
                if(totalVars == 3){
                    $('#errorContainer').addClass('d-none');
                }
            });

            //Re-enable buton.
            $('#'+name+'Vars').find('button').prop('disabled', false);
            //Disable tooltip
            $('#'+name+'Tooltip').attr('title', 'Edit the values in this interval.');

            if(detailtype=="enum" || detailtype=="resource" || detailtype=="relationtype") {

                var $rowDiv;        //First row within the modal.
                var $leftColDiv;    //Left column of div 
                var $rightColDiv;   //Right column of div
                var $firstRowDiv;  
                var $buttons;
                var $bodyDiv;
                var $addIntervalBtn;
                var $btnDiv;
                var $intervalHeadRow;
                var $listDiv;

                $('#'+name+'Dialog').addClass('modal-xl');
                $('#'+name+'Dialog').addClass('modal-dialog-width');

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
                .addClass('col-md col-sm-12 card me-md-2 mb-sm-2 mb-2 bg-light')
                .attr('id', 'leftColDiv'+name)
                .css({'padding-right':'2rem'})
                .appendTo($rowDiv);

                $rightColDiv = $(document.createElement('div'))
                .addClass('col-md-8 col-sm-12 card mb-sm-2 mb-2 bg-light')
                .attr('id','rightColDiv'+name)
                //.css({'padding-right':'2rem'})
                .appendTo($rowDiv);

                $firstRowDiv = $(document.createElement('div'))
                .addClass('row')
                .css('margin-bottom','1rem')
                .appendTo($rightColDiv);

                $firstRowDiv.append('<div class="col pt-2"><h5>Intervals</h5></div>')

                /* Input field to show number of intervals
                $firstRowDiv
                .append('<div class="col-6 form-group"><label>Number of intervals:</label><input id="'+name+'IntCount" size="6" value="'+keepCount+'"></div>')
                */

                //$('<input id="'+name+'IntCount">').attr('size',6).val(keepCount)

                $buttons = $(document.createElement('div'))
                .addClass('col-3 p-2')
                .append($('<button>',{html: "<i class='ui-icon ui-icon-refresh'></i> Reset",class: "btn btn-secondary"})
                    .click(function( event ) {
                        calculateIntervals(name, parseInt($('#'+name+'IntCount').val()), true );
                    }).css('margin-right',"1rem"));
                
                $buttons.appendTo($firstRowDiv);

                $intervalHeadRow = $(document.createElement('div'))
                .addClass('row')
                .appendTo($rightColDiv);

                $('<div class="col-1 p-1">')
                .append($('<button>').addClass('btn btn-primary w-100 p-1')
                .attr('id','deselectAll')
                .attr('data-bs-toggle', 'tooltips')
                .attr('data-bs-placement', 'top')
                .attr('title', 'Deselect all values')
                .append('<i class="ui-icon ui-icon-arrow-l w-100"></i>')
                .click(function(){
                    //Remove all fields from array
                    fields3[name].intervals = [];

                    //Clear UI
                    $('#rightColDiv'+name+' > .intervalDiv').each(function(index, element){
                        $(element).remove();
                    });

                    //Uncheck checkboxes
                    $('input[name="'+name+'Options"]').each(function(index, element){
                        $(element).prop('checked', false);
                        $(element).prop('disabled', false);

                    });

                    //Uncheck select all button
                    $('#selectAll'+name).prop('checked', false);
                    $('#selectAll'+name).prop('disabled', false);

                    //Hide deselect all button.
                    $('#deselectAll').addClass('d-none');
                }))
                .appendTo($intervalHeadRow);

                $('<div class="col-4">')
                .append('<h6>Label<h6>')
                .appendTo($intervalHeadRow);

                $('<div class="col">')
                .append('<h6>Value</h6>')
                .appendTo($intervalHeadRow);

                
                var idx;
                var intervals = fields3[name].intervals;

                if(notSaved){
                    $('#'+name+'IntCount').val(intervals.length)

                    for (idx=0; idx<intervals.length; idx++){

                        var interval = intervals[idx];

                        $intdiv = $(document.createElement('div'))
                        .addClass('intervalDiv list row pe-1 bg-light')
                        .attr('id', name+idx )
                        .appendTo($rightColDiv);

                        $('<div class="col-1 p-1">')
                        .attr('id', name+idx+'ArrowPlacement')
                        .appendTo($intdiv);

                        $('<div class="col-4 p-1 border-2 border-top border-secondary pointer">')
                        //.css({'width':'160px','display':'inline-block'})
                        .html(interval.name)
                        .css({'font-weight':'bold'} )
                        .dblclick(function(event){
                            //Collect the interval number of the clicked row
                            var intervalElement = $(this).parent();
                            var intervalPosition = intervalElement.attr('id').replace(name, '');

                            intervalPosition = parseInt(intervalPosition);

                            //Create input box to change name
                            $(this).html('<input class="w-100" id="changeNameBox" value="'+fields3[name].intervals[intervalPosition].name+'">');
                            //When user clicks out of input box edit name
                            $('#changeNameBox').blur(function(){
                                var nameChanged = $('#changeNameBox').val();
                                $(this).parent().html(nameChanged);

                                fields3[name].intervals[intervalPosition].name = nameChanged;
                                _doRender();    //Apply to table
                            });
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
                            $('<div class="col p-1 border-2 border-top border-secondary">')
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
                    }
                }

                $btnDiv = $('<div class="row my-2"></div>').attr('id', 'addIntervalDiv'+name)
                    .appendTo($rightColDiv);

                $addIntervalBtn = $('<button>',{class: "btn btn-success w-100"})
                    //.button({icons: {primary: "ui-icon-plus"}} )
                    .click(function( event ) {
                        editInterval( name, -1);
                    })
                    .html('<i class="ui-icon ui-icon-plusthick"></i> Add Interval')
                    .attr('id','addInterval');

                $('<div class="col-1">').appendTo($btnDiv);

                $('<div class="col-11">')
                    .append($addIntervalBtn)
                    .appendTo($btnDiv);

                //render checkboxes
                $firstRowDiv = $(document.createElement('div'))
                .addClass('row')
                .appendTo($leftColDiv);

                //Heading for left col values
                $('<div id="topdiv" class="col-12 p-2"></div>')
                .append('<h5>Available Values</h5>')
                .appendTo($firstRowDiv);

                $('<div class="col-12">')
                .append('<p>Select and assign to new intervals</p>')
                .appendTo($firstRowDiv);

                //Create body of checkboxes
                if ( detailtype=="enum" || detailtype=="resource" || detailtype=="relationtype")
                {
                    //Creates select all checkbox
                    $intdiv = $(document.createElement('div'))
                    .addClass('row p-1')
                    .appendTo($leftColDiv);

                    $listDiv = $(document.createElement('div'))
                    .addClass('col-1 d-flex align-items-center')
                    .appendTo($intdiv);

                    $('<input>')
                    .attr('type','checkbox')
                    .attr('checked', false)
                    .attr('id','selectAll'+name)
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
                    .addClass('recordTitle col p-1')
                    .html('Select All')
                    .appendTo($intdiv);

                    //Creates value checkboxes
                    var i, j,
                    termlist = fields3[name].values; //all terms or pointers
                    for(i=0; i<termlist.length; i++)
                    {
                        var notused = true, isAllocated = false;
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

                        //Check to see if the fields have already been allocated
                        for(j=0;j<intervals.length;j++){
                            if(intervals[j].values.length > 1){
                                for(k=0;k<intervals[j].values.length;k++){
                                    if(termlist[i].id == intervals[j].values[k]){
                                        isAllocated = true;
                                        break;
                                    }
                                }
                            }
                            else if(termlist[i].id == intervals[j].values[0]){
                                isAllocated = true;
                            }
                        }

                        if(notused){

                            $intdiv = $(document.createElement('div'))
                            .addClass('intervalDiv list p-1 row')
                            .attr('intid', i )
                            .appendTo($leftColDiv);

                            $listDiv = $(document.createElement('div'))
                            .addClass('col-1 d-flex align-items-center')
                            .appendTo($intdiv);

                            $('<input>')
                            .attr('type','checkbox')
                            .attr('checked', isAllocated)
                            .attr('disabled', isAllocated)
                            .addClass('recordIcons')
                            .attr('termid',termlist[i].id)
                            .attr('termname',termlist[i].text)
                            .attr('name', name+'Options')
                            .change(function(){
                                //If select all is chosen and user deselects a value, select all checkbox will be unchecked.
                                if(($('input[id=selectAll'+name+']').prop('checked') == true) && ($(this).prop('checked') == false)){
                                    $('input[id=selectAll'+name+']').prop('checked', false);
                                }
                                
                                if($('input[name='+name+'Options]:checked').length == fields3[name].values.length){
                                    $('input[id=selectAll'+name+']').prop('checked', true);
                                }
                            })
                            .appendTo($listDiv);

                            $('<div>')
                            .addClass('recordTitle col p-1')
                            //.css('margin-top','0.4em')
                            .html( termlist[i].text )
                            .appendTo($intdiv);

                        }

                        //Add arrows to fields that are already allocated (original values)
                        if(isAllocated){
                            var $removeButton = $('<button></button>')
                            .addClass('btn btn-outline-primary w-100 p-1')
                            .attr('valueid',termlist[i].id)
                            .click(function(){
                                //Get the name of the value clicked to remove from group interval
                                var clicked = $(this).attr('valueid');

                                //Find checkbox with same valueid
                                $('input[termid='+ clicked+']')
                                .prop('checked', false)
                                .attr('disabled', false);

                                if($('input[name='+name+'Options]:checked').length != fields3[name].values.length){
                                    $('#selectAll'+name).prop('checked', false)
                                    .attr('disabled',false);
                                }

                                //Remove div containing field.
                                $(this).parents('div.list').remove();
                                //Remove interval from rendered field.
                                var index = findPositionInArray(name, parseInt(clicked))
                                removeInterval(name, index,-1);
                            });

                            $('<i class="ui-icon ui-icon-arrow-l w-100"></i>')
                            .appendTo($removeButton);

                            $('#'+name+i+'ArrowPlacement')
                            .append($removeButton);
                        }
                    }

                    if($('input[name='+name+'Options]:checked').length == fields3[name].values.length){
                        $('#selectAll'+name).prop('checked', true)
                        .attr('disabled',true);
                    }
                }
            }
            else if(detailtype=="float" || detailtype=="integer" || detailtype=="date"){
                var $entireDiv;
                var $resetRow;
                var $resetRowBody;
                var $roundingDiv;
                var $roundingRowBody;
                var $intervalsDiv;
                var $intervalColumn;
                var selectBox;
                
                var decimalPlaces = [0,1,2,3];

                //Change size of modal to accommodate and remove white space. These fields are not large.
                $('#'+name+'Dialog').removeClass('modal-xl');
                $('#'+name+'Dialog').removeClass('modal-dialog-width');

                $('#'+name+'Header').text('Assign intervals for: ' + fields3[name].fieldname.toUpperCase()
                    + ' (Range: '+fields3[name].values[0]+' - ' +fields3[name].values[1]+')');

                //Creates entire element in modal
                $intdiv = $(document.createElement('div'))
                .css({'padding':'0.4em'})
                .attr('intid', 'b0' )
                .addClass('container-fluid')
                .appendTo($modalDialogBody);

                $entireDiv = $(document.createElement('div'))
                .addClass('row '+name)
                .appendTo($intdiv);

                //Create the reset row.
                $resetRow = $(document.createElement('div'))
                .addClass('col-12 card mb-2 pt-2 bg-light')
                .appendTo($entireDiv);
                
                $resetRowBody = '<div class="row">'+
                    '<div class="col-12 mb-2"><div class="row"><div class="col-sm-3 col-xs-12"><label>Reset Intervals:</label></div><div class="col-sm col-xs-12"><input id="'+name+'IntCount" size="6" value="'+keepCount+'"></input></div></div></div>'+
                    '<div class="col-12 mb-2"><div class="row"><div class="col-sm-3 col-xs-12"><label>Range (from):</label></div><div class="col-sm col-xs-12"><input id="minOutlier'+name+'" size="6" value="'+minMax[0]+'"></input></div></div></div>'+
                    '<div class="col-12 mb-2"><div class="row"><div class="col-sm-3 col-xs-12"><label>Range (to):</label></div><div class="col-sm col-xs-12"><input id="maxOutlier'+name+'" size="6" value="'+minMax[1]+'"></input></div></div></div>' +
                    '<div class="col-12 mb-2"><button class="btn btn-success w-100" id="numericApply"><i class="ui-icon ui-icon-circle-check"></i>Apply</button></div>' +
                    '</div>';

                $($resetRowBody).appendTo($resetRow);
                
                //Add click function to apply button.
                $($resetRow).find('#numericApply').click(function(event){
                    var isMinWithin = (parseInt($('#minOutlier'+name).val()) >= fields3[name].values[0] && parseInt($('#minOutlier'+name).val()) <= fields3[name].values[1]) ? true : false;    //If min within range.
                    var isMaxWithin = (parseInt($('#maxOutlier'+name).val()) <= fields3[name].values[1] && parseInt($('#maxOutlier'+name).val()) >= fields3[name].values[0]) ? true : false;    //If man within range.
                    var isMaxGreater = (parseInt($('#maxOutlier'+name).val()) < parseInt($('#minOutlier'+name).val())) ? true : false;
                    var isMinGreater = (parseInt($('#minOutlier'+name).val()) > parseInt($('#maxOutlier'+name).val())) ? true : false;

                    if(isMinWithin && isMaxWithin && !isMaxGreater && !isMinGreater){
                        minMax[0] = $('#minOutlier'+name).val();
                        minMax[1] = $('#maxOutlier'+name).val();
                        calculateIntervals(name, parseInt($('#'+name+'IntCount').val()), true );
                    }
                    else{
                        var errorMessage = "When entering the range of intervals to apply to a dataset, the from and to values must be between the range above."
                        + " The from value must also not exceed the to value and vice versa.";

                        createErrorMessage($('#'+name+'IntervalsBody'), errorMessage);
                        /*
                        setTimeout(function(){
                            $('#numberAlert').fadeOut(500, function(){
                                $('#numberAlert').remove();
                            })
                        }, 3000)
                        */
                    }     
                });

                if(detailtype=="float" || detailtype=="integer" ){
                    //Create rounding row
                    $roundingDiv = $(document.createElement('div'))
                    .addClass('col-12 card mb-2 pt-2 bg-light')
                    .appendTo($entireDiv);

                    $roundingRowBody = '<div class="row">' +
                    '<div class="col-12 mb-2"><div class="row"><div class="col-sm-3 col-xs-12"><label>Rounding:</label></div><div class="col-sm col-xs-12"><select id="roundingSelect"><span>decimal place</span></div></div></div>' +
                    '</div>'

                    $($roundingRowBody).appendTo($roundingDiv);

                    //Append rounding numbers in select box
                    for(j=0;j<decimalPlaces.length;j++){
                        selectBox = $roundingDiv.find('#roundingSelect');

                        //Make 1 decimal place default.
                        if(j == 1){
                            selectBox.append('<option value="'+decimalPlaces[j]+'" selected>'+decimalPlaces[j]+'</option>');
                            continue;
                        }
                        selectBox.append('<option value="'+decimalPlaces[j]+'">'+decimalPlaces[j]+'</option>');
                    }

                    selectBox.change(function(){
                        changeIntervalDecimal(name,$(this).val());
                        var changedIntervals = fields3[name].intervals
                        generateNumericIntervalsRows(name, changedIntervals, $intervalColumn, $(this).val())
                    });
                }

                //Create intervals
                $intervalsDiv = $(document.createElement('div'))
                .addClass('col-12 card mb-2 pt-2 bg-light')
                .appendTo($entireDiv);

                $('<div class="row">').append($('<div class="col-12">').append('<h5>Intervals:</h5>')).appendTo($intervalsDiv);

                $intervalColumn = $(document.createElement('div'))
                .addClass('col-12 mb-2')
                .appendTo($intervalsDiv);

                //Create number rows of intervals
                var idx;
                //Create deep copy of object
                intervalsNumeric = $.extend(true,{},fields3[name].intervals);
                
                if(detailtype=="float" || detailtype=="integer"){
                    changeIntervalDecimal(name, 1);
                    generateNumericIntervalsRows(name, fields3[name].intervals, $intervalColumn, 1);
                }
                else if(detailtype=="date"){
                    generateNumericIntervalsRows(name, fields3[name].intervals, $intervalColumn, 0);
                }
            }
        }
        
    }

    /*
    * Create the rows for the numeric detail types.
    */
    function generateNumericIntervalsRows(name, int, htmlElement, decimalPlace){
        //Update description before rendering.
        updateDescriptionName(name, int, decimalPlace);
        htmlElement.empty();

        for(i=0;i<int.length;i++){

            var isOutlierMin = ((int[i].values[0] < Number($('#minOutlier'+name).val())) && ((int[i].values[1] < Number($('#minOutlier'+name).val())) || !(int[i].values[1] < Number($('#minOutlier').val())))) ? true : false;
            var isOutlierMax = ((int[i].values[1] > Number($('#maxOutlier'+name).val())) && ((int[i].values[0] > Number($('#maxOutlier'+name).val())) || !(int[i].values[0] > Number($('#maxOutlier').val())))) ? true : false;

            if(!isOutlierMin && !isOutlierMax){
                if((int[i].values[0] < Number($('#maxOutlier'+name).val())) && int[i].values[1] > Number($('#maxOutlier'+name).val())){
                    fields3[name].intervals[i].values[1] = Number($('#maxOutlier'+name).val());
                    fields3[name].intervals[i].name = fields3[name].intervals[i].values[0].toFixed(decimalPlace) + ' ~ ' + fields3[name].intervals[i].values[1].toFixed(decimalPlace);
                    fields3[name].intervals[i].name = fields3[name].intervals[i].values[0].toFixed(decimalPlace) + ' ~ ' + fields3[name].intervals[i].values[1].toFixed(decimalPlace);
                }
                //Create the row div.
                var $intRows = $(document.createElement('div'));
                var betweenText = (fields3[name].type == 'date') ? 'to' : 'to <';

                $intRows.addClass('row text-center pb-1')
                .attr('id',name+i)
                .appendTo(htmlElement);

                $('<div class="col-4">').html(int[i].values[0].toFixed(decimalPlace)).appendTo($intRows);

                $('<div class="col-4">').html(betweenText).appendTo($intRows);

                $('<div class="col-4 pointer">').html(int[i].values[1].toFixed(decimalPlace))
                .dblclick(function(){
                    var intervalId = parseInt($(this).parent().attr('id').replace(name,''));
                    var intervalValue = fields3[name].intervals[intervalId].values[1];

                    $(this).html('<input type="number" class="w-100" id="changeValueBox" value="'+intervalValue+'">');
                    //When user clicks out of input box change the intervals value min and max
                    $('#changeValueBox').blur(function(){                    
                        //Change the max value for the intervals based on what the user has entered.
                        for(k=0;k<fields3[name].intervals.length;k++){
                            if(k < intervalId){
                                continue;
                            }
                            else{
                                var newNumber = Number($('#changeValueBox').val());

                                if((newNumber % 1) != 0 && fields3[name].type == 'date'){
                                    var errorMessage = 'Cannot add decimal points to dates';
                                    createErrorMessage($('#'+name+'IntervalsBody'), errorMessage);
                                    break;
                                }

                                var intervalK = (fields3[name].intervals.length == 1) ? 0 : (k-1);

                                if(intervalK == -1){
                                    intervalK = 0;
                                }

                                if((newNumber <= fields3[name].values[1]) && (newNumber >= fields3[name].intervals[k].values[0])){
                                    if(k==intervalId){
                                        fields3[name].intervals[intervalId].values[1] = newNumber;
                                        fields3[name].intervals[intervalId].name = rnd(fields3[name].intervals[intervalId].values[0]) + ' ~ ' +  rnd(fields3[name].intervals[intervalId].values[1]);
                                        fields3[name].intervals[intervalId].description = rnd(fields3[name].intervals[intervalId].values[0]) + ' ~ ' +  rnd(fields3[name].intervals[intervalId].values[1]);
                                    }
                                    else{
                                        if(newNumber >= fields3[name].intervals[k].values[1]){
                                            fields3[name].intervals.splice(k, 1);
                                            k=0;
                                            continue;
                                        }
                                        else{
                                            fields3[name].intervals[k].values[0] = newNumber;
                                            break;
                                        }
                                    }
                                }else if((newNumber < fields3[name].intervals[k].values[0]) && (newNumber <= fields3[name].values[1]) && newNumber >= fields3[name].intervals[intervalK].values[1]){
                                    fields3[name].intervals[k].values[0] = newNumber;
                                    alreadyChanged = true;
                                    break;
                                }
                                else{
                                    var errorMessage;
                                    var text = (fields3[name].type == 'date') ? 'Date' : 'Number';
                                    if((newNumber > fields3[name].values[1]) && (newNumber >= fields3[name].intervals[k].values[0])){
                                        errorMessage = text+' cannot be greater than the max range.'
                                    }
                                    else if((newNumber < fields3[name].values[1]) && (newNumber < fields3[name].intervals[k].values[0])){
                                        errorMessage = text+' cannot be less than the min range of this interval.'
                                    }
                                    createErrorMessage($('#'+name+'IntervalsBody'), errorMessage);
                                    break;
                                }
                            }
                        }
                        //Because user is making changes to the values, save the object, making it easier to return to the original value when decimals are changed.
                        intervalsNumeric = $.extend(true,{},fields3[name].intervals);
                        if(!(fields3[name].type == 'date')){
                            generateNumericIntervalsRows(name, fields3[name].intervals, htmlElement, $('#roundingSelect').val());
                        }else{
                            generateNumericIntervalsRows(name, fields3[name].intervals, htmlElement, 0);
                        }

                        _doRender();    //Apply to table
                    });
                })
                .appendTo($intRows);
            }
            else{
                if(isOutlierMin){
                    var clickedMinOutlier = false;
                    var clickedMaxOutlier = false;
                    //Creates seperate div for outliers min
                    var outlierNumber = Number($('#minOutlier'+name).val());
                    //Change array to incorporate outliers for min
                    for(t=0;t<fields3[name].intervals.length;t++){
                        if(t==0){
                            fields3[name].intervals[t].values[0] = fields3[name].values[0];
                            fields3[name].intervals[t].values[1] = outlierNumber;
                            fields3[name].intervals[t].name = '<' + outlierNumber.toFixed(decimalPlace);
                            fields3[name].intervals[t].description = '<' + outlierNumber.toFixed(decimalPlace);
                            continue;
                        }

                        if(t==1){
                            if(fields3[name].intervals[t].values[0] > outlierNumber){
                                fields3[name].intervals[t].values[0] = outlierNumber;
                                fields3[name].intervals[t].name = outlierNumber.toFixed(decimalPlace) + ' ~ ' + fields3[name].intervals[t].values[1].toFixed(decimalPlace);
                                fields3[name].intervals[t].description = outlierNumber.toFixed(decimalPlace) + ' ~ ' + fields3[name].intervals[t].values[1].toFixed(decimalPlace);
                                continue;
                            }
                        }
                    
                        if((fields3[name].intervals[t].values[0] < outlierNumber)&&(fields3[name].intervals[t].values[1] <= outlierNumber)){
                            fields3[name].intervals.splice(t, 1);
                            t=0;
                            continue
                        }
                        
                        if((fields3[name].intervals[t].values[0] < outlierNumber) && (fields3[name].intervals[t].values[1] > outlierNumber)){
                            fields3[name].intervals[t].values[0] = outlierNumber;
                            fields3[name].intervals[t].name = outlierNumber.toFixed(decimalPlace)+ ' ~ ' + fields3[name].intervals[t].values[1].toFixed(decimalPlace);
                            fields3[name].intervals[t].description = outlierNumber.toFixed(decimalPlace)+ ' ~ ' + fields3[name].intervals[t].values[1].toFixed(decimalPlace);
                            continue;
                        }
                    }
                    var $intRows = $(document.createElement('div'));
                    var lessThanPrior = (fields3[name].type == 'date') ? 'Prior to ' : '<';

                    $intRows.addClass('row text-center pb-1')
                    .appendTo(htmlElement);
                    
                    $('<div class="col-4">').html('Outliers').appendTo($intRows);
                    $('<div class="col-4">').html(lessThanPrior+outlierNumber.toFixed(decimalPlace)).appendTo($intRows);
                    $('<div class="col-4">').append($('<button>').addClass('btn btn-secondary border-dark').attr('id','removeMinOutlier')
                    .append('<i class="ui-icon ui-icon-trash"></i>'))
                    .click(function(){
                        if(!clickedMinOutlier){
                            originalOutliers[0] = fields3[name].intervals[0];
                            fields3[name].intervals.splice(0,1);

                            $('#removeMinOutlier').empty();
                            $('#removeMinOutlier').toggleClass('btn-secondary border-dark btn-success')
                            .append('<i class="bi bi-plus-circle"></i>');
                            clickedMinOutlier = true;
                        }
                        else {
                            fields3[name].intervals.unshift(originalOutliers[0]);
                            $('#removeMinOutlier').empty();
                            $('#removeMinOutlier').append('<i class="ui-icon ui-icon-trash"></i>');
                            $('#removeMinOutlier').toggleClass('btn-success btn-secondary border-dark');
                            clickedMinOutlier = false;
                        }
                        _doRender();
                        
                    })
                    .appendTo($intRows);
                }
                else if(isOutlierMax){
                    //Creates seperate div for outliers min
                    var outlierNumber = Number($('#maxOutlier'+name).val());
                    fields3[name].intervals[i].values[1] = outlierNumber;
                    fields3[name].intervals[i].name = fields3[name].intervals[i].values[0].toFixed(decimalPlace) + ' ~ ' + outlierNumber.toFixed(decimalPlace);
                    fields3[name].intervals[i].description = fields3[name].intervals[i].values[0].toFixed(decimalPlace) + ' ~ ' + outlierNumber.toFixed(decimalPlace);
                    
                    //Change array to incorporate outliers for min
                    for(t=0;t<fields3[name].intervals.length;t++){
                        if(t== fields3[name].intervals.length-1){
                            fields3[name].intervals[t].values[1] = fields3[name].values[1];
                            fields3[name].intervals[t].values[0] = outlierNumber;
                            fields3[name].intervals[t].name = '>' + outlierNumber.toFixed(decimalPlace);
                            fields3[name].intervals[t].description = '>' + outlierNumber.toFixed(decimalPlace);
                            continue;
                        }
                        
                        if(fields3[name].intervals[t].values[1] > outlierNumber){
                            fields3[name].intervals.splice(t, 1);
                            t-=1;
                        }
                    }
                    var $intRows = $(document.createElement('div'));
                    var greaterThanPrior = (fields3[name].type == 'date') ? 'After ' : '>';

                    $intRows.addClass('row text-center pt-1')
                    .appendTo(htmlElement);
                    
                    $('<div class="col-4">').html('Outliers').appendTo($intRows);
                    $('<div class="col-4">').html(greaterThanPrior+outlierNumber.toFixed(decimalPlace)).appendTo($intRows);
                    $('<div class="col-4">').append($('<button>').addClass('btn btn-secondary border-dark').attr('id','removeMaxOutlier')
                    .append('<i class="ui-icon ui-icon-trash"></i>'))
                    .click(function(){
                        if(!clickedMaxOutlier){
                            originalOutliers[1] = fields3[name].intervals[fields3[name].intervals.length-1];
                            fields3[name].intervals.splice(fields3[name].intervals.length-1,1);

                            $('#removeMaxOutlier').empty();
                            $('#removeMaxOutlier').toggleClass('btn-secondary border-dark btn-success')
                            .append('<i class="bi bi-plus-circle"></i>');
                            clickedMaxOutlier = true;
                        }
                        else {
                            fields3[name].intervals.push(originalOutliers[1]);
                            $('#removeMaxOutlier').empty();
                            $('#removeMaxOutlier').append('<i class="ui-icon ui-icon-trash"></i>');
                            $('#removeMaxOutlier').toggleClass('btn-success btn-secondary border-dark');
                            clickedMaxOutlier = false;
                        }
                        _doRender();
                        
                    })
                    .appendTo($intRows);
                    break;
                }
            }
        }

        _doRender();
    }

    /* 
    * Update the description and name within the fields3 objects
    */
    function updateDescriptionName(name, ints, decimalPlace){

        for(i=0;i<ints.length;i++){
            var intervalName = ints[i].values[0].toFixed(decimalPlace) + ' ~ ' + ints[i].values[1].toFixed(decimalPlace);

            fields3[name].intervals[i].name = intervalName;
            fields3[name].intervals[i].description = intervalName;
        }
    }

    /**
    * remove interval and rearange div rowids to correspond to the array.
    */
    function removeInterval(name, idx, groupidx){

        //Remove the value from the interval with multiple values.
        if(groupidx != -1){
            fields3[name].intervals[groupidx].description = '';
            fields3[name].intervals[groupidx].values.splice(idx,1);

            //Re-adjust description.
            for(k=0;k<fields3[name].values.length;k++){
                var string = (fields3[name].intervals[groupidx].values.length > 1) ? '+' : '';
                
                for(j=0;j<fields3[name].intervals[groupidx].values.length;j++){
                    if(parseInt(fields3[name].values[k].id) == fields3[name].intervals[groupidx].values[j]){
                        fields3[name].intervals[groupidx].description = fields3[name].values[k].text + string
                    }
                }
            }

            //Re-adjust value ids for each div.
            var currentValues = $('#'+name+groupidx).find('div.groupList > :first-child');
            currentValues.each(function(i,ele){
                $(ele).attr('id', i);
            });

            //If last value in interval remove entire value.
            if(fields3[name].intervals[groupidx].values.length == 0){
                fields3[name].intervals.splice(groupidx,1);
                isIntervalRemoved = true;   //Used to help keep track of the removed variable.
            }
        }
        else{
            //Remove from intervals
            fields3[name].intervals.splice(idx,1);        
        }

        //Re-adjust rowids
        var currentRows = $('#rightColDiv'+name+' > .list').not('#templateInterval');
        currentRows.each(function(i, ele){
            $(ele).attr('id', name+i);
        });
        
        _doRender(); //Render after the removal of a value.
    }

    //Find the position of the value within the stored array
    function findPositionInArray(name, detailID){
        var idx;

        for(i=0;i<fields3[name].intervals.length;i++){
            if(fields3[name].intervals[i].values.length > 1){
                for(j=0;j<fields3[name].intervals[i].values.length;j++){
                    if(fields3[name].intervals[i].values[j]==detailID){
                        idx = j;
                        break;
                    }
                }
            }
            else {
                if(fields3[name].intervals[i].values[0]==detailID){
                    idx = i;
                    break;
                }
            }
        }

        return idx;
    }

    /**
    * add/edit interval
    */
    function editInterval( name, idx){

        var $editedRow = $('#'+name+idx);

        var $newInterval = $(document.createElement('div'))
            .addClass('intervalDiv list row pe-1')
            .attr('id','templateInterval')
            .insertBefore($('#addIntervalDiv'+name));

            $('<div class="col-1 p-1">')
            .attr('id', name+idx+'ArrowPlacement')
            .appendTo($newInterval);

            $('<div class="col-4 border-2 border-top border-secondary">')
            .append(
                $('<div>')
                .html('newInterval')
                .css({'font-weight':'bold'} ))
            .appendTo($newInterval);

            $('<div class="col border-2 border-top border-secondary">')
            .html('empty')
            .appendTo($newInterval);

            $('#addInterval').attr('disabled', true);
        /*
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
        */
        $newInterval.find('#'+name+idx+'ArrowPlacement')
            .append($('<button>')
            .addClass("btn btn-outline-success w-100 p-1")
            .attr('id','applyButton')
            .click(function(){
                __addeditInterval(name, idx, true);
                $('#deselectAll').removeClass('d-none');
            }));
        $newInterval.find("#applyButton").append('<i class="ui-icon ui-icon-arrow-r w-100"></i>');
 
        $($newInterval.find("#applyButton")).appendTo('#'+name+idx+'ArrowPlacement'); //Places arrow at the begining of the edited or newly added interval.


            //window.hWin.HEURIST4.msg.showMsgDlg('There are no more terms available');
    }

    function __addeditInterval( name, idx, notSaved){

        if(notSaved){
            var isAllChecked = ($('input[name='+name+'Options]:checked:disabled').length == $('input[name='+name+'Options]').length) ? true : false;
            var isNotChecked = ($('input[name='+name+'Options]:checked:not(:disabled)').length == 0) ? true : false;
            //Generate error message to prevent user from submitting an empty interval
            if(isAllChecked || isNotChecked){
                var errorMessage = 'This new interval cannot be empty. If all available values are used, click the blue arrow to make it available.'
                $('#applyButton').popover({
                    container : 'body',
                    placement : 'bottom',
                    content : errorMessage,
                    trigger : 'click',
                    delay : {
                        show : '100', 
                        hide :'100'
                    }
                }).popover('show');

                //Hide the popover after 7 seconds.
                setTimeout(function(){
                    $('#applyButton').popover('hide');
                }, 7000);

                return;
            }

            if($('div.popover:visible').length){
                $('#applyButton').popover('hide');
            }

            var detailtype = fields3[name].type;

            if(idx<0){
                fields3[name].intervals.push( {name:'', description:'', values:[] });
                idx = fields3[name].intervals.length-1;
            }else{
                fields3[name].intervals[idx].values = [];
                fields3[name].intervals[idx].description = '';
            }
            fields3[name].intervals[idx].name = "newInterval";

            if(detailtype=="enum" || detailtype=="resource" || detailtype=="relationtype"){ //false &&
                var sels = $('input[name='+name+'Options]').filter(function(){
                    return !this.disabled && this.checked;
                });
                var isMulti = (sels.length > 1) ? '+' : ''
                $.each(sels, function(i, ele){
                    fields3[name].intervals[idx].values.push( parseInt($(ele).attr('termid')) );
                    fields3[name].intervals[idx].description = fields3[name].intervals[idx].description + $(ele).attr('termname')+isMulti;
                    sels.attr('disabled',true);
                    sels.attr('checked', true);
                });
            
            }/*else if(detailtype=="float" || detailtype=="integer"){

                fields3[name].intervals[idx].values.push( parseFloat($dlg.find('#minval').val() ));
                fields3[name].intervals[idx].values.push(  parseFloat($dlg.find('#maxval').val() ));
                fields3[name].intervals[idx].description = $dlg.find('#minval').val()+' ~ '+$dlg.find('#maxval').val();

            }
            */
        }

        //Render new interval
        var interval = fields3[name].intervals[idx];

        $intdiv = $(document.createElement('div'))
            .addClass('intervalDiv list row pe-1')
            .attr('id', name+idx )
            .insertBefore($('#addIntervalDiv'+name));

            $('<div class="col-1 p-1 arrowDiv">')
            .attr('id', name+idx+'ArrowPlacement')
            .appendTo($intdiv);

            $('<div class="col-4 p-1 border-2 border-top border-secondary pointer">')
            //.css({'width':'160px','display':'inline-block'})
            .html(interval.name)
            .css({'font-weight':'bold'} )
            .dblclick(function(event){
                //Collect the interval number of the clicked row
                var intervalElement = $(this).parent();
                var intervalPosition = intervalElement.attr('id').replace(name, '');

                intervalPosition = parseInt(intervalPosition);

                $(this).html('<input class="w-100" id="changeNameBox" value="'+interval.name+'">');
                //When user clicks out of input box edit name
                $('#changeNameBox').blur(function(){
                    var nameChanged = $('#changeNameBox').val();
                    $(this).parent().html(nameChanged);

                    fields3[name].intervals[intervalPosition].name = nameChanged;
                    _doRender();    //Apply to table

                 });
                })
            .appendTo($intdiv);

            //If group contains more than one value
            if(interval.values.length > 1){
                //Add delete button
                $('<div class="col-1 p-1 border-2 border-top border-secondary delete d-flex align-items-center">')
                .append($('<button>')
                    .addClass('btn btn-secondary border-dark w-100 p-0 py-1')
                    .append('<i class="ui-icon ui-icon-trash"></i>')
                    .click(function(){
                        //Remove interval and uncheck checkboxes
                        var interval = parseInt($(this).parents('div.list').attr('id').replace(name,''));

                        for(s=0;s<fields3[name].intervals[interval].values.length;s++)
                        {
                            var clicked = fields3[name].intervals[interval].values[s];

                            //Find checkbox with same valueid
                            $('input[termid='+ clicked+']')
                                .prop('checked', false)
                                .attr('disabled', false);
                        }

                        if($('input[name='+name+'Options]:checked').length != fields3[name].values.length){
                            $('#selectAll'+name).prop('checked', false)
                            .attr('disabled',false);
                        }

                        $('#'+name+interval).remove();

                        removeInterval(name, interval, -1);
                    })
                )
                .appendTo($intdiv);

                var splitDescription = interval.description.split("+");

                var listGroup = $('<div class="col p-1 border-2 border-top border-secondary groupings">')
                .appendTo($intdiv);
            
                for(x=0;x<(splitDescription.length-1); x++){
                    var listItem = $('<div class="row p-1 w-100 bg-transparent groupList">')
                    .append('<div class="col-2 p-1" id="'+x+'">')
                    .append('<div class="col p-1 border-bottom border-dark description">'+splitDescription[x]+'</div>')
                    .appendTo(listGroup);
                }

                //Create add button for the group
                $intdiv.find('#'+name+idx+'ArrowPlacement')
                .append($('<button>')
                .addClass("btn btn-outline-success w-100 p-1 applyToGroup")
                .click(function(){

                    var isAllChecked = ($('input[name='+name+'Options]:checked:disabled').length == $('input[name='+name+'Options]').length) ? true : false;
                    var isNotChecked = ($('input[name='+name+'Options]:checked:not(:disabled)').length == 0) ? true : false;
                    //Generate error message to prevent user from submitting an empty interval
                    if(isAllChecked || isNotChecked){
                        var errorMessage = 'All values have been assigned. If you would like to add a value to this group, please click the blue arrow to de-assign.'
                        $(this).popover({
                            container : 'body',
                            placement : 'bottom',
                            content : errorMessage,
                            trigger : 'click',
                            delay : {
                                show : '100', 
                                hide :'100'
                            }
                        }).popover('show');

                        //Hide the popover after 7 seconds.
                        setTimeout(function(){
                            $('.applyToGroup').popover('hide');
                        }, 7000);
                        
                        return;
                    }

                    if($('div.popover:visible').length){
                        $(this).popover('hide');
                    }

                    //Add new value to group
                    var interval = parseInt($(this).parents('div.list').attr('id').replace(name,''));
                    var newValue = [];
                    var newDescription = [];
                    var numberValues = fields3[name].intervals[interval].values.length;
                    //Find all checkboxes that have been clicked.
                    var sels = $('input[name='+name+'Options]').filter(function(){
                        return !this.disabled && this.checked;
                    });
                    //Add to the existing array.
                    var isMulti = (sels.length > 1) ? '+' : ''
                    $.each(sels, function(i, ele){
                        fields3[name].intervals[interval].values.push( parseInt($(ele).attr('termid')) );
                        fields3[name].intervals[interval].description = fields3[name].intervals[interval].description + $(ele).attr('termname')+isMulti;
                        newValue[i] = parseInt($(ele).attr('termid'));
                        newDescription[i] = $(ele).attr('termname');
                        sels.attr('disabled',true);
                        sels.attr('checked', true);
                    });
                    //Find list group
                    listGroup = $(this).parents('div.list');
                    listGroup = listGroup.find('div.groupings');
                    //Add new layer to group interface.
                    for(i=0;i<newDescription.length;i++){
                        //Add description
                        $('<div class="row p-1 w-100 bg-transparent groupList">')
                        .append('<div class="col-2 p-1" id="'+numberValues+'">')
                        .append('<div class="col p-1 border-bottom border-dark description">'+newDescription[i]+'</div>')
                        .appendTo(listGroup);

                        //Append remove button
                        var $removeButton = $('<button></button>')
                        .addClass('btn btn-outline-primary w-100 p-0 py-1')
                        .attr('valueid',newValue[i])
                        .click(function(){
                            //Get the name of the value clicked to remove from group interval
                            var clicked = $(this).attr('valueid');

                            //Find checkbox with same valueid
                            $('input[termid='+ clicked+']')
                            .prop('checked', false)
                            .attr('disabled', false);

                            if($('input[name='+name+'Options]:checked').length != fields3[name].values.length){
                                $('#selectAll'+name).prop('checked', false)
                                .attr('disabled',false);
                            }

                            //Remove interval from rendered field.
                            var index = findPositionInArray(name, parseInt(clicked))
                            var groupIndex = parseInt($(this).parents('div.list').attr('id').replace(name,''));
                            
                            var singleIndex = (fields3[name].intervals[groupIndex].values.length == 1) ? -1 : groupIndex;

                            if(singleIndex != -1){
                                if(fields3[name].intervals[groupIndex].values.length > 1){
                                    //Remove div containing the corresponding field.
                                    $(this).parents('div.groupList').remove();
                                }
                                
                                if(fields3[name].intervals[groupIndex].values.length == 2){
                                    /*If two values are left rearange to be consistent with other interval
                                    * This is done before it is deleted within the array.
                                    * Find entire row.
                                    */  
                                    var rowElement = $('#'+name+singleIndex);
                                    //Find the only list element left
                                    var listElement = rowElement.find('div.groupList').children();
                                    var button = listElement.find('button');
                                    //append to front of div
                                    button.appendTo(rowElement.find('div.arrowDiv'));

                                    var nameOfLastInterval = listElement.parent().find('div.description').html();
                                    //remove all elements within this div
                                    listElement = listElement.parents('div.groupings');
                                    listElement.empty();
                                    listElement.html(nameOfLastInterval);

                                    //Remove add button if single interval is found
                                    rowElement.find("button.applyToGroup").remove();
                                    //Remove delete button if single interval found.
                                    rowElement.find('div.delete').remove();
                                }
                            }
                            else{
                                $(this).parents('div.list').remove();
                            }

                            removeInterval(name, index, singleIndex);
                        });

                        $('<i class="ui-icon ui-icon-arrow-l w-100"></i>')
                        .appendTo($removeButton);

                        //Append arrow to the first child element of the div
                        listGroup.find('#'+numberValues).append($removeButton);
                        numberValues++;
                    }
                    _doRender();
                }));

                $intdiv.find(".applyToGroup").append('<i class="ui-icon ui-icon-arrow-r w-100"></i>');
 
                $($intdiv.find("#applyButton")).appendTo('#'+name+idx+'ArrowPlacement'); //Places arrow at the begining of the edited or newly added interval.

                //Create Remove Buttons
                for(i=0;i<interval.values.length;i++){
                    var $removeButton = $('<button></button>')
                        .addClass('btn btn-outline-primary w-100 p-0 py-1')
                        .attr('valueid',interval.values[i])
                        .click(function(){
                            //Get the name of the value clicked to remove from group interval
                            var clicked = $(this).attr('valueid');

                            //Find checkbox with same valueid
                            $('input[termid='+ clicked+']')
                            .prop('checked', false)
                            .attr('disabled', false);

                            if($('input[name='+name+'Options]:checked').length != fields3[name].values.length){
                                $('#selectAll'+name).prop('checked', false)
                                .attr('disabled',false);
                            }

                            //Remove interval from rendered field.
                            var index = findPositionInArray(name, parseInt(clicked))
                            var groupIndex = parseInt($(this).parents('div.list').attr('id').replace(name,''));
                            
                            var singleIndex = (fields3[name].intervals[groupIndex].values.length == 1) ? -1 : groupIndex;

                            if(singleIndex != -1){
                                if(fields3[name].intervals[groupIndex].values.length > 1){
                                    //Remove div containing the corresponding field.
                                    $(this).parents('div.groupList').remove();
                                }
                                
                                if(fields3[name].intervals[groupIndex].values.length == 2){
                                    /*If two values are left rearange to be consistent with other interval
                                    * This is done before it is deleted within the array.
                                    * Find entire row.
                                    */  
                                    var rowElement = $('#'+name+singleIndex);
                                    //Find the only list element left
                                    var listElement = rowElement.find('div.groupList').children();
                                    var button = listElement.find('button');
                                    //append to front of div
                                    button.appendTo(rowElement.find('div.arrowDiv'));

                                    var nameOfLastInterval = listElement.parent().find('div.description').html();
                                    //remove all elements within this div
                                    listElement = listElement.parents('div.groupings');
                                    listElement.empty();
                                    listElement.html(nameOfLastInterval);

                                    //Remove add button if single interval is found
                                    rowElement.find("button.applyToGroup").remove();
                                    //Remove delete button if single interval found.
                                    rowElement.find('div.delete').remove();
                                }
                            }
                            else{
                                $(this).parents('div.list').remove();
                            }

                            removeInterval(name, index, singleIndex);
                        });

                        $('<i class="ui-icon ui-icon-arrow-l w-100"></i>')
                        .appendTo($removeButton);

                        //Append arrow to the first child element of the div
                        listGroup.find('#'+i).append($removeButton);
                }

            }
            else{
                $('<div class="col p-1 border-2 border-top border-secondary">')
                .html(interval.description)
                //.css({'max-width':'250px','width':'250px','display':'inline-block','padding-left':'1.5em'})
                .appendTo($intdiv);
                
                //Add remove button if a single value is added
                var $removeButton = $('<button></button>')
                    .addClass('btn btn-outline-primary w-100 p-1')
                    .attr('valueid',interval.values[0])
                    .click(function(){
                        //Get the name of the value clicked to remove from group interval
                        var clicked = $(this).attr('valueid');

                        //Find checkbox with same valueid
                        $('input[termid='+ clicked+']')
                        .prop('checked', false)
                        .attr('disabled', false);

                        if($('input[name='+name+'Options]:checked').length != fields3[name].values.length){
                            $('#selectAll'+name).prop('checked', false)
                            .attr('disabled',false);
                        }

                        //Remove interval from rendered field.
                        var index = findPositionInArray(name, parseInt(clicked))
                        removeInterval(name, index, -1);
                        //Remove div containing field.
                        $(this).parents('div.list').remove();
                    });

                    $('<i class="ui-icon ui-icon-arrow-l w-100"></i>')
                    .appendTo($removeButton);

                    //Append arrow to the first child element of the div
                    $('#'+name+idx+' div:first-child').append($removeButton);
            }

        if($('input[name='+name+'Options]:checked').length == fields3[name].values.length){
            $('#selectAll'+name).prop('checked', true)
            .attr('disabled',true);
        }

        $('#templateInterval').remove();

        $('#addInterval').prop('disabled',false);
        
        if(notSaved) _doRender();
    }

    //Create error message
    function createErrorMessage(location, message){
        
        if($('#alert').length){
            $('#alert').remove();
        }

        var alert = document.createElement('div');
            $(alert).addClass('alert alert-warning alert-dismissible fade show')
            .attr('role', 'alert')
            .attr('id','alert')
            .append('<i class="ui-icon ui-icon-alert"></i>')
            .append('<span> '+message+'</span>')
            .append($('<button>')
                .attr('type', 'button')
                .attr('class','btn-close')
                .attr('data-bs-dismiss', 'alert')
                .attr('aria-label', 'close')
            )
            .prependTo(location);
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

    //Change the decimal places within the array
    function changeIntervalDecimal(name, option){
        $.extend(true, fields3[name].intervals, intervalsNumeric);
        for(i=0;i<fields3[name].intervals.length;i++){
            for(j=0;j<fields3[name].intervals[i].values.length;j++){
                var num = fields3[name].intervals[i].values[j].toFixed(parseInt(option));
                fields3[name].intervals[i].values[j] = Number(num);
            }
        }
    }

    /**
    * render crosstab data as set of tables
    */
    function _doRender(){
        //Destroy chart if exists
        if(Chart.getChart('pieResults')){
            Chart.getChart('pieResults').destroy();
        }

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
        var topText = 'DB: '+window.hWin.HAPI4.database+ ',Date and time: ' + (date.getDate() + "/" + (date.getMonth()+1) + "/" + date.getFullYear());

        $divres.append('<div></div>')
        $divres.append('<span>DB: <b>'+window.hWin.HAPI4.database+' </b></span>');
        $divres.append('<span>Date and time: '+ (date.getDate() + "/" + (date.getMonth()+1) + "/" + date.getFullYear())+' </span>');
        //$divres.append('<div>Type of analysis: Crosstab</div>');
        //$divres.append('<div>Title (name) of saved analysis: '+ +'</div>');
        //????? $divres.append('<div>Record type analysed: '++'</div>');
        if(_currentRecordset!=null){
            $divres.append('<span>N = '+ _currentRecordset['recordCount'] +' </span>');
            $divres.append('<span>Query string: '+_currentRecordset['query_main'] +' </span>');
            topText += ',N = '+ _currentRecordset['recordCount'] +  ',Query string: '+_currentRecordset['query_main'];

        }else{
            $divres.append('<span>Query string: q='+query_main+'&w='+query_domain +' </span>');
            topText += ',Query string: q='+query_main+'&w='+query_domain;
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
        
        if(fields3['page'].intervals.length <=0){
            var tableTitle = 'Table Displaying ';
            if(fields3['row'].intervals.length > 0 && fields3['column'].intervals.length <= 0) tableTitle += fields3['row'].fieldname;
            if(fields3['row'].intervals.length > 0 && fields3['column'].intervals.length > 0) tableTitle += fields3['row'].fieldname + ' and ' + fields3['column'].fieldname;
            tableTitle += ' ' + aggregationMode;
            //Append text box for user to enter table title.
            $divres.append('<div class="p-2"><label class="me-2" for="tableTitle">Enter a table title:</label><input type="text" class="rounded-0" id="tableTitle"></input><button class="btn btn-success ms-2" id="titleSubmit"><i class="ui-icon ui-icon-circle-check"></i> Apply</button></div>');
            $divres.append('<h2 class="crosstab-page" id="tableHeader">'+tableTitle+'</h2>');
            $('#titleSubmit').click(function(){
                var title = $('#tableTitle').val();

                if($('#tableTitle').val().length <=0 || $.trim($('#tableTitle').val()) == ''){
                    $('#tableHeader').html(tableTitle);
                }
                else{
                    $('#tableHeader').html(title);
                }
                
            });
        }

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
                            if( fitToInterval( fields3.page.type, pages[i].values, pval, 'page' ) ){
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

        //Create datatable
        $(document).ready(function(){
            $(".resultsTable").DataTable({
                "paging" : false,
                "info" : false,
                dom:"Bfrtip",
                buttons:[

                    {
                        extend: 'csv',
                        className: 'exportButtons',
                        filename: function(){
                            var title = (fields3['page'].intervals.length <=0) ? $('#tableHeader').html() : fields3['page'].fieldname;
                            return title;
                        },
                        customize: function(csv){
                            var extendedRow = '';

                            if(($('#rbShowPercentRow').is(':checked') && !($('#rbShowPercentColumn').is(':checked'))) || (!($('#rbShowPercentRow').is(':checked')) && $('#rbShowPercentColumn').is(':checked'))){
                                $('#resultsTable').find('thead>tr:nth-child(2)>th').each(
                                    function(index, element){
                                        var text = $(element).text();
                                        if(index == 0){
                                            extendedRow += '\n\t';
                                        }
                                        else{
                                            extendedRow += '"'+text+'"' + '\t\t';
                                        }
                                });
                            }

                            if($('#rbShowPercentRow').is(':checked') && $('#rbShowPercentColumn').is(':checked')){
                                $('#resultsTable').find('thead>tr:nth-child(2)>th').each(
                                    function(index, element){
                                        var text = $(element).text();
                                        if(index == 0){
                                            extendedRow += '\n\t';
                                        }
                                        else{
                                            extendedRow += '"'+text+'"' + '\t\t\t';
                                        }
                                });
                            }

                            return topText+ '\n\n\t' + ((fields3['column'].intervals.length > 0) ? fields3['column'].fieldname+ extendedRow : '') + '\n' + csv;
                        },
                        footer: true
                    },

                    {
                        extend:'pdf',
                        orientation: 'landscape',
                        className: 'exportButtons',
                        customize: function(pdfDocument){
                            pdfDocument.content[0].text = (fields3['page'].intervals.length <=0) ? $('#tableHeader').html() : fields3['page'].fieldname;
                            pdfDocument.content.splice(1, 0, topText);
                            if(fields3['column'].intervals.length == 0) return;

                            pdfDocument.content[2].table.headerRows = 2;
                            var firstHeaderRow = [];
                            $('#resultsTable').find('thead>tr:first-child>th').each(
                                function(index, element){
                                    var cols = element.getAttribute('colSpan');
                                    if(index == 0){
                                        firstHeaderRow.push({
                                            text: '',
                                            style: 'tableHeader',
                                            colSpan: cols
                                        });
                                    }
                                    else{
                                        firstHeaderRow.push({
                                            text: element.innerHTML,
                                            style: 'tableHeader',
                                            colSpan: cols
                                        });
                                    }
                                    for(var i= 0;i<cols-1;i++){
                                        firstHeaderRow.push({});
                                    }
                                });

                            if($('#rbShowPercentRow').is(':checked') || $('#rbShowPercentColumn').is(':checked')){
                                var secondHeaderRow = [];
                                $('#resultsTable').find('thead>tr:nth-child(2)>th').each(
                                    function(index, element){
                                        var cols = element.getAttribute('colSpan');
                                        if(index == 0){
                                            secondHeaderRow.push({
                                                text: '',
                                                style: 'tableHeader',
                                                rowSpan: '1'
                                            });
                                        }
                                        else{
                                            secondHeaderRow.push({
                                                text: element.innerHTML,
                                                style: 'tableHeader',
                                                colSpan: cols
                                            });
                                        }
                                        for(var i= 0;i<cols-1;i++){
                                            secondHeaderRow.push({});
                                        }
                                    });

                                pdfDocument.content[2].table.body.unshift(secondHeaderRow);
                            }
                            pdfDocument.content[2].table.body.unshift(firstHeaderRow);
                        },
                        footer: true
                    
                    }, 

                    /*
                    {
                        extend:'print',
                        className: 'exportButtons',
                        messageTop: topText,
                        footer: true
                    }
                    */
                ],
            }
            );
        });

        //Create the pie chart
        var pieCanvas = $('#pieResults');

        let colorsList = [
            '#f08080',
            '#7fffd4',
            '#c39797',
            '#fff68f',
            '#eeeeee',
            '#ffc3a0',
            '#20b2aa',
            '#333333',
            '#ac25e2',
            '#4ca3dd',
            '#ff6666',
            '#ffc0cb'
            
        ];

        //Extract label and data values for pie chart
        let labelsNames = extractData('row', true);
        let dataValues = extractData('row', false);

        // Pie chart will only work for row variables.
        if(fields3['row'].intervals.length > 0 && fields3['column'].intervals.length <= 0 && fields3['page'].intervals.length <= 0){
            $('#pieResults').removeClass('d-none');
            $('#pieMessage').addClass('d-none');
            var config = {
                type: 'pie',
                data: {
                    labels: labelsNames,
                      datasets: [{
                        data: dataValues,
                        backgroundColor: colorsList,
                        hoverOffset: 4
                    }
                ]
                },
                options: {
                    responsive: false,
                    plugins: {
                      legend: {
                        display: false
                      },
                      title: {
                          display: true,
                          text: fields3['row'].fieldname
                      }
                    }
                }
            };
        }

        /* For both column and row selections.
         * No implementation for row and column select.
        */
        if(fields3['row'].intervals.length > 0 && (fields3['column'].intervals.length > 0 || fields3['page'].intervals.length > 0)){
            $('#pieMessage').removeClass('d-none');
            $('#pieResults').addClass('d-none');
            /*
            var dataVals;
            var config = {
                type: 'doughnut',
                data: {
                    datasets: [{
                    },
                ], 
                    labels: labelsNames
                },
                options: {
                    responsive: false,
                    plugins: {
                    legend: {
                        display: false
                    }
                    },
                }
            };;

            for(t=0; t<dataValues.length;t++){

            }
            */
        }
        

        var pieChart = new Chart(pieCanvas, config);
        
        //console.log($.fn.dataTable.isDataTable("table#resultsTable"));

        _setMode(2);//results
    }//_doRenders

    function extractData(name, isLabel){
        var data = [];
        if(isLabel){
            for(i=0;i<fields3[name].intervals.length;i++){
                data.push(fields3[name].intervals[i].name);
            }
        }
        else{
            for(i=0;i<fields3[name].intervals.length;i++){
                data.push(fields3[name].intervals[i].output);
            }
        }

        return data;
    }

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
        var totalspan = 1;

        if((showPercentageRow || showPercentageColumn) && clen>0) rowspan++;
        if(showPercentageRow && clen>0) colspan++;
        if(showPercentageColumn) colspan++;
        if(showPercentageRow && showTotalsColumn) totalspan++;
        if(showPercentageColumn && showTotalsColumn) totalspan++;

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
                        if( fitToInterval( fields3.row.type, rows[i].values, rval, 'row') ){
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
                            if( fitToInterval( fields3.column.type, columns[j].values, records[idx][1], 'column' ) ){
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
        $table.css('width', '0%');
        $table.css('margin', '0');
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
            rowHeader1.append('<th>&nbsp;</th><th class="crosstab-header0" style="text-align:left; border-left:1px solid black;" colspan="'+((notemtycolumns*colspan)+(showTotalsColumn?totalspan:0))+'">'+fields3.column.fieldname+'</th>');
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
                    $rowPercentageHeader.append('<th class="crosstab-header">'+aggregationMode+'</th><th class="percent">Row%</th><th class="percent">Col%</th>');
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
            $row.append('<td class="crosstab-header" style="text-align:left;">'+rows[i].name+'</td>');

            if(noColumns){
                if(rows[i].output[0]!=0 || !supressZero){
                    s = '<td class="crosstab-value">'+rows[i].output[0] +'</td>'
                    if(showPercentageColumn){
                        s = s+'<td class="percent">'+rows[i].percent_row[0] +'%</td>'
                    }
                    $row.append(s);
                }else{
                    $row.append('<td>&nbsp;</td>');

                    if(showPercentageColumn){
                        $row.append('<td>&nbsp;</td>'); //Add extra data for DataTables to work correctly
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

    function fitToInterval(type, values, val, name){
        if(type=="enum" || type=="resource" || type=="relationtype"){
            return (window.hWin.HEURIST4.util.findArrayIndex(val,values)>=0); // values.indexOf(val)
        }else{
            if(val == fields3[name].values[1]){
                return (val>=values[0] && val<=values[1]);    
            }else{
                val = parseFloat(val);
                return (val>=values[0] && val<values[1]); 
            }     
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
            createErrorMessage($('#errorContainerFilter'), 'Please apply a filter to create a result set');
            $("#errorContainerFilter").removeClass('d-none');
            //$("#div_empty").show();
        }else{
            //show results
            $("#divres").show();
            $("#qform").show();
            $("#errorContainerFilter").addClass('d-none');
            //$("#div_empty").hide();
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
            count: keepCount,
            allFields: {column:fields3.column,row:fields3.row,page:fields3.page}
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

        _applySavedIntervals(settings.allFields, settings.count);
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

        OnRowTypeChange: function(value) {
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

    return that;

}
