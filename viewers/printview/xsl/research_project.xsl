<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
<xsl:template name="research_project" match="record[type/@id=63]">
<div id="{id}" class="record">
	<xsl:if test="detail[@id=223]"><!-- thumbnail -->
		<xsl:for-each select="detail[@id=223]">
			<img src="{file/thumbURL}" class="thumbnail"/>
		</xsl:for-each>
	</xsl:if>
	<a target="_new">
		<xsl:attribute name="href">reftype_renderer/<xsl:value-of select="id"/></xsl:attribute>
		<xsl:value-of select="detail[@id=160]"/><!-- Title -->
	</a>
	(<xsl:value-of select="detail[@id=177]/text()"/><!-- Start Date -->
	- <xsl:value-of select="detail[@id=178]/text()"/><!-- End Date -->)
	<br/>
	<xsl:for-each select="detail[@id=249]/record"><!-- Person -->
		<xsl:value-of select="title"/>
			<xsl:if test="position() != last()">,
		</xsl:if>
	</xsl:for-each>
	<br/>
	<xsl:value-of select="detail[@id=303]"/><!-- Summary for web -->
</div>
</xsl:template>
</xsl:stylesheet>
