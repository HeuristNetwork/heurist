<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
  <!-- 
 this style renders geographical objects using HAPI and GOI
 author  Maria Shvedova
 last updated 2/12/2008 ms
  -->
  <xsl:param name="arg"/>
 
  <xsl:template match="/">
    <html>
      <head>
        
        <style type="text/css">
          body {font-family:Verdana,Helvetica,Arial,sans-serif; font-size:11px; }
          td { vertical-align: top; }
          .rectype {
          color: #999999;
          
          }
        </style>
		
       <script src="{$hBase}common/php/loadHAPI.php?db={$instance}"/>
	<script src="{$hBase}hapi/js/goi.js"/>
	<script src="http://maps.google.com/maps?file=api&amp;v=2&amp;key=ABQIAAAAogdH9AwvOFbvnh0YaNkLgBTSYVSXM3_pxTZfKUi_fD1c4-9PWxSMejnbLNsbQ7VMTSucGheu4pvMbA"/>
        <script src="http://www.google.com/uds/api?file=uds.js&amp;v=1.0&amp;key=ABQIAAAAogdH9AwvOFbvnh0YaNkLgBTSYVSXM3_pxTZfKUi_fD1c4-9PWxSMejnbLNsbQ7VMTSucGheu4pvMbA" type="text/javascript"></script>
      </head>
      <body>
          <div id="map" style="width: 480px; height: 320px;"></div>
          <div id="objects" style="padding-top: 5px; "></div>
  <script>
    var arg = <xsl:value-of select="$arg"/>;  
   
	var mysearch = new HSearch("id:" + arg);
 	var loader = new HLoader(	
 	
	function(s,r) {
		
		var geoDetails = r[0].getDetails(HDetailManager.getDetailTypeById(230));
		console.log(geoDetails);
		if (GBrowserIsCompatible()) {
		  map = new GMap2(document.getElementById("map"));
		  map.enableDoubleClickZoom();
		  map.enableContinuousZoom();
		  map.enableScrollWheelZoom();
		  map.addControl(new GLargeMapControl());
		  map.addControl(new GMapTypeControl());
		  map.removeMapType(G_SATELLITE_MAP);
		  map.addMapType(G_PHYSICAL_MAP);
		  map.setCenter(new GLatLng(4, 100), 2, G_PHYSICAL_MAP);
		}
		
		for (i = 0; i&lt;geoDetails.length; i++) {
	           document.getElementById("objects").innerHTML +=  geoDetails[i] + "<br/>";
		    loadMap(geoDetails[i].getWKT(), geoDetails[i].getType().toUpperCase());
		  
		}
	},
	
	function(s,e) { alert("Load failed: " + e); });
	HeuristScholarDB.loadRecords(mysearch, loader);
	
	function loadMap(value, type) {	
		digitiser = new HAPI.GOI.Digitiser(map,value, type);
	}
  </script>    
      </body>
    </html>
    
  </xsl:template>
</xsl:stylesheet>
