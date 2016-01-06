/**
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* Layout configuration fails  ????? files ????
* @see js/layout.js
*
* @type Array widgets - list of widgets/applications
* @type Array layouts - list of layouts
*/

/**
* List of applications (widgets)
*  id - unique identificator
*  name - title
*  widgetname - name of jquery widget (init function)
*  script - link to jquery widget file
*  minsize - array width,height
*  size   - array width,height
*  isframe - widget or link will be loaded in iframe
*  url - link to be loaded (if not widget)
*
* @type Array
*/

var cfg_widgets = [

    {id:'h4_search', name:'Search', widgetname:'search', script:'hclient/widgets/search/search.js'},

    {id:'ha_search_tree', name:'Saved searches', widgetname:'svs_list', script:'hclient/widgets/search/svs_list.js'},


    /* experimental - widgets for future implementation
    {id:'ha14', name:'Tags', url:'hclient/widgets/search_tags.php'},
    {id:'ha01', name:'About', widgetname:'about', script:'hclient/widgets/about.js', minsize:[200,200], size:[300,300], isframe:false },
    {id:'ha02', name:'Profile', widgetname:'profile', script:'hclient/widgets/profile.js'},
    {id:'ha15', name:'Navigation', widgetname:'pagination', script:'hclient/widgets/pagination.js'},
    {id:'ha21', name:'Search result',  widgetname:'rec_list', script:'hclient/widgets/exp/rec_list.js'},
    {id:'ha31', name:'Record', widgetname:'rec_viewer', script:'hclient/widgets/rec_viewer.js'},
    {id:'ha32', name:'Media', title:'Record media viewer', url:'hclient/widgets/rec_media.php'},
    {id:'ha33', name:'Relations', title:'Record relations viewer', url:'hclient/widgets/rec_relation.php'},
    {id:'ha34', name:'Comments', title:'Discussion over record', url:'hclient/widgets/rec_comments.php'},

    {id:'ha52', name:'Report', title:'Smarty report system', url:'hclient/widgets/rep_smarty.php'},
    {id:'ha53', name:'Transform', url:'hclient/widgets/rep_xslt.php'},
    {id:'ha54', name:'Crosstabs', url:'php/sync/crosstabs.php', isframe:true},
    {id:'ha61', name:'Ext Record Viewer', widgetname:'rec_viewer_ext', script:'hclient/widgets/exp/rec_viewer_ext.js'},
    */

    {id:'h3_mainMenu', name:'Main Menu', widgetname:'mainMenu', script:'hclient/widgets/topmenu/mainMenu.js'},
    {id:'h3_resultList', name:'Search Result', widgetname:'resultList', script:'hclient/widgets/viewers/resultList.js'},
    {id:'h3_recordDetails', name:'Record', widgetname:'recordDetails', script:'hclient/widgets/viewers/recordDetails.js'},
    {id:'h3_recordListExt', name:'&nbsp;&nbsp;&nbsp;', widgetname:'recordListExt', script:'hclient/widgets/viewers/recordListExt.js'},

    {id:'ha51', name:'Map', title:'Map and timeline', widgetname:'app_timemap', script:'hclient/widgets/viewers/app_timemap.js'},  // map in iframe
    {id:'h4_static', name:'Static Page', widgetname:'staticPage', script:'hclient/widgets/viewers/staticPage.js'},

    {id:'h4_connections', name:'Connections', widgetname:'connections', script:'hclient/widgets/viewers/connections.js'},

    // DIGITAL HARLEM APPS
    {id:'dh_search', name:'Search Forms', widgetname:'dh_search', script:'hclient/widgets/digital_harlem/dh_search.js'},
    {id:'dh_maps', name:'Saved Maps', widgetname:'dh_maps', script:'hclient/widgets/digital_harlem/dh_maps.js'},
    {id:'dh_results', name:'Layers', widgetname:'dh_results', script:'hclient/widgets/digital_harlem/dh_results.js'},
    {id:'dh_legend', name:'Legend', widgetname:'dh_legend', script:'hclient/widgets/digital_harlem/dh_legend.js'}

];


/**
entire layout may be divided into 5 panes  : north  west  center  east south
each pane may have: size, minsize, resizable (true), dropable(false)

each pane contains applications, application may be grouped into tabs
tab's properties  dockable,dragable,resizable applied to all children applications

dockable - (false) placed into tabcontrol and allows to dock other apps
if true and not in tabgroup, tabgroup is created by default
hasheader - (if isdocking false)
css - list of css parameters - mostly for position
resizable - (false)
dragable - (false) it is possible to drag around  otherwise fixed position
options - parameters to init application

*/

var cfg_layouts = [

    // Default layout - the standard Heurist interface, used if no parameter provided
    {id:'H4Default', name:'Heurist Def', theme:'heurist', type:'cardinal',
        north:{size:'12em', resizable:false, overflow:'hidden',
            apps:[
                {appid:'h3_mainMenu', hasheader:false, css:{position:'absolute', top:0,left:0,height:'6em',right:0, border:'none', 'background':'none', 'min-width':'75em'} },    //top panel
                {appid:'h4_search', hasheader:false, css:{position:'absolute', top:'6em', left:0, height:'6em', right:0, border:'none', 'background':'none', 'min-width':'75em'}, options:{has_paginator:false} },   //search '#8ea9b9'
        ]},
        west:{size:260, minsize:230, apps:[{appid:'ha_search_tree', hasheader:false, css:{border:'none', 'background':'none'} }]},  //saved searches
        center:{minsize:300, dropable:false, apps:[{appid:'h3_resultList', hasheader:false, innerHeader:true, dockable:false, dragable:false, css:{'background-color':'white'}, options:{innerHeader: true} }]},  //search result
        east:{size:'50%', minsize:300, dropable:false,
        tabs:[{dockable:true, dragable:false, resizable:false,
            apps:[
                {appid:'h3_recordListExt', name: 'Record', options:{url: 'records/view/renderRecordData.php?recID=[recID]&db=[dbname]', is_single_selection:true}},    // H3 record viewer        
                {appid:'ha51'}, // map viewer (map.php) inside widget (app_timemap.js)
                {appid:'h3_recordListExt', options:{title:'Report', url: 'viewers/smarty/showReps.html?db=[dbname]'}},
                {appid:'h4_connections',   options:{title:'Connections', url: 'hclient/framecontent/springDiagram.php?db=[dbname]'}}
            ]}]
        }
    },

    // Position of widgets are specified in CSS, all widgets can be dragged around and repositioned
    /* This layout needs to be defiend spcifically to be useful
    {id:'FreeLayout', name:'Free example', theme:'heurist', type:'free',
        mainpane: {dropable:true, tabs:[{dockable:true, dragable:true, resizable:true,
            apps:[{appid:'h3_mainMenu', hasheader:false, css:{width:'100%', border:'none', 'background':'none'} },
                {appid:'h4_search', hasheader:false, css:{width:'100%', border:'none', 'background':'none'} },
                {appid:'ha_search_tree', hasheader:false, css:{border:'none', 'background':'none'} },  //saved searches
                {appid:'h3_resultList', name: 'Search result' },
                {appid:'h3_recordListExt', name: 'Record', options:{url: 'records/view/renderRecordData.php?recID=[recID]&db=[dbname]', is_single_selection:true}},    //H3 record viewer
                {appid:'ha51'}, // H4 map V2
                {appid:'h3_recordListExt', options:{title:'Report', url: 'viewers/smarty/showReps.html'}},     //H3 smarty
                {appid:'h4_connections',   options:{title:'Connections', url: 'hclient/framecontent/springDiagram.php?db=[dbname]'}}  //H4 connections
            ]}]
        }
    },
    */

    {id:'DigitalHarlem', name:'Digital Harlem', theme:'heurist', type:'cardinal',
        north:{size:140, resizable:false, overflow:'hidden',
            apps:[
                {appid:'h4_static', hasheader:false,
                    options:{url: 'hclient/widgets/digital_harlem/dh_header.php?db=[dbname]'}
                    //css:{width:'100%',height:'100%'}},
                    ,css:{position:'absolute', top:0,left:0,bottom:0,right:0, border:'none', 'background':'none', 'min-width':'75em'}},    //top panel
        ]},
        west:{size:270, resizable:false, apps:[
            //{appid:'h4_search', hasheader:false, css:{position:'absolute', top:'0', height:'0', border:'none', display:'none'}, options:{has_paginator:false, isloginforced:false} },
            {appid:'dh_search', hasheader:false, css:{border:'none', 'background':'none'} }]},  //faceted/forms searches
        center:{minsize:300, dropable:false,
            tabs:[{dockable:false, dragable:false, resizable:false,
                apps:[
                    {appid:'ha51', options:{layout:['map','timeline'], startup:50926, eventbased:false} } //mapping
                    ,{appid:'h3_resultList', hasheader:true, name: 'List', options:{title:'List', showmenu:false, eventbased:false} }
                    //,{appid:'h4_static', hasheader:true, name: 'DH Blog', options:{url: 'http://digitalharlemblog.wordpress.com/'} }
                ]
            }]
        },
        east:{size:300, minsize:300, dropable:false,
            tabs:[{dockable:false, dragable:false, resizable:false,
                apps:[
                    {appid:'dh_maps', name: 'Maps'},     // saved searches(maps)
                    {appid:'h4_static', name:'Legend', options:{title:'Legend', url: 'hclient/widgets/digital_harlem/dh_legend.php?db=[dbname]'}}
                ]
            }]
        },
        south:{size:40, resizable:false, overflow:'hidden',
            apps:[
                {appid:'h4_static', hasheader:false,
                    options:{url: 'hclient/widgets/digital_harlem/dh_footer.html'}
                    ,css:{position:'absolute', top:0,left:0,bottom:0,right:0}}
        ]},
    },

        // Alternative (Gridster) layout (like Windows tiles) - not very useful unless a small set of widgets
    /*   {id:'Gridster', name:'gridster example', theme:'smoothness', type:'gridster',
    options:{widget_base_dimensions:[50, 50]},
    mainmenu: { col:1, row:1, size_x:10, size_y:1, apps:[{appid:'h3_mainMenu', hasheader:false, css:{width:'100%', height:'100%', border:'none', 'background':'none'} } ]},
    search: { col:11, row:1, size_x:10, size_y:1, apps:[{appid:'h4_search', hasheader:false, css:{width:'100%', height:'100%', border:'none', 'background':'none'} } ]},
    search_nav:{ col:1, row:2, size_x:3, size_y:10, apps:[{appid:'ha_search_tree', hasheader:false, css:{border:'none', 'background':'none'} }]},  //saved searches

    pane1:{col:4, row:2, size_x:7, size_y:7, dockable:true, apps:[{appid:'h3_resultList', name: 'Search result' }]},  //search result
    pane2:{col:12, row:2, size_x:7, size_y:7, dockable:true, apps:[{appid:'h3_recordListExt', name: 'Record' }]},
    pane5:{col:20, row:2, size_x:7, size_y:7, dockable:true, apps:[{appid:'h4_connections',   options:{title:'Connections', url: 'hclient/framecontent/springDiagram.php?db=[dbname]'} }]},
    pane3:{col:4, row:9, size_x:7, size_y:7, dockable:true, apps:[ {appid:'ha51', options:{title:'Map'}} ] },
    pane4:{col:12, row:9, size_x:7, size_y:7, dockable:true, apps:[{appid:'h3_recordListExt', options:{title:'Report', url: 'viewers/smarty/showReps.html'} }]},

    }, */


];


