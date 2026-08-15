/**
 * mapSymbolPreview.js - Compact visual preview for Heurist map symbology.
 *
 * The editor often does not know the geometry family of the records that will
 * be rendered. In that case this renderer deliberately shows a compact
 * composite sample (point + polygon) rather than pretending that the
 * symbol belongs to one particular geometry type.
 *
 * @project     Heurist academic knowledge management system
 * @package     Widgets.Editing
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov <osmakov@gmail.com>
 * @since       7.0
 */

(function(){
    'use strict';

    if(!window.hWin.HEURIST4.ui) window.hWin.HEURIST4.ui = {};

    /**
     * Render a compact map-symbol sample into container.
     *
     * @param {HTMLElement|jQuery} container Target element.
     * @param {Object} symbol Normalized Heurist map symbol.
     * @param {Object} [options]
     * @param {?string} [options.geometryType=null] point|line|polygon. Null renders
     *        a composite preview because thematic editing does not know geometry.
     * @param {number} [options.rectypeId=12] Record type used when iconType=rectype.
     */
    window.hWin.HEURIST4.ui.renderMapSymbolPreview = function(container, symbol, options){
        container = $(container);
        if(container.length===0) return;

        symbol = symbol || {};
        options = options || {};

        container.empty().css({
            display:'inline-flex',
            'align-items':'center',
            'justify-content':'center',
            gap:'5px',
            width:'90px',
            height:'34px',
            padding:'1px 3px',
            'box-sizing':'border-box',
            'vertical-align':'middle',
            'background-image':'none'
        });

        const geometryType = options.geometryType || null;
        if(geometryType==='point'){
            container.append(createPointSample(symbol, options));
        }else if(geometryType==='line'){
            container.append(createLineSample(symbol));
        }else if(geometryType==='polygon'){
            container.append(createPolygonSample(symbol));
        }else{
            // Geometry is unknown during editing. A point sample shows marker-specific
            // settings while a polygon sample shows fill/stroke styling. A separate line
            // sample adds little information and makes the preview unnecessarily busy.
            container.append(createPointSample(symbol, options));
            container.append(createPolygonSample(symbol));
        }
    };

    function createPointSample(symbol, options){
        const wrapper = $('<span>').css({
            display:'inline-flex', width:'24px', height:'28px',
            'align-items':'center', 'justify-content':'center', overflow:'hidden'
        });

        const iconType = String(symbol.iconType || 'circle').toLowerCase();

        if(iconType==='iconfont' && symbol.iconFont){
            const icon = $('<span>').addClass(normalizeIconFontClass(symbol.iconFont));
            const size = pointSize(symbol);
            icon.css({
                color:symbol.color || symbol.fillColor || '',
                'font-size':size+'px', width:size+'px', height:size+'px',
                display:'inline-flex', 'align-items':'center', 'justify-content':'center'
            });
            return wrapper.append(icon);
        }

        if(iconType==='url' && symbol.iconUrl){
            const image = $('<img>', {src:String(symbol.iconUrl), alt:''}).css({
                width:pointSize(symbol)+'px', height:pointSize(symbol)+'px',
                'object-fit':'contain', 'max-width':'24px', 'max-height':'28px'
            });
            applyImageSymbolFilter(image, symbol);
            return wrapper.append(image);
        }

        if(iconType==='rectype'){
            const rectypeId = parseInt(options && options.rectypeId) || 12;
            const iconBaseURL = window.hWin.HAPI4 && window.hWin.HAPI4.iconBaseURL;

            if(iconBaseURL){
                const size = pointSize(symbol);
                const image = $('<img>', {
                    src:String(iconBaseURL)+rectypeId,
                    alt:''
                }).css({
                    width:size+'px', height:size+'px',
                    'object-fit':'contain', 'max-width':'24px', 'max-height':'28px'
                });
                applyImageSymbolFilter(image, symbol);

                // Fall back to a generic marker if the record-type icon is unavailable.
                image.on('error', function(){
                    $(this).replaceWith(createGenericPoint(symbol));
                });
                return wrapper.append(image);
            }
        }

        return wrapper.append(createGenericPoint(symbol));
    }

    /**
     * Apply the same monochrome-image tinting method used by heurist-map.
     * URL/record-type image markers use symbol.color, matching heurist-map.
     */
    function applyImageSymbolFilter(image, symbol){
        const tintColor = symbol.color;
        const filter = hexToCssFilter(tintColor);
        if(filter) image.css('filter', filter);
    }

    /** Return a deterministic CSS filter which tints a dark/monochrome icon toward a hex color. */
    function hexToCssFilter(color){
        const rgb = parseHex(color);
        if(!rgb) return '';
        const hsl = rgbToHsl(rgb.r, rgb.g, rgb.b);
        const invert = Math.round(hsl.l * 100);
        const saturation = Math.round(100 + hsl.s * 900);
        const brightness = Math.round(60 + hsl.l * 80);
        return 'brightness(0) saturate(100%) invert('+invert+'%) sepia(100%) saturate('+saturation+
               '%) hue-rotate('+Math.round(hsl.h * 360 - 45)+'deg) brightness('+brightness+'%) contrast(100%)';
    }

    function parseHex(value){
        const text = String(value || '').trim();
        let match = /^#([0-9a-f]{6})$/i.exec(text);
        if(match){
            return {r:parseInt(match[1].slice(0,2),16), g:parseInt(match[1].slice(2,4),16), b:parseInt(match[1].slice(4,6),16)};
        }
        match = /^#([0-9a-f]{3})$/i.exec(text);
        if(!match) return null;
        const hex = match[1].split('').map(function(c){ return c+c; }).join('');
        return {r:parseInt(hex.slice(0,2),16), g:parseInt(hex.slice(2,4),16), b:parseInt(hex.slice(4,6),16)};
    }

    function rgbToHsl(r, g, b){
        r /= 255; g /= 255; b /= 255;
        const max = Math.max(r,g,b);
        const min = Math.min(r,g,b);
        let h = 0;
        let saturation = 0;
        const lightness = (max + min) / 2;
        if(max !== min){
            const delta = max - min;
            saturation = lightness > 0.5 ? delta / (2 - max - min) : delta / (max + min);
            if(max === r) h = (g - b) / delta + (g < b ? 6 : 0);
            else if(max === g) h = (b - r) / delta + 2;
            else h = (r - g) / delta + 4;
            h /= 6;
        }
        return {h:h, s:saturation, l:lightness};
    }

    function createGenericPoint(symbol){
        const size = Math.min(24, pointSize(symbol));
        return $('<span>').css({
            display:'inline-block', width:size+'px', height:size+'px',
            'box-sizing':'border-box', 'border-radius':'50%',
            border: symbol.stroke===false ? 'none' : Math.max(1, Number(symbol.weight)||1)+'px solid '+
                    cssColorWithOpacity(symbol.color || '#777', numberOr(symbol.opacity, 1)),
            background: symbol.fill===false ? 'transparent' :
                    cssColorWithOpacity(symbol.fillColor || symbol.color || '#777', numberOr(symbol.fillOpacity, 1))
        });
    }

    function createLineSample(symbol){
        const wrapper = $('<span>').css({
            display:'inline-flex', width:'18px', height:'28px',
            'align-items':'center', 'justify-content':'center'
        });
        const line = $('<span>').css({
            display:'block', width:'18px', height:0,
            'border-top-width':Math.max(1, Math.min(6, Number(symbol.weight)||2))+'px',
            'border-top-style':symbol.dashArray ? 'dashed' : 'solid',
            'border-top-color':symbol.stroke===false ? 'transparent' : (symbol.color || '#777'),
            opacity:numberOr(symbol.opacity, 1)
        });
        return wrapper.append(line);
    }

    function createPolygonSample(symbol){
        const wrapper = $('<span>').css({
            display:'inline-flex', width:'18px', height:'28px',
            'align-items':'center', 'justify-content':'center'
        });
        const polygon = $('<span>').css({
            display:'block', width:'16px', height:'16px', 'box-sizing':'border-box',
            border: symbol.stroke===false ? 'none' : Math.max(1, Math.min(4, Number(symbol.weight)||1))+'px solid '+
                    cssColorWithOpacity(symbol.color || '#777', numberOr(symbol.opacity, 1)),
            background: symbol.fill===false ? 'transparent' :
                    cssColorWithOpacity(symbol.fillColor || symbol.color || '#777', numberOr(symbol.fillOpacity, 1))
        });
        return wrapper.append(polygon);
    }

    function pointSize(symbol){
        const radiusSize = Number(symbol.radius) * 2;
        if((symbol.iconType || 'circle')==='circle' && Number.isFinite(radiusSize) && radiusSize>0){
            return Math.max(5, Math.min(24, Math.round(radiusSize)));
        }
        let iconSize = Array.isArray(symbol.iconSize) ? Number(symbol.iconSize[0]) : Number(symbol.iconSize);
        if(!Number.isFinite(iconSize) || iconSize<=0){
            iconSize = Number.isFinite(radiusSize) && radiusSize>0 ? radiusSize : 14;
        }
        return Math.max(5, Math.min(24, Math.round(iconSize)));
    }

    function normalizeIconFontClass(iconFont){
        const classes = String(iconFont || '').trim().split(/\s+/).filter(Boolean);
        const isFontAwesome = classes.some(function(name){
            return name==='fa' || name==='fas' || name==='far' || name==='fab' || name.indexOf('fa-')===0;
        });
        if(isFontAwesome){
            const hasStyleClass = classes.some(function(name){
                return name==='fa-solid' || name==='fa-regular' || name==='fa-brands' ||
                       name==='fas' || name==='far' || name==='fab';
            });
            if(!hasStyleClass) classes.unshift('fa-solid');
            return classes.join(' ');
        }
        const iconClass = classes.find(function(name){ return name.indexOf('ui-icon-')===0; }) || classes[0] || 'ui-icon-location';
        return 'ui-icon '+(iconClass.indexOf('ui-icon-')===0 ? iconClass : 'ui-icon-'+iconClass);
    }

    function numberOr(value, fallback){
        const number = Number(value);
        return Number.isFinite(number) ? number : fallback;
    }

    function cssColorWithOpacity(color, opacity){
        const alpha = Math.min(1, Math.max(0, numberOr(opacity, 1)));
        const text = String(color || '').trim();
        const short = /^#([0-9a-f]{3})$/i.exec(text);
        const full = /^#([0-9a-f]{6})$/i.exec(text);
        let hex = full ? full[1] : null;
        if(short) hex = short[1].split('').map(function(c){ return c+c; }).join('');
        if(!hex) return text;
        return 'rgba('+parseInt(hex.slice(0,2),16)+','+parseInt(hex.slice(2,4),16)+','+
                     parseInt(hex.slice(4,6),16)+','+alpha+')';
    }
})();
