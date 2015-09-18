
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
                    "lat" : 23.0,
                    "lon" : 12.0
                },
                "title" : "Test Event 1",
                "options" : {
                    "description": "Test Description"
                }
            },
            {
                "start" : "1980-01-02",
                "point" : {
                    "lat" : 24.0,
                    "lon" : 13.0
                },
                "title" : "Test Event 2",
                "options" : {
                    "description": "Test Description"
                }
            },
            {
                "start" : "1980-01-02",
                "point" : {
                    "lat" : 25.0,
                    "lon" : 14.0
                },
                "title" : "Test Event 3",
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
        'testMultipleWindows',
        'testCloseOnScroll'
    ];
}

IWT.setup = function() {
    // this is the test preamble
    var ds = IWT.tm.datasets['test'],
        x = 0;
    // we need some delay or Google maps v3 won't close windows
    function openItem() {
        var item = ds.getItems()[x++];
        if (item) {
            item.openInfoWindow();
            setTimeout(openItem, 150);
        }
    }
    openItem();
}
IWT.setupTest = function() {
    // this is effectively the assertion
    return $('div.infotitle, div.infodescription').length == 2 &&
        $('div.infotitle').text() == 'Test Event 3'
        
}
function testMultipleWindows() {
    assertTrue('Timeout with more or fewer than one info window, or wrong info window open', IWT.success);
}

function testCloseOnScroll() {
    IWT.tm.timeline.getBand(0).setCenterVisibleDate(new Date());
    if (IWT.tm.map.api == 'microsoft') {
        // Bing just hides the divs
        assertEquals('Info window content is not hidden', 
            'hidden',$('div.infotitle').css('visibility'));
    } else {
        // everyone else seems to remove the divs from the DOM
        assertEquals('Info window div were found after the timeline was scrolled',
            0, $('div.infotitle').length);
    }
}