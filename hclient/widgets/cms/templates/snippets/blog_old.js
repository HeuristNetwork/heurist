var container = $('div[data-template-temp]');

//this script will be executed once after addition of template
//it adds new faceted search and new smarty template

var sfilter = {"facets":[{"var":84411,"code":"7:added","title":"Added","groupby":"month","orderby":"desc","type":"date","order":0,"isfacet":"3","help":""},{"var":75653,"code":"7:lt15:10,4:title","title":"Creator(s)","orderby":null,"type":"freetext","order":1,"isfacet":"3","help":"","multisel":false},{"var":84259,"code":"7:942","title":"Category","orderby":null,"type":"enum","order":2,"isfacet":"3","help":"","multisel":false},{"var":75132,"code":"7:1","title":"Title of post","help":"","isfacet":"0","multisel":false,"orderby":null,"type":"freetext","order":3}],"rectypes":["7"],"version":2,"rules":"","rulesonly":0,"sup_filter":"","ui_title":"Project blog","ui_viewmode":"","search_on_reset":true,"ui_prelim_filter_toggle":false,"ui_prelim_filter_toggle_mode":0,"ui_prelim_filter_toggle_label":"Apply preliminary filter","ui_spatial_filter":false,"ui_spatial_filter_init":false,"ui_spatial_filter_label":"Map Search","ui_spatial_filter_initial":"","ui_additional_filter":false,"ui_additional_filter_label":"Search everything","ui_exit_button":true,"ui_exit_button_label":"","domain":"all","title_hierarchy":false,"viewport":null,"sort_order":"-a","ui_temporal_filter_initial":"after:\"1 week ago\""};

//@todo - create unique name for filter (counter)

var request = {svs_Name: 'Blog navigation - DO NOT DELETE',
            svs_Query: JSON.stringify(sfilter),
            svs_UGrpID: '4'}; //Website filters
            
window.hWin.HAPI4.SystemMgr.ssearch_save(request,
    function(response){
        if(response.status == window.hWin.ResponseStatus.OK){

            var svsID = response.data;

            //replace template values
            container.find('a[data-heurist-id="add_link"]').attr('href',window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+
            '&rec_rectype=7&rec_owner=1&rec_visibility=public');
            
            /*
            var alink = container.find('a[data-heurist-id="add_link"]');
            alink.attr('onclick','function(e){'
                +'window.hWin.HEURIST4.util.stopEvent(e);'
                +'window.hWin.HEURIST4.ui.openRecordEdit(-1, null,{new_record_params:{RecTypeID:7,OwnerUGrpID:1,NonOwnerVisibility:'public'}});'
                +'return false;}');
            */

            //replace search realm
            var realm_id = 'sr'+window.hWin.HEURIST4.util.random();
            var widgetid = 'mywidget_'+window.hWin.HEURIST4.util.random();

            //replace widget id, filter id and realm
            var ele = container.find('div[data-heurist-app-id="heurist_recordAddButton"]');
            ele.attr('id', widgetid);
            var content = window.hWin.HEURIST4.util.isJSON(ele.text());
            content.search_realm = realm_id;
            ele.text(JSON.stringify(content));
            
            ele = container.find('div[data-heurist-app-id="heurist_SearchTree"]');
            var content = window.hWin.HEURIST4.util.isJSON(ele.text());
            //DEBUG console.log(ele.text());
            ele.attr('id', widgetid+1);
            content.search_realm = realm_id;
            content.init_svsID = svsID;
            content.allowed_svsIDs = svsID;
            ele.text(JSON.stringify(content));

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
                    
                    var ele = container.find('div[data-heurist-app-id="heurist_resultList"]');
                    var content = window.hWin.HEURIST4.util.isJSON(ele.text());
                    ele.attr('id', widgetid+2);
                    content.search_realm = realm_id;
                    content.rendererExpandDetails = template_name;
                    ele.text(JSON.stringify(content));
                  
                    container.trigger('oncomplete',{widgetid:widgetid});
                }
                
            });

            
        }else{
            window.hWin.HEURIST4.msg.showMsgErr(response, true);
        }
    }

);//ssearch_save            