<!--

/*
* Copyright (C) 2005-2016 University of Sydney
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
* selectFieldType.html
* select type for Heurist field
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

-->
$.getMultiScripts = function(arr, path) {
    var _arr = $.map(arr, function(scr) {
        return $.getScript( (path||"") + scr );
    });

    _arr.push($.Deferred(function( deferred ){
        $( deferred.resolve );
    }));

    return $.when.apply($, _arr);
}    

$(document).ready(function() {
	
	function getFirstFieldWithType(ft_type){
		
		var dt_idx = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex['dty_Type'];
		
        for (var ft_id in top.HEURIST.detailTypes.typedefs){
		       var dt = top.HEURIST.detailTypes.typedefs[ft_id];
		       if(dt.commonFields[dt_idx]==ft_type){
			   		return ft_id;				   
		       }
		}
		return null;
	}
/*
        for (value in top.HEURIST.detailTypes.lookups){
            if(!Hul.isnull(Number(value))) {
                if(!(value==="relationtype" || value==="year" || value==="boolean" || value==="integer")){
                    text = top.HEURIST.detailTypes.lookups[value];
                    Hul.addoption(el, value, text);
                }
            }
        } //for
*/	

    if(!top){
        return;
    }else if($.isFunction(top.HEURIST.is_admin)){
		  onScriptsReady();
	}else{
		
        dbname = top.HEURIST.getQueryVar("db");
    
		    $.getMultiScripts([
		          //'../../common/js/utilsLoad.js',
		          '../../../common/php/displayPreferences.php?db='+dbname,
		          '../../../common/php/getMagicNumbers.php?db='+dbname,
		          '../../../common/php/loadUserInfo.php?db='+dbname,
		          '../../../common/php/loadCommonInfo.php?db='+dbname,
		          //'../../../records/edit/editRecord.js'
		          //'../../common/php/loadHAPI.php?db='+dbname,
		    ])
		        .done(onScriptsReady)
		        .fail(function(error) {
		            console.log(error);         
		            //alert('Error on script laoding '+error);
		        }).always(function() {
		            // always called, both on success and error
		        }); 
		
	}

	
	function onScriptsReady(){
	
  	top.HEURIST.edit.allInputs = [];
	
	var container = document.getElementById("inpt0");
	var rfr = getFirstFieldWithType('enum');
	var input = top.HEURIST.edit.createInput(0, rfr, null, [], container); //enum 203

	container = document.getElementById("inpt1");
	input = top.HEURIST.edit.createInput(0, getFirstFieldWithType('float'), null, [], container); //numeric

	container = document.getElementById("inpt2");
	input = top.HEURIST.edit.createInput(0, getFirstFieldWithType('freetext'), null, [], container); //freetext

	container = document.getElementById("inpt3");
	input = top.HEURIST.edit.createInput(0, getFirstFieldWithType('blocktext'), null, [], container); //memo

	container = document.getElementById("inpt4");
	input = top.HEURIST.edit.createInput(0, getFirstFieldWithType('date'), null, [], container); 

	container = document.getElementById("inpt5");
	input = top.HEURIST.edit.createInput(0, getFirstFieldWithType('geo'), null, [], container);
	
	container = document.getElementById("inpt6");
	input = top.HEURIST.edit.createInput(0, getFirstFieldWithType('file'), null, [], container);
	
	container = document.getElementById("inpt7");
	input = top.HEURIST.edit.createInput(0, getFirstFieldWithType('separator'), null, [], container);
	
	container = document.getElementById("inpt8");
	input = top.HEURIST.edit.createInput(0, getFirstFieldWithType('resource'), null, [], container);
	
                                                                           
	container = document.getElementById("inpt9");
	//input = top.HEURIST.edit.createInput(1, getFirstFieldWithType('relmarker'), null, [], container);
/*	var record = {recID:-1,title:'',rectype:2,isTemporary:true}
    var relManager = new top.RelationManager(container, record,[], 0, null, false, true);
	$(relManager.addOtherTd).hide(); 
	$(relManager.addOtherTd).find('a').click();*/
	$(container).find('[name="savebutton"]').css('color','lightgray').prop('disabled','disabled');
    
	                                                                       
	//$('.input-cell > input').css('width','30ex');
	$('.in').css('width','30ex');
	$('.resource-title').css('width','150px');
	
	$('.input-cell > .prompt').hide();
	$('.input-header-cell').css({'min-width':'0','width':'0','font-size':'0.8em'});
    $('.input2-header-cell').css({'min-width':'0','font-size':'0.8em'});
    $('#relationship-type').css({'font-size':'0.8em'});
    $('.input-cell > .text').css({'font-size':'0.8em'});
    $('.input-cell > span').css({'font-size':'0.8em'});
	$('.separator > .input-header-cell').css('min-width','200px');
	
	$('#btnSelect').click(
	   function(){
	   	    var res = $('input[name="ft_type"]:checked').val();
	   		window.close(res);
	   }
	);
	
	
	}
	
});  