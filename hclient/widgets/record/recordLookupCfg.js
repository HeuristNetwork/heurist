/**
* recordLookupCfg.js - configuration for record lookup services
*                       original config is hsapi/controller/record_lookup_config.json
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

$.widget( "heurist.recordLookupCfg", {

    // default options
    options: {
    
        //DIALOG section       
        isdialog: false,     // show as dialog @see  _initDialog(), popupDialog(), closeDialog
        height: 640,
        width:  900,
        modal:  true,
        title:  '',
        htmlContent: 'recordLookupCfg.html',
        helpContent: null,
        
        //parameters
        service_config: {}, // all assigned services
        
        //listeners
        onInitFinished:null,  //event listener when dialog is fully inited - use to perform initial search with specific parameters
        beforeClose:null,     //to show warning before close
        onClose:null       
    },
    
    _as_dialog:null, //reference to itself as dialog (see options.isdialog)
    
    _need_load_content:true,
    
    _current_cfg: null, // current set service details
    _current_serivce: null, // current service, for editing
    _available_services:null, // list of available services


    //controls
    selectRecordType:null, //selector for rectypes
    selectServiceType: null, //selector for lookup service types
    serviceList: null, //left panel list
    
    
    // the widget's constructor
    _create: function() {
        // prevent double click to select text
        //it prevents inputs in FF this.element.disableSelection();
    }, //end _create
    
    //
    //  load configuration and call _initControls
    //
    _init: function() {
        
        this._available_services = window.hWin.HAPI4.sysinfo['services_list'];
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(this._available_services)){
            window.hWin.HEURIST4.msg.showMsgErr('There are no available services, or the configuration file was not found or is broken');
            return;
        }
        
        var that = this;

        this.options.service_config = window.hWin.HEURIST4.util.isJSON(this.options.service_config);
        if(!this.options.service_config){
            this.options.service_config = {};    
        } 
        
        this.element.addClass('ui-heurist-design');
        
        if(this.options.isdialog){  //show this widget as popup dialog
            this._initDialog();
        }
        
        //load html from file
        if(this._need_load_content && this.options.htmlContent){        
            this.element.load(window.hWin.HAPI4.baseURL+'hclient/widgets/record/'+this.options.htmlContent
                            +'?t='+window.hWin.HEURIST4.util.random(), 
            function(response, status, xhr){
                that._need_load_content = false;
                if ( status == "error" ) {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }else{
                    if(that._initControls()){
                        if($.isFunction(that.options.onInitFinished)){
                            that.options.onInitFinished.call(that);
                        }        
                    }
                }
            });
            return;
        }else{
            if(that._initControls()){
                if($.isFunction(that.options.onInitFinished)){
                    that.options.onInitFinished.call(that);
                }        
            }
        }
    },
    
     
    //  
    // invoked from _init after loading of html content
    //
    _initControls:function(){
        
        var that = this;

        //fill record type selector
        this.selectRecordType = this.element.find('#sel_rectype').css({'list-style-type': 'none'});
        this.selectRecordType = window.hWin.HEURIST4.ui.createRectypeSelectNew(this.selectRecordType.get(0),
                            {topOptions:'select record type...'});
        // hide default option
        $(this.selectRecordType.find('option')[0]).attr('hidden', true);
        this.selectMenuRefresh(this.selectRecordType);
        // on change handler
        this._on(this.selectRecordType, {
            change: function(){

                that.element.find('#btnSave').css({'opacity': '1', 'cursor': 'pointer'}).attr('disabled', false);

                that._onRectypeChange();
            }
        });
        
        //fill service list
        this.serviceList = this.element.find('#sel_service');
        this._reloadServiceList();
        // on selected handler
        this.serviceList.selectable( {
            selected: function( event, ui ) {
                if($(ui.selected).is('li')){

                    that.serviceList.find('li').removeClass('ui-state-active');
                    that.serviceList.find('div').removeClass('ui-state-active');
                    $(ui.selected).addClass('ui-state-active');
                    
                    //load configuration into right hand form
                    var  service_name = $(ui.selected).attr('value');
                    
                    that.selectServiceType.val('');

                    that._current_service = service_name;

                    that._fillConfigForm( service_name );
                }
            }
        });

        //fill service types
        this.selectServiceType = this.element.find('#sel_servicetype').css({'list-style-type': 'none'});
        this._getServiceSelectmenu();
        // on change handler
        this._on(this.selectServiceType[0], {
            change: function(event, ui){

                var service = that.selectServiceType.val(); // selected service

                that._changeService( service ); // setup
            }
        });

        var ele = this.element.find('#inpt_label');
        this._on(ele, {input: function(){

            this.element.find('#btnSave').css({'opacity': '1', 'cursor': 'pointer'}).attr('disabled', false);
        }});

        ele = this.element.find('#btnAddService').button({ icon: "ui-icon-plus" }).css('left', '165px');
        this._on(ele, {click: this._addNewService});

        ele = this.element.find('#btnSave').button().css({'opacity': '0.8', 'cursor': 'default'}).attr('disabled', true);
        this._on(ele, {click: this._applyConfig});            
        
        if(this.options.isdialog){
            this.popupDialog();
        }
        
        //show hide hints and helps according to current level
        window.hWin.HEURIST4.ui.applyCompetencyLevel(-1, this.element); 
        
        return true;
    },

    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    //
    _setOptions: function( ) {
        this._superApply( arguments );
    },

    /* 
    * private function 
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

    },

    // 
    // custom, widget-specific, cleanup.
    //
    _destroy: function() {
        // remove generated elements
        if(this.selectRecordType) this.selectRecordType.remove();
        if(this.serviceList) this.serviceList.remove();

    },
    
    //
    // array of button defintions
    //
    _getActionButtons: function(){

        var that = this;        
        return [
                 {text:window.hWin.HR('Done'), 
                    id:'btnCancel',
                    css:{'float':'right','margin-left':'30px'}, 
                    click: function() { 
                        that.closeDialog();
                    }
                 }
                ];
    },

    //
    // init dialog widget
    // see also popupDialog, closeDialog 
    //
    _initDialog: function(){
        
        var options = this.options,
            btn_array = this._getActionButtons(), 
            position = null,
                that = this;
    
        if(!options.beforeClose){
                options.beforeClose = function(){
                    //show warning on close
                    /*
                    var $dlg, buttons = {};
                    buttons['Save'] = function(){ that._saveEditAndClose(null, 'close'); $dlg.dialog('close'); }; 
                    buttons['Ignore and close'] = function(){ that._currentEditID=null; that.closeDialog(); $dlg.dialog('close'); };
                    
                    $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                            'You have made changes to the configuration. Click "Save" otherwise all changes will be lost.',
                            buttons,
                            {title:'Confirm',yes:'Save',no:'Ignore and close'});
                    return false;   
                    */
                    return true;
                };
        }
        
        if(position==null) position = { my: "center", at: "center", of: window };
        var maxw = (window.hWin?window.hWin.innerWidth:window.innerWidth);
        if(options['width']>maxw) options['width'] = maxw*0.95;
        var maxh = (window.hWin?window.hWin.innerHeight:window.innerHeight);
        if(options['height']>maxh) options['height'] = maxh*0.95;
        
        var that = this;
        
        var $dlg = this.element.dialog({
            autoOpen: false ,
            //element: this.element[0],
            height: options['height'],
            width:  options['width'],
            modal:  (options['modal']!==false),
            title: 'Lookup services configuration',
            position: position,
            beforeClose: options.beforeClose,
            resizeStop: function( event, ui ) {//fix bug
                that.element.css({overflow: 'none !important','width':that.element.parent().width()-24 });
            },
            close:function(){
                if($.isFunction(that.options.onClose)){
                  //that.options.onClose(that._currentEditRecordset);  
                  that.options.onClose( that.options.service_config );
                } 
                that._as_dialog.remove();    
                    
            },
            buttons: btn_array
        }); 
        this._as_dialog = $dlg; 
        
        $dlg.parent().addClass('ui-dialog-heurist ui-heurist-design');
    },
    
    //
    // show itself as popup dialog
    //
    popupDialog: function(){
        if(this.options.isdialog){

            this._as_dialog.dialog("open");
            
            var helpURL = (this.options.helpContent)
                ?(window.hWin.HAPI4.baseURL+'context_help/'+this.options.helpContent+' #content'):null;
            
            window.hWin.HEURIST4.ui.initDialogHintButtons(this._as_dialog, null, helpURL, false);
        }
    },
    
    //
    // close dialog
    //
    closeDialog: function(is_force){
        if(this.options.isdialog){
            
            if(is_force===true){
                this._as_dialog.dialog('option','beforeClose',null);
            }
            
            this._as_dialog.dialog("close");
        }
    },

    //
    // Get list of available services
    //
    _getServiceSelectmenu: function(){

        var options = [];

        var values = {};

        values = {
            title: 'select a service...',
            key: '',
            disabled: true,
            selected: true,
            hidden: true
        }; // top option

        options.push(values);

        for(idx in this._available_services){

            values = {
                title: this._available_services[idx].label,
                key: this._available_services[idx].service,
                disabled: false,
                selected: false,
                hidden: false
            };

            options.push(values);
        }

        this.selectServiceType = window.hWin.HEURIST4.ui.createSelector(this.selectServiceType.get(0), options); // create dropdown
        this.selectServiceType = $(this.selectServiceType);
        window.hWin.HEURIST4.ui.initHSelect(this.selectServiceType, false); // initial selectmenu

        this.selectMenuRefresh(this.selectServiceType);
    },    

    //
    // prepare forms for new service
    //
    _addNewService: function(){

        // empty all inputs
        this.serviceList.find('li').removeClass('ui-state-active');
        this.selectServiceType.val('');
        this.selectRecordType.val('');
        this.element.find('#inpt_label').val('');

        // empty control variables
        this._current_cfg = null;
        this._current_serivce = null;

        // refresh dropdowns
        this.selectMenuRefresh(this.selectServiceType);
        this.selectMenuRefresh(this.selectRecordType);

        this.element.find('#btnSave').css({'opacity': '0.8', 'cursor': 'default'}).attr('disabled', true);

        this._fillConfigForm(null);
    },

    //
    // fill in contents of right panel
    //    
    _fillConfigForm: function( service_val ){

        if(!window.hWin.HEURIST4.util.isempty(service_val)){

            var service_name = service_val.split("_");

            var cfg0 = null;

            $.each(this._available_services, function(i, srv){
              if(srv.service==service_name[0]){
                  cfg0 = srv;
                  return false;
              }
            });
            
            if(cfg0==null){
                this.element.find('#service_config').hide();
                this.element.find('#service_name').html('<span class="ui-icon ui-icon-arrowthick-1-w"/>Select a service to edit or click the assign button');
                return;
            }
            
            this.element.find('#service_config').show();
            this.element.find('.service_details').show();
            this.element.find('#btnSave').show();

            if(window.hWin.HEURIST4.util.isempty(this.selectServiceType.val())){

                this.selectServiceType.val(service_name[0]);
                
                this.selectMenuRefresh(this.selectServiceType);
            }

            if(this.options.service_config && service_name.length==1 && this.options.service_config[service_name[0]]){ 
                
                //already exists, old id
                this._current_cfg = this.options.service_config[service_name[0]];
            }else if(this.options.service_config && service_name.length==2 && this.options.service_config[service_val]){
                
                //already exists, new id
                this._current_cfg = this.options.service_config[service_val];
            }else{

                //new configuration
                this._current_cfg = window.hWin.HEURIST4.util.cloneJSON(cfg0);
            }

            this.element.find('#service_name').html(cfg0.label);
            this.element.find('#service_description').html('<strong>' + cfg0.service + '</strong>: ' + cfg0.description);
            this.element.find('#inpt_label').val(this._current_cfg.label);

            //that._changeService( service_name[0] );

            var tbl = this.element.find('#tbl_matches');
            tbl.empty();

            $.each(this._current_cfg.fields, function(field, code){
                $('<tr><td>'+field+'</td><td><select data-field="'+field+'"/></td></tr>').appendTo(tbl);
            });

            var rty_ID = this._current_cfg.rty_ID>0 ?$Db.getLocalID('rty',this._current_cfg.rty_ID) :'';
            
            this.selectRecordType.val( rty_ID );
            this.selectMenuRefresh(this.selectRecordType);
            this._onRectypeChange();
        }else{

            if(!window.hWin.HEURIST4.util.isempty(this.selectServiceType.val()) && !window.hWin.HEURIST4.util.isempty(this._current_cfg)){
                
                this._fillConfigForm(this._current_cfg);
            }else{ // no id and no service selected

                this.element.find('#service_config').show();
                this.element.find('#service_type').show();
                
                this.element.find('#service_name').html('<span class="ui-icon ui-icon-arrowthick-1-w"/>Select a service to edit or');
                
                this.element.find('.service_details').hide();
                this.element.find('#btnSave').hide();
                
                return;
            }
        }
    },
    
    //
    // prepare form for service type change
    //
    _changeService: function( service_name ){

        var cfg0 = null;

        $.each(this._available_services, function(i, srv){ // get new service info
          if(srv.service==service_name){
              cfg0 = srv;
              return false;
          }
        });

        this._current_cfg = cfg0;

        // show containers and save button
        this.element.find('#service_config').show();
        this.element.find('.service_details').show();
        this.element.find('#btnSave').show();
        this.element.find('#btnSave').css({'opacity': '1', 'cursor': 'pointer'}).attr('disabled', false);

        // set label and description
        this.element.find('#service_name').html(cfg0.label);
        this.element.find('#service_description').html(cfg0.description);

        var tbl = this.element.find('#tbl_matches');
        tbl.empty();

        $.each(cfg0.fields, function(field, code){
            $('<tr><td>'+field+'</td><td><select data-field="'+field+'"/></td></tr>').appendTo(tbl);
        });

        this._onRectypeChange();
    },

    //
    // create map fields dropdowns
    //
    _onRectypeChange: function(){
     
        var rty_ID = this.selectRecordType.val();   
        
        var tbl = this.element.find('#tbl_matches');
        
        if(rty_ID>0){
            var that = this;
            $.each(tbl.find('select'), function(i, ele){
                
                var field = $(ele).attr('data-field');
                var dty_ID;
              
                if(!window.hWin.HEURIST4.util.isempty(that._current_cfg)){
                    
                    dty_ID = that._current_cfg.fields[field];
                }else if(!window.hWin.HEURIST4.util.isempty(that.selectServiceType.val())){
                    
                    for(idx in that._available_services){

                        if(that._available_services[idx] == that.selectServiceType.val()){
                            dty_ID = that._available_services[idx].fields[field];
                        }
                    }
                }

                if (dty_ID!=null && !(dty_ID>0) && (dty_ID.indexOf('_long') >= 0 || dty_ID.indexOf('_lat') >= 0)){
                  
                }else{
                    dty_ID = dty_ID>0 ?$Db.getLocalID('dty', dty_ID) :'';
                }
                
                window.hWin.HEURIST4.ui.createRectypeDetailSelect(ele, rty_ID, 
                    ['freetext','blocktext','enum','date','geo','float','year','integer','resource'], '...', 
                    {show_latlong:true, show_dt_name:true, selectedValue:dty_ID} );
            });
        }else{
            
            $.each(tbl.find('select'), function(i,selObj){

                if($(selObj).hSelect("instance")!=undefined){
                   $(selObj).hSelect("destroy"); 
                }
                $(selObj).empty();
            });
            
        }
        
    },
    
    //
    // refresh assigned service list, popup's left panel
    //
    _reloadServiceList: function(){
      
        var that = this;

        this.serviceList.empty(); // empty list

        for(idx in this.options.service_config){ // display all assigned services

            var cfg = this.options.service_config[idx];

            if(window.hWin.HEURIST4.util.isempty(cfg)){
                continue;
            }

            var s = cfg.label + '<span class="ui-icon ui-icon-arrowthick-1-e"/> ' 
                + $Db.rty(cfg.rty_ID, 'rty_Name');

            s = s + '<button id="'+idx+'" style="float:right;">X</button>';

            $('<li class="ui-widget-content" value="'+idx+'">'+s+'</li>')  
                .css({margin: '5px 2px 2px', padding: '0.4em', cursor:'pointer', background:'#e0dfe0'}) 
                .appendTo(this.serviceList);    
        }
        
        this.serviceList.find('li').hover(function(event){ // service list hover event
            var ele = $(event.target);
            if(!ele.is('li')) ele = ele.parent();
            ele.addClass('ui-state-hover');
        }, function(event){
            var ele = $(event.target);
            if(!ele.is('li')){ 
                ele.removeClass('ui-state-hover'); // ensure that this element does not have the hover state
                ele = ele.parent();
            }
            ele.removeClass('ui-state-hover');
        });

        this.serviceList.find('button').click(function(event){ // remove service button
            that._removeConfig($(event.target).attr('id'));
        }); 
    },
    
    //
    // save current service details
    //
    _applyConfig: function(){
        
        var rty_ID = this.selectRecordType.val();
        var service = this.selectServiceType.val();
        var label = this.element.find('#inpt_label').val();

        var name = this._current_service;
        var t_name = service + '_' + rty_ID;

        if(window.hWin.HEURIST4.util.isempty(name)){ // is a new service
            name = service + '_' + rty_ID;
        }
        if(window.hWin.HEURIST4.util.isempty(label)){ // set label to default, if none provided
            label = service;
        }

        if(rty_ID>0 && !window.hWin.HEURIST4.util.isempty(service)){ // check if a service and table have been selected

            var that = this;
            var tbl = this.element.find('#tbl_matches');
            var is_field_mapped = false;

            var fields = {};
            
            $.each(tbl.find('select'), function(i, ele){ // get mapped fields
                
                var field = $(ele).attr('data-field');
                var dty_ID = $(ele).val();
                fields[field] = dty_ID; 
                
                if(dty_ID>0) is_field_mapped = true;

            });
            
            if(is_field_mapped){
                
                this.options.service_config = window.hWin.HEURIST4.util.isJSON(this.options.service_config); // get existing assigned services
                if(!this.options.service_config){
                    this.options.service_config = {};    
                } 
                
                if(!window.hWin.HEURIST4.util.isempty(this._current_cfg)){ // save changes

                    this._current_cfg.rty_ID = rty_ID;
                    this._current_cfg.label = label;
                    this._current_cfg.service = service;
                    this._current_cfg.fields = fields;

                    if(name != t_name){
                        
                        delete this.options.service_config[name];
                        
                        name = t_name;
                    }

                    this.options.service_config[name] = this._current_cfg;

                    this.element.find('#btnSave').css({'opacity': '0.8', 'cursor': 'default'}).attr('disabled', true);
                }else{ // no service and no service information is available

                    window.hWin.HEURIST4.msg.showMsgErr('An unknown error has occurred with setting this service\'s details');
                }

                this._reloadServiceList(); // reload left panel
                
            }else{
                window.hWin.HEURIST4.msg.showMsgFlash('Map at least one field listed', 3000);
            }
        }else{ 
            window.hWin.HEURIST4.msg.showMsgFlash('Select a service and a record type to map', 3000);
        }
    },
    
    //
    // Remove service's details, thus removing it completely
    //
    _removeConfig: function(service_id){

        if(window.hWin.HEURIST4.util.isempty(service_id)) { // check if a service was provided
            return;
        }

        if(this.options.service_config[service_id]!=null) { // normal method

            delete this.options.service_config[service_id]; // remove details

            this._reloadServiceList(); // reload left panel
            this._addNewService(); // reload right panel

            return;
        } else { // precautionary check

            var service_info = service_id.split("_"); // if new id, contains both service name and rty_ID

            var data = window.hWin.HEURIST4.util.isJSON(this.options.service_config); // get assigned service

            if(!data) { // check if isJSON returned false
                window.hWin.HEURIST4.msg.showMsgErr('No services have been saved');
                return;
            }

            for (idx in data){
                if (data[idx]['service'] == service_info[0] && data[idx]['rty_ID'] == service_info[1]){
                    
                    delete this.options.service_config[idx];

                    this._reloadServiceList(); // reload left panel
                    this._addNewService(); // reload right panel

                    return;
                }
            }
        }

        window.hWin.HEURIST4.msg.showMsgErr('An unknown error occurred while trying to remove this service');
    },

    //
    // Refresh the element if it is an instance of selectmenu/hSelect
    //
    selectMenuRefresh: function(selectMenu){

        if(selectMenu.hSelect('instance')){
            selectMenu.hSelect('refresh');
        }
    },    

});

