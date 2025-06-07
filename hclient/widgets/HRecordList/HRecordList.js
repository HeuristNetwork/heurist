/**
* RecordList - widget for presentation of the set of records
* 
* Content:
*     Initial content can be defined via:
* 
* - A Heurist query (as initial filter to be applied at start) 
* - Programmatically (via method setRecordSet) 
* - Smarty template output 
* - Html or csv content of widget element. 
* 
* For smarty and html cases, html elements which are considered as record cards/table rows must have an attribute  data-heurist-rec="nnn"  where nnn is the record ID.
* 
* For csv input, the value in the column H-ID is considered as the Heurist record ID.
* 
* Appearance/Presentation:
* The list can be split into pages (via a parameter in the widget properties). In any case, record cards/rows are rendered incrementally (only in visible viewport), so pagination is useful for quick navigation or for very large recordsets (> 10K entries).
* 
* The publisher of the recordset can define two kinds of messages: for the initial state and where there are no data (empty search result).
* 
* Each record card/row can be rendered with:
* 
* - Built-in renderer (function within widget) corresponding with the standard views in previous versions of Heurist
* - One of four sample built-in smarty templates 
* - The publisher’s smarty template.  
* - Programmatically it can be defined as a function in options.rendererCard or it can overwrite method _renderRecord if you use HRecordView as a template for a new widget.
* When creating a smarty template for this purpose, each record card or row (html element) must be specified with attribute  data-heurist-rec="nnn".
* 
* Record cards can be presented in four view modes: grid, horizontal, vertical list or as a table. For table mode, the publisher’s smarty template should generate <tr><td> for records. Otherwise the appearance will look like a vertical list.
* 
* Interaction with other widgets:
* If a search group property is specified, HRecordList accepts ON_REC_SEARCHSTART, ON_REC_SEARCH_FINISH, ON_REC_SELECT and triggers ON_REC_SELECT events. So it can accept search result events from HFilter or selection events from other HRecordSet widgets.
* 
* The widget has a built-in HRecordView widget. It handles view action (on record card click, or action link click). See HRecordView for details.
* 
* Record card/rows can have html elements: links or buttons (to be specified in smarty template) that can trigger an arbitrary or record-specific action. For this purpose they must have an attribute data-heurist-action.  
* For example  <a href=”#” data-heurist-action=”record-edit”>Edit</a> will open the record edit dialog.
* 
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/

/*
* HBaseWidget->HBaseList->HRecordList ( TBD HRecordCards, HRecordMap, HRecordNetwork)
*
* HBaseWidget - loads resources: html, css, localization
* HRecordList - setDomain, setRecordSet, loadRecordDetails, doSearch(?)
* 
* BaseList:
* setRecordSet
* doSearch TBD for initial search or on search domain event
* selectRecords TBD
* clearContent 
* loadRecordDetails - loads records details
* renderPage - abstract
* renderMessage - notification message (init or for empty result)
* 
* RecordList:
* pagination  _renderPagination/_clearPagination
* page renderer implementation 
* selection
* open view/edit record
* 
*/
import './HRecordView.js';
import '../HBase/HBaseList.js';
import '../HRecordList/HRecordListOpts.js';


$.widget( 'heurist.HRecordList', $.heurist.HBaseList, {

    //roles in content heurist-role-*
    // recordList-count
    // recordList-pagination
    // recordList-content
    // recordList-selection
    
    // default options
    options: {

        resourcePath: 'hclient/widgets/HRecordList/HRecordList', //relative path+filename to resources: html, css and localization
        
        /* inherited from HBaseWidget, HBaseList
        hapi: null,
        
        htmlContent: null, // custom content
        uiLibrary: null,   // 'bootstrap' or 'jqueryui'
        
        //event listeners
        onInitFinished: null
        
        entityType: 'rec', //'rec' by default

        
        recordSet:null,         // initial recordset
*/                
        searchDomain: null,     // reference to entity HSearchDomains
        searchInitial: null,    // initial search query
        
        showCounter: true,      // If `true`, displays the total count of records in the list.
        selectFirstRecord:false,
        
        pageSize: 0, //   if zero it shows all records, and no pagination, maxvalue is 1000

        supportCollection: false, // TBD
        showMediaViewer: false,   // TBD show gallery on thumbnail click - data-heurist-media
        
        //default action of record item click  ????
        selectAction: 'select', // none, select, view
        
        selectMode: 'single',   //TBD none, single, multi

        viewMode: 'grid', // grid, list (vertical list), row (horizontal list), table
        
        //where to show view or edit 
        viewRecordMode: 'popup', // none, inline, offcanvas-*, modal-*, popup (jquery dialog), target id, event
        editRecordMode: 'none',   //TBD none, inline, offset, full, main, page, popup, event
        
        rendererCard: null,     // custom record card renderer that overrides default renderer
        
        templateCard: null,     // template for card renderer 
        templateView: null,     //(if not defined it uses entity default smarty report)
        
        placeholderEmptyBlank: false,
        placeholderEmpty: null,
        placeholderEmptyDef: 'No entries match the filter criteria (entries may exist but may not have been made visible to the public or to your user profile)',
    },
    
    _needLoadContent: true,
    _needLoadCss: true,
    /* inherited
    recordSet:null,   // HRecordSet
    recordSetSelected:[], // array of selected ids
    recordSetSubset:null, // HRecordSet - filtered and sorted locally
    */ 
    
    record_id_attr: null, //name of attribute of record div that have record ID
    
    //sub-elements
    div_counter: null,
    div_pagination: null,
    div_content: null,
    
    recordView: null, //instance of HRecordView
    
    _current_page: 0,
    _cashedItem:{},
    _lastSelectedIndex: null,

    _init: function() {

        this.record_id_attr = `data-heurist-${this.options.entityType}`;
        
//console.log('DEF', $.heurist.HRecordList.prototype.options)        
//console.log('INIT', this.options);      
        
        if(this.options.pageSize>1000){
            this.options.pageSize = 1000;
        }

        this._super();
    },
    
    /*
    * Use it a) to add event listeners for subelements of this widget
    *        b) perform some default actions (intial search for example) 
    */
    _initControls:function(){

        //TBD
        // init multi-selection elements
        //init showMediaViewer
        

        //apply translation for elements
        //it uses data-heurist-role(-title) as a key

        this.div_counter = this._$('[data-heurist-role="recordList-count"]');
        if(this.div_counter.length==0) {
            this.div_counter = null;
        }
        
        this.div_pagination = this._$('[data-heurist-role="recordList-pagination"]');
        if(this.div_pagination.length==0) {
            this.options.pageSize = 0;
            this.div_pagination = null
        }
        
        this.div_content = this._$('[data-heurist-role="recordList-content"]');
        
        if(this.options.selectMode!='multi'){
            this._$('[data-heurist-role="recordList-selection"]').hide();
        }
        
        this._$('[data-heurist-role="recordList-options"]').hide();
        /* this button is for debug 
        this._on(this._$('[data-heurist-role="recordList-options"]'),
            {click: ()=>this.openOptionsEditor()});
        */
            
        // create observer to check record's cards visibility within scrollable div_content viewport
        this._createIntersectionObserver();

        //triggers onInitFinished and performs initial search
        this._super();
    },
    
    /*
    *
    */
    onCloseOptionEditor: function(newOptions){
        if(newOptions){
            
            newOptions = $.extend(this.$H.cloneJSON($.heurist.HRecordList.prototype.options), newOptions);
            
            if(this.recordView){
                this.recordView.remove();   
                this.recordView = null;
            }
            this._cashedItem = {};
            this._current_page = 0;

            this.element.HRecordList(newOptions);
        }
    },
    
    /* 
    * Cleanup. Removes generated elements and off event listeners
    */
    _destroy: function() {
        // remove generated elements
        this.clearContent();
        this._clearMultiselect();       
    },
    
    /*
    * Returns element with atribute data-heurist-rec=recID (this.record_id_attr)
    */
    getRecordCard(recID){
        
        return this.div_content[0].querySelector(`${this.options.viewMode=='table'?'tr':'div'}[${this.record_id_attr}="${recID}"]`);
    },
    
    /*
    * Returns array of elements atribute data-heurist-rec (this.record_id_attr)
    */
    getRecordCardAll(){
        let searchFor = `${this.options.viewMode=='table'?'tr':'div'}[${this.record_id_attr}]`;
        
        return this.div_content.find( searchFor );
    },
    
    
    /*
    * Removes all record elements
    *  overwrites parent's method
    */
    clearContent: function(){
        
        if(!this._initCompleted) return;
        
        //stop observing
        this.observer.disconnect();
        
        //_off all clicks for actions per record cards
        this._off( this.getRecordCardAll(), 'click');

        this.div_content[0].innerHTML = '';
        
        this._clearPagination();
    },

    /*
    * Adds notification/placeholder message (init, error or for empty result)
    * overwrites parent's method
    */
    renderMessage: function(msg){
    
        this.clearContent();
        
        let $emptyres = $('<div>')
        .css('merge','auto')
        .html(msg)
        .appendTo(this.div_content);
        
    },
    
    /*
    * overwrites parent's method
    */
    renderConent: function(){

        this._cashedItem = {}; //reset
        this._renderPagination();

        if(this.recordSet==null || this.recordSet.count_total()==0){
            //render placeholder
            if(!this.options.placeholderEmptyBlank){
                this.renderMessage(this.options.placeholderEmpty || this.options.placeholderEmptyDef);
            }
        }else{
            this._setPageStyle();
            this._renderPage(0);
        }
    },
    
    /**
    * selection - HRecordSet or array of record Ids or 'all'
    *
    * @param selection - record ids
    */
    setSelection: function(selection){
        
        this._super(selection);
        //clear selection
        this.div_content.find('.selected').removeClass('selected');
        this.div_content.find('.selected_last').removeClass('selected_last');
        this._lastSelectedIndex = null;    
        
        //highlight selection
        if( this.$H.isArrayNotEmpty(this.recordSetSelected) ){
            
            let that = this;
            this.div_content.getRecordCardAll().each(function(ids, rdiv){
                    let rec_id = $(rdiv).attr(that.record_id_attr);
                    let idx = that.$H.findArrayIndex(rec_id, that.recordSetSelected);
                    if(idx>=0){ 
                        $(rdiv).addClass('selected');
                    }
                });
                
            if(this.recordSetSelected.length==1){
                this._scrollToRecordDiv(this.recordSetSelected[0]);
            }
            
        }
        
    },

    //------------------ methods defined in HRecordList

    //
    //
    //    
    _clearPagination: function(){
        if(this.div_pagination){
            //off events for pagination buttons
            this._off( this.div_pagination.find('.page-link'), 'click' );
            
            this._off( this.div_pagination.find('select'), 'change' );
            
            //clear
            this.div_pagination.find('.pagination').empty();
        }
        this.div_counter?.text('');
    },  

    //
    // recreates pagination buttons and/or dropdown
    //  
    _renderPagination: function(refreshMenuOnly){
        
        let total_inquery = (this.recordSet!=null)?this.recordSet.count_total():0;
        
        if(this.options.showCounter){
            this.div_counter?.text('n = '+total_inquery);
        }else{
            this.div_counter?.text('');
        }

        if(this.options.pageSize==0 || total_inquery==0){
            return;
        }
        
        let pageCount = Math.ceil(total_inquery / this.options.pageSize);
        
        if(this._current_page > pageCount-1){
            this._current_page = 0;
        }

        if(pageCount<2){
            return;
        }
        
        let sel_ele = this.div_pagination.find('select');
        if(refreshMenuOnly && pageCount<10 && sel_ele.length>0){
            sel_ele.val(this._current_page);
            return;
        }
        
        let currentPage = this._current_page;
        let start = 0;
        let finish = 0;

        // KJ's patented heuristics for awesome useful page numbers
        if (pageCount > 9) {
            if (currentPage < 5) { start = 1; finish = 8; }
            else if (currentPage < pageCount-4) { start = currentPage - 2; finish = currentPage + 4; }
                else { start = pageCount - 7; finish = pageCount; }
        } else {
            start = 1; finish = pageCount;
        }
                
        let smenu = '';
        let sbuttons = '';

        if (start != 1) {    //force first page
            
            smenu += '<option value="0">1</option>'
            if(start!=2){                                                                              
                smenu += '<option disabled>...</option>';
            }
        }
                
        for (let i=start; i <= finish; ++i) {
            
            smenu += `<option value="${i-1}">${i}</option>`;
        
            if(pageCount<10){
                //visible on mid
                sbuttons += `<li class="page-item d-none d-md-block"><a class="page-link" href="#" data-heurist-pageno="${i-1}">${i}</a></li>`
            }
        }//for
                
        if (finish != pageCount) { //force last page
            if(finish!= pageCount-1){
                smenu += '<option disabled>...</option>';
            }
            smenu += `<option value="${pageCount-1}">${pageCount}</option>`;
        }
        
        if(refreshMenuOnly){
            this._off( sel_ele, 'change');
            sel_ele[0].innerHTML = smenu;
            sel_ele.val(this._current_page);
            this._on( sel_ele, {
                    change: function(event){
                        let page = Number($(event.target).val());
                        this._renderPage(page);
                    }} );
            return;            
        }

        //hidden since mid
        const hide_since_md = (pageCount<10)?' d-block d-md-none':'';

        let html = sbuttons
            +'<li class="page-item"><select class="form-select form-select-sm'
                +hide_since_md+'" tabindex="-1" aria-label="Page select" aria-disabled="disabled">'
            +smenu
            +'</select></li>';

        if(pageCount>3){
            html = '<li class="page-item"><a class="page-link" href="#" aria-label="Previous" data-heurist-pageno="prev">«</a></li>'
            + html
            +'<li class="page-item"><a class="page-link" href="#" aria-label="Next" data-heurist-pageno="next">»</a></li>';
        }
        
        //assign event listeners
        this.div_pagination.find('.pagination')[0].innerHTML = html;
        
        this._on( this.div_pagination.find('.page-link'), {
                click: function(event){
                    
                    let page = $(event.target).attr('data-heurist-pageno');
                    if(page=='prev'){
                        page = this._current_page-1;
                    }else if(page=='next'){
                        page = this._current_page+1;
                    }else{
                        page = Number(page);
                    }
                    this._renderPage(page);
                }} );
                
        sel_ele.val(this._current_page);
        
    },

    //
    // off listeners
    //    
    _clearMultiselect: function(){

    },    
    
    //
    //
    //    
    _setPageStyle: function(){
        //grid - move to renderPage
        if(this.options.viewMode=='row'){
        
            this.div_content[0].className = 'd-flex flex-row flex-nowrap';    
            this.div_content[0].style.overflowX = 'auto';
            this.div_content[0].style.overflowY = 'hidden';
            
        }else if(this.options.viewMode=='list'){

            this.div_content[0].className = 'd-flex flex-column';    
            this.div_content[0].style.overflowX = 'hidden';
            this.div_content[0].style.overflowY = 'auto';
            
        }else if(this.options.viewMode=='table'){
            
            this.div_content[0].className='';
            this.div_content[0].style.overflowX = 'auto';
            this.div_content[0].style.overflowY = 'auto';
            
        }else { //}if(this.options.viewMode=='grid'){    
            this.div_content[0].className = 'row row-cols-auto g-0';  //row-cols-1 row-cols-sm-2 row-cols-md-auto   
            this.div_content[0].style.overflowX = 'hidden';
            this.div_content[0].style.overflowY = 'auto';
        }
        
    },
    
    //
    //
    //
    _renderPage: function( pageno ){

        let html = ''; //result html for content
        
        //TBD - if pageSize>1000 - imlement implicit pagination - render for visible viewport only
        pageno = (pageno<0) ?0:pageno;

        let pagesize = this.options.pageSize;
        let idx = pageno*pagesize; //starts from
        let len = this.recordSet.count_total();
        
        if(idx>=len){
            pageno = 0;
            idx = 0;
        }
        this._current_page = pageno;

        if(pagesize>0){
            len = Math.min(len, idx+pagesize); //ends on
        }
        
        let recs = this.recordSet.getRecords();
        let rec_order = this.recordSet.getOrder();
        let rec_toload = [];
        
        for(; (idx<len); idx++) {
            const recID = rec_order[idx];
            if(!this.$H.isPositiveInt(recID)){ continue; }
            
            if(this._cashedItem[recID]){ //cached html of table row or record card

                html  += this._cashedItem[recID];
                
            }else if(recs[recID]){ //record has been loaded to client side
            
                html  += this._renderRecord(recID);
                        
            }else{ //record is not loaded yet

                html  += this._renderRecordStub(recID);
            
                //check if it is visible
                rec_toload.push(''+recID);
            }
        }
        
        if(rec_toload.length>0){
            // loads record to be rendered
            // this._loadRecordsDetails( rec_toload );
        }else{
            
        }
        this._renderPagination(true);
        
        this.observer.disconnect();
        
        if(this.options.viewMode=='table'){
            
            let tbl = $('<table class="table table-striped table-hover"></table').appendTo(this.div_content);
            tbl[0].innerHTML = html;
        }else{
            this.div_content[0].innerHTML = html;
        }
        
        
        let allCards = this.getRecordCardAll();
        
        this._on( allCards, {
            click: this._recordDivOnClick
        });
        
        if(rec_toload.length>0){
            let that = this;
            allCards.each((idx, item)=>{
                if(rec_toload.indexOf( item.getAttribute(that.record_id_attr) )>=0){
                    that.observer.observe(item);    
                }
            });
        }
        
        

    },
    
    _createIntersectionObserver: function () {

          let options = {
            root: null, //this.div_content[0],
            rootMargin: '0px',
            threshold: [0, 0.25, 0.5, 0.75, 1],
            delay: 300
          };

          let that = this;
          this.observer = new IntersectionObserver((entries, observer)=>that._handleIntersect(entries, observer), options);
    },
    
    _handleIntersect: function(entries, observer){
         
         let that = this;
         let rec_toload = [];
         entries.forEach((entry) => {
            if (entry.intersectionRatio > 0.44) {        
                rec_toload.push( entry.target.getAttribute(that.record_id_attr) );
                that.observer.unobserve( entry.target );
            }
         });
         
         if(rec_toload.length>0){
             this._loadRecordsDetails(rec_toload);
         }
                
    },
    
    //
    // Loads record details for page
    //
    _loadRecordsDetails: function( rec_toload ){
        
        let that = this;
        let ids = rec_toload.join(',');        
        
        // template for records
        if(this.options.templateCard){
            //loads template results
            let request = {q:`ids:${ids}`, 
                           db:this.HAPI.database, 
                           snippet: 1, //without header
                           template:this.options.templateCard,
                           lang: this.HAPI.getLocale()
                          };
            
            let temp_ele = document.createElement('div');
            let that = this;
                                                            
            $(temp_ele).load(this.HAPI.baseURL, request, function(){ 
                for (const child of temp_ele.children) {
                    //find card among stubs and replace 
                    const recID = child.getAttribute(that.record_id_attr);
                    if(recID>0){
                        that._cashedItem[recID] = child.outerHTML; //keep in cache
                        
                        that._replaceStubWithContent(recID);
                    }
                }
                
                temp_ele = null;//clear   
            });
            //this._renderPagination(true);
            
        }else{

            //request for records
            if(this.options.entityType=='rec'){
                
                const request = { q: `{"ids":"${ids}"}`,
                    w: 'a',
                    detail: 'header',
                    pageno: that._current_page };

                this.HAPI.RecordMgr.search(request, function(response){
                    that._onGetRecordsDetails(response, rec_toload);   
                });
                    
                
            }else{
                //TBD
                const request = {
                        'a'          : 'search',
                        'entity'     : this.options.entityType,
                        'details'    : 'list',
                        'pageno'    : that._current_page
                };
                //request[this.options.entity.keyField] = ids;
                this.HAPI.EntityMgr.doRequest(request, function(response){
                    that._onGetRecordsDetails(response, rec_toload)
                });
            }
        }
        
    },
    
    //
    //
    //
    _onGetRecordsDetails: function(response, rec_toload){
        
        if(!this.recordSet) return;
        
        if(response.status == window.hWin.ResponseStatus.OK){

            let resp = new HRecordSet( response.data );
            this.recordSet.fillHeader( resp ); //add header info to this recset

            if(this.options.pageSize==0 || response.data.pageno==this._current_page) { 

                //we can't recieve data - remove records
                let i;
                for(i in rec_toload){
                    let recID = rec_toload[i];
                    if(resp.getById(recID)==null){ //not found
                        this.recordSet.removeRecord(recID);
                    }else{
                        this._cashedItem[recID] = this._renderRecord(recID);
                        this._replaceStubWithContent(recID);
                    }        
                }

                //this._renderPage( this._current_page );
            }

        }else{
            window.hWin.HEURIST4.msg.showMsgErr(response);
        }
        
    },
    
    _replaceStubWithContent(recID){
        
        //get stub
        let ele = this.getRecordCard(recID);
        if(ele){
            //replace content
            /*
            ele.outerHTML = this._cashedItem[recID];
            this._on( $(ele), {
                click: this._recordDivOnClick
            });
            */
            ele.innerHTML = $(this._cashedItem[recID]).html();
        }
        
    },

    //
    // Stub while loading the entire data
    //    
    _renderRecordStub: function(recID){
        
        if(this.options.viewMode=='table'){
            return `<tr ${this.record_id_attr}="${recID}"><td>${recID}</td></tr>`;    
        }else{
            return `<div class="col" ${this.record_id_attr}="${recID}"><div class="recordList-item shadow-sm">${recID}</div></div>`;    
        }
        
    },
    
    //
    // General renderer for any entity type 
    //    
    _renderRecord: function(recID){

        let html = '';
        
        //call external/custom function to render
        if(this.$H.isFunction(this.options.rendererCard)){
            html = this.options.rendererCard.call(this, this.recordSet, recID);
        }else{
            //default    
            let record   = this.recordSet.getById(recID)
            let recTitle = this.recordSet.fld(record, 'rec_Title');
            let recTypeID = this.recordSet.fld(record, 'rec_RecTypeID');
            let recThumb = this.recordSet.fld(record, 'rec_ThumbnailURL');
            
            let recTitleStripped = this.$H.htmlEscape(this.$H.stripTags(recTitle))+' id:'+recID;
            recTitle = this.$H.stripTags(recTitle,'u, i, b, strong, em');
            //let recTitle_strip2 = this.$H.stripTags(recTitle,'a, u, i, b, strong, em');
            let recTypeIcon = this.HAPI.iconBaseURL+recTypeID;
            let hasThumb = recThumb!=null && recThumb!='';
            
            recTypeIcon = `<div class="recordList-icon" style="background-image:url(${recTypeIcon})"></div>`;
            
            
            if(this.options.viewMode=='table'){

                html = `<tr ${this.record_id_attr}="${recID}"><td>${recID}</td><td>${recTypeIcon}</td><td>${recTitle}</td></tr>`;
                
            }else{
            
                let recThumbImg = '';
                if(recThumb){
                    recThumbImg = `<div class="recordList-thumb" style="background-image: url(&quot;${recThumb}&quot;);" data-id="${recID}"></div>`;
                }else{
                    recThumbImg = `<div class="recordList-thumb" style="opacity:0.5;background-image: url(&quot;${this.HAPI.iconBaseURL  + recTypeID}&version=thumb&quot;);"></div>`; //this._icon_timer_suffix
                }
                html = `<div class="col" ${this.record_id_attr}="${recID}"><div class="recordList-item shadow-sm">${recTypeIcon} ${recThumbImg} <div class="recordList-text">${recID}: ${recTitle}</div></div></div>`;
                
            }
            
        }

        this._cashedItem[recID] = html; //keep in cache
        return html;
       
    },
    
    
    //
    //
    //
    _recordDivOnClick: function(event){

        if($(event.target).is('a')) return; // || $(event.target).parents('a')

        let recdiv = event.target;
        
        if(!recdiv.hasAttribute(this.record_id_attr)){
            recdiv = $(recdiv).parents(`${this.options.viewMode=='table'?'tr':'div'}[${this.record_id_attr}]`);
            if(recdiv.length==0){
                return;
            }
            recdiv = recdiv[0];
        }

        let selected_rec_ID = recdiv.getAttribute(this.record_id_attr);
        
        //remove hightlight and expand state for others
        //this.div_content.find('.selected').removeClass('selected');
        this.div_content[0].querySelectorAll('.selected').forEach(sub=>sub.classList.remove('selected'));
        
        let recdiv_card = (this.options.viewMode=='table')?recdiv:recdiv.firstChild;        
        if(recdiv_card.classList){
            recdiv_card.classList.add('selected'); //highlight record card
        }else{
            recdiv_card.className = 'selected';
        }
        
        
        if(this.options.selectAction=='view' && this.options.viewRecordMode!='none')
        {
            if(this.options.viewRecordMode=='inline'){
                
                if(this.options.viewMode=='table'){
                    
                    //recdiv_card - selected TR

                    // hide expanded TR and show usual row
                    let expanded_row = this.div_content[0].querySelector('.selected_row');
                    if(expanded_row){
                        $(expanded_row).prev().show();
                        expanded_row.remove();
                        expanded_row = null;
                    }
                    
                    //insert expanded TR                    
                    let ncount = recdiv_card.children.length;
                    this.recordView = $(`<tr class="selected_row"><td class="recordList-fullview" colspan="${ncount}"></td></tr>`).insertAfter($(recdiv_card));
                    
                    recdiv_card.style.display = 'none';
                    
                    this.recordView = this.recordView.find('.recordList-fullview');
                    
                }else{
                
                    let expanded_col = this.div_content[0].querySelector('.selected_col');
                    if(expanded_col){
                        // hide expanded column
                        expanded_col.classList.remove('selected_col');
                        let recdiv_card = expanded_col.firstChild;
                        [...recdiv_card.children].forEach((sub)=>{ 
                            sub.style.display = sub.classList.contains('recordList-fullview')?'none':'block'; 
                        });
                    }
                    
                    //expand col - record card is a parent
                    recdiv.classList.add('selected_col');
                    
                    //load content
                    let view_div = recdiv_card.querySelector('.recordList-fullview');
                    if(!view_div){
                        //create new container
                        view_div = document.createElement('div');
                        view_div.classList.add('recordList-fullview');
                        recdiv_card.append(view_div);
                    }
                    [...recdiv_card.children].forEach(function (sub) { sub.style.display = 'none'; });
                    view_div.style.display = 'block';
                    
                    this.recordView = $(view_div);
                }
                
                
                
            }
            else if ( this.recordView==null ){
                this.recordView = $('<div>').appendTo(this.element);
            }

            if(this.recordView.HRecordView('instance')){
                this.recordView.HRecordView('show', selected_rec_ID);
            }else{
                let that = this;
                this.recordView.HRecordView({recID: selected_rec_ID,
                                            viewMode: this.options.viewRecordMode, 
                                            templateView: this.options.templateView,
                                            keepInstance: true});
            }
        }
        
        
        if(this.recordSetSelected.indexOf(selected_rec_ID)<0){
            //single selection
            this.recordSetSelected = [selected_rec_ID];
        }
        
        if(!this.$H.isempty(this.options.searchDomain)){
            const request = {selection: this.recordSetSelected, 
                             search_realm: this.options.searchDomain,
                             source: this._widgetId}; //search_origin
            this.document.trigger(this.HAPI.Event.ON_REC_SELECT, request);
        }
        
    },
    
    /*
    *
    */
    _scrollToRecordDiv: function(selected, to_top_of_viewport){
        
        let rdiv = null;
        if( this.$H.isPositiveInt(selected) ){
            const recID = selected;
            rdiv = this.getRecordCard(recID);
        }else{
            rdiv = selected;
        }
        
        rdiv = $(rdiv);
        if(rdiv.length!=1 ||  this.$H.isempty(rdiv.attr(this.record_id_attr))){
            //not found
            return;
        }
        
        let spos = this.div_content.scrollTop(); //current pos
        let spos2 = rdiv.position().top; //relative position of record div
        let offh = spos2 + rdiv.height() - this.div_content.height() + 10;
       
        if(spos2 < 0 || to_top_of_viewport===true){ //above viewport
            this.div_content.scrollTop(spos+spos2);
        }else if ( offh > 0 )
        {
            let newpos = spos + offh;
            if(newpos<0) newpos = 0;
            
            this.div_content.scrollTop( newpos );
        }
        
    },
    
    
});
