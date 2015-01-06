/**
* Query result listing. 
* 
* NO Requires apps/rec_actions.js (must be preloaded)
* Requires apps/search/resultListMenu.js (must be preloaded)
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


$.widget( "heurist.resultList", {

    // default options
    options: {
        view_mode: 'list', // list|icons|thumbnails   @toimplement detail, condenced
        multiselect: true,
        isapplication: true,
        showcounter: true,
        showmenu: true
        //searchsource: null,
    },
    

    _query_request: null, //keep current query request
    
    _events: null,
    _lastSelectedIndex: -1, //required for shift-click selection
    _count_of_divs: 0,

    
    

    // the constructor
    _create: function() {

        var that = this;

        //this.div_actions = $('<div>').css({'width':'100%', 'height':'2.8em'}).appendTo(this.element);
        
        var hasHeader = ($(".header"+that.element.attr('id')).length>0);

        this.div_toolbar = $( "<div>" ).css({'width': '100%', 'height':'2.2em'}).appendTo( this.element );
        this.div_content = $( "<div>" )
        .css({'left':0,'right':'15px','overflow-y':'auto','padding':'0.5em',
            'position':'absolute',
            'top':hasHeader?'4.4em':'2.4em','bottom':'15px'})   //@todo - proper relative layout
        //.position({my: "left top", at: "left bottom", of: this.div_toolbar })
        .appendTo( this.element );

        /*
        this.action_buttons = $('<div>')
        .css('display','inline-block')
        .rec_actions({actionbuttons: this.options.actionbuttons})
        .appendTo(this.div_toolbar);
        */

        //-----------------------
        // layout - one button mode - OLD WAY
        /*
        this.btn_view = $( "<button>", {text: "view"} )
        .css({'float':'right', 'font-size': '0.8em', 'width': '10em'})
        .appendTo( this.div_toolbar )
        .button({icons: {
            secondary: "ui-icon-triangle-1-s"
            },text:true});

        this.menu_view = $('<ul>'+
            '<li id="menu-view-list"><a href="#">'+top.HR('list')+'</a></li>'+
            //'<li id="menu-view-detail"><a href="#">Details</a></li>'+
            '<li id="menu-view-icons"><a href="#">'+top.HR('icons')+'</a></li>'+
            '<li id="menu-view-thumbs"><a href="#">'+top.HR('thumbs')+'</a></li>'+
            '</ul>')
        .addClass('menu-or-popup')
        .css('position','absolute')
        .appendTo( this.document.find('body') )
        .menu({
            select: function( event, ui ) {
                var mode = ui.item.attr('id');
                mode = mode.substr(10);
                that._applyViewMode(mode);
        }})
        .hide();

        var view_mode = top.HAPI4.get_prefs('rec_list_viewmode');        
        if(view_mode){
            this._applyViewMode(view_mode);
        }

        this._on( this.btn_view, {
            click: function(e) {
                $('.menu-or-popup').hide(); //hide other
                var menu_view = $( this.menu_view )
                .show()
                .position({my: "right top", at: "right bottom", of: this.btn_view });
                $( document ).one( "click", function() {  menu_view.hide(); });
                return false;
            }
        });*/
        
        
        this.mode_selector = $( "<div>" )
        .css({'float':'right'})
        .html('<input type="radio" id="list_layout_list" name="list_lo" checked="checked" value="list"/>'
            +'<label for="list_layout_list">'+top.HR('list')+'</label>'
            +'<input type="radio" id="list_layout_icons" name="list_lo" value="icons"/>'
            +'<label for="list_layout_icons">'+top.HR('icons')+'</label>'
            +'<input type="radio" id="list_layout_thumbs" name="list_lo" value="thumbs"/>'
            +'<label for="list_layout_thumbs">'+top.HR('thumbs')+'</label>')
        .buttonset()
        .click(function( event ) {
            var view_mode = $("input[name='list_lo']:checked").val(); //event.target.value;
            that._applyViewMode(view_mode);
            //that._refresh();
        })
        .appendTo( this.div_toolbar );
        
        $('#list_layout_list').button({icons: {primary: "icon-list"}, text:false});
        $('#list_layout_icons').button({icons: {primary: "icon-th"}, text:false});
        $('#list_layout_thumbs').button({icons: {primary: "icon-th-large"}, text:false});

        

        //----------------------
        
        var view_mode = top.HAPI4.get_prefs('rec_list_viewmode');        
        if(view_mode){
            this._applyViewMode(view_mode);
        }
        
        //----------------------
        
        this.span_info = $("<label>").appendTo(
            $( "<div>").css({'float':'right','min-width':'10em','padding':'3px 2em 0 0px'}).appendTo( this.div_toolbar ));

        //-----------------------
        
        
        if(this.options.showmenu){
            this.div_actions = $('<div>')
                //.css({'position':'absolute','top':3,'left':2})
                .resultListMenu()
                .appendTo(this.div_toolbar);
        }
        
        
        //-----------------------     listener of global events
        this._events = top.HAPI4.Event.LOGIN+' '+top.HAPI4.Event.LOGOUT;
        if(this.options.isapplication){
            this._events = this._events + ' ' + top.HAPI4.Event.ON_REC_SEARCHRESULT
                        + ' ' + top.HAPI4.Event.ON_REC_SEARCHSTART 
                        + ' ' + top.HAPI4.Event.ON_REC_SELECT 
                        + ' ' + top.HAPI4.Event.ON_REC_SEARCH_FINISH;
        }

        $(this.document).on(this._events, function(e, data) {

            if(e.type == top.HAPI4.Event.LOGIN){

                that._refresh();

            }else  if(e.type == top.HAPI4.Event.LOGOUT)
            {
                that._clearAllRecordDivs();

            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCHRESULT){ //get new chunk of data from server

                that.loadanimation(false);
                
                if(that._query_request!=null &&  data.queryid()==that._query_request.id) {  
                    that._renderRecordsIncrementally(data); //hRecordSet
                }
                
                //@todo show total number of records ???
                

            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCHSTART){

                that.span_info.hide();
                
                if(data){
                    
                    if(that._query_request==null || data.id!=that._query_request.id) {  //data.source!=that.element.attr('id') || 
                        //new search from outside
                        var $header = $(".header"+that.element.attr('id'));
                        if($header.length>0){
                            var new_title = top.HR(data.qname || 'Search result');
                            $header.html(new_title);
                            $('a[href="#'+that.element.attr('id')+'"]').html(new_title);
                        }

                        that._clearAllRecordDivs();
                        that.loadanimation(true);
                        
                        that._renderProgress();
                        
                    }
                    that._query_request = data;  //keep current query request 
                    
                }else{ //fake restart
                        that._clearAllRecordDivs();
                        that.loadanimation(true);
                }
                
            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCH_FINISH){
                
                   that.span_info.show();
                
            }else if(e.type == top.HAPI4.Event.ON_REC_SELECT){
                
                //this selection is triggered by some other app - we have to redraw selection
                if(data && data.source!=that.element.attr('id')) { 
                      that.setSelected(data.selection);
                }
            }
            //that._refresh();
        });
        /*
        if(this.options.isapplication){
        $(this.document).on(top.HAPI4.Event.ON_REC_SEARCHRESULT, function(e, data) {
        that.option("recordset", data); //hRecordSet
        that._refresh();
        });
        }
        */

        

        
        
        this._refresh();

    }, //end _create

    _setOptions: function() {
        // _super and _superApply handle keeping the right this-context
        this._superApply( arguments );
        this._refresh();
    },
    
    /*_setOption: function( key, value ) {
        this._super( key, value );
    },*/
    

    /* private function */
    _refresh: function(){

        // repaint current record set
        //??? this._renderRecords();  //@todo add check that recordset really changed  !!!!!
        this._applyViewMode();

        if(top.HAPI4.currentUser.ugr_ID>0){
            $(this.div_toolbar).find('.logged-in-only').css('visibility','visible');
            $(this.div_content).find('.logged-in-only').css('visibility','visible');
        }else{
            $(this.div_toolbar).find('.logged-in-only').css('visibility','hidden');
            $(this.div_content).find('.logged-in-only').css('visibility','hidden');
        }

        /*
        var abtns = (this.options.actionbuttons?this.options.actionbuttons:"tags,share,more,sort,view").split(',');
        var that = this;
        $.each(this._allbuttons, function(index, value){

            var btn = that['btn_'+value];
            if(btn){
                btn.css('display',($.inArray(value, abtns)<0?'none':'inline-block'));
            }
        });
        */


    },

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {

        $(this.document).off(this._events);

        var that = this;
        /*$.each(this._allbuttons, function(index, value){
            var btn = that['btn_'+value];
            if(btn) btn.remove();
        });*/

        // remove generated elements
        if(this.div_actions) this.div_actions.remove();
        this.div_toolbar.remove();
        this.div_content.remove();


        this.menu_tags.remove();
        this.menu_share.remove();
        this.menu_more.remove();
        this.menu_view.remove();

    },


    _initTagMenu: function() {

        this.menu_tags = $('<div>')
        .addClass('menu-or-popup')
        .css('position','absolute')
        .appendTo( this.document.find('body') )
        .tag_manager()
        .hide();

        this.btn_tags.click();
    },

    _applyViewMode: function(newmode){

        //var $allrecs = this.div_content.find('.recordDiv');
        if(newmode){
            var oldmode = this.options.view_mode;
            this.options.view_mode = newmode;
            //this.option("view_mode", newmode);
            this.div_content.removeClass(oldmode);

            //save viewmode is session
            top.HAPI4.SystemMgr.save_prefs({'rec_list_viewmode': newmode});

        }else{
            newmode = this.options.view_mode;
        }
        this.div_content.addClass(newmode);

        //this.btn_view.button( "option", "label", top.HR(newmode));
        $('#list_layout_'+newmode).attr('checked','true');

    },

    // @todo move record related stuff to HAPI
    // NOT USED 
    /*_renderRecords: function(){

        if(this.div_content){
            var $allrecs = this.div_content.find('.recordDiv');
            this._off( $allrecs, "click");
            this.div_content[0].innerHTML = '';//.empty();  //clear
        }

        if(this.options.recordset){
            this.loadanimation(false);

            var recs = this.options.recordset.getRecords();

            if( this.options.recordset.count_total() > 0 )
            {
                //for(i=0; i<recs.length; i++){
                //$.each(this.options.records.records, this._renderRecord)
                var recID;
                for(recID in recs) {
                    if(recID){
                        this._renderRecord(recs[recID]);
                    }
                }

                $allrecs = this.div_content.find('.recordDiv');
                this._on( $allrecs, {
                    click: this._recordDivOnClick
                });

            }else{

                var $emptyres = $('<div>')
                .html(top.HR('No records match the search')+
                    '<div class="prompt">'+top.HR((top.HAPI4.currentUser.ugr_ID>0)
                        ?'Note: some records are only visible to members of particular workgroups'
                        :'To see workgoup-owned and non-public records you may need to log in')+'</div>'
                )
                .appendTo(this.div_content);                   

                if(top.HAPI4.currentUser.ugr_ID>0 && this._query_request){ //logged in and current search was by bookmarks
                    var domain = this._query_request.w
                    if((domain=='b' || domain=='bookmark')){
                        var $al = $('<a href="#">')
                        .text(top.HR('Click here to search the whole database'))
                        .appendTo($emptyres);
                        this._on(  $al, {
                            click: this._doSearch4
                        });

                    }
                }
            }
        }

    },*/

    _clearAllRecordDivs: function(){

        //this.options.recordset = null;
        
        if(this.div_content){
            var $allrecs = this.div_content.find('.recordDiv');
            this._off( $allrecs, "click");
            this.div_content[0].innerHTML = '';//.empty();  //clear
        }
        
        this._count_of_divs = 0;
    },
    
    
    /**
    * Add new divs and join recordset
    * 
    * @param recordset
    */
    _renderRecordsIncrementally: function( recordset ){

        if(recordset)
        {
            //this.loadanimation(false);
            /*if(this.options.recordset==null){
                this.options.recordset = recordset;
            }else{
                //unite record sets 
                this.options.recordset = this.options.recordset.doUnite(recordset);
            }*/   
            
            var total_count_of_curr_request = (recordset!=null)?recordset.count_total():0;
            
            this._renderProgress();

            if( total_count_of_curr_request > 0 )
            {

                if(this._count_of_divs<10001){
                
                    var recs = recordset.getRecords();

                    var html = '';
                    var recID;
                    for(recID in recs) {
                        if(recID){
                            //var recdiv = this._renderRecord(recs[recID]);
                            html  += this._renderRecord_html(recordset, recs[recID]);
                            this._count_of_divs++;
                            /*this._on( recdiv, {
                                click: this._recordDivOnClick
                            });*/
                        }
                    }
                    this.div_content[0].innerHTML += html;   
                    this.span_info.html(this._count_of_divs); //that.div_content.find('.recordDiv').length);

                    /*var lastdiv = this.div_content.last( ".recordDiv" ).last();
                    this._on( lastdiv.nextAll(), {
                                click: this._recordDivOnClick
                            });*/
                }
                $allrecs = this.div_content.find('.recordDiv');
                this._on( $allrecs, {
                    click: this._recordDivOnClick
                });

            }else if(this._count_of_divs<1) {

                var $emptyres = $('<div>')
                .html(top.HR('No records match the search')+
                    '<div class="prompt">'+top.HR((top.HAPI4.currentUser.ugr_ID>0)
                        ?'Note: some records are only visible to members of particular workgroups'
                        :'To see workgoup-owned and non-public records you may need to log in')+'</div>'
                )
                .appendTo(this.div_content);                   

                if(top.HAPI4.currentUser.ugr_ID>0 && this._query_request){ //logged in and current search was by bookmarks
                    var domain = this._query_request.w
                    if((domain=='b' || domain=='bookmark')){
                        var $al = $('<a href="#">')
                        .text(top.HR('Click here to search the whole database'))
                        .appendTo($emptyres);
                        this._on(  $al, {
                            click: this._doSearch4
                        });

                    }
                }
            }
        }

    },
    
    
    /**
    * create div for given record  
    * 
    * NOT USED  - jquery works too slow if there are thousands of divs
    *
    * @param record
    */
    _renderRecord: function(record){

        var recset = this.options.recordset;
        function fld(fldname){
            return recset.fld(record, fldname);
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

        $recdiv = $(document.createElement('div'));

        $recdiv
        .addClass('recordDiv')
        .attr('id', 'rd'+recID )
        .attr('recID', recID )
        .attr('bkmk_id', fld('bkm_ID') )
        .attr('rectype', rectypeID )
        //.attr('title', 'Select to view, Ctrl-or Shift- for multiple select')
        //.on("click", that._recordDivOnClick )
        .appendTo(this.div_content);

        $(document.createElement('div'))
        .addClass('recTypeThumb')
        .css('background-image', 'url('+ top.HAPI4.iconBaseURL + 'thumb/th_' + rectypeID + '.png)')
        .appendTo($recdiv);

        if(fld('rec_ThumbnailURL')){
            $(document.createElement('div'))
            .addClass('recTypeThumb')
            .css({'background-image': 'url('+ fld('rec_ThumbnailURL') + ')', 'opacity':'1' } )
            .appendTo($recdiv);
        }

        $iconsdiv = $(document.createElement('div'))
        .addClass('recordIcons')
        .attr('recID', recID )
        .attr('bkmk_id', fld('bkm_ID') )
        .appendTo($recdiv);

        //record type icon
        $('<img>',{
            src:  top.HAPI4.basePath+'assets/16x16.gif',
            title: '@todo rectypeTitle'.htmlEscape()
        })
        //!!! .addClass('rtf')
        .css('background-image', 'url('+ top.HAPI4.iconBaseURL + rectypeID + '.png)')
        .appendTo($iconsdiv);

        //bookmark icon - asterics
        $('<img>',{
            src:  top.HAPI4.basePath+'assets/13x13.gif'
        })
        .addClass(fld('bkm_ID')?'bookmarked':'unbookmarked')
        .appendTo($iconsdiv);

        $('<div>',{
            title: fld('rec_Title')
        })
        .addClass('recordTitle')
        .html(fld('rec_URL') ?("<a href='"+fld('rec_URL')+"' target='_blank'>"+fld('rec_Title') + "</a>") :fld('rec_Title') )
        .appendTo($recdiv);

        $('<div>',{
            id: 'rec_edit_link',
            title: 'Click to edit record'
        })
        .addClass('logged-in-only')
        .button({icons: {
            primary: "ui-icon-pencil"
            },
            text:false})
        .click(function( event ) {
            event.preventDefault();
            //window.open(top.HAPI4.basePath + "php/recedit.php?db="+top.HAPI4.database+"&q=ids:"+recID, "_blank");
            window.open(top.HAPI4.basePathOld + "edit/editRecord.html?db="+top.HAPI4.database+"&recID="+recID, "_new");
        })
        .appendTo($recdiv);


        /*
        var editLinkIcon = "<div id='rec_edit_link' class='logged-in-only'><a href='"+
        top.HEURIST4.basePath+ "records/edit/editRecord.html?sid=" +
        top.HEURIST4.search.results.querySid + "&recID="+ res[2] +
        (top.HEURIST4.database && top.HEURIST4.database.name ? '&db=' + top.HEURIST4.database.name : '');

        if (top.HEURIST4.user && res[6] && (top.HEURIST4.user.isInWorkgroup(res[6])|| res[6] == top.HEURIST4.get_user_id()) || res[6] == 0) {
        editLinkIcon += "' target='_blank' title='Click to edit record'><img src='"+
        top.HEURIST4.basePath + "common/images/edit-pencil.png'/></a></div>";
        }else{
        editLinkIcon += "' target='_blank' title='Click to edit record extras only'><img src='"+
        top.HEURIST4.basePath + "common/images/edit-pencil-no.png'/></a></div>";
        }
        */

        
        return $recdiv;
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
        var recTitle = fld('rec_Title');//.htmlescape();
        
        var html_thumb = '';
        if(fld('rec_ThumbnailURL')){
            html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+ fld('rec_ThumbnailURL') + '&quot;);opacity:1"></div>'
        }
    
        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" rectype="'+rectypeID+'" bkmk_id="'+bkm_ID+'">'
            + '<div class="recTypeThumb" style="background-image: url(&quot;'+ top.HAPI4.iconBaseURL + 'thumb/th_' + rectypeID + '.png&quot;);"></div>'
            + html_thumb 
            + '<div class="recordIcons" recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
            +     '<img src="'+top.HAPI4.basePath+'assets/16x16.gif'+'" title="@todo rectypeTitle" style="background-image: url(&quot;'+top.HAPI4.iconBaseURL + rectypeID+'.png&quot;);">'
            +     '<img src="'+top.HAPI4.basePath+'assets/13x13.gif" class="'+(bkm_ID?'bookmarked':'unbookmarked')+'">'
            + '</div>'
            + '<div title="'+recTitle+'" class="recordTitle">'
            +     (fld('rec_URL') ?("<a href='"+fld('rec_URL')+"' target='_blank'>"+ recTitle + "</a>") :recTitle)  
            + '</div>'
            + '<div title="Click to edit record" class="rec_edit_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false">'
            //+ ' onclick={event.preventDefault(); window.open("'+(top.HAPI4.basePathOld+'edit/editRecord.html?db='+top.HAPI4.database+'&recID='+recID)+'", "_new");} >'
            +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
            + '</div></div>';

            
        return html;

        /*$('<div>',{
            id: 'rec_edit_link',
            title: 'Click to edit record'
        })
        .addClass('logged-in-only')
        .button({icons: {
            primary: "ui-icon-pencil"
            },
            text:false})
        .click(function( event ) {
            event.preventDefault();
            window.open(top.HAPI4.basePath + "php/recedit.php?db="+top.HAPI4.database+"&q=ids:"+recID, "_blank");
        })
        .appendTo($recdiv);*/
        
        
    },
   
    _recordDivOnClick: function(event){

        //var $allrecs = this.div_content.find('.recordDiv');

        var $rdiv = $(event.target), 
            that = this;
            
        var isedit = ($rdiv.parents('.rec_edit_link').length>0); //this is edit click

        if(!$rdiv.hasClass('recordDiv')){
            $rdiv = $rdiv.parents('.recordDiv');
        }

        var selected_rec_ID = $rdiv.attr('recid');
        this.div_content.find('.selected_last').removeClass('selected_last');
        
        if(isedit){
            var url = top.HAPI4.basePathOld + "records/edit/editRecord.html?db="+top.HAPI4.database+"&recID="+selected_rec_ID;
            window.open(url, "_new");              
        }

        if(this.options.multiselect && event.ctrlKey){
            
            if($rdiv.hasClass('selected')){
                $rdiv.removeClass('selected');
                this._lastSelectedIndex = 0;
                //$rdiv.removeClass('ui-state-highlight');
            }else{
                $rdiv.addClass('selected');
                $rdiv.addClass('selected_last'); 
                this._lastSelectedIndex = selected_rec_ID;
                //$rdiv.addClass('ui-state-highlight');
            }
            //this._lastSelectedIndex = selected_rec_ID;
            
        }else if(this.options.multiselect && event.shiftKey){
            
            if(Number(this._lastSelectedIndex)>0){
                var nowSelectedIndex = selected_rec_ID;
                
                this.div_content.find('.selected').removeClass('selected');
                
                var isstarted = false;
                
                this.div_content.find('.recordDiv').each(function(ids, rdiv){
                        var rec_id = $(rdiv).attr('recid');
                        
                        if(rec_id == that._lastSelectedIndex || rec_id==nowSelectedIndex){
                              if(isstarted){ //stop selection and exit
                                  $(rdiv).addClass('selected');
                                  return false;
                              }
                              isstarted = true;
                        }
                        if(isstarted) {
                            $(rdiv).addClass('selected');
                        }
                });
                
                $rdiv.addClass('selected_last');
                that._lastSelectedIndex = selected_rec_ID; 
                
            }else{
                lastSelectedIndex = selected_rec_ID;    
            }
            
            
        }else{
            //remove selection from all recordDiv
            this.div_content.find('.selected').removeClass('selected');
            $rdiv.addClass('selected');
            $rdiv.addClass('selected_last'); 
            this._lastSelectedIndex = selected_rec_ID;

            //var record = this.options.recordset.getById($rdiv.attr('recID'));
        }

        this.triggerSelection();
    },
    
    triggerSelection: function(){

console.log("triggerSelection "+this._lastSelectedIndex);        
        
        if(this.options.isapplication){
            var selected = this.getSelected();
            $(this.document).trigger(top.HAPI4.Event.ON_REC_SELECT, {selection:selected, source:this.element.attr('id')} );
        }
        
        //this._trigger( "onselect", event, selected );
    },

    /**
    * return hRecordSet of selected records
    */
    getSelected: function(){

        var selected = []
        if(top.HAPI4.currentRecordset){
            var that = this;
            this.div_content.find('.selected').each(function(ids, rdiv){
                var rec_ID = $(rdiv).attr('recid');
                if(that._lastSelectedIndex!=rec_ID){
                    selected.push(rec_ID);
                }
            });
            if(Number(this._lastSelectedIndex)>0){
                selected.push(""+this._lastSelectedIndex); 
            }
        }
        return selected;
        
        /* it works but returns recordset 
        var selected = {};
        var that = this;
        
        if(top.HAPI4.currentRecordset){
            this.div_content.find('.selected').each(function(ids, rdiv){
                var rec_ID = $(rdiv).attr('recid');
                if(that._lastSelectedIndex!=rec_ID){
                    var record = top.HAPI4.currentRecordset.getById(rec_ID);
                    selected[rec_ID] = record;
                }
            });
            if(Number(this._lastSelectedIndex)>0){
                var record = top.HAPI4.currentRecordset.getById(this._lastSelectedIndex);
                selected[this._lastSelectedIndex] = record;
            }

            return top.HAPI4.currentRecordset.getSubSet(selected, this.document);
        }else{
            return null
        }*/
    },

    /**
    * selection - hRecordSet or array of record Ids
    * 
    * @param record_ids
    */
    setSelected: function(selection){

        //clear selection
        this.div_content.find('.selected').removeClass('selected');
        this.div_content.find('.selected_last').removeClass('selected_last');

        if (selection == "all") {
            this.div_content.find('.recordDiv').addClass('selected');   
        }else{

            var recIDs_list = top.HAPI4.getSelection(selection, true);    
            if( top.HEURIST4.util.isArrayNotEmpty(recIDs_list) ){
            
                    this.div_content.find('.recordDiv').each(function(ids, rdiv){
                            var rec_id = $(rdiv).attr('recid');
                            if(recIDs_list.indexOf(rec_id)>=0){
                               $(rdiv).addClass('selected'); 
                               //if(that._lastSelectedIndex==rec_id){
                               //    $(rdiv).addClass('selected_last'); 
                               //}
                            }
                    });
                
            }    
        }

    },
    
    
    loadanimation: function(show){
        if(show){
            this.div_content.css('background','url('+top.HAPI4.basePath+'assets/loading-animation-white.gif) no-repeat center center');
        }else{
            this.div_content.css('background','none');
        }
    },

    /**
    * search with the same criteria for all reocrds (assumed before it was for bookmarks)
    * 
    * @returns {Boolean}
    */
    _doSearch4: function(){

        if ( this._query_request ) {

            this._query_request.w = 'a';
            this._query_request.source = this.element.attr('id'); //orig = 'rec_list';

            top.HAPI4.RecordMgr.search(this._query_request, $(this.document));
        }

        return false;
    }
    
    , _renderProgress: function(){
    
    } 

});
