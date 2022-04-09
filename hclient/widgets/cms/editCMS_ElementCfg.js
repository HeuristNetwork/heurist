/**
* editCMS_ElementCfg.js - configuration dialog for css and cardinal properties,
* for widgets it uses editCMS_WidgetCfg.js 
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
//
//
//
function editCMS_ElementCfg( element_cfg, _layout_content, _layout_container, $container, main_callback, already_changed ){

    var _className = 'editCMS_ElementCfg';
    var element;
    var l_cfg; //copy of json config  
    var codeEditor = null,
        codeEditorDlg = null,
        codeEditorBtns = null;
    var textAreaCss;
    var margin_mode_full = true;
    
    function _init(){

        /* not used as dialog
        var buttons= [
            {text:window.hWin.HR('Cancel'), 
                id:'btnCancel',
                css:{'float':'right','margin-left':'30px','margin-right':'20px'}, 
                click: function() { 
                    $dlg.dialog( "close" );
            }},
            {text:window.hWin.HR('Apply'), 
                id:'btnDoAction',
                class:'ui-button-action',
                //disabled:'disabled',
                css:{'float':'right'}, 
                click: function() { 
                        var config = _getValues();
                        main_callback.call(this, config);
                        $dlg.dialog( "close" );    
        }}];
        */
        
        
        element = _layout_container.find('div[data-hid='+element_cfg.key+']'); //element in main-content
        l_cfg = window.hWin.HEURIST4.util.cloneJSON(element_cfg);

        $container.empty().load(window.hWin.HAPI4.baseURL
                +'hclient/widgets/cms/editCMS_ElementCfg.html',
                _initControls
            );
    }
    
    //
    // Assign widget properties to UI
    //
    function _initControls(){

        var cont = $container;

        cont.find('input[data-type="element-name"]').val(l_cfg.name);
        cont.find('input[data-type="element-id"]').val(l_cfg.dom_id); //duplication for options.widget_id
        cont.find('textarea[name="elementClasses"]').val(l_cfg.classes);

        var etype = (l_cfg.type?l_cfg.type:(l_cfg.appid?'widget':'text'));

        cont.find('h4').css({margin:0});
        cont.find('.props').hide(); //hide all
        $('.btn-widget').parent().hide();
        cont.find('.props.'+etype).show(); //show required only

        var activePage = (etype=='group'?0:(etype=='widget'?2:(etype=='cardinal'?1:2)));

        cont.find('#properties_form').accordion({header:'h3',heightStyle:'content',active:activePage});
        cont.find('h3').css({'font-size': '1.1em', 'font-weight': 'bold'});

        if(!l_cfg.css) l_cfg.css = {display:'block'};

        _assignCssToUI();

        //load and init widget properties
        if(etype=='widget'){

            //var l_cfg = layoutMgr.layoutContentFindElement(_layout_content, ele_id);  //json

            function __openWidgetCfg(){
                
                var dom_id = window.hWin.HEURIST4.util.stripTags(cont.find('input[data-type="element-id"]').val());
                if(dom_id!=l_cfg.options.widget_id){
                    l_cfg.options.widget_id = dom_id;
                }
                
                editCMS_WidgetCfg(l_cfg, _layout_content, null, function(new_cfg){
                    //add new options 
                    if(JSON.stringify(l_cfg.options) != JSON.stringify(new_cfg)){
                        _enableSave();    
                    }
                    
                    l_cfg.options = new_cfg;
                    

                    if(new_cfg.widget_id){
                        l_cfg.dom_id = new_cfg.widget_id;
                        cont.find('input[data-type="element-id"]').val(l_cfg.dom_id);
                    }

                    //recreate widget with new options
                    layoutMgr.layoutAddWidget(l_cfg, element.parent());
                    
                    
                } );
            }

            
            $('.btn-widget').button().css({'border-radius':'4px',display:'inline-block'}).click(__openWidgetCfg)
            $('.btn-widget').parent().show();
            
            //var container = cont.find('div.widget');
            //$('<button>').button({label:top.HR('Configure')}).click(__openWidgetCfg).appendTo(container);
            /*
            if(l_cfg.app!='heurist_Graph'){
                    __openWidgetCfg();
            }*/

        }else
        if(etype=='group' && l_cfg.children && l_cfg.children.length>0){

            //4a.add list of children with flex-grow and flex-basis
                var item_ele = cont.find('div[data-flexitem]');
                var item_last = item_ele;
                for(var i=0; i<l_cfg.children.length; i++){

                    var child = l_cfg.children[i];

                    var item = item_ele.clone().insertAfter(item_last);
                    item.attr('data-flexitem',i).show();
                    var lbl = item.find('.header_narrow');
                    lbl.text((i+1)+'. '+lbl.text());

                    var val = (child.css)?child.css['flex']:null;
                    if(val){
                        val = val.split(' '); //grow shrink basis
                    }else{
                        val = [0,1,'auto'];
                    }
                    if(val[0]) item.find('input[data-type="flex-grow"]').val(val[0]);
                    if(val.length==3 && val[2]) item.find('input[data-type="flex-basis"]').val(val[2]);

                    item.find('input').change(function(e){
                        var item = $(e.target).parent();//('div[data-flexitem]');
                        var k = item.attr('data-flexitem');

                        if(!l_cfg.children[k].css) l_cfg.children[k].css = {};

                        l_cfg.children[k].css['flex'] = item.find('input[data-type="flex-grow"]').val()
                        +' 1 '+ item.find('input[data-type="flex-basis"]').val();

                        /*l_cfg.children[k].css['border'] = '1px dotted gray';
                        l_cfg.children[k].css['border-radius'] = '4px';
                        l_cfg.children[k].css['margin'] = '4px';*/

                        var child_ele = _layout_container.find('div[data-hid='+l_cfg.children[k].key+']');
                        child_ele.removeAttr('style');
                        child_ele.css(l_cfg.children[k].css);
                    });

                    item_last = item;
                }//for
        }else 
        if(etype=='cardinal'){ //assign cardinal properties
            
            for(var i=0; i<l_cfg.children.length; i++){
                var lpane = l_cfg.children[i];
                var pane = lpane.type;

                if(lpane.options){
                   var keys = Object.keys(lpane.options); 
                   for(var k=0; k<keys.length; k++){
                       var key = keys[k];
                       var ele = cont.find('[data-type="cardinal"][data-pane="'+pane+'"][name="'+key+'"]');
                       if(ele.length>0){
                            var val = lpane.options[key];   
                            if(ele.attr('type')=='checkbox'){
                                ele.attr('checked', (val=='true' || val===true));
                            }else {
                                ele.val(val);    
                            }
                       }
                   }//for
                }
            }//for
        }

        if(etype=='cardinal' || etype=='tabs' || etype=='accordion'){
            cont.find('input[data-type="element-id"]').parent().hide();
        }
        
        
        //4. listeners for selects    
        cont.find('select').hSelect({change:function(event){
            
            var ele = $(event.target);
            
            if((ele).attr('data-type')=='cardinal') return;
            
            var name = ele.attr('id');
            
            if(name=='display'){
                if(ele.val()=='flex'){
                    cont.find('.flex-select').each(function(i,item){ $(item).parent().show(); })
                }else{
                    cont.find('.flex-select').each(function(i,item){ $(item).parent().hide(); })
                }
            }
            _getCss();
            
            //var css = 
            //element.removeAttr('style');
            //element.css(css);
            
            _enableSave();
        }});


        //4b. listeners for styles (border,bg,margin)
        cont.find('input[data-type="css"]').change(_getCss);
        cont.find('input[data-type="css"]').change(_getCss);
        cont.find('input[name="background"]').change(_getCss);
        /*
            var css = _getCss();
            //element.removeAttr('style');
            //element.css(css);
        });*/
        
        //4c. button listeners
        cont.find('.margin-mode').button()
            .css({'font-size':'0.7em'})
            .click(function(e){
            //show hide short and full margin/padding
            margin_mode_full = !margin_mode_full;
            _onMarginMode();
        });
        
        cont.find('.cb_sync').parent().css({'font-size':'0.8em'});
        cont.find('.cb_sync').change(_onMarginSync);
        cont.find('input[name="padding-left"]').change(_onMarginSyncVal);
        cont.find('input[name="margin-left"]').change(_onMarginSyncVal);

        //save entire page (in background)
        cont.find('.btn-save-page').button().css('border-radius','4px').click(function(){
            _getCfgFromUI();
            main_callback.call(this, l_cfg, 'save_close'); //save and close
        });

        
        cont.find('.btn-save-element').button().css('border-radius','4px').click(function(){
            //5. save in layout cfg        
            _getCfgFromUI();
            main_callback.call(this, l_cfg, 'save'); //save only
            window.hWin.HEURIST4.util.setDisabled(cont.find('.btn-save-element'), true);
        });
        cont.find('.btn-cancel').css('border-radius','4px').button().click(function(){
            //6. restore old settings 
            element.removeAttr('style');
            if(element_cfg.css) element.css(element_cfg.css);
            main_callback.call(this, null);
        });
        
        window.hWin.HEURIST4.util.setDisabled(cont.find('.btn-save-page'), already_changed!==true);
        window.hWin.HEURIST4.util.setDisabled(cont.find('.btn-save-element'), true);
        
        
        //direct editor        
        textAreaCss = $container.find('textarea[name="elementCss"]');
        
        _assignCssTextArea();
        
        textAreaCss.change(function(){

            var vals = textAreaCss.val();
            //vals = vals.replace(/;/g, ";\n");
            vals = vals.replace(/"/g, ' ');
            
            vals = vals.split(';')
            var new_css = {};
            for (var i=0; i<vals.length; i++){
                var vs = vals[i].split(':');
                if(vs && vs.length==2){
                     var key = vs[0].trim();
                     var val = vs[1].trim();
                     new_css[key] = val;
                }
            }
            
            element.removeAttr('style');
            element.css(new_css);
            l_cfg.css = new_css;

            //element.attr('style',textAreaCss.val());
            //l_cfg.css = element.css();

            _assignCssToUI();
           
        });
        
        
        var btnDirectEdit = cont.find('div.btn-html-edit');
        if(etype=='text'){
             btnDirectEdit.parent().show();               
             btnDirectEdit.button().click(_initCodeEditor);
        }else{
             btnDirectEdit.parent().hide();               
        }
        
        $container.find('textarea').on({keyup:_enableSave});
        $container.find('input').on({keyup:_enableSave});
        $container.find('input').on({change:_enableSave});
    }


    //
    //
    //
    function _getCfgFromUI(){            
        
            var cont = $container;
            var css = _getCss();
            l_cfg.css = css;
            l_cfg.name = window.hWin.HEURIST4.util.stripTags(cont.find('input[data-type="element-name"]').val());
            if(!l_cfg.name) l_cfg.name = 'Define name of element';
            l_cfg.title = '<span data-lid="'+l_cfg.key+'">'+l_cfg.name+'</span>';
            
            l_cfg.dom_id = window.hWin.HEURIST4.util.stripTags(cont.find('input[data-type="element-id"]').val());
            if(l_cfg.appid && l_cfg.options){
                l_cfg.options.widget_id = l_cfg.dom_id;
            }

            if(window.hWin.HEURIST4.util.isempty(cont.find('textarea[name="elementClasses"]').val())){
                if(l_cfg.classes) delete l_cfg['classes'];
            }else{
                l_cfg.classes = cont.find('textarea[name="elementClasses"]').val();    
            }
        
            //get cardinal parameters  
            if(l_cfg.type=='cardinal')
            for(var i=0; i<l_cfg.children.length; i++){
                    var lpane = l_cfg.children[i];
                    var pane = lpane.type;

                    l_cfg.children[i].options = {}; //reset
            
                    $.each(cont.find('[data-type="cardinal"][data-pane="'+pane+'"]'), function(k, item){
                         item = $(item);
                         var name = item.attr('name');
                         var val = item.val();
                         if(item.attr('type')=='checkbox'){
                             val = item.is(':checked'); 
                             l_cfg.children[i].options[name] = val;    
                         }else if(val!=''){
                             l_cfg.children[i].options[name] = val;    
                         }
                    });
            }//for
            
    }            
    
    //
    //
    //
    function _getCss()
    {
        var cont = $container;
        var css = {};
        if(cont.find('#display').val()=='flex'){
            css['display'] = 'flex';

            cont.find('.flex-select').each(function(i,item){
                if($(item).val()){
                    css[$(item).attr('id')] = $(item).val();       
                }
            });
        }else if(cont.find('#display').val()=='table'){
            css['display'] = 'table';
        }else{
            css['display'] = 'block';
        }

        //style - border
        var val = cont.find('#border-style').val();
        css['border-style'] = val;

        fieldset = cont.find('fieldset[data-section="border"] > div:not(:first)');
        if(val=='none'){
            fieldset.hide();

        }else{
            fieldset.css('display','table-row');

            cont.find('input[name^="border-"]').each(function(i,item){
                if($(item).val()){
                    css[$(item).attr('name')] = $(item).val()
                    +($(item).attr('type')=='number'?'px':'');       
                }
            });

            if(!css['border-width']) css['border-width'] = '1px';
            if(!css['border-color']) css['border-color'] = 'black';
        }

        //style - background
        val = cont.find('input[name="background"]').is(':checked');
        fieldset = cont.find('fieldset[data-section="background"] > div:not(:first)');
        if(!val){
            fieldset.hide();
            css['background'] = 'none';
        }else{

            fieldset.css('display','table-row');
            val = cont.find('input[name="background-color"]').val();
            if(val) css['background-color'] = val;

            val = cont.find('input[name="background-image"]').val();
            if(val){
                css['background-image'] = val;  
                css['bg-image'] = cont.find('input[name="bg-image"]').val();
                val = cont.find('select[name="background-position"]').val();
                css['background-position'] = val;  
                val = cont.find('select[name="background-repeat"]').val();
                css['background-repeat'] = val;  
            } 
        }


        function __setDim(name){
            var ele = cont.find('input[name="'+name+'"]');
            var val = ele.val();
            if( (val!='' || val!='auto') && parseInt(val)>0){
                if(!(val.indexOf('%')>0 || val.indexOf('px')>0)){
                    val = val + 'px';
                }
                css[name] = val;
            }
        }

        __setDim('width');
        __setDim('height');

        if(margin_mode_full){
            __setDim('margin-left');
            __setDim('margin-top');
            __setDim('margin-bottom');
            __setDim('margin-right');
            __setDim('padding-left');
            __setDim('padding-top');
            __setDim('padding-bottom');
            __setDim('padding-right');
        }else{
            __setDim('margin');
            __setDim('padding');
        }

        if(l_cfg.css){
            var old_css = l_cfg.css;
            var params = ['display','width','height',
                'padding','padding-left','padding-top','padding-bottom','padding-right',
                'margin','margin-left','margin-top','margin-bottom','margin-right',
                'background','background-image','bg-image',
                'flex-direction','flex-wrap','justify-content','align-items','align-content'];
            for(var i=0; i<params.length; i++){
                var prm = params[i];
                if (old_css[prm]){ //drop old value
                    old_css[prm] = null;
                    delete old_css[prm];
                };
            }
            css = $.extend(old_css, css);
        }

        l_cfg.css = css;
        _assignCssTextArea();
        //console.log(css);  
        element.removeAttr('style');
        element.css(css);

        return css;
    }

    //
    //
    //
    function _onMarginSync(event){
        
        var type = $(event.target).attr('data-type');
        
        if($(event.target).is(':checked')){
            
            //disable
            //window.hWin.HEURIST4.util.setDisabled($container.find('input[name^="'+type+'-"]'), true);
            //window.hWin.HEURIST4.util.setDisabled($container.find('input[name^="'+type+'-left"]'), false);

            $container.find('input[name^="'+type+'-"]').prop('readonly',true);
            $container.find('input[name^="'+type+'-left"]').removeProp('readonly');
            
            _onMarginSyncVal(null, type)
            
        }else{
            $container.find('input[name^="'+type+'-"]').removeProp('readonly');
            //window.hWin.HEURIST4.util.setDisabled($container.find('input[name^="'+type+'-"]'), false);
        }       
    }
    
    //
    //
    //
    function _onMarginSyncVal(event, type){
        
        if(!type){
            type = $(event.target).attr('name');
            type = type.substr(0,type.indexOf('-'));
        }
        
        if($container.find('.cb_sync[data-type="'+type+'"]').is(':checked')){
        
                var val = $container.find('input[name="'+type+'-left"]').val();
                $container.find('input[name="'+type+'-top"]').val(val);
                $container.find('input[name="'+type+'-bottom"]').val(val);
                $container.find('input[name="'+type+'-right"]').val(val);
                _getCss();
        }
    }

    //
    //
    //
    function _onMarginMode(){
        var cont = $container;
        var btn = cont.find('.margin-mode').hide();
        if(margin_mode_full){
            btn.text('short');
            cont.find('.margin-short').hide();
            cont.find('.margin-full').css({display: 'inline-block'});
        }else{
            btn.text('full');
            cont.find('.margin-short').show();
            cont.find('.margin-full').hide();
        }
    }

    //
    //
    //
    function _enableSave(){
        window.hWin.HEURIST4.util.setDisabled($container.find('.btn-save-element'), false);
        window.hWin.HEURIST4.util.setDisabled($container.find('.btn-save-page'), false);
    }
    
    //
    //
    //
    function _assignCssTextArea(){
        
        var s = '';
        if(l_cfg.css){
            var keys = Object.keys(l_cfg.css);

            s = [];
            for(var i=0; i<keys.length; i++){
                s.push( keys[i]+': '+l_cfg.css[keys[i]] );
            }            
            s = s.join(";\n");
        }
        
        $container.find('textarea[name="elementCss"]').val(s);    
    }
    
    //
    //
    //
    function _assignCssToUI(){        
            
            var cont = $container;
            
            //assign flex css parameters
            var params = ['display','flex-direction','flex-wrap','justify-content','align-items','align-content'];
            for(var i=0; i<params.length; i++){
                var prm = params[i];
                if (l_cfg.css[prm]) cont.find('#'+prm).val(l_cfg.css[prm]);
            }

            var no_margin_values = true, mode_full = false;    
            //assign other css parameters
            cont.find('[data-type="css"]').each(function(i,item){
                var key = $(item).attr('name');
                var val = l_cfg.css[key];
                if(key=='background'){
                    $(item).prop('checked', val='none');
                }else if(val){
                    $(item).val($(item).attr('type')=='number'?parseInt(val):val);
                }
                
                if(!mode_full && !window.hWin.HEURIST4.util.isempty(val)){
                    if(key.indexOf('padding')===0 || 
                       key.indexOf('margin')===0){
                           
                       no_margin_values = false;
                           
                       mode_full = (key.indexOf('-') > 0);    
                    }
                }
            });
            margin_mode_full = true; //no_margin_values || mode_full;
//console.log(no_margin_values +'  '+ mode_full);
            //init file picker
            cont.find('input[name="bg-image"]')
                    .click(_selecHeuristMedia);
            cont.find('#btn-background-image').button()
                    .css({'font-size':'0.7em'})
                    .click(_selecHeuristMedia);

            cont.find('#btn-background-image-clear')
                    .button() //{icon:'ui-icon-close',showLabel:false})
                    .css({'font-size':'0.7em'})
                    .click(_clearBgImage);
            
            //init color pickers
            cont.find('input[name$="-color"]').colorpicker({
                hideButton: false, //show button right to input
                showOn: "both"});//,val:value
            cont.find('input[name$="-color"]').parent('.evo-cp-wrap').css({display:'inline-block',width:'100px'});

            //initially hide-show        
            if(cont.find('#display').val()=='flex'){
                cont.find('.flex-select').each(function(i,item){ $(item).parent().show(); })
            }else{
                cont.find('.flex-select').each(function(i,item){ $(item).parent().hide(); })
            }
            
            var fieldset = cont.find('fieldset[data-section="border"] > div:not(:first)');
            if(cont.find('#border-style').val()=='none'){
                fieldset.hide();
            }else{
                fieldset.css('display','table-row');
            }
            fieldset = cont.find('fieldset[data-section="background"] > div:not(:first)');
            if(cont.find('input[name="background"]').is(':checked')){
                fieldset.css('display','table-row');
            }else{
                fieldset.hide();
            }
            
            _onMarginMode();
    }

    
   
    //
    // from UI to element properties/css
    //
    function _getValues(){
        

        //return opts;
    }//_getValues


    //
    // init codemirror editor
    //
    function _initCodeEditor() {
        
        var $dlg;
        
        if(codeEditor==null){
            
            
                //document.getElementById('codemirror-container')
                codeEditor = CodeMirror($container.find('#codemirror-body')[0], {
                    mode           : "htmlmixed",
                    tabSize        : 2,
                    indentUnit     : 2,
                    indentWithTabs : false,
                    lineNumbers    : false,
                    matchBrackets  : true,
                    smartIndent    : true,
                    /*extraKeys: {
                        "Enter": function(e){
                            insertAtCursor(null, "");
                        }
                    },*/
                    onFocus:function(){},
                    onBlur:function(){}
                });
                
                
                codeEditorBtns = [
                    {text:window.hWin.HR('Cancel'), 
                        id:'btnCancel',
                        css:{'float':'right','margin-left':'30px','margin-right':'20px'}, 
                        click: function() { 
                            codeEditorDlg.dialog( "close" );
                    }},
                    {text:window.hWin.HR('Apply'), 
                        id:'btnDoAction',
                        class:'ui-button-action',
                        //disabled:'disabled',
                        css:{'float':'right'}, 
                        click: function() { 
                            var newval = codeEditor.getValue();

                            if(l_cfg.content != newval){
                                _enableSave();                
                            }
                            
                            element.html(newval);    
                            l_cfg.content = newval;
                            element_cfg.content = newval;
                            
                            codeEditorDlg.dialog( "close" );    
                }}]; 
                
        }
        
        codeEditorDlg = window.hWin.HEURIST4.msg.showElementAsDialog({
            window:  window.hWin, //opener is top most heurist window
            title: window.hWin.HR('Edit HTML source for element '+l_cfg.name),
            width: 800,
            height: 600,
            element: $container.find('#codemirror-container')[0] ,
            resizable: true,
            buttons: codeEditorBtns,
            //h6style_class: 'ui-heurist-publish',
            default_palette_class: 'ui-heurist-publish'
            //close: function(){}
        });                 
        
        
        
        //preformat - break lines for widget options
        /*
        var ele = $('<div>').html(l_cfg.content);
        $.each(ele.find('div[data-heurist-app-id]'),function(i,el){
            var s = $(el).text();
            s = "\n"+s.replace(/,/g, ", \n");
            $(el).text(s);
        });
        var content = ele.html();
        */
        
        if(window.hWin.HEURIST4.util.isempty(element_cfg.content)) element_cfg.content = ' ';
        
        codeEditor.setValue(element_cfg.content);

        //autoformat
        setTimeout(function(){
                    //codeEditorDlg.find('div.CodeMirror').css('height','100%').show();
                    
                    var totalLines = codeEditor.lineCount();  
                    codeEditor.autoFormatRange({line:0, ch:0}, {line:totalLines});                    
                    codeEditor.scrollTo(0,0);
                    codeEditor.setCursor(0,0); //clear selection
                    
                    codeEditor.focus()
                    //setTimeout(function(){;},200);
                },500);
    }
    
    function _clearBgImage(){
        $container.find('input[name="background-image"]').val('');
        $container.find('input[name="bg-image"]').val('');
        
        _getCss();
        _enableSave();
    }
    
    //
    //
    //
    function _selecHeuristMedia(){

        var popup_options = {
            isdialog: true,
            select_mode: 'select_single',
            edit_addrecordfirst: false, //show editor atonce
            selectOnSave: true,
            select_return_mode:'recordset', //ids or recordset(for files)
            filter_group_selected:null,
            filter_types: 'image',
            //filter_groups: this.configMode.filter_group,
            onselect:function(event, data){

                if(data){

                    if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                        var recordset = data.selection;
                        var record = recordset.getFirstRecord();
                        
                        var sUrl = recordset.fld(record,'ulf_ExternalFileReference');
                        if(!sUrl){
                            //always add media as reference to production version of heurist code (not dev version)
                            var sUrl = window.hWin.HAPI4.baseURL_pro+'?db='+window.hWin.HAPI4.database
                            +"&file="+recordset.fld(record,'ulf_ObfuscatedFileID');
                            $container.find('input[name="bg-image"]').val(recordset.fld(record,'ulf_OrigFileName'));
                        }else{
                            $container.find('input[name="bg-image"]').val(sUrl);
                        }
                        
                        sUrl = 'url(\'' + sUrl + '\')';
                        $container.find('input[name="background-image"]').val(sUrl);
                        
                        _getCss();
                        _enableSave();

                    }

                }//data

            }
        };//popup_options        

        window.hWin.HEURIST4.ui.showEntityDialog('recUploadedFiles', popup_options);
    }

    //
    //
    //    
    function _warningOnExit( callback ){
        
        if(!$container.find('.btn-save-element').prop('disabled')){
            
            var $dlg;
            var _buttons = [
                {text:window.hWin.HR('Save'), 
                    click: function(){
                        $container.find('.btn-save-element').click();
                        $dlg.dialog('close');
                        if($.isFunction(callback)) callback.call(this);
                    }
                },
                {text:window.hWin.HR('Discard'), 
                    click: function(){
                        $container.find('.btn-cancel').click();
                        $dlg.dialog('close'); 
                        if($.isFunction(callback)) callback.call(this);
                    }
                },
                {text:window.hWin.HR('Cancel'), 
                    click: function(){$dlg.dialog('close');}
                }
            ];            
            
            var sMsg = '"'+ window.hWin.HEURIST4.util.stripTags(l_cfg.name) 
                    +'" '+window.hWin.HR('element has been modified');
            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(sMsg, _buttons, {title:window.hWin.HR('Element changed')});   

            return true;     
        }else{
            return false;     
        }
        
        
    }
    
        

    //public members
    var that = {

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            return (strClass === _className);
        },
        
        warningOnExit: function( callback ){
            return _warningOnExit( callback );
        },
        
        isModified: function(){
            return !$container.find('.btn-save-element').prop('disabled');
        },
        
        onContentChange: function(){
            _enableSave();
        },
        
        getKey: function(){
            return element_cfg.key;
        }
    }

    _init();
    
    return that;
}



