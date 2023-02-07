/*
* Copyright (C) 2005-2020 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* hintDiv.js
* Creates popup div with given HTML content to view at the specified coordinates
*
* @todo consider replacement with jquery ui tooltip 
* requires jquery
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

//
// initcontent - html content
// 
function HintDiv(_id, _width, _height, _initcontent) {

	//private members
	var _className = "HintDiv";

	var popup_div = null,
		hideTimer,
		needHideTip = true;
		id = _id,
		width = _width,
		height = _height,
		initcontent = _initcontent;

	function _init()
	{
		if(popup_div===null){

			var _map_popup = document.createElement('div');
			_map_popup.id = id;
			document.body.appendChild(_map_popup);
			popup_div = $("#"+id);

			if(width>0 && height>0){
				popup_div.css({
					'width':width+'px',
					'height':height+'px'});
			}

			popup_div.css({
				'position':'absolute',
				'z-index':'2147483647',
				'left':'-9999px',
				'top':'0px',
				'background-color':'RGBA(0,0,0,0.75)',
				'padding':'5px',
				'border':'1px solid #fff',
				'min-width':'200',
				'color':'#EDEDED',
				'border-radius':'5px',
				'-moz-border-radius':'5px',
				'-webkit-border-radius':'5px',
				'box-shadow':'0px 1px 5px RGBA(0,0,0,0.5)'});

			if(!initcontent){
				initcontent	= "<div id='"+_id+"-content'>test popup</div>";
			}

			popup_div.html(initcontent);

			//tooltip div mouse out
			function __hideToolTip2() {
				needHideTip = true;
				hideTimer = window.setTimeout(_hideToolTip, 500); //_hideToolTip();
			}
			//tooltip div mouse over
			function __clearHideTimer2() {
				needHideTip = false;
				_clearHideTimer();
			}

			popup_div.mouseover(__clearHideTimer2);
			popup_div.mouseout(__hideToolTip2);
		}
	}

	/**
	*
	*/
	function _setSize(wh){
		if(popup_div!=null){

				popup_div.css({
					'width':wh[0]+'px',
					'height':wh[1]+'px'});
		}
	}

	/**
	* Returns array that contain the mouse position relative to the document
	*/
	function _getMousePos(e){

		var posx = 0;
		var posy = 0;
		if (!e) var e = window.event;
		if (e.pageX || e.pageY) 	{
				posx = e.pageX;
				posy = e.pageY;
		}
		else if (e.clientX || e.clientY) 	{
			posx = e.clientX + document.body.scrollLeft
				+ document.documentElement.scrollLeft;
			posy = e.clientY + document.body.scrollTop
				+ document.documentElement.scrollTop;
		}

		return [posx, posy];
	}

	/**
	* Adjusts the position of div to prevent it out of border
	*/
	function _showPopupDivAt(xy, border_top, border_right, border_height, offset){

		var div_height =  popup_div.height();
		var div_width =  popup_div.width();
		var pageHeight = popup_div.parents().height();
		var scrollValue = popup_div.parents().scrollTop();
		if(!offset) {
			offset = 5;
		}
		//var lft = popup_div.css('left');
		left_pos=Math.max(0,Math.min(xy[0]+offset, border_right - div_width));
 		top_pos=Math.max(xy[1]-(div_height/2)+offset,0);//-scrollValue;

		popup_div.css( {left:left_pos+'px',
					top:top_pos+'px',
					visibility:'visible',opacity:'1'});
	}


	//
	//
	//
	function _showAt(event)
	{
			var xy = _getMousePos(event);
			_showAtXY(xy);
	}

	function _showAtXY(xy){

			_init();
			//xy = [posx = event.target.x,posy = event.target.y];

			//var _map_popup = $("#mapPopup");
			//_map_popup.html(xy[0]+",  "+xy[1]+"<br/>");

			var border_top = $(window).scrollTop();
			var border_right = $(window).width();
			var border_height = $(window).height();
			var offset =0;

			_showPopupDivAt(xy, border_top ,border_right ,border_height, offset );

			return popup_div;
	}

	//
	//
	function _clearHideTimer(){
		if (hideTimer) {
			window.clearTimeout(hideTimer);
			hideTimer = 0;
		}
	}

	//
	//
	function _hideToolTip(){
		if(needHideTip && popup_div){
			//!!! currentTipId = null;
			_clearHideTimer();
			popup_div.css( {visibility:"hidden",opacity:"0"});
		}
	}

	//public members
	var that = {

		showAt: function(event){
			_showAt(event);
		},
		showAtXY: function(xy){
			_showAtXY(xy);
		},
		showInfoAt: function(xy, divid, divcontent){

					if(!divid){
						divid = id+"-content";
					}

					var my_tooltip = $("#"+divid);
					my_tooltip.html(divcontent);

                    _setSize([my_tooltip.width(), my_tooltip.height()+25]);
					_showAtXY(xy);
		},
		setSize: function(wh){
			_setSize(wh);
		},
		hide: function(){
			hideTimer = window.setTimeout(_hideToolTip, 1000);
		},
		close: function(){
			needHideTip = true;
			_hideToolTip();
		},
		getClass: function () {
			return _className;
		},

		isA: function (strClass) {
			return (strClass === _className);
		}

	}

	_init();
	return that;
}
