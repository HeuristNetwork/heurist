/**
* @file editingBrowse.js
* @brief Record and term browsing helpers for editing_input.
* @fileOverview Provides record/term browsing, searchable selectors, and generic record/entity selection dialogs used by editing_input.
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
 * Enhances a jQuery hSelect dropdown menu with live search/filter capabilities and other UI improvements.
 * This function is typically called from within an `editing_input` widget context.
 *
 * @memberof Widgets.Editing
 * @param {object} that - The context object, usually an instance of an `editing_input` jQuery widget.
 *                        It's used to manage event bindings (`_on`) and access field configurations (`f`).
 * @param {jQuery} $select - The jQuery object representing the `<select>` element that has been initialized with `hSelect`.
 * @param {boolean} [has_filter=true] - If true, the first item in the dropdown is treated as a non-selectable search input area.
 * @param {boolean} [is_terms=false] - If true, applies specific logic for term selection, including hierarchical display
 *                                   and showing child/parent terms related to search results.
 */
function openSearchMenu(that, $select, has_filter=true, is_terms=false){

    let $menu = $select.hSelect('menuWidget');
    let $inpt = $menu.find('input.input_menu_filter'); //filter input

    if(!$inpt.attr('data-inited')){

        $inpt.attr('data-inited',1);
        
        //reset filter                                
        that._on($menu.find('span.smallbutton'), {click:
        function(event){
            window.hWin.HEURIST4.util.stopEvent(event); 
            let $mnu = $select.hSelect('menuWidget');
            $mnu.find('input.input_menu_filter').val('');
            $mnu.find('li').css('display','list-item');
            $mnu.find('div.not-found').hide();
        }});

        that._on($menu.find('span.show-select-dialog'), {click:
        function(event){
            let $mnu = $select.hSelect('menuWidget');
            if($mnu.find('.ui-menu-item-wrapper:first').css('cursor')!='progress'){
                let foo = $select.hSelect('option','change');
                foo.call(this, null, 'select'); //call __onSelectMenu
            }
        }});
        
        let _timeout = 0;
        
        //set filter
        that._on($menu, {
            click:function(event){
                window.hWin.HEURIST4.util.stopEvent(event);
                return false;                       
            },
            keyup:function(event){
                let val = $(event.target).val().toLowerCase();
                window.hWin.HEURIST4.util.stopEvent(event);                       
                let $mnu = $select.hSelect('menuWidget');
                if(val.length<2){
                    $mnu.find('li').css('display','list-item');
                    $mnu.find('div.not-found').hide();
                    that.selObj.hSelect('openAllGroupings');
                }else{ //start search from 3 characters
                    if(_timeout==0){
                        $mnu.find('.ui-menu-item-wrapper').css('cursor','progress');
                    }

                    let key = that.f('rst_RecTypeID')+'-'+that.f('rst_DetailTypeID');
                    let showing_option = [];
                    let harchy = [];
                    
                    if(is_terms && val.indexOf('.')>0){
                        harchy = val.split('.');
                        val = harchy.pop();
                    }
                    
                    $.each($mnu.find('.ui-menu-item-wrapper'), function(i,item){

                        let title = $(item).text().toLowerCase();
                        if($select.attr('rectype-select') == 1 && Object.hasOwn(window.hWin.HEURIST4.browseRecordCache,key)){
                            title = window.hWin.HEURIST4.browseRecordCache[key][i]['rec_Title'].toLowerCase();
                            title = title.replace(/[\r\n]+/g, ' ');
                        }

                        if(title.indexOf(val)>=0){
                            
                            let is_ok = true;

                            //for terms - if hierarchy check parents
                            if(harchy.length>0){

                                let item2 = $(item);
                                let depth = parseInt(item2.attr('data-depth'));
                                
                                is_ok = false;
                                //find previous element with depth-1 - parent term
                                if(depth>0){                       
                                    let idx = harchy.length-1;
                                    $.each(item2.parent().prevAll(),function(i,li_item){
                                        let opt_item = $(li_item).find('.ui-menu-item-wrapper');
                                        let depth2 = parseInt(opt_item.attr('data-depth'));
                                        if(depth2<depth){
                                            let title = opt_item.text().toLowerCase();
                                            if(title.indexOf(harchy[idx])>=0){
                                                idx--;
                                                if(depth2==0 || idx<0){
                                                    is_ok = (idx<0);
                                                    return false; //break
                                                } 
                                            }else{
                                                return false; //not found
                                            }
                                        }
                                    });                        
                                }
                            }
                            
                            if(is_ok){
                                $(item).parent().css('display','list-item');   //li
                                showing_option.push( item ); //found
                            }
                            
                        }else{
                            $(item).parent().css('display','none');
                        }
                    });
                    
                    //show children of found items - for terms
                    if(is_terms){
                        showing_option.forEach(function(item){
                            
                            item = $(item);
                            let depth = parseInt(item.attr('data-depth'));
                            
                            //find previous element with depth-1 - parent term
                            if(depth>0){                       
                            $.each(item.parent().prevAll(),function(i,li_item){
                                let opt_item = $(li_item).find('.ui-menu-item-wrapper');
                                let depth2 = parseInt(opt_item.attr('data-depth'));
                                if(depth2<depth){
                                    $(li_item).css('display','list-item');
                                    if(depth2==0) return false; //break
                                }
                            });                        
                            }    
                            //find next elements with depth+1
                            if(depth>=0){                       
                            $.each(item.parent().nextAll(),function(i,li_item){
                                let opt_item = $(li_item).find('.ui-menu-item-wrapper');
                                let depth2 = parseInt(opt_item.attr('data-depth'));
                                if(depth2<=depth){
                                    return false; //break - the same level
                                }else if(depth2==depth+1){
                                    //children
                                    $(li_item).css('display','list-item');
                                }
                            });
                            }
                        });
                    }
                    
                    $mnu.find('div.not-found').css('display',
                            (showing_option.length==0)?'block':'none');
                    _timeout = setTimeout(function(){$mnu.find('.ui-menu-item-wrapper').css('cursor','default');_timeout=0;},500);
                }                                    
            }
        });

		if(has_filter){			
 
            let start_pos = 0;

            let $search_li = $menu.find('li.ui-menu-item:first');
            $search_li.removeClass('ui-menu-item').addClass('ui-menu-search');
            $search_li.find('[role="option"]').attr('role', '');

            that._on($search_li, {
                keydown: function(event){ // allow hotkeys for input filter

                    /**
                     * Allows:
                     *  Space bar to add a space (default was select and close)
                     *  Press Enter to auto select the only displayed option
                     *  Press Tab to change focus to the dropdown's first item
                     *  Control/Meta + 'A' to select all input, and
                     *  Highlighting input via arrow keys, control/meta, and shift keys
                     */

                    let $input = $menu.find('.input_menu_filter');
                    let cur_val = $input.val();

                    let code = event.keyCode || event.which;

                    let ctrl_pressed = event.ctrlKey || event.metaKey;
                    let shift_pressed = event.shiftKey;

                    let is_enter = event.key == "Enter" || code == 13;
                    let is_tab = event.key == "Tab" || code == 9;

                    let left_arrow = event.key == "ArrowLeft" || code == 37;
                    let right_arrow = event.key == "ArrowRight" || code == 39;

                    let add_space = event.key == " " || code == 32 || (is_enter && (shift_pressed || ctrl_pressed));

                    if(add_space){

                        window.hWin.HEURIST4.util.stopEvent(event);
                        event.stopImmediatePropagation();

                        let value = $input.val();
                        let start = $input[0].selectionStart;
                        let end = $input[0].selectionEnd;

                        // Add space and update value
                        value = `${value.substring(0, start)} ${value.substring(end)}`;
                        $input.val(value);

                        // Correct cursor position
                        start_pos = ++start;
                        $input[0].setSelectionRange(start_pos, start_pos);
                    }else if(is_enter && $menu.find('.ui-menu-item:visible').length == 1){ // auto select only result

                        window.hWin.HEURIST4.util.stopEvent(event);
                        event.stopImmediatePropagation();

                        $menu.find('.ui-menu-item:visible').trigger('mouseover').trigger('click'); // trigger selection, needs focus first
                    }else if(is_tab && $menu.find('.ui-menu-item:visible').length > 1){ // focus first item

                        window.hWin.HEURIST4.util.stopEvent(event);
                        event.stopImmediatePropagation();

                        $($menu.find('.ui-menu-item:visible')[1]).trigger('mouseover'); // change focus to options
                    }else if((event.key == "A" || code == 13) && ctrl_pressed){

                        window.hWin.HEURIST4.util.stopEvent(event);
                        event.stopImmediatePropagation();

                        // Highlight input text
                        $input[0].setSelectionRange(0, cur_val.length);
                        start_pos = cur_val.length;
                    }else if(left_arrow || right_arrow){

                        // ensure start is within bounds
                        start_pos = start_pos < 0 ? 0 : start_pos;
                        start_pos = start_pos > cur_val.length ? cur_val.length : start_pos;

                        let swap_start = false;
                        let cur_start = $input[0].selectionStart;
                        let end_pos = $input[0].selectionEnd;

                        if(ctrl_pressed && shift_pressed){

                            if(cur_start == end_pos){
                                start_pos = right_arrow ? cur_start : 0;
                                end_pos = right_arrow ? cur_val.length : end_pos;
                                swap_start = right_arrow;
                            }else if(start_pos == end_pos){ // already selected section
                                start_pos = cur_start;
                                end_pos = right_arrow ? cur_val.length : start_pos;
                                swap_start = true;
                            }else{
                                start_pos = right_arrow ? end_pos : 0;
                            }

                        }else if(shift_pressed){

                            if(cur_start == end_pos){
                                start_pos = right_arrow ? cur_start : --cur_start;
                                end_pos = right_arrow ? ++end_pos : end_pos;
                                swap_start = right_arrow;
                            }else if(start_pos == end_pos){ // already selected section
                                start_pos = cur_start;
                                end_pos = right_arrow ? ++end_pos : --end_pos;
                                swap_start = true;
                            }else{
                                start_pos = right_arrow ? ++cur_start : --cur_start;
                            }

                        }else if(ctrl_pressed){

                            start_pos = right_arrow ? cur_val.length : 0;
                            end_pos = start_pos;

                        }else{

                            if(cur_start == end_pos){
                                start_pos = right_arrow ? ++start_pos : --start_pos;
                            }else if(start_pos == end_pos){
                                start_pos = right_arrow ? start_pos : cur_start; 
                            }else{
                                start_pos = right_arrow ? end_pos : start_pos;
                            }

                            start_pos = start_pos < 0 ? 0 : start_pos;
                            start_pos = start_pos > cur_val.length ? cur_val.length : start_pos;

                            end_pos = start_pos;
                        }

                        $input[0].setSelectionRange(start_pos, end_pos);

                        if(swap_start){ // replace start_pos w/ end_pos
                            start_pos = end_pos;
                        }

                    }else{
                        ++start_pos
                    }
                }
			});
		}
        
        let btn_add_term = $menu.find('.add-trm');
        if(btn_add_term.length>0){
            that._on(btn_add_term, {
                click: function(){

                    let suggested_name = $menu.find('input.input_menu_filter').val();
                    let vocab_id = that.f('rst_FilteredJsonTermIDTree');

                    let rg_options = {
                        isdialog: true, 
                        select_mode: 'manager',
                        edit_mode: 'editonly',
                        height: 240,
                        rec_ID: -1,
                        trm_VocabularyID: vocab_id,
                        suggested_name: suggested_name, 
                        create_one_term: true,
                        onClose: function(trm_id){
                            let trm_info = $Db.trm(trm_id, 'trm_ParentTermID'); 
                            if(trm_info > 0){
                                if(that.selObj){
                                    let ref_id = that.selObj.attr('ref-id');
                                    that.selObj.remove();    
                                    that.selObj = null;
                                    
                                    let $input = that.element.find('#'+ref_id);
                                    browseTerms(that, $input, trm_id);                                    
                                }
                            }
                        }
                    };

                    window.hWin.HEURIST4.ui.showEntityDialog('defTerms', rg_options);
                }
            });
        }

        // Add term image to dropdown options
        $menu.find('.ui-menu-item .ui-menu-item-wrapper').each(function(idx, option){

            let trm_id = $select.find(`option:nth-child(${idx+1})`).val();

            if(trm_id == 'select' || window.hWin.HEURIST4.util.isempty(trm_id)){
                return;
            }

            let icon = window.hWin.HAPI4.getImageUrl('defTerms', trm_id, 'icon', null, null, true);

            icon = `<img src='${window.hWin.HAPI4.baseURL}hclient/assets/16x16.gif' style='background-image: url("${icon}");' />`;

            $('<span>', {style: 'position: absolute;right: 5px;'}).html(icon).appendTo($(option).css('padding-right', '25px'));
        });

        let $trm_btns = $select.parents('.input-div').find('.btn_add_term');
        if($trm_btns.length > 0){
            $trm_btns.clone(true, true).css({
                'position': 'relative',
                'margin': '0px 2.5px'
            }).appendTo($menu.find('span.trm-btns'));
        }

        $inpt.parents('.ui-menu-item-wrapper').removeClass('ui-menu-item-wrapper ui-state-active');
    }

    // Alter width of menu for term fields
    let enum_fld = $select.parents('.input-div').find('.enum-selector');
    if(enum_fld.length > 0){

        $menu.width('auto');
        let width = $menu.width();

        if((width + 30) < 200){
            $menu.width(200);
        }else{
            $menu.width(width+30); // make slightly bigger than needed to avoid resizing
        }
    }else{

        setTimeout(() => {

            // Increase max-height of menuWidget if there is space
            const defMaxHeight = 220;
            let $editor = $select.parents('.editForm.recordEditor');
            let editorTop = $editor.length > 0 ? $editor.position().top : 0;

            let menuTop = $menu.position().top;
            let $widget = $select.hSelect('widget').is(':visible') ? $select.hSelect('widget') : $select.parents('.input-div').find('.sel_link2');
            let widgetTop = $widget.position().top;

            if($editor.length > 0 && menuTop > widgetTop){ // check that the editor container exists & that the menu is going downwards

                let editorHeight = $editor.height();
                let newHeight = editorHeight - menuTop - 20;

                $menu.css('max-height', `${newHeight < defMaxHeight ? defMaxHeight : newHeight}px`);
            }else{
                $menu.css('max-height', `${defMaxHeight}px`);
            }
        }, 500);
    }

    $inpt.trigger('select'); // auto focus + highlight existing search
}

/**
 * Provides functionality to browse and select Heurist records for a pointer field.
 * It handles different pointer modes (e.g., addonly, browseonly, dropdown) and
 * can open a detailed record selection dialog or a dropdown list based on configuration
 * and cached data.
 * 
 * @memberof Widgets.Editing
 * @uses window.hWin.HEURIST4.browseRecordCache
 *  
 * @param {object} _editing_input - The instance of the `editing_input` widget this browser is for.
 *                                  Provides context and configuration for the record selection.
 * @param {jQuery} $input - The jQuery element (typically a `<div>` or `<span>`) that displays
 *                          the currently selected record's title and acts as a trigger for opening
 *                          the browser/dropdown.
 * @param {string} popupTitle - title for popup dialog.
 * @returns {function(string,Event): void} A function (`__show_select_dropdown` or `__show_select_dialog`)
 *                                         that can be called to programmatically open the record browser/dropdown.
 *                                         This returned function itself takes an event object or an input ID string.
 */
function browseRecords(_editing_input, $input, popupTitle){

    let that = _editing_input;
    
    let $inputdiv = $input.parent(); //div.input-div
    let __current_input_id = $input.attr('id'); //__current_input_id is used within __show_select_dialog, which might be called later when `that` or `$input` context could be different if not careful

    if ($inputdiv.find('.sel_link2 > .ui-button-icon').hasClass('rotate')) return;
    
    let isparententity = (that.f('rst_CreateChildIfRecPtr')==1);
    let pointerMode = that.f('rst_PointerMode');
    
    if(isparententity && pointerMode!='addonly'){
        pointerMode = 'dropdown_add';
    }
    
    let is_dropdown = (pointerMode && pointerMode.indexOf('dropdown')===0);
    
    let s_action = '';
    if(pointerMode=='addonly'){
        s_action = 'create';
    }else if(pointerMode=='browseonly' || pointerMode=='dropdown'){
        s_action = 'select';
        pointerMode = 'browseonly';
    }else{
        s_action = 'select or create';
        pointerMode = 'addorbrowse';
    }
    
    if(!popupTitle){
        popupTitle = window.hWin.HR((isparententity)
                        ?('CHILD record pointer: '+s_action+' a linked child record')
                        :('Record pointer: '+s_action+' a linked record'));    
    }

    let popup_options = {
                    select_mode: (that.configMode.csv==true?'select_multi':'select_single'),
                    select_return_mode: 'recordset',
                    edit_mode: 'popup',
                    selectOnSave: true, //it means that select popup will be closed after add/edit is completed
                    title: popupTitle,
                    rectype_set: that.f('rst_PtrFilteredIDs'),
                    pointer_mode: pointerMode,
                    pointer_filter: that.f('rst_PointerBrowseFilter'),  //initial filter
                    pointer_field_id: (isparententity)?0:that.options.dtID,
                    pointer_source_rectype:  (isparententity)?0:that.options.rectypeID,
                    parententity: (isparententity)?that.options.recID:0,
                    
                    onselect: function(event, data){
                             if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){

                                let f_id = $('#'+__current_input_id).parents('fieldset').attr('id');
                                
                                if(!f_id && that.options.editing){
                                    //for parent-child there is chance that edit form can be reloaded after open this popup
                                    //and original target elements will be missed (it saves record to obtain title)
                                    //we have to find new targets 
                                    let edit_ele = that.options.editing.getFieldByName(that.options.dtID);    
                                            
                                    $input = null;   
                                    let inputs = edit_ele.editing_input('getInputs');
                                    for (let idx in inputs) {
                                        //$(edit_ele.editing_input('getInputs')[idx])
                                        if($(inputs[idx]) && $(inputs[idx]).parent().find('.child_rec_fld:visible').length>0){
                                            $input = inputs[idx];
                                            break;
                                        }
                                    }
                                    if(!$input){ //last resort - take last one
                                       $input = inputs[inputs.length-1];
                                    }
                                }else{
                                     let inpt = that.element.find('#'+__current_input_id);
                                     if(inpt.length>0){
                                        $input = inpt;   
                                     }
                                }
                                
                                 
                                let recordset = data.selection;
                                let record = recordset.getFirstRecord();
                                
                                const rec_Title = recordset.fld(record,'rec_Title');
                                if(window.hWin.HEURIST4.util.isempty(rec_Title)){
                                    // no proper selection 
                                    // consider that record was not saved - it returns FlagTemporary=1 
                                    return;
                                }
                               
                                const targetID = recordset.fld(record,'rec_ID');
                                const rec_RecType = recordset.fld(record,'rec_RecTypeID');
                                
                                that.newvalues[$input.attr('id')] = targetID;
                                $input.attr('data-value', targetID); //that's more reliable

                                //save last 25 selected records
                                let now_selected = data.selection.getIds(25);
                                window.hWin.HAPI4.save_pref('recent_Records', now_selected, 25);      
                                
                                
                                $input.empty();
                                let ele = window.hWin.HEURIST4.ui.createRecordLinkInfo($input, 
                                    {rec_ID: targetID, 
                                     rec_Title: rec_Title, 
                                     rec_RecTypeID: rec_RecType,
                                     rec_IsChildRecord:isparententity
                                    }, __show_select_dialog);
                                
                                that.onChange();
                                ele.css({margin:'4px', 'border':'2px red solid !important'});
                                
                                let $inputdiv = $input.parent();
                                $inputdiv.css('border','4px green solid !important');
                                $input.css('border','1px blue solid');

                                if( $inputdiv.find('.link-div').length>0 ){ //hide this button if there are links
                                    $input.show();
                                    $inputdiv.find('.sel_link2').hide(); 
                                }else{
                                    $input.hide();
                                    $inputdiv.find('.sel_link2').show();
                                }
                                
                             }
                    }
    }; //popup_options

    // select/add target record with help of manageRecords popup dialog
    //
    // event is false for confirmation of select mode for parent entity
    // 
    let __show_select_dialog = function(event){

            if(that.is_disabled) return;
        
            if(event!==false){
        
                if(event) event.preventDefault();
     
                if(popup_options.parententity>0){
                    
                    if(that.newvalues[$input.attr('id')]>0){
                        
                        window.hWin.HEURIST4.msg.showMsgFlash('Points to a child record; value cannot be changed (delete it or edit the child record itself)', 2500);
                        return;
                    }
                    //__show_select_dialog(false); 
                }
            }

            // Save record first without validation, only if this is a new record
            if(that.options.editing){
                let et = that.options.editing.getFieldByName('rec_Title');
                
                let isparententity = (that.f('rst_CreateChildIfRecPtr')==1);
                if(et && et.editing_input('instance') && et.editing_input('getValues')[0] == '' && isparententity){

                    let is_empty = true;
                    let fields = that.options.editing.getValues(false);
                    for (let dtid in fields) {
                        if(parseInt(dtid)>0 && fields[dtid]!=''){
                            is_empty = false;
                            break;
                        }
                    }
                    if(is_empty){
                        window.hWin.HEURIST4.msg.showMsgFlash('To add child record you have to define some fields in parent record<br>(it is required to compose valid record title)', 2500);    
                        return;
                    }else if(that.options.editing && window.hWin.HEURIST4.util.isFunction(that.options.editing.getOptions().onaction)){
                        //quick save without validation
                        that.options.editing.getOptions().onaction(null, 'save_quick');
                    }
                }
            }

            if(that.selObj?.hSelect('instance') !== undefined){
                popup_options['init_filter'] = $(that.selObj).hSelect('menuWidget').find('.input_menu_filter').val();
            }

            let usrPreferences = window.hWin.HAPI4.get_prefs_def('select_dialog_'+that.configMode.entity, 
                {width: null,  //null triggers default width within particular widget
                height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });

            popup_options.width = Math.max(usrPreferences.width,710);
            popup_options.height = (s_action=='create')?160:Math.max(usrPreferences.height,600);
            
            if(pointerMode!='browseonly' && that.options.editing && that.configMode.entity=='records'){
                
                let ele = that.options.editing.getFieldByName('rec_OwnerUGrpID');
                if(ele){
                    let vals = ele.editing_input('getValues');
                    ele = that.options.editing.getFieldByName('rec_NonOwnerVisibility');
                    let vals2 = ele.editing_input('getValues');
                    popup_options.new_record_params = {};
                    popup_options.new_record_params['ro'] = vals[0];
                    popup_options.new_record_params['rv'] = vals2[0];
                }
            }
            
            //init related/liked records selection dialog - selectRecord
            window.hWin.HEURIST4.ui.showEntityDialog(that.configMode.entity, popup_options);
    }

    
    if(is_dropdown && !isparententity && !(popup_options.parententity>0)){
        
        // select target record from cached drop down
        //
        let __show_select_dropdown = function(event_or_id){
          
            if(that.is_disabled) return;
            
            let $input, $inputdiv, ref_id;
            
            if(typeof event_or_id == 'string'){
                
                ref_id = event_or_id;
                $input = that.element.find('#'+ref_id);
                $inputdiv = $input.parents('.input-div');
                
            }else
            if(event_or_id && event_or_id.target){
                
                let event = event_or_id;
            
                $inputdiv = $(event.target).parents('.input-div');
                $input = $inputdiv.find('div:first');
                ref_id = $input.attr('id');

                if(event) event.preventDefault();
            }
            
            
            let key = that.f('rst_RecTypeID')+'-'+that.f('rst_DetailTypeID');
			let recordMax = 5000;
    
            if(!window.hWin.HEURIST4.browseRecordCache){
                window.hWin.HEURIST4.browseRecordCache = {};
            }
            if(!window.hWin.HEURIST4.browseRecordTargets){
                window.hWin.HEURIST4.browseRecordTargets = {};
            }
            if(window.hWin.HEURIST4.browseRecordMax){
                recordMax = window.hWin.HEURIST4.browseRecordMax;
            }
            
            if(window.hWin.HEURIST4.browseRecordCache[key]=='zero' || window.hWin.HEURIST4.browseRecordCache[key] > recordMax){
  
                __show_select_dialog(); //show usual dialog
                
            }else if(!window.hWin.HEURIST4.browseRecordCache[key]){ //cache does not exist - search for it
            
                    $inputdiv.find('.sel_link2 > .ui-button-icon').removeClass('ui-icon-triangle-1-e');
                    $inputdiv.find('.sel_link2 > .ui-button-icon').addClass('ui-icon-loading-status-circle rotate');
                
                    let rectype_set = that.f('rst_PtrFilteredIDs');
                    let qobj = (rectype_set)?[{t:rectype_set}]:null;
                    let pointer_filter = that.f('rst_PointerBrowseFilter');
                    if(pointer_filter){
                        if(qobj==null){
                            qobj = pointer_filter;
                        }else{
                            qobj = window.hWin.HEURIST4.query.mergeHeuristQuery(qobj, pointer_filter);
                        }
                    }
                    if(window.hWin.HEURIST4.util.isempty(qobj)){
                        window.hWin.HEURIST4.msg.showMsgFlash('Constraints or browse filter not defined');       
                        setTimeout(__show_select_dialog, 2000);
                        return;
                    }
                    
                    qobj.push({"sortby":"t"}); //sort by title
                    
                    let request = {
                        q: qobj,
                        w: 'a',
                        source:'_browseRecords',
                        detail: 'count'};
                    window.hWin.HAPI4.RecordMgr.search(request, function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            
                            function __assignCache(value){
                                
                                   $inputdiv.find('.sel_link2 > .ui-button-icon').addClass('ui-icon-triangle-1-e');
                                   $inputdiv.find('.sel_link2 > .ui-button-icon').removeClass('ui-icon-loading-status-circle rotate');
                                
                                   window.hWin.HEURIST4.browseRecordCache[key] = value;
                                   if(!rectype_set) rectype_set = 'any';
                                   rectype_set = rectype_set.split(',');
                                   $.each(rectype_set, function(i,rty_id){
                                       rty_id = ''+rty_id;
                                       if(!window.hWin.HEURIST4.browseRecordTargets[rty_id]){
                                           window.hWin.HEURIST4.browseRecordTargets[rty_id] = [];
                                       }
                                       window.hWin.HEURIST4.browseRecordTargets[rty_id].push(key);
                                   });
                            }
                            
                            if(response.data.count>recordMax){
                                __assignCache(response.data.count);
                                __show_select_dialog();
                            }else if (response.data.count==0){
                                __assignCache('zero');
                                window.hWin.HEURIST4.msg.showMsgFlash('No records for Browse filter');
                                setTimeout(__show_select_dialog, 1000);
                            }else{
                                
                                let request = {
                                    q: qobj,
                                    restapi: 1,
                                    columns:['rec_ID', 'rec_RecTypeID', 'rec_Title'],
                                    zip: 1,
                                    format:'json'};
                                
                                that.is_disabled = true;
                                
                                if(!that.selObj){
                                    that._off($(that.selObj), 'change');   
                                    $(that.selObj).remove();   
                                    that.selObj = null;
                                }
                                    
                                window.hWin.HAPI4.RecordMgr.search_new(request,
                                function(response){
                                   that.is_disabled = false;
                                   if(window.hWin.HEURIST4.util.isJSON(response)) {
                                       if(response['records'] && response['records'].length>0){

                                           //keep in cache
                                           __assignCache(response['records']);
                                           __show_select_dropdown(ref_id); //call again after loading list of records

                                       }else{
                                           //nothing found
                                           __assignCache('zero');
                                           window.hWin.HEURIST4.msg.showMsgFlash('No records for Browse filter');
                                               setTimeout(__show_select_dialog, 1000);
                                       }
                                   }else{
                                        window.hWin.HEURIST4.msg.showMsgErr(response);       
                                   }
                                });
                                
                            }
                        }
                    });
                        
                    return;
            }else{
                //load from cache
                
                //recreate dropdown
                if(!that.selObj || !$(that.selObj).hSelect('instance')){

                    if(that.selObj){
                        $(that.selObj).remove();
                    }
                    
                    that.selObj = window.hWin.HEURIST4.ui.createSelector(null);

                    $(that.selObj).attr('rectype-select', 1);
                    $(that.selObj).appendTo($inputdiv);
                    $(that.selObj).hide();

                    let search_icon = window.hWin.HAPI4.baseURL+'hclient/assets/v6/magglass_12x11.gif',
                        filter_icon = window.hWin.HAPI4.baseURL+'hclient/assets/v6/filter_icon_black18.png',
                        add_link = s_action == 'select'
                            ? '' : '<span style="padding-left: 1em;"><span class="ui-icon ui-icon-plus" style="position: relative;padding-right: 5px;"></span> Add target record</span>';

                    let opt = window.hWin.HEURIST4.ui.addoption(that.selObj, 'select', 
                    '<div style="width:300px;padding:15px 0px">'
                    +'<span style="padding:0px 4px 0 10px;vertical-align:sub">'
                    +'<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
                    + '" class="rt-icon rt-icon2" style="background-image: url(&quot;'+filter_icon+ '&quot;);"/></span>'
                    +'<input class="input_menu_filter" size="10" style="outline: none;background:none;border: 1px solid lightgray;"/>'
+'<span class="smallbutton ui-icon ui-icon-circlesmall-close" tabindex="-1" title="Clear entered value" '
+'style="position:relative; cursor: pointer; outline: none; box-shadow: none; border-color: transparent;"></span>'
                    +'<span class="show-select-dialog" style="cursor:pointer;"><span style="padding: 0px 4px 0 5px;vertical-align:sub;">'
                    +'<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
                    + '" class="rt-icon rt-icon2" style="background-image: url(&quot;'+search_icon+ '&quot;);"/></span>'
                    + add_link
                    + '</span><div class="not-found" style="padding:10px;color:darkgreen;display:none;">'
                    +window.hWin.HR('No records match the filter')+'</div></div>');


                    $.each(window.hWin.HEURIST4.browseRecordCache[key], function(idx, item){
                        
                        let title = item['rec_Title'].slice(0,64).replace(/[\r\n]+/g, ' ');
                        
                        let opt = window.hWin.HEURIST4.ui.addoption(that.selObj, item['rec_ID'], title); 
                        
                        let icon = window.hWin.HAPI4.iconBaseURL + item['rec_RecTypeID'];
                        $(opt).attr('icon-url', icon);
                        $(opt).attr('data-rty', item['rec_RecTypeID']);
                    });
                    
                    let events = {};
                    events['onOpenMenu'] = function(){

                        let ele = that.selObj.hSelect('menuWidget');
                        ele.css('max-width', '500px');
                        ele.find('div.ui-menu-item-wrapper').addClass('truncate');
                        ele.find('.rt-icon').css({width:'12px',height:'12px','margin-right':'10px'});
                        ele.find('.rt-icon2').css({'margin-right':'0px'});

                        openSearchMenu(that, that.selObj, true, false);
                    };

                    events['onSelectMenu'] = function ( event ){
                        
                        let $mnu = that.selObj.hSelect('menuWidget');
                        if($mnu.find('.ui-menu-item-wrapper:first').css('cursor')=='progress'){
                            openSearchMenu(that, that.selObj, false, false);
                            return;
                        }
                        
                        let targetID = (event) ?$(event.target).val() :$(that.selObj).val();
                        if(!targetID) return;

                        that._off($(that.selObj),'change');
                        
                        let ref_id = $(that.selObj).attr('ref-id');
                        
                        if(targetID=='select'){
                            __current_input_id = ref_id;
                           __show_select_dialog(); 
                        }else{
                            
                            let $input = $('#'+ref_id);
                            let $inputdiv = $('#'+ref_id).parent();

                            let opt = $(that.selObj).find('option:selected');
                            
                            let rec_Title = opt.text();
                            let rec_RecType = opt.attr('data-rty');
                            that.newvalues[$input.attr('id')] = targetID;
                            $input.attr('data-value', targetID); //that's more reliable
                            $input.empty();
                            let ele = window.hWin.HEURIST4.ui.createRecordLinkInfo($input, 
                                {rec_ID: targetID, 
                                 rec_Title: rec_Title, 
                                 rec_RecTypeID: rec_RecType,
                                 rec_IsChildRecord:false
                                }, __show_select_dropdown);
                           
                            that.onChange();
                            
                            if( $inputdiv.find('.link-div').length>0 ){ //hide this button if there are links
                                $input.show();
                                $inputdiv.find('.sel_link2').hide(); 
                            }else{
                                $input.hide();
                                $inputdiv.find('.sel_link2').show();
                            }
                        }
                    }

                    $inputdiv.addClass('selectmenu-parent');
                    $(that.selObj).css('max-width','300px');
                    that.selObj = window.hWin.HEURIST4.ui.initHSelect(that.selObj, false, null, events);
                }else{
                    that._off($(that.selObj), 'change');    
                }

                let org_scroll = $inputdiv.parents('.editForm').length > 0 ?
                                    $inputdiv.parents('.editForm')[0].scrollTop : null;
                
                let $inpt_ele = $inputdiv.find('.sel_link2'); //button
                let _ref_id = $input.attr('id');
               
                
                if($inpt_ele.is(':hidden') && $inputdiv.find('.link-div').length == 1){
                    $inpt_ele = $inputdiv.find('.link-div');
                }

                that.selObj.attr('ref-id', _ref_id);
                that.selObj.hSelect('open');
                that.selObj.hSelect('widget').hide();

                let prn = that.selObj.hSelect('menuWidget').parent('div.ui-selectmenu-menu');
                if(prn.length>0){
                    prn.css({'position':'fixed'}); //to show above all 
                    if(org_scroll !== null){ // fix scroll
                        prn.parents('.editForm').scrollTop(org_scroll);
                    }
                }
                that.selObj.hSelect('menuWidget')
                        .css('background', '#E1FFFF')
                        .position({my: "left top", at: "left bottom", of: $inpt_ele});

            }
        } //__show_select_dropdown
        
    
        that._on( $inputdiv.find('.sel_link2'), { click: __show_select_dropdown } ); //main invocation of dialog - via button "select record"

        return __show_select_dropdown;
    }else{
        that._on( $inputdiv.find('.sel_link2'), { click: __show_select_dialog } );
        return __show_select_dialog;
    }
}

/**
 * Provides functionality to browse and select enumerations (terms)
 * 
 * @memberof Widgets.Editing
 * @param {object} _editing_input - The instance of the `editing_input` widget this browser is for.
 *                                  Provides context and configuration for the record selection.
 * @param {jQuery} $input - The jQuery element (typically a `<div>` or `<span>`) that displays
 *                          the currently selected record's title and acts as a trigger for opening
 *                          the browser/dropdown.
 * @param {string|int} value - Current term value
 */
function browseTerms(_editing_input, $input, value){
    
    let that = _editing_input;
    
    let $inputdiv = $input.parent(); //div.input-div

        
    function __recreateTrmLabel($input, trm_ID){

        let lang_code = that.options.language;
        if(!window.hWin.HEURIST4.util.isempty(lang_code) && lang_code != 'ALL' && !window.hWin.HAPI4.EntityMgr.getEntityData2('trm_Translation')){ // retrieve translations

            window.hWin.HAPI4.EntityMgr.getTranslatedDefs('defTerms', 'trm', null, function(){
                __recreateTrmLabel($input, trm_ID);
            });
            lang_code = '';
           
        }

        $input.empty();
        if(!$input[0]) return;
        window.hWin.HEURIST4.ui.addoption($input[0], '', '&nbsp;');
        if(window.hWin.HEURIST4.util.isNumber(trm_ID) && trm_ID>0){
            
            let trm_Label = $Db.trm_getLabel(trm_ID, lang_code);
            let trm_info = $Db.trm(trm_ID);

            while(trm_info && trm_info.trm_ParentTermID > 0){

                let label = $Db.trm_getLabel(trm_info.trm_ParentTermID, lang_code);
                trm_info = $Db.trm(trm_info.trm_ParentTermID);

                if(trm_info && trm_info.trm_ParentTermID > 0){
                    trm_Label = label + '.' +  trm_Label;
                }
            }

            trm_Label = $Db.trm_RemoveDupHierarchy(trm_Label);
        
            window.hWin.HEURIST4.ui.addoption($input[0], trm_ID, trm_Label);
            $input.css('min-width', '');
        }else{
            $input.css('min-width', '230px');
            trm_ID = '';
        }
        $input.val(trm_ID);

        if($input.hSelect('instance') !== undefined){
            $input.hSelect('refresh');
        }
    }    

    function __createTermTooltips($input){

        let $menu = $input.hSelect('menuWidget');
        if($input.attr('data-tooltips')){
            return;
        }

        let $tooltip = null;
        $input.attr('data-tooltips', 1);

        $menu.find('li.ui-menu-item')
             .on('mouseenter', (event) => { // create tooltip

                let $target_ele = $(event.target);

                if(($target_ele.children().length != 0 && $target_ele.find('img').length != 1) || $target_ele.find('div.ui-menu-item-wrapper').text() == '<blank>'){
                    return;
                }

                let term_id = $target_ele.attr('data-hid');
                let details = '';

                if(window.hWin.HEURIST4.util.isPositiveInt(term_id)){

                    let term = $Db.trm(term_id);
                    if(!window.hWin.HEURIST4.util.isempty(term.trm_Code)){
                        details += "<span style='text-align: center;'>Code &rArr; " + term.trm_Code + "</span>";
                    }

                    if(!window.hWin.HEURIST4.util.isempty(term.trm_Description)){

                        if(details == ''){
                            details = "<span style='text-align: center;'>Code &rArr; N/A </span>";
                        }
                        details += "<hr><span>" + term.trm_Description + "</span>";
                    }
                }

                if(details == ''){
                    details = "No Description Provided";
                }

                $tooltip = $menu.tooltip({
                    items: "div.ui-state-active",
                    position: { // Post it to the right of menu item
                        my: "left+20 center",
                        at: "right center",
                        collision: "none"
                    },
                    show: { // Add slight delay to show
                        delay: 1500,
                        duration: 0
                    },
                    content: function(callback){ // Check for image, then provide text

                        const ele_context = this;

                        window.hWin.HAPI4.checkImage('defTerms', term_id, 'icon', function(response){

                            if(response.status == window.hWin.ResponseStatus.OK && response.data == 'ok'){

                                let icon = window.hWin.HAPI4.getImageUrl('defTerms', term_id, 'icon', null, null, true);
                                details += `<br><br><img src='${window.hWin.HAPI4.baseURL}hclient/assets/16x16.gif' style='background-image: url("${icon}")' height=64 width=64 />`;
                            }

                            callback.call(ele_context, details);
                        });

                        return '';
                    },
                    open: function(event, ui){ // Add custom CSS + class
                        ui.tooltip.css({
                            "width": "200px",
                            "background": "rgb(209, 231, 231)",
                            "font-size": "1.1em"
                        });
                    }
                });
             })
             .on('mouseleave', (event) => { // ensure tooltip is gone
                if($tooltip && $tooltip.tooltip('instance') != undefined){
                    $tooltip.tooltip('destroy');
                }
             });
    }

    function __recreateSelector(){

        if(that.selObj){
            $(that.selObj).remove();
        }


        let allTerms = that.f('rst_FilteredJsonTermIDTree');        
        //headerTerms - disabled terms
        let headerTerms = that.f('rst_TermIDTreeNonSelectableIDs') || that.f('dty_TermIDTreeNonSelectableIDs');
        let lang_code = that.options.language;

        if(window.hWin.HEURIST4.util.isempty(allTerms) &&
            that.options.dtID==window.hWin.HAPI4.sysinfo['dbconst']['DT_RELATION_TYPE'])
        { //specific behaviour - show all
            allTerms = 'relation'; //show all possible relations
        }else if(typeof allTerms == 'string' && allTerms.indexOf('-')>0){ //vocabulary concept code
            allTerms = $Db.getLocalID('trm', allTerms);
        }else if(!window.hWin.HEURIST4.util.isempty(lang_code) && lang_code != 'ALL'
            && !window.hWin.HAPI4.EntityMgr.getEntityData2('trm_Translation')){
            window.hWin.HAPI4.EntityMgr.getTranslatedDefs('defTerms', 'trm', null, __recreateSelector);
            return;
        }


        let search_icon = window.hWin.HAPI4.baseURL+'hclient/assets/v6/filter_icon_black18.png';

        let  filter_form = '<div style="padding:10px 0px">'
        +'<span style="padding-right:10px;vertical-align:sub">'
        +'<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        + '" class="rt-icon rt-icon2" style="background-image: url(&quot;'+search_icon+ '&quot;);"/></span>'
        +'<input class="input_menu_filter" size="8" style="outline: none;background:none;border: 1px solid lightgray;"/>'
        +'<span class="smallbutton ui-icon ui-icon-circlesmall-close" tabindex="-1" title="Clear entered" '
        +'style="position:relative; cursor: pointer; outline: none; box-shadow: none; border-color: transparent;"></span>'
        +'<span class="trm-btns" style="padding: 0 0 0 10px;cursor: pointer;"></span>'
        + '<div class="not-found" style="padding:10px;color:darkgreen;display:none;width:210px;">No terms match the filter '
        + '<a class="add-trm" href="#" style="padding: 0 0 0 10px;color:blue;display:inline-block;">Add term</a>'
        +'</div></div>';

        let topOptions = [{key:'select',title:filter_form},{key:'',title:'&lt;blank&gt;'}];

        let events = {};
        events['onOpenMenu'] = function(){
            __createTermTooltips(that.selObj);
            openSearchMenu(that, that.selObj, true, true);
            that.selObj.hSelect('refreshGroupings', true);
        };

        events['onSelectMenu'] = function ( event ){

            let trm_ID = (event) ?$(event.target).val() :$(that.selObj).val();

            that._off($(that.selObj),'change');

            let ref_id = $(that.selObj).attr('ref-id');

            let $input = $('#'+ref_id);
            that.newvalues[$input.attr('id')] = trm_ID;
            $input.attr('data-value', trm_ID); //that's more reliable

            __recreateTrmLabel($input, trm_ID);
            /*
            $input.empty(); //clear 
            //add new value
            $('<span tabindex="0"class="ui-selectmenu-button ui-button ui-widget ui-selectmenu-button-closed ui-corner-top" style="padding: 0px; font-size: 1.1em; width: auto; min-width: 10em;">'
            +'<span class="ui-selectmenu-icon ui-icon ui-icon-triangle-1-s"></span><span class="ui-selectmenu-text" style="min-height: 17px;">'
            + window.hWin.HEURIST4.util.htmlEscape(trm_Label)
            +'</span></span>').appendTo($input);
            */
            that.onChange();

        };

        events['onCloseMenu'] = function (event){

            let $menu = that.selObj.hSelect('menuWidget');

            // Reset filter input
            $menu.find('.input_menu_filter').val('');
            $menu.find('li').css('display','list-item');
        };

        $inputdiv.addClass('selectmenu-parent');

        that.selObj = document.createElement("select");
        $(that.selObj).addClass('enum-selector-main')
        .css('max-width','300px')
        .appendTo($inputdiv);

        that.selObj = window.hWin.HEURIST4.ui.createTermSelect(that.selObj,
            {vocab_id:allTerms, //headerTermIDsList:headerTerms,
                defaultTermID:$input.val(), topOptions:topOptions, supressTermCode:true, 
                useHtmlSelect:false, eventHandlers:events, language_code: lang_code});

        that.selObj.hSelect('option', { groupings: allTerms !== 'relation', groupingsType: 'trm' });

        that.selObj.hSelect('menuWidget').css('background', '#E1FFFF');

        $(that.selObj).hide(); //button will be hidden        
    }
    
    //
    // select term from drop down
    //
    let __show_select_dropdown = function(event_or_id){
        
        if(that.is_disabled) return;
        
        let $input, $inputdiv, ref_id; 
        
        if(typeof event_or_id == 'string'){ //id
            
            ref_id = event_or_id; 
            $input = that.element.find('#'+ref_id);
            $inputdiv = $input.parents('.input-div');
            
        }else 
        if(event_or_id && event_or_id.target){ //event
            
            let event = event_or_id;
        
            $inputdiv = $(event.target).parents('.input-div');
            $input = $inputdiv.find('select');
            ref_id = $input.attr('id');

            if(event) event.preventDefault();
        }

        let org_scroll = $inputdiv.parents('.editForm').length > 0 ?
                    $inputdiv.parents('.editForm')[0].scrollTop : null;
        
        //recreate dropdown if not inited
        if(!that.selObj || !that.selObj.hSelect('instance')){

            __recreateSelector();
            
            //case for window.hWin.HEURIST4.util.isempty(lang_code) && lang_code != 'ALL' - it requests for translations
            if(!that.selObj || !that.selObj.hSelect('instance')){
                return;
            }
        }else{
            that._off($(that.selObj), 'change');    
        }
            
        //Adjust position
        let _ref_id = $input.attr('id');
        let menu_location = $input;

        if($input.hSelect('instance') !== undefined){
            menu_location = $input.hSelect('widget');
        }

        that.selObj.attr('ref-id', _ref_id); //assign current input id for reference in onSelectMenu
        that.selObj.hSelect('open');
        that.selObj.hSelect('widget').hide();

        let prn = that.selObj.hSelect('menuWidget').parent('div.ui-selectmenu-menu');
        if(prn.length>0){
            prn.css({'position':'fixed'}); //to show above all 
            if(org_scroll !== null){ // fix scroll
                prn.parents('.editForm').scrollTop(org_scroll);
            }
        }
        that.selObj.hSelect('menuWidget')
            .position({my: "left top", at: "left bottom", of: menu_location});

    } //__show_select_dropdown
    
    that._off( $input, 'click');
    that._on( $input, { click: __show_select_dropdown } ); //main invocation of dropdown

    
    if($input.is('select')){
        $input.addClass('enum-selector').css({'min-width':'230px', width:'auto', 'padding-left': '15px'});
        
        __recreateTrmLabel($input, value);
        
        /*replace with div
        $input = $('<div>').uniqueId()
                .addClass('enum-selector')
                .appendTo( $inputdiv );
        */
    }
    
    return __show_select_dropdown;
}

/**
 * Opens a generic Heurist record selection dialog.
 *
 * @memberof Widgets.Editing
 * @param {object} [options] - Optional parameters to customize the record selection dialog.
 *                             These options are passed to `window.hWin.HEURIST4.ui.showEntityDialog`.
 *                             Common options include:
 *                             `rectype_set` (string|null): Comma-separated list of record type IDs to filter by.
 *                             `title` (string): Custom title for the dialog.
 *                             `select_mode` (string): 'select_single' or 'select_multi'.
 *                             `edit_mode` (string): 'popup' or 'none'.
 * @param {function(HRecordSet): void} callback - A function to be called when a record is selected.
 *                                              It receives an `HRecordSet` containing the selected record(s)
 *                                              as its argument.
 */
function selectRecord(options, callback)
{
        let popup_options = {
            select_mode: 'select_single', //select_multi
            select_return_mode: 'recordset', //or ids
            edit_mode: 'popup',//'none'
            selectOnSave: true, //it means that select popup will be closed after add/edit is completed
            title: window.hWin.HR('Select record'),
            rectype_set: null,
            parententity: 0, //a default value, might be overridden by options.
            default_palette_class: 'ui-heurist-populate',
            onselect:function(event, data){
                if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                    callback(data.selection);
                }
            }
        };//popup_options
        
        let usrPreferences = window.hWin.HAPI4.get_prefs_def('select_dialog_records', 
            {width: null,  //null triggers default width within particular widget
                height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });

        popup_options.width = Math.max(usrPreferences.width,710);
        popup_options.height = usrPreferences.height;

        if(options){
            popup_options = $.extend(popup_options,options);
        }
        
        window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);
}


/**
 * Opens a Entity (user, field, recordtype...) selection dialog.
 *
 * @memberof Widgets.Editing
 */
function selectEntity(options, callback)
{
    
        let usrPreferences = window.hWin.HAPI4.get_prefs_def('select_dialog_'+options.entity, 
            {width: null,  //null triggers default width within particular widget
             height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });

        //default option
        let popup_options = {
            isdialog: true,
            select_mode: (options.csv==true?'select_multi':'select_single'),
            select_return_mode: options.select_return_mode!='ids'?'recordset':'ids',
            title: window.hWin.HR(options.title??'Select'),
            
            width: usrPreferences.width,
            height: usrPreferences.height,
            
            filter_group_selected: null,
            filter_groups: options.filter_group,
            filters: options.filters,
            
            initial_filter: options.initial_filter,
            search_form_visible: options.search_form_visible??true, 

            selection_on_init: window.hWin.HEURIST4.util.isempty(options.values)?null:options.values.split(','),
            selectOnSave: options.selectOnSave??true, //it means that select popup will be closed after add/edit is completed
            
            //edit_mode: 'popup',//'none'
            //rectype_set: null,
            //parententity: 0,
            default_palette_class: options.default_palette_class??'ui-heurist-populate',
            onselect:function(event, data){
                
                if(!data) return;
                
                if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                    let recordset = data.selection;
                    callback(data.selection);
                }else{
                    let newsel = window.hWin.HEURIST4.util.isArrayNotEmpty(data.selection)?data.selection:[];
                    callback(newsel);
                }
            }
        };//popup_options

        if(options.popup_options){
            popup_options = $.extend(popup_options, options.popup_options);
        }
        
        if(options.entity=='records'){
            popup_options.width = Math.max(popup_options.width,710);   
        }     
        
        window.hWin.HEURIST4.ui.showEntityDialog(options.entity, popup_options);
}

