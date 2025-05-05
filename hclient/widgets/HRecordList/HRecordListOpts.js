/**
* HRecordListOpts - form to modify HRecordList options
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/

import '../HBase/HBaseView.js';

$.widget( 'heurist.HRecordListOpts', $.heurist.HBaseView, {
    
    // default options
    options: {
        height: 600,
        title: 'Options editor',
        default_palette_class: 'ui-heurist-publish',
        resourcePath: 'hclient/widgets/HRecordList/HRecordListOpts', //relative path+filename to resources: html, css and localization
        editOptions: {},
        onChange: null
    },

    /*
    * Use it a) to add event listeners for subelements of this widget
    *        b) perform some default actions (intial search for example) 
    */
    _initControls:function(){
        
        this._super();
        
        this.show();  
        
        //Init some controls
        this._$('select').each((i,selObj)=>{
            selObj = window.hWin.HEURIST4.ui.initHSelect(selObj);
        });
        
        this._$('#tabs').tabs();

    },

    /* 
    * Cleanup. Removes generated elements and off event listeners
    */
    _destroy: function() {
        // remove generated elements
        this._super();
    },
    
    /**
     * Displays options editor
     * @param {object} editOptions - options object to be edited 
     */
    show: function(editOptions) {
        this._super();
        
        if(editOptions){
            this.options.editOptions = editOptions;  
        } 
        this._fillControls();
    },
    
    /*
    *
    */
    _getActionButtons: function() {
        let btns = this._super();
        let that = this;
        btns.push(
        {text:window.hWin.HR('Apply'),
                    class:'ui-button-action btnDoAction',
                    //disabled:'disabled',
                    css:{'float':'right'},  
                    click: () => that._applyChanges()
        });
        return btns;
    }, 
    
    /*
    * from editOptions to UI
    */
    _fillControls: function(){

        let that = this;
        let allFields = this._$('input,select,textarea');
        if(!that.options.editOptions) that.options.editOptions = {};
        //from prefs to ui
        allFields.each(function(){
            let opt = that.options.editOptions[this.name];
            if(opt){
                if(this.type=="checkbox"){
                    this.checked = (opt=='1' || opt=='true')
                }else{
                    $(this).val(opt);
                }
            };
        });

        
        this._$('select[name^="template"]').each((i,sel)=>{
        window.hWin.HEURIST4.ui.createTemplateSelector(  
                        $(sel), [{key:'',title:'standard template'}], 
                           that.options.editOptions[sel.name],  //$select3.attr('data-template')
                           {extraOptions: {menu_parent: that.jqDialog}});  // or bsModal bsOffcanvas
                        }); 

        if (this.$H.isFunction(this.options.onChange)) { 
            //event listeners for all input,select and textarea
            this._on(allFields, {change:this._triggerOnChange});
        }
    },
    
    _triggerOnChange(){
        this._getEditOptions();
        this.options.onChange.call(this, this.options.editOptions);   
    },

    /*
    *
    */    
    _getEditOptions: function(){
        
        let that = this;
        let allFields = this._$('input,select,textarea');
        
        that.options.editOptions = {};

        allFields.each(function(){
            if(this.type=="checkbox"){
                that.options.editOptions[this.name] = this.checked;
            }else if(!($(this).val()=='' || ($(this).is('select') && $(this)[0].selectedIndex==0))) {
                that.options.editOptions[this.name] = $(this).val();
            }
        });
    },

    /*
    * from UI to editOptions
    */    
    _applyChanges: function(){

        this._getEditOptions();    
        this._contextOnClose = this.options.editOptions;
        this.close(true);
    }
    
});
