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

{id:'ha10', name:'Search', widgetname:'search', script:'apps/search.js'},
{id:'ha11', name:'Quick Search', url:'apps/search_quick.php'},
{id:'ha12', name:'Advanced Search', url:'apps/search_advanced.php'},
{id:'ha13', name:'Saved searches', widgetname:'search_links', script:'apps/search_links.js'},
{id:'ha14', name:'Tags', url:'apps/search_tags.php'},

{id:'ha21', name:'Search result',  widgetname:'rec_list', script:'apps/rec_list.js'},

{id:'ha31', name:'Record', widgetname:'rec_viewer', script:'apps/rec_viewer.js'},
{id:'ha32', name:'Media', title:'Record media viewer', url:'apps/rec_media.php'},
{id:'ha33', name:'Relations', title:'Record relations viewer', url:'apps/rec_relation.php'},
{id:'ha34', name:'Comments', title:'Discussion over record', url:'apps/rec_comments.php'},

{id:'ha51', name:'Map', title:'Map and timeline', widgetname:'app_timemap', script:'apps/app_timemap.js'},
{id:'ha52', name:'Report', title:'Smarty report system', url:'apps/rep_smarty.php'},
{id:'ha53', name:'Transform', url:'apps/rep_xslt.php'},
{id:'ha54', name:'Crosstabs', url:'php/sync/crosstabs.php', isframe:true},

{id:'ha61', name:'Ext Record Viewer', widgetname:'rec_viewer_ext', script:'apps/rec_viewer_ext.js'}


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
{id:'l01', name:'h3 classic', theme:'smoothness',
north:{size:85, resizable:false,
        apps:[
        {appid:'ha01', hasheader:false, css:{position:'absolute', top:0,left:0,height:44,width:'49%', border:'none'} },    //databases
        {appid:'ha02', hasheader:false, css:{position:'absolute', top:0,right:0,height:44,width:'49%', border:'none'} },   //profile
        {appid:'ha10', hasheader:false, css:{position:'absolute', top:44,left:0,height:40,width:'98%', border:'none'} }
            ]},
west:{size:160, minsize:160, apps:[{appid:'ha13', hasheader:false, css:{border:'none'} }]},
center:{minsize:300, dropable:true, apps:[{appid:'ha21', dockable:true, dragable:false }]},
east:{size:'50%', minsize:300, dropable:true,
        tabs:[{dockable:true, dragable:true, resizable:true,
            apps:[                                      //or viewRecord or renderRecordData
                {appid:'ha31'},    //rec_viewer
                {appid:'ha54'}     //crosstabs
/*                ,
                {appid:'ha51'},
                {appid:'ha52'},
                {appid:'ha53'},
                {appid:'ha61', name:'H3', options:{url:'http://heuristscholar.org/h3-ao//records/view/renderRecordData.php?db=[dbname]&recID=[recID]'}},
                {appid:'ha61', name:'DoS', options:{url:'http://heuristscholar.org/dosh3/[recID]', databases:['dos_3'] }}
*/
                ]
        }]
    }
}
];