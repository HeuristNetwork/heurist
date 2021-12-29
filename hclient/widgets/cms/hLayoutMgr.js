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

    //
    //
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
        
        container.empty();   
        
        if(typeof layout === 'string' &&
            layout.indexOf('data-heurist-app-id')>0){ //old format with some widgets
            
            container.html(layout);
                
            window.hWin.HAPI4.LayoutMgr.appInitFromContainer( document, '#main-content', _supp_options );
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
console.log('!!!!');
            layout = [layout];    
        }

        if(isFirstLevel===true && _supp_options && _supp_options.page_name){
            layout[0].name  = _supp_options.page_name;
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

    function _layoutInitGroup(layout, container){
        
        //create parent div
        $d = $(document.createElement('div'));
        $d.attr('id','hl-'+layout.key).attr('data-lid', layout.key) 
                .appendTo(container);
                
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
        
        $d = $(document.createElement('div'));
        $d.attr('id','hl-'+layout.key).attr('data-lid', layout.key)
            .addClass('editable tinymce-body')
            .addClass('cms-element')
            .appendTo(container);
            
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

        $d = $(document.createElement('div'));

        //remove previous one
        var old_widget = container.find('#hl-'+layout.key);
        if(old_widget.length>0){
            //parent_ele = old_widget.parent();
            //var prev_sibling = old_widget.prev();
            old_widget.replaceWith($d);
        }else{
            $d.appendTo(container);    
        }
        
        //add new one
        $d.attr('id','hl-'+layout.key).attr('data-lid', layout.key)
        .addClass('heurist-widget editable')
        .addClass('cms-element');
        
        
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
        
        _layoutInitWidget(layout, container.find('#hl-'+layout.key));

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
            layout.options = $.extend(layout.options, _supp_options);    
        }
        
        
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
        
        var key_id = 'hl-'+layout.key;
        
        if(container.attr('id')==key_id){
            $d = container;    
        }else{
            $d = container.find('#'+key_id);
        }
        
        if($d.length>0){
            container = $d.parent();            
            $d.remove(); //remove itself
        }
        
        //create parent div
        $parent = $(document.createElement('div'));
        if( layout.css && !$.isEmptyObject(layout.css) ){
            $parent.css( layout.css );
        }
        
        $parent.attr('id', key_id)
          .attr('data-lid', layout.key)
          //.css({height:'100%',width:'100%'})
          .appendTo(container);
        
        //if(isEditMode) $parent.css('border','2px dashed green');
        
        
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

              
            $d2 = $(document.createElement('div'));
            $d2.attr('id','hl-'+lpane.key)
              .attr('data-lid', lpane.key)
              .addClass('ui-layout-content2')
              .appendTo($d);
              
              
            //@todo additional container for children>1        
            layout_opts[pos+'__contentSelector'] = '#hl-'+lpane.key;
                    
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
        
        var key_id = 'hl-'+layout.key;
        
        if(container.attr('id')==key_id){
            $d = container;    
        }else{
            $d = container.find('#'+key_id);
        }
        
        if($d.length>0){
            container = $d.parent();            
            $d.remove();
        }
        
        //create parent div
        $d = $(document.createElement('div'));
        $d.attr('id', key_id)
          .attr('data-lid', layout.key)
          .appendTo(container);
          
        //if(isEditMode) $d.css('border','2px dotted blue');
          
        if($d.parent().hasClass('layout-content')){
            $d.addClass('ent_wrapper');    
        }

        //tab panels    
        _layoutInit(layout.children, $d);
                
        //tab header
        $d = body.find('#'+key_id);
        var groupTabHeader = $('<ul>').prependTo($d);
        
        for(var i=0; i<layout.children.length; i++){
      
            //.addClass('edit-form-tab')
            $('<li>').html('<a href="#hl-'+layout.children[i].key
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
       
        var key_id = 'hl-'+layout.key;
        
        if(container.attr('id')==key_id){
            $d = container;    
        }else{
            $d = container.find('#'+key_id);
        }
        
        if($d.length>0){
            container = $d.parent();            
            $d.remove();
        }
            
        //create parent div
        $d = $(document.createElement('div'));
        $d.attr('id', key_id)
              .attr('data-lid', layout.key)
              .appendTo(container);
       
        //if(isEditMode) $d.css('border','2px dotted blue');
       
        //accordion panels    
        _layoutInit(layout.children, $d);
       
        //accordion headers
        for(var i=0; i<layout.children.length; i++){
      
            $d = body.find('#hl-'+layout.children[i].key);
            
            $('<h3>').html( layout.children[i].name )
                     .insertBefore($d);
            
        }
        
        $d = body.find('#'+key_id);
        $d.accordion({heightStyle: "content", 
                //active:(currGroupType == 'expanded')?0:false,
                      collapsible: true });
    }
    
    //
    // Find element in array
    //
    function _layoutContentFindElement(content, ele_id){

        if(!$.isArray(content)){
            if(content.children && content.children.length>0){
                return _layoutContentFindElement(content.children, ele_id);    
            }else{
                return null;
            }
        }
        
        for(var i=0; i<content.length; i++){
            if(content[i].key == ele_id){
                return  content[i];
            }else if(content[i].children && content[i].children.length>0){
                var res = _layoutContentFindElement(content[i].children, ele_id);    
                if(res) return res;
            }
        }
        return null; //not found
    }
    
    //
    // widget_id - id in cfg_widgets sush as "heurist_SearchInput"
    //
    function _layoutContentFindWidget(content, widget_id){
        
        if(!$.isArray(content)){
            if(content.children && content.children.length>0){
                return _layoutContentFindWidget(content.children, widget_id);    
            }else{
                return null;
            }
        }
        
        for(var i=0; i<content.length; i++){
            if(content[i].appid == widget_id){
                return  content[i];
            }else if(content[i].children && content[i].children.length>0){
                var res = _layoutContentFindWidget(content[i].children, widget_id);    
                if(res) return res;
            }
        }
        return null; //not found
    }

    //
    // Find parent element
    //
    function _layoutContentFindParent(parent, ele_id){
        
        var children;
        if($.isArray(parent)){
            children = parent;
            parent = 'root';
        }else{
            children = parent.children;    
        }
        
        for(var i=0; i<children.length; i++){
            if(children[i].key == ele_id){
                return  parent;
            }else if(children[i].children && children[i].children.length>0){
                var res = _layoutContentFindParent(children[i], ele_id);    
                if(res) return res;
            }
        }
        return false; //not found
    }
    
    //
    // Replace element
    //    
    function _layoutContentSaveElement(content, new_cfg){
            
        var ele_id = new_cfg.key;
        
        for(var i=0; i<content.length; i++){
            if(content[i].key == ele_id){
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
       
        if(layout.template=='blog'){
            
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
        
        layoutContentFindElement: function(_layout_cfg, key){
            return _layoutContentFindElement(_layout_cfg, key);    
        },
        
        layoutContentFindParent: function(parent, ele_id){
            return _layoutContentFindParent(parent, ele_id);
        },

        layoutContentFindWidget: function(_layout_cfg, widget_id){
            return _layoutContentFindWidget(_layout_cfg, widget_id);    
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
   
