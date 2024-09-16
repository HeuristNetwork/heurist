/**
* manageServer.js - list of server manager actions
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

$.widget( "heurist.manageServer", $.heurist.baseAction, {

    // default options
    options: {
        height: 620,
        width:  400,
        title:  'Server manager',
        default_palette_class: 'ui-heurist-admin',
        actionName: 'manageServer'
    },
    
    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){
        
            this._$('li').css({padding:'10px 0px'});
            
            $.each(this._$('a'), function(i,item){
                
                let href = $(item).attr('href');
                
                if(!(href.indexOf('http://')==0 || href.indexOf('https://')==0)){
                    href = window.hWin.HAPI4.baseURL + href;
                }
                $(item).attr('href', href);
            });

            this._on(this._$('a'),{click:function(event){
                    let surl = $(event.target).attr('href');
                    
                    let subform = this._$('#mainForm');
                    
                    if(this.options.entered_password){
                        subform.find('input[name="pwd"]').val(this.options.entered_password);   
                    }
                    subform.find('input[name="db"]').val(window.hWin.HAPI4.database);
                    subform.attr('action',surl);
                    subform.submit();

                    window.hWin.HEURIST4.util.stopEvent(event);   
                    return false;
                }});
        
        return this._super();
    },


    //    
    //
    //
    _getActionButtons: function(){
        let res = this._super();
        res[0].text = 'Close';
        res.splice(1,1);
        return res;
    }

        
});

