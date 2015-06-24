/**
*  Wizard to define new faceted search
*    1. Options
*    2. Select record types (uses rectype_manager)
*    3. Select fields for facets (recTitle, numeric, date, terms, pointers, relationships)
*    4. Define ranges for date and numeric fields
*    5. Preview
*    6. Save into database
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


/* Explanation of faceted search

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

$.widget( "heurist.search_faceted_wiz", {

    // default options
    options: {
        svsID: null,
        domain: null, // bookmark|all or usergroup ID
        params: {},
        onsave: null
    },
    //params:
    // domain
    // isadvanced
    // rectype_as_facets
    // fieldtypes:[] //allowed field types besides enum amd resource
    //  rectypes:[]                                                                                                                                     //for enums     //for other
    //  facets:[[{ title:node.title, type: freetext|enum|integer|relmarker, query: "t:id f:id", fieldid: "f:id", 
    //                                    currentvalue:{text:label, query:value, termid:termid}, history:[currentvalue] }, ]  

/*
{"isadvanced":false,
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
     
    

    step: 0, //current step
    step_panels:[],
    current_tree_rectype_ids:null,

    // the widget's constructor
    _create: function() {

        var that = this;

        // prevent double click to select text
        //this.element.disableSelection();

        // Sets up element to apply the ui-state-focus class on focus.
        //this._focusable($element);   

        var that = this;

        this.element.css({overflow: 'none !important'}).addClass('ui-heurist-bg-light');

        this.element.dialog({
            autoOpen: false,
            height: 700,
            width: 500,
            modal: true,
            title: top.HR("Define Faceted Search"),
            resizeStop: function( event, ui ) {
                that.element.css({overflow: 'none !important','width':'100%'});
            },
            beforeClose: function( event, ui ) { 
                            if(event && event.currentTarget){ 
                                var that_dlg = this;
                                top.HEURIST4.util.showMsgDlg(top.HR("Cancel? Please confirm"),
                                        function(){ $( that_dlg ).dialog( "close" ); });  
                                return false;        
                            }
                        },
            buttons: [
                {text:top.HR('Back'),
                    click: function() {
                        that.navigateWizard(-1);
                }},
                {text:top.HR('Next'), id:'btnNext',
                    click: function() {
                        that.navigateWizard(1);
                }},
                /*{text:top.HR('Close'), click: function() {
                    var that_dlg = this;
                    top.HEURIST4.util.showMsgDlg(top.HR("Cancel? Please confirm"),
                        function(){ $( that_dlg ).dialog( "close" ); });
                }}*/
            ]
        });
        this.element.parent().addClass('ui-dialog-heurist');

        //option
        this.step0 = $("<div>")
        .css({overflow: 'none !important', width:'100% !important', 'display':'block'})
        .appendTo(this.element);
        //$("<div>").append($("<h4>").html(top.HR("Options"))).appendTo(this.step0);
        $("<div>",{id:'facets_options'}).appendTo(this.step0);
        this.step_panels.push(this.step0);


        //select rectypes
        this.step1 = $("<div>")
        .css({'display':'none'})
        .appendTo(this.element);
        $("<div>").css({'padding':'1em 0'}).append($("<h4>").html(top.HR("Select record types that will be used in search"))).appendTo(this.step1);
        //.css({overflow: 'none !important', width:'100% !important'})
        this.step1.rectype_manager({ isdialog:false, list_top:'8em', isselector:true });

        this.step_panels.push(this.step1);

        //select field types                
        this.step2 = $("<div>")
        .css({overflow: 'none !important', width:'100% !important', 'display':'none'})
        .appendTo(this.element);
        $("<div>").html(top.HR("Select fields that act as facet")).appendTo(this.step2);
        $("<div>",{id:'field_treeview'}).appendTo(this.step2);
        this.step_panels.push(this.step2);

        //ranges
        this.step3 = $("<div>")
        .css({overflow: 'none !important', width:'100% !important', 'display':'none'})
        .appendTo(this.element);
        $("<div>").append($("<h4>").html(top.HR("Define ranges for numeric and date facets"))).appendTo(this.step3);
        $("<div>",{id:'facets_list'}).appendTo(this.step3);
        this.step_panels.push(this.step3);

        //preview
        this.step4 = $("<div>")
        .css({overflow: 'none !important', width:'100% !important', 'display':'none'})
        .appendTo(this.element);
        $("<div>").append($("<h4>").html(top.HR("Preview"))).appendTo(this.step4);
        $("<div>",{id:'facets_preview'}).css('top','3.2em').appendTo(this.step4);
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
        }else if(newstep>4){
            if(newstep==5){
                //save into database
                this._doSaveSearch();
                return;
            }
            newstep = 4;  
        } else{
            $("#btnNext").button('option', 'label', top.HR('Next'));
        }
        if(this.step != newstep){

            if(isNaN(this.step) && newstep==0){ //select record types

                var that = this;
                var $dlg = this.step0.find("#facets_options");
                if($dlg.html()==''){
                    $dlg.load("apps/search/svs_edit_faceted.html?t=9", function(){
                        that._initStep0_options();
                    });
                }else{
                    this._initStep0_options();
                }

            }else if(this.step==0 && newstep==1){ //select record types 

                var svs_name = this.step0.find('#svs_Name');
                var message = this.step0.find('.messages');
                var bValid = top.HEURIST4.util.checkLength( svs_name, "Name", message, 3, 25 );
                if(!bValid){
                        svs_name.focus();
                        //top.HEURIST4.util.showMsgFlash(top.HR("Define Saved search name"), 2000, "Required", svs_name);
                        //setTimeout(function(){svs_name.focus();},2200);
                        return;
                }

                this.options.params.isadvanced = this.step0.find("#opt_mode_advanced").is(":checked");
                this.options.params.rectype_as_facets = this.step0.find("#opt_rectype_as_facets").is(":checked");

                if(this.options.params.isadvanced){

                    this.step1.rectype_manager({selection:this.options.params.rectypes});                  

                }else{
                    this.step = 1; 
                    newstep = 2; //skip step
                }
            }

            if(this.step==1 && newstep==2){ //select field types

                var rectypeIds = null;
                if(this.options.params.isadvanced){
                    rectypeIds = this.step1.rectype_manager("option","selection");
                }else{
                    rectypeIds = [this.step0.find("#opt_rectypes").val()];
                }
                //mandatory
                if(!top.HEURIST4.util.isArrayNotEmpty(rectypeIds)){
                    top.HEURIST4.util.showMsgDlg(top.HR("Select record type"));

                    this.step = (this.options.params.isadvanced)?1:0;
                    return;
                }else{
                    this.step0.hide();
                }

                //load field types
                allowed = ['enum'];
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

                //load list of field types
                this._initStep2_FieldTreeView(rectypeIds);

            }  if(this.step==2 && newstep==3){  //set ranges

                if(!this._initStep3_FacetsRanges()){
                    return;
                }
            }

            //skip step
            if(this.step==2 && newstep==1 && !this.options.params.isadvanced){
                newstep = 0;
            }else if(this.step==4 && newstep==3){
                newstep=2; 
            }

            this._showStep(newstep);
            
             if(newstep==3)               //skip step
                this.navigateWizard(1);

        }

    }

    , _showStep :function(newstep){

        if(this.step>=0) this.step_panels[this.step].css('display','none');
        this.step_panels[newstep].css('display','block');
        
        if(this.step==3 && newstep==4){ //preview

            this._initStep4_FacetsPreview();
            $("#btnNext").button('option', 'label', top.HR('Save'));

        }
            
        this.step = newstep;
    }


    /**
    * Assign values to UI input controls
    */
    , _initStep0_options: function( ){

        var $dlg = this.step0;
        if($dlg){
            $dlg.find('.messages').empty();

            var svs_id = $dlg.find('#svs_ID');
            var svs_name = $dlg.find('#svs_Name');
            var svs_ugrid = $dlg.find('#svs_UGrpID');

            var opt_numeric = $dlg.find('#opt_use_numeric');
            var opt_date = $dlg.find('#opt_use_date');
            var opt_text = $dlg.find('#opt_use_freetext');
            var opt_rectypes = $dlg.find("#opt_rectypes").get(0);
            var opt_mode = $dlg.find("input[name='opt_mode']");

            var opt_mode_simple = $dlg.find("#opt_mode_simple");
            var opt_mode_advanced = $dlg.find("#opt_mode_advanced");

            if($(opt_rectypes).is(':empty')){
                top.HEURIST4.util.createRectypeSelect( opt_rectypes, null, null);

                this._on( opt_mode, {
                    click: function(e){
                        if($(e.target).val()=="true"){
                            $dlg.find('.simple-mode').hide();
                            $dlg.find('.advanced-mode').show();
                        }else{
                            $dlg.find('.simple-mode').show();
                            $dlg.find('.advanced-mode').hide();
                            if(this.options.params.rectypes) $(opt_rectypes).val(this.options.params.rectypes[0]);
                        }
                    }
                }); 

            }

            var svsID = this.options.svsID;

            var isEdit = (parseInt(svsID)>0);

            if(isEdit){
                var svs = top.HAPI4.currentUser.usr_SavedSearch[svsID];
                svs_id.val(svsID);
                svs_name.val(svs[0]);
                this.options.params = $.parseJSON(svs[1]);
                this.options.domain = this.options.params.domain;

                svs_ugrid.val(svs[2]==top.HAPI4.currentUser.ugr_ID ?this.options.domain:svs[2]);
                svs_ugrid.parent().hide();

                var ft = this.options.params.fieldtypes;
                opt_numeric.attr( "checked", ft.indexOf('integer') );
                opt_date.attr( "checked", ft.indexOf('date') );
                opt_text.attr( "checked", ft.indexOf('freetext') );

                this.step0.find("#opt_mode_simple").attr("checked", !this.options.params.isadvanced );
                if(!this.options.params.isadvanced) this.step0.find("#opt_mode_simple").click();
                opt_mode_advanced.attr("checked", this.options.params.isadvanced );
                if(this.options.params.isadvanced) opt_mode_advanced.click();

                this.step0.find("#opt_rectype_as_facets").attr('checked', this.options.params.rectype_as_facets);
                
            }else{ //add new saved search

                svs_id.val('');
                svs_name.val('');

                //fill with list of user groups in case non bookmark search
                var selObj = svs_ugrid.get(0); //select element
                top.HEURIST4.util.createUserGroupsSelect(selObj, top.HAPI4.currentUser.usr_GroupsList,
                    [{key:'bookmark', title:top.HR('My Bookmarks')}, {key:'all', title:top.HR('All Records')}],
                    function(){
                        svs_ugrid.val(top.HAPI4.currentUser.ugr_ID);
                });
                svs_ugrid.val(this.options.domain);
                svs_ugrid.parent().show();

                opt_numeric.attr( "checked", true );
                opt_date.attr( "checked", true );
                opt_text.attr( "checked", true );

                opt_mode_simple.attr("checked", (this.options.params.isadvanced) );
                opt_mode_simple.click();
            }
        }
    }

    // 2d step - init fieldtreeview
    , _initStep2_FieldTreeView: function(rectypeIds){

        if(top.HEURIST4.util.isArrayNotEmpty(rectypeIds) && this.current_tree_rectype_ids != rectypeIds){
            /*if(!this.options.params.rectypes || 
            !($(rectypeIds).not(this.options.params.rectypes).length == 0 && 
            $(this.options.params.rectypes).not(rectypeIds).length == 0))*/
            {

                var that = this;
                this.options.params.rectypes = rectypeIds;
                var treediv = $(this.step2).find('#field_treeview');

                window.HAPI4.SystemMgr.get_defs({rectypes: this.options.params.rectypes.join(), 
                        mode:4, 
                        fieldtypes:this.options.params.fieldtypes.join() }, 
                function(response){
                    if(response.status == top.HAPI4.ResponseStatus.OK){

                        //create unique identificator=code for each leaf fields - rt:ft:rt:ft....
                        function __set_queries(parentquery, recordtype, fields){
                           
                           var j; 
                           for(j=0; j<fields.length; j++){ 

                               var field = fields[j];
                               var recordtype_new = null;
                               var parentquery_new = null;

                               if(field.type=='rectype'){
                                   recordtype_new  = field.key;
                               }else if(field.type=='resource' || field.type=='relmarker'){
                                   //@todo for relmarker - separate case
                                   var q = recordtype + ':' + field.key.substr(2);
                                   if(parentquery!=''){
                                        q = parentquery + ':' + q;
                                   }
                                   parentquery_new = q;

                                   //unconstrained or one-type constraint                                    
                                   if(top.HEURIST4.util.isempty(field.rt_ids) || field.rt_ids.indexOf(',')<0){
                                       recordtype_new = field.rt_ids;
                                       recordtype = field.rt_ids;
                                   }
                                   
                               }

                               if(field.children && field.children.length>0){
                                   __set_queries(parentquery_new?parentquery_new:parentquery, 
                                                 recordtype_new?recordtype_new:recordtype, field.children);
                                   //__set_queries(parentquery, recordtype_new?recordtype_new:recordtype, field.children);
                               }else{

                                   var q = field.key;
                                   if(q=="recTitle") q = "title"                           
                                   else if(q=="recModified") q = "modified"                               
                                   else q = q.substr(2);

                                   q = recordtype + ':' + q
                                   if(parentquery!=''){
                                        q = parentquery + ':' + q;
                                        //var nums = parentquery.split('(');
                                        //q = q + new Array( nums.length+1 ).join(')'); 
                                   }
                                   fields[j].code = q;
                               }

                           }
                        }//function
                        
                        __set_queries('', '', response.data.rectypes);                        
                        
                        
                        if(!treediv.is(':empty')){
                            treediv.fancytree("destroy");
                        }

                        if(response.data.rectypes) {
                            response.data.rectypes[0].expanded = true;
                        }

                        //setTimeout(function(){
                        treediv.fancytree({
                            //            extensions: ["select"],
                            checkbox: true,
                            selectMode: 3,  // hierarchical multi-selection    
                            source: response.data.rectypes,
                            beforeSelect: function(event, data){
                                // A node is about to be selected: prevent this, for folder-nodes:
                                if( data.node.hasChildren() ){
                                    return false;
                                }
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
                        var facets = that.options.params.facets_new;
                        if(facets && facets.length>0){
                        var tree = treediv.fancytree("getTree");
                        tree.visit(function(node){
                                        
                                    if(!top.HEURIST4.util.isArrayNotEmpty(node.children)){ //this is leaf 
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
                        
                        
                        
                        that.current_tree_rectype_ids = rectypeIds;

                    }else{
                        top.HEURIST4.util.redirectToError(response.message);
                    }
                });

            }
        }
    }

    // 3d step
    , _initStep3_FacetsRanges: function() {

        var listdiv = $(this.step3).find("#facets_list");
        listdiv.empty();

        var that = this;
        var facets = [];
        var facets_new = [];
        var k, len = this.options.params.rectypes.length;
        var isOneRoot = (this.options.params.rectypes.length==1);
        //if more than one root rectype each of them acts as facet
        if(this.options.params.rectype_as_facets && len>1){
            facets.push([{ title:top.HR('Record types'), type:"rectype", query:"t" }]);
            /*for (k=0;k<len;k++){
            var rtID = this.options.params.rectypes[k];
            facets.push([{ title:top.HEURIST4.rectypes.names[rtID], type:"rectype", query:"t:"+rtID, fieldid:rtID }]);
            }*/
        }

        // ------------------------------------------------------
        /*  sample:
    [{"title":"Family name",                  "type":"freetext","query":"t:10 f:1" ,"fieldid":"f:1", "currentvalue":null,"history":[]},
     {"title":"Person affected",              "type":"resource","query":"t:13 f:16","fieldid":"f:16"},
     {"title":"Punishment event(s) - SAVE record first","type":"resource", "query":"f:93","fieldid":"f:93"}],
        */
         /*  garbage
        var tree = $(this.step2).find('#field_treeview').fancytree("getTree");
                      
        tree.visit(function(node){
                    
                    if(!top.HEURIST4.util.isArrayNotEmpty(node.children)){ //this is leaf 
                          //find it among facets  
                        
                        var facets = this.options.params.facets;
                        for(var i=0; i<facets.length; i++){
                            facets[i][0].fieldid==node.key
                        }                        
                        
                        node.setSelected(true);
                    }
        });            
        
        var facets = this.options.params.facets;
        for(var i=0; i<facets.length; i++){
            var facet = facets[i];
            for(var j=facet.length-1; j>=0; j--){
            
            }
        }
        */

       
        
        function __get_queries(node){

            var res = [];

            var parent = node.parent; //it may rectype of pointer field
            var fieldnode;

            if( parent.data.type=="rectype"){

                fieldnode = parent.parent
                if(fieldnode.isRoot()){
                    var q = node.key;
                    if(node.data.type=="relmarker") q = "relmarker"
                    else if(q=="recTitle") q = "title"
                    else if(q=="recModified") q = "modified";

                    if(isOneRoot){
                        //root rectypes will be added only once - when full_query is creating
                        // q = "t:"+parent.key+" "+q; 
                    }
                    
                    q_full = "t:"+that.options.params.rectypes.join(",")+" "+q; 
                    
                    //final exit when we access root
                    return [{ title:(node.data.name?node.data.name:node.title), type:node.data.type, query: q_full, fieldid:q }];  
                }
            }else{
                fieldnode = parent;
            }

            var q = node.key;
            //if(false && fieldnode.data.type=="relmarker") q = "f:relmarker"  //ARTEM 0429
            if(node.data.type=="relmarker") q = "relmarker"
            else if(q=="recTitle") q = "title"
            else if(q=="recModified") q = "modified";
            
            if(fieldnode.data.rt_ids){ //constrained
                q_full = "t:"+fieldnode.data.rt_ids+" "+q; 
            }else{
                q_full = q;
            }
            res.push( { title:(node.data.name?node.data.name:node.title), type:node.data.type, query:q_full, fieldid:q });    

            var res2 = __get_queries(fieldnode);
            for(var i=0; i<res2.length; i++){
                res.push(res2[i]);
            }

            /*
            if( parent.data.type=="rectype"){
            fieldnode = parent.parent;
            //if rectype find parent field and get rectype constraints
            if(fieldnode.isRoot()){ //this is top most rectype
            if(isOneRoot){
            return [{ title:node.title, type:node.data.type, query:"t:"+parent.key+" "+node.key }]; 
            }else{
            return [{ title:node.title, type:node.data.type, query:node.key }]; 
            }
            } else { //pointer field
            if(fieldnode.data.rt_ids){ //constrained
            res.push( { title:node.title, type:node.data.type, query:"t:"+fieldnode.data.rt_ids+" "+node.key }); 
            }else{
            res.push( { title:node.title, type:node.data.type, query:node.key });    
            }
            res.push(__get_queries(fieldnode));
            }
            }else{ //pointer field
            fieldnode = parent;

            if(fieldnode.data.rt_ids){ //constrained
            res.push( { title:node.title, type:node.data.type, query:"t:"+fieldnode.data.rt_ids+" "+node.key }); 
            }else{
            res.push( { title:node.title, type:node.data.type, squery:node.key });    
            }
            res.push(__get_queries(fieldnode));
            }
            */
            return res;
        } //end __get_queries ------------------------------------

        var tree = $(this.step2).find('#field_treeview').fancytree("getTree");
        var fieldIds = tree.getSelectedNodes(false);
        len = fieldIds.length;              

        if(len>0){

            for (k=0;k<len;k++){
                var node =  fieldIds[k];      //FancytreeNode
                //name, type, query,  ranges
                if(!top.HEURIST4.util.isArrayNotEmpty(node.children)){  //ignore top levels selection
                
                    var ids = node.data.code.split(":");
                
                    var facet = __get_queries(node);
                    facets.push( facet ); // { id:node.key, title:node.title, query: squery, fieldid } );
                    facets_new.push( { 
                            id:ids[ids.length-1],  
                            code:node.data.code,
                            title:(node.data.name?node.data.name:node.title), 
                            type:node.data.type,
                            levels:[],        //search for facet values
                            search: []        //search for target rectype
                             } );
                }
            }

            //draw list
            len = facets.length; 
            for (k=0;k<len;k++){

                var s = facets[k][0].type+'  ';
                var title = '';
                for (var i=0;i<facets[k].length;i++){

                    title = title + ' ' + facets[k][i].title;

                    s = s + facets[k][i].query;
                    if(i<facets[k].length-1){
                        s = s + "=>";
                    }
                }
                listdiv.append($('<div>').html( title + '  ' + s + '<hr />'));
            }

            this.options.params.facets = facets;  
            this.options.params.facets_new = facets_new;

            return true;
        }else{
            return false;
        }
    }

    //4. show facet search preview
    ,_initStep4_FacetsPreview: function(){

        this._defineDomain();

        var listdiv = $(this.step4).find("#facets_preview");

        var noptions= { query_name:"test", params:this.options.params, ispreview: true}

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
        var message = $dlg.find('.messages');

        var allFields = $dlg.find('input');
        allFields.removeClass( "ui-state-error" );

        var bValid = top.HEURIST4.util.checkLength( svs_name, "Name", message, 3, 25 );
        if(!bValid){
            this._showStep(0);
            return false;
        }

        var bValid = top.HEURIST4.util.isArrayNotEmpty(this.options.params.facets);
        if(!bValid){
            this._showStep(2);
            return false;
        }

        var svs_ugrid = svs_ugrid.val();
        if(parseInt(svs_ugrid)>0){
            this.options.params.domain = 'all';    
        }else{
            this.options.params.domain = svs_ugrid;    
            svs_ugrid = top.HAPI4.currentUser.ugr_ID;
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
        top.HAPI4.SystemMgr.ssearch_save(request,
            function(response){
                if(response.status == top.HAPI4.ResponseStatus.OK){

                    var svsID = response.data;

                    if(!top.HAPI4.currentUser.usr_SavedSearch){
                        top.HAPI4.currentUser.usr_SavedSearch = {};
                    }

                    top.HAPI4.currentUser.usr_SavedSearch[svsID] = [request.svs_Name, request.svs_Query, request.svs_UGrpID];

                    request.new_svs_ID = svsID;

                    that._trigger( "onsave", null, request );

                    that.element.dialog("close");

                }else{
                    top.HEURIST4.util.redirectToError(response.message);
                }
            }

        );


    }

});

function showSearchFacetedWizard( params ){

    if(!$.isFunction($('body').rectype_manager)){
        $.getScript(top.HAPI4.basePath+'apps/rectype_manager.js', function(){ showSearchFacetedWizard(params); } );
    }else if(!$.isFunction($('body').fancytree)){

        $.getScript(top.HAPI4.basePath+'ext/fancytree/jquery.fancytree-all.min.js', function(){ showSearchFacetedWizard(params); } );

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