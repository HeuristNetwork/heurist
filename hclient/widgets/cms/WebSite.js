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
    
    current_language = null;
    is_execute_homepage_custom_javascript = false;

    // {siteId:siteId, pageId:pageId, siteMenu:menuContentJSON}
    constructor(_options) {

        this.siteId = _options.siteId;
        this.pageId = _options.pageId;
        this.siteMenu = _options.siteMenu;
        this.container = 'main';
        
        this.pageCache = {};

        this.is_execute_homepage_custom_javascript = true; //semaphore
        this.initWebsite();
    }

    initWebsite(){
        //init widgets in header and footer
        window.hWin.HAPI4.layoutMgr.layoutInit(null, 'header'); 

        this.loadPage({pageId:this.pageId});
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

        
        const supp_options = options.supp_options; //additiona init options for widgets
        
        const DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'];
        const DT_EXTENDED_DESCRIPTION = window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'];
        
        const server_request = {
                        q: 'ids:'+options.pageId,
                        restapi: 1,
                        columns: ['rec_ID', DT_NAME, DT_EXTENDED_DESCRIPTION],
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
    *
    */
    #initPage( record ){
        
        //options.container
        let pageElement = $(this.container??'main');
        if(pageElement.length==0){
            pageElement = $('#main-content');
        }
        if(pageElement.length==0){
            window.hWin.HEURIST4.msg.showMsgErr('Web Page can not be loaded. Target element not found');
            return;
        }

        const DT_EXTENDED_DESCRIPTION = window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'];
        let content = window.hWin.HAPI4.getTranslation( record[DT_EXTENDED_DESCRIPTION], null );
        const supp_options = {};
        const pageTreeData = window.hWin.HAPI4.layoutMgr.layoutInit( content, pageElement, supp_options );
        
        if(window.parent?.cmsEditor){ //keep json structure for edit mode
            record['pageTreeData'] = pageTreeData;
        }else if(!this.pageCache[this.pageId]){
            //keep cache for not edit mode
            this.pageCache[this.pageId] = record;
        }
        this.currentPageRec = record;
        
        //not used
        //if (window.hWin.HEURIST4.util.isFunction(options.callback)) options.callback.call(this, res);
        
        this.timeoutCount = 0;
        this.#onPageLoad(); //to update current pageId and cmsEditor
    }

    /*
    *  Executed after layout initialization for every page
    *  Updates pageId    
    *  Calls cmsEditor.onLoadPageContent
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
        
        this.pageId = this.currentPageRec['rec_ID'];
        
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
        
    }
    
    /*
    *
    */
    #assignPageTitle(){
        
        const DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'];
        if(this.currentPageRec && !window.hWin.HEURIST4.util.isempty(this.currentPageRec[DT_NAME])){
            let pagetitle = window.hWin.HAPI4.getTranslation(this.currentPageRec[DT_NAME], this.current_language);
            pagetitle = window.hWin.HEURIST4.util.stripTags(pagetitle,'br,hr,p,i,b,u,em,strong,sup,sub,small,span');//<br>
        }
        //TBD
        
    }
    
    /*
    *
    */
    #onPageLoadPublisherScripts(){
    
        let eventdata = {}; //TBD see old websiteScriptAndStyles.php
        
        //pass url params to custom javascript
        let params = window.hWin.HEURIST4.util.getUrlParams(location.href);
        params['db'] = window.hWin.HAPI4.database;
        if(!eventdata) eventdata = {};
        eventdata['url_params'] = params;
        
        //execute custom javascript for home page =========================
        if(this.is_execute_homepage_custom_javascript){
            var func_name = 'afterPageLoad'+this.siteId;
            if(window.hWin.HEURIST4.util.isFunction(window[func_name])){
                //script may have event listener that is triggered on page exit
                //disable it
                $( "#main-content" ).off( "onexitpage");
                //execute the script
                try{
                    window[func_name]( document, this.siteId, eventdata );
                }catch(e){
                    console.error(e);
                }
            }
        }
        this.is_execute_homepage_custom_javascript = false;
    
    }
    
 
    /**
    * Reload page to open CMS editor
    */
    editPage(options){
        
        if(!options){
            //edit=3 loads WebSiteEditor and then website in iframe 
            //edit=2 loads website in edit mode
            options = {mode:'edit', websiteid:this.siteId, pageid:this.pageId};
        }
    
        let sURL = window.hWin.HEURIST4.ui.getCmsLink(options);

        if (options.newlycreated) {
            sURL = sURL + '&newlycreated';
        }
        window.open(sURL);
        
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