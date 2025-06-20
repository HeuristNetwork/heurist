/**
 * @file hintDiv.js
 * @brief Creates and manages a popup hint div (tooltip) with HTML content.
 * @fileOverview This script defines the HintDiv factory function, which creates and manages a customizable
 * popup div (tooltip). The hint can display HTML content, be positioned at specified coordinates or
 * follow the mouse, and automatically adjusts to stay within viewport boundaries. It includes features
 * like delayed hiding on mouseout and staying visible on hover over the hint itself. This utility is
 * used throughout Heurist for providing contextual information to users. It requires jQuery.
 * @package Heurist academic knowledge management system
 * 
 * @subpackage hclient\core
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Tom Murtagh
 * @author Kim Jackson
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @author Stephen White
 * @author Artem Osmakov <osmakov@gmail.com>
 * @since 3.1.0
 * @todo Consider replacement with jQuery UI tooltip.
 */

/**
 * Creates and manages a popup hint div (tooltip).
 * The hint div is created with a specific ID, dimensions, and initial HTML content.
 * It can be shown at specified coordinates or at the mouse position, and automatically
 * adjusts its position to stay within the window boundaries.
 * The hint hides on mouseout with a delay, but stays visible if the mouse moves over the hint itself.
 *
 * @constructor HintDiv
 * @param {string} _id - The ID to be assigned to the hint div element.
 * @param {number} [_width=0] - Initial width of the hint div in pixels. If 0, width is auto-adjusted by content.
 * @param {number} [_height=0] - Initial height of the hint div in pixels. If 0, height is auto-adjusted by content.
 * @param {string} [_initcontent] - Initial HTML content for the hint div. Defaults to a test message if not provided.
 * @returns {Object} An object with public methods to control the hint div.
 */
function HintDiv(_id, _width, _height, _initcontent) {

	//private members
	const _className = "HintDiv";

	/** @private @type {jQuery|null} The jQuery object for the hint div. */
	let popup_div = null;
	/** @private @type {number|null} Timer ID for hiding the tooltip. */
	let hideTimer;
	/** @private @type {boolean} Flag to control whether the tooltip should be hidden. */
	let needHideTip = true;
	/** @private @type {string} ID of the hint div. */
	let id = _id;
	/** @private @type {number} Width of the hint div. */
	let width = _width || 0;
	/** @private @type {number} Height of the hint div. */
	let height = _height || 0;
	/** @private @type {string} Initial HTML content. */
	let initcontent = _initcontent;

    /**
     * Initializes the hint div element.
     * If not already created, it appends a new div to the body, styles it,
     * sets its initial content, and attaches mouse event handlers for hover behavior.
     * @private
     * @returns {void}
     */    
	function _init()
	{
		if(popup_div===null){
			let _map_popup_elem = document.createElement('div');
			_map_popup_elem.id = id;
			document.body.appendChild(_map_popup_elem);
			popup_div = $("#"+id);

			if(width > 0 && height > 0){
				popup_div.css({
					'width': width + 'px',
					'height': height + 'px'
                });
			}

			popup_div.css({
				'position':'absolute',
				'z-index':'2147483647',
				'left':'-9999px',
				'top':'0px',
				'background-color':'RGBA(0,0,0,0.75)',
				'padding':'5px',
				'border':'1px solid #fff',
				'min-width':'200px',
				'color':'#EDEDED',
				'border-radius':'5px',
				'-moz-border-radius':'5px',
				'-webkit-border-radius':'5px',
				'box-shadow':'0px 1px 5px RGBA(0,0,0,0.5)'
            });

			if(!initcontent){
				initcontent	= "<div id='"+id+"-content'>Hint content</div>";
			}
			popup_div.html(initcontent);

			function __clearHideTimerOnEnter() {
				needHideTip = false;
				_clearHideTimer();
			}
			function __startHideTimerOnLeave() {
				needHideTip = true;
				hideTimer = window.setTimeout(_hideToolTip, 500);
			}

			popup_div.on( 'mouseenter', __clearHideTimerOnEnter ).on( 'mouseleave', __startHideTimerOnLeave );
		}
	}

    /**
     * Sets the width and height of the hint div.
     * @private
     * @param {Array<number>} wh - An array containing [width, height] in pixels.
     * @returns {void}
     */    
	function _setSize(wh){
		if(popup_div !== null && wh && wh.length === 2){
			popup_div.css({
				'width': wh[0] + 'px',
				'height': wh[1] + 'px'
            });
		}
	}

    /**
     * Gets the mouse position relative to the document.
     * @private
     * @param {MouseEvent} e - The mouse event object.
     * @returns {Array<number>} An array `[posX, posY]` containing the mouse x and y coordinates.
     */    
	function _getMousePos(e){
		let posx = 0;
		let posy = 0;
		if (!e) e = window.event;

		if (e.pageX || e.pageY){
			posx = e.pageX;
			posy = e.pageY;
		} else if (e.clientX || e.clientY){
			posx = e.clientX + (document.body.scrollLeft || document.documentElement.scrollLeft);
			posy = e.clientY + (document.body.scrollTop || document.documentElement.scrollTop);
		}
		return [posx, posy];
	}

    /**
     * Positions the hint div at the specified coordinates, adjusting to keep it within window boundaries.
     * @private
     * @param {Array<number>} xy - An array `[posX, posY]` for the desired initial position (usually mouse coordinates).
     * @param {number} viewport_border_top - The top scroll offset of the window.
     * @param {number} viewport_border_right - The right edge of the viewport (window width).
     * @param {number} viewport_height - The height of the viewport.
     * @param {number} [offset_val=5] - Offset in pixels from the mouse position.
     * @returns {void}
     */    
	function _showPopupDivAt(xy, viewport_border_top, viewport_border_right, viewport_height, offset_val){
		if (!popup_div) _init();

		const div_height = popup_div.outerHeight();
		const div_width = popup_div.outerWidth();
		const effective_offset = offset_val || 5;

		let left_pos = xy[0] + effective_offset;
		if (left_pos + div_width > viewport_border_right) {
			left_pos = xy[0] - div_width - effective_offset;
		}
		left_pos = Math.max(0, Math.min(left_pos, viewport_border_right - div_width));

		let top_pos = xy[1] - (div_height / 2);
		if (top_pos < viewport_border_top) {
			top_pos = viewport_border_top;
		} else if (top_pos + div_height > viewport_border_top + viewport_height) {
			top_pos = viewport_border_top + viewport_height - div_height;
		}
		top_pos = Math.max(0, top_pos);

		popup_div.css({
            left: left_pos + 'px',
            top: top_pos + 'px',
            visibility: 'visible',
            opacity: '1'
        });
	}

    /**
     * Shows the hint div at the current mouse position based on the provided event.
     * @private
     * @param {MouseEvent} event - The mouse event.
     * @returns {void}
     */    
	function _showAt(event) {
		const xy = _getMousePos(event);
		_showAtXY(xy);
	}

	function _showAtXY(xy){
		_init();
		const scrollTop = $(window).scrollTop();
		const windowWidth = $(window).width();
		const windowHeight = $(window).height();
		const offset = 15;
		_showPopupDivAt(xy, scrollTop, windowWidth, windowHeight, offset);
		return popup_div;
	}

    /**
     * Clears the timer that is set to hide the tooltip.
     * This is used, for example, when the mouse enters the tooltip itself.
     * @private
     * @returns {void}
     */    
	function _clearHideTimer(){
		if (hideTimer) {
			window.clearTimeout(hideTimer);
			hideTimer = 0;
		}
	}

    /**
     * Hides the tooltip if `needHideTip` is true.
     * Clears any existing hide timer before hiding.
     * Hiding is done by setting visibility to hidden and opacity to 0.
     * @private
     * @returns {void}
     */    
	function _hideToolTip(){
		if(needHideTip && popup_div){
			_clearHideTimer();
			popup_div.css( {visibility:"hidden", opacity:"0"});
		}
	}

	// Public methods exposed by the HintDiv instance
	let that = {
		/**
		 * Shows the hint div at the position of a mouse event.
		 * @param {MouseEvent} event - The mouse event object.
		 * @returns {void}
		 */
		showAt: function(event){
			_showAt(event);
		},
		/**
		 * Shows the hint div at specified X, Y document coordinates.
		 * @param {Array<number>} xy - An array `[posX, posY]` for the desired position.
		 * @returns {void}
		 */
		showAtXY: function(xy){
			_showAtXY(xy);
		},
		/**
		 * Updates the content of the hint div (or a specified inner div) and shows it at given coordinates.
		 * Adjusts the size of the hint div based on the new content before showing.
		 * @param {Array<number>} xy - An array `[posX, posY]` where the hint should be shown.
		 * @param {string} [divid] - Optional ID of an inner element within the hint div to update.
		 *                         If not provided, defaults to `id + "-content"`.
		 * @param {string} divcontent - HTML content to set for the specified div.
		 * @returns {void}
		 */
		showInfoAt: function(xy, divid, divcontent){
			_init();
			const target_divid = divid || (id + "-content");
			const $content_target = $("#" + target_divid);

			if ($content_target.length) {
				$content_target.html(divcontent);
				_setSize([$content_target.outerWidth(), $content_target.outerHeight() + 25]);
			} else {
                 popup_div.html(divcontent);
            }
			_showAtXY(xy);
		},
		/**
		 * Sets the size (width and height) of the hint div.
		 * @param {Array<number>} wh - An array `[width, height]` in pixels.
		 * @returns {void}
		 */
		setSize: function(wh){
			_setSize(wh);
		},
		/**
		 * Initiates hiding the hint div after a short delay (1 second).
		 * This allows for mouse movement towards the hint before it disappears.
		 * @returns {void}
		 */
		hide: function(){
			_clearHideTimer();
			needHideTip = true;
			hideTimer = window.setTimeout(_hideToolTip, 1000);
		},
		/**
		 * Immediately hides the hint div.
		 * @returns {void}
		 */
		close: function(){
			needHideTip = true;
			_hideToolTip();
		},
		/**
		 * Gets the class name of this component.
		 * @returns {string} The class name "HintDiv".
		 */
		getClass: function () {
			return _className;
		},
		/**
		 * Checks if the provided string matches the class name of this component.
		 * @param {string} strClass - The class name to compare.
		 * @returns {boolean} True if `strClass` is "HintDiv", false otherwise.
		 */
		isA: function (strClass) {
			return (strClass === _className);
		}
	};

	_init();
	return that;
}
