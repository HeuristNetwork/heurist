/**
* Search header
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

$.widget( "heurist.searchRecords", $.heurist.searchEntity, {

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
        
        this.selectRectype.empty();
        window.hWin.HEURIST4.ui.createRectypeSelect(this.selectRectype.get(0), 
            this.options.rectype_set, 
            window.hWin.HEURIST4.util.isempty(this.options.rectype_set)
            ?window.hWin.HR('Any Record Type')
                :'',  // (this.options.parententity>0)?window.hWin.HR('select record type')
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

                var search_val = that.input_search.val();
                if(!window.hWin.HEURIST4.util.isempty(search_val)){
                    window.hWin.HEURIST4.util.copyStringToClipboard(search_val);
                }

                that._trigger( "onaddrecord", null, {'_rectype_id': that.selectRectype.val(), 'fill_in_data': search_val} );
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
/*  pair of add+search buttons              
                $('<button>')
                    .button({label: label, 
                             icon: is_browse?'ui-icon-search':"ui-icon-plus"})
                    .attr({'data-rtyid': rectypeID, 'title': label})
                    .css({'font-size':'10px',display:'inline-block',width:100,'text-align':'left','margin':'6px 0px 3px 8px','text-transform':'none'})
                    .addClass('truncate ui-button-action')
                    .click(function(e) {
                        
                        var rtyid = $(e.target).attr('data-rtyid');
                        if(is_browse){
                            that.selectRectype.val( rtyid ).hSelect('refresh');
                            that.selectRectype.change();
                        }else{
                            that._trigger( "onaddrecord", null, rtyid );    
                        }
                    })
                    .appendTo(cont);
                    
                if(this.options.pointer_mode == 'addorbrowse'){
                                
                    $('<button>')
                        .button({label:window.hWin.HR("Filter by record type"), icon: "ui-icon-search", showLabel:false})
                        .attr('data-rtyid', rectypeID)
                        .css({'font-size':'10px',display:'inline-block',width:20,'margin-right':10,'margin':'6px 0 3px 0','text-transform':'none'})
                        .click(function(e) {
                            var el = $(e.target);
                            el = el.is('button') ?el :el.parents('button');
                            var rtyid = el.attr('data-rtyid'); 
                            
                            that.selectRectype.val( rtyid ).hSelect('refresh');
                            that.selectRectype.change();
                            //that.startSearch();//
                        })
                        .appendTo(cont);
                    
                }
*/                
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
                    that._trigger( "onaddrecord", null, that.selectRectype.val() );
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
            if(that.btn_add_record.is(':visible')){
                /*
                if(sel.val()>0){
                    lbl = window.hWin.HR(is_browse?'Select':'Add')+' '+ sel.find( "option:selected" ).text().trim();
                }else{
                    lbl = window.hWin.HR(is_browse?"Search Record":"Add Record");
                }
                */
                if(sel.val()>0){
                    lbl = window.hWin.HR('Add')+' '+ sel.find( "option:selected" ).text().trim();
                }else{
                    lbl = window.hWin.HR('Add Record');
                }
                that.btn_add_record.button('option','label',lbl);
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
                
                this.element.find('#row_parententity_helper').css({'display':'table-row'});
                this.element.find('#row_parententity_helper2').css({'display':'table-row'});
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
                if(this.selectRectype.val()>0){
                    this.selectRectype.change(); 
                }
            }
        }
        
        //if(this.searchForm && this.searchForm.length>0)
        //this.searchForm.find('#input_search').focus();
        this.input_search.focus();

       this._trigger( "onstart" ); //trigger ajust          
        
       if(this.options.fixed_search) this.startSearch() 
    },  

    //
    // public methods
    //
    startSearch: function(){
        
        var ele = this.element.find('#row_parententity_helper2')
        if(ele.is(':visible')){
            //this.element.height('12em');
            ele.hide();
        }
            

        this._super();

        var request = {}

        var qstr = '', domain = 'a', qobj = [];
        
        var links_count = null;
        
        //by record type
        if(this.selectRectype.val()!=''){
            qstr = qstr + 't:'+this.selectRectype.val();
            qobj.push({"t":this.selectRectype.val()});
            
            if(this.options.pointer_field_id>0 && this.options.pointer_source_rectype>0){
                if(this.element.find('#cb_getcounts').is(':checked')){
                    links_count = {source:this.options.pointer_source_rectype, 
                                   target:this.selectRectype.val(),
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
