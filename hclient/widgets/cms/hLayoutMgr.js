/*
* hLayoutMgr.js - web page generator based on json configuration
* 
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

var layoutMgr;  

function hLayoutMgr(){

    var _className = "hLayoutMgr";

    var pnl_counter = 1;

    var body = $(this.document).find('body');
    
    var isEditMode = false;
    
    var _supp_options = null; //defined in layoutInit dynamic options with current status params
    
    var _main_layout_cfg = null;

    //
    // assign unique key property for all layer elements (in array "key" in html "data-lid")
    //
    function _layoutInitKey(layout, i){
        
        if(!layout[i].key){
            layout[i].key = pnl_counter;
            layout[i].title = '<span data-lid="'+pnl_counter+'">' + layout[i].name 
                                +'</span>';
            layout[i].folder = (layout[i].children && layout[i].children.length>0);
        
            pnl_counter++;
        }
    }
    
    //---------------------------------------
    //
    // layout - JSON
    // container - id or element
    //
    function _layoutInit(layout, container, isFirstLevel){
        
        container = $(container);
        
        if(layout==null){
            //take layout from container
            layout = container.html();
            var res = window.hWin.HEURIST4.util.isJSON(layout);
            if(res!==false){
                layout = res;    
            }
        }
        
        container.empty();   
        
        if(typeof layout === 'string' &&
            layout.indexOf('data-heurist-app-id')>0){ //old format with some widgets
            
            container.html(layout);
            
            //'#main-content'
                
            window.hWin.HAPI4.LayoutMgr.appInitFromContainer( null, container, _supp_options );
            return false;
        }
        
        var res = window.hWin.HEURIST4.util.isJSON(layout);
        
        if(res===false){
            //this is not json - HTML
            //if(layout==''){ layout = 'Add content here'}
            
            layout = [{name:'Page', type:'group',
                    children:[
                        {name:'Content', type:'text', css:{}, content: layout}
                    ] 
                }]; 
        }else{
            layout = res;    
        }
        
        if(!$.isArray(layout)){
            layout = [layout];    
        }

        if(isFirstLevel===true && _supp_options){
            
            if(_supp_options.page_name){
                layout[0].name  = 'Page'; //_supp_options.page_name;
            }
            if(_supp_options.keep_top_config && isEditMode){
                _main_layout_cfg = layout;
            }
        }
        
        
        for(var i=0; i<layout.length; i++){
            
            _layoutInitKey(layout, i);
            
            var ele = layout[i];
            
            if(ele.type=='cardinal'){
                
                _layoutInitCardinal(ele, container);
                
            }else if(ele.type=='tabs'){
                
                _layoutInitTabs(ele, container);
                
            }else if(ele.type=='accordion'){
             
                _layoutInitAccordion(ele, container);
                
            }else if(ele.children && ele.children.length>0){ //free, flex or group
                
                _layoutInitGroup(ele, container);
                
            }else if( (ele.type && ele.type.indexOf('text')==0) || ele.content){
                //text elements
                _layoutInitText(ele, container);
                
            }else if(ele.type=='widget' || ele.appid){
                //widget element
                
                _layoutAddWidget(ele, container);
                
            }
        }//for
        
        return layout;
    }//_layoutInit
    
    //
    // creates new div
    //
    function _layoutCreateDiv( layout, classes ){

        var $d = $(document.createElement('div'));
        
        
        if(layout.dom_id && layout.dom_id.indexOf('cms-tabs-')===0){
            //assign unique identificator (for cardinal, tabs, accordion)
            //id is reassigned on every page reload
            layout.dom_id = 'cms-tabs-' + layout.key;  
        }else if(!layout.dom_id){
        
        
            if(layout.appid && _main_layout_cfg!=null){
                //assign search_realm and map_widget_id
                var widget_name = layout.appid;
                if(!layout.options) layout.options = {};
                //find map widget on this page
                if(widget_name=='heurist_StoryMap'){
                    if(!layout.options.map_widget_id){
                        var ele = layoutMgr.layoutContentFindWidget(_main_layout_cfg, 'heurist_Map');
                        //if(ele) console.log(ele.options); //ele.options.widget_id ele.dom_id
                        if(ele && ele.dom_id){                        
                            window.hWin.HEURIST4.msg.showMsgDlg('For full fnctionality Story map has to be linked with Map/timeline element. '
                            +' Connect it to map widget "'+ele.dom_id+'" ?', 
                            function(){ 
                                ele.options.search_realm = '';
                                layout.options.map_widget_id = ele.dom_id;
                            },null,{default_palette_class: 'ui-heurist-publish'});
                        }
                    }
                }
                
                //find and assign prevail search group (except heurist_Map if heurist_StoryMap exists)
                if(!layout.options.search_realm){ //not defined yet
                
                    var need_assign = true;
                    
                    if(widget_name=='heurist_Map')
                    {
                        var ele = layoutMgr.layoutContentFindWidget(_main_layout_cfg, 'heurist_StoryMap');
                        if(ele && !ele.options.map_widget_id != layout.dom_id){

                            need_assign = false;        
                            window.hWin.HEURIST4.msg.showMsgDlg('For full fnctionality Story map has to be linked with Map/timeline element. '
                                +' Link map widget to Story map "'+ele.dom_id+'" ?', 
                                function(){ 
                                    layout.options.search_realm = '';
                                    ele.options.map_widget_id = layout.dom_id;
                            },null,{default_palette_class: 'ui-heurist-publish'});
                        }
                        
                    }
                    //assign search realm
                    if(need_assign){
                        var sg = layoutMgr.layoutContentFindMainRealm(_main_layout_cfg);    
                        if(sg=='') sg = 'search_group_1';
                        layout.options.search_realm = sg;
                    }
                }
            }
         
            // assign id for new content and widgety divs
            // it is saved in configuration
            var suffix = '', cnt = 0;
            do{
                if(layout.appid){
                    layout.dom_id = 'cms-widget-' + layout.key + suffix;
                }else{
                    layout.dom_id = 'cms-content-' + layout.key + suffix;
                }
                //check that it is unique on this page
                cnt++;
                suffix = '_' + cnt;
                if(cnt>1){
                    console.log(layout.dom_id);
                }
            }while (body.find('#'+layout.dom_id).length>0);
            
        }
        
        
        $d.attr('id', layout.dom_id)
          .attr('data-hid', layout.key); //.attr('data-lid', layout.key);
        
        if(classes){
            $d.addClass(classes);
        } 
        if(layout.classes){ //custom classes
            $d.addClass(layout.classes);
        }
        
        return $d;        
    }

    function _layoutInitGroup(layout, container){
        
        //create parent div
        var $d = _layoutCreateDiv(layout, 'cms-element brick');
        
        $d.appendTo(container);
                
        if(isEditMode){
            //$d.css({'border':'2px dotted gray','border-radius':'4px','margin':'4px'});  
        }
        
        if(!layout.css) layout.css = {};
        if($.isEmptyObject(layout.css)){ //default
            //AAA layout.css = {'border':'1px dotted gray','border-radius':'4px','margin':'4px'};
        }

        if(layout.css && !$.isEmptyObject(layout.css)){
            $d.css(layout.css);
        }
        
        _layoutInit(layout.children, $d);
        
    }
    function _layoutInitText(layout, container){
        
        var $d = _layoutCreateDiv(layout, 'editable tinymce-body cms-element brick');

        $d.appendTo(container);
            
        if(!layout.css) layout.css = {};
        if($.isEmptyObject(layout.css)){ //default
            //AAA layout.css = {'border':'1px dotted gray','border-radius':'4px','margin':'4px'};
        }
            
        if(layout.css && !$.isEmptyObject(layout)){
            $d.css( layout.css );    
        }

        $d.html(layout.content);
    }
    
    //
    // layout - json configuration
    // container - if not defined - it tries to find current one
    //
    function _layoutAddWidget(layout, container){

        var $d = _layoutCreateDiv(layout, 'editable heurist-widget cms-element brick');

        //remove previous one
        var old_widget = container.find('div[data-hid='+layout.key+']');
        if(old_widget.length>0){
            //parent_ele = old_widget.parent();
            //var prev_sibling = old_widget.prev();
            $d.insertBefore(old_widget);
            old_widget.remove();
            //old_widget.replaceWith($d);
        }else{
            $d.appendTo(container);    
        }
        
        
        if(!layout.css){
            layout.css  = {};    
            layout.css['minHeight'] = '100px';
            //layout.css['position'] = 'relative';
        } 
        if(!layout.css['position']) layout.css['position'] = 'relative';
        
        //default values for various widgets
        /*
        if(layout.appid=='heurist_Map' ||  layout.appid=='heurist_SearchTree' || 
           layout.appid=='heurist_resultList' || layout.appid=='heurist_resultListExt'){
        }
        
        if(layout.appid=='heurist_Search'){
            if(layout.css['display']!='flex'){
                //layout.css['display'] = 'table';
            }
            if(!layout.css['width']){
                //layout.css['width'] = '100%';
            }
        }else if(layout.appid=='heurist_Map'){
            if(!layout.css['height']){
                //layout.css['height'] = '100%';
            }
        }*/

        
        //default min-height position depends on widget
        var app = _getWidgetById(layout.appid);
        if(app.minw>0 && !layout.css['minWidth']){
            layout.css['minWidth'] = app.minw;
        }
        if(app.minh>0 && !layout.css['minHeight']){
            layout.css['minHeight'] = app.minh;
        }

        if(isEditMode) {
            //$d.css('border','2px dashed red');
        }
        
        if(layout.css && !$.isEmptyObject(layout)){
            
            $d.removeAttr('style');
            $d.css( layout.css );    
        }
        
        _layoutInitWidget(layout, container.find('div[data-hid='+layout.key+']'));

    }
    
    //
    //
    //
    function _getWidgetById(id){

        var i;
        for(i=0; i<cfg_widgets.length; i++){
            if(cfg_widgets[i].id==id){
                return cfg_widgets[i];
            }
        }
        return null;
    }
    
    //
    //
    //
    function _layoutInitWidget(layout, container){

        
        //var layout = _layoutContentFindElement(_layout_cfg, container.attr('data-lid'));

        var app = _getWidgetById(layout.appid); //find in app array (appid is heurist_Search for example)

        if(!layout.options) layout.options = {};
        
        if(layout.appid=='heurist_Map'){
            layout.options['leaflet'] = true;
            layout.options['init_at_once'] = true;
        }
        
        if(_supp_options){
            if(_supp_options.rec_ID) _supp_options.rec_ID = null; //@todo remove assignment - we don't use it
            
            if(_supp_options[layout.appid]){
                layout.options = $.extend(layout.options, _supp_options[layout.appid]);        
            }
        }
        
        var weblang = window.hWin.HEURIST4.util.getUrlParameter('weblang');
        if(weblang) layout.options['language'] = weblang;
        
        if (app && app.script && app.widgetname) { //widgetname - function name to init widget

            if($.isFunction($('body')[app.widgetname])){ //OK! widget script js has been loaded            

                container[app.widgetname]( layout.options );   //call function

            }else{

                $.getScript( window.hWin.HAPI4.baseURL + app.script, function() {  //+'?t='+(new Date().getTime())
                    if($.isFunction(container[app.widgetname])){
                        container[app.widgetname]( layout.options );   //call function
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr('Widget '+app.widgetname+' not loaded. Verify your configuration');
                    }
                });

            }

        }
    }

    //
    // groups of containers    
    //
    function _layoutInitCardinal(layout, container){
        
        var $d, $parent;
        
        layout.dom_id = 'cms-tabs-'+layout.key;
        
        if(container.attr('id')==layout.dom_id){
            $d = container;    
        }else{
            $d = container.find('#'+layout.dom_id);
        }
        
        if($d.length>0){
            container = $d.parent();            
            $d.remove(); //remove itself
        }
        
        //create parent div
        var $parent = _layoutCreateDiv(layout);
        
        if( layout.css && !$.isEmptyObject(layout.css) ){
            $parent.css( layout.css );
        }
        
        $parent.appendTo(container);
        
        
        var layout_opts = {applyDefaultStyles: true, maskContents: true};
    
        for(var i=0; i<layout.children.length; i++){
            
            _layoutInitKey(layout.children, i);
            
            lpane = layout.children[i];
            var pos = lpane.type;
            
            var opts = lpane.options;
            if(!opts) opts = {};
            
            if(!$.isEmptyObject(opts)){
            
                if(opts.init){
                    layout_opts[pos+'__initHidden'] = (opts.init=='hidden');
                    layout_opts[pos+'__initClosed'] = (opts.init=='closed');
                }
                
                if(opts.size){
                    layout_opts[pos+'__size'] = opts.size;
                }
                if(Hul.isnull(opts.resizable) || opts.resizable ){
                    if(opts.minSize){
                        layout_opts[pos+'__minSize'] = opts.minSize;
                    }
                    if(opts.maxSize){
                        layout_opts[pos+'__maxSize'] = opts.maxSize;
                    }
                    layout_opts[pos+'__resizable'] = true;
                }else{
                    layout_opts[pos+'__spacing_open'] = 0;
                    layout_opts[pos+'__resizable'] = false;
                }
            }
            
            //create cardinal div
            $d = $(document.createElement('div'));
            $d.addClass('ui-layout-'+pos)
              .appendTo($parent);


            lpane.dom_id = 'cms-tabs-'+lpane.key;
            var $d2 =_layoutCreateDiv(lpane, 'ui-layout-content2');  
              
            $d2.appendTo($d);
              
              
            //@todo additional container for children>1        
            layout_opts[pos+'__contentSelector'] = '#'+lpane.dom_id;
                    
            //init                    
            _layoutInit(layout.children[i].children, $d2);
                    
        }//for
    
    
        $parent.layout( layout_opts );
        
        //$parent.find('.ui-layout-content2').css('padding','0px !important');
    }
    
    //
    //
    //
    function _layoutInitTabs(layout, container){
        
        
        var $d;
        
        layout.dom_id = 'cms-tabs-'+layout.key;
        
        if(container.attr('id')==layout.dom_id){
            $d = container;    
        }else{
            $d = container.find('#'+layout.dom_id);
        }
        
        if($d.length>0){
            container = $d.parent();            
            $d.remove();
        }
        
        //create parent div
        $d = _layoutCreateDiv(layout);
        
        $d.appendTo(container);
          
        //if(isEditMode) $d.css('border','2px dotted blue');
          
        if($d.parent().hasClass('layout-content')){
            $d.addClass('ent_wrapper');    
        }

        //tab panels    
        _layoutInit(layout.children, $d);
                
        //tab header
        $d = body.find('#'+layout.dom_id);
        var groupTabHeader = $('<ul>').prependTo($d);
        
        for(var i=0; i<layout.children.length; i++){
      
            //.addClass('edit-form-tab')
            $('<li>').html('<a href="#'+layout.children[i].dom_id
                                +'"><span style="font-weight:bold">'
                                +layout.children[i].name+'</span></a>')
                        .appendTo(groupTabHeader);
        }
        
        $d.tabs();
    }
    
    //
    //
    //
    function _layoutInitAccordion(layout, container){
       
        var $d;
        
        layout.dom_id = 'cms-tabs-'+layout.key;
        
        if(container.attr('id')==layout.dom_id){
            $d = container;    
        }else{
            $d = container.find('#'+layout.dom_id);
        }
        
        if($d.length>0){
            container = $d.parent();            
            $d.remove();
        }
            
        //create parent div
        $d = _layoutCreateDiv(layout);
        
        $d.appendTo(container);
       
        //accordion panels    
        _layoutInit(layout.children, $d);
       
        //accordion headers
        for(var i=0; i<layout.children.length; i++){
      
            $d = body.find('#'+layout.children[i].dom_id);
            
            $('<h3>').html( layout.children[i].name )
                     .insertBefore($d);
            
        }
        
        $d = body.find('#'+layout.dom_id);
        $d.accordion({heightStyle: "content", 
                      active:false,
                //active:(currGroupType == 'expanded')?0:false,
                      collapsible: true });
    }
    
    //
    // Find element in array by internal key property
    //
    function _layoutContentFindElement(content, ele_key){

        if(!$.isArray(content)){
            if(content.children && content.children.length>0){
                return _layoutContentFindElement(content.children, ele_key);    
            }else{
                return null;
            }
        }
        
        for(var i=0; i<content.length; i++){
            if(content[i].key == ele_key){
                return  content[i];
            }else if(content[i].children && content[i].children.length>0){
                var res = _layoutContentFindElement(content[i].children, ele_key);    
                if(res) return res;
            }
        }
        return null; //not found
    }
    
    //
    // Find widget bt application/widget name in cfg_widgets sush as "heurist_SearchInput"
    //
    function _layoutContentFindWidget(content, widget_name){
        
        if(!$.isArray(content)){
            if(content.children && content.children.length>0){
                return _layoutContentFindWidget(content.children, widget_name);    
            }else{
                return null;
            }
        }
        
        for(var i=0; i<content.length; i++){
            if(content[i].appid == widget_name){
                return  content[i];
            }else if(content[i].children && content[i].children.length>0){
                var res = _layoutContentFindWidget(content[i].children, widget_name);    
                if(res) return res;
            }
        }
        return null; //not found
    }

    //
    //
    //
    function _layoutContentFindAllWidget(content){

        var res = [];
        
        if(!$.isArray(content)){
            if(content.children && content.children.length>0){
                var res2 =  _layoutContentFindAllWidget(content.children);    
                if(res2) res = res.concat(res2);
            }else{
                return null;
            }
        }
        
        for(var i=0; i<content.length; i++){
            if(content[i].appid){
                res.push(content[i]);
            }else if(content[i].children && content[i].children.length>0){
                var res2 = _layoutContentFindAllWidget(content[i].children);    
                if(res2) res = res.concat(res2);
            }
        }
        return res;
    }
    
    //
    //
    //
    function _layoutContentFindMainRealm(content){
        //find all widgets on page
        var res = {};
        var widgets = _layoutContentFindAllWidget(content);
        for(var i=0; i<widgets.length; i++){
            if(!widgets[i].options.search_page && widgets[i].options.search_realm){
                if(res[widgets[i].options.search_realm]>0){
                    res[widgets[i].options.search_realm]++;
                }else{
                    res[widgets[i].options.search_realm]=1;
                }
            }
        }
        //find max usage
        var max_usage = 0; 
        var max_sg = ''
        widgets = Object.keys(res);
        for(var i=0; i<widgets.length; i++){
            if(res[widgets[i]]>max_usage){
                max_usage = res[widgets[i]];
                max_sg = widgets[i];
            }
        }
        return max_sg;
    }

    //
    // Find parent element for given key
    //
    function _layoutContentFindParent(parent, ele_key){
        
        var children;
        if($.isArray(parent)){
            children = parent;
            parent = 'root';
        }else{
            children = parent.children;    
        }
        
        for(var i=0; i<children.length; i++){
            if(children[i].key == ele_key){
                return  parent;
            }else if(children[i].children && children[i].children.length>0){
                var res = _layoutContentFindParent(children[i], ele_key);    
                if(res) return res;
            }
        }
        return false; //not found
    }
    
    //
    // Replace element
    //    
    function _layoutContentSaveElement(content, new_cfg){
            
        var ele_key = new_cfg.key;
        
        for(var i=0; i<content.length; i++){
            if(content[i].key == ele_key){
                content[i] = new_cfg;
                return true 
            }else if(content[i].children && content[i].children.length>0){
                if (_layoutContentSaveElement(content[i].children, new_cfg)){
                    return true;
                }
            }
        }

        return false;            
    }
    
    //
    //
    //
    // container.html(layout);
    function _convertOldCmsFormat(container, lvl){
        

      var res = [];
                
      $.each(container.children(), function(idx, ele){
          
         ele = $(ele);
         
         var child;
          
         if(ele.attr('data-heurist-app-id')){
             //this is widget
             var opts = window.hWin.HEURIST4.util.isJSON(ele.text());
             
             child = {appid: ele.attr('data-heurist-app-id'),
                                 options: opts};
                                 
             if(opts.__widget_name){
                 child.name = opts.__widget_name.replaceAll('=','').trim();
             }
             if(!child.name) child.name = "Widget "+lvl+'.'+idx;
         }else 
         if(ele.find('div[data-heurist-app-id]').length==0){ //no widgets
      
             var tag = ele[0].nodeName;
             var s = '<' + tag + '>'+ele.html()+'</' + tag + '>';
//console.log(s);
             
             child = {name:"Content "+lvl+'.'+idx, 
                                type:"text", 
                                content: s };
         }else{
             
             if(ele[0].nodeName=='TABLE'){
                 //window.hWin.HEURIST4.msg.showMsgDlg('We encounter troubles on conversion. Dynamic widget is within TABLE element');
                 //return false;
             }
             
             //there are widgets among children
             child = {name:"Group "+lvl+'.'+idx,
                                type:"group", 
                                folder:true, 
                                children:_convertOldCmsFormat(ele, lvl+1) };
         }
         
         if(child){
             if(ele.attr('style')){
                 
                 
                var styles = ele.attr('style').split(';'),
                    i= styles.length,
                    css = {},
                    style, k, v;


                while (i--)
                {
                    style = styles[i].split(':');
                    k = $.trim(style[0]);
                    v = $.trim(style[1]);
                    if (k.length > 0 && v.length > 0)
                    {
                        css[k] = v;
                    }
                }                 
                 
                 //var css = window.hWin.HEURIST4.util.isJSON(ele.attr('style'));
                 if(!$.isEmptyObject(css)) child['css'] = css;
//console.log( ele.attr('style') );                 
//console.log( css );                 
             }
             res.push(child);
         }
      });

      if(lvl == 0){
          res = [{name:"Name of this page",type:"group",folder:true, children:res }];
      }
      
      return res;
    }
    
    //
    //
    //
    function _prepareTemplate(layout, callback){ 
       
        if(layout.template=='default'){
        
           callback.call(this, new_element_json.children[0]); 
            
        }else if(layout.template=='blog'){
            
           var ele = _layoutContentFindWidget(layout, 'heurist_SearchTree');
           if (ele && ele.options.init_svsID=='????') {
                layout.template = null;

                try{
                
                var sURL2 = window.hWin.HAPI4.baseURL+'hclient/widgets/cms/templates/snippets/blog.js';
                // 3. Execute template script to replace template variables, adds filters and smarty templates
                    $.getScript(sURL2, function(data, textStatus, jqxhr){ //it will trigger oncomplete
                          //function in blog.js
                          _prepareTemplateBlog(layout, callback);
                          
                    }).fail(function( jqxhr, settings, exception ) {
                        console.log( 'Error in template script: '+exception );
                    });
                    
                    return true;    
                    
                }catch(e){
                    alert('Error in blog template script');
                }
           }
        }
    }
        
        
        
    
    
    //
    //public members
    layoutMgr = {

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            return (strClass === _className);
        },
        
        layoutInitTabs: function(layout, container){
            _layoutInitTabs(layout, container);    
        },
       
        layoutInitAccordion: function(layout, container){
            _layoutInitAccordion(layout, container);    
        },
        
        layoutInitCardinal: function(layout, container) {
            _layoutInitCardinal(layout, container);
        },
        
        //
        // supp_options - parameters that refelect current status - for example page record id
        //
        layoutInit: function(layout, container, supp_options){
            _supp_options = supp_options;
            return _layoutInit(layout, container, true);
        },
        
        convertOldCmsFormat: function(layout, container){
            container = $(container);
            container.empty();   
            container.html(layout);
            return _convertOldCmsFormat(container, 0)
        },
        
        layoutInitKey: function(layout, i){
            _layoutInitKey(layout, i);
        },
        
        layoutAddWidget: function( layout, container ){
            _layoutAddWidget(layout, container)            
        },
        
        layoutContentFindElement: function(_layout_cfg, ele_key){
            return _layoutContentFindElement(_layout_cfg, ele_key);    
        },
        
        layoutContentFindParent: function(parent, ele_key){
            return _layoutContentFindParent(parent, ele_key);
        },

        //
        //  Find widget bt application/widget name in cfg_widgets sush as "heurist_SearchInput"
        //
        layoutContentFindWidget: function(_layout_cfg, widget_name){
            return _layoutContentFindWidget(_layout_cfg, widget_name);    
        },
        
        //
        // Find most used search realm for current layout
        //
        layoutContentFindMainRealm: function(_layout_cfg){
            return _layoutContentFindMainRealm(_layout_cfg);
        },
        
        
        //replace element in layout
        layoutContentSaveElement: function(_layout_cfg, new_cfg){
            return _layoutContentSaveElement(_layout_cfg, new_cfg);    
        },
        
        setEditMode: function(newmode){
            isEditMode = newmode;            
        },
        
        prepareTemplate: function(layout, callback){
            _prepareTemplate(layout, callback);
        }
        
        
    }
}
   
