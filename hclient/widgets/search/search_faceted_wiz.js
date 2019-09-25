/**
*  Wizard to define new faceted search
*    1. Options
*    2. [removed]
*    3. Select fields for facets (recTitle, numeric, date, terms, pointers, relationships)
*    4. Define ranges for date and numeric fields
*    5. Preview
*    6. Save into database
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


/* Explanation of faceted search - @todo OUTDATED - NEED TO REWRITE

There are two types of queries: 1) to search facet values 2) to search results

Examples
1) No levels:

search results: t:10 f:1:"XXX" - search persons by name
facet search:   f:1  where t:10 + other current queries

2) One level:

search results: t:10 linked_to:5-61 [t:5 f:1:"XXX"] - search persons where multimedia name is XXX
facet search:   f:1  where t:5 linkedfrom:10-61 [other current queries(parent query)]

3) Two levels

search results: t:10 linked_to:5-61 [t:5 linked_to:4-15 [t:4 f:1:"XXX"]] - search persons where multimedia has copyright of organization with name is XXX
facet search:   f:1 where t:4 linkedfrom:5-15 [t:5 linkedfrom:10-61 [other current queries for person]]   - find organization who is copyright of multimedia that belong to person


Thus, our definition has the followig structure
rectype - main record type to search
domain
facets:[ [
code:  10:61:5:15:4:1  to easy init and edit    rt:ft:rt:ft:rt:ft  if link is unconstrained it will be empty  61::15
title: "Author Name < Multimedia"
id:  1  - field type id
type:  "freetext"  - field type
levels: [t:4 linkedfrom:5-15, t:5 linkedfrom:10-61]   (the last query in this array is ommitted - it will be current query)

search - main query to search results
[linked_to:5-61, t:5 linked_to:4-15, t:4 f:1]    (the last query in the top most parent )

currentvalue:
history:  - to keep facet values (to avoid redundat search)

],
the simple (no level) facet
[
code: 10:1
title: "Family Name"
type:  "freetext"
id: 1
levels: []
search: [t:10 f:1]
]

]

NOTE - to make search fo facet value faster we may try to omit current search in query and search for entire database

*/
                     
/*
step1 - selection of rectype, sup filter, rules
step2 - select fields in treeview
step3 - define label, help prompt and filter type (by first letter, by full name, directly)
step4 - preview
*/
$.widget( "heurist.search_faceted_wiz", {

    // default options
    options: {
        svsID: null,
        domain: null, // bookmark|all or usergroup ID
        params: {
            viewport:5
        },
        onsave: null
    },
    isversion:2,
    //params:
    // domain
    // rectype_as_facets
    // fieldtypes:[] //allowed field types besides enum amd resource
    //  rectypes:[]                                                                                                                                     //for enums     //for other
    //  facets:[[{ title:node.title, type: freetext|enum|integer|relmarker, query: "t:id f:id", fieldid: "f:id",
    //                                    currentvalue:{text:label, query:value, termid:termid}, history:[currentvalue] }, ]

    /*
    {
    "rectype_as_facets":false,
    "fieldtypes":["enum","freetext","year","date","integer","float"],
    "rectypes":["11"],
    "facets":[
    [{"title":"Language - change to required","type":"enum",    "query":"f:99",     "fieldid":"f:99","currentvalue":null,"history":[]}],
    [{"title":"Family name",                  "type":"freetext","query":"t:10 f:1" ,"fieldid":"f:1", "currentvalue":null,"history":[]},
    {"title":"Person affected",              "type":"resource","query":"t:13 f:16","fieldid":"f:16"},
    {"title":"Punishment event(s) - SAVE record first","type":"resource", "query":"f:93","fieldid":"f:93"}],
    [{"title":"Given name(s)","type":"freetext","query":"t:10 f:18","fieldid":"f:18","currentvalue":null,"history":[]},         lvl2
    {"title":"Person affected","type":"resource","query":"t:13 f:16","fieldid":"f:16"},                                        lvl1
    {"title":"Punishment event(s) - SAVE record first","type":"resource","query":"f:93","fieldid":"f:93"}]],"domain":"all"}    lvl0

    */


    select_main_rectype: null,

    step: 0, //current step
    step_panels:[],
    current_tree_rectype_ids:null,
    originalRectypeID:null, //flag that allows to save on first page for edit mode

    // the widget's constructor
    _create: function() {

        var that = this;

        // prevent double click to select text
        //this.element.disableSelection();

        // Sets up element to apply the ui-state-focus class on focus.
        //this._focusable($element);

        var that = this;

        this.element.css({overflow: 'hidden !important'}).addClass('ui-heurist-bg-light');

        var ht = $(window).height();
        if(ht>700) ht = 700;

        this.element.dialog({
            autoOpen: false,
            height: 580,
            width: 1000,
            modal: true,
            title: window.hWin.HR("Define Faceted Search"),
            resizeStop: function( event, ui ) {
                that.element.css({overflow: 'hidden !important','width':'100%'});
            },
            beforeClose: function( event, ui ) {
                if(event && event.currentTarget){
                    var that_dlg = this;
                    window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR("Discard changes?"),
                        function(){ $( that_dlg ).dialog( "close" ); });
                    return false;
                }
            },
            buttons: [
                {text:window.hWin.HR('Back'), id:'btnBack',
                    click: function() {
                        that.navigateWizard(-1);
                }},
                {text:window.hWin.HR('Next'), id:'btnNext',
                    click: function() {
                        that.navigateWizard(1);
                }},
                {text:window.hWin.HR('Save'), id:'btnSave',
                    click: function() {
                        that._doSaveSearch()
                        //that.navigateWizard(1);
                }},
            ]
        });
        this.element.parent().addClass('ui-dialog-heurist');

        this.element.parent().find('.ui-dialog-buttonset').css('margin-right','260px');
        
        
        //option
        this.step0 = $("<div>")
        .css({width:'100% !important', 'display':'block'})
        .appendTo(this.element);
        //$("<div>").append($("<h4>").html(window.hWin.HR("Options"))).appendTo(this.step0);
        $("<div>",{id:'facets_options'}).appendTo(this.step0);
        this.step_panels.push(this.step0);


        //select rectypes  - this step is always hidden @todo remove it 
        this.step1 = $("<div>")
        .css({'display':'none'})
        .appendTo(this.element);
        this.step_panels.push(this.step1);
        
        //select field types
        this.step2 = $("<div>")
        .css({width:'100% !important', 'display':'none'})
        .appendTo(this.element);

        var header = $("<div>").css({'font-size':'0.8em', 'padding-bottom':'10px'}).appendTo(this.step2);

        header.html("<label>"+window.hWin.HR("Select fields that act as facet")+
            "</label><br><br><label for='fsw_showreverse'><input type='checkbox' id='fsw_showreverse' style='vertical-align: middle;' />&nbsp;"+
            window.hWin.HR("Show linked-from record types (reverse pointers, indicated as &lt;&lt;)")+"</label>");

        //$("<label>").text(window.hWin.HR("Select fields that act as facet")).appendTo(header);
        //$("<checkbox>").text(window.hWin.HR("Show linked-from record types (reverse pointers)")).appendTo(header);

        $("<div>",{id:'field_treeview'}).appendTo(this.step2);
        this.step_panels.push(this.step2);


        //ranges
        this.step3 = $("<div>").addClass('step3')
        .css({width:'100% !important', 'display':'none'})
        .appendTo(this.element);

        var div_leftside = $("<div>").css({position:'absolute',top:0,bottom:0,left:0,right:'301px'}).appendTo(this.step3);
        
        var header = $("<div>").css({'font-size':'0.8em','padding':'4px 10px'}).appendTo(div_leftside);
        header.html("<label>"+window.hWin.HR("Define titles, help tips and facet type")+"</label>"
        +'<br><br><label><input type="checkbox" id="cbShowHierarchy" style="vertical-align: middle;">'
            +window.hWin.HR("Show entity hierarchy above facet label")+"</label>"
            +'<label style="margin-left:16px" for="selViewportLimit">'+window.hWin.HR("Limit lists initially to")+'</label>'
            +'&nbsp;<select id="selViewportLimit"><option value=0>All</option><option value=5>5</option><option value=10>10</option>'
            +'<option value=20>20</option><option value=50>50</option></select>'
            
            +'<div style="float:right"><label><input type="checkbox" id="cbShowAdvanced" style="vertical-align: middle;">'
            +window.hWin.HR("Show advanced ")+'</label></div>'
            );
            
        $("<div>",{id:'facets_list'}).css({'overflow-y':'auto','padding':'10px 10px 10px 0px',
                'min-width':'670px',width:'100%'}).appendTo(div_leftside); //fieldset
        
        $("<div>",{id:'facets_preview2'})
        .css({position:'absolute',top:0,bottom:0,right:0,width:'300px', 'border-left':'1px solid lightgray'})
        .appendTo(this.step3);
        this.step_panels.push(this.step3);

        
        //preview
        this.step4 = $("<div>")
        .css({width:'100% !important', 'display':'none'})
        .appendTo(this.element);
        $("<div>").append($("<h4>").html(window.hWin.HR("Preview"))).appendTo(this.step4);
        $("<div>",{id:'facets_preview'}).css({'top':'3.2em'}).appendTo(this.step4);
        this.step_panels.push(this.step4);
        

        this._refresh();

    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash,
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
    },

    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    /*
    _setOptions: function( options ) {
    },*/

    /*
    * private function
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

    },
    //
    // custom, widget-specific, cleanup.
    _destroy: function() {
        
        if(this.select_main_rectype){
            if(this.select_main_rectype.hSelect("instance")!=undefined){
               this.select_main_rectype.hSelect("destroy"); 
            }
            this.select_main_rectype.remove();   
        }
        
        
        // remove generated elements
        this.step0.remove();
        this.step1.remove();
        this.step2.remove();
        this.step3.remove();
        this.step4.remove();
    }

    ,show: function(){
        this.element.dialog("open");
        this.step1.hide();
        this.step2.hide();
        this.step3.hide();
        this.step4.hide();
        this.navigateWizard(NaN); //init for 0 step
    }

    , navigateWizard: function(nav){
        //@todo - validate

        var newstep = 0;
        if(isNaN(nav)){
            this.step = NaN;
        }else{
            newstep = this.step + nav;
        }

        if(newstep<0){
            newstep = 0;
        }else if(newstep>3){ //was 4
            if(newstep==4){
                //save into database
                this._doSaveSearch();
                return;
            }
            newstep = 3; //was 4
        } else{
            //$("#btnNext").button('option', 'label', window.hWin.HR('Next'));
            $("#btnNext").button({icon:'ui-icon-carat-1-e', iconPosition:'end', label:window.hWin.HR('Next')});
        }
        $("#btnBack").button('option','icon','ui-icon-carat-1-w').css('margin-right','20px');
        
        if(this.step != newstep){

            if(isNaN(this.step) && newstep==0){ //select record types

                var that = this;
                var $dlg = this.step0.find("#facets_options");
                if($dlg.html()==''){
                    $dlg.load(window.hWin.HAPI4.baseURL+"hclient/widgets/search/search_faceted_wiz.html?t=19", function(){
                        that._initStep0_options();
                        
                        $dlg.find( ".optional_fields" ).accordion({collapsible:true, active:false});
                        $dlg.find( ".optional_fields > fieldset" ).css({'background':'none'});

                        /* it works however it produces a large gap below
                        $dlg.find("#svs_btnset")
                                .css({'width':'20px'})
                                .position({my: "left top", at: "right+4 top", of: $dlg.find('#svs_Rules') });
                        */
                                
                        $dlg.find("#svs_Rules_edit")
                        .button({icons: {primary: "ui-icon-pencil"}, text:false})
                        .attr('title', window.hWin.HR('Edit RuleSet'))
                        .css({'height':'16px', 'width':'16px'})
                        .click(function( event ) {
                            //that.
                            that._editRules( $dlg.find('#svs_Rules') );
                        });

                        $dlg.find("#svs_Rules_clear")
                        .button({icons: {primary: "ui-icon-close"}, text:false})
                        .attr('title', window.hWin.HR('Clear RuleSet'))
                        .css({'height':'16px', 'width':'16px'})
                        .click(function( event ) {
                            $dlg.find('#svs_Rules').val('');
                        });

                                
                        /* it works however it produces a large gap below
                        $dlg.find("#svs_btnset2")
                                .css({'width':'20px'})
                                .position({my: "left top", at: "right+4 top", of: $dlg.find('#svs_Query') });
                        */

                        $dlg.find("#svs_getCurrentFilter")
                        .button({icons: {primary: "ui-icon-search"}, text:false})
                        .attr('title', window.hWin.HR('Get current query'))
                        .css({'height':'16px', 'width':'16px'})
                        .click(function( event ) {
                            
                                var res = window.hWin.HEURIST4.util.hQueryStringify(window.hWin.HEURIST4.current_query_request);
                                
                                $dlg.find('#svs_Query').val(res).trigger('keyup');
                                
                        });

                    });
                }else{
                    this._initStep0_options();
                }

            }
            else if(this.step==0 && newstep==1){ //select record types

                if(!(this.select_main_rectype.val()>0)){
                    window.hWin.HEURIST4.msg.showMsgErr('Select record type');
                    if(this.select_main_rectype.hSelect("instance")!=undefined){
                        this.select_main_rectype.hSelect("focus"); 
                    }else{
                        this.select_main_rectype.focus();  
                    }
                    return;
                }
            
                var svs_name = this.step0.find('#svs_Name');
                var message = this.step0.find('.messages');
                var bValid = window.hWin.HEURIST4.msg.checkLength( svs_name, "Name", message, 3, 64 );
                if(!bValid){
                    svs_name.focus();
                    //window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR("Define Saved search name"), 2000, "Required", svs_name);
                    //setTimeout(function(){svs_name.focus();},2200);
                    return;
                }else{
                    message.empty();
                    svs_name.removeClass( "ui-state-error" );
                }
                
                var svs_title = this.step0.find('#svs_Title');
                bValid = window.hWin.HEURIST4.msg.checkLength( svs_title, 'Title', message, 0, 64 );
                if(!bValid){
                    svs_title.focus();                
                    return;
                }else{
                    message.empty();
                    svs_title.removeClass( "ui-state-error" );
                }


                this.step = 1;
                newstep = 2; //skip step
            }

            if(this.step==1 && newstep==2){ //select field types

                var rectypeIds = [this.select_main_rectype.val()];
                
                //mandatory
                if(!window.hWin.HEURIST4.util.isArrayNotEmpty(rectypeIds)){
                    window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR("Select record type"));

                    this.step = 0;
                    return;
                }else{
                    this.step0.hide();
                }

                //load field types
                /* ART20150810
                allowed = ['enum','freetext'];
                var $dlg = this.step0.find("#facets_options");
                if($dlg.find("#opt_use_freetext").is(":checked")){
                allowed.push('freetext');
                }
                if($dlg.find("#opt_use_date").is(":checked")){
                allowed.push('year');
                allowed.push('date');
                }
                if($dlg.find("#opt_use_numeric").is(":checked")){
                allowed.push('integer');
                allowed.push('float');
                }
                this.options.params.fieldtypes = allowed;
                */

                //load list of field types
                this._initStep2_FieldTreeView(rectypeIds);

            }  if(this.step==2 && newstep==3){  //set individual facets

                if(!this._initStep3_FacetsSettings()){
                    return;
                }
            }

            //skip step
            if(this.step==2 && newstep==1){
                newstep = 0;
            }else if(this.step==4 && newstep==3){
                //newstep=2;
            }

            this._showStep(newstep);

            //if(newstep==3)               //skip step - define ranges
            //this.navigateWizard(1);

        }

        
        window.hWin.HEURIST4.ui.applyCompetencyLevel(-1, $dlg); 
    }

    , _showStep :function(newstep){

        if(this.step>=0) this.step_panels[this.step].css({'display':'none'});
        this.step_panels[newstep].css({'display':'block','overflow':'hidden'});

        if(this.step==3 && newstep==4){ //preview
            this._assignFacetParams();
            this._initStep4_FacetsPreview();
            $("#btnNext").button('option', 'label', window.hWin.HR('Save'));
        }
        if(this.step==2 && newstep==3){
            this._assignFacetParams();
            this._refresh_FacetsPreview();
            //$("#btnNext").button('option', 'label', window.hWin.HR('Save'));
            $("#btnNext").button({icon:'ui-icon-check', label:window.hWin.HR('Save')});
        }

        if(newstep==0 && this.options.svsID>0 && 
            this.options.params.rectypes && this.options.params.rectypes[0]==this.originalRectypeID){
            $("#btnSave").css({'visibility':'visible','margin-left':'20px'});//show();
        }else{
            $("#btnSave").css('visibility','hidden');//$("#btnSave").hide();
        }
        
        
        if(newstep==0){
            var that = this;
            setTimeout(function(){that.step0.find('#svs_Name').focus();},500);
            $("#btnBack").hide();
            
        }else{
            var ht = $(window).height();
            if(ht>700) ht = 700;
            this.element.dialog( "option", "height",  ht );

            $("#btnBack").show();
        }

        this.step = newstep;
    }


    /**
    * Assign values to UI input controls
    */
    , _initStep0_options: function( ){

        var $dlg = this.step0;
        if($dlg){

            var that = this;

            var svs_id = $dlg.find('#svs_ID');
            var svs_name = $dlg.find('#svs_Name');
            var svs_ugrid = $dlg.find('#svs_UGrpID');
            var svs_rules = $dlg.find('#svs_Rules');
            var svs_rules_only = $dlg.find('#svs_RulesOnly');
            var svs_filter = $dlg.find('#svs_Query');
            
            this._on(svs_filter,{keyup:function(){                                 
                        if(svs_filter.val()!=''){
                            $dlg.find('.prefilter-settings').show();    
                        }else{
                            $dlg.find('.prefilter-settings').hide();    
                        }
                        $dlg.find('#svs_PrelimFilterToggle').change();
                }});
            

            $dlg.find('.messages').empty();
            svs_name.removeClass( "ui-state-error" );

            if(this.select_main_rectype==null){
                
                this.select_main_rectype = window.hWin.HEURIST4.ui.createRectypeSelectNew( $dlg.find("#opt_rectypes").get(0), {
                    topOptions: [{key:'',title:'select...'}],
                    useHtmlSelect: false,
                    showAllRectypes: true
                });
                this.select_main_rectype.hSelect({change: function(event, data){
                    var selval = data.item.value;
                    if(selval>0){
                        if(svs_name.val()==''){
                            var opt = that.select_main_rectype.find('option[value="'+selval+'"]');
                            svs_name.val(opt.text().trim()+'s');
                        }
                        svs_name.focus();
                        
                        that.options.params.facets = [];
                    }
                    
                    that.originalRectypeID=null; //reset flag - facet was changed - need to proceed all steps of wizard
                    $("#btnSave").css('visibility','hidden');//$("#btnSave").hide();
                }});
                /*
                $dlg.find("#opt_rectypes").change(function(){
                    if($(opt_rectypes).val()>0){
                        if(svs_name.val()=='') 
                            svs_name.val(opt_rectypes.options[opt_rectypes.selectedIndex].text+'s');
                        svs_name.focus();
                    }
                    if(parseInt(that.options.svsID)>0){ //edit
                        that.originalRectypeID=null; 
                        $("#btnSave").css('visibility','hidden');//$("#btnSave").hide();
                    }
                });
                */
            }


            var svsID = this.options.svsID;

            var isEdit = (parseInt(svsID)>0);

            //fill with list of Workgroups in case non bookmark search
            var selObj = svs_ugrid.get(0); //select element
            window.hWin.HEURIST4.ui.createUserGroupsSelect(selObj, null,
                [{key:'bookmark', title:window.hWin.HR('My Bookmarks')}, {key:'all', title:window.hWin.HR('All Records')}],
                function(){
                    svs_ugrid.val(window.hWin.HAPI4.currentUser.ugr_ID);
            });


            if(isEdit){
                
                var svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID];
                svs_id.val(svsID);
                svs_name.val(svs[0]);
                svs_rules.val( this.options.params.rules?this.options.params.rules:'' );
                svs_rules_only.prop('checked', (this.options.params.rulesonly==1 || this.options.params.rulesonly==true));
                svs_filter.val( this.options.params.sup_filter?this.options.params.sup_filter:'' ).trigger('keyup');
                
                this.options.params = $.parseJSON(svs[1]);

                this.options.domain = this.options.params.domain;

                svs_ugrid.val(svs[2]==window.hWin.HAPI4.currentUser.ugr_ID ?this.options.params.domain:svs[2]);
                //svs_ugrid.parent().hide();
                svs_ugrid.attr('disabled','disabled');;
                
                if(this.options.params.rectypes) {
                    this.select_main_rectype.val(this.options.params.rectypes[0]);
                    if(this.select_main_rectype.hSelect("instance")!=undefined){
                       this.select_main_rectype.hSelect("refresh"); 
                    }
                    
                    //$(opt_rectypes).change(function(){});
                    
                    if(this.originalRectypeID==null){//init flag
                        this.originalRectypeID = this.options.params.rectypes[0];    
                    }
                }
                
                
                $dlg.find('#svs_Title').val(this.options.params.ui_title);
                $dlg.find('#svs_ViewMode').val(this.options.params.ui_viewmode);
                $dlg.find('#svs_SearchOnReset').prop('checked', this.options.params.search_on_reset);
                
                $dlg.find('#svs_AdditionalFilter').prop('checked', this.options.params.ui_additional_filter!==false);
                $dlg.find('#svs_AdditionalFilterLabel').val(this.options.params.ui_additional_filter_label);

                $dlg.find('#svs_PrelimFilterToggle').prop('checked', this.options.params.ui_prelim_filter_toggle!==false);
                $dlg.find('#svs_PrelimFilterToggleMode'
                        +(this.options.params.ui_prelim_filter_toggle_mode==1)?'1':'0')
                        .prop('checked', true);
                $dlg.find('#svs_PrelimFilterToggleLabel')
                
                
            }else{ //add new saved search
                this.originalRectypeID == null;

                svs_id.val('');
                svs_name.val('');
                svs_rules.val('');
                svs_rules_only.prop('checked', false);
                svs_filter.val('').trigger('keyup');


                svs_ugrid.val(this.options.domain);
                svs_ugrid.removeAttr('disabled');;
                //svs_ugrid.parent().show();
                $dlg.find('#svs_Title').val('');
                $dlg.find('#svs_SearchOnReset').prop('checked', true);
                
                $dlg.find('#svs_AdditionalFilter').prop('checked', false);
                $dlg.find('#svs_AdditionalFilterLabel').val(window.hWin.HR('Search everything'));
                
                $dlg.find('#svs_PrelimFilterToggle').prop('checked', true);
                $dlg.find('#svs_PrelimFilterToggleMode0').prop('checked', true);
                $dlg.find('#svs_PrelimFilterToggleLabel').val(window.hWin.HR('Apply preliminary filter'));
            }

            this._on($dlg.find('#svs_PrelimFilterToggle'), {change:function( event ){
                if($(event.target).is(':checked') && svs_filter.val()!=''){
                    $dlg.find('#svs_PrelimFilterToggleSettings').show();
                }else{
                    $dlg.find('#svs_PrelimFilterToggleSettings').hide();
                }
            }});
            $dlg.find('#svs_PrelimFilterToggle').change()
            

            this._on($dlg.find('#svs_AdditionalFilter'), {change:function( event ){
                if($(event.target).is(':checked')){
                    $dlg.find('.svs_AdditionalFilter').show();
                }else{
                    $dlg.find('.svs_AdditionalFilter').hide();
                }
            }});
            $dlg.find('#svs_AdditionalFilter').change()
            
            
            
            if(isEdit && this.options.params.rectypes[0]==this.originalRectypeID)
            {
                $("#btnSave").css({'visibility':'visible','margin-left':'20px'});//$("#btnSave").show();
            }else{
                $("#btnSave").css('visibility','hidden');//$("#btnSave").hide();
            }
        
        }
    }

    //
    //
    //
    , _editRules: function(ele_rules) {

        var that = this;

        var url = window.hWin.HAPI4.baseURL+ "hclient/framecontent/ruleBuilderDialog.php?db=" + window.hWin.HAPI4.database;
        if(!Hul.isnull(ele_rules)){
            url = url + '&rules=' + encodeURIComponent(ele_rules.val());
        }

        window.hWin.HEURIST4.msg.showDialog(url, { width:1200, height:600, title:'Ruleset Editor', callback:
            function(res){
                if(!Hul.isempty(res)) {
                    ele_rules.val( JSON.stringify(res.rules) ); //assign new rules
                }
        }});


    }


    // 2d step - init fieldtreeview
    , _initStep2_FieldTreeView: function(rectypeIds){

        if(window.hWin.HEURIST4.util.isArrayNotEmpty(rectypeIds) && this.current_tree_rectype_ids != rectypeIds){
            /*if(!this.options.params.rectypes ||
            !($(rectypeIds).not(this.options.params.rectypes).length == 0 &&
            $(this.options.params.rectypes).not(rectypeIds).length == 0))*/
            {

                var that = this;
                this.options.params.rectypes = rectypeIds;
                var treediv = $(this.step2).find('#field_treeview');
                var rectype;

                if(this.options.params.rectypes){
                    rectype = this.options.params.rectypes.join();
                }
                
                //window.hWin.HEURIST4.util.setDisabled($('#btnNext'),true);

                var allowed_fieldtypes = ['title','modified','enum','freetext',"year","date","integer","float","resource","relmarker"];
                
                var treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 5, rectype, allowed_fieldtypes );
/*                
                //load definitions for given rectypes
                window.hWin.HAPI4.SystemMgr.get_defs({rectypes: rectype,
                    mode:5, //special node - returns data for treeview
                    fieldtypes:allowed_fieldtypes},  //ART20150810 this.options.params.fieldtypes.join() },
                    function(response){

                        if($.isArray(response) || response.status == window.hWin.ResponseStatus.OK){

                            //create unique identificator=code for each leaf fields - rt:ft:rt:ft....
                            if($.isArray(response)){
                                  treedata = response;
                            }else 
                            if(response.data.rectypes) {
                                treedata = response.data.rectypes;
                            }
*/
                            treedata[0].expanded = true; //first expanded
                            
                            if(!treediv.is(':empty')){
                                treediv.fancytree("destroy");
                            }

                            //setTimeout(function(){
                            treediv.addClass('tree-facets').fancytree({
                                //extensions: ["filter"],
                                //            extensions: ["select"],
                                checkbox: true,
                                selectMode: 3,  // hierarchical multi-selection
                                source: treedata,
                                beforeSelect: function(event, data){
                                    // A node is about to be selected: prevent this, for folder-nodes:
                                    if( data.node.hasChildren() ){
                                        return false;
                                    }
                                },
                                lazyLoad: function(event, data){
                                    
                                    var node = data.node;
                                    var parentcode = node.data.code; 
                                    var rectypes = node.data.rt_ids;
                                    
                                    var res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 5, rectypes, allowed_fieldtypes, parentcode );
                                    if(res.length>1){
                                        data.result = res;
                                    }else{
                                        data.result = res[0].children;
                                    }
                                    
                                    return data;                                                   
                                    /* from server
                                    var node = data.node;
                                    var sURL = window.hWin.HAPI4.baseURL + "hsapi/controller/sys_structure.php";
                                    data.result = {
                                        url: sURL,
                                        data: {db:window.hWin.HAPI4.database, mode:5, parentcode:node.data.code, 
                                            rectypes:node.data.rt_ids, fieldtypes:allowed_fieldtypes}
                                    } 
                                    */                                   
                                },
                                expand: function(e, data){
                                    that.showHideReverse();
                                },
                                loadChildren: function(e, data){
                                    setTimeout(function(){
                                        that.showHideReverse();   
                                        that._assignSelectedFacets();
                                    },500);
                                },
                                select: function(e, data) {
                                    /* Get a list of all selected nodes, and convert to a key array:
                                    var selKeys = $.map(data.tree.getSelectedNodes(), function(node){
                                    return node.key;
                                    });
                                    $("#echoSelection3").text(selKeys.join(", "));

                                    // Get a list of all selected TOP nodes
                                    var selRootNodes = data.tree.getSelectedNodes(true);
                                    // ... and convert to a key array:
                                    var selRootKeys = $.map(selRootNodes, function(node){
                                    return node.key;
                                    });
                                    $("#echoSelectionRootKeys3").text(selRootKeys.join(", "));
                                    $("#echoSelectionRoots3").text(selRootNodes.join(", "));
                                    */
                                },
                                click: function(e, data){
                                   if($(e.originalEvent.target).is('span') && data.node.children && data.node.children.length>0){
                                       data.node.setExpanded(!data.node.isExpanded());
                                       //treediv.find('.fancytree-expander').hide();
                                       
                                   }else if( data.node.lazy) {
                                       data.node.setExpanded( true );
                                   }
                                },
                                dblclick: function(e, data) {
                                    data.node.toggleSelected();
                                },
                                keydown: function(e, data) {
                                    if( e.which === 32 ) {
                                        data.node.toggleSelected();
                                        return false;
                                    }
                                }
                                // The following options are only required, if we have more than one tree on one page:
                                //          initId: "treeData",
                                //cookieId: "fancytree-Cb3",
                                //idPrefix: "fancytree-Cb3-"
                            });
                            //},1000);

                            //restore selection
                            var facets;
                            if(that.options.params.facets_new){ //old version
                                facets = JSON.parse(JSON.stringify(that.options.params.facets_new));
                                for(var i=0; i<facets.length; i++){
                                    if(facets[i].code){ //change code to new format (with links direction)
                                        var codes = facets[i].code.split(':');
                                        if(codes.length>2){
                                            var k = 1;
                                            while(k<codes.length-2){
                                                codes[k] = 'lt'+codes[k];
                                                k = k+2;
                                            }
                                            facets[i].code = codes.join(':');
                                        }
                                    }
                                }
                                that.options.params.facets = that.options.params.facets_new;
                            }else{
                                facets = that.options.params.facets;
                            }

                            that._assignSelectedFacets();

                            //hide all folder triangles
                            //treediv.find('.fancytree-expander').hide();

                            that.current_tree_rectype_ids = rectypeIds;

                            $("#fsw_showreverse").change(function(event){

                                that.showHideReverse();
                                /*
                                var showrev = $(event.target).is(":checked");
                                var tree = treediv.fancytree("getTree");
                                tree.visit(function(node){
                                    if(node.data.isreverse==1){ //  window.hWin.HEURIST4.util.isArrayNotEmpty(node.children) &&
                                                if(showrev===true){
                                                    $(node.li).removeClass('fancytree-hidden');
                                                }else{
                                                    $(node.li).addClass('fancytree-hidden');
                                                }
                                    }
                                });*/
                            });

                            //tree.options.filter.mode = "hide";
                            //tree.options.filter.highlight = false;
                            $("#fsw_showreverse").attr('checked', false);
                            $("#fsw_showreverse").change();

/* server response
                        }else{
                            window.hWin.HEURIST4.msg.redirectToError(response.message);
                        }
                        window.hWin.HEURIST4.util.setDisabled($('#btnNext'), false);
                });
                */

            }
        }
    }
    
    , showHideReverse: function(){
        
        var showrev = $('#fsw_showreverse').is(":checked");
        var treediv = $('#field_treeview');
        var tree = treediv.fancytree("getTree");
        tree.visit(function(node){

            if(node.data.isreverse==1){ 

                if(showrev===true){
                    $(node.li).removeClass('fancytree-hidden');
                }else{
                    $(node.li).addClass('fancytree-hidden');
                }
            }
        });
    }
    
    //restore selection in treeview
    , _assignSelectedFacets: function(){
          
        var treediv = $(this.step2).find('#field_treeview');
        var facets = this.options.params.facets;
       
                            var tree = treediv.fancytree("getTree");

                            if(facets && facets.length>0){
                                tree.visit(function(node){
                                    if(!window.hWin.HEURIST4.util.isArrayNotEmpty(node.children)){ //this is leaf
                                        //find it among facets
                                        for(var i=0; i<facets.length; i++){
                                            if(facets[i].code && facets[i].code==node.data.code){
                                                node.setSelected(true);
                                                break;
                                            }
                                        }
                                    }
                                });
                            }
        
    }

    , _findFacetByCode: function(code){
        if( window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.params.facets)){

            var k, len = this.options.params.facets.length;
            for (k=0;k<len;k++){
                var facet =  this.options.params.facets[k];
                if(facet.code == code){
                    return facet;
                }
            }
        }
        return null;
    }

    //
    // 3d step  - define individual setting for facets
    //
    , _initStep3_FacetsSettings: function() {

        var facets = [];
        var facets_new = [];
        var k, len = this.options.params.rectypes.length;
        var isOneRoot = true;

        // ------------------------------------------------------
        var tree = $(this.step2).find('#field_treeview').fancytree("getTree");
        var fieldIds = tree.getSelectedNodes(false);
        len = fieldIds.length;

        facets = [];
        
        function __getRandomInt() {
            var min = 0;
            var max =  100000;
            min = Math.ceil(min);
            max = Math.floor(max);
            return Math.floor(Math.random() * (max - min)) + min; //The maximum is exclusive and the minimum is inclusive
        }        
        
        //first scan current facets and add to selection fields that are not found in tree 
        // it means they are not loaded
        var old_facets = this.options.params.facets;
        if(old_facets)
        for (k=0;k<old_facets.length;k++){
            
            var code = old_facets[k]['code'];
            
            var isfound = false;
            tree.visit(function(node){
                if(!window.hWin.HEURIST4.util.isArrayNotEmpty(node.children)){ //this is leaf
                        if(code && code==node.data.code){
                            isfound = true; //exists = loaded
                            return false;
                        }
                }
            });//visit
            if(!isfound){ //assume that they not loaded with last load
                facets.push(old_facets[k]); 
            }
        }

        if(len>0 || facets.length>0){
            
            var order_for_new  = old_facets?old_facets.length:0;

            for (k=0;k<len;k++){
                var node =  fieldIds[k];      //FancytreeNode
                //name, type, query,  ranges
                if(!window.hWin.HEURIST4.util.isArrayNotEmpty(node.children)){  //ignore top levels selection

                    var ids = node.data.code.split(":");
                    //rtid: ids[ids.length-2],
                    //id:  ids[ids.length-1],

                    //if facets are already defined try to restore title,help and type from previous
                    var old_facet = this._findFacetByCode(node.data.code);
                    if(old_facet!=null){

                        facets.push( {
                            'var': __getRandomInt(), //unique identificator
                            code:node.data.code,
                            title: old_facet.title,
                            help: old_facet.help,
                            isfacet: old_facet.isfacet,
                            groupby: old_facet.groupby,
                            type: node.data.type,
                            order: old_facet.order>=0?old_facet.order:order_for_new
                        } );

                        if(!(old_facet.order>=0)) order_for_new++;
                        
                    }else{

                        facets.push( {
                            'var': __getRandomInt(),
                            code:node.data.code,
                            title:'{NEW}', //(node.data.name?node.data.name:node.title),
                            groupby: null,
                            type:node.data.type,
                            order: order_for_new
                        } );
                        
                        order_for_new++;
                    }
                }
            }
            
            
            facets.sort(function(a,b){
                return a.order<b.order?-1:1;
            });

            if(len>0){
                //facets[0].isfacet = true;
            }

            //-----------------------------------------------------------
            $(this.step3).find("#cbShowHierarchy").attr('checked', this.options.params.title_hierarchy==true);
            $(this.step3).find("#selViewportLimit").val(this.options.params.viewport);
            
            var listdiv = $(this.step3).find("#facets_list");
            listdiv.empty();

            var dispname_idx = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_DisplayName'];
            var allterms_idx = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex['dty_JsonTermIDTree'];
            var disterms_idx = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex['dty_TermIDTreeNonSelectableIDs'];

            len = facets.length;
            k = 0;
            while(k<facets.length){
                //title
                //help tip (take from rectype structure?)
                //type of facet (radio group)
                var removeFacet = false;
                var harchy = [];
                var harchy_fields = []; //for facet.title
                var codes = facets[k]['code'].split(':');
                var j = 0;
                while(j<codes.length){
                    var rtid = codes[j];
                    var dtid = codes[j+1];
                    
                    if(!window.hWin.HEURIST4.rectypes.typedefs[ rtid ]){
                        //record type was removed - remove facet
                        removeFacet = true;
                        break;
                    }
                    
                    harchy.push('<b>'+window.hWin.HEURIST4.rectypes.names[ rtid ]+'</b>');
                    
                    if(j==0 && dtid=='title'){
                       harchy_fields.push(window.hWin.HEURIST4.rectypes.names[ rtid ]);  
                    }else
                    if(dtid=='modified'){
                       harchy_fields.push("Modified"); 
                    }
                    
                    var linktype = dtid.substr(0,2);                                
                    if(isNaN(Number(linktype))){
                        dtid = dtid.substr(2);
                        
                        if(dtid>0){
                        
                            
                        if(linktype=='lt' || linktype=='rt'){
                            
                            if(!window.hWin.HEURIST4.rectypes.typedefs[ rtid ].dtFields[dtid]){
                                //field was removed - remove facet
                                removeFacet = true;
                                break;
                            }
                            
                            harchy.push(' . '+window.hWin.HEURIST4.rectypes.typedefs[rtid].dtFields[dtid][dispname_idx]+' &gt ');
                            harchy_fields.push(window.hWin.HEURIST4.rectypes.typedefs[rtid].dtFields[dtid][dispname_idx]);
                        }else{
                            var from_rtid = codes[j+2];
                            
                            if(!window.hWin.HEURIST4.rectypes.typedefs[ from_rtid ].dtFields[dtid]){
                                //field was removed - remove facet
                                removeFacet = true;
                                break;
                            }
                            
                            harchy.push(' &lt '+
                            (window.hWin.HEURIST4.rectypes.typedefs[from_rtid]
                                ?window.hWin.HEURIST4.rectypes.typedefs[from_rtid].dtFields[dtid][dispname_idx]+' . '
                                :'') );
                        }
                        
                        }
                        
                    }else{
                        
                        if(!window.hWin.HEURIST4.rectypes.typedefs[ rtid ].dtFields[dtid]){
                            //field was removed - remove facet
                            removeFacet = true;
                            break;
                        }
                        
                        //harchy.push(window.hWin.HEURIST4.detailtypes.names[ dtid ]);    
                        harchy.push(' . '+window.hWin.HEURIST4.rectypes.typedefs[rtid].dtFields[dtid][dispname_idx]);
                        harchy_fields.push(window.hWin.HEURIST4.rectypes.typedefs[rtid].dtFields[dtid][dispname_idx]);
                    }
                    j = j+2;
                }//while codes
                
                if(removeFacet){
                    facets.splice(k,1);
                    continue;
                }
                
                
                /*
                var j = 0;
                while (j<codes.length){
                    if(j % 2 ==0){
                        harchy.push(window.hWin.HEURIST4.rectypes.names[codes[j]]);
                    }else{
                        harchy.push(window.hWin.HEURIST4.detailtypes.names[codes[j]]);    
                    }
                    j++;
                }*/  
                
                var idd = facets[k]['var'];
                
                var sContent =
                '<div id="facet'+idd+'" style="border-top:1px lightgray solid; padding-top:4px">'
                +'<div><span class="ui-icon ui-icon-up-down span_for_radio"></span><label>Facet </label>&nbsp;'
                +'<label style="font-size:smaller">' + harchy.join('') + '</label></div>'
                
                +'<div style="padding:5px 0 5px 29px">'
                +'<label style="font-size:smaller" for="facet_Title'+idd+'">Label</label>&nbsp;'   //'<div class="header_narrow"></div>'
                +'<input type="text" name="facet_Title'+idd+'" id="facet_Title'+idd+'" '
                +' style="font-weight:bold;display:inline-block" class="text ui-widget-content ui-corner-all" />'
                
                + '<div class="ent_search_cb" style="float:right;font-style:italic;padding-right:6px">'
                + '<span id="buttonset'+idd+'">';
                
                
                var sGroupBy = '';
                if(facets[k].type=='freetext'){
                    sGroupBy =
                        '<label><input type="checkbox" name="facet_Group'+idd+'" value="firstchar"/>'
                        +'Group by first character</label>';
                   
                }else if(facets[k].type=='float' || facets[k].type=='integer'){
                    sContent = sContent 
                        //+'<input type="radio" data-idx="'+idd+'" id="facetType'+idd+'_1" name="facet_Type'
                        //+idd+'" value="1" data-type="slider"/><label for="facetType'+idd+'_1">slider</label>';
                        +'<button label="slider" class="btnset_radio" data-idx="'+idd+'" data-value="1" data-type="slider"/>';
                    
                }else if(facets[k].type=='date' || facets[k].type=='year'){
                    sContent = sContent 
                        //+'<input type="radio" data-idx="'+idd+'" id="facetType'+idd+'_1" name="facet_Type'
                        //+idd+'" value="1" data-type="slider"/><label for="facetType'+idd+'_1">slider</label>';
                        +'<button label="slider" class="btnset_radio" data-idx="'+idd+'" data-value="1" data-type="slider"/>';
                    
                    sGroupBy = '<span id="facet_DateGroup'+idd+'"><label>Group by '
                        +'<select id="facet_Group'+idd+'"><option>year</option><option>decade</option><option>century</option></select>'
                        +'</label></span>';
                    
                }else if(facets[k].type=='enum' || facets[k].type=='relationtype'){
                    sContent = sContent 
                        //+'<input type="radio" data-idx="'+idd+'" id="facetType'+idd+'_1" name="facet_Type'
                        //+idd+'" value="1" data-type="dropdown"/><label for="facetType'+idd+'_1">dropdown</label>';
                        +'<button label="dropdown" class="btnset_radio" data-idx="'+idd+'" data-value="1" data-type="dropdown"/>';
                    
                    sGroupBy = '<label>'
                                            +'<input type="checkbox" name="facet_Group'+idd+'" value="firstlevel"/>Group by first level</label>';
                    
                }
                
                sContent = sContent
                        +'<button label="list" class="btnset_radio" data-idx="'+idd+'" data-value="3"/>'
                        +'<button label="wrapped" class="btnset_radio" data-idx="'+idd+'" data-value="2"/>'
                        +'<button label="search" class="btnset_radio" data-idx="'+idd+'" data-value="0"/>'
                //+'<input type="radio" data-idx="'+idd+'" id="facetType'+idd+'_3" name="facet_Type'+idd+'" value="3"/><label for="facetType'+idd+'_3">list</label>'
                //+'<input type="radio" data-idx="'+idd+'" id="facetType'+idd+'_2" name="facet_Type'+idd+'" value="2"/><label for="facetType'+idd+'_2">wrapped</label>'
                //+'<input type="radio" data-idx="'+idd+'" id="facetType'+idd+'_0" name="facet_Type'+idd+'" value="0"/><label for="facetType'+idd+'_0">search</label>'
                + '</span></div></div>' 

                //second/optional line
                +'<div style="padding:5px 0 0 29px;display:none" class="optional_settings">'
                +'<div style="display:inline-block">'
                +'<label style="font-size:smaller" for="facet_Help'+idd+'">Rollover (optional)&nbsp;</label>'
                +'<input name="facet_Help'+idd+'" id="facet_Help'+idd+'" type="text" '
                +' class="text ui-widget-content ui-corner-all"'
                +' style="font-size:smaller;margin-top:0.4em;margin-bottom:1.0em;width:200px;"/>'
                +'</div>'
                
                + '<div style="float:right;font-size:smaller;margin-right:20px;margin-top: 4px;">'
                + sGroupBy 
                +'<label><input type="checkbox" id="facet_Order'    // style="float:right;font-size:smaller;"
                + idd+'" style="vertical-align: middle;margin-left:16px">'
                + window.hWin.HR("Order by count")+"</label>"
                + '</div>'
                + '</div>'
                + '</div>'; 
                

                listdiv.append($(sContent));

                //backward capability
                if(facets[k].isfacet==false){
                    facets[k].isfacet = 0;
                }else if(facets[k].isfacet==true || !(Number(facets[k].isfacet)<4 && Number(facets[k].isfacet)>=0)){
                    //by default column for text and selector/slider for dates
                    //for text field default is search for others slider/dropdown
                    if(facets[k].type=='enum' || facets[k].type=='relationtype'){
                        
                        var allTerms = window.hWin.HEURIST4.detailtypes.typedefs[dtid].commonFields[allterms_idx];
                        var disTerms = window.hWin.HEURIST4.detailtypes.typedefs[dtid].commonFields[disterms_idx];
                        var list = window.hWin.HEURIST4.dbs.getPlainTermsList(facets[k].type, allTerms, disTerms);
                        
                        if(list.length<5){
                            facets[k].isfacet = 2; //wrap 
                        }else if (list.length<25) {
                            facets[k].isfacet = 3; //list
                        }else{
                            facets[k].isfacet = 1;    
                        }
                    }else{
                        facets[k].isfacet = (facets[k].type=='freetext')?0:1;
                    }
                    
                    
                    
                }


                //assign values
                if(facets[k].title=='{NEW}'){
                    var l = harchy_fields.length;
                    if(l==1)
                        facets[k].title = harchy_fields[0]
                    else
                        facets[k].title = harchy_fields[l-2]+'>'+harchy_fields[l-1]; 
                }
                listdiv.find('#facet_Title'+idd).val(facets[k].title);
                listdiv.find('#facet_Help'+idd).val(facets[k].help);
                
                listdiv.find('#facet_Order'+idd).attr('checked', (facets[k].orderby=='count'));

                //listdiv.find('input:radio[name="facet_Type'+idd+'"][value="'+facets[k].isfacet+'"]').attr('checked', true);
                listdiv.find('button.btnset_radio[data-idx="'+idd+'"]').removeClass('ui-heurist-btn-header1');
                var btn =   listdiv.find('button.btnset_radio[data-idx="'+idd+'"][data-value="'+facets[k].isfacet+'"]');
                btn.addClass('ui-heurist-btn-header1');                


                    function __dateGrouping(idd){
                        
                            var idx = -1;
                            for(var m=0; m<facets.length; m++){
                                if(facets[m]['var']==idd){
                                    idx = m;
                                    break;
                                }
                            }
                            if(idx>=0){
                                if(facets[idx].type=='date' || facets[idx].type=='year'){
                                    var is_allowed = (listdiv.find('button.ui-heurist-btn-header1[data-idx="'+idd+'"]').attr('data-value')>1);
                                                    //(listdiv.find('input:radio[name="facet_Type'+idd+'"]:checked').val()>1);
                                    listdiv.find('#facet_DateGroup'+idd).css({'visibility':is_allowed?'visible':'hidden'});        
                                    if(is_allowed){
                                        if(Hul.isempty(facets[idx].groupby)){
                                                                                facets[idx].groupby = 'year';
                                        }
                                        listdiv.find('#facet_Group'+idd).val(facets[idx].groupby);
                                    }
                                }
                            }else{
                                console.log('facet not found '+idd);
                            }
                    }

                
                this._on( listdiv.find('button.btnset_radio'), {
                    click: function(event) {
                        var btn = $(event.target);
                        if(!btn.is('button')){ btn = btn.parent('button'); }
                        var view_mode = btn.attr('data-value');
                        
                        var idd = btn.attr('data-idx');

                        listdiv.find('button.btnset_radio[data-idx="'+idd+'"]').removeClass('ui-heurist-btn-header1');
                        var btn =   listdiv.find('button.btnset_radio[data-idx="'+idd+'"][data-value="'+view_mode+'"]');
                        btn.addClass('ui-heurist-btn-header1');
                        
                        __dateGrouping(idd);
                        this._refresh_FacetsPreview();
                        
                }});
                
                
                
                if(facets[k].type=='date' || facets[k].type=='year'){
                    __dateGrouping(idd);
                    
                    /*
                    listdiv.find('input:radio[name="facet_Type'+idd+'"]').click(
                        function( event ){
                            var idd = $(event.target).attr('data-idx');
                            __dateGrouping(idd);
                        }                    
                    )
                    */
                }else{
                    listdiv.find('input:checkbox[name="facet_Group'+idd+'"][value="'+facets[k].groupby+'"]').attr('checked', true);
                }
                
                k++;
            }//while facets
           
            this.options.params.facets = facets;   //assign new selection
            this.options.params.version = 2;
           
            var that = this;
            listdiv.sortable({stop: function( event, ui ) { that._refresh_FacetsPreview() } });
            listdiv.disableSelection();


            /*            
            listdiv.find('input:radio[value="3"]')                                           
                            .button({icons: { secondary: 'ui-icon-list-column' }});
            listdiv.find('input:radio[value="2"]')
                            .button({icons: { secondary: 'ui-icon-list-inline' }});
            listdiv.find('input:radio[value="0"]')
                            .button({icons: { secondary: 'ui-icon-search' }});
            listdiv.find('input:radio[data-type="slider"]')
                            .button({icons: { secondary: 'ui-icon-input-slider' }});
            listdiv.find('input:radio[data-type="dropdown"]')
                            .button({icons: { secondary: 'ui-icon-input-dropdown' }});
                            
            listdiv.find('input:radio').button({text:true});
            listdiv.find('.ui-button-text').css({"min-width":"60px","font-size":'0.9em'});
            */
            
            listdiv.find('button[data-value="3"]').button({icon: "ui-icon-list-column",iconPosition:'end',showLabel:true,label:'list'});
            listdiv.find('button[data-value="2"]').button({icon: "ui-icon-list-inline",iconPosition:'end',showLabel:true,label:'wrapped'});
            listdiv.find('button[data-value="0"]').button({icon: "ui-icon-search",iconPosition:'end',showLabel:true,label:'search'});
            listdiv.find('button[data-type="slider"]').button({icon: "ui-icon-input-slider",iconPosition:'end',showLabel:true,label:'slider'});
            listdiv.find('button[data-type="dropdown"]').button({icon: "ui-icon-input-dropdown",iconPosition:'end',showLabel:true,label:'dropdown'});
            listdiv.find('.ui-button-text').css({"min-width":"60px","font-size":'0.9em'});
            listdiv.find('button.btnset_radio[data-idx="'+idd+'"]').controlgroup();
                                      
            $(this.step3).find('#cbShowAdvanced').attr('checked',false).change(function(event){
                if($(event.target).is(':checked')){
                     listdiv.find('.optional_settings').show();
                }else{
                     listdiv.find('.optional_settings').hide();                    
                }
            });
            
            this._on( listdiv.find('input[id^="facet_Title"]'), {change: this._refresh_FacetsPreview});
            this._on( listdiv.find('input[id^="facet_Help"]'), {change: this._refresh_FacetsPreview});
            this._on( listdiv.find('input[name^="facet_Group"]'), {change: this._refresh_FacetsPreview});
            this._on( listdiv.find('input[id^="facet_Group"]'), {change: this._refresh_FacetsPreview});
            this._on( listdiv.find('input[id^="facet_Order"]'), {change: this._refresh_FacetsPreview});
            this._on( $(this.step3).find('#cbShowHierarchy'), {change: this._refresh_FacetsPreview});
            
            
            return true;
        }else{
            return false;
        }
    }

    //
    // from UI to options.params
    //
    , _assignFacetParams: function(){
        if( window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.params.facets)){

            this.options.params.title_hierarchy  = $(this.step3).find("#cbShowHierarchy").is(':checked');
            this.options.params.viewport  = $(this.step3).find("#selViewportLimit").val();
            
            var listdiv = $(this.step3).find("#facets_list");

            var k, len = this.options.params.facets.length;
            for (k=0;k<len;k++){
                var idd = this.options.params.facets[k]['var'];
                var title = listdiv.find('#facet_Title'+idd).val();
                if(title!='') this.options.params.facets[k].title = title;
                this.options.params.facets[k].help = listdiv.find('#facet_Help'+idd).val();
                this.options.params.facets[k].isfacet = listdiv.find('button.ui-heurist-btn-header1[data-idx="'+idd+'"]').attr('data-value');
                                //was  listdiv.find('input:radio[name="facet_Type'+idd+'"]:checked').val();
                this.options.params.facets[k].orderby = listdiv.find('#facet_Order'+idd).is(':checked')?'count':null;
                
                if(this.options.params.facets[k].type=='date' 
                  || this.options.params.facets[k].type=='year'){
                    if(this.options.params.facets[k].isfacet>1){
                        this.options.params.facets[k].groupby = listdiv.find('#facet_Group'+idd).val();
                    }else{
                        this.options.params.facets[k].groupby = null;    
                    }
                    
                }else{
                    this.options.params.facets[k].groupby = listdiv.find('input:checkbox[name="facet_Group'+idd+'"]:checked').val();    
                }
                
                this.options.params.facets[k]['order'] = $('#facet'+idd).index();

            }
            //sort according to order in UI list
            
            this.options.params.facets.sort(function(a,b){
                return a['order']<b['order']?-1:1;
            });
        }
        return null;
    }
    
    ,_refresh_FacetsPreview: function(){
        
        this._assignFacetParams();
        this._defineDomain();
        var listdiv = $(this.step3).find("#facets_preview2");

        var noptions= { query_name:"test", params: JSON.parse(JSON.stringify(this.options.params)), ispreview: true}

        if(listdiv.html()==''){ //not created yet
            listdiv.search_faceted( noptions );
        }else{
            listdiv.search_faceted('option', noptions ); //assign new parameters
        }
    }

    //4. show facet search preview
    ,_initStep4_FacetsPreview: function(){

        this._defineDomain();
        var listdiv = $(this.step4).find("#facets_preview");

        var noptions= { query_name:"test", params: JSON.parse(JSON.stringify(this.options.params)), ispreview: true}

        if(listdiv.html()==''){ //not created yet
            listdiv.search_faceted( noptions );
        }else{
            listdiv.search_faceted('option', noptions ); //assign new parameters
        }

    }

    ,_defineDomain: function(){

        var svs_ugrid = this.step0.find('#svs_UGrpID');
        var svs_ugrid = svs_ugrid.val();
        if(parseInt(svs_ugrid)>0){
            this.options.params.domain = 'all';
        }else{
            this.options.params.domain = svs_ugrid;
        }
    }

    //
    // save into database
    //
    ,_doSaveSearch:function(){

        var $dlg = this.step0;

        var svs_id = $dlg.find('#svs_ID');
        var svs_name = $dlg.find('#svs_Name');
        var svs_ugrid = $dlg.find('#svs_UGrpID');
        var svs_rules = $dlg.find('#svs_Rules');
        var svs_rules_only = $dlg.find('#svs_RulesOnly');
        var svs_filter = $dlg.find('#svs_Query');
        var message = $dlg.find('.messages');

        var allFields = $dlg.find('input');
        allFields.removeClass( "ui-state-error" );

        var bValid = window.hWin.HEURIST4.msg.checkLength( svs_name, "Name", message, 3, 64 );
        if(!bValid){
            this._showStep(0);
            return false;
        }

        var bValid = window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.params.facets);
        if(!bValid){
            this._showStep(2);
            return false;
        }

        if(true || !Hul.isempty(svs_rules.val())){
            this.options.params.rules = svs_rules.val();
            this.options.params.rulesonly = svs_rules_only.is(':checked')?1:0;
        }else{
            this.options.params.rules = null;
            this.options.params.rulesonly = null;
        }
        if(true || !Hul.isempty(svs_filter.val())){
            this.options.params.sup_filter = svs_filter.val();
        }

        this.options.params.ui_title =  $dlg.find('#svs_Title').val();
        this.options.params.ui_viewmode = $dlg.find('#svs_ViewMode').val();
        this.options.params.search_on_reset = $dlg.find('#svs_SearchOnReset').is(':checked');
         
        this.options.params.ui_prelim_filter_toggle = $dlg.find('#svs_PrelimFilterToggle').is(':checked');
        this.options.params.ui_prelim_filter_toggle_mode = $dlg.find('#svs_PrelimFilterToggleMode0').is(':checked')?0:1;
        this.options.params.ui_prelim_filter_toggle_label =$dlg.find('#svs_PrelimFilterToggleLabel').val();
        
        this.options.params.ui_additional_filter = $dlg.find('#svs_AdditionalFilter').is(':checked');
        this.options.params.ui_additional_filter_label =$dlg.find('#svs_AdditionalFilterLabel').val();

        var svs_ugrid = svs_ugrid.val();
        if(parseInt(svs_ugrid)>0){
            this.options.params.domain = 'all';
        }else{
            this.options.params.domain = svs_ugrid;
            svs_ugrid = window.hWin.HAPI4.currentUser.ugr_ID;
        }

        var request = {svs_Name: svs_name.val(),
            svs_Query: JSON.stringify(this.options.params),   //$.toJSON
            svs_UGrpID: svs_ugrid,
            domain:this.options.params.domain};

        var isEdit = ( parseInt(svs_id.val()) > 0 );

        if(isEdit){
            request.svs_ID = svs_id.val();
        }

        var that = this;
        //
        window.hWin.HAPI4.SystemMgr.ssearch_save(request,
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){

                    var svsID = response.data;

                    if(!window.hWin.HAPI4.currentUser.usr_SavedSearch){
                        window.hWin.HAPI4.currentUser.usr_SavedSearch = {};
                    }

                    window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID] = [request.svs_Name, request.svs_Query, request.svs_UGrpID];

                    request.new_svs_ID = svsID;
                    request.isNewSavedFilter  = !isEdit;

                    that._trigger( "onsave", null, request );

                    that.element.dialog("close");

                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response, true);
                }
            }

        );


    }

});

function showSearchFacetedWizard( params ){

    if(!$.isFunction($('body').fancytree)){

        $.getScript(window.hWin.HAPI4.baseURL+'external/jquery.fancytree/jquery.fancytree-all.min.js', 
                function(){ showSearchFacetedWizard(params); } );

    }else{

        var manage_dlg = $('#heurist-search-faceted-dialog');

        if(manage_dlg.length<1){

            manage_dlg = $('<div id="heurist-search-faceted-dialog">')
            .appendTo( $('body') );
            manage_dlg.search_faceted_wiz( params );
        }else{
            manage_dlg.search_faceted_wiz('option', params);
        }

        manage_dlg.search_faceted_wiz( "show" );
    }
}
