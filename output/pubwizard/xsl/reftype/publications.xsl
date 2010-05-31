<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
	<xsl:template name="publications" match="reference[reftype/@id=4 or reftype/@id=5 or reftype/@id=7 or reftype/@id=10 or reftype/@id=12 or reftype/@id=28 or reftype/@id=31]">
		<tr>
			<td>
				<xsl:if test="detail[@id=303] and detail[@id=223]"><!-- thumbnail -->
					<div style="float: right;">
						<xsl:for-each select="detail[@id=223]">
							<img src="{file_thumb_url}"/>
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
					&#160;[<a href="{file_fetch_url}"><xsl:value-of select="file_orig_name"/></a>, <xsl:value-of select="file_size"/>]
				</xsl:for-each>
				&#160;<a target="_new" href="reftype_renderer/{id}">details</a>
				<br/>
				<xsl:for-each select="pointer[@id=158]"><!-- creator -->
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
