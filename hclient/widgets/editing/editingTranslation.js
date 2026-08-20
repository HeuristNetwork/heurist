/**
* @file editingTranslation.js
* @brief Translation helpers for editing_input.
* @fileOverview Provides translation dialog integration and conversion between translated parameter values and editing UI fields.
* @project     Heurist academic knowledge management system
* @package     Widgets.Editing
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay <blmckay13@gmail.com>
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

/**
 * Opens a popup dialog for defining translations for field values.
 * It dynamically loads the `editTranslations` widget if not already available.
 *
 * @memberof Widgets.Editing
 * @param {object|Array<string>} _input_or_values - If an object, it's assumed to be an `editing_input` instance
 *                                                  from which current values and field type are derived.
 *                                                  If an array, it's treated as an array of initial translation strings
 *                                                  (e.g., ["eng:Hello", "fra:Bonjour"]).
 * @param {boolean} is_text_area - Indicates if the input field is a textarea (true) or a single-line text input (false).
 *                                 This parameter is used only if `_input_or_values` is an array.
 * @param {function(Array<string>): void} callback - A function to be called when translations are applied.
 *                                                  It receives an array of the updated translation strings as its argument.
 */
function translationSupport(_input_or_values, is_text_area, callback){

    if(!window.hWin.HEURIST4.util.isFunction($('body')['editTranslations'])){
        $.getScript( window.hWin.HAPI4.baseURL + 'hclient/widgets/editing/editTranslations.js', 
            function() {  //+'?t='+(new Date().getTime()) // Timestamp for cache busting commented out.
                if(window.hWin.HEURIST4.util.isFunction($('body')['editTranslations'])){
                    translationSupport( _input_or_values, is_text_area, callback );
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: 'Widget editTranslations not loaded. Verify your configuration',
                        error_title: 'Translation widget loading failed',
                        status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                    });
                }
        });
    }else{
        //open popup
        let that = _input_or_values;    
        let _dlg, values, fieldtype;
        
        if(Array.isArray(that)){
            values = that;
            _dlg = $('<div/>').hide().appendTo($('body'));
            fieldtype = is_text_area?'blocktext':'freetext';
        }else{ //editing_input
            _dlg = $('<div/>').hide().appendTo( that.element );                               
            values = that.getValues();
            fieldtype = that.detailtype
        }

        _dlg.editTranslations({
            values: values,
            fieldtype: fieldtype,
            onclose:function(res){
                if(res){
                    if(window.hWin.HEURIST4.util.isFunction(callback)){
                        callback.call(this, res);
                    }else{ // 'that' is an editing_input instance
                        that.setValue(res);    
                        that.isChanged(true);
                        that.onChange();
                    }
                }
                _dlg.remove();
        }});

    }


}


/**
 * Extracts translation values from UI input/textarea elements within a container
 * and populates a parameters object.
 * Input elements are expected to have a `data-lang` attribute.
 * 'def' indicates the default language (no language suffix in the key).
 * Other values for `data-lang` are used as suffixes (e.g., keyname:eng).
 *
 * @memberof Widgets.Editing
 * @param {Object<string, string>} params - The object to be populated with translation strings.
 *                                          Existing relevant translation keys will be cleared first.
 * @param {jQuery} $container - The jQuery object representing the container of the input/textarea elements.
 * @param {string} keyname - The base key name to use in the `params` object for translations.
 * @param {string} name - The `name` attribute of the input/textarea elements to scan.
 * @param {boolean} is_text_area - True if the elements are textarea, false if input.
 */
function translationFromUI(params, $container, keyname, name, is_text_area){
    
    //clear previous values, except default
    $(Object.keys(params)).each(function(i, key){

        let key2 = key;        
        if(key.length>5 && key.indexOf(':')==key.length-4){ // Check for pattern like ":xyz"
            key2 = key.substring(0, key.length-4);
            if(key2 == keyname){
                delete params[key];
            }
        }
    });
    
    //find all elements with given name
    let ele_type = is_text_area?'textarea':'input';
    
    $container.find(ele_type+'[name="'+name+'"]').each(function(i,item){
        item = $(item);
        let lang = item.attr('data-lang');
        if(lang=='def') lang = ''
        else lang = ':'+lang;
        
        let value = item.val().trim();
        if(!window.hWin.HEURIST4.util.isempty(value) || lang===''){ // Store if value is not empty OR it's the default language input
            params[keyname+lang] = value;    
        }
    });
}

/**
 * Populates UI input/textarea elements from a parameters object containing translations
 * and initializes a "translation" button for editing these translations.
 * It creates input elements for each language found in `params` (e.g., keyname:eng, keyname:fra)
 * and sets up a button to launch the `translationSupport` dialog.
 *
 * @memberof Widgets.Editing
 * @param {Object<string, string>} params - An object containing translation strings, where keys are typically `keyname`
 *                                          for the default language and `keyname:<code>` for others.
 * @param {jQuery} $container - The jQuery object representing the container where input elements will be managed/created.
 * @param {string} keyname - The base key name used in `params` for the default translation.
 * @param {string} name - The `name` attribute to assign to the created input/textarea elements.
 * @param {boolean} is_text_area - True to create textarea elements, false for input.
 */
function translationToUI(params, $container, keyname, name, is_text_area){
    
    let def_ele = null;
    
    let ele_type = is_text_area?'textarea':'input';
    
    //find element assign data-lang for default, remove others
    //1. Removes all except default (first one)
    $container.find(ele_type+'[name="'+name+'"]').each(function(i,item){
        let lang  = $(item).attr('data-lang');
        if(lang=='def' || !lang){
            def_ele = $(item);
        }else{
            $(item).remove(); //remove non-default
        }
    });
    
    if(!def_ele) return; // Should not happen if an input for the field exists.
    
    if(!params) params = {}; 
    if(!params[keyname]){ // Ensure default key exists even if empty
      params[keyname] = def_ele.val();  
    } 
    
    let sTitle = ''; // To accumulate titles for the default element's tooltip
    
    //init input element for default value and button
    def_ele.attr('data-lang','def').val(params[keyname]);
    
    //2. Add translation button    
    if($container.find('span[name="'+name+'"]').length==0){ // Add button only if it doesn't exist

        //translation button    
        let btn_add = $( "<span>")
            .attr('data-lang','def')
            .attr('name',name) // Using name attribute for a span, consider data-*
            .addClass('smallbutton editint-inout-repeat-button ui-icon ui-icon-translate')
            .insertAfter( def_ele )
        .attr('tabindex', '-1')
        .attr('title', 'Define translation' )
        .css({display:'inline-block', 
        'font-size': '1em', cursor:'pointer', 
            'min-width':'22px',
            outline: 'none','outline-style':'none', 'box-shadow':'none'
        });
        
        if(is_text_area){
            btn_add.css({'vertical-align':'top'});    
        }
        
        btn_add.on({click: function(e){//--------------------------
            
            let values = [];
            
            //gather the list of values from input elements
            $container.find(ele_type+'[name="'+name+'"]').each(function(i,item){
                let lang  = $(item).attr('data-lang');
                if(lang=='def' || !lang){ // Default language
                    values.push($(item).val())
                }else{ // Other languages
                    values.push(lang+':'+$(item).val());
                }
            });
            
            //open dialog
            translationSupport( values, is_text_area, function(newvalues){
                
                let res2 = {};
                for(let i=0; i<newvalues.length; i++){
                    let keyname2=keyname, value = newvalues[i];
                    
                    if(!window.hWin.HEURIST4.util.isempty(value) && value.slice(3,4)==':'){ // lang:value format
                        keyname2 = keyname2+':'+value.slice(0,3); // e.g. keyname:eng
                        value = value.slice(4).trim();
                    }else{ // Default language value
                        value = value.trim();
                    }
                    if(!window.hWin.HEURIST4.util.isempty(value) || keyname2 === keyname){ // Store if value not empty OR it's the default key
                        res2[keyname2] = value;
                    }
                }
                if(typeof res2[keyname] === 'undefined'){ // Ensure default key exists if all translations were removed
                    res2[keyname] = '';  
                } 

                translationToUI(res2, $container, keyname, name, is_text_area); // Re-render UI with new values
            });
            
        }});
    }//end add translation button
    
    
    //3. add new hidden lang elements for other languages
    $(Object.keys(params)).each(function(i, key){
        if(key!=keyname && keyname==key.substring(0,key.length-4)){ // e.g. key is "mykey:eng" and keyname is "mykey"
            let lang = key.substring(key.length-3); // "eng"
            
            let ele = $('<'+ele_type+'>')
                .attr('name',name).attr('data-lang',lang)
                .val(params[key]).insertAfter(def_ele); // Insert after default element
                
            if(is_text_area){ // Hidden if textarea, visible if input (though typically these are hidden)
                ele.css('display','none');
            }
                
            sTitle += (lang+':'+params[key]+'\n');
        }
    });    
    
    def_ele.attr('title',sTitle); // Set tooltip on default element showing all translations

}

