/**
* Search header for DefTerms manager
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

$.widget( "heurist.searchRecords", $.heurist.searchEntity, {

    //
    _initControls: function() {
        this._super();

        var that = this;

        //-----------------
        this.element.css('min-width','700px');
        this.selectRectype = this.element.find('#sel_rectypes');


        /*
        var rectypeList = this.options.rectype_set;
        var topOption = null;
        if(!window.hWin.HEURIST4.util.isempty(rectypeList)){

            if(!window.hWin.HEURIST4.util.isArray(rectypeList)){
                rectypeList = rectypeList.split(',');
            }
        }else{
            rectypeList = [];
        }
        if(rectypeList.length==0){
            topOption =  window.hWin.HR('Any Record Type');
        }*/
        
        var rt_list = this.options.rectype_set;
        var is_expand_rt_list = false;
        if(!window.hWin.HEURIST4.util.isempty(rt_list)){
            if(!window.hWin.HEURIST4.util.isArray(rt_list)){
                rt_list = rt_list.split(',');
            }
            is_expand_rt_list = (rt_list.length>1 && rt_list.length<10);
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
            
        if(is_expand_rt_list){
            
            this.btn_select_rt.hide();
            this.btn_add_record.hide();
            var cont = this.btn_add_record.parent();
            
            for(var idx=0; idx<rt_list.length; idx++){
                
                var rectypeID = rt_list[idx];
                var name = window.hWin.HEURIST4.rectypes.names[rectypeID];
                
                $('<button>')
                    .button({label: window.hWin.HR('Add')+' '+ name.trim(), icon: "ui-icon-plus"})
                    .attr('data-rtyid', rectypeID)
                    .css({'font-size':'11px',display:'inline-block',width:200})
                    .click(function(e) {
                        that._trigger( "onaddrecord", null, $(e.target).attr('data-rtyid') );
                    })
                    .appendTo(cont);

                $('<button>')
                    .button({label:window.hWin.HR("Filter by record type"), icon: "ui-icon-carat-1-s", showLabel:false})
                    .attr('data-rtyid', rectypeID)
                    .css({'font-size':'11px',display:'inline-block',width:20})
                    .click(function(e) {
                        var el = $(e.target);
                        el = el.is('button') ?el :el.parents('button');
                        var rtyid = el.attr('data-rtyid'); 
                        
                        that.selectRectype.val( rtyid ).change();
                        //that.startSearch();//
                    })
                    .appendTo(cont);
                
            }
            
        }else{
            
            this.btn_add_record
            .button({label: window.hWin.HR("Add Record"), icon: "ui-icon-plus"})
            .click(function(e) {
                if(that.selectRectype.val()>0){
                    that._trigger( "onaddrecord", null, that.selectRectype.val() );
                }else{
                    that.btn_select_rt.click();
                }
            });  

            this.btn_select_rt
                .button({label:window.hWin.HR("Select record type"), icon: "ui-icon-carat-1-s", showLabel:false});
                
           //open and adjust position of dropdown    
            this._on( this.btn_select_rt, {
                    click:  function(){
                    this.selectRectype.hSelect('open');
                    this.selectRectype.hSelect('menuWidget').position({my: "left top", at: "left bottom", of: this.btn_add_record });
                    return false;
                        
                }});
           
           //adjust position of dropdown    
           this.sel_rectypes_btn = this.element.find( "#sel_rectypes-button");
           this._on( this.sel_rectypes_btn, {
                    click:  function(){
                    this.selectRectype.hSelect('menuWidget').position({my: "left top", at: "left bottom", of: this.sel_rectypes_btn });
                }});
                
            this.btn_add_record.parent().controlgroup();
            
            
        
        }//is_expand_rt_list    
        
        function __onSelectRecType(sel){
            if(that.btn_add_record.is(':visible')){
                if(sel.val()>0){
                    lbl = window.hWin.HR('Add')+' '+ sel.find( "option:selected" ).text().trim();
                }else{
                    lbl = window.hWin.HR("Add Record");
                }
                that.btn_add_record.button('option','label',lbl);
            }
        }
        //force search if rectype_set is defined
        this._on( this.selectRectype, {
            change: function(event){
                __onSelectRecType(this.selectRectype);
                this.startSearch();
            }
        });

        this._on( this.element.find('input[type=checkbox]'), {
            change: function(event){
                this.startSearch();
        }});

        if(this.options.parententity>0){
            this.element.find('#row_parententity_helper').css({'display':'table-row'});
            this.element.find('#row_parententity_helper2').css({'display':'table-row'});
            this.element.find('#row_parententity_helper2').parent('.ent_header').css({'z-index':99});
            
            this.btn_search_start2 = this.element.find('#btn_search_start2').css({height:'20px',width:'20px'})
                .button({showLabel:false, icon:"ui-icon-search", iconPosition:'end'});
                
            this._on( this.btn_search_start2, {click: this.startSearch });
                
            __onSelectRecType(this.selectRectype);
        }else{
            //start search
            if(this.selectRectype.val()>0){
                this.selectRectype.change(); 
            }
        }
        
        //if(this.searchForm && this.searchForm.length>0)
        //this.searchForm.find('#input_search').focus();
        this.input_search.focus();

    },  

    //
    // public methods
    //
    startSearch: function(){
        
        var ele = this.element.find('#row_parententity_helper2')
        if(ele.is(':visible')){
            this.element.height('12em');
            ele.hide();
        }
            

        this._super();

        var request = {}

        var qstr = '', domain = 'a', qobj = [];
        
        //by record type
        if(this.selectRectype.val()!=''){
            qstr = qstr + 't:'+this.selectRectype.val();
            qobj.push({"t":this.selectRectype.val()});
        }   

        //by title        
        if(this.input_search.val()!=''){
            qstr = qstr + ' title:'+this.input_search.val();
            qobj.push({"title":this.input_search.val()});
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
        
        if(this.element.find('#cb_modified').is(':checked')){
            qstr = qstr + ' sortby:-m after:"1 week ago"';
            qobj.push({"sortby":"-m"}); // after:\"1 week ago\"
        }else{
            qstr = 'sortby:t';
            qobj.push({"sortby":"t"}); //sort by record title
        }
        
        if(this.element.find('#cb_bookmarked').is(':checked')){
            domain = 'b';
        }            
        
        if(qstr==''){
            this._trigger( "onresult", null, {recordset:new hRecordSet()} );
        }else{
            this._trigger( "onstart" );

            var request = { 
                //q: qstr, 
                q: qobj,
                w: domain,
                limit: 100000,
                needall: 1,
                detail: 'ids',
                id: window.hWin.HEURIST4.util.random()}
            //source: this.element.attr('id') };

            var that = this;                                                
            //that.loadanimation(true);

            window.hWin.HAPI4.RecordMgr.search(request, function( response ){
                //that.loadanimation(false);
                if(response.status == window.hWin.ResponseStatus.OK){
                    that._trigger( "onresult", null, 
                        {recordset:new hRecordSet(response.data), request:request} );
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }

            });
            
        }            
    }
    

});
