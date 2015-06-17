<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
<xsl:template name="conference" match="record[type/@id=49]">
<xsl:param name="style"/>
	<div id="{id}" class="record L{@depth}">
		<span><h2><xsl:value-of select="detail[@id=160]"/></h2></span><br/>
		<!-- Title -->
		<xsl:choose>
			<xsl:when test="$style = 'compressed'"> </xsl:when>
			<xsl:otherwise>
				<xsl:value-of select="detail[@id=181]"/>
				<br/>
				<!-- Conference location -->
			</xsl:otherwise>
		</xsl:choose>
		<xsl:value-of select="detail[@id=177]/text()"/><!-- Start Date -->
		<xsl:if test="detail[@id=178]">
			<br/> 
			<xsl:value-of select="detail[@id=178]/text()"/><!-- End date -->
		</xsl:if>
		<xsl:if test="url">
			<a><xsl:attribute name="href"><xsl:value-of select="url"/></xsl:attribute><xsl:value-of select="url"/></a>
		</xsl:if>
	</div>
</xsl:template>
</xsl:stylesheet>
