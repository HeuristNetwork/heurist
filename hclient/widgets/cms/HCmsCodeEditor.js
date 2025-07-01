/**
* HCmsCodeEditor
* 
* @project     Heurist academic knowledge management system
* @package CMS
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/
/* global CodeMirror */

import '../HBase/HBaseView.js';

$.widget( 'heurist.HCmsCodeEditor', $.heurist.HBaseView, {

    // Default options
    options: {
            width: 800,
            height: 600,
            default_palette_class: 'ui-heurist-publish',
            helpContent: null,
            keepInstance:true,
            allLanguages: null,
            currentLanguage: null
    },
    
    // Instance of CodeMirror
    codeEditor: null,
    ce_container: null,

    contentToBeEdited: null,
    newContent: null,

    
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
                        
                            let cur_lang;
                            if(that.options.allLanguages && that.options.allLanguages.length>1){
                                cur_lang = that.ce_container.attr('data-lang');
                            }else{
                                cur_lang = (that.options.currentLanguage??'def').toUpperCase();
                            }
                            that.newContent[cur_lang] = that.codeEditor.getValue();
                            
                            that._contextOnClose = that.newContent;
                            that.close();
                }}]; 
         
                
        //add language buttons
        let website_languages = this.options.allLanguages;
        if(website_languages && website_languages.length>1){
                
                for(let i=0;i<website_languages.length;i++){

                     let lang = website_languages[i].toUpperCase(); 
                    
                     //switcth language buttons   
                     codeEditorBtns.push({
                text: lang,
                'data-lang': lang,
                css:{'float':'left'}, 
                click: function(event) {  //switch language
                    
                    //keep previous
                    let newval = that.codeEditor.getValue();
                    let cur_lang = that.ce_container.attr('data-lang');
                    
                    if(that.newContent[cur_lang]!=newval){
                        that.newContent[cur_lang] = newval; 
                    }
                    
                    let new_lang = $(event.target).text();
                    that.assign( new_lang );
                                         
                    that.ce_container.attr('data-lang',new_lang);
            
                    }});
                    
                }//for
        }
            
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
        
        this.contentToBeEdited = contentToBeEdited??{};
        
        this.newContent = {};
        let website_languages = this.options.allLanguages;
        if(website_languages && website_languages.length>1){
                
                for(let i=0;i<website_languages.length;i++){

                     let lang = website_languages[i].toUpperCase(); 
                     //fill newContent object
                     if(Object.hasOwn(this.contentToBeEdited, 'content'+lang)){
                            this.newContent[lang] = this.contentToBeEdited['content'+lang];    
                     }else{
                            this.newContent[lang] = this.contentToBeEdited['content']; 
                     }
                }                    
                
        }else{
            let lang = (this.options.currentLanguage??'def').toUpperCase();
            this.newContent = {};
            this.newContent[lang] = (lang=='DEF')?this.contentToBeEdited['content']:this.contentToBeEdited['content'+lang];
        }
        
        this.assign( this.options.currentLanguage );
        
        this.ce_container.find('.CodeMirror ').css('height','100%');
    },

    /*
    *  Assign content for specified lang to code editor
    */    
    assign: function(lang){
        
        lang = (lang??'def').toUpperCase();
        
        if(!this.newContent[lang]) this.newContent[lang] = ' ';
        
        this.codeEditor.setValue(this.newContent[lang]);

        let that = this;
        //autoformat
        setTimeout(function(){
                    let totalLines = that.codeEditor.lineCount();  
                    that.codeEditor.autoFormatRange({line:0, ch:0}, {line:totalLines});                    
                    that.codeEditor.scrollTo(0,0);
                    that.codeEditor.setCursor(0,0); //clear selection
                    
                    that.codeEditor.focus()
                },500);
                
        let btnPanel = that.jqDialog.parent().find('.ui-dialog-buttonset');
        if(this.options.allLanguages && this.options.allLanguages.length>1){
            btnPanel.css('width', '100%');
            btnPanel.find('[data-lang]').show();
            btnPanel.find('[data-lang]').removeClass('ui-button-action');
            btnPanel.find(`[data-lang=${lang}]`).addClass('ui-button-action');
        }else{
            btnPanel.find('[data-lang]').hide();            
        }
                    
                
        
    },
    
    
});
