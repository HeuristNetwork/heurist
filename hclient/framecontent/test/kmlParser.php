<?php
require_once(dirname(__FILE__).'/../../../external/geoPHP/geoPHP.inc');
require_once(dirname(__FILE__).'/../../../external/geoPHP/geoPHPfeatures.inc');


//$kml_content = file_get_contents('c:/work/acl/kml/Danggan Balun (Five Rivers) People.kml'); 
//$kml_content = file_get_contents('c:/work/acl/kml/NTTTest1CenQld.kml');
//$kml_content = file_get_contents('c:/work/acl/kml/March2011Sites.kml');
$kml_content = file_get_contents('c:/work/acl/kml/KML_Samples.kml');

$kml = new KMLFeatures();
$kml->setCallback('onParsePlacemark');
$kml->read($kml_content);
/*
while ($kml->getNextPlacemark()) {
    $geometry = $kml->getGeometry();
    $properties = $kml->getProperties();

    if($geometry){
        $wkt = new WKT();  
        $geometry = $wkt->write($geometry);
    }
    
    print $geometry.' <br>';        
    print print_r($properties, true);        
    
    unset($properties);
    
}
*/
print 'END';


//$feats = $features->getFeatures();

//
//
function onParsePlacemark($geometry, $properties, $cnt) {
    
        if($geometry){
            $wkt = new WKT();  
            $geometry = $wkt->write($geometry);
        }
        
        print $geometry.' <br>';        
        print print_r($properties, true);        
        
        unset($properties);
        
        return true;// false;
}
?>