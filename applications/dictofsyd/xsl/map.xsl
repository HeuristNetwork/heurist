<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:exsl="http://exslt.org/common"
                exclude-result-prefixes="exsl"
                version="1.0">

	<xsl:template match="record[type/@conceptID='1084-28']">


		<xsl:variable name="factoids_recs" select="/hml/records/record[type/@conceptID='1084-26']"/>

<!--
		<xsl:variable name="related" select="
			relationships
				/record
					/detail[@id=202 or @id=199]
						/record[id != current()/id]
		"/>
-->		

		<div>

			<!-- dc.description -->
			<xsl:if test="detail[@conceptID='2-4']">
				<p class="map-description">
					<xsl:value-of select="detail[@conceptID='2-4']"/>
				</p>
			</xsl:if>

			<div id="map" class="full"/>
			<div id="timeline-zoom">
				<xsl:if test="not($factoids_recs/detail[@conceptID='2-10'])">
					<xsl:attribute name="class">hide</xsl:attribute>
				</xsl:if>
			</div>
			<div id="timeline">
				<xsl:if test="not($factoids_recs/detail[@conceptID='2-10'])">
					<xsl:attribute name="class">hide</xsl:attribute>
				</xsl:if>
			</div>
			<script type="text/javascript">
				var datasets = [];
				
					<!-- kml records -->
					<xsl:variable name="kml_rec_ids" select="detail[@conceptID='1084-91']"/>
					<xsl:for-each select="/hml/records/record[id=$kml_rec_ids]">
						datasets.push({
							title: "<xsl:value-of select="detail[@conceptID='2-1']"/>",
							type: "kml",
							options: {
								url: "<xsl:call-template name="getFileURL">
									<xsl:with-param name="file" select="detail[@conceptID='2-38']"/>
								</xsl:call-template>"
							}
						});
					</xsl:for-each>
				
					var items = [];
					<!-- related entities that have a TimePlace factoid ARTEM???? -->
					<xsl:variable name="factoids_timeplace" select="$factoids_recs[detail[@conceptID='1084-85']='TimePlace']"/>
					<xsl:for-each select="$factoids_timeplace">
						
						<xsl:variable name="entity" select="/hml/records/record[id=current()/detail[@conceptID='1084-87']]"/>
						<xsl:variable name="geoobj" select="current()/detail[@conceptID='2-28']/geo"/>
						
						items.push(getTimeMapItem(<xsl:value-of select="$entity/id"/>,
														"<xsl:value-of select="$entity/detail[@conceptID='1084-75']"/>",
														"<xsl:value-of select="$entity/detail[@conceptID='2-1']"/>",
														'<xsl:value-of select="current()/detail[@conceptID='2-10']/raw"/>',
				<xsl:choose>
					<xsl:when test="current()/detail[@conceptID='2-11']/raw">'<xsl:value-of select="current()/detail[@conceptID='2-11']/raw"/>',</xsl:when>
					<xsl:otherwise><xsl:text>'2013',</xsl:text></xsl:otherwise>
				</xsl:choose>
														'<xsl:value-of select="$geoobj/type"/>', 
														'<xsl:value-of select="$geoobj/wkt"/>'));
						
<!--						
						<source>
							<title><xsl:value-of select="$entity/detail[@conceptID='2-1']"/></title>
							<url>http://dictionaryofsydney.org/kml/summary/<xsl:value-of select="$entity/id"/>.kml</url>
							<target>
								<xsl:text>../</xsl:text>
								<xsl:call-template name="getPath">
									<xsl:with-param name="id" select="$entity/id"/>
								</xsl:call-template>
							</target>
							<preview><xsl:value-of select="$entity/id"/></preview>   c<xsl:value-of select="../../id"/>
						</source>
-->						
					</xsl:for-each>

				if(items.length>0){
						datasets.push({
								type: "basic",	
								options: { items: items }
						});
				}

				var mapdata = {
					<xsl:if test="detail[@conceptID='2-28']">
					focus: "<xsl:value-of select="detail[@conceptID='2-28']/geo/wkt"/>",
					</xsl:if>
					timemap: datasets,
					layers: [
						<xsl:variable name="mapimage_rec_ids" select="detail[@conceptID='1084-93']"/>
						<xsl:for-each select="/hml/records/record[id=$mapimage_rec_ids]">
						{
							title: "<xsl:value-of select="detail[@conceptID='2-2']"/>",
							type: "<xsl:value-of select="detail[@conceptID='2-31']"/>",
							url: "<xsl:value-of select="detail[@conceptID='2-34']"/>",
							<!--
							url: "<xsl:value-of select="$urlbase"/>tiles/<xsl:value-of select="id"/>/", ARTEM TODO!!! 
							url: "http://dictionaryofsydney.org/map/tiles/<xsl:value-of select="id"/>/", -->
							mime_type: "<xsl:value-of select="detail[@conceptID='2-29']"/>",
							min_zoom: "<xsl:value-of select="detail[@conceptID='2-32']"/>",
							max_zoom: "<xsl:value-of select="detail[@conceptID='2-33']"/>",
							copyright: "<xsl:value-of select="detail[@conceptID='2-35']"/>"
						}<xsl:if test="position() != last()">,</xsl:if>
						</xsl:for-each>
					]
				};
				//added to ensure proper startup timing SAW 2-2-12
				//initialiseMapping();
				initMapping("map", mapdata);
			</script>

		</div>

	</xsl:template>


	<xsl:template match="record[type/@conceptID='1084-28']" mode="sidebar">
			<div id="connections">
			<h3>Connections</h3>
			<xsl:call-template name="relatedEntitiesByType"/>
			<xsl:call-template name="connections"/>
		</div>
	</xsl:template>

</xsl:stylesheet>
