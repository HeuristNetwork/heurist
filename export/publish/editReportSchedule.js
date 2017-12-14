/**
* editReportSchedule.js
* A form to edit report schedules, or create a new one It is utilized as pop-up from manageReports
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


//aliases
var Dom = YAHOO.util.Dom,
    Hul = top.HEURIST.util;

/**
* ReportScheduleEditor - class for pop-up edit report schedules
*
* public methods
*
* save - sends data to server and closes the pop-up window in case of success
* cancel - checks if changes were made, shows warning and closes the window
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2011.0427
*/

function ReportScheduleEditor() {

        var _className = "ReportScheduleEditor",
            _entity, //object (report schedule) to edit
            _recID,     // its ID
            _updatedFields = [], //field names which values were changed to be sent to server
            _updatedDetails = [], //field values
            _db;

    /**
    * Initialization of input form
    */
    function _init() {

        var typeID = "smarty",
            templatefile = '',
            qlabel = '',
            hquery = '';


        _db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));

        // reads parameters from GET request
        if (location.search.length > 1) {
                top.HEURIST.parameters = top.HEURIST.parseParams(location.search);
                _recID = top.HEURIST.parameters.recID;

                typeID = top.HEURIST.parameters.typeID;
                templatefile = top.HEURIST.parameters.template;
                hquery = top.HEURIST.parameters.hquery;

                if(Number(_recID)>0){
                    _entity = top.HEURIST.reports.records[_recID];
                }
                if(Hul.isnull(typeID)){
                    typeID = "smarty";
                }
                if(Hul.isnull(hquery)){
                    hquery = '';
                }else{
                    var hparams = top.HEURIST.parameters = top.HEURIST.parseParams(hquery);
                    if(!Hul.isnull(hparams.label)){
                        qlabel = hparams.label;
                    }
                }
                if(Hul.isnull(templatefile)){
                    templatefile = '';
                }
        }

        if (Number(_recID>0) && Hul.isnull(_entity) ){
            Dom.get("statusMsg").innerHTML = "<strong>Error: Report Schedule #"+_recID+"  was not found. Clicking 'save' button will create a new Schedule.</strong><br /><br />";
        }
        //creates new empty field type in case ID is not defined
        if(Hul.isnull(_entity)){
            _recID =  -1;
            //"rps_ID", "rps_Type", "rps_Title", "rps_FilePath", "rps_URL", "rps_FileName", "rps_HQuery", "rps_Template", "rps_IntervalMinutes"
            _entity = [-1,typeID,qlabel,'','','',hquery,templatefile,1440]; //interval 1 day
        }
        
        Dom.get('rps_Title').onchange = function(event){
            Dom.get('rps_FileName').value = top.HEURIST.util.cleanFilename(event.target.value);
        }

        _reload_templates();

    }

    /**
    *  show the list of available reports
    *  #todo - filter based on record types in result set
    */
    function _updateTemplatesList(context) {

            var sel = document.getElementById('rps_Template'),
                option,
                keepSelIndex = sel.selectedIndex;

            //celear selection list
            while (sel.length>0){
                    sel.remove(0);
            }

            if(!Hul.isnull(context) && context.length>0){
                for (i in context){
                    if(i!==undefined){
                        Hul.addoption(sel, context[i].filename, context[i].name);
                    }
                } // for

                sel.selectedIndex = (keepSelIndex<0)?0:keepSelIndex;
            }

            //fills input with values from _entity array
            _fromArrayToUI();
    }
    /**
    * loads list of templates
    */
    function _reload_templates(){
                var baseurl = top.HEURIST.baseURL + "viewers/smarty/templateOperations.php";
                var callback = _updateTemplatesList;
                Hul.getJsonData(baseurl, callback, 'db='+_db+'&mode=list');
    }



    /**
    * Fills inputs with values from _entity array
    */
    function _fromArrayToUI(){

        var i,
            el,
            fnames = top.HEURIST.reports.fieldNames;

        for (i = 0, l = fnames.length; i < l; i++) {
            var fname = fnames[i];
            el = Dom.get(fname);
            if(!Hul.isnull(el)){
                el.value = _entity[i];
            }
        }

        if (_recID<0){
            Dom.get("rps_ID").innerHTML = 'to be generated';
            document.title = "Create New Report Schedule";
        }else{
            Dom.get("rps_ID").innerHTML =  _recID;
            document.title = "Report Schedule #: " + _recID+" '"+_entity[2]+"'";

            Dom.get("statusMsg").innerHTML = "";
        }
        //set interval to 1440 (1 day) in case not defined
        var interval = Dom.get("rps_IntervalMinutes").value;
        if(Hul.isempty(interval) || isNaN(parseInt(interval)) || parseInt(interval)<1){
            Dom.get("rps_IntervalMinutes").value = 1440;            
        }

    }


    /**
    * Stores the changed values and verifies mandatory fields
    *
    * Compares data in input with values and in _entity array, then
    * gathers changed values from UI elements (inputs) into 2 arrays _updatedFields and _updatedDetails
    * this function is invoked in 2 places:
    * 1) in cancel method - to check if something was changed and show warning
    * 2) in save (_updateOnServer) - to gather the data to send to server
    *
    * @param isShowWarn - show alert about empty mandatory fields, it is false for cancel
    * @return "mandatory" in case there are empty mandatory fields (it prevents further saving on server)
    *           or "ok" if all mandatory fields are filled
    */
    function _fromUItoArray(isShowWarn){

        _updatedFields = [];
        _updatedDetails = [];

        var interval = Dom.get("rps_IntervalMinutes").value;
        if(Hul.isempty(interval) || isNaN(parseInt(interval)) || parseInt(interval)<1){
            Dom.get("rps_IntervalMinutes").value = 1440;            
        }
        
        var i,
            fnames = top.HEURIST.reports.fieldNames;

        //take only changed values
        for (i = 0, l = fnames.length; i < l; i++){
            var fname = fnames[i];
            el = Dom.get(fname);
            if( !Hul.isnull(el) && fname!='rps_ID' ){
                if(_recID<0 || (el.value!==String(_entity[i]) && !(el.value==="" && _entity[i]===null)))
                {
                    // DEBUG alert(el.value+" "+String(_entity[i]));
                    _updatedFields.push(fname);
                    _updatedDetails.push(el.value);
                }
                if(Hul.isempty(el.value) && !(fname==='rps_FilePath' || fname==='rps_URL' || fname==='rps_IntervalMinutes') ) {
                    if(isShowWarn) {
                        alert(fname.substr(4)+" is a mandatory field");
                    }
                    el.focus();
                    _updatedFields = [];
                    return "mandatory";
                }
            }
        }

        return "ok";
    }

    /**
    * Http responce listener
    *
    * shows information about result of operation of saving on server and closes this pop-up window in case of success
    *
    * @param context - data from server
    */
    function _updateResult(context) {

        if(!Hul.isnull(context)){

            var error = false,
                report = "",
                ind;

            for(ind in context.result){
                if( !Hul.isnull(ind) ){
                    var item = context.result[ind];
                    if(isNaN(item)){
                        Hul.showError(item);
                        error = true;
                    }else{
                        _recID = Number(item);
                        if(report!=="") {
                            report = report + ",";
                        }
                        report = report + Math.abs(_recID);
                    }
                }
            }

            if(!error){
                var ss = (_recID < 0)?"added":"updated";

                // this alert is a pain  alert("Report schedule with ID " + report + " was succesfully "+ss);
                window.close(context); //send back new HEURIST strcuture
            }
        }
    }

    /**
    * Apply form
    * private method for public method "save"
    * 1. gather changed data from UI (_fromUItoArray) to _updatedFields, _updatedDetails
    * 2. creates object to be sent to server
    * 3. sends data to server
    */
    function _updateOnServer()
    {

        //1. gather changed data
        if(_fromUItoArray(true)==="mandatory"){ //save all changes
            return;
        }

        var str = null;

        //2. creates object to be sent to server
        if(_recID !== null && _updatedFields.length > 0){
            var k,
                val;
            var oDataToServer = {report:{
                colNames:[],
                defs: {}
            }};

            var values = [];
            for(k = 0; k < _updatedFields.length; k++) {
                oDataToServer.report.colNames.push(_updatedFields[k]);
                values.push(_updatedDetails[k]);
            }


            oDataToServer.report.defs[_recID] = [];
            for(val in values) {
                oDataToServer.report.defs[_recID].push(values[val]);
            }
            str = YAHOO.lang.JSON.stringify(oDataToServer);
        }


        if(!Hul.isempty(str)) {
//DEBUG alert("Stringified changes: " + str);

            // 3. sends data to server
            var baseurl = top.HEURIST.baseURL + "export/publish/loadReports.php";
            var callback = _updateResult;
            var params = "method=savereport&db=" + _db + "&data=" + encodeURIComponent(str);
            Hul.getJsonData(baseurl, callback, params);
        } else {
            window.close(null);
        }
    }

    //public members
    var that = {

            /**
             *    Apply form - sends data to server and closes this pop-up window in case of success
             */
            save : function () {
                _updateOnServer();
            },

            /**
             * Cancel form - checks if changes were made, shows warning and closes the window
             */
            cancel : function () {
                _fromUItoArray(false);
                if(_updatedFields.length > 0) {
                    var areYouSure = confirm("Changes were made. By cancelling, all changes will be lost. Are you sure?");
                    if(areYouSure) {
                        window.close(null);
                    }
                }else{
                    window.close(null);
                }
            },

            getClass: function () {
                return _className;
            },

            isA: function (strClass) {
                return (strClass === _className);
        }

    };

    _init();
    return that;
}