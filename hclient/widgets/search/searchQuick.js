/**
* Simple query builder
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @designer    Ian Johnson     <ian.johnson@sydney.edu.au>
* @note        Completely revised for Heurist version 4
* @note        BEING REPLACVED WITH ENHANCED FILTER BUILDER IN VERSION 6
* @TODO         DELETE WHEN NO LOGNER REFERENCED
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


$.widget( "heurist.searchQuick", $.heurist.recordAction, {

    // default options
    options: {
        is_h6style: true,
        is_json_query: false,    
        
        isdialog: false, 
        supress_dialog_title: true,
        menu_locked: null, //callback to prevent close h6 menu on mouse exit
        
        button_class: 'ui-button-action',
        
        currentRecordset: {},  //stub
        search_realm: null
    },
    
    current_query:null,
    current_query_json:null,

    _suprress_change: true,
    
    //
    //
    //
    _init: function(){
        
        this.element.css('overflow','hidden');
        
        this.options.htmlContent = window.hWin.HAPI4.baseURL+'hclient/widgets/search/searchQuick.html'
                            +'?t='+window.hWin.HEURIST4.util.random();
        this._super();        
    },

    //  
    // invoked from _init after loading of html content
    //
    _initControls:function(){
        
        var that = this;

        /*
        this.selectRecordScope = this.element.find('#sel_record_scope');
        if(this.selectRecordScope.length>0){
            this._fillSelectRecordScope();
        }
        */
        
        var $dlg = this.element.children('fieldset');
        
        if(this.options.is_h6style){
            //add title 
            $dlg.css({top:'36px',position:'absolute',width: 'auto', margin: '0px','font-size':'0.9em'});
            
            var _innerTitle = $('<div class="ui-heurist-header" style="top:0px;padding-left:10px;text-align:left">Filter builder</div>')
                .insertBefore($dlg);
                
            $('<button>').button({icon:'ui-icon-closethick',showLabel:false, label:'Close'}) 
                     .css({'position':'absolute', 'right':'4px', 'top':'6px', height:20, width:20})
                     .appendTo(_innerTitle)
                     .on({click:function(){
                         that.closeDialog();
                     }});
                
        }else{
             $dlg.addClass('ui-heurist-header1');
        }
        
        
        var dv = $dlg.find('.btns')
                .css({'display':'block !important'});

        this.search_quick_go = $( "<button>")
        .appendTo( dv )
        //.css({position:'absolute', zIndex:9999, 'right':4, top:4, width:18, height:18})
        .css('float', 'right')
        .button({
            icon: 'ui-icon-filter',
            label: window.hWin.HR("Filter"), showLabel:true
        })
        .addClass(this.options.button_class);
        
        this._on( this.search_quick_go, {
            click: function(event){
                this.doAction();
            }
        });
        
        
        this.search_get_query = $( "<button>")
        .appendTo( dv )
        .css({'float':'right',margin:'4px 10px'})
        .button({
            label: window.hWin.HR("get filter string"), showLabel:true
        });
        
        this._on( this.search_get_query, {
            click: function(event){
                this.getQueryString();
            }
        });
        
        this._on( $('<a href="#" style="float:left">Advanced</a>')
        .appendTo( dv )
        .css({'float':'left',margin:'4px 10px','font-size':'0.8em'}), {
            click: function(event){
                var widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('mainMenu6');
                if(widget){
                    var pos = this.element.offset();
                    //this.closeDialog();
                    setTimeout(function(){
                        widget.mainMenu6('show_ExploreMenu', null, 'searchBuilder', {top:pos.top-46, left:pos.left});    
                    },200);
                    
                }
            }
        });

        
        
        

        //find all labels and apply localization
        $dlg.find('label').each(function(){
            $(this).html(window.hWin.HR($(this).html()));
        });
        
        $dlg.find(".fld_enum").hide();
        
/* BAX
        var select_rectype = $dlg.find(".sa_rectype");//.uniqueId();
        var select_fieldtype = $dlg.find(".sa_fieldtype");
        var select_sortby = $dlg.find(".sa_sortby");
        var select_terms = $dlg.find(".sa_termvalue");
        var sortasc =  $dlg.find('.sa_sortasc');
        var exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);
        select_rectype = window.hWin.HEURIST4.ui.createRectypeSelectNew(select_rectype.get(0), 
                    {useIcons: false, useCounts:true, useGroups:true, useIds: (exp_level<2), 
                        topOptions:window.hWin.HR('Any record type'), useHtmlSelect:false});
*/
        function __startSearchOnEnterPress(e){
                var code = (e.keyCode ? e.keyCode : e.which);
                if (code == 13) {
                    window.hWin.HEURIST4.util.stopEvent(e);
                    that.doAction();
                }
        }
        
        that._on( $dlg.find('.text'), { keypress: __startSearchOnEnterPress});
        

        
/*        
        that._on( select_terms, { change: function(event){
                this.calcShowSimpleSearch();
                //AAAA
                search_quick_go.focus();
            }
        } );
        that._on( select_sortby, { change: function(event){
                this.calcShowSimpleSearch();
                //AAAA
                search_quick_go.focus();
            }
        } );
        that._on( $dlg.find(".sa_fieldvalue"), {
            keyup: function(event){
                this.calcShowSimpleSearch();
            }
        });
        that._on( $dlg.find(".sa_negate"), {
            change: function(event){
                this.calcShowSimpleSearch();
                //AAAA
                search_quick_go.focus();
            }
        });
        that._on( $dlg.find(".sa_negate2"), {
            change: function(event){
                this.calcShowSimpleSearch();
            }
        });
        that._on( $dlg.find(".sa_coord1"), {
            change: function(event){
                this.calcShowSimpleSearch();
            }
        });
        that._on( $dlg.find(".sa_coord2"), {
            change: function(event){
                this.calcShowSimpleSearch();
            }
        });
        that._on( sortasc, {
            click: function(event){
                //window.hWin.HEURIST4.util.stopEvent(event);
                //sortasc.prop('checked', !sortasc.is(':checked'));
                this.calcShowSimpleSearch();
            }
        });

*/        
        $dlg.find(".sa_spatial").button();
        that._on( $dlg.find(".sa_spatial"), {    //opens digitizer
            click: function(event){
                
                this._lockPopup(true)
                
                //open map digitizer - returns WKT rectangle 
                var rect_wkt = $dlg.find(".sa_spatial_val").val();
                var url = window.hWin.HAPI4.baseURL
                +'viewers/map/mapDraw.php?db='+window.hWin.HAPI4.database;

                var wkt_params = {wkt: rect_wkt, geofilter:true};

                window.hWin.HEURIST4.msg.showDialog(url, {height:'540', width:'600',
                    window: window.hWin,  //opener is top most heurist window
                    dialogid: 'map_digitizer_filter_dialog',
                    params: wkt_params,
                    title: window.hWin.HR('Heurist spatial search'),
                    class:'ui-heurist-bg-light',
                    afterclose: function(){
                        that._lockPopup(false)
                    },
                    callback: function(location){
                        
                        if( !window.hWin.HEURIST4.util.isempty(location) ){
                            //that.newvalues[$input.attr('id')] = location
                            $dlg.find(".sa_spatial_val").val(location.wkt);
                            that.calcShowSimpleSearch();
                        }
                    }
                } );
            }
        });
        
        $dlg.find(".sa_spatial_clear").button({
            icon: 'ui-icon-undo',
            showLabel:false
        });
        that._on( $dlg.find(".sa_spatial_clear"), {
            click: function(event){
                $dlg.find(".sa_spatial_val").val('');
                that.calcShowSimpleSearch();
            }
        });
        
        this.popupDialog();
        
        
        window.hWin.HAPI4.addEventListener(this, window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            function(data) { 
                that._recreateSelectors();
            });
        
        this._recreateSelectors();       
        
        return true;
    },
    
    _lockPopup: function( is_locked ){
        
        if($.isFunction(this.options.menu_locked)){
            this.options.menu_locked.call( this, is_locked );
        }
    },
    
    //
    //
    //
    _recreateSelectors: function(){
        
        this._suprress_change = true;
        
        var that = this;
        var $dlg = this.element.children('fieldset');
        var select_rectype = $dlg.find(".sa_rectype");//.uniqueId();
        var select_fieldtype = $dlg.find(".sa_fieldtype");
        var select_sortby = $dlg.find(".sa_sortby");
        var select_terms = $dlg.find(".sa_termvalue");
        var sortasc =  $dlg.find('.sa_sortasc');
        
        var allowed = Object.keys($Db.baseFieldType);
        allowed.splice(allowed.indexOf("separator"),1);
        allowed.splice(allowed.indexOf("geo"),1);
        allowed.splice(allowed.indexOf("relmarker"),1);
        
        var exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);
        
        select_rectype = window.hWin.HEURIST4.ui.createRectypeSelectNew(select_rectype.get(0), 
                    {useIcons: false, useCounts:true, useGroups:true, useIds: (exp_level<2), 
                        topOptions:window.hWin.HR('Any record type'), useHtmlSelect:false});
                        
        //change list of field types on rectype change
        that._on( select_rectype, {
            change: function (event){

                var rectype = (event)?Number(event.target.value):0;
                
                //var topOptions2 = 'All fields';
                var topOptions2 = [{key:'',title:window.hWin.HR('All fields')},{key:'title',title:'Record title (constructed)'}];
                var bottomOptions = null;

                if(!(rectype>0)){
                    bottomOptions = [{key:'latitude',title:window.hWin.HR('geo: Latitude')},
                                     {key:'longitude',title:window.hWin.HR('geo: Longitude')}]; 
                }
                var exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);
                
                select_fieldtype = window.hWin.HEURIST4.ui.createRectypeDetailSelect(
                        select_fieldtype[0], //$dlg.find(".sa_fieldtype").get(0), 
                            rectype, allowed, topOptions2, 
                            {show_parent_rt:true, show_latlong:true, bottom_options:bottomOptions, 
                                useIds: (exp_level<2), useHtmlSelect:false});

                var topOptions = [{key:'t', title:window.hWin.HR("record title")},
                    {key:'id', title:window.hWin.HR("record id")},
                    {key:'rt', title:window.hWin.HR("record type")},
                    {key:'u', title:window.hWin.HR("record URL")},
                    {key:'m', title:window.hWin.HR("date modified")},
                    {key:'a', title:window.hWin.HR("date added")},
                    {key:'r', title:window.hWin.HR("personal rating")},
                    {key:'p', title:window.hWin.HR("popularity")}];

                if(Number(rectype)>0){
                    topOptions.push({optgroup:'yes', title:$Db.rty(rectype,'rty_Name')+' '+window.hWin.HR('fields')});
                }
                select_sortby = window.hWin.HEURIST4.ui.createRectypeDetailSelect(
                        select_sortby[0], //$dlg.find(".sa_sortby").get(0)
                        rectype, allowed, topOptions,
                            {initial_indent:1, useHtmlSelect:false});
                
                select_sortby.css({margin:'4px 0px'});            
                            
                that._on( select_fieldtype, {
                    change: __onFieldTypeChange
                });
                that._on( select_sortby, {
                    change: function(event){ 
                        this.calcShowSimpleSearch(); 
                        this.search_quick_go.focus();
                    }
                });
                            
                $dlg.find(".sa_fieldvalue").val("");
                $dlg.find(".sa_negate").prop("checked",'');
                $dlg.find(".sa_negate2").prop("checked",'');
                
                $dlg.find(".fld_contain").css({'display':'inline-block'});
                $dlg.find(".fld_enum").hide();
                $dlg.find(".fld_coord").hide();
                this.calcShowSimpleSearch();
                //AAAA 
                this.search_quick_go.focus();
            }
        });
        
        //change compare option according to selected field type
        // enum, geocoord, others
        function __onFieldTypeChange(event){
            
                if(event.target.value=='longitude' || event.target.value=='latitude'){

                    $dlg.find(".fld_contain").hide();
                    $dlg.find(".fld_enum").hide();
                    $dlg.find(".fld_coord").css({'display':'inline-block'});
                    
                }else{
                    var dtID = Number(event.target.value);
                    
                    $dlg.find(".fld_coord").hide();
                
                    var detailType = '';

                    if(Number(dtID)>0){
                        detailType = $Db.dty(dtID,'dty_Type');
                    }
                    if(detailType=='enum'  || detailType=='relationtype'){
                        $dlg.find(".fld_contain").hide();
                        $dlg.find(".fld_enum").css({'display':'inline-block'});

                        var select_terms = $dlg.find(".sa_termvalue");
                        
                        window.hWin.HEURIST4.ui.createTermSelect(select_terms.get(0),
                        {vocab_id: (detailType=='relationtype')?'relation':$Db.dty(dtID,'dty_JsonTermIDTree'),
                            topOptions:[{ key:'any', title:window.hWin.HR('<any>')},{ key:'blank', title:'  '}] });
                                             
                        that._on( select_terms, { change: function(event){
                                this.calcShowSimpleSearch();
                            }
                        } );
                                                                                                 
                    } else {
                        $dlg.find(".fld_contain").css({'display':'inline-block'});
                        $dlg.find(".fld_enum").hide();
                    }
                    
                }

                this.calcShowSimpleSearch();
                //AAAA
                this.search_quick_go.focus();
        }//__onFieldTypeChange
            
        that._on( select_fieldtype, {
            change: __onFieldTypeChange
        });        
        that._on( sortasc, {
            change: that.calcShowSimpleSearch
        });        
                        
                        
        select_rectype.trigger('change'); 
        
        this._suprress_change = false;
    },
    
    //
    //
    //
    calcShowSimpleSearch: function(){
      
        if(!this._suprress_change) this._lockPopup('delay');
        
        var $dlg = this.element.children('fieldset');
        
        var q = $dlg.find(".sa_rectype").val(); if(q) q = "t:"+q;
        var fld = $dlg.find(".sa_fieldtype").val(); 
        var ctn = '';  //field value
        var spatial = '';
        
        if(fld=='latitude' || fld=='longitude'){
            var coord1 = $dlg.find(".sa_coord1").val();
            var coord2 = $dlg.find(".sa_coord2").val();
            
            var morethan = !isNaN(parseFloat(coord1));
            var lessthan = !isNaN(parseFloat(coord2));
            
            if(morethan && lessthan){
                fld = fld+':'+coord1+'<>'+coord2;
            }else if(morethan){
                fld = fld+'>'+coord1;
            }else if(lessthan){
                fld = fld+'<'+coord2;
            }else{
                fld = '';
            }
        }else{
            
            var isEnum = false;//$dlg.find(".fld_enum").is(':visible');
            
            if(fld){
                var detailType = '';

                if(Number(fld)>0){
                    var detailType = $Db.dty(fld,'dty_Type');
                    isEnum = (detailType=='enum'  || detailType=='relationtype');
                    fld = "f:"+fld+":";
                }else{
                    fld = fld+":";
                }
                  
            } 
            
            if(isEnum){
                var termid = $dlg.find(".sa_termvalue").val();
                if(termid=='any' || termid=='blank'){
                    ctn = ''; 
                }else{
                    ctn = termid;
                }
                if(termid=='blank' || $dlg.find(".sa_negate2").is(':checked')){
                    fld  = '-'+fld;
                }
                
            }else{
                ctn =  $dlg.find(".sa_fieldvalue").val();
                if($dlg.find(".sa_negate").is(':checked')){
                    fld  = '-'+fld;
                }
            }
        }
        
        if($dlg.find(".sa_spatial_val").val()){
            spatial = ' geo:'+$dlg.find(".sa_spatial_val").val();  
        }

        var asc = ($dlg.find(".sa_sortasc").val()==1?"-":'') ; //($("#sa_sortasc:checked").length > 0 ? "" : "-");
        var srt = $dlg.find(".sa_sortby").val();
        srt = (srt == "t" && asc == "" ? "" : ("sortby:" + asc + (isNaN(srt)?"":"f:") + srt));

        q = (q? (fld?q+" ": q ):"") + (fld?fld: (ctn?" all:":"")) 
                 + (ctn?(isNaN(Number(ctn))?'"'+ctn+'"':ctn):"")
                 + spatial
                 + (srt? " " + srt : "");
        if(!q){
            q = "sortby:t";
        }
        
        
        this.current_query = q;
        this.current_query_json = {};// q_json;

        //e = window.hWin.HEURIST4.util.stopEvent(e);        
    },
    
    //
    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {

        //$(window.hWin.document).off(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);
        window.hWin.HAPI4.removeEventListener(this, window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);        
        
        //this.div_entity_btns.remove();
    },
    
    getQueryString: function(){
        
        this.calcShowSimpleSearch();
        
        var req = {q:this.options.is_json_query ?this.current_query_json :this.current_query};
        
        window.hWin.HEURIST4.util.hQueryCopyPopup(req, {my:'middle bottom', at:'middle top', of:this.search_get_query});

    },

    //
    //
    //
    doAction: function(){
        
        this.calcShowSimpleSearch();
        var request = {};
            request.q = this.options.is_json_query ?this.current_query_json :this.current_query;
            request.w  = 'a';
            request.detail = 'ids';
            request.source = this.element.attr('id');
            request.search_realm = this.options.search_realm;
            
            window.hWin.HAPI4.SearchMgr.doSearch( this, request );
        
        this.closeDialog();
    },
    
    outerHeight: function(callback){
        
        var fs = this.element.children('fieldset');
        var eles = fs.children();
        if(!fs.is(':visible') || eles.length==0){
            var that = this;
            setTimeout(function(){that.outerHeight(callback)},500);
            return 246;
        }
        var h = fs.outerHeight();
        if(h<20){
            $.each(eles,function(i,ele){ h = h + $(ele).outerHeight();});
        }
        
        h = h + this.element.children('div.ui-heurist-header').outerHeight();
        
        callback.call(this, h);

        return h;
    }
    
});
