/** D3 */
var data = [
    {
        name: 'City',
        columns: [
            'id',
            'country_id',
            'name',
            'population'
        ]
    },
    {
        name: 'Country',
        columns: [
            'id',
            'continent_id',
            'name',
            'population',
            'gpd',
            'average_salary',
            'birth_rate'
        ]
    },
    {
        name: 'Continent',
        columns: [
            'id',
            'name',
            'population'
        ]
    }
];
console.log(data);                    

/** SVG details */
var fieldHeight = 30;
var fieldWidth = 150;
var offset = 300;
var svg = d3.select("svg");

/**
* Attaches drag detection to an element
*/
var drag = d3.behavior.drag()
             .origin(function() { 
                var t = d3.select(this);
                return {x: t.attr("x"), y: t.attr("y")};
             })
             .on("drag", dragMove);

/**
* Called when an element has been dragged
*/
function dragMove(d) {
    console.log(d3.event);
    var x = d3.event.x;
    var y = d3.event.y;
    
    d3.select(this)
      .attr("x", x)
      .attr("y", y)
      .attr("transform", "translate(" +x+ ", " +y+ ")");
}

/**
* Creates a 'g' element for each table
*/
var tables = svg.selectAll("g")
                .data(data)
                .enter()
                .append("g")
                .attr("class", "table")
                .attr("x", function(d, i) { 
                    console.log(offset);
                    return (offset + i*fieldWidth*1.05);
                })
                .attr("y", 0)
                .attr("transform", function(d, i) {
                    console.log(offset);
                    return "translate(" + (offset + i*fieldWidth*1.1) + ", 0)"; 
                })
                .call(drag);

/**
* Creates a 'g' element for each cell inside the table
*/
var cells = tables.selectAll("g")
                  .data(function(d) {
                        return d3.values(d.columns);
                    })
                  .enter()
                  .append("g")
                  .attr("class", "cell")
                  .attr("transform", function(d, i) {
                        return "translate(0, " + i*fieldHeight + ")"; 
                  });
/**
* Creates a 'rect' element inside a cell
*/
cells.append("rect")
     .attr("class", "empty")
     .attr("width", fieldWidth-1)
     .attr("height", fieldHeight-1);

/**
* Creates a 'text' element inside a cell
*/
cells.append("text")
     .text(String)
     .attr("class", "center")
     .attr("x", fieldWidth/2)
     .attr("y", fieldHeight/2);




console.log("Done");