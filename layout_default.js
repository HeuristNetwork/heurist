/**
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

    {id:'heurist_Search', name:'Search', widgetname:'search', script:'hclient/widgets/search/search.js'},
    {id:'heurist_SearchTree', name:'Saved searches', widgetname:'svs_list', script:'hclient/widgets/search/svs_list.js'},
    {id:'heurist_Navigation', name:'Navigation', widgetname:'navigation', script:'hclient/widgets/dropdownmenus/navigation.js'},
    {id:'heurist_Groups', name:'Groups'},{id:'heurist_Cardinals', name:'Cardinal layout'},


    {id:'heurist_mainMenu', name:'Main Menu', widgetname:'mainMenu', script:'hclient/widgets/dropdownmenus/mainMenu.js'},
    {id:'heurist_mainMenu6', name:'Main Side Menu', widgetname:'mainMenu6', script:'hclient/widgets/dropdownmenus/mainMenu6.js'},
    {id:'heurist_resultList', name:'Search Result', widgetname:'resultList', script:'hclient/widgets/viewers/resultList.js'},
    {id:'heurist_resultListDataTable', name:'List View', widgetname:'resultListDataTable', script:'hclient/widgets/viewers/resultListDataTable.js'},
    {id:'heurist_resultListExt', name:'&nbsp;&nbsp;&nbsp;', widgetname:'recordListExt', script:'hclient/widgets/viewers/recordListExt.js'},
    {id:'heurist_resultListCollection', name:'Records Collection', widgetname:'resultListCollection', script:'hclient/widgets/viewers/resultListCollection.js'},

    {id:'heurist_Map', name:'Map (old)', title:'Map and timeline', widgetname:'app_timemap', script:'hclient/widgets/viewers/app_timemap.js'},  // map in iframe
    {id:'heurist_Map2', name:'Map-Timeline', title:'Map and timeline', widgetname:'app_timemap', script:'hclient/widgets/viewers/app_timemap.js'},  // map in iframe
    {id:'heurist_Frame', name:'Static Page', widgetname:'staticPage', script:'hclient/widgets/viewers/staticPage.js'},

    {id:'heurist_Graph', name:'Network Diagram', widgetname:'connections', script:'hclient/widgets/viewers/connections.js'},

    {id:'heurist_recordAddButton', name:'Add Record', widgetname:'recordAddButton', script:'hclient/widgets/record/recordAddButton.js'},
    
    // DIGITAL HARLEM APPS
    {id:'dh_search', name:'Search Forms', widgetname:'dh_search', script:'hclient/widgets/digital_harlem/dh_search.js'},
    {id:'dh_maps', name:'Saved Maps', widgetname:'dh_maps', script:'hclient/widgets/digital_harlem/dh_maps.js'},
    {id:'dh_results', name:'Layers', widgetname:'dh_results', script:'hclient/widgets/digital_harlem/dh_results.js'}, //not used
    {id:'dh_legend', name:'Legend', widgetname:'dh_legend', script:'hclient/widgets/digital_harlem/dh_legend.js'}, //not used

    //ExpertNation APPS
    {id:'expertnation_results', name:'Search Result', widgetname:'expertnation_results', script:'hclient/widgets/expertnation/expertnation_results.js'},
    {id:'expertnation_nav', name:'Navigation', widgetname:'expertnation_nav', script:'hclient/widgets/expertnation/expertnation_nav.js'},
    {id:'expertnation_place', name:'Place', widgetname:'expertnation_place', script:'hclient/widgets/expertnation/expertnation_place.js'},
     
    
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
    {id:'H5Default', name:'Heurist Def v5', theme:'heurist', type:'free',
        north_pane:{ dropable:false, dragable:false, 
                css:{position:'absolute', top:0,left:0,height:'6em',right:0, 
                     'min-width':'75em'}, 
            apps:[{appid:'heurist_mainMenu', hasheader:false, css:{height:'100%', border:'solid'}}] 
        },
        center_pane:{ dockable:false, dropable:false, dragable:false, 
                css:{position:'absolute', top:'6em',left:0,bottom:0,right:0},
            apps:[{appid:'include_layout', 
                        name: 'Filter-Analyse-Publish',
                        layout_id:'FAP',dragable:false,
                        options:{ref: 'SearchAnalyze'}
                        ,css:{position:'absolute', top:'0',left:0,bottom:'0.1em',right:0}}]
        }    
    },

    {id:'H6Default', name:'Heurist Def v6', theme:'heurist', type:'free',
        north_pane:{ dropable:false, dragable:false, 
                css:{position:'absolute', top:0,left:0,height:'50px',right:'-2px', 
                     'min-width':'75em'}, 
            apps:[{appid:'heurist_mainMenu', hasheader:false, css:{height:'100%', border:'solid'}}] 
        },
        center_pane:{ dockable:false, dropable:false, dragable:false, 
                css:{position:'absolute', top:'50px',left:0,bottom:'0.1em',right:'2px'},
            apps:[{appid:'heurist_mainMenu6', hasheader:false, css:{width:'100%'}}]
        }    
    },
    
    // WebSearch to embed into other websites
    {id:'WebSearch', name:'Heurist Embed', theme:'heurist', type:'cardinal',
        west:{size:300, minsize:150, apps:[{appid:'heurist_SearchTree', hasheader:false,
                options:{buttons_mode: true},
                css:{border:'none','font-size':'14px'} }]},  //saved searches
                
        center:{minsize:300, dropable:false,
            tabs:[{dockable:false, dragable:false, resizable:false,
                apps:[
                    {appid:'heurist_resultList', hasheader:true, name:'List', layout_id:'list',
                            css:{'background-color':'white','font-size':'14px'}, 
                            options:{title:'List', view_mode:'thumbs', recordview_onselect: 'popup', 
                            show_inner_header: true, show_url_as_link:true} },  //search result
                    {appid:'heurist_Map', layout_id:'map', options:{layout:['map','timeline'],tabpanel:true}, 
                                    css:{'background-color':'white'} } //mapping
                ]
            }]
        }
     },

    // 3 main tabs on top with accordion menu on each one - most of admin/import/export in iframes
    {id:'SearchAnalyze', name:'Search Analyze Publish', theme:'heurist', type:'cardinal',
    
        west:{size:260, Xminsize:150, apps:[{appid:'heurist_SearchTree', hasheader:false, 
                css:{border:'none', 'background':'none'},
                options:{btn_visible_dbstructure:true} }]},  //saved searches
                
        center:{Xminsize:300, dropable:false,
            apps:[{appid:'include_layout', name: 'AAA', layout_id:'FAP2',dragable:false,
                        options:{ref: 'SearchAnalyze2'}
                        ,css:{position:'absolute', top:0,left:0,bottom:'0.1em',right:0}}] //,'font-size':'0.9em'
    
        }
    },

    // old version - search panel on top, center - result list, east - tabs        
    {id:'SearchAnalyze2', name:'Search Analyze Publish2', theme:'heurist', type:'cardinal',
        north:{size:'8em', resizable:false, overflow:'hidden',
            apps:[
                {appid:'heurist_Search', hasheader:false, 
                css:{position:'absolute', top:0, left:0, right:0,bottom:0,
                border:'none', 'background':'white', 'min-width':'75em'}, 
            options:{has_paginator:false, btn_visible_newrecord:true, search_button_label:'Filter'} }, 
        ]},
        center:{Xminsize:300, dropable:false, apps:[{appid:'heurist_resultList', hasheader:false, 
                     dockable:false, dragable:false, css:{'background-color':'white','font-size':'0.9em'}, //AO 2020-01-30 ,'font-size':'12px'
                     options:{empty_remark:null, show_menu:true, support_collection:true, 
                     show_savefilter:true, show_inner_header:true, header_class:'ui-heurist-header2',show_url_as_link:true} }]},
        east:{size:'50%', Xminsize:300, dropable:false,
            tabs:[{dockable:true, dragable:false, resizable:false, adjust_positions:true, //css:{'font-size':'0.95em'},
                apps:[
                    {appid:'heurist_resultListExt', name: 'Record View', 
                                options:{url: 'viewers/record/renderRecordData.php?recID=[recID]&db=[dbname]', 
                                is_single_selection:true, 'data-logaction':'open_Record'}
                    },    // H3 record viewer
                    {appid:'heurist_resultListDataTable', name: 'List View', options:{ dataTableParams:{}, show_export_buttons:true } },
                    {appid:'heurist_Map', options:{'data-logaction':'open_MapTime'}}, // map viewer (map.php) inside widget (app_timemap.js)
                    {appid:'heurist_Map2', options:{'data-logaction':'open_MapTime', leaflet:true
                        , layout_params:{legend:'search,-basemaps,-mapdocs,250,off'} }}, 
                    
                    {appid:'heurist_resultListExt', name: 'Custom Reports', options:{title:'Custom Reports', 
                                    url: 'viewers/smarty/showReps.html?db=[dbname]', 'data-logaction':'open_Reports'}
                    },
                    {appid:'heurist_Frame', name: 'Export',
                        options:{url: 'hclient/framecontent/exportMenu.php?db=[dbname]',
                                         isframe:true, 'data-logaction':'open_Export'}
                        ,css:{position:'absolute', top:0,left:0,bottom:0,right:0,'min-width':'75em'}},
                    
                    {appid:'heurist_Graph',   options:{title:'Network Diagram',
                                     url: 'hclient/framecontent/visualize/springDiagram.php?db=[dbname]',
                                     'data-logaction':'open_Network'}},
 
                    {appid:'heurist_resultListExt', name: 'Crosstabs', options:{title:'Crosstabs', 
                                url: 'viewers/crosstab/crosstabs.php?db=[dbname]','data-logaction':'open_Crosstabs'}}
                    
            ]}]
        }
    },

    // Heurist v6 version. It is inited in mainMenu6.js
    {id:'SearchAnalyze3', name:'Search Analyze Publish2', theme:'heurist', type:'cardinal',
        center:{minsize:156, dropable:false, apps:[{appid:'heurist_resultList', hasheader:false,
                     dockable:false, dragable:false, css:{'background-color':'white','font-size':'0.9em'}, //AO 2020-01-30 ,'font-size':'12px'
                     options:{empty_remark:null, show_menu:true, support_collection:true, is_h6style:true,
                     XXXrecordDivEvenClass: 'ui-widget-content',
                     show_savefilter:false, show_search_form:true, show_inner_header:true, 
                     show_url_as_link:true} }]},
        east:{size:'50%', Xminsize:300, dropable:false,
            tabs:[{dockable:true, dragable:false, resizable:false, adjust_positions:true, //css:{'font-size':'0.95em'},
                apps:[
                    {appid:'heurist_resultListExt', name: 'Record View', 
                                options:{url: 'viewers/record/renderRecordData.php?recID=[recID]&db=[dbname]', 
                                is_single_selection:true, 'data-logaction':'open_Record'}
                    },    // H3 record viewer
                    {appid:'heurist_resultListDataTable', name: 'List View', options:{ dataTableParams:{}, show_export_buttons:true } },
                    //{appid:'heurist_Map', options:{'data-logaction':'open_MapTime'}}, // map viewer (map.php) inside widget (app_timemap.js)
                    {appid:'heurist_Map2', options:{'data-logaction':'open_MapTime', leaflet:true
                        , layout_params:{legend:'search,-basemaps,-mapdocs,250,off'} }}, 
                    
                    {appid:'heurist_resultListExt', name: 'Custom Reports', options:{title:'Custom Reports', 
                                    url: 'viewers/smarty/showReps.html?db=[dbname]', 'data-logaction':'open_Reports'}
                    },
                    {appid:'heurist_Frame', name: 'Export',
                        options:{url: 'hclient/framecontent/exportMenu.php?db=[dbname]',
                                         isframe:true, 'data-logaction':'open_Export'}
                        ,css:{position:'absolute', top:0,left:0,bottom:0,right:0,'min-width':'75em'}},
                        
                    {appid:'heurist_Graph',   options:{title:'Network Diagram',
                                     url: 'hclient/framecontent/visualize/springDiagram.php?db=[dbname]',
                                     'data-logaction':'open_Network'}},
 
                    {appid:'heurist_resultListExt', name: 'Crosstabs', options:{title:'Crosstabs', 
                                url: 'viewers/crosstab/crosstabs.php?db=[dbname]','data-logaction':'open_Crosstabs'}}
                    
            ]}]
        }
    },

    
    // Position of widgets are specified in CSS, all widgets can be dragged around and repositioned
    /* This layout needs to be defiend spcifically to be useful
    {id:'FreeLayout', name:'Free example', theme:'heurist', type:'free',
    mainpane: {dropable:true, tabs:[{dockable:true, dragable:true, resizable:true,
    apps:[{appid:'heurist_mainMenu', hasheader:false, css:{width:'100%', border:'none', 'background':'none'} },
    {appid:'heurist_Search', hasheader:false, css:{width:'100%', border:'none', 'background':'none'} },
    {appid:'heurist_SearchTree', hasheader:false, css:{border:'none', 'background':'none'} },  //saved searches
    {appid:'heurist_resultList', name: 'Search result' },
    {appid:'heurist_resultListExt', name: 'Record', options:{url: 'viewers/record/renderRecordData.php?recID=[recID]&db=[dbname]', is_single_selection:true}},    //H3 record viewer
    {appid:'heurist_Map'}, // H4 map V2
    {appid:'heurist_resultListExt', options:{title:'Report', url: 'viewers/smarty/showReps.html'}},     //H3 smarty
    {appid:'heurist_Graph',   options:{title:'Network', url: 'hclient/framecontent/visualize/springDiagram.php?db=[dbname]'}}  //H4 connections
    ]}]
    }
    },
    */

    {id:'Beyond1914', name:'Beyond 1914 / Book of Remembrance, University of Sydney, built with Heurist', theme:'heurist', type:'free', 
                cssfile:['hclient/widgets/expertnation/expertnation.css','hclient/widgets/expertnation/beyond1914.css'],
                template: 'hclient/widgets/expertnation/beyond1914_main.html'
       //widgets will be loaded into divs with id "result_pane" and "search_pane" in beyond1914_main.html         
       ,expertnation_place:{dropable:false,css:{},
                apps:[                           
                {appid:'expertnation_place', 
                    options:{view_mode:'list', show_toolbar: false, select_mode:'select_single', 
                                multiselect:false, show_menu: false},
                hasheader:false, css:{border:'none', 'background':'none'} }]}
       ,result_pane:{dropable:false,css:{},
                apps:[{appid:'expertnation_results', 
                    options:{view_mode:'list', show_toolbar: false, select_mode:'select_single', 
                    multiselect:false, show_menu: false, hasheader:false},
                hasheader:false, css:{border:'none', 'background':'none'} }]}
       ,search_pane:{dropable:false,css:{},
                apps:[                           
                //prod 
                {appid:'dh_search', options:{UGrpID:48, search_at_init:152, uni_ID:4705}, 
                    hasheader:false, css:{border:'none', 'background':'none'} }]       }  
                //faceted/forms searches
                //dev
                //{appid:'dh_search', options:{UGrpID:18, Xsearch_at_init:110}, hasheader:false, css:{border:'none', 'background':'none'} }]}  //faceted/forms searches
       ,expertnation_nav:{dropable:false,css:{},
                apps:[                           
                //creates navigation menu and loads info pages
                {appid:'expertnation_nav', options:{menu_div:'bor-navbar-collapse', search_UGrpID:48, uni_ID:4705}, hasheader:false, 
                            css:{border:'none', 'background':'none'} }]}
    },

    {id:'UAdelaide', name:'November 1918, University of Adelaide, built with Heurist', theme:'heurist', type:'free', 
                cssfile:['hclient/widgets/expertnation/expertnation.css',
                'https://global.adelaide.edu.au/style-guide-v2/latest/css/global-assets.css',
                'https://global.adelaide.edu.au/style-guide-v2/0.24.0/css/header-footer.css',
                'hclient/widgets/expertnation/uadelaide.css'], 
                template: 'hclient/widgets/expertnation/uadelaide_main.html'
       //widgets will be loaded into divs with id "result_pane" and "search_pane" in uadelaide_main.html         
       ,expertnation_place:{dropable:false,css:{},
                apps:[                           
                {appid:'expertnation_place', 
                    options:{view_mode:'list', show_toolbar: false, select_mode:'select_single', 
                                multiselect:false, show_menu: false},
                hasheader:false, css:{border:'none', 'background':'none'} }]}
       ,result_pane:{dropable:false,css:{},
                apps:[{appid:'expertnation_results', 
                    options:{view_mode:'list', show_toolbar: false, select_mode:'select_single', 
                    multiselect:false, show_menu: false},
                hasheader:false, css:{border:'none', 'background':'none'} }]}
       ,search_pane:{dropable:false,css:{},
                apps:[                           
                //prod 
                {appid:'dh_search', options:{UGrpID:55, search_at_init:196, uni_ID:4710}, hasheader:false, 
                css:{border:'none', 'background':'none'} }]         
       }
       ,expertnation_nav:{dropable:false,css:{},
                apps:[                           
                //creates navigation menu and loads info pages
                {appid:'expertnation_nav', options:{menu_div:'bor-navbar-collapse', search_UGrpID:55, uni_ID:4710}, hasheader:false, 
                            css:{border:'none', 'background':'none'} }]}
    },

    
    {id:'DigitalHarlem', name:'Digital Harlem', theme:'heurist', type:'cardinal', cssfile:'hclient/widgets/digital_harlem/dh_style.css',
        north:{size:140, resizable:false, overflow:'hidden',
            apps:[
                {appid:'heurist_Frame', hasheader:false,
                    options:{url: 'hclient/widgets/digital_harlem/dh_header.php?db=[dbname]&app=[layout]'}
                    //css:{width:'100%',height:'100%'}},
                    ,css:{position:'absolute', top:0,left:0,bottom:0,right:0, border:'none', 'background':'none', 'min-width':'75em'}},    //top panel
        ]},
        west:{size:270, resizable:false, apps:[
            //{appid:'heurist_Search', hasheader:false, css:{position:'absolute', top:'0', height:'0', border:'none', display:'none'}, options:{has_paginator:false, isloginforced:false} },
            {appid:'dh_search', options:{UGrpID:1007}, hasheader:false, css:{border:'none', 'background':'none'} }]},  //faceted/forms searches
        center:{minsize:300, dropable:false,
            tabs:[{dockable:false, dragable:false, resizable:false, adjust_positions:true,
                apps:[
                    {appid:'heurist_Map', options:{layout:['map','timeline'], mapdocument:61557, eventbased:false, published:1} } //mapping
                    ,{appid:'heurist_resultList', hasheader:true, name: 'List', 
                        options:{empty_remark:null, title:'List', show_viewmode:false, eventbased:false} }
                    //,{appid:'heurist_Frame', hasheader:true, name: 'DH Blog', options:{url: 'http://digitalharlemblog.wordpress.com/'} }
                ]
            }]
        },
        east:{size:300, minsize:300, dropable:false,
            tabs:[{dockable:false, dragable:false, resizable:false,
                apps:[
                    {appid:'dh_maps', name: 'Maps'},     // saved searches(maps)
                    {appid:'heurist_Frame', name:'Legend', options:{title:'Legend', url: 'hclient/widgets/digital_harlem/dh_legend.php?db=[dbname]'}}
                ]
            }]
        },
        south:{size:40, resizable:false, overflow:'hidden',
            apps:[
                {appid:'heurist_Frame', hasheader:false,
                    options:{url: 'hclient/widgets/digital_harlem/dh_footer.php?db=[dbname]&app=[layout]'}
                    //old way options:{url: 'hclient/widgets/digital_harlem/dh_footer.html'}
                    ,css:{position:'absolute', top:0,left:0,bottom:0,right:0}}
        ]},
    },

    {id:'DigitalHarlem1935', name:'Digital Harlem 1935', theme:'heurist', type:'cardinal', cssfile:'hclient/widgets/digital_harlem/dh_style1935.css',
        north:{size:140, resizable:false, overflow:'hidden',
            apps:[
                {appid:'heurist_Frame', hasheader:false,
                    options:{url: 'hclient/widgets/digital_harlem/dh_header.php?db=[dbname]&app=[layout]'}
                    //css:{width:'100%',height:'100%'}},
                    ,css:{position:'absolute', top:0,left:0,bottom:0,right:0, border:'none', 'background':'none', 'min-width':'75em'}},    //top panel
        ]},
        west:{size:270, resizable:false, apps:[
            //{appid:'heurist_Search', hasheader:false, css:{position:'absolute', top:'0', height:'0', border:'none', display:'none'}, options:{has_paginator:false, isloginforced:false} },
            {appid:'dh_search', options:{UGrpID:1010}, hasheader:false, css:{border:'none', 'background':'none'} }]},  //faceted/forms searches
        center:{minsize:300, dropable:false,
            tabs:[{dockable:false, dragable:false, resizable:false, adjust_positions:true,
                apps:[
                    {appid:'heurist_Map', options:{layout:['map','timeline'], mapdocument:61557, eventbased:false, published:1} } //mapping
                    ,{appid:'heurist_resultList', hasheader:true, name: 'List', 
                        options:{empty_remark:null, title:'List', show_viewmode:false, eventbased:false} }
                    //,{appid:'heurist_Frame', hasheader:true, name: 'DH Blog', options:{url: 'http://digitalharlemblog.wordpress.com/'} }
                ]
            }]
        },
        east:{size:300, minsize:300, dropable:false,
            tabs:[{dockable:false, dragable:false, resizable:false,
                apps:[
                    {appid:'dh_maps', name: 'Maps'},     // saved searches(maps)
                    {appid:'heurist_Frame', name:'Legend', options:{title:'Legend', url: 'hclient/widgets/digital_harlem/dh_legend.php?db=[dbname]'}}
                ]
            }]
        },
        south:{size:40, resizable:false, overflow:'hidden',
            apps:[
                {appid:'heurist_Frame', hasheader:false,
                    options:{url: 'hclient/widgets/digital_harlem/dh_footer.php?db=[dbname]&app=[layout]'}
                    //old static version options:{url: 'hclient/widgets/digital_harlem/dh_footer.html'}
                    ,css:{position:'absolute', top:0,left:0,bottom:0,right:0}}
        ]},
    },


];


