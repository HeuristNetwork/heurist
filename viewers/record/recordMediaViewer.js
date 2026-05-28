/**
 * recordMediaViewer.js - record viewer media behaviour for renderRecordData.php.
 *
 * This file intentionally wraps, rather than changes, the generic mediaViewer widget.
 * 
 * @project     Heurist academic knowledge management system
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @since       7.0
 */
(function(window, $){
    'use strict';

    const api = {
        options: {},

        init: function(options){
            this.options = $.extend({
                baseURL: window.baseURL || '',
                database: window.database || '',
                language: window.language || 'eng'
            }, options || {});

            $('.record-media[data-record-media]').each((idx, container) => this.bind(container));
        },

        bind: function(container){
            const $container = $(container);
            const files = this.readMediaFiles($container);

            this.bindFullScreen($container, files);
            this.bindMirador($container);
            this.bind3dViewer($container);
            this.bindPopup($container, files);
            this.bindRefreshThumb($container);
            this.bindVisibilityControls($container);
            this.restoreLinkedMediaState($container);
            this.mediaTooltips($container);
            
        },

        readMediaFiles: function($container){
            const json = $container.find('script.record-media-files-json').first().html() || '[]';
            try{
                const files = JSON.parse(json);
                return Array.isArray(files) ? files : [];
            }catch(e){
                console.error('Cannot parse record media files JSON', e);
                return [];
            }
        },

        bindFullScreen: function($container, files){
            if(files.length === 0 || typeof $.fn.mediaViewer !== 'function'){
                return;
            }

            if(!$container.mediaViewer('instance')){
                $container.mediaViewer({
                    selector: '.mediaViewer_link',
                    rec_Files: files,
                    showLink: false,
                    database: this.options.database,
                    baseURL: this.options.baseURL
                });
            }
        },

        bindMirador: function($container){
            $container.find('.record-media-mirador').off('click.recordMedia').on('click.recordMedia', (event) => {
                event.preventDefault();

                const url = $(event.currentTarget).attr('href');
                if(!url){
                    return false;
                }

                if(window.hWin?.HAPI4?.has_access && window.hWin.HAPI4.has_access()){
                    const evt = event;
                    if(evt.already_checked !== true && window.hWin.HAPI4.SystemMgr?.checkPresenceOfRectype){
                        window.hWin.HAPI4.SystemMgr.checkPresenceOfRectype('2-101', 2,
                            'In order to add Annotation to image you have to import "Annotation" record type',
                            function(){
                                evt.already_checked = true;
                                api.openMirador(url);
                            });
                        return false;
                    }
                }

                this.openMirador(url);
                return false;
            });
        },

        openMirador: function(url){
            if(window.hWin?.HEURIST4?.msg?.showDialog){
                const $dlg = window.hWin.HEURIST4.msg.showDialog(url, {
                    dialogid: 'mirador-viewer',
                    default_palette_class: 'ui-heurist-explore',
                    width: '90%',
                    height: '95%',
                    allowfullscreen: true,
                    'padding-content': '0px'
                });

                try{
                    $dlg.parent().css('top', '50px');
                }catch(e){
                    // Non-critical: dialog implementation can differ outside the admin UI.
                }
            }else{
                window.open(url, '_blank');
            }
        },
        
        bind3dViewer: function($container){
            $container.find('.record-media-3d-viewer')
                .off('click.recordMedia')
                .on('click.recordMedia', (event) => {
                    event.preventDefault();

                    const url = $(event.currentTarget).attr('href');
                    if(!url){
                        return false;
                    }

                    this.open3dViewer(url);
                    return false;
                });
        },

        open3dViewer: function(url){
            if(window.hWin?.HEURIST4?.msg?.showDialog){
                window.hWin.HEURIST4.msg.showDialog(url, {
                    dialogid: 'record-3d-viewer',
                    default_palette_class: 'ui-heurist-explore',
                    width: '90%',
                    height: '95%',
                    allowfullscreen: true,
                    'padding-content': '0px'
                });
            }else{
                window.open(url, '_blank');
            }
        },        
        
        bindPopup: function($container, files){
            $container.find('.popupMedia_link').off('click.recordMedia').on('click.recordMedia', (event) => {
                event.preventDefault();

                const fileNonce = $(event.currentTarget).attr('data-id');
                const file = files.find((item) => item.id === fileNonce) || {};
                const fileUrl = `${this.options.baseURL}?db=${this.options.database}&file=${fileNonce}`;

                if(window.hWin?.HEURIST4?.msg?.showMsgDlg){
                    let fileDesc = $(event.currentTarget).closest('.download_link').find('span.media-desc').attr('title') || '';
                    fileDesc = fileDesc.replace(/"/g, '&quot;').replace(/'/g, '&apos;');

                    const msg = `<img src='${fileUrl}' alt='${fileDesc}' style='height:99%;width:99%;object-fit:contain' />`;
                    const $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                        msg,
                        null,
                        {title: file.filename || ''},
                        {default_palette_class: 'ui-heurist-explore', resizable: true, width: 'auto', height: 'auto'}
                    );
                    $dlg.css('max-width', 'none');
                }else{
                    window.open(fileUrl, '_blank');
                }

                return false;
            });
        },

        bindRefreshThumb: function($container){
            let refreshing = false;

            $container.find('.refreshThumb_link').off('click.recordMedia').on('click.recordMedia', (event) => {
                event.preventDefault();

                if(refreshing){
                    window.hWin?.HEURIST4?.msg?.showMsgErr?.('A thumbnail is already being refreshed, please wait for it to complete before refreshing another thumbnail.');
                    return false;
                }

                const ulfObfuscatedFileID = $(event.currentTarget).attr('data-id');
                const $thumb = $(event.currentTarget).closest('.media-content').find('img').first();

                if(!ulfObfuscatedFileID || $thumb.length === 0 || !window.hWin?.HEURIST4?.util?.sendRequest){
                    return false;
                }

                refreshing = true;
                window.hWin.HEURIST4.msg.showMsgFlash('Refreshing thumbnail...', 2500);

                window.hWin.HEURIST4.util.sendRequest(`${this.options.baseURL}hserv/controller/fileDownload.php`, {
                    db: this.options.database,
                    thumb: ulfObfuscatedFileID,
                    refresh: 1
                }, null, (response) => {
                    refreshing = false;

                    if(response.message?.startsWith('Error_')){
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                        return;
                    }

                    window.hWin.HEURIST4.msg.showMsgFlash('Thumbnail has been refreshed', 3000);
                    const random = window.hWin.HEURIST4.util.random ? window.hWin.HEURIST4.util.random() : Date.now();
                    $thumb.attr('src', `${this.options.baseURL}?db=${this.options.database}&offer_download=1&thumb=${ulfObfuscatedFileID}&${random}`);
                    window.hWin.HAPI4?.triggerEvent?.(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, {type: 'ulf'});
                });

                return false;
            });
        },

        bindVisibilityControls: function($container){
            $container.find('.record-media-toggle-images').off('click.recordMedia').on('click.recordMedia', (event) => {
                event.preventDefault();
                const hidden = $(event.currentTarget).attr('data-state') === 'hidden';
                this.setImagesVisible($container, hidden);
                return false;
            });

            $container.find('.show-linked-media').off('change.recordMedia').on('change.recordMedia', (event) => {
                const showLinked = $(event.currentTarget).is(':checked');
                this.setLinkedVisible($container, showLinked);
                sessionStorage.setItem('Heurist_RecView_LinkedMedia', showLinked ? '0' : '1');
            });
        },

        restoreLinkedMediaState: function($container){
            const stored = sessionStorage.getItem('Heurist_RecView_LinkedMedia');
            const $linkedCheckbox = $container.find('.show-linked-media');
            if($linkedCheckbox.length === 0){
                return;
            }

            if(stored === '0' || stored === '1'){
                $linkedCheckbox.prop('checked', stored === '0');
                this.setLinkedVisible($container, stored === '0');
            }else{
                this.setLinkedVisible($container, $linkedCheckbox.is(':checked'));
            }
        },

        setImagesVisible: function($container, visible){
            $container.find('.media-content, .record-linked-media-header').toggle(visible);

            const $toggle = $container.find('.record-media-toggle-images');
            $toggle.attr('data-state', visible ? 'shown' : 'hidden');
            $toggle.html(`<span class="ui-icon ui-icon-menu" style="font-size:1.2em;display:inline-block;vertical-align:middle;"></span>&nbsp;${visible ? 'hide all media' : 'show all media'}`);

            if(visible){
                const $linkedCheckbox = $container.find('.show-linked-media');
                if($linkedCheckbox.length > 0){
                    this.setLinkedVisible($container, $linkedCheckbox.is(':checked'));
                }
            }
        },

        setLinkedVisible: function($container, visible){
            $container.find('.linked-media').toggle(visible);
        },

        zoomInOut: function(obj, thumb, url){
            const currentImg = obj;
            if(!currentImg || !currentImg.parentNode){
                return;
            }

            if(currentImg.parentNode.className.indexOf('fullSize') === -1){
                $(currentImg).hide();
                currentImg.src = url;
                currentImg.onload = function(){
                    $(currentImg).fadeIn(500);
                };
                currentImg.parentNode.className = `${currentImg.parentNode.className} fullSize`;
                currentImg.parentNode.parentNode.style.width = '100%';
            }else{
                currentImg.src = thumb;
                currentImg.parentNode.className = currentImg.parentNode.className.replace(/\bfullSize\b/g, '').replace(/\s+/g, ' ').trim();
                if(currentImg.parentNode.className.indexOf('thumb_image') === -1){
                    currentImg.parentNode.className += ' thumb_image';
                }
                currentImg.parentNode.parentNode.style.width = 'auto';
            }
        },
        
        mediaTooltips: function($container){

            $container.find('span.media-desc, span.media-right')
                .off('mouseenter.recordMediaTooltip focusin.recordMediaTooltip mouseleave.recordMediaTooltip focusout.recordMediaTooltip')
                .on('mouseenter.recordMediaTooltip focusin.recordMediaTooltip', (event) => {

                    let $ele = $(event.target);

                    if(typeof $ele.tooltip !== 'function'){
                        return;
                    }

                    $ele.tooltip({
                        content: function(){
                            return $(this).attr('title');
                        },
                        open: function(event, ui){

                            ui.tooltip.css({
                                background: '#D4DBEA',
                                'font-size': '1em',
                                padding: '5px',
                                width: '85%',
                                cursor: 'default'
                            });

                            let $ele = $(this);
                            let $tooltip = ui.tooltip;

                            $tooltip.off('mouseenter.recordMediaTooltip mouseleave.recordMediaTooltip');

                            $tooltip
                                .on('mouseleave.recordMediaTooltip', function(){
                                    $ele.attr('data-tooltip', 0);
                                    setTimeout(function(){
                                        if($ele.attr('data-tooltip') != 1 && $ele.tooltip('instance') !== undefined){
                                            $ele.tooltip('close');
                                        }
                                    }, 1000);
                                })
                                .on('mouseenter.recordMediaTooltip', function(){
                                    $ele.attr('data-tooltip', 1);
                                });
                        },
                        position: {
                            my: 'left top+5',
                            at: 'left bottom',
                            collision: 'flipfit'
                        }
                    });

                    $ele.tooltip('open');
                })
                .on('mouseleave.recordMediaTooltip focusout.recordMediaTooltip', function(event){

                    if(window.hWin?.HEURIST4?.util?.stopEvent){
                        window.hWin.HEURIST4.util.stopEvent(event);
                    }

                    event.stopImmediatePropagation();

                    let $ele = $(event.target);

                    let int_id = setInterval(function(){
                        if($ele.attr('data-tooltip') != 1 && $ele.tooltip('instance') !== undefined){
                            $ele.tooltip('destroy');
                        }
                        clearInterval(int_id);
                    }, 1000);
                });
        }        
    };

    window.HeuristRecordMedia = api;
})(window, jQuery);