<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
	<xsl:template name="publications" match="record[type/@id=4 or type/@id=5 or type/@id=7 or type/@id=10 or type/@id=12 or type/@id=28 or type/@id=31]">
		<tr>
			<td>
				<xsl:if test="detail[@id=303] and detail[@id=223]"><!-- thumbnail -->
					<div style="float: right;">
						<xsl:for-each select="detail[@id=223]">
							<img src="{file/thumbURL}"/>
						</xsl:for-each>
					</div>
				</xsl:if>
				<b><xsl:value-of select="detail[@id=160]"/></b><!-- title -->
				<xsl:if test="string-length(url)">
					&#160;<a href="{url}">web</a>
				</xsl:if>
				<xsl:for-each select="detail[@id=256]"><!-- web links -->
					&#160;<a href="{text()}">link</a>
				</xsl:for-each>
				<xsl:for-each select="detail[@id=221]"><!-- associated file -->
					&#160;[<a href="{file/url}"><xsl:value-of select="origName"/></a>, <xsl:value-of select="size"/>]
				</xsl:for-each>
				&#160;<a target="_new" href="reftype_renderer/{id}">details</a>
				<br/>
				<xsl:for-each select="detail[@id=158]/record"><!-- creator -->
					<xsl:choose>
						<xsl:when test="position() = 1"></xsl:when>
						<xsl:when test="position() != last()">,&#160; </xsl:when>
						<xsl:otherwise> and&#160; </xsl:otherwise>
					</xsl:choose>
					<xsl:value-of select="detail[@id=160]"/>,&#160;<xsl:value-of select="detail[@id=291]"/>
				</xsl:for-each>
				<xsl:if test="detail[@id=159]"><!-- publication year -->
					&#160;<xsl:value-of select="detail[@id=159]"/>
				</xsl:if>
				<xsl:if test="detail[@id=303]"><!-- summary for web -->
					<br/>
					<xsl:value-of select="detail[@id=303]"/>
				</xsl:if>
				<br/>
				<br/>
			</td>
		</tr>
	</xsl:template>
</xsl:stylesheet>
