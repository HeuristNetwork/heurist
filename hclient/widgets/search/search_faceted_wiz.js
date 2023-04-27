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
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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
step3 - define label, help prompt and filter type (by first letter, by full name, directly) + preview
*/
$.widget( "heurist.search_faceted_wiz", {

    // default options
    options: {
        is_h6style: false,
        svsID: null,
        domain: null, // bookmark|all or usergroup ID
        is_modal: true,
        menu_locked: null,
        params: {
            viewport:5
        },
        onsave: null
    },
    isversion:2,
    is_edit_continuing: false,
    _lock_mouseleave: false,
    _save_in_porgress: false,
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
    select_additional_rectypes: null,
    svs_MultiRtSearch: null,

    step: 0, //current step
    step_panels:[],
    current_tree_rectype_ids:null,
    originalRectypeID:null, //flag that allows to save on first page for edit mode
    
    facetPreview_reccount:0, 

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

        //this.options.is_h6style = (window.hWin.HAPI4.sysinfo['layout']=='H6Default');

        this._dialog = this.element.dialog({
            autoOpen: false,
            height: 400,
            width: 650,
            modal: this.options.is_modal,

            resizable: true, //!is_h6style,
            draggable: this.options.is_modal, //!this.options.is_h6style,
            //position: this.options.position,
            
            title: window.hWin.HR('Facets builder'),
            resizeStop: function( event, ui ) {//fix bug
                    var pele = that.element.parents('div[role="dialog"]');
                    that.element.css({overflow: 'none !important', 'width':pele.width()-24 });
            },
            
            beforeClose: function( event, ui ) {
                if(event && event.currentTarget){
                    var that_dlg = this;
                    if($( that_dlg ).dialog( 'option', 'modal' )){
                        window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR("Discard changes?"),
                            function(){ $( that_dlg ).dialog( "close" ); });
                        return false;
                    }
                }
            },
            buttons: [
                {text:window.hWin.HR('Back'), id:'btnBack',
                    click: function() {
                        that.navigateWizard(-1);
                }},
                {text:window.hWin.HR('Next'), id:'btnNext',
                    class:'ui-button-action', 
                    click: function() {
                        that.navigateWizard(1);
                }},
                {text:window.hWin.HR('Save'), id:'btnSave',
                    class:'ui-button-action', 
                    click: function() {
                        that._doSaveSearch()
                        //that.navigateWizard(1);
                }},
            ],
            show: {
                effect: 'fade',
                duration: 500
            }
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
            window.hWin.HR("Show linked-from record types (reverse pointers, indicated as &lt;&lt;)")+"</label>"+
            // Get usages
            "<span id='get_usages'></span><br>"+
            // Tree order options
            "<label for='order_alphabetic'><input type='radio' name='tree_order' id='order_alphabetic' style='vertical-align: middle;' value='1' />&nbsp;Alphabetic</label>"+
            "<label for='order_default'><input type='radio' name='tree_order' id='order_default' style='vertical-align: middle;' value='0' checked />&nbsp;Form order</label>"+
            "<br><br>"+
            // Check all visible options
            "<label id='selectAll_container' style='font-size: 11px;position: relative;top: 10px;left: 20px;'>"+
            "<input type='checkbox' id='selectAll'>Select All Visible Options</label>");

        $("<div>",{id:'field_treeview'}).appendTo(this.step2);
        this.step_panels.push(this.step2);


        //ranges
        this.step3 = $("<div>").addClass('step3')
        .css({width:'100% !important', 'display':'none'})
        .appendTo(this.element);

        var div_leftside = $("<div>").css({position:'absolute',top:0,bottom:0,left:0,right:'301px',overflow:'hidden auto'}).appendTo(this.step3);
        
        var header = $("<div>").css({'font-size':'0.8em','padding':'4px 10px'}).appendTo(div_leftside);
        header.html("<label>"+window.hWin.HR("Define titles, help tips and facet type")+"</label>"
        +'<br><br><label><input type="checkbox" id="cbShowHierarchy" style="vertical-align: middle;">'
            +window.hWin.HR("Show entity hierarchy above facet label")+"</label>"
            +'<label style="margin-left:16px" for="selViewportLimit">'+window.hWin.HR("Limit lists initially to")+'</label>'
            +'&nbsp;<select id="selViewportLimit"><option value=0>All</option><option value=5>5</option><option value=10>10</option>'
            +'<option value=20>20</option><option value=50>50</option></select>'
            
            +'<span style="float:right; margin-left:10px;display:none;" id="btnUpdatePreview">Update Preview</span>'
            +'<div style="float:right"><label><input type="checkbox" id="cbShowAdvanced" style="vertical-align: middle;">'
            +window.hWin.HR("All options ")+'</label></div>'
            +'<label style="margin-left:16px;"><input type="checkbox" id="cbAccordionView" style="vetical-align: middle;">'+window.hWin.HR("Accordion view")+'</label>'
            +'<label style="display:none;margin-left:16px;"><input type="checkbox" id="cbShowAccordIcons" style="vetical-align: middle;" checked>'+window.hWin.HR("Show accordion arrows")+'</label>'
            );
            
        $("<div>",{id:'facets_list'}).css({'overflow-y':'auto','padding':'10px 10px 10px 0px',
                'min-width':'670px',width:'100%'}).appendTo(div_leftside); //fieldset
        
        $("<div>",{id:'facets_preview2'})
        .css({position:'absolute',top:0,bottom:0,right:0,width:'300px', 'border-left':'1px solid lightgray'})
        .appendTo(this.step3);
        this.step_panels.push(this.step3);

        this._on($(this.step3).find('#selViewportLimit'),{change:this._refresh_FacetsPreview});
        
        this._on($(this.step3).find('#btnUpdatePreview').button({icon:'ui-icon-carat-2-e', iconPosition:'end'})
                    .css({'opacity':0.5, background:'#f38989'}).show()
                , {click:this._refresh_FacetsPreviewReal});
        
        this._on($(this.step2).find('#selectAll'), {
            click: function(e){
                var treediv = that.element.find('#field_treeview');

                var check_status = $(e.target).is(":checked");

                if(!treediv.is(':empty') && treediv.fancytree("instance")){
                    var tree = treediv.fancytree("getTree");
                    tree.visit(function(node){
                        if(!node.hasChildren() && node.data.type != "relmarker" && node.data.type != "resource" && node.data.type != "separator"
                            && (node.getLevel()==2 || (!window.hWin.HEURIST4.util.isempty(node.span) && $(node.span.parentNode.parentNode).is(":visible")))
                        ){    
                            node.setSelected(check_status);
                        }
                    });
                }
            }
        });
        
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
            this.select_main_rectype = null;
            
            this.select_additional_rectypes.remove();   
            this.select_additional_rectypes = null;
        }
        
        
        // remove generated elements
        this.step0.remove();
        this.step1.remove();
        this.step2.remove();
        this.step3.remove();
    }
    
    //
    //
    //
    , adjustDimension: function(){

        var ch =   this._dialog[0].scrollHeight+100; 
        var width = this._dialog.dialog( 'option', 'width');
        if(this.step==3){
            minw = 990;
        }else if(this.step==0){
            
            var $dlg = this.step0.find("#facets_options");
            minw = $dlg.find( ".ui-accordion-content-active" ).length>0?800:650;
            
            ch = Math.max($dlg.height(),$dlg[0].scrollHeight)+100; 
            if(ch<400) ch = 400;
            
        }else{
            minw = 650;
        }
        //if(width<minw) 
        this._dialog.dialog( 'option', 'width', minw);
        
        var topPos = 0;
        var pos = this._dialog.dialog('option', 'position');
        if(pos && pos.of && !(pos.of instanceof Window)){
            var offset = $(pos.of).offset();
            if(offset) topPos = offset.top+40;
        }

        var dh =  this._dialog.dialog('option', 'height');

        var ht = Math.min(ch, window.innerHeight-topPos);
        this._dialog.dialog('option', 'height', ht);    
        
        /*
        if(this.options.is_h6style){
            //var dialog_height = window.innerHeight - this.element.parent().position().top - 5;
            //this.element.dialog( 'option', 'height', dialog_height);
        }else{
            var ht = window.innerHeight; //$(window).height();
            if(ht>700) ht = 700;
            this.element.dialog( 'option', 'height',  ht );
        }
        */
    }

    ,show: function( ){
        this.current_tree_rectype_ids = null;
        
        this.element.dialog( 'option', 'modal', this.options.is_modal);
        this.element.dialog( 'option', 'draggable', this.options.is_modal);
        
        if(this.options.position!=null){
            this._dialog.dialog( 'option', 'position', this.options.position );   
        }
        this._dialog.dialog('open');                           
        
        if(this.options.is_modal || !this.is_edit_continuing){
            
            if(!(this.options.is_modal && this.options.svsID>0) || !this.is_edit_continuing){
                this.options.params = {};
            }
            
            this.is_edit_continuing = !this.options.is_modal;
            this.step1.hide();
            this.step2.hide();
            this.step3.hide();
            this.navigateWizard(NaN); //init for 0 step
        }
        
        if(this.options.is_h6style){
            this.element.parent().addClass('ui-heurist-explore');
            this.adjustDimension();
        }
        
        if($.isFunction(this.options.menu_locked)){
            
            this._on(this.element.parent('.ui-dialog'), {
                        mouseover:function(){ this.options.menu_locked.call( this, false, false );},  //just prevent close
                        mouseleave: function(e){ 
                            this.options.menu_locked.call( this, false, true ); //close after delay
                        }}); //that.closeEditDialog();
            
        }else{
            
            this._off(this.element.parent('.ui-dialog'), 'mouseover mouseleave');
        }
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
                        
                        $dlg.find( ".optional_fields" ).accordion({collapsible:true, active:false, animate: false});
                        //, activate:function(){that.adjustDimension();}}
                        that._on($dlg.find( ".optional_fields" ),{click:that.adjustDimension});
                        
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
                        .button({'label':window.hWin.HR('Get current query')}) //{icons: {primary: "ui-icon-search"}, text:false}
                        .css({'height':'16px','font-size':'0.8em',width:'45px'})
                        .click(function( event ) {
                            var res = window.hWin.HEURIST4.query.hQueryStringify(window.hWin.HEURIST4.current_query_request, true);
                            if($dlg.find('#svs_Query').val().trim()!=''){
                                var that_dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                                    window.hWin.HR("Are you sure?"),
                                    function(){ 
                                        $( that_dlg ).dialog( "close" ); 
                                        $dlg.find('#svs_Query').val(res).trigger('keyup');    
                                    });
                            }else{
                                $dlg.find('#svs_Query').val(res).trigger('keyup');
                            }
                        });
                        
                        window.hWin.HEURIST4.ui.disableAutoFill($dlg.find('input'));

                    });
                }else{
                    this._initStep0_options();
                }

            }
            else if(this.step==0 && newstep==1){ //select record types

                //MAIN RECORD TYPE
                if(!(this.select_main_rectype.val()>0)){
                    window.hWin.HEURIST4.msg.showMsgFlash('You have to select record type',1000);
                    this.select_main_rectype.focus();  
                    /*
                    if(this.select_main_rectype.hSelect("instance")!=undefined){
                        this.select_main_rectype.hSelect("focus"); 
                    }else{
                        this.select_main_rectype.focus();  
                    }*/
                    return;
                }
            
                var svs_name = this.step0.find('#svs_Name');
                var message = this.step0.find('.messages');
                var bValid = window.hWin.HEURIST4.msg.checkLength( svs_name, "Name", null, 3, 64 );
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
                bValid = window.hWin.HEURIST4.msg.checkLength( svs_title, 'Title', null, 0, 64 );
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
                if(this.select_additional_rectypes && this.select_additional_rectypes.editing_input('instance')){
                    
                    var val = this.select_additional_rectypes.editing_input('getValues');
                    if(val){
                        val = val[0].split(',');
                        var is_reduced = false, vals=[];
                        for (var i=0; i<val.length; i++){
                            if(val[i] && window.hWin.HEURIST4.util.findArrayIndex(val[i], rectypeIds)<0){
                                rectypeIds.push(val[i]);
                                vals.push(val[i]);
                            }else{
                                is_reduced = true;
                            }
                        }
                        if(is_reduced){
                            this.select_additional_rectypes.editing_input('setValue',vals.join(','));    
                        }
                        //rectypeIds = rectypeIds.concat(val);
                    }
                }
                
                
                //mandatory
                if(!window.hWin.HEURIST4.util.isArrayNotEmpty(rectypeIds)){
                    window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR("Select record type(s)"));

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

            if($.isFunction(this.options.menu_locked)){
                if(newstep>0)  //increase delay on mouse exit 
                    this.options.menu_locked.call( this, 'delay');
            }
            
            //if(newstep==3)               //skip step - define ranges
            //this.navigateWizard(1);
        }

        
        window.hWin.HEURIST4.ui.applyCompetencyLevel(-1, $dlg); 
        
    }

    //
    // record type selector (@todo move to utils_ui ???)
    //
    ,_createInputElement_RecordTypeSelector: function(){
        
        var that = this;

        var ed_options = {
            recID: -1,
            dtID: "dty_PtrTargetRectypeIDs",
            dtFields:{
                "dty_Type":"resource",
                "rst_DisplayName":"Also search for:",
                "rst_DisplayHelpText": "", 
//This determines the record type which will be retrieved by the search. The facets can, however, be based on attributes of other record types linked from this type                
                "rst_FieldConfig": {"entity":"DefRecTypes","csv":true}
            },
            change: function(){
                    var $dlg = that.step0.find("#facets_options");
                    var svs_name = $dlg.find('#svs_Name');
                    var val = this.getValues()
                    if(val.length>0 && val[0]){
                        val = val[0].split(',');
                        if(svs_name.val()==''){
                            var names = [];
                            $.each(val,function(i,item){ names.push( $Db.rty(item,'rty_Name') ) });
                            svs_name.val( names.join(', ') );
                        }
                        svs_name.focus();
                    }
                    that._resetFacets();
            }    
        };

        return $("<div>").editing_input(ed_options).insertAfter( this.step0.find('.main-rectype') );
    }
    
    ,_createOrderSelect: function(){
        
            var topOptions2 = [
                    {key:'', title:window.hWin.HR("select...")},
                    {key:'t', title:window.hWin.HR("record title")},
                    {key:'id', title:window.hWin.HR("record id")},
                    {key:'rt', title:window.hWin.HR("record type")},
                    {key:'u', title:window.hWin.HR("record URL")},
                    {key:'m', title:window.hWin.HR("date modified")},
                    {key:'a', title:window.hWin.HR("date added")},
                    {key:'r', title:window.hWin.HR("personal rating")},
                    {key:'p', title:window.hWin.HR("popularity")}];
                    
            var rty_ID = this.select_main_rectype.val();
            var $dlg = this.step0.find("#facets_options");
            
            if(rty_ID>0){
            
                var allowed_fieldtypes = ['enum','freetext','blocktext','year','date','integer','float'];
                //show field selector
                
                
                window.hWin.HEURIST4.ui.createRectypeDetailSelect($dlg.find('.sa_sortby').get(0), rty_ID, 
                            allowed_fieldtypes, topOptions2, 
                            {useHtmlSelect:false});    //, selectedValue:'t'
            
            }else{
                var selObj = $dlg.find('.sa_sortby').get(0);
                window.hWin.HEURIST4.ui.createSelector(selObj, topOptions2); 
                window.hWin.HEURIST4.ui.initHSelect(selObj, false); 
            }
        
    }
    
    
    , _showStep :function(newstep){

        if(this.step>=0) this.step_panels[this.step].css({'display':'none'});
        this.step_panels[newstep].css({'display':'block','overflow':'hidden'});
        
        var btns = this.element.parent().find('.ui-dialog-buttonset');

        if(this.step==2 && newstep==3){
            
            this._doSaveSearch( true );//from ui to options.params

            this.facetPreview_reccount = 0; //first time it always refresh preview
            this._refresh_FacetsPreview();
            //this._refresh_FacetsPreviewReal();
            btns.find("#btnNext").button({icon:'ui-icon-check', label:window.hWin.HR('Save')});
        }

        if(newstep==0 && this.options.svsID>0 && 
            this.options.params.rectypes && this.options.params.rectypes[0]==this.originalRectypeID)
        {
            btns.find("#btnSave").css({'visibility':'visible','margin-left':'20px'});//show();
        }else{
            btns.find("#btnSave").css('visibility','hidden');//$("#btnSave").hide();
        }
        
        
        this.step = newstep;
        
        this.adjustDimension();
        
        if(newstep==0){
            var that = this;
            setTimeout(function(){that.step0.find('#svs_Name').focus();},500);
            btns.find("#btnBack").hide();
            
        }else{
            btns.find("#btnBack").show();
        }

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
            var svs_filter = $dlg.find('#svs_Query'); //preliminary query

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
                    }
                    that._createOrderSelect();
                    
                    that._resetFacets();
                }});

                //additional rectypes                
                this.select_additional_rectypes = this._createInputElement_RecordTypeSelector();
                this.select_additional_rectypes.hide();
                
                this.svs_MultiRtSearch = $dlg.find('#svs_MultiRtSearch');
                
                this._on(this.svs_MultiRtSearch, {change:function(event){
                    if(this.select_additional_rectypes.editing_input('instance')){
                        if(this.svs_MultiRtSearch.is(':checked')){
                            this.select_additional_rectypes.show();
                        }else{
                            
                            //reset flag - facet was changed - need to proceed all steps of wizard
                            if(this.select_additional_rectypes.editing_input('getValues')[0]){
                                this._resetFacets();
                            }
                            this.select_additional_rectypes.editing_input('setValue', '');
                            this.select_additional_rectypes.hide();
                        }
                    }}});
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

            var sa_order = $dlg.find('.sa_sortby');

            if(isEdit){
                
                var svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID];
                svs_id.val(svsID);
                svs_name.val(svs[0]);
                
                if(svsID>0){
                    this.options.params = window.hWin.HEURIST4.util.isJSON(svs[1]);
                    if(!this.options.params){
                        window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR('Cannot initialise edit for faceted search due to corrupted parameters. Please remove and re-create this search.'), null, "Error");
                        return;
                    }
                }
                
                svs_rules.val( this.options.params.rules?this.options.params.rules:'' );
                svs_filter.val( this.options.params.sup_filter?this.options.params.sup_filter:'' ).trigger('keyup');

                var is_rules_only = (this.options.params.rulesonly>0 || this.options.params.rulesonly==true);
                svs_rules_only.prop('checked', is_rules_only);
                
                $dlg.find('#svs_RulesOnly'+this.options.params.rulesonly).prop('checked', true);


                this.options.domain = this.options.params.domain;

                svs_ugrid.val(svs[2]==window.hWin.HAPI4.currentUser.ugr_ID ?this.options.params.domain:svs[2]);
                //svs_ugrid.parent().hide();
                svs_ugrid.attr('disabled','disabled');
                
                if(this.options.params.rectypes) {

                    if($.isArray(this.options.params.rectypes)){
                        rtids = this.options.params.rectypes;
                    }else{
                        rtids = this.options.params.rectypes.split(',')
                    }
                    
                    this.select_main_rectype.val(rtids[0]);
                    if(this.select_main_rectype.hSelect("instance")!=undefined){
                       this.select_main_rectype.hSelect("refresh"); 
                    }
                    this._createOrderSelect();
                    
                    if(this.select_additional_rectypes.editing_input('instance')){
                        var initval = '';
                        if(rtids.length>1){
                            rtids.shift();
                            initval = rtids.join(',');
                        }
                        this.select_additional_rectypes.editing_input('setValue', initval );    
                        
                        this.svs_MultiRtSearch.prop('checked',(initval!='')).change();
                    }
                    
                    
                    //$(opt_rectypes).change(function(){});
                    
                    if(this.originalRectypeID==null){//init flag
                        this.originalRectypeID = this.options.params.rectypes[0];    
                    }
                }
                
                $dlg.find('#svs_Title').val(this.options.params.ui_title);
                $dlg.find('#svs_ViewMode').val(this.options.params.ui_viewmode);
                $dlg.find('#svs_SearchOnReset').prop('checked', this.options.params.search_on_reset);
                $dlg.find('#svs_TempInitSearch').val(this.options.params.ui_temporal_filter_initial);
                
                $dlg.find('#svs_SpatialFilter').prop('checked', this.options.params.ui_spatial_filter!==false);
                $dlg.find('#svs_SpatialFilterInit').prop('checked', this.options.params.ui_spatial_filter_init!==false);
                $dlg.find('#svs_SpatialFilterLabel').val(this.options.params.ui_spatial_filter_label);
                $dlg.find('#svs_SpatialFilterInitial').val(this.options.params.ui_spatial_filter_initial);
                $dlg.find('#svs_AdditionalFilter').prop('checked', this.options.params.ui_additional_filter!==false);
                $dlg.find('#svs_AdditionalFilterLabel').val(this.options.params.ui_additional_filter_label);

                $dlg.find('#svs_PrelimFilterToggle').prop('checked', this.options.params.ui_prelim_filter_toggle!==false);
                $dlg.find('#svs_PrelimFilterToggleMode'
                        +(this.options.params.ui_prelim_filter_toggle_mode==1)?'1':'0')
                        .prop('checked', true);
                $dlg.find('#svs_PrelimFilterToggleLabel').val(this.options.params.ui_prelim_filter_toggle_label);

                $dlg.find('#svs_ExitButton').prop('checked', this.options.params.ui_exit_button!==false);
                $dlg.find('#svs_ExitButtonLabel').val(this.options.params.ui_exit_button_label);
                
                
                if(this.options.params.sort_order){
                    var s = this.options.params.sort_order;
                    if(s.indexOf('-')==0){
                        $dlg.find('.sa_sortasc').val(1);    
                        s = s.substr(1);
                    }
                    if(s.indexOf('f:')==0){
                        s = s.substr(2);                        
                    }
                    
                   sa_order.val(s)
                }else{
                   sa_order.val('')
                }
                
            }else{ //add new saved search
                this.originalRectypeID == null;

                svs_id.val('');
                svs_name.val('');
                svs_rules.val('');
                svs_filter.val('').trigger('keyup');
                svs_rules_only.prop('checked', false);
                $dlg.find('#divRulesOnly').hide();


                svs_ugrid.val(this.options.domain);
                svs_ugrid.removeAttr('disabled');;
                //svs_ugrid.parent().show();
                $dlg.find('#svs_Title').val('');
                $dlg.find('#svs_SearchOnReset').prop('checked', false);
                
                $dlg.find('#svs_SpatialFilter').prop('checked', false);
                $dlg.find('#svs_SpatialFilterInit').prop('checked', false);
                $dlg.find('#svs_SpatialFilterLabel').val(window.hWin.HR('Map Search'));                
                $dlg.find('#svs_SpatialFilterInitial').val('');                
                $dlg.find('#svs_TempInitSearch').val('');

                $dlg.find('#svs_AdditionalFilter').prop('checked', false);
                $dlg.find('#svs_AdditionalFilterLabel').val(window.hWin.HR('Search everything'));
                
                $dlg.find('#svs_PrelimFilterToggle').prop('checked', true);
                $dlg.find('#svs_PrelimFilterToggleMode0').prop('checked', true);
                $dlg.find('#svs_PrelimFilterToggleLabel').val(window.hWin.HR('Apply preliminary filter'));
                
                $dlg.find('#svs_ExitButton').prop('checked', true);
                $dlg.find('#svs_ExitButtonLabel').val('');
                
                sa_order.val('')
                
                this.select_main_rectype.val('');
                if(this.select_main_rectype.hSelect('instance')){
                    this.select_main_rectype.hSelect('refresh');    
                }
                this._createOrderSelect();
                
                this.svs_MultiRtSearch.prop('checked',false).change();
                
            }
            
            //init localization
            translationToUI(this.options.params, $dlg, 'ui_name', 'svs_Name', false);
            translationToUI(this.options.params, $dlg, 'ui_title', 'svs_Title', false);
            translationToUI(this.options.params, $dlg, 'ui_additional_filter_label', 'svs_AdditionalFilterLabel', false);
            translationToUI(this.options.params, $dlg, 'ui_spatial_filter_label', 'svs_SpatialFilterLabel', false);
            translationToUI(this.options.params, $dlg, 'ui_exit_button_label', 'svs_ExitButtonLabel', false);
            
            if(sa_order.hSelect("instance")!=undefined){
                sa_order.hSelect("refresh"); 
            }
            
            this._on(svs_rules_only,{change:function(e){
                $dlg.find('#divRulesOnly').css('display',$(e.target).is(':checked')?'block':'none');    
            }});
            svs_rules_only.change();
            
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
            
            this._on($dlg.find('#svs_SpatialFilter'), {change:function( event ){
                if($(event.target).is(':checked')){
                    $dlg.find('.svs_SpatialFilter').show();
                }else{
                    $dlg.find('.svs_SpatialFilter').hide();
                }
            }});
            $dlg.find('#svs_SpatialFilter').change();

            this._on($dlg.find('#svs_ExitButton'), {change:function( event ){
                if($(event.target).is(':checked')){
                    $dlg.find('.svs_ExitButton').show();
                }else{
                    $dlg.find('.svs_ExitButton').hide();
                }
            }});
            $dlg.find('#svs_ExitButton').change();
            
            
            this._on( [$dlg.find('#svs_SpatialFilterSet')[0],$dlg.find('#svs_SpatialFilterInitial')[0]],
            { click:                         
           function(event){
                
                //open map digitizer - returns WKT rectangle 
                var rect_wkt = $dlg.find('#svs_SpatialFilterInitial').val();
                
                var url = window.hWin.HAPI4.baseURL 
                +'viewers/map/mapDraw.php?db='+window.hWin.HAPI4.database;

                var wkt_params = {wkt: rect_wkt, geofilter:true, need_screenshot:false};

                window.hWin.HEURIST4.msg.showDialog(url, {height:'540', width:'600',
                    window: window.hWin,  //opener is top most heurist window
                    dialogid: 'map_digitizer_filter_dialog',
                    params: wkt_params,
                    title: window.hWin.HR('Define initial spatial search'),
                    class:'ui-heurist-bg-light',
                    callback: function(location){
                        if( !window.hWin.HEURIST4.util.isempty(location) ){
                            $dlg.find('#svs_SpatialFilterInitial').val(location.wkt)
                        }
                    }
                } );
                return false;     
           }});   

            
            
            
            if(isEdit && this.options.params.rectypes[0]==this.originalRectypeID)
            {
                $("#btnSave").css({'visibility':'visible','margin-left':'20px'});//$("#btnSave").show();
            }else{
                $("#btnSave").css('visibility','hidden');//$("#btnSave").hide();
            }
        
        }
    }
    
    //
    // reset flag - facet was changed - need to proceed all steps of wizard
    //
    , _resetFacets: function(){
        this.options.params.facets = [];
        this.originalRectypeID = null; //
        $("#btnSave").css('visibility','hidden');
    }

    //
    //
    //
    , _editRules: function(ele_rules) {

        var that = this;

        var url = window.hWin.HAPI4.baseURL+ "hclient/widgets/search/ruleBuilderDialog.php?db=" + window.hWin.HAPI4.database;
        if(ele_rules && !Hul.isempty(ele_rules.val())){
            url = url + '&rules=' + encodeURIComponent(ele_rules.val());
        }else if (this.select_main_rectype.val()>0){
            url = url + '&rty_ID=' + this.select_main_rectype.val();
        }
        
        if(this.options.menu_locked && $.isFunction(this.options.menu_locked)){
            this.options.menu_locked.call( this, true, false); //lock
        }
        

        window.hWin.HEURIST4.msg.showDialog(url, { is_h6style:this.options.is_h6style, 
                    closeOnEscape:true, width:1200, height:600, 
                    title:'Ruleset Editor', 
                    close:function(){
                            if(this.options.menu_locked && $.isFunction(this.options.menu_locked)){
                                this.options.menu_locked.call( this, false, false); //unlock
                            }
                    },
                    callback:
            function(res){
                if(!Hul.isempty(res)) {
                    ele_rules.val( JSON.stringify(res.rules) ); //assign new rules
                }
        }});


    }


    // 2d step - init fieldtreeview
    , _initStep2_FieldTreeView: function(rectypeIds){

        if(window.hWin.HEURIST4.util.isArrayNotEmpty(rectypeIds) && this.current_tree_rectype_ids != rectypeIds.join(',') ){
            /*if(!this.options.params.rectypes ||
            !($(rectypeIds).not(this.options.params.rectypes).length == 0 &&
            $(this.options.params.rectypes).not(rectypeIds).length == 0))*/
            {

                var that = this;
                this.options.params.rectypes = rectypeIds;
                var treediv = $(this.step2).find('#field_treeview');
                var rectype;

                if(this.options.params.rectypes){
                    rectype = this.options.params.rectypes.join(',');
                }
                
            let node_order = sessionStorage.getItem('heurist_ftorder_facetbuilder');
            if(window.hWin.HEURIST4.util.isempty(node_order) || !Number.isInteger(+node_order)){
                node_order = 0; // default to form order
            }
            $(this.step2).find('[name="tree_order"]').filter('[value="'+ node_order +'"]').prop('checked', true);

                var allowed_fieldtypes = ['header_ext',
                'enum','freetext','blocktext',"year","date","integer","float","resource","relmarker",'separator'];
                
            var treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 5, rectype, allowed_fieldtypes, null, node_order );

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
                    renderNode: function(event, data){

                        let order = $(that.step2).find('[name="tree_order"]:checked').val();

                        if(data.node.data.dtyID_local && data.node.data.code.includes(rectype+':')!==false && data.node.data.type != 'separator'){ // top level only, add usage container
                            $(data.node.span.childNodes[3]).append(
                                '<span style="display:inline-block;margin-left: 10px;" data-dtid="'+ data.node.data.dtyID_local +'" class="usage_count">&nbsp;</span>');
                        }

                        if(data.node.data.is_generic_fields) { // hide blue arrow for generic fields
                            $(data.node.span.childNodes[1]).hide();
                        }else if(data.node.data.type == 'separator'){
                            $(data.node.span).attr('style', 'background: none !important;color: black !important;'); //stop highlighting
                            $(data.node.span.childNodes[1]).hide(); //checkbox for separators

                            if(order == 1){
                                $(data.node.li).addClass('fancytree-hidden');
                            }
                        }else if(data.node.data.type == 'enum'){ // TODO - Move to CSS for general use when field colours are set out
                            $(data.node.span.childNodes[3]).css('color', '#871F78');
                        }else if(data.node.data.type == 'date'){ // TODO - Move to CSS for general use when field colours are set out
                            $(data.node.span.childNodes[3]).css('color', 'darkgreen');
                        }
                    },
                    lazyLoad: function(event, data){
                        
                        var node = data.node;
                        var parentcode = node.data.code; 
                        var rectypes = node.data.rt_ids;
                        
                        if(parentcode.split(":").length<5){  //limit with 3 levels
                        
                            let node_order = $(that.step2).find('[name="tree_order"]:checked').val();

                            var res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 5, rectypes, allowed_fieldtypes, parentcode, node_order );
                            if(res.length>1){
                                data.result = res;
                            }else{
                                data.result = res[0].children;
                            }
                        
                        }else{
                            data.result = [];
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

                        if(data.node.data.type == 'separator'){
                            return false;
                        }

                        var isExpander = $(e.originalEvent.target).hasClass('fancytree-expander');

                        if(isExpander){
                            return;
                        }

                        if($(e.originalEvent.target).is('span') && data.node.children && data.node.children.length>0){
                            data.node.setExpanded(!data.node.isExpanded());
                        }else if( data.node.lazy) {
                            data.node.setExpanded( true );
                        }
                    },
                    dblclick: function(e, data) {
                        if(data.node.data.type == 'separator'){
                            return false;
                        }
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

                that.current_tree_rectype_ids = rectypeIds.join(',');

                var ele = that.element.find("#fsw_showreverse");
                that._on(ele,{change:function(event){

                    that.showHideReverse();
                }});

                // Calculate field usage button
                ele = that.element.find("#get_usages").button({showLabel: true, label: 'Calculate Usage'}).css('margin-left', '15px');
                if(that.current_tree_rectype_ids.includes(',')===false){
                    ele.show();
                    that._on(ele, {click: function(){
                        that.calculateFieldUsage();
                    }});
                }else{
                    ele.hide();
                }

                // Reorder tree nodes
                this._off($('[name="tree_order"]'), 'change');
                this._on($('[name="tree_order"]'), {
                    change: () => {
                        let order = $('[name="tree_order"]:checked').val();
                        sessionStorage.setItem('heurist_ftorder_facetbuilder', order);

                        if(treediv.fancytree('instance')!==undefined){

                            window.hWin.HEURIST4.ui.reorderFancytreeNodes_rst(treediv, order);
                            that.showHideReverse();
                        }
                    }
                });

                ele.attr('checked', false);
                ele.change();
            }
        }
    }
    
    , showHideReverse: function(){
        
        var treediv = this.element.find('#field_treeview');

        if(treediv.fancytree('instance')){
        
            var tree = treediv.fancytree("getTree");
            var showrev = this.element.find('#fsw_showreverse').is(":checked");

            tree.visit(function(node){

                if(node.data.isparent==1){ // always show parent entities
                    $(node.li).removeClass('fancytree-hidden');
                }else if(node.data.isreverse==1){

                    if(showrev===true){
                        $(node.li).removeClass('fancytree-hidden');
                    }else{
                        $(node.li).addClass('fancytree-hidden');
                    }
                }
            });
        }
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
    // 3d step  - define individual setting for facets and preview
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
            
            if(!old_facets[k]){
                continue;
            }

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
                            multisel: old_facet.multisel,
                            groupby: old_facet.groupby,
                            orderby: old_facet.orderby,
                            type: node.data.type,
                            order: old_facet.order>=0?old_facet.order:order_for_new,
                            trm_tree: old_facet.trm_tree && old_facet.trm_tree === true
                        } );

                        if(!(old_facet.order>=0)) order_for_new++;
                        
                    }else{

                        facets.push( {
                            'var': __getRandomInt(),
                            code:node.data.code,
                            title:'{NEW}', //(node.data.name?node.data.name:node.title),
                            groupby: null,
                            orderby: null,
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
            $(this.step3).find("#cbAccordionView").prop('checked', this.options.params.accordion_view==true);
            $(this.step3).find("#cbShowAccordIcons").prop('checked', this.options.params.show_accordion_icons==true);
            
            if(this.options.params.accordion_view==true){
                $(this.step3).find("#cbShowAccordIcons").show();
            }

            var listdiv = $(this.step3).find("#facets_list");
            listdiv.empty();

            len = facets.length;
            k = 0;
            while(k<facets.length){
                //title
                //help tip (take from rectype structure?)
                //type of facet (radio group)
                var resh = $Db.getHierarchyTitles(facets[k]['code']);
                
                if(!resh){ //remove facet - one of record type is missed
                    facets.splice(k,1);
                    continue;
                }
                
                var harchy = resh.harchy;
                var harchy_fields = resh.harchy_fields;
                
                var idd = facets[k]['var'];
                
                var sContent =
                '<div id="facet'+idd+'" style="border-top:1px lightgray solid; padding:10px 0px;">'
                +'<div><span class="ui-icon ui-icon-up-down span_for_radio"></span>'
                +'<input type="text" name="facet_Title'+idd+'" id="facet_Title'+idd+'" '
                +' style="font-weight:bold;display:inline-block" class="text ui-widget-content ui-corner-all" />'
                +'<button label="Remove" class="remove_facet" data-var="'+idd+'" /></div>'
                
                +'<div style="padding:5px 0 5px 40px">'
                //+'<label style="font-size:smaller" for="facet_Title'+idd+'">Label</label>&nbsp;'   //'<div class="header_narrow"></div>'
                
                + '<div class="ent_search_cb" style="font-style:italic;padding-top:10px">'
                + '<span id="buttonset'+idd+'">';
                
                
                var sMultiSel = '';
                var sGroupBy = '';
                var includeDropdown = true;
                if(facets[k].type=='freetext' || facets[k].type=='blocktext'){
                    sGroupBy =
                        '<label><input type="checkbox" name="facet_Group'+idd+'" value="firstchar"/>'
                        +'Group by first character</label>';
						
                    sMultiSel = '<label title="applicable for list and wrapped modes only" style="font-size: smaller;">'
                            + '<input type="checkbox" name="facet_MultiSel'
                            + idd+'" value="1"/>multi-select</label>';
						
                    includeDropdown = facets[k].type != 'blocktext';
                   
                }else if(facets[k].type=='float' || facets[k].type=='integer'){
                    sContent = sContent 
                        +'<button label="slider" class="btnset_radio" data-idx="'+idd+'" data-value="1" data-type="slider"/>';
						
                    includeDropdown = false;
                    
                }else if(facets[k].type=='date' || facets[k].type=='year'){
                    sContent = sContent 
                        +'<button label="slider" class="btnset_radio" data-idx="'+idd+'" data-value="1" data-type="slider"/>';
                    
                    sGroupBy = '<span id="facet_DateGroup'+idd+'"><label>Group by '
                        +'<select name="facet_Group'+idd+'"><option>month</option>'
                        +'<option>year</option><option>decade</option><option>century</option></select>'
                        +'</label></span>';
                        
                    sGroupBy = sGroupBy
                        +'<label><input type="checkbox" data-sort="desc" data-id="'
                        + idd+'" style="vertical-align: middle;margin-left:16px">'
                        + window.hWin.HR("Order descending")+"</label>";
						
                    includeDropdown = false;
                   
                }else if(facets[k].type=='enum' || facets[k].type=='reltype'){
                    
                    sGroupBy = '<label><input type="checkbox" name="facet_Group'
                                +idd+'" value="firstlevel"/>Group by first level</label>';
                                
                    sMultiSel = '<label title="applicable for list and wrapped modes only" style="font-size: smaller;">'
                                + '<input type="checkbox" name="facet_MultiSel'
                                + idd+'" value="1"/>multi-select</label>';
                    
                }
                
                if(includeDropdown){
                    sContent = sContent + '<button label="dropdown" class="btnset_radio" data-idx="'+idd+'" data-value="1" data-type="dropdown"/>';
                }
				
                if(facets[k].code.indexOf(':ids')<0 && facets[k].type!='blocktext')
                    sContent = sContent
                        +'<button label="list" class="btnset_radio" data-idx="'+idd+'" data-value="3"/>'
                        +'<button label="wrapped" class="btnset_radio" data-idx="'+idd+'" data-value="2"/>';
                                        
                if((facets[k].type=='enum' || facets[k].type=='reltype') && (facets[k].code.indexOf(':addedby')<0 && facets[k].code.indexOf(':owner')<0)){
                    sContent = sContent + '<button label="tree" class="btnset_radio" data-idx="'+idd+'" data-value="tree" data-type="tree"/>';
                }

                sContent = sContent        
                        +'<button label="search" class="btnset_radio" data-idx="'+idd+'" data-value="0"/>'
                + '</span>'+sMultiSel+'</div></div>' 

                //second/optional line
                +'<div style="padding:5px 0 0 40px;" class="optional_settings">'
                
                +'<div style="display:inline-block">'
                +'<label style="font-size:smaller" for="facet_Help'+idd+'">Rollover (optional)&nbsp;</label>'
                +'<input name="facet_Help'+idd+'" id="facet_Help'+idd+'" type="text" '
                +' class="text ui-widget-content ui-corner-all"'
                +' style="font-size:smaller;margin-top:0.4em;margin-bottom:1.0em;width:200px;"/>'
                +'</div>'
                
                + '<div style="display:inline-block;font-size:smaller;">'
                +'<label><input name="facet_AccordHide'+idd+'" id="facet_AccordHide'+idd+'" type="checkbox" '
                +' style="vertical-align: middle;" />Accordion closed</label>'
                + sGroupBy 
                +'<label><input type="checkbox" data-sort="count" data-id="'
                + idd+'" style="vertical-align: middle;margin-left:16px">'
                + window.hWin.HR("Order by count")+"</label>"
                + '</div>'
                
                + '</div>'
                + '<div style="padding-left:40px;" class="optional_settings">'
                    + '<label>Facet </label>&nbsp;'
                    + '<label style="font-size:smaller">' + harchy.join('') + '</label>'
                + '</div>'
                + '</div>'; 
                

                listdiv.append($(sContent));

                //backward capability
                if(facets[k].isfacet===false){
                    facets[k].isfacet = 0;
                }else if(facets[k].isfacet===true || !(Number(+facets[k].isfacet)<4 && Number(+facets[k].isfacet)>=0)){
                    //by default column for text and selector/slider for dates
                    //for text field default is search for others slider/dropdown
                    if(facets[k].type=='enum' || facets[k].type=='reltype'){
                        
                        if(!(facets[k].isfacet>0)){
                            let dtid = facets[k].code.split(':');
                            dtid = dtid[dtid.length-1];
                            var vocab_id = $Db.dty(dtid, 'dty_JsonTermIDTree');    
                            var list = $Db.trm_TreeData(vocab_id, 'set');
                            
                            if(list.length<5){
                                facets[k].isfacet = 2; //wrap 
                            }else if (list.length<25) {
                                facets[k].isfacet = 3; //list
                            }else{
                                facets[k].isfacet = 1; //dropdown   
                            }
                        }
                    }else{
                        facets[k].isfacet = (facets[k].type=='freetext' || facets[k].type=='blocktext')?0:1;
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
                
                translationToUI(facets[k], listdiv, 'title', 'facet_Title'+idd, false);
                translationToUI(facets[k], listdiv, 'help', 'facet_Help'+idd, false);

                
                listdiv.find('input[data-sort="count"][data-id="'+idd+'"]').prop('checked', (facets[k].orderby=='count'));
                listdiv.find('input[data-sort="desc"][data-id="'+idd+'"]').prop('checked', (facets[k].orderby=='desc'));

                //listdiv.find('input:radio[name="facet_Type'+idd+'"][value="'+facets[k].isfacet+'"]').attr('checked', true);
                listdiv.find('button.btnset_radio[data-idx="'+idd+'"]').removeClass('ui-heurist-btn-header1');

                let def_facet = facets[k].isfacet;
                if(facets[k].trm_tree === true){
                    def_facet = 'tree';
                }
                var btn = listdiv.find('button.btnset_radio[data-idx="'+idd+'"][data-value="'+facets[k].isfacet+'"]');
                btn.addClass('ui-heurist-btn-header1');  //heighlight               

                    function __dateGrouping(idd){ //for year-date
                        
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
                                    listdiv.find('#facet_DateGroup'+idd).css({'display':is_allowed?'inline':'none'});        
                                    if(is_allowed){
                                        if(Hul.isempty(facets[idx].groupby)){
                                            facets[idx].groupby = 'year';
                                        }
                                        listdiv.find('select[name="facet_Group'+idd+'"]').val(facets[idx].groupby);
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
                
                if(facets[k]['accordion_hide'] == true){ // set accordion collasped by default
                    listdiv.find('input:checkbox[name="facet_AccordHide'+idd+'"]').prop('checked', true);
                }
                
                if(facets[k].type=='date' || facets[k].type=='year'){
                    __dateGrouping(idd);
                }else{
                    listdiv.find('input:checkbox[name="facet_Group'+idd+'"][value="'+facets[k].groupby+'"]').attr('checked', true);
                    if(facets[k].multisel){
                        listdiv.find('input:checkbox[name="facet_MultiSel'+idd+'"]').attr('checked', true);    
                    }
                }
                
                k++;
            }//while facets
           
            this.options.params.facets = facets;   //assign new selection
            this.options.params.version = 2;
           
            var that = this;
            listdiv.sortable({stop: function( event, ui ) { that._refresh_FacetsPreview() } });
            listdiv.disableSelection();

            listdiv.find('button.remove_facet').button({showLabel: true, label: 'Remove'}).css({'padding': '2px', 'font-size': '10px', 'margin-left': '10px'});
            this._on(listdiv.find('button.remove_facet'), {click: function(event){
                this._remove_facet($(event.target).attr('data-var'));
            }});

            listdiv.find('button[data-value="3"]').button({icon: "ui-icon-list-column",iconPosition:'end',showLabel:true,label:'list'});
            listdiv.find('button[data-value="2"]').button({icon: "ui-icon-list-inline",iconPosition:'end',showLabel:true,label:'wrapped'});
            listdiv.find('button[data-value="0"]').button({icon: "ui-icon-search",iconPosition:'end',showLabel:true,label:'search'});
            listdiv.find('button[data-type="slider"]').button({icon: "ui-icon-input-slider",iconPosition:'end',showLabel:true,label:'slider'});
            listdiv.find('button[data-type="dropdown"]').button({icon: "ui-icon-input-dropdown",iconPosition:'end',showLabel:true,label:'dropdown'});
            listdiv.find('button[data-type="tree"]').button({icon: "ui-icon-structure",iconPosition:'end',showLabel:true,label:'tree'});
            listdiv.find('.ui-button-text').css({"min-width":"60px","font-size":'0.9em'});
            listdiv.find('button.btnset_radio[data-idx="'+idd+'"]').controlgroup();
                                      
            $(this.step3).find('#cbShowAdvanced').attr('checked',true).change(function(event){
                if($(event.target).is(':checked')){
                     listdiv.find('.optional_settings').show();
                    $(that.step3).find('#cbAccordionView').parent().show();
                }else{
                    listdiv.find('.optional_settings').hide();
                    $(that.step3).find('#cbAccordionView').parent().hide();
                }

                if($(this.step3).find('#cbAccordionView').is(':checked') && $(event.target).is(':checked')){
                    $(this.step3).find('#cbShowAccordIcons').prop('checked', true).parent().show();
                }else{
                    $(this.step3).find('#cbShowAccordIcons').parent().hide();
                }
            });
            
            this._on( listdiv.find('input[id^="facet_Title"]'), {change: this._refresh_FacetsPreview});
            this._on( listdiv.find('input[id^="facet_Help"]'), {change: this._refresh_FacetsPreview});
            this._on( listdiv.find('input[id^="facet_AccordHide"]'), {change: this._refresh_FacetsPreview});
            this._on( listdiv.find('select[name^="facet_Group"]'), {change: this._refresh_FacetsPreview});
            this._on( listdiv.find('input[name^="facet_MultiSel"]'), {change: this._refresh_FacetsPreview});
            this._on( listdiv.find('input[name^="facet_Group"]'), {change: this._refresh_FacetsPreview});
            this._on( $(this.step3).find('#cbShowHierarchy'), {change: this._refresh_FacetsPreview});
            this._on( $(this.step3).find('#cbAccordionView'), {change: () => { 
                if($(this.step3).find('#cbAccordionView').is(':checked')){
                    $(this.step3).find('#cbShowAccordIcons').prop('checked', true).parent().show();
                }else{
                    $(this.step3).find('#cbShowAccordIcons').parent().hide();
                }
                this._refresh_FacetsPreview(); 
            }});
            this._on( $(this.step3).find('#cbShowAccordIcons'), {change: this._refresh_FacetsPreview});

            this._on( listdiv.find('input[data-sort]'), {change: function(e){
                
                var iid = $(e.target).attr('data-id');
                var ssort = $(e.target).attr('data-sort');
                
                $.each(listdiv.find('input[data-sort]'), function(i,item){
                    if(iid==$(item).attr('data-id') && ssort!=$(item).attr('data-sort')){
                        $(item).removeAttr('checked');
                    }
                });
                this._refresh_FacetsPreview();   
            }});
            
            
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
            this.options.params.accordion_view = $(this.step3).find("#cbAccordionView").is(':checked');
            this.options.params.show_accordion_icons = $(this.step3).find("#cbShowAccordIcons").is(':checked');
            
            var listdiv = $(this.step3).find("#facets_list");

            var k, len = this.options.params.facets.length;
            for (k=0;k<len;k++){
                var idd = this.options.params.facets[k]['var'];
                
                var keep_title = this.options.params.facets[k].title;
                translationFromUI(this.options.params.facets[k], listdiv, 'title', 'facet_Title'+idd, false);
                translationFromUI(this.options.params.facets[k], listdiv, 'help', 'facet_Help'+idd, false);
                if(this.options.params.facets[k].title=='') this.options.params.facets[k].title=keep_title;
                
                let facet = listdiv.find('button.ui-heurist-btn-header1[data-idx="'+idd+'"]').attr('data-value');
                if(facet == 'tree'){
                    facet = 3; // force list
                    this.options.params.facets[k].trm_tree = true;
                }
                this.options.params.facets[k].isfacet = facet;

                this.options.params.facets[k].orderby = null;    
                if(listdiv.find('input[data-sort="count"][data-id="'+idd+'"]').is(':checked'))
                {
                    this.options.params.facets[k].orderby = 'count';    
                }else if(listdiv.find('input[data-sort="desc"][data-id="'+idd+'"]').is(':checked')){
                    this.options.params.facets[k].orderby = 'desc';    
                }
                    
                
                if(this.options.params.facets[k].type=='date' 
                  || this.options.params.facets[k].type=='year'){
                    if(this.options.params.facets[k].isfacet>1){
                        this.options.params.facets[k].groupby = listdiv.find('select[name="facet_Group'+idd+'"]').val();
                    }else{
                        this.options.params.facets[k].groupby = null;    
                    }
                    
                }else{
                    this.options.params.facets[k].groupby = listdiv.find('input:checkbox[name="facet_Group'+idd+'"]:checked').val();    
                    this.options.params.facets[k].multisel = listdiv.find('input:checkbox[name="facet_MultiSel'+idd+'"]').is(':checked'); 
                }
                
                this.options.params.facets[k]['order'] = $('#facet'+idd).index();

                this.options.params.facets[k]['accordion_hide'] = $('#facet_AccordHide'+idd).is(':checked');

            }
            //sort according to order in UI list
            
            this.options.params.facets.sort(function(a,b){
                return a['order']<b['order']?-1:1;
            });
        }
        return null;
    }
    
    //
    // old version it updates parms only (from UI to options.params)
    //
    ,_refresh_FacetsPreview: function(){
        
        this._assignFacetParams();
        this._defineDomain();

        //if( (window.hWin.HAPI4.sysinfo['db_workset_count']>0 && window.hWin.HAPI4.sysinfo['db_workset_count']<10000) 
        //    || window.hWin.HAPI4.sysinfo['db_total_records']<10000 )
        
        if( this.facetPreview_reccount < 10000 ){
            this._refresh_FacetsPreviewReal();
            $(this.step3).find('#btnUpdatePreview').hide();
        }else{
            $(this.step3).find('#btnUpdatePreview').css('opacity',1).show();
        }
    }
    
    //
    // update preview
    //
    ,_refresh_FacetsPreviewReal: function(){

        var listdiv = $(this.step3).find("#facets_preview2");

        var noptions = { query_name:"test", params: JSON.parse(JSON.stringify(this.options.params)), ispreview: true}
        
        //force search for entire recordset to get total count of records
        if( this.facetPreview_reccount == 0 ){
            noptions.params.search_on_reset = true; 
            var that = this;
            noptions.params.callback_on_search_finish = function(total_count){

                that.facetPreview_reccount = total_count;
                if(total_count<10000){
                    $(that.step3).find('#btnUpdatePreview').hide();
                }else{
                    $(that.step3).find('#btnUpdatePreview').show();
                }
            }
        }
            
        if(listdiv.html()==''){ //not created yet
            listdiv.search_faceted( noptions );
        }else{
            listdiv.search_faceted('option', noptions ); //assign new parameters
        }
        $(this.step3).find('#btnUpdatePreview').css('opacity',0.5);
    }

    //
    //
    //
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
    //  prevent_real_save - if true just fill this.options.params
    //
    ,_doSaveSearch:function(prevent_real_save){

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

        var bValid = window.hWin.HEURIST4.msg.checkLength( svs_name, "Name", null, 3, 64 );
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
            
            var rules_only = 0;
            if(svs_rules_only.is(':checked')){
                rules_only = $dlg.find('input[name="svs_RulesOnly"]:checked').val();
            }
            this.options.params.rulesonly = rules_only;
            
        }else{
            this.options.params.rules = null;
            this.options.params.rulesonly = null;
        }
        if(true || !Hul.isempty(svs_filter.val())){
            this.options.params.sup_filter = svs_filter.val();
        }

        this.options.params.ui_viewmode = $dlg.find('#svs_ViewMode').val();
        this.options.params.search_on_reset = $dlg.find('#svs_SearchOnReset').is(':checked');
         
        this.options.params.ui_prelim_filter_toggle = $dlg.find('#svs_PrelimFilterToggle').is(':checked');
        this.options.params.ui_prelim_filter_toggle_mode =  $dlg.find('#svs_PrelimFilterToggleMode0').is(':checked')?0:1;
        this.options.params.ui_prelim_filter_toggle_label = $dlg.find('#svs_PrelimFilterToggleLabel').val();
        
        this.options.params.ui_spatial_filter = $dlg.find('#svs_SpatialFilter').is(':checked');
        this.options.params.ui_spatial_filter_init = $dlg.find('#svs_SpatialFilterInit').is(':checked');
        this.options.params.ui_spatial_filter_label = $dlg.find('#svs_SpatialFilterLabel').val();
        this.options.params.ui_spatial_filter_initial = $dlg.find('#svs_SpatialFilterInitial').val();
        this.options.params.ui_temporal_filter_initial = $dlg.find('#svs_TempInitSearch').val();
        
        this.options.params.ui_additional_filter = $dlg.find('#svs_AdditionalFilter').is(':checked');
        this.options.params.ui_additional_filter_label =$dlg.find('#svs_AdditionalFilterLabel').val();
        
        this.options.params.ui_exit_button = $dlg.find('#svs_ExitButton').is(':checked');
        this.options.params.ui_exit_button_label = $dlg.find('#svs_ExitButtonLabel').val();
        
        //localized paramerers
        //this.options.params.ui_title =  $dlg.find('#svs_Title').val();
        translationFromUI(this.options.params, $dlg, 'ui_name', 'svs_Name', false);
        translationFromUI(this.options.params, $dlg, 'ui_title', 'svs_Title', false);
        translationFromUI(this.options.params, $dlg, 'ui_additional_filter_label', 'svs_AdditionalFilterLabel', false);
        translationFromUI(this.options.params, $dlg, 'ui_spatial_filter_label', 'svs_SpatialFilterLabel', false);
        translationFromUI(this.options.params, $dlg, 'ui_exit_button_label', 'svs_ExitButtonLabel', false);

        var s = $dlg.find('.sa_sortby').val();
        if(s!=''){
            if(s>0){
                s = 'f:'+s;
            }
            this.options.params.sort_order = ($dlg.find('.sa_sortasc').val()==1?'-':'')+s;    
        }else{
            this.options.params.sort_order = null;
        }
        
        var svs_ugrid = svs_ugrid.val();
        if(parseInt(svs_ugrid)>0){
            this.options.params.domain = 'all';
        }else{
            this.options.params.domain = svs_ugrid;
            svs_ugrid = window.hWin.HAPI4.currentUser.ugr_ID;
        }
        
        if(prevent_real_save===true || this._save_in_porgress===true) return;
        this._save_in_porgress = true;
        //window.hWin.HEURIST4.util.setDisabled(this.element.parent().find('.ui-dialog-buttonset').find("#btnSave"), true);

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
                that._save_in_porgress = false;
                if(response.status == window.hWin.ResponseStatus.OK){

                    var svsID = response.data;

                    if(!window.hWin.HAPI4.currentUser.usr_SavedSearch){
                        window.hWin.HAPI4.currentUser.usr_SavedSearch = {};
                    }

                    window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID] = [request.svs_Name, request.svs_Query, request.svs_UGrpID];

                    request.new_svs_ID = svsID;
                    request.isNewSavedFilter  = !isEdit;

                    that._trigger( "onsave", null, request );

                    that.is_edit_continuing = false;
                    that.element.dialog("close");

                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response, true);
                }
            }

        );
    }

    //
    // Remove facet from list and uncheck within fancytree
    //
    , _remove_facet: function(facetID){

        var that = this;
        $(this.step3).find("#facets_list #facet"+facetID).remove();
        $(this.step3).find("div[data-fid='facets_list_container'] #fv_"+facetID).remove();

        var treediv = $(this.step2).find('#field_treeview');
        var tree = treediv.fancytree("getTree");

        // Uncheck node in fancytree
        $.each(this.options.params.facets, (idx, facet) => {
            if(facet && facet['var'] == facetID){
                that.options.params.facets.splice(idx, 1);
                tree.visit(function(node){
                    if(!window.hWin.HEURIST4.util.isArrayNotEmpty(node.children) && facet.code && facet.code==node.data.code){ // reach leaf node, then check code
                        node.setSelected(false);
                        return false;
                    }
                });

                return false;
            }
        });
    }

    //
    // Retrieve and display field usages
    //
    , calculateFieldUsage: function(){

        if(this.current_tree_rectype_ids.includes(',')!==false){ // single record type only
            return;
        }

        var that = this;

        var req = {
            'rtyID': this.current_tree_rectype_ids,
            'entity': 'defRecStructure',
            'a': 'counts',
            'mode': 'rectype_field_usage',
            'request_id': window.hWin.HEURIST4.util.random()
        };

        window.hWin.HAPI4.EntityMgr.doRequest(req, function(response){
            if(response.status == window.hWin.ResponseStatus.OK){

                let usages = response.data;
                let $usage_eles = $(that.step2).find('#field_treeview').find('span.usage_count');

                $.each($usage_eles, (idx, ele) => {
                    let $ele = $(ele);
                    let dtid = $ele.attr('data-dtid');

                    if(dtid && usages[dtid]){
                        $ele.text('n = ' + usages[dtid]);
                    }else{
                        $ele.text('n = 0');
                    }
                });
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    }
});

function showSearchFacetedWizard( params ){

    if(!$.isFunction($('body').fancytree)){

        $.getScript(window.hWin.HAPI4.baseURL+'external/jquery.fancytree/jquery.fancytree-all.min.js', 
                function(){ showSearchFacetedWizard(params); } );

    }else{

        var manage_dlg = $('#heurist-search-faceted-dialog');

        var need_create = (manage_dlg.length<1);
        
        if( need_create ){

            manage_dlg = $('<div id="heurist-search-faceted-dialog">')
            .addClass('save-filter-dialog')
            .appendTo( $('body') );
            manage_dlg.search_faceted_wiz( params );
        }else{
            if(!params.is_modal && params.params==null){
                params.params = manage_dlg.search_faceted_wiz('option', 'params');
            }
            
            manage_dlg.search_faceted_wiz('option', params);
        }

        manage_dlg.search_faceted_wiz( 'show' );
        
        return manage_dlg;
    }
}