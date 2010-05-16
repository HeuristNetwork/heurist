<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0"
xmlns:exsl="http://exslt.org/common" extension-element-prefixes="exsl">


<xsl:template name="kml" match="reference[reftype/@id=103 or reftype/@id=51 or reftype/@id=165 or reftype/@id=122 or reftype/@id=57 or reftype/@id=168]">
	<div id="main" class="div-main">
		<div id="map" class="map"/>
		<div id="timeline" class="timeline"/>
		<div id="map-types" class="map-timeline-key"/>
		<div id="timeline-zoom" class="timeline-zoom"/>
	</div>
	
	<script type="text/javascript">			

		window.mapdata = {
			timemap: []
		};

		<xsl:if test="pointer[@id=588]">
			<!-- this template creates additional map layers in gmaps -->
			window.mapdata.layers = [
				<xsl:for-each select="pointer[@id=588]">
				{
					title: "<xsl:value-of select="detail[@id=173]"/>",
					type: "<xsl:value-of select="detail[@id=585]"/>",
					url: "<xsl:value-of select="detail[@id=339]"/>",
					mime_type: "<xsl:value-of select="detail[@id=289]"/>",
					min_zoom: "<xsl:value-of select="detail[@id=586]"/>",
					max_zoom: "<xsl:value-of select="detail[@id=587]"/>",
					copyright: "<xsl:value-of select="detail[@id=311]"/>"
				}<xsl:if test="position() != last()">,</xsl:if>
				</xsl:for-each>
			];
		</xsl:if>
	
	//from here down we are constructing various timeMap objects for our map
	
		<!-- a blank timeMap object -->
		<xsl:call-template name="generateTimeMapObjects">
			<xsl:with-param name="title">Blank</xsl:with-param>
			<xsl:with-param name="link">http://heuristscholar.org<xsl:value-of select="$urlbase"/>/blank.kml</xsl:with-param>
			<xsl:with-param name="theme">red</xsl:with-param>
		</xsl:call-template>
		<!-- related records -->
		<xsl:for-each select="related">
			<!-- Related KML Maps (103) and KML Files (165) -->
			<xsl:call-template name="getRelatedKMLItems">
				<xsl:with-param name="related"><xsl:value-of select="."/></xsl:with-param>
				<xsl:with-param name="currentId"><xsl:value-of select="id"/></xsl:with-param>
			</xsl:call-template>
		</xsl:for-each>
		<xsl:call-template name="generateTimeMapObjects">
			<xsl:with-param name="title">Related records</xsl:with-param>
			<xsl:with-param name="link">http://heuristscholar.org<xsl:value-of select="$cocoonbase"/>/kml/relatedto:<xsl:value-of select="id"/></xsl:with-param>
			<xsl:with-param name="theme"><xsl:value-of select="$relatedTheme"/></xsl:with-param>
		</xsl:call-template>
		<!-- pointer to Site Record (57) for Historical Events -->
		<xsl:for-each select="pointer[@id=276] | reverse-pointer[@id=276]">
			<xsl:call-template name="generateTimeMapObjects">
				<xsl:with-param name="title">Site reference</xsl:with-param>
				<xsl:with-param name="link">http://heuristscholar.org<xsl:value-of select="$cocoonbase"/>/kml/id:<xsl:value-of select="id"/></xsl:with-param>
				<xsl:with-param name="theme"><xsl:value-of select="$focusTheme"/></xsl:with-param>
			</xsl:call-template>
		</xsl:for-each>
		<!-- pointer to KML File (165) in case with KML map -->
		<xsl:for-each select="pointer[@id=564]">
			<xsl:call-template name="generateTimeMapObjects">
				<xsl:with-param name="title"><xsl:value-of select="title"/></xsl:with-param>
				<xsl:with-param name="link">http://heuristscholar.org<xsl:value-of select="$cocoonbase"/>/kmlfile/<xsl:value-of select="id"/></xsl:with-param>
				<xsl:with-param name="theme">
					<xsl:choose>
						<xsl:when test="detail[@id=567]">
							<xsl:value-of select="detail[@id=567]"/>
						</xsl:when>
						<xsl:otherwise>
							<xsl:value-of select="$relatedTheme"/>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:with-param>
			</xsl:call-template>
		</xsl:for-each>
		<!-- record in focus -->
		<xsl:call-template name="generateTimeMapObjects">
			<xsl:with-param name="title">Focus record</xsl:with-param>
			<xsl:with-param name="link">
				<xsl:choose>
					<xsl:when test="reftype[@id = 165]">http://heuristscholar.org<xsl:value-of select="$cocoonbase"/>/kmlfile/<xsl:value-of select="id"/></xsl:when>
					<xsl:otherwise>http://heuristscholar.org<xsl:value-of select="$cocoonbase"/>/kml/id:<xsl:value-of select="id"/></xsl:otherwise>
				</xsl:choose>
			</xsl:with-param>
			<xsl:with-param name="theme">
				<xsl:choose>
					<xsl:when test="detail[@id=567]">
						<xsl:value-of select="detail[@id=567]"/>
					</xsl:when>
					<xsl:otherwise>
						<xsl:value-of select="$focusTheme"/>
					</xsl:otherwise>
				</xsl:choose>
			</xsl:with-param>
		</xsl:call-template>
		<xsl:call-template name="generateTimeMapObjectsForCrumbs"></xsl:call-template>
		
	if (enableMapTrack){
		saveAndLoad({recId: <xsl:value-of select="id"/>, recTitle: document.getElementById("<xsl:value-of select="id"/>").text, recType: '<xsl:value-of select="reftype/@id"/>', hasGeoData:'<xsl:call-template name="checkForGeoData"/>'});	
	} else {
		initTMap();
	}
        	</script>
</xsl:template>
	
<xsl:template name="generateTimeMapObjectsForCrumbs">
	if (enableMapTrack){
	// and time map objects based on breadcrumbs!
	HAPI.PJ.retrieve(_nameTrack, function (name, value){
		gatherCrumbs(_nameTrack, value,<xsl:value-of select="id"/>);
		if (value)  {
			var i;				
			for ((value[0].recId == <xsl:value-of select="id"/>?i=1: i=0); i &lt; value.length; ++i) {
				var link;
				var title = "MapCrumb" + i;
	
				if (value[i].recType &amp; value[i].recType == '165') {
					link = "http://heuristscholar.org<xsl:value-of select="$cocoonbase"/>/kmlfile/" + value[i].recId;
				} else {
					link = "http://heuristscholar.org<xsl:value-of select="$cocoonbase"/>/kml/id:" + value[i].recId;
				}
	
				var timeCrumb = {
					title: "Breadcrumb"+i,
					theme: TimeMapDataset.redTheme({
						color: crumbThemes[(value[0].recId == <xsl:value-of select="id"/>?i-1: i)].colour,
						iconImage: crumbThemes[(value[0].recId == <xsl:value-of select="id"/>?i-1: i)].icon
					}),
					data: {
						type: "kml", 
						url: link
					}
				}					
				window.mapdata.timemap.push(timeCrumb);
			}
		}	
	}); //end hapi   
	}
</xsl:template>
	
<xsl:template name="checkForGeoData">	
		<!-- Historical event 51 -->
	<xsl:if test="reftype/@id = 51 or reftype/@id = 122 or reftype/@id = 57">
			<xsl:for-each select="detail[@id=230]">
				<xsl:value-of select="normalize-space(.)"/><xsl:if test="position() != last()">,</xsl:if>
			</xsl:for-each>
		</xsl:if>
		<!-- KML maps 103-->
		<xsl:if test="reftype/@id = 103">
			<xsl:for-each select="pointer[@id=564]">
				<!-- fix me for uploaded files! -->
				<xsl:if test="contains(detail[@id=551],'Polygon') or contains(detail[@id=551],'LineString') or contains(detail[@id=551],'MultiGeometry')">l</xsl:if>				
				<xsl:if test="contains(detail[@id=551],'Point')">p</xsl:if>		
				<xsl:if test="position() != last()">,</xsl:if>				
			</xsl:for-each>
		</xsl:if>
		<!-- KML maps 165-->
		<xsl:if test="reftype/@id = 165">			
			<!-- fix me for uploaded files! -->
			<xsl:if test="contains(detail[@id=551],'Polygon') or contains(detail[@id=551],'LineString') or contains(detail[@id=551],'MultiGeometry')">l</xsl:if>				
			<xsl:if test="contains(detail[@id=551],'Point')">p</xsl:if>												
		</xsl:if>
</xsl:template>

<xsl:template name="getRelatedKMLItems">
	<!-- Related KML Maps (103) and KML Files (165) -->
	
	<xsl:param name="related"/>
	<xsl:param name="currentId"/>
	
	<xsl:if test="reftype[@id = 103]">			
		<!-- KML MAP (103) can contain pointers(564) to To KML file (165) or related Historical Events (51) -->
		<xsl:if test="pointer[@id=564]">
			<xsl:for-each select="pointer[@id=564]">				
				<xsl:call-template name="KMLFile165"/>
			</xsl:for-each>
		</xsl:if>
		<xsl:if test="related/reftype[@id=51]">
			<xsl:for-each select="related/reftype[@id=51]">
				<xsl:if test="../id != $currentId">
					<xsl:call-template name="generateTimeMapObjects">
						<xsl:with-param name="title">Related records</xsl:with-param>
						<xsl:with-param name="link">http://heuristscholar.org<xsl:value-of select="$cocoonbase"/>/kml/id:<xsl:value-of select="../id"/></xsl:with-param>
						<xsl:with-param name="theme"><xsl:value-of select="$relatedTheme"/></xsl:with-param>
					</xsl:call-template>
				</xsl:if>
			</xsl:for-each>
		</xsl:if>		
	</xsl:if>
	<xsl:if test="reftype[@id = 165]">
		<!-- KML file (165) may or may not have a default symbology colour (567) detail type -->
		<xsl:call-template name="KMLFile165"/>
	</xsl:if>
</xsl:template>
<xsl:template name="KMLFile165">
	<xsl:choose>
		<xsl:when test="detail[@id=567]">
			<xsl:call-template name="generateTimeMapObjects">
				<xsl:with-param name="title">Related records</xsl:with-param>
				<xsl:with-param name="link">http://heuristscholar.org<xsl:value-of select="$cocoonbase"/>/kmlfile/<xsl:value-of select="id"/></xsl:with-param>
				<xsl:with-param name="theme"><xsl:value-of select="detail[@id=567]"/></xsl:with-param>
			</xsl:call-template>
		</xsl:when>
		<xsl:otherwise>
			<xsl:call-template name="generateTimeMapObjects">
				<xsl:with-param name="title">Related records</xsl:with-param>
				<xsl:with-param name="link">http://heuristscholar.org<xsl:value-of select="$cocoonbase"/>/kmlfile/<xsl:value-of select="id"/></xsl:with-param>
				<xsl:with-param name="theme"><xsl:value-of select="$relatedTheme"/></xsl:with-param>
			</xsl:call-template>
		</xsl:otherwise>
	</xsl:choose>
</xsl:template>

<xsl:template name="generateTimeMapObjects">
	<xsl:param name="title"/>
	<xsl:param name="link"/>
	<xsl:param name="theme"/>

	window.mapdata.timemap.push({
		title: "<xsl:value-of select="$title"/>",
		<xsl:choose>
			<xsl:when test="exsl:node-set($timeMapThemes)/theme[@name=$theme]/@name">
				theme: TimeMapDataset.redTheme({
					iconImage:'<xsl:value-of select="exsl:node-set($timeMapThemes)/theme[@name=$theme]/icon"/>',
					color:'<xsl:value-of select="exsl:node-set($timeMapThemes)/theme[@name=$theme]/colour"/>'
				}),
			</xsl:when>
			<xsl:otherwise>
				theme: TimeMapDataset.<xsl:value-of select="$theme"/>Theme(),
			</xsl:otherwise>
		</xsl:choose>
		data: {
			type: "kml", // Data to be loaded in KML - must be a local URL
			url: "<xsl:value-of select="$link"/>" // KML file to load
		}
	});
</xsl:template>
<xsl:template name="generateIntervalUnit">
	<xsl:param name="varName"/>
	<xsl:param name="value"/>
	var <xsl:value-of select="$varName"/> = Timeline.DateTime.<xsl:value-of select="$value"/>;
</xsl:template>
	
<!-- below are all the templates concerned with presentation of related items for those record types  as rendered in item_view.xsl -->
	
<xsl:template name="renderAppropriateLegend">
	<xsl:param name="themeToUse"/>
	<xsl:param name="record"/>
	
	<xsl:if test="$record/detail[@id=230]!='p'">
		<xsl:call-template name="colourSpan">
			<xsl:with-param name="colour">
				<xsl:value-of select="exsl:node-set($timeMapThemes)/theme[@name=$themeToUse]/colour"/>
			</xsl:with-param>
		</xsl:call-template>
	</xsl:if>
	<!-- as above put points only -->
	<xsl:if test="$record/detail[@id=230]='p'">
		<img src="{exsl:node-set($timeMapThemes)/theme[@name=$themeToUse]/icon}" height="15"/>
	</xsl:if>
	<!--TODO: rendering of legend based on contents of uploaded file -->
	<!--xsl:if test="contains(document(detail[@id=221]/file_fetch_url), 'kml')">
		<span style="background-color: {exsl:node-set($timeMapThemes)/theme[@name=$relatedTheme]/colour}; padding-right: 10px; padding-left: 5px;"/>
	</xsl:if-->
	
	<!-- KML File (165) -->							
	<xsl:if test="contains($record/detail[@id=551],'Polygon') or contains($record/detail[@id=551],'LineString') or contains($record/detail[@id=551],'MultiGeometry')">
		<xsl:call-template name="generateLegend165">
			<xsl:with-param name="themeToUse" select="$themeToUse"/>
			<xsl:with-param name="point"/>
			<xsl:with-param name="record" select="$record"/>
		</xsl:call-template>
	</xsl:if>
	<xsl:if test="contains($record/detail[@id=551],'Point')">
		<xsl:call-template name="generateLegend165">
			<xsl:with-param name="themeToUse" select="$themeToUse"/>
			<xsl:with-param name="point">true</xsl:with-param>
			<xsl:with-param name="record" select="$record"/>
		</xsl:call-template>
	</xsl:if>							
	
</xsl:template>
<xsl:template name ="colourSpan">
	<xsl:param name="colour"/>
	<span style="background-color: {$colour}; padding-right: 10px; padding-left: 5px;"/>
</xsl:template>
	
<xsl:template name="generateLegend165">
	<!-- an appropriate legend for type KML File 165 -->
	<!-- kml file may contain a specified theme (567) -->
	<xsl:param name="point"/>
	<xsl:param name="themeToUse"/>
	<xsl:param name="record"/>
	<xsl:choose>
		<xsl:when test="$record/detail[@id=567]">
			<xsl:variable name="thisColour" select="$record/detail[@id=567]"/>
			<xsl:choose>
				<xsl:when test="point">
					<img src="{exsl:node-set($timeMapThemes)/theme[@name=$thisColour]/icon}" height="15"/>
				</xsl:when>
				<xsl:otherwise>
					<xsl:call-template name="colourSpan">
						<xsl:with-param name="colour">
							<xsl:value-of select="exsl:node-set($timeMapThemes)/theme[@name=$thisColour]/colour"/>
						</xsl:with-param>
					</xsl:call-template>
				</xsl:otherwise>
			</xsl:choose>
		</xsl:when>
		<xsl:otherwise>
			<xsl:choose>
				<xsl:when test="point">
					<img src="{exsl:node-set($timeMapThemes)/theme[@name=$themeToUse]/icon}" height="15"/>
				</xsl:when>
				<xsl:otherwise>
					<xsl:call-template name="colourSpan">
						<xsl:with-param name="colour">
							<xsl:value-of select="exsl:node-set($timeMapThemes)/theme[@name=$themeToUse]/colour"/>
						</xsl:with-param>
					</xsl:call-template>
				</xsl:otherwise>
			</xsl:choose>
		</xsl:otherwise>
	</xsl:choose>
</xsl:template>
	
</xsl:stylesheet>
