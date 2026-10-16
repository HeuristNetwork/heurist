/**
* @file editingHelpers.js
* @brief Miscellaneous helpers for editing_input.
* @fileOverview Contains editing_input-specific helpers that do not belong to record/term browsing, translations, or map symbology editing.
* @project     Heurist academic knowledge management system
* @package     Widgets.Editing
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay <blmckay13@gmail.com>
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

/**
 * Calculates the geographic extent (bounding box) of an image based on its dimensions and an associated world file.
 * This function is typically triggered from within an HEditing form context.
 *
 * @memberof Widgets.Editing
 * @param {HEditing} _editing - The HEditing instance managing the form that contains the image and world file fields.
 *                              This instance is used to get and set field values (e.g., image file, world file parameters, bounding box).
 * @param {string} [ulf_ID=null] - The obfuscated file ID (ulf_ObfuscatedFileID) of the image.
 *                                 If not provided, the function attempts to retrieve it from the relevant field
 *                                 within the `_editing` form.
 */
function calculateImageExtentFromWorldFile(_editing, ulf_ID = null){

    if(!_editing) return;

    let worldFile = null;
    
    //
    // calculate extent based on worldfile parameters
    //
    let dtId_File = window.hWin.HAPI4.sysinfo['dbconst']['DT_FILE_RESOURCE'];
    let ele = _editing.getFieldByName( dtId_File );
    if(ele && !ulf_ID){

        let val = ele.editing_input('getValues');
        if(val && val.length>0){

            ulf_ID = val[0]['ulf_ObfuscatedFileID'];
            if(!ulf_ID && val[0]['ulf_ID'] && parseInt(val[0]['ulf_ID']) > 0){

                let request = {
                    recID: parseInt(val[0]['ulf_ID']),
                    a: 'search',
                    details: 'list',
                    entity: 'recUploadedFiles',
                    request_id: window.hWin.HEURIST4.util.random()
                };

                window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){

                        let recordset = new HRecordSet(response.data);
                        let record = recordset.getFirstRecord();
                        if(record){
                            calculateImageExtentFromWorldFile(_editing, recordset.fld(record,'ulf_ObfuscatedFileID'));
                        }else{
                            window.hWin.HEURIST4.msg.showMsgFlash('Invalid image file provided');
                        }
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });

                return;
            }
        }
    }

    let dtId_WorldFile = window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_IMAGE_WORLDFILE'];
    ele = _editing.getFieldByName( dtId_WorldFile );
    if(ele){
        let val = ele.editing_input('getValues');
        if(val && val.length>0 && !window.hWin.HEURIST4.util.isempty( val[0] )){
            worldFile = val[0];    
        }
    }

    if(ulf_ID && worldFile){

        let dtId_Geo = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'];
        ele = _editing.getFieldByName( dtId_Geo );
        if(!ele){
            window.hWin.HEURIST4.msg.showMsgErr({
                message: 'Image map source record must have Bounding Box field! '
                        +'Please correct record type structure.',
                error_title: 'Missing bounding box'
            });
        }else{

            window.hWin.HEURIST4.msg.showMsgDlg(
                '<p>Recalculate image extent based on these parameters and image dimensions. </p>'+
                '<p>You can also define extent directly by drawing rectangle in map digitizer</p>',
                function() {
                    //get image dimensions
                    window.hWin.HAPI4.checkImage('Records', ulf_ID, 
                        null,
                        function(response){
                            if(response!=null && response.status == window.hWin.ResponseStatus.OK){
                                if($.isPlainObject(response.data) && 
                                    response.data.width>0 && response.data.height>0)
                                {
                                    let extentWKT = window.hWin.HEURIST4.geo.parseWorldFile(worldFile, 
                                        response.data.width, response.data.height);

                                    if(extentWKT){
                                        _editing.setFieldValueByName(dtId_Geo, 'pl '+extentWKT);
                                    }else{
                                        window.hWin.HEURIST4.msg.showMsgErr({
                                            message: 'Cannot calculate image extent. Verify your worldfile parameters',
                                            error_title: 'Invalid image extent'
                                        });
                                    }

                                }else{
                                    let error = response.data.error ? response.data.error : response.data;
                                    error = $.isPlainObject(error) ? error : {message: error, error_title: 'Data error'};
                                    window.hWin.HEURIST4.msg.showMsgErr( error );
                                }
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr( response );
                            }
                        }
                    );

                },
                {title:'Calculate image extent', yes:'Proceed', no:'Cancel'});

        }                    
    }else if(!ulf_ID){
        window.hWin.HEURIST4.msg.showMsgFlash('Define image file first');
    }else if(!worldFile){
        window.hWin.HEURIST4.msg.showMsgFlash('Define valid worldfile parameters first');
    }

}

