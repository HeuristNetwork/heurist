/**
* Select element to be inserted into CMS page - opens popup
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
//
//
//
function editCMS_SelectElement( callback ){

    var $dlg;

    var selected_element = null, selected_name='';

    var t_components = {
        
        text:{name:'Simple Text', description:'Simple text wiht header'},
        
    grp1:{name:'Content', description:'Content layouts or templates', is_header:true},
        
        text_media:{name:'Text with media', description:'media and text '},
        text_2:{name:'Text in 2 columns', description:'2 columns layout'},
        group_2:{name:'Groups as 2 columns', description:'2 columns layout'},
        text_3:{name:'Text in 3 columns', description:'3 columns layout'},
        text_banner:{name:'Text with banner', description:'Text over background image'},
        tpl_discover: {name:'Discover (filters/results/map)', description:'3 columns layout'},
        tpl_blog: {name:'Blog', description:''},

    grp2:{name:'Widgets', description:'Heurist Widgets for dynamic content or interaction', is_header:true},
        
        heurist_Search:{name:'Filter', description:'Search field (with standard filter builder)'},
        heurist_SearchTree:{name:'Saved filters', description:'Simple &amp; facet filters, selection or tree'},            

        heurist_resultList:{name:'Standard filter result', description:'Switchable modes, action controls'},            
        heurist_resultListExt:{name:'Custom report', description:'Also use for single record view'},            
        heurist_resultListDataTable:{name:'Table format', description:'Result list as data table'},            

        heurist_Map:{name:'Map and timeline', description:'Map and timeline widgets'},            
        heurist_Graph:{name:'Network graph', description:'Visualization for records links and relationships'},            
        
        heurist_Navigation:{name:'Menu', description:'Navigation Menu'},            
        heurist_recordAddButton:{name:'Add Record', description:'Button to addition of new Heurist record'},
        
    grp3:{name:'Containers', description:'to be described....', is_header:true},

        group:{name:'Group', description:'Container for elements'},
        accordion:{name:'Accordion', description:'Set of collapsable groups'},            
        tabs:{name:'Tabs', description:'Tab/Page control. Each page may have group of elements'},            
        cardinal:{name:'Cardinal', description:'Container for five groups or elements placed orthogonally (N-S-E-W,-Center panels)'},                                                    
        
    };


    var buttons= [
        {text:window.hWin.HR('Cancel'), 
            id:'btnCancel',
            css:{'float':'right','margin-left':'30px','margin-right':'20px'}, 
            click: function() { 
                $dlg.dialog( "close" );
        }},
        {text:window.hWin.HR('Insert'), 
            id:'btnDoAction',
            class:'ui-button-action',
            disabled:'disabled',
            css:{'float':'right'}, 
            click: function() { 
                if(selected_element){
                    callback.call(this, selected_element, selected_name);
                    $dlg.dialog( "close" );    
                }
    }}];

    $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
        +"hclient/widgets/cms/editCMS_SelectElement.html?t="+(new Date().getTime()), 
        buttons, 'Insert component into web page', 
        {  container:'cms-add-widget-popup',
            default_palette_class: 'ui-heurist-publish',
            width: 600,
            height: 600,
            close: function(){
                $dlg.dialog('destroy');       
                $dlg.remove();
            },
            open: function(){
                is_edit_widget_open = true;

                //load list of groups and elements and init selector
                var sel = $dlg.find('#components');
                $.each(t_components, function(key, item){
                    if(item.is_header){
                            var grp = document.createElement("optgroup");
                            grp.label =  item.name;
                            sel[0].appendChild(grp);
                    }else{
                        window.hWin.HEURIST4.ui.addoption(sel[0], key, item.name);    
                    }
                    
                    
                });

                sel.mouseover(function(e){
                    window.hWin.HEURIST4.util.setDisabled( $dlg.parents('.ui-dialog').find('#btnDoAction'), false );
                    var t_name = $(e.target).val();
                    //selected_element  = t_name;
                    var desc = '';
                    if(t_components[t_name]){
                        desc = t_components[t_name];    
                    }
                    if(desc){
                        desc = desc.description
                    }
                    $dlg.find('.template_description').html(desc);    

                });
                sel.change(function(e){
                    window.hWin.HEURIST4.util.setDisabled( $dlg.parents('.ui-dialog').find('#btnDoAction'), false );
                    var sel = e.target;
                    var t_name = $(sel).val();
                    selected_element  = t_name;
                    selected_name = sel.options[sel.selectedIndex].text;
                });

                sel.val('text').change();
                selected_element = 'text';

            }
    });

}



