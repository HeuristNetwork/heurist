/**
* lookupConfig.js - configuration for record lookup services
*                       original config is hserv/controller/record_lookup_config.json
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
$.widget( "heurist.repositoryConfig", {

    // default options
    options: {
    
        //DIALOG section       
        isdialog: false,     // show as dialog @see  _initDialog(), popupDialog(), closeDialog
        height: 640,
        width:  900,
        modal:  true,
        title:  '',
        htmlContent: 'repositoryConfig.html',
        helpContent: null,
        
        //parameters
        service_config: {}, // all assigned services
        service_remove: [], // services to be removed
        
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
    selectUserGroups:null, //selector user and groups
    selectServiceType: null, //selector for lookup service/repository
    serviceList: null, //left panel list

    example_results: {},    
    
    save_btn: null,
    close_btn: null,
    
    // the widget's constructor                            
    _create: function() {
        // prevent double click to select text
       
    }, //end _create
    
    //
    //  load configuration and call _initControls
    //
    _init: function() {
        

        let _services = ['Nakala','Flickr','Zenodo','Isidore','MediHAL','DSpace'];
        this._available_services = [];
        for(let idx in _services){
            this._available_services.push({service:_services[idx].toLowerCase(),label:_services[idx]});
        }
        
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(this._available_services)){
            window.hWin.HEURIST4.msg.showMsgErr({
                message: 'There are no available services, or the configuration file was not found or is broken',
                error_title: 'No external services'
            });
            return;
        }
        
        let that = this;
        
        this.element.addClass('ui-heurist-design');
        
        if(this.options.isdialog){  //show this widget as popup dialog
            this._initDialog();
        }
        
        //load html content from file
        if(this._need_load_content && this.options.htmlContent){        
            this.element.load(window.hWin.HAPI4.baseURL+'hclient/widgets/admin/'+this.options.htmlContent
                            +'?t='+window.hWin.HEURIST4.util.random(), 
            function(response, status, xhr){
                that._need_load_content = false;
                if ( status == "error" ) {
                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: response,
                        error_title: 'Failed to load HTML content',
                        status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                    });
                }else{
                    that.getConfigurations();
                }
            });
            return;
        }else{
            this.getConfigurations();
        }
    },
    
    // 
    // custom, widget-specific, cleanup.
    //
    _destroy: function() {
        // remove generated elements
        if(this.selectServiceType) this.selectServiceType.remove();
        if(this.selectUserGroups) this.selectUserGroups.remove();
        if(this.serviceList) this.serviceList.remove();

    },
    
    //
    // array of button defintions
    //
    _getActionButtons: function(){

        let that = this;

        return [
            {
                text:window.hWin.HR('Close'), 
                id:'btnClose',
                css:{'float':'right','margin-left':'30px'}, 
                click: function() { 
                    that._closeHandler(false, false, null);
                }
            },
            {
                text:window.hWin.HR('Save'),
                id:'btnSave',
                css:{'float':'right'},
                click: function() {
                    that._closeHandler(true, false, null);
                },
                class: "ui-button-action"
            }
        ];
    },

    //
    // init dialog widget
    // see also popupDialog, closeDialog 
    //
    _initDialog: function(){
        
        let options = this.options,
            btn_array = this._getActionButtons(), 
            position = null,
                that = this;
    
        if(!options.beforeClose){
                options.beforeClose = function(){
                    return true;
                };
        }
        
        if(position==null) position = { my: "center", at: "center", of: window };
        let maxw = (window.hWin?window.hWin.innerWidth:window.innerWidth);
        if(options['width']>maxw) options['width'] = maxw*0.95;
        let maxh = (window.hWin?window.hWin.innerHeight:window.innerHeight);
        if(options['height']>maxh) options['height'] = maxh*0.95;
        
        
        let $dlg = this.element.dialog({
            autoOpen: false ,
            //element: this.element[0],
            height: options['height'],
            width:  options['width'],
            modal:  (options['modal']!==false),
            title: 'External repositories configuration',
            position: position,
            beforeClose: options.beforeClose,
            resizeStop: function( event, ui ) {//fix bug
                that.element.css({overflow: 'none !important','width':that.element.parent().width()-24 });
            },
            close:function(){
                if(window.hWin.HEURIST4.util.isFunction(that.options.onClose)){
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
                let helpURL = window.hWin.HRes( this.options.helpContent )+' #content';
                window.hWin.HEURIST4.ui.initDialogHintButtons(this._as_dialog, null, helpURL, false);    
            }
            
            this.save_btn = this._as_dialog.find('#btnSave');
            this.close_btn = this._as_dialog.find('#btnClose');
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
        
        let that = this;

        //fill record type selector
        this.selectUserGroups = this.element.find('#sel_usergroup').css({'list-style-type': 'none'});
        
       
        
        window.hWin.HEURIST4.ui.createUserGroupsSelect(this.selectUserGroups.get(0), null, //show groups for current user only
            [{key:-1, title:'select group or user...'},
             {key:0, title:'Any logged-in user'}, 
             {key:window.hWin.HAPI4.user_id(), title:'Current user'}]);
        
        
        // on change handler
        this._on(this.selectUserGroups, { change: this._onUserGroupChange });
        
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
                let service = that.selectServiceType.val(); // selected service
                that._changeService( service ); // setup
            }
        });

        let ele = this.element.find('#btnAddService').button({ icon: "ui-icon-plus" }).css('left', '165px');
        this._on(ele, {click: this._addNewService});
        
        this.btnApply = this.element.find('#btnApplyCfg').button().css("margin-right", "10px");
        this._on(this.btnApply, {click: this._applyConfig});            

        this.btnDiscard = this.element.find('#btnDiscard').button().hide();
        this._on(this.btnDiscard, {click: function(){this._removeConfig(null)}});            

        ele = this.element.find('input[data-field]')
        this._on(ele, {change:this._updateStatus});
        
        window.hWin.HEURIST4.ui.disableAutoFill(ele);

        this._updateStatus();
        
        if(this.options.isdialog){
            
            this.element.find('.popup_buttons_div, .ui-heurist-header').hide();
            this.element.find('div.ent_content').toggleClass(['ent_content', 'ent_content_full']).css('top', '-0.2em');

            this.popupDialog();
        }else{

            // add title/heading
            this.element.find('.ui-heurist-header').text(this.options.title);

            // bottom bar buttons
            this.save_btn = this.element.find('#btnSave').button().on('click', function() {that._closeHandler(true, false, null);} );
            this.close_btn = this.element.find('#btnClose').button().on('click', function() {that._closeHandler(false, false, null);} );

            // mouse leaves container
            this.element.find('.ent_wrapper:first').on('mouseleave', function(event) {

                if($(event.target).is('div') && (that._is_modified || that._services_modified) && !that._isNewCfg){
                    that._closeHandler(false, true, $(event.target));
                }
            } );
        }
        
        window.hWin.HEURIST4.util.setDisabled(this.save_btn, !this._services_modified);
        //show hide hints and helps according to current level
        window.hWin.HEURIST4.ui.applyCompetencyLevel(-1, this.element); 

        let rpanel_width = this.element.find('#editing_panel').width() - 40;
        this.element.find('#service_params').width(rpanel_width);

        return true;
    },

    //
    // get configurations from server for current user
    //
    getConfigurations: function(){

        let that = this;

        window.hWin.HAPI4.SystemMgr.repositoryAction({'a': 'get'}, function(response){
            if(response.status == window.hWin.ResponseStatus.OK){
                that.options.service_config = window.hWin.HEURIST4.util.isJSON(response.data);
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
            
            that.options.service_remove = []; //reset
            if(!that.options.service_config || that.options.service_config.length==0){ // Invalid value / None
                that.options.service_config = {};    
            } 
            that._is_modified = false;
            that._services_modified = false;

            if(that._initControls()){
                if(window.hWin.HEURIST4.util.isFunction(that.options.onInitFinished)){
                    that.options.onInitFinished.call(that);
                }        
            }
            
            window.hWin.HEURIST4.util.setDisabled(that.save_btn, true);
        });        
        
    },
    
    //
    // save on server
    //
    saveConfigrations: function(){

        let that = this;

        let request = {
            'a': 'update',
            'delete': that.options.service_remove, //to be deleted                
            'edit': JSON.stringify(that.options.service_config)   //to be updted
        };
//JSON.stringify        
console.log( JSON.stringify(that.options.service_config) );        
        
        window.hWin.HAPI4.SystemMgr.repositoryAction(request, function(response){

            if(response.status == window.hWin.ResponseStatus.OK){
                that.options.service_remove = []; //reset
                //window.hWin.HAPI4.sysinfo['repository_config'] = window.hWin.HEURIST4.util.cloneJSON(that.options.service_config); // update local copy

                that._is_modified = false;
                that._services_modified = false;

                window.hWin.HEURIST4.util.setDisabled(that.save_btn, !that._services_modified);
                window.hWin.HEURIST4.msg.showMsgFlash('Saved repositories configurations...', 3000);
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });

    },

    //
    // on close 
    //
    _closeHandler: function(isSave=false, isMouseLeave=false, trigger){

        let that = this;

        let hasChanges = (this._is_modified || this._services_modified);

        let $dlg, buttons = {};

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

            let wording = this._is_modified ? 'current configuration' : 'available services';
            let button = this._is_modified ? '"Apply"' : '"Save"'

            $dlg = window.hWin.HEURIST4.msg.showMsgDlg('You have made changes to the '+wording+'. Click '+button+' otherwise all changes will be lost.', 
                buttons, {title: 'Unsaved Changes', yes: 'Save', no: 'Ignore and Close'});
        }else{
            if(isSave){
                this.saveConfigrations();
            }else{
                if(this.options.isdialog && this._as_dialog.dialog('instance') !== undefined){
                    this._as_dialog.dialog('close');
                }else{
                    this.element.empty().hide();
                }
            }
        }
    },

    //
    // Get list of available services
    //
    _getServiceSelectmenu: function(){

        let options = [];

        let values = {};

        for(let idx in this._available_services){

            values = {
                title: this._available_services[idx].label,
                key: this._available_services[idx].service,
                disabled: false,
                selected: false,
                hidden: false
            };

            options.push(values);
        }

        options.sort((a, b) => { return a.title > b.title; });

        values = {
            title: 'select a repository...',
            key: '',
            disabled: true,
            selected: true,
            hidden: true
        }; // top option

        options.unshift(values);

        this.selectServiceType = window.hWin.HEURIST4.ui.createSelector(this.selectServiceType.get(0), options); // create dropdown
        this.selectServiceType = $(this.selectServiceType);
        window.hWin.HEURIST4.ui.initHSelect(this.selectServiceType, false); // initial selectmenu

        if(this.selectServiceType.hSelect('instance')!=undefined){

            this.selectServiceType.hSelect('widget').css('width', 'auto');
            this.selectServiceType.hSelect('menuWidget').css('max-height', ''); // remove to force dropdown to scroll
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
        
        let ele = this._reloadServiceList_item( 'new', 'assign on right ...' );
    },
    
    //
    // fill in contents of right panel
    //    
    // {service_id:'nakala_0', service:'nakala', usr_ID:0, params:{readApiKey:,readUser:,readPwd...}}
    //
    _fillConfigForm: function( service_id, cfg0 ){
        
        if(service_id && this.options.service_config[service_id]){
            cfg0 = this.options.service_config[service_id];
        }

        if( cfg0 ){

            this._current_cfg = cfg0;
            
           
            
            //fill values
            this.element.find('input[data-field]').val('');

            $.each(this._current_cfg.params, function(field, value){
                $('input[data-field='+field+']').val(value);     
            });

            let usr_ID = this._current_cfg.usr_ID;
            
            //select service and type
            if(cfg0.service) {
                this.selectServiceType.val(cfg0.service);
            }

            this.selectUserGroups.val( usr_ID );
            this._onUserGroupChange();
        }else{
            
            this.selectServiceType.val('');
            this.selectUserGroups.val(-1);

            this.element.find('#service_name').html('');
            this.element.find('input[data-field]').val('');
            
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
            
            this.element.find('#service_name').html('<span class="ui-icon ui-icon-arrowthick-1-w"></span>Select a repository to edit or click the assign button');
            this.element.find('#service_config').hide();
            
        }else{
            
            let sName = 'select group or user';
            let usr_ID = this._current_cfg.usr_ID;
            if(usr_ID>=0){
                sName = window.hWin.HAPI4.SystemMgr.getUserNameLocal(usr_ID);    
            }
            let sSrvName = 'select repository';
            if(this._current_cfg.label){
                sSrvName = this._current_cfg.label
            }
            
            sName = sSrvName + '<span class="ui-icon ui-icon-arrowthick-1-e"></span> ' 
                    +  sName;
            
            this.element.find('#service_name').html(sName);
            
            
            this.element.find('#service_config').show();
            
            if($.isEmptyObject(this._current_cfg) || this._isNewCfg){ //new cfg

                this.element.find('#assign_fieldset').show();
                this._is_modified = true;
            }else{

                this.element.find('#assign_fieldset').hide();  //hide service selector
                this.element.find('.service_details').show();
                
                //verify if modified
                this._is_modified =  (this._current_cfg.usr_ID != this.selectUserGroups.val()); 
                if(!this._is_modified){

                    let inputs = this.element.find('input[data-field]');
                    let fields = {};
                    let that = this;
                    $.each(inputs, function(i, ele){ // get mapped fields
                
                        let field = $(ele).attr('data-field');
                        let value = $(ele).val();
                        
                        if(that._current_cfg.params[field]!=value){

                            if(!(that._current_cfg.params[field] == null && value == "")){
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

            if(this.selectUserGroups.val()){

                this.element.find('#service_params').show();
                this.btnApply.show();
            }else{

                this.element.find('#service_params').hide();
                this.btnApply.hide();
            }
        }
            
        // refresh dropdowns
        this.selectMenuRefresh(this.selectServiceType);
        this.selectMenuRefresh(this.selectUserGroups);

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

        const that = this;
        let cfg0 = null;

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
    _onUserGroupChange: function(){
     
        let usr_ID = this.selectUserGroups.val();   
        
        let that = this;
        
        if(usr_ID>=0){
            this.element.find('#service_params').show();
            this.btnApply.show();
        }else{
            this.element.find('#service_params').hide();
            this.btnApply.hide();
        }
        
        if(this._isNewCfg && this._current_cfg.label){

            let sName = 'select group or user';
            if(usr_ID>=0){
                sName = window.hWin.HAPI4.SystemMgr.getUserNameLocal(usr_ID);    
            }
            
            sName = this._current_cfg.label + '<span class="ui-icon ui-icon-arrowthick-1-e"></span> ' 
                    +  sName;
            this.serviceList.find('li[data-service-id="new"]').html(sName);
            this.element.find('#service_name').html(sName);
        }
        
    },
    
    //
    // refresh assigned service list, popup's left panel
    //
    _reloadServiceList: function(){
      
        let that = this;

        this._off(this.serviceList.find('span[data-service-id]'),'click');
        this.serviceList.empty(); // empty list

        for(let idx in this.options.service_config){ // display all assigned services

            let cfg = this.options.service_config[idx];

            if(window.hWin.HEURIST4.util.isempty(cfg)){
                continue;
            }

            let name = cfg.label;
            
            for(let j in this._available_services){
                if(cfg.service == this._available_services[j].service){
                    name = this._available_services[j].label;
                }
            }

            let s = name + ' <span class="ui-icon ui-icon-arrowthick-1-e"></span> ' 
                    + window.hWin.HAPI4.SystemMgr.getUserNameLocal(cfg.usr_ID);
            s = s + '<span data-service-id="'+idx+'" style="float:right;padding-top: 5px" class="ui-icon ui-icon-circle-b-close"></span>';

            this._reloadServiceList_item( idx, s ); //add to list
        }
        
        
        this.serviceList.find('li')
        .on( 'mouseenter', function(event){ // service list hover event
            let ele = $(event.target);
            if(!ele.is('li')) ele = ele.parent();
            ele.addClass('ui-state-hover');
        })
        .on( 'mouseleave', function(event){
            let ele = $(event.target);
            if(!ele.is('li')){ 
                ele.removeClass('ui-state-hover'); // ensure that this element does not have the hover state
                ele = ele.parent();
            }
            ele.removeClass('ui-state-hover');
        });

        let eles = this.serviceList.find('span[data-service-id]');
        this._on(eles,{'click':function(event)
        { // remove service button
            that._removeConfig($(event.target).attr('data-service-id'));
        }}); 
        
        

    },
    
    //
    //
    //
    _reloadServiceList_item: function( service_id, s ){
        
            let s_active = '';
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

        let usr_ID = this.selectUserGroups.val();
        let service_name = this.selectServiceType.val();

        if(window.hWin.HEURIST4.util.isempty(this._current_cfg)){

            // no service and no service information is available
            window.hWin.HEURIST4.msg.showMsgFlash('Select or define new service first');

        }else if(usr_ID>=0 && !window.hWin.HEURIST4.util.isempty(service_name)){ // check if a service and table have been selected

            let that = this;
            let is_field_assigned = false;

            let inputs = this.element.find('input[data-field]');
            let fields = {};
            
            $.each(inputs, function(i, ele){ // get mapped fields
        
                let field = $(ele).attr('data-field');
               
                
                fields[field] = $(ele).val();
                if(fields[field]!=''){
                    is_field_assigned = true;    
                }
            });
            

            if(is_field_assigned){

                this.options.service_config = window.hWin.HEURIST4.util.isJSON(this.options.service_config); // get existing assigned services
                if(!this.options.service_config){ // Invalid value / None
                    this.options.service_config = {};    
                } 

                let t_name = service_name + '_' + usr_ID;

                // save changes

                //if rectype has been changed - remove previous one                
                if(t_name != this._current_cfg.service_id && this.options.service_config[t_name]){
                    delete this.options.service_config[t_name];
                }

                this._current_cfg.service_id = t_name;
                this._current_cfg.usr_ID = usr_ID;
                this._current_cfg.service = service_name;
                this._current_cfg.params = fields;

                this.options.service_config[t_name] = this._current_cfg;

                this._isNewCfg = false;

                this._services_modified = true;
                window.hWin.HEURIST4.util.setDisabled(this.save_btn, !this._services_modified);

                this._reloadServiceList(); // reload left panel

                this._updateStatus(); // update is modified

            }else{
                window.hWin.HEURIST4.msg.showMsgFlash('Define at least one parameter', 3000);
            }
        }else{ 
            window.hWin.HEURIST4.msg.showMsgFlash('Select a repository and a user/group', 2000);
        }
    },
    
    //
    // Remove service's details, thus removing it completely
    //
    _removeConfig: function(service_id){

        let is_del = false;
        if(window.hWin.HEURIST4.util.isempty(service_id)) { // check if a service was provided
            if(this._isNewCfg){
                this._isNewCfg = false;
                is_del = true;
            }
        }

        if(this.options.service_config[service_id]!=null) { // check if service has been assigned
        
            if(this.options.service_remove.indexOf(service_id)<0){
                this.options.service_remove.push(service_id);
            }
        
            delete this.options.service_config[service_id]; // remove assigned service
            is_del = true;

            this._services_modified = true;
            window.hWin.HEURIST4.util.setDisabled(this.save_btn, !this._services_modified);
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
