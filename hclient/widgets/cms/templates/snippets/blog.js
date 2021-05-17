var container = $('div[data-template-temp]');

//this script will be executed once after addition of template
//add new faceted search

var sfilter = {"facets":[{"var":43482,"code":"7:1111","title":"Month","help":"","isfacet":"3","multisel":true,"type":"enum","order":0,"orderby":null},{"var":21948,"code":"7:1110","title":"Author","help":"","isfacet":"3","multisel":false,"type":"enum","order":1,"orderby":null},{"var":25488,"code":"7:1112","title":"Categories","help":"","isfacet":"3","multisel":false,"type":"enum","order":2,"orderby":null},{"var":82034,"code":"7:1","title":"Title of post","help":"","isfacet":"0","multisel":false,"type":"freetext","order":3,"orderby":null}],"rectypes":["7"],"version":2,"rules":"","rulesonly":0,"sup_filter":"t:7 sortby:-m ","ui_title":"Project blog","ui_viewmode":"","search_on_reset":true,"ui_prelim_filter_toggle":false,"ui_prelim_filter_toggle_mode":0,"ui_prelim_filter_toggle_label":"Apply preliminary filter","ui_spatial_filter":false,"ui_spatial_filter_init":false,"ui_spatial_filter_label":"Map Search","ui_spatial_filter_initial":"","ui_additional_filter":false,"ui_additional_filter_label":"Search everything","ui_exit_button":true,"ui_exit_button_label":"","domain":"all","title_hierarchy":false,"viewport":null,"sort_order":"-a"};


var request = {svs_Name: 'Project blog',
            svs_Query: JSON.stringify(sfilter),
            svs_UGrpID: '4'}; //Website filters
            
window.hWin.HAPI4.SystemMgr.ssearch_save(request,
    function(response){
        if(response.status == window.hWin.ResponseStatus.OK){

            var svsID = response.data;

            //window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID] = [request.svs_Name, request.svs_Query, request.svs_UGrpID];

            //replace template values
            container.find('a[data-heurist-id="add_link"]').attr('href',window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+
            '&rec_rectype=7&rec_owner=1&rec_visibility=public'); //@todo replace rectype

            //replace search realm
            var realm_id = 'sr'+window.hWin.HEURIST4.util.random();
            var widgetid = 'mywidget_'+window.hWin.HEURIST4.util.random();

            //replace widget id, filter id and realm
            var ele = container.find('div[data-heurist-app-id="heurist_SearchTree"]');
            var content = window.hWin.HEURIST4.util.isJSON(ele.text());
            console.log(ele.text());
            console.log(content);
            ele.attr('id', widgetid);
            content.search_realm = realm_id;
            content.init_svsID = svsID;
            content.allowed_svsIDs = svsID;
            ele.text(JSON.stringify(content));

            //copy blog template to smarty folder
            var baseurl = window.hWin.HAPI4.baseURL + "viewers/smarty/templateOperations.php";
            
            var request = {mode:'import', 
                           import_template:{name:'Blog entry.tpl',tmp_name:'cms/Blog entry.gpl',size:999}, 
                           db:window.hWin.HAPI4.database};
            
            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, function(res){
                
                if(res['error']){
                    window.hWin.HEURIST4.msg.showMsgErr(res['error'], true);
                }else{
                    var template_name = res['ok'];
                    
                    var ele = container.find('div[data-heurist-app-id="heurist_resultList"]');
                    var content = window.hWin.HEURIST4.util.isJSON(ele.text());
                    ele.attr('id', widgetid+1);
                    content.search_realm = realm_id;
                    content.rendererExpandDetails = template_name;
                    ele.text(JSON.stringify(content));
                    
                    _onScriptExecute();
                }
                
            });
            
        }else{
            window.hWin.HEURIST4.msg.showMsgErr(response, true);
        }
    }

);//ssearch_save
