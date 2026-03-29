/**
* @file        mediaViewer.js
* @brief       Viewer for various media types with thumbnail display and FancyBox integration.
* @fileOverview This file provides the `heurist.mediaViewer` jQuery UI widget. It is
*              designed to display various media types (images, PDFs, audio, video,
*              IIIF, 3D models). The widget can operate in several modes:
*              1. Generate thumbnails for a provided list of media files
*                 (`options.rec_Files`) and open them in a FancyBox lightbox on click.
*              2. Utilize existing thumbnail elements in the DOM (specified by
*                 `options.selector`) and attach FancyBox functionality to them.
*              3. Programmatically open media in FancyBox using the `show()` method.
*              It handles different media sources, including direct file URLs, external
*              URLs, and special Heurist internal formats like IIIF manifests or
*              tiled images. It can also display links to open media in new tabs or
*              download them.
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       6.0
*/

/**
 * @widget heurist.mediaViewer
 * @description A jQuery UI widget for displaying various media types.
 * It supports thumbnail generation, FancyBox integration for lightbox viewing,
 * and handling of diverse media sources including IIIF and 3D models.
 */
$.widget( "heurist.mediaViewer", {

    /**
     * @memberof heurist.mediaViewer
     * @instance
     * @property {Object} options - Default options for the widget.
     * @property {?Array<Object>} options.rec_Files - An array of file objects to display. Each object should
     *           typically have properties like `rec_ID`, `id` (obfuscated file ID), `mimeType`,
     *           `filename`, `title`, `external` (URL for external content), and `mode_3d_viewer`.
     *           If null, `search_initial` might be used.
     * @property {?string|Object} options.search_initial - A search query or parameters to fetch media files
     *           if `rec_Files` is not provided directly.
     * @property {?string} options.selector - A jQuery selector for existing thumbnail elements. If provided,
     *           the widget will not render its own thumbnails but will attach FancyBox behavior
     *           to the elements matching this selector.
     * @property {boolean} [options.openInPopup=true] - If true, attempts to open media like videos or PDFs
     *           in a FancyBox popup. For audio, it might still render inline.
     * @property {boolean} [options.showLink=false] - If true, displays an "OPEN IN NEW TAB" or "DOWNLOAD" link
     *           (or "open in Mirador/3D viewer") below each thumbnail.
     * @property {?string} options.baseURL - The base URL for constructing Heurist file links. If null,
     *           it attempts to use `window.hWin.HAPI4.baseURL`.
     * @property {?string} options.database - The Heurist database name. If null, it attempts to use
     *           `window.hWin.HAPI4.database`.
     */
    options: {
        rec_Files: null, //array of objects {rec_ID, (obfuscation_file_)id, mimeType, filename, extrernal}
        search_initial:null, //if rec_Files are not defined - use this search query to retrieve rec_Files
        mediaViewer_recIDs: null,
        mediaViewer_fileIDs: null,
        
        selector: null,  //if defined it does not render thumbnails, it searches for elements that will trigger fancybox
        
        openInPopup: true, //show video in popup
        showLink: false,   // show link to open full view in new tab or download
        useFancyBoxCss: true,
        
        baseURL: null,  //define when mediaViewer is run outside standard Heurist environment
        database: null,

        // slideshow options
        slideshowShow: false,           // render slideshow stack and auto-rotate on show()
        slideshowDuration: 5000,       // ms each slide visible
        showFade: 300,                 // ms fade transition
        slideshowFull: true,           // if true use full image URL (images only), else thumbnails
        slideshowCaptions: true,
        showTitle: true,
        maxHeight: null,
        maxWidth: null,
        
        slideshowStretch: 'height', // contain | cover | fill | none | width | height
        slideshowHeaderCss: null
    },

    /**
     * @memberof heurist.mediaViewer
     * @instance
     * @private
     * @description Widget constructor. Initializes `mediacontent` to the widget's element,
     * sets default `baseURL` and `database` from global Heurist context if available,
     * and calls `_refresh()` to process initial media.
     */
    _create: function() {
        this.mediacontent = this.element;
        
        // slideshow runtime state
        this._slideshowTimer = null;
        this._slideshowImgs = [];
        this._slideshowTitleEle = null;
        this._slideshowIdx = 0;
        if(window.hWin && window.hWin.HAPI4){
            if(!this.options.baseURL){
                this.options.baseURL = window.hWin.HAPI4.baseURL;
            }
            if(!this.options.database){
                this.options.database = window.hWin.HAPI4.database;
            }
        }
        
        this._refresh();
        
    }, //end _create
    
    /**
     * @memberof heurist.mediaViewer
     * @instance
     * @private
     * @description Handles setting options for the widget. It calls the superclass's
     * method and then triggers a refresh to apply any changes.
     */
    _setOptions: function() {
        this._superApply( arguments );
        this._refresh();
    },
    
    /**
     * @memberof heurist.mediaViewer
     * @instance
     * @private
     * @description Refreshes the media display. If `options.search_initial` is set,
     * it fetches media files via `RecordMgr.search_new`. Once media files are available
     * (either from search or directly from `options.rec_Files`), it calls `_initControls`
     * to render or initialize them.
     */
    _refresh: function(){
        
        if(Array.isArray(this.options.mediaViewer_recIDs) && this.options.mediaViewer_recIDs.length>0){
            this.options.search_initial = 'ids:'+this.options.mediaViewer_recIDs.join(',');
        }else if(this.options.mediaViewer_recIDs){
            this.options.search_initial = 'ids:'+this.options.mediaViewer_recIDs;
        }else if(this.options.mediaViewer_fileIDs){
            
            const _search_request = {
            a:'search', //action
            entity:'recUploadedFiles',
            request_id:window.hWin.HEURIST4.util.random(),
            details: 'mediaViewer',
            recID: this.options.mediaViewer_fileIDs,
            db: window.hWin.HAPI4.database
            }    

            window.hWin.HAPI4.EntityMgr.doRequest(_search_request, 
                (response)=>{
                    if(response.status == window.hWin.ResponseStatus.OK){
                        this.options.rec_Files = response['data'];
                        if(this.options.rec_Files && this.options.rec_Files.length>0){
                            this._initControls();    
                        }                

                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
            });      
            
            return;
        }
        
        if(this.options.search_initial)
        {
            
            let request = {
                    q: this.options.search_initial,
                    restapi: 1,
                    zip: 1,
                    extended: 3, 
                    format:'json'};
                        
            window.hWin.HAPI4.RecordMgr.search_new(request, (response)=>{
                if(window.hWin.HEURIST4.util.isJSON(response)) {
                   this.options.rec_Files = response['records'];
                   if(this.options.rec_Files && this.options.rec_Files.length>0){
                        this._initControls();    
                   }                
                   
                }else{
                   window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
            
        }else{
            this._initControls();    
        }
    },

    /**
     * @memberof heurist.mediaViewer
     * @instance
     * @private
     * @description Cleans up the widget before it's removed. Empties the media content area.
     */
    _destroy: function() {
        this._stopSlideshow();
        this.mediacontent.empty();
    },

    /**
     * @memberof heurist.mediaViewer
     * @instance
     * @private
     * @description Initializes the controls for displaying media.
     * If `options.selector` is provided, it calls `_initThumbnails` to use existing elements.
     * Otherwise, it calls `_renderThumbnails` to create new thumbnail elements.
     */
    _initControls: function(){
        
        // If slideshow is enabled, we render a slideshow stack instead of binding FancyBox to external thumbnails.
        if(this.options.slideshowShow){
            this._renderSlideshow();
            return;
        }

        if(this.options.selector){
           //thumbnails already exist
           this._initThumbnails( this.options.selector );
        }else if(this.options.useFancyBoxCss){
            this._renderThumbnailsFB();
        }else{
            this._renderThumbnails();
        }
        
    },
    
    /**
     * @memberof heurist.mediaViewer
     * @instance
     * @private
     * @description Unescapes HTML entities in a given text string.
     * @param {?string} text - The text containing HTML entities to unescape.
     * @returns {?string} The unescaped text, or null if the input was null.
     */
    _htmlUnescape:function(text) {
        if(text){
            let e = document.createElement("textarea");
            e.innerHTML = text;
            // handle case of empty input
            return e.childNodes.length === 0 ? "" : e.childNodes[0].nodeValue;    
        }else{
            return null;
        }
    },

/**
 * @memberof heurist.mediaViewer
 * @instance
 * @private
 * @description Stops the slideshow timer (if running).
 */
_stopSlideshow: function(){
    if(this._slideshowTimer){
        clearInterval(this._slideshowTimer);
        this._slideshowTimer = null;
    }
},

/**
 * @memberof heurist.mediaViewer
 * @instance
 * @private
 * @description Starts rotating slideshow images previously rendered by _renderSlideshow().
 */
_startSlideshow: function(){
    this._stopSlideshow();

    if(!this.options.slideshowShow) return;
    if(!this._slideshowImgs || this._slideshowImgs.length < 2) return;

    let that = this;
    let duration = Number(this.options.slideshowDuration) || 5000;
    let fade = Number(this.options.showFade) || 300;

    this._slideshowIdx = 0;

    this._slideshowTimer = setInterval(function(){
        let cur = that._slideshowIdx;
        let next = (cur === that._slideshowImgs.length - 1) ? 0 : (cur + 1);

        let $cur = $(that._slideshowImgs[cur]);
        let $next = $(that._slideshowImgs[next]);

        $cur.stop(true, true).fadeOut(fade, function(){
            $next.stop(true, true).fadeIn(fade);

            if(that._slideshowTitleEle && that.options.showTitle){
                that._slideshowTitleEle.html($next.attr('title') || '');
            }
        });

        that._slideshowIdx = next;
    }, duration);
},

_prepareFile: function(file){
    
    if($.isPlainObject(file)){
        //rec_ID = file.rec_ID;
        file.obf_recID = file.id ?? file.obf_recID ?? '';
    }else{
        file = {obf_recID: file[0], mimeType: file[1]};
    }

    if(!file.obf_recID){
        return file;
    }
    
    file.mimeType = file.mimeType??'';
    file.title = (this.options.slideshowCaptions || !file.title  ? file.caption : file.title) || '';
    file.filename = file.filename ?? '';
    file.external = file.external ?? '';
    file.mode_3d_viewer = file.mode_3d_viewer ?? '';
    
    let randomNumber = window.hWin.HEURIST4.util.random();

    file.fileURL = `${this.options.baseURL}?db=${this.options.database}&file=${file.obf_recID}&t=${randomNumber}`;
    file.thumbURL = `${this.options.baseURL}?db=${this.options.database}&thumb=${file.obf_recID}&t=${randomNumber}`;
    
    return file;    
},

_getSlideshowImageCss: function(){
    let mode = this.options.slideshowStretch || 'contain';

    switch(mode){
        case 'fill':      // stretch both directions
            return {
                width: '100%',
                height: '100%',
                'object-fit': 'fill'
            };

        case 'cover':     // fill container, crop if needed
            return {
                width: '100%',
                height: '100%',
                'object-fit': 'cover'
            };

        case 'none':      // original size, no stretching
            return {
                width: 'auto',
                height: 'auto',
                'max-width': '100%',
                'max-height': '100%',
                'object-fit': 'none'
            };

        case 'width':     // full container width
            return {
                width: '100%',
                height: 'auto'
            };

        case 'height':    // full container height
            return {
                width: 'auto',
                height: '100%'
            };

        case 'contain':
        default:          // fit inside container, preserve aspect ratio
            return {
                width: '100%',
                height: '100%',
                'object-fit': 'contain'
            };
    }
},

/**
 * @memberof heurist.mediaViewer
 * @instance
 * @private
 * @description Builds a slideshow "stack" container with <img> layers.
 * - For image mimeType: can use fileURL when slideshowFull=true, otherwise thumbURL.
 * - For non-image mimeType: uses thumbURL only (preview), regardless of slideshowFull.
 * This method only renders; it does NOT start the timer (show() starts it).
 * @param {string} [title] - default title if file does not have one
 */
_renderSlideshow: function(title){
    this.mediacontent.empty();
    this._stopSlideshow();
    this._slideshowImgs = [];
    this._slideshowTitleEle = null;
    this._slideshowIdx = 0;

    // If rec_Files are not provided but selector is, try to build a file list from DOM anchors.
    let files = Array.isArray(this.options.rec_Files) ? this.options.rec_Files : null;
    if(!files && this.options.selector){
        files = [];
        let anchors = this.mediacontent.find(this.options.selector);
        anchors.each(function(){
            let $a = $(this);
            let $img = $a.find('img:first');
            files.push({
                mimeType: 'image',
                title: $a.attr('title') || $img.attr('title') || '',
                fileURL: $a.attr('href'),
                thumbURL: $img.length ? $img.attr('src') : null
            });
        });
    }

    if(!files || files.length===0){
        return;
    }

    // container for stacking images
    let $container = $('<div>')
        .addClass('media-viewer-slideshow')
        .css({position:'relative', overflow:'hidden',height:'100%','text-align':'center'})
        .appendTo(this.mediacontent);

    // optional title overlay
    let headerCss = window.hWin.HEURIST4.ui.mergeCss(this.options.slideshowHeaderCss, {'background-color': 'rgba(0,0,0,0.65)', bottom: 0,
            color: '#fff', left: 0, margin:'0.2em 0em', padding: '0.75em 1em', position: 'absolute', 'z-index':9999});
    let $title = $('<h4>')
        .css(headerCss)
        .appendTo($container);

    if(!this.options.showTitle){
        $title.hide();
    }
    this._slideshowTitleEle = $title;

    // build <img> layers
    let firstTitle = '';

    for(let i=0; i<files.length; i++){
        let file = files[i];
        if(!file) continue;
        
        file = this._prepareFile(file);

        let isImage = (file.mimeType === 'image' || file.mimeType.indexOf('image')===0);

        // choose src by rules:
        // - images: full or thumb depending on slideshowFull
        // - non-images: thumb only
        let src = null;
        if(isImage){
            if(this.options.slideshowFull){
                src = file.fileURL || file.thumbURL;
            }else{
                src = file.thumbURL || file.fileURL;
            }
        }else{
            // non-image => thumbnails only (preview)
            src = file.thumbURL || null;
        }

        if(!src) continue;

        let t = file.title || title || '';
        if(i===0) firstTitle = t;

        let imgCss = this._getSlideshowImageCss();

        let $img = $('<img>')
            .css($.extend({
                display: 'block',
                margin: '0 auto'
            }, imgCss))
            .attr('title', t)
            .attr('src', src)
            .appendTo($container);

        if(this.options.maxHeight){
            $img.css('max-height', this.options.maxHeight);
        }
        if(this.options.maxWidth){
            $img.css('max-width', this.options.maxWidth);
        }

        // hide all except first
        if(this._slideshowImgs.length>0){
            $img.hide();
        }else{
            $img.show();
            if(this.options.showTitle){
                $title.html(t);
            }
        }

        this._slideshowImgs.push($img);
    }

    // If we ended up with <2 slides, no need for timer; show() will no-op.
    if(this._slideshowImgs.length>1){
        this.show();
    }
},


    /**
     * @memberof heurist.mediaViewer
     * @instance
     * @private
     * @description Renders thumbnail previews for the media files specified in `options.rec_Files`.
     * It clears existing content, then iterates through the files, creating thumbnail images
     * and associated links. If `options.showLink` is true, it also adds download/external links.
     * Finally, it calls `_initThumbnails` to attach FancyBox functionality.
     * @param {string} [title] - A default title to use for thumbnails if a file object doesn't have one.
     */
    _renderThumbnails: function(title){

        if(this.options.slideshowShow){
            this._renderSlideshow(title);
            return;
        }

        this.mediacontent.empty();
        
        const files = this.options.rec_Files || [];
        if (!files.length) return;
        
            for (let i=0; i<files.length; i++)
            { 
                    let file = this._prepareFile(this.options.rec_Files[idx]);
                    if(!file || !file.thumbURL) continue;

                    let filetitle = file.title || title || '';

                    //thumbnail preview
                    let $alink = $('<a>')
                            .attr('data-id', file.obf_recID)
                            .arrt('data-caption', filetitle)
                            .appendTo($("<div>").css({cursor:'pointer',height:'auto','display':'inline-block'})
                            .appendTo(this.mediacontent));
                        
                    $('<img>', {src: file.thumbURL, title:filetitle})
                            .css({border: '2px solid #FFF', margin:'5px', 'box-shadow': '0 2px 4px #bbb', width:'200px'})
                            .appendTo($alink);
                
                if(this.options.showLink){
                    $('<br>').appendTo(this.mediacontent);
                    let external_url = this._htmlUnescape(file.external);
                    
                    if(external_url || file.filename === '_iiif' || file.filename === '_remote')  //@todo check preferred source
                    {
                        if(!external_url) external_url = file.fileURL; 
                               
                        if(file.filename && file.filename.indexOf('_iiif')>=0){
                            external_url =  this.options.baseURL 
                                     + 'hclient/widgets/viewers/miradorViewer.php?db='
                                     +  this.options.database
                                     + '&'+file.filename.substring(1)+'='+file.obf_recID;  //either iiif or iiif_image
                                     
                            if(file.rec_ID>0){
                                external_url =  external_url + '&recID='+file.rec_ID;    
                            }
                        }                
                       
                        $('<a href="'+external_url+'" target="_blank">'
                    +'<span class="ui-icon ui-icon-mirador" style="width:12px;height:12px;margin-left:5px;font-size:1em;display:inline-block;vertical-align: middle;'
                    +'filter: invert(35%) sepia(91%) saturate(792%) hue-rotate(174deg) brightness(96%) contrast(89%);'
                    +'"></span>&nbsp;open in Mirador</a>')
                        .appendTo(this.mediacontent);
                       
                       /* 
                       $('<a>', {href:external_url, target:'_blank'})
                                .text('OPEN IN NEW TAB')
                                .addClass('external-link')
                                .appendTo(this.mediacontent);
                       */
                    }else{
                        $('<a>', {href:(file.fileURL+'&download=1'), target:'_surf'}) 
                                .text('DOWNLOAD')
                                .addClass('external-link image_tool')
                                .appendTo(this.mediacontent);
                                
                        if (file.mode_3d_viewer!=null && file.mode_3d_viewer!='') { //3d or 3dhop

                            external_url =  this.options.baseURL 
                                     + 'hclient/widgets/viewers/'+file.mode_3d_viewer+'Viewer.php?db='
                                     +  this.options.database
                                     + '&file='+file.obf_recID;
                        
                        $('<a href="'+external_url+'" target="_blank">'
                    +'<span class="ui-icon ui-icon-box" style="width:12px;height:12px;margin-left:5px;font-size:1em;display:inline-block;vertical-align: middle;'
                    +'"></span>&nbsp;open in 3D viewer</a>')
                        .appendTo(this.mediacontent);
                        
                        }
                    }
                    $('<br>').appendTo(this.mediacontent);
                }//showLink

            }//for

            this.mediacontent.show();
            
            this._initThumbnails('a[data-id]');
        
    },
    
    
    _renderThumbnailsFB: function () {

        const that = this;
        const files = this.options.rec_Files || [];
        if (!files.length) return;
        
        this.mediacontent.empty();

        // container
        const thumbsBox = $('<div>')
            .addClass('fancybox-thumbs') // fancybox-thumbs-y
            .css({position:'relative',zIndex:0,width:'auto',display:'block'})
            .appendTo(this.mediacontent);

        const ul = $('<ul>').appendTo(thumbsBox);

        this._fbThumbs = []; // store li refs for activation

        $.each(files, function (idx, file) {

            // choose thumbnail source
            file = that._prepareFile(file);
            
            let src = file.thumbURL;
            if (!src && file.mimeType && file.mimeType.indexOf('image') === 0) {
                src = file.fileURL;
            }
            if (!src) return; // skip if nothing usable
            
            const li = $('<li>')
                .attr({
                    'data-index': idx,
                    'data-id': file.rec_ID, 
                    'tabindex': 0
                })
                .addClass('fancybox-thumbs-loading')
                .css('background-image', 'url(' + src + ')')
                .appendTo(ul);

            // first active
            if (idx === 0) {
                //li.addClass('fancybox-thumbs-active');
            }

            /* click → show item
            li.on('click', function () {
                that._activateThumb(idx);
                that._showByIndex(idx);
            });
            */

            that._fbThumbs.push(li);
        });
        
        this.mediacontent.show();
        this._initThumbnails('li[data-id]');
    },
    
    _activateThumb: function (idx) {
        if (!this._fbThumbs) return;
        $.each(this._fbThumbs, function (_, li) {
            li.removeClass('fancybox-thumbs-active');
        });
        this._fbThumbs[idx].addClass('fancybox-thumbs-active');
    },    
    
    /**
     * @memberof heurist.mediaViewer
     * @instance
     * @private
     * @description Initializes FancyBox functionality for thumbnail elements matching the given selector.
     * It iterates through the matched elements, determines the media type and appropriate URL
     * (for direct viewing, IIIF, 3D, PDF, audio/video), and configures FancyBox attributes
     * (like `data-src`, `data-type`, `data-href`). It also attaches click handlers for special
     * cases like IIIF and 3D viewers that open in custom dialogs or new tabs.
     * @param {string} selector - A jQuery selector for the thumbnail elements (typically `<a>` tags)
     *                          that should trigger FancyBox or custom viewers.
     */
    _initThumbnails: function(selector){
        
        let eles = this.mediacontent.find(selector);
        let that = this;
        const files = this.options.rec_Files || [];
        if(!files.length) return;
        
        $.each(eles, function(idx, $alink){
            
            $alink = $($alink);
            let recid = $alink.attr('data-id');

            if (!recid) return;
        
            for (let i=0; i<files.length; i++)
            { 

                    let file = that._prepareFile(files[idx]);
                    if(recid==file.rec_ID || recid==file.id){ 
                        //found
                        let rec_ID = file.rec_ID,
                        obf_recID = file.id,
                        mimeType = file.mimeType ?? '',
                        filename = file.filename, //to detect _iiif or _tiled
                        filetitle = file.title,
                        external_url = that._htmlUnescape(file.external),
                        mode_3d_viewer = file.mode_3d_viewer;
                    
                        if(!mimeType) mimeType = '';

                        let fileURL = that.options.baseURL+'?db=' + that.options.database //+ (needplayer?'&player=1':'')
                                     + '&file='+obf_recID;

                        let thumbURL =  that.options.baseURL+'?db=' +  that.options.database 
                                     + '&thumb='+obf_recID
                    
                        if(filename && filename.indexOf('_iiif') === 0){ //manifest

                            let param = 'manifest';
                            if(filename == '_iiif_image'){
                                
                                if(rec_ID>0){
                                    //param = 'q'; //it adds format=iiif in miradorViewer.php
                                   
                                    //$alink.attr('data-id', obf_recID);  
                                    param = 'q=ids:'+rec_ID;
                                }else{
                                    param = 'iiif_image='+obf_recID;
                                } 
                            }else{
                                //param = 'manifest='+obf_recID;    
                                param = 'iiif='+obf_recID;    
                            }
                            
                            
                            $alink
                                .css('cursor','pointer')
                                .attr('data-id', obf_recID)                            
                                .attr('data-iiif', param);
                            
                        
                            //for link below thumb                        
                            external_url =  that.options.baseURL 
                                     + 'hclient/widgets/viewers/miradorViewer.php?db='
                                     +  that.options.database
                                     + '&' + param;
                                     
                            if(rec_ID>0){
                                external_url =  external_url + '&recID='+rec_ID;    
                            }
                                     
                            function __openMiradorViewer(e){

                                let evt = e;

                                if(evt.already_checked!==true && window.hWin && window.hWin.HAPI4 && window.hWin.HAPI4.has_access()){
                                    window.hWin.HAPI4.SystemMgr.checkPresenceOfRectype('2-101', 2,
                                        'In order to add Annotation to image you have to import "Annotation" record type',
                                        function(isCancel){
                                            if(isCancel) return;
                                            evt.already_checked = true;
                                            __openMiradorViewer(evt);
                                    });
                                    return;
                                }

                                let ele = $(e.target)
                                if(!ele.attr('data-iiif')){
                                    ele = ele.parents('[data-iiif]');
                                }
                                let param  = ele.attr('data-iiif');
                                let obf_recID = ele.attr('data-id');

                                let url =  that.options.baseURL 
                                + 'hclient/widgets/viewers/miradorViewer.php?db='
                                +  that.options.database
                                + '&' + param;

                                if(rec_ID>0){
                                    url =  url + '&recID='+rec_ID;    
                                }

                                if(window.hWin && window.hWin.HEURIST4){
                                    //borderless:true, 
                                    window.hWin.HEURIST4.msg.showDialog(url, 
                                        {dialogid:'mirador-viewer',
                                            //resizable:false, draggable: false, 
                                            //maximize:true, 
                                            default_palette_class: 'ui-heurist-explore',
                                            width:'90%',height:'95%',
                                            allowfullscreen:true,'padding-content':'0px'});   

                                    let $dlg = $(window.hWin?window.hWin.document:document).find('body #mirador-viewer');

                                    $dlg.parent().css('top','50px');
                                }else{
                                    window.open(url, '_blank');        
                                }

                            };
                            
                            //on thumbnail click
                            that._on($alink, {click:__openMiradorViewer});
                            
                            
                        }else
                        if(mode_3d_viewer!=null && mode_3d_viewer!=''){
                            
                            $alink
                                .css('cursor','pointer')
                                .attr('data-id', obf_recID);
                                
                            //on thumbnail click
                            that._on($alink, {click:function(e){
                        
                                  let ele = $(e.target)
                                  if(!ele.attr('data-id')){
                                      ele = ele.parents('[data-id]');
                                  }
                                  let obf_recID = ele.attr('data-id');
                                  
                                  let url =  that.options.baseURL 
                                     + 'hclient/widgets/viewers/'+mode_3d_viewer+'Viewer.php?db='
                                     +  that.options.database+'&file='+obf_recID;
                                    
                                  if(window.hWin && window.hWin.HEURIST4){
                                        //borderless:true, 
                                        window.hWin.HEURIST4.msg.showDialog(url, 
                                            {dialogid:'mirador-viewer',
                                             //resizable:false, draggable: false, 
                                             //maximize:true, 
                                             default_palette_class: 'ui-heurist-explore',
                                             width:'90%',height:'95%',
                                             allowfullscreen:true,'padding-content':'0px'});   
                                             
                                       
                                       
                                  }else{
                                        window.open(url, '_blank');        
                                  }
                                     
                                     
                            }});
                            
                            
                        }else
                        if(mimeType.indexOf('image')===0){
                            $alink.attr('data-href', external_url?external_url:fileURL+'&fancybox=1')
                                  .attr('data-type', 'image')
                                  .attr('data-src', external_url?external_url:fileURL+'&fancybox=1')
                                  .attr('data-myfancybox','fb-images')
                                  .css('cursor','pointer')
                                  .attr('data-thumb', thumbURL);
                            
                            if(file.caption) $alink.attr('data-caption', file.caption);
                            
                        }else
                        if(mimeType=='application/pdf' || mimeType.indexOf('audio/')===0 || mimeType.indexOf('video/')===0){

                            external_url = fileURL  + '&mode=page';
                           
                            
                            if(that.options.selector || (that.options.openInPopup && mimeType.indexOf('audio/')!==0)){
                                
                                fileURL = fileURL  + '&mode=tag&fancybox=1';
                                
                                $alink.attr('data-href','{}')
                                    .attr('data-src', fileURL)
                                    .attr('data-type', 'ajax')
                                    .attr('data-myfancybox','fb-images')
                                    .css('cursor','pointer')
                                    .attr('data-thumb', thumbURL);
                                
                                if(file.caption) $alink.attr('data-caption', file.caption);
                                
                            }else{
                                fileURL = fileURL  + '&mode=tag';
                                $alink.hide();
                                let ele = $('<div>').css({width:'90%',height:'160px'}).load( fileURL );
                                ele.insertAfter($alink);
                            }
                        }                        
                    }
                
            }//for
            
        });
        
        
        let fancy_opts = { selectorParentEl:this.mediacontent, 
                            selector : '[data-myfancybox="fb-images"]', 
                            loop:true};
        $('body').off("click.fb-start"); //was unbind
       
        
        if(window.hWin && window.hWin.HAPI4 && window.hWin.HAPI4.fancybox){ 
                window.hWin.HAPI4.fancybox( fancy_opts );
        }else if (typeof $.fn.fancybox === 'function'){ //  window.hWin.HEURIST4.util.isFunction
                $.fn.fancybox( fancy_opts );
        }
        
    },

    /**
     * @memberof heurist.mediaViewer
     * @instance
     * @description Programmatically triggers a click on the first thumbnail matching `options.selector`.
     * This is used to open the FancyBox gallery if `options.selector` is defined and thumbnails
     * are managed externally.
     */
    show: function (){

         if(this.options.slideshowShow){ this._startSlideshow(); return; }

         if(this.options.selector){
             this.mediacontent.find(this.options.selector + ':first').trigger('click');
         } else if (this.options.useFancyBoxCss){
             this.mediacontent.find('[data-myfancybox="fb-images"]:first').trigger('click');
         }else{
             this.mediacontent.find('a[data-id]:first').trigger('click');
         }

    },
    
    /**
     * @memberof heurist.mediaViewer
     * @instance
     * @description Clears all event handlers, specifically the FancyBox click handler,
     * from the media content area. This is useful when the widget is being destroyed or refreshed
     * to prevent multiple handlers from being attached.
     */
    clearAll: function (){

        if(this.options.selector){
            this.mediacontent
                    .off("click.fb-start", '[data-myfancybox="fb-images"]'); //this.options.selector
        }
    }
    


});
