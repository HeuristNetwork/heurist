/**
* HContainerPopup - container widget for poup as jquery dialog, bootstrap modal or bootstrap offcanvas
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     7.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/*
* HBasePopup
*
*/
import './HBaseWidget.js';

$.widget( 'heurist.HContainerPopup', $.heurist.HBaseWidget, {

    // default options
    options: {
/* inherits from HBaseWidget        
        resourcePath: null, //relative path+filename to resources: html, css and localization
        htmlContent: null, // custom content
        uiLibrary: null,   // 'bootstrap' or 'jqueryui'
        
        //event listeners
        onInitFinished: null
*/        
        viewMode: 'popup', // offcanvas-*, modal-*, popup (jquery dialog)   
        showMargin: true,
        
        //DIALOG section       
        default_palette_class: 'ui-heurist-admin',
        supress_dialog_title: false, //hide dialog title bar (applicable if viewMode='popup')
        
        height: 400,
        width:  760,
        position: null,
        modal:  true,
        title:  '',
        innerTitle: false, //show title as top panel 
        
        //event listeners
        beforeClose:null, // to show warning before close
        onClose:null,     // after close event listener
        
        keepInstance: false
    },
    
    bsModal: null,
    bsOffcanvas:null,
    jqDialog: null,

    _context_on_close:false, //variable to be passed to options.onClose event listener    
    
    //
    // the widget's constructor
    //
    _create: function() {
        
console.log('_create popup');
        this._super();
        
        if(this.options.viewMode.indexOf('modal')==0 || this.options.viewMode.indexOf('offcanvas')==0){
            this.options.resourcePath = 'hclient/widgets/HRecordList/HContainerPopup';
            this._needLoadContent = true;
        }
    },
    
    
    /*
    * 
    */
    _init: function(){
console.log('_init popup');
        this._super();
        
    },
    
    /*
    * 
    */
    _destroy: function(){

console.log('_destroy popup');
        
        if(this.bsModal){
            this.bsModal.dispose();   
        }
        if(this.bsOffcanvas){
            this.bsOffcanvas.dispose();   
        }
        if(this.jqDialog){
            this.jqDialog.dialog('close');   
        }
        
        this._super();
    },
    
    /*
    * Use it a) to add event listeners for subelements of this widget
    *        b) perform some default actions (intial search for example) 
    */
    _initControls:function(){
        
        if(this.options.viewMode.indexOf('modal')==0){
            
                let modal = this._$('[data-heurist-role="container-modal"]')[0];
                if(!modal.classList.contains(this.options.viewMode)){
                    modal.classList.add(this.options.viewMode);
                }
                
                this.bsModal = bootstrap.Modal.getOrCreateInstance(modal);
                
                if(this.options.showMargin){
                    let view_div = modal.querySelector('.modal-body');
                    
                    if(!view_div.classList.contains('p-1')){
                        view_div.classList.add('p-1');
                        view_div.style.overflowY = 'hidden';
                        
                        let content_div = modal.querySelector('.modal-content');
                        content_div.style.height = '100%';
                    }
                }
        
        }else if(this.options.viewMode.indexOf('offcanvas')==0){

                let offcanvas = this._$('[data-heurist-role="container-offcanvas"]')[0];
                if(!offcanvas.classList.contains(this.options.viewMode)){
                    offcanvas.classList.add(this.options.viewMode)
                }
                
                let handles = 'w'; //default is on the right
                if(this.options.viewMode.indexOf('-start')>0){
                    handles = 'e';
                }else if(this.options.viewMode.indexOf('-bottom')>0){
                    handles = 'n';
                }else if(this.options.viewMode.indexOf('-end')>0){
                    handles = 's';
                }
                
                $(offcanvas)
                  .resizable({
                    minWidth: 400,
                    handles: handles,
                  });

                this.bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvas); //'#recordList-offcanvas');

                if(this.options.showMargin){
                    let view_div = offcanvas.querySelector('.offcanvas-body');
                    
                    if(!view_div.classList.contains('p-1')){
                        view_div.classList.add('p-1');
                        view_div.style.overflowY = 'hidden';
                    }
                }
                
            
        }else{
            // by default jquery dialog
            this._initDialog();    
            
            if(this.options.showMargin){
                let view_div = this.element;
                view_div.css({'padding':0, 'overflow':'hidden'});
            }
        }
    
        this._super();
    },
    
    /*
    *
    */
    getContainer: function(){
        
        if(this.bsModal){
            return this.bsModal._element.querySelector('.modal-body');
        }else if(this.bsOffcanvas){
            return this.bsOffcanvas._element.querySelector('.offcanvas-body');
        }else if(this.jqDialog) {
            return this.element[0];
        }
    },
    
    /*
    *
    */
    show:function(){
        
        if(this.bsModal){
            this.bsModal.show();
        }else if(this.bsOffcanvas){
            this.bsOffcanvas.show();
        }else if(this.jqDialog) {
            this._popupDialog();
        }
    },

    /*
    *
    */
    close:function(isForce){
        
        if(this.bsModal){
            this.bsModal.hide();
        }else if(this.bsOffcanvas){
            this.bsOffcanvas.hide();
        }else if(this.jqDialog) {
            this._closeDialog();
        }
    },
    
    
    // ---------------- jQuery dialog
    
    /*
    * standard set of button - close and cancel
    */    
    _getActionButtons: function(){
        let that = this;        
        return [
                 {text:window.hWin.HR('Close'), 
                    class:'btnCancel',
                    css:{'float':'right','margin-left':'30px','margin-right':'20px'}, 
                    click: function() { 
                        that._closeDialog();
                    }},
                 ];
/*        return [
                 {text:window.hWin.HR('Cancel'), 
                    class:'btnCancel',
                    css:{'float':'right','margin-left':'30px','margin-right':'20px'}, 
                    click: function() { 
                        that.closeDialog();
                    }},
                 {text:window.hWin.HR('Go'),
                    class:'ui-button-action btnDoAction',
                    disabled:'disabled',
                    css:{'float':'right'},  
                    click:function() { 
                            that.doAction(); 
                    }}  
                 ];*/
    },

    /*
    *
    */
    _initDialog: function(){
        
            let options = this.options,
                btn_array = this._getActionButtons();
            const that = this;
            if(!options.beforeClose){
                    options.beforeClose = function(){
                        //show warning on close
                        return true;
                    };
            }
            
            if(options.position==null) options.position = { my: "center", at: "center", of: window };
            
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
                title: window.hWin.HEURIST4.util.isempty(options['title'])?'':window.hWin.HR(options['title']), //title will be set in  initControls as soon as entity config is loaded
                position: options['position'],
                beforeClose: options.beforeClose,
                //resizeStop: function( event, ui ) {//fix bug
                //    that.element.css({overflow: 'none !important','width':that.element.parent().width()-24 });
                //},
                close:function(){
                    if(window.hWin.HEURIST4.util.isFunction(that.options.onClose)){
                      //that.options.onClose(that._currentEditRecordset);  
                      that.options.onClose( that._context_on_close );
                    } 
                    if(!that.options.keepInstance){
                        that.jqDialog.remove();
                    }
                },
                buttons: btn_array
            }); 
            this.jqDialog = $dlg; 
        
    },

    _popupDialog: function(){

            let $dlg = this.jqDialog.dialog("open");
            
            if(this.jqDialog.attr('data-palette')){
                $dlg.parent().removeClass(this.jqDialog.attr('data-palette'));
            }
            if(this.options.default_palette_class){
                this.jqDialog.attr('data-palette', this.options.default_palette_class);
                $dlg.parent().addClass(this.options.default_palette_class);
                this.element.removeClass('ui-heurist-bg-light');
            }else{
                this.jqDialog.attr('data-palette', null);
                this.element.addClass('ui-heurist-bg-light');
            }

            /* TBD
            if(this.options.supress_dialog_title) $dlg.parent().find('.ui-dialog-titlebar').hide();

            if(this.options.helpContent==null){
                this.options.helpContent = this.widgetName;
            }
            if(this.options.helpContent){
                let helpURL = window.hWin.HRes( this.options.helpContent )+' #content';
                window.hWin.HEURIST4.ui.initDialogHintButtons(this.jqDialog, null, helpURL, false);    
            }
            */
        
    },
    
    //
    // close dialog
    //
    _closeDialog: function(isForce){
        if(this.jqDialog){
            if(isForce===true){
                this.jqDialog.dialog('option','beforeClose',null);
            }
            
            this.jqDialog.dialog("close");
        }else{
            /* TBD
            let canClose = true;
            if(window.hWin.HEURIST4.util.isFunction(this.options.beforeClose)){
                canClose = this.options.beforeClose();
            }
            if(canClose){
                if(window.hWin.HEURIST4.util.isFunction(this.options.onClose)){
                    this.options.onClose( this._context_on_close );
                }
            }
            this.element.hide();
            */
        }
    },
    
    
});
