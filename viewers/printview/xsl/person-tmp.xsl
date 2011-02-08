<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
<xsl:template name="person" match="record[type/@id=55]">
<div id="{id}" class="record">
	<xsl:if test="detail[@id=223]"><!-- thumbnail -->
		<div hrecord="{id}" hdetail="223" class="thumbnail">
			<xsl:for-each select="detail[@id=223]">
				<img src="{file/thumbURL}"/>
			</xsl:for-each>
		</div>
	</xsl:if>
	<div>
	<a target="_new" href="rectype_renderer/{id}">
		<xsl:value-of select="title"/>
	</a>
	</div>
	<xsl:if test="detail[@id=255]">
		<div hrecord="{id}" hdetail="255">
			<xsl:for-each select="detail[@id=255]"><!-- role -->
				<xsl:value-of select="text()"/>
				<xsl:if test="position() != last()">,&#160; </xsl:if>
			</xsl:for-each>
		</div>
	</xsl:if>
	<xsl:if test="detail[@id=191]">
		<b>Teaching and research interests:&#160;</b>
		<div hrecord="{id}" hdetail="191">
			<xsl:value-of select="detail[@id=191]"/>
		</div>
	</xsl:if>
</div>
</xsl:template>
</xsl:stylesheet>
