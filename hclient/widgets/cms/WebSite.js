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
    pageContent;
    container; 


    // {siteId:siteId, pageId:pageId, siteMenu:menuContentJSON, pageContent:pageContentJSON}
    constructor(_options) {

        this.siteId = _options.siteId;
        this.pageId = _options.pageId;
        this.siteMenu = _options.siteMenu;
        this.pageContent = _options.pageContent;
        this.container = 'main';

        this.initWebsite();
    }

    initWebsite(){

        //init layout - init Heurist widgets on this page
        //init layout
        //const pageTreeData = window.hWin.HAPI4.layoutMgr.layoutInit(this.pageContent, this.container, {});

        //init widgets in header
        window.hWin.HAPI4.layoutMgr.layoutInit(null, 'header'); 
        // additional options for particular widgets  {HMenu:{onActionComplete:this.onPageLoad, onBeforeAction:this.onPageBeforeLoad}});

        // init webpage editor in case edit mode
        //onPageLoad(<?php echo $this->getPageRecord()?>, pageTreeData);
        
        this.loadPage({pageId:this.pageId});
        
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
    *  Updates pageId    
    *  Calls cmsEditor.onLoadPageContent
    */
    onPageLoad(record, pageTreeData){

        this.pageId = record['rec_ID'];
        
        //close main-menu if it is offcanvas
        let ele_menu = $('#main-header');
        if(ele_menu.length>0 && ele_menu.hasClass('offcanvas')){
            let bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(ele_menu);
            bsOffcanvas.hide();
        }

        if(window.parent?.cmsEditor){ //edit mode
            if(pageTreeData){
                record['pageTreeData'] = pageTreeData;
            }
            window.parent.cmsEditor.onLoadPageContent(record);    
        }
    }
    
    /**
    * Loads given RT_CMS_MENU into container (by default main (v3) or #main-content (v2) )
    */
    loadPage(options){
        
        if(this.onPageBeforeLoad()){
            //window.hWin.HEURIST4.msg.showMsgErr('Page has been modified');
            return;
        }
        
        //options.container
        let page_target = $(this.container??'main');
        if(page_target.length==0){
            page_target = $('#main-content');
        }
        if(page_target.length==0){
            window.hWin.HEURIST4.msg.showMsgErr('Web Page can not be loaded. Target element not found');
            return;
        }
        
        const DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'];
        const DT_EXTENDED_DESCRIPTION = window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'];
        const supp_options = options.supp_options; //additiona init options for widgets
        
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
                        let content = window.hWin.HAPI4.getTranslation(res[DT_EXTENDED_DESCRIPTION], null);
                        const pageTreeData = window.hWin.HAPI4.layoutMgr.layoutInit( content, page_target, supp_options );
                        
                        res['pageTreeData'] = pageTreeData;
                        
                        //not used
                        if (window.hWin.HEURIST4.util.isFunction(options.callback)) options.callback.call(this, res);
                        
                        that.onPageLoad( res ); //to update current pageId and cmsEditor

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
                            
                            $(type).replaceWith( new_content );    
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