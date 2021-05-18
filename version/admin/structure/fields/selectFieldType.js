/*
* Copyright (C) 2005-2020 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* selectFieldType.js
* select type for Heurist field
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
$(document).ready(function() {
	
        window.hWin.HEURIST4.ui.initHelper( {button:$('#hint_more_info1'), 
                        title:'Field data type: Record pointer', 
                        utl:window.hWin.HAPI4.baseURL+'context_help/field_data_types.html #resource',
                        position:{ my: "left top", at: "left top", of:$(window.frameElement)}, no_init:true} ); 
        window.hWin.HEURIST4.ui.initHelper( {button:$('#hint_more_info2'), 
                        title:'Field data type: Relationship marker', 
                        url:window.hWin.HAPI4.baseURL+'context_help/field_data_types.html #relmarker',
                        position:{ my: "left top", at: "left top", of:$(window.frameElement)}, no_init:true} ); 

	
/*    
	$('.input-cell > .prompt').hide(); //hide help text
	$('.input-header-cell').css({'width':'0','min-width':'15ex','font-size':'0.8em'});
    $('.input2-header-cell').css({'min-width':'0','font-size':'0.8em'});
    $('#relationship-type').css({'font-size':'0.8em'});
    $('.input-cell > span').css({'font-size':'0.8em'});
	$('.separator > .input-header-cell').css('min-width','200px');
    $('.input-row select').css({'max-width':'100px'});
    $('.input-row > .input-header-cell').text('Example: ');
    
    //reduce width of input elements	
    var csobj = {'font-size':'0.8em','width':'25ex'};
    $('.in').css(csobj);
    $('.input-cell > .text').css(csobj);
    $('.input-cell > textarea').css(csobj).css({'min-width': '20ex'});
    $('.file-resource-div > .resource-title').css(csobj);
    $('.resource-div > .resource-title').css({'font-size':'0.8em','width':'20ex'});
    $('.temporal-div img').css({'vertical-align':'baseline'});
    
    $('.separator > .input-header-cell').css({'text-align':'right','min-width':'15ex'});
*/
    
        $('input[name="ft_type"]').change(function(){
            $('#btnSelect').removeProp('disabled');
            $('#btnSelect').css('color','black');
        });
        
        
	    $('#btnSelect').button().click(
	       function(){
	   	        var res = $('input[name="ft_type"]:checked').val();
	   		    window.close(res);
	       }
	    );
	
});  