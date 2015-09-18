
/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


// namespace for info window tests
var IWT = {};
    
IWT.setUpEventually = function() {
    var timeoutLimit = 3000,
        timeoutInterval = 100,
        elapsed = 0,
        timeoutId;
    function look() {
        IWT.success = IWT.setupTest();
        if (IWT.success || elapsed > timeoutLimit) {
            setUpPageStatus = "complete";
        }
        else {
            elapsed += timeoutInterval;
            timeoutId = window.setTimeout(look, timeoutInterval);
        }
    }
    look();
}

function setUpPage() {
    IWT.tm = TimeMap.init({
        mapId: "map",               // Id of map div element (required)
        timelineId: "timeline",     // Id of timeline div element (required) 
        datasets: [ 
            IWT.dataset
        ]
    });
    IWT.setup();
    IWT.setUpEventually();
}