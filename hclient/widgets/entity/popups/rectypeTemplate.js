/**
* rectypeTemplate.js - download xml or json record type template
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$.widget( "heurist.rectypeTemplate", $.heurist.baseAction, {

    // default options
    options: {
        height: 520,
        width:  400,
        title:  'Download XML or JSON template',
        default_palette_class: 'ui-heurist-populate',
        path: 'widgets/entity/popups/', //location of this widget
        actionName: 'rectypeTemplate'
    },
    
    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){
        
        this._$('button#rectypes-select').button();

        this._on(this._$('button#rectypes-select, div#rectypes-list'),{click: function(){

            let $selected_rectypes = this._$('div#rectypes-list');

            let popup_options = {
                select_mode: 'select_multi',
                edit_mode: 'popup',
                isdialog: true,
                width: 540,
                title: 'Select record types',
                selection_on_init: $selected_rectypes.attr('data-ids').split(','),
                default_palette_class: 'ui-heurist-publish',

                onselect:function(event, data){

                    let ids = data.selection;

                    if(ids != null && window.hWin.HEURIST4.util.isArrayNotEmpty(ids)){

                        $selected_rectypes.attr('data-ids', data.selection.join(',')).text('');

                        for(let i = 0; i < ids.length; i++){

                            let name = $Db.rty(ids[i], 'rty_Name');

                            $selected_rectypes.append(
                                '<span class="truncate" style="display: inline-block;width: 155px; max-width: 155px;margin: 2.5px 0px" title="'+ name +'">'
                                    + name +
                                '</span>');

                            if((i+1) != ids.length){
                                $selected_rectypes.append('<br>');
                            }
                        }
                    }else{
                        $selected_rectypes.attr('data-ids', '').text('<span style="display: inline-block;margin: 5px 0px;"> None </span>');
                    }
                }
            };

            window.hWin.HEURIST4.ui.showEntityDialog('defRecTypes', popup_options);
        }});

        this._on(this._$('input#rectypes-all'),{change:function(event){
            window.hWin.HEURIST4.util.setDisabled(this._$('button#rectypes-select, div#rectypes-list'), $(event.target).is(':checked'));
        }});
        
        this._$('input#rectypes-all').trigger('change');
        
        return this._super();
    },


    //    
    //
    //
    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR('Download');
        res[1].disabled = null;
        return res;
    },

    //
    //
    //
    doAction: function(){

            let template_type = this._$('input[name="template-type"]:checked').attr('id');
            let rectype_ids = this._$('div#rectypes-list').attr('data-ids');
            let is_all_rectypes = this._$('input#rectypes-all').is(':checked');

            if(is_all_rectypes) { rectype_ids = 'y'; } // get all rectypes

            if(rectype_ids == null){
                window.hWin.HEURIST4.msg.showMsgFlash('Please select some record types...', 2000);
                return;
            }

            if(template_type == 'template-xml'){

                window.hWin.HEURIST4.util.downloadURL(window.hWin.HAPI4.baseURL
                        +'export/xml/flathml.php?file=1&'
                        +'rectype_templates='+ rectype_ids
                        +'&db='+window.hWin.HAPI4.database);
                        
            }else if(template_type == 'template-json'){

                window.hWin.HEURIST4.util.downloadURL(window.hWin.HAPI4.baseURL
                    +'export/json/recordTemplate.php?'
                    +'rectype_ids='+ rectype_ids
                    +'&db='+window.hWin.HAPI4.database);
            }else{
                window.hWin.HEURIST4.msg.showMsgFlash('Please select what type of template you want...', 2000);
                return;
            }

            window.hWin.HEURIST4.msg.showMsgFlash('Downloading File...', 3000);
            
            this.closeDialog();
    }
        
});

