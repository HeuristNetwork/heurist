/**
 * ImageAnnotation.js
 *
 * Toolbar to create/edit image markers
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 *
 */
var image_digitizer_container;

function ImageAnnotation(self, _recID) {

	var _className = "ImageAnnotation",
		_markers,
		_markers_div = [],
		_imageviewer = self,
		_selAnnotations, lblInfo, _selRectypes,
		_isModeAddPoint = false,
		_isModeAddRect = 0,
		_db,
		_recordID = _recID;

	function _init(){

		if(image_digitizer_container){

		var edittoolbar = document.createElement('div');

		_selRectypes = document.createElement('select');
		//top.HEURIST.util.addoption(_selRectypes, 0, 'select rectype');

		var btnAnnotation = document.createElement('button');
		btnAnnotation.innerHTML = "marker";
		btnAnnotation.onclick = function(e){ _isModeAddRect = 0; _isModeAddPoint=true; };

		var btnRectangle = document.createElement('button');
		btnRectangle.innerHTML = "rect";
		btnRectangle.onclick = function(e){ _isModeAddRect = 2; _isModeAddPoint=false; };

		var btnEdit = document.createElement('button');
		btnEdit.innerHTML = "edit";
		btnEdit.onclick = function(e){ _editAnnotationRecord(); };

		var btnDelete = document.createElement('button');
		btnDelete.innerHTML = "delete";
		btnDelete.onclick = function(e){ _delAnnotation(); };

		_selAnnotations = document.createElement('select');
		_selAnnotations.style.width = 200;
		_selAnnotations.onchange = _hightlighSelection;
		//_hightlighSelection(null);

		edittoolbar.appendChild(_selRectypes);
		edittoolbar.appendChild(btnAnnotation);
		edittoolbar.appendChild(btnRectangle);
		edittoolbar.appendChild(_selAnnotations);
		edittoolbar.appendChild(btnEdit);
		edittoolbar.appendChild(btnDelete);

		lblInfo = document.createElement('label');
		lblInfo.id = 'lblInfoCoords';
		lblInfo.innerHTML = "";
		edittoolbar.appendChild(lblInfo);


		image_digitizer_container.appendChild(edittoolbar);
		}


		_db = (window.HEURIST && window.HEURIST.parameters && window.HEURIST.parameters.db ? window.HEURIST.parameters.db :
													(top.HEURIST.parameters.db?top.HEURIST.parameters.db:
															(top.HEURIST.database.name? top.HEURIST.database.name:'')));

		getAnnotationRectypes();
		//_createAnnotations();

	}

	//
	function _noEditAllowed(){

		if(image_digitizer_container){

			var cell = image_digitizer_container;
			if ( cell.hasChildNodes() )
			{
    			while ( cell.childNodes.length >= 1 ){
     					cell.removeChild( cell.firstChild );
    			}
			}
			cell.innerHTML = "";

			var edittoolbar = document.createElement('div');
			lblInfo = document.createElement('label');
			lblInfo.id = 'lblInfoCoords';
			lblInfo.style.color = '#ff0000';
			lblInfo.innerHTML = "Image annotation feature is disabled. No appropriate record type found. Import from Core definitions or create new one";
			edittoolbar.appendChild(lblInfo);
			image_digitizer_container.appendChild(edittoolbar);

		}
	}

	//
	function _setDimension(czoom){
			var k, mdiv, msize;
			for (k=0; k<_markers_div.length; k++){
				mdiv  = _markers_div[k];
				msize = _markers[k];
				if(msize[2]==10 && msize[2]==10){ //marker
					mdiv.style.width  = 10;
					mdiv.style.height = 10;
				}else{
					mdiv.style.width  = msize[2]*czoom;
					mdiv.style.height = msize[3]*czoom;
				}
			}
	}

	//
	// x,y - top left of image
	//
	function _setPosition(x, y, czoom){
		var k, mdiv, msize;
		for (k=0; k<_markers_div.length; k++){
			mdiv  = _markers_div[k];
			msize = _markers[k];
			if(msize[2]==10 && msize[2]==10){ //marker
				mdiv.style.left  = (msize[0]*czoom - 5 + Math.round(x)+'px');
				mdiv.style.top = (msize[1]*czoom - 5 + Math.round(y)+'px');
			}else{
				mdiv.style.left  = (msize[0]*czoom + Math.round(x)+'px');
				mdiv.style.top = (msize[1]*czoom + Math.round(y)+'px');
			}
		}
	}


	/*function isnull(obj){
		return ( (typeof obj==="undefined") || (obj===null));
	}
	function isempty(obj){
		return ( isnull(obj) || (obj==="") || (obj==="null") );
	}*/

	function _createAnnotationDiv(ind, clr, czoom) {

		var params = _markers[ind];

		var d = document.createElement('div');
		d.id = "marker"+ind;
		d.style.position='absolute';
		d.style.left = Math.round(params[0]*czoom);
		d.style.top = Math.round(params[1]*czoom);
		d.style.width = Math.round((params[2] - params[0])*czoom);
		d.style.height = Math.round((params[3] - params[1])*czoom);
		d.style.borderWidth="1px";
		d.style.borderColor="#ff0000";
		d.style.borderStyle = "solid";
		//d.style.backgroundColor = clr;
		d.style.zIndex=999;
		d.title = top.HEURIST.util.isempty(params[5])?params[4]:params[5];
		if(params[4].indexOf('http')==0){
			d.onclick = function(event){
					var k = Number(event.target.id.substr(6));
					var url = _markers[k][4];
					if(!top.HEURIST.util.isempty(url)){
						window.open(url, '_blank')
					}
			};
		}
		/*d.onmousewheel = function(event,object,direction) {
					self.onmousewheel(event,object,direction);
		};*/
		return d;
	}

	//
	// search annotation for given _recordID
	//
	function _createAnnotations(){

		//load list of annotations
		function _updateList(context){

			if(!context) {
				alert("An error occurred trying to contact the database");
				return;
			}

			_markers = [];
			if(_selAnnotations){
				while (_selAnnotations.length>0){
						_selAnnotations.remove(0);
				}
			}

			if(top.HEURIST.util.isnull(context['records']) || context['resultCount']==0){
				return;
			}

			var ind, ind2, k = 0,
				records = context['records'];

			for(ind in records)
			{
				if(!top.HEURIST.util.isnull(ind)){
					var item = records[ind];

					var area = item['details'][top.HEURIST.magicNumbers['DT_ANNOTATION_RANGE']];
					for(ind2 in area){
						     if(!top.HEURIST.util.isnull(ind2)){
						     	 area = area[ind2];
						     	 break;
							 }
					}

					if(!top.HEURIST.util.isempty(area)){ //temp && area.indexOf("i:")==0){

						if(area.indexOf("i:")<0) area = "i:"+area;
						//area = area+":";

						area = area.split(":");
						if(area.length>2){
							var x0 = Number(area[1]),
								y0 = Number(area[2]),
								x1 = (area.length>3)?Number(area[3]):NaN,
								y1 = (area.length>3)?Number(area[4]):NaN;

							if(!(isNaN(x0) || isNaN(y0) || x0<0 || y0<0)){
								_markers.push([x0, y0, x1, y1, item.rec_URL, item.rec_Title, item.rec_ID]);

								var d1 = _createAnnotationDiv(k, "#0000ff", 1);
								_imageviewer.frameElement.appendChild(d1);
								_markers_div.push(d1);

								if(_selAnnotations) top.HEURIST.util.addoption(_selAnnotations, k, _markers[k][5]);
								k++;
							}
						}
					}

				}
			}
		}

		var baseurl = top.HEURIST.baseURL + "records/files/imageAnnotation.php";
		var callback = _updateList;
		var params = "recid="+_recordID+"&db="+_db;
		top.HEURIST.util.getJsonData(baseurl, callback, params);
	}

	//
	//  Loads list of appropriate record types
	//
	function getAnnotationRectypes(){

		function _updateAnnList(context){

			if(!context) {
				alert("An error occurred trying to contact the database");
			}else{

				var ind, k = 0;

				for(ind in context)
				{
					if(!top.HEURIST.util.isnull(ind)){
						var item = context[ind];
						top.HEURIST.util.addoption(_selRectypes, item['id'], item['name']);
					}
				}

			}
			if(_selRectypes.length<1){
				_noEditAllowed();
			}else{
				_createAnnotations();
			}
		}

		if(_selRectypes){
			var baseurl = top.HEURIST.baseURL + "records/files/imageAnnotation.php";
			var callback = _updateAnnList;
			var params = "listrt=1&db="+_db;
			top.HEURIST.util.getJsonData(baseurl, callback, params);
		}
	}


	//
	//
	//
	function _hightlighSelection(event){
		var k, mdiv;
		for (k=0; k<_markers_div.length; k++){
			mdiv  = _markers_div[k];
			//mdiv.style.borderColor = (_selAnnotations.selectedIndex==k)?"#ff0000":"#00ff00";
			mdiv.style.borderWidth = (_selAnnotations.selectedIndex==k)?"3px":"2px";
		}
	}

	//
	// create new annotation div
	//
	function _addAnnotation(x, y, czoom, imgx, imgy){

		if(_isModeAddPoint || _isModeAddRect==2){
			var dx = 0;//_isModeAddPoint?5/czoom:0;
			_markers.push([Math.round(x)-dx, Math.round(y)-dx, 10, 10, "", "Annotation #"+_markers.length, 0]);
			var k = _markers.length-1;
			var d1 = _createAnnotationDiv(k, "#ff0000", 1);
			_imageviewer.frameElement.appendChild(d1);
			_markers_div.push(d1);

			top.HEURIST.util.addoption(_selAnnotations, k, _markers[k][5]);
			_selAnnotations.selectedIndex = k;
			_hightlighSelection(null);

			_setPosition(imgx, imgy, czoom);
			_setDimension(czoom);

			if(!_isModeAddPoint) {
				_isModeAddRect=1;
			}

		}else if (_isModeAddRect==1) {
			_isModeAddRect=0;

			var d1 = _markers_div[_markers_div.length-1];
			var marker = _markers[_markers_div.length-1];

			if(x<marker[0]){
				marker[2] = marker[0]-x;
				marker[0] = x;
			}else{
				marker[2] = x-marker[0];
			}
			if(y<marker[1]){
				marker[3] = marker[1]-y;
				marker[1] = y;
			}else{
				marker[3] = y-marker[1];
			}

			_setPosition(imgx, imgy, czoom);
			_setDimension(czoom);
		}

		_isModeAddPoint = false;
	}

	//
	//
	//
	function _delAnnotation(){
		var ind = _selAnnotations.selectedIndex;

		if(ind>=0 && ind<_markers_div.length){
			var div = _markers_div[ind];
			var recid = Number(_markers[ind][6]);

        	function _afterDelete(context){

        		if(top.HEURIST.util.isnull(context) || top.HEURIST.util.isnull(context['error'])){

					div.parentNode.removeChild(div);
					_markers_div.splice(ind,1);
					_markers.splice(ind,1);
					_selAnnotations.remove(ind);
				}else{
					alert(context['error']);
				}
			}

			if(recid>0){
				//delete annotation record as well


				var baseurl = top.HEURIST.baseURL + "records/files/imageAnnotation.php";
				var callback = _afterDelete;
				var params = "delete=1&recid="+recid+"&db="+_db;
				top.HEURIST.util.getJsonData(baseurl, callback, params);
			}else{
				_afterDelete(null);

			}

		}
	}

	function _editAnnotationRecord(){

		var ind = _selAnnotations.selectedIndex;

		if(ind<0 && ind>=_markers_div.length){
			return;
		}

        var rectype = Number(_selRectypes.value);
		if(isNaN(rectype)){
			return;
		}

		var marker = _markers[ind];
		var recImageTitle = 'todo: name of file';

			if(Number(marker[6])>0){
					window.open(top.HEURIST.basePath+"/records/edit/editRecord.html?recID="+marker[6]+"&db="+_db,"_blank");
			}else if(_recordID>0){

				top.HEURIST.util.popupURL(window, top.HEURIST.basePath +'records/add/formAddRecordPopup.html?rectype='+
							rectype+ //top.HEURIST.magicNumbers['RT_ANNOTATION_IMAGE']+
										'&addr='+marker[0]+":"+marker[1]+":"+marker[2]+":"+marker[3]+
										'&trgRecID='+_recordID +
										(recImageTitle ? '&trgRecTitle='+ recImageTitle: "") +
										'&db='+ _db,
									{ callback: function(title, bd, bibID) {
													if (bibID) {
														//save scroll location
														//reload Document

														//xml = loadXMLDocFromFile(curQueryURL);
														//displayResultDoc(curStyle, xml);
														//setScroll location

														marker[5] = title;
														marker[6] = bibID;
														_markers_div[ind].text = title;
														_selAnnotations.options[_selAnnotations.selectedIndex].text = title;

													} else {
														alert("Annotation didn't save please try again");
													}
												}
									});
			}
	}

	//
	//public members
	//
	var that = {

				setDimension: function(czoom){ _setDimension(czoom); },
				setPosition: function(x, y, czoom){ _setPosition(x, y, czoom); },
				createAnnotations: function(){ _createAnnotations(); },

				addAnnotation: function(x, y, czoom, imgx, imgy){ _addAnnotation(x, y, czoom, imgx, imgy); },

				showInfo: function(msg){ lblInfo.innerHTML = msg; }, //debug function - to remove


				getClass: function () {
					return _className;
				},

				isA: function (strClass) {
					return (strClass === _className);
				}

	};

	_init();
	return that;
}