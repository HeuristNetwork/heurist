/**
* RecordList - listing of record from given HRecordSet
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     7.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


$.widget( 'heurist.recordList', {

    //roles in content
    // heurist-role-count
    // heurist-role-pagination
    // heurist-role-viewport
    
    // default options
    options: {
        hapi: null,
        
        path: 'hclient/widgets/recordList/',
        htmlContent: null, // custom content
        uiLibrary: null,   // 'bootstrap','jqueryui'
        
        entityType: 'rec', //'rec' by default

        showCounter: true,
        pageSize: 0, //   if zero it shows all records, and no pagination, maxvalue is 1000

        supportCollection: false, // TBD
        showMediaViewer: false,   // TBD show gallery on thumbnail click - data-heurist-media
        
        //default action of record item click  ????
        selectAction: 'none', // none, select, view
        
        selectMode: 'none',   // none, single, multi

        //where to show view or edit 
        viewMode: 'none', // none, inline, offcanvas-*, modal-*
        editMode: 'none',   // none, inline, offset, full, main, page, popup
        
        rendererCard: null,     // custom record card renderer that overrides default renderer
        rendererTable: null,
        templateCard: null,     // template for card renderer 
        templateTable: null,
        templateView: null,     //(if not defined it uses entity default template)

        searchDomain: null,     // reference to entity HSearchDomains
        
        //event listeners
        onInitFinished: null,
        
        recordSet:null,
        
    },
    
    $H: window.hWin.HEURIST4.util,
    _$: $, //shorthand for this.element.find
    
    record_id_attr: null,

    recordSet:null,   // HRecordSet
    recordSetSelected:[], // array of selected ids
    recordSetSubset:null, // HRecordSet - filtered and sorted locally
    
    //sub-elements
    div_counter: null,
    div_pagination: null,
    div_content: null,
    
    _need_load_content: true,
    _init_completed: false,

    _current_page: 0,
    _cashedItem:{},

    // the widget's constructor
    _create: function() {
        
        this._$ = selector => this.element.find(selector); //querySelector(selector); 

        // prevent double click to select text
        this.element.disableSelection();
    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
        
        //debug
        this.options.templateView = null; 
        this.options.viewMode = 'modal-xl'; //modal-sm modal-lg modal-xl  modal-fullscreen-md-down  modal-fullscreen
        
    
        let that = this;    
        
        this.record_id_attr = `data-heurist-${this.options.entityType}`;
        
        if(!this.options.hapi){
            this.options.hapi = window.hWin.HAPI4;    
        }
        if(this.options.pageSize>1000){
            this.options.pageSize = 1000;
        }
        
        //add widget classes
        let css_url = this.options.hapi.baseURL + this.options.path + 'recordList.css';
        $.getStyles(css_url);
            
        if(this.$H.isempty(this.options.htmlContent)){ 
            //load default content
            if(!this._need_load_content){
                return;
            }
            this._need_load_content = false;
            
            //this.options.htmlContent.indexOf(this.options.hapi.baseURL)===0?this.options.htmlContent:
            let url = this.options.hapi.baseURL
                        + this.options.path + 'recordList.html'
                        + '?t='+this.$H.random();
                        
            // +(this.options.hapi.getLocale()=='FRE'?'_fre':'')+'.html';                         
            
            this.element.load(url, 
            function(response, status, xhr){
                if ( status == "error" ) {
                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: response,
                        error_title: 'Failed to load HTML content',
                        status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                    });
                }else {
                    that._initControls();
                }
            });
            return;
        }else{
            //custom content
            this.element.html(this.options.htmlContent);
        }
        
        this._initControls();
    },
    
    //  
    // invoked from _init after loading of html content
    // adds event listeners 
    //
    _initControls:function(){
        
        let that = this;
        
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

        this._init_completed = true;
        //trigger event
        if (this.$H.isFunction(that.options.onInitFinished)){
            this.options.onInitFinished.call(this);
        }
        
        if(this.options.recordSet){
            this.setRecordSet(this.options.recordSet);
        }
        
    },

    /* 
    * private function 
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

        if(!this._init_completed) return;

        //show hide elements according to user status
        /* TBD
        if(this.options.hapi.has_access()){ //logged in
            $(this.div_content).find('.logged-in-only').css('visibility','visible');
        }else{
            $(this.div_content).find('.logged-in-only').css('visibility','hidden');
        }
        */
    },
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements

        this._clearMultiselect();       
        this._clearPagination();       
    },
    
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
    // Removes all record elements
    //
    clearContent: function(){
        
        if(!this._init_completed) return;
        
        //_off all clicks for actions per record cards
        this._off( this.div_content.find(`div[${this.record_id_attr}]`), 'click');

        this.div_content[0].innerHTML = '';
    },
    
    //
    // assign new recordset to this list
    //
    setRecordSet: function( recordset ){

        if(!this._init_completed) return;

        this._cashedItem = {}; //reset
        this.recordSet = recordset;
        
        this.clearContent();
        this._renderPagination();

        //grid
        this.div_content[0].className = 'row row-cols-auto g-3';  //row-cols-1 row-cols-sm-2 row-cols-md-auto   
        this.div_content[0].style.overflowX = 'hidden';
        this.div_content[0].style.overflowY = 'auto';

        //horizontal        
        //this.div_content[0].className = 'd-flex flex-row flex-nowrap';    
        //this.div_content[0].style.overflowX = 'auto';
        //this.div_content[0].style.overflowY = 'hidden';

        //table
        
        
        if(recordset==null || recordset.count_total()==0){
            //render placeholder
            this._renderMessage('empty recordset')
        }else{
            this._renderPage(0);
        }
    },

    //
    // Adds message on div_content 
    // for search start and empty result
    //
    _renderMessage: function(msg){
    
        this.clearContent();
        
        let $emptyres = $('<div>')
        .css('merge','auto')
        .html(msg)
        .appendTo(this.div_content);
        
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
            this._loadRecordData( rec_toload );
        }else{
            this._renderPagination(true);    
        }
        
        this.div_content[0].innerHTML = html;
        
        this._on( this.div_content.find(`div[${this.record_id_attr}]`), {
            click: this._recordDivOnClick
        });

    },
    
    //
    //
    //
    _loadRecordData( rec_toload ){
        
        let that = this;
        let ids = rec_toload.join(',');        
            
        // template for records
        if(this.options.templateCard || this.options.templateTable){
            //loads template results
            
            let request = {q:`ids:${ids}`, 
                           db:this.options.hapi.database, 
                           template:this.options.templateCard,
                           lang: this.options.hapi.getLocale()
                          };
            
            const temp_ele = document.createElement('div');
            let that = this;

            $(temp_ele).load(this.options.hapi.baseURL, request, function(){ 
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
                //add event listeners
                //that._on( that.div_content.find(`div[${that.record_id_attr}]`), {
                //    click: that._recordDivOnClick
                //});
                      
                    });  
            
            this._renderPagination(true);
            
        }else{

            //request for records
            if(this.options.entityType=='rec'){
                
                /*
                const request = {
                    q: 'ids:'+rec_toload.join(','),
                    restapi: 1,
                    columns: ['rec_ID', 'rec_Title'],
                    zip: 1,
                    format:'json'};
                
                //perform search see record_output.php       
                this.options.hapi.RecordMgr.search_new(server_request,
                    function(response){
                        if(that.$H.isJSON(response)) {
                            
                            that._onGetRecordData(response, rec_toload);   
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });                
                */    
                const request = { q: `{"ids":"${ids}"}`,
                    w: 'a',
                    detail: 'header',
                    pageno: that._current_page };

                this.options.hapi.RecordMgr.search(request, function(response){
                    that._onGetRecordData(response, rec_toload);   
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
                this.options.hapi.EntityMgr.doRequest(request, function(response){
                    that._onGetRecordData(response, rec_toload)
                });
            }
        }
        
    },
    
    //
    //
    //
    _onGetRecordData: function(response, rec_toload){
        
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
                
            let record   = this.recordSet.getById(recID)
            let recTitle = this.recordSet.fld(record, 'rec_Title');
            let recTypeID = this.recordSet.fld(record, 'rec_RecTypeID');
            let recThumb = this.recordSet.fld(record, 'rec_ThumbnailURL');
            
            let recTitleStripped = this.$H.htmlEscape(window.hWin.HEURIST4.util.stripTags(recTitle))+' id:'+recID;
            recTitle = this.$H.stripTags(recTitle,'u, i, b, strong, em');
            //let recTitle_strip2 = this.$H.stripTags(recTitle,'a, u, i, b, strong, em');
            let recTypeIcon = this.options.hapi.iconBaseURL+recTypeID;
            let hasThumb = recThumb!=null && recThumb!='';
            
            recTypeIcon = `<div class="recordList-icon" style="background-image:url(${recTypeIcon})"></div>`;
            
            let recThumbImg = '';
            if(recThumb){
                recThumbImg = `<div class="recordList-thumb" style="background-image: url(&quot;${recThumb}&quot;);" data-id="${recID}"></div>`;
            }else{
                recThumbImg = `<div class="recordList-thumb" style="opacity:0.5;background-image: url(&quot;${this.options.hapi.iconBaseURL  + recTypeID}&version=thumb&quot;);"></div>`; //this._icon_timer_suffix
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
        
        if(this.options.selectAction=='view'){
            if(this.options.viewMode=='inline'){
                
                let expanded_col = this.div_content[0].querySelector('.selected_col');
                if(expanded_col){
                    // hide expanded column
                    expanded_col.classList.remove('selected_col');
                    let recdiv_card = expanded_col.firstChild;
                    [...recdiv_card.children].forEach((sub)=>{ 
                        sub.style.display = sub.classList.contains('recordList-fullview')?'none':'block'; 
                    });
                }
                
                //expand col - parent of record card
                recdiv.classList.add('selected_col');
                
                //load content
                let view_div = recdiv_card.querySelector('.recordList-fullview');
                if(!view_div){
                    view_div = document.createElement('div');
                    view_div.classList.add('recordList-fullview');
                    recdiv_card.append(view_div);
                    //load content
                    this._showFullRecordInfo(view_div, selected_rec_ID);    
                }
                [...recdiv_card.children].forEach(function (sub) { sub.style.display = 'none'; });
                view_div.style.display = 'block';
                
            }else if(this.options.viewMode.indexOf('offcanvas')==0){
                
                let offcanvas = document.getElementById('recordList-offcanvas');
                if(!offcanvas.classList.contains(this.options.viewMode)){
                    offcanvas.classList.add(this.options.viewMode)
                }
                
                let bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance('#recordList-offcanvas');
                
                if(this.recordSetSelected.indexOf(selected_rec_ID)<0){
                    //let bsOffcanvas = new bootstrap.Offcanvas(offcanvas)                
                    let view_div = offcanvas.querySelector('.offcanvas-body');
                    
                    if(!this.options.templateView && !view_div.classList.contains('p-1')){
                        view_div.classList.add('p-1');
                        view_div.style.overflowY = 'hidden';
                    }
                    
                    let handles = 's';
                    if(this.options.viewMode.indexOf('-start')>0){
                        handles = 'e';
                    }else if(this.options.viewMode.indexOf('-bottom')>0){
                        handles = 'w';
                    }else if(this.options.viewMode.indexOf('-end')>0){
                        handles = 'n';
                    }
                    
                    $(offcanvas)
                      .resizable({
                        minWidth: 400,
                        handles: handles,
                      });
                    
                    this._showFullRecordInfo(view_div, selected_rec_ID);
                    
                    bsOffcanvas.show();
                }else{
                    bsOffcanvas.toggle();    
                }
                
            }else if(this.options.viewMode!='none'){
                //modal by default

                let modal = document.getElementById('recordList-modal');
                if(!modal.classList.contains(this.options.viewMode)){
                    modal.classList.add(this.options.viewMode);
                }
                
                let bsModal = bootstrap.Modal.getOrCreateInstance(modal);

                if(this.recordSetSelected.indexOf(selected_rec_ID)<0){
                    let view_div = modal.querySelector('.modal-body');
                    
                    if(!this.options.templateView && !view_div.classList.contains('p-1')){
                        view_div.classList.add('p-1');
                        view_div.style.overflowY = 'hidden';
                        
                        let content_div = modal.querySelector('.modal-content');
                        content_div.style.height = '100%';
                    }
                    
                    this._showFullRecordInfo(view_div, selected_rec_ID);
                    
                    bsModal.show();
                    
                    /* makes popup resizable
                    $(modal).find('.modal-content')
                      .resizable({
                        minWidth: 625,
                        minHeight: 175,
                        handles: 'n, e, s, w, ne, sw, se, nw',
                      })
                      .draggable({
                        handle: '.modal-header'
                      });                    
                    */
                }else{
                    bsModal.toggle();    
                }

                
            }
            
        }
        
        
        if(this.recordSetSelected.indexOf(selected_rec_ID)<0){
            //single selection
            this.recordSetSelected = [selected_rec_ID];
        }
        
    },
    
    _showFullRecordInfo: function(view_div, selected_rec_ID){
        
            let request;
            if(this.options.templateView){
            
                request = {q:`ids:${selected_rec_ID}`, 
                           db:this.options.hapi.database, 
                           //
                           template:this.options.templateView,
                           lang: this.options.hapi.getLocale()
                          };
            
                let that = this;
                $(view_div).load(this.options.hapi.baseURL, request, function(){ 
                    //TBD correct urls and activate action links
                    //console.log('>>',view_div.innerHTML);
                });
            }else{

                let frame = view_div.querySelector('iframe');
                if(!frame){
                    frame = document.createElement('iframe');
                    frame.style.width = '100%';
                    frame.style.height = '100%';
                    //view_div.style.height = '100%';
                    view_div.append(frame);
                    if(view_div.classList.contains('recordList-fullview')){ //for iframe
                        frame.addEventListener('load',(event)=>{ 
                             const h = frame.contentWindow.document.body.scrollHeight;
                             view_div.style.height = h+'px';
                        });
                    }
                }
                frame.src = this.options.hapi.baseURL+`?recID=${selected_rec_ID}&db=${this.options.hapi.database}&format=html`;
                /*
                request = {recID:selected_rec_ID, 
                           db:this.options.hapi.database, 
                           format: 'html',
                           lang: this.options.hapi.getLocale()
                          };
                */
            }
        
    },

    
});
