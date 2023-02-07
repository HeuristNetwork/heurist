/**
* Search header
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

$.widget( "heurist.searchRecords", $.heurist.searchEntity, {

    options:{
        init_filter: ''
    },

    _select_mode: 1, //0 - add, 1 - search
    
    //
    _initControls: function() {
        this._super();

        var that = this;

        //-----------------
        this.element.css('min-width','255px');
        this.selectRectype = this.element.find('#sel_rectypes');
        
        var rt_list = this.options.rectype_set;
        var is_expand_rt_list = false;
        var is_only_rt = false;
        if(!window.hWin.HEURIST4.util.isempty(rt_list)){
            if(!window.hWin.HEURIST4.util.isArray(rt_list)){
                rt_list = rt_list.split(',');
            }
            is_expand_rt_list = (rt_list.length>1 && rt_list.length<20);
            is_only_rt = (rt_list.length==1);
        }else{
            rt_list = [];
        }
        
        let recType_topOption = window.hWin.HR('Any Record Type');
        if(!window.hWin.HEURIST4.util.isempty(this.options.rectype_set)){

            if(this.options.rectype_set.indexOf(',') !== -1){
                recType_topOption = [{key: this.options.rectype_set, title: 'All Record Types', selected: true}]; // search all record types
            }else{
                recType_topOption = '';
            }
        }

        this.selectRectype.empty();
        window.hWin.HEURIST4.ui.createRectypeSelect(this.selectRectype.get(0), 
            this.options.rectype_set, 
            recType_topOption, 
            false);
            
        this.btn_add_record = this.element.find('#btn_add_record');    
        this.btn_select_rt = this.element.find( "#btn_select_rt");
        
        var is_browse = (that.options.pointer_mode == 'browseonly');
        var is_addonly = (that.options.pointer_mode == 'addonly');
        
        if(that.options.pointer_mode != 'addorbrowse'){
            $('#addrec_helper > .heurist-helper1').css('visibility','hidden');
        }
        if(is_addonly){
            this.element.find('#lbl_add_record').text('Add new');
            this.element.find('.not-addonly').hide();
            this.options.pointer_filter = '';
            this._select_mode = 0;
        }else{
            this.element.find('#cb_initial').prop('checked', 
                !window.hWin.HEURIST4.util.isempty(this.options.pointer_filter));
        }
        if(window.hWin.HEURIST4.util.isempty(this.options.pointer_filter)){
            this.element.find('#cb_initial_filter').text('');
            this.element.find('.i-filter').hide();
        }else{
            //initial pre-filter (see rst_PointerBrowseFilter)
            this.element.find('#cb_initial_filter').text(this.options.pointer_filter);    
            this.element.find('.i-filter').show();
        }
        if(this.options.pointer_field_id>0 && this.options.pointer_source_rectype>0){
            this.element.find('.i-counts').show();
        }else{
            this.element.find('.i-counts').hide();
        }

        this.btn_add_record
            .button({label: window.hWin.HR('Add Record'), 
                        icon: is_browse?null:"ui-icon-plus"})
            .addClass('ui-button-action')
            .click(function(e) {

                var search_val = that.element.find('#fill_in_data').val();
                search_val = search_val == '' ? that.options.init_filter : search_val;
                if(!window.hWin.HEURIST4.util.isempty(search_val)){
                    window.hWin.HEURIST4.util.copyStringToClipboard(search_val);
                }

                if(that.selectRectype.val().indexOf(',') === -1){
                    that._trigger( "onaddrecord", null, {'_rectype_id': that.selectRectype.val(), 'fill_in_data': search_val} );
                }else{
                    window.hWin.HEURIST4.msg.showMsgFlash('Cannot create a record of all types', 3000);
                }
            }); 
        if(is_browse){
            this.element.find('#lbl_add_record').text('Select in list');
            this.btn_add_record.hide();
        }else{
            this.btn_add_record.show();
        }

        // create list of tabs for every rectype in this.options.rectype_set
        if(!is_addonly && is_expand_rt_list){ //(rt_list.length>1 && rt_list.length<20)
            
            this.element.find('label[for="sel_rectypes-button"]').hide();
            this.element.find('#sel_rectypes-button').hide();
            this.btn_select_rt.hide();
            var cont = this.element.find('#row_tabulator');

            var groupTabHeader = $('<ul>').appendTo(cont);
            
            if(rt_list.length > 1){
                    $('<li>').html('<a href="#rty'+ rt_list.join(',') +'"><span style="font-weight:bold">All types</span></a>')
                        .appendTo(groupTabHeader);
                    $('<div id="rty'+ rt_list.join(',') +'">').appendTo(cont);
            }

            for(var idx=0; idx<rt_list.length; idx++){
                
                var rectypeID = rt_list[idx];
                if(rectypeID>0){
                    var name = $Db.rty(rectypeID,'rty_Name');
                    var label = window.hWin.HEURIST4.util.htmlEscape(name.trim());
                    if(!name) continue;

                    $('<li>').html('<a href="#rty'+rectypeID
                                        +'"><span style="font-weight:bold">'
                                        +label+'</span></a>')
                            .appendTo(groupTabHeader);
                    $('<div id="rty'+rectypeID+'">').appendTo(cont);
                }
            }//for
            
            //on switch - change filter
            cont.tabs({activate:function( event, ui ) {
                var rtyid = ui.newPanel.attr('id').substr(3);
                that._select_mode = 1; //search
                that.selectRectype.val( rtyid ).hSelect('refresh');
                that.selectRectype.change();
            }});
            groupTabHeader.css('background','none');
            
        }else{

            this.btn_select_rt
            .button({label:window.hWin.HR("Select record type"), icon: "ui-icon-carat-1-s", showLabel:false});

            //open and adjust position of dropdown    
            this._on( this.btn_select_rt, {
                click:  function(){
                    this._select_mode = 0;
                    this.selectRectype.hSelect('open');
                    this.selectRectype.hSelect('menuWidget').position({my: "left top", at: "left bottom", of: this.btn_add_record });
                    return false;

            }});

            //adjust position of dropdown    
            this.sel_rectypes_btn = this.element.find( "#sel_rectypes-button");
            this._on( this.sel_rectypes_btn, {
                click:  function(){
                    this._select_mode = 1;
                    this.selectRectype.hSelect('menuWidget').position({my: "left top", at: "left bottom", of: this.sel_rectypes_btn });
            }});

            this.btn_add_record.parent().controlgroup();

            if(is_only_rt){
                this.element.find('label[for="sel_rectypes-button"]').hide();
                this.element.find('#sel_rectypes-button').hide();
                this.btn_select_rt.hide();

                if(is_addonly){

                    var search_val = that.element.find('#fill_in_data').val();
                    search_val = search_val == '' ? that.options.init_filter : search_val;
                    if(!window.hWin.HEURIST4.util.isempty(search_val)){
                        window.hWin.HEURIST4.util.copyStringToClipboard(search_val);
                    }

                    if(that.selectRectype.val().indexOf(',') === -1){
                        that._trigger( "onaddrecord", null, {'_rectype_id': that.selectRectype.val(), 'fill_in_data': search_val} );
                    }else{
                        window.hWin.HEURIST4.msg.showMsgFlash('Cannot create a record of all types', 3000);
                    }
                }else if (is_browse) {
                    //this.element.find('#addrec_helper').hide();
                }
            }else if(is_addonly){
                that.btn_select_rt.click(); //show dropdown
            }

        }//is_expand_rt_list    
        
        // change label for btn_add_record 
        //
        function __onSelectRecType(sel){

            let is_any = sel.val().indexOf(',') !== -1;
            if(is_browse || is_any){
                that.btn_add_record.hide();
            }else{
                that.btn_add_record.show();
            }

            if(that.btn_add_record.is(':visible')){

                if(sel.val()>0){
                    lbl = window.hWin.HR('Add')+' '+ sel.find( "option:selected" ).text().trim();
                }else{
                    lbl = window.hWin.HR('Add Record');
                }
                that.btn_add_record.button('option','label',lbl);
            }

            if(is_browse || is_any){
                that.element.find('#lbl_add_record').text('Select in list');
            }else if(is_addonly){
                that.element.find('#lbl_add_record').text('Add new');
            }else{
                that.element.find('#lbl_add_record').text('Select in list or add new');
            }
        }
        //force search if rectype_set is defined
        this._on( this.selectRectype, {
            change: function(event){
                __onSelectRecType(this.selectRectype);
                if(this._select_mode==1){
                    this.startSearch();    
                }else{
                    this.btn_add_record.click();
                }
                
            }
        });

        this._on( this.element.find('input[type=radio], input[type=checkbox]'), {
            change: function(event){
                this.startSearch();
        }});
		
        // User Preference for filter buttons
        var filter_pref = window.hWin.HAPI4.get_prefs_def('rSearch_filter', 'rb_alphabet');
        if (filter_pref != 'rb_alphabet'){
            this.element.find('#'+filter_pref).prop('checked', true);
        }

        if(is_addonly){
            __onSelectRecType(this.selectRectype);
        }else{
            if(this.options.parententity>0){
                
                this.element.find('#row_parententity_helper4').show();
                this.element.find('#parententity_header').show();
                this._on(this.element.find('#parententity_header'), {
                    'click': function(event){

                        let $header_icon = this.element.find('#parententity_header .ui-icon');
                        let is_expanding = $header_icon.hasClass('ui-icon-triangle-1-e');
                        let is_results_only = $header_icon.parent().hasClass('search-results-only');

                        $header_icon.toggleClass('ui-icon-triangle-1-e ui-icon-triangle-1-s');

                        if(is_expanding){
                            if(!is_results_only){
                                this.element.find('#row_parententity_helper, #row_parententity_helper2').css({'display':'table-row'});
                                this.element.parent().find('.recordList').hide();
                            }else{
                                this.element.find('#row_parententity_helper2').hide();
                                this.element.find('#row_parententity_helper').css({'display':'table-row'});
                                this.element.parent().find('.recordList').show();
                            }
                            this.element.find('#row_parententity_helper4').hide();
                        }else{
                            this.element.find('#row_parententity_helper, #row_parententity_helper2').hide();
                            this.element.parent().find('.recordList').hide();
                            this.element.find('#row_parententity_helper4').show();
                        }
                    }
                });
                this.element.parent().find('.recordList').hide();
                this.element.find('#row_parententity_helper2').parent('.ent_header').css({'z-index':99});
                
                this.btn_search_start2 = this.element.find('#btn_search_start2').css({height:'20px',width:'20px'})
                    .button({showLabel:false, icon:"ui-icon-search", iconPosition:'end'});
                    
                this._on( this.btn_search_start2, {click: this.startSearch });
                    
                __onSelectRecType(this.selectRectype);
            }else{
                if(this.options.parentselect>0){
                    var ele = this.element.find('#row_parententity_helper3').css({'display':'table-row'});
                    ele.find('span').text( $Db.rty(this.options.parentselect,'rty_Name') );
                }
                //start search
                if(!window.hWin.HEURIST4.util.isempty(this.selectRectype.val())){
                    this.selectRectype.change(); 
                }
            }
        }
        
        //if(this.searchForm && this.searchForm.length>0)
        //this.searchForm.find('#input_search').focus();
        this.input_search.focus();

        if(!window.hWin.HEURIST4.util.isempty(this.options.init_filter)){
            this.input_search.val(this.options.init_filter).css({'max-width': '20em', 'width': '20em'}); // enter value

            //move search box
            this.input_search.parent().css({
                'display': 'block',
                'position': 'relative',
                'z-index': 1,
                'text-align': ''
            });

            this.element.find('#fill_in_data').val(this.options.init_filter).css({'max-width': '20em', 'width': '20em'}).parent().show();
        }else{
            this.element.find('#fill_in_data').parent().hide();
        }

        this._trigger( "onstart" ); //trigger ajust          
        
        if(this.options.fixed_search || this.options.parententity > 0 || !window.hWin.HEURIST4.util.isempty(this.options.init_filter)){
            setTimeout(() => { that.startSearch(); }, 500);
        }
    },  

    //
    // public methods
    //
    startSearch: function(){
        
        var ele = this.element.find('#row_parententity_helper2').hide();
        ele = this.element.find('#parententity_header');

        if(ele.is(':visible')){
            ele.addClass('search-results-only');
            ele.find('.ui-icon').addClass('ui-icon-triangle-1-s').removeClass('ui-icon-triangle-1-e');
            this.element.parent().find('.recordList').show();
        }

        this._super();

        var request = {}

        var qstr = '', domain = 'a', qobj = [];
        
        var links_count = null;

        //by record type
        let rectype_filter = this.selectRectype.val();
        if(rectype_filter!=''){
            qstr = qstr + 't:'+rectype_filter;
            qobj.push({"t":rectype_filter});
            
            if(this.options.pointer_field_id>0 && this.options.pointer_source_rectype>0){
                if(this.element.find('#cb_getcounts').is(':checked')){
                    links_count = {source:this.options.pointer_source_rectype, 
                                   target:this.rectype_filter,
                                   dty_ID:this.options.pointer_field_id};
                }            
            }    
        }

        //by title        
        if(this.input_search.val().trim()!=''){
            var is_whole_phrase = true;
            var s = this.input_search.val().trim();
            if(s.length>4 && s[0]=='"' && s[s.length-1]=='"'){
                s = s.substring(1,s.length-2);
                is_whole_phrase = true;
            }else{
                s = s.split(' ');
                is_whole_phrase = !(s.length>1);
            }    
                
            if(is_whole_phrase){
                s = this.input_search.val().trim()
                qstr = qstr + ' title:'+s;
                qobj.push({"title":s});
            }else{        
                for(var i=0;i<s.length;i++)
                if(s[i]!=''){
                    qobj.push({"title":s[i]});    
                    qstr = qstr + ' title:'+s[i];
                }
            }
        
        }        

        //by ids of recently selected
        if(this.element.find('#cb_selected').is(':checked')){
            var previously_selected_ids = window.hWin.HAPI4.get_prefs('recent_Records');
            if (previously_selected_ids && 
                window.hWin.HEURIST4.util.isArrayNotEmpty(previously_selected_ids.split(',')))
            {
                qstr = qstr + ' ids:' + previously_selected_ids;
                qobj.push({"ids":previously_selected_ids});
            }
        }

        //exclude already children
        if(this.options.parententity>0){
            //filter out records with parent entiy (247) field
            var DT_PARENT_ENTITY  = window.hWin.HAPI4.sysinfo['dbconst']['DT_PARENT_ENTITY'];
            var pred = {}; pred["f:"+DT_PARENT_ENTITY]="NULL";
            qobj.push(pred);
            
            this.element.find('#parententity_helper').css({'display':'table-row'});
        }
        
        var limit = 100000;
        var needall = 1
        
        if(this.element.find('#rb_modified').is(':checked')){
            qstr = qstr + ' sortby:-m after:"1 week ago"';
            qobj.push({"sortby":"-m"}); // after:\"1 week ago\"
            limit = 100;
            needall = 0;

            window.hWin.HAPI4.save_pref('rSearch_filter', 'rb_modified');
        }else{
            qstr = 'sortby:t';
            qobj.push({"sortby":"t"}); //sort by record title
        }

        if(this.element.find('#rb_alphabet').is(':checked')){
            window.hWin.HAPI4.save_pref('rSearch_filter', 'rb_alphabet');
        }
		
        if(this.element.find('#cb_bookmarked').is(':checked')){
            domain = 'b';
        }       
        
        if(this.element.find('#cb_initial').is(':checked')){
            qobj = window.hWin.HEURIST4.util.mergeHeuristQuery(qobj, 
                            (this.options.pointer_filter?this.options.pointer_filter:''));
        }
        
        if(this.options.fixed_search){
            qstr = 'x';
            qobj = this.options.fixed_search;
        }
        
        if(qstr==''){
            this._trigger( "onresult", null, {recordset:new hRecordSet()} );
        }else{
            this._trigger( "onstart" );

            var request = { 
                //q: qstr, 
                q: qobj,
                w: domain,
                limit: limit,
                needall: needall,
                detail: 'ids',
                links_count: links_count,
                id: window.hWin.HEURIST4.util.random()}
            //source: this.element.attr('id') };

            var that = this;                                                
            //that.loadanimation(true);

            window.hWin.HAPI4.RecordMgr.search(request, function( response ){
                //that.loadanimation(false);
                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    if(response.data.links_count){
                        that._trigger( "onlinkscount", null, 
                            {links_count: response.data.links_count,
                             links_query: response.data.links_query});
                    }else{
                        that._trigger( "onlinkscount", null, 
                            {links_count: null,
                             links_query: null});
                    }
                    
                    that._trigger( "onresult", null, 
                        {recordset:new hRecordSet(response.data), request:request} );
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }

            });
            
        }            
    }
    

});
