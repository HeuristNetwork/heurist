/**
* Search header for manageDefRecTypes manager
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

$.widget( "heurist.searchDefRecTypes", $.heurist.searchEntity, {

    //
    _initControls: function() {
        
        var that = this;
        
        //this.widgetEventPrefix = 'searchDefRecTypes';
        
        this._super();

        //hide all help divs except current mode
        var smode = this.options.select_mode; 
        this.element.find('.heurist-helper1').find('span').hide();
        this.element.find('.heurist-helper1').find('span.'+smode+',span.common_help').show();
        
        this.btn_add_record = this.element.find('#btn_add_record');
        this.btn_find_record = this.element.find('#btn_find_record');

        if(this.options.edit_mode=='none' || this.options.import_structure){
            this.btn_add_record.parent().hide();
            //this.btn_find_record.hide();
            //this.element.find('#inner_title').hide();
            
            var ele = this.element.find('#div_show_all_groups');
            ele.parent().css('float','left');
            ele.hide();
        }else{
            
            this.btn_add_record
                    .button({label: window.hWin.HR("Add"), showLabel:true, 
                            icon:"ui-icon-plus"})
                    .addClass('ui-button-action')
                    .css({padding:'2px'})
                    .show();
                    
            this._on( this.btn_add_record, {
                        click: function(){
                            this._trigger( "onadd" );
                        }} );
            
            /*

            this.btn_find_record.css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Find/Add Record Type"), icon: "ui-icon-search"})
                .click(function(e) {
                    that._trigger( "onfind" );
                }); 
                
            //@todo proper alignment
            if(this.options.edit_mode=='inline'){
                this.btn_add_record.css({'float':'left','border-bottom':'1px lightgray solid',
                'min-height': '2.4em', 'margin-bottom': '0.4em'});    
            }
            */                       
        }
        
        this._on(this.input_search_type,  { change:this.startSearch });
        
        this._on(this.input_search,  { keyup:this.startSearch });

        this.input_sort_type = this.element.find('#input_sort_type');
        this._on(this.input_sort_type,  { change:this.startSearch });
                      
        if( this.options.import_structure ){
            //this.element.find('#div_show_already_in_db').css({'display':'inline-block'});    
            this.chb_show_already_in_db = this.element.find('#chb_show_already_in_db');
            this._on(this.chb_show_already_in_db,  { change:this.startSearch });
            this.element.find('#div_group_information').hide();
            
            this.options.simpleSearch = true;
        }else{
            
            this.input_search.parent().hide();
            this.element.find('#div_group_information').show();
            this.element.find('#div_show_already_in_db').hide();
            this._on(this.element.find('#chb_show_all_groups'),  { change:this.startSearch });
            /*
            function(){
                this.input_search_group.val(this.element.find('#chb_show_all_groups').is(':checked')
                                            ?'any':this.options.rtg_ID).change();
            }});*/
                        
        }
        
        this.element.find('#div_search_group').hide();
        
        if( this.options.simpleSearch){
            this.element.find('#input_sort_type_div').hide();
        }else{
            if(smode=='select_multi'){
                
                this.element.find('#btn_ui_config').hide();
                this.element.find('#div_show_all_groups').hide();
                this.element.find('#div_group_information').hide();
                this.element.find('#input_sort_type').parent().hide();
                this.input_search.parent().show();
                
                this.element.find('#btn_ui_config').parent().css({'float':'none'});
                this.element.find('#inner_title').parent().css({'float':'none',position:'absolute',top:'55px'});
                
                this.element.find('#inner_title')
                    .css('font-size','smaller')
                    .text('Not finding the record type you require?');
                this.btn_add_record
                    .button({label: 'Define new record type'});
                
                this.element.find('#div_search_group').show();
                this.input_search_group = this.element.find('#input_search_group');   //rectype group

                window.hWin.HEURIST4.ui.createRectypeGroupSelect(this.input_search_group[0], 
                            [{key:'any',title:'all groups'}]);
                this._on(this.input_search_group,  { change:this.startSearch });
        
                
            }else{
                
                this.btn_ui_config = this.element.find('#btn_ui_config')
                        //.css({'width':'6em'})
                        .button({label: window.hWin.HR("Configure UI"), showLabel:false, 
                                icon:"ui-icon-gear", iconPosition:'end'});
                if(this.btn_ui_config){
                    this._on( this.btn_ui_config, {
                            click: this.configureUI });
                }
            
                
            }

        }
       
        if($.isFunction(this.options.onInitCompleted)){
            this.options.onInitCompleted.call();
        }else{
            this.startSearch();              
        }
    },  
    
    //
    //
    //
    _setOption: function( key, value ) {
        this._super( key, value );
        if(key == 'rtg_ID'){
            if(!this.element.find('#chb_show_all_groups').is(':checked'))
                this.startSearch();
                //this.element.find('#input_search_group').val(value).change();
                
                if(value==$Db.getTrashGroupId('rtg')){
                    this.btn_add_record.hide();
                }else{
                    this.btn_add_record.show();
                }
                
        }
    },
    
    //
    //
    //    
    configureUI: function(){
        
        var that = this;

        var popele = that.element.find('#div_ui_config');
        
        var flist = popele.find( ".toggles" );
        var opts = this.options.ui_params['fields'];
        
        flist.controlgroup( {
            direction: "vertical"
        } ).sortable();       
        
        popele.find('.ui-checkboxradio-icon').css('color','black');

        //rest all checkboxes
        popele.find('input[type="checkbox"]').prop('checked', '');
        $(opts).each(function(idx,val)
        {
            popele.find('input[name="'+val+'"]').prop('checked', 'checked');    
        });
        popele.find('input[name="name"]').prop('checked', 'checked');
        popele.find('input[name="edit"]').prop('checked', 'checked');
        
        //sort
        var cnt = flist.children().length;
        var items = flist.children().sort(
            function(a, b) {
                    var vA = opts.indexOf($(a).attr('for'));
                    var vB = opts.indexOf($(b).attr('for'));
                    if(!(vA>=0)) vA = cnt;
                    if(!(vB>=0)) vB = cnt;
                    return (vA < vB) ? -1 : (vA > vB) ? 1 : 0;
            });
        var cop = flist.children();
        flist.append(items);    
        
        flist.controlgroup('refresh');
        
        var $dlg_pce = null;

        var btns = [
            {text:window.hWin.HR('Apply'),
                click: function() { 
                    
                    var fields = [];
                    /*popele.find('input[type="checkbox"]:checked').each(function(idx,item){
                        fields.push($(item).attr('name'));
                    });*/
                    flist.find('input[name="name"]').prop('checked', 'checked');
                    flist.find('input[name="edit"]').prop('checked', 'checked');
                    flist.children().each(function(idx,item){
                        var item = $(item).find('input');
                        if(item.is(':checked')){
                            fields.push(item.attr('name'));    
                        }                        
                    });
                    
                    //get new parameters
                    var params = { 
                        fields: fields
                    };
                    
                    that.options.ui_params = params;
                    //trigger event to redraw list
                    that._trigger( "onuichange", null, params );
                   
                    $dlg_pce.dialog('close'); 
            }},
            {text:window.hWin.HR('Cancel'),
                click: function() { $dlg_pce.dialog('close'); }}
        ];            

        $dlg_pce = window.hWin.HEURIST4.msg.showElementAsDialog({
            window:  window.hWin, //opener is top most heurist window
            title: window.hWin.HR('Configure User Interface'),
            width: 260,
            height: 500,
            element:  popele[0],
            //resizable: false,
            buttons: btns
        });



    },
    
    reloadGroupSelector: function(){
        
    },


/*    
    // NOT USED
    //  recreate groups listings - selector, tab and list
    //
    reloadGroupSelector: function (rectypes){
        
        this.input_search_group = this.element.find('#input_search_group');   //rectype group

        window.hWin.HEURIST4.ui.createRectypeGroupSelect(this.input_search_group[0],
                                            [{key:'any',title:'any group'}], rectypes);
        this._on(this.input_search_group,  { change:this.startSearch });
        

        this.searchList = null;
        if(this.options.searchFormList){
            this.options.searchFormList.empty();
            this.searchList = $('<ol>').css({'list-style-type': 'none','padding':'0px'}).appendTo(this.options.searchFormList)
        }
        
        this.selectGroup = this.element.find('#sel_group');
        
        this.selectGroup.empty();
        var ul = $('<ul>').appendTo(this.selectGroup);
        var that = this;

        
        this.input_search_group.find('option').each(function(idx,item){
            if(idx>0){
                var grpid = $(item).attr('value');
                $('<li data-grp="'+grpid+'"><a href="#grp'+grpid+'">'+$(item).text()
                            +'</a><span class="ui-icon ui-icon-pencil" title="Edit Group" '
                            +'style="vertical-align: -webkit-baseline-middle;visibility:hidden;font-size:0.8em"/></li>')
                            .appendTo(ul);
                $('<div id="grp'+grpid+'"/>').appendTo(that.selectGroup);
                
                if(that.searchList!=null){
                    $('<li class="ui-widget-content" value="'+grpid+'"><span style="width:133px;display:inline-block">'+$(item).text()
                            +'</span><span class="ui-icon ui-icon-pencil" '
                            +' title="Edit Group" style="display:none;float:right;font-size:0.8em;position:relative"/></li>')  
                        .css({margin: '2px', padding: '0.2em', cursor:'pointer'}) 
                        .appendTo(that.searchList);    
                }
            }
        });//end each
        
        this.selectGroup.tabs();
        this.selectGroup.find('ul').css({'background':'none','border':'none'});
        this.selectGroup.css({'background':'none','border':'none',bottom:'20px'});
        this.selectGroup.find('li').hover(function(event){
                var ele = $(event.target);
                if(!ele.is('li')) ele = ele.parent();
                ele.find('.ui-icon-pencil').css('visibility','visible');
            }, function(event){
                var ele = $(event.target);
                if(!ele.is('li')) ele = ele.parent();
                ele.find('.ui-icon-pencil').css('visibility','hidden'); //parent().
            });

        
        this._on( this.selectGroup, { tabsactivate: function(event, ui){
            //var active = this.selectGroup.tabs('option','active');
            //console.log(ui.newTab.attr('data-grp'));
            this.input_search_group.val( ui.newTab.attr('data-grp') ).change();
        }  });

        
        this._on( this.selectGroup.find('.ui-icon-pencil'), {
            click: function(event){
                window.hWin.HEURIST4.ui.showEntityDialog('defRecTypeGroups', 
                    {edit_mode:'editonly', rec_ID: $(event.target).parent().attr('data-grp'), //select_mode:'single', 
                    onselect:function(event, data){
                    }                    
                    } );
            }
        });
        
        if(this.searchList!=null){
            
            this.searchList.find('li').hover(function(event){
                var ele = $(event.target);
                if(!ele.is('li')) ele = ele.parent();
                ele.addClass('ui-state-hover');
                ele.find('.ui-icon-pencil').show();
            }, function(event){
                var ele = $(event.target);
                if(!ele.is('li')) ele = ele.parent();
                ele.removeClass('ui-state-hover');
                ele.find('.ui-icon-pencil').hide();
            });
            
            //sortable().
            this.searchList.selectable( {selected: function( event, ui ) {
                    that.searchList.find('li').removeClass('ui-state-active');
                    $(ui.selected).addClass('ui-state-active');
                    that.input_search_group.val( $(ui.selected).attr('value') ).change();
                }}).css({width:'100%',height:'100%'});
        }
    },
*/        
    
    //
    // public methods
    //
    startSearch: function(){
        
            this._super();
            
            if(!this.input_search) return;
            
            var request = {}
        
            if(this.input_search.val()!=''){
                var s = this.input_search.val();
                if(window.hWin.HEURIST4.util.isNumber(s) && parseInt(s)>0){
                     request['rty_ID'] = s;   
                     s = '';
                }else if (s.indexOf('-')>0){
                    
                    var codes = s.split('-');
                    if(codes.length==2 
                        && window.hWin.HEURIST4.util.isNumber(codes[0])
                        && window.hWin.HEURIST4.util.isNumber(codes[1])
                        && parseInt(codes[0])>0 && parseInt(codes[1])>0 ){
                        request['rty_OriginatingDBID'] = codes[0];
                        request['rty_IDInOriginatingDB'] = codes[1];
                        s = '';
                    }
                }
                
                if(s!='') request['rty_Name'] = s;
            }
            
            if(this.options.import_structure){

                if(this.chb_show_already_in_db && !this.chb_show_already_in_db.is(':checked')){
                        request['rty_ID_local'] = '=0';
                }
                
            }else if(this.options.select_mode=='select_multi'){
                    if(this.input_search_group.val()>0){
                        request['rty_RecTypeGroupID'] = this.input_search_group.val();
                        this.options.rtg_ID = request['rty_RecTypeGroupID'];
                    }else{
                        this.options.rtg_ID = null;
                    }
                    
            }else{
            
                if( this.options.rtg_ID<0 ){
                    //not in given group
                    request['not:rty_RecTypeGroupID'] = Math.abs(this.options.rtg_ID);
                }
            
                var sGroupTitle = '<h4 style="margin:0">';
                if(!this.element.find('#chb_show_all_groups').is(':checked') && this.options.rtg_ID>0){
                    this.input_search.parent().hide();

                    request['rty_RecTypeGroupID'] = this.options.rtg_ID;
                    sGroupTitle += ($Db.rtg(this.options.rtg_ID,'rtg_Name')
                                        +'</h5><div class="heurist-helper3 truncate" style="font-size:0.7em">'
                                        +$Db.rtg(this.options.rtg_ID,'rtg_Description')+'</div>');
                }else{
                    this.input_search.parent().show();
                    sGroupTitle += 'All Groups</h4><div class="heurist-helper3" style="font-size:0.7em">All record type groups</div>';
                }
                this.element.find('#div_group_information').html(sGroupTitle);
        
            }
            
            this.input_sort_type = this.element.find('#input_sort_type');
            if(this.input_sort_type.val()=='recent'){
                request['sort:rty_Modified'] = '-1' 
            }else if(this.input_sort_type.val()=='id'){
                request['sort:rty_ID'] = '1';   
            }else if(this.input_sort_type.val()=='count'){
                request['sort:rty_RecCount'] = '-1';   
            }else{
                request['sort:rty_Name'] = '1';   
            }
  
            if(this.options.use_cache){
            
                this._trigger( "onfilter", null, request);            
            }else
            if(false && $.isEmptyObject(request)){
                this._trigger( "onresult", null, {recordset:new hRecordSet()} );
            }else{
                this._trigger( "onstart" );
        
                request['a']          = 'search'; //action
                request['entity']     = this.options.entity.entityName;
                request['details']    = 'id'; //'id';
                request['request_id'] = window.hWin.HEURIST4.util.random();
                
                //we may search users in any database
                request['db']     = this.options.database;

                var that = this;                                                
           
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
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
