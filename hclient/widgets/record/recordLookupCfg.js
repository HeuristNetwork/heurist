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
        service_config: {},
        
        //listeners
        onInitFinished:null,  //event listener when dialog is fully inited - use to perform initial search with specific parameters
        beforeClose:null,     //to show warning before close
        onClose:null       
    },
    
    _as_dialog:null, //reference to itself as dialog (see options.isdialog)
    
    _need_load_content:true,
    
    _current_cfg: null,
    _available_services:null,
    //controls
    selectRecordType:null, //selector for rectypes
    serviceList: null,
    
    
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
        
        this.element.addClass('ui-heurist-bg-light');
        
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
    
        this._on(this.selectRecordType, {change: this._onRectypeChange});
        
        //fill service list
        this.serviceList = this.element.find('#sel_service');
        this._reloadServiceList();
        
        this.serviceList.selectable( {selected: function( event, ui ) {
                    that.serviceList.find('li').removeClass('ui-state-active');
                    $(ui.selected).addClass('ui-state-active');
                    
                    //load configuration into right hand form
                    var  service_name = $(ui.selected).attr('value');
                    
                    that._fillConfigForm( service_name );
                    
                }});//.css({height:'100%'});
            

        var ele = this.element.find('#btnSave').button();
        this._on(ele, {click: this._applyConfig});
        
        ele = this.element.find('#btnRemove').button();
        this._on(ele, {click: this._removeConfig});            
            
        
        if(this.options.isdialog){
            this.popupDialog();
        }
        
        //show hide hints and helps according to current level
        window.hWin.HEURIST4.ui.applyCompetencyLevel(-1, this.element); 
        
        return true;
    },

    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
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
    _destroy: function() {
        // remove generated elements
        if(this.selectRecordType) this.selectRecordType.remove();
        if(this.serviceList) this.serviceList.remove();

    },
    
    //----------------------
    //
    // array of button defintions
    //
    _getActionButtons: function(){

        var that = this;        
        return [
                 {text:window.hWin.HR('Close'), 
                    id:'btnCancel',
                    css:{'float':'right','margin-left':'30px'}, 
                    click: function() { 
                        that.closeDialog();
                    }}
                    /*,
                 {text:window.hWin.HR('Next'),
                    id:'btnDoAction',
                    disabled:'disabled',
                    css:{'float':'right'},  
                    click: function() { 
                            that.doAction(); 
                    }}*/
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
            
            $dlg.parent().addClass('ui-dialog-heurist ui-heurist-admin');
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
    //
    //    
    _fillConfigForm: function( service_name ){

        var cfg0 = null;
        
        $.each(this._available_services, function(i, srv){
          if(srv.service==service_name){
              cfg0 = srv;
              return false;
          }
        });
        
        if(cfg0==null){
            this.element.find('#service_config').hide();
            this.element.find('#service_name').html('<span class="ui-icon ui-icon-arrowthick-1-w"/>Select service');
            return;
        }
        
        this.element.find('#service_config').show();
        
        if(this.options.service_config && this.options.service_config[service_name]){ 
            //already exists
            this._current_cfg = this.options.service_config[service_name];
            
        }else{
            //new configuration
            this._current_cfg = window.hWin.HEURIST4.util.cloneJSON(cfg0);
        }
        
        this.element.find('#service_name').html(service_name);
        this.element.find('#service_description').html(cfg0.description);
        this.element.find('#inpt_label').val(this._current_cfg.label);

        var tbl = this.element.find('#tbl_matches');
        tbl.empty();

        $.each(this._current_cfg.fields, function(field, code){
            $('<tr><td>'+field+'</td><td><select data-field="'+field+'"/></td></tr>').appendTo(tbl);
        });

        var rty_ID = this._current_cfg.rty_ID>0 ?$Db.getLocalID('rty',this._current_cfg.rty_ID) :'';
        
        this.selectRecordType.val( rty_ID ).hSelect("refresh");
        this._onRectypeChange();
    },
    
    //
    //
    //
    _onRectypeChange: function(){
     
        var rty_ID = this.selectRecordType.val();   
        
        var tbl = this.element.find('#tbl_matches');
        
        if(rty_ID>0){
            var that = this;
            $.each(tbl.find('select'), function(i, ele){
                
                var field = $(ele).attr('data-field');
                var dty_ID = that._current_cfg.fields[field];

                var dty_ID = dty_ID>0 ?$Db.getLocalID('dty', dty_ID) :'';
                
                window.hWin.HEURIST4.ui.createRectypeDetailSelect(ele, rty_ID, 
                    ['freetext','blocktext','enum','date','geo','float','year','integer','resource'], '...', 
                    {show_latlong:true, show_dt_name:true, selected_value:dty_ID} );
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
    //
    //
    _reloadServiceList: function(){
      
        this.serviceList.empty();
        
        for(var i=0;i< this._available_services.length; i++){
                    
            var cfg = this._available_services[i];   
            var s = cfg.label;

            if(cfg.service && this.options.service_config[cfg.service]){   
                s = s + '<span class="ui-icon ui-icon-arrowthick-1-e"/> ' 
                    + $Db.rty(this.options.service_config[cfg.service].rty_ID, 'rty_Name');
            }
            
            $('<li class="ui-widget-content" value="'+cfg.service+'">'+s+'</li>')  
                .css({margin: '2px', padding: '0.2em', cursor:'pointer'}) 
                .appendTo(this.serviceList);    
            
        }        
            
        
        this.serviceList.find('li').hover(function(event){
                var ele = $(event.target);
                if(!ele.is('li')) ele = ele.parent();
                ele.addClass('ui-state-hover');
                //ele.find('.ui-icon-pencil').show();
            }, function(event){
                var ele = $(event.target);
                if(!ele.is('li')) ele = ele.parent();
                ele.removeClass('ui-state-hover');
                //ele.find('.ui-icon-pencil').hide();
            });        
    },
    
    //
    //
    //
    _applyConfig: function(){
        
        var rty_ID = this.selectRecordType.val();

        if(rty_ID>0){
            var that = this;
            var tbl = this.element.find('#tbl_matches');
            var is_field_mapped = false;
            
            $.each(tbl.find('select'), function(i, ele){
                
                var field = $(ele).attr('data-field');
                var dty_ID = $(ele).val();
                that._current_cfg.fields[field] = dty_ID; 
                
                if(dty_ID>0) is_field_mapped = true;

            });
            
            if(is_field_mapped){
                this._current_cfg.rty_ID = rty_ID;
                this._current_cfg.label = this.element.find('#inpt_label').val();


                this.options.service_config = window.hWin.HEURIST4.util.isJSON(this.options.service_config);
                if(!this.options.service_config){
                    this.options.service_config = {};    
                } 

                this.options.service_config[this._current_cfg.service] = this._current_cfg;
                this._reloadServiceList();
                
            }else{
                window.hWin.HEURIST4.msg.showMsgFlash('Map at least one field');
            }
            
        }else{
            window.hWin.HEURIST4.msg.showMsgFlash('Select record type');
        }
    },
    
    //
    //
    //
    _removeConfig: function(){
        if(this._current_cfg.service && this.options.service_config[this._current_cfg.service])
        {
            this.options.service_config[this._current_cfg.service] = null;
            this._reloadServiceList();
        }
    }

});

