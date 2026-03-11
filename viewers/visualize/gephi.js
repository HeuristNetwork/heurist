/**
* gephi.js - Functions to download the displayed nodes in GEPHI format
*
* @fileOverview This file contains functions to transform the current visualization data
* into the GEXF (Gephi Exchange Format) and initiate a download for the user.
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4
*/

/* global settings */

/**
 * Transforms the current visualization data into GEXF (Gephi Exchange Format)
 * and initiates a download of the .gexf file.
 * The function constructs an XML string representing the graph's nodes, edges,
 * and their attributes, then triggers a browser download.
 */
function getGephiFormat() {

    const ID = Math.floor(Math.random() * 90000);
    let query = typeof window.visualiserRequest === 'string' ? {q: window.visualiserRequest} : window.visualiserRequest;
    window.hWin.HAPI4.SystemMgr.prepareParameters('export', query, 0, ID);

    let params = new URLSearchParams();

    params.set('w', 'all');
    params.set('format', 'gephi');
    params.set('file', '1');
    params.set('linkmode', 'none');
    params.set('preparedID', ID);
    params.set('db', window.hWin.HAPI4.database);

    const url = `${window.hWin.HAPI4.baseURL}hserv/controller/record_output.php?${params.toString()}`;

    window.open(url, '_blank');
}