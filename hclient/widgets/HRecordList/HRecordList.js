/**
* RecordList - listing of record from given HRecordSet
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/

/*
* HBaseWidget->HRecordList->HRecordTable, HRecordCards, HRecordMap, HRecordNetwork
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
* Plan:
* BaseList, RecordList->RecordTable, RecordCards, 
* RecordReport 
* 
*/
import './HRecordView.js';
import '../HBase/HBaseList.js';

$.widget( 'heurist.HRecordList', $.heurist.HBaseList, {

    //roles in content heurist-role-*
    // recordList-count
    // recordList-pagination
    // recordList-content
    // recordList-selection
    
    // default options
    options: {
        
/* inherited from HBaseWidget, HBaseList
        hapi: null,
        
        htmlContent: null, // custom content
        uiLibrary: null,   // 'bootstrap' or 'jqueryui'
        
        //event listeners
        onInitFinished: null
        
        entityType: 'rec', //'rec' by default

        searchDomain: null,     // reference to entity HSearchDomains
        searchInitial: null,    // initial search query
        
        recordSet:null,         // initial recordset
*/                
        resourcePath: 'hclient/widgets/HRecordList/HRecordList', //relative path+filename to resources: html, css and localization

        showCounter: true,
        pageSize: 0, //   if zero it shows all records, and no pagination, maxvalue is 1000

        supportCollection: false, // TBD
        showMediaViewer: false,   // TBD show gallery on thumbnail click - data-heurist-media
        
        //default action of record item click  ????
        selectAction: 'view', // none, select, view
        
        selectMode: 'none',   //TBD none, single, multi

        //where to show view or edit 
        viewRecordMode: 'none', // none, inline, offcanvas-*, modal-*, popup (jquery dialog), target id, event
        editRecordMode: 'none',   //TBD none, inline, offset, full, main, page, popup, event
        
        rendererCard: null,     // custom record card renderer that overrides default renderer
        rendererTable: null,
        
        templateCard: null,     // template for card renderer 
        templateTable: null,
        templateView: null,     //(if not defined it uses entity default smarty report)

    },
    
    _needLoadContent: true, //flag to avoid repeatable load of html content
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
        
        //debug
        this.options.templateView = null; 
        this.options.selectAction = 'view';
        this.options.viewRecordMode = 'inline';
        //this.options.viewRecordMode = 'modal-xl';
        //this.options.viewRecordMode = 'offcanvas-end';
        //this.options.viewRecordMode = 'popup';
        //this.options.viewRecordMode = 'modal-xl'; //modal-sm modal-lg modal-xl  modal-fullscreen-md-down  modal-fullscreen
        
        this.record_id_attr = `data-heurist-${this.options.entityType}`;
        
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

        //triggers onInitFinished and performs initial search
        this._super();
        
    },

    /* 
    * Cleanup. Removes generated elements and off event listeners
    */
    _destroy: function() {
        // remove generated elements
        this._clearMultiselect();       
        this._clearPagination();
    },
    
    /*
    * Removes all record elements
    *  overwrites parent's method
    */
    clearContent: function(){
        
        if(!this._initCompleted) return;
        
        //_off all clicks for actions per record cards
        this._off( this.div_content.find(`div[${this.record_id_attr}]`), 'click');

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
            this.renderMessage('empty recordset');
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
            this.div_content.find(`div[${this.record_id_attr}]`).each(function(ids, rdiv){
                    let rec_id = $(rdiv).attr(that.record_id_attr);
                    let idx = window.hWin.HEURIST4.util.findArrayIndex(rec_id, that.recordSetSelected);
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
        this.div_content[0].className = 'row row-cols-auto g-3';  //row-cols-1 row-cols-sm-2 row-cols-md-auto   
        this.div_content[0].style.overflowX = 'hidden';
        this.div_content[0].style.overflowY = 'auto';

        //horizontal        
        //this.div_content[0].className = 'd-flex flex-row flex-nowrap';    
        //this.div_content[0].style.overflowX = 'auto';
        //this.div_content[0].style.overflowY = 'hidden';

        //table
        
    },
    
    //
    //
    //
    _renderPage: function( pageno ){

        let html = ''; //result html for content
        
        //TBD - if pageSize>1000 - imlement implicit pagination - for visible viewport
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
            
                rec_toload.push(recID);
            }
        }
        
        if(rec_toload.length>0){
            //loads record to be rendered
            this._loadRecordsDetails( rec_toload );
        }else{
            this._renderPagination(true);    
        }
        
        this.div_content[0].innerHTML = html;
        
        this._on( this.div_content.find(`div[${this.record_id_attr}]`), {
            click: this._recordDivOnClick
        });

    },
    
    //
    // Loads record details for page
    //
    _loadRecordsDetails: function( rec_toload ){
        
        let that = this;
        let ids = rec_toload.join(',');        
            
        // template for records
        if(this.options.templateCard || this.options.templateTable){
            //loads template results
            
            let request = {q:`ids:${ids}`, 
                           db:this.HAPI.database, 
                           template:this.options.templateCard,
                           lang: this.HAPI.getLocale()
                          };
            
            const temp_ele = document.createElement('div');
            let that = this;

            $(temp_ele).load(this.HAPI.baseURL, request, function(){ 
                for (const child of temp_ele.children) {
                    //find card among stub
                    const recID = child.getAttribute(that.record_id_attr);
                    if(recID>0){
                        that._cashedItem[recID] = child.outerHTML; //keep in cache
                        let ele = that.div_content[0].querySelector(`div[${that.record_id_attr}="${recID}"]`);
                        if(ele){
                            ele.innerHTML = child.innerHTML;
                        }
                    }
                }   
            });
            this._renderPagination(true);
            
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
                    }        
                }

                this._renderPage( this._current_page );
            }

        }else{
            window.hWin.HEURIST4.msg.showMsgErr(response);
        }
        
    },

    //
    // Stub while loading the entire data
    //    
    _renderRecordStub: function(recID){

        return `<div class="col" ${this.record_id_attr}="${recID}"><div class="recordList-item shadow-sm">${recID}</div></div>`;
        
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
            
            let recTitleStripped = this.$H.htmlEscape(window.hWin.HEURIST4.util.stripTags(recTitle))+' id:'+recID;
            recTitle = this.$H.stripTags(recTitle,'u, i, b, strong, em');
            //let recTitle_strip2 = this.$H.stripTags(recTitle,'a, u, i, b, strong, em');
            let recTypeIcon = this.HAPI.iconBaseURL+recTypeID;
            let hasThumb = recThumb!=null && recThumb!='';
            
            recTypeIcon = `<div class="recordList-icon" style="background-image:url(${recTypeIcon})"></div>`;
            
            let recThumbImg = '';
            if(recThumb){
                recThumbImg = `<div class="recordList-thumb" style="background-image: url(&quot;${recThumb}&quot;);" data-id="${recID}"></div>`;
            }else{
                recThumbImg = `<div class="recordList-thumb" style="opacity:0.5;background-image: url(&quot;${this.HAPI.iconBaseURL  + recTypeID}&version=thumb&quot;);"></div>`; //this._icon_timer_suffix
            }
            
            html = `<div class="col" ${this.record_id_attr}="${recID}"><div class="recordList-item shadow-sm">${recTypeIcon} ${recThumbImg} <div class="recordList-text">${recID}: ${recTitle}</div></div></div>`;
            
        }

        this._cashedItem[recID] = html; //keep in cache
        return html;
        
/*
const interpolate = (str, obj) => {
  return str.replace(/\${([^}]+)}/g, (_, target) => {
    let keys = target.split(".");
    return keys.reduce((prev, curr) => {
      if (curr.search(/\[/g) > -1) {
        //if element/key in target array is array, get the value and return
        let m_curr = curr.replace(/\]/g, "");
        let arr = m_curr.split("[");
        return arr.reduce((pr, cu) => {
          return pr && pr[cu];
        }, prev);
      } else {
        //else it is a object, get the value and return
        return prev && prev[curr];
      }
    }, obj);
  });
};

let template = "hello ${a[0][0].b.c}";
let data = {
  a: [
    [{
      b: {
        c: "world",
        f: "greetings"
      }
    }, 2], 3
  ],
  d: 12,
  e: 14
}
console.log(interpolate(template, { ...data
}));
*/        
    },
    
    
    //
    //
    //
    _recordDivOnClick: function(event){

        if($(event.target).is('a')) return;

        let recdiv = event.target;
        
        if(!recdiv.hasAttribute(this.record_id_attr)){
            recdiv = $(recdiv).parents(`div[${this.record_id_attr}]`);
            if(recdiv.length==0){
                return;
            }
            recdiv = recdiv[0];
        }

        let selected_rec_ID = recdiv.getAttribute(this.record_id_attr);
        
        //remove hightlight and expand state for others
        //this.div_content.find('.selected').removeClass('selected');
        this.div_content[0].querySelectorAll('.selected').forEach(sub=>sub.classList.remove('selected'));
        
        let recdiv_card = recdiv.firstChild;        
        recdiv_card.classList.add('selected'); //highlight record card
        
        if(this.options.selectAction=='view' && this.options.viewRecordMode!='none')
        {
            if(this.options.viewRecordMode=='inline'){
                
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
            else if ( this.recordView==null ){
                this.recordView = $('<div>').appendTo(this.element);
            }

            if(this.recordView.HRecordView('instance')){
                this.recordView.HRecordView('show', selected_rec_ID);
            }else{
                let that = this;
                this.recordView.HRecordView({recID: selected_rec_ID,
                                            viewMode: this.options.viewRecordMode, 
                                            recordTemplate: this.options.templateView,
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
            rdiv = this.div_content[0].querySelector(`div[${this.record_id_attr}="${recID}"]`);
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
