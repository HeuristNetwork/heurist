/*
* Copyright (C) 2005-2023 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

function switchDev (toAsym) {
	if (toAsym) {
		$("#DEV")[0].disabled = true;
		$("#DVP")[0].disabled = false;
		$("#DVN")[0].disabled = false;
		$("input[name=stdDEV][value=asym]").attr("checked","checked");
		$("input[name=stdDEV][value=sym]").attr("checked","");
	}else{
		$("#DEV")[0].disabled = false;
		$("#DVP")[0].disabled = true;
		$("#DVN")[0].disabled = true;
		$("input[name=stdDEV][value=asym]").attr("checked","");
		$("input[name=stdDEV][value=sym]").attr("checked","checked");
	}
}

//
// show alternative calendar
//
function calendarPopup(buttonElt) {

	var callback =	function(date)
	{
		if (date) {
			document.getElementById("simpleDate").value = date;
            
			if(window.hWin && window.hWin.HAPI4) window.hWin.HAPI4.save_pref("record-edit-date", date);
			calendarViewer.close();
		}
	}
	var date = document.getElementById("simpleDate").value;
    
	if(window.hWin.HEURIST4.util.isempty(date) && window.hWin && window.hWin.HAPI4){
		date = window.hWin.HAPI4.get_prefs_def('record-edit-date','');
    }

    calendarViewer.showAt(getOffset(buttonElt), date, callback);
}

function getOffset(obj) {

        var x = y = 0;
        var sleft = 0;//obj.ownerDocument.body.scrollLeft;
        var stop = 0; //obj.ownerDocument.body.scrollTop;
        while (obj) {
            x += obj.offsetLeft;
            y += obj.offsetTop;
            obj = obj.offsetParent;
        }
        return [x-sleft, y-stop];
}


function setPDBtoTPQ () {
	var tpq = document.getElementById("TPQ");
	var pdb = document.getElementById("PDB");
	if (typeof tpq == "object" && typeof pdb == "object" && tpq.value) {
		pdb.value = tpq.value
	}
}

function setPDEtoTAQ () {
	var taq = document.getElementById("TAQ");
	var pde = document.getElementById("PDE");
	if (typeof taq == "object" && typeof pde == "object" && taq.value) {
		pde.value = taq.value
	}
}

function c14DevClick(id) {
	if($("#"+id).attr("tabindex")> -1) return true; //if input is in tab path then pass click on
	if (id == "DEV"){
		$("#DEV").attr("tabindex","2");
		$("#DEV").val($("#DVP").val());
		$("#DVP").val("");
		$("#DVN").val("");
		$("#DVP").attr("tabindex","-1");
		$("#DVN").attr("tabindex","-1");
	}else{
		$("#DEV").attr("tabindex","-1");
		$("#DVP").attr("tabindex","2");
		$("#DVP").val($("#DEV").val());
		$("#DVN").val($("#DEV").val());
		$("#DEV").val("");
		$("#DVN").attr("tabindex","3");
	}
}

function c14DateClick(id) {
	if($("#"+id).attr("tabindex")> -1) return true; //if input is in tab path then pass click on
	if (id == "BCE"){
		$("#BCE").attr("tabindex","1");
		$("#BCE").val($("#BPD").val());
		$("#BPD").val("");
		$("#BPD").attr("tabindex","-1");
	}else{
		$("#BPD").attr("tabindex","1");
		$("#BPD").val($("#BCE").val());
		$("#BCE").val("");
		$("#BCE").attr("tabindex","-1");
	}
}

//
// not used - replaced to image
/*
function dRangeDraw() {

	var canvas = document.getElementById("dRangeCanvas");
	if (canvas.getContext) {
		var ctx = canvas.getContext("2d");

		ctx.fillStyle = "rgb(150,150,150)";
		ctx.fillRect (135, 0, 260, 60);
		var lingrad = ctx.createLinearGradient(75,0,135,0);
		lingrad.addColorStop(0, 'rgba(0,0,150,0)');
		lingrad.addColorStop(0.5, 'rgba(75,75,150,0.5)');
		lingrad.addColorStop(1, 'rgba(150,150,150,0.9)');
		var lingrad2 = ctx.createLinearGradient(455,0,395,0);
		lingrad2.addColorStop(0, 'rgba(0,0,150,0)');
		lingrad2.addColorStop(0.5, 'rgba(75,75,150,0.5)');
		lingrad2.addColorStop(1, 'rgba(150,150,150,0.9)');

		ctx.fillStyle = lingrad;
		ctx.fillRect (75, 0, 60, 60);
		ctx.fillStyle = lingrad2;
		ctx.fillRect (395, 0, 60, 60);
		ctx.beginPath();
		ctx.fillStyle = "rgba(0, 0, 200, 0.2)";
		ctx.lineWidth = 5;
		ctx.strokeStyle = "#808080";
		ctx.moveTo(0,62.5);
		ctx.lineTo(530,62.5);
		ctx.moveTo(75.5,0);
		ctx.lineTo(75.5,62.5);
		ctx.moveTo(135.5,0);
		ctx.lineTo(135.5,62.5);
		ctx.moveTo(395.5,0);
		ctx.lineTo(395.5,62.5);
		ctx.moveTo(455.5,0);
		ctx.lineTo(455.5,62.5);
		ctx.stroke();
		ctx.beginPath();
		ctx.fillStyle = "rgb(50,50,50)";
		ctx.font = "8pt Arial";
		drawLabel(ctx,"TPQ",75,80,20);
		drawLabel(ctx,"PB",135,80,20);
		drawLabel(ctx,"PE",395,80,20);
		drawLabel(ctx,"TAQ",455,80,20);
		ctx.fillStyle = "rgb(250,250,250)";
		ctx.font = "12pt Arial Bold";
		drawLabel(ctx,"likely range",265,40,60);
		ctx.stroke();
	}
}
*/

function drawLabel ( ctx, label, xPos, yPos, maxX) {
	var pxLen = ctx.measureText(label).width;
	ctx.fillText(label, xPos - pxLen/2, yPos, maxX);
}

var TemporalPopup = (function () {
	//private members
	var _className = "Applet";  // I know this is a singleton and the application object, but hey it matches the pattern.
	var _type2TabIndexMap = {};

	var _change_tab_only = false;

	function _init () {
		if (location.search.length > 1) {		// the calling app passed a parameter string - save it
			that.originalInputString = unescape(location.search.substring(1));
		}
		if ( Temporal.isValidFormat(that.originalInputString)) {
			try {
				that.origTemporal = Temporal.parse(that.originalInputString);
			}
			catch (e) {
				return "Error creating temporal from valid input string : " + that.originalInputString + " : " + e;
			}
		} else if (that.originalInputString) { // non temporal non empty string
			try {
					var tDate = TDate.parse(that.originalInputString);
					that.origTemporal = new Temporal();
					that.origTemporal.setType("s");  // simple date
					that.origTemporal.setTDate("DAT",tDate);
					that.origTemporal.setField("COM",that.originalInputString);
				}
				catch(e) { // unknown string
					that.origTemporal = new Temporal();
					that.origTemporal.setType("s");  // simple date with no date
					that.origTemporal.setField("COM",that.originalInputString);
				}
		} else { // empty string
			that.origTemporal = new Temporal();
			that.origTemporal.setType("s");  // simple date with no data
		}
		// set current temporal to original
		try {
			that.curTemporal = Temporal.cloneObj(that.origTemporal);
		}
		catch(e) {
			that.curTemporal = new Temporal();
			that.curTemporal.setType("s");  // simple date with no date
			that.curTemporal.setField("COM",that.originalInputString);
		}
		// set display
        $('#display-div').tabs({
        	beforeActivate: function( event, ui ) {

	            var curType = ui.oldPanel.attr('id'),
	                newType = ui.newPanel.attr('id');

	            _updateUIFromTemporal(that.curTemporal, false); //do not dates
	            _updateGeorgianDate();

	            if(!_change_tab_only){
		            // grab all the data from the current tab using the
		            _updateTemporalFromUI(that.curTemporal, false);
		            that.curTemporal.setType(newType);
	            }else{
					_change_tab_only = false;
					return false;

				}

	            //move tab_note to new tab
	            ui.newPanel.prepend($('#tab_note'));
	        },
	        activate: function(event, ui){
	        	let newType = ui.newPanel.attr('id');
	        	if(newType === "f"){
	        		_updateSimpleRange();
	        	}
	        }
    	});
        
        // set up temporal type to tab index mapping
        $.each($('#display-div').find('.display-tab'), function(i,item){
            _type2TabIndexMap[$(item).attr('id')] = i;
        });

        if(that.curTemporal.getType() === "p" && // if only earliest and latest estimates exist, treat as simple range instead of fuzzy range
			window.hWin.HEURIST4.util.isempty(that.curTemporal.getStringForCode('PDB')) && window.hWin.HEURIST4.util.isempty(that.curTemporal.getStringForCode('PDE')) && 
			!window.hWin.HEURIST4.util.isempty(that.curTemporal.getStringForCode('TPQ')) && !window.hWin.HEURIST4.util.isempty(that.curTemporal.getStringForCode('TAQ'))){

			that.curTemporal.setType("f");	
		}

		// select the tab for the initial temporal's type and change the label to show the user this is where things started
        _updateUIFromTemporal(that.curTemporal, true);
        var active_idx = _type2TabIndexMap[ that.curTemporal.getType() ? that.curTemporal.getType():'s' ];
        $('#display-div').tabs('option','active',active_idx);
        
		//dRangeDraw();

        _initJqCalendar(that.curTemporal);

        $(".withCalendarsPicker").change(_updateGeorgianDate);
        _updateGeorgianDate();
        
        
        $('input[value="Save"]').addClass('ui-button-action').button();
        $('input[value="Cancel"]').button();
        

        $('#fTPQ, #fTAQ').blur(_updateSimpleRange).change(function(){
        	const tpq = $('#fTPQ').val();
        	const taq = $('#fTAQ').val();
        	if(!window.hWin.HEURIST4.util.isempty(tpq) && !window.hWin.HEURIST4.util.isempty(taq)){
        		setTimeout(function(){
        			const T_tpq = $('#fTPQ').val();
        			const T_taq = $('#fTAQ').val();
        			if(T_tpq == tpq && T_taq == taq){ // unchanged
        				_updateSimpleRange();
        			}
        		}, 3000);
        	}
        });
	};

	function _updateSimpleRange(is_selection=false){

		let $range_cont = $('#fRange');

		if($('#display-div').tabs('option', 'active') != _type2TabIndexMap["f"] || ($('.calendars-popup').is(':visible') && !is_selection)){
			$range_cont.hide();
			return;
		}

		let $early = $('#fTPQ');
		let $latest = $('#fTAQ');

		let $range_amount = $('#fRNG');
		let $range_level = $('#level');

		if($early.val() == '' || $latest.val() == ''){
			$range_cont.hide();
			return;
		}

		let early_date = $early.val();
		let late_date = $latest.val();

		let is_greg = $('#selectCLD').val() == 'gregorian';

		// Get values as gregorian, then send across
		if(!is_greg){
			early_date = convert($early, false);
			late_date = convert($latest, false);
		}

		if(new Date(early_date).getTime() >= new Date(late_date).getTime()){

			$range_cont.hide();
			window.hWin.HEURIST4.msg.showMsgFlash('Earliest estimate needs to be before latest date', 3000);
			return;
		}

		window.hWin.HAPI4.SystemMgr.get_time_diffs({'early_date': early_date, 'latest_date': late_date}, function(response){
			if(response.status == window.hWin.ResponseStatus.OK){

				const data = response.data;

				$('#fYears').text(parseInt(data.years));
				$('#fMonths').text(parseInt(data.months));
				$('#fDays').text(parseInt(data.days));

				$range_cont.show();

			}else{
				$range_cont.hide();
				window.hWin.HEURIST4.msg.showMsgErr(response);
			}
		});
	}

    function _updateGeorgianDate(){
        var type = that.curTemporal.getType();
        if(calendar && calendar.name.toLowerCase()!='gregorian' && type){

            var value = '';
            if (type === "s") {
                value = convert($("#simpleDate"), true);
            }else if (type === "f") {
                value = convert($("#fTPQ"), true) + " " + convert($("#fTAQ"), true);
            }else  if (type === "p") {
                value = convert($("#TPQ"), true) + " " + convert($("#TAQ"), true);
                //PDB  PDE
            }

            $("#dateGregorian").text(value?"gregorian: "+value:'');

        }else{
            $("#dateGregorian").text('');
        }
    }

    //changedates - false for tab switch, it assign date intputs on init only
	function _updateUIFromTemporal (temporal, changedates) {
		var type = temporal.getType();
		if (!type) {
			return;
		}
		if (type === "s") {
			var tDate = temporal.getTDate("DAT");
			// if DAT then separate Date, Time and TimeZone
			if (tDate) {
                if(changedates){
				    $("#simpleDate").val(tDate.toString("yy-MM-dd"));
                }
				$("#simpleTime").val(tDate.toString("HH:mm:ss"));
				$("#tzone").val(tDate.toString("z"));
			}
		}

		var fields = Temporal.getFieldsForType(type);
		for(var i =0; i< fields.length; i++) {
			var code = fields[i];
			var val = temporal.getStringForCode(code);
			var elem = $( "#" + type + code);
			if (elem.length == 0) {
				elem = $("#" + code);
			}
			if (elem.length == 0) {
				elem = $("input[name=" + type + code + "]:checked");
			}
			if (type === "c" && val && (code == "DEV" || code == "DVP" ||code == "DVN" )) {
				var v = val.match(/P(\d+)Y/);
				val = v[1] ? v[1] : val;
			}
			if (type === "f" && val && code == "RNG" ) {
				var v = val.match(/P(\d+)(Y|M|D)/);
				val = v[1] ? v[1] : val;
				if (v[2]) $("#level").val(v[2]);
			}
			if (elem.length != 0) {
				switch (elem[0].type) {
					case "checkbox" :
						if (val) {
							elem.attr("checked","checked");
						}
						break;
					case "radio" :
						if (val) {
							elem.attr("checked","");
							elem = $("input[name=" + type + code + "][value=" + val + "]");
							elem.attr("checked","checked");
						}
						break;
					case "select-one" :
						if (val) {
							elem.val(val);
						}
						break;
					default :
                        if(!elem.hasClass('withCalendarsPicker') ||  changedates){
						        elem.val(val);
                        }
				}
			}
		}//for

	};

	function _updateTemporalFromUI (temporal, togregorian) {
		var type = temporal.getType();

        //store oirginal value
        if(togregorian){
            if(calendar && calendar.name.toLowerCase()!='gregorian'){

                var isj = (calendar.name.toLowerCase()=='julian');

                var value = '';
                if (type === "s") {
                    value = formatGregJulian($("#simpleDate").val(), isj);
                }else if (type === "f") {
                    value = formatGregJulian($("#fTPQ").val(), isj) + " to " + formatGregJulian($("#fTAQ").val(), isj);
                }else  if (type === "p") {
                    if($("#TPQ").val()!='' && $("#TAQ").val()!=''){
                        value = formatGregJulian($("#TPQ").val(), isj) + " to " + formatGregJulian($("#TAQ").val(), isj);
                    }else if($("#PDB").val()!='' && $("#PDE").val()!=''){
                        value = formatGregJulian($("#PDB").val(), isj) + " to " + formatGregJulian($("#PDE").val(), isj);
                    }
                }
                temporal.addObjForString("CL2", value);

            }else{
                  temporal.removeObjForCode("CL2");
            }
        }

		var fields = Temporal.getFieldsForType(type);
		for(var i =0; i< fields.length; i++) {
			var code = fields[i];
			var elem = $( "#" + type + code);
			if (elem.length == 0) {
				elem = $("#" + code);
			}
			if (elem.length == 0) {
				elem = $("input[name=" + type + code + "]:checked");  //radio button group
			}
			if (elem.length != 0) {
				switch (elem[0].type) {
					case "checkbox" :
						if (elem.is(":checked")) {
							temporal.addObjForString(code, "1");
						}else {
							temporal.removeObjForCode(code);
						}
						break;
					default :
						if (elem.val()) {
							var val = elem.val();
							if (code == "RNG") {
								val = "P" + val + $("#level").val();
							}
							if (code == "DEV" || code == "DVP" ||code == "DVN" ) {
								val = "P" + val + "Y";
							}
                            //convert to gregorian
                            if(togregorian && elem.hasClass('withCalendarsPicker')){
                                val = convert(elem, true);
                            }
							temporal.addObjForString(code, val);  // FIXME  this should validate input from the user.
						}else if (elem.length != 0) {
							temporal.removeObjForCode(code);
						}
				}
			}
		}
		if (type === "s") {

			var strDate = ($("#simpleTime").val());
			if (strDate && $("#tzone").val()) {
				var zone = $("#tzone").val().match(/^\s*(?:UTC|GMT)?\s*([\+|\-]?\d?\d:?(?:\d\d)?)?/)[1];
				if (zone) {
					strDate += " " + zone;
				}
			}
            var elem = $("#simpleDate");
            var dt = elem.val();
            if(togregorian){
                /*if(calendar && calendar.name!='gregorian'){
                    temporal.addObjForString("CL2", elem.val());
                }else{
                    temporal.removeObjForCode("CL2");
                }*/
                dt = convert(elem, true);
            }
			if (strDate && dt) {
				strDate = dt + " " + strDate;
			}else{
				strDate = dt;
			}
			if (strDate) temporal.addObjForString("DAT", strDate);
		}

		if(togregorian && type === "f"){ // check earliest and latest estimates

			let tpq = temporal.getStringForCode('TPQ');
			let taq = temporal.getStringForCode('TAQ')
			if(window.hWin.HEURIST4.util.isempty(tpq) || window.hWin.HEURIST4.util.isempty(taq)){
				throw 'Both earliest and latest estimates are required';
			}

			if(new Date(tpq).getTime() >= new Date(taq).getTime()){
				throw 'Earliest estimate needs to be before latest estimate';
			}
		}
		if(togregorian && type === "f" && // change to date range format and add simple range flag
			!window.hWin.HEURIST4.util.isempty(temporal.getStringForCode('TPQ')) && !window.hWin.HEURIST4.util.isempty(temporal.getStringForCode('TAQ'))){

        	temporal.setType("p");
        }

        if(calendar && calendar.name!='gregorian'){
            temporal.addObjForString("CLD", calendar.name);
        }else{
            temporal.removeObjForCode("CLD");
        }
	};

    var calendar = null;

    function _initJqCalendar(temporal){

        var defaultDate = null;
        var calendar_name = temporal.getStringForCode("CLD");
        if(!calendar_name){
            calendar_name = "gregorian";
        }else{
        	calendar_name = calendar_name.toLowerCase();
        }

        var type = temporal.getType();
        if (!type) {
            return;
        }

        $('.withCalendarsPicker').calendarsPicker({
            calendar: $.calendars.instance('gregorian'),
            showOnFocus: false,
            //defaultDate: convert($(this)), //null, defaultDate),
            dateFormat: 'yyyy-mm-dd',
            pickerClass: 'calendars-jumps',
            onSelect: function(dates){ 
				_updateGeorgianDate(); 
                if($('#display-div').tabs('option', 'active') == _type2TabIndexMap["f"]){
	                _updateSimpleRange(true);
                }
            },
            renderer: $.extend({}, $.calendars.picker.defaultRenderer,
                    {picker: $.calendars.picker.defaultRenderer.picker.
                        replace(/\{link:prev\}/, '{link:prevJump}{link:prev}').
                        replace(/\{link:next\}/, '{link:nextJump}{link:next}')}),
            showTrigger: '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/cal.gif" alt="Popup" class="trigger">'}
        );

        //change current calendar
        $('#selectCLD').change(function() {

            var name = $(this).val();
            if(!name) return;
            //var loadName = (name == 'julian' ? '' : '.'+name);
            //$.localise('../../external/jquery.calendars-1.2.1/jquery.calendars' + (loadName ? '.' : '') + loadName, 'en');

            calendar = $.calendars.instance(name);
            if(!calendar) return;
            //assign new calendar for all pickers
            $('.withCalendarsPicker').each(function() {

                var dd = convert($(this), false);
                $(this).calendarsPicker('option', {calendar: calendar,
                        defaultDate: dd
                });
            });
        }); //end change calendar

        calendar = $.calendars.instance(calendar_name);
        $('#selectCLD').val(calendar_name);
        $('#selectCLD').change();

        /*
        if (type === "s") {
            var tDate = temporal.getTDate("DAT");
            // if DAT then separate Date, Time and TimeZone
            if (tDate) {
                if(tDate.getYear()){
                    var month = tDate.getMonth()? tDate.getMonth(): 1;
                    var day = tDate.getDay()? tDate.getDay(): 1;
                    defaultDate = calendar.newDate(tDate.getYear(),month, day);
                }
            }
        }*/


    }

    // mode 0 - to gregorian (no assign), 1 - to
    var convert = function($inpt, togregorian) {

            //current value
            var tDate = TDate.parse($inpt.val());
            var value = null;
            var dformat = 'yyyy';
            var hasMonth = true;
            if (tDate && tDate.getYear()) {
                    hasMonth = tDate.getMonth()>0;
                    var hasDay  = tDate.getDay()>0;

                    var month = hasMonth? tDate.getMonth(): 1;
                    var day = hasDay? tDate.getDay(): 1;

                    dformat = dformat + (hasMonth?'-mm':'');
                    dformat = dformat + (hasDay?'-dd':'');

                    /*var value = $inpt.calendarsPicker('getDate');
                    if(value && Object.prototype.toString.apply(value) === '[object Array]' && value.length>0){
                            value = value[0];
                    }*/

                    var fromcal = $inpt.calendarsPicker('option', 'calendar');
                    value = fromcal.newDate(tDate.getYear(),month, day);
            }
            //var value = $inpt.calendarsPicker('getDate');

            function noNeedConvert(from, to){

                var cc = ['taiwan','julian','gregorian'];

                return (from.name.toLowerCase()==to.name.toLowerCase()) ||
                (!hasMonth &&
                 ((cc.indexOf(from.name.toLowerCase())>=0 && cc.indexOf(to.name.toLowerCase())>=0) ||
                  (cc.indexOf(from.name.toLowerCase())>=0 && cc.indexOf(to.name.toLowerCase())>=0)));
            }

            var newval = '';
            if(value){

                        var tocalendar = togregorian ?$.calendars.instance('gregorian') :calendar;
                        if(noNeedConvert(value._calendar, tocalendar)){
                            if(togregorian){
                                newval = $inpt.val();
                            }else{
                                //newval = togregorian ?$inpt.val():value;
                                newval = value;
                                newval._calendar.local.name = tocalendar.local.name;
                                newval._calendar.name  = tocalendar.local.name;
                            }
                        }else{
                            try{
                                var jd = value._calendar.toJD(Number(value.year()), Number(value.month()), Number(value.day()));
                                newval = tocalendar.fromJD(jd);
                            }catch(err){
                                alert(err);
                                if(togregorian){
                                    newval = '';
                                }else{
                                    $inpt.val('');
                                }
                            }

                            if(togregorian){
                                newval = tocalendar.formatDate(dformat, newval);
                            }else {
                                $inpt.val( tocalendar.formatDate(dformat, newval) );
                            }
                        }
            }else {
                $inpt.val('');
            }

            return newval;
    };
	//public members
	var that = {
			originalInputString : "",
			origTemporal : null,
			curTemporal : null,
			name : "App",
			//getTabView : function () {return _tabView; },
			close : function () {
				try{
					_updateTemporalFromUI(that.curTemporal, true);
				}catch(e) {	// save string in COM field and keep an empty simple date temporal
					alert(e);
					return;
				}
				var validity = Temporal.checkValidity(that.curTemporal);
				if (validity[0]) {  // valid temporal
					if (validity[2]) { //some extra code fields, so remove them
						for (var i=0; i<validity[2].length; i++) {
							that.curTemporal.removeObjForCode(validity[2][i]);
						}
					}
					window.close( that.curTemporal.toString());
				}else{
					var msg = "";
					for (var i = 0; i < validity[1].length; i++) {
					 if (!msg){
						msg = Temporal.getStringForCode(validity[1][i]);
					 }else{
						msg += ", " + Temporal.getStringForCode(validity[1][i]);
					 }
					}
					msg = msg ? "The current temporal is missing the " + msg + " value(s)." : "";
					msg +=  validity[3] ? " " + validity[3] : "";
                    
                    alert(msg);
					/*
                    if (!confirm( msg +
							"Would you like to continue working? Press cancel to reset to the original string.")) {
						window.close(that.originalInputString);
					}else{
						window.close("");
					}*/
				}

			},
			cancel : function () {
				window.close(that.originalInputString);
			},
			getClass : function () {
				return _className;
			},
			isA: function(strClass) {
				if(strClass === _className) return true;
				return false;
			}
	};

	_init();
	return that;
})();

