/** 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
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
* Layout configuration fails 
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
*  misize - array width,height 
*  size   - array width,height
*  isframe - widget or link will be loaded in iframe
*  url - link to be loaded (if not widget)
* 
* @type Array
*/
var widgets = [
    {id:'ha01', name:'Databases', widgetname:'about', script:'apps/about.js', minsize:[200,200], size:[300,300], isframe:false },
    {id:'ha02', name:'Profile', widgetname:'profile', script:'apps/profile.js'},

    {id:'ha10', name:'Search', widgetname:'search', script:'apps/search/search.js'},

    {id:'ha13', name:'Saved searches', widgetname:'search_links', script:'apps/search_links.js'},
    {id:'ha_search_tree', name:'Saved searches', widgetname:'search_links_tree', script:'apps/search/search_links_tree.js'},
    {id:'ha14', name:'Tags', url:'apps/search_tags.php'},
    {id:'ha15', name:'Navigation', widgetname:'pagination', script:'apps/pagination.js'},


    /* experementals
    {id:'ha21', name:'Search result',  widgetname:'rec_list', script:'apps/exp/rec_list.js'},
    {id:'ha31', name:'Record', widgetname:'rec_viewer', script:'apps/rec_viewer.js'},
    {id:'ha32', name:'Media', title:'Record media viewer', url:'apps/rec_media.php'},
    {id:'ha33', name:'Relations', title:'Record relations viewer', url:'apps/rec_relation.php'},
    {id:'ha34', name:'Comments', title:'Discussion over record', url:'apps/rec_comments.php'},
    
    {id:'ha52', name:'Report', title:'Smarty report system', url:'apps/rep_smarty.php'},
    {id:'ha53', name:'Transform', url:'apps/rep_xslt.php'},
    {id:'ha54', name:'Crosstabs', url:'php/sync/crosstabs.php', isframe:true},
    {id:'ha61', name:'Ext Record Viewer', widgetname:'rec_viewer_ext', script:'apps/exp/rec_viewer_ext.js'},
    */

    {id:'h3_mainMenu', name:'Main Menu', widgetname:'mainMenu', script:'apps/others/mainMenu.js'},
    {id:'h3_resultList', name:'Search Result', widgetname:'resultList', script:'apps/search/resultList.js'},
    {id:'h3_recordDetails', name:'Record', widgetname:'recordDetails', script:'apps/viewers/recordDetails.js'},
    {id:'h3_recordListExt', name:'h3 ext', widgetname:'recordListExt', script:'apps/viewers/recordListExt.js'},

    {id:'ha51', name:'Map', title:'Map and timeline', widgetname:'app_timemap', script:'apps/viewers/app_timemap.js'},
    {id:'h4_map', name:'Map', widgetname:'map', script:'apps/viewers/map.js'},
    
    {id:'h4_connections', name:'Connections', widgetname:'connections', script:'apps/viewers/connections.js'}
    
];


/**
entire layout may be devided into 5 panes  : north  west  center  east south
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
var layouts = [
    {id:'L01', name:'h3 classic', theme:'smoothness', type:'cardinal', 
        north:{size:100, resizable:false,
            apps:[
                {appid:'h3_mainMenu', hasheader:false, css:{position:'absolute', top:0,left:0,height:'50',right:0, border:'none', 'background':'none'} },    //top panel
                
                {appid:'ha10', hasheader:false, css:{position:'absolute', top:50, left:180, height:40, right:200, border:'none', 'background':'none'}, options:{has_paginator:false} },   //search
                //{appid:'ha15', hasheader:false, css:{position:'absolute', top:44, left:800, height:40, width:600, border:'none', 'background':'none'} }  //pagination               
        ]},
        west:{size:230, minsize:230, apps:[{appid:'ha_search_tree', hasheader:false, css:{border:'none', 'background':'none'} }]},  //saved searches
        center:{minsize:300, dropable:false, apps:[{appid:'h3_resultList', hasheader:false, dockable:false, dragable:false }]},  //search result 
        east:{size:'50%', minsize:300, dropable:false,
            tabs:[{dockable:true, dragable:false, resizable:false,
                apps:[                                      
                    {appid:'h3_recordListExt', name: 'Record', options:{url: 'records/view/renderRecordData.php?recID=[recID]&db=[dbname]', is_single_selection:true}},    //H3 record viewer
                    {appid:'ha51'}, // H4 map V2
                    {appid:'h3_recordListExt', options:{title:'Report', url: 'viewers/smarty/showReps.html'}},     //H3 smarty  
                    {appid:'h4_connections',   options:{title:'Connections', url: 'applications/h4/page/springDiagram.php?db=[dbname]'}}  //H4 connections
                    //{appid:'h3_recordListExt', name:'Related', options:{ url:'applications/h4/page/relatedRecords.php?db=[dbname]' }} // H4 related records
                ]
            }]
        }
    },
    {id:'L04', name:'gridster example', theme:'smoothness', type:'gridster', 
        options:{widget_base_dimensions:[50, 50]},
        mainmenu: { col:1, row:1, size_x:10, size_y:1, apps:[{appid:'h3_mainMenu', hasheader:false, css:{width:'100%', height:'100%', border:'none', 'background':'none'} } ]},
        search: { col:11, row:1, size_x:10, size_y:1, apps:[{appid:'ha10', hasheader:false, css:{width:'100%', height:'100%', border:'none', 'background':'none'} } ]},    
        search_nav:{ col:1, row:2, size_x:3, size_y:10, apps:[{appid:'ha_search_tree', hasheader:false, css:{border:'none', 'background':'none'} }]},  //saved searches
        
        pane1:{col:4, row:2, size_x:7, size_y:7, dockable:true, apps:[{appid:'h3_resultList', name: 'Search result' }]},  //search result 
        pane2:{col:12, row:2, size_x:7, size_y:7, dockable:true, apps:[{appid:'h3_recordListExt', name: 'Record' }]},  
        pane5:{col:20, row:2, size_x:7, size_y:7, dockable:true, apps:[{appid:'h4_connections',   options:{title:'Connections', url: 'applications/h4/page/springDiagram.php?db=[dbname]'} }]},
        pane3:{col:4, row:9, size_x:7, size_y:7, dockable:true, apps:[ {appid:'ha51', options:{title:'Map'}} ] },  
        pane4:{col:12, row:9, size_x:7, size_y:7, dockable:true, apps:[{appid:'h3_recordListExt', options:{title:'Report', url: 'viewers/smarty/showReps.html'} }]},  

    },
    {id:'L05', name:'free example', theme:'smoothness', type:'free', 
        mainpane: {dropable:true, tabs:[{dockable:true, dragable:true, resizable:true,
            apps:[{appid:'h3_mainMenu', hasheader:false, css:{width:'100%', height:'100%', border:'none', 'background':'none'} },
                  {appid:'ha10', hasheader:false, css:{width:'100%', height:'100%', border:'none', 'background':'none'} }, 
                  {appid:'ha_search_tree', hasheader:false, css:{border:'none', 'background':'none'} },  //saved searches
                  {appid:'h3_resultList', name: 'Search result' },
                    {appid:'h3_recordListExt', name: 'Record', options:{url: 'records/view/renderRecordData.php?recID=[recID]&db=[dbname]', is_single_selection:true}},    //H3 record viewer
                    {appid:'ha51'}, // H4 map V2
                    {appid:'h3_recordListExt', options:{title:'Report', url: 'viewers/smarty/showReps.html'}},     //H3 smarty  
                    {appid:'h4_connections',   options:{title:'Connections', url: 'applications/h4/page/springDiagram.php?db=[dbname]'}}  //H4 connections
                        ]}]
        }
    },
    {id:'L02', name:'connections', theme:'smoothness',
    north:{size:40, resizable:false,
            apps:[
                {appid:'ha10', hasheader:false, css:{position:'absolute', top:44, left:180, height:40, right:200, border:'none', 'background':'none'}, options:{has_paginator:false} }   //search
        ]},    
        center:{dropable:false, apps:[
        {appid:'h4_connections', options:{title:'Connections'} }]}  //search result 
    },
    {id:'L03', name:'abandoned', theme:'smoothness',
        north:{size:88, resizable:false,
            apps:[
                {appid:'ha01', hasheader:false, css:{position:'absolute', top:0,left:0,height:44,width:'50%', border:'none', 'background':'none'} },    //about
                {appid:'ha02', hasheader:false, css:{position:'absolute', top:0,right:0,height:44,width:'50%', border:'none', 'background':'none'} },   //profile
                {appid:'ha10', hasheader:false, css:{position:'absolute', top:44,left:0,height:40,width:'90%', border:'none', 'background':'none'}, options:{has_paginator:false} },   //search
                {appid:'ha15', hasheader:false, css:{position:'absolute', top:44,right:0,height:40,width:'50%', border:'none', 'background':'none'} }  //pagination               
        ]},
        west:{size:160, minsize:160, apps:[{appid:'ha13', hasheader:false, css:{border:'none', 'background':'none'} }]},  //saved searches
        center:{minsize:300, dropable:true, apps:[{appid:'ha21', dockable:true, dragable:false }]},  //search result 
        east:{size:'50%', minsize:300, dropable:true,
            tabs:[{dockable:true, dragable:true, resizable:true,
                apps:[                                      //or viewRecord or renderRecordData
                    {appid:'ha31'},    //rec_viewer
                    {appid:'ha54'}     //crosstabs
                ]
            }]
        }
    }
];