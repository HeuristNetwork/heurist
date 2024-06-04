/**
* editInputGeo.js widget for input controls on edit form
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
import "./editInputBase.js";

$.widget( "heurist.editInputDate", $.heurist.editInputBase, {

    // default options
    options: {
    },

    _btn_temporal:null,
    _datepicker:null,
    
    _create: function() {
        
            var that = this;
            
            this._super();
        
            this.element.uniqueId();
            
            var $input = $( "<input>")
                        .uniqueId()
                        .addClass('text ui-widget-content ui-corner-all')
                        .val(this._value)
                        .appendTo( this.element );
            
            window.hWin.HEURIST4.ui.disableAutoFill( $input );
            
            this._input = $input;

            this._createDateInput();
            
            this._onDateChange(); //to show human readble value
            
    },
    
    _destroy: function() {
        if(this._btn_temporal) this._btn_temporal.remove();
        if(this._datepicker) this._datepicker.remove();
        if(this._input) this._input.remove();
    },


    /**
    * 
    */
    setWidth: function(dwidth){

        if( typeof dwidth==='string' && dwidth.indexOf('%')== dwidth.length-1){ //set in percents
            this._input.css('width', dwidth);
        }
/*        
        else{
              //if the size is greater than zero
              var nw = (this.detailType=='integer' || this.detailType=='float')?40:120;
              if (parseFloat( dwidth ) > 0){ 
                  nw = Math.round( 3+Number(dwidth) );
                    //Math.round(2 + Math.min(120, Number(dwidth))) + "ex";
              }
              this._input.css({'min-width':nw+'ex','width':nw+'ex'}); //was *4/3
        }
*/        
    },
    
    /**
    * put your comment there...
    */
    clearValue: function(){
        this._newvalue = '';    
        if(this._input){
            this._input.val('');   
            this._onDateChange();
        }
    },
    
    /**
    * 
    * 
    */
    _createDateInput: function(){
        
        var $input = this._input;
        var $inputdiv = this.element;
      
        $input.css('width', this.options.is_faceted_search?'13ex':'20ex');
        
        var that = this;

        var defDate = $input.val();
        var $tinpt = $('<input type="hidden" data-picker="'+$input.attr('id')+'">')
                        .val(defDate).insertAfter( $input );

        if(window.hWin.HUL.isFunction($('body').calendarsPicker)){ // third party extension for jQuery date picker, used for Record editing

            var calendar = $.calendars.instance('gregorian');
            var g_calendar = $.calendars.instance('gregorian');
            var temporal = null;

            try {
                temporal = Temporal.parse($input.val());
            } catch(e) {
                temporal = null;
            }
            var cal_name = temporal ? temporal.getField('CLD') : null;
            var tDate = temporal ? temporal.getTDate("DAT") : null;

            if(!window.hWin.HEURIST4.util.isempty($input.val()) 
                    && tDate 
                    && cal_name && cal_name.toLowerCase() !== 'gregorian')
            {

                // change calendar to current type
                calendar = $.calendars.instance(cal_name);                

                if(tDate && tDate.getYear()){
                    var hasMonth = tDate.getMonth();
                    var hasDay = tDate.getDay();

                    var month = hasMonth ? tDate.getMonth() : 1;
                    var day = hasDay ? tDate.getDay() : 1;

                    defDate = that._translateDate({'year': tDate.getYear(), 'month': month, 'day': day}, g_calendar, calendar);
                }
            }else if(tDate){
                // remove padding zeroes from year
                var year = Number(tDate.getYear());
                defDate = tDate.toString('yyyy-MM-dd');
                defDate = defDate.replace(tDate.getYear(), year);
            }

            $tinpt.val(defDate);

            $tinpt.calendarsPicker({
                calendar: calendar,
                defaultDate: defDate,
                //selectDefaultDate: false,
                showOnFocus: false,
                dateFormat: 'yyyy-mm-dd',
                pickerClass: 'calendars-jumps',
                onShow: function($calendar, calendar_locale, config){
                    config.div.css('z-index', 9999999);
                },
                onSelect: function(date){

                    let cur_cal = $tinpt.calendarsPicker('option', 'calendar');
                    let value = $tinpt.val();
                    const org_value = value;
                    if(cur_cal.name.toLowerCase() === 'japanese'){
                        value = cur_cal.japaneseToGregorianStr(value);
                    }
                    let val_parts = value != '' ? value.split('-') : '';
                    let new_temporal = new Temporal();

                    if(val_parts.length == 4 && val_parts[0] == ''){ // for BC years
                        val_parts.shift();
                        val_parts[0] = '-'+val_parts[0];
                    }

                    if(window.hWin.HEURIST4.util.isArrayNotEmpty(val_parts) && val_parts.length == 3 && cur_cal.local.name.toLowerCase() != 'gregorian'){

                        let g_value = that._translateDate({'year': val_parts[0], 'month': val_parts[1], 'day': val_parts[2]}, cur_cal, g_calendar);
                        g_value = g_calendar.formatDate('yyyy-mm-dd', g_value);

                        if(g_value != ''){
                            try {

                                var new_tdate = TDate.parse(g_value);

                                new_temporal.setType('s');
                                new_temporal.setTDate('DAT', new_tdate);
                                new_temporal.addObjForString('CLD', cur_cal.local.name);
                                new_temporal.addObjForString('CL2', org_value);

                                value = new_temporal.toString();
                            } catch(e) {}
                        }
                    }

                    $input.val(value);
                    window.hWin.HAPI4.save_pref('edit_record_last_entered_date', $input.val());
                    that._onDateChange();
                },
                renderer: $.extend({}, $.calendarsPicker.defaultRenderer,
                        {picker: $.calendarsPicker.defaultRenderer.picker.
                            replace(/\{link:prev\}/, '{link:prevJump}{link:prev}').
                            replace(/\{link:next\}/, '{link:nextJump}{link:next}')}),
                showTrigger: '<span class="smallicon ui-icon ui-icon-calendar" style="display:inline-block" data-picker="'+$input.attr('id')+'" title="Show calendar"></span>'}
            );

            this._on($input, {
                'blur': function(event){ //update to changed value
                    $tinpt.val($input.val());
                }
            });
        }else{ // we use jquery datepicker for general use

                /*var $tinpt = $('<input type="hidden" data-picker="'+$input.attr('id')+'">')
                        .val($input.val()).insertAfter( $input );*/

                var $btn_datepicker = $( '<span>', {title: 'Show calendar'})
                    .attr('data-picker',$input.attr('id'))
                    .addClass('smallicon ui-icon ui-icon-calendar')
                    .insertAfter( $tinpt );
                    
                
                var $datepicker = $tinpt.datepicker({
                    /*showOn: "button",
                    buttonImage: "ui-icon-calendar",
                    buttonImageOnly: true,*/
                    showButtonPanel: true,
                    changeMonth: true,
                    changeYear: true,
                    dateFormat: 'yy-mm-dd',
                    beforeShow: function(){
                        
                        if(that._container.is_disabled) return false;
                        var cv = $input.val();
                        
                        var prev_dp_value = window.hWin.HAPI4.get_prefs('edit_record_last_entered_date'); 
                        if(cv=='' && !window.hWin.HEURIST4.util.isempty(prev_dp_value)){
                            //$datepicker.datepicker( "setDate", prev_dp_value );    
                            $datepicker.datepicker( "option", "defaultDate", prev_dp_value); 
                        }else if(cv!='' && cv.indexOf('-')<0){
                            $datepicker.datepicker( "option", "defaultDate", cv+'-01-01'); 
                        }else if(cv!='') {
                            $tinpt.val($input.val());
                            //$datepicker.datepicker( "option", "setDate", cv); 
                        }
                    },
                    onClose: function(dateText, inst){
                        
                        if($tinpt.val()!=''){
                            $input.val($tinpt.val());
                            window.hWin.HAPI4.save_pref('edit_record_last_entered_date', $input.val());
                            that._onDateChange();
                        }else{
                            $tinpt.val($input.val());
                        }
                    }
                });
                
                this._on( $input, {
                    keyup: function(event){
                        if(!isNaN(String.fromCharCode(event.which))){
                            var cv = $input.val();
                            if(cv!='' && cv.indexOf('-')<0){
                                $datepicker.datepicker( "setDate", cv+'-01-01');   
                                $input.val(cv);
                            }
                        }
                    },
                    keypress: function (e) {
                        var code = e.charCode || e.keyCode;
                        var charValue = String.fromCharCode(code);
                        var valid = false;

                        if(charValue=='-'){
                            valid = true;
                        }else{
                            valid = /^[0-9]+$/.test(charValue);
                        }

                        if(!valid){
                            window.hWin.HEURIST4.util.stopEvent(e);
                            e.preventDefault();
                        }

                    },
                    dblclick: function(){
                        $btn_datepicker.trigger('click');
                    }
                });

                //.button({icons:{primary: 'ui-icon-calendar'},text:false});
               
                
                this._on( $btn_datepicker, { click: function(){
                    
                        if(that._container.is_disabled) return;
                        
                        $datepicker.datepicker( 'show' ); 
                        $("#ui-datepicker-div").css("z-index", "999999 !important"); 
                        //$(".ui-datepicker").css("z-index", "999999 !important");   
                }} );
        } 

        if(this.options.is_faceted_search){
            
            $input.css({'max-width':'13ex','min-width':'13ex'});
            
        }else if(this.options.dtID>0){ //this is details of records

            // temporal widget button
            this._btn_temporal = $( '<span>', {
                title: 'Pop up widget to enter compound date information (uncertain, fuzzy, radiometric etc.)', 
                class: 'smallicon', 
                style: 'margin-left: 1em;width: 55px !important;font-size: 0.8em;cursor: pointer;'
            })
            .text('range')
            .appendTo( $inputdiv );

            $('<span>', {class: 'ui-icon ui-icon-date-range', style: 'margin-left: 5px;'}).appendTo(this._btn_temporal); // date range icon
            
            this._on( this._btn_temporal, { click: function(){
                
                if(that._container.is_disabled) return;

                var url = window.hWin.HAPI4.baseURL 
                    + 'hclient/widgets/editing/editTemporalObject.html?'
                    + encodeURIComponent(that._newvalue?that._newvalue:$input.val());
                
                window.hWin.HEURIST4.msg.showDialog(url, {height:570, width:750,
                    title: 'Temporal Object',
                    class:'ui-heurist-populate-fade',
                    //is_h6style: true,
                    default_palette_class: 'ui-heurist-populate',
                    callback: function(str){
                        if(!window.hWin.HEURIST4.util.isempty(str) && that._newvalue != str){
                            $input.val(str);    
                            $input.trigger('change');
                        }

                        if(window.hWin.HUL.isFunction($('body').calendarsPicker) && $tinpt.hasClass('hasCalendarsPicker')){

                            var new_temporal = null;
                            var new_cal = null;
                            var new_date = null;
                            try {
                                new_temporal = Temporal.parse(str);
                                new_cal = new_temporal.getField('CLD');
                                new_cal = $.calendars.instance(new_cal);
                                new_date = new_temporal.getTDate("DAT");
                            } catch(e) {
                                new_cal = null;
                                new_date = null;
                            }

                            // Update calendar for calendarPicker
                            if(new_cal && new_date && typeof $tinpt !== 'undefined'){

                                if(new_date.getYear()){
                                    var hasMonth = new_date.getMonth();
                                    var hasDay = new_date.getDay();

                                    var month = hasMonth ? new_date.getMonth() : 1;
                                    var day = hasDay ? new_date.getDay() : 1;

                                    new_date = that._translateDate({'year': new_date.getYear(), 'month': month, 'day': day}, g_calendar, new_cal);
                                    new_date = new_date.formatDate('yyyy-mm-dd', new_cal);
                                }

                                var cur_cal = $tinpt.calendarsPicker('option', 'calendar');
                                if(cur_cal.local.name.toLowerCase() != new_cal.local.name.toLowerCase()){
                                    $tinpt.calendarsPicker('option', 'calendar', new_cal);
                                }

                                if(typeof new_date == 'string'){
                                    $tinpt.val(new_date);
                                }
                            }
                        }
                    }
                } );
            }} );

        }//temporal allowed
        
        
        let css = 'display: block; font-size: 0.8em; color: #999999; padding: 0.3em 0px;';

        if(this.options.recordset?.entityName=='Records' && this._$('.extra_help').length == 0){
            // Add additional controls to insert yesterday, today or tomorrow

            let $help_controls = $('<div>', { style: css, class: 'extra_help' })
                .html('<span class="fake_link">Yesterday</span>'
                    + '<span style="margin: 0px 5px" class="fake_link">Today</span>'
                    + '<span class="fake_link" class="fake_link">Tomorrow</span>'
                    + '<span style="margin-left: 10px;">yyyy, yyyy-mm or yyyy + click calendar (remembers last date)</span>');

            let input_prompt = this._$('div.input_cell > div.heurist-helper1');
                    
            $help_controls.insertBefore(input_prompt);

            this._on($help_controls.find('span.fake_link'), {
                click: function(e){
                    this._input.val(e.target.textContent).trigger('change');
                }
            });
        }
        
        this._datepicker = $tinpt;
        
        this._on( this._input, { keyup:this.onChange, change:this._onDateChange });
        //$input.on('change',_onDateChange);
    },
   
    /**
    * 
    * 
    */
    _onDateChange: function (){

            var $input = this._input;
            
            var value = $input.val();
            
            this._newvalue = value; 
            
            var that = this;
        
            if(that.options.dtID>0){
                
                var isTemporalValue = value && value.search(/\|VER/) != -1; 
                if(isTemporalValue) {
                    window.hWin.HEURIST4.ui.setValueAndWidth($input, temporalToHumanReadableString(value));    

                    var temporal = new Temporal(value);
                    var content = '<p>'+temporal.toReadableExt('<br>')+'</p>';
                    
                    var $tooltip = $input.tooltip({
                        items: "input.ui-widget-content",
                        position: { // Post it to the right of $input
                            my: "left+20 center",
                            at: "right center",
                            collision: "none"
                        },
                        show: { // Add slight delay to show
                            delay: 500,
                            duration: 0
                        },
                        content: function(){ // Provide text
                            return content;
                        },
                        open: function(event, ui){ // Add custom CSS + class
                            ui.tooltip.css({
                                "width": "200px",
                                "background": "rgb(209, 231, 231)",
                                "font-size": "1.1em"
                            })//.addClass('ui-heurist-populate');
                        }
                    });
                    
                    that._container.addTooltip($input.attr('id'), $tooltip);

                    $input.addClass('Temporal').removeClass('text').attr('readonly',true);
                }else{
                    $input.removeClass('Temporal').addClass('text').attr('readonly',false).css('width','20ex');
                    that._container.removeTooltip($input.attr('id'));
                }
            }
            
            this.onChange();
    },

    /**
    * Convert date from one calendar system to another
    * 
    * @param {Object} date
    * @param from_calendar
    * @param to_calendar
    * 
    * @returns {Object}
    */
    _translateDate: function(date, from_calendar, to_calendar){

            if(!window.hWin.HUL.isFunction($('body').calendarsPicker)){
                return date;
            }

            if(typeof date == 'string'){
                var date_parts = date.split('-');
                date = {};
                date['year'] = date_parts[0];

                if(date_parts.length >= 2){
                    date['month'] = date_parts[1];
                }
                if(date_parts.length == 3){
                    date['day'] = date_parts[2];
                }
            }

            var new_cal = from_calendar.newDate(date['year'], date['month'], date['day']);
            if(!new_cal){
                return date;
            }

            var julian_date = new_cal._calendar.toJD(Number(new_cal.year()), Number(new_cal.month()), Number(new_cal.day()));
            return to_calendar.fromJD(julian_date);
        }

    
    
});