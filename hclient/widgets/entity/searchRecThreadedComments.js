/**
* Search header for recThreadedComments manager
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

$.widget( "heurist.searchRecThreadedComments", $.heurist.searchEntity, {

    //
    _initControls: function() {
        this._super();
        
        this.btn_search_start.css('float','right');   
        
        this.input_sort_rectitle = this.element.find('#input_sort_rectitle');
        this.input_sort_sdate = this.element.find('#input_sort_sdate');
        this._on(this.input_sort_rectitle,  { change:this.startSearch });
        this._on(this.input_sort_sdate,  { change:this.startSearch });
       
        
        this.startSearch();            
    },  
    
    //
    // public methods
    //
    startSearch: function(){
        
            let request = {}
        
            request['cmt_Text'] = this.input_search.val();    
            
            if(this.input_sort_rectitle.is(':checked')){
                request['sort:cmt_RecTitle'] = '1';
            }else
            if(this.input_sort_sdate.is(':checked')){
                request['sort:cmt_Modified'] = '-1' 
            }

            if($.isEmptyObject(request)){
                this._trigger( "onresult", null, {recordset:new HRecordSet()} );
            }else{
                request['details']   = 'list';
                this._search_request = request;
                this._super();
            }  
                     
    },

});
