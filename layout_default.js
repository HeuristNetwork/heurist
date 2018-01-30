/**
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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
    {id:'ha01', name:'About', widgetname:'about', script:'hclient/widgets/about.js', minsize:[200,200], size:[300,300], isframe:false },
    {id:'ha02', name:'Profile', widgetname:'profile', script:'hclient/widgets/profile/profile_edit.js'},
    {id:'ha14', name:'Tags', url:'hclient/widgets/search/tag_management.php'},
    {id:'ha15', name:'Navigation', widgetname:'pagination', script:'hclient/widgets/search/pagination.js'},

    {id:'ha21', name:'Search result',  widgetname:'rec_list', script:'hclient/widgets/viewers/rec_list.js'},
    {id:'ha31', name:'Record', widgetname:'rec_viewer', script:'hclient/widgets/viewers/rec_viewer.js'},
    {id:'ha32', name:'Media', title:'Record media viewer', url:'hclient/widgets/viewers/rec_media.php'},
    {id:'ha33', name:'Relations', title:'Record relations viewer', url:'hclient/widgets/viewers/rec_relation.php'},
    {id:'ha34', name:'Comments', title:'Discussion over record', url:'hclient/widgets/viewers/rec_comments.php'},

    {id:'ha52', name:'Report', title:'Smarty report system', url:'hclient/widgets/viewers/smarty/rep_smarty.php'},
    {id:'ha53', name:'Transform', url:'hclient/widgets/viewers/transforms/rep_xslt.php'},
    {id:'ha54', name:'Crosstabs', url:'hserver/analysis/crosstabulation.php', isframe:true},
    {id:'ha61', name:'Ext Record Viewer', widgetname:'rec_viewer_ext', script:'hclient/widgets/viewers/rec_viewer_ext.js'},
    */

    {id:'h3_mainMenu', name:'Main Menu', widgetname:'mainMenu', script:'hclient/widgets/dropdownmenus/mainMenu.js'},
    {id:'h3_resultList', name:'Search Result', widgetname:'resultList', script:'hclient/widgets/viewers/resultList.js'},
    {id:'h3_recordDetails', name:'Record Viewer', widgetname:'recordDetails', script:'hclient/widgets/viewers/recordDetails.js'},
    {id:'h3_recordListExt', name:'&nbsp;&nbsp;&nbsp;', widgetname:'recordListExt', script:'hclient/widgets/viewers/recordListExt.js'},

    {id:'ha51', name:'Map-Timeline', title:'Map and timeline', widgetname:'app_timemap', script:'hclient/widgets/viewers/app_timemap.js'},  // map in iframe
    {id:'h4_static', name:'Static Page', widgetname:'staticPage', script:'hclient/widgets/viewers/staticPage.js'},

    {id:'h4_connections', name:'Network Diagram', widgetname:'connections', script:'hclient/widgets/viewers/connections.js'},

    // DIGITAL HARLEM APPS
    {id:'dh_search', name:'Search Forms', widgetname:'dh_search', script:'hclient/widgets/digital_harlem/dh_search.js'},
    {id:'dh_maps', name:'Saved Maps', widgetname:'dh_maps', script:'hclient/widgets/digital_harlem/dh_maps.js'},
    {id:'dh_results', name:'Layers', widgetname:'dh_results', script:'hclient/widgets/digital_harlem/dh_results.js'},
    {id:'dh_legend', name:'Legend', widgetname:'dh_legend', script:'hclient/widgets/digital_harlem/dh_legend.js'},

    // BORO APPS
    {id:'boro_results', name:'Search Result', widgetname:'boro_results', script:'hclient/widgets/boro/boro_results.js'},
    {id:'boro_nav', name:'Navigation', widgetname:'boro_nav', script:'hclient/widgets/boro/boro_nav.js'},
    {id:'boro_place', name:'Place', widgetname:'boro_place', script:'hclient/widgets/boro/boro_place.js'},
     
    
    //fake app - reference to another layout to include
    {id:'include_layout',name:'Inner Layout', widgetname:'include_layout'}

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
    // TODO: change the id and name to jsut HeuristDefault and Heurist Default - h4 and h3 are hangovers from old versions
    {id:'H4Default', name:'Heurist Def v3', theme:'heurist', type:'free',
        north_pane:{ dropable:false, dragable:false, 
                css:{position:'absolute', top:0,left:0,height:'6em',right:0, 
                     'min-width':'75em'}, 
            apps:[{appid:'h3_mainMenu', hasheader:false, css:{height:'100%', border:'solid'} }] 
        },
        center_pane:{ dockable:false, dropable:false, dragable:false, 
                css:{position:'absolute', top:'6em',left:0,bottom:0,right:0},
            tabs:[{dockable:false, dropable:false, dragable:false, resizable:false, layout_id:'main_header_tab',
                ccs:{position:'absolute', top:0,left:0,bottom:0,right:0,
                                border:'3px solid green'}, //height:'99%',width:'99%'

                apps:[
                    {appid:'h4_static', 
                      name: 'Manage<span class="ui-icon ui-icon-gears" style="display:inline-block;font-size:24px;margin-left:14px;margin-top:-0.2em;width:24px;height:24px;vertical-align:middle;"></span>',
                      dragable:false,
                      options:{url: 'hclient/framecontent/tabmenus/manageMenu.php?db=[dbname]', isframe:true}}
                        //,css:{position:'absolute', top:'4.5em',left:0,bottom:'0.2em',right:0, 'min-width':'75em'}}             
                    ,{appid:'h4_static', 
                    name: 'Add Data<span class="ui-icon ui-icon-addtodb-28" style="display:inline-block;margin-left:14px;vertical-align:middle;"></span>',
                        dragable:false,
                        options:{url: 'hclient/framecontent/tabmenus/addDataMenu.php?db=[dbname]', isframe:true}}
                        //,css:{position:'absolute', top:'4.5em',left:0,bottom:'0.2em',right:0,'min-width':'75em'}}
                    ,{appid:'include_layout', 
                        name: 'Filter-Analyse-Publish<span class="ui-icon ui-icon-filter-28" style="display:inline-block;margin-left:7px;vertical-align:middle;"></span>',
                        layout_id:'FAP',dragable:false,
                        options:{ref: 'SearchAnalyze'}
                        ,css:{position:'absolute', top:'5.5em',left:0,bottom:'0.1em',right:0,'font-size':'0.9em'}}
                     ]
            }]
        }
     },
      
    // WebSearch to embed into other websites
    {id:'WebSearch', name:'Heurist Embed', theme:'heurist', type:'cardinal',
        west:{size:260, minsize:150, apps:[{appid:'ha_search_tree', hasheader:false,
                options:{buttons_mode: true},
                css:{border:'none','font-size':'14px'} }]},  //saved searches
        center:{minsize:300, dropable:false, 
                apps:[{appid:'h3_resultList', hasheader:false,
                        dockable:false, dragable:false, 
                            css:{'background-color':'white','font-size':'14px'}, 
                            options:{title:'List', view_mode:'thumbs', recordview_onselect: true, 
                            show_inner_header: true} }]}  //search result
     },
      
    // original layout with united top dropdown menu - all admin/import/export features are in popup dialogs
    {id:'original', name:'Heurist Def Original', theme:'heurist', type:'cardinal',
        north:{size:'13em', resizable:false, overflow:'hidden',
            apps:[
                {appid:'h3_mainMenu', hasheader:false, css:{position:'absolute', top:0,left:0,height:'6em',right:0, border:'none', 'background':'none', 'min-width':'75em'} },    //top panel
                {appid:'h4_search', hasheader:false, 
                    css:{position:'absolute', top:'6em', left:0, height:'7em', right:0, 
                     'min-width':'75em'}, options:{has_paginator:false} },   //search '#8ea9b9'
        ]},
        west:{size:260, minsize:150, apps:[{appid:'ha_search_tree', hasheader:false, css:{border:'none', 'background':'none'} }]},  //saved searches
        center:{minsize:300, dropable:false, 
                apps:[{appid:'h3_resultList', hasheader:false, dockable:false, dragable:false, 
                            css:{'background-color':'white'}, 
                            options:{ empty_remark:null, show_menu:true, show_savefilter:true, show_inner_header: true} }]},  //search result
        east:{size:'50%', minsize:300, dropable:false,
            tabs:[{dockable:true, dragable:false, resizable:false,
                apps:[
                    {appid:'h3_recordListExt', name: 'Record View', options:{url: 'records/view/renderRecordData.php?recID=[recID]&db=[dbname]', is_single_selection:true}},    // H3 record viewer
                    {appid:'ha51'}, // map viewer (map.php) inside widget (app_timemap.js)
                    {appid:'h3_recordListExt', name: 'Custom Reports', options:{title:'Custom Reports', url: 'viewers/smarty/showReps.html?db=[dbname]'}},
                    {appid:'h4_connections',   options:{title:'Network Diagram', url: 'hclient/framecontent/visualize/springDiagram.php?db=[dbname]'}}            ]}]
        }
    },

    // 3 main tabs on top with accodion menu on each one - most of admin/import/export in iframes
    {id:'SearchAnalyze', name:'Search Analyze Publish', theme:'heurist', type:'cardinal',
    
        west:{size:260, minsize:150, apps:[{appid:'ha_search_tree', hasheader:false, 
                css:{border:'none', 'background':'none'},
                options:{btn_visible_dbstructure:false} }]},  //saved searches
                
        center:{minsize:300, dropable:false,
            apps:[{appid:'include_layout', name: 'AAA', layout_id:'FAP2',dragable:false,
                        options:{ref: 'SearchAnalyze2'}
                        ,xcss:{position:'absolute', top:'4.5em',left:0,bottom:'0.1em',right:0,'font-size':'0.9em'}}]
    
        }
    },
        
    {id:'SearchAnalyze2', name:'Search Analyze Publish2', theme:'heurist', type:'cardinal',
        north:{size:'7em', resizable:false, overflow:'hidden',
            apps:[
                {appid:'h4_search', hasheader:false, 
                css:{position:'absolute', top:0, left:0, right:0,
                border:'none', 'background':'white', 'min-width':'75em'}, 
            options:{has_paginator:false, btn_visible_newrecord:true} }, 
        ]},
        center:{minsize:300, dropable:false, apps:[{appid:'h3_resultList', hasheader:false, 
                     dockable:false, dragable:false, css:{'background-color':'white'}, 
                     options:{empty_remark:null, show_menu:true, show_savefilter:true, show_inner_header:true} }]},  //search result
        east:{size:'50%', minsize:300, dropable:false,
            tabs:[{dockable:true, dragable:false, resizable:false,
                apps:[
                    {appid:'h3_recordListExt', name: 'Record View', 
                                options:{url: 'records/view/renderRecordData.php?recID=[recID]&db=[dbname]', 
                                is_single_selection:true, 'data-logaction':'viewRecord'}},    // H3 record viewer
                    {appid:'ha51', options:{'data-logaction':'viewMapTime'}}, // map viewer (map.php) inside widget (app_timemap.js)
                    {appid:'h3_recordListExt', name: 'Custom Reports', options:{title:'Custom Reports', 
                                    url: 'viewers/smarty/showReps.html?db=[dbname]', 'data-logaction':'viewReports'}},
                    {appid:'h4_static', name: 'Export',
                        options:{url: 'hclient/framecontent/tabmenus/exportMenu.php?db=[dbname]',
                                         isframe:true, 'data-logaction':'viewExport'}
                        ,css:{position:'absolute', top:0,left:0,bottom:0,right:0,'min-width':'75em'}},
                    
                    {appid:'h4_connections',   options:{title:'Network Diagram',
                                     url: 'hclient/framecontent/visualize/springDiagram.php?db=[dbname]',
                                     'data-logaction':'viewNetwork'}},
 
                    {appid:'h3_recordListExt', name: 'Crosstabs', options:{title:'Crosstabs', 
                                url: 'viewers/crosstab/crosstabs.php?db=[dbname]','data-logaction':'viewCrosstabs'}}
                    
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
    {appid:'h4_connections',   options:{title:'Network', url: 'hclient/framecontent/visualize/springDiagram.php?db=[dbname]'}}  //H4 connections
    ]}]
    }
    },
    */

    {id:'boro', name:'Beyond 1914 - Book of Rememberance', theme:'heurist', type:'free', 
                cssfile:'hclient/widgets/boro/beyond1914.css', template: 'hclient/widgets/boro/boro_main.html'
       //widgets will be loaded into divs with id "result_pane" and "search_pane" in boro_main.html         
       ,boro_place:{dropable:false,css:{},
                apps:[                           
                //creates navigation menu and loads info pages
                {appid:'boro_place', 
                    options:{view_mode:'list', show_toolbar: false, select_mode:'select_single', 
                                multiselect:false, show_menu: false},
                hasheader:false, css:{border:'none', 'background':'none'} }]}
       ,result_pane:{dropable:false,css:{},
                apps:[{appid:'boro_results', 
                    options:{view_mode:'list', show_toolbar: false, select_mode:'select_single', 
                    multiselect:false, show_menu: false},
                hasheader:true, css:{border:'none', 'background':'none'} }]}
       ,search_pane:{dropable:false,css:{},
                apps:[                           
                //prod 
                {appid:'dh_search', options:{UGrpID:48, search_at_init:152}, hasheader:false, css:{border:'none', 'background':'none'} }]}  //faceted/forms searches
                //dev
                //{appid:'dh_search', options:{UGrpID:18, Xsearch_at_init:110}, hasheader:false, css:{border:'none', 'background':'none'} }]}  //faceted/forms searches
       ,boro_nav:{dropable:false,css:{},
                apps:[                           
                //creates navigation menu and loads info pages
                {appid:'boro_nav', options:{menu_div:'bor-navbar-collapse'}, hasheader:false, 
                            css:{border:'none', 'background':'none'} }]}
/*       ,search_pane:{dropable:false,apps:[{appid:'boro_search', options:{}, hasheader:false, css:{border:'none', 'background':'none'} }]}
       ,result_pane:{dropable:false,apps:[{appid:'boro_reslist', options:{}, hasheader:false, css:{border:'none', 'background':'none'} }]} */
    },
    
    {id:'DigitalHarlem', name:'Digital Harlem', theme:'heurist', type:'cardinal', cssfile:'hclient/widgets/digital_harlem/dh_style.css',
        north:{size:140, resizable:false, overflow:'hidden',
            apps:[
                {appid:'h4_static', hasheader:false,
                    options:{url: 'hclient/widgets/digital_harlem/dh_header.php?db=[dbname]&app=[layout]'}
                    //css:{width:'100%',height:'100%'}},
                    ,css:{position:'absolute', top:0,left:0,bottom:0,right:0, border:'none', 'background':'none', 'min-width':'75em'}},    //top panel
        ]},
        west:{size:270, resizable:false, apps:[
            //{appid:'h4_search', hasheader:false, css:{position:'absolute', top:'0', height:'0', border:'none', display:'none'}, options:{has_paginator:false, isloginforced:false} },
            {appid:'dh_search', options:{UGrpID:1007}, hasheader:false, css:{border:'none', 'background':'none'} }]},  //faceted/forms searches
        center:{minsize:300, dropable:false,
            tabs:[{dockable:false, dragable:false, resizable:false,
                apps:[
                    {appid:'ha51', options:{layout:['map','timeline'], startup:61557, eventbased:false} } //mapping
                    ,{appid:'h3_resultList', hasheader:true, name: 'List', 
                        options:{empty_remark:null, title:'List', show_viewmode:false, eventbased:false} }
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
                    options:{url: 'hclient/widgets/digital_harlem/dh_footer.php?db=[dbname]&app=[layout]'}
                    //old way options:{url: 'hclient/widgets/digital_harlem/dh_footer.html'}
                    ,css:{position:'absolute', top:0,left:0,bottom:0,right:0}}
        ]},
    },

    {id:'DigitalHarlem1935', name:'Digital Harlem 1935', theme:'heurist', type:'cardinal', cssfile:'hclient/widgets/digital_harlem/dh_style1935.css',
        north:{size:140, resizable:false, overflow:'hidden',
            apps:[
                {appid:'h4_static', hasheader:false,
                    options:{url: 'hclient/widgets/digital_harlem/dh_header.php?db=[dbname]&app=[layout]'}
                    //css:{width:'100%',height:'100%'}},
                    ,css:{position:'absolute', top:0,left:0,bottom:0,right:0, border:'none', 'background':'none', 'min-width':'75em'}},    //top panel
        ]},
        west:{size:270, resizable:false, apps:[
            //{appid:'h4_search', hasheader:false, css:{position:'absolute', top:'0', height:'0', border:'none', display:'none'}, options:{has_paginator:false, isloginforced:false} },
            {appid:'dh_search', options:{UGrpID:1010}, hasheader:false, css:{border:'none', 'background':'none'} }]},  //faceted/forms searches
        center:{minsize:300, dropable:false,
            tabs:[{dockable:false, dragable:false, resizable:false,
                apps:[
                    {appid:'ha51', options:{layout:['map','timeline'], startup:61557, eventbased:false} } //mapping
                    ,{appid:'h3_resultList', hasheader:true, name: 'List', 
                        options:{empty_remark:null, title:'List', show_viewmode:false, eventbased:false} }
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
                    options:{url: 'hclient/widgets/digital_harlem/dh_footer.php?db=[dbname]&app=[layout]'}
                    //old static version options:{url: 'hclient/widgets/digital_harlem/dh_footer.html'}
                    ,css:{position:'absolute', top:0,left:0,bottom:0,right:0}}
        ]},
    },


];


