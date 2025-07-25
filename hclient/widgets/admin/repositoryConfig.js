/**
* @file repositoryConfig.js
* @brief configuration for external repositories
* @fileOverview
*
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay   <blmckay13@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @version     6.0
*/

/**
* @class repositoryConfig
* @augments baseConfig
* @memberof Widgets.Admin
* @description configuration for external repositories
*
* @property {object} options - Configuration options for the widget.
*/
$.widget( "heurist.repositoryConfig", $.heurist.baseConfig, {

    /**
    * @memberof Widgets.Admin.repositoryConfig
    * @type {object} Extends {@link baseConfig.options}.
    * @property {string} [title='External repositories configuration'] - The title displayed for the widget dialog.
    * @property {string} [htmlContent='repositoryConfig.html'] - The path to the HTML template file for the widget.
    * @property {string|null} [helpContent=null] - The path to the help content file.
    * @property {string} [type='repository'] - The type of configuration.
    */    
    options: {
        title: 'External repositories configuration',
        htmlContent: 'repositoryConfig.html',
        helpContent: null,

        type: 'repository'
    },

    /**
     * @function _init
     * @description load configuration and call _initControls
     * @memberof Widgets.Admin.repositoryConfig
     * @private
     */
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

        this.getConfigurations(this._super());
    },

    /**
     * @function _initControls
     * @description invoked from _init after loading of html content
     * @memberof Widgets.Admin.repositoryConfig
     * @private
     */
    _initControls:function(){

        //fill record type selector
        this.selectUserGroups = this._$('#sel_usergroup').css({'list-style-type': 'none'});

        window.hWin.HEURIST4.ui.createUserGroupsSelect(this.selectUserGroups.get(0), null, //show groups for current user only
            [{key:-1, title:'select group or user...'},
             {key:0, title:'Any logged-in user'}, 
             {key:window.hWin.HAPI4.user_id(), title:'Current user'}]);

        // on change handler
        this._on(this.selectUserGroups, { change: this._onUserGroupChange });

        let ele = this._$('#btnAddService').button({ icon: "ui-icon-plus" }).css('left', '165px');
        this._on(ele, {click: this._addNewService});

        this.btnApply = this._$('#btnApplyCfg').button().css("margin-right", "10px");
        this._on(this.btnApply, {click: this._applyConfig});            

        this.btnDiscard = this._$('#btnDiscard').button().hide();
        this._on(this.btnDiscard, {click: function(){this._removeConfig(null)}});            

        ele = this._$('input[data-field]')
        this._on(ele, {change: this._updateStatus, keyup: this._updateStatus});

        window.hWin.HEURIST4.ui.disableAutoFill(ele);

        return this._super();
    },

    /**
     * @function getConfigurations
     * @description get configurations from server for current user
     * @param {function} callback
     * @memberof Widgets.Admin.repositoryConfig
     */
    getConfigurations: function(callback){

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

            if(typeof callback === 'function'){
                callback.call(that);
            }

            window.hWin.HEURIST4.util.setDisabled(that.save_btn, true);
        });        
        
    },
    
    /**
     * @function saveConfigrations
     * @description save on server
     * @memberof Widgets.Admin.repositoryConfig
     */
    saveConfigrations: function(){

        let that = this;

        let request = {
            'a': 'update',
            'delete': that.options.service_remove, //to be deleted                
            'edit': JSON.stringify(that.options.service_config)   //to be updted
        };

        window.hWin.HAPI4.SystemMgr.repositoryAction(request, function(response){

            if(response.status == window.hWin.ResponseStatus.OK){

                that.options.service_remove = []; //reset

                that._is_modified = false;
                that._services_modified = false;

                window.hWin.HEURIST4.util.setDisabled(that.save_btn, !that._services_modified);
                window.hWin.HEURIST4.msg.showMsgFlash('Saved repositories configurations...', 3000);
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });

    },

    /**
     * @function _fillConfigForm
     * @description fill in contents of right panel
     * @param {string} service_id
     * @param {object} cfg0 - {service_id:'nakala_0', service:'nakala', usr_ID:0, params:{readApiKey:,readUser:,readPwd...}}
     * @memberof Widgets.Admin.repositoryConfig
     * @private
     */
    _fillConfigForm: function( service_id, cfg0 ){
        
        if(service_id && this.options.service_config[service_id]){
            cfg0 = this.options.service_config[service_id];
        }

        if( cfg0 ){

            this._current_cfg = cfg0;

            //fill values
            this._$('input[data-field]').val('');

            if(this._current_cfg.params){ // fill in values
                for(const [field, value] of Object.entries(this._current_cfg.params)){
                    $(`input[data-field=${field}]`).val(value);
                }
            }

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

            this._$('#service_name').html('');
            this._$('input[data-field]').val('');
            
            if(service_id=='new'){
                this._isNewCfg = true;
                this._current_cfg = {};
            }else{
                this._current_cfg = null;
            }
        }
        
        this._updateStatus();
    },
    
    /**
     * @function _updateStatus
     * @description set _is_modified flag
     * @memberof Widgets.Admin.repositoryConfig
     * @private
     */
    _updateStatus: function(){

        this._is_modified = false;

        if(this._current_cfg==null){

            this._$('#service_name').html('<span class="ui-icon ui-icon-arrowthick-1-w"></span>Select a repository to edit or click the assign button');
            this._$('#service_config').hide();

        }else{

            let usr_ID = this._current_cfg.usr_ID;
            let sName = usr_ID >= 0 ? window.hWin.HAPI4.SystemMgr.getUserNameLocal(usr_ID) : 'select group or user';

            let sSrvName = this._current_cfg.label ?? 'select repository';

            sName = `${sSrvName}<span class="ui-icon ui-icon-arrowthick-1-e"></span> ${sName}`;

            this._$('#service_name').html(sName);

            this._$('#service_config').show();

            this._checkModification();

            if(!$.isEmptyObject(this._current_cfg) || this.selectServiceType.val()){
                this._$('.service_details').show();
            }else{
                this._$('.service_details').hide();
            }

            if(this.selectUserGroups.val()){

                this._$('#service_params').show();
                this.btnApply.show();
            }else{

                this._$('#service_params').hide();
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

    _checkModification: function(){

        if($.isEmptyObject(this._current_cfg) || this._isNewCfg){ //new cfg

            this._$('#assign_fieldset').show();
            this._is_modified = true;
        }else{

            this._$('#assign_fieldset').hide();  //hide service selector
            this._$('.service_details').show();

            this._is_modified = this._current_cfg.usr_ID != this.selectUserGroups.val();
    
            if(!this._is_modified){
                this._super();
            }
        }
    },
    
    /**
     * @function _changeService
     * @description prepare form for service type change
     * @param {string} service_name
     * @memberof Widgets.Admin.repositoryConfig
     * @private
     */
    _changeService: function( service_name ){

        let cfg0 = this.getServiceDefInfo(service_name, false);

        this._fillConfigForm(null, cfg0);
    },

    /**
     * @function _onUserGroupChange
     * @description create map fields dropdowns
     * @memberof Widgets.Admin.repositoryConfig
     * @private
     */
    _onUserGroupChange: function(){
     
        let usr_ID = this.selectUserGroups.val();   
        
        if(usr_ID>=0){
            this._$('#service_params').show();
            this.btnApply.show();
        }else{
            this._$('#service_params').hide();
            this.btnApply.hide();
        }
        
        if(this._isNewCfg && this._current_cfg.label){

            let sName = 'select group or user';
            if(usr_ID>=0){
                sName = window.hWin.HAPI4.SystemMgr.getUserNameLocal(usr_ID);    
            }
            
            sName = `${this._current_cfg.label}<span class="ui-icon ui-icon-arrowthick-1-e"></span> ${sName}`;
            this.serviceList.find('li[data-service-id="new"]').html(sName);
            this._$('#service_name').html(sName);
        }
        
    },

    /**
     * @function _applyConfig
     * @description save current service details
     * @memberof Widgets.Admin.repositoryConfig
     * @private
     */
    _applyConfig: function(){

        let usr_ID = this.selectUserGroups.val();
        let service_name = this.selectServiceType.val();

        if(window.hWin.HEURIST4.util.isempty(this._current_cfg)){

            // no service and no service information is available
            window.hWin.HEURIST4.msg.showMsgFlash('Select or define new service first');

        }else if(usr_ID>=0 && !window.hWin.HEURIST4.util.isempty(service_name)){ // check if a service and table have been selected

            let is_field_assigned = false;

            let inputs = this._$('input[data-field]');
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

                let t_name = `${service_name}_${usr_ID}`;

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
    
    /**
     * @function _removeConfig
     * @description Remove service's details, thus removing it completely
     * @param {string} service_id
     * @memberof Widgets.Admin.repositoryConfig
     * @private
     */
    _removeConfig: function(service_id){

        if(this.options.service_config[service_id] != null && this.options.service_remove.indexOf(service_id) < 0){
            this.options.service_remove.push(service_id);
        }

        return this._super(service_id);
    }
});