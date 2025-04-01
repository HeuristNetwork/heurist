/**
* RecordList - listing of record from given HRecordSet
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/

/*
* HBaseWidget->HRecordList->HRecordTable, HRecordCards, HRecordMap, HRecordNetwork
*
* HBaseWidget - loads resources: html, css, localization
* HRecordList - setDomain, setRecordSet, loadRecordDetails, doSearch(?)
* 
* BaseList:
* setRecordSet
* doSearch TBD for initial search or on search domain event
* selectRecords TBD
* clearContent 
* loadRecordDetails - loads records details
* renderPage - abstract
* renderMessage - notification message (init or for empty result)
* 
* RecordList:
* pagination  _renderPagination/_clearPagination
* page renderer implementation 
* selection
* open view/edit record
* 
* Plan:
* BaseList, RecordList->RecordTable, RecordCards, 
* RecordReport 
* 
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
    },

    /*
    * Use it a) to add event listeners for subelements of this widget
    *        b) perform some default actions (intial search for example) 
    */
    _initControls:function(){
        
        this._super();
        this.show();  
        
        //Init some controls
        this._$('select').hSelect();

        let that = this;
        this._$('select[name^="template"]').each((i,sel)=>{
        window.hWin.HEURIST4.ui.createTemplateSelector(  
                        $(sel), [{key:'',title:'standard template'}], 
                           '',  //$select3.attr('data-template')
                           {extraOptions: {menu_parent: that.jqDialog}}); 
                        }); // or bsModal bsOffcanvas
        
    },

    /* 
    * Cleanup. Removes generated elements and off event listeners
    */
    _destroy: function() {
        // remove generated elements
        this.clearContent();
        
        this._super();
    },
    
    /*
    * Removes all record elements
    *  overwrites parent's method
    */
    clearContent: function(){
        
        if(!this._initCompleted) return;
        
        //_off all clicks for actions per record cards
        //this._off( this.div_content.find(`div[${this.record_id_attr}]`), 'click');
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
        let allFields = this._$('input,select');
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
    },

    /*
    * from UI to editOptions
    */    
    _applyChanges: function(){

        let that = this;
        let allFields = this._$('input,select');
        
        that.options.editOptions = {};

        allFields.each(function(){
            if(this.type=="checkbox"){
                if(!this.checked){
                    that.options.editOptions[this.name] = false; //this.checked?1:0;
                }
            }else if(!($(this).val()=='' || ($(this).is('select') && $(this)[0].selectedIndex==0))) {
                that.options.editOptions[this.name] = $(this).val();
            }
        });
        
        this._contextOnClose = this.options.editOptions;
        this.close(true);
    }
    
});
