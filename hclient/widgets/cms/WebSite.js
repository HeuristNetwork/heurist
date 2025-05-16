/*
* WebSite.js - stores website parameters
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Apparently need to unite CmsManager and WebSite
*/
class WebSite {

    siteId;
    pageId;
    siteMenu;
    container; 

    timeoutCount;
    currentPageRec;
    
    pageCache = {}; 
    
    currentPageStyle = null;
    
    currentLanguage = null;
    is_execute_homepage_custom_javascript = false; //flag
    
    /*
    Workflow:
    constructor -> inits header/footer and loads home page
        loadPage ->  request for page content (DT_EXTENDED_DESCRIPTION)
            #initPage -> Inits layout for given page record, keeps page tree data (for edit mode) or record cache
                onPageLoad -> waits till all widgets are inits, updates pageId and page title, executes punlisher scrtips
                    cmsEditor.onLoadPageContent (for edit mode)
    */
    

    // {siteId:siteId, pageId:pageId, siteMenu:menuContentJSON}
    constructor(_options) {
        //set global constants
        window.hWin.RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'];
        window.hWin.DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'];
        window.hWin.DT_SHORT_SUMMARY = window.hWin.HAPI4.sysinfo['dbconst']['DT_SHORT_SUMMARY'];
        window.hWin.DT_EXTENDED_DESCRIPTION = window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'];
        window.hWin.DT_CMS_SCRIPT = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_SCRIPT'];
        window.hWin.DT_CMS_CSS = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_CSS'];

        this.siteId = _options.siteId;
        this.pageId = _options.pageId;
        this.siteMenu = _options.siteMenu;
        this.container = 'main';
        
        this.pageCache = {};

        this.is_execute_homepage_custom_javascript = true; //semaphore

        if(this.pageId>0){
        
            //init widgets in header and footer
            window.hWin.HAPI4.layoutMgr.layoutInit(null, 'header'); 
            window.hWin.HAPI4.layoutMgr.layoutInit(null, 'footer'); 
            
            this.loadPage({pageId:this.pageId});
        
        }else{
            window.hWin.HAPI4.layoutMgr.layoutInit(null, '#main-content'); 
        }
    }

    /**
    * Loads given RT_CMS_MENU into container (by default main (v3) or #main-content (v2) )
    * 
    * loadPage->initPage->onPageLoad
    */
    loadPage(options){
        
        if(this.onPageBeforeLoad()){
            //window.hWin.HEURIST4.msg.showMsgErr('Page has been modified');
            return;
        }
        
        if(this.pageCache[options.pageId]){ //this page has been already loaded
            this.#initPage( this.pageCache[options.pageId] );
            return;       
        }

        
        const supp_options = options.supp_options; //additional init options for widgets
        
        let fields = ['rec_ID', DT_NAME, DT_SHORT_SUMMARY, DT_EXTENDED_DESCRIPTION, DT_CMS_CSS];
        
        if(window.hWin.HAPI4.sysinfo['custom_js_allowed']){
            fields.push(DT_CMS_SCRIPT);
        }
        
        const server_request = {
                        q: 'ids:'+options.pageId,
                        restapi: 1,
                        columns: fields,
                        zip: 1,
                        format:'json'};
                        
        let that = this;
                        
        //perform search see record_output.php       
        window.hWin.HAPI4.RecordMgr.search_new(server_request,
            function(response){
              
                if(window.hWin.HEURIST4.util.isJSON(response)) {
                    let record = response['records'];
                    if(record && record.length>0){
                        record = record[0];
                        let res = record['details'];
                        /*
                        let keys = Object.keys(res);
                        for(let idx in keys){
                            let key = keys[idx];
                            res[key] = res[key][ Object.keys(res[key])[0] ];
                        }
                        */
                        var keys = Object.keys(res);
                        for(var idx in keys){
                            var key = keys[idx];

                            if(key == DT_EXTENDED_DESCRIPTION){
                                //the size content can be big so it stores in db as 64K chunks
                                //implode all parts of page
                                res[key] = Object.values(res[key]).join('');
                            }else if(key == DT_CMS_CSS || key == DT_CMS_SCRIPT){ //for scripts and styles
                                //takes only first value
                                res[key] = res[key][ Object.keys(res[key])[0] ];
                            }
                        }
                        if(window.hWin.HEURIST4.util.isBase64(res[DT_EXTENDED_DESCRIPTION])){
                            res[DT_EXTENDED_DESCRIPTION] = new TextDecoder().decode(
                                    window.hWin.HEURIST4.util.base64ToBytes(res[DT_EXTENDED_DESCRIPTION]));
                        }
                        
                        res['rec_ID'] = record['rec_ID'];
                        
                        //reload content of page_target
                        that.#initPage( res );
                        
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: `Web Page not found (record #${options.pageId})`,
                            error_title: 'Failed to load page'
                        });
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
        

    }
    
    /*
    * Returns true if action is blocked
    */
    onPageBeforeLoad(){
        if(window.parent?.cmsEditor){
            return window.parent.cmsEditor.warningOnExit();    
        }
        return false;
    }
    
    /*
    * Inits layout for given page record, keeps page tree data (for edit mode) or record cache
    */
    #initPage( record ){
        
        //options.container
        let pageElement = $(this.container||'main');
        if(pageElement.length==0){
            pageElement = $('#main-content');
        }
        if(pageElement.length==0){
            window.hWin.HEURIST4.msg.showMsgErr('Web Page can not be loaded. Target element not found');
            return;
        }
        this.container = pageElement;
        
        const isEditMode = window.parent?.cmsEditor;

        this.currentPageRec = record;
        this.pageId = this.currentPageRec['rec_ID'];
        
        let pageContent = record[window.hWin.DT_EXTENDED_DESCRIPTION]; //window.hWin.HAPI4.getTranslation( , null );
        let supp_options = {heurist_isJsAllowed:window.hWin.HAPI4.sysinfo['custom_js_allowed'],
                              lang: this.currentLanguage};
/* TBD        
        supp_options['page'] = {title:this.#getPageRecValue(DT_NAME), description:this.#getPageRecValue(DT_SHORT_SUMMARY)};
        if(window.hWin.websiteInfo){
            supp_options['website'] = websiteInfo;
        }
*/
        const pageTreeData = window.hWin.HAPI4.layoutMgr.layoutInit( pageContent, pageElement, supp_options, isEditMode );
        
        if(isEditMode){ //keep json structure for edit mode
            record['pageTreeData'] = pageTreeData;
        }else if(!this.pageCache[this.pageId]){
            //keep cache for not edit mode
            this.pageCache[this.pageId] = record;
        }
        
        //not used
        //if (window.hWin.HEURIST4.util.isFunction(options.callback)) options.callback.call(this, res);
        
        this.timeoutCount = 0;
        this.#onPageLoad(); //to update current pageId and cmsEditor
    }

    /*
    *  Executed after layout initialization for all widgets on the page
    *  Updates pageId, page title
    *  Calls cmsEditor.onLoadPageContent
    *  Executes publisher (custom) javascripts 
    */
    #onPageLoad(){

        let that = this;
        
        //waiting till all widgets are inited
        var is_inited = window.hWin.HAPI4.layoutMgr.layoutCheckWidgets();
        if (is_inited===false) {
            this.timeoutCount++;
            if(this.timeoutCount<100){
                setTimeout(function(){ that.#onPageLoad() },500);
                return;
            }else{

            }
        }        
        
        //close main-menu if it is offcanvas
        let ele_menu = $('#main-header');
        if(ele_menu.length>0 && ele_menu.hasClass('offcanvas')){
            let bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(ele_menu);
            bsOffcanvas.hide();
        }

        if(window.parent?.cmsEditor){ //edit mode
            window.parent.cmsEditor.onLoadPageContent(this.currentPageRec);    
        }
        
        this.#assignPageTitle();
        this.#onPageLoadPublisherScripts();
       
        //TBD  this.#initLinksAndImages();
    }
    
    /*
    *
    */
    #assignPageTitle(){
        
        let pagetitle = this.#getPageRecValue(DT_NAME);
        pagetitle = window.hWin.HEURIST4.util.stripTags(pagetitle,'br,hr,p,i,b,u,em,strong,sup,sub,small,span');//<br>
        
        //TBD - change url in browser        
    }
     
    #getPageRecValue(fieldCode){
        if(this.currentPageRec && !window.hWin.HEURIST4.util.isempty(this.currentPageRec[fieldCode])){
            return window.hWin.HAPI4.getTranslation(this.currentPageRec[fieldCode], this.currentLanguage);
        }else{
            return '';
        }
    }
    
    /*
    *
    */
    #executePublisherScript(pageId, eventdata){

        let func_name = 'afterPageLoad'+pageId;
        
        if(window.hWin.HEURIST4.util.isFunction(window[func_name])){
            //script may have event listener that is triggered on page exit - disable it
            this.container.off( 'onexitpage' );
            //execute publisher's script
            try{
                window[func_name]( document, pageId, eventdata );
            }catch(e){
                console.error(e);
            }
        }
    }
    
    /*
    * 1. Adds publisher's styles
    * 2. Adds and executes publisher's javascript
    * 3. Performs search if it is specified in url_params
    */
    #onPageLoadPublisherScripts(){
        
        //remove old style and add custom style per page ===========================
        if(DT_CMS_CSS>0){
            
            //remove style for previous page
            if(this.currentPageStyle){
                document.getElementsByTagName('head')[0].removeChild(this.currentPageStyle);
                this.currentPageStyle = null;
            }

            const stylesCode = this.currentPageRec[DT_CMS_CSS];
            //custom website css from home page has been added already in WebSite.php
            if(stylesCode && this.pageId!=this.siteId)
            {
                this.currentPageStyle = document.createElement('style');
                this.currentPageStyle.type = 'text/css';
                this.currentPageStyle.innerHTML = stylesCode;
                document.getElementsByTagName('head')[0].appendChild(this.currentPageStyle);
            }
        }
    
        //--------------------------------------------
    
        let eventdata = {}; //data that are passed from one page to another - search query for example or parameters for publisher's js
        
        //pass url params to custom javascript
        let params = window.hWin.HEURIST4.util.getUrlParams(location.href);
        params['db'] = window.hWin.HAPI4.database;
        if(!eventdata) eventdata = {};
        eventdata['url_params'] = params;
        
        let func_name = 'afterPageLoad'+this.siteId;
        
        //execute custom javascript for home page =========================
        if(this.is_execute_homepage_custom_javascript){
            //script for home page has been added in WebSite.php - execute once on website load
            this.#executePublisherScript(this.siteId, eventdata);
        }
        this.is_execute_homepage_custom_javascript = false;
        
        //execute custom javascript per loaded page =========================
        if(this.siteId!=this.pageId && DT_CMS_SCRIPT>0){
            func_name = 'afterPageLoad'+this.pageId;    
            if(!window.hWin.HEURIST4.util.isFunction(window[func_name])){
                const script_code = this.currentPageRec[DT_CMS_SCRIPT];
                if(script_code && script_code !== false){ //false means it is already inited

                    //add script to page header
                    let script = document.createElement('script');
                    script.type = 'text/javascript';
                    script.text = 'function '+func_name
                    +'(document, pageid, eventdata){\n'
                    +'try{\n' + script_code + '\n}catch(e){console.error(e)}}';

                    //$('head').append(script);
                    //document.getElementsByTagName('head')[0].appendChild(script);
                    document.head.appendChild(script);
                }
            }
            
            this.#executePublisherScript(this.pageId, eventdata);
        }
        
    
    }
    
 
    /**
    * Reload page to open CMS editor
    */
    openPageEditor(options){
        
        if(!options){
            //edit=3 loads WebSiteEditor and then website in iframe 
            //edit=2 loads website in edit mode
            options = {mode:'edit', websiteid:this.siteId, pageid:this.pageId};
        }
    
        let sURL = window.hWin.HEURIST4.ui.getCmsLink(options);

        if (options.newlycreated) {
            sURL = sURL + '&newlycreated';
        }
        
        window.location.replace(sURL);
        
        //window.open(sURL);
        
    }
    
    closePageEditor(options){
        if(window.parent){
            let sURL = window.hWin.HEURIST4.ui.getCmsLink({websiteid:this.siteId, pageid:this.pageId});
            window.parent.location.replace(sURL);
        }
    }

    
    /*
    * Reloads header or footer separately with default or given template
    */ 
    reloadMargin(isHeader, template){
        
        const mtype = isHeader?'header':'footer';
        
        let request = {website:this.siteId, ver:3};
        request[mtype] = template;
      
        window.hWin.HEURIST4.util.sendRequest(window.hWin.HAPI4.baseURL,
                    request, null, function(response){
                        if(response?.message){
                            //TBD - always wrap content into header or footer
                            let new_content = $(response.message);
                            if(!new_content.is(mtype)){
                                new_content = $(`<${mtype}>`).append(new_content);
                            }
                            
                            $(mtype).replaceWith( new_content );    
                            window.hWin.HAPI4.layoutMgr.layoutInit(null, new_content); 
                        }
                    });
        
/*        
        let sURL = window.hWin.HEURIST4.ui.getCmsLink({websiteid:this.siteId, header:template});
        let new_header = $('<div>')
        new_header.load(sURL, ()=>{
            new_header = new_header.children(0);
            $('header').replaceWith( new_header );    
            window.hWin.HAPI4.layoutMgr.layoutInit(null, new_header); 
        });
*/    
        
    }
    

}