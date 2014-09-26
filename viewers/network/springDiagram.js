/** Displays the diagram URL */
function getDiagramUrl() {
   top.HEURIST.util.popupURL(this, '../viewers/network/diagramUrl.html', null);
}

/** Displays the diagram embed code */
function getDiagramCode() {
   top.HEURIST.util.popupURL(this, '../viewers/network/diagramCode.html', null);
}

/** Constructs an URL that shows the results independently */
function getCustomURL() {
    // Get search data
    var data = parseRecSet();
    console.log(data);
    
    // Construct ID's
    var ids = [];
    for(var key in data.nodes) {
        ids.push(data.nodes[key].id);
    }
    console.log("IDS:");
    console.log(ids);
    
    // Convert data to JSON
    var json = JSON.stringify(ids);
    console.log("JSON: " + json);

    // Construct url
    var url = top.HEURIST.baseURL + "/viewers/network/springDiagram.php" +
                                    "?db=" + top.HEURIST.database.name + 
                                    "&ids=" + json;
    console.log("URL: " + url);
    return url;
}

/** Gets the selected IDs from top.HEURIST.search */
function getSelectedIDs() {
    var selectedIDs = [];
    var recIDs = top.HEURIST.search.getSelectedRecIDs(); 
    if(recIDs) {
        for(var key in recIDs) {
            if(!isNaN(key)) {
                selectedIDs.push(recIDs[key]);       
            }
        }
    }
    console.log("SELECTED IDs");
    console.log(selectedIDs);
    return selectedIDs;
}

/** Parses a recSet into usable data */
function parseRecSet() {
    var selectedIDs = getSelectedIDs();
    var results = top.HEURIST.search.results;
    var nodes = {};
    var links = [];
            
    // Building nodes
    for(var id in results.recSet) {
        // Get details
        var record = results.recSet[id].record;
        var depth = results.recSet[id].depth;
        var name = record["5"];  
        var group = record["4"];
        var image = top.HEURIST.iconBaseURL + group + ".png";
        var selected = selectedIDs.indexOf(id.toString()) > -1;

        // Construct node
        var node = {id: parseInt(id), name: name, image: image, count: 1, depth: depth, selected: selected};
        nodes[id] = node;    
        console.log("Node #" + id);    
    }
    
    
    /**
    * Finds links in a revPtrLinks or revRelLinks object
    */
    function findLinks(source, object) {
        var recIDs = object.byRecIDs;
        for(var recID in recIDs) {
            //console.log("ID " +id+ " points to recID: " + recID);
            var target = nodes[recID];
            if(target !== undefined) {
                var ids = recIDs[recID];
                //console.log("RELATION ID's");
                //console.log(ids);
                if(ids !== undefined && ids.length > 0) {
                    for(var i = 0; i < ids.length; i++) {
                        // Define relation    
                        console.log("Relation #" + i + " ID: " + ids[i]);        
                        var relation = nodes[ids[i]];
                        if(relation === undefined) {
                            relation = {id: ids[i], name: "Unknown", image: "unknown.png", count: 1};
                        }
                        
                        // Construct a link
                        var link = {source: source, relation: relation, target: target};
                        //console.log("LINK");
                        //console.log(link);   
                        links.push(link);  
                    } 
                }  
            }
        }
    }

    // Go through all records
    for(var id in results.recSet) {
        //console.log("RecSet["+id+"]:");
        //console.log(results.recSet[id]);
        var source = nodes[id];
        
        // Determine links
        if(source !== undefined) {
             // Get ptrLinks
            var ptr = results.recSet[id].ptrLinks;
            //console.log("Ptr for recSet["+id+"]");
            //console.log(ptr);
            if(source !== undefined && ptr !== undefined) {
                findLinks(source, ptr);
            }

            // Get relLinks
            var rel = results.recSet[id].relLinks;
            //console.log("Rel for recSet["+id+"]");
            //console.log(ptr);
            if(rel !== undefined) {
                findLinks(source, rel);
            }
        }
    }
    
    // Construct data object
    var data = {nodes: nodes, links: links};
    console.log("DATA");
    console.log(data);
    return data;
}

/** Calls the visualisation plugin */
function visualize(data) {
    // Custom data parsing
    function getData(data) {
        console.log("Custom getData() call");
        return data;
    }
    
    // Calculates the line length
    function getLineLength(record) {
        var length = getSetting(setting_linelength);
        if(record !== undefined && record.hasOwnProperty("depth")) {
            length = length / (record.depth+1);
        }
        return length;
    }
    
    // Call plugin
    console.log("Calling plugin!");
    $("#visualisation").visualize({
        data: data,
        getData: function(data) { return getData(data); },
        getLineLength: function(record) { return getLineLength(record); },
        
        showEntitySettings: false,
        showLineWidth: false,
        showFormula: false
    });  
}


