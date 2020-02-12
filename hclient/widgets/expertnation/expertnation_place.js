/**
* Aggregation result list for Connected People to Place or particular Attribute
* 
* see function fillConnectedPeople in bor_nav
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
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

$.widget( "heurist.expertnation_place", $.heurist.resultList, {
    
  _initControls:function(){
        this.options.searchfull = this._searchFullRecords;  //just re-render page
        this.options.navigator = 'buttons';
        
        this.options.eventbased = false;
        
        //this.options.renderer = _renderRecord_html;
        
        this._super();

        
        //var $header = $(".header"+this.element.attr('id'));
        //this.span_info.addClass('help-block','lead').css({'color':'red','float':'left'}).insertAfter($header);
        
        //add bottom panel and place navigator on it
        this.div_content.removeClass('ent_content_full').addClass('ent_content');
        
        this.div_content_whole = this.div_content;
        
        this.div_content = $('<ul>').addClass('bor-stories').appendTo(this.div_content);
        
        this.div_bottom = $( "<div>" ).addClass('ent_footer').css('text-align','center')
                .appendTo( this.element );
        

        this.span_pagination.css('float','').addClass('pagination').appendTo(this.div_bottom);
  },
  
  _refresh: function(){
      this._super();
      this.div_content.css({'padding':'30px','top':'0em'});//.addClass('bor-search-results');
  },
  
   //
   // function for searchfull option - return full record info
   //
  _searchFullRecords: function(rec_toload, current_page, callback){
      this._renderPage( this.current_page );
      return;
  },  

  //
  //
  //
  _renderPage: function(pageno, recordset, is_retained_selection){

        this._super( pageno, recordset, is_retained_selection );

        this.element.parent().removeClass('ui-widget-content');
        
        this.div_content.css('overflow','hidden');
        var newHeight = $(this.div_content)[0].scrollHeight + 100;
        if (newHeight<500) newHeight = 500;
               
        $('.bor-page-search').height( newHeight );
  },    
    
 _renderRecord_html: function(recordset, record){
        var DT_EVENT_DESC = 999999;
         
        var history = recordset.fld(record, DT_EVENT_DESC);        

        return history;
 }
 
});