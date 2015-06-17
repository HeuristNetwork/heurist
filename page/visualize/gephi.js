 // Functions to download the displayed nodes in GEPHI format.
 
/**
* Returns a URL parameter
* @param name Name of the parameter
*/
function getParameterByName(name) {
    name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
    var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
        results = regex.exec(location.search);
    return results == null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
}

/**
* Returns the database name from the search query string
*/
function getDatabaseName() {
    return getParameterByName("db");
}

/** Transforms the visualisation into Gephi format */
function getGephiFormat() {
    // Get data
    var data = settings.getData.call(this, settings.data);
    console.log("GEPHI DATA");
    console.log(data);
    
    // META
    var gexf = '<?xml version="1.0" encoding="UTF-8"?>';
    gexf +=      '<gexf xmlns="http://www.gexf.net/1.2draft" version="1.2">';
    gexf +=        '<meta lastmodifieddate="2014-10-10">';
    gexf +=          '<creator>HeuristNetwork.org</creator>';
    gexf +=          '<description>Visualisation export</description>';
    gexf +=        '</meta>' ;
    gexf +=        '<graph mode="static" defaultedgetype="directed">';
    
    // NODE ATTRIBUTES
    gexf += '<attributes class="node">';
    gexf +=     '<attribute id="0" title="name" type="string"/>';
    gexf +=     '<attribute id="1" title="image" type="string"/>';
    gexf +=     '<attribute id="2" title="count" type="float"/>';
    gexf += '</attributes>';
    
    // EDGE ATTRIBUTES
    gexf += '<attributes class="edge">';
    gexf +=     '<attribute id="0" title="relation-id" type="float"/>';
    gexf +=     '<attribute id="1" title="relation-name" type="string"/>';
    gexf +=     '<attribute id="2" title="relation-image" type="string"/>';
    gexf +=     '<attribute id="3" title="relation-count" type="float"/>';
    gexf += '</attributes>';
     
    // NODES
    gexf += '<nodes>';
    for(var key in data.nodes) {
        var node = data.nodes[key];
        gexf += '<node id="'+node.id+'" label="'+node.name+'">';
        gexf +=     '<attvalues>';
        gexf +=         '<attvalue for="0" value="'+node.name+'"/>';
        gexf +=         '<attvalue for="1" value="'+node.image+'"/>';
        gexf +=         '<attvalue for="2" value="'+node.count+'"/>';
        gexf +=     '</attvalues>'; 
        gexf += '</node>';
    }
    gexf += '</nodes>';
    
    // EDGES
    gexf += '<edges>';
    for(var i = 0; i < data.links.length; i++) {
        var edge = data.links[i]; 
        gexf += '<edge id="'+i+'" source="'+edge.source.id+'" target="'+edge.target.id+'" weight="'+edge.targetcount+'">';
        gexf +=     '<attvalues>';  
        gexf +=         '<attvalue for="0" value="'+edge.relation.id+'"/>';      
        gexf +=         '<attvalue for="1" value="'+edge.relation.name+'"/>';
        gexf +=         '<attvalue for="2" value="'+edge.relation.image+'"/>';
        gexf +=         '<attvalue for="3" value="'+edge.targetcount+'"/>';
        gexf +=     '</attvalues>'; 
        gexf += '</edge>';
    }
    gexf += '</edges>';
    
    // COMPLETE
    gexf +=         '</graph>';
    gexf +=       '</gexf>';
    
    // DOWNLOAD
    var pom = document.createElement('a');
    pom.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(gexf));
    pom.setAttribute('download', getDatabaseName()+".gexf");
    pom.click();
}