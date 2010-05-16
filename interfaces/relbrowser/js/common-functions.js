var path = window.opener.HAPI.instance ? "http://" + window.opener.HAPI.instance + ".heuristscholar.org/heurist/" : "http://heuristscholar.org/heurist/";

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
	var tR = document.createElement("tr");
	var tD = document.createElement("td");
	var tD2 = document.createElement("td");
	var tD3 = document.createElement("td");
	var tD4 = document.createElement("td");

	tD.id = "sub-res-type";
	tD.setAttribute("class", reqClass);
	tD.innerHTML = detName;

	tD2.setAttribute("class", "rep");
	if (isRepeatable)
		tD2.innerHTML = "<img id= \"img"+detailId +"\" src=\""+path+"/img/duplicate.gif\" border=0>";

	tD3.setAttribute("class", reqClass);
	if(detPrompt)
		tD3.innerHTML = "<div class=pad id=sel"+detailId+"></div><div class=hint>"+detPrompt+"</div>";
	else {
		detPrompt = " ";
		tD3.innerHTML = "<div class=pad id=sel"+detailId+"></div><div class=hint> "+detPrompt+" </div>";
	}

	tD4.setAttribute("class", reqClass);
	tD4.innerHTML = "<div id=lookup"+detailId+"></div>";

	tR.appendChild(tD);
	tR.appendChild(tD2);
	tR.appendChild(tD3);
	tR.appendChild(tD4);

	return tR;

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
	var tR = document.createElement("tr");
	var tD = document.createElement("td");
	var tD2 = document.createElement("td");
	var tD3 = document.createElement("td");
	var tD4 = document.createElement("td");

	tD.id = "sub-res-type";
	tD.innerHTML = "URL";
	tD2.setAttribute("class", "rep");
	if (Url)
	tD3.innerHTML ="<div class=pad-bottom><input type=\"text\" id=\"url-text\" value=\""+Url+"\"></div>";
	else
	tD3.innerHTML ="<div class=pad-bottom><input type=\"text\" id=\"url-text\" value=\"\"></div>";
	tD4.innerHTML = "<div></div>";

	tR.appendChild(tD);
	tR.appendChild(tD2);
	tR.appendChild(tD3);
	tR.appendChild(tD4);

	return tR;
}

function drawFile(id, detail){
	var sel = document.getElementById("sel"+id);
	var html ="";

	if (!isEmpty(detail)) { //files
		for (i in detail) {
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

function drawFileForAdding(id, detail){
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
	 }else{ //upload box

	  html += "<div class=\"inp-div\"><input type=file  name=\""+id+"\" id=\""+id+"\" value=\"\" extra=\"\" onchange=\"uploadFileForAdding("+id+");\"></div>";
	 }
	  sel.innerHTML += html;
	}

	function drawInputField(id, detail){
	  var sel = document.getElementById("sel"+id);
	  var html ="";
		if (!isEmpty(detail)){
		  for (i in detail){
			html += "<div class=\"inp-div\"><input type=text  name=\""+id+"\" id=\""+id+"\" value=\""+detail[i]+"\"></div>";
		  }
		}else{
		  html += "<div class=\"inp-div\"><input type=text  name=\""+id+"\" id=\""+id+"\" value=\"\"></div>";
		}
	  sel.innerHTML += html;
	}

function drawDateField(id, detail){
	var sel = document.getElementById("sel"+id);
	var html ="";
	if (!isEmpty(detail)){
	  for (i in detail){

		html += "<div class=\"inp-div\"><input type=text  name=\""+id+"\" id=\""+id+"\" value=\""+detail[i]+"\">&nbsp<img src=\""+path+"/img/calendar.gif\" id=\"calendar"+id+"\" align = \"absbottom\" onclick=\"window.open('calendar.html?id="+id+"','mywin','width=300, height=150, resizable=no');\"><div class=\"hint\">Note:The date should be in yyyy-mm-dd format optionally followed by space then hh:mm:ss, where ss is also optional. Date in format yyyy, mm-yyyy or yyyy-mm will aslo be accepted but without time string.</div></div>";
	  }
	}else{
	  html += "<div class=\"inp-div\"><input type=text  name=\""+id+"\" id=\""+id+"\" value=\"\">&nbsp<img src=\""+path+"/img/calendar.gif\" id=\"calendar"+id+"\" align = \"absbottom\" onclick=\"window.open('calendar.html?id="+id+"','mywin','width=300, height=150, resizable=no');\"><div class=\"hint\">Note:The date should be in yyyy-mm-dd format optionally followed by space then hh:mm:ss, where ss is also optional. Date in format yyyy, mm-yyyy or yyyy-mm will aslo be accepted but without time string.</div></div>";
	}
	sel.innerHTML += html;
}

   function drawResource(id, detail, constRecType,source_entity){
	var sel = document.getElementById("sel"+id);
	var html = "";
	if (!isEmpty(detail)){
	  html = ""; //reset
	  var count=0;
	  for (z in detail){
	   count ++;
	  html +=  "<div class=\"inp-div\"><input class=\"resource\" style=\"cursor:pointer\" mode=resource type=text recid=\"\" extra="+count+" id=\""+id+"\" name=\""+id+"\" value=\"\" ><img src=\""+path+"/img/cross.gif\" onclick=\"removeIt("+id+","+count+");\"></div>";
	  doRecSearch(detail[z].getID(), "resource", id, count, constRecType);
	  }
	  sel.innerHTML += html;
	 }
	 else if(!isEmpty(source_entity) && (id==528 || id==152)){ //this is specific for adding source reference to a factoid recordtype
	  html = ""; //reset
	  var count=0;
	  html +=  "<div class=\"inp-div\"><input class=\"resource\" style=\"cursor:pointer\" mode=resource type=text recid=\"\" extra="+count+" id=\""+id+"\" name=\""+id+"\" value=\"\" ><img src=\""+path+"/img/cross.gif\" onclick=\"removeIt("+id+","+count+");\"></div>";
	  doRecSearch(source_entity, "resource", id, count, constRecType);

	  sel.innerHTML += html;
	 }
	 else{
	 html += "<div class=\"inp-div\"><input class=\"resource\" style=\"cursor:pointer\" mode=resource type=text  extra=1 name=\""+id+"\" id=\""+id+"\" value=\"\" onclick=\"window.open('searchRes.html?id="+id+"&t="+constRecType+"&ext=1&q=', 'mywin','width=700, height=400, resizable=yes'); \" onchange=\"onchangeResource("+id+",1);\"><img src=\""+path+"/img/cross.gif\" onclick=\"removeIt("+id+",1);\"></div>";
	 sel.innerHTML += html;
	 //drawResourceDetails(null, id, 1, constRecType); // FAILS MISERABLY> WHY??? this would have been a tidier way of doing it.
	 }
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

function onchangeResource(id, extra){
	var elts = document.getElementsByName(id);
	for (l = 0;  l<elts.length; l++ ){
		if (elts[l].getAttribute("extra") == extra){
			doRecSearch(elts[l].getAttribute("recid"), "resource", id);
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
			allInputs[i].onclick = function (){  window.open('searchRes.html?id='+id+'&t='+constRecType+'&ext='+extra+'&q='+recTitle+'', 'mywin','width=700, height=400'); };
			allInputs[i].onchange = function() {
		 		var elts = document.getElementsByName(id);
		  		for (l = 0;  l<elts.length; l++ ){
		  			if (elts[l].getAttribute("extra") == extra){
						doRecSearch(elts[l].getAttribute("recid"), "resource", id);
		  			}
		 		}
			}
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
	var html = "<input disabled name=\""+id+"\" id=\""+id+"\" type=text value=\""+text+"\">";
	var sel = document.getElementById("sel"+id);
	sel.innerHTML = html;
}



function drawEnums(id, detail){
	  var enums =[];
	  enums = HDetailManager.getDetailTypeById(id).getEnumerationValues();
	  var sel = document.getElementById("sel"+id);

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
				if (detail[d] == enums[i]){
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
				if (detail == enums[i]){
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
	if (!isEmpty(detail)){
		for (d in detail){
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
			if (i==0)
			opt.selected = true;
			recSelect.appendChild(opt);
		}
		sel.appendChild(recSelect);
	}
}

function generateNewField(fields, id){
	var thisfield = fields[parseInt(fields.length-1)];
	if (thisfield.getAttribute("extra")){
		  var extra = parseInt(thisfield.getAttribute("extra"))+1;
	} else {
		  var extra = 1;
	}

	var type = thisfield.type;
	var constRecType ="";
	if (HDetailManager.getDetailTypeById(id).getConstrainedRecordType())
	constRecType = HDetailManager.getDetailTypeById(id).getConstrainedRecordType().getID();
	var q = "";
	var newField;
	switch (type){
		case "text":
			newField = document.createElement("input");
			newField.type = type;
			var div = thisfield.parentNode;
			var divClass = div.getAttribute("class");
			var newdiv =document.createElement("div");
			newdiv.setAttribute("class", divClass);
			newField.setAttribute("extra", extra);
			newField.id = id;
			newField.name = id;
			newdiv.appendChild(newField);
			if (thisfield.getAttribute("mode")== "resource"){ //resource pointers
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

		case "hidden": //for files and geo objects
			if (thisfield.value =="geo")
				drawGeo(id, "");
		   	if (thisfield.value =="file")
				drawFile(id, "");
		   	break;

		case "select-one": //dropdowns
			if (thisfield.getAttribute("mode")=="boolean")
				drawBool(id, "");
			if (thisfield.getAttribute("mode")=="enum")
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
	  	if (replinks[i].id.toString().match("img")){
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

function uploadFileForAdding(id){
	var files = document.getElementsByName(id);
	for (f in files){
	 if (files[f].type == "file") var file = files[f];
	}
	var fileCallback = function(fInput, hFile) {
	   record.addDetail(HDetailManager.getDetailTypeById(id), hFile);
	   var detail = record.getDetails(HDetailManager.getDetailTypeById(id));
	   document.getElementById("sel"+id).innerHTML= "";
	   drawFileForAdding(id, detail);
		};
	 var recordCallback = function(record) {
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


function saveRecord(record) {
	var recType = record.getRecordType();
	var detTypes = HDetailManager.getDetailTypesForRecordType(recType);

	for (d in detTypes){
		var detailVariety = detTypes[d].getVariety();
		var fields = document.getElementsByName(detTypes[d].getID());

		// **** customize the form
		//because v2 of HAPI does not distinguish boolean from literal, set detVariety "boolean" when the fields are "fictional" and "exists"
		if(detTypes[d].getID()==524 || detTypes[d].getID()==525) {
			detailVariety = "boolean";
		}

		if (fields.length === 0) continue;

		for (f=0; f<fields.length; f++){
		  	if (fields[f].value != "file"){ //skip removal of files, remove all other details
		  		if (fields[f].value || fields[f].value=="" || fields[f].value=="geo")
					record.removeDetails(detTypes[d]);
		  	}
		 }

		for (f=0; f<fields.length; f++){
			if (fields[f].value){

		 		switch(detailVariety){

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
		       			if (fields[f].value == enums[n]){
			       			record.addDetail(detTypes[d], fields[f].value);
			   			}
		      		}
			 		 break;

		      	case "reference":
		      		record.addDetail(detTypes[d], HeuristScholarDB.getRecord(fields[f].getAttribute("recid")));
		      		break;

			  	case "date":
				
					var cleanDate = parseDate(fields[f]);
					if (!cleanDate) { return; }
					console.log(cleanDate);
					record.addDetail(detTypes[d], cleanDate); //yyyy, mm, dd
		      		break;

			  	case "geographic":
			  		if (fields[f].getAttribute("extra")){
			  			var geoFields = fields[f].getAttribute("extra").split("(");
			  			var geoType = stripSpace(geoFields[0].toLowerCase());
			  			if (geoType == "linestring") geoType = "circle";
			  			var geo = new HGeographicValue(HGeographicType.abbreviationForType(geoType),fields[f].getAttribute("extra"));
			  			record.addDetail(detTypes[d], geo);
			  		}
		     		break;
		      	}
		     }
		}
	}

    var saver = new HSaver(
		function(r) {
			alert ("Record saved");
			if (rec_type_id == 150 || recType.getID() == 150){
				window.opener.location.reload(true);
			}
			if(window.opener.document.getElementById("results") ){
				window.opener.document.getElementById("search").value = "id:"+r.getID();
				eval(window.opener.document.getElementById("searchbutton").onclick());
				window.opener.document.getElementById("search").value = "";
			}
			if (window.opener.document.getElementById("linked-results") || window.opener.document.getElementById("image-results")) {
				if (window.opener.document.getElementById("active-search") == "image") {
					window.opener.document.getElementById("image-search").value = "id:"+r.getID();
					eval(window.opener.document.getElementById("image-search-form").onsubmit());
					window.opener.document.getElementById("image-search").value = "";
				} else {
					window.opener.document.getElementById("linked-search").value = "id:"+r.getID();
					eval(window.opener.document.getElementById("linked-search-form").onsubmit());
					window.opener.document.getElementById("linked-search").value = "";
				}
			}
			window.close();

		},
		function(r,e) {
			alert("Record save failed: " + e);
	});
	HeuristScholarDB.saveRecord(record, saver);
}

//very basic date validation functions.
//TODO: date/month recognition

function dirtyDate(input){
	var reg = new RegExp ("[^0-9-]\s:");
	return reg.test(input);
}

function parseDate(input){
 	if (dirtyDate(input.value)){
		alert ("Please only use numbers, dashes and colons in date fields.");
		return;
	} else {
	  	if (matchFullDate(input.value)) {
	  		//construct date object
			var theDate = input.value.replace(/-/g, ",");
			return new Date(theDate);
	  	} else {
			if (matchDate(input.value)) {
	  			//string
				return input.value;
	  		} else {
				alert ("Invalid date. The date should be in yyyy-mm-dd format optionally followed by space then hh:mm:ss, where ss is also optional. Date formats yyyy, mm-yyyy or yyyy-mm will aslo be accepted but without time string");
				return;
			}
		}
	}
}

function matchFullDate(input) {
	var date1 = new RegExp ("^\\d{4}-\\d{2}-\\d{2}((\\s|\T)\\d{2}[:]\\d{2}([:]\\d{2})?)?$"); //1234-12-12 (space or T 12:12 or 12:12:12)-optional

	if (date1.test(input)){
		return true;
	} else {
		return false;
	}
}

function matchDate(input) {
 	var date1 = new RegExp ("^\\d{2}[-]\\d{4}$");
 	var date2 = new RegExp ("^\\d{4}[-]\\d{2}$");
	var date3 = new RegExp ("^\\d{4}$");
 	if (date1.test(input) || date2.test(input) || date3.test(input)){
		return true;
 	} else {
		return false;
	}
}

