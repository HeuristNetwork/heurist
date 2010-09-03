<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
	<xsl:template name="thesis" match="record[type/@id=13]">
		<div id="{id}" class="record">
			<h2><xsl:value-of select="title"/></h2>
			<br/>
			<xsl:value-of select="detail[@id=243]"/><!-- Thesis type -->
			<br/>
			<xsl:value-of select="detail[@id=303]"/><!-- Summary for web -->
		</div>
	</xsl:template>
</xsl:stylesheet>
