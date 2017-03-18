/**
* result list for BORO
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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


$.widget( "heurist.boro_results", $.heurist.resultList, {
 
  _initControls:function(){
        this.options.searchfull = this._searchFullRecords; 
        
        //this.options.renderer = _renderRecord_html;
        
        this._super();

        
        this.div_content.css({'padding':'30px','top':'5em'});//.addClass('bor-search-results');
        
  },
    
  _searchFullRecords: function(rec_toload, current_page, callback){
      
    var DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'], //1
        DT_GIVEN_NAMES = window.hWin.HAPI4.sysinfo['dbconst']['DT_GIVEN_NAMES'],
        DT_EXTENDED_DESCRIPTION = 134;//window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION']; //4      
      
                var ids = rec_toload.join(',');
                var request = { q: 'ids:'+ ids,
                    w: 'a',
                    detail: 'detail', //[DT_NAME, DT_GIVEN_NAMES, DT_EXTENDED_DESCRIPTION], 
                    id: current_page,
                    source:this.element.attr('id') };
               var that = this;
                    
                window.hWin.HAPI4.RecordMgr.search(request, function(responce){
                    that._onGetFullRecordData(responce, rec_toload);   
                });
      
      
  },  
    
 //<h3 class="bor-section-title">Results</h3>   
 _renderRecord_html: function(recordset, record){
  
        function fld(fldname){
            return recordset.fld(record, fldname);
        }

        var DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'], //1
            DT_GIVEN_NAMES = window.hWin.HAPI4.sysinfo['dbconst']['DT_GIVEN_NAMES'],
            DT_EXTENDED_DESCRIPTION = 134;//window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION']; //4      
   
        var fullName = fld(DT_GIVEN_NAMES)+' '+fld(DT_NAME);
   
        //get thumbnail if available for this record, or generic thumbnail for record type
        var html_thumb = '';
        if(fld('rec_ThumbnailURL')){
            html_thumb = 
                '<a href="#" class="bor-stop-image ab-dark" data-ab-yaq="19" style="background-color: rgb(19, 19, 19);">'
                +'<img src="'+fld('rec_ThumbnailURL')+'" alt="Photograph of '+fullName
                +'" data-adaptive-background="1" data-ab-color="rgb(19,19,19)"></a>';
            //'<div class="recTypeThumb realThumb" style="background-image: url(&quot;'+ fld('rec_ThumbnailURL') + '&quot;);opacity:1"></div>';
        }else{
            html_thumb = '<a href="#" class="bor-stop-image bor-stop-image-placeholder"></a>';
        }
   
        var html = 
                '<div class="bor-search-result bor-stop bor-stop-large">'
                + html_thumb
                + '<div class="bor-stop-description">'
                +     '<h3><a href="#">'+fullName+'</a></h3>'
                +     '<p class="bor-search-result-text">'+fld(DT_EXTENDED_DESCRIPTION)+'</p>'
                + '</div>'
                +'</div>';

        return html;
 }
 
    
});