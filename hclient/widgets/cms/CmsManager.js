/**
*  ActionHandler
*
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
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


class CmsManager {
    
    popup_dialog_options = {};

    cms_home_records_count = 0;
    cms_home_private_records_ids = 0;
    sMsgCmsPrivate = '';
    
    constructor() {
    }
    
    // Method to exhtecute an action by id
    executeAction(actionid) {
        
        switch (actionid) {
            //action dialogs
            case "menu-cms-create":
                this.createNewWebSite();
                break;
            case "menu-cms-create-page":
                this.createNewWebPage();
                break;
            case 'menu-cms-edit-page':
            case 'menu-cms-view-page':                
                this.selectSelectPage(actionid);
                break;
            case 'menu-cms-edit':
            case 'menu-cms-view':
                this.selectSelectWebSite(actionid);
                break;
            
        }
        
    }
    
    selectSelectWebSite(action){
                //if(popup_dialog_options.record_id>0){
                //        window.hWin.HEURIST4.ui.showEditCMSDialog( popup_dialog_options );                    
            let is_view_mode = (action=='menu-cms-view');
            var that = this;
            this._getCountWebSiteRecords(function(){
                if(this.cms_home_records_count>0){
                        if(that.sMsgCmsPrivate!=''){
                                let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(that.sMsgCmsPrivate,
                                   {Continue:function(){ 
                                        $dlg.dialog('close'); 
                                        this._select_CMS_Home( is_view_mode ); 
                                   }},
                                   'Non-public website records',
                                   {default_palette_class: 'ui-heurist-publish'});
                        }else{
                                this._select_CMS_Home( is_view_mode, this.popup_dialog_options );    
                        }
                }else{
                            window.hWin.HEURIST4.msg.showMsgDlg(
                                    'New website will be created. Continue?',
                                    function(){
                                        this.createNewWebSite();                  
                                    });
                }
        
            });
    }
    
    
    selectSelectPage(action){
            //if(popup_dialog_options.record_id>0){
            //    window.hWin.HEURIST4.ui.showEditCMSDialog( popup_dialog_options );                    
            //}else{ }
    
            this._getCountWebPageRecords(function( count ){
                if(count>0){ 
                    //select aong existing
                    this._select_WebPage( (action == 'menu-cms-view-page') );    
                }else{
                    this.createNewWebPage();
                }
            });
    }
    
    createNewWebPage() {
            window.hWin.HAPI4.SystemMgr.check_allow_cms({a:'check_allow_cms'}, function(response){

                if(response.status == window.hWin.ResponseStatus.OK){
                    this._create_WebPage();    
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
    
    }
    //
    //
    //
    createNewWebSite() {

        var that = this;
        
            window.hWin.HAPI4.SystemMgr.check_allow_cms({a:'check_allow_cms'}, 
            function(response){

                if(response.status == window.hWin.ResponseStatus.OK){
                    const RT_CMS_HOME = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'],
                          RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'];

                    that._getCountWebSiteRecords(function(){

                        let sMsg = '';

                        if(RT_CMS_HOME>0 && that.cms_home_records_count>0){

                            sMsg = 'You already have '+
                            ((that.cms_home_records_count==1)?'a website'
                                :(that.cms_home_records_count+' websites'))+
                            '. Are you sure you want to create an additional site?';

                            if(that.sMsgCmsPrivate!=''){
                                sMsg = sMsg + that.sMsgCmsPrivate;    
                            }

                        }else if(RT_CMS_HOME > 0 && RT_CMS_MENU > 0){
                            sMsg = 'Are you sure you want to create a site?';
                        }else{
                            // construct missing part of msg
                            let missing = RT_CMS_HOME > 0 ? 'CMS_Home (Concept-ID: 99-51)' : '';
                            missing = RT_CMS_MENU > 0 && missing == '' ? 'CMS Menu-Page (Concept-ID: 99-52)' : RT_CMS_MENU > 0 ? missing + ' and CMS Menu-Page (Concept-ID: 99-52)' : missing;
                            missing += (RT_CMS_HOME <= 0 && RT_CMS_MENU <= 0 ? ' record types' : ' record type');

                            window.hWin.HEURIST4.msg.showMsgErr({
                                message: `Your database is missing the ${missing}.<br>`
                                        +'These record types can be downloaded from the Heurist_Bibliographic database (# 6) using Design > Browse templates.<br>'
                                        +'You will need to refresh your window after downloading the record type(s) for the additions to take affect.',
                                error_title: 'Missing required record types'
                            });
                            return;
                        }

                        sMsg = sMsg 
                        + '<p>Check the box if you wish to keep your website private '
                        + '<br><input type="checkbox"> hide website (can be changed later)</p>';

                        let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(sMsg,
                            function(){ 
                                let chb = $dlg.find('input[type="checkbox"]');
                                let is_private = chb.is(':checked');
                                
                                this.popup_dialog_options.record_id = -1;
                                this.popup_dialog_options.webpage_private = is_private;
                                
                                window.hWin.HEURIST4.ui.showEditCMSDialog(popup_dialog_options); 
                            },
                            window.hWin.HR('New website'),
                            {default_palette_class: 'ui-heurist-publish'});

                        });
                        
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });        
        
    } //createNewWebSite
    
    
    _getCountWebPageRecords(callback){
    
        let RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'],
        DT_CMS_PAGETYPE = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGETYPE'];
        
        let query_search_pages = {t:RT_CMS_MENU}
        query_search_pages['f:'+DT_CMS_PAGETYPE] = window.hWin.HAPI4.sysinfo['dbconst']['TRM_PAGETYPE_WEBPAGE'];
    
        let request = {q:query_search_pages, w: 'a', detail: 'count', source:'_getCountWebPageRecords' };
        window.hWin.HAPI4.RecordMgr.search(request, function(response){
            if(response.status == window.hWin.ResponseStatus.OK){
                callback.call(this, response.data.count);
            }
        });
        
    }
    
     //
     //
     //   
    _getCountWebSiteRecords(callback){
        let RT_CMS_HOME = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'];
        if(RT_CMS_HOME>0){
            let request = {
                    'a'       : 'counts',
                    'entity'  : 'defRecTypes',
                    'mode'    : 'cms_record_count',
                    'ugr_ID'  : window.hWin.HAPI4.currentUser['ugr_ID'],
                    //'rty_ID'  : RT_CMS_HOME
                    };
            let that = this;        
                                    
            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        that.cms_home_records_count = response.data['all'];
                        let aPriv = response.data['private'];

                        if(aPriv.length>0){
                            let cnt_home = response.data['private_home'];
                            let cnt_menu = response.data['private_menu'];
                            let s1 = '';                          
                            if(cnt_home>0){
                                if(cnt_home==1){
                                    that.cms_home_private_records_ids = response.data['private_home_ids'][0];
                                    s1 = '<p>Note: CMS website record is non-public. This website is not visible to the public.</p>';        
                                }else{
                                    s1 = '<p>Note: There are '+cnt_home+' non-public CMS website records. These websites are not visible to the public.</p>';
                                }
                            }            
                            
                            that.sMsgCmsPrivate = 
                            '<div style="margin-top:10px;padding:4px">'
+s1                            
+((cnt_menu>0)?('<p>Warning: There are '+cnt_menu+' non-public CMS menu/page records. Database login is required to see these pages in the website.'):'')                            
                            
                            /*+'This database has '+aPriv.length
                            +' CMS records which are hidden from public view - '
                            +'parts of your website(s) will not be visible to visitors '
                            +'who are not logged in to the database'*/
                            
                            +'<br><br>'
                            +'<a target="_blank" href="'+window.hWin.HAPI4.baseURL
                            +'?db='+window.hWin.HAPI4.database+'&q=ids:'+aPriv.join(',')+'">Click here</a>'
                            +' to view these records and set their visibility '
                            +'to Public (use Share > Ownership/Visibility)';
                        }else{
                            that.sMsgCmsPrivate = '';
                        }
                        
                        if(window.hWin.HEURIST4.util.isFunction(callback)) callback(that);
                    }
                });
        }else{
            this.cms_home_records_count = 0;
            this.cms_home_private_records_ids = 0;
            if(window.hWin.HEURIST4.util.isFunction(callback)) callback(this);
        }   
    }    
    
    
    //
    //
    _create_WebPage( popup_dialog_options )
    {
        let $dlg = window.hWin.HEURIST4.msg.showPrompt(
            window.hWin.HR('Name for new page')+':',
            function(value){ 

                popup_dialog_options.record_id = -2;
                popup_dialog_options.webpage_title = value;

                if(window.hWin.HEURIST4.util.isempty(value)){

                    window.hWin.HEURIST4.msg.showMsgFlash('Specify name',1000);

                }else{
                    //$dlg
                    //create new web page
                    window.hWin.HEURIST4.ui.showEditCMSDialog( popup_dialog_options );
                }
            },
            {title: window.hWin.HR('New standalone web page'), yes: window.hWin.HR('Create'), no: window.hWin.HR('Cancel')},
            {default_palette_class: 'ui-heurist-publish'});
    }
    
    //
    //
    _select_WebPage( is_view_mode, popup_dialog_options ){
        
        let RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'],
        DT_CMS_PAGETYPE = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGETYPE'];
        
        let query_search_pages = {t:RT_CMS_MENU, sort:'-id'};
        query_search_pages['f:'+DT_CMS_PAGETYPE] = window.hWin.HAPI4.sysinfo['dbconst']['TRM_PAGETYPE_WEBPAGE'];
        
        let that = this;
        
        let popup_options = {
                        select_mode: 'select_single', //select_multi
                        select_return_mode: 'recordset',
                        edit_mode: 'popup',
                        selectOnSave: true, //it means that select popup will be closed after add/edit is completed
                        title: window.hWin.HR('Select Web page'),
                        fixed_search: query_search_pages, // RT_CMS_MENU,
                        parententity: 0,
                        
                        layout_mode: 'listonly',
                        width:500, height:400,
                        default_palette_class: 'ui-heurist-publish',
                        
                        resultList:{
                            show_toolbar: false,
                            view_mode:'icons',
                            searchfull:null,
                            //search_realm: 'x',
                            //search_initial: , 
                            renderer:function(recordset, record){
                                let recID = recordset.fld(record, 'rec_ID')
                                let recTitle = recordset.fld(record, 'rec_Title'); 
                                let recTitle_strip_all = window.hWin.HEURIST4.util.htmlEscape(recTitle);
                                let html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'+recTitle_strip_all+'</div>';
                                return html;
                            }
                        },
                        
                        onselect:function(event, data){
                                 if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                    let recordset = data.selection;
                                    let rec_ID = recordset.getOrder()[0];
                                    
                                    if(is_view_mode){
                                        that._openCMS(rec_ID);
                                    }else{
                                        popup_dialog_options.record_id = rec_ID;
                                        window.hWin.HEURIST4.ui.showEditCMSDialog( popup_dialog_options );    
                                    }
                                    
                                 }
                        }
        };//popup_options
        
        window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);
    }    
    
    //@todo - move to editCMS_Records
    // show popup with list of web home records - on select either view or edit
    //                
    _select_CMS_Home ( is_view_mode, popup_dialog_options ){
        
        let RT_CMS_HOME = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'];
        if(!(RT_CMS_HOME>0)){
            window.hWin.HEURIST4.msg.showMsgDlg('This function is still in development. You will need record types '
                +'99-51 and 99-52 which will be made available as part of Heurist_Reference_Set. '
                +'You may contact support@HeuristNetwork.org if you want to test out this function prior to release.');
            return;
        }
        
        if(this.cms_home_records_count==1){ 

            if(is_view_mode){
                
                this._openCMS( this.cms_home_private_records_ids );
            }else{
                popup_dialog_options.record_id = 0;
                window.hWin.HEURIST4.ui.showEditCMSDialog( popup_dialog_options ); //load the only entry at once
            }
            return;
        }
        
        let query_search_sites = {t:RT_CMS_HOME, sort:'-id'};
        
        let that = this;
        
        let popup_options = {
                        select_mode: 'select_single', //select_multi
                        select_return_mode: 'recordset',
                        edit_mode: 'popup',
                        selectOnSave: true, //it means that select popup will be closed after add/edit is completed
                        title: window.hWin.HR('Select Website'),
                        fixed_search: query_search_sites,
                        //rectype_set: RT_CMS_HOME,
                        parententity: 0,
                        
                        layout_mode: 'listonly',
                        width:500, height:400,
                        default_palette_class: 'ui-heurist-publish',
                        
                        resultList:{
                            show_toolbar: false,
                            view_mode:'icons',
                            searchfull:null,
                            renderer:function(recordset, record){
                                let recID = recordset.fld(record, 'rec_ID')
                                let recTitle = recordset.fld(record, 'rec_Title'); 
                                let recTitle_strip_all = window.hWin.HEURIST4.util.htmlEscape(recTitle);
                                let html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'+recTitle_strip_all+'</div>';
                                return html;
                            }
                        },
                        
                        onselect:function(event, data){
                                 if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                    let recordset = data.selection;
                                    let rec_ID = recordset.getOrder()[0];
                                    
                                    if(is_view_mode){
                                        that._openCMS(rec_ID);
                                    }else{
                                        popup_dialog_options.record_id = rec_ID;
                                        window.hWin.HEURIST4.ui.showEditCMSDialog( popup_dialog_options );    
                                    }
                                    
                                 }
                        }
        };//popup_options
        
        window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);
        
    }
    
    //
    // VIEW CMS in production or development version
    // 
    _openCMS (rec_ID, force_production_version){
        
        let url = window.hWin.HAPI4.baseURL;
        
        if(force_production_version===true){

            //replace devlopment folder to production one (ie h6-ij to heurist)
            url = url.split('/');
            let i = url.length-1;
            while(i>0 && url[i]=='') i--;
            url[i]='heurist';
            url = url.join('/');
        
        }else if(force_production_version!==false 
            && window.hWin.HAPI4.installDir && !window.hWin.HAPI4.installDir.endsWith('/heurist/')){
        
            let that = this;    
            let buttons = {};
            buttons[window.hWin.HR('Current (development) version')]  = function() {
                let $dlg = window.hWin.HEURIST4.msg.getMsgDlg(); $dlg.dialog( "close" );
                that._openCMS(rec_ID, false);
            };                                 
            buttons[window.hWin.HR('Production version')]  = function() {
                let $dlg = window.hWin.HEURIST4.msg.getMsgDlg(); $dlg.dialog( "close" );
                that._openCMS(rec_ID, true);
            };                                 
            
            window.hWin.HEURIST4.msg.showMsgDlg('<p>You are currently running a development version of Heurist.</p>' 
+'<p>Reply "Current (development) version" to use the development version for previewing your site, but please do not publish this URL.</p>' 
+'<p>Reply "Production version" to obtain the URL for public dissemination, which will load the production version of Heurist.</p>' 
,buttons,null,{default_palette_class:'ui-heurist-publish'});
            
            return;
        }
        
        url = url+'?db='+window.hWin.HAPI4.database+'&website';
        if(rec_ID>0){
            url = url + '&id='+rec_ID;
        }
                                                    
        window.open(url, '_blank');
        
    }
    

}