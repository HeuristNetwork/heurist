
/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
* @todo change storage of settings to user session (instead of current usage of localStorage)
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

// Functions to handle the visualisation settings

/** SETTING NAMES */
var setting_linetype      = "setting_linetype";
var setting_line_empty_link = "setting_line_empty_link";
var setting_linelength    = "setting_linelength";
var setting_linewidth     = "setting_linewidth";
var setting_linecolor     = "setting_linecolor";
var setting_markercolor   = "setting_markercolor";
var setting_entityradius  = "setting_entityradius";
var setting_entitycolor   = "setting_entitycolor";
var setting_labels        = "setting_labels";
var setting_fontsize      = "setting_fontsize";
var setting_textlength    = "setting_textlength";
var setting_textcolor     = "setting_textcolor";
var setting_formula       = "setting_formula";
var setting_gravity       = "setting_gravity";
var setting_attraction    = "setting_attraction";
var setting_fisheye       = "setting_fisheye";
var setting_translatex    = "setting_translatex";
var setting_translatey    = "setting_translatey";
var setting_scale         = "setting_scale"; 
var setting_advanced      = "setting_advanced"; 

//localStorage.clear();
/**
* Returns the current displayed URL
* 
*/
function getURL() {
    return window.location.href; 
}

/**
 * Returns a setting from the localStorage
 * @param setting The setting to retrieve
 */
function getSetting(key, defvalue) {
    var value = localStorage.getItem(getURL()+key);
    
    if (   //(isNaN(value) && $.isNumeric(defvalue)) ||   //!isNaN(parseFloat(n)) && isFinite(n)
        (window.hWin.HEURIST4.util.isnull(value) && !window.hWin.HEURIST4.util.isnull(defvalue))){
        value = defvalue;
        putSetting(key, value);
    }
//console.log(key+'  '+value+'  '+defvalue);    
    return value;
}

/**
* Stores a value in the localStorage
*/
function putSetting(key, value) {
//    if(key==setting_linecolor){
//console.log('put '+key+'  '+value);        
//    }

    localStorage.setItem(getURL()+key, value);
}

/**
 * This function makes sure the default settings are stored in the localStorage.
 * @param settings The plugin settings object
 */
function checkStoredSettings() {
    getSetting(   setting_linetype,      settings.linetype    );
    getSetting(   setting_line_empty_link, settings.setting_line_empty_link );
    getSetting(   setting_linelength,    settings.linelength  );
    getSetting(   setting_linewidth,     settings.linewidth   );
    getSetting(   setting_linecolor,     settings.linecolor   );
    getSetting(   setting_markercolor,   settings.markercolor );
    getSetting(   setting_entityradius,  settings.entityradius);
    getSetting(   setting_entitycolor,   settings.entitycolor );
    getSetting(   setting_labels,        settings.labels      );
    getSetting(   setting_fontsize,      settings.fontsize    );
    getSetting(   setting_textlength,    settings.textlength  );
    getSetting(   setting_textcolor,     settings.textcolor   );
    getSetting(   setting_formula,       settings.formula     );
    getSetting(   setting_gravity,       settings.gravity     );
    getSetting(   setting_attraction,    settings.attraction  );
    getSetting(   setting_fisheye,       settings.fisheye     );
    getSetting(   setting_translatex,    settings.translatex  );
    getSetting(   setting_translatey,    settings.translatey  );
    getSetting(   setting_scale,         settings.scale       );
    getSetting(   setting_advanced,      settings.advanced    );
}

/**
* This function sets the settings in the UI
*/
function handleSettingsInUI() {
    
    //add elements on toolbar
    var tBar = $('#toolbar');
    
    
    var is_advanced = getSetting(setting_advanced);
    
    $('#setAdvancedMode').css({cursor:'hand'}).click(
        function(){
              var is_advanced = getSetting(setting_advanced);
              is_advanced = (is_advanced==='false');
                if(is_advanced){
                    $('.advanced').show();
                    $('#setAdvancedMode').find('a').hide();
                    if(settings.isDatabaseStructure){
                        $('#setDivExport').hide();
                    }
                }else{
                    $('.advanced').hide();
                    $('#setAdvancedMode').find('a').show();
                }
              putSetting(setting_advanced, is_advanced); 
        }
    );
    
    if(is_advanced!=='false'){
        $('.advanced').show();
        $('#setAdvancedMode').find('a').hide();
        if(settings.isDatabaseStructure){
            $('#setDivExport').hide();
        }
    }else{
        $('.advanced').hide();
        $('#setAdvancedMode').find('a').show();
    }
    
    //-------------------------------
    
    $('#btnSingleSelect').button({icon:'ui-icon-cursor' , showLabel:false})
        .click( function(){ selectionMode = 'single'; $("#d3svg").css("cursor", "default"); _syncUI();});
    $('#btnMultipleSelect').button({icon: 'ui-icon-select', showLabel:false})
        .click( function(){ selectionMode = 'multi'; $("#d3svg").css("cursor", "crosshair"); _syncUI();});
    $('#selectMode').controlgroup();
        
    $('#btnViewModeIcon').button({icon: 'ui-icon-circle' , showLabel:false})
        .click( function(){changeViewMode('icons');} );
    $('#btnViewModeInfo').button({icon: 'ui-icon-circle-b-info' , showLabel:false})
        .click( function(){changeViewMode('infoboxes');} );
    $('#btnViewModeFull').button({icon: 'ui-icon-circle-info' , showLabel:false})
        .click( function(){changeViewMode('infoboxes_full');} );
    $( "#setViewMode" ).controlgroup();    

    $('#gravityMode0').button(/*{icon: 'ui-icon-gravity0' , showLabel:false}*/)
        .click( function(){setGravity('off');} );
    $('#gravityMode1').button(/*{icon: 'ui-icon-gravity1' , showLabel:false}*/)
        .click( function(){setGravity('touch');} );
    $('#gravityMode2').button(/*{icon: 'ui-icon-gravity2' , showLabel:false}*/)
        .click( function(){setGravity('aggressive');} );
    $("#setGravityMode").controlgroup();    
    
    //------------ NODES ----------
    
    var radius = getSetting(setting_entityradius);
    if(radius<circleSize) radius = circleSize  //min
    else if(radius>maxEntityRadius) radius = maxEntityRadius;
    $('#nodesRadius').val(radius).change(function(){
        putSetting(setting_entityradius, $(event.target).val());
        //visualizeData();
        d3.selectAll(".node > .background").attr("r", function(d) {
                        return getEntityRadius(d.count);
                    })
    });
    
    //$("input[name='nodesMode'][value='" +getSetting(setting_formula)+ "']").attr("checked", true);
    
    $('#nodesMode0').button().css('width','35px')
        .click( function(){ setFormulaMode('linear'); });
    $('#nodesMode1').button().css('width','40px')
        .click( function(){ setFormulaMode('logarithmic'); }); 
    $('#nodesMode2').button().css('width','50px')
        .click( function(){ setFormulaMode('unweighted'); });
    $( "#setNodesMode" ).controlgroup();    

    
    $("#entityColor")
        //.addClass('ui-icon ui-icon-bullet')
        //.css({'font-size':'3.5em','color':getSetting(setting_entitycolor)})
        .val(getSetting(setting_entitycolor))
        .colorpicker({
                        hideButton: false, //show button right to input
                        showOn: "button",
                        val:getSetting(setting_entitycolor)})
        .on('change.color', function(event, color){
            if(color){
                putSetting(setting_entitycolor, color);
                $(".background").attr("fill", color);
                visualizeData();
            }
        });
    //------------ LINKS ----------

    //$("input[name='linksMode'][value='" +getSetting(setting_linetype)+ "']").attr("checked", true);
    
    $('#linksMode0').button({icon: 'ui-icon-link-streight', showLabel:false})
        .click( function(){ setLinkMode('straight');} );
    $('#linksMode1').button({icon: 'ui-icon-link-curved', showLabel:false})
        .click( function(){ setLinkMode('curved');} );
    $('#linksMode2').button({icon: 'ui-icon-link-stepped', showLabel:false})
        .click( function(){ setLinkMode('stepped');} );
        
    $('#linksEmpty').change( function(e){
        putSetting(setting_line_empty_link, $(e.target).is(':checked')?1:0);
        visualizeData();
        _syncUI();
    });
	$('#expand-links').change( function(){ // expand single links
        tick(); 
	});
        
    $( "#setLinksMode" ).controlgroup();    
    
    _syncUI();

    var linksLength = getSetting(setting_linelength, 200);    
    $('#linksLength').val(linksLength).change(function(){
        var newval = $(event.target).val();
        putSetting(setting_linelength, newval);
        if(getSetting(setting_gravity) != "off"){
            visualizeData();    
        }
    });
    
    var linksWidth = getSetting(setting_linewidth);    
    if(linksWidth<1) linksWidth = 1  //min
    else if(linksWidth>maxLinkWidth) linksWidth = maxLinkWidth;
    
    $('#linksWidth').val(linksWidth).change(
    function(){
        var newval = $(event.target).val();
        putSetting(setting_linewidth, newval);
        
        refreshLinesWidth();
    
    });
    
    $("#linksPathColor")
        //.addClass('ui-icon ui-icon-loading-status-circle')
        .css({'font-size':'1.8em','font-weight':'bold','color':getSetting(setting_linecolor)})
        .click(function(e){
                window.hWin.HEURIST4.util.stopEvent(e);
                $("#linksPathColor_inpt").colorpicker("showPalette");
        });
        
    $("#linksPathColor_inpt")
        .val(getSetting(setting_linecolor))
        .colorpicker({
                        hideButton: true, //show button right to input
                        showOn: "both",
                        val:getSetting(setting_linecolor)})
        .on('change.color', function(event, color){
            if(color){
                putSetting(setting_linecolor, color);
                $(".bottom-lines.link").attr("stroke", color);
                $('#linksPathColor').css('color', color);
                visualizeData();
            }
        });
        
    $("#linksMarkerColor")
        .addClass('ui-icon ui-icon-triangle-1-e')
        .css({'color':getSetting(setting_markercolor)})
        .click(function(e){
                window.hWin.HEURIST4.util.stopEvent(e);
                $("#linksMarkerColor_inpt").colorpicker("showPalette");
        });
        
    $("#linksMarkerColor_inpt")
        .val(getSetting(setting_markercolor))
        .colorpicker({
                        hideButton: true, //show button right to input
                        showOn: "focus",
                        val:getSetting(setting_markercolor)})
        .on('change.color', function(event, color){
            if(color){
                putSetting(setting_markercolor, color);
                $("marker").attr("fill", color);
                $('#linksMarkerColor').css('color', color);
                visualizeData();
            }
        });
    
    
    //------------ LABELS ----------
    
    putSetting(setting_labels, 'on'); //always on
    var isLabelVisible = (getSetting(setting_labels, 'on')=='on');
    
    $('#textOnOff').attr('checked',isLabelVisible).change(function(){
        
        var newval = $(event.target).is(':checked')?'on':'off';
        putSetting(setting_labels, newval);

        if(currentMode=='icons'){
            var isLabelVisible = (newval=='on');
            //d3.selectAll(".nodelabel").style('display', isLabelVisible?'block':'none');
            if(isLabelVisible) {
                visualizeData();
            }else{
                d3.selectAll(".nodelabel").style('display', 'none');
            }
        }
        // visualizeData();
    });
    
    var textLength = getSetting(setting_textlength, 200);    
    $('#textLength').val(textLength).change(function(){
        var newval = $(event.target).val();
        putSetting(setting_textlength, newval);
        var isLabelVisible = (currentMode!='icons' || (getSetting(setting_labels, 'on')=='on'));
        if(isLabelVisible) visualizeData();    
    });
    
    
    var fontSize = getSetting(setting_fontsize, 12);    
    if(isNaN(fontSize) || fontSize<8) fontSize = 8  //min
    else if(fontSize>25) fontSize = 25;
   
    $('#fontSize').val(fontSize).change(
    function(){
        var newval = $(event.target).val();
        putSetting(setting_fontsize, newval);
        var isLabelVisible = (currentMode!='icons' || (getSetting(setting_labels, 'on')=='on'));
        if(isLabelVisible) visualizeData();    
    });    

    /* hidden in current version
    $("#textColor")
        //.addClass('ui-icon ui-icon-loading-status-circle')
        .css({'display':'inline-block','cursor':'pointer','font-size':'1em','font-weight':'bold',
            'color':getSetting(setting_textcolor)})
        .colpick({
            layout: 'hex',
            onSubmit: function(hsb, hex, rgb, el) {
                var color = "#"+hex; 
                
                putSetting(setting_textcolor, color);
                
                $(".namelabel").attr("fill", color);
                //visualizeData();
                
                $(el).css('color', color);
                $(el).colpickHide();
            }});
    */

    
    if(settings.isDatabaseStructure){
        initRecTypeSelector();    
        $('#setDivExport').hide();
    }else{
        $('#setDivExport').show();
        $('#gephi-export').button();
    }
    
    tBar.show();
 /*   OLD JJ CODE
    // LINE SETTINGS
    if(settings.showLineSettings) {
        handleLineType();
        handleLineLength();
        handleLineWidth();
        handleLineColor();
        handleMarkerColor();
    }else{
        $("#lineSettings").remove();
    }
    $("#lineSettings").hide();
    
    // ENTITY SETTINGS
    if(settings.showEntitySettings) {
        //handleEntityRadius();
        //handleEntityColor();
    }else{
        $("#entitySettings").remove();
    }
    
    // LABEL SETTINGS
    if(settings.showTextSettings) {
        handleLabels();
        handleTextSize();
        handleTextLength();
        handleTextColor();
    }else{
        $("#textSettings").remove();
    }
    
    // TRANSFORM SETTINGS
    if(settings.showTransformSettings) {
        handleFormula();
        handleFisheye();
    }else{
        $("#transformSettings").remove();
    }
    
    // GRAVITY SETTINGS
    if(settings.showGravitySettings) {
        handleGravity();
        handleAttraction();
    }else{
        $("#gravitySettings").remove();
    }
*/
}

function initRecTypeSelector(){
    
    $('#showRectypeSelector').button(
        {icons:{secondary:'ui-icon-carat-1-s'}}   //triangle-1-s
    ).css({'padding':'4px 2px'})
    .click(
        function(){
            var ele = $('#list_rectypes');
            if(ele.is(':visible')){
                ele.hide();     
                $(this).button({icons:{secondary:'ui-icon-carat-1-s'}});
            }else{
                ele.show();     
                $(this).button({icons:{secondary:'ui-icon-carat-1-n'}});
            }
        }
    );
    
    $('#rectypeSelector').css('display','table-cell');     
}

function _syncUI(){
    $('#toolbar').find('button').removeClass('ui-heurist-btn-header1');
    
    $('#toolbar').find('button[value="'+selectionMode+'"]').addClass('ui-heurist-btn-header1');
    $('#toolbar').find('button[value="'+currentMode+'"]').addClass('ui-heurist-btn-header1');

    var grv = getSetting(setting_gravity,'off');
    if(grv=='agressive') grv = 'touch';
    $('#toolbar').find('button[name="gravityMode"][value="'+grv+'"]').addClass('ui-heurist-btn-header1');
    
    var formula = getSetting(setting_formula,'linear')
    $('#toolbar').find('button[name="nodesMode"][value="'+formula+'"]').addClass('ui-heurist-btn-header1');
    
    var linetype = getSetting(setting_linetype,'straight');
    $('#toolbar').find('button[name="linksMode"][value="'+linetype+'"]').addClass('ui-heurist-btn-header1');
    
    
    var is_show_empty = (getSetting(setting_line_empty_link, 1)==1);
    $('#toolbar').find('#linksEmpty').prop('checked', is_show_empty);
}

function changeViewMode(mode){
    
    if(mode!=currentMode){
        if(mode=='infoboxes'){ // && currentMode=='icons'
            currentMode = 'infoboxes';
            //d3.selectAll(".icon-mode").style('display', 'none');
            d3.selectAll(".info-mode").style('display', 'initial');
            d3.selectAll(".info-mode-full").style('display', 'none');
            
            d3.selectAll(".rect-info-full").style('display', 'none');
            d3.selectAll(".rect-info").style('display', 'initial');
            
        }else if(mode=='infoboxes_full'){
            
            currentMode = 'infoboxes_full';
            d3.selectAll(".info-mode").style('display', 'initial');
            d3.selectAll(".info-mode-full").style('display', 'initial');
            
            d3.selectAll(".rect-info-full").style('display', 'initial');
            d3.selectAll(".rect-info").style('display', 'none');
            
        }else{
            
            currentMode = 'icons';
            //d3.selectAll(".icon-mode").style('display', 'initial');
            d3.selectAll(".info-mode").style('display', 'none');
            d3.selectAll(".info-mode-full").style('display', 'none');
        }
        var isLabelVisible = (currentMode != 'icons') || (getSetting(setting_labels)=='on');
        d3.selectAll(".nodelabel").style('display', isLabelVisible?'block':'none');
        
        
        _syncUI();
    }
}

//
//
//
function setGravity(gravity) {
    
    putSetting(setting_gravity,  gravity);
    
    // Update gravity impact on nodes
    svg.selectAll(".node").attr("fixed", function(d, i) {
        if(gravity == "aggressive") {
            d.fixed = false;
            return false;
        }else{
            d.fixed = true;
            return true;
        }
    });
    
    //visualizeData();

    if(gravity !== "off") {
        force.resume(); 
    }     
    
    _syncUI();
}
//
//
//
function setFormulaMode(formula) {
    putSetting(setting_formula, formula);
    //visualizeData();
    d3.selectAll(".node > .background").attr("r", function(d) {
                        return getEntityRadius(d.count);
                    })
    refreshLinesWidth();
    _syncUI();
}

//
//
//
function refreshLinesWidth(){
    
        /*d3.selectAll("path").style("stroke-width", function(d) { 
            return getLineWidth(d.targetcount);
         });
        d3.selectAll("polyline.link").style("stroke-width", function(d) { 
            return getLineWidth(d.targetcount);
         });*/

        d3.selectAll(".bottom-lines").style("stroke-width", //thickness);
             function(d) { return getLineWidth(d.targetcount); });
    
        d3.selectAll("marker").attr("markerWidth", function(d) {    
                        return getMarkerWidth(d.targetcount);             
                    })
                    .attr("markerHeight", function(d) {
                       return getMarkerWidth(d.targetcount);
                    });
    
}


//
// straight or curverd links type
//
function setLinkMode(formula) {
    //var formula = $(event.target).val();
    putSetting(setting_linetype, formula);
    visualizeData();
    _syncUI();
}
