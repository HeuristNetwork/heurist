
/**
* manageReports.js
* ReportManager object for listing and searching of scheduled report
* @todo change to entity manager
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

var reportManager;


/**
* ReportManager - class for listing and searching of scheduled reports
*
* @param _isFilterMode - either select from all reports or filtering of existing set of reports (NOT USED)
* @param _isWindowMode - true in window popup, false in div

* @author Artem Osmakov <osmakov@gmail.com>
* @version 2012.0426
*/
function ReportManager(_isFilterMode, _isWindowMode) {

		var _className = "ReportManager",
            _dataTable,
            _dataTableParams,
            
			_myDataTable,
			_myDataSource,
            _isSelection = false,
			_usrID, //filter - @todo to show only reports that belongs to this user
			_arr_selection = [],
			_callback_func, //callback function for non-window mode
			_isSingleSelection = false,
			_records, //array of all reports from server
			_keepParameters = null;

	/**
	* Initialization of form
	*
	* 1. Reads GET parameters
	* 2. create and fill table of reports
	* 3. fille selector for type of groups
	* 4. assign listeners for filter UI controls
	*/
	function _init(usrID, _callback)
	{
		_callback_func = _callback;

		_keepParameters = null;

				if (window.hWin.HEURIST4.util.isnull(usrID) && location.search.length > 1) { //for selection mode
					
					//list of selected
					var sIDs = window.hWin.HEURIST4.util.getUrlParameter('ids', location.search);
					if (sIDs) {
							_arr_selection = sIDs.split(',');
					}

					if(!(window.hWin.HEURIST4.util.isempty(
                                window.hWin.HEURIST4.util.getUrlParameter('hquery', location.search)) 
                        || window.hWin.HEURIST4.util.isempty(
                                window.hWin.HEURIST4.util.getUrlParameter('template', location.search))))
					{
						_keepParameters = location.search;
						//auto open _onAddEditRecord(_keepParameters);
					}
				}

				_usrID = usrID; //filter - show only this user reports - NOT USED

                _refreshReports();
	}


	/**
	* Creates and (re)fill datatable
	*/
    function _initDataTable( dataSet ){

        if(!(dataSet && dataSet.length>0)){
            $('#tb_bottom').hide(); // hide second 'create schedule' button
            return;
        }
        $('#tb_bottom').show(); // show second 'create schedule' button

        if(_dataTable!=null){
            _dataTable.destroy();
            _dataTable = null;
            $('.div_datatable').empty();
        }
        
        _dataTableParams = {
            //scrollCollapse:true,
            //scrollX: false,
            //scrollY: true,
            autoWidth: false,
            //initComplete: _onDataTableInitComplete,
            dom:'fip',
            pageLength: 20,
            ordering: true,
            processing: false,
            serverSide: false,
            data: dataSet,
            columns: null
        };
        
        _dataTableParams['columns'] = [
            { data: "selection", title: "Sel", visible:_isSelection, sortable:true }, //checkbox

            { data: 'status',
                title: "<div style='font-size:10;'>Status</div>", visible:true, sortable:true, className:'center', width:16,
                render: function(data, type) {
                    if (type === 'display') {
                        
                        if(data>0){
                            var simg, sfont='', shint='';
                            if(data==1){
                                simg = 'url_error.png';
                                shint = 'template file does not exsist';
                                sfont = 'style="color:red"';
                            }else if(data==2){
                                simg = 'url_warning.png';
                                shint = 'output folder does not exsist';
                            }else if(data==3){
                                simg = 'url_warning.png';
                                shint = 'generated report is not created yet';
                            }
                            if(shint){
                                return '<span class="ui-icon ui-icon-alert" '+sfont+' title="'+shint+'">';    
                            }else{
                                return '';
                            }
                            
                        }else{
                            return '';
                        }
                    }else{
                        return '';    
                    }
            }},

            { data: 'rps_ID', title: "<div style='max-width:15px;'>#</div>", sortable:true, className:'right', width:16}, //className:'right',resizeable:false},

            { data: 'rps_ID', title: "<div style='font-size:10;'>Edit</div>", sortable:false, width:16, //width:12,resizeable:false,
                render: function(data, type) {
                    if (type === 'display') { //edit_record
                        return '<a href="#" onclick="window.reportManager.editReport('+data+');return false">'
                        +'<span class="ui-icon ui-icon-pencil" title="'+window.hWin.HR('Edit')+'">'
                    }else{
                        return '';    
            }}},

            { data: 'rps_ID', title: "<div style='font-size:10;'>Exec</div>", sortable:false, width:16, //resizeable:false,
                render: function(data, type) {
                    if (type === 'display') {
                        var status = 0; //@todo Number(oRecord.getData('status'));
                        if(status==1){
                            return '';
                        }else{
                            return '<a href="../../viewers/smarty/updateReportOutput.php?db='
                            +window.hWin.HAPI4.database+'&publish=1&id='
                            +data
                            +'" target="_blank">'
                            +'<span class="ui-icon ui-icon-refresh" title="'+window.hWin.HR('Run report')+'">'
                            +'</a>';
                        }
                    }else{
                        return '';    
            }}},

            { data: 'rps_ID', title: "<div style='font-size:10;min-width:30px;'>HTML</div>", sortable:false, width:18, //resizeable:false,
                render: function(data, type) {
                    if (type === 'display') {
                        var status = 0; //@todo Number(oRecord.getData('status'));
                        if(status==1){
                            return '';
                        }else{
                            return '<a href="../../viewers/smarty/updateReportOutput.php?db='
                            +window.hWin.HAPI4.database+'&publish=3&mode=html&id='
                            +data
                            +'" target="_blank">'
                            +'<img src="../../hclient/assets/external_link_16x16.gif" width="16" height="16" border="0" title="HTML link">'
                            +'</a>';
                        }
                    }else{
                        return '';    
            }}},
            
            { data: 'rps_URL', title: "<div style='font-size:10;'>Raw</div>", sortable:false, width:16,  //resizeable:false,width:7,
                render: function(data, type, row) {
                    if (type === 'display') {
                        var status = 0; //@todo Number(oRecord.getData('status'));
                        if(status==1){
                            return '';
                        }else{
                            let outputmode = data?data:'txt';
                            return '<a href="../../viewers/smarty/updateReportOutput.php?db='
                            +window.hWin.HAPI4.database+'&publish=3&mode='+outputmode+'&id='
                            +row.rps_ID+'" target="_blank">'+outputmode.toUpperCase()
                            +'&nbsp;<img src="../../hclient/assets/external_link_16x16.gif" width="16" height="16" border="0" title="JavaScript link"></a>';
                        }
                    }else{
                        return '';    
            }}},


            { data: "rps_Title", title: "Title", sortable:true, resizeable:true},
            { data: "rps_HQuery", title: "Query", sortable:false, resizeable:true, 
                render: function(data, type) {
                    if (type === 'display') {
                        return "<div style='max-width:400px;overflow: hidden;white-space: nowrap;text-overflow: ellipsis;'>"+data+"</div>";
                    }else{
                        return '';    
                    }
            }},
            { data: "rps_IntervalMinutes", title: "Interval", sortable:true, resizeable:false},

            { data: 'rps_ID', title: "Del", className:'center', sortable:false,
                render: function(data, type) {
                    if (type === 'display') {
return '<div align="center" data-id="'+data+'">'
+'<a href="#" onclick="window.reportManager.deleteReport('+data+');return false;">'
+'<span class="ui-icon ui-icon-close" title="'+window.hWin.HR('Delete this Report')+'"></span><\/a></div>';
                    }else{
                        return '';    
                    }
                }
            }
        ];

        _dataTable = $('.div_datatable').DataTable( _dataTableParams );

        $('.dataTables_filter').css({float:'left'});
    }

	/**
	* Opens popup to add/edit record
	*/
	function _onAddEditRecord(params){

		var url = window.hWin.HAPI4.baseURL + "export/publish/editReportSchedule.html";
		if(!window.hWin.HEURIST4.util.isempty(params)){
			url = url + params;
		}

		window.hWin.HEURIST4.msg.showDialog( url, {
		    "close-on-blur": false,
			"no-resize": false,
			height: 440,
			width: 620,
			callback: function(context) {
				if(!window.hWin.HEURIST4.util.isnull(context)){

					//update id
					var recID = Math.abs(Number(context.data[0]));

					//refresh table
					_refreshReports();

				}
			}
		});
	}
    
    function _onDeleteRecord(recID){
        
                        window.hWin.HEURIST4.msg.showMsgDlg(
                            "Do you really want to delete report schedule?",
                            function(){ 
                                function _updateAfterDelete(response) {
                                    if(response.status == window.hWin.ResponseStatus.OK){

                                            
                                            $('.div_datatable').find('div[data-id='+recID+']').parents('tr').addClass('selected');
                                            
                                            _dataTable.row('.selected').remove().draw( false );
                                            
                                            window.hWin.HEURIST4.msg.showMsgFlash(
                                                "Report schedule #"+recID+" was deleted",1000);
                                    }else{
                                        window.hWin.HEURIST4.msg.showMsgErr(response);
                                    }
                                }

                                var baseurl = window.hWin.HAPI4.baseURL + "export/publish/loadReports.php";
                                var callback = _updateAfterDelete;
                                var request = {method:'deletereport', db:window.hWin.HAPI4.database, recID:recID};
                                window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, callback);
                            }, 
                            {title:'Confirm',yes:'Continue',no:'Cancel'});
        
    }
    
    //
    //
    //
    function _refreshReports() {

                /**
                * Result handler for search on server
                */
                function __updateRecordsList(response )
                {
                    if(response.status == window.hWin.ResponseStatus.OK){
                        _records = window.hWin.HEURIST4.util.isnull(response.data)?[]:response.data;
                        _initDataTable( _records );
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                };

                var baseurl = window.hWin.HAPI4.baseURL + "export/publish/loadReports.php";
                var request = {method:'searchreports', db:window.hWin.HAPI4.database};
                window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, __updateRecordsList);
    };    

	//
	//public members
	//
	var that = {

				/**
				* Reinitialization of form for new detailtype
				* @param usrID - detail type id to work with
				* @param _callback - callback function that obtain 3 parameters all terms, disabled terms and usrID
				*/
				reinit : function (usrID, _callback) {
						_init(usrID, _callback);
				},

				/**
				 * Cancel form - closes this window (NOT USED HERE)
				 */
				cancel : function () {
					if(_isWindowMode){
						window.close();
					}else if (!window.hWin.HEURIST4.util.isnull(_callback_func) ) {
						_callback_func();
					}
				},

				editReport: function(recID){
					_onAddEditRecord((recID<0 && _keepParameters)
                        ?_keepParameters
                        :"?db="+window.hWin.HAPI4.database+"&recID="+recID);
				},
                
                deleteReport: function(recID){
                    _onDeleteRecord(recID);
                },

				getClass: function () {
					return _className;
				},

				isA: function (strClass) {
					return (strClass === _className);
				}

	};

	_init();  // initialize before returning
	return that;

}