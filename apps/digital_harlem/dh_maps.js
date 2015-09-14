/**
* Template to define new widget
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
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


$.widget( "heurist.dh_maps", {

    // default options
    options: {
        // callbacks
    },
    
    _resultset:null,

    // the widget's constructor
    _create: function() {

        var that = this;

        // prevent double click to select text
        //this.element.disableSelection();

        // Sets up element to apply the ui-state-focus class on focus.
        //this._focusable($element);   
        
        this.div_content = $( "<div>" )
        .css({'width':'100%','height':'100%','overflow-y':'auto','padding':'0.5em'})
        .addClass('thumbs2')
        .appendTo( this.element );

        this._refresh();

        //perform search for maps
        var that = this;
        
        $(this.document).on(top.HAPI4.Event.ON_SYSTEM_INITED, function(e, data) {
            
            var request = {q:'t:19', w: 'a', f: 'map', l:3000, source:that.element.attr('id') };

            //perform search
            top.HAPI4.RecordMgr.search(request, 
                    function(response) {
                        
                            if(response.status == top.HAPI4.ResponseStatus.OK){
                                that._resultset = new hRecordSet(response.data);
                            }else{
                                that._resultset = null;
                                top.HEURIST4.util.showMsgErr(response);
                            }
                            
                            that._refresh();
                    }
            );
                
                
        });
        
    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
    },

    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    _setOptions: function( ) {
        this._superApply( arguments );
    },

    /* 
    * private function 
    * show/hide buttons depends on current login status
    */
    _refresh: function(){
        
        this.div_content.empty();
        
        if(!this._resultset) return;
        
        var recs = this._resultset.getRecords();
        
        var recorder = this._resultset.getOrder();

        var html = '';
        var recID, idx;
        for(idx=0; idx<recorder.length; idx++) {
            recID = recorder[idx];
            if(recID){
                html  += this._renderRecord_html(this._resultset, recs[recID]);
            }
        }
        this.div_content[0].innerHTML += html;
        
        $allrecs = this.div_content.find('.recordDiv');
        this._on( $allrecs, {
            click: this._recordDivOnClick,
            mouseover: this._recordDivOnHover
        });

    },
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements
        $(this.document).off(top.HAPI4.Event.ON_SYSTEM_INITED);
        this.div_content.remove();
    },
    
    
    _renderRecord_html: function(recordset, record){

        function fld(fldname){
            return recordset.fld(record, fldname);
        }

        /*
        0 .'bkm_ID,'
        1 .'bkm_UGrpID,'
        2 .'rec_ID,'
        3 .'rec_URL,'
        4 .'rec_RecTypeID,'
        5 .'rec_Title,'
        6 .'rec_OwnerUGrpID,'
        7 .'rec_NonOwnerVisibility,'
        8. rec_ThumbnailURL

        9 .'rec_URLLastVerified,'
        10 .'rec_URLErrorMessage,'
        11 .'bkm_PwdReminder ';
        11  thumbnailURL - may not exist
        */

        var recID = fld('rec_ID');
        var rectypeID = fld('rec_RecTypeID');
        var bkm_ID = fld('bkm_ID');
        var recTitle = top.HEURIST4.util.htmlEscape(fld('rec_Title'));

        var html_thumb = '';
        if(fld('rec_ThumbnailURL')){
            html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+ fld('rec_ThumbnailURL') + '&quot;);opacity:1"></div>'
        }

        
        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" rectype="'+rectypeID+'" bkmk_id="'+bkm_ID+'">'
            + '<div class="recTypeThumb" style="background-image: url(&quot;'+ top.HAPI4.iconBaseURL + 'thumb/th_' + rectypeID + '.png&quot;);"></div>'
            + html_thumb
            + '<div title="'+recTitle+'" class="recordTitle">'
            +     (fld('rec_URL') ?("<a href='"+fld('rec_URL')+"' target='_blank'>"+ recTitle + "</a>") :recTitle)
            + '</div>'
            + '</div>';


        return html;
    },
    
    _recordDivOnHover: function(event){
        var $rdiv = $(event.target);
        if($rdiv.hasClass('rt-icon') && !$rdiv.attr('title')){

              $rdiv = $rdiv.parents('.recordDiv')
              var rectypeID = $rdiv.attr('rectype');
              var title = top.HEURIST4.rectypes.names[rectypeID] + ' [' + rectypeID + ']';
              $rdiv.attr('title', title);
        }
    },

    /**
    * loads the selected map document
    */
    _recordDivOnClick: function(event){
        
        var $target = $(event.target),
            $rdiv;

        if(!$target.hasClass('recordDiv')){
            $rdiv = $target.parents('.recordDiv');
        }else{
            $rdiv = $target;
        }
        
        var recId = $rdiv.attr('recid');
        
        //hack $('#map-doc-select').click();
        var app = appGetWidgetByName('app_timemap');  //appGetWidgetById('ha51'); 
        if(app && app.widget){
            //switch to Map Tab
            
            //load Map Document
            $(app.widget).app_timemap('loadMapDocumentById', recId);
        }
        
    }    

});
