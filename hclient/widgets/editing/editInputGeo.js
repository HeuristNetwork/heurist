/**
* editInputGeo.js widget for input controls on edit form
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
import "./editInputBase.js";

$.widget( "heurist.editInputGeo", $.heurist.editInputBase, {

    // default options
    options: {
    },
    
    _gicon: null,
    
    _create: function() {
        
            var that = this;
            
            this._super();
        
            var $inputdiv = this.element;
            $inputdiv.uniqueId();
            
            var $input = $( "<input>")
            .addClass('text ui-widget-content ui-corner-all')
            .css({'width':'62ex','padding-left':'30px',cursor:'hand'})
            .appendTo( $inputdiv );
            
            window.hWin.HEURIST4.ui.disableAutoFill( $input );
            
            this._input = $input;

            this._on( this._input, { keyup:this.onChange, change:this.onChange });
            
            this._gicon = $('<span>').addClass('ui-icon ui-icon-globe')
                    .css({position:'absolute',margin:'4px 0 0 8px',cursor:'hand'})
                    .insertBefore($input);

            var geovalue = window.hWin.HEURIST4.geo.wktValueToDescription(this._newvalue);

            if(geovalue.summary && geovalue.summary != ''){
                $input.val(geovalue.type+'  '+geovalue.summary).css('cursor','hand');
            }else if(!window.hWin.HEURIST4.util.isempty(this._newvalue)){
                var parsedWkt = window.hWin.HEURIST4.geo.getParsedWkt(this._newvalue, true);
                if(parsedWkt == '' || parsedWkt == null){
                    $input.val('');
                    $('<span>').addClass('geo-badvalue').css({'display': 'inline-block', 'margin-left': '5px'})
                            .text('Bad value: ' + this._newvalue)
                            .appendTo($inputdiv);
                }else{
                    if(parsedWkt.type == 'Point'){

                        var invalid = '';
                        if(Math.abs(parsedWkt.coordinates[0]) > 180){
                            invalid = 'longitude is';
                        }
                        if(Math.abs(parsedWkt.coordinates[1]) > 90){
                            invalid = (invalid != '') ? 'longitude and latitude are' : 'latitude is';
                        }
                        $('<span>').addClass('geo-badvalue').css({'display': 'inline-block', 'margin-left': '5px', color: 'red'})
                                .text(invalid + ' outside of range')
                                .appendTo($inputdiv);
                    }
                }
            }
                      
                var __show_mapdigit_dialog = function (event){
                        event.preventDefault();
                        
                        if(that._container.is_disabled) return;
                    
                        var url = window.hWin.HAPI4.baseURL 
                            +'viewers/map/mapDraw.php?db='+window.hWin.HAPI4.database;
                       
                        var wkt_params = {'wkt': that._newvalue };
                        if(that.options.is_faceted_search){
                            wkt_params['geofilter'] = true;
                        }

                        if(this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_GEOTIFF_SOURCE']){

                            var ele = that.options.editing.getFieldByName(window.hWin.HAPI4.sysinfo['dbconst']['DT_FILE_RESOURCE']);
                            var vals = ele.editing_input('getValues');
                            if($.isArray(vals) && vals.length>0){
                                vals = vals[0];
                                if(vals['ulf_ExternalFileReference']){
                                    wkt_params['imageurl'] = vals['ulf_ExternalFileReference'];
                                }else{
                                    wkt_params['imageurl'] = window.hWin.HAPI4.baseURL
                                        +'?db='+window.hWin.HAPI4.database
                                        +'&file='+vals['ulf_ObfuscatedFileID'];
                                }
                                wkt_params['tool_option'] = 'image';
                            }else{
                                wkt_params['tool_option'] = 'rectangle';
                            }
                        }

                        if(this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_KML_SOURCE'] || this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_FILE_SOURCE'] || 
                        this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_SHP_SOURCE'] || this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT'] || 
                        this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_LAYER'] || this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_QUERY_SOURCE'] || 
                        this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_TILED_IMAGE_SOURCE']) {
                            // assume bounding box, rectangle tool only
                            wkt_params['tool_option'] = 'rectangle';
                        }

                        var d_width = (window.hWin?window.hWin.innerWidth:window.innerWidth)*0.95,
                        d_height = (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95;

                        window.hWin.HEURIST4.msg.showDialog(url, {
                            height:that.options.is_faceted_search?540:d_height,
                            width:that.options.is_faceted_search?600:d_width,
                            window: window.hWin,  //opener is top most heurist window
                            dialogid: 'map_digitizer_dialog',
                            default_palette_class: 'ui-heurist-populate',
                            params: wkt_params,
                            title: window.hWin.HR('Heurist map digitizer'),
                            //class:'ui-heurist-bg-light',
                            callback: function(location){
                                if( !window.hWin.HEURIST4.util.isempty(location) ){
                                    //that._newvalue = location
                                    that._newvalue = (that.options.is_faceted_search
                                                ?'':(location.type+' '))
                                                +location.wkt;
                                    var geovalue = window.hWin.HEURIST4.geo.wktValueToDescription(location.type+' '+location.wkt);
                                    if(that.options.is_faceted_search){
                                        $input.val(geovalue.summary).trigger('change');
                                    }else{
                                        $input.val(geovalue.type+'  '+geovalue.summary);
                                        $input.trigger('change');

                                        $inputdiv.find('span.geo-badvalue').remove();
                                    }
                                    
                                    //$input.val(location.type+' '+location.wkt)
                                }
                            }
                        } );
                };

                //this._on( $link_digitizer_dialog, { click: __show_mapdigit_dialog } );
                //this._on( $btn_digitizer_dialog, { click: __show_mapdigit_dialog } );
                if(that._container.isReadonly()){
                     this._input.removeClass('ui-widget-content')
                        .css({'background-color':'transparent', border:0})
                        .attr('readonly',true);
                }else{
                    this._on( $input, { keypress: __show_mapdigit_dialog, click: __show_mapdigit_dialog } );
                    this._on( this._gicon, { click: __show_mapdigit_dialog } );
                }
        
    },
    
    _destroy: function() {
        if(this._gicon) this._gicon.remove();
        if(this._input) this._input.remove();
    },


    /**
    * 
    */
    setWidth: function(dwidth){

        if( typeof dwidth==='string' && dwidth.indexOf('%')== dwidth.length-1){ //set in percents
            this._input.css('width', dwidth);
        }
/*        
        else{
              //if the size is greater than zero
              var nw = (this.detailType=='integer' || this.detailType=='float')?40:120;
              if (parseFloat( dwidth ) > 0){ 
                  nw = Math.round( 3+Number(dwidth) );
                    //Math.round(2 + Math.min(120, Number(dwidth))) + "ex";
              }
              this._input.css({'min-width':nw+'ex','width':nw+'ex'}); //was *4/3
        }
*/        
    },
    
    /**
    * put your comment there...
    */
    clearValue: function(){
        this._newvalue = '';    
        if(this._input){
            this._input.val('');   
        }
    }
    
});