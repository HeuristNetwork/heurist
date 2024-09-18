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
    
    cms_home_counts = null;    

    RT_CMS_HOME;
    RT_CMS_MENU;

    DT_CMS_TOP_MENU;
    DT_CMS_MENU;
    DT_NAME;
    DT_CMS_HEADER;
    DT_LANGUAGES;
    DT_CMS_PAGETYPE;
    
    
    constructor() {
    }
    
    //
    //
    //
    #initDefCodes(){
        this.RT_CMS_HOME = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'];
        this.RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'];

        this.DT_CMS_TOP_MENU = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TOP_MENU'];
        this.DT_CMS_MENU  = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_MENU'];
        this.DT_NAME       = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME']
        this.DT_CMS_HEADER = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_HEADER'];
        this.DT_LANGUAGES = window.hWin.HAPI4.sysinfo['dbconst']['DT_LANGUAGES'];
        this.DT_CMS_PAGETYPE = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGETYPE'];
    }
    
    
    // Method to exhtecute an action by id
    executeAction(actionid) {
        
        if(!this.isCmsAllowedOnThisServer()){
            return;
        }
        
        if(!this.checkRequiredRecordTypes( function(){
            this.executeAction(actionid);
        })){
            return;
        }
        
        this.cms_home_counts=null; //reset
        
        switch (actionid) {
            //action dialogs
            case "menu-cms-create":
                this.#createWebSite();
                break;
            case "menu-cms-create-page":
                this.#createPage();
                break;
            case 'menu-cms-edit-page':
            case 'menu-cms-view-page':                
                this.#selectPage(actionid,-1);
                break;
            case 'menu-cms-edit':
            case 'menu-cms-view':
                this.#selectWebSite(actionid);
                break;
            
        }
        
    }
    
    //
    //
    //
    isCmsAllowedOnThisServer(){
        
        if(window.hWin.HAPI4.sysinfo['cms_allowed']==-1){
            window.hWin.HEURIST4.msg.showMsgErr('Due to security restrictions, website creation is blocked on this server.'
            +'<br>Please contact system administrator ('+window.hWin.HAPI4.sysinfo.sysadmin_email+')' 
            +' if you wish to create a website.');
            return false;
            
        }
        return true;
    }
    
    //
    //
    //
    checkRequiredRecordTypes(callback){
        
        this.#initDefCodes();
        
        let missing = '';
        
        if(!(this.RT_CMS_HOME>0 && this.RT_CMS_MENU>0)){

            missing = 'You will need record types '
                    +'99-51 (Web home) and 99-52 (Web menu/content)';
        }else if( !(this.DT_LANGUAGES>0) || !$Db.rst(this.RT_CMS_HOME, this.DT_LANGUAGES)){
            missing = 'You will need record types '
                +'99-51 (Web home) with field 2-967 (Languages)';
        }

        if(missing!=''){
            
            window.hWin.HAPI4.SystemMgr.checkPresenceOfRectype('99-51',2,
                missing + ' which are available as part of Heurist_Core_Definitions.',
                    callback,
                    true  //force import
                );
            return false;
        }
        
        return true;    
    }
    
    //
    //
    //
    #selectPage(action, count){
        
        let that = this;
        
        if(count<0){
            this.#getCountWebPageRecords(function( count ){
                  that.#selectPage(action, count)
            });
            return;
        }
        
        if(count==0){
            this.#createPage();    
            return;
        }
        
        let is_view_mode = (action=='menu-cms-view-page');
        
        
        let query_search_pages = {t:this.RT_CMS_MENU, sort:'-id'};
        query_search_pages['f:'+this.DT_CMS_PAGETYPE] = window.hWin.HAPI4.sysinfo['dbconst']['TRM_PAGETYPE_WEBPAGE'];
        
        #.openCMSlist(window.hWin.HR('Select Web page'), query_search_pages, is_view_mode);
    }    

    //
    //
    //    
    #createPage() 
    {
        let that = this;
        let $dlg = window.hWin.HEURIST4.msg.showPrompt(
            window.hWin.HR('Name for new page')+':',
            function(value){ 

                if(window.hWin.HEURIST4.util.isempty(value)){
                    window.hWin.HEURIST4.msg.showMsgFlash('Specify name',1000);
                }else{
                    //create new web page
                    let popup_options = {record_id: -2,
                                                webpage_title: value};

                    that.#openCMSedit( popup_options );
                }
            },
            {title: window.hWin.HR('New standalone web page'), yes: window.hWin.HR('Create'), no: window.hWin.HR('Cancel')},
            {default_palette_class: 'ui-heurist-publish'});
    }
    
    
    //
    // show popup with list of web home records - on select either view or edit
    //                
    #selectWebSite(action){
        
        let that = this;
        
        if(this.cms_home_counts==null){
            this.#getCountWebSiteRecords(function(){
                  that.#selectWebSite(action);
            });
            return;
        }
        
        if(this.cms_home_counts.count==0){
            this.#createWebSite();    
            return;
        }
        
        //otherwise select
        let is_view_mode = (action=='menu-cms-view');        
        
        if(this.cms_home_counts.count==1){
              this.#openCMS(0, is_view_mode?'':'edit');
              return;
        }

        let query_search_sites = {t:this.RT_CMS_HOME, sort:'-id'};
        
        #.openCMSlist(window.hWin.HR('Select Website'), query_search_sites, is_view_mode);
        
        if(this.cms_home_counts.sMsgCmsPrivate!=''){
                //show warning
                window.hWin.HEURIST4.msg.showMsgDlg(this.cms_home_counts.sMsgCmsPrivate, null,
                                   'Non-public website records',
                                   {default_palette_class: 'ui-heurist-publish'});
            
        }
    }
    
    //
    //
    //
    #openCMSlist(sTitle, query_search, is_view_mode){
        
        let popup_options = {
                        select_mode: 'select_single', //select_multi
                        select_return_mode: 'recordset',
                        edit_mode: 'popup',
                        selectOnSave: true, //it means that select popup will be closed after add/edit is completed
                        title: sTitle,
                        fixed_search: query_search,
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
                                    
                                    that.#openCMS(rec_ID, is_view_mode?'':'edit');
                                 }
                        }
        };//popup_options
        
        window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);
    }
    
    
    //
    // create New Web Site
    //
    #createWebSite() {

        var that = this;
        
        if(this.cms_home_counts==null){
            this.#getCountWebSiteRecords(function(){
                  that.#createWebSite();
            });
            return;
        }
        
        let sMsg = '';
        
        if(this.cms_home_counts.count>0){
            
            sMsg = 'You already have '+
            ((this.cms_home_counts.count==1)?'a website'
                :(this.cms_home_counts.count+' websites'))+
            '. Are you sure you want to create an additional site?';

            if(this.cms_home_counts.sMsgCmsPrivate!=''){
                sMsg = sMsg + this.cms_home_counts.sMsgCmsPrivate;    
            }

        }else{
            sMsg = 'Are you sure you want to create a site?';

        }

        sMsg = sMsg
        + '<p>Check the box if you wish to keep your website private '
        + '<br><input type="checkbox"> hide website (can be changed later)</p>';

        let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(sMsg,
            function(){ 
                let chb = $dlg.find('input[type="checkbox"]');
                let is_private = chb.is(':checked');
                
                let popup_options = {record_id: -1, webpage_private: is_private};
                
                that.#openCMSedit(popup_options); 
            },
            window.hWin.HR('New website'),
            {default_palette_class: 'ui-heurist-publish'});
    } 
    

    //
    // VIEW CMS in production or development version
    // 
    // mode - 'edit', 'development', 'production'
    //      
    #openCMS (rec_ID, mode){
        
        if(mode=='edit'){
            this.#openCMSedit( {record_id: rec_ID} );   
            return;
        }
        
        
        let url = window.hWin.HAPI4.baseURL;
        
        if(mode=='production'){

            url = window.hWin.HAPI4.baseURL_pro;
        
        }else if(mode!='development' && window.hWin.HAPI4.baseURL!=window.hWin.HAPI4.baseURL_pro) {
            //&& window.hWin.HAPI4.installDir && !window.hWin.HAPI4.baseURL.endsWith('/heurist/')){
        
            let that = this;    
            let buttons = {};
            buttons[window.hWin.HR('Current (development) version')]  = function() {
                let $dlg = window.hWin.HEURIST4.msg.getMsgDlg(); $dlg.dialog( "close" );
                that.#openCMS(rec_ID, 'development');
            };                                 
            buttons[window.hWin.HR('Production version')]  = function() {
                let $dlg = window.hWin.HEURIST4.msg.getMsgDlg(); $dlg.dialog( "close" );
                that.#openCMS(rec_ID, 'production');
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
    
    
    //
    // Find number of RC_CMS_MENU records with type "page"
    //
    #getCountWebPageRecords(callback){
    
        let DT_CMS_PAGETYPE = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGETYPE'];
        
        let query_search_pages = {t:this.RT_CMS_MENU}
        query_search_pages['f:'+DT_CMS_PAGETYPE] = window.hWin.HAPI4.sysinfo['dbconst']['TRM_PAGETYPE_WEBPAGE'];
    
        let request = {q:query_search_pages, w: 'a', detail: 'count', source:'getCountWebPageRecords' };
        window.hWin.HAPI4.RecordMgr.search(request, function(response){
            if(response.status == window.hWin.ResponseStatus.OK){
                callback.call(this, response.data.count);
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    }
    
     //
     // finds number of cms website (RT_CMS_HOME) and how many private records among them
     //   
    #getCountWebSiteRecords(callback){
        
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
                        
                        that.cms_home_counts = {count:response.data['all'], sMsgCmsPrivate:''};
                        
                        let aPriv = response.data['private'];

                        if(aPriv.length>0){
                            let cnt_home = response.data['private_home'];
                            let cnt_menu = response.data['private_menu'];
                            let s1 = '';                          
                            if(cnt_home>0){
                                if(cnt_home==1){
                                    //let cms_home_private_records_ids = response.data['private_home_ids'][0];
                                    s1 = '<p>Note: CMS website record is non-public. This website is not visible to the public.</p>';        
                                }else{
                                    s1 = '<p>Note: There are '+cnt_home+' non-public CMS website records. These websites are not visible to the public.</p>';
                                }
                            }            
                            
                            that.cms_home_counts.sMsgCmsPrivate = 
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
                        }
                        
                        if(window.hWin.HEURIST4.util.isFunction(callback)) callback(that);
                    }
                    else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
    }    
    
    
    #openCMSedit(options){
        
        if(options.record_id<0){
            this.#createNewWebContent(options);
            return;
        }

        let sURL = window.hWin.HAPI4.baseURL+'?db='
                                        + window.hWin.HAPI4.database
                                        + '&website&id='+options.record_id+'&edit=2';
        
        if(options.newlycreated){
            sURL = sURL + '&newlycreated';
        }
        window.open(sURL, '_blank');
        
    }
    
    #createNewWebContent(options){

            let home_page_record_id = options.record_id,
                webpage_title = options.webpage_title,
                webpage_private = (options.webpage_private==true);
    
        
            //single page website/embed otherwise website with menu
            let isWebPage = (home_page_record_id==-2);
            
            //create new set of records - website template -----------------------
            window.hWin.HEURIST4.msg.bringCoverallToFront(); 
            window.hWin.HEURIST4.msg.showMsgFlash(
                (isWebPage
                    ?'Creating default layout (webpage) record'
                    :'Creating the set of website records')
                , 10000);

            let session_id = Math.round((new Date()).getTime()/1000); //for progress

            let request = { action: 'import_records',
                filename: isWebPage?'webpageStarterRecords.xml':'websiteStarterRecords.xml',
                is_cms_init: 1,
                make_public: (webpage_private===true)?0:1 ,
                //session: session_id,
                id: window.hWin.HEURIST4.util.random()
            };
            
            let that = this;

            //create default set of records for website see importController
            function __callback( response ){
                
                let $dlg = window.hWin.HEURIST4.msg.getMsgFlashDlg();
                $dlg.dialog('close');

                window.hWin.HEURIST4.msg.sendCoverallToBack();

                if(response.status == window.hWin.ResponseStatus.OK){
                    $('#spanRecCount2').text(response.data.count_imported);

                    if(isWebPage){
                        //update title of webpage
                        if(!window.hWin.HEURIST4.util.isempty(webpage_title)){

                            let page_recid = response.data.ids[0];

                            //replace name of webpage to the provided one
                            let request = {a: 'replace',
                                recIDs: page_recid,
                                dtyID: window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
                                rVal: webpage_title};
                            window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    options.record_id = page_recid;
                                    options.newlycreated = true;
                                    that.#openCMSedit( options );
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                }
                            });
                            return;
                        }
                    }else{

                        window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
                            +'hclient/widgets/cms/editCMS_NewSiteMsg.html');
                        //add blog template
                        if(response.data.page_id_for_blog>0){
                            that.#addTemplate('blog', response.data.page_id_for_blog);
                        }

                        options.record_id = response.data.home_page_id>0?response.data.home_page_id:response.data.ids[0];
                        that.#openCMSedit( options );

                    }

                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            };
            
            window.hWin.HAPI4.doImportAction(request, __callback);
    } //createNewWebContent
        
        
    //
    // replace content of page with template
    //
    #addTemplate(template_name, affected_page_id){

        // 1. load template files
        let sURL = window.hWin.HAPI4.baseURL+'hclient/widgets/cms/templates/snippets/'+template_name+'.json';

        // 2. Loads template json
        $.getJSON(sURL, 
        function( new_element_json ){
            
            if(!layoutMgr) hLayoutMgr();
            
            layoutMgr.prepareTemplate(new_element_json, function(updated_json){

                //replace content of blog webpage
                let request = {a: 'replace',
                    recIDs: affected_page_id,
                    dtyID: window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'],
                    rVal: JSON.stringify( updated_json )};
                window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                    if(response.status != window.hWin.ResponseStatus.OK){
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
                               
            }); 
        });
    }
    

}