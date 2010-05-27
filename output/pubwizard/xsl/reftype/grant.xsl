<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
	<xsl:template name="grant" match="reference[reftype/@id=61]">
		<tr>
			<td>
				<b><xsl:value-of select="detail[@id=160]"/></b><!-- title -->
				<xsl:if test="detail[@id=207]"><!-- funding amount -->
					:&#160;&#160;<xsl:value-of select="detail[@id=207]"/>
				</xsl:if>
				<br/>
				<xsl:if test="detail[@id=206]"><!-- funding type -->
					Type: <xsl:value-of select="detail[@id=206]"/>
					<br/>
				</xsl:if>
				<xsl:for-each select="pointer[@id=254]"><!-- funding source -->
					Source: <xsl:value-of select="title"/>
					<br/>
				</xsl:for-each>
				<xsl:if test="detail[@id=177]"><!-- start date -->
					<xsl:value-of select="detail[@id=177]/year"/>
					<xsl:if test="detail[@id=178]/year != detail[@id=177]/year">
						- <xsl:value-of select="detail[@id=178]/year"/>
					</xsl:if>
					<br/>
				</xsl:if>
				<br/>
			</td>
		</tr>
	</xsl:template>
</xsl:stylesheet>
