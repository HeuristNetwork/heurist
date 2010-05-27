<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:exsl="http://exslt.org/common"
                version="1.0">

	<xsl:template name="kml" match="reference[reftype/@id=103 or reftype/@id=51 or reftype/@id=165 or reftype/@id=122 or reftype/@id=57 or reftype/@id=168]">

		<div>

			<!-- dc.description -->
			<xsl:if test="detail[@id=303]">
				<p> ......
					<xsl:value-of select="detail[@id=303]"/>
				</p>
			</xsl:if>

			<div id="map-controls">
				<div id="map-types"/>
				<div id="map-key"/>
			</div>
			<div id="map"/>
			<div id="timeline-zoom"/>
			<div id="timeline"/>
			<script>
				<xsl:variable name="sources">
					<!-- kml references -->
					<xsl:for-each select="pointer[@id=564]">
						<source>
							<title><xsl:value-of select="detail[@id=160]"/></title>
							<!--url><xsl:call-template name="getFileURL">
									<xsl:with-param name="file" select="detail[@id=221]"/>
								</xsl:call-template></url-->
							<url>../kmlfile/<xsl:value-of select="id"/></url>
						</source>
					</xsl:for-each>
					<!-- related entities that have a TimePlace factoid -->
					<xsl:for-each select="related[reftype/@id=151]
					                             [reverse-pointer[@id=528]
					                                             [detail[@id=526]='TimePlace']]">
						<source>
							<title><xsl:value-of select="detail[@id=160]"/></title>
							<!--url><xsl:value-of select="$urlbase"/>kml/summary/<xsl:value-of select="id"/>.kml</url-->
							<url>../kml/summary/rename/<xsl:value-of select="id"/></url>
						</source>
					</xsl:for-each>
				</xsl:variable>

				window.mapdata = {
					timemap: [
						<xsl:for-each select="exsl:node-set($sources)/source">
						{
							title: "<xsl:value-of select="title"/>",
							data: {
								type: "kml",
								url: "<xsl:value-of select="url"/>"
							}
						}<xsl:if test="position() != last()">,</xsl:if>
						</xsl:for-each>
					],
					layers: [
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
					]
				};

				$(function() {
					initTMap();
				});
			</script>

		</div>

	</xsl:template>

	<xsl:template name="renderAppropriateLegend">
		</xsl:template>



</xsl:stylesheet>
