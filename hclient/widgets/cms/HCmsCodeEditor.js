/**
* HCmsCodeEditor
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/

import '../HBase/HBaseView.js';

$.widget( 'heurist.HCmsCodeEditor', $.heurist.HBaseView, {

    // Default options
    options: {
            width: 800,
            height: 600,
            default_palette_class: 'ui-heurist-publish',
            helpContent: null,
            keepInstance:true
    },
    
    // Instance of CodeMirror
    codeEditor: null,
    ce_container: null,
    contentToBeEdited: null,

    
    /*
    *
    */
    _getActionButtons: function() {
        
        let that = this;
        
        let codeEditorBtns = [
                    {text:window.hWin.HR('Cancel'), 
                        class:'btnCancel',
                        css:{'float':'right','margin-left':'30px','margin-right':'20px'}, 
                        click: function() { 
                            that.close();
                    }},
                    {text:window.hWin.HR('Apply'), 
                        class:'ui-button-action btnDoAction',
                        //disabled:'disabled',
                        css:{'float':'right'}, 
                        click: function() { 
                            let newval = that.codeEditor.getValue();

                            //if(contents==null){ //no languages defined
                                if(that.contentToBeEdited != newval){
//that._enableSave();                
// element.html(newval); update element in web content
                                    that.contentToBeEdited = newval;
                                }
                            /*    
                            }else{ //multilang
                                let cur_lang = ce_container.attr('data-lang');
                                contents[cur_lang] = newval;
                                let langs = Object.keys(contents);
                                for(let i=0; i<langs.length; i++){
                                    let lang_key = 'content'+langs[i];
                                    if(default_language.toUpperCase()==langs[i]){
                                        lang_key = 'content';
                                    }
                                    if(l_cfg[lang_key] != contents[langs[i]]){
                                        
                                        l_cfg[lang_key] = contents[langs[i]];
// _enableSave();
                                        if(current_language.toUpperCase()==langs[i]){
// element.html(l_cfg[lang_key]);    
                                        }
                                    }
                                }
                            }*/
                            
                            that._contextOnClose = newval;
                            that.close();
                }}]; 
                
                //TBD add language buttons
        
        return codeEditorBtns;
    },
    

    /**
     * Cleanup function. Removes generated elements and event listeners.
     */
    _destroy: function() {
        this._super();
    },
    
    /**
     * Initializes controls and triggers rendering.
     */    
    _initControls:function(){
        
        this.ce_container = $('<div id="codemirror-body" style="position:absolute;left:0px;right:2px;top:0px;bottom:0px;border:lightblue 1px dotted;"></div>')
        .appendTo(this.element);        

        if(this.codeEditor==null){

            //document.getElementById('codemirror-container')
            this.codeEditor = CodeMirror(this.ce_container[0], {
                mode           : "htmlmixed",
                tabSize        : 2,
                indentUnit     : 2,
                indentWithTabs : false,
                lineNumbers    : false,
                matchBrackets  : true,
                smartIndent    : true,
                /*extraKeys: {
                "Enter": function(e){
                insertAtCursor(null, "");
                }
                },*/
                onFocus:function(){},
                onBlur:function(){}
            });
        }           
        
        this._super();
    },
    
    /**
     * Displays the record viewer.
     * @param {number} recID - The record ID to display (optional).
     */
    show: function(contentToBeEdited) {
       
        this._super();
        
        this.contentToBeEdited = contentToBeEdited;
        
        if(window.hWin.HEURIST4.util.isempty(contentToBeEdited)) contentToBeEdited = ' ';
        this.codeEditor.setValue(contentToBeEdited);

        let that = this;
        //autoformat
        setTimeout(function(){
                    let totalLines = that.codeEditor.lineCount();  
                    that.codeEditor.autoFormatRange({line:0, ch:0}, {line:totalLines});                    
                    that.codeEditor.scrollTo(0,0);
                    that.codeEditor.setCursor(0,0); //clear selection
                    
                    that.codeEditor.focus()
                },500);
                
        this.ce_container.find('.CodeMirror ').css('height','100%');
    },
    
    
});
