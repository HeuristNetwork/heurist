//this script will be executed once after addition of template
//it adds new faceted search and new smarty template
function _prepareTemplateBlog(new_element_json, callback){

    var sfilter = {"facets":[{"var":84411,"code":"7:added","title":"Added","groupby":"month",
            "orderby":"desc","type":"date","order":0,"isfacet":"3","help":""},
            {"var":75653,"code":"7:lt15:10,4:title","title":"Creator(s)","orderby":null,
            "type":"freetext","order":1,"isfacet":"3","help":"","multisel":false},
            {"var":84259,"code":"7:942","title":"Category","orderby":null,"type":"enum","order":2,"isfacet":"3","help":"","multisel":false},
            {"var":75132,"code":"7:1","title":"Title of post","help":"","isfacet":"0","multisel":false,"orderby":null,"type":"freetext","order":3}],"rectypes":["7"],"version":2,"rules":"","rulesonly":0,"sup_filter":"","ui_title":"Project blog","ui_viewmode":"","search_on_reset":true,"ui_prelim_filter_toggle":false,"ui_prelim_filter_toggle_mode":0,"ui_prelim_filter_toggle_label":"Apply preliminary filter","ui_spatial_filter":false,"ui_spatial_filter_init":false,"ui_spatial_filter_label":"Map Search","ui_spatial_filter_initial":"","ui_additional_filter":false,"ui_additional_filter_label":"Search everything","ui_exit_button":true,"ui_exit_button_label":"","domain":"all","title_hierarchy":false,"viewport":null,"sort_order":"-a","ui_temporal_filter_initial":"after:\"1 week ago\""};

    //@todo - create unique name for filter (counter)
    var request = {svs_Name: 'Blog navigation (retain if blog required)',
                svs_Query: JSON.stringify(sfilter),
                svs_UGrpID: '1'}; // Database managers
                
    window.hWin.HAPI4.SystemMgr.ssearch_save(request,
        function(response){
            if(response.status == window.hWin.ResponseStatus.OK){

                var svsID = response.data;
                //replace search realm
                var realm_id = 'sr'+window.hWin.HEURIST4.util.random();

                var ele = layoutMgr.layoutContentFindWidget(new_element_json, 'heurist_recordAddButton');
                if(ele){
                    ele.options.RecTypeID = window.hWin.HAPI4.sysinfo['dbconst']['RT_BLOG_ENTRY'];
                }
            
                ele = layoutMgr.layoutContentFindWidget(new_element_json, 'heurist_SearchTree');
                if(ele){
                    ele.options.search_realm = realm_id;
                    ele.options.init_svsID = svsID;
                    ele.options.allowed_svsIDs = svsID;
                }
                
                //copy blog template to smarty folder
                var baseurl = window.hWin.HAPI4.baseURL + "viewers/smarty/templateOperations.php";
                
                var request = {mode:'import', 
                               import_template:{name:'Blog post format - DO NOT DELETE.tpl',tmp_name:'cms/Blog entry.gpl',size:999}, 
                               db:window.hWin.HAPI4.database};
                
                window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, function(res){
                    
                    if(res['error']){
                        window.hWin.HEURIST4.msg.showMsgErr(res['error'], true);
                    }else{
                        var template_name = res['ok'];

                        var ele = layoutMgr.layoutContentFindWidget(new_element_json, 'heurist_resultList');
                        if(ele){
                            ele.options.search_realm = realm_id;
                            ele.options.rendererExpandDetails = template_name;
                        }
                      
                        if($.isFunction(callback)) callback.call(this, new_element_json);
                    }
                    
                });
                
                
                
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response, true);
            }
        }

    );//ssearch_save            

}