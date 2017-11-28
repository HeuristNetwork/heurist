<?php

/**
*
* crosstabulation.php : crosstabulate 1 through 3 fields, calculate percentages, col and row totals etc.
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


// TODO: crosstabulation.php      UNDER DEVELOPMENT    see also /viewers/crosstabs/crosstabs.php (vsn 3 version)




require_once(dirname(__FILE__)."/../System.php");
require_once(dirname(__FILE__)."/../dbaccess/db_structure.php");

$system = new System();

if(! $system->init(@$_REQUEST['db'], true) ){
    //@todo - redirect to error page
    print_r($system->getError(),true);
    exit();
}
?>
<html>
    <head>
        <title><?=HEURIST_TITLE ?></title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">

        <link rel="stylesheet" type="text/css" href="../../style.css">

        <link rel="stylesheet" type="text/css" href="../../ext/jquery-ui-themes-1.12.1/themes/base/jquery-ui.css" />
        
        <script type="text/javascript" src="../../ext/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="../../ext/jquery-ui-1.12.1/jquery-ui.js"></script>

        <script type="text/javascript" src="../../core/recordset.js"></script>
        <script type="text/javascript" src="../../core/hapi.js"></script>
        <script type="text/javascript" src="../../core/utils.js"></script>

        <script type="text/javascript">
            <?php
            //@ todo - load this stuff in hEditing
            print "window.hWin.HEURIST4.rectypes = ".json_encode( dbs_GetRectypeStructures($system, null, 2) ).";\n";
            print "window.hWin.HEURIST4.terms = ".json_encode( dbs_GetTerms($system ) ).";\n";
            ?>
            var recordtype;

            //localization stub
            window.hWin.HR = function(str){
                return str;
            }

            $(document).ready(function() {

                if(!window.hWin.HAPI4){
                    window.hWin.HAPI4 = new hAPI('<?=$_REQUEST['db']?>');
                }

                window.hWin.HEURIST4.util.createRectypeSelect($('#cbRectypes').get(0), null, ' ');

                $('.showintervals')
                .button({icons: {primary: "ui-icon-triangle-1-s"}, text: false })
                .css({width:'16px',height:'16px'})
                .click(function( event ) {
                    showHideIntervals( event );
                });

            });

            //update list of fields
            function onRectypeChange(event){

                recordtype = event.target.value;

                var allowedlist = ["enum", "integer", "float", "date", "freetext"]; //"resource",

                window.hWin.HEURIST4.util.createRectypeDetailSelect($('#cbColumns').get(0), recordtype, allowedlist, ' ');
                window.hWin.HEURIST4.util.createRectypeDetailSelect($('#cbRows').get(0), recordtype, allowedlist, ' ');
                window.hWin.HEURIST4.util.createRectypeDetailSelect($('#cbPages').get(0), recordtype, allowedlist, ' ');

                clearIntervals('column');
                clearIntervals('row');
                clearIntervals('page');

            }

            function doCalc(){
                /*
                use `hdb_dos_3`;
                select dtl_Value, count(*) from Records, recDetails
                where rec_RectypeID=5 and dtl_RecID=rec_ID and dtl_DetailTypeID=29
                group by dtl_Value;


                select d1.dtl_Value, d2.dtl_Value, count(*)
                from Records left join recDetails d2 on d2.dtl_RecID=rec_ID and d2.dtl_DetailTypeID=90,
                recDetails d1
                where rec_RectypeID=5 and d1.dtl_RecID=rec_ID and d1.dtl_DetailTypeID=29
                group by d1.dtl_Value, d2.dtl_Value;

                use `hdb_dos_3`;
                select d1.dtl_Value as cls, d2.dtl_Value as rws, count(*) as cnt
                from Records
                left join recDetails d1 on d1.dtl_RecID=rec_ID and d1.dtl_DetailTypeID=29
                left join recDetails d2 on d2.dtl_RecID=rec_ID and d2.dtl_DetailTypeID=90
                where rec_RectypeID=5
                group by d1.dtl_Value, d2.dtl_Value
                order by d1.dtl_Value, d2.dtl_Value;
                */
            }

            /**
            *
            */
            function showHideIntervals(event){

                var name = $(event.target).attr('tt');

                var ele = $('#'+name+'Intervals');

                if( ele.is(':visible') ){
                    ele.hide();
                }else{
                    ele.show();
                }

            }

            var fields3 = {column:{field:0, type:'', values:[], intervals:[]}, row:{}, page:{}};

            /**
            *
            */
            function clearIntervals(name){
                var $container = $('#'+name+'Intervals');
                $container.empty().hide();
                fields3[name] = {field:0, type:'', values:[], intervals:[]};
            }

            /**
            * create fortm specific for particular detail type
            */
            function resetIntervals(event){

                var detailid = event.target.value;
                var name = $(event.target).attr('tt');  //type

                var $container = $('#'+name+'Intervals');
                $container.empty();
                fields3[name] = {field:0, type:'', values:[], intervals:[]};

                if (!(window.hWin.HEURIST4.rectypes.typedefs[recordtype] &&
                    window.hWin.HEURIST4.rectypes.typedefs[recordtype].dtFields[detailid]))
                {
                    $container.hide();
                    return;
                }

                //get detail type
                var fi = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex;
                var details = window.hWin.HEURIST4.rectypes.typedefs[recordtype].dtFields[detailid];
                var detailtype = details[fi['dty_Type']];

                if(detailtype=="enum") //false &&
                {
                    //get all terms and create intervals

                    var allTerms = details[fi['rst_FilteredJsonTermIDTree']];
                    var headerTerms = details[fi['dty_TermIDTreeNonSelectableIDs']];
                    var termlist = window.hWin.HEURIST4.util.getPlainTermsList(detailtype, allTerms, headerTerms);

                    fields3[name] = {field:detailid, type:detailtype, values:termlist, intervals:[]}

                    var i;
                    for (i=0; i<termlist.length; i++){
                        fields3[name].intervals.push( {name:termlist[i].text, description:termlist[i].text, values:[ termlist[i].id ] });  //minvalue, maxvalue
                    }

                }else if(detailtype=="enum" || detailtype=="float" || detailtype=="integer"){
                    //get min and max for this detail in database

                    fields3[name] = {field:detailid, type:detailtype, values:[], intervals:[]}

                    window.hWin.HAPI4.RecordMgr.minmax({ rt:recordtype , dt:detailid },
                        function(response){
                            if(response.status == window.hWin.HAPI4.ResponseStatus.OK){

                                var val0 = parseFloat(response.data.min);
                                var valmax = parseFloat(response.data.max);

                                fields3[name].values = [val0, valmax];

                                var delta = Math.round((valmax - val0)/10);
                                var cnt = 0;
                                while (val0<valmax && cnt<9){
                                    fields3[name].intervals.push( {name:val0+' ~ '+(val0+delta), description: val0+' ~ '+(val0+delta) , values:[ val0, val0+delta ] });  //minvalue, maxvalue
                                    if(cnt>8){
                                        break;
                                    }
                                    val0 = val0+delta;
                                    cnt++;
                                }
                                if(cnt>8){
                                    fields3[name].intervals.push( {name:val0+' ~ '+response.data.max, description: val0+' ~ '+response.data.max , values:[ val0, response.data.max ] });  //minvalue, maxvalue
                                }
                                renderIntervals(name);

                            }else{
                                alert(response.message);
                            }
                        }
                    );

                    return;


                }else if(detailtype=="date"){
                    //get min and max for this detail in database

                }else if(detailtype=="resource"){
                    //show list of fields and then recursive

                }else if(detailtype=="freetext"){
                    //alphabetically, or if distinct values less that 50 like terms

                }

                renderIntervals(name);
            }

            /**
            *
            *
            */
            function renderIntervals(name){

                var $container = $('#'+name+'Intervals');
                $container.empty();

                var idx;
                var intervals = fields3[name].intervals;
                for (idx=0; idx<intervals.length; idx++){

                    var interval = intervals[idx];

                    $intdiv = $(document.createElement('div'))
                    .addClass('recordDiv list')
                    .css({'padding':'0.2em'})
                    .attr('id', name+idx )
                    .appendTo($container);

                    $('<div>')
                    .css({'width':'160px','display':'inline-block'})
                    .append(
                        $('<input>')
                        .addClass('text ui-widget-content ui-corner-all')
                        .val(interval.name)
                        .css({'width':'150px'} ))
                    .appendTo($intdiv);

                    $('<div>')
                    .html(interval.description)
                    .css({'width':'280px','display':'inline-block'})
                    .appendTo($intdiv);

                    $('<div>')
                    .attr('intid', idx)
                    .button({icons: {primary: "ui-icon-pencil"}, text: false })
                    .css({width:'16px',height:'16px','display':'inline-block'})
                    .click(function( event ) {
                        editInterval( name,  $(this).attr('intid') );
                    })
                    .appendTo($intdiv);

                    $('<div>')
                    .button({icons: {primary: "ui-icon-close"}, text: false })
                    .attr('intid', idx)
                    .css({width:'16px',height:'16px','display':'inline-block'})
                    .click(function( event ) {
                        removeInterval( name, $(this).attr('intid') );
                    })
                    .appendTo($intdiv);
                }

                if(fields3[name].values && fields3[name].values.length>0)
                {
                    $intdiv = $(document.createElement('div'))
                    .css({'padding-top':'0.4em'})
                    .attr('intid', 'b0' )
                    .appendTo($container);
                    $('<button>',{text: "Add"})
                    .button({icons: {primary: "ui-icon-plus"}} )
                    .click(function( event ) {
                        editInterval(  name, -1 );
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

                //create multiselect list of terms
                var $dlg = $("#terms-dialog");
                if($dlg.length==0){
                    $dlg = $('<div>')
                    .attr('id','terms-dialog')
                    .appendTo($('body'));
                }
                $dlg.empty();

                var intname = (idx<0)?'new interval':fields3[name].intervals[idx].name;
                $('<div>Name:<input id="intname" value="'+intname+'"></div>')
                //.addClass('recordDiv list')
                .css({'padding':'0.2em'})
                .appendTo($dlg);

                var iHeight = 220;
                var detailtype = fields3[name].type;
                var cnt=0;


                if ( detailtype=="enum") { //false &&

                    var i, j,
                    termlist = fields3[name].values; //all terms
                    for(i=0; i<termlist.length; i++)
                    {
                        var notused = true, isused = false;
                        var intvalues = fields3[name].intervals;
                        for(j=0; j<intvalues.length; j++){
                            if(intvalues[j].values.indexOf( termlist[i].id )>=0){
                                if(idx==j){
                                    isused = true;
                                }else{
                                    notused = false;
                                }
                                break;
                            }
                        }

                        if(notused){

                            $intdiv = $(document.createElement('div'))
                            .addClass('recordDiv list')
                            .css({'padding':'0.2em'})
                            .attr('intid', idx )
                            .appendTo($dlg);

                            $('<input>')
                            .attr('type','checkbox')
                            .attr('checked', isused)
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

                }else if(detailtype=="enum" || detailtype=="float" || detailtype=="integer"){

                    var minv = idx<0?0:fields3[name].intervals[idx].values[0];
                    var maxv = idx<0?0:fields3[name].intervals[idx].values[1];

                    $intdiv = $(document.createElement('div'))
                    .attr('intid', idx )
                    .appendTo($dlg);

                    $intdiv.append($('<div class="header"><label>from</label></div>'))
                    .append( $('<input>')
                        .attr('id','minval')
                        .val(minv))
                    .append($('<div class="header"><label>to</label></div>'))
                    .append( $('<input>')
                        .attr('id','maxval')
                        .val(maxv));

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

                        if(detailtype=="enum"){ //false &&

                            var sels = $dlg.find("input:checked")
                            $.each(sels, function(i, ele){
                                fields3[name].intervals[idx].values.push( $(ele).attr('termid') );
                                fields3[name].intervals[idx].description = fields3[name].intervals[idx].description + $(ele).attr('termname')+' ';
                            });

                        }else if(detailtype=="enum" || detailtype=="float" || detailtype=="integer"){

                            fields3[name].intervals[idx].values.push( $dlg.find('#minval').val() );
                            fields3[name].intervals[idx].values.push( $dlg.find('#maxval').val() );
                            fields3[name].intervals[idx].description = $dlg.find('#minval').val()+' ~ '+$dlg.find('#maxval').val();

                        }

                        renderIntervals(name);

                        $dlg.dialog( "close" )
                    }

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
                }else{
                    alert('no more available terms');
                }


            }



        </script>

    </head>
    <body style="padding:44px;">
        <div style="width:640px; margin:0px auto; padding: 0.5em;">

            <div class="disign-content ui-corner-all ui-widget-content">
                <fieldset>
                    <div>
                        <div class="header"><label for="cbRectypes">Recordtype</label></div>
                        <div class="input-cell"><select id="cbRectypes" onchange="onRectypeChange(event)" class="text ui-widget-content ui-corner-all"></select></div>
                    </div>
                    <div>
                        <div class="header"><label for="cbColumns">Columns</label></div>
                        <div class="input-cell"><select id="cbColumns" tt="column" onchange="resetIntervals(event)" class="text ui-widget-content ui-corner-all"></select>
                            <button tt="column" class='showintervals'></button>
                            <div id="columnIntervals" class="ui-corner-all ui-widget-content" style="margin:0.2em;padding:0.5em;display: none;">Intervals</div>
                        </div>
                    </div>
                    <div>
                        <div class="header"><label for="cbRows">Rows</label></div>
                        <div class="input-cell"><select id="cbRows" tt="row" onchange="resetIntervals(event)" class="text ui-widget-content ui-corner-all"></select>
                            <button tt='row' class='showintervals'></button>
                            <div id="rowIntervals" class="ui-corner-all ui-widget-content" style="margin:0.2em;padding:0.5em;display: none;">Intervals</div>
                        </div>
                    </div>
                    <div>
                        <div class="header"><label for="cbPages">Pages/groups</label></div>
                        <div class="input-cell"><select id="cbPages" tt="page" onchange="resetIntervals(event)" class="text ui-widget-content ui-corner-all"></select>
                            <button tt='page' class='showintervals'></button>
                            <div id="pageIntervals" class="ui-corner-all ui-widget-content" style="margin:0.2em;padding:0.5em;display: none;">Intervals</div>
                        </div>
                    </div>
                    <div>
                        <div class="header"><label for="rbValues">Values</label></div>
                        <div class="input-cell">
                            <input type="radio" checked="" value="count" name="rbValues">Count

                            <input type="radio" value="sum" name="rbValues">Sum

                            <input type="radio" value="avg" name="rbValues">Average</div>
                    </div>

                    <div>
                        <div class="header"><label for="rbShowValue">Show</label></div>
                        <div class="input-cell">
                            <input type="checkbox" checked="" id="rbShowValue">Value

                            <input type="checkbox" id="rbShowPercent">Percent

                            <input type="checkbox" id="rbShowTotals">Totals</div>
                    </div>

                    <div>
                        <button onclick="doCalc()">Apply</button>
                    </div>
                </fieldset>
            </div>
            <div class="output-content ui-corner-all ui-widget-content" style="display:none;">
            </div>
        </div>
    </body>
</html>
