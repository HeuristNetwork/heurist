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
    _is_modified: false, // is current service modified
    _available_services:null, // list of available services
    _services_modified: false, // has any service been removed/added
    _isNewCfg: false,

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
            
            if(this.options.helpContent){
                var helpURL = window.hWin.HRes( this.options.helpContent )+' #content';
                window.hWin.HEURIST4.ui.initDialogHintButtons(this._as_dialog, null, helpURL, false);    
            }
            
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
    // invoked from _init after loading of html content
    //
    _initControls:function(){
        
        var that = this;

        // check that all assigned services contain valid details
        this.updateOldConfigurations();

        //fill record type selector
        this.selectRecordType = this.element.find('#sel_rectype').css({'list-style-type': 'none'});
        this.selectRecordType = window.hWin.HEURIST4.ui.createRectypeSelectNew(this.selectRecordType.get(0),
            {topOptions:'select record type'});
        // on change handler
        this._on(this.selectRecordType, { change: this._onRectypeChange });
        
        //fill service configuration list
        this.serviceList = this.element.find('#sel_service');
        this._reloadServiceList();
        // on selected handler
        this.serviceList.selectable( {
            cancel: '.ui-icon-circle-b-close',  // service delete "button"
            selected: function( event, ui ) {
                if($(ui.selected).is('li')){
                    
                    if($(ui.selected).hasClass('unfinished')){
                        window.hWin.HEURIST4.msg.showMsgFlash('Complete or discard unfinished one',700);
                        return;  
                    }

                    that.serviceList.find('li').removeClass('ui-state-active');
                    that.serviceList.find('div').removeClass('ui-state-active');
                    $(ui.selected).addClass('ui-state-active');

                    //load configuration into right hand form
                    that._fillConfigForm( $(ui.selected).attr('data-service-id') );
                }
            }
        });

        //fill service types   (selector)
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
        this._on(ele, {input: this._updateStatus });

        ele = this.element.find('#btnAddService').button({ icon: "ui-icon-plus" }).css('left', '165px');
        this._on(ele, {click: this._addNewService});

        this.btnApply = this.element.find('#btnApplyCfg').button().css("margin-right", "10px");
        this._on(this.btnApply, {click: this._applyConfig});            

        this.btnDiscard = this.element.find('#btnDiscard').button().hide();
        this._on(this.btnDiscard, {click: function(){this._removeConfig(null)}});            

        this._updateStatus();
        
        if(this.options.isdialog){
            
            this.element.find('.popup_buttons_div, .ui-heurist-header').hide();
            this.element.find('div.ent_content').toggleClass(['ent_content', 'ent_content_full']).css('top', '-0.2em');

            this.popupDialog();
        }else{

            // add title/heading
            this.element.find('.ui-heurist-header').text(this.options.title);

            // bottom bar buttons
            this.element.find('#btnSave').button().on('click', function() {that._closeHandler(true, false, null);} );
            this.element.find('#btnClose').button().on('click', function() {that._closeHandler(false, false, null);} );

            // mouse leaves container
            this.element.find('.ent_wrapper:first').on('mouseleave', function(event) {

                if($(event.target).is('div') && (that._is_modified || that._services_modified)){
                    that._closeHandler(false, true, $(event.target));
                }
            } );
        }
        
        //show hide hints and helps according to current level
        window.hWin.HEURIST4.ui.applyCompetencyLevel(-1, this.element); 

        return true;
    },

    updateOldConfigurations: function(){

        var that = this;
        var has_changes = false;

        $.each(this.options.service_config, function(key, value){

            // Check that the service property has been defined
            if(that.options.service_config[key]['service'] == null){

                // Likely has service_name instead
                if(that.options.service_config[key]['service_name'] != null){
                    that.options.service_config[key]['service'] = that.options.service_config[key]['service_name'];
                    delete that.options.service_config[key]['service_name'];
                }else{ // invalid configuration, missing a service name
                    delete that.options.service_config[key];
                }

                has_changes = true;
            }else if(that.options.service_config[key]['service_name'] != null){
                delete that.options.service_config[key]['service_name'];

                has_changes = true;
            }

            // Check that the service id (serviceName_rtyID) has been defined
            if(value.service_id == null){
                that.options.service_config[key]['service_id'] = that.options.service_config[key]['service'] + '_' + that.options.service_config[key]['rty_ID'];

                has_changes = true;
            }

            // Ensure that the key is correct, otherwise there will be problems with updating (creating duplicates)
            if(key.includes("_") === false){

                var new_key = that.options.service_config[key]['service_id'];
                that.options.service_config[new_key] = window.hWin.HEURIST4.util.cloneJSON(that.options.service_config[key]);

                delete that.options.service_config[key];

                has_changes = true;
            }
        });

        // Update with new changes
        if(has_changes){
            this.saveConfigrations();            
        }
    },

    saveConfigrations: function(){

        var that = this;

        var fields = {
            'sys_ID': 1,
            'sys_ExternalReferenceLookups': JSON.stringify(this.options.service_config)
        };

        // Update sysIdentification record
        var request = {
            'a': 'save',
            'entity': 'sysIdentification',
            'request_id': window.hWin.HEURIST4.util.random(),
            'isfull': 0,
            'fields': fields
        };

        window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){

            if(response.status == window.hWin.ResponseStatus.OK){
                window.hWin.HAPI4.sysinfo['service_config'] = window.hWin.HEURIST4.util.cloneJSON(that.options.service_config); // update local copy

                that._is_modified = false;
                that._services_modified = false;
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    },

    //
    //
    //
    _closeHandler: function(isSave=false, isMouseLeave=false, trigger){

        var that = this;

        var hasChanges = (this._is_modified || this._services_modified);

        var $dlg, buttons = {};

        buttons['Save'] = function(){
            
            if(that._is_modified){
                that._applyConfig();
            }

            $dlg.dialog('close');

            // Update sysIdentification record
            that.saveConfigrations();
        };

        buttons['Ignore and close'] = function(){ 
            $dlg.dialog('close');
            that.element.empty().hide();
        };

        if(!isSave && trigger && !trigger.is('button') && hasChanges){

            var wording = this._is_modified ? 'current configuration' : 'available services';
            var button = this._is_modified ? '"Apply"' : '"Save"'

            $dlg = window.hWin.HEURIST4.msg.showMsgDlg('You have made changes to the '+wording+'. Click '+button+' otherwise all changes will be lost.', 
                buttons, {title: 'Unsaved Changes', yes: 'Save', no: 'Ignore and Close'});
        }else{
            if(isSave){
                this.saveConfigrations();
            }else{
                this.element.empty().hide();
            }
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

        if(this.selectServiceType.hSelect('instance')!=undefined){

            this.selectServiceType.hSelect('widget').css('width', 'auto');
        }

    },    

    //
    // prepare forms for new service
    //
    _addNewService: function(){

        if(this._isNewCfg){
            window.hWin.HEURIST4.msg.showMsgFlash('Complete or discard unfinished one',700);
            return;
        }
        
        // empty all inputs
        this.serviceList.find('li').removeClass('ui-state-active');
        

        // empty control variables
        this._fillConfigForm('new');
        
        var ele = this._reloadServiceList_item( 'new', 'assign on right ...' );
    },
    
    //
    // fill in contents of right panel
    //    
    _fillConfigForm: function( service_id, cfg0 ){
        
        if(service_id && this.options.service_config[service_id]){
            cfg0 = this.options.service_config[service_id];
        }

        if( cfg0 ){

            this._current_cfg = cfg0;
            
            this.element.find('#service_name').html(cfg0.label);
            this.element.find('#service_description').html('<strong>' + cfg0.service + '</strong>: ' + cfg0.description);
            this.element.find('#inpt_label').val(cfg0.label);
            
            var tbl = this.element.find('#tbl_matches');
            tbl.empty();

            $.each(this._current_cfg.fields, function(field, code){
                $('<tr><td>'+field+'</td><td><select data-field="'+field+'"/></td></tr>').appendTo(tbl);
            });

            var rty_ID = this._current_cfg.rty_ID>0 ?$Db.getLocalID('rty',this._current_cfg.rty_ID) :'';
            
            //select service and type
            if(cfg0.service_name) {
                this.selectServiceType.val(cfg0.service);
            }

            this.selectRecordType.val( rty_ID );
            this._onRectypeChange();
        }else{
            
            this.selectServiceType.val('');
            this.selectRecordType.val('');
            this.element.find('#inpt_label').val('');

            this.element.find('#service_name').html('');
            
            if(service_id=='new'){
                this._isNewCfg = true;
                this._current_cfg = {};
            }else{
                this._current_cfg = null;
            }
        }
        
        this._updateStatus();
    },
    
    //
    // set _is_modified flag
    //
    _updateStatus: function(){
        
        this._is_modified = false;

        if(this._current_cfg==null){
            
            this.element.find('#service_name').html('<span class="ui-icon ui-icon-arrowthick-1-w"/>Select a service to edit or click the assign button');
            this.element.find('#service_config').hide();
            
        }else{
            this.element.find('#service_config').show();
            
            if($.isEmptyObject(this._current_cfg) || this._isNewCfg){ //new cfg

                this.element.find('#service_type').show();
                this._is_modified = true;
            }else{

                this.element.find('#service_type').hide();  //hide service selector
                this.element.find('.service_details').show();
                
                //verify if modified
                this._is_modified =  (this._current_cfg.rty_ID != this.selectRecordType.val())
                               || (this._current_cfg.label != this.element.find('#inpt_label').val()); 
                if(!this._is_modified){

                    var tbl = this.element.find('#tbl_matches');
                    var fields = {};
                    var that = this;
                    $.each(tbl.find('select'), function(i, ele){ // get mapped fields
                
                        var field = $(ele).attr('data-field');
                        var dty_ID = $(ele).val();

                        if(dty_ID == ""){
                            //dty_ID = null;
                        }
                        
                        if(that._current_cfg.fields[field]!=dty_ID){

                            if(!(that._current_cfg.fields[field] == null && dty_ID == "")){
                                that._is_modified = true;
                                return false; //break
                            }
                        }
                    });
                }
            }

            if(!$.isEmptyObject(this._current_cfg) || this.selectServiceType.val()){

                this.element.find('.service_details').show();
            }else{

                this.element.find('.service_details').hide();
            }

            if(this.selectRecordType.val()){

                this.element.find('#service_mapping').show();
                this.btnApply.show();
            }else{

                this.element.find('#service_mapping').hide();
                this.btnApply.hide();
            }
        }
            
        // refresh dropdowns
        this.selectMenuRefresh(this.selectServiceType);
        this.selectMenuRefresh(this.selectRecordType);

        this.btnDiscard.show();

        
        window.hWin.HEURIST4.util.setDisabled(this.btnApply, !this._is_modified);
        if(this._is_modified){
            this.btnApply.addClass('ui-button-action');
        }else{
            this.btnApply.removeClass('ui-button-action');   
        }
    },
    
    //
    // prepare form for service type change
    //
    _changeService: function( service_name ){

        var cfg0 = null;

        $.each(this._available_services, function(i, srv){ // get new service info
          if(srv.service==service_name){
              cfg0 = window.hWin.HEURIST4.util.cloneJSON(srv);
              return false;
          }
        });
        
        this._fillConfigForm(null, cfg0);
    },

    //
    // create map fields dropdowns
    //
    _onRectypeChange: function(){
     
        var rty_ID = this.selectRecordType.val();   
        
        var tbl = this.element.find('#tbl_matches');
        
        var that = this;
        
        
        $.each(tbl.find('select'), function(i,selObj){

            
            if($(selObj).hSelect("instance")!=undefined){
               that._off($(selObj).hSelect("instance"),'change');
               $(selObj).hSelect("destroy"); 
            }
            $(selObj).empty();
        });
        
        
        if(rty_ID>0){
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
                
                var sel = window.hWin.HEURIST4.ui.createRectypeDetailSelect(ele, rty_ID, 
                    ['freetext','blocktext','enum','date','geo','float','year','integer','resource'], '...', 
                    {show_latlong:true, show_dt_name:true, selectedValue:dty_ID} );
                    
                that._on($(sel), {change:function(){that._updateStatus();}});
            });
            
            this.element.find('#service_mapping').show();
            this.btnApply.show();
            
        }else{
			
            this.element.find('#service_mapping').hide();
            this.btnApply.hide();
        }
        
        
        if(this._isNewCfg && this._current_cfg.label){

            var s = this._current_cfg.label + '<span class="ui-icon ui-icon-arrowthick-1-e"/> ' 
                    +  (rty_ID>0?$Db.rty(rty_ID, 'rty_Name'):'select record type');
            this.serviceList.find('li[data-service-id="new"]').html(s);
        }
        
    },
    
    //
    // refresh assigned service list, popup's left panel
    //
    _reloadServiceList: function(){
      
        var that = this;

        this._off(this.serviceList.find('span[data-service-id]'),'click');
        this.serviceList.empty(); // empty list

        for(idx in this.options.service_config){ // display all assigned services

            var cfg = this.options.service_config[idx];

            if(window.hWin.HEURIST4.util.isempty(cfg)){
                continue;
            }

            var name = cfg.label;
            
            for(j in this._available_services){
                if(cfg.service == this._available_services[j].service){
                    name = this._available_services[j].label;
                }
            }

            s = name + ' <span class="ui-icon ui-icon-arrowthick-1-e"/> ' 
                    + $Db.rty(cfg.rty_ID, 'rty_Name');
            s = s + '<span data-service-id="'+idx+'" style="float:right;padding-top: 5px" class="ui-icon ui-icon-circle-b-close"/>';

            this._reloadServiceList_item( idx, s ); //add to list
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

        var eles = this.serviceList.find('span[data-service-id]');
        this._on(eles,{'click':function(event)
        { // remove service button
            that._removeConfig($(event.target).attr('data-service-id'));
        }}); 
        
        

    },
    
    _reloadServiceList_item: function( service_id, s ){
        
            var s_active = '';
            if(service_id=='new' || (this._current_cfg && this._current_cfg.service_id==service_id)){
                s_active = ' ui-state-active';
            }

            return $('<li class="ui-widget-content'+s_active+'" data-service-id="'+service_id+'">'+s+'</li>')  
                .css({margin: '5px 2px 2px', padding: '0.4em', cursor:'pointer', background:'#e0dfe0'}) 
                .appendTo(this.serviceList);    
    
    },
    
    //
    // save current service details
    //
    _applyConfig: function(){

        var rty_ID = this.selectRecordType.val();
        var service_name = this.selectServiceType.val();
        var label = this.element.find('#inpt_label').val();

        if(window.hWin.HEURIST4.util.isempty(this._current_cfg)){

            // no service and no service information is available
            window.hWin.HEURIST4.msg.showMsgFlash('Select or define new service first');

        }else if(rty_ID>0 && !window.hWin.HEURIST4.util.isempty(service_name)){ // check if a service and table have been selected

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

                var t_name = service_name + '_' + rty_ID;

                if(window.hWin.HEURIST4.util.isempty(label)){ // set label to default, if none provided
                    label = service_name;
                }

                // save changes

                //if rectype has been changed - remove previous one                
                if(t_name != this._current_cfg.service_id && this.options.service_config[t_name]){
                    delete this.options.service_config[t_name];
                }

                this._current_cfg.service_id = t_name;
                this._current_cfg.rty_ID = rty_ID;
                this._current_cfg.label = label;
                this._current_cfg.service = service_name;
                this._current_cfg.fields = fields;

                this.options.service_config[t_name] = this._current_cfg;

                this._isNewCfg = false;

                this._services_modified = true;

                this._reloadServiceList(); // reload left panel

                this._updateStatus(); // update is modified

            }else{
                window.hWin.HEURIST4.msg.showMsgFlash('Map at least one field listed', 3000);
            }
        }else{ 
            window.hWin.HEURIST4.msg.showMsgFlash('Select a service and a record type to map', 2000);
        }
    },
    
    //
    // Remove service's details, thus removing it completely
    //
    _removeConfig: function(service_id){

        var is_del = false;
        if(window.hWin.HEURIST4.util.isempty(service_id)) { // check if a service was provided
            if(this._isNewCfg){
                this._isNewCfg = false;
                is_del = true;
            }
        }

        if(this.options.service_config[service_id]!=null) { // check if service has been assigned
            delete this.options.service_config[service_id]; // remove assigned service
            is_del = true;
            this._services_modified = true;
        }

        if(is_del){
            this._reloadServiceList();
            this._current_cfg = null;
            this._updateStatus();
        }else if(this._current_cfg){
            //reload
            this._fillConfigForm(this._current_cfg.service_id);
        }
        
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
