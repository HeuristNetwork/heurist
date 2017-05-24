/**
* result list for connection to Place
* via options('place', ID) it searches for linkedfrom events 37,24,29,33 and then linkto Persons
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

$.widget( "heurist.boro_place", $.heurist.resultList, {
    
  _initControls:function(){
        this.options.searchfull = this._searchFullRecords; 
        this.options.navigator = 'buttons';
        
        this.options.isapplication = false;
        this.options.eventbased = false;
        
        //this.options.renderer = _renderRecord_html;
        
        this._super();

        
        this.div_content.css({'padding':'30px','top':'8em'});//.addClass('bor-search-results');
        
        //this.span_info.appendTo(this.div_header);

        //this.span_info = $('<div>').css('color','red').after(this.div_header);
        
        var $header = $(".header"+this.element.attr('id'));
        this.span_info.addClass('help-block','lead').css({'color':'red','float':'left'}).insertAfter($header);
        
        //add bottom panel and place navigator on it
        this.div_content.removeClass('ent_content_full').addClass('ent_content');
        
        this.div_content_whole = this.div_content;
        
        this.div_content = $('<ul>').addClass('bor-stories').appendTo(this.div_content);
        
        this.div_bottom = $( "<div>" ).addClass('ent_footer').css('text-align','center')
                .appendTo( this.element );
        

        this.span_pagination.css('float','').addClass('pagination').appendTo(this.div_bottom);
  },
  
   //
   // function for searchfull option - return full record info
   //
  _searchFullRecords: function(rec_toload, current_page, callback){
      this._renderPage( this.current_page );
      return;
  },  

  _renderPage: function(pageno, recordset, is_retained_selection){

        this._super( pageno, recordset, is_retained_selection );

        this.div_content.css('overflow','hidden');
        var newHeight = $(this.div_content)[0].scrollHeight + 100;
        if (newHeight<500) newHeight = 500;
               
        $('.bor-page-search').height( newHeight );
  },    
    
 //<h3 class="bor-section-title">Results</h3>   
 _renderRecord_html: function(recordset, record){
  
     //<ul class="bor-stories">
     
        function fld(fldname){
            return recordset.fld(record, fldname);
        }

        var DT_EXTENDED_DESCRIPTION = 134,//window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION']; //4      
            DT_EVENT_DESC = 999999;
            
        var fullName = composePersonName(recordset, record);
   
        var html_thumb = '';
        if(fld('rec_ThumbnailURL')){
            html_thumb = 
                '<a href="#" class="bor-stop-image ab-dark" data-ab-yaq="19" style="background-color: rgb(43, 43, 43);">'
                +'<img src="'+fld('rec_ThumbnailURL')+'" height="36" alt="Photograph of '+fullName
                +'" data-adaptive-background="1" data-ab-color="rgb(19,19,19)"></a>';
            //'<div class="recTypeThumb realThumb" style="background-image: url(&quot;'+ fld('rec_ThumbnailURL') + '&quot;);opacity:1"></div>';
        }else{
            html_thumb = '<a href="#" class="bor-stop-image bor-stop-image-placeholder"></a>';
        }
         
         
        var history = recordset.values(record, DT_EVENT_DESC);        

        var html = 
         '<li class="bor-stop">'+html_thumb
            +'<div class="bor-stop-description">'
                //event: {$event.recTitle}<br>
              +'<a href="'+window.hWin.HAPI4.baseURL+'profile/'+fld('rec_ID')+'/a" onclick="{window.hWin.boroResolver(event);}">'
              + fullName + '</a> '
                     + history.join(' and ')+' here'
         +'</div></li>';
                    

        return html;
 },
 
 //
 // main method that searches for Place connections
 //
 findConnections: function(placeID){
   
        this.span_pagination.hide();
        this._clearAllRecordDivs('');
        this.loadanimation(true);
        this._renderProgress();
        this._renderSearchInfoMsg(null);
     
        var request = {
            w: 'a',
            q:'ids:'+placeID,
            rules:[{"query":"t:37,24,29,33,26 linked_to:25 ",
                        "levels":[{"query":"t:10 linkedfrom:37,24,29,33"},
                                  {"query":"t:31 linked_to:26-97","levels":[{"query":"t:10 linkedfrom:31-16"}]}] }],
            
            detail: 'detail', //[DT_NAME, DT_GIVEN_NAMES, DT_EXTENDED_DESCRIPTION], 
            //id: current_page,
            source:this.element.attr('id') };
       var that = this;
       window.hWin.HAPI4.RecordMgr.search(request, function(response){
           
            that.loadanimation(false);
           
            if(response.status != window.hWin.HAPI4.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            var res_records = {}, res_orders = [];
            var recordset = new hRecordSet(response.data);
            
            var RT_PERSON = 10;
            var RT_GEOPLACE = 25;
            var RT_INSTITUTION = 26;
            var RT_SCHOOLING = 31;
            
            var DT_PARENT_PERSON = 16;
            var DT_EVENT_TYPE = 77;
            var DT_INSTITUTION = 97;
            var DT_OCCUPATION = 150;
            var DT_EVENT_DESC = 999999;
            var DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'];
            
            //prepare result 
            var idx, records = recordset.getRecords();
            for(idx in records){
            
                var record = records[idx];
                var recID = recordset.fld(record, 'rec_ID');
                var recTypeID = Number(recordset.fld(record, 'rec_RecTypeID'));
                
                //loop for events
                if(recTypeID == RT_PERSON){
                    res_records[recID] = record;
                    res_orders.push(recID);
                
                }else if(recTypeID!=RT_GEOPLACE && recTypeID!=RT_INSTITUTION){ //not place neither institution
                    
                    var sDesc = '';
                    
                    var personID = recordset.fld(record, DT_PARENT_PERSON);
                    //find target person
                    var person_record = recordset.getById(personID);
                    
                    var values = recordset.values(person_record, DT_EVENT_DESC);
                    if(window.hWin.HEURIST4.util.isnull(values)) values = [];
                    
                    if(recTypeID==RT_SCHOOLING){ //schooling
                        
                        var institutionID = recordset.fld(record, DT_INSTITUTION);
                        var institution_record = recordset.getById(institutionID);
                        sDesc = 'attended '+recordset.fld(institution_record, DT_NAME);
                        
                    }else{
                        if(recTypeID==24){ //eventlet
                        
                            sDesc = window.hWin.HEURIST4.ui.getTermValue('enum', recordset.fld(record, DT_EVENT_TYPE));
                            
                        }else if(recTypeID==29){ //military service

                            sDesc = window.hWin.HEURIST4.ui.getTermValue('enum', recordset.fld(record, DT_EVENT_TYPE));
                        
                        }else if(recTypeID==33){ //death

                            sDesc = window.hWin.HEURIST4.ui.getTermValue('enum', recordset.fld(record, DT_EVENT_TYPE));
                            
                            
                        }else if(recTypeID==37){ //occupation

                            sDesc = window.hWin.HEURIST4.ui.getTermValue('enum', recordset.fld(record, DT_OCCUPATION));
                            
                        }
                        
                        if(sDesc=='Death') sDesc = 'died'
                        else if(sDesc=='Birth') sDesc = 'was born'
                        else{
                            sDesc = 'was ' + sDesc;//sDesc[0].toLowerCase() + sDesc.substring(1);
                        }
                    }
                    
                    values.push(sDesc);
                    //add field - event description
                    recordset.setFld(person_record, DT_EVENT_DESC, values);
                }
            
            }
            
            var res_recordset = new hRecordSet({
                count: res_orders.length,
                offset:0,
                fields: recordset.getFields(),
                rectypes: [RT_PERSON],
                records: res_records,
                order: res_orders,
                mapenabled: true
            });            
            that.updateResultSet(res_recordset);
       });     
     
 },
 
 _setOption: function( key, value ) {
    this._super( key, value );
    if(key=='placeID' && value>0){
        this.findConnections(value);
    }
  },
    
});