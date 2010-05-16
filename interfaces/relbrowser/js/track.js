
// PJ functions for Map bread crumbs
function saveAndLoad(cValue) {
 	HAPI.PJ.retrieve(_nameTrack, function (name, value){
    	var newVals = [];
	  	if (!value || value == null){
	   		newVals.push(cValue);
	  	} else { //determine the last value, only new value if different to the previous one, to avoid duplicates
	   		var lastVal= value[value.length-1];
	     	if (lastVal.recId !== cValue.recId) {
	       		value.push(cValue);
			} 
		   	newVals = value;
	  	}
		
     	HAPI.PJ.store (_nameTrack, newVals);
		initTMap();
  	});
}


function gatherCrumbs(_nameTrack, value, currentId) {
		if (value && value.length >= 1) {
			var placeHolder = document.getElementById("track-placeholder");
			placeHolder.innerHTML = ""; //reset
			var header = document.createElement("div");
			header.id = "track-header";
			header.innerHTML = "My Map Track";
			placeHolder.appendChild(header);
			
			var div = document.createElement("div");
			div.id = "track";
			
			value.reverse();
			var i,lastCrumb;
			value[0].recId == currentId? lastCrumb = parseInt(maptrackCrumbNumber + 1) : lastCrumb = maptrackCrumbNumber;
				
			if (value.length > lastCrumb) { //a bit of housekeeping to prevent session from overloading
				 value.pop();
				 value.reverse();
				 HAPI.PJ.store (_nameTrack, value);
				 value.reverse();
			}
			
			for ((value[0].recId == currentId? i=1 : i=0); i < value.length; ++i){ 
					var crumb = value[i];
					var a = document.createElement("a");
					a.href="../" + crumb.recId;				
					a.style.paddingBottom = "10px";
					a.style.textDecoration = "none";
					var t = document.createTextNode(crumb.recTitle);
					a.appendChild(t);
					
					//only display colour square only if there is geographic data associated with the record,
					//since this is when it will be displayed on the timeline, not in the case when the record is an aggregation of records
					if (crumb.hasGeoData && crumb.recType != "103") {
						if (crumb.hasGeoData.indexOf("l") != -1){
							var spanchik = document.createElement("span");
							spanchik.style.backgroundColor = crumbThemes[(value[0].recId == currentId)?i-1:i].colour;
							spanchik.style.paddingLeft = "5px";
							spanchik.style.paddingRight = "10px";
							a.style.paddingLeft = "5px";
							div.appendChild(spanchik);
						}
						if (crumb.hasGeoData.indexOf("p") != -1){
							var imgik = document.createElement("img");
							imgik.src= crumbThemes[(value[0].recId == currentId)?i-1:i].icon;
							imgik.height = "15";
							div.appendChild(imgik);
						}
					}
				
					div.appendChild(a);
					div.appendChild(document.createElement("br"));
					div.appendChild(document.createElement("br"));
				
			}
				
			var inp = document.createElement("input");
			inp.type = "button";
			inp.value  = "clear history";
			inp.onclick = function() {
				cleanUpCrumbs();
			}
			div.appendChild(inp);		
			placeHolder.appendChild(div);
		}
		
}

function cleanUpCrumbs(){
	document.getElementById("track-placeholder").innerHTML ="";
	HAPI.PJ.store (_nameTrack, null);
	dataSets = reviseDatasets(dataSets); //remove all breadcrumbs from datasets
	loadSavedView();
}

function reviseDatasets(dataSets){
	var i, dataSet;
	var revisedSet = [];
	for (i = 0; i < dataSets.length; ++i){
		dataSet = dataSets[i];
		if (!dataSet.title.match(/Breadcrumb/)){
			revisedSet.push(dataSet);
		}
	}
	return revisedSet;
}


