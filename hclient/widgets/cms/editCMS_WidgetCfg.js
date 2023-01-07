/**
* editCMS_WidgetCfg.js - configuration dialog for widget properties - this is either popup or use provided container
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
//
// widget_cfg -json cfg for widget to be edited 
// _layout_content- json cfg for website
//
function editCMS_WidgetCfg( widget_cfg, _layout_content, $dlg, main_callback ){

    var _className = 'editCMS_WidgetCfg';
    //var isWebPage = false;
    
    function _init(){

        var buttons= [
            {text:window.hWin.HR('Cancel'), 
                id:'btnCancel',
                css:{'float':'right','margin-left':'30px','margin-right':'20px'}, 
                click: function() { 
                    $dlg.dialog( "close" );
            }},
            {text:window.hWin.HR('Apply'), 
                id:'btnDoAction',
                class:'ui-button-action',
                //disabled:'disabled',
                css:{'float':'right'}, 
                click: function() { 
                        var config = _getValues();
                        if(config!==false){
                            main_callback.call(this, config);
                            $dlg.dialog( "close" );    
                        }
        }}];
        
        if($dlg && $dlg.length>0){
            //container provided

            $container.empty().load(window.hWin.HAPI4.baseURL
                +'hclient/widgets/cms/editCMS_WidgetCfg.html',
                _initControls
            );

        }else{
            //open as popup

            $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
                +"hclient/widgets/cms/editCMS_WidgetCfg.html?t="+(new Date().getTime()), 
                buttons, 'Modify Widget Properties', 
                {  container:'cms-add-widget-popup',
                    default_palette_class: 'ui-heurist-publish',
                    width: 800,
                    height: 700,
                    close: function(){
                        $dlg.dialog('destroy');       
                        $dlg.remove();
                    },
                    open: _initControls
            });

        }



    }
    
    //
    // Assign widget properties to UI
    //
    function _initControls(){
        
        var widget_name = widget_cfg.appid;
        var opts = window.hWin.HEURIST4.util.isJSON(widget_cfg.options);

        $dlg.find('div[class^="heurist_"], hr[class^="heurist_"]').hide(); //hide all
        $dlg.find('div.'+widget_name+', hr.'+widget_name).show();
        
        //fill page list
        var selPage = $dlg.find('select[name="search_page"]');
        
        var main_menu = $('#main-menu > div[widgetid="heurist_Navigation"]');
        
        if(main_menu.length>0 && widget_name!='heurist_StoryMap'){
            pages = main_menu.navigation('getMenuContent','list');
            if(!pages){
                selPage.parent().hide();
            }else{
                pages.unshift({key:'',title:''});   
                window.hWin.HEURIST4.ui.createSelector(selPage[0], pages);
            }
            //isWebPage = false;
        }else{
            //isWebPage = true;
            selPage.parent().hide();
        }
        
        if(opts!==false){
            
            //find map widget on this page
            if(widget_name=='heurist_StoryMap'){
                if(!opts.map_widget_id){
                    var ele = layoutMgr.layoutContentFindWidget(_layout_content, 'heurist_Map');
                    //if(ele) console.log(ele.options); //ele.options.widget_id ele.dom_id
                    
                    if(ele && ele.options.search_realm=='' && ele.dom_id){
                        opts.map_widget_id = ele.dom_id;
                    }
                }
            }
            
            //find and assign prevail search group (except heurist_Map if heurist_StoryMap exists)
            if(!opts.search_realm){ //not defined yet
            
                if(widget_name=='heurist_Map' && layoutMgr.layoutContentFindWidget(_layout_content, 'heurist_StoryMap')!=null)
                {
                    
                }else{
                    var sg = layoutMgr.layoutContentFindMainRealm(_layout_content);    
                    if(sg=='') sg = 'search_group_1';
                    opts.search_realm = sg;
                }
            }
            
        
            
            if(opts.search_page) {
                $(selPage).val(opts.search_page);        
                //selPage.hSelect('refresh');
            }
            $dlg.find('input[name="search_realm"]').val(opts.search_realm);    
            $dlg.find('input[name="widget_id"]').val(opts.widget_id);    

            if(widget_name=='heurist_Map'){
                //special behaviour for map widget

                if(opts.layout_params){
                    $dlg.find("#use_timeline").prop('checked', !opts.layout_params.notimeline);    
                    $dlg.find("#map_rollover").prop('checked', opts.layout_params.map_rollover);    
                    $dlg.find("#use_cluster").prop('checked', !opts.layout_params.nocluster);    
                    $dlg.find("#editstyle").prop('checked', opts.layout_params.editstyle);    

                    var ctrls = (opts.layout_params.controls)?opts.layout_params.controls.split(','):[];
                    $dlg.find('input[name="controls"]').each(
                        function(idx,item){$(item).prop('checked',ctrls.indexOf($(item).val())>=0);}
                    );
                    var legend = (opts.layout_params.legend)?opts.layout_params.legend.split(','):[];
                    if(legend.length>0){

                        $dlg.find('input[name="legend_exp2"]').prop('checked',legend.indexOf('off')<0);

                        $.each(legend, function(i, val){
                            if(parseInt(val)>0){
                                $dlg.find('input[name="legend_width"]').val(val);        
                            }else if(val!='off'){
                                var is_exp = (val[0]!='-')
                                if (!is_exp) legend[i] = val.substring(1);
                                $dlg.find('input[name="legend_exp"][value="'+val+'"]').prop('checked',is_exp);
                            }
                        });
                        $dlg.find('input[name="legend"]').each(
                            function(idx,item){
                                $(item).prop('checked',legend.indexOf($(item).val())>=0);
                            }
                        );
                    }
                    if(opts.layout_params['template']){
                        $dlg.find('select[name="map_template"]').attr('data-template', opts.layout_params['template']);        
                    }
                    var popup = 'standard';
                    if(opts.layout_params['template']=='none'){
                        popup = 'none';
                    }else if(opts.layout_params['popup']) {
                        popup = opts.layout_params['popup'];
                    }
                    $dlg.find('input[name="map_popup"][value="'+popup+'"]').prop('checked', true);        
                    
                    if(opts.layout_params['basemap']){
                        $dlg.find('select[name="map_basemap"]').attr('data-basemap', opts.layout_params['basemap']);    
                    }
                    if(opts.layout_params['basemap_filter']){
                        $dlg.find('input[name="map_basemap_filter"]').val(opts.layout_params['basemap_filter']);        
                    }

                    if(opts.layout_params['popup_behaviour']){
                        $dlg.find('input[name="popup_behaviour"][value="'+ opts.layout_params['popup_behaviour'] +'"]').prop('checked', true);

                        if(opts.layout_params['popup_behaviour'] == 'scale'){
                            $dlg.find('span#pw_label').text('Max width:');
                        }
                        if(opts.layout_params['popup_behaviour'] == 'scale' || opts.layout_params['popup_behaviour'] == 'fixed_width'){
                            $dlg.find('span#ph_label').text('Max length:');
                        }
                    }
                    if(opts.layout_params['popup_width']){

                        var value = opts.layout_params['popup_width'];
                        var unit = (value.indexOf('px') > 0) ? value.slice(-2) : value.slice(-1);
                        value = (value.indexOf('px') > 0) ? value.slice(0, -2) : value.slice(0, -1);

                        $dlg.find('input[name="popup_width"]').val(value); // first index
                        $dlg.find('select[name="popup_wunit"]').val(unit); // second index
                    }
                    if(opts.layout_params['popup_height']){

                        var value = opts.layout_params['popup_height'];
                        var unit = (value.indexOf('px') > 0) ? value.slice(-2) : value.slice(-1);
                        value = (value.indexOf('px') > 0) ? value.slice(0, -2) : value.slice(0, -1);

                        $dlg.find('input[name="popup_height"]').val(value); // first index
                        $dlg.find('select[name="popup_hunit"]').val(unit); // second index
                    }
                    if(opts.layout_params['popup_resizing']){
                        $dlg.find('input[name="popup_resizing"]').prop('checked', false); //opts.layout_params['popup_resizing']
                    }
                    if(opts.layout_params['maxzoom']>0){
                        $dlg.find('input[name="map_maxzoom"]').val(opts.layout_params['maxzoom']);        
                    }
                    if(opts.layout_params['minzoom']>0){
                        $dlg.find('input[name="map_minzoom"]').val(opts.layout_params['minzoom']);        
                    }
                    if(opts.layout_params['pntzoom']>0){
                        $dlg.find('input[name="map_pntzoom"]').val(opts.layout_params['pntzoom']);        
                    }
                }
                if(opts['mapdocument']>0){
                    $dlg.find('select[name="mapdocument"]').attr('data-mapdocument', opts['mapdocument']);        
                }
                if(opts['custom_links']){
                    $dlg.find('textarea[name="custom_links"]').val(opts['custom_links']);    
                }
                if(opts['current_search_filter']){
                    $dlg.find('input[name="current_search_filter"]').val(opts['current_search_filter']);    
                }

                $dlg.find('button[name="basemap_filter"]')
                    .button()
                    .css('font-size','0.7em')
                    .on( { click: function(){
                        var cfg = window.hWin.HEURIST4.util.isJSON($dlg.find('input[name="map_basemap_filter"]').val());
                        if(!cfg) cfg = null;
                        imgFilter(cfg,null,function(filter){
                            $dlg.find('input[name="map_basemap_filter"]').val( JSON.stringify(filter) );
                        });   
                    }});
                $dlg.find('button[name="basemap_filter_reset"]')
                    .button()
                    .css('font-size','0.7em')
                    .on( { click: function(){
                        $dlg.find('input[name="map_basemap_filter"]').val('');
                    }});
                
            }else{

                $dlg.find('div.'+widget_name+' input').each(function(idx, item){
                    item = $(item);
                    if(item.attr('name')){
                        if(item.attr('type')=='checkbox'){
                            item.prop('checked', opts[item.attr('name')]===true || opts[item.attr('name')]=='true');
                        }else if(item.attr('type')=='radio'){
                            item.prop('checked', item.val()== String(opts[item.attr('name')]));
                        }else {  //if(item.val()!=''){
                            item.val( opts[item.attr('name')] );
                        }
                    }
                });
                $dlg.find('div.'+widget_name+' select').each(function(idx, item){
                    item = $(item);
                    item.val( opts[item.attr('name')] );
                });

                //
                // assign paramters
                //
                if(widget_name=='heurist_resultListExt'){ // custom report
                    if(opts['template']){
                        $dlg.find('select[name="rep_template"]').attr('data-template', opts['template']);        
                    }
                    $dlg.find('#empty_remark').val(opts['emptysetmessage']);
                    $dlg.find('#placeholder_text').val(opts['placeholder_text']);
                }else if(widget_name=='heurist_resultList'){ // standard result list
                    if(opts['rendererExpandDetails']){
                        $dlg.find('select[name="rendererExpandDetails"]').attr('data-template', opts['rendererExpandDetails']);        
                    }
                    $dlg.find('#empty_remark').val(opts['empty_remark']);
                    $dlg.find('#placeholder_text').val(opts['placeholder_text']);
                }else if(widget_name=='heurist_resultListDataTable'){ // datatable
                    $dlg.find('#dataTableParams').val(opts['dataTableParams']);
                    $dlg.find('#empty_remark').val(opts['emptyTableMsg']);
                    $dlg.find('#placeholder_text').val(opts['placeholder_text']);
                }
            }

            
            // init special edit elements            
            if(widget_name=='heurist_Navigation'){

                var rval = $dlg.find('input[name="menu_recIDs"]').val();
                rval =  rval?rval.split(','):[];

                var ele = $dlg.find('#menu_recIDs');

                if(!ele.editing_input('instance')){



                    var ed_options = {
                        recID: -1,                                                                                       
                        dtID: ele.attr('id'), //'group_selector',
                        //show_header: false,
                        values: rval,
                        readonly: false,
                        showclear_button: true,
                        dtFields:{
                            dty_Type:"resource", rst_MaxValues:0,
                            rst_DisplayName: 'Top level menu items', rst_DisplayHelpText:'',
                            rst_PtrFilteredIDs: [window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'],
                                window.hWin.HAPI4.sysinfo['dbconst']['RT_WEB_CONTENT']],
                            rst_FieldConfig: {entity:'records', csv:false}
                        }
                    };

                    ele.editing_input(ed_options);
                    ele.parent().css('display','block');
                    ele.find('.header').css({'width':'150px','text-align':'right'});
                }
                //ele.editing_input('setValue', rval);

                $dlg.find('select[name="orientation"]')
                .change(function(e){

                    var is_horiz = ($(e.target).val()=='horizontal');
                    var is_vertical = ($(e.target).val()=='vertical');

                    if($(e.target).val()=='treeview'){
                        $dlg.find('#expandLevels').show();
                    }else{
                        $dlg.find('#expandLevels').hide();
                    }

                    var vals = $dlg.find('#widgetCss').val().split(';');
                    for (var i=0; i<vals.length; i++){
                        var vs = vals[i].split(':');
                        if(vs && vs.length==2){
                            vs[0] = vs[0].trim();
                            if(is_horiz){
                                if(vs[0]=='width'){
                                    vals[i] = ('\nwidth: 100%');
                                }else if(vs[0]=='height'){
                                    vals[i] = ('\nheight:50px');
                                }
                            }else if(is_vertical){
                                if(vs[0]=='width'){
                                    vals[i] = ('\nwidth: 50px');
                                }else if(vs[0]=='height'){
                                    vals[i] = ('\nheight:100%');
                                }
                            }else{
                                if(vs[0]=='width'){
                                    vals[i] = ('\nwidth: 200px');
                                }else if(vs[0]=='height'){
                                    vals[i] = ('\nheight:300px');
                                }
                            }
                        }
                    }

                    $dlg.find('#widgetCss').val( vals.join(';'));
                });



            }else 
            if (widget_name=='heurist_recordAddButton'){

                    var ele = $dlg.find('button[name="add_record_cfg"]');

                    if(!ele.button('instance')){
                        
                        function __human_readble(){
                            
                            var rty_ID = $dlg.find('input[name="RecTypeID"]').val();
                            if(rty_ID>0){
                                $('#add_record_desc').html(
                                '&nbsp;&nbsp;<i>Record type: </i>'+$Db.rty(rty_ID,'rty_Name')
                                //+' <i>editable by user/group# </i>'+$dlg.find('input[name="OwnerUGrpID"]').val()
                                //+' <i>viewable by: </i>'+$dlg.find('input[name="NonOwnerVisibility"]').val()
                                );                                
                            }else{
                                $('#add_record_desc').html('');
                            }
                        }

                        ele.button().click(
                            function(){
                                window.hWin.HEURIST4.ui.showRecordActionDialog('recordAdd',{
                                    title: 'Select record type',
                                    height: 520,
                                    get_params_only: true,
                                    onClose: function(context){
                                        if(context && context.RecTypeID>0){
                                            $dlg.find('input[name="RecTypeID"]').val(context.RecTypeID);
                                            $dlg.find('input[name="OwnerUGrpID"]').val(context.OwnerUGrpID);
                                            $dlg.find('input[name="NonOwnerVisibility"]').val(context.NonOwnerVisibility);
                                            __human_readble();
                                        }

                                    },
                                    default_palette_class: 'ui-heurist-publish'                                        
                                    }
                                );    
                            }
                        );
                        __human_readble();
                    }

            }else
            if(widget_name=='heurist_SearchTree'){

                var ele_rb = $dlg.find('input[name="searchTreeMode"]')
                .change(function(e){

                    var selval = __getSearchTreeMode();

                    $dlg.find('#simple_search_header').parent().css('display','none');
                    $dlg.find('#simple_search_text').parent().css('display','none');

                    if(selval==0){
                        //buttons
                        $dlg.find('#allowed_UGrpID').css('display','table-row');
                        $dlg.find('#allowed_svsIDs').css('display','table-row');
                        $dlg.find('#allowed_UGrpID').editing_input('setDisabled', false);
                        $dlg.find('#allowed_svsIDs').editing_input('setDisabled', false);

                        var is_vis = $dlg.find('input[name="simple_search_allowed"]').is(':checked');
                        if(is_vis){
                            $dlg.find('#simple_search_header').parent().css('display','table-row');
                            $dlg.find('#simple_search_text').parent().css('display','table-row');
                        }else{
                            $dlg.find('#simple_search_header').parent().css('display','none');
                            $dlg.find('#simple_search_text').parent().css('display','none');
                        }
                    }else if(selval==1){
                        //tree
                        $dlg.find('#allowed_UGrpID').css('display','table-row');
                        $dlg.find('#allowed_svsIDs').css('display','none');
                        $dlg.find('#allowed_UGrpID').editing_input('setDisabled', false);
                        $dlg.find('#allowed_svsIDs').editing_input('setDisabled', true);
                        $dlg.find('input[name="allowed_svsIDs"]').val('');
                    }else{
                        //full
                        $dlg.find('#allowed_UGrpID').css('display','none');
                        $dlg.find('#allowed_svsIDs').css('display','none');
                        $dlg.find('#allowed_UGrpID').editing_input('setDisabled', true);
                        $dlg.find('#allowed_svsIDs').editing_input('setDisabled', true);
                        $dlg.find('input[name="allowed_UGrpID"]').val('');
                        $dlg.find('input[name="allowed_svsIDs"]').val('');
                    }
                });

                var selval = __getSearchTreeMode();
                if(window.hWin.HEURIST4.util.isempty(selval)
                    || !(selval==0 || selval==1 || selval==2)){
                    selval = 0;
                    $dlg.find('#heurist_SearchTreeMode1').prop('checked',true);
                }

                //visible for buttons and tree mode
                var ele = $dlg.find('#allowed_UGrpID');
                if(!ele.editing_input('instance')){

                    var init_val = $dlg.find('input[name="allowed_UGrpID"]').val();
                    if(init_val=='' && selval!=2 && $dlg.find('input[name="allowed_svsIDs"]').val()==''){
                        init_val==5;//web search  by default
                    }

                    var ed_options = {
                        recID: -1,
                        dtID: ele.attr('id'), 
                        values: [init_val], 
                        readonly: false,
                        showclear_button: true,
                        dtFields:{
                            dty_Type:"resource", rst_MaxValues:1,
                            rst_DisplayName: 'EITHER Show all filters in these workgroups', 
                            rst_DisplayHelpText:'',
                            rst_FieldConfig: {entity:'sysGroups', csv:true}
                        },
                        change:function(){
                            $dlg.find('#allowed_svsIDs').editing_input('setValue','');
                            $dlg.find('#init_svsID').editing_input('setValue','');
                            __restFilterForInitSearch();
                        }
                    };

                    ele.editing_input(ed_options);
                    //ele.editing_input('setValue','5'); 
                    ele.parent().css('display','block');
                    ele.find('.header').css({'width':'150px','text-align':'right'});
                }

                //visible for buttons mode only
                ele = $dlg.find('#allowed_svsIDs');
                if(!ele.editing_input('instance')){

                    var ed_options = {
                        recID: -1,
                        dtID: ele.attr('id'), 
                        values: [$dlg.find('input[name="allowed_svsIDs"]').val()],
                        readonly: false,
                        showclear_button: true,
                        dtFields:{
                            dty_Type:"resource", rst_MaxValues:1,
                            rst_DisplayName: 'OR Choose specific filters', rst_DisplayHelpText:'',
                            rst_FieldConfig: {entity:'usrSavedSearches', csv:true}
                        },
                        change:function(){
                            $dlg.find('#allowed_UGrpID').editing_input('setValue','');
                            $dlg.find('#init_svsID').editing_input('setValue','');
                            __restFilterForInitSearch();
                        }
                    };

                    ele.editing_input(ed_options);
                    ele.parent().css('display','block');
                    ele.find('.header').css({'width':'150px','text-align':'right'});
                }

                ele = $dlg.find('#init_svsID');
                if(!ele.editing_input('instance')){

                    var ed_options = {
                        recID: -1,
                        dtID: ele.attr('id'), 
                        values: [$dlg.find('input[name="init_svsID"]').val()],
                        readonly: false,
                        showclear_button: true,
                        dtFields:{
                            dty_Type:"resource", rst_MaxValues:1,
                            rst_DisplayName: 'Trigger this filter on page load', rst_DisplayHelpText:'',
                            rst_FieldConfig: {entity:'usrSavedSearches', csv:false} 
                        }
                    };


                    ele.editing_input(ed_options);
                    ele.parent().css('display','block');
                    ele.find('.header').css({'width':'150px','text-align':'right'});
                }

                function __getSearchTreeMode(){
                    var ele_rb = $dlg.find('input[name="searchTreeMode"]')
                    var selval = '';
                    $.each(ele_rb, function(idx, item){
                        item = $(item);
                        if(item.is(':checked')){
                            selval = item.prop('value');
                            return false;
                        }
                    });
                    return selval
                }

                function __restFilterForInitSearch(){

                    var ifilter = null; 
                    var val = $dlg.find('#allowed_svsIDs').editing_input('getValues');
                    // $dlg.find('input[name="allowed_svsIDs"]').val();
                    if(!window.hWin.HEURIST4.util.isempty(val) && val[0]!=''){
                        if($.isArray(val)) val = val.join(',');
                        ifilter = {svs_ID:val};
                    }else{
                        val = $dlg.find('#allowed_UGrpID').editing_input('getValues');
                        //$dlg.find('input[name="allowed_UGrpID"]').val();
                        if(!window.hWin.HEURIST4.util.isempty(val) && val[0]!=''){
                            if($.isArray(val)) val = val.join(',');
                            ifilter = {svs_UGrpID:val};
                        }
                    }


                    ele.editing_input({
                        dtFields:{
                            dty_Type:"resource", rst_MaxValues:1,
                            rst_DisplayName: 'Initial filter', rst_DisplayHelpText:'',
                            rst_FieldConfig: {entity:'usrSavedSearches', csv:false,
                                initial_filter:ifilter, search_form_visible:(ifilter==null)    
                            } 
                        }
                    }); 
                }

                __restFilterForInitSearch();

                ele_rb.change();

                $dlg.find('input[name="simple_search_allowed"]').change(function(){
                    var is_vis = $dlg.find('input[name="simple_search_allowed"]').is(':checked');
                    if(is_vis){
                        $dlg.find('#simple_search_header').parent().css('display','table-row');
                        $dlg.find('#simple_search_text').parent().css('display','table-row');
                    }else{
                        $dlg.find('#simple_search_header').parent().css('display','none');
                        $dlg.find('#simple_search_text').parent().css('display','none');
                    }
                });

                var is_vis = $dlg.find('input[name="simple_search_allowed"]').is(':checked');
                if(is_vis){
                    $dlg.find('#simple_search_header').parent().css('display','table-row');
                    $dlg.find('#simple_search_text').parent().css('display','table-row');
                }else{
                    $dlg.find('#simple_search_header').parent().css('display','none');
                    $dlg.find('#simple_search_text').parent().css('display','none');
                }
            }else
            if(widget_name=='heurist_Map' && 
                $dlg.find('select[name="mapdocument"]').find('options').length==0){

                var $selectMapDoc = $dlg.find('select[name="mapdocument"]');
                //fill list of mapdpcuments
                var request = {
                    q: 't:'+window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT'],w: 'a',
                    detail: 'header',
                    source: 'cms_edit'};
                //perform filter
                window.hWin.HAPI4.RecordMgr.search(request,
                    function(response){

						if(response.status == window.hWin.ResponseStatus.OK){

							var resdata = new hRecordSet(response.data);
							var idx, records = resdata.getRecords(), opts = [{key:'',title:'none'}];
							for(idx in records){
								if(idx)
								{
									var record = records[idx];
									opts.push({key:resdata.fld(record, 'rec_ID'), title:resdata.fld(record, 'rec_Title')});
								}
							}//for

							window.hWin.HEURIST4.ui.fillSelector($selectMapDoc[0], opts);
							if($selectMapDoc.attr('data-mapdocument')>0){
								$selectMapDoc.val( $selectMapDoc.attr('data-mapdocument') );
							}
							window.hWin.HEURIST4.ui.initHSelect($selectMapDoc[0], false);

						}else {
							window.hWin.HEURIST4.msg.showMsgErr(response);
						}
                    }
                );  

                var $selectMapTemplate = $dlg.find('select[name="map_template"]'); 

                window.hWin.HEURIST4.ui.createTemplateSelector( $selectMapTemplate
                    ,[{key:'',title:'Standard popup template'}], $selectMapTemplate.attr('data-template'));
                    //,{key:'none',title:'Disable popup'}

                var $selectBaseMap = $dlg.find('select[name="map_basemap"]');

                var baseMapOptions = [
                    {key:'OpenStreetMap', title:'OpenStreetMap'},
                    {key:'OpenTopoMap', title:'OpenTopoMap'},
                    {key:'MapBox.StreetMap', title:'MapBox.StreetMap'},
                    {key:'MapBox.Satellite', title:'MapBox.Satellite'},
                    {key:'MapBox.Combined',title:'MapBox.Combined'},
                    {key:'MapBox.Relief', title:'MapBox.Relief'},
                    {key:'Esri.WorldStreetMap', title:'Esri.WorldStreetMap'},
                    {key:'Esri.WorldTopoMap', title:'Esri.WorldTopoMap'},
                    {key:'Esri.WorldImagery', title:'Esri.WorldImagery'},
                    {key:'Esri.WorldShadedRelief', title:'Esri.WorldShadedRelief'},
                    {key:'Stamen.Toner', title:'Stamen.Toner'},
                    {key:'Stamen.TerrainBackground', title:'Stamen.TerrainBackground'},
                    {key:'Esri.NatGeoWorldMap',title:'Esri.NatGeoWorldMap'},
                    {key:'Esri.WorldGrayCanvas',title:'Esri.WorldGrayCanvas'}
                ];

                window.hWin.HEURIST4.ui.fillSelector($selectBaseMap[0], baseMapOptions);
                if($selectBaseMap.attr('data-basemap') != null){
                    $selectBaseMap.val($selectBaseMap.attr('data-basemap'));
                }
                $selectBaseMap = window.hWin.HEURIST4.ui.initHSelect($selectBaseMap[0], false);

                if($selectBaseMap.hSelect('instance') != undefined){
                    $selectBaseMap.hSelect('widget').css('width', '200px');
                }

                $dlg.find('input[name="popup_behaviour"]')
                    .change(function(event){

                        var val = $dlg.find('input[name="popup_behaviour"]:checked').val();

                        if(val == 'scale'){
                            $dlg.find('span#pw_label').text('Max width:');
                        }else{
                            $dlg.find('span#pw_label').text('Width:');
                        }

                        if(val == 'scale' || val == 'fixed_width'){
                            $dlg.find('span#ph_label').text('Max length:');
                        }else{
                            $dlg.find('span#ph_label').text('Length:');
                        }
                    });
            }else
            if(widget_name=='heurist_StoryMap'){
                
                if(!opts['reportOverviewMode']) opts['reportOverviewMode'] = 'inline';
                if(!opts['reportElementMode']) opts['reportElementMode'] = 'vertical';
                if(!opts['reportElementMapMode']) opts['reportElementMapMode'] = 'linked';
                
                
                $dlg.find('select[name="reportOverviewMode"]').val(opts['reportOverviewMode']);
                
                var $selectTemplate = $dlg.find('select[name="reportOverview"]'); 

                window.hWin.HEURIST4.ui.createTemplateSelector( $selectTemplate
                    ,[{key:'',title:'Standard record view'}], opts['reportOverview']);
                    
                $selectTemplate = $dlg.find('select[name="reportElement"]'); 

                window.hWin.HEURIST4.ui.createTemplateSelector( $selectTemplate
                    ,[{key:'',title:'Standard record view'}], opts['reportElement']);

                    
                var $elementOrder = $dlg.find('select[name="elementOrder"]'); 

                window.hWin.HEURIST4.ui.createRectypeDetailSelect( $elementOrder[0], null, ['date','year','integer']
                    ,[{key:'',title:'As is (order in record)'},{key:'def',title:'Common date fields (##9,10,11)'}]
                    ,{selectedValue:opts['elementOrder']});
                    
                /*
                var selectFields = $dlg.find('select[name="storyFields"]'); 
                window.hWin.HEURIST4.ui.createRectypeDetailSelect(selectFields[0], 
                        null, 'resource', 
                        [{key:'',title:''}], {selectedValue:opts['storyFields']});
                */
                var defValue = $dlg.find('input[name="storyFields"]').val();
                
                if(window.hWin.HEURIST4.util.isempty(defValue)){
                    var DT_STORY_FIELD = $Db.getLocalID('dty','1414-1089');// Story element field    
                    if(DT_STORY_FIELD>0) defValue = DT_STORY_FIELD;
                }
                
                
                var ele = $dlg.find('#storyFields');

                var ed_options = {
                        recID: -1,
                        dtID: ele.attr('id'), 
                        values: [defValue],
                        readonly: false,
                        showclear_button: true,
                        dtFields:{
                            dty_Type:"resource", rst_MaxValues:1,
                            rst_DisplayName: 'Story element fields', 
                            rst_DisplayHelpText: 'Defines the field that points to story element records (such as Life event, Ocuppation etc)',
                            rst_FieldConfig: {entity:'defDetailTypes', csv:true, filters:{types:['resource']}}
                        },
                        change:function(){
                        }
                    };

                ele.editing_input(ed_options);
                ele.parent().css('display','block');
                ele.find('.header').css({'width':'150px','text-align':'right'});
                
                //----------------
                /*
                ele = $dlg.find('#storyRectypes');

                var ed_options = {
                        recID: -1,
                        dtID: ele.attr('id'), 
                        values: [$dlg.find('input[name="storyRectypes"]').val()],
                        readonly: false,
                        showclear_button: true,
                        dtFields:{
                            dty_Type:"resource", rst_MaxValues:1,
                            rst_DisplayName: 'Story element record types', rst_DisplayHelpText:'',
                            rst_FieldConfig: {entity:'defRecTypes', csv:true}
                        },
                        change:function(){
                        }
                    };

                ele.editing_input(ed_options);
                ele.parent().css('display','block');
                ele.find('.header').css({'width':'150px','text-align':'right'});
                */
                
                $dlg.find('select[name="reportElementMode"]')
                .change(function(e){

                    $dlg.find('select[name="reportElementSlideEffect"]').parent().hide();
                    $dlg.find('select[name="reportElementDistinct"]').parent().hide();
                    
                    if($(e.target).val()=='vertical'){
                        $dlg.find('select[name="reportElementDistinct"]').parent().show();
                    }else if($(e.target).val()=='slide'){
                        $dlg.find('select[name="reportElementSlideEffect"]').parent().show();
                    }
                    
                })
                .val(opts['reportElementMode']).change();

                $dlg.find('select[name="reportElementMapMode"]')
                .change(function(e){

                    if($(e.target).val()=='filtered'){
                        $dlg.find('input[name="reportElementMapFilter"]').parent().show();
                    }else{
                        $dlg.find('input[name="reportElementMapFilter"]').parent().hide();
                    }
                    
                })
                .val(opts['reportElementMapMode']).change();
                
                
            }else
            if(widget_name=='heurist_resultListExt' && 
                            $dlg.find('select[name="rep_template"]').find('options').length==0){

                                var $select3 = $dlg.find('select[name="rep_template"]'); 

                                window.hWin.HEURIST4.ui.createTemplateSelector( $select3 
                                    ,null, $select3.attr('data-template'));


            }else 
            if(widget_name=='heurist_resultList' && 
                            $dlg.find('select[name="rendererExpandDetails"]').find('options').length==0){

                                    var $select4 = $dlg.find('select[name="rendererExpandDetails"]'); 

                                    window.hWin.HEURIST4.ui.createTemplateSelector( $select4
                                        ,[{key:'',title:'Standard record view template'}], $select4.attr('data-template'));

            }
            

        }
    }
    
   
    //
    // from UI to widget properties
    //
    function _getValues(){
        
        
        function __prepareVal(val){
            if(val==='false'){
                val = false;
            }else if(val==='true'){
                val = true;
            }
            return val;
        }
        
        
        var widget_name = widget_cfg.appid;

        var opts = {};

        if(widget_name=='heurist_Map'){

            var layout_params = {};//special option for leaflet mapping
            //parameters for controls
            layout_params['notimeline'] = !$dlg.find("#use_timeline").is(':checked');
            layout_params['nocluster'] = !$dlg.find("#use_cluster").is(':checked');
            layout_params['editstyle'] = $dlg.find("#editstyle").is(':checked');
            layout_params['map_rollover'] = $dlg.find("#map_rollover").is(':checked');
            //@todo select basemap from selector
            //layout_params['basemap']

            var ctrls = [];
            $dlg.find('input[name="controls"]:checked').each(
                function(idx,item){ctrls.push($(item).val());}
            );
            if(ctrls.length>0){
                layout_params['controls'] = ctrls.join(',');    
            }else{
                layout_params['controls'] = 'none';
            }
            if(ctrls.indexOf('legend')>=0){ //legend is visible
                ctrls = [];
                $dlg.find('input[name="legend"]').each( //visible section in legend
                    function(idx,item){
                        var is_exp = $dlg.find('input[name="legend_exp"][value="'+$(item).val()+'"]').is(':checked')?'':'-'
                        ctrls.push(is_exp+$(item).val());
                    }
                );
                if(ctrls.length>0){
                    layout_params['legend'] = ctrls.join(',');    
                    if(!$dlg.find('input[name="legend_exp2"]').is(':checked')){
                        layout_params['legend'] += ',off';    
                    }
                    var w = $dlg.find('input[name="legend_width"]').val();
                    if(w>100 && w<600){
                        layout_params['legend'] += (','+w);
                    }
                } 
            }
            
            layout_params['published'] = 1;
            layout_params['template'] = $dlg.find('select[name="map_template"]').val();
            layout_params['basemap'] = $dlg.find('select[name="map_basemap"]').val();
            layout_params['basemap_filter'] = $dlg.find('input[name="map_basemap_filter"]').val();

            layout_params['popup_behaviour'] = $dlg.find('input[name="popup_behaviour"]:checked').val();
            layout_params['popup_width'] = $dlg.find('input[name="popup_width"]').val() + $dlg.find('select[name="popup_wunit"]').val();
            layout_params['popup_height'] = $dlg.find('input[name="popup_height"]').val() + $dlg.find('select[name="popup_hunit"]').val();
            layout_params['popup_resizing'] = false;//$dlg.find('input[name="popup_resizing"]').is(':checked')
            
            if($dlg.find('input[name="map_maxzoom"]').val()>0){
                layout_params['maxzoom'] = $dlg.find('input[name="map_maxzoom"]').val();
            }
            if($dlg.find('input[name="map_minzoom"]').val()>0){
                layout_params['minzoom'] = $dlg.find('input[name="map_minzoom"]').val();
            }
            if($dlg.find('input[name="map_pntzoom"]').val()>0){
                layout_params['pntzoom'] = $dlg.find('input[name="map_pntzoom"]').val();
            }

  
//  use_timeline use_cluster editstyle map_rollover controls legend legend_exp legend_exp2 map_popup  mapdocument      
//  map_template map_basemap_filter  map_template
  
            var popup = $dlg.find('input[name="map_popup"]:checked').val();
            if(popup!='standard'){
                layout_params['popup'] = popup;
            }

            opts['layout_params'] = layout_params;
            opts['leaflet'] = true;

            var mapdoc_id = $dlg.find('select[name="mapdocument"]').val();
            if(mapdoc_id>0) opts['mapdocument'] = mapdoc_id;

            opts['custom_links'] = $dlg.find('textarea[name="custom_links"]').val(); 
            opts['current_search_filter'] = $dlg.find('input[name="current_search_filter"]').val();   
        }

                var cont = $dlg.find('div.'+widget_name);

                if(widget_name=='heurist_SearchTree'){
                    cont.find('input[name="allowed_UGrpID"]').val( 
                        cont.find('#allowed_UGrpID').editing_input('getValues') );
                    cont.find('input[name="allowed_svsIDs"]').val( 
                        cont.find('#allowed_svsIDs').editing_input('getValues') );
                    cont.find('input[name="init_svsID"]').val( 
                        cont.find('#init_svsID').editing_input('getValues') );
                }else 
                if(widget_name=='heurist_Navigation'){
                    var menu_recIDs = cont.find('#menu_recIDs').editing_input('getValues');
                    if(window.hWin.HEURIST4.util.isempty(menu_recIDs) || 
                        ($.isArray(menu_recIDs)&& (menu_recIDs.length==0||window.hWin.HEURIST4.util.isempty(menu_recIDs[0]))))
                    {
                        window.hWin.HEURIST4.msg.showMsgErr('Please set at least one top level menu item');                     
                        return false;   
                    }
                    cont.find('input[name="menu_recIDs"]').val( menu_recIDs );
                }else
                if(widget_name=='heurist_StoryMap'){
                    //var storyRectypes = cont.find('#storyRectypes').editing_input('getValues');
                    //cont.find('input[name="storyRectypes"]').val( storyRectypes );
                    
                    //cont.find('select[name="storyFields"]').val
                    var storyFields = cont.find('#storyFields').editing_input('getValues');
                    cont.find('input[name="storyFields"]').val( storyFields );
                    
                    if(window.hWin.HEURIST4.util.isempty(storyFields) || 
                        ($.isArray(storyFields)&& (storyFields.length==0||window.hWin.HEURIST4.util.isempty(storyFields[0]))))
                    {
                        window.hWin.HEURIST4.msg.showMsgErr('Please set at least one story field');                     
                        return false;   
                    }
                }
                
//controls":false,"legend":true,"legend_width":"250","legend_exp":false,"legend_exp2":false,
                //find INPUT elements and fill opts with values
                cont.find('input').each(function(idx, item){
                    item = $(item);
                    if(item.attr('name') && item.parents('.heurist_Map').length==0){ //item.attr('name').indexOf('map')<0){
                        if(item.attr('type')=='checkbox'){
                            opts[item.attr('name')] = item.is(':checked');    
                        }else if(item.attr('type')=='radio'){
                            if(item.is(':checked')) opts[item.attr('name')] = __prepareVal(item.val());    
                        }else if(item.val()!=''){
                            opts[item.attr('name')] = __prepareVal(item.val());    
                        }
                    }
                });
                //find SELECT
                $dlg.find('div.'+widget_name+' select').each(function(idx, item){
                    item = $(item);
                    if(item.parents('.heurist_Map').length==0){
                        opts[item.attr('name')] = item.val();     
                    }
                });

                if(widget_name=='heurist_resultListExt'){
                    opts['template'] = $dlg.find('select[name="rep_template"]').val();
                    opts['reload_for_recordset'] = true;
                    opts['emptysetmessage'] = $dlg.find('#empty_remark').val() == '' ? 'def' : $dlg.find('#empty_remark').val();
                    opts['url'] = 'viewers/smarty/showReps.php?publish=1&debug=0'
                    +'&emptysetmessage='+encodeURIComponent(opts['emptysetmessage'])
                    +'&template='+encodeURIComponent(opts['template'])
                    +'&[query]';
                    opts['placeholder_text'] = $dlg.find('#placeholder_text').val();
                }else if(widget_name=='heurist_resultList'){
                    opts['show_toolbar'] = opts['show_counter'] || opts['show_viewmode'];
                    if(window.hWin.HEURIST4.util.isempty(opts['recordview_onselect'])){
                        opts['recordview_onselect']  = 'inline'; //default value    
                    }
                    opts['empty_remark'] = $dlg.find('#empty_remark').val();
                    opts['placeholder_text'] = $dlg.find('#placeholder_text').val();
                }else if(widget_name=='heurist_resultListDataTable'){
                    opts['dataTableParams'] = $dlg.find('#dataTableParams').val();
                    opts['emptyTableMsg'] = $dlg.find('#empty_remark').val();
                    opts['placeholder_text'] = $dlg.find('#placeholder_text').val();
                }

                var selval = opts.searchTreeMode;
                if(window.hWin.HEURIST4.util.isempty(opts.allowed_UGrpID)){ //groups are not defined

                    if(selval==1){
                        window.hWin.HEURIST4.msg.showMsgErr('For "tree" mode you have to select groups to be displayed');
                        return false;
                    }else if (window.hWin.HEURIST4.util.isempty(opts.allowed_svsIDs) && selval==0) { //individual filters are not defined
                        window.hWin.HEURIST4.msg.showMsgErr('For "button" mode you must select either workgroups or filters individually');
                        return false;
                    }
                }

//console.log(opts);          

        opts['init_at_once'] = true;
        opts['search_realm'] = $dlg.find('input[name="search_realm"]').val();
        opts['search_page'] = $dlg.find('select[name="search_page"]').val();
        opts['widget_id'] = $dlg.find('input[name="widget_id"]').val();    

        return opts;
    }//_getValues



    //public members
    var that = {

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            return (strClass === _className);
        },
    }

    _init();
    
    return that;
}



