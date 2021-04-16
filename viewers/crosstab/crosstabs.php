<?php
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
 *   Corsstabs analysis UI
 *
 * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
 * @copyright   (C) 2005-2020 University of Sydney
 * @link        http://HeuristNetwork.org
 * @version     3.1.0
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @package     Heurist academic knowledge management system
 */

define('PDIR', '../../');  //need for proper path to js and css
require_once(dirname(__FILE__) . '/../../hclient/framecontent/initPage.php');
?>
<link rel="stylesheet" type="text/css" href="DataTables/datatables.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-eOJMYsd53ii+scO/bJGFsiCZc+5NDVN2yr8+0RDqr0Ql0h+rP48ckxlpbzKgwra6" crossorigin="anonymous">
<script type="text/javascript" src="<?php echo PDIR; ?>hclient/widgets/entity/configEntity.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js" integrity="sha384-JEW9xMcG8R+pH31jmWH6WWP0WintQrMb4s7ZOdauHnUtxwoG2vI5DkLtS3qm9Ekf" crossorigin="anonymous"></script>
<script type="text/javascript" charset="utf8" src="DataTables/datatables.js"></script>

<script type="text/javascript">
    var mapping, menu_datasets, btn_datasets;

    // Callback function on page initialization - see initPage.php
    function onPageInit(success) {
        //database, query q, domain w
        crosstabsAnalysis = CrosstabsAnalysis('', '');
        //
    }
    //Used to open the crosstabs section to the full width on the initial load.
    window.hWin.HAPI4.LayoutMgr.cardinalPanel('sizePane',
        ['east', (top ? top.innerWidth : window.innerWidth)]);
</script>

<link rel=stylesheet href="crosstabs.css" media="all">
</head>

<body style="padding:5px;overflow-x:auto;" class="popup">

    <script type="text/javascript" src="crosstabs.js"></script>

    <div style="margin:0px auto; padding: 0.5em; height: 100%;overflow: auto;">
        <div class="container-fluid">
            <!-- Page container -->
            <div class="row">
                <div class="col-12 mb-2" id="topContainer">
                    <div id="qform" class="disign-content" style="width:100%;">
                        <!-- <div style="position: absolute;top:20px;left:450px;width:200px"><img src="crosstabs_image.png"/></div> -->
                        <div class="row">
                            <div class="col-12 col-md-4 border border-info">
                                <fieldset>
                                    <!-- Dataset selection -->
                                    <div class="row">
                                        <div class="col-3">
                                            <!-- <label for="cbRectypes"> -->
                                            Show fields for
                                            <!-- </label> -->
                                        </div>
                                        <!-- <div class="input-cell col-9">
                            <select id="cbRectypes" class="text ui-widget-content ui-corner-all"></select>
                        </div> -->
                                        <div class="col-8">
                                            <select id="cbRectypes" class="form-input ui-widget-content ui-corner-all"></select>
                                        </div>
                                        <div class="col-12" style="margin-top:1rem">
                                            <div style="font-size: 0.9em;">Note: choice of record type determines the list of fields avaiable but does not filter the results - the analysis is based on all records in the result set</div>
                                        </div>
                                    </div>
                                    <div style="padding: 0.2em; width: 100%; display:none;" class="input" id="divLoadSettings">
                                        <!--
                        <div class="header" style="padding: 0 16px 0 16px;">
                            <label for="sel_saved_settings">Saved settings:</label>
                        </div>
                        <select id="sel_saved_settings" class="text ui-widget-content ui-corner-all" style="max-width:30em"></select> -->
                                    </div>
                                </fieldset>
                                <div class="row" id="divSaveSettings">

                                </div>
                                <!-- <div style="width: 100%;" class="input" id="divSaveSettings"> -->
                                                <!-- <div class="header" style="padding: 0 16px 0 16px;">
                            <label>Save settings for future use</label>
                        </div>
                        &nbsp;&nbsp;<b>Name</b>&nbsp;
                        <input id="inpt_save_setting_name" class="text ui-widget-content ui-corner-all" style="max-width:30em"/>
                        &nbsp;&nbsp;<button id="btnSaveSettings">Save</button> -->
                                            <!-- </div> -->
                            </div>
                            <div class="col-12 col-md-4 border border-danger">
                                <div class="row">
                                    <div class="col-12">
                                        <fieldset id="vars" style="display:none;">
                                            <div>
                                                <div class="fldheader"><label for="cbRows">Var 1 (rows)</label></div>
                                                <div class="input-cell">
                                                    <select id="cbRows" name="row" onchange="crosstabsAnalysis.resetIntervals(event); crosstabsAnalysis.OnRowTypeChange(this);" class="text ui-widget-content ui-corner-all" style="display: inline-block;"></select>
                                                    <button tt='row' class='showintervals collapsed'></button>
                                                    <div id="rowIntervals" class="ui-corner-all ui-widget-content crosstab-interval">Select field to set intervals</div>
                                                </div>
                                            </div>
                                            <div style="height:2em">&nbsp;</div>
                                            <div>
                                                <div class="fldheader"><label for="cbColumns">Var 2 (columns)</label></div>
                                                <div class="input-cell">
                                                    <select id="cbColumns" name="column" onchange="crosstabsAnalysis.resetIntervals(event)" class="text ui-widget-content ui-corner-all"></select>
                                                    <button tt="column" class='showintervals collapsed'></button>
                                                    <div id="columnIntervals" class="ui-corner-all ui-widget-content crosstab-interval">Select field to set intervals</div>
                                                </div>
                                            </div>
                                            <div style="height:2em">&nbsp;</div>
                                            <div>
                                                <div class="fldheader"><label for="cbPages">Var3 (pages)</label></div>
                                                <div class="input-cell"><select id="cbPages" name="page" onchange="crosstabsAnalysis.resetIntervals(event)" class="text ui-widget-content ui-corner-all"></select>
                                                    <button tt='page' class='showintervals collapsed'></button>
                                                    <div id="pageIntervals" class="ui-corner-all ui-widget-content crosstab-interval">Select field to set intervals</div>
                                                </div>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4 border border-dark">
                                <div class="row">
                                    <div class="col-12">
                                        <fieldset id="shows" style="display:none;">
                                            <span style="float:left; width:47.5%">
                                                <div style="text-align: center; padding-top: 19px; font-size: 20px; display: block;"><label for="aggregationMode">Values:</label></div>
                                                <div class="radioDiv">
                                                    <div class="input-cell" style="padding-top: 4px;">

                                                        <div class="crosstab-aggregation">
                                                            <input type="radio" class="btn-check" checked value="count" id="aggregationModeCount" name="aggregationMode" onchange="crosstabsAnalysis.changeAggregationMode()">
                                                            <label class="btn btn-outline-secondary" for="aggregationModeCount">Counts</label>
                                                        </div>

                                                        <div id="aggSum" class="crosstab-aggregation">
                                                            <input type="radio" class="btn-check" id="aggregationModeSum" value="sum" name="aggregationMode" onchange="crosstabsAnalysis.changeAggregationMode()">
                                                            <label class="btn btn-outline-secondary" for="aggregationModeSum">Sum</label>
                                                        </div>

                                                        <div id="aggAvg" class="crosstab-aggregation">
                                                            <input type="radio" class="btn-check" id="aggregationModeAvg" value="avg" name="aggregationMode" onchange="crosstabsAnalysis.changeAggregationMode()">
                                                            <label class="btn btn-outline-secondary" for="aggregationModeAvg">Average</labels>
                                                        </div>

                                                        <div id="divAggField" class="crosstab-aggregation" style="margin-top:10px;">of&nbsp;<select id="cbAggField" name="column" onchange="crosstabsAnalysis.changeAggregationMode()" class="text ui-widget-content ui-corner-all"></select></div>
                                                    </div>
                                                </div>
                                            </span>
                                            <span class="verticalLine"></span>
                                            <span style="float:left; width: 23.75%">
                                                <div style="text-align: center; padding-top: 19px; display:block; font-size: 20px;"><label for="rbShowValue">Show:</label></div>
                                                <div class="input-cell">
                                                    <div class="checkboxValues">
                                                        <input type="checkbox" class="btn-check" onchange="crosstabsAnalysis.doRender()" style="margin-left: 0;" checked id="rbShowValue">
                                                        <label class="btn btn-outline-secondary" for="rbShowValue">Values</label>
                                                    </div>

                                                    <div class="checkboxValues">
                                                        <input type="checkbox" class="btn-check" onchange="crosstabsAnalysis.doRender()" id="rbShowPercentRow">
                                                        <label class="btn btn-outline-secondary" for="rbShowPercentRow">Row %</label>
                                                    </div>

                                                    <div class="checkboxValues">
                                                        <input type="checkbox" class="btn-check" onchange="crosstabsAnalysis.doRender()" id="rbShowPercentColumn">
                                                        <label class="btn btn-outline-secondary" for="rbShowPercentColumn">Column %</label>
                                                    </div>

                                                    <div class="checkboxValues">
                                                        <input type="checkbox" class="btn-check" onchange="crosstabsAnalysis.doRender()" checked id="rbShowTotals">
                                                        <label class="btn btn-outline-secondary" for="rbShowTotals">Totals</label>
                                                    </div>
                                                </div>
                                            </span>
                                            <span class="blankCheckboxesSpan" style="float: right; width: 23.75%;">
                                                <div class="fldheader"></div>
                                                <div class="input-cell" style="padding-top: 10px;">
                                                    <div class="blankCheckboxes">
                                                        <input type="checkbox" class="btn-check" onchange="crosstabsAnalysis.doRender()" checked id="rbSupressZero">
                                                        <label class="btn btn-outline-secondary" for="rbSupressZero">blank for zero values</label>
                                                    </div>

                                                    <div class="blankCheckboxes">
                                                        <input type="checkbox" class="btn-check" onchange="crosstabsAnalysis.doRender()" id="rbShowBlanks">
                                                        <label class="btn btn-outline-secondary" for="rbShowBlanks">show blank rows/columns</label>
                                                    </div>
                                                </div>
                                            </span>

                                            

                                    </div>
                                </div>
                            </div>
                        </div>


                        <div style="height:2em">&nbsp;</div>

                        <div id="nofields" style="padding-left:180px; font-weight:bold;">
                            Select record type.
                        </div>

                        <div style="border-color: black; border-style: solid; border-width:3px; padding-left: 5px;">

                        </div>

                        </fieldset>
                        <div style="text-align:center;padding-top:1em; padding-bottom:1em; display:none;" id="btnPanels">
                            <button id="btnUpdate" onclick="crosstabsAnalysis.doRetrieve()" style="font-size:larger;font-weight:bold">Update results</button>
                            <!--
                    <button onclick="crosstabsAnalysis.doSave()">Save specification</button>
                    <button id="btnCancel" onclick="crosstabsAnalysis.doCancel()">Cancel</button>
                    <button id="btnPrint" onclick="crosstabsAnalysis.doPrint()">Print results</button>
                    -->
                        </div>
                    </div>
                </div>
                <div class="col-12 mb-2" id="bottomContainer">
                    <!-- Datables goes here!!! -->
                </div>
            </div>
        </div>


        <div id="inporgress" style="display:none;">
            <div id="inporgress-loading"></div>
            <div id="pmessage" class="progress-message">Requesting</div>
        </div>

        <!-- Adding modal to crosstabs 
            <div class="col text-center">
                <button id="modalButton" class="btn btn-success btn-lg" data-toggle="modal" data-target="#modalDisplay" disabled>View Analysis</button>
            </div>
        -->

        <div id="divres" class="output-content" style="display:none;">
        </div>


        <div id="div_empty" class="output-content" style="color:red;font-weight:bold;display:none;">
            Please apply a filter to create a result set
        </div>
    </div>


</body>

</html>