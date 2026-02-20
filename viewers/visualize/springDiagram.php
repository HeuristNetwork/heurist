<?php
/**
* springDiagram.php - Renders a search result set as a network diagram.
*
* @fileOverview This file is the main entry point for displaying a network visualization
* based on a Heurist search query. It initializes the page, fetches data if in standalone mode,
* and then uses the `visualize.js` plugin to render the graph. It also provides functions
* for parsing data into the required D3 format and for showing/updating the visualization.
* @project     Heurist academic knowledge management system
* @package  Viewers\Network
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Jan Jaap de Groot    <jjedegroot@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4
*/
define('PDIR','../../');//need for proper path to js and css
require_once dirname(__FILE__).'/../../hclient/framecontent/initPage.php';

$isMinimalVersion = intval(@$_REQUEST['mini']);
?>
        <style>
            body, html {
                background-color: #fff;
            }
        </style>

        <!-- D3 -->
        <script type="text/javascript" src="<?php echo PDIR;?>external/d3/d3.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/d3/fisheye.js"></script>

        <!-- Colpick -->
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/evol.colorpicker.js" charset="utf-8"></script>
        <link href="<?php echo PDIR;?>external/jquery.widgets/evol.colorpicker.css" rel="stylesheet" type="text/css">

        <!-- Visualize plugin -->
        <script type="text/javascript" src="<?php echo PDIR;?>viewers/visualize/settings.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>viewers/visualize/overlay.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>viewers/visualize/selection.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>viewers/visualize/gephi.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>viewers/visualize/drag.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>viewers/visualize/visualize.js"></script>

        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>viewers/visualize/visualize.css">

        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/jquery.ui-contextmenu.js"></script>

        <!-- Ensure required Heurist files are loaded (RecordSet and Symbology tools) -->
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/recordset.js"></script>

        <?php
        if($isMinimalVersion){
        ?>
        <script type="text/javascript" src="<?php echo PDIR;?>external/tinymce5/tinymce.min.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_color.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_exts.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing2.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>
        <?php } ?>

        <script type="text/javascript">

/**
 * Flag indicating if the visualization is running in standalone mode (i.e., not embedded in a Heurist page).
 * @type {boolean}
 */
var isStandAlone = false;

var currentRequest = null;

var graphLevels = []; // keep track of each graph level (for handling graph level expansions), beware manual expanding needs to be accounted for
var nodeRelationMapping = {}; // keep a list of related nodes, to simplify individual node expansions

/**
 * Callback function executed after the page's initial dependencies are loaded.
 * If successful and in standalone mode (query parameter 'q' is present),
 * it fetches record data and related records to build and display the visualization.
 *
 * @global
 * @param {boolean} success - Indicates whether the page initialization was successful.
 */
function onPageInit(success){

    if(!success) {return;}

        var q = window.hWin.HEURIST4.util.getUrlParameter('q', location.search);

        // Example query: t:26 f:85:3313  f:1:building
        // Perform database query if possible (for standalone mode - when springDiagram.php is a separate page)
        if( !window.hWin.HEURIST4.util.isempty(q) )
        {
            isStandAlone = true;

            performSearch(q);
        }
}

function performSearch(q){

    var rules = window.hWin.HEURIST4.util.getUrlParameter('rules', location.search);

    if(!window.hWin.HEURIST4.util.isempty(rules)){
        try{
            rules = JSON.parse(rules);
        }catch(ex){
            rules = null; // Handle potential JSON parsing errors
        }
    }else{
        rules = null;
    }

    var MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');

    let query = {q: q, rules: rules, w: 'a', detail: 'detail', l: MAXITEMS};

    window.hWin.HAPI4.RecordMgr.search(query, (response) => {

        if(response.status == window.hWin.ResponseStatus.OK){

            var recordset = new HRecordSet(response.data); // Assumes HRecordSet is available globally or via hWin

            var records_ids = recordset.getIds(MAXITEMS);
            if(records_ids.length <= 0){
                return;
            }

            window.hWin.HAPI4.RecordMgr.search_related({ids:records_ids.join(',')}, (response_related) => {

                if(response_related.status == window.hWin.ResponseStatus.OK){
                    // Store relationships
                    // Parse response to spring diagram format
                    var data = __parseData(records_ids, response_related.data);

                    showData(data, [], query, null, null, null);

                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response_related);
                }
            });

        }else{
            window.hWin.HEURIST4.msg.showMsgErr(response);
        }
    });
}

function expandNode(rec_ID){

    const currentQuery = currentRequest.q;
    let parts = typeof currentQuery === 'string' ? currentQuery.split(':') : [];
    if(!Object.hasOwn(nodeRelationMapping, rec_ID) || parts.length > 2 || parts[0] !== 'ids'){
        return;
    }

    let currentRecIDs = parts[1].split(',');
    let recIDs = new Set(currentRecIDs).union(nodeRelationMapping[rec_ID]);

    let isUpdated = currentRecIDs.length < recIDs.size; // whether we need to update the graph
    if(!isUpdated){
        window.hWin.HEURIST4.msg.showMsgFlash('Node has already been expanded', 3000);
        return;
    }

    let title = d3.select(`.id${rec_ID}`).data();
    title = title[0] && title[0].name ? `${title[0].name} (#${rec_ID})` : `Record #${rec_ID}`;

    window.hWin.HEURIST4.msg.bringCoverallToFront($('body'), {'background-color': 'white', opacity: 1, color: 'black'}, `Extending graph for ${title}...`);

    performSearch(`ids:${Array.from(recIDs).join(',')}`);
}

        </script>
    </head>

    <body>
        <!-- Visualize HTML (Toolbar and SVG container) -->
        <?php
            /**
             * @global int $isDatabaseStructure Indicates if the visualization is for database structure (0 for record data).
             */
            $isDatabaseStructure = 0;
            include_once "visualize.php"; // Includes the common HTML structure for the visualization
        ?>

        <!-- Functions callable from parent iframe or for standalone mode -->
        <script>
        
        var isMinimalVersion = <?php echo $isMinimalVersion?'true':'false';?>;


        /**
        * Parses Heurist record data and relationship data into a D3-compatible format (nodes and links).
        *
        * @private
        * @param {Array<number|string>} records_ids - An array of record IDs to be included as nodes.
        * @param {object} relations - An object containing relationship data, expected to have `headers` (for node info),
        *                           `direct` (for outgoing links), and `reverse` (for incoming links).
        * @returns {object} An object with `nodes` and `links` arrays for D3.
        */
        function __parseData(records_ids, relations) {

            let data = {}; // Unused variable
            let nodes = {};
            let links = [];

            if(records_ids !== undefined && relations !== undefined) {

                // Construct nodes for each record
                for(let i=0;i<records_ids.length;i++) {

                    const recId = records_ids[i];
                    // Ensure relations.headers[recId] exists to prevent errors
                    if (relations.headers && relations.headers[recId]) {

                        let node = {
                            id: parseInt(recId),
                            name: relations.headers[recId][0],  // record title
                            image: window.hWin.HAPI4.iconBaseURL+relations.headers[recId][1],  // record type id for icon
                            count: 0, // Default count, might be updated later if applicable
                            depth: 1, // Default depth
                            rty_ID: relations.headers[recId][1] // Store record type ID
                        };
                        nodes[recId] = node;

                        if(!Object.hasOwn(nodeRelationMapping, recId)){
                            nodeRelationMapping[recId] = new Set();
                        }
                    }
                }

                /**
                * Helper function to determine links between nodes based on a set of relations.
                *
                * @private
                * @param {object} currentNodes - The map of currently defined nodes.
                * @param {Array<object>} relationSet - An array of relation objects (e.g., `relations.direct` or `relations.reverse`).
                *                                     Each object should have `recID` (source), `targetID`, `dtID` (detail type ID), `trmID` (term ID).
                * @returns {Array<object>} An array of D3 link objects.
                */
                function __getLinks(currentNodes, relationSet) {

                    let newLinks = []; // Renamed to avoid conflict

                    if (!relationSet) { // Ensure relationSet is defined
                        return newLinks;
                    }

                    // Go through all relations
                    for(let j = 0; j < relationSet.length; j++) {

                        // Null check
                        const sourceId = relationSet[j].recID;
                        const targetId = relationSet[j].targetID;
                        const dtID = relationSet[j].dtID;
                        const trmID = relationSet[j].trmID;
                        let relationName = "Floating relationship"; // Default name

                        if(dtID > 0) {
                            relationName = $Db.dty(dtID, 'dty_Name'); // Assumes $Db is available
                        }else if(trmID > 0) {
                            relationName = $Db.trm(trmID, 'trm_Label'); // Assumes $Db is available
                        }

                        // Link check: ensure both source and target nodes exist in our current set
                        if(sourceId !== undefined && currentNodes[sourceId] !== undefined &&
                            targetId !== undefined && currentNodes[targetId] !== undefined) {

                            // Construct link
                            let link = {
                                source: currentNodes[sourceId],
                                target: currentNodes[targetId],
                                targetcount: 1, // Default, might represent cardinality or frequency if available
                                relation: {
                                    id: dtID > 0 ? dtID : trmID, // Use dtID if available, else trmID
                                    name: relationName,
                                    type: dtID > 0 ? 'resource' : 'relationship' // Distinguish type
                                }
                            };
                            newLinks.push(link);
                        }
                    }

                    return newLinks;
                }

                // Consolidate links from direct and reverse relations
                links = links.concat( __getLinks(nodes, relations.direct)  );
                links = links.concat( __getLinks(nodes, relations.reverse) );

                function addNewConnections(){

                    for(let i = 0; i < relations.direct.length; i++){

                        let direct = relations.direct[i];
                        const recID = Number.parseInt(direct.recID);
                        const targetID = Number.parseInt(direct.targetID);

                        if(Object.hasOwn(nodeRelationMapping, recID)){
                            nodeRelationMapping[recID].add(targetID);
                        }
                        if(Object.hasOwn(nodeRelationMapping, targetID)){
                            nodeRelationMapping[targetID].add(recID);
                        }
                    }

                    for(let i = 0; i < relations.reverse.length; i++){

                        let reverse = relations.reverse[i];
                        const recID = Number.parseInt(reverse.recID);
                        const sourceID = Number.parseInt(reverse.sourceID);

                        if(Object.hasOwn(nodeRelationMapping, recID)){
                            nodeRelationMapping[recID].add(sourceID);
                        }
                        if(Object.hasOwn(nodeRelationMapping, sourceID)){
                            nodeRelationMapping[sourceID].add(recID);
                        }
                    }
                }

                addNewConnections();

                /*
                newly_added_nodes = record_ids - previous_nodes;
                graphLevels.push(newly_added_nodes);
                */
            }

            // Construct data object with nodes as an array
            var nodesArray = []; // Renamed for clarity
            for(var id in nodes) {
                if (Object.hasOwn(nodes, id)) { // Ensure it's an own property
                    nodesArray.push(nodes[id]);
                }
            }

            return {nodes: nodesArray, links: links};
        }
        
        /**
         * Updates the visualization to highlight a given set of selected record IDs.
         *
         * @global
         * @param {Array<string|number>} selectedRecordsIds - An array of record IDs to select.
         */
        function showSelection( selectedRecordsIds ){
             visualizeSelection( selectedRecordsIds ); // Assumes visualizeSelection is globally available from selection.js
        }

        /**
         * Initializes or updates the D3 visualization with new data and settings.
         * This function is typically called from the parent Heurist page or by `onPageInit` in standalone mode.
         *
         * @global
         * @param {object} data - The data object for D3 (containing `nodes` and `links`).
         * @param {Array<string|number>} selectedRecordsIds - Array of initially selected record IDs.
         * @param {object} new_request - The search query object that generated this data.
         * @param {function} [onSelectEvent] - Callback function triggered when nodes are selected in the visualization.
         * @param {function} [onRefreshData] - Callback function to request a data refresh (e.g., after structural changes).
         * @param {function} [onExpandRecords] - Callback function to handle requests to expand node connections.
         */
        function showData(data, selectedRecordsIds, new_request, onSelectEvent, onRefreshData, onExpandRecords) {
            // Initial message while building graph
            if(data && data.nodes && data.nodes.length > 0){ // Check if nodes array is not empty
                $("#d3svg").html('<text x="25" y="25" fill="black">Building graph ...</text>');
            }else{
                $("#d3svg").html('<text x="25" y="25" fill="black">No data for graph</text>');
                return;
            }

            /**
             * Custom data parsing function for the visualize plugin.
             * In this case, it's an identity function as data is pre-parsed by `__parseData`.
             * @param {object} d - Input data.
             * @returns {object} The same data.
             */
            function getData(d) {
                return d;
            }

            /**
             * Calculates the desired line length for links, potentially adjusted by node depth.
             * @param {object} record - The source or target record data object, may have a `depth` property.
             * @returns {number} The calculated line length.
             */
            function getLineLength(record) {
                var length = getSetting('setting_linelength'); // Assumes getSetting is globally available
                if(record !== undefined && Object.hasOwn(record, "depth") && record.depth > 0) { // Ensure depth is positive
                    length = length / (record.depth+1);
                }
                return length;
            }

            $(window).on('resize',onVisualizeResize); // Bind resize handler
            setTimeout(()=>onVisualizeResize(),1000); // Initial call

            // Initialize the visualize plugin
            $("#visualize").visualize({ // Assumes #visualize is the ID of the main container in visualize.php
                data: data,
                request: new_request,
                getData: getData, // Pass the identity function
                getLineLength: getLineLength,

                selectedNodeIds: selectedRecordsIds,   // Assign current selection
                triggerSelection: onSelectEvent,       // Callback for selection changes
                onRefreshData: onRefreshData,          // Callback for data refresh requests
                onExpandNode: onExpandRecords,         // Callback for node expansion requests

                entityradius: 1, // Default, likely overridden by settings.js
                linewidth: 1,    // Default, likely overridden by settings.js

                showCounts: false,            // These are specific to the plugin's capabilities
                showEntitySettings: false,
                showFormula: false,
                gravity: 'off', // Start with gravity off; can be 'touch' to initially scatter
                minimal: isMinimalVersion 
            });

            // Example: setTimeout(function(){ setGravity('off');}, 3000); // turn off gravity after initial scatter

            changeViewMode('icons'); // set initial view mode
            zoomToFit();

            currentRequest = new_request;

            window.hWin.HEURIST4.msg.sendCoverallToBack();
        }

        /**
         * Handles window resize events to adjust the SVG container's top position.
         * @global
         */
        function onVisualizeResize(){
            var width = $(window).width();
            let topPos = '0em';
            if(isMinimalVersion){
                let ele = $('#toolbar').find('.dropdown-content1');
                if(ele.length>0){
                    ele.removeClass('dropdown-content1');
                }
                /*
                ele.css('position','relative !important');
console.log(ele); */               
                //$('#toolbar').css({'position':'absolute',left:'0px',top:'0px'});
                topPos = $('#toolbar').height();
                if(topPos<40) { topPos = 40; }
                topPos = (topPos+10)+'px';
                $('#toolbar').find('.heurist-helper2').hide();
            }
console.log('>>>',isMinimalVersion, topPos);            
            $('#divSvg').css('top', topPos); // Assumes #divSvg is the SVG container
        }

        </script>
    </body>

</html>