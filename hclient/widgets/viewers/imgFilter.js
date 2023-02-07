/*
* imgFilter.js - define image filters css
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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

//
//
//
function imgFilter( current_cfg, $dlg, main_callback ){

    var _className = 'imgFilter';
    var _default_values = {};
    
    function _init(){

        var buttons= [
            {text:window.hWin.HR('Cancel'), 
                id:'btnCancel',
                css:{'float':'right','margin-left':'10px','margin-right':'20px'}, 
                click: function() { 
                    $dlg.dialog( "close" );
            }},
            {text:window.hWin.HR('Reset'), 
                id:'btnReset',
                css:{'float':'right','margin-left':'10px'}, 
                click: function() { 
                    _resetValues();
            }},
            {text:window.hWin.HR('Apply'), 
                id:'btnDoAction',
                class:'ui-button-action',
                //disabled:'disabled',
                css:{'float':'right'}, 
                click: function() { 
                        var config = _getValues();
                        main_callback.call(this, config);
                        $dlg.dialog( "close" );    
        }}];

        if($dlg && $dlg.length>0){
            //container provided

            $container.empty().load(window.hWin.HAPI4.baseURL
                +'hclient/widgets/viewers/imgFilter.html',
                _initControls
            );

        }else{
            //open as popup

            $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
                +"hclient/widgets/viewers/imgFilter.html?t="+(new Date().getTime()), 
                buttons, 'Define Filters', 
                {   //container:'cms-add-widget-popup',
                    default_palette_class: 'ui-heurist-explore', //'ui-heurist-publish',
                    width: 300,
                    height: 450,
                    close: function(){
                        $dlg.dialog('destroy');       
                        $dlg.remove();
                    },
                    open: _initControls
            });

        }



    }
    
    //
    // Assign css values to UI
    //
    function _initControls(){
        _default_values = {};
        $.each($dlg.find('input'), function(idx, item){
            item = $(item);
            _default_values[item.attr('name')] = item.val();
            
            $(item).on({change:function(e){
                $(e.target).prev().text( $(e.target).val() );
            }});
            
            if(current_cfg && !window.hWin.HEURIST4.util.isempty(current_cfg[item.attr('name')]))
            {
                var val = parseInt(current_cfg[item.attr('name')]);
console.log(current_cfg[item.attr('name')]+' >>> '+val);                
                item.val( val ).change();    
            }
            
        });
    }
    
    //
    //
    //
    function _resetValues(){
        $.each($dlg.find('input'), function(idx, item){
            $(item).val(_default_values[$(item).attr('name')])
        });
    }
   
    //
    // get css
    //
    function _getValues(){
        
        var filter_cfg = {};
        var filter = '';
        $.each($dlg.find('input'), function(idx, item){
            item = $(item);
            
            var val = item.val();
            if(val!=_default_values[item.attr('name')]){
                var suffix = item.attr('data-suffix');
                if(!suffix) suffix = '';
                
                filter_cfg[item.attr('name')] = val+suffix;
                filter = filter + item.attr('name')+'('+val+suffix+') ';
            }
        });
        //var filter_css = {filter:'', '-webkit-filter':'', '-moz-filter': ''};

        return filter_cfg; //{filte:filter, cfg:filter_cfg};
    }//_getValues



    //public members
    var that = {

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            return (strClass === _className);
        },
    }

    _init();
    
    return that;
}



