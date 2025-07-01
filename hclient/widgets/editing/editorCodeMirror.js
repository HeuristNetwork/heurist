/**
 * editorCodeMirror.js - Provides a wrapper class `EditorCodeMirror` to integrate the CodeMirror editor.
 *
 * @fileOverview Provides a wrapper class `EditorCodeMirror` to integrate the CodeMirror editor
 *               with a textarea element for enhanced text/code editing capabilities.
 *               It supports lazy loading of CodeMirror assets and various configuration options.
 *
 * @project     Heurist academic knowledge management system
 * @package  hclient\widgets\editing
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
 * @since       6.0
 */


/* global CodeMirror */
      
/**
 * @class EditorCodeMirror
 * @classdesc A wrapper class to replace a standard HTML textarea with a CodeMirror editor instance,
 * providing enhanced code and text editing features. It handles the dynamic loading of
 * CodeMirror library and modes, and manages the editor's lifecycle and interaction
 * with the original textarea.
 */
class EditorCodeMirror {
  
    /**
     * The original HTML textarea element (as a jQuery object) that this CodeMirror instance is attached to.
     * Its value is kept in sync with the editor content.
     * @public
     * @type {jQuery}
     */
    input;  
  
    /**
     * The parent div of the input textarea, typically with class 'input-div'.
     * Used for UI manipulations like finding switcher buttons.
     * @public
     * @type {jQuery|null}
     */
    inputdiv;

    /**
     * The div element that contains the CodeMirror editor instance.
     * This container is created dynamically and inserted after the original textarea.
     * @public
     * @type {jQuery|null}
     */
    editorContainer = null;

    /**
     * The CodeMirror editor instance. Null until `showEditor` successfully initializes it.
     * @public
     * @type {CodeMirror.Editor|null}
     */
    codeEditor = null;

    /**
     * Default configuration options for the CodeMirror instance.
     * These can be overridden or extended by options passed to the constructor.
     * @private
     * @type {Object}
     * @property {string} mode - Default syntax highlighting mode (e.g., "htmlmixed").
     * @property {number} tabSize - Size of a tab character.
     * @property {number} indentUnit - How many spaces a block should be indented.
     * @property {boolean} indentWithTabs - Whether, when indenting, the first N*tabSize spaces should be replaced by N tabs.
     * @property {boolean} lineNumbers - Whether to show line numbers.
     * @property {boolean} matchBrackets - Highlights matching brackets.
     * @property {boolean} smartIndent - Whether to use context-sensitive indentation.
     */
    #default_options = {
        mode           : "htmlmixed", // list of modes: https://codemirror.net/5/mode/index.html
        tabSize        : 2,
        indentUnit     : 2,
        indentWithTabs : false,
        lineNumbers    : false,
        matchBrackets  : true,
        smartIndent    : true
        /*extraKeys: {
            "Enter": function(e){
                insertAtCursor(null, "");
            }
        },*/
    };

    /**
     * The final effective options for the CodeMirror instance, after merging
     * constructor-passed options with `#default_options`.
     * @public
     * @type {Object}
     */
    options = {};

    /**
     * Constructs an EditorCodeMirror instance.
     * @param {jQuery} _input_element - The jQuery object representing the textarea element to be replaced by CodeMirror.
     * @param {Object} [options={}] - Configuration options to customize the CodeMirror editor.
     *                                These options will be merged with the `#default_options`.
     */
    constructor( _input_element, options = {} ) { 

        this.input = _input_element;

        //add hidden textarea element
        this.inputdiv = this.input.parent('.input-div');

        $.extend(this.options, this.#default_options, options);
    }
  
    /**
     * Dynamically loads the CodeMirror library and required mode scripts.
     * This method is called by `showEditor` if CodeMirror is not yet available globally.
     * It uses `$.getMultiScripts2` (expected to be a utility function provided by the environment,
     * likely from `hWin.HEURIST4` or similar) for loading scripts from a path derived from `hWin.HAPI4.baseURL`.
     * On successful load, it calls `this.showEditor()` again to proceed with editor initialization.
     * On failure, it displays an error message using `hWin.HEURIST4.msg.showMsg_ScriptFail()`.
     * @private
     * @returns {void}
     */
    #getCodeMirror(){

        let path = window.hWin.HAPI4.baseURL + 'external/codemirror-5.61.0/';
        let scripts = [ //'lib/codemirror.css', CSS is included in index.php
                        'lib/codemirror.js',
                        'lib/util/formatting.js', // For autoFormatRange
                        'mode/xml/xml.js',        // Dependency for htmlmixed
                        'mode/htmlmixed/htmlmixed.js' // Default mode
                        ];
        let that = this;
        // See getMultiScripts2 in utils.js
        $.getMultiScripts2(scripts, path)
        .then(function() {  //OK! widget script js has been loaded
            that.showEditor(); // Retry showing editor now that scripts are loaded
        }).catch(function() {
            window.hWin.HEURIST4.msg.showMsg_ScriptFail(); // Show generic script failure message.
        });
    }

    /**
     * Hides the CodeMirror editor container if it has been initialized and is visible.
     * This does not destroy the editor instance, allowing it to be shown again later.
     * @public
     * @returns {void}
     */
    hideEditor(){
        if(this.editorContainer) { // Check if editor container exists
            this.editorContainer.hide();
        }
    }

    /**
     * Shows and initializes the CodeMirror editor.
     * If CodeMirror global object is not found, it first calls `#getCodeMirror()` to attempt to load
     * the necessary scripts, and then returns, expecting `#getCodeMirror` to recall `showEditor` upon success.
     *
     * If the editor container (`this.editorContainer`) or the CodeMirror instance (`this.codeEditor`)
     * doesn't exist, it creates them:
     * - A new div (`this.editorContainer`) is created, inserted after the original textarea, and initially hidden.
     *   Its width is based on the original input's width (min 300px).
     * - The CodeMirror instance (`this.codeEditor`) is then created within this new container,
     *   configured with `this.options`.
     * - An 'on change' event listener is attached to the CodeMirror instance to synchronize its content
     *   back to the original textarea (`this.input`) and trigger a 'change' event on the textarea.
     * - The editor container is made resizable using jQuery UI's `resizable`, and CodeMirror's size
     *   is updated on resize.
     *
     * After ensuring the editor is initialized, this method:
     * - Sets the editor's value from the textarea if they differ.
     * - Schedules a timeout (500ms) to:
     *   - Auto-format the entire content if `autoFormatRange` is available.
     *   - Scroll the editor to the top.
     *   - Set the cursor at the beginning (line 0, char 0) to clear any selection.
     *   - Focus the CodeMirror editor.
     * - Hides the original textarea (`this.input`).
     * - Updates any associated UI switcher button (class `.editor_switcher` within `this.inputdiv`)
     *   to visually indicate that "codeeditor" is the active mode.
     * - Finally, makes the editor container visible (`display: 'inline-block'`).
     * @public
     * @returns {void}
     */
    showEditor(){

        if(typeof CodeMirror !== 'function'){ // Check if CodeMirror is loaded
            this.#getCodeMirror(); // If not, attempt to load it
            return; // Exit, #getCodeMirror will call showEditor again if successful
        }

        let that = this; // For use in closures

        // Initialize editor container if it doesn't exist
        if(this.editorContainer==null){
            let iwidth = $(this.input).width();
            if(iwidth<300) iwidth = 300; // Ensure a minimum width

            let editor_id = $(this.input).attr('id')+'_codemirror';
            this.editorContainer = $( "<div>")
            .attr("id", editor_id)
            .css({'overflow':'auto',resize:'both',width:iwidth}) // Make it resizable and scrollable
            .insertAfter(this.input) ; // Insert after the original textarea
            this.editorContainer.hide(); // Hide initially
        }

        // Initialize CodeMirror instance if it doesn't exist
        if(this.codeEditor==null){
            this.codeEditor = CodeMirror(this.editorContainer[0], this.options); // Create CodeMirror

            // Sync CodeMirror changes back to the original textarea
            this.codeEditor.on('change', function(instance){
                that.input.val(instance.getValue());
                that.input.trigger('change'); // Trigger change on original textarea for other listeners
            });

            // Make the editor resizable and update CodeMirror size accordingly
            this.editorContainer.resizable({
                resize: function() { // jQuery UI resizable event
                    that.codeEditor.setSize($(this).width(), $(this).height());
                }
            });              
        }

        // Actions to perform after editor is definitely initialized, with a slight delay
        setTimeout(function(){
            // Auto-format the content if the method exists (from formatting.js)
            if(typeof that.codeEditor.autoFormatRange === 'function'){
                let totalLines = that.codeEditor.lineCount();  
                that.codeEditor.autoFormatRange({line:0, ch:0}, {line:totalLines});                    
            }
            that.codeEditor.scrollTo(0,0); // Scroll to top
            that.codeEditor.setCursor(0,0); // Place cursor at start, clear selection

            that.codeEditor.focus(); // Focus the editor
        },500);

        this.input.hide(); // Hide the original textarea

        // Update editor switcher UI if present
        let btn_switcher = this.inputdiv?.find('.editor_switcher');
        if(btn_switcher.length>0){
            btn_switcher.find('span').css('text-decoration', ''); // Clear previous underline
            btn_switcher.find('span:contains("codeeditor")').css('text-decoration', 'underline'); // Underline "codeeditor"
        }

        this.editorContainer.css({display:'inline-block'}); // Show the editor
        // Ensure editor content is up-to-date with textarea value if they diverged
        if($(this.input).val()!=this.codeEditor.getValue()){
            this.codeEditor.setValue($(this.input).val());    
        }
    }  
}