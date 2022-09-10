/*
* editCMS_Manager.js - loads websiteRecord.php in edit mode - either in popup or in container
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

/**
* @param options

record_id - home page id
callback
webpage_title
webpage_private
container

*/
function editCMS_Manager( options ){

    var _className = "EditCMS_Manager";


    var home_page_record_id = options.record_id,
    main_callback = options.callback,
    webpage_title = options.webpage_title,
    webpage_private = (options.webpage_private==true);

    var RT_CMS_HOME = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'],
    RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'],
    DT_CMS_TOP_MENU = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TOP_MENU'],
    DT_CMS_MENU  = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_MENU'],
    //     DT_CMS_THEME = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_THEME'],
    DT_NAME       = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
    DT_CMS_HEADER = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_HEADER'];

    if(!(RT_CMS_HOME>0 && RT_CMS_MENU>0 && DT_CMS_TOP_MENU>0 && DT_CMS_MENU>0)){
        
        window.hWin.HAPI4.SystemMgr.checkPresenceOfRectype('99-51',2,
            'You will need record types '
            +'99-51 (Web home) and 99-52 (Web menu/content) which are available as part of Heurist_Core_Definitions.',
                function(){
                   editCMS_Manager( options ); //call itself again 
                }
            );

        return;
    }
    
    options.is_open_in_new_tab = true;
    
    
    if(home_page_record_id<0){
        _createNewWebContent();
        return;
    }
    
    
    var edit_dialog = null;
    var web_link, tree_element = null;
    var was_something_edited = false;
    var remove_menu_records = false;
    var open_page_on_init = -1;
    var home_page_record_title = '';

    var isWebPage = false; //single page website/embed otherwise website with menu

    _initWebSiteEditor();
    
    //
    // Creates new web site or page
    //
    function _createNewWebContent(){
        
            isWebPage = (home_page_record_id==-2);
            
            options.is_new_website = !isWebPage;
            
            if(options.container) options.container.empty();

            //create new set of records - website template -----------------------
            window.hWin.HEURIST4.msg.bringCoverallToFront(edit_dialog && edit_dialog.dialog('instance')?edit_dialog.parents('.ui-dialog'):null); 
            window.hWin.HEURIST4.msg.showMsgFlash(
                (isWebPage
                    ?'Creating default layout (webpage) record'
                    :'Creating the set of website records')
                , 10000);

            var session_id = Math.round((new Date()).getTime()/1000); //for progress

            var request = { action: 'import_records',
                filename: isWebPage?'webpageStarterRecords.xml':'websiteStarterRecords.xml',
                is_cms_init: 1,
                make_public: (webpage_private===true)?0:1 ,
                //session: session_id,
                id: window.hWin.HEURIST4.util.random()
            };

            //create default set of records for website see importController
            function __callback( response ){
                
                $dlg = window.hWin.HEURIST4.msg.getMsgFlashDlg();
                $dlg.dialog('close');

                window.hWin.HEURIST4.msg.sendCoverallToBack();

                if(response.status == window.hWin.ResponseStatus.OK){
                    $('#spanRecCount2').text(response.data.count_imported);

                    if(isWebPage){
                        //update title of webpage
                        if(!window.hWin.HEURIST4.util.isempty(webpage_title)){

                            var page_recid = response.data.ids[0];

                            //replace name of webpage to the provided one
                            var request = {a: 'replace',
                                recIDs: page_recid,
                                dtyID: window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
                                rVal: webpage_title};
                            window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                                if(response.status == hWin.ResponseStatus.OK){
                                    options.record_id = page_recid;
                                    editCMS_Manager( options );
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                }
                            });
                            return;
                        }
                    }else{

                        window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
                            +'hclient/widgets/cms/editCMS_NewSiteMsg.html');
//console.log(response.data);          
                        //add blog template
                        if(response.data.page_id_for_blog>0){
                            _addTemplate('blog', response.data.page_id_for_blog);
                        }

                        options.record_id = response.data.home_page_id>0?response.data.home_page_id:response.data.ids[0];
                        editCMS_Manager( options );                                

                    }

                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    closeCMSEditor();
                }
            };
            
            //__callback({status:'ok',data:{home_page_id:572}}); //DEBUG
            
            window.hWin.HAPI4.doImportAction(request, __callback);

            
    } //_createNewWebContent
    
    //
    // replace content of page with template
    //
    function _addTemplate(template_name, affected_page_id){
    

        // 1. load template files
        var sURL = window.hWin.HAPI4.baseURL+'hclient/widgets/cms/templates/snippets/'+template_name+'.json';

        // 2. Loads template json
        $.getJSON(sURL, 
        function( new_element_json ){
            
            if(!layoutMgr) hLayoutMgr();
            
            layoutMgr.prepareTemplate(new_element_json, function(updated_json){

                //replace content of blog webpage
                var request = {a: 'replace',
                    recIDs: affected_page_id,
                    dtyID: window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'],
                    rVal: JSON.stringify( updated_json )};
                window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                    if(response.status == hWin.ResponseStatus.OK){
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
                               
            }); 
        });
    }
    
    
    //
    // load website/page and open cms editor in iframe within heurist interface
    //
    function _initWebSiteEditor(){
        
        var sURL = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&website&id='+options.record_id;
        
        if(options.is_open_in_new_tab){ //open in new tab

            sURL = sURL + '&edit=2';
            if(options.is_new_website){
                sURL = sURL + '&newlycreated'; // was edit=3
            }
            window.open(sURL, '_blank');
        }else {
            
            sURL = sURL + '&edit=4';
            
            if(options.container){ //load into container
                

                options.container.empty();
                edit_dialog = options.container;
                options.container.html('<iframe id="web_preview" style="overflow:none !important;width:100% !important"></iframe>');
                
                options.container.find('#web_preview').attr('src', sURL);

            }else{ 
                //load into popup dialog
                var edit_buttons = [
                    {text:window.hWin.HR('Close'), 
                        id:'btnRecCancel',
                        css:{'float':'right'}, 
                        click: closeCMSEditor
                    }
                ];

                //var body = $(this.document).find('body');
                //var dim = {h:body.innerHeight(), w:body.innerWidth()};
                //dim.h = (window.hWin?window.hWin.innerHeight:window.innerHeight);
                
                edit_dialog = window.hWin.HEURIST4.msg.showMsgDlgUrl(sURL,
                    edit_buttons, window.hWin.HR('Define Website'),
                    {   
                        //open: onDialogInit, 
                        width:'95%', height:'95%', isPopupDlg:true, 
                        close:function(){
                            edit_dialog.empty();//dialog('destroy');
                        },
                        beforeClose: beforeCloseCMSEditor
                });
            }
        }
    
    }
    

    //
    //
    //
    function closeCMSEditor()
    {
        if(edit_dialog && edit_dialog.dialog('instance')){
            edit_dialog.dialog('close');     
        }else if(beforeCloseCMSEditor()){
            closeCMSEditorFinally();
        }
    }

    //
    //
    //
    function closeCMSEditorFinally(){
        if(edit_dialog){
            if(edit_dialog.dialog('instance')){
                edit_dialog.dialog('close');     
            }else{
                edit_dialog.hide();
                if(options.container){
                    options.container.empty();
                }
            }
        }
        editCMS_instance = null;
    }

    //
    //
    //
    function beforeCloseCMSEditor(){
        if(edit_dialog){
            var preview_frame = edit_dialog.find('#web_preview');
            if(preview_frame.length>0 && preview_frame[0].contentWindow.cmsEditing){
                //check that everything is saved
                var res = preview_frame[0].contentWindow.cmsEditing.onEditorExit(
                    function( need_close_explicitly ){
                        //exit allowed
                        if(need_close_explicitly!==false) closeCMSEditorFinally();

                        if($.isFunction(main_callback) && home_page_record_id>0){
                            main_callback( home_page_record_id, home_page_record_title ); 
                        }
                });
                return res;
            }
        }else{
            return true;
        }
    }    

    //public members
    var that = {

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            return (strClass === _className);
        },
        
        closeCMS: function(){
            return closeCMSEditor();
        }
    }
    
    return that;
}
   
