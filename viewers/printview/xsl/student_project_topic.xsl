<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
<xsl:template name="student_project_topic" match="record[type/@id=65]">
<div id="{id}" class="record">
	<a target="_new">
		<xsl:attribute name="href">rectype_renderer/<xsl:value-of select="id"/></xsl:attribute>
		<xsl:value-of select="detail[@id=160]"/><!-- Title -->
	</a>
	<br/>
	<xsl:for-each select="detail[@id=249]/record"><!-- Person -->
		<xsl:value-of select="title"/>
		<xsl:if test="position() != last()">,
		</xsl:if>
		</xsl:for-each>

		<xsl:value-of select="detail[@id=172]"/><!-- Place published -->
		<xsl:value-of select="detail[@id=177]/text()"/><!-- Start Date -->
		<xsl:value-of select="detail[@id=233]"/><!-- Start time -->
		<xsl:value-of select="detail[@id=234]"/><!-- End time -->
</div>
</xsl:template>
</xsl:stylesheet>
