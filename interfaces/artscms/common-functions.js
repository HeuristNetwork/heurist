	
var record = new HRecord();

isEmpty = function (object){
	
	for (var o in object){ return false;}
	return true;
	
}
	
function getIdFromUrl (){
	var regexS = "[\\?&]id=([^&#]*)";
	var regex = new RegExp( regexS );
	var results = regex.exec( window.location.search );
	
	if( results == null )
		return;
	else
		return results[1];
		
}
	  
function getParams (name){
	var regexS = "[\\?&]"+name+"=([^&#]*)";
	var regex = new RegExp( regexS );
	var results = regex.exec( window.location.search );
	
	if( results == null )
		return;
	else
		return results[1];

}
	 
function stripSpace(words) {
	var spaces = words.length;
	
	for(var x = 0; x<spaces; ++x){
	   words = words.replace(" ", "");
	}
	
	return words;
}

function drawInputForm(detailType, recType){
	var detailId =detailType.getID();
	var detailType =  HDetailManager.getDetailTypeById(detailId); // HDetailType object
	var detName =  HDetailManager.getDetailNameForRecordType(recType, detailType);
	var detPrompt = HDetailManager.getDetailPromptForRecordType (recType, detailType);
	var detRequiremence = HDetailManager.getDetailRequiremence(recType, detailType);
	var isRepeatable = HDetailManager.getDetailRepeatable(recType, detailType);
	var reqText = ""; 
	var reqClass = "";
	
	switch (detRequiremence){
	case "Y":
	 	reqText = "Required";
	 	reqClass = "required";
	 	break;
	 
	 case "R":
	 	reqText = "Recommended";
	 	reqClass = "recommended";
		break;
	 
	 case "O":
	 	reqText = "Optional";
	 	reqClass = "optional";
		break;
	 
	 default:
	 	reqText = "Optional";
	 	reqClass = "optional";
	 	break;
	}
	
	//build template
	var tr = document.createElement("tr"); 
	var td = document.createElement("td");
	var td2 = document.createElement("td");
	var td3 = document.createElement("td");
	
    tr.className = reqClass;    // tag this detail's row with the requiremence so that we can show or hide based on it
    tr.id = detailId;           // tag this row with the detail id so that we can show a reduced list 

	td.id = "sub-res-type"; 
	td.innerHTML = detName;
	
	td2.className = "rep";
	if (isRepeatable)
	td2.innerHTML = "<img id= \"img"+detailId +"\" src=\""+path+"/img/duplicate.gif\" border=0>";
    
	td3.innerHTML = "<div class=pad id=sel"+detailId+"></div><div class=hint>"+detPrompt+"</div>";
	
	tr.appendChild(td);
	tr.appendChild(td2);
	tr.appendChild(td3);
	
	return tr;
	
}


var geoIndex=1000;

function drawGeo(id, detail){
	var html = "";
	var sel = document.getElementById("sel"+id);

	if (!sel) { //catch updated geo details and re-render them on main page
		var sel = window.opener.document.getElementById("sel"+id);
		var last  = sel.lastChild;
		sel.removeChild(last);
		html += "<div id=\"geoDiv" +[geoIndex]+"\"><div class=\"inp-div\" ><input type=hidden name=\""+id+"\" id=\""+id+"\" value=geo extra=\""+detail.getWKT()+"\"><img src=\""+path+"img/geo.gif\" align=absmiddle> "+detail.toString()+"&nbsp<a href=\"#\" id=\"editgeo"+[geoIndex]+"\" onclick=\"window.open('addGeoObject.html?id="+rec_id+"&did="+id+"&eid=editgeo"+[geoIndex]+"','form','width=600,height=400,scrollbars=yes');\")>edit</a><input type=\"hidden\" id=\"geodetail"+[geoIndex]+"\" value=\""+ detail.getWKT()+ "\"><input type=\"hidden\" id=\"geotype"+[geoIndex]+"\" value=\""+ detail.toString()+ "\">&nbsp;"+" <img src=\""+path+"img/cross.gif\" align=absmiddle onclick=\"remGeo("+id+",'"+detail.getWKT()+"');\"></div></div>";
		geoIndex++;
	} else {
		if (!isEmpty(detail)){
			for (i in detail){
				html += "<div id=\"geoDiv" +[i]+"\"><div class=\"inp-div\"  ><input type=hidden name=\""+id+"\" id=\""+id+"\" value=geo extra=\""+detail[i].getWKT()+"\"><img src=\""+path+"img/geo.gif\" align=absmiddle> "+detail[i].toString()+"&nbsp<a href=\"#\" id=\"editgeo"+[i]+"\" onclick=\"window.open('addGeoObject.html?id="+rec_id+"&did="+id+"&eid=editgeo"+[i]+"','form','width=600,height=400,scrollbars=yes');\")>edit</a><input type=\"hidden\" id=\"geodetail"+[i]+"\" value=\""+ detail[i].getWKT()+ "\"><input type=\"hidden\" id=\"geotype"+[i]+"\" value=\""+ detail[i].toString()+ "\">&nbsp;"+" <img src=\""+path+"img/cross.gif\" align=absmiddle onclick=\"remGeo("+id+",'"+detail[i].getWKT()+"');\"></div></div>"; 	  
			}
			
		} else {
			html = "<div class=\"inp-div\"><input type=hidden name=\""+id+"\" id=\""+id+"\" value=geo extra=\"\"><a href=\"#\" onclick=\"window.open('addGeoObject.html?id="+rec_id+"&did="+id+"','form','width=600,height=400,scrollbars=yes');\")>add</a></div>";

		}
	}
	sel.innerHTML += html;
	
}
	
function drawUrlField(record){
	if (record) var Url = record.getURL();
	var tr = document.createElement("tr"); 
	var td = document.createElement("td");
	var td2 = document.createElement("td");
	var td3 = document.createElement("td");
	
    tr.id = "url"
	td.id = "sub-res-type"; 
	td.innerHTML = "URL";

	if (Url)
		td3.innerHTML ="<div class=pad-bottom><input type=\"text\" class=\"text-input\" id=\"url-text\" value=\""+Url+"\"></div>";
	else
		td3.innerHTML ="<div class=pad-bottom><input type=\"text\" class=\"text-input\" id=\"url-text\" value=\"\"></div>";
    td3.innerHTML += "</div><div class=hint>Enter a URL of the form HTTP://HeuristScholar.org/heurist </div>";
	
	tr.appendChild(td);
	tr.appendChild(td2);
	tr.appendChild(td3);
	
	return tr;
}
	
function drawFile(id, detail){
	var sel = document.getElementById("sel"+id);
	var html ="";
	 
	if (!isEmpty(detail)){ //files
		for (i in detail){
			var thumb = detail[i].getThumbnailURL();
			var url = detail[i].getURL();
			var filename = detail[i].getOriginalName();
			var size = detail[i].getSize();
	  	
			html += "<div class=\"inp-div\"><input type=hidden name=\""+id+"\" id=\""+id+"\" value=file extra=\""+detail[i].getID()+"\"><img src = \""+thumb+"\"><img src=\""+path+"/img/cross.gif\" onclick=\"remFile("+id+","+detail[i].getID()+");\"><br><a href=\""+url+"\">"+filename+"</a> ["+size+"]</div>"; 
		}
		
	} else { //upload box
		html += "<div class=\"inp-div\"><input type=file  name=\""+id+"\" id=\""+id+"\" value=\"\" extra=\"\" onchange=\"uploadFile("+id+");\"></div>"; 
	}
	
	sel.innerHTML += html;
}
	
function drawInputField(id, detail){  
	var sel = document.getElementById("sel"+id);
	var html ="";
	
	if (!isEmpty(detail)){
		
		for (i in detail){
			html += "<div class=\"inp-div\"><input type=text class=\"text-input\"  name=\""+id+"\" id=\""+id+"\" value=\""+detail[i]+"\"></div>"; 
		}
		
	} else { 
		html += "<div class=\"inp-div\"><input type=text class=\"text-input\"  name=\""+id+"\" id=\""+id+"\" value=\"\"></div>"; 
	}
	sel.innerHTML += html;
	
}

function drawDateField(id, detail){  
	var sel = document.getElementById("sel"+id);
	var html ="";
	if (!isEmpty(detail)){
	  for (i in detail){
		
		html += "<div class=\"inp-div\"><input type=text  name=\""+id+"\" id=\""+id+"\" value=\""+detail[i]+"\">&nbsp<img src=\""+path+"/img/calendar.gif\" id=\"calendar"+id+"\" align = \"absbottom\" onclick=\"window.open('calendar.html?id="+id+"','mywin','width=300, height=150, resizable=no');\"></div>"; 
	  }
	}else{ 
	  html += "<div class=\"inp-div\"><input type=text  name=\""+id+"\" id=\""+id+"\" value=\"\">&nbsp<img src=\""+path+"/img/calendar.gif\" id=\"calendar"+id+"\" align = \"absbottom\" onclick=\"window.open('calendar.html?id="+id+"','mywin','width=300, height=150, resizable=no');\"></div>"; 
	}
	sel.innerHTML += html;
}
	
function drawResource(id, detail, constRecType){  
	var sel = document.getElementById("sel"+id);
	var html = "";
	 
	if (!isEmpty(detail)){
		var count = 0;
		html = ""; //reset
		
		for (z in detail){
			count ++;
			html +=  "<div class=\"inp-div\"><input class=\"resource\" mode=resource type=text recid=\"\" extra="+count+" id=\""+id+"\" name=\""+id+"\" value=\"\" ><img src=\""+path+"/img/cross.gif\" onclick=\"removeIt("+id+","+count+");\"></div>"; 
			doRecSearch(detail[z].getID(), "resource", id, count, constRecType);
		}
	
		sel.innerHTML += html;
	
	} else {
	 	html += "<div class=\"inp-div\"><input class=\"resource\" mode=resource type=text  extra=1 name=\""+id+"\" id=\""+id+"\" value=\"\" onclick=\"window.open('searchRes.html?id="+id+"&t="+constRecType+"&ext=1&q=', '','width=700, height=400, resizable=no'); \" ><img src=\""+path+"/img/cross.gif\" onclick=\"removeIt("+id+",1);\"></div>"; 
		sel.innerHTML += html;
		//drawResourceDetails(null, id, 1, constRecType); // FAILS MISERABLY> WHY??? this would have been a tidier way of doing it.
	}
	
}

function findChildElementById(elem, id){
    var e = elem;
    var  c;
    for (var i=0; i < e.childNodes.length ; i++) {
        c = e.childNodes[i];
        if (c.id && c.id == id) return c;
        if (c.childNodes && c.childNodes.length > 0){
            var temp = findChildElementById(c,id);
            if (temp) return temp;
        }
    }
    return null;
}
	
function removeIt(id, extra, notaTextField){ 
	var allInputs = document.getElementsByName(id);  
	var size = allInputs.length;
	
	for (i=0; i<allInputs.length; i++){
		if (allInputs[i].getAttribute("extra") == extra){
			var div = allInputs[i].parentNode;
			
			if (notaTextField){
				if (size > 1 && extra > 1){
					div.innerHTML="";
				} else { //Files and Geo Objects
				
					if (size == 1){
						generateNewField(allInputs, id);
						removeIt(id, extra, notaTextField);
					} else {
						div.parentNode.removeChild(div);
					}
					
				}
				
			} else { //if its an only field in the series, remove value and recid attributes only
				allInputs[i].value = "";
				if (allInputs[i].getAttribute("recid") != "") {
					allInputs[i].removeAttribute("recid");
				}
			}
		}
	}
}
	
function drawResourceDetails(record, id, extra, constRecType){  
	var recTitle="";
	var recid = "";
	
	if (record != null) {
		recTitle = record.getTitle(); //HRecordType object
		recid = record.getID();
	}
	
	var allInputs = document.getElementsByName(id); 
	for (i=0; i<allInputs.length; i++){
		
		if (allInputs[i].getAttribute("extra") == extra){
			allInputs[i].value = recTitle;
			allInputs[i].readOnly = true;
			allInputs[i].setAttribute("class", "resource");
			allInputs[i].setAttribute("mode", "resource");
			allInputs[i].setAttribute("recid", recid);
			allInputs[i].onclick = function (){  window.open('searchRes.html?id='+id+'&t='+constRecType+'&ext='+extra+'&q='+recTitle+'', '','width=700, height=400'); };
		}
		
	}
	  
}
	
	
function drawTextArea(id, detail){  
	var sel = document.getElementById("sel"+id);
	var html ="";
	
	if (!isEmpty(detail)){
		for (i in detail){
			html += "<div class=\"inp-div\"><textarea name=\""+id+"\" id=\""+id+"\">"+detail[i]+"</textarea></div>"; 
		}
		
	} else { 
		html += "<div class=\"inp-div\"><textarea name=\""+id+"\" id=\""+id+"\"></textarea></div>"; 
	}
	
	sel.innerHTML += html;
	
}
	
function drawRest(id, text){
	var html = "<input disabled name=\""+id+"\" id=\""+id+"\" type=text class=\"text-input\" value=\""+text+"\">"; 
	var sel = document.getElementById("sel"+id);
	
	sel.innerHTML = html;
	
}
	
	
	
function drawEnums(id, detail){
	var enums =[];
	var sel = document.getElementById("sel"+id);
	enums = HDetailManager.getDetailTypeById(id).getEnumerationValues();
	
	if (!isEmpty(detail)){
		for (d in detail){
	  		var recSelect = document.createElement("select");
	  		recSelect.id = id;
	  		recSelect.name = id;
	  		recSelect.setAttribute("mode", "enum");
	
			for (var i in enums) {
				var opt = document.createElement("option");
				opt.value = enums[i];
				opt.innerHTML = enums[i];
				if (detail[d].toLowerCase() == enums[i].toLowerCase()){
					opt.selected = true;
				}
				recSelect.appendChild(opt);
			}
			
			sel.appendChild(recSelect);
		}
		
	} else {
		var recSelect = document.createElement("select");
		recSelect.id = id;
		recSelect.name = id;
		recSelect.setAttribute("mode", "enum");
	 	var opt = document.createElement("option");
		opt.disabled = false;
		recSelect.appendChild(opt);
		for (var i in enums) {
			opt = document.createElement("option");
			opt.value = enums[i];
			opt.innerHTML = enums[i];
				if (detail == enums[i]) {
				 	opt.selected = true;
				}
				recSelect.appendChild(opt);
		}
		
		sel.appendChild(recSelect);
	}
	
}
		
function drawBool(id, detail){
	var sel = document.getElementById("sel"+id);
	var vals = new Array("true", "false");
	
	if (!isEmpty(detail)) {
		
		for (d in detail) {
			var recSelect = document.createElement("select");
			recSelect.setAttribute("mode", "boolean");
			recSelect.id = id;
			recSelect.name = id;
			
			var opt = document.createElement("option");
			opt.disabled = false;
			recSelect.appendChild(opt);
			
			for (var i in vals) {
				opt = document.createElement("option");
				var optText ="";
				if (vals[i] == "true") optText = "yes";
				if (vals[i] == "false") optText = "no";
				opt.value = vals[i];
				opt.innerHTML = optText;
				
				if (detail[d] == vals[i]){
					opt.selected = true;
				}
				recSelect.appendChild(opt);
			}
			sel.appendChild(recSelect);
	  }
	  
	} else {
		
		var recSelect = document.createElement("select");
	 	recSelect.id = id;
	 	recSelect.name = id;
	 	recSelect.setAttribute("mode", "boolean");
	 	var opt = document.createElement("option");
		opt.disabled = false;
		recSelect.appendChild(opt);
			
		for (var i in vals) {
			opt = document.createElement("option");
			var optText ="";
			if (vals[i] == "true") optText = "yes";
			if (vals[i] == "false") optText = "no";
			opt.value = vals[i];
			opt.innerHTML = optText;
			recSelect.appendChild(opt);
		}
		sel.appendChild(recSelect);
	}
	
}
	
function generateNewField(fields, id){
	var thisfield = fields[parseInt(fields.length-1)];
	
	if (thisfield.getAttribute("extra"))
		var extra = parseInt(thisfield.getAttribute("extra"))+1;
	else 
		var extra = 1;
		   
	var type = thisfield.type;
	var constRecType ="";
	
	if (HDetailManager.getDetailTypeById(id).getConstrainedRecordType())constRecType = HDetailManager.getDetailTypeById(id).getConstrainedRecordType().getID(); 
	
	var q = "";
	var newField;
	
	switch (type){
	case "text":
		newField = document.createElement("input"); 
		newField.type = type;
		newField.className = thisfield.className;
		
		var div = thisfield.parentNode;
		var divClass = div.getAttribute("class");
		var newdiv =document.createElement("div");
		newdiv.setAttribute("class", divClass);
		newField.setAttribute("extra", extra); 
		newField.id = id;
		newField.name = id;
		newdiv.appendChild(newField);
			
		if (thisfield.getAttribute("mode") == "resource") { 	//resource pointers
			var im = document.createElement("img");
			 im.src = path+"/img/cross.gif"; 
			 im.onclick = function () { removeIt(id,extra); }
			 newdiv.appendChild(im);
			 div.parentNode.appendChild(newdiv);
			 drawResourceDetails(null, id, extra, constRecType);
		} else {
			 div.parentNode.appendChild(newdiv);
		}
		break;
		   
	case "file":
		drawFile(id, "");
		break;
		   
	case "hidden": 		//for files and geo objects
		if (thisfield.value == "geo")
			drawGeo(id, "");
		if (thisfield.value == "file")
			drawFile(id, "");
		break;
		   
	case "select-one": 	//dropdowns
		if (thisfield.getAttribute("mode") == "boolean")
			drawBool(id, "");
		if (thisfield.getAttribute("mode") == "enum")
			drawEnums(id, "");
		break;
		   
	case "textarea":
		drawTextArea(id, "");
		break;
		   
	default:
		newField = document.createElement(type); 
		break;
	}
		
}

function createOnclickEvent (id, replink){ 
	var fields = document.getElementsByName(id);//build a new field based on current attributes
	
	replink.onclick = function () { 
		generateNewField(fields, id);
	};
	
}
	
function drawRepeats(){
	var replinks = document.getElementsByTagName("img");
	
	for (i=0; i<replinks.length; i++){
		if (replinks[i].id.toString().match("img")) {
			var replink = replinks[i].id.toString().split("img");
	  		createOnclickEvent(replink[1], replinks[i]);
	  	}
	}
	
}
	

function uploadFile(id){
	var files = document.getElementsByName(id);
	
	for (f in files){
		if (files[f].type == "file") var file = files[f];
	}
	
	var fileCallback = function(fInput, hFile) {
		var record = HeuristScholarDB.getRecord(rec_id); 
		record.addDetail(HDetailManager.getDetailTypeById(id), hFile);
		HeuristScholarDB.saveRecord(record, new HSaver(recordCallback, fileUploadErrorCallback));
	};
	var recordCallback = function(record) {
		var detail = record.getDetails(HDetailManager.getDetailTypeById(id));
		document.getElementById("sel"+id).innerHTML= "";
		drawFile(id, detail); 
	};
	
	var fileUploadErrorCallback = function(record, error) {
		console.log(error);
	};
	HeuristScholarDB.saveFile(file, new HSaver(fileCallback, fileUploadErrorCallback)); 
	
}
	
function remFile(id, fid){
	if (HeuristScholarDB.getRecord(rec_id)) record = HeuristScholarDB.getRecord(rec_id); 
		
	var details = record.getDetails(HDetailManager.getDetailTypeById(id));
	record.removeDetails(HDetailManager.getDetailTypeById(id));
	for (d in details){
		if (details[d].getID() != fid){
			record.addDetail(HDetailManager.getDetailTypeById(id), details[d]);
		}
	}
	
	removeIt(id, fid, true); 
	
}
	
	
	
function remGeo(id, geo){
	if (HeuristScholarDB.getRecord(rec_id)) record = HeuristScholarDB.getRecord(rec_id); 
		
	var details = record.getDetails(HDetailManager.getDetailTypeById(id));
	record.removeDetails(HDetailManager.getDetailTypeById(id));
	for (d in details){
		if (details[d].getWKT() != geo){
			record.addDetail(HDetailManager.getDetailTypeById(id), details[d]);
		}
	}
	
	removeIt(id, geo, true); 
	
}

function preSaveRecord(record){
	var recType = record.getRecordType();
	var detTypes = HDetailManager.getDetailTypesForRecordType(recType);
	var url= document.getElementById("url-text").value;
	if (url) record.setURL(url);
		
	for (d in detTypes){
		var detailVariety = detTypes[d].getVariety();
		var fields = document.getElementsByName(detTypes[d].getID());
		  
		if (fields.length === 0) continue;
		  
		for (f=0; f<fields.length; f++) {
			if (fields[f].value != "file") { //skip removal of files, remove all other details
				if (fields[f].value || fields[f].value=="" || fields[f].value=="geo") 
				record.removeDetails(detTypes[d]);
			}
		}
		   
		for (f=0; f<fields.length; f++) {
			
			if (fields[f].value) {
				switch (detailVariety) {
				case "literal":
				case "blocktext":
					record.addDetail(detTypes[d], fields[f].value);
		    		break;
			  
				case "boolean":
					if (fields[f].value == "false"){
						record.addDetail(detTypes[d], 0); 
			   		} else {
		       			record.addDetail(detTypes[d], 1); 
			   		}
		      		break;
				
		    	case "enumeration":
					var enums = detTypes[d].getEnumerationValues();
		      		for (n in enums){
		      			if (fields[f].value == enums[n]) {
		       				record.addDetail(detTypes[d], fields[f].value);
						}
					}
			  		break;
			  
				case "reference":
		    		record.addDetail(detTypes[d], HeuristScholarDB.getRecord(fields[f].getAttribute("recid")));
		      		break;
			  
				case "date":
			  		var theDate= fields[f].value.replace(/-/g, ",");
			  		record.addDetail(detTypes[d], new Date(theDate)); //yyyy, mm, dd
		      		break;
			  
				case "geographic":
					if (fields[f].getAttribute("extra")){
						var geoFields = fields[f].getAttribute("extra").split("(");
			  			var geoType = stripSpace(geoFields[0].toLowerCase());
			  			if (geoType == "linestring") {
							geoType = "circle";
						}
			  			var geo = new HGeographicValue(HGeographicType.abbreviationForType(geoType),fields[f].getAttribute("extra")); 
			  			record.addDetail(detTypes[d], geo); 
					}
					break;
				}
			}
		}
	}
}
	
function saveRecord(record) {
	preSaveRecord(record); 
	var saver = new HSaver(
		function(r) {
			alert ("Record saved");
			
			if (document.referrer.match("searchRes.html")){
				window.opener.location = document.referrer + "sortby:-m"; 
			}

			if (window.opener.recordSaved) {
				window.opener.recordSaved(r.getID());
			}

			window.close();
		},
		
		function(r,e) { alert("Record save failed: " + e); }
	);
	HeuristScholarDB.saveRecord(record, saver);
	
}

function autoSaveRecord(record) {
	preSaveRecord(record); 
	var saver = new HSaver(
		function(r) {
			displaySavedDetails(); 
		},
		
		function(r,e) { 
			displaySavedDetails(e); 
		}
	);
	HeuristScholarDB.saveRecord(record, saver);
}


function displaySavedDetails(error){
	var today = new Date();
	var container = document.getElementById("auto-save-span");
	container.innerHTML = "";
	if (!error){
		container.setAttribute("style", "background-color: #FBEFC0");
		container.appendChild(document.createTextNode("Record saved " + today.getHours() + ":" + today.getMinutes()));
	} else {
		container.setAttribute("style", "background-color: red");
		container.appendChild(document.createTextNode("Could not save record: " + error  + " " + today.getHours() + ":" + today.getMinutes()));
	}
}

function setAutoSaveTimeout(record){
	this.veryRecord = record; //need this because otherwise it will always pass the empty HRecord instantiated at the begining of this file.
	window.setInterval("autoSaveRecord(this.veryRecord)", 120000);
}
	
function geoDetail(geo){
	var geoType = geo.getType();
	
	switch (geoType) {
	case "point":
	case "circle":
		return "<b>"+geoType+"</b>" + " X (" +Math.round(geo.getX()) +") - Y ("+ Math.round(geo.getY())+ ") ";
		break;
				
	case "bounds":
		return "<b>rectangle</b>"+ " X (" +Math.round(geo.getX0())+","+Math.round(geo.getX1()) +") - Y ("+ Math.round(geo.getY0())+","+Math.round(geo.getY1())+ ") ";
		break;
				
	case "polygon":
	case "path":
		var string = "<b>"+geoType+"</b>"; 
		var points = geo.getPoints(); 
		string += " X (";
		for (p in points){
			string += Math.round(points[p][0]);
			if (p != points.length -1)  string +=",";
		}
		string += ") - Y (";
		for (p in points){
			string += Math.round(points[p][1]);
			if (p != points.length -1)  string +=",";
		}
		string += ")";
		return  string;
		break;
	}
	
}
