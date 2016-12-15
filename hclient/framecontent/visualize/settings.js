
/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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

// Functions to handle the visualisation settings

/** SETTING NAMES */
var setting_linetype      = "setting_linetype";
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
function getSetting(setting) {
    return localStorage.getItem(getURL()+setting);
}

/**
* Stores a value in the localStorage
*/
function putSetting(key, value) {
    localStorage.setItem(getURL()+key, value);
}

/**
* Checks if a setting has been set, if not it sets the default value
* @param key    Localstorage key
* @param value  The default value
*/
function checkSetting(key, value) {
    var obj = getSetting(key);
    if(obj === null) {
        putSetting(key, value);
    }
}

/**
 * This function makes sure the default settings are stored in the localStorage.
 * @param settings The plugin settings object
 */
function checkStoredSettings() {
    checkSetting(   setting_linetype,      settings.linetype    );
    checkSetting(   setting_linelength,    settings.linelength  );
    checkSetting(   setting_linewidth,     settings.linewidth   );
    checkSetting(   setting_linecolor,     settings.linecolor   );
    checkSetting(   setting_markercolor,   settings.markercolor );
    checkSetting(   setting_entityradius,  settings.entityradius);
    checkSetting(   setting_entitycolor,   settings.entitycolor );
    checkSetting(   setting_labels,        settings.labels      );
    checkSetting(   setting_fontsize,      settings.fontsize    );
    checkSetting(   setting_textlength,    settings.textlength  );
    checkSetting(   setting_textcolor,     settings.textcolor   );
    checkSetting(   setting_formula,       settings.formula     );
    checkSetting(   setting_gravity,       settings.gravity     );
    checkSetting(   setting_attraction,    settings.attraction  );
    checkSetting(   setting_fisheye,       settings.fisheye     );
    checkSetting(   setting_translatex,    settings.translatex  );
    checkSetting(   setting_translatey,    settings.translatey  );
    checkSetting(   setting_scale,         settings.scale       );
}

/**
* This function sets the settings in the UI
*/
function handleSettingsInUI() {
    
    handleViewMode();
    
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
    
    // ENTITY SETTINGS
    if(settings.showEntitySettings) {
        handleEntityRadius();
        handleEntityColor();
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
}

/** LINE TYPE SETTING */
function handleLineType() {
    if(settings.showLineType) {
        // Set line type setting in UI
        $("#linetype option[value='" +getSetting(setting_linetype)+ "']").attr("selected", true);
        
        // Listens to linetype selection changes
        $("#linetype").change(function(e) {
            putSetting(setting_linetype, $("#linetype").val());
            visualizeData();
        });
    }else{
        $("#linetypeContainer").remove();
    }
}

/** LINE LENGTH SETTING */
function handleLineLength() {
    if(settings.showLineLength) {
        // Set line length setting in UI
        $("#linelength").val(getSetting(setting_linelength));
        
        // Listen to line length changes
        $("#linelength").change(function() {
            putSetting(setting_linelength, $(this).val());
            visualizeData();
        });
    }else{
        $("#linelengthContainer").remove();
    }
}
        
/** LINE WIDTH SETTING */
function handleLineWidth() {
    if(settings.showLineWidth) {
        // Set line width setting in UI
        $("#linewidth").val(getSetting(setting_linewidth));
        
        // Listen to line width changes
        $("#linewidth").change(function() {
            putSetting(setting_linewidth, $(this).val());
            visualizeData();
        });
    }else{
        $("#linewidthContainer").remove();
    }
}

/** LINE COLOR SETTING */
function handleLineColor() {
    if(settings.showLineColor) {
        // Set line color setting in UI
        $("#linecolor").css("background-color", getSetting(setting_linecolor));

        // Listen to 'line color' selection changes
        $('#linecolor').colpick({
            layout: 'hex',
            onSubmit: function(hsb, hex, rgb, el) {
                var color = "#"+hex; 
                
                putSetting(setting_linecolor, color);
                $(".bottom.link").attr("stroke", color);
        
                $(el).css('background-color', color);
                $(el).colpickHide();
            }
        });
    }else{
        $("#linecolorContainer").remove();
    }
}
        
/** MARKER COLOR SETTING */
function handleMarkerColor() {
    if(settings.showMarkerColor) {
        // Set marker color in UI
        $("#markercolor").css("background-color", getSetting(setting_markercolor));
        
        // Listen to 'marker color' selection changes
        $('#markercolor').colpick({
            layout: 'hex',
            onSubmit: function(hsb, hex, rgb, el) {
                var color = "#"+hex; 
                
                putSetting(setting_markercolor, color);
                $("marker").attr("fill", color);
                
                $(el).css('background-color', color);
                $(el).colpickHide();
            }
        });
    }else{
        $("#markercolorContainer").remove();
    }
}

/**  ENTITY RADIUS SETTING */
function handleEntityRadius() {
    if(settings.showEntityRadius) {
        // Set entity radius setting in UI
        $("#entityradius").val(getSetting(setting_entityradius));
        
        // Listen to line width changes
        $("#entityradius").change(function() {
            putSetting(setting_entityradius, $(this).val());
            visualizeData();
        });
    }else{
        $("#entityradiusContainer").remove();
    }
}

/** ENTITY COLOR SETTING */
function handleEntityColor() {
    if(settings.showEntityColor) {
        // Set count color in UI
        $("#entitycolor").css("background-color", getSetting(setting_entitycolor));

        // Listen to 'count color' selection changes
        $('#entitycolor').colpick({
            layout: 'hex',
            onSubmit: function(hsb, hex, rgb, el) {
                var color = "#"+hex; 
                
                putSetting(setting_entitycolor, color);
                $(".background").attr("fill", color);
                
                $(el).css('background-color', color);
                $(el).colpickHide();
            }
        });
    }else{
        $("#entitycolorSettings").remove();
    }
}

/** LABEL VISIBLITY SETTING */
function handleLabels() {
    if(settings.showLabels) {
        // Set checkbox value
        if(getSetting(setting_labels) == "false") {
            $("#labelCheckBox").prop("checked", false);
        }
        
        // Listen to changes
        $("#labelCheckBox").change(function(e) {
            putSetting(setting_labels, $(this).is(':checked'));
            visualizeData();
        });
    }else{
        $("#labelCheckBox").remove();
    }   
}

/** TEXT FONT SIZE SETTING */
function handleTextSize() {
    if(settings.showFontSize) {
        // Set font size setting in UI
        $("#fontsize").val(parseInt(getSetting(setting_fontsize)));
        
        // Listen to font size changes
        $("#fontsize").change(function() {
            putSetting(setting_fontsize, $(this).val()+"px");
            $(".node text").css("font-size", getSetting(setting_fontsize), "important");
            $(".node text").each(function() {
                this.style.setProperty("font-size", getSetting(setting_fontsize), "important"); 
            });
      
        });
    }else{
        $("#fontsizeContainer").remove();
    }
}

/** TEXT LENGTH SETTING */
function handleTextLength() {
    if(settings.showTextLength) {
        // Set text length in UI
        $("#textlength").val(getSetting(setting_textlength));
        
        // Listen to text length changes
        $("#textlength").change(function() {
            putSetting(setting_textlength, $(this).val());
            visualizeData();
        });
    }else{
        $("#textlengthContainer").remove();
    }
}

/** TEXT COLOR SETTING */
function handleTextColor() {
    if(settings.showTextColor) {
        // Set text color in UI
        $("#textcolor").css("background-color", getSetting(setting_textcolor));

        // Listen to 'count color' selection changes
        $('#textcolor').colpick({
            layout: 'hex',
            onSubmit: function(hsb, hex, rgb, el) {
                var color = "#"+hex; 
                
                putSetting(setting_textcolor, color);
                $(".namelabel").attr("fill", color);
                
                $(el).css('background-color', color);
                $(el).colpickHide();
            }
        });
    }else{
        $("#textcolorContainer").remove();
    }
}

/** FORMULA SETTING */
function handleFormula() {
    if(settings.showFormula) {
        // Set formula setting in UI
        $("#formula option[value='" +getSetting(setting_formula)+ "']").attr("selected", true); 

        // Listen to formula changes
        $("#formula").change(function() {
            putSetting(setting_formula, $(this).val());
            visualizeData();
        });
    }else{
        $("#formulaContainer").remove();
    }
}

/** FISH EYE */
function handleFisheye() {
    if(settings.showFishEye) {
        // Set fish eye setting in UI
        if(getSetting(setting_fisheye) === "true") {
            $("#fisheye").prop("checked", true);
        }
        
        // Listen to fisheye changes
        $("#fisheye").change(function(e) {
            putSetting(setting_fisheye, $(this).is(':checked'));
            visualizeData();
        });
    }else{
        $("#fisheyeContainer").remove();
    }
}

/**
* change view mode icons or info boxes
* 
*/
function handleViewMode() {
    $("#btnSwitchViewMode").click(function() {
        changeViewMode();
    });
}

/** GRAVITY SETTING */
function handleGravity() {
    if(settings.showGravity) {
        // Set gravity setting in UI
        $("#gravity option[value='" +getSetting(setting_gravity)+ "']").attr("selected", true);

        // Listen to gravity changes
        $("#gravity").change(function() {
            var gravity = $(this).val();
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
            
            visualizeData();
        });
    }else{
        $("#gravityContainer").remove();
    }
}
        
/** ATTRACTION SETTING */
function handleAttraction() {
    if(settings.showAttraction) {
        // Set attraction setting in UI
        $("#attraction").val(getSetting(setting_attraction));
        
        // Listen to attraction changes
        $("#attraction").change(function() {
            putSetting(setting_attraction, $(this).val());
            visualizeData();
        });
    }else{
        $("#attractionContainer").remove();
    }
}