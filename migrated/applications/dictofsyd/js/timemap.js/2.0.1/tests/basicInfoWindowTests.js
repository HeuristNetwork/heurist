
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


IWT.dataset = {
    id: "test",
    type: "basic",
    options: {
        items: [
            {
                "start" : "1980-01-02",
                "point" : {
                    "lat" : 23.456,
                    "lon" : 12.345
                },
                "title" : "Test Event",
                "options" : {
                    "description": "Test Description"
                }
            }
        ]
    }
};

// tests

function exposeTestFunctionNames() {
    return [
        'testDefaultOpen',
        'testDefaultClose'
    ];
}

IWT.setup = function() {
    // this is the test preamble
    var ds = IWT.tm.datasets['test'];
    var item = ds.getItems()[0];
    item.openInfoWindow();
}

IWT.setupTest = function() {
    // this is effectively the assertion
    return $('div.infotitle, div.infodescription').length == 2
}
function testDefaultOpen() {
    assertTrue('Timeout with no info window divs found', IWT.success);
    assertEquals('Info window title incorrect', 
         'Test Event', $('div.infotitle').text());
    assertEquals('Info window description incorrect', 
        'Test Description', $('div.infodescription').text());
}

function testDefaultClose() {
    var ds = IWT.tm.datasets['test'];
    var item = ds.getItems()[0];
    item.closeInfoWindow();
    if (IWT.tm.map.api == 'microsoft') {
        // Bing just hides the divs
        assertEquals('Info window title is not hidden', 
            'hidden', $('div.infotitle').css('visibility'));
        assertEquals('Info window description is not hidden', 
            'hidden', $('div.infodescription').css('visibility'));
    } else {
        // everyone else seems to remove the divs from the DOM
        assertEquals('Info window div were found after the window was closed',
            0, $('div.infotitle, div.infodescription').length);
    }
}