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

var isMinimalVersion = <?php echo $isMinimalVersion?'true':'false';?>;
/**
 * Flag indicating if the visualization is running in standalone mode (i.e., not embedded in a Heurist page).
 * @type {boolean}
 */
var isStandAlone = false;

window.visualiserRequest = null;

/**
 * Array<Set> tracking for each graph level (to handle moving backwards in levels)
 * Use sets to avoid duplicate record IDs
 */
var graphLevels = [];
/**
 * Object<Record ID => RecType ID> tracking records to record types that appear in the graph (or has appeared)
 */
var nodeRecTypes = {};
/**
 * Object<Record ID => Set> tracking related records for each record that appears in the graph (or has appeared)
 */
var nodeRelationMapping = {};
/**
 * Visualiser instance, for calling destroy method
 */
var visualise = null;

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
// 
// onPageInit or expandNode -> performSearch -> showData
//    
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

    const MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');

    let query = {q: q, rules: rules, w: 'a', detail: 'detail', l: MAXITEMS};

    window.hWin.HAPI4.RecordMgr.search(query, (response) => {

        if(response.status == window.hWin.ResponseStatus.OK){

            let recordset = new HRecordSet(response.data); // Assumes HRecordSet is available globally or via hWin

            let records_ids = recordset.getIds(MAXITEMS);
            if(records_ids.length <= 0){
                return;
            }
            //Finds all directly related (linked or via relationship records) records for a given set of record IDs.
            window.hWin.HAPI4.RecordMgr.search_related({ids:records_ids.join(',')}, (response_related) => {

                if(response_related.status == window.hWin.ResponseStatus.OK){
                    // Store relationships
                    // Parse response to spring diagram format
                    let data = __parseData(records_ids, response_related.data);

                    showData(data, [], query, null, null, null, isMinimalVersion?onExpandLevel:null);

                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response_related);
                }
            });

        }else{
            window.hWin.HEURIST4.msg.showMsgErr(response);
        }
    });
}

function onExpandLevel(action, currentRecIDs){

    let updateGraphLevels = (fullList, filterOut) => {

        let newList = new Set();
        for(const ID of fullList){

            if(filterOut.has(ID)){
                continue;
            }

            newList.add(ID);
        }

        graphLevels.push(newList);
    };

    let getNextLevel = (newOnly = false) => {

        let toExpand = graphLevels[graphLevels.length - 1];
        currentRecIDs = new Set(currentRecIDs);
        let newRecIDs = new Set(newOnly ? null : currentRecIDs);
        let hasExtensionAvailable = new Set();

        for(const recID of toExpand){

            if(!Object.hasOwn(nodeRelationMapping, recID) || nodeRelationMapping[recID].size === 0){
                continue;
            }

            if(!newOnly){
                newRecIDs = newRecIDs.union(nodeRelationMapping[recID]);
                continue;
            }

            for(const relRecID of nodeRelationMapping[recID]){

                if(currentRecIDs.has(relRecID)){
                    continue;
                }

                newRecIDs.add(relRecID);
                hasExtensionAvailable.add(recID);
            }
        }

        if(!newOnly && newRecIDs.size > 0){
            graphLevels.push(newRecIDs);
        }

        return [newRecIDs, hasExtensionAvailable];
    };

    if(action === 'decrease'){

        let toRemove = graphLevels.pop(); // remove outer most layer

        if(toRemove){
            currentRecIDs = currentRecIDs.filter((id) => !toRemove.has(id));
            currentRecIDs = new Set(currentRecIDs);
        }else{
            currentRecIDs = null;
        }

        if(currentRecIDs || currentRecIDs.size === 0){
            $('#decreaseGraphLevel').addClass('ui-state-disabled');
            return false;
        }
        /** @todo
         * Manually remove graph nodes and links (was causing issues), AND update the force simulation (this should fix those issues)
         */
    }else if(action === 'nextLevel'){

        let [newRecIDs, hasExtensionAvailable] = getNextLevel(action === 'nextLevel');
        currentRecIDs = new Set(currentRecIDs);

        if(action === 'nextLevel'){
            currentRecIDs = {new: newRecIDs, extending: hasExtensionAvailable};
        }else if(currentRecIDs.size <= newRecIDs.size){
            window.hWin.HEURIST4.msg.showMsgFlash('The graph cannot grow any further...', 3000);
            $('#increaseGraphLevel').addClass('ui-state-disabled');
            return false;
        }

    }else if(action === 'increase'){

        const currentQuery = window.visualiserRequest;
        let parts = typeof currentQuery === 'string' ? currentQuery.split(':') : [];
        if(parts.length !== 2 || parts[0] !== 'ids' || !currentRecIDs instanceof Set || currentRecIDs.size === 0){
            return;
        }
        let originalIDs = parts[1].split(',').map((x) => +x);
        currentRecIDs = currentRecIDs.union(new Set(originalIDs));
    }

    if(action !== 'nextLevel'){

        window.hWin.HEURIST4.msg.bringCoverallToFront($('body'), {'background-color': 'white', opacity: 1, color: 'black'}, `${action === 'increase' ? 'Extending' : 'Removing'} leaf nodes...`);
    
        performSearch(`ids:${Array.from(currentRecIDs).join(',')}`);
    }

    return action === 'nextLevel' ? currentRecIDs : true;
}

function expandNode(rec_ID){

    const currentQuery = window.visualiserRequest;
    let parts = typeof currentQuery === 'string' ? currentQuery.split(':') : [];
    if(!Object.hasOwn(nodeRelationMapping, rec_ID) || nodeRelationMapping[rec_ID].size === 0 || parts.length > 2 || parts[0] !== 'ids'){
        return;
    }

    let currentRecIDs = parts[1].split(',');
    let recIDs = new Set(currentRecIDs).union(nodeRelationMapping[rec_ID]);
    let newIDs = Array.from(nodeRelationMapping[rec_ID]).filter((ID) => currentRecIDs.indexOf(ID) === -1);

    if(newIDs.length === 0){
        window.hWin.HEURIST4.msg.showMsgFlash('Node has already been expanded', 3000);
        return;
    }

    // Add to current level in graphLevels
    let currentLevel = graphLevels.length - 1;
    graphLevels[currentLevel].union(new Set(newIDs));

    let title = d3.select(`.id${rec_ID}`).data();
    title = title[0] && title[0].name ? `${title[0].name} (#${rec_ID})` : `Record #${rec_ID}`;

    window.hWin.HEURIST4.msg.bringCoverallToFront($('body'), {'background-color': 'white', opacity: 1, color: 'black'}, `Extending graph for ${title}...`);

    performSearch(`ids:${Array.from(recIDs).join(',')}`);
}

function updateRecordCache(mainRecID, rtyID, parentRecID = 0){

    mainRecID = parseInt(mainRecID);
    rtyID = parseInt(rtyID);
    parentRecID = parseInt(parentRecID);

    const addToParentList = window.hWin.HEURIST4.util.isPositiveInt(parentRecID);

    if(!window.hWin.HEURIST4.util.isPositiveInt(mainRecID)){
        return;
    }

    if(!Object.hasOwn(nodeRelationMapping, mainRecID)){
        nodeRelationMapping[mainRecID] = new Set();
    }

    if(addToParentList){

        if(!Object.hasOwn(nodeRelationMapping, parentRecID)){
            nodeRelationMapping[parentRecID] = new Set();
        }

        nodeRelationMapping[parentRecID].add(mainRecID);
    }

    if(!window.hWin.HEURIST4.util.isPositiveInt(rtyID)){
        return;
    }

    if(!Object.hasOwn(nodeRecTypes, mainRecID)){
        nodeRecTypes[mainRecID] = rtyID;
    }
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

                        const recTypeId = relations.headers[recId][1];

                        let node = {
                            id: parseInt(recId),
                            name: relations.headers[recId][0],  // record title
                            image: window.hWin.HAPI4.iconBaseURL+recTypeId,  // record type id for icon
                            count: 0, // Default count, might be updated later if applicable
                            depth: 1, // Default depth
                            rty_ID: recTypeId // Store record type ID
                        };
                        nodes[recId] = node;

                        updateRecordCache(recId, recTypeId);
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

                        updateRecordCache(recID, relations.headers[recID][1], targetID);
                        updateRecordCache(targetID, relations.headers[targetID][1], recID);
                    }

                    for(let i = 0; i < relations.reverse.length; i++){

                        let reverse = relations.reverse[i];
                        const recID = Number.parseInt(reverse.recID);
                        const sourceID = Number.parseInt(reverse.sourceID);

                        updateRecordCache(recID, relations.headers[recID][1], sourceID);
                        updateRecordCache(sourceID, relations.headers[sourceID][1], recID);
                    }
                }

                addNewConnections();

                if(graphLevels.length === 0){
                    graphLevels.push(new Set(records_ids));
                    localStorage.setItem('extendedLevel', 0);
                }
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
        function showData(data, selectedRecordsIds, new_request, onSelectEvent, onRefreshData, onExpandRecords, onExpandLevel) {
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

            if(visualise !== null){
                visualise.destroy();
            }

            $(window).on('resize', onVisualizeResize); // Bind resize handler
            setTimeout(()=>onVisualizeResize(),1000); // Initial call

            // Initialize the visualize plugin
            visualise = $("#visualize").visualize({ // Assumes #visualize is the ID of the main container in visualize.php
                data: data,
                request: new_request,
                getData: getData, // Pass the identity function
                getLineLength: getLineLength,

                selectedNodeIds: selectedRecordsIds,   // Assign current selection
                triggerSelection: onSelectEvent,       // Callback for selection changes
                onRefreshData: onRefreshData,          // Callback for data refresh requests
                onExpandNode: onExpandRecords,         // Callback for node expansion requests
                onExpandLevel: onExpandLevel,          // Callback for entrire level expansion

                entityradius: 1, // Default, likely overridden by settings.js
                linewidth: 1,    // Default, likely overridden by settings.js

                showCounts: false,            // These are specific to the plugin's capabilities
                showEntitySettings: false,
                showFormula: false,
                gravity: 'off', // Start with gravity off; can be 'touch' to initially scatter
                minimal: isMinimalVersion
            });

            let recIDs = new Set();
            if(isMinimalVersion){ // trigger additional settings for minimal mode

                setGravity('aggressive');
                setTimeout(() => {
                    setGravity('off');
                    zoomToFit();
                }, 2000); // turn off gravity after initial scatter

                changeViewMode('icons'); // set initial view mode

                recIDs = getRecordIDs(data.nodes);
            }

            zoomToFit();

            window.visualiserRequest = isMinimalVersion && recIDs.size > 0 ? `ids:${Array.from(recIDs).join(',')}` : new_request;
            window.hWin.HEURIST4.msg.sendCoverallToBack();
        }

        function getRecordIDs(nodes){

            let recIDs = new Set();

            for(let i = 0; i < nodes.length; i++){
                if(!Object.hasOwn(nodes[i], 'id') || !window.hWin.HEURIST4.util.isPositiveInt(nodes[i]['id'])){
                    continue;
                }

                recIDs.add(Number.parseInt(nodes[i]['id']));
            }

            return recIDs;
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
                topPos = $('#toolbar').height();
                if(topPos < 40) { topPos = 40; }
                topPos = (topPos+15)+'px';
                $('#toolbar').find('.heurist-helper2').hide();
            }
            $('#divSvg').css('top', topPos); // Assumes #divSvg is the SVG container

            // Update wub-toolbar top as well
            $('#showSubToolbar, .dropdown-subbar').css('top', topPos);
            $('#expanderSettings').css('top', topPos + 30);
        }

        </script>
    </body>

</html>
