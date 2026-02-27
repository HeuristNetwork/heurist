/**
 * @file CmsManager.js
 * @brief Manages CMS websites and pages, including creation, selection, and editing.
 * @fileOverview This file contains the CmsManager class, which is responsible for all CMS-related actions within the Heurist client. It handles the lifecycle of websites and standalone pages, from creation through to loading and displaying them.
 * @project     Heurist academic knowledge management system
 *
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 6.0
 */

/**
 * @class CmsManager
 * The CmsManager class is responsible for managing the CMS (Content Management System) 
 * functionalities such as selecting, viewing, and editing websites or pages.
 * It also provides methods for creating new websites or pages.
 * @property {object|null} cms_home_counts Stores counts related to CMS home pages.
 * @property {number} RT_CMS_HOME Record Type ID for CMS Home.
 * @property {number} RT_CMS_MENU Record Type ID for CMS Menu.
 * @property {number} DT_CMS_TOP_MENU Detail Type ID for CMS Top Menu.
 * @property {number} DT_CMS_MENU Detail Type ID for CMS Menu field.
 * @property {number} DT_NAME Detail Type ID for Name.
 * @property {number} DT_CMS_HEADER Detail Type ID for CMS Header.
 * @property {number} DT_LANGUAGES Detail Type ID for Languages.
 * @property {number} DT_CMS_PAGETYPE Detail Type ID for CMS Page Type.
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
    
    /**
     * Initializes the CmsManager instance.
     * CMS-specific constants are loaded later when specific methods requiring them are called.
     * @constructor
     */
    constructor() {
    }

    /**
     * Initializes CMS-specific codes from system constants. These include record types and field definitions related to CMS.
     * This method is called internally to load necessary definitions.
     * 
     * @private
     */
    #initDefCodes() {
        this.RT_CMS_HOME = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'];
        this.RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'];

        this.DT_CMS_TOP_MENU = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TOP_MENU'];
        this.DT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_MENU'];
        this.DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'];
        this.DT_CMS_HEADER = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_HEADER'];
        this.DT_LANGUAGES = window.hWin.HAPI4.sysinfo['dbconst']['DT_LANGUAGES'];
        this.DT_CMS_PAGETYPE = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGETYPE'];
    }

    /**
     * Executes a CMS action based on the given action ID.
     * Depending on the action type, it may create a website, create a page,
     * or trigger the editing/viewing of an existing page or website.
     * Also handles loading a specific webpage if `actionid` is 'data-heurist-pageid'.
     * @param {string} actionid - The ID of the action to execute (e.g., 'menu-cms-create', 'data-heurist-pageid').
     * @param {object} [options] - Optional parameters. Used when actionid is 'data-heurist-pageid'.
     * @param {string} [options.container] - The jQuery selector for the container to load the page into.
     * @param {string} [options.page_id] - The ID of the page to load.
     * @param {object} [options.supp_options] - Supplementary options passed to layoutManager.
     * @param {function} [options.callback] - Callback function after page load.
     * @returns {void}
     */
    executeAction(actionid, options) {
        if (!this.isCmsAllowedOnThisServer()) {
            return;
        }
        
        if (!this.checkRequiredRecordTypes((isCancel) => {
            if(!isCancel) this.executeAction(actionid);
        })) {
            return;
        }

        this.cms_home_counts = null; // Reset

        switch (actionid) {
            case 'menu-cms-create':
                this.#createWebSite();
                break;
            case 'menu-cms-create-page':
                this.#createPage();
                break;
            case 'menu-cms-edit-page':
            case 'menu-cms-view-page':
                //standalone page
                this.#selectPage(actionid, -1);
                break;
            case 'menu-cms-edit':
            case 'menu-cms-view':
                this.#selectWebSite(actionid);
                break;
        }
    }

    /**
     * Method: isCmsAllowedOnThisServer
     * 
     * Checks if CMS creation is allowed on the current server based on security settings.
     * 
     * @returns {boolean} Returns `true` if CMS is allowed, otherwise `false`.
     */
    isCmsAllowedOnThisServer() {
        if (window.hWin.HAPI4.sysinfo['cms_allowed'] == -1) {
            window.hWin.HEURIST4.msg.showMsgErr('Due to security restrictions, website creation is blocked on this server.' +
                '<br>Please contact system administrator (' + window.hWin.HAPI4.sysinfo.sysadmin_email + ') ' +
                'if you wish to create a website.');
            return false;
        }
        return true;
    }

    /**
     * Method: checkRequiredRecordTypes
     * 
     * Ensures that the required record types and fields for CMS are available. If not, it triggers a system check to import missing types.
     * 
     * @param {function} callback - A callback function that is executed after the check is completed.
     * @returns {boolean} Returns `true` if all required record types are present, otherwise `false`.
     */
    checkRequiredRecordTypes(callback) {
        this.#initDefCodes();
        
        let missing = '';

        if (!(this.RT_CMS_HOME > 0 && this.RT_CMS_MENU > 0)) {
            missing = 'You will need record types 99-51 (Web home) and 99-52 (Web menu/content)';
        } else if (!(this.DT_LANGUAGES > 0) || !$Db.rst(this.RT_CMS_HOME, this.DT_LANGUAGES)) {
            missing = 'You will need record types 99-51 (Web home) with field 2-967 (Languages)';
        }

        if (missing != '') {
            window.hWin.HAPI4.SystemMgr.checkPresenceOfRectype('99-51', 2,
                missing + ' which are available as part of Heurist_Core_Definitions.',
                callback,
                true  // Force import
            );
            return false;
        }

        return true;
    }

    /**
     * Private Method: #selectPage
     * 
     * Opens a dialog to select or create a CMS page.
     * 
     * @private
     * @param {string} action - The action ID.
     * @param {number} count - Number of existing CMS page records.
     */
    #selectPage(action, count) {
        let that = this;

        if (count < 0) {
            this.#getCountWebPageRecords((count) => {
                that.#selectPage(action, count);
            });
            return;
        }

        if (count == 0) {
            this.#createPage();
            return;
        }

        let is_view_mode = (action == 'menu-cms-view-page');

        let query_search_pages = { t: this.RT_CMS_MENU, sort: '-id' };
        query_search_pages['f:' + this.DT_CMS_PAGETYPE] = window.hWin.HAPI4.sysinfo['dbconst']['TRM_PAGETYPE_WEBPAGE'];

        this.#openCMSlist(window.hWin.HR('Select Web page'), query_search_pages, is_view_mode, false);
    }

    /**
     * Private Method: #createPage
     * 
     * Opens a dialog to create a new CMS page.
     * 
     * @private
     */
    #createPage() {
        let that = this;
        window.hWin.HEURIST4.msg.showPrompt(
            window.hWin.HR('Name for new page') + ':',
            function(value) {
                if (window.hWin.HEURIST4.util.isempty(value)) {
                    window.hWin.HEURIST4.msg.showMsgFlash('Specify name', 1000);
                } else {
                    let popup_options = { record_id: -2, webpage_title: value };
                    that.#openCMSedit(popup_options);
                }
            },
            { title: window.hWin.HR('New standalone web page'), yes: window.hWin.HR('Create'), no: window.hWin.HR('Cancel') },
            { default_palette_class: 'ui-heurist-publish' }
        );
    }

    /**
     * Private Method: #selectWebSite
     * 
     * Opens a dialog to select or create a CMS website.
     * 
     * @private
     * @param {string} action - The action ID (either view or edit).
     */
    #selectWebSite(action) {
        let that = this;

        if (this.cms_home_counts == null) {
            this.#getCountWebSiteRecords(() => {
                that.#selectWebSite(action);
            });
            return;
        }

        if (this.cms_home_counts.count == 0) {
            this.#createWebSite();
            return;
        }

        let is_view_mode = (action == 'menu-cms-view');

        if (false && this.cms_home_counts.count == 1) {
            this.#openCMS(0, is_view_mode ? '' : 'edit');
            return;
        }

        let query_search_sites = { t: this.RT_CMS_HOME, sort: '-id' };

        this.#openCMSlist(window.hWin.HR('Select Website'), query_search_sites, is_view_mode, (action=='menu-cms-edit'));

        if (this.cms_home_counts.sMsgCmsPrivate != '') {
            window.hWin.HEURIST4.msg.showMsgDlg(this.cms_home_counts.sMsgCmsPrivate, null,
                'Non-public website records',
                { default_palette_class: 'ui-heurist-publish' });
        }
    }

    /**
     * Private Method: #openCMSlist
     * 
     * Displays a list of CMS items (pages or websites) for the user to select.
     * 
     * @private
     * @param {string} sTitle - The title of the selection dialog.
     * @param {Object} query_search - Search parameters for filtering the CMS items.
     * @param {boolean} is_view_mode - If true, items will be opened in view mode, otherwise edit mode.
     */
    #openCMSlist(sTitle, query_search, is_view_mode, conversionAllowed) {
        let that = this;
        
        
        let layout = '<div class="ent_wrapper">'
                                +    '<div class="searchForm" style="display:none;"></div>'
//+'<div class="ent_header" style="padding:0 5px"><label>Use CMS version 3 <input type="checkbox" id="useVersion3"></label>'
//+'&nbsp;&nbsp;&nbsp;&nbsp;Websites created in previous verion can be distored '+(is_view_mode?'':'if you save in')+' in new version</div>'    
+'<div class="ent_content_full" style="top:0px">' 
                                +    '<div class="ent_content_full recordList" style="top:0"></div></div>'
                     +'</div>';
        
        let selDlg = null;

        let popup_options = {
            select_mode: 'select_single',
            select_return_mode: 'recordset',
            edit_mode: 'popup',
            selectOnSave: true,
            title: sTitle,
            fixed_search: query_search,
            layout_mode: 'listonly',
            layout: layout,
            width: 500, height: 400,
            default_palette_class: 'ui-heurist-publish',
            resultList: {
                show_toolbar: false,
                view_mode: 'icons', //'icons',
                //show_action_buttons: false,
                searchfull: function(arr_ids, pageno, callback){

                    let ids = arr_ids.join(',');
                    let request = { q: '{"ids":"'+ ids+'"}',
                        w: 'a',
                        verify_credentials: 'ok',
                        detail: window.hWin.HAPI4.sysinfo['dbconst']['DT_VERSION']??'header',
                        id: window.hWin.HEURIST4.util.random(),
                        pageno: pageno };

                    window.hWin.HAPI4.RecordMgr.search(request, callback);
                    
                    
                },
                afterPageRenderer: function(){

                    const cnt = this.element.find('.recordDiv').length;
                    
                    if(cnt==0 || !conversionAllowed){ return; }
                    
                    let recordset = this.getRecordSet();
                    
                    const btnConvert = '<div data-key="cms-convert" '
                    +'style="position:absolute;bottom:4px;right:5px;cursor:pointer;text-decoration:underline;color:blue">Convert to CMS v3</div>';
                    
                    this.element.find('.recordDiv').each(function(ids, rdiv){
                        rdiv = $(rdiv);
                        let rec_id = rdiv.attr('recid');
                        
                        let record = recordset.getRecord(rec_id);    
                        const ver = recordset.fld(record, window.hWin.HAPI4.sysinfo['dbconst']['DT_VERSION']);
                        if(ver!=3){
                            $(btnConvert).appendTo(rdiv);    
                        }  
                        const s = `<b>${rec_id}&nbsp;&bull;&nbsp;</b>`;
                        rdiv.find('.recordTitle').prepend($(s));
                    });                    
                    
                    
                    
                },
                renderer: 'use standard',
                /* XXXrenderer: function(recordset, record) {
                    
                    const ver = conversionAllowed?recordset.fld(record, window.hWin.HAPI4.sysinfo['dbconst']['DT_VERSION']):3;
                    const btnConvert = '<div data-key="cms-convert" '
                    +'style="float:right;cursor:pointer;text-decoration:underline;color:blue">Convert to CMS v3</div>';
                    
                    let recID = recordset.fld(record, 'rec_ID');
                    let rectypeID = recordset.fld('rec_RecTypeID');
                    let recTitle = recordset.fld(record, 'rec_Title');
                    let recTitle_strip_all = window.hWin.HEURIST4.util.stripTags(recTitle,'b');
                    let html_thumb = '';
                    if(recordset.fld('rec_ThumbnailURL')){
                        let thumbURL = recordset.fld('rec_ThumbnailURL');
                        html_thumb = `<div class="recTypeThumb realThumb" title="${recTitle_strip_all}" style="background-image: url(&quot;${thumbURL}&quot;);" data-id="${recID}"></div>`;   
                    }else{
                        html_thumb = '<div class="recTypeThumb rectypeThumb" title="'
                            +recTitle_strip_all+'" style="background-image: url(&quot;'
                            + window.hWin.HAPI4.iconBaseURL  + rectypeID + '&version=thumb&quot;);"></div>';
                    }
                    
                    let html = '<div class="recordDiv" id="rd' + recID + '" recid="' + recID + '" '
                             +'style="padding: 10px;font-size: 1.2em !important;">'
                             + html_thumb 
                             //+ (ver==3?'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;':'VER 2') + ' '
                             + `<b>${recID}</b>&nbsp;&bull;&nbsp;`
                             + recTitle_strip_all + (ver==3?'':btnConvert) +'</div>';
                    return html;
                },*/
                onaction: function(params){

                    if(params.action=='cms-convert'){
                        selDlg.dialog('close');
                        that.#convertToVersion3( params.recID, false );
                        return true;
                    }
                    return false;
                }
            },
            onselect: function(event, data) {
               
                if (window.hWin.HEURIST4.util.isRecordSet(data.selection)) {
                    //let version = data.useNewCmsVersion?'3':'2';
                    
                    let recordset = data.selection;
                    let rec_ID = recordset.getOrder()[0];
                    that.#openCMS(rec_ID, is_view_mode ? '' : 'edit'); //, version
                }
            }
        };

        selDlg = window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);
    }
    
    #convertToVersion3(recId, withoutConditions)
    {     
        
        //1. association validation
        if('nonmember'==window.hWin.HAPI4.sysinfo['associationMembershipStatus']){

            let $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(
                `${window.hWin.HAPI4.baseURL}?disclaimer=association_membership.html #content`,
                null, 'Heurist Network Association', 
                {enable_buttons_after:2000, closeOnEscape:false, noClose:true,
                    open: (event, ui) => { $dlg.find('#noteAboutFunction').show(); $(event.target).css({height: '44em', padding: '0em 2em'}); },
                    container: 'dlg-association-teaser'
            });

            //call logger
            let request = {
                db:  window.hWin.HAPI4.sysinfo.database_prefix + window.hWin.HAPI4.database,
                host: window.location.hostname,
                email: window.hWin.HAPI4.currentUser['ugr_eMail'],
                log: 1,
                ctx: 'cms v3 coversion'  // context
            };  

            window.hWin.HEURIST4.util.sendRequest('https://heuristref.net/heurist/admin/utilities/checkMembershipApi.php',
                request, null, ()=>{}, 'auto');
            return false;
        }

        if(withoutConditions){
            
            //create duplication for the existing website
            window.hWin.HEURIST4.msg.bringCoverallToFront();
            window.hWin.HAPI4.RecordMgr.duplicate({id: recId, 
                permissions:{owner_grps:[2], access:'hidden'}, likedRtyID:this.RT_CMS_MENU, namePrefix:'BACKUP'}, 
                response=>{
                    
                    window.hWin.HEURIST4.msg.sendCoverallToBack();
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                    
                        //update version field
                        let request = {a: 'addreplace',
                            recIDs: recId,
                            dtyID: window.hWin.HAPI4.sysinfo['dbconst']['DT_VERSION'],
                            insert_new_values: 1,
                            rVal: 3};

                        window.hWin.HAPI4.RecordMgr.batch_details(request, response=>{
                            this.#openCMS(recId, 'edit', 3);
                        });        
                    
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    } 
                });
                          
            return true;
        }
        //2. warning message
        window.hWin.HEURIST4.msg.showMsgDlg(
'<p>Conversion to CMS version 3 means that the web site will be opened and edited in a new editing environment that allows Bootstrap and other new features such as the creation of responsive websites. Conversion should not affect the appearance of the website, unless it has a heavily customized page header or footer.</p> '
+'<p>On conversion, your existing website records will be backed up with a title starting with BACKUP <date>. These backup records are owned by the database owner and marked as Hidden from all other users. If you need to restore the existing website, ask the database owner (if not yourself) to delete the new version and change the ownership and title of these backup records.<p>',
            ()=>this.#convertToVersion3(recId, true));

        return true;

    }

    /**
     * Private Method: #createWebSite
     * 
     * Opens a dialog to create a new CMS website.
     * 
     * @private
     */
    #createWebSite() {
        let that = this;

        if (this.cms_home_counts == null) {
            this.#getCountWebSiteRecords(() => {
                that.#createWebSite();
            });
            return;
        }

        let sMsg = '';

        if (this.cms_home_counts.count > 0) {
            sMsg = 'You already have ' +
                ((this.cms_home_counts.count == 1) ? 'a website' :
                    (this.cms_home_counts.count + ' websites')) +
                '. Are you sure you want to create an additional site?';

            if (this.cms_home_counts.sMsgCmsPrivate != '') {
                sMsg = sMsg + this.cms_home_counts.sMsgCmsPrivate;
            }
        } else {
            sMsg = 'Are you sure you want to create a site?';
        }

        const disabled = ('nonmember'==window.hWin.HAPI4.sysinfo['associationMembershipStatus'])?'disabled':'';
        
        sMsg = sMsg 
            + '<p>Check the box if you wish to keep your website private ' 
            + '<br><input type="checkbox"> hide website (can be changed later)</p>'
            + '<p>Choose the version for CMS  ' 
            + '<br><input name="rbVer" type="radio" checked id="rbV2"/><label for="rbV2">v 2</label>'
            + `<br><input name="rbVer" type="radio" id="rbV3" ${disabled}/><label for="rbV3">v 3 (responsive layouts, testing, contact Heurist team for access)</label>`
            +'</p>';

        let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(sMsg,
            function() {
                let chb = $dlg.find('input[type="checkbox"]');
                let is_private = chb.is(':checked');
                let version = $dlg.find('#rbV3').is(':checked')?3:2;
                 
                let popup_options = { record_id: -1, webpage_private: is_private, version:version };
                that.#openCMSedit(popup_options);
            },
            window.hWin.HR('New website'),
            { default_palette_class: 'ui-heurist-publish' }
        );
    }

    /**
     * Private Method: #openCMS
     * 
     * Opens a CMS page or website in either view or edit mode.
     * 
     * @private
     * @param {number} rec_ID - The record ID of the CMS item.
     * @param {string} mode - The mode to open the CMS in ('edit', 'development', or 'production').
     */
    #openCMS(rec_ID, mode, version) {
        if (mode == 'edit') {
            this.#openCMSedit({ record_id: rec_ID, version:version });
            return;
        }

        let url = window.hWin.HAPI4.baseURL;

        if (mode == 'production') {
            url = window.hWin.HAPI4.baseURL_pro;
        } else if (mode != 'development' && window.hWin.HAPI4.baseURL != window.hWin.HAPI4.baseURL_pro) {
            let that = this;
            let buttons = {};
            buttons[window.hWin.HR('Current (development) version')] = function() {
                let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                $dlg.dialog('close');
                that.#openCMS(rec_ID, 'development', version);
            };
            buttons[window.hWin.HR('Production version')] = function() {
                let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                $dlg.dialog('close');
                that.#openCMS(rec_ID, 'production', version);
            };

            window.hWin.HEURIST4.msg.showMsgDlg('<p>You are currently running a development version of Heurist.</p>' +
                '<p>Reply "Current (development) version" to use the development version for previewing your site, but please do not publish this URL.</p>' +
                '<p>Reply "Production version" to obtain the URL for public dissemination, which will load the production version of Heurist.</p>',
                buttons, null, { default_palette_class: 'ui-heurist-publish' });

            return;
        }
        
        url = window.hWin.HEURIST4.ui.getCmsLink({mode:mode, websiteid:rec_ID, version:version, use_redirect:false});
        window.open(url, '_blank');
    }

    /**
     * Retrieves the count of CMS page records with the type "page".
     * 
     * @private
     * @param {function(number):void} callback - A callback function that is executed after the count is retrieved.
     *                                          The count of webpage records is passed as an argument.
     */
    #getCountWebPageRecords(callback) {
        let DT_CMS_PAGETYPE = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGETYPE'];

        let query_search_pages = { t: this.RT_CMS_MENU };
        query_search_pages['f:' + DT_CMS_PAGETYPE] = window.hWin.HAPI4.sysinfo['dbconst']['TRM_PAGETYPE_WEBPAGE'];

        let request = { q: query_search_pages, w: 'a', detail: 'count', source: 'getCountWebPageRecords' };
        window.hWin.HAPI4.RecordMgr.search(request, function(response) {
            if (response.status == window.hWin.ResponseStatus.OK) {
                callback.call(this, response.data.count);
            } else {
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    }

    /**
     * Retrieves the count of CMS website (RT_CMS_HOME) records and details about private records.
     * Updates `this.cms_home_counts` with the retrieved data.
     * 
     * @private
     * @param {function(CmsManager):void} callback - A callback function that is executed after the counts are retrieved.
     *                                              The CmsManager instance (`this`) is passed as an argument.
     */
    #getCountWebSiteRecords(callback) {
        let request = {
            'a': 'counts',
            'entity': 'defRecTypes',
            'mode': 'cms_record_count',
            'ugr_ID': window.hWin.HAPI4.currentUser['ugr_ID']
        };

        let that = this;

        window.hWin.HAPI4.EntityMgr.doRequest(request, function(response) {
            if (response.status == window.hWin.ResponseStatus.OK) {
                that.cms_home_counts = { count: response.data['all'], sMsgCmsPrivate: '' };

                let aPriv = response.data['private'];

                if (aPriv.length > 0) {
                    let cnt_home = response.data['private_home'];
                    let cnt_menu = response.data['private_menu'];
                    let s1 = '';

                    if (cnt_home > 0) {
                        if (cnt_home == 1) {
                            s1 = '<p>Note: CMS website record is non-public. This website is not visible to the public.</p>';
                        } else {
                            s1 = '<p>Note: There are ' + cnt_home + ' non-public CMS website records. These websites are not visible to the public.</p>';
                        }
                    }

                    that.cms_home_counts.sMsgCmsPrivate =
                        '<div style="margin-top:10px;padding:4px">' +
                        s1 +
                        ((cnt_menu > 0) ? ('<p>Warning: There are ' + cnt_menu + ' non-public CMS menu/page records. Database login is required to see these pages in the website.') : '') +
                        '<br><br>' +
                        '<a target="_blank" href="' + window.hWin.HAPI4.baseURL +
                        '?db=' + window.hWin.HAPI4.database + '&q=ids:' + aPriv.join(',') + '">Click here</a>' +
                        ' to view these records and set their visibility ' +
                        'to Public (use Share > Ownership/Visibility)';
                }

                if (window.hWin.HEURIST4.util.isFunction(callback)) callback(that);
            } else {
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    }

    /**
     * Private Method: #openCMSedit
     * 
     * Opens a CMS page or website in edit mode.
     * 
     * @private
     * @param {Object} options - Configuration options for the CMS item being edited.
     */
    #openCMSedit(options) {
        if (options.record_id < 0) {
            this.#createNewWebContent(options);
            return;
        }
                                                    
        let sURL = window.hWin.HEURIST4.ui.getCmsLink({mode:'edit', websiteid:options.record_id, version:options.version, use_redirect:false});
        if (options.newlycreated) {
            sURL = sURL + '&newlycreated';
        }
        window.open(sURL, '_blank');
    }

    /**
     * Private Method: #createNewWebContent
     * 
     * Creates new web content (webpage or website) by importing default records.
     * 
     * @private
     * @param {Object} options - Configuration options such as page title and privacy settings.
     */
    #createNewWebContent(options) {
        let home_page_record_id = options.record_id,
            webpage_title = options.webpage_title,
            webpage_private = (options.webpage_private == true);

        let isWebPage = (home_page_record_id == -2);

        window.hWin.HEURIST4.msg.bringCoverallToFront();
        window.hWin.HEURIST4.msg.showMsgFlash(
            (isWebPage ? 'Creating default layout (webpage) record' : 'Creating the set of website records'),
            10000
        );

        let request = {
            action: 'import_records',
            filename: isWebPage ? 'webpageStarterRecords.xml' : 'websiteStarterRecords.xml',
            is_cms_init: 1,
            make_public: (webpage_private === true) ? 0 : 1,
            id: window.hWin.HEURIST4.util.random()
        };

        let that = this;

        function __callback(response) {
            let $dlg = window.hWin.HEURIST4.msg.getMsgFlashDlg();
            $dlg.dialog('close');

            window.hWin.HEURIST4.msg.sendCoverallToBack();

            if (response.status == window.hWin.ResponseStatus.OK) {
                $('#spanRecCount2').text(response.data.count_imported);

                if (isWebPage) {
                    if (!window.hWin.HEURIST4.util.isempty(webpage_title)) {
                        let page_recid = response.data.ids[0];

                        let request = {
                            a: 'replace',
                            recIDs: page_recid,
                            dtyID: window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
                            rVal: webpage_title
                        };
                        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response) {
                            if (response.status == window.hWin.ResponseStatus.OK) {
                                options.record_id = page_recid;
                                options.newlycreated = true;
                                that.#openCMSedit(options);
                            } else {
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        });
                        return;
                    }
                } else {
                    window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL + 'hclient/widgets/cms/editCMS_NewSiteMsg.html');

                    if (response.data.page_id_for_blog > 0) {
                        that.#addTemplate('blog', response.data.page_id_for_blog);
                    }

                    options.record_id = response.data.home_page_id > 0 ? response.data.home_page_id : response.data.ids[0];
                    
                    if(options.version==3){
                        that.#convertToVersion3( options.record_id, true );
                        /*
                        let request = {a: 'addreplace',
                                        recIDs: options.record_id,
                                        dtyID: window.hWin.HAPI4.sysinfo['dbconst']['DT_VERSION'],
                                        insert_new_values: 1,
                                        rVal: 3};
                        
                        window.hWin.HAPI4.RecordMgr.batch_details(request, response=>{that.#openCMSedit(options)});
                        */
                    }else{
                        that.#openCMSedit(options);    
                    }
                    
                }
            } else {
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        }

        window.hWin.HAPI4.doImportAction(request, __callback);
    }

    /**
     * Private Method: #addTemplate
     * 
     * Replaces the content of a CMS page with a predefined template.
     * 
     * @private
     * @param {string} template_name - The name of the template to apply.
     * @param {number} affected_page_id - The ID of the page to update with the template content.
     */
    #addTemplate(template_name, affected_page_id) {
        let sURL = window.hWin.HAPI4.baseURL + 'hclient/widgets/cms/templates/snippets/' + template_name + '.json';

        $.getJSON(sURL, function(new_element_json) {

            window.hWin.HAPI4.layoutMgr.prepareTemplate(new_element_json, function(updated_json) {
                let request = {
                    a: 'replace',
                    recIDs: affected_page_id,
                    dtyID: window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'],
                    rVal: JSON.stringify(updated_json)
                };
                window.hWin.HAPI4.RecordMgr.batch_details(request, function(response) {
                    if (response.status != window.hWin.ResponseStatus.OK) {
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
            });
        });
    }
    
}
