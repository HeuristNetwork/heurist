/* global Utm */
// @todo: move to utils_geo
 // converts coordinate pairs (points) to WKT and calls callback function with result as parameter
 //
 // Requires external/js/geodesy-master/utm.js
 //
 function simplePointsToWKT( sCoords, type, UTMzone, callback){
     

    //let s = sCoords.replace(/[\b\t\n\v\f\r]/g, ' '); //replace invisible service chars
    let s = sCoords.replace(/[,]/g,' ');
    s = s.replace(/\s\s+/g, ' ').trim();
    //s = s.replace(/  +/g, ' ');  //only spaces 
    
    
    let arc = s.split(' ');  
    
    let islat = false;
    let hemisphere = 'N';  
    
    if(window.hWin.HEURIST4.util.isnull(type) && arc.length>2){
        //define type
            let $ddlg, buttons = {};
            buttons['Select'] = function(){ 
                
                let geoType = $ddlg.find('input[name="gtype"]:checked').val();
                setTimeout(function(){ simplePointsToWKT(sCoords, geoType, UTMzone, callback); },500);
                $ddlg.dialog('close'); 
            }; 
            buttons['Cancel'] = function(){ $ddlg.dialog('close'); };
        
            $ddlg = window.hWin.HEURIST4.msg.showMsgDlg( 
'<p style="text-align:center">There are more than one coordinate pairs.<br>'
+'Please define what geometric object they will be converted to</p><br>'
+'<p style="text-align:center"><label><input type="radio" name="gtype" value="Point" checked/>Point<label> '
   +'<label><input type="radio" name="gtype" value="LineString"/>Path<label> '
   +'<label><input type="radio" name="gtype" value="Polygon"/>Polygon<label></p>',
    buttons, 'Define geometric type');            
    
            return;
    }
    
    
    if(window.hWin.HEURIST4.util.isnull(UTMzone)){
        
        let allInteger = true, allOutWGS = true;
        //check for UTM - assume they are integer and at least several are more than 180
        for (let k=0; k<arc.length; k++){
            
            let crd = Number(arc[k]);
            if(isNaN(crd)){
                alert(arc[k]+" is not number value");
                return null;
            }
            allInteger = allInteger && (crd==parseInt(arc[k]));
            allOutWGS = allOutWGS &&  ((islat && Math.abs(crd)>90) || Math.abs(crd)>180);
            if (!(allOutWGS && allInteger)) break;
            
            islat = !islat;
        }
        if(allInteger || allOutWGS){ //offer to convert UTM to LatLong

            let $ddlg, buttons = {};
            buttons['Yes, UTM'] = function(){ 
                
                let UTMzone = $ddlg.find('#dlg-prompt-value').val();
                if(!window.hWin.HEURIST4.util.isempty(UTMzone)){
                        let re = /s|n/gi;
                        let zone = parseInt(UTMzone.replace(re,''));
                        if(isNaN(zone) || zone<1 || zone>60){
                            setTimeout('alert("UTM zone must be within range 1-60");',500);
                            return false;
                            //38N 572978.70 5709317.22 
                            //east 572980.08 5709317.24
                            //574976.85  5706301.55
                        }
                }else{
                    UTMzone = 0;
                }
                setTimeout(function(){  simplePointsToWKT(sCoords, type, UTMzone, callback); },500);
                $ddlg.dialog('close'); 
            }; 
            buttons['No'] = function(){ $ddlg.dialog('close'); 
                setTimeout(function(){ simplePointsToWKT(sCoords, type, 0, callback);},500);
            };
            
            $ddlg = window.hWin.HEURIST4.msg.showMsgDlg( 
'<p>We have detected coordinate values in the import '
+'which we assume to be UTM coordinates/grid references.</p><br>'
+'<p>Heurist will only import coordinates from one UTM zone at a time. Please '
+'split into separate files if you have more than one UTM zone in your data.</p><br>'
+'<p>UTM zone (1-60) and Hemisphere (N or S) for these data: <input id="dlg-prompt-value"></p>',
    buttons, 'UTM coordinates?');
    
             return;                       
        }
        UTMzone = 0;
    }
    
    
    else if(UTMzone!=0) {
        //parse UTMzone must be 1-60 N or S
        if(UTMzone.toLowerCase().indexOf('s')>=0){
            hemisphere = 'S';
        }
        let re = /s|n/gi;
        UTMzone = parseInt(UTMzone.replace(re,''));
        if(isNaN(UTMzone) || UTMzone<1 || UTMzone>60){
            setTimeout("alert('UTM zone must be within range 1-60')",500);
            return;
            //38N 572978.70 5709317.22 
        }
    }
    
    //verify and gather coordintes
    let coords = []; //Array of LatLngLiteral
    
    islat = true;
    for (let k=0; k<arc.length; k++){

        let crd = Number(arc[k]);
        if(isNaN(crd)){
            alert(arc[k]+" is not number value");
            return null;
        }else if(UTMzone==0 && !islat && Math.abs(crd)>180){
            alert(arc[k]+" is an invalid longitude value");
            return null;
        }else if(UTMzone==0 && islat && Math.abs(crd)>90){
            alert(arc[k]+" is an invalid latitude value");
            return null;
        }
        islat = !islat;
        if(islat){
            if(UTMzone>0){
                let easting = Number(arc[k-1]);
                let northing = crd;
                
                let utm = new Utm( UTMzone, hemisphere, easting, northing );
                let latlon = utm.toLatLonE();
                coords.push(latlon.lon+' '+latlon.lat); //X Y
                //coords.push({lat:latlon.lat, lng:latlon.lon});    
            }else{
                coords.push(Number(arc[k-1])+' '+crd);
                //coords.push({lat:Number(arc[k-1]), lng:crd});    
            }
        }
            
    }//for
    
    
    //create wkt -----------------------------------------
    let wkt = '';
    if (coords && coords.length>0){
        if(type=='Polygon'){
            if(coords.length<3){
                alert("Not enough coordinates for rectangle. Need at least 3 pairs");
            }else{
                coords.push(coords[0]);
                wkt =  'POLYGON (('+coords.join(', ')+'))';
            }
        }else if(type=='LineString'){
            if(coords.length<2){
                alert("Not enough coordinates for path. Need at least 2 pairs");
            }else{
                wkt =  'LINESTRING ('+coords.join(', ')+')';
            }
        }else if(coords.length==1){
                wkt =  'POINT ('+coords[0]+')';
                
        }else{
                wkt =  'MULTIPOINT ('+coords.join(', ')+')';
        }
    }
    if(wkt!='' && window.hWin.HEURIST4.util.isFunction(callback)){
        callback.call(this, wkt);
    }
}
